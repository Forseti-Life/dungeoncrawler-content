<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Coordinates streamed room-chat completion event emission and result merging.
 */
class RoomChatStreamResultCoordinator {

  /**
   * Emit streamed turn result events and final completion payload.
   *
   * @param array<string,mixed> $result
   *   Stream result payload from room chat service.
   * @param callable $emit_progress_update
   *   Callback: fn(string $stage, array $context): void
   * @param callable $build_snapshot_from_result
   *   Callback: fn(array $result, int $campaign_id): array
   * @param callable $complete_deferred_npc_interjections
   *   Callback: fn(int $campaign_id, string $room_id, string $player_message, string $gm_message, ?int $character_id): array
   */
  public function emitStreamedTurnResult(
    callable $emit,
    array $result,
    int $campaign_id,
    string $room_id,
    string $player_message,
    ?int $character_id,
    string $channel,
    string $client_request_id,
    callable $emit_progress_update,
    callable $build_snapshot_from_result,
    callable $complete_deferred_npc_interjections
  ): void {
    $result['turn_logs'] = $this->filterClientVisibleTurnLogs(
      is_array($result['turn_logs'] ?? NULL) ? $result['turn_logs'] : []
    );
    $this->emitSystemMessages($emit, $result['turn_logs']);
    $this->emitGmResponseEvent($emit, $result, $client_request_id);

    if ($this->shouldCompleteDeferredNpcTurns($result)) {
      $this->resolveDeferredNpcInterjectionsForStream(
        $emit,
        $result,
        $campaign_id,
        $room_id,
        $player_message,
        $character_id,
        $channel,
        $client_request_id,
        $emit_progress_update,
        $build_snapshot_from_result,
        $complete_deferred_npc_interjections
      );
    }

    $emit([
      'type' => 'complete',
      'data' => $result + [
        'client_request_id' => $client_request_id,
      ],
    ]);
  }

  /**
   * @param array<string,mixed> $result
   */
  protected function emitGmResponseEvent(callable $emit, array $result, string $client_request_id): void {
    if (empty($result['gm_response'])) {
      return;
    }

    $emit([
      'type' => 'gm_response',
      'data' => $result['gm_response'] + [
        'client_request_id' => $client_request_id,
      ],
    ]);
  }

  protected function emitSystemMessages(callable $emit, array $system_messages): void {
    if ($system_messages === []) {
      return;
    }

    foreach ($system_messages as $system_message) {
      $emit([
        'type' => 'system_message',
        'data' => $system_message,
      ]);
    }
  }

  protected function emitNpcInterjectionEvents(callable $emit, array $npc_messages): void {
    foreach ($npc_messages as $npc_message) {
      $emit([
        'type' => 'npc_interjection',
        'data' => $npc_message,
      ]);
    }
  }

  /**
   * @param array<string,mixed> $result
   */
  protected function shouldCompleteDeferredNpcTurns(array $result): bool {
    return !empty($result['npc_interjections_deferred'])
      && !empty($result['gm_response']['message'])
      && empty($result['gm_response']['gm_payload']['flags']['suppress_npc_interjections']);
  }

  /**
   * @param array<string,mixed> $result
   *   Stream result payload to mutate in-place.
   * @param callable $emit_progress_update
   *   Callback: fn(string $stage, array $context): void
   * @param callable $build_snapshot_from_result
   *   Callback: fn(array $result, int $campaign_id): array
   * @param callable $complete_deferred_npc_interjections
   *   Callback: fn(int $campaign_id, string $room_id, string $player_message, string $gm_message, ?int $character_id): array
   */
  protected function resolveDeferredNpcInterjectionsForStream(
    callable $emit,
    array &$result,
    int $campaign_id,
    string $room_id,
    string $player_message,
    ?int $character_id,
    string $channel,
    string $client_request_id,
    callable $emit_progress_update,
    callable $build_snapshot_from_result,
    callable $complete_deferred_npc_interjections
  ): void {
    $emit_progress_update('npc_reactions_generating', [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel,
    ] + $build_snapshot_from_result($result, $campaign_id));

    $npc_turn_result = $complete_deferred_npc_interjections(
      $campaign_id,
      $room_id,
      $player_message,
      (string) $result['gm_response']['message'],
      $character_id
    );

    if (!empty($npc_turn_result['turn_log_key'])) {
      $result['turn_log_key'] = $npc_turn_result['turn_log_key'];
    }

    $deferred_visible_turn_logs = $this->filterClientVisibleTurnLogs(
      is_array($npc_turn_result['turn_logs'] ?? NULL) ? $npc_turn_result['turn_logs'] : []
    );
    if ($deferred_visible_turn_logs !== []) {
      $result['turn_logs'] = array_values(array_merge(
        is_array($result['turn_logs'] ?? NULL) ? $result['turn_logs'] : [],
        $deferred_visible_turn_logs
      ));
      $this->emitSystemMessages($emit, $deferred_visible_turn_logs);
    }

    $npc_messages = is_array($npc_turn_result['messages'] ?? NULL) ? $npc_turn_result['messages'] : [];
    if ($npc_messages !== []) {
      $result['turn_harness'] = $npc_turn_result;
      $result['npc_interjections'] = $npc_messages;
      $this->emitNpcInterjectionEvents($emit, $npc_messages);
    }

    $quest_updates = is_array($npc_turn_result['quest_updates'] ?? NULL) ? $npc_turn_result['quest_updates'] : [];
    if ($quest_updates !== []) {
      $result['quest_updates'] = array_values(array_merge(
        is_array($result['quest_updates'] ?? NULL) ? $result['quest_updates'] : [],
        $quest_updates
      ));
    }

    $result['npc_interjections_deferred'] = FALSE;
  }

  /**
   * Filter room-turn-harness diagnostics that should not render as transcript lines.
   */
  protected function filterClientVisibleTurnLogs(array $turn_logs): array {
    $visible = [];
    foreach ($turn_logs as $turn_log) {
      if (!is_array($turn_log)) {
        continue;
      }
      if (!empty($turn_log['internal_log']) || !empty($turn_log['turn_prompt'])) {
        continue;
      }
      $visible[] = $turn_log;
    }
    return array_values($visible);
  }

}
