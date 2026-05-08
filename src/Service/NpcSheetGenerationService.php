<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Psr\Log\LoggerInterface;

/**
 * Queues and generates full NPC character sheets in the background.
 */
class NpcSheetGenerationService {

  protected LoggerInterface $logger;

  public function __construct(
    protected readonly Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    protected readonly ?AIApiService $aiApiService = NULL,
    protected readonly ?NpcPsychologyService $npcPsychologyService = NULL,
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler_npc_sheet_generation');
  }

  /**
   * Enqueue NPC sheet generation and ensure library placeholders exist.
   */
  public function enqueueNpcSheetGeneration(
    int $campaign_id,
    string $content_id,
    array $seed_data,
    bool $auto_start = TRUE
  ): void {
    $content_id = trim($content_id);
    if ($campaign_id <= 0 || $content_id === '') {
      return;
    }

    $seed_data = $this->normalizeSeedData($campaign_id, $content_id, $seed_data);
    $this->ensureNpcLibraryEntries($campaign_id, $content_id, $seed_data);

    $existing = $this->database->select('dc_npc_sheet_generation_jobs', 'j')
      ->fields('j', ['id', 'status'])
      ->condition('campaign_id', $campaign_id)
      ->condition('content_id', $content_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($existing && ($existing['status'] ?? '') === 'completed') {
      return;
    }

    $this->database->merge('dc_npc_sheet_generation_jobs')
      ->keys([
        'campaign_id' => $campaign_id,
        'content_id' => $content_id,
      ])
      ->fields([
        'npc_name' => $seed_data['name'] ?? $content_id,
        'status' => 'pending',
        'attempts' => 0,
        'payload_json' => json_encode([
          'campaign_id' => $campaign_id,
          'content_id' => $content_id,
          'seed_data' => $seed_data,
        ]),
        'error_message' => '',
        'started' => 0,
        'finished' => 0,
        'updated' => time(),
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => time()])
      ->execute();

    if ($auto_start) {
      $this->launchDetachedWorker();
    }
  }

  /**
   * Process queued jobs synchronously.
   */
  public function processPendingJobs(int $limit = 3): array {
    $limit = max(1, $limit);
    $summary = [
      'processed' => 0,
      'completed' => 0,
      'failed' => 0,
    ];

    $jobs = $this->database->select('dc_npc_sheet_generation_jobs', 'j')
      ->fields('j')
      ->condition('status', ['pending', 'failed'], 'IN')
      ->orderBy('updated', 'ASC')
      ->range(0, $limit)
      ->execute()
      ->fetchAllAssoc('id');

    foreach ($jobs as $job) {
      $claimed = $this->database->update('dc_npc_sheet_generation_jobs')
        ->fields([
          'status' => 'running',
          'attempts' => ((int) ($job->attempts ?? 0)) + 1,
          'started' => time(),
          'updated' => time(),
          'error_message' => '',
        ])
        ->condition('id', $job->id)
        ->condition('status', ['pending', 'failed'], 'IN')
        ->execute();

      if (!$claimed) {
        continue;
      }

      $summary['processed']++;

      try {
        $payload = json_decode($job->payload_json ?? '{}', TRUE) ?: [];
        $campaign_id = (int) ($payload['campaign_id'] ?? $job->campaign_id ?? 0);
        $content_id = (string) ($payload['content_id'] ?? $job->content_id ?? '');
        $seed_data = $payload['seed_data'] ?? [];
        $sheet = $this->generateNpcSheet($campaign_id, $content_id, $seed_data);
        $this->persistGeneratedSheet($campaign_id, $content_id, $sheet, $seed_data);

        $this->database->update('dc_npc_sheet_generation_jobs')
          ->fields([
            'status' => 'completed',
            'error_message' => '',
            'finished' => time(),
            'updated' => time(),
          ])
          ->condition('id', $job->id)
          ->execute();

        $summary['completed']++;
      }
      catch (\Throwable $e) {
        $this->database->update('dc_npc_sheet_generation_jobs')
          ->fields([
            'status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
            'finished' => time(),
            'updated' => time(),
          ])
          ->condition('id', $job->id)
          ->execute();

        $this->logger->warning('NPC sheet generation failed for @campaign/@content: @error', [
          '@campaign' => $job->campaign_id,
          '@content' => $job->content_id,
          '@error' => $e->getMessage(),
        ]);

        $summary['failed']++;
      }
    }

    return $summary;
  }

  /**
   * Launch a detached Drush worker so chat can continue immediately.
   */
  public function launchDetachedWorker(int $limit = 3): void {
    $has_pending = (bool) $this->database->select('dc_npc_sheet_generation_jobs', 'j')
      ->fields('j', ['id'])
      ->condition('status', 'pending')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$has_pending) {
      return;
    }

    $has_running = (bool) $this->database->select('dc_npc_sheet_generation_jobs', 'j')
      ->fields('j', ['id'])
      ->condition('status', 'running')
      ->condition('updated', time() - 300, '>')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($has_running) {
      return;
    }

    $drush_binary = $this->resolveDrushBinary();
    $project_root = $this->resolveProjectRoot();
    if ($drush_binary === '' || $project_root === '') {
      return;
    }

    $command = escapeshellarg($drush_binary)
      . ' dungeoncrawler_content:npc-sheet-worker --limit=' . max(1, $limit)
      . ' >/tmp/dungeoncrawler-npc-sheet-worker.log 2>&1 &';

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['file', '/tmp/dungeoncrawler-npc-sheet-launch.log', 'a'],
      2 => ['file', '/tmp/dungeoncrawler-npc-sheet-launch.log', 'a'],
    ];

    $process = @proc_open($command, $descriptors, $pipes, $project_root);
    if (is_resource($process)) {
      foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
          fclose($pipe);
        }
      }
      proc_close($process);
    }
  }

  /**
   * Generate a richer NPC sheet, using AI when available.
   */
  protected function generateNpcSheet(int $campaign_id, string $content_id, array $seed_data): array {
    if ($this->aiApiService) {
      try {
        return $this->generateNpcSheetWithAi($campaign_id, $content_id, $seed_data);
      }
      catch (\Throwable $e) {
        $this->logger->warning('AI NPC sheet generation failed for @content: @error', [
          '@content' => $content_id,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $this->generateFallbackNpcSheet($campaign_id, $content_id, $seed_data);
  }

  /**
   * Generate an NPC sheet via AI.
   */
  protected function generateNpcSheetWithAi(int $campaign_id, string $content_id, array $seed_data): array {
    $prompt = "Generate a Pathfinder 2e-friendly NPC character sheet as valid JSON.\n";
    $prompt .= "Campaign ID: {$campaign_id}\n";
    $prompt .= "NPC content id: {$content_id}\n";
    $prompt .= "Seed data:\n" . json_encode($seed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    $prompt .= "Return ONLY valid JSON with these keys:\n";
    $prompt .= "{\n";
    $prompt .= '  "name": string,' . "\n";
    $prompt .= '  "level": integer,' . "\n";
    $prompt .= '  "ancestry": string,' . "\n";
    $prompt .= '  "class": string,' . "\n";
    $prompt .= '  "occupation": string,' . "\n";
    $prompt .= '  "role": string,' . "\n";
    $prompt .= '  "alignment": string,' . "\n";
    $prompt .= '  "description": string,' . "\n";
    $prompt .= '  "backstory": string,' . "\n";
    $prompt .= '  "personality_traits": [string, ...],' . "\n";
    $prompt .= '  "motivations": string,' . "\n";
    $prompt .= '  "fears": string,' . "\n";
    $prompt .= '  "bonds": string,' . "\n";
    $prompt .= '  "abilities": {"strength": int, "dexterity": int, "constitution": int, "intelligence": int, "wisdom": int, "charisma": int},' . "\n";
    $prompt .= '  "stats": {"ac": int, "perception": int, "fortitude": int, "reflex": int, "will": int, "currentHp": int, "maxHp": int},' . "\n";
    $prompt .= '  "skills": [{"name": string, "modifier": int}, ...],' . "\n";
    $prompt .= '  "attacks": [{"name": string, "bonus": int, "damage": string}, ...],' . "\n";
    $prompt .= '  "equipment": [string, ...],' . "\n";
    $prompt .= '  "languages": [string, ...],' . "\n";
    $prompt .= '  "senses": [string, ...],' . "\n";
    $prompt .= '  "spells": [string, ...]' . "\n";
    $prompt .= "}\n";

    $result = $this->aiApiService->invokeModelDirect(
      $prompt,
      'dungeoncrawler_content',
      'npc_character_sheet_generation',
      ['campaign_id' => $campaign_id, 'content_id' => $content_id],
      [
        'system_prompt' => 'You generate complete but concise Pathfinder 2e NPC sheets as strict JSON. Stay grounded in the provided seed data. Do not wrap the JSON in markdown fences.',
        'max_tokens' => 1200,
        'skip_cache' => TRUE,
      ]
    );

    $response = trim((string) ($result['response'] ?? ''));
    $response = preg_replace('/^```json\s*|\s*```$/', '', $response) ?? $response;
    $parsed = json_decode($response, TRUE);
    if (!is_array($parsed)) {
      throw new \RuntimeException('AI did not return valid JSON for NPC sheet generation.');
    }

    return $this->normalizeGeneratedSheet($content_id, $seed_data, $parsed);
  }

  /**
   * Deterministic fallback when AI is unavailable.
   */
  protected function generateFallbackNpcSheet(int $campaign_id, string $content_id, array $seed_data): array {
    $stats = $seed_data['stats'] ?? [];
    $role = (string) ($seed_data['role'] ?? 'neutral');
    $level = max(1, (int) ($seed_data['level'] ?? $stats['level'] ?? 1));
    $class = (string) ($seed_data['class'] ?? ($role === 'merchant' ? 'Expert' : 'Commoner'));
    $ancestry = (string) ($seed_data['ancestry'] ?? 'Humanoid');
    $occupation = (string) ($seed_data['occupation'] ?? $role);

    $abilities = [
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
    ];

    if (in_array(strtolower($class), ['wizard', 'sage', 'scholar'], TRUE)) {
      $abilities['intelligence'] = 16;
      $abilities['wisdom'] = 12;
    }
    elseif (in_array(strtolower($role), ['merchant', 'contact'], TRUE)) {
      $abilities['charisma'] = 14;
      $abilities['intelligence'] = 12;
    }
    elseif (in_array(strtolower($role), ['villain', 'guard'], TRUE)) {
      $abilities['strength'] = 14;
      $abilities['constitution'] = 12;
    }

    $default_hp = max(8, 8 + ($level * 6));
    $sheet = [
      'name' => $seed_data['name'] ?? $content_id,
      'level' => $level,
      'ancestry' => $ancestry,
      'class' => $class,
      'occupation' => ucfirst(str_replace('_', ' ', $occupation)),
      'role' => $role,
      'alignment' => $seed_data['alignment'] ?? 'N',
      'description' => $seed_data['description'] ?? '',
      'backstory' => $seed_data['backstory'] ?? 'A local figure shaped by the current setting.',
      'personality_traits' => [
        'observant',
        in_array($role, ['merchant', 'contact'], TRUE) ? 'socially adept' : 'cautious',
      ],
      'motivations' => $seed_data['motivations'] ?? 'Stay safe, protect their interests, and respond to the party according to the situation.',
      'fears' => $seed_data['fears'] ?? 'Being outmatched or losing control of the situation.',
      'bonds' => $seed_data['bonds'] ?? 'Tied to the current room, town, or faction.',
      'abilities' => $abilities,
      'stats' => [
        'ac' => (int) ($stats['ac'] ?? 14 + max(0, $level - 1)),
        'perception' => (int) ($stats['perception'] ?? 4 + $level),
        'fortitude' => (int) ($stats['fortitude'] ?? 4 + $level),
        'reflex' => (int) ($stats['reflex'] ?? 4 + $level),
        'will' => (int) ($stats['will'] ?? 4 + $level),
        'currentHp' => (int) ($stats['currentHp'] ?? $stats['maxHp'] ?? $default_hp),
        'maxHp' => (int) ($stats['maxHp'] ?? $default_hp),
      ],
      'skills' => [
        ['name' => 'Perception', 'modifier' => (int) ($stats['perception'] ?? 4 + $level)],
        ['name' => 'Diplomacy', 'modifier' => in_array($role, ['merchant', 'contact'], TRUE) ? 6 + $level : 2 + $level],
        ['name' => 'Society', 'modifier' => 3 + $level],
      ],
      'attacks' => [
        ['name' => 'Strike', 'bonus' => 4 + $level, 'damage' => max(1, $level) . 'd6 bludgeoning'],
      ],
      'equipment' => $seed_data['equipment'] ?? ['coin purse', 'work clothes'],
      'languages' => $seed_data['languages'] ?? ['Common'],
      'senses' => $seed_data['senses'] ?? [],
      'spells' => $seed_data['spells'] ?? [],
    ];

    return $this->normalizeGeneratedSheet($content_id, $seed_data, $sheet);
  }

  /**
   * Normalize generated data into the NPC/library schema used by the module.
   */
  protected function normalizeGeneratedSheet(string $content_id, array $seed_data, array $sheet): array {
    $name = (string) ($sheet['name'] ?? $seed_data['name'] ?? $content_id);
    $level = max(1, (int) ($sheet['level'] ?? $seed_data['level'] ?? 1));
    $stats = $sheet['stats'] ?? [];

    return [
      'content_id' => $content_id,
      'name' => $name,
      'level' => $level,
      'ancestry' => (string) ($sheet['ancestry'] ?? $seed_data['ancestry'] ?? 'Humanoid'),
      'class' => (string) ($sheet['class'] ?? $seed_data['class'] ?? 'Commoner'),
      'occupation' => (string) ($sheet['occupation'] ?? $seed_data['occupation'] ?? ''),
      'role' => (string) ($sheet['role'] ?? $seed_data['role'] ?? 'neutral'),
      'alignment' => (string) ($sheet['alignment'] ?? $seed_data['alignment'] ?? 'N'),
      'description' => (string) ($sheet['description'] ?? $seed_data['description'] ?? ''),
      'backstory' => (string) ($sheet['backstory'] ?? $seed_data['backstory'] ?? ''),
      'personality_traits' => array_values(array_filter(array_map('strval', $sheet['personality_traits'] ?? []))),
      'motivations' => (string) ($sheet['motivations'] ?? $seed_data['motivations'] ?? ''),
      'fears' => (string) ($sheet['fears'] ?? $seed_data['fears'] ?? ''),
      'bonds' => (string) ($sheet['bonds'] ?? $seed_data['bonds'] ?? ''),
      'abilities' => [
        'strength' => (int) (($sheet['abilities']['strength'] ?? 10)),
        'dexterity' => (int) (($sheet['abilities']['dexterity'] ?? 10)),
        'constitution' => (int) (($sheet['abilities']['constitution'] ?? 10)),
        'intelligence' => (int) (($sheet['abilities']['intelligence'] ?? 10)),
        'wisdom' => (int) (($sheet['abilities']['wisdom'] ?? 10)),
        'charisma' => (int) (($sheet['abilities']['charisma'] ?? 10)),
      ],
      'stats' => [
        'ac' => (int) ($stats['ac'] ?? $seed_data['stats']['ac'] ?? 10),
        'perception' => (int) ($stats['perception'] ?? $seed_data['stats']['perception'] ?? 0),
        'fortitude' => (int) ($stats['fortitude'] ?? $seed_data['stats']['fortitude'] ?? 0),
        'reflex' => (int) ($stats['reflex'] ?? $seed_data['stats']['reflex'] ?? 0),
        'will' => (int) ($stats['will'] ?? $seed_data['stats']['will'] ?? 0),
        'currentHp' => (int) ($stats['currentHp'] ?? $stats['maxHp'] ?? $seed_data['stats']['currentHp'] ?? $seed_data['stats']['maxHp'] ?? 1),
        'maxHp' => (int) ($stats['maxHp'] ?? $seed_data['stats']['maxHp'] ?? $stats['currentHp'] ?? 1),
      ],
      'skills' => array_values($sheet['skills'] ?? []),
      'attacks' => array_values($sheet['attacks'] ?? []),
      'equipment' => array_values($sheet['equipment'] ?? $seed_data['equipment'] ?? []),
      'languages' => array_values($sheet['languages'] ?? $seed_data['languages'] ?? ['Common']),
      'senses' => array_values($sheet['senses'] ?? $seed_data['senses'] ?? []),
      'spells' => array_values($sheet['spells'] ?? []),
      'source' => 'generated_npc_sheet',
      'generation_status' => 'completed',
      'generated_at' => date('c'),
    ];
  }

  /**
   * Persist the generated sheet into libraries, campaign characters, and psychology.
   */
  protected function persistGeneratedSheet(int $campaign_id, string $content_id, array $sheet, array $seed_data): void {
    $schema_data = json_encode($sheet);
    $tags = json_encode(array_values(array_filter([
      'npc',
      $sheet['role'] ?? NULL,
      'generated_sheet',
      'ai_generated',
    ])));
    $now = time();

    $this->database->merge('dungeoncrawler_content_registry')
      ->keys([
        'content_type' => 'npc',
        'content_id' => $content_id,
      ])
      ->fields([
        'content_type' => 'npc',
        'content_id' => $content_id,
        'name' => $sheet['name'],
        'level' => $sheet['level'],
        'rarity' => 'common',
        'tags' => $tags,
        'schema_data' => $schema_data,
        'source_file' => 'generated_npc_sheet',
        'version' => '1.0',
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();

    $this->database->merge('dc_campaign_content_registry')
      ->keys([
        'campaign_id' => $campaign_id,
        'content_type' => 'npc',
        'content_id' => $content_id,
      ])
      ->fields([
        'campaign_id' => $campaign_id,
        'content_type' => 'npc',
        'content_id' => $content_id,
        'name' => $sheet['name'],
        'level' => $sheet['level'],
        'rarity' => 'common',
        'tags' => $tags,
        'schema_data' => $schema_data,
        'source_content_id' => $content_id,
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();

    $character_payload = $this->buildCampaignCharacterPayload($sheet);
    $state_payload = $this->buildCampaignStatePayload($sheet);

    $this->database->update('dc_campaign_characters')
      ->fields([
        'name' => $sheet['name'],
        'level' => $sheet['level'],
        'ancestry' => $sheet['ancestry'],
        'class' => $sheet['class'],
        'role' => $sheet['role'],
        'character_data' => json_encode($character_payload),
        'state_data' => json_encode($state_payload),
        'hp_current' => $sheet['stats']['currentHp'],
        'hp_max' => $sheet['stats']['maxHp'],
        'armor_class' => $sheet['stats']['ac'],
        'updated' => $now,
        'changed' => $now,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $content_id)
      ->execute();

    if ($this->npcPsychologyService) {
      $profile = $this->npcPsychologyService->getOrCreateProfile($campaign_id, $content_id, $seed_data + [
        'display_name' => $sheet['name'],
        'creature_type' => $content_id,
        'level' => $sheet['level'],
        'description' => $sheet['description'],
        'stats' => $sheet['stats'],
        'role' => $sheet['role'],
      ]);
      if ($profile) {
        $this->npcPsychologyService->updateProfile($campaign_id, $content_id, [
          'display_name' => $sheet['name'],
          'character_sheet' => $sheet,
          'personality_traits' => implode(', ', $sheet['personality_traits'] ?? []),
          'motivations' => $sheet['motivations'] ?? '',
          'fears' => $sheet['fears'] ?? '',
          'bonds' => $sheet['bonds'] ?? '',
        ]);
      }
    }
  }

  /**
   * Ensure the NPC exists in the library before the worker expands it.
   */
  protected function ensureNpcLibraryEntries(int $campaign_id, string $content_id, array $seed_data): void {
    $base_schema = [
      'content_id' => $content_id,
      'name' => $seed_data['name'] ?? $content_id,
      'ancestry' => $seed_data['ancestry'] ?? 'Humanoid',
      'class' => $seed_data['class'] ?? 'Commoner',
      'role' => $seed_data['role'] ?? 'neutral',
      'occupation' => $seed_data['occupation'] ?? '',
      'description' => $seed_data['description'] ?? '',
      'backstory' => $seed_data['backstory'] ?? '',
      'stats' => $seed_data['stats'] ?? [],
      'equipment' => $seed_data['equipment'] ?? [],
      'generation_status' => 'queued',
      'source' => 'ai_generated',
    ];
    $schema_data = json_encode($base_schema);
    $tags = json_encode(array_values(array_filter(['npc', $seed_data['role'] ?? NULL, 'ai_generated'])));
    $now = time();

    $this->database->merge('dungeoncrawler_content_registry')
      ->keys([
        'content_type' => 'npc',
        'content_id' => $content_id,
      ])
      ->fields([
        'content_type' => 'npc',
        'content_id' => $content_id,
        'name' => $base_schema['name'],
        'level' => (int) ($seed_data['level'] ?? 1),
        'rarity' => 'common',
        'tags' => $tags,
        'schema_data' => $schema_data,
        'source_file' => 'ai_generated',
        'version' => '1.0',
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();

    $this->database->merge('dc_campaign_content_registry')
      ->keys([
        'campaign_id' => $campaign_id,
        'content_type' => 'npc',
        'content_id' => $content_id,
      ])
      ->fields([
        'campaign_id' => $campaign_id,
        'content_type' => 'npc',
        'content_id' => $content_id,
        'name' => $base_schema['name'],
        'level' => (int) ($seed_data['level'] ?? 1),
        'rarity' => 'common',
        'tags' => $tags,
        'schema_data' => $schema_data,
        'source_content_id' => $content_id,
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();
  }

  /**
   * Normalize seed data before queueing.
   */
  protected function normalizeSeedData(int $campaign_id, string $content_id, array $seed_data): array {
    return [
      'campaign_id' => $campaign_id,
      'content_id' => $content_id,
      'entity_ref' => $seed_data['entity_ref'] ?? $content_id,
      'name' => trim((string) ($seed_data['name'] ?? $content_id)),
      'role' => (string) ($seed_data['role'] ?? 'neutral'),
      'team' => (string) ($seed_data['team'] ?? ''),
      'level' => (int) ($seed_data['level'] ?? $seed_data['stats']['level'] ?? 1),
      'ancestry' => (string) ($seed_data['ancestry'] ?? 'Humanoid'),
      'class' => (string) ($seed_data['class'] ?? 'Commoner'),
      'occupation' => (string) ($seed_data['occupation'] ?? ''),
      'description' => (string) ($seed_data['description'] ?? ''),
      'backstory' => (string) ($seed_data['backstory'] ?? ''),
      'attitude' => (string) ($seed_data['attitude'] ?? 'indifferent'),
      'stats' => is_array($seed_data['stats'] ?? NULL) ? $seed_data['stats'] : [],
      'equipment' => array_values($seed_data['equipment'] ?? []),
      'languages' => array_values($seed_data['languages'] ?? ['Common']),
      'senses' => array_values($seed_data['senses'] ?? []),
      'alignment' => (string) ($seed_data['alignment'] ?? 'N'),
      'motivations' => (string) ($seed_data['motivations'] ?? ''),
      'fears' => (string) ($seed_data['fears'] ?? ''),
      'bonds' => (string) ($seed_data['bonds'] ?? ''),
    ];
  }

  /**
   * Build a campaign-character-compatible payload for NPC rows.
   */
  protected function buildCampaignCharacterPayload(array $sheet): array {
    return [
      'basicInfo' => [
        'name' => $sheet['name'],
        'level' => $sheet['level'],
        'ancestry' => $sheet['ancestry'],
        'class' => $sheet['class'],
        'alignment' => $sheet['alignment'],
        'personality' => implode(', ', $sheet['personality_traits'] ?? []),
      ],
      'resources' => [
        'hitPoints' => [
          'current' => $sheet['stats']['currentHp'],
          'max' => $sheet['stats']['maxHp'],
          'temporary' => 0,
        ],
      ],
      'defenses' => [
        'armorClass' => $sheet['stats']['ac'],
        'fortitude' => $sheet['stats']['fortitude'],
        'reflex' => $sheet['stats']['reflex'],
        'will' => $sheet['stats']['will'],
        'perception' => $sheet['stats']['perception'],
      ],
      'inventory' => [
        'carried' => [],
        'equipped' => [],
        'stashed' => [],
        'worn' => [
          'weapons' => [],
          'armor' => [],
          'accessories' => [],
        ],
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      ],
      'display_name' => $sheet['name'],
      'ancestry' => $sheet['ancestry'],
      'class' => $sheet['class'],
      'occupation' => $sheet['occupation'],
      'level' => $sheet['level'],
      'description' => $sheet['description'],
      'backstory' => $sheet['backstory'],
      'role' => $sheet['role'],
      'alignment' => $sheet['alignment'],
      'stats' => $sheet['stats'],
      'abilities' => $sheet['abilities'],
      'equipment' => $sheet['equipment'],
      'languages' => $sheet['languages'],
      'senses' => $sheet['senses'],
      'skills' => $sheet['skills'],
      'attacks' => $sheet['attacks'],
      'spells' => $sheet['spells'],
      'personality_traits' => $sheet['personality_traits'],
      'motivations' => $sheet['motivations'],
      'fears' => $sheet['fears'],
      'bonds' => $sheet['bonds'],
    ];
  }

  /**
   * Build runtime state payload for NPC rows.
   */
  protected function buildCampaignStatePayload(array $sheet): array {
    return [
      'basicInfo' => [
        'name' => $sheet['name'],
        'level' => $sheet['level'],
        'ancestry' => $sheet['ancestry'],
        'class' => $sheet['class'],
      ],
      'resources' => [
        'hitPoints' => [
          'current' => $sheet['stats']['currentHp'],
          'max' => $sheet['stats']['maxHp'],
          'temporary' => 0,
        ],
      ],
      'inventory' => [
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      ],
      'description' => $sheet['description'],
      'stats' => $sheet['stats'],
      'equipment' => $sheet['equipment'],
      'role' => $sheet['role'],
    ];
  }

  /**
   * Resolve the project root for detached Drush execution.
   */
  protected function resolveProjectRoot(): string {
    return defined('DRUPAL_ROOT') ? dirname((string) DRUPAL_ROOT) : '';
  }

  /**
   * Resolve the local Drush binary path.
   */
  protected function resolveDrushBinary(): string {
    $project_root = $this->resolveProjectRoot();
    $candidates = [
      $project_root . '/vendor/bin/drush',
      (defined('DRUPAL_ROOT') ? DRUPAL_ROOT : '') . '/vendor/bin/drush',
    ];

    foreach ($candidates as $candidate) {
      if ($candidate !== '' && file_exists($candidate)) {
        return $candidate;
      }
    }

    return '';
  }

}
