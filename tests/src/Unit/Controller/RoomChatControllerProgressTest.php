<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\RoomChatController;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests streamed room-chat progress payload mapping.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\RoomChatController
 */
class RoomChatControllerProgressTest extends UnitTestCase {

  /**
   * @covers ::buildProgressEventData
   */
  public function testBuildProgressEventDataMapsServiceStages(): void {
    $controller = new RoomChatController($this->createMock(RoomChatService::class));
    $method = new \ReflectionMethod(RoomChatController::class, 'buildProgressEventData');
    $method->setAccessible(TRUE);

    $persisted = $method->invoke($controller, 'conversation_persisted', 'req-1');
    $queued = $method->invoke($controller, 'queued_messages_loaded', 'req-2', [
      'queued_player_count' => 3,
    ]);
    $unknown = $method->invoke($controller, 'unknown_stage', 'req-3');

    $this->assertSame('updating-conversation', $persisted['phase']);
    $this->assertSame('Game Master is updating the conversation state...', $persisted['message']);
    $this->assertSame('req-1', $persisted['client_request_id']);

    $this->assertSame('reviewing-queue', $queued['phase']);
    $this->assertSame('Game Master is reviewing 3 queued messages...', $queued['message']);
    $this->assertSame('req-2', $queued['client_request_id']);

    $this->assertNull($unknown);
  }

}
