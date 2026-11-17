<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\dungeoncrawler_content\Service\CampaignRuntimeStateStore;
use Drupal\dungeoncrawler_content\Service\EncounterIntentRouter;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandlerRouteExecutionSupportTrait;
use Drupal\dungeoncrawler_content\Service\HexUtilityService;
use Drupal\dungeoncrawler_content\Service\PlayerSetupService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\RoomSceneEncounterCoordinator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('dungeoncrawler_content')]
class PlayerSetupServiceTest extends UnitTestCase {

  private function service(): PlayerSetupService {
    $factory = new KeyValueMemoryFactory();
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $editor = $this->getMockBuilder(RoomEditorService::class)
      ->disableOriginalConstructor()->onlyMethods([])->getMock();
    $service = new class(
      $this->createMock(Connection::class), $factory, $lock,
      new HexUtilityService(), $editor,
      $this->createMock(CampaignRuntimeStateStore::class),
    ) extends PlayerSetupService {
      public KeyValueMemoryFactory $storage;
      public array $actors = [
        'scout' => ['id' => 'scout', 'name' => 'Scout', 'required' => TRUE],
        'companion' => ['id' => 'companion', 'name' => 'Companion', 'required' => TRUE],
      ];

      public function dungeon(int $campaign_id): array {
        $saved = $this->storage->get('dungeoncrawler.player_setup')->get((string) $campaign_id);
        return [
          'dungeon_id' => 'playersetup-' . $campaign_id,
          'revision' => $saved['revision'] ?? 0,
          'layouts' => $saved['layouts'] ?? [],
          'rooms' => ['camping' => $this->room('camping'), 'marching' => $this->room('marching')],
          'roster' => $this->actors,
        ];
      }
    };
    $service->storage = $factory;
    return $service;
  }

  /**
   * Anyone with the party who is not hostile can be added to the setup.
   */
  public function testNonHostileNpcsInThePartysRoomCanJoinTheSetup(): void {
    $npc = static fn(string $id, string $room, string $attitude): array => [
      'id' => 1, 'instance_id' => $id, 'name' => $id, 'role' => 'clan_chieftain', 'type' => 'npc',
      'location_ref' => $room, 'state_data' => json_encode(['npc_profile' => ['attitude' => $attitude]]),
    ];
    $rows = [
      ['id' => 1, 'instance_id' => 'hero', 'name' => 'Hero', 'role' => 'player', 'type' => 'pc', 'location_ref' => 'camp', 'state_data' => '{}'],
      $npc('npc_chieftain', 'camp', 'indifferent'),
      $npc('npc_raider', 'camp', 'hostile'),
      $npc('npc_elsewhere', 'caves', 'friendly'),
    ];
    $statement = new class($rows) {

      public function __construct(private array $rows) {}

      public function fetchAll(int $mode): array {
        return $this->rows;
      }

    };
    $select = $this->createMock(\Drupal\Core\Database\Query\SelectInterface::class);
    foreach (['fields', 'condition', 'orderBy'] as $method) {
      $select->method($method)->willReturnSelf();
    }
    $select->method('execute')->willReturn($statement);
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);
    $runtime = $this->createMock(CampaignRuntimeStateStore::class);
    $runtime->method('loadGameState')->willReturn(['active_room_id' => 'camp']);
    $service = new PlayerSetupService(
      $database, new KeyValueMemoryFactory(), $this->createMock(LockBackendInterface::class), new HexUtilityService(),
      $this->getMockBuilder(RoomEditorService::class)->disableOriginalConstructor()->onlyMethods([])->getMock(), $runtime,
    );

    $roster = $service->roster(12);

    $this->assertSame(['hero', 'npc_chieftain'], array_keys($roster));
    $this->assertSame(['required' => FALSE, 'group' => 'escort'], array_intersect_key($roster['npc_chieftain'], ['required' => 1, 'group' => 1]));
  }

  private function layout(string $mode): array {
    return [
      'scout' => ['q' => 1, 'r' => 0, 'facing' => 2, 'duty' => $mode === 'camping' ? 'watch' : 'travel'],
      'companion' => ['q' => 0, 'r' => 0, 'facing' => 4, 'duty' => $mode === 'camping' ? 'sleep' : 'travel'],
    ];
  }

  public function testSeparateRoomSavesPersistAndInvalidateWhenPartyChanges(): void {
    $service = $this->service();
    $this->assertTrue($service->restRequirement(42)['requires_setup']);
    $service->save(42, 'camping', $this->layout('camping'), 0);
    $this->assertStringContainsString('Road travel', $service->restRequirement(42)['error']);
    $saved = $service->save(42, 'marching', $this->layout('marching'), 1);
    $this->assertSame($this->layout('camping'), $saved['layouts']['camping']);
    $this->assertSame(2, $saved['revision']);
    $this->assertNull($service->restRequirement(42));
    $state = $dungeon = [];
    $result = (new EncounterIntentRouter($service))->routeRestAction(
      'refocus', 'scout', NULL, [], $state, $dungeon, 42,
      static fn() => [], static fn() => ['rest_completed' => TRUE],
      static fn() => [], static fn() => [],
    );
    $this->assertTrue($result['result']['rest_completed']);
    $this->assertTrue($service->restRequirement(43)['requires_setup']);
    $service->actors['new-party-member'] = ['name' => 'New member', 'required' => TRUE];
    $this->assertStringContainsString('New member', $service->restRequirement(42)['error']);
  }

  public function testStaleWindowCannotOverwriteAnotherSave(): void {
    $service = $this->service();
    $service->save(42, 'camping', $this->layout('camping'), 0);
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('changed in another window');
    $service->save(42, 'marching', $this->layout('marching'), 0);
  }

  public function testInvalidPlacementsFailWithoutSaving(): void {
    $service = $this->service();
    $valid = $this->layout('camping');
    $invalid = [];
    $invalid['overlap'] = $valid;
    $invalid['overlap']['scout']['q'] = 0;
    $invalid['outside'] = $valid;
    $invalid['outside']['scout']['q'] = 6;
    $invalid['facing'] = $valid;
    $invalid['facing']['scout']['facing'] = 6;
    $invalid['duty'] = $valid;
    $invalid['duty']['scout']['duty'] = 'travel';
    $invalid['type'] = $valid;
    $invalid['type']['scout']['q'] = '1';
    $invalid['missing'] = ['scout' => $valid['scout']];
    $invalid['unknown'] = $valid + ['stranger' => $valid['scout']];
    foreach ($invalid as $case => $layout) {
      try {
        $service->save(42, 'camping', $layout, 0);
        $this->fail("Accepted invalid placement: $case");
      }
      catch (\InvalidArgumentException $exception) {
        $this->assertStringContainsString('Playersetup camping', $exception->getMessage());
      }
      $this->assertSame(0, $service->dungeon(42)['revision']);
    }
  }

  public function testDifferentEscortSelectionsRequireReconciliation(): void {
    $service = $this->service();
    $service->actors['escort'] = ['name' => 'Escort', 'required' => FALSE];
    $camp = $this->layout('camping') + ['escort' => ['q' => -1, 'r' => 0, 'facing' => 0, 'duty' => 'sleep']];
    $service->save(42, 'camping', $camp, 0);
    $service->save(42, 'marching', $this->layout('marching'), 1);
    $this->assertStringContainsString('same party and escort', $service->restRequirement(42)['error']);
  }

  public function testRestGatePreservesSetupMetadataAndHasNoEffects(): void {
    $router = new EncounterIntentRouter($this->service());
    foreach (['treat_wounds', 'refocus', 'repair', 'daily_preparations'] as $type) {
      $state = ['time' => 123];
      $dungeon = ['actors' => ['scout']];
      $mutations = $events = $time = [];
      $narration = NULL;
      $callback = static function (): never {
        throw new \LogicException('A blocked rest must never execute.');
      };
      $result = $router->handleRestRoute(
        $type, 'scout', NULL, [], $state, $dungeon, 42,
        $callback, $callback, $callback, $callback, $mutations, $events, $time, $narration,
      );
      $this->assertFalse($result['success']);
      $this->assertTrue($result['result']['requires_setup']);
      $this->assertSame('/campaign/42/player-setup', $result['result']['setup_url']);
      $this->assertSame(['time' => 123], $state);
      $this->assertSame(['actors' => ['scout']], $dungeon);
      $this->assertSame([], $time);
    }
  }

  public function testMarkedEncounterUsesSavedPositionsAndCanonicalPlacementCallback(): void {
    $service = $this->service();
    $service->save(42, 'camping', $this->layout('camping'), 0);
    $service->save(42, 'marching', $this->layout('marching'), 1);
    $coordinator = new RoomSceneEncounterCoordinator($service);
    $room = [
      'room_id' => 'original-road',
      'hexes' => $service->room('marching')['hexes'],
      'gameplay_state' => ['player_setup_mode' => 'marching'],
    ];
    $participants = [
      ['entity_id' => 'scout', 'position_q' => -2, 'position_r' => 0],
      ['entity_id' => 'companion', 'position_q' => -3, 'position_r' => 0],
      ['entity_id' => 'ambusher', 'position_q' => 3, 'position_r' => 0],
    ];
    $moves = $context = [];
    $arranged = $coordinator->preparePlayerSetupEncounter(42, $participants, $room, $context,
      static function (...$arguments) use (&$moves): void { $moves[] = $arguments; });
    $this->assertSame(1, $arranged[0]['position_q']);
    $this->assertSame($participants[2], $arranged[2]);
    $this->assertSame(['scout', 'companion'], $context['player_setup']['marching_order']);
    $this->assertSame(['scout', 'original-road', ['q' => 1, 'r' => 0], 2], $moves[0]);
    $this->assertCount(2, $moves);
    $room['gameplay_state'] = [];
    $this->assertSame($participants, $coordinator->preparePlayerSetupEncounter(42, $participants, $room, $context, NULL));
  }

  public function testAmbushCollisionFailsInsteadOfMovingAnActorElsewhere(): void {
    $service = $this->service();
    $service->save(42, 'camping', $this->layout('camping'), 0);
    $service->save(42, 'marching', $this->layout('marching'), 1);
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('placement conflict');
    $service->arrangeEncounter(42, 'marching', [
      ['entity_id' => 'scout'],
      ['entity_id' => 'companion'],
      ['entity_id' => 'ambusher', 'position_q' => 1, 'position_r' => 0],
    ], ['room_id' => 'road', 'hexes' => $service->room('marching')['hexes']]);
  }

  public function testMissingEncounterParticipantIsNotSilentlyDropped(): void {
    $service = $this->service();
    $service->save(42, 'camping', $this->layout('camping'), 0);
    $service->save(42, 'marching', $this->layout('marching'), 1);
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('missing configured participant');
    $service->arrangeEncounter(42, 'marching', [['entity_id' => 'scout']],
      ['room_id' => 'road', 'hexes' => $service->room('marching')['hexes']]);
  }

  public function testCanonicalPlacementUpdatesOriginalPayloadAndOrientation(): void {
    $handler = new class {
      use EncounterPhaseHandlerRouteExecutionSupportTrait {
        moveEntityToRoom as public;
      }

      protected function resolveRoomHexH3IndexRes14(array &$dungeon_data, string $room_id, array $hex): string {
        return 'original-project-fixture-h3';
      }
    };
    $dungeon = ['entities' => [['entity_instance_id' => 'scout', 'placement' => ['hex' => ['q' => 0, 'r' => 0]]]]];
    $handler->moveEntityToRoom($dungeon, 'scout', 'road', ['q' => 2, 'r' => 1], 2);
    $this->assertSame(['q' => 2, 'r' => 1], $dungeon['entities'][0]['placement']['hex']);
    $this->assertSame('se', $dungeon['entities'][0]['placement']['orientation']);
    $this->assertSame('original-project-fixture-h3', $dungeon['entities'][0]['placement']['h3_index_res14']);
  }

}
