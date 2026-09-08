<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Exception\QuestTemplateReferenceIntegrityException;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Psr\Log\LoggerInterface;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\dungeoncrawler_content\Support\H3SpatialHelper;

/**
 * Orchestrates complete campaign initialization with default dungeon and rooms.
 *
 * Authority boundary:
 * - dc_campaign_rooms = campaign room source of truth created during bootstrap
 * - dc_campaign_connections = campaign traversal source of truth created during bootstrap
 * - dc_campaign_dungeons.dungeon_data = server-managed delivery snapshot seeded
 *   from campaign room/connector authority for compatibility/bootstrap delivery
 *
 * Responsible for:
 * - Creating campaign record
 * - Creating default starter dungeon based on theme
 * - Loading initial game content (Tavern Entrance room)
 * - Setting up NPCs and interactive objects
 * - Initializing campaign state
 *
 * Creates a fully playable campaign in one operation.
 */
class CampaignInitializationService {

  private const STARTER_CITY_DUNGEON_NAME = 'Absalom';
  private const STARTER_CITY_DUNGEON_DESCRIPTION = 'City hub containing The Gilded Tankard and nearby starter routes.';
  private const STARTER_CITY_STREETS_ROOM_ID = 'tpl_room_absalom_streets';
  private const STARTER_DEFAULT_SOURCE_ROOM_ID = 'tavern_entrance';
  private const STARTER_DEFAULT_RUNTIME_ROOM_ID = 'tavern_entrance';
  private const STARTER_UNDEAD_SOURCE_ROOM_ID = 'tpl_room_crypt_anteroom';
  private const STARTER_UNDEAD_RUNTIME_ROOM_ID = 'undead_crypt_entry_hall';
  private const STARTER_CANONICAL_CONNECTOR_DUNGEON_ID = 'tpl_dungeon_absalom_city';
  private const STARTER_LIBRARY_CONNECTOR_DUNGEON_ID = 'asset-library-starter-room';
  private const H3_ACTIVE_RESOLUTION = 14;
  private const INIT_STEP_BOOTSTRAP = 'campaign_bootstrap';
  private const INIT_PHASE_STRUCTURAL_INITIALIZING = 'structural_initializing';
  private const INIT_PHASE_STRUCTURAL_READY = 'structural_ready';

  protected Connection $database;
  protected UuidInterface $uuid;
  protected TimeInterface $time;
  protected LoggerInterface $logger;
  protected ModuleExtensionList $moduleList;
  protected QuestGeneratorService $questGenerator;
  protected CampaignNameGeneratorService $campaignNameGenerator;
  protected ?ChatSessionManager $chatSessionManager;
  protected ?NpcSheetGenerationService $npcSheetGenerationService;
  protected ?RoomViewImageService $roomViewImageService;
  protected ?StorylineManagerService $storylineManager;
  protected ?RelationshipManagerService $relationshipManager;
  protected ?ExitConnectorAuthorityService $connectorDefinitionService;
  protected ?NavigationRuntimeService $navigationRuntime;
  protected ?H3ProjectionQueueService $h3ProjectionQueue;
  protected ?StarterProjectionArtifactRegistryService $starterProjectionArtifactRegistry;
  protected StorylineQuestLifecycleService $storylineQuestLifecycleService;
  protected CampaignClockService $campaignClockService;
  protected ConfigFactoryInterface $configFactory;
  protected ?DungeonEditorService $dungeonEditor;

  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleExtensionList $module_list,
    QuestGeneratorService $quest_generator,
    CampaignNameGeneratorService $campaign_name_generator,
    CampaignClockService $campaign_clock_service,
    StorylineQuestLifecycleService $storyline_quest_lifecycle_service,
    ?ChatSessionManager $chat_session_manager = NULL,
    ?NpcSheetGenerationService $npc_sheet_generation_service = NULL,
    ?RoomViewImageService $room_view_image_service = NULL,
    ?StorylineManagerService $storyline_manager = NULL,
    ?RelationshipManagerService $relationship_manager = NULL,
    ?ExitConnectorAuthorityService $connector_definition_service = NULL,
    ?NavigationRuntimeService $navigation_runtime = NULL,
    ?H3ProjectionQueueService $h3_projection_queue = NULL,
    ?StarterProjectionArtifactRegistryService $starter_projection_artifact_registry = NULL,
    ?ConfigFactoryInterface $config_factory = NULL,
    ?DungeonEditorService $dungeon_editor = NULL
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->moduleList = $module_list;
    $this->questGenerator = $quest_generator;
    $this->campaignNameGenerator = $campaign_name_generator;
    $this->configFactory = $config_factory ?? Drupal::configFactory();
    $this->campaignClockService = $campaign_clock_service;
    $this->chatSessionManager = $chat_session_manager;
    $this->npcSheetGenerationService = $npc_sheet_generation_service;
    $this->roomViewImageService = $room_view_image_service;
    $this->storylineManager = $storyline_manager;
    $this->relationshipManager = $relationship_manager;
    $this->connectorDefinitionService = $connector_definition_service;
    $this->navigationRuntime = $navigation_runtime;
    $this->h3ProjectionQueue = $h3_projection_queue;
    $this->starterProjectionArtifactRegistry = $starter_projection_artifact_registry;
    $this->storylineQuestLifecycleService = $storyline_quest_lifecycle_service;
    $this->dungeonEditor = $dungeon_editor;
  }

  /**
   * Initialize a complete campaign with default dungeon and starting content.
   *
   * @param int $uid
   *   Campaign owner user ID.
   * @param string $name
   *   Campaign name.
   * @param string $theme
   *   Campaign theme (classic_dungeon, goblin_warrens, undead_crypt).
   * @param string $difficulty
   *   Difficulty level (normal, hard, extreme).
   *
   * @return int
   *   Campaign ID on success, or 0 on failure.
   */
  public function initializeCampaign(
    int $uid,
    string $name,
    string $theme,
    string $difficulty,
    ?array $source = NULL
  ): int {
    $source = $this->normalizeCampaignSource($source, $theme);
    if ($source['kind'] === 'published_dungeon') {
      return $this->initializeCampaignFromPublishedDungeon($uid, $name, $difficulty, $source);
    }
    $theme = (string) $source['theme'];
    $now = $this->time->getRequestTime();
    $campaign_name = $this->resolveCampaignName($name, $theme, $uid, $now);
    $operation_uuid = $this->uuid->generate();
    $campaign_id = 0;
    $phase = 'bootstrap_start';

    $transaction = $this->database->startTransaction('campaign_init');
    try {
      // 1. Create campaign record
      $phase = 'create_campaign_record';
      $campaign_id = $this->createCampaign($uid, $campaign_name, $theme, $difficulty, $now);
      if (!$campaign_id) {
        return 0;
      }
      $phase = 'resolve_campaign_profile';
      $campaign_profile = $this->resolveCampaignProfile($theme);
      $phase = 'claim_initialization_step';
      $this->claimInitializationStep(
        $campaign_id,
        $operation_uuid,
        self::INIT_STEP_BOOTSTRAP,
        $now
      );

      $phase = 'resolve_starter_blueprint';
      $starter_blueprint = $this->resolveStarterBlueprint($campaign_profile, $theme);
      $this->assertStarterBlueprintContract($starter_blueprint);
      $phase = 'load_starter_room_seed';
      $starter_room = $this->loadStarterRoomSeed($starter_blueprint);
      if ($starter_room === NULL) {
        $transaction->rollBack();
        $this->logger->error('Failed to load explicit starter room asset for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }
      $starter_room_ids = $this->resolveStarterRoomIdentifiers($starter_room);
      $starter_runtime_room_id = $starter_room_ids['runtime_room_id'];

      // 2. Create default starter dungeon
      $phase = 'create_starter_dungeon';
      $dungeon_id = $this->createStarterDungeon($campaign_id, $theme, $now, $starter_room, $starter_blueprint);
      if (!$dungeon_id) {
        $transaction->rollBack();
        $this->logger->error('Failed to create starter dungeon for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }
      $connected_room_source_id = trim((string) ($starter_blueprint['connected_room_source_id'] ?? ''));
      if ($connected_room_source_id !== '') {
        $phase = 'seed_starter_connector_authority';
        $this->seedStarterConnectorAuthority($campaign_id, $dungeon_id, $starter_runtime_room_id);
      }
      $starter_frontier_room_ids = [$starter_runtime_room_id];
      if ($connected_room_source_id !== '') {
        $starter_frontier_room_ids[] = $connected_room_source_id;
      }
      $starter_frontier_room_ids = array_values(array_unique(array_filter(array_map(
        static fn(string $room_id): string => trim($room_id),
        $starter_frontier_room_ids
      ))));
      sort($starter_frontier_room_ids);
      $starter_profile_id = trim((string) ($starter_blueprint['starter_profile_id'] ?? ''));

      // 3. Load starter room and content.
      $phase = 'load_starter_room_into_campaign';
      if (!$this->loadStarterRoomIntoCampaign($campaign_id, $now, $starter_room, $starter_blueprint)) {
        $transaction->rollBack();
        $this->logger->error('Failed to load starter room into campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }

      $phase = 'seed_starter_quests';
      $this->seedStarterQuests($campaign_id, $difficulty, $now, $starter_runtime_room_id, $starter_blueprint);

      // 5. Bootstrap hierarchical chat sessions for the campaign.
      //    Include the starter dungeon and starter room so they get
      //    dedicated sessions from the very start.

      $phase = 'bootstrap_chat_sessions';
      $this->bootstrapChatSessions(
        $campaign_id,
        $campaign_name,
        $dungeon_id,
        $starter_runtime_room_id,
        (string) ($starter_room['name'] ?? 'The Gilded Tankard'),
        (string) ($starter_room['description'] ?? '')
      );
      if ($this->roomViewImageService) {
        $phase = 'warm_starter_room_view_image_cache';
        $this->roomViewImageService->warmRoomViewImageCache($starter_room, [
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
          'room_id' => $starter_runtime_room_id,
        ]);
      }
      if (!$this->h3ProjectionQueue) {
        throw new \RuntimeException('Campaign initialization contract violation: H3 projection provisioning service is required for starter readiness.');
      }
      $phase = 'provision_h3_launch_slice';
      $starter_provisioning_result = $this->h3ProjectionQueue->provisionLaunchSliceNow(
        $campaign_id,
        $dungeon_id,
        $starter_frontier_room_ids
      );
      $phase = 'persist_campaign_active_state';
      $this->persistCampaignActiveState($campaign_id, $dungeon_id, $starter_runtime_room_id, $now);
      $canonical_graph_version = trim((string) ($starter_provisioning_result['canonical_graph_version'] ?? ''));
      $campaign_graph_version = trim((string) ($starter_provisioning_result['campaign_graph_version'] ?? ''));
      if ($canonical_graph_version === '' || $campaign_graph_version === '') {
        throw new \RuntimeException('Campaign initialization contract violation: starter frontier provisioning did not return graph versions.');
      }
      if (!$this->starterProjectionArtifactRegistry) {
        throw new \RuntimeException('Campaign initialization contract violation: starter artifact registry service is required.');
      }
      $phase = 'upsert_starter_artifact_manifest';
      $starter_manifest = $this->starterProjectionArtifactRegistry->upsertStarterArtifactManifest(
        $starter_profile_id,
        $canonical_graph_version,
        $starter_frontier_room_ids,
        [
          'campaign_graph_version' => $campaign_graph_version,
          'trigger' => 'campaign_creation',
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
        ]
      );
      $starter_source_contract_hash = trim((string) ($starter_manifest['starter_source_contract_hash'] ?? ''));
      $starter_artifact_version = trim((string) ($starter_manifest['starter_artifact_version'] ?? ''));
      if ($starter_source_contract_hash === '' || $starter_artifact_version === '') {
        throw new \RuntimeException('Campaign initialization contract violation: starter artifact manifest is missing required version/hash metadata.');
      }
      $phase = 'complete_initialization_step';
      $this->completeInitializationStep(
        $campaign_id,
        self::INIT_STEP_BOOTSTRAP,
        $now,
        [
          'operation_uuid' => $operation_uuid,
          'dungeon_id' => $dungeon_id,
          'starter_room_id' => $starter_runtime_room_id,
        ]
      );
      $phase = 'persist_campaign_ready_phase';
      $this->persistCampaignInitPhase($campaign_id, self::INIT_PHASE_STRUCTURAL_READY, [
        'owner' => 'CampaignInitializationService',
        'ready_at' => gmdate('c', $now),
        'dungeon_id' => $dungeon_id,
        'runtime_dungeon_id' => $dungeon_id,
        'runtime_active_room_id' => $starter_runtime_room_id,
        'starter_room_id' => $starter_runtime_room_id,
        'operation_uuid' => $operation_uuid,
        'starter_profile_id' => $starter_profile_id,
        'starter_artifact_version' => $starter_artifact_version,
        'starter_source_contract_hash' => $starter_source_contract_hash,
        'starter_frontier_room_ids' => $starter_frontier_room_ids,
        'starter_frontier_scope_hash' => hash('sha256', implode(',', $starter_frontier_room_ids)),
        'starter_canonical_graph_version' => $canonical_graph_version,
        'starter_campaign_graph_version' => $campaign_graph_version,
        'starter_frontier_certified_at' => gmdate('c', $now),
        'starter_artifact_registry_content_type' => StarterProjectionArtifactRegistryService::CONTENT_TYPE,
        'starter_artifact_registry_content_id' => $starter_profile_id,
      ], $now);

      $this->logger->info('Campaign {campaign_id} initialized with starter dungeon {dungeon_id}', [
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
      ]);

      return $campaign_id;
    }
    catch (\Throwable $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Campaign initialization failed (operation={operation_uuid}, phase={phase}, campaign_id={campaign_id}, uid={uid}, theme={theme}, difficulty={difficulty}, exception={exception_class}): {error}', [
        'operation_uuid' => $operation_uuid,
        'phase' => $phase,
        'campaign_id' => $campaign_id,
        'uid' => $uid,
        'theme' => $theme,
        'difficulty' => $difficulty,
        'exception_class' => $e::class,
        'error' => $e->getMessage(),
      ]);
      $trace = $e->getTraceAsString();
      if ($trace !== '') {
        $this->logger->error('Campaign initialization failure trace (operation={operation_uuid}): {trace}', [
          'operation_uuid' => $operation_uuid,
          'trace' => substr($trace, 0, 4000),
        ]);
      }
      return 0;
    }
  }

  /**
   * Initialize a campaign from a current published canonical dungeon version.
   *
   * @param array{kind:string,dungeon_id:string,version_id:string} $source
   *   Validated source selector.
   */
  private function initializeCampaignFromPublishedDungeon(
    int $uid,
    string $name,
    string $difficulty,
    array $source
  ): int {
    $now = $this->time->getRequestTime();
    $operation_uuid = $this->uuid->generate();
    $campaign_id = 0;
    $phase = 'bootstrap_start';

    $transaction = $this->database->startTransaction('campaign_init');
    try {
      $phase = 'resolve_published_dungeon_source';
      $published = $this->resolvePublishedDungeonSource($source);
      $aggregate = $published['aggregate'];
      $entrance_placement = $published['entrance_placement'];
      $runtime_dungeon_id = $this->uuid->generate();
      $campaign_theme = $this->resolvePublishedDungeonTheme($aggregate);
      $campaign_name = trim($name) !== '' ? trim($name) : (string) $aggregate['name'];
      $campaign_profile = $this->buildPublishedDungeonCampaignProfile($published, $runtime_dungeon_id);

      $phase = 'create_campaign_record';
      $campaign_id = $this->createCampaign($uid, $campaign_name, $campaign_theme, $difficulty, $now, $campaign_profile);
      if (!$campaign_id) {
        return 0;
      }

      $phase = 'claim_initialization_step';
      $this->claimInitializationStep($campaign_id, $operation_uuid, self::INIT_STEP_BOOTSTRAP, $now);

      $phase = 'instantiate_published_dungeon_rooms';
      $room_context = $this->instantiatePublishedDungeonCampaignRooms($campaign_id, $published, $now);

      $phase = 'seed_published_dungeon_connectors';
      $connections = $this->seedPublishedDungeonCampaignConnectors($campaign_id, $runtime_dungeon_id, $published, $room_context);

      $phase = 'create_published_campaign_dungeon';
      $this->createPublishedCampaignDungeon($campaign_id, $runtime_dungeon_id, $published, $room_context['rooms'], $connections, $now);

      $phase = 'persist_published_sparse_h3_mappings';
      $this->persistPublishedCampaignSparseH3Mappings($runtime_dungeon_id, $room_context['sparse_h3_rooms'], $now);

      $entrance_room_id = (string) $entrance_placement['placement_id'];
      $entrance_room = $room_context['rooms_by_placement_id'][$entrance_room_id] ?? NULL;
      if (!is_array($entrance_room)) {
        throw new \RuntimeException('campaign_source_room_instantiation_invalid: entrance placement was not materialized.');
      }

      $phase = 'bootstrap_chat_sessions';
      $this->bootstrapChatSessions(
        $campaign_id,
        $campaign_name,
        $runtime_dungeon_id,
        $entrance_room_id,
        (string) ($entrance_room['name'] ?? $entrance_room_id),
        (string) ($entrance_room['description'] ?? '')
      );

      $phase = 'persist_campaign_active_state';
      $this->persistCampaignActiveState($campaign_id, $runtime_dungeon_id, $entrance_room_id, $now);

      $phase = 'complete_initialization_step';
      $this->completeInitializationStep($campaign_id, self::INIT_STEP_BOOTSTRAP, $now, [
        'operation_uuid' => $operation_uuid,
        'source_kind' => 'published_dungeon',
        'source_dungeon_id' => (string) $source['dungeon_id'],
        'source_version_id' => (string) $source['version_id'],
        'runtime_dungeon_id' => $runtime_dungeon_id,
        'starter_room_id' => $entrance_room_id,
      ]);

      $phase = 'persist_campaign_ready_phase';
      $this->persistCampaignInitPhase($campaign_id, self::INIT_PHASE_STRUCTURAL_READY, [
        'owner' => 'CampaignInitializationService',
        'ready_at' => gmdate('c', $now),
        'source_kind' => 'published_dungeon',
        'source_dungeon_id' => (string) $source['dungeon_id'],
        'source_version_id' => (string) $source['version_id'],
        'dungeon_id' => $runtime_dungeon_id,
        'runtime_dungeon_id' => $runtime_dungeon_id,
        'runtime_active_room_id' => $entrance_room_id,
        'starter_room_id' => $entrance_room_id,
        'operation_uuid' => $operation_uuid,
      ], $now);

      $this->logger->info('Campaign {campaign_id} initialized from published dungeon {source_dungeon_id} version {source_version_id}', [
        'campaign_id' => $campaign_id,
        'source_dungeon_id' => (string) $source['dungeon_id'],
        'source_version_id' => (string) $source['version_id'],
      ]);

      return $campaign_id;
    }
    catch (\Throwable $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Published-dungeon campaign initialization failed (operation={operation_uuid}, phase={phase}, campaign_id={campaign_id}, uid={uid}, difficulty={difficulty}, exception={exception_class}): {error}', [
        'operation_uuid' => $operation_uuid,
        'phase' => $phase,
        'campaign_id' => $campaign_id,
        'uid' => $uid,
        'difficulty' => $difficulty,
        'exception_class' => $e::class,
        'error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Record a campaign initialization step claim as the single-flight authority.
   */
  private function claimInitializationStep(
    int $campaign_id,
    string $operation_uuid,
    string $step_name,
    int $timestamp
  ): void {
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Campaign initialization contract violation: campaign id is required for step claims.');
    }
    if (trim($operation_uuid) === '') {
      throw new \RuntimeException('Campaign initialization contract violation: operation uuid is required for step claims.');
    }
    $step_name = trim($step_name);
    if ($step_name === '') {
      throw new \RuntimeException('Campaign initialization contract violation: step name is required for step claims.');
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_initialization_steps')) {
      throw new \RuntimeException('Campaign initialization contract violation: required table dc_campaign_initialization_steps is missing.');
    }

    try {
      $this->database->insert('dc_campaign_initialization_steps')
        ->fields([
          'campaign_id' => $campaign_id,
          'operation_uuid' => $operation_uuid,
          'step_name' => $step_name,
          'step_status' => 'in_progress',
          'details' => NULL,
          'created' => $timestamp,
          'updated' => $timestamp,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization hard-failed: duplicate or invalid initialization step claim for campaign %d step %s (%s).',
        $campaign_id,
        $step_name,
        $e->getMessage()
      ), 0, $e);
    }
  }

  /**
   * Mark a claimed campaign initialization step as completed.
   */
  private function completeInitializationStep(
    int $campaign_id,
    string $step_name,
    int $timestamp,
    array $details = []
  ): void {
    $step_name = trim($step_name);
    if ($campaign_id <= 0 || $step_name === '') {
      throw new \RuntimeException('Campaign initialization contract violation: completion requires campaign id and step name.');
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_initialization_steps')) {
      throw new \RuntimeException('Campaign initialization contract violation: required table dc_campaign_initialization_steps is missing.');
    }

    $encoded_details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded_details)) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: failed to encode completion details for campaign %d step %s.',
        $campaign_id,
        $step_name
      ));
    }

    $updated = (int) $this->database->update('dc_campaign_initialization_steps')
      ->fields([
        'step_status' => 'completed',
        'details' => $encoded_details,
        'updated' => $timestamp,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('step_name', $step_name)
      ->condition('step_status', 'in_progress')
      ->execute();

    if ($updated !== 1) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: completion update affected %d rows for campaign %d step %s.',
        $updated,
        $campaign_id,
        $step_name
      ));
    }
  }

  /**
   * Normalize the caller-selected campaign source.
   *
   * @return array{kind:string,theme?:string,dungeon_id?:string,version_id?:string}
   *   Validated source selector.
   */
  private function normalizeCampaignSource(?array $source, string $theme): array {
    if ($source === NULL || $source === []) {
      return ['kind' => 'theme', 'theme' => $theme];
    }

    $kind = strtolower(trim((string) ($source['kind'] ?? '')));
    if ($kind === 'theme') {
      $selected_theme = trim((string) ($source['theme'] ?? $theme));
      if ($selected_theme === '') {
        throw new \RuntimeException('campaign_source_invalid: theme source requires a theme.');
      }
      return ['kind' => 'theme', 'theme' => $selected_theme];
    }
    if ($kind === 'published_dungeon') {
      $dungeon_id = trim((string) ($source['dungeon_id'] ?? ''));
      $version_id = trim((string) ($source['version_id'] ?? ''));
      if ($dungeon_id === '' || $version_id === '') {
        throw new \RuntimeException('campaign_source_invalid: published_dungeon source requires dungeon_id and version_id.');
      }
      return [
        'kind' => 'published_dungeon',
        'dungeon_id' => $dungeon_id,
        'version_id' => $version_id,
      ];
    }

    throw new \RuntimeException(sprintf('campaign_source_invalid: unsupported source kind "%s".', $kind !== '' ? $kind : 'missing'));
  }

  /**
   * Resolve and validate a published canonical dungeon source.
   *
   * @param array{kind:string,dungeon_id:string,version_id:string} $source
   *   Published dungeon source selector.
   *
   * @return array<string,mixed>
   *   Resolved source context.
   */
  private function resolvePublishedDungeonSource(array $source): array {
    if (!$this->dungeonEditor) {
      throw new \RuntimeException('campaign_source_invalid: DungeonEditorService is required for published_dungeon source resolution.');
    }

    $dungeon_id = trim((string) $source['dungeon_id']);
    $version_id = trim((string) $source['version_id']);
    $version_row = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
      ->fields('v', ['version_id', 'dungeon_id', 'version', 'schema_version', 'dungeon_payload', 'payload_hash', 'catalog_version', 'publication_note', 'source', 'published_by', 'published_at'])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('version_id', $version_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($version_row)) {
      throw new \RuntimeException(sprintf(
        'campaign_source_dungeon_version_not_found: dungeon_id=%s version_id=%s.',
        $dungeon_id,
        $version_id
      ));
    }

    $identity_row = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'description', 'theme', 'published_version_id', 'publication_status'])
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($identity_row) || trim((string) ($identity_row['published_version_id'] ?? '')) !== $version_id) {
      throw new \RuntimeException(sprintf(
        'campaign_source_dungeon_version_not_published: dungeon_id=%s version_id=%s is not the current published identity.',
        $dungeon_id,
        $version_id
      ));
    }

    try {
      $aggregate = json_decode((string) $version_row['dungeon_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \RuntimeException(sprintf('dungeon_aggregate_invalid: dungeon_id=%s version_id=%s payload is invalid JSON.', $dungeon_id, $version_id), 0, $e);
    }
    if (!is_array($aggregate)) {
      throw new \RuntimeException(sprintf('dungeon_aggregate_invalid: dungeon_id=%s version_id=%s payload is not an object.', $dungeon_id, $version_id));
    }
    try {
      $this->dungeonEditor->assertAggregateConforms($aggregate, 'publication');
    }
    catch (DungeonAggregateException $e) {
      throw new \RuntimeException(sprintf('dungeon_aggregate_invalid: dungeon_id=%s version_id=%s.', $dungeon_id, $version_id), 0, $e);
    }

    $entrances = array_values(array_filter(
      (array) ($aggregate['room_placements'] ?? []),
      static fn($placement): bool => is_array($placement) && !empty($placement['is_level_entrance'])
    ));
    if (count($entrances) !== 1) {
      throw new \RuntimeException(sprintf(
        'campaign_source_entrance_ambiguous: dungeon_id=%s version_id=%s entrance_count=%d.',
        $dungeon_id,
        $version_id,
        count($entrances)
      ));
    }

    $room_versions_by_placement = [];
    $seen_placements = [];
    foreach ((array) ($aggregate['room_placements'] ?? []) as $placement) {
      if (!is_array($placement)) {
        throw new \RuntimeException('campaign_source_room_instantiation_invalid: room placement payload must be an object.');
      }
      $placement_id = trim((string) ($placement['placement_id'] ?? ''));
      if ($placement_id === '' || isset($seen_placements[$placement_id])) {
        throw new \RuntimeException(sprintf('campaign_source_placement_id_conflict: duplicate or blank placement_id "%s".', $placement_id));
      }
      $seen_placements[$placement_id] = TRUE;
      $room_versions_by_placement[$placement_id] = $this->loadPublishedRoomVersionForPlacement($placement);
    }

    return [
      'source' => $source,
      'identity_row' => $identity_row,
      'version_row' => $version_row,
      'aggregate' => $aggregate,
      'entrance_placement' => $entrances[0],
      'room_versions_by_placement' => $room_versions_by_placement,
    ];
  }

  /**
   * Load the pinned room version for one dungeon placement.
   */
  private function loadPublishedRoomVersionForPlacement(array $placement): array {
    $placement_id = trim((string) ($placement['placement_id'] ?? ''));
    $source_room_id = trim((string) ($placement['room_id'] ?? ''));
    $version_id = trim((string) ($placement['version_id'] ?? ''));
    if ($placement_id === '' || $source_room_id === '' || $version_id === '') {
      throw new \RuntimeException(sprintf('campaign_source_room_version_not_found: placement %s is missing room_id or version_id.', $placement_id !== '' ? $placement_id : 'unknown'));
    }
    $row = $this->database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['version_id', 'room_id', 'version', 'schema_version', 'room_payload', 'payload_hash', 'catalog_version', 'source', 'published_at'])
      ->condition('version_id', $version_id)
      ->condition('room_id', $source_room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf(
        'campaign_source_room_version_not_found: placement_id=%s room_id=%s version_id=%s.',
        $placement_id,
        $source_room_id,
        $version_id
      ));
    }
    try {
      $room = json_decode((string) $row['room_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \RuntimeException(sprintf(
        'campaign_source_room_instantiation_invalid: placement_id=%s pinned room payload is invalid JSON.',
        $placement_id
      ), 0, $e);
    }
    if (!is_array($room) || trim((string) ($room['room_id'] ?? '')) !== $source_room_id) {
      throw new \RuntimeException(sprintf(
        'campaign_source_room_instantiation_invalid: placement_id=%s pinned room payload does not match source room_id=%s.',
        $placement_id,
        $source_room_id
      ));
    }
    return ['row' => $row, 'room' => $room];
  }

  /**
   * Build the launch profile for an authored dungeon campaign.
   */
  private function buildPublishedDungeonCampaignProfile(array $published, string $runtime_dungeon_id): array {
    $aggregate = $published['aggregate'];
    $entrance = $published['entrance_placement'];
    $entrance_room_id = (string) $entrance['placement_id'];
    return [
      'content_profile_id' => 'published-dungeon-v1',
      'starter_profile_id' => 'published-dungeon-v1',
      'starter_source_room_id' => (string) $entrance['room_id'],
      'starter_runtime_room_id' => $entrance_room_id,
      'starter_source_dungeon_id' => (string) $published['source']['dungeon_id'],
      'starter_dungeon_name' => (string) $aggregate['name'],
      'starter_dungeon_description' => (string) $aggregate['description'],
      'connected_room_source_id' => '',
      'starter_room_tags_default' => array_values(array_map('strval', (array) ($aggregate['metadata']['tags'] ?? []))),
      'starter_location_tags' => array_values(array_map('strval', (array) ($entrance['tags'] ?? []))),
      'published_dungeon_source' => [
        'dungeon_id' => (string) $published['source']['dungeon_id'],
        'version_id' => (string) $published['source']['version_id'],
        'version' => (string) ($published['version_row']['version'] ?? ''),
      ],
      'narrative_hub_policy' => [
        'primary_room_id' => $entrance_room_id,
        'primary_contact_actor_id' => NULL,
        'quest_completion_room_id' => $entrance_room_id,
        'fallback_mode' => 'campaign_profile',
      ],
      'launch_policy' => [
        'default_active_dungeon_id' => $runtime_dungeon_id,
        'default_active_room_id' => $entrance_room_id,
        'runtime_fallback_mode' => 'campaign_profile_only',
      ],
    ];
  }

  /**
   * Resolve the campaign/runtime theme for a published dungeon source.
   */
  private function resolvePublishedDungeonTheme(array $aggregate): string {
    $metadata_theme = trim((string) ($aggregate['metadata']['theme'] ?? ''));
    if ($metadata_theme !== '') {
      return $metadata_theme;
    }
    return 'authored';
  }

  /**
   * Materialize every published room placement into campaign room authority.
   *
   * @return array{rooms:array<int,array<string,mixed>>,rooms_by_placement_id:array<string,array<string,mixed>>,footprints:array<string,array<string,array<int,string>>>,sparse_h3_rooms:array<string,array<string,mixed>>}
   *   Runtime rooms and transformed footprint lookup.
   */
  private function instantiatePublishedDungeonCampaignRooms(int $campaign_id, array $published, int $now): array {
    $rooms = [];
    $rooms_by_placement_id = [];
    $footprints = [];
    $sparse_h3_rooms = [];
    foreach ((array) ($published['aggregate']['room_placements'] ?? []) as $placement) {
      $placement_id = trim((string) ($placement['placement_id'] ?? ''));
      $source_room_id = trim((string) ($placement['room_id'] ?? ''));
      if ($placement_id === '' || isset($rooms_by_placement_id[$placement_id])) {
        throw new \RuntimeException(sprintf('campaign_source_placement_id_conflict: duplicate or blank runtime room id "%s".', $placement_id));
      }
      $existing = (int) $this->database->select('dc_campaign_rooms', 'r')
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $placement_id)
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($existing !== 0) {
        throw new \RuntimeException(sprintf('campaign_source_placement_id_conflict: campaign %d room_id %s already exists.', $campaign_id, $placement_id));
      }
      $room_version = $published['room_versions_by_placement'][$placement_id] ?? NULL;
      if (!is_array($room_version) || !is_array($room_version['room'] ?? NULL)) {
        throw new \RuntimeException(sprintf('campaign_source_room_version_not_found: placement_id=%s.', $placement_id));
      }
      $room = $room_version['room'];
      $layout = $this->buildPublishedCampaignRoomLayout($room, $placement, $room_version['row']);
      $contents = $this->buildPublishedCampaignRoomContents($room, $placement, $room_version['row']);
      $environment_tags = array_values(array_unique(array_filter(array_map(
        'strval',
        array_merge((array) ($room['metadata']['tags'] ?? []), (array) ($placement['tags'] ?? []))
      ))));
      $encoded_layout = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $encoded_contents = json_encode($contents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $encoded_tags = json_encode($environment_tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($encoded_layout) || !is_string($encoded_contents) || !is_string($encoded_tags)) {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: failed to encode placement_id=%s.', $placement_id));
      }
      $decoded_layout = json_decode($encoded_layout, TRUE, 512, JSON_THROW_ON_ERROR);
      $decoded_contents = json_decode($encoded_contents, TRUE, 512, JSON_THROW_ON_ERROR);
      $this->assertPublishedCampaignRoomPersistencePayload($placement, $room, $decoded_layout, $decoded_contents);

      $this->database->insert('dc_campaign_rooms')
        ->fields([
          'campaign_id' => $campaign_id,
          'room_id' => $placement_id,
          'name' => (string) ($room['name'] ?? $placement_id),
          'description' => (string) ($room['description'] ?? ''),
          'environment_tags' => $encoded_tags,
          'layout_data' => $encoded_layout,
          'contents_data' => $encoded_contents,
          'source_room_id' => $source_room_id,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
      $this->database->insert('dc_campaign_room_states')
        ->fields([
          'campaign_id' => $campaign_id,
          'room_id' => $placement_id,
          'is_cleared' => 0,
          'fog_state' => json_encode([
            'visibility' => 'initial',
            'discovered_hexes' => [],
            'source_kind' => 'published_dungeon',
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'last_visited' => $now,
          'updated' => $now,
        ])
        ->execute();

      $runtime_room = [
        'room_id' => $placement_id,
        'source_room_id' => $source_room_id,
        'source_room_version_id' => (string) ($placement['version_id'] ?? ''),
        'name' => (string) ($room['name'] ?? $placement_id),
        'description' => (string) ($room['description'] ?? ''),
        'hexes' => $layout['hexes'],
        'entry_points' => $layout['entry_points'],
        'exit_points' => $layout['exit_points'],
        'exits' => $layout['exits'],
        'terrain' => $layout['terrain'],
        'lighting' => $layout['lighting'],
        'metadata' => $layout['metadata'],
      ];
      $rooms[] = $runtime_room;
      $rooms_by_placement_id[$placement_id] = $runtime_room;
      $sparse_h3_rooms[$placement_id] = $this->buildPublishedCampaignSparseH3Room($placement, $room);
      foreach ((array) ($room['hexes'] ?? []) as $hex) {
        if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
          throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s has a non-integer room hex.', $placement_id));
        }
        $level_hex = RoomPlacementTransformer::toLevel(['q' => $hex['q'], 'r' => $hex['r']], $placement);
        $key = RoomPlacementTransformer::hexKey($level_hex);
        $footprints[$source_room_id][$key] ??= [];
        $footprints[$source_room_id][$key][] = $placement_id;
      }
    }

    return [
      'rooms' => $rooms,
      'rooms_by_placement_id' => $rooms_by_placement_id,
      'footprints' => $footprints,
      'sparse_h3_rooms' => $sparse_h3_rooms,
    ];
  }

  /**
   * Build transformed sparse H3 coverage for a runtime placement id.
   */
  private function buildPublishedCampaignSparseH3Room(array $placement, array $room): array {
    $placement_id = trim((string) ($placement['placement_id'] ?? ''));
    if ($placement_id === '') {
      throw new \RuntimeException('campaign_source_room_instantiation_invalid: sparse H3 placement id is blank.');
    }

    $hexes = [];
    $seen = [];
    foreach ((array) ($room['hexes'] ?? []) as $index => $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s hex[%d] has a non-integer sparse H3 coordinate.', $placement_id, $index));
      }
      $h3 = strtolower(trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? '')));
      if ($h3 === '') {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s hex[%d] is missing h3_index_res14.', $placement_id, $index));
      }
      $level_hex = RoomPlacementTransformer::toLevel(['q' => (int) $hex['q'], 'r' => (int) $hex['r']], $placement);
      $key = RoomPlacementTransformer::hexKey($level_hex);
      if (isset($seen[$key])) {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s transformed sparse H3 footprint repeats %s.', $placement_id, $key));
      }
      $seen[$key] = TRUE;
      $hexes[] = [
        'q' => (int) $level_hex['q'],
        'r' => (int) $level_hex['r'],
        'h3_index_res14' => $h3,
        'h3_index' => $h3,
        'lat' => isset($hex['lat']) && is_numeric($hex['lat']) ? (float) $hex['lat'] : NULL,
        'lng' => isset($hex['lng']) && is_numeric($hex['lng']) ? (float) $hex['lng'] : NULL,
      ];
    }
    if ($hexes === []) {
      throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s has no sparse H3 hexes.', $placement_id));
    }

    $anchor_coordinate = NULL;
    foreach ((array) ($room['entry_ports'] ?? []) as $port) {
      if (!is_array($port) || !is_array($port['hex'] ?? NULL)) {
        continue;
      }
      if (!empty($port['is_default']) || $anchor_coordinate === NULL) {
        $anchor_coordinate = RoomPlacementTransformer::toLevel([
          'q' => (int) ($port['hex']['q'] ?? 0),
          'r' => (int) ($port['hex']['r'] ?? 0),
        ], $placement);
      }
      if (!empty($port['is_default'])) {
        break;
      }
    }
    if ($anchor_coordinate === NULL) {
      $anchor_coordinate = ['q' => (int) $hexes[0]['q'], 'r' => (int) $hexes[0]['r']];
    }

    $anchor_key = RoomPlacementTransformer::hexKey($anchor_coordinate);
    $anchor_hex = NULL;
    foreach ($hexes as $hex) {
      if (RoomPlacementTransformer::hexKey($hex) === $anchor_key) {
        $anchor_hex = $hex;
        break;
      }
    }
    if ($anchor_hex === NULL) {
      $anchor_hex = $hexes[0];
      $anchor_coordinate = ['q' => (int) $anchor_hex['q'], 'r' => (int) $anchor_hex['r']];
    }

    return [
      'room_id' => $placement_id,
      'source_room_id' => (string) ($placement['room_id'] ?? ''),
      'source_room_version_id' => (string) ($placement['version_id'] ?? ''),
      'anchor' => [
        'q' => (int) $anchor_coordinate['q'],
        'r' => (int) $anchor_coordinate['r'],
        'h3_index_res14' => (string) $anchor_hex['h3_index_res14'],
        'lat' => $anchor_hex['lat'],
        'lng' => $anchor_hex['lng'],
      ],
      'hexes' => $hexes,
    ];
  }

  /**
   * Persist transformed H3 sparse rows for published-dungeon runtime rooms.
   *
   * Runtime room ids are placement ids, so transition-time projection must be
   * able to resolve sparse coverage by placement id in level-space coordinates.
   */
  private function persistPublishedCampaignSparseH3Mappings(string $runtime_dungeon_id, array $sparse_h3_rooms, int $timestamp): void {
    $runtime_dungeon_id = trim($runtime_dungeon_id);
    if ($runtime_dungeon_id === '') {
      throw new \RuntimeException('campaign_source_room_instantiation_invalid: runtime dungeon id is required for sparse H3 mappings.');
    }
    if ($sparse_h3_rooms === []) {
      throw new \RuntimeException('campaign_source_room_instantiation_invalid: published dungeon has no sparse H3 mappings.');
    }

    $schema = $this->database->schema();
    foreach (['dungeoncrawler_content_h3_room_anchors', 'dungeoncrawler_content_h3_room_cells'] as $table) {
      if (!$schema->tableExists($table)) {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: required H3 table %s is missing.', $table));
      }
    }

    $this->database->delete('dungeoncrawler_content_h3_room_cells')
      ->condition('dungeon_id', $runtime_dungeon_id)
      ->execute();
    $this->database->delete('dungeoncrawler_content_h3_room_anchors')
      ->condition('dungeon_id', $runtime_dungeon_id)
      ->execute();

    foreach ($sparse_h3_rooms as $room) {
      if (!is_array($room)) {
        throw new \RuntimeException('campaign_source_room_instantiation_invalid: sparse H3 room payload must be an array.');
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      $anchor = is_array($room['anchor'] ?? NULL) ? $room['anchor'] : [];
      $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
      if ($room_id === '' || $anchor === [] || $hexes === []) {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: sparse H3 room %s is incomplete.', $room_id !== '' ? $room_id : 'unknown'));
      }
      $anchor_h3 = strtolower(trim((string) ($anchor['h3_index_res14'] ?? '')));
      if ($anchor_h3 === '') {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: sparse H3 room %s anchor is missing h3_index_res14.', $room_id));
      }

      $anchor_metadata = [
        'status' => 'h3_index_assigned',
        'h3_index_source' => 'published_room_version',
        'normalization' => 'published_dungeon_level_space',
        'normalization_version' => 'published-dungeon-runtime-persist-v1',
        'source' => 'campaign_initialization_published_dungeon',
        'source_room_id' => (string) ($room['source_room_id'] ?? ''),
        'source_room_version_id' => (string) ($room['source_room_version_id'] ?? ''),
      ];
      $this->database->insert('dungeoncrawler_content_h3_room_anchors')
        ->fields([
          'dungeon_id' => $runtime_dungeon_id,
          'room_id' => $room_id,
          'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
          'h3_index' => $anchor_h3,
          'center_latitude' => isset($anchor['lat']) && is_numeric($anchor['lat']) ? (float) $anchor['lat'] : NULL,
          'center_longitude' => isset($anchor['lng']) && is_numeric($anchor['lng']) ? (float) $anchor['lng'] : NULL,
          'reference_q' => (int) ($anchor['q'] ?? 0),
          'reference_r' => (int) ($anchor['r'] ?? 0),
          'hex_size_meters' => H3SpatialHelper::H3_HEX_SIZE_METERS,
          'metadata' => json_encode($anchor_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'created' => $timestamp,
          'updated' => $timestamp,
        ])
        ->execute();

      foreach ($hexes as $hex_index => $hex) {
        if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
          throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: sparse H3 room %s hex[%d] has invalid q/r.', $room_id, $hex_index));
        }
        $cell_h3 = strtolower(trim((string) ($hex['h3_index_res14'] ?? '')));
        if ($cell_h3 === '') {
          throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: sparse H3 room %s hex[%d] is missing h3_index_res14.', $room_id, $hex_index));
        }
        $cell_metadata = [
          'status' => 'h3_index_assigned',
          'h3_index_source' => 'published_room_version',
          'normalization' => 'published_dungeon_level_space',
          'normalization_version' => 'published-dungeon-runtime-persist-v1',
          'source' => 'campaign_initialization_published_dungeon',
          'source_room_id' => (string) ($room['source_room_id'] ?? ''),
          'source_room_version_id' => (string) ($room['source_room_version_id'] ?? ''),
        ];
        $this->database->insert('dungeoncrawler_content_h3_room_cells')
          ->fields([
            'dungeon_id' => $runtime_dungeon_id,
            'room_id' => $room_id,
            'cell_role' => 'room_hex',
            'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
            'h3_index' => $cell_h3,
            'source_q' => (int) $hex['q'],
            'source_r' => (int) $hex['r'],
            'center_latitude' => isset($hex['lat']) && is_numeric($hex['lat']) ? (float) $hex['lat'] : NULL,
            'center_longitude' => isset($hex['lng']) && is_numeric($hex['lng']) ? (float) $hex['lng'] : NULL,
            'metadata' => json_encode($cell_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created' => $timestamp,
            'updated' => $timestamp,
          ])
          ->execute();
      }
    }
  }

  /**
   * Build runtime layout_data from an untransformed canonical room version.
   */
  private function buildPublishedCampaignRoomLayout(array $room, array $placement, array $version_row): array {
    $entry_points = array_map(static fn(array $port): array => [
      'port_id' => (string) $port['port_id'],
      'q' => (int) $port['hex']['q'],
      'r' => (int) $port['hex']['r'],
      'edge' => (int) $port['edge'],
      'label' => (string) $port['label'],
      'arrival_facing' => (int) $port['arrival_facing'],
      'is_default' => (bool) $port['is_default'],
      'tags' => array_values(array_map('strval', (array) ($port['tags'] ?? []))),
    ], (array) ($room['entry_ports'] ?? []));
    $exit_points = array_map(static fn(array $port): array => [
      'port_id' => (string) $port['port_id'],
      'q' => (int) $port['hex']['q'],
      'r' => (int) $port['hex']['r'],
      'edge' => (int) $port['edge'],
      'label' => (string) $port['label'],
      'kind' => (string) $port['kind'],
      'direction' => (string) $port['direction'],
      'default_state' => (string) $port['default_state'],
      'target_room_id' => $port['destination_hint'] ?? NULL,
      'requirements' => (array) ($port['requirements'] ?? []),
      'tags' => array_values(array_map('strval', (array) ($port['tags'] ?? []))),
    ], (array) ($room['exit_ports'] ?? []));

    $metadata = is_array($room['layout_data']['metadata'] ?? NULL) ? $room['layout_data']['metadata'] : [];
    $metadata = array_replace_recursive($metadata, is_array($room['metadata'] ?? NULL) ? $room['metadata'] : []);
    $metadata['campaign_source'] = [
      'kind' => 'published_dungeon',
      'placement_id' => (string) $placement['placement_id'],
      'source_room_id' => (string) $placement['room_id'],
      'room_version_id' => (string) $placement['version_id'],
      'room_version' => (string) ($version_row['version'] ?? ''),
    ];

    return [
      'hexes' => (array) ($room['hexes'] ?? []),
      'entry_points' => $entry_points,
      'exit_points' => $exit_points,
      'exits' => $exit_points,
      'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
      'lighting' => is_array($room['lighting'] ?? NULL) ? $room['lighting'] : [],
      'environmental_effects' => is_array($room['environmental_effects'] ?? NULL) ? $room['environmental_effects'] : [],
      'gameplay_defaults' => is_array($room['gameplay_defaults'] ?? NULL) ? $room['gameplay_defaults'] : [],
      'room_type' => (string) ($room['room_type'] ?? 'room'),
      'size_category' => (string) ($room['size_category'] ?? 'medium'),
      'metadata' => $metadata,
      'source' => 'published_dungeon',
    ];
  }

  /**
   * Build runtime contents_data from canonical room placements.
   */
  private function buildPublishedCampaignRoomContents(array $room, array $placement, array $version_row): array {
    $contents = [
      'entities' => is_array($room['placements'] ?? NULL) ? $room['placements'] : [],
      'npcs' => [],
      'creatures' => [],
      'items' => [],
      'obstacles' => [],
      'traps' => [],
      'hazards' => [],
      'interactables' => [],
      '_source' => [
        'kind' => 'published_dungeon',
        'placement_id' => (string) $placement['placement_id'],
        'source_room_id' => (string) $placement['room_id'],
        'room_version_id' => (string) $placement['version_id'],
        'room_version' => (string) ($version_row['version'] ?? ''),
      ],
    ];
    foreach ((array) ($room['placements'] ?? []) as $entity) {
      if (!is_array($entity) || !is_array($entity['definition_ref'] ?? NULL)) {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s has malformed contents placement.', (string) $placement['placement_id']));
      }
      $family = (string) ($entity['definition_ref']['family'] ?? '');
      $bucket = match ($family) {
        'actor' => 'npcs',
        'creature' => 'creatures',
        'item' => 'items',
        'obstacle' => 'obstacles',
        'trap' => 'traps',
        'hazard' => 'hazards',
        default => throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: unsupported contents family "%s".', $family)),
      };
      $contents[$bucket][] = [
        'instance_id' => (string) ($entity['instance_id'] ?? ''),
        'content_id' => (string) ($entity['definition_ref']['definition_id'] ?? ''),
        'version' => (string) ($entity['definition_ref']['version'] ?? ''),
        'position' => is_array($entity['anchor_hex'] ?? NULL) ? $entity['anchor_hex'] : [],
        'orientation' => (int) ($entity['facing'] ?? 0),
        'elevation_ft' => (int) ($entity['elevation_ft'] ?? 0),
        'state_defaults' => is_array($entity['state_defaults'] ?? NULL) ? $entity['state_defaults'] : [],
        'overrides' => is_array($entity['overrides'] ?? NULL) ? $entity['overrides'] : [],
      ];
    }
    return $contents;
  }

  /**
   * Verify authored metadata survived campaign room JSON encoding.
   */
  private function assertPublishedCampaignRoomPersistencePayload(array $placement, array $room, array $layout, array $contents): void {
    $placement_id = (string) ($placement['placement_id'] ?? '');
    if (($layout['hexes'] ?? []) === []) {
      throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s has no layout_data.hexes.', $placement_id));
    }
    if (!is_array($layout['metadata'] ?? NULL) || !is_array($layout['metadata']['campaign_source'] ?? NULL)) {
      throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s layout metadata did not persist.', $placement_id));
    }
    foreach (['tags', 'provenance'] as $required_metadata_key) {
      if (array_key_exists($required_metadata_key, (array) ($room['metadata'] ?? [])) && !array_key_exists($required_metadata_key, $layout['metadata'])) {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s dropped metadata.%s.', $placement_id, $required_metadata_key));
      }
    }
    foreach (['placement_id', 'source_room_id', 'room_version_id'] as $required_source_key) {
      if (trim((string) ($layout['metadata']['campaign_source'][$required_source_key] ?? '')) === '') {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s missing layout metadata campaign_source.%s.', $placement_id, $required_source_key));
      }
      if (trim((string) ($contents['_source'][$required_source_key] ?? '')) === '') {
        throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: placement_id=%s missing contents source.%s.', $placement_id, $required_source_key));
      }
    }
  }

  /**
   * Seed runtime campaign connectors from the published connector projection.
   */
  private function seedPublishedDungeonCampaignConnectors(int $campaign_id, string $runtime_dungeon_id, array $published, array $room_context): array {
    if (!$this->connectorDefinitionService) {
      throw new \RuntimeException('campaign_source_invalid: ConnectorDefinitionService is required for published_dungeon connector instantiation.');
    }
    $source_dungeon_id = (string) $published['source']['dungeon_id'];
    $rows = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c')
      ->condition('dungeon_id', $source_dungeon_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $connections = [];
    foreach ($rows as $row) {
      $payload = $this->buildPublishedDungeonCampaignConnectorPayload($runtime_dungeon_id, $row, $room_context['footprints']);
      $this->connectorDefinitionService->saveCampaignConnector($campaign_id, $payload);
      $connections[] = $this->buildPublishedDungeonPayloadConnection($payload, $row);
    }
    return $connections;
  }

  /**
   * Build a saveCampaignConnector payload with remapped placement room IDs.
   */
  private function buildPublishedDungeonCampaignConnectorPayload(string $runtime_dungeon_id, array $row, array $footprints): array {
    $from_h3 = strtolower(trim((string) ($row['from_h3_index_res14'] ?? '')));
    $to_h3 = strtolower(trim((string) ($row['to_h3_index_res14'] ?? '')));
    if ($from_h3 === '' || $to_h3 === '') {
      throw new \RuntimeException(sprintf('campaign_source_connector_h3_missing: connection_id=%s.', (string) ($row['connection_id'] ?? 'unknown')));
    }

    foreach (['from_hex_q', 'from_hex_r', 'to_hex_q', 'to_hex_r'] as $field) {
      if (!is_numeric($row[$field] ?? NULL)) {
        throw new \RuntimeException(sprintf('campaign_source_connector_unresolvable: connection_id=%s missing %s.', (string) ($row['connection_id'] ?? 'unknown'), $field));
      }
    }

    $from_source_room_id = trim((string) ($row['from_room_id'] ?? ''));
    $to_source_room_id = trim((string) ($row['to_room_id'] ?? ''));
    $from_hex = ['q' => (int) $row['from_hex_q'], 'r' => (int) $row['from_hex_r']];
    $to_hex = ['q' => (int) $row['to_hex_q'], 'r' => (int) $row['to_hex_r']];
    $from_placement_id = $this->resolveConnectorEndpointPlacement($from_source_room_id, $from_hex, $footprints, (string) ($row['connection_id'] ?? 'unknown'), 'from');
    $to_placement_id = $this->resolveConnectorEndpointPlacement($to_source_room_id, $to_hex, $footprints, (string) ($row['connection_id'] ?? 'unknown'), 'to');

    $payload = [
      'connection_id' => (string) ($row['connection_id'] ?? ''),
      'dungeon_id' => $runtime_dungeon_id,
      'from_room_id' => $from_placement_id,
      'to_room_id' => $to_placement_id,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'from_h3_index_res14' => $from_h3,
      'to_h3_index_res14' => $to_h3,
      'direction' => (string) ($row['direction'] ?? 'bidirectional'),
      'kind' => (string) ($row['kind'] ?? 'hallway'),
      'default_state' => (string) ($row['default_state'] ?? 'open'),
      'state' => (string) ($row['default_state'] ?? 'open'),
      'description' => isset($row['description']) ? (string) $row['description'] : '',
      'travel_cost' => max(0, (int) ($row['travel_cost'] ?? 0)),
      'is_discovered_default' => (int) ($row['is_discovered_default'] ?? 1),
    ];
    foreach (['trap_data', 'lock_data', 'requirements_data'] as $json_field) {
      $decoded = $this->decodeOptionalConnectorJsonField($row, $json_field);
      if ($decoded !== NULL) {
        $payload[$json_field] = $decoded;
      }
    }
    if ($payload['connection_id'] === '' || $payload['from_room_id'] === '' || $payload['to_room_id'] === '') {
      throw new \RuntimeException(sprintf('campaign_source_connector_unresolvable: connection_id=%s produced an incomplete remap.', (string) ($row['connection_id'] ?? 'unknown')));
    }
    return $payload;
  }

  /**
   * Resolve one connector endpoint by transformed room footprint containment.
   */
  private function resolveConnectorEndpointPlacement(string $source_room_id, array $level_hex, array $footprints, string $connection_id, string $endpoint): string {
    $source_room_id = trim($source_room_id);
    if ($source_room_id === '') {
      throw new \RuntimeException(sprintf('campaign_source_connector_unresolvable: connection_id=%s %s endpoint has no source room id.', $connection_id, $endpoint));
    }
    $key = RoomPlacementTransformer::hexKey($level_hex);
    $candidates = $footprints[$source_room_id][$key] ?? [];
    if (count($candidates) !== 1) {
      throw new \RuntimeException(sprintf(
        'campaign_source_connector_unresolvable: connection_id=%s %s endpoint room_id=%s level_hex=%s resolved %d placements.',
        $connection_id,
        $endpoint,
        $source_room_id,
        $key,
        count($candidates)
      ));
    }
    return (string) $candidates[0];
  }

  /**
   * Decode nullable connector JSON fields from canonical connector rows.
   */
  private function decodeOptionalConnectorJsonField(array $row, string $field): ?array {
    if (!array_key_exists($field, $row) || $row[$field] === NULL || $row[$field] === '') {
      return NULL;
    }
    try {
      $decoded = is_string($row[$field])
        ? json_decode($row[$field], TRUE, 512, JSON_THROW_ON_ERROR)
        : $row[$field];
    }
    catch (\JsonException $e) {
      throw new \RuntimeException(sprintf('campaign_source_connector_unresolvable: connector %s is invalid JSON.', $field), 0, $e);
    }
    if (!is_array($decoded)) {
      throw new \RuntimeException(sprintf('campaign_source_connector_unresolvable: connector %s is not JSON object/array data.', $field));
    }
    return $decoded;
  }

  /**
   * Build the runtime dungeon_data connection mirror.
   */
  private function buildPublishedDungeonPayloadConnection(array $payload, array $row): array {
    $state = (string) ($payload['state'] ?? $payload['default_state'] ?? 'open');
    return [
      'connection_id' => (string) $payload['connection_id'],
      'source_connection_id' => (string) ($row['connection_id'] ?? ''),
      'from_room' => (string) $payload['from_room_id'],
      'from_room_id' => (string) $payload['from_room_id'],
      'to_room' => (string) $payload['to_room_id'],
      'to_room_id' => (string) $payload['to_room_id'],
      'type' => (string) ($payload['kind'] ?? 'hallway'),
      'kind' => (string) ($payload['kind'] ?? 'hallway'),
      'state' => $state,
      'bidirectional' => strtolower((string) ($payload['direction'] ?? 'bidirectional')) !== 'one_way',
      'is_discovered' => !empty($payload['is_discovered_default']),
      'is_passable' => !in_array($state, ['locked', 'barred', 'collapsed', 'destroyed', 'trapped'], TRUE),
      'destination_type' => 'room',
      'destination_id' => (string) $payload['to_room_id'],
      'from_hex' => $payload['from_hex'],
      'to_hex' => $payload['to_hex'],
      'from_h3_index_res14' => (string) $payload['from_h3_index_res14'],
      'to_h3_index_res14' => (string) $payload['to_h3_index_res14'],
      'travel_cost' => (int) ($payload['travel_cost'] ?? 0),
      'description' => (string) ($payload['description'] ?? ''),
    ];
  }

  /**
   * Persist the campaign dungeon row for a published source.
   */
  private function createPublishedCampaignDungeon(int $campaign_id, string $runtime_dungeon_id, array $published, array $rooms, array $connections, int $now): void {
    $aggregate = $published['aggregate'];
    $entrance_room_id = (string) $published['entrance_placement']['placement_id'];
    $theme = $this->resolvePublishedDungeonTheme($aggregate);
    $dungeon_data = [
      'schema_version' => '1.0.0',
      'source_schema_version' => (string) ($aggregate['schema_version'] ?? ''),
      'dungeon_id' => $runtime_dungeon_id,
      'source_dungeon_id' => (string) $published['source']['dungeon_id'],
      'source_version_id' => (string) $published['source']['version_id'],
      'active_room_id' => $entrance_room_id,
      'current_room_id' => $entrance_room_id,
      'level_id' => $runtime_dungeon_id,
      'depth' => (int) ($aggregate['depth'] ?? 0),
      'theme' => $theme,
      'custom_theme' => $theme,
      'name' => (string) $aggregate['name'],
      'flavor_text' => (string) $aggregate['description'],
      'created_at' => gmdate('c', $now),
      'updated_at' => gmdate('c', $now),
      'is_persistent' => TRUE,
      'hex_map' => [
        'map_id' => $runtime_dungeon_id,
        'name' => (string) $aggregate['name'],
        'hex_size_ft' => 5,
        'orientation' => 'flat-top',
        'connections' => $connections,
        'regions' => array_values(array_map(static fn(array $region): array => [
          'region_id' => (string) ($region['region_id'] ?? ''),
          'name' => (string) ($region['name'] ?? ''),
          'description' => (string) ($region['description'] ?? ''),
          'room_ids' => array_values(array_map('strval', (array) ($region['placement_ids'] ?? []))),
          'ambient_hazard_level' => (int) ($region['ambient_hazard_level'] ?? 0),
          'environmental_effects' => (array) ($region['environmental_effects'] ?? []),
        ], (array) ($aggregate['regions'] ?? []))),
        'metadata' => [
          'created_at' => gmdate('c', $now),
          'generated_by' => 'published_dungeon',
          'source_dungeon_id' => (string) $published['source']['dungeon_id'],
          'source_version_id' => (string) $published['source']['version_id'],
          'source_version' => (string) ($published['version_row']['version'] ?? ''),
          'is_finalized' => TRUE,
          'total_rooms' => count($rooms),
          'explored_rooms' => 0,
          'exploration_percentage' => 0,
          'canonical_metadata' => is_array($aggregate['metadata'] ?? NULL) ? $aggregate['metadata'] : [],
        ],
      ],
      'rooms' => $rooms,
      'connections' => $connections,
    ];
    $encoded = json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw new \RuntimeException(sprintf('campaign_source_room_instantiation_invalid: failed to encode campaign dungeon payload for %s.', $runtime_dungeon_id));
    }
    $this->database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $runtime_dungeon_id,
        'name' => (string) $aggregate['name'],
        'description' => (string) $aggregate['description'],
        'theme' => $theme,
        'dungeon_data' => $encoded,
        'source_dungeon_id' => (string) $published['source']['dungeon_id'],
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
  }

  /**
   * Create a campaign record.
   *
   * @param int $uid
   *   Campaign owner.
   * @param string $name
   *   Campaign name.
   * @param string $theme
   *   Theme key.
   * @param string $difficulty
   *   Difficulty key.
   * @param int $now
   *   Current timestamp.
   *
   * @return int
   *   Campaign ID on success.
   */
  private function createCampaign(
    int $uid,
    string $name,
    string $theme,
    string $difficulty,
    int $now,
    ?array $profile_override = NULL
  ): int {
    $profile = $profile_override ?? $this->resolveCampaignProfile($theme);
    $default_active_room_id = trim((string) ($profile['launch_policy']['default_active_room_id'] ?? ''));
    $payload = [
      'state' => [
        'schema_version' => '1.0.0',
        'created_by' => $uid,
        'started' => FALSE,
        'progress' => [],
        'created_at' => gmdate('c', $now),
        'updated_at' => gmdate('c', $now),
        'active' => [
          'dungeon_id' => '',
          'room_id' => $default_active_room_id,
          'character_id' => 0,
        ],
        CampaignClockService::STATE_KEY => $this->campaignClockService->createClockFromTimestamp($now),
      ],
      'profile' => $profile,
      'children' => [
        'dungeon_ids' => [],
        'actor_ids' => [],
      ],
      'authority' => [
        'graph_source' => 'campaign_tables',
        'delivery_snapshot_policy' => 'projection_only',
      ],
      'state_meta' => [
        'version' => 1,
        'updatedAt' => gmdate('c', $now),
      ],
      'init' => [
        'phase' => self::INIT_PHASE_STRUCTURAL_INITIALIZING,
        'owner' => 'CampaignInitializationService',
        'version' => 1,
        'updated_at' => gmdate('c', $now),
        'context' => [
          'operation' => self::INIT_STEP_BOOTSTRAP,
        ],
      ],
    ];

    $this->campaignClockService->syncLegacyGameTime($payload['state']);

    $campaign_id = (int) $this->database->insert('dc_campaigns')
      ->fields([
        'uuid' => $this->uuid->generate(),
        'uid' => $uid,
        'name' => $name,
        'status' => 'ready',
        'theme' => $theme,
        'difficulty' => $difficulty,
        'campaign_data' => json_encode($payload, JSON_PRETTY_PRINT),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
    $this->enrollCampaignInLatencyCanaryIfEnabled($campaign_id);
    return $campaign_id;
  }

  /**
   * Auto-enroll newly created campaigns in latency canary cohort when enabled.
   */
  private function enrollCampaignInLatencyCanaryIfEnabled(int $campaign_id): void {
    if ($campaign_id <= 0 || !$this->shouldAutoEnrollNewCampaignForLatencyCanary()) {
      return;
    }
    $config = $this->configFactory->getEditable('dungeoncrawler_content.settings');
    $raw = (string) $config->get('latency_toggle_canary_campaign_ids');
    $campaign_ids = [];
    foreach (preg_split('/[\s,]+/', $raw, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $candidate) {
      $id = (int) $candidate;
      if ($id > 0) {
        $campaign_ids[$id] = TRUE;
      }
    }
    if (isset($campaign_ids[$campaign_id])) {
      return;
    }
    $campaign_ids[$campaign_id] = TRUE;
    $ids = array_keys($campaign_ids);
    sort($ids);
    $config->set('latency_toggle_canary_campaign_ids', implode(',', $ids))->save();
    $this->logger->notice('Latency canary auto-enrolled newly created campaign {campaign_id}.', [
      'campaign_id' => $campaign_id,
    ]);
  }

  /**
   * Returns whether newly created campaigns should auto-join latency canary.
   */
  private function shouldAutoEnrollNewCampaignForLatencyCanary(): bool {
    $raw = strtolower(trim((string) getenv('DC_LATENCY_AUTO_ENROLL_NEW_CAMPAIGNS')));
    if (in_array($raw, ['1', 'true', 'yes', 'on'], TRUE)) {
      return TRUE;
    }
    if (in_array($raw, ['0', 'false', 'no', 'off'], TRUE)) {
      return FALSE;
    }
    return (bool) $this->configFactory
      ->get('dungeoncrawler_content.settings')
      ->get('latency_toggle_auto_enroll_new_campaigns');
  }

  /**
   * Resolve campaign-owned content/starter profile contract for a theme.
   *
   * @return array<string, mixed>
   *   Campaign profile payload.
   */
  private function resolveCampaignProfile(string $theme): array {
    $normalized_theme = strtolower(trim($theme));
    if ($normalized_theme === '') {
      $normalized_theme = 'classic_dungeon';
    }

    if ($normalized_theme === 'undead_crypt') {
      return [
        'content_profile_id' => 'starter-undead-crypt-v1',
        'starter_profile_id' => 'starter-undead-crypt-room-40x40-v1',
        'starter_source_room_id' => self::STARTER_UNDEAD_SOURCE_ROOM_ID,
        'starter_runtime_room_id' => self::STARTER_UNDEAD_RUNTIME_ROOM_ID,
        'starter_source_dungeon_id' => 'tpl_dungeon_undead_crypt_intro',
        'starter_dungeon_name' => 'Undead Crypt',
        'starter_dungeon_description' => 'A cold crypt starter space for movement and combat iteration.',
        'connected_room_source_id' => self::STARTER_CITY_STREETS_ROOM_ID,
        'starter_room_tags_default' => ['undead', 'crypt', 'stone', 'starting_area', 'combat_testbed'],
        'starter_location_tags' => ['crypt', 'starting_area'],
        'narrative_hub_policy' => [
          'primary_room_id' => self::STARTER_UNDEAD_RUNTIME_ROOM_ID,
          'primary_contact_actor_id' => NULL,
          'quest_completion_room_id' => self::STARTER_UNDEAD_RUNTIME_ROOM_ID,
          'fallback_mode' => 'campaign_profile',
        ],
        'launch_policy' => [
          'default_active_dungeon_id' => 'campaign_starter_dungeon_primary',
          'default_active_room_id' => self::STARTER_UNDEAD_RUNTIME_ROOM_ID,
          'runtime_fallback_mode' => 'campaign_profile_only',
        ],
      ];
    }

    return [
      'content_profile_id' => 'starter-city-tavern-v1',
      'starter_profile_id' => 'starter-tavern-room-v1',
      'starter_source_room_id' => self::STARTER_DEFAULT_SOURCE_ROOM_ID,
      'starter_runtime_room_id' => self::STARTER_DEFAULT_RUNTIME_ROOM_ID,
      'starter_source_dungeon_id' => self::STARTER_LIBRARY_CONNECTOR_DUNGEON_ID,
      'starter_dungeon_name' => self::STARTER_CITY_DUNGEON_NAME,
      'starter_dungeon_description' => self::STARTER_CITY_DUNGEON_DESCRIPTION,
      'connected_room_source_id' => self::STARTER_CITY_STREETS_ROOM_ID,
      'starter_room_tags_default' => ['indoor', 'tavern', 'safe', 'starting_area'],
      'starter_location_tags' => ['tavern', 'starting_area'],
      'narrative_hub_policy' => [
        'primary_room_id' => self::STARTER_DEFAULT_RUNTIME_ROOM_ID,
        'primary_contact_actor_id' => 'npc_tavern_keeper',
        'quest_completion_room_id' => self::STARTER_DEFAULT_RUNTIME_ROOM_ID,
        'fallback_mode' => 'campaign_profile',
      ],
      'launch_policy' => [
        'default_active_dungeon_id' => 'campaign_starter_dungeon_primary',
        'default_active_room_id' => self::STARTER_DEFAULT_RUNTIME_ROOM_ID,
        'runtime_fallback_mode' => 'campaign_profile_only',
      ],
    ];
  }

  /**
   * Resolve starter blueprint used by initialization orchestration.
   *
   * @param array<string, mixed> $campaign_profile
   *   Campaign profile payload.
   *
   * @return array<string, mixed>
   *   Starter blueprint payload.
   */
  private function resolveStarterBlueprint(array $campaign_profile, string $theme): array {
    return [
      'theme' => strtolower(trim($theme)),
      'content_profile_id' => (string) ($campaign_profile['content_profile_id'] ?? ''),
      'starter_profile_id' => (string) ($campaign_profile['starter_profile_id'] ?? ''),
      'source_room_id' => (string) ($campaign_profile['starter_source_room_id'] ?? ''),
      'runtime_room_id' => (string) ($campaign_profile['starter_runtime_room_id'] ?? ''),
      'source_dungeon_id' => (string) ($campaign_profile['starter_source_dungeon_id'] ?? ''),
      'connected_room_source_id' => (string) ($campaign_profile['connected_room_source_id'] ?? ''),
      'dungeon_name' => (string) ($campaign_profile['starter_dungeon_name'] ?? ''),
      'dungeon_description' => (string) ($campaign_profile['starter_dungeon_description'] ?? ''),
      'room_tags_default' => is_array($campaign_profile['starter_room_tags_default'] ?? NULL) ? $campaign_profile['starter_room_tags_default'] : [],
      'location_tags' => is_array($campaign_profile['starter_location_tags'] ?? NULL) ? $campaign_profile['starter_location_tags'] : [],
    ];
  }

  /**
   * Enforce strict starter blueprint requirements (no implicit defaults).
   */
  private function assertStarterBlueprintContract(array $starter_blueprint): void {
    $required_fields = [
      'theme',
      'content_profile_id',
      'starter_profile_id',
      'source_room_id',
      'runtime_room_id',
      'source_dungeon_id',
      'dungeon_name',
      'dungeon_description',
    ];
    foreach ($required_fields as $field) {
      $value = trim((string) ($starter_blueprint[$field] ?? ''));
      if ($value === '') {
        throw new \RuntimeException(sprintf(
          'Starter blueprint contract violation: required field "%s" is missing or empty.',
          $field
        ));
      }
    }

    $room_tags = is_array($starter_blueprint['room_tags_default'] ?? NULL) ? $starter_blueprint['room_tags_default'] : [];
    if ($room_tags === []) {
      throw new \RuntimeException('Starter blueprint contract violation: room_tags_default must be a non-empty array.');
    }

    if (
      strtolower((string) $starter_blueprint['theme']) === 'undead_crypt'
      && trim((string) ($starter_blueprint['connected_room_source_id'] ?? '')) === ''
    ) {
      throw new \RuntimeException('Starter blueprint contract violation: connected_room_source_id is required for undead_crypt starter graph readiness.');
    }
  }

  /**
   * Persist campaign-level active pointers after starter bootstrap.
   */
  private function persistCampaignActiveState(
    int $campaign_id,
    string $dungeon_id,
    string $starter_room_id,
    int $timestamp
  ): void {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['campaign_data'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($campaign)) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: campaign %d missing during active-state persistence.',
        $campaign_id
      ));
    }

    $campaign_data = json_decode((string) ($campaign['campaign_data'] ?? '{}'), TRUE);
    if (!is_array($campaign_data)) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: campaign %d campaign_data is invalid JSON during active-state persistence.',
        $campaign_id
      ));
    }

    $campaign_data['state'] = is_array($campaign_data['state'] ?? NULL) ? $campaign_data['state'] : [];
    $campaign_data['state']['active'] = [
      'dungeon_id' => trim($dungeon_id),
      'room_id' => trim($starter_room_id),
      'character_id' => 0,
    ];
    $campaign_data['state']['updated_at'] = gmdate('c', $timestamp);
    $campaign_data['children'] = is_array($campaign_data['children'] ?? NULL) ? $campaign_data['children'] : [];
    $campaign_data['children']['dungeon_ids'] = array_values(array_unique(array_filter(array_map(
      static fn($id): string => trim((string) $id),
      array_merge((array) ($campaign_data['children']['dungeon_ids'] ?? []), [trim($dungeon_id)])
    ))));

    $encoded = json_encode($campaign_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: failed to encode campaign_data for campaign %d active-state persistence.',
        $campaign_id
      ));
    }

    $updated = (int) $this->database->update('dc_campaigns')
      ->fields([
        'campaign_data' => $encoded,
        'changed' => $timestamp,
      ])
      ->condition('id', $campaign_id)
      ->execute();
    if ($updated !== 1) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: active-state persistence affected %d rows for campaign %d.',
        $updated,
        $campaign_id
      ));
    }
  }

  /**
   * Resolve a usable campaign name from user input or the local generator.
   */
  private function resolveCampaignName(string $name, string $theme, int $uid, int $now): string {
    $trimmed = trim($name);
    if ($trimmed !== '') {
      return $trimmed;
    }

    $seed = abs(crc32($uid . ':' . $theme . ':' . $now));
    return $this->campaignNameGenerator->generate($theme, $seed);
  }

  /**
   * Persist authoritative initialization phase metadata.
   */
  private function persistCampaignInitPhase(int $campaign_id, string $phase, array $context, int $timestamp): void {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'campaign_data'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($campaign)) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: campaign %d missing during init phase persistence.',
        $campaign_id
      ));
    }

    $campaign_data = json_decode((string) ($campaign['campaign_data'] ?? '{}'), TRUE);
    if (!is_array($campaign_data)) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: campaign %d campaign_data is not valid JSON.',
        $campaign_id
      ));
    }

    $campaign_data['init'] = is_array($campaign_data['init'] ?? NULL) ? $campaign_data['init'] : [];
    $campaign_data['init']['phase'] = $phase;
    $campaign_data['init']['owner'] = 'CampaignInitializationService';
    $campaign_data['init']['version'] = (int) ($campaign_data['init']['version'] ?? 0) + 1;
    $campaign_data['init']['updated_at'] = gmdate('c', $timestamp);
    $campaign_data['init']['context'] = array_replace(
      is_array($campaign_data['init']['context'] ?? NULL) ? $campaign_data['init']['context'] : [],
      $context
    );

    $encoded = json_encode($campaign_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: failed encoding campaign_data for campaign %d.',
        $campaign_id
      ));
    }

    $updated = (int) $this->database->update('dc_campaigns')
      ->fields([
        'campaign_data' => $encoded,
        'changed' => $timestamp,
      ])
      ->condition('id', $campaign_id)
      ->execute();
    if ($updated !== 1) {
      throw new \RuntimeException(sprintf(
        'Campaign initialization contract violation: init phase update affected %d rows for campaign %d.',
        $updated,
        $campaign_id
      ));
    }
  }

  /**
   * Create a starter dungeon for the campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $theme
   *   Theme key.
   * @param int $now
   *   Current timestamp.
   *
   * @return string|FALSE
   *   Dungeon ID on success, FALSE on failure.
   */
  private function createStarterDungeon(
    int $campaign_id,
    string $theme,
    int $now,
    array $starter_room,
    array $starter_blueprint = []
  ): string|FALSE {
    $runtime_room_id = trim((string) ($starter_room['runtime_room_id'] ?? ''));
    $layout_data = is_array($starter_room['layout_data'] ?? NULL) ? $starter_room['layout_data'] : [];

    if ($runtime_room_id === '' || empty($layout_data['hexes']) || empty($starter_room['contents_data']['npcs'])) {
      $this->logger->error('Starter room asset is incomplete; refusing to synthesize a dungeon from partial data.');
      return FALSE;
    }

    $dungeon_id = $this->uuid->generate();
    $level_id = $this->uuid->generate();
    $room_name = (string) ($starter_room['name'] ?? 'The Gilded Tankard');
    $room_description = (string) ($starter_room['description'] ?? 'Starter tavern asset.');
    $dungeon_name = trim((string) ($starter_blueprint['dungeon_name'] ?? '')) ?: self::STARTER_CITY_DUNGEON_NAME;
    $dungeon_description = trim((string) ($starter_blueprint['dungeon_description'] ?? '')) ?: self::STARTER_CITY_DUNGEON_DESCRIPTION;
    $dungeon_theme = $theme !== '' ? $theme : 'starter_asset';
    $room_payload = [
      'room_id' => $runtime_room_id,
      'source_room_id' => (string) ($starter_room['room_id'] ?? $runtime_room_id),
      'name' => $room_name,
      'description' => $room_description,
      'hexes' => is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [],
      'entry_points' => is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [],
      'exit_points' => is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [],
      'exits' => is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [],
      'terrain' => is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : [],
      'lighting' => is_array($layout_data['lighting'] ?? NULL) ? $layout_data['lighting'] : [],
    ];
    $room_payload = $this->requireStarterRoomHexH3Indexes($dungeon_id, $room_payload);
    $connected_room_payload = NULL;
    $connected_room_id = trim((string) ($starter_blueprint['connected_room_source_id'] ?? ''));
    if ($connected_room_id !== '') {
      $starter_connected_room = $this->loadStarterConnectedRoomSeed($connected_room_id);
      if (!is_array($starter_connected_room)) {
        $this->logger->error('Configured connected starter room asset {room_id} is missing; refusing starter dungeon synthesis.', [
          'room_id' => $connected_room_id,
        ]);
        return FALSE;
      }
      $connected_layout_data = is_array($starter_connected_room['layout_data'] ?? NULL) ? $starter_connected_room['layout_data'] : [];
      if (empty($connected_layout_data['hexes'])) {
        throw new \RuntimeException(sprintf(
          'Connected starter room asset %s is incomplete; hexes are required.',
          $connected_room_id
        ));
      }
      $connected_room_payload = [
        'room_id' => (string) ($starter_connected_room['room_id'] ?? $connected_room_id),
        'source_room_id' => (string) ($starter_connected_room['source_room_id'] ?? $connected_room_id),
        'name' => (string) ($starter_connected_room['name'] ?? $connected_room_id),
        'description' => (string) ($starter_connected_room['description'] ?? ''),
        'hexes' => is_array($connected_layout_data['hexes'] ?? NULL) ? $connected_layout_data['hexes'] : [],
        'entry_points' => is_array($connected_layout_data['entry_points'] ?? NULL) ? $connected_layout_data['entry_points'] : [],
        'exit_points' => is_array($connected_layout_data['exit_points'] ?? NULL) ? $connected_layout_data['exit_points'] : [],
        'exits' => is_array($connected_layout_data['exits'] ?? NULL) ? $connected_layout_data['exits'] : [],
        'terrain' => is_array($connected_layout_data['terrain'] ?? NULL) ? $connected_layout_data['terrain'] : [],
        'lighting' => is_array($connected_layout_data['lighting'] ?? NULL) ? $connected_layout_data['lighting'] : [],
        'room_type' => (string) ($connected_layout_data['room_type'] ?? 'connected_room'),
      ];
      $connected_room_payload = $this->requireStarterRoomHexH3Indexes($dungeon_id, $connected_room_payload);
    }
    $region_room_ids = [$runtime_room_id];
    $connections = [];
    if (is_array($connected_room_payload)) {
      $connected_runtime_room_id = trim((string) ($connected_room_payload['room_id'] ?? ''));
      if ($connected_runtime_room_id !== '') {
        $region_room_ids[] = $connected_runtime_room_id;
      }
      $connections = $this->buildStarterCanonicalConnections($runtime_room_id, $connected_runtime_room_id);
    }

    $dungeon_data = [
      'schema_version' => '1.0.0',
      'active_room_id' => $runtime_room_id,
      'current_room_id' => $runtime_room_id,
      'level_id' => $level_id,
      'depth' => 1,
      'theme' => 'starter_asset',
      'custom_theme' => $dungeon_theme,
      'name' => $dungeon_name,
      'flavor_text' => $dungeon_description,
      'created_at' => gmdate('c', $now),
      'updated_at' => gmdate('c', $now),
      'is_persistent' => TRUE,
      'hex_map' => [
        'map_id' => $dungeon_id,
        'name' => $dungeon_name,
        'hex_size_ft' => 5,
        'orientation' => 'flat-top',
        'connections' => $connections,
        'regions' => [
          [
            'region_id' => 'starter-region',
            'name' => $dungeon_name,
            'description' => $dungeon_description,
            'room_ids' => $region_room_ids,
            'ambient_hazard_level' => 0,
          ],
        ],
        'metadata' => [
          'created_at' => gmdate('c', $now),
          'generated_by' => 'asset-library',
          'is_finalized' => TRUE,
          'total_rooms' => count($region_room_ids),
          'explored_rooms' => 0,
          'exploration_percentage' => 0,
        ],
      ],
      'rooms' => array_values(array_filter([$room_payload, $connected_room_payload], 'is_array')),
    ];

    $this->database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'name' => $dungeon_name,
        'description' => $dungeon_description,
        'theme' => $dungeon_theme,
        'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'source_dungeon_id' => trim((string) ($starter_blueprint['source_dungeon_id'] ?? '')),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
    $this->persistStarterDungeonSparseH3Mappings($dungeon_id, $dungeon_data, $now);

    return $dungeon_id;
  }

  /**
   * Build starter payload connection rows from canonical connector authority.
   *
   * @return array<int, array<string, mixed>>
   *   Canonical starter connections for payload mirroring.
   */
  private function buildStarterCanonicalConnections(string $starter_room_id, string $connected_room_id = self::STARTER_CITY_STREETS_ROOM_ID): array {
    $starter_room_id = trim($starter_room_id);
    $connected_room_id = trim($connected_room_id);
    if ($starter_room_id === '') {
      throw new \RuntimeException('Starter dungeon contract violation: starter room id is required for canonical starter connections.');
    }
    if ($connected_room_id === '') {
      throw new \RuntimeException('Starter dungeon contract violation: connected room id is required for canonical starter connections.');
    }

    $canonical_match = $this->resolveCanonicalStarterConnector($starter_room_id, $connected_room_id);
    return [[
      'connection_id' => (string) ($canonical_match['connection_id'] ?? ''),
      'from_room' => (string) ($canonical_match['from_room_id'] ?? $starter_room_id),
      'from_room_id' => (string) ($canonical_match['from_room_id'] ?? $starter_room_id),
      'to_room' => (string) ($canonical_match['to_room_id'] ?? $connected_room_id),
      'to_room_id' => (string) ($canonical_match['to_room_id'] ?? $connected_room_id),
      'type' => (string) ($canonical_match['kind'] ?? 'hallway'),
      'kind' => (string) ($canonical_match['kind'] ?? 'hallway'),
      'state' => (string) ($canonical_match['state'] ?? $canonical_match['default_state'] ?? 'open'),
      'bidirectional' => strtolower((string) ($canonical_match['direction'] ?? 'bidirectional')) !== 'one_way',
      'is_discovered' => !empty($canonical_match['is_discovered_default']) || !empty($canonical_match['is_discovered']),
      'is_passable' => strtolower((string) ($canonical_match['state'] ?? $canonical_match['default_state'] ?? 'open')) === 'open',
      'destination_type' => 'room',
      'destination_id' => (string) ($canonical_match['to_room_id'] ?? $connected_room_id),
      'from_hex' => is_array($canonical_match['from_hex'] ?? NULL) ? $canonical_match['from_hex'] : NULL,
      'to_hex' => is_array($canonical_match['to_hex'] ?? NULL) ? $canonical_match['to_hex'] : NULL,
    ]];
  }

  /**
   * Seed authoritative connector rows for starter navigation.
   *
   * Runtime transition validation is DB-authoritative via connector tables.
   * Starter campaigns must persist both:
   * - campaign-scoped connectors for the runtime dungeon id, and
   * - canonical starter-library connectors for asset-library-starter-room.
   */
  private function seedStarterConnectorAuthority(int $campaign_id, string $runtime_dungeon_id, string $starter_room_id): void {
    if ($campaign_id <= 0 || trim($runtime_dungeon_id) === '' || trim($starter_room_id) === '') {
      throw new \RuntimeException('Starter connector authority contract violation: campaign_id, runtime_dungeon_id, and starter_room_id are required.');
    }
    if (!$this->connectorDefinitionService) {
      throw new \RuntimeException('Starter connector authority contract violation: ConnectorDefinitionService is required.');
    }

    $dungeon_payload_row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $runtime_dungeon_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    $dungeon_row_id = (int) ($dungeon_payload_row['id'] ?? 0);
    $dungeon_payload = (string) ($dungeon_payload_row['dungeon_data'] ?? '');
    $dungeon_data = json_decode($dungeon_payload, TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Starter connector authority contract violation: campaign %d dungeon %s payload is invalid JSON.',
        $campaign_id,
        $runtime_dungeon_id
      ));
    }

    $starter_connections = [];
    foreach ([
      $dungeon_data['hex_map']['connections'] ?? [],
      $dungeon_data['connections'] ?? [],
    ] as $connection_bucket) {
      foreach (array_values(array_filter(is_array($connection_bucket) ? $connection_bucket : [], 'is_array')) as $connection) {
        $from_room_id = trim((string) ($connection['from_room'] ?? $connection['from_room_id'] ?? ''));
        $to_room_id = trim((string) ($connection['to_room'] ?? $connection['to_room_id'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '') {
          continue;
        }
        if (
          !(
            ($from_room_id === $starter_room_id && $to_room_id === self::STARTER_CITY_STREETS_ROOM_ID)
            || ($from_room_id === self::STARTER_CITY_STREETS_ROOM_ID && $to_room_id === $starter_room_id)
          )
        ) {
          continue;
        }
        $starter_connections[] = [
          'from_room_id' => $from_room_id,
          'to_room_id' => $to_room_id,
          'connection_id' => trim((string) ($connection['connection_id'] ?? '')),
        ];
      }
    }
    if ($starter_connections === []) {
      throw new \RuntimeException(sprintf(
        'Starter connector authority contract violation: campaign %d dungeon %s has no starter-room connector %s <-> %s in dungeon_data.',
        $campaign_id,
        $runtime_dungeon_id,
        $starter_room_id,
        self::STARTER_CITY_STREETS_ROOM_ID
      ));
    }

    foreach ($starter_connections as $starter_connection) {
      $from_room_id = (string) $starter_connection['from_room_id'];
      $to_room_id = (string) $starter_connection['to_room_id'];
      $canonical_match = $this->resolveCanonicalStarterConnector($from_room_id, $to_room_id);

      $connection_id = (string) ($starter_connection['connection_id'] ?? '');
      if ($connection_id === '') {
        $connection_id = (string) ($canonical_match['connection_id'] ?? '');
      }
      if ($connection_id === '') {
        $connection_id = sprintf(
          '%s::%s::%s::%s',
          $runtime_dungeon_id,
          $from_room_id,
          $to_room_id,
          (string) ($canonical_match['kind'] ?? 'hallway')
        );
      }

      $connector_payload = [
        'connection_id' => $connection_id,
        'from_room_id' => $from_room_id,
        'to_room_id' => $to_room_id,
        'kind' => (string) ($canonical_match['kind'] ?? 'hallway'),
        'direction' => (string) ($canonical_match['direction'] ?? 'bidirectional'),
        'default_state' => (string) ($canonical_match['default_state'] ?? 'open'),
        'state' => (string) ($canonical_match['state'] ?? $canonical_match['default_state'] ?? 'open'),
        'travel_cost' => max(0, (int) ($canonical_match['travel_cost'] ?? 1)),
        'description' => (string) ($canonical_match['description'] ?? ''),
        'is_discovered_default' => !empty($canonical_match['is_discovered_default']) || !empty($canonical_match['is_discovered']) ? 1 : 0,
        'from_hex' => is_array($canonical_match['from_hex'] ?? NULL) ? $canonical_match['from_hex'] : NULL,
        'to_hex' => is_array($canonical_match['to_hex'] ?? NULL) ? $canonical_match['to_hex'] : NULL,
      ];
      if (!is_array($connector_payload['from_hex']) || !is_array($connector_payload['to_hex'])) {
        throw new \RuntimeException(sprintf(
          'Starter connector authority contract violation: endpoint hexes unresolved for %s -> %s in campaign %d dungeon %s.',
          $from_room_id,
          $to_room_id,
          $campaign_id,
          $runtime_dungeon_id
        ));
      }

      $starter_library_connector_payload = $connector_payload;
      unset($starter_library_connector_payload['connection_id']);
      $this->connectorDefinitionService->saveCanonicalConnector($starter_library_connector_payload + [
        'dungeon_id' => self::STARTER_LIBRARY_CONNECTOR_DUNGEON_ID,
      ]);

      $this->connectorDefinitionService->saveCampaignConnector($campaign_id, $connector_payload + [
        'dungeon_id' => $runtime_dungeon_id,
      ]);
    }

    // Template-instantiation contract: room instantiation must also instantiate
    // connector rows immediately in campaign authority. Expand the starter
    // streets neighborhood inside bootstrap so newly created campaigns have
    // room rows + connector rows in one transaction.
    $this->expandStarterCityNeighborhoodFromTemplateInstantiation(
      $campaign_id,
      $runtime_dungeon_id,
      $dungeon_row_id,
      $dungeon_data,
      $starter_room_id
    );
  }

  /**
   * Materialize starter city neighborhood room+connector authority at bootstrap.
   *
   * @param array<string, mixed> $dungeon_data
   *   Mutable runtime dungeon payload for starter campaign.
   */
  private function expandStarterCityNeighborhoodFromTemplateInstantiation(
    int $campaign_id,
    string $runtime_dungeon_id,
    int $dungeon_row_id,
    array &$dungeon_data,
    string $starter_room_id
  ): void {
    if ($campaign_id <= 0 || trim($runtime_dungeon_id) === '' || $dungeon_row_id <= 0) {
      throw new \RuntimeException('Template room+connector instantiation contract violation: campaign_id, runtime_dungeon_id, and dungeon row id are required.');
    }
    if (!$this->navigationRuntime) {
      throw new \RuntimeException('Template room+connector instantiation contract violation: NavigationRuntimeService is required.');
    }
    if (trim((string) ($dungeon_data['dungeon_id'] ?? '')) === '') {
      $dungeon_data['dungeon_id'] = $runtime_dungeon_id;
    }
    if (!isset($dungeon_data['hex_map']) || !is_array($dungeon_data['hex_map'])) {
      $dungeon_data['hex_map'] = [];
    }
    if (trim((string) ($dungeon_data['hex_map']['map_id'] ?? '')) === '') {
      $dungeon_data['hex_map']['map_id'] = $runtime_dungeon_id;
    }
    $rooms_before = count((array) ($dungeon_data['rooms'] ?? []));
    $connections_before = $this->countPayloadConnectionRows($dungeon_data);

    $this->navigationRuntime->expandCanonicalRoomNeighborhood(
      $campaign_id,
      $dungeon_data,
      self::STARTER_CITY_STREETS_ROOM_ID,
      1
    );

    $rooms_after = count((array) ($dungeon_data['rooms'] ?? []));
    $connections_after = $this->countPayloadConnectionRows($dungeon_data);
    if ($rooms_after < $rooms_before || $connections_after < $connections_before) {
      throw new \RuntimeException(sprintf(
        'Template room+connector instantiation contract violation: expansion regressed graph state for campaign %d dungeon %s (rooms %d→%d, connections %d→%d).',
        $campaign_id,
        $runtime_dungeon_id,
        $rooms_before,
        $rooms_after,
        $connections_before,
        $connections_after
      ));
    }
    if ($rooms_after === $rooms_before && $connections_after === $connections_before) {
      $this->logger->notice(
        'Starter neighborhood expansion was already satisfied for campaign {campaign_id} dungeon {dungeon_id} (rooms={rooms}, connections={connections}).',
        [
          'campaign_id' => $campaign_id,
          'dungeon_id' => $runtime_dungeon_id,
          'rooms' => $rooms_after,
          'connections' => $connections_after,
        ]
      );
    }

    $this->trimStarterBootstrapSnapshot($dungeon_data, $starter_room_id);

    $encoded = json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === FALSE) {
      throw new \RuntimeException(sprintf(
        'Template room+connector instantiation contract violation: unable to encode expanded dungeon payload for campaign %d dungeon %s.',
        $campaign_id,
        $runtime_dungeon_id
      ));
    }
    $now = $this->time->getRequestTime();
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => $encoded,
        'updated' => $now,
      ])
      ->condition('id', $dungeon_row_id)
      ->execute();
  }

  /**
   * Trim starter bootstrap dungeon snapshot to starter scope.
   *
   * Campaign authority rows (dc_campaign_rooms/dc_campaign_connections) remain
   * fully materialized. This only keeps the delivery snapshot lightweight so
   * initial hexmap loads don't hydrate the entire city payload at once.
   *
   * @param array<string,mixed> $dungeon_data
   *   Mutable runtime dungeon payload.
   */
  private function trimStarterBootstrapSnapshot(array &$dungeon_data, string $starter_room_id): void {
    $starter_room_id = trim($starter_room_id);
    if ($starter_room_id === '') {
      throw new \RuntimeException('Starter snapshot trim contract violation: starter_room_id is required.');
    }
    $keep_room_ids = [
      $starter_room_id => TRUE,
      self::STARTER_CITY_STREETS_ROOM_ID => TRUE,
    ];

    $filter_rooms = static function (array $rooms, array $keep): array {
      return array_values(array_filter($rooms, static function ($room) use ($keep): bool {
        if (!is_array($room)) {
          return FALSE;
        }
        $room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
        return $room_id !== '' && isset($keep[$room_id]);
      }));
    };

    $filter_connections = static function (array $connections, array $keep): array {
      return array_values(array_filter($connections, static function ($connection) use ($keep): bool {
        if (!is_array($connection)) {
          return FALSE;
        }
        $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
        $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
        return $from_room_id !== '' && $to_room_id !== ''
          && isset($keep[$from_room_id]) && isset($keep[$to_room_id]);
      }));
    };

    $dungeon_data['rooms'] = $filter_rooms((array) ($dungeon_data['rooms'] ?? []), $keep_room_ids);

    if (!isset($dungeon_data['hex_map']) || !is_array($dungeon_data['hex_map'])) {
      $dungeon_data['hex_map'] = [];
    }
    $dungeon_data['hex_map']['rooms'] = $filter_rooms((array) ($dungeon_data['hex_map']['rooms'] ?? []), $keep_room_ids);
    $dungeon_data['hex_map']['connections'] = $filter_connections((array) ($dungeon_data['hex_map']['connections'] ?? []), $keep_room_ids);
    if (isset($dungeon_data['connections']) && is_array($dungeon_data['connections'])) {
      $dungeon_data['connections'] = $filter_connections((array) $dungeon_data['connections'], $keep_room_ids);
    }
    if (!isset($dungeon_data['hex_map']['metadata']) || !is_array($dungeon_data['hex_map']['metadata'])) {
      $dungeon_data['hex_map']['metadata'] = [];
    }
    $dungeon_data['hex_map']['metadata']['total_rooms'] = count($dungeon_data['hex_map']['rooms']);
  }

  /**
   * Count unique connection rows across runtime payload connection buckets.
   */
  private function countPayloadConnectionRows(array $dungeon_data): int {
    $seen = [];
    foreach ([
      $dungeon_data['hex_map']['connections'] ?? [],
      $dungeon_data['connections'] ?? [],
    ] as $connection_bucket) {
      foreach (array_values(array_filter(is_array($connection_bucket) ? $connection_bucket : [], 'is_array')) as $connection) {
        $connection_id = trim((string) ($connection['connection_id'] ?? ''));
        if ($connection_id !== '') {
          $seen['id:' . $connection_id] = TRUE;
          continue;
        }
        $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
        $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '') {
          continue;
        }
        $seen['edge:' . $from_room_id . '>' . $to_room_id] = TRUE;
      }
    }
    return count($seen);
  }

  /**
   * Resolve the canonical starter connector payload for a room pair.
   */
  private function resolveCanonicalStarterConnector(string $from_room_id, string $to_room_id): array {
    if (!$this->connectorDefinitionService) {
      throw new \RuntimeException('Starter connector authority contract violation: ConnectorDefinitionService is required.');
    }

    $canonical_connectors = $this->connectorDefinitionService->loadCanonicalConnectorsForDungeon(self::STARTER_CANONICAL_CONNECTOR_DUNGEON_ID);
    if ($canonical_connectors === []) {
      throw new \RuntimeException(sprintf(
        'Starter connector authority contract violation: canonical connector table is empty for %s.',
        self::STARTER_CANONICAL_CONNECTOR_DUNGEON_ID
      ));
    }

    $canonical_match = $this->matchCanonicalStarterConnector($canonical_connectors, $from_room_id, $to_room_id);
    if ($canonical_match === NULL) {
      throw new \RuntimeException(sprintf(
        'Starter connector authority contract violation: canonical connector missing for %s <-> %s in %s.',
        $from_room_id,
        $to_room_id,
        self::STARTER_CANONICAL_CONNECTOR_DUNGEON_ID
      ));
    }

    return $canonical_match;
  }

  /**
   * Resolve canonical starter connector payload and normalize endpoint direction.
   */
  private function matchCanonicalStarterConnector(array $canonical_connectors, string $from_room_id, string $to_room_id): ?array {
    $from_room_id = trim($from_room_id);
    $to_room_id = trim($to_room_id);
    if ($from_room_id === '' || $to_room_id === '') {
      return NULL;
    }
    foreach ($canonical_connectors as $connector) {
      if (!is_array($connector)) {
        continue;
      }
      $canonical_from = trim((string) ($connector['from_room_id'] ?? ''));
      $canonical_to = trim((string) ($connector['to_room_id'] ?? ''));
      if ($canonical_from === $from_room_id && $canonical_to === $to_room_id) {
        return $connector;
      }
      if ($canonical_from === $to_room_id && $canonical_to === $from_room_id) {
        $swapped = $connector;
        $swapped['from_room_id'] = $from_room_id;
        $swapped['to_room_id'] = $to_room_id;
        $from_hex = is_array($connector['from_hex'] ?? NULL) ? $connector['from_hex'] : NULL;
        $to_hex = is_array($connector['to_hex'] ?? NULL) ? $connector['to_hex'] : NULL;
        $swapped['from_hex'] = $to_hex;
        $swapped['to_hex'] = $from_hex;
        return $swapped;
      }
    }

    return NULL;
  }

  /**
   * Require starter room payload hexes to include canonical Res14 H3 indexes.
   *
   * Starter template instantiation must copy fixed spatial data and never
   * compute H3 at runtime.
   *
   * @param array<string, mixed> $room
   *   Starter room payload with a hexes array.
   *
   * @return array<string, mixed>
   *   Room payload with normalized lowercase h3_index_res14/h3_index values.
   */
  private function requireStarterRoomHexH3Indexes(string $dungeon_id, array $room): array {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf(
        'H3 fixed-data contract violation: starter dungeon %s room %s has no hexes.',
        $dungeon_id,
        $room_id !== '' ? $room_id : 'unknown'
      ));
    }

    foreach ($hexes as $hex_index => &$hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf(
          'H3 fixed-data contract violation: starter dungeon %s room %s hex[%d] must include numeric q/r.',
          $dungeon_id,
          $room_id !== '' ? $room_id : 'unknown',
          $hex_index
        ));
      }
      $h3_index = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($h3_index === '') {
        throw new \RuntimeException(sprintf(
          'H3 fixed-data contract violation: starter dungeon %s room %s hex[%d] is missing h3_index_res14/h3_index.',
          $dungeon_id,
          $room_id !== '' ? $room_id : 'unknown',
          $hex_index
        ));
      }
      $normalized_h3 = strtolower($h3_index);
      $hex['h3_index_res14'] = $normalized_h3;
      if (trim((string) ($hex['h3_index'] ?? '')) === '') {
        $hex['h3_index'] = $normalized_h3;
      }
      elseif (strtolower((string) $hex['h3_index']) !== $normalized_h3) {
        $hex['h3_index'] = $normalized_h3;
      }
    }
    unset($hex);

    $room['hexes'] = $hexes;
    return $room;
  }

  /**
   * Persist sparse H3 anchor/cell rows for starter dungeon payloads.
   */
  private function persistStarterDungeonSparseH3Mappings(string $dungeon_id, array $dungeon_data, int $timestamp): void {
    $schema = $this->database->schema();
    foreach (['dungeoncrawler_content_h3_room_anchors', 'dungeoncrawler_content_h3_room_cells'] as $table) {
      if (!$schema->tableExists($table)) {
        throw new \RuntimeException(sprintf('H3 system-of-record contract violation: required table %s is missing.', $table));
      }
    }

    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    if ($rooms === []) {
      throw new \RuntimeException(sprintf('H3 system-of-record contract violation: starter dungeon %s has no rooms for sparse mapping persistence.', $dungeon_id));
    }

    $this->database->delete('dungeoncrawler_content_h3_room_cells')
      ->condition('dungeon_id', $dungeon_id)
      ->execute();
    $this->database->delete('dungeoncrawler_content_h3_room_anchors')
      ->condition('dungeon_id', $dungeon_id)
      ->execute();

    foreach ($rooms as $room_index => $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \RuntimeException(sprintf('H3 system-of-record contract violation: starter dungeon %s room[%d] is missing room_id.', $dungeon_id, $room_index));
      }

      $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
      if ($hexes === []) {
        throw new \RuntimeException(sprintf('H3 system-of-record contract violation: starter dungeon %s room %s has no hexes for sparse mapping persistence.', $dungeon_id, $room_id));
      }

      $entry_coordinate = $this->resolveStarterRoomEntryCoordinate($room, $dungeon_id, $room_id);
      $entry_hex = $this->findStarterRoomHexByCoordinate($hexes, $entry_coordinate['q'], $entry_coordinate['r']);
      if (!is_array($entry_hex)) {
        $entry_hex = $hexes[0] ?? NULL;
      }
      if (!is_array($entry_hex)) {
        throw new \RuntimeException(sprintf(
          'H3 fixed-data contract violation: starter dungeon %s room %s cannot resolve anchor hex.',
          $dungeon_id,
          $room_id
        ));
      }
      $anchor_h3 = trim((string) ($entry_hex['h3_index_res14'] ?? $entry_hex['h3_index'] ?? ''));
      if ($anchor_h3 === '') {
        throw new \RuntimeException(sprintf(
          'H3 fixed-data contract violation: starter dungeon %s room %s anchor hex is missing h3_index_res14/h3_index.',
          $dungeon_id,
          $room_id
        ));
      }
      $entry_latlng = [
        'latitude' => is_numeric($entry_hex['lat'] ?? NULL) ? (float) $entry_hex['lat'] : NULL,
        'longitude' => is_numeric($entry_hex['lng'] ?? NULL) ? (float) $entry_hex['lng'] : NULL,
      ];

      $anchor_metadata = [
        'status' => 'h3_index_assigned',
        'h3_index_source' => 'libh3',
        'normalization' => 'global_non_overlapping_axial',
        'normalization_version' => 'starter-runtime-persist-v1',
        'global_offset_q' => 0,
        'global_offset_r' => 0,
        'room_entrance_global_q' => $entry_coordinate['q'],
        'room_entrance_global_r' => $entry_coordinate['r'],
        'source' => 'campaign_initialization_starter_room',
      ];

      $this->database->insert('dungeoncrawler_content_h3_room_anchors')
        ->fields([
          'dungeon_id' => $dungeon_id,
          'room_id' => $room_id,
          'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
          'h3_index' => strtolower($anchor_h3),
          'center_latitude' => $entry_latlng['latitude'],
          'center_longitude' => $entry_latlng['longitude'],
          'reference_q' => $entry_coordinate['q'],
          'reference_r' => $entry_coordinate['r'],
          'hex_size_meters' => H3SpatialHelper::H3_HEX_SIZE_METERS,
          'metadata' => json_encode($anchor_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'created' => $timestamp,
          'updated' => $timestamp,
        ])
        ->execute();

      $seen_coordinate_keys = [];
      foreach ($hexes as $hex_index => $hex) {
        if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
          throw new \RuntimeException(sprintf('H3 system-of-record contract violation: starter dungeon %s room %s hex[%d] must include numeric q/r.', $dungeon_id, $room_id, $hex_index));
        }
        $q = (int) $hex['q'];
        $r = (int) $hex['r'];
        $coordinate_key = $q . ':' . $r;
        if (isset($seen_coordinate_keys[$coordinate_key])) {
          throw new \RuntimeException(sprintf(
            'H3 system-of-record contract violation: starter dungeon %s room %s repeats source coordinate %s at hex[%d] and hex[%d].',
            $dungeon_id,
            $room_id,
            $coordinate_key,
            $seen_coordinate_keys[$coordinate_key],
            $hex_index
          ));
        }
        $seen_coordinate_keys[$coordinate_key] = $hex_index;
        $cell_h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
        if ($cell_h3 === '') {
          throw new \RuntimeException(sprintf(
            'H3 fixed-data contract violation: starter dungeon %s room %s hex[%d] is missing h3_index_res14/h3_index.',
            $dungeon_id,
            $room_id,
            $hex_index
          ));
        }
        $cell_latlng = [
          'latitude' => is_numeric($hex['lat'] ?? NULL) ? (float) $hex['lat'] : NULL,
          'longitude' => is_numeric($hex['lng'] ?? NULL) ? (float) $hex['lng'] : NULL,
        ];

        $cell_metadata = [
          'status' => 'h3_index_assigned',
          'h3_index_source' => 'libh3',
          'normalization' => 'global_non_overlapping_axial',
          'normalization_version' => 'starter-runtime-persist-v1',
          'global_offset_q' => 0,
          'global_offset_r' => 0,
          'local_source_q' => $q,
          'local_source_r' => $r,
          'global_source_q' => $q,
          'global_source_r' => $r,
          'room_entrance_global_q' => $entry_coordinate['q'],
          'room_entrance_global_r' => $entry_coordinate['r'],
          'source' => 'campaign_initialization_starter_room',
        ];

        $this->database->insert('dungeoncrawler_content_h3_room_cells')
          ->fields([
            'dungeon_id' => $dungeon_id,
            'room_id' => $room_id,
            'cell_role' => 'room_hex',
            'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
            'h3_index' => strtolower($cell_h3),
            'source_q' => $q,
            'source_r' => $r,
            'center_latitude' => $cell_latlng['latitude'],
            'center_longitude' => $cell_latlng['longitude'],
            'metadata' => json_encode($cell_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created' => $timestamp,
            'updated' => $timestamp,
          ])
          ->execute();
      }
    }
  }

  /**
   * Canonical contract for required undead-crypt starter NPC anchors.
   *
   * @return array<string, array{q:int,r:int}>
   *   Required npc content_id => anchor position.
   */
  private function undeadCryptStarterNpcContracts(): array {
    return [
      'skeleton_guard_alpha' => ['q' => 3, 'r' => 2],
      'skeleton_guard_beta' => ['q' => 2, 'r' => 3],
    ];
  }

  /**
   * Resolve the entry coordinate for a starter room.
   *
   * @return array{q:int, r:int}
   *   Entry q/r coordinate.
   */
  private function resolveStarterRoomEntryCoordinate(array $room, string $dungeon_id, string $room_id): array {
    $entry_points = is_array($room['entry_points'] ?? NULL) ? $room['entry_points'] : [];
    if ($entry_points !== []) {
      $entry_point = $entry_points[0] ?? NULL;
      if (is_array($entry_point) && is_numeric($entry_point['q'] ?? NULL) && is_numeric($entry_point['r'] ?? NULL)) {
        return [
          'q' => (int) $entry_point['q'],
          'r' => (int) $entry_point['r'],
        ];
      }
    }

    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    $first_hex = $hexes[0] ?? NULL;
    if (is_array($first_hex) && is_numeric($first_hex['q'] ?? NULL) && is_numeric($first_hex['r'] ?? NULL)) {
      return [
        'q' => (int) $first_hex['q'],
        'r' => (int) $first_hex['r'],
      ];
    }

    throw new \RuntimeException(sprintf('H3 system-of-record contract violation: starter dungeon %s room %s has no numeric entry_points[0] or hexes[0] coordinate.', $dungeon_id, $room_id));
  }

  /**
   * Find one starter-room hex by source q/r coordinate.
   *
   * @param array<int, mixed> $hexes
   *   Room hex payloads.
   *
   * @return array<string, mixed>|null
   *   Matching hex payload.
   */
  private function findStarterRoomHexByCoordinate(array $hexes, int $q, int $r): ?array {
    foreach ($hexes as $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        continue;
      }
      if ((int) $hex['q'] === $q && (int) $hex['r'] === $r) {
        return $hex;
      }
    }

    return NULL;
  }

  /**
   * Load the canonical starter-room asset used for new campaigns.
   *
   * Runtime surfaces (chat, hexmap, room view) use the authored runtime room id
   * from the dungeon seed when available, while `source_room_id` retains the
   * canonical asset-library slug (for example `tavern_entrance`).
   *
   * @return array|null
   *   Starter room data, or NULL if unavailable.
   */
  private function loadStarterRoomSeed(array $starter_blueprint = []): ?array {
    $source_room_id = trim((string) ($starter_blueprint['source_room_id'] ?? ''));
    if ($source_room_id === '') {
      $this->logger->error('Starter room asset contract violation: starter_blueprint.source_room_id is required.');
      return NULL;
    }
    $runtime_room_id = trim((string) ($starter_blueprint['runtime_room_id'] ?? ''));
    $default_room_tags = is_array($starter_blueprint['room_tags_default'] ?? NULL) ? $starter_blueprint['room_tags_default'] : [];
    $query = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data', 'source_room_id']);
    $or = $query->orConditionGroup()
      ->condition('room_id', $source_room_id)
      ->condition('source_room_id', $source_room_id);
    if ($runtime_room_id !== '') {
      $or->condition('room_id', $runtime_room_id)
        ->condition('source_room_id', $runtime_room_id);
    }

    $record = $query
      ->condition($or)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($record)) {
      $this->logger->error('Starter room asset {room_id} not found in dungeoncrawler_content_rooms; campaign bootstrap aborted.', [
        'room_id' => $source_room_id,
      ]);
      return NULL;
    }

    $room_id = trim((string) ($record['source_room_id'] ?? ''));
    $resolved_runtime_room_id = $runtime_room_id !== '' ? $runtime_room_id : trim((string) ($record['room_id'] ?? ''));
    if ($room_id === '') {
      $room_id = trim((string) ($record['room_id'] ?? ''));
    }
    if ($room_id === '' || $resolved_runtime_room_id === '') {
      $this->logger->error('Starter room asset record is missing canonical room identifiers.');
      return NULL;
    }
    $layout_data = $this->decodeJsonArray($record['layout_data'] ?? NULL);
    $contents_data = $this->decodeJsonArray($record['contents_data'] ?? NULL);
    if ($this->isUndeadCryptStarterBlueprint($starter_blueprint) && !$this->starterRoomSeedHasRequiredBootstrapShape($layout_data, $contents_data)) {
      throw new \RuntimeException(sprintf(
        'Undead crypt starter room asset %s is structurally incomplete for campaign bootstrap.',
        $room_id
      ));
    }
    if ($this->isUndeadCryptStarterBlueprint($starter_blueprint)) {
      $this->assertUndeadCryptStarterSeedContract(
        $room_id,
        $resolved_runtime_room_id,
        $layout_data,
        $contents_data
      );
    }
    $this->assertRoomQuestTemplateReferencesExist($room_id, $contents_data);

    return [
      'room_id' => $room_id,
      'runtime_room_id' => $resolved_runtime_room_id,
      'name' => (string) ($record['name'] ?? 'The Gilded Tankard'),
      'description' => (string) ($record['description'] ?? ''),
      'environment_tags' => $this->decodeJsonArray($record['environment_tags'] ?? NULL) ?: $default_room_tags,
      'layout_data' => $layout_data,
      'contents_data' => $contents_data,
      'starter_default_source_room_id' => $source_room_id,
    ];
  }

  /**
   * Determine if the starter blueprint targets undead-crypt bootstrap.
   */
  private function isUndeadCryptStarterBlueprint(array $starter_blueprint): bool {
    $theme = strtolower(trim((string) ($starter_blueprint['theme'] ?? '')));
    $source_room_id = trim((string) ($starter_blueprint['source_room_id'] ?? ''));
    $runtime_room_id = trim((string) ($starter_blueprint['runtime_room_id'] ?? ''));

    return $theme === 'undead_crypt'
      || $source_room_id === self::STARTER_UNDEAD_SOURCE_ROOM_ID
      || $runtime_room_id === self::STARTER_UNDEAD_RUNTIME_ROOM_ID;
  }

  /**
   * Validate starter room seed includes bootstrap-required layout/content.
   */
  private function starterRoomSeedHasRequiredBootstrapShape(array $layout_data, array $contents_data): bool {
    $hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
    $npcs = is_array($contents_data['npcs'] ?? NULL) ? $contents_data['npcs'] : [];

    return $hexes !== [] && $npcs !== [];
  }

  /**
   * Validate undead-crypt starter seed contract for canonical bootstrap.
   */
  private function assertUndeadCryptStarterSeedContract(
    string $source_room_id,
    string $runtime_room_id,
    array $layout_data,
    array $contents_data
  ): void {
    if ($source_room_id !== self::STARTER_UNDEAD_SOURCE_ROOM_ID) {
      throw new \RuntimeException(sprintf(
        'Undead crypt starter contract violation: source_room_id must be %s, got %s.',
        self::STARTER_UNDEAD_SOURCE_ROOM_ID,
        $source_room_id
      ));
    }
    if ($runtime_room_id !== self::STARTER_UNDEAD_RUNTIME_ROOM_ID) {
      throw new \RuntimeException(sprintf(
        'Undead crypt starter contract violation: runtime_room_id must be %s, got %s.',
        self::STARTER_UNDEAD_RUNTIME_ROOM_ID,
        $runtime_room_id
      ));
    }

    if ((int) ($layout_data['width'] ?? 0) !== 8 || (int) ($layout_data['height'] ?? 0) !== 8) {
      throw new \RuntimeException('Undead crypt starter contract violation: layout dimensions must be 8x8 (40x40 feet).');
    }

    $entry_points = is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [];
    $has_west_entry = FALSE;
    foreach ($entry_points as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      if (
        (int) ($entry['q'] ?? 999) === -4
        && (int) ($entry['r'] ?? 999) === 0
        && strtolower(trim((string) ($entry['side'] ?? ''))) === 'west'
      ) {
        $has_west_entry = TRUE;
        break;
      }
    }
    if (!$has_west_entry) {
      throw new \RuntimeException('Undead crypt starter contract violation: west entry point (-4,0) is required.');
    }

    $hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
    if (count($hexes) < 64) {
      throw new \RuntimeException(sprintf(
        'Undead crypt starter contract violation: expected at least 64 room hexes, got %d.',
        count($hexes)
      ));
    }

    $npcs = is_array($contents_data['npcs'] ?? NULL) ? $contents_data['npcs'] : [];
    $required_npcs = $this->undeadCryptStarterNpcContracts();
    foreach ($required_npcs as $content_id => $position) {
      $matched = NULL;
      foreach ($npcs as $npc) {
        if (!is_array($npc)) {
          continue;
        }
        if (trim((string) ($npc['content_id'] ?? '')) === $content_id) {
          $matched = $npc;
          break;
        }
      }
      if (!is_array($matched)) {
        throw new \RuntimeException(sprintf(
          'Undead crypt starter contract violation: required NPC %s is missing from contents_data.npcs.',
          $content_id
        ));
      }
      if (
        (int) ($matched['position']['q'] ?? 999) !== (int) $position['q']
        || (int) ($matched['position']['r'] ?? 999) !== (int) $position['r']
      ) {
        throw new \RuntimeException(sprintf(
          'Undead crypt starter contract violation: NPC %s must spawn at (%d,%d).',
          $content_id,
          (int) $position['q'],
          (int) $position['r']
        ));
      }
      if (!$this->isStarterNpcHostileAttitude((string) ($matched['attitude'] ?? ''))) {
        throw new \RuntimeException(sprintf(
          'Undead crypt starter contract violation: NPC %s must be hostile.',
          $content_id
        ));
      }
    }
  }

  /**
   * Load canonical Absalom Streets room seed for starter dungeon linkage.
   */
  private function loadStarterCityStreetsRoomSeed(): ?array {
    return $this->loadStarterConnectedRoomSeed(self::STARTER_CITY_STREETS_ROOM_ID);
  }

  /**
   * Resolve whether starter NPC attitude satisfies hostile contract gate.
   */
  private function isStarterNpcHostileAttitude(string $attitude): bool {
    $score = DispositionAuthorityContract::attitudeToScore($attitude);
    return $score !== NULL && DispositionAuthorityContract::isHostileScore($score);
  }

  /**
   * Load one connected room seed by room identifier.
   */
  private function loadStarterConnectedRoomSeed(string $room_id): ?array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return NULL;
    }

    $record = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'source_room_id', 'name', 'description', 'layout_data'])
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($record)) {
      return NULL;
    }

    $room_id = trim((string) ($record['room_id'] ?? ''));
    if ($room_id === '') {
      return NULL;
    }
    $source_room_id = trim((string) ($record['source_room_id'] ?? ''));
    if ($source_room_id === '') {
      $source_room_id = $room_id;
    }

    return [
      'room_id' => $room_id,
      'source_room_id' => $source_room_id,
      'name' => (string) ($record['name'] ?? $room_id),
      'description' => (string) ($record['description'] ?? ''),
      'layout_data' => $this->decodeJsonArray($record['layout_data'] ?? NULL),
    ];
  }

  /**
   * Decode a JSON column into an array.
   */
  private function decodeJsonArray(mixed $value): array {
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
   * Validate that room-authored NPC quest references resolve canonically.
   */
  private function assertRoomQuestTemplateReferencesExist(string $room_id, array $contents_data): void {
    $npcs = is_array($contents_data['npcs'] ?? NULL) ? $contents_data['npcs'] : [];
    if ($npcs === []) {
      return;
    }

    $schema = $this->database->schema();
    if (
      !$schema->tableExists('dc_canonical_quests')
      || !$schema->fieldExists('dc_canonical_quests', 'template_id')
    ) {
      throw new QuestTemplateReferenceIntegrityException(
        'Starter room quest reference validation requires dc_canonical_quests.template_id.'
      );
    }

    $quest_template_ids = [];
    foreach ($npcs as $npc) {
      if (!is_array($npc)) {
        continue;
      }
      foreach ((array) ($npc['quests'] ?? []) as $quest_entry) {
        if (!is_array($quest_entry)) {
          continue;
        }
        $quest_template_id = trim((string) ($quest_entry['quest_id'] ?? ''));
        if ($quest_template_id !== '') {
          $quest_template_ids[$quest_template_id] = TRUE;
        }
      }
    }
    if ($quest_template_ids === []) {
      return;
    }

    $requested_ids = array_keys($quest_template_ids);
    $existing_ids = $this->database->select('dc_canonical_quests', 'q')
      ->fields('q', ['template_id'])
      ->condition('template_id', $requested_ids, 'IN')
      ->execute()
      ->fetchCol();
    $existing_map = [];
    foreach ((array) $existing_ids as $existing_id) {
      $normalized = trim((string) $existing_id);
      if ($normalized !== '') {
        $existing_map[$normalized] = TRUE;
      }
    }

    $missing_ids = array_values(array_filter(
      $requested_ids,
      static fn(string $template_id): bool => !isset($existing_map[$template_id])
    ));
    if ($missing_ids !== []) {
      foreach ($missing_ids as $missing_id) {
        $this->ensureCanonicalQuestTemplateExists($missing_id);
      }
      $existing_ids = $this->database->select('dc_canonical_quests', 'q')
        ->fields('q', ['template_id'])
        ->condition('template_id', $missing_ids, 'IN')
        ->execute()
        ->fetchCol();
      foreach ((array) $existing_ids as $existing_id) {
        $normalized = trim((string) $existing_id);
        if ($normalized !== '') {
          $existing_map[$normalized] = TRUE;
        }
      }
      $missing_ids = array_values(array_filter(
        $requested_ids,
        static fn(string $template_id): bool => !isset($existing_map[$template_id])
      ));
    }
    if ($missing_ids !== []) {
      sort($missing_ids);
      throw new QuestTemplateReferenceIntegrityException(sprintf(
        'Starter room %s references missing canonical quest template ids: %s.',
        $room_id,
        implode(', ', $missing_ids)
      ));
    }
  }

  /**
   * Ensure one canonical quest template row exists for initialization integrity.
   */
  private function ensureCanonicalQuestTemplateExists(string $template_id): void {
    $normalized = trim($template_id);
    if ($normalized === '') {
      return;
    }
    $existing_id = $this->database->select('dc_canonical_quests', 'q')
      ->fields('q', ['id'])
      ->condition('template_id', $normalized)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($existing_id) {
      return;
    }

    $canonical = $this->loadCanonicalQuestTemplateDefinition($normalized);
    if ($canonical === NULL) {
      return;
    }
    $canonical['template_id'] = $normalized;
    $canonical['created_at'] = $canonical['updated_at'] ?? $this->time->getRequestTime();
    $this->database->insert('dc_canonical_quests')
      ->fields($canonical)
      ->execute();
  }

  /**
   * Resolve the module's absolute filesystem path.
   *
   * @return string
   *   Absolute path to the dungeoncrawler_content module directory.
   */
  private function getModulePath(): string {
    // dirname(__DIR__, 2) navigates from src/Service/ up to the module root.
    return dirname(__DIR__, 2);
  }

  /**
   * Resolve map generator service for centralized campaign room persistence.
   */
  private function resolveMapGeneratorService(): MapGeneratorService {
    if (\Drupal::hasService('dungeoncrawler_content.map_generator')) {
      $candidate = \Drupal::service('dungeoncrawler_content.map_generator');
      if ($candidate instanceof MapGeneratorService) {
        return $candidate;
      }
    }
    throw new \RuntimeException('Campaign initialization contract violation: MapGeneratorService is required for campaign room persistence.');
  }

  /**
   * Load starter room and content into campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $now
   *   Current timestamp.
   *
   * @return bool
   *   TRUE on success.
   */
  private function loadStarterRoomIntoCampaign(int $campaign_id, int $now, array $starter_room, array $starter_blueprint = []): bool {
    $room_ids = $this->resolveStarterRoomIdentifiers($starter_room);
    $source_room_id = $room_ids['source_room_id'];
    $runtime_room_id = $room_ids['runtime_room_id'];
    $room_name = (string) ($starter_room['name'] ?? 'The Gilded Tankard');
    $room_description = (string) ($starter_room['description'] ?? '');
    if ($room_description === '') {
      $room_description = 'The warm glow of candlelight fills the spacious tavern hall as the adventure begins.';
    }

    $starter_layout = is_array($starter_room['layout_data'] ?? NULL) ? $starter_room['layout_data'] : [];
    $runtime_room_payload = $this->loadRuntimeDungeonRoomPayload($campaign_id, $runtime_room_id);
    $runtime_layout_hexes = is_array($runtime_room_payload['hexes'] ?? NULL) ? $runtime_room_payload['hexes'] : [];
    $runtime_layout_entry_points = is_array($runtime_room_payload['entry_points'] ?? NULL) ? $runtime_room_payload['entry_points'] : [];
    $runtime_layout_exit_points = is_array($runtime_room_payload['exit_points'] ?? NULL) ? $runtime_room_payload['exit_points'] : [];
    $runtime_layout_exits = is_array($runtime_room_payload['exits'] ?? NULL) ? $runtime_room_payload['exits'] : [];
    $runtime_layout_terrain = is_array($runtime_room_payload['terrain'] ?? NULL) ? $runtime_room_payload['terrain'] : [];
    $runtime_layout_lighting = is_array($runtime_room_payload['lighting'] ?? NULL) ? $runtime_room_payload['lighting'] : [];
    $runtime_hex_h3_by_qr = [];
    foreach ($runtime_layout_hexes as $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        continue;
      }
      $h3_index = strtolower(trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? '')));
      if ($h3_index === '') {
        continue;
      }
      $runtime_hex_h3_by_qr[((int) $hex['q']) . ':' . ((int) $hex['r'])] = $h3_index;
    }

    $layout_data = [
      'hexes' => $runtime_layout_hexes,
      'entry_points' => $runtime_layout_entry_points,
      'exit_points' => $runtime_layout_exit_points,
      'exits' => $runtime_layout_exits,
      'terrain' => $runtime_layout_terrain,
      'lighting' => $runtime_layout_lighting,
      'room_type' => (string) ($runtime_room_payload['room_type'] ?? $starter_layout['room_type'] ?? 'starter_tavern'),
      'source' => 'runtime_dungeon_room_payload',
    ];
    if ($layout_data['hexes'] === []) {
      throw new \RuntimeException(sprintf(
        'Starter room contract violation: starter room %s must provide layout_data.hexes for campaign room persistence.',
        $runtime_room_id
      ));
    }

    $contents_data = is_array($starter_room['contents_data'] ?? NULL) ? $starter_room['contents_data'] : [];

    $this->resolveMapGeneratorService()->persistCanonicalCampaignRoom(
      $campaign_id,
      $runtime_room_id,
      $room_name,
      $room_description,
      $layout_data,
      $contents_data,
      is_array($starter_room['environment_tags'] ?? NULL)
        ? $starter_room['environment_tags']
        : (is_array($starter_blueprint['room_tags_default'] ?? NULL) ? $starter_blueprint['room_tags_default'] : ['starting_area']),
      $source_room_id
    );

    // Initialize room state
    $this->database->insert('dc_campaign_room_states')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $runtime_room_id,
        'is_cleared' => 0,
        'fog_state' => json_encode([
          'visibility' => 'initial',
          'discovered_hexes' => [],
          'runtime_room_items_seeded' => TRUE,
        ]),
        'last_visited' => $now,
        'updated' => $now,
      ])
      ->execute();

    // Create content objects
    foreach ((array) ($contents_data['items'] ?? []) as $item) {
      $item_type = strtolower(trim((string) ($item['type'] ?? '')));
      $quest_association = trim((string) ($item['quest_association'] ?? ''));
      $item_tags = array_values(array_unique(array_filter(array_map(
        static fn($tag): string => trim((string) $tag),
        (array) ($item['tags'] ?? [])
      ))));
      if ($item_tags === []) {
        $item_tags = ['collectible'];
      }

      if ($item_type === 'collectible_item' && $quest_association === '') {
        throw new \RuntimeException(sprintf(
          'Starter room collectible item "%s" is missing required quest_association.',
          (string) ($item['content_id'] ?? 'unknown')
        ));
      }

      $schema_data = [
        'position' => $item['position'] ?? [],
        'description' => $item['name'] ?? '',
        'quest_association' => $quest_association !== '' ? $quest_association : NULL,
      ];

      $this->database->insert('dc_campaign_content_registry')
        ->fields([
          'campaign_id' => $campaign_id,
          'content_type' => 'item',
          'content_id' => $item['content_id'],
          'name' => $item['name'] ?? 'Unknown',
          'rarity' => 'common',
          'tags' => json_encode($item_tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'schema_data' => json_encode($schema_data),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();

      $item_state = [
        'id' => $item['content_id'],
        'content_id' => $item['content_id'],
        'name' => $item['name'] ?? 'Unknown',
        'type' => 'collectible_item',
        'description' => $item['description'] ?? ($item['name'] ?? ''),
        'position' => $item['position'] ?? [],
        'quest_association' => $quest_association !== '' ? $quest_association : NULL,
        'tags' => $item_tags,
        '_spawn' => [
          'source' => 'campaign_initialization',
          'room_id' => $runtime_room_id,
          'content_id' => $item['content_id'],
        ],
      ];

      $this->database->insert('dc_campaign_item_instances')
        ->fields([
          'campaign_id' => $campaign_id,
          'item_instance_id' => sprintf('room_item_%d_%s', $campaign_id, $item['content_id']),
          'item_id' => $item['content_id'],
          'location_type' => 'room',
          'location_ref' => $runtime_room_id,
          'quantity' => 1,
          'state_data' => json_encode($item_state),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }

    // Create NPCs from room-management canonical content IDs.
    $seen_content_ids = [];
    foreach ((array) ($contents_data['npcs'] ?? []) as $npc) {
      $content_id = $this->canonicalizeRoomNpcContentId((string) ($npc['content_id'] ?? ''));
      if ($content_id === '') {
        throw new \RuntimeException(sprintf(
          'Starter room NPC "%s" is missing canonical content_id.',
          (string) ($npc['name'] ?? 'unknown')
        ));
      }
      if (isset($seen_content_ids[$content_id])) {
        throw new \RuntimeException(sprintf(
          'Starter room contains duplicate NPC content_id "%s".',
          $content_id
        ));
      }
      $seen_content_ids[$content_id] = TRUE;
      $npc = $this->hydrateStarterNpcSeedFromCanonicalRegistry(
        is_array($npc) ? $npc : [],
        $content_id
      );

      $instance_id = 'npc_' . $content_id;
      $npc_stats = is_array($npc['stats'] ?? NULL) ? $npc['stats'] : [];
      $npc_level = max(1, (int) ($npc['level'] ?? 1));
      $npc_hp_current = max(0, (int) ($npc_stats['currentHp'] ?? 0));
      $npc_hp_max = max($npc_hp_current, (int) ($npc_stats['maxHp'] ?? 0));
      $npc_ac = max(0, (int) ($npc_stats['ac'] ?? 0));
      $npc_perception = (int) ($npc_stats['perception'] ?? 0);
      $npc_fortitude = (int) ($npc_stats['fortitude'] ?? 0);
      $npc_reflex = (int) ($npc_stats['reflex'] ?? 0);
      $npc_will = (int) ($npc_stats['will'] ?? 0);
      $npc_role = (string) ($npc['role'] ?? 'npc');
      $npc_class = (string) ($npc['class'] ?? 'npc');
      $npc_ancestry = (string) ($npc['ancestry'] ?? 'humanoid');
      $npc_position_q = is_numeric($npc['position']['q'] ?? NULL) ? (int) $npc['position']['q'] : 0;
      $npc_position_r = is_numeric($npc['position']['r'] ?? NULL) ? (int) $npc['position']['r'] : 0;
      $npc_position_key = $npc_position_q . ':' . $npc_position_r;
      $npc_position_h3 = strtolower(trim((string) ($npc['position']['h3_index_res14'] ?? $npc['position']['h3_index'] ?? '')));
      if ($npc_position_h3 === '' && isset($runtime_hex_h3_by_qr[$npc_position_key])) {
        $npc_position_h3 = $runtime_hex_h3_by_qr[$npc_position_key];
      }
      $npc_seed_payload = [
        'content_id' => $content_id,
        'role' => $npc_role,
        'description' => $npc['description'] ?? '',
        'backstory' => $npc['backstory'] ?? '',
        'quests' => $npc['quests'] ?? [],
        'abilities' => is_array($npc['abilities'] ?? NULL) ? $npc['abilities'] : [],
        'skills' => is_array($npc['skills'] ?? NULL) ? $npc['skills'] : [],
        'attacks' => is_array($npc['attacks'] ?? NULL) ? $npc['attacks'] : [],
        'equipment' => is_array($npc['equipment'] ?? NULL) ? $npc['equipment'] : [],
        'languages' => is_array($npc['languages'] ?? NULL) ? $npc['languages'] : ['Common'],
        'senses' => is_array($npc['senses'] ?? NULL) ? $npc['senses'] : [],
        'goals' => is_array($npc['goals'] ?? NULL) ? $npc['goals'] : [],
        'motivations' => (string) ($npc['motivations'] ?? ''),
        'personality_traits' => is_array($npc['personality_traits'] ?? NULL) ? $npc['personality_traits'] : [],
        'fears' => (string) ($npc['fears'] ?? ''),
        'bonds' => (string) ($npc['bonds'] ?? ''),
        'psychology' => is_array($npc['psychology'] ?? NULL) ? $npc['psychology'] : [],
        'animation_state' => 'idle',
      ];
      $state_data = [
        'content_id' => $content_id,
        'role' => $npc_role,
        'description' => $npc['description'] ?? '',
        'quests' => $npc['quests'] ?? [],
        'animation_state' => 'idle',
      ];
      $state_data = array_replace_recursive($state_data, $npc_seed_payload, [
        'stats' => [
          'ac' => $npc_ac,
          'perception' => $npc_perception,
          'fortitude' => $npc_fortitude,
          'reflex' => $npc_reflex,
          'will' => $npc_will,
          'currentHp' => $npc_hp_current,
          'maxHp' => $npc_hp_max,
        ],
      ]);

      $npc_row_id = (int) $this->database->insert('dc_campaign_characters')
        ->fields([
          'campaign_id' => $campaign_id,
          'character_id' => 0,
          'source_character_id' => NULL,
          'name' => $npc['name'],
          'level' => $npc_level,
          'ancestry' => $npc_ancestry,
          'class' => $npc_class,
          'hp_current' => $npc_hp_current,
          'hp_max' => $npc_hp_max,
          'armor_class' => $npc_ac,
          'experience_points' => 0,
          'position_q' => $npc_position_q,
          'position_r' => $npc_position_r,
          'position_h3' => $npc_position_h3,
          'last_room_id' => $runtime_room_id,
          'instance_id' => $instance_id,
          'type' => 'npc',
          'lifecycle_state' => 'campaign_npc',
          'character_data' => json_encode([
            'step' => 8,
            'name' => $npc['name'],
            'type' => 'npc',
            'role' => $npc_role,
            'description' => $npc['description'] ?? '',
            'class' => $npc_class,
            'ancestry' => $npc_ancestry,
            'level' => $npc_level,
            'backstory' => (string) ($npc['backstory'] ?? ''),
            'goals' => is_array($npc['goals'] ?? NULL) ? $npc['goals'] : [],
            'abilities' => is_array($npc['abilities'] ?? NULL) ? $npc['abilities'] : [],
            'skills' => is_array($npc['skills'] ?? NULL) ? $npc['skills'] : [],
            'attacks' => is_array($npc['attacks'] ?? NULL) ? $npc['attacks'] : [],
            'inventory' => [
              'carried' => is_array($npc['equipment'] ?? NULL) ? $npc['equipment'] : [],
              'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
            ],
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'state_data' => json_encode($state_data),
          'default_locations' => NULL,
          'portrait' => NULL,
          'location_type' => 'room',
          'location_ref' => $runtime_room_id,
          'is_active' => 1,
          'uid' => 0,
          'role' => $npc_role,
          'status' => 1,
          'joined' => $now,
          'created' => $now,
          'changed' => $now,
          'updated' => $now,
        ])
        ->execute();
      $this->seedRuntimeNpcPortraitFromExistingActor(
        $campaign_id,
        $npc_row_id,
        (string) $instance_id,
        (string) ($npc['name'] ?? ''),
        $now
      );

      if ($this->npcSheetGenerationService) {
        $this->npcSheetGenerationService->enqueueNpcSheetGeneration($campaign_id, $instance_id, [
          'entity_ref' => $instance_id,
          'instance_id' => $instance_id,
          'content_id' => $content_id,
          'name' => $npc['name'],
          'role' => $npc_role,
          'description' => $npc['description'] ?? '',
          'backstory' => $npc['backstory'] ?? '',
          'stats' => [
            'currentHp' => $npc_hp_current,
            'maxHp' => $npc_hp_max,
            'ac' => $npc_ac,
            'perception' => $npc_perception,
            'fortitude' => $npc_fortitude,
            'reflex' => $npc_reflex,
            'will' => $npc_will,
          ],
          'equipment' => is_array($npc['equipment'] ?? NULL) ? $npc['equipment'] : [],
          'level' => $npc_level,
          'ancestry' => (string) ($npc['ancestry'] ?? 'Humanoid'),
          'class' => (string) ($npc['class'] ?? 'npc'),
          'alignment' => (string) ($npc['alignment'] ?? 'N'),
          'attitude' => (string) ($npc['attitude'] ?? 'indifferent'),
          'motivations' => (string) ($npc['motivations'] ?? ''),
          'personality_traits' => is_array($npc['personality_traits'] ?? NULL) ? $npc['personality_traits'] : [],
          'fears' => (string) ($npc['fears'] ?? ''),
          'bonds' => (string) ($npc['bonds'] ?? ''),
          'goals' => is_array($npc['goals'] ?? NULL) ? $npc['goals'] : [],
          'languages' => is_array($npc['languages'] ?? NULL) ? $npc['languages'] : ['Common'],
          'senses' => is_array($npc['senses'] ?? NULL) ? $npc['senses'] : [],
          'psychology' => is_array($npc['psychology'] ?? NULL) ? $npc['psychology'] : [],
        ], FALSE);
      }
    }

    if ($this->npcSheetGenerationService) {
      $this->npcSheetGenerationService->launchDetachedWorker();
    }

    $this->loadConnectedRoomsForActiveStarterRoom($campaign_id, $runtime_room_id, $now);

    return TRUE;
  }

  /**
   * Backward-compatible alias while callers migrate to generic starter naming.
   */
  private function loadTavernEntranceRoom(int $campaign_id, int $now, array $starter_room): bool {
    return $this->loadStarterRoomIntoCampaign($campaign_id, $now, $starter_room);
  }

  /**
   * Load the authoritative runtime dungeon room payload for a campaign room.
   *
   * Campaign room rows must mirror this payload so navigation/state contracts
   * read the same room-hex authority everywhere.
   *
   * @return array<string, mixed>
   *   Room payload from dc_campaign_dungeons.dungeon_data.
   */
  private function loadRuntimeDungeonRoomPayload(int $campaign_id, string $room_id): array {
    $room_id = trim($room_id);
    if ($campaign_id <= 0 || $room_id === '') {
      throw new \RuntimeException('Campaign room contract violation: campaign_id and room_id are required for runtime dungeon room lookup.');
    }

    $dungeon_row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($dungeon_row)) {
      throw new \RuntimeException(sprintf(
        'Campaign room contract violation: campaign %d has no dungeon_data row.',
        $campaign_id
      ));
    }

    $dungeon_data = json_decode((string) ($dungeon_row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Campaign room contract violation: campaign %d dungeon_data is invalid JSON.',
        $campaign_id
      ));
    }

    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    foreach ($rooms as $room) {
      if (!is_array($room)) {
        continue;
      }
      if (trim((string) ($room['room_id'] ?? '')) === $room_id) {
        return $room;
      }
    }

    throw new \RuntimeException(sprintf(
      'Campaign room contract violation: room %s is missing from campaign %d dungeon_data.',
      $room_id,
      $campaign_id
    ));
  }

  /**
   * Preload campaign room rows for rooms connected to the active starter room.
   *
   * Quest destination contract checks validate against dc_campaign_rooms. The
   * starter dungeon graph can already include adjacent rooms (for example
   * Absalom Streets), so we mirror those connected rooms into campaign room
   * storage at bootstrap time.
   */
  private function loadConnectedRoomsForActiveStarterRoom(
    int $campaign_id,
    string $active_room_id,
    int $now
  ): void {
    $active_room_id = trim($active_room_id);
    if ($campaign_id <= 0 || $active_room_id === '') {
      throw new \RuntimeException('Starter room preload contract violation: campaign_id and active_room_id are required.');
    }

    $dungeon_row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($dungeon_row)) {
      throw new \RuntimeException(sprintf(
        'Starter room preload contract violation: campaign %d has no dungeon_data row.',
        $campaign_id
      ));
    }

    $dungeon_data = json_decode((string) ($dungeon_row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Starter room preload contract violation: campaign %d dungeon_data is invalid JSON.',
        $campaign_id
      ));
    }

    $rooms = array_values(array_filter(
      is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [],
      'is_array'
    ));
    $rooms_by_id = [];
    foreach ($rooms as $room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        $rooms_by_id[$room_id] = $room;
      }
    }
    if (!isset($rooms_by_id[$active_room_id])) {
      throw new \RuntimeException(sprintf(
        'Starter room preload contract violation: active room %s is not present in campaign %d dungeon_data rooms.',
        $active_room_id,
        $campaign_id
      ));
    }

    $connections = [];
    foreach ([
      $dungeon_data['hex_map']['connections'] ?? [],
      $dungeon_data['connections'] ?? [],
    ] as $connection_bucket) {
      foreach (array_values(array_filter(is_array($connection_bucket) ? $connection_bucket : [], 'is_array')) as $connection) {
        $from_room_id = trim((string) ($connection['from_room'] ?? $connection['from_room_id'] ?? ''));
        $to_room_id = trim((string) ($connection['to_room'] ?? $connection['to_room_id'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
          continue;
        }
        $connections[] = [$from_room_id, $to_room_id];
      }
    }

    $connected_room_ids = [];
    foreach ($connections as [$from_room_id, $to_room_id]) {
      if ($from_room_id === $active_room_id && isset($rooms_by_id[$to_room_id])) {
        $connected_room_ids[$to_room_id] = TRUE;
      }
      elseif ($to_room_id === $active_room_id && isset($rooms_by_id[$from_room_id])) {
        $connected_room_ids[$from_room_id] = TRUE;
      }
    }

    foreach (array_keys($connected_room_ids) as $connected_room_id) {
      $room = $rooms_by_id[$connected_room_id];
      $environment_tags = is_array($room['environment_tags'] ?? NULL) ? $room['environment_tags'] : [];
      if ($environment_tags === []) {
        $environment_tags = ['connected_room', 'starter_region'];
      }

      $layout_data = [
        'hexes' => is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [],
        'entry_points' => is_array($room['entry_points'] ?? NULL) ? $room['entry_points'] : [],
        'exit_points' => is_array($room['exit_points'] ?? NULL) ? $room['exit_points'] : [],
        'exits' => is_array($room['exits'] ?? NULL) ? $room['exits'] : [],
        'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
        'lighting' => is_array($room['lighting'] ?? NULL) ? $room['lighting'] : [],
        'room_type' => (string) ($room['room_type'] ?? 'starter_connected_room'),
        'source' => 'dungeon_data_room_payload',
      ];
      if ($layout_data['hexes'] === []) {
        throw new \RuntimeException(sprintf(
          'Starter room preload contract violation: connected room %s has no hexes in dungeon_data payload.',
          $connected_room_id
        ));
      }
      $contents_data = is_array($room['contents_data'] ?? NULL) ? $room['contents_data'] : [];
      $source_room_id = trim((string) ($room['source_room_id'] ?? $connected_room_id));
      if ($source_room_id === '') {
        $source_room_id = $connected_room_id;
      }

      $this->resolveMapGeneratorService()->persistCanonicalCampaignRoom(
        $campaign_id,
        $connected_room_id,
        (string) ($room['name'] ?? $connected_room_id),
        (string) ($room['description'] ?? ''),
        $layout_data,
        $contents_data,
        $environment_tags,
        $source_room_id
      );

      $fog_state = json_encode([
        'visibility' => 'initial',
        'discovered_hexes' => [],
        'runtime_room_items_seeded' => TRUE,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($fog_state)) {
        throw new \RuntimeException(sprintf(
          'Starter room preload contract violation: failed to encode room fog state for campaign %d room %s.',
          $campaign_id,
          $connected_room_id
        ));
      }

      $this->database->merge('dc_campaign_room_states')
        ->keys([
          'campaign_id' => $campaign_id,
          'room_id' => $connected_room_id,
        ])
        ->fields([
          'is_cleared' => 0,
          'fog_state' => $fog_state,
          'last_visited' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Resolve canonical source/runtime identifiers for starter room persistence.
   *
   * @return array{source_room_id:string,runtime_room_id:string}
   *   Normalized starter room identifiers.
   */
  private function resolveStarterRoomIdentifiers(array $starter_room): array {
    $source_room_id = trim((string) ($starter_room['room_id'] ?? self::STARTER_DEFAULT_SOURCE_ROOM_ID));
    if ($source_room_id === '') {
      $source_room_id = self::STARTER_DEFAULT_SOURCE_ROOM_ID;
    }
    $runtime_room_id = trim((string) ($starter_room['runtime_room_id'] ?? $source_room_id));
    if ($runtime_room_id === '') {
      $runtime_room_id = $source_room_id;
    }

    return [
      'source_room_id' => $source_room_id,
      'runtime_room_id' => $runtime_room_id,
    ];
  }

  /**
   * Seed runtime NPC portrait from existing authoritative NPC actor rows.
   */
  private function seedRuntimeNpcPortraitFromExistingActor(
    int $campaign_id,
    int $target_row_id,
    string $instance_id,
    string $name,
    int $now
  ): void {
    if ($campaign_id <= 0 || $target_row_id <= 0) {
      return;
    }

    $target_portrait = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['portrait'])
      ->condition('cc.id', $target_row_id)
      ->condition('cc.campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (trim((string) $target_portrait) !== '') {
      return;
    }

    $instance_id = trim($instance_id);
    $name = trim($name);

    $canonical_source = $this->resolveCanonicalNpcPortraitSource($instance_id, $name);
    if ($canonical_source === NULL) {
      $this->logger->warning('NPC portrait canonical source missing; leaving runtime row empty for generation fallback. campaign_id={campaign_id} row_id={row_id} instance_id={instance_id} name={name}', [
        'campaign_id' => $campaign_id,
        'row_id' => $target_row_id,
        'instance_id' => $instance_id,
        'name' => $name,
      ]);
      return;
    }

    $image_id = (int) ($canonical_source['image_id'] ?? 0);
    $canonical_portrait = trim((string) ($canonical_source['portrait_url'] ?? ''));
    if ($image_id <= 0 || $canonical_portrait === '') {
      throw new \RuntimeException(sprintf('Canonical portrait source contract violation for NPC %s (instance_id=%s).', $name !== '' ? $name : 'unknown', $instance_id));
    }

    $this->database->update('dc_campaign_characters')
      ->fields([
        'portrait' => $canonical_portrait,
        'changed' => $now,
        'updated' => $now,
      ])
      ->condition('id', $target_row_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    $link_exists = (bool) $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['id'])
      ->condition('l.campaign_id', $campaign_id)
      ->condition('l.table_name', 'dc_campaign_characters')
      ->condition('l.object_id', (string) $target_row_id)
      ->condition('l.slot', 'portrait')
      ->condition('l.variant', 'original')
      ->condition('l.image_id', $image_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$link_exists) {
      $this->database->insert('dc_generated_image_links')
        ->fields([
          'image_id' => $image_id,
          'scope_type' => 'campaign',
          'campaign_id' => $campaign_id,
          'table_name' => 'dc_campaign_characters',
          'object_id' => (string) $target_row_id,
          'slot' => 'portrait',
          'variant' => 'original',
          'is_primary' => 1,
          'sort_weight' => 0,
          'visibility' => 'owner',
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Resolve canonical library portrait source for a starter NPC identity.
   *
   * @return array{image_id:int,portrait_url:string}|null
   *   Canonical image source descriptor, or NULL when no canonical image exists.
   */
  private function resolveCanonicalNpcPortraitSource(string $instance_id, string $name): ?array {
    $library_row_id = $this->resolveCanonicalNpcLibraryRowId($instance_id, $name);
    if ($library_row_id === NULL) {
      return NULL;
    }

    $link_row = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['image_id'])
      ->condition('l.table_name', 'dungeoncrawler_content_characters')
      ->condition('l.object_id', (string) $library_row_id)
      ->condition('l.slot', 'portrait')
      ->condition('l.variant', 'original')
      ->isNull('l.campaign_id')
      ->orderBy('l.is_primary', 'DESC')
      ->orderBy('l.created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($link_row)) {
      return NULL;
    }

    $image_id = (int) ($link_row['image_id'] ?? 0);
    if ($image_id <= 0) {
      return NULL;
    }

    $image_row = $this->database->select('dc_generated_images', 'i')
      ->fields('i', ['public_url', 'file_uri'])
      ->condition('i.id', $image_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($image_row)) {
      return NULL;
    }

    $public_url = trim((string) ($image_row['public_url'] ?? ''));
    if ($public_url === '') {
      $file_uri = trim((string) ($image_row['file_uri'] ?? ''));
      if ($file_uri === '' || !str_starts_with($file_uri, 'public://')) {
        return NULL;
      }
      $public_url = '/sites/default/files/' . ltrim(substr($file_uri, strlen('public://')), '/');
    }

    return [
      'image_id' => $image_id,
      'portrait_url' => $public_url,
    ];
  }

  /**
   * Resolve canonical library NPC row id by stable instance id, then exact name.
   */
  private function resolveCanonicalNpcLibraryRowId(string $instance_id, string $name): ?int {
    $instance_candidates = [];
    $instance_id = trim($instance_id);
    if ($instance_id !== '') {
      $instance_candidates[] = $instance_id;
      if (str_starts_with($instance_id, 'npc_')) {
        $instance_candidates[] = substr($instance_id, strlen('npc_'));
      }
    }
    $instance_candidates = array_values(array_unique(array_filter($instance_candidates, static fn(string $candidate): bool => $candidate !== '')));
    if ($instance_candidates !== []) {
      $row_id = $this->database->select('dungeoncrawler_content_characters', 'c')
        ->fields('c', ['id'])
        ->condition('c.type', 'npc')
        ->condition('c.instance_id', $instance_candidates, 'IN')
        ->orderBy('c.updated', 'DESC')
        ->orderBy('c.id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($row_id !== FALSE) {
        return (int) $row_id;
      }
    }

    $name = trim($name);
    if ($name === '') {
      return NULL;
    }

    $candidates = $this->database->select('dungeoncrawler_content_characters', 'c')
      ->fields('c', ['id', 'state_data'])
      ->condition('c.type', 'npc')
      ->condition('c.state_data', '%' . $this->database->escapeLike($name) . '%', 'LIKE')
      ->orderBy('c.id', 'DESC')
      ->execute()
      ->fetchAllAssoc('id');
    if (!is_array($candidates) || $candidates === []) {
      return NULL;
    }

    foreach ($candidates as $candidate) {
      $state_data = json_decode((string) ($candidate->state_data ?? '{}'), TRUE);
      if (!is_array($state_data)) {
        continue;
      }
      if (trim((string) ($state_data['name'] ?? '')) !== $name) {
        continue;
      }
      return (int) ($candidate->id ?? 0) ?: NULL;
    }

    return NULL;
  }

  /**
   * Seed starter quest templates and create initial campaign quests.
   */
  private function seedStarterQuests(int $campaign_id, string $difficulty, int $now, string $starter_runtime_room_id, array $starter_blueprint = []): void {
    if (!$this->database->schema()->tableExists('dc_canonical_quests')
      || !$this->database->schema()->tableExists('dc_campaign_quests')) {
      return;
    }

    $starter_theme = strtolower(trim((string) ($starter_blueprint['theme'] ?? '')));
    $location_tags = is_array($starter_blueprint['location_tags'] ?? NULL) ? $starter_blueprint['location_tags'] : ['starting_area'];

    // Phase-1 scope: only tavern-aligned starts auto-seed storyline lead quests.
    if ($starter_theme === 'undead_crypt') {
      return;
    }

    $npc_ids = $this->resolveNpcInstanceIds($campaign_id, ['tavern_keeper', 'scholar_npc']);

    $starter_templates = [
      'tavern_storyline_leads' => [
        'giver_npc_id' => $npc_ids['tavern_keeper'] ?? NULL,
        'initial_status' => 'offered',
      ],
    ];

    $this->ensureQuestTemplatesLoaded(array_keys($starter_templates));

    $difficulty_map = [
      'normal' => 'moderate',
      'hard' => 'severe',
      'extreme' => 'extreme',
    ];
    $quest_difficulty = $difficulty_map[$difficulty] ?? 'moderate';

    foreach ($starter_templates as $template_id => $overrides) {
      $context = array_merge([
        'party_level' => 1,
        'difficulty' => $quest_difficulty,
        'location' => trim($starter_runtime_room_id) !== '' ? trim($starter_runtime_room_id) : 'tavern_entrance',
        'location_tags' => $location_tags,
      ], $overrides);

      $quest_data = $this->questGenerator->generateQuestFromTemplate(
        $template_id,
        $campaign_id,
        $context
      );

      if (empty($quest_data)) {
        $this->logger->warning('Starter quest generation failed for template {template_id}', [
          'template_id' => $template_id,
        ]);
        continue;
      }

      $this->storylineQuestLifecycleService->ensureOfferedQuestFromTemplate(
        $campaign_id,
        $template_id,
        static fn(): array => $quest_data
      );
    }
  }

  /**
   * Seeds bundled storyline instances plus their runtime relationship graph.
   */
  private function seedBundledStorylinesAndRelationships(int $campaign_id): void {
    if (!$this->storylineManager || !$this->relationshipManager || !$this->relationshipManager->isRelationshipStorageReady()) {
      return;
    }

    try {
      $storylines = $this->storylineManager->ensureBundledCampaignStorylines($campaign_id, [
        'status' => 'available',
        'priority_base' => 100,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      throw new \RuntimeException(sprintf(
        'Storyline bootstrap contract violation: failed to seed bundled campaign storylines for campaign %d: %s',
        $campaign_id,
        $e->getMessage()
      ), 0, $e);
    }

    $this->relationshipManager->seedLibraryRelationships($campaign_id);
    $npc_ids = $this->resolveNpcInstanceIds($campaign_id, ['tavern_keeper']);

    foreach ($storylines as $storyline) {
      $this->relationshipManager->seedStorylineContacts($campaign_id, $storyline, [
        'default_broker_campaign_character_id' => (int) ($npc_ids['tavern_keeper'] ?? 0),
      ]);
    }

    $this->relationshipManager->refreshCampaignStorylineContacts($campaign_id, 'npc_tavern_keeper');
  }

  /**
   * Canonicalize room-management NPC content IDs.
   */
  private function canonicalizeRoomNpcContentId(string $content_id): string {
    $normalized = strtolower(trim($content_id));
    if ($normalized === '') {
      return '';
    }

    if (str_starts_with($normalized, 'npc_')) {
      $normalized = substr($normalized, 4);
    }
    elseif (str_starts_with($normalized, 'npc-')) {
      $normalized = substr($normalized, 4);
    }

    return trim($normalized);
  }

  /**
   * Hydrate starter NPC payload from canonical registry when room seed is sparse.
   */
  private function hydrateStarterNpcSeedFromCanonicalRegistry(array $npc, string $content_id): array {
    $content_id = $this->canonicalizeRoomNpcContentId($content_id);
    if ($content_id === '') {
      throw new \RuntimeException('Starter NPC hydration contract violation: content_id is required.');
    }

    $canonical_definition = [];
    $row = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['name', 'schema_data'])
      ->condition('content_type', 'npc')
      ->condition('content_id', $content_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($row)) {
      $canonical_definition = json_decode((string) ($row['schema_data'] ?? '{}'), TRUE);
      if (!is_array($canonical_definition)) {
        $canonical_definition = [];
      }
      if (trim((string) ($npc['name'] ?? '')) === '') {
        $npc['name'] = (string) ($row['name'] ?? '');
      }
    }

    $npc['name'] = trim((string) ($npc['name'] ?? $canonical_definition['name'] ?? ''));
    if ($npc['name'] === '') {
      throw new \RuntimeException(sprintf(
        'Starter room NPC contract violation: canonical name is required for content_id "%s".',
        $content_id
      ));
    }
    if (trim((string) ($npc['description'] ?? '')) === '') {
      $npc['description'] = (string) ($canonical_definition['description'] ?? '');
    }
    if (trim((string) ($npc['role'] ?? '')) === '') {
      $npc['role'] = (string) ($canonical_definition['role'] ?? 'npc');
    }
    if (trim((string) ($npc['class'] ?? '')) === '') {
      $npc['class'] = (string) ($canonical_definition['class'] ?? 'npc');
    }
    if (trim((string) ($npc['ancestry'] ?? '')) === '') {
      $npc['ancestry'] = (string) ($canonical_definition['ancestry'] ?? 'humanoid');
    }
    if (!is_numeric($npc['level'] ?? NULL) || (int) ($npc['level'] ?? 0) <= 0) {
      $npc['level'] = max(1, (int) ($canonical_definition['level'] ?? 1));
    }
    if (!is_array($npc['stats'] ?? NULL)) {
      $npc['stats'] = [];
    }
    $npc['stats']['ac'] = (int) ($npc['stats']['ac'] ?? $canonical_definition['stats']['ac'] ?? 0);
    $npc['stats']['perception'] = (int) ($npc['stats']['perception'] ?? $canonical_definition['stats']['perception'] ?? 0);
    $npc['stats']['fortitude'] = (int) ($npc['stats']['fortitude'] ?? $canonical_definition['stats']['fortitude'] ?? 0);
    $npc['stats']['reflex'] = (int) ($npc['stats']['reflex'] ?? $canonical_definition['stats']['reflex'] ?? 0);
    $npc['stats']['will'] = (int) ($npc['stats']['will'] ?? $canonical_definition['stats']['will'] ?? 0);
    $npc['stats']['currentHp'] = max(0, (int) ($npc['stats']['currentHp'] ?? $canonical_definition['stats']['currentHp'] ?? 0));
    $npc['stats']['maxHp'] = max(
      (int) $npc['stats']['currentHp'],
      (int) ($npc['stats']['maxHp'] ?? $canonical_definition['stats']['maxHp'] ?? 0)
    );

    return $npc;
  }

  /**
   * Resolve NPC instance IDs for a campaign by content IDs.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $content_ids
   *   Content IDs to resolve (without npc_ prefix).
   *
   * @return array
   *   Map of content_id => npc numeric ID.
   */
  private function resolveNpcInstanceIds(int $campaign_id, array $content_ids): array {
    if (empty($content_ids)) {
      return [];
    }

    $canonical_content_ids = array_values(array_filter(array_unique(array_map(
      fn(string $content_id): string => $this->canonicalizeRoomNpcContentId($content_id),
      $content_ids
    ))));
    if ($canonical_content_ids === []) {
      return [];
    }

    $instance_ids = array_map(static function (string $content_id): string {
      return 'npc_' . $content_id;
    }, $canonical_content_ids);

    $rows = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_ids, 'IN')
      ->execute()
      ->fetchAllKeyed(1, 0);

    $map = [];
    foreach ($rows as $instance_id => $id) {
      $content_id = preg_replace('/^npc_/', '', (string) $instance_id);
      $map[$content_id] = (int) $id;
    }

    return $map;
  }

  /**
   * Ensure required quest templates exist in the canonical asset library.
   */
  private function ensureQuestTemplatesLoaded(array $template_ids): void {
    foreach ($template_ids as $template_id) {
      $existing = $this->database->select('dc_canonical_quests', 'q')
        ->fields('q', ['id'])
        ->condition('template_id', $template_id)
        ->orderBy('updated_at', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      $canonical = $this->loadCanonicalQuestTemplateDefinition((string) $template_id);
      if (!$existing) {
        $this->logger->error('Required starter quest template missing from canonical asset library: {template_id}', [
          'template_id' => $template_id,
        ]);
      }
      elseif ($canonical !== NULL) {
        $this->database->update('dc_canonical_quests')
          ->fields($canonical)
          ->condition('id', (int) $existing)
          ->execute();
      }
    }
  }

  /**
   * Load one canonical bundled quest template definition from the source file.
   */
  private function loadCanonicalQuestTemplateDefinition(string $template_id): ?array {
    $path = $this->moduleList->getPath('dungeoncrawler_content') . '/content/quest_templates.json';
    if (!is_file($path)) {
      return NULL;
    }

    $decoded = json_decode((string) file_get_contents($path), TRUE);
    if (!is_array($decoded)) {
      return NULL;
    }

    foreach ($decoded as $entry) {
      if (!is_array($entry) || trim((string) ($entry['template_id'] ?? '')) !== $template_id) {
        continue;
      }

      return [
        'name' => (string) ($entry['name'] ?? ''),
        'description' => (string) ($entry['description'] ?? ''),
        'quest_type' => (string) ($entry['quest_type'] ?? 'side_quest'),
        'level_min' => (int) ($entry['level_min'] ?? 1),
        'level_max' => (int) ($entry['level_max'] ?? 20),
        'tags' => json_encode($entry['tags'] ?? []),
        'objectives_schema' => json_encode($entry['objectives_schema'] ?? []),
        'rewards_schema' => json_encode($entry['rewards_schema'] ?? []),
        'prerequisites' => json_encode($entry['prerequisites'] ?? []),
        'story_impact' => json_encode($entry['story_impact'] ?? []),
        'estimated_duration_minutes' => isset($entry['estimated_duration_minutes']) ? (int) $entry['estimated_duration_minutes'] : NULL,
        'updated_at' => $this->time->getRequestTime(),
        'version' => (string) ($entry['version'] ?? '1.0.0'),
      ];
    }

    return NULL;
  }

  /**
   * Bootstrap hierarchical chat sessions for a new campaign.
   *
   * Creates the campaign root (GM master feed), system log, party chat,
   * and the starter dungeon / room sessions so every tab in the chat
   * panel has a dedicated, campaign-specific instance from the start.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $campaign_name
   *   Campaign name for labeling.
   * @param string $dungeon_id
   *   Starter dungeon ID (from createStarterDungeon).
   * @param string $room_id
   *   Starter room ID (e.g. 'tavern_entrance').
   * @param string $room_name
   *   Human-readable room name.
   * @param string $room_description
   *   Authoritative room description text.
   */
  private function bootstrapChatSessions(
    int $campaign_id,
    string $campaign_name,
    string $dungeon_id = '',
    string $room_id = '',
    string $room_name = '',
    string $room_description = ''
  ): void {
    if (!$this->chatSessionManager) {
      $this->logger->notice('ChatSessionManager not available; skipping chat session bootstrap for campaign {id}', [
        'id' => $campaign_id,
      ]);
      return;
    }

    try {
      // 1. Campaign root + system_log + party.
      $root = $this->chatSessionManager->ensureCampaignSessions($campaign_id, $campaign_name);

      // 2. Post the initial GM system message.
      $this->chatSessionManager->postMessage(
        (int) $root['id'],
        $campaign_id,
        'System',
        'system',
        '',
        "Campaign \"{$campaign_name}\" initialized. GM master feed active.",
        'system',
        'gm_only',
        ['event' => 'campaign_init'],
        FALSE
      );

      // 3. Eagerly create dungeon + room sessions for the starter content
      //    so the chat panel has campaign-specific instances immediately.
      if ($dungeon_id !== '') {
        $dungeon_session = $this->chatSessionManager->ensureDungeonSession(
          $campaign_id,
          $dungeon_id,
          'Starter Dungeon'
        );

        if ($room_id !== '') {
          $this->chatSessionManager->ensureRoomSession(
            $campaign_id,
            $dungeon_id,
            $room_id,
            $room_name ?: $room_id,
          );
        }
      }

      // 4. Seed the system-log session with a mechanical entry so the
      //    Dice Log tab shows campaign context immediately.
      $sys_log_key = $this->chatSessionManager->systemLogSessionKey($campaign_id);
      $sys_log = $this->chatSessionManager->loadSession($sys_log_key);
      if ($sys_log) {
        $this->chatSessionManager->postMessage(
          (int) $sys_log['id'],
          $campaign_id,
          'System',
          'system',
          '',
          "Campaign \"{$campaign_name}\" created. Dice log ready.",
          'mechanical',
          'all',
          ['event' => 'campaign_init'],
          FALSE
        );
      }

      $this->logger->info('Chat sessions bootstrapped for campaign {id} (root session: {root_id})', [
        'id' => $campaign_id,
        'root_id' => $root['id'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to bootstrap chat sessions for campaign {id}: {error}', [
        'id' => $campaign_id,
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Build the starter room opener text for canonical room-entry narration.
   */
  private function buildStarterRoomIntroMessage(string $room_name, string $room_description): string {
    $room_name = trim($room_name);
    $room_description = trim($room_description);

    if ($room_description !== '') {
      if ($room_name !== '' && stripos($room_description, $room_name) === FALSE) {
        return $room_name . "\n\n" . $room_description;
      }
      return $room_description;
    }

    return $room_name !== ''
      ? "You arrive at {$room_name}. The adventure begins..."
      : 'You enter the room. The adventure begins...';
  }

}
