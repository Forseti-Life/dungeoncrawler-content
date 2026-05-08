<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\DungeonGeneratorService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP controller for dungeon generation API endpoints.
 *
 * Provides REST API for generating complete multi-level dungeons.
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class DungeonGeneratorController extends ControllerBase {

  /**
   * The dungeon generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonGeneratorService
   */
  protected DungeonGeneratorService $dungeonGenerator;

  /**
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * Constructs a DungeonGeneratorController object.
   *
   * @param \Drupal\dungeoncrawler_content\Service\DungeonGeneratorService $dungeon_generator
   *   The dungeon generator service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   */
  public function __construct(
    DungeonGeneratorService $dungeon_generator,
    SchemaLoader $schema_loader
  ) {
    $this->dungeonGenerator = $dungeon_generator;
    $this->schemaLoader = $schema_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.dungeon_generator'),
      $container->get('dungeoncrawler_content.schema_loader')
    );
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/generate
   *
   * Generate a complete new dungeon at world coordinates.
   *
   * Request:
   * {
   *   "location_x": 100,
   *   "location_y": 200,
   *   "party_level": 5,
   *   "party_size": 4,
   *   "party_composition": {
   *     "fighter": 1,
   *     "wizard": 1,
   *     "cleric": 1,
   *     "rogue": 1
   *   },
   *   "theme": null
   * }
   *
   * Response: 201 Created
   * {
   *   "dungeon_id": "uuid",
   *   "name": "The Goblin Warren",
   *   "theme": "goblin_warrens",
   *   "depth": 3,
   *   "location_x": 100,
   *   "location_y": 200,
   *   "levels": [
   *     { ...dungeon_level.schema.json... },
   *     { ...dungeon_level.schema.json... },
   *     { ...dungeon_level.schema.json... }
   *   ]
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with generated dungeon (201 Created)
   */
  public function generateDungeon(
    Request $request,
    int $campaign_id
  ): JsonResponse {
    // 1. Parse and validate request body.
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], JsonResponse::HTTP_BAD_REQUEST);
    }

    // 2. Validate required fields.
    $required = ['location_x', 'location_y', 'party_level'];
    foreach ($required as $field) {
      if (!isset($data[$field])) {
        return new JsonResponse(
          ['error' => sprintf('Missing required field: %s', $field)],
          JsonResponse::HTTP_BAD_REQUEST
        );
      }
    }

    $party_level = (int) $data['party_level'];
    if ($party_level < 1 || $party_level > 20) {
      return new JsonResponse(
        ['error' => 'party_level must be between 1 and 20'],
        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
      );
    }

    // 3. Check if dungeon already exists at location.
    try {
      $db = \Drupal::database();
      $dungeon_id_check = sprintf('dungeon_%d_%d_%d', $campaign_id, (int) $data['location_x'], (int) $data['location_y']);
      $existing = $db->select('dc_campaign_dungeons', 'd')
        ->fields('d')
        ->condition('d.campaign_id', $campaign_id)
        ->condition('d.dungeon_id', $dungeon_id_check)
        ->execute()
        ->fetchAssoc();

      if ($existing) {
        $dungeon_data = json_decode($existing['dungeon_data'], TRUE) ?: [];
        $dungeon_data['dungeon_id'] = $existing['dungeon_id'];
        $dungeon_data['name'] = $existing['name'];
        $dungeon_data['theme'] = $existing['theme'];
        return new JsonResponse(
          array_merge(['conflict' => 'Dungeon already exists at this location'], $dungeon_data),
          JsonResponse::HTTP_CONFLICT
        );
      }
    }
    catch (\Exception $e) {
      // DB check failed — proceed with generation.
    }

    // 4. Build generation context.
    $context = [
      'campaign_id' => $campaign_id,
      'location_x' => (int) $data['location_x'],
      'location_y' => (int) $data['location_y'],
      'party_level' => $party_level,
      'party_size' => (int) ($data['party_size'] ?? 4),
      'party_composition' => $data['party_composition'] ?? [],
      'theme' => $data['theme'] ?? NULL,
    ];

    // 5. Generate the dungeon.
    try {
      $dungeon_data = $this->dungeonGenerator->generateDungeon($context);
      return new JsonResponse($dungeon_data, JsonResponse::HTTP_CREATED);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(
        ['error' => $e->getMessage()],
        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
      );
    }
    catch (\Exception $e) {
      return new JsonResponse(
        ['error' => 'Dungeon generation failed: ' . $e->getMessage()],
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}
   *
   * Get dungeon details with all levels.
   *
   * Response: 200 OK
   * {
   *   "dungeon_id": "uuid",
   *   "name": "The Goblin Warren",
   *   "theme": "goblin_warrens",
   *   "depth": 3,
   *   "levels": [...]
   * }
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with dungeon data (200 OK) or 404 Not Found
   */
  public function getDungeon(
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    try {
      $db = \Drupal::database();

      $row = $db->select('dc_campaign_dungeons', 'd')
        ->fields('d')
        ->condition('d.campaign_id', $campaign_id)
        ->condition('d.id', $dungeon_id)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return new JsonResponse(
          ['error' => 'Dungeon not found'],
          JsonResponse::HTTP_NOT_FOUND
        );
      }

      $dungeon_data = json_decode($row['dungeon_data'], TRUE) ?: [];

      $response = array_merge([
        'id' => (int) $row['id'],
        'dungeon_id' => $row['dungeon_id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? '',
        'theme' => $row['theme'] ?? '',
        'created' => (int) $row['created'],
        'updated' => (int) $row['updated'],
      ], $dungeon_data);

      return new JsonResponse($response, JsonResponse::HTTP_OK);
    }
    catch (\Exception $e) {
      return new JsonResponse(
        ['error' => 'Failed to load dungeon: ' . $e->getMessage()],
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}
   *
   * Get single dungeon level.
   *
   * Response: 200 OK
   * {
   *   "level_id": "uuid",
   *   "depth": 1,
   *   "theme": "goblin_warrens",
   *   ...dungeon_level.schema.json...
   * }
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID (UUID).
   * @param int $depth
   *   Level depth (1-based).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with level data (200 OK) or 404 Not Found
   */
  public function getDungeonLevel(
    int $campaign_id,
    int $dungeon_id,
    int $depth
  ): JsonResponse {
    try {
      $db = \Drupal::database();

      // Load dungeon to get level data.
      $row = $db->select('dc_campaign_dungeons', 'd')
        ->fields('d')
        ->condition('d.campaign_id', $campaign_id)
        ->condition('d.id', $dungeon_id)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return new JsonResponse(
          ['error' => 'Dungeon not found'],
          JsonResponse::HTTP_NOT_FOUND
        );
      }

      $dungeon_data = json_decode($row['dungeon_data'], TRUE) ?: [];
      $levels = $dungeon_data['levels'] ?? [];

      // Find the level at the requested depth.
      foreach ($levels as $level) {
        if (($level['depth'] ?? 0) === $depth) {
          return new JsonResponse($level, JsonResponse::HTTP_OK);
        }
      }

      return new JsonResponse(
        ['error' => sprintf('Level at depth %d not found', $depth)],
        JsonResponse::HTTP_NOT_FOUND
      );
    }
    catch (\Exception $e) {
      return new JsonResponse(
        ['error' => 'Failed to load level: ' . $e->getMessage()],
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels
   *
   * Extend dungeon with new level (when party descends deeper).
   *
   * Request:
   * {
   *   "party_level": 5,
   *   "party_composition": {...}
   * }
   *
   * Response: 201 Created
   * {
   *   "level_id": "uuid",
   *   "depth": 4,
   *   ...dungeon_level.schema.json...
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with new level (201 Created)
   */
  public function addDungeonLevel(
    Request $request,
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], JsonResponse::HTTP_BAD_REQUEST);
    }

    $party_level = (int) ($data['party_level'] ?? 0);
    if ($party_level < 1 || $party_level > 20) {
      return new JsonResponse(
        ['error' => 'party_level must be between 1 and 20'],
        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
      );
    }

    try {
      $db = \Drupal::database();

      // Load existing dungeon.
      $row = $db->select('dc_campaign_dungeons', 'd')
        ->fields('d')
        ->condition('d.campaign_id', $campaign_id)
        ->condition('d.id', $dungeon_id)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return new JsonResponse(
          ['error' => 'Dungeon not found'],
          JsonResponse::HTTP_NOT_FOUND
        );
      }

      $dungeon_data = json_decode($row['dungeon_data'], TRUE) ?: [];
      $levels = $dungeon_data['levels'] ?? [];
      $new_depth = count($levels) + 1;

      // Check if level at new_depth already exists.
      foreach ($levels as $level) {
        if (($level['depth'] ?? 0) === $new_depth) {
          return new JsonResponse(
            ['error' => sprintf('Level at depth %d already exists', $new_depth)],
            JsonResponse::HTTP_CONFLICT
          );
        }
      }

      // Build context for new level generation.
      $context = [
        'campaign_id' => $campaign_id,
        'party_level' => $party_level,
        'party_size' => (int) ($data['party_size'] ?? 4),
        'party_composition' => $data['party_composition'] ?? [],
        'theme' => $row['theme'] ?? 'dungeon',
        'depth' => $new_depth,
        'level_id' => $new_depth,
        'dungeon_id' => $dungeon_id,
      ];

      $new_level = $this->dungeonGenerator->generateLevel($context);

      // Update dungeon_data with the new level.
      $levels[] = $new_level;
      $dungeon_data['levels'] = $levels;

      $db->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data),
          'updated' => time(),
        ])
        ->condition('id', $dungeon_id)
        ->execute();

      // Persist new level's rooms.
      foreach (($new_level['rooms'] ?? []) as $room) {
        $now = time();
        $db->insert('dc_campaign_rooms')
          ->fields([
            'campaign_id' => $campaign_id,
            'room_id' => $room['room_id'] ?? '',
            'name' => $room['name'] ?? 'Unknown Room',
            'description' => $room['description'] ?? '',
            'environment_tags' => json_encode($room['environmental_effects'] ?? []),
            'layout_data' => json_encode([
              'hexes' => $room['hexes'] ?? [],
              'hex_manifest' => $room['hex_manifest'] ?? [],
              'entry_points' => $room['entry_points'] ?? [],
              'exit_points' => $room['exit_points'] ?? [],
              'terrain' => $room['terrain'] ?? [],
              'lighting' => $room['lighting'] ?? [],
            ]),
            'contents_data' => json_encode([
              'creatures' => $room['creatures'] ?? [],
              'items' => $room['items'] ?? [],
              'traps' => $room['traps'] ?? [],
              'hazards' => $room['hazards'] ?? [],
            ]),
            'created' => $now,
            'updated' => $now,
          ])
          ->execute();
      }

      return new JsonResponse($new_level, JsonResponse::HTTP_CREATED);
    }
    catch (\Exception $e) {
      return new JsonResponse(
        ['error' => 'Failed to add level: ' . $e->getMessage()],
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

}
