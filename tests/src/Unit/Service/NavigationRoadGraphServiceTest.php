<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\NavigationRoadGraphService;
use Drupal\Tests\UnitTestCase;

/**
 * Unit coverage for deterministic road-graph distance resolution.
 *
 * @group dungeoncrawler_content
 * @group navigation
 */
class NavigationRoadGraphServiceTest extends UnitTestCase {

  /**
   * Verifies single-node anchor travel resolves to access-distance sum.
   */
  public function testResolveRoomToRoomDistanceSingleNodeAnchor(): void {
    $service = new NavigationRoadGraphService();
    $distance = $service->resolveRoomToRoomDistance([
      'room_road_anchors' => [
        ['room_id' => 'hall', 'road_node_id' => 'road-1', 'access_distance' => 2],
        ['room_id' => 'market', 'road_node_id' => 'road-1', 'access_distance' => 3],
      ],
      'road_edges' => [],
    ], 'hall', 'market');

    $this->assertSame(5, $distance);
  }

  /**
   * Verifies multi-leg path chooses shortest weighted road route.
   */
  public function testResolveRoomToRoomDistanceUsesShortestPath(): void {
    $service = new NavigationRoadGraphService();
    $distance = $service->resolveRoomToRoomDistance([
      'room_road_anchors' => [
        ['room_id' => 'hall', 'road_node_id' => 'road-a', 'access_distance' => 1],
        ['room_id' => 'millhouse', 'road_node_id' => 'road-d', 'access_distance' => 1],
      ],
      'road_edges' => [
        ['from_node_id' => 'road-a', 'to_node_id' => 'road-b', 'distance' => 4, 'bidirectional' => TRUE],
        ['from_node_id' => 'road-b', 'to_node_id' => 'road-d', 'distance' => 3, 'bidirectional' => TRUE],
        ['from_node_id' => 'road-a', 'to_node_id' => 'road-c', 'distance' => 1, 'bidirectional' => TRUE],
        ['from_node_id' => 'road-c', 'to_node_id' => 'road-d', 'distance' => 2, 'bidirectional' => TRUE],
      ],
    ], 'hall', 'millhouse');

    // 1 (from access) + 3 (shortest road a->c->d) + 1 (to access) = 5.
    $this->assertSame(5, $distance);
  }

  /**
   * Verifies unresolved graph segments return NULL instead of fallback distance.
   */
  public function testResolveRoomToRoomDistanceReturnsNullWhenNoPath(): void {
    $service = new NavigationRoadGraphService();
    $distance = $service->resolveRoomToRoomDistance([
      'room_road_anchors' => [
        ['room_id' => 'hall', 'road_node_id' => 'road-a', 'access_distance' => 1],
        ['room_id' => 'market', 'road_node_id' => 'road-z', 'access_distance' => 1],
      ],
      'road_edges' => [
        ['from_node_id' => 'road-a', 'to_node_id' => 'road-b', 'distance' => 2, 'bidirectional' => TRUE],
      ],
    ], 'hall', 'market');

    $this->assertNull($distance);
  }

}
