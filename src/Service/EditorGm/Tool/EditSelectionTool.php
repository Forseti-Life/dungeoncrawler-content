<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\PlacementRegion;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Plans edits to a selection of placed objects.
 *
 * A selection is stateless: instance IDs, or a filter (family, definition_id,
 * a disc of hexes). Every edit runs through the bulk commands, so a blocked
 * target becomes a gap rather than a failure.
 */
final class EditSelectionTool implements EditorGmToolInterface {

  public const NAMES = ['move_selection', 'rotate_selection', 'mirror_selection', 'copy_selection', 'remove_selection', 'replace_selection'];

  public function __construct(private readonly string $name) {
    if (!in_array($name, self::NAMES, TRUE)) {
      throw new \LogicException('edit_selection_tool_invalid:' . $name);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $hex = 'Hex as {q, r}.';
    $selection = [
      EditorGmToolDefinition::argument('instance_ids', 'array', FALSE, 'Placements to edit. Omit to select by filter.'),
      EditorGmToolDefinition::argument('family', 'string', FALSE, 'Filter: obstacle or item (actors are never edited here).'),
      EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Filter: one definition.'),
      EditorGmToolDefinition::argument('center', 'object', FALSE, 'Filter: disc centre. ' . $hex),
      EditorGmToolDefinition::argument('radius', 'integer', FALSE, 'Filter: disc radius (default 0).'),
    ];
    [$summary, $extra] = match ($this->name) {
      'move_selection' => ['Move the selection by an offset in axial hexes.', [
        EditorGmToolDefinition::argument('dq', 'integer', TRUE, 'Offset in q.'),
        EditorGmToolDefinition::argument('dr', 'integer', TRUE, 'Offset in r.'),
      ]],
      'rotate_selection' => ['Rotate the selection about a pivot in 60 degree steps, turning each object to match.', [
        EditorGmToolDefinition::argument('steps', 'integer', TRUE, 'Sixth-turns clockwise, 1 to 5.'),
        EditorGmToolDefinition::argument('pivot', 'object', FALSE, 'Pivot hex (default: the selection centre). ' . $hex),
      ]],
      'mirror_selection' => ['Mirror the selection across an axis through a pivot.', [
        EditorGmToolDefinition::argument('axis', 'integer', TRUE, 'Axis 0 to 5.'),
        EditorGmToolDefinition::argument('pivot', 'object', FALSE, 'Pivot hex (default: the selection centre). ' . $hex),
      ]],
      'copy_selection' => ['Place copies of the selection shifted by an offset.', [
        EditorGmToolDefinition::argument('dq', 'integer', TRUE, 'Offset in q.'),
        EditorGmToolDefinition::argument('dr', 'integer', TRUE, 'Offset in r.'),
      ]],
      'remove_selection' => ['Remove the selection.', []],
      'replace_selection' => ['Swap the selection for another obstacle or item definition, keeping hex and facing.', [
        EditorGmToolDefinition::argument('new_family', 'string', TRUE, 'obstacle or item.'),
        EditorGmToolDefinition::argument('new_definition_id', 'string', TRUE, 'Definition to use.'),
        EditorGmToolDefinition::argument('new_version', 'string', TRUE, 'Definition version.'),
      ]],
    };
    return new EditorGmToolDefinition(
      $this->name,
      EditorGmToolDefinition::FAMILY_PLANNING,
      $summary . ' Returns a command plan; run it with apply_room_commands.',
      FALSE,
      'planning only; execution requires apply_room_commands',
      array_merge($selection, $extra),
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    $room = $context->room();
    $picked = $this->select($room, $arguments);
    $hexes = array_column($picked, 'anchor_hex');
    $count = count($picked);
    $steps = match ($this->name) {
      'move_selection' => [PlanRoomCommandsTool::stepOf(1, 'move_objects_bulk', [
        'operation' => $this->name,
        'items' => $this->shifted($picked, (int) $arguments['dq'], (int) $arguments['dr']),
      ], sprintf('Move %d objects.', $count))],
      'rotate_selection' => $this->transform($picked, $hexes, $arguments, TRUE),
      'mirror_selection' => $this->transform($picked, $hexes, $arguments, FALSE),
      'copy_selection' => PlanRoomCommandsTool::bulkPlaceSteps(array_map(static fn(array $p): array => [
        'definition_ref' => $p['definition_ref'],
        'anchor_hex' => ['q' => $p['anchor_hex']['q'] + (int) $arguments['dq'], 'r' => $p['anchor_hex']['r'] + (int) $arguments['dr']],
        'facing' => (int) ($p['facing'] ?? 0),
      ], $picked), $this->name),
      'remove_selection' => [PlanRoomCommandsTool::stepOf(1, 'remove_objects_bulk', ['instance_ids' => array_column($picked, 'instance_id')], sprintf('Remove %d objects.', $count))],
      'replace_selection' => [PlanRoomCommandsTool::stepOf(1, 'replace_objects_bulk', [
        'instance_ids' => array_column($picked, 'instance_id'),
        'definition_ref' => [
          'family' => EditorGmToolContext::requireString($arguments, 'new_family'),
          'definition_id' => EditorGmToolContext::requireString($arguments, 'new_definition_id'),
          'version' => EditorGmToolContext::requireString($arguments, 'new_version'),
        ],
      ], sprintf('Replace %d objects.', $count))],
    };
    foreach ($steps as $i => $step) {
      $steps[$i]['step'] = $i + 1;
    }
    return [
      'goal' => $this->name,
      'selected_count' => $count,
      'command_plan' => [
        'schema_version' => 'editor-gm-command-plan-v1',
        'draft_id' => $context->draftId,
        'base_revision' => (int) ($context->draft()['revision'] ?? 0),
        'steps' => $steps,
      ],
    ];
  }

  /**
   * @return list<array>
   */
  private function select(array $room, array $arguments): array {
    $ids = isset($arguments['instance_ids']) ? (array) $arguments['instance_ids'] : NULL;
    $near = NULL;
    if (isset($arguments['center'])) {
      $near = array_flip(array_map([HexShapes::class, 'key'], HexShapes::disk($arguments['center'], max(0, (int) ($arguments['radius'] ?? 0)))));
    }
    if ($ids === NULL && !isset($arguments['family']) && !isset($arguments['definition_id']) && $near === NULL) {
      throw new \InvalidArgumentException('selection_required');
    }
    $picked = [];
    foreach ((array) ($room['placements'] ?? []) as $p) {
      $ref = $p['definition_ref'];
      if (!in_array($ref['family'], ['obstacle', 'item'], TRUE)
        || ($ids !== NULL && !in_array($p['instance_id'], $ids, TRUE))
        || (isset($arguments['family']) && $ref['family'] !== $arguments['family'])
        || (isset($arguments['definition_id']) && $ref['definition_id'] !== $arguments['definition_id'])
        || ($near !== NULL && !isset($near[HexShapes::key($p['anchor_hex'])]))) {
        continue;
      }
      $picked[] = $p;
    }
    if ($ids !== NULL) {
      $found = array_column($picked, 'instance_id');
      foreach ($ids as $id) {
        if (!in_array($id, $found, TRUE)) {
          throw new \DomainException('placement_not_found:' . $id);
        }
      }
    }
    if ($picked === []) {
      throw new \DomainException('selection_empty');
    }
    return $picked;
  }

  private function shifted(array $picked, int $dq, int $dr): array {
    return array_map(static fn(array $p): array => [
      'instance_id' => $p['instance_id'],
      'anchor_hex' => ['q' => $p['anchor_hex']['q'] + $dq, 'r' => $p['anchor_hex']['r'] + $dr],
    ], $picked);
  }

  private function transform(array $picked, array $hexes, array $arguments, bool $rotate): array {
    $pivot = isset($arguments['pivot']) ? $arguments['pivot'] : PlacementRegion::centre($hexes);
    if ($rotate) {
      $steps = (int) ($arguments['steps'] ?? 0);
      if ($steps < 1 || $steps > 5) {
        throw new \InvalidArgumentException('rotate_steps_invalid');
      }
      $targets = HexShapes::rotateAbout($hexes, $pivot, $steps);
    }
    else {
      $axis = (int) ($arguments['axis'] ?? -1);
      $targets = HexShapes::mirror($hexes, $pivot, $axis);
    }
    $items = [];
    foreach ($picked as $i => $p) {
      $item = ['instance_id' => $p['instance_id'], 'anchor_hex' => $targets[$i]];
      if ($rotate) {
        $item['facing'] = (((int) ($p['facing'] ?? 0)) + $steps) % 6;
      }
      $items[] = $item;
    }
    return [PlanRoomCommandsTool::stepOf(1, 'move_objects_bulk', ['operation' => $this->name, 'items' => $items], sprintf('%s %d objects.', $rotate ? 'Rotate' : 'Mirror', count($items)))];
  }

}
