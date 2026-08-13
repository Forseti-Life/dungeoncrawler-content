<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\FollowerSubsystemService;
use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
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
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);

    $player_statement = $this->createMock(StatementInterface::class);
    $player_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 218,
        'character_id' => 205,
        'source_character_id' => 205,
        'instance_id' => 'pc-63-205',
        'name' => 'Brakouk',
        'hp_current' => 18,
        'hp_max' => 18,
        'armor_class' => 10,
        'character_data' => json_encode([
          'name' => 'Brakouk',
          'skills' => ['medicine' => ['modifier' => 7, 'proficiency' => 'trained']],
          'spells' => ['prepared' => [['id' => 'magic_missile', 'name' => 'Magic Missile']]],
          'feats' => [['id' => 'power_attack', 'name' => 'Power Attack']],
          'inventory' => ['items' => [['item_id' => 'healing-potion', 'item_type' => 'consumable']]],
          'resources' => ['spellSlots' => ['1' => ['current' => 2, 'max' => 2]]],
          'actions' => ['availableActions' => ['feat' => [['id' => 'power_attack', 'name' => 'Power Attack']]]],
        ]),
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

    $service = new class($database, $follower_subsystem) extends CampaignCharacterRuntimeSyncService {
      protected function loadCanonicalPlayerCharacterState(array $record, int $campaign_id): array {
        return [
          'name' => 'Brakouk',
          'skills' => ['medicine' => ['modifier' => 7, 'proficiency' => 'trained']],
          'spells' => ['prepared' => [['id' => 'magic_missile', 'name' => 'Magic Missile']]],
          'feats' => [['id' => 'power_attack', 'name' => 'Power Attack']],
          'inventory' => ['items' => [['item_id' => 'healing-potion', 'item_type' => 'consumable']]],
          'resources' => ['spellSlots' => ['1' => ['current' => 2, 'max' => 2]]],
          'actions' => ['availableActions' => ['feat' => [['id' => 'power_attack', 'name' => 'Power Attack']]]],
        ];
      }
    };

    $payload = [
      'active_room_id' => 'room-bazaar',
      'rooms' => [
        'room-bazaar' => [
          'hexes' => [
            ['q' => -4, 'r' => -3, 'h3_index_res14' => '842a10000000001'],
            ['q' => -3, 'r' => -3, 'h3_index_res14' => '842a10000000002'],
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
    $this->assertSame(['medicine' => ['modifier' => 7, 'proficiency' => 'trained']], $result['entities'][1]['state']['skills']);
    $this->assertSame(['prepared' => [['id' => 'magic_missile', 'name' => 'Magic Missile']]], $result['entities'][1]['state']['spells']);
    $this->assertSame([['id' => 'power_attack', 'name' => 'Power Attack']], $result['entities'][1]['state']['feats']);
    $this->assertSame(['items' => [['item_id' => 'healing-potion', 'item_type' => 'consumable']]], $result['entities'][1]['state']['inventory']);
    $this->assertSame(['spellSlots' => ['1' => ['current' => 2, 'max' => 2]]], $result['entities'][1]['state']['resources']);
    $this->assertSame(['availableActions' => ['feat' => [['id' => 'power_attack', 'name' => 'Power Attack']]]], $result['entities'][1]['state']['actions']);
  }

  /**
   * @covers ::syncActiveRoomPlayerEntities
   */
  public function testSyncActiveRoomPlayerEntitiesDedupesRuntimeRowsBySourceCharacterIdentity(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);

    $player_statement = $this->createMock(StatementInterface::class);
    $player_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 992,
        'character_id' => 4488,
        'source_character_id' => 4488,
        'instance_id' => 'pc-758-992',
        'name' => 'Burasco',
        'hp_current' => 24,
        'hp_max' => 24,
        'armor_class' => 16,
        'character_data' => json_encode(['name' => 'Burasco']),
        'position_q' => 0,
        'position_r' => 0,
        'position_h3' => '842a10000000010',
        'last_room_id' => 'undead_crypt_entry',
        'location_ref' => 'undead_crypt_entry',
        'updated' => 1778962951,
      ],
      [
        'id' => 811,
        'character_id' => 4488,
        'source_character_id' => 4488,
        'instance_id' => 'pc-758-811',
        'name' => 'Burasco',
        'hp_current' => 24,
        'hp_max' => 24,
        'armor_class' => 16,
        'character_data' => json_encode(['name' => 'Burasco']),
        'position_q' => 1,
        'position_r' => 0,
        'position_h3' => '842a10000000011',
        'last_room_id' => 'undead_crypt_entry',
        'location_ref' => 'undead_crypt_entry',
        'updated' => 1778962900,
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

    $service = new class($database, $follower_subsystem) extends CampaignCharacterRuntimeSyncService {
      protected function loadCanonicalPlayerCharacterState(array $record, int $campaign_id): array {
        return ['name' => 'Burasco'];
      }
    };
    $payload = [
      'active_room_id' => 'undead_crypt_entry',
      'rooms' => [
        'undead_crypt_entry' => [
          'hexes' => [
            ['q' => 0, 'r' => 0, 'h3_index_res14' => '842a10000000010'],
            ['q' => 1, 'r' => 0, 'h3_index_res14' => '842a10000000011'],
          ],
        ],
      ],
      'entities' => [],
    ];

    $result = $service->syncActiveRoomPlayerEntities($payload, 758, 'pc-758-992');

    $players = array_values(array_filter($result['entities'], static function (array $entity): bool {
      return ($entity['entity_type'] ?? '') === 'player_character';
    }));
    $this->assertCount(1, $players);
    $this->assertSame('pc-758-992', $players[0]['instance_id']);
    $this->assertSame(992, $players[0]['state']['metadata']['campaign_character_id']);
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesMatchesByNameWhenContentIdDiffers(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $service = new class($database, $follower_subsystem) extends CampaignCharacterRuntimeSyncService {
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
            ['q' => 0, 'r' => 0, 'h3_index_res14' => '842a10000000003'],
            ['q' => 1, 'r' => 0, 'h3_index_res14' => '842a10000000004'],
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

    $this->assertCount(1, $result['entities']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['instance_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['entity_instance_id']);
    $this->assertSame('scholar_npc', $result['entities'][0]['entity_ref']['content_id']);
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['character_id']);
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['campaign_character_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['state']['metadata']['runtime_entity_id']);
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesKeepsMatchedNpcOnItsCurrentHexWhenValid(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $service = new class($database, $follower_subsystem) extends CampaignCharacterRuntimeSyncService {
      public function syncNpcEntities(array $payload, int $campaign_id, string $active_room_id): array {
        return $this->syncActiveRoomNpcEntities($payload, $campaign_id, $active_room_id);
      }
    };

    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 640,
        'instance_id' => 'npc_skeleton_guard_alpha',
        'name' => 'Skeleton Guard Alpha',
        'state_data' => json_encode([
          'content_id' => 'skeleton_guard_alpha',
          'role' => 'guard',
          'team' => 'hostile',
        ]),
        'position_q' => 1,
        'position_r' => 0,
        'location_ref' => 'undead_crypt_entry',
      ],
    ]);
    $room_statement = $this->createMock(StatementInterface::class);
    $room_statement->method('fetchField')->willReturn('undead_crypt_entry');
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
      'active_room_id' => 'undead_crypt_entry',
      'rooms' => [
        'undead_crypt_entry' => [
          'hexes' => [
            ['q' => 1, 'r' => 0, 'h3_index_res14' => '842a10000000012'],
            ['q' => 2, 'r' => 0, 'h3_index_res14' => '842a10000000013'],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_type' => 'npc',
          'instance_id' => 'npc_skeleton_guard_alpha',
          'entity_instance_id' => 'npc_skeleton_guard_alpha',
          'entity_ref' => [
            'content_type' => 'npc',
            'content_id' => 'skeleton_guard_alpha',
          ],
          'placement' => [
            'room_id' => 'undead_crypt_entry',
            'hex' => ['q' => 1, 'r' => 0],
          ],
          'state' => [
            'metadata' => [
              'display_name' => 'Skeleton Guard Alpha',
            ],
          ],
        ],
      ],
    ];

    $result = $service->syncNpcEntities($payload, 758, 'undead_crypt_entry');
    $this->assertSame(1, (int) ($result['entities'][0]['placement']['hex']['q'] ?? 0));
    $this->assertSame(0, (int) ($result['entities'][0]['placement']['hex']['r'] ?? 0));
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesReanchorsUndeadCryptSkeletonsToCanonicalPositions(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $service = new class($database, $follower_subsystem) extends CampaignCharacterRuntimeSyncService {
      public function syncNpcEntities(array $payload, int $campaign_id, string $active_room_id): array {
        return $this->syncActiveRoomNpcEntities($payload, $campaign_id, $active_room_id);
      }
    };

    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 641,
        'instance_id' => 'npc_skeleton_guard_alpha',
        'name' => 'Skeleton Guard Alpha',
        'state_data' => json_encode([
          'content_id' => 'skeleton_guard_alpha',
          'role' => 'guard',
          'team' => 'hostile',
        ]),
        'position_q' => 0,
        'position_r' => 0,
        'location_ref' => 'undead_crypt_entry_hall',
      ],
    ]);
    $room_statement = $this->createMock(StatementInterface::class);
    $room_statement->method('fetchField')->willReturn('undead_crypt_entry_hall');
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
      'active_room_id' => 'undead_crypt_entry_hall',
      'rooms' => [
        'undead_crypt_entry_hall' => [
          'room_id' => 'undead_crypt_entry_hall',
          'source_room_id' => 'tpl_room_crypt_anteroom',
          'room_type' => 'starter_undead_crypt',
          'hexes' => [
            ['q' => 0, 'r' => 0, 'h3_index_res14' => '842a10000000020'],
            ['q' => 3, 'r' => 2, 'h3_index_res14' => '842a10000000021'],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_type' => 'npc',
          'instance_id' => 'npc_skeleton_guard_alpha',
          'entity_instance_id' => 'npc_skeleton_guard_alpha',
          'entity_ref' => [
            'content_type' => 'npc',
            'content_id' => 'skeleton_guard_alpha',
          ],
          'placement' => [
            'room_id' => 'undead_crypt_entry_hall',
            'hex' => ['q' => 0, 'r' => 0],
          ],
          'state' => [
            'metadata' => [
              'display_name' => 'Skeleton Guard Alpha',
            ],
          ],
        ],
      ],
    ];

    $result = $service->syncNpcEntities($payload, 758, 'undead_crypt_entry_hall');
    $this->assertSame(3, (int) ($result['entities'][0]['placement']['hex']['q'] ?? 0));
    $this->assertSame(2, (int) ($result['entities'][0]['placement']['hex']['r'] ?? 0));
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesEnrichesExistingEntityInPlace(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $service = new class($database, $follower_subsystem) extends CampaignCharacterRuntimeSyncService {
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
    $empty_statement = $this->createMock(StatementInterface::class);
    $empty_statement->method('fetchField')->willReturn(FALSE);
    $link_statement = $this->createMock(StatementInterface::class);
    $link_statement->method('fetchField')->willReturn(FALSE);
    $library_select = $this->createSelectMock($empty_statement);
    $campaign_portrait_select = $this->createSelectMock($link_statement);
    $existing_library_link_select = $this->createSelectMock($empty_statement);
    $select_calls = ['dc_generated_image_links' => 0];
    $database->method('select')->willReturnCallback(function (string $table, string $alias) use ($npc_select, $room_select, $library_select, $campaign_portrait_select, $existing_library_link_select, &$select_calls) {
      if ($table === 'dc_campaign_characters' && $alias === 'cc') {
        return $npc_select;
      }
      if ($table === 'dc_campaign_characters' && $alias === 'lib') {
        return $library_select;
      }
      if ($table === 'dungeoncrawler_content_characters' && $alias === 'c') {
        return $library_select;
      }
      if ($table === 'dc_campaign_rooms' && $alias === 'r') {
        return $room_select;
      }
      if ($table === 'dc_generated_image_links' && $alias === 'l') {
        $select_calls['dc_generated_image_links']++;
        return $select_calls['dc_generated_image_links'] === 1 ? $campaign_portrait_select : $existing_library_link_select;
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
    $database->method('insert')->willReturnCallback(function (string $table) {
      return new class($table) {
        public function __construct(private readonly string $table) {}
        public function fields(array $fields): self {
          return $this;
        }
        public function execute(): int {
          return $this->table === 'dc_campaign_characters' ? 777 : 1;
        }
      };
    });

    $payload = [
      'active_room_id' => 'room-tavern',
      'rooms' => [
        'room-tavern' => [
          'hexes' => [
            ['q' => 0, 'r' => 0, 'h3_index_res14' => '842a10000000005'],
            ['q' => 1, 'r' => 0, 'h3_index_res14' => '842a10000000006'],
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
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['character_id']);
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['campaign_character_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['state']['metadata']['runtime_entity_id']);
    $this->assertSame('quest_giver', $result['entities'][0]['state']['metadata']['role']);
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesCanonicalizesPrefixedContentIdWithoutDuplication(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $service = new class($database, $follower_subsystem) extends CampaignCharacterRuntimeSyncService {
      public function syncNpcEntities(array $payload, int $campaign_id, string $active_room_id): array {
        return $this->syncActiveRoomNpcEntities($payload, $campaign_id, $active_room_id);
      }
    };

    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 398,
        'instance_id' => 'npc_tavern_keeper',
        'name' => 'Eldric',
        'state_data' => json_encode([
          'content_id' => 'npc_tavern_keeper',
          'role' => 'contact',
          'team' => 'neutral',
        ]),
        'position_q' => 2,
        'position_r' => 1,
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

    $update = new class() {
      public array $fields = [];
      public function fields(array $fields): self {
        $this->fields = $fields;
        return $this;
      }
      public function condition(string $field, mixed $value, ?string $operator = NULL): self {
        return $this;
      }
      public function execute(): int {
        return 1;
      }
    };
    $database->method('update')->with('dc_campaign_characters')->willReturn($update);

    $payload = [
      'active_room_id' => 'room-tavern',
      'rooms' => [
        'room-tavern' => [
          'hexes' => [
            ['q' => 2, 'r' => 1, 'h3_index_res14' => '842a10000000007'],
            ['q' => 1, 'r' => 1, 'h3_index_res14' => '842a10000000008'],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_type' => 'npc',
          'instance_id' => 'npc-tavern_keeper',
          'entity_instance_id' => 'npc-tavern_keeper',
          'entity_ref' => [
            'content_type' => 'npc',
            'content_id' => 'tavern_keeper',
          ],
          'placement' => [
            'room_id' => 'room-tavern',
            'hex' => ['q' => 2, 'r' => 1],
          ],
          'state' => [
            'metadata' => [
              'display_name' => 'Eldric',
              'name' => 'Eldric',
            ],
          ],
        ],
      ],
    ];

    $result = $service->syncNpcEntities($payload, 295, 'room-tavern');

    $this->assertCount(1, $result['entities']);
    $this->assertSame('npc_tavern_keeper', $result['entities'][0]['instance_id']);
    $this->assertSame('tavern_keeper', $result['entities'][0]['entity_ref']['content_id']);
    $this->assertSame(398, $result['entities'][0]['state']['metadata']['campaign_character_id']);
    $this->assertArrayHasKey('state_data', $update->fields);
    $state_data = json_decode((string) $update->fields['state_data'], TRUE);
    $this->assertSame('tavern_keeper', $state_data['content_id'] ?? NULL);
  }

  /**
   * @covers ::syncActiveRoomPlayerEntities
   */
  public function testSyncActiveRoomPlayerEntitiesStillSyncsNpcsWithoutActivePlayers(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $service = new CampaignCharacterRuntimeSyncService($database, $follower_subsystem);

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
            ['q' => 0, 'r' => 0, 'h3_index_res14' => '842a10000000009'],
            ['q' => 1, 'r' => 0, 'h3_index_res14' => '842a1000000000a'],
          ],
        ],
      ],
      'entities' => [],
    ];

    $result = $service->syncActiveRoomPlayerEntities($payload, 69, 'pc-69-198');

    $this->assertCount(1, $result['entities']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['instance_id']);
    $this->assertSame('scholar_npc', $result['entities'][0]['entity_ref']['content_id']);
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['character_id']);
    $this->assertSame(259, $result['entities'][0]['state']['metadata']['campaign_character_id']);
    $this->assertSame('npc_scholar_npc', $result['entities'][0]['state']['metadata']['runtime_entity_id']);
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesSeedsLibraryAndPortraitGeneration(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $npc_sheet_generation = $this->createMock(NpcSheetGenerationService::class);
    $portrait_generator = $this->createMock(CharacterPortraitGenerationService::class);
    $service = new class($database, $follower_subsystem, $npc_sheet_generation, $portrait_generator) extends CampaignCharacterRuntimeSyncService {
      public function syncNpcEntities(array $payload, int $campaign_id, string $active_room_id): array {
        return $this->syncActiveRoomNpcEntities($payload, $campaign_id, $active_room_id);
      }
    };

    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 329,
        'instance_id' => 'npc_bousterous',
        'name' => 'Bousterous',
        'state_data' => json_encode([
          'content_id' => 'bousterous',
          'role' => 'merchant',
          'description' => 'Inventor merchant',
          'team' => 'neutral',
          'metadata' => [
            'display_name' => 'Bousterous',
            'occupation' => 'Inventor merchant',
          ],
        ]),
        'character_data' => json_encode([
          'name' => 'Bousterous',
          'basicInfo' => [
            'ancestry' => 'elf',
            'class' => 'inventor',
          ],
          'profile' => [
            'appearance' => 'Sharp-featured elf merchant',
          ],
        ]),
        'uid' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'location_ref' => 'market_building',
      ],
    ]);
    $room_statement = $this->createMock(StatementInterface::class);
    $room_statement->method('fetchField')->willReturn('market_building');

    $npc_select = $this->createSelectMock($npc_statement);
    $room_select = $this->createSelectMock($room_statement);
    $empty_statement = $this->createMock(StatementInterface::class);
    $empty_statement->method('fetchField')->willReturn(FALSE);
    $link_statement = $this->createMock(StatementInterface::class);
    $link_statement->method('fetchField')->willReturn(FALSE);
    $library_select = $this->createSelectMock($empty_statement);
    $campaign_portrait_select = $this->createSelectMock($link_statement);
    $existing_library_link_select = $this->createSelectMock($empty_statement);
    $generated_image_link_calls = 0;
    $database->method('select')->willReturnCallback(function (string $table, string $alias) use ($npc_select, $room_select, $library_select, $campaign_portrait_select, $existing_library_link_select, &$generated_image_link_calls) {
      if ($table === 'dc_campaign_characters' && $alias === 'cc') {
        return $npc_select;
      }
      if ($table === 'dc_campaign_characters' && $alias === 'lib') {
        return $library_select;
      }
      if ($table === 'dungeoncrawler_content_characters' && $alias === 'c') {
        return $library_select;
      }
      if ($table === 'dc_campaign_rooms' && $alias === 'r') {
        return $room_select;
      }
      if ($table === 'dc_generated_image_links' && $alias === 'l') {
        $generated_image_link_calls++;
        return $generated_image_link_calls === 1 ? $campaign_portrait_select : $existing_library_link_select;
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
    $database->method('insert')->willReturnCallback(function (string $table) {
      return new class($table) {
        public function __construct(private readonly string $table) {}
        public function fields(array $fields): self {
          return $this;
        }
        public function execute(): int {
          return $this->table === 'dc_campaign_characters' ? 777 : 1;
        }
      };
    });

    $npc_sheet_generation->expects($this->once())
      ->method('enqueueNpcSheetGeneration')
      ->with(85, 'bousterous', $this->callback(static function (array $seed): bool {
        return ($seed['name'] ?? '') === 'Bousterous'
          && ($seed['role'] ?? '') === 'merchant';
      }));

    $portrait_generator->expects($this->once())
      ->method('generatePortrait')
      ->with(
        $this->callback(static function (array $payload): bool {
          return ($payload['name'] ?? '') === 'Bousterous'
            && !empty($payload['portrait_generate']);
        }),
        329,
        0,
        85,
        ['generate' => TRUE]
      );

    $payload = [
      'active_room_id' => 'market_building',
      'rooms' => [
        'market_building' => [
          'hexes' => [
            ['q' => 0, 'r' => 0, 'h3_index_res14' => '842a1000000000b'],
          ],
        ],
      ],
      'entities' => [],
    ];

    $result = $service->syncNpcEntities($payload, 85, 'market_building');

    $this->assertCount(1, $result['entities']);
    $this->assertSame(329, $result['entities'][0]['state']['metadata']['character_id']);
    $this->assertSame('bousterous', $result['entities'][0]['entity_ref']['content_id']);
  }

  /**
   * @covers ::syncActiveRoomNpcEntities
   */
  public function testSyncActiveRoomNpcEntitiesEnrichesFamiliarPortraitPayloadWithSpeciesData(): void {
    $database = $this->createMock(Connection::class);
    $follower_subsystem = $this->createMock(FollowerSubsystemService::class);
    $follower_subsystem->method('resolveRuntimeFollowerProfiles')->willReturn([]);
    $npc_sheet_generation = $this->createMock(NpcSheetGenerationService::class);
    $portrait_generator = $this->createMock(CharacterPortraitGenerationService::class);
    $service = new class($database, $follower_subsystem, $npc_sheet_generation, $portrait_generator) extends CampaignCharacterRuntimeSyncService {
      public function syncNpcEntities(array $payload, int $campaign_id, string $active_room_id): array {
        return $this->syncActiveRoomNpcEntities($payload, $campaign_id, $active_room_id);
      }
    };

    $npc_statement = $this->createMock(StatementInterface::class);
    $npc_statement->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
      [
        'id' => 931,
        'instance_id' => 'npc_familiar_weasel',
        'name' => 'Mimi',
        'state_data' => json_encode([
          'content_id' => 'familiar_weasel',
          'role' => 'familiar',
          'description' => '',
          'team' => 'ally',
          'metadata' => [
            'follower_kind' => 'familiar',
            'familiar_type' => 'weasel',
          ],
        ]),
        'character_data' => json_encode([
          'name' => 'Mimi',
          'type' => 'npc',
          'role' => 'familiar',
          'stats' => [
            'maxHp' => 5,
            'currentHp' => 5,
            'speed' => 25,
          ],
        ]),
        'uid' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'location_ref' => 'room-entry',
      ],
    ]);
    $room_statement = $this->createMock(StatementInterface::class);
    $room_statement->method('fetchField')->willReturn('room-entry');

    $npc_select = $this->createSelectMock($npc_statement);
    $room_select = $this->createSelectMock($room_statement);
    $empty_statement = $this->createMock(StatementInterface::class);
    $empty_statement->method('fetchField')->willReturn(FALSE);
    $link_statement = $this->createMock(StatementInterface::class);
    $link_statement->method('fetchField')->willReturn(FALSE);
    $library_select = $this->createSelectMock($empty_statement);
    $campaign_portrait_select = $this->createSelectMock($link_statement);
    $existing_library_link_select = $this->createSelectMock($empty_statement);
    $generated_image_link_calls = 0;
    $database->method('select')->willReturnCallback(function (string $table, string $alias) use ($npc_select, $room_select, $library_select, $campaign_portrait_select, $existing_library_link_select, &$generated_image_link_calls) {
      if ($table === 'dc_campaign_characters' && $alias === 'cc') {
        return $npc_select;
      }
      if ($table === 'dc_campaign_characters' && $alias === 'lib') {
        return $library_select;
      }
      if ($table === 'dungeoncrawler_content_characters' && $alias === 'c') {
        return $library_select;
      }
      if ($table === 'dc_campaign_rooms' && $alias === 'r') {
        return $room_select;
      }
      if ($table === 'dc_generated_image_links' && $alias === 'l') {
        $generated_image_link_calls++;
        return $generated_image_link_calls === 1 ? $campaign_portrait_select : $existing_library_link_select;
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
    $database->method('insert')->willReturnCallback(function (string $table) {
      return new class($table) {
        public function __construct(private readonly string $table) {}
        public function fields(array $fields): self {
          return $this;
        }
        public function execute(): int {
          return $this->table === 'dc_campaign_characters' ? 802 : 1;
        }
      };
    });

    $npc_sheet_generation->expects($this->once())
      ->method('enqueueNpcSheetGeneration')
      ->with(294, 'familiar_weasel', $this->callback(static function (array $seed): bool {
        return ($seed['role'] ?? '') === 'familiar'
          && ($seed['familiar_type'] ?? '') === 'weasel'
          && ($seed['familiar_species_name'] ?? '') === 'Weasel';
      }));

    $portrait_generator->expects($this->once())
      ->method('generatePortrait')
      ->with(
        $this->callback(static function (array $payload): bool {
          return ($payload['name'] ?? '') === 'Mimi'
            && ($payload['role'] ?? '') === 'familiar'
            && ($payload['class'] ?? '') === 'familiar'
            && ($payload['ancestry'] ?? '') === 'Weasel'
            && ($payload['familiar_type'] ?? '') === 'weasel'
            && ($payload['familiar_species_name'] ?? '') === 'Weasel'
            && str_contains((string) ($payload['description'] ?? ''), 'weasel familiar ally')
            && !empty($payload['portrait_generate']);
        }),
        931,
        0,
        294,
        ['generate' => TRUE]
      );

    $payload = [
      'active_room_id' => 'room-entry',
      'rooms' => [
        'room-entry' => [
          'hexes' => [
            ['q' => 0, 'r' => 0, 'h3_index_res14' => '842a1000000000c'],
          ],
        ],
      ],
      'entities' => [],
    ];

    $service->syncNpcEntities($payload, 294, 'room-entry');
  }

  /**
   * Build a generic fluent select mock.
   */
  protected function createSelectMock(StatementInterface $statement): Select {
    $select = $this->createMock(Select::class);
    $or_condition = new class() {
      public function condition(string $field, mixed $value, ?string $operator = NULL): self {
        return $this;
      }
      public function isNull(string $field): self {
        return $this;
      }
    };
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orConditionGroup')->willReturn($or_condition);
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    return $select;
  }

}
