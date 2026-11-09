<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Projects a reviewed trace into the existing Room Editor validator.
 *
 * No draft, command, version, or release is written. A room author must
 * supply all non-geometric canonical content and explicitly classify exits.
 */
final class ModuleSceneHexRoomValidationAdapter {

  /**
   * @param array $room
   *   Project-authored canonical room shell with no hexes, ports or placements.
   * @param array $exit_details
   *   Reviewed canonical exit fields indexed by the trace's port_id.
   */
  public function validate(array $trace, array $room, array $exit_details, RoomEditorService $editor, ModuleSceneGenerationAdapter $traversal): array {
    if (($trace['schema_version'] ?? NULL) !== 'module-scene-hex-trace-v1'
      || ($trace['publication_ready'] ?? NULL) !== FALSE
      || !is_string($trace['review_sha256'] ?? NULL)
      || !preg_match('/^[a-f0-9]{64}$/D', $trace['review_sha256'])
      || !is_array($trace['hexes'] ?? NULL)
      || !is_array($trace['entry_ports'] ?? NULL)
      || !is_array($trace['exit_ports'] ?? NULL)
      || !is_array($exit_details)
      || !empty($room['hexes']) || !empty($room['entry_ports'])
      || !empty($room['exit_ports']) || !empty($room['placements'])) {
      throw new \InvalidArgumentException('scene_trace_room_projection_invalid');
    }
    $exits = [];
    foreach ($trace['exit_ports'] as $port) {
      $id = $port['port_id'] ?? NULL;
      $required = array_fill_keys([
        'kind', 'direction', 'default_state', 'destination_hint',
        'linked_placement_id', 'requirements',
      ], TRUE);
      if (!is_string($id) || !array_key_exists($id, $exit_details)
        || !is_array($exit_details[$id])
        || array_diff_key($required, $exit_details[$id]) !== []
        || array_diff_key($exit_details[$id], $required) !== []) {
        throw new \InvalidArgumentException('scene_trace_exit_review_required');
      }
      $exits[] = $port + $exit_details[$id];
      unset($exit_details[$id]);
    }
    if ($exit_details !== []) {
      throw new \InvalidArgumentException('scene_trace_exit_review_required');
    }
    $room['hexes'] = $trace['hexes'];
    $room['entry_ports'] = $trace['entry_ports'];
    $room['exit_ports'] = $exits;
    $validation = $editor->validateAggregate($room, 'publication');
    if ($validation['errors'] === []) {
      $hazards = array_filter($room['hexes'], static fn(array $hex): bool =>
        in_array($hex['terrain_type'] ?? NULL, ['water_deep', 'lava'], TRUE));
      if ($hazards !== []) {
        $validation['errors'][] = [
          'code' => 'scene_trace_land_traversal_unverified',
          'path' => '/hexes',
          'message' => 'Deep water and lava require independent land-traversal review.',
          'severity' => 'error',
          'context' => [],
        ];
      }
      else {
        try {
          $traversal->assertTraversableRoom($room, TRUE);
        }
        catch (\DomainException $exception) {
          if ($exception->getMessage() !== 'module_scene_room_traversal_invalid') {
            throw $exception;
          }
          $validation['errors'][] = [
            'code' => 'scene_trace_movement_unreachable',
            'path' => '/hexes',
            'message' => 'The reviewed footprint is not fully passable and reachable from its entry.',
            'severity' => 'error',
            'context' => [],
          ];
        }
      }
    }
    return [
      'room' => $room,
      'validation' => ['valid' => $validation['errors'] === []] + $validation,
      'publication_ready' => FALSE,
      'trace_review_sha256' => $trace['review_sha256'],
    ];
  }

}
