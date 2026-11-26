<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Plans every building of a district onto the street at once.
 *
 * Uses the same placement rule as plan_district_building, but keeps each
 * planned building's hexes in account so later buildings cannot overlap or
 * crowd earlier ones. The whole list is refused if any building cannot be
 * placed.
 */
final class PlanDistrictBuildingsTool implements EditorGmToolInterface {

  public function __construct(private readonly UuidInterface $uuid) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_district_buildings',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Plan a whole list of building rooms onto the district street in one command plan, with no overlap between them. Each building has its entry port on a street hex. With reflow=true, the same plan first removes existing non-street placements and preserves the old level-entrance room, enabling district-ground-first resizing/reflow. Apply the returned plan with apply_dungeon_commands, then run inspect_district.',
      FALSE,
      'DistrictBuildingCandidates::place()',
      [
        EditorGmToolDefinition::argument('street_placement_id', 'string', TRUE, 'Placement of the street room.'),
        EditorGmToolDefinition::argument('buildings', 'array', TRUE, 'List of {room_id, label (optional), q and r (optional street hex for the entry)} in the order to place them.'),
        EditorGmToolDefinition::argument('reflow', 'boolean', FALSE, 'Include removal of every existing non-street placement before replanning the district.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $street_id = EditorGmToolContext::requireString($arguments, 'street_placement_id');
    $buildings = EditorGmToolContext::requireArray($arguments, 'buildings');
    if ($buildings === []) {
      throw new \InvalidArgumentException('buildings_empty');
    }

    $model = $context->model();
    $street = DungeonPlanSteps::placement($model, $street_id);
    if (!$street['resolved']) {
      throw new \DomainException(sprintf('placement_unresolved:%s', $street_id));
    }

    $occupied = (array) ($model['occupancy'] ?? []);
    $steps = [];
    $placed = [];
    $reflow = !empty($arguments['reflow']);
    if ($reflow) {
      $street_only = [];
      foreach ($occupied as $key => $claims) {
        $claims = array_values(array_filter(
          (array) $claims,
          static fn(string $id): bool => $id === $street_id,
        ));
        if ($claims !== []) {
          $street_only[$key] = $claims;
        }
      }
      $occupied = $street_only;
      foreach ((array) ($model['placements'] ?? []) as $placement) {
        if (($placement['placement_id'] ?? '') !== $street_id) {
          $steps[] = DungeonPlanSteps::step(
            count($steps) + 1,
            'remove_room_placement',
            ['placement_id' => $placement['placement_id']],
            sprintf('Remove "%s" before district reflow.', $placement['label']),
          );
        }
      }
    }
    foreach (array_values($buildings) as $index => $building) {
      if (!is_array($building) || !is_string($building['room_id'] ?? NULL) || $building['room_id'] === '') {
        throw new \InvalidArgumentException(sprintf('building_room_id_required:%d', $index + 1));
      }
      if (isset($building['q']) !== isset($building['r'])) {
        throw new \InvalidArgumentException(sprintf('district_hex_needs_q_and_r:%d', $index + 1));
      }
      $room_id = $building['room_id'];
      $at = isset($building['q']) ? ['q' => (int) $building['q'], 'r' => (int) $building['r']] : NULL;
      $library = DungeonPlanSteps::libraryRoom($context, $room_id);
      $result = DistrictBuildingCandidates::place($room_id, $library, $street_id, $at, $occupied);
      if ($result['found']['candidates'] === []) {
        throw new \DomainException(sprintf('no_district_placement:%s:%s:%d,%d:%s', $room_id, $street_id, $result['at']['q'], $result['at']['r'], $result['found']['why']));
      }
      $chosen = $result['found']['candidates'][0];
      $placement_id = $this->uuid->generate();
      foreach ((array) $library['footprint'] as $hex) {
        $key = RoomPlacementTransformer::hexKey(RoomPlacementTransformer::toLevel($hex, $chosen));
        $occupied[$key][] = $placement_id;
      }
      $label = isset($building['label']) ? trim((string) $building['label']) : '';
      $payload = [
        'placement_id' => $placement_id,
        'room_id' => $room_id,
        'version_id' => $library['version_id'],
        'origin' => $chosen['origin'],
        'rotation_steps' => $chosen['rotation_steps'],
      ];
      if ($label !== '') {
        $payload['label'] = $label;
      }
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'place_room', $payload, sprintf('Place "%s" with its entry on street hex (%d, %d).', $library['name'], $result['at']['q'], $result['at']['r']));
      $placed[] = ['room_id' => $room_id, 'street_hex' => $result['at'], 'origin' => $chosen['origin'], 'rotation_steps' => $chosen['rotation_steps']];
    }
    if ($reflow) {
      $old_entrances = [];
      foreach ((array) ($model['placements'] ?? []) as $placement) {
        if (!empty($placement['is_level_entrance'])) {
          $old_entrances[(string) ($placement['room_id'] ?? '')] = TRUE;
        }
      }
      foreach ($steps as $step) {
        if (($step['command_type'] ?? '') !== 'place_room'
          || !isset($old_entrances[$step['payload']['room_id'] ?? ''])) {
          continue;
        }
        $steps[] = DungeonPlanSteps::step(
          count($steps) + 1,
          'set_placement_metadata',
          [
            'placement_id' => $step['payload']['placement_id'],
            'changes' => ['is_level_entrance' => TRUE],
          ],
          sprintf('Restore %s as a level entrance.', $step['payload']['room_id']),
        );
      }
    }

    return [
      'street_placement_id' => $street_id,
      'placed' => $placed,
      'command_plan' => DungeonPlanSteps::plan($context, $steps),
    ];
  }

}
