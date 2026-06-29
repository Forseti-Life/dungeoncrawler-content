<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Coordinates navigation transition post-processing for GM room turns.
 *
 * This keeps navigation transition orchestration out of generateGmReply()
 * while preserving the existing RoomChatService-owned handlers.
 */
class NavigationTransitionPipeline {

  /**
   * Apply navigation transition orchestration for a GM turn.
   *
   * @param callable $handle_navigation
   *   fn(array $actions, int $campaign_id, string $room_id, array $dungeon_data, string $narrative): ?array
   * @param callable $resolve_room_index
   *   fn(array $dungeon_data, string $room_id): ?int
   * @param callable $append_destination_arrival
   *   fn(int $campaign_id, int|string $dungeon_id, array &$dungeon_data, array $navigation_result): void
   * @param callable $record_location_transition
   *   fn(array &$dungeon_data, array $room_meta, array $navigation_result): void
   *
   * @return array{
   *   navigation_result: ?array,
   *   dungeon_data: array,
   *   room_index: int|string,
   *   navigation_success: bool
   * }
   */
  public function apply(
    array $actions,
    int $campaign_id,
    string $room_id,
    int|string $dungeon_id,
    array $room_meta,
    array $dungeon_data,
    int|string $room_index,
    string $narrative,
    callable $handle_navigation,
    callable $resolve_room_index,
    callable $append_destination_arrival,
    callable $record_location_transition
  ): array {
    $navigation_result = NULL;
    if ($actions === []) {
      return [
        'navigation_result' => NULL,
        'dungeon_data' => $dungeon_data,
        'room_index' => $room_index,
        'navigation_success' => FALSE,
      ];
    }

    $navigation_result = $handle_navigation($actions, $campaign_id, $room_id, $dungeon_data, $narrative);
    $navigation_success = !empty($navigation_result) && empty($navigation_result['error']);

    if ($navigation_success && !empty($navigation_result['dungeon_data']) && is_array($navigation_result['dungeon_data'])) {
      $dungeon_data = $navigation_result['dungeon_data'];
      $resolved_room_index = $resolve_room_index($dungeon_data, $room_id);
      $room_index = $resolved_room_index !== NULL ? $resolved_room_index : 0;
    }

    if ($navigation_success) {
      $append_destination_arrival($campaign_id, $dungeon_id, $dungeon_data, $navigation_result);
      $record_location_transition($dungeon_data, $room_meta, $navigation_result);
    }

    return [
      'navigation_result' => is_array($navigation_result) ? $navigation_result : NULL,
      'dungeon_data' => $dungeon_data,
      'room_index' => $room_index,
      'navigation_success' => $navigation_success,
    ];
  }

}

