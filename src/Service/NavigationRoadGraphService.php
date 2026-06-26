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

    $from_access = (int) ($from_anchor['access_distance'] ?? 0);
    $to_access = (int) ($to_anchor['access_distance'] ?? 0);
    $from_node_id = trim((string) ($from_anchor['road_node_id'] ?? ''));
    $to_node_id = trim((string) ($to_anchor['road_node_id'] ?? ''));
    if ($from_node_id === '' || $to_node_id === '') {
      return NULL;
    }

    $path_distance = $this->resolveShortestRoadPathDistance($dungeon_data, $from_node_id, $to_node_id);
    if ($path_distance === NULL) {
      return NULL;
    }

    return max(0, $from_access) + $path_distance + max(0, $to_access);
  }

  /**
   * Resolve shortest path between two road nodes using Dijkstra.
   */
  public function resolveShortestRoadPathDistance(
    array $dungeon_data,
    string $from_node_id,
    string $to_node_id
  ): ?int {
    $from_node_id = trim($from_node_id);
    $to_node_id = trim($to_node_id);
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
      if ($current_node === NULL) {
        break;
      }
      if ($current_node === $to_node_id) {
        return (int) $current_distance;
      }

      $visited[$current_node] = TRUE;
      foreach ($adjacency[$current_node] ?? [] as $edge) {
        $neighbor = (string) ($edge['to'] ?? '');
        $weight = (int) ($edge['distance'] ?? 0);
        if ($neighbor === '' || isset($visited[$neighbor])) {
          continue;
        }
        $candidate = (int) $current_distance + max(0, $weight);
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
      $from = trim((string) ($edge['from_node_id'] ?? $edge['from'] ?? ''));
      $to = trim((string) ($edge['to_node_id'] ?? $edge['to'] ?? ''));
      if ($from === '' || $to === '') {
        continue;
      }
      $distance = max(0, (int) ($edge['distance'] ?? $edge['weight'] ?? 0));
      $bidirectional = array_key_exists('bidirectional', $edge) ? !empty($edge['bidirectional']) : TRUE;

      $adjacency[$from][] = ['to' => $to, 'distance' => $distance];
      $adjacency[$to] = $adjacency[$to] ?? [];
      if ($bidirectional) {
        $adjacency[$to][] = ['to' => $from, 'distance' => $distance];
      }
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
        'road_node_id' => trim((string) ($anchor['road_node_id'] ?? '')),
        'access_distance' => max(0, (int) ($anchor['access_distance'] ?? 0)),
      ];
    }

    return NULL;
  }

}
