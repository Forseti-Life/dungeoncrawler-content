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

}
