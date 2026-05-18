<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\PlayerAgentEncounterPolicy;
use Drupal\dungeoncrawler_content\Service\PlayerAgentExplorationPolicy;
use Drupal\dungeoncrawler_content\Service\PlayerAgentHarnessService;
use Drupal\dungeoncrawler_content\Service\PlayerAgentProgressTracker;
use Drupal\dungeoncrawler_content\Service\PlayerAgentRuntimeAdapterInterface;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\dungeoncrawler_content\Service\SessionService;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\PlayerAgentHarnessService
 * @group dungeoncrawler_content
 * @group ai
 */
class PlayerAgentHarnessServiceTest extends UnitTestCase {

  /**
   * @covers ::runStep
   */
  public function testRunStepBuildsIntentAndUpdatesProgress(): void {
    $adapter = $this->createMock(PlayerAgentRuntimeAdapterInterface::class);
    $session_service = $this->createMock(SessionService::class);
    $session_service->method('getCampaignCharacterXp')->willReturn(450);

    $adapter->method('buildSnapshot')->willReturn([
      'success' => TRUE,
      'campaign_id' => 12,
      'phase' => 'exploration',
      'state_version' => 9,
      'event_cursor' => 4,
      'active_room_id' => 'room-a',
      'available_actions' => ['search', 'transition'],
      'visible_npcs' => [],
      'connected_rooms' => [['room_id' => 'room-b']],
      'game_state' => ['phase' => 'exploration'],
    ]);
    $adapter->expects($this->once())
      ->method('submitIntent')
      ->with(12, $this->callback(function (array $intent): bool {
        return $intent['type'] === 'search' && $intent['client_state_version'] === 9;
      }))
      ->willReturn([
        'success' => TRUE,
        'game_state' => ['phase' => 'exploration'],
        'events' => [],
      ]);

    $harness = new PlayerAgentHarnessService(
      $adapter,
      new PlayerAgentExplorationPolicy(),
      new PlayerAgentEncounterPolicy(),
      new PlayerAgentProgressTracker($session_service)
    );

    $result = $harness->runStep(12, ['actor_id' => 'pc-1', 'character_id' => 77], []);

    $this->assertTrue($result['success']);
    $this->assertSame('search', $result['decision']['intent']['type']);
    $this->assertSame(1, $result['run_state']['step_count']);
    $this->assertSame(450, $result['run_state']['progress']['campaign_xp_total']);
    $this->assertContains('room-a', $result['run_state']['memory']['visited_rooms']);
  }

  /**
   * @covers ::runStep
   */
  public function testRunStepOverridesRestWithFallbackAnalysisDecision(): void {
    $adapter = $this->createMock(PlayerAgentRuntimeAdapterInterface::class);
    $session_service = $this->createMock(SessionService::class);
    $session_service->method('getCampaignCharacterXp')->willReturn(450);
    $room_chat = $this->createMock(RoomChatService::class);

    $adapter->method('buildSnapshot')->willReturn([
      'success' => TRUE,
      'campaign_id' => 12,
      'actor_id' => 'pc-1',
      'phase' => 'exploration',
      'state_version' => 9,
      'event_cursor' => 4,
      'active_room_id' => 'room-a',
      'available_actions' => ['rest', 'search'],
      'visible_npcs' => [],
      'connected_rooms' => [],
      'game_state' => ['phase' => 'exploration'],
    ]);
    $room_chat->expects($this->once())
      ->method('suggestPlayerAutomationFallbackDecision')
      ->with(12, 'room-a', 77, $this->isType('array'), $this->isType('array'))
      ->willReturn([
        'type' => 'intent',
        'reason' => 'Search instead of resting again.',
        'intent' => [
          'type' => 'search',
          'actor' => 'pc-1',
          'params' => [
            'automation_goal' => 'rest_loop_recovery',
          ],
        ],
        'decision_meta' => [
          'stage' => 'rest_loop_llm_recovery',
          'priority' => 15,
        ],
      ]);
    $adapter->expects($this->once())
      ->method('submitIntent')
      ->with(12, $this->callback(function (array $intent): bool {
        return $intent['type'] === 'search' && $intent['client_state_version'] === 9;
      }))
      ->willReturn([
        'success' => TRUE,
        'game_state' => ['phase' => 'exploration'],
        'events' => [],
      ]);

    $harness = new PlayerAgentHarnessService(
      $adapter,
      new PlayerAgentExplorationPolicy(),
      new PlayerAgentEncounterPolicy(),
      new PlayerAgentProgressTracker($session_service),
      $room_chat
    );

    $result = $harness->runStep(12, ['actor_id' => 'pc-1', 'character_id' => 77], [
      'memory' => [
        'searched_rooms' => ['room-a'],
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('search', $result['decision']['intent']['type']);
    $this->assertSame('rest_loop_llm_recovery', $result['decision']['decision_meta']['stage']);
  }

  /**
   * @covers ::runStep
   */
  public function testRunStepStopsAutomationWhenFallbackStillRecommendsRest(): void {
    $adapter = $this->createMock(PlayerAgentRuntimeAdapterInterface::class);
    $session_service = $this->createMock(SessionService::class);
    $session_service->method('getCampaignCharacterXp')->willReturn(450);
    $room_chat = $this->createMock(RoomChatService::class);

    $adapter->method('buildSnapshot')->willReturn([
      'success' => TRUE,
      'campaign_id' => 12,
      'actor_id' => 'pc-1',
      'phase' => 'exploration',
      'state_version' => 9,
      'event_cursor' => 4,
      'active_room_id' => 'room-a',
      'available_actions' => ['rest', 'search'],
      'visible_npcs' => [],
      'connected_rooms' => [],
      'game_state' => ['phase' => 'exploration'],
    ]);
    $room_chat->expects($this->once())
      ->method('suggestPlayerAutomationFallbackDecision')
      ->willReturn([
        'type' => 'stop',
        'reason' => 'The best choice is still to rest, so automation should pause.',
        'decision_meta' => [
          'stage' => 'rest_analysis_stop',
          'priority' => 14,
        ],
      ]);
    $adapter->expects($this->never())->method('submitIntent');

    $harness = new PlayerAgentHarnessService(
      $adapter,
      new PlayerAgentExplorationPolicy(),
      new PlayerAgentEncounterPolicy(),
      new PlayerAgentProgressTracker($session_service),
      $room_chat
    );

    $result = $harness->runStep(12, ['actor_id' => 'pc-1', 'character_id' => 77], [
      'memory' => [
        'searched_rooms' => ['room-a'],
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('stop', $result['decision']['type']);
    $this->assertSame('The best choice is still to rest, so automation should pause.', $result['stop_reason']);
  }

}
