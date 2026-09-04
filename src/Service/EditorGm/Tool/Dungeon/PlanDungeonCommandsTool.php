<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Turns a structured authoring goal into an ordered dungeon command plan.
 *
 * Every goal grounds on the live read model: placement ids and region ids
 * must already exist, rooms must be published. Nothing is guessed; a goal
 * that cannot be planned exactly hard-fails with the reason.
 */
final class PlanDungeonCommandsTool implements EditorGmToolInterface {

  private const GOALS = [
    'set_metadata',
    'place_rooms',
    'arrange_placements',
    'remove_placements',
    'label_placements',
    'set_level_entrance',
    'define_region',
    'update_region',
    'remove_regions',
    'retarget_placements',
    'update_links',
    'unlink_ports',
    'undo',
    'redo',
  ];

  public const GOAL_PARAMETERS = [
    'set_metadata' => 'at least one of name, description, depth, theme',
    'place_rooms' => 'placements: [{room_id, origin: {q, r}, rotation_steps (0-5), label (optional)}] (required); version resolves to the published version',
    'arrange_placements' => 'moves: [{placement_id, origin: {q, r} (optional), rotation_steps (optional)}] (required, each needs at least one of origin/rotation_steps)',
    'remove_placements' => 'placement_ids: [string] (required)',
    'label_placements' => 'labels: [{placement_id, label (optional), tags (optional)}] (required)',
    'set_level_entrance' => 'placement_id (required), is_level_entrance (boolean, default true)',
    'define_region' => 'region_id (required, [a-z0-9][a-z0-9_-]*), name (required), placement_ids: [string] (required, non-empty), description (optional)',
    'update_region' => 'region_id (required), changes: {name, placement_ids, description, environmental_effects, ambient_hazard_level} (required, at least one key)',
    'remove_regions' => 'region_ids: [string] (required)',
    'retarget_placements' => 'placement_ids: [string] (required); each is repinned to the current published version of its room',
    'update_links' => 'links: [{link_id, changes: {kind, direction, default_state, travel_cost, requirements, description, tags}}] (required)',
    'unlink_ports' => 'link_ids: [string] (required)',
    'undo' => 'target_command_id (required): command id from the draft history to undo',
    'redo' => 'target_command_id (required): undo command id to redo',
  ];

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_dungeon_commands',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Turn a structured authoring goal into an ordered, previewable dungeon command plan. Goals: ' . implode(', ', self::GOALS) . '. For sealed adjacency use plan_room_placement and plan_port_links.',
      FALSE,
      'planning only; execution requires apply_dungeon_commands',
      [
        EditorGmToolDefinition::argument('goal', 'string', TRUE, 'Goal type: ' . implode('|', self::GOALS) . '.'),
        EditorGmToolDefinition::argument(
          'parameters',
          'array',
          TRUE,
          'Flat keyed object of goal-specific parameters, never a list. Accepted keys per goal: '
          . implode('; ', array_map(
            static fn(string $goal, string $shape): string => $goal . ' => ' . $shape,
            array_keys(self::GOAL_PARAMETERS),
            self::GOAL_PARAMETERS,
          )) . '.',
        ),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $goal = EditorGmToolContext::requireString($arguments, 'goal');
    if (!in_array($goal, self::GOALS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('planning_goal_unsupported:%s', $goal));
    }
    $parameters = EditorGmToolContext::requireArray($arguments, 'parameters');
    $model = $context->model();

    $steps = match ($goal) {
      'set_metadata' => $this->planSetMetadata($parameters),
      'place_rooms' => $this->planPlaceRooms($parameters, $context),
      'arrange_placements' => $this->planArrange($parameters, $model),
      'remove_placements' => $this->planRemove($parameters, $model),
      'label_placements' => $this->planLabels($parameters, $model),
      'set_level_entrance' => $this->planEntrance($parameters, $model),
      'define_region' => $this->planRegion($parameters, $model),
      'update_region' => $this->planUpdateRegion($parameters, $model),
      'remove_regions' => $this->planRemoveRegions($parameters, $model),
      'retarget_placements' => $this->planRetarget($parameters, $model, $context),
      'update_links' => $this->planUpdateLinks($parameters, $model),
      'unlink_ports' => $this->planUnlink($parameters, $model),
      'undo', 'redo' => $this->planHistory($goal, $parameters),
    };

    return [
      'goal' => $goal,
      'command_plan' => DungeonPlanSteps::plan($context, $steps),
    ];
  }

  private function planSetMetadata(array $parameters): array {
    $changes = [];
    foreach (['name', 'description', 'depth', 'theme'] as $field) {
      if (array_key_exists($field, $parameters)) {
        $changes[$field] = $parameters[$field];
      }
    }
    if ($changes === []) {
      throw new \InvalidArgumentException('metadata_changes_required');
    }
    return [DungeonPlanSteps::step(1, 'set_dungeon_metadata', ['changes' => $changes], 'Update dungeon metadata: ' . implode(', ', array_keys($changes)) . '.')];
  }

  private function planPlaceRooms(array $parameters, DungeonEditorGmToolContext $context): array {
    $placements = $this->requireList($parameters, 'placements');
    $steps = [];
    foreach ($placements as $index => $spec) {
      $room_id = EditorGmToolContext::requireString($spec, 'room_id');
      $room = DungeonPlanSteps::libraryRoom($context, $room_id);
      $payload = [
        'room_id' => $room_id,
        'version_id' => $room['version_id'],
        'origin' => $this->requireOrigin($spec),
        'rotation_steps' => $this->requireRotation($spec, TRUE),
      ];
      if (isset($spec['label'])) {
        $payload['label'] = (string) $spec['label'];
      }
      $steps[] = DungeonPlanSteps::step(
        count($steps) + 1,
        'place_room',
        $payload,
        sprintf('Place "%s" (version %s) at (%d, %d) rotated %d steps.', $room['name'], $room['version'], $payload['origin']['q'], $payload['origin']['r'], $payload['rotation_steps'])
      );
    }
    return $steps;
  }

  private function planArrange(array $parameters, array $model): array {
    $steps = [];
    foreach ($this->requireList($parameters, 'moves') as $move) {
      $placement_id = EditorGmToolContext::requireString($move, 'placement_id');
      $placement = DungeonPlanSteps::placement($model, $placement_id);
      $planned = FALSE;
      if (array_key_exists('origin', $move)) {
        $origin = $this->requireOrigin($move);
        $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'move_room_placement', ['placement_id' => $placement_id, 'origin' => $origin], sprintf('Move "%s" to (%d, %d).', $placement['label'], $origin['q'], $origin['r']));
        $planned = TRUE;
      }
      if (array_key_exists('rotation_steps', $move)) {
        $rotation = $this->requireRotation($move, TRUE);
        $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'rotate_room_placement', ['placement_id' => $placement_id, 'rotation_steps' => $rotation], sprintf('Rotate "%s" to %d steps (absolute).', $placement['label'], $rotation));
        $planned = TRUE;
      }
      if (!$planned) {
        throw new \InvalidArgumentException(sprintf('move_requires_origin_or_rotation:%s', $placement_id));
      }
    }
    return $steps;
  }

  private function planRemove(array $parameters, array $model): array {
    $steps = [];
    foreach ($this->requireStringList($parameters, 'placement_ids') as $placement_id) {
      $placement = DungeonPlanSteps::placement($model, $placement_id);
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'remove_room_placement', ['placement_id' => $placement_id], sprintf('Remove "%s" and any links or region memberships that reference it.', $placement['label']));
    }
    return $steps;
  }

  private function planLabels(array $parameters, array $model): array {
    $steps = [];
    foreach ($this->requireList($parameters, 'labels') as $spec) {
      $placement_id = EditorGmToolContext::requireString($spec, 'placement_id');
      DungeonPlanSteps::placement($model, $placement_id);
      $changes = [];
      if (array_key_exists('label', $spec)) {
        $changes['label'] = (string) $spec['label'];
      }
      if (array_key_exists('tags', $spec)) {
        if (!is_array($spec['tags'])) {
          throw new \InvalidArgumentException('tags_must_be_list');
        }
        $changes['tags'] = array_values($spec['tags']);
      }
      if ($changes === []) {
        throw new \InvalidArgumentException(sprintf('label_changes_required:%s', $placement_id));
      }
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'set_placement_metadata', ['placement_id' => $placement_id, 'changes' => $changes], 'Update placement metadata: ' . implode(', ', array_keys($changes)) . '.');
    }
    return $steps;
  }

  private function planEntrance(array $parameters, array $model): array {
    $placement_id = EditorGmToolContext::requireString($parameters, 'placement_id');
    $placement = DungeonPlanSteps::placement($model, $placement_id);
    $flag = array_key_exists('is_level_entrance', $parameters) ? $parameters['is_level_entrance'] : TRUE;
    if (!is_bool($flag)) {
      throw new \InvalidArgumentException('is_level_entrance_must_be_boolean');
    }
    return [DungeonPlanSteps::step(1, 'set_placement_metadata', ['placement_id' => $placement_id, 'changes' => ['is_level_entrance' => $flag]], sprintf('%s "%s" as a level entrance.', $flag ? 'Mark' : 'Unmark', $placement['label']))];
  }

  private function planRegion(array $parameters, array $model): array {
    $region_id = EditorGmToolContext::requireString($parameters, 'region_id');
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $region_id)) {
      throw new \InvalidArgumentException('region_id_invalid');
    }
    foreach ((array) ($model['regions'] ?? []) as $region) {
      if ($region['region_id'] === $region_id) {
        throw new \DomainException(sprintf('region_id_duplicate:%s', $region_id));
      }
    }
    $name = EditorGmToolContext::requireString($parameters, 'name');
    $placement_ids = $this->requireStringList($parameters, 'placement_ids');
    foreach ($placement_ids as $placement_id) {
      DungeonPlanSteps::placement($model, $placement_id);
    }
    $payload = ['region_id' => $region_id, 'name' => $name, 'placement_ids' => $placement_ids];
    if (isset($parameters['description'])) {
      $payload['description'] = (string) $parameters['description'];
    }
    return [DungeonPlanSteps::step(1, 'add_region', $payload, sprintf('Define region "%s" over %d placements.', $name, count($placement_ids)))];
  }

  private function planUnlink(array $parameters, array $model): array {
    $known = array_column((array) ($model['port_links'] ?? []), 'link_id');
    $steps = [];
    foreach ($this->requireStringList($parameters, 'link_ids') as $link_id) {
      if (!in_array($link_id, $known, TRUE)) {
        throw new \OutOfBoundsException(sprintf('link_not_found:%s', $link_id));
      }
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'unlink_ports', ['link_id' => $link_id], sprintf('Remove link %s.', $link_id));
    }
    return $steps;
  }

  private function planUpdateRegion(array $parameters, array $model): array {
    $region_id = EditorGmToolContext::requireString($parameters, 'region_id');
    $region = $this->region($model, $region_id);
    $changes = $this->requireChanges($parameters, ['name', 'placement_ids', 'description', 'environmental_effects', 'ambient_hazard_level']);
    if (isset($changes['placement_ids'])) {
      if (!is_array($changes['placement_ids']) || $changes['placement_ids'] === []) {
        throw new \InvalidArgumentException('placement_ids_list_required');
      }
      foreach ($changes['placement_ids'] as $placement_id) {
        DungeonPlanSteps::placement($model, (string) $placement_id);
      }
    }
    return [DungeonPlanSteps::step(1, 'update_region', ['region_id' => $region_id, 'changes' => $changes], sprintf('Update region "%s": %s.', $region['name'], implode(', ', array_keys($changes))))];
  }

  private function planRemoveRegions(array $parameters, array $model): array {
    $steps = [];
    foreach ($this->requireStringList($parameters, 'region_ids') as $region_id) {
      $region = $this->region($model, $region_id);
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'remove_region', ['region_id' => $region_id], sprintf('Remove region "%s".', $region['name']));
    }
    return $steps;
  }

  private function planRetarget(array $parameters, array $model, DungeonEditorGmToolContext $context): array {
    $steps = [];
    foreach ($this->requireStringList($parameters, 'placement_ids') as $placement_id) {
      $placement = DungeonPlanSteps::placement($model, $placement_id);
      $room = DungeonPlanSteps::libraryRoom($context, (string) $placement['room_id']);
      if ($room['version_id'] === $placement['version_id']) {
        throw new \DomainException(sprintf('placement_already_current:%s', $placement_id));
      }
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'retarget_room_placement', ['placement_id' => $placement_id, 'version_id' => $room['version_id']], sprintf('Repin "%s" from version %s to published version %s.', $placement['label'], $placement['version_id'], $room['version_id']));
    }
    return $steps;
  }

  private function planUpdateLinks(array $parameters, array $model): array {
    $known = array_column((array) ($model['port_links'] ?? []), 'link_id');
    $steps = [];
    foreach ($this->requireList($parameters, 'links') as $spec) {
      $link_id = EditorGmToolContext::requireString($spec, 'link_id');
      if (!in_array($link_id, $known, TRUE)) {
        throw new \OutOfBoundsException(sprintf('link_not_found:%s', $link_id));
      }
      $changes = $this->requireChanges($spec, ['kind', 'direction', 'default_state', 'travel_cost', 'requirements', 'description', 'tags']);
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'update_port_link', ['link_id' => $link_id, 'changes' => $changes], sprintf('Update link %s: %s.', $link_id, implode(', ', array_keys($changes))));
    }
    return $steps;
  }

  private function planHistory(string $goal, array $parameters): array {
    $target = EditorGmToolContext::requireString($parameters, 'target_command_id');
    return [DungeonPlanSteps::step(1, $goal, ['target_command_id' => $target], sprintf('%s command %s.', ucfirst($goal), $target))];
  }

  private function region(array $model, string $region_id): array {
    foreach ((array) ($model['regions'] ?? []) as $region) {
      if ($region['region_id'] === $region_id) {
        return $region;
      }
    }
    throw new \OutOfBoundsException(sprintf('region_not_found:%s', $region_id));
  }

  private function requireChanges(array $spec, array $allowed): array {
    $changes = $spec['changes'] ?? NULL;
    if (!is_array($changes) || $changes === [] || array_is_list($changes)) {
      throw new \InvalidArgumentException('changes_required');
    }
    foreach (array_keys($changes) as $key) {
      if (!in_array($key, $allowed, TRUE)) {
        throw new \InvalidArgumentException(sprintf('change_key_unsupported:%s', $key));
      }
    }
    return $changes;
  }

  private function requireList(array $parameters, string $key): array {
    $list = $parameters[$key] ?? NULL;
    if (!is_array($list) || $list === [] || !array_is_list($list)) {
      throw new \InvalidArgumentException(sprintf('%s_list_required', $key));
    }
    foreach ($list as $item) {
      if (!is_array($item)) {
        throw new \InvalidArgumentException(sprintf('%s_item_invalid', $key));
      }
    }
    return $list;
  }

  private function requireStringList(array $parameters, string $key): array {
    $list = $parameters[$key] ?? NULL;
    if (!is_array($list) || $list === [] || !array_is_list($list)) {
      throw new \InvalidArgumentException(sprintf('%s_list_required', $key));
    }
    foreach ($list as $item) {
      if (!is_string($item) || $item === '') {
        throw new \InvalidArgumentException(sprintf('%s_item_invalid', $key));
      }
    }
    return array_values(array_unique($list));
  }

  private function requireOrigin(array $spec): array {
    $origin = $spec['origin'] ?? NULL;
    if (!is_array($origin) || !is_int($origin['q'] ?? NULL) || !is_int($origin['r'] ?? NULL)) {
      throw new \InvalidArgumentException('origin_must_be_axial');
    }
    return ['q' => $origin['q'], 'r' => $origin['r']];
  }

  private function requireRotation(array $spec, bool $required): int {
    $steps = $spec['rotation_steps'] ?? NULL;
    if ($steps === NULL && !$required) {
      return 0;
    }
    if (!is_int($steps) || $steps < 0 || $steps > 5) {
      throw new \InvalidArgumentException('rotation_steps_invalid');
    }
    return $steps;
  }

}
