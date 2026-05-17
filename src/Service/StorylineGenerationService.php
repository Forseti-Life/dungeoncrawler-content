<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\ai_conversation\Service\AIApiService;
use Psr\Log\LoggerInterface;

/**
 * Generates storyline definitions and aligned quest-template blueprints.
 */
class StorylineGenerationService {

  private const QUEST_TEMPLATE_VERSION = '1.0.0';
  private const STORYLINE_EXPANSION_JOB_SCHEMA_VERSION = 'storyline-expansion-job-v1';
  private const GENERATED_STORYLINE_SLUG_MAX_LENGTH = 48;
  private const GENERATED_TEMPLATE_ID_MAX_LENGTH = 64;

  protected LoggerInterface $logger;

  public function __construct(
    protected readonly Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    protected readonly ?AIApiService $aiApiService,
    protected readonly StorylineManagerService $storylineManager,
    protected readonly CampaignStateService $campaignStateService,
    protected readonly TreasureByLevelService $treasureByLevelService,
    protected readonly UuidInterface $uuid,
    protected readonly ?RelationshipManagerService $relationshipManager = NULL,
    protected readonly ?QuestGeneratorService $questGenerator = NULL,
    protected readonly ?StateValidationService $stateValidationService = NULL,
    protected readonly ?NpcSheetGenerationService $npcSheetGenerationService = NULL,
    protected readonly ?StorylineRealizationService $storylineRealizationService = NULL,
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Generate a storyline package from prompt and campaign context.
   */
  public function generateStorylinePackage(int $campaign_id, array $request): array {
    $request = $this->normalizeRequest($request);
    $context = $this->buildGenerationContext($campaign_id, $request);

    if ($this->aiApiService) {
      try {
        $package = $this->generatePackageWithAi($campaign_id, $request, $context);
        return $this->normalizeGeneratedPackage($campaign_id, $request, $context, $package, 'ai');
      }
      catch (\Throwable $e) {
        $this->logger->warning('AI storyline generation failed for campaign {campaign_id}: {error}', [
          'campaign_id' => $campaign_id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    $package = $this->generateFallbackPackage($campaign_id, $request, $context);
    return $this->normalizeGeneratedPackage($campaign_id, $request, $context, $package, 'fallback');
  }

  /**
   * Generate the minimal synchronous storyline bootstrap package.
   */
  public function generateStorylineBootstrapPackage(int $campaign_id, array $request): array {
    $request = $this->normalizeBootstrapRequest($request);
    $this->assertValidBootstrapRequest($request);
    $context = $this->buildGenerationContext($campaign_id, $request);

    if ($this->aiApiService) {
      try {
        $package = $this->generateBootstrapPackageWithAi($campaign_id, $request, $context);
        return $this->normalizeGeneratedBootstrapPackage($campaign_id, $request, $context, $package, 'ai');
      }
      catch (\Throwable $e) {
        $this->logger->warning('AI storyline bootstrap failed for campaign {campaign_id}: {error}', [
          'campaign_id' => $campaign_id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    $package = $this->generateFallbackBootstrapPackage($campaign_id, $request, $context);
    return $this->normalizeGeneratedBootstrapPackage($campaign_id, $request, $context, $package, 'fallback');
  }

  /**
   * Create a minimal storyline immediately, then queue async expansion.
   */
  public function bootstrapCampaignStoryline(int $campaign_id, array $request): array {
    $request = $this->normalizeBootstrapRequest($request);
    $this->assertValidBootstrapRequest($request);
    $package = $this->generateStorylineBootstrapPackage($campaign_id, $request);
    $saved_templates = $this->persistQuestTemplates($package['quest_templates'] ?? []);
    $storyline = $this->storylineManager->createCampaignStoryline(
      $campaign_id,
      $package['storyline_definition'] ?? [],
      $request + ['status' => 'bootstrapping']
    );
    $this->realizeStorylineAssets($campaign_id, $storyline);
    $this->realizeStorylineNpcs($campaign_id, $storyline);
    $initial_quest = $this->materializeBootstrapQuest($campaign_id, $storyline, $request);

    if ($this->relationshipManager !== NULL) {
      $this->relationshipManager->seedLibraryRelationships($campaign_id);
      $this->relationshipManager->seedStorylineContacts($campaign_id, $storyline);
      $this->relationshipManager->refreshCampaignStorylineContacts($campaign_id, 'npc_tavern_keeper');
    }

    $queued = $this->enqueueStorylineExpansion($campaign_id, (string) ($storyline['storyline_id'] ?? ''), [
      'prompt' => (string) (($package['storyline_definition']['metadata']['goal'] ?? '') ?: $request['prompt']),
      'name' => (string) ($package['storyline_definition']['name'] ?? $request['name']),
      'template_id' => (string) ($package['storyline_definition']['template_id'] ?? ''),
      'level_range' => (string) ($package['storyline_definition']['level_range'] ?? $request['level_range']),
      'tone' => (string) ($request['tone'] ?? ''),
      'theme' => (string) ($request['theme'] ?? ''),
      'source' => 'storyline-expansion',
      'status' => 'available',
      'is_primary' => !empty($request['is_primary']),
      'activate' => !empty($request['activate']),
      'speaker_npc_id' => (string) ($request['speaker_npc_id'] ?? ''),
      'speaker_name' => (string) ($request['speaker_name'] ?? ''),
      'lead_location_id' => (string) ($request['lead_location_id'] ?? ''),
      'entry_dungeon_id' => (string) (($package['campaign_outline']['entry_dungeon']['dungeon_id'] ?? '')),
      'entry_room_id' => (string) (($package['campaign_outline']['entry_dungeon']['entrance_room_id'] ?? '')),
      'first_quest_id' => (string) (($package['storyline_definition']['questline']['primary_quest_id'] ?? '')),
    ]);

    return [
      'storyline' => $storyline,
      'storyline_definition' => $package['storyline_definition'] ?? [],
      'quest_templates' => $saved_templates,
      'initial_quest' => $initial_quest,
      'generation_source' => $package['generation_source'] ?? 'fallback',
      'campaign_outline' => $package['campaign_outline'] ?? [],
      'expansion_queued' => $queued,
    ];
  }

  /**
   * Queue deferred storyline expansion for detached processing.
   */
  public function enqueueStorylineExpansion(int $campaign_id, string $storyline_id, array $request, bool $auto_start = TRUE): bool {
    $storyline_id = trim($storyline_id);
    if ($campaign_id <= 0 || $storyline_id === '') {
      return FALSE;
    }

    $request = $this->normalizeRequest($request);
    $payload = $this->buildExpansionJobPayload($campaign_id, $storyline_id, $request);
    $this->database->merge('dc_storyline_expansion_jobs')
      ->keys([
        'campaign_id' => $campaign_id,
        'storyline_id' => $storyline_id,
      ])
      ->fields([
        'status' => 'pending',
        'attempts' => 0,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'error_message' => '',
        'started' => 0,
        'finished' => 0,
        'updated' => time(),
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => time()])
      ->execute();

    if ($auto_start) {
      $this->launchDetachedExpansionWorker();
    }

    return TRUE;
  }

  /**
   * Process queued expansion jobs.
   */
  public function processPendingExpansionJobs(int $limit = 2): array {
    $limit = max(1, $limit);
    $summary = [
      'processed' => 0,
      'completed' => 0,
      'failed' => 0,
    ];

    $jobs = $this->database->select('dc_storyline_expansion_jobs', 'j')
      ->fields('j')
      ->condition('status', ['pending', 'failed'], 'IN')
      ->orderBy('updated', 'ASC')
      ->range(0, $limit)
      ->execute()
      ->fetchAllAssoc('id');

    foreach ($jobs as $job) {
      $claimed = $this->database->update('dc_storyline_expansion_jobs')
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
        $payload = $this->normalizeExpansionJobPayload(json_decode((string) ($job->payload_json ?? '{}'), TRUE) ?: []);
        $campaign_id = (int) ($payload['campaign_id'] ?? $job->campaign_id ?? 0);
        $storyline_id = trim((string) ($payload['storyline_id'] ?? $job->storyline_id ?? ''));
        $request = is_array($payload['request'] ?? NULL) ? $payload['request'] : [];
        $package = $this->generateStorylinePackage($campaign_id, $request);
        $this->persistQuestTemplates($package['quest_templates'] ?? []);
        $storyline = $this->storylineManager->replaceCampaignStorylineDefinition(
          $campaign_id,
          $storyline_id,
          $package['storyline_definition'] ?? [],
          ['status' => (string) ($request['status'] ?? 'available')]
        );
        if ($storyline === NULL) {
          throw new \RuntimeException('Storyline not found for queued expansion.');
        }

        if ($this->relationshipManager !== NULL) {
          $this->relationshipManager->seedLibraryRelationships($campaign_id);
          $this->relationshipManager->seedStorylineContacts($campaign_id, $storyline);
          $this->relationshipManager->refreshCampaignStorylineContacts($campaign_id, 'npc_tavern_keeper');
        }
        $this->realizeStorylineAssets($campaign_id, $storyline);
        $this->realizeStorylineNpcs($campaign_id, $storyline);

        $this->database->update('dc_storyline_expansion_jobs')
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
        $this->database->update('dc_storyline_expansion_jobs')
          ->fields([
            'status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
            'finished' => time(),
            'updated' => time(),
          ])
          ->condition('id', $job->id)
          ->execute();

        $this->logger->warning('Storyline expansion failed for {campaign_id}/{storyline_id}: {error}', [
          'campaign_id' => $job->campaign_id ?? 0,
          'storyline_id' => $job->storyline_id ?? '',
          'error' => $e->getMessage(),
        ]);

        $summary['failed']++;
      }
    }

    return $summary;
  }

  /**
   * Launch a detached worker so storyline expansion does not block chat.
   */
  public function launchDetachedExpansionWorker(int $limit = 2): void {
    $has_pending = (bool) $this->database->select('dc_storyline_expansion_jobs', 'j')
      ->fields('j', ['id'])
      ->condition('status', 'pending')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$has_pending) {
      return;
    }

    $has_running = (bool) $this->database->select('dc_storyline_expansion_jobs', 'j')
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
      . ' dungeoncrawler_content:storyline-expansion-worker --limit=' . max(1, $limit)
      . ' >/tmp/dungeoncrawler-storyline-expansion-worker.log 2>&1 &';

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['file', '/tmp/dungeoncrawler-storyline-expansion-launch.log', 'a'],
      2 => ['file', '/tmp/dungeoncrawler-storyline-expansion-launch.log', 'a'],
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
   * Persist generated quest templates referenced by the storyline package.
   *
   * @return array<int, array<string, mixed>>
   *   Saved quest template summaries.
   */
  public function persistQuestTemplates(array $quest_templates): array {
    $saved = [];

    foreach ($quest_templates as $template) {
      if (!is_array($template) || empty($template['template_id'])) {
        continue;
      }

      $template_id = (string) $template['template_id'];
      $fields = [
        'name' => (string) ($template['name'] ?? $template_id),
        'description' => (string) ($template['description'] ?? ''),
        'quest_type' => (string) ($template['quest_type'] ?? 'main'),
        'level_min' => max(1, (int) ($template['level_min'] ?? 1)),
        'level_max' => max(1, (int) ($template['level_max'] ?? ($template['level_min'] ?? 1))),
        'tags' => json_encode(array_values(array_map('strval', is_array($template['tags'] ?? NULL) ? $template['tags'] : [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'objectives_schema' => json_encode(is_array($template['objectives_schema'] ?? NULL) ? $template['objectives_schema'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'rewards_schema' => json_encode(is_array($template['rewards_schema'] ?? NULL) ? $template['rewards_schema'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'prerequisites' => json_encode(is_array($template['prerequisites'] ?? NULL) ? $template['prerequisites'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'story_impact' => json_encode(is_array($template['story_impact'] ?? NULL) ? $template['story_impact'] : ['generated' => TRUE], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'estimated_duration_minutes' => max(5, (int) ($template['estimated_duration_minutes'] ?? 20)),
        'updated' => time(),
        'version' => (string) ($template['version'] ?? self::QUEST_TEMPLATE_VERSION),
      ];

      $this->database->merge('dungeoncrawler_content_quest_templates')
        ->keys(['template_id' => $template_id])
        ->fields($fields)
        ->expression('created', 'COALESCE(created, :created)', [':created' => time()])
        ->execute();

      $saved[] = [
        'template_id' => $template_id,
        'name' => $fields['name'],
        'quest_type' => $fields['quest_type'],
        'level_min' => $fields['level_min'],
        'level_max' => $fields['level_max'],
      ];
    }

    return $saved;
  }

  /**
   * Normalize incoming request payload for generation.
   */
  protected function normalizeRequest(array $request): array {
    $prompt = trim((string) ($request['prompt'] ?? $request['goal'] ?? $request['story_prompt'] ?? ''));
    if ($prompt === '') {
      throw new \InvalidArgumentException('Storyline generation requires a prompt.', 400);
    }

    return [
      'prompt' => $prompt,
      'name' => trim((string) ($request['name'] ?? '')),
      'level_range' => trim((string) ($request['level_range'] ?? '1-4')),
      'tone' => trim((string) ($request['tone'] ?? 'mythic dark fantasy')),
      'theme' => trim((string) ($request['theme'] ?? '')),
      'source' => trim((string) ($request['source'] ?? 'storyline-generator')),
      'template_id' => trim((string) ($request['template_id'] ?? '')),
      'entry_dungeon_id' => trim((string) ($request['entry_dungeon_id'] ?? '')),
      'entry_room_id' => trim((string) ($request['entry_room_id'] ?? '')),
      'first_quest_id' => trim((string) ($request['first_quest_id'] ?? '')),
      'speaker_npc_id' => trim((string) ($request['speaker_npc_id'] ?? '')),
      'speaker_name' => trim((string) ($request['speaker_name'] ?? '')),
      'lead_location_id' => trim((string) ($request['lead_location_id'] ?? '')),
      'tags' => array_values(array_filter(array_map('strval', is_array($request['tags'] ?? NULL) ? $request['tags'] : []))),
      'activate' => !empty($request['activate']),
      'is_primary' => !empty($request['is_primary']),
      'status' => trim((string) ($request['status'] ?? 'available')),
      'priority' => isset($request['priority']) ? (int) $request['priority'] : 0,
    ];
  }

  /**
   * Normalize the small blocking request used during live NPC dialogue.
   */
  protected function normalizeBootstrapRequest(array $request): array {
    $normalized = $this->normalizeRequest($request + ['status' => 'bootstrapping']);
    $normalized['speaker_npc_id'] = trim((string) ($request['speaker_npc_id'] ?? $request['quest_giver_id'] ?? ''));
    $normalized['speaker_name'] = trim((string) ($request['speaker_name'] ?? $request['quest_giver_name'] ?? ''));
    $normalized['lead_location_id'] = trim((string) ($request['lead_location_id'] ?? $request['location_id'] ?? ''));
    return $normalized;
  }

  /**
   * Validate the normalized bootstrap handoff payload when schema validation exists.
   */
  protected function assertValidBootstrapRequest(array $request): void {
    if ($this->stateValidationService === NULL) {
      return;
    }

    $validation = $this->stateValidationService->validateStorylineBootstrapRequest($request);
    if (!($validation['valid'] ?? FALSE)) {
      throw new \InvalidArgumentException('Storyline bootstrap request failed validation: ' . implode('; ', $validation['errors'] ?? []), 400);
    }
  }

  /**
   * Build the queue payload used to hand off deferred expansion safely.
   */
  protected function buildExpansionJobPayload(int $campaign_id, string $storyline_id, array $request): array {
    $payload = [
      'schema_version' => self::STORYLINE_EXPANSION_JOB_SCHEMA_VERSION,
      'campaign_id' => $campaign_id,
      'storyline_id' => $storyline_id,
      'request' => $request,
    ];

    if ($this->stateValidationService !== NULL) {
      $validation = $this->stateValidationService->validateStorylineExpansionJob($payload);
      if (!($validation['valid'] ?? FALSE)) {
        throw new \InvalidArgumentException('Storyline expansion job failed validation: ' . implode('; ', $validation['errors'] ?? []), 400);
      }
    }

    return $payload;
  }

  /**
   * Normalize and validate a queued expansion payload loaded from storage.
   */
  protected function normalizeExpansionJobPayload(array $payload): array {
    $payload['schema_version'] = (string) ($payload['schema_version'] ?? '');
    $payload['campaign_id'] = (int) ($payload['campaign_id'] ?? 0);
    $payload['storyline_id'] = trim((string) ($payload['storyline_id'] ?? ''));
    $payload['request'] = $this->normalizeRequest(is_array($payload['request'] ?? NULL) ? $payload['request'] : []);

    if ($this->stateValidationService !== NULL) {
      $validation = $this->stateValidationService->validateStorylineExpansionJob($payload);
      if (!($validation['valid'] ?? FALSE)) {
        throw new \InvalidArgumentException('Stored storyline expansion job failed validation: ' . implode('; ', $validation['errors'] ?? []), 400);
      }
    }

    return $payload;
  }

  /**
   * Build compact campaign context for storyline generation.
   */
  protected function buildGenerationContext(int $campaign_id, array $request): array {
    $state = $this->campaignStateService->getState($campaign_id);
    $existing_storylines = array_slice($this->storylineManager->listCampaignStorylines($campaign_id), 0, 5);
    $party_level = $this->guessPartyLevelFromState($state);
    $party_size = $this->guessPartySizeFromState($state);
    $budget = $this->treasureByLevelService->getLevelBudget($party_level, $party_size);

    return [
      'campaign_id' => $campaign_id,
      'location_id' => $this->guessLocationIdFromState($state),
      'party_level' => $party_level,
      'party_size' => $party_size,
      'treasure_budget' => $budget,
      'existing_storylines' => array_map(static function (array $storyline): array {
        return [
          'storyline_id' => (string) ($storyline['storyline_id'] ?? ''),
          'name' => (string) ($storyline['name'] ?? ''),
          'status' => (string) ($storyline['status'] ?? ''),
        ];
      }, $existing_storylines),
      'request' => $request,
    ];
  }

  /**
   * Ask the AI provider for a structured storyline package.
   */
  protected function generatePackageWithAi(int $campaign_id, array $request, array $context): array {
    $prompt = "Generate a campaign storyline package as strict JSON.\n";
    $prompt .= "Prompt: {$request['prompt']}\n";
    $prompt .= "Requested tone: {$request['tone']}\n";
    $prompt .= "Requested level range: {$request['level_range']}\n";
    $prompt .= "Campaign context:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    $prompt .= "Return ONLY JSON with this shape:\n";
    $prompt .= "{\n";
    $prompt .= '  "storyline": {' . "\n";
    $prompt .= '    "name": string,' . "\n";
    $prompt .= '    "template_id": string,' . "\n";
    $prompt .= '    "synopsis": string,' . "\n";
    $prompt .= '    "level_range": string,' . "\n";
    $prompt .= '    "source": string,' . "\n";
    $prompt .= '    "tags": [string, ...],' . "\n";
    $prompt .= '    "metadata": object,' . "\n";
    $prompt .= '    "asset_references": [object, ...],' . "\n";
    $prompt .= '    "contacts": [object, ...],' . "\n";
    $prompt .= '    "chapters": [object, ...]' . "\n";
    $prompt .= '  },' . "\n";
    $prompt .= '  "quest_templates": [object, ...]' . "\n";
    $prompt .= "}\n\n";
    $prompt .= "Hard requirements:\n";
    $prompt .= "- Exactly one campaign goal.\n";
    $prompt .= "- Exactly one big boss.\n";
    $prompt .= "- Exactly two sub-bosses aligned to the big boss.\n";
    $prompt .= "- Exactly three dungeons total, one for each boss.\n";
    $prompt .= "- Exactly five rooms per dungeon.\n";
    $prompt .= "- One quest template per room, so fifteen quest templates total.\n";
    $prompt .= "- Every quest id referenced by storyline chapters/scenes must exist in quest_templates[].template_id.\n";
    $prompt .= "- Storyline metadata must include a generated campaign outline with generation_phase = expanded covering the goal, boss hierarchy, dungeon styles, room contents, encounter plans, treasure plans, and progression connectors.\n";
    $prompt .= "- Keep all styles aligned: goal -> big boss -> sub-bosses -> dungeons -> rooms -> NPCs/items/encounters/treasure.\n";
    $prompt .= "- The progression chain must be explicit: quest giver points to dungeon entrance 1, sub-boss 1 points to dungeon entrance 2, sub-boss 2 points to dungeon entrance 3, and the final boss anchors the goal.\n";
    $prompt .= "- If template_id, entry_dungeon_id, entry_room_id, first_quest_id, or questgiver speaker fields are provided, reuse them exactly for the first handoff.\n";

    $result = $this->aiApiService->invokeModelDirect(
      $prompt,
      'dungeoncrawler_content',
      'storyline_generation',
      ['campaign_id' => $campaign_id],
      [
        'system_prompt' => 'You generate strict JSON storyline packages for a PF2e dungeon crawler. Never wrap JSON in markdown fences. Prefer concrete, gameable room and quest details over vague prose.',
        'max_tokens' => 2200,
        'skip_cache' => TRUE,
      ]
    );

    $response = trim((string) ($result['response'] ?? ''));
    $response = preg_replace('/^```json\s*|\s*```$/', '', $response) ?? $response;
    $parsed = json_decode($response, TRUE);
    if (!is_array($parsed)) {
      throw new \RuntimeException('AI did not return valid JSON for storyline generation.');
    }

    return $parsed;
  }

  /**
   * Ask the AI provider for the minimal storyline bootstrap package.
   */
  protected function generateBootstrapPackageWithAi(int $campaign_id, array $request, array $context): array {
    $prompt = "Generate a minimal campaign storyline bootstrap package as strict JSON.\n";
    $prompt .= "Prompt: {$request['prompt']}\n";
    $prompt .= "Requested tone: {$request['tone']}\n";
    $prompt .= "Requested level range: {$request['level_range']}\n";
    $prompt .= "Campaign context:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    $prompt .= "Return ONLY JSON with this shape:\n";
    $prompt .= "{\n";
    $prompt .= '  "storyline": {' . "\n";
    $prompt .= '    "name": string,' . "\n";
    $prompt .= '    "template_id": string,' . "\n";
    $prompt .= '    "synopsis": string,' . "\n";
    $prompt .= '    "level_range": string,' . "\n";
    $prompt .= '    "source": string,' . "\n";
    $prompt .= '    "tags": [string, ...],' . "\n";
    $prompt .= '    "metadata": {' . "\n";
    $prompt .= '      "goal": string,' . "\n";
    $prompt .= '      "generated_outline": {' . "\n";
    $prompt .= '        "generation_phase": "bootstrap",' . "\n";
    $prompt .= '        "goal": string,' . "\n";
    $prompt .= '        "entry_dungeon": {' . "\n";
    $prompt .= '          "dungeon_id": string,' . "\n";
    $prompt .= '          "name": string,' . "\n";
    $prompt .= '          "style": string,' . "\n";
    $prompt .= '          "entrance_room_id": string,' . "\n";
    $prompt .= '          "lead_location_id": string,' . "\n";
    $prompt .= '          "lead_location_hint": string' . "\n";
    $prompt .= '        },' . "\n";
    $prompt .= '        "progression_connectors": [object, ...],' . "\n";
    $prompt .= '        "bootstrap_handoff": {' . "\n";
    $prompt .= '          "speaker_npc_id": string,' . "\n";
    $prompt .= '          "speaker_name": string,' . "\n";
    $prompt .= '          "lead_text": string' . "\n";
    $prompt .= '        }' . "\n";
    $prompt .= '      }' . "\n";
    $prompt .= '    },' . "\n";
    $prompt .= '    "asset_references": [object, ...],' . "\n";
    $prompt .= '    "contacts": [object, ...],' . "\n";
    $prompt .= '    "chapters": [object, ...]' . "\n";
    $prompt .= '  },' . "\n";
    $prompt .= '  "quest_templates": [object]' . "\n";
    $prompt .= "}\n\n";
    $prompt .= "Hard requirements:\n";
    $prompt .= "- This is the synchronous bootstrap phase only.\n";
    $prompt .= "- Generate exactly one goal, one entry dungeon stub, one entrance room, one first quest template, and one immediate questgiver handoff.\n";
    $prompt .= "- Do not generate bosses, downstream dungeons, or a full room graph yet.\n";

    $result = $this->aiApiService->invokeModelDirect(
      $prompt,
      'dungeoncrawler_content',
      'storyline_bootstrap_generation',
      ['campaign_id' => $campaign_id],
      [
        'system_prompt' => 'You generate strict JSON storyline bootstrap payloads for a PF2e dungeon crawler. Keep the output minimal, immediately playable, and never wrap JSON in markdown fences.',
        'max_tokens' => 1200,
        'skip_cache' => TRUE,
      ]
    );

    $response = trim((string) ($result['response'] ?? ''));
    $response = preg_replace('/^```json\s*|\s*```$/', '', $response) ?? $response;
    $parsed = json_decode($response, TRUE);
    if (!is_array($parsed)) {
      throw new \RuntimeException('AI did not return valid JSON for storyline bootstrap generation.');
    }

    return $parsed;
  }

  /**
   * Deterministic fallback package when AI is unavailable or invalid.
   */
  protected function generateFallbackPackage(int $campaign_id, array $request, array $context): array {
    $goal = $this->normalizeSentence($request['prompt']);
    $base_name = $request['name'] !== '' ? $request['name'] : $this->deriveStorylineName($request['prompt']);
    $base_slug = $this->sanitizeIdentifier(
      $request['template_id'] !== '' ? $request['template_id'] : $base_name,
      self::GENERATED_STORYLINE_SLUG_MAX_LENGTH
    );
    if ($base_slug === '') {
      $base_slug = 'generated-storyline-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
    }

    $style_seed = $request['theme'] !== '' ? $request['theme'] : $this->deriveStyleSeed($request['prompt'], $request['tone']);
    $location_id = $request['lead_location_id'] !== '' ? (string) $request['lead_location_id'] : (string) ($context['location_id'] ?? 'tavern_entrance');
    $level_bounds = $this->parseLevelRange((string) $request['level_range']);
    $dungeon_levels = [
      $level_bounds['min'],
      min(20, max($level_bounds['min'], (int) floor(($level_bounds['min'] + $level_bounds['max']) / 2))),
      $level_bounds['max'],
    ];

    $boss_specs = [
      [
        'boss_id' => $base_slug . '-sub-boss-1',
        'name' => 'Warden of the First Seal',
        'style' => 'disciplined ' . $style_seed,
        'role' => 'sub_boss',
        'dungeon_id' => $request['entry_dungeon_id'] !== '' ? $request['entry_dungeon_id'] : $base_slug . '-vault-of-ashes',
        'dungeon_name' => $request['entry_dungeon_id'] !== '' ? $base_name : 'Vault of Ashes',
        'dungeon_style' => 'fortified ' . $style_seed,
      ],
      [
        'boss_id' => $base_slug . '-sub-boss-2',
        'name' => 'Whispering Archivist',
        'style' => 'occult ' . $style_seed,
        'role' => 'sub_boss',
        'dungeon_id' => $base_slug . '-catacomb-of-echoes',
        'dungeon_name' => 'Catacomb of Echoes',
        'dungeon_style' => 'haunted ' . $style_seed,
      ],
      [
        'boss_id' => $base_slug . '-big-boss',
        'name' => 'The Crown of Ruin',
        'style' => 'apex ' . $style_seed,
        'role' => 'big_boss',
        'dungeon_id' => $base_slug . '-throne-of-ruin',
        'dungeon_name' => 'Throne of Ruin',
        'dungeon_style' => 'cataclysmic ' . $style_seed,
      ],
    ];

    $dungeons = [];
    $chapters = [];
    $quest_templates = [];
    $asset_references = [
      [
        'asset_type' => 'room',
        'asset_id' => $location_id,
        'asset_role' => 'lead-location',
        'notes' => 'Primary lead-in location for the generated storyline.',
      ],
    ];

    foreach ($boss_specs as $index => $boss) {
      $dungeon_level = $dungeon_levels[$index] ?? $level_bounds['max'];
      $room_bundle = $this->buildDungeonRoomBundle(
        $base_slug,
        $boss,
        $dungeon_level,
        $style_seed,
        $context,
        $index === 0 ? [
          'entry_room_id' => (string) ($request['entry_room_id'] ?? ''),
          'first_quest_id' => (string) ($request['first_quest_id'] ?? ''),
        ] : []
      );
      $dungeons[] = $room_bundle['dungeon_outline'];
      $chapters[] = $room_bundle['chapter'];
      $quest_templates = array_merge($quest_templates, $room_bundle['quest_templates']);
      $asset_references = array_merge($asset_references, $room_bundle['asset_references']);
    }

    $big_boss = $boss_specs[2];
    $sub_bosses = [$boss_specs[0], $boss_specs[1]];
    $progression_connectors = $this->buildProgressionConnectors($base_slug, $location_id, $dungeons, $boss_specs, $goal);
    $asset_references = array_merge($asset_references, $this->buildProgressionAssetReferences($progression_connectors));

    $outline = [
      'generation_phase' => 'expanded',
      'goal' => $goal,
      'big_boss' => [
        'boss_id' => $big_boss['boss_id'],
        'name' => $big_boss['name'],
        'style' => $big_boss['style'],
        'alignment_to_goal' => 'Embodies the core threat behind the campaign goal.',
        'dungeon_id' => $big_boss['dungeon_id'],
      ],
      'sub_bosses' => array_map(static function (array $boss): array {
        return [
          'boss_id' => $boss['boss_id'],
          'name' => $boss['name'],
          'style' => $boss['style'],
          'alignment_to_big_boss' => 'Acts as a lieutenant advancing the big boss plan.',
          'dungeon_id' => $boss['dungeon_id'],
        ];
      }, $sub_bosses),
      'dungeons' => $dungeons,
      'progression_connectors' => $progression_connectors,
      'treasure_strategy' => [
        'budget_basis' => $context['treasure_budget'] ?? [],
        'style_alignment' => 'Treasure and consumables escalate with the boss hierarchy and room pressure.',
      ],
    ];

    return [
      'storyline' => [
        'name' => $base_name,
        'template_id' => $base_slug,
        'synopsis' => 'Pursue the goal "' . $goal . '" across three boss-linked dungeons, culminating in a final confrontation with ' . $big_boss['name'] . '.',
        'level_range' => (string) $request['level_range'],
        'source' => (string) $request['source'],
        'tags' => array_values(array_unique(array_merge($request['tags'], [$style_seed, 'generated', 'boss-arc']))),
        'metadata' => [
          'campaign_role' => 'generated_arc',
          'generation_source' => 'fallback',
          'tone' => $request['tone'],
          'goal' => $goal,
          'generated_outline' => $outline,
        ],
        'asset_references' => $asset_references,
        'contacts' => [
          [
            'contact_id' => $base_slug . '-patron',
            'entity_type' => $request['speaker_npc_id'] !== '' ? 'campaign_npc' : 'npc_template',
            'entity_id' => $request['speaker_npc_id'] !== '' ? $request['speaker_npc_id'] : ($base_slug . '-patron'),
            'role' => 'quest_giver',
            'display_name' => $request['speaker_name'] !== '' ? $request['speaker_name'] : 'Keeper Althea',
            'attitude' => 'friendly',
            'notes' => 'Patron who briefs the party on the goal and the three boss dungeons.',
            'relationship_state' => [
              'points_to_dungeon_id' => (string) ($dungeons[0]['dungeon_id'] ?? ''),
              'points_to_room_id' => (string) ($dungeons[0]['entrance_room_id'] ?? ''),
              'mechanism' => 'npc_direction',
            ],
          ],
        ],
        'chapters' => $chapters,
      ],
      'quest_templates' => $quest_templates,
    ];
  }

  /**
   * Deterministic fallback for synchronous bootstrap generation.
   */
  protected function generateFallbackBootstrapPackage(int $campaign_id, array $request, array $context): array {
    $goal = $this->normalizeSentence($request['prompt']);
    $base_name = $request['name'] !== '' ? $request['name'] : $this->deriveStorylineName($request['prompt']);
    $base_slug = $this->sanitizeIdentifier($base_name, self::GENERATED_STORYLINE_SLUG_MAX_LENGTH);
    if ($base_slug === '') {
      $base_slug = 'storyline-bootstrap-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
    }

    $style_seed = $request['theme'] !== '' ? $request['theme'] : $this->deriveStyleSeed($request['prompt'], $request['tone']);
    $lead_location_id = $request['lead_location_id'] !== '' ? $request['lead_location_id'] : (string) ($context['location_id'] ?? 'tavern_entrance');
    $entry_dungeon_id = $base_slug . '-entry-dungeon';
    $entrance_room_id = $entry_dungeon_id . '-entrance';
    $entry_dungeon_name = 'Threshold of ' . $base_name;
    $speaker_id = $request['speaker_npc_id'] !== '' ? $request['speaker_npc_id'] : $base_slug . '-questgiver';
    $speaker_name = $request['speaker_name'] !== '' ? $request['speaker_name'] : 'Keeper Althea';
    $boss = [
      'boss_id' => $base_slug . '-future-boss',
      'name' => 'Unseen Adversary',
      'dungeon_name' => $entry_dungeon_name,
      'dungeon_style' => 'threshold ' . $style_seed,
      'style' => 'threshold ' . $style_seed,
      'role' => 'sub_boss',
    ];
    $quest_template_id = $entrance_room_id . '-quest';
    $quest_template = $this->buildQuestTemplate(
      $quest_template_id,
      $boss,
      'entrance',
      'threshold ' . $style_seed,
      max(1, (int) ($context['party_level'] ?? 1)),
      $entrance_room_id,
      $this->chooseLootTableId($style_seed, max(1, (int) ($context['party_level'] ?? 1)), 'entrance'),
      [
        'encounter_type' => 'exploration',
        'threat_level' => 'low',
        'theme' => 'threshold ' . $style_seed,
        'objective' => 'Reach the first dungeon entrance tied to the storyline goal.',
      ],
      $this->buildTreasurePlan(max(1, (int) ($context['party_level'] ?? 1)), 'low', 'core_starter_adventure', 'entrance', $context)
    );

    $outline = [
      'generation_phase' => 'bootstrap',
      'goal' => $goal,
      'entry_dungeon' => [
        'dungeon_id' => $entry_dungeon_id,
        'name' => $entry_dungeon_name,
        'style' => 'threshold ' . $style_seed,
        'entrance_room_id' => $entrance_room_id,
        'lead_location_id' => $lead_location_id,
        'lead_location_hint' => 'Start at ' . str_replace('_', ' ', $lead_location_id) . ' and follow the first lead toward ' . $entry_dungeon_name . '.',
      ],
      'progression_connectors' => [
        [
          'connector_id' => $base_slug . '-bootstrap-handoff',
          'source_type' => 'npc',
          'source_id' => $speaker_id,
          'mechanism' => 'npc_direction',
          'from_location_id' => $lead_location_id,
          'target_dungeon_id' => $entry_dungeon_id,
          'target_room_id' => $entrance_room_id,
          'narrative' => $speaker_name . ' points the party toward the first dungeon entrance.',
        ],
      ],
      'bootstrap_handoff' => [
        'speaker_npc_id' => $speaker_id,
        'speaker_name' => $speaker_name,
        'lead_text' => $speaker_name . ' points the party toward ' . $entry_dungeon_name . ' to pursue the goal "' . $goal . '".',
      ],
      'expansion_status' => 'pending',
    ];

    return [
      'storyline' => [
        'name' => $base_name,
        'template_id' => $base_slug,
        'synopsis' => 'Follow the first lead toward ' . $entry_dungeon_name . ' and begin pursuing "' . $goal . '".',
        'level_range' => (string) $request['level_range'],
        'source' => (string) $request['source'],
        'tags' => array_values(array_unique(array_merge($request['tags'], [$style_seed, 'generated', 'bootstrap']))),
        'metadata' => [
          'campaign_role' => 'generated_bootstrap',
          'generation_source' => 'fallback',
          'tone' => $request['tone'],
          'goal' => $goal,
          'generated_outline' => $outline,
        ],
        'asset_references' => [
          [
            'asset_type' => 'location',
            'asset_id' => $lead_location_id,
            'asset_role' => 'lead-location',
            'notes' => 'The current location where the questgiver gives the first lead.',
          ],
          [
            'asset_type' => 'dungeon',
            'asset_id' => $entry_dungeon_id,
            'asset_role' => 'entry-dungeon',
            'chapter_id' => $entry_dungeon_id,
            'notes' => 'First storyline dungeon stub generated during bootstrap.',
          ],
          [
            'asset_type' => 'room',
            'asset_id' => $entrance_room_id,
            'asset_role' => 'entrance-room',
            'chapter_id' => $entry_dungeon_id,
            'scene_id' => $entrance_room_id,
            'notes' => 'Initial storyline entrance room.',
          ],
        ],
        'contacts' => [
          [
            'contact_id' => $base_slug . '-questgiver',
            'entity_type' => 'campaign_npc',
            'entity_id' => $speaker_id,
            'role' => 'quest_giver',
            'display_name' => $speaker_name,
            'attitude' => 'friendly',
            'availability' => 'available',
            'notes' => 'Questgiver who bootstrapped this storyline.',
            'relationship_state' => [
              'points_to_dungeon_id' => $entry_dungeon_id,
              'points_to_room_id' => $entrance_room_id,
              'mechanism' => 'npc_direction',
            ],
            'introduces_to' => [],
          ],
        ],
        'chapters' => [
          [
            'chapter_id' => $entry_dungeon_id,
            'name' => $entry_dungeon_name,
            'summary' => 'Reach the first dungeon entrance and commit to the storyline goal.',
            'asset_references' => [
              [
                'asset_type' => 'dungeon',
                'asset_id' => $entry_dungeon_id,
                'asset_role' => 'chapter-space',
              ],
            ],
            'scenes' => [
              [
                'scene_id' => $entrance_room_id,
                'name' => 'Dungeon Entrance',
                'summary' => 'The first threshold tied to "' . $goal . '".',
                'quest_ids' => [$quest_template_id],
                'asset_references' => [
                  [
                    'asset_type' => 'room',
                    'asset_id' => $entrance_room_id,
                    'asset_role' => 'entrance',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'quest_templates' => [$quest_template],
    ];
  }

  /**
   * Normalize generated package into a runtime-safe form.
   */
  protected function normalizeGeneratedPackage(int $campaign_id, array $request, array $context, array $package, string $generation_source): array {
    $storyline = is_array($package['storyline'] ?? NULL) ? $package['storyline'] : $package;
    if ($storyline === []) {
      throw new \InvalidArgumentException('Generated storyline package is empty.', 400);
    }

    $storyline['name'] = trim((string) ($storyline['name'] ?? $request['name'] ?? $this->deriveStorylineName($request['prompt'])));
    $storyline['template_id'] = $this->sanitizeIdentifier(
      (string) ($storyline['template_id'] ?? $storyline['name'] ?? 'generated-storyline'),
      self::GENERATED_TEMPLATE_ID_MAX_LENGTH
    );
    $storyline['synopsis'] = trim((string) ($storyline['synopsis'] ?? 'Generated storyline based on the supplied campaign goal.'));
    $storyline['level_range'] = trim((string) ($storyline['level_range'] ?? $request['level_range']));
    $storyline['source'] = trim((string) ($storyline['source'] ?? $request['source'])) ?: 'storyline-generator';
    $storyline['tags'] = array_values(array_unique(array_merge(
      array_map('strval', is_array($storyline['tags'] ?? NULL) ? $storyline['tags'] : []),
      $request['tags'],
      ['generated']
    )));
    $storyline['metadata'] = is_array($storyline['metadata'] ?? NULL) ? $storyline['metadata'] : [];
    $storyline['metadata']['generation_source'] = $generation_source;
    $storyline['metadata']['goal'] = (string) ($storyline['metadata']['goal'] ?? $this->normalizeSentence($request['prompt']));
    [$storyline, $raw_quest_templates] = $this->preserveBootstrapAnchors(
      $storyline,
      is_array($package['quest_templates'] ?? NULL) ? $package['quest_templates'] : [],
      $request
    );

    $quest_templates = $this->normalizeQuestTemplates(
      $raw_quest_templates,
      $storyline,
      $context
    );
    $storyline = $this->synchronizeStorylineToQuestTemplates($storyline, $quest_templates, $context);

    $normalized_storyline = $this->storylineManager->normalizeStorylineDefinition($storyline);

    return [
      'storyline_definition' => $normalized_storyline,
      'quest_templates' => $quest_templates,
      'generation_source' => $generation_source,
      'campaign_outline' => $normalized_storyline['metadata']['generated_outline'] ?? [],
    ];
  }

  /**
   * Reuse bootstrap identifiers in the first expansion handoff when provided.
   */
  protected function preserveBootstrapAnchors(array $storyline, array $quest_templates, array $request): array {
    $entry_dungeon_id = trim((string) ($request['entry_dungeon_id'] ?? ''));
    $entry_room_id = trim((string) ($request['entry_room_id'] ?? ''));
    $first_quest_id = trim((string) ($request['first_quest_id'] ?? ''));
    $speaker_npc_id = trim((string) ($request['speaker_npc_id'] ?? ''));
    $speaker_name = trim((string) ($request['speaker_name'] ?? ''));
    $lead_location_id = trim((string) ($request['lead_location_id'] ?? ''));

    if ($entry_dungeon_id !== '' && !empty($storyline['chapters'][0])) {
      $storyline['chapters'][0]['chapter_id'] = $entry_dungeon_id;
    }
    if ($entry_room_id !== '' && !empty($storyline['chapters'][0]['scenes'][0])) {
      $storyline['chapters'][0]['scenes'][0]['scene_id'] = $entry_room_id;
    }
    if ($first_quest_id !== '' && !empty($storyline['chapters'][0]['scenes'][0])) {
      $storyline['chapters'][0]['scenes'][0]['quest_ids'] = [$first_quest_id];
    }

    if ($speaker_npc_id !== '' && !empty($storyline['contacts'][0])) {
      $storyline['contacts'][0]['entity_type'] = 'campaign_npc';
      $storyline['contacts'][0]['entity_id'] = $speaker_npc_id;
    }
    if ($speaker_name !== '' && !empty($storyline['contacts'][0])) {
      $storyline['contacts'][0]['display_name'] = $speaker_name;
    }
    if ($entry_dungeon_id !== '' && !empty($storyline['contacts'][0]['relationship_state']) && is_array($storyline['contacts'][0]['relationship_state'])) {
      $storyline['contacts'][0]['relationship_state']['points_to_dungeon_id'] = $entry_dungeon_id;
    }
    if ($entry_room_id !== '' && !empty($storyline['contacts'][0]['relationship_state']) && is_array($storyline['contacts'][0]['relationship_state'])) {
      $storyline['contacts'][0]['relationship_state']['points_to_room_id'] = $entry_room_id;
    }

    foreach ((array) ($storyline['asset_references'] ?? []) as $index => $reference) {
      if (!is_array($reference)) {
        continue;
      }
      if ($entry_dungeon_id !== '' && (string) ($reference['asset_type'] ?? '') === 'dungeon' && $index === 0) {
        $storyline['asset_references'][$index]['asset_id'] = $entry_dungeon_id;
        $storyline['asset_references'][$index]['chapter_id'] = $entry_dungeon_id;
      }
      if ($entry_room_id !== '' && (string) ($reference['asset_type'] ?? '') === 'room' && (($reference['scene_id'] ?? '') !== '' || ($reference['asset_role'] ?? '') === 'entrance-room')) {
        $storyline['asset_references'][$index]['asset_id'] = $entry_room_id;
        $storyline['asset_references'][$index]['scene_id'] = $entry_room_id;
        if ($entry_dungeon_id !== '') {
          $storyline['asset_references'][$index]['chapter_id'] = $entry_dungeon_id;
        }
        break;
      }
    }

    $outline = is_array($storyline['metadata']['generated_outline'] ?? NULL) ? $storyline['metadata']['generated_outline'] : [];
    if ($entry_dungeon_id !== '' && !empty($outline['dungeons'][0])) {
      $outline['dungeons'][0]['dungeon_id'] = $entry_dungeon_id;
    }
    if ($entry_room_id !== '' && !empty($outline['dungeons'][0])) {
      $outline['dungeons'][0]['entrance_room_id'] = $entry_room_id;
      if (!empty($outline['dungeons'][0]['rooms'][0]) && is_array($outline['dungeons'][0]['rooms'][0])) {
        $outline['dungeons'][0]['rooms'][0]['room_id'] = $entry_room_id;
      }
    }
    if ($lead_location_id !== '' && !empty($outline['progression_connectors'][0])) {
      $outline['progression_connectors'][0]['from_location_id'] = $lead_location_id;
    }
    if ($entry_dungeon_id !== '' && !empty($outline['progression_connectors'][0])) {
      $outline['progression_connectors'][0]['target_dungeon_id'] = $entry_dungeon_id;
    }
    if ($entry_room_id !== '' && !empty($outline['progression_connectors'][0])) {
      $outline['progression_connectors'][0]['target_room_id'] = $entry_room_id;
    }
    if ($speaker_npc_id !== '' && !empty($outline['progression_connectors'][0])) {
      $outline['progression_connectors'][0]['source_id'] = $speaker_npc_id;
    }
    $storyline['metadata']['generated_outline'] = $outline;

    if ($first_quest_id !== '' && $quest_templates !== []) {
      $quest_templates[0]['template_id'] = $first_quest_id;
    }

    return [$storyline, $quest_templates];
  }

  /**
   * Normalize a bootstrap package into a runtime-safe storyline definition.
   */
  protected function normalizeGeneratedBootstrapPackage(int $campaign_id, array $request, array $context, array $package, string $generation_source): array {
    $storyline = is_array($package['storyline'] ?? NULL) ? $package['storyline'] : $package;
    if ($storyline === []) {
      throw new \InvalidArgumentException('Generated storyline bootstrap package is empty.', 400);
    }

    $storyline['name'] = trim((string) ($storyline['name'] ?? $request['name'] ?? $this->deriveStorylineName($request['prompt'])));
    $storyline['template_id'] = $this->sanitizeIdentifier(
      (string) ($storyline['template_id'] ?? $storyline['name'] ?? 'generated-storyline-bootstrap'),
      self::GENERATED_TEMPLATE_ID_MAX_LENGTH
    );
    $storyline['synopsis'] = trim((string) ($storyline['synopsis'] ?? 'Generated storyline bootstrap based on the supplied campaign goal.'));
    $storyline['level_range'] = trim((string) ($storyline['level_range'] ?? $request['level_range']));
    $storyline['source'] = trim((string) ($storyline['source'] ?? $request['source'])) ?: 'storyline-bootstrap';
    $storyline['tags'] = array_values(array_unique(array_merge(
      array_map('strval', is_array($storyline['tags'] ?? NULL) ? $storyline['tags'] : []),
      $request['tags'],
      ['generated', 'bootstrap']
    )));
    $storyline['metadata'] = is_array($storyline['metadata'] ?? NULL) ? $storyline['metadata'] : [];
    $storyline['metadata']['generation_source'] = $generation_source;
    $storyline['metadata']['goal'] = (string) ($storyline['metadata']['goal'] ?? $this->normalizeSentence($request['prompt']));
    $storyline['metadata']['generated_outline'] = is_array($storyline['metadata']['generated_outline'] ?? NULL)
      ? $storyline['metadata']['generated_outline']
      : [];
    $storyline['metadata']['generated_outline']['generation_phase'] = 'bootstrap';

    $quest_templates = $this->normalizeQuestTemplates(
      is_array($package['quest_templates'] ?? NULL) ? $package['quest_templates'] : [],
      $storyline,
      $context
    );
    $storyline = $this->synchronizeStorylineToQuestTemplates($storyline, $quest_templates, $context);

    $normalized_storyline = $this->storylineManager->normalizeStorylineDefinition($storyline);

    return [
      'storyline_definition' => $normalized_storyline,
      'quest_templates' => $quest_templates,
      'generation_source' => $generation_source,
      'campaign_outline' => $normalized_storyline['metadata']['generated_outline'] ?? [],
    ];
  }

  /**
   * Build one dungeon, five rooms, and one quest template per room.
   */
  protected function buildDungeonRoomBundle(string $base_slug, array $boss, int $dungeon_level, string $style_seed, array $context, array $bootstrap_overrides = []): array {
    $room_roles = [
      ['role' => 'entrance', 'difficulty' => 'low', 'encounter_type' => 'exploration'],
      ['role' => 'gauntlet', 'difficulty' => 'moderate', 'encounter_type' => 'combat'],
      ['role' => 'sanctum', 'difficulty' => 'moderate', 'encounter_type' => 'investigation'],
      ['role' => 'lieutenant', 'difficulty' => 'severe', 'encounter_type' => 'combat'],
      ['role' => 'boss', 'difficulty' => $boss['role'] === 'big_boss' ? 'extreme' : 'severe', 'encounter_type' => 'combat'],
    ];

    $rooms = [];
    $quest_templates = [];
    $asset_references = [
      [
        'asset_type' => 'dungeon',
        'asset_id' => $boss['dungeon_id'],
        'asset_role' => $boss['role'] === 'big_boss' ? 'final-dungeon' : 'boss-dungeon',
        'notes' => $boss['dungeon_style'],
      ],
      [
        'asset_type' => 'npc',
        'asset_id' => $boss['boss_id'],
        'asset_role' => $boss['role'] === 'big_boss' ? 'big-boss' : 'sub-boss',
        'notes' => $boss['style'],
      ],
    ];

    foreach ($room_roles as $index => $room_role) {
      $room_number = $index + 1;
      $room_id = ($room_number === 1 && !empty($bootstrap_overrides['entry_room_id']))
        ? (string) $bootstrap_overrides['entry_room_id']
        : ($boss['dungeon_id'] . '-room-' . $room_number);
      $room_style = $this->buildRoomStyle($boss['dungeon_style'], $room_role['role'], $boss['style']);
      $quest_template_id = ($room_number === 1 && !empty($bootstrap_overrides['first_quest_id']))
        ? (string) $bootstrap_overrides['first_quest_id']
        : ($room_id . '-quest');
      $loot_table_id = $this->chooseLootTableId($room_style, $dungeon_level, $room_role['role']);
      $treasure_plan = $this->buildTreasurePlan($dungeon_level, $room_role['difficulty'], $loot_table_id, $room_role['role'], $context);
      $encounter_plan = [
        'encounter_type' => $room_role['encounter_type'] === 'investigation' ? 'exploration' : $room_role['encounter_type'],
        'threat_level' => $room_role['difficulty'],
        'theme' => $boss['dungeon_style'],
        'style_alignment' => $room_style,
        'objective' => $this->buildRoomObjective($room_role['role'], $boss['name'], $room_style),
      ];

      $rooms[] = [
        'room_id' => $room_id,
        'quest_template_id' => $quest_template_id,
        'name' => ucwords(str_replace('-', ' ', $room_role['role'])) . ' of ' . $boss['dungeon_name'],
        'room_role' => $room_role['role'],
        'style' => $room_style,
        'summary' => $this->buildRoomSummary($room_role['role'], $boss['name'], $boss['dungeon_style']),
        'npc_ids' => $this->buildRoomNpcIds($room_role['role'], $boss),
        'item_ids' => $this->buildRoomItemIds($room_role['role'], $boss, $loot_table_id),
        'encounter_connector' => [
          'room_id' => $room_id,
          'boss_id' => (string) ($boss['boss_id'] ?? ''),
          'threat_level' => (string) ($encounter_plan['threat_level'] ?? 'moderate'),
          'theme' => (string) ($encounter_plan['theme'] ?? ''),
          'encounter_type' => (string) ($encounter_plan['encounter_type'] ?? 'combat'),
        ],
        'treasure_connector' => [
          'room_id' => $room_id,
          'loot_table_id' => $loot_table_id,
          'currency_gp' => (float) ($treasure_plan['currency_gp'] ?? 0),
          'permanent_item_level' => (int) ($treasure_plan['permanent_item_level'] ?? 1),
        ],
      ];

      foreach ($rooms[$index]['npc_ids'] as $npc_id) {
        $asset_references[] = [
          'asset_type' => 'npc',
          'asset_id' => $npc_id,
          'asset_role' => $room_role['role'] . '-npc',
          'chapter_id' => $boss['dungeon_id'],
          'scene_id' => $room_id,
          'notes' => $room_style,
        ];
      }
      foreach ($rooms[$index]['item_ids'] as $item_id) {
        $asset_references[] = [
          'asset_type' => 'item',
          'asset_id' => $item_id,
          'asset_role' => $room_role['role'] . '-item',
          'chapter_id' => $boss['dungeon_id'],
          'scene_id' => $room_id,
          'notes' => $loot_table_id,
        ];
      }

      $asset_references[] = [
        'asset_type' => 'room',
        'asset_id' => $room_id,
        'asset_role' => $room_role['role'] . '-room',
        'chapter_id' => $boss['dungeon_id'],
        'scene_id' => $room_id,
        'notes' => $room_style,
      ];

      $quest_templates[] = $this->buildQuestTemplate(
        $quest_template_id,
        $boss,
        $room_role['role'],
        $room_style,
        $dungeon_level,
        $room_id,
        $loot_table_id,
        $encounter_plan,
        $treasure_plan
      );
    }

    $chapter = [
      'chapter_id' => $boss['dungeon_id'],
      'name' => $boss['dungeon_name'],
      'summary' => 'Delve through ' . $boss['dungeon_name'] . ' and break the hold of ' . $boss['name'] . '.',
      'asset_references' => [
        [
          'asset_type' => 'dungeon',
          'asset_id' => $boss['dungeon_id'],
          'asset_role' => 'chapter-space',
        ],
      ],
      'scenes' => array_map(static function (array $room): array {
        return [
          'scene_id' => (string) $room['room_id'],
          'name' => (string) $room['name'],
          'summary' => (string) $room['summary'],
          'quest_ids' => [(string) ($room['quest_template_id'] ?? ((string) $room['room_id'] . '-quest'))],
          'asset_references' => [
            [
              'asset_type' => 'room',
              'asset_id' => (string) $room['room_id'],
              'asset_role' => (string) ($room['room_role'] ?? 'room'),
            ],
          ],
        ];
      }, $rooms),
    ];

    return [
      'dungeon_outline' => [
        'dungeon_id' => $boss['dungeon_id'],
        'name' => $boss['dungeon_name'],
        'boss_id' => $boss['boss_id'],
        'style' => $boss['dungeon_style'],
        'goal_alignment' => $boss['style'],
        'entrance_room_id' => (string) ($rooms[0]['room_id'] ?? ''),
        'boss_room_id' => (string) ($rooms[count($rooms) - 1]['room_id'] ?? ''),
        'room_count' => count($rooms),
        'rooms' => $rooms,
      ],
      'chapter' => $chapter,
      'quest_templates' => $quest_templates,
      'asset_references' => $asset_references,
    ];
  }

  /**
   * Create the first quest row for a bootstrap storyline so it appears immediately.
   */
  protected function materializeBootstrapQuest(int $campaign_id, array $storyline, array $request): ?array {
    if ($this->questGenerator === NULL) {
      return NULL;
    }

    $storyline_id = trim((string) ($storyline['storyline_id'] ?? ''));
    $storyline_data = is_array($storyline['storyline_data'] ?? NULL) ? $storyline['storyline_data'] : [];
    $template_id = trim((string) ($storyline_data['questline']['primary_quest_id'] ?? ''));
    if ($storyline_id === '' || $template_id === '') {
      return NULL;
    }

    $existing = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('source_template_id', $template_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($existing)) {
      return $existing;
    }

    $context = $this->buildGenerationContext($campaign_id, $request);
    $quest_data = $this->questGenerator->generateQuestFromTemplate($template_id, $campaign_id, [
      'party_level' => (int) ($context['party_level'] ?? 1),
      'difficulty' => 'moderate',
      'location' => (string) ($request['lead_location_id'] ?? ($context['location_id'] ?? '')),
    ]);
    if ($quest_data === []) {
      return NULL;
    }

    $this->database->insert('dc_campaign_quests')
      ->fields($quest_data)
      ->execute();

    $inserted = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('source_template_id', $template_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($inserted)) {
      return NULL;
    }

    $quest_link = is_array($storyline_data['linked_quests'][$template_id] ?? NULL) ? $storyline_data['linked_quests'][$template_id] : [];
    $this->database->update('dc_campaign_quests')
      ->fields([
        'storyline_id' => $storyline_id,
        'storyline_chapter_id' => !empty($quest_link['chapter_id']) ? (string) $quest_link['chapter_id'] : NULL,
        'storyline_scene_id' => !empty($quest_link['scene_id']) ? (string) $quest_link['scene_id'] : NULL,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', (string) ($inserted['quest_id'] ?? ''))
      ->execute();

    return $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', (string) ($inserted['quest_id'] ?? ''))
      ->range(0, 1)
      ->execute()
      ->fetchAssoc() ?: NULL;
  }

  /**
   * Materialize questgiver/boss storyline NPC references into real campaign NPCs.
   *
   * @return array<int, string>
   *   Entity refs realized during this pass.
   */
  protected function realizeStorylineNpcs(int $campaign_id, array $storyline): array {
    return $this->storylineRealizationService?->realizeStorylineNpcs($campaign_id, $storyline) ?? [];
  }

  /**
   * Materialize storyline dungeon, room, and item references into campaign rows.
   *
   * @return array<string, int>
   *   Counts of realized asset rows.
   */
  protected function realizeStorylineAssets(int $campaign_id, array $storyline): array {
    return $this->storylineRealizationService?->realizeStorylineAssets($campaign_id, $storyline) ?? [
      'dungeons' => 0,
      'rooms' => 0,
      'items' => 0,
    ];
  }

  /**
   * Build campaign NPC specs from storyline contacts and generated boss outline.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized NPC specs keyed for dc_npc persistence.
   */
  protected function buildStorylineNpcSpecs(array $storyline_data): array {
    return $this->storylineRealizationService?->buildStorylineNpcSpecs($storyline_data) ?? [];
  }

  /**
   * Normalize storyline-generated NPC fields for dc_npc persistence.
   */
  protected function normalizeStorylineNpcFields(int $campaign_id, array $spec): ?array {
    return $this->storylineRealizationService?->normalizeStorylineNpcFields($campaign_id, $spec);
  }

  /**
   * Build NPC sheet seed payload aligned with NpcService-generated jobs.
   */
  protected function buildNpcSheetSeedData(array $fields): array {
    return $this->storylineRealizationService?->buildNpcSheetSeedData($fields) ?? [];
  }

  /**
   * Extract dungeon outlines from either bootstrap or expanded storyline metadata.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized dungeon outline payloads.
   */
  protected function extractStorylineDungeonOutlines(array $storyline_data): array {
    return $this->storylineRealizationService?->extractStorylineDungeonOutlines($storyline_data) ?? [];
  }

  /**
   * Convert generated identifiers into readable fallback display text.
   */
  protected function humanizeGeneratedIdentifier(string $identifier): string {
    return $this->storylineRealizationService?->humanizeGeneratedIdentifier($identifier) ?? 'Generated Asset';
  }

  /**
   * Normalize or synthesize quest templates for every referenced room quest id.
   */
  protected function normalizeQuestTemplates(array $quest_templates, array $storyline, array $context): array {
    $template_map = [];
    foreach ($quest_templates as $template) {
      if (!is_array($template)) {
        continue;
      }
      $template_id = $this->sanitizeIdentifier((string) ($template['template_id'] ?? ''));
      if ($template_id === '') {
        continue;
      }
      $template['template_id'] = $template_id;
      $template_map[$template_id] = $template;
    }

    foreach ((array) ($storyline['chapters'] ?? []) as $chapter) {
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        foreach ((array) ($scene['quest_ids'] ?? []) as $quest_id) {
          $template_id = $this->sanitizeIdentifier((string) $quest_id);
          if ($template_id === '') {
            continue;
          }
          if (!isset($template_map[$template_id])) {
            $template_map[$template_id] = $this->buildQuestTemplate(
              $template_id,
              [
                'name' => (string) ($storyline['name'] ?? 'Generated Storyline'),
                'dungeon_name' => (string) ($chapter['name'] ?? 'Generated Dungeon'),
                'dungeon_style' => (string) ($storyline['metadata']['goal'] ?? 'generated threat'),
                'style' => (string) ($storyline['metadata']['goal'] ?? 'generated threat'),
                'role' => 'sub_boss',
              ],
              'scene',
              (string) ($scene['summary'] ?? 'generated room'),
              max(1, (int) ($context['party_level'] ?? 1)),
              (string) ($scene['scene_id'] ?? $template_id),
              $this->chooseLootTableId((string) ($scene['summary'] ?? ''), max(1, (int) ($context['party_level'] ?? 1)), 'scene'),
              [
                'encounter_type' => 'exploration',
                'threat_level' => 'moderate',
                'theme' => (string) ($chapter['name'] ?? 'generated'),
                'objective' => (string) ($scene['summary'] ?? 'Advance the storyline'),
              ],
              $this->buildTreasurePlan(max(1, (int) ($context['party_level'] ?? 1)), 'moderate', 'gmg_story_treasures', 'scene', $context)
            );
          }
        }
      }
    }

    ksort($template_map);
    return array_values(array_map(function (array $template): array {
      $level_min = max(1, (int) ($template['level_min'] ?? 1));
      $level_max = max($level_min, (int) ($template['level_max'] ?? $level_min));

      return [
        'template_id' => (string) $template['template_id'],
        'name' => trim((string) ($template['name'] ?? $template['template_id'])),
        'description' => trim((string) ($template['description'] ?? '')),
        'quest_type' => trim((string) ($template['quest_type'] ?? 'main')) ?: 'main',
        'level_min' => $level_min,
        'level_max' => $level_max,
        'tags' => array_values(array_filter(array_map('strval', is_array($template['tags'] ?? NULL) ? $template['tags'] : []))),
        'objectives_schema' => is_array($template['objectives_schema'] ?? NULL) ? $template['objectives_schema'] : [],
        'rewards_schema' => is_array($template['rewards_schema'] ?? NULL) ? $template['rewards_schema'] : [],
        'prerequisites' => is_array($template['prerequisites'] ?? NULL) ? $template['prerequisites'] : ['level_min' => $level_min],
        'story_impact' => is_array($template['story_impact'] ?? NULL) ? $template['story_impact'] : ['generated' => TRUE],
        'estimated_duration_minutes' => max(5, (int) ($template['estimated_duration_minutes'] ?? 20)),
        'version' => (string) ($template['version'] ?? self::QUEST_TEMPLATE_VERSION),
      ];
    }, $template_map));
  }

  /**
   * Ensure storyline scenes and metadata align to the normalized quest templates.
   */
  protected function synchronizeStorylineToQuestTemplates(array $storyline, array $quest_templates, array $context): array {
    $template_ids = array_column($quest_templates, 'template_id');
    $template_ids = array_values(array_filter(array_map('strval', $template_ids)));

    $boss_outline = $storyline['metadata']['generated_outline'] ?? [];
    if (!is_array($boss_outline)) {
      $boss_outline = [];
    }

    $generation_phase = (string) ($boss_outline['generation_phase'] ?? 'expanded');
    $boss_outline['generation_phase'] = $generation_phase;
    if ($generation_phase === 'expanded') {
      $dungeons = is_array($boss_outline['dungeons'] ?? NULL) ? $boss_outline['dungeons'] : [];
      if (count($dungeons) !== 3) {
        throw new \InvalidArgumentException('Generated storyline must contain exactly three boss dungeons.', 400);
      }

      foreach ($dungeons as $dungeon) {
        if (!is_array($dungeon) || count((array) ($dungeon['rooms'] ?? [])) !== 5) {
          throw new \InvalidArgumentException('Each generated boss dungeon must contain exactly five rooms.', 400);
        }
      }

      $progression_connectors = is_array($boss_outline['progression_connectors'] ?? NULL) ? $boss_outline['progression_connectors'] : [];
      if (count($progression_connectors) !== 4) {
        throw new \InvalidArgumentException('Generated storyline must contain exactly four progression connectors.', 400);
      }
    }
    elseif ($generation_phase === 'bootstrap') {
      if (!is_array($boss_outline['entry_dungeon'] ?? NULL)) {
        throw new \InvalidArgumentException('Bootstrap storyline must include an entry_dungeon outline.', 400);
      }
    }

    $storyline['metadata']['generated_outline'] = $boss_outline;
    $storyline['metadata']['quest_template_count'] = count($template_ids);
    $storyline['metadata']['party_level'] = (int) ($context['party_level'] ?? 1);
    $storyline['metadata']['party_size'] = (int) ($context['party_size'] ?? 4);

    foreach ((array) ($storyline['chapters'] ?? []) as $chapter_index => $chapter) {
      foreach ((array) ($chapter['scenes'] ?? []) as $scene_index => $scene) {
        $quest_id = $this->sanitizeIdentifier((string) (($scene['quest_ids'][0] ?? '') ?: (($scene['scene_id'] ?? '') . '-quest')));
        if ($quest_id === '') {
          $quest_id = $this->sanitizeIdentifier((string) ($scene['scene_id'] ?? 'generated-scene')) . '-quest';
        }
        $storyline['chapters'][$chapter_index]['scenes'][$scene_index]['quest_ids'] = [$quest_id];
      }
    }

    if (!is_array($storyline['asset_references'] ?? NULL)) {
      $storyline['asset_references'] = [];
    }
    $storyline['asset_references'][] = [
      'asset_type' => 'location',
      'asset_id' => (string) ($context['location_id'] ?? 'tavern_entrance'),
      'asset_role' => 'starting-location',
      'notes' => 'Campaign location that anchors the generated storyline lead.',
    ];

    return $storyline;
  }

  /**
   * Build a quest template aligned to a generated room.
   */
  protected function buildQuestTemplate(
    string $template_id,
    array $boss,
    string $room_role,
    string $room_style,
    int $level,
    string $room_id,
    string $loot_table_id,
    array $encounter_plan,
    array $treasure_plan
  ): array {
    $quest_name = match ($room_role) {
      'entrance' => 'Enter ' . $boss['dungeon_name'],
      'gauntlet' => 'Break the Gauntlet in ' . $boss['dungeon_name'],
      'sanctum' => 'Uncover the Secret of ' . $boss['dungeon_name'],
      'lieutenant' => 'Defeat the Lieutenant of ' . $boss['name'],
      'boss' => 'Confront ' . $boss['name'],
      default => 'Advance Through ' . $boss['dungeon_name'],
    };

    $objectives_schema = match ($room_role) {
      'entrance' => [
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'reach_' . $this->sanitizeIdentifier($room_id),
              'type' => 'explore',
              'location' => $room_id,
              'description' => 'Reach ' . $room_id . ' and establish a foothold.',
            ],
          ],
        ],
      ],
      'sanctum' => [
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'investigate_' . $this->sanitizeIdentifier($room_id),
              'type' => 'investigate',
              'target' => $room_id,
              'target_count' => 1,
              'description' => 'Investigate the sanctum and recover the clue hidden there.',
            ],
          ],
        ],
      ],
      'boss', 'lieutenant' => [
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'defeat_' . $this->sanitizeIdentifier($boss['boss_id'] ?? $room_id),
              'type' => 'kill',
              'target' => $boss['name'],
              'target_count' => 1,
              'description' => 'Defeat ' . $boss['name'] . ' and break this layer of the campaign threat.',
            ],
          ],
        ],
      ],
      default => [
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'interact_' . $this->sanitizeIdentifier($room_id),
              'type' => 'interact',
              'target' => $room_id,
              'description' => 'Clear the room and secure its strategic objective.',
            ],
          ],
        ],
      ],
    };

    return [
      'template_id' => $template_id,
      'name' => $quest_name,
      'description' => 'Resolve the ' . $room_role . ' challenge inside ' . $boss['dungeon_name'] . '. Style: ' . $room_style . '.',
      'quest_type' => 'main',
      'level_min' => max(1, $level),
      'level_max' => max(1, min(20, $level + ($room_role === 'boss' ? 1 : 0))),
      'tags' => array_values(array_unique([
        'generated',
        $this->sanitizeIdentifier($boss['dungeon_style'] ?? 'generated'),
        $room_role,
        (string) ($encounter_plan['threat_level'] ?? 'moderate'),
      ])),
      'objectives_schema' => $objectives_schema,
      'rewards_schema' => [
        'xp' => [
          'base' => max(20, (int) round(($treasure_plan['currency_gp'] ?? 20) * 2)),
          'per_level' => 10,
        ],
        'gold' => [
          'base' => max(5, (int) round($treasure_plan['currency_gp'] ?? 5)),
          'per_level' => 2,
          'randomize' => TRUE,
        ],
        'items' => [
          'loot_table' => $loot_table_id,
          'count' => $room_role === 'boss' ? 2 : 1,
        ],
      ],
      'prerequisites' => ['level_min' => max(1, $level)],
      'story_impact' => [
        'boss' => (string) ($boss['name'] ?? ''),
        'room_role' => $room_role,
        'encounter_plan' => $encounter_plan,
        'treasure_plan' => $treasure_plan,
      ],
      'estimated_duration_minutes' => $room_role === 'boss' ? 35 : 20,
      'version' => self::QUEST_TEMPLATE_VERSION,
    ];
  }

  /**
   * Pick a loot table aligned to style and room pressure.
   */
  protected function chooseLootTableId(string $style, int $level, string $room_role): string {
    $normalized = strtolower($style);
    if ($room_role === 'boss') {
      return $level >= 3 ? 'gmg_story_treasures' : 'core_weapon_progression';
    }
    if (str_contains($normalized, 'hazard') || str_contains($normalized, 'trap')) {
      return 'core_hazard_toolbox';
    }
    if (str_contains($normalized, 'archive') || str_contains($normalized, 'occult') || str_contains($normalized, 'knowledge')) {
      return 'core_ruin_scavengers';
    }
    if (str_contains($normalized, 'support') || str_contains($normalized, 'sanctum')) {
      return 'core_field_support';
    }
    return $level >= 3 ? 'gmg_story_treasures' : 'core_starter_adventure';
  }

  /**
   * Build a room-level treasure plan using PF2e budget guidance.
   */
  protected function buildTreasurePlan(int $level, string $difficulty, string $loot_table_id, string $room_role, array $context): array {
    $budget = $this->treasureByLevelService->getLevelBudget(max(1, min(20, $level)), (int) ($context['party_size'] ?? 4));
    $difficulty_multiplier = match ($difficulty) {
      'low' => 0.75,
      'moderate' => 1.0,
      'severe' => 1.35,
      'extreme' => 1.7,
      default => 1.0,
    };
    $role_bonus = $room_role === 'boss' ? 1.5 : ($room_role === 'lieutenant' ? 1.2 : 1.0);

    return [
      'loot_table_id' => $loot_table_id,
      'currency_gp' => round(((float) ($budget['per_encounter_gp'] ?? 20)) * $difficulty_multiplier * $role_bonus, 2),
      'permanent_item_level' => max(1, min(20, $level + ($room_role === 'boss' ? 1 : 0))),
      'consumable_item_level' => max(1, min(20, $level)),
      'style_alignment' => $difficulty . ' ' . $room_role . ' reward cadence',
    ];
  }

  /**
   * Build a concise style phrase for a generated room.
   */
  protected function buildRoomStyle(string $dungeon_style, string $room_role, string $boss_style): string {
    return trim($room_role . ' ' . $dungeon_style . ' shaped by ' . $boss_style);
  }

  /**
   * Build a room objective sentence.
   */
  protected function buildRoomObjective(string $room_role, string $boss_name, string $room_style): string {
    return match ($room_role) {
      'entrance' => 'Secure entry to the dungeon and read its defenses.',
      'gauntlet' => 'Push through the defensive pressure built by ' . $boss_name . '.',
      'sanctum' => 'Recover the clue or relic that explains the campaign threat.',
      'lieutenant' => 'Break the lieutenant cell enforcing ' . $boss_name . '\'s will.',
      'boss' => 'Defeat ' . $boss_name . ' in a ' . $room_style . ' climax.',
      default => 'Advance the storyline.',
    };
  }

  /**
   * Build a concise room summary.
   */
  protected function buildRoomSummary(string $room_role, string $boss_name, string $dungeon_style): string {
    return match ($room_role) {
      'entrance' => 'The opening threshold introduces the ' . $dungeon_style . ' tone and points toward ' . $boss_name . '.',
      'gauntlet' => 'A pressure chamber meant to exhaust the party before they reach ' . $boss_name . '.',
      'sanctum' => 'A clue-rich chamber that reveals how ' . $boss_name . ' serves the wider goal.',
      'lieutenant' => 'A fortified hold where one of ' . $boss_name . '\'s lieutenants enforces the dungeon style.',
      'boss' => 'The final chamber where ' . $boss_name . ' embodies the dungeon threat at full strength.',
      default => 'A generated room aligned to the dungeon style.',
    };
  }

  /**
   * Build room NPC suggestions aligned to role and style.
   */
  protected function buildRoomNpcIds(string $room_role, array $boss): array {
    $ids = [
      $this->sanitizeIdentifier(($boss['dungeon_id'] ?? 'generated-dungeon') . '-' . $room_role . '-sentinel'),
    ];

    if ($room_role === 'sanctum') {
      $ids[] = $this->sanitizeIdentifier(($boss['dungeon_id'] ?? 'generated-dungeon') . '-bound-witness');
    }
    elseif ($room_role === 'lieutenant') {
      $ids[] = $this->sanitizeIdentifier(($boss['boss_id'] ?? 'generated-boss') . '-lieutenant');
    }
    elseif ($room_role === 'boss') {
      $ids[] = $this->sanitizeIdentifier((string) ($boss['boss_id'] ?? 'generated-boss'));
    }

    return array_values(array_unique(array_filter($ids)));
  }

  /**
   * Build room item ids aligned to role and loot table usage.
   */
  protected function buildRoomItemIds(string $room_role, array $boss, string $loot_table_id): array {
    return [
      $this->sanitizeIdentifier(($boss['dungeon_id'] ?? 'generated-dungeon') . '-' . $room_role . '-relic'),
      $this->sanitizeIdentifier($loot_table_id . '-' . $room_role . '-cache'),
    ];
  }

  /**
   * Build the directed handoff chain through the generated boss arc.
   */
  protected function buildProgressionConnectors(string $base_slug, string $location_id, array $dungeons, array $boss_specs, string $goal): array {
    $connector_one = [
      'connector_id' => $base_slug . '-handoff-1',
      'source_type' => 'npc',
      'source_id' => $base_slug . '-patron',
      'mechanism' => 'npc_direction',
      'clue_item_id' => $this->sanitizeIdentifier(($dungeons[0]['dungeon_id'] ?? 'dungeon-1') . '-entrance-relic'),
      'from_location_id' => $location_id,
      'target_dungeon_id' => (string) ($dungeons[0]['dungeon_id'] ?? ''),
      'target_room_id' => (string) ($dungeons[0]['entrance_room_id'] ?? ''),
      'narrative' => 'The quest giver directs the party to the first dungeon entrance.',
    ];

    $connector_two = [
      'connector_id' => $base_slug . '-handoff-2',
      'source_type' => 'npc',
      'source_id' => (string) ($boss_specs[0]['boss_id'] ?? ''),
      'mechanism' => 'clue_or_confession',
      'clue_item_id' => $this->sanitizeIdentifier(($dungeons[0]['boss_room_id'] ?? 'dungeon-1-boss-room') . '-relic'),
      'from_location_id' => (string) ($dungeons[0]['boss_room_id'] ?? ''),
      'target_dungeon_id' => (string) ($dungeons[1]['dungeon_id'] ?? ''),
      'target_room_id' => (string) ($dungeons[1]['entrance_room_id'] ?? ''),
      'narrative' => 'Sub-boss 1 reveals or drops the clue to dungeon entrance 2.',
    ];

    $connector_three = [
      'connector_id' => $base_slug . '-handoff-3',
      'source_type' => 'npc',
      'source_id' => (string) ($boss_specs[1]['boss_id'] ?? ''),
      'mechanism' => 'clue_or_confession',
      'clue_item_id' => $this->sanitizeIdentifier(($dungeons[1]['boss_room_id'] ?? 'dungeon-2-boss-room') . '-relic'),
      'from_location_id' => (string) ($dungeons[1]['boss_room_id'] ?? ''),
      'target_dungeon_id' => (string) ($dungeons[2]['dungeon_id'] ?? ''),
      'target_room_id' => (string) ($dungeons[2]['entrance_room_id'] ?? ''),
      'narrative' => 'Sub-boss 2 points the party to dungeon entrance 3.',
    ];

    $connector_four = [
      'connector_id' => $base_slug . '-handoff-4',
      'source_type' => 'npc',
      'source_id' => (string) ($boss_specs[2]['boss_id'] ?? ''),
      'mechanism' => 'goal_anchor',
      'clue_item_id' => $this->sanitizeIdentifier(($dungeons[2]['boss_room_id'] ?? 'dungeon-3-boss-room') . '-relic'),
      'from_location_id' => (string) ($dungeons[2]['boss_room_id'] ?? ''),
      'target_dungeon_id' => (string) ($dungeons[2]['dungeon_id'] ?? ''),
      'target_room_id' => (string) ($dungeons[2]['boss_room_id'] ?? ''),
      'goal' => $goal,
      'narrative' => 'The final boss directly embodies the campaign goal.',
    ];

    return [$connector_one, $connector_two, $connector_three, $connector_four];
  }

  /**
   * Convert progression connectors into storyline asset-link edges.
   */
  protected function buildProgressionAssetReferences(array $progression_connectors): array {
    $references = [];

    foreach ($progression_connectors as $connector) {
      if (!is_array($connector)) {
        continue;
      }

      if (!empty($connector['source_id'])) {
        $references[] = [
          'asset_type' => (string) (($connector['source_type'] ?? 'npc') === 'item' ? 'item' : 'npc'),
          'asset_id' => (string) $connector['source_id'],
          'asset_role' => 'progression-source',
          'notes' => (string) ($connector['narrative'] ?? ''),
          'link_data' => $connector,
        ];
      }

      if (!empty($connector['clue_item_id'])) {
        $references[] = [
          'asset_type' => 'item',
          'asset_id' => (string) $connector['clue_item_id'],
          'asset_role' => 'progression-clue',
          'notes' => (string) ($connector['narrative'] ?? ''),
          'link_data' => $connector,
        ];
      }
    }

    return $references;
  }

  /**
   * Guess party level from campaign state.
   */
  protected function guessPartyLevelFromState(array $state): int {
    $levels = [];
    $characters = $state['characters'] ?? $state['party']['characters'] ?? [];
    if (is_array($characters)) {
      foreach ($characters as $character) {
        if (is_array($character) && isset($character['level']) && is_numeric($character['level'])) {
          $levels[] = (int) $character['level'];
        }
      }
    }

    if ($levels === []) {
      return 1;
    }

    return max(1, (int) round(array_sum($levels) / count($levels)));
  }

  /**
   * Guess party size from campaign state.
   */
  protected function guessPartySizeFromState(array $state): int {
    $characters = $state['characters'] ?? $state['party']['characters'] ?? [];
    return max(1, is_array($characters) ? count($characters) : 4);
  }

  /**
   * Guess a location identifier from campaign state.
   */
  protected function guessLocationIdFromState(array $state): string {
    return trim((string) ($state['current_room_id'] ?? $state['room_id'] ?? $state['currentLocationId'] ?? 'tavern_entrance'));
  }

  /**
   * Parse a simple level range string into bounds.
   */
  protected function parseLevelRange(string $level_range): array {
    if (preg_match('/(\d+)\s*-\s*(\d+)/', $level_range, $matches)) {
      $min = max(1, min(20, (int) $matches[1]));
      $max = max($min, min(20, (int) $matches[2]));
      return ['min' => $min, 'max' => $max];
    }

    $level = max(1, min(20, (int) preg_replace('/\D+/', '', $level_range)));
    return ['min' => $level ?: 1, 'max' => max(1, $level ?: 4)];
  }

  /**
   * Derive a presentable storyline name from the prompt.
   */
  protected function deriveStorylineName(string $prompt): string {
    $trimmed = trim($prompt);
    if ($trimmed === '') {
      return 'Generated Storyline';
    }

    $trimmed = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;
    $trimmed = ucfirst(rtrim($trimmed, '.!?'));
    return mb_strlen($trimmed) > 60 ? mb_substr($trimmed, 0, 60) : $trimmed;
  }

  /**
   * Derive a style seed from prompt and tone.
   */
  protected function deriveStyleSeed(string $prompt, string $tone): string {
    $candidate = strtolower(trim($prompt . ' ' . $tone));
    foreach (['plague', 'shadow', 'storm', 'ash', 'echo', 'iron', 'void', 'relic', 'wyrm', 'crown'] as $keyword) {
      if (str_contains($candidate, $keyword)) {
        return $keyword;
      }
    }

    return 'ruin';
  }

  /**
   * Normalize a prompt into a sentence-like goal.
   */
  protected function normalizeSentence(string $text): string {
    $text = trim($text);
    if ($text === '') {
      return 'Stop the threat before it spreads.';
    }
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return rtrim($text, '.!?') . '.';
  }

  /**
   * Sanitize identifiers into stable slugs.
   */
  protected function sanitizeIdentifier(string $value, int $max_length = 0): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    if ($max_length > 0 && strlen($value) > $max_length) {
      $value = rtrim(substr($value, 0, $max_length), '-');
    }
    return $value;
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
