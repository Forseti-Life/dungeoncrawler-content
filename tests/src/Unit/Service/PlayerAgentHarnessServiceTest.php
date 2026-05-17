<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\PlayerAgentEncounterPolicy;
use Drupal\dungeoncrawler_content\Service\PlayerAgentExplorationPolicy;
use Drupal\dungeoncrawler_content\Service\PlayerAgentHarnessService;
use Drupal\dungeoncrawler_content\Service\PlayerAgentProgressTracker;
use Drupal\dungeoncrawler_content\Service\PlayerAgentRuntimeAdapterInterface;
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

}
