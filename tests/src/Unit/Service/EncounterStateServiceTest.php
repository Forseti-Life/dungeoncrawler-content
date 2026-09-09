<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\CampaignLifecycleService;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\EncounterStateService;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 encounter current-state authority.
 *
 * @group dungeoncrawler_content
 * @group encounter_state
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EncounterStateService
 */
class EncounterStateServiceTest extends TestCase {

  /**
   * An encounter that is outside combat (idle/no active encounter for context).
   */
  public function testActiveEncounterStateForContextReturnsIdleWhenNoEncounter(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->never())->method('loadEncounter');

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->expects($this->once())->method('assertLaunchable')->with(986);

    $service = new EncounterStateService($store, $this->emptyDatabase(), $lifecycle);
    $data = $service->getActiveEncounterStateForContext(986, 'room-1');

    $this->assertNull($data['encounter_id']);
    $this->assertSame('idle', $data['status']);
    $this->assertSame('encounter-map-v1', $data['encounter_presentation']['schema_version']);
    $this->assertSame([], $data['encounter_presentation']['initiative_order']);
  }

  /**
   * Active encounter: participants, turn/round, and map projection assembled.
   */
  public function testGetStateAssemblesActiveEncounter(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(99001)->willReturn($this->activeEncounterRow());

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->expects($this->once())->method('assertLaunchable')->with(986);

    $service = new EncounterStateService($store, $this->emptyDatabase(), $lifecycle);
    $state = $service->getState(99001);

    $this->assertSame(99001, $state['encounter_id']);
    $this->assertSame(986, $state['campaign_id']);
    $this->assertSame('room-1', $state['room_id']);
    $this->assertSame('map-1', $state['map_id']);
    $this->assertSame('active', $state['status']);
    $this->assertSame(3, $state['current_round']);
    $this->assertSame(1, $state['turn_index']);
    // Participants + turn/round + map projection.
    $this->assertCount(2, $state['participants']);
    $this->assertSame('goblin-1', $state['current_participant']['entity_id']);
    $presentation = $state['encounter_presentation'];
    $this->assertSame('encounter-map-v1', $presentation['schema_version']);
    $this->assertSame('map-1', $state['map_id']);
    $this->assertSame('room-1', $presentation['room_id']);
    $this->assertSame(3, $presentation['current_round']);
    $this->assertSame(1, $presentation['turn_index']);
    $this->assertSame('goblin-1', $presentation['current_entity_id']);
    // Player HP is fully visible; enemy HP is status_only.
    $cards = $presentation['initiative_order'];
    $this->assertSame('full', $cards[0]['hp']['visibility']);
    $this->assertSame('status_only', $cards[1]['hp']['visibility']);
  }

  /**
   * Completed encounter: status flows through and is not marked active.
   */
  public function testGetObjectStateForCompletedEncounter(): void {
    $row = $this->activeEncounterRow();
    $row['status'] = 'completed';

    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(99001)->willReturn($row);

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->method('assertLaunchable')->with(986);

    $service = new EncounterStateService($store, $this->emptyDatabase(), $lifecycle);
    $envelope = $service->getObjectState(ObjectRef::create('encounter', '99001', [
      'campaign_id' => 986,
    ]))->toArray();

    $this->assertSame('encounter', $envelope['object_type']);
    $this->assertSame('completed', $envelope['state']['status']);
    $this->assertFalse($envelope['projection']['in_active_encounter']);
  }

  /**
   * ObjectState envelope authority: the owner and provider are named and equal
   * to EncounterStateService; the authority source is the persistence store.
   */
  public function testGetObjectStateEnvelopeAuthority(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(99001)->willReturn($this->activeEncounterRow());

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->method('assertLaunchable')->with(986);

    $service = new EncounterStateService($store, $this->emptyDatabase(), $lifecycle);
    $envelope = $service->getObjectState(ObjectRef::create('encounter', '99001', [
      'campaign_id' => 986,
    ]))->toArray();

    $this->assertSame(1, $envelope['envelope_version']);
    $this->assertSame('encounter', $envelope['object_type']);
    $this->assertSame(EncounterStateService::class, $envelope['authority']['owner']);
    $this->assertSame(EncounterStateService::class, $envelope['authority']['provider']);
    $this->assertSame('combat_encounter_store', $envelope['authority']['source']);
    $this->assertTrue($envelope['projection']['in_active_encounter']);
  }

  /**
   * Canonical packet state: only combat.resolution_envelope.v1 is surfaced;
   * legacy top-level damage/movement fields are never used as a fallback.
   */
  public function testResolutionEnvelopeSurfacesCanonicalPacketsOnly(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(99001)->willReturn($this->activeEncounterRow());

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->method('assertLaunchable')->with(986);

    // Latest action carries the canonical envelope plus legacy sibling fields.
    $action_result = json_encode([
      'damage' => 7,
      'damage_type' => 'slashing',
      'resolution_envelope' => [
        'contract_version' => 'combat.resolution_envelope.v1',
        'kind' => 'combat_resolution_envelope',
        'packets' => [
          ['kind' => 'damage_application', 'amount' => 7],
          'not-an-array',
        ],
      ],
    ]);

    $database = $this->configurableDatabase(['actions' => [['result' => $action_result]]]);
    $service = new EncounterStateService($store, $database, $lifecycle);
    $state = $service->getState(99001);

    $this->assertIsArray($state['resolution_envelope']);
    $this->assertSame('combat.resolution_envelope.v1', $state['resolution_envelope']['contract_version']);
    // Only the array packet survives; legacy sibling damage fields are ignored.
    $this->assertCount(1, $state['resolution_envelope']['packets']);
    $this->assertSame('damage_application', $state['resolution_envelope']['packets'][0]['kind']);
    $this->assertArrayNotHasKey('damage', $state['resolution_envelope']);
  }

  /**
   * A non-canonical (legacy) action envelope is not surfaced: no fallback.
   */
  public function testResolutionEnvelopeIgnoresNonCanonicalContract(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(99001)->willReturn($this->activeEncounterRow());

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->method('assertLaunchable')->with(986);

    $action_result = json_encode([
      'damage' => 5,
      'resolution_envelope' => ['contract_version' => 'combat.legacy.v0', 'packets' => [['x' => 1]]],
    ]);
    $database = $this->configurableDatabase(['actions' => [['result' => $action_result]]]);
    $service = new EncounterStateService($store, $database, $lifecycle);
    $state = $service->getState(99001);

    $this->assertNull($state['resolution_envelope']);
  }

  /**
   * Archived campaign hard-fails at the encounter read boundary.
   */
  public function testArchivedCampaignRejectsEncounterRead(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(310001)->willReturn([
      'id' => 310001,
      'campaign_id' => 310,
      'status' => 'active',
      'participants' => [],
    ]);

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->method('assertLaunchable')
      ->with(310)
      ->willThrowException(new LegacyCampaignArchivedException(
        'legacy_campaign_archived: campaign 310 is archived and cannot be launched or read by the current runtime.'
      ));

    $service = new EncounterStateService($store, $this->emptyDatabase(), $lifecycle);
    $this->expectException(LegacyCampaignArchivedException::class);
    $service->getState(310001);
  }

  /**
   * Missing encounter hard-fails.
   */
  public function testGetStateRejectsMissingEncounter(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(99001)->willReturn(NULL);

    $service = new EncounterStateService(
      $store,
      $this->emptyDatabase(),
      $this->createMock(CampaignLifecycleService::class)
    );
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Encounter not found: 99001');
    $service->getState(99001);
  }

  /**
   * Malformed (non-positive) encounter id hard-fails.
   */
  public function testGetStateRejectsNonPositiveId(): void {
    $service = new EncounterStateService(
      $this->createMock(CombatEncounterStore::class),
      $this->emptyDatabase(),
      $this->createMock(CampaignLifecycleService::class)
    );
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Encounter id must be positive.');
    $service->getState(0);
  }

  /**
   * The runtime-snapshot projection lane produces the encounter-map-v1 shape
   * that the delivery snapshot consumes (owner produces the projection).
   */
  public function testBuildPresentationFromRuntimeGameState(): void {
    $service = new EncounterStateService(
      $this->createMock(CombatEncounterStore::class),
      $this->emptyDatabase(),
      $this->createMock(CampaignLifecycleService::class)
    );

    $presentation = $service->buildPresentationFromRuntimeGameState([
      'encounter_id' => 99001,
      'active_room_id' => 'room-9',
      'round' => 2,
      'turn' => ['index' => 0, 'entity' => 'pc-1'],
      'initiative_order' => [
        ['entity_id' => 'pc-1', 'name' => 'Hero', 'team' => 'player', 'initiative' => 18, 'hp' => 20, 'max_hp' => 22],
        ['entity_id' => 'orc-1', 'name' => 'Orc', 'team' => 'enemy', 'initiative' => 9, 'hp' => 12, 'max_hp' => 15],
      ],
    ]);

    $this->assertSame('encounter-map-v1', $presentation['schema_version']);
    $this->assertSame(99001, $presentation['encounter_id']);
    $this->assertSame('room-9', $presentation['room_id']);
    $this->assertSame(2, $presentation['current_round']);
    $this->assertSame('pc-1', $presentation['current_entity_id']);
    $this->assertTrue($presentation['initiative_order'][0]['is_current']);
    $this->assertSame('full', $presentation['initiative_order'][0]['hp']['visibility']);
    $this->assertSame('status_only', $presentation['initiative_order'][1]['hp']['visibility']);
  }

  /**
   * Current-turn read routes through the owner.
   */
  public function testGetCurrentTurn(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->method('loadEncounter')->with(99001)->willReturn($this->activeEncounterRow());

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    $lifecycle->method('assertLaunchable')->with(986);

    $service = new EncounterStateService($store, $this->emptyDatabase(), $lifecycle);
    $turn = $service->getCurrentTurn(99001);

    $this->assertSame(2002, $turn['participant_id']);
    $this->assertSame('Goblin', $turn['name']);
    $this->assertSame(1, $turn['turn_index']);
    $this->assertSame(3, $turn['current_round']);
  }

  /**
   * A canonical active encounter row with two participants.
   *
   * @return array<string,mixed>
   */
  private function activeEncounterRow(): array {
    return [
      'id' => 99001,
      'encounter_id' => 99001,
      'campaign_id' => 986,
      'room_id' => 'room-1',
      'map_id' => 'map-1',
      'status' => 'active',
      'current_round' => 3,
      'turn_index' => 1,
      'updated' => 1757400000,
      'participants' => [
        [
          'id' => 2001,
          'entity_id' => 'pc-1',
          'entity_ref' => 'pc-1',
          'name' => 'Hero',
          'team' => 'player',
          'initiative' => 18,
          'hp' => 20,
          'max_hp' => 22,
          'actions_remaining' => 3,
          'reaction_available' => 1,
          'is_defeated' => 0,
        ],
        [
          'id' => 2002,
          'entity_id' => 'goblin-1',
          'entity_ref' => 'goblin-1',
          'name' => 'Goblin',
          'team' => 'enemy',
          'initiative' => 9,
          'hp' => 6,
          'max_hp' => 8,
          'actions_remaining' => 3,
          'reaction_available' => 1,
          'is_defeated' => 0,
        ],
      ],
    ];
  }

  /**
   * A database whose every query resolves to empty results.
   */
  private function emptyDatabase(): Connection {
    return $this->configurableDatabase([]);
  }

  /**
   * Build a permissive Connection mock returning configured combat rows.
   *
   * @param array{conditions?:array,ai_turn_plan?:array,actions?:array,active_ids?:array} $config
   */
  private function configurableDatabase(array $config): Connection {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAllAssoc')->willReturn($config['conditions'] ?? []);
    $statement->method('fetchAssoc')->willReturn($config['ai_turn_plan'] ?? FALSE);
    $statement->method('fetchAll')->willReturn($config['actions'] ?? []);
    $statement->method('fetchCol')->willReturn($config['active_ids'] ?? []);

    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);

    return $database;
  }

}
