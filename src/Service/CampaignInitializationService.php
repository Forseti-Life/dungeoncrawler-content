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

  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleExtensionList $module_list,
    QuestGeneratorService $quest_generator,
    CampaignNameGeneratorService $campaign_name_generator,
    ?ChatSessionManager $chat_session_manager = NULL,
    ?NpcSheetGenerationService $npc_sheet_generation_service = NULL,
    ?RoomViewImageService $room_view_image_service = NULL
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->moduleList = $module_list;
    $this->questGenerator = $quest_generator;
    $this->campaignNameGenerator = $campaign_name_generator;
    $this->chatSessionManager = $chat_session_manager;
    $this->npcSheetGenerationService = $npc_sheet_generation_service;
    $this->roomViewImageService = $room_view_image_service;
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

      // 2. Create default starter dungeon
      $dungeon_id = $this->createStarterDungeon($campaign_id, $theme, $now);
      if (!$dungeon_id) {
        $transaction->rollBack();
        $this->logger->error('Failed to create starter dungeon for campaign {campaign_id}', [
          'campaign_id' => $campaign_id,
        ]);
        return 0;
      }

      $starter_room = $this->loadStarterRoomSeed();
      if ($starter_room === NULL) {
        $transaction->rollBack();
        $this->logger->error('Failed to load starter tavern seed for campaign {campaign_id}', [
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

      $this->seedStarterQuests($campaign_id, $difficulty, $now);

      // 5. Bootstrap hierarchical chat sessions for the campaign.
      //    Include the starter dungeon and tavern room so they get
      //    dedicated sessions from the very start.
      $starter_runtime_room_id = (string) ($starter_room['runtime_room_id'] ?? $starter_room['room_id'] ?? 'tavern_entrance');

      $this->bootstrapChatSessions(
        $campaign_id,
        $campaign_name,
        $dungeon_id,
        $starter_runtime_room_id,
        (string) ($starter_room['name'] ?? 'The Gilded Tankard')
      );
      $this->seedStarterRoomChatHistory(
        $campaign_id,
        $dungeon_id,
        $starter_runtime_room_id,
        (string) ($starter_room['name'] ?? 'The Gilded Tankard'),
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
      'schema_version' => '1.0.0',
      'created_by' => $uid,
      'started' => FALSE,
      'progress' => [],
      'initialized_at' => $now,
    ];

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
    int $now
  ): string|FALSE {
    // Try to load the full tavern-entrance-dungeon.json seed payload first.
    $seed_payload = $this->loadTavernDungeonSeed();

    if ($seed_payload !== NULL) {
      $dungeon_id = (string) ($seed_payload['hex_map']['map_id'] ?? 'tavern-' . $campaign_id);
      $dungeon_name = (string) ($seed_payload['name'] ?? 'Tavern Entrance');
      $dungeon_description = (string) ($seed_payload['flavor_text'] ?? 'The adventure begins at the tavern entrance.');
      $dungeon_theme = (string) ($seed_payload['custom_theme'] ?? $seed_payload['theme'] ?? $theme);

      // Merge obstacle objects into the payload so hexmap JS has them.
      $obstacle_path = $this->getModulePath() . '/config/examples/tavern-obstacle-objects.json';
      $obstacle_catalog = $this->readJsonFile($obstacle_path);
      if (is_array($obstacle_catalog) && !empty($obstacle_catalog['objects'])) {
        // Merge catalog definitions into the seed payload's existing definitions.
        // Index by object_id so the catalog augments (not overwrites) the seed.
        $existing_defs = [];
        foreach (($seed_payload['object_definitions'] ?? []) as $def) {
          if (is_array($def) && !empty($def['object_id'])) {
            $existing_defs[$def['object_id']] = $def;
          }
        }
        foreach ($obstacle_catalog['objects'] as $catalog_def) {
          if (is_array($catalog_def) && !empty($catalog_def['object_id'])) {
            $oid = $catalog_def['object_id'];
            if (!isset($existing_defs[$oid])) {
              $existing_defs[$oid] = $catalog_def;
            }
          }
        }
        $seed_payload['object_definitions'] = array_values($existing_defs);
      }

      $this->database->insert('dc_campaign_dungeons')
        ->fields([
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
          'name' => $dungeon_name,
          'description' => $dungeon_description,
          'theme' => $dungeon_theme,
          'dungeon_data' => json_encode($seed_payload, JSON_UNESCAPED_UNICODE),
          'source_dungeon_id' => 'tavern-entrance-default',
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();

      return $dungeon_id;
    }

    // Fallback: themed stub when seed file is missing.
    $dungeon_id = 'starter_dungeon_' . $campaign_id;
    $dungeon_names = [
      'classic_dungeon' => 'The Forsaken Crypt',
      'goblin_warrens' => 'Goblin Warren',
      'undead_crypt' => 'Undead Tomb',
    ];
    $dungeon_name = $dungeon_names[$theme] ?? 'The Forsaken Crypt';

    $dungeon_descriptions = [
      'classic_dungeon' => 'An ancient crypt shrouded in mystery, with secrets waiting to be uncovered.',
      'goblin_warrens' => 'A chaotic warren of goblin tunnels and chambers, full of mischief and danger.',
      'undead_crypt' => 'A cursed tomb where the dead refuse to rest, filled with undead horrors.',
    ];
    $dungeon_description = $dungeon_descriptions[$theme] ?? 'An ancient mystery waiting to be explored.';

    $dungeon_data = [
      'schema_version' => '1.0.0',
      'depth' => 1,
      'rooms' => [
        'tavern_entrance' => [
          'position' => [0, 0],
          'connections' => [],
          'priority' => 'entry',
        ],
      ],
      'theme' => $theme,
      'generated_at' => $now,
    ];

    $this->database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'name' => $dungeon_name,
        'description' => $dungeon_description,
        'theme' => $theme,
        'dungeon_data' => json_encode($dungeon_data, JSON_PRETTY_PRINT),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    return $dungeon_id;
  }

  /**
   * Load the tavern-entrance-dungeon.json seed file.
   *
   * @return array|null
   *   Decoded dungeon payload, or NULL if unavailable.
   */
  private function loadTavernDungeonSeed(): ?array {
    $path = $this->getModulePath() . '/config/examples/tavern-entrance-dungeon.json';
    return $this->readJsonFile($path);
  }

  /**
   * Load the canonical starter-room seed used for new campaigns.
   *
   * The tavern room slug remains `tavern_entrance` for compatibility with
   * dc_campaign_rooms, while runtime surfaces (chat, hexmap, room view) use the
   * authored room UUID from the dungeon seed when available.
   *
   * @return array|null
   *   Starter room data, or NULL if unavailable.
   */
  private function loadStarterRoomSeed(): ?array {
    $room_data = $this->readJsonFile($this->getModulePath() . '/tavern_entrance_room.json');
    if ($room_data === NULL) {
      return NULL;
    }

    $seed_payload = $this->loadTavernDungeonSeed();
    $seed_room = is_array($seed_payload['rooms'][0] ?? NULL) ? $seed_payload['rooms'][0] : [];

    if ($seed_room !== []) {
      $room_data['name'] = (string) ($seed_room['name'] ?? ($room_data['name'] ?? 'The Gilded Tankard'));
      $room_data['description'] = (string) ($seed_room['description'] ?? ($room_data['description'] ?? ''));
      $seed_room_id = trim((string) ($seed_room['room_id'] ?? $seed_room['id'] ?? ''));
      if ($seed_room_id !== '') {
        $room_data['runtime_room_id'] = $seed_room_id;
      }
    }

    $room_data['room_id'] = trim((string) ($room_data['room_id'] ?? 'tavern_entrance'));
    if ($room_data['room_id'] === '') {
      $room_data['room_id'] = 'tavern_entrance';
    }

    if (empty($room_data['runtime_room_id'])) {
      $room_data['runtime_room_id'] = $room_data['room_id'];
    }

    return $room_data;
  }

  /**
   * Read and decode a JSON file.
   *
   * @param string $path
   *   Absolute file path.
   *
   * @return array|null
   *   Decoded array, or NULL on failure.
   */
  private function readJsonFile(string $path): ?array {
    if (!is_file($path)) {
      return NULL;
    }
    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      return NULL;
    }
    $decoded = json_decode($contents, TRUE);
    return is_array($decoded) ? $decoded : NULL;
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
    $room_id = (string) ($starter_room['room_id'] ?? 'tavern_entrance');
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
        'room_id' => $room_id,
        'name' => $room_name,
        'description' => $room_description,
        'environment_tags' => json_encode($starter_room['environment_tags'] ?? ['indoor', 'tavern', 'safe', 'starting_area']),
        'layout_data' => json_encode($layout_data, JSON_PRETTY_PRINT),
        'contents_data' => json_encode($contents_data, JSON_PRETTY_PRINT),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    // Initialize room state
    $this->database->insert('dc_campaign_room_states')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
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
          'room_id' => $room_id,
          'content_id' => $item['content_id'],
        ],
      ];

      $this->database->insert('dc_campaign_item_instances')
        ->fields([
          'campaign_id' => $campaign_id,
          'item_instance_id' => sprintf('room_item_%d_%s', $campaign_id, $item['content_id']),
          'item_id' => $item['content_id'],
          'location_type' => 'room',
          'location_ref' => $room_id,
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
      $state_data = [
        'content_id' => $npc['content_id'],
        'role' => $npc['role'] ?? 'npc',
        'description' => $npc['description'] ?? '',
        'quests' => $npc['quests'] ?? [],
        'animation_state' => 'idle',
      ];

      $this->database->insert('dc_campaign_characters')
        ->fields([
          'campaign_id' => $campaign_id,
          'character_id' => 0,
          'name' => $npc['name'],
          'level' => 0,
          'ancestry' => 'humanoid',
          'class' => 'npc',
          'hp_current' => 0,
          'hp_max' => 0,
          'armor_class' => 0,
          'experience_points' => 0,
          'position_q' => $npc['position']['q'],
          'position_r' => $npc['position']['r'],
          'last_room_id' => $room_id,
          'instance_id' => $instance_id,
          'type' => 'npc',
          'state_data' => json_encode($state_data),
          'location_type' => 'room',
          'location_ref' => $room_id,
          'is_active' => 1,
          'uid' => 0,
          'role' => 'npc',
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
          'name' => $npc['name'],
          'role' => $npc['role'] ?? 'npc',
          'description' => $npc['description'] ?? '',
          'backstory' => $npc['backstory'] ?? '',
          'stats' => [
            'currentHp' => 0,
            'maxHp' => 0,
            'ac' => 0,
          ],
          'equipment' => $npc['equipment'] ?? [],
          'level' => 1,
          'ancestry' => 'Humanoid',
          'class' => 'npc',
        ], FALSE);
      }
    }

    if ($this->npcSheetGenerationService) {
      $this->npcSheetGenerationService->launchDetachedWorker();
    }

    return TRUE;
  }

  /**
   * Seed starter quest templates and create initial campaign quests.
   */
  private function seedStarterQuests(int $campaign_id, string $difficulty, int $now): void {
    if (!$this->database->schema()->tableExists('dungeoncrawler_content_quest_templates')
      || !$this->database->schema()->tableExists('dc_campaign_quests')) {
      return;
    }

    $npc_ids = $this->resolveNpcInstanceIds($campaign_id, ['tavern_keeper', 'scholar_npc']);

    $starter_templates = [
      'gather_wine' => [
        'item_name' => 'wine bottle',
        'giver_npc_id' => $npc_ids['tavern_keeper'] ?? NULL,
      ],
      'gather_torch_components' => [
        'item_name' => 'torch components',
        'giver_npc_id' => $npc_ids['tavern_keeper'] ?? NULL,
      ],
      'gather_two_room_items' => [
        'item_name' => 'room supplies',
        'giver_npc_id' => $npc_ids['tavern_keeper'] ?? NULL,
      ],
      'collect_spellbooks' => [
        'item_name' => 'spellbooks',
        'giver_npc_id' => $npc_ids['scholar_npc'] ?? NULL,
      ],
    ];

    $this->ensureQuestTemplatesLoaded(array_keys($starter_templates), $now);

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
        'location' => 'tavern_entrance',
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
   * Ensure quest templates are loaded from module JSON files.
   */
  private function ensureQuestTemplatesLoaded(array $template_ids, int $now): void {
    $module_path = $this->moduleList->getPath('dungeoncrawler_content');
    $template_dir = DRUPAL_ROOT . '/' . $module_path . '/templates/quests';

    foreach ($template_ids as $template_id) {
      $existing = $this->database->select('dungeoncrawler_content_quest_templates', 't')
        ->fields('t', ['id'])
        ->condition('template_id', $template_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($existing) {
        continue;
      }

      $path = $template_dir . '/' . $template_id . '.json';
      $template = $this->readJsonFile($path);
      if (!is_array($template)) {
        $this->logger->warning('Quest template file not found or invalid: {path}', ['path' => $path]);
        continue;
      }

      if (empty($template['template_id']) || empty($template['name']) || empty($template['quest_type'])) {
        $this->logger->warning('Quest template missing required fields: {path}', ['path' => $path]);
        continue;
      }

      $record = [
        'template_id' => $template['template_id'],
        'name' => $template['name'],
        'description' => $template['description'] ?? '',
        'quest_type' => $template['quest_type'],
        'level_min' => $template['level_min'] ?? 1,
        'level_max' => $template['level_max'] ?? 20,
        'objectives_schema' => json_encode($template['objectives_schema'] ?? []),
        'rewards_schema' => json_encode($template['rewards_schema'] ?? []),
        'prerequisites' => isset($template['prerequisites']) ? json_encode($template['prerequisites']) : NULL,
        'tags' => isset($template['tags']) ? json_encode($template['tags']) : NULL,
        'story_impact' => isset($template['story_impact']) ? json_encode($template['story_impact']) : NULL,
        'estimated_duration_minutes' => $template['estimated_duration_minutes'] ?? NULL,
        'version' => $template['version'] ?? '1.0.0',
        'created' => $now,
        'updated' => $now,
      ];

      $this->database->insert('dungeoncrawler_content_quest_templates')
        ->fields($record)
        ->execute();
    }
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
   */
  private function bootstrapChatSessions(
    int $campaign_id,
    string $campaign_name,
    string $dungeon_id = '',
    string $room_id = '',
    string $room_name = ''
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
          $this->chatSessionManager->postMessage(
            (int) $room_session['id'],
            $campaign_id,
            'Game Master',
            'gm',
            '',
            $room_name
              ? "You arrive at {$room_name}. The adventure begins..."
              : 'You enter the room. The adventure begins...',
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
      foreach ($room['chat'] as $message) {
        if (($message['speaker'] ?? '') === 'Game Master'
          && ($message['message'] ?? '') === "You arrive at {$room_name}. The adventure begins...") {
          return;
        }
      }

      $room['chat'][] = [
        'speaker' => 'Game Master',
        'message' => "You arrive at {$room_name}. The adventure begins...",
        'type' => 'gm',
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

}
