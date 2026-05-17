<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CampaignClockService;
use Drupal\dungeoncrawler_content\Service\CampaignTimeResolverService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for campaign time resolution and overlap handling.
 *
 * @group dungeoncrawler_content
 * @group time
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignTimeResolverService
 */
class CampaignTimeResolverServiceTest extends UnitTestCase {

  /**
   * Builds a baseline game state for time-resolution tests.
   */
  protected function makeState(string $phase = 'exploration'): array {
    return [
      'phase' => $phase,
      'exploration' => ['time_elapsed_minutes' => 0],
      'downtime' => ['days_elapsed' => 0],
      CampaignClockService::STATE_KEY => [
        'datetime' => '2024-01-01T08:00:00Z',
        'date' => '2024-01-01',
        'time' => '08:00',
        'timezone' => 'UTC',
        'year' => 2024,
        'month' => 1,
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'weekday' => 'Monday',
        'season' => 'winter',
      ],
      'game_time' => [
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'date' => '2024-01-01',
        'datetime' => '2024-01-01T08:00:00Z',
        'timezone' => 'UTC',
      ],
      CampaignTimeResolverService::TIMED_ACTIVITIES_KEY => [],
    ];
  }

  /**
   * @covers ::applyTimeEffects
   */
  public function testParallelActorsAdvanceOnlyMaxDuration(): void {
    $resolver = new CampaignTimeResolverService(new CampaignClockService());
    $state = $this->makeState();

    $result = $resolver->applyTimeEffects($state, [
      [
        'phase' => 'exploration',
        'action_type' => 'rest',
        'actor_ids' => ['char-a'],
        'duration_minutes' => 60,
      ],
      [
        'phase' => 'exploration',
        'action_type' => 'rest',
        'actor_ids' => ['char-b'],
        'duration_minutes' => 60,
      ],
    ]);

    $this->assertSame(60, $result['elapsed_minutes']);
    $this->assertSame(60, $state['exploration']['time_elapsed_minutes']);
    $this->assertSame('2024-01-01T09:00:00Z', $state['campaign_clock']['datetime']);
    $this->assertCount(2, $state[CampaignTimeResolverService::TIMED_ACTIVITIES_KEY]);
  }

  /**
   * @covers ::applyTimeEffects
   */
  public function testSameActorEffectsSerializeElapsedTime(): void {
    $resolver = new CampaignTimeResolverService(new CampaignClockService());
    $state = $this->makeState();

    $result = $resolver->applyTimeEffects($state, [
      [
        'phase' => 'exploration',
        'action_type' => 'search',
        'actor_ids' => ['char-a'],
        'duration_minutes' => 10,
      ],
      [
        'phase' => 'exploration',
        'action_type' => 'repair',
        'actor_ids' => ['char-a'],
        'duration_minutes' => 20,
      ],
    ]);

    $this->assertSame(30, $result['elapsed_minutes']);
    $this->assertSame(30, $state['exploration']['time_elapsed_minutes']);
    $this->assertSame('2024-01-01T08:30:00Z', $state['campaign_clock']['datetime']);
  }

  /**
   * @covers ::applyTimeEffects
   */
  public function testDowntimeEffectsAdvanceResolvedDayCount(): void {
    $resolver = new CampaignTimeResolverService(new CampaignClockService());
    $state = $this->makeState('downtime');

    $result = $resolver->applyTimeEffects($state, [
      [
        'phase' => 'downtime',
        'action_type' => 'earn_income',
        'actor_ids' => ['char-a'],
        'duration_days' => 1,
      ],
      [
        'phase' => 'downtime',
        'action_type' => 'earn_income',
        'actor_ids' => ['char-b'],
        'duration_days' => 1,
      ],
    ]);

    $this->assertSame(1440, $result['elapsed_minutes']);
    $this->assertSame(1, $state['downtime']['days_elapsed']);
    $this->assertSame('2024-01-02T08:00:00Z', $state['campaign_clock']['datetime']);
  }

  /**
   * @covers ::applyTimeEffects
   */
  public function testScheduledActivitiesCompleteWhenClockCatchesUp(): void {
    $resolver = new CampaignTimeResolverService(new CampaignClockService());
    $state = $this->makeState();

    $initial = $resolver->applyTimeEffects($state, [
      [
        'mode' => 'schedule',
        'phase' => 'exploration',
        'action_type' => 'rest',
        'actor_ids' => ['char-a'],
        'duration_minutes' => 60,
      ],
    ]);

    $this->assertSame(0, $initial['elapsed_minutes']);
    $this->assertSame('active', $state[CampaignTimeResolverService::TIMED_ACTIVITIES_KEY][0]['status']);

    $follow_up = $resolver->applyTimeEffects($state, [
      [
        'phase' => 'exploration',
        'action_type' => 'search',
        'actor_ids' => ['char-b'],
        'duration_minutes' => 60,
      ],
    ]);

    $this->assertSame(60, $follow_up['elapsed_minutes']);
    $this->assertSame('completed', $state[CampaignTimeResolverService::TIMED_ACTIVITIES_KEY][0]['status']);
    $this->assertSame('2024-01-01T09:00:00Z', $state['campaign_clock']['datetime']);
  }

}
