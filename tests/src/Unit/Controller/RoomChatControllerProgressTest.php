<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\dungeoncrawler_content\Controller\RoomChatController;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests streamed room-chat progress payload mapping.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\RoomChatController
 */
class RoomChatControllerProgressTest extends UnitTestCase {

  protected function createController(RoomChatService $chat_service, ?LoggerInterface $logger = NULL, ?GameCoordinatorService $coordinator = NULL, ?GameMasterSubsystemService $gm_subsystem = NULL): RoomChatController {
    return new RoomChatController(
      $chat_service,
      $coordinator ?: $this->createMock(GameCoordinatorService::class),
      $gm_subsystem ?: $this->createMock(GameMasterSubsystemService::class),
      $logger ?: $this->createMock(LoggerInterface::class)
    );
  }

  /**
   * @covers ::create
   */
  public function testCreateUsesLoggerFactoryChannel(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('dungeoncrawler_chat')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('dungeoncrawler_content.room_chat_service', $chat_service);
    $container->set('dungeoncrawler_content.game_coordinator', $this->createMock(GameCoordinatorService::class));
    $container->set('dungeoncrawler_content.game_master_subsystem', $this->createMock(GameMasterSubsystemService::class));
    $container->set('logger.factory', $logger_factory);

    $controller = RoomChatController::create($container);

    $this->assertInstanceOf(RoomChatController::class, $controller);
  }

  /**
   * @covers ::buildProgressEventData
   */
  public function testBuildProgressEventDataMapsServiceStages(): void {

    $coordinator = $this->createMock(GameCoordinatorService::class);
    $coordinator->method('getFullState')->willReturn([
      'success' => TRUE,
      'round' => 1,
      'turn' => ['index' => 0],
      'game_state' => ['round' => 1, 'turn' => ['index' => 0]],
    ]);

    $controller = $this->createController($this->createMock(RoomChatService::class), NULL, $coordinator);
    $method = new \ReflectionMethod(RoomChatController::class, 'buildProgressEventData');
    $method->setAccessible(TRUE);

    $started = $method->invoke($controller, 'room_request_started', 'req-0', ['campaign_id' => 63]);
    $persisted = $method->invoke($controller, 'conversation_persisted', 'req-1', ['campaign_id' => 63]);
    $queued = $method->invoke($controller, 'queued_messages_loaded', 'req-2', [
      'campaign_id' => 63,
      'queued_player_count' => 3,
    ]);
    $npc_reactions = $method->invoke($controller, 'npc_reactions_generating', 'req-2b', ['campaign_id' => 63]);
    $private_started = $method->invoke($controller, 'room_request_started', 'req-private', [
      'campaign_id' => 63,
      'channel' => 'whisper:npc-1',
    ]);
    $unknown = $method->invoke($controller, 'unknown_stage', 'req-3', ['campaign_id' => 63]);

    $this->assertSame('reviewing-room', $started['phase']);
    $this->assertSame('Round 0: Turn 1: Actor System: Reviewing the room and what you just said...', $started['message']);
    $this->assertSame('req-0', $started['client_request_id']);

    $this->assertSame('updating-conversation', $persisted['phase']);
    $this->assertSame('Round 0: Turn 1: Actor System: Updating conversation state...', $persisted['message']);
    $this->assertSame('req-1', $persisted['client_request_id']);

    $this->assertSame('reviewing-queue', $queued['phase']);
    $this->assertSame('Round 0: Turn 1: Actor System: Thinking about the 3 things you just said...', $queued['message']);
    $this->assertSame('req-2', $queued['client_request_id']);

    $this->assertSame('npc-reactions', $npc_reactions['phase']);
    $this->assertSame('System', $npc_reactions['speaker']);
    $this->assertSame('Round 0: Turn 1: Actor System: Resolving the next actor in turn order...', $npc_reactions['message']);
    $this->assertSame('req-2b', $npc_reactions['client_request_id']);

    $this->assertSame('reviewing-room', $private_started['phase']);
    $this->assertSame('Round 0: Turn 1: Actor System: Reviewing what you just said...', $private_started['message']);
    $this->assertSame('req-private', $private_started['client_request_id']);

    $this->assertNull($unknown);
  }

  /**
   * @covers ::buildProgressEventData
   */
  public function testBuildProgressEventDataUsesProvidedEncounterSnapshot(): void {
    $coordinator = $this->createMock(GameCoordinatorService::class);
    $coordinator->method('getFullState')->willReturn([
      'success' => TRUE,
      'round' => 1,
      'turn' => ['index' => 0],
      'game_state' => ['round' => 1, 'turn' => ['index' => 0]],
    ]);

    $controller = $this->createController($this->createMock(RoomChatService::class), NULL, $coordinator);
    $method = new \ReflectionMethod(RoomChatController::class, 'buildProgressEventData');
    $method->setAccessible(TRUE);

    $snapshot_message = $method->invoke($controller, 'npc_reactions_generating', 'req-snapshot', [
      'campaign_id' => 63,
      'encounter_round_raw' => 1,
      'encounter_turn_index_raw' => 1,
    ]);

    $this->assertSame('Round 0: Turn 2: Actor System: Resolving the next actor in turn order...', $snapshot_message['message']);
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
      ->with(63, 'room-1', 'Burasco', 'Who answers?', 'player', 218, 'whisper:npc-1', FALSE, FALSE)
      ->willReturn([
        'message' => ['speaker' => 'Burasco', 'message' => 'Who answers?'],
        'turn_log_key' => 'room_turn_abc',
        'turn_logs' => [
          ['speaker' => 'System', 'message' => 'Turn order: Narrator -> Game Master -> Eldric 17.', 'type' => 'system'],
        ],
      ]);

    $controller = $this->createController($chat_service);
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
        'channel' => 'whisper:npc-1',
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
   * @covers ::postChatMessage
   */
  public function testPostChatMessageIncludesEncounterStateForClientResync(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('hasCampaignAccess')
      ->with(63)
      ->willReturn(TRUE);

    $coordinator = $this->createMock(GameCoordinatorService::class);
    $gm_subsystem = $this->createMock(GameMasterSubsystemService::class);
    $gm_subsystem->expects($this->once())
      ->method('handlePlayerRoomChat')
      ->with(63, 'room-1', 241, 'Any work for me?', FALSE, FALSE)
      ->willReturn([
        'message' => [
          'speaker' => 'Burasco',
          'message' => 'Any work for me?',
          'type' => 'player',
        ],
        'game_state' => [
          'round' => 1,
          'turn' => ['entity' => 'pc-241-324', 'index' => 0, 'actions_remaining' => 2],
        ],
        'available_actions' => ['talk', 'delay', 'end_turn'],
        'action_contract' => ['available_actions' => ['talk', 'delay', 'end_turn']],
      ]);

    $controller = $this->createController($chat_service, NULL, $coordinator, $gm_subsystem);
    $request = Request::create(
      '/api/campaign/63/room/room-1/chat',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'speaker' => 'Burasco',
        'message' => 'Any work for me?',
        'type' => 'player',
        'character_id' => 241,
        'channel' => 'room',
      ])
    );

    $response = $controller->postChatMessage(63, 'room-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame(2, $payload['data']['game_state']['turn']['actions_remaining']);
    $this->assertSame(['talk', 'delay', 'end_turn'], $payload['data']['available_actions']);
    $this->assertSame(['talk', 'delay', 'end_turn'], $payload['data']['action_contract']['available_actions']);
  }

  /**
   * @covers ::postChatMessage
   */
  public function testPostChatMessageRoutesDelayChatIntoEncounterDelayAction(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('hasCampaignAccess')
      ->with(63)
      ->willReturn(TRUE);

    $coordinator = $this->createMock(GameCoordinatorService::class);
    $gm_subsystem = $this->createMock(GameMasterSubsystemService::class);
    $gm_subsystem->expects($this->once())
      ->method('handlePlayerRoomChat')
      ->with(63, 'room-1', 241, "I'm waiting until after Eldric", FALSE, FALSE)
      ->willReturn([
        'message' => [
          'speaker' => 'Felaiamiali',
          'message' => "I'm waiting until after Eldric",
          'type' => 'player',
        ],
        'game_state' => [
          'round' => 1,
          'turn' => ['entity' => 'pc-241-324', 'index' => 1, 'actions_remaining' => 2],
        ],
        'available_actions' => ['talk', 'delay', 'end_turn'],
        'action_contract' => ['available_actions' => ['talk', 'delay', 'end_turn']],
      ]);

    $controller = $this->createController($chat_service, NULL, $coordinator, $gm_subsystem);
    $request = Request::create(
      '/api/campaign/63/room/room-1/chat',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'speaker' => 'Felaiamiali',
        'message' => "I'm waiting until after Eldric",
        'type' => 'player',
        'character_id' => 241,
        'channel' => 'room',
      ])
    );

    $response = $controller->postChatMessage(63, 'room-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame(2, $payload['data']['game_state']['turn']['actions_remaining']);
    $this->assertSame(['talk', 'delay', 'end_turn'], $payload['data']['available_actions']);
  }

  /**
   * @covers ::postChatMessage
   */
  public function testPostChatMessageAutoResolvesNpcTurnBlockBeforePlayerTalk(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('hasCampaignAccess')
      ->with(63)
      ->willReturn(TRUE);

    $coordinator = $this->createMock(GameCoordinatorService::class);
    $gm_subsystem = $this->createMock(GameMasterSubsystemService::class);
    $gm_subsystem->expects($this->once())
      ->method('handlePlayerRoomChat')
      ->with(63, 'room-1', 241, 'Who answers?', FALSE, FALSE)
      ->willReturn([
        'message' => [
          'speaker' => 'Burasco',
          'message' => 'Who answers?',
          'type' => 'player',
        ],
      ]);

    $controller = $this->createController($chat_service, NULL, $coordinator, $gm_subsystem);
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
        'character_id' => 241,
        'channel' => 'room',
      ])
    );

    $response = $controller->postChatMessage(63, 'room-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame('Who answers?', $payload['data']['message']['message']);
    $this->assertSame('Burasco', $payload['data']['message']['speaker']);
  }

  /**
   * @covers ::emitStreamedTurnResult
   */
  public function testEmitStreamedTurnResultSuppressesHarnessSystemLogsFromTranscript(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('completeDeferredNpcInterjections')
      ->with(63, 'room-1', 'Who answers?', 'The room quiets.', 218)
      ->willReturn([
        'turn_log_key' => 'room_turn_stream',
        'turn_logs' => [
          ['speaker' => 'System', 'message' => 'Current turn: Eldric.', 'type' => 'system', 'turn_role' => 'npc', 'turn_name' => 'Eldric', 'turn_index' => 3, 'internal_log' => TRUE],
        ],
        'messages' => [
          ['speaker' => 'Eldric', 'message' => 'I do.', 'type' => 'npc'],
        ],
      ]);

    $controller = $this->createController($chat_service);
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
          ['speaker' => 'System', 'message' => 'Turn order: Narrator -> Game Master -> Eldric 17.', 'type' => 'system', 'internal_log' => TRUE],
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
      ['gm_response', 'thinking', 'npc_interjection', 'complete'],
      $event_types
    );
    $this->assertSame('The room quiets.', $events[0]['data']['message']);
    $this->assertSame('I do.', $events[2]['data']['message']);
    $this->assertSame([], $events[3]['data']['turn_logs']);
    $this->assertSame('room_turn_stream', $events[3]['data']['turn_log_key']);
  }

  /**
   * @covers ::emitStreamError
   */
  public function testEmitStreamErrorIncludesDebugContextAndLogsIt(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Room chat stream failed'),
        $this->callback(function (array $context): bool {
          return $context['campaign_id'] === 63
            && $context['room_id'] === 'room-1'
            && $context['character_id'] === 218
            && $context['channel'] === 'room'
            && $context['client_request_id'] === 'req-err'
            && $context['stream_mode'] === 'post_message'
            && $context['message_length'] === 12
            && $context['message'] === 'Turn blocked'
            && $context['status'] === 409
            && $context['exception_class'] === \InvalidArgumentException::class
            && $context['exception'] instanceof \InvalidArgumentException
            && str_starts_with($context['debug_id'], 'roomchat-');
        })
      );

    $controller = $this->createController($this->createMock(RoomChatService::class), $logger);
    $method = new \ReflectionMethod(RoomChatController::class, 'emitStreamError');
    $method->setAccessible(TRUE);

    $events = [];
    $emit = static function (array $event) use (&$events): void {
      $events[] = $event;
    };

    $method->invoke(
      $controller,
      $emit,
      new \InvalidArgumentException('Turn blocked', 409),
      [
        'campaign_id' => 63,
        'room_id' => 'room-1',
        'character_id' => 218,
        'channel' => 'room',
        'client_request_id' => 'req-err',
        'stream_mode' => 'post_message',
        'speaker' => 'Burasco',
        'type' => 'player',
        'message_length' => 12,
      ]
    );

    $this->assertCount(1, $events);
    $this->assertSame('error', $events[0]['type']);
    $this->assertSame('Turn blocked', $events[0]['error']);
    $this->assertSame(409, $events[0]['status']);
    $this->assertSame('req-err', $events[0]['debug']['client_request_id']);
    $this->assertSame(63, $events[0]['debug']['campaign_id']);
    $this->assertSame('room-1', $events[0]['debug']['room_id']);
    $this->assertSame(218, $events[0]['debug']['character_id']);
    $this->assertSame('room', $events[0]['debug']['channel']);
    $this->assertSame('post_message', $events[0]['debug']['stream_mode']);
    $this->assertSame(409, $events[0]['debug']['status']);
    $this->assertStringStartsWith('roomchat-', $events[0]['debug']['debug_id']);
  }

}
