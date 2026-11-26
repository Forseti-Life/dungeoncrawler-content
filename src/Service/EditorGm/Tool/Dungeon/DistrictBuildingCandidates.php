<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;

/**
 * Finds where a building room can stand on the street.
 *
 * The one place the district placement rule is computed; single and batch
 * planning both call it. The door belongs to the building: a placement is
 * good when one of the room's entry ports lands on a street hex, no hex is
 * shared with another building, and a one hex walkway stays free around it.
 */
final class DistrictBuildingCandidates {

  /**
   * @param string $room_id
   *   Library room id, for error messages.
   * @param array<string, mixed> $library
   *   Library room (footprint, ports).
   * @param array{q: int, r: int} $at
   *   Street hex the entry port must land on.
   * @param array<string, list<string>> $occupied
   *   Placement ids by hex key.
   *
   * @return array{candidates: list<array<string, mixed>>, rejected: list<array<string, mixed>>, why: string}
   */
  public static function find(string $room_id, array $library, string $street_id, array $at, array $occupied, string $entry_filter = ''): array {
    if (!in_array($street_id, $occupied[RoomPlacementTransformer::hexKey($at)] ?? [], TRUE)) {
      throw new \DomainException(sprintf('district_hex_not_on_street:%d,%d', $at['q'], $at['r']));
    }
    $entries = array_values(array_filter((array) $library['ports'], static fn(array $p): bool => $p['kind'] === 'entry'));
    if ($entry_filter !== '') {
      $entries = array_values(array_filter($entries, static fn(array $p): bool => $p['port_id'] === $entry_filter));
    }
    if ($entries === []) {
      throw new \DomainException(sprintf('room_has_no_matching_entry_port:%s', $room_id));
    }

    $candidates = [];
    $rejected = [];
    foreach ($entries as $entry) {
      for ($steps = 0; $steps < RoomPlacementTransformer::EDGE_COUNT; $steps++) {
        $rotated = RoomPlacementTransformer::rotate((int) $entry['q'], (int) $entry['r'], $steps);
        $placement = [
          'origin' => ['q' => $at['q'] - $rotated['q'], 'r' => $at['r'] - $rotated['r']],
          'rotation_steps' => $steps,
        ];
        $problems = [];
        $own = [];
        foreach ((array) $library['footprint'] as $hex) {
          $level = RoomPlacementTransformer::toLevel($hex, $placement);
          if (abs($level['q']) > DungeonEditorService::AXIAL_BOUND || abs($level['r']) > DungeonEditorService::AXIAL_BOUND) {
            $problems['out_of_bounds'] = TRUE;
          }
          $own[RoomPlacementTransformer::hexKey($level)] = $level;
        }
        foreach ($own as $key => $level) {
          if (array_diff((array) ($occupied[$key] ?? []), [$street_id]) !== []) {
            $problems['overlaps'][] = $level;
          }
          for ($edge = 0; $edge < RoomPlacementTransformer::EDGE_COUNT; $edge++) {
            $next = RoomPlacementTransformer::neighbor($level, $edge);
            $next_key = RoomPlacementTransformer::hexKey($next);
            if (!isset($own[$next_key]) && array_diff((array) ($occupied[$next_key] ?? []), [$street_id]) !== []) {
              $problems['crowds'][] = $level;
              break;
            }
          }
        }
        $candidate = ['entry_port_id' => $entry['port_id'], 'origin' => $placement['origin'], 'rotation_steps' => $steps];
        if ($problems !== []) {
          $rejected[] = $candidate + ['problems' => array_keys($problems)];
          continue;
        }
        $candidates[] = $candidate;
      }
    }
    $why = $candidates === [] ? implode(',', array_map(static fn(array $r): string => sprintf('rot%d:%s', $r['rotation_steps'], implode('+', $r['problems'])), $rejected)) : '';

    return ['candidates' => $candidates, 'rejected' => $rejected, 'why' => $why];
  }

  /**
   * Street hexes nearest the street centre first, clear of the rim.
   *
   * @param array<string, list<string>> $occupied
   *
   * @return list<array{q: int, r: int}>
   */
  public static function streetHexes(string $street_id, array $occupied): array {
    $street = [];
    foreach ($occupied as $key => $ids) {
      if (in_array($street_id, $ids, TRUE)) {
        [$q, $r] = array_map('intval', explode(':', $key));
        $street[$key] = ['q' => $q, 'r' => $r];
      }
    }
    $margin = DungeonEditorService::STREET_EDGE_MARGIN;
    $hexes = [];
    foreach ($street as $hex) {
      $clear = TRUE;
      for ($dq = -$margin; $dq <= $margin && $clear; $dq++) {
        for ($dr = max(-$margin, -$dq - $margin); $dr <= min($margin, -$dq + $margin); $dr++) {
          if (!isset($street[($hex['q'] + $dq) . ':' . ($hex['r'] + $dr)])) {
            $clear = FALSE;
            break;
          }
        }
      }
      if ($clear) {
        $hexes[] = $hex + ['distance' => intdiv(abs($hex['q']) + abs($hex['q'] + $hex['r']) + abs($hex['r']), 2)];
      }
    }
    usort($hexes, static fn(array $a, array $b): int => [$a['distance'], $a['q'], $a['r']] <=> [$b['distance'], $b['q'], $b['r']]);
    return array_map(static fn(array $h): array => ['q' => $h['q'], 'r' => $h['r']], $hexes);
  }

  /**
   * First street hex where the room fits, or the given hex.
   *
   * @return array{at: array{q: int, r: int}, found: array<string, mixed>}
   */
  public static function place(string $room_id, array $library, string $street_id, ?array $at, array $occupied, string $entry_filter = ''): array {
    if ($at !== NULL) {
      return ['at' => $at, 'found' => self::find($room_id, $library, $street_id, $at, $occupied, $entry_filter)];
    }
    foreach (self::streetHexes($street_id, $occupied) as $hex) {
      $found = self::find($room_id, $library, $street_id, $hex, $occupied, $entry_filter);
      if ($found['candidates'] !== []) {
        return ['at' => $hex, 'found' => $found];
      }
    }
    throw new \DomainException(sprintf('no_district_placement:%s:%s:street_full', $room_id, $street_id));
  }

}
