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
      'navigation_travel',
      [],
      NULL,
      "Then I leave for the rat dungeon",
      ['name' => 'The Gilded Tankard']
    );

    $this->assertNotNull($response);
    $this->assertSame('navigate_to_location', $response['actions'][0]['type']);
    $this->assertSame('Rat Dungeon', $response['actions'][0]['details']['destination']);
    $this->assertStringContainsString('sets out toward Rat Dungeon', $response['narrative']);
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
    $this->assertStringContainsString('combat begins', strtolower($response['narrative']));
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
    string $intent,
    array $room_npcs,
    ?array $directly_addressed_npc,
    string $player_message,
    array $room_meta = [],
    string $room_id = '',
    array $dungeon_data = []
  ): ?array {
    return $this->buildDeterministicGmResponse($intent, $room_npcs, $directly_addressed_npc, $player_message, $room_meta, $room_id, $dungeon_data);
  }

  public function publicExtractNavigationDestination(string $player_message, array $room_meta = []): ?string {
    return $this->extractNavigationDestination($player_message, $room_meta);
  }

  public function publicTrimIncompleteNarrative(string $narrative): string {
    return $this->trimIncompleteNarrative($narrative);
  }

  public function publicStripPlayerVisibleActionBlocks(string $narrative): string {
    return $this->stripPlayerVisibleActionBlocks($narrative);
  }

}
