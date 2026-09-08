<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\CampaignClockService;
use Drupal\dungeoncrawler_content\Service\CampaignNameGeneratorService;
use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\RoomViewImageService;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\StorylineQuestLifecycleService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests starter tavern seed alignment for campaign initialization.
 *
 * @group dungeoncrawler_content
 * @group campaign_init
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignInitializationService
 */
class CampaignInitializationServiceTest extends UnitTestCase {

  /**
   * @covers ::loadStarterRoomSeed
   */
  public function testLoadStarterRoomSeedUsesCanonicalGildedTankardMetadata(): void {
    $select = $this->createMock(SelectInterface::class);
    $condition_group = $this->createMock(ConditionInterface::class);
    $result = new class() {
      public function fetchAssoc(): array {
        return [
          'room_id' => 'tavern_entrance',
          'source_room_id' => 'tavern_entrance',
          'name' => 'The Gilded Tankard',
          'description' => 'Eldric keeps watch while Marta the Scholar studies nearby.',
          'environment_tags' => json_encode(['indoor', 'tavern']),
          'layout_data' => json_encode(['shape' => 'seed']),
          'contents_data' => json_encode([
            'items' => [
              ['item_id' => 'wine-1'],
            ],
            'npcs' => [],
          ]),
        ];
      }
    };

    $condition_group->method('condition')->willReturnSelf();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('orConditionGroup')->willReturn($condition_group);
    $select->method('execute')->willReturn($result);

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);

    $module_list = $this->createMock(ModuleExtensionList::class);
    $module_list->method('getPath')->with('dungeoncrawler_content')
      ->willReturn('/path/that/does/not/exist');

    $service = new CampaignInitializationService(
      $database,
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      $this->buildLoggerFactory(),
      $module_list,
      $this->createMock(QuestGeneratorService::class),
      $this->createMock(CampaignNameGeneratorService::class),
      $this->createMock(CampaignClockService::class),
      $this->createMock(StorylineQuestLifecycleService::class),
      $this->createMock(ChatSessionManager::class),
      $this->createMock(NpcSheetGenerationService::class),
      $this->createMock(RoomViewImageService::class),
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      $this->createMock(ConfigFactoryInterface::class),
    );

    $method = new \ReflectionMethod(CampaignInitializationService::class, 'loadStarterRoomSeed');
    $method->setAccessible(TRUE);
    $room = $method->invoke($service, [
      'source_room_id' => 'tavern_entrance',
      'runtime_room_id' => 'tavern_entrance',
      'room_tags_default' => ['indoor', 'tavern'],
      'theme' => 'classic_dungeon',
    ]);

    $this->assertIsArray($room);
    $this->assertSame('tavern_entrance', $room['room_id']);
    $this->assertSame('tavern_entrance', $room['runtime_room_id']);
    $this->assertSame('The Gilded Tankard', $room['name']);
    $this->assertStringContainsString('Eldric', $room['description']);
    $this->assertStringContainsString('Marta the Scholar', $room['description']);
    $this->assertIsArray($room['contents_data'] ?? NULL);
    $this->assertNotEmpty($room['contents_data']['items'] ?? []);
  }

  /**
   * @covers ::buildStarterRoomIntroMessage
   */
  public function testBuildStarterRoomIntroMessageUsesRoomDescriptionWhenProvided(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildStarterRoomIntroMessage');
    $method->setAccessible(TRUE);

    $message = $method->invoke(
      $service,
      'The Gilded Tankard',
      'Warm light and low voices fill the tavern.'
    );

    $this->assertStringStartsWith('The Gilded Tankard', $message);
    $this->assertStringContainsString('Warm light and low voices fill the tavern.', $message);
  }

  /**
   * @covers ::buildStarterRoomIntroMessage
   */
  public function testBuildStarterRoomIntroMessageFallsBackToRoomArrivalTextWhenDescriptionMissing(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildStarterRoomIntroMessage');
    $method->setAccessible(TRUE);

    $message = $method->invoke($service, 'The Gilded Tankard', '');

    $this->assertStringContainsString('You arrive at The Gilded Tankard. The adventure begins...', $message);
  }

  /**
   * @dataProvider invalidCampaignSourceProvider
   * @covers ::normalizeCampaignSource
   */
  public function testNormalizeCampaignSourceHardFailsInvalidContracts(?array $source): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'normalizeCampaignSource');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_source_invalid');
    $method->invoke($service, $source, 'classic_dungeon');
  }

  /**
   * Invalid source fixtures.
   */
  public static function invalidCampaignSourceProvider(): array {
    return [
      'unknown kind' => [['kind' => 'legacy']],
      'missing dungeon id' => [['kind' => 'published_dungeon', 'version_id' => '11111111-1111-4111-8111-111111111111']],
      'missing version id' => [['kind' => 'published_dungeon', 'dungeon_id' => 'd2_fixture']],
      'blank theme' => [['kind' => 'theme', 'theme' => '']],
    ];
  }

  /**
   * @covers ::buildPublishedDungeonCampaignProfile
   */
  public function testPublishedDungeonProfilePinsFallbackLiteralsAndEntrancePlacement(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignProfile');
    $method->setAccessible(TRUE);

    $profile = $method->invoke($service, [
      'source' => [
        'dungeon_id' => 'd2_fixture',
        'version_id' => '11111111-1111-4111-8111-111111111111',
      ],
      'version_row' => ['version' => '1.0.0'],
      'aggregate' => [
        'name' => 'D-2 Fixture',
        'description' => 'Fixture dungeon.',
        'metadata' => ['tags' => ['d2-fixture']],
      ],
      'entrance_placement' => [
        'placement_id' => '22222222-2222-4222-8222-222222222222',
        'room_id' => 'd2_fixture_room_a',
        'tags' => ['entrance'],
      ],
    ], 'runtime-dungeon-id');

    $this->assertSame('published-dungeon-v1', $profile['content_profile_id']);
    $this->assertSame('22222222-2222-4222-8222-222222222222', $profile['launch_policy']['default_active_room_id']);
    $this->assertSame('runtime-dungeon-id', $profile['launch_policy']['default_active_dungeon_id']);
    $this->assertSame('campaign_profile', $profile['narrative_hub_policy']['fallback_mode']);
    $this->assertSame('campaign_profile_only', $profile['launch_policy']['runtime_fallback_mode']);
    $this->assertNull($profile['narrative_hub_policy']['primary_contact_actor_id']);
  }

  /**
   * @covers ::buildPublishedCampaignRoomLayout
   * @covers ::buildPublishedCampaignRoomContents
   * @covers ::assertPublishedCampaignRoomPersistencePayload
   */
  public function testPublishedDungeonRoomInstantiationPreservesMetadataInsideLayoutData(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $layout_method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedCampaignRoomLayout');
    $contents_method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedCampaignRoomContents');
    $assert_method = new \ReflectionMethod(CampaignInitializationService::class, 'assertPublishedCampaignRoomPersistencePayload');
    $layout_method->setAccessible(TRUE);
    $contents_method->setAccessible(TRUE);
    $assert_method->setAccessible(TRUE);
    $room = $this->publishedRoomFixture();
    $placement = [
      'placement_id' => '22222222-2222-4222-8222-222222222222',
      'room_id' => 'd2_fixture_room_a',
      'version_id' => '11111111-1111-4111-8111-111111111111',
    ];
    $version_row = ['version' => '1.0.0'];

    $layout = $layout_method->invoke($service, $room, $placement, $version_row);
    $contents = $contents_method->invoke($service, $room, $placement, $version_row);
    $decoded_layout = json_decode(json_encode($layout, JSON_THROW_ON_ERROR), TRUE, 512, JSON_THROW_ON_ERROR);
    $decoded_contents = json_decode(json_encode($contents, JSON_THROW_ON_ERROR), TRUE, 512, JSON_THROW_ON_ERROR);
    $assert_method->invoke($service, $placement, $room, $decoded_layout, $decoded_contents);

    $this->assertSame('D-2 Room A', $decoded_layout['metadata']['module_source']['licence_notice']);
    $this->assertSame('d2_fixture_room_a', $decoded_layout['metadata']['campaign_source']['source_room_id']);
    $this->assertSame('11111111-1111-4111-8111-111111111111', $decoded_contents['_source']['room_version_id']);
  }

  /**
   * @covers ::buildPublishedCampaignSparseH3Room
   */
  public function testPublishedDungeonSparseH3MappingsUseTransformedLevelSpace(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedCampaignSparseH3Room');
    $method->setAccessible(TRUE);

    $room = $this->publishedRoomFixture();
    $room['hexes'] = [
      ['q' => 0, 'r' => 0, 'h3_index_res14' => '8f0000000000001'],
      ['q' => 1, 'r' => 0, 'h3_index_res14' => '8f0000000000002'],
    ];
    $room['entry_ports'][0]['hex'] = ['q' => 0, 'r' => 0];
    $placement = [
      'placement_id' => '22222222-2222-4222-8222-222222222222',
      'room_id' => 'd2_fixture_room_a',
      'version_id' => '11111111-1111-4111-8111-111111111111',
      'origin' => ['q' => 5, 'r' => -2],
      'rotation_steps' => 0,
    ];

    $sparse_room = $method->invoke($service, $placement, $room);

    $this->assertSame('22222222-2222-4222-8222-222222222222', $sparse_room['room_id']);
    $this->assertSame(['q' => 5, 'r' => -2, 'h3_index_res14' => '8f0000000000001', 'lat' => NULL, 'lng' => NULL], $sparse_room['anchor']);
    $this->assertSame(5, $sparse_room['hexes'][0]['q']);
    $this->assertSame(-2, $sparse_room['hexes'][0]['r']);
    $this->assertSame(6, $sparse_room['hexes'][1]['q']);
    $this->assertSame(-2, $sparse_room['hexes'][1]['r']);
  }

  /**
   * @covers ::buildPublishedDungeonCampaignConnectorPayload
   * @covers ::resolveConnectorEndpointPlacement
   */
  public function testPublishedDungeonConnectorRemapsByTransformedFootprintAndPassesH3(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignConnectorPayload');
    $method->setAccessible(TRUE);

    $payload = $method->invoke($service, 'runtime-dungeon-id', [
      'connection_id' => '33333333-3333-4333-8333-333333333333',
      'from_room_id' => 'd2_fixture_room_a',
      'to_room_id' => 'd2_fixture_room_b',
      'from_hex_q' => 1,
      'from_hex_r' => 0,
      'to_hex_q' => 2,
      'to_hex_r' => 0,
      'from_h3_index_res14' => '8f0000000000001',
      'to_h3_index_res14' => '8f0000000000002',
      'kind' => 'door',
      'direction' => 'bidirectional',
      'default_state' => 'open',
      'requirements_data' => '[]',
    ], [
      'd2_fixture_room_a' => ['1:0' => ['22222222-2222-4222-8222-222222222222']],
      'd2_fixture_room_b' => ['2:0' => ['44444444-4444-4444-8444-444444444444']],
    ]);

    $this->assertSame('runtime-dungeon-id', $payload['dungeon_id']);
    $this->assertSame('22222222-2222-4222-8222-222222222222', $payload['from_room_id']);
    $this->assertSame('44444444-4444-4444-8444-444444444444', $payload['to_room_id']);
    $this->assertSame('8f0000000000001', $payload['from_h3_index_res14']);
    $this->assertSame('8f0000000000002', $payload['to_h3_index_res14']);
  }

  /**
   * @covers ::buildPublishedDungeonCampaignConnectorPayload
   */
  public function testPublishedDungeonConnectorHardFailsMissingH3(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignConnectorPayload');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_source_connector_h3_missing');
    $method->invoke($service, 'runtime-dungeon-id', [
      'connection_id' => '33333333-3333-4333-8333-333333333333',
      'from_room_id' => 'd2_fixture_room_a',
      'to_room_id' => 'd2_fixture_room_b',
      'from_hex_q' => 1,
      'from_hex_r' => 0,
      'to_hex_q' => 2,
      'to_hex_r' => 0,
      'from_h3_index_res14' => '',
      'to_h3_index_res14' => '8f0000000000002',
    ], []);
  }

  /**
   * @covers ::buildPublishedDungeonCampaignConnectorPayload
   */
  public function testPublishedDungeonConnectorHardFailsUnresolvableEndpoint(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignConnectorPayload');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_source_connector_unresolvable');
    $method->invoke($service, 'runtime-dungeon-id', [
      'connection_id' => '33333333-3333-4333-8333-333333333333',
      'from_room_id' => 'd2_fixture_room_a',
      'to_room_id' => 'd2_fixture_room_b',
      'from_hex_q' => 1,
      'from_hex_r' => 0,
      'to_hex_q' => 2,
      'to_hex_r' => 0,
      'from_h3_index_res14' => '8f0000000000001',
      'to_h3_index_res14' => '8f0000000000002',
    ], []);
  }

  /**
   * Published room fixture.
   */
  private function publishedRoomFixture(): array {
    return [
      'room_id' => 'd2_fixture_room_a',
      'name' => 'D-2 Room A',
      'description' => 'Fixture room.',
      'room_type' => 'chamber',
      'size_category' => 'small',
      'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'h3_index_res14' => '8f0000000000001']],
      'terrain' => ['type' => 'stone_floor'],
      'lighting' => ['level' => 'bright_light', 'light_sources' => []],
      'entry_ports' => [[
        'port_id' => 'entry_a',
        'hex' => ['q' => 0, 'r' => 0],
        'edge' => 3,
        'label' => 'Entry',
        'arrival_facing' => 0,
        'is_default' => TRUE,
        'tags' => [],
      ]],
      'exit_ports' => [[
        'port_id' => 'exit_a',
        'hex' => ['q' => 0, 'r' => 0],
        'edge' => 0,
        'label' => 'Exit',
        'kind' => 'door',
        'direction' => 'bidirectional',
        'default_state' => 'open',
        'destination_hint' => NULL,
        'linked_placement_id' => NULL,
        'requirements' => [],
        'tags' => [],
      ]],
      'placements' => [],
      'environmental_effects' => [],
      'gameplay_defaults' => ['safe_for_rest' => FALSE, 'visibility' => 'visible'],
      'metadata' => [
        'tags' => ['d2-fixture'],
        'provenance' => ['author' => 'unit'],
        'module_source' => ['licence_notice' => 'D-2 Room A'],
      ],
    ];
  }

  /**
   * Builds a logger factory mock returning a channel mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

}
