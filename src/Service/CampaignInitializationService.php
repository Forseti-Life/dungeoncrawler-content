<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Exception\QuestTemplateReferenceIntegrityException;
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
  protected StorylineQuestLifecycleService $storylineQuestLifecycleService;
  protected CampaignClockService $campaignClockService;

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
    ?NavigationRuntimeService $navigation_runtime = NULL
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->moduleList = $module_list;
    $this->questGenerator = $quest_generator;
    $this->campaignNameGenerator = $campaign_name_generator;
    $this->campaignClockService = $campaign_clock_service;
    $this->chatSessionManager = $chat_session_manager;
    $this->npcSheetGenerationService = $npc_sheet_generation_service;
    $this->roomViewImageService = $room_view_image_service;
    $this->storylineManager = $storyline_manager;
    $this->relationshipManager = $relationship_manager;
    $this->connectorDefinitionService = $connector_definition_service;
    $this->navigationRuntime = $navigation_runtime;
    $this->storylineQuestLifecycleService = $storyline_quest_lifecycle_service;
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
    string $difficulty
  ): int {
    $now = $this->time->getRequestTime();
    $campaign_name = $this->resolveCampaignName($name, $theme, $uid, $now);
    $operation_uuid = $this->uuid->generate();

    $transaction = $this->database->startTransaction('campaign_init');
    try {
      // 1. Create campaign record
      $campaign_id = $this->createCampaign($uid, $campaign_name, $theme, $difficulty, $now);
      if (!$campaign_id) {
        return 0;
      }
      $this->claimInitializationStep(
        $campaign_id,
        $operation_uuid,
        self::INIT_STEP_BOOTSTRAP,
        $now
      );

      $starter_room = $this->loadStarterRoomSeed();
      if ($starter_room === NULL) {
        $transaction->rollBack();
        $this->logger->error('Failed to load explicit starter tavern asset for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }
      $starter_room_ids = $this->resolveStarterRoomIdentifiers($starter_room);
      $starter_runtime_room_id = $starter_room_ids['runtime_room_id'];

      // 2. Create default starter dungeon
      $dungeon_id = $this->createStarterDungeon($campaign_id, $theme, $now, $starter_room);
      if (!$dungeon_id) {
        $transaction->rollBack();
        $this->logger->error('Failed to create starter dungeon for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }
      $this->seedStarterConnectorAuthority($campaign_id, $dungeon_id, $starter_runtime_room_id);

      // 3. Load Tavern Entrance room and content
      if (!$this->loadTavernEntranceRoom($campaign_id, $now, $starter_room)) {
        $transaction->rollBack();
        $this->logger->error('Failed to load tavern entrance for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }

      $this->seedStarterQuests($campaign_id, $difficulty, $now, $starter_runtime_room_id);

      // 5. Bootstrap hierarchical chat sessions for the campaign.
      //    Include the starter dungeon and tavern room so they get
      //    dedicated sessions from the very start.

      $this->bootstrapChatSessions(
        $campaign_id,
        $campaign_name,
        $dungeon_id,
        $starter_runtime_room_id,
        (string) ($starter_room['name'] ?? 'The Gilded Tankard'),
        (string) ($starter_room['description'] ?? '')
      );
      if ($this->roomViewImageService) {
        $this->roomViewImageService->warmRoomViewImageCache($starter_room, [
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
          'room_id' => $starter_runtime_room_id,
        ]);
      }
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
      $this->persistCampaignInitPhase($campaign_id, self::INIT_PHASE_STRUCTURAL_READY, [
        'owner' => 'CampaignInitializationService',
        'ready_at' => gmdate('c', $now),
        'dungeon_id' => $dungeon_id,
        'starter_room_id' => $starter_runtime_room_id,
        'operation_uuid' => $operation_uuid,
      ], $now);

      $this->logger->info('Campaign {campaign_id} initialized with starter dungeon {dungeon_id}', [
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
      ]);

      return $campaign_id;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Campaign initialization failed: {error}', ['error' => $e->getMessage()]);
      return 0;
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
    int $now
  ): int {
    $payload = [
      'state' => [
        'schema_version' => '1.0.0',
        'created_by' => $uid,
        'started' => FALSE,
        'progress' => [],
        'created_at' => gmdate('c', $now),
        'updated_at' => gmdate('c', $now),
        CampaignClockService::STATE_KEY => $this->campaignClockService->createClockFromTimestamp($now),
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

    return (int) $this->database->insert('dc_campaigns')
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
    array $starter_room
  ): string|FALSE {
    $runtime_room_id = trim((string) ($starter_room['runtime_room_id'] ?? ''));
    $layout_data = is_array($starter_room['layout_data'] ?? NULL) ? $starter_room['layout_data'] : [];

    if ($runtime_room_id === '' || empty($layout_data['hexes']) || empty($starter_room['contents_data']['npcs'])) {
      $this->logger->error('Starter tavern asset is incomplete; refusing to synthesize a dungeon from partial data.');
      return FALSE;
    }

    $dungeon_id = $this->uuid->generate();
    $level_id = $this->uuid->generate();
    $room_name = (string) ($starter_room['name'] ?? 'The Gilded Tankard');
    $room_description = (string) ($starter_room['description'] ?? 'Starter tavern asset.');
    $dungeon_name = self::STARTER_CITY_DUNGEON_NAME;
    $dungeon_description = self::STARTER_CITY_DUNGEON_DESCRIPTION;
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
    $starter_streets_room = $this->loadStarterCityStreetsRoomSeed();
    if (!is_array($starter_streets_room)) {
      $this->logger->error('Starter city streets asset is missing; refusing to synthesize starter dungeon.');
      return FALSE;
    }
    $streets_layout_data = is_array($starter_streets_room['layout_data'] ?? NULL) ? $starter_streets_room['layout_data'] : [];
    if (empty($streets_layout_data['hexes'])) {
      throw new \RuntimeException('Starter city streets asset is incomplete; hexes are required.');
    }
    $streets_payload = [
      'room_id' => (string) ($starter_streets_room['room_id'] ?? self::STARTER_CITY_STREETS_ROOM_ID),
      'source_room_id' => (string) ($starter_streets_room['source_room_id'] ?? self::STARTER_CITY_STREETS_ROOM_ID),
      'name' => (string) ($starter_streets_room['name'] ?? 'Absalom Streets'),
      'description' => (string) ($starter_streets_room['description'] ?? ''),
      'hexes' => is_array($streets_layout_data['hexes'] ?? NULL) ? $streets_layout_data['hexes'] : [],
      'entry_points' => is_array($streets_layout_data['entry_points'] ?? NULL) ? $streets_layout_data['entry_points'] : [],
      'exit_points' => is_array($streets_layout_data['exit_points'] ?? NULL) ? $streets_layout_data['exit_points'] : [],
      'exits' => is_array($streets_layout_data['exits'] ?? NULL) ? $streets_layout_data['exits'] : [],
      'terrain' => is_array($streets_layout_data['terrain'] ?? NULL) ? $streets_layout_data['terrain'] : [],
      'lighting' => is_array($streets_layout_data['lighting'] ?? NULL) ? $streets_layout_data['lighting'] : [],
      'room_type' => (string) ($streets_layout_data['room_type'] ?? 'city_street'),
    ];
    $room_payload = $this->requireStarterRoomHexH3Indexes($dungeon_id, $room_payload);
    $streets_payload = $this->requireStarterRoomHexH3Indexes($dungeon_id, $streets_payload);

    $dungeon_data = [
      'schema_version' => '1.0.0',
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
        'connections' => $this->buildStarterCanonicalConnections($runtime_room_id),
        'regions' => [
          [
            'region_id' => 'starter-tavern-region',
            'name' => $dungeon_name,
            'description' => $dungeon_description,
            'room_ids' => [$runtime_room_id, (string) $streets_payload['room_id']],
            'ambient_hazard_level' => 0,
          ],
        ],
        'metadata' => [
          'created_at' => gmdate('c', $now),
          'generated_by' => 'asset-library',
          'is_finalized' => TRUE,
          'total_rooms' => 2,
          'explored_rooms' => 0,
          'exploration_percentage' => 0,
        ],
      ],
      'rooms' => [$room_payload, $streets_payload],
    ];

    $this->database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'name' => $dungeon_name,
        'description' => $dungeon_description,
        'theme' => $dungeon_theme,
        'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'source_dungeon_id' => 'asset-library-starter-room',
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
  private function buildStarterCanonicalConnections(string $starter_room_id): array {
    $starter_room_id = trim($starter_room_id);
    if ($starter_room_id === '') {
      throw new \RuntimeException('Starter dungeon contract violation: starter room id is required for canonical starter connections.');
    }

    $canonical_match = $this->resolveCanonicalStarterConnector($starter_room_id, self::STARTER_CITY_STREETS_ROOM_ID);
    return [[
      'connection_id' => (string) ($canonical_match['connection_id'] ?? ''),
      'from_room' => (string) ($canonical_match['from_room_id'] ?? $starter_room_id),
      'from_room_id' => (string) ($canonical_match['from_room_id'] ?? $starter_room_id),
      'to_room' => (string) ($canonical_match['to_room_id'] ?? self::STARTER_CITY_STREETS_ROOM_ID),
      'to_room_id' => (string) ($canonical_match['to_room_id'] ?? self::STARTER_CITY_STREETS_ROOM_ID),
      'type' => (string) ($canonical_match['kind'] ?? 'hallway'),
      'kind' => (string) ($canonical_match['kind'] ?? 'hallway'),
      'state' => (string) ($canonical_match['state'] ?? $canonical_match['default_state'] ?? 'open'),
      'bidirectional' => strtolower((string) ($canonical_match['direction'] ?? 'bidirectional')) !== 'one_way',
      'is_discovered' => !empty($canonical_match['is_discovered_default']) || !empty($canonical_match['is_discovered']),
      'is_passable' => strtolower((string) ($canonical_match['state'] ?? $canonical_match['default_state'] ?? 'open')) === 'open',
      'destination_type' => 'room',
      'destination_id' => (string) ($canonical_match['to_room_id'] ?? self::STARTER_CITY_STREETS_ROOM_ID),
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
    if ($rooms_after <= $rooms_before || $connections_after <= $connections_before) {
      throw new \RuntimeException(sprintf(
        'Template room+connector instantiation contract violation: expansion produced no graph growth for campaign %d dungeon %s (rooms %d→%d, connections %d→%d).',
        $campaign_id,
        $runtime_dungeon_id,
        $rooms_before,
        $rooms_after,
        $connections_before,
        $connections_after
      ));
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
  private function loadStarterRoomSeed(): ?array {
    $query = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data', 'source_room_id']);
    $or = $query->orConditionGroup()
      ->condition('room_id', 'tavern_entrance')
      ->condition('source_room_id', 'tavern_entrance');

    $record = $query
      ->condition($or)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($record)) {
      $this->logger->error('Starter tavern asset not found in dungeoncrawler_content_rooms; packaged JSON fallbacks are disabled.');
      return NULL;
    }

    $room_id = trim((string) ($record['source_room_id'] ?? ''));
    $runtime_room_id = trim((string) ($record['room_id'] ?? ''));
    if ($room_id === '') {
      $room_id = $runtime_room_id;
    }
    if ($room_id === '' || $runtime_room_id === '') {
      $this->logger->error('Starter tavern asset record is missing canonical room identifiers.');
      return NULL;
    }
    $contents_data = $this->decodeJsonArray($record['contents_data'] ?? NULL);
    $this->assertRoomQuestTemplateReferencesExist($room_id, $contents_data);

    return [
      'room_id' => $room_id,
      'runtime_room_id' => $runtime_room_id,
      'name' => (string) ($record['name'] ?? 'The Gilded Tankard'),
      'description' => (string) ($record['description'] ?? ''),
      'environment_tags' => $this->decodeJsonArray($record['environment_tags'] ?? NULL),
      'layout_data' => $this->decodeJsonArray($record['layout_data'] ?? NULL),
      'contents_data' => $contents_data,
    ];
  }

  /**
   * Load canonical Absalom Streets room seed for starter dungeon linkage.
   */
  private function loadStarterCityStreetsRoomSeed(): ?array {
    $record = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'source_room_id', 'name', 'description', 'layout_data'])
      ->condition('room_id', self::STARTER_CITY_STREETS_ROOM_ID)
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
      'name' => (string) ($record['name'] ?? 'Absalom Streets'),
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
      sort($missing_ids);
      throw new QuestTemplateReferenceIntegrityException(sprintf(
        'Starter room %s references missing canonical quest template ids: %s.',
        $room_id,
        implode(', ', $missing_ids)
      ));
    }
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
   * Load Tavern Entrance room and content into campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $now
   *   Current timestamp.
   *
   * @return bool
   *   TRUE on success.
   */
  private function loadTavernEntranceRoom(int $campaign_id, int $now, array $starter_room): bool {
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
      is_array($starter_room['environment_tags'] ?? NULL) ? $starter_room['environment_tags'] : ['indoor', 'tavern', 'safe', 'starting_area'],
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
    foreach ($contents_data['items'] as $item) {
      $item_type = strtolower(trim((string) ($item['type'] ?? '')));
      $quest_association = trim((string) ($item['quest_association'] ?? ''));
      $item_tags = array_values(array_unique(array_filter(array_map(
        static fn($tag): string => trim((string) $tag),
        (array) ($item['tags'] ?? [])
      ))));
      if ($item_tags === []) {
        $item_tags = ['collectible', 'tavern'];
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
    foreach ($contents_data['npcs'] as $npc) {
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
          'position_q' => $npc['position']['q'],
          'position_r' => $npc['position']['r'],
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
    $source_room_id = trim((string) ($starter_room['room_id'] ?? 'tavern_entrance'));
    if ($source_room_id === '') {
      $source_room_id = 'tavern_entrance';
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
  private function seedStarterQuests(int $campaign_id, string $difficulty, int $now, string $starter_runtime_room_id): void {
    if (!$this->database->schema()->tableExists('dc_canonical_quests')
      || !$this->database->schema()->tableExists('dc_campaign_quests')) {
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
        'location_tags' => ['tavern', 'starting_area'],
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
          $room_session = $this->chatSessionManager->ensureRoomSession(
            $campaign_id,
            $dungeon_id,
            $room_id,
            $room_name ?: $room_id,
          );

          // Post a welcome message into the room session so the room
          // tab has something to show besides an empty state.
          $seed_message = $this->buildStarterRoomSeedNarration($room_name, $room_description);

          $this->chatSessionManager->postMessage(
            (int) $room_session['id'],
            $campaign_id,
            'Narrator',
            'narrator',
            '',
            $seed_message,
            'narrative',
            'all',
            ['event' => 'room_enter', 'room_id' => $room_id],
            TRUE
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
   * Seed the starter room's visible runtime chat log for the hexmap frontend.
   */
  private function seedStarterRoomChatHistory(
    int $campaign_id,
    string $dungeon_id,
    string $room_id,
    string $room_name,
    string $room_description,
    int $now
  ): void {
    if ($room_id === '') {
      return;
    }

    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$record) {
      return;
    }

    $dungeon_data = json_decode((string) ($record['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data) || !is_array($dungeon_data['rooms'] ?? NULL)) {
      return;
    }

    foreach ($dungeon_data['rooms'] as &$room) {
      if (!is_array($room)) {
        continue;
      }

      $candidate_room_id = (string) ($room['room_id'] ?? $room['id'] ?? '');
      if ($candidate_room_id !== $room_id) {
        continue;
      }

      $room['chat'] = is_array($room['chat'] ?? NULL) ? $room['chat'] : [];
      $resolved_room_name = trim((string) ($room['name'] ?? $room_name));
      $resolved_room_description = trim((string) ($room['description'] ?? $room_description));
      $seed_message = $this->buildStarterRoomSeedNarration($resolved_room_name, $resolved_room_description);

      foreach ($room['chat'] as $message) {
        if (($message['speaker'] ?? '') === 'Game Master'
          && ($message['message'] ?? '') === $seed_message) {
          return;
        }
      }

      $room['chat'][] = [
        'speaker' => 'Narrator',
        'message' => $seed_message,
        'type' => 'narrator',
        'channel' => 'room',
        'timestamp' => date('c', $now),
        'character_id' => NULL,
        'user_id' => NULL,
      ];

      $this->database->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE),
          'updated' => $now,
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('dungeon_id', $dungeon_id)
        ->execute();
      return;
    }
  }

  private function prefixInitialEncounterNarration(string $speaker, string $message): string {
    $message = trim($message);
    if ($message === '') {
      return $message;
    }

    if (\Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::isPrefixed($message)) {
      return $message;
    }

    $speaker = trim($speaker) !== '' ? trim($speaker) : 'Narrator';
    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::formatPrefix(0, 1, $speaker) . $message;
  }

  /**
   * Build the starter room opener text for GM narration.
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

  /**
   * Build and prefix starter room narration for initial campaign room feeds.
   */
  private function buildStarterRoomSeedNarration(string $room_name, string $room_description): string {
    return $this->prefixInitialEncounterNarration(
      'Narrator',
      $this->buildStarterRoomIntroMessage($room_name, $room_description)
    );
  }

}
