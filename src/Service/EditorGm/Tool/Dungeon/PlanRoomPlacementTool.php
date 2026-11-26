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
        EditorGmToolDefinition::argument('anchor_placement_id', 'string', FALSE, 'Existing placement whose exit port the new room must seal against. Use with anchor_port_id.'),
        EditorGmToolDefinition::argument('anchor_port_id', 'string', FALSE, 'Exit port id on the anchor placement. Use with anchor_placement_id.'),
        EditorGmToolDefinition::argument('anchor_room_id', 'string', FALSE, 'Resolve the existing placement and first unlinked exit port from this published room ID.'),
        EditorGmToolDefinition::argument('near_room_id', 'string', FALSE, 'Place in a non-overlapping position near this published room without requiring a port seal.'),
        EditorGmToolDefinition::argument('entry_port_id', 'string', FALSE, 'Restrict to one entry port of the new room.'),
        EditorGmToolDefinition::argument('candidate', 'integer', FALSE, '1-based index into the returned candidates to plan (default 1).'),
        EditorGmToolDefinition::argument('label', 'string', FALSE, 'Placement label.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $room_id = EditorGmToolContext::requireString($arguments, 'room_id');
    $entry_filter = isset($arguments['entry_port_id']) ? trim((string) $arguments['entry_port_id']) : '';

    $model = $context->model();
    $library = DungeonPlanSteps::libraryRoom($context, $room_id);
    $anchor_room_id = trim((string) ($arguments['anchor_room_id'] ?? ''));
    $near_room_id = trim((string) ($arguments['near_room_id'] ?? ''));
    $anchor_id = trim((string) ($arguments['anchor_placement_id'] ?? ''));
    $anchor_port_id = trim((string) ($arguments['anchor_port_id'] ?? ''));
    if ($near_room_id !== '' && ($anchor_room_id !== '' || $anchor_id !== '' || $anchor_port_id !== '')) {
      throw new \InvalidArgumentException('near_room_id_cannot_mix_sealed_anchor');
    }
    if ($anchor_room_id !== '') {
      if ($anchor_id !== '' || $anchor_port_id !== '' || $near_room_id !== '') {
        throw new \InvalidArgumentException('anchor_room_id_cannot_mix_explicit_anchor');
      }
      $matches = array_values(array_filter(
        (array) ($model['placements'] ?? []),
        static fn(mixed $placement): bool => is_array($placement)
          && ($placement['room_id'] ?? '') === $anchor_room_id,
      ));
      if (count($matches) !== 1) {
        throw new \DomainException('anchor_room_placement_ambiguous:' . $anchor_room_id);
      }
      $anchor_id = (string) ($matches[0]['placement_id'] ?? '');
    }
    if ($anchor_id === '') {
      if ($near_room_id !== '') {
        return $this->planNearRoom($room_id, $near_room_id, $library, $model, $context, $arguments);
      }
      throw new \InvalidArgumentException('anchor_placement_id_required');
    }
    $anchor = DungeonPlanSteps::placement($model, $anchor_id);
    if (!$anchor['resolved']) {
      throw new \DomainException(sprintf('placement_unresolved:%s', $anchor_id));
    }

    $anchor_port = NULL;
    if ($anchor_port_id === '' && $anchor_room_id !== '') {
      $linked = [];
      foreach ((array) ($model['port_links'] ?? []) as $link) {
        if (($link['from']['placement_id'] ?? '') === $anchor_id) {
          $linked[(string) ($link['from']['port_id'] ?? '')] = TRUE;
        }
      }
      foreach ((array) $anchor['room']['ports'] as $port) {
        if (($port['kind'] ?? '') === 'exit' && !isset($linked[(string) ($port['port_id'] ?? '')])) {
          $anchor_port_id = (string) $port['port_id'];
          break;
        }
      }
      if ($anchor_port_id === '') {
        throw new \DomainException('anchor_room_has_no_unlinked_exit:' . $anchor_room_id);
      }
    }
    if ($anchor_port_id === '') {
      throw new \InvalidArgumentException('anchor_port_id_required');
    }
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

  private function planNearRoom(
    string $room_id,
    string $anchor_room_id,
    array $library,
    array $model,
    DungeonEditorGmToolContext $context,
    array $arguments,
  ): array {
    $matches = array_values(array_filter(
      (array) ($model['placements'] ?? []),
      static fn(mixed $placement): bool => is_array($placement)
        && ($placement['room_id'] ?? '') === $anchor_room_id,
    ));
    if (count($matches) !== 1) {
      throw new \DomainException('near_room_placement_ambiguous:' . $anchor_room_id);
    }
    $bounds = (array) ($matches[0]['level_bounds'] ?? []);
    if ($bounds === []) {
      throw new \DomainException('near_room_bounds_unresolved:' . $anchor_room_id);
    }
    $center_q = (int) floor(((int) $bounds['min_q'] + (int) $bounds['max_q']) / 2);
    $center_r = (int) floor(((int) $bounds['min_r'] + (int) $bounds['max_r']) / 2);
    $occupied = (array) ($model['occupancy'] ?? []);
    $candidates = [];
    foreach (range(0, RoomPlacementTransformer::EDGE_COUNT - 1) as $rotation) {
      $rotated = [];
      $min_q = $max_q = $min_r = $max_r = NULL;
      foreach ((array) ($library['footprint'] ?? []) as $hex) {
        $point = RoomPlacementTransformer::rotate((int) $hex['q'], (int) $hex['r'], $rotation);
        $rotated[] = $point;
        $min_q = $min_q === NULL ? $point['q'] : min($min_q, $point['q']);
        $max_q = $max_q === NULL ? $point['q'] : max($max_q, $point['q']);
        $min_r = $min_r === NULL ? $point['r'] : min($min_r, $point['r']);
        $max_r = $max_r === NULL ? $point['r'] : max($max_r, $point['r']);
      }
      $origins = [
        ['q' => (int) $bounds['max_q'] + 1 - $min_q, 'r' => $center_r - (int) floor(($min_r + $max_r) / 2)],
        ['q' => (int) $bounds['min_q'] - 1 - $max_q, 'r' => $center_r - (int) floor(($min_r + $max_r) / 2)],
        ['q' => $center_q - (int) floor(($min_q + $max_q) / 2), 'r' => (int) $bounds['max_r'] + 1 - $min_r],
        ['q' => $center_q - (int) floor(($min_q + $max_q) / 2), 'r' => (int) $bounds['min_r'] - 1 - $max_r],
      ];
      foreach ($origins as $origin) {
        $overlaps = FALSE;
        foreach ($rotated as $point) {
          $key = RoomPlacementTransformer::hexKey([
            'q' => $origin['q'] + $point['q'],
            'r' => $origin['r'] + $point['r'],
          ]);
          if (isset($occupied[$key])) {
            $overlaps = TRUE;
            break;
          }
        }
        if (!$overlaps) {
          $candidates[] = ['origin' => $origin, 'rotation_steps' => $rotation];
        }
      }
    }
    $candidates = array_values(array_unique($candidates, SORT_REGULAR));
    if ($candidates === []) {
      throw new \DomainException('near_room_candidate_not_found:' . $anchor_room_id);
    }
    $chosen_index = isset($arguments['candidate']) ? (int) $arguments['candidate'] - 1 : 0;
    $chosen = $candidates[$chosen_index] ?? NULL;
    if ($chosen === NULL) {
      throw new \OutOfBoundsException('candidate_not_found:' . ($chosen_index + 1));
    }
    $payload = [
      'room_id' => $room_id,
      'version_id' => (string) $library['version_id'],
      'origin' => $chosen['origin'],
      'rotation_steps' => $chosen['rotation_steps'],
    ];
    if (isset($arguments['label'])) {
      $payload['label'] = trim((string) $arguments['label']);
    }
    return [
      'room_id' => $room_id,
      'version_id' => $library['version_id'],
      'near_room_id' => $anchor_room_id,
      'candidate_count' => count($candidates),
      'candidates' => $candidates,
      'chosen' => $chosen,
      'follow_up' => 'Use plan_port_links to connect the new placement to the anchor room if a link is required.',
      'command_plan' => DungeonPlanSteps::plan($context, [
        DungeonPlanSteps::step(
          1,
          'place_room',
          $payload,
          sprintf('Place "%s" near "%s" without overlapping any existing placement.', $library['name'], $anchor_room_id),
        ),
      ]),
    ];
  }

}
