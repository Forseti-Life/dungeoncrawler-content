<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * One current Dungeon Editor problem and its native resolution path.
 */
final class DungeonValidationTroubleshootingGuide {

  public static function packet(DungeonEditorGmToolContext $context): array {
    $validation = $context->validation();
    $model = $context->model();
    $district_missing = ($model['kind'] ?? 'dungeon') === 'district'
      && self::streetPlacementIds($model) === [];
    if ($district_missing) {
      return [
        'surface' => 'dungeon_editor',
        'finding' => [
          'severity' => 'prerequisite',
          'code' => 'district_ground_missing',
          'message' => 'A district must have its street ground before any building room is placed.',
        ],
        'workflow' => 'district_ground_first',
        'owner' => 'DungeonEditorService',
        'required_first_tool' => 'build_district_street',
        'editable' => [
          'room_id' => NULL,
          'name' => $context->dungeon()['name'] ?? '',
          'building_count' => count((array) ($context->dungeon()['room_placements'] ?? [])),
        ],
        'scope_rule' => 'Create and publish the ground, set the dungeon kind to district, place the ground, then place buildings.',
        'code_change_required' => FALSE,
      ];
    }
    $finding = self::selectedFinding($validation);
    if ($finding === []) {
      return [];
    }
    $packet = [
      'surface' => 'dungeon_editor',
      'finding' => $finding,
      'workflow' => 'validation_finding',
      'scope_rule' => 'Resolve only this Dungeon Editor finding and its required dependency.',
      'code_change_required' => FALSE,
    ];
    if (($finding['code'] ?? '') === 'district_building_near_street_edge'
      || ($finding['code'] ?? '') === 'district_building_no_street_access') {
      $street_placements = array_values(array_filter(
        (array) ($model['placements'] ?? []),
        static fn(mixed $placement): bool => is_array($placement)
          && ($placement['room']['room_type'] ?? '') === 'street',
      ));
      $packet = array_replace($packet, [
        'workflow' => 'district_ground_capacity',
        'owner' => 'DungeonEditorService',
        'object' => [
          'type' => 'room',
          'id' => (string) ($street_placements[0]['room_id'] ?? ''),
        ],
        'verification' => 'The building is inside the resized street with a clear walkway, and district validation no longer reports this finding.',
      ]);
      $street_hexes = (int) ($street_placements[0]['room']['hex_count'] ?? 0);
      if ($street_hexes > 0 && $street_hexes * 1.3 <= 50000) {
        $packet['required_tool'] = 'resize_room';
        $packet['required_first_tool'] = 'resize_room';
        $packet['suggested_values'] = ['area_percent' => 130];
      }
      else {
        $packet['required_tool'] = 'plan_district_buildings';
        $packet['required_first_tool'] = 'plan_district_buildings';
        $packet['suggested_values'] = [
          'reflow' => TRUE,
          'street_placement_id' => (string) ($street_placements[0]['placement_id'] ?? ''),
          'buildings' => array_values(array_map(
            static fn(array $placement): array => [
              'room_id' => (string) ($placement['room_id'] ?? ''),
              'label' => (string) ($placement['label'] ?? ''),
            ],
            array_filter(
              (array) ($context->model()['placements'] ?? []),
              static fn(array $placement): bool => ($placement['room']['room_type'] ?? '') !== 'street',
            ),
          )),
        ];
      }
    }
    if (($finding['code'] ?? '') === 'story_room_not_placed') {
      $room_id = self::storyRoomNotPlacedId($finding['message'] ?? '');
      $published = self::publishedRoomIds($context->roomLibrary());
      $packet = array_replace($packet, [
        'object' => ['type' => 'room', 'id' => $room_id],
        'workflow' => isset($published[$room_id]) ? 'district_building_placement' : 'room_creation_delegation',
        'owner' => isset($published[$room_id]) ? 'DungeonEditorService' : 'RoomEditorGmService',
        'required_tool' => isset($published[$room_id]) ? 'plan_district_building' : 'delegate_room_editor_task',
        'dependency' => isset($published[$room_id])
          ? 'published room'
          : 'create and publish the canonical room before placement',
      ]);
    }
    return $packet;
  }

  private static function selectedFinding(array $validation): array {
    foreach (['error', 'warning'] as $severity) {
      foreach ((array) ($validation['findings'] ?? []) as $finding) {
        if (is_array($finding) && ($finding['severity'] ?? '') === $severity) {
          return $finding;
        }
      }
    }
    return [];
  }

  private static function streetPlacementIds(array $model): array {
    $ids = [];
    foreach ((array) ($model['placements'] ?? []) as $placement) {
      if (($placement['room']['room_type'] ?? '') === 'street') {
        $ids[] = (string) ($placement['placement_id'] ?? '');
      }
    }
    return array_values(array_filter($ids));
  }

  private static function publishedRoomIds(array $rooms): array {
    $ids = [];
    foreach ($rooms as $room) {
      if (trim((string) ($room['room_id'] ?? '')) !== '') {
        $ids[(string) $room['room_id']] = TRUE;
      }
    }
    return $ids;
  }

  private static function storyRoomNotPlacedId(string $message): string {
    return preg_match('/not placed: ([a-z0-9_-]+)\./', $message, $match) === 1 ? $match[1] : '';
  }

}
