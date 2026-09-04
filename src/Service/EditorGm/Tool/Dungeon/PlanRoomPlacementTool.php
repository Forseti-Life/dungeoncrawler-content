<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Finds every origin/rotation that seals a new room against an exit port.
 *
 * A sealed link requires the new room's entry port to sit on the hex the
 * anchor exit faces, with the opposite edge. That fixes the rotation per
 * entry port and the origin per rotation, so the search is exhaustive and
 * exact: six rotations per entry port, each checked for overlap and bounds.
 * If nothing fits, the tool says so rather than proposing an unsealed spot.
 */
final class PlanRoomPlacementTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_room_placement',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Compute every non-overlapping origin and rotation that places a published room so one of its entry ports seals against a given exit port, and plan the place_room step for the chosen candidate. Follow up with plan_port_links after applying.',
      FALSE,
      'planning only; RoomPlacementTransformer geometry, execution requires apply_dungeon_commands',
      [
        EditorGmToolDefinition::argument('room_id', 'string', TRUE, 'Published room to place (from list_published_rooms).'),
        EditorGmToolDefinition::argument('anchor_placement_id', 'string', TRUE, 'Existing placement whose exit port the new room must seal against.'),
        EditorGmToolDefinition::argument('anchor_port_id', 'string', TRUE, 'Exit port id on the anchor placement.'),
        EditorGmToolDefinition::argument('entry_port_id', 'string', FALSE, 'Restrict to one entry port of the new room.'),
        EditorGmToolDefinition::argument('candidate', 'integer', FALSE, '1-based index into the returned candidates to plan (default 1).'),
        EditorGmToolDefinition::argument('label', 'string', FALSE, 'Placement label.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $room_id = EditorGmToolContext::requireString($arguments, 'room_id');
    $anchor_id = EditorGmToolContext::requireString($arguments, 'anchor_placement_id');
    $anchor_port_id = EditorGmToolContext::requireString($arguments, 'anchor_port_id');
    $entry_filter = isset($arguments['entry_port_id']) ? trim((string) $arguments['entry_port_id']) : '';

    $model = $context->model();
    $library = DungeonPlanSteps::libraryRoom($context, $room_id);
    $anchor = DungeonPlanSteps::placement($model, $anchor_id);
    if (!$anchor['resolved']) {
      throw new \DomainException(sprintf('placement_unresolved:%s', $anchor_id));
    }

    $anchor_port = NULL;
    foreach ((array) $anchor['room']['ports'] as $port) {
      if ($port['port_id'] === $anchor_port_id) {
        $anchor_port = $port;
        break;
      }
    }
    if ($anchor_port === NULL) {
      throw new \OutOfBoundsException(sprintf('port_not_found:%s:%s', $anchor_id, $anchor_port_id));
    }
    if ($anchor_port['kind'] !== 'exit') {
      throw new \DomainException(sprintf('anchor_port_not_exit:%s', $anchor_port_id));
    }
    foreach ((array) ($model['port_links'] ?? []) as $link) {
      if ($link['from']['placement_id'] === $anchor_id && $link['from']['port_id'] === $anchor_port_id) {
        throw new \DomainException(sprintf('port_already_linked:%s', $link['link_id']));
      }
    }

    $exit = RoomPlacementTransformer::toLevelPort(['q' => $anchor_port['q'], 'r' => $anchor_port['r']], (int) $anchor_port['edge'], $anchor);
    $target_hex = RoomPlacementTransformer::neighbor(['q' => $exit['q'], 'r' => $exit['r']], $exit['edge']);
    $target_edge = RoomPlacementTransformer::opposite($exit['edge']);

    $occupied = [];
    foreach ((array) ($model['occupancy'] ?? []) as $key => $ids) {
      $occupied[$key] = $ids;
    }

    $entries = array_values(array_filter((array) $library['ports'], static fn(array $p): bool => $p['kind'] === 'entry'));
    if ($entry_filter !== '') {
      $entries = array_values(array_filter($entries, static fn(array $p): bool => $p['port_id'] === $entry_filter));
      if ($entries === []) {
        throw new \OutOfBoundsException(sprintf('entry_port_not_found:%s:%s', $room_id, $entry_filter));
      }
    }
    if ($entries === []) {
      throw new \DomainException(sprintf('room_has_no_entry_ports:%s', $room_id));
    }

    $candidates = [];
    $rejected = [];
    foreach ($entries as $entry) {
      for ($steps = 0; $steps < RoomPlacementTransformer::EDGE_COUNT; $steps++) {
        if (RoomPlacementTransformer::rotateEdge((int) $entry['edge'], $steps) !== $target_edge) {
          continue;
        }
        $rotated = RoomPlacementTransformer::rotate((int) $entry['q'], (int) $entry['r'], $steps);
        $placement = [
          'origin' => ['q' => $target_hex['q'] - $rotated['q'], 'r' => $target_hex['r'] - $rotated['r']],
          'rotation_steps' => $steps,
        ];
        $overlaps = [];
        $out_of_bounds = FALSE;
        foreach ((array) $library['footprint'] as $hex) {
          $level = RoomPlacementTransformer::toLevel($hex, $placement);
          if (abs($level['q']) > DungeonEditorService::AXIAL_BOUND || abs($level['r']) > DungeonEditorService::AXIAL_BOUND) {
            $out_of_bounds = TRUE;
          }
          $key = RoomPlacementTransformer::hexKey($level);
          if (isset($occupied[$key])) {
            $overlaps[] = ['hex' => $level, 'placement_ids' => $occupied[$key]];
          }
        }
        $candidate = [
          'entry_port_id' => $entry['port_id'],
          'origin' => $placement['origin'],
          'rotation_steps' => $steps,
        ];
        if ($overlaps !== [] || $out_of_bounds) {
          $rejected[] = $candidate + ['overlaps' => $overlaps, 'out_of_bounds' => $out_of_bounds];
          continue;
        }
        $candidates[] = $candidate;
      }
    }

    $result = [
      'room_id' => $room_id,
      'version_id' => $library['version_id'],
      'anchor' => [
        'placement_id' => $anchor_id,
        'port_id' => $anchor_port_id,
        'level_hex' => ['q' => $exit['q'], 'r' => $exit['r']],
        'level_edge' => $exit['edge'],
      ],
      'required_entry' => ['level_hex' => $target_hex, 'level_edge' => $target_edge],
      'candidate_count' => count($candidates),
      'candidates' => $candidates,
      'rejected' => $rejected,
    ];
    if ($candidates === []) {
      throw new \DomainException(sprintf('no_sealed_placement:%s:%s:%s', $room_id, $anchor_id, $anchor_port_id));
    }

    $index = array_key_exists('candidate', $arguments) ? EditorGmToolContext::requireInt($arguments, 'candidate') : 1;
    if ($index < 1 || $index > count($candidates)) {
      throw new \InvalidArgumentException(sprintf('candidate_out_of_range:%d', $index));
    }
    $chosen = $candidates[$index - 1];
    $payload = [
      'room_id' => $room_id,
      'version_id' => $library['version_id'],
      'origin' => $chosen['origin'],
      'rotation_steps' => $chosen['rotation_steps'],
    ];
    if (isset($arguments['label'])) {
      $payload['label'] = (string) $arguments['label'];
    }

    $result['chosen'] = $chosen;
    $result['follow_up'] = sprintf(
      'After applying, call plan_port_links to link exit "%s" on %s to entry "%s" on the new placement.',
      $anchor_port_id,
      $anchor_id,
      $chosen['entry_port_id']
    );
    $result['command_plan'] = DungeonPlanSteps::plan($context, [
      DungeonPlanSteps::step(1, 'place_room', $payload, sprintf(
        'Place "%s" at (%d, %d) rotated %d so entry "%s" seals against exit "%s" on "%s".',
        $library['name'],
        $chosen['origin']['q'],
        $chosen['origin']['r'],
        $chosen['rotation_steps'],
        $chosen['entry_port_id'],
        $anchor_port_id,
        $anchor['label']
      )),
    ]);
    return $result;
  }

}
