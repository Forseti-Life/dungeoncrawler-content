<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatEncounterProgressService;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatEndpointFacadeOrchestrator;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatProgressStageMapper;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatPostDispatchOrchestrator;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatResponseMapper;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatStreamErrorReporter;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatStreamEnvelopeEmitter;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatStreamFlowOrchestrator;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatStreamResultCoordinator;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatWriteEndpointOrchestrator;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-side room chat API controller.
 *
 * Scope:
 * - Accept chat requests from clients and return server-managed transcript output.
 * - Route player room chat through the canonical encounter Talk action path.
 * - Stream progress/response envelopes to clients while preserving server authority.
 *
 * Non-scope:
 * - Does not decide turn/round ownership or action legality.
 * - Does not author encounter state transitions outside the authoritative action
 *   chain (GameCoordinator + phase handlers).
 */
class RoomChatController extends ControllerBase {

  protected RoomChatService $chatService;

  protected RoomChatProgressStageMapper $progressStageMapper;

  protected RoomChatStreamEnvelopeEmitter $streamEnvelopeEmitter;

  protected RoomChatStreamResultCoordinator $streamResultCoordinator;

  protected RoomChatStreamErrorReporter $streamErrorReporter;

  protected RoomChatEncounterProgressService $encounterProgressService;

  protected RoomChatResponseMapper $responseMapper;

  protected RoomChatWriteEndpointOrchestrator $writeEndpointOrchestrator;

  protected RoomChatStreamFlowOrchestrator $streamFlowOrchestrator;

  protected RoomChatEndpointFacadeOrchestrator $endpointFacadeOrchestrator;

  protected RoomChatPostDispatchOrchestrator $postDispatchOrchestrator;

  /**
   * Constructor.
   */
  public function __construct(RoomChatService $chat_service, RoomChatProgressStageMapper $progress_stage_mapper, RoomChatStreamEnvelopeEmitter $stream_envelope_emitter, RoomChatStreamResultCoordinator $stream_result_coordinator, RoomChatStreamErrorReporter $stream_error_reporter, RoomChatEncounterProgressService $encounter_progress_service, RoomChatResponseMapper $response_mapper, RoomChatWriteEndpointOrchestrator $write_endpoint_orchestrator, RoomChatStreamFlowOrchestrator $stream_flow_orchestrator, RoomChatEndpointFacadeOrchestrator $endpoint_facade_orchestrator, RoomChatPostDispatchOrchestrator $post_dispatch_orchestrator) {
    $this->chatService = $chat_service;
    $this->progressStageMapper = $progress_stage_mapper;
    $this->streamEnvelopeEmitter = $stream_envelope_emitter;
    $this->streamResultCoordinator = $stream_result_coordinator;
    $this->streamErrorReporter = $stream_error_reporter;
    $this->encounterProgressService = $encounter_progress_service;
    $this->responseMapper = $response_mapper;
    $this->writeEndpointOrchestrator = $write_endpoint_orchestrator;
    $this->streamFlowOrchestrator = $stream_flow_orchestrator;
    $this->endpointFacadeOrchestrator = $endpoint_facade_orchestrator;
    $this->postDispatchOrchestrator = $post_dispatch_orchestrator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_chat_service'),
      $container->get('dungeoncrawler_content.room_chat_progress_stage_mapper'),
      $container->get('dungeoncrawler_content.room_chat_stream_envelope_emitter'),
      $container->get('dungeoncrawler_content.room_chat_stream_result_coordinator'),
      $container->get('dungeoncrawler_content.room_chat_stream_error_reporter'),
      $container->get('dungeoncrawler_content.room_chat_encounter_progress'),
      $container->get('dungeoncrawler_content.room_chat_response_mapper'),
      $container->get('dungeoncrawler_content.room_chat_write_endpoint_orchestrator'),
      $container->get('dungeoncrawler_content.room_chat_stream_flow_orchestrator'),
      $container->get('dungeoncrawler_content.room_chat_endpoint_facade_orchestrator'),
      $container->get('dungeoncrawler_content.room_chat_post_dispatch_orchestrator')
    );
  }

  /**
   * Apply encounter round/turn prefixing when campaign context is available.
   *
   * @param array<string,mixed> $data
   *   Stage-mapped progress payload.
   * @param array<string,mixed> $context
   *   Progress context payload.
   *
   * @return array<string,mixed>
   *   Progress payload with encounter-prefixed message if applicable.
   */
  protected function applyEncounterPrefixToProgressData(array $data, int $campaign_id, array $context = []): array {
    if ($campaign_id <= 0) {
      return $data;
    }

    $round_raw = is_numeric($context['encounter_round_raw'] ?? NULL) ? (int) $context['encounter_round_raw'] : NULL;
    $turn_index_raw = is_numeric($context['encounter_turn_index_raw'] ?? NULL) ? (int) $context['encounter_turn_index_raw'] : NULL;
    $data['message'] = $this->encounterProgressService->prefixEncounterProgressMessage(
      $campaign_id,
      (string) ($data['speaker'] ?? 'System'),
      (string) ($data['message'] ?? ''),
      $round_raw,
      $turn_index_raw
    );
    return $data;
  }

  /**
   * Check campaign access and return an access-denied response when blocked.
   */
  protected function getCampaignAccessDeniedResponse(int $campaign_id): ?JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return $this->responseMapper->buildAccessDeniedResponse();
    }
    return NULL;
  }

  /**
   * Decode request JSON payload into an array.
   *
   * @throws \InvalidArgumentException
   *   Thrown when request body is not a JSON object payload.
   */
  protected function decodeJsonPayload(Request $request): array {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      throw new \InvalidArgumentException('Invalid JSON payload', 400);
    }
    return $payload;
  }

  /**
   * Parse optional character_id query parameter.
   */
  protected function getOptionalCharacterIdFromQuery(Request $request): ?int {
    return $request->query->get('character_id') ? (int) $request->query->get('character_id') : NULL;
  }

  /**
   * Get chat history for a room.
   * 
   * GET /api/campaign/{campaign_id}/room/{room_id}/chat?channel=room&character_id=85
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Chat history response.
   */
  public function getChatHistory(int $campaign_id, string $room_id, Request $request): JsonResponse {
    $channel = 'room';
    $character_id = NULL;
    try {
      $access_denied = $this->getCampaignAccessDeniedResponse($campaign_id);
      if ($access_denied !== NULL) {
        return $access_denied;
      }

      $channel = $request->query->get('channel', 'room');
      $character_id = $this->getOptionalCharacterIdFromQuery($request);

      $messages = $this->chatService->getChatHistory($campaign_id, $room_id, $channel, $character_id);
      return $this->responseMapper->buildSuccessDataResponse(
        $this->responseMapper->buildChatHistoryResponseData($room_id, $channel, $messages)
      );
    }
    catch (\InvalidArgumentException $e) {
      return $this->responseMapper->buildChatHistoryInvalidRequestResponse(
        $e,
        $campaign_id,
        $room_id,
        (string) $channel,
        is_int($character_id) ? $character_id : NULL
      );
    }
    catch (\Throwable $e) {
      $this->responseMapper->logChatHistoryFailure($e, $campaign_id, $room_id, (string) $channel, is_int($character_id) ? $character_id : NULL);
      return $this->responseMapper->buildUnhandledErrorResponse();
    }
  }

  /**
   * Post a new chat message to a room.
   * 
   * POST /api/campaign/{campaign_id}/room/{room_id}/chat
   * 
   * Payload: {
   *   "speaker": "Name",
   *   "message": "...",
   *   "type": "player",
   *   "character_id": 123,
   *   "channel": "room",
   *   "stream": true
   * }
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * 
   * @return \Symfony\Component\HttpFoundation\Response
   *   Standard JSON response or streamed NDJSON room-chat response.
   */
  public function postChatMessage(int $campaign_id, string $room_id, Request $request): Response {
    $request_context = [];

    try {
      $access_denied = $this->getCampaignAccessDeniedResponse($campaign_id);
      if ($access_denied !== NULL) {
        return $access_denied;
      }

      try {
        $payload = $this->decodeJsonPayload($request);
      }
      catch (\InvalidArgumentException $e) {
        return $this->responseMapper->buildInvalidRequestResponse('Invalid JSON payload', 400);
      }
      $request_context = $this->resolvePostChatRequestContextFromPayload($payload);

      return $this->postDispatchOrchestrator->dispatchFromContext(
        $campaign_id,
        $room_id,
        $request_context,
        function (int $current_campaign_id, string $current_room_id, string $speaker, string $message, string $type, ?int $character_id, string $channel, string $client_request_id): Response {
          return $this->streamChatMessage($current_campaign_id, $current_room_id, $speaker, $message, $type, $character_id, $channel, $client_request_id);
        },
        function (int $current_campaign_id, string $current_room_id, ?int $character_id, string $channel, string $client_request_id): Response {
          return $this->streamQueuedGmContinuation($current_campaign_id, $current_room_id, $character_id, $channel, $client_request_id);
        },
        function (array $result, string $client_request_id): Response {
          return $this->responseMapper->buildSuccessDataResponse($result, $client_request_id);
        }
      );
    }
    catch (\InvalidArgumentException $e) {
      $error_context = $this->extractPostChatErrorContext($request_context);
      return $this->responseMapper->buildPostChatInvalidRequestResponse(
        $e,
        $campaign_id,
        $room_id,
        $error_context['channel'],
        $error_context['character_id'],
        $error_context['client_request_id']
      );
    }
    catch (\Throwable $e) {
      $error_context = $this->extractPostChatErrorContext($request_context);
      return $this->responseMapper->buildPostChatFailureResponse(
        $e,
        $campaign_id,
        $room_id,
        $error_context['channel'],
        $error_context['character_id'],
        $error_context['client_request_id'],
        $error_context['speaker'],
        $error_context['type']
      );
    }
  }

  /**
   * Normalize post-chat request payload to canonical context.
   *
   * @param array<string,mixed> $payload
   *   Decoded request payload.
   *
   * @return array<string,mixed>
   *   Normalized post-chat request context.
   */
  protected function resolvePostChatRequestContextFromPayload(array $payload): array {
    return $this->writeEndpointOrchestrator->normalizePostChatPayload($payload);
  }

  /**
   * Build canonical post-chat error context from normalized request payload.
   *
   * @param array<string,mixed> $request_context
   *   Normalized post-chat request context.
   *
   * @return array{speaker:string,type:string,channel:string,character_id:?int,client_request_id:string}
   *   Canonical error context for post-chat error response mapping.
   */
  protected function extractPostChatErrorContext(array $request_context): array {
    return [
      'speaker' => (string) ($request_context['speaker'] ?? ''),
      'type' => (string) ($request_context['type'] ?? 'player'),
      'channel' => (string) ($request_context['channel'] ?? 'room'),
      'character_id' => isset($request_context['character_id']) ? (int) $request_context['character_id'] : NULL,
      'client_request_id' => (string) ($request_context['client_request_id'] ?? ''),
    ];
  }

  /**
   * Suggest the next in-character player chat message for automation.
   */
  public function suggestPlayerAutomationMessage(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      $access_denied = $this->getCampaignAccessDeniedResponse($campaign_id);
      if ($access_denied !== NULL) {
        return $access_denied;
      }

      $payload = $this->decodeJsonPayload($request);
      $result = $this->endpointFacadeOrchestrator->suggestPlayerAutomationMessage($campaign_id, $room_id, $payload);
      return $this->responseMapper->buildSuccessDataResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->responseMapper->buildInvalidRequestResponse($e->getMessage(), (int) $e->getCode() ?: 400);
    }
    catch (\Throwable $e) {
      return $this->responseMapper->buildUnhandledErrorResponse();
    }
  }

  /**
   * Stream a room chat send so the client can render staged results.
   */
  protected function streamChatMessage(
    int $campaign_id,
    string $room_id,
    string $speaker,
    string $message,
    string $type,
    ?int $character_id,
    string $channel,
    string $client_request_id = ''
  ): StreamedResponse {
    return $this->createStreamedTurnResponse(
      function (callable $emit) use ($campaign_id, $room_id, $speaker, $message, $type, $character_id, $channel, $client_request_id): void {
        $this->streamFlowOrchestrator->handleStreamChatMessageFlow(
          $emit,
          $campaign_id,
          $room_id,
          $speaker,
          $message,
          $type,
          $character_id,
          $channel,
          $client_request_id,
          $this->buildStreamProgressForwarder(),
          $this->buildStreamResultForwarder()
        );
      },
      [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'speaker' => $speaker,
        'message_length' => strlen($message),
        'type' => $type,
        'character_id' => $character_id,
        'channel' => $channel,
        'client_request_id' => $client_request_id,
        'stream_mode' => 'post_message',
      ]
    );
  }

  /**
   * Stream a follow-up GM turn for queued player room messages.
   */
  protected function streamQueuedGmContinuation(
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    string $channel = 'room',
    string $client_request_id = ''
  ): StreamedResponse {
    return $this->createStreamedTurnResponse(
      function (callable $emit) use ($campaign_id, $room_id, $character_id, $channel, $client_request_id): void {
        $this->streamFlowOrchestrator->handleStreamQueuedContinuationFlow(
          $emit,
          $campaign_id,
          $room_id,
          $character_id,
          $channel,
          $client_request_id,
          $this->buildStreamProgressForwarder(),
          $this->buildStreamResultForwarder()
        );
      },
      [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'character_id' => $character_id,
        'channel' => $channel,
        'client_request_id' => $client_request_id,
        'stream_mode' => 'queued_continuation',
      ]
    );
  }

  /**
   * Build stream-flow callback that forwards progress events through controller mapping.
   */
  protected function buildStreamProgressForwarder(): callable {
    return function (callable $stream_emit, string $request_id, string $stage, array $context = []): void {
      $this->emitProgressUpdate($stream_emit, $request_id, $stage, $context);
    };
  }

  /**
   * Build stream-flow callback that forwards completed turn results.
   */
  protected function buildStreamResultForwarder(): callable {
    return function (callable $stream_emit, array $result, int $current_campaign_id, string $current_room_id, string $player_message_for_followup, ?int $current_character_id, string $current_channel, string $request_id): void {
      $this->emitStreamedTurnResult(
        $stream_emit,
        $result,
        $current_campaign_id,
        $current_room_id,
        $player_message_for_followup,
        $current_character_id,
        $current_channel,
        $request_id
      );
    };
  }


  /**
   * Create an NDJSON streaming response with shared error handling.
   */
  protected function createStreamedTurnResponse(callable $stream_callback, array $context = []): StreamedResponse {
    return $this->streamEnvelopeEmitter->createStreamedResponse(
      $stream_callback,
      function (callable $emit, \Throwable $e) use ($context): void {
        $this->emitStreamError($emit, $e, $context);
      }
    );
  }

  /**
   * Emit a completed streamed turn result, including turn-log system messages.
   *
   * Streamed room-chat responses deliver GM text, turn-log `system_message`
   * events, then any deferred NPC reactions so the browser sees the same actor
   * order diagnostics that are persisted into room chat history.
   */
  protected function emitStreamedTurnResult(
    callable $emit,
    array $result,
    int $campaign_id,
    string $room_id,
    string $player_message,
    ?int $character_id,
    string $channel,
    string $client_request_id
  ): void {
    $this->streamResultCoordinator->emitStreamedTurnResult(
      $emit,
      $result,
      $campaign_id,
      $room_id,
      $player_message,
      $character_id,
      $channel,
      $client_request_id,
      function (string $stage, array $context = []) use ($emit, $client_request_id): void {
        $this->emitProgressUpdate($emit, $client_request_id, $stage, $context);
      },
      function (array $result_payload, int $current_campaign_id): array {
        return $this->encounterProgressService->buildEncounterProgressSnapshotFromResult($result_payload, $current_campaign_id);
      },
      function (int $current_campaign_id, string $current_room_id, string $current_player_message, string $gm_message, ?int $current_character_id): array {
        return $this->chatService->completeDeferredNpcInterjections(
          $current_campaign_id,
          $current_room_id,
          $current_player_message,
          $gm_message,
          $current_character_id
        );
      }
    );
  }

  /**
   * Emit a normalized streamed error payload.
   */
  protected function emitStreamError(callable $emit, \Throwable $e, array $context = []): void {
    $this->streamErrorReporter->emit($emit, $e, $context);
  }

  /**
   * Emit a client-facing progress event when a stage maps to visible status text.
   */
  protected function emitProgressUpdate(callable $emit, string $client_request_id, string $stage, array $context = []): void {
    $payload = $this->buildProgressEventData($stage, $client_request_id, $context);
    if ($payload === NULL) {
      return;
    }

    $emit([
      'type' => 'thinking',
      'data' => $payload,
    ]);
  }

  /**
   * Convert service/controller progress stages into UI-facing progress text.
   */
  protected function buildProgressEventData(string $stage, string $client_request_id, array $context = []): ?array {
    $channel = (string) ($context['channel'] ?? 'room');
    $campaign_id = isset($context['campaign_id']) ? (int) $context['campaign_id'] : 0;
    $data = $this->progressStageMapper->map($stage, $channel, $client_request_id, $context);

    if ($data === NULL) {
      return NULL;
    }

    return $this->applyEncounterPrefixToProgressData($data, $campaign_id, $context);
  }

  /**
   * Get available channels for a room.
   *
   * GET /api/campaign/{campaign_id}/room/{room_id}/channels?character_id=85
   */
  public function getChannels(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      $access_denied = $this->getCampaignAccessDeniedResponse($campaign_id);
      if ($access_denied !== NULL) {
        return $access_denied;
      }

      $character_id = $this->getOptionalCharacterIdFromQuery($request);
      $result = $this->endpointFacadeOrchestrator->getChannelsForRoom($campaign_id, $room_id, $character_id);
      return $this->responseMapper->buildSuccessDataResponse($result);
    }
    catch (\Throwable $e) {
      return $this->responseMapper->buildUnhandledErrorResponse();
    }
  }

  /**
   * Open a new channel in a room.
   *
   * POST /api/campaign/{campaign_id}/room/{room_id}/channels
   *
   * Payload: {
   *   "channel_key": "whisper:goblin_1",
   *   "opened_by": "85",
   *   "target_entity": "goblin_guard_1",
   *   "target_name": "Goblin Guard",
   *   "source_ability": "whisper"
   * }
   */
  public function openChannel(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      $access_denied = $this->getCampaignAccessDeniedResponse($campaign_id);
      if ($access_denied !== NULL) {
        return $access_denied;
      }

      $payload = $this->decodeJsonPayload($request);
      $result = $this->endpointFacadeOrchestrator->openChannelFromPayload($campaign_id, $room_id, $payload);

      $status = $result['success'] ? 200 : 400;
      return $this->responseMapper->buildSuccessDataResponse($result)->setStatusCode($status);
    }
    catch (\InvalidArgumentException $e) {
      return $this->responseMapper->buildInvalidRequestResponse($e->getMessage(), (int) $e->getCode() ?: 400);
    }
    catch (\Throwable $e) {
      return $this->responseMapper->buildUnhandledErrorResponse();
    }
  }

  /**
   * Close a channel in a room.
   *
   * DELETE /api/campaign/{campaign_id}/room/{room_id}/channels/{channel_key}
   */
  public function closeChannel(int $campaign_id, string $room_id, string $channel_key): JsonResponse {
    try {
      $access_denied = $this->getCampaignAccessDeniedResponse($campaign_id);
      if ($access_denied !== NULL) {
        return $access_denied;
      }

      $closed = $this->endpointFacadeOrchestrator->closeChannel($campaign_id, $room_id, $channel_key);
      return $this->responseMapper->buildSuccessDataResponse([
        'channel_key' => $channel_key,
        'closed' => $closed,
      ]);
    }
    catch (\Throwable $e) {
      return $this->responseMapper->buildUnhandledErrorResponse();
    }
  }

}
