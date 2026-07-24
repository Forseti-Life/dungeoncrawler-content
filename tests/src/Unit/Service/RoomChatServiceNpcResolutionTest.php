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
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineGenerationService;
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
   * @covers ::validateEncounterPlayerTurnForChat
   */
  public function testValidateEncounterPlayerTurnForChatAllowsActivePlayer(): void {
    $message = $this->roomChatService->publicValidateEncounterPlayerTurnForChat([
      'game_state' => [
        'phase' => 'encounter',
        'turn' => ['entity' => 'pc-1'],
      ],
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'entity_ref' => ['content_id' => 17],
          'state' => ['metadata' => ['team' => 'player']],
        ],
      ],
    ], 'room', 17, 'player');

    $this->assertNull($message);
  }

  /**
   * @covers ::validateEncounterPlayerTurnForChat
   */
  public function testValidateEncounterPlayerTurnForChatRejectsOutOfTurnPlayer(): void {
    $message = $this->roomChatService->publicValidateEncounterPlayerTurnForChat([
      'game_state' => [
        'phase' => 'encounter',
        'turn' => ['entity' => 'pc-1'],
      ],
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'entity_ref' => ['content_id' => 17],
          'state' => ['metadata' => ['team' => 'player']],
        ],
      ],
    ], 'room', 18, 'player');

    $this->assertSame("It's not your turn, please wait.", $message);
  }

  /**
   * @covers ::buildVisibleGmNarrative
   */
  public function testBuildVisibleGmNarrativeFallsBackWhenNarrativeIsEmpty(): void {
    $message = $this->roomChatService->publicBuildVisibleGmNarrative('', [
      ['name' => 'Take Cover'],
    ], [
      'validation_errors' => [],
      'character_changes' => [['field' => 'conditions', 'added' => ['cover']]],
      'room_changes' => [],
    ]);

    $this->assertStringContainsString('Game Master update: resolved Take Cover.', $message);
    $this->assertStringContainsString('Situational update: 1 character change, 0 room changes.', $message);
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
   * @covers ::resolveDirectlyAddressedNpc
   */
  public function testResolveDirectlyAddressedNpcMatchesNameAfterYouToken(): void {
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
      'For you Eldric, your stuff'
    );

    $this->assertNotNull($resolved);
    $this->assertSame('tavern_keeper', $resolved['entity_ref']);
    $this->assertSame('Eldric', $resolved['profile']['display_name']);
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
  public function testResolveNamedRoomNpcFallsBackToHighestCharismaOnAmbiguousTie(): void {
    $resolved = $this->roomChatService->publicResolveNamedRoomNpc(
      [
        [
          'entity_ref' => 'marta',
          'entity' => [
            'state' => [
              'abilities' => [
                'charisma' => 12,
              ],
            ],
          ],
          'profile' => [
            'display_name' => 'Marta',
          ],
        ],
        [
          'entity_ref' => 'marla',
          'entity' => [
            'state' => [
              'abilities' => [
                'charisma' => 16,
              ],
            ],
          ],
          'profile' => [
            'display_name' => 'Marla',
          ],
        ],
      ],
      'Marra'
    );

    $this->assertNotNull($resolved);
    $this->assertSame('marla', $resolved['entity_ref']);
  }

  /**
   * @covers ::resolveDirectlyAddressedNpc
   */
  public function testResolveDirectlyAddressedNpcFallsBackToHighestCharismaWhenTargetIsUnclear(): void {
    $resolved = $this->roomChatService->publicResolveDirectlyAddressedNpc(
      [
        [
          'entity_ref' => 'quiet_guard',
          'entity' => [
            'state' => [
              'abilities' => [
                'charisma' => 10,
              ],
            ],
          ],
          'profile' => [
            'display_name' => 'Quiet Guard',
          ],
        ],
        [
          'entity_ref' => 'tavern_keeper',
          'entity' => [
            'state' => [
              'abilities' => [
                'charisma' => 16,
              ],
            ],
          ],
          'profile' => [
            'display_name' => 'Eldric',
          ],
        ],
      ],
      'Can someone answer me?'
    );

    $this->assertNotNull($resolved);
    $this->assertSame('tavern_keeper', $resolved['entity_ref']);
  }

  /**
   * @covers ::resolveDirectlyAddressedNpc
   */
  public function testResolveDirectlyAddressedNpcRandomizesAmongHighestCharismaTie(): void {
    $resolved = $this->roomChatService->publicResolveDirectlyAddressedNpc(
      [
        [
          'entity_ref' => 'bard_one',
          'entity' => [
            'state' => [
              'abilities' => [
                'charisma' => 18,
              ],
            ],
          ],
          'profile' => [
            'display_name' => 'Lute',
          ],
        ],
        [
          'entity_ref' => 'bard_two',
          'entity' => [
            'state' => [
              'abilities' => [
                'charisma' => 18,
              ],
            ],
          ],
          'profile' => [
            'display_name' => 'Lyre',
          ],
        ],
        [
          'entity_ref' => 'quiet_guard',
          'entity' => [
            'state' => [
              'abilities' => [
                'charisma' => 8,
              ],
            ],
          ],
          'profile' => [
            'display_name' => 'Guard',
          ],
        ],
      ],
      'Anyone know what happened?'
    );

    $this->assertNotNull($resolved);
    $this->assertContains($resolved['entity_ref'], ['bard_one', 'bard_two']);
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
    $this->assertCount(1, $plan['ordered_npcs']);
    $this->assertSame('tavern_keeper', $plan['ordered_npcs'][0]['entity_ref']);
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
  public function testBuildNpcTurnPlanCarriesActiveConversationFocusForward(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'gribbles_rindsworth',
        'profile' => [
          'display_name' => 'Gribbles Rindsworth',
        ],
      ],
      [
        'entity_ref' => 'eldric',
        'profile' => [
          'display_name' => 'Eldric',
        ],
      ],
      [
        'entity_ref' => 'marta_the_scholar',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'I need a storyline.',
      'The space narrows to a direct conversation.',
      [
        'rooms' => [
          [
            'room_id' => 'tavern-room',
            'chat' => [
              ['speaker' => 'Player', 'message' => 'Hey Gribbles, I need a storyline.', 'type' => 'player', 'channel' => 'room'],
              ['speaker' => 'Game Master', 'message' => 'The space narrows to a direct conversation.', 'type' => 'npc', 'channel' => 'room'],
              ['speaker' => 'Gribbles Rindsworth', 'message' => '"What kind of trouble are you after?"', 'type' => 'npc', 'channel' => 'room'],
            ],
          ],
        ],
      ],
      'tavern-room'
    );

    $this->assertSame('gribbles_rindsworth', $plan['directly_addressed_npc']['entity_ref']);
    $this->assertSame('gribbles_rindsworth', $plan['active_conversation_npc']['entity_ref']);
    $this->assertContains('gribbles_rindsworth', $plan['speaking_npc_refs']);
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanUsesPersistedConversationStateFocus(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'eldric',
        'profile' => [
          'display_name' => 'Eldric',
          'role' => 'merchant',
        ],
      ],
      [
        'entity_ref' => 'marta_the_scholar',
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'One ale, then.',
      'Eldric stays focused on the order.',
      [
        'rooms' => [
          [
            'room_id' => 'tavern-room',
            'conversation_state' => [
              'entity_ref' => 'eldric',
              'speaker_name' => 'Eldric',
              'intent' => 'direct_npc_transaction',
              'channel' => 'room',
            ],
            'chat' => [
              ['speaker' => 'Player', 'message' => 'Eldric, what are you serving?', 'type' => 'player', 'channel' => 'room'],
              ['speaker' => 'Game Master', 'message' => 'Eldric gives you his full attention.', 'type' => 'npc', 'channel' => 'room'],
            ],
          ],
        ],
      ],
      'tavern-room'
    );

    $this->assertSame('eldric', $plan['directly_addressed_npc']['entity_ref']);
    $this->assertSame('eldric', $plan['active_conversation_npc']['entity_ref']);
    $this->assertNotEmpty($plan['speaking_npc_refs']);
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanUsesInitiativeOrderBeforeRoomFallback(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'scholar_npc',
        'entity' => [
          'state' => [
            'abilities' => [
              'charisma' => 50,
              'intelligence' => 50,
            ],
          ],
        ],
        'profile' => [
          'display_name' => 'Marta the Scholar',
        ],
      ],
      [
        'entity_ref' => 'tavern_keeper',
        'entity' => [
          'state' => [
            'abilities' => [
              'charisma' => 50,
              'intelligence' => 50,
            ],
          ],
        ],
        'profile' => [
          'display_name' => 'Eldric',
        ],
      ],
      [
        'entity_ref' => 'guard_captain',
        'entity' => [
          'state' => [
            'abilities' => [
              'charisma' => 50,
              'intelligence' => 50,
            ],
          ],
        ],
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
    $orderedRefs = array_map(static fn(array $npc): string => $npc['entity_ref'], $plan['ordered_npcs']);
    $expectedRefs = ['guard_captain', 'tavern_keeper', 'scholar_npc'];
    sort($orderedRefs);
    sort($expectedRefs);
    $this->assertSame($expectedRefs, $orderedRefs);
    $initiativeTotals = array_map(static fn(array $npc): int => (int) ($npc['initiative_total'] ?? 0), $plan['ordered_npcs']);
    $sortedTotals = $initiativeTotals;
    rsort($sortedTotals);
    $this->assertSame($sortedTotals, $initiativeTotals);
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanFiltersAmbientSideChatterByCharismaGate(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'quiet_guard',
        'entity' => [
          'state' => [
            'abilities' => [
              'charisma' => 0,
              'intelligence' => 0,
            ],
          ],
        ],
        'profile' => [
          'display_name' => 'Quiet Guard',
        ],
      ],
      [
        'entity_ref' => 'chatty_scholar',
        'entity' => [
          'state' => [
            'abilities' => [
              'charisma' => 18,
              'intelligence' => 18,
            ],
          ],
        ],
        'profile' => [
          'display_name' => 'Chatty Scholar',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'What does the room look like?',
      'Dusty shelves line the wall.',
      [],
      'room-library',
      'ambient-side-chatter-seed'
    );

    $orderedRefs = array_map(static fn(array $npc): string => $npc['entity_ref'], $plan['ordered_npcs']);
    $this->assertSame(['chatty_scholar'], $orderedRefs);
  }

  /**
   * @covers ::resolveAmbientNpcInterjectionPercent
   */
  public function testResolveAmbientNpcInterjectionPercentUsesFourTimesCharisma(): void {
    $this->assertSame(72, $this->roomChatService->publicResolveAmbientNpcInterjectionPercent([
      'entity' => ['state' => ['abilities' => ['charisma' => 18, 'intelligence' => 1]]],
    ]));
    $this->assertSame(100, $this->roomChatService->publicResolveAmbientNpcInterjectionPercent([
      'entity' => ['state' => ['abilities' => ['charisma' => 30, 'intelligence' => 0]]],
    ]));
  }

  /**
   * @covers ::resolveAmbientNpcInterjectionPercent
   */
  public function testResolveAmbientNpcInterjectionPercentDefaultsToCharismaTenWhenMissing(): void {
    $this->assertSame(40, $this->roomChatService->publicResolveAmbientNpcInterjectionPercent([
      'entity_ref' => 'unknown_npc',
      'profile' => ['display_name' => 'Unknown NPC'],
    ]));
  }

  /**
   * @covers ::resolveAmbientNpcInterjectionPercent
   */
  public function testResolveAmbientNpcInterjectionPercentReadsPf2eCharismaAlias(): void {
    $this->assertSame(56, $this->roomChatService->publicResolveAmbientNpcInterjectionPercent([
      'entity' => [
        'state' => [
          'pf2e_stats' => [
            'ability_scores' => [
              'cha' => ['score' => 14],
            ],
          ],
        ],
      ],
    ]));
  }

  /**
   * @covers ::resolveAmbientNpcInterjectionPercent
   */
  public function testResolveAmbientNpcInterjectionPercentClampsNegativeCharismaToMinimumThree(): void {
    $this->assertSame(12, $this->roomChatService->publicResolveAmbientNpcInterjectionPercent([
      'entity' => ['state' => ['abilities' => ['charisma' => -4]]],
    ]));
  }

  /**
   * @covers ::resolveNpcCharismaScore
   */
  public function testResolveNpcCharismaScoreReadsCanonicalAbilityShapes(): void {
    $cases = [
      [
        'npc' => ['entity' => ['state' => ['abilities' => ['charisma' => 12]]]],
        'expected' => 12,
      ],
      [
        'npc' => ['entity' => ['state' => ['pf2e_stats' => ['ability_scores' => ['charisma' => ['score' => 15]]]]]],
        'expected' => 15,
      ],
      [
        'npc' => ['ability_scores' => ['cha' => ['score' => 17]]],
        'expected' => 17,
      ],
      [
        'npc' => ['profile' => ['ability_scores' => ['charisma' => ['value' => 13]]]],
        'expected' => 13,
      ],
      [
        'npc' => ['profile' => ['display_name' => 'Fallback NPC']],
        'expected' => 10,
      ],
    ];

    foreach ($cases as $case) {
      $this->assertSame(
        $case['expected'],
        $this->roomChatService->publicResolveNpcCharismaScore($case['npc'])
      );
    }
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanDoesNotGateNpcNamedInConversation(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'quiet_guard',
        'entity' => [
          'state' => [
            'abilities' => [
              'charisma' => 0,
              'intelligence' => 0,
            ],
          ],
        ],
        'profile' => [
          'display_name' => 'Quiet Guard',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'Quiet Guard, what happened here?',
      'The room stills around the question.',
      [],
      'room-guardpost',
      'ambient-side-chatter-seed'
    );

    $orderedRefs = array_map(static fn(array $npc): string => $npc['entity_ref'], $plan['ordered_npcs']);
    $this->assertSame(['quiet_guard'], $orderedRefs);
  }

  /**
   * @covers ::buildNpcTurnPlan
   */
  public function testBuildNpcTurnPlanDoesNotTreatVerbUseAsNpcReference(): void {
    $roomNpcs = [
      [
        'entity_ref' => 'guard',
        'entity' => [
          'state' => [
            'abilities' => [
              'charisma' => 0,
              'intelligence' => 0,
            ],
          ],
        ],
        'profile' => [
          'display_name' => 'Guard',
        ],
      ],
    ];

    $plan = $this->roomChatService->publicBuildNpcTurnPlan(
      $roomNpcs,
      'I guard the door.',
      'The torchlight flickers.',
      [],
      'room-gatehouse',
      'ambient-side-chatter-seed'
    );

    $this->assertSame([], $plan['ordered_npcs']);
  }

  /**
   * @covers ::buildCharacterDialoguePayload
   * @covers ::buildCharacterDialogueChatMessage
   */
  public function testBuildCharacterDialoguePayloadProducesValidatedCanonicalStructure(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $validator = new StateValidationService($loggerFactory);

    $service = new TestableRoomChatService(
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
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      $validator
    );

    $payload = $service->publicBuildCharacterDialoguePayload(
      17,
      'room-tavern',
      'scholar_npc',
      'Marta the Scholar',
      'room',
      'room_interjection',
      '"The archive remembers."',
      'deterministic',
      NULL,
      NULL,
      TRUE,
      FALSE
    );
    $message = $service->publicBuildCharacterDialogueChatMessage($payload);

    $this->assertSame('character-dialogue-v1', $payload['schema_version']);
    $this->assertSame('scholar_npc', $payload['entity_ref']);
    $this->assertSame('room_interjection', $payload['delivery_type']);
    $this->assertSame('deterministic', $payload['context']['generation_source']);
    $this->assertTrue($payload['flags']['interjection']);
    $this->assertSame('"The archive remembers."', $message['message']);
    $this->assertSame($payload, $message['dialogue_payload']);
    $this->assertTrue($message['interjection']);
  }

  /**
   * @covers ::buildGmRoomResponsePayload
   * @covers ::buildRoomTurnHarnessPayload
   * @covers ::buildRoomChatResponsePayload
   * @covers ::buildQueuedRoomContinuationPayload
   */
  public function testBuildGmAndHarnessPayloadsProduceValidatedCanonicalStructures(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $validator = new StateValidationService($loggerFactory);

    $service = new TestableRoomChatService(
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
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      $validator
    );

    $gmPayload = $service->publicBuildGmRoomResponsePayload(
      'The torchlight bends across the stone corridor.',
      [['type' => 'search', 'name' => 'Search the room']],
      [['type' => 'perception', 'total' => 17]],
      TRUE
    );
    $harnessPayload = $service->publicBuildRoomTurnHarnessPayload([
      'player' => ['message' => 'Marta, what do you know?'],
      'gm' => ['narrative' => 'The tavern quiets around the question.'],
      'gm_addressed' => FALSE,
      'directly_addressed_npc' => 'scholar_npc',
      'npc_turns' => [
        ['entity_ref' => 'scholar_npc', 'display_name' => 'Marta the Scholar', 'spoke' => TRUE, 'initiative_total' => 17, 'initiative_roll' => 12, 'initiative_modifier' => 5],
      ],
      'turn_sequence' => [
        ['actor_key' => 'narrator', 'actor_ref' => NULL, 'display_name' => 'Narrator', 'role' => 'narrator', 'turn_index' => 1, 'initiative_total' => NULL, 'initiative_roll' => NULL, 'initiative_modifier' => NULL, 'spoke' => TRUE],
        ['actor_key' => 'scholar_npc', 'actor_ref' => 'scholar_npc', 'display_name' => 'Marta the Scholar', 'role' => 'npc', 'turn_index' => 2, 'initiative_total' => 17, 'initiative_roll' => 12, 'initiative_modifier' => 5, 'spoke' => TRUE],
      ],
      'turn_log_key' => 'room_turn_123',
      'turn_logs' => [
        [
          'speaker' => 'System',
          'message' => 'Current turn: Marta the Scholar.',
          'type' => 'system',
          'channel' => 'room',
          'timestamp' => '2026-05-17T00:00:00+00:00',
          'character_id' => NULL,
          'user_id' => 0,
          'internal_log' => TRUE,
          'turn_role' => 'npc',
          'turn_name' => 'Marta the Scholar',
          'turn_index' => 2,
          'initiative_total' => 17,
          'initiative_roll' => 12,
          'initiative_modifier' => 5,
          'turn_prompt' => TRUE,
        ],
      ],
      'messages' => [
        [
          'speaker' => 'Marta the Scholar',
          'message' => '"Knowledge has a price."',
          'type' => 'npc',
          'channel' => 'room',
          'timestamp' => '2026-05-17T00:00:01+00:00',
          'character_id' => NULL,
          'user_id' => 0,
          'interjection' => TRUE,
          'dialogue_payload' => [
            'schema_version' => 'character-dialogue-v1',
            'entity_ref' => 'scholar_npc',
            'speaker_name' => 'Marta the Scholar',
            'channel' => 'room',
            'delivery_type' => 'room_interjection',
            'text' => '"Knowledge has a price."',
            'context' => [
              'campaign_id' => 17,
              'room_id' => 'room-tavern',
              'generation_source' => 'deterministic',
              'target_entity' => NULL,
              'source_ability' => NULL,
            ],
            'flags' => [
              'interjection' => TRUE,
              'direct_addressed' => FALSE,
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame('gm-room-response-v1', $gmPayload['schema_version']);
    $this->assertSame('Game Master', $gmPayload['speaker']);
    $this->assertTrue($gmPayload['flags']['suppress_npc_interjections']);
    $this->assertSame('room-turn-harness-v1', $harnessPayload['schema_version']);
    $this->assertSame('scholar_npc', $harnessPayload['directly_addressed_npc']);
    $this->assertCount(1, $harnessPayload['npc_turns']);
    $this->assertCount(2, $harnessPayload['turn_sequence']);
    $this->assertTrue(!empty($harnessPayload['turn_logs'][0]['turn_prompt']));
    $this->assertCount(1, $harnessPayload['messages']);

    $roomChatPayload = $service->publicBuildRoomChatResponsePayload([
      'message' => [
        'speaker' => 'Player',
        'message' => 'Marta, what do you know?',
        'type' => 'player',
        'channel' => 'room',
        'timestamp' => '2026-05-17T00:00:00+00:00',
        'character_id' => 263,
        'user_id' => 1,
      ],
      'totalMessages' => 12,
      'dungeon_data' => [
        'rooms' => [
          ['id' => 'room-tavern'],
        ],
      ],
      'gm_response' => [
        'speaker' => 'Game Master',
        'message' => 'The tavern quiets around the question.',
        'type' => 'gm',
        'channel' => 'room',
        'timestamp' => '2026-05-17T00:00:01+00:00',
        'character_id' => NULL,
        'user_id' => 0,
        'gm_payload' => $gmPayload,
      ],
      'npc_interjections' => $harnessPayload['messages'],
      'quest_updates' => [],
      'turn_harness' => $harnessPayload,
      'turn_log_key' => 'room_turn_123',
      'turn_logs' => $harnessPayload['turn_logs'],
      'turn_sequence' => $harnessPayload['turn_sequence'],
      'client_request_id' => 'client-123',
      'npc_interjections_deferred' => FALSE,
    ]);

    $this->assertSame('room-chat-response-v1', $roomChatPayload['schema_version']);
    $this->assertSame(12, $roomChatPayload['totalMessages']);
    $this->assertSame('Game Master', $roomChatPayload['gm_response']['speaker']);
    $this->assertSame('client-123', $roomChatPayload['client_request_id']);
    $this->assertSame($harnessPayload, $roomChatPayload['turn_harness']);

    $queuedContinuationPayload = $service->publicBuildQueuedRoomContinuationPayload([
      'continued' => TRUE,
      'queued_player_count' => 2,
      'queued_player_summary' => "Marta, what do you know?\nTell me about the ledger.",
      'channel' => 'room',
      'gm_response' => $roomChatPayload['gm_response'],
      'canonical_actions' => ['available' => [['id' => 'search-room']]],
      'navigation' => ['destination_room_id' => 'room-archive'],
      'turn_harness' => $harnessPayload,
      'turn_log_key' => 'room_turn_queued_123',
      'turn_logs' => $harnessPayload['turn_logs'],
      'turn_sequence' => $harnessPayload['turn_sequence'],
      'npc_interjections' => $harnessPayload['messages'],
      'npc_interjections_deferred' => FALSE,
      'client_request_id' => 'client-queued-123',
    ]);

    $this->assertSame('queued-room-continuation-v1', $queuedContinuationPayload['schema_version']);
    $this->assertTrue($queuedContinuationPayload['continued']);
    $this->assertSame(2, $queuedContinuationPayload['queued_player_count']);
    $this->assertSame('client-queued-123', $queuedContinuationPayload['client_request_id']);
    $this->assertSame($harnessPayload, $queuedContinuationPayload['turn_harness']);
    $this->assertCount(1, $queuedContinuationPayload['npc_interjections']);
    $this->assertFalse($queuedContinuationPayload['npc_interjections_deferred']);
  }

  /**
   * @covers ::buildGmRoomResponsePayload
   * @covers ::buildRoomTurnHarnessPayload
   * @covers ::buildRoomChatResponsePayload
   * @covers ::buildQueuedRoomContinuationPayload
   */
  public function testQuestUpdatePayloadTruncatesOverlongGeneratedFieldsToCanonicalLimits(): void {
    $payload = $this->roomChatService->publicBuildQuestUpdatePayload(
      str_repeat('q', 220),
      str_repeat('N', 320),
      str_repeat('s', 120),
      [str_repeat('o', 1205), ''],
      'invalid-source',
      str_repeat('t', 220)
    );

    $this->assertSame('quest-update-v1', $payload['schema_version']);
    $this->assertSame(160, strlen($payload['quest_id']));
    $this->assertSame(255, strlen($payload['quest_name']));
    $this->assertSame(64, strlen($payload['status']));
    $this->assertSame('available_quest', $payload['source']);
    $this->assertSame(160, strlen((string) $payload['storyline_id']));
    $this->assertCount(1, $payload['objectives']);
    $this->assertSame(1000, strlen($payload['objectives'][0]));
  }

  /**
   * @covers ::buildRoomTurnHarnessPayload
   */
  public function testBuildRoomTurnHarnessPayloadGeneratesNonEmptyTurnLogKeyWhenMissing(): void {
    $payload = $this->roomChatService->publicBuildRoomTurnHarnessPayload([
      'player' => ['message' => 'Who answers?'],
      'gm' => ['narrative' => 'The room quiets around the question.'],
      'gm_addressed' => FALSE,
      'directly_addressed_npc' => NULL,
      'npc_turns' => [],
      'turn_sequence' => [],
      'turn_log_key' => '',
      'turn_logs' => [],
      'messages' => [],
    ]);

    $this->assertNotSame('', trim((string) $payload['turn_log_key']));
    $this->assertStringStartsWith('room_turn_', (string) $payload['turn_log_key']);
  }

  /**
   * @covers ::buildRoomTurnHarnessPayload
   */
  public function testBuildRoomTurnHarnessPayloadStripsPersistedChatOnlyFieldsFromMessages(): void {
    $payload = $this->roomChatService->publicBuildRoomTurnHarnessPayload([
      'player' => ['message' => 'Tell me about this place.'],
      'gm' => ['narrative' => 'The tavern stills for a moment.'],
      'gm_addressed' => FALSE,
      'directly_addressed_npc' => 'npc_tavern_keeper',
      'npc_turns' => [],
      'turn_sequence' => [],
      'turn_log_key' => 'room_turn_contract',
      'turn_logs' => [],
      'messages' => [
        [
          'speaker' => 'Innkeeper',
          'message' => '"Depends who is asking."',
          'type' => 'npc',
          'channel' => 'room',
          'timestamp' => '2026-06-19T18:00:45+00:00',
          'character_id' => NULL,
          'user_id' => 0,
          'entity_ref' => 'npc_tavern_keeper',
          'interjection' => TRUE,
          'dialogue_payload' => ['schema_version' => 'character-dialogue-v1'],
          'sequence_index' => 12,
          'message_class' => 'authoritative_transcript',
        ],
      ],
    ]);

    $this->assertSame('Innkeeper', $payload['messages'][0]['speaker']);
    $this->assertSame('"Depends who is asking."', $payload['messages'][0]['message']);
    $this->assertArrayNotHasKey('sequence_index', $payload['messages'][0]);
    $this->assertArrayNotHasKey('message_class', $payload['messages'][0]);
    $this->assertSame('npc_tavern_keeper', $payload['messages'][0]['entity_ref']);
    $this->assertTrue($payload['messages'][0]['interjection']);
  }

  /**
   * @covers ::selectBestMatchingQuestLeadCandidate
   * @covers ::extractSpecificQuestLeadTokens
   */
  public function testSelectBestMatchingQuestLeadCandidatePrefersSpecificTopicMatch(): void {
    $match = $this->roomChatService->publicSelectBestMatchingQuestLeadCandidate(
      'Gribbles, I need spellbooks. Got a lead?',
      [
        [
          'giver_npc_id' => 264,
          'giver_name' => 'Eldric',
          'quest_name' => 'Collect Wine Bottles',
          'quest_description' => 'Gather spare bottles for the tavern service.',
          'generated_objectives' => '[]',
        ],
        [
          'giver_npc_id' => 265,
          'giver_name' => 'Marta the Scholar',
          'quest_name' => 'Collect Lost spellbooks',
          'quest_description' => 'Find and collect spellbooks around the tavern.',
          'generated_objectives' => '[{\"objectives\":[{\"description\":\"Find and collect spellbooks\"}]}]',
        ],
      ],
      [266]
    );

    $this->assertNotNull($match);
    $this->assertSame(265, (int) $match['giver_npc_id']);
    $this->assertSame('Marta the Scholar', $match['giver_name']);
  }

  /**
   * @covers ::selectBestMatchingQuestLeadCandidate
   * @covers ::extractSpecificQuestLeadTokens
   */
  public function testSelectBestMatchingQuestLeadCandidateReturnsNullForVagueRequest(): void {
    $match = $this->roomChatService->publicSelectBestMatchingQuestLeadCandidate(
      'Got any work?',
      [
        [
          'giver_npc_id' => 265,
          'giver_name' => 'Marta the Scholar',
          'quest_name' => 'Collect Lost spellbooks',
          'quest_description' => 'Find and collect spellbooks around the tavern.',
          'generated_objectives' => '[{\"objectives\":[{\"description\":\"Find and collect spellbooks\"}]}]',
        ],
      ]
    );

    $this->assertNull($match);
  }

  /**
   * @covers ::looksLikeImplicitLeadRequest
   */
  public function testLooksLikeImplicitLeadRequestDetectsAnyWorkFollowUp(): void {
    $this->assertTrue($this->roomChatService->publicLooksLikeImplicitLeadRequest('what about you marta you have any'));
    $this->assertTrue($this->roomChatService->publicLooksLikeImplicitLeadRequest('what about you gribbles'));
    $this->assertTrue($this->roomChatService->publicLooksLikeImplicitLeadRequest('got any work'));
    $this->assertFalse($this->roomChatService->publicLooksLikeImplicitLeadRequest('what do you think about this room'));
  }

  /**
   * @covers ::buildQuestgiverQuestDialogueLine
   */
  public function testBuildQuestgiverQuestDialogueLineUsesActiveQuestGuidance(): void {
    $line = $this->roomChatService->publicBuildQuestgiverQuestDialogueLine((object) [
      'quest_name' => 'Collect Lost spellbooks',
      'status' => 'active',
      'quest_description' => 'Recover the missing spellbooks.',
      'generated_objectives' => '[{"phase":1,"objectives":[{"description":"Find and collect spellbooks"}]}]',
    ], 'Marta the Scholar');

    $this->assertSame(
      "You're currently working on Collect Lost spellbooks. Start by Find and collect spellbooks",
      $line
    );
  }

  /**
   * @covers ::buildQuestgiverQuestDialogueLine
   */
  public function testBuildQuestgiverQuestDialogueLineUsesOfferForOfferedQuest(): void {
    $line = $this->roomChatService->publicBuildQuestgiverQuestDialogueLine((object) [
      'quest_name' => 'Collect Wine Bottles',
      'status' => 'offered',
      'quest_description' => 'Recover some bottles for the tavern.',
      'generated_objectives' => '[{"phase":1,"objectives":[{"description":"Collect wine bottle from around the tavern"}]}]',
    ], 'Eldric');

    $this->assertSame(
      'I have an assignment for you: Collect Wine Bottles. Collect wine bottle from around the tavern',
      $line
    );
  }

  /**
   * @covers ::buildQuestgiverQuestDialogueLine
   * @covers ::sanitizeQuestgiverObjectiveHintForSpeaker
   */
  public function testBuildQuestgiverQuestDialogueLineStripsSelfReferentialObjectivePrefix(): void {
    $line = $this->roomChatService->publicBuildQuestgiverQuestDialogueLine((object) [
      'quest_name' => 'Gather Storyline Leads in the Tavern',
      'status' => 'active',
      'quest_description' => 'Gather storyline leads from the tavern regulars.',
      'generated_objectives' => '[{"phase":1,"objectives":[{"description":"Speak to Eldric and gather his storyline lead"}]}]',
    ], 'Eldric');

    $this->assertSame(
      "You're currently working on Gather Storyline Leads in the Tavern. Start by Gather his storyline lead",
      $line
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
      ['speaker' => 'System', 'message' => 'Turn order: Narrator -> Game Master -> Eldric 17.', 'type' => 'system', 'internal_log' => TRUE],
      ['speaker' => 'System', 'message' => 'Current turn: Eldric.', 'type' => 'system', 'internal_log' => TRUE],
      ['speaker' => 'Eldric', 'message' => 'I do.', 'type' => 'npc'],
    ];

    $transcript = $this->roomChatService->publicBuildRoomConversationTranscript($chat, 8);
    $observation = $this->roomChatService->publicBuildRoomObservationFromChat($chat, 8);

    $this->assertStringContainsString('Burasco: Who speaks first?', $transcript);
    $this->assertStringContainsString('Eldric: I do.', $transcript);
    $this->assertStringNotContainsString('Turn order:', $transcript);
    $this->assertStringNotContainsString('Current turn:', $observation);
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
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesCanonicalMoveToCommand(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Move to the rat dungeon.'
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
    $this->assertStringContainsString('travel to <location>', $response['narrative']);
    $this->assertStringContainsString('exit via <exit name>', $response['narrative']);
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
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationSupportsExitViaCommand(): void {
    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'Exit via the north passage.'
    );

    $this->assertSame('North Passage', $destination);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationIgnoresNonNavigationUseQuestion(): void {
    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'Why do people use you as a job board?'
    );

    $this->assertNull($destination);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationHandlesLetsHadToTypoAndTalkSuffix(): void {
    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'Lets had to the tavern entrance to talk to Venture-Captain Celia Arvanxi.'
    );

    $this->assertSame('Tavern Entrance', $destination);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationStripsTrailingTurnInIntentClause(): void {
    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'Travel to the old crypt to turn in the relic.'
    );

    $this->assertSame('Old Crypt', $destination);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationResolvesDirectionalExitFromRoomExits(): void {
    $actionProcessor = $this->createMock(GameplayActionProcessor::class);
    $actionProcessor->method('getResolvedRoomExits')
      ->willReturn([
        [
          'name' => 'West Hall',
          'room_id' => 'room-west',
          'connection_type' => 'passage',
          'explored' => TRUE,
        ],
        [
          'name' => 'North Tunnel',
          'room_id' => 'room-north',
          'connection_type' => 'tunnel',
          'explored' => FALSE,
        ],
      ]);

    $this->roomChatService->setActionProcessor($actionProcessor);

    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'Take the north exit.',
      ['name' => 'Crossroads', 'room_id' => 'room-crossroads'],
      'room-crossroads',
      []
    );

    $this->assertSame('North Tunnel', $destination);
  }

  /**
   * @covers ::extractNavigationDestination
   */
  public function testExtractNavigationDestinationAcceptsShortProceedConfirmation(): void {
    $actionProcessor = $this->createMock(GameplayActionProcessor::class);
    $actionProcessor->method('getResolvedRoomExits')
      ->willReturn([
        [
          'name' => 'The Goblin Warrens',
          'room_id' => 'room-warrens',
          'connection_type' => 'passage',
          'explored' => FALSE,
        ],
      ]);

    $this->roomChatService->setActionProcessor($actionProcessor);

    $destination = $this->roomChatService->publicExtractNavigationDestination(
      'Proceed.',
      ['name' => 'Vermin-Ridden Antechamber', 'room_id' => 'room-rats'],
      'room-rats',
      []
    );

    $this->assertSame('The Goblin Warrens', $destination);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentTreatsLetsHadToPhraseAsNavigationTravel(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Lets had to the tavern entrance to talk to Venture-Captain Celia Arvanxi.'
    );

    $this->assertSame('navigation_travel', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentKeepsNonNavigationUseQuestionInDialogueThread(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Why do people use you as a job board?',
      [],
      NULL,
      ['entity_ref' => 'npc_tavern_keeper']
    );

    $this->assertSame('direct_npc_dialogue', $intent);
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
  public function testClassifyRoomTurnIntentRecognizesExpectedOccupantsQuestion(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      "Shouldn't there be kobolds here to meet me?"
    );

    $this->assertSame('room_roster_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesExplicitExitQuestion(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'What exits do I have here?'
    );

    $this->assertSame('navigation_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesFlexibleExpectedOccupantsQuestion(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Where are the people who were supposed to meet us in this chamber?'
    );

    $this->assertSame('room_roster_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentRecognizesFlexibleExitQuestion(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'Which way can we go from here?'
    );

    $this->assertSame('navigation_query', $intent);
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

    $this->assertSame('quest_query', $intent);
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
  public function testClassifyRoomTurnIntentKeepsInformationFollowupOnActiveNpcThread(): void {
    $room_npcs = [
      [
        'entity_ref' => 'gribbles_rindsworth',
        'profile' => [
          'display_name' => 'Gribbles Rindsworth',
        ],
      ],
    ];

    $active_npc = $this->roomChatService->publicResolveActiveDirectConversationNpc(
      [
        ['speaker' => 'Burasco', 'message' => 'Hey Gribbles, I need a quest.', 'type' => 'player', 'channel' => 'room'],
        ['speaker' => 'Game Master', 'message' => 'The space narrows to a direct conversation.', 'type' => 'npc', 'channel' => 'room'],
        ['speaker' => 'Gribbles Rindsworth', 'message' => '"If you are after work, say what kind."', 'type' => 'npc', 'channel' => 'room'],
      ],
      $room_npcs
    );

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      'OK, give me the information',
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
  public function testClassifyRoomTurnIntentKeepsGenericActionOnActiveNpcThread(): void {
    $room_npcs = [
      [
        'entity_ref' => 'gribbles_rindsworth',
        'profile' => [
          'display_name' => 'Gribbles Rindsworth',
        ],
      ],
    ];

    $active_npc = $this->roomChatService->publicResolveActiveDirectConversationNpc(
      [
        ['speaker' => 'Burasco', 'message' => 'Hey Gribbles, I need a quest.', 'type' => 'player', 'channel' => 'room'],
        ['speaker' => 'Game Master', 'message' => 'The space narrows to a direct conversation.', 'type' => 'npc', 'channel' => 'room'],
        ['speaker' => 'Gribbles Rindsworth', 'message' => '"If you are after work, say what kind."', 'type' => 'npc', 'channel' => 'room'],
      ],
      $room_npcs
    );

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      'OK, I search the room.',
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
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentKeepsBriefMerchantFollowupOnActiveNpcThread(): void {
    $active_npc = [
      'entity_ref' => 'eldric',
      'profile' => [
        'display_name' => 'Eldric',
        'role' => 'merchant',
        'motivations' => 'sell food and drink to travelers',
      ],
    ];

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      'One ale, then.',
      [$active_npc],
      NULL,
      $active_npc
    );

    $this->assertSame('direct_npc_transaction', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentKeepsSceneActionOnActiveNpcThread(): void {
    $active_npc = [
      'entity_ref' => 'marta_the_scholar',
      'profile' => [
        'display_name' => 'Marta the Scholar',
      ],
    ];

    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      'I inspect the desk for ledgers.',
      [$active_npc],
      NULL,
      $active_npc
    );

    $this->assertSame('direct_npc_dialogue', $intent);
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
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentTreatsTurnQuestionAsGmQuery(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      "Who's turn is it?"
    );

    $this->assertSame('gm_adjudication_query', $intent);
  }

  /**
   * @covers ::classifyRoomTurnIntent
   */
  public function testClassifyRoomTurnIntentKeepsWaitPhraseOnActiveNpcThread(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntentWithActiveConversation(
      "I'll wait for you Eldric",
      [[
        'entity_ref' => 'tavern_keeper',
        'profile' => ['display_name' => 'Eldric'],
      ]],
      NULL,
      [
        'entity_ref' => 'tavern_keeper',
        'profile' => ['display_name' => 'Eldric'],
      ]
    );

    $this->assertSame('direct_npc_dialogue', $intent);
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
  public function testBuildDeterministicGmResponseCallsOutMissingExpectedOccupants(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'gm_adjudication_query',
      [],
      NULL,
      "Shouldn't there be kobolds here to meet me?",
      [
        'name' => 'Vault Entry Chamber',
        'description' => 'A circular stone chamber with three empty pedestals.',
        'characters' => [
          ['name' => 'Burasco'],
        ],
      ],
      'ltba-vault-entry',
      [],
      FALSE,
      [
        'basicInfo' => [
          'name' => 'Burasco',
        ],
      ]
    );

    $this->assertNotNull($response);
    $this->assertStringContainsString('expected meetup NPCs are currently missing', $response['narrative']);
    $this->assertStringNotContainsString("I'm", $response['narrative']);
  }

  /**
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseBackstopsUnmatchedQuestionWithGroundedAnalysis(): void {
    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'gm_narration',
      [],
      NULL,
      'Could you clarify where the people who were supposed to meet us are?',
      [
        'name' => 'Vault Entry Chamber',
        'description' => 'A circular stone chamber with three empty pedestals.',
        'characters' => [
          ['name' => 'Burasco'],
        ],
      ],
      'ltba-vault-entry',
      [],
      FALSE,
      [
        'basicInfo' => [
          'name' => 'Burasco',
        ],
      ]
    );

    $this->assertNotNull($response);
    $this->assertStringContainsString('expected meetup NPCs are currently missing', $response['narrative']);
    $this->assertTrue($response['suppress_npc_interjections']);
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
   * @covers ::buildDeterministicGmResponse
   */
  public function testBuildDeterministicGmResponseQueuesDirectNpcReplyForNpcTurn(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'tikka', ['display_name' => 'Tikka the Trapmaster', 'attitude' => 'indifferent', 'role' => 'guide', 'motivations' => 'protect the burrow']],
      ]);

    $npc = [
      'entity_ref' => 'tikka',
      'profile' => [
        'display_name' => 'Tikka the Trapmaster',
      ],
    ];

    $response = $this->roomChatService->publicBuildDeterministicGmResponse(
      22,
      'direct_npc_dialogue',
      [$npc],
      $npc,
      'Are you alone Tikka? How big is this Kobold colony?',
      [
        'name' => 'Kobold Burrow',
        'description' => 'A network of small tunnels opens into an organized underground chamber.',
      ],
      'room-burrow',
      [
        'game_state' => [
          'phase' => 'encounter',
        ],
        'rooms' => [
          [
            'room_id' => 'room-burrow',
            'name' => 'Kobold Burrow',
            'description' => 'A network of small tunnels opens into an organized underground chamber.',
          ],
        ],
      ]
    );

    $this->assertNotNull($response);
    $this->assertArrayNotHasKey('suppress_npc_interjections', $response);
    $this->assertTrue($response['suppress_visible_gm_response']);
    $this->assertSame('', $response['narrative']);
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
   */
  public function testDeterministicNpcDialoguePrefersStorylineLeadOverGreeting(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'npc_tavern_keeper', [
          'display_name' => 'Gribbles Rindsworth',
          'attitude' => 'friendly',
          'role' => 'quest_giver',
          'motivations' => 'connect locals with paying work',
        ]],
      ]);

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
      'npc_tavern_keeper',
      'Gribbles Rindsworth',
      'OK, hey Gribbles. I need a storyline.'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Threshold of Knowledge', $reply);
    $this->assertStringContainsString('Okoro of the Open Palm', $reply);
    $this->assertStringNotContainsString('What do you need?', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   * @covers ::buildBrokeredStorylineLeadDialogue
   */
  public function testDeterministicNpcDialogueSurfacesBrokeredStorylinesOneAtATime(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'npc_tavern_keeper', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'quest_giver',
          'motivations' => 'connect travelers with paying work',
        ]],
      ]);

    $relationship_manager = $this->createMock(\Drupal\dungeoncrawler_content\Service\RelationshipManagerService::class);
    $relationship_manager->expects($this->once())
      ->method('getCampaignStorylineContacts')
      ->with(22, 'npc_tavern_keeper')
      ->willReturn([
        [
          'name' => 'Little Trouble in Big Absalom',
          'quest_giver' => [
            'display_name' => 'Grandmother',
          ],
          'lead_location' => [
            'label' => "Grandma's House",
          ],
        ],
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
      'npc_tavern_keeper',
      'Eldric',
      'Any additional jobs for me Eldric?'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Little Trouble in Big Absalom', $reply);
    $this->assertStringContainsString('Grandmother', $reply);
    $this->assertStringNotContainsString('Threshold of Knowledge', $reply);
    $this->assertStringNotContainsString('Also,', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   */
  public function testDeterministicNpcDialogueDoesNotFallbackToGreetingForQuestAskOnInformant(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'gribbles_rindsworth', [
          'display_name' => 'Gribbles Rindsworth',
          'attitude' => 'indifferent',
          'role' => 'neutral',
          'occupation' => 'Tavern regular, petty informant',
          'backstory' => 'He trades rumors and secrets for drinks and coin.',
        ]],
      ]);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'gribbles_rindsworth',
      'Gribbles Rindsworth',
      'Hey Gribbles, I need a quest.'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('what kind of work', strtolower($reply));
    $this->assertStringNotContainsString('What do you need?', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   * @covers ::looksLikeQuestTurnInHandoff
   */
  public function testDeterministicNpcDialoguePrefersQuestHandoffAcknowledgementOverGreeting(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'npc_tavern_keeper', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'quest_giver',
        ]],
      ]);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'npc_tavern_keeper',
      'Eldric',
      'Hey Eldric, here are the wine bottles and the torch components.'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('handoff', strtolower($reply));
    $this->assertStringNotContainsString('What do you need?', $reply);
  }

  /**
   * @covers ::buildDeterministicNpcDialogue
   * @covers ::looksLikeQuestTurnInHandoff
   */
  public function testDeterministicNpcDialogueTreatsHereYouGoStuffAsHandoff(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'npc_tavern_keeper', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'quest_giver',
        ]],
      ]);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'npc_tavern_keeper',
      'Eldric',
      'Eldric, here you go, here is your stuff.'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('handoff', strtolower($reply));
    $this->assertStringNotContainsString('I have work for you', $reply);
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
   * @covers ::buildDeterministicNpcDialogue
   * @covers ::buildGeneratedStorylineLeadDialogue
   */
  public function testDeterministicNpcDialogueIncludesCanonicalQuestContractFromGeneratedBootstrap(): void {
    $this->psychologyService->method('loadProfile')
      ->willReturnMap([
        [22, 'npc_tavern_keeper', [
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'role' => 'quest_giver',
          'motivations' => 'connect travelers with larger story arcs',
        ]],
      ]);

    $storyline_generation = $this->createMock(StorylineGenerationService::class);
    $storyline_generation->expects($this->once())
      ->method('bootstrapCampaignStoryline')
      ->with(22, $this->arrayHasKey('prompt'))
      ->willReturn([
        'storyline' => [
          'name' => 'Threshold of Knowledge',
          'storyline_data' => [
            'metadata' => [
              'generated_outline' => [
                'entry_dungeon' => [
                  'name' => 'Threshold of Knowledge',
                  'lead_location_hint' => 'Follow the lantern marks to the old library stairs.',
                ],
              ],
            ],
          ],
        ],
        'initial_quest' => [
          'quest_name' => 'Collect the Spellbooks',
          'generated_objectives' => json_encode([
            [
              'phase' => 1,
              'objectives' => [
                [
                  'description' => 'Recover 3 missing spellbooks from the bar area.',
                ],
              ],
            ],
          ]),
        ],
      ]);

    $this->roomChatService->setStorylineGenerationService($storyline_generation);

    $reply = $this->roomChatService->publicBuildDeterministicNpcDialogue(
      22,
      'npc_tavern_keeper',
      'Eldric',
      'I need a storyline.',
      'tavern_entrance'
    );

    $this->assertNotNull($reply);
    $this->assertStringContainsString('Collect the Spellbooks', $reply);
    $this->assertStringContainsString('Recover 3 missing spellbooks from the bar area', $reply);
    $this->assertStringContainsString('Threshold of Knowledge', $reply);
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
          'display_name' => 'Grandmother',
          'notes' => 'She asks the kobolds to recover her magical hedge trimmer.',
        ],
        'lead_location' => [
          'label' => "Grandma's House",
        ],
      ],
    ], "If you want work, For Little Trouble in Big Absalom, look for Grandmother at Grandma's House. Also, For Threshold of Knowledge, look for Okoro of the Open Palm at Magaambya Campus.");

    $this->assertCount(2, $matches);
    $storyline_ids = array_values(array_unique(array_map(static fn(array $match): string => (string) ($match['storyline_id'] ?? ''), $matches)));
    sort($storyline_ids);
    $this->assertSame([
      'little-trouble-in-big-absalom',
      'threshold-of-knowledge',
    ], $storyline_ids);
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
  public function testClassifyRoomTurnIntentPrioritizesQuestQueryBeforeMerchantInquiry(): void {
    $intent = $this->roomChatService->publicClassifyRoomTurnIntent(
      'I am looking for work.',
      [
        [
          'entity_ref' => 'tavern_keeper',
          'profile' => [
            'display_name' => 'Eldric',
            'role' => 'quest_giver',
            'motivations' => 'connect travelers with work and keep the bar running',
          ],
        ],
      ]
    );

    $this->assertSame('quest_query', $intent);
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
   * @covers ::buildCompactSessionContext
   */
  public function testBuildCompactSessionContextTruncatesSummaryAndRecentLines(): void {
    $long_summary = str_repeat('Summary sentence. ', 30);
    $long_recent_line = '[ASSISTANT]: ' . str_repeat('A', 220);

    $session_manager = $this->createMock(AiSessionManager::class);
    $session_manager->expects($this->once())
      ->method('buildSessionContext')
      ->with('campaign.31.room_chat.room-2', 31, 2)
      ->willReturn(
        "PRIOR SESSION CONTEXT (summary of earlier interactions):\n{$long_summary}\n\nRECENT CONVERSATION:\n[USER]: Old question\n{$long_recent_line}"
      );

    $this->roomChatService->setSessionManager($session_manager);

    $context = $this->roomChatService->publicBuildCompactSessionContext(
      'campaign.31.room_chat.room-2',
      31,
      2,
      3000,
      80,
      TRUE
    );

    $this->assertStringContainsString('PRIOR SESSION CONTEXT', $context);
    $this->assertStringContainsString(substr($long_summary, 0, 77) . '...', $context);
    $this->assertStringNotContainsString($long_summary, $context);
    $this->assertStringContainsString(substr($long_recent_line, 0, 177) . '...', $context);
    $this->assertStringNotContainsString($long_recent_line, $context);
  }

  /**
   * @covers ::buildCompactSessionContext
   */
  public function testBuildCompactSessionContextPreservesSectionEnvelopeOrder(): void {
    $session_manager = $this->createMock(AiSessionManager::class);
    $session_manager->expects($this->once())
      ->method('buildSessionContext')
      ->with('campaign.44.room_chat.room-9', 44, 2)
      ->willReturn(
        "PRIOR SESSION CONTEXT (summary of earlier interactions):\nEarlier summary.\n\nRECENT CONVERSATION:\n[USER]: first\n[ASSISTANT]: second\n\nSYSTEM NOTES:\nKeep role boundaries."
      );

    $this->roomChatService->setSessionManager($session_manager);

    $context = $this->roomChatService->publicBuildCompactSessionContext(
      'campaign.44.room_chat.room-9',
      44,
      2,
      3000,
      320,
      TRUE
    );

    $lines = array_values(array_filter(
      preg_split("/\r?\n/", $context) ?: [],
      static fn(string $line): bool => $line !== ''
    ));

    $this->assertSame([
      'PRIOR SESSION CONTEXT (summary of earlier interactions):',
      'Earlier summary.',
      'RECENT CONVERSATION:',
      '[USER]: first',
      '[ASSISTANT]: second',
      'SYSTEM NOTES:',
      'Keep role boundaries.',
    ], $lines);
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

  protected function resolveRoomSlugForQuery(int $campaign_id, string $room_id, array $dungeon_data): ?string {
    return $room_id !== '' ? $room_id : NULL;
  }

  protected function loadExistingQuestgiverStoryline(int $campaign_id, string $entity_ref): ?array {
    return NULL;
  }

  protected function resolveCampaignQuestgiverNpcId(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $room_id,
    array $dungeon_data = []
  ): ?int {
    return NULL;
  }

  protected function loadRoomQuestLeadCandidates(int $campaign_id, string $room_id, array $dungeon_data = []): array {
    return [];
  }

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

  public function publicValidateEncounterPlayerTurnForChat(array $dungeon_data, string $channel = 'room', ?int $character_id = NULL, string $type = 'player', string $speaker = ''): ?string {
    return $this->validateEncounterPlayerTurnForChat($dungeon_data, $channel, $character_id, $type, $speaker);
  }

  public function publicBuildVisibleGmNarrative(string $narrative, array $actions = [], ?array $state_diff = NULL, ?array $navigation_result = NULL): string {
    return $this->buildVisibleGmNarrative($narrative, $actions, $state_diff, $navigation_result);
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
    string $room_id = '',
    string $turn_seed = ''
  ): array {
    return $this->buildNpcTurnPlan($room_npcs, $player_message, $gm_narrative, $dungeon_data, $room_id, $turn_seed);
  }

  public function publicResolveAmbientNpcInterjectionPercent(array $npc): int {
    return $this->resolveAmbientNpcInterjectionPercent($npc);
  }

  public function publicResolveNpcCharismaScore(array $npc): int {
    return $this->resolveNpcCharismaScore($npc);
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

  public function setStorylineGenerationService(StorylineGenerationService $storyline_generation_service): void {
    $this->storylineGenerationService = $storyline_generation_service;
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

  public function publicBuildCharacterDialoguePayload(
    int $campaign_id,
    string $room_id,
    ?string $speaker_ref,
    string $speaker_name,
    string $channel,
    string $delivery_type,
    string $text,
    string $generation_source,
    ?string $target_entity = NULL,
    ?string $source_ability = NULL,
    bool $interjection = FALSE,
    bool $direct_addressed = FALSE
  ): array {
    return $this->buildCharacterDialoguePayload(
      $campaign_id,
      $room_id,
      $speaker_ref,
      $speaker_name,
      $channel,
      $delivery_type,
      $text,
      $generation_source,
      $target_entity,
      $source_ability,
      $interjection,
      $direct_addressed
    );
  }

  public function publicBuildCharacterDialogueChatMessage(array $dialogue_payload, ?int $character_id = NULL): array {
    return $this->buildCharacterDialogueChatMessage($dialogue_payload, $character_id);
  }

  public function publicBuildGmRoomResponsePayload(
    string $narrative,
    array $actions = [],
    array $dice_rolls = [],
    bool $suppress_npc_interjections = FALSE
  ): array {
    return $this->buildGmRoomResponsePayload($narrative, $actions, $dice_rolls, $suppress_npc_interjections);
  }

  public function publicBuildRoomTurnHarnessPayload(array $payload): array {
    return $this->buildRoomTurnHarnessPayload($payload);
  }

  public function publicBuildRoomChatResponsePayload(array $payload): array {
    return $this->buildRoomChatResponsePayload($payload);
  }

  public function publicBuildQueuedRoomContinuationPayload(array $payload): array {
    return $this->buildQueuedRoomContinuationPayload($payload);
  }

  public function publicSelectBestMatchingQuestLeadCandidate(string $player_message, array $candidates, array $exclude_giver_npc_ids = []): ?array {
    return $this->selectBestMatchingQuestLeadCandidate($player_message, $candidates, $exclude_giver_npc_ids);
  }

  public function publicLooksLikeImplicitLeadRequest(string $normalized_message): bool {
    return $this->looksLikeImplicitLeadRequest($normalized_message);
  }

  public function publicBuildQuestgiverQuestDialogueLine(object $row, string $display_name): ?string {
    return $this->buildQuestgiverQuestDialogueLine($row, $display_name);
  }

  public function publicBuildQuestUpdatePayload(
    string $quest_id,
    string $quest_name,
    string $status,
    array $objectives,
    string $source,
    string $storyline_id = ''
  ): array {
    return $this->buildQuestUpdatePayload($quest_id, $quest_name, $status, $objectives, $source, $storyline_id);
  }

}
