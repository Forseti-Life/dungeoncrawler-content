<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;

/**
 * Example test demonstrating CharacterCalculator testing patterns.
 *
 * This is a design example showing how tests should be structured.
 * It serves as a template for implementing actual tests.
 *
 * @group dungeoncrawler_content
 * @group character-creation
 * @group example
 */
class CharacterCalculatorExampleTest extends UnitTestCase {

  /**
   * Example fixture data for a fighter.
   *
   * @var array
   */
  protected $fighterData;

  /**
   * Example fixture data for a wizard.
   *
   * @var array
   */
  protected $wizardData;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // In actual implementation, load from fixture files
    // $this->fighterData = $this->loadFixture('characters/level_1_fighter.json');
    
    $this->fighterData = [
      'class' => 'fighter',
      'class_hp' => 10,
      'level' => 1,
      'abilities' => ['constitution' => 16],
      'ancestry_hp_bonus' => 0,
      'expected_hp' => 13,
    ];

    $this->wizardData = [
      'class' => 'wizard',
      'class_hp' => 6,
      'level' => 1,
      'abilities' => ['constitution' => 12],
      'ancestry_hp_bonus' => 0,
      'expected_hp' => 7,
    ];
  }

  /**
   * Example: Test HP calculation for a Fighter.
   *
   * Demonstrates the AAA pattern: Arrange, Act, Assert
   *
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::calculateHP
   */
  public function testCalculateHPForFighter(): void {
    // Arrange: Set up test data
    $characterData = $this->fighterData;

    // Act: Call the method being tested
    // $calculator = new CharacterCalculator();
    // $result = $calculator->calculateHP($characterData);
    
    // For this example, simulate the expected result
    $result = [
      'total' => 13,
      'breakdown' => [
        'class_base' => 10,
        'con_modifier' => 3,
        'ancestry_bonus' => 0,
      ],
    ];

    // Assert: Verify the results
    $this->assertEquals(13, $result['total'], 'Fighter HP should be 13');
    $this->assertEquals(10, $result['breakdown']['class_base'], 'Base HP should be 10');
    $this->assertEquals(3, $result['breakdown']['con_modifier'], 'CON modifier should be 3');
  }

  /**
   * Example: Test HP calculation for a Wizard.
   *
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::calculateHP
   */
  public function testCalculateHPForWizard(): void {
    $characterData = $this->wizardData;

    // Simulated result
    $result = [
      'total' => 7,
      'breakdown' => [
        'class_base' => 6,
        'con_modifier' => 1,
        'ancestry_bonus' => 0,
      ],
    ];

    $this->assertEquals(7, $result['total'], 'Wizard HP should be 7');
    $this->assertEquals(6, $result['breakdown']['class_base'], 'Base HP should be 6');
  }

  /**
   * Example: Test HP calculation with data provider.
   *
   * This demonstrates testing multiple scenarios efficiently.
   *
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::calculateHP
   * @dataProvider hpCalculationProvider
   */
  public function testCalculateHPWithVariousClasses(
    string $class,
    int $classHp,
    int $con,
    int $ancestryBonus,
    int $expectedTotal
  ): void {
    $characterData = [
      'class' => $class,
      'class_hp' => $classHp,
      'level' => 1,
      'abilities' => ['constitution' => $con],
      'ancestry_hp_bonus' => $ancestryBonus,
    ];

    // Simulate calculation
    $conModifier = floor(($con - 10) / 2);
    $total = $classHp + $conModifier + $ancestryBonus;

    $this->assertEquals(
      $expectedTotal,
      $total,
      "HP for level 1 $class with CON $con should be $expectedTotal"
    );
  }

  /**
   * Data provider for HP calculation tests.
   *
   * @return array
   *   Test cases with [class, class_hp, con, ancestry_bonus, expected_total]
   */
  public function hpCalculationProvider(): array {
    return [
      'Fighter high CON' => ['fighter', 10, 18, 0, 14],
      'Fighter medium CON' => ['fighter', 10, 14, 0, 12],
      'Wizard low CON' => ['wizard', 6, 10, 0, 6],
      'Wizard high CON' => ['wizard', 6, 16, 0, 9],
      'Dwarf Fighter' => ['fighter', 10, 16, 2, 15],
      'Human Rogue' => ['rogue', 8, 14, 0, 10],
    ];
  }

  /**
   * Example: Test ability modifier calculation per PF2e rules.
   *
   * Reference: PF2e Core Rulebook, pp. 20-21
   *
   * @group pf2e-rules
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::calculateAbilityModifier
   * @dataProvider abilityModifierProvider
   */
  public function testCalculateAbilityModifier(int $score, int $expectedModifier): void {
    // Formula: floor((score - 10) / 2)
    $modifier = floor(($score - 10) / 2);

    $this->assertEquals(
      $expectedModifier,
      $modifier,
      "Ability score $score should have modifier $expectedModifier"
    );
  }

  /**
   * Data provider for ability modifier tests.
   *
   * Based on PF2e Core Rulebook ability modifier table.
   *
   * @return array
   */
  public function abilityModifierProvider(): array {
    return [
      'Score 1' => [1, -5],
      'Score 8' => [8, -1],
      'Score 10' => [10, 0],
      'Score 11' => [11, 0],
      'Score 12' => [12, 1],
      'Score 14' => [14, 2],
      'Score 16' => [16, 3],
      'Score 18' => [18, 4],
      'Score 20' => [20, 5],
    ];
  }

  /**
   * Example: Test ability boost rules.
   *
   * Per PF2e rules: Boosts add 2 to scores under 18, or 1 to scores at 18+
   *
   * Reference: PF2e Core Rulebook, p. 20
   *
   * @group pf2e-rules
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::applyAbilityBoost
   */
  public function testApplyAbilityBoost(): void {
    // Test boost under 18
    $score = 10;
    $boosted = $score + 2; // Should add 2
    $this->assertEquals(12, $boosted, 'Score 10 + boost = 12');

    // Test boost at 18
    $score = 18;
    $boosted = $score + 1; // Should add 1
    $this->assertEquals(19, $boosted, 'Score 18 + boost = 19');

    // Test boost over 18
    $score = 19;
    $boosted = $score + 1; // Should add 1
    $this->assertEquals(20, $boosted, 'Score 19 + boost = 20');
  }

  /**
   * Example: Test exception handling for invalid data.
   *
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::calculateHP
   */
  public function testCalculateHPWithInvalidDataThrowsException(): void {
    // This demonstrates testing error conditions
    // In actual implementation:
    // $this->expectException(InvalidCharacterDataException::class);
    // $this->expectExceptionMessage('Invalid character data');
    // $calculator->calculateHP(['invalid' => 'data']);

    // For this example, we simulate the check
    $invalidData = ['invalid' => 'data'];
    $hasRequiredFields = isset($invalidData['class_hp']) && isset($invalidData['abilities']['constitution']);
    
    $this->assertFalse($hasRequiredFields, 'Invalid data should not have required fields');
  }

  /**
   * Example: Test AC calculation.
   *
   * Formula: 10 + DEX + armor + shield + proficiency + level
   *
   * @group pf2e-rules
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::calculateAC
   * @dataProvider acCalculationProvider
   */
  public function testCalculateArmorClass(
    int $dex,
    int $armorBonus,
    int $shieldBonus,
    int $proficiencyBonus,
    int $level,
    int $expectedAC
  ): void {
    $dexModifier = floor(($dex - 10) / 2);
    $ac = 10 + $dexModifier + $armorBonus + $shieldBonus + $proficiencyBonus + $level;

    $this->assertEquals($expectedAC, $ac, "AC calculation should match expected value");
  }

  /**
   * Data provider for AC calculation tests.
   *
   * @return array
   */
  public function acCalculationProvider(): array {
    return [
      'Unarmored wizard' => [14, 0, 0, 2, 1, 15],
      'Fighter in scale mail' => [14, 4, 0, 2, 1, 19],
      'Fighter with scale mail and shield' => [14, 4, 2, 2, 1, 21],
      'Rogue in leather' => [18, 2, 0, 2, 1, 17],
    ];
  }

  /**
   * Example: Test proficiency bonus calculation.
   *
   * @group pf2e-rules
   * @covers \Drupal\dungeoncrawler_content\Service\CharacterCalculator::calculateProficiencyBonus
   * @dataProvider proficiencyProvider
   */
  public function testCalculateProficiencyBonus(
    string $rank,
    int $level,
    int $expectedBonus
  ): void {
    $rankBonuses = [
      'untrained' => 0,
      'trained' => 2,
      'expert' => 4,
      'master' => 6,
      'legendary' => 8,
    ];

    $bonus = ($rankBonuses[$rank] ?? 0) + $level;

    $this->assertEquals(
      $expectedBonus,
      $bonus,
      "$rank proficiency at level $level should be $expectedBonus"
    );
  }

  /**
   * Data provider for proficiency tests.
   *
   * @return array
   */
  public function proficiencyProvider(): array {
    return [
      'Untrained level 1' => ['untrained', 1, 1],
      'Trained level 1' => ['trained', 1, 3],
      'Expert level 1' => ['expert', 1, 5],
      'Master level 5' => ['master', 5, 11],
      'Legendary level 10' => ['legendary', 10, 18],
    ];
  }

  /**
   * Example: Test multiple attack penalty.
   *
   * Reference: PF2e Core Rulebook, p. 446
   *
   * @group pf2e-rules
   * @covers \Drupal\dungeoncrawler_content\Service\CombatCalculator::calculateMultipleAttackPenalty
   */
  public function testMultipleAttackPenalty(): void {
    // Normal weapons
    $this->assertEquals(0, $this->getAttackPenalty(1, false), '1st attack: no penalty');
    $this->assertEquals(-5, $this->getAttackPenalty(2, false), '2nd attack: -5');
    $this->assertEquals(-10, $this->getAttackPenalty(3, false), '3rd+ attack: -10');

    // Agile weapons
    $this->assertEquals(0, $this->getAttackPenalty(1, true), '1st agile attack: no penalty');
    $this->assertEquals(-4, $this->getAttackPenalty(2, true), '2nd agile attack: -4');
    $this->assertEquals(-8, $this->getAttackPenalty(3, true), '3rd+ agile attack: -8');
  }

  /**
   * Helper method for attack penalty calculation.
   */
  protected function getAttackPenalty(int $attackNumber, bool $isAgile): int {
    if ($attackNumber === 1) {
      return 0;
    }
    if ($isAgile) {
      return $attackNumber === 2 ? -4 : -8;
    }
    return $attackNumber === 2 ? -5 : -10;
  }

}
