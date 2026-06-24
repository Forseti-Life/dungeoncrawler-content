<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService
 *
 * @group dungeoncrawler_content
 * @group service
 */
class GameMasterSubsystemServiceTest extends UnitTestCase {

  /**
   * @covers ::getActorActionAvailability
   */
  public function testGetActorActionAvailabilityResolvesActorFromCharacterId(): void {
    $coordinator = $this->createMock(GameCoordinatorService::class);
    $room_chat_service = $this->createMock(RoomChatService::class);
    $coordinator->expects($this->once())
      ->method('resolveActorIdForCharacterId')
      ->with(63, 241)
      ->willReturn('pc-241-324');
    $coordinator->expects($this->once())
      ->method('getActionAvailabilityForActor')
      ->with(63, 'pc-241-324')
      ->willReturn([
        'available_actions' => ['talk', 'delay', 'end_turn'],
        'action_contract' => ['available_actions' => ['talk', 'delay', 'end_turn']],
      ]);

    $service = new GameMasterSubsystemService($coordinator, $room_chat_service);
    $result = $service->getActorActionAvailability(63, NULL, 241);

    $this->assertSame(['talk', 'delay', 'end_turn'], $result['available_actions']);
    $this->assertSame(['talk', 'delay', 'end_turn'], $result['action_contract']['available_actions']);
  }

  /**
   * @covers ::handlePlayerRoomChat
   */
  public function testHandlePlayerRoomChatRoutesStandardPlayerSpeechWithoutSpendingAction(): void {
    $coordinator = $this->createMock(GameCoordinatorService::class);
    $room_chat_service = $this->createMock(RoomChatService::class);
    $coordinator->expects($this->once())
      ->method('resolveActorIdForCharacterId')
      ->with(63, 241)
      ->willReturn('pc-241-324');
    $coordinator->expects($this->once())
      ->method('getActiveRoomId')
      ->with(63, 'pc-241-324')
      ->willReturn('room-1');
    $coordinator->expects($this->once())
      ->method('resolveActorDisplayName')
      ->with(63, 'pc-241-324')
      ->willReturn('Tikask');
    $coordinator->expects($this->once())
      ->method('getFullState')
      ->willReturn([
        'game_state' => [
          'turn' => [
            'entity' => 'pc-241-324',
            'index' => 0,
            'actions_remaining' => 2,
          ],
        ],
        'available_actions' => ['talk', 'delay', 'end_turn'],
        'action_contract' => ['available_actions' => ['talk', 'delay', 'end_turn']],
      ]);
    $coordinator->expects($this->never())
      ->method('processAction');
    $room_chat_service->expects($this->once())
      ->method('postMessage')
      ->with(
        63,
        'room-1',
        'Tikask',
        'Any work for me?',
        'player',
        241,
        'room',
        TRUE,
        FALSE,
        NULL,
        ['_validated_encounter_room_chat' => TRUE]
      )
      ->willReturn([
        'message' => [
          'speaker' => 'Tikask',
          'message' => 'Any work for me?',
          'type' => 'player',
        ],
      ]);

    $service = new GameMasterSubsystemService($coordinator, $room_chat_service);
    $result = $service->handlePlayerRoomChat(63, 'room-1', 241, 'Any work for me?', FALSE, FALSE, 'Tikask');

    $this->assertSame('Any work for me?', $result['message']['message']);
    $this->assertSame('Tikask', $result['message']['speaker']);
    $this->assertFalse($result['gm_subsystem']['deterministic']);
    $this->assertSame('free_player_room_chat', $result['gm_subsystem']['route']);
    $this->assertSame('gm_backstop_chat', $result['gm_subsystem']['route_family']);
    $this->assertSame('no_deterministic_turn_control_match', $result['gm_subsystem']['handoff_reason']);
    $this->assertSame('room_chat', $result['gm_subsystem']['intent']['type']);
    $this->assertSame('pc-241-324', $result['gm_subsystem']['intent']['actor']);
    $this->assertSame(2, $result['game_state']['turn']['actions_remaining']);
  }

  /**
   * @covers ::handlePlayerRoomChat
   */
  public function testHandlePlayerRoomChatRoutesDeterministicDelayIntent(): void {
    $coordinator = $this->createMock(GameCoordinatorService::class);
    $room_chat_service = $this->createMock(RoomChatService::class);
    $coordinator->expects($this->once())
      ->method('resolveActorIdForCharacterId')
      ->with(63, 241)
      ->willReturn('pc-241-324');
    $coordinator->expects($this->once())
      ->method('getActiveRoomId')
      ->with(63, 'pc-241-324')
      ->willReturn('room-1');
    $coordinator->expects($this->never())
      ->method('resolveActorDisplayName');
    $coordinator->expects($this->once())
      ->method('getFullState')
      ->with(63)
      ->willReturn([
        'initiative_order' => [
          ['entity_id' => 'pc-241-324', 'team' => 'player', 'name' => 'Tikask'],
          ['entity_id' => 'npc-eldric', 'team' => 'npc', 'name' => 'Eldric'],
        ],
      ]);
    $coordinator->expects($this->once())
      ->method('getActionAvailabilityForActor')
      ->with(63, 'pc-241-324')
      ->willReturn([
        'available_actions' => ['talk', 'delay', 'end_turn'],
        'action_contract' => ['available_actions' => ['talk', 'delay', 'end_turn']],
      ]);
    $coordinator->expects($this->once())
      ->method('processAction')
      ->with(63, $this->callback(function (array $intent): bool {
        $this->assertSame('delay', $intent['type'] ?? NULL);
        $this->assertSame('pc-241-324', $intent['actor'] ?? NULL);
        $this->assertSame('npc-eldric', $intent['params']['delay_until_actor_id'] ?? NULL);
        $this->assertSame('room_chat', $intent['params']['source'] ?? NULL);
        return TRUE;
      }))
      ->willReturn([
        'success' => TRUE,
        'result' => [],
        'game_state' => [
          'turn' => [
            'entity' => 'pc-241-324',
            'index' => 1,
            'actions_remaining' => 2,
          ],
        ],
      ]);
    $room_chat_service->expects($this->never())
      ->method('postMessage');

    $service = new GameMasterSubsystemService($coordinator, $room_chat_service);
    $result = $service->handlePlayerRoomChat(63, 'room-1', 241, "I'm waiting until after Eldric");

    $this->assertSame(2, $result['game_state']['turn']['actions_remaining']);
    $this->assertSame('pc-241-324', $result['game_state']['turn']['entity']);
    $this->assertTrue($result['gm_subsystem']['deterministic']);
    $this->assertSame('deterministic_turn_control', $result['gm_subsystem']['route']);
    $this->assertSame('deterministic_action', $result['gm_subsystem']['route_family']);
    $this->assertSame('deterministic_turn_control_phrase', $result['gm_subsystem']['handoff_reason']);
    $this->assertSame('delay', $result['gm_subsystem']['intent']['type']);
    $this->assertSame('npc-eldric', $result['gm_subsystem']['intent']['params']['delay_until_actor_id']);
  }

}
