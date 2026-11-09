<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\ConnectorProjectionService;
use Drupal\dungeoncrawler_content\Service\DungeonCommandRejectedException;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class ConnectorProjectionServiceTest extends UnitTestCase {

  public function testPublishedRoomVersionIsEndpointH3Authority(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())->method('select');
    $projector = new ConnectorProjectionService($database);
    $lookup = new \ReflectionMethod($projector, 'lookupRoomHexH3IndexRes14');
    $room = ['hexes' => [
      ['q' => 0, 'r' => 0, 'h3_index_res14' => '8e754e64992c377'],
      ['q' => 1, 'r' => 0, 'h3_index_res14' => '8e754e64992c37f'],
    ]];
    $this->assertSame('8e754e64992c37f', $lookup->invoke($projector, $room, 1, 0));
    $this->assertSame('', $lookup->invoke($projector, $room, 2, 0));
  }

  public function testPublishedRoomMissingH3FailsClosed(): void {
    $projector = new ConnectorProjectionService($this->createMock(Connection::class));
    $lookup = new \ReflectionMethod($projector, 'lookupRoomHexH3IndexRes14');
    $this->expectException(DungeonCommandRejectedException::class);
    $lookup->invoke($projector, ['hexes' => [['q' => 0, 'r' => 0]]], 0, 0);
  }

}
