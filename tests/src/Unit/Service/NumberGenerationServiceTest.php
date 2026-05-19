<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;

/**
 * Tests for NumberGenerationService.
 *
 * @group dungeoncrawler_content
 * @group dice
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\NumberGenerationService
 */
class NumberGenerationServiceTest extends UnitTestCase {

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->service = new class extends NumberGenerationService {
      public array $loggedRolls = [];

      public function logRoll(string $expression, int $total, ?int $characterId = NULL, string $rollType = 'general'): void {
        $this->loggedRolls[] = [
          'expression' => $expression,
          'total' => $total,
          'character_id' => $characterId,
          'roll_type' => $rollType,
        ];
      }
    };
  }

  /**
   * Tests Pathfinder die ranges.
   *
   * @covers ::rollPathfinderDie
   */
  public function testRollPathfinderDieRange(): void {
    foreach (NumberGenerationService::PATHFINDER_DICE as $sides) {
      $roll = $this->service->rollPathfinderDie($sides);
      $this->assertGreaterThanOrEqual(1, $roll);
      $this->assertLessThanOrEqual($sides, $roll);
    }
  }

  /**
   * Tests percentile range.
   *
   * @covers ::rollPercentile
   */
  public function testRollPercentileRange(): void {
    $roll = $this->service->rollPercentile();
    $this->assertGreaterThanOrEqual(1, $roll);
    $this->assertLessThanOrEqual(100, $roll);
  }

  /**
   * Tests generic inclusive range rolling.
   *
   * @covers ::rollRange
   */
  public function testRollRange(): void {
    $roll = $this->service->rollRange(-1, 1);
    $this->assertGreaterThanOrEqual(-1, $roll);
    $this->assertLessThanOrEqual(1, $roll);
  }

  /**
   * Tests rolling multiple dice in generic range.
   *
   * @covers ::rollMultiple
   */
  public function testRollMultiple(): void {
    $rolls = $this->service->rollMultiple(20, 5);
    $this->assertCount(5, $rolls);
    foreach ($rolls as $roll) {
      $this->assertGreaterThanOrEqual(1, $roll);
      $this->assertLessThanOrEqual(20, $roll);
    }
  }

  /**
   * Tests notation parsing and totals.
   *
   * @covers ::rollNotation
   */
  public function testRollNotation(): void {
    $result = $this->service->rollNotation('2d6+3');

    $this->assertSame('2d6+3', $result['notation']);
    $this->assertSame(2, $result['count']);
    $this->assertSame(6, $result['sides']);
    $this->assertSame(3, $result['modifier']);
    $this->assertCount(2, $result['rolls']);
    $this->assertSame($result['subtotal'] + 3, $result['total']);
    $this->assertSame('2d6+3', $this->service->loggedRolls[0]['expression']);
    $this->assertSame($result['total'], $this->service->loggedRolls[0]['total']);
    $this->assertSame('general', $this->service->loggedRolls[0]['roll_type']);
  }

  /**
   * Tests Pathfinder die rolls write an audit entry.
   *
   * @covers ::rollPathfinderDie
   */
  public function testRollPathfinderDieLogsAuditEntry(): void {
    $roll = $this->service->rollPathfinderDie(20, 77, 'skill');

    $this->assertSame('1d20', $this->service->loggedRolls[0]['expression']);
    $this->assertSame($roll, $this->service->loggedRolls[0]['total']);
    $this->assertSame(77, $this->service->loggedRolls[0]['character_id']);
    $this->assertSame('skill', $this->service->loggedRolls[0]['roll_type']);
  }

  /**
   * Tests unsupported Pathfinder die rejection.
   *
   * @covers ::rollPathfinderDie
   */
  public function testRollPathfinderDieRejectsUnsupportedSides(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->service->rollPathfinderDie(30);
  }

  /**
   * Tests invalid dice notation rejection.
   *
   * @covers ::rollNotation
   */
  public function testRollNotationRejectsInvalidFormat(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->service->rollNotation('not-a-roll');
  }

  /**
   * Tests invalid range arguments.
   *
   * @covers ::rollRange
   */
  public function testRollRangeRejectsInvalidBounds(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->service->rollRange(10, 1);
  }

}
