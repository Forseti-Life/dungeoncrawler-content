<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Psr\Log\LoggerInterface;
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

  protected ?GameCoordinatorService $coordinator;

  protected ?GameMasterSubsystemService $gmSubsystem;

  protected LoggerInterface $logger;

  /**
   * Constructor.
   */
  public function __construct(RoomChatService $chat_service, ?GameCoordinatorService $coordinator, ?GameMasterSubsystemService $gm_subsystem, LoggerInterface $logger) {
    $this->chatService = $chat_service;
    $this->coordinator = $coordinator;
    $this->gmSubsystem = $gm_subsystem;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_chat_service'),
      NULL,
      NULL,
      $container->get('logger.factory')->get('dungeoncrawler_chat')
    );
  }

  /**
   * Resolve the coordinator lazily so read-only room history requests do not
   * depend on the full encounter runtime during controller construction.
   */
  protected function getCoordinator(): GameCoordinatorService {
    if (!$this->coordinator) {
      $service = \Drupal::service('dungeoncrawler_content.game_coordinator');
      if (!$service instanceof GameCoordinatorService) {
        throw new \RuntimeException('Game coordinator service is unavailable.');
      }
      $this->coordinator = $service;
    }

    return $this->coordinator;
  }

  /**
   * Resolve the GM subsystem lazily so read-only room history requests do not
   * depend on the full chat action graph during controller construction.
   */
  protected function getGmSubsystem(): GameMasterSubsystemService {
    if (!$this->gmSubsystem) {
      $service = \Drupal::service('dungeoncrawler_content.game_master_subsystem');
      if (!$service instanceof GameMasterSubsystemService) {
        throw new \RuntimeException('Game Master subsystem service is unavailable.');
      }
      $this->gmSubsystem = $service;
    }

    return $this->gmSubsystem;
  }

  /**
   * Build the standard success wrapper for room-chat JSON responses.
   */
  protected function buildSuccessDataResponse(array $data, ?string $client_request_id = NULL): JsonResponse {
    if ($client_request_id !== NULL && $client_request_id !== '') {
      $data['client_request_id'] = $client_request_id;
    }

    $response = new JsonResponse(NULL);
    $response->setEncodingOptions($response->getEncodingOptions() | JSON_INVALID_UTF8_SUBSTITUTE);
    $response->setData([
      'success' => TRUE,
      'data' => $data,
    ]);
    return $response;
  }

  protected function isPlayerRoomChat(string $type, string $channel): bool {
    return $type === 'player' && $channel === 'room';
  }

  /**
   * Route player room chat through the GM subsystem.
   */
  protected function postPlayerRoomChatViaEncounterTalk(
    int $campaign_id,
    string $requested_room_id,
    ?int $character_id,
    string $speaker,
    string $message,
    bool $defer_npc_interjections = FALSE,
    bool $suppress_gm = FALSE
  ): array {
    $result = $this->getGmSubsystem()->handlePlayerRoomChat(
      $campaign_id,
      $requested_room_id,
      $character_id,
      $message,
      $defer_npc_interjections,
      $suppress_gm,
      $speaker
    );

    return $result;
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
    try {
      // Verify user has access to campaign
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }

      $channel = $request->query->get('channel', 'room');
      $character_id = $request->query->get('character_id') ? (int) $request->query->get('character_id') : NULL;

      $messages = $this->chatService->getChatHistory($campaign_id, $room_id, $channel, $character_id);

      $response = new JsonResponse(NULL);
      $response->setEncodingOptions($response->getEncodingOptions() | JSON_INVALID_UTF8_SUBSTITUTE);
      $response->setData([
        'success' => TRUE,
        'data' => [
          'roomId' => $room_id,
          'channel' => $channel,
          'messages' => $messages,
        ],
      ]);
      return $response;
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 500;
      $this->logger->warning('Room chat history request rejected: campaign={campaign_id} room={room_id} channel={channel} character_id={character_id} status={status} message={message}', [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'channel' => isset($channel) ? (string) $channel : 'room',
        'character_id' => isset($character_id) ? (int) ($character_id ?? 0) : 0,
        'status' => $status,
        'message' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => $status === 404 ? 'Dungeon not found' : 'Invalid request',
      ], $status);
    }
    catch (\Throwable $e) {
      $this->logger->error('Room chat history request failed: campaign={campaign_id} room={room_id} channel={channel} character_id={character_id} exception={exception} message={message}', [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'channel' => isset($channel) ? (string) $channel : 'room',
        'character_id' => isset($character_id) ? (int) ($character_id ?? 0) : 0,
        'exception' => get_class($e),
        'message' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An error occurred',
      ], 500);
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
    try {
      // Verify user has access to campaign
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }

      // Parse request body
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON payload',
        ], 400);
      }

      $request_context = $this->normalizePostChatPayload($payload);
      $speaker = $request_context['speaker'];
      $message = $request_context['message'];
      $type = $request_context['type'];
      $character_id = $request_context['character_id'];
      $channel = $request_context['channel'];
      $client_request_id = $request_context['client_request_id'];
      $stream = $request_context['stream'];
      $suppress_gm = $request_context['suppress_gm'];
      $continue_gm = $request_context['continue_gm'];

      if ($stream && $continue_gm) {
        return $this->streamQueuedGmContinuation($campaign_id, $room_id, $character_id, $channel, $client_request_id);
      }

      if ($stream) {
        return $this->streamChatMessage($campaign_id, $room_id, $speaker, $message, $type, $character_id, $channel, $client_request_id);
      }

      if ($continue_gm) {
        $result = $this->chatService->continueQueuedRoomConversation(
          $campaign_id,
          $room_id,
          $character_id,
          $channel
        );

        return $this->buildSuccessDataResponse($result, $client_request_id);
      }

      if ($this->isPlayerRoomChat($type, $channel)) {
        $result = $this->postPlayerRoomChatViaEncounterTalk(
          $campaign_id,
          $room_id,
          $character_id,
          $speaker,
          $message,
          FALSE,
          $suppress_gm
        );
      }
      else {
        $result = $this->chatService->postMessage(
          $campaign_id,
          $room_id,
          $speaker,
          $message,
          $type,
          $character_id,
          $channel,
          FALSE,
          $suppress_gm
        );
      }

      return $this->buildSuccessDataResponse($result, $client_request_id);
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 400;
      $debug_id = 'roomchat-' . substr(hash('sha256', microtime(TRUE) . '|' . random_int(0, PHP_INT_MAX)), 0, 12);
      $this->logger->warning(
        'Room chat POST rejected [{debug_id}] campaign={campaign_id} room={room_id} channel={channel} character={character_id} request={client_request_id}: {message}',
        [
          'debug_id' => $debug_id,
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'channel' => $channel,
          'character_id' => $character_id,
          'client_request_id' => $client_request_id,
          'message' => $e->getMessage(),
          'exception' => $e,
        ]
      );
      return new JsonResponse([
        'success' => FALSE,
        'error' => $debug_id !== '' ? $e->getMessage() . ' [debug ' . $debug_id . ']' : $e->getMessage(),
        'debug' => [
          'debug_id' => $debug_id,
          'client_request_id' => $client_request_id,
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'character_id' => $character_id,
          'channel' => $channel,
          'status' => $status,
          'stream_mode' => 'json_post',
          'exception_class' => get_class($e),
          'message' => $e->getMessage(),
        ],
      ], $status);
    }
    catch (\Throwable $e) {
      $debug_id = 'roomchat-' . substr(hash('sha256', microtime(TRUE) . '|' . random_int(0, PHP_INT_MAX)), 0, 12);
      $this->logger->error(
        'Room chat POST failed [{debug_id}] campaign={campaign_id} room={room_id} channel={channel} character={character_id} request={client_request_id}: {message}',
        [
          'debug_id' => $debug_id,
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'channel' => $channel,
          'character_id' => $character_id,
          'client_request_id' => $client_request_id,
          'message' => $e->getMessage(),
          'exception_class' => get_class($e),
          'exception' => $e,
          'speaker' => $speaker,
          'type' => $type,
        ]
      );
      return new JsonResponse([
        'success' => FALSE,
        'error' => $debug_id !== '' ? 'An error occurred [debug ' . $debug_id . ']' : 'An error occurred',
        'debug' => [
          'debug_id' => $debug_id,
          'client_request_id' => $client_request_id,
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'character_id' => $character_id,
          'channel' => $channel,
          'status' => 500,
          'stream_mode' => 'json_post',
          'exception_class' => get_class($e),
          'message' => $e->getMessage(),
        ],
      ], 500);
    }
  }

  /**
   * Normalize room-chat POST payload into canonical request context.
   *
   * @return array<string, mixed>
   *   Normalized payload fields used by the post-chat route.
   */
  protected function normalizePostChatPayload(array $payload): array {
    $speaker = (string) ($payload['speaker'] ?? '');
    $message = (string) ($payload['message'] ?? '');
    $type = (string) ($payload['type'] ?? 'player');
    $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : NULL;
    $channel = (string) ($payload['channel'] ?? 'room');
    $client_request_id = (string) ($payload['client_request_id'] ?? '');
    $is_player_turn = $type === 'player';

    // Room transcript lines are encounter-governed: clients cannot inject NPC/system
    // lines into the room channel. Player room chat must route via the canonical
    // encounter Talk action.
    if ($channel === 'room' && !$is_player_turn) {
      throw new \InvalidArgumentException('Only player messages may be posted to the room channel.', 400);
    }

    // stream: use NDJSON streaming for player turns so the client can render
    // player ack, progress, primary reply, and any follow-up reactions
    // incrementally instead of waiting for one large JSON response.
    $stream = !empty($payload['stream']) && $is_player_turn;

    // suppress_gm: persist the player's room message but intentionally skip
    // response generation for this request because a turn is already in
    // flight. The queued player messages are folded into one later
    // continuation for the same channel.
    $suppress_gm = !empty($payload['suppress_gm']) && $is_player_turn;

    // continue_gm: run exactly one follow-up pass over queued player
    // messages after the active turn settles. This keeps AI analysis
    // serialized while still allowing the player to keep sending messages.
    $continue_gm = !empty($payload['continue_gm']) && $is_player_turn;

    return [
      'speaker' => $speaker,
      'message' => $message,
      'type' => $type,
      'character_id' => $character_id,
      'channel' => $channel,
      'client_request_id' => $client_request_id,
      'stream' => $stream,
      'suppress_gm' => $suppress_gm,
      'continue_gm' => $continue_gm,
    ];
  }

  /**
   * Suggest the next in-character player chat message for automation.
   */
  public function suggestPlayerAutomationMessage(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }

      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON payload',
        ], 400);
      }

      $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : 0;
      $channel = (string) ($payload['channel'] ?? 'room');
      if ($character_id <= 0) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'character_id is required',
        ], 400);
      }

      $result = $this->chatService->suggestPlayerAutomationMessage(
        $campaign_id,
        $room_id,
        $character_id,
        $channel
      );

      return new JsonResponse([
        'success' => TRUE,
        'data' => $result,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 400;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], $status);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An error occurred',
      ], 500);
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
        $encounter_progress_ctx = $this->buildEncounterProgressSnapshot($campaign_id);
        $this->emitProgressUpdate($emit, $client_request_id, 'room_request_started', [
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'channel' => $channel,
        ] + $encounter_progress_ctx);

        $posted_message = NULL;
        $player_message_for_followup = $message;

        if ($this->isPlayerRoomChat($type, $channel)) {
          $result = $this->postPlayerRoomChatViaEncounterTalk(
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
          $emit([
            'type' => 'player_ack',
            'data' => [
              'speaker' => $speaker,
              'message' => $message,
              'type' => $type,
              'channel' => $channel,
              'client_request_id' => $client_request_id,
            ],
          ]);

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
            ] + $encounter_progress_ctx)
          );
        }

        if ($posted_message !== NULL) {
          $emit([
            'type' => 'player_ack',
            'data' => [
              'speaker' => (string) ($posted_message['speaker'] ?? $speaker),
              'message' => (string) ($posted_message['message'] ?? $message),
              'type' => (string) ($posted_message['type'] ?? $type),
              'channel' => (string) ($posted_message['channel'] ?? $channel),
              'client_request_id' => $client_request_id,
            ],
          ]);
        }

        $this->emitStreamedTurnResult(
          $emit,
          $result,
          $campaign_id,
          $room_id,
          $player_message_for_followup,
          $character_id,
          $channel,
          $client_request_id
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
        $encounter_progress_ctx = $this->buildEncounterProgressSnapshot($campaign_id);
        $emit([
          'type' => 'thinking',
          'data' => $this->buildProgressEventData('queued_continuation_started', $client_request_id, [
            'channel' => $channel,
          ] + $encounter_progress_ctx),
        ]);

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
          ] + $encounter_progress_ctx)
        );

        $this->emitStreamedTurnResult(
          $emit,
          $result,
          $campaign_id,
          $room_id,
          (string) ($result['queued_player_summary'] ?? ''),
          $character_id,
          $channel,
          $client_request_id
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
   * Build a stable encounter round/turn snapshot for progress-line prefixing.
   */
  protected function buildEncounterProgressSnapshot(int $campaign_id): array {
    if ($campaign_id <= 0) {
      return [];
    }

    try {
      $state = $this->getCoordinator()->getFullState($campaign_id);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Encounter progress snapshot fallback: campaign={campaign_id} message={message}', [
        'campaign_id' => $campaign_id,
        'message' => $e->getMessage(),
      ]);
      return [];
    }
    if (!is_array($state)) {
      return [];
    }

    $round_raw = $state['round'] ?? ($state['game_state']['round'] ?? NULL);
    $turn = $state['turn'] ?? ($state['game_state']['turn'] ?? []);
    $turn_index_raw = is_array($turn) && isset($turn['index']) && is_numeric($turn['index'])
      ? (int) $turn['index']
      : NULL;

    $snapshot = [];
    if (is_numeric($round_raw)) {
      $snapshot['encounter_round_raw'] = (int) $round_raw;
    }
    if ($turn_index_raw !== NULL) {
      $snapshot['encounter_turn_index_raw'] = $turn_index_raw;
    }

    return $snapshot;
  }

  /**
   * Resolve progress prefix snapshot from action/chat result payloads.
   */
  protected function buildEncounterProgressSnapshotFromResult(array $result, int $campaign_id): array {
    $game_state = [];
    if (is_array($result['game_state'] ?? NULL)) {
      $game_state = $result['game_state'];
    }
    elseif (is_array($result['dungeon_data']['game_state'] ?? NULL)) {
      $game_state = $result['dungeon_data']['game_state'];
    }

    if ($game_state !== []) {
      $round_raw = $game_state['round'] ?? NULL;
      $turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
      $turn_index_raw = isset($turn['index']) && is_numeric($turn['index']) ? (int) $turn['index'] : NULL;

      $snapshot = [];
      if (is_numeric($round_raw)) {
        $snapshot['encounter_round_raw'] = (int) $round_raw;
      }
      if ($turn_index_raw !== NULL) {
        $snapshot['encounter_turn_index_raw'] = $turn_index_raw;
      }
      if ($snapshot !== []) {
        return $snapshot;
      }
    }

    return $this->buildEncounterProgressSnapshot($campaign_id);
  }

  /**
   * Create an NDJSON streaming response with shared error handling.
   */
  protected function createStreamedTurnResponse(callable $stream_callback, array $context = []): StreamedResponse {
    $response = new StreamedResponse(function () use ($stream_callback, $context): void {
      $emit = $this->createNdjsonEmitter();

      try {
        $stream_callback($emit);
      }
      catch (\Throwable $e) {
        $this->emitStreamError($emit, $e, $context);
      }
    });

    $response->headers->set('Content-Type', 'application/x-ndjson');
    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }

  /**
   * Build a shared NDJSON emitter.
   */
  protected function createNdjsonEmitter(): callable {
    return function (array $payload): void {
      echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
      if (function_exists('ob_flush')) {
        @ob_flush();
      }
      flush();
    };
  }

  /**
   * Build a shared streaming progress callback.
   */
  protected function buildStreamProgressCallback(callable $emit, string $client_request_id, array $base_context = []): callable {
    return function (array $progress) use ($emit, $client_request_id, $base_context): void {
      $progress_context = is_array($progress['context'] ?? NULL) ? $progress['context'] : [];
      $context = $base_context + $progress_context;

      $this->emitProgressUpdate(
        $emit,
        $client_request_id,
        (string) ($progress['stage'] ?? ''),
        $context
      );
    };
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
    $result['turn_logs'] = $this->filterClientVisibleTurnLogs(
      is_array($result['turn_logs'] ?? NULL) ? $result['turn_logs'] : []
    );
    if (!empty($result['turn_logs'])) {
      foreach ($result['turn_logs'] as $system_message) {
        $emit([
          'type' => 'system_message',
          'data' => $system_message,
        ]);
      }
    }

    if (!empty($result['gm_response'])) {
      $emit([
        'type' => 'gm_response',
        'data' => $result['gm_response'] + [
          'client_request_id' => $client_request_id,
        ],
      ]);
    }

    $should_complete_deferred_npc_turns = !empty($result['npc_interjections_deferred'])
      && !empty($result['gm_response']['message'])
      && empty($result['gm_response']['gm_payload']['flags']['suppress_npc_interjections']);
    if ($should_complete_deferred_npc_turns) {
      $this->emitProgressUpdate($emit, $client_request_id, 'npc_reactions_generating', [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'channel' => $channel,
      ] + $this->buildEncounterProgressSnapshotFromResult($result, $campaign_id));
      $npc_turn_result = $this->chatService->completeDeferredNpcInterjections(
        $campaign_id,
        $room_id,
        $player_message,
        (string) $result['gm_response']['message'],
        $character_id
      );

      if (!empty($npc_turn_result['turn_log_key'])) {
        $result['turn_log_key'] = $npc_turn_result['turn_log_key'];
      }

      if (!empty($npc_turn_result['turn_logs'])) {
        $deferred_visible_turn_logs = $this->filterClientVisibleTurnLogs(
          is_array($npc_turn_result['turn_logs'] ?? NULL) ? $npc_turn_result['turn_logs'] : []
        );
        $result['turn_logs'] = array_values(array_merge(
          is_array($result['turn_logs'] ?? NULL) ? $result['turn_logs'] : [],
          $deferred_visible_turn_logs
        ));
        foreach ($deferred_visible_turn_logs as $system_message) {
          $emit([
            'type' => 'system_message',
            'data' => $system_message,
          ]);
        }
      }

      if (!empty($npc_turn_result['messages'])) {
        $result['turn_harness'] = $npc_turn_result;
        $result['npc_interjections'] = $npc_turn_result['messages'];
        foreach ($npc_turn_result['messages'] as $npc_message) {
          $emit([
            'type' => 'npc_interjection',
            'data' => $npc_message,
          ]);
        }
      }

      if (!empty($npc_turn_result['quest_updates'])) {
        $result['quest_updates'] = array_values(array_merge(
          is_array($result['quest_updates'] ?? NULL) ? $result['quest_updates'] : [],
          $npc_turn_result['quest_updates']
        ));
      }
      $result['npc_interjections_deferred'] = FALSE;
    }

    $emit([
      'type' => 'complete',
      'data' => $result + [
        'client_request_id' => $client_request_id,
      ],
    ]);
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

  /**
   * Emit a normalized streamed error payload.
   */
  protected function emitStreamError(callable $emit, \Throwable $e, array $context = []): void {
    $status = $e instanceof \InvalidArgumentException ? ((int) $e->getCode() ?: 400) : 500;
    $debug_id = 'roomchat-' . substr(hash('sha256', microtime(TRUE) . '|' . random_int(0, PHP_INT_MAX)), 0, 12);
    $debug = $this->buildStreamErrorDebugPayload($debug_id, $status, $context);
    $this->logStreamError($debug_id, $e, $context, $status);

    $emit([
      'type' => 'error',
      'error' => $e instanceof \InvalidArgumentException ? $e->getMessage() : 'An error occurred',
      'status' => $status,
      'debug' => $debug,
    ]);
  }

  /**
   * Build the client-visible stream debug payload.
   */
  protected function buildStreamErrorDebugPayload(string $debug_id, int $status, array $context = []): array {
    return [
      'debug_id' => $debug_id,
      'client_request_id' => $context['client_request_id'] ?? '',
      'campaign_id' => $context['campaign_id'] ?? NULL,
      'room_id' => $context['room_id'] ?? NULL,
      'character_id' => $context['character_id'] ?? NULL,
      'channel' => $context['channel'] ?? NULL,
      'stream_mode' => $context['stream_mode'] ?? 'unknown',
      'status' => $status,
    ];
  }

  /**
   * Write the server-side log entry for a streamed room-chat failure.
   */
  protected function logStreamError(string $debug_id, \Throwable $e, array $context = [], int $status = 500): void {
    $this->logger->error(
      'Room chat stream failed [{debug_id}] campaign={campaign_id} room={room_id} channel={channel} character={character_id} request={client_request_id} mode={stream_mode}: {message}',
      [
        'debug_id' => $debug_id,
        'campaign_id' => $context['campaign_id'] ?? NULL,
        'room_id' => $context['room_id'] ?? NULL,
        'channel' => $context['channel'] ?? NULL,
        'character_id' => $context['character_id'] ?? NULL,
        'client_request_id' => $context['client_request_id'] ?? '',
        'stream_mode' => $context['stream_mode'] ?? 'unknown',
        'message' => $e->getMessage(),
        'status' => $status,
        'exception_class' => get_class($e),
        'speaker' => $context['speaker'] ?? '',
        'type' => $context['type'] ?? '',
        'message_length' => $context['message_length'] ?? 0,
        'exception' => $e,
      ]
    );
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

    $data = NULL;
    switch ($stage) {
      case 'room_request_started':
        $data = [
          'message' => $channel !== 'room'
            ? 'Reviewing what you just said...'
            : 'Reviewing the room and what you just said...',
          'phase' => 'reviewing-room',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'conversation_persisted':
        $data = [
          'message' => 'Updating conversation state...',
          'phase' => 'updating-conversation',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'conversation_bridged':
        $data = [
          'message' => 'Syncing the scene context...',
          'phase' => 'syncing-context',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'npc_context_prepared':
        $data = [
          'message' => $channel !== 'room'
            ? 'Checking the active participants...'
            : 'Checking who is active in the scene...',
          'phase' => 'checking-reactions',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'gm_reply_generating':
        $data = [
          'message' => $channel !== 'room'
            ? 'Preparing the reply...'
            : 'Preparing the scene...',
          'phase' => 'drafting-response',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'npc_reactions_generating':
        $data = [
          'message' => 'Resolving the next actor in turn order...',
          'phase' => 'npc-reactions',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'queued_continuation_started':
      case 'queued_messages_loaded':
        $queued_count = max(1, (int) ($context['queued_player_count'] ?? 1));
        $data = [
          'message' => $queued_count === 1
            ? 'Thinking about what you just said...'
            : "Thinking about the {$queued_count} things you just said...",
          'phase' => 'reviewing-queue',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
        break;
    }

    if ($data === NULL) {
      return NULL;
    }

    if ($campaign_id > 0) {
      $round_raw = is_numeric($context['encounter_round_raw'] ?? NULL) ? (int) $context['encounter_round_raw'] : NULL;
      $turn_index_raw = is_numeric($context['encounter_turn_index_raw'] ?? NULL) ? (int) $context['encounter_turn_index_raw'] : NULL;
      $data['message'] = $this->prefixEncounterProgressMessage(
        $campaign_id,
        (string) ($data['speaker'] ?? 'System'),
        (string) ($data['message'] ?? ''),
        $round_raw,
        $turn_index_raw
      );
    }

    return $data;
  }

  protected function isEncounterPrefixedMessage(string $content): bool {
    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::isPrefixed($content);
  }

  protected function prefixEncounterProgressMessage(
    int $campaign_id,
    string $speaker,
    string $message,
    ?int $round_raw = NULL,
    ?int $turn_index_raw = NULL
  ): string {
    $message = trim($message);
    if ($message === '' || $this->isEncounterPrefixedMessage($message)) {
      return $message;
    }

    if ($round_raw === NULL || $turn_index_raw === NULL) {
      try {
        $state = $this->getCoordinator()->getFullState($campaign_id);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Encounter progress prefix fallback: campaign={campaign_id} message={message}', [
          'campaign_id' => $campaign_id,
          'message' => $e->getMessage(),
        ]);
        $state = [];
      }
      if ($round_raw === NULL) {
        $round_raw = is_array($state) ? ($state['round'] ?? ($state['game_state']['round'] ?? 1)) : 1;
      }
      if ($turn_index_raw === NULL) {
        $turn = is_array($state) ? ($state['turn'] ?? ($state['game_state']['turn'] ?? [])) : [];
        $turn_index_raw = is_array($turn) && isset($turn['index']) && is_numeric($turn['index']) ? (int) $turn['index'] : NULL;
      }
    }

    $round_display = \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::displayRound($round_raw);
    $turn_display = \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::displayTurnFromIndexRaw($turn_index_raw);

    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::formatPrefix($round_display, $turn_display, $speaker) . $message;
  }

  /**
   * Get available channels for a room.
   *
   * GET /api/campaign/{campaign_id}/room/{room_id}/channels?character_id=85
   */
  public function getChannels(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
      }

      $character_id = $request->query->get('character_id') ? (int) $request->query->get('character_id') : NULL;
      $result = $this->chatService->getChannelsForRoom($campaign_id, $room_id, $character_id);

      return new JsonResponse([
        'success' => TRUE,
        'data' => $result,
      ]);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
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
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
      }

      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON payload'], 400);
      }

      $channel_key = $payload['channel_key'] ?? '';
      $opened_by = (string) ($payload['opened_by'] ?? '');
      $target_entity = $payload['target_entity'] ?? '';
      $target_name = $payload['target_name'] ?? 'Unknown';
      $source_ability = $payload['source_ability'] ?? 'whisper';

      if (empty($channel_key) || empty($opened_by) || empty($target_entity)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Missing required fields: channel_key, opened_by, target_entity'], 400);
      }

      $result = $this->chatService->openChannel(
        $campaign_id,
        $room_id,
        $channel_key,
        $opened_by,
        $target_entity,
        $target_name,
        $source_ability
      );

      $status = $result['success'] ? 200 : 400;
      return new JsonResponse(['success' => $result['success'], 'data' => $result], $status);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  /**
   * Close a channel in a room.
   *
   * DELETE /api/campaign/{campaign_id}/room/{room_id}/channels/{channel_key}
   */
  public function closeChannel(int $campaign_id, string $room_id, string $channel_key): JsonResponse {
    try {
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
      }

      $closed = $this->chatService->closeChannel($campaign_id, $room_id, $channel_key);

      return new JsonResponse([
        'success' => $closed,
        'data' => ['channel_key' => $channel_key, 'closed' => $closed],
      ]);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

}
