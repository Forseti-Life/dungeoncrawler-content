<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\AnimalCompanionService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService
 * @group dungeoncrawler_content
 */
class CampaignCharacterRuntimeSyncServiceTest extends UnitTestCase {

  /**
   * @covers ::syncActiveRoomPlayerEntities
   */
  public function testSyncActiveRoomPlayerEntitiesReplacesTemplatePlayersWithCampaignCharacterRows(): void {
    $database = $this->createMock(Connection::class);
    $animal_companion_service = $this->createMock(AnimalCompanionService::class);
    $animal_companion_service->method('resolveCompanionFromCharacterData')->willReturn(NULL);

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 218,
        'character_id' => 205,
        'instance_id' => 'pc-63-205',
        'name' => 'Brakouk',
        'hp_current' => 18,
        'hp_max' => 18,
        'armor_class' => 10,
        'character_data' => json_encode(['name' => 'Brakouk']),
        'position_q' => -4,
        'position_r' => -3,
        'last_room_id' => 'room-bazaar',
        'location_ref' => 'room-bazaar',
        'updated' => 1778962951,
      ],
    ]);

    $select = $this->createMock(Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    $database->method('select')->with('dc_campaign_characters', 'cc')->willReturn($select);

    $service = new CampaignCharacterRuntimeSyncService($database, $animal_companion_service);

    $payload = [
      'active_room_id' => 'room-bazaar',
      'rooms' => [
        'room-bazaar' => [
          'hexes' => [
            ['q' => -4, 'r' => -3],
            ['q' => -3, 'r' => -3],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_type' => 'player_character',
          'instance_id' => 'template-player',
          'placement' => [
            'room_id' => 'room-bazaar',
            'hex' => ['q' => 0, 'r' => 0],
          ],
        ],
        [
          'entity_type' => 'npc',
          'instance_id' => 'npc-mira',
          'placement' => [
            'room_id' => 'room-bazaar',
            'hex' => ['q' => -3, 'r' => -3],
          ],
        ],
      ],
    ];

    $result = $service->syncActiveRoomPlayerEntities($payload, 63, 'pc-63-205');

    $this->assertCount(2, $result['entities']);
    $this->assertSame('npc-mira', $result['entities'][0]['instance_id']);
    $this->assertSame('pc-63-205', $result['entities'][1]['instance_id']);
    $this->assertSame('pc-63-205', $result['entities'][1]['entity_instance_id']);
    $this->assertSame('room-bazaar', $result['entities'][1]['placement']['room_id']);
    $this->assertSame(-4, $result['entities'][1]['placement']['hex']['q']);
    $this->assertSame(-3, $result['entities'][1]['placement']['hex']['r']);
    $this->assertSame(218, $result['entities'][1]['state']['metadata']['character_id']);
    $this->assertSame('Brakouk', $result['entities'][1]['state']['metadata']['display_name']);
  }

}
