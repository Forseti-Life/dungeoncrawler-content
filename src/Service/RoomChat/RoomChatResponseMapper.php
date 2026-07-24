<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Builds canonical RoomChat JSON response envelopes and diagnostics.
 */
class RoomChatResponseMapper {

  protected LoggerInterface $logger;

  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
  }

  public function buildSuccessDataResponse(array $data, ?string $client_request_id = NULL): JsonResponse {
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

  public function buildAccessDeniedResponse(): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error' => 'Access denied',
    ], 403);
  }

  public function buildInvalidRequestResponse(string $message, int $status = 400): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error' => $message,
    ], $status);
  }

  public function buildUnhandledErrorResponse(): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error' => 'An error occurred',
    ], 500);
  }

  public function buildChatHistoryResponseData(string $room_id, string $channel, array $messages): array {
    return [
      'roomId' => $room_id,
      'channel' => $channel,
      'messages' => $messages,
    ];
  }

  public function buildChatHistoryInvalidRequestResponse(\InvalidArgumentException $e, int $campaign_id, string $room_id, string $channel, ?int $character_id): JsonResponse {
    $status = (int) $e->getCode() ?: 500;
    $this->logger->warning('Room chat history request rejected: campaign={campaign_id} room={room_id} channel={channel} character_id={character_id} status={status} message={message}', [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel,
      'character_id' => (int) ($character_id ?? 0),
      'status' => $status,
      'message' => $e->getMessage(),
    ]);
    return new JsonResponse([
      'success' => FALSE,
      'error' => $status === 404 ? 'Dungeon not found' : 'Invalid request',
    ], $status);
  }

  public function logChatHistoryFailure(\Throwable $e, int $campaign_id, string $room_id, string $channel, ?int $character_id): void {
    $this->logger->error('Room chat history request failed: campaign={campaign_id} room={room_id} channel={channel} character_id={character_id} exception={exception} message={message}', [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel,
      'character_id' => (int) ($character_id ?? 0),
      'exception' => get_class($e),
      'message' => $e->getMessage(),
    ]);
  }

  public function buildPostChatInvalidRequestResponse(
    \InvalidArgumentException $e,
    int $campaign_id,
    string $room_id,
    string $channel,
    ?int $character_id,
    string $client_request_id
  ): JsonResponse {
    $status = (int) $e->getCode() ?: 400;
    $debug_id = $this->createDebugId();
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
      'error' => $e->getMessage() . ' [debug ' . $debug_id . ']',
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

  public function buildPostChatFailureResponse(
    \Throwable $e,
    int $campaign_id,
    string $room_id,
    string $channel,
    ?int $character_id,
    string $client_request_id,
    string $speaker,
    string $type
  ): JsonResponse {
    $debug_id = $this->createDebugId();
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
      'error' => 'An error occurred [debug ' . $debug_id . ']',
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

  public function buildRoomNotReadyResponse(
    \Throwable $e,
    int $campaign_id,
    string $room_id,
    string $channel,
    ?int $character_id,
    string $client_request_id,
    string $speaker,
    string $type
  ): JsonResponse {
    $debug_id = $this->createDebugId();
    $this->logger->warning(
      'Room chat POST deferred [{debug_id}] campaign={campaign_id} room={room_id} channel={channel} character={character_id} request={client_request_id}: {message}',
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
      'error' => 'Room is still loading. Try again in a moment.',
      'error_code' => 'room_not_ready',
      'retryable' => TRUE,
      'debug' => [
        'debug_id' => $debug_id,
        'client_request_id' => $client_request_id,
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'character_id' => $character_id,
        'channel' => $channel,
        'status' => 409,
        'stream_mode' => 'json_post',
        'exception_class' => get_class($e),
        'message' => $e->getMessage(),
        'error_code' => 'room_not_ready',
      ],
    ], 409);
  }

  protected function createDebugId(): string {
    return 'roomchat-' . substr(hash('sha256', microtime(TRUE) . '|' . random_int(0, PHP_INT_MAX)), 0, 12);
  }

}
