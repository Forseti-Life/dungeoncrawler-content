<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\RoomGeneratorService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP controller for room generation API endpoints.
 *
 * Provides REST API for generating individual dungeon rooms.
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class RoomGeneratorController extends ControllerBase {

  /**
   * The room generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomGeneratorService
   */
  protected RoomGeneratorService $roomGenerator;

  /**
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * Constructs a RoomGeneratorController object.
   *
   * @param \Drupal\dungeoncrawler_content\Service\RoomGeneratorService $room_generator
   *   The room generator service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   */
  public function __construct(
    RoomGeneratorService $room_generator,
    SchemaLoader $schema_loader
  ) {
    $this->roomGenerator = $room_generator;
    $this->schemaLoader = $schema_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_generator'),
      $container->get('dungeoncrawler_content.schema_loader')
    );
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}/rooms
   *
   * Generate a new room in a dungeon level.
   *
   * Request:
   * {
   *   "level_id": "uuid",
   *   "depth": 1,
   *   "party_level": 5,
   *   "room_size": "medium",
   *   "room_type": "chamber",
   *   "terrain_type": "stone"
   * }
   *
   * Response: 201 Created
   * {
   *   "room_id": "uuid",
   *   "name": "The Goblin Barracks",
   *   "description": "...",
   *   "hexes": [...],
   *   "entities": [...],
   *   ...room.schema.json...
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with generated room (201 Created)
   */
  public function createRoom(
    Request $request,
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    // 1. Parse and validate request body.
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], JsonResponse::HTTP_BAD_REQUEST);
    }

    // 2. Validate required fields.
    $required = ['level_id', 'depth', 'party_level'];
    foreach ($required as $field) {
      if (empty($data[$field]) && $data[$field] !== 0) {
        return new JsonResponse(
          ['error' => sprintf('Missing required field: %s', $field)],
          JsonResponse::HTTP_BAD_REQUEST
        );
      }
    }

    // 3. Validate party_level range.
    $party_level = (int) $data['party_level'];
    if ($party_level < 1 || $party_level > 20) {
      return new JsonResponse(
        ['error' => 'party_level must be between 1 and 20'],
        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
      );
    }

    // 4. Build generation context.
    $context = [
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'level_id' => $data['level_id'],
      'depth' => (int) $data['depth'],
      'party_level' => $party_level,
      'room_index' => (int) ($data['room_index'] ?? 0),
      'room_size' => $data['room_size'] ?? 'medium',
      'room_type' => $data['room_type'] ?? 'chamber',
      'terrain_type' => $data['terrain_type'] ?? 'stone_floor',
      'theme' => $data['theme'] ?? 'dungeon',
      'party_size' => (int) ($data['party_size'] ?? 4),
    ];

    // 5. Generate the room.
    try {
      $room_data = $this->roomGenerator->generateRoom($context);
      return new JsonResponse($room_data, JsonResponse::HTTP_CREATED);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(
        ['error' => $e->getMessage()],
        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
      );
    }
    catch (\Exception $e) {
      return new JsonResponse(
        ['error' => 'Room generation failed: ' . $e->getMessage()],
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}
   *
   * Get room details (either cached or newly generated if missing).
   *
   * Response: 200 OK
   * {
   *   "room_id": "uuid",
   *   "name": "The Goblin Barracks",
   *   ...room.schema.json...
   * }
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID.
   * @param string $room_id
   *   Room ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with room data (200 OK) or 404 Not Found
   */
  public function getRoom(
    int $campaign_id,
    int $dungeon_id,
    string $room_id
  ): JsonResponse {
    try {
      $db = \Drupal::database();

      // Load room from database.
      $row = $db->select('dc_campaign_rooms', 'r')
        ->fields('r')
        ->condition('r.campaign_id', $campaign_id)
        ->condition('r.room_id', $room_id)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return new JsonResponse(
          ['error' => 'Room not found'],
          JsonResponse::HTTP_NOT_FOUND
        );
      }

      $layout = json_decode($row['layout_data'], TRUE) ?: [];
      $contents = json_decode($row['contents_data'], TRUE) ?: [];
      $env_tags = json_decode($row['environment_tags'], TRUE) ?: [];

      // Load room state.
      $state_row = $db->select('dc_campaign_room_states', 's')
        ->fields('s')
        ->condition('s.campaign_id', $campaign_id)
        ->condition('s.room_id', $room_id)
        ->execute()
        ->fetchAssoc();

      $state = [
        'explored' => FALSE,
        'is_cleared' => FALSE,
        'visibility' => 'hidden',
      ];
      if ($state_row) {
        $fog = json_decode($state_row['fog_state'], TRUE) ?: [];
        $state = array_merge($state, $fog, [
          'is_cleared' => (bool) $state_row['is_cleared'],
          'last_visited' => (int) $state_row['last_visited'],
        ]);
      }

      $room_data = array_merge([
        'room_id' => $row['room_id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? '',
        'environment_tags' => $env_tags,
        'state' => $state,
      ], $layout, $contents);

      return new JsonResponse($room_data, JsonResponse::HTTP_OK);
    }
    catch (\Exception $e) {
      return new JsonResponse(
        ['error' => 'Failed to load room: ' . $e->getMessage()],
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}/regenerate
   *
   * Force regenerate a room (admin only).
   * WARNING: This will overwrite existing room data!
   *
   * Request:
   * {
   *   "confirm": true
   * }
   *
   * Response: 200 OK
   * {
   *   "status": "regenerated",
   *   "room_id": "uuid",
   *   ...room.schema.json...
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID.
   * @param string $room_id
   *   Room ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with regenerated room
   */
  public function regenerateRoom(
    Request $request,
    int $campaign_id,
    int $dungeon_id,
    string $room_id
  ): JsonResponse {
    // 1. Validate confirmation.
    $data = json_decode($request->getContent(), TRUE);
    if (empty($data['confirm'])) {
      return new JsonResponse(
        ['error' => 'Must confirm regeneration with {"confirm": true}'],
        JsonResponse::HTTP_BAD_REQUEST
      );
    }

    try {
      $db = \Drupal::database();

      // 2. Load existing room to get original context.
      $row = $db->select('dc_campaign_rooms', 'r')
        ->fields('r')
        ->condition('r.campaign_id', $campaign_id)
        ->condition('r.room_id', $room_id)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return new JsonResponse(
          ['error' => 'Room not found'],
          JsonResponse::HTTP_NOT_FOUND
        );
      }

      // 3. Extract original generation context from layout_data.
      $layout = json_decode($row['layout_data'], TRUE) ?: [];

      // Parse room_id to get dungeon_id, level_id, room_index.
      $parts = explode('_', $room_id);
      $context = [
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'level_id' => $parts[2] ?? 1,
        'room_index' => $parts[3] ?? 0,
        'depth' => 1,
        'party_level' => $layout['generation_context']['party_level'] ?? 5,
        'room_type' => 'chamber',
        'terrain_type' => $layout['terrain']['type'] ?? 'stone_floor',
        'theme' => 'dungeon',
        'party_size' => 4,
      ];

      // 4. Delete old room data.
      $db->delete('dc_campaign_rooms')
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->execute();

      $db->delete('dc_campaign_room_states')
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->execute();

      // 5. Regenerate.
      $room_data = $this->roomGenerator->generateRoom($context);

      return new JsonResponse(
        array_merge(['status' => 'regenerated'], $room_data),
        JsonResponse::HTTP_OK
      );
    }
    catch (\Exception $e) {
      return new JsonResponse(
        ['error' => 'Regeneration failed: ' . $e->getMessage()],
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

}
