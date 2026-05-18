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

    $player_statement = $this->createMock(StatementInterface::class);
    $player_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
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
    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([]);
    $room_statement = $this->createMock(StatementInterface::class);
    $room_statement->method('fetchField')->willReturn(FALSE);

    $player_select = $this->createSelectMock($player_statement);
    $npc_select = $this->createSelectMock($npc_statement);
    $room_select = $this->createSelectMock($room_statement);

    $character_select_calls = 0;
    $database->method('select')->willReturnCallback(function (string $table, string $alias) use ($player_select, $npc_select, $room_select, &$character_select_calls) {
      if ($table === 'dc_campaign_characters' && $alias === 'cc') {
        $character_select_calls++;
        return $character_select_calls === 1 ? $player_select : $npc_select;
      }
      if ($table === 'dc_campaign_rooms' && $alias === 'r') {
        return $room_select;
      }
      throw new \RuntimeException(sprintf('Unexpected select %s %s', $table, $alias));
    });

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
    $this->assertSame(205, $result['entities'][1]['state']['metadata']['source_character_id']);
    $this->assertSame(218, $result['entities'][1]['state']['metadata']['campaign_character_id']);
    $this->assertSame('pc-63-205', $result['entities'][1]['state']['metadata']['runtime_entity_id']);
    $this->assertSame('Brakouk', $result['entities'][1]['state']['metadata']['display_name']);
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesDoesNotMatchByName(): void {
    $database = $this->createMock(Connection::class);
    $animal_companion_service = $this->createMock(AnimalCompanionService::class);
    $service = new class($database, $animal_companion_service) extends CampaignCharacterRuntimeSyncService {
      public function syncNpcEntities(array $payload, int $campaign_id, string $active_room_id): array {
        return $this->syncActiveRoomNpcEntities($payload, $campaign_id, $active_room_id);
      }
    };

    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 259,
        'instance_id' => 'npc_scholar_npc',
        'name' => 'Marta the Scholar',
        'state_data' => json_encode([
          'content_id' => 'scholar_npc',
          'role' => 'scholar',
          'team' => 'neutral',
        ]),
        'position_q' => 1,
        'position_r' => 0,
        'location_ref' => 'room-tavern',
      ],
    ]);
    $room_statement = $this->createMock(StatementInterface::class);
    $room_statement->method('fetchField')->willReturn('room-tavern');

    $npc_select = $this->createSelectMock($npc_statement);
    $room_select = $this->createSelectMock($room_statement);
    $update = new class() {
      public function fields(array $fields): self {
        return $this;
      }
      public function condition(string $field, mixed $value, ?string $operator = NULL): self {
        return $this;
      }
      public function execute(): int {
        return 1;
      }
    };

    $database->method('select')->willReturnCallback(function (string $table, string $alias) use ($npc_select, $room_select) {
      if ($table === 'dc_campaign_characters' && $alias === 'cc') {
        return $npc_select;
      }
      if ($table === 'dc_campaign_rooms' && $alias === 'r') {
        return $room_select;
      }
      throw new \RuntimeException(sprintf('Unexpected select %s %s', $table, $alias));
    });
    $database->method('update')->with('dc_campaign_characters')->willReturn($update);

    $payload = [
      'active_room_id' => 'room-tavern',
      'rooms' => [
        'room-tavern' => [
          'hexes' => [
            ['q' => 0, 'r' => 0],
            ['q' => 1, 'r' => 0],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_type' => 'npc',
          'instance_id' => 'npc_wrong_marta',
          'entity_instance_id' => 'npc_wrong_marta',
          'entity_ref' => [
            'content_type' => 'npc',
            'content_id' => 'wrong_ref',
          ],
          'placement' => [
            'room_id' => 'room-tavern',
            'hex' => ['q' => 0, 'r' => 0],
          ],
          'state' => [
            'metadata' => [
              'display_name' => 'Marta the Scholar',
              'name' => 'Marta the Scholar',
            ],
          ],
        ],
      ],
    ];

    $result = $service->syncNpcEntities($payload, 69, 'room-tavern');

    $this->assertCount(2, $result['entities']);
    $this->assertSame('wrong_ref', $result['entities'][0]['entity_ref']['content_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][1]['instance_id']);
    $this->assertSame('scholar_npc', $result['entities'][1]['entity_ref']['content_id']);
    $this->assertSame(259, $result['entities'][1]['state']['metadata']['campaign_character_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][1]['state']['metadata']['runtime_entity_id']);
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesEnrichesExistingEntityInPlace(): void {
    $database = $this->createMock(Connection::class);
    $animal_companion_service = $this->createMock(AnimalCompanionService::class);
    $service = new class($database, $animal_companion_service) extends CampaignCharacterRuntimeSyncService {
      public function syncNpcEntities(array $payload, int $campaign_id, string $active_room_id): array {
        return $this->syncActiveRoomNpcEntities($payload, $campaign_id, $active_room_id);
      }
    };

    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 259,
        'instance_id' => 'npc_scholar_npc',
        'name' => 'Marta the Scholar',
        'state_data' => json_encode([
          'content_id' => 'scholar_npc',
          'role' => 'quest_giver',
          'description' => 'Scholar on alert',
          'team' => 'neutral',
        ]),
        'position_q' => 1,
        'position_r' => 0,
        'location_ref' => 'room-tavern',
      ],
    ]);
    $room_statement = $this->createMock(StatementInterface::class);
    $room_statement->method('fetchField')->willReturn('room-tavern');

    $npc_select = $this->createSelectMock($npc_statement);
    $room_select = $this->createSelectMock($room_statement);
    $database->method('select')->willReturnCallback(function (string $table, string $alias) use ($npc_select, $room_select) {
      if ($table === 'dc_campaign_characters' && $alias === 'cc') {
        return $npc_select;
      }
      if ($table === 'dc_campaign_rooms' && $alias === 'r') {
        return $room_select;
      }
      throw new \RuntimeException(sprintf('Unexpected select %s %s', $table, $alias));
    });
    $database->method('update')->with('dc_campaign_characters')->willReturn(new class() {
      public function fields(array $fields): self {
        return $this;
      }
      public function condition(string $field, mixed $value, ?string $operator = NULL): self {
        return $this;
      }
      public function execute(): int {
        return 1;
      }
    });

    $payload = [
      'active_room_id' => 'room-tavern',
      'rooms' => [
        'room-tavern' => [
          'hexes' => [
            ['q' => 0, 'r' => 0],
            ['q' => 1, 'r' => 0],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_type' => 'npc',
          'instance_id' => 'old-marta',
          'entity_instance_id' => 'old-marta',
          'entity_ref' => [
            'content_type' => 'npc',
            'content_id' => 'scholar_npc',
          ],
          'placement' => [
            'room_id' => 'room-tavern',
            'hex' => ['q' => 0, 'r' => 0],
          ],
          'state' => [
            'metadata' => [
              'display_name' => 'Marta the Scholar',
              'name' => 'Marta the Scholar',
            ],
          ],
        ],
      ],
    ];

    $result = $service->syncNpcEntities($payload, 69, 'room-tavern');

    $this->assertCount(1, $result['entities']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['instance_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['entity_instance_id']);
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['campaign_character_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['state']['metadata']['runtime_entity_id']);
    $this->assertSame('quest_giver', $result['entities'][0]['state']['metadata']['role']);
  }

  /**
   * @covers ::syncActiveRoomPlayerEntities
   */
  public function testSyncActiveRoomPlayerEntitiesStillSyncsNpcsWithoutActivePlayers(): void {
    $database = $this->createMock(Connection::class);
    $animal_companion_service = $this->createMock(AnimalCompanionService::class);
    $service = new CampaignCharacterRuntimeSyncService($database, $animal_companion_service);

    $player_statement = $this->createMock(StatementInterface::class);
    $player_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([]);
    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 259,
        'instance_id' => 'npc_scholar_npc',
        'name' => 'Marta the Scholar',
        'state_data' => json_encode([
          'content_id' => 'scholar_npc',
          'role' => 'quest_giver',
          'description' => 'Scholar on alert',
          'team' => 'neutral',
        ]),
        'position_q' => 1,
        'position_r' => 0,
        'location_ref' => 'tavern_entrance',
      ],
    ]);
    $room_id_statement = $this->createMock(StatementInterface::class);
    $room_id_statement->method('fetchField')->willReturn(FALSE);
    $room_name_statement = $this->createMock(StatementInterface::class);
    $room_name_statement->method('fetchCol')->willReturn(['tavern_entrance']);

    $player_select = $this->createSelectMock($player_statement);
    $npc_select = $this->createSelectMock($npc_statement);
    $room_id_select = $this->createSelectMock($room_id_statement);
    $room_name_select = $this->createSelectMock($room_name_statement);

    $character_select_calls = 0;
    $room_select_calls = 0;
    $database->method('select')->willReturnCallback(function (string $table, string $alias) use ($player_select, $npc_select, $room_id_select, $room_name_select, &$character_select_calls, &$room_select_calls) {
      if ($table === 'dc_campaign_characters' && $alias === 'cc') {
        $character_select_calls++;
        return $character_select_calls === 1 ? $player_select : $npc_select;
      }
      if ($table === 'dc_campaign_rooms' && $alias === 'r') {
        $room_select_calls++;
        return $room_select_calls === 1 ? $room_id_select : $room_name_select;
      }
      throw new \RuntimeException(sprintf('Unexpected select %s %s', $table, $alias));
    });
    $database->method('update')->with('dc_campaign_characters')->willReturn(new class() {
      public function fields(array $fields): self {
        return $this;
      }
      public function condition(string $field, mixed $value, ?string $operator = NULL): self {
        return $this;
      }
      public function execute(): int {
        return 1;
      }
    });

    $payload = [
      'active_room_id' => '7f2f1051-5f88-45a2-a66a-0f7063900001',
      'rooms' => [
        [
          'room_id' => '7f2f1051-5f88-45a2-a66a-0f7063900001',
          'name' => 'The Gilded Tankard',
          'hexes' => [
            ['q' => 0, 'r' => 0],
            ['q' => 1, 'r' => 0],
          ],
        ],
      ],
      'entities' => [],
    ];

    $result = $service->syncActiveRoomPlayerEntities($payload, 69, 'pc-69-198');

    $this->assertCount(1, $result['entities']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['instance_id']);
    $this->assertSame('scholar_npc', $result['entities'][0]['entity_ref']['content_id']);
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['campaign_character_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['state']['metadata']['runtime_entity_id']);
  }

  /**
   * Build a generic fluent select mock.
   */
  protected function createSelectMock(StatementInterface $statement): Select {
    $select = $this->createMock(Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    return $select;
  }

}
