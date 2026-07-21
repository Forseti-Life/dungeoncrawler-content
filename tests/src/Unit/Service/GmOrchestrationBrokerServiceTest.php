<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CanonicalActionRegistryService;
use Drupal\dungeoncrawler_content\Service\GmOrchestrationBrokerService;
use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests for GmOrchestrationBrokerService.
 *
 * @group dungeoncrawler_content
 * @group gm-orchestration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GmOrchestrationBrokerService
 */
class GmOrchestrationBrokerServiceTest extends UnitTestCase {

  protected GmOrchestrationBrokerService $service;
  protected QuestTouchpointService $questTouchpointService;
  protected InventoryManagementService $inventoryManagementService;
  protected StateValidationService $stateValidationService;
  protected CanonicalActionRegistryService $registry;
  protected ContainerInterface $serviceContainer;

  protected function setUp(): void {
    parent::setUp();

    $database = $this->createMock(Connection::class);
    $this->registry = $this->createMock(CanonicalActionRegistryService::class);
    $this->questTouchpointService = $this->createMock(QuestTouchpointService::class);
    $this->inventoryManagementService = $this->createMock(InventoryManagementService::class);
    $this->stateValidationService = $this->createMock(StateValidationService::class);
    $this->stateValidationService->method('validateQuestTouchpointIngest')
      ->willReturn([
        'valid' => TRUE,
        'errors' => [],
      ]);
    $this->serviceContainer = $this->createMock(ContainerInterface::class);
    $this->serviceContainer->method('get')
      ->willReturnCallback(function (string $service_id) {
        if ($service_id === 'dungeoncrawler_content.inventory_management') {
          return $this->inventoryManagementService;
        }
        if ($service_id === 'dungeoncrawler_content.state_validation_service') {
          return $this->stateValidationService;
        }
        return NULL;
      });

    $this->service = new GmOrchestrationBrokerService(
      $database,
      $this->registry,
      $this->questTouchpointService,
      $this->serviceContainer
    );
  }

  /**
   * @covers ::validateQuestTurnInAction
   */
  public function testQuestTurnInValidationRequiresCharacterAndObjectiveType(): void {
    $result = $this->service->validateQuestTurnInAction(NULL, [
      'details' => [
        'quest' => [],
      ],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertContains('Quest turn-in requires an acting character.', $result['errors']);
    $this->assertContains('Quest turn-in action is missing objective_type.', $result['errors']);
  }

  /**
   * @covers ::validateCombatInitiationAction
   */
  public function testCombatInitiationValidationRejectsActiveEncounter(): void {
    $result = $this->service->validateCombatInitiationAction('tavern_entrance', [
      'game_state' => ['phase' => 'encounter'],
      'entities' => [],
    ], [
      'details' => [
        'combat' => [],
      ],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertSame(['Combat is already active.'], $result['errors']);
  }

  /**
   * @covers ::validateCombatInitiationAction
   * @covers ::resolveCombatEnemyEntities
   */
  public function testCombatInitiationValidationResolvesEnemyByName(): void {
    $dungeon_data = [
      'game_state' => ['phase' => 'exploration'],
      'entities' => [
        [
          'entity_instance_id' => 'npc-gribbles',
          'placement' => ['room_id' => 'tavern_entrance'],
          'state' => [
            'metadata' => [
              'display_name' => 'Gribbles',
              'team' => 'hostile',
            ],
          ],
        ],
        [
          'entity_instance_id' => 'npc-eldric',
          'placement' => ['room_id' => 'tavern_entrance'],
          'state' => [
            'metadata' => [
              'display_name' => 'Eldric',
              'team' => 'friendly',
            ],
          ],
        ],
      ],
    ];

    $result = $this->service->validateCombatInitiationAction('tavern_entrance', $dungeon_data, [
      'details' => [
        'combat' => [
          'target_name' => 'Gribbles',
        ],
      ],
    ]);

    $this->assertTrue($result['valid']);
    $this->assertCount(1, $result['enemies']);
    $this->assertSame('npc-gribbles', $result['enemies'][0]['entity_instance_id']);
  }

  /**
   * @covers ::validateCombatInitiationAction
   * @covers ::resolveCombatEnemyEntities
   */
  public function testCombatInitiationValidationRejectsAmbiguousUntargetedHostiles(): void {
    $dungeon_data = [
      'game_state' => ['phase' => 'exploration'],
      'entities' => [
        [
          'entity_instance_id' => 'npc-gribbles',
          'placement' => ['room_id' => 'tavern_entrance'],
          'state' => ['metadata' => ['display_name' => 'Gribbles', 'team' => 'hostile']],
        ],
        [
          'entity_instance_id' => 'npc-snarl',
          'placement' => ['room_id' => 'tavern_entrance'],
          'state' => ['metadata' => ['display_name' => 'Snarl', 'team' => 'hostile']],
        ],
      ],
    ];

    $result = $this->service->validateCombatInitiationAction('tavern_entrance', $dungeon_data, [
      'details' => [
        'combat' => [
          'reason' => 'Combat begins.',
        ],
      ],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertSame(['No valid enemy entities were found for combat initiation.'], $result['errors']);
  }

  /**
   * @covers ::handleQuestTurnInAction
   */
  public function testHandleQuestTurnInActionDelegatesToTouchpointService(): void {
    $this->questTouchpointService->expects($this->once())
      ->method('ingestEvent')
      ->with(42, $this->callback(static function (array $payload): bool {
        return $payload['character_id'] === 7
          && ($payload['touchpoint']['objective_type'] ?? NULL) === 'deliver'
          && ($payload['touchpoint']['room_id'] ?? NULL) === 'tavern_entrance';
      }))
      ->willReturn([
        'success' => TRUE,
        'objective_id' => 'deliver_spellbooks',
      ]);

    $result = $this->service->handleQuestTurnInAction(42, 'tavern_entrance', 7, [
      'details' => [
        'quest' => [
          'objective_type' => 'deliver',
          'objective_id' => 'deliver_spellbooks',
          'item_ref' => 'spellbook',
        ],
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('deliver_spellbooks', $result['objective_id']);
  }

  /**
   * @covers ::executeCanonicalAuthoritativeActions
   */
  public function testExecuteCanonicalAuthoritativeActionsReturnsReceiptForRejectedQuestTurnIn(): void {
    $result = $this->service->executeCanonicalAuthoritativeActions(
      42,
      'tavern_entrance',
      ['name' => 'Tavern Entrance'],
      NULL,
      [
        [
          'type' => 'quest_turn_in',
          'name' => 'Turn in quest item',
          'details' => [
            'quest' => [],
          ],
        ],
      ],
      ['entities' => []]
    );

    $this->assertCount(1, $result['errors']);
    $this->assertCount(1, $result['receipts']);
    $this->assertSame('rejected', $result['receipts'][0]['status']);
    $this->assertFalse($result['receipts'][0]['validation']['valid']);
    $this->assertNotEmpty($result['receipts'][0]['clarification']);
  }

  /**
   * @covers ::executeCanonicalAuthoritativeActions
   * @covers ::handleTransferCurrencyAction
   */
  public function testExecuteCanonicalAuthoritativeActionsExecutesTransferCurrencyViaBroker(): void {
    $this->inventoryManagementService->expects($this->once())
      ->method('transferCurrencyTransaction')
      ->with(
        ['owner_type' => 'character', 'owner_id' => '7'],
        ['owner_type' => 'character', 'owner_id' => '9'],
        'gp',
        3,
        42
      )
      ->willReturn([
        'success' => TRUE,
        'transaction_id' => 'currency_tx_test',
      ]);

    $result = $this->service->executeCanonicalAuthoritativeActions(
      42,
      'tavern_entrance',
      ['name' => 'Tavern Entrance'],
      7,
      [[
        'type' => 'transfer_currency',
        'name' => 'Pay innkeeper',
        'details' => [
          'currency_transfer' => [
            'source_owner_type' => 'character',
            'source_owner_id' => 'ACTING_CHARACTER',
            'dest_owner_type' => 'character',
            'dest_owner_id' => '9',
            'denomination' => 'gp',
            'amount' => 3,
          ],
        ],
      ]],
      ['entities' => []]
    );

    $this->assertSame([], $result['actions']);
    $this->assertCount(1, $result['results']['transfer_currency']);
    $this->assertTrue($result['results']['transfer_currency'][0]['success']);
    $this->assertCount(1, $result['receipts']);
    $this->assertSame('executed', $result['receipts'][0]['status']);
  }

  /**
   * @covers ::executeCanonicalAuthoritativeActions
   * @covers ::handleConsumeInventoryAction
   */
  public function testExecuteCanonicalAuthoritativeActionsExecutesConsumeInventoryViaBroker(): void {
    $this->inventoryManagementService->expects($this->once())
      ->method('consumeItemTransaction')
      ->with(
        ['owner_type' => 'character', 'owner_id' => '7', 'location_type' => NULL],
        'item-abc',
        1,
        42
      )
      ->willReturn([
        'success' => TRUE,
        'item_instance_id' => 'item-abc',
      ]);

    $result = $this->service->executeCanonicalAuthoritativeActions(
      42,
      'tavern_entrance',
      ['name' => 'Tavern Entrance'],
      7,
      [[
        'type' => 'consume_inventory',
        'name' => 'Drink potion',
        'details' => [
          'consume' => [
            'source_owner_type' => 'character',
            'source_owner_id' => 'ACTING_CHARACTER',
            'item_instance_id' => 'item-abc',
            'quantity' => 1,
          ],
        ],
      ]],
      ['entities' => []]
    );

    $this->assertSame([], $result['actions']);
    $this->assertCount(1, $result['results']['consume_inventory']);
    $this->assertTrue($result['results']['consume_inventory'][0]['success']);
    $this->assertCount(1, $result['receipts']);
    $this->assertSame('executed', $result['receipts'][0]['status']);
  }

  /**
   * @covers ::executeCanonicalAuthoritativeActions
   * @covers ::handleApplyQuestTouchpointAction
   */
  public function testExecuteCanonicalAuthoritativeActionsExecutesQuestTouchpointViaBroker(): void {
    $this->stateValidationService->expects($this->once())
      ->method('validateQuestTouchpointIngest')
      ->willReturn([
        'valid' => TRUE,
        'errors' => [],
      ]);
    $this->questTouchpointService->expects($this->once())
      ->method('ingestEvent')
      ->with(42, $this->callback(static function (array $payload): bool {
        return (int) ($payload['character_id'] ?? 0) === 7
          && (string) ($payload['touchpoint']['objective_type'] ?? '') === 'deliver';
      }))
      ->willReturn([
        'success' => TRUE,
        'decision' => 'APPLIED',
      ]);

    $result = $this->service->executeCanonicalAuthoritativeActions(
      42,
      'tavern_entrance',
      ['name' => 'Tavern Entrance'],
      7,
      [[
        'type' => 'apply_quest_touchpoint',
        'name' => 'Apply quest touchpoint',
        'details' => [
          'touchpoint' => [
            'objective_type' => 'deliver',
            'objective_id' => 'deliver_spellbooks',
            'item_ref' => 'spellbook',
            'room_id' => 'tavern_entrance',
          ],
        ],
      ]],
      ['entities' => []]
    );

    $this->assertSame([], $result['actions']);
    $this->assertCount(1, $result['results']['apply_quest_touchpoint']);
    $this->assertTrue($result['results']['apply_quest_touchpoint'][0]['success']);
    $this->assertCount(1, $result['receipts']);
    $this->assertSame('executed', $result['receipts'][0]['status']);
  }

  /**
   * @covers ::executeCanonicalAuthoritativeActions
   * @covers ::handleApplyQuestTouchpointAction
   */
  public function testExecuteCanonicalAuthoritativeActionsRejectsInvalidQuestTouchpointContract(): void {
    $this->stateValidationService->expects($this->once())
      ->method('validateQuestTouchpointIngest')
      ->willReturn([
        'valid' => FALSE,
        'errors' => ['touchpoint.objective_type is required'],
      ]);
    $this->questTouchpointService->expects($this->never())
      ->method('ingestEvent');

    $result = $this->service->executeCanonicalAuthoritativeActions(
      42,
      'tavern_entrance',
      ['name' => 'Tavern Entrance'],
      7,
      [[
        'type' => 'apply_quest_touchpoint',
        'name' => 'Apply quest touchpoint',
        'details' => [
          'touchpoint' => [
            'objective_type' => 'deliver',
            'objective_id' => 'deliver_spellbooks',
            'room_id' => 'tavern_entrance',
          ],
        ],
      ]],
      ['entities' => []]
    );

    $this->assertCount(1, $result['results']['apply_quest_touchpoint']);
    $this->assertFalse($result['results']['apply_quest_touchpoint'][0]['success']);
    $this->assertSame('rejected', $result['receipts'][0]['status']);
  }

}
