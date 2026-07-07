<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Support\H3SpatialHelper;
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
   * Layout profile resolver.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonLayoutProfileResolver
   */
  protected DungeonLayoutProfileResolver $layoutProfileResolver;

  /**
   * Room-anchor placement planner.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonAnchorPlacementPlanner
   */
  protected DungeonAnchorPlacementPlanner $anchorPlacementPlanner;

  /**
   * Room connection planner.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonRoomConnectionPlanner
   */
  protected DungeonRoomConnectionPlanner $roomConnectionPlanner;

  /**
   * Active deterministic RNG sequence for current generation pass.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SeededRandomSequence|null
   */
  protected ?SeededRandomSequence $rng = NULL;
  protected const MIN_ROOM_SPACING_HEXES = 5;
  protected const MIN_ANCHOR_DISTANCE_RES14_HEXES = 200;
  protected const H3_ACTIVE_RESOLUTION = 14;
  protected const AXIAL_NEIGHBOR_OFFSETS = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, -1], [-1, 1]];

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
   * @param \Drupal\dungeoncrawler_content\Service\NumberGenerationService $number_generation
   *   Number generation service.
   * @param \Drupal\dungeoncrawler_content\Service\DungeonLayoutProfileResolver $layout_profile_resolver
   *   Dungeon type/layout profile resolver.
   * @param \Drupal\dungeoncrawler_content\Service\DungeonAnchorPlacementPlanner $anchor_placement_planner
   *   Anchor placement strategy planner.
   * @param \Drupal\dungeoncrawler_content\Service\DungeonRoomConnectionPlanner $room_connection_planner
   *   Room connection strategy planner.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    SchemaLoader $schema_loader,
    RoomGeneratorService $room_generator,
    RoomConnectionAlgorithm $room_connector,
    EncounterBalancer $encounter_balancer,
    NumberGenerationService $number_generation,
    DungeonLayoutProfileResolver $layout_profile_resolver,
    DungeonAnchorPlacementPlanner $anchor_placement_planner,
    DungeonRoomConnectionPlanner $room_connection_planner
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->schemaLoader = $schema_loader;
    $this->roomGenerator = $room_generator;
    $this->roomConnector = $room_connector;
    $this->encounterBalancer = $encounter_balancer;
    $this->numberGeneration = $number_generation;
    $this->layoutProfileResolver = $layout_profile_resolver;
    $this->anchorPlacementPlanner = $anchor_placement_planner;
    $this->roomConnectionPlanner = $room_connection_planner;
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
    $layout_profile = $this->layoutProfileResolver->resolveProfile($context + ['theme' => $theme]);
    $context['theme'] = $theme;
    $context['dungeon_type'] = $layout_profile['dungeon_type'];
    $context['layout_algorithm'] = $layout_profile['layout_algorithm'];
    $context['dungeon_id'] = sprintf('dungeon_%d_%d_%d',
      $context['campaign_id'],
      $context['location_x'],
      $context['location_y']
    );

    // Step 4: Determine dungeon depth
    $depth = isset($context['depth_override'])
      ? max(1, (int) $context['depth_override'])
      : $this->calculateDungeonDepth($context['party_level']);

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
    $topology_payload = $this->buildDungeonTopologyPayload($levels, $all_connections);

    $first_level = $levels[0] ?? [];
    $dungeon_id = (string) ($context['dungeon_id'] ?? '');
    if ($dungeon_id === '') {
      throw new \RuntimeException('Dungeon generation contract violation: dungeon_id was not resolved before payload assembly.');
    }

    $dungeon_data = [
      'schema_version' => '1.0.0',
      'dungeon_id' => $dungeon_id,
      'name' => $this->generateDungeonName($theme, $context),
      'theme' => $theme,
      'dungeon_type' => $layout_profile['dungeon_type'],
      'layout_algorithm' => $layout_profile['layout_algorithm'],
      'depth' => $depth,
      'location_x' => $context['location_x'],
      'location_y' => $context['location_y'],
      'level_id' => $first_level['level_id'] ?? '',
      'hex_map' => [
        'map_id' => $dungeon_id,
        'connections' => $all_connections,
        'placement_surface' => $topology_payload['placement_surface'],
        'placement_surfaces_by_level' => $topology_payload['placement_surfaces_by_level'],
      ],
      'rooms' => $all_rooms,
      'entities' => $all_entities,
      'object_definitions' => [],
      'room_road_anchors' => $topology_payload['room_road_anchors'],
      'road_anchors' => $topology_payload['room_road_anchors'],
      'road_graph' => [
        'edges' => $topology_payload['road_edges'],
      ],
      'road_edges' => $topology_payload['road_edges'],
      'levels' => $levels,
      'generation_context' => [
        'party_level' => $context['party_level'],
        'party_size' => $context['party_size'],
        'seed' => $context['seed'],
        'dungeon_type' => $layout_profile['dungeon_type'],
        'layout_algorithm' => $layout_profile['layout_algorithm'],
        'generated_at' => date('c'),
      ],
    ];

    // Step 7: Persist complete dungeon to database.
    $db_dungeon_id = $this->persistDungeon($context, $levels);
    $dungeon_data['persisted'] = TRUE;
    $dungeon_data['dungeon_id'] = $db_dungeon_id;

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
    $layout_profile = $this->layoutProfileResolver->resolveProfile($context);
    $context['dungeon_type'] = $layout_profile['dungeon_type'];
    $context['layout_algorithm'] = $layout_profile['layout_algorithm'];

    // Step 1: Determine room count for this level
    $room_count = isset($context['room_count_override'])
      ? max(1, (int) $context['room_count_override'])
      : $this->calculateRoomCount($context);

    // Step 2: Generate all rooms for this level
    $rooms = [];
    for ($i = 0; $i < $room_count; $i++) {
      $room_context = array_merge($context, [
        'room_index' => $i,
        'dungeon_id' => $context['dungeon_id'] ?? $context['campaign_id'],
        'defer_room_persistence' => TRUE,
        'terrain_type' => $this->selectTerrainType($context['theme']),
        'room_type' => ($i === 0 && !empty($context['landing_room_type']))
          ? (string) $context['landing_room_type']
          : $this->selectRoomType($i, $room_count),
      ]);

      // Vary difficulty across rooms
      $room_context['difficulty'] = $this->selectRoomDifficulty($i, $room_count, $context['depth']);

      $room = $this->roomGenerator->generateRoom($room_context);
      $rooms[] = $room;
    }

    $room_anchors = $this->applyMinimumRoomSpacing($rooms, self::MIN_ROOM_SPACING_HEXES, $context);

    // Step 3: Connect rooms (basic linear for now, Delaunay later)
    $connections = $this->connectRoomsInLevel($rooms, $context);
    $placement_surface = $this->buildPlacementSurface($rooms, $room_anchors, $connections, self::MIN_ROOM_SPACING_HEXES);

    // Step 4: Build level structure
    $level_data = [
      'level_id' => sprintf('level_%d_%d',
        $context['campaign_id'],
        $context['depth']
      ),
      'depth' => $context['depth'],
      'theme' => $context['theme'],
      'dungeon_type' => $layout_profile['dungeon_type'],
      'layout_algorithm' => $layout_profile['layout_algorithm'],
      'name' => sprintf('Level %d - %s', $context['depth'], ucfirst($context['theme'])),
      'room_count' => count($rooms),
      'hex_map' => [
        'room_anchors' => $room_anchors,
        'minimum_room_spacing_hexes' => self::MIN_ROOM_SPACING_HEXES,
        'dungeon_layout' => [
          'dungeon_type' => $layout_profile['dungeon_type'],
          'layout_algorithm' => $layout_profile['layout_algorithm'],
        ],
        'placement_surface' => $placement_surface,
      ],
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
    $this->layoutProfileResolver->validateContext($context);
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
    $dungeon_id = trim((string) ($context['dungeon_id'] ?? ''));
    if ($dungeon_id === '') {
      $dungeon_id = sprintf('dungeon_%d_%d_%d',
        $campaign_id,
        $context['location_x'] ?? 0,
        $context['location_y'] ?? 0
      );
    }

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
    $topology_payload = $this->buildDungeonTopologyPayload($levels, $all_connections);

    $first_level = $levels[0] ?? [];
    $dungeon_data = json_encode([
      'schema_version' => '1.0.0',
      'level_id' => $first_level['level_id'] ?? '',
      'hex_map' => [
        'map_id' => $dungeon_id,
        'connections' => $all_connections,
        'placement_surface' => $topology_payload['placement_surface'],
        'placement_surfaces_by_level' => $topology_payload['placement_surfaces_by_level'],
      ],
      'rooms' => $all_rooms,
      'entities' => $all_entities,
      'object_definitions' => [],
      'room_road_anchors' => $topology_payload['room_road_anchors'],
      'road_anchors' => $topology_payload['room_road_anchors'],
      'road_graph' => [
        'edges' => $topology_payload['road_edges'],
      ],
      'road_edges' => $topology_payload['road_edges'],
      'generation_context' => [
        'party_level' => $context['party_level'],
        'party_size' => $context['party_size'] ?? 4,
        'seed' => $context['seed'] ?? 0,
        'dungeon_type' => (string) ($context['dungeon_type'] ?? DungeonLayoutProfileResolver::DUNGEON_TYPE_GENERIC),
        'layout_algorithm' => (string) ($context['layout_algorithm'] ?? DungeonLayoutProfileResolver::PLACEMENT_ALGORITHM_VERSION),
        'generated_at' => date('c'),
      ],
    ]);

    $theme = $context['theme'] ?? 'dungeon';
    $name = $this->generateDungeonName($theme, $context);

    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();

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
      $this->persistAuthoritativeSparseH3Mappings($dungeon_id, $levels, $now);

      // Persist each room from each level.
      foreach ($levels as $level) {
        foreach (($level['rooms'] ?? []) as $room) {
          $room_id = $room['room_id'] ?? '';
          $layout_data = json_encode([
            'hexes' => $room['hexes'] ?? [],
            'hex_manifest' => $room['hex_manifest'] ?? [],
            'entry_points' => $room['entry_points'] ?? [],
            'exit_points' => $room['exit_points'] ?? [],
            'exits' => $room['exits'] ?? [],
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
              'source_room_id' => !empty($room['_library_source']) ? (string) $room['_library_source'] : NULL,
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

            $this->database->merge('dc_campaign_characters')
              ->keys([
                'campaign_id' => $campaign_id,
                'instance_id' => $instance_id,
              ])
              ->fields([
                'character_id' => 0,
                'source_character_id' => NULL,
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
                'lifecycle_state' => 'campaign_entity',
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
        }
      }

      $this->logger->info('Dungeon @id persisted with @count levels', [
        '@id' => $dungeon_id,
        '@count' => count($levels),
      ]);

      return $dungeon_id;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Failed to persist dungeon: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw new \RuntimeException('Failed to persist dungeon: ' . $e->getMessage(), 0, $e);
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
    $layout_profile = $this->layoutProfileResolver->resolveProfile($context);
    return $this->roomConnectionPlanner->connectRoomsInLevel(
      $rooms,
      $layout_profile,
      fn(int $percentage): bool => $this->chance($percentage),
      fn(int $q1, int $r1, int $q2, int $r2): int => $this->axialDistanceSteps($q1, $r1, $q2, $r2)
    );
  }

  /**
   * Repositions room coordinates so generated rooms keep minimum spacing.
   *
   * @param array $rooms
   *   Room payloads generated for one level (modified in place).
   * @param int $minimum_gap_hexes
   *   Minimum required inter-room gap measured in empty hexes.
   * @param array $context
   *   Generation context used for deterministic metadata.
   *
   * @return array<int, array<string, mixed>>
   *   Room anchor metadata for hex_map export.
   */
  protected function applyMinimumRoomSpacing(array &$rooms, int $minimum_gap_hexes, array $context): array {
    $layout_profile = $this->layoutProfileResolver->resolveProfile($context);
    return $this->anchorPlacementPlanner->applyMinimumRoomSpacing(
      $rooms,
      $minimum_gap_hexes,
      $context,
      $layout_profile,
      fn(array $room): array => $this->calculateRoomBounds($room),
      fn(array $room, int $offset_q, int $offset_r): array => $this->offsetRoomCoordinates($room, $offset_q, $offset_r),
      fn(int $q1, int $r1, int $q2, int $r2): int => $this->axialDistanceSteps($q1, $r1, $q2, $r2)
    );
  }

  /**
   * Build one explicit placement surface with room/street/buffer reservations.
   *
   * @param array $rooms
   *   Generated rooms with normalized placement coordinates.
   * @param array $room_anchors
   *   Anchor metadata emitted by applyMinimumRoomSpacing().
   * @param array $connections
   *   Connection edges generated for this level.
   * @param int $minimum_gap_hexes
   *   Required empty-gap size around room footprints.
   *
   * @return array<string, mixed>
   *   Placement-surface payload including cell roles and street graph.
   */
  protected function buildPlacementSurface(array &$rooms, array $room_anchors, array $connections, int $minimum_gap_hexes): array {
    $room_anchor_by_room = [];
    foreach ($room_anchors as $room_anchor) {
      if (!is_array($room_anchor)) {
        continue;
      }
      $room_id = trim((string) ($room_anchor['room_id'] ?? ''));
      if ($room_id !== '') {
        $room_anchor_by_room[$room_id] = $room_anchor;
      }
    }

    $room_cells_by_room = [];
    $room_cell_owner_by_key = [];
    $room_ingress_by_room = [];
    foreach ($rooms as &$room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \RuntimeException('Placement surface generation requires room_id for each room.');
      }

      $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
      if ($hexes === []) {
        throw new \RuntimeException(sprintf('Placement surface generation requires non-empty hexes for room %s.', $room_id));
      }

      foreach ($hexes as $hex_index => $hex) {
        if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
          throw new \RuntimeException(sprintf('Room %s hexes[%d] must include numeric q/r coordinates for placement surface.', $room_id, $hex_index));
        }
        $q = (int) $hex['q'];
        $r = (int) $hex['r'];
        $hex_key = $q . ':' . $r;
        $existing_room_id = trim((string) ($room_cell_owner_by_key[$hex_key] ?? ''));
        if ($existing_room_id !== '' && $existing_room_id !== $room_id) {
          throw new \RuntimeException(sprintf("Room footprint conflict detected at %s between rooms '%s' and '%s'.", $hex_key, $existing_room_id, $room_id));
        }
        $room_cell_owner_by_key[$hex_key] = $room_id;
        if (!isset($room_cells_by_room[$room_id])) {
          $room_cells_by_room[$room_id] = [];
        }
        $room_cells_by_room[$room_id][$hex_key] = ['q' => $q, 'r' => $r];
      }

      $ingress_point = NULL;
      $ingress_keys = is_array($room['placement']['ingress_hex_ids'] ?? NULL) ? $room['placement']['ingress_hex_ids'] : [];
      if ($ingress_keys !== []) {
        $candidate_key = trim((string) $ingress_keys[0]);
        $parsed = $this->parseCoordinateKey($candidate_key);
        if (is_array($parsed) && isset($room_cells_by_room[$room_id][$candidate_key])) {
          $ingress_point = $parsed;
        }
      }
      if ($ingress_point === NULL) {
        $anchor = is_array($room_anchor_by_room[$room_id] ?? NULL) ? $room_anchor_by_room[$room_id] : NULL;
        if (
          $anchor !== NULL
          && is_numeric($anchor['anchor_q'] ?? NULL)
          && is_numeric($anchor['anchor_r'] ?? NULL)
        ) {
          $candidate_key = (int) $anchor['anchor_q'] . ':' . (int) $anchor['anchor_r'];
          if (isset($room_cells_by_room[$room_id][$candidate_key])) {
            $ingress_point = ['q' => (int) $anchor['anchor_q'], 'r' => (int) $anchor['anchor_r']];
          }
        }
      }
      if ($ingress_point === NULL) {
        throw new \RuntimeException(sprintf('Placement surface requires one in-footprint ingress coordinate for room %s.', $room_id));
      }
      $room_ingress_by_room[$room_id] = [
        'q' => (int) $ingress_point['q'],
        'r' => (int) $ingress_point['r'],
      ];
    }
    unset($room);

    $street_segments = [];
    $street_cells = [];
    foreach ($connections as $index => $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '') {
        throw new \RuntimeException('Placement surface street generation requires from_room_id/to_room_id on each connection.');
      }
      if (!isset($room_ingress_by_room[$from_room_id]) || !isset($room_ingress_by_room[$to_room_id])) {
        throw new \RuntimeException(sprintf("Placement surface street generation missing ingress metadata for connection '%s' -> '%s'.", $from_room_id, $to_room_id));
      }

      $from_ingress = $room_ingress_by_room[$from_room_id];
      $to_ingress = $room_ingress_by_room[$to_room_id];
      $path = $this->buildAxialPath($from_ingress['q'], $from_ingress['r'], $to_ingress['q'], $to_ingress['r']);
      if (count($path) < 2) {
        throw new \RuntimeException(sprintf("Street segment path for '%s' -> '%s' must contain at least two nodes.", $from_room_id, $to_room_id));
      }

      $segment_id = 'street_' . strtolower(substr(sha1($from_room_id . '|' . $to_room_id . '|' . (string) $index), 0, 16));
      foreach ($path as $path_index => $node) {
        if (!is_array($node)) {
          continue;
        }
        $q = isset($node['q']) ? (int) $node['q'] : 0;
        $r = isset($node['r']) ? (int) $node['r'] : 0;
        $node_key = $q . ':' . $r;
        $owner_room_id = trim((string) ($room_cell_owner_by_key[$node_key] ?? ''));
        if (
          $owner_room_id !== ''
          && $owner_room_id !== $from_room_id
          && $owner_room_id !== $to_room_id
        ) {
          throw new \RuntimeException(sprintf("Street segment '%s' intersects unrelated room footprint '%s' at %s.", $segment_id, $owner_room_id, $node_key));
        }
        if (!isset($street_cells[$node_key])) {
          $street_cells[$node_key] = ['q' => $q, 'r' => $r, 'segments' => []];
        }
        $street_cells[$node_key]['segments'][$segment_id] = TRUE;
      }

      $edge_direction = strtolower(trim((string) ($connection['edge_direction'] ?? 'bidirectional')));
      if (!in_array($edge_direction, ['one_way', 'bidirectional'], TRUE)) {
        $edge_direction = 'bidirectional';
      }
      $street_segments[] = [
        'segment_id' => $segment_id,
        'from_room_id' => $from_room_id,
        'to_room_id' => $to_room_id,
        'edge_kind' => trim((string) ($connection['edge_kind'] ?? 'street_path')) !== '' ? trim((string) ($connection['edge_kind'] ?? 'street_path')) : 'street_path',
        'edge_direction' => $edge_direction,
        'street_class' => 'primary',
        'traversal_cost' => max(1, count($path) - 1),
        'blocked' => !empty($connection['blocked']),
        'path' => $path,
      ];
    }

    $intersection_by_key = [];
    $intersection_index = 1;
    foreach ($street_cells as $street_key => $street_cell) {
      if (!is_array($street_cell)) {
        continue;
      }
      $segment_count = count((array) ($street_cell['segments'] ?? []));
      if ($segment_count > 1) {
        $intersection_by_key[$street_key] = [
          'intersection_id' => 'intersection_' . $intersection_index,
          'q' => (int) ($street_cell['q'] ?? 0),
          'r' => (int) ($street_cell['r'] ?? 0),
          'segment_ids' => array_keys((array) ($street_cell['segments'] ?? [])),
        ];
        $intersection_index++;
      }
    }

    $street_or_intersection_by_key = [];
    foreach ($street_cells as $street_key => $_street_cell) {
      $street_or_intersection_by_key[$street_key] = TRUE;
    }

    $buffer_reserved = [];
    $expansion_reserved = [];
    foreach ($room_cells_by_room as $room_id => $room_cells) {
      foreach ($room_cells as $room_cell) {
        if (!is_array($room_cell)) {
          continue;
        }
        $center_q = isset($room_cell['q']) ? (int) $room_cell['q'] : 0;
        $center_r = isset($room_cell['r']) ? (int) $room_cell['r'] : 0;

        for ($delta_q = -$minimum_gap_hexes; $delta_q <= $minimum_gap_hexes; $delta_q++) {
          for ($delta_r = -$minimum_gap_hexes; $delta_r <= $minimum_gap_hexes; $delta_r++) {
            $q = $center_q + $delta_q;
            $r = $center_r + $delta_r;
            $distance = $this->axialDistanceSteps($center_q, $center_r, $q, $r);
            if ($distance === 0 || $distance > $minimum_gap_hexes) {
              continue;
            }
            $hex_key = $q . ':' . $r;
            $owner_room_id = trim((string) ($room_cell_owner_by_key[$hex_key] ?? ''));
            if ($owner_room_id !== '') {
              if ($owner_room_id !== $room_id && $distance <= $minimum_gap_hexes) {
                throw new \RuntimeException(sprintf("Room spacing contract violation between rooms '%s' and '%s' at %s.", $room_id, $owner_room_id, $hex_key));
              }
              continue;
            }
            if (isset($street_or_intersection_by_key[$hex_key])) {
              continue;
            }

            if ($distance < $minimum_gap_hexes) {
              if (!isset($buffer_reserved[$hex_key])) {
                $buffer_reserved[$hex_key] = ['q' => $q, 'r' => $r, 'room_ids' => [$room_id => TRUE]];
              }
              else {
                $buffer_reserved[$hex_key]['room_ids'][$room_id] = TRUE;
              }
            }
            elseif (!isset($buffer_reserved[$hex_key])) {
              if (!isset($expansion_reserved[$hex_key])) {
                $expansion_reserved[$hex_key] = ['q' => $q, 'r' => $r, 'room_ids' => [$room_id => TRUE]];
              }
              else {
                $expansion_reserved[$hex_key]['room_ids'][$room_id] = TRUE;
              }
            }
          }
        }
      }
    }

    $cell_role_map = [];
    foreach ($room_cells_by_room as $room_id => $room_cells) {
      foreach ($room_cells as $hex_key => $room_cell) {
        if (!is_array($room_cell)) {
          continue;
        }
        $cell_role_map[$hex_key] = [
          'q' => (int) ($room_cell['q'] ?? 0),
          'r' => (int) ($room_cell['r'] ?? 0),
          'cell_role' => 'room_hex',
          'room_id' => $room_id,
        ];
      }
    }

    foreach ($street_cells as $street_key => $street_cell) {
      if (!is_array($street_cell) || isset($cell_role_map[$street_key])) {
        continue;
      }
      $is_intersection = isset($intersection_by_key[$street_key]);
      $cell_role_map[$street_key] = [
        'q' => (int) ($street_cell['q'] ?? 0),
        'r' => (int) ($street_cell['r'] ?? 0),
        'cell_role' => $is_intersection ? 'intersection' : 'street',
        'room_id' => 'NA',
      ];
    }

    foreach ($buffer_reserved as $buffer_key => $buffer_cell) {
      if (!is_array($buffer_cell) || isset($cell_role_map[$buffer_key])) {
        continue;
      }
      $room_ids = array_keys((array) ($buffer_cell['room_ids'] ?? []));
      sort($room_ids, SORT_STRING);
      $cell_role_map[$buffer_key] = [
        'q' => (int) ($buffer_cell['q'] ?? 0),
        'r' => (int) ($buffer_cell['r'] ?? 0),
        'cell_role' => 'buffer_reserved',
        'room_id' => count($room_ids) === 1 ? (string) $room_ids[0] : 'NA',
        'shared_room_ids' => $room_ids,
      ];
    }

    foreach ($expansion_reserved as $expansion_key => $expansion_cell) {
      if (!is_array($expansion_cell) || isset($cell_role_map[$expansion_key])) {
        continue;
      }
      $room_ids = array_keys((array) ($expansion_cell['room_ids'] ?? []));
      sort($room_ids, SORT_STRING);
      $cell_role_map[$expansion_key] = [
        'q' => (int) ($expansion_cell['q'] ?? 0),
        'r' => (int) ($expansion_cell['r'] ?? 0),
        'cell_role' => 'expansion_reserved',
        'room_id' => count($room_ids) === 1 ? (string) $room_ids[0] : 'NA',
        'shared_room_ids' => $room_ids,
      ];
    }
    ksort($cell_role_map, SORT_STRING);

    $room_frontage_by_room = [];
    foreach ($room_cells_by_room as $room_id => $room_cells) {
      $frontage = [];
      foreach ($room_cells as $room_cell) {
        if (!is_array($room_cell)) {
          continue;
        }
        $q = (int) ($room_cell['q'] ?? 0);
        $r = (int) ($room_cell['r'] ?? 0);
        $room_hex_key = $q . ':' . $r;
        foreach (self::AXIAL_NEIGHBOR_OFFSETS as $offset) {
          $neighbor_key = ($q + $offset[0]) . ':' . ($r + $offset[1]);
          if (isset($street_or_intersection_by_key[$neighbor_key])) {
            $frontage[$room_hex_key] = TRUE;
            break;
          }
        }
      }
      $frontage_keys = array_keys($frontage);
      if ($frontage_keys === []) {
        throw new \RuntimeException(sprintf("Room '%s' has no street frontage in generated placement surface.", $room_id));
      }
      $room_frontage_by_room[$room_id] = $frontage_keys;
    }

    foreach ($rooms as &$room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        continue;
      }
      $frontage_keys = (array) ($room_frontage_by_room[$room_id] ?? []);
      if ($frontage_keys !== []) {
        if (!is_array($room['placement'] ?? NULL)) {
          $room['placement'] = [];
        }
        $room['placement']['street_frontage_hex_ids'] = $frontage_keys;
      }
    }
    unset($room);

    return [
      'cell_roles' => array_values($cell_role_map),
      'street_segments' => $street_segments,
      'intersections' => array_values($intersection_by_key),
      'summary' => [
        'room_hex_cells' => count($room_cell_owner_by_key),
        'street_cells' => count($street_cells),
        'intersection_cells' => count($intersection_by_key),
        'buffer_reserved_cells' => count($buffer_reserved),
        'expansion_reserved_cells' => count($expansion_reserved),
      ],
    ];
  }

  /**
   * Build persisted topology payloads for navigation + analysis read paths.
   *
   * @param array $levels
   *   Generated level payloads.
   * @param array $connections
   *   Flattened connection list across levels.
   *
   * @return array<string, mixed>
   *   Topology payload parts for dungeon_data persistence.
   */
  protected function buildDungeonTopologyPayload(array $levels, array $connections): array {
    $placement_surface = [];
    $placement_surfaces_by_level = [];
    $room_road_anchors_by_room = [];

    foreach ($levels as $level_index => $level) {
      if (!is_array($level)) {
        continue;
      }
      $hex_map = is_array($level['hex_map'] ?? NULL) ? $level['hex_map'] : [];
      $level_id = trim((string) ($level['level_id'] ?? ''));
      if ($level_id === '') {
        $level_id = 'level_' . ((int) $level_index + 1);
      }

      $level_surface = is_array($hex_map['placement_surface'] ?? NULL)
        ? $hex_map['placement_surface']
        : [];
      if ($level_surface !== []) {
        $placement_surfaces_by_level[$level_id] = $level_surface;
        if ($placement_surface === []) {
          $placement_surface = $level_surface;
        }
      }

      $level_room_anchors = is_array($hex_map['room_anchors'] ?? NULL)
        ? $hex_map['room_anchors']
        : [];
      foreach ($level_room_anchors as $room_anchor) {
        if (!is_array($room_anchor)) {
          continue;
        }
        $room_id = trim((string) ($room_anchor['room_id'] ?? ''));
        if ($room_id === '') {
          continue;
        }
        if (isset($room_road_anchors_by_room[$room_id])) {
          continue;
        }

        $anchor = [
          'room_id' => $room_id,
          'road_node_id' => 'room:' . $room_id,
          'access_distance' => 0,
          'level_id' => $level_id,
        ];
        if (is_numeric($room_anchor['anchor_q'] ?? NULL) && is_numeric($room_anchor['anchor_r'] ?? NULL)) {
          $anchor['anchor_q'] = (int) $room_anchor['anchor_q'];
          $anchor['anchor_r'] = (int) $room_anchor['anchor_r'];
        }
        $algorithm_version = trim((string) ($room_anchor['algorithm_version'] ?? ''));
        if ($algorithm_version !== '') {
          $anchor['algorithm_version'] = $algorithm_version;
        }
        $room_road_anchors_by_room[$room_id] = $anchor;
      }
    }

    ksort($placement_surfaces_by_level, SORT_STRING);
    ksort($room_road_anchors_by_room, SORT_STRING);
    $room_road_anchors = array_values($room_road_anchors_by_room);

    $road_edges = [];
    $edge_seen = [];
    foreach ($connections as $connection_index => $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }
      $edge_direction = strtolower(trim((string) ($connection['edge_direction'] ?? 'bidirectional')));
      if (!in_array($edge_direction, ['one_way', 'bidirectional'], TRUE)) {
        $edge_direction = 'bidirectional';
      }
      $bidirectional = $edge_direction !== 'one_way';
      $distance = is_numeric($connection['traversal_cost'] ?? NULL)
        ? max(1, (int) $connection['traversal_cost'])
        : (is_numeric($connection['distance'] ?? NULL) ? max(1, (int) $connection['distance']) : 1);
      $edge_kind = trim((string) ($connection['edge_kind'] ?? 'street_path'));
      if ($edge_kind === '') {
        $edge_kind = 'street_path';
      }

      $edge_key = implode('|', [
        $from_room_id,
        $to_room_id,
        $bidirectional ? '1' : '0',
        (string) $distance,
        $edge_kind,
      ]);
      if (isset($edge_seen[$edge_key])) {
        continue;
      }
      $edge_seen[$edge_key] = TRUE;
      $road_edges[] = [
        'edge_id' => 'edge_' . substr(sha1($edge_key . '|' . (string) $connection_index), 0, 16),
        'from_node_id' => 'room:' . $from_room_id,
        'to_node_id' => 'room:' . $to_room_id,
        'distance' => $distance,
        'bidirectional' => $bidirectional,
        'edge_kind' => $edge_kind,
      ];
    }

    return [
      'placement_surface' => $placement_surface,
      'placement_surfaces_by_level' => $placement_surfaces_by_level,
      'room_road_anchors' => $room_road_anchors,
      'road_edges' => $road_edges,
    ];
  }

  /**
   * Computes room hex bounds from room payload.
   *
   * @return array{min_q:int,max_q:int,min_r:int,max_r:int}
   *   Bounding box over room hex coordinates.
   */
  protected function calculateRoomBounds(array $room): array {
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf('Room %s must define non-empty hexes for placement.', (string) ($room['room_id'] ?? 'unknown')));
    }

    $min_q = NULL;
    $max_q = NULL;
    $min_r = NULL;
    $max_r = NULL;
    foreach ($hexes as $hex_index => $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf(
          'Room %s hexes[%d] must include numeric q/r coordinates.',
          (string) ($room['room_id'] ?? 'unknown'),
          $hex_index
        ));
      }
      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $min_q = $min_q === NULL ? $q : min($min_q, $q);
      $max_q = $max_q === NULL ? $q : max($max_q, $q);
      $min_r = $min_r === NULL ? $r : min($min_r, $r);
      $max_r = $max_r === NULL ? $r : max($max_r, $r);
    }

    return [
      'min_q' => (int) $min_q,
      'max_q' => (int) $max_q,
      'min_r' => (int) $min_r,
      'max_r' => (int) $max_r,
    ];
  }

  /**
   * Applies one axial offset to all room coordinate payloads.
   */
  protected function offsetRoomCoordinates(array $room, int $offset_q, int $offset_r): array {
    $room['hexes'] = $this->offsetHexCoordinates(
      is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [],
      $offset_q,
      $offset_r
    );
    $room['entry_points'] = $this->offsetCoordinatePoints(
      is_array($room['entry_points'] ?? NULL) ? $room['entry_points'] : [],
      $offset_q,
      $offset_r
    );
    $room['exit_points'] = $this->offsetCoordinatePoints(
      is_array($room['exit_points'] ?? NULL) ? $room['exit_points'] : [],
      $offset_q,
      $offset_r
    );
    $room['exits'] = $this->offsetCoordinatePoints(
      is_array($room['exits'] ?? NULL) ? $room['exits'] : [],
      $offset_q,
      $offset_r
    );

    foreach (['creatures', 'items', 'traps', 'hazards', 'obstacles', 'interactables'] as $entity_key) {
      if (!is_array($room[$entity_key] ?? NULL)) {
        continue;
      }
      $room[$entity_key] = $this->offsetEntityPlacements($room[$entity_key], $offset_q, $offset_r);
    }

    $hex_manifest = is_array($room['hex_manifest'] ?? NULL) ? $room['hex_manifest'] : [];
    $manifest_by_hex = is_array($hex_manifest['by_hex'] ?? NULL) ? $hex_manifest['by_hex'] : [];
    if ($manifest_by_hex !== []) {
      $shifted_manifest = [];
      foreach ($manifest_by_hex as $manifest_hex) {
        if (!is_array($manifest_hex) || !is_numeric($manifest_hex['q'] ?? NULL) || !is_numeric($manifest_hex['r'] ?? NULL)) {
          continue;
        }
        $q = (int) $manifest_hex['q'] + $offset_q;
        $r = (int) $manifest_hex['r'] + $offset_r;
        $manifest_hex['q'] = $q;
        $manifest_hex['r'] = $r;
        $shifted_manifest[$q . ',' . $r] = $manifest_hex;
      }
      $hex_manifest['by_hex'] = $shifted_manifest;
      $room['hex_manifest'] = $hex_manifest;
    }

    return $room;
  }

  /**
   * Offsets one list of q/r hex coordinates.
   */
  protected function offsetHexCoordinates(array $hexes, int $offset_q, int $offset_r): array {
    $shifted = [];
    foreach ($hexes as $hex) {
      if (!is_array($hex)) {
        $shifted[] = $hex;
        continue;
      }
      if (!is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException('Hex payload missing numeric q/r while applying room spacing.');
      }
      $hex['q'] = (int) $hex['q'] + $offset_q;
      $hex['r'] = (int) $hex['r'] + $offset_r;
      $shifted[] = $hex;
    }
    return $shifted;
  }

  /**
   * Offsets q/r and nested hex.q/hex.r coordinates in one point list.
   */
  protected function offsetCoordinatePoints(array $points, int $offset_q, int $offset_r): array {
    $shifted = [];
    foreach ($points as $point) {
      if (!is_array($point)) {
        $shifted[] = $point;
        continue;
      }
      if (is_numeric($point['q'] ?? NULL) && is_numeric($point['r'] ?? NULL)) {
        $point['q'] = (int) $point['q'] + $offset_q;
        $point['r'] = (int) $point['r'] + $offset_r;
      }
      if (is_array($point['hex'] ?? NULL) && is_numeric($point['hex']['q'] ?? NULL) && is_numeric($point['hex']['r'] ?? NULL)) {
        $point['hex']['q'] = (int) $point['hex']['q'] + $offset_q;
        $point['hex']['r'] = (int) $point['hex']['r'] + $offset_r;
      }
      $shifted[] = $point;
    }
    return $shifted;
  }

  /**
   * Offsets nested entity placement hex coordinates for one entity list.
   */
  protected function offsetEntityPlacements(array $entities, int $offset_q, int $offset_r): array {
    $shifted = [];
    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        $shifted[] = $entity;
        continue;
      }
      if (
        is_array($entity['placement'] ?? NULL)
        && is_array($entity['placement']['hex'] ?? NULL)
        && is_numeric($entity['placement']['hex']['q'] ?? NULL)
        && is_numeric($entity['placement']['hex']['r'] ?? NULL)
      ) {
        $entity['placement']['hex']['q'] = (int) $entity['placement']['hex']['q'] + $offset_q;
        $entity['placement']['hex']['r'] = (int) $entity['placement']['hex']['r'] + $offset_r;
      }
      $shifted[] = $entity;
    }
    return $shifted;
  }

  /**
   * Parse one axial coordinate key in "q:r" format.
   *
   * @return array{q:int,r:int}|null
   *   Parsed coordinates or NULL for invalid format.
   */
  protected function parseCoordinateKey(string $coordinate_key): ?array {
    if (!preg_match('/^(-?\d+):(-?\d+)$/', trim($coordinate_key), $matches)) {
      return NULL;
    }
    return [
      'q' => (int) $matches[1],
      'r' => (int) $matches[2],
    ];
  }

  /**
   * Build one greedy shortest path between two axial coordinates.
   *
   * @return array<int, array{q:int,r:int}>
   *   Path nodes from start to target (inclusive).
   */
  protected function buildAxialPath(int $from_q, int $from_r, int $to_q, int $to_r): array {
    $path = [['q' => $from_q, 'r' => $from_r]];
    $current_q = $from_q;
    $current_r = $from_r;
    $guard = 0;
    while (($current_q !== $to_q || $current_r !== $to_r) && $guard < 8192) {
      $guard++;
      $current_distance = $this->axialDistanceSteps($current_q, $current_r, $to_q, $to_r);
      $best_q = $current_q;
      $best_r = $current_r;
      $best_distance = $current_distance;
      foreach (self::AXIAL_NEIGHBOR_OFFSETS as $offset) {
        $candidate_q = $current_q + $offset[0];
        $candidate_r = $current_r + $offset[1];
        $candidate_distance = $this->axialDistanceSteps($candidate_q, $candidate_r, $to_q, $to_r);
        if ($candidate_distance < $best_distance) {
          $best_distance = $candidate_distance;
          $best_q = $candidate_q;
          $best_r = $candidate_r;
        }
      }
      if ($best_distance >= $current_distance) {
        throw new \RuntimeException(sprintf(
          'Failed to build axial path from %d:%d to %d:%d (distance stalled at %d).',
          $from_q,
          $from_r,
          $to_q,
          $to_r,
          $current_distance
        ));
      }
      $current_q = $best_q;
      $current_r = $best_r;
      $path[] = ['q' => $current_q, 'r' => $current_r];
    }

    if ($current_q !== $to_q || $current_r !== $to_r) {
      throw new \RuntimeException(sprintf('Failed to terminate axial path from %d:%d to %d:%d within guard.', $from_q, $from_r, $to_q, $to_r));
    }
    return $path;
  }

  /**
   * Return axial hex distance in grid steps.
   */
  protected function axialDistanceSteps(int $q1, int $r1, int $q2, int $r2): int {
    $dq = $q1 - $q2;
    $dr = $r1 - $r2;
    return (int) ((abs($dq) + abs($dr) + abs($dq + $dr)) / 2);
  }

  /**
   * Persist authoritative sparse H3 anchor/cell mappings for one dungeon.
   *
   * H3 tables are the system-of-record for geospatial room ownership metadata.
   *
   * @param string $dungeon_id
   *   Persisted dungeon id.
   * @param array $levels
   *   Generated levels.
   * @param int $timestamp
   *   Unix timestamp for created/updated fields.
   */
  protected function persistAuthoritativeSparseH3Mappings(string $dungeon_id, array $levels, int $timestamp): void {
    $schema = $this->database->schema();
    foreach (['dungeoncrawler_content_h3_room_anchors', 'dungeoncrawler_content_h3_room_cells'] as $table) {
      if (!$schema->tableExists($table)) {
        throw new \RuntimeException(sprintf('H3 system-of-record contract violation: required table %s is missing.', $table));
      }
    }
    if ($levels === []) {
      throw new \RuntimeException(sprintf('H3 system-of-record contract violation: cannot persist sparse mappings for dungeon %s without generated levels.', $dungeon_id));
    }

    $this->database->delete('dungeoncrawler_content_h3_room_cells')
      ->condition('dungeon_id', $dungeon_id)
      ->execute();
    $this->database->delete('dungeoncrawler_content_h3_room_anchors')
      ->condition('dungeon_id', $dungeon_id)
      ->execute();

    $room_anchor_by_room = [];
    $room_entry_by_room = [];
    foreach ($levels as $level_index => $level) {
      if (!is_array($level)) {
        continue;
      }
      $level_id = trim((string) ($level['level_id'] ?? ''));
      if ($level_id === '') {
        $level_id = 'level_' . ((int) $level_index + 1);
      }
      $rooms = is_array($level['rooms'] ?? NULL) ? $level['rooms'] : [];
      foreach ($rooms as $room) {
        if (!is_array($room)) {
          continue;
        }
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id === '') {
          throw new \RuntimeException(sprintf('H3 system-of-record contract violation: room entry in %s is missing room_id.', $level_id));
        }
        $entry = $this->resolveRoomEntryCoordinate($room, $room_id, $level_id);
        $room_entry_by_room[$room_id] = $entry;
        if (!isset($room_anchor_by_room[$room_id])) {
          $room_anchor_by_room[$room_id] = [
            'reference_q' => $entry['q'],
            'reference_r' => $entry['r'],
            'level_id' => $level_id,
            'anchor_type' => 'derived',
            'anchor_priority' => 1,
            'placement_wave_index' => 0,
            'placement_seed' => 0,
            'algorithm_version' => DungeonLayoutProfileResolver::PLACEMENT_ALGORITHM_VERSION,
            'dungeon_type' => DungeonLayoutProfileResolver::DUNGEON_TYPE_GENERIC,
            'buffer_ring_size' => self::MIN_ROOM_SPACING_HEXES,
          ];
        }
      }

      $room_anchors = is_array($level['hex_map']['room_anchors'] ?? NULL) ? $level['hex_map']['room_anchors'] : [];
      foreach ($room_anchors as $room_anchor) {
        if (!is_array($room_anchor)) {
          continue;
        }
        $room_id = trim((string) ($room_anchor['room_id'] ?? ''));
        if ($room_id === '') {
          throw new \RuntimeException(sprintf('H3 system-of-record contract violation: room anchor in %s is missing room_id.', $level_id));
        }
        if (!is_numeric($room_anchor['anchor_q'] ?? NULL) || !is_numeric($room_anchor['anchor_r'] ?? NULL)) {
          throw new \RuntimeException(sprintf('H3 system-of-record contract violation: room anchor %s in %s must include numeric anchor_q/anchor_r.', $room_id, $level_id));
        }
        $room_anchor_by_room[$room_id] = [
          'reference_q' => (int) $room_anchor['anchor_q'],
          'reference_r' => (int) $room_anchor['anchor_r'],
          'level_id' => $level_id,
          'anchor_type' => trim((string) ($room_anchor['anchor_type'] ?? '')) ?: 'derived',
          'anchor_priority' => is_numeric($room_anchor['anchor_priority'] ?? NULL) ? (int) $room_anchor['anchor_priority'] : 1,
          'placement_wave_index' => is_numeric($room_anchor['placement_wave_index'] ?? NULL) ? (int) $room_anchor['placement_wave_index'] : 0,
          'placement_seed' => is_numeric($room_anchor['placement_seed'] ?? NULL) ? (int) $room_anchor['placement_seed'] : 0,
          'algorithm_version' => trim((string) ($room_anchor['algorithm_version'] ?? '')) ?: DungeonLayoutProfileResolver::PLACEMENT_ALGORITHM_VERSION,
          'dungeon_type' => trim((string) ($room_anchor['dungeon_type'] ?? '')) ?: DungeonLayoutProfileResolver::DUNGEON_TYPE_GENERIC,
          'buffer_ring_size' => is_numeric($room_anchor['buffer_ring_size'] ?? NULL) ? max(1, (int) $room_anchor['buffer_ring_size']) : self::MIN_ROOM_SPACING_HEXES,
        ];
      }
    }

    if ($room_anchor_by_room === []) {
      throw new \RuntimeException(sprintf('H3 system-of-record contract violation: no room anchors resolved for dungeon %s.', $dungeon_id));
    }

    $anchor_h3_by_room = [];
    foreach ($room_anchor_by_room as $room_id => $anchor) {
      $reference_q = (int) ($anchor['reference_q'] ?? 0);
      $reference_r = (int) ($anchor['reference_r'] ?? 0);
      $latlng = $this->projectAxialHexToLatLng($dungeon_id, $reference_q, $reference_r);
      $h3_index = $this->buildSparseRes14AnchorIndex(
        $dungeon_id,
        (string) $room_id,
        $reference_q,
        $reference_r,
        (float) $latlng['latitude'],
        (float) $latlng['longitude']
      );
      foreach ($anchor_h3_by_room as $existing_room_id => $existing_h3_index) {
        $anchor_distance = H3SpatialHelper::h3GridDistance((string) $existing_h3_index, $h3_index);
        if ($anchor_distance < self::MIN_ANCHOR_DISTANCE_RES14_HEXES) {
          throw new \RuntimeException(sprintf(
            'H3 system-of-record contract violation: anchor spacing between rooms %s and %s in dungeon %s is %d res14 hexes (minimum required %d).',
            (string) $existing_room_id,
            (string) $room_id,
            $dungeon_id,
            $anchor_distance,
            self::MIN_ANCHOR_DISTANCE_RES14_HEXES
          ));
        }
      }
      $anchor_h3_by_room[(string) $room_id] = $h3_index;
      $entry = $room_entry_by_room[$room_id] ?? ['q' => $reference_q, 'r' => $reference_r];
      $layout_algorithm = trim((string) ($anchor['algorithm_version'] ?? '')) ?: DungeonLayoutProfileResolver::PLACEMENT_ALGORITHM_VERSION;
      $dungeon_type = trim((string) ($anchor['dungeon_type'] ?? '')) ?: DungeonLayoutProfileResolver::DUNGEON_TYPE_GENERIC;
      $metadata = [
        'status' => 'h3_index_assigned',
        'h3_index_source' => 'libh3',
        'normalization' => 'global_non_overlapping_axial',
        'normalization_version' => 'runtime-persist-v1',
        'placement_model' => $layout_algorithm,
        'layout_algorithm' => $layout_algorithm,
        'dungeon_type' => $dungeon_type,
        'placement_min_gap_hexes' => self::MIN_ROOM_SPACING_HEXES,
        'placement_min_anchor_distance_hexes' => self::MIN_ANCHOR_DISTANCE_RES14_HEXES,
        'global_offset_q' => 0,
        'global_offset_r' => 0,
        'room_entrance_global_q' => (int) ($entry['q'] ?? $reference_q),
        'room_entrance_global_r' => (int) ($entry['r'] ?? $reference_r),
        'anchor_type' => (string) ($anchor['anchor_type'] ?? 'derived'),
        'anchor_priority' => (int) ($anchor['anchor_priority'] ?? 1),
        'placement_wave_index' => (int) ($anchor['placement_wave_index'] ?? 0),
        'placement_seed' => (int) ($anchor['placement_seed'] ?? 0),
        'algorithm_version' => (string) ($anchor['algorithm_version'] ?? DungeonLayoutProfileResolver::PLACEMENT_ALGORITHM_VERSION),
        'buffer_ring_size' => (int) ($anchor['buffer_ring_size'] ?? self::MIN_ROOM_SPACING_HEXES),
        'level_id' => (string) ($anchor['level_id'] ?? ''),
      ];

      $this->database->insert('dungeoncrawler_content_h3_room_anchors')
        ->fields([
          'dungeon_id' => $dungeon_id,
          'room_id' => (string) $room_id,
          'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
          'h3_index' => $h3_index,
          'center_latitude' => $latlng['latitude'],
          'center_longitude' => $latlng['longitude'],
          'reference_q' => $reference_q,
          'reference_r' => $reference_r,
          'hex_size_meters' => H3SpatialHelper::H3_HEX_SIZE_METERS,
          'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'created' => $timestamp,
          'updated' => $timestamp,
        ])
        ->execute();
    }

    $room_hex_owner_by_key = [];
    $h3_owner_by_index = [];
    foreach ($levels as $level_index => $level) {
      if (!is_array($level)) {
        continue;
      }
      $level_id = trim((string) ($level['level_id'] ?? ''));
      if ($level_id === '') {
        $level_id = 'level_' . ((int) $level_index + 1);
      }
      $rooms = is_array($level['rooms'] ?? NULL) ? $level['rooms'] : [];
      foreach ($rooms as $room) {
        if (!is_array($room)) {
          continue;
        }
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id === '') {
          throw new \RuntimeException(sprintf('H3 system-of-record contract violation: room entry in %s is missing room_id during cell persistence.', $level_id));
        }
        $entry = $room_entry_by_room[$room_id] ?? $this->resolveRoomEntryCoordinate($room, $room_id, $level_id);
        $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
        if ($hexes === []) {
          throw new \RuntimeException(sprintf('H3 system-of-record contract violation: room %s in %s has no hexes for sparse cell persistence.', $room_id, $level_id));
        }
        $room_anchor_metadata = is_array($room_anchor_by_room[$room_id] ?? NULL) ? $room_anchor_by_room[$room_id] : [];
        $layout_algorithm = trim((string) ($room_anchor_metadata['algorithm_version'] ?? '')) ?: DungeonLayoutProfileResolver::PLACEMENT_ALGORITHM_VERSION;
        $dungeon_type = trim((string) ($room_anchor_metadata['dungeon_type'] ?? '')) ?: DungeonLayoutProfileResolver::DUNGEON_TYPE_GENERIC;

        foreach ($hexes as $hex_index => $hex) {
          if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
            throw new \RuntimeException(sprintf('H3 system-of-record contract violation: room %s hexes[%d] in %s must include numeric q/r.', $room_id, $hex_index, $level_id));
          }
          $q = (int) $hex['q'];
          $r = (int) $hex['r'];
          $hex_key = $q . ':' . $r;
          $existing_owner = trim((string) ($room_hex_owner_by_key[$hex_key] ?? ''));
          if ($existing_owner !== '' && $existing_owner !== $room_id) {
            throw new \RuntimeException(sprintf('H3 system-of-record contract violation: overlapping room hex %s between %s and %s in dungeon %s.', $hex_key, $existing_owner, $room_id, $dungeon_id));
          }
          $room_hex_owner_by_key[$hex_key] = $room_id;

          $latlng = $this->projectAxialHexToLatLng($dungeon_id, $q, $r);
          $h3_index = $this->buildSparseRes14CellIndex(
            $dungeon_id,
            $room_id,
            $q,
            $r,
            (float) $latlng['latitude'],
            (float) $latlng['longitude']
          );
          $h3_owner = trim((string) ($h3_owner_by_index[$h3_index] ?? ''));
          if ($h3_owner !== '' && $h3_owner !== $room_id) {
            throw new \RuntimeException(sprintf('H3 system-of-record contract violation: Res14 h3_index collision %s between rooms %s and %s in dungeon %s.', $h3_index, $h3_owner, $room_id, $dungeon_id));
          }
          if ($h3_owner === $room_id) {
            continue;
          }
          $h3_owner_by_index[$h3_index] = $room_id;

          $cell_metadata = [
            'status' => 'h3_index_assigned',
            'h3_index_source' => 'libh3',
            'normalization' => 'global_non_overlapping_axial',
            'normalization_version' => 'runtime-persist-v1',
            'placement_model' => $layout_algorithm,
            'layout_algorithm' => $layout_algorithm,
            'dungeon_type' => $dungeon_type,
            'placement_min_gap_hexes' => self::MIN_ROOM_SPACING_HEXES,
            'placement_min_anchor_distance_hexes' => self::MIN_ANCHOR_DISTANCE_RES14_HEXES,
            'local_source_q' => $q,
            'local_source_r' => $r,
            'global_source_q' => $q,
            'global_source_r' => $r,
            'global_offset_q' => 0,
            'global_offset_r' => 0,
            'room_entrance_global_q' => (int) ($entry['q'] ?? $q),
            'room_entrance_global_r' => (int) ($entry['r'] ?? $r),
            'level_id' => $level_id,
          ];

          $this->database->insert('dungeoncrawler_content_h3_room_cells')
            ->fields([
              'dungeon_id' => $dungeon_id,
              'room_id' => $room_id,
              'cell_role' => 'room_hex',
              'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
              'h3_index' => $h3_index,
              'source_q' => $q,
              'source_r' => $r,
              'center_latitude' => $latlng['latitude'],
              'center_longitude' => $latlng['longitude'],
              'metadata' => json_encode($cell_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
              'created' => $timestamp,
              'updated' => $timestamp,
            ])
            ->execute();
        }
      }
    }
  }

  /**
   * Resolve one canonical room entry coordinate for sparse H3 metadata.
   *
   * @return array{q:int,r:int}
   *   Entry coordinate.
   */
  protected function resolveRoomEntryCoordinate(array $room, string $room_id, string $level_id): array {
    $entry_points = is_array($room['entry_points'] ?? NULL) ? $room['entry_points'] : [];
    if (is_array($entry_points[0] ?? NULL) && is_numeric($entry_points[0]['q'] ?? NULL) && is_numeric($entry_points[0]['r'] ?? NULL)) {
      return [
        'q' => (int) $entry_points[0]['q'],
        'r' => (int) $entry_points[0]['r'],
      ];
    }

    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    if (is_array($hexes[0] ?? NULL) && is_numeric($hexes[0]['q'] ?? NULL) && is_numeric($hexes[0]['r'] ?? NULL)) {
      return [
        'q' => (int) $hexes[0]['q'],
        'r' => (int) $hexes[0]['r'],
      ];
    }

    throw new \RuntimeException(sprintf('H3 system-of-record contract violation: room %s in %s must define at least one numeric entry/hex coordinate.', $room_id, $level_id));
  }

  /**
   * Deterministically project one axial coordinate into WGS84 lat/lng.
   *
   * @return array{latitude: float, longitude: float}
   *   Projected coordinates.
   */
  protected function projectAxialHexToLatLng(string $dungeon_id, int $q, int $r): array {
    return H3SpatialHelper::projectAxialHexToLatLng($dungeon_id, $q, $r);
  }

  /**
   * Build Res14 sparse cell index from room/cell coordinates.
   */
  protected function buildSparseRes14CellIndex(string $dungeon_id, string $room_id, int $source_q, int $source_r, float $latitude, float $longitude): string {
    return H3SpatialHelper::latLngToH3Index($latitude, $longitude, self::H3_ACTIVE_RESOLUTION);
  }

  /**
   * Build Res14 sparse anchor index from room anchor data.
   */
  protected function buildSparseRes14AnchorIndex(string $dungeon_id, string $room_id, int $reference_q, int $reference_r, float $latitude, float $longitude): string {
    return H3SpatialHelper::latLngToH3Index($latitude, $longitude, self::H3_ACTIVE_RESOLUTION);
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
