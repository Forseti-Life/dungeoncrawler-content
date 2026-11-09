<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Planning tool: turns a structured authoring goal into Room Editor commands.
 *
 * Planning is deterministic and non-mutating. It only emits native command
 * steps, so an approved plan executes through exactly the same authority as
 * manual editing. Unknown goals hard-fail rather than degrading into a partial
 * or approximate plan.
 */
final class PlanRoomCommandsTool implements EditorGmToolInterface {

  private const GOALS = [
    'set_metadata',
    'expand_footprint',
    'shrink_footprint',
    'retheme_terrain',
    'level_elevation',
    'place_objects',
    'move_objects',
    'rotate_objects',
    'update_object_overrides',
    'duplicate_objects',
    'clear_placements',
    'add_entry_ports',
    'update_entry_ports',
    'remove_entry_ports',
    'add_exit_ports',
    'update_exit_ports',
    'remove_exit_ports',
    'undo',
    'redo',
  ];

  /**
   * Parameter shape accepted per goal.
   *
   * Exposed on the tool definition so a caller is grounded on the exact shape
   * instead of guessing it.
   */
  public const GOAL_PARAMETERS = [
    'set_metadata' => 'at least one of room_id, name, description, room_type, size_category',
    'expand_footprint' => 'hexes: [{q, r}] (required), terrain_type (optional)',
    'shrink_footprint' => 'hexes: [{q, r}] (required; must not contain placements or ports)',
    'retheme_terrain' => 'terrain_type (required), from_terrain_type (optional), hexes: [{q, r}] (optional)',
    'level_elevation' => 'elevation_ft (required), hexes: [{q, r}] (optional)',
    'place_objects' => 'placements: [{family, definition_id, version (optional, default 1.0.0), anchor_hex: {q, r}, facing (optional, 0-5)}] OR top-level family, definition_id, version (optional), facing (optional), hexes: [{q, r}]',
    'move_objects' => 'moves: [{instance_id, anchor_hex: {q, r}}] (required)',
    'rotate_objects' => 'rotations: [{instance_id, facing (0-5)}] (required)',
    'update_object_overrides' => 'updates: [{instance_id, overrides: {...}}] (required)',
    'duplicate_objects' => 'duplicates: [{instance_id, anchor_hex: {q, r}}] (required)',
    'clear_placements' => 'instance_ids: [string] (optional), family (optional), definition_id (optional) — at least one required',
    'add_entry_ports' => 'ports: [{port_id, hex: {q, r}, edge (0-5), label, arrival_facing (0-5, default 0), is_default (bool, default false), tags (optional)}] (required)',
    'update_entry_ports' => 'ports: [{port_id, changes: {hex, edge, label, arrival_facing, is_default, tags}}] (required)',
    'remove_entry_ports' => 'port_ids: [string] (required; default entry port cannot be removed)',
    'add_exit_ports' => 'ports: [{port_id, hex: {q, r}, edge (0-5), label, kind (default door), direction (default bidirectional), default_state (default open), destination_hint (optional), requirements (optional), tags (optional)}] (required)',
    'update_exit_ports' => 'ports: [{port_id, changes: {hex, edge, label, kind, direction, default_state, destination_hint, linked_placement_id, requirements, tags}}] (required)',
    'remove_exit_ports' => 'port_ids: [string] (required)',
    'undo' => 'target_command_id (required): command id from the draft history to undo',
    'redo' => 'target_command_id (required): undo command id to redo',
  ];

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_room_commands',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Turn a structured authoring goal into an ordered, previewable command plan. Goals: ' . implode(', ', self::GOALS) . '.',
      FALSE,
      'planning only; execution requires apply_room_commands',
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
    $context = RoomEditorGmToolContext::of($context);
    $goal = EditorGmToolContext::requireString($arguments, 'goal');
    if (!in_array($goal, self::GOALS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('planning_goal_unsupported:%s', $goal));
    }
    $parameters = EditorGmToolContext::requireArray($arguments, 'parameters');
    $room = $context->room();

    $steps = match ($goal) {
      'set_metadata' => $this->planSetMetadata($parameters),
      'expand_footprint' => $this->planExpandFootprint($parameters, $room),
      'shrink_footprint' => $this->planShrinkFootprint($parameters, $room),
      'retheme_terrain' => $this->planRethemeTerrain($parameters, $room),
      'level_elevation' => $this->planLevelElevation($parameters, $room),
      'place_objects' => $this->planPlaceObjects($parameters, $room),
      'move_objects' => $this->planMoveObjects($parameters, $room),
      'rotate_objects' => $this->planRotateObjects($parameters, $room),
      'update_object_overrides' => $this->planUpdateObjectOverrides($parameters, $room),
      'duplicate_objects' => $this->planDuplicateObjects($parameters, $room),
      'clear_placements' => $this->planClearPlacements($parameters, $room),
      'add_entry_ports' => $this->planAddPorts($parameters, $room, TRUE),
      'update_entry_ports' => $this->planUpdatePorts($parameters, $room, TRUE),
      'remove_entry_ports' => $this->planRemovePorts($parameters, $room, TRUE),
      'add_exit_ports' => $this->planAddPorts($parameters, $room, FALSE),
      'update_exit_ports' => $this->planUpdatePorts($parameters, $room, FALSE),
      'remove_exit_ports' => $this->planRemovePorts($parameters, $room, FALSE),
      'undo', 'redo' => $this->planHistory($goal, $parameters),
    };

    if ($steps === []) {
      throw new \DomainException('planning_produced_no_steps');
    }

    $draft = $context->draft();
    return [
      'goal' => $goal,
      'command_plan' => [
        'schema_version' => 'editor-gm-command-plan-v1',
        'draft_id' => $context->draftId,
        'base_revision' => (int) ($draft['revision'] ?? 0),
        'steps' => $steps,
      ],
    ];
  }

  private function planSetMetadata(array $parameters): array {
    $changes = [];
    foreach (['room_id', 'name', 'description', 'room_type', 'size_category'] as $field) {
      if (array_key_exists($field, $parameters)) {
        $changes[$field] = $parameters[$field];
      }
    }
    if ($changes === []) {
      throw new \InvalidArgumentException('metadata_changes_required');
    }
    return [$this->step(1, 'set_room_metadata', ['changes' => $changes], 'Update room metadata: ' . implode(', ', array_keys($changes)) . '.')];
  }

  private function planExpandFootprint(array $parameters, array $room): array {
    $hexes = $this->requireHexList($parameters);
    $terrain = isset($parameters['terrain_type']) ? (string) $parameters['terrain_type'] : NULL;
    $existing = $this->hexKeys($room);

    $steps = [];
    foreach ($hexes as $hex) {
      if (isset($existing[$this->key($hex)])) {
        continue;
      }
      $payload = ['hex' => ['q' => $hex['q'], 'r' => $hex['r']]];
      if ($terrain !== NULL) {
        $payload['hex']['terrain_type'] = $terrain;
      }
      $steps[] = $this->step(
        count($steps) + 1,
        'add_hex',
        $payload,
        sprintf('Add hex (%d, %d) to the room footprint.', $hex['q'], $hex['r']),
      );
    }
    if ($steps === []) {
      throw new \DomainException('all_requested_hexes_already_exist');
    }
    return $steps;
  }

  private function planShrinkFootprint(array $parameters, array $room): array {
    $hexes = $this->requireHexList($parameters);
    $existing = $this->hexKeys($room);
    $occupied_by_placement = [];
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if (is_array($placement) && is_array($placement['anchor_hex'] ?? NULL)) {
        $occupied_by_placement[$this->key($placement['anchor_hex'])] = TRUE;
      }
    }
    $occupied_by_port = [];
    foreach (['entry_ports', 'exit_ports'] as $bucket) {
      foreach ((array) ($room[$bucket] ?? []) as $port) {
        if (is_array($port) && is_array($port['hex'] ?? NULL)) {
          $occupied_by_port[$this->key($port['hex'])] = TRUE;
        }
      }
    }

    $steps = [];
    foreach ($hexes as $hex) {
      $key = $this->key($hex);
      if (!isset($existing[$key])) {
        throw new \DomainException(sprintf('hex_not_found:%d,%d', $hex['q'], $hex['r']));
      }
      if (isset($occupied_by_placement[$key])) {
        throw new \DomainException(sprintf('hex_has_placements:%d,%d', $hex['q'], $hex['r']));
      }
      if (isset($occupied_by_port[$key])) {
        throw new \DomainException(sprintf('hex_has_ports:%d,%d', $hex['q'], $hex['r']));
      }
      $steps[] = $this->step(
        count($steps) + 1,
        'remove_hex',
        ['hex' => ['q' => $hex['q'], 'r' => $hex['r']]],
        sprintf('Remove hex (%d, %d) from the room footprint.', $hex['q'], $hex['r']),
      );
    }
    return $steps;
  }

  private function planRethemeTerrain(array $parameters, array $room): array {
    $terrain = EditorGmToolContext::requireString($parameters, 'terrain_type');
    $from = isset($parameters['from_terrain_type']) ? (string) $parameters['from_terrain_type'] : NULL;
    $targets = isset($parameters['hexes']) ? $this->requireHexList($parameters) : NULL;
    $target_keys = $targets === NULL ? NULL : array_flip(array_map(fn(array $h): string => $this->key($h), $targets));

    $steps = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (!is_array($hex)) {
        continue;
      }
      $current = (string) ($hex['terrain_type'] ?? '');
      if ($current === $terrain) {
        continue;
      }
      if ($from !== NULL && $current !== $from) {
        continue;
      }
      if ($target_keys !== NULL && !isset($target_keys[$this->key($hex)])) {
        continue;
      }
      $steps[] = $this->step(
        count($steps) + 1,
        'set_hex_terrain',
        ['hex' => ['q' => (int) $hex['q'], 'r' => (int) $hex['r']], 'terrain_type' => $terrain],
        sprintf('Retheme hex (%d, %d) from %s to %s.', (int) $hex['q'], (int) $hex['r'], $current ?: 'unset', $terrain),
      );
    }
    if ($steps === []) {
      throw new \DomainException('no_hexes_match_retheme_goal');
    }
    return $steps;
  }

  private function planLevelElevation(array $parameters, array $room): array {
    $elevation = EditorGmToolContext::requireInt($parameters, 'elevation_ft');
    $targets = isset($parameters['hexes']) ? $this->requireHexList($parameters) : NULL;
    $target_keys = $targets === NULL ? NULL : array_flip(array_map(fn(array $h): string => $this->key($h), $targets));

    $steps = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (!is_array($hex) || (int) ($hex['elevation_ft'] ?? 0) === $elevation) {
        continue;
      }
      if ($target_keys !== NULL && !isset($target_keys[$this->key($hex)])) {
        continue;
      }
      $steps[] = $this->step(
        count($steps) + 1,
        'set_hex_elevation',
        ['hex' => ['q' => (int) $hex['q'], 'r' => (int) $hex['r']], 'elevation_ft' => $elevation],
        sprintf('Level hex (%d, %d) to %d ft.', (int) $hex['q'], (int) $hex['r'], $elevation),
      );
    }
    if ($steps === []) {
      throw new \DomainException('no_hexes_match_elevation_goal');
    }
    return $steps;
  }

  private function planPlaceObjects(array $parameters, array $room): array {
    $existing = $this->hexKeys($room);
    $steps = [];

    if (isset($parameters['placements']) && is_array($parameters['placements']) && array_is_list($parameters['placements'])) {
      if ($parameters['placements'] === []) {
        throw new \InvalidArgumentException('placements_list_required');
      }
      foreach ($parameters['placements'] as $index => $spec) {
        if (!is_array($spec)) {
          throw new \InvalidArgumentException(sprintf('placement_spec_invalid:%d', $index + 1));
        }
        $family = EditorGmToolContext::requireString($spec, 'family');
        $definition_id = EditorGmToolContext::requireString($spec, 'definition_id');
        $version = isset($spec['version']) && trim((string) $spec['version']) !== '' ? (string) $spec['version'] : '1.0.0';
        $anchor = $spec['anchor_hex'] ?? $spec['hex'] ?? NULL;
        if (!is_array($anchor) || !is_int($anchor['q'] ?? NULL) || !is_int($anchor['r'] ?? NULL)) {
          throw new \InvalidArgumentException(sprintf('placement_anchor_invalid:%d', $index + 1));
        }
        $hex = ['q' => $anchor['q'], 'r' => $anchor['r']];
        if (!isset($existing[$this->key($hex)])) {
          throw new \DomainException(sprintf('placement_outside_room:%d,%d', $hex['q'], $hex['r']));
        }
        $facing = isset($spec['facing'])
          ? (int) $spec['facing']
          : (isset($spec['rotation_deg']) ? (((int) round(((int) $spec['rotation_deg']) / 60)) % 6 + 6) % 6 : 0);
        $facing = max(0, min(5, $facing));
        $payload = [
          'definition_ref' => ['family' => $family, 'definition_id' => $definition_id, 'version' => $version],
          'anchor_hex' => $hex,
          'facing' => $facing,
        ];
        if (isset($spec['elevation_ft']) && is_int($spec['elevation_ft'])) {
          $payload['elevation_ft'] = max(-50, min(200, $spec['elevation_ft']));
        }
        if (isset($spec['overrides']) && is_array($spec['overrides'])) {
          $payload['overrides'] = $spec['overrides'];
        }
        $steps[] = $this->step(
          count($steps) + 1,
          'place_object',
          $payload,
          sprintf('Place %s/%s at (%d, %d).', $family, $definition_id, $hex['q'], $hex['r']),
        );
      }
      return $steps;
    }

    $family = EditorGmToolContext::requireString($parameters, 'family');
    $definition_id = EditorGmToolContext::requireString($parameters, 'definition_id');
    $version = isset($parameters['version']) ? (string) $parameters['version'] : '1.0.0';
    $facing = isset($parameters['facing']) ? max(0, min(5, (int) $parameters['facing'])) : 0;
    $hexes = $this->requireHexList($parameters);

    foreach ($hexes as $hex) {
      if (!isset($existing[$this->key($hex)])) {
        throw new \DomainException(sprintf('placement_outside_room:%d,%d', $hex['q'], $hex['r']));
      }
      $steps[] = $this->step(
        count($steps) + 1,
        'place_object',
        [
          'definition_ref' => ['family' => $family, 'definition_id' => $definition_id, 'version' => $version],
          'anchor_hex' => ['q' => $hex['q'], 'r' => $hex['r']],
          'facing' => $facing,
        ],
        sprintf('Place %s/%s at (%d, %d).', $family, $definition_id, $hex['q'], $hex['r']),
      );
    }
    return $steps;
  }

  private function planMoveObjects(array $parameters, array $room): array {
    $moves = $this->requireList($parameters, 'moves');
    $existing = $this->hexKeys($room);
    $steps = [];
    foreach ($moves as $spec) {
      $instance_id = EditorGmToolContext::requireString($spec, 'instance_id');
      $placement = $this->requirePlacement($room, $instance_id);
      $anchor = $this->requireHex($spec, 'anchor_hex');
      if (!isset($existing[$this->key($anchor)])) {
        throw new \DomainException(sprintf('placement_outside_room:%d,%d', $anchor['q'], $anchor['r']));
      }
      $ref = is_array($placement['definition_ref'] ?? NULL) ? $placement['definition_ref'] : [];
      $steps[] = $this->step(
        count($steps) + 1,
        'move_object',
        ['instance_id' => $instance_id, 'anchor_hex' => $anchor],
        sprintf('Move %s/%s (%s) to (%d, %d).', $ref['family'] ?? '?', $ref['definition_id'] ?? '?', $instance_id, $anchor['q'], $anchor['r']),
      );
    }
    return $steps;
  }

  private function planRotateObjects(array $parameters, array $room): array {
    $rotations = $this->requireList($parameters, 'rotations');
    $steps = [];
    foreach ($rotations as $spec) {
      $instance_id = EditorGmToolContext::requireString($spec, 'instance_id');
      $placement = $this->requirePlacement($room, $instance_id);
      $facing = EditorGmToolContext::requireInt($spec, 'facing');
      if ($facing < 0 || $facing > 5) {
        throw new \InvalidArgumentException('facing_invalid');
      }
      $ref = is_array($placement['definition_ref'] ?? NULL) ? $placement['definition_ref'] : [];
      $steps[] = $this->step(
        count($steps) + 1,
        'rotate_object',
        ['instance_id' => $instance_id, 'facing' => $facing],
        sprintf('Rotate %s/%s (%s) to facing %d.', $ref['family'] ?? '?', $ref['definition_id'] ?? '?', $instance_id, $facing),
      );
    }
    return $steps;
  }

  private function planUpdateObjectOverrides(array $parameters, array $room): array {
    $updates = $this->requireList($parameters, 'updates');
    $steps = [];
    foreach ($updates as $spec) {
      $instance_id = EditorGmToolContext::requireString($spec, 'instance_id');
      $placement = $this->requirePlacement($room, $instance_id);
      $overrides = $spec['overrides'] ?? NULL;
      if (!is_array($overrides)) {
        throw new \InvalidArgumentException('overrides_invalid');
      }
      $ref = is_array($placement['definition_ref'] ?? NULL) ? $placement['definition_ref'] : [];
      $steps[] = $this->step(
        count($steps) + 1,
        'update_object_overrides',
        ['instance_id' => $instance_id, 'overrides' => $overrides],
        sprintf('Update overrides for %s/%s (%s).', $ref['family'] ?? '?', $ref['definition_id'] ?? '?', $instance_id),
      );
    }
    return $steps;
  }

  private function planDuplicateObjects(array $parameters, array $room): array {
    $duplicates = $this->requireList($parameters, 'duplicates');
    $existing = $this->hexKeys($room);
    $steps = [];
    foreach ($duplicates as $spec) {
      $instance_id = EditorGmToolContext::requireString($spec, 'instance_id');
      $placement = $this->requirePlacement($room, $instance_id);
      $anchor = $this->requireHex($spec, 'anchor_hex');
      if (!isset($existing[$this->key($anchor)])) {
        throw new \DomainException(sprintf('placement_outside_room:%d,%d', $anchor['q'], $anchor['r']));
      }
      $payload = ['instance_id' => $instance_id, 'anchor_hex' => $anchor];
      if (isset($spec['new_instance_id']) && is_string($spec['new_instance_id']) && $spec['new_instance_id'] !== '') {
        $payload['new_instance_id'] = $spec['new_instance_id'];
      }
      $ref = is_array($placement['definition_ref'] ?? NULL) ? $placement['definition_ref'] : [];
      $steps[] = $this->step(
        count($steps) + 1,
        'duplicate_object',
        $payload,
        sprintf('Duplicate %s/%s (%s) onto (%d, %d).', $ref['family'] ?? '?', $ref['definition_id'] ?? '?', $instance_id, $anchor['q'], $anchor['r']),
      );
    }
    return $steps;
  }

  private function planClearPlacements(array $parameters, array $room): array {
    $family = isset($parameters['family']) ? (string) $parameters['family'] : NULL;
    $definition_id = isset($parameters['definition_id']) ? (string) $parameters['definition_id'] : NULL;
    $instance_ids = isset($parameters['instance_ids']) && is_array($parameters['instance_ids'])
      ? array_values(array_filter(array_map('strval', $parameters['instance_ids'])))
      : [];
    if ($family === NULL && $definition_id === NULL && $instance_ids === []) {
      throw new \InvalidArgumentException('clear_placements_filter_required');
    }

    $steps = [];
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if (!is_array($placement)) {
        continue;
      }
      $instance_id = (string) ($placement['instance_id'] ?? '');
      if ($instance_id === '') {
        continue;
      }
      if ($instance_ids !== [] && !in_array($instance_id, $instance_ids, TRUE)) {
        continue;
      }
      $ref = is_array($placement['definition_ref'] ?? NULL) ? $placement['definition_ref'] : [];
      if ($family !== NULL && (string) ($ref['family'] ?? '') !== $family) {
        continue;
      }
      if ($definition_id !== NULL && (string) ($ref['definition_id'] ?? '') !== $definition_id) {
        continue;
      }
      $steps[] = $this->step(
        count($steps) + 1,
        'remove_object',
        ['instance_id' => $instance_id],
        sprintf('Remove %s/%s instance %s.', $ref['family'] ?? '?', $ref['definition_id'] ?? '?', $instance_id),
      );
    }
    if ($steps === []) {
      throw new \DomainException('no_placements_match_clear_goal');
    }
    return $steps;
  }

  private function planAddPorts(array $parameters, array $room, bool $is_entry): array {
    $ports = $this->requireList($parameters, 'ports');
    $existing_hexes = $this->hexKeys($room);
    $bucket = $is_entry ? 'entry_ports' : 'exit_ports';
    $known_ids = array_column((array) ($room[$bucket] ?? []), 'port_id');
    $command_type = $is_entry ? 'add_entry_port' : 'add_exit_port';

    $steps = [];
    foreach ($ports as $spec) {
      $port_id = EditorGmToolContext::requireString($spec, 'port_id');
      if (in_array($port_id, $known_ids, TRUE)) {
        throw new \DomainException(sprintf('port_already_exists:%s', $port_id));
      }
      $hex = $this->requireHex($spec, 'hex');
      if (!isset($existing_hexes[$this->key($hex)])) {
        throw new \DomainException(sprintf('port_outside_room:%d,%d', $hex['q'], $hex['r']));
      }
      $edge = EditorGmToolContext::requireInt($spec, 'edge');
      if ($edge < 0 || $edge > 5) {
        throw new \InvalidArgumentException('port_edge_invalid');
      }
      $label = EditorGmToolContext::requireString($spec, 'label');
      $tags = isset($spec['tags']) && is_array($spec['tags']) ? array_values(array_map('strval', $spec['tags'])) : [];

      if ($is_entry) {
        $port = [
          'port_id' => $port_id,
          'hex' => $hex,
          'edge' => $edge,
          'label' => $label,
          'arrival_facing' => isset($spec['arrival_facing']) ? max(0, min(5, (int) $spec['arrival_facing'])) : 0,
          'is_default' => (bool) ($spec['is_default'] ?? FALSE),
          'tags' => $tags,
        ];
      }
      else {
        $port = [
          'port_id' => $port_id,
          'hex' => $hex,
          'edge' => $edge,
          'label' => $label,
          'kind' => isset($spec['kind']) ? (string) $spec['kind'] : 'door',
          'direction' => isset($spec['direction']) ? (string) $spec['direction'] : 'bidirectional',
          'default_state' => isset($spec['default_state']) ? (string) $spec['default_state'] : 'open',
          'destination_hint' => isset($spec['destination_hint']) ? (string) $spec['destination_hint'] : NULL,
          'linked_placement_id' => isset($spec['linked_placement_id']) ? (string) $spec['linked_placement_id'] : NULL,
          'requirements' => isset($spec['requirements']) && is_array($spec['requirements']) ? $spec['requirements'] : [],
          'tags' => $tags,
        ];
      }

      $known_ids[] = $port_id;
      $steps[] = $this->step(
        count($steps) + 1,
        $command_type,
        ['port' => $port],
        sprintf('Add %s port "%s" (%s) at (%d, %d) edge %d.', $is_entry ? 'entry' : 'exit', $label, $port_id, $hex['q'], $hex['r'], $edge),
      );
    }
    return $steps;
  }

  private function planUpdatePorts(array $parameters, array $room, bool $is_entry): array {
    $ports = $this->requireList($parameters, 'ports');
    $bucket = $is_entry ? 'entry_ports' : 'exit_ports';
    $known_ids = array_column((array) ($room[$bucket] ?? []), 'port_id');
    $command_type = $is_entry ? 'update_entry_port' : 'update_exit_port';
    $allowed = $is_entry
      ? ['hex', 'edge', 'label', 'arrival_facing', 'is_default', 'tags']
      : ['hex', 'edge', 'label', 'kind', 'direction', 'default_state', 'destination_hint', 'linked_placement_id', 'requirements', 'tags'];

    $steps = [];
    foreach ($ports as $spec) {
      $port_id = EditorGmToolContext::requireString($spec, 'port_id');
      if (!in_array($port_id, $known_ids, TRUE)) {
        throw new \OutOfBoundsException(sprintf('port_not_found:%s', $port_id));
      }
      $changes = $spec['changes'] ?? NULL;
      if (!is_array($changes) || $changes === [] || array_is_list($changes)) {
        throw new \InvalidArgumentException('port_changes_required');
      }
      foreach (array_keys($changes) as $key) {
        if (!in_array($key, $allowed, TRUE)) {
          throw new \InvalidArgumentException(sprintf('port_change_key_unsupported:%s', $key));
        }
      }
      $steps[] = $this->step(
        count($steps) + 1,
        $command_type,
        ['port_id' => $port_id, 'changes' => $changes],
        sprintf('Update %s port %s: %s.', $is_entry ? 'entry' : 'exit', $port_id, implode(', ', array_keys($changes))),
      );
    }
    return $steps;
  }

  private function planRemovePorts(array $parameters, array $room, bool $is_entry): array {
    $port_ids = $this->requireStringList($parameters, 'port_ids');
    $bucket = $is_entry ? 'entry_ports' : 'exit_ports';
    $by_id = [];
    foreach ((array) ($room[$bucket] ?? []) as $port) {
      if (is_array($port) && isset($port['port_id'])) {
        $by_id[(string) $port['port_id']] = $port;
      }
    }
    $command_type = $is_entry ? 'remove_entry_port' : 'remove_exit_port';

    $steps = [];
    foreach ($port_ids as $port_id) {
      if (!isset($by_id[$port_id])) {
        throw new \OutOfBoundsException(sprintf('port_not_found:%s', $port_id));
      }
      if ($is_entry && !empty($by_id[$port_id]['is_default'])) {
        throw new \DomainException(sprintf('default_entry_cannot_be_removed:%s', $port_id));
      }
      $steps[] = $this->step(
        count($steps) + 1,
        $command_type,
        ['port_id' => $port_id],
        sprintf('Remove %s port %s.', $is_entry ? 'entry' : 'exit', $port_id),
      );
    }
    return $steps;
  }

  private function planHistory(string $goal, array $parameters): array {
    $target = EditorGmToolContext::requireString($parameters, 'target_command_id');
    return [$this->step(1, $goal, ['target_command_id' => $target], sprintf('%s command %s.', ucfirst($goal), $target))];
  }

  private function requirePlacement(array $room, string $instance_id): array {
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if (is_array($placement) && (string) ($placement['instance_id'] ?? '') === $instance_id) {
        return $placement;
      }
    }
    throw new \OutOfBoundsException(sprintf('placement_not_found:%s', $instance_id));
  }

  private function requireHex(array $spec, string $key): array {
    $hex = $spec[$key] ?? NULL;
    if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
      throw new \InvalidArgumentException(sprintf('%s_must_be_axial', $key));
    }
    return ['q' => $hex['q'], 'r' => $hex['r']];
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

  /**
   * Reads and normalizes a required list of {q, r} hex coordinates.
   */
  private function requireHexList(array $parameters): array {
    $hexes = EditorGmToolContext::requireArray($parameters, 'hexes');
    if ($hexes === []) {
      throw new \InvalidArgumentException('hex_list_empty');
    }
    $normalized = [];
    foreach (array_values($hexes) as $index => $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        throw new \InvalidArgumentException(sprintf('hex_coordinate_invalid:%d', $index + 1));
      }
      $normalized[] = ['q' => $hex['q'], 'r' => $hex['r']];
    }
    return $normalized;
  }

  private function hexKeys(array $room): array {
    $keys = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (is_array($hex)) {
        $keys[$this->key($hex)] = TRUE;
      }
    }
    return $keys;
  }

  private function key(array $hex): string {
    return ((int) ($hex['q'] ?? 0)) . ':' . ((int) ($hex['r'] ?? 0));
  }

  private function step(int $number, string $type, array $payload, string $rationale): array {
    return [
      'step' => $number,
      'command_type' => $type,
      'payload' => $payload,
      'rationale' => $rationale,
    ];
  }

}
