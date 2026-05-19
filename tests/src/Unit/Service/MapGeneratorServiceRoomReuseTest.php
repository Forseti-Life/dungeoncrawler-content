<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\MapGeneratorService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests campaign room reuse during navigation.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MapGeneratorService
 */
class MapGeneratorServiceRoomReuseTest extends UnitTestCase {

  /**
   * Tests existing named rooms are reused before generating duplicates.
   *
   * @covers ::findExistingCampaignRoomMatch
   */
  public function testFindExistingCampaignRoomMatchPrefersExactConnectedRoom(): void {
    $service = new class extends MapGeneratorService {
      public function __construct() {}

      public function callFindExistingCampaignRoomMatch(array $dungeon_data, string $destination, string $origin_room_id): ?array {
        return $this->findExistingCampaignRoomMatch($dungeon_data, $destination, $origin_room_id);
      }
    };

    $dungeonData = [
      'rooms' => [
        [
          'room_id' => 'tankard-original',
          'name' => 'The Gilded Tankard',
          'connections' => [
            ['target_room_id' => 'archive', 'type' => 'passage'],
          ],
        ],
        [
          'room_id' => 'archive',
          'name' => 'Academy Archives',
          'connections' => [
            ['target_room_id' => 'tankard-original', 'type' => 'passage'],
          ],
        ],
        [
          'room_id' => 'tankard-duplicate',
          'name' => 'The Gilded Tankard',
          'connections' => [],
        ],
      ],
    ];

    $match = $service->callFindExistingCampaignRoomMatch($dungeonData, 'The Gilded Tankard', 'archive');

    $this->assertIsArray($match);
    $this->assertSame('tankard-original', $match['room']['room_id']);
    $this->assertSame(0, $match['room_index']);
  }

  /**
   * Tests room connection creation is idempotent.
   *
   * @covers ::createRoomConnection
   */
  public function testCreateRoomConnectionAvoidsDuplicates(): void {
    $service = new class extends MapGeneratorService {
      public function __construct() {}

      public function callCreateRoomConnection(array &$dungeon_data, string $from_room_id, string $to_room_id): void {
        $this->createRoomConnection($dungeon_data, $from_room_id, $to_room_id);
      }
    };

    $dungeonData = [
      'hex_map' => [
        'connections' => [],
      ],
      'rooms' => [
        ['room_id' => 'archive', 'connections' => []],
        ['room_id' => 'tankard', 'connections' => []],
      ],
    ];

    $service->callCreateRoomConnection($dungeonData, 'archive', 'tankard');
    $service->callCreateRoomConnection($dungeonData, 'archive', 'tankard');

    $this->assertCount(1, $dungeonData['hex_map']['connections']);
    $this->assertCount(1, $dungeonData['rooms'][0]['connections']);
    $this->assertCount(1, $dungeonData['rooms'][1]['connections']);
  }

}
