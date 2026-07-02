<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Utility functions for hex grid calculations.
 *
 * Implements axial coordinate system for flat-top hexagons.
 * Reference: https://www.redblobgames.com/grids/hexagons/
 *
 * Coordinate System:
 * - q = column (increases east)
 * - r = row (increases southeast)
 * - s = derived as -q - r (provides 3D symmetry)
 * - Distance = max(|Δq|, |Δr|, |Δs|)
 *
 * Flat-top hex neighbors (clockwise from east):
 *   (+1,0)   East
 *   (+1,-1)  Southeast
 *   (0,-1)   Southwest
 *   (-1,0)   West
 *   (-1,+1)  Northwest
 *   (0,+1)   Northeast
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 * @see /docs/dungeoncrawler/HEXMAP_ARCHITECTURE.md#coordinate-system
 */
class HexUtilityService {

  /**
   * Get neighbors of a hex in all 6 directions.
   *
   * Returns hexes in clockwise order starting from east.
   *
   * @param array $hex
   *   Hex coordinate ['q' => int, 'r' => int]
   *
   * @return array
   *   Array of 6 neighbor hexes:
   *   [
   *     ['q' => q+1, 'r' => r],        // East
   *     ['q' => q+1, 'r' => r-1],      // Southeast
   *     ['q' => q, 'r' => r-1],        // Southwest
   *     ['q' => q-1, 'r' => r],        // West
   *     ['q' => q-1, 'r' => r+1],      // Northwest
   *     ['q' => q, 'r' => r+1]         // Northeast
   *   ]
   */
  public function getNeighbors(array $hex): array {
    $q = $hex['q'];
    $r = $hex['r'];

    return [
      ['q' => $q + 1, 'r' => $r],        // East
      ['q' => $q + 1, 'r' => $r - 1],    // Southeast
      ['q' => $q, 'r' => $r - 1],        // Southwest
      ['q' => $q - 1, 'r' => $r],        // West
      ['q' => $q - 1, 'r' => $r + 1],    // Northwest
      ['q' => $q, 'r' => $r + 1],        // Northeast
    ];
  }

  /**
   * Get single neighbor in specific direction.
   *
   * @param array $hex
   *   Hex coordinate
   * @param int $direction
   *   0=East, 1=Southeast, 2=Southwest, 3=West, 4=Northwest, 5=Northeast
   *
   * @return array
   *   Neighbor hex coordinate
   */
  public function getNeighbor(array $hex, int $direction): array {
    $q = $hex['q'];
    $r = $hex['r'];

    $offsets = [
      [1, 0],     // 0: East
      [1, -1],    // 1: Southeast
      [0, -1],    // 2: Southwest
      [-1, 0],    // 3: West
      [-1, 1],    // 4: Northwest
      [0, 1],     // 5: Northeast
    ];

    if (!isset($offsets[$direction])) {
      throw new \InvalidArgumentException("Direction must be 0-5");
    }

    [$dq, $dr] = $offsets[$direction];
    return ['q' => $q + $dq, 'r' => $r + $dr];
  }

  /**
   * Get direction name from integer.
   *
   * @param int $direction
   *   0-5
   *
   * @return string
   *   Direction name
   */
  public function getDirectionName(int $direction): string {
    $names = [
      'east',
      'southeast',
      'southwest',
      'west',
      'northwest',
      'northeast',
    ];

    return $names[$direction] ?? 'unknown';
  }

  /**
   * Calculate distance between two hexes.
   *
   * Uses axial coordinate distance formula:
   * distance = max(|Δq|, |Δr|, |Δs|)
   * where s = -q - r
   *
   * @param array $hex1
   *   First hex ['q' => int, 'r' => int]
   * @param array $hex2
   *   Second hex ['q' => int, 'r' => int]
   *
   * @return int
   *   Distance in hex units
   */
  public function distance(array $hex1, array $hex2): int {
    $q_diff = abs($hex1['q'] - $hex2['q']);
    $r_diff = abs($hex1['r'] - $hex2['r']);
    $s1 = -$hex1['q'] - $hex1['r'];
    $s2 = -$hex2['q'] - $hex2['r'];
    $s_diff = abs($s1 - $s2);

    return max($q_diff, $r_diff, $s_diff);
  }

  /**
   * Get all hexes within distance N of center.
   *
   * Returns hexes in rings from center outward.
   *
   * @param array $center
   *   Center hex ['q' => int, 'r' => int]
   * @param int $radius
   *   Radius in hex units
   *
   * @return array
   *   Array of hex coordinates within radius
   */
  public function getHexesWithinRadius(array $center, int $radius): array {
    $result = [];

    for ($q = -$radius; $q <= $radius; $q++) {
      for ($r = max(-$radius, -$q - $radius); $r <= min($radius, -$q + $radius); $r++) {
        $result[] = [
          'q' => $center['q'] + $q,
          'r' => $center['r'] + $r,
        ];
      }
    }

    return $result;
  }

  /**
   * Get hexes in ring N hexes away from center.
   *
   * Only returns hexes exactly at distance N.
   *
   * @param array $center
   *   Center hex
   * @param int $radius
   *   Distance from center
   *
   * @return array
   *   Array of hex coordinates at exactly distance radius
   */
  public function getHexRing(array $center, int $radius): array {
    if ($radius === 0) {
      return [$center];
    }

    $result = [];
    $cube = $this->axialtoCube($center);

    // Start from cube + radius in one direction
    $cube = [
      'x' => $cube['x'] + $radius,
      'y' => $cube['y'] - $radius,
      'z' => $cube['z'],
    ];

    // Walk around the ring
    $directions = [
      [-1, 1, 0],
      [-1, 0, 1],
      [0, -1, 1],
      [1, -1, 0],
      [1, 0, -1],
      [0, 1, -1],
    ];

    foreach ($directions as $direction) {
      for ($i = 0; $i < $radius; $i++) {
        $result[] = $this->cubeToAxial($cube);
        $cube['x'] += $direction[0];
        $cube['y'] += $direction[1];
        $cube['z'] += $direction[2];
      }
    }

    return $result;
  }

  /**
   * Get line of hexes between two points.
   *
   * Uses linear interpolation in cube coordinates.
   *
   * @param array $start
   *   Start hex
   * @param array $end
   *   End hex
   *
   * @return array
   *   Array of hexes from start to end (inclusive)
   */
  public function getLine(array $start, array $end): array {
    $distance = $this->distance($start, $end);
    $result = [];

    for ($i = 0; $i <= $distance; $i++) {
      $t = $distance === 0 ? 0 : $i / $distance;

      $cube_start = $this->axialtoCube($start);
      $cube_end = $this->axialtoCube($end);

      $cube_interp = [
        'x' => $cube_start['x'] + ($cube_end['x'] - $cube_start['x']) * $t,
        'y' => $cube_start['y'] + ($cube_end['y'] - $cube_start['y']) * $t,
        'z' => $cube_start['z'] + ($cube_end['z'] - $cube_start['z']) * $t,
      ];

      $result[] = $this->cubeToAxial($this->roundCube($cube_interp));
    }

    return $result;
  }

  /**
   * Convert axial to cube coordinates.
   *
   * Cube uses x, y, z with constraint x + y + z = 0.
   * Useful for interpolation and line drawing.
   *
   * @param array $hex
   *   Axial hex ['q' => int, 'r' => int]
   *
   * @return array
   *   Cube hex ['x' => int, 'y' => int, 'z' => int]
   */
  protected function axialtoCube(array $hex): array {
    $x = $hex['q'];
    $z = $hex['r'];
    $y = -$x - $z;

    return ['x' => $x, 'y' => $y, 'z' => $z];
  }

  /**
   * Convert cube to axial coordinates.
   *
   * @param array $cube
   *   Cube hex ['x' => int, 'y' => int, 'z' => int]
   *
   * @return array
   *   Axial hex ['q' => int, 'r' => int]
   */
  protected function cubeToAxial(array $cube): array {
    return ['q' => $cube['x'], 'r' => $cube['z']];
  }

  /**
   * Round cube coordinates to nearest valid hex.
   *
   * Used after floating-point interpolation.
   *
   * @param array $cube
   *   Cube hex with floating-point values
   *
   * @return array
   *   Rounded cube hex with integer values
   */
  protected function roundCube(array $cube): array {
    $rx = round($cube['x']);
    $ry = round($cube['y']);
    $rz = round($cube['z']);

    $x_diff = abs($rx - $cube['x']);
    $y_diff = abs($ry - $cube['y']);
    $z_diff = abs($rz - $cube['z']);

    if ($x_diff > $y_diff && $x_diff > $z_diff) {
      $rx = -$ry - $rz;
    } elseif ($y_diff > $z_diff) {
      $ry = -$rx - $rz;
    } else {
      $rz = -$rx - $ry;
    }

    return ['x' => $rx, 'y' => $ry, 'z' => $rz];
  }

  /**
   * Check if hex is within rectangular bounds.
   *
   * @param array $hex
   *   Hex to check
   * @param int $q_min
   *   Minimum Q coordinate
   * @param int $q_max
   *   Maximum Q coordinate
   * @param int $r_min
   *   Minimum R coordinate
   * @param int $r_max
   *   Maximum R coordinate
   *
   * @return bool
   *   True if hex is within bounds
   */
  public function isWithinBounds(array $hex, int $q_min, int $q_max, int $r_min, int $r_max): bool {
    return $hex['q'] >= $q_min && $hex['q'] <= $q_max &&
           $hex['r'] >= $r_min && $hex['r'] <= $r_max;
  }

  /**
   * Rotate hex around center.
   *
   * Rotates counter-clockwise by 60° increments.
   *
   * @param array $hex
   *   Hex to rotate
   * @param array $center
   *   Center of rotation
   * @param int $rotations
   *   Number of 60° rotations (1-6)
   *
   * @return array
   *   Rotated hex coordinate
   */
  public function rotate(array $hex, array $center, int $rotations): array {
    $cube = $this->axialtoCube($hex);
    $center_cube = $this->axialtoCube($center);

    // Translate to origin
    $cube['x'] -= $center_cube['x'];
    $cube['y'] -= $center_cube['y'];
    $cube['z'] -= $center_cube['z'];

    // Rotate
    $rotations = ((($rotations % 6) + 6) % 6);
    for ($i = 0; $i < $rotations; $i++) {
      // 60° counter-clockwise rotation
      $tmp = $cube['x'];
      $cube['x'] = -$cube['z'];
      $cube['z'] = -$cube['y'];
      $cube['y'] = -$tmp;
    }

    // Translate back
    $cube['x'] += $center_cube['x'];
    $cube['y'] += $center_cube['y'];
    $cube['z'] += $center_cube['z'];

    return $this->cubeToAxial($cube);
  }

}
