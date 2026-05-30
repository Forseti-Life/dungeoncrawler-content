<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;
use Drupal\Tests\UnitTestCase;

/**
 * Tests state-diff summaries for sparse chat action payloads.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GameplayActionProcessor
 */
class GameplayActionProcessorStateDiffSummaryTest extends UnitTestCase {

  /**
   * Tests empty diffs do not require pre-populated keys.
   *
   * @covers ::buildStateDiffSummary
   */
  public function testBuildStateDiffSummaryHandlesSparseDiffs(): void {
    $processor = new class extends GameplayActionProcessor {
      public function __construct() {}
    };

    $summary = $processor->buildStateDiffSummary([], [], [], [], ['validation failed']);

    $this->assertSame(['validation failed'], $summary['validation_errors']);
    $this->assertSame([], $summary['character_changes']);
    $this->assertSame([], $summary['room_changes']);
    $this->assertFalse($summary['has_mechanical_effects']);
  }

  /**
   * @covers ::buildRealitySnapshot
   */
  public function testBuildRealitySnapshotPrefersCanonicalSpellSlotResources(): void {
    $processor = new class extends GameplayActionProcessor {
      public function __construct() {}
    };

    $snapshot = $processor->buildRealitySnapshot([
      'name' => 'Meris',
      'spells' => [
        'slots' => ['first' => 2],
        'slots_used' => ['first' => 0],
      ],
      'resources' => [
        'spellSlots' => [
          '1' => ['current' => 1, 'max' => 2],
        ],
      ],
    ]);

    $this->assertSame(['first' => 1], $snapshot['character']['spell_slots_remaining']);
  }

}
