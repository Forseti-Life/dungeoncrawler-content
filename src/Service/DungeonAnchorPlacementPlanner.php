<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Plans room anchors and spacing for dungeon layouts.
 */
class DungeonAnchorPlacementPlanner {

  public const MIN_ANCHOR_DISTANCE_RES14_HEXES = 200;

  /**
   * Apply spacing strategy for the active layout profile.
   *
   * @param array $rooms
   *   Room payloads generated for one level (modified in place).
   * @param int $minimum_gap_hexes
   *   Minimum required inter-room gap measured in empty hexes.
   * @param array $context
   *   Generation context.
   * @param array{dungeon_type:string,layout_algorithm:string} $layout_profile
   *   Layout profile resolved from context.
   * @param callable $calculate_room_bounds
   *   Callback: fn(array $room): array{min_q:int,max_q:int,min_r:int,max_r:int}
   * @param callable $offset_room_coordinates
   *   Callback: fn(array $room,int $offset_q,int $offset_r): array
   * @param callable $axial_distance_steps
   *   Callback: fn(int $q1,int $r1,int $q2,int $r2): int
   *
   * @return array<int, array<string, mixed>>
   *   Room anchor metadata for hex_map export.
   */
  public function applyMinimumRoomSpacing(
    array &$rooms,
    int $minimum_gap_hexes,
    array $context,
    array $layout_profile,
    callable $calculate_room_bounds,
    callable $offset_room_coordinates,
    callable $axial_distance_steps,
  ): array {
    if (($layout_profile['layout_algorithm'] ?? '') === DungeonLayoutProfileResolver::CITY_PLACEMENT_ALGORITHM_VERSION) {
      return $this->applyCityCenteredRoomSpacing(
        $rooms,
        $minimum_gap_hexes,
        $context,
        $layout_profile,
        $calculate_room_bounds,
        $offset_room_coordinates,
        $axial_distance_steps
      );
    }
    return $this->applyLinearRoomSpacing(
      $rooms,
      $minimum_gap_hexes,
      $context,
      $layout_profile,
      $calculate_room_bounds,
      $offset_room_coordinates,
      $axial_distance_steps
    );
  }

  /**
   * Default deterministic linear placement strategy.
   */
  protected function applyLinearRoomSpacing(
    array &$rooms,
    int $minimum_gap_hexes,
    array $context,
    array $layout_profile,
    callable $calculate_room_bounds,
    callable $offset_room_coordinates,
    callable $axial_distance_steps,
  ): array {
    if ($rooms === []) {
      return [];
    }

    $anchors = [];
    $cursor_q = 0;
    $cursor_r = 0;

    $placement_seed = isset($context['seed']) ? (int) $context['seed'] : 0;
    $placed_anchor_points = [];
    $layout_algorithm = (string) ($layout_profile['layout_algorithm'] ?? DungeonLayoutProfileResolver::PLACEMENT_ALGORITHM_VERSION);
    $dungeon_type = (string) ($layout_profile['dungeon_type'] ?? DungeonLayoutProfileResolver::DUNGEON_TYPE_GENERIC);

    foreach ($rooms as $index => &$room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \RuntimeException('Dungeon level generation produced a room without room_id.');
      }

      $bounds = $calculate_room_bounds($room);
      $target_min_q = $cursor_q;
      $target_min_r = $cursor_r;
      $entry_point = (is_array($room['entry_points'] ?? NULL) && is_array($room['entry_points'][0] ?? NULL))
        ? $room['entry_points'][0]
        : NULL;
      $offset_q = 0;
      $offset_r = 0;
      $anchor_q = 0;
      $anchor_r = 0;
      $shifted_bounds = [];
      $anchor_guard = 0;
      while (TRUE) {
        $anchor_guard++;
        if ($anchor_guard > 8192) {
          throw new \RuntimeException(sprintf('Failed to place room %s while enforcing minimum anchor spacing.', $room_id));
        }
        $offset_q = $target_min_q - $bounds['min_q'];
        $offset_r = $target_min_r - $bounds['min_r'];
        $shifted_bounds = [
          'min_q' => $bounds['min_q'] + $offset_q,
          'max_q' => $bounds['max_q'] + $offset_q,
          'min_r' => $bounds['min_r'] + $offset_r,
          'max_r' => $bounds['max_r'] + $offset_r,
        ];
        $anchor_q = is_array($entry_point) && is_numeric($entry_point['q'] ?? NULL)
          ? (int) $entry_point['q'] + $offset_q
          : $shifted_bounds['min_q'];
        $anchor_r = is_array($entry_point) && is_numeric($entry_point['r'] ?? NULL)
          ? (int) $entry_point['r'] + $offset_r
          : $shifted_bounds['min_r'];

        $nearest_anchor_distance = NULL;
        foreach ($placed_anchor_points as $placed_anchor) {
          if (!is_array($placed_anchor)) {
            continue;
          }
          $distance = $axial_distance_steps(
            $anchor_q,
            $anchor_r,
            (int) ($placed_anchor['q'] ?? 0),
            (int) ($placed_anchor['r'] ?? 0)
          );
          $nearest_anchor_distance = $nearest_anchor_distance === NULL
            ? $distance
            : min($nearest_anchor_distance, $distance);
        }
        if ($nearest_anchor_distance === NULL || $nearest_anchor_distance >= self::MIN_ANCHOR_DISTANCE_RES14_HEXES) {
          break;
        }

        $target_min_q += max(1, self::MIN_ANCHOR_DISTANCE_RES14_HEXES - $nearest_anchor_distance);
      }

      if ($offset_q !== 0 || $offset_r !== 0) {
        $room = $offset_room_coordinates($room, $offset_q, $offset_r);
      }

      $wave_index = intdiv((int) $index, 6);

      $placement_attempt_id = substr(sha1(implode('|', [
        (string) $placement_seed,
        (string) ($context['campaign_id'] ?? 0),
        (string) ($context['depth'] ?? 0),
        $room_id,
        (string) $wave_index,
      ])), 0, 20);

      $room['placement'] = [
        'anchor_q' => $anchor_q,
        'anchor_r' => $anchor_r,
        'offset_q' => $offset_q,
        'offset_r' => $offset_r,
        'minimum_gap_hexes' => $minimum_gap_hexes,
        'anchor_type' => $index === 0 ? 'fixed' : 'derived',
        'anchor_priority' => $index + 1,
        'placement_wave_index' => $wave_index,
        'placement_seed' => $placement_seed,
        'algorithm_version' => $layout_algorithm,
        'dungeon_type' => $dungeon_type,
        'layout_algorithm' => $layout_algorithm,
        'placement_attempt_id' => $placement_attempt_id,
        'buffer_ring_size' => $minimum_gap_hexes,
        'minimum_anchor_distance_hexes' => self::MIN_ANCHOR_DISTANCE_RES14_HEXES,
        'frontage_required' => TRUE,
        'ingress_hex_ids' => [$anchor_q . ':' . $anchor_r],
      ];

      $anchors[] = [
        'room_id' => $room_id,
        'anchor_q' => $anchor_q,
        'anchor_r' => $anchor_r,
        'min_q' => $shifted_bounds['min_q'],
        'max_q' => $shifted_bounds['max_q'],
        'min_r' => $shifted_bounds['min_r'],
        'max_r' => $shifted_bounds['max_r'],
        'anchor_type' => $index === 0 ? 'fixed' : 'derived',
        'anchor_priority' => $index + 1,
        'placement_wave_index' => $wave_index,
        'placement_seed' => $placement_seed,
        'algorithm_version' => $layout_algorithm,
        'dungeon_type' => $dungeon_type,
        'layout_algorithm' => $layout_algorithm,
        'buffer_ring_size' => $minimum_gap_hexes,
        'minimum_anchor_distance_hexes' => self::MIN_ANCHOR_DISTANCE_RES14_HEXES,
        'frontage_required' => TRUE,
        'ingress_hex_ids' => [$anchor_q . ':' . $anchor_r],
      ];
      $placed_anchor_points[] = [
        'room_id' => $room_id,
        'q' => $anchor_q,
        'r' => $anchor_r,
      ];
      $cursor_q = (int) $shifted_bounds['max_q'] + $minimum_gap_hexes + 1;
    }
    unset($room);

    return $anchors;
  }

  /**
   * City layout strategy: centered clustered anchors in deterministic hex rings.
   */
  protected function applyCityCenteredRoomSpacing(
    array &$rooms,
    int $minimum_gap_hexes,
    array $context,
    array $layout_profile,
    callable $calculate_room_bounds,
    callable $offset_room_coordinates,
    callable $axial_distance_steps,
  ): array {
    if ($rooms === []) {
      return [];
    }

    $anchors = [];
    $placement_seed = isset($context['seed']) ? (int) $context['seed'] : 0;
    $placed_anchor_points = [];
    $layout_algorithm = (string) ($layout_profile['layout_algorithm'] ?? DungeonLayoutProfileResolver::CITY_PLACEMENT_ALGORITHM_VERSION);
    $dungeon_type = (string) ($layout_profile['dungeon_type'] ?? DungeonLayoutProfileResolver::DUNGEON_TYPE_CITY);
    $city_anchor_targets = $this->buildCityClusterAnchorTargets(count($rooms), self::MIN_ANCHOR_DISTANCE_RES14_HEXES);

    foreach ($rooms as $index => &$room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \RuntimeException('Dungeon level generation produced a room without room_id.');
      }
      $anchor_target = $city_anchor_targets[$index] ?? NULL;
      if (!is_array($anchor_target)) {
        throw new \RuntimeException(sprintf('City placement target missing for room %s at index %d.', $room_id, $index));
      }

      $bounds = $calculate_room_bounds($room);
      $entry_point = (is_array($room['entry_points'] ?? NULL) && is_array($room['entry_points'][0] ?? NULL))
        ? $room['entry_points'][0]
        : NULL;
      $entry_local_q = is_array($entry_point) && is_numeric($entry_point['q'] ?? NULL)
        ? (int) $entry_point['q']
        : $bounds['min_q'];
      $entry_local_r = is_array($entry_point) && is_numeric($entry_point['r'] ?? NULL)
        ? (int) $entry_point['r']
        : $bounds['min_r'];

      $anchor_q = (int) ($anchor_target['q'] ?? 0);
      $anchor_r = (int) ($anchor_target['r'] ?? 0);
      $offset_q = $anchor_q - $entry_local_q;
      $offset_r = $anchor_r - $entry_local_r;

      if ($offset_q !== 0 || $offset_r !== 0) {
        $room = $offset_room_coordinates($room, $offset_q, $offset_r);
      }

      $shifted_bounds = [
        'min_q' => $bounds['min_q'] + $offset_q,
        'max_q' => $bounds['max_q'] + $offset_q,
        'min_r' => $bounds['min_r'] + $offset_r,
        'max_r' => $bounds['max_r'] + $offset_r,
      ];

      $nearest_anchor_distance = NULL;
      foreach ($placed_anchor_points as $placed_anchor) {
        if (!is_array($placed_anchor)) {
          continue;
        }
        $distance = $axial_distance_steps(
          $anchor_q,
          $anchor_r,
          (int) ($placed_anchor['q'] ?? 0),
          (int) ($placed_anchor['r'] ?? 0)
        );
        $nearest_anchor_distance = $nearest_anchor_distance === NULL
          ? $distance
          : min($nearest_anchor_distance, $distance);
      }
      if ($nearest_anchor_distance !== NULL && $nearest_anchor_distance < self::MIN_ANCHOR_DISTANCE_RES14_HEXES) {
        throw new \RuntimeException(sprintf(
          'City placement contract violation for room %s: nearest anchor distance %d is below required %d.',
          $room_id,
          $nearest_anchor_distance,
          self::MIN_ANCHOR_DISTANCE_RES14_HEXES
        ));
      }

      $wave_index = (int) round(
        $axial_distance_steps(0, 0, $anchor_q, $anchor_r) / max(1, self::MIN_ANCHOR_DISTANCE_RES14_HEXES)
      );
      $placement_attempt_id = substr(sha1(implode('|', [
        (string) $placement_seed,
        (string) ($context['campaign_id'] ?? 0),
        (string) ($context['depth'] ?? 0),
        $room_id,
        (string) $wave_index,
      ])), 0, 20);

      $room['placement'] = [
        'anchor_q' => $anchor_q,
        'anchor_r' => $anchor_r,
        'offset_q' => $offset_q,
        'offset_r' => $offset_r,
        'minimum_gap_hexes' => $minimum_gap_hexes,
        'anchor_type' => $index === 0 ? 'fixed' : 'derived',
        'anchor_priority' => $index + 1,
        'placement_wave_index' => $wave_index,
        'placement_seed' => $placement_seed,
        'algorithm_version' => $layout_algorithm,
        'dungeon_type' => $dungeon_type,
        'layout_algorithm' => $layout_algorithm,
        'placement_attempt_id' => $placement_attempt_id,
        'buffer_ring_size' => $minimum_gap_hexes,
        'minimum_anchor_distance_hexes' => self::MIN_ANCHOR_DISTANCE_RES14_HEXES,
        'frontage_required' => TRUE,
        'ingress_hex_ids' => [$anchor_q . ':' . $anchor_r],
      ];

      $anchors[] = [
        'room_id' => $room_id,
        'anchor_q' => $anchor_q,
        'anchor_r' => $anchor_r,
        'min_q' => $shifted_bounds['min_q'],
        'max_q' => $shifted_bounds['max_q'],
        'min_r' => $shifted_bounds['min_r'],
        'max_r' => $shifted_bounds['max_r'],
        'anchor_type' => $index === 0 ? 'fixed' : 'derived',
        'anchor_priority' => $index + 1,
        'placement_wave_index' => $wave_index,
        'placement_seed' => $placement_seed,
        'algorithm_version' => $layout_algorithm,
        'dungeon_type' => $dungeon_type,
        'layout_algorithm' => $layout_algorithm,
        'buffer_ring_size' => $minimum_gap_hexes,
        'minimum_anchor_distance_hexes' => self::MIN_ANCHOR_DISTANCE_RES14_HEXES,
        'frontage_required' => TRUE,
        'ingress_hex_ids' => [$anchor_q . ':' . $anchor_r],
      ];
      $placed_anchor_points[] = [
        'room_id' => $room_id,
        'q' => $anchor_q,
        'r' => $anchor_r,
      ];
    }
    unset($room);

    return $anchors;
  }

  /**
   * Build city-cluster anchor targets ordered by concentric hex rings.
   */
  protected function buildCityClusterAnchorTargets(int $room_count, int $anchor_step): array {
    if ($room_count < 1) {
      return [];
    }
    if ($anchor_step < 1) {
      throw new \InvalidArgumentException('anchor_step must be >= 1 for city cluster anchor generation.');
    }
    $targets = [
      ['q' => 0, 'r' => 0],
    ];
    $radius = 1;
    while (count($targets) < $room_count) {
      if ($radius > 2048) {
        throw new \RuntimeException('City cluster anchor generation exceeded ring radius guardrail.');
      }
      $ring_coordinates = $this->buildHexRingUnitCoordinates($radius);
      foreach ($ring_coordinates as $coordinate) {
        if (!is_array($coordinate)) {
          continue;
        }
        $targets[] = [
          'q' => (int) ($coordinate['q'] ?? 0) * $anchor_step,
          'r' => (int) ($coordinate['r'] ?? 0) * $anchor_step,
        ];
        if (count($targets) >= $room_count) {
          break;
        }
      }
      $radius++;
    }
    return array_slice($targets, 0, $room_count);
  }

  /**
   * Build one hex-ring of unit axial coordinates around origin.
   */
  protected function buildHexRingUnitCoordinates(int $radius): array {
    if ($radius < 1) {
      return [['q' => 0, 'r' => 0]];
    }
    $directions = [[1, 0], [1, -1], [0, -1], [-1, 0], [-1, 1], [0, 1]];
    $coordinates = [];
    $q = -$radius;
    $r = $radius;
    foreach ($directions as $direction) {
      for ($step = 0; $step < $radius; $step++) {
        $coordinates[] = ['q' => $q, 'r' => $r];
        $q += (int) $direction[0];
        $r += (int) $direction[1];
      }
    }
    return $coordinates;
  }

}
