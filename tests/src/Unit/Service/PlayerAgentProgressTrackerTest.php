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
    $this->assertNotEmpty($run_state['memory']['pending_conversation_lead']['signature']);
    $this->assertSame(0, $run_state['memory']['pending_conversation_lead']['follow_up_attempts']);
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
    $this->assertNotEmpty($run_state['memory']['exhausted_conversation_leads']);
  }

  /**
   * @covers ::updateRunState
   */
  public function testRepeatedLeadIsNotRequeuedAfterFollowUpExhaustsIt(): void {
    $session_service = $this->createMock(SessionService::class);
    $session_service->method('getCampaignCharacterXp')->willReturn(120);
    $tracker = new PlayerAgentProgressTracker($session_service);
    $signature = sha1('npc-gribbles|room-tavern|town guard mentioned strange lights near the cemetery.');

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
              'message' => 'Town guard mentioned strange lights near the cemetery.',
            ],
          ],
        ],
      ],
      [
        'memory' => [
          'exhausted_conversation_leads' => [$signature],
        ],
      ]
    );

    $this->assertArrayNotHasKey('pending_conversation_lead', $run_state['memory']);
  }

  /**
   * @covers ::updateRunState
   */
  public function testTraceCapturesDecisionMeta(): void {
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
        'type' => 'wait',
        'reason' => 'Nothing to do.',
        'decision_meta' => [
          'stage' => 'wait',
          'priority' => 110,
        ],
      ],
      [
        'success' => TRUE,
        'game_state' => ['phase' => 'exploration'],
        'events' => [],
      ],
      []
    );

    $this->assertSame('wait', $run_state['trace'][0]['decision_meta']['stage']);
    $this->assertSame(110, $run_state['trace'][0]['decision_meta']['priority']);
  }

}
