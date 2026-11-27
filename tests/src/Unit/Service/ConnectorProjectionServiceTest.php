<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\ConnectorProjectionService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class ConnectorProjectionServiceTest extends UnitTestCase {

  public function testDistrictBuildingEntriesProjectBidirectionalStreetConnectors(): void {
    $projector = new ConnectorProjectionService($this->createMock(Connection::class));
    $project = new \ReflectionMethod($projector, 'districtStreetContactRows');
    $ports = [
      'building:on-street' => [
        'placement_id' => 'building',
        'room_id' => 'tavern',
        'port_id' => 'front',
        'kind' => 'entry',
        'q' => 2,
        'r' => -1,
        'h3_index_res14' => '8e754e64992c377',
      ],
      'building:off-street' => [
        'placement_id' => 'building',
        'room_id' => 'tavern',
        'port_id' => 'rear',
        'kind' => 'entry',
        'q' => 8,
        'r' => 8,
        'h3_index_res14' => '8e754e64992c37f',
      ],
      'building:out' => [
        'placement_id' => 'building',
        'room_id' => 'tavern',
        'port_id' => 'out',
        'kind' => 'exit',
        'q' => 2,
        'r' => -1,
        'h3_index_res14' => '8e754e64992c377',
      ],
    ];

    $rows = $project->invoke(
      $projector,
      'absalom',
      ['placement_id' => 'street-placement', 'room_id' => 'streets'],
      ['2:-1' => TRUE],
      $ports
    );

    $this->assertCount(1, $rows);
    $this->assertSame('streets', $rows[0]['from_room_id']);
    $this->assertSame('tavern', $rows[0]['to_room_id']);
    $this->assertSame('bidirectional', $rows[0]['direction']);
    $this->assertSame(2, $rows[0]['from_hex_q']);
    $this->assertSame(-1, $rows[0]['from_hex_r']);
  }

}
