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
   * Direct NPC dialogue touchpoints are deterministic when one active objective matches.
   */
  public function testIngestEventAppliesDirectNpcDialogueTouchpoint(): void {
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
        'npc_ref' => 'Guard Captain',
        'entity_ref' => 'npc-guard',
        'room_id' => 'crossroads',
        'matching_mode' => 'direct_npc_dialogue',
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('APPLY_PROGRESS', $result['decision']);
    $this->assertSame('escort_to_safety_runtime_1', $result['objective_id']);
  }

  /**
   * Offered quests are auto-started before applying a matching touchpoint.
   */
  public function testIngestEventStartsOfferedQuestBeforeApplyingProgress(): void {
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
      ->willReturn([]);
    $quest_tracker->expects($this->once())
      ->method('getOfferQuests')
      ->with(85, 'crossroads', 99)
      ->willReturn([$this->buildOfferedQuestRow()]);
    $quest_tracker->expects($this->once())
      ->method('startQuest')
      ->with(85, 'rescue_merchant_offered', 99)
      ->willReturn(TRUE);
    $quest_tracker->expects($this->once())
      ->method('updateObjectiveProgress')
      ->with(85, 'rescue_merchant_offered', 'escort_to_safety_runtime_1', 1, 99)
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
        'npc_ref' => 'Guard Captain',
        'entity_ref' => 'npc-guard',
        'room_id' => 'crossroads',
        'matching_mode' => 'direct_npc_dialogue',
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('APPLY_PROGRESS', $result['decision']);
    $this->assertSame('rescue_merchant_offered', $result['quest_id']);
  }

  /**
   * Entity refs complete NPC objectives even when speaker names are descriptive.
   */
  public function testIngestEventMatchesNpcEntityRefWhenSpeakerNameDiffersFromObjectiveTarget(): void {
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
      ->with(110, 429)
      ->willReturn([$this->buildSpellbookReturnQuestRow()]);
    $quest_tracker->expects($this->once())
      ->method('updateObjectiveProgress')
      ->with(110, 'collect_spellbooks_110_6a1ce5c3e390a', 'return_books', 1, 429)
      ->willReturn(['success' => TRUE]);

    $confirmation_service = $this->createMock(QuestConfirmationService::class);
    $confirmation_service->expects($this->never())
      ->method('createPending');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new QuestTouchpointService($quest_tracker, $confirmation_service, $factory, $time);
    $result = $service->ingestEvent(110, [
      'character_id' => 429,
      'touchpoint' => [
        'objective_type' => 'interact',
        'npc_ref' => 'Marta the Scholar',
        'entity_ref' => 'npc_scholar_npc',
        'room_id' => 'tavern_entrance',
        'matching_mode' => 'direct_npc_dialogue',
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('APPLY_PROGRESS', $result['decision']);
    $this->assertSame('return_books', $result['objective_id']);
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
   * Direct NPC hand-ins should auto-apply all matching interact objectives.
   */
  public function testIngestEventAutoAppliesAllDirectNpcInteractCandidates(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->expects($this->once())
      ->method('get')
      ->willReturn(NULL);
    $store->expects($this->exactly(3))
      ->method('set');

    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);

    $captured_updates = [];
    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('getActiveQuests')
      ->with(282, 1046)
      ->willReturn([
        $this->buildTavernTurnInQuestRow('gather_wine_quest', 'return_wine', 'Return the wine to the tavern keeper'),
        $this->buildTavernTurnInQuestRow('gather_torch_quest', 'return_torches', 'Bring the torch components to the tavern keeper'),
      ]);
    $quest_tracker->expects($this->exactly(2))
      ->method('updateObjectiveProgress')
      ->willReturnCallback(static function (
        int $campaign_id,
        string $quest_id,
        string $objective_id,
        int $amount,
        int $character_id
      ) use (&$captured_updates): array {
        $captured_updates[] = [$campaign_id, $quest_id, $objective_id, $amount, $character_id];
        return ['success' => TRUE];
      });

    $confirmation_service = $this->createMock(QuestConfirmationService::class);
    $confirmation_service->expects($this->never())
      ->method('createPending');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new QuestTouchpointService($quest_tracker, $confirmation_service, $factory, $time);
    $result = $service->ingestEvent(282, [
      'character_id' => 1046,
      'touchpoint' => [
        'objective_type' => 'interact',
        'npc_ref' => 'Eldric',
        'entity_ref' => 'tavern_keeper',
        'room_id' => 'tavern_entrance',
        'confidence' => 'high',
        'matching_mode' => 'direct_npc_dialogue',
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('APPLY_PROGRESS', $result['decision']);
    $this->assertCount(2, $result['applied_objectives'] ?? []);
    $this->assertCount(2, $captured_updates);
    $this->assertSame([282, 'gather_wine_quest', 'return_wine', 1, 1046], $captured_updates[0]);
    $this->assertSame([282, 'gather_torch_quest', 'return_torches', 1, 1046], $captured_updates[1]);
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

  /**
   * Build the spellbook return quest shape from campaign runtime data.
   */
  private function buildSpellbookReturnQuestRow(): array {
    return [
      'quest_id' => 'collect_spellbooks_110_6a1ce5c3e390a',
      'quest_name' => 'Collect Lost Spellbooks',
      'character_id' => 429,
      'current_phase' => 2,
      'objective_states' => json_encode([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'collect_books',
              'type' => 'collect',
              'description' => 'Find and collect Spellbooks in The Gilded Tankard',
              'completed' => TRUE,
              'item' => 'Spellbooks',
              'current' => 4,
              'target_count' => 4,
            ],
          ],
        ],
        [
          'phase' => 2,
          'objectives' => [
            [
              'objective_id' => 'return_books',
              'type' => 'interact',
              'description' => 'Return the books to the scholar',
              'completed' => FALSE,
              'target' => 'scholar_npc',
              'location_id' => 'tavern_entrance',
              'completion_criteria' => [
                'kind' => 'flag',
                'metric' => 'completed',
                'required_value' => TRUE,
              ],
            ],
          ],
        ],
      ]),
    ];
  }

  /**
   * Build an offered quest row that can be matched and then started.
   */
  private function buildOfferedQuestRow(): array {
    return [
      'quest_id' => 'rescue_merchant_offered',
      'quest_name' => 'Rescue the Merchant',
      'character_id' => 0,
      'current_phase' => 1,
      'generated_objectives' => json_encode([
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
      'objective_states' => '[]',
    ];
  }

  /**
   * Build a tavern hand-in quest row with one active interact objective.
   */
  private function buildTavernTurnInQuestRow(string $quest_id, string $objective_id, string $description): array {
    return [
      'quest_id' => $quest_id,
      'quest_name' => 'Tavern Hand-in',
      'character_id' => 1046,
      'current_phase' => 2,
      'objective_states' => json_encode([
        [
          'phase' => 2,
          'objectives' => [
            [
              'objective_id' => $objective_id,
              'type' => 'interact',
              'description' => $description,
              'completed' => FALSE,
              'target' => 'tavern_keeper',
              'location_id' => 'tavern_entrance',
              'revealed' => TRUE,
            ],
          ],
        ],
      ]),
    ];
  }

}
