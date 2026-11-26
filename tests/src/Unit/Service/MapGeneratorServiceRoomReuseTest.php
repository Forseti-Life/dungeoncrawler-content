<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\MapGeneratorService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;

/**
 * Tests campaign room reuse during navigation.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MapGeneratorService
 */
class MapGeneratorServiceRoomReuseTest extends UnitTestCase {

  /**
   * @covers ::mergeCampaignRoomRowsIntoDungeonData
   */
  public function testExistingRoomReferenceReceivesGeometryWithoutLosingRuntimeState(): void {
    $hexes = [['q' => -2, 'r' => 0, 'terrain_type' => 'floor']];
    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())->method('fetchAllAssoc')->with('room_id')->willReturn([
      'tavern_entrance' => (object) [
        'room_id' => 'tavern_entrance',
        'name' => 'The Gilded Tankard',
        'layout_data' => json_encode(['hexes' => $hexes], JSON_THROW_ON_ERROR),
      ],
    ]);
    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->with('campaign_id', 1159)->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    $database = $this->createMock(Connection::class);
    $database->method('select')->with('dc_campaign_rooms', 'r')->willReturn($select);
    $service = new class($database) extends MapGeneratorService {
      public function __construct(Connection $database) {
        $this->database = $database;
        $this->logger = new NullLogger();
      }

      public function match(array &$dungeon_data): ?array {
        return $this->findExistingCampaignRoomMatch(1159, $dungeon_data, 'The Gilded Tankard', 'parlor');
      }
    };
    $chat = [['speaker' => 'Eldric', 'message' => 'Welcome back.']];
    $data = ['rooms' => [
      ['room_id' => 'tavern_entrance', 'name' => 'The Gilded Tankard', 'hexes' => [], 'chat' => $chat],
      ['room_id' => 'parlor', 'name' => 'Parlor'],
    ]];

    $match = $service->match($data);

    $this->assertSame(0, $match['room_index']);
    $this->assertSame($hexes, $match['room']['hexes']);
    $this->assertSame($chat, $match['room']['chat']);
    $this->assertCount(2, $data['rooms']);
  }

  /**
   * Tests existing named rooms are reused before generating duplicates.
   *
   * @covers ::findExistingCampaignRoomMatch
   */
  public function testFindExistingCampaignRoomMatchPrefersExactConnectedRoom(): void {
    $service = new class extends MapGeneratorService {
      public function __construct() {
        $this->logger = new NullLogger();
      }

      public function callFindExistingCampaignRoomMatch(array $dungeon_data, string $destination, string $origin_room_id): ?array {
        return $this->findExistingCampaignRoomMatch(1, $dungeon_data, $destination, $origin_room_id);
      }

      protected function mergeCampaignRoomRowsIntoDungeonData(int $campaign_id, array &$dungeon_data): void {
        $dungeon_data['rooms'] ??= [];
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
      public function __construct() {
        $this->logger = new NullLogger();
      }

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
