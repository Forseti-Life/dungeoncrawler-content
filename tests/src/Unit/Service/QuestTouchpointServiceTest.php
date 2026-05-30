<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\dungeoncrawler_content\Service\QuestConfirmationService;
use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers recursive current-phase objective flattening for touchpoints.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestTouchpointServiceTest extends UnitTestCase {

  /**
   * Verifies nested active child objectives are surfaced for touchpoint matching.
   */
  public function testGetActiveObjectivesForCurrentPhaseFlattensNestedChildren(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);

    $service = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(QuestConfirmationService::class),
      $factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTouchpointService {
      public function exposedGetActiveObjectivesForCurrentPhase(array $quest): array {
        return $this->getActiveObjectivesForCurrentPhase($quest);
      }
    };

    $objectives = $service->exposedGetActiveObjectivesForCurrentPhase([
      'current_phase' => 1,
      'objective_states' => json_encode([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'escort_to_safety',
              'type' => 'escort',
              'description' => 'Escort the merchant to safety.',
              'completed' => FALSE,
              'children' => [
                [
                  'objective_id' => 'reach_safehouse',
                  'type' => 'explore',
                  'location' => 'safehouse',
                  'description' => 'Reach the safehouse.',
                  'completed' => FALSE,
                  'discovered' => FALSE,
                ],
                [
                  'objective_id' => 'speak_to_merchant',
                  'type' => 'interact',
                  'target' => 'merchant',
                  'description' => 'Check on the merchant.',
                  'completed' => FALSE,
                ],
              ],
            ],
          ],
        ],
      ]),
    ]);

    $this->assertCount(2, $objectives);
    $this->assertSame(['reach_safehouse', 'speak_to_merchant'], array_column($objectives, 'objective_id'));
  }

  /**
   * Typed receipt hints apply progress without confirmation.
   */
  public function testIngestEventAppliesTypedReceiptTouchpoint(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->expects($this->once())
      ->method('get')
      ->willReturn(NULL);
    $store->expects($this->once())
      ->method('set');

    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);

    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('getActiveQuests')
      ->with(85, 99)
      ->willReturn([$this->buildActiveQuestRow()]);
    $quest_tracker->expects($this->once())
      ->method('updateObjectiveProgress')
      ->with(85, 'rescue_merchant', 'escort_to_safety_runtime_1', 1, 99)
      ->willReturn(['success' => TRUE]);

    $confirmation_service = $this->createMock(QuestConfirmationService::class);
    $confirmation_service->expects($this->never())
      ->method('createPending');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new QuestTouchpointService($quest_tracker, $confirmation_service, $factory, $time);
    $result = $service->ingestEvent(85, [
      'character_id' => 99,
      'touchpoint' => [
        'objective_type' => 'interact',
        'objective_id' => 'escort_to_safety_runtime_1',
        'npc_ref' => 'Guard Captain',
        'entity_ref' => 'npc-guard',
        'room_id' => 'crossroads',
        'quantity' => 1,
        'matching_mode' => 'typed_receipt',
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('APPLY_PROGRESS', $result['decision']);
    $this->assertSame('escort_to_safety_runtime_1', $result['objective_id']);
  }

  /**
   * Text-only touchpoints must go through confirmation even when they match.
   */
  public function testIngestEventRequestsConfirmationForTextOnlyTouchpoint(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->expects($this->once())
      ->method('get')
      ->willReturn(NULL);
    $store->expects($this->never())
      ->method('set');

    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);

    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('getActiveQuests')
      ->with(85, 99)
      ->willReturn([$this->buildActiveQuestRow()]);
    $quest_tracker->expects($this->never())
      ->method('updateObjectiveProgress');

    $confirmation_service = $this->createMock(QuestConfirmationService::class);
    $confirmation_service->expects($this->once())
      ->method('createPending')
      ->with(
        85,
        99,
        $this->callback(static fn(array $payload): bool => ($payload['touchpoint']['objective_id'] ?? '') === ''),
        $this->callback(static fn(array $candidates): bool => count($candidates) === 1 && ($candidates[0]['objective_id'] ?? '') === 'escort_to_safety_runtime_1'),
        $this->stringContains('Text-only quest touchpoint requires confirmation')
      )
      ->willReturn([
        'confirmation_id' => 'qcf_123',
        'candidates' => [['objective_id' => 'escort_to_safety_runtime_1']],
      ]);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new QuestTouchpointService($quest_tracker, $confirmation_service, $factory, $time);
    $result = $service->ingestEvent(85, [
      'character_id' => 99,
      'touchpoint' => [
        'objective_type' => 'interact',
        'npc_ref' => 'Guard Captain',
        'entity_ref' => 'npc-guard',
        'room_id' => 'crossroads',
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('REQUEST_CONFIRMATION', $result['decision']);
    $this->assertTrue($result['requires_confirmation']);
  }

  /**
   * Receipt touchpoints override text-only room hints when both are present.
   */
  public function testIngestEventPrefersDeterministicReceiptTouchpoint(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->expects($this->once())
      ->method('get')
      ->willReturn(NULL);
    $store->expects($this->once())
      ->method('set');

    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);

    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('getActiveQuests')
      ->with(85, 99)
      ->willReturn([$this->buildActiveQuestRow()]);
    $quest_tracker->expects($this->once())
      ->method('updateObjectiveProgress')
      ->with(85, 'rescue_merchant', 'escort_to_safety_runtime_1', 1, 99)
      ->willReturn(['success' => TRUE]);

    $confirmation_service = $this->createMock(QuestConfirmationService::class);
    $confirmation_service->expects($this->never())
      ->method('createPending');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new QuestTouchpointService($quest_tracker, $confirmation_service, $factory, $time);
    $result = $service->ingestEvent(85, [
      'character_id' => 99,
      'touchpoint' => [
        'objective_type' => 'interact',
        'npc_ref' => 'Wrong Guard',
        'entity_ref' => 'npc-other',
        'room_id' => 'crossroads',
        'matching_mode' => 'text_inference',
      ],
      'receipt' => [
        'route' => 'quest_progression',
        'tool' => 'quest_turn_in',
        'resolved_arguments' => [
          'quest' => [
            'objective_type' => 'interact',
            'objective_id' => 'escort_to_safety_runtime_1',
            'npc_ref' => 'Guard Captain',
            'entity_ref' => 'npc-guard',
            'quantity' => 1,
            'room_id' => 'crossroads',
          ],
        ],
        'execution' => [
          'objective_id' => 'escort_to_safety_runtime_1',
          'progress_delta' => 1,
        ],
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('APPLY_PROGRESS', $result['decision']);
    $this->assertSame('escort_to_safety_runtime_1', $result['objective_id']);
  }

  /**
   * Build a representative active quest row for matching.
   */
  private function buildActiveQuestRow(): array {
    return [
      'quest_id' => 'rescue_merchant',
      'quest_name' => 'Rescue the Merchant',
      'character_id' => 99,
      'current_phase' => 1,
      'objective_states' => json_encode([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'escort_to_safety_runtime_1',
              'type' => 'interact',
              'target' => 'Guard Captain',
              'npc_ref' => 'npc-guard',
              'description' => 'Speak to the Guard Captain.',
              'completed' => FALSE,
            ],
          ],
        ],
      ]),
    ];
  }

}
