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
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
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
   * Initial encounter state still exposes the active turn actions without an explicit actor id.
   *
   * @covers ::getAvailableActions
   */
  public function testGetAvailableActionsDefaultsToCurrentTurnWhenActorIdMissing(): void {
    $handler = $this->buildHandler();
    $actions = $handler->getAvailableActions([
      'turn' => [
        'entity' => 'char-001',
        'actions_remaining' => 3,
        'reaction_available' => TRUE,
      ],
    ], []);

    $this->assertContains('talk', $actions);
    $this->assertContains('end_turn', $actions);
    $this->assertContains('reaction', $actions);
  }

  /**
   * Talk is blocked when the acting player does not own the active turn.
   *
   * @covers ::validateIntent
   */
  public function testValidateIntentRejectsTalkOutOfTurnWithFriendlyMessage(): void {
    $handler = $this->buildHandler();
    $validation = $handler->validateIntent([
      'type' => 'talk',
      'actor' => 'char-002',
    ], [
      'encounter_id' => 42,
      'turn' => [
        'entity' => 'char-001',
        'actions_remaining' => 3,
      ],
    ], []);

    $this->assertFalse($validation['valid']);
    $this->assertSame("It's not your turn, please wait.", $validation['reason']);
  }

  /**
   * Room-scene actions require complete round/turn/initiative context.
   *
   * @covers ::validateIntent
   */
  public function testValidateIntentRejectsRoomSceneActionWithIncompleteContext(): void {
    $handler = $this->buildHandler();
    $validation = $handler->validateIntent([
      'type' => 'talk',
      'actor' => 'char-001',
      'params' => ['message' => 'Hello'],
    ], [
      'encounter_id' => NULL,
      'encounter_context' => ['room_id' => 'room-a'],
      'turn' => ['entity' => 'char-001'],
      // Missing round + initiative_order on purpose.
    ], []);

    $this->assertFalse($validation['valid']);
    $this->assertSame(
      'Encounter room-scene context is incomplete (missing round/turn/initiative).',
      $validation['reason']
    );
  }

  /**
   * Encounter talk delegates to RoomChatService and returns an explicit contract.
   *
   * @covers ::processIntent
   * @covers ::getClientActionContract
   */
  public function testProcessIntentTalkDelegatesToRoomChatServiceAndBuildsContract(): void {
    $room_chat = $this->createMock(RoomChatService::class);
    $room_chat->expects($this->once())
      ->method('postMessage')
      ->with(
        42,
        'room-a',
        $this->callback(static fn($speaker): bool => is_string($speaker) && trim($speaker) !== ''),
        $this->callback(static fn($message): bool => is_string($message) && str_contains($message, 'Guide, Hold the doorway.')),
        'player',
        99,
        'room',
        TRUE,
        FALSE,
        NULL,
        $this->callback(static function (array $metadata): bool {
          return ($metadata['objective_type'] ?? '') === ''
            && ($metadata['objective_id'] ?? '') === ''
            && ($metadata['entity_ref'] ?? '') === 'npc-guide'
            && ($metadata['_validated_encounter_talk'] ?? FALSE) === TRUE
            && is_string($metadata['_encounter_prefix'] ?? NULL);
        })
      )
      ->willReturn([
        'gm_response' => ['message' => 'The guide nods and braces the door.'],
        'npc_interjections' => [['speaker' => 'Guide', 'message' => 'On it.']],
        'state_diff' => ['situational' => ['attack_bonus' => 1]],
        'canonical_actions' => ['other' => ['success' => TRUE]],
        'mutations' => [['type' => 'state', 'key' => 'cover', 'value' => TRUE]],
      ]);

    $handler = $this->buildHandler($room_chat);
    $game_state = [
      'encounter_id' => 42,
      'phase' => 'encounter',
      'round' => 2,
      'turn' => [
        'entity' => 'char-001',
        'actions_remaining' => 3,
        'reaction_available' => TRUE,
      ],
      'initiative_order' => [
        ['entity_id' => 'char-001', 'team' => 'player', 'name' => 'Hero'],
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [
        [
          'entity_instance_id' => 'char-001',
          'entity_ref' => ['content_id' => 99],
          'state' => ['metadata' => ['display_name' => 'Hero']],
        ],
        [
          'entity_instance_id' => 'npc-guide',
          'entity_ref' => ['content_id' => 'guide-1'],
          'state' => ['metadata' => ['display_name' => 'Guide']],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'talk',
      'actor' => 'char-001',
      'target' => 'npc-guide',
      'params' => ['message' => 'Hold the doorway.'],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertStringContainsString('Guide, Hold the doorway.', (string) ($response['result']['message'] ?? ''));
    $this->assertSame('The guide nods and braces the door.', $response['result']['gm_response']['message']);
    $this->assertSame(1, $response['result']['state_diff']['situational']['attack_bonus']);
    $this->assertSame('The guide nods and braces the door.', $response['result']['chat_response']['gm_response']['message']);
    $this->assertSame(['other' => ['success' => TRUE]], $response['result']['chat_response']['canonical_actions']);
    $this->assertNotEmpty($response['events']);

    $contract = $handler->getClientActionContract($game_state, $dungeon_data, 'char-001');
    $this->assertSame('encounter', $contract['phase']);
    $this->assertContains('talk', $contract['available_actions']);
    $this->assertTrue((bool) array_values(array_filter($contract['actions'], static fn(array $action): bool => $action['id'] === 'talk'))[0]['available']);
  }

  /**
   * Room-scene talk spends exactly one action and keeps turn advancement explicit.
   *
   * @covers ::processIntent
   */
  public function testProcessIntentTalkSpendsSingleActionWithoutAutoEndTurn(): void {
    $room_chat = $this->createMock(RoomChatService::class);
    $room_chat->expects($this->once())
      ->method('postMessage')
      ->willReturn([
        'gm_response' => ['message' => 'Eldric nods.'],
        'npc_interjections' => [],
        'state_diff' => [],
        'canonical_actions' => [],
        'mutations' => [],
      ]);

    $handler = $this->buildHandler($room_chat);
    $game_state = [
      'encounter_id' => NULL,
      'phase' => 'encounter',
      'round' => 1,
      'encounter_context' => ['room_id' => 'room-a'],
      'turn' => [
        'entity' => 'pc-1',
        'index' => 0,
        'actions_remaining' => 3,
        'reaction_available' => TRUE,
      ],
      'initiative_order' => [
        ['entity_id' => 'pc-1', 'team' => 'player', 'name' => 'Hero'],
        ['entity_id' => 'npc-1', 'team' => 'npc', 'name' => 'Eldric'],
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'entity_type' => 'player_character',
          'entity_ref' => ['content_id' => 501],
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 0, 'r' => 0]],
          'state' => ['metadata' => ['display_name' => 'Hero']],
        ],
        [
          'entity_instance_id' => 'npc-1',
          'entity_type' => 'npc',
          'entity_ref' => ['content_id' => 'eldric'],
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 1, 'r' => 0]],
          'state' => ['metadata' => ['display_name' => 'Eldric']],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'talk',
      'actor' => 'pc-1',
      'target' => 'npc-1',
      'params' => ['message' => 'Any work for me?'],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(1, $game_state['round']);
    $this->assertSame('pc-1', $game_state['turn']['entity']);
    $this->assertSame(0, $game_state['turn']['index']);
    $this->assertSame(2, $game_state['turn']['actions_remaining']);
    $event_types = array_map(static fn(array $event): string => (string) ($event['type'] ?? ''), $response['events']);
    $this->assertNotContains('auto_end_turn', $event_types);
  }

  /**
   * End turn triggers automatic search at the next actor's turn start.
   *
   * @covers ::processIntent
   */
  public function testProcessIntentEndTurnTriggersTurnStartSearch(): void {
    $exploration = $this->createMock(ExplorationPhaseHandler::class);
    $exploration->expects($this->once())
      ->method('processSearch')
      ->with(
        'pc-2',
        $this->callback(static fn(array $params): bool => ($params['search_mode'] ?? '') === 'automatic'
          && ($params['trigger'] ?? '') === 'turn_start'
          && ($params['room_id'] ?? '') === 'room-a'),
        $this->isType('array'),
        $this->isType('array'),
        42
      )
      ->willReturn([
        'searched' => TRUE,
        'roll' => 12,
        'total' => 16,
        'dc' => 15,
        'degree' => 'success',
        'discoveries' => [],
        'mutations' => [],
        'narration' => NULL,
      ]);

    $handler = $this->buildHandler(NULL, $exploration);
    $game_state = [
      'encounter_id' => NULL,
      'phase' => 'encounter',
      'round' => 1,
      'encounter_context' => ['room_id' => 'room-a'],
      'turn' => [
        'entity' => 'pc-1',
        'index' => 0,
        'actions_remaining' => 0,
        'reaction_available' => TRUE,
      ],
      'initiative_order' => [
        ['entity_id' => 'pc-1', 'team' => 'player', 'name' => 'Hero'],
        ['entity_id' => 'pc-2', 'team' => 'player', 'name' => 'Scout'],
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'entity_type' => 'player_character',
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 0, 'r' => 0]],
          'state' => ['metadata' => ['display_name' => 'Hero']],
        ],
        [
          'entity_instance_id' => 'pc-2',
          'entity_type' => 'player_character',
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 1, 'r' => 0]],
          'state' => ['metadata' => ['display_name' => 'Scout']],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'end_turn',
      'actor' => 'pc-1',
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('pc-2', $game_state['turn']['entity']);
    $event_types = array_map(static fn(array $event): string => (string) ($event['type'] ?? ''), $response['events']);
    $this->assertContains('turn_start', $event_types);
  }

  /**
   * Room-scene turn order is initiative-driven (roll + perception), not actor insertion order.
   *
   * @covers ::buildRoomEncounterTurnOrder
   */
  public function testBuildRoomEncounterTurnOrderUsesInitiativeRollAndPerception(): void {
    $number_generation = $this->createMock(NumberGenerationService::class);
    $number_generation->expects($this->exactly(3))
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturnOnConsecutiveCalls(5, 18, 10);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, NULL, $number_generation);
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'entity_type' => 'player_character',
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 0, 'r' => 0]],
          'state' => [
            'metadata' => [
              'display_name' => 'Hero',
              'stats' => ['perception' => 4],
            ],
          ],
        ],
        [
          'entity_instance_id' => 'npc-1',
          'entity_type' => 'npc',
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 1, 'r' => 0]],
          'state' => [
            'metadata' => [
              'display_name' => 'Bandit Captain',
              'stats' => ['perception' => 1],
            ],
          ],
        ],
        [
          'entity_instance_id' => 'npc-2',
          'entity_type' => 'npc',
          'placement' => ['room_id' => 'room-a', 'hex' => ['q' => 2, 'r' => 0]],
          'state' => [
            'metadata' => [
              'display_name' => 'Scout',
              'stats' => ['perception' => 2],
            ],
          ],
        ],
      ],
    ];

    $initiative = $this->invokeBuildRoomEncounterTurnOrder($handler, $dungeon_data, 'room-a', 'pc-1');

    $this->assertSame(['npc-1', 'npc-2', 'pc-1'], array_column($initiative, 'entity_id'));
    $this->assertSame(18, (int) $initiative[0]['initiative_roll']);
    $this->assertSame(19, (int) $initiative[0]['initiative_total']);
    $this->assertSame(10, (int) $initiative[1]['initiative_roll']);
    $this->assertSame(12, (int) $initiative[1]['initiative_total']);
    $this->assertSame(5, (int) $initiative[2]['initiative_roll']);
    $this->assertSame(9, (int) $initiative[2]['initiative_total']);
  }

  /**
   * Round-start narration is explicitly emitted as narrator turn 0.
   *
   * @covers ::buildRoundStartEvents
   */
  public function testBuildRoundStartEventsUsesNarratorTurnZeroPrefix(): void {
    $handler = $this->buildHandler();
    $game_state = [
      'round' => 1,
      'turn' => [
        'entity' => 'pc-1',
        'index' => 0,
        'actions_remaining' => 3,
      ],
      'encounter_context' => ['room_id' => 'room-a'],
    ];
    $dungeon_data = ['active_room_id' => 'room-a'];

    $events = $this->invokeBuildRoundStartEvents($handler, 1, $game_state, $dungeon_data, 42, 'room-a');

    $this->assertCount(1, $events);
    $this->assertSame('round_start', $events[0]['type'] ?? NULL);
    $this->assertStringContainsString('Turn 0: Actor Narrator:', (string) ($events[0]['narration'] ?? ''));
  }

  /**
   * Encounter casting spends focus from canonical state and mirrors projection.
   *
   * @covers ::processCastSpell
   */
  public function testProcessCastSpellConsumesCanonicalFocusPointsAndSyncsProjection(): void {
    $encounter_store = $this->createMock(CombatEncounterStore::class);
    $encounter_store->expects($this->exactly(2))
      ->method('loadEncounter')
      ->with(42)
      ->willReturn([
        'participants' => [
          [
            'id' => 17,
            'entity_id' => 'pc-1',
            'entity_ref' => json_encode([
              'focus_points' => 2,
              'state' => ['focus_points' => ['current' => 2, 'max' => 2]],
            ]),
          ],
        ],
      ]);
    $encounter_store->expects($this->once())
      ->method('updateParticipant')
      ->with(
        17,
        $this->callback(function (array $fields): bool {
          $entity_ref = json_decode((string) ($fields['entity_ref'] ?? ''), TRUE);
          return is_array($entity_ref)
            && (int) ($entity_ref['focus_points'] ?? -1) === 1
            && (int) ($entity_ref['state']['resources']['focusPoints']['current'] ?? -1) === 1
            && (int) ($entity_ref['state']['resources']['focusPoints']['max'] ?? -1) === 2;
        })
      );

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('castSpell')
      ->with('745', 'focus-blast', 0, TRUE, 42, 'pc-1')
      ->willReturn(['level' => 'focus', 'remaining' => 1]);
    $character_state->expects($this->once())
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturn([
        'resources' => [
          'focusPoints' => ['current' => 2, 'max' => 2],
          'spellSlots' => ['1' => ['current' => 2, 'max' => 2]],
        ],
      ]);

    $handler = $this->buildHandler(NULL, NULL, $encounter_store, $character_state);
    $game_state = [];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [
            'metadata' => [
              'campaign_character_id' => '745',
              'runtime_entity_id' => 'pc-1',
            ],
          ],
        ],
      ],
    ];
    $params = [
      'spell_name' => 'Focus Blast',
      'spell_id' => 'focus-blast',
      'is_focus_spell' => TRUE,
      'spell_level' => 1,
    ];

    $result = $this->invokeProcessCastSpell($handler, 42, 'pc-1', NULL, $params, $game_state, $dungeon_data, 42);

    $this->assertTrue($result['cast']);
    $this->assertSame(1, $result['focus_points_remaining']);
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['resources']['focusPoints']['current'] ?? -1));
  }

  /**
   * Encounter casting spends spell slots from canonical state and mirrors projection.
   *
   * @covers ::processCastSpell
   */
  public function testProcessCastSpellConsumesCanonicalSpellSlotsAndSyncsProjection(): void {
    $encounter_store = $this->createMock(CombatEncounterStore::class);
    $encounter_store->expects($this->exactly(2))
      ->method('loadEncounter')
      ->with(42)
      ->willReturn([
        'participants' => [
          [
            'id' => 19,
            'entity_id' => 'pc-1',
            'entity_ref' => json_encode([
              'casting_type' => 'spontaneous',
              'spell_slots' => [
                '3' => ['max' => 2, 'used' => 0],
              ],
            ]),
          ],
        ],
      ]);
    $encounter_store->expects($this->once())
      ->method('updateParticipant')
      ->with(
        19,
        $this->callback(function (array $fields): bool {
          $entity_ref = json_decode((string) ($fields['entity_ref'] ?? ''), TRUE);
          return is_array($entity_ref)
            && (int) ($entity_ref['spell_slots']['3']['max'] ?? -1) === 2
            && (int) ($entity_ref['spell_slots']['3']['current'] ?? -1) === 1
            && (int) ($entity_ref['spell_slots']['3']['used'] ?? -1) === 1;
        })
      );

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('castSpell')
      ->with('745', 'fireball', 3, FALSE, 42, 'pc-1')
      ->willReturn(['level' => 3, 'remaining' => 1]);
    $character_state->expects($this->once())
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturn([
        'resources' => [
          'spellSlots' => ['3' => ['current' => 2, 'max' => 2]],
          'focusPoints' => ['current' => 1, 'max' => 1],
        ],
      ]);

    $handler = $this->buildHandler(NULL, NULL, $encounter_store, $character_state);
    $game_state = [];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [
            'metadata' => [
              'campaign_character_id' => '745',
              'runtime_entity_id' => 'pc-1',
            ],
          ],
        ],
      ],
    ];
    $params = [
      'spell_name' => 'Fireball',
      'spell_id' => 'fireball',
      'spell_level' => 3,
      'cast_at_level' => 3,
      'is_focus_spell' => FALSE,
    ];

    $result = $this->invokeProcessCastSpell($handler, 42, 'pc-1', NULL, $params, $game_state, $dungeon_data, 42);

    $this->assertTrue($result['cast']);
    $this->assertSame(1, $result['slots_remaining']);
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['spell_slots']['3']['used'] ?? -1));
  }

  /**
   * Canonical slot authority must win when participant projection is stale.
   *
   * @covers ::processCastSpell
   */
  public function testProcessCastSpellUsesCanonicalSlotsWhenParticipantProjectionIsStale(): void {
    $encounter_store = $this->createMock(CombatEncounterStore::class);
    $encounter_store->expects($this->exactly(2))
      ->method('loadEncounter')
      ->with(42)
      ->willReturn([
        'participants' => [
          [
            'id' => 29,
            'entity_id' => 'pc-1',
            'entity_ref' => json_encode([
              'casting_type' => 'spontaneous',
              'spell_slots' => [
                '3' => ['max' => 0, 'used' => 0],
              ],
            ]),
          ],
        ],
      ]);
    $encounter_store->expects($this->once())
      ->method('updateParticipant')
      ->with(
        29,
        $this->callback(function (array $fields): bool {
          $entity_ref = json_decode((string) ($fields['entity_ref'] ?? ''), TRUE);
          return is_array($entity_ref)
            && (int) ($entity_ref['spell_slots']['3']['max'] ?? -1) === 2
            && (int) ($entity_ref['spell_slots']['3']['current'] ?? -1) === 1
            && (int) ($entity_ref['spell_slots']['3']['used'] ?? -1) === 1;
        })
      );

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('castSpell')
      ->with('745', 'fireball', 3, FALSE, 42, 'pc-1')
      ->willReturn(['level' => 3, 'remaining' => 1]);
    $character_state->expects($this->once())
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturn([
        'resources' => [
          'spellSlots' => ['3' => ['current' => 2, 'max' => 2]],
          'focusPoints' => ['current' => 1, 'max' => 1],
        ],
      ]);

    $handler = $this->buildHandler(NULL, NULL, $encounter_store, $character_state);
    $game_state = [];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [
            'metadata' => [
              'campaign_character_id' => '745',
              'runtime_entity_id' => 'pc-1',
            ],
          ],
        ],
      ],
    ];
    $params = [
      'spell_name' => 'Fireball',
      'spell_id' => 'fireball',
      'spell_level' => 3,
      'cast_at_level' => 3,
      'is_focus_spell' => FALSE,
    ];

    $result = $this->invokeProcessCastSpell($handler, 42, 'pc-1', NULL, $params, $game_state, $dungeon_data, 42);

    $this->assertTrue($result['cast']);
    $this->assertSame(1, $result['slots_remaining']);
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['spell_slots']['3']['used'] ?? -1));
  }

  /**
   * Participant canonical identity can drive casts even when dungeon entity is missing.
   *
   * @covers ::processCastSpell
   */
  public function testProcessCastSpellUsesParticipantCanonicalIdentityWithoutDungeonEntity(): void {
    $encounter_store = $this->createMock(CombatEncounterStore::class);
    $encounter_store->expects($this->exactly(2))
      ->method('loadEncounter')
      ->with(42)
      ->willReturn([
        'participants' => [
          [
            'id' => 31,
            'entity_id' => 'pc-1',
            'entity_ref' => json_encode([
              'state' => [
                'metadata' => [
                  'campaign_character_id' => '745',
                  'runtime_entity_id' => 'pc-1',
                ],
              ],
              'casting_type' => 'spontaneous',
              'spell_slots' => [
                '3' => ['max' => 0, 'used' => 0],
              ],
            ]),
          ],
        ],
      ]);
    $encounter_store->expects($this->once())
      ->method('updateParticipant')
      ->with(
        31,
        $this->callback(function (array $fields): bool {
          $entity_ref = json_decode((string) ($fields['entity_ref'] ?? ''), TRUE);
          return is_array($entity_ref)
            && (int) ($entity_ref['spell_slots']['3']['max'] ?? -1) === 2
            && (int) ($entity_ref['spell_slots']['3']['current'] ?? -1) === 1
            && (int) ($entity_ref['spell_slots']['3']['used'] ?? -1) === 1;
        })
      );

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturn([
        'resources' => [
          'spellSlots' => ['3' => ['current' => 2, 'max' => 2]],
          'focusPoints' => ['current' => 1, 'max' => 1],
        ],
      ]);
    $character_state->expects($this->once())
      ->method('castSpell')
      ->with('745', 'fireball', 3, FALSE, 42, 'pc-1')
      ->willReturn(['level' => 3, 'remaining' => 1]);

    $handler = $this->buildHandler(NULL, NULL, $encounter_store, $character_state);
    $game_state = [];
    $dungeon_data = ['entities' => []];
    $params = [
      'spell_name' => 'Fireball',
      'spell_id' => 'fireball',
      'spell_level' => 3,
      'cast_at_level' => 3,
      'is_focus_spell' => FALSE,
    ];

    $result = $this->invokeProcessCastSpell($handler, 42, 'pc-1', NULL, $params, $game_state, $dungeon_data, 42);

    $this->assertTrue($result['cast']);
    $this->assertSame(1, $result['slots_remaining']);
  }

  /**
   * Spell resource mutations require canonical character identity for focus casts.
   *
   * @covers ::processCastSpell
   */
  public function testProcessCastSpellRejectsFocusSpendWithoutCanonicalIdentity(): void {
    $encounter_store = $this->createMock(CombatEncounterStore::class);
    $encounter_store->expects($this->once())
      ->method('loadEncounter')
      ->with(42)
      ->willReturn([
        'participants' => [
          [
            'id' => 41,
            'entity_id' => 'pc-1',
            'entity_ref' => json_encode([
              'focus_points' => 2,
              'state' => ['focus_points' => ['current' => 2, 'max' => 2]],
            ]),
          ],
        ],
      ]);
    $encounter_store->expects($this->never())->method('updateParticipant');

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->never())->method('castSpell');

    $handler = $this->buildHandler(NULL, NULL, $encounter_store, $character_state);
    $game_state = [];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [],
        ],
      ],
    ];
    $params = [
      'spell_name' => 'Focus Blast',
      'spell_id' => 'focus-blast',
      'is_focus_spell' => TRUE,
      'spell_level' => 1,
    ];

    $result = $this->invokeProcessCastSpell($handler, 42, 'pc-1', NULL, $params, $game_state, $dungeon_data, 42);

    $this->assertFalse($result['cast']);
    $this->assertSame('Canonical character sheet is required for spellcasting resource updates.', $result['error']);
  }

  /**
   * Spell resource mutations require canonical character identity for slot casts.
   *
   * @covers ::processCastSpell
   */
  public function testProcessCastSpellRejectsSlotSpendWithoutCanonicalIdentity(): void {
    $encounter_store = $this->createMock(CombatEncounterStore::class);
    $encounter_store->expects($this->once())
      ->method('loadEncounter')
      ->with(42)
      ->willReturn([
        'participants' => [
          [
            'id' => 43,
            'entity_id' => 'pc-1',
            'entity_ref' => json_encode([
              'spell_slots' => [
                '3' => ['max' => 2, 'used' => 0],
              ],
            ]),
          ],
        ],
      ]);
    $encounter_store->expects($this->never())->method('updateParticipant');

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->never())->method('castSpell');

    $handler = $this->buildHandler(NULL, NULL, $encounter_store, $character_state);
    $game_state = [];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [],
        ],
      ],
    ];
    $params = [
      'spell_name' => 'Fireball',
      'spell_id' => 'fireball',
      'spell_level' => 3,
      'cast_at_level' => 3,
      'is_focus_spell' => FALSE,
    ];

    $result = $this->invokeProcessCastSpell($handler, 42, 'pc-1', NULL, $params, $game_state, $dungeon_data, 42);

    $this->assertFalse($result['cast']);
    $this->assertSame('Canonical character sheet is required for spellcasting resource updates.', $result['error']);
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
   * Friendly empathetic NPCs adjacent to PCs should try to de-escalate.
   *
   * @covers ::chooseFallbackAction
   * @covers ::chooseFallbackTarget
   */
  public function testChooseFallbackActionUsesFriendlyPsychologyToTalk(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [42, 'npc_friendly', [
        'display_name' => 'Friendly Scout',
        'attitude' => 'friendly',
        'personality_axes' => ['boldness' => 5, 'empathy' => 8, 'discipline' => 5, 'cunning' => 4, 'motivation' => 6],
        'motivations' => 'Protect the camp',
        'fears' => '',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 42,
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_friendly',
          'team' => 'enemy',
          'hp' => 18,
          'max_hp' => 20,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 24,
          'max_hp' => 24,
          'position_q' => 1,
          'position_r' => 0,
        ],
      ],
    ];

    $action = $this->invokeChooseFallbackAction($handler, 'npc-1', $game_state, 42);
    $target = $this->invokeChooseFallbackTarget($handler, 'npc-1', $game_state, 42, $action);

    $this->assertSame('talk', $action);
    $this->assertSame('pc-1', $target);
  }

  /**
   * Self-preservation motivations should bias wounded NPCs toward movement.
   *
   * @covers ::chooseFallbackAction
   */
  public function testChooseFallbackActionUsesSurvivalMotivationWhenWounded(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [77, 'npc_coward', [
        'display_name' => 'Shaken Raider',
        'attitude' => 'unfriendly',
        'personality_axes' => ['boldness' => 4, 'empathy' => 3, 'discipline' => 3, 'cunning' => 5, 'motivation' => 6],
        'motivations' => 'Survive and escape this fight',
        'fears' => 'Getting caught in conflict',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 77,
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_coward',
          'team' => 'enemy',
          'hp' => 3,
          'max_hp' => 20,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 24,
          'max_hp' => 24,
          'position_q' => 1,
          'position_r' => 0,
        ],
      ],
    ];

    $action = $this->invokeChooseFallbackAction($handler, 'npc-1', $game_state, 77);
    $this->assertSame('stride', $action);
  }

  /**
   * High-cunning fallback targeting should prefer the weakest adjacent player.
   *
   * @covers ::chooseFallbackTarget
   */
  public function testChooseFallbackTargetPrefersWeakestAdjacentWhenCunningHigh(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [13, 'npc_hunter', [
        'display_name' => 'Hunter',
        'attitude' => 'hostile',
        'personality_axes' => ['boldness' => 6, 'empathy' => 2, 'discipline' => 7, 'cunning' => 8, 'motivation' => 7],
        'motivations' => 'Win quickly',
        'fears' => '',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 13,
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_hunter',
          'team' => 'enemy',
          'hp' => 18,
          'max_hp' => 18,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-strong',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 22,
          'max_hp' => 24,
          'position_q' => 1,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-weak',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 4,
          'max_hp' => 20,
          'position_q' => 0,
          'position_r' => 1,
        ],
      ],
    ];

    $target = $this->invokeChooseFallbackTarget($handler, 'npc-1', $game_state, 13, 'strike');
    $this->assertSame('pc-weak', $target);
  }

  /**
   * Treasure-oriented goals should bias non-adjacent actors toward interact.
   *
   * @covers ::chooseFallbackAction
   */
  public function testChooseFallbackActionUsesTreasureGoalWhenNotAdjacent(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [19, 'npc_looter', [
        'display_name' => 'Looter',
        'attitude' => 'hostile',
        'character_sheet' => [
          'goals' => ['Gain Treasure', 'Grab valuables and leave'],
        ],
        'personality_axes' => ['boldness' => 5, 'empathy' => 3, 'discipline' => 4, 'cunning' => 5, 'motivation' => 7],
        'motivations' => 'Get rich',
        'fears' => '',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 19,
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_looter',
          'team' => 'enemy',
          'hp' => 16,
          'max_hp' => 16,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 22,
          'max_hp' => 22,
          'position_q' => 3,
          'position_r' => 0,
        ],
      ],
    ];

    $action = $this->invokeChooseFallbackAction($handler, 'npc-1', $game_state, 19);
    $this->assertSame('interact', $action);
  }

  /**
   * NPC context should include structured psychology profile for AI decisions.
   *
   * @covers ::buildNpcContext
   */
  public function testBuildNpcContextIncludesCurrentActorProfile(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [55, 'npc_profiled', [
        'display_name' => 'Profiled NPC',
        'attitude' => 'indifferent',
        'personality_traits' => 'cautious and calculating',
        'personality_axes' => ['boldness' => 4, 'empathy' => 5, 'discipline' => 7, 'cunning' => 8, 'motivation' => 7],
        'motivations' => 'Protect the relic',
        'fears' => 'Losing control',
        'bonds' => 'Temple wardens',
        'character_sheet' => [
          'goals' => ['Protect the relic', 'Gain XP', 'Gain Treasure'],
        ],
        'inner_monologue' => [
          [
            'thought' => 'If they touch the relic, I strike.',
            'emotion' => 'determined',
            'event_type' => 'observation',
          ],
        ],
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 55,
      'encounter_id' => 901,
      'round' => 2,
      'turn' => ['actions_remaining' => 3],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_profiled',
          'name' => 'Profiled NPC',
          'team' => 'enemy',
          'hp' => 16,
          'max_hp' => 18,
          'ac' => 18,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'name' => 'Hero',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 30,
          'max_hp' => 30,
          'position_q' => 2,
          'position_r' => 0,
        ],
      ],
    ];

    $context = $this->invokeBuildNpcContext($handler, 'npc-1', $game_state, []);
    $this->assertSame('Protect the relic', $context['current_actor_profile']['motivations']);
    $this->assertTrue(in_array('Gain XP', $context['current_actor_profile']['goals'], TRUE));
    $this->assertTrue(in_array('Gain Treasure', $context['current_actor_profile']['goals'], TRUE));
    $this->assertSame('determined', $context['current_actor_profile']['latest_thought']['emotion']);
    $this->assertStringContainsString('Fighting motivation: Protect the relic', (string) $context['npc_psychology']);
    $this->assertStringContainsString('Goals: Protect the relic, Gain XP, Gain Treasure', (string) $context['npc_psychology']);
    $this->assertSame('finish_weakest', $context['current_actor_tactical_intent']['intent'] ?? NULL);
    $this->assertContains('cast_spell', $context['allowed_actions']);
    $this->assertTrue(($context['actions_available_to_me_this_turn']['is_active_turn_actor'] ?? FALSE));
    $this->assertSame(3, $context['actions_available_to_me_this_turn']['actions_remaining'] ?? NULL);
    $this->assertContains('cast_spell', $context['actions_available_to_me_this_turn']['available_actions'] ?? []);
    $this->assertSame('encounter', $context['actions_available_to_me_this_turn']['action_contract']['phase'] ?? NULL);
    $this->assertTrue(is_array($context['actions_available_to_me_this_turn']['action_contract']['actions'] ?? NULL));
  }

  /**
   * Tactical intent contracts should keep de-escalation intent across all actions.
   *
   * @covers ::buildNpcTacticalIntentContract
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanKeepsDeescalationIntentAcrossThreeActions(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [42, 'npc_friendly', [
        'display_name' => 'Friendly Scout',
        'attitude' => 'friendly',
        'personality_axes' => ['boldness' => 5, 'empathy' => 8, 'discipline' => 5, 'cunning' => 4, 'motivation' => 6],
        'motivations' => 'Protect the camp',
        'fears' => '',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 42,
      'turn' => ['actions_remaining' => 3],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_friendly',
          'team' => 'enemy',
          'hp' => 18,
          'max_hp' => 20,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 24,
          'max_hp' => 24,
          'position_q' => 1,
          'position_r' => 0,
        ],
      ],
    ];

    $plan = $this->invokeBuildNpcTurnPlan($handler, 'npc-1', $game_state, 42);
    $this->assertSame('deescalate', $plan['intent_contract']['intent'] ?? NULL);
    $this->assertCount(3, $plan['steps']);
    $this->assertSame('talk', $plan['steps'][0]['action_type'] ?? NULL);
    $this->assertSame('deescalate', $plan['steps'][0]['decision_basis']['intent'] ?? NULL);
    $this->assertSame('deescalate', $plan['steps'][1]['decision_basis']['intent'] ?? NULL);
    $this->assertSame('deescalate', $plan['steps'][2]['decision_basis']['intent'] ?? NULL);
  }

  /**
   * Self-preservation plans should remain retreat/reposition focused across turn actions.
   *
   * @covers ::buildNpcTacticalIntentContract
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanKeepsSelfPreservationAcrossThreeActions(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [77, 'npc_coward', [
        'display_name' => 'Shaken Raider',
        'attitude' => 'unfriendly',
        'personality_axes' => ['boldness' => 4, 'empathy' => 3, 'discipline' => 3, 'cunning' => 5, 'motivation' => 6],
        'motivations' => 'Survive and escape this fight',
        'fears' => 'Getting caught in conflict',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 77,
      'turn' => ['actions_remaining' => 3],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_coward',
          'team' => 'enemy',
          'hp' => 3,
          'max_hp' => 20,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 24,
          'max_hp' => 24,
          'position_q' => 1,
          'position_r' => 0,
        ],
      ],
    ];

    $plan = $this->invokeBuildNpcTurnPlan($handler, 'npc-1', $game_state, 77);
    $this->assertSame('self_preserve', $plan['intent_contract']['intent'] ?? NULL);
    $this->assertCount(3, $plan['steps']);
    $this->assertSame('stride', $plan['steps'][0]['action_type'] ?? NULL);
    $this->assertSame('stride', $plan['steps'][1]['action_type'] ?? NULL);
    $this->assertSame('self_preserve', $plan['steps'][2]['decision_basis']['intent'] ?? NULL);
  }

  /**
   * High-cunning plans should keep weakest-target prioritization consistent.
   *
   * @covers ::buildNpcTacticalIntentContract
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanKeepsWeakestTargetContinuityWhenCunningHigh(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [13, 'npc_hunter', [
        'display_name' => 'Hunter',
        'attitude' => 'hostile',
        'personality_axes' => ['boldness' => 6, 'empathy' => 2, 'discipline' => 7, 'cunning' => 8, 'motivation' => 7],
        'motivations' => 'Win quickly',
        'fears' => '',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 13,
      'turn' => ['actions_remaining' => 3],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_hunter',
          'team' => 'enemy',
          'hp' => 18,
          'max_hp' => 18,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-strong',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 22,
          'max_hp' => 24,
          'position_q' => 1,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-weak',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 4,
          'max_hp' => 20,
          'position_q' => 0,
          'position_r' => 1,
        ],
      ],
    ];

    $plan = $this->invokeBuildNpcTurnPlan($handler, 'npc-1', $game_state, 13);
    $this->assertSame('finish_weakest', $plan['intent_contract']['intent'] ?? NULL);
    $this->assertCount(3, $plan['steps']);
    $this->assertSame('pc-weak', $plan['steps'][0]['target'] ?? NULL);
    $this->assertSame('pc-weak', $plan['steps'][1]['target'] ?? NULL);
  }

  /**
   * Tactical intent basis should normalize malformed personality-axis values.
   *
   * @covers ::buildNpcTacticalIntentContract
   */
  public function testBuildNpcTacticalIntentContractNormalizesAxes(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [88, 'npc_malformed', [
        'display_name' => 'Malformed Profile NPC',
        'attitude' => 'hostile',
        'personality_axes' => ['boldness' => 99, 'empathy' => -5, 'discipline' => 7],
        'motivations' => 'Win quickly',
        'fears' => '',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 88,
      'turn' => ['actions_remaining' => 3],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_malformed',
          'team' => 'enemy',
          'hp' => 18,
          'max_hp' => 18,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 20,
          'max_hp' => 20,
          'position_q' => 1,
          'position_r' => 0,
        ],
      ],
    ];

    $contract = $this->invokeBuildNpcTacticalIntentContract($handler, 'npc-1', $game_state, 88);
    $axes = $contract['decision_basis']['axes'] ?? [];
    $this->assertSame(10, $axes['boldness'] ?? NULL);
    $this->assertSame(0, $axes['empathy'] ?? NULL);
    $this->assertSame(7, $axes['discipline'] ?? NULL);
    $this->assertSame(5, $axes['cunning'] ?? NULL);
  }

  /**
   * Missing psychology profiles should not default into treasure-seek behavior.
   *
   * @covers ::buildNpcTacticalIntentContract
   */
  public function testBuildNpcTacticalIntentContractWithoutProfileUsesAggressiveBaseline(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturn(NULL);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 91,
      'turn' => ['actions_remaining' => 3],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_unknown',
          'team' => 'enemy',
          'hp' => 16,
          'max_hp' => 16,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 24,
          'max_hp' => 24,
          'position_q' => 3,
          'position_r' => 0,
        ],
      ],
    ];

    $contract = $this->invokeBuildNpcTacticalIntentContract($handler, 'npc-1', $game_state, 91);
    $this->assertSame('aggressive_engage', $contract['intent'] ?? NULL);
    $this->assertSame(['stride', 'strike', 'strike'], $contract['action_sequence'] ?? []);
    $this->assertFalse((bool) ($contract['decision_basis']['has_adjacent_player'] ?? TRUE));
    $this->assertFalse((bool) ($contract['decision_basis']['profile_present'] ?? TRUE));
  }

  /**
   * NPC encounter outputs should include decision metadata for observability.
   *
   * @covers ::autoPlayNpcTurn
   */
  public function testAutoPlayNpcTurnAddsDecisionMetadataToEvents(): void {
    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('loadProfile')->willReturnMap([
      [42, 'npc_friendly', [
        'display_name' => 'Friendly Scout',
        'attitude' => 'friendly',
        'personality_axes' => ['boldness' => 5, 'empathy' => 8, 'discipline' => 5, 'cunning' => 4, 'motivation' => 6],
        'motivations' => 'Protect the camp',
        'fears' => '',
      ]],
    ]);

    $handler = $this->buildHandler(NULL, NULL, NULL, NULL, $psychology);
    $game_state = [
      'campaign_id' => 42,
      'round' => 2,
      'turn' => ['entity' => 'npc-1', 'actions_remaining' => 3],
      'encounter_context' => ['room_id' => 'room-a'],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_friendly',
          'name' => 'Friendly Scout',
          'team' => 'enemy',
          'hp' => 18,
          'max_hp' => 20,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'name' => 'Hero',
          'team' => 'player',
          'is_defeated' => FALSE,
          'hp' => 24,
          'max_hp' => 24,
          'position_q' => 1,
          'position_r' => 0,
        ],
      ],
    ];
    $dungeon_data = ['active_room_id' => 'room-a'];

    $result = $this->invokeAutoPlayNpcTurn($handler, 99, 'npc-1', $game_state, $dungeon_data, 42);
    $events = $result['events'] ?? [];

    $this->assertNotEmpty($events);
    $first = $events[0] ?? [];
    $payload = is_array($first['data'] ?? NULL) ? $first['data'] : [];
    $this->assertNotSame('', trim((string) ($payload['decision_reason'] ?? '')));
    $this->assertTrue(is_array($payload['decision_basis'] ?? NULL));

    $last = $events[count($events) - 1] ?? [];
    $last_payload = is_array($last['data'] ?? NULL) ? $last['data'] : [];
    $this->assertSame('npc_choose_not_to_act', $last['type'] ?? NULL);
    $this->assertNotSame('', trim((string) ($last_payload['decision_reason'] ?? '')));
    $this->assertTrue(is_array($last_payload['decision_basis'] ?? NULL));
  }

  /**
   * Stride position sync updates canonical participant row by participant id.
   *
   * @covers ::processStride
   */
  public function testProcessStrideSyncsParticipantPositionByParticipantId(): void {
    $encounter_store = $this->createMock(CombatEncounterStore::class);
    $encounter_store->expects($this->once())
      ->method('loadEncounter')
      ->with(88)
      ->willReturn([
        'participants' => [
          [
            'id' => 321,
            'entity_id' => 'actor-1',
          ],
        ],
      ]);
    $encounter_store->expects($this->once())
      ->method('updateParticipant')
      ->with(321, [
        'position_q' => 4,
        'position_r' => 5,
      ]);

    $handler = $this->buildHandler(NULL, NULL, $encounter_store);
    $game_state = [
      'active_room_id' => 'room-a',
      'turn' => [],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'actor-1',
          'placement' => ['hex' => ['q' => 1, 'r' => 1]],
        ],
      ],
    ];

    $result = $this->invokeProcessStride(
      $handler,
      88,
      'actor-1',
      [
        'to_hex' => ['q' => 4, 'r' => 5],
        'is_forced' => TRUE,
      ],
      $game_state,
      $dungeon_data,
      42
    );

    $this->assertTrue($result['stride']);
    $this->assertSame(['q' => 4, 'r' => 5], $dungeon_data['entities'][0]['placement']['hex']);
  }

  /**
   * Builds an EncounterPhaseHandler with lightweight mocks.
   */
  private function buildHandler(
    ?RoomChatService $room_chat = NULL,
    ?ExplorationPhaseHandler $exploration = NULL,
    ?CombatEncounterStore $encounter_store = NULL,
    ?CharacterStateService $character_state = NULL,
    ?NpcPsychologyService $psychology_service = NULL,
    ?NumberGenerationService $number_generation_service = NULL
  ): EncounterPhaseHandler {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $config = $this->createMock(\Drupal\Core\Config\ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['encounter_ai_npc_autoplay_enabled', FALSE],
      ['encounter_ai_retry_attempts', 1],
      ['encounter_ai_recommendation_max_tokens', 800],
      ['encounter_ai_narration_max_tokens', 500],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('dungeoncrawler_content.settings')
      ->willReturn($config);

    return new EncounterPhaseHandler(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(CombatEngine::class),
      $this->createMock(ActionProcessor::class),
      $encounter_store ?? $this->createMock(CombatEncounterStore::class),
      $this->createMock(HPManager::class),
      $this->createMock(ConditionManager::class),
      $this->createMock(CombatCalculator::class),
      $number_generation_service ?? $this->createMock(NumberGenerationService::class),
      $this->createMock(EncounterAiIntegrationService::class),
      $this->createMock(RulesEngine::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(AiGmService::class),
      $config_factory,
      $psychology_service ?? $this->createMock(NpcPsychologyService::class),
      NULL,
      NULL,
      NULL,
      NULL,
      $character_state ?? $this->createMock(CharacterStateService::class),
      NULL,
      $room_chat ?? $this->createMock(RoomChatService::class),
      $exploration
    );
  }

  /**
   * Invoke protected room-scene initiative builder.
   */
  private function invokeBuildRoomEncounterTurnOrder(
    EncounterPhaseHandler $handler,
    array $dungeon_data,
    string $room_id,
    ?string $actor_id = NULL
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'buildRoomEncounterTurnOrder');
    $method->setAccessible(TRUE);
    return $method->invoke($handler, $dungeon_data, $room_id, $actor_id);
  }

  /**
   * Invoke protected round-start event builder.
   */
  private function invokeBuildRoundStartEvents(
    EncounterPhaseHandler $handler,
    int $round,
    array $game_state,
    array $dungeon_data,
    int $campaign_id,
    ?string $room_id = NULL
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'buildRoundStartEvents');
    $method->setAccessible(TRUE);
    return $method->invoke($handler, $round, $game_state, $dungeon_data, $campaign_id, $room_id);
  }

  /**
   * Invoke protected spellcast processor with by-reference state payloads.
   */
  private function invokeProcessCastSpell(
    EncounterPhaseHandler $handler,
    int $encounter_id,
    string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'processCastSpell');
    $method->setAccessible(TRUE);
    $args = [$encounter_id, $actor_id, $target_id, $params, &$game_state, &$dungeon_data, $campaign_id];
    return $method->invokeArgs($handler, $args);
  }

  /**
   * Invoke protected stride processor with by-reference state payloads.
   */
  private function invokeProcessStride(
    EncounterPhaseHandler $handler,
    int $encounter_id,
    string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'processStride');
    $method->setAccessible(TRUE);
    $args = [$encounter_id, $actor_id, $params, &$game_state, &$dungeon_data, $campaign_id];
    return $method->invokeArgs($handler, $args);
  }

  /**
   * Invoke protected NPC fallback action selector.
   */
  private function invokeChooseFallbackAction(
    EncounterPhaseHandler $handler,
    string $entity_id,
    array $game_state,
    int $campaign_id
  ): string {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'chooseFallbackAction');
    $method->setAccessible(TRUE);
    return (string) $method->invoke($handler, $entity_id, $game_state, $campaign_id);
  }

  /**
   * Invoke protected NPC fallback target selector.
   */
  private function invokeChooseFallbackTarget(
    EncounterPhaseHandler $handler,
    string $entity_id,
    array $game_state,
    int $campaign_id,
    string $action_type
  ): ?string {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'chooseFallbackTarget');
    $method->setAccessible(TRUE);
    $target = $method->invoke($handler, $entity_id, $game_state, $campaign_id, $action_type);
    return is_string($target) ? $target : NULL;
  }

  /**
   * Invoke protected NPC context builder.
   *
   * @return array<string, mixed>
   *   Context envelope for recommendation.
   */
  private function invokeBuildNpcContext(
    EncounterPhaseHandler $handler,
    string $entity_id,
    array $game_state,
    array $dungeon_data
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'buildNpcContext');
    $method->setAccessible(TRUE);
    $context = $method->invoke($handler, $entity_id, $game_state, $dungeon_data);
    return is_array($context) ? $context : [];
  }

  /**
   * Invoke protected NPC intent contract builder.
   */
  private function invokeBuildNpcTacticalIntentContract(
    EncounterPhaseHandler $handler,
    string $entity_id,
    array $game_state,
    int $campaign_id
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'buildNpcTacticalIntentContract');
    $method->setAccessible(TRUE);
    $contract = $method->invoke($handler, $entity_id, $game_state, $campaign_id);
    return is_array($contract) ? $contract : [];
  }

  /**
   * Invoke protected NPC turn-plan builder.
   */
  private function invokeBuildNpcTurnPlan(
    EncounterPhaseHandler $handler,
    string $entity_id,
    array $game_state,
    int $campaign_id
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'buildNpcTurnPlan');
    $method->setAccessible(TRUE);
    $plan = $method->invoke($handler, $entity_id, $game_state, $campaign_id, NULL);
    return is_array($plan) ? $plan : [];
  }

  /**
   * Invoke protected NPC autoplay handler.
   */
  private function invokeAutoPlayNpcTurn(
    EncounterPhaseHandler $handler,
    int $encounter_id,
    string $entity_id,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $method = new \ReflectionMethod(EncounterPhaseHandler::class, 'autoPlayNpcTurn');
    $method->setAccessible(TRUE);
    $args = [$encounter_id, $entity_id, &$game_state, &$dungeon_data, $campaign_id];
    $result = $method->invokeArgs($handler, $args);
    return is_array($result) ? $result : [];
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
      $this->createMock(NpcPsychologyService::class),
      NULL,
      NULL,
      NULL,
      NULL,
      $this->createMock(CharacterStateService::class),
      NULL,
      $this->createMock(RoomChatService::class)
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

      protected function processEndTurn(?int $encounter_id, ?string $actor_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
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
      $this->createMock(NpcPsychologyService::class),
      NULL,
      NULL,
      NULL,
      NULL,
      $this->createMock(CharacterStateService::class),
      NULL,
      $this->createMock(RoomChatService::class)
    ) extends EncounterPhaseHandler {
      public function exposedBuildParticipantList(array $dungeon_data, string $room_id, array $enemies = []): array {
        return $this->buildParticipantList($dungeon_data, $room_id, $enemies);
      }
    };
  }

}
