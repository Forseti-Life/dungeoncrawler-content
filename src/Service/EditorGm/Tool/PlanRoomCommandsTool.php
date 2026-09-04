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
    'retheme_terrain',
    'level_elevation',
    'place_objects',
    'clear_placements',
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
    'retheme_terrain' => 'terrain_type (required), from_terrain_type (optional), hexes: [{q, r}] (optional)',
    'level_elevation' => 'elevation_ft (required), hexes: [{q, r}] (optional)',
    'place_objects' => 'placements: [{family, definition_id, version, anchor_hex: {q, r}, rotation_deg}] (required)',
    'clear_placements' => 'family (optional), definition_id (optional)',
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
      'retheme_terrain' => $this->planRethemeTerrain($parameters, $room),
      'level_elevation' => $this->planLevelElevation($parameters, $room),
      'place_objects' => $this->planPlaceObjects($parameters, $room),
      'clear_placements' => $this->planClearPlacements($parameters, $room),
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
    $family = EditorGmToolContext::requireString($parameters, 'family');
    $definition_id = EditorGmToolContext::requireString($parameters, 'definition_id');
    $version = isset($parameters['version']) ? (string) $parameters['version'] : '1.0.0';
    $facing = isset($parameters['facing']) ? (int) $parameters['facing'] : 0;
    $hexes = $this->requireHexList($parameters);
    $existing = $this->hexKeys($room);

    $steps = [];
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

  private function planClearPlacements(array $parameters, array $room): array {
    $family = isset($parameters['family']) ? (string) $parameters['family'] : NULL;
    $definition_id = isset($parameters['definition_id']) ? (string) $parameters['definition_id'] : NULL;
    if ($family === NULL && $definition_id === NULL) {
      throw new \InvalidArgumentException('clear_placements_filter_required');
    }

    $steps = [];
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if (!is_array($placement)) {
        continue;
      }
      $ref = is_array($placement['definition_ref'] ?? NULL) ? $placement['definition_ref'] : [];
      if ($family !== NULL && (string) ($ref['family'] ?? '') !== $family) {
        continue;
      }
      if ($definition_id !== NULL && (string) ($ref['definition_id'] ?? '') !== $definition_id) {
        continue;
      }
      $instance_id = (string) ($placement['instance_id'] ?? '');
      if ($instance_id === '') {
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
