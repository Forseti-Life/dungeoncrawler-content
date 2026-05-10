<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CanonicalActionRegistryService;
use Drupal\dungeoncrawler_content\Service\GmOrchestrationBrokerService;
use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
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
  protected ContainerInterface $serviceContainer;

  protected function setUp(): void {
    parent::setUp();

    $database = $this->createMock(Connection::class);
    $registry = $this->createMock(CanonicalActionRegistryService::class);
    $this->questTouchpointService = $this->createMock(QuestTouchpointService::class);
    $this->serviceContainer = $this->createMock(ContainerInterface::class);

    $this->service = new GmOrchestrationBrokerService(
      $database,
      $registry,
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

}
