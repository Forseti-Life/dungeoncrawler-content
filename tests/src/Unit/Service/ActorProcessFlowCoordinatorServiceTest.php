<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorProcessFlowCoordinatorService;
use Drupal\dungeoncrawler_content\Service\ProcessFlowEventStoreService;
use Drupal\dungeoncrawler_content\Service\ProcessFlowStateStoreService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ActorProcessFlowCoordinatorService.
 *
 * @group dungeoncrawler_content
 * @group actor-process-flow
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorProcessFlowCoordinatorService
 */
class ActorProcessFlowCoordinatorServiceTest extends UnitTestCase {

  /**
   * @covers ::selectActiveFlow
   */
  public function testRoomEngageDialogueSelectsRoomDialogueFlow(): void {
    $state_store = $this->createMock(ProcessFlowStateStoreService::class);
    $state_store->method('loadLatestState')->willReturn(NULL);
    $state_store->expects($this->once())->method('storeLatestState');
    $event_store = $this->createMock(ProcessFlowEventStoreService::class);
    $event_store->expects($this->once())->method('recordProcessFlowEvent');

    $service = new ActorProcessFlowCoordinatorService($state_store, $event_store);
    $result = $service->selectActiveFlow(77, 'npc_gribbles', [
      'mode' => 'room',
      'stance' => 'engage_dialogue',
      'target_actor_ref' => 'pc_1',
    ], [
      'trigger' => 'player_direct_address',
    ]);

    $this->assertSame('actor_process_flow_contract_v1', $result['contract_version']);
    $this->assertSame('room-dialogue-flow', $result['active_flow']);
    $this->assertSame('player_direct_address', $result['trigger']);
  }

  /**
   * @covers ::selectActiveFlow
   */
  public function testCombatEntryAggressiveStanceSelectsCombatEntryFlow(): void {
    $service = new ActorProcessFlowCoordinatorService();
    $result = $service->selectActiveFlow(77, 'npc_skeleton_1', [
      'mode' => 'combat_entry',
      'stance' => 'aggressive_engage',
      'target_actor_ref' => 'pc_2',
    ], [
      'explicit_attack_declared' => TRUE,
      'combat_entry_threshold_gate' => TRUE,
    ]);

    $this->assertSame('combat-entry-flow', $result['active_flow']);
    $this->assertFalse($result['handoff_ready']);
  }

  /**
   * @covers ::selectActiveFlow
   */
  public function testCombatEntryThreatenWithThresholdGateUsesCombatEntryPrecedence(): void {
    $service = new ActorProcessFlowCoordinatorService();
    $result = $service->selectActiveFlow(77, 'npc_bandit', [
      'mode' => 'combat_entry',
      'stance' => 'threaten',
      'target_actor_ref' => 'pc_2',
    ], [
      'combat_entry_threshold_gate' => TRUE,
      'explicit_attack_declared' => FALSE,
    ]);

    $this->assertSame('combat-entry-flow', $result['active_flow']);
    $this->assertStringContainsString('precedence routing', (string) ($result['metadata']['selection_reason'] ?? ''));
  }

  /**
   * @covers ::selectActiveFlow
   */
  public function testScriptedSceneOverridesOtherCandidates(): void {
    $service = new ActorProcessFlowCoordinatorService();
    $result = $service->selectActiveFlow(77, 'npc_merchant', [
      'mode' => 'room',
      'stance' => 'observe',
    ], [
      'scripted_scene_required' => TRUE,
    ]);

    $this->assertSame('scripted-scene-flow', $result['active_flow']);
  }

  /**
   * @covers ::selectActiveFlow
   */
  public function testBlockedHigherPrecedenceFlowRemainsSelectedWithBlockers(): void {
    $service = new ActorProcessFlowCoordinatorService();
    $result = $service->selectActiveFlow(77, 'npc_guard', [
      'mode' => 'combat_entry',
      'stance' => 'aggressive_engage',
      'target_actor_ref' => 'pc_1',
    ], [
      'flow_blockers' => [
        'combat-entry-flow' => ['no_valid_targets'],
      ],
    ]);

    $this->assertSame('combat-entry-flow', $result['active_flow']);
    $this->assertSame(['no_valid_targets'], $result['metadata']['blocking_conditions']);
  }

}
