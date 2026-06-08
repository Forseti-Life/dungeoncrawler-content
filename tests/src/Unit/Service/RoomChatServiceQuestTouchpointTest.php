<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers room-chat quest touchpoint hint handoff.
 *
 * @group dungeoncrawler_content
 * @group quest
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChatService
 */
class RoomChatServiceQuestTouchpointTest extends UnitTestCase {

  /**
   * Objective hints are forwarded into conversation touchpoint ingestion.
   *
   * @covers ::applyConversationQuestTouchpoint
   */
  public function testApplyConversationQuestTouchpointUsesObjectiveHint(): void {
    $quest_touchpoint = $this->createMock(QuestTouchpointService::class);
    $quest_touchpoint->expects($this->once())
      ->method('ingestEvent')
      ->with(85, [
        'character_id' => 99,
        'touchpoint' => [
          'objective_type' => 'interact',
          'objective_id' => 'escort_to_safety_runtime_1',
          'npc_ref' => 'Guard Captain',
          'entity_ref' => 'npc-guard',
          'room_id' => 'crossroads',
          'confidence' => 'high',
          'quantity' => 1,
          'matching_mode' => 'typed_receipt',
        ],
      ])
      ->willReturn([
        'success' => TRUE,
        'decision' => 'APPLY_PROGRESS',
        'quest_id' => 'rescue_merchant',
        'objective_id' => 'escort_to_safety_runtime_1',
      ]);

    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedApplyConversationQuestTouchpoint(int $campaign_id, ?int $character_id, string $room_id, string $npc_ref, string $target_name = '', array $quest_touchpoint_hint = []): void {
        $this->applyConversationQuestTouchpoint($campaign_id, $character_id, $room_id, $npc_ref, $target_name, $quest_touchpoint_hint);
      }
    };

    $this->setProtectedProperty($service, 'questTouchpointService', $quest_touchpoint);
    $this->setProtectedProperty($service, 'logger', $this->createMock(LoggerInterface::class));

    $service->exposedApplyConversationQuestTouchpoint(
      85,
      99,
      'crossroads',
      'npc-guard',
      'Guard Captain',
      [
        'objective_type' => 'interact',
        'objective_id' => 'escort_to_safety_runtime_1',
        'entity_ref' => 'npc-guard',
      ]
    );
  }

  /**
   * Missing quest hints still use the default touchpoint contract.
   *
   * @covers ::activateMentionedAvailableQuests
   */
  public function testActivateMentionedAvailableQuestsDefaultsQuestHintToArray(): void {
    $quest_tracker = $this->createMock(\Drupal\dungeoncrawler_content\Service\QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('findMentionedAvailableQuests')
      ->with(93, 'tavern_entrance', 361, 'Eldric gives you their full attention and prepares to answer directly.', 2, 5)
      ->willReturn([]);

    $quest_touchpoint = $this->createMock(QuestTouchpointService::class);
    $quest_touchpoint->expects($this->once())
      ->method('ingestEvent')
      ->with(93, [
        'character_id' => 361,
        'touchpoint' => [
          'objective_type' => 'interact',
          'objective_id' => '',
          'npc_ref' => 'Eldric',
          'entity_ref' => 'npc_tavern_keeper',
          'room_id' => 'tavern_entrance',
          'confidence' => 'high',
          'quantity' => 1,
          'matching_mode' => 'direct_npc_dialogue',
        ],
      ])
      ->willReturn([
        'success' => TRUE,
        'decision' => 'APPLY_PROGRESS',
      ]);

    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedActivateMentionedAvailableQuests(
        int $campaign_id,
        string $room_id,
        ?int $character_id,
        array $dungeon_data,
        ?array $gm_response,
        array $npc_interjections,
        array $quest_touchpoint_hint = []
      ): array {
        return $this->activateMentionedAvailableQuests(
          $campaign_id,
          $room_id,
          $character_id,
          $dungeon_data,
          $gm_response,
          $npc_interjections,
          $quest_touchpoint_hint
        );
      }

      protected function resolveRoomSlugForQuery(int $campaign_id, string $room_id, array $dungeon_data): ?string {
        return $room_id;
      }

      protected function activateMentionedBrokeredStorylineQuests(
        int $campaign_id,
        string $room_id,
        string $location_id,
        int $character_id,
        string $combined_text,
        array $message_entries = []
      ): array {
        return [];
      }

      protected function looksLikeQuestOrLeadRequest(string $text): bool {
        return FALSE;
      }
    };

    $this->setProtectedProperty($service, 'questTracker', $quest_tracker);
    $this->setProtectedProperty($service, 'questTouchpointService', $quest_touchpoint);
    $this->setProtectedProperty($service, 'logger', $this->createMock(LoggerInterface::class));

    $result = $service->exposedActivateMentionedAvailableQuests(
      93,
      'tavern_entrance',
      361,
      ['rooms' => [['room_id' => 'tavern_entrance', 'name' => 'Tavern Entrance']]],
      [
        'entity_ref' => 'npc_tavern_keeper',
        'speaker_name' => 'Eldric',
        'message' => 'Eldric gives you their full attention and prepares to answer directly.',
      ],
      []
    );

    $this->assertSame([], $result);
  }

  /**
   * Player quest wording is included in dialogue matching even when NPC reply is generic.
   *
   * @covers ::activateMentionedAvailableQuests
   */
  public function testActivateMentionedAvailableQuestsIncludesPlayerMessageInQuestMatching(): void {
    $quest_tracker = $this->createMock(\Drupal\dungeoncrawler_content\Service\QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('findMentionedAvailableQuests')
      ->with(200, 'tavern_entrance', 735, 'I have a quest that says there are four spellbooks in this tavern.', 2, 5)
      ->willReturn([]);

    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedActivateMentionedAvailableQuests(
        int $campaign_id,
        string $room_id,
        ?int $character_id,
        array $dungeon_data,
        ?array $gm_response,
        array $npc_interjections,
        array $quest_touchpoint_hint = [],
        string $player_message = ''
      ): array {
        return $this->activateMentionedAvailableQuests(
          $campaign_id,
          $room_id,
          $character_id,
          $dungeon_data,
          $gm_response,
          $npc_interjections,
          $quest_touchpoint_hint,
          $player_message
        );
      }

      protected function resolveRoomSlugForQuery(int $campaign_id, string $room_id, array $dungeon_data): ?string {
        return $room_id;
      }

      protected function activateMentionedBrokeredStorylineQuests(
        int $campaign_id,
        string $room_id,
        string $location_id,
        int $character_id,
        string $combined_text,
        array $message_entries = []
      ): array {
        return [];
      }

      protected function looksLikeQuestOrLeadRequest(string $text): bool {
        return FALSE;
      }
    };

    $this->setProtectedProperty($service, 'questTracker', $quest_tracker);
    $this->setProtectedProperty($service, 'logger', $this->createMock(LoggerInterface::class));

    $result = $service->exposedActivateMentionedAvailableQuests(
      200,
      'tavern_entrance',
      735,
      ['rooms' => [['room_id' => 'tavern_entrance', 'name' => 'The Gilded Tankard']]],
      NULL,
      [],
      [],
      'I have a quest that says there are four spellbooks in this tavern.'
    );

    $this->assertSame([], $result);
  }

  /**
   * Explicit player "how do I complete this quest" requests promote leads to offers.
   *
   * @covers ::resolveSurfacedQuestStatus
   */
  public function testResolveSurfacedQuestStatusPromotesLeadOnActivationRequest(): void {
    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedResolveSurfacedQuestStatus(string $current_status, string $dialogue_text, bool $promote = FALSE): string {
        return $this->resolveSurfacedQuestStatus($current_status, $dialogue_text, $promote);
      }
    };

    $promoted = $service->exposedResolveSurfacedQuestStatus(
      'lead',
      'I have a quest that says there are four spellbooks here. How do I search for them and complete it?',
      TRUE
    );
    $this->assertSame('offered', $promoted);

    $unchanged = $service->exposedResolveSurfacedQuestStatus(
      'lead',
      'The tavern is noisy tonight.'
    );
    $this->assertSame('lead', $unchanged);
  }

  /**
   * Offered quests stay offered unless explicitly activated elsewhere.
   *
   * @covers ::resolveSurfacedQuestStatus
   */
  public function testResolveSurfacedQuestStatusPreservesOfferedState(): void {
    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedResolveSurfacedQuestStatus(string $current_status, string $dialogue_text, bool $promote = FALSE): string {
        return $this->resolveSurfacedQuestStatus($current_status, $dialogue_text, $promote);
      }
    };

    $status = $service->exposedResolveSurfacedQuestStatus(
      'offered',
      'I heard a rumor but I am not accepting anything yet.'
    );
    $this->assertSame('offered', $status);
  }

  /**
   * Current-room lead quests are included in GM questbook context.
   *
   * @covers ::buildRoomQuestbookPromptContext
   */
  public function testRoomQuestbookContextIncludesRoomLeadObjectives(): void {
    $quest_tracker = $this->createMock(\Drupal\dungeoncrawler_content\Service\QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('getCampaignQuestTracking')
      ->with(107)
      ->willReturn([
        [
          'quest_id' => 'collect_spellbooks_107',
          'quest_name' => 'Collect Lost Spellbooks',
          'status' => 'lead',
          'location_id' => 'tavern_entrance',
          'generated_objectives' => json_encode([
            [
              'phase' => 1,
              'objectives' => [[
                'objective_id' => 'collect_books',
                'type' => 'collect',
                'description' => 'Find and collect Spellbooks in The Gilded Tankard',
                'item' => 'Spellbooks',
                'current' => 0,
                'target_count' => 4,
                'next_step' => 'Search The Gilded Tankard and pick up each Spellbook quest item.',
                'completion_criteria' => [
                  'description' => 'Collect 4 Spellbooks in The Gilded Tankard.',
                ],
              ]],
            ],
          ]),
          'objective_states' => '',
          'current_phase' => 1,
        ],
        [
          'quest_id' => 'other_room_quest',
          'quest_name' => 'Other Room Quest',
          'status' => 'lead',
          'location_id' => 'upstairs',
          'generated_objectives' => '[]',
          'objective_states' => '',
        ],
      ]);

    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedBuildRoomQuestbookPromptContext(int $campaign_id, string $room_id, ?int $character_id): string {
        return $this->buildRoomQuestbookPromptContext($campaign_id, $room_id, $character_id);
      }
    };

    $this->setProtectedProperty($service, 'questTracker', $quest_tracker);

    $context = $service->exposedBuildRoomQuestbookPromptContext(107, 'tavern_entrance', 417);

    $this->assertStringContainsString('=== ROOM QUESTBOOK CONTEXT ===', $context);
    $this->assertStringContainsString('Collect Lost Spellbooks', $context);
    $this->assertStringContainsString('Find and collect Spellbooks in The Gilded Tankard (0/4)', $context);
    $this->assertStringContainsString('Search The Gilded Tankard', $context);
    $this->assertStringContainsString('Collect 4 Spellbooks in The Gilded Tankard.', $context);
    $this->assertStringNotContainsString('Other Room Quest', $context);
  }

  /**
   * Set a protected property on the test double.
   */
  private function setProtectedProperty(object $object, string $property_name, mixed $value): void {
    $reflection = new \ReflectionProperty(RoomChatService::class, $property_name);
    $reflection->setAccessible(TRUE);
    $reflection->setValue($object, $value);
  }

}
