<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates individual dungeon rooms with terrain, entities, and encounters.
 *
 * Responsible for:
 * - Generating room hexes with terrain and elevation
 * - Creating AI-driven room descriptions
 * - Placing entities (creatures, items, traps, hazards)
 * - Generating balanced encounters
 * - Validating against room.schema.json
 * - Persisting rooms to database
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class RoomGeneratorService {

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
   * The entity placer service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EntityPlacerService
   */
  protected EntityPlacerService $entityPlacer;

  /**
   * The encounter generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EncounterGeneratorService
   */
  protected EncounterGeneratorService $encounterGenerator;

  /**
   * The hex utility service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\HexUtilityService
   */
  protected HexUtilityService $hexUtility;

  /**
   * The terrain generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\TerrainGeneratorService
   */
  protected TerrainGeneratorService $terrainGenerator;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * Room library service for caching/reusing generated rooms.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomLibraryService
   */
  protected RoomLibraryService $roomLibrary;

  /**
   * Optional AI API service for narrative generation.
   *
   * @var \Drupal\ai_conversation\Service\AIApiService|null
   */
  protected $aiService = NULL;

  /**
   * Constructs a RoomGeneratorService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   * @param \Drupal\dungeoncrawler_content\Service\EntityPlacerService $entity_placer
   *   The entity placer service.
   * @param \Drupal\dungeoncrawler_content\Service\EncounterGeneratorService $encounter_generator
   *   The encounter generator service.
   * @param \Drupal\dungeoncrawler_content\Service\HexUtilityService $hex_utility
   *   The hex utility service.
   * @param \Drupal\dungeoncrawler_content\Service\TerrainGeneratorService $terrain_generator
   *   The terrain generator service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    SchemaLoader $schema_loader,
    EntityPlacerService $entity_placer,
    EncounterGeneratorService $encounter_generator,
    HexUtilityService $hex_utility,
    TerrainGeneratorService $terrain_generator,
    NumberGenerationService $number_generation,
    RoomLibraryService $room_library
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->schemaLoader = $schema_loader;
    $this->entityPlacer = $entity_placer;
    $this->encounterGenerator = $encounter_generator;
    $this->hexUtility = $hex_utility;
    $this->terrainGenerator = $terrain_generator;
    $this->numberGeneration = $number_generation;
    $this->roomLibrary = $room_library;

    // Try to inject AI service if available
    try {
      if (\Drupal::hasService('ai_conversation.ai_api_service')) {
        $this->aiService = \Drupal::service('ai_conversation.ai_api_service');
        $this->logger->info('AI service available for room description generation');
      }
    }
    catch (\Exception $e) {
      $this->logger->info('AI service not available, using template-based descriptions');
    }
  }

  /**
   * Generate a single room.
   *
   * Workflow:
   * 1. Check if room already exists (return cached)
   * 2. Generate hexes (terrain, elevation, obstacles)
   * 3. Generate description (AI-driven narrative)
   * 4. Generate lighting effects
   * 5. Place entities (creatures, items, hazards)
   * 6. Validate against room.schema.json
   * 7. Persist to database
   *
   * @param array $context
   *   Generation context with keys:
   *   - campaign_id: int - Campaign ID
   *   - dungeon_id: int - Dungeon ID
   *   - level_id: int - Level ID
   *   - depth: int - Dungeon depth (1-based), drives difficulty
   *   - party_level: int - Average party level (1-20)
   *   - room_index: int - Index of this room in the level
   *   - theme: string - Dungeon theme (e.g., 'goblin_warrens')
   *   - room_size: string - 'small', 'medium', or 'large'
   *   - room_type: string - e.g., 'chamber', 'corridor', 'library'
   *   - terrain_type: string - e.g., 'stone', 'sand', 'crystal'
   *   - ai_service: object - Optional AI service for description generation
   *
   * @return array
   *   Complete room structure matching room.schema.json:
   *   - room_id: string (UUID)
   *   - name: string
   *   - description: string
   *   - hexes: array of hex objects with terrain
   *   - entities: array of entity_instance objects
   *   - terrain: object with primary_type, secondary_features
   *   - lighting: object with illumination, light_sources
   *   - state: object with explored, cleared flags
   *
   * @throws \Drupal\dungeoncrawler_content\Exception\GenerationException
   *   If generation fails or schema validation fails
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  public function generateRoom(array $context): array {
    $this->logger->info('Generating room for campaign @campaign, level @level', [
      '@campaign' => $context['campaign_id'],
      '@level' => $context['level_id'],
    ]);

    // Ensure seed is set for reproducible generation
    if (!isset($context['seed'])) {
      $context['seed'] = $this->numberGeneration->rollRange(1, 2147483647);
    }

    // Step 1: Check campaign-scoped cache
    $cached = $this->getRoomFromCache($context);
    if ($cached) {
      return $cached;
    }

    // Step 1b: Check the room template library for a reusable match
    $library_room = $this->findAndInstantiateFromLibrary($context);
    if ($library_room) {
      // Persist the library instance to the campaign cache
      try {
        $db_id = $this->persistRoom($context, $library_room);
        if ($db_id) {
          $library_room['db_id'] = $db_id;
        }
        // Update source_room_id link
        if (!empty($library_room['_library_source'])) {
          $this->database->update('dc_campaign_rooms')
            ->fields(['source_room_id' => $library_room['_library_source']])
            ->condition('id', $db_id)
            ->execute();
        }
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to persist library room: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
      $library_room['from_library'] = TRUE;
      return $library_room;
    }

    // Step 2: Generate hexes (terrain, elevation, obstacles)
    $hexes = $this->generateHexes($context);

    // Step 3: Generate description (AI-driven or template-based)
    $description_data = $this->generateDescription($context, $hexes);

    // Step 4: Generate lighting effects
    $lighting = $this->generateLighting($context);

    // Compute room_id early so entities can reference it
    $room_id = sprintf('room_%d_%d_%d',
      $context['dungeon_id'],
      $context['level_id'],
      $context['room_index']
    );
    $context['room_id'] = $room_id;

    // Step 5: Place entities (creatures, items, hazards)
    $entities = $this->generateEntities($context, $hexes);

    // Step 6: Generate entry and exit points
    $entry_points = $this->generateEntryPoints($hexes, $context);
    $exit_points = $this->generateExitPoints($hexes, $context);

    // Step 7: Enrich hexes with occupant data
    $enriched_hexes = $this->enrichHexesWithOccupants($hexes, $entities, $entry_points, $exit_points);

    // Build complete room data structure (room.schema.json compliant)
    $terrain_type = $context['terrain_type'] ?? 'stone_floor';
    $terrain_props = $this->terrainGenerator->getTerrainProperties($terrain_type);

    $room_data = [
      'schema_version' => '1.0.0',
      'room_id' => $room_id,
      'name' => $description_data['name'],
      'description' => $description_data['description'] ?? '',
      'gm_notes' => $description_data['gm_notes'] ?? '',
      'hexes' => $enriched_hexes,
      'room_type' => $context['room_type'] ?? 'chamber',
      'size_category' => $this->getSizeCategory(count($hexes)),
      'terrain' => [
        'type' => $terrain_type,
        'difficult_terrain' => $terrain_props['difficult_terrain'] ?? FALSE,
        'greater_difficult_terrain' => FALSE,
        'hazardous_terrain' => NULL,
        'ceiling_height_ft' => $this->getCeilingHeight($context['room_type'] ?? 'chamber'),
      ],
      'lighting' => $lighting,
      'entry_points' => $entry_points,
      'exit_points' => $exit_points,
      'environmental_effects' => [],
      'creatures' => $entities,
      'items' => [],
      'traps' => [],
      'hazards' => [],
      'obstacles' => [],
      'interactables' => [],
      'state' => [
        'explored' => FALSE,
        'visibility' => 'hidden',
      ],
      'hex_manifest' => $this->generateHexManifest($enriched_hexes, $entities, $terrain_type),
    ];

    // Step 6: Validate against room.schema.json
    try {
      if (method_exists($this->schemaLoader, 'validate')) {
        $validated = $this->schemaLoader->validate('room', $room_data);
        if (!$validated) {
          $this->logger->warning('Room data failed schema validation for @name — proceeding with unvalidated data', [
            '@name' => $room_data['name'],
          ]);
        }
      }
    }
    catch (\Throwable $e) {
      // Schema validation is non-blocking; log and continue.
      $this->logger->notice('Schema validation unavailable: @msg', [
        '@msg' => $e->getMessage(),
      ]);
    }

    // Step 7: Persist to database
    try {
      $db_id = $this->persistRoom($context, $room_data);
      if ($db_id) {
        $room_data['db_id'] = $db_id;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist room @name: @error', [
        '@name' => $room_data['name'],
        '@error' => $e->getMessage(),
      ]);
    }

    // Step 8: Catalogue into the room library for future reuse
    try {
      $template_id = $this->roomLibrary->catalogueRoom($room_data, $context);
      if ($template_id) {
        $room_data['_library_source'] = $template_id;
        // Link the campaign room back to the library template
        if (!empty($room_data['db_id'])) {
          $this->database->update('dc_campaign_rooms')
            ->fields(['source_room_id' => $template_id])
            ->condition('id', $room_data['db_id'])
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->notice('Room library catalogue skipped: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    $this->logger->info('Room generation complete: @name (library: @lib)', [
      '@name' => $room_data['name'],
      '@lib' => $room_data['_library_source'] ?? 'new',
    ]);

    return $room_data;
  }

  /**
   * Check if a room already exists in the database for the given context.
   *
   * @param array $context
   *   Generation context with campaign_id, dungeon_id, level_id, room_index.
   *
   * @return array|null
   *   Cached room data array, or NULL if not found.
   */
  protected function getRoomFromCache(array $context): ?array {
    $room_id = sprintf('room_%d_%d_%d',
      $context['dungeon_id'] ?? 0,
      $context['level_id'] ?? 0,
      $context['room_index'] ?? 0
    );

    try {
      $row = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r')
        ->condition('r.campaign_id', $context['campaign_id'] ?? 0)
        ->condition('r.room_id', $room_id)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return NULL;
      }

      $layout = json_decode($row['layout_data'], TRUE) ?: [];
      $contents = json_decode($row['contents_data'], TRUE) ?: [];

      return array_merge([
        'room_id' => $row['room_id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? '',
        'cached' => TRUE,
      ], $layout, $contents);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Attempt to find and instantiate a room from the template library.
   *
   * @param array $context
   *   Generation context (theme, room_type, party_level, etc.).
   *
   * @return array|null
   *   Instantiated room data, or NULL if no suitable template found.
   */
  protected function findAndInstantiateFromLibrary(array $context): ?array {
    try {
      $template = $this->roomLibrary->findTemplate([
        'theme' => $context['theme'] ?? '',
        'room_type' => $context['room_type'] ?? '',
        'size_category' => $context['room_size'] ?? '',
        'party_level' => $context['party_level'] ?? 1,
        'terrain_type' => $context['terrain_type'] ?? '',
        'exclude_template_ids' => $context['exclude_template_ids'] ?? [],
      ]);

      if (!$template) {
        return NULL;
      }

      $this->logger->info('Found library template @id for @theme/@type', [
        '@id' => $template['template_id'],
        '@theme' => $context['theme'] ?? '?',
        '@type' => $context['room_type'] ?? '?',
      ]);

      return $this->roomLibrary->instantiateTemplate($template, $context);
    }
    catch (\Exception $e) {
      $this->logger->notice('Library lookup failed, will generate fresh: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Get size_category from hex count per room.schema.json.
   *
   * @param int $hex_count
   *   Number of hexes in room
   *
   * @return string
   *   Size category enum value
   */
  protected function getSizeCategory(int $hex_count): string {
    if ($hex_count === 1) {
      return 'tiny';
    }
    elseif ($hex_count <= 3) {
      return 'small';
    }
    elseif ($hex_count <= 7) {
      return 'medium';
    }
    elseif ($hex_count <= 12) {
      return 'large';
    }
    elseif ($hex_count <= 19) {
      return 'huge';
    }
    else {
      return 'gargantuan';
    }
  }

  /**
   * Get ceiling height based on room type.
   *
   * @param string $room_type
   *   Room type
   *
   * @return int
   *   Ceiling height in feet
   */
  protected function getCeilingHeight(string $room_type): int {
    $heights = [
      'corridor' => 10,
      'chamber' => 15,
      'cavern' => 20,
      'hall' => 20,
      'throne_room' => 30,
      'library' => 20,
      'vault' => 15,
      'arena' => 40,
    ];

    return $heights[$room_type] ?? 10;
  }

  /**
   * Generate hexes for a room.
   *
   * Creates the hex layout including terrain and obstacles.
   *
   * Algorithm:
   * 1. Determine hexes within room radius based on room_size
   * 2. Generate terrain distribution via TerrainGeneratorService
   * 3. Add elevation variations
   * 4. Scatter obstacles (pillars, walls, chasms)
   *
   * @param array $context
   *   @see self::generateRoom()
   *
   * @return array
   *   Array of hex objects:
   *   [
   *     {
   *       "q": 0,
   *       "r": 0,
   *       "terrain": "stone",
   *       "elevation_ft": 0,
   *       "objects": []
   *     },
   *     ...
   *   ]
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function generateHexes(array $context): array {
    $party_size = $context['party_size'] ?? 4;
    $room_type = $context['room_type'] ?? 'chamber';
    $depth = $context['depth'] ?? 1;
    $theme = $context['theme'] ?? 'dungeon';
    $terrain_type = $context['terrain_type'] ?? 'stone_floor';
    $seed = isset($context['seed']) ? (int) $context['seed'] : $this->numberGeneration->rollRange(1, 2147483647);

    // Calculate appropriate dimensions based on party size and room type
    $dimensions = $this->calculateRoomDimensions($party_size, $room_type, $depth, $seed);

    $width = $dimensions['width'];
    $height = $dimensions['height'];

    // Generate base hex grid - all hexes within bounds
    $hexes = [];
    $center_q = 0;
    $center_r = 0;
    
    // Generate rectangular hex grid
    for ($q = $center_q - (int)($width / 2); $q <= $center_q + (int)($width / 2); $q++) {
      for ($r = $center_r - (int)($height / 2); $r <= $center_r + (int)($height / 2); $r++) {
        $hexes[] = ['q' => $q, 'r' => $r];
      }
    }

    // Apply terrain using TerrainGeneratorService
    $hexes_with_terrain = $this->terrainGenerator->generateTerrain(
      $hexes,
      $theme,
      $terrain_type,
      $seed
    );

    // Place obstacles
    $hexes_with_obstacles = $this->terrainGenerator->placeObstacles($hexes_with_terrain, $seed);

    $this->logger->debug('Generated @count hexes for party of @size (@wxh room)', [
      '@count' => count($hexes_with_obstacles),
      '@size' => $party_size,
      '@w' => $width,
      '@h' => $height,
    ]);

    return $hexes_with_obstacles;
  }

  /**
   * Calculate room dimensions appropriate for party size.
   *
   * Ensures tactical space for combat:
   * - Minimum 2 hexes per party member for combat maneuvering
   * - Boss rooms get 50% more space
   * - Corridors are narrower (2-3 hexes wide)
   *
   * @param int $party_size
   *   Number of party members
   * @param string $room_type
   *   Room type (chamber, corridor, boss_room, etc.)
   * @param int $depth
   *   Dungeon depth (deeper = potentially larger)
   *
   * @return array
   *   Array with 'width' and 'height' keys
   */
  protected function calculateRoomDimensions(int $party_size, string $room_type, int $depth, int $seed): array {
    $rng = new SeededRandomSequence($seed ^ abs(crc32('room_dimensions')));

    // Base hex count: 2-3 hexes per party member
    $min_hexes_per_party = 2;
    $max_hexes_per_party = 3;

    $target_hexes = $rng->nextInt(
      $party_size * $min_hexes_per_party,
      $party_size * $max_hexes_per_party
    );

    // Adjust for room type
    if ($room_type === 'boss_room') {
      // Boss rooms need 50% more space for epic battles
      $target_hexes = (int) ($target_hexes * 1.5);
    }
    elseif ($room_type === 'corridor') {
      // Corridors are narrow
      $target_hexes = max(6, (int) ($target_hexes * 0.6));
      return [
        'width' => $rng->nextInt(2, 3),
        'height' => max(3, (int) ($target_hexes / 2)),
      ];
    }

    // Add depth bonus (deeper dungeons can be slightly larger)
    $depth_bonus = min(10, $depth * 2);
    $target_hexes += $depth_bonus;

    // Calculate rough square dimensions
    $side = max(5, (int) sqrt($target_hexes));

    return [
      'width' => $side + $rng->nextInt(-1, 2),
      'height' => $side + $rng->nextInt(-1, 2),
    ];
  }

  /**
   * Generate room description via AI.
   *
   * Uses Claude/Gemini to create narrative description compatible with
   * thematic and difficulty requirements.
   *
   * Algorithm:
   * 1. Build AI prompt with theme, room_type, terrain, depth context
   * 2. Call AI service (if available) for narrative generation
   * 3. Fallback to template-based description if AI unavailable
   * 4. Parse and validate response
   *
   * @param array $context
   *   @see self::generateRoom()
   * @param array $hexes
   *   Generated hexes from generateHexes()
   *
   * @return array
   *   Description object:
   *   {
   *     "name": "The Fungal Pantry",
   *     "description": "As you enter...",
   *     "gm_notes": "Hidden door at q2,r1"
   *   }
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function generateDescription(array $context, array $hexes): array {
    $theme = $context['theme'] ?? 'dungeon';
    $room_type = $context['room_type'] ?? 'chamber';
    $terrain_type = $context['terrain_type'] ?? 'stone_floor';
    $depth = $context['depth'] ?? 1;
    $hex_count = count($hexes);
    $size_category = $this->getSizeCategory($hex_count);

    // Try AI-powered description if service is available
    if ($this->aiService && ($context['use_ai'] ?? TRUE)) {
      try {
        return $this->generateAIDescription($context, $hexes);
      }
      catch (\Exception $e) {
        $this->logger->warning('AI description generation failed, using fallback: @error', [
          '@error' => $e->getMessage(),
        ]);
        return $this->generateFallbackDescription($context, $hexes);
      }
    }
    else {
      // Fallback to template-based generation
      return $this->generateFallbackDescription($context, $hexes);
    }
  }

  /**
   * Generate AI-powered room description.
   *
   * Uses AI service to create evocative, contextual descriptions.
   *
   * @param array $context
   *   Generation context
   * @param array $hexes
   *   Room hexes
   *
   * @return array
   *   Description with keys: name, description, gm_notes
   *
   * @throws \Exception
   *   If AI service fails
   */
  protected function generateAIDescription(array $context, array $hexes): array {
    $prompt = $this->buildDescriptionPrompt($context, $hexes);

    // Call AI service directly
    // Note: AIApiService expects a conversation node, so we'll use a simpler approach
    // For now, use the prompt method and parse JSON response

    $this->logger->info('Generating AI description for room');

    // Attempt direct AI API call for description generation.
    try {
      if (method_exists($this->aiService, 'generateText')) {
        $response = $this->aiService->generateText($prompt);
      }
      elseif (method_exists($this->aiService, 'complete')) {
        $response = $this->aiService->complete($prompt);
      }
      else {
        // No compatible method — fall back.
        return $this->generateFallbackDescription($context, $hexes);
      }

      // Parse JSON response from AI.
      $text = is_array($response) ? ($response['text'] ?? json_encode($response)) : (string) $response;

      // Try to extract JSON from the response.
      if (preg_match('/\{[^}]*"name"[^}]*\}/s', $text, $matches)) {
        $parsed = json_decode($matches[0], TRUE);
        if ($parsed && !empty($parsed['name'])) {
          return [
            'name' => $parsed['name'],
            'description' => $parsed['description'] ?? '',
            'gm_notes' => $parsed['gm_notes'] ?? '',
          ];
        }
      }

      // If we can't parse JSON, use the text as description.
      $fallback = $this->generateFallbackDescription($context, $hexes);
      if (strlen($text) > 10) {
        $fallback['description'] = $text;
      }
      return $fallback;
    }
    catch (\Exception $e) {
      $this->logger->warning('AI description direct call failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      return $this->generateFallbackDescription($context, $hexes);
    }
  }

  /**
   * Build AI prompt for room description generation.
   *
   * @param array $context
   *   Generation context
   * @param array $hexes
   *   Room hexes
   *
   * @return string
   *   AI prompt text
   */
  protected function buildDescriptionPrompt(array $context, array $hexes): string {
    $theme = $context['theme'] ?? 'dungeon';
    $room_type = $context['room_type'] ?? 'chamber';
    $terrain_type = $context['terrain_type'] ?? 'stone_floor';
    $depth = $context['depth'] ?? 1;
    $party_size = $context['party_size'] ?? 4;
    $party_level = $context['party_level'] ?? 1;
    $difficulty = $context['difficulty'] ?? 'moderate';
    $hex_count = count($hexes);
    $size_category = $this->getSizeCategory($hex_count);

    // Count creatures for context
    $creature_count = 0;
    // This will be filled after entity placement

    $prompt = "Generate a dungeon room description for a Pathfinder 2E game.\n\n";
    $prompt .= "Context:\n";
    $prompt .= "- Theme: {$theme}\n";
    $prompt .= "- Room Type: {$room_type}\n";
    $prompt .= "- Size: {$size_category} ({$hex_count} hexes, ~" . ($hex_count * 5) . " feet diameter)\n";
    $prompt .= "- Primary Terrain: {$terrain_type}\n";
    $prompt .= "- Dungeon Depth: Level {$depth}\n";
    $prompt .= "- Party: Level {$party_level}, {$party_size} members\n";
    $prompt .= "- Encounter Difficulty: {$difficulty}\n\n";

    $prompt .= "Requirements:\n";
    $prompt .= "1. Room name should be evocative and thematic (max 40 characters)\n";
    $prompt .= "2. Description should be 2-3 vivid sentences for players, describing atmosphere, sensory details\n";
    $prompt .= "3. GM notes should include tactical considerations, hidden details, or encounter hooks (1-2 sentences)\n";
    $prompt .= "4. Match the theme and difficulty - {$difficulty} encounters should feel appropriately challenging\n\n";

    $prompt .= "Return ONLY valid JSON (no markdown, no extra text):\n";
    $prompt .= "{\n";
    $prompt .= '  "name": "Room name here",' . "\n";
    $prompt .= '  "description": "Player description here",' . "\n";
    $prompt .= '  "gm_notes": "GM notes here"' . "\n";
    $prompt .= "}\n";

    return $prompt;
  }

  /**
   * Generate fallback description without AI.
   *
   * Uses template-based generation for predictable output.
   *
   * @param array $context
   *   Generation context
   * @param array $hexes
   *   Room hexes
   *
   * @return array
   *   Description object
   */
  protected function generateFallbackDescription(array $context, array $hexes): array {
    $theme = $context['theme'] ?? 'dungeon';
    $room_type = $context['room_type'] ?? 'chamber';
    $terrain_type = $context['terrain_type'] ?? 'stone_floor';
    $hex_count = count($hexes);
    $size_category = $this->getSizeCategory($hex_count);

    // Generate template-based name
    $terrain_suffix = $this->terrainGenerator->getTerrainNameSuffix($terrain_type);
    $name = ucfirst($room_type) . ' - ' . $terrain_suffix;

    // Template description based on size
    $size_adjectives = [
      'tiny' => 'cramped',
      'small' => 'small',
      'medium' => 'spacious',
      'large' => 'vast',
      'huge' => 'enormous',
      'gargantuan' => 'massive',
    ];

    $adjective = $size_adjectives[$size_category] ?? 'spacious';

    $description = "You enter a {$adjective} {$room_type} with {$terrain_type} surfaces. ";
    $description .= "The air is stale. The room awaits exploration.";

    $gm_notes = "Standard {$theme} room. Check for hidden details or encounters.";

    return [
      'name' => $name,
      'description' => $description,
      'gm_notes' => $gm_notes,
    ];
  }

  /**
   * Get theme-appropriate air quality description.
   *
   * @param string $theme
   *   Theme key
   *
   * @return string
   *   Air quality description
   */
  protected function getThemeAirQuality(string $theme): string {
    $qualities = [
      'goblin_warrens' => 'thick with the stench of decay',
      'fungal_caverns' => 'heavy with spores',
      'spider_nests' => 'filled with sticky webbing',
      'undead_crypts' => 'cold and stagnant',
      'flooded_depths' => 'damp and musty',
      'lava_forge' => 'scorching hot',
      'shadow_realm' => 'unnaturally still',
      'crystal_caves' => 'crisp and clear',
      'beast_den' => 'rank with animal musk',
      'abandoned_mine' => 'dusty and stale',
      'eldritch_library' => 'charged with arcane energy',
      'dragon_lair' => 'thick with sulfur',
      'elemental_nexus' => 'crackling with elemental power',
      'demonic_sanctum' => 'oppressive and foul',
      'ancient_ruins' => 'still and ancient',
    ];

    return $qualities[$theme] ?? 'stale';
  }

  /**
   * Get theme-appropriate ambiance description.
   *
   * @param string $theme
   *   Theme key
   *
   * @return string
   *   Ambiance description
   */
  protected function getThemeAmbiance(string $theme): string {
    $ambiances = [
      'goblin_warrens' => 'You hear distant chittering echoes.',
      'fungal_caverns' => 'Bioluminescent fungi cast an eerie glow.',
      'spider_nests' => 'Cobwebs cling to every surface.',
      'undead_crypts' => 'An oppressive silence pervades the space.',
      'flooded_depths' => 'Water drips steadily from above.',
      'lava_forge' => 'Heat radiates from glowing fissures.',
      'shadow_realm' => 'Shadows seem to move of their own accord.',
      'crystal_caves' => 'Crystals hum with latent energy.',
      'beast_den' => 'Claw marks score the walls.',
      'abandoned_mine' => 'Rusted tools lie scattered about.',
      'eldritch_library' => 'Ancient tomes line crumbling shelves.',
      'dragon_lair' => 'Treasures glint in the distance.',
      'elemental_nexus' => 'Reality seems to warp at the edges.',
      'demonic_sanctum' => 'Unholy symbols adorn the walls.',
      'ancient_ruins' => 'Time has worn this place smooth.',
    ];

    return $ambiances[$theme] ?? 'The room awaits exploration.';
  }

  /**
   * Generate lighting effects for the room.
   *
   * Generates lighting per room.schema.json lighting structure.
   *
   * Algorithm:
   * 1. Determine base lighting level from theme (bright_light/dim_light/darkness)
   * 2. Place light sources with bright_radius_ft and dim_radius_ft
   * 3. Use schema-compliant light source types
   *
   * @param array $context
   *   @see self::generateRoom()
   *
   * @return array
   *   Lighting object matching room.schema.json:
   *   {
   *     "level": "bright_light|dim_light|darkness|magical_darkness",
   *     "light_sources": [
   *       {
   *         "type": enum (torch, brazier, magical_crystal, etc.),
   *         "hex": {"q": int, "r": int},
   *         "bright_radius_ft": number,
   *         "dim_radius_ft": number,
   *         "permanent": bool
   *       }
   *     ]
   *   }
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function generateLighting(array $context): array {
    $theme = $context['theme'] ?? 'goblin_warrens';
    $room_size = $context['room_size'] ?? 'medium';
    $rng = $this->createScopedRng($context, 'lighting');

    // Theme-based lighting profiles (room.schema.json compliant)
    $lighting_profiles = [
      'goblin_warrens' => ['level' => 'dim_light', 'source_type' => 'torch'],
      'fungal_caverns' => ['level' => 'dim_light', 'source_type' => 'bioluminescent'],
      'spider_nests' => ['level' => 'darkness', 'source_type' => 'none'],
      'undead_crypts' => ['level' => 'darkness', 'source_type' => 'torch'],
      'flooded_depths' => ['level' => 'darkness', 'source_type' => 'bioluminescent'],
      'lava_forge' => ['level' => 'bright_light', 'source_type' => 'lava_glow'],
      'shadow_realm' => ['level' => 'magical_darkness', 'source_type' => 'none'],
      'crystal_caves' => ['level' => 'bright_light', 'source_type' => 'magical_crystal'],
      'beast_den' => ['level' => 'dim_light', 'source_type' => 'torch'],
      'abandoned_mine' => ['level' => 'darkness', 'source_type' => 'torch'],
      'eldritch_library' => ['level' => 'dim_light', 'source_type' => 'magical_flame'],
      'dragon_lair' => ['level' => 'bright_light', 'source_type' => 'lava_glow'],
      'elemental_nexus' => ['level' => 'bright_light', 'source_type' => 'magical_crystal'],
      'demonic_sanctum' => ['level' => 'dim_light', 'source_type' => 'magical_flame'],
      'ancient_ruins' => ['level' => 'darkness', 'source_type' => 'none'],
    ];

    $profile = $lighting_profiles[$theme] ?? [
      'level' => 'dim_light',
      'source_type' => 'torch',
    ];

    $lighting = [
      'level' => $profile['level'],
      'light_sources' => [],
    ];

    // If source_type is not 'none', scatter light sources
    if ($profile['source_type'] !== 'none') {
      $count_map = ['small' => 2, 'medium' => 3, 'large' => 5];
      $source_count = $count_map[$room_size] ?? 3;

      // Light radii by type (room.schema.json compliant)
      $light_properties = [
        'torch' => ['bright' => 20, 'dim' => 40],
        'brazier' => ['bright' => 30, 'dim' => 60],
        'magical_crystal' => ['bright' => 40, 'dim' => 80],
        'bioluminescent' => ['bright' => 10, 'dim' => 30],
        'lava_glow' => ['bright' => 20, 'dim' => 60],
        'sunrod' => ['bright' => 30, 'dim' => 60],
        'lantern' => ['bright' => 30, 'dim' => 60],
        'magical_flame' => ['bright' => 40, 'dim' => 80],
      ];

      for ($i = 0; $i < $source_count; $i++) {
        // Place randomly within room radius
        $radius_map = ['small' => 2, 'medium' => 3, 'large' => 5];
        $radius = $radius_map[$room_size] ?? 3;

        $q = $rng->nextInt(-$radius, $radius);
        $r = $rng->nextInt(-$radius, $radius);

        $props = $light_properties[$profile['source_type']] ?? ['bright' => 20, 'dim' => 40];

        $lighting['light_sources'][] = [
          'type' => $profile['source_type'],
          'hex' => ['q' => $q, 'r' => $r],
          'bright_radius_ft' => $props['bright'],
          'dim_radius_ft' => $props['dim'],
          'permanent' => TRUE,
        ];
      }
    }

    return $lighting;
  }

  /**
   * Generate entities (creatures, items, traps, hazards).
   *
   * Orchestrates entity generation and placement using EncounterGeneratorService
   * and EntityPlacerService.
   *
   * Algorithm:
   * 1. Generate encounter via encounterGenerator (calculates XP budget, selects creatures)
   * 2. Place entities via entityPlacer (finds valid hexes, avoids collisions)
   * 3. Return entity_instance.schema.json compliant objects
   *
   * @param array $context
   *   @see self::generateRoom()
   * @param array $hexes
   *   Generated hexes
   *
   * @return array
   *   Array of entity_instance.schema.json objects
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function generateEntities(array $context, array $hexes): array {
    // Determine if this room should have an encounter
    $room_type = $context['room_type'] ?? 'chamber';
    $depth = $context['depth'] ?? 1;

    // Some rooms are empty (30% chance for corridors, 10% for chambers)
    $empty_chance = $room_type === 'corridor' ? 30 : 10;
    $rng = $this->createScopedRng($context, 'entities_presence');
    $is_empty = $rng->chance($empty_chance);

    if ($is_empty) {
      $this->logger->info('Room generated empty (random chance)');
      return [];
    }

    // Generate encounter
    $encounter = $this->encounterGenerator->generateEncounter($context);

    if (empty($encounter['combatants'])) {
      $this->logger->info('No combatants generated for encounter');
      return [];
    }

    // Place entities using EntityPlacerService
    $placed_entities = $this->entityPlacer->placeEntities(
      $encounter['combatants'],
      $hexes,
      $context
    );

    $this->logger->info('Generated @count entities for room', [
      '@count' => count($placed_entities),
    ]);

    return $placed_entities;
  }

  /**
   * Generate entry points for party members.
   *
   * Entry points are typically on the room edge closest to the dungeon entrance.
   *
   * @param array $hexes
   *   Room hexes
   * @param array $context
   *   Generation context
   *
   * @return array
   *   Array of entry point hexes with metadata:
   *   [
   *     {
   *       "hex": {"q": int, "r": int},
   *       "direction": "north|south|east|west",
   *       "type": "door|archway|corridor",
   *       "locked": bool,
   *       "hidden": bool
   *     },
   *     ...
   *   ]
   */
  protected function generateEntryPoints(array $hexes, array $context): array {
    if (empty($hexes)) {
      return [];
    }

    // Find hex closest to center (0,0) for entry point
    $center = ['q' => 0, 'r' => 0];
    $closest_hex = $hexes[0];
    $min_distance = $this->hexUtility->distance($hexes[0], $center);

    foreach ($hexes as $hex) {
      $distance = $this->hexUtility->distance($hex, $center);
      if ($distance < $min_distance) {
        $min_distance = $distance;
        $closest_hex = $hex;
      }
    }

    return [
      [
        'hex' => ['q' => $closest_hex['q'], 'r' => $closest_hex['r']],
        'direction' => 'south',
        'type' => 'door',
        'locked' => FALSE,
        'hidden' => FALSE,
      ],
    ];
  }

  /**
   * Generate exit points to other rooms.
   *
   * @param array $hexes
   *   Room hexes
   * @param array $context
   *   Generation context
   *
   * @return array
   *   Array of exit point hexes
   */
  protected function generateExitPoints(array $hexes, array $context): array {
    if (empty($hexes)) {
      return [];
    }

    $room_type = $context['room_type'] ?? 'chamber';

    // Corridors typically have 2 exits, chambers have 1-3
    $rng = $this->createScopedRng($context, 'exit_points');
    $exit_count = $room_type === 'corridor' ? 2 : $rng->nextInt(1, 3);

    $exits = [];
    $center = ['q' => 0, 'r' => 0];

    // Find farthest hexes for exits
    usort($hexes, function($a, $b) use ($center) {
      return $this->hexUtility->distance($b, $center) <=> $this->hexUtility->distance($a, $center);
    });

    $candidates = array_slice($hexes, 0, min($exit_count + 2, count($hexes)));

    for ($i = 0; $i < min($exit_count, count($candidates)); $i++) {
      $exit_hex = $candidates[$i];
      $exits[] = [
        'hex' => ['q' => $exit_hex['q'], 'r' => $exit_hex['r']],
        'direction' => 'north',
        'type' => 'door',
        'locked' => $rng->chance(10), // 10% chance locked
        'hidden' => $rng->chance(5),  // 5% chance hidden
        'leads_to' => NULL, // Set during dungeon level connection phase
      ];
    }

    return $exits;
  }

  /**
   * Enrich hexes with occupant information.
   *
   * Adds references to entities placed in each hex.
   *
   * @param array $hexes
   *   Base hex array
   * @param array $entities
   *   Placed entities
   * @param array $entry_points
   *   Entry point hexes
   * @param array $exit_points
   *   Exit point hexes
   *
   * @return array
   *   Enriched hex array with occupant data
   */
  protected function enrichHexesWithOccupants(
    array $hexes,
    array $entities,
    array $entry_points,
    array $exit_points
  ): array {
    $enriched = [];

    foreach ($hexes as $hex) {
      $hex_data = $hex;

      // Add occupants array
      $hex_data['occupants'] = [];

      // Check if this hex has an entry point
      foreach ($entry_points as $entry) {
        if ($entry['hex']['q'] === $hex['q'] && $entry['hex']['r'] === $hex['r']) {
          $hex_data['occupants'][] = [
            'type' => 'entry_point',
            'data' => $entry,
          ];
        }
      }

      // Check if this hex has an exit point
      foreach ($exit_points as $exit) {
        if ($exit['hex']['q'] === $hex['q'] && $exit['hex']['r'] === $hex['r']) {
          $hex_data['occupants'][] = [
            'type' => 'exit_point',
            'data' => $exit,
          ];
        }
      }

      // Check if this hex has entities
      foreach ($entities as $entity) {
        if (isset($entity['placement']['hex'])) {
          $entity_hex = $entity['placement']['hex'];
          if ($entity_hex['q'] === $hex['q'] && $entity_hex['r'] === $hex['r']) {
            $hex_data['occupants'][] = [
              'type' => 'entity',
              'entity_instance_id' => $entity['entity_instance_id'],
              'entity_type' => $entity['entity_type'],
              'entity_ref' => $entity['entity_ref']['content_id'],
              'facing' => $entity['placement']['facing'] ?? 0,
              'facing_direction' => $this->getFacingDirection($entity['placement']['facing'] ?? 0),
            ];
          }
        }
      }

      $enriched[] = $hex_data;
    }

    return $enriched;
  }

  /**
   * Build deterministic scoped RNG for room generation sub-systems.
   */
  protected function createScopedRng(array $context, string $scope): SeededRandomSequence {
    $base_seed = isset($context['seed']) ? (int) $context['seed'] : 1;
    $scope_hash = abs(crc32($scope));
    return new SeededRandomSequence($base_seed ^ $scope_hash);
  }

  /**
   * Generate comprehensive hex manifest.
   *
   * Creates a summary of what's in each hex for easy reference.
   *
   * @param array $hexes
   *   Enriched hex array
   * @param array $entities
   *   Entity array
   * @param string $primary_terrain
   *   Primary terrain type
   *
   * @return array
   *   Hex manifest with complete occupancy data:
   *   {
   *     "total_hexes": int,
   *     "passable_hexes": int,
   *     "occupied_hexes": int,
   *     "by_hex": [
   *       "0,0": {
   *         "q": 0,
   *         "r": 0,
   *         "terrain": "stone_floor",
   *         "elevation_ft": 0,
   *         "passable": true,
   *         "objects": [],
   *         "creatures": [],
   *         "entry_point": bool,
   *         "exit_point": bool,
   *         "summary": "Empty stone floor hex"
   *       },
   *       ...
   *     ]
   *   }
   */
  protected function generateHexManifest(
    array $hexes,
    array $entities,
    string $primary_terrain
  ): array {
    $manifest = [
      'total_hexes' => count($hexes),
      'passable_hexes' => 0,
      'occupied_hexes' => 0,
      'creature_count' => 0,
      'entry_point_count' => 0,
      'exit_point_count' => 0,
      'by_hex' => [],
    ];

    foreach ($hexes as $hex) {
      $key = sprintf('%d,%d', $hex['q'], $hex['r']);

      $terrain = $hex['terrain_override'] ?? $primary_terrain;
      $is_passable = $this->terrainGenerator->isPassable($terrain);

      // Check for blocking objects
      $has_blocking_objects = FALSE;
      $object_names = [];
      if (isset($hex['objects']) && !empty($hex['objects'])) {
        foreach ($hex['objects'] as $obj) {
          $object_names[] = $obj['name'] ?? $obj['type'];
          if (isset($obj['blocks_movement']) && $obj['blocks_movement'] === TRUE) {
            $is_passable = FALSE;
            $has_blocking_objects = TRUE;
          }
        }
      }

      // Extract occupants
      $creatures = [];
      $creature_details = [];
      $has_entry = FALSE;
      $has_exit = FALSE;

      if (isset($hex['occupants']) && !empty($hex['occupants'])) {
        foreach ($hex['occupants'] as $occupant) {
          if ($occupant['type'] === 'entity' && isset($occupant['entity_type'])) {
            if ($occupant['entity_type'] === 'creature') {
              $creatures[] = $occupant['entity_ref'];
              $creature_details[] = [
                'name' => $occupant['entity_ref'],
                'facing' => $occupant['facing'] ?? 0,
                'facing_direction' => $occupant['facing_direction'] ?? 'E',
              ];
              $manifest['creature_count']++;
            }
          }
          elseif ($occupant['type'] === 'entry_point') {
            $has_entry = TRUE;
            $manifest['entry_point_count']++;
          }
          elseif ($occupant['type'] === 'exit_point') {
            $has_exit = TRUE;
            $manifest['exit_point_count']++;
          }
        }
      }

      if ($is_passable) {
        $manifest['passable_hexes']++;
      }

      if (!empty($creatures) || !empty($object_names) || $has_entry || $has_exit) {
        $manifest['occupied_hexes']++;
      }

      // Build summary
      $summary_parts = [];
      if ($has_entry) {
        $summary_parts[] = 'ENTRY POINT';
      }
      if ($has_exit) {
        $summary_parts[] = 'EXIT POINT';
      }
      if (!empty($creature_details)) {
        $creature_strings = array_map(
          fn($c) => sprintf('%s (facing %s)', $c['name'], $c['facing_direction']),
          $creature_details
        );
        $summary_parts[] = 'Creatures: ' . implode(', ', $creature_strings);
      }
      if (!empty($object_names)) {
        $summary_parts[] = 'Objects: ' . implode(', ', $object_names);
      }
      if (empty($summary_parts)) {
        $summary_parts[] = $is_passable ? 'Empty passable hex' : 'Impassable terrain';
      }

      $manifest['by_hex'][$key] = [
        'q' => $hex['q'],
        'r' => $hex['r'],
        'terrain' => $terrain,
        'elevation_ft' => $hex['elevation_ft'] ?? 0,
        'passable' => $is_passable,
        'objects' => $object_names,
        'creatures' => $creatures,
        'creature_details' => $creature_details,
        'entry_point' => $has_entry,
        'exit_point' => $has_exit,
        'summary' => implode(' | ', $summary_parts),
      ];
    }

    return $manifest;
  }

  /**
   * Convert facing degrees to compass direction.
   *
   * Hex directions:
   * - 0 = E (East)
   * - 1 = SE (Southeast)
   * - 2 = SW (Southwest)
   * - 3 = W (West)
   * - 4 = NW (Northwest)
   * - 5 = NE (Northeast)
   *
   * @param int $facing_degrees
   *   Facing in degrees (0-5)
   *
   * @return string
   *   Compass direction (E, SE, SW, W, NW, NE)
   */
  protected function getFacingDirection(int $facing_degrees): string {
    $directions = ['E', 'SE', 'SW', 'W', 'NW', 'NE'];
    return $directions[$facing_degrees % 6];
  }

  /**
   * Persist room to database.
   *
   * @param array $context
   *   Generation context
   * @param array $room_data
   *   Complete room data (room.schema.json format)
   *
   * @return int
   *   Room database ID
   */
  protected function persistRoom(array $context, array $room_data): int {
    $now = time();
    $campaign_id = $context['campaign_id'] ?? 0;
    $room_id = $room_data['room_id'] ?? '';

    $layout_data = json_encode([
      'hexes' => $room_data['hexes'] ?? [],
      'hex_manifest' => $room_data['hex_manifest'] ?? [],
      'entry_points' => $room_data['entry_points'] ?? [],
      'exit_points' => $room_data['exit_points'] ?? [],
      'terrain' => $room_data['terrain'] ?? [],
      'lighting' => $room_data['lighting'] ?? [],
    ]);

    $contents_data = json_encode([
      'creatures' => $room_data['creatures'] ?? [],
      'items' => $room_data['items'] ?? [],
      'traps' => $room_data['traps'] ?? [],
      'hazards' => $room_data['hazards'] ?? [],
      'obstacles' => $room_data['obstacles'] ?? [],
      'interactables' => $room_data['interactables'] ?? [],
    ]);

    $env_tags = json_encode($room_data['environmental_effects'] ?? []);

    $db_id = $this->database->insert('dc_campaign_rooms')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'name' => $room_data['name'] ?? 'Unknown Room',
        'description' => $room_data['description'] ?? '',
        'environment_tags' => $env_tags,
        'layout_data' => $layout_data,
        'contents_data' => $contents_data,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    // Initialize room state.
    $this->database->insert('dc_campaign_room_states')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'is_cleared' => 0,
        'fog_state' => json_encode($room_data['state'] ?? ['explored' => FALSE, 'visibility' => 'hidden']),
        'last_visited' => 0,
        'updated' => $now,
      ])
      ->execute();

    return (int) $db_id;
  }

}
