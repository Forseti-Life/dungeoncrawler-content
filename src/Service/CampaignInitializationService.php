<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;

/**
 * Orchestrates complete campaign initialization with default dungeon and rooms.
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
    ?ChatSessionManager $chat_session_manager = NULL,
    ?NpcSheetGenerationService $npc_sheet_generation_service = NULL,
    ?RoomViewImageService $room_view_image_service = NULL,
    ?StorylineManagerService $storyline_manager = NULL,
    ?RelationshipManagerService $relationship_manager = NULL
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

    $transaction = $this->database->startTransaction('campaign_init');
    try {
      // 1. Create campaign record
      $campaign_id = $this->createCampaign($uid, $campaign_name, $theme, $difficulty, $now);
      if (!$campaign_id) {
        return 0;
      }

      $starter_room = $this->loadStarterRoomSeed();
      if ($starter_room === NULL) {
        $transaction->rollBack();
        $this->logger->error('Failed to load explicit starter tavern asset for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }

      // 2. Create default starter dungeon
      $dungeon_id = $this->createStarterDungeon($campaign_id, $theme, $now, $starter_room);
      if (!$dungeon_id) {
        $transaction->rollBack();
        $this->logger->error('Failed to create starter dungeon for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }

      // 3. Load Tavern Entrance room and content
      if (!$this->loadTavernEntranceRoom($campaign_id, $now, $starter_room)) {
        $transaction->rollBack();
        $this->logger->error('Failed to load tavern entrance for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }

      $starter_room_ids = $this->resolveStarterRoomIdentifiers($starter_room);
      $starter_runtime_room_id = $starter_room_ids['runtime_room_id'];
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
      $this->seedStarterRoomChatHistory(
        $campaign_id,
        $dungeon_id,
        $starter_runtime_room_id,
        (string) ($starter_room['name'] ?? 'The Gilded Tankard'),
        (string) ($starter_room['description'] ?? ''),
        $now
      );

      if ($this->roomViewImageService) {
        $this->roomViewImageService->warmRoomViewImageCache($starter_room, [
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
          'room_id' => $starter_runtime_room_id,
        ]);
      }

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
    $dungeon_name = (string) ($starter_room['name'] ?? 'Starter Tavern');
    $dungeon_description = (string) ($starter_room['description'] ?? 'Starter tavern asset.');
    $dungeon_theme = $theme !== '' ? $theme : 'starter_asset';
    $room_payload = [
      'room_id' => $runtime_room_id,
      'source_room_id' => (string) ($starter_room['room_id'] ?? $runtime_room_id),
      'name' => $dungeon_name,
      'description' => $dungeon_description,
      'hexes' => is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [],
      'entry_points' => is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [],
      'exit_points' => is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [],
      'exits' => is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [],
      'terrain' => is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : [],
      'lighting' => is_array($layout_data['lighting'] ?? NULL) ? $layout_data['lighting'] : [],
    ];
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
        'connections' => [],
        'regions' => [
          [
            'region_id' => 'starter-tavern-region',
            'name' => $dungeon_name,
            'description' => $dungeon_description,
            'room_ids' => [$runtime_room_id],
            'ambient_hazard_level' => 0,
          ],
        ],
        'metadata' => [
          'created_at' => gmdate('c', $now),
          'generated_by' => 'asset-library',
          'is_finalized' => TRUE,
          'total_rooms' => 1,
          'explored_rooms' => 0,
          'exploration_percentage' => 0,
        ],
      ],
      'rooms' => [$room_payload],
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

    return $dungeon_id;
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
    $canonical_override = $this->loadCanonicalStarterRoomOverride('tavern_entrance');
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

    if (is_array($canonical_override)) {
      $record['name'] = $canonical_override['name'] ?? $record['name'];
      $record['description'] = $canonical_override['description'] ?? $record['description'];
      $record['environment_tags'] = json_encode($canonical_override['environment_tags'] ?? $this->decodeJsonArray($record['environment_tags'] ?? NULL));
      $record['layout_data'] = json_encode($canonical_override['layout_data'] ?? $this->decodeJsonArray($record['layout_data'] ?? NULL));
      $record['contents_data'] = json_encode($canonical_override['contents_data'] ?? $this->decodeJsonArray($record['contents_data'] ?? NULL));
      $record['source_room_id'] = $canonical_override['source_room_id'] ?? $record['source_room_id'];
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

    return [
      'room_id' => $room_id,
      'runtime_room_id' => $runtime_room_id,
      'name' => (string) ($record['name'] ?? 'The Gilded Tankard'),
      'description' => (string) ($record['description'] ?? ''),
      'environment_tags' => $this->decodeJsonArray($record['environment_tags'] ?? NULL),
      'layout_data' => $this->decodeJsonArray($record['layout_data'] ?? NULL),
      'contents_data' => $this->decodeJsonArray($record['contents_data'] ?? NULL),
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

    // Room layout data is authoritative from the dungeon seed payload
    // (dc_campaign_dungeons.dungeon_data). This field stores a reference
    // marker; the real hex grid lives in the dungeon data.
    $layout_data = [
      'source' => 'dungeon_data',
      'note' => 'Hex grid is authoritative from dc_campaign_dungeons.dungeon_data. This field is retained for schema compatibility.',
    ];

    $contents_data = is_array($starter_room['contents_data'] ?? NULL) ? $starter_room['contents_data'] : [];

    // Create room record
    $this->database->insert('dc_campaign_rooms')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $runtime_room_id,
        'name' => $room_name,
        'description' => $room_description,
        'environment_tags' => json_encode($starter_room['environment_tags'] ?? ['indoor', 'tavern', 'safe', 'starting_area']),
        'layout_data' => json_encode($layout_data, JSON_PRETTY_PRINT),
        'contents_data' => json_encode($contents_data, JSON_PRETTY_PRINT),
        'source_room_id' => $source_room_id,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

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
      $schema_data = [
        'position' => $item['position'] ?? [],
        'description' => $item['name'] ?? '',
        'quest_association' => $item['quest_association'] ?? NULL,
      ];

      $this->database->insert('dc_campaign_content_registry')
        ->fields([
          'campaign_id' => $campaign_id,
          'content_type' => 'item',
          'content_id' => $item['content_id'],
          'name' => $item['name'] ?? 'Unknown',
          'rarity' => 'common',
          'tags' => json_encode(['collectible', 'tavern']),
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
        'quest_association' => $item['quest_association'] ?? NULL,
        'tags' => ['collectible', 'tavern'],
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

    // Create NPCs
    foreach ($contents_data['npcs'] as $npc) {
      $instance_id = 'npc_' . $npc['content_id'];
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
        'content_id' => $npc['content_id'],
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
        'content_id' => $npc['content_id'],
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

      $this->database->insert('dc_campaign_characters')
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

      if ($this->npcSheetGenerationService) {
        $this->npcSheetGenerationService->enqueueNpcSheetGeneration($campaign_id, $instance_id, [
          'entity_ref' => $instance_id,
          'instance_id' => $instance_id,
          'content_id' => (string) ($npc['content_id'] ?? $instance_id),
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

    return TRUE;
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
   * Seed starter quest templates and create initial campaign quests.
   */
  private function seedStarterQuests(int $campaign_id, string $difficulty, int $now, string $starter_runtime_room_id): void {
    if (!$this->database->schema()->tableExists('dungeoncrawler_content_quest_templates')
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
      $existing = $this->database->select('dc_campaign_quests', 'q')
        ->fields('q', ['quest_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('source_template_id', $template_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($existing) {
        continue;
      }

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

      $this->database->insert('dc_campaign_quests')
        ->fields($quest_data)
        ->execute();
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
      return;
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

    $instance_ids = array_map(static function (string $content_id): string {
      return 'npc_' . $content_id;
    }, $content_ids);

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
      $existing = $this->database->select('dungeoncrawler_content_quest_templates', 't')
        ->fields('t', ['id'])
        ->condition('template_id', $template_id)
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
        $this->database->update('dungeoncrawler_content_quest_templates')
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
        'story_impact' => (string) ($entry['story_impact'] ?? ''),
        'estimated_duration_minutes' => isset($entry['estimated_duration_minutes']) ? (int) $entry['estimated_duration_minutes'] : NULL,
        'updated' => $this->time->getRequestTime(),
        'version' => (string) ($entry['version'] ?? '1.0.0'),
      ];
    }

    return NULL;
  }

  /**
   * Load the canonical tavern starter room override from the source file.
   */
  private function loadCanonicalStarterRoomOverride(string $room_id): ?array {
    $path = $this->moduleList->getPath('dungeoncrawler_content') . '/config/examples/templates/dungeoncrawler_content_rooms/default_room_templates.json';
    if (!is_file($path)) {
      return NULL;
    }

    $decoded = json_decode((string) file_get_contents($path), TRUE);
    $rows = is_array($decoded['rows'] ?? NULL) ? $decoded['rows'] : [];
    foreach ($rows as $row) {
      if (is_array($row) && trim((string) ($row['room_id'] ?? '')) === $room_id) {
        return $row;
      }
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
          $seed_message = $this->buildStarterRoomIntroMessage($room_name, $room_description);

          $this->chatSessionManager->postMessage(
            (int) $room_session['id'],
            $campaign_id,
            'Narrator',
            'narrator',
            '',
            $this->prefixInitialEncounterNarration('Narrator', $seed_message),
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
      $seed_message = $this->prefixInitialEncounterNarration(
        'Narrator',
        $this->buildStarterRoomIntroMessage($resolved_room_name, $resolved_room_description)
      );

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

}
