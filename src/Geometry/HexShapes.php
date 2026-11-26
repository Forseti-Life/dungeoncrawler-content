<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Geometry;

/**
 * Pure hex shapes on axial coordinates, shared by every placement tool.
 *
 * Every function returns a list of {q, r} in a fixed order, so a shape is
 * repeatable. Nothing here knows about rooms, objects or the database.
 */
final class HexShapes {

  public const MIN_CORRIDOR_WIDTH = 1;

  public const MAX_CORRIDOR_WIDTH = 10;

  public static function key(array $hex): string {
    return $hex['q'] . ':' . $hex['r'];
  }

  public static function distance(array $a, array $b): int {
    $dq = $a['q'] - $b['q'];
    $dr = $a['r'] - $b['r'];
    return (int) ((abs($dq) + abs($dr) + abs($dq + $dr)) / 2);
  }

  /**
   * Hexes within `$radius` of `$centre`, in row order.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function disk(array $centre, int $radius): array {
    if ($radius < 0) {
      throw new \InvalidArgumentException('shape_radius_negative');
    }
    $hexes = [];
    for ($dq = -$radius; $dq <= $radius; $dq++) {
      for ($dr = max(-$radius, -$dq - $radius); $dr <= min($radius, -$dq + $radius); $dr++) {
        $hexes[] = ['q' => $centre['q'] + $dq, 'r' => $centre['r'] + $dr];
      }
    }
    return $hexes;
  }

  /**
   * The hexes exactly `$radius` from `$centre`, walking clockwise from the
   * east.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function ring(array $centre, int $radius): array {
    if ($radius < 0) {
      throw new \InvalidArgumentException('shape_radius_negative');
    }
    if ($radius === 0) {
      return [['q' => $centre['q'], 'r' => $centre['r']]];
    }
    $hex = ['q' => $centre['q'] + RoomPlacementTransformer::EDGE_DIRECTIONS[4]['q'] * $radius, 'r' => $centre['r'] + RoomPlacementTransformer::EDGE_DIRECTIONS[4]['r'] * $radius];
    $hexes = [];
    // Walking edges 0,1,2,3,4,5 from the hex at direction 4 traces the ring.
    for ($side = 0; $side < RoomPlacementTransformer::EDGE_COUNT; $side++) {
      for ($step = 0; $step < $radius; $step++) {
        $hexes[] = $hex;
        $hex = RoomPlacementTransformer::neighbor($hex, $side);
      }
    }
    return $hexes;
  }

  /**
   * Straight line of hexes from `$a` to `$b`, both ends included.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function line(array $a, array $b): array {
    $n = self::distance($a, $b);
    if ($n === 0) {
      return [['q' => $a['q'], 'r' => $a['r']]];
    }
    $hexes = [];
    for ($i = 0; $i <= $n; $i++) {
      $t = $i / $n;
      // Nudge off exact ties so the same line is always chosen.
      $q = $a['q'] + ($b['q'] - $a['q']) * $t + 1e-6;
      $r = $a['r'] + ($b['r'] - $a['r']) * $t + 2e-6;
      $hexes[] = self::round($q, $r);
    }
    return $hexes;
  }

  /**
   * Hexes of a strip `$width` hexes wide along the line from `$a` to `$b`.
   *
   * Width counts hex rows across the strip. An even width is centred half a
   * row to the left of the direction of travel.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function strip(array $a, array $b, int $width): array {
    if ($width < self::MIN_CORRIDOR_WIDTH || $width > self::MAX_CORRIDOR_WIDTH) {
      throw new \InvalidArgumentException(sprintf('corridor_width_out_of_range:%d (allowed %d to %d)', $width, self::MIN_CORRIDOR_WIDTH, self::MAX_CORRIDOR_WIDTH));
    }
    $xy = static fn(array $h): array => [sqrt(3) * ($h['q'] + $h['r'] / 2), 1.5 * $h['r']];
    [$ax, $ay] = $xy($a);
    [$bx, $by] = $xy($b);
    $dx = $bx - $ax;
    $dy = $by - $ay;
    $length = sqrt($dx * $dx + $dy * $dy);
    if ($width % 2 === 0 && $length > 0) {
      $shift = 0.75;
      $ax += $dy / $length * $shift;
      $ay -= $dx / $length * $shift;
      $bx += $dy / $length * $shift;
      $by -= $dx / $length * $shift;
      $dx = $bx - $ax;
      $dy = $by - $ay;
    }
    $reach = 0.75 * $width + 0.01;
    $pad = $width + 2;
    $hexes = [];
    for ($q = min($a['q'], $b['q']) - $pad; $q <= max($a['q'], $b['q']) + $pad; $q++) {
      for ($r = min($a['r'], $b['r']) - $pad; $r <= max($a['r'], $b['r']) + $pad; $r++) {
        [$x, $y] = $xy(['q' => $q, 'r' => $r]);
        $t = $length > 0 ? max(0.0, min(1.0, (($x - $ax) * $dx + ($y - $ay) * $dy) / ($dx * $dx + $dy * $dy))) : 0.0;
        if (hypot($x - ($ax + $t * $dx), $y - ($ay + $t * $dy)) < $reach) {
          $hexes[] = ['q' => $q, 'r' => $r];
        }
      }
    }
    return $hexes;
  }

  /**
   * Hexes outside `$shape` within `$thickness` of it ("place around").
   *
   * @param list<array{q: int, r: int}> $shape
   *
   * @return list<array{q: int, r: int}>
   *   Ordered by distance from the shape, then q, then r.
   */
  public static function around(array $shape, int $thickness): array {
    if ($thickness < 1) {
      throw new \InvalidArgumentException('shape_thickness_invalid');
    }
    $inside = [];
    foreach ($shape as $hex) {
      $inside[self::key($hex)] = TRUE;
    }
    $found = [];
    $frontier = $shape;
    for ($layer = 1; $layer <= $thickness; $layer++) {
      $next = [];
      foreach ($frontier as $hex) {
        for ($edge = 0; $edge < RoomPlacementTransformer::EDGE_COUNT; $edge++) {
          $n = RoomPlacementTransformer::neighbor($hex, $edge);
          $k = self::key($n);
          if (!isset($inside[$k]) && !isset($found[$k])) {
            $found[$k] = [$layer, $n];
            $next[] = $n;
          }
        }
      }
      $frontier = $next;
    }
    $rows = array_values($found);
    usort($rows, static fn(array $x, array $y): int => [$x[0], $x[1]['q'], $x[1]['r']] <=> [$y[0], $y[1]['q'], $y[1]['r']]);
    return array_map(static fn(array $row): array => $row[1], $rows);
  }

  /**
   * Hexes of `$shape` that touch a hex outside it.
   *
   * @param list<array{q: int, r: int}> $shape
   *
   * @return list<array{q: int, r: int}>
   */
  public static function boundary(array $shape): array {
    $inside = [];
    foreach ($shape as $hex) {
      $inside[self::key($hex)] = TRUE;
    }
    $edge = [];
    foreach ($shape as $hex) {
      for ($e = 0; $e < RoomPlacementTransformer::EDGE_COUNT; $e++) {
        if (!isset($inside[self::key(RoomPlacementTransformer::neighbor($hex, $e))])) {
          $edge[] = $hex;
          break;
        }
      }
    }
    return $edge;
  }

  /**
   * Rows and columns with spacing, starting at `$origin`.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function grid(array $origin, int $columns, int $rows, int $spacing_q, int $spacing_r): array {
    if ($columns < 1 || $rows < 1 || $spacing_q < 1 || $spacing_r < 1) {
      throw new \InvalidArgumentException('shape_grid_invalid');
    }
    $hexes = [];
    for ($row = 0; $row < $rows; $row++) {
      for ($column = 0; $column < $columns; $column++) {
        $hexes[] = ['q' => $origin['q'] + $column * $spacing_q, 'r' => $origin['r'] + $row * $spacing_r];
      }
    }
    return $hexes;
  }

  /**
   * Every `$every`-th hex of a list (1 keeps all).
   *
   * @param list<array{q: int, r: int}> $hexes
   *
   * @return list<array{q: int, r: int}>
   */
  public static function every(array $hexes, int $every): array {
    if ($every < 1) {
      throw new \InvalidArgumentException('shape_spacing_invalid');
    }
    $kept = [];
    foreach ($hexes as $i => $hex) {
      if ($i % $every === 0) {
        $kept[] = $hex;
      }
    }
    return $kept;
  }

  /**
   * `$count` hexes chosen from `$region`, repeatable for a given seed.
   *
   * No two chosen hexes are closer than `$min_spacing`. Fewer than `$count`
   * come back when the region cannot hold that many; the caller decides
   * whether that is a problem.
   *
   * @param list<array{q: int, r: int}> $region
   *
   * @return list<array{q: int, r: int}>
   */
  public static function scatter(array $region, int $count, int $seed, int $min_spacing = 1): array {
    if ($count < 0 || $min_spacing < 1) {
      throw new \InvalidArgumentException('shape_scatter_invalid');
    }
    $region = array_values($region);
    $state = ($seed & 0x7fffffff) ?: 1;
    $next = static function (int $limit) use (&$state): int {
      // xorshift32, so the result never depends on global random state.
      $state ^= ($state << 13) & 0xffffffff;
      $state ^= $state >> 17;
      $state ^= ($state << 5) & 0xffffffff;
      return $state % $limit;
    };
    for ($i = count($region) - 1; $i > 0; $i--) {
      $j = $next($i + 1);
      [$region[$i], $region[$j]] = [$region[$j], $region[$i]];
    }
    $chosen = [];
    foreach ($region as $hex) {
      if (count($chosen) >= $count) {
        break;
      }
      foreach ($chosen as $other) {
        if (self::distance($hex, $other) < $min_spacing) {
          continue 2;
        }
      }
      $chosen[] = $hex;
    }
    return $chosen;
  }

  /**
   * Rotates hexes about a centre by sixty-degree steps.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function rotateAbout(array $hexes, array $centre, int $steps): array {
    $out = [];
    foreach ($hexes as $hex) {
      $rotated = RoomPlacementTransformer::rotate($hex['q'] - $centre['q'], $hex['r'] - $centre['r'], $steps);
      $out[] = ['q' => $rotated['q'] + $centre['q'], 'r' => $rotated['r'] + $centre['r']];
    }
    return $out;
  }

  /**
   * Mirrors hexes across the line through `$centre` along an edge direction.
   *
   * `$axis` is an edge index 0-5; the mirror line runs along that direction.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function mirror(array $hexes, array $centre, int $axis): array {
    if ($axis < 0 || $axis >= RoomPlacementTransformer::EDGE_COUNT) {
      throw new \InvalidArgumentException('shape_axis_invalid');
    }
    // Reflect in cube space across the axis through the origin: rotate the
    // axis to edge 0, swap across it, rotate back.
    $out = [];
    foreach ($hexes as $hex) {
      $local = RoomPlacementTransformer::rotate($hex['q'] - $centre['q'], $hex['r'] - $centre['r'], RoomPlacementTransformer::EDGE_COUNT - $axis);
      // Reflection across edge 0 (the q axis): (q, r) -> (q + r, -r).
      $reflected = ['q' => $local['q'] + $local['r'], 'r' => -$local['r']];
      $back = RoomPlacementTransformer::rotate($reflected['q'], $reflected['r'], $axis);
      $out[] = ['q' => $back['q'] + $centre['q'], 'r' => $back['r'] + $centre['r']];
    }
    return $out;
  }

  /**
   * @return array{q: int, r: int}
   */
  private static function round(float $q, float $r): array {
    $x = $q;
    $z = $r;
    $y = -$x - $z;
    $rx = round($x);
    $ry = round($y);
    $rz = round($z);
    $dx = abs($rx - $x);
    $dy = abs($ry - $y);
    $dz = abs($rz - $z);
    if ($dx > $dy && $dx > $dz) {
      $rx = -$ry - $rz;
    }
    elseif ($dy > $dz) {
      $ry = -$rx - $rz;
    }
    else {
      $rz = -$rx - $ry;
    }
    return ['q' => (int) $rx, 'r' => (int) $rz];
  }

}
