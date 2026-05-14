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
    $this->assertCount(1, $plan['ordered_npcs']);
    $this->assertSame('tavern_keeper', $plan['ordered_npcs'][0]['entity_ref']);
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

  public function publicBuildRoomConversationTranscript(array $chat, int $limit = 8): string {
    return $this->buildRoomConversationTranscript($chat, $limit);
  }

  public function publicBuildRoomObservationFromChat(array $chat, int $limit = 8): string {
    return $this->buildRoomObservationFromChat($chat, $limit);
  }

  public function publicClassifyRoomTurnIntent(string $player_message, array $room_npcs = [], ?array $directly_addressed_npc = NULL): string {
    return $this->classifyRoomTurnIntent($player_message, $room_npcs, $directly_addressed_npc);
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
    bool $is_room_entry = FALSE
  ): ?array {
    return $this->buildDeterministicGmResponse($campaign_id, $intent, $room_npcs, $directly_addressed_npc, $player_message, $room_meta, $room_id, $dungeon_data, $is_room_entry);
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

  public function publicRecordLocationTransition(array &$dungeon_data, array $origin_room_meta, array $navigation_result): void {
    $this->recordLocationTransition($dungeon_data, $origin_room_meta, $navigation_result);
  }

  public function publicIsEffectiveRoomEntryTurn(array $chat): bool {
    return $this->isEffectiveRoomEntryTurn($chat);
  }

  public function publicBuildNpcTurnPlan(array $room_npcs, string $player_message, string $gm_narrative): array {
    return $this->buildNpcTurnPlan($room_npcs, $player_message, $gm_narrative);
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

}
