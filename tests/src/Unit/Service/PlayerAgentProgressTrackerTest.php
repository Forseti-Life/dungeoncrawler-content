<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\PlayerAgentProgressTracker;
use Drupal\dungeoncrawler_content\Service\SessionService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\PlayerAgentProgressTracker
 * @group dungeoncrawler_content
 * @group ai
 */
class PlayerAgentProgressTrackerTest extends UnitTestCase {

  /**
   * @covers ::updateRunState
   */
  public function testUpdateRunStateStoresPendingConversationLead(): void {
    $session_service = $this->createMock(SessionService::class);
    $session_service->method('getCampaignCharacterXp')->willReturn(120);
    $tracker = new PlayerAgentProgressTracker($session_service);

    $run_state = $tracker->updateRunState(
      ['character_id' => 230],
      [
        'campaign_id' => 65,
        'phase' => 'exploration',
        'active_room_id' => 'room-tavern',
        'event_cursor' => 0,
        'game_state' => ['phase' => 'exploration'],
      ],
      [
        'type' => 'intent',
        'intent' => [
          'type' => 'talk',
          'target' => 'npc-gribbles',
          'params' => [
            'automation_goal' => 'paid_work_fallback',
          ],
        ],
      ],
      [
        'success' => TRUE,
        'game_state' => ['phase' => 'exploration'],
        'events' => [],
        'result' => [
          'npc_interjections' => [
            [
              'message' => 'Town guard has been jumpy about missing livestock and strange lights near the cemetery north of town.',
            ],
          ],
        ],
      ],
      []
    );

    $this->assertSame('npc-gribbles', $run_state['memory']['pending_conversation_lead']['target']);
    $this->assertSame('room-tavern', $run_state['memory']['pending_conversation_lead']['room_id']);
    $this->assertStringContainsString('cemetery', strtolower($run_state['memory']['pending_conversation_lead']['excerpt']));
  }

  /**
   * @covers ::updateRunState
   */
  public function testConversationFollowUpClearsPendingLead(): void {
    $session_service = $this->createMock(SessionService::class);
    $session_service->method('getCampaignCharacterXp')->willReturn(120);
    $tracker = new PlayerAgentProgressTracker($session_service);

    $run_state = $tracker->updateRunState(
      ['character_id' => 230],
      [
        'campaign_id' => 65,
        'phase' => 'exploration',
        'active_room_id' => 'room-tavern',
        'event_cursor' => 0,
        'game_state' => ['phase' => 'exploration'],
      ],
      [
        'type' => 'intent',
        'intent' => [
          'type' => 'talk',
          'target' => 'npc-gribbles',
          'params' => [
            'automation_goal' => 'conversation_follow_up',
          ],
        ],
      ],
      [
        'success' => TRUE,
        'game_state' => ['phase' => 'exploration'],
        'events' => [],
        'result' => [],
      ],
      [
        'memory' => [
          'pending_conversation_lead' => [
            'target' => 'npc-gribbles',
            'room_id' => 'room-tavern',
            'excerpt' => 'Town guard mentioned strange lights near the cemetery.',
          ],
        ],
      ]
    );

    $this->assertArrayNotHasKey('pending_conversation_lead', $run_state['memory']);
  }

}
