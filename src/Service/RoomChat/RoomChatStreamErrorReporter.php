<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Reports streamed room-chat errors with canonical debug payload/log shape.
 */
class RoomChatStreamErrorReporter {

  protected LoggerInterface $logger;

  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
  }

  public function emit(callable $emit, \Throwable $e, array $context = []): void {
    $status = $e instanceof \InvalidArgumentException ? ((int) $e->getCode() ?: 400) : 500;
    $debug_id = $this->createDebugId();
    $debug = $this->buildDebugPayload($debug_id, $status, $context);
    $this->logError($debug_id, $e, $context, $status);

    $emit([
      'type' => 'error',
      'error' => $e instanceof \InvalidArgumentException ? $e->getMessage() : 'An error occurred',
      'status' => $status,
      'debug' => $debug,
    ]);
  }

  protected function createDebugId(): string {
    return 'roomchat-' . substr(hash('sha256', microtime(TRUE) . '|' . random_int(0, PHP_INT_MAX)), 0, 12);
  }

  protected function buildDebugPayload(string $debug_id, int $status, array $context = []): array {
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

  protected function logError(string $debug_id, \Throwable $e, array $context = [], int $status = 500): void {
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

}
