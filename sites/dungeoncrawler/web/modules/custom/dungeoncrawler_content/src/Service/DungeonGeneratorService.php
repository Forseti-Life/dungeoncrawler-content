<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates AI-driven procedural dungeon generation.
 *
 * Responsible for:
 * - Checking/caching multiple dungeon levels
 * - Determining dungeon depth based on party level
 * - Selecting thematic content
 * - Orchestrating room generation for each level
 * - Connecting rooms via Delaunay triangulation
 * - Validating XP budgets across encounters
 * - Persisting complete dungeon structure
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class DungeonGeneratorService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * The room generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomGeneratorService
   */
  protected RoomGeneratorService $roomGenerator;

  /**
   * The room connection algorithm service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomConnectionAlgorithm
   */
  protected RoomConnectionAlgorithm $roomConnector;

  /**
   * The encounter balancer service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EncounterBalancer
   */
  protected EncounterBalancer $encounterBalancer;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * Active deterministic RNG sequence for current generation pass.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SeededRandomSequence|null
   */
  protected ?SeededRandomSequence $rng = NULL;

  /**
   * Constructs a DungeonGeneratorService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   * @param \Drupal\dungeoncrawler_content\Service\RoomGeneratorService $room_generator
   *   The room generator service.
   * @param \Drupal\dungeoncrawler_content\Service\RoomConnectionAlgorithm $room_connector
   *   The room connection algorithm service.
   * @param \Drupal\dungeoncrawler_content\Service\EncounterBalancer $encounter_balancer
   *   The encounter balancer service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    SchemaLoader $schema_loader,
    RoomGeneratorService $room_generator,
    RoomConnectionAlgorithm $room_connector,
      EncounterBalancer $encounter_balancer,
      NumberGenerationService $number_generation
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->schemaLoader = $schema_loader;
    $this->roomGenerator = $room_generator;
    $this->roomConnector = $room_connector;
    $this->encounterBalancer = $encounter_balancer;
    $this->numberGeneration = $number_generation;
  }

  /**
   * Generate a complete dungeon with multiple levels.
   *
   * Workflow:
   * 1. Check if dungeon already exists at location (return cached)
   * 2. Validate input parameters
   * 3. Determine dungeon depth based on party level
   * 4. Select theme (auto-select or override)
   * 5. For each level (1 to depth):
   *    - Generate hexmap
   *    - Generate multiple rooms
   *    - Connect rooms using Delaunay + MST algorithm
   *    - Place entities with encounter balancing
   *    - Validate XP budget
   * 6. Validate entire dungeon structure
   * 7. Persist complete dungeon to database
   *
   * @param array $context
   *   Generation context with keys:
   *   - campaign_id: int - Campaign ID
   *   - location_x: int - World X coordinate
   *   - location_y: int - World Y coordinate
   *   - party_level: int - Average party level (1-20)
   *   - party_size: int - Number of party members
   *   - party_composition: array - Class breakdown
   *       Example: { "fighter": 1, "wizard": 1, "cleric": 1, "rogue": 1 }
   *   - theme: string|null - Override theme or null for auto-select
   *   - ai_service: object - Optional AI service
   *
   * @return array
   *   Complete dungeon structure with keys:
   *   - dungeon_id: string (UUID)
   *   - name: string
   *   - theme: string
   *   - depth: int (number of levels)
   *   - location_x: int
   *   - location_y: int
   *   - levels: array of dungeon_level.schema.json objects
   *
   * @throws \Drupal\dungeoncrawler_content\Exception\GenerationException
   *   If generation fails
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  public function generateDungeon(array $context): array {
    $this->logger->info('Generating dungeon at location (@x, @y) for campaign @campaign', [
      '@x' => $context['location_x'],
      '@y' => $context['location_y'],
      '@campaign' => $context['campaign_id'],
    ]);

    // Step 1: Validate input
    $this->validateContext($context);

    // Step 2: Set seed for reproducible generation
    if (!isset($context['seed'])) {
      $context['seed'] = $this->numberGeneration->rollRange(1, 2147483647);
    }
    $this->rng = new SeededRandomSequence((int) $context['seed']);

    // Step 3: Select theme (auto-select or override)
    $theme = $context['theme'] ?? $this->selectTheme(
      $context['location_x'],
      $context['location_y'],
      $context['party_level']
    );
    $context['theme'] = $theme;

    // Step 4: Determine dungeon depth
    $depth = $this->calculateDungeonDepth($context['party_level']);

    // Step 5: Generate each level
    $levels = [];
    for ($d = 1; $d <= $depth; $d++) {
      $context['depth'] = $d;
      $context['level_id'] = $d;
      $level = $this->generateLevel($context);
      $levels[] = $level;
    }

    // Step 6: Build complete dungeon structure (normalizer-compatible format).
    // Flatten rooms and entities from levels for normalizeDungeonPayload().
    $all_rooms = [];
    $all_entities = [];
    $all_connections = [];
    foreach ($levels as $level) {
      foreach (($level['rooms'] ?? []) as $room) {
        $all_rooms[] = $room;
        foreach (($room['creatures'] ?? []) as $entity) {
          $all_entities[] = $entity;
        }
      }
      foreach (($level['connections'] ?? []) as $conn) {
        $all_connections[] = $conn;
      }
    }

    $first_level = $levels[0] ?? [];
    $dungeon_id = sprintf('dungeon_%d_%d_%d',
      $context['campaign_id'],
      $context['location_x'],
      $context['location_y']
    );

    $dungeon_data = [
      'schema_version' => '1.0.0',
      'dungeon_id' => $dungeon_id,
      'name' => $this->generateDungeonName($theme, $context),
      'theme' => $theme,
      'depth' => $depth,
      'location_x' => $context['location_x'],
      'location_y' => $context['location_y'],
      'level_id' => $first_level['level_id'] ?? '',
      'hex_map' => [
        'map_id' => $dungeon_id,
        'connections' => $all_connections,
      ],
      'rooms' => $all_rooms,
      'entities' => $all_entities,
      'object_definitions' => [],
      'levels' => $levels,
      'generation_context' => [
        'party_level' => $context['party_level'],
        'party_size' => $context['party_size'],
        'seed' => $context['seed'],
        'generated_at' => date('c'),
      ],
    ];

    // Step 7: Persist complete dungeon to database.
    $db_dungeon_id = $this->persistDungeon($context, $levels);
    if ($db_dungeon_id) {
      $dungeon_data['persisted'] = TRUE;
      $dungeon_data['dungeon_id'] = $db_dungeon_id;
    }

    $this->logger->info('Dungeon generation complete: @name with @depth levels', [
      '@name' => $dungeon_data['name'],
      '@depth' => $depth,
    ]);

    return $dungeon_data;
  }

  /**
   * Generate a single dungeon level.
   *
   * Orchestrates the full generation pipeline for one floor.
   *
   * @param array $context
   *   Generation context. @see self::generateDungeon() with additional:
   *   - depth: int - 1-based level number
   *   - theme: string - Already determined theme
   *
   * @return array
   *   Complete dungeon_level.schema.json structure:
   *   - level_id: string (UUID)
   *   - depth: int
   *   - theme: string
   *   - name: string
   *   - hex_map: object
   *   - rooms: array of room.schema.json objects
   *   - entities: array of placed entity_instance objects
   *   - generation_rules: object with party_level_target, etc.
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  public function generateLevel(array $context): array {
    $this->logger->info('Generating level @depth for theme @theme', [
      '@depth' => $context['depth'],
      '@theme' => $context['theme'],
    ]);

    // Step 1: Determine room count for this level
    $room_count = $this->calculateRoomCount($context);

    // Step 2: Generate all rooms for this level
    $rooms = [];
    for ($i = 0; $i < $room_count; $i++) {
      $room_context = array_merge($context, [
        'room_index' => $i,
        'dungeon_id' => $context['campaign_id'],
        'terrain_type' => $this->selectTerrainType($context['theme']),
        'room_type' => $this->selectRoomType($i, $room_count),
      ]);

      // Vary difficulty across rooms
      $room_context['difficulty'] = $this->selectRoomDifficulty($i, $room_count, $context['depth']);

      $room = $this->roomGenerator->generateRoom($room_context);
      $rooms[] = $room;
    }

    // Step 3: Connect rooms (basic linear for now, Delaunay later)
    $connections = $this->connectRoomsInLevel($rooms, $context);

    // Step 4: Build level structure
    $level_data = [
      'level_id' => sprintf('level_%d_%d',
        $context['campaign_id'],
        $context['depth']
      ),
      'depth' => $context['depth'],
      'theme' => $context['theme'],
      'name' => sprintf('Level %d - %s', $context['depth'], ucfirst($context['theme'])),
      'room_count' => count($rooms),
      'rooms' => $rooms,
      'connections' => $connections,
      'generation_rules' => [
        'party_level' => $context['party_level'],
        'party_size' => $context['party_size'] ?? 4,
        'difficulty_distribution' => $this->getDifficultyDistribution($rooms),
      ],
    ];

    $this->logger->info('Level @depth complete with @count rooms', [
      '@depth' => $context['depth'],
      '@count' => count($rooms),
    ]);

    return $level_data;
  }

  /**
   * Select dungeon theme based on location and party level.
   *
   * Map coordinates influence theme selection:
   * - Northern mountains → dragon_lair, crystal_caves
   * - Forest → beast_den, spider_nests
   * - Underdark → undead_crypts, demonic_sanctum
   * - Volcanic → lava_forge, elemental_nexus
   *
   * @param int $x
   *   World X coordinate
   * @param int $y
   *   World Y coordinate
   * @param int $party_level
   *   Average party level
   *
   * @return string
   *   Theme key matching dungeon_level.schema.json enum
   */
  protected function selectTheme(int $x, int $y, int $party_level): string {
    // Map coordinates to theme selection
    // For now, use simple hashing based on location
    $hash = abs(crc32(sprintf('%d,%d', $x, $y)));

    $themes = [
      'dungeon',
      'cave',
      'crypt',
      'ruins',
      'underground',
    ];

    // Higher level parties get more dangerous themes
    if ($party_level >= 10) {
      $themes[] = 'demonic';
      $themes[] = 'underdark';
    }

    return $themes[$hash % count($themes)];
  }

  /**
   * Calculate dungeon depth (number of levels).
   *
   * Higher party levels = deeper dungeons (more exploration).
   * Scaling:
   * - Levels 1-6: 1-2 levels
   * - Levels 7-15: 2-4 levels
   * - Levels 16-20: 3-5 levels
   *
   * @param int $party_level
   *   Average party level (1-20)
   *
   * @return int
   *   Number of levels to generate (1-5 typical, max 10)
   */
  protected function calculateDungeonDepth(int $party_level): int {
    // Scale depth with party level
    // Levels 1-4: 1-2 levels
    // Levels 5-9: 2-3 levels
    // Levels 10-14: 3-4 levels
    // Levels 15-20: 4-5 levels

    if ($party_level <= 4) {
      return $this->nextInt(1, 2);
    }
    elseif ($party_level <= 9) {
      return $this->nextInt(2, 3);
    }
    elseif ($party_level <= 14) {
      return $this->nextInt(3, 4);
    }
    else {
      return $this->nextInt(4, 5);
    }
  }

  /**
   * Generate hexmap for a level.
   *
   * Creates the hex terrain structure without rooms.
   *
   * @param array $context
   *   Generation context
   *
   * @return array
   *   hexmap.schema.json object:
   *   {
   *     "width": 40,
   *     "height": 30,
   *     "hexes": [...]
   *   }
   */
  protected function generateHexmap(array $context): array {
    $party_level = $context['party_level'] ?? 1;
    $depth = $context['depth'] ?? 1;

    // Scale hexmap size with party level and depth.
    $base_width = 30 + ($party_level * 2);
    $base_height = 20 + ($party_level * 2);
    $width = min(80, $base_width + ($depth * 5));
    $height = min(60, $base_height + ($depth * 5));

    $hexes = [];
    for ($q = 0; $q < $width; $q++) {
      for ($r = 0; $r < $height; $r++) {
        $hexes[] = [
          'q' => $q,
          'r' => $r,
          'terrain' => 'void',
          'elevation' => 0,
          'passable' => FALSE,
        ];
      }
    }

    return [
      'width' => $width,
      'height' => $height,
      'hexes' => $hexes,
    ];
  }

  /**
   * Calculate ideal room count for a level.
   *
   * Based on party level and depth.
   *
   * @param array $context
   *   Generation context
   *
   * @return int
   *   Number of rooms to generate
   */
  protected function calculateRoomCount(array $context): int {
    $party_level = $context['party_level'];
    $depth = $context['depth'];

    // Base room count on party level
    // Low level: 3-5 rooms
    // Mid level: 4-7 rooms
    // High level: 5-9 rooms

    if ($party_level <= 5) {
      $base = $this->nextInt(3, 5);
    }
    elseif ($party_level <= 10) {
      $base = $this->nextInt(4, 7);
    }
    else {
      $base = $this->nextInt(5, 9);
    }

    // Deeper levels may have slightly more rooms
    $depth_bonus = min(2, floor($depth / 2));

    return $base + $depth_bonus;
  }

  /**
   * Validate generation context.
   *
   * @param array $context
   *   Generation context
   *
   * @throws \InvalidArgumentException
   *   If context is invalid
   */
  protected function validateContext(array $context): void {
    if (empty($context['campaign_id'])) {
      throw new \InvalidArgumentException('campaign_id is required');
    }
    if (!isset($context['location_x']) || !isset($context['location_y'])) {
      throw new \InvalidArgumentException('location_x and location_y are required');
    }
    if (empty($context['party_level']) || $context['party_level'] < 1 || $context['party_level'] > 20) {
      throw new \InvalidArgumentException('party_level must be 1-20');
    }
    if (!isset($context['party_size'])) {
      $context['party_size'] = 4; // Default party size
    }
    if ($context['party_size'] < 1 || $context['party_size'] > 20) {
      throw new \InvalidArgumentException('party_size must be 1-20');
    }
  }

  /**
   * Persist complete dungeon to database.
   *
   * @param array $context
   *   Generation context
   * @param array $levels
   *   Array of generated levels
   *
   * @return string
   *   Dungeon ID (UUID)
   */
  protected function persistDungeon(array $context, array $levels): string {
    $now = time();
    $campaign_id = $context['campaign_id'];

    // Build dungeon_id.
    $dungeon_id = sprintf('dungeon_%d_%d_%d',
      $campaign_id,
      $context['location_x'] ?? 0,
      $context['location_y'] ?? 0
    );

    // Build dungeon_data JSON in normalizer-compatible format.
    // normalizeDungeonPayload() expects: rooms[], entities[], hex_map, level_id
    // at top level — NOT nested under levels[].
    $all_rooms = [];
    $all_entities = [];
    $all_connections = [];
    foreach ($levels as $level) {
      foreach (($level['rooms'] ?? []) as $room) {
        $all_rooms[] = $room;
        // Extract creature entities from room into top-level entities array.
        foreach (($room['creatures'] ?? []) as $creature_entity) {
          $all_entities[] = $creature_entity;
        }
      }
      foreach (($level['connections'] ?? []) as $conn) {
        $all_connections[] = $conn;
      }
    }

    $first_level = $levels[0] ?? [];
    $dungeon_data = json_encode([
      'schema_version' => '1.0.0',
      'level_id' => $first_level['level_id'] ?? '',
      'hex_map' => [
        'map_id' => $dungeon_id,
        'connections' => $all_connections,
      ],
      'rooms' => $all_rooms,
      'entities' => $all_entities,
      'object_definitions' => [],
      'generation_context' => [
        'party_level' => $context['party_level'],
        'party_size' => $context['party_size'] ?? 4,
        'seed' => $context['seed'] ?? 0,
        'generated_at' => date('c'),
      ],
    ]);

    $theme = $context['theme'] ?? 'dungeon';
    $name = $this->generateDungeonName($theme, $context);

    try {
      // Upsert dungeon record (may already exist from prior generation).
      $this->database->merge('dc_campaign_dungeons')
        ->keys([
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
        ])
        ->fields([
          'name' => $name,
          'description' => '',
          'theme' => $theme,
          'dungeon_data' => $dungeon_data,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();

      // Persist each room from each level.
      foreach ($levels as $level) {
        foreach (($level['rooms'] ?? []) as $room) {
          $room_id = $room['room_id'] ?? '';
          $layout_data = json_encode([
            'hexes' => $room['hexes'] ?? [],
            'hex_manifest' => $room['hex_manifest'] ?? [],
            'entry_points' => $room['entry_points'] ?? [],
            'exit_points' => $room['exit_points'] ?? [],
            'terrain' => $room['terrain'] ?? [],
            'lighting' => $room['lighting'] ?? [],
          ]);
          $contents_data = json_encode([
            'creatures' => $room['creatures'] ?? [],
            'items' => $room['items'] ?? [],
            'traps' => $room['traps'] ?? [],
            'hazards' => $room['hazards'] ?? [],
            'obstacles' => $room['obstacles'] ?? [],
            'interactables' => $room['interactables'] ?? [],
          ]);
          $env_tags = json_encode($room['environmental_effects'] ?? []);

          // Upsert room — RoomGeneratorService::persistRoom() may have already
          // inserted this row during generateRoom().
          $this->database->merge('dc_campaign_rooms')
            ->keys([
              'campaign_id' => $campaign_id,
              'room_id' => $room_id,
            ])
            ->fields([
              'name' => $room['name'] ?? 'Unknown Room',
              'description' => $room['description'] ?? '',
              'environment_tags' => $env_tags,
              'layout_data' => $layout_data,
              'contents_data' => $contents_data,
              'created' => $now,
              'updated' => $now,
            ])
            ->execute();

          // Persist creature entities into dc_campaign_characters.
          // Creatures are now entity_instance objects from EntityPlacerService.
          foreach (($room['creatures'] ?? []) as $creature) {
            $instance_id = $creature['instance_id'] ?? $creature['entity_instance_id'] ?? '';
            if (!$instance_id) {
              continue;
            }
            $content_id = $creature['entity_ref']['content_id'] ?? 'creature';
            $display_name = $creature['display_name'] ?? $creature['state']['metadata']['display_name'] ?? 'Unknown Creature';
            $creature_level = $creature['state']['metadata']['stats']['level'] ?? 1;
            $hp_max = $creature['state']['hit_points']['max'] ?? $creature['state']['metadata']['stats']['maxHp'] ?? 0;
            $hp_current = $creature['state']['hit_points']['current'] ?? $creature['state']['metadata']['stats']['currentHp'] ?? $hp_max;
            $ac = $creature['state']['metadata']['stats']['ac'] ?? 10;
            $hex = $creature['placement']['hex'] ?? [];

            try {
              $this->database->merge('dc_campaign_characters')
                ->keys([
                  'campaign_id' => $campaign_id,
                  'instance_id' => $instance_id,
                ])
                ->fields([
                  'character_id' => 0,
                  'name' => $display_name,
                  'level' => $creature_level,
                  'ancestry' => '',
                  'class' => $content_id,
                  'hp_current' => $hp_current,
                  'hp_max' => $hp_max,
                  'armor_class' => $ac,
                  'experience_points' => 0,
                  'position_q' => $hex['q'] ?? 0,
                  'position_r' => $hex['r'] ?? 0,
                  'last_room_id' => $room_id,
                  'type' => 'npc',
                  'status' => 1,
                  'uid' => 0,
                  'role' => 'creature',
                  'location_type' => 'room',
                  'location_ref' => $room_id,
                  'is_active' => 1,
                  'joined' => $now,
                  'created' => $now,
                  'changed' => $now,
                  'updated' => $now,
                  'version' => 0,
                ])
                ->execute();
            }
            catch (\Exception $e) {
              $this->logger->warning('Failed to persist creature @id: @error', [
                '@id' => $instance_id,
                '@error' => $e->getMessage(),
              ]);
            }
          }
        }
      }

      $this->logger->info('Dungeon @id persisted with @count levels', [
        '@id' => $dungeon_id,
        '@count' => count($levels),
      ]);

      return $dungeon_id;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist dungeon: @error', [
        '@error' => $e->getMessage(),
      ]);
      return '';
    }
  }

  /**
   * Select terrain type for room based on theme.
   *
   * @param string $theme
   *   Dungeon theme
   *
   * @return string
   *   Terrain type (room.schema.json terrain.type enum)
   */
  protected function selectTerrainType(string $theme): string {
    // Map themes to appropriate terrain types
    $theme_terrains = [
      'dungeon' => ['stone_floor', 'cobblestone', 'flagstone'],
      'cave' => ['dirt', 'stone_rough', 'gravel'],
      'crypt' => ['stone_floor', 'flagstone', 'marble'],
      'ruins' => ['stone_rough', 'cobblestone', 'rubble', 'overgrown'],
      'underground' => ['dirt', 'stone_rough', 'mud'],
      'demonic' => ['obsidian', 'lava_rock', 'sulfur'],
      'underdark' => ['stone_rough', 'crystal', 'fungal'],
      'sewer' => ['mud', 'water_shallow', 'slime'],
      'mine' => ['stone_rough', 'gravel', 'ore_deposits'],
    ];

    $options = $theme_terrains[$theme] ?? ['stone_floor'];
    return $this->pick($options);
  }

  /**
   * Select room type based on position in dungeon.
   *
   * @param int $index
   *   Room index
   * @param int $total
   *   Total room count
   *
   * @return string
   *   Room type: 'chamber', 'corridor', 'boss_room'
   */
  protected function selectRoomType(int $index, int $total): string {
    // First room is always chamber (entrance)
    if ($index === 0) {
      return 'chamber';
    }

    // Last room is boss room
    if ($index === $total - 1) {
      return 'boss_room';
    }

    // Some corridors for variety
    if ($this->chance(30)) {
      return 'corridor';
    }

    return 'chamber';
  }

  /**
   * Select difficulty for room.
   *
   * @param int $index
   *   Room index
   * @param int $total
   *   Total room count
   * @param int $depth
   *   Dungeon depth
   *
   * @return string
   *   Difficulty: 'trivial', 'low', 'moderate', 'severe', 'extreme'
   */
  protected function selectRoomDifficulty(int $index, int $total, int $depth): string {
    // Boss room is always severe/extreme
    if ($index === $total - 1) {
      return $this->chance(50) ? 'severe' : 'extreme';
    }

    // First room is easier
    if ($index === 0) {
      return $this->chance(60) ? 'trivial' : 'low';
    }

    // Mix of difficulties
    $roll = $this->nextInt(1, 100);
    if ($roll <= 15) {
      return 'trivial';
    }
    elseif ($roll <= 40) {
      return 'low';
    }
    elseif ($roll <= 75) {
      return 'moderate';
    }
    elseif ($roll <= 92) {
      return 'severe';
    }
    else {
      return 'extreme';
    }
  }

  /**
   * Connect rooms in linear sequence.
   *
   * Note: Uses linear connections. For graph-based layouts, call
   * RoomConnectionAlgorithm::connectRooms() instead.
   *
   * @param array $rooms
   *   Generated rooms
   * @param array $context
   *   Generation context
   *
   * @return array
   *   Room connections
   */
  protected function connectRoomsInLevel(array $rooms, array $context): array {
    $connections = [];

    for ($i = 0; $i < count($rooms) - 1; $i++) {
      $from_room = $rooms[$i];
      $to_room = $rooms[$i + 1];

      $connections[] = [
        'from_room_id' => $from_room['room_id'],
        'to_room_id' => $to_room['room_id'],
        'connection_type' => 'door',
        'is_locked' => $this->chance(15), // 15% locked
        'is_trapped' => $this->chance(10), // 10% trapped
        'is_hidden' => FALSE,
      ];
    }

    return $connections;
  }

  /**
   * Generate dungeon name from theme.
   *
   * @param string $theme
   *   Dungeon theme
   * @param array $context
   *   Generation context
   *
   * @return string
   *   Generated name
   */
  protected function generateDungeonName(string $theme, array $context): string {
    $prefixes = [
      'dungeon' => ['Ancient', 'Forgotten', 'Dark', 'Abandoned'],
      'cave' => ['Deep', 'Murky', 'Echoing', 'Shadowed'],
      'crypt' => ['Cursed', 'Silent', 'Haunted', 'Ancient'],
      'ruins' => ['Crumbling', 'Lost', 'Overgrown', 'Forbidden'],
      'underground' => ['Hidden', 'Sunken', 'Subterranean', 'Buried'],
    ];

    $suffixes = [
      'dungeon' => ['Dungeon', 'Prison', 'Keep', 'Halls'],
      'cave' => ['Caverns', 'Grotto', 'Warren', 'Depths'],
      'crypt' => ['Crypt', 'Tomb', 'Sepulcher', 'Mausoleum'],
      'ruins' => ['Ruins', 'Temple', 'Citadel', 'Fortress'],
      'underground' => ['Labyrinth', 'Passage', 'Network', 'Complex'],
    ];

    $prefix_list = $prefixes[$theme] ?? ['Dark'];
    $suffix_list = $suffixes[$theme] ?? ['Dungeon'];

    $prefix = $this->pick($prefix_list);
    $suffix = $this->pick($suffix_list);

    return sprintf('%s %s', $prefix, $suffix);
  }

  /**
   * Get difficulty distribution for level.
   *
   * @param array $rooms
   *   Generated rooms
   *
   * @return array
   *   Difficulty counts
   */
  protected function getDifficultyDistribution(array $rooms): array {
    $distribution = [
      'trivial' => 0,
      'low' => 0,
      'moderate' => 0,
      'severe' => 0,
      'extreme' => 0,
    ];

    foreach ($rooms as $room) {
      // Extract difficulty from creatures or generation context
      // For now, we'll need to parse from room data or track during generation
      // Placeholder implementation
    }

    return $distribution;
  }

  /**
   * Get deterministic ranged int for current generation scope.
   */
  protected function nextInt(int $minimum, int $maximum): int {
    if ($this->rng instanceof SeededRandomSequence) {
      return $this->rng->nextInt($minimum, $maximum);
    }

    return $this->numberGeneration->rollRange($minimum, $maximum);
  }

  /**
   * Pick one value from a non-empty list using deterministic scope RNG.
   */
  protected function pick(array $items): mixed {
    if ($this->rng instanceof SeededRandomSequence) {
      return $this->rng->pick($items);
    }

    return $items[$this->numberGeneration->rollRange(0, count($items) - 1)];
  }

  /**
   * Check percentage chance using deterministic scope RNG.
   */
  protected function chance(int $percent): bool {
    if ($this->rng instanceof SeededRandomSequence) {
      return $this->rng->chance($percent);
    }

    return $this->numberGeneration->rollRange(1, 100) <= max(0, min(100, $percent));
  }

}
