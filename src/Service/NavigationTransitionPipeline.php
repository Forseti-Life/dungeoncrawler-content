<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Coordinates navigation transition post-processing for room turns.
 */
class NavigationTransitionPipeline {

  protected NavigationRuntimeService $navigationRuntime;

  public function __construct(NavigationRuntimeService $navigation_runtime) {
    $this->navigationRuntime = $navigation_runtime;
  }

  /**
   * Apply navigation transition orchestration for a room turn.
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
    string $narrative
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

    $navigation_result = $this->navigationRuntime->handleNavigationActions(
      $actions,
      $campaign_id,
      $room_id,
      $dungeon_data,
      $narrative
    );
    $navigation_success = $navigation_result !== NULL;

    if ($navigation_success && !empty($navigation_result['dungeon_data']) && is_array($navigation_result['dungeon_data'])) {
      $dungeon_data = $navigation_result['dungeon_data'];
      $resolved_room_index = $this->navigationRuntime->resolveNavigationTransitionRoomIndex($dungeon_data, $room_id);
      $room_index = $resolved_room_index !== NULL ? $resolved_room_index : 0;
    }

    if ($navigation_success) {
      $this->navigationRuntime->appendDestinationArrivalNarration($campaign_id, $dungeon_id, $dungeon_data, $navigation_result);
      $this->navigationRuntime->recordLocationTransition($dungeon_data, $room_meta, $navigation_result);
    }

    return [
      'navigation_result' => is_array($navigation_result) ? $navigation_result : NULL,
      'dungeon_data' => $dungeon_data,
      'room_index' => $room_index,
      'navigation_success' => $navigation_success,
    ];
  }

}
