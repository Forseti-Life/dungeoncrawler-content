<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests Survival action behavior in ExplorationPhaseHandler.
 *
 * Covers: Sense Direction, Cover Tracks, Track — proficiency gates, degree
 * outcomes, and edge cases from dc-cr-skills-survival-track-direction AC.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerSurvivalActionTest extends UnitTestCase {

  // ---------------------------------------------------------------------------
  // Sense Direction
  // ---------------------------------------------------------------------------

  /**
   * Sense Direction in clear conditions auto-succeeds with no check.
   *
   * @covers ::processIntent
   */
  public function testSenseDirectionAutoSucceedsInClearConditions(): void {
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'sense_direction',
        'actor'  => 'char-1',
        'params' => ['condition' => 'clear'],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $r = $result['result'] ?? [];
    $this->assertTrue($r['direction_known']);
    $this->assertSame('auto_success', $r['degree']);
    $this->assertNull($r['dc']);
  }

  /**
   * Sense Direction in supernatural darkness requires a Survival check.
   *
   * @covers ::processIntent
   */
  public function testSenseDirectionRequiresCheckInSupernaturalDarkness(): void {
    // Die 15 + 5 bonus = 20 vs DC 25 (15 base + 10 supernatural) → failure.
    $handler = $this->buildHandler(15);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'sense_direction',
        'actor'  => 'char-1',
        'params' => ['condition' => 'supernatural', 'survival_bonus' => 5],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $r = $result['result'] ?? [];
    $this->assertSame(25, $r['dc']);
    $this->assertFalse($r['direction_known']);
  }

  /**
   * Sense Direction critical success also provides a distance estimate.
   *
   * @covers ::processIntent
   */
  public function testSenseDirectionCritSuccessGrantsDistanceEstimate(): void {
    // Die 20 + 10 = 30 vs DC 15 (base) → crit success (beats DC by 10+).
    $handler = $this->buildHandler(20);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'sense_direction',
        'actor'  => 'char-1',
        'params' => ['condition' => 'featureless_plane', 'survival_bonus' => 10],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $r = $result['result'] ?? [];
    $this->assertTrue($r['direction_known']);
    $this->assertTrue($r['distance_estimate']);
    $this->assertSame('critical_success', $r['degree']);
  }

  // ---------------------------------------------------------------------------
  // Cover Tracks
  // ---------------------------------------------------------------------------

  /**
   * Cover Tracks requires at least Trained proficiency.
   *
   * @covers ::processIntent
   */
  public function testCoverTracksBlockedWithoutTrainedProficiency(): void {
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'cover_tracks',
        'actor'  => 'char-1',
        'params' => ['proficiency_rank' => 0, 'survival_bonus' => 5],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Trained', $result['result']['error'] ?? '');
  }

  /**
   * Cover Tracks sets tracks_covered flag and pursuer_dc on entity state.
   *
   * @covers ::processIntent
   */
  public function testCoverTracksSetsEntityFlagAndPursuerDc(): void {
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'cover_tracks',
        'actor'  => 'char-1',
        'params' => ['proficiency_rank' => 1, 'survival_bonus' => 4],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertTrue($result['result']['tracks_covered'] ?? FALSE);
    $this->assertSame(14, $result['result']['pursuer_dc'] ?? NULL);
    // Flag also written to game_state entity_states.
    $this->assertTrue($game_state['entity_states']['char-1']['tracks_covered'] ?? FALSE);
    $this->assertSame(14, $game_state['entity_states']['char-1']['tracks_pursuer_dc'] ?? NULL);
  }

  // ---------------------------------------------------------------------------
  // Track
  // ---------------------------------------------------------------------------

  /**
   * Track requires at least Trained proficiency.
   *
   * @covers ::processIntent
   */
  public function testTrackBlockedWithoutTrainedProficiency(): void {
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'track',
        'actor'  => 'char-1',
        'params' => ['proficiency_rank' => 0, 'trail_id' => 'trail-a'],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Trained', $result['result']['error'] ?? '');
  }

  /**
   * Track critical failure permanently loses the trail.
   *
   * @covers ::processIntent
   */
  public function testTrackCritFailPermanentlyLosesTrail(): void {
    // Die 1 + 0 = 1 vs DC 20 → crit failure (total ≤ DC − 10).
    $handler = $this->buildHandler(1);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'track',
        'actor'  => 'char-1',
        'params' => [
          'proficiency_rank' => 1,
          'survival_bonus'   => 0,
          'trail_id'         => 'trail-x',
          'trail_age'        => 'today',
          'terrain'          => 'default',
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $r = $result['result'] ?? [];
    $this->assertSame('critical_failure', $r['degree'] ?? NULL);
    // Trail is now permanently lost.
    $this->assertTrue($game_state['track_lost']['char-1:trail-x'] ?? FALSE);
  }

  /**
   * Track after crit fail returns a permanent-lost error.
   *
   * @covers ::processIntent
   */
  public function testTrackRetryAfterCritFailBlocked(): void {
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalGameState();
    $game_state['track_lost']['char-1:trail-x'] = TRUE;
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'track',
        'actor'  => 'char-1',
        'params' => [
          'proficiency_rank' => 1,
          'survival_bonus'   => 10,
          'trail_id'         => 'trail-x',
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('permanently lost', $result['result']['error'] ?? '');
  }

  /**
   * Track success grants progress; crit success grants full speed.
   *
   * @covers ::processIntent
   */
  public function testTrackCritSuccessGrantsFullSpeedProgress(): void {
    // Die 20 + 10 = 30 vs DC 20 → crit success (≥ DC + 10).
    $handler = $this->buildHandler(20);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'track',
        'actor'  => 'char-1',
        'params' => [
          'proficiency_rank' => 1,
          'survival_bonus'   => 10,
          'trail_id'         => 'trail-y',
          'trail_age'        => 'today',
          'terrain'          => 'default',
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $r = $result['result'] ?? [];
    $this->assertSame('critical_success', $r['degree'] ?? NULL);
    $this->assertTrue($r['progress'] ?? FALSE);
    $this->assertTrue($r['full_speed'] ?? FALSE);
  }

  /**
   * Cover Tracks pursuer_dc overrides default Trail DC when Track is called.
   *
   * @covers ::processIntent
   */
  public function testTrackUsesCovertTracksDcWhenPresent(): void {
    // Die 10 + 0 = 10 vs cover_tracks_pursuer_dc 20 → failure.
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalGameState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'track',
        'actor'  => 'char-1',
        'params' => [
          'proficiency_rank'      => 1,
          'survival_bonus'        => 0,
          'trail_id'              => 'trail-z',
          'cover_tracks_pursuer_dc' => 20,
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $r = $result['result'] ?? [];
    $this->assertSame(20, $r['dc'] ?? NULL);
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Builds ExplorationPhaseHandler with a deterministic die roller.
   */
  private function buildHandler(int $die_result = 10): ExplorationPhaseHandler {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->method('rollPathfinderDie')->willReturn($die_result);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new ExplorationPhaseHandler(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $this->createMock(CharacterStateService::class),
      $roller,
      $this->createMock(AiGmService::class)
    );
  }

  /**
   * Returns a minimal game_state array for exploration tests.
   */
  private function minimalGameState(): array {
    return [
      'phase'         => 'exploration',
      'entity_states' => [],
      'track_lost'    => [],
    ];
  }

}
