<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Navigation-owned application orchestrator for transition execution.
 */
class NavigationApplicationOrchestrator {

  protected NavigationTransitionPipeline $navigationTransitionPipeline;

  public function __construct(NavigationTransitionPipeline $navigation_transition_pipeline) {
    $this->navigationTransitionPipeline = $navigation_transition_pipeline;
  }

  /**
   * Apply navigation transition orchestration for one room turn.
   *
   * @return array{
   *   navigation_result: ?array,
   *   dungeon_data: array,
   *   room_index: int|string,
   *   navigation_success: bool
   * }
   */
  public function applyNavigationTransition(
    array $actions,
    int $campaign_id,
    string $room_id,
    int|string $dungeon_id,
    array $room_meta,
    array $dungeon_data,
    int|string $room_index,
    string $narrative
  ): array {
    return $this->navigationTransitionPipeline->apply(
      $actions,
      $campaign_id,
      $room_id,
      $dungeon_id,
      $room_meta,
      $dungeon_data,
      $room_index,
      $narrative
    );
  }

}

