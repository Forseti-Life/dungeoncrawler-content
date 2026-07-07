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
    if (($layout_profile['layout_algorithm'] ?? '') === DungeonLayoutProfileResolver::CITY_PLACEMENT_ALGORITHM_VERSION) {
      return $this->connectCityRoomsInLevel($rooms, $chance, $axial_distance_steps);
    }

    $connections = [];
    for ($i = 0; $i < count($rooms) - 1; $i++) {
      $from_room = $rooms[$i];
      $to_room = $rooms[$i + 1];
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
    if (count($rooms) < 2) {
      return [];
    }
    $room_records = [];
    foreach ($rooms as $index => $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \RuntimeException('City room connection strategy requires room_id on every room.');
      }
      $anchor = $this->resolveRoomAnchorCoordinate($room, $room_id);
      $wave_index = isset($room['placement']['placement_wave_index']) && is_numeric($room['placement']['placement_wave_index'])
        ? (int) $room['placement']['placement_wave_index']
        : 0;
      $priority = isset($room['placement']['anchor_priority']) && is_numeric($room['placement']['anchor_priority'])
        ? (int) $room['placement']['anchor_priority']
        : ($index + 1);
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
      if ($room_id === '') {
        continue;
      }
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
          continue;
        }
        $best_parent_id = '';
        $best_parent_distance = NULL;
        foreach ($parent_wave_room_ids as $candidate_parent_id) {
          $parent = $by_room_id[$candidate_parent_id] ?? NULL;
          if (!is_array($parent)) {
            continue;
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
    if (
      is_array($room['placement'] ?? NULL)
      && is_numeric($room['placement']['anchor_q'] ?? NULL)
      && is_numeric($room['placement']['anchor_r'] ?? NULL)
    ) {
      return [
        'q' => (int) $room['placement']['anchor_q'],
        'r' => (int) $room['placement']['anchor_r'],
      ];
    }
    if (is_array($room['entry_points'] ?? NULL) && is_array($room['entry_points'][0] ?? NULL)) {
      $entry = $room['entry_points'][0];
      if (is_numeric($entry['q'] ?? NULL) && is_numeric($entry['r'] ?? NULL)) {
        return [
          'q' => (int) $entry['q'],
          'r' => (int) $entry['r'],
        ];
      }
    }
    if (is_array($room['hexes'] ?? NULL) && is_array($room['hexes'][0] ?? NULL)) {
      $hex = $room['hexes'][0];
      if (is_numeric($hex['q'] ?? NULL) && is_numeric($hex['r'] ?? NULL)) {
        return [
          'q' => (int) $hex['q'],
          'r' => (int) $hex['r'],
        ];
      }
    }
    throw new \RuntimeException(sprintf('Unable to resolve anchor coordinate for room %s.', $room_id));
  }

}
