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

  private const NPC_SHEET_SCHEMA_VERSION = '1.0.0';

  protected LoggerInterface $logger;

  public function __construct(
    protected readonly Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    protected readonly ?AIApiService $aiApiService = NULL,
    protected readonly ?NpcPsychologyService $npcPsychologyService = NULL,
    protected readonly ?StateValidationService $stateValidationService = NULL,
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
    $prompt .= '  "schema_version": string,' . "\n";
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
    $prompt .= '  "psychology": {' . "\n";
    $prompt .= '    "inner_conflict": string,' . "\n";
    $prompt .= '    "coping_mechanism": string,' . "\n";
    $prompt .= '    "stress_response": string,' . "\n";
    $prompt .= '    "insecurity": string,' . "\n";
    $prompt .= '    "secret": string,' . "\n";
    $prompt .= '    "desire": string,' . "\n";
    $prompt .= '    "need": string,' . "\n";
    $prompt .= '    "trigger": string,' . "\n";
    $prompt .= '    "anchor": string' . "\n";
    $prompt .= '  },' . "\n";
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
        'system_prompt' => 'You generate complete but concise Pathfinder 2e NPC sheets as strict JSON. Stay grounded in the provided seed data. Create nuanced, non-stigmatizing psychology using inner conflict, coping habits, stress responses, attachments, and insecurities. Do not assign clinical diagnoses unless the seed data explicitly requests one. Do not wrap the JSON in markdown fences.',
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
    $psychology = $this->normalizePsychologyPayload([
      'role' => $role,
      'class' => $class,
      'occupation' => $occupation,
      'psychology' => $seed_data['psychology'] ?? [],
    ], $seed_data, $content_id);
    $sheet = [
      'name' => $seed_data['name'] ?? $content_id,
      'schema_version' => self::NPC_SHEET_SCHEMA_VERSION,
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
      'psychology' => $psychology,
      'motivations' => $seed_data['motivations'] ?? $this->deriveMotivationsFromPsychology($psychology),
      'fears' => $seed_data['fears'] ?? $this->deriveFearsFromPsychology($psychology),
      'bonds' => $seed_data['bonds'] ?? $this->deriveBondsFromPsychology($psychology),
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
    $psychology = $this->normalizePsychologyPayload($sheet, $seed_data, $content_id);
    $ancestry = $this->firstNonEmptyString([
      $sheet['ancestry'] ?? '',
      $seed_data['ancestry'] ?? '',
      'Humanoid',
    ], 'Humanoid');
    $class = $this->firstNonEmptyString([
      $sheet['class'] ?? '',
      $seed_data['class'] ?? '',
      'Commoner',
    ], 'Commoner');
    $role = $this->firstNonEmptyString([
      $sheet['role'] ?? '',
      $seed_data['role'] ?? '',
      'neutral',
    ], 'neutral');
    $alignment = $this->firstNonEmptyString([
      $sheet['alignment'] ?? '',
      $seed_data['alignment'] ?? '',
      'N',
    ], 'N');
    $occupation = $this->firstNonEmptyString([
      $sheet['occupation'] ?? '',
      $seed_data['occupation'] ?? '',
      $role,
      'resident',
    ], 'resident');
    $personality_traits = array_values(array_filter(array_map('strval', $sheet['personality_traits'] ?? [])));
    if ($personality_traits === []) {
      $personality_traits = ['guarded'];
    }
    $equipment = $this->normalizeStringList($sheet['equipment'] ?? $seed_data['equipment'] ?? []);
    $languages = $this->normalizeStringList($sheet['languages'] ?? $seed_data['languages'] ?? ['Common']);
    if ($languages === []) {
      $languages = ['Common'];
    }
    $senses = $this->normalizeStringList($sheet['senses'] ?? $seed_data['senses'] ?? []);
    $spells = $this->normalizeStringList($sheet['spells'] ?? []);
    $normalized_stats = [
      'ac' => max(1, (int) ($stats['ac'] ?? $seed_data['stats']['ac'] ?? 10)),
      'perception' => (int) ($stats['perception'] ?? $seed_data['stats']['perception'] ?? 0),
      'fortitude' => (int) ($stats['fortitude'] ?? $seed_data['stats']['fortitude'] ?? 0),
      'reflex' => (int) ($stats['reflex'] ?? $seed_data['stats']['reflex'] ?? 0),
      'will' => (int) ($stats['will'] ?? $seed_data['stats']['will'] ?? 0),
      'currentHp' => max(0, (int) ($stats['currentHp'] ?? $stats['maxHp'] ?? $seed_data['stats']['currentHp'] ?? $seed_data['stats']['maxHp'] ?? 1)),
      'maxHp' => max(1, (int) ($stats['maxHp'] ?? $seed_data['stats']['maxHp'] ?? $stats['currentHp'] ?? 1)),
    ];

    return $this->finalizeNpcSheetContract([
      'schema_version' => self::NPC_SHEET_SCHEMA_VERSION,
      'content_id' => $content_id,
      'name' => $name,
      'level' => $level,
      'ancestry' => $ancestry,
      'class' => $class,
      'occupation' => $occupation,
      'role' => $role,
      'alignment' => $alignment,
      'description' => (string) ($sheet['description'] ?? $seed_data['description'] ?? ''),
      'backstory' => (string) ($sheet['backstory'] ?? $seed_data['backstory'] ?? ''),
      'personality_traits' => $personality_traits,
      'psychology' => $psychology,
      'motivations' => $this->firstNonEmptyString([
        $sheet['motivations'] ?? '',
        $seed_data['motivations'] ?? '',
        $this->deriveMotivationsFromPsychology($psychology),
      ]),
      'fears' => $this->firstNonEmptyString([
        $sheet['fears'] ?? '',
        $seed_data['fears'] ?? '',
        $this->deriveFearsFromPsychology($psychology),
      ]),
      'bonds' => $this->firstNonEmptyString([
        $sheet['bonds'] ?? '',
        $seed_data['bonds'] ?? '',
        $this->deriveBondsFromPsychology($psychology),
      ]),
      'abilities' => [
        'strength' => (int) (($sheet['abilities']['strength'] ?? 10)),
        'dexterity' => (int) (($sheet['abilities']['dexterity'] ?? 10)),
        'constitution' => (int) (($sheet['abilities']['constitution'] ?? 10)),
        'intelligence' => (int) (($sheet['abilities']['intelligence'] ?? 10)),
        'wisdom' => (int) (($sheet['abilities']['wisdom'] ?? 10)),
        'charisma' => (int) (($sheet['abilities']['charisma'] ?? 10)),
      ],
      'stats' => $normalized_stats,
      'skills' => array_values($sheet['skills'] ?? []),
      'attacks' => array_values($sheet['attacks'] ?? []),
      'equipment' => $equipment,
      'languages' => $languages,
      'senses' => $senses,
      'spells' => $spells,
      'source' => 'generated_npc_sheet',
      'generation_status' => 'completed',
      'generated_at' => date('c'),
    ]);
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
    $base_schema = $this->buildQueuedNpcSheetContract($content_id, $seed_data);
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
      'psychology' => is_array($seed_data['psychology'] ?? NULL) ? $seed_data['psychology'] : [],
    ];
  }

  /**
   * Normalize and validate the final NPC sheet contract.
   */
  protected function finalizeNpcSheetContract(array $sheet): array {
    if ($this->stateValidationService) {
      $validation = $this->stateValidationService->validateNpcSheet($sheet);
      if (!$validation['valid']) {
        throw new \RuntimeException('NPC sheet contract violation: ' . implode('; ', $validation['errors']));
      }
    }

    return $sheet;
  }

  /**
   * Build a contract-valid queued NPC sheet placeholder.
   */
  protected function buildQueuedNpcSheetContract(string $content_id, array $seed_data): array {
    $sheet = $this->generateFallbackNpcSheet(0, $content_id, $seed_data);
    $sheet['generation_status'] = 'queued';
    $sheet['source'] = 'ai_generated';
    return $this->finalizeNpcSheetContract($sheet);
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
          'armor' => NULL,
          'shield' => NULL,
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
      'psychology' => $sheet['psychology'] ?? [],
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
      'psychology' => $sheet['psychology'] ?? [],
      'motivations' => $sheet['motivations'] ?? '',
      'fears' => $sheet['fears'] ?? '',
      'bonds' => $sheet['bonds'] ?? '',
    ];
  }

  /**
   * Normalize rich psychology fields, falling back to deterministic templates.
   */
  private function normalizePsychologyPayload(array $sheet, array $seed_data, string $content_id): array {
    $role = (string) ($sheet['role'] ?? $seed_data['role'] ?? 'neutral');
    $class = (string) ($sheet['class'] ?? $seed_data['class'] ?? 'Commoner');
    $occupation = (string) ($sheet['occupation'] ?? $seed_data['occupation'] ?? $role);
    $fallback = $this->buildFallbackPsychology($content_id, $role, $class, $occupation);
    $provided = is_array($sheet['psychology'] ?? NULL)
      ? $sheet['psychology']
      : (is_array($seed_data['psychology'] ?? NULL) ? $seed_data['psychology'] : []);

    $normalized = [];
    foreach (['inner_conflict', 'coping_mechanism', 'stress_response', 'insecurity', 'secret', 'desire', 'need', 'trigger', 'anchor'] as $field) {
      $normalized[$field] = $this->firstNonEmptyString([
        $provided[$field] ?? '',
        $fallback[$field] ?? '',
      ]);
    }

    return $normalized;
  }

  /**
   * Build a deterministic but richer fallback psychology profile.
   */
  private function buildFallbackPsychology(string $content_id, string $role, string $class, string $occupation): array {
    $role_key = strtolower(trim($role));
    $class_label = trim($class) !== '' ? $class : 'Commoner';
    $occupation_label = trim($occupation) !== '' ? $occupation : $role_key;
    $templates = [
      'ally' => [
        'inner_conflict' => [
          'They want to be dependable, but they quietly resent how often everyone leans on them.',
          'They crave closeness, but every promise they make reminds them how badly failure would hurt.',
        ],
        'coping_mechanism' => [
          'They stay busy solving other people\'s problems so they do not have to sit with their own doubts.',
          'They overprepare and rehearse difficult conversations before anyone can catch them off guard.',
        ],
        'stress_response' => [
          'Under stress they become protective, overextend themselves, and stop asking for help.',
          'Pressure makes them terse and self-sacrificing, even when they need rest.',
        ],
        'insecurity' => [
          'They fear being useful only when they are needed in a crisis.',
          'They worry their kindness is mistaken for weakness.',
        ],
        'secret' => [
          "They still feel guilty about one person they could not protect.",
          "They keep a private plan for leaving before anyone can rely on them too deeply.",
        ],
        'desire' => [
          'To keep their circle safe without losing themselves in the process.',
          'To prove they can be steady without becoming invisible.',
        ],
        'need' => [
          'To believe they deserve care even when they are not being useful.',
          'To trust others enough to share the weight.',
        ],
        'trigger' => [
          'Watching someone dismiss vulnerability as weakness.',
          'Being told they are responsible for everyone in the room.',
        ],
        'anchor' => [
          "The people they have chosen to protect around the {$occupation_label}.",
          "A promise they made to someone tied to the {$occupation_label}.",
        ],
      ],
      'contact' => [
        'inner_conflict' => [
          'They want to be trusted, but survival has taught them to keep an escape route.',
          'They enjoy being indispensable, but hate how that keeps them tangled in everyone else\'s secrets.',
        ],
        'coping_mechanism' => [
          'They deflect with wit and selective honesty whenever a conversation cuts too close.',
          'They compartmentalize everything into favors, debts, and manageable pieces.',
        ],
        'stress_response' => [
          'When cornered, they grow slippery, vague, and hyperaware of leverage.',
          'Stress makes them bargain first, then disappear if the room turns volatile.',
        ],
        'insecurity' => [
          'They fear becoming disposable once their information loses value.',
          'They worry that intimacy will cost them the one advantage they control.',
        ],
        'secret' => [
          'They are quietly protecting someone whose name would change the power balance in town.',
          'They have already sold part of the truth to a rival and regret it.',
        ],
        'desire' => [
          'To stay informed, relevant, and one move ahead of the next betrayal.',
          'To turn knowledge into lasting security.',
        ],
        'need' => [
          'To find one relationship that does not feel transactional.',
          'To admit that safety built only on leverage never feels safe enough.',
        ],
        'trigger' => [
          'Public demands for absolute loyalty.',
          'Anyone insisting that trust should be immediate.',
        ],
        'anchor' => [
          "A fragile network of favors tied to the {$occupation_label}.",
          "One person who still sees them as more than a source inside the {$occupation_label}.",
        ],
      ],
      'merchant' => [
        'inner_conflict' => [
          'They want stability, but every opportunity to grow feels too dangerous to ignore.',
          'They like being seen as generous, but panic whenever generosity threatens their margin.',
        ],
        'coping_mechanism' => [
          'They soothe themselves by counting stock, rehearsing numbers, and controlling every small risk.',
          'They turn uncertainty into negotiation, even when the room needs warmth more than terms.',
        ],
        'stress_response' => [
          'Stress makes them brisk, guarded, and obsessed with contingency plans.',
          'Under pressure they start treating relationships like contracts they can enforce.',
        ],
        'insecurity' => [
          'They fear one bad season will undo everything they built.',
          'They worry that people like the goods more than the person selling them.',
        ],
        'secret' => [
          "They are extending credit to someone they should have cut off long ago.",
          "They hide how close one recent loss came to ruining the {$occupation_label}.",
        ],
        'desire' => [
          'To build a business no one can casually take from them.',
          'To convert hustle into real, durable security.',
        ],
        'need' => [
          'To feel safe enough to stop measuring every interaction for loss.',
          'To remember that trust sometimes pays better than control.',
        ],
        'trigger' => [
          'Sudden chaos that threatens property or reputation.',
          'People acting as though their labor should be free or invisible.',
        ],
        'anchor' => [
          "The staff, regulars, and routines that keep the {$occupation_label} standing.",
          "A ledger, a family expectation, and the reputation wrapped around the {$occupation_label}.",
        ],
      ],
      'villain' => [
        'inner_conflict' => [
          'They crave total control, but every victory deepens the fear that they are still vulnerable underneath it.',
          'They despise dependence, yet secretly hunger for devotion they can believe is real.',
        ],
        'coping_mechanism' => [
          'They manage anxiety by overplanning, tightening control, and punishing uncertainty.',
          'They turn every emotional threat into a test of dominance before it can touch them.',
        ],
        'stress_response' => [
          'Pressure makes them colder, sharper, and more willing to humiliate others first.',
          'When stressed, they become controlling, retaliatory, and unable to tolerate dissent.',
        ],
        'insecurity' => [
          'They fear public irrelevance more than death.',
          'They cannot bear being seen as ordinary, weak, or replaceable.',
        ],
        'secret' => [
          'They still measure themself against one humiliation they never truly survived.',
          'They keep a private record of every slight because forgetting would feel like surrender.',
        ],
        'desire' => [
          'To become untouchable and impossible to dismiss.',
          'To shape the world until it can no longer surprise or shame them.',
        ],
        'need' => [
          'To accept that control cannot substitute for trust or belonging.',
          'To face the part of themself that still feels small without lashing out.',
        ],
        'trigger' => [
          'Being laughed at, contradicted, or made to look foolish in public.',
          'Any reminder that loyalty bought through fear is unstable.',
        ],
        'anchor' => [
          "A cause, symbol, or chosen heir tied to the {$occupation_label} they refuse to lose.",
          "The single person or ambition that still gives their ambition emotional weight.",
        ],
      ],
      'neutral' => [
        'inner_conflict' => [
          'They want a quiet life, but they are drawn to the drama they pretend to avoid.',
          'They value caution, yet resent how much caution has kept them from acting decisively.',
        ],
        'coping_mechanism' => [
          'They minimize, joke, and redirect whenever conversation threatens to become personal.',
          'They keep life manageable by reducing everything to routine and practical tasks.',
        ],
        'stress_response' => [
          'When stressed they withdraw, go quiet, and hope the storm passes without naming what they feel.',
          'Pressure makes them indecisive, watchful, and overly concerned with avoiding blame.',
        ],
        'insecurity' => [
          'They worry they will matter only when something goes wrong.',
          'They fear choosing a side and regretting it forever.',
        ],
        'secret' => [
          "They know more about one recent conflict than they admit aloud.",
          "They are quietly waiting for one chance to reinvent themself through the {$occupation_label}.",
        ],
        'desire' => [
          'To stay secure without being dragged under by other people\'s chaos.',
          'To keep enough freedom that no one can corner them into a life they hate.',
        ],
        'need' => [
          'To accept that neutrality is also a choice with consequences.',
          'To risk being known rather than living entirely through caution.',
        ],
        'trigger' => [
          'Sudden demands to pick a side immediately.',
          'Being treated like background when they know they are carrying context others missed.',
        ],
        'anchor' => [
          "A familiar routine and a handful of people around the {$occupation_label}.",
          "The place, work, or promise that still makes daily life feel worth preserving.",
        ],
      ],
    ];

    $template = $templates[$role_key] ?? $templates['neutral'];
    $psychology = [];
    foreach ($template as $field => $options) {
      $psychology[$field] = $this->selectPsychologyOption($content_id . '|' . $class_label . '|' . $occupation_label, $field, $options);
    }

    return $psychology;
  }

  /**
   * Select a deterministic option from a pool.
   */
  private function selectPsychologyOption(string $seed, string $bucket, array $options): string {
    if ($options === []) {
      return '';
    }

    $index = ((int) sprintf('%u', crc32($seed . '|' . $bucket))) % count($options);
    return (string) ($options[$index] ?? '');
  }

  /**
   * Derive legacy motivation text from structured psychology.
   */
  private function deriveMotivationsFromPsychology(array $psychology): string {
    return $this->firstNonEmptyString([
      trim(($psychology['desire'] ?? '') . '; ' . ($psychology['need'] ?? '')),
      $psychology['desire'] ?? '',
      $psychology['need'] ?? '',
    ]);
  }

  /**
   * Derive legacy fear text from structured psychology.
   */
  private function deriveFearsFromPsychology(array $psychology): string {
    return $this->firstNonEmptyString([
      trim(($psychology['insecurity'] ?? '') . '; Triggered by ' . ($psychology['trigger'] ?? '')),
      $psychology['insecurity'] ?? '',
      $psychology['trigger'] ?? '',
    ]);
  }

  /**
   * Derive legacy bond text from structured psychology.
   */
  private function deriveBondsFromPsychology(array $psychology): string {
    return $this->firstNonEmptyString([
      $psychology['anchor'] ?? '',
      $psychology['need'] ?? '',
    ]);
  }

  /**
   * Return the first non-empty scalar string.
   */
  private function firstNonEmptyString(array $values, string $default = ''): string {
    foreach ($values as $value) {
      if (!is_scalar($value)) {
        continue;
      }
      $normalized = trim((string) $value);
      if ($normalized !== '') {
        return $normalized;
      }
    }

    return $default;
  }

  /**
   * Normalize a scalar list into a trimmed string list.
   */
  private function normalizeStringList($values): array {
    if (!is_array($values)) {
      return [];
    }

    $normalized = [];
    foreach ($values as $value) {
      if (!is_scalar($value)) {
        continue;
      }
      $trimmed = trim((string) $value);
      if ($trimmed !== '') {
        $normalized[] = $trimmed;
      }
    }

    return array_values($normalized);
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
