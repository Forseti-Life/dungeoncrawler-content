<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\DungeonGenerationEngine;
use Drupal\dungeoncrawler_content\Service\DungeonCache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for dungeon generation and management.
 *
 * Provides REST API endpoints for procedural dungeon generation system.
 *
 * @see /docs/dungeoncrawler/issues/issue-4-procedural-dungeon-generation-design.md
 * Line 1634-1709
 */
class DungeonController extends ControllerBase {

  /**
   * The dungeon generation engine service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonGenerationEngine
   */
  protected DungeonGenerationEngine $dungeonEngine;

  /**
   * The dungeon cache service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonCache
   */
  protected DungeonCache $dungeonCache;

  /**
   * Constructs a DungeonController object.
   *
   * @param \Drupal\dungeoncrawler_content\Service\DungeonGenerationEngine $dungeon_engine
   *   The dungeon generation engine service.
   * @param \Drupal\dungeoncrawler_content\Service\DungeonCache $dungeon_cache
   *   The dungeon cache service.
   */
  public function __construct(
    DungeonGenerationEngine $dungeon_engine,
    DungeonCache $dungeon_cache
  ) {
    $this->dungeonEngine = $dungeon_engine;
    $this->dungeonCache = $dungeon_cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.dungeon_generation_engine'),
      $container->get('dungeoncrawler_content.dungeon_cache')
    );
  }

  /**
   * GET /api/dungeons/{dungeonId}
   *
   * Get dungeon details.
   * See design doc line 1634-1650
   *
   * @param int $dungeon_id
   *   Dungeon ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with dungeon data.
   */
  public function getDungeon(int $dungeon_id): JsonResponse {
    // Response:
    // {
    //     "id": 42,
    //     "name": "The Goblin King's Warren",
    //     "theme": "goblin_warren",
    //     "depth_levels": 3,
    //     "party_level_generated": 5,
    //     "description": "...",
    //     "lore": "...",
    //     "is_cleared": false,
    //     "levels": [...]
    // }

    // TODO: Implement getDungeon endpoint
    $dungeon = $this->dungeonCache->getDungeon($dungeon_id);
    return new JsonResponse($dungeon);
  }

  /**
   * POST /api/dungeons/generate
   *
   * Generate a new dungeon.
   * See design doc line 1652-1668
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with generated dungeon.
   */
  public function generateDungeon(Request $request): JsonResponse {
    // Request:
    // {
    //     "campaign_id": 1,
    //     "location_x": 100,
    //     "location_y": 200,
    //     "party_level": 5,
    //     "party_composition": [...]
    // }
    //
    // Response:
    // {
    //     "dungeon": {...},
    //     "generation_time_ms": 3420
    // }

    // TODO: Implement generateDungeon endpoint
    $data = json_decode($request->getContent(), TRUE);
    $start_time = microtime(TRUE);

    $dungeon = $this->dungeonEngine->generateDungeon(
      $data['campaign_id'] ?? 1,
      $data['location_x'] ?? 0,
      $data['location_y'] ?? 0,
      $data['party_level'] ?? 1,
      $data['party_composition'] ?? []
    );

    $generation_time = (microtime(TRUE) - $start_time) * 1000;

    return new JsonResponse([
      'dungeon' => $dungeon,
      'generation_time_ms' => round($generation_time, 2),
    ]);
  }

  /**
   * GET /api/dungeons/{dungeonId}/levels/{levelNumber}
   *
   * Get specific dungeon level details.
   * See design doc line 1670-1684
   *
   * @param int $dungeon_id
   *   Dungeon ID.
   * @param int $level_number
   *   Level number.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with level data.
   */
  public function getDungeonLevel(int $dungeon_id, int $level_number): JsonResponse {
    // Response:
    // {
    //     "level_number": 1,
    //     "name": "The Entrance Hall",
    //     "description": "...",
    //     "difficulty_rating": "moderate",
    //     "rooms": [...],
    //     "connections": [...]
    // }

    // TODO: Implement getDungeonLevel endpoint
    return new JsonResponse([]);
  }

  /**
   * POST /api/dungeons/{dungeonId}/state
   *
   * Update dungeon state (rooms discovered, encounters defeated, loot taken).
   * See design doc line 1686-1709
   *
   * @param int $dungeon_id
   *   Dungeon ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with updated state.
   */
  public function updateDungeonState(int $dungeon_id, Request $request): JsonResponse {
    // Request:
    // {
    //     "changes": [
    //         {"type": "room_discovered", "roomId": 5},
    //         {"type": "encounter_defeated", "encounterId": 12},
    //         {"type": "loot_taken", "lootId": 45, "characterId": 7}
    //     ]
    // }
    //
    // Response:
    // {
    //     "success": true,
    //     "dungeon_state": {...}
    // }

    // TODO: Implement updateDungeonState endpoint
    $data = json_decode($request->getContent(), TRUE);
    $this->dungeonCache->updateDungeonState($dungeon_id, $data['changes'] ?? []);

    return new JsonResponse([
      'success' => TRUE,
      'dungeon_state' => [],
    ]);
  }

}
