<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler;
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

}
