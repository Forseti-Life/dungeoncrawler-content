<?php

namespace Drupal\dungeoncrawler_content\Geometry;


/**
 * Turns a region description into the room hexes it covers.
 */
final class PlacementRegion {

  /**
   * @return list<array{q: int, r: int}>
   */
  public static function roomHexes(array $room): array {
    return array_map(static fn(array $h): array => ['q' => (int) $h['q'], 'r' => (int) $h['r']], array_values((array) ($room['hexes'] ?? [])));
  }

  /**
   * Hexes of the room inside `$region`, in row order.
   *
   * @return list<array{q: int, r: int}>
   */
  public static function hexes(mixed $region, array $room): array {
    if (!is_array($region) || !is_string($region['kind'] ?? NULL)) {
      throw new \InvalidArgumentException('region_invalid');
    }
    $in_room = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      $in_room[HexShapes::key($hex)] = $hex;
    }
    $wanted = match ($region['kind']) {
      'room' => array_values($in_room),
      'disk' => HexShapes::disk(self::hex($region['center'] ?? NULL), (int) ($region['radius'] ?? -1)),
      'hexes' => array_map([self::class, 'hex'], (array) ($region['hexes'] ?? [])),
      'terrain' => array_values(array_filter($in_room, static fn(array $h): bool => ($h['terrain_type'] ?? '') === ($region['terrain_type'] ?? NULL))),
      default => throw new \InvalidArgumentException('region_kind_invalid:' . $region['kind']),
    };
    $hexes = [];
    foreach ($wanted as $hex) {
      if (isset($in_room[HexShapes::key($hex)])) {
        $hexes[] = ['q' => $hex['q'], 'r' => $hex['r']];
      }
    }
    if ($hexes === []) {
      throw new \DomainException('region_empty');
    }
    usort($hexes, static fn(array $a, array $b): int => [$a['r'], $a['q']] <=> [$b['r'], $b['q']]);
    return $hexes;
  }

  public static function centre(array $hexes): array {
    return [
      'q' => (int) round(array_sum(array_column($hexes, 'q')) / count($hexes)),
      'r' => (int) round(array_sum(array_column($hexes, 'r')) / count($hexes)),
    ];
  }

  private static function hex(mixed $value): array {
    if (!is_array($value) || !is_int($value['q'] ?? NULL) || !is_int($value['r'] ?? NULL)) {
      throw new \InvalidArgumentException('region_hex_invalid');
    }
    return ['q' => $value['q'], 'r' => $value['r']];
  }

}
