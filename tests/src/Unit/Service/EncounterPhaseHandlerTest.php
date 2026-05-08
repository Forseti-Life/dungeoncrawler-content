<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ActionProcessor;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\CombatEngine;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RulesEngine;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests EncounterPhaseHandler available-actions behavior.
 *
 * @group dungeoncrawler_content
 * @group encounter
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler
 */
class EncounterPhaseHandlerTest extends UnitTestCase {

  /**
   * Chameleon Gnomes expose minor color shift on their turn.
   *
   * @covers ::getAvailableActions
   */
  public function testGetAvailableActionsIncludesMinorColorShiftForChameleonGnome(): void {
    $handler = $this->buildHandler();
    $game_state = [
      'turn' => [
        'entity' => 'char-001',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'char-001',
          'heritage' => 'chameleon',
        ],
      ],
    ];

    $actions = $handler->getAvailableActions($game_state, $dungeon_data, 'char-001');

    $this->assertContains('minor_color_shift', $actions);
  }

  /**
   * Non-chameleon actors do not gain the heritage-specific action.
   *
   * @covers ::getAvailableActions
   */
  public function testGetAvailableActionsOmitsMinorColorShiftForOtherHeritages(): void {
    $handler = $this->buildHandler();
    $game_state = [
      'turn' => [
        'entity' => 'char-001',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'char-001',
          'heritage' => 'sensate',
        ],
      ],
    ];

    $actions = $handler->getAvailableActions($game_state, $dungeon_data, 'char-001');

    $this->assertNotContains('minor_color_shift', $actions);
  }

  /**
   * Minor Color Shift updates coloration and spends one action.
   *
   * @covers ::processIntent
   */
  public function testProcessIntentMinorColorShiftUpdatesColoration(): void {
    $handler = $this->buildHandler();
    $game_state = [
      'encounter_id' => 42,
      'round' => 3,
      'turn' => [
        'entity' => 'char-001',
        'actions_remaining' => 2,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [];
    $intent = [
      'type' => 'minor_color_shift',
      'actor' => 'char-001',
      'params' => [
        'heritage' => 'chameleon',
        'terrain_color_tag' => 'forest_green',
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('forest_green', $response['result']['coloration_tag']);
    $this->assertSame(1, $response['result']['action_cost']);
    $this->assertSame(1, $game_state['turn']['actions_remaining']);
    $this->assertSame(
      ['type' => 'char_state', 'key' => 'coloration_tag', 'value' => 'forest_green'],
      $response['mutations'][0]
    );
  }

  /**
   * Minor Color Shift is heritage-gated.
   *
   * @covers ::processIntent
   */
  public function testProcessIntentMinorColorShiftRejectsNonChameleonActor(): void {
    $handler = $this->buildHandler();
    $game_state = [
      'encounter_id' => 42,
      'turn' => [
        'entity' => 'char-001',
        'actions_remaining' => 2,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [];
    $intent = [
      'type' => 'minor_color_shift',
      'actor' => 'char-001',
      'params' => [
        'heritage' => 'sensate',
        'terrain_color_tag' => 'forest_green',
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('Minor Color Shift requires Chameleon Gnome heritage.', $response['result']['error']);
    $this->assertSame(2, $game_state['turn']['actions_remaining']);
  }

  /**
   * Builds an EncounterPhaseHandler with lightweight mocks.
   */
  private function buildHandler(): EncounterPhaseHandler {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new EncounterPhaseHandler(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(CombatEngine::class),
      $this->createMock(ActionProcessor::class),
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(HPManager::class),
      $this->createMock(ConditionManager::class),
      $this->createMock(CombatCalculator::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(EncounterAiIntegrationService::class),
      $this->createMock(RulesEngine::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(AiGmService::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(NpcPsychologyService::class)
    );
  }

}
