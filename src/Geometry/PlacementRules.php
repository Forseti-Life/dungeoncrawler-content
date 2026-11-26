<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Geometry;

/**
 * Whether an obstacle or item may stand on a room hex.
 *
 * The one rule set behind the bulk placement commands and every tool that
 * looks for a free hex. Pure functions of a room aggregate.
 */
final class PlacementRules {

  /**
   * Terrain no placed object stands on.
   */
  public const BLOCKING_TERRAIN = ['void', 'wall'];

  /**
   * Hex key => terrain type for every hex of the room.
   *
   * @return array<string, string>
   */
  public static function terrainByHex(array $room): array {
    $map = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      $map[$hex['q'] . ':' . $hex['r']] = (string) ($hex['terrain_type'] ?? '');
    }
    return $map;
  }

  /**
   * Hexes a bulk command leaves empty: ports, the hex outside each, and any
   * hex the caller names in `keep_clear`.
   *
   * @return array<string, string>
   *   Reason ("port" or "caller") keyed by "q:r".
   */
  public static function keptClear(array $room, array $options): array {
    $clear = [];
    foreach ((array) ($options['keep_clear'] ?? []) as $hex) {
      $clear[((int) ($hex['q'] ?? PHP_INT_MAX)) . ':' . ((int) ($hex['r'] ?? PHP_INT_MAX))] = 'caller';
    }
    if (($options['keep_ports_clear'] ?? TRUE) !== FALSE) {
      foreach (['entry_ports', 'exit_ports'] as $bucket) {
        foreach ((array) ($room[$bucket] ?? []) as $port) {
          $hex = $port['hex'];
          $clear[$hex['q'] . ':' . $hex['r']] = 'port';
          $outside = RoomPlacementTransformer::neighbor($hex, (int) $port['edge']);
          $clear[$outside['q'] . ':' . $outside['r']] = 'port';
        }
      }
    }
    return $clear;
  }

  /**
   * Solid (actor and obstacle) placements by hex, leaving out `$moving`.
   *
   * @param list<string> $moving
   *   Instance IDs treated as already gone.
   *
   * @return array<string, string>
   *   Instance ID keyed by "q:r".
   */
  public static function solidOccupancy(array $room, array $moving = []): array {
    $occupied = [];
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if (in_array($placement['instance_id'] ?? NULL, $moving, TRUE)
        || !in_array($placement['definition_ref']['family'] ?? '', ['actor', 'obstacle'], TRUE)) {
        continue;
      }
      $occupied[$placement['anchor_hex']['q'] . ':' . $placement['anchor_hex']['r']] = (string) $placement['instance_id'];
    }
    return $occupied;
  }

  /**
   * Why an object may not stand on a hex, or NULL when it may.
   *
   * @return array{reason: string, blocker: string}|null
   */
  public static function refusal(array $terrain_by_hex, array $clear, array $occupied, string $family, string $key): ?array {
    if (!isset($terrain_by_hex[$key])) {
      return ['reason' => 'hex_not_in_room', 'blocker' => ''];
    }
    if (isset($clear[$key])) {
      return ['reason' => 'hex_kept_clear', 'blocker' => $clear[$key]];
    }
    if (in_array($terrain_by_hex[$key], self::BLOCKING_TERRAIN, TRUE)) {
      return ['reason' => 'hex_blocked_by_terrain', 'blocker' => $terrain_by_hex[$key]];
    }
    if (in_array($family, ['actor', 'obstacle'], TRUE) && isset($occupied[$key])) {
      return ['reason' => 'hex_blocked_by_object', 'blocker' => $occupied[$key]];
    }
    return NULL;
  }

}
