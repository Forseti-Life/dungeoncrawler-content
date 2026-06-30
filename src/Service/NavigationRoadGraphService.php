<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves deterministic road-graph travel distances.
 */
class NavigationRoadGraphService {

  /**
   * Resolve room-to-room travel distance through road anchors + road graph.
   *
   * Returns NULL when required anchors/graph segments are missing.
   */
  public function resolveRoomToRoomDistance(
    array $dungeon_data,
    string $from_room_id,
    string $to_room_id
  ): ?int {
    $from_anchor = $this->findRoomAnchor($dungeon_data, $from_room_id);
    $to_anchor = $this->findRoomAnchor($dungeon_data, $to_room_id);
    if ($from_anchor === NULL || $to_anchor === NULL) {
      return NULL;
    }

    $from_access = $this->normalizeNonNegativeDistance($from_anchor['access_distance'] ?? 0);
    $to_access = $this->normalizeNonNegativeDistance($to_anchor['access_distance'] ?? 0);
    $from_node_id = $this->normalizeRoadNodeId($from_anchor['road_node_id'] ?? '');
    $to_node_id = $this->normalizeRoadNodeId($to_anchor['road_node_id'] ?? '');
    if ($from_node_id === '' || $to_node_id === '') {
      return NULL;
    }

    $path_distance = $this->resolveShortestRoadPathDistance($dungeon_data, $from_node_id, $to_node_id);
    if ($path_distance === NULL) {
      return NULL;
    }

    return $from_access + $path_distance + $to_access;
  }

  /**
   * Resolve shortest path between two road nodes using Dijkstra.
   */
  public function resolveShortestRoadPathDistance(
    array $dungeon_data,
    string $from_node_id,
    string $to_node_id
  ): ?int {
    $from_node_id = $this->normalizeRoadNodeId($from_node_id);
    $to_node_id = $this->normalizeRoadNodeId($to_node_id);
    if ($from_node_id === '' || $to_node_id === '') {
      return NULL;
    }
    if ($from_node_id === $to_node_id) {
      return 0;
    }

    $adjacency = $this->buildRoadAdjacencyList($dungeon_data);
    if (empty($adjacency[$from_node_id]) || !isset($adjacency[$to_node_id])) {
      return NULL;
    }

    $distances = [$from_node_id => 0];
    $visited = [];

    while (TRUE) {
      [$current_node, $current_distance] = $this->resolveClosestUnvisitedNode($distances, $visited);
      if ($current_node === NULL) {
        break;
      }
      if ($current_node === $to_node_id) {
        return (int) $current_distance;
      }

      $visited[$current_node] = TRUE;
      foreach ($adjacency[$current_node] ?? [] as $edge) {
        $neighbor = $this->normalizeRoadNodeId($edge['to'] ?? '');
        $weight = $this->normalizeNonNegativeDistance($edge['distance'] ?? 0);
        if ($neighbor === '' || isset($visited[$neighbor])) {
          continue;
        }
        $candidate = (int) $current_distance + $weight;
        if (!isset($distances[$neighbor]) || $candidate < $distances[$neighbor]) {
          $distances[$neighbor] = $candidate;
        }
      }
    }

    return NULL;
  }

  /**
   * Build canonical adjacency list from road edge payload.
   *
   * Supported payload keys:
   * - road_graph.edges[]
   * - road_edges[]
   */
  public function buildRoadAdjacencyList(array $dungeon_data): array {
    $edges = $dungeon_data['road_graph']['edges'] ?? ($dungeon_data['road_edges'] ?? []);
    if (!is_array($edges)) {
      return [];
    }

    $adjacency = [];
    foreach ($edges as $edge) {
      if (!is_array($edge)) {
        continue;
      }
      $from = $this->normalizeRoadNodeId($edge['from_node_id'] ?? $edge['from'] ?? '');
      $to = $this->normalizeRoadNodeId($edge['to_node_id'] ?? $edge['to'] ?? '');
      if ($from === '' || $to === '') {
        continue;
      }
      $distance = $this->normalizeNonNegativeDistance($edge['distance'] ?? $edge['weight'] ?? 0);
      $bidirectional = array_key_exists('bidirectional', $edge) ? !empty($edge['bidirectional']) : TRUE;
      $this->appendAdjacencyEdge($adjacency, $from, $to, $distance, $bidirectional);
    }

    return $adjacency;
  }

  /**
   * Find one room-road anchor.
   */
  public function findRoomAnchor(array $dungeon_data, string $room_id): ?array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return NULL;
    }

    $anchors = $dungeon_data['room_road_anchors'] ?? ($dungeon_data['road_anchors'] ?? []);
    if (!is_array($anchors)) {
      return NULL;
    }

    foreach ($anchors as $anchor) {
      if (!is_array($anchor)) {
        continue;
      }
      if ((string) ($anchor['room_id'] ?? '') !== $room_id) {
        continue;
      }
      return [
        'room_id' => $room_id,
        'road_node_id' => $this->normalizeRoadNodeId($anchor['road_node_id'] ?? ''),
        'access_distance' => $this->normalizeNonNegativeDistance($anchor['access_distance'] ?? 0),
      ];
    }

    return NULL;
  }

  /**
   * Normalize a road node identifier into canonical string form.
   */
  protected function normalizeRoadNodeId(mixed $node_id): string {
    return trim((string) $node_id);
  }

  /**
   * Normalize numeric distance fields to non-negative integers.
   */
  protected function normalizeNonNegativeDistance(mixed $distance): int {
    return max(0, (int) $distance);
  }

  /**
   * Append one canonical edge (and optional reverse edge) to adjacency payload.
   *
   * @param array<string, array<int, array<string, int|string>>> $adjacency
   *   Adjacency list keyed by node id.
   * @param string $from
   *   Source node id.
   * @param string $to
   *   Target node id.
   * @param int $distance
   *   Non-negative edge distance.
   * @param bool $bidirectional
   *   Whether reverse edge should be emitted.
   */
  protected function appendAdjacencyEdge(
    array &$adjacency,
    string $from,
    string $to,
    int $distance,
    bool $bidirectional
  ): void {
    $adjacency[$from][] = ['to' => $to, 'distance' => $distance];
    $adjacency[$to] = $adjacency[$to] ?? [];
    if ($bidirectional) {
      $adjacency[$to][] = ['to' => $from, 'distance' => $distance];
    }
  }

  /**
   * Resolve the next unvisited node with the smallest tentative distance.
   *
   * @param array<string, int> $distances
   *   Tentative distances keyed by node id.
   * @param array<string, bool> $visited
   *   Visited marker map keyed by node id.
   *
   * @return array{0: string|null, 1: int|null}
   *   Tuple of [node id, tentative distance].
   */
  protected function resolveClosestUnvisitedNode(array $distances, array $visited): array {
    $current_node = NULL;
    $current_distance = NULL;
    foreach ($distances as $node_id => $distance) {
      if (isset($visited[$node_id])) {
        continue;
      }
      if ($current_node === NULL || $distance < $current_distance) {
        $current_node = $node_id;
        $current_distance = $distance;
      }
    }

    return [$current_node, $current_distance];
  }

}
