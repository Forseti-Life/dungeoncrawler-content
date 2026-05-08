<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\HexUtilityService;
use Drupal\dungeoncrawler_content\Service\LineOfEffectService;

/**
 * Resolves areas of effect (burst, cone, emanation, line) on the hex grid.
 *
 * All methods ignore terrain — difficult terrain does not shrink areas (req 2129).
 * Uses axial (q/r) hex coordinates with flat-top orientation.
 *
 * Direction integers (HexUtilityService convention):
 *   0=East, 1=Southeast, 2=Southwest, 3=West, 4=Northwest, 5=Northeast
 *
 * Direction string aliases accepted by directionToInt():
 *   'E'/'east'=0, 'SE'/'southeast'=1, 'SW'/'southwest'=2,
 *   'W'/'west'=3, 'NW'/'northwest'=4, 'NE'/'northeast'=5,
 *   'N'/'north'→5 (Northeast), 'S'/'south'→2 (Southwest)
 */
class AreaResolverService {

  protected HexUtilityService $hexUtility;
  protected ?LineOfEffectService $losService;

  public function __construct(HexUtilityService $hex_utility, ?LineOfEffectService $los_service = NULL) {
    $this->hexUtility = $hex_utility;
    $this->losService = $los_service;
  }

  /**
   * Resolve all participants within a burst area.
   *
   * Burst originates at (origin_q, origin_r). All hexes within $radius
   * are included. Difficult terrain does NOT affect inclusion (req 2129).
   *
   * @param int $origin_q
   *   Origin hex q coordinate.
   * @param int $origin_r
   *   Origin hex r coordinate.
   * @param int $radius
   *   Radius in hex units.
   * @param array $participants
   *   All encounter participants with position_q/position_r fields.
   *
   * @return array
   *   Participant IDs (int[]) whose position falls within the burst.
   */
  public function resolveBurst(int $origin_q, int $origin_r, int $radius, array $participants): array {
    $origin = ['q' => $origin_q, 'r' => $origin_r];
    $in_area = [];
    foreach ($participants as $p) {
      $pos = $this->participantPos($p);
      if ($pos === NULL) {
        continue;
      }
      if ($this->hexUtility->distance($origin, $pos) <= $radius) {
        $in_area[] = (int) $p['id'];
      }
    }
    return $in_area;
  }

  /**
   * Resolve all participants within a cone area.
   *
   * Cone covers a quarter-circle (90°) from caster in $direction.
   * The caster's own hex is never included (req 2126).
   *
   * @param array $caster_pos
   *   Caster hex ['q' => int, 'r' => int].
   * @param string $direction
   *   One of: 'E','NE','SE','S','SW','NW','N','W' (case-insensitive).
   * @param int $length
   *   Cone length in hexes (max distance from caster).
   * @param array $participants
   *   All encounter participants.
   *
   * @return array
   *   Participant IDs in cone.
   */
  public function resolveCone(array $caster_pos, string $direction, int $length, array $participants): array {
    $dir_int = $this->directionToInt($direction);
    $center_angle = $this->directionAngle($dir_int);
    $in_area = [];
    foreach ($participants as $p) {
      $pos = $this->participantPos($p);
      if ($pos === NULL) {
        continue;
      }
      // Exclude caster.
      if ($pos['q'] === $caster_pos['q'] && $pos['r'] === $caster_pos['r']) {
        continue;
      }
      $dq = $pos['q'] - $caster_pos['q'];
      $dr = $pos['r'] - $caster_pos['r'];
      $dist = $this->hexUtility->distance($caster_pos, $pos);
      if ($dist === 0 || $dist > $length) {
        continue;
      }
      $cart = $this->hexOffsetToCartesian($dq, $dr);
      $hex_angle = rad2deg(atan2($cart['y'], $cart['x']));
      if ($this->isInArc($hex_angle, $center_angle, 45.0)) {
        $in_area[] = (int) $p['id'];
      }
    }
    return $in_area;
  }

  /**
   * Resolve all participants within an emanation area.
   *
   * Emanation extends from all sides of the caster's hex (req 2127).
   *
   * @param array $caster_pos
   *   Caster hex ['q' => int, 'r' => int].
   * @param int $radius
   *   Radius in hex units.
   * @param array $participants
   *   All encounter participants.
   * @param bool $include_origin
   *   Whether to include participants on the caster's own hex (default FALSE).
   *
   * @return array
   *   Participant IDs in emanation.
   */
  public function resolveEmanation(array $caster_pos, int $radius, array $participants, bool $include_origin = FALSE): array {
    $in_area = [];
    foreach ($participants as $p) {
      $pos = $this->participantPos($p);
      if ($pos === NULL) {
        continue;
      }
      $dist = $this->hexUtility->distance($caster_pos, $pos);
      if ($dist === 0 && !$include_origin) {
        continue;
      }
      if ($dist <= $radius) {
        $in_area[] = (int) $p['id'];
      }
    }
    return $in_area;
  }

  /**
   * Resolve all participants along a line area.
   *
   * Line is 1 hex wide, extending from $start_pos for $length hexes in
   * $direction (req 2128). The starting hex is included; terrain ignored
   * (req 2129).
   *
   * @param array $start_pos
   *   Start hex ['q' => int, 'r' => int].
   * @param string $direction
   *   One of: 'E','NE','SE','S','SW','NW','N','W' (case-insensitive).
   * @param int $length
   *   Line length in hexes.
   * @param array $participants
   *   All encounter participants.
   *
   * @return array
   *   Participant IDs on the line.
   */
  public function resolveLine(array $start_pos, string $direction, int $length, array $participants): array {
    $dir_int = $this->directionToInt($direction);
    // Build the set of hexes on the line.
    $line_hexes = [];
    $current = $start_pos;
    for ($step = 0; $step <= $length; $step++) {
      $line_hexes[] = $current;
      $current = $this->hexUtility->getNeighbor($current, $dir_int);
    }
    $in_area = [];
    foreach ($participants as $p) {
      $pos = $this->participantPos($p);
      if ($pos === NULL) {
        continue;
      }
      foreach ($line_hexes as $lh) {
        if ($lh['q'] === $pos['q'] && $lh['r'] === $pos['r']) {
          $in_area[] = (int) $p['id'];
          break;
        }
      }
    }
    return $in_area;
  }

  /**
   * Extract participant hex position from participant array.
   *
   * @return array|null
   *   ['q' => int, 'r' => int] or NULL if position is missing.
   */
  private function participantPos(array $p): ?array {
    if (!isset($p['position_q']) || !isset($p['position_r'])) {
      return NULL;
    }
    return ['q' => (int) $p['position_q'], 'r' => (int) $p['position_r']];
  }

  /**
   * Map direction string to integer (0–5).
   *
   * Accepts: E, NE, SE, SW, NW, W, N (→NE), S (→SW). Case-insensitive.
   */
  private function directionToInt(string $direction): int {
    $map = [
      'E' => 0, 'EAST' => 0,
      'SE' => 1, 'SOUTHEAST' => 1,
      'SW' => 2, 'SOUTHWEST' => 2,
      'W' => 3, 'WEST' => 3,
      'NW' => 4, 'NORTHWEST' => 4,
      'NE' => 5, 'NORTHEAST' => 5,
      'N' => 5, 'NORTH' => 5,
      'S' => 2, 'SOUTH' => 2,
    ];
    return $map[strtoupper($direction)] ?? 0;
  }

  /**
   * Get the Cartesian angle (degrees) for a hex direction integer.
   *
   * Flat-top hex axial-to-Cartesian mapping:
   *   x = 1.5 * q, y = (sqrt(3)/2)*q + sqrt(3)*r
   *
   * Resulting angles: E=30°, SE=330°, SW=270°, W=210°, NW=150°, NE=90°
   *
   * @param int $dir
   *   Direction integer 0–5.
   *
   * @return float
   *   Angle in degrees (range −180 to 180).
   */
  private function directionAngle(int $dir): float {
    $angles = [30.0, -30.0, -90.0, -150.0, 150.0, 90.0];
    return $angles[$dir] ?? 0.0;
  }

  /**
   * Convert axial hex offset (dq, dr) to flat-top Cartesian coordinates.
   */
  private function hexOffsetToCartesian(int $dq, int $dr): array {
    return [
      'x' => 1.5 * $dq,
      'y' => (sqrt(3) / 2.0) * $dq + sqrt(3) * $dr,
    ];
  }

  /**
   * Check if $angle falls within $half_width degrees of $center_angle.
   *
   * Handles wrap-around at ±180°.
   */
  private function isInArc(float $angle, float $center, float $half_width): bool {
    $diff = fmod($angle - $center + 540.0, 360.0) - 180.0;
    return abs($diff) <= $half_width;
  }

  /**
   * Filter a list of participant IDs to those with LoE from $origin.
   *
   * When $los_service is not available, returns IDs unfiltered (no-op).
   * terrain_obstacles must be pre-keyed arrays with is_solid/is_semi_solid.
   *
   * @param array $origin
   *   Origin hex ['q' => int, 'r' => int].
   * @param array $participant_ids
   *   Array of participant IDs (int[]).
   * @param array $participants
   *   Full participant records (for position lookup).
   * @param array $terrain_obstacles
   *   Obstacle array; pass [] if not available.
   *
   * @return array
   *   Filtered participant IDs that have LoE from origin.
   */
  public function filterByLoE(array $origin, array $participant_ids, array $participants, array $terrain_obstacles = []): array {
    if (!$this->losService || empty($terrain_obstacles)) {
      return $participant_ids;
    }
    $pos_map = [];
    foreach ($participants as $p) {
      $pos_map[(int) $p['id']] = $this->participantPos($p);
    }
    $filtered = [];
    foreach ($participant_ids as $id) {
      $pos = $pos_map[(int) $id] ?? NULL;
      if ($pos === NULL) {
        continue;
      }
      if ($this->losService->hasLineOfEffect($origin, $pos, $terrain_obstacles)) {
        $filtered[] = $id;
      }
    }
    return $filtered;
  }

}
