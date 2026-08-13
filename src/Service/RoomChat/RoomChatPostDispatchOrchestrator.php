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
    $overall_started_at = hrtime(true);
    $timings = [];
    $speaker = (string) ($request_context['speaker'] ?? '');
    $message = (string) ($request_context['message'] ?? '');
    $type = (string) ($request_context['type'] ?? 'player');
    $character_id = isset($request_context['character_id']) ? (int) $request_context['character_id'] : NULL;
    $channel = (string) ($request_context['channel'] ?? 'room');
    $client_request_id = (string) ($request_context['client_request_id'] ?? '');
    $stream = !empty($request_context['stream']);
    $suppress_gm = !empty($request_context['suppress_gm']);
    $continue_gm = !empty($request_context['continue_gm']);
    $options = [
      'response_mode' => (string) ($request_context['response_mode'] ?? 'actor_scoped'),
      'include_legacy_overlay' => !empty($request_context['include_legacy_overlay']),
    ];

    if ($stream && $continue_gm) {
      return $stream_continue_handler($campaign_id, $room_id, $character_id, $channel, $client_request_id);
    }

    if ($stream) {
      return $stream_chat_handler($campaign_id, $room_id, $speaker, $message, $type, $character_id, $channel, $client_request_id, $options);
    }

    if ($continue_gm) {
      $stage_started_at = hrtime(true);
      $result = $this->writeEndpointOrchestrator->continueQueuedRoomConversation($campaign_id, $room_id, $character_id, $channel);
      $timings['continue_queued_conversation_ms'] = round((hrtime(true) - $stage_started_at) / 1000000, 2);
      $timings['branch'] = 'continue';
      $timings['total_ms'] = round((hrtime(true) - $overall_started_at) / 1000000, 2);
      $result = $this->appendInvocationTiming($result, 'room_chat_post_dispatch', $timings, $overall_started_at);
      return $build_success_response($result, $client_request_id);
    }

    $stage_started_at = hrtime(true);
    $result = $this->writeEndpointOrchestrator->dispatchStandardPostChatWrite(
      $campaign_id,
      $room_id,
      $speaker,
      $message,
      $type,
      $character_id,
      $channel,
      $suppress_gm,
      $options
    );
    $timings['dispatch_standard_post_chat_write_ms'] = round((hrtime(true) - $stage_started_at) / 1000000, 2);
    $timings['branch'] = 'standard_write';
    $timings['total_ms'] = round((hrtime(true) - $overall_started_at) / 1000000, 2);
    $result = $this->appendInvocationTiming($result, 'room_chat_post_dispatch', $timings, $overall_started_at);

    return $build_success_response($result, $client_request_id);
  }

  /**
   * Attach invocation timing data to one response payload.
   *
   * @param array<string,mixed> $payload
   * @param array<string,mixed> $stages
   *
   * @return array<string,mixed>
   */
  protected function appendInvocationTiming(array $payload, string $scope, array $stages, int $overall_started_at): array {
    $timing = is_array($payload['invocation_timing'] ?? NULL) ? $payload['invocation_timing'] : [];
    $timing[$scope] = [
      'total_ms' => round((hrtime(true) - $overall_started_at) / 1000000, 2),
      'stages_ms' => $stages,
    ];
    $payload['invocation_timing'] = $timing;
    return $payload;
  }

}
