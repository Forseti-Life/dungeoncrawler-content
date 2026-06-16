<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService
 *
 * @group dungeoncrawler_content
 * @group service
 */
class GameMasterSubsystemServiceTest extends UnitTestCase {

  /**
   * @covers ::handlePlayerRoomChat
   */
  public function testHandlePlayerRoomChatRoutesStandardTalkIntent(): void {
    $coordinator = $this->createMock(GameCoordinatorService::class);
    $coordinator->expects($this->once())
      ->method('resolveActorIdForCharacterId')
      ->with(63, 241)
      ->willReturn('pc-241-324');
    $coordinator->expects($this->once())
      ->method('getActiveRoomId')
      ->with(63, 'pc-241-324')
      ->willReturn('room-1');
    $coordinator->expects($this->never())
      ->method('getFullState');
    $coordinator->expects($this->once())
      ->method('processAction')
      ->with(63, $this->callback(function (array $intent): bool {
        $this->assertSame('talk', $intent['type'] ?? NULL);
        $this->assertSame('pc-241-324', $intent['actor'] ?? NULL);
        $this->assertSame('Any work for me?', $intent['params']['message'] ?? NULL);
        $this->assertSame(241, $intent['params']['character_id'] ?? NULL);
        return TRUE;
      }))
      ->willReturn([
        'success' => TRUE,
        'result' => [
          'chat_message' => [
            'speaker' => 'Tikask',
            'message' => 'Any work for me?',
            'type' => 'player',
          ],
        ],
      ]);

    $service = new GameMasterSubsystemService($coordinator);
    $result = $service->handlePlayerRoomChat(63, 'room-1', 241, 'Any work for me?');

    $this->assertSame('Any work for me?', $result['message']['message']);
    $this->assertSame('Tikask', $result['message']['speaker']);
  }

  /**
   * @covers ::handlePlayerRoomChat
   */
  public function testHandlePlayerRoomChatRoutesDeterministicDelayIntent(): void {
    $coordinator = $this->createMock(GameCoordinatorService::class);
    $coordinator->expects($this->once())
      ->method('resolveActorIdForCharacterId')
      ->with(63, 241)
      ->willReturn('pc-241-324');
    $coordinator->expects($this->once())
      ->method('getActiveRoomId')
      ->with(63, 'pc-241-324')
      ->willReturn('room-1');
    $coordinator->expects($this->once())
      ->method('getFullState')
      ->with(63)
      ->willReturn([
        'available_actions' => ['talk', 'delay', 'end_turn'],
        'initiative_order' => [
          ['entity_id' => 'pc-241-324', 'team' => 'player', 'name' => 'Tikask'],
          ['entity_id' => 'npc-eldric', 'team' => 'npc', 'name' => 'Eldric'],
        ],
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

    $service = new GameMasterSubsystemService($coordinator);
    $result = $service->handlePlayerRoomChat(63, 'room-1', 241, "I'm waiting until after Eldric");

    $this->assertSame(2, $result['game_state']['turn']['actions_remaining']);
    $this->assertSame('pc-241-324', $result['game_state']['turn']['entity']);
  }

}
