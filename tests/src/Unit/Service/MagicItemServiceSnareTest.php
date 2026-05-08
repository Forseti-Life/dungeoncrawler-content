<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\MagicItemService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests snare placement and trigger behavior in MagicItemService.
 *
 * @group dungeoncrawler_content
 * @group magic_items
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MagicItemService
 */
class MagicItemServiceSnareTest extends UnitTestCase {

  /**
   * Craft Snare requires the snare crafting feat.
   *
   * @covers ::craftSnare
   */
  public function testCraftSnareRequiresFeat(): void {
    $service = $this->buildService();
    $game_state = [];

    $result = $service->craftSnare(
      ['snare_type' => 'alarm'],
      [
        'id' => 'char-001',
        'inventory' => ['snare_kit' => TRUE],
        'feats' => [],
      ],
      'room-1',
      $game_state
    );

    $this->assertFalse($result['success']);
    $this->assertSame(['missing_feat: Snare Crafting feat required.'], $result['failures']);
  }

  /**
   * Craft Snare stores a placed snare with creator and DC metadata.
   *
   * @covers ::craftSnare
   */
  public function testCraftSnarePlacesSnareInGameState(): void {
    $service = $this->buildService();
    $game_state = [];

    $result = $service->craftSnare(
      [
        'id' => 'snare-a',
        'snare_type' => 'alarm',
        'placed_square' => ['q' => 2, 'r' => 3],
        'level' => 2,
      ],
      [
        'id' => 'char-001',
        'inventory' => ['snare_kit' => TRUE],
        'feats' => [['id' => 'snare-crafting']],
        'skills' => ['crafting' => ['rank' => 2, 'bonus' => 7]],
      ],
      'room-1',
      $game_state
    );

    $this->assertTrue($result['success']);
    $this->assertSame('snare-a', $result['snare']['snare_id']);
    $this->assertSame('char-001', $result['snare']['creator_id']);
    $this->assertSame(17, $result['snare']['detection_dc']);
    $this->assertTrue($result['snare']['requires_search']);
    $this->assertSame('alarm', $game_state['snares']['room-1'][0]['snare_type']);
  }

  /**
   * Craft Snare rejects occupied squares.
   *
   * @covers ::craftSnare
   */
  public function testCraftSnareRejectsOccupiedSquare(): void {
    $service = $this->buildService();
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'triggered' => FALSE,
            'disarmed' => FALSE,
            'placed_square' => ['q' => 4, 'r' => 5],
          ],
        ],
      ],
    ];

    $result = $service->craftSnare(
      [
        'snare_type' => 'hampering',
        'placed_square' => ['q' => 4, 'r' => 5],
      ],
      [
        'id' => 'char-001',
        'inventory' => ['snare_kit' => TRUE],
        'feats' => [['id' => 'snare-crafting']],
        'skills' => ['crafting' => ['rank' => 1, 'bonus' => 5]],
      ],
      'room-1',
      $game_state
    );

    $this->assertFalse($result['success']);
    $this->assertSame(['occupied_square: A snare already occupies that square.'], $result['failures']);
  }

  /**
   * Entering a snared square triggers the first active snare there.
   *
   * @covers ::checkSnareAtHex
   * @covers ::triggerSnare
   */
  public function testCheckSnareAtHexTriggersAlarmSnare(): void {
    $service = $this->buildService();
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id' => 'snare-a',
            'snare_type' => 'alarm',
            'location_id' => 'room-1',
            'placed_square' => ['q' => 1, 'r' => 2],
            'triggered' => FALSE,
            'disarmed' => FALSE,
            'level' => 1,
          ],
        ],
      ],
    ];

    $result = $service->checkSnareAtHex('target-1', 'room-1', ['q' => 1, 'r' => 2], $game_state);

    $this->assertSame('alarm', $result['snare_type']);
    $this->assertSame('target-1', $result['target_id']);
    $this->assertTrue($game_state['snares']['room-1'][0]['triggered']);
    $this->assertSame('snare-a', $game_state['snare_alarms'][0]['snare_id']);
  }

  /**
   * Builds the service with a deterministic dice roller mock.
   */
  private function buildService(int $die_result = 4): MagicItemService {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->method('rollPathfinderDie')->willReturn($die_result);

    return new MagicItemService($roller);
  }

  // -------------------------------------------------------------------------
  // Creator instant-disarm (no check required)
  // -------------------------------------------------------------------------

  /**
   * @covers ::creatorDisarmsSnare
   */
  public function testCreatorDisarmsOwnSnareWithoutCheck(): void {
    $service = $this->buildService();
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id'   => 'snare-x',
            'creator_id' => 'char-001',
            'triggered'  => FALSE,
            'disarmed'   => FALSE,
          ],
        ],
      ],
    ];

    $result = $service->creatorDisarmsSnare('char-001', 'room-1', 0, $game_state);

    $this->assertTrue($result['success']);
    $this->assertTrue($game_state['snares']['room-1'][0]['disarmed']);
  }

  /**
   * @covers ::creatorDisarmsSnare
   */
  public function testNonCreatorCannotInstantDisarm(): void {
    $service = $this->buildService();
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id'   => 'snare-x',
            'creator_id' => 'char-001',
            'triggered'  => FALSE,
            'disarmed'   => FALSE,
          ],
        ],
      ],
    ];

    $result = $service->creatorDisarmsSnare('char-002', 'room-1', 0, $game_state);

    $this->assertFalse($result['success']);
    $this->assertFalse($game_state['snares']['room-1'][0]['disarmed']);
  }

  // -------------------------------------------------------------------------
  // Expert-crafter detection guard (active search required)
  // -------------------------------------------------------------------------

  /**
   * @covers ::detectSnareAtHex
   */
  public function testExpertSnareBlocksPassiveDetection(): void {
    // Die roll 4 + 5 bonus = 9, DC 15 → fail even if allowed; but the block
    // must happen before the roll (reason = requires_active_search).
    $service = $this->buildService(4);
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id'        => 'snare-e',
            'placed_square'   => ['q' => 1, 'r' => 1],
            'requires_search' => TRUE,
            'min_prof_detect' => 2,
            'detection_dc'    => 15,
            'triggered'       => FALSE,
            'disarmed'        => FALSE,
          ],
        ],
      ],
    ];

    $results = $service->detectSnareAtHex(
      'actor-1', 'room-1', ['q' => 1, 'r' => 1],
      5, 3,
      FALSE,  // not actively searching
      $game_state
    );

    $this->assertCount(1, $results);
    $this->assertFalse($results[0]['detected']);
    $this->assertSame('requires_active_search', $results[0]['reason']);
  }

  /**
   * @covers ::detectSnareAtHex
   */
  public function testExpertSnareAllowsActiveSearchDetection(): void {
    // Die roll 15 + 5 = 20, DC 15 → success.
    $service = $this->buildService(15);
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id'        => 'snare-e',
            'placed_square'   => ['q' => 1, 'r' => 1],
            'requires_search' => TRUE,
            'min_prof_detect' => 2,
            'detection_dc'    => 15,
            'triggered'       => FALSE,
            'disarmed'        => FALSE,
          ],
        ],
      ],
    ];

    $results = $service->detectSnareAtHex(
      'actor-1', 'room-1', ['q' => 1, 'r' => 1],
      5, 3,
      TRUE,  // actively searching
      $game_state
    );

    $this->assertCount(1, $results);
    $this->assertTrue($results[0]['detected']);
  }

  /**
   * @covers ::detectSnareAtHex
   */
  public function testDetectionBlockedByInsufficientProficiency(): void {
    // Master-tier snare (min_prof_detect = 3); detector is Expert (rank 2).
    $service = $this->buildService(18);
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id'        => 'snare-m',
            'placed_square'   => ['q' => 2, 'r' => 3],
            'requires_search' => FALSE,
            'min_prof_detect' => 3,
            'detection_dc'    => 10,
            'triggered'       => FALSE,
            'disarmed'        => FALSE,
          ],
        ],
      ],
    ];

    $results = $service->detectSnareAtHex(
      'actor-1', 'room-1', ['q' => 2, 'r' => 3],
      10, 2,  // rank 2 = Expert, but min is 3 = Master
      TRUE,
      $game_state
    );

    $this->assertCount(1, $results);
    $this->assertFalse($results[0]['detected']);
    $this->assertSame('insufficient_proficiency', $results[0]['reason']);
  }

  // -------------------------------------------------------------------------
  // disableSnare — Thievery check
  // -------------------------------------------------------------------------

  /**
   * @covers ::disableSnare
   */
  public function testDisableSnareSucceedsWithHighRoll(): void {
    // Die 18 + 5 bonus = 23 vs DC 15 → success.
    $service = $this->buildService(18);
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id'        => 'snare-d',
            'triggered'       => FALSE,
            'disarmed'        => FALSE,
            'disable_dc'      => 15,
            'min_prof_detect' => 1,
          ],
        ],
      ],
    ];

    $result = $service->disableSnare('char-2', 'room-1', 0, 5, 2, $game_state);

    $this->assertTrue($result['success']);
    $this->assertTrue($game_state['snares']['room-1'][0]['disarmed']);
  }

  /**
   * @covers ::disableSnare
   */
  public function testDisableSnareFailsWithLowRoll(): void {
    // Die 1 + 0 bonus = 1 vs DC 20 → failure.
    $service = $this->buildService(1);
    $game_state = [
      'snares' => [
        'room-1' => [
          [
            'snare_id'        => 'snare-d',
            'triggered'       => FALSE,
            'disarmed'        => FALSE,
            'disable_dc'      => 20,
            'min_prof_detect' => 1,
          ],
        ],
      ],
    ];

    $result = $service->disableSnare('char-2', 'room-1', 0, 0, 2, $game_state);

    $this->assertFalse($result['success']);
    $this->assertFalse($game_state['snares']['room-1'][0]['disarmed']);
  }

  // -------------------------------------------------------------------------
  // Snare trigger effects — marking and striking snare types
  // -------------------------------------------------------------------------

  /**
   * @covers ::triggerSnare
   */
  public function testMarkingSnareAppliesMarkedCondition(): void {
    $service = $this->buildService();
    $game_state = [];

    $result = $service->triggerSnare(
      [
        'snare_id'     => 'snare-mark',
        'snare_type'   => 'marking',
        'placed_square' => ['q' => 0, 'r' => 0],
        'level'        => 1,
      ],
      'target-1',
      $game_state
    );

    $this->assertSame('marking', $result['snare_type']);
    $this->assertSame('target-1', $result['target_id']);
    $this->assertArrayHasKey('effect', $result);
  }

  /**
   * @covers ::triggerSnare
   */
  public function testStrikingSnareDealsPhysicalDamage(): void {
    $service = $this->buildService();
    $game_state = [];

    $result = $service->triggerSnare(
      [
        'snare_id'      => 'snare-strike',
        'snare_type'    => 'striking',
        'placed_square' => ['q' => 0, 'r' => 0],
        'level'         => 3,
      ],
      'target-1',
      $game_state
    );

    $this->assertSame('striking', $result['snare_type']);
    $this->assertArrayHasKey('effect', $result);
    $effect = $result['effect'];
    // The effect should carry a damage value.
    $this->assertArrayHasKey('damage', $effect);
  }

}

