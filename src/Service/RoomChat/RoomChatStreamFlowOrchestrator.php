<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\dungeoncrawler_content\Service\RoomChatService;

/**
 * Coordinates streamed room-chat write/continuation flow orchestration.
 */
class RoomChatStreamFlowOrchestrator {

  protected RoomChatService $chatService;

  protected RoomChatEncounterProgressService $encounterProgressService;

  protected RoomChatWriteEndpointOrchestrator $writeEndpointOrchestrator;

  public function __construct(
    RoomChatService $chat_service,
    RoomChatEncounterProgressService $encounter_progress_service,
    RoomChatWriteEndpointOrchestrator $write_endpoint_orchestrator
  ) {
    $this->chatService = $chat_service;
    $this->encounterProgressService = $encounter_progress_service;
    $this->writeEndpointOrchestrator = $write_endpoint_orchestrator;
  }

  /**
   * Execute streamed room-chat post flow and emit ordered stream events.
   */
  public function handleStreamChatMessageFlow(
    callable $emit,
    int $campaign_id,
    string $room_id,
    string $speaker,
    string $message,
    string $type,
    ?int $character_id,
    string $channel,
    string $client_request_id,
    callable $emit_progress_update,
    callable $emit_streamed_turn_result
  ): void {
    $encounter_progress_ctx = $this->encounterProgressService->buildEncounterProgressSnapshot($campaign_id);
    $emit_progress_update($emit, $client_request_id, 'room_request_started', [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel,
    ] + $encounter_progress_ctx);

    $posted_message = NULL;
    $player_message_for_followup = $message;

    if ($this->writeEndpointOrchestrator->isPlayerRoomChat($type, $channel)) {
      $result = $this->writeEndpointOrchestrator->postPlayerRoomChatViaEncounterTalk(
        $campaign_id,
        $room_id,
        $character_id,
        $speaker,
        $message,
        TRUE,
        FALSE
      );
      $posted_message = is_array($result['message'] ?? NULL) ? $result['message'] : NULL;
      if (is_array($posted_message) && isset($posted_message['message']) && is_string($posted_message['message'])) {
        $player_message_for_followup = $posted_message['message'];
      }
    }
    else {
      $this->emitPlayerAck($emit, [
        'speaker' => $speaker,
        'message' => $message,
        'type' => $type,
        'channel' => $channel,
      ], $client_request_id);

      $result = $this->chatService->postMessage(
        $campaign_id,
        $room_id,
        $speaker,
        $message,
        $type,
        $character_id,
        $channel,
        TRUE,
        FALSE,
        $this->buildStreamProgressCallback($emit, $client_request_id, [
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'channel' => $channel,
        ] + $encounter_progress_ctx, $emit_progress_update)
      );
    }

    if ($posted_message !== NULL) {
      $this->emitPlayerAck($emit, [
        'speaker' => (string) ($posted_message['speaker'] ?? $speaker),
        'message' => (string) ($posted_message['message'] ?? $message),
        'type' => (string) ($posted_message['type'] ?? $type),
        'channel' => (string) ($posted_message['channel'] ?? $channel),
      ], $client_request_id);
    }

    $emit_streamed_turn_result(
      $emit,
      $result,
      $campaign_id,
      $room_id,
      $player_message_for_followup,
      $character_id,
      $channel,
      $client_request_id
    );
  }

  /**
   * Execute streamed queued-continuation flow and emit ordered stream events.
   */
  public function handleStreamQueuedContinuationFlow(
    callable $emit,
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    string $channel,
    string $client_request_id,
    callable $emit_progress_update,
    callable $emit_streamed_turn_result
  ): void {
    $encounter_progress_ctx = $this->encounterProgressService->buildEncounterProgressSnapshot($campaign_id);
    $emit_progress_update($emit, $client_request_id, 'queued_continuation_started', [
      'channel' => $channel,
    ] + $encounter_progress_ctx);

    $result = $this->chatService->continueQueuedRoomConversation(
      $campaign_id,
      $room_id,
      $character_id,
      $channel,
      TRUE,
      $this->buildStreamProgressCallback($emit, $client_request_id, [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'channel' => $channel,
      ] + $encounter_progress_ctx, $emit_progress_update)
    );

    $emit_streamed_turn_result(
      $emit,
      $result,
      $campaign_id,
      $room_id,
      (string) ($result['queued_player_summary'] ?? ''),
      $character_id,
      $channel,
      $client_request_id
    );
  }

  /**
   * @param array<string,string> $ack_data
   *   Ack payload fields (speaker, message, type, channel).
   */
  protected function emitPlayerAck(callable $emit, array $ack_data, string $client_request_id): void {
    $emit([
      'type' => 'player_ack',
      'data' => [
        'speaker' => (string) ($ack_data['speaker'] ?? ''),
        'message' => (string) ($ack_data['message'] ?? ''),
        'type' => (string) ($ack_data['type'] ?? 'player'),
        'channel' => (string) ($ack_data['channel'] ?? 'room'),
        'client_request_id' => $client_request_id,
      ],
    ]);
  }

  /**
   * Build a shared streaming progress callback.
   */
  protected function buildStreamProgressCallback(
    callable $emit,
    string $client_request_id,
    array $base_context,
    callable $emit_progress_update
  ): callable {
    return function (array $progress) use ($emit, $client_request_id, $base_context, $emit_progress_update): void {
      $progress_context = is_array($progress['context'] ?? NULL) ? $progress['context'] : [];
      $context = $base_context + $progress_context;
      $emit_progress_update($emit, $client_request_id, (string) ($progress['stage'] ?? ''), $context);
    };
  }

}
