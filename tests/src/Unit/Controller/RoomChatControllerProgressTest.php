<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\RoomChatController;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

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

    $started = $method->invoke($controller, 'room_request_started', 'req-0');
    $persisted = $method->invoke($controller, 'conversation_persisted', 'req-1');
    $queued = $method->invoke($controller, 'queued_messages_loaded', 'req-2', [
      'queued_player_count' => 3,
    ]);
    $private_started = $method->invoke($controller, 'room_request_started', 'req-private', [
      'channel' => 'whisper:npc-1',
    ]);
    $unknown = $method->invoke($controller, 'unknown_stage', 'req-3');

    $this->assertSame('reviewing-room', $started['phase']);
    $this->assertSame('Turn 1: Narrator is reviewing the room and what you just said...', $started['message']);
    $this->assertSame('req-0', $started['client_request_id']);

    $this->assertSame('updating-conversation', $persisted['phase']);
    $this->assertSame('Turn 1: Narrator is updating conversation state...', $persisted['message']);
    $this->assertSame('req-1', $persisted['client_request_id']);

    $this->assertSame('reviewing-queue', $queued['phase']);
    $this->assertSame('Thinking about the 3 things you just said...', $queued['message']);
    $this->assertSame('req-2', $queued['client_request_id']);

    $this->assertSame('reviewing-room', $private_started['phase']);
    $this->assertSame('Turn 1: Narrator is reviewing what you just said...', $private_started['message']);
    $this->assertSame('req-private', $private_started['client_request_id']);

    $this->assertNull($unknown);
  }

  /**
   * @covers ::postChatMessage
   */
  public function testPostChatMessageReturnsTurnLogsInJsonPayload(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('hasCampaignAccess')
      ->with(63)
      ->willReturn(TRUE);
    $chat_service->expects($this->once())
      ->method('postMessage')
      ->with(63, 'room-1', 'Burasco', 'Who answers?', 'player', 218, 'room', FALSE, FALSE)
      ->willReturn([
        'message' => ['speaker' => 'Burasco', 'message' => 'Who answers?'],
        'turn_log_key' => 'room_turn_abc',
        'turn_logs' => [
          ['speaker' => 'System', 'message' => 'Turn order: Narrator -> Game Master -> Eldric 17.', 'type' => 'system'],
        ],
      ]);

    $controller = new RoomChatController($chat_service);
    $request = Request::create(
      '/api/campaign/63/room/room-1/chat',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'speaker' => 'Burasco',
        'message' => 'Who answers?',
        'type' => 'player',
        'character_id' => 218,
        'channel' => 'room',
      ])
    );

    $response = $controller->postChatMessage(63, 'room-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame('room_turn_abc', $payload['data']['turn_log_key']);
    $this->assertSame('Turn order: Narrator -> Game Master -> Eldric 17.', $payload['data']['turn_logs'][0]['message']);
  }

  /**
   * @covers ::emitStreamedTurnResult
   */
  public function testEmitStreamedTurnResultEmitsImmediateAndDeferredSystemMessages(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('completeDeferredNpcInterjections')
      ->with(63, 'room-1', 'Who answers?', 'The room quiets.', 218)
      ->willReturn([
        'turn_log_key' => 'room_turn_stream',
        'turn_logs' => [
          ['speaker' => 'System', 'message' => 'Current turn: Eldric.', 'type' => 'system', 'turn_role' => 'npc', 'turn_name' => 'Eldric', 'turn_index' => 3],
        ],
        'messages' => [
          ['speaker' => 'Eldric', 'message' => 'I do.', 'type' => 'npc'],
        ],
      ]);

    $controller = new RoomChatController($chat_service);
    $method = new \ReflectionMethod(RoomChatController::class, 'emitStreamedTurnResult');
    $method->setAccessible(TRUE);

    $events = [];
    $emit = static function (array $event) use (&$events): void {
      $events[] = $event;
    };

    $method->invoke(
      $controller,
      $emit,
      [
        'gm_response' => ['speaker' => 'Game Master', 'message' => 'The room quiets.', 'type' => 'npc'],
        'turn_logs' => [
          ['speaker' => 'System', 'message' => 'Turn order: Narrator -> Game Master -> Eldric 17.', 'type' => 'system'],
        ],
        'npc_interjections_deferred' => TRUE,
      ],
      63,
      'room-1',
      'Who answers?',
      218,
      'room',
      'req-1'
    );

    $event_types = array_column($events, 'type');
    $this->assertSame(
      ['system_message', 'gm_response', 'thinking', 'system_message', 'npc_interjection', 'complete'],
      $event_types
    );
    $this->assertSame('Turn order: Narrator -> Game Master -> Eldric 17.', $events[0]['data']['message']);
    $this->assertSame('Current turn: Eldric.', $events[3]['data']['message']);
    $this->assertSame('I do.', $events[4]['data']['message']);
    $this->assertSame('Current turn: Eldric.', $events[5]['data']['turn_logs'][1]['message']);
    $this->assertSame('room_turn_stream', $events[5]['data']['turn_log_key']);
  }

}
