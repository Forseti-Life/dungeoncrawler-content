<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\PlacementRules;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Read-only placement inspection for the Room GM.
 */
final class PlacementAnalysisTool implements EditorGmToolInterface {

  public const NAMES = ['list_placements', 'inspect_blocking', 'analyze_navigation'];

  private const LIMIT = 500;

  public function __construct(private readonly string $name) {
    if (!in_array($name, self::NAMES, TRUE)) {
      throw new \LogicException('placement_analysis_tool_invalid:' . $name);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $hex = 'Hex as {q, r}.';
    [$summary, $arguments] = match ($this->name) {
      'list_placements' => ['List placed objects, optionally filtered by family, definition_id or a disc of hexes (center and radius).', [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'actor, obstacle or item.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Only placements of this definition.'),
        EditorGmToolDefinition::argument('center', 'object', FALSE, $hex),
        EditorGmToolDefinition::argument('radius', 'integer', FALSE, 'With center: hexes around it (default 0).'),
      ]],
      'inspect_blocking' => ['Say what stands on a hex and whether movement can enter it: terrain, objects and kept-clear port hexes.', [
        EditorGmToolDefinition::argument('hex', 'object', TRUE, $hex),
      ]],
      'analyze_navigation' => ['Flood-fill the room over passable hexes from every entry port. Reports ports and hexes that cannot be reached and open placement gaps that sit on a route.', []],
    };
    return new EditorGmToolDefinition(
      $this->name,
      EditorGmToolDefinition::FAMILY_CONTEXT,
      $summary . ' Read-only.',
      FALSE,
      'draft room payload',
      $arguments,
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $room = RoomEditorGmToolContext::of($context)->room();
    return match ($this->name) {
      'list_placements' => $this->list($room, $arguments),
      'inspect_blocking' => $this->inspect($room, $arguments),
      'analyze_navigation' => $this->navigation($room),
    };
  }

  private function list(array $room, array $arguments): array {
    $near = NULL;
    if (isset($arguments['center'])) {
      $near = [HexShapes::disk($arguments['center'], max(0, (int) ($arguments['radius'] ?? 0)))];
      $near = array_flip(array_map([HexShapes::class, 'key'], $near[0]));
    }
    $found = [];
    foreach ((array) ($room['placements'] ?? []) as $p) {
      $ref = $p['definition_ref'];
      if ((isset($arguments['family']) && $ref['family'] !== $arguments['family'])
        || (isset($arguments['definition_id']) && $ref['definition_id'] !== $arguments['definition_id'])
        || ($near !== NULL && !isset($near[HexShapes::key($p['anchor_hex'])]))) {
        continue;
      }
      $found[] = ['instance_id' => $p['instance_id'], 'family' => $ref['family'], 'definition_id' => $ref['definition_id'], 'hex' => $p['anchor_hex'], 'facing' => $p['facing'] ?? 0];
    }
    return ['match_count' => count($found), 'truncated' => count($found) > self::LIMIT, 'placements' => array_slice($found, 0, self::LIMIT)];
  }

  private function inspect(array $room, array $arguments): array {
    $hex = $arguments['hex'] ?? NULL;
    if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
      throw new \InvalidArgumentException('hex_invalid');
    }
    $key = HexShapes::key($hex);
    $terrain = PlacementRules::terrainByHex($room);
    $clear = PlacementRules::keptClear($room, []);
    $objects = [];
    foreach ((array) ($room['placements'] ?? []) as $p) {
      if (HexShapes::key($p['anchor_hex']) === $key) {
        $objects[] = ['instance_id' => $p['instance_id'], 'family' => $p['definition_ref']['family'], 'definition_id' => $p['definition_ref']['definition_id']];
      }
    }
    $blockers = [];
    if (!isset($terrain[$key])) {
      $blockers[] = 'not_in_room';
    }
    elseif (in_array($terrain[$key], self::IMPASSABLE, TRUE)) {
      $blockers[] = 'terrain:' . $terrain[$key];
    }
    foreach ($objects as $o) {
      if (in_array($o['family'], ['actor', 'obstacle'], TRUE)) {
        $blockers[] = 'object:' . $o['instance_id'];
      }
    }
    return [
      'hex' => $hex,
      'in_room' => isset($terrain[$key]),
      'terrain_type' => $terrain[$key] ?? NULL,
      'objects' => $objects,
      'kept_clear_by' => $clear[$key] ?? NULL,
      'blocked_by' => $blockers,
      'enterable' => $blockers === [],
    ];
  }

  /**
   * Terrain movement cannot enter (MovementResolverService).
   */
  private const IMPASSABLE = ['void', 'wall', 'solid_rock', 'chasm', 'pit'];

  private function navigation(array $room): array {
    $terrain = PlacementRules::terrainByHex($room);
    $blocked = [];
    foreach (PlacementRules::solidOccupancy($room) as $key => $_) {
      $blocked[$key] = TRUE;
    }
    $passable = static fn(string $k): bool => isset($terrain[$k]) && !in_array($terrain[$k], self::IMPASSABLE, TRUE) && !isset($blocked[$k]);
    $entries = (array) ($room['entry_ports'] ?? []);
    if ($entries === []) {
      throw new \DomainException('room_has_no_entry_ports');
    }
    $seen = [];
    $queue = [];
    $start = HexShapes::key($entries[0]['hex']);
    if ($passable($start)) {
      $seen[$start] = TRUE;
      $queue[] = $entries[0]['hex'];
    }
    while ($queue) {
      $hex = array_shift($queue);
      foreach (HexShapes::ring($hex, 1) as $n) {
        $k = HexShapes::key($n);
        if (!isset($seen[$k]) && $passable($k)) {
          $seen[$k] = TRUE;
          $queue[] = $n;
        }
      }
    }
    $unreachable_ports = [];
    foreach (['entry_ports', 'exit_ports'] as $bucket) {
      foreach ((array) ($room[$bucket] ?? []) as $port) {
        if (!isset($seen[HexShapes::key($port['hex'])])) {
          $unreachable_ports[] = ['port_id' => $port['port_id'] ?? NULL, 'bucket' => $bucket, 'hex' => $port['hex']];
        }
      }
    }
    $unreachable = [];
    foreach ($terrain as $k => $_) {
      if ($passable($k) && !isset($seen[$k])) {
        [$q, $r] = explode(':', $k);
        $unreachable[] = ['q' => (int) $q, 'r' => (int) $r];
      }
    }
    $route_gaps = [];
    foreach ((array) ($room['metadata']['placement_gaps'] ?? []) as $gap) {
      if (($gap['status'] ?? 'open') !== 'open' || ($gap['reason'] ?? '') !== 'hex_blocked_by_object') {
        continue;
      }
      // Removing a blocker that touches both reached and unreached hexes
      // reopens a route.
      $touches_seen = FALSE;
      $touches_cut = FALSE;
      foreach (HexShapes::ring($gap['hex'], 1) as $n) {
        $k = HexShapes::key($n);
        $touches_seen = $touches_seen || isset($seen[$k]);
        $touches_cut = $touches_cut || ($passable($k) && !isset($seen[$k]));
      }
      if ($touches_seen && $touches_cut) {
        $route_gaps[] = $gap['gap_id'];
      }
    }
    return [
      'reachable_hex_count' => count($seen),
      'unreachable_hex_count' => count($unreachable),
      'unreachable_hexes' => array_slice($unreachable, 0, self::LIMIT),
      'unreachable_ports' => $unreachable_ports,
      'gaps_that_would_reopen_a_route' => $route_gaps,
      'navigable' => $unreachable_ports === [] && $unreachable === [],
    ];
  }

}
