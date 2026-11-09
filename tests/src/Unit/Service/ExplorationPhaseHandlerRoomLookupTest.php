<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\ActionTargetingService;
use Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests room lookup helpers in exploration phase handling.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerRoomLookupTest extends UnitTestCase {

  /**
   * @covers ::findRoomIndexById
   */
  public function testFindRoomIndexByIdReturnsMatchingIndex(): void {
    $handler = (new \ReflectionClass(ExplorationPhaseHandler::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($handler, 'findRoomIndexById');
    $method->setAccessible(TRUE);

    $dungeon_data = [
      'rooms' => [
        ['room_id' => 'room-a'],
        ['room_id' => 'room-b'],
      ],
    ];

    $this->assertSame(1, $method->invoke($handler, 'room-b', $dungeon_data));
    $this->assertNull($method->invoke($handler, 'room-z', $dungeon_data));
  }

  /**
   * @covers ::getActiveRoom
   * @covers ::getActiveRoomIndex
   */
  public function testGetActiveRoomResolvesRoomFromActiveId(): void {
    $handler = (new \ReflectionClass(ExplorationPhaseHandler::class))
      ->newInstanceWithoutConstructor();
    $get_active_room = new \ReflectionMethod($handler, 'getActiveRoom');
    $get_active_room->setAccessible(TRUE);
    $get_active_room_index = new \ReflectionMethod($handler, 'getActiveRoomIndex');
    $get_active_room_index->setAccessible(TRUE);

    $dungeon_data = [
      'active_room_id' => 'room-b',
      'rooms' => [
        ['room_id' => 'room-a', 'name' => 'Entry'],
        ['room_id' => 'room-b', 'name' => 'Vault'],
      ],
    ];

    $this->assertSame(1, $get_active_room_index->invoke($handler, $dungeon_data));
    $this->assertSame('Vault', $get_active_room->invoke($handler, $dungeon_data)['name']);
  }

  /**
   * @covers ::findRoomInDungeon
   */
  public function testFindRoomInDungeonReturnsRoomById(): void {
    $handler = (new \ReflectionClass(ExplorationPhaseHandler::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($handler, 'findRoomInDungeon');
    $method->setAccessible(TRUE);

    $dungeon_data = [
      'rooms' => [
        ['room_id' => 'room-a', 'name' => 'Entry'],
        ['room_id' => 'room-b', 'name' => 'Vault'],
      ],
    ];

    $this->assertSame('Entry', $method->invoke($handler, 'room-a', $dungeon_data)['name']);
    $this->assertNull($method->invoke($handler, 'room-z', $dungeon_data));
  }

  /**
   * @covers ::validateIntent
   */
  public function testPublishedTransitionRequiresAvailableCanonicalExit(): void {
    $handler = (new \ReflectionClass(ExplorationPhaseHandler::class))
      ->newInstanceWithoutConstructor();
    $navigation = $this->createMock(NavigationService::class);
    (new \ReflectionProperty(ExplorationPhaseHandler::class, 'navigationService'))
      ->setValue($handler, $navigation);
    $dungeon = [
      'source_dungeon_id' => 'published-dungeon',
      'active_room_id' => 'entry',
      'rooms' => [
        ['room_id' => 'entry'],
        ['room_id' => 'next'],
        ['room_id' => 'distant'],
      ],
    ];
    $intent = [
      'type' => 'transition',
      'params' => [
        'target_room_id' => 'next',
        'connection_id' => 'entry-next',
        'target_hex' => ['q' => 1, 'r' => 0],
      ],
    ];
    $resolved = NULL;
    $navigation->expects($this->exactly(5))
      ->method('resolveRequestedCapability')
      ->with($dungeon, 'entry', 'entry-next', ['q' => 1, 'r' => 0])
      ->willReturnCallback(static function () use (&$resolved): ?array {
        return $resolved;
      });

    $this->assertFalse($handler->validateIntent($intent, [], $dungeon)['valid']);
    $resolved = ['target_room_id' => 'distant', 'available' => TRUE, 'is_discovered' => TRUE];
    $this->assertFalse($handler->validateIntent($intent, [], $dungeon)['valid']);
    $resolved = ['target_room_id' => 'next', 'available' => FALSE, 'is_discovered' => TRUE, 'blocked_reason' => 'locked'];
    $this->assertFalse($handler->validateIntent($intent, [], $dungeon)['valid']);
    $resolved = ['target_room_id' => 'next', 'available' => TRUE, 'is_discovered' => FALSE];
    $this->assertFalse($handler->validateIntent($intent, [], $dungeon)['valid']);
    $resolved = ['target_room_id' => 'next', 'available' => TRUE, 'is_discovered' => TRUE];
    $this->assertTrue($handler->validateIntent($intent, [], $dungeon)['valid']);
  }

  /**
   * @covers ::validateIntent
   */
  public function testPublishedInteractionRequiresPlacementInActiveRoom(): void {
    $handler = (new \ReflectionClass(ExplorationPhaseHandler::class))
      ->newInstanceWithoutConstructor();
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchField')->willReturn(json_encode([
      'entities' => [['instance_id' => 'entry-marker']],
    ], JSON_THROW_ON_ERROR));
    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    $database = $this->createMock(Connection::class);
    $database->method('select')->with('dc_campaign_rooms', 'r')->willReturn($select);
    (new \ReflectionProperty(ExplorationPhaseHandler::class, 'database'))->setValue($handler, $database);
    $targeting = $this->createMock(ActionTargetingService::class);
    $targeting->method('normalizeTargetRef')->willReturnCallback(static fn(?string $target): ?string => $target);
    (new \ReflectionProperty(ExplorationPhaseHandler::class, 'actionTargetingService'))->setValue($handler, $targeting);
    $dungeon = [
      'source_dungeon_id' => 'published-dungeon',
      'campaign_id' => 123,
      'active_room_id' => 'entry',
      'rooms' => [
        ['room_id' => 'entry', 'entities' => [['instance_id' => 'entry-marker']]],
        ['room_id' => 'next', 'entities' => [['instance_id' => 'next-marker']]],
      ],
    ];
    foreach ([NULL, '', 'next-marker', 'invented-marker'] as $target) {
      $this->assertFalse($handler->validateIntent([
        'type' => 'interact',
        'target' => $target,
      ], [], $dungeon)['valid']);
    }
    $this->assertTrue($handler->validateIntent([
      'type' => 'interact',
      'target' => 'entry-marker',
    ], [], $dungeon)['valid']);
    unset($dungeon['source_dungeon_id']);
    $this->assertTrue($handler->validateIntent([
      'type' => 'interact',
      'target' => 'invented-marker',
    ], [], $dungeon)['valid']);
  }

}
