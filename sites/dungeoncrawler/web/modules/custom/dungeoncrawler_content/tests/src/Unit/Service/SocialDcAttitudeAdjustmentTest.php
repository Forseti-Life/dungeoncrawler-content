<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ActionProcessor;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\CombatEngine;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler;
use Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\dungeoncrawler_content\Service\RulesEngine;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests NPC attitude social DC adjustments.
 *
 * @group dungeoncrawler_content
 * @group social
 * @group pf2e-rules
 */
class SocialDcAttitudeAdjustmentTest extends UnitTestCase {

  /**
   * Tests encounter Request uses stored NPC attitude to modify the DC.
   */
  public function testEncounterRequestAppliesNpcAttitudeDcAdjustment(): void {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $combat_calculator = $this->createMock(CombatCalculator::class);
    $combat_calculator
      ->method('calculateDegreeOfSuccess')
      ->with(17, 18, 10)
      ->willReturn('failure');

    $number_generation = $this->createMock(NumberGenerationService::class);
    $number_generation->method('rollPathfinderDie')->with(20)->willReturn(10);

    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology
      ->method('loadProfile')
      ->with(42, 'npc-1')
      ->willReturn(['attitude' => 'friendly']);

    $handler = new EncounterPhaseHandler(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(CombatEngine::class),
      $this->createMock(ActionProcessor::class),
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(HPManager::class),
      $this->createMock(ConditionManager::class),
      $combat_calculator,
      $number_generation,
      $this->createMock(EncounterAiIntegrationService::class),
      $this->createMock(RulesEngine::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(AiGmService::class),
      $this->createMock(ConfigFactoryInterface::class),
      $psychology
    );

    $intent = [
      'type' => 'request',
      'actor' => 'pc-1',
      'target' => 'npc-1',
      'params' => [
        'dc' => 20,
        'diplomacy_bonus' => 7,
      ],
    ];
    $game_state = [
      'encounter_id' => 1,
      'turn' => ['actions_remaining' => 3],
    ];
    $dungeon_data = [];

    $response = $handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(18, $response['result']['dc']);
    $this->assertSame(20, $response['result']['base_dc']);
    $this->assertSame(-2, $response['result']['attitude_dc_delta']);
    $this->assertSame('friendly', $response['result']['npc_attitude']);
  }

  /**
   * Tests exploration Lie uses target entity attitude to modify the DC.
   */
  public function testExplorationLieAppliesNpcAttitudeDcAdjustment(): void {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $number_generation = $this->createMock(NumberGenerationService::class);
    $number_generation->method('rollPathfinderDie')->with(20)->willReturn(10);

    $handler = new ExplorationPhaseHandler(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $this->createMock(CharacterStateService::class),
      $number_generation,
      $this->createMock(AiGmService::class)
    );

    $intent = [
      'type' => 'lie',
      'actor' => 'pc-1',
      'target' => 'npc-1',
      'params' => [
        'dc' => 20,
        'deception_bonus' => 7,
      ],
    ];
    $game_state = ['phase' => 'exploration'];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'name' => 'Hero',
        ],
        [
          'entity_instance_id' => 'npc-1',
          'name' => 'Guard',
          'state' => [
            'metadata' => [
              'attitude' => 'hostile',
            ],
          ],
        ],
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(25, $response['result']['dc']);
    $this->assertSame(20, $response['result']['base_dc']);
    $this->assertSame(5, $response['result']['attitude_dc_delta']);
    $this->assertSame('hostile', $response['result']['npc_attitude']);
  }

}
