<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API controller for room chat messages.
 *
 * Provides REST endpoints for reading and posting chat messages in dungeon rooms.
 * Player room chat is governed by the encounter engine and must flow through the
 * canonical talk action.
 */
class RoomChatController extends ControllerBase {

  protected RoomChatService $chatService;

  protected GameCoordinatorService $coordinator;

  protected LoggerInterface $logger;

  /**
   * Constructor.
   */
  public function __construct(RoomChatService $chat_service, GameCoordinatorService $coordinator, LoggerInterface $logger) {
    $this->chatService = $chat_service;
    $this->coordinator = $coordinator;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_chat_service'),
      $container->get('dungeoncrawler_content.game_coordinator'),
      $container->get('logger.factory')->get('dungeoncrawler_chat')
    );
  }

  /**
   * Build the standard success wrapper for room-chat JSON responses.
   */
  protected function buildSuccessDataResponse(array $data, ?string $client_request_id = NULL): JsonResponse {
    if ($client_request_id !== NULL && $client_request_id !== '') {
      $data['client_request_id'] = $client_request_id;
    }

    return new JsonResponse([
      'success' => TRUE,
      'data' => $data,
    ]);
  }

  protected function isPlayerRoomChat(string $type, string $channel): bool {
    return $type === 'player' && $channel === 'room';
  }

  /**
   * Route player room chat through the canonical encounter talk action.
   */
  protected function postPlayerRoomChatViaEncounterTalk(
    int $campaign_id,
    string $requested_room_id,
    ?int $character_id,
    string $message,
    bool $defer_npc_interjections = FALSE,
    bool $suppress_gm = FALSE
  ): array {
    if ($character_id === NULL || $character_id <= 0) {
      throw new \InvalidArgumentException('character_id is required for player room chat.', 400);
    }

    $actor_id = $this->coordinator->resolveActorIdForCharacterId($campaign_id, $character_id);
    if (!$actor_id) {
      throw new \InvalidArgumentException('Unable to resolve encounter actor for character.', 409);
    }

    $active_room_id = $this->coordinator->getActiveRoomId($campaign_id, $actor_id);
    if ($active_room_id !== NULL && $active_room_id !== '' && $active_room_id !== $requested_room_id) {
      throw new \InvalidArgumentException('Cannot post room chat: requested room does not match active room.', 409);
    }

    $intent = [
      'type' => 'talk',
      'actor' => $actor_id,
      'target' => NULL,
      'params' => [
        'message' => $message,
        'character_id' => $character_id,
        'defer_npc_interjections' => $defer_npc_interjections,
        'suppress_gm' => $suppress_gm,
      ],
    ];

    $action_response = $this->coordinator->processAction($campaign_id, $intent);
    if (empty($action_response['success'])) {
      $error = (string) (
        $action_response['error']
        ?? ($action_response['result']['error'] ?? NULL)
        ?? 'Talk failed.'
      );
      throw new \InvalidArgumentException($error, 409);
    }

    $talk_result = is_array($action_response['result'] ?? NULL) ? $action_response['result'] : [];
    if (isset($talk_result['chat_message']) && is_array($talk_result['chat_message'])) {
      $talk_result['message'] = $talk_result['chat_message'];
      unset($talk_result['chat_message']);
    }

    return $talk_result;
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

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'roomId' => $room_id,
          'channel' => $channel,
          'messages' => $messages,
        ],
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 500;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $status === 404 ? 'Dungeon not found' : 'Invalid request',
      ], $status);
    }
    catch (\Exception $e) {
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

      $speaker = $payload['speaker'] ?? '';
      $message = $payload['message'] ?? '';
      $type = $payload['type'] ?? 'player';
      $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : null;
      $channel = $payload['channel'] ?? 'room';
      $client_request_id = (string) ($payload['client_request_id'] ?? '');

      // Room transcript lines are encounter-governed: clients cannot inject NPC/system
      // lines into the room channel. Player room chat must route via the canonical
      // encounter Talk action.
      if ($channel === 'room' && $type !== 'player') {
        throw new \InvalidArgumentException('Only player messages may be posted to the room channel.', 400);
      }

      $is_player_turn = $type === 'player';

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
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], $status);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An error occurred',
      ], 500);
    }
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
    catch (\Exception $e) {
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
        $this->emitProgressUpdate($emit, $client_request_id, 'room_request_started', [
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'channel' => $channel,
        ]);

        $posted_message = NULL;
        $player_message_for_followup = $message;

        if ($this->isPlayerRoomChat($type, $channel)) {
          $result = $this->postPlayerRoomChatViaEncounterTalk(
            $campaign_id,
            $room_id,
            $character_id,
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
            ])
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
        $emit([
          'type' => 'thinking',
          'data' => $this->buildProgressEventData('queued_continuation_started', $client_request_id, [
            'channel' => $channel,
          ]),
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
          ])
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
      echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
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

    if (!empty($result['npc_interjections_deferred']) && !empty($result['gm_response']['message'])) {
      $this->emitProgressUpdate($emit, $client_request_id, 'npc_reactions_generating', [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'channel' => $channel,
      ]);
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
        $result['turn_logs'] = array_values(array_merge(
          is_array($result['turn_logs'] ?? NULL) ? $result['turn_logs'] : [],
          $npc_turn_result['turn_logs']
        ));
        foreach ($npc_turn_result['turn_logs'] as $system_message) {
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
          'speaker' => 'Narrator',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'conversation_persisted':
        $data = [
          'message' => 'Updating conversation state...',
          'phase' => 'updating-conversation',
          'speaker' => 'Narrator',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'conversation_bridged':
        $data = [
          'message' => 'Syncing the scene context...',
          'phase' => 'syncing-context',
          'speaker' => 'Narrator',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'npc_context_prepared':
        $data = [
          'message' => $channel !== 'room'
            ? 'Checking the active participants...'
            : 'Checking who is active in the scene...',
          'phase' => 'checking-reactions',
          'speaker' => 'Narrator',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'gm_reply_generating':
        $data = [
          'message' => $channel !== 'room'
            ? 'Preparing the reply...'
            : 'Preparing the scene...',
          'phase' => 'drafting-response',
          'speaker' => 'Narrator',
          'client_request_id' => $client_request_id,
        ];
        break;

      case 'npc_reactions_generating':
        $data = [
          'message' => 'Resolving nearby NPC turns...',
          'phase' => 'npc-reactions',
          'speaker' => 'Initiative Order',
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
      $data['message'] = $this->prefixEncounterProgressMessage(
        $campaign_id,
        (string) ($data['speaker'] ?? 'System'),
        (string) ($data['message'] ?? '')
      );
    }

    return $data;
  }

  protected function isEncounterPrefixedMessage(string $content): bool {
    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::isPrefixed($content);
  }

  protected function prefixEncounterProgressMessage(int $campaign_id, string $speaker, string $message): string {
    $message = trim($message);
    if ($message === '' || $this->isEncounterPrefixedMessage($message)) {
      return $message;
    }

    $state = $this->coordinator->getFullState($campaign_id);

    $round_raw = is_array($state) ? ($state['round'] ?? ($state['game_state']['round'] ?? 1)) : 1;
    $round_display = \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::displayRound($round_raw);

    $turn = is_array($state) ? ($state['turn'] ?? ($state['game_state']['turn'] ?? [])) : [];
    $turn_index_raw = is_array($turn) && isset($turn['index']) && is_numeric($turn['index']) ? (int) $turn['index'] : NULL;
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
    catch (\Exception $e) {
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
    catch (\Exception $e) {
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
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

}
