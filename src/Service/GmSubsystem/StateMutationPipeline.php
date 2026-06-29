<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;

/**
 * Applies authoritative state mutations and builds client-facing state diffs.
 *
 * This extraction keeps RoomChatService behavior stable while isolating the
 * mutation/diff policy from generateGmReply().
 */
class StateMutationPipeline {

  protected GameplayActionProcessor $actionProcessor;

  /**
   * Constructor.
   */
  public function __construct(GameplayActionProcessor $action_processor) {
    $this->actionProcessor = $action_processor;
  }

  /**
   * Apply character/room mutations and build state diff summary.
   *
   * @return array{
   *   dungeon_data: array,
   *   state_diff: ?array,
   *   char_diff: array,
   *   room_diff: array
   * }
   */
  public function apply(
    int|string $dungeon_id,
    int $campaign_id,
    int|string $room_index,
    array $dungeon_data,
    ?int $character_id,
    array $actions,
    array $dice_rolls,
    array $validation_errors
  ): array {
    $char_diff = [];
    $room_diff = [];
    $state_diff = NULL;

    if ($actions !== []) {
      if ($character_id) {
        $char_diff = $this->actionProcessor->applyCharacterStateChanges($character_id, $actions, $campaign_id);
      }
      $room_diff = $this->actionProcessor->applyRoomStateChanges(
        $dungeon_id,
        $campaign_id,
        $room_index,
        $dungeon_data,
        $actions
      );
      $state_diff = $this->actionProcessor->buildStateDiffSummary(
        $char_diff,
        $room_diff,
        $dice_rolls,
        $actions,
        $validation_errors
      );
    }
    elseif ($validation_errors !== []) {
      $state_diff = $this->actionProcessor->buildStateDiffSummary(
        $char_diff,
        $room_diff,
        $dice_rolls,
        $actions,
        $validation_errors
      );
    }

    return [
      'dungeon_data' => $dungeon_data,
      'state_diff' => is_array($state_diff) ? $state_diff : NULL,
      'char_diff' => $char_diff,
      'room_diff' => $room_diff,
    ];
  }

}

