<?php

namespace Drupal\Tests\dungeoncrawler_tester\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;

/**
 * Tests for CombatCalculator service.
 *
 * @group dungeoncrawler_content
 * @group combat
 * @group pf2e-rules
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CombatCalculator
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "PF2e Rules Validation Tests" - Combat Calculations
 */
class CombatCalculatorTest extends UnitTestCase {

  /**
   * The combat calculator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CombatCalculator
   */
  protected $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new CombatCalculator();
  }

  /**
   * Tests multiple attack penalty calculation.
   *
   * Per PF2e Core Rulebook p. 446:
   * - 1st attack: no penalty
   * - 2nd attack: -5 (normal) or -4 (agile)
   * - 3rd+ attack: -10 (normal) or -8 (agile)
   *
   * @covers ::calculateMultipleAttackPenalty
   * @group pf2e-rules
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   multiple_attack_penalty section
   */
  public function testMultipleAttackPenalty(): void {
    // Normal weapons
    $this->assertSame(0, $this->calculator->calculateMultipleAttackPenalty(1, FALSE));
    $this->assertSame(-5, $this->calculator->calculateMultipleAttackPenalty(2, FALSE));
    $this->assertSame(-10, $this->calculator->calculateMultipleAttackPenalty(3, FALSE));

    // Agile weapons
    $this->assertSame(0, $this->calculator->calculateMultipleAttackPenalty(1, TRUE));
    $this->assertSame(-4, $this->calculator->calculateMultipleAttackPenalty(2, TRUE));
    $this->assertSame(-8, $this->calculator->calculateMultipleAttackPenalty(3, TRUE));
  }

  /**
   * Tests degree of success calculation.
   *
   * Per PF2e Core Rulebook p. 445:
   * - Critical Success: Beat DC by 10+, or natural 20
   * - Success: Meet or beat DC
   * - Failure: Below DC
   * - Critical Failure: Miss DC by 10+, or natural 1
   *
   * @covers ::calculateDegreeOfSuccess
   * @group pf2e-rules
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   degrees_of_success section
   */
  public function testCalculateDegreeOfSuccess(): void {
    $this->assertSame('critical_success', $this->calculator->calculateDegreeOfSuccess(25, 15, 20));
    $this->assertSame('success', $this->calculator->calculateDegreeOfSuccess(15, 15, NULL));
    $this->assertSame('failure', $this->calculator->calculateDegreeOfSuccess(14, 15, NULL));
    $this->assertSame('critical_failure', $this->calculator->calculateDegreeOfSuccess(5, 15, 1));
  }

  /**
   * Tests attack bonus calculation.
   *
   * @covers ::calculateAttackBonus
   */
  public function testCalculateAttackBonus(): void {
    $result = $this->calculator->calculateAttackBonus([
      'ability_modifier' => 4,
      'proficiency_bonus' => 2,
      'level' => 1,
      'item_bonus' => 1,
      'other_bonuses' => 0,
    ]);

    $this->assertSame(8, $result);
  }

  /**
   * Tests spell save DC calculation.
   *
   * @covers ::calculateSpellSaveDC
   */
  public function testCalculateSpellSaveDC(): void {
    $result = $this->calculator->calculateSpellSaveDC([
      'ability_modifier' => 4,
      'proficiency_bonus' => 2,
      'level' => 1,
      'item_bonus' => 1,
      'other_bonuses' => 0,
    ]);

    $this->assertSame(18, $result);
  }

}
