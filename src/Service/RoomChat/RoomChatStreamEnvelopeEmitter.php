<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds NDJSON streaming envelopes for room-chat endpoints.
 */
class RoomChatStreamEnvelopeEmitter {

  /**
   * Build a streamed NDJSON response with shared emitter/error handling.
   */
  public function createStreamedResponse(callable $stream_callback, callable $error_callback): StreamedResponse {
    $response = new StreamedResponse(function () use ($stream_callback, $error_callback): void {
      $emit = $this->createNdjsonEmitter();

      try {
        $stream_callback($emit);
      }
      catch (\Throwable $e) {
        $error_callback($emit, $e);
      }
    });

    $response->headers->set('Content-Type', 'application/x-ndjson');
    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }

  /**
   * Build a shared NDJSON emitter closure.
   */
  protected function createNdjsonEmitter(): callable {
    return static function (array $payload): void {
      echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
      if (function_exists('ob_flush')) {
        @ob_flush();
      }
      flush();
    };
  }

}
