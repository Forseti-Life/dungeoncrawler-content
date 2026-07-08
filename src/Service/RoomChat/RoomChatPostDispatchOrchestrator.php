<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Symfony\Component\HttpFoundation\Response;

/**
 * Coordinates post-chat route context branching and write dispatch.
 */
class RoomChatPostDispatchOrchestrator {

  protected RoomChatWriteEndpointOrchestrator $writeEndpointOrchestrator;

  public function __construct(RoomChatWriteEndpointOrchestrator $write_endpoint_orchestrator) {
    $this->writeEndpointOrchestrator = $write_endpoint_orchestrator;
  }

  /**
   * Dispatch canonical post-chat request context into stream/continue/write paths.
   *
   * @param array<string,mixed> $request_context
   *   Canonical post-chat request context.
   * @param callable $stream_chat_handler
   *   Callback: fn(int $campaign_id, string $room_id, string $speaker, string $message, string $type, ?int $character_id, string $channel, string $client_request_id): Response
   * @param callable $stream_continue_handler
   *   Callback: fn(int $campaign_id, string $room_id, ?int $character_id, string $channel, string $client_request_id): Response
   * @param callable $build_success_response
   *   Callback: fn(array $result, string $client_request_id): Response
   */
  public function dispatchFromContext(
    int $campaign_id,
    string $room_id,
    array $request_context,
    callable $stream_chat_handler,
    callable $stream_continue_handler,
    callable $build_success_response
  ): Response {
    $speaker = (string) ($request_context['speaker'] ?? '');
    $message = (string) ($request_context['message'] ?? '');
    $type = (string) ($request_context['type'] ?? 'player');
    $character_id = isset($request_context['character_id']) ? (int) $request_context['character_id'] : NULL;
    $channel = (string) ($request_context['channel'] ?? 'room');
    $client_request_id = (string) ($request_context['client_request_id'] ?? '');
    $stream = !empty($request_context['stream']);
    $suppress_gm = !empty($request_context['suppress_gm']);
    $continue_gm = !empty($request_context['continue_gm']);

    if ($stream && $continue_gm) {
      return $stream_continue_handler($campaign_id, $room_id, $character_id, $channel, $client_request_id);
    }

    if ($stream) {
      return $stream_chat_handler($campaign_id, $room_id, $speaker, $message, $type, $character_id, $channel, $client_request_id);
    }

    if ($continue_gm) {
      $result = $this->writeEndpointOrchestrator->continueQueuedRoomConversation($campaign_id, $room_id, $character_id, $channel);
      return $build_success_response($result, $client_request_id);
    }

    $result = $this->writeEndpointOrchestrator->dispatchStandardPostChatWrite(
      $campaign_id,
      $room_id,
      $speaker,
      $message,
      $type,
      $character_id,
      $channel,
      $suppress_gm
    );

    return $build_success_response($result, $client_request_id);
  }

}
