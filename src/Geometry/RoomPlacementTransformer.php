<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Geometry;

/**
 * Rigid transform between room-local and dungeon-level axial hex space.
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   10-placement-transform-spec.md
 *
 * This class is pure, stateless and dependency free. It performs no database
 * access, no logging and no business-rule validation.
 *
 * It is the ONLY place in the PHP codebase where EDGE_DIRECTIONS and the
 * rotation primitive may appear. The JavaScript mirror is
 * js/v2/editor/placementTransform.js and the two are pinned together by the
 * shared fixture vectors in
 * config/schemas/fixtures/placement_transform_vectors.json.
 * Any duplicate of this math anywhere else is a defect.
 *
 * All arithmetic is integer. No floating point is permitted.
 */
final class RoomPlacementTransformer {

  /**
   * Number of edges on a hex, and therefore the rotation order.
   */
  public const EDGE_COUNT = 6;

  /**
   * Frozen edge convention. Edge $e of hex H faces H + EDGE_DIRECTIONS[$e].
   *
   * This ordering is load bearing. It is chosen so that rotation reduces to
   * index arithmetic:
   *   rotate(EDGE_DIRECTIONS[e], k) === EDGE_DIRECTIONS[(e + k) % 6]
   * Any other ordering would require an explicit edge permutation table.
   *
   * Before this constant existed the codebase validated `0 <= edge <= 5` and
   * nothing else, so previously authored edge values carry no reliable
   * meaning. They are audited, never reinterpreted silently.
   */
  public const EDGE_DIRECTIONS = [
    0 => ['q' => 1, 'r' => 0],
    1 => ['q' => 0, 'r' => 1],
    2 => ['q' => -1, 'r' => 1],
    3 => ['q' => -1, 'r' => 0],
    4 => ['q' => 0, 'r' => -1],
    5 => ['q' => 1, 'r' => -1],
  ];

  /**
   * Rotates an axial coordinate by $steps sixty-degree steps about the origin.
   *
   * Derivation: convert axial to cube with x = q, z = r, y = -x - z. A sixty
   * degree rotation is the cube permutation (x, y, z) -> (-z, -x, -y).
   * Substituting back gives q' = -r, r' = q + r.
   *
   * @return array{q: int, r: int}
   *   The rotated coordinate.
   */
  public static function rotate(int $q, int $r, int $steps): array {
    $steps = self::normalizeSteps($steps);

    for ($i = 0; $i < $steps; $i++) {
      [$q, $r] = [-$r, $q + $r];
    }

    return ['q' => $q, 'r' => $r];
  }

  /**
   * Maps a room-local hex into level space under a placement.
   *
   * @param array $hex
   *   Room-local hex with integer 'q' and 'r'.
   * @param array $placement
   *   Placement with 'origin' (q, r) and 'rotation_steps'.
   *
   * @return array{q: int, r: int}
   *   The level-space hex.
   */
  public static function toLevel(array $hex, array $placement): array {
    [$q, $r] = self::readHex($hex);
    [$origin_q, $origin_r, $steps] = self::readPlacement($placement);

    $rotated = self::rotate($q, $r, $steps);

    return [
      'q' => $rotated['q'] + $origin_q,
      'r' => $rotated['r'] + $origin_r,
    ];
  }

  /**
   * Maps a level-space hex back into room-local space under a placement.
   *
   * Inverse of toLevel(). toRoomLocal(toLevel(h, p), p) === h for all h, p.
   *
   * @return array{q: int, r: int}
   *   The room-local hex.
   */
  public static function toRoomLocal(array $hex, array $placement): array {
    [$q, $r] = self::readHex($hex);
    [$origin_q, $origin_r, $steps] = self::readPlacement($placement);

    return self::rotate(
      $q - $origin_q,
      $r - $origin_r,
      self::EDGE_COUNT - $steps,
    );
  }

  /**
   * Rotates an edge index. Rotation of the frozen convention is index addition.
   */
  public static function rotateEdge(int $edge, int $steps): int {
    self::assertEdge($edge);

    return ($edge + self::normalizeSteps($steps)) % self::EDGE_COUNT;
  }

  /**
   * Returns the edge facing the opposite direction.
   */
  public static function opposite(int $edge): int {
    self::assertEdge($edge);

    return ($edge + 3) % self::EDGE_COUNT;
  }

  /**
   * Returns the neighbouring hex across the given edge.
   *
   * @return array{q: int, r: int}
   *   The neighbour coordinate, in the same space as the input.
   */
  public static function neighbor(array $hex, int $edge): array {
    [$q, $r] = self::readHex($hex);
    self::assertEdge($edge);

    $direction = self::EDGE_DIRECTIONS[$edge];

    return [
      'q' => $q + $direction['q'],
      'r' => $r + $direction['r'],
    ];
  }

  /**
   * Maps a room-local port into level space.
   *
   * Because of the frozen edge ordering this needs no lookup table: the edge
   * rotates by the same number of steps as the hex.
   *
   * @return array{q: int, r: int, edge: int}
   *   Level-space hex plus rotated edge.
   */
  public static function toLevelPort(array $hex, int $edge, array $placement): array {
    [,, $steps] = self::readPlacement($placement);

    $level_hex = self::toLevel($hex, $placement);
    $level_hex['edge'] = self::rotateEdge($edge, $steps);

    return $level_hex;
  }

  /**
   * Cube distance from the axial origin. Invariant under rotation.
   */
  public static function distanceFromOrigin(int $q, int $r): int {
    return intdiv(abs($q) + abs($q + $r) + abs($r), 2);
  }

  /**
   * Stable string key for a level hex, for occupancy maps.
   */
  public static function hexKey(array $hex): string {
    [$q, $r] = self::readHex($hex);

    return $q . ':' . $r;
  }

  /**
   * Reduces a rotation step count to the canonical 0..5 range.
   *
   * Accepts negative input so callers may express inverse rotations directly.
   */
  private static function normalizeSteps(int $steps): int {
    return (($steps % self::EDGE_COUNT) + self::EDGE_COUNT) % self::EDGE_COUNT;
  }

  /**
   * Reads and hard-validates an integer axial coordinate.
   *
   * @return array{0: int, 1: int}
   *   The q and r values.
   */
  private static function readHex(array $hex): array {
    foreach (['q', 'r'] as $axis) {
      if (!array_key_exists($axis, $hex)) {
        throw new \InvalidArgumentException('placement_transform_hex_missing_axis:' . $axis);
      }
      if (!is_int($hex[$axis])) {
        throw new \InvalidArgumentException('placement_transform_hex_axis_not_integer:' . $axis);
      }
    }

    return [$hex['q'], $hex['r']];
  }

  /**
   * Reads and hard-validates a placement.
   *
   * @return array{0: int, 1: int, 2: int}
   *   Origin q, origin r, and rotation steps.
   */
  private static function readPlacement(array $placement): array {
    if (!isset($placement['origin']) || !is_array($placement['origin'])) {
      throw new \InvalidArgumentException('placement_transform_origin_missing');
    }

    [$origin_q, $origin_r] = self::readHex($placement['origin']);

    if (!array_key_exists('rotation_steps', $placement)) {
      throw new \InvalidArgumentException('placement_transform_rotation_missing');
    }
    if (!is_int($placement['rotation_steps'])) {
      throw new \InvalidArgumentException('placement_transform_rotation_not_integer');
    }

    $steps = $placement['rotation_steps'];
    if ($steps < 0 || $steps > 5) {
      throw new \InvalidArgumentException('placement_transform_rotation_out_of_range:' . $steps);
    }

    return [$origin_q, $origin_r, $steps];
  }

  /**
   * Hard-validates an edge index.
   */
  private static function assertEdge(int $edge): void {
    if ($edge < 0 || $edge >= self::EDGE_COUNT) {
      throw new \InvalidArgumentException('placement_transform_edge_out_of_range:' . $edge);
    }
  }

}
