<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

use Drupal\dungeoncrawler_content\Service\GmOrchestrationBrokerService;

/**
 * Runs canonical authoritative GM actions through the broker.
 *
 * This is a behavior-preserving extraction from RoomChatService so canonical
 * execution policy can evolve behind a dedicated subsystem boundary.
 */
class CanonicalExecutionPipeline {

  protected GmOrchestrationBrokerService $gmOrchestrationBroker;

  /**
   * Constructor.
   */
  public function __construct(GmOrchestrationBrokerService $gm_orchestration_broker) {
    $this->gmOrchestrationBroker = $gm_orchestration_broker;
  }

  /**
   * Execute canonical authoritative actions and normalize output shape.
   *
   * @return array{
   *   actions: array,
   *   canonical_results: array,
   *   validation_errors: array,
   *   dungeon_data: array,
   *   error_count: int
   * }
   */
  public function execute(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    ?int $character_id,
    array $actions,
    array $dungeon_data,
    array $validation_errors
  ): array {
    $canonical_results = [
      'quest_turn_in' => [],
      'combat_initiation' => NULL,
    ];
    if ($actions === []) {
      return [
        'actions' => $actions,
        'canonical_results' => $canonical_results,
        'validation_errors' => $validation_errors,
        'dungeon_data' => $dungeon_data,
        'error_count' => 0,
      ];
    }

    $canonical_execution = $this->gmOrchestrationBroker->executeCanonicalAuthoritativeActions(
      $campaign_id,
      $room_id,
      $room_meta,
      $character_id,
      $actions,
      $dungeon_data
    );

    $actions = is_array($canonical_execution['actions'] ?? NULL)
      ? $canonical_execution['actions']
      : $actions;
    $canonical_results = is_array($canonical_execution['results'] ?? NULL)
      ? $canonical_execution['results']
      : $canonical_results;
    $errors = is_array($canonical_execution['errors'] ?? NULL)
      ? $canonical_execution['errors']
      : [];
    if ($errors !== []) {
      $validation_errors = array_merge($validation_errors, $errors);
    }
    if (!empty($canonical_execution['combat_runtime_snapshot']) && is_array($canonical_execution['combat_runtime_snapshot'])) {
      $dungeon_data = $this->applyRuntimeSnapshotToDungeonData(
        $dungeon_data,
        $canonical_execution['combat_runtime_snapshot']
      );
    }

    return [
      'actions' => $actions,
      'canonical_results' => $canonical_results,
      'validation_errors' => $validation_errors,
      'dungeon_data' => $dungeon_data,
      'error_count' => count($errors),
    ];
  }

  /**
   * Apply compact runtime snapshot updates to the local dungeon_data context.
   *
   * This keeps downstream mutation processing aligned with authoritative
   * coordinator transitions without requiring full payload compatibility blobs.
   */
  protected function applyRuntimeSnapshotToDungeonData(array $dungeon_data, array $runtime_snapshot): array {
    $game_state = is_array($runtime_snapshot['game_state'] ?? NULL)
      ? $runtime_snapshot['game_state']
      : NULL;
    if ($game_state !== NULL) {
      $dungeon_data['game_state'] = $game_state;
    }

    $active_room_id = trim((string) (
      $runtime_snapshot['active_room_id']
      ?? ($runtime_snapshot['active_room']['room_id'] ?? '')
    ));
    if ($active_room_id !== '') {
      $dungeon_data['active_room_id'] = $active_room_id;
    }

    if (is_numeric($runtime_snapshot['state_version'] ?? NULL)) {
      if (!is_array($dungeon_data['game_state'] ?? NULL)) {
        $dungeon_data['game_state'] = [];
      }
      $dungeon_data['game_state']['state_version'] = (int) $runtime_snapshot['state_version'];
    }

    return $dungeon_data;
  }

}
