<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Coordinates storyline templates and campaign storyline instances.
 */
class StorylineManagerService {

  public const STORYLINE_DEFINITION_SCHEMA_VERSION = 'storyline-definition-v1';
  public const STORYLINE_RUNTIME_SCHEMA_VERSION = 'storyline-runtime-v1';
  private const CANONICAL_STORYLINE_TABLE = 'dc_canonical_storylines';
  private const CANONICAL_STORYLINE_REQUIRED_FIELDS = [
    'template_id',
    'name',
    'template_data',
    'created_at',
    'updated_at',
  ];

  protected Connection $database;
  protected LoggerInterface $logger;
  protected UuidInterface $uuid;
  protected CampaignStateService $campaignStateService;
  protected StateValidationService $stateValidationService;
  protected ?StorylineQuestLifecycleService $storylineQuestLifecycleService;
  protected ?ContentRegistry $contentRegistry;
  protected ?StorylineRealizationService $storylineRealizationService;
  protected ?ObjectiveTypeService $objectiveTypeService;
  protected ?array $canonicalLocationTemplateIndex = NULL;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    UuidInterface $uuid,
    CampaignStateService $campaign_state_service,
    StateValidationService $state_validation_service,
    mixed $arg6 = NULL,
    mixed $arg7 = NULL,
    mixed $arg8 = NULL,
    mixed $arg9 = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->uuid = $uuid;
    $this->campaignStateService = $campaign_state_service;
    $this->stateValidationService = $state_validation_service;
    [
      'storyline_quest_lifecycle_service' => $storyline_quest_lifecycle_service,
      'storyline_realization_service' => $storyline_realization_service,
      'objective_type_service' => $objective_type_service,
      'content_registry' => $content_registry,
    ] = $this->resolveOptionalConstructorDependencies($arg6, $arg7, $arg8, $arg9);
    $this->storylineQuestLifecycleService = $storyline_quest_lifecycle_service;
    $this->storylineRealizationService = $storyline_realization_service;
    $this->objectiveTypeService = $objective_type_service;
    $this->contentRegistry = $content_registry;
  }

  /**
   * Resolve optional constructor services from mixed argument ordering.
   *
   * Supports both legacy and current container argument positions to avoid
   * runtime fatals while service containers refresh.
   *
   * @return array{
   *   storyline_quest_lifecycle_service: ?StorylineQuestLifecycleService,
   *   storyline_realization_service: ?StorylineRealizationService,
   *   objective_type_service: ?ObjectiveTypeService,
   *   content_registry: ?ContentRegistry
   * }
   */
  protected function resolveOptionalConstructorDependencies(
    mixed $arg6,
    mixed $arg7,
    mixed $arg8,
    mixed $arg9
  ): array {
    $storyline_quest_lifecycle_service = NULL;
    $storyline_realization_service = NULL;
    $objective_type_service = NULL;
    $content_registry = NULL;

    foreach ([$arg6, $arg7, $arg8, $arg9] as $argument) {
      if ($argument === NULL) {
        continue;
      }
      if ($argument instanceof StorylineQuestLifecycleService) {
        $storyline_quest_lifecycle_service = $argument;
        continue;
      }
      if ($argument instanceof StorylineRealizationService) {
        $storyline_realization_service = $argument;
        continue;
      }
      if ($argument instanceof ObjectiveTypeService) {
        $objective_type_service = $argument;
        continue;
      }
      if ($argument instanceof ContentRegistry) {
        $content_registry = $argument;
        continue;
      }
      throw new \InvalidArgumentException(sprintf(
        'Unsupported StorylineManagerService constructor dependency argument type: %s',
        get_debug_type($argument)
      ));
    }

    return [
      'storyline_quest_lifecycle_service' => $storyline_quest_lifecycle_service instanceof StorylineQuestLifecycleService
        ? $storyline_quest_lifecycle_service
        : NULL,
      'storyline_realization_service' => $storyline_realization_service instanceof StorylineRealizationService
        ? $storyline_realization_service
        : NULL,
      'objective_type_service' => $objective_type_service instanceof ObjectiveTypeService
        ? $objective_type_service
        : NULL,
      'content_registry' => $content_registry instanceof ContentRegistry
        ? $content_registry
        : NULL,
    ];
  }

  /**
   * Returns all stored storyline templates.
   */
  public function listTemplates(): array {
    $this->assertCanonicalStorylineStorageReady();

    $rows = $this->database->select(self::CANONICAL_STORYLINE_TABLE, 's')
      ->fields('s')
      ->orderBy('name', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_values(array_map(fn(array $row): array => $this->hydrateTemplateRow($row), $rows));
  }

  /**
   * Returns canonical quest template ids from DB-authoritative storage.
   *
   * @return array<int, string>
   *   Sorted quest template ids.
   */
  public function listCanonicalQuestTemplateIds(): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_canonical_quests')) {
      throw new \RuntimeException(
        'Canonical quest table dc_canonical_quests is required to load quest template ids.'
      );
    }
    if (!$schema->fieldExists('dc_canonical_quests', 'template_id')) {
      throw new \RuntimeException(
        'Canonical quest table dc_canonical_quests is missing required template_id column.'
      );
    }

    $rows = $this->database->select('dc_canonical_quests', 't')
      ->fields('t', ['template_id'])
      ->orderBy('template_id', 'ASC')
      ->execute()
      ->fetchCol();

    $ids = array_values(array_filter(
      array_map(static fn($value): string => trim((string) $value), is_array($rows) ? $rows : []),
      static fn(string $id): bool => $id !== ''
    ));
    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NATURAL | SORT_FLAG_CASE);

    return $ids;
  }

  /**
   * Loads a single storyline template.
   */
  public function getTemplate(string $template_id): ?array {
    $this->assertCanonicalStorylineStorageReady();

    $row = $this->database->select(self::CANONICAL_STORYLINE_TABLE, 's')
      ->fields('s')
      ->condition('template_id', $template_id)
      ->execute()
      ->fetchAssoc();

    return $row ? $this->hydrateTemplateRow($row) : NULL;
  }

  /**
   * Creates or updates a storyline template from authored JSON.
   */
  public function saveTemplate(array $definition): array {
    $this->assertCanonicalStorylineStorageReady();

    $normalized = $this->normalizeStorylineDefinition($definition);
    $existing = $this->getTemplate((string) $normalized['template_id']);
    $now = time();
    $schema = $this->database->schema();

    $fields = [
      'name' => (string) $normalized['name'],
      'synopsis' => (string) ($normalized['synopsis'] ?? ''),
      'level_range' => (string) ($normalized['level_range'] ?? ''),
      'source' => (string) ($normalized['source'] ?? ''),
      'tags' => json_encode($normalized['tags'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'template_data' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'updated_at' => $now,
    ];
    if (!$schema->fieldExists(self::CANONICAL_STORYLINE_TABLE, 'level_range')) {
      unset($fields['level_range']);
    }
    if (!$schema->fieldExists(self::CANONICAL_STORYLINE_TABLE, 'source')) {
      unset($fields['source']);
    }
    if (!$schema->fieldExists(self::CANONICAL_STORYLINE_TABLE, 'tags')) {
      unset($fields['tags']);
    }

    if ($existing) {
      $this->database->update(self::CANONICAL_STORYLINE_TABLE)
        ->fields($fields)
        ->condition('template_id', (string) $normalized['template_id'])
        ->execute();
    }
    else {
      $fields['template_id'] = (string) $normalized['template_id'];
      $fields['created_at'] = $now;
      $this->database->insert(self::CANONICAL_STORYLINE_TABLE)
        ->fields($fields)
        ->execute();
    }

    return $this->getTemplate((string) $normalized['template_id']) ?? $normalized;
  }

  /**
   * Creates a campaign storyline instance from a raw definition.
   */
  public function createCampaignStoryline(int $campaign_id, array $definition, array $options = []): array {
    $this->assertStorylineStorageReady();
    $this->campaignStateService->getState($campaign_id);

    $normalized = $this->normalizeStorylineDefinition($definition);
    $instance = $this->buildInitialStorylineState($normalized, $options);
    $storyline_id = $this->generateCampaignStorylineId($campaign_id, (string) ($options['storyline_id'] ?? $normalized['template_id']));
    $status = !empty($options['activate']) ? 'active' : ((string) ($options['status'] ?? 'available'));
    $is_primary = !empty($options['is_primary']);
    $now = time();
    $template_version = trim((string) ($normalized['version'] ?? ($normalized['metadata']['version'] ?? '1.0.0')));
    if ($template_version === '') {
      $template_version = '1.0.0';
    }
    $contract_hash = hash('sha256', json_encode($instance['storyline_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $this->database->insert('dc_campaign_storylines')
      ->fields([
        'campaign_id' => $campaign_id,
        'storyline_id' => $storyline_id,
        'template_id' => isset($normalized['template_id']) ? (string) $normalized['template_id'] : NULL,
        'name' => (string) $normalized['name'],
        'status' => $status,
        'priority' => isset($options['priority']) ? (int) $options['priority'] : 0,
        'is_primary' => $is_primary ? 1 : 0,
        'current_chapter_id' => $instance['current_chapter_id'] ?: NULL,
        'current_scene_id' => $instance['current_scene_id'] ?: NULL,
        'storyline_data' => json_encode($instance['storyline_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variables' => json_encode($instance['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'template_version' => $template_version,
        'contract_hash' => $contract_hash,
        'created_at' => $now,
        'updated_at' => $now,
        'activated_at' => $status === 'active' ? $now : NULL,
        'completed_at' => NULL,
      ])
      ->execute();

    $this->attachQuestReferences(
      $campaign_id,
      $storyline_id,
      $instance['storyline_data']['linked_quests'] ?? []
    );
    $this->syncCampaignStorylineAssetLinks(
      $campaign_id,
      $storyline_id,
      $instance['storyline_data']['asset_references'] ?? []
    );
    $this->synchronizeCampaignValidationIndexes($campaign_id, $storyline_id);

    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      'storyline_created',
      [
        'template_id' => $normalized['template_id'] ?? NULL,
        'status' => $status,
      ],
      'Storyline created: ' . (string) $normalized['name']
    );

    if ($status === 'active' || $is_primary) {
      $this->persistCampaignStorylinePointers($campaign_id, $storyline_id, $is_primary);
    }

    return $this->finalizePersistedCampaignStoryline($campaign_id, $storyline_id, [
      'realize_storyline_assets' => !empty($options['realize_storyline_assets']),
    ]) ?? [];
  }

  /**
   * Replace the definition/runtime payload for an existing campaign storyline.
   */
  public function replaceCampaignStorylineDefinition(int $campaign_id, string $storyline_id, array $definition, array $options = []): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }

    $normalized = $this->normalizeStorylineDefinition($definition);
    $existing_storyline_data = $this->decodeJsonColumn($row['storyline_data'] ?? NULL);
    $existing_variables = $this->decodeJsonColumn($row['variables'] ?? NULL);
    $runtime_options = $options + [
      'activate' => (string) ($row['status'] ?? '') === 'active',
      'status' => (string) ($options['status'] ?? ($row['status'] ?? 'available')),
      'variables' => $existing_variables,
    ];
    $instance = $this->buildInitialStorylineState($normalized, $runtime_options);
    $status = (string) ($runtime_options['status'] ?? ($row['status'] ?? 'available'));
    $now = time();
    $template_version = trim((string) ($normalized['version'] ?? ($normalized['metadata']['version'] ?? '1.0.0')));
    if ($template_version === '') {
      $template_version = '1.0.0';
    }
    $contract_hash = hash('sha256', json_encode($instance['storyline_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $this->database->update('dc_campaign_storylines')
      ->fields([
        'template_id' => isset($normalized['template_id']) ? (string) $normalized['template_id'] : NULL,
        'name' => (string) $normalized['name'],
        'status' => $status,
        'current_chapter_id' => $instance['current_chapter_id'] ?: NULL,
        'current_scene_id' => $instance['current_scene_id'] ?: NULL,
        'storyline_data' => json_encode($instance['storyline_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variables' => json_encode($instance['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'template_version' => $template_version,
        'contract_hash' => $contract_hash,
        'updated_at' => $now,
        'activated_at' => $status === 'active'
          ? (!empty($row['activated_at']) ? (int) $row['activated_at'] : $now)
          : NULL,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $this->attachQuestReferences(
      $campaign_id,
      $storyline_id,
      $instance['storyline_data']['linked_quests'] ?? []
    );
    $this->syncCampaignStorylineAssetLinks(
      $campaign_id,
      $storyline_id,
      $instance['storyline_data']['asset_references'] ?? []
    );
    $this->synchronizeCampaignValidationIndexes($campaign_id, $storyline_id);
    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      'storyline_definition_replaced',
      [
        'previous_generation_phase' => (string) (($existing_storyline_data['metadata']['generated_outline']['generation_phase'] ?? '')),
        'current_generation_phase' => (string) (($instance['storyline_data']['metadata']['generated_outline']['generation_phase'] ?? '')),
        'status' => $status,
      ],
      'Storyline definition updated: ' . (string) $normalized['name']
    );

    return $this->finalizePersistedCampaignStoryline($campaign_id, $storyline_id);
  }

  /**
   * Finalize a persisted storyline so all creation paths share realization.
   */
  protected function finalizePersistedCampaignStoryline(int $campaign_id, string $storyline_id, array $options = []): ?array {
    $storyline = $this->getCampaignStoryline($campaign_id, $storyline_id, FALSE);
    if (!is_array($storyline) || $storyline === []) {
      return $storyline;
    }

    if (!empty($options['realize_storyline_assets']) && $this->storylineRealizationService !== NULL) {
      $this->storylineRealizationService->realizeStorylineAssets($campaign_id, $storyline);
      $this->storylineRealizationService->realizeStorylineNpcs($campaign_id, $storyline);
    }

    $this->ensureActiveStorylineRoomsAvailableInCampaign($campaign_id, $storyline);

    return $storyline;
  }

  /**
   * Normalize and validate a storyline definition for storage/runtime use.
   */
  public function normalizeStorylineDefinition(array $definition): array {
    $normalized = $this->normalizeTemplateDefinition($definition);
    $validation = $this->validateNormalizedStorylineDefinition($normalized);
    if (!($validation['valid'] ?? FALSE)) {
      throw new \InvalidArgumentException('Storyline definition failed validation: ' . implode('; ', $validation['errors'] ?? []), 400);
    }

    return $normalized;
  }

  /**
   * Validate a normalized storyline definition when schema validation is wired.
   */
  public function validateNormalizedStorylineDefinition(array $definition): array {
    return $this->validateStorylineEndToEndContract($definition, 'definition');
  }

  /**
   * Validate storyline runtime payloads before they enter management flows.
   */
  public function validateRuntimeStorylineContract(array $storyline_data): array {
    return $this->validateStorylineEndToEndContract($storyline_data, 'runtime');
  }

  /**
   * Loads DB-authoritative objective phases for one quest template id.
   *
   * @param string $template_id
   *   Quest template id.
   *
   * @return array<int, mixed>|null
   *   Objective phase payload or NULL when template is missing.
   */
  public function getCanonicalQuestTemplateObjectivePhases(string $template_id): ?array {
    return $this->loadQuestTemplateObjectivePhases($template_id);
  }

  /**
   * Load one canonical dungeon template row by dungeon id.
   *
   * @return array<string, mixed>|null
   *   Canonical dungeon row or NULL when missing.
   */
  public function getCanonicalDungeonTemplate(string $dungeon_id): ?array {
    return $this->loadCanonicalDungeonEntity($dungeon_id);
  }

  /**
   * Load one canonical room template row by room id.
   *
   * @return array<string, mixed>|null
   *   Canonical room row or NULL when missing.
   */
  public function getCanonicalRoomTemplate(string $room_id): ?array {
    return $this->loadCanonicalRoomEntity($room_id);
  }

  /**
   * Loads canonical location template index used by storyline validators.
   *
   * @return array{
   *   dungeon_ids: array<string, bool>,
   *   room_ids: array<string, bool>,
   *   dungeon_room_ids: array<string, array<string, bool>>,
   *   errors: array<int, string>
   * }
   *   Canonical location index plus load-time diagnostics.
   */
  public function getCanonicalLocationTemplateIndex(): array {
    return $this->loadCanonicalLocationTemplateIndex();
  }

  /**
   * Validate objective control-chain contracts against in-memory quest templates.
   *
   * This is used by generation pre-persist gates so objective validation does not
   * depend on DB canonical template rows that may not exist yet.
   *
   * @param array<string, mixed> $storyline_definition
   *   Storyline definition payload used to derive reference anchors.
   * @param array<int, mixed> $quest_templates
   *   Generated quest template payloads containing objectives_schema.
   *
   * @return array<int, string>
   *   Aggregated objective control-chain errors.
   */
  public function validateObjectiveControlChainForGeneratedTemplates(array $storyline_definition, array $quest_templates): array {
    $anchors = $this->collectObjectiveReferenceAnchors($storyline_definition);
    $errors = [];

    foreach ($quest_templates as $index => $template) {
      if (!is_array($template)) {
        continue;
      }
      $template_id = trim((string) ($template['template_id'] ?? ''));
      $template_label = $template_id !== '' ? $template_id : 'generated-template-' . $index;
      $template_objectives = is_array($template['objectives_schema'] ?? NULL) ? $template['objectives_schema'] : [];
      if ($template_objectives === []) {
        $errors[] = "Quest template '{$template_label}' has an empty objectives_schema payload.";
        continue;
      }

      $errors = array_merge($errors, $this->validateQuestObjectiveControlChain(
        $template_objectives,
        $anchors,
        "quest template '{$template_label}' objectives_schema"
      ));
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate a storyline payload with explicit end-to-end stages.
   *
   * @param array $storyline_data
   *   Storyline definition/runtime payload.
   * @param string $payload_type
   *   One of: definition, runtime.
   *
   * @return array{
   *   valid: bool,
   *   errors: array<int, string>,
   *   stages: array<string, array{valid: bool, errors: array<int, string>}>,
   *   payload_type: string
   * }
   *   Stage-by-stage validation results and aggregate status.
   */
  public function validateStorylineEndToEndContract(array $storyline_data, string $payload_type = 'runtime'): array {
    $normalized_payload_type = strtolower(trim($payload_type));
    if (!in_array($normalized_payload_type, ['definition', 'runtime'], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unsupported storyline payload type "%s".', $payload_type), 400);
    }

    $stages = [];

    $schema_errors = $this->validateStorylineSchemaStage($storyline_data, $normalized_payload_type);
    $stages['schema'] = [
      'valid' => $schema_errors === [],
      'errors' => $schema_errors,
    ];

    $cross_reference_errors = $this->validateStorylineCrossReferences($storyline_data);
    $stages['cross_references'] = [
      'valid' => $cross_reference_errors === [],
      'errors' => $cross_reference_errors,
    ];

    $questline_progression_errors = $this->validateQuestlineProgressionFlow($storyline_data);
    $stages['questline_progression'] = [
      'valid' => $questline_progression_errors === [],
      'errors' => $questline_progression_errors,
    ];

    $navigation_progression_errors = $this->validateNavigationProgressionFlow($storyline_data, $normalized_payload_type);
    $stages['navigation_progression'] = [
      'valid' => $navigation_progression_errors === [],
      'errors' => $navigation_progression_errors,
    ];

    $objective_control_chain_errors = $this->validateObjectiveControlChainStage($storyline_data, $normalized_payload_type);
    $stages['objective_control_chain'] = [
      'valid' => $objective_control_chain_errors === [],
      'errors' => $objective_control_chain_errors,
    ];

    $entity_type_contract_errors = $this->validateReferencedEntityTypeContractsStage($storyline_data, $normalized_payload_type);
    $stages['entity_type_contracts'] = [
      'valid' => $entity_type_contract_errors === [],
      'errors' => $entity_type_contract_errors,
    ];

    $task_contract_errors = $this->validateTaskContractsStage($storyline_data, $normalized_payload_type);
    $stages['task_contracts'] = [
      'valid' => $task_contract_errors === [],
      'errors' => $task_contract_errors,
    ];

    $objective_playability_errors = $this->validateObjectivePlayabilityStage($storyline_data, $normalized_payload_type);
    $stages['objective_playability'] = [
      'valid' => $objective_playability_errors === [],
      'errors' => $objective_playability_errors,
    ];

    $entity_linkage_errors = $this->validateEntityLinkageStage($storyline_data, $normalized_payload_type);
    $stages['entity_linkage'] = [
      'valid' => $entity_linkage_errors === [],
      'errors' => $entity_linkage_errors,
    ];

    $dungeon_room_contract_errors = $this->validateDungeonRoomContractsStage($storyline_data, $normalized_payload_type);
    $stages['dungeon_room_contracts'] = [
      'valid' => $dungeon_room_contract_errors === [],
      'errors' => $dungeon_room_contract_errors,
    ];

    $errors = [];
    foreach ($stages as $stage) {
      $errors = array_merge($errors, $stage['errors']);
    }

    return [
      'valid' => $errors === [],
      'errors' => array_values(array_unique($errors)),
      'stages' => $stages,
      'payload_type' => $normalized_payload_type,
    ];
  }

  /**
   * Stage scaffold: validate referenced entities against per-type contracts.
   *
   * This stage intentionally starts as a no-op contract scaffold so we can
   * incrementally add deep validators per entity type without changing calling
   * code or stage wiring.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateReferencedEntityTypeContractsStage(array $storyline_data, string $payload_type): array {
    if ($this->contentRegistry === NULL) {
      return ['Canonical validation dependency missing: ContentRegistry is required for entity_type_contracts stage execution.'];
    }

    $errors = [];
    $asset_references = array_values(array_filter(is_array($storyline_data['asset_references'] ?? NULL) ? $storyline_data['asset_references'] : [], 'is_array'));
    foreach ($asset_references as $index => $reference) {
      $asset_type = strtolower(trim((string) ($reference['asset_type'] ?? '')));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_type === '' || $asset_id === '') {
        continue;
      }
      $errors = array_merge($errors, $this->validateReferencedEntityByTypeStub(
        $asset_type,
        $asset_id,
        [
          'payload_type' => $payload_type,
          'path' => "asset_references[{$index}]",
          'source' => 'asset_reference',
          'reference' => $reference,
        ]
      ));
    }

    $contacts = array_values(array_filter(is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : [], 'is_array'));
    foreach ($contacts as $index => $contact) {
      $entity_type = strtolower(trim((string) ($contact['entity_type'] ?? '')));
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_type === '' || $entity_id === '') {
        continue;
      }
      $errors = array_merge($errors, $this->validateReferencedEntityByTypeStub(
        $entity_type,
        $entity_id,
        [
          'payload_type' => $payload_type,
          'path' => "contacts[{$index}]",
          'source' => 'contact',
          'reference' => $contact,
        ]
      ));
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate objective child-task contract rules for canonical definitions.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateTaskContractsStage(array $storyline_data, string $payload_type): array {
    if ($payload_type !== 'definition') {
      return [];
    }
    if ($this->objectiveTypeService === NULL) {
      return ['Canonical validation dependency missing: ObjectiveTypeService is required for task_contracts stage execution.'];
    }

    $errors = [];
    $player_interaction_types = ['interact', 'collect', 'explore', 'escort', 'investigate', 'kill'];
    $supports_children_types = ['composite', 'escort'];
    $quest_ids = $this->collectStorylineQuestIdsForObjectiveValidation($storyline_data);

    foreach ($quest_ids as $quest_id) {
      try {
        $phases = $this->loadQuestTemplateObjectivePhases($quest_id);
      }
      catch (\Throwable $exception) {
        $errors[] = "Task contract validation for quest template '{$quest_id}' failed: {$exception->getMessage()}";
        continue;
      }
      if ($phases === NULL || $phases === []) {
        continue;
      }
      $task_ids_seen = [];
      foreach ($phases as $phase_index => $phase) {
        if (!is_array($phase)) {
          continue;
        }
        foreach ((array) ($phase['objectives'] ?? []) as $obj_index => $objective) {
          if (!is_array($objective)) {
            continue;
          }
          $children = is_array($objective['children'] ?? NULL) ? $objective['children'] : [];
          $obj_type = strtolower(trim((string) ($objective['type'] ?? '')));
          $obj_path = "quest.{$quest_id}.phase[{$phase_index}].objective[{$obj_index}]";
          if ($children === []) {
            if ($obj_type === 'composite') {
              $errors[] = "{$obj_path}: composite objectives must include children tasks";
            }
            continue;
          }
          if (!in_array($obj_type, $supports_children_types, TRUE)) {
            $errors[] = "{$obj_path}: type '{$obj_type}' does not support children; use composite or escort";
            continue;
          }
          $criteria_kind = strtolower(trim((string) ($objective['completion_criteria']['kind'] ?? '')));
          if ($criteria_kind !== 'all_children') {
            $errors[] = "{$obj_path}: objective with children must use completion_criteria.kind=all_children";
          }

          foreach ($children as $task_index => $task) {
            if (!is_array($task)) {
              $errors[] = "{$obj_path}.children[{$task_index}]: task must be an object";
              continue;
            }
            $task_path = "{$obj_path}.children[{$task_index}]";
            $task_id = trim((string) ($task['objective_id'] ?? ''));
            if ($task_id === '') {
              $errors[] = "{$task_path}: objective_id (task_id) is required";
            }
            else {
              $dedup_key = $quest_id . '::' . $task_id;
              if (isset($task_ids_seen[$dedup_key])) {
                $errors[] = "{$task_path}: duplicate objective_id '{$task_id}' in quest '{$quest_id}'";
              }
              $task_ids_seen[$dedup_key] = TRUE;
            }

            if (trim((string) ($task['description'] ?? '')) === '') {
              $errors[] = "{$task_path}: description is required";
            }

            $task_criteria = $task['completion_criteria'] ?? NULL;
            if (!is_array($task_criteria)) {
              $errors[] = "{$task_path}: completion_criteria is required";
            }
            else {
              $kind = strtolower(trim((string) ($task_criteria['kind'] ?? '')));
              if (!in_array($kind, ['count', 'flag', 'all_children'], TRUE)) {
                $errors[] = "{$task_path}: completion_criteria.kind must be count, flag, or all_children";
              }
              if (trim((string) ($task_criteria['metric'] ?? '')) === '') {
                $errors[] = "{$task_path}: completion_criteria.metric is required";
              }
              if (trim((string) ($task_criteria['description'] ?? '')) === '') {
                $errors[] = "{$task_path}: completion_criteria.description is required";
              }
            }

            $task_type = strtolower(trim((string) ($task['type'] ?? '')));
            if (in_array($task_type, $player_interaction_types, TRUE) && trim((string) ($task['next_step'] ?? '')) === '') {
              $errors[] = "{$task_path}: next_step is required for player-interaction task type '{$task_type}'";
            }
          }
        }
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate objective playability contracts for canonical definitions.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateObjectivePlayabilityStage(array $storyline_data, string $payload_type): array {
    if ($payload_type !== 'definition') {
      return [];
    }
    if ($this->objectiveTypeService === NULL) {
      return ['Canonical validation dependency missing: ObjectiveTypeService is required for objective_playability stage execution.'];
    }

    $errors = [];
    $quest_ids = $this->collectStorylineQuestIdsForObjectiveValidation($storyline_data);
    foreach ($quest_ids as $quest_id) {
      try {
        $phases = $this->loadQuestTemplateObjectivePhases($quest_id);
      }
      catch (\Throwable $exception) {
        $errors[] = "Objective playability validation for quest template '{$quest_id}' failed: {$exception->getMessage()}";
        continue;
      }
      if ($phases === NULL || $phases === []) {
        continue;
      }

      foreach ($phases as $phase_index => $phase) {
        if (!is_array($phase)) {
          continue;
        }
        foreach ((array) ($phase['objectives'] ?? []) as $obj_index => $objective) {
          if (!is_array($objective)) {
            continue;
          }
          $obj_path = "quest.{$quest_id}.phase[{$phase_index}].objective[{$obj_index}]";
          $errors = array_merge($errors, $this->validateObjectivePlayabilityNodeRecursive($objective, $obj_path));
        }
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate one objective and its children for clear actionable playability.
   *
   * @param array<string, mixed> $objective
   *   Objective payload.
   * @param string $path
   *   Objective path for error reporting.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateObjectivePlayabilityNodeRecursive(array $objective, string $path): array {
    $errors = [];
    $objective_type = strtolower(trim((string) ($objective['type'] ?? '')));
    $requires_actionability = in_array($objective_type, ['interact', 'collect', 'explore', 'escort', 'investigate', 'kill'], TRUE);
    if ($requires_actionability) {
      $next_step = trim((string) ($objective['next_step'] ?? ''));
      if ($next_step === '') {
        $errors[] = "[DCV_OBJ_PLAYABILITY_MISSING_NEXT_STEP] {$path}: next_step is required for objective type '{$objective_type}'.";
      }

      $target_ref = trim((string) ($objective['target'] ?? ''));
      $target_id_ref = trim((string) ($objective['target_id'] ?? ''));
      if ($target_id_ref !== '' && $target_ref === '') {
        $errors[] = "[DCV_OBJ_PLAYABILITY_NONSTANDARD_TARGET_FIELD] {$path}: use target (not target_id) for playability anchor contracts.";
      }
      if ($target_ref !== '' && $target_id_ref !== '' && $target_ref !== $target_id_ref) {
        $errors[] = "[DCV_OBJ_PLAYABILITY_TARGET_FIELD_CONFLICT] {$path}: target and target_id must match when both are provided.";
      }

      $location_ref = trim((string) ($objective['location_id'] ?? ''));
      $location_alias_ref = trim((string) ($objective['location'] ?? ''));
      $destination_id_ref = trim((string) ($objective['destination_id'] ?? ''));
      $destination_ref = trim((string) ($objective['destination'] ?? ''));
      if ($location_ref === '' && ($location_alias_ref !== '' || $destination_id_ref !== '' || $destination_ref !== '')) {
        $errors[] = "[DCV_OBJ_PLAYABILITY_NONSTANDARD_LOCATION_FIELD] {$path}: use location_id (not location/destination/destination_id) for playability anchor contracts.";
      }
      if ($target_ref === '' && $location_ref === '') {
        $errors[] = "[DCV_OBJ_PLAYABILITY_MISSING_ACTION_ANCHOR] {$path}: objective must define at least one target or location/destination anchor.";
      }
      if (
        $objective_type === 'explore'
        && $this->isGenericRuntimeLocationAnchor($location_ref)
        && $this->hasLiteralTravelInstructionWithoutLocationPlaceholder($next_step)
      ) {
        $errors[] = "[DCV_OBJ_PLAYABILITY_LOCATION_ANCHOR_NEXT_STEP_MISMATCH] {$path}: objective uses generic location_id '{location}' but next_step names a specific travel target; use an explicit canonical location_id/destination_id anchor.";
      }

      if ($this->objectiveNeedsNamedTargetGuidance($objective)) {
        $target_label = trim((string) ($objective['target_label'] ?? ''));
        if ($target_ref !== '' && $target_label === '') {
          $errors[] = "[DCV_OBJ_PLAYABILITY_MISSING_TARGET_LABEL] {$path}: target_label is required for meet/travel guidance objectives with canonical target refs.";
        }

        $target_aliases = array_values(array_filter(array_map('strval', is_array($objective['target_aliases'] ?? NULL) ? $objective['target_aliases'] : []), static fn(string $value): bool => trim($value) !== ''));
        if ($target_ref !== '' && $target_aliases === []) {
          $errors[] = "[DCV_OBJ_PLAYABILITY_MISSING_TARGET_ALIASES] {$path}: target_aliases must provide at least one name variant for player phrasing.";
        }

        if ($location_ref !== '' && trim((string) ($objective['wayfinding_hint'] ?? '')) === '') {
          $errors[] = "[DCV_OBJ_PLAYABILITY_MISSING_WAYFINDING_HINT] {$path}: wayfinding_hint is required for meet/travel guidance objectives with a location anchor.";
        }
      }
    }

    $children = $this->objectiveTypeService->extractNestedObjectiveDefinitions($objective);
    foreach ($children as $index => $child) {
      if (!is_array($child)) {
        $errors[] = "{$path}.children[{$index}]: objective child must be an object.";
        continue;
      }
      $errors = array_merge(
        $errors,
        $this->validateObjectivePlayabilityNodeRecursive($child, "{$path}.children[{$index}]")
      );
    }

    return array_values(array_unique($errors));
  }

  /**
   * Identify generic runtime location anchors that must not be paired with
   * explicit named travel instructions.
   */
  protected function isGenericRuntimeLocationAnchor(string $location_ref): bool {
    return strtolower(trim($location_ref)) === '{location}';
  }

  /**
   * Detect literal travel instructions that omit the {location} placeholder.
   */
  protected function hasLiteralTravelInstructionWithoutLocationPlaceholder(string $next_step): bool {
    $normalized = strtolower(trim($next_step));
    if ($normalized === '' || !str_contains($normalized, 'travel to')) {
      return FALSE;
    }
    return !str_contains($normalized, '{location}');
  }

  /**
   * Determine whether objective phrasing requires explicit named target guidance.
   *
   * @param array<string, mixed> $objective
   *   Objective payload.
   */
  protected function objectiveNeedsNamedTargetGuidance(array $objective): bool {
    $combined = strtolower(trim(implode(' ', array_filter([
      (string) ($objective['description'] ?? ''),
      (string) ($objective['next_step'] ?? ''),
    ]))));
    if ($combined === '') {
      return FALSE;
    }

    if (preg_match('/\b(?:reach|meet|follow|find|talk\s+to|speak\s+with|get\s+to|go\s+to|travel\s+to)\b/u', $combined)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Validate quest objective entity-linkage contracts for canonical definitions.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateEntityLinkageStage(array $storyline_data, string $payload_type): array {
    if ($payload_type !== 'definition') {
      return [];
    }

    $errors = [];
    $strict_actor_target_types = ['kill', 'escort'];
    $anchored_target_types = ['interact', 'investigate'];
    $anchors = $this->collectObjectiveReferenceAnchors($storyline_data);
    $canonical_index = $this->loadCanonicalLocationTemplateIndex();

    foreach ((array) ($canonical_index['errors'] ?? []) as $canonical_error) {
      $errors[] = (string) $canonical_error;
    }
    foreach (array_keys((array) ($canonical_index['room_ids'] ?? [])) as $room_id) {
      $anchors['location_ids'][(string) $room_id] = TRUE;
      $anchors['target_ids'][(string) $room_id] = TRUE;
    }
    foreach (array_keys((array) ($canonical_index['dungeon_ids'] ?? [])) as $dungeon_id) {
      $anchors['location_ids'][(string) $dungeon_id] = TRUE;
      $anchors['target_ids'][(string) $dungeon_id] = TRUE;
    }

    $quest_ids = $this->collectStorylineQuestIdsForObjectiveValidation($storyline_data);
    foreach ($quest_ids as $quest_id) {
      try {
        $phases = $this->loadQuestTemplateObjectivePhases($quest_id);
      }
      catch (\Throwable $exception) {
        $errors[] = "Entity linkage validation for quest template '{$quest_id}' failed: {$exception->getMessage()}";
        continue;
      }
      if ($phases === NULL || $phases === []) {
        continue;
      }

      foreach ($phases as $phase_index => $phase) {
        if (!is_array($phase)) {
          continue;
        }
        foreach ((array) ($phase['objectives'] ?? []) as $obj_index => $objective) {
          if (!is_array($objective)) {
            continue;
          }
          $obj_path = "quest.{$quest_id}.phase[{$phase_index}].objective[{$obj_index}]";
          $obj_type = strtolower(trim((string) ($objective['type'] ?? '')));
          foreach (['target', 'target_id'] as $field) {
            $target = trim((string) ($objective[$field] ?? ''));
            if ($target === '') {
              continue;
            }
            if (in_array($obj_type, $strict_actor_target_types, TRUE) && !isset($anchors['actor_ids'][$target])) {
              $errors[] = "{$obj_path}: {$field} '{$target}' not in actor registry";
            }
            elseif (in_array($obj_type, $anchored_target_types, TRUE) && !isset($anchors['target_ids'][$target])) {
              $errors[] = "{$obj_path}: {$field} '{$target}' not in anchored entity registry";
            }
          }
          foreach (['location', 'location_id', 'destination', 'destination_id'] as $field) {
            $ref = trim((string) ($objective[$field] ?? ''));
            if ($ref !== '' && !isset($anchors['location_ids'][$ref])) {
              $errors[] = "{$obj_path}: {$field} '{$ref}' not in entity registry";
            }
          }
          $item_ref = trim((string) ($objective['item'] ?? ''));
          if ($item_ref !== '' && !isset($anchors['item_ids'][$item_ref])) {
            $errors[] = "{$obj_path}: item '{$item_ref}' not in entity registry";
          }

          foreach ((array) ($objective['children'] ?? []) as $task_index => $task) {
            if (!is_array($task)) {
              continue;
            }
            $task_path = "{$obj_path}.children[{$task_index}]";
            $task_type = strtolower(trim((string) ($task['type'] ?? '')));
            foreach (['target', 'target_id'] as $field) {
              $task_target = trim((string) ($task[$field] ?? ''));
              if ($task_target === '') {
                continue;
              }
              if (in_array($task_type, $strict_actor_target_types, TRUE) && !isset($anchors['actor_ids'][$task_target])) {
                $errors[] = "{$task_path}: {$field} '{$task_target}' not in actor registry";
              }
              elseif (in_array($task_type, $anchored_target_types, TRUE) && !isset($anchors['target_ids'][$task_target])) {
                $errors[] = "{$task_path}: {$field} '{$task_target}' not in anchored entity registry";
              }
            }
            foreach (['location', 'location_id', 'destination', 'destination_id'] as $field) {
              $ref = trim((string) ($task[$field] ?? ''));
              if ($ref !== '' && !isset($anchors['location_ids'][$ref])) {
                $errors[] = "{$task_path}: {$field} '{$ref}' not in entity registry";
              }
            }
            $task_item = trim((string) ($task['item'] ?? ''));
            if ($task_item !== '' && !isset($anchors['item_ids'][$task_item])) {
              $errors[] = "{$task_path}: item '{$task_item}' not in entity registry";
            }
          }
        }
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate canonical dungeon-room coverage for referenced dungeon assets.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateDungeonRoomContractsStage(array $storyline_data, string $payload_type): array {
    if ($payload_type !== 'definition') {
      return [];
    }

    $errors = [];
    foreach ($this->extractReferencedDungeonIds($storyline_data) as $dungeon_id) {
      $dungeon_row = $this->loadCanonicalDungeonEntity($dungeon_id);
      if (!is_array($dungeon_row)) {
        $errors[] = "dungeon '{$dungeon_id}': canonical dungeon template row not found in dungeoncrawler_content_dungeons.";
        continue;
      }

      $dungeon_data = $this->decodeJsonArrayValue($dungeon_row['dungeon_data'] ?? NULL);
      if ($dungeon_data === []) {
        $errors[] = "dungeon '{$dungeon_id}': dungeon_data contract is required.";
        continue;
      }

      $entry_room = trim((string) ($dungeon_data['entry_room'] ?? ''));
      $room_ids = array_values(array_filter(array_map(
        'strval',
        is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : []
      ), static fn(string $id): bool => trim($id) !== ''));
      $room_ids = array_values(array_unique($room_ids));

      if ($room_ids === []) {
        $errors[] = "dungeon '{$dungeon_id}': dungeon_data.rooms must define at least one room.";
      }
      if ($entry_room === '') {
        $errors[] = "dungeon '{$dungeon_id}': dungeon_data.entry_room is required.";
      }
      elseif ($room_ids !== [] && !in_array($entry_room, $room_ids, TRUE)) {
        $errors[] = "dungeon '{$dungeon_id}': dungeon_data.entry_room '{$entry_room}' must be listed in dungeon_data.rooms.";
      }

      foreach ($room_ids as $room_id) {
        $room_row = $this->loadCanonicalRoomEntity($room_id);
        if (!is_array($room_row)) {
          $errors[] = "dungeon '{$dungeon_id}': room '{$room_id}' is missing from canonical room templates.";
          continue;
        }
        $layout_data = $this->decodeJsonArrayValue($room_row['layout_data'] ?? NULL);
        $contents_data = $this->decodeJsonArrayValue($room_row['contents_data'] ?? NULL);
        if ($layout_data === []) {
          $errors[] = "dungeon '{$dungeon_id}': room '{$room_id}' layout_data contract is required.";
        }
        if ($contents_data === []) {
          $errors[] = "dungeon '{$dungeon_id}': room '{$room_id}' contents_data contract is required.";
        }
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Extract referenced dungeon ids from storyline payload.
   *
   * @return array<int, string>
   *   Sorted unique dungeon ids.
   */
  protected function extractReferencedDungeonIds(array $storyline_data): array {
    $dungeon_ids = [];
    foreach ((array) ($storyline_data['asset_references'] ?? []) as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      if (strtolower(trim((string) ($reference['asset_type'] ?? ''))) !== 'dungeon') {
        continue;
      }
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_id !== '') {
        $dungeon_ids[$asset_id] = $asset_id;
      }
    }

    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL)
      ? $storyline_data['metadata']['generated_outline']
      : [];
    $entry_dungeon_id = trim((string) ($outline['entry_dungeon']['dungeon_id'] ?? ''));
    if ($entry_dungeon_id !== '') {
      $dungeon_ids[$entry_dungeon_id] = $entry_dungeon_id;
    }
    foreach ((array) ($outline['dungeons'] ?? []) as $dungeon) {
      if (!is_array($dungeon)) {
        continue;
      }
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id !== '') {
        $dungeon_ids[$dungeon_id] = $dungeon_id;
      }
    }

    $ids = array_values($dungeon_ids);
    sort($ids);
    return $ids;
  }

    /**
     * Dispatch per-entity-type contract validators.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateReferencedEntityByTypeStub(string $entity_type, string $entity_id, array $context): array {
      return match ($entity_type) {
        'npc', 'campaign_npc', 'character', 'character_group', 'creature'
          => $this->validateReferencedNpcLikeEntityContractStub($entity_type, $entity_id, $context),
        'hazard' => $this->validateReferencedHazardEntityContractStub($entity_type, $entity_id, $context),
        'item' => $this->validateReferencedItemEntityContractStub($entity_type, $entity_id, $context),
        'room', 'location' => $this->validateReferencedLocationEntityContractStub($entity_type, $entity_id, $context),
        'dungeon' => $this->validateReferencedDungeonEntityContractStub($entity_type, $entity_id, $context),
        'faction', 'institution'
          => $this->validateReferencedFactionEntityContractStub($entity_type, $entity_id, $context),
        default => [],
      };
    }

    /**
     * Return entity types currently covered by entity_type_contracts validators.
     *
     * @return array<int, string>
     *   Normalized supported entity types.
     */
    public function getSupportedEntityTypeContractTypes(): array {
      return [
        'npc',
        'campaign_npc',
        'character',
        'character_group',
        'creature',
        'hazard',
        'item',
        'room',
        'location',
        'dungeon',
        'faction',
        'institution',
      ];
    }

    /**
     * Validate npc-like entity contracts.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateReferencedNpcLikeEntityContractStub(string $entity_type, string $entity_id, array $context): array {
      $errors = [];
      $character_row = $this->loadCanonicalCharacterEntityByInstanceId($entity_id);
      $registry_types = $this->resolveNpcLikeRegistryTypes($entity_type);
      $registry_row = $this->loadCanonicalRegistryEntityByTypes($registry_types, $entity_id);

      if ($character_row === NULL && $registry_row === NULL) {
        $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'referenced NPC-like entity was not found in canonical character templates or registry.');
        return $errors;
      }

      if ($character_row !== NULL) {
        $state_data = $this->decodeJsonArrayValue($character_row['state_data'] ?? NULL);
        $name = trim((string) ($state_data['name'] ?? ''));
        $class = trim((string) ($state_data['class'] ?? ''));
        if ($name === '') {
          $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'character template state_data.name is required.');
        }
        if ($class === '') {
          $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'character template state_data.class is required.');
        }
        if (!is_numeric($state_data['level'] ?? NULL) || (int) ($state_data['level'] ?? 0) < 1) {
          $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'character template state_data.level must be >= 1.');
        }
      }

      if ($registry_row !== NULL) {
        $schema_data = $this->decodeJsonArrayValue($registry_row['schema_data'] ?? NULL);
        if ($schema_data === []) {
          $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'registry schema_data is required for npc contract validation.');
        }
        else {
          $registry_name = trim((string) ($schema_data['name'] ?? $registry_row['name'] ?? ''));
          if ($registry_name === '') {
            $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'registry NPC contract requires name.');
          }
          if ((string) ($registry_row['content_type'] ?? '') === 'creature') {
            $errors = array_merge($errors, $this->validateRegistryContractWithExistingValidator(
              'creature',
              $schema_data,
              $entity_type,
              $entity_id,
              $context
            ));
          }
        }
      }

      return $errors;
    }

    /**
     * Resolve canonical registry content types for npc-like entity validation.
     *
     * @return array<int, string>
     *   Ordered registry content types to probe.
     */
    protected function resolveNpcLikeRegistryTypes(string $entity_type): array {
      return match ($entity_type) {
        'creature' => ['creature', 'npc'],
        'character', 'character_group', 'campaign_npc', 'npc' => ['npc', 'creature'],
        default => ['npc', 'creature'],
      };
    }

    /**
     * Validate hazard entity contracts.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateReferencedHazardEntityContractStub(string $entity_type, string $entity_id, array $context): array {
      $row = $this->loadCanonicalRegistryEntity('hazard', $entity_id);
      if ($row === NULL) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'referenced hazard is missing from canonical registry.')];
      }

      $schema_data = $this->decodeJsonArrayValue($row['schema_data'] ?? NULL);
      if ($schema_data === []) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'hazard schema_data contract is required.')];
      }

      return $this->validateRegistryContractWithExistingValidator(
        'hazard',
        $schema_data,
        $entity_type,
        $entity_id,
        $context
      );
    }

    /**
     * Validate item entity contracts.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateReferencedItemEntityContractStub(string $entity_type, string $entity_id, array $context): array {
      $row = $this->loadCanonicalRegistryEntity('item', $entity_id);
      if ($row === NULL) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'referenced item is missing from canonical registry.')];
      }

      $schema_data = $this->decodeJsonArrayValue($row['schema_data'] ?? NULL);
      if ($schema_data === []) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'item schema_data contract is required.')];
      }

      $errors = [];
      $validation = $this->stateValidationService->validateItemDefinition($schema_data);
      if (empty($validation['valid'])) {
        foreach (array_values(array_filter(array_map('strval', (array) ($validation['errors'] ?? [])))) as $error) {
          $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, $error);
        }
      }

      $errors = array_merge($errors, $this->validateRegistryContractWithExistingValidator(
        'item',
        $schema_data,
        $entity_type,
        $entity_id,
        $context
      ));

      return $errors;
    }

    /**
     * Validate room/location entity contracts.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateReferencedLocationEntityContractStub(string $entity_type, string $entity_id, array $context): array {
      if ($entity_type === 'room') {
        $row = $this->loadCanonicalRoomEntity($entity_id);
        if ($row === NULL) {
          return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'referenced room is missing from canonical room templates.')];
        }
        $layout_data = $this->decodeJsonArrayValue($row['layout_data'] ?? NULL);
        $contents_data = $this->decodeJsonArrayValue($row['contents_data'] ?? NULL);
        $errors = [];
        if ($layout_data === []) {
          $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'room layout_data contract is required.');
        }
        if ($contents_data === []) {
          $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'room contents_data contract is required.');
        }
        return $errors;
      }

      $row = $this->loadCanonicalRegistryEntity('location', $entity_id);
      if ($row === NULL) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'referenced location is missing from canonical registry.')];
      }
      $schema_data = $this->decodeJsonArrayValue($row['schema_data'] ?? NULL);
      $name = trim((string) ($schema_data['name'] ?? $row['name'] ?? ''));
      if ($name === '') {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'location contract requires name.')];
      }
      return [];
    }

    /**
     * Validate dungeon entity contracts.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateReferencedDungeonEntityContractStub(string $entity_type, string $entity_id, array $context): array {
      $row = $this->loadCanonicalDungeonEntity($entity_id);
      if ($row === NULL) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'referenced dungeon is missing from canonical dungeon templates.')];
      }

      $dungeon_data = $this->decodeJsonArrayValue($row['dungeon_data'] ?? NULL);
      if ($dungeon_data === []) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'dungeon_data contract is required.')];
      }

      $rooms = array_values(array_filter(array_map('strval', is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : []), static fn(string $id): bool => trim($id) !== ''));
      $entry_room = trim((string) ($dungeon_data['entry_room'] ?? ''));
      $errors = [];
      if ($rooms === []) {
        $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'dungeon_data.rooms must define at least one room.');
      }
      if ($entry_room === '') {
        $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'dungeon_data.entry_room is required.');
      }
      elseif ($rooms !== [] && !in_array($entry_room, $rooms, TRUE)) {
        $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, "dungeon_data.entry_room '{$entry_room}' must be listed in dungeon_data.rooms.");
      }

      $room_contract_refs = [];
      if ($entry_room !== '') {
        $room_contract_refs[] = [
          'room_id' => $entry_room,
          'path' => 'dungeon_data.entry_room',
        ];
      }
      foreach ($rooms as $index => $room_id) {
        $room_contract_refs[] = [
          'room_id' => $room_id,
          'path' => "dungeon_data.rooms[{$index}]",
        ];
      }

      $validated_room_ids = [];
      $base_path = trim((string) ($context['path'] ?? 'entity_reference'));
      foreach ($room_contract_refs as $room_ref) {
        $room_id = trim((string) ($room_ref['room_id'] ?? ''));
        if ($room_id === '' || isset($validated_room_ids[$room_id])) {
          continue;
        }
        $validated_room_ids[$room_id] = TRUE;

        $room_context = $context;
        $room_path = trim((string) ($room_ref['path'] ?? ''));
        $room_context['path'] = $base_path !== '' && $room_path !== ''
          ? "{$base_path}.{$room_path}"
          : ($room_path !== '' ? $room_path : $base_path);

        $errors = array_merge(
          $errors,
          $this->validateReferencedLocationEntityContractStub('room', $room_id, $room_context)
        );
      }

      return $errors;
    }

    /**
     * Validate faction/institution entity contracts.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateReferencedFactionEntityContractStub(string $entity_type, string $entity_id, array $context): array {
      $registry_type = $entity_type === 'institution' ? 'institution' : 'faction';
      $row = $this->loadCanonicalRegistryEntity($registry_type, $entity_id);
      if ($row === NULL && $registry_type === 'institution') {
        $row = $this->loadCanonicalRegistryEntity('faction', $entity_id);
      }
      if ($row === NULL) {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'referenced faction/institution is missing from canonical registry.')];
      }

      $schema_data = $this->decodeJsonArrayValue($row['schema_data'] ?? NULL);
      $name = trim((string) ($schema_data['name'] ?? $row['name'] ?? ''));
      if ($name === '') {
        return [$this->formatEntityTypeContractError($entity_type, $entity_id, $context, 'faction/institution contract requires name.')];
      }
      return [];
    }

    /**
     * Load one canonical registry entity row by content type/id.
     */
    protected function loadCanonicalRegistryEntity(string $content_type, string $content_id): ?array {
      $schema = $this->database->schema();
      if (!$schema->tableExists('dungeoncrawler_content_registry')) {
        return NULL;
      }

      $row = $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['content_id', 'content_type', 'name', 'schema_data'])
        ->condition('content_type', $content_type)
        ->condition('content_id', $content_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      return is_array($row) ? $row : NULL;
    }

    /**
     * Load the first matching canonical registry entity row by candidate types.
     *
     * @param array<int, string> $content_types
     *   Candidate content types in probe order.
     */
    protected function loadCanonicalRegistryEntityByTypes(array $content_types, string $content_id): ?array {
      foreach ($content_types as $content_type) {
        $content_type = trim((string) $content_type);
        if ($content_type === '') {
          continue;
        }
        $row = $this->loadCanonicalRegistryEntity($content_type, $content_id);
        if ($row !== NULL) {
          return $row;
        }
      }

      return NULL;
    }

    /**
     * Load one canonical character template row by instance id.
     */
    protected function loadCanonicalCharacterEntityByInstanceId(string $instance_id): ?array {
      $schema = $this->database->schema();
      if (!$schema->tableExists('dungeoncrawler_content_characters')) {
        return NULL;
      }

      $row = $this->database->select('dungeoncrawler_content_characters', 'c')
        ->fields('c', ['instance_id', 'type', 'role', 'state_data'])
        ->condition('instance_id', $instance_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      return is_array($row) ? $row : NULL;
    }

    /**
     * Load one canonical room template row by room id.
     */
    protected function loadCanonicalRoomEntity(string $room_id): ?array {
      $schema = $this->database->schema();
      if (!$schema->tableExists('dungeoncrawler_content_rooms')) {
        return NULL;
      }

      $row = $this->database->select('dungeoncrawler_content_rooms', 'r')
        ->fields('r', ['room_id', 'name', 'layout_data', 'contents_data'])
        ->condition('room_id', $room_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      return is_array($row) ? $row : NULL;
    }

    /**
     * Load one canonical dungeon template row by dungeon id.
     */
    protected function loadCanonicalDungeonEntity(string $dungeon_id): ?array {
      $schema = $this->database->schema();
      if (!$schema->tableExists('dungeoncrawler_content_dungeons')) {
        return NULL;
      }

      $row = $this->database->select('dungeoncrawler_content_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'name', 'dungeon_data'])
        ->condition('dungeon_id', $dungeon_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      return is_array($row) ? $row : NULL;
    }

    /**
     * Format a stable entity-type-contract validator error string.
     *
     * @param array<string, mixed> $context
     *   Validation context metadata.
     */
    protected function formatEntityTypeContractError(string $entity_type, string $entity_id, array $context, string $message): string {
      $path = trim((string) ($context['path'] ?? 'entity_reference'));
      return "[entity_type_contracts:{$entity_type}] {$path} ({$entity_id}): {$message}";
    }

    /**
     * Validate registry schema_data with existing content validators when available.
     *
     * @param array<string, mixed> $schema_data
     *   Canonical registry schema_data payload.
     * @param array<string, mixed> $context
     *   Validation context metadata.
     *
     * @return array<int, string>
     *   Validation errors.
     */
    protected function validateRegistryContractWithExistingValidator(
      string $content_type,
      array $schema_data,
      string $entity_type,
      string $entity_id,
      array $context
    ): array {
      if ($this->contentRegistry === NULL) {
          return [$this->formatEntityTypeContractError(
            $entity_type,
            $entity_id,
            $context,
            'ContentRegistry dependency is missing; cannot validate canonical registry contract.'
          )];
        }

      $validation = $this->contentRegistry->validateContent($content_type, $schema_data);
      if (!empty($validation['valid'])) {
        return [];
      }

      $errors = [];
      foreach (array_values(array_filter(array_map('strval', (array) ($validation['errors'] ?? [])))) as $error) {
        $errors[] = $this->formatEntityTypeContractError($entity_type, $entity_id, $context, $error);
      }
      return $errors;
    }

  /**
   * Ensures each bundled storyline template exists as a campaign storyline.
   */
  public function ensureBundledCampaignStorylines(int $campaign_id, array $options = []): array {
    $this->assertStorylineStorageReady();

    $templates = $this->listTemplates();
    if ($templates === []) {
      return [];
    }

    $existing = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s', ['template_id', 'storyline_id'])
      ->condition('campaign_id', $campaign_id)
      ->isNotNull('template_id')
      ->execute()
      ->fetchAllKeyed();

    $instances = [];
    $priority_base = (int) ($options['priority_base'] ?? 100);

    foreach (array_values($templates) as $index => $template) {
      $template_id = (string) ($template['template_id'] ?? '');
      if ($template_id === '') {
        continue;
      }

      $template_definition = is_array($template['template_data'] ?? NULL) ? $template['template_data'] : [];
      if ($template_definition === []) {
        $this->logger->warning('Skipping bundled storyline template {template_id}: empty template_data.', [
          'template_id' => $template_id,
        ]);
        continue;
      }
      $template_validation = $this->validateNormalizedStorylineDefinition($template_definition);
      if (empty($template_validation['valid'])) {
        $this->logger->warning('Skipping bundled storyline template {template_id}: validation failed ({error_count} errors).', [
          'template_id' => $template_id,
          'error_count' => count((array) ($template_validation['errors'] ?? [])),
        ]);
        continue;
      }

      try {
        if (!empty($existing[$template_id])) {
          $instance = $this->getCampaignStoryline($campaign_id, (string) $existing[$template_id], TRUE);
        }
        else {
          $instance_options = $options;
          $instance_options['status'] = (string) ($options['status'] ?? 'available');
          $instance_options['priority'] = (int) ($options['priority'] ?? ($priority_base - $index));
          $instance_options['realize_storyline_assets'] = FALSE;
          $instance = $this->instantiateStorylineTemplate($campaign_id, $template_id, $instance_options);
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Skipping bundled storyline template {template_id}: {message}', [
          'template_id' => $template_id,
          'message' => $e->getMessage(),
        ]);
        continue;
      }

      if (is_array($instance) && $instance !== []) {
        $instances[] = $instance;
      }
    }

    return $instances;
  }

  /**
   * Creates a campaign storyline instance from a stored template.
   */
  public function instantiateStorylineTemplate(int $campaign_id, string $template_id, array $options = []): array {
    $this->assertStorylineStorageReady();

    $template = $this->getTemplate($template_id);
    if ($template === NULL) {
      throw new \InvalidArgumentException('Storyline template not found', 404);
    }

    $definition = $template['template_data'] ?? [];
    if (!is_array($definition) || $definition === []) {
      throw new \InvalidArgumentException('Storyline template is invalid', 400);
    }

    return $this->createCampaignStoryline($campaign_id, $definition, $options + ['template_id' => $template_id]);
  }

  /**
   * Returns campaign storyline instances.
   */
  public function listCampaignStorylines(int $campaign_id, bool $refresh = FALSE): array {
    $this->assertStorylineStorageReady();

    $rows = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('is_primary', 'DESC')
      ->orderBy('priority', 'DESC')
      ->orderBy('created_at', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $storylines = [];
    foreach ($rows as $row) {
      $storyline_id = (string) ($row['storyline_id'] ?? '');
      $storylines[] = $refresh
        ? ($this->getCampaignStoryline($campaign_id, $storyline_id, TRUE) ?? $this->hydrateCampaignStorylineRow($row))
        : $this->hydrateCampaignStorylineRow($row);
    }

    return $storylines;
  }

  /**
   * Loads a single campaign storyline instance.
   */
  public function getCampaignStoryline(int $campaign_id, string $storyline_id, bool $refresh = FALSE): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    if ($refresh) {
      $row = $this->synchronizeStorylineProgress($row);
    }

    $hydrated = $this->hydrateCampaignStorylineRow($row);
    $hydrated['asset_links'] = $this->getCampaignStorylineAssetLinks($campaign_id, $storyline_id);
    return $hydrated;
  }

  /**
   * Returns storyline journal entries for a campaign instance.
   */
  public function getCampaignStorylineLog(int $campaign_id, string $storyline_id): array {
    $this->assertStorylineStorageReady();

    $rows = $this->database->select('dc_campaign_storyline_log', 'l')
      ->fields('l')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->orderBy('created_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function (array $row): array {
      $row['event_data'] = $this->decodeJsonColumn($row['event_data'] ?? NULL);
      return $row;
    }, $rows);
  }

  /**
   * Returns normalized asset links for a campaign storyline.
   */
  public function getCampaignStorylineAssetLinks(int $campaign_id, string $storyline_id): array {
    $this->assertStorylineStorageReady();

    $rows = $this->database->select('dc_campaign_storyline_links', 'l')
      ->fields('l')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->orderBy('source_scope', 'ASC')
      ->orderBy('asset_type', 'ASC')
      ->orderBy('asset_role', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function (array $row): array {
      $row['link_data'] = $this->decodeJsonColumn($row['link_data'] ?? NULL);
      return $row;
    }, $rows);
  }

  /**
   * Activates a campaign storyline and updates campaign pointers.
   */
  public function activateCampaignStoryline(int $campaign_id, string $storyline_id, bool $primary = FALSE): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $this->database->update('dc_campaign_storylines')
      ->fields([
        'status' => 'active',
        'is_primary' => $primary ? 1 : (int) ($row['is_primary'] ?? 0),
        'activated_at' => (int) ($row['activated_at'] ?? time()) ?: time(),
        'updated_at' => time(),
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $this->persistCampaignStorylinePointers($campaign_id, $storyline_id, $primary || !empty($row['is_primary']));
    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      'storyline_activated',
      ['is_primary' => $primary || !empty($row['is_primary'])],
      'Storyline activated.'
    );

    return $this->finalizePersistedCampaignStoryline($campaign_id, $storyline_id);
  }

  /**
   * Ensure active storyline rooms are initialized and reachable in campaign runtime.
   */
  protected function ensureActiveStorylineRoomsAvailableInCampaign(int $campaign_id, array $storyline): void {
    $status = strtolower(trim((string) ($storyline['status'] ?? '')));
    if ($status !== 'active') {
      return;
    }

    $storyline_id = trim((string) ($storyline['storyline_id'] ?? ''));
    if ($storyline_id === '') {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: campaign %d storyline_id is required.',
        $campaign_id
      ));
    }

    $room_ids = $this->collectActiveStorylineRoomIds($storyline);
    if ($room_ids === []) {
      return;
    }

    $runtime_context = $this->loadRuntimeCampaignContext($campaign_id);
    $runtime_dungeon_id = $runtime_context['runtime_dungeon_id'];
    $active_room_id = $runtime_context['runtime_active_room_id'];
    $connector_definition = $this->resolveConnectorDefinitionService();
    $has_active_room_bridge = FALSE;
    foreach ($room_ids as $storyline_room_id) {
      $canonical_connectors = $connector_definition->loadCanonicalConnectorsForRoom($storyline_room_id);
      foreach ($canonical_connectors as $connector_row) {
        if (!is_array($connector_row)) {
          continue;
        }
        $from_room_id = trim((string) ($connector_row['from_room_id'] ?? ''));
        $to_room_id = trim((string) ($connector_row['to_room_id'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
          continue;
        }
        if (
          ($from_room_id === $active_room_id && in_array($to_room_id, $room_ids, TRUE))
          || ($to_room_id === $active_room_id && in_array($from_room_id, $room_ids, TRUE))
        ) {
          $has_active_room_bridge = TRUE;
          break 2;
        }
      }
    }
    if (!$has_active_room_bridge) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: campaign %d storyline %s has no canonical connector bridge from active room %s to storyline rooms.',
        $campaign_id,
        $storyline_id,
        $active_room_id
      ));
    }

    foreach ($room_ids as $room_id) {
      $this->ensureCampaignRoomMaterializedFromCanonical($campaign_id, $room_id);
      if (!$this->campaignRoomExists($campaign_id, $room_id)) {
        throw new \RuntimeException(sprintf(
          'Active storyline availability contract violation: campaign %d storyline %s room %s failed campaign room materialization.',
          $campaign_id,
          $storyline_id,
          $room_id
        ));
      }
    }

    $bridged_connection_count = 0;
    foreach ($room_ids as $storyline_room_id) {
      $canonical_connectors = $connector_definition->loadCanonicalConnectorsForRoom($storyline_room_id);
      foreach ($canonical_connectors as $connector_row) {
        if (!is_array($connector_row)) {
          continue;
        }
        $from_room_id = trim((string) ($connector_row['from_room_id'] ?? ''));
        $to_room_id = trim((string) ($connector_row['to_room_id'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
          continue;
        }

        $from_is_storyline = in_array($from_room_id, $room_ids, TRUE);
        $to_is_storyline = in_array($to_room_id, $room_ids, TRUE);
        $bridges_active_room = ($from_room_id === $active_room_id || $to_room_id === $active_room_id);
        $internal_storyline = ($from_is_storyline && $to_is_storyline);
        if (!($bridges_active_room || $internal_storyline)) {
          continue;
        }
        if (!$this->campaignRoomExists($campaign_id, $from_room_id) || !$this->campaignRoomExists($campaign_id, $to_room_id)) {
          continue;
        }

        $connector_definition->saveCampaignConnector($campaign_id, [
          'dungeon_id' => $runtime_dungeon_id,
          'from_room_id' => $from_room_id,
          'to_room_id' => $to_room_id,
          'from_hex' => is_array($connector_row['from_hex'] ?? NULL) ? $connector_row['from_hex'] : NULL,
          'to_hex' => is_array($connector_row['to_hex'] ?? NULL) ? $connector_row['to_hex'] : NULL,
          'direction' => (string) ($connector_row['direction'] ?? 'bidirectional'),
          'kind' => (string) ($connector_row['kind'] ?? 'hallway'),
          'default_state' => (string) ($connector_row['state'] ?? $connector_row['default_state'] ?? 'open'),
          'state' => (string) ($connector_row['state'] ?? $connector_row['default_state'] ?? 'open'),
          'trap_data' => is_array($connector_row['trap_data'] ?? NULL) ? $connector_row['trap_data'] : NULL,
          'lock_data' => is_array($connector_row['lock_data'] ?? NULL) ? $connector_row['lock_data'] : NULL,
          'requirements_data' => is_array($connector_row['requirements_data'] ?? NULL) ? $connector_row['requirements_data'] : NULL,
          'description' => (string) ($connector_row['description'] ?? 'Storyline-activated connector'),
          'travel_cost' => (int) ($connector_row['travel_cost'] ?? 0),
          'is_discovered_default' => (int) ($connector_row['is_discovered_default'] ?? $connector_row['is_discovered'] ?? 1),
          'connection_id' => (string) ($connector_row['connection_id'] ?? ''),
        ]);
        if ($bridges_active_room) {
          $bridged_connection_count++;
        }
      }
    }

    if ($bridged_connection_count <= 0) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: campaign %d storyline %s has no connector bridge from active room %s to storyline rooms.',
        $campaign_id,
        $storyline_id,
        $active_room_id
      ));
    }
  }

  /**
   * Resolve runtime campaign context required for active storyline availability.
   *
   * @return array{runtime_dungeon_id:string,runtime_active_room_id:string}
   *   Runtime dungeon and active room identifiers.
   */
  protected function loadRuntimeCampaignContext(int $campaign_id): array {
    $campaign_row = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['campaign_data'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($campaign_row)) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: campaign %d not found.',
        $campaign_id
      ));
    }

    $campaign_data = json_decode((string) ($campaign_row['campaign_data'] ?? '{}'), TRUE);
    if (!is_array($campaign_data)) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: campaign %d campaign_data is invalid JSON.',
        $campaign_id
      ));
    }
    $init = is_array($campaign_data['init'] ?? NULL) ? $campaign_data['init'] : [];
    $context = is_array($init['context'] ?? NULL) ? $init['context'] : [];
    $runtime_dungeon_id = trim((string) (
      $init['runtime_dungeon_id']
      ?? $context['runtime_dungeon_id']
      ?? ''
    ));
    $runtime_active_room_id = trim((string) (
      $init['runtime_active_room_id']
      ?? $context['runtime_active_room_id']
      ?? ''
    ));

    if ($runtime_dungeon_id === '' || $runtime_active_room_id === '') {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: campaign %d requires runtime_dungeon_id and runtime_active_room_id before activating storyline room availability.',
        $campaign_id
      ));
    }

    if (!$this->campaignRoomExists($campaign_id, $runtime_active_room_id)) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: campaign %d active room %s is missing from dc_campaign_rooms.',
        $campaign_id,
        $runtime_active_room_id
      ));
    }

    return [
      'runtime_dungeon_id' => $runtime_dungeon_id,
      'runtime_active_room_id' => $runtime_active_room_id,
    ];
  }

  /**
   * Collect unique storyline room asset identifiers from persisted storyline payload.
   *
   * @return array<int, string>
   *   Unique room IDs.
   */
  protected function collectActiveStorylineRoomIds(array $storyline): array {
    $room_ids = [];
    $asset_links = is_array($storyline['asset_links'] ?? NULL) ? $storyline['asset_links'] : [];
    foreach ($asset_links as $link) {
      if (!is_array($link)) {
        continue;
      }
      if (strtolower(trim((string) ($link['asset_type'] ?? ''))) !== 'room') {
        continue;
      }
      $room_id = trim((string) ($link['asset_id'] ?? ''));
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }

    $storyline_data = is_array($storyline['storyline_data'] ?? NULL) ? $storyline['storyline_data'] : [];
    foreach ((array) ($storyline_data['asset_references'] ?? []) as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      if (strtolower(trim((string) ($reference['asset_type'] ?? ''))) !== 'room') {
        continue;
      }
      $room_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }

    return array_values(array_keys($room_ids));
  }

  /**
   * Check whether a campaign room row exists.
   */
  protected function campaignRoomExists(int $campaign_id, string $room_id): bool {
    $room_id = trim($room_id);
    if ($campaign_id <= 0 || $room_id === '') {
      return FALSE;
    }
    $found = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return is_string($found) && trim($found) !== '';
  }

  /**
   * Materialize one storyline room into campaign room storage from canonical room data.
   */
  protected function ensureCampaignRoomMaterializedFromCanonical(int $campaign_id, string $room_id): void {
    $room_id = trim($room_id);
    if ($campaign_id <= 0 || $room_id === '') {
      throw new \RuntimeException('Active storyline availability contract violation: campaign_id and room_id are required for room materialization.');
    }
    if ($this->campaignRoomExists($campaign_id, $room_id)) {
      return;
    }

    $canonical_room = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data', 'source_room_id'])
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($canonical_room)) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: canonical room %s is missing for campaign %d materialization.',
        $room_id,
        $campaign_id
      ));
    }

    $layout_data = json_decode((string) ($canonical_room['layout_data'] ?? '{}'), TRUE);
    if (!is_array($layout_data)) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: canonical room %s has invalid layout_data JSON.',
        $room_id
      ));
    }
    $hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: canonical room %s has no layout_data.hexes.',
        $room_id
      ));
    }
    $layout_data['entry_points'] = is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [];
    $layout_data['exit_points'] = is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [];
    $layout_data['exits'] = is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [];
    $layout_data['terrain'] = is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : [];
    $layout_data['lighting'] = is_array($layout_data['lighting'] ?? NULL) ? $layout_data['lighting'] : [];

    $environment_tags = json_decode((string) ($canonical_room['environment_tags'] ?? '[]'), TRUE);
    $contents_data = json_decode((string) ($canonical_room['contents_data'] ?? '{}'), TRUE);
    $this->resolveMapGeneratorService()->persistCanonicalCampaignRoom(
      $campaign_id,
      $room_id,
      (string) ($canonical_room['name'] ?? $room_id),
      (string) ($canonical_room['description'] ?? ''),
      $layout_data,
      is_array($contents_data) ? $contents_data : [],
      is_array($environment_tags) ? $environment_tags : [],
      trim((string) ($canonical_room['source_room_id'] ?? $room_id)) ?: $room_id
    );

    $fog_state = json_encode([
      'visibility' => 'initial',
      'discovered_hexes' => [],
      'runtime_room_items_seeded' => TRUE,
      'source' => 'storyline_activation',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($fog_state)) {
      throw new \RuntimeException(sprintf(
        'Active storyline availability contract violation: failed to encode fog state for campaign %d room %s.',
        $campaign_id,
        $room_id
      ));
    }

    $this->database->merge('dc_campaign_room_states')
      ->keys([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
      ])
      ->fields([
        'is_cleared' => 0,
        'fog_state' => $fog_state,
        'last_visited' => 0,
        'updated' => $now,
      ])
      ->execute();
  }

  /**
   * Resolve connector definition service lazily to avoid constructor-order drift.
   */
  protected function resolveConnectorDefinitionService(): ConnectorDefinitionService {
    if (\Drupal::hasService('dungeoncrawler_content.connector_definition_service')) {
      $candidate = \Drupal::service('dungeoncrawler_content.connector_definition_service');
      if ($candidate instanceof ConnectorDefinitionService) {
        return $candidate;
      }
    }
    throw new \RuntimeException('Active storyline availability contract violation: ConnectorDefinitionService is required.');
  }

  /**
   * Resolve map generator service for centralized campaign room persistence.
   */
  protected function resolveMapGeneratorService(): MapGeneratorService {
    if (\Drupal::hasService('dungeoncrawler_content.map_generator')) {
      $candidate = \Drupal::service('dungeoncrawler_content.map_generator');
      if ($candidate instanceof MapGeneratorService) {
        return $candidate;
      }
    }
    throw new \RuntimeException('Active storyline availability contract violation: MapGeneratorService is required for campaign room materialization.');
  }

  /**
   * Advances or edits campaign storyline runtime state.
   */
  public function advanceCampaignStoryline(int $campaign_id, string $storyline_id, array $changes): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $row = $this->synchronizeStorylineProgress($row);
    $storyline_data = $this->decodeJsonColumn($row['storyline_data'] ?? NULL);
    $variables = $this->decodeJsonColumn($row['variables'] ?? NULL);

    $current_chapter_id = (string) ($changes['chapter_id'] ?? ($row['current_chapter_id'] ?? ''));
    $current_scene_id = (string) ($changes['scene_id'] ?? ($row['current_scene_id'] ?? ''));
    $status = (string) ($changes['status'] ?? ($row['status'] ?? 'active'));
    $variables = array_replace($variables, is_array($changes['variables'] ?? NULL) ? $changes['variables'] : []);

    $storyline_data['current_chapter_id'] = $current_chapter_id;
    $storyline_data['current_scene_id'] = $current_scene_id;
    $storyline_data['variables'] = $variables;
    $storyline_data['unlocked_chapter_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_chapter_ids'] ?? [], $current_chapter_id);
    $storyline_data['unlocked_scene_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_scene_ids'] ?? [], $current_scene_id);

    $validation = $this->validateRuntimeStorylineContract($storyline_data);
    if (!($validation['valid'] ?? FALSE)) {
      throw new \InvalidArgumentException('Storyline runtime failed validation during advance: ' . implode('; ', $validation['errors'] ?? []), 400);
    }

    $fields = [
      'status' => $status,
      'current_chapter_id' => $current_chapter_id ?: NULL,
      'current_scene_id' => $current_scene_id ?: NULL,
      'storyline_data' => json_encode($storyline_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'updated_at' => time(),
    ];

    if ($status === 'completed' && empty($row['completed_at'])) {
      $fields['completed_at'] = time();
    }

    $this->database->update('dc_campaign_storylines')
      ->fields($fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      'storyline_advanced',
      [
        'chapter_id' => $current_chapter_id,
        'scene_id' => $current_scene_id,
        'status' => $status,
        'variables' => $changes['variables'] ?? [],
      ],
      isset($changes['narrative_text']) ? (string) $changes['narrative_text'] : 'Storyline advanced.'
    );

    if ($status === 'active' || !empty($changes['is_primary'])) {
      $this->persistCampaignStorylinePointers($campaign_id, $storyline_id, !empty($changes['is_primary']));
    }

    return $this->getCampaignStoryline($campaign_id, $storyline_id, FALSE);
  }

  /**
   * Synchronizes a storyline after a quest lifecycle event.
   */
  public function recordQuestStateChange(
    int $campaign_id,
    string $quest_id,
    string $event_type,
    ?int $character_id = NULL,
    array $event_data = []
  ): ?array {
    if (!$this->isRuntimeStorylineStorageReady()) {
      return NULL;
    }

    $quest = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', [
        'quest_id',
        'status',
        'storyline_id',
        'storyline_chapter_id',
        'storyline_scene_id',
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->execute()
      ->fetchAssoc();

    if (!$quest || empty($quest['storyline_id'])) {
      return NULL;
    }

    $storyline_id = (string) $quest['storyline_id'];
    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $storyline_data = $this->decodeJsonColumn($row['storyline_data'] ?? NULL);
    $linked_quests = $storyline_data['linked_quests'] ?? [];
    $linked_quests[(string) $quest_id] = array_filter([
      'quest_id' => (string) $quest_id,
      'chapter_id' => (string) ($quest['storyline_chapter_id'] ?? ''),
      'scene_id' => (string) ($quest['storyline_scene_id'] ?? ''),
      'status' => (string) ($quest['status'] ?? 'available'),
    ], static fn($value): bool => $value !== '');
    $storyline_data['linked_quests'] = $linked_quests;

    $row['storyline_data'] = json_encode($storyline_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $row = $this->synchronizeStorylineProgress($row);

    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      $event_type,
      $event_data + [
        'quest_id' => $quest_id,
        'quest_status' => (string) ($quest['status'] ?? ''),
        'character_id' => $character_id,
      ],
      'Quest state updated for storyline: ' . $quest_id
    );

    return $this->hydrateCampaignStorylineRow($row);
  }

  /**
   * Normalizes a storyline template definition.
   */
  protected function normalizeTemplateDefinition(array $definition): array {
    $name = trim((string) ($definition['name'] ?? $definition['title'] ?? 'Untitled Storyline'));
    $template_id = $this->sanitizeIdentifier((string) ($definition['template_id'] ?? $definition['storyline_id'] ?? $name));
    if ($template_id === '') {
      $template_id = 'storyline-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
    }

    $chapters = $this->normalizeChapterDefinitions($definition['chapters'] ?? []);
    $linked_quests = $this->buildLinkedQuestMap($chapters);
    $questline = $this->buildQuestlineDefinition($chapters, $linked_quests);
    $asset_references = $this->buildAssetReferenceMap($definition, $chapters, $linked_quests);
    $contacts = $this->normalizeContactDefinitions($definition['contacts'] ?? [], $asset_references);
    $this->backfillQuestGiverLocationAnchors($contacts, $asset_references, $chapters);
    $tags = array_values(array_filter(array_map('strval', is_array($definition['tags'] ?? NULL) ? $definition['tags'] : [])));
    $metadata = $this->normalizeStorylineMetadata($definition, $chapters, $contacts, $asset_references, $tags);

    return [
      'schema_version' => self::STORYLINE_DEFINITION_SCHEMA_VERSION,
      'template_id' => $template_id,
      'name' => $name,
      'synopsis' => trim((string) ($definition['synopsis'] ?? $definition['summary'] ?? $definition['description'] ?? '')),
      'level_range' => trim((string) ($definition['level_range'] ?? $definition['levelBand'] ?? '')),
      'source' => trim((string) ($definition['source'] ?? '')),
      'tags' => $tags,
      'storyline_type' => 'questline',
      'metadata' => $metadata,
      'chapters' => $chapters,
      'linked_quests' => $linked_quests,
      'questline' => $questline,
      'asset_references' => array_values($asset_references),
      'contacts' => $contacts,
    ];
  }

  /**
   * Normalize storyline metadata into the canonical library-backed contract.
   */
  protected function normalizeStorylineMetadata(
    array $definition,
    array $chapters,
    array $contacts,
    array $asset_references,
    array $tags
  ): array {
    $metadata = is_array($definition['metadata'] ?? NULL) ? $definition['metadata'] : [];
    $goal = trim((string) (
      $metadata['goal']
      ?? $definition['goal']
      ?? $definition['synopsis']
      ?? $definition['summary']
      ?? $definition['description']
      ?? $definition['name']
      ?? $definition['title']
      ?? ''
    ));
    if ($goal === '') {
      $goal = 'Advance the active storyline.';
    }

    $metadata['goal'] = $goal;
    $outline = is_array($metadata['generated_outline'] ?? NULL) ? $metadata['generated_outline'] : [];
    $generation_phase = $this->inferStorylineGenerationPhase($definition, $outline, $tags, $chapters);
    $outline['generation_phase'] = $generation_phase;
    $outline['goal'] = trim((string) ($outline['goal'] ?? $goal));
    $outline['entry_point'] = $this->normalizeStorylineEntryPoint($outline['entry_point'] ?? [], $contacts, $chapters, $asset_references, $definition);

    if ($generation_phase === 'bootstrap') {
      $first_chapter = is_array($chapters[0] ?? NULL) ? $chapters[0] : [];
      $first_scene = is_array($first_chapter['scenes'][0] ?? NULL) ? $first_chapter['scenes'][0] : [];
      $lead_location_id = $this->resolveStorylineLeadLocationId($definition, $asset_references);
      $speaker_contact = $this->resolvePrimaryQuestGiverContact($contacts);

      if (!is_array($outline['entry_dungeon'] ?? NULL) && $first_chapter !== []) {
        $chapter_name = trim((string) ($first_chapter['name'] ?? $first_chapter['chapter_id'] ?? 'First Lead'));
        $chapter_id = trim((string) ($first_chapter['chapter_id'] ?? 'first-lead'));
        $scene_id = trim((string) ($first_scene['scene_id'] ?? 'first-scene'));
        $entry_point_room_id = trim((string) ($outline['entry_point']['primary_location_id'] ?? ''));
        $style = trim((string) ($tags[0] ?? 'generated lead'));
        $outline['entry_dungeon'] = [
          'dungeon_id' => $chapter_id !== '' ? $chapter_id : 'generated-entry-dungeon',
          'name' => $chapter_name !== '' ? $chapter_name : 'First Lead',
          'style' => $style !== '' ? $style : 'generated lead',
          // Hard contract: entry_dungeon.entrance_room_id must be a room/location id, never a scene id.
          'entrance_room_id' => $entry_point_room_id !== ''
            ? $entry_point_room_id
            : ($scene_id !== '' ? $scene_id : 'generated-entry-room'),
          'lead_location_id' => $lead_location_id,
          'lead_location_hint' => 'Start at ' . str_replace('_', ' ', $lead_location_id) . ' and follow the first lead.',
        ];
      }

      if (!is_array($outline['bootstrap_handoff'] ?? NULL)) {
        $speaker_name = trim((string) ($speaker_contact['display_name'] ?? $definition['speaker_name'] ?? 'Questgiver'));
        $speaker_id = trim((string) ($speaker_contact['entity_id'] ?? $definition['speaker_npc_id'] ?? 'npc_tavern_keeper'));
        $lead_name = trim((string) (($outline['entry_dungeon']['name'] ?? $definition['name'] ?? 'the first lead')));
        $outline['bootstrap_handoff'] = [
          'speaker_npc_id' => $speaker_id !== '' ? $speaker_id : 'npc_tavern_keeper',
          'speaker_name' => $speaker_name !== '' ? $speaker_name : 'Questgiver',
          'lead_text' => ($speaker_name !== '' ? $speaker_name : 'The questgiver') . ' points the party toward ' . $lead_name . '.',
        ];
      }

      if (
        !is_array($outline['progression_connectors'] ?? NULL)
        && is_array($outline['entry_dungeon'] ?? NULL)
        && is_array($outline['bootstrap_handoff'] ?? NULL)
      ) {
        $outline['progression_connectors'] = $this->buildDefaultBootstrapProgressionConnectors($outline, $lead_location_id);
      }
    }

    $metadata['generated_outline'] = $outline;
    return $metadata;
  }

  /**
   * Build fallback bootstrap progression connectors for first-lead handoff flow.
   */
  protected function buildDefaultBootstrapProgressionConnectors(array $outline, string $lead_location_id): array {
    $target_dungeon_id = (string) ($outline['entry_dungeon']['dungeon_id'] ?? 'generated-entry-dungeon');
    $target_room_id = (string) ($outline['entry_dungeon']['entrance_room_id'] ?? 'generated-entry-room');
    $source_id = (string) ($outline['bootstrap_handoff']['speaker_npc_id'] ?? 'npc_tavern_keeper');
    $narrative = (string) ($outline['bootstrap_handoff']['lead_text'] ?? 'The questgiver points the party toward the first lead.');

    return [[
      'connector_id' => trim((string) ($target_dungeon_id . '-bootstrap-handoff')),
      'source_type' => 'npc',
      'source_id' => $source_id,
      'mechanism' => 'npc_direction',
      'from_location_id' => $lead_location_id,
      'target_dungeon_id' => $target_dungeon_id,
      'target_room_id' => $target_room_id,
      'narrative' => $narrative,
    ]];
  }

  /**
   * Infer the most likely generation phase for a storyline object.
   */
  protected function inferStorylineGenerationPhase(array $definition, array $outline, array $tags, array $chapters): string {
    if (!empty($outline['entry_dungeon']) || !empty($outline['bootstrap_handoff'])) {
      return 'bootstrap';
    }

    if (!empty($outline['dungeons']) || !empty($outline['big_boss']) || !empty($outline['sub_bosses'])) {
      return 'expanded';
    }

    $source = strtolower(trim((string) ($definition['source'] ?? '')));
    $normalized_tags = array_map(static fn(string $tag): string => strtolower(trim($tag)), $tags);
    if (str_contains($source, 'bootstrap') || in_array('bootstrap', $normalized_tags, TRUE)) {
      return 'bootstrap';
    }

    if (str_contains($source, 'generator') || in_array('generated', $normalized_tags, TRUE)) {
      return 'expanded';
    }

    return count($chapters) <= 1 ? 'bootstrap' : 'expanded';
  }

  /**
   * Resolve the lead location id used for bootstrap-style handoffs.
   */
  protected function resolveStorylineLeadLocationId(array $definition, array $asset_references): string {
    $candidate = trim((string) ($definition['lead_location_id'] ?? ''));
    if ($candidate !== '') {
      return $candidate;
    }

    foreach ($asset_references as $reference) {
      if (!is_array($reference) || (string) ($reference['asset_type'] ?? '') !== 'location') {
        continue;
      }
      $candidate = trim((string) ($reference['asset_id'] ?? ''));
      if ($candidate !== '') {
        return $candidate;
      }
    }

    return 'tavern_entrance';
  }

  /**
   * Resolve the primary quest-giver contact for bootstrap handoffs.
   */
  protected function resolvePrimaryQuestGiverContact(array $contacts): array {
    foreach ($contacts as $contact) {
      if (is_array($contact) && (string) ($contact['role'] ?? '') === 'quest_giver') {
        return $contact;
      }
    }

    return is_array($contacts[0] ?? NULL) ? $contacts[0] : [];
  }

  /**
   * Build an explicit storyline entry-point node for graph validation and UX.
   */
  protected function normalizeStorylineEntryPoint(mixed $entry_point, array $contacts, array $chapters, array $asset_references, array $definition): array {
    $entry_point = is_array($entry_point) ? $entry_point : [];

    $first_chapter = is_array($chapters[0] ?? NULL) ? $chapters[0] : [];
    $first_scene = is_array($first_chapter['scenes'][0] ?? NULL) ? $first_chapter['scenes'][0] : [];
    $first_chapter_id = trim((string) ($first_chapter['chapter_id'] ?? ''));
    $first_scene_id = trim((string) ($first_scene['scene_id'] ?? ''));

    $primary_contact = $this->resolvePrimaryQuestGiverContact($contacts);
    $primary_relationship = is_array($primary_contact['relationship_state'] ?? NULL) ? $primary_contact['relationship_state'] : [];

    $primary_quest_giver_id = trim((string) (
      $entry_point['primary_quest_giver_id']
      ?? $primary_contact['entity_id']
      ?? $definition['primary_quest_giver_id']
      ?? ''
    ));
    $primary_quest_giver_name = trim((string) (
      $entry_point['primary_quest_giver_name']
      ?? $primary_contact['display_name']
      ?? $definition['primary_quest_giver_name']
      ?? ''
    ));
    $primary_chapter_id = trim((string) (
      $entry_point['primary_chapter_id']
      ?? $primary_relationship['chapter_id']
      ?? $first_chapter_id
    ));
    $primary_scene_id = trim((string) (
      $entry_point['primary_scene_id']
      ?? $primary_relationship['scene_id']
      ?? $first_scene_id
    ));
    $primary_location_id = trim((string) (
      $entry_point['primary_location_id']
      ?? $definition['primary_location_id']
      ?? $primary_relationship['location_id']
      ?? ''
    ));
    if ($primary_location_id === '') {
      $primary_location_id = $this->resolveEntryPointPrimaryLocationId($asset_references, $primary_chapter_id, $primary_scene_id);
    }
    $primary_dungeon_id = trim((string) (
      $entry_point['primary_dungeon_id']
      ?? $definition['primary_dungeon_id']
      ?? ($definition['metadata']['generated_outline']['entry_dungeon']['dungeon_id'] ?? '')
    ));
    if ($primary_dungeon_id === '') {
      $primary_dungeon_id = $this->resolveEntryPointPrimaryDungeonId($asset_references, $primary_chapter_id, $primary_scene_id);
    }
    if ($primary_dungeon_id === '') {
      $primary_dungeon_id = $primary_chapter_id;
    }

    $broker_contact = $this->resolveBrokerContactForQuestGiver($contacts, $primary_quest_giver_id);
    $broker_id = trim((string) ($entry_point['broker_id'] ?? $broker_contact['entity_id'] ?? ''));
    $broker_name = trim((string) ($entry_point['broker_name'] ?? $broker_contact['display_name'] ?? ''));
    if ($primary_quest_giver_id !== '' && $broker_id === $primary_quest_giver_id) {
      $broker_id = '';
      $broker_name = '';
    }
    $introduction_path = strtolower(trim((string) ($entry_point['introduction_path'] ?? ($broker_id !== '' ? 'brokered' : 'direct'))));
    if (!in_array($introduction_path, ['direct', 'brokered'], TRUE)) {
      $introduction_path = $broker_id !== '' ? 'brokered' : 'direct';
    }

    $detail_summary = trim((string) (
      $entry_point['detail_summary']
      ?? $definition['entry_detail_summary']
      ?? ($primary_quest_giver_name !== ''
        ? "{$primary_quest_giver_name} briefs the party on the storyline details."
        : 'The primary quest giver briefs the party on the storyline details.')
    ));

    return [
      'primary_quest_giver_id' => $primary_quest_giver_id,
      'primary_quest_giver_name' => $primary_quest_giver_name,
      'primary_dungeon_id' => $primary_dungeon_id,
      'primary_chapter_id' => $primary_chapter_id,
      'primary_scene_id' => $primary_scene_id,
      'primary_location_id' => $primary_location_id,
      'broker_id' => $broker_id,
      'broker_name' => $broker_name,
      'introduction_path' => $introduction_path,
      'detail_summary' => $detail_summary,
    ];
  }

  /**
   * Resolve the best room/location asset id for the primary entry-point anchor.
   */
  protected function resolveEntryPointPrimaryLocationId(array $asset_references, string $chapter_id, string $scene_id): string {
    $chapter_id = trim($chapter_id);
    $scene_id = trim($scene_id);

    foreach ($asset_references as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if (!in_array($asset_type, ['room', 'location'], TRUE) || $asset_id === '') {
        continue;
      }
      if (
        ($chapter_id === '' || trim((string) ($reference['chapter_id'] ?? '')) === $chapter_id)
        && ($scene_id === '' || trim((string) ($reference['scene_id'] ?? '')) === $scene_id)
      ) {
        return $asset_id;
      }
    }

    foreach ($asset_references as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if (!in_array($asset_type, ['room', 'location'], TRUE) || $asset_id === '') {
        continue;
      }
      if ($chapter_id !== '' && trim((string) ($reference['chapter_id'] ?? '')) === $chapter_id) {
        return $asset_id;
      }
    }

    return '';
  }

  /**
   * Resolve the canonical dungeon id for the primary entry-point anchor.
   */
  protected function resolveEntryPointPrimaryDungeonId(array $asset_references, string $chapter_id, string $scene_id): string {
    $chapter_id = trim($chapter_id);
    $scene_id = trim($scene_id);

    foreach ($asset_references as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_type !== 'dungeon' || $asset_id === '') {
        continue;
      }
      if (
        ($chapter_id === '' || trim((string) ($reference['chapter_id'] ?? '')) === $chapter_id)
        && ($scene_id === '' || trim((string) ($reference['scene_id'] ?? '')) === $scene_id || trim((string) ($reference['scene_id'] ?? '')) === '')
      ) {
        return $asset_id;
      }
    }

    foreach ($asset_references as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_type !== 'dungeon' || $asset_id === '') {
        continue;
      }
      if ($chapter_id !== '' && trim((string) ($reference['chapter_id'] ?? '')) === $chapter_id) {
        return $asset_id;
      }
    }

    return '';
  }

  /**
   * Resolve the preferred broker contact for one primary quest giver.
   */
  protected function resolveBrokerContactForQuestGiver(array $contacts, string $primary_quest_giver_id): array {
    $primary_quest_giver_id = trim($primary_quest_giver_id);

    foreach ($contacts as $contact) {
      if (!is_array($contact) || (string) ($contact['role'] ?? '') !== 'broker') {
        continue;
      }
      if ($primary_quest_giver_id !== '' && $this->doesBrokerIntroduceContact($contact, $primary_quest_giver_id)) {
        return $contact;
      }
    }

    foreach ($contacts as $contact) {
      if (is_array($contact) && (string) ($contact['role'] ?? '') === 'broker') {
        return $contact;
      }
    }

    return [];
  }

  /**
   * Determine whether a broker contact explicitly introduces one entity id.
   */
  protected function doesBrokerIntroduceContact(array $broker_contact, string $target_entity_id): bool {
    $target_entity_id = trim($target_entity_id);
    if ($target_entity_id === '') {
      return FALSE;
    }

    foreach (array_values(array_filter(is_array($broker_contact['introduces_to'] ?? NULL) ? $broker_contact['introduces_to'] : [], 'is_array')) as $introduction) {
      if (trim((string) ($introduction['entity_id'] ?? '')) === $target_entity_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Synchronizes quest-derived storyline runtime state.
   */
  protected function synchronizeStorylineDataWithQuestStates(
    array $storyline_data,
    string $current_chapter_id,
    string $current_scene_id,
    array $quest_state_map
  ): array {
    $storyline_data['linked_quests'] = is_array($storyline_data['linked_quests'] ?? NULL) ? $storyline_data['linked_quests'] : [];
    foreach ($storyline_data['linked_quests'] as $quest_id => &$quest_link) {
      $quest_link['status'] = (string) ($quest_state_map[$quest_id] ?? ($quest_link['status'] ?? 'available'));
    }
    unset($quest_link);
    $storyline_data['questline'] = $this->synchronizeQuestlineRuntime(
      is_array($storyline_data['questline'] ?? NULL) ? $storyline_data['questline'] : [],
      $storyline_data['linked_quests'],
      $quest_state_map
    );

    $status = (string) ($storyline_data['status'] ?? 'available');
    $events = [];

    if ($current_chapter_id === '' && !empty($storyline_data['chapters'][0]['chapter_id'])) {
      $current_chapter_id = (string) $storyline_data['chapters'][0]['chapter_id'];
    }

    if ($current_chapter_id !== '' && $current_scene_id === '') {
      foreach ($storyline_data['chapters'] as $chapter) {
        if ((string) ($chapter['chapter_id'] ?? '') === $current_chapter_id) {
          $current_scene_id = (string) ($chapter['scenes'][0]['scene_id'] ?? '');
          break;
        }
      }
    }

    while ($current_chapter_id !== '') {
      $position_quest_ids = $this->getQuestIdsForPosition($storyline_data, $current_chapter_id, $current_scene_id);
      if ($position_quest_ids === []) {
        break;
      }

      $all_completed = TRUE;
      foreach ($position_quest_ids as $quest_id) {
        if (($quest_state_map[$quest_id] ?? '') !== 'completed') {
          $all_completed = FALSE;
          break;
        }
      }

      if (!$all_completed) {
        break;
      }

      $next = $this->deriveNextPosition($storyline_data, $current_chapter_id, $current_scene_id);
      if ($next === NULL) {
        $status = 'completed';
        $events[] = [
          'event_type' => 'storyline_completed',
          'narrative_text' => 'Storyline completed by linked quest progression.',
        ];
        break;
      }

      $current_chapter_id = (string) ($next['chapter_id'] ?? '');
      $current_scene_id = (string) ($next['scene_id'] ?? '');
      $storyline_data['unlocked_chapter_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_chapter_ids'] ?? [], $current_chapter_id);
      $storyline_data['unlocked_scene_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_scene_ids'] ?? [], $current_scene_id);
      $status = 'active';
      $events[] = [
        'event_type' => 'storyline_progressed',
        'narrative_text' => sprintf('Storyline advanced to %s / %s.', $current_chapter_id, $current_scene_id ?: 'chapter'),
      ];
    }

    $storyline_data['status'] = $status;
    $storyline_data['current_chapter_id'] = $current_chapter_id;
    $storyline_data['current_scene_id'] = $current_scene_id;

    $validation = $this->validateRuntimeStorylineContract($storyline_data);
    if (!($validation['valid'] ?? FALSE)) {
      throw new \InvalidArgumentException('Storyline runtime failed validation after quest sync: ' . implode('; ', $validation['errors'] ?? []), 400);
    }

    return [
      'storyline_data' => $storyline_data,
      'current_chapter_id' => $current_chapter_id,
      'current_scene_id' => $current_scene_id,
      'status' => $status,
      'events' => $events,
    ];
  }

  /**
   * Normalizes chapter payloads.
   */
  protected function normalizeChapterDefinitions(array $chapters): array {
    $normalized = [];
    foreach (array_values($chapters) as $chapter_index => $chapter) {
      if (!is_array($chapter)) {
        continue;
      }

      $chapter_name = trim((string) ($chapter['name'] ?? $chapter['title'] ?? ('Chapter ' . ($chapter_index + 1))));
      $chapter_id = $this->sanitizeIdentifier((string) ($chapter['chapter_id'] ?? $chapter['id'] ?? $chapter_name));
      if ($chapter_id === '') {
        $chapter_id = 'chapter-' . ($chapter_index + 1);
      }

      $scenes = $this->normalizeSceneDefinitions($chapter['scenes'] ?? [], $chapter_id);
      $normalized[] = [
        'chapter_id' => $chapter_id,
        'name' => $chapter_name,
        'summary' => trim((string) ($chapter['summary'] ?? $chapter['description'] ?? '')),
        'order' => $chapter_index,
        'quest_ids' => array_values(array_filter(array_map('strval', is_array($chapter['quest_ids'] ?? NULL) ? $chapter['quest_ids'] : []))),
        'asset_references' => is_array($chapter['asset_references'] ?? NULL) ? array_values($chapter['asset_references']) : [],
        'gates' => is_array($chapter['gates'] ?? NULL) ? $chapter['gates'] : [],
        'scenes' => $scenes,
      ];
    }

    return $normalized;
  }

  /**
   * Normalizes scene payloads.
   */
  protected function normalizeSceneDefinitions(array $scenes, string $chapter_id): array {
    $normalized = [];
    foreach (array_values($scenes) as $scene_index => $scene) {
      if (!is_array($scene)) {
        continue;
      }

      $scene_name = trim((string) ($scene['name'] ?? $scene['title'] ?? ('Scene ' . ($scene_index + 1))));
      $scene_id = $this->sanitizeIdentifier((string) ($scene['scene_id'] ?? $scene['id'] ?? $scene_name));
      if ($scene_id === '') {
        $scene_id = $chapter_id . '-scene-' . ($scene_index + 1);
      }

      $normalized[] = [
        'scene_id' => $scene_id,
        'name' => $scene_name,
        'summary' => trim((string) ($scene['summary'] ?? $scene['description'] ?? '')),
        'order' => $scene_index,
        'quest_ids' => array_values(array_filter(array_map('strval', is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : []))),
        'asset_references' => is_array($scene['asset_references'] ?? NULL) ? array_values($scene['asset_references']) : [],
        'gates' => is_array($scene['gates'] ?? NULL) ? $scene['gates'] : [],
      ];
    }

    return $normalized;
  }

  /**
   * Builds a quest linkage map from normalized chapters/scenes.
   */
  protected function buildLinkedQuestMap(array $chapters): array {
    $linked_quests = [];
    foreach ($chapters as $chapter) {
      $chapter_id = (string) ($chapter['chapter_id'] ?? '');
      foreach (($chapter['quest_ids'] ?? []) as $quest_id) {
        $linked_quests[(string) $quest_id] = [
          'quest_id' => (string) $quest_id,
          'chapter_id' => $chapter_id,
          'scene_id' => '',
          'status' => 'available',
        ];
      }

      foreach (($chapter['scenes'] ?? []) as $scene) {
        foreach (($scene['quest_ids'] ?? []) as $quest_id) {
          $linked_quests[(string) $quest_id] = [
            'quest_id' => (string) $quest_id,
            'chapter_id' => $chapter_id,
            'scene_id' => (string) ($scene['scene_id'] ?? ''),
            'status' => 'available',
          ];
        }
      }
    }

    return $linked_quests;
  }

  /**
   * Builds an explicit questline graph from the ordered storyline quest list.
   */
  protected function buildQuestlineDefinition(array $chapters, array $linked_quests): array {
    $ordered_quest_ids = $this->extractOrderedQuestIds($chapters);
    $quest_nodes = [];

    foreach ($ordered_quest_ids as $index => $quest_id) {
      $previous = $ordered_quest_ids[$index - 1] ?? NULL;
      $next = $ordered_quest_ids[$index + 1] ?? NULL;
      $quest_link = $linked_quests[$quest_id] ?? [];
      $quest_nodes[] = [
        'quest_id' => $quest_id,
        'chapter_id' => (string) ($quest_link['chapter_id'] ?? ''),
        'scene_id' => (string) ($quest_link['scene_id'] ?? ''),
        'status' => (string) ($quest_link['status'] ?? 'available'),
        'unlocks_after' => $previous ? [$previous] : [],
        'unlocks_to' => $next ? [$next] : [],
        'unlock_condition' => $previous ? 'complete_previous_quest' : 'initially_available',
      ];
    }

    return [
      'primary_quest_id' => $ordered_quest_ids[0] ?? '',
      'ordered_quest_ids' => $ordered_quest_ids,
      'quest_nodes' => $quest_nodes,
    ];
  }

  /**
   * Extract the canonical ordered quest list for a storyline.
   *
   * Chapter-level quest ids come before scene quest ids within that chapter.
   */
  protected function extractOrderedQuestIds(array $chapters): array {
    $ordered = [];

    foreach ($chapters as $chapter) {
      foreach ((array) ($chapter['quest_ids'] ?? []) as $quest_id) {
        $quest_id = (string) $quest_id;
        if ($quest_id !== '' && !in_array($quest_id, $ordered, TRUE)) {
          $ordered[] = $quest_id;
        }
      }

      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        foreach ((array) ($scene['quest_ids'] ?? []) as $quest_id) {
          $quest_id = (string) $quest_id;
          if ($quest_id !== '' && !in_array($quest_id, $ordered, TRUE)) {
            $ordered[] = $quest_id;
          }
        }
      }
    }

    return $ordered;
  }

  /**
   * Builds a normalized asset-reference map from storyline, chapter, and scene declarations.
   */
  protected function buildAssetReferenceMap(array $definition, array $chapters, array $linked_quests): array {
    $references = [];

    foreach ($this->normalizeAssetReferences($definition['asset_references'] ?? [], '', '', 'storyline') as $reference) {
      $references[$this->buildAssetReferenceKey($reference)] = $reference;
    }

    foreach ($chapters as $chapter) {
      $chapter_id = (string) ($chapter['chapter_id'] ?? '');

      foreach ($this->normalizeAssetReferences($chapter['asset_references'] ?? [], $chapter_id, '', 'chapter') as $reference) {
        $references[$this->buildAssetReferenceKey($reference)] = $reference;
      }

      foreach (($chapter['scenes'] ?? []) as $scene) {
        $scene_id = (string) ($scene['scene_id'] ?? '');
        foreach ($this->normalizeAssetReferences($scene['asset_references'] ?? [], $chapter_id, $scene_id, 'scene') as $reference) {
          $references[$this->buildAssetReferenceKey($reference)] = $reference;
        }
      }
    }

    foreach ($linked_quests as $quest_link) {
      if (!is_array($quest_link) || empty($quest_link['quest_id'])) {
        continue;
      }

      $reference = [
        'asset_type' => 'quest',
        'asset_id' => (string) $quest_link['quest_id'],
        'asset_role' => 'story-quest',
        'chapter_id' => (string) ($quest_link['chapter_id'] ?? ''),
        'scene_id' => (string) ($quest_link['scene_id'] ?? ''),
        'source_scope' => 'derived',
        'notes' => '',
        'link_data' => [],
      ];
      $references[$this->buildAssetReferenceKey($reference)] = $reference;
    }

    return $references;
  }

  /**
   * Normalizes storyline contact payloads and guarantees a tavern broker path.
   */
  protected function normalizeContactDefinitions(array $contacts, array $asset_references): array {
    $normalized = [];

    foreach (array_values($contacts) as $contact) {
      if (!is_array($contact)) {
        continue;
      }

      $entity_type = $this->sanitizeIdentifier((string) ($contact['entity_type'] ?? $contact['contact_type'] ?? $contact['type'] ?? ''));
      if ($entity_type === 'npc_template') {
        $entity_type = 'campaign_npc';
      }
      $entity_id = trim((string) ($contact['entity_id'] ?? $contact['id'] ?? ''));
      $role = $this->sanitizeIdentifier((string) ($contact['role'] ?? $contact['contact_role'] ?? 'contact'));
      if ($entity_type === '' || $entity_id === '') {
        continue;
      }

      $contact_id = $this->sanitizeIdentifier((string) ($contact['contact_id'] ?? ($role . '-' . $entity_id)));
      $normalized[] = [
        'contact_id' => $contact_id !== '' ? $contact_id : ($role !== '' ? $role : 'contact'),
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'role' => $role !== '' ? $role : 'contact',
        'display_name' => trim((string) ($contact['display_name'] ?? $contact['name'] ?? '')),
        'attitude' => $this->normalizeAttitudeValue((string) ($contact['attitude'] ?? 'indifferent')),
        'availability' => trim((string) ($contact['availability'] ?? 'available')) ?: 'available',
        'notes' => trim((string) ($contact['notes'] ?? '')),
        'relationship_state' => is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [],
        'introduces_to' => $this->normalizeContactIntroductions($contact['introduces_to'] ?? []),
      ];
    }

    $quest_giver_contacts = [];
    foreach ($normalized as $contact) {
      if (($contact['role'] ?? '') === 'quest_giver') {
        $quest_giver_contacts[] = $contact;
      }
    }

    if ($quest_giver_contacts === []) {
      foreach ($asset_references as $reference) {
        if (!is_array($reference) || (string) ($reference['asset_role'] ?? '') !== 'quest-giver') {
          continue;
        }

        $asset_type = (string) ($reference['asset_type'] ?? '');
        $entity_type = $asset_type === 'npc' ? 'campaign_npc' : $asset_type;
        $entity_id = trim((string) ($reference['asset_id'] ?? ''));
        if ($entity_type === '' || $entity_id === '') {
          continue;
        }

        $quest_giver_contacts[] = [
          'contact_id' => 'quest-giver-' . $this->sanitizeIdentifier($entity_id),
          'entity_type' => $entity_type,
          'entity_id' => $entity_id,
          'role' => 'quest_giver',
          'display_name' => '',
          'attitude' => 'friendly',
          'availability' => 'available',
          'notes' => trim((string) ($reference['notes'] ?? '')),
          'relationship_state' => [],
          'introduces_to' => [],
        ];
        break;
      }

      $normalized = array_merge($normalized, $quest_giver_contacts);
    }

    $has_broker = FALSE;
    foreach ($normalized as $contact) {
      if (($contact['role'] ?? '') === 'broker') {
        $has_broker = TRUE;
        break;
      }
    }

    if (!$has_broker) {
      $introductions = [];
      foreach ($quest_giver_contacts as $contact) {
        $introductions[] = [
          'entity_type' => (string) ($contact['entity_type'] ?? ''),
          'entity_id' => (string) ($contact['entity_id'] ?? ''),
          'relationship_type' => 'knows',
          'attitude' => (string) ($contact['attitude'] ?? 'friendly'),
          'display_name' => (string) ($contact['display_name'] ?? ''),
          'notes' => 'Eldric can point the party toward this storyline contact.',
          'relationship_state' => ['seeded' => TRUE],
        ];
      }

      $normalized[] = [
        'contact_id' => 'eldric-broker',
        'entity_type' => 'campaign_npc',
        'entity_id' => 'npc_tavern_keeper',
        'role' => 'broker',
        'display_name' => 'Eldric',
        'attitude' => 'friendly',
        'availability' => 'available',
        'notes' => 'Eldric knows who to talk to about this lead and can make the introduction from the tavern.',
        'relationship_state' => ['seeded' => TRUE],
        'introduces_to' => $introductions,
      ];
    }

    $this->ensureBrokerIntroductions($normalized);

    return array_values($normalized);
  }

  /**
   * Ensure every broker explicitly introduces at least one quest-giver contact.
   */
  protected function ensureBrokerIntroductions(array &$contacts): void {
    $quest_givers = [];
    foreach ($contacts as $contact) {
      if (!is_array($contact) || (string) ($contact['role'] ?? '') !== 'quest_giver') {
        continue;
      }
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id === '') {
        continue;
      }
      $quest_givers[$entity_id] = [
        'entity_type' => (string) ($contact['entity_type'] ?? 'campaign_npc'),
        'entity_id' => $entity_id,
        'display_name' => trim((string) ($contact['display_name'] ?? '')),
        'attitude' => (string) ($contact['attitude'] ?? 'friendly'),
      ];
    }

    if ($quest_givers === []) {
      return;
    }

    foreach ($contacts as &$contact) {
      if (!is_array($contact) || (string) ($contact['role'] ?? '') !== 'broker') {
        continue;
      }

      $introductions = array_values(array_filter(is_array($contact['introduces_to'] ?? NULL) ? $contact['introduces_to'] : [], 'is_array'));
      $existing_keys = [];
      foreach ($introductions as $introduction) {
        $key = trim((string) ($introduction['entity_type'] ?? '')) . '|' . trim((string) ($introduction['entity_id'] ?? ''));
        if ($key !== '|') {
          $existing_keys[$key] = TRUE;
        }
      }

      foreach ($quest_givers as $quest_giver) {
        if ((string) ($contact['entity_id'] ?? '') === (string) ($quest_giver['entity_id'] ?? '')) {
          continue;
        }
        $introduction_key = trim((string) ($quest_giver['entity_type'] ?? '')) . '|' . (string) ($quest_giver['entity_id'] ?? '');
        if ($introduction_key === '|' || isset($existing_keys[$introduction_key])) {
          continue;
        }
        $introductions[] = [
          'entity_type' => (string) ($quest_giver['entity_type'] ?? 'campaign_npc'),
          'entity_id' => (string) ($quest_giver['entity_id'] ?? ''),
          'relationship_type' => 'knows',
          'attitude' => (string) ($quest_giver['attitude'] ?? 'friendly'),
          'display_name' => (string) ($quest_giver['display_name'] ?? ''),
          'notes' => 'Broker introduction to the primary quest-giver path.',
          'relationship_state' => ['derived' => TRUE],
        ];
      }

      $contact['introduces_to'] = $introductions;
    }
    unset($contact);
  }

  /**
   * Normalizes contact-introduction payloads.
   */
  protected function normalizeContactIntroductions(mixed $introductions): array {
    if (!is_array($introductions)) {
      return [];
    }

    $normalized = [];
    foreach (array_values($introductions) as $introduction) {
      if (!is_array($introduction)) {
        continue;
      }

      $entity_type = $this->sanitizeIdentifier((string) ($introduction['entity_type'] ?? $introduction['contact_type'] ?? $introduction['type'] ?? ''));
      if ($entity_type === 'npc_template') {
        $entity_type = 'campaign_npc';
      }
      $entity_id = trim((string) ($introduction['entity_id'] ?? $introduction['id'] ?? ''));
      if ($entity_type === '' || $entity_id === '') {
        continue;
      }

      $normalized[] = [
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'relationship_type' => $this->sanitizeIdentifier((string) ($introduction['relationship_type'] ?? 'knows')) ?: 'knows',
        'attitude' => $this->normalizeAttitudeValue((string) ($introduction['attitude'] ?? 'indifferent')),
        'display_name' => trim((string) ($introduction['display_name'] ?? $introduction['name'] ?? '')),
        'notes' => trim((string) ($introduction['notes'] ?? '')),
        'relationship_state' => is_array($introduction['relationship_state'] ?? NULL) ? $introduction['relationship_state'] : [],
      ];
    }

    return $normalized;
  }

  /**
   * Ensures quest-giver contacts carry an explicit opening scene anchor.
   */
  protected function backfillQuestGiverLocationAnchors(array &$contacts, array &$asset_references, array $chapters): void {
    $first_chapter = is_array($chapters[0] ?? NULL) ? $chapters[0] : [];
    $first_scene = is_array($first_chapter['scenes'][0] ?? NULL) ? $first_chapter['scenes'][0] : [];
    $chapter_id = trim((string) ($first_chapter['chapter_id'] ?? ''));
    $scene_id = trim((string) ($first_scene['scene_id'] ?? ''));
    if ($chapter_id === '' && $scene_id === '') {
      return;
    }

    foreach ($contacts as &$contact) {
      if (!is_array($contact) || (string) ($contact['role'] ?? '') !== 'quest_giver') {
        continue;
      }

      $contact['relationship_state'] = is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [];
      if ($chapter_id !== '' && empty($contact['relationship_state']['chapter_id'])) {
        $contact['relationship_state']['chapter_id'] = $chapter_id;
      }
      if ($scene_id !== '' && empty($contact['relationship_state']['scene_id'])) {
        $contact['relationship_state']['scene_id'] = $scene_id;
      }

      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id === '' || $chapter_id === '') {
        continue;
      }

      $has_anchor = FALSE;
      foreach ($asset_references as $reference) {
        if (!is_array($reference)) {
          continue;
        }
        if (
          (string) ($reference['asset_role'] ?? '') === 'quest-giver'
          && (string) ($reference['asset_id'] ?? '') === $entity_id
        ) {
          $has_anchor = TRUE;
          break;
        }
      }

      if (!$has_anchor) {
        $asset_references[] = [
          'asset_type' => 'npc',
          'asset_id' => $entity_id,
          'asset_role' => 'quest-giver',
          'chapter_id' => $chapter_id,
          'scene_id' => $scene_id,
          'source_scope' => 'derived',
          'notes' => 'Quest giver anchored to the opening storyline scene.',
          'link_data' => ['derived' => TRUE],
        ];
      }
    }
    unset($contact);
  }

  /**
   * Builds initial runtime state for a newly instantiated storyline.
   */
  protected function buildInitialStorylineState(array $normalized, array $options): array {
    $first_chapter = $normalized['chapters'][0] ?? [];
    $first_chapter_id = (string) ($first_chapter['chapter_id'] ?? '');
    $first_scene_id = (string) (($first_chapter['scenes'][0]['scene_id'] ?? ''));
    $variables = is_array($options['variables'] ?? NULL) ? $options['variables'] : [];

    $storyline_data = [
      'schema_version' => self::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => [
        'template_id' => (string) ($normalized['template_id'] ?? ''),
        'name' => (string) ($normalized['name'] ?? ''),
        'synopsis' => (string) ($normalized['synopsis'] ?? ''),
        'level_range' => (string) ($normalized['level_range'] ?? ''),
        'source' => (string) ($normalized['source'] ?? ''),
        'tags' => $normalized['tags'] ?? [],
        'goal' => (string) ($normalized['metadata']['goal'] ?? ''),
        'generated_outline' => is_array($normalized['metadata']['generated_outline'] ?? NULL)
          ? $normalized['metadata']['generated_outline']
          : [],
      ],
      'chapters' => $normalized['chapters'] ?? [],
      'linked_quests' => $normalized['linked_quests'] ?? [],
      'questline' => $normalized['questline'] ?? ['primary_quest_id' => '', 'ordered_quest_ids' => [], 'quest_nodes' => []],
      'asset_references' => $normalized['asset_references'] ?? [],
      'contacts' => $normalized['contacts'] ?? [],
      'unlocked_chapter_ids' => $first_chapter_id !== '' ? [$first_chapter_id] : [],
      'unlocked_scene_ids' => $first_scene_id !== '' ? [$first_scene_id] : [],
      'current_chapter_id' => $first_chapter_id,
      'current_scene_id' => $first_scene_id,
      'status' => !empty($options['activate']) ? 'active' : ((string) ($options['status'] ?? 'available')),
      'variables' => $variables,
    ];

    $validation = $this->validateRuntimeStorylineContract($storyline_data);
    if (!($validation['valid'] ?? FALSE)) {
      throw new \InvalidArgumentException('Storyline runtime failed validation: ' . implode('; ', $validation['errors'] ?? []), 400);
    }

    return [
      'current_chapter_id' => $first_chapter_id,
      'current_scene_id' => $first_scene_id,
      'variables' => $variables,
      'storyline_data' => $storyline_data,
    ];
  }

  /**
   * Keep questline runtime nodes aligned with quest-table statuses.
   */
  protected function synchronizeQuestlineRuntime(array $questline, array $linked_quests, array $quest_state_map): array {
    $nodes = is_array($questline['quest_nodes'] ?? NULL) ? $questline['quest_nodes'] : [];
    foreach ($nodes as &$node) {
      if (!is_array($node)) {
        continue;
      }
      $quest_id = (string) ($node['quest_id'] ?? '');
      $node['status'] = (string) ($quest_state_map[$quest_id] ?? ($linked_quests[$quest_id]['status'] ?? $node['status'] ?? 'available'));
    }
    unset($node);

    $questline['quest_nodes'] = $nodes;
    $questline['ordered_quest_ids'] = array_values(array_filter(array_map('strval', is_array($questline['ordered_quest_ids'] ?? NULL) ? $questline['ordered_quest_ids'] : [])));
    $questline['primary_quest_id'] = (string) ($questline['primary_quest_id'] ?? ($questline['ordered_quest_ids'][0] ?? ''));
    return $questline;
  }

  /**
   * Validate storyline runtime data when schema validation is wired.
   */
  protected function validateRuntimeStorylineData(array $storyline_data): array {
    return $this->stateValidationService->validateStorylineRuntime($storyline_data);
  }

  /**
   * Validate the schema stage for one storyline payload type.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateStorylineSchemaStage(array $storyline_data, string $payload_type): array {
    if ($payload_type === 'definition') {
      $validation = $this->stateValidationService->validateStorylineDefinition($storyline_data);
    }
    else {
      $validation = $this->stateValidationService->validateStorylineRuntime($storyline_data);
    }

    return !empty($validation['valid']) ? [] : array_values(array_filter(array_map('strval', (array) ($validation['errors'] ?? []))));
  }

  /**
   * Validate questline start-to-finish progression integrity.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateQuestlineProgressionFlow(array $storyline_data): array {
    $errors = [];
    $questline = is_array($storyline_data['questline'] ?? NULL) ? $storyline_data['questline'] : [];
    $nodes = array_values(array_filter(is_array($questline['quest_nodes'] ?? NULL) ? $questline['quest_nodes'] : [], 'is_array'));
    if ($nodes === []) {
      return $errors;
    }

    $primary_quest_id = trim((string) ($questline['primary_quest_id'] ?? ''));
    if ($primary_quest_id === '') {
      $errors[] = 'Questline progression is missing primary_quest_id.';
      return $errors;
    }

    $node_ids = [];
    $adjacency = [];
    $terminal_nodes = [];
    foreach ($nodes as $node) {
      $quest_id = trim((string) ($node['quest_id'] ?? ''));
      if ($quest_id === '') {
        $errors[] = 'Questline progression contains a quest node without quest_id.';
        continue;
      }
      if (isset($node_ids[$quest_id])) {
        $errors[] = "Questline progression duplicates quest node '{$quest_id}'.";
        continue;
      }

      $node_ids[$quest_id] = TRUE;
      $unlocks_to = array_values(array_filter(array_map('strval', is_array($node['unlocks_to'] ?? NULL) ? $node['unlocks_to'] : []), static fn(string $id): bool => trim($id) !== ''));
      $unlocks_after = array_values(array_filter(array_map('strval', is_array($node['unlocks_after'] ?? NULL) ? $node['unlocks_after'] : []), static fn(string $id): bool => trim($id) !== ''));
      $adjacency[$quest_id] = $unlocks_to;
      if ($unlocks_to === []) {
        $terminal_nodes[$quest_id] = TRUE;
      }

      foreach ($unlocks_after as $prerequisite_id) {
        if ($prerequisite_id === $quest_id) {
          $errors[] = "Questline node '{$quest_id}' cannot unlock after itself.";
        }
      }
    }

    if (!isset($node_ids[$primary_quest_id])) {
      $errors[] = "Questline primary_quest_id '{$primary_quest_id}' is not present in quest_nodes.";
      return $errors;
    }

    foreach ($adjacency as $quest_id => $targets) {
      foreach ($targets as $target_id) {
        if (!isset($node_ids[$target_id])) {
          $errors[] = "Questline node '{$quest_id}' unlocks unknown quest '{$target_id}'.";
        }
      }
    }

    $ordered_quest_ids = array_values(array_filter(array_map('strval', is_array($questline['ordered_quest_ids'] ?? NULL) ? $questline['ordered_quest_ids'] : []), static fn(string $id): bool => trim($id) !== ''));
    foreach ($ordered_quest_ids as $quest_id) {
      if (!isset($node_ids[$quest_id])) {
        $errors[] = "Questline ordered_quest_ids references unknown quest '{$quest_id}'.";
      }
    }

    $visited = [];
    $stack = [$primary_quest_id];
    while ($stack !== []) {
      $current = array_pop($stack);
      if (!is_string($current) || $current === '' || isset($visited[$current])) {
        continue;
      }
      $visited[$current] = TRUE;
      foreach ($adjacency[$current] ?? [] as $next) {
        if (!isset($visited[$next])) {
          $stack[] = $next;
        }
      }
    }

    foreach (array_keys($node_ids) as $quest_id) {
      if (!isset($visited[$quest_id])) {
        $errors[] = "Questline node '{$quest_id}' is unreachable from primary quest '{$primary_quest_id}'.";
      }
    }

    if ($terminal_nodes === []) {
      $errors[] = 'Questline progression has no terminal quest node; define at least one end-of-storyline quest.';
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate navigation handoff continuity from entry to connectors.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateNavigationProgressionFlow(array $storyline_data, string $payload_type): array {
    $errors = [];
    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL) ? $storyline_data['metadata']['generated_outline'] : [];
    if ($outline === []) {
      return $errors;
    }

    $entry_dungeon = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    if ($entry_dungeon !== []) {
      $entry_dungeon_id = trim((string) ($entry_dungeon['dungeon_id'] ?? ''));
      $entry_room_id = trim((string) ($entry_dungeon['entrance_room_id'] ?? ''));
      if ($entry_dungeon_id === '' || $entry_room_id === '') {
        $errors[] = 'Storyline entry_dungeon must define both dungeon_id and entrance_room_id.';
      }
    }

    foreach (array_values(array_filter(is_array($outline['progression_connectors'] ?? NULL) ? $outline['progression_connectors'] : [], 'is_array')) as $index => $connector) {
      $connector_path = "progression_connectors[{$index}]";
      $connector_id = trim((string) ($connector['connector_id'] ?? ''));
      if ($connector_id === '') {
        $errors[] = "{$connector_path}: connector_id is required.";
      }

      $source_type = trim((string) ($connector['source_type'] ?? ''));
      $source_id = trim((string) ($connector['source_id'] ?? ''));
      $target_dungeon_id = trim((string) ($connector['target_dungeon_id'] ?? ''));
      $target_room_id = trim((string) ($connector['target_room_id'] ?? ''));
      if ($source_type === '' || $source_id === '') {
        $errors[] = "{$connector_path}: source_type and source_id are required.";
      }
      if ($target_dungeon_id === '' || $target_room_id === '') {
        $errors[] = "{$connector_path}: target_dungeon_id and target_room_id are required.";
      }
    }

    if ($payload_type !== 'definition') {
      return array_values(array_unique($errors));
    }

    $canonical_index = $this->loadCanonicalLocationTemplateIndex();
    foreach ((array) ($canonical_index['errors'] ?? []) as $canonical_error) {
      $errors[] = '[DCV_NAV_INDEX_LOAD_ERROR] ' . (string) $canonical_error;
    }

    $entry_dungeon = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    $entry_dungeon_id = trim((string) ($entry_dungeon['dungeon_id'] ?? ''));
    $entry_room_id = trim((string) ($entry_dungeon['entrance_room_id'] ?? ''));
    if ($entry_dungeon_id !== '' && !isset($canonical_index['dungeon_ids'][$entry_dungeon_id])) {
      $errors[] = "[DCV_NAV_ENTRY_DUNGEON_MISSING] entry_dungeon.dungeon_id '{$entry_dungeon_id}' is not in canonical dungeon templates.";
    }
    if ($entry_room_id !== '' && !isset($canonical_index['room_ids'][$entry_room_id])) {
      $errors[] = "[DCV_NAV_ENTRY_ROOM_MISSING] entry_dungeon.entrance_room_id '{$entry_room_id}' is not in canonical room templates.";
    }
    if (
      $entry_dungeon_id !== ''
      && $entry_room_id !== ''
      && isset($canonical_index['dungeon_ids'][$entry_dungeon_id])
      && isset($canonical_index['room_ids'][$entry_room_id])
      && !isset($canonical_index['dungeon_room_ids'][$entry_dungeon_id][$entry_room_id])
    ) {
      $errors[] = "[DCV_NAV_ENTRY_ROOM_NOT_IN_DUNGEON] entry_dungeon.entrance_room_id '{$entry_room_id}' is not linked to dungeon '{$entry_dungeon_id}'.";
    }

    $chapter_ids = [];
    $scene_ids = [];
    foreach ((array) ($storyline_data['chapters'] ?? []) as $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id !== '') {
        $chapter_ids[$chapter_id] = TRUE;
      }
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        if (!is_array($scene)) {
          continue;
        }
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id !== '') {
          $scene_ids[$scene_id] = TRUE;
        }
      }
    }

    $actor_ids = [];
    foreach ((array) ($storyline_data['contacts'] ?? []) as $contact) {
      if (!is_array($contact)) {
        continue;
      }
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id !== '') {
        $actor_ids[$entity_id] = TRUE;
      }
      foreach ((array) ($contact['introduces_to'] ?? []) as $introduction) {
        if (!is_array($introduction)) {
          continue;
        }
        $intro_entity_id = trim((string) ($introduction['entity_id'] ?? ''));
        if ($intro_entity_id !== '') {
          $actor_ids[$intro_entity_id] = TRUE;
        }
      }
    }
    foreach ((array) ($storyline_data['asset_references'] ?? []) as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = strtolower(trim((string) ($reference['asset_type'] ?? '')));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if (
        $asset_id !== ''
        && in_array($asset_type, ['npc', 'campaign_npc', 'character', 'creature'], TRUE)
      ) {
        $actor_ids[$asset_id] = TRUE;
      }
    }
    foreach ((array) ($outline['sub_bosses'] ?? []) as $boss) {
      if (!is_array($boss)) {
        continue;
      }
      $boss_id = trim((string) ($boss['boss_id'] ?? ''));
      if ($boss_id !== '') {
        $actor_ids[$boss_id] = TRUE;
      }
    }
    $big_boss_id = trim((string) ($outline['big_boss']['boss_id'] ?? ''));
    if ($big_boss_id !== '') {
      $actor_ids[$big_boss_id] = TRUE;
    }

    foreach (array_values(array_filter(is_array($outline['progression_connectors'] ?? NULL) ? $outline['progression_connectors'] : [], 'is_array')) as $index => $connector) {
      $connector_path = "progression_connectors[{$index}]";
      $source_type = strtolower(trim((string) ($connector['source_type'] ?? '')));
      $source_id = trim((string) ($connector['source_id'] ?? ''));
      $target_dungeon_id = trim((string) ($connector['target_dungeon_id'] ?? ''));
      $target_room_id = trim((string) ($connector['target_room_id'] ?? ''));

      if ($source_id !== '') {
        $source_valid = match ($source_type) {
          'npc', 'campaign_npc', 'character', 'creature' => isset($actor_ids[$source_id]),
          'dungeon' => isset($canonical_index['dungeon_ids'][$source_id]),
          'room', 'location' => isset($canonical_index['room_ids'][$source_id]),
          'chapter' => isset($chapter_ids[$source_id]),
          'scene' => isset($scene_ids[$source_id]),
          default => FALSE,
        };
        if (!$source_valid) {
          $errors[] = "[DCV_NAV_CONNECTOR_SOURCE_INVALID] {$connector_path}: source_type/source_id '{$source_type}:{$source_id}' does not resolve to a canonical/anchored source.";
        }
      }

      if ($target_dungeon_id !== '' && !isset($canonical_index['dungeon_ids'][$target_dungeon_id])) {
        $errors[] = "[DCV_NAV_CONNECTOR_TARGET_DUNGEON_MISSING] {$connector_path}: target_dungeon_id '{$target_dungeon_id}' is not in canonical dungeon templates.";
      }
      if ($target_room_id !== '' && !isset($canonical_index['room_ids'][$target_room_id])) {
        $errors[] = "[DCV_NAV_CONNECTOR_TARGET_ROOM_MISSING] {$connector_path}: target_room_id '{$target_room_id}' is not in canonical room templates.";
      }
      if (
        $target_dungeon_id !== ''
        && $target_room_id !== ''
        && isset($canonical_index['dungeon_ids'][$target_dungeon_id])
        && isset($canonical_index['room_ids'][$target_room_id])
        && !isset($canonical_index['dungeon_room_ids'][$target_dungeon_id][$target_room_id])
      ) {
        $errors[] = "[DCV_NAV_CONNECTOR_TARGET_ROOM_NOT_IN_DUNGEON] {$connector_path}: target_room_id '{$target_room_id}' is not linked to target_dungeon_id '{$target_dungeon_id}'.";
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate quest objective control-chain integrity for definition/runtime payloads.
   *
   * @return array<int, string>
   *   Validation errors for this stage.
   */
  protected function validateObjectiveControlChainStage(array $storyline_data, string $payload_type): array {
    if ($this->objectiveTypeService === NULL) {
      return ['Canonical validation dependency missing: ObjectiveTypeService is required for objective_control_chain stage execution.'];
    }

    $errors = [];
    $quest_ids = $this->collectStorylineQuestIdsForObjectiveValidation($storyline_data);
    if ($quest_ids === []) {
      return [];
    }

    $anchors = $this->collectObjectiveReferenceAnchors($storyline_data);
    foreach ($quest_ids as $quest_id) {
      if ($payload_type === 'runtime') {
        try {
          $runtime_payload = $this->loadRuntimeQuestObjectivePayload($quest_id);
        }
        catch (\Throwable $exception) {
          $errors[] = "Runtime objective validation for quest '{$quest_id}' failed to load runtime payload: {$exception->getMessage()}";
          continue;
        }

        if ($runtime_payload !== NULL) {
          $generated = $runtime_payload['generated_objectives'];
          $runtime_anchors = $anchors;
          if ($generated !== []) {
            $runtime_anchors = $this->withObjectiveItemAnchors($runtime_anchors, $generated);
          }
          if ($generated === []) {
            $errors[] = "Runtime quest '{$quest_id}' is missing generated_objectives.";
          }
          else {
            $errors = array_merge($errors, $this->validateQuestObjectiveControlChain(
              $generated,
              $runtime_anchors,
              "runtime quest '{$quest_id}' generated_objectives"
            ));
          }

          $states = $runtime_payload['objective_states'] !== []
            ? $runtime_payload['objective_states']
            : $generated;
          if ($states !== []) {
            $runtime_anchors = $this->withObjectiveItemAnchors($runtime_anchors, $states);
            $errors = array_merge($errors, $this->validateQuestObjectiveControlChain(
              $states,
              $runtime_anchors,
              "runtime quest '{$quest_id}' objective_states"
            ));
            $errors = array_merge($errors, $this->validateRuntimeObjectiveStateAlignment(
              $generated,
              $states,
              $quest_id
            ));
          }

          continue;
        }
      }

      try {
        $template_objectives = $this->loadQuestTemplateObjectivePhases($quest_id);
      }
      catch (\Throwable $exception) {
        $errors[] = "Objective validation for quest template '{$quest_id}' failed: {$exception->getMessage()}";
        continue;
      }

      if ($template_objectives === NULL) {
        $errors[] = "Quest '{$quest_id}' is missing canonical quest template objectives for objective control-chain validation.";
        continue;
      }
      if ($template_objectives === []) {
        $errors[] = "Quest template '{$quest_id}' has an empty objectives_schema payload.";
        continue;
      }

      $errors = array_merge($errors, $this->validateQuestObjectiveControlChain(
        $template_objectives,
        $anchors,
        "quest template '{$quest_id}' objectives_schema"
      ));
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate one quest objective set for structure, references, and control-chain.
   *
   * @param array<int, mixed> $objective_phases
   *   Objective phases payload (definition/runtime shape).
   * @param array<string, array<string, bool>> $anchors
   *   Allowed storyline anchors by category.
   * @param string $path
   *   Human-readable context path for error messages.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateQuestObjectiveControlChain(array $objective_phases, array $anchors, string $path): array {
    if ($this->objectiveTypeService === NULL) {
      return ["{$path}: ObjectiveTypeService dependency is required for objective control-chain validation."];
    }

    $errors = $this->objectiveTypeService->validateObjectivePhases($objective_phases, $path);
    $graph = $this->buildQuestObjectiveControlGraph($objective_phases, $path);
    $errors = array_merge($errors, $graph['errors']);

    /** @var array<string, array<string, mixed>> $nodes */
    $nodes = $graph['nodes'];
    /** @var array<string, array<int, string>> $depends_on */
    $depends_on = $graph['depends_on'];
    /** @var array<string, array<int, string>> $unlocks_to */
    $unlocks_to = $graph['unlocks_to'];

    foreach ($nodes as $objective_id => $node) {
      $node_path = (string) ($node['path'] ?? $path);
      $objective = is_array($node['objective'] ?? NULL) ? $node['objective'] : [];

      $errors = array_merge($errors, $this->validateObjectiveCompletionCriteriaContract($objective, $node_path));
      $errors = array_merge($errors, $this->validateObjectiveHowTriggerContract(
        $objective,
        $node_path,
        !empty($node['requires_player_interaction'])
      ));

      if (!empty($node['target_ref']) && !isset($anchors['target_ids'][(string) $node['target_ref']])) {
        $errors[] = "{$node_path}: target '{$node['target_ref']}' is not anchored to storyline contacts/assets.";
      }
      if (!empty($node['item_ref']) && !isset($anchors['item_ids'][(string) $node['item_ref']])) {
        $errors[] = "{$node_path}: item '{$node['item_ref']}' is not anchored to storyline item assets.";
      }
      if (!empty($node['location_ref']) && !isset($anchors['location_ids'][(string) $node['location_ref']])) {
        $errors[] = "{$node_path}: location_id '{$node['location_ref']}' is not anchored to storyline rooms/scenes.";
      }
      if (!empty($node['destination_ref']) && !isset($anchors['location_ids'][(string) $node['destination_ref']])) {
        $errors[] = "{$node_path}: destination_id '{$node['destination_ref']}' is not anchored to storyline rooms/scenes.";
      }

      if (!empty($node['requires_player_interaction'])) {
        $unlocked_by = $depends_on[$objective_id] ?? [];
        $unlocks = $unlocks_to[$objective_id] ?? [];
        if ($unlocked_by === []) {
          $unlocked_by = ['quest_start'];
        }
        if ($unlocks === []) {
          $unlocks = ['quest_completion'];
        }
        if ($unlocked_by === []) {
          $errors[] = "{$node_path}: interaction objective is missing unlocked-by control-chain linkage.";
        }
        if ($unlocks === []) {
          $errors[] = "{$node_path}: interaction objective is missing unlocks-to control-chain linkage.";
        }
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Build objective dependency/unlock graph and validate dependency integrity.
   *
   * @param array<int, mixed> $objective_phases
   *   Objective phase payload.
   * @param string $path
   *   Human-readable context path for error messages.
   *
   * @return array{
   *   nodes: array<string, array<string, mixed>>,
   *   depends_on: array<string, array<int, string>>,
   *   unlocks_to: array<string, array<int, string>>,
   *   errors: array<int, string>
   * }
   *   Graph nodes/edges plus validation errors.
   */
  protected function buildQuestObjectiveControlGraph(array $objective_phases, string $path): array {
    $nodes = [];
    $errors = [];

    /** @var array<int, string> $ordered_ids */
    $ordered_ids = [];
    /** @var array<string, array<int, string>> $explicit_unlocks */
    $explicit_unlocks = [];

    foreach ($objective_phases as $phase_index => $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $phase_label = isset($phase['phase']) && is_numeric($phase['phase']) ? (int) $phase['phase'] : ($phase_index + 1);
      $phase_objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      $this->collectObjectiveGraphNodes(
        $phase_objectives,
        "phases[{$phase_index}].objectives",
        $phase_label,
        $nodes,
        $ordered_ids,
        $explicit_unlocks,
        $errors
      );
    }

    /** @var array<string, array<int, string>> $depends_on */
    $depends_on = [];
    foreach ($nodes as $objective_id => $node) {
      $depends_on[$objective_id] = is_array($node['depends_on'] ?? NULL) ? $node['depends_on'] : [];
    }

    foreach ($ordered_ids as $index => $objective_id) {
      if (!isset($depends_on[$objective_id])) {
        $depends_on[$objective_id] = [];
      }
      if ($depends_on[$objective_id] === [] && $index > 0) {
        $depends_on[$objective_id][] = $ordered_ids[$index - 1];
      }
    }

    foreach ($explicit_unlocks as $source_objective_id => $targets) {
      foreach ($targets as $target_objective_id) {
        if (!isset($nodes[$target_objective_id])) {
          $source_path = (string) ($nodes[$source_objective_id]['path'] ?? $path);
          $errors[] = "{$source_path}: unlock target '{$target_objective_id}' does not exist in the quest objective graph.";
          continue;
        }
        $depends_on[$target_objective_id][] = $source_objective_id;
      }
    }

    foreach ($depends_on as $objective_id => $dependencies) {
      $normalized_dependencies = [];
      $node_path = (string) ($nodes[$objective_id]['path'] ?? $path);
      foreach ($dependencies as $dependency_id) {
        $dependency_id = trim((string) $dependency_id);
        if ($dependency_id === '') {
          continue;
        }
        if ($dependency_id === $objective_id) {
          $errors[] = "{$node_path}: objective cannot depend on itself.";
          continue;
        }
        if (!isset($nodes[$dependency_id])) {
          $errors[] = "{$node_path}: depends_on references unknown objective '{$dependency_id}'.";
          continue;
        }
        $normalized_dependencies[] = $dependency_id;
      }
      $depends_on[$objective_id] = array_values(array_unique($normalized_dependencies));
    }

    $unlocks_to = [];
    foreach ($depends_on as $objective_id => $dependencies) {
      foreach ($dependencies as $dependency_id) {
        $unlocks_to[$dependency_id][] = $objective_id;
      }
    }
    foreach ($unlocks_to as $objective_id => $targets) {
      $unlocks_to[$objective_id] = array_values(array_unique(array_map('strval', $targets)));
    }

    if ($nodes !== []) {
      $errors = array_merge($errors, $this->validateObjectiveDependencyAcyclic($depends_on, $nodes, $path));
      $errors = array_merge($errors, $this->validateObjectiveDependencyReachability($depends_on, $unlocks_to, $nodes, $path));
    }

    return [
      'nodes' => $nodes,
      'depends_on' => $depends_on,
      'unlocks_to' => $unlocks_to,
      'errors' => array_values(array_unique($errors)),
    ];
  }

  /**
   * Collect objective nodes recursively into one flattened graph payload.
   *
   * @param array<int, mixed> $objectives
   *   Objective definitions.
   * @param string $path_prefix
   *   Path prefix for error reporting.
   * @param int $phase
   *   Objective phase number.
   * @param array<string, array<string, mixed>> $nodes
   *   Node map (mutated).
   * @param array<int, string> $ordered_ids
   *   Objective ids in deterministic order (mutated).
   * @param array<string, array<int, string>> $explicit_unlocks
   *   Explicit unlock edges keyed by source objective id (mutated).
   * @param array<int, string> $errors
   *   Validation errors (mutated).
   * @param string $parent_id
   *   Optional parent objective id for nested objective trees.
   */
  protected function collectObjectiveGraphNodes(
    array $objectives,
    string $path_prefix,
    int $phase,
    array &$nodes,
    array &$ordered_ids,
    array &$explicit_unlocks,
    array &$errors,
    string $parent_id = ''
  ): void {
    foreach ($objectives as $index => $objective) {
      if (!is_array($objective)) {
        continue;
      }

      $node_path = "{$path_prefix}[{$index}]";
      $objective_id = trim((string) ($objective['objective_id'] ?? $objective['id'] ?? ''));
      if ($objective_id === '') {
        $errors[] = "{$node_path}: missing objective_id.";
        continue;
      }
      if (isset($nodes[$objective_id])) {
        $errors[] = "{$node_path}: duplicate objective_id '{$objective_id}' in quest objective graph.";
        continue;
      }

      $dependency_ids = array_values(array_filter(array_map('strval', is_array($objective['depends_on'] ?? NULL) ? $objective['depends_on'] : []), static fn(string $id): bool => trim($id) !== ''));
      if ($parent_id !== '') {
        $dependency_ids[] = $parent_id;
      }
      $dependency_ids = array_values(array_unique($dependency_ids));

      $unlock_targets = array_values(array_filter(array_map('strval', is_array($objective['unlocks_to'] ?? ($objective['unlocks'] ?? NULL)) ? ($objective['unlocks_to'] ?? $objective['unlocks']) : []), static fn(string $id): bool => trim($id) !== ''));
      $type = strtolower(trim((string) ($objective['type'] ?? '')));
      $target_ref = trim((string) ($objective['target'] ?? ''));
      $item_ref = trim((string) ($objective['item'] ?? ''));
      $location_ref = trim((string) ($objective['location_id'] ?? ''));
      $destination_ref = trim((string) ($objective['destination_id'] ?? ''));
      $requires_player_interaction = $target_ref !== ''
        || $item_ref !== ''
        || $location_ref !== ''
        || $destination_ref !== ''
        || in_array($type, ['interact', 'collect', 'explore', 'escort', 'investigate', 'kill'], TRUE);

      $nodes[$objective_id] = [
        'objective' => $objective,
        'path' => $node_path,
        'phase' => $phase,
        'depends_on' => $dependency_ids,
        'target_ref' => $target_ref,
        'item_ref' => $item_ref,
        'location_ref' => $location_ref,
        'destination_ref' => $destination_ref,
        'requires_player_interaction' => $requires_player_interaction,
      ];
      $ordered_ids[] = $objective_id;

      if ($unlock_targets !== []) {
        $explicit_unlocks[$objective_id] = $unlock_targets;
      }

      $children = $this->objectiveTypeService?->extractNestedObjectiveDefinitions($objective) ?? [];
      if ($children !== []) {
        $this->collectObjectiveGraphNodes(
          $children,
          "{$node_path}.children",
          $phase,
          $nodes,
          $ordered_ids,
          $explicit_unlocks,
          $errors,
          $objective_id
        );
      }
    }
  }

  /**
   * Validate objective dependency graph has no cycles.
   *
   * @param array<string, array<int, string>> $depends_on
   *   Objective dependency graph.
   * @param array<string, array<string, mixed>> $nodes
   *   Objective node metadata.
   * @param string $path
   *   Human-readable context path.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateObjectiveDependencyAcyclic(array $depends_on, array $nodes, string $path): array {
    $errors = [];
    $visit_state = [];

    $visit = function (string $objective_id, array $trail = []) use (&$visit, &$visit_state, &$depends_on, &$errors, &$nodes, $path): void {
      $state = $visit_state[$objective_id] ?? 0;
      if ($state === 2) {
        return;
      }
      if ($state === 1) {
        $cycle = array_merge($trail, [$objective_id]);
        $node_path = (string) ($nodes[$objective_id]['path'] ?? $path);
        $errors[] = "{$node_path}: objective dependency cycle detected (" . implode(' -> ', $cycle) . ").";
        return;
      }

      $visit_state[$objective_id] = 1;
      $next_trail = array_merge($trail, [$objective_id]);
      foreach ($depends_on[$objective_id] ?? [] as $dependency_id) {
        if (!isset($depends_on[$dependency_id])) {
          continue;
        }
        $visit($dependency_id, $next_trail);
      }
      $visit_state[$objective_id] = 2;
    };

    foreach (array_keys($nodes) as $objective_id) {
      if (($visit_state[$objective_id] ?? 0) === 0) {
        $visit($objective_id);
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate objective graph has a connected chain from roots to each node.
   *
   * @param array<string, array<int, string>> $depends_on
   *   Objective dependency graph.
   * @param array<string, array<int, string>> $unlocks_to
   *   Objective unlock graph.
   * @param array<string, array<string, mixed>> $nodes
   *   Objective node metadata.
   * @param string $path
   *   Human-readable context path.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateObjectiveDependencyReachability(array $depends_on, array $unlocks_to, array $nodes, string $path): array {
    $roots = [];
    foreach ($depends_on as $objective_id => $dependencies) {
      if ($dependencies === []) {
        $roots[] = $objective_id;
      }
    }

    if ($roots === []) {
      return ["{$path}: objective dependency graph has no root objective (every objective is blocked)."];
    }

    $visited = [];
    $stack = array_values($roots);
    while ($stack !== []) {
      $current = array_pop($stack);
      if (!is_string($current) || $current === '' || isset($visited[$current])) {
        continue;
      }
      $visited[$current] = TRUE;
      foreach ($unlocks_to[$current] ?? [] as $target_id) {
        if (!isset($visited[$target_id])) {
          $stack[] = $target_id;
        }
      }
    }

    $errors = [];
    foreach (array_keys($nodes) as $objective_id) {
      if (!isset($visited[$objective_id])) {
        $node_path = (string) ($nodes[$objective_id]['path'] ?? $path);
        $errors[] = "{$node_path}: objective is disconnected from the control chain roots.";
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate completion_criteria contract fields for one objective.
   *
   * @param array<string, mixed> $objective
   *   Objective payload.
   * @param string $path
   *   Objective path for error reporting.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateObjectiveCompletionCriteriaContract(array $objective, string $path): array {
    $criteria = $objective['completion_criteria'] ?? NULL;
    if (!is_array($criteria)) {
      return ["{$path}: missing completion_criteria contract object."];
    }

    $errors = [];
    $kind = strtolower(trim((string) ($criteria['kind'] ?? '')));
    if (!in_array($kind, ['count', 'flag', 'all_children'], TRUE)) {
      $errors[] = "{$path}: completion_criteria.kind must be one of count, flag, all_children.";
    }

    $metric = trim((string) ($criteria['metric'] ?? ''));
    if ($metric === '') {
      $errors[] = "{$path}: completion_criteria.metric is required.";
    }

    $description = trim((string) ($criteria['description'] ?? ''));
    if ($description === '') {
      $errors[] = "{$path}: completion_criteria.description is required.";
    }

    if ($kind === 'count') {
      $target_count = $criteria['target_count'] ?? ($objective['target_count'] ?? NULL);
      if (!is_numeric($target_count) || (int) $target_count < 1) {
        $errors[] = "{$path}: count completion criteria requires target_count >= 1.";
      }
    }
    elseif (array_key_exists('required_value', $criteria) && !is_bool($criteria['required_value'])) {
      $errors[] = "{$path}: completion_criteria.required_value must be boolean when provided.";
    }

    return $errors;
  }

  /**
   * Validate objective HOW trigger contract for player-executed actions.
   *
   * @param array<string, mixed> $objective
   *   Objective payload.
   * @param string $path
   *   Objective path for error reporting.
   * @param bool $requires_player_interaction
   *   Whether this objective requires player interaction to progress.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateObjectiveHowTriggerContract(array $objective, string $path, bool $requires_player_interaction): array {
    if (!$requires_player_interaction) {
      return [];
    }

    $next_step = trim((string) ($objective['next_step'] ?? ''));
    if ($next_step !== '') {
      return [];
    }

    return ["{$path}: next_step HOW trigger is required for player-action objectives."];
  }

  /**
   * Validate runtime objective states align with generated objective node ids.
   *
   * @param array<int, mixed> $generated_objectives
   *   Generated objective phases.
   * @param array<int, mixed> $objective_states
   *   Runtime objective state phases.
   * @param string $quest_id
   *   Runtime quest id.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateRuntimeObjectiveStateAlignment(array $generated_objectives, array $objective_states, string $quest_id): array {
    $generated_ids = $this->collectObjectiveIdsFromPhases($generated_objectives);
    $state_ids = $this->collectObjectiveIdsFromPhases($objective_states);
    $errors = [];

    foreach (array_keys($generated_ids) as $objective_id) {
      if (!isset($state_ids[$objective_id])) {
        $errors[] = "Runtime quest '{$quest_id}' objective_states is missing objective '{$objective_id}' from generated_objectives.";
      }
    }
    foreach (array_keys($state_ids) as $objective_id) {
      if (!isset($generated_ids[$objective_id])) {
        $errors[] = "Runtime quest '{$quest_id}' objective_states contains unknown objective '{$objective_id}'.";
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Collect objective ids recursively from phased objective payloads.
   *
   * @param array<int, mixed> $phases
   *   Objective phases.
   *
   * @return array<string, bool>
   *   Objective id set.
   */
  protected function collectObjectiveIdsFromPhases(array $phases): array {
    $ids = [];
    foreach ($phases as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      foreach ((array) ($phase['objectives'] ?? []) as $objective) {
        if (!is_array($objective)) {
          continue;
        }
        $this->collectObjectiveIdsRecursively($objective, $ids);
      }
    }

    return $ids;
  }

  /**
   * Collect one objective id and nested child objective ids.
   *
   * @param array<string, mixed> $objective
   *   Objective payload.
   * @param array<string, bool> $ids
   *   Objective id set (mutated).
   */
  protected function collectObjectiveIdsRecursively(array $objective, array &$ids): void {
    $objective_id = trim((string) ($objective['objective_id'] ?? $objective['id'] ?? ''));
    if ($objective_id !== '') {
      $ids[$objective_id] = TRUE;
    }

    foreach ($this->objectiveTypeService?->extractNestedObjectiveDefinitions($objective) ?? [] as $child) {
      if (is_array($child)) {
        $this->collectObjectiveIdsRecursively($child, $ids);
      }
    }
  }

  /**
   * Load canonical objective phases for one quest template.
   *
   * Source-of-truth rule: dungeoncrawler_content_quest_templates in DB is
   * authoritative. File templates are not a runtime fallback path.
   *
   * @param string $template_id
   *   Quest template id.
   *
   * @return array<int, mixed>|null
   *   Objective phase payload or NULL when template is missing.
   */
  protected function loadQuestTemplateObjectivePhases(string $template_id): ?array {
    $template_id = trim($template_id);
    if ($template_id === '') {
      return NULL;
    }
    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_canonical_quests')) {
      throw new \RuntimeException(
        'Canonical quest table dc_canonical_quests is required as the system of record for objective validation. ' .
        'No file-based fallback is permitted. JSON template files are reference material for repairing DB state only.'
      );
    }
    if (!$schema->fieldExists('dc_canonical_quests', 'objectives_schema')) {
      throw new \RuntimeException(
        'Canonical quest table dc_canonical_quests is missing required objectives_schema column.'
      );
    }

    $row = $this->database->select('dc_canonical_quests', 't')
      ->fields('t', ['objectives_schema'])
      ->condition('template_id', $template_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }

    return $this->decodeJsonArrayValue($row['objectives_schema'] ?? NULL);
  }

  /**
   * Load runtime objective payload for one campaign quest id.
   *
   * @param string $quest_id
   *   Runtime quest id.
   *
   * @return array{
   *   source_template_id: string,
   *   generated_objectives: array<int, mixed>,
   *   objective_states: array<int, mixed>
   * }|null
   *   Runtime objective payload or NULL when quest row is not found.
   */
  protected function loadRuntimeQuestObjectivePayload(string $quest_id): ?array {
    $quest_id = trim($quest_id);
    if ($quest_id === '') {
      return NULL;
    }

    $schema = $this->database->schema();
    $has_generated_objectives = $schema->fieldExists('dc_campaign_quests', 'generated_objectives');
    $has_objective_states = $schema->fieldExists('dc_campaign_quests', 'objective_states');

    $quest_fields = ['source_template_id'];
    if ($has_generated_objectives) {
      $quest_fields[] = 'generated_objectives';
    }
    if ($has_objective_states) {
      $quest_fields[] = 'objective_states';
    }

    $row = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', $quest_fields)
      ->condition('quest_id', $quest_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }

    $generated_objectives = $has_generated_objectives
      ? $this->decodeJsonArrayValue($row['generated_objectives'] ?? NULL)
      : [];
    $objective_states = $has_objective_states
      ? $this->decodeJsonArrayValue($row['objective_states'] ?? NULL)
      : [];

    if ($objective_states === []) {
      $objective_states = $this->loadQuestProgressObjectiveStates($quest_id);
    }
    if ($objective_states === []) {
      $objective_states = $generated_objectives;
    }

    return [
      'source_template_id' => trim((string) ($row['source_template_id'] ?? '')),
      'generated_objectives' => $generated_objectives,
      'objective_states' => $objective_states,
    ];
  }

  /**
   * Load objective states from quest progress when quest rows do not store them.
   *
   * @return array<int, mixed>
   *   Objective states from progress storage.
   */
  protected function loadQuestProgressObjectiveStates(string $quest_id): array {
    $quest_id = trim($quest_id);
    if ($quest_id === '') {
      return [];
    }

    $schema = $this->database->schema();
    if (
      !$schema->tableExists('dc_quest_progress')
      || !$schema->fieldExists('dc_quest_progress', 'objective_states')
    ) {
      return [];
    }

    $row = $this->database->select('dc_quest_progress', 'qp')
      ->fields('qp', ['objective_states'])
      ->condition('quest_id', $quest_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      return [];
    }

    return $this->decodeJsonArrayValue($row['objective_states'] ?? NULL);
  }

  /**
   * Collect quest ids referenced by storyline graph structures.
   *
   * @param array<string, mixed> $storyline_data
   *   Storyline payload.
   *
   * @return array<int, string>
   *   Deduplicated quest id list.
   */
  protected function collectStorylineQuestIdsForObjectiveValidation(array $storyline_data): array {
    $quest_ids = [];
    foreach ((array) ($storyline_data['linked_quests'] ?? []) as $quest_key => $quest_link) {
      $quest_key = trim((string) $quest_key);
      if ($quest_key !== '') {
        $quest_ids[$quest_key] = TRUE;
      }
      if (is_array($quest_link)) {
        $quest_id = trim((string) ($quest_link['quest_id'] ?? ''));
        if ($quest_id !== '') {
          $quest_ids[$quest_id] = TRUE;
        }
      }
    }

    foreach (array_values(array_filter(array_map('strval', is_array($storyline_data['questline']['ordered_quest_ids'] ?? NULL) ? $storyline_data['questline']['ordered_quest_ids'] : []), static fn(string $id): bool => trim($id) !== '')) as $quest_id) {
      $quest_ids[$quest_id] = TRUE;
    }

    foreach (array_values(array_filter(is_array($storyline_data['questline']['quest_nodes'] ?? NULL) ? $storyline_data['questline']['quest_nodes'] : [], 'is_array')) as $quest_node) {
      $quest_id = trim((string) ($quest_node['quest_id'] ?? ''));
      if ($quest_id !== '') {
        $quest_ids[$quest_id] = TRUE;
      }
    }

    foreach (array_values(array_filter(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : [], 'is_array')) as $chapter) {
      foreach (array_values(array_filter(array_map('strval', is_array($chapter['quest_ids'] ?? NULL) ? $chapter['quest_ids'] : []), static fn(string $id): bool => trim($id) !== '')) as $quest_id) {
        $quest_ids[$quest_id] = TRUE;
      }
      foreach (array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array')) as $scene) {
        foreach (array_values(array_filter(array_map('strval', is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : []), static fn(string $id): bool => trim($id) !== '')) as $quest_id) {
          $quest_ids[$quest_id] = TRUE;
        }
      }
    }

    return array_values(array_keys($quest_ids));
  }

  /**
   * Collect objective target anchors from storyline payload references.
   *
   * @param array<string, mixed> $storyline_data
   *   Storyline payload.
   *
   * @return array<string, array<string, bool>>
   *   Anchor id sets by category.
   */
  protected function collectObjectiveReferenceAnchors(array $storyline_data): array {
    $chapter_ids = [];
    foreach (array_values(array_filter(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : [], 'is_array')) as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id !== '') {
        $chapter_ids[$chapter_id] = TRUE;
      }
    }

    $asset_ids = [];
    $item_ids = [];
    $scene_ids = [];
    foreach (array_values(array_filter(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : [], 'is_array')) as $chapter) {
      foreach (array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array')) as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id !== '') {
          $scene_ids[$scene_id] = TRUE;
        }
      }
    }
    $location_ids = $scene_ids;
    foreach (array_values(array_filter(is_array($storyline_data['asset_references'] ?? NULL) ? $storyline_data['asset_references'] : [], 'is_array')) as $reference) {
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_id === '') {
        continue;
      }
      $asset_ids[$asset_id] = TRUE;
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      if ($asset_type === 'item') {
        $item_ids[$asset_id] = TRUE;
      }
      if (in_array($asset_type, ['room', 'location'], TRUE)) {
        $location_ids[$asset_id] = TRUE;
      }
    }

    $contact_ids = [];
    foreach (array_values(array_filter(is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : [], 'is_array')) as $contact) {
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id !== '') {
        $contact_ids[$entity_id] = TRUE;
      }
      foreach (array_values(array_filter(is_array($contact['introduces_to'] ?? NULL) ? $contact['introduces_to'] : [], 'is_array')) as $introduction) {
        $intro_entity_id = trim((string) ($introduction['entity_id'] ?? ''));
        if ($intro_entity_id !== '') {
          $contact_ids[$intro_entity_id] = TRUE;
        }
      }
    }

    $outline_npc_ids = [];
    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL) ? $storyline_data['metadata']['generated_outline'] : [];
    $big_boss_id = trim((string) ($outline['big_boss']['boss_id'] ?? ''));
    if ($big_boss_id !== '') {
      $outline_npc_ids[$big_boss_id] = TRUE;
    }
    foreach (array_values(array_filter(is_array($outline['sub_bosses'] ?? NULL) ? $outline['sub_bosses'] : [], 'is_array')) as $boss) {
      $boss_id = trim((string) ($boss['boss_id'] ?? ''));
      if ($boss_id !== '') {
        $outline_npc_ids[$boss_id] = TRUE;
      }
    }
    foreach (array_values(array_filter(is_array($outline['dungeons'] ?? NULL) ? $outline['dungeons'] : [], 'is_array')) as $dungeon) {
      foreach (array_values(array_filter(is_array($dungeon['rooms'] ?? NULL) ? $dungeon['rooms'] : [], 'is_array')) as $room) {
        foreach (array_values(array_filter(array_map('strval', is_array($room['npc_ids'] ?? NULL) ? $room['npc_ids'] : []), static fn(string $id): bool => trim($id) !== '')) as $npc_id) {
          $outline_npc_ids[$npc_id] = TRUE;
        }
      }
    }

    $actor_ids = $contact_ids + $outline_npc_ids;
    foreach (array_values(array_filter(is_array($storyline_data['asset_references'] ?? NULL) ? $storyline_data['asset_references'] : [], 'is_array')) as $reference) {
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      $asset_type = strtolower(trim((string) ($reference['asset_type'] ?? '')));
      if ($asset_id !== '' && in_array($asset_type, ['npc', 'campaign_npc', 'character', 'creature'], TRUE)) {
        $actor_ids[$asset_id] = TRUE;
      }
    }

    $target_ids = $asset_ids + $contact_ids + $outline_npc_ids + $location_ids + $chapter_ids;

    return [
      'target_ids' => $target_ids,
      'actor_ids' => $actor_ids,
      'item_ids' => $item_ids,
      'location_ids' => $location_ids,
    ];
  }

  /**
   * Add item anchors derived from objective definitions.
   *
   * @param array<string, array<string, bool>> $anchors
   *   Anchor payload.
   * @param array<int, mixed> $objective_phases
   *   Objective phases.
   *
   * @return array<string, array<string, bool>>
   *   Anchor payload with objective-derived item ids.
   */
  protected function withObjectiveItemAnchors(array $anchors, array $objective_phases): array {
    if (!isset($anchors['item_ids']) || !is_array($anchors['item_ids'])) {
      $anchors['item_ids'] = [];
    }
    foreach ($this->collectObjectiveItemRefs($objective_phases) as $item_ref) {
      $anchors['item_ids'][$item_ref] = TRUE;
    }
    return $anchors;
  }

  /**
   * Collect item refs referenced by objective phases.
   *
   * @param array<int, mixed> $objective_phases
   *   Objective phases.
   *
   * @return array<int, string>
   *   Item refs.
   */
  protected function collectObjectiveItemRefs(array $objective_phases): array {
    $item_refs = [];
    foreach ($objective_phases as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      foreach ($this->collectObjectiveItemRefsFromNodes($objectives) as $item_ref) {
        $item_refs[$item_ref] = TRUE;
      }
    }
    return array_values(array_keys($item_refs));
  }

  /**
   * Collect item refs from nested objective nodes.
   *
   * @param array<int, mixed> $objectives
   *   Objective node list.
   *
   * @return array<int, string>
   *   Item refs.
   */
  protected function collectObjectiveItemRefsFromNodes(array $objectives): array {
    $item_refs = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      $item_ref = trim((string) ($objective['item_ref'] ?? $objective['item'] ?? ''));
      if ($item_ref !== '') {
        $item_refs[$item_ref] = TRUE;
      }
      $criteria_item_ref = trim((string) ($objective['completion_criteria']['item_ref'] ?? $objective['completion_criteria']['item'] ?? ''));
      if ($criteria_item_ref !== '') {
        $item_refs[$criteria_item_ref] = TRUE;
      }
      $children = is_array($objective['children'] ?? NULL) ? $objective['children'] : [];
      foreach ($this->collectObjectiveItemRefsFromNodes($children) as $child_item_ref) {
        $item_refs[$child_item_ref] = TRUE;
      }
    }
    return array_values(array_keys($item_refs));
  }

  /**
   * Validate cross-object storyline references that schema validation misses.
   *
   * @return array<int, string>
   *   Validation error messages.
   */
  protected function validateStorylineCrossReferences(array $definition): array {
    $errors = [];
    $storyline_template_id = trim((string) ($definition['template_id'] ?? ''));
    $chapters = array_values(array_filter(is_array($definition['chapters'] ?? NULL) ? $definition['chapters'] : [], 'is_array'));
    $chapter_ids = [];
    $scene_ids = [];
    $scene_chapter_map = [];
    foreach ($chapters as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id !== '') {
        $chapter_ids[$chapter_id] = TRUE;
      }
      foreach (array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array')) as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id !== '') {
          $scene_ids[$scene_id] = TRUE;
          if (!isset($scene_chapter_map[$scene_id])) {
            $scene_chapter_map[$scene_id] = $chapter_id;
          }

          $scene_quest_ids = array_values(array_filter(array_map('strval', is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : []), static fn(string $id): bool => trim($id) !== ''));
          if ($scene_quest_ids === []) {
            $errors[] = "Scene '{$scene_id}' is missing quest_ids progression gate.";
          }
        }
      }
    }

    $current_chapter_id = trim((string) ($definition['current_chapter_id'] ?? ''));
    $current_scene_id = trim((string) ($definition['current_scene_id'] ?? ''));
    if ($current_chapter_id !== '' && !isset($chapter_ids[$current_chapter_id])) {
      $errors[] = "Current chapter '{$current_chapter_id}' is not defined by the storyline.";
    }
    if ($current_scene_id !== '' && !isset($scene_ids[$current_scene_id])) {
      $errors[] = "Current scene '{$current_scene_id}' is not defined by the storyline.";
    }
    if (
      $current_chapter_id !== ''
      && $current_scene_id !== ''
      && isset($scene_chapter_map[$current_scene_id])
      && $scene_chapter_map[$current_scene_id] !== $current_chapter_id
    ) {
      $errors[] = "Current scene '{$current_scene_id}' does not belong to current chapter '{$current_chapter_id}'.";
    }

    $unlocked_chapter_ids = array_values(array_filter(array_map('strval', is_array($definition['unlocked_chapter_ids'] ?? NULL) ? $definition['unlocked_chapter_ids'] : []), static fn(string $id): bool => trim($id) !== ''));
    foreach ($unlocked_chapter_ids as $chapter_id) {
      if (!isset($chapter_ids[$chapter_id])) {
        $errors[] = "Unlocked chapter '{$chapter_id}' is not defined by the storyline.";
      }
    }
    if ($current_chapter_id !== '' && !in_array($current_chapter_id, $unlocked_chapter_ids, TRUE)) {
      $errors[] = "Current chapter '{$current_chapter_id}' must be present in unlocked_chapter_ids.";
    }

    $unlocked_scene_ids = array_values(array_filter(array_map('strval', is_array($definition['unlocked_scene_ids'] ?? NULL) ? $definition['unlocked_scene_ids'] : []), static fn(string $id): bool => trim($id) !== ''));
    foreach ($unlocked_scene_ids as $scene_id) {
      if (!isset($scene_ids[$scene_id])) {
        $errors[] = "Unlocked scene '{$scene_id}' is not defined by the storyline.";
      }
    }
    if ($current_scene_id !== '' && !in_array($current_scene_id, $unlocked_scene_ids, TRUE)) {
      $errors[] = "Current scene '{$current_scene_id}' must be present in unlocked_scene_ids.";
    }

    $contact_entity_ids = [];
    $contact_anchors = [];
    $contact_roles = [];
    $broker_introductions = [];
    foreach (array_values(array_filter(is_array($definition['contacts'] ?? NULL) ? $definition['contacts'] : [], 'is_array')) as $contact) {
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id !== '') {
        $contact_entity_ids[$entity_id] = TRUE;
        $role = trim((string) ($contact['role'] ?? ''));
        if ($role !== '') {
          $contact_roles[$entity_id][$role] = TRUE;
        }
      }

      $relationship_state = is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [];
      $relationship_chapter_id = trim((string) ($relationship_state['chapter_id'] ?? ''));
      $relationship_scene_id = trim((string) ($relationship_state['scene_id'] ?? ''));
      if ($relationship_chapter_id !== '' && !isset($chapter_ids[$relationship_chapter_id])) {
        $errors[] = "Contact '{$entity_id}' references unknown chapter '{$relationship_chapter_id}'.";
      }
      if ($relationship_scene_id !== '' && !isset($scene_ids[$relationship_scene_id])) {
        $errors[] = "Contact '{$entity_id}' references unknown scene '{$relationship_scene_id}'.";
      }
      if ($relationship_chapter_id !== '' || $relationship_scene_id !== '') {
        $contact_anchors[$entity_id] = TRUE;
      }

      if ((string) ($contact['role'] ?? '') === 'broker' && $entity_id !== '') {
        $broker_introductions[$entity_id] = [];
        foreach (array_values(array_filter(is_array($contact['introduces_to'] ?? NULL) ? $contact['introduces_to'] : [], 'is_array')) as $introduction) {
          $introduced_entity_id = trim((string) ($introduction['entity_id'] ?? ''));
          if ($introduced_entity_id !== '') {
            $broker_introductions[$entity_id][$introduced_entity_id] = TRUE;
          }
        }
      }
    }

    $declared_asset_npc_ids = [];
    $location_asset_anchors = [];
    $asset_references = array_values(array_filter(is_array($definition['asset_references'] ?? NULL) ? $definition['asset_references'] : [], 'is_array'));
    foreach ($asset_references as $reference) {
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_type === 'npc' && $asset_id !== '') {
        $declared_asset_npc_ids[$asset_id] = TRUE;
      }
      if (in_array($asset_type, ['room', 'location'], TRUE) && $asset_id !== '') {
        if (!isset($location_asset_anchors[$asset_id])) {
          $location_asset_anchors[$asset_id] = [
            'chapter_id' => trim((string) ($reference['chapter_id'] ?? '')),
            'scene_id' => trim((string) ($reference['scene_id'] ?? '')),
          ];
        }
      }

      $chapter_id = trim((string) ($reference['chapter_id'] ?? ''));
      $scene_id = trim((string) ($reference['scene_id'] ?? ''));
      if ($chapter_id !== '' && !isset($chapter_ids[$chapter_id])) {
        $errors[] = "Asset reference '{$reference['asset_id']}' points to unknown chapter '{$chapter_id}'.";
      }
      if ($scene_id !== '' && !isset($scene_ids[$scene_id])) {
        $errors[] = "Asset reference '{$reference['asset_id']}' points to unknown scene '{$scene_id}'.";
      }
      if ((string) ($reference['asset_role'] ?? '') === 'quest-giver') {
        if ($asset_id !== '') {
          $contact_anchors[$asset_id] = TRUE;
        }
      }
    }

    foreach (array_values(array_filter(is_array($definition['contacts'] ?? NULL) ? $definition['contacts'] : [], 'is_array')) as $contact) {
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if (
        (string) ($contact['role'] ?? '') === 'quest_giver'
        && $entity_id !== ''
        && !isset($contact_anchors[$entity_id])
      ) {
        $errors[] = "Quest giver '{$entity_id}' is missing a canonical chapter/scene anchor.";
      }
    }

    foreach ((array) ($definition['linked_quests'] ?? []) as $quest_id => $quest_link) {
      if (!is_array($quest_link)) {
        continue;
      }
      $chapter_id = trim((string) ($quest_link['chapter_id'] ?? ''));
      $scene_id = trim((string) ($quest_link['scene_id'] ?? ''));
      if ($chapter_id !== '' && !isset($chapter_ids[$chapter_id])) {
        $errors[] = "Linked quest '{$quest_id}' points to unknown chapter '{$chapter_id}'.";
      }
      if ($scene_id !== '' && !isset($scene_ids[$scene_id])) {
        $errors[] = "Linked quest '{$quest_id}' points to unknown scene '{$scene_id}'.";
      }
    }
    $errors = array_merge(
      $errors,
      $this->validateCanonicalLinkedQuestStorylineOwnership($storyline_template_id, $definition['linked_quests'] ?? [])
    );

    $questline = is_array($definition['questline'] ?? NULL) ? $definition['questline'] : [];
    foreach (array_values(array_filter(is_array($questline['quest_nodes'] ?? NULL) ? $questline['quest_nodes'] : [], 'is_array')) as $quest_node) {
      $quest_id = trim((string) ($quest_node['quest_id'] ?? ''));
      $chapter_id = trim((string) ($quest_node['chapter_id'] ?? ''));
      $scene_id = trim((string) ($quest_node['scene_id'] ?? ''));
      if ($chapter_id !== '' && !isset($chapter_ids[$chapter_id])) {
        $errors[] = "Quest node '{$quest_id}' points to unknown chapter '{$chapter_id}'.";
      }
      if ($scene_id !== '' && !isset($scene_ids[$scene_id])) {
        $errors[] = "Quest node '{$quest_id}' points to unknown scene '{$scene_id}'.";
      }
    }

    $outline = is_array($definition['metadata']['generated_outline'] ?? NULL) ? $definition['metadata']['generated_outline'] : [];
    $outline_location_index = $this->collectOutlineLocationAnchors($outline);
    $outline_declared_dungeons = $outline_location_index['dungeons'];
    $outline_declared_rooms = $outline_location_index['rooms'];

    $entry_point = is_array($outline['entry_point'] ?? NULL) ? $outline['entry_point'] : [];
    if ($entry_point === []) {
      $errors[] = 'Storyline entry_point is required under metadata.generated_outline and must declare a primary quest giver.';
    }
    else {
      $primary_quest_giver_id = trim((string) ($entry_point['primary_quest_giver_id'] ?? ''));
      $primary_quest_giver_name = trim((string) ($entry_point['primary_quest_giver_name'] ?? ''));
      $primary_dungeon_id = trim((string) ($entry_point['primary_dungeon_id'] ?? ''));
      $primary_chapter_id = trim((string) ($entry_point['primary_chapter_id'] ?? ''));
      $primary_scene_id = trim((string) ($entry_point['primary_scene_id'] ?? ''));
      $primary_location_id = trim((string) ($entry_point['primary_location_id'] ?? ''));
      $introduction_path = strtolower(trim((string) ($entry_point['introduction_path'] ?? '')));
      $broker_id = trim((string) ($entry_point['broker_id'] ?? ''));
      $detail_summary = trim((string) ($entry_point['detail_summary'] ?? ''));

      if ($primary_quest_giver_id === '') {
        $errors[] = 'Storyline entry_point.primary_quest_giver_id is required.';
      }
      if ($primary_quest_giver_name === '') {
        $errors[] = 'Storyline entry_point.primary_quest_giver_name is required.';
      }
      if ($primary_dungeon_id === '') {
        $errors[] = 'Storyline entry_point.primary_dungeon_id is required and must reference a storyline or canonical dungeon anchor.';
      }
      if ($primary_chapter_id === '') {
        $errors[] = 'Storyline entry_point.primary_chapter_id is required.';
      }
      if ($primary_scene_id === '') {
        $errors[] = 'Storyline entry_point.primary_scene_id is required.';
      }
      if ($primary_location_id === '') {
        $errors[] = 'Storyline entry_point.primary_location_id is required and must reference a storyline or canonical room/location anchor.';
      }
      if ($detail_summary === '') {
        $errors[] = 'Storyline entry_point.detail_summary is required.';
      }
      if (!in_array($introduction_path, ['direct', 'brokered'], TRUE)) {
        $errors[] = "Storyline entry_point.introduction_path must be 'direct' or 'brokered'.";
      }

      if ($primary_quest_giver_id !== '' && !isset($contact_entity_ids[$primary_quest_giver_id])) {
        $errors[] = "Storyline entry_point primary quest giver '{$primary_quest_giver_id}' is not declared as a contact.";
      }
      if (
        $primary_quest_giver_id !== ''
        && !isset($contact_roles[$primary_quest_giver_id]['quest_giver'])
      ) {
        $errors[] = "Storyline entry_point primary quest giver '{$primary_quest_giver_id}' must use role quest_giver.";
      }
      if ($primary_chapter_id !== '' && !isset($chapter_ids[$primary_chapter_id])) {
        $errors[] = "Storyline entry_point chapter '{$primary_chapter_id}' is not defined by the storyline.";
      }
      if ($primary_scene_id !== '' && !isset($scene_ids[$primary_scene_id])) {
        $errors[] = "Storyline entry_point scene '{$primary_scene_id}' is not defined by the storyline.";
      }
      if (
        $primary_chapter_id !== ''
        && $primary_scene_id !== ''
        && isset($scene_chapter_map[$primary_scene_id])
        && $scene_chapter_map[$primary_scene_id] !== $primary_chapter_id
      ) {
        $errors[] = "Storyline entry_point scene '{$primary_scene_id}' does not belong to chapter '{$primary_chapter_id}'.";
      }
      if ($primary_location_id !== '') {
        if (!isset($location_asset_anchors[$primary_location_id])) {
          $errors[] = "Storyline entry_point primary location '{$primary_location_id}' is not declared as a room/location asset reference.";
        }
        else {
          $location_anchor = $location_asset_anchors[$primary_location_id];
          $location_chapter_id = trim((string) ($location_anchor['chapter_id'] ?? ''));
          $location_scene_id = trim((string) ($location_anchor['scene_id'] ?? ''));
          if (
            $primary_chapter_id !== ''
            && $location_chapter_id !== ''
            && $location_chapter_id !== $primary_chapter_id
          ) {
            $errors[] = "Storyline entry_point primary location '{$primary_location_id}' does not belong to chapter '{$primary_chapter_id}'.";
          }
          if (
            $primary_scene_id !== ''
            && $location_scene_id !== ''
            && $location_scene_id !== $primary_scene_id
          ) {
            $errors[] = "Storyline entry_point primary location '{$primary_location_id}' does not belong to scene '{$primary_scene_id}'.";
          }
        }
      }

      $canonical_index = $this->loadCanonicalLocationTemplateIndex();
      foreach ($canonical_index['errors'] as $canonical_error) {
        $errors[] = $canonical_error;
      }
      $primary_dungeon_is_storyline_declared = $primary_dungeon_id !== '' && (
        isset($outline_declared_dungeons[$primary_dungeon_id]) || isset($chapter_ids[$primary_dungeon_id])
      );
      $primary_location_is_storyline_declared = $primary_location_id !== '' && (
        isset($outline_declared_rooms[$primary_location_id]) || isset($scene_ids[$primary_location_id]) || isset($location_asset_anchors[$primary_location_id])
      );

      if (
        $primary_dungeon_id !== ''
        && !isset($canonical_index['dungeon_ids'][$primary_dungeon_id])
        && !$primary_dungeon_is_storyline_declared
      ) {
        $errors[] = "Storyline entry_point primary dungeon '{$primary_dungeon_id}' is not defined in canonical dungeon templates.";
      }
      if (
        $primary_location_id !== ''
        && !isset($canonical_index['room_ids'][$primary_location_id])
        && !$primary_location_is_storyline_declared
      ) {
        $errors[] = "Storyline entry_point primary location '{$primary_location_id}' is not defined in canonical room templates.";
      }
      if (
        $primary_dungeon_id !== ''
        && $primary_location_id !== ''
        && isset($canonical_index['dungeon_ids'][$primary_dungeon_id])
        && isset($canonical_index['room_ids'][$primary_location_id])
      ) {
        $dungeon_rooms = $canonical_index['dungeon_room_ids'][$primary_dungeon_id] ?? [];
        if ($dungeon_rooms === []) {
          $errors[] = "Canonical dungeon '{$primary_dungeon_id}' does not declare entry/room links needed for entry-point validation.";
        }
        elseif (!isset($dungeon_rooms[$primary_location_id])) {
          $errors[] = "Storyline entry_point primary location '{$primary_location_id}' is not linked to canonical dungeon '{$primary_dungeon_id}'.";
        }
      }

      if ($introduction_path === 'brokered') {
        if ($broker_id === '') {
          $errors[] = 'Storyline entry_point.broker_id is required when introduction_path is brokered.';
        }
        elseif (!isset($contact_entity_ids[$broker_id])) {
          $errors[] = "Storyline entry_point broker '{$broker_id}' is not declared as a contact.";
        }
        elseif (!isset($contact_roles[$broker_id]['broker'])) {
          $errors[] = "Storyline entry_point broker '{$broker_id}' must use role broker.";
        }

        if (
          $broker_id !== ''
          && $primary_quest_giver_id !== ''
          && !isset($broker_introductions[$broker_id][$primary_quest_giver_id])
        ) {
          $errors[] = "Storyline entry_point broker '{$broker_id}' must explicitly introduce primary quest giver '{$primary_quest_giver_id}'.";
        }
      }
      elseif ($broker_id !== '' && !isset($contact_roles[$broker_id]['broker'])) {
        $errors[] = "Storyline entry_point broker '{$broker_id}' must use role broker when provided.";
      }
    }

    $outline_dungeons = $outline_declared_dungeons;
    $outline_rooms = $outline_declared_rooms;
    $outline_npcs = $contact_entity_ids + $declared_asset_npc_ids;
    foreach (array_values(array_filter(is_array($outline['sub_bosses'] ?? NULL) ? $outline['sub_bosses'] : [], 'is_array')) as $boss) {
      $boss_id = trim((string) ($boss['boss_id'] ?? ''));
      if ($boss_id !== '') {
        $outline_npcs[$boss_id] = TRUE;
      }
    }
    $big_boss_id = trim((string) ($outline['big_boss']['boss_id'] ?? ''));
    if ($big_boss_id !== '') {
      $outline_npcs[$big_boss_id] = TRUE;
    }

    foreach (array_values(array_filter(is_array($outline['dungeons'] ?? NULL) ? $outline['dungeons'] : [], 'is_array')) as $dungeon) {
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id === '') {
        continue;
      }
      $rooms = array_values(array_filter(is_array($dungeon['rooms'] ?? NULL) ? $dungeon['rooms'] : [], 'is_array'));
      $room_ids = [];
      foreach ($rooms as $room) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id === '') {
          continue;
        }
        $room_ids[$room_id] = TRUE;
        foreach (array_values(array_filter(array_map('strval', is_array($room['npc_ids'] ?? NULL) ? $room['npc_ids'] : []))) as $npc_id) {
          if (!isset($outline_npcs[$npc_id])) {
            $errors[] = "Room '{$room_id}' references unknown NPC '{$npc_id}'.";
          }
        }
      }

      if ((int) ($dungeon['room_count'] ?? 0) !== count($rooms)) {
        $errors[] = "Dungeon '{$dungeon_id}' room_count does not match realized room definitions.";
      }
      foreach (['entrance_room_id', 'boss_room_id'] as $room_field) {
        $room_id = trim((string) ($dungeon[$room_field] ?? ''));
        if ($room_id !== '' && !isset($room_ids[$room_id])) {
          $errors[] = "Dungeon '{$dungeon_id}' references unknown room '{$room_id}' in {$room_field}.";
        }
      }
    }

    $entry_dungeon = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    $entry_dungeon_id = trim((string) ($entry_dungeon['dungeon_id'] ?? ''));
    if ($entry_dungeon_id !== '' && !isset($outline_dungeons[$entry_dungeon_id]) && !isset($chapter_ids[$entry_dungeon_id])) {
      $errors[] = "Entry dungeon '{$entry_dungeon_id}' is not defined by the storyline.";
    }
    $entry_room_id = trim((string) ($entry_dungeon['entrance_room_id'] ?? ''));
    if ($entry_room_id !== '' && !isset($outline_rooms[$entry_room_id]) && !isset($scene_ids[$entry_room_id])) {
      $errors[] = "Entry room '{$entry_room_id}' is not defined by the storyline.";
    }

    foreach (array_values(array_filter(is_array($outline['progression_connectors'] ?? NULL) ? $outline['progression_connectors'] : [], 'is_array')) as $connector) {
      $connector_id = trim((string) ($connector['connector_id'] ?? 'unknown-connector'));
      $source_type = trim((string) ($connector['source_type'] ?? ''));
      $source_id = trim((string) ($connector['source_id'] ?? ''));
      if ($source_type === 'npc' && $source_id !== '' && !isset($outline_npcs[$source_id])) {
        $errors[] = "Progression connector '{$connector_id}' references unknown NPC source '{$source_id}'.";
      }

      $target_dungeon_id = trim((string) ($connector['target_dungeon_id'] ?? ''));
      if ($target_dungeon_id !== '' && !isset($outline_dungeons[$target_dungeon_id]) && !isset($chapter_ids[$target_dungeon_id])) {
        $errors[] = "Progression connector '{$connector_id}' points to unknown dungeon '{$target_dungeon_id}'.";
      }
      $target_room_id = trim((string) ($connector['target_room_id'] ?? ''));
      if ($target_room_id !== '' && !isset($outline_rooms[$target_room_id]) && !isset($scene_ids[$target_room_id])) {
        $errors[] = "Progression connector '{$connector_id}' points to unknown room '{$target_room_id}'.";
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate canonical quest-to-storyline ownership for linked quests.
   *
   * @param array<string, mixed> $linked_quests
   *   Storyline linked_quests map.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateCanonicalLinkedQuestStorylineOwnership(string $storyline_template_id, array $linked_quests): array {
    $storyline_template_id = trim($storyline_template_id);
    if ($storyline_template_id === '') {
      return [];
    }

    $quest_template_ids = [];
    foreach ($linked_quests as $quest_key => $quest_link) {
      $quest_template_id = trim((string) $quest_key);
      if (is_array($quest_link)) {
        $quest_template_id = trim((string) ($quest_link['quest_id'] ?? $quest_template_id));
      }
      if ($quest_template_id !== '') {
        $quest_template_ids[$quest_template_id] = TRUE;
      }
    }
    if ($quest_template_ids === []) {
      return [];
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_canonical_quests')) {
      return ['Canonical quest ownership validation requires dc_canonical_quests table.'];
    }
    if (!$schema->fieldExists('dc_canonical_quests', 'template_id')) {
      return ['Canonical quest ownership validation requires dc_canonical_quests.template_id column.'];
    }
    if (!$schema->fieldExists('dc_canonical_quests', 'storyline_template_id')) {
      return ['Canonical quest ownership validation requires dc_canonical_quests.storyline_template_id column.'];
    }
    if (!$schema->fieldExists('dc_canonical_quests', 'story_impact')) {
      return ['Canonical quest ownership validation requires dc_canonical_quests.story_impact column.'];
    }

    $rows = $this->database->select('dc_canonical_quests', 'q')
      ->fields('q', ['template_id', 'storyline_template_id', 'story_impact'])
      ->condition('template_id', array_keys($quest_template_ids), 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $row_map = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $template_id = trim((string) ($row['template_id'] ?? ''));
      if ($template_id !== '') {
        $row_map[$template_id] = $row;
      }
    }

    $errors = [];
    foreach (array_keys($quest_template_ids) as $quest_template_id) {
      $row = $row_map[$quest_template_id] ?? NULL;
      if (!is_array($row)) {
        $errors[] = "Linked quest '{$quest_template_id}' is missing from canonical quest templates.";
        continue;
      }

      $canonical_storyline_template_id = trim((string) ($row['storyline_template_id'] ?? ''));
      if ($canonical_storyline_template_id === '') {
        $errors[] = "Linked quest '{$quest_template_id}' is missing canonical storyline_template_id.";
      }
      elseif ($canonical_storyline_template_id !== $storyline_template_id) {
        $errors[] = "Linked quest '{$quest_template_id}' has canonical storyline_template_id '{$canonical_storyline_template_id}' but storyline template is '{$storyline_template_id}'.";
      }

      $story_impact = $this->decodeJsonArrayValue($row['story_impact'] ?? NULL);
      $story_impact_storyline_id = trim((string) ($story_impact['storyline_id'] ?? ''));
      if ($story_impact_storyline_id !== '' && $story_impact_storyline_id !== $storyline_template_id) {
        $errors[] = "Linked quest '{$quest_template_id}' has story_impact.storyline_id '{$story_impact_storyline_id}' but storyline template is '{$storyline_template_id}'.";
      }
    }

    return $errors;
  }

  /**
   * Collect outline-declared dungeon and room anchors.
   *
   * @return array{dungeons: array<string, bool>, rooms: array<string, bool>}
   *   Dedupe maps keyed by declared dungeon and room ids.
   */
  protected function collectOutlineLocationAnchors(array $outline): array {
    $dungeons = [];
    $rooms = [];

    foreach (array_values(array_filter(is_array($outline['dungeons'] ?? NULL) ? $outline['dungeons'] : [], 'is_array')) as $dungeon) {
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id !== '') {
        $dungeons[$dungeon_id] = TRUE;
      }
      foreach (array_values(array_filter(is_array($dungeon['rooms'] ?? NULL) ? $dungeon['rooms'] : [], 'is_array')) as $room) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id !== '') {
          $rooms[$room_id] = TRUE;
        }
      }
    }

    $entry_dungeon = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    $entry_dungeon_id = trim((string) ($entry_dungeon['dungeon_id'] ?? ''));
    if ($entry_dungeon_id !== '') {
      $dungeons[$entry_dungeon_id] = TRUE;
    }
    $entry_room_id = trim((string) ($entry_dungeon['entrance_room_id'] ?? ''));
    if ($entry_room_id !== '') {
      $rooms[$entry_room_id] = TRUE;
    }

    return [
      'dungeons' => $dungeons,
      'rooms' => $rooms,
    ];
  }

  /**
   * Normalizes raw asset-reference payloads.
   */
  protected function normalizeAssetReferences(array $raw_references, string $chapter_id, string $scene_id, string $source_scope): array {
    $normalized = [];
    foreach (array_values($raw_references) as $reference) {
      if (!is_array($reference)) {
        continue;
      }

      $asset_type = $this->sanitizeIdentifier((string) ($reference['asset_type'] ?? $reference['type'] ?? ''));
      $asset_id = trim((string) ($reference['asset_id'] ?? $reference['id'] ?? ''));
      if ($asset_type === '' || $asset_id === '') {
        continue;
      }

      $normalized[] = [
        'asset_type' => $asset_type,
        'asset_id' => $asset_id,
        'asset_role' => trim((string) ($reference['asset_role'] ?? $reference['role'] ?? '')),
        'chapter_id' => trim((string) ($reference['chapter_id'] ?? $chapter_id)),
        'scene_id' => trim((string) ($reference['scene_id'] ?? $scene_id)),
        'source_scope' => trim((string) ($reference['source_scope'] ?? $source_scope)) ?: 'storyline',
        'notes' => trim((string) ($reference['notes'] ?? '')),
        'link_data' => is_array($reference['link_data'] ?? NULL) ? $reference['link_data'] : [],
      ];
    }

    return $normalized;
  }

  /**
   * Builds a stable dedupe key for a normalized asset reference.
   */
  protected function buildAssetReferenceKey(array $reference): string {
    return implode('|', [
      (string) ($reference['asset_type'] ?? ''),
      (string) ($reference['asset_id'] ?? ''),
      (string) ($reference['asset_role'] ?? ''),
      (string) ($reference['chapter_id'] ?? ''),
      (string) ($reference['scene_id'] ?? ''),
      (string) ($reference['source_scope'] ?? ''),
    ]);
  }

  /**
   * Attaches known quest ids to a storyline instance in the quest table.
   */
  protected function attachQuestReferences(int $campaign_id, string $storyline_id, array $linked_quests): void {
    $storyline_quest_lifecycle_service = $this->resolveStorylineQuestLifecycleService();
    foreach ($linked_quests as $quest_link) {
      if (!is_array($quest_link) || empty($quest_link['quest_id'])) {
        continue;
      }

      $storyline_quest_lifecycle_service->attachStorylineReferenceToQuestRow(
        $campaign_id,
        (string) $quest_link['quest_id'],
        $storyline_id,
        !empty($quest_link['chapter_id']) ? (string) $quest_link['chapter_id'] : NULL,
        !empty($quest_link['scene_id']) ? (string) $quest_link['scene_id'] : NULL
      );
    }
  }

  /**
   * Resolve storyline quest lifecycle service on first use to avoid DI cycles.
   */
  protected function resolveStorylineQuestLifecycleService(): StorylineQuestLifecycleService {
    if ($this->storylineQuestLifecycleService instanceof StorylineQuestLifecycleService) {
      return $this->storylineQuestLifecycleService;
    }

    if (\Drupal::hasService('dungeoncrawler_content.storyline_quest_lifecycle')) {
      $candidate = \Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle');
      if ($candidate instanceof StorylineQuestLifecycleService) {
        $this->storylineQuestLifecycleService = $candidate;
        return $candidate;
      }
    }

    throw new \RuntimeException('StorylineQuestLifecycleService is required to attach storyline quest references.');
  }

  /**
   * Synchronizes normalized asset links for a storyline instance.
   */
  protected function syncCampaignStorylineAssetLinks(int $campaign_id, string $storyline_id, array $asset_references): void {
    $this->database->delete('dc_campaign_storyline_links')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $now = time();
    $normalized_rows = [];
    foreach ($asset_references as $reference) {
      if (!is_array($reference) || empty($reference['asset_type']) || empty($reference['asset_id'])) {
        continue;
      }
      $asset_type = trim((string) $reference['asset_type']);
      $asset_id = trim((string) $reference['asset_id']);
      if ($asset_type === '' || $asset_id === '') {
        continue;
      }
      $chapter_id = !empty($reference['chapter_id']) ? trim((string) $reference['chapter_id']) : NULL;
      $scene_id = !empty($reference['scene_id']) ? trim((string) $reference['scene_id']) : NULL;
      $source_scope = !empty($reference['source_scope']) ? trim((string) $reference['source_scope']) : 'storyline';
      if ($source_scope === '') {
        $source_scope = 'storyline';
      }
      $link_key = implode('|', [
        $asset_type,
        $asset_id,
        $source_scope,
        $chapter_id ?? '',
        $scene_id ?? '',
      ]);
      $normalized_rows[$link_key] = [
        'campaign_id' => $campaign_id,
        'storyline_id' => $storyline_id,
        'asset_type' => $asset_type,
        'asset_id' => $asset_id,
        'asset_role' => !empty($reference['asset_role']) ? (string) $reference['asset_role'] : NULL,
        'chapter_id' => $chapter_id,
        'scene_id' => $scene_id,
        'source_scope' => $source_scope,
        'notes' => !empty($reference['notes']) ? (string) $reference['notes'] : NULL,
        'link_data' => json_encode($reference['link_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ];
    }

    foreach ($normalized_rows as $row) {
      $this->database->merge('dc_campaign_storyline_links')
        ->keys([
          'campaign_id' => $row['campaign_id'],
          'storyline_id' => $row['storyline_id'],
          'asset_type' => $row['asset_type'],
          'asset_id' => $row['asset_id'],
          'source_scope' => $row['source_scope'],
          'chapter_id' => $row['chapter_id'],
          'scene_id' => $row['scene_id'],
        ])
        ->fields([
          'asset_role' => $row['asset_role'],
          'notes' => $row['notes'],
          'link_data' => $row['link_data'],
          'updated_at' => $now,
        ])
        ->expression('created_at', 'COALESCE(created_at, :time)', [':time' => $now])
        ->execute();
    }
  }

  /**
   * Synchronize normalized DB validation indexes for one storyline runtime scope.
   */
  public function synchronizeCampaignValidationIndexes(int $campaign_id, string $storyline_id): void {
    $storyline_id = trim($storyline_id);
    if ($campaign_id <= 0 || $storyline_id === '') {
      return;
    }
    $this->syncCampaignObjectiveReferences($campaign_id, $storyline_id);
    $this->syncCampaignLocationRegistry($campaign_id, $storyline_id);
  }

  /**
   * Rebuild normalized objective references for storyline-linked quests.
   */
  protected function syncCampaignObjectiveReferences(int $campaign_id, string $storyline_id): void {
    $schema_handler = $this->database->schema();
    if (
      !$schema_handler->tableExists('dc_campaign_objective_refs')
      || !$schema_handler->tableExists('dc_campaign_quests')
    ) {
      return;
    }

    $this->database->delete('dc_campaign_objective_refs')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $quest_rows = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'storyline_id', 'objective_states', 'generated_objectives'])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    if ($quest_rows === []) {
      return;
    }

    $now = time();
    foreach ($quest_rows as $quest_row) {
      $quest_id = trim((string) ($quest_row['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      $source_column = 'objective_states';
      $phases = $this->decodeJsonColumn($quest_row['objective_states'] ?? NULL);
      if (!is_array($phases) || $phases === []) {
        $source_column = 'generated_objectives';
        $phases = $this->decodeJsonColumn($quest_row['generated_objectives'] ?? NULL);
      }
      if (!is_array($phases) || $phases === []) {
        continue;
      }

      foreach ($this->collectObjectiveReferenceRows($phases, $quest_id, $source_column) as $ref_row) {
        $this->database->merge('dc_campaign_objective_refs')
          ->keys([
            'campaign_id' => $campaign_id,
            'quest_id' => $quest_id,
            'source_column' => (string) ($ref_row['source_column'] ?? $source_column),
            'objective_path' => (string) ($ref_row['objective_path'] ?? ''),
            'ref_field' => (string) ($ref_row['ref_field'] ?? ''),
            'ref_id' => (string) ($ref_row['ref_id'] ?? ''),
          ])
          ->fields([
            'storyline_id' => $storyline_id,
            'phase' => (int) ($ref_row['phase'] ?? 0),
            'objective_id' => (string) ($ref_row['objective_id'] ?? ''),
            'objective_type' => (string) ($ref_row['objective_type'] ?? ''),
            'ref_type' => (string) ($ref_row['ref_type'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
          ])
          ->execute();
      }
    }
  }

  /**
   * Rebuild normalized location registry for one storyline runtime scope.
   */
  protected function syncCampaignLocationRegistry(int $campaign_id, string $storyline_id): void {
    $schema_handler = $this->database->schema();
    if (
      !$schema_handler->tableExists('dc_campaign_locations')
      || !$schema_handler->tableExists('dc_campaign_quests')
      || !$schema_handler->tableExists('dc_campaign_rooms')
      || !$schema_handler->tableExists('dc_campaign_dungeons')
    ) {
      return;
    }

    $this->database->delete('dc_campaign_locations')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $now = time();
    $storyline = $this->getCampaignStoryline($campaign_id, $storyline_id, FALSE);
    $storyline_data = is_array($storyline['storyline_data'] ?? NULL) ? $storyline['storyline_data'] : [];
    foreach ((array) ($storyline_data['chapters'] ?? []) as $chapter_index => $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id !== '') {
        $this->database->merge('dc_campaign_locations')
          ->keys([
            'campaign_id' => $campaign_id,
            'storyline_id' => $storyline_id,
            'location_id' => $chapter_id,
            'location_type' => 'chapter',
            'source_type' => 'chapter_scene',
            'source_key' => "chapter:{$chapter_index}",
          ])
          ->fields(['created_at' => $now, 'updated_at' => $now])
          ->execute();
      }
      foreach ((array) ($chapter['scenes'] ?? []) as $scene_index => $scene) {
        $scene_id = trim((string) (is_array($scene) ? ($scene['scene_id'] ?? '') : ''));
        if ($scene_id === '') {
          continue;
        }
        $this->database->merge('dc_campaign_locations')
          ->keys([
            'campaign_id' => $campaign_id,
            'storyline_id' => $storyline_id,
            'location_id' => $scene_id,
            'location_type' => 'scene',
            'source_type' => 'chapter_scene',
            'source_key' => "scene:{$chapter_index}:{$scene_index}",
          ])
          ->fields(['created_at' => $now, 'updated_at' => $now])
          ->execute();
      }
    }

    $room_rows = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchCol() ?: [];
    foreach ($room_rows as $room_id) {
      $room_id = trim((string) $room_id);
      if ($room_id === '') {
        continue;
      }
      $this->database->merge('dc_campaign_locations')
        ->keys([
          'campaign_id' => $campaign_id,
          'storyline_id' => $storyline_id,
          'location_id' => $room_id,
          'location_type' => 'room',
          'source_type' => 'campaign_room',
          'source_key' => $room_id,
        ])
        ->fields(['created_at' => $now, 'updated_at' => $now])
        ->execute();
    }

    $dungeon_rows = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchCol() ?: [];
    foreach ($dungeon_rows as $dungeon_id) {
      $dungeon_id = trim((string) $dungeon_id);
      if ($dungeon_id === '') {
        continue;
      }
      $this->database->merge('dc_campaign_locations')
        ->keys([
          'campaign_id' => $campaign_id,
          'storyline_id' => $storyline_id,
          'location_id' => $dungeon_id,
          'location_type' => 'dungeon',
          'source_type' => 'campaign_dungeon',
          'source_key' => $dungeon_id,
        ])
        ->fields(['created_at' => $now, 'updated_at' => $now])
        ->execute();
    }

    $quest_rows = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'location_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    foreach ($quest_rows as $quest_row) {
      $quest_id = trim((string) ($quest_row['quest_id'] ?? ''));
      $location_id = trim((string) ($quest_row['location_id'] ?? ''));
      if ($quest_id === '' || $location_id === '') {
        continue;
      }
      $this->database->merge('dc_campaign_locations')
        ->keys([
          'campaign_id' => $campaign_id,
          'storyline_id' => $storyline_id,
          'location_id' => $location_id,
          'location_type' => 'location',
          'source_type' => 'quest_location',
          'source_key' => $quest_id,
        ])
        ->fields(['created_at' => $now, 'updated_at' => $now])
        ->execute();
    }

    $ref_rows = $this->database->select('dc_campaign_objective_refs', 'r')
      ->fields('r', ['quest_id', 'objective_path', 'ref_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->condition('ref_type', 'location')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    foreach ($ref_rows as $ref_row) {
      $location_id = trim((string) ($ref_row['ref_id'] ?? ''));
      if ($location_id === '') {
        continue;
      }
      $source_key = trim((string) ($ref_row['quest_id'] ?? '')) . ':' . trim((string) ($ref_row['objective_path'] ?? ''));
      $this->database->merge('dc_campaign_locations')
        ->keys([
          'campaign_id' => $campaign_id,
          'storyline_id' => $storyline_id,
          'location_id' => $location_id,
          'location_type' => 'location',
          'source_type' => 'objective_location',
          'source_key' => $source_key !== '' ? $source_key : 'objective',
        ])
        ->fields(['created_at' => $now, 'updated_at' => $now])
        ->execute();
    }
  }

  /**
   * Extract normalized reference rows from objective phases.
   *
   * @return array<int, array<string, mixed>>
   *   Flat reference rows.
   */
  protected function collectObjectiveReferenceRows(array $phases, string $quest_id, string $source_column): array {
    $rows = [];
    foreach ($phases as $phase_index => $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $phase_number = isset($phase['phase']) && is_numeric($phase['phase']) ? (int) $phase['phase'] : ($phase_index + 1);
      $objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      $this->collectObjectiveReferenceRowsRecursive($objectives, "phase[{$phase_index}].objectives", $phase_number, $quest_id, $source_column, $rows);
    }
    return $rows;
  }

  /**
   * Recursively collect objective references.
   *
   * @param array<int, mixed> $objectives
   *   Objective definitions.
   * @param string $path_prefix
   *   Path prefix.
   * @param int $phase
   *   Phase number.
   * @param string $quest_id
   *   Quest id.
   * @param string $source_column
   *   Source column.
   * @param array<int, array<string, mixed>> $rows
   *   Output rows.
   */
  protected function collectObjectiveReferenceRowsRecursive(
    array $objectives,
    string $path_prefix,
    int $phase,
    string $quest_id,
    string $source_column,
    array &$rows
  ): void {
    foreach ($objectives as $index => $objective) {
      if (!is_array($objective)) {
        continue;
      }
      $path = "{$path_prefix}[{$index}]";
      $objective_id = trim((string) ($objective['objective_id'] ?? $objective['id'] ?? ''));
      $objective_type = strtolower(trim((string) ($objective['type'] ?? '')));
      foreach (['target', 'target_id'] as $field) {
        $value = trim((string) ($objective[$field] ?? ''));
        if ($value !== '') {
          $rows[] = [
            'quest_id' => $quest_id,
            'phase' => $phase,
            'objective_id' => $objective_id,
            'objective_type' => $objective_type,
            'objective_path' => $path,
            'source_column' => $source_column,
            'ref_type' => 'target',
            'ref_field' => $field,
            'ref_id' => $value,
          ];
        }
      }
      foreach (['location', 'location_id', 'destination', 'destination_id'] as $field) {
        $value = trim((string) ($objective[$field] ?? ''));
        if ($value !== '') {
          $rows[] = [
            'quest_id' => $quest_id,
            'phase' => $phase,
            'objective_id' => $objective_id,
            'objective_type' => $objective_type,
            'objective_path' => $path,
            'source_column' => $source_column,
            'ref_type' => 'location',
            'ref_field' => $field,
            'ref_id' => $value,
          ];
        }
      }
      $item_ref = trim((string) ($objective['item'] ?? ''));
      if ($item_ref !== '') {
        $rows[] = [
          'quest_id' => $quest_id,
          'phase' => $phase,
          'objective_id' => $objective_id,
          'objective_type' => $objective_type,
          'objective_path' => $path,
          'source_column' => $source_column,
          'ref_type' => 'item',
          'ref_field' => 'item',
          'ref_id' => $item_ref,
        ];
      }

      $children = is_array($objective['children'] ?? NULL) ? $objective['children'] : [];
      if ($children !== []) {
        $this->collectObjectiveReferenceRowsRecursive($children, "{$path}.children", $phase, $quest_id, $source_column, $rows);
      }
    }
  }

  /**
   * Writes a storyline journal/log entry.
   */
  protected function logStorylineEvent(
    int $campaign_id,
    string $storyline_id,
    string $event_type,
    array $event_data,
    ?string $narrative_text = NULL
  ): void {
    $this->database->insert('dc_campaign_storyline_log')
      ->fields([
        'campaign_id' => $campaign_id,
        'storyline_id' => $storyline_id,
        'event_type' => $event_type,
        'event_data' => json_encode($event_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'narrative_text' => $narrative_text,
        'created_at' => time(),
      ])
      ->execute();
  }

  /**
   * Persists active storyline pointers into campaign state.
   */
  protected function persistCampaignStorylinePointers(int $campaign_id, string $storyline_id, bool $primary): void {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $storylines = is_array($state['storylines'] ?? NULL) ? $state['storylines'] : [];
    $active_ids = array_values(array_unique(array_filter(array_map('strval', $storylines['active_storyline_ids'] ?? []))));
    if (!in_array($storyline_id, $active_ids, TRUE)) {
      $active_ids[] = $storyline_id;
    }

    $storylines['active_storyline_id'] = $storyline_id;
    $storylines['active_storyline_ids'] = $active_ids;
    if ($primary || empty($storylines['primary_storyline_id'])) {
      $storylines['primary_storyline_id'] = $storyline_id;
    }

    $state['storylines'] = $storylines;
    $this->campaignStateService->setState($campaign_id, $state, isset($current['version']) ? (int) $current['version'] : NULL);
  }

  /**
   * Synchronizes a storyline instance against current quest state.
   */
  protected function synchronizeStorylineProgress(array $row): array {
    $campaign_id = (int) ($row['campaign_id'] ?? 0);
    $storyline_id = (string) ($row['storyline_id'] ?? '');
    $storyline_data = $this->normalizeRuntimeStorylineData($this->decodeJsonColumn($row['storyline_data'] ?? NULL));

    $quest_rows = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'status'])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $quest_state_map = [];
    foreach ($quest_rows as $quest_row) {
      $quest_state_map[(string) ($quest_row['quest_id'] ?? '')] = (string) ($quest_row['status'] ?? 'available');
    }

    $sync = $this->synchronizeStorylineDataWithQuestStates(
      $storyline_data,
      (string) ($row['current_chapter_id'] ?? ''),
      (string) ($row['current_scene_id'] ?? ''),
      $quest_state_map
    );

    $fields = [
      'status' => (string) $sync['status'],
      'current_chapter_id' => $sync['current_chapter_id'] !== '' ? (string) $sync['current_chapter_id'] : NULL,
      'current_scene_id' => $sync['current_scene_id'] !== '' ? (string) $sync['current_scene_id'] : NULL,
      'storyline_data' => json_encode($sync['storyline_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'variables' => json_encode($sync['storyline_data']['variables'] ?? $this->decodeJsonColumn($row['variables'] ?? NULL), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'updated_at' => time(),
    ];

    $this->syncCampaignStorylineAssetLinks(
      $campaign_id,
      $storyline_id,
      $sync['storyline_data']['asset_references'] ?? []
    );
    $this->synchronizeCampaignValidationIndexes($campaign_id, $storyline_id);

    if ($sync['status'] === 'completed' && empty($row['completed_at'])) {
      $fields['completed_at'] = time();
    }

    $this->database->update('dc_campaign_storylines')
      ->fields($fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    foreach ($sync['events'] as $event) {
      $this->logStorylineEvent(
        $campaign_id,
        $storyline_id,
        (string) ($event['event_type'] ?? 'storyline_progressed'),
        [
          'current_chapter_id' => $sync['current_chapter_id'],
          'current_scene_id' => $sync['current_scene_id'],
          'status' => $sync['status'],
        ],
        isset($event['narrative_text']) ? (string) $event['narrative_text'] : NULL
      );
    }

    return array_replace($row, $fields);
  }

  /**
   * Returns quest ids relevant to a given chapter/scene position.
   */
  protected function getQuestIdsForPosition(array $storyline_data, string $chapter_id, string $scene_id): array {
    foreach (($storyline_data['chapters'] ?? []) as $chapter) {
      if ((string) ($chapter['chapter_id'] ?? '') !== $chapter_id) {
        continue;
      }

      if ($scene_id !== '') {
        foreach (($chapter['scenes'] ?? []) as $scene) {
          if ((string) ($scene['scene_id'] ?? '') === $scene_id) {
            return array_values(array_unique(array_filter(array_map('strval', $scene['quest_ids'] ?? []))));
          }
        }
      }

      return array_values(array_unique(array_filter(array_map('strval', $chapter['quest_ids'] ?? []))));
    }

    return [];
  }

  /**
   * Derives the next chapter/scene position after the current one.
   */
  protected function deriveNextPosition(array $storyline_data, string $chapter_id, string $scene_id): ?array {
    $chapters = array_values($storyline_data['chapters'] ?? []);
    foreach ($chapters as $chapter_index => $chapter) {
      if ((string) ($chapter['chapter_id'] ?? '') !== $chapter_id) {
        continue;
      }

      $scenes = array_values($chapter['scenes'] ?? []);
      if ($scene_id !== '') {
        foreach ($scenes as $scene_index => $scene) {
          if ((string) ($scene['scene_id'] ?? '') !== $scene_id) {
            continue;
          }

          if (isset($scenes[$scene_index + 1])) {
            return [
              'chapter_id' => $chapter_id,
              'scene_id' => (string) ($scenes[$scene_index + 1]['scene_id'] ?? ''),
            ];
          }

          break;
        }
      }

      if (isset($chapters[$chapter_index + 1])) {
        $next_chapter = $chapters[$chapter_index + 1];
        return [
          'chapter_id' => (string) ($next_chapter['chapter_id'] ?? ''),
          'scene_id' => (string) ($next_chapter['scenes'][0]['scene_id'] ?? ''),
        ];
      }
    }

    return NULL;
  }

  /**
   * Hydrates a template row for API use.
   */
  protected function hydrateTemplateRow(array $row): array {
    $row['tags'] = $this->decodeJsonColumn($row['tags'] ?? NULL);
    $row['template_data'] = $this->decodeJsonColumn($row['template_data'] ?? NULL);
    return $row;
  }

  /**
   * Hydrates a campaign storyline row for API use.
   */
  protected function hydrateCampaignStorylineRow(array $row): array {
    $row['storyline_data'] = $this->normalizeRuntimeStorylineData($this->decodeJsonColumn($row['storyline_data'] ?? NULL));
    $row['variables'] = $this->decodeJsonColumn($row['variables'] ?? NULL);
    $row['is_primary'] = !empty($row['is_primary']);
    return $row;
  }

  /**
   * Backfill legacy storyline runtime rows to the current runtime envelope.
   */
  protected function normalizeRuntimeStorylineData(array $storyline_data): array {
    $legacy_template_id = trim((string) ($storyline_data['template_id'] ?? ''));
    $legacy_name = trim((string) ($storyline_data['name'] ?? ''));
    $storyline_data = array_replace([
      'schema_version' => self::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => [],
      'chapters' => [],
      'linked_quests' => [],
      'questline' => [
        'primary_quest_id' => '',
        'ordered_quest_ids' => [],
        'quest_nodes' => [],
      ],
      'asset_references' => [],
      'contacts' => [],
      'unlocked_chapter_ids' => [],
      'unlocked_scene_ids' => [],
      'current_chapter_id' => '',
      'current_scene_id' => '',
      'status' => 'available',
      'variables' => [],
    ], $storyline_data);

    $storyline_data['schema_version'] = (string) ($storyline_data['schema_version'] ?? self::STORYLINE_RUNTIME_SCHEMA_VERSION);
    $storyline_data['storyline_type'] = (string) ($storyline_data['storyline_type'] ?? 'questline');
    $storyline_data['metadata'] = is_array($storyline_data['metadata'] ?? NULL) ? $storyline_data['metadata'] : [];
    $storyline_data['chapters'] = is_array($storyline_data['chapters'] ?? NULL) ? array_values($storyline_data['chapters']) : [];
    $storyline_data['linked_quests'] = is_array($storyline_data['linked_quests'] ?? NULL) ? $storyline_data['linked_quests'] : [];
    $storyline_data['questline'] = is_array($storyline_data['questline'] ?? NULL)
      ? array_replace([
        'primary_quest_id' => '',
        'ordered_quest_ids' => [],
        'quest_nodes' => [],
      ], $storyline_data['questline'])
      : [
        'primary_quest_id' => '',
        'ordered_quest_ids' => [],
        'quest_nodes' => [],
      ];
    $storyline_data['asset_references'] = is_array($storyline_data['asset_references'] ?? NULL) ? array_values($storyline_data['asset_references']) : [];
    $storyline_data['contacts'] = is_array($storyline_data['contacts'] ?? NULL) ? array_values($storyline_data['contacts']) : [];
    $storyline_data['unlocked_chapter_ids'] = array_values(array_filter(array_map('strval', is_array($storyline_data['unlocked_chapter_ids'] ?? NULL) ? $storyline_data['unlocked_chapter_ids'] : [])));
    $storyline_data['unlocked_scene_ids'] = array_values(array_filter(array_map('strval', is_array($storyline_data['unlocked_scene_ids'] ?? NULL) ? $storyline_data['unlocked_scene_ids'] : [])));
    $storyline_data['current_chapter_id'] = (string) ($storyline_data['current_chapter_id'] ?? '');
    $storyline_data['current_scene_id'] = (string) ($storyline_data['current_scene_id'] ?? '');
    $storyline_data['status'] = (string) ($storyline_data['status'] ?? 'available');
    $storyline_data['variables'] = is_array($storyline_data['variables'] ?? NULL) ? $storyline_data['variables'] : [];
    if ($legacy_template_id !== '' && empty($storyline_data['metadata']['template_id'])) {
      $storyline_data['metadata']['template_id'] = $legacy_template_id;
    }
    if ($legacy_name !== '' && empty($storyline_data['metadata']['name'])) {
      $storyline_data['metadata']['name'] = $legacy_name;
    }

    unset($storyline_data['storyline_id'], $storyline_data['template_id'], $storyline_data['name']);

    $storyline_data = $this->normalizeBootstrapRuntimeOutline($storyline_data);

    return $storyline_data;
  }

  /**
   * Repair legacy bootstrap outline references so runtime rows stay canonical.
   */
  protected function normalizeBootstrapRuntimeOutline(array $storyline_data): array {
    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL) ? $storyline_data['metadata']['generated_outline'] : [];
    $entry_dungeon = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    $generation_phase = strtolower(trim((string) ($outline['generation_phase'] ?? '')));
    if ($generation_phase !== 'bootstrap' && $entry_dungeon === []) {
      return $storyline_data;
    }

    $first_chapter = is_array($storyline_data['chapters'][0] ?? NULL) ? $storyline_data['chapters'][0] : [];
    $first_scene = is_array($first_chapter['scenes'][0] ?? NULL) ? $first_chapter['scenes'][0] : [];
    $canonical_chapter_id = trim((string) ($first_chapter['chapter_id'] ?? ''));
    $canonical_scene_id = trim((string) ($first_scene['scene_id'] ?? ''));
    if ($canonical_chapter_id === '' && $canonical_scene_id === '') {
      return $storyline_data;
    }

    $chapter_ids = [];
    $scene_ids = [];
    foreach (array_values(array_filter(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : [], 'is_array')) as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id !== '') {
        $chapter_ids[$chapter_id] = TRUE;
      }
      foreach (array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array')) as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id !== '') {
          $scene_ids[$scene_id] = TRUE;
        }
      }
    }

    $outline_dungeon_ids = [];
    $outline_room_ids = [];
    foreach (array_values(array_filter(is_array($outline['dungeons'] ?? NULL) ? $outline['dungeons'] : [], 'is_array')) as $dungeon) {
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id !== '') {
        $outline_dungeon_ids[$dungeon_id] = TRUE;
      }
      foreach (array_values(array_filter(is_array($dungeon['rooms'] ?? NULL) ? $dungeon['rooms'] : [], 'is_array')) as $room) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id !== '') {
          $outline_room_ids[$room_id] = TRUE;
        }
      }
    }

    if ($entry_dungeon === []) {
      $entry_dungeon = [];
    }
    $entry_dungeon_id = trim((string) ($entry_dungeon['dungeon_id'] ?? ''));
    if (
      $canonical_chapter_id !== ''
      && ($entry_dungeon_id === '' || (!isset($outline_dungeon_ids[$entry_dungeon_id]) && !isset($chapter_ids[$entry_dungeon_id])))
    ) {
      $entry_dungeon['dungeon_id'] = $canonical_chapter_id;
    }
    if (empty($entry_dungeon['name']) && !empty($first_chapter['name'])) {
      $entry_dungeon['name'] = (string) $first_chapter['name'];
    }

    $canonical_room_id = trim((string) ($outline['entry_point']['primary_location_id'] ?? ''));
    if ($canonical_room_id === '') {
      $canonical_room_id = $this->resolveEntryPointPrimaryLocationId(
        array_values(array_filter(is_array($storyline_data['asset_references'] ?? NULL) ? $storyline_data['asset_references'] : [], 'is_array')),
        $canonical_chapter_id,
        $canonical_scene_id
      );
    }
    if ($canonical_room_id === '' && isset($outline_room_ids[$canonical_scene_id])) {
      $canonical_room_id = $canonical_scene_id;
    }

    $entry_room_id = trim((string) ($entry_dungeon['entrance_room_id'] ?? ''));
    if (
      $canonical_room_id !== ''
      && ($entry_room_id === '' || !isset($outline_room_ids[$entry_room_id]))
    ) {
      $entry_dungeon['entrance_room_id'] = $canonical_room_id;
    }
    $outline['entry_dungeon'] = $entry_dungeon;

    $target_dungeon_id = trim((string) ($entry_dungeon['dungeon_id'] ?? $canonical_chapter_id));
    $target_room_id = trim((string) ($entry_dungeon['entrance_room_id'] ?? $canonical_room_id));
    $connectors = [];
    foreach (array_values(array_filter(is_array($outline['progression_connectors'] ?? NULL) ? $outline['progression_connectors'] : [], 'is_array')) as $connector) {
      $connector_target_dungeon_id = trim((string) ($connector['target_dungeon_id'] ?? ''));
      if (
        $target_dungeon_id !== ''
        && ($connector_target_dungeon_id === '' || (!isset($outline_dungeon_ids[$connector_target_dungeon_id]) && !isset($chapter_ids[$connector_target_dungeon_id])))
      ) {
        $connector['target_dungeon_id'] = $target_dungeon_id;
      }

      $connector_target_room_id = trim((string) ($connector['target_room_id'] ?? ''));
      if (
        $target_room_id !== ''
        && ($connector_target_room_id === '' || !isset($outline_room_ids[$connector_target_room_id]))
      ) {
        $connector['target_room_id'] = $target_room_id;
      }

      $connectors[] = $connector;
    }
    if ($connectors !== []) {
      $outline['progression_connectors'] = $connectors;
    }

    $storyline_data['metadata']['generated_outline'] = $outline;
    return $storyline_data;
  }

  /**
   * Load canonical dungeon/room template ids for entry-point existence checks.
   *
   * @return array{
   *   dungeon_ids: array<string, bool>,
   *   room_ids: array<string, bool>,
   *   dungeon_room_ids: array<string, array<string, bool>>,
   *   errors: array<int, string>
   * }
   *   Canonical template index and load errors.
   */
  protected function loadCanonicalLocationTemplateIndex(): array {
    if ($this->canonicalLocationTemplateIndex !== NULL) {
      return $this->canonicalLocationTemplateIndex;
    }

    $index = [
      'dungeon_ids' => [],
      'room_ids' => [],
      'dungeon_room_ids' => [],
      'errors' => [],
    ];

    $schema = $this->database->schema();
    if (!$schema->tableExists('dungeoncrawler_content_dungeons')) {
      $index['errors'][] = 'Canonical dungeon table dungeoncrawler_content_dungeons is unavailable.';
    }
    elseif (
      !$schema->fieldExists('dungeoncrawler_content_dungeons', 'dungeon_id')
      || !$schema->fieldExists('dungeoncrawler_content_dungeons', 'dungeon_data')
    ) {
      $index['errors'][] = 'Canonical dungeon table dungeoncrawler_content_dungeons is missing required fields: dungeon_id, dungeon_data.';
    }
    else {
      $dungeon_rows = $this->database->select('dungeoncrawler_content_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'dungeon_data'])
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

      foreach ($dungeon_rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $dungeon_id = trim((string) ($row['dungeon_id'] ?? ''));
        if ($dungeon_id === '') {
          continue;
        }
        $index['dungeon_ids'][$dungeon_id] = TRUE;

        $dungeon_data = $this->decodeJsonArrayValue($row['dungeon_data'] ?? []);
        $entry_room_id = trim((string) ($dungeon_data['entry_room'] ?? ''));
        if ($entry_room_id !== '') {
          $index['dungeon_room_ids'][$dungeon_id][$entry_room_id] = TRUE;
        }
        foreach (array_values(array_filter(array_map('strval', is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : []), static fn(string $id): bool => trim($id) !== '')) as $room_id) {
          $index['dungeon_room_ids'][$dungeon_id][$room_id] = TRUE;
        }
      }
    }

    if (!$schema->tableExists('dungeoncrawler_content_rooms')) {
      $index['errors'][] = 'Canonical room table dungeoncrawler_content_rooms is unavailable.';
    }
    elseif (!$schema->fieldExists('dungeoncrawler_content_rooms', 'room_id')) {
      $index['errors'][] = 'Canonical room table dungeoncrawler_content_rooms is missing required field: room_id.';
    }
    else {
      $room_rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
        ->fields('r', ['room_id'])
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

      foreach ($room_rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $room_id = trim((string) ($row['room_id'] ?? ''));
        if ($room_id !== '') {
          $index['room_ids'][$room_id] = TRUE;
        }
      }
    }

    foreach ($index['dungeon_room_ids'] as $dungeon_id => $room_ids) {
      $index['dungeon_room_ids'][$dungeon_id] = array_fill_keys(array_keys($room_ids), TRUE);
      foreach (array_keys($index['dungeon_room_ids'][$dungeon_id]) as $room_id) {
        $index['room_ids'][(string) $room_id] = TRUE;
      }
    }

    $this->canonicalLocationTemplateIndex = $index;
    return $this->canonicalLocationTemplateIndex;
  }

  /**
   * Decode mixed JSON/array values into arrays.
   */
  protected function decodeJsonArrayValue(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }

    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Decodes JSON columns safely.
   */
  protected function decodeJsonColumn(mixed $value): array {
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Sanitizes identifiers for template, chapter, scene, and storyline ids.
   */
  protected function sanitizeIdentifier(string $candidate): string {
    $candidate = strtolower(trim($candidate));
    $candidate = preg_replace('/[^a-z0-9_-]+/', '-', $candidate) ?? '';
    return trim($candidate, '-_');
  }

  /**
   * Ensures an id exists once in an unlocked-id list.
   */
  protected function ensureUnlockedId(array $ids, string $candidate): array {
    $candidate = trim($candidate);
    if ($candidate === '') {
      return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    $ids[] = $candidate;
    return array_values(array_unique(array_filter(array_map('strval', $ids))));
  }

  /**
   * Normalizes relationship attitudes to the supported PF2e social scale.
   */
  protected function normalizeAttitudeValue(string $attitude): string {
    $attitude = strtolower(trim($attitude));
    $valid = ['helpful', 'friendly', 'indifferent', 'unfriendly', 'hostile'];
    return in_array($attitude, $valid, TRUE) ? $attitude : 'indifferent';
  }

  /**
   * Generates a unique campaign-scoped storyline id.
   */
  protected function generateCampaignStorylineId(int $campaign_id, string $base): string {
    $candidate = $this->sanitizeIdentifier($base);
    if ($candidate === '') {
      $candidate = 'storyline';
    }

    $existing = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s', ['storyline_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $candidate)
      ->execute()
      ->fetchField();

    if (!$existing) {
      return $candidate;
    }

    return $candidate . '-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
  }

  /**
   * Returns whether the required storyline storage schema is available.
   */
  protected function isCanonicalStorylineStorageReady(): bool {
    $schema = $this->database->schema();
    if (
      !$schema->tableExists(self::CANONICAL_STORYLINE_TABLE)
      || !$schema->tableExists('dc_canonical_quests')
    ) {
      return FALSE;
    }

    foreach (self::CANONICAL_STORYLINE_REQUIRED_FIELDS as $field_name) {
      if (!$schema->fieldExists(self::CANONICAL_STORYLINE_TABLE, $field_name)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Returns whether the required runtime storyline storage schema is available.
   */
  protected function isRuntimeStorylineStorageReady(): bool {
    $schema = $this->database->schema();
    return $this->isCanonicalStorylineStorageReady()
      && $schema->tableExists('dc_canonical_quests')
      && $schema->tableExists('dc_campaign_storylines')
      && $schema->tableExists('dc_campaign_storyline_log')
      && $schema->tableExists('dc_campaign_storyline_links')
      && $schema->tableExists('dc_campaign_quests')
      && $schema->fieldExists('dc_campaign_quests', 'storyline_id')
      && $schema->fieldExists('dc_campaign_quests', 'storyline_chapter_id')
      && $schema->fieldExists('dc_campaign_quests', 'storyline_scene_id');
  }

  /**
   * Ensures canonical storyline schema exists before canonical APIs are used.
   */
  protected function assertCanonicalStorylineStorageReady(): void {
    if ($this->isCanonicalStorylineStorageReady()) {
      return;
    }

    $schema = $this->database->schema();
    $issues = [];
    if (!$schema->tableExists(self::CANONICAL_STORYLINE_TABLE)) {
      $issues[] = sprintf('missing table %s', self::CANONICAL_STORYLINE_TABLE);
    }
    if (!$schema->tableExists('dc_canonical_quests')) {
      $issues[] = 'missing table dc_canonical_quests';
    }
    if ($schema->tableExists(self::CANONICAL_STORYLINE_TABLE)) {
      foreach (self::CANONICAL_STORYLINE_REQUIRED_FIELDS as $field_name) {
        if (!$schema->fieldExists(self::CANONICAL_STORYLINE_TABLE, $field_name)) {
          $issues[] = sprintf('missing field %s.%s', self::CANONICAL_STORYLINE_TABLE, $field_name);
        }
      }
    }
    $detail = $issues !== [] ? implode('; ', $issues) : 'unknown contract mismatch';
    throw new \InvalidArgumentException(
      sprintf('Canonical storyline storage contract is not installed: %s. Run database updates first.', $detail),
      503
    );
  }

  /**
   * Ensures runtime storyline schema exists before runtime storyline APIs are used.
   */
  protected function assertStorylineStorageReady(): void {
    if ($this->isRuntimeStorylineStorageReady()) {
      return;
    }

    throw new \InvalidArgumentException('Storyline runtime storage is not installed yet. Run database updates first.', 503);
  }

}
