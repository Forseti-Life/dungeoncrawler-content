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
   * Encounter startup auto-plays an initial non-player turn.
   *
   * @covers ::onEnter
   */
  public function testOnEnterAutoPlaysInitialNonPlayerTurn(): void {
    $combat_engine = $this->createMock(CombatEngine::class);
    $combat_engine->expects($this->once())
      ->method('createEncounter')
      ->with(42, 'room-a', $this->isType('array'), ['room_id' => 'room-a'])
      ->willReturn(99);
    $combat_engine->expects($this->once())
      ->method('startEncounter')
      ->with(99)
      ->willReturn([
        'encounter' => [
          'participants' => [
            ['entity_id' => 'npc-1', 'team' => 'enemy'],
            ['entity_id' => 'pc-1', 'team' => 'player'],
          ],
        ],
      ]);

    $ai_gm = $this->createMock(AiGmService::class);
    $ai_gm->method('narrateEncounterStart')->willReturn('');

    $handler = $this->buildOnEnterTestHandler($combat_engine, $ai_gm);
    $game_state = [];
    $dungeon_data = ['active_room_id' => 'room-a'];

    $events = $handler->onEnter([], $game_state, $dungeon_data, 42);

    $this->assertSame([99, 'npc-1', 42], $handler->autoPlayArgs);
    $this->assertSame([99, 'npc-1', 42], $handler->processEndTurnArgs);
    $this->assertSame('pc-1', $game_state['turn']['entity']);
    $this->assertSame(1, $game_state['turn']['index']);
    $this->assertSame('encounter_started', $events[0]['type'] ?? null);
    $this->assertSame('npc_auto', $events[1]['type'] ?? null);
    $this->assertSame('npc_advanced', $events[2]['type'] ?? null);
  }

  /**
   * Neutral NPCs are excluded from combat participant lists.
   *
   * @covers ::buildParticipantList
   */
  public function testBuildParticipantListExcludesNeutralNpcQuestGivers(): void {
    $handler = $this->buildParticipantListTestHandler();
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'entity_type' => 'player_character',
          'entity_ref' => ['content_type' => 'player_character', 'content_id' => 'pc-1'],
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 0, 'r' => 0]],
          'state' => ['metadata' => ['display_name' => 'Hero', 'stats' => ['perception' => 5, 'currentHp' => 20, 'maxHp' => 20, 'ac' => 18]]],
        ],
        [
          'entity_instance_id' => 'npc-gribbles',
          'entity_type' => 'npc',
          'entity_ref' => ['content_type' => 'npc', 'content_id' => 'gribbles_rindsworth'],
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 1, 'r' => 0]],
          'state' => ['metadata' => ['display_name' => 'Gribbles Rindsworth', 'team' => 'neutral', 'stats' => ['perception' => 5, 'currentHp' => 16, 'maxHp' => 16, 'ac' => 18]]],
        ],
      ],
    ];

    $participants = $handler->exposedBuildParticipantList($dungeon_data, 'room-a', []);

    $this->assertCount(1, $participants);
    $this->assertSame('pc-1', $participants[0]['entity_id']);
    $this->assertSame('player', $participants[0]['team']);
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

  /**
   * Builds an EncounterPhaseHandler test double for onEnter startup flow.
   */
  private function buildOnEnterTestHandler(CombatEngine $combat_engine, AiGmService $ai_gm): EncounterPhaseHandler {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $combat_engine,
      $this->createMock(ActionProcessor::class),
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(HPManager::class),
      $this->createMock(ConditionManager::class),
      $this->createMock(CombatCalculator::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(EncounterAiIntegrationService::class),
      $this->createMock(RulesEngine::class),
      $this->createMock(EventDispatcherInterface::class),
      $ai_gm,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(NpcPsychologyService::class)
    ) extends EncounterPhaseHandler {
      public array $autoPlayArgs = [];
      public array $processEndTurnArgs = [];

      protected function buildParticipantList(array $dungeon_data, string $room_id, array $enemies = []): array {
        return [
          ['entity_id' => 'npc-1', 'entity_ref' => 'npc-1', 'team' => 'enemy', 'name' => 'NPC 1'],
          ['entity_id' => 'pc-1', 'entity_ref' => 'pc-1', 'team' => 'player', 'name' => 'PC 1'],
        ];
      }

      protected function autoPlayNpcTurn(int $encounter_id, string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
        $this->autoPlayArgs = [$encounter_id, $entity_id, $campaign_id];
        return [
          'events' => [
            ['type' => 'npc_auto'],
          ],
        ];
      }

      protected function isEncounterOver(int $encounter_id, array $game_state): bool {
        return FALSE;
      }

      protected function processEndTurn(int $encounter_id, ?string $actor_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
        $this->processEndTurnArgs = [$encounter_id, $actor_id, $campaign_id];
        $game_state['turn'] = [
          'entity' => 'pc-1',
          'index' => 1,
          'actions_remaining' => 3,
          'attacks_this_turn' => 0,
          'reaction_available' => TRUE,
          'delayed' => FALSE,
        ];

        return [
          'npc_events' => [
            ['type' => 'npc_advanced'],
          ],
        ];
      }

      protected function queueNarrationEvent(int $campaign_id, array $dungeon_data, array $event, ?string $room_id = NULL): array {
        return [];
      }
    };
  }

  /**
   * Builds an EncounterPhaseHandler test double exposing participant list building.
   */
  private function buildParticipantListTestHandler(): EncounterPhaseHandler {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new class(
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
    ) extends EncounterPhaseHandler {
      public function exposedBuildParticipantList(array $dungeon_data, string $room_id, array $enemies = []): array {
        return $this->buildParticipantList($dungeon_data, $room_id, $enemies);
      }
    };
  }

}
