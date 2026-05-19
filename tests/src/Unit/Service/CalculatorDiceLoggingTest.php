<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\Calculator;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies calculator check paths use the correct dice log roll types.
 *
 * @group dungeoncrawler_content
 * @group dice
 */
class CalculatorDiceLoggingTest extends UnitTestCase {

  /**
   * Verifies saving throws use the save roll type.
   */
  public function testRollSavingThrowUsesSaveRollType(): void {
    $number_generation = new class extends NumberGenerationService {
      public array $calls = [];

      public function rollPathfinderDie(int $sides, ?int $characterId = NULL, string $rollType = 'general'): int {
        $this->calls[] = ['sides' => $sides, 'character_id' => $characterId, 'roll_type' => $rollType];
        return 12;
      }
    };

    $calculator = new Calculator($number_generation, new CombatCalculator());
    $result = $calculator->rollSavingThrow(4, 2, 1, []);

    $this->assertSame(12, $result['roll']);
    $this->assertSame('save', $number_generation->calls[0]['roll_type']);
  }

  /**
   * Verifies skill checks use the skill roll type.
   */
  public function testRollSkillCheckUsesSkillRollType(): void {
    $number_generation = new class extends NumberGenerationService {
      public array $calls = [];

      public function rollPathfinderDie(int $sides, ?int $characterId = NULL, string $rollType = 'general'): int {
        $this->calls[] = ['sides' => $sides, 'character_id' => $characterId, 'roll_type' => $rollType];
        return 15;
      }
    };

    $calculator = new Calculator($number_generation, new CombatCalculator());
    $result = $calculator->rollSkillCheck(4, 2, [], []);

    $this->assertSame(15, $result['roll']);
    $this->assertSame('skill', $number_generation->calls[0]['roll_type']);
  }

}
