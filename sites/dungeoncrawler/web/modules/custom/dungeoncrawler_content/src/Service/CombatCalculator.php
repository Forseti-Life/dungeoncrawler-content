<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Service for combat-related calculations.
 *
 * Implements PF2e combat rules including attack bonuses, multiple attack
 * penalty, degrees of success, and other combat mechanics.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "PF2e Rules Validation Tests" - Combat Calculations
 *
 * Design Reference:
 * - Multiple Attack Penalty rules (PF2e Core Rulebook p. 446)
 * - Degrees of Success (PF2e Core Rulebook p. 445)
 * - Attack rolls and modifiers
 */
class CombatCalculator {

  /**
   * Calculate multiple attack penalty.
   *
   * First attack: no penalty
   * Second attack: -5 (normal) or -4 (agile)
   * Third+ attack: -10 (normal) or -8 (agile)
   *
   * Per PF2e Core Rulebook p. 446
   *
   * @param int $attackNumber
   *   Which attack in the turn (1, 2, 3+).
   * @param bool $isAgile
   *   Whether the weapon has the agile trait.
   *
   * @return int
   *   Penalty to apply to the attack roll.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   multiple_attack_penalty section
   */
  public function calculateMultipleAttackPenalty(int $attackNumber, bool $isAgile = FALSE): int {
    if ($attackNumber <= 1) {
      return 0;
    }

    if ($isAgile) {
      return $attackNumber === 2 ? -4 : -8;
    }

    return $attackNumber === 2 ? -5 : -10;
  }

  /**
   * Determine degree of success.
   *
   * Critical Success: Beat DC by 10+, or natural 20
   * Success: Meet or beat DC
   * Failure: Below DC
   * Critical Failure: Miss DC by 10+, or natural 1
   *
   * Per PF2e Core Rulebook p. 445
   *
   * @param int $result
   *   Total roll result.
   * @param int $dc
   *   Difficulty class.
   * @param int|null $naturalRoll
   *   Natural die roll (1-20), or NULL if not applicable.
   *
   * @return string
   *   'critical_success', 'success', 'failure', or 'critical_failure'.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   degrees_of_success section
   */
  public function calculateDegreeOfSuccess(int $result, int $dc, ?int $naturalRoll = NULL): string {
    $difference = $result - $dc;

    if ($difference >= 10) {
      $degree = 'critical_success';
    }
    elseif ($difference >= 0) {
      $degree = 'success';
    }
    elseif ($difference <= -10) {
      $degree = 'critical_failure';
    }
    else {
      $degree = 'failure';
    }

    if ($naturalRoll !== NULL) {
      if ($naturalRoll === 20) {
        $degree = $this->bumpDegree($degree, 1);
      }
      elseif ($naturalRoll === 1) {
        $degree = $this->bumpDegree($degree, -1);
      }
    }

    return $degree;
  }

  /**
   * Calculate attack bonus.
   *
   * Formula: ability_mod + proficiency + level + item + other
   *
   * @param array $attackData
   *   Attack data including ability modifier, proficiency, level, bonuses.
   *
   * @return int
   *   Total attack bonus.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   attack_bonus_calculation section
   */
  public function calculateAttackBonus(array $attackData): int {
    $ability = (int) ($attackData['ability_modifier'] ?? 0);
    $proficiency = (int) ($attackData['proficiency_bonus'] ?? 0);
    $level = (int) ($attackData['level'] ?? 0);
    $item = (int) ($attackData['item_bonus'] ?? 0);
    // other_bonuses may be a plain int (legacy) or a structured bonus array.
    $other_raw = $attackData['other_bonuses'] ?? 0;
    $other = is_array($other_raw) ? BonusResolver::resolve($other_raw) : (int) $other_raw;

    return $ability + $proficiency + $level + $item + $other;
  }

  /**
   * Calculate spell save DC.
   *
   * Formula: 10 + ability_mod + proficiency + level + item
   *
   * @param array $casterData
   *   Caster data including spellcasting ability, proficiency, level.
   *
   * @return int
   *   Spell save DC.
   */
  public function calculateSpellSaveDC(array $casterData): int {
    $ability = (int) ($casterData['ability_modifier'] ?? 0);
    $proficiency = (int) ($casterData['proficiency_bonus'] ?? 0);
    $level = (int) ($casterData['level'] ?? 0);
    $item = (int) ($casterData['item_bonus'] ?? 0);
    // other_bonuses may be a plain int (legacy) or a structured bonus array.
    $other_raw = $casterData['other_bonuses'] ?? 0;
    $other = is_array($other_raw) ? BonusResolver::resolve($other_raw) : (int) $other_raw;

    return 10 + $ability + $proficiency + $level + $item + $other;
  }

  /**
   * Shift success degree up or down one step.
   */
  protected function bumpDegree(string $degree, int $steps): string {
    $order = [
      'critical_failure',
      'failure',
      'success',
      'critical_success',
    ];

    $currentIndex = array_search($degree, $order, TRUE);
    if ($currentIndex === FALSE) {
      return $degree;
    }

    $newIndex = max(0, min(count($order) - 1, $currentIndex + $steps));
    return $order[$newIndex];
  }

  /**
   * Simple DC by level (PF2E Core Rulebook Table 10-5).
   * Levels 1–20.
   */
  const SIMPLE_DC = [
    1 => 15, 2 => 16, 3 => 18,  4 => 19,  5 => 20,
    6 => 22, 7 => 23, 8 => 24,  9 => 26, 10 => 27,
    11 => 28, 12 => 30, 13 => 31, 14 => 32, 15 => 34,
    16 => 35, 17 => 36, 18 => 38, 19 => 39, 20 => 40,
  ];

  /**
   * Task DC by difficulty tier (PF2E Core Rulebook Chapter 10).
   */
  const TASK_DC = [
    'trivial'    => 10,
    'low'        => 15,
    'moderate'   => 20,
    'high'       => 25,
    'extreme'    => 30,
    'incredible' => 40,
  ];

  /**
   * Determine degree of success using PF2E four-degree model.
   *
   * Note: method name preserves the AC typo "Degre" for interface consistency.
   *
   * @param int $rollTotal     Total roll result (d20 + modifiers).
   * @param int $dc            Difficulty class.
   * @param bool $naturalTwenty Whether the raw d20 was a 20 (bumps degree up).
   * @param bool $naturalOne   Whether the raw d20 was a 1 (bumps degree down).
   *
   * @return string 'critical_success' | 'success' | 'failure' | 'critical_failure'
   */
  public function determineDegreOfSuccess(int $rollTotal, int $dc, bool $naturalTwenty = FALSE, bool $naturalOne = FALSE): string {
    $naturalRoll = $naturalTwenty ? 20 : ($naturalOne ? 1 : NULL);
    return $this->calculateDegreeOfSuccess($rollTotal, $dc, $naturalRoll);
  }

  /**
   * Get the Simple DC for a given character/creature level (PF2E Table 10-5).
   *
   * @param int $level  Level 1–20. Negative/zero → error array. >20 → capped at 20.
   *
   * @return int|array  DC integer, or ['error' => '...'] on invalid input.
   */
  public function getSimpleDC(int $level) {
    if ($level <= 0) {
      return ['error' => "Level must be a positive integer (1–20), got: {$level}."];
    }
    $capped = min($level, 20);
    return self::SIMPLE_DC[$capped];
  }

  /**
   * Get the DC for a named difficulty tier.
   *
   * Valid tiers: trivial (10), low (15), moderate (20), high (25), extreme (30), incredible (40).
   *
   * @param string $difficulty  Difficulty tier name (case-insensitive).
   *
   * @return int|array  DC integer, or ['error' => '...'] on unknown tier.
   */
  public function getTaskDC(string $difficulty) {
    $key = strtolower(trim($difficulty));
    if (!isset(self::TASK_DC[$key])) {
      $valid = implode(', ', array_keys(self::TASK_DC));
      return ['error' => "Unknown difficulty tier: '{$difficulty}'. Valid tiers: {$valid}."];
    }
    return self::TASK_DC[$key];
  }

}
