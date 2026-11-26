<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\PlacementRules;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Finds and fixes placements a shape skipped.
 *
 * A bulk placement never stops on a blocked hex; it records a gap. These
 * tools list the gaps and plan their repair. Repair plans carry the gap ID on
 * each item, so a placed gap closes and a still-blocked one is updated in
 * place instead of duplicated.
 */
final class PlacementGapsTool implements EditorGmToolInterface {

  public const NAMES = ['list_placement_gaps', 'retry_placement_gaps', 'nudge_placement_gaps', 'resolve_placement_gaps'];

  public function __construct(private readonly string $name) {
    if (!in_array($name, self::NAMES, TRUE)) {
      throw new \LogicException('placement_gaps_tool_invalid:' . $name);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $gap_ids = EditorGmToolDefinition::argument('gap_ids', 'array', FALSE, 'Gap IDs from list_placement_gaps; default every open gap (or every open gap of operation_id).');
    $operation = EditorGmToolDefinition::argument('operation_id', 'string', FALSE, 'Limit to the gaps of one operation.');
    [$summary, $arguments] = match ($this->name) {
      'list_placement_gaps' => ['List the placements a shape skipped, with the reason and what blocked each. Read-only.', [$operation, EditorGmToolDefinition::argument('status', 'string', FALSE, 'open (default), dismissed or all.')]],
      'retry_placement_gaps' => ['Plan another attempt at skipped placements on the same hexes, for after the blocker was changed.', [$gap_ids, $operation]],
      'nudge_placement_gaps' => ['Plan skipped placements onto the nearest free hex within a radius.', [$gap_ids, $operation, EditorGmToolDefinition::argument('radius', 'integer', FALSE, 'Search radius in hexes (default 2, at most 10).')]],
      'resolve_placement_gaps' => ['Close skipped placements: dismiss them with a reason, or remove the object blocking each and place it.', [
        $gap_ids,
        $operation,
        EditorGmToolDefinition::argument('action', 'string', TRUE, 'dismiss or remove_blocker_and_place.'),
        EditorGmToolDefinition::argument('reason', 'string', FALSE, 'Required to dismiss: why the skipped placements are acceptable.'),
      ]],
    };
    $reads = $this->name === 'list_placement_gaps';
    return new EditorGmToolDefinition(
      $this->name,
      $reads ? EditorGmToolDefinition::FAMILY_CONTEXT : EditorGmToolDefinition::FAMILY_PLANNING,
      $summary . ($reads ? '' : ' Returns a command plan; run it with apply_room_commands.'),
      FALSE,
      $reads ? 'draft room payload' : 'planning only; execution requires apply_room_commands',
      $arguments,
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    $room = $context->room();
    $all = array_values((array) ($room['metadata']['placement_gaps'] ?? []));

    if ($this->name === 'list_placement_gaps') {
      $status = (string) ($arguments['status'] ?? 'open');
      if (!in_array($status, ['open', 'dismissed', 'all'], TRUE)) {
        throw new \InvalidArgumentException('gap_status_invalid');
      }
      $gaps = array_values(array_filter($all, static fn(array $g): bool => ($status === 'all' || ($g['status'] ?? 'open') === $status)
        && (!isset($arguments['operation_id']) || $g['operation_id'] === $arguments['operation_id'])));
      $by_reason = [];
      foreach ($gaps as $gap) {
        $by_reason[$gap['reason']] = ($by_reason[$gap['reason']] ?? 0) + 1;
      }
      return ['gap_count' => count($gaps), 'by_reason' => $by_reason, 'gaps' => $gaps];
    }

    $gaps = $this->selected($all, $arguments);
    $steps = match ($this->name) {
      'retry_placement_gaps' => $this->retry($gaps),
      'nudge_placement_gaps' => $this->nudge($gaps, $room, min(10, max(1, (int) ($arguments['radius'] ?? 2)))),
      'resolve_placement_gaps' => $this->resolve($gaps, $arguments),
    };
    if ($steps === []) {
      throw new \DomainException('planning_produced_no_steps');
    }
    $draft = $context->draft();
    return [
      'goal' => $this->name,
      'gap_count' => count($gaps),
      'command_plan' => [
        'schema_version' => 'editor-gm-command-plan-v1',
        'draft_id' => $context->draftId,
        'base_revision' => (int) ($draft['revision'] ?? 0),
        'steps' => $steps,
      ],
    ];
  }

  /**
   * @return list<array>
   */
  private function selected(array $all, array $arguments): array {
    $open = array_values(array_filter($all, static fn(array $g): bool => ($g['status'] ?? 'open') === 'open'));
    if (isset($arguments['gap_ids'])) {
      $wanted = (array) $arguments['gap_ids'];
      $known = array_column($open, 'gap_id');
      foreach ($wanted as $id) {
        if (!in_array($id, $known, TRUE)) {
          throw new \DomainException('placement_gap_not_found:' . $id);
        }
      }
      return array_values(array_filter($open, static fn(array $g): bool => in_array($g['gap_id'], $wanted, TRUE)));
    }
    if (isset($arguments['operation_id'])) {
      $open = array_values(array_filter($open, static fn(array $g): bool => $g['operation_id'] === $arguments['operation_id']));
    }
    if ($open === []) {
      throw new \DomainException('no_open_placement_gaps');
    }
    return $open;
  }

  /**
   * Splits gaps into place items and move items, each carrying its gap ID.
   *
   * @param list<array> $gaps
   * @param array<string, array{q: int, r: int}> $targets
   *   Replacement hex per gap ID; a gap not listed keeps its own hex.
   *
   * @return list<array>
   */
  private function repairSteps(array $gaps, array $targets = []): array {
    $place = [];
    $move = [];
    foreach ($gaps as $gap) {
      $hex = $targets[$gap['gap_id']] ?? $gap['hex'];
      if (isset($gap['instance_id'])) {
        $move[] = ['gap_id' => $gap['gap_id'], 'instance_id' => $gap['instance_id'], 'anchor_hex' => $hex];
      }
      else {
        $place[] = ['gap_id' => $gap['gap_id'], 'definition_ref' => $gap['definition_ref'], 'anchor_hex' => $hex, 'facing' => (int) ($gap['facing'] ?? 0)];
      }
    }
    $steps = [];
    if ($place !== []) {
      $steps = array_merge($steps, PlanRoomCommandsTool::bulkPlaceSteps($place, 'retry_gaps'));
    }
    if ($move !== []) {
      $steps[] = PlanRoomCommandsTool::stepOf(0, 'move_objects_bulk', ['operation' => 'retry_gaps', 'items' => $move], sprintf('Retry %d refused moves.', count($move)));
    }
    foreach ($steps as $i => $step) {
      $steps[$i]['step'] = $i + 1;
    }
    return $steps;
  }

  private function retry(array $gaps): array {
    return $this->repairSteps($gaps);
  }

  private function nudge(array $gaps, array $room, int $radius): array {
    $terrain = PlacementRules::terrainByHex($room);
    $clear = PlacementRules::keptClear($room, []);
    $moving = array_values(array_filter(array_column($gaps, 'instance_id')));
    $occupied = PlacementRules::solidOccupancy($room, $moving);
    // Hexes other open gaps still want are not offered to this one.
    $wanted = [];
    foreach ((array) ($room['metadata']['placement_gaps'] ?? []) as $other) {
      if (($other['status'] ?? 'open') === 'open') {
        $wanted[HexShapes::key($other['hex'])] = TRUE;
      }
    }
    $targets = [];
    foreach ($gaps as $gap) {
      $family = (string) $gap['definition_ref']['family'];
      $candidates = HexShapes::disk($gap['hex'], $radius);
      usort($candidates, static fn(array $a, array $b): int => [HexShapes::distance($gap['hex'], $a), $a['r'], $a['q']] <=> [HexShapes::distance($gap['hex'], $b), $b['r'], $b['q']]);
      foreach ($candidates as $candidate) {
        $key = HexShapes::key($candidate);
        if (isset($wanted[$key]) || PlacementRules::refusal($terrain, $clear, $occupied, $family, $key) !== NULL) {
          continue;
        }
        $targets[$gap['gap_id']] = $candidate;
        if (in_array($family, ['actor', 'obstacle'], TRUE)) {
          $occupied[$key] = $gap['gap_id'];
        }
        break;
      }
    }
    if ($targets === []) {
      throw new \DomainException('no_free_hex_within_radius');
    }
    return $this->repairSteps(array_values(array_filter($gaps, static fn(array $g): bool => isset($targets[$g['gap_id']]))), $targets);
  }

  private function resolve(array $gaps, array $arguments): array {
    $action = EditorGmToolContext::requireString($arguments, 'action');
    if ($action === 'dismiss') {
      $reason = EditorGmToolContext::requireString($arguments, 'reason');
      return [PlanRoomCommandsTool::stepOf(1, 'dismiss_placement_gaps', ['gap_ids' => array_column($gaps, 'gap_id'), 'reason' => $reason], sprintf('Accept %d skipped placements: %s', count($gaps), $reason))];
    }
    if ($action !== 'remove_blocker_and_place') {
      throw new \InvalidArgumentException('gap_action_invalid');
    }
    $blockers = [];
    foreach ($gaps as $gap) {
      if ($gap['reason'] !== 'hex_blocked_by_object') {
        throw new \DomainException(sprintf('gap_blocker_not_removable:%s:%s', $gap['gap_id'], $gap['reason']));
      }
      $blockers[] = $gap['blocker'];
    }
    $steps = [PlanRoomCommandsTool::stepOf(1, 'remove_objects_bulk', ['instance_ids' => array_values(array_unique($blockers))], sprintf('Remove %d blocking objects.', count(array_unique($blockers))))];
    foreach ($this->repairSteps($gaps) as $step) {
      $step['step'] = count($steps) + 1;
      $steps[] = $step;
    }
    return $steps;
  }

}
