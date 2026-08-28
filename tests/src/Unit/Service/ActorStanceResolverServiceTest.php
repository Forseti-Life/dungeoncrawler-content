<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorDispositionService;
use Drupal\dungeoncrawler_content\Service\ActorStanceResolverService;
use Drupal\dungeoncrawler_content\Service\DispositionResolverService;
use Drupal\dungeoncrawler_content\Service\StanceEventStoreService;
use Drupal\dungeoncrawler_content\Service\StanceStateStoreService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ActorStanceResolverService.
 *
 * @group dungeoncrawler_content
 * @group actor-stance
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorStanceResolverService
 */
class ActorStanceResolverServiceTest extends UnitTestCase {

  /**
   * @covers ::resolveStance
   */
  public function testRoomDirectAddressFriendlyResolvesEngageDialogue(): void {
    $actor_disposition = $this->createMock(ActorDispositionService::class);
    $actor_disposition->method('getDispositionSummary')->willReturn([
      'current_attitude' => 'friendly',
      'current_score' => 50,
      'score_source' => 'state_store',
    ]);
    $disposition_resolver = $this->createMock(DispositionResolverService::class);
    $disposition_resolver->method('resolveDispositionMap')->willReturn([]);

    $service = new ActorStanceResolverService($actor_disposition, $disposition_resolver);
    $result = $service->resolveStance(77, 'npc_gribbles', [
      'mode' => 'room',
      'direct_addressed' => TRUE,
    ]);

    $this->assertSame('actor_stance_contract_v1', $result['contract_version']);
    $this->assertSame('engage_dialogue', $result['stance']);
    $this->assertTrue($result['policy_flags']['chat_allowed']);
    $this->assertFalse($result['policy_flags']['aggressive_action_allowed']);
  }

  /**
   * @covers ::resolveStance
   */
  public function testCombatEntryWithExplicitAttackResolvesAggressiveEngage(): void {
    $actor_disposition = $this->createMock(ActorDispositionService::class);
    $actor_disposition->method('getDispositionSummary')->willReturn([
      'current_attitude' => 'hostile',
      'current_score' => -120,
      'score_source' => 'state_store',
    ]);
    $disposition_resolver = $this->createMock(DispositionResolverService::class);
    $disposition_resolver->method('resolveDispositionMap')->willReturn([
      'pc_1' => [
        'effective_disposition_score' => -220,
        'effective_disposition_label' => 'hostile',
      ],
    ]);

    $service = new ActorStanceResolverService($actor_disposition, $disposition_resolver);
    $result = $service->resolveStance(77, 'npc_skeleton_1', [
      'mode' => 'combat_entry',
      'target_entity_refs' => ['pc_1'],
      'explicit_attack_declared' => TRUE,
      'threat_level' => 'major',
    ]);

    $this->assertSame('aggressive_engage', $result['stance']);
    $this->assertSame('pc_1', $result['target_actor_ref']);
    $this->assertTrue($result['policy_flags']['combat_entry_candidate']);
    $this->assertTrue($result['policy_flags']['aggressive_action_allowed']);
  }

  /**
   * @covers ::resolveStance
   */
  public function testEncounterLowHpResolvesSelfPreserveOrFlee(): void {
    $actor_disposition = $this->createMock(ActorDispositionService::class);
    $actor_disposition->method('getDispositionSummary')->willReturn([
      'current_attitude' => 'hostile',
      'current_score' => -100,
      'score_source' => 'state_store',
    ]);
    $disposition_resolver = $this->createMock(DispositionResolverService::class);
    $disposition_resolver->method('resolveDispositionMap')->willReturn([
      'pc_1' => [
        'effective_disposition_score' => -180,
        'effective_disposition_label' => 'hostile',
      ],
    ]);

    $service = new ActorStanceResolverService($actor_disposition, $disposition_resolver);
    $result = $service->resolveStance(77, 'npc_skeleton_2', [
      'mode' => 'encounter',
      'target_entity_refs' => ['pc_1'],
      'survival' => ['hp_ratio' => 0.30],
    ]);

    $this->assertSame('self_preserve', $result['stance']);
    $this->assertFalse($result['policy_flags']['aggressive_action_allowed']);
  }

  /**
   * @covers ::resolveStance
   */
  public function testResolveStancePersistsBehavioralProjectionWhenStoresAvailable(): void {
    $actor_disposition = $this->createMock(ActorDispositionService::class);
    $actor_disposition->method('getDispositionSummary')->willReturn([
      'current_attitude' => 'friendly',
      'current_score' => 40,
      'score_source' => 'state_store',
    ]);
    $disposition_resolver = $this->createMock(DispositionResolverService::class);
    $disposition_resolver->method('resolveDispositionMap')->willReturn([]);

    $stance_state_store = $this->createMock(StanceStateStoreService::class);
    $stance_state_store->method('loadLatestState')->willReturn([
      'summary' => [
        'active_stances' => [],
      ],
    ]);
    $stance_state_store->expects($this->once())
      ->method('storeLatestState')
      ->with(
        77,
        'npc_gribbles',
        $this->callback(static fn(array $summary): bool => isset($summary['behavioral_stance']['stance']) && $summary['behavioral_stance']['stance'] === 'observe'),
        $this->anything()
      );

    $stance_event_store = $this->createMock(StanceEventStoreService::class);
    $stance_event_store->expects($this->once())
      ->method('recordStanceEvent')
      ->with(
        77,
        'npc_gribbles',
        $this->callback(static fn(array $event): bool => ($event['event_type'] ?? '') === 'behavioral_stance_resolved')
      );

    $service = new ActorStanceResolverService(
      $actor_disposition,
      $disposition_resolver,
      $stance_state_store,
      $stance_event_store
    );
    $service->resolveStance(77, 'npc_gribbles', [
      'mode' => 'room',
    ]);
  }

  /**
   * @covers ::projectStance
   */
  public function testProjectStanceDoesNotPersistBehavioralProjection(): void {
    $actor_disposition = $this->createMock(ActorDispositionService::class);
    $actor_disposition->method('getDispositionSummary')->willReturn([
      'current_attitude' => 'hostile',
      'current_score' => -100,
      'score_source' => 'state_store',
    ]);
    $disposition_resolver = $this->createMock(DispositionResolverService::class);
    $disposition_resolver->method('resolveDispositionMap')->willReturn([
      'pc_1' => [
        'effective_disposition_score' => -180,
        'effective_disposition_label' => 'hostile',
      ],
    ]);

    $stance_state_store = $this->createMock(StanceStateStoreService::class);
    $stance_state_store->expects($this->never())->method('storeLatestState');
    $stance_event_store = $this->createMock(StanceEventStoreService::class);
    $stance_event_store->expects($this->never())->method('recordStanceEvent');

    $service = new ActorStanceResolverService(
      $actor_disposition,
      $disposition_resolver,
      $stance_state_store,
      $stance_event_store
    );
    $result = $service->projectStance(77, 'npc_skeleton_2', [
      'mode' => 'combat_entry',
      'target_entity_refs' => ['pc_1'],
      'threat_level' => 'major',
    ]);

    $this->assertSame('aggressive_engage', $result['stance']);
  }

}
