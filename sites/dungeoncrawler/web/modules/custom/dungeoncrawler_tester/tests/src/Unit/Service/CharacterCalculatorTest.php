<?php

namespace Drupal\Tests\dungeoncrawler_tester\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\CharacterCalculator;
use Drupal\Tests\dungeoncrawler_tester\Unit\Traits\FixtureLoaderTrait;

/**
 * Tests for CharacterCalculator service.
 *
 * @group dungeoncrawler_content
 * @group character-creation
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterCalculator
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Unit Tests" - CharacterCalculator Service Tests
 */
class CharacterCalculatorTest extends UnitTestCase {

  use FixtureLoaderTrait;

  /**
   * The character calculator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CharacterCalculator
   */
  protected $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new CharacterCalculator();
  }

  /**
   * Tests HP calculation for a Fighter with 16 Constitution.
   *
   * @covers ::calculateHP
   *
   * @see docs/dungeoncrawler/testing/fixtures/characters/level_1_fighter.json
   *   Expected: 13 HP (10 base + 3 CON modifier)
   */
  public function testCalculateHPForFighterWithSixteenConstitution(): void {
    $fighter = $this->getTestCharacterData('fighter');

    $result = $this->calculator->calculateHP([
      'class_hp' => $fighter['class']['hp'],
      'level' => $fighter['level'],
      'abilities' => [
        'constitution' => $fighter['ability_scores']['constitution'],
      ],
      'ancestry_hp_bonus' => $fighter['ancestry']['hp_bonus'],
    ]);

    $this->assertSame($fighter['calculated_stats']['max_hp'], $result['total']);
  }

  /**
   * Tests ability modifier calculation.
   *
   * @covers ::calculateAbilityModifier
   * @dataProvider abilityModifierProvider
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ability_scores.modifiers section for official table
   */
  public function testCalculateAbilityModifier(int $score, int $expectedModifier): void {
    $modifier = $this->calculator->calculateAbilityModifier($score);
    $this->assertSame($expectedModifier, $modifier);
  }

  /**
   * Data provider for ability modifier tests.
   *
   * @return array
   *   Test data: [score, expected_modifier]
   */
  public static function abilityModifierProvider(): array {
    return [
      'Score 10' => [10, 0],
      'Score 18' => [18, 4],
      'Score 8' => [8, -1],
      // Additional PF2e score/modifier cases can be added as fixtures expand.
    ];
  }

  /**
   * Tests ability boost rules per PF2e.
   *
   * Boosts add +2 under 18, +1 at 18 or higher.
   *
   * @covers ::applyAbilityBoost
   * @group pf2e-rules
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ability_scores.boost_rules section
   */
  public function testApplyAbilityBoost(): void {
    $this->assertSame(12, $this->calculator->applyAbilityBoost(10));
    $this->assertSame(19, $this->calculator->applyAbilityBoost(18));
  }

  /**
   * Tests proficiency bonus calculation.
   *
   * @covers ::calculateProficiencyBonus
   * @dataProvider proficiencyProvider
   */
  public function testCalculateProficiencyBonus(string $rank, int $level, int $expected): void {
    $this->assertSame($expected, $this->calculator->calculateProficiencyBonus($rank, $level));
  }

  /**
   * Data provider for proficiency tests.
   *
   * @return array
   */
  public static function proficiencyProvider(): array {
    return [
      'Untrained level 1' => ['untrained', 1, 1],
      'Trained level 1' => ['trained', 1, 3],
      'Expert level 1' => ['expert', 1, 5],
      // Additional proficiency progression cases can be added as needed.
    ];
  }

  /**
   * Tests AC calculation.
   *
   * @covers ::calculateArmorClass
   */
  public function testCalculateArmorClass(): void {
    $fighter = $this->getTestCharacterData('fighter');

    $result = $this->calculator->calculateArmorClass([
      'abilities' => [
        'dexterity' => $fighter['ability_scores']['dexterity'],
      ],
      'armor_bonus' => $fighter['calculated_stats']['ac_breakdown']['armor'],
      'shield_bonus' => $fighter['calculated_stats']['ac_breakdown']['shield'],
      'proficiency_rank' => 'trained',
      'level' => $fighter['level'],
    ]);

    $this->assertSame($fighter['calculated_stats']['armor_class'], $result['total']);
  }

}
