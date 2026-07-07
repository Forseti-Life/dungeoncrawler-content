<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Plans room-to-room connection topology per layout algorithm.
 */
class DungeonRoomConnectionPlanner {

  /**
   * Build connections for one level based on resolved layout profile.
   *
   * @param array $rooms
   *   Generated rooms.
   * @param array{dungeon_type:string,layout_algorithm:string} $layout_profile
   *   Layout profile for this generation.
   * @param callable $chance
   *   Callback: fn(int $percentage): bool
   * @param callable $axial_distance_steps
   *   Callback: fn(int $q1,int $r1,int $q2,int $r2): int
   *
   * @return array<int, array<string, mixed>>
   *   Connection payloads.
   */
  public function connectRoomsInLevel(
    array $rooms,
    array $layout_profile,
    callable $chance,
    callable $axial_distance_steps,
  ): array {
    $layout_profile = $this->normalizeLayoutProfile($layout_profile);
    if (count($rooms) < 2) {
      return [];
    }

    if ($layout_profile['layout_algorithm'] === DungeonLayoutProfileResolver::CITY_PLACEMENT_ALGORITHM_VERSION) {
      return $this->connectCityRoomsInLevel($rooms, $chance, $axial_distance_steps);
    }

    $normalized_rooms = $this->normalizeRooms($rooms, 'Linear');
    $connections = [];
    for ($i = 0; $i < count($normalized_rooms) - 1; $i++) {
      $from_room = $normalized_rooms[$i];
      $to_room = $normalized_rooms[$i + 1];
      $connections[] = [
        'from_room_id' => $from_room['room_id'],
        'to_room_id' => $to_room['room_id'],
        'connection_type' => 'door',
        'edge_kind' => 'street_path',
        'edge_direction' => 'bidirectional',
        'traversal_cost' => 1,
        'blocked' => FALSE,
        'is_locked' => $chance(15),
        'is_trapped' => $chance(10),
        'is_hidden' => FALSE,
      ];
    }
    return $connections;
  }

  /**
   * Connect rooms using concentric-wave parent chaining for city layouts.
   */
  protected function connectCityRoomsInLevel(
    array $rooms,
    callable $chance,
    callable $axial_distance_steps,
  ): array {
    $normalized_rooms = $this->normalizeRooms($rooms, 'City');
    if (count($normalized_rooms) < 2) {
      return [];
    }
    $room_records = [];
    foreach ($normalized_rooms as $index => $room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      $placement = is_array($room['placement'] ?? NULL) ? $room['placement'] : NULL;
      if (!is_array($placement)) {
        throw new \RuntimeException(sprintf('City room connection strategy requires placement metadata for room %s.', $room_id));
      }
      $anchor = $this->resolveRoomAnchorCoordinate($room, $room_id);
      if (!is_numeric($placement['placement_wave_index'] ?? NULL)) {
        throw new \RuntimeException(sprintf('City room connection strategy requires numeric placement_wave_index for room %s.', $room_id));
      }
      if (!is_numeric($placement['anchor_priority'] ?? NULL)) {
        throw new \RuntimeException(sprintf('City room connection strategy requires numeric anchor_priority for room %s.', $room_id));
      }
      $wave_index = (int) $placement['placement_wave_index'];
      $priority = (int) $placement['anchor_priority'];
      $room_records[] = [
        'room_id' => $room_id,
        'wave_index' => max(0, $wave_index),
        'anchor_priority' => max(1, $priority),
        'anchor_q' => $anchor['q'],
        'anchor_r' => $anchor['r'],
      ];
    }
    if (count($room_records) < 2) {
      return [];
    }

    usort($room_records, static function (array $left, array $right): int {
      $wave_cmp = ((int) ($left['wave_index'] ?? 0)) <=> ((int) ($right['wave_index'] ?? 0));
      if ($wave_cmp !== 0) {
        return $wave_cmp;
      }
      $priority_cmp = ((int) ($left['anchor_priority'] ?? 0)) <=> ((int) ($right['anchor_priority'] ?? 0));
      if ($priority_cmp !== 0) {
        return $priority_cmp;
      }
      return strcmp((string) ($left['room_id'] ?? ''), (string) ($right['room_id'] ?? ''));
    });

    $root = $room_records[0];
    $by_room_id = [];
    $wave_to_room_ids = [];
    foreach ($room_records as $record) {
      $room_id = (string) ($record['room_id'] ?? '');
      $by_room_id[$room_id] = $record;
      $wave = (int) ($record['wave_index'] ?? 0);
      if (!isset($wave_to_room_ids[$wave])) {
        $wave_to_room_ids[$wave] = [];
      }
      $wave_to_room_ids[$wave][] = $room_id;
    }

    ksort($wave_to_room_ids, SORT_NUMERIC);
    $connections = [];
    $added_edges = [];
    $connected_by_wave = [
      0 => [(string) ($root['room_id'] ?? '')],
    ];
    $known_waves = array_keys($wave_to_room_ids);
    sort($known_waves, SORT_NUMERIC);

    foreach ($known_waves as $wave) {
      if ($wave === 0) {
        continue;
      }
      $current_wave_room_ids = $wave_to_room_ids[$wave] ?? [];
      if ($current_wave_room_ids === []) {
        continue;
      }
      $parent_wave_room_ids = $connected_by_wave[$wave - 1] ?? [];
      if ($parent_wave_room_ids === []) {
        $parent_wave_room_ids = $connected_by_wave[0] ?? [(string) ($root['room_id'] ?? '')];
      }

      foreach ($current_wave_room_ids as $room_id) {
        $child = $by_room_id[$room_id] ?? NULL;
        if (!is_array($child)) {
          throw new \RuntimeException(sprintf('City room connection strategy could not resolve child room metadata for %s.', $room_id));
        }
        $best_parent_id = '';
        $best_parent_distance = NULL;
        foreach ($parent_wave_room_ids as $candidate_parent_id) {
          $parent = $by_room_id[$candidate_parent_id] ?? NULL;
          if (!is_array($parent)) {
            throw new \RuntimeException(sprintf('City room connection strategy parent metadata is missing for room %s.', $candidate_parent_id));
          }
          $distance = $axial_distance_steps(
            (int) $child['anchor_q'],
            (int) $child['anchor_r'],
            (int) $parent['anchor_q'],
            (int) $parent['anchor_r']
          );
          if ($best_parent_distance === NULL || $distance < $best_parent_distance) {
            $best_parent_distance = $distance;
            $best_parent_id = (string) $parent['room_id'];
          }
        }
        if ($best_parent_id === '') {
          throw new \RuntimeException(sprintf('City room connection strategy failed to resolve parent room for %s in wave %d.', $room_id, $wave));
        }
        $edge_key = $best_parent_id . '|' . $room_id;
        if (isset($added_edges[$edge_key])) {
          continue;
        }
        $added_edges[$edge_key] = TRUE;
        $connections[] = [
          'from_room_id' => $best_parent_id,
          'to_room_id' => $room_id,
          'connection_type' => 'street',
          'edge_kind' => 'street_path',
          'edge_direction' => 'bidirectional',
          'traversal_cost' => max(1, (int) ($best_parent_distance ?? 1)),
          'blocked' => FALSE,
          'is_locked' => $chance(5),
          'is_trapped' => $chance(3),
          'is_hidden' => FALSE,
        ];
      }
      $connected_by_wave[$wave] = $current_wave_room_ids;
    }

    return $connections;
  }

  /**
   * Resolve one room anchor coordinate for layout/connection topology.
   *
   * @return array{q:int,r:int}
   *   Anchor coordinate.
   */
  protected function resolveRoomAnchorCoordinate(array $room, string $room_id): array {
    $placement = is_array($room['placement'] ?? NULL) ? $room['placement'] : NULL;
    if (!is_array($placement)) {
      throw new \RuntimeException(sprintf('City room connection strategy requires placement metadata for room %s.', $room_id));
    }
    if (!is_numeric($placement['anchor_q'] ?? NULL) || !is_numeric($placement['anchor_r'] ?? NULL)) {
      throw new \RuntimeException(sprintf('City room connection strategy requires numeric placement anchor_q/anchor_r for room %s.', $room_id));
    }
    return [
      'q' => (int) $placement['anchor_q'],
      'r' => (int) $placement['anchor_r'],
    ];
  }

  /**
   * Normalize and validate room list contract for connection planning.
   *
   * @param array $rooms
   *   Room payloads.
   * @param string $strategy_name
   *   Human-readable strategy label for errors.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized room payloads.
   */
  protected function normalizeRooms(array $rooms, string $strategy_name): array {
    $normalized = [];
    foreach ($rooms as $index => $room) {
      if (!is_array($room)) {
        throw new \RuntimeException(sprintf('%s room connection strategy requires room payload object at index %d.', $strategy_name, $index));
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \RuntimeException(sprintf('%s room connection strategy requires room_id on every room (index %d).', $strategy_name, $index));
      }
      $room['room_id'] = $room_id;
      $normalized[] = $room;
    }
    return $normalized;
  }

  /**
   * Validate layout-profile contract.
   *
   * @return array{dungeon_type:string,layout_algorithm:string}
   *   Canonical validated profile.
   */
  protected function normalizeLayoutProfile(array $layout_profile): array {
    $dungeon_type = strtolower(trim((string) ($layout_profile['dungeon_type'] ?? '')));
    $layout_algorithm = trim((string) ($layout_profile['layout_algorithm'] ?? ''));
    if ($dungeon_type === '' || $layout_algorithm === '') {
      throw new \RuntimeException('Room connection strategy requires non-empty dungeon_type and layout_algorithm.');
    }
    if (!in_array($dungeon_type, DungeonLayoutProfileResolver::SUPPORTED_DUNGEON_TYPES, TRUE)) {
      throw new \RuntimeException(sprintf(
        "Room connection strategy received unsupported dungeon_type '%s'.",
        $dungeon_type
      ));
    }
    $expected_algorithm = DungeonLayoutProfileResolver::DUNGEON_LAYOUT_ALGORITHM_BY_TYPE[$dungeon_type] ?? '';
    if ($expected_algorithm === '') {
      throw new \RuntimeException(sprintf(
        "Room connection strategy has no layout mapping for dungeon_type '%s'.",
        $dungeon_type
      ));
    }
    if ($layout_algorithm !== $expected_algorithm) {
      throw new \RuntimeException(sprintf(
        "Room connection strategy contract violation: layout_algorithm '%s' is invalid for dungeon_type '%s' (required: %s).",
        $layout_algorithm,
        $dungeon_type,
        $expected_algorithm
      ));
    }
    return [
      'dungeon_type' => $dungeon_type,
      'layout_algorithm' => $layout_algorithm,
    ];
  }

}
