<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\AiSessionManager;
use Drupal\dungeoncrawler_content\Service\CanonicalActionRegistryService;
use Drupal\dungeoncrawler_content\Service\ChatChannelManager;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\GmOrchestrationBrokerService;
use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;
use Drupal\dungeoncrawler_content\Service\MapGeneratorService;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers NPC room-chat resolution edge cases.
 *
 * @group dungeoncrawler_content
 * @group room_chat
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChatService
 */
class RoomChatServiceNpcResolutionTest extends UnitTestCase {

  private Connection $database;
  private NpcPsychologyService $psychologyService;
  private TestableRoomChatService $roomChatService;

  protected function setUp(): void {
    parent::setUp();

    $this->database = $this->createMock(Connection::class);
    $this->psychologyService = $this->createMock(NpcPsychologyService::class);

    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $this->roomChatService = new TestableRoomChatService(
      $this->database,
      $this->createMock(\Drupal\dungeoncrawler_content\Service\DungeonStateService::class),
      $loggerFactory,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(AIApiService::class),
      $this->createMock(PromptManager::class),
      $this->createMock(GameplayActionProcessor::class),
      $this->createMock(AiSessionManager::class),
      $this->createMock(ChatChannelManager::class),
      $this->psychologyService,
      $this->createMock(NarrationEngine::class),
      $this->createMock(ChatSessionManager::class),
      $this->createMock(MapGeneratorService::class),
      $this->createMock(CanonicalActionRegistryService::class),
      $this->createMock(GmOrchestrationBrokerService::class),
    );
  }

  /**
   * @covers ::resolveCampaignCharacterNpcProfile
   */
  public function testResolveCampaignCharacterNpcProfileHandlesNpcPrefixedInstanceIds(): void {
    $profile = [
      'display_name' => 'Marta the Scholar',
      'attitude' => 'indifferent',
    ];

    $this->psychologyService->expects($this->exactly(2))
      ->method('loadProfile')
      ->willReturnMap([
        [17, 'npc_scholar_npc', NULL],
        [17, 'scholar_npc', $profile],
      ]);

    $resolved = $this->roomChatService->publicResolveCampaignCharacterNpcProfile(17, (object) [
      'instance_id' => 'npc_scholar_npc',
      'name' => 'Marta the Scholar',
      'role' => 'npc',
    ]);

    $this->assertSame('scholar_npc', $resolved['entity_ref']);
    $this->assertSame($profile, $resolved['profile']);
  }

  /**
   * @covers ::resolveDirectlyAddressedNpc
   */
  public function testResolveDirectlyAddressedNpcMatchesMinorMisspelling(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
          'attitude' => 'indifferent',
        ],
      ],
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
        ],
      ],
    ];

    $resolved = $this->roomChatService->publicResolveDirectlyAddressedNpc(
      $roomNpcs,
      "yea, say something Martha. I'm testing for a defect in the system"
    );

    $this->assertNotNull($resolved);
    $this->assertSame('scholar_npc', $resolved['entity_ref']);
    $this->assertSame('Marta the Scholar', $resolved['profile']['display_name']);
  }

  /**
   * @covers ::resolveSelectedRoomNpcs
   */
  public function testResolveSelectedRoomNpcsParsesMultipleSpeakers(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
          'attitude' => 'indifferent',
        ],
      ],
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
        ],
      ],
    ];

    $resolved = $this->roomChatService->publicResolveSelectedRoomNpcs(
      $roomNpcs,
      '{"speakers":["Eldric","Martha"]}'
    );

    $this->assertCount(2, $resolved);
    $resolved_refs = array_values(array_map(
      static fn(array $npc): string => $npc['entity_ref'],
      $resolved
    ));
    sort($resolved_refs);
    $this->assertSame(['scholar_npc', 'tavern_keeper'], $resolved_refs);
  }

  /**
   * @covers ::resolveNamedRoomNpc
   */
  public function testResolveNamedRoomNpcReturnsNullForAmbiguousTie(): void {
    $resolved = $this->roomChatService->publicResolveNamedRoomNpc(
      [
        [
          'entity_ref' => 'marta',
          'profile' => [
            'display_name' => 'Marta',
          ],
        ],
        [
          'entity_ref' => 'marla',
          'profile' => [
            'display_name' => 'Marla',
          ],
        ],
      ],
      'Marra'
    );

    $this->assertNull($resolved);
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanPrioritizesDirectlyAddressedNpc(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
          'attitude' => 'indifferent',
        ],
      ],
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'Eldric, answer me plainly.',
      'The tavern settles into a tense quiet.'
    );

    $this->assertSame('tavern_keeper', $plan['directly_addressed_npc']['entity_ref']);
    $this->assertFalse($plan['gm_addressed']);
    $this->assertCount(2, $plan['ordered_npcs']);
    $this->assertSame('scholar_npc', $plan['ordered_npcs'][0]['entity_ref']);
    $this->assertSame('tavern_keeper', $plan['ordered_npcs'][1]['entity_ref']);
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanSuppressesNpcTurnsForExplicitGmAddress(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => [
          'display_name' => 'Eldric',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'GM, what does Burasco know about this mark?',
      'The lantern light catches on the bar top.'
    );

    $this->assertTrue($plan['gm_addressed']);
    $this->assertSame([], $plan['ordered_npcs']);
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanUsesInitiativeOrderBeforeRoomFallback(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => [
          'display_name' => 'Eldric',
        ],
      ],
      [
        'entity_ref' => 'guard_captain',
        'profile' => [
          'display_name' => 'Captain Hadrik',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'Tell me what happened here.',
      'The room stills around the question.',
      [
        'game_state' => [
          'initiative_order' => [
            ['entity_id' => 'guard_captain', 'name' => 'Captain Hadrik', 'room_id' => 'room-tavern'],
            ['entity_id' => 'tavern_keeper', 'name' => 'Eldric', 'room_id' => 'room-tavern'],
          ],
        ],
      ],
      'room-tavern'
    );

    $this->assertFalse($plan['gm_addressed']);
    $this->assertSame(
      ['guard_captain', 'tavern_keeper', 'scholar_npc'],
      array_map(static fn(array $npc): string => $npc['entity_ref'], $plan['ordered_npcs'])
    );
  }

  /**
   * @covers ::buildRoomConversationTranscript
   * @covers ::buildRoomObservationFromChat
   */
  public function testRoomObservationIncludesPriorNpcDialogueInOrder(): void {
    $chat = [
      ['speaker' => 'Burasco', 'message' => 'Eldric, what happened here?'],
      ['speaker' => 'Game Master', 'message' => 'The tavern falls quiet for a moment.'],
      ['speaker' => 'Eldric', 'message' => 'You came back later than I expected.'],
      ['speaker' => 'Marta the Scholar', 'message' => 'And with more questions than answers, it seems.'],
    ];

    $transcript = $this->roomChatService->publicBuildRoomConversationTranscript($chat, 8);
    $observation = $this->roomChatService->publicBuildRoomObservationFromChat($chat, 8);

    $this->assertStringContainsString('Burasco: Eldric, what happened here?', $transcript);
    $this->assertStringContainsString('Eldric: You came back later than I expected.', $transcript);
    $this->assertStringContainsString('Marta the Scholar: And with more questions than answers, it seems.', $transcript);
    $this->assertStringContainsString('Overheard in the room', $observation);
    $this->assertStringContainsString('Marta the Scholar: And with more questions than answers, it seems.', $observation);
  }

  /**
   * @covers ::buildRoomConversationTranscript
   * @covers ::buildRoomObservationFromChat
   */
  public function testRoomObservationSkipsInternalTurnLogMessages(): void {
    $chat = [
      ['speaker' => 'Burasco', 'message' => 'Who speaks first?'],
      ['speaker' => 'System', 'message' => 'Turn order: Player -> Narrator -> Eldric.', 'type' => 'system', 'internal_log' => TRUE],
      ['speaker' => 'System', 'message' => 'Next speaker: Eldric.', 'type' => 'system', 'internal_log' => TRUE],
      ['speaker' => 'Eldric', 'message' => 'I do.', 'type' => 'npc'],
    ];

    $transcript = $this->roomChatService->publicBuildRoomConversationTranscript($chat, 8);
    $observation = $this->roomChatService->publicBuildRoomObservationFromChat($chat, 8);

    $this->assertStringContainsString('Burasco: Who speaks first?', $transcript);
    $this->assertStringContainsString('Eldric: I do.', $transcript);
    $this->assertStringNotContainsString('Turn order:', $transcript);
    $this->assertStringNotContainsString('Next speaker:', $observation);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesNavigation(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      "OK, so who is going with me? I'll meet you there. Then I leave for the rat dungeon"
    );

    $this->assertSame('navigation_travel', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesTravelingVariant(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'OK, lets try traveling to the rat dungeon again.'
    );

    $this->assertSame('navigation_travel', $intent);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseCreatesNavigationAction(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      17,
      'navigation_travel',
      [],
      NULL,
      "Then I leave for the rat dungeon",
      ['name' => 'The Gilded Tankard']
    );

    $this->assertNotNull($response);
    $this->assertSame('navigate_to_location', $response['actions'][0]['type']);
    $this->assertSame('Rat Dungeon', $response['actions'][0]['details']['destination']);
    $this->assertStringContainsString('leads toward Rat Dungeon', $response['narrative']);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseUsesReturnNarrationForVisitedDestination(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      17,
      'navigation_travel',
      [],
      NULL,
      'Then I leave for the goblin warrens',
      ['name' => 'The Gilded Tankard', 'room_id' => 'room-tavern'],
      'room-tavern',
      [
        'rooms' => [
          [
            'room_id' => 'room-warrens',
            'name' => 'Goblin Warrens',
            'chat' => [
              ['speaker' => 'Game Master', 'message' => 'You arrive at Goblin Warrens.'],
            ],
          ],
        ],
        'location_history' => [
          [
            'room_id' => 'room-warrens',
            'room_name' => 'Goblin Warrens',
            'action' => 'arrived at',
            'timestamp' => '2026-01-01T00:00:00+00:00',
          ],
        ],
      ]
    );

    $this->assertNotNull($response);
    $this->assertSame('navigate_to_location', $response['actions'][0]['type']);
    $this->assertStringContainsString('route back toward Goblin Warrens', $response['narrative']);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationStripsTrailingFillerWords(): void {
    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'I leave for the rat dungeon now.'
    );

    $this->assertSame('Rat Dungeon', $destination);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesCombatEngagement(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Hey Gribbles and Marta, let us kill those rats and search the room.'
    );

    $this->assertSame('combat_engagement', $intent);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseCreatesCombatInitiationAction(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      17,
      'combat_engagement',
      [],
      NULL,
      'Let us kill those rats now.',
      ['name' => 'Rat Nest'],
      'room-1',
      [
        'entities' => [
          [
            'entity_instance_id' => 'giant_rat_alpha',
            'name' => 'Giant Rat Alpha',
            'team' => 'hostile',
            'placement' => ['room_id' => 'room-1'],
          ],
          [
            'entity_instance_id' => 'giant_rat_beta',
            'name' => 'Giant Rat Beta',
            'state' => ['metadata' => ['team' => 'enemy']],
            'placement' => ['room_id' => 'room-1'],
          ],
        ],
      ]
    );

    $this->assertNotNull($response);
    $this->assertSame('combat_initiation', $response['actions'][0]['type']);
    $this->assertSame(['giant_rat_alpha', 'giant_rat_beta'], $response['actions'][0]['details']['combat']['enemy_entity_ids']);
    $this->assertStringContainsString('erupts into combat', strtolower($response['narrative']));
  }

  /**
   * @covers ::trimIncompleteNarrative
   */
  public function testTrimIncompleteNarrativeReturnsLastCompleteSentence(): void {
    $trimmed = $this->roomChatService->publicTrimIncompleteNarrative(
      'The room falls silent for a moment. Eldric glances toward the door and starts to sa'
    );

    $this->assertSame('The room falls silent for a moment.', $trimmed);
  }

  /**
   * @covers ::stripPlayerVisibleActionBlocks
   */
  public function testStripPlayerVisibleActionBlocksRemovesJsonLeakage(): void {
    $sanitized = $this->roomChatService->publicStripPlayerVisibleActionBlocks(
      "You can target the rats with Sleep. Here's the JSON action block for casting Sleep: ```json\n{ \"actions\": [ { \"type\": \"cast_spell\" } ]"
    );

    $this->assertSame('You can target the rats with Sleep.', $sanitized);
  }

  /**
   * @covers ::validateGmNarrativeRoleBoundary
   */
  public function testValidateGmNarrativeRoleBoundaryFlagsFirstPersonPlayerVoice(): void {
    $errors = $this->roomChatService->publicValidateGmNarrativeRoleBoundary(
      "I'm Burasco, and I stride up to the bar with a grin.",
      [
        'basicInfo' => [
          'name' => 'Burasco',
        ],
      ]
    );

    $this->assertContains('gm_role_boundary_first_person_voice', $errors);
  }

  /**
   * @covers ::validateGmNarrativeRoleBoundary
   */
  public function testValidateGmNarrativeRoleBoundaryFlagsStagedPlayerRoleplay(): void {
    $errors = $this->roomChatService->publicValidateGmNarrativeRoleBoundary(
      'He braces his staff and waits for Marta to answer.',
      [
        'basicInfo' => [
          'name' => 'Burasco',
        ],
      ]
    );

    $this->assertContains('gm_role_boundary_staged_in_world_roleplay', $errors);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesNavigationQuery(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'What is in the next room?'
    );

    $this->assertSame('navigation_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesUnexploredNavigationQuery(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      "Which way haven't I been?"
    );

    $this->assertSame('navigation_query', $intent);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseAnswersNavigationQueryFromGroundedExits(): void {
    $actionProcessor = $this->createMock(GameplayActionProcessor::class);
    $actionProcessor->method('getResolvedRoomExits')
      ->willReturn([
        [
          'name' => 'The Gilded Tankard',
          'room_id' => 'room-tavern',
          'connection_type' => 'passage',
          'explored' => TRUE,
        ],
        [
          'name' => 'The Goblin Warrens',
          'room_id' => 'room-warrens',
          'connection_type' => 'passage',
          'explored' => FALSE,
        ],
      ]);

    $this->roomChatService->setActionProcessor($actionProcessor);

    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'navigation_query',
      [],
      NULL,
      'What is in the next room?',
      ['name' => 'Vermin-Ridden Antechamber', 'room_id' => 'room-rats'],
      'room-rats',
      []
    );

    $this->assertNotNull($response);
    $this->assertSame([], $response['actions']);
    $this->assertStringContainsString('The Goblin Warrens', $response['narrative']);
    $this->assertStringContainsString('unexplored', $response['narrative']);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationUsesPreferredExitForGenericDoorPush(): void {
    $actionProcessor = $this->createMock(GameplayActionProcessor::class);
    $actionProcessor->method('getResolvedRoomExits')
      ->willReturn([
        [
          'name' => 'The Gilded Tankard',
          'room_id' => 'room-tavern',
          'connection_type' => 'passage',
          'explored' => TRUE,
        ],
        [
          'name' => 'The Goblin Warrens',
          'room_id' => 'room-warrens',
          'connection_type' => 'passage',
          'explored' => FALSE,
        ],
      ]);

    $this->roomChatService->setActionProcessor($actionProcessor);

    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'break down the door, lets go',
      ['name' => 'Vermin-Ridden Antechamber', 'room_id' => 'room-rats'],
      'room-rats',
      []
    );

    $this->assertSame('The Goblin Warrens', $destination);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationUsesPreferredExitForGoThereFollowup(): void {
    $actionProcessor = $this->createMock(GameplayActionProcessor::class);
    $actionProcessor->method('getResolvedRoomExits')
      ->willReturn([
        [
          'name' => 'The Gilded Tankard',
          'room_id' => 'room-tavern',
          'connection_type' => 'passage',
          'explored' => TRUE,
        ],
        [
          'name' => 'Northeast Passage',
          'room_id' => 'room-passage',
          'connection_type' => 'tunnel',
          'explored' => FALSE,
        ],
      ]);

    $this->roomChatService->setActionProcessor($actionProcessor);

    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'yea, lets go there',
      ['name' => 'The Glowing Cavern', 'room_id' => 'room-cavern'],
      'room-cavern',
      []
    );

    $this->assertSame('Northeast Passage', $destination);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesRoomDescriptionQuery(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent('explanation, description?');

    $this->assertSame('room_description_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesImplicitRosterQuestion(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Its just this one Kobold in the room with me? Any others hiding?'
    );

    $this->assertSame('room_roster_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesContractedRosterQuestion(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      "Who's here?"
    );

    $this->assertSame('room_roster_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesExplicitGmAdjudicationQuery(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent('GM, have I heard that phrase before?');

    $this->assertSame('gm_adjudication_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentDoesNotTreatNarrativeGmMentionAsAdjudication(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent('This is a GM-led quest.');

    $this->assertSame('gm_narration', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentPrioritizesGmAdjudicationOverNpcDialogue(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Marta, GM, would Burasco recognize that phrase?',
      [],
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ]
    );

    $this->assertSame('gm_adjudication_query', $intent);
  }

  /**
   * @covers ::resolveActiveDirectConversationNpc
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentContinuesScopedNpcConversation(): void {
    $room_npcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
    ];

    $active_npc = $this->roomChatService->publicResolveActiveDirectConversationNpc(
      [
        ['speaker' => 'Burasco', 'message' => 'Marta, what is this note?', 'type' => 'player', 'channel' => 'room'],
        ['speaker' => 'Game Master', 'message' => 'The space narrows to a direct conversation.', 'type' => 'npc', 'channel' => 'room'],
        ['speaker' => 'Marta the Scholar', 'message' => '"It is older than it looks."', 'type' => 'npc', 'channel' => 'room'],
      ],
      $room_npcs
    );

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      "I'm looking at the text Marta presented.",
      $room_npcs,
      NULL,
      $active_npc
    );

    $this->assertNotNull($active_npc);
    $this->assertSame('scholar_npc', $active_npc['entity_ref']);
    $this->assertSame('direct_npc_dialogue', $intent);
  }

  /**
   * @covers ::resolveActiveDirectConversationNpc
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentKeepsQuestionMarkFollowupOnActiveNpcThread(): void {
    $room_npcs = [
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => [
          'display_name' => 'Eldric',
        ],
      ],
    ];

    $active_npc = $this->roomChatService->publicResolveActiveDirectConversationNpc(
      [
        ['speaker' => 'Burasco', 'message' => 'Eldric, tell me about the mission.', 'type' => 'player', 'channel' => 'room'],
        ['speaker' => 'Game Master', 'message' => 'The space narrows to a direct conversation.', 'type' => 'npc', 'channel' => 'room'],
        ['speaker' => 'Eldric', 'message' => '"If you want work, I can point you to it."', 'type' => 'npc', 'channel' => 'room'],
      ],
      $room_npcs
    );

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      'You have no stories for me?',
      $room_npcs,
      NULL,
      $active_npc
    );

    $this->assertNotNull($active_npc);
    $this->assertSame('direct_npc_dialogue', $intent);
  }

  /**
   * @covers ::resolveActiveDirectConversationNpc
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentKeepsQuotedEmoteFollowupOnActiveNpcThread(): void {
    $room_npcs = [
      [
        'entity_ref' => 'smith_merchant',
        'profile' => [
          'display_name' => 'Brunt',
          'role' => 'merchant',
          'motivations' => 'sell well-made weapons',
        ],
      ],
    ];

    $active_npc = $this->roomChatService->publicResolveActiveDirectConversationNpc(
      [
        ['speaker' => 'Brakouk', 'message' => 'Brunt. Show me the blade that will not quit.', 'type' => 'player', 'channel' => 'room'],
        ['speaker' => 'Game Master', 'message' => 'The space narrows to a direct conversation.', 'type' => 'npc', 'channel' => 'room'],
        ['speaker' => 'Brunt', 'message' => '"This axe will hold its edge."', 'type' => 'npc', 'channel' => 'room'],
      ],
      $room_npcs
    );

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      '*Sets down the coin pouch with a heavy clink.* "Done. If this edge holds, I will be back for a second."',
      $room_npcs,
      NULL,
      $active_npc
    );

    $this->assertNotNull($active_npc);
    $this->assertSame('direct_npc_transaction', $intent);
  }

  /**
   * @covers ::resolveActiveDirectConversationNpc
   */
  public function testResolveActiveDirectConversationNpcAllowsSimplePlayerFollowups(): void {
    $room_npcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
    ];

    $active_npc = $this->roomChatService->publicResolveActiveDirectConversationNpc(
      [
        ['speaker' => 'Burasco', 'message' => 'Marta, what is this note?', 'type' => 'player', 'channel' => 'room'],
        ['speaker' => 'Game Master', 'message' => 'The space narrows to a direct conversation.', 'type' => 'npc', 'channel' => 'room'],
        ['speaker' => 'Burasco', 'message' => 'Can you read it?', 'type' => 'player', 'channel' => 'room'],
        ['speaker' => 'Game Master', 'message' => 'The note remains in Marta\'s hands between you.', 'type' => 'npc', 'channel' => 'room'],
      ],
      $room_npcs
    );

    $this->assertNotNull($active_npc);
    $this->assertSame('scholar_npc', $active_npc['entity_ref']);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesGmRoleBoundaryCorrection(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      "GM isn't supposed to act as the Player..."
    );

    $this->assertSame('gm_role_correction', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentKeepsExplicitGmQueryAboveActiveNpcThread(): void {
    $room_npcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
    ];

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      'GM, have I heard that phrase before?',
      $room_npcs,
      NULL,
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ]
    );

    $this->assertSame('gm_adjudication_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentTreatsKnowledgeCheckAsGmQueryEvenWithActiveNpcThread(): void {
    $room_npcs = [
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
    ];

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      'Do I know this?',
      $room_npcs,
      NULL,
      [
        'entity_ref' => 'scholar_npc',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ]
    );

    $this->assertSame('gm_adjudication_query', $intent);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseAnswersRoomDescriptionQueryWithoutNpcDialogue(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'room_description_query',
      [
        [
          'entity_ref' => 'tikka',
          'profile' => [
            'display_name' => 'Tikka the Trapmaster',
          ],
        ],
      ],
      NULL,
      'explanation, description?',
      [
        'name' => 'Kobold Burrow',
        'description' => 'A network of meticulous tunnels opens into a cleverly trapped chamber.',
        'characters' => [
          ['name' => 'Burasco'],
        ],
      ],
      'room-burrow',
      []
    );

    $this->assertNotNull($response);
    $this->assertSame([], $response['actions']);
    $this->assertStringContainsString('Kobold Burrow', $response['narrative']);
    $this->assertStringContainsString('Visible here: Burasco, Tikka the Trapmaster.', $response['narrative']);
    $this->assertTrue($response['suppress_npc_interjections']);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseAnswersGmAdjudicationWithoutRoleplay(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'gm_adjudication_query',
      [
        [
          'entity_ref' => 'tikka',
          'profile' => [
            'display_name' => 'Tikka the Trapmaster',
          ],
        ],
      ],
      NULL,
      'GM, would Burasco recognize that phrase?',
      [
        'name' => 'Kobold Burrow',
        'description' => 'A network of meticulous tunnels opens into a cleverly trapped chamber.',
        'characters' => [
          ['name' => 'Burasco'],
        ],
      ],
      'room-burrow',
      [],
      FALSE,
      [
        'basicInfo' => [
          'name' => 'Burasco',
        ],
      ]
    );

    $this->assertNotNull($response);
    $this->assertSame([], $response['actions']);
    $this->assertStringContainsString('From what is grounded in the current scene', $response['narrative']);
    $this->assertStringContainsString('In Kobold Burrow, the only clearly visible named occupant is Tikka the Trapmaster is present.', $response['narrative']);
    $this->assertStringNotContainsString("I'm", $response['narrative']);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseAcknowledgesRoleBoundaryCorrection(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'gm_role_correction',
      [],
      NULL,
      "GM isn't supposed to act as the Player..."
    );

    $this->assertNotNull($response);
    $this->assertTrue($response['suppress_npc_interjections']);
    $this->assertStringContainsString('leave your character', $response['narrative']);
  }

  /**
   * @covers ::isEffectiveRoomEntryTurn
   */
  public function testEffectiveRoomEntryTurnTreatsArrivalPlusFirstPlayerPromptAsEntry(): void {
    $is_entry = $this->roomChatService->publicIsEffectiveRoomEntryTurn([
      [
        'speaker' => 'System',
        'message' => 'You arrive at Kobold Burrow.',
        'type' => 'system',
      ],
      [
        'speaker' => 'Burasco',
        'message' => 'explanation, description?',
        'type' => 'player',
      ],
    ]);

    $this->assertTrue($is_entry);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   */
  public function testDeterministicNpcDialogueAnswersAloneAndColonyQuestion(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'tikka', ['display_name' => 'Tikka the Trapmaster', 'attitude' => 'indifferent', 'role' => 'guide', 'motivations' => 'protect the burrow']],
      ]);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'tikka',
      'Tikka the Trapmaster',
      'Are you alone Tikka? How big is this Kobold colony?',
      'room-burrow',
      [
        'rooms' => [
          [
            'room_id' => 'room-burrow',
            'name' => 'Kobold Burrow',
            'description' => 'A network of small tunnels opens into an organized underground chamber.',
          ],
        ],
      ]
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('In this chamber, yes. In the burrow, no.', $reply);
    $this->assertStringContainsString('The burrow runs deeper through the tunnels', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   * @covers ::buildBrokeredStorylineLeadDialogue
   */
  public function testDeterministicNpcDialoguePrefersBrokeredStorylineLeads(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'npc_tavern_keeper', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'quest_giver',
          'motivations' => 'connect travelers with work',
        ]],
      ]);

    $relationship_manager = $this->createMock(\Drupal\dungeoncrawler_content\Service\RelationshipManagerService::class);
    $relationship_manager->expects($this->once())
      ->method('getCampaignStorylineContacts')
      ->with(22, 'npc_tavern_keeper')
      ->willReturn([
        [
          'name' => 'Missing Caravan',
          'quest_giver' => [
            'display_name' => 'Marta the Scholar',
            'notes' => 'she keeps the ledger and knows who failed to report in',
          ],
          'lead_location' => [
            'label' => 'The Gilded Tankard',
          ],
        ],
      ]);

    $this->roomChatService->setRelationshipManager($relationship_manager);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'npc_tavern_keeper',
      'Eldric',
      'Any work around here?'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Missing Caravan', $reply);
    $this->assertStringContainsString('Marta the Scholar', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   * @covers ::buildBrokeredStorylineLeadDialogue
   * @covers ::loadBrokeredStorylineContacts
   */
  public function testDeterministicNpcDialogueAcceptsLiveTavernKeeperAliasForStorylineLeads(): void {
    $relationship_manager = $this->createMock(\Drupal\dungeoncrawler_content\Service\RelationshipManagerService::class);
    $relationship_manager->expects($this->once())
      ->method('getCampaignStorylineContacts')
      ->with(22, 'npc_tavern_keeper')
      ->willReturn([
        [
          'name' => 'Threshold of Knowledge',
          'quest_giver' => [
            'display_name' => 'Okoro of the Open Palm',
          ],
          'lead_location' => [
            'label' => 'Magaambya Campus',
          ],
        ],
      ]);

    $this->roomChatService->setRelationshipManager($relationship_manager);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'tavern_keeper',
      'Eldric',
      'Tell me about the mission.'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Threshold of Knowledge', $reply);
    $this->assertStringContainsString('Okoro of the Open Palm', $reply);
  }

  /**
   * @covers ::selectMentionedBrokeredStorylineContacts
   */
  public function testSelectMentionedBrokeredStorylineContactsMatchesNamedStorylineLeads(): void {
    $matches = $this->roomChatService->publicSelectMentionedBrokeredStorylineContacts([
      [
        'storyline_id' => 'threshold-of-knowledge',
        'name' => 'Threshold of Knowledge',
        'quest_giver' => [
          'display_name' => 'Okoro of the Open Palm',
          'notes' => 'Okoro briefs the party on the missing teacher.',
        ],
        'lead_location' => [
          'label' => 'Magaambya Campus',
        ],
      ],
      [
        'storyline_id' => 'little-trouble-in-big-absalom',
        'name' => 'Little Trouble in Big Absalom',
        'quest_giver' => [
          'display_name' => 'The Kind Old Lady',
          'notes' => 'She asks the kobolds to recover her magical hedge trimmer.',
        ],
        'lead_location' => [
          'label' => 'Upstairs!',
        ],
      ],
    ], 'If you want work, For Little Trouble in Big Absalom, look for The Kind Old Lady at Upstairs! Also, For Threshold of Knowledge, look for Okoro of the Open Palm at Magaambya Campus.');

    $this->assertCount(2, $matches);
    $this->assertSame([
      'little-trouble-in-big-absalom',
      'threshold-of-knowledge',
    ], array_values(array_unique(array_map(static fn(array $match): string => (string) ($match['storyline_id'] ?? ''), $matches))));
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentPrioritizesLeadQuestionsOverTransactionKeywords(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'OK, drinking it. Tell me about the mission Eldric.',
      [],
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => [
          'display_name' => 'Eldric',
        ],
      ]
    );

    $this->assertSame('direct_npc_dialogue', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesMerchantInquiryWithoutDirectAddress(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'I want to purchase a longsword.',
      [
        [
          'entity_ref' => 'eldric_merchant',
          'profile' => [
            'display_name' => 'Eldric',
            'role' => 'merchant',
            'motivations' => 'sell useful gear to travelers',
          ],
        ],
      ]
    );

    $this->assertSame('merchant_inquiry', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentAvoidsMerchantFalsePositiveOnGenericPayPhrase(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      "Eldric, I'll pay you back later.",
      [],
      [
        'entity_ref' => 'eldric_merchant',
        'profile' => [
          'display_name' => 'Eldric',
          'role' => 'merchant',
          'motivations' => 'sell useful gear to travelers',
        ],
      ]
    );

    $this->assertSame('direct_npc_dialogue', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesCoinAmountAsMerchantTransaction(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Thirty silvers, Brunt. No more.',
      [],
      [
        'entity_ref' => 'brunt_merchant',
        'profile' => [
          'display_name' => 'Brunt',
          'role' => 'merchant',
          'motivations' => 'sell sturdy weapons to capable buyers',
        ],
      ]
    );

    $this->assertSame('direct_npc_transaction', $intent);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   */
  public function testDeterministicNpcDialogueQuotesMerchantPurchase(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'eldric_merchant', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'merchant',
          'motivations' => 'sell useful gear to travelers',
        ]],
      ]);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'eldric_merchant',
      'Eldric',
      'I want to purchase a longsword.'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Longsword', $reply);
    $this->assertStringContainsString('1 gp', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   */
  public function testDeterministicNpcDialogueQuotesMerchantSaleAtHalfPrice(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'eldric_merchant', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'merchant',
          'motivations' => 'sell useful gear to travelers',
        ]],
      ]);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'eldric_merchant',
      'Eldric',
      'I want to sell a longsword.'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Longsword', $reply);
    $this->assertStringContainsString('5 sp', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   */
  public function testDeterministicNpcDialogueUnderstandsPriceOfPhrasing(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'eldric_merchant', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'merchant',
          'motivations' => 'sell useful gear to travelers',
        ]],
      ]);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'eldric_merchant',
      'Eldric',
      'What is the price of a longsword?'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Longsword', $reply);
    $this->assertStringContainsString('1 gp', $reply);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseExecutesMerchantPurchase(): void {
    $merchant_bot = $this->createMock(\Drupal\dungeoncrawler_content\Service\MerchantBotService::class);
    $merchant_bot->expects($this->once())
      ->method('planMerchantTransaction')
      ->with(17, 'I want to purchase a longsword.', 22)
      ->willReturn([
        'status' => 'ready_purchase',
        'item' => [
          'id' => 'longsword',
          'name' => 'Longsword',
          'type' => 'weapon',
          'item_type' => 'weapon',
          'price_gp' => 1.0,
        ],
        'quantity' => 1,
        'price_cp' => 100,
      ]);

    $inventory_management = $this->createMock(\Drupal\dungeoncrawler_content\Service\InventoryManagementService::class);
    $inventory_management->expects($this->once())
      ->method('purchaseItem')
      ->with('17', [
        'id' => 'longsword',
        'name' => 'Longsword',
        'type' => 'weapon',
        'item_type' => 'weapon',
        'price_gp' => 1.0,
      ], 'downtime', 1, 22)
      ->willReturn(['success' => TRUE]);

    $this->roomChatService->setMerchantBotService($merchant_bot);
    $this->roomChatService->setInventoryManagementService($inventory_management);

    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'merchant_inquiry',
      [[
        'entity_ref' => 'eldric_merchant',
        'profile' => ['display_name' => 'Eldric', 'role' => 'merchant'],
      ]],
      NULL,
      'I want to purchase a longsword.',
      ['name' => 'Market Stall'],
      'room-market',
      [],
      FALSE,
      NULL,
      17
    );

    $this->assertNotNull($response);
    $this->assertStringContainsString('Eldric completes the sale', $response['narrative']);
    $this->assertTrue($response['suppress_npc_interjections']);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseReportsBlockedMerchantTrade(): void {
    $merchant_bot = $this->createMock(\Drupal\dungeoncrawler_content\Service\MerchantBotService::class);
    $merchant_bot->expects($this->once())
      ->method('planMerchantTransaction')
      ->with(17, 'I want to purchase a holy avenger.', 22)
      ->willReturn([
        'status' => 'blocked',
        'message' => 'You do not have enough coin for Holy Avenger.',
      ]);

    $this->roomChatService->setMerchantBotService($merchant_bot);

    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'merchant_inquiry',
      [[
        'entity_ref' => 'eldric_merchant',
        'profile' => ['display_name' => 'Eldric', 'role' => 'merchant'],
      ]],
      NULL,
      'I want to purchase a holy avenger.',
      ['name' => 'Market Stall'],
      'room-market',
      [],
      FALSE,
      NULL,
      17
    );

    $this->assertNotNull($response);
    $this->assertStringContainsString('cannot close the deal', $response['narrative']);
    $this->assertStringContainsString('Holy Avenger', $response['narrative']);
    $this->assertTrue($response['suppress_npc_interjections']);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentTreatsBrokerNpcAsQuestContactWithoutRoleMetadata(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Any work around here?',
      [
        [
          'entity_ref' => 'tavern_keeper',
          'profile' => [
            'display_name' => 'Eldric',
            'role' => '',
            'motivations' => '',
          ],
        ],
      ]
    );

    $this->assertSame('quest_query', $intent);
  }

  /**
   * @covers ::buildCompactSessionContext
   */
  public function testBuildCompactSessionContextCanDropRecentMessages(): void {
    $session_manager = $this->createMock(AiSessionManager::class);
    $session_manager->expects($this->once())
      ->method('buildSessionContext')
      ->with('campaign.17.room_chat.room-1', 17, 2)
      ->willReturn("PRIOR SESSION CONTEXT (summary of earlier interactions):\nEarlier summary.\n\nRECENT CONVERSATION:\n[USER]: Old question\n[ASSISTANT]: Old answer");

    $this->roomChatService->setSessionManager($session_manager);

    $context = $this->roomChatService->publicBuildCompactSessionContext(
      'campaign.17.room_chat.room-1',
      17,
      2,
      900,
      320,
      FALSE
    );

    $this->assertStringContainsString('PRIOR SESSION CONTEXT', $context);
    $this->assertStringContainsString('Earlier summary.', $context);
    $this->assertStringNotContainsString('RECENT CONVERSATION', $context);
    $this->assertStringNotContainsString('Old question', $context);
  }

  /**
   * @covers ::sanitizePlayerVisibleNarrative
   */
  public function testSanitizePlayerVisibleNarrativeRemovesPromptLeakageHeadings(): void {
    $sanitized = $this->roomChatService->publicSanitizePlayerVisibleNarrative(
      "=== AVAILABLE STORYLINE LEADS ===\nCurrent room: The Gilded Tankard\nRECENT CONVERSATION:\n[USER]: Marta, what is this?\nThe note looks freshly folded."
    );

    $this->assertSame('The note looks freshly folded.', $sanitized);
  }

  /**
   * @covers ::recordLocationTransition
   */
  public function testRecordLocationTransitionPersistsCurrentAndActiveRoomIds(): void {
    $dungeonData = [];

    $this->roomChatService->publicRecordLocationTransition(
      $dungeonData,
      ['room_id' => 'room-rats', 'name' => 'Vermin-Ridden Antechamber'],
      [
        'new_room' => [
          'room_id' => 'room-warrens',
          'name' => 'The Goblin Warrens',
        ],
      ]
    );

    $this->assertSame('room-warrens', $dungeonData['current_room_id']);
    $this->assertSame('room-warrens', $dungeonData['active_room_id']);
    $this->assertSame('room-warrens', $dungeonData['last_navigation']['to_room_id']);
  }

}

/**
 * Test wrapper exposing protected RoomChatService helpers.
 */
class TestableRoomChatService extends RoomChatService {

  public function publicResolveCampaignCharacterNpcProfile(int $campaign_id, object $row, array $seen_refs = []): array {
    return $this->resolveCampaignCharacterNpcProfile($campaign_id, $row, $seen_refs);
  }

  public function publicResolveDirectlyAddressedNpc(array $room_npcs, string $player_message): ?array {
    return $this->resolveDirectlyAddressedNpc($room_npcs, $player_message);
  }

  public function publicResolveSelectedRoomNpcs(array $room_npcs, string $response_text): array {
    return $this->resolveSelectedRoomNpcs($room_npcs, $response_text);
  }

  public function publicResolveNamedRoomNpc(array $room_npcs, string $speaker_name): ?array {
    return $this->resolveNamedRoomNpc($room_npcs, $speaker_name);
  }

  public function publicBuildRoomConversationTranscript(array $chat, int $limit = 8): string {
    return $this->buildRoomConversationTranscript($chat, $limit);
  }

  public function publicBuildRoomObservationFromChat(array $chat, int $limit = 8): string {
    return $this->buildRoomObservationFromChat($chat, $limit);
  }

  public function publicClassifyRoomTurnIntent(
    string $player_message,
    array $room_npcs = [],
    ?array $directly_addressed_npc = NULL,
    ?array $active_conversation_npc = NULL
  ): string {
    return $this->classifyRoomTurnIntent($player_message, $room_npcs, $directly_addressed_npc, $active_conversation_npc);
  }

  public function publicResolveActiveDirectConversationNpc(array $chat, array $room_npcs): ?array {
    return $this->resolveActiveDirectConversationNpc($chat, $room_npcs);
  }

  public function publicClassifyRoomTurnIntentWithActiveConversation(
    string $player_message,
    array $room_npcs = [],
    ?array $directly_addressed_npc = NULL,
    ?array $active_conversation_npc = NULL
  ): string {
    return $this->publicClassifyRoomTurnIntent($player_message, $room_npcs, $directly_addressed_npc, $active_conversation_npc);
  }

  public function publicBuildDeterministicGmResponse(
    int $campaign_id,
    string $intent,
    array $room_npcs,
    ?array $directly_addressed_npc,
    string $player_message,
    array $room_meta = [],
    string $room_id = '',
    array $dungeon_data = [],
    bool $is_room_entry = FALSE,
    ?array $character_data = NULL,
    ?int $character_id = NULL
  ): ?array {
    return $this->buildDeterministicGmResponse($campaign_id, $intent, $room_npcs, $directly_addressed_npc, $player_message, $room_meta, $room_id, $dungeon_data, $is_room_entry, $character_data, $character_id);
  }

  public function publicExtractNavigationDestination(string $player_message, array $room_meta = [], string $room_id = '', array $dungeon_data = []): ?string {
    return $this->extractNavigationDestination($player_message, $room_meta, $room_id, $dungeon_data);
  }

  public function publicTrimIncompleteNarrative(string $narrative): string {
    return $this->trimIncompleteNarrative($narrative);
  }

  public function publicStripPlayerVisibleActionBlocks(string $narrative): string {
    return $this->stripPlayerVisibleActionBlocks($narrative);
  }

  public function publicSanitizePlayerVisibleNarrative(string $narrative): string {
    return $this->sanitizePlayerVisibleNarrative($narrative);
  }

  public function publicRecordLocationTransition(array &$dungeon_data, array $origin_room_meta, array $navigation_result): void {
    $this->recordLocationTransition($dungeon_data, $origin_room_meta, $navigation_result);
  }

  public function publicIsEffectiveRoomEntryTurn(array $chat): bool {
    return $this->isEffectiveRoomEntryTurn($chat);
  }

  public function publicBuildNpcTurnPlan(
    array $room_npcs,
    string $player_message,
    string $gm_narrative,
    array $dungeon_data = [],
    string $room_id = ''
  ): array {
    return $this->buildNpcTurnPlan($room_npcs, $player_message, $gm_narrative, $dungeon_data, $room_id);
  }

  public function publicBuildDeterministicNpcDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $player_message,
    string $room_id = '',
    array $dungeon_data = []
  ): ?string {
    return $this->buildDeterministicNpcDialogue($campaign_id, $entity_ref, $display_name, $player_message, $room_id, $dungeon_data);
  }

  public function publicSelectMentionedBrokeredStorylineContacts(array $contacts, string $text, int $max_matches = 3, int $minimum_score = 2): array {
    return $this->selectMentionedBrokeredStorylineContacts($contacts, $text, $max_matches, $minimum_score);
  }

  public function setSessionManager(AiSessionManager $session_manager): void {
    $this->sessionManager = $session_manager;
  }

  public function publicBuildCompactSessionContext(
    string $session_key,
    int $campaign_id,
    int $max_recent = 3,
    int $max_chars = 1200,
    int $max_summary_chars = 400,
    bool $include_recent_messages = TRUE
  ): string {
    return $this->buildCompactSessionContext(
      $session_key,
      $campaign_id,
      $max_recent,
      $max_chars,
      $max_summary_chars,
      $include_recent_messages
    );
  }

  public function setActionProcessor(GameplayActionProcessor $action_processor): void {
    $this->actionProcessor = $action_processor;
  }

  public function setRelationshipManager(\Drupal\dungeoncrawler_content\Service\RelationshipManagerService $relationship_manager): void {
    $this->relationshipManager = $relationship_manager;
  }

  public function setMerchantBotService(\Drupal\dungeoncrawler_content\Service\MerchantBotService $merchant_bot_service): void {
    $this->merchantBotService = $merchant_bot_service;
  }

  public function setInventoryManagementService(\Drupal\dungeoncrawler_content\Service\InventoryManagementService $inventory_management_service): void {
    $this->inventoryManagementService = $inventory_management_service;
  }

  public function publicValidateGmNarrativeRoleBoundary(string $narrative, ?array $character_data): array {
    return $this->validateGmNarrativeRoleBoundary($narrative, $character_data);
  }

}
