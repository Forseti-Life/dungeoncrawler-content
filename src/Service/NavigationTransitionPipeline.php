<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Coordinates navigation transition post-processing for room turns.
 */
class NavigationTransitionPipeline {

  protected NavigationRuntimeService $navigationRuntime;
  protected H3ProjectionQueueService $h3ProjectionQueue;

  public function __construct(
    NavigationRuntimeService $navigation_runtime,
    H3ProjectionQueueService $h3_projection_queue,
  ) {
    $this->navigationRuntime = $navigation_runtime;
    $this->h3ProjectionQueue = $h3_projection_queue;
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
      $runtime_dungeon_id = trim((string) (
        $dungeon_data['dungeon_id']
        ?? $dungeon_data['hex_map']['map_id']
        ?? $dungeon_data['map_id']
        ?? ''
      ));
      $launch_slice_scope = $this->resolveLaunchSliceRoomScopeFromDungeonData($dungeon_data);
      if ($runtime_dungeon_id !== '' && $launch_slice_scope !== []) {
        $this->h3ProjectionQueue->provisionLaunchSliceNow($campaign_id, $runtime_dungeon_id, $launch_slice_scope);
      }
    }

    return [
      'navigation_result' => is_array($navigation_result) ? $navigation_result : NULL,
      'dungeon_data' => $dungeon_data,
      'room_index' => $room_index,
      'navigation_success' => $navigation_success,
    ];
  }

  /**
   * Resolve transition frontier as active room plus direct neighbors.
   *
   * @return array<int, string>
   *   Provisioning scope for launch slice.
   */
  protected function resolveLaunchSliceRoomScopeFromDungeonData(array $dungeon_data): array {
    $rooms = [];
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
      if ($room_id !== '') {
        $rooms[$room_id] = TRUE;
      }
    }
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? $dungeon_data['current_room_id'] ?? ''));
    $scope = [];
    if ($active_room_id !== '' && isset($rooms[$active_room_id])) {
      $scope[$active_room_id] = TRUE;
    }

    $connections = [];
    if (is_array($dungeon_data['connections'] ?? NULL)) {
      $connections = array_merge($connections, $dungeon_data['connections']);
    }
    if (is_array($dungeon_data['hex_map']['connections'] ?? NULL)) {
      $connections = array_merge($connections, $dungeon_data['hex_map']['connections']);
    }
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }
      if ($active_room_id !== '' && $from_room_id !== $active_room_id && $to_room_id !== $active_room_id) {
        continue;
      }
      if (isset($rooms[$from_room_id])) {
        $scope[$from_room_id] = TRUE;
      }
      if (isset($rooms[$to_room_id])) {
        $scope[$to_room_id] = TRUE;
      }
    }

    if ($scope === [] && $active_room_id !== '') {
      $scope[$active_room_id] = TRUE;
    }
    if ($scope === [] && $rooms !== []) {
      $scope[(string) array_key_first($rooms)] = TRUE;
    }
    return array_values(array_keys($scope));
  }

}
