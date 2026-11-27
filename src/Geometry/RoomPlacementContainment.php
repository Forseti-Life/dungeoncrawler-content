<?php

namespace Drupal\dungeoncrawler_content\Geometry;

/**
 * Shared containment rules for nested room placements.
 */
final class RoomPlacementContainment {

  /**
   * @return array<string, true>
   */
  public static function hexes(array $room, array $placement): array {
    $set = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (!is_array($hex) || !isset($hex['q'], $hex['r'])) {
        continue;
      }
      $set[self::key(RoomPlacementTransformer::toLevel($hex, $placement))] = TRUE;
    }
    return $set;
  }

  public static function key(array $hex): string {
    return (int) $hex['q'] . ':' . (int) $hex['r'];
  }

  public static function strictlyContains(array $outer, array $inner): bool {
    if (count($outer) <= count($inner) || $inner === []) {
      return FALSE;
    }
    return array_diff_key($inner, $outer) === [];
  }

  public static function nested(array $a, array $b): bool {
    return self::strictlyContains($a, $b) || self::strictlyContains($b, $a);
  }

  /**
   * Invalid overlap pairs for one occupancy cell.
   *
   * @param list<string> $ids
   * @param array<string, array<string, true>> $footprints
   *
   * @return list<array{0: string, 1: string}>
   */
  public static function invalidPairs(array $ids, array $footprints): array {
    $ids = array_values(array_unique($ids));
    $pairs = [];
    for ($i = 0; $i < count($ids); $i++) {
      for ($j = $i + 1; $j < count($ids); $j++) {
        $a = $ids[$i];
        $b = $ids[$j];
        if (isset($footprints[$a], $footprints[$b]) && self::nested($footprints[$a], $footprints[$b])) {
          continue;
        }
        $pair = [$a, $b];
        sort($pair, SORT_STRING);
        $pairs[] = $pair;
      }
    }
    return $pairs;
  }

}
