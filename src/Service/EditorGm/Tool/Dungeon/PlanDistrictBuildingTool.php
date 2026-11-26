<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Plans a building room onto the district street.
 *
 * A building overlays the street and overrides the hexes it covers. The door
 * belongs to the building: its entry port lands on a street hex, and that is
 * the way in. No street door or link is involved.
 */
final class PlanDistrictBuildingTool implements EditorGmToolInterface {

  public function __construct(private readonly UuidInterface $uuid) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_district_building',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Plan a building onto the district street: places the published room so one of its entry ports sits on a street hex (q, r when given, otherwise the free street hex nearest the centre), keeping a one hex walkway around it. Rooms may not share any hex.',
      FALSE,
      'planning only; DistrictBuildingCandidates, execution requires apply_dungeon_commands',
      [
        EditorGmToolDefinition::argument('room_id', 'string', TRUE, 'Published building room to place (from list_published_rooms).'),
        EditorGmToolDefinition::argument('street_placement_id', 'string', TRUE, 'Placement of the street room.'),
        EditorGmToolDefinition::argument('q', 'integer', FALSE, 'Street hex q for the entry port (give both q and r, or neither).'),
        EditorGmToolDefinition::argument('r', 'integer', FALSE, 'Street hex r for the entry port.'),
        EditorGmToolDefinition::argument('entry_port_id', 'string', FALSE, 'Restrict to one entry port of the building.'),
        EditorGmToolDefinition::argument('candidate', 'integer', FALSE, '1-based index into the returned candidates to plan (default 1).'),
        EditorGmToolDefinition::argument('label', 'string', FALSE, 'Placement label.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $room_id = EditorGmToolContext::requireString($arguments, 'room_id');
    $street_id = EditorGmToolContext::requireString($arguments, 'street_placement_id');
    $entry_filter = isset($arguments['entry_port_id']) ? trim((string) $arguments['entry_port_id']) : '';
    if (isset($arguments['q']) !== isset($arguments['r'])) {
      throw new \InvalidArgumentException('district_hex_needs_q_and_r');
    }
    $at = isset($arguments['q']) ? ['q' => EditorGmToolContext::requireInt($arguments, 'q'), 'r' => EditorGmToolContext::requireInt($arguments, 'r')] : NULL;

    $model = $context->model();
    $library = DungeonPlanSteps::libraryRoom($context, $room_id);
    $street = DungeonPlanSteps::placement($model, $street_id);
    if (!$street['resolved']) {
      throw new \DomainException(sprintf('placement_unresolved:%s', $street_id));
    }

    $placed = DistrictBuildingCandidates::place($room_id, $library, $street_id, $at, (array) ($model['occupancy'] ?? []), $entry_filter);
    $found = $placed['found'];
    $candidates = $found['candidates'];
    if ($candidates === []) {
      throw new \DomainException(sprintf('no_district_placement:%s:%s:%d,%d:%s', $room_id, $street_id, $placed['at']['q'], $placed['at']['r'], $found['why']));
    }

    $index = array_key_exists('candidate', $arguments) ? EditorGmToolContext::requireInt($arguments, 'candidate') : 1;
    if ($index < 1 || $index > count($candidates)) {
      throw new \InvalidArgumentException(sprintf('candidate_out_of_range:%d', $index));
    }
    $chosen = $candidates[$index - 1];
    $label = isset($arguments['label']) ? trim((string) $arguments['label']) : '';
    $payload = [
      'placement_id' => $this->uuid->generate(),
      'room_id' => $room_id,
      'version_id' => $library['version_id'],
      'origin' => $chosen['origin'],
      'rotation_steps' => $chosen['rotation_steps'],
    ];
    if ($label !== '') {
      $payload['label'] = $label;
    }

    return [
      'room_id' => $room_id,
      'street_hex' => $placed['at'],
      'candidate_count' => count($candidates),
      'candidates' => $candidates,
      'rejected' => $found['rejected'],
      'chosen' => $chosen,
      'command_plan' => DungeonPlanSteps::plan($context, [
        DungeonPlanSteps::step(1, 'place_room', $payload, sprintf(
          'Place "%s" at (%d, %d) rotated %d so entry "%s" stands on street hex (%d, %d).',
          $library['name'], $chosen['origin']['q'], $chosen['origin']['r'], $chosen['rotation_steps'], $chosen['entry_port_id'], $placed['at']['q'], $placed['at']['r']
        )),
      ]),
    ];
  }

}
