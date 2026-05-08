<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Calculator service - All combat-related calculations and formulas.
 *
 * PF2e combat math engine providing initiative, attack resolution, damage
 * calculation, resistance/weakness application, and AC computation.
 *
 * Delegates to CombatCalculator where overlapping implementations exist
 * (MAP, degree-of-success) and adds damage/resistance/AC math that
 * CombatCalculator does not cover.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (Calculator)
 */
class Calculator {

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService|null
   */
  protected $numberGeneration;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatCalculator|null
   */
  protected $combatCalculator;

  /**
   * Constructor.
   *
   * @param \Drupal\dungeoncrawler_content\Service\NumberGenerationService|null $number_generation
   *   Dice rolling service.
   * @param \Drupal\dungeoncrawler_content\Service\CombatCalculator|null $combat_calculator
   *   Underlying combat calculator for MAP/degree-of-success.
   */
  public function __construct(?NumberGenerationService $number_generation = NULL, ?CombatCalculator $combat_calculator = NULL) {
    $this->numberGeneration = $number_generation ?? new NumberGenerationService();
    $this->combatCalculator = $combat_calculator ?? new CombatCalculator();
  }

  /**
   * Calculate initiative.
   *
   * PF2e: Initiative = d20 + perception_modifier + sum(bonuses).
   * Exploration activities can substitute another skill (Stealth for Avoid
   * Notice, etc.) — the caller passes the appropriate modifier.
   *
   * @param int $perception_modifier
   *   Perception modifier (or substitute skill modifier).
   * @param array $bonuses
   *   Additional flat bonuses (circumstance, status, item).
   *
   * @return array
   *   ['roll' => int, 'modifier' => int, 'bonuses' => int, 'total' => int]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculateinitiative
   */
  public function calculateInitiative($perception_modifier, array $bonuses = []) {
    $roll = $this->numberGeneration->rollPathfinderDie(20);
    $modifier = (int) $perception_modifier;
    $bonus_sum = BonusResolver::resolve($bonuses);
    $total = $roll + $modifier + $bonus_sum;

    return [
      'roll' => $roll,
      'modifier' => $modifier,
      'bonuses' => $bonus_sum,
      'total' => $total,
    ];
  }

  /**
   * Sort initiative order.
   *
   * PF2e: Highest initiative goes first. Ties broken by:
   * 1. Higher tiebreaker value (perception modifier)
   * 2. Lower participant ID (arbitrary but deterministic)
   *
   * @param array $participants
   *   Array of participant arrays. Each must have 'initiative_total'.
   *   Optional: 'tiebreaker' (int), 'id' (int).
   *
   * @return array
   *   Participants sorted in initiative order (first = acts first).
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#sortinitiativeorder
   */
  public function sortInitiativeOrder(array $participants) {
    usort($participants, function ($a, $b) {
      $init_diff = (int) ($b['initiative_total'] ?? $b['initiative'] ?? 0)
                 - (int) ($a['initiative_total'] ?? $a['initiative'] ?? 0);
      if ($init_diff !== 0) {
        return $init_diff;
      }

      $tie_diff = (int) ($b['tiebreaker'] ?? 0) - (int) ($a['tiebreaker'] ?? 0);
      if ($tie_diff !== 0) {
        return $tie_diff;
      }

      // Lower ID goes first for deterministic ordering.
      return (int) ($a['id'] ?? 0) - (int) ($b['id'] ?? 0);
    });

    return $participants;
  }

  /**
   * Calculate attack bonus.
   *
   * PF2e attack bonus = proficiency + ability_mod + item_bonus + bonuses
   *                     - MAP - penalties.
   *
   * Bonuses and penalties are flat integer arrays.
   *
   * @param int $proficiency
   *   Proficiency bonus (trained + level, expert + level, etc.).
   * @param int $ability_mod
   *   Ability modifier (Str for melee, Dex for ranged/finesse).
   * @param int $item_bonus
   *   Item bonus (weapon potency rune: +1/+2/+3).
   * @param int $map
   *   Multiple Attack Penalty (0, -5/-4, -10/-8). Pass as positive; subtracted internally.
   * @param array $bonuses
   *   Additional flat bonuses (circumstance, status, etc.).
   * @param array $penalties
   *   Additional flat penalties (conditions, range increments, etc.).
   *
   * @return int
   *   Total attack bonus.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculateattackbonus
   */
  public function calculateAttackBonus($proficiency, $ability_mod, $item_bonus, $map, array $bonuses = [], array $penalties = []) {
    $total = (int) $proficiency
           + (int) $ability_mod
           + (int) $item_bonus
           - abs((int) $map)
           + BonusResolver::resolve($bonuses)
           + BonusResolver::resolvePenalties($penalties);

    return $total;
  }

  /**
   * Calculate Multiple Attack Penalty.
   *
   * PF2e MAP (Core Rulebook p. 446):
   *   1st attack: 0
   *   2nd attack: -5 (normal) / -4 (agile)
   *   3rd+ attack: -10 (normal) / -8 (agile)
   *
   * @param int $attacks_this_turn
   *   Which attack number (1-based). 1 = first attack, 2 = second, etc.
   * @param bool $is_agile_weapon
   *   Whether the weapon has the agile trait.
   *
   * @return int
   *   Penalty to apply (0, -4/-5, or -8/-10).
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculatemap
   */
  public function calculateMAP($attacks_this_turn, $is_agile_weapon = FALSE) {
    return $this->combatCalculator->calculateMultipleAttackPenalty(
      (int) $attacks_this_turn,
      (bool) $is_agile_weapon
    );
  }

  /**
   * Determine degree of success.
   *
   * PF2e four-degree model (Core Rulebook p. 445):
   *   Critical Success: total >= DC + 10, or nat 20 bumps success → crit success
   *   Success: total >= DC
   *   Failure: total < DC
   *   Critical Failure: total <= DC - 10, or nat 1 bumps failure → crit failure
   *
   * Natural 20 improves degree by one step.
   * Natural 1 worsens degree by one step.
   *
   * @param int $roll
   *   Total result (d20 + modifiers).
   * @param int $dc
   *   Difficulty class.
   * @param bool $is_natural_1
   *   True if the raw d20 was a 1.
   * @param bool $is_natural_20
   *   True if the raw d20 was a 20.
   *
   * @return string
   *   'critical_success', 'success', 'failure', or 'critical_failure'.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#determinedegreeofuccess
   */
  public function determineDegreeOfSuccess($roll, $dc, $is_natural_1 = FALSE, $is_natural_20 = FALSE) {
    $natural_roll = NULL;
    if ($is_natural_20) {
      $natural_roll = 20;
    }
    elseif ($is_natural_1) {
      $natural_roll = 1;
    }

    return $this->combatCalculator->calculateDegreeOfSuccess(
      (int) $roll,
      (int) $dc,
      $natural_roll
    );
  }

  /**
   * Roll damage.
   *
   * PF2e damage = dice rolls + ability_modifier + bonuses.
   * Uses standard dice notation (e.g. '1d8', '2d6+3').
   *
   * @param string $damage_dice
   *   Dice notation (e.g. '1d8', '2d6', '1d4+1').
   * @param int $ability_modifier
   *   Ability modifier added to damage (Str for melee typically).
   * @param array $bonuses
   *   Additional flat damage bonuses.
   *
   * @return array
   *   ['rolls' => int[], 'dice_total' => int, 'modifier' => int,
   *    'bonuses' => int, 'total' => int]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#rolldamage
   */
  public function rollDamage($damage_dice, $ability_modifier = 0, array $bonuses = []) {
    $result = $this->numberGeneration->rollNotation((string) $damage_dice);
    $dice_total = (int) ($result['subtotal'] ?? array_sum($result['rolls'] ?? [0]));
    $notation_modifier = (int) ($result['modifier'] ?? 0);
    $ability_mod = (int) $ability_modifier;
    $bonus_sum = array_sum(array_map('intval', $bonuses));

    $total = max(0, $dice_total + $notation_modifier + $ability_mod + $bonus_sum);

    return [
      'rolls' => $result['rolls'] ?? [],
      'dice_total' => $dice_total,
      'modifier' => $ability_mod + $notation_modifier,
      'bonuses' => $bonus_sum,
      'total' => $total,
    ];
  }

  /**
   * Apply critical damage.
   *
   * PF2e critical hit: Double ALL damage (dice + modifiers).
   * Striking runes add extra weapon damage dice on crits, but that's
   * handled before calling this method. This just doubles the final total.
   *
   * @param array $base_damage_rolls
   *   Individual die roll results, e.g. [4, 6, 3].
   * @param int $static_modifiers
   *   Sum of all static modifiers (ability mod + bonuses).
   *
   * @return array
   *   ['base_dice' => int, 'base_static' => int, 'doubled_total' => int]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applycriticaldamage
   */
  public function applyCriticalDamage(array $base_damage_rolls, $static_modifiers = 0) {
    $dice_sum = array_sum(array_map('intval', $base_damage_rolls));
    $static = (int) $static_modifiers;

    // PF2e: Double all damage on a critical hit.
    $doubled_total = max(0, ($dice_sum * 2) + $static);

    return [
      'base_dice' => $dice_sum,
      'base_static' => $static,
      'doubled_total' => $doubled_total,
    ];
  }

  /**
   * Apply resistances and weaknesses to damage.
   *
   * PF2e order (Core Rulebook p. 453):
   * 1. Apply resistance (subtract from damage, minimum 0).
   * 2. Apply weakness (add to damage).
   *
   * Resistances and weaknesses are keyed by damage type:
   *   ['fire' => 5, 'physical' => 2]
   *
   * @param int $damage
   *   Incoming damage amount.
   * @param string $damage_type
   *   Damage type (slashing, piercing, bludgeoning, fire, cold, etc.).
   * @param array $resistances
   *   Target's resistances: ['type' => value].
   * @param array $weaknesses
   *   Target's weaknesses: ['type' => value].
   *
   * @return array
   *   ['original' => int, 'resistance_applied' => int,
   *    'weakness_applied' => int, 'final' => int]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyresistancesweaknesses
   */
  public function applyResistancesWeaknesses($damage, $damage_type, array $resistances = [], array $weaknesses = []) {
    $original = (int) $damage;
    $type = strtolower((string) $damage_type);
    $current = $original;

    // Physical damage types also check 'physical' resistance.
    $physical_types = ['slashing', 'piercing', 'bludgeoning'];
    $check_types = [$type];
    if (in_array($type, $physical_types, TRUE)) {
      $check_types[] = 'physical';
    }
    // 'all' resistance applies to every damage type.
    $check_types[] = 'all';

    // Step 1: Apply best matching resistance.
    $best_resistance = 0;
    foreach ($check_types as $check) {
      if (isset($resistances[$check])) {
        $best_resistance = max($best_resistance, (int) $resistances[$check]);
      }
    }
    $current = max(0, $current - $best_resistance);

    // Step 2: Apply weakness (stacks; each matching weakness adds).
    $total_weakness = 0;
    foreach ($check_types as $check) {
      if (isset($weaknesses[$check])) {
        $total_weakness += (int) $weaknesses[$check];
      }
    }
    $current += $total_weakness;

    return [
      'original' => $original,
      'resistance_applied' => $best_resistance,
      'weakness_applied' => $total_weakness,
      'final' => max(0, $current),
    ];
  }

  /**
   * Calculate AC.
   *
   * PF2e AC = 10 + Dex modifier (capped by armor) + proficiency bonus
   *         + armor item bonus + shield bonus (if raised) + condition modifiers.
   *
   * @param int $base_ac
   *   Base AC (typically 10, or pre-calculated if armor already included).
   * @param int $dex_mod
   *   Dexterity modifier (already capped by armor's Dex cap if applicable).
   * @param int $armor_bonus
   *   Armor item bonus (includes proficiency if pre-calculated).
   * @param bool $shield_raised
   *   Whether a shield is currently raised.
   * @param array $conditions
   *   Condition modifiers: ['flat_footed' => -2, 'frightened' => -1, ...].
   * @param int $shield_bonus
   *   Shield's circumstance bonus to AC (default +2).
   *
   * @return array
   *   ['base' => int, 'dex_mod' => int, 'armor' => int, 'shield' => int,
   *    'conditions' => int, 'total' => int]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculateac
   */
  public function calculateAC($base_ac, $dex_mod, $armor_bonus, $shield_raised = FALSE, array $conditions = [], $shield_bonus = 2) {
    $base = (int) $base_ac;
    $dex = (int) $dex_mod;
    $armor = (int) $armor_bonus;
    $shield = $shield_raised ? (int) $shield_bonus : 0;
    $condition_total = array_sum(array_map('intval', $conditions));

    $total = $base + $dex + $armor + $shield + $condition_total;

    return [
      'base' => $base,
      'dex_mod' => $dex,
      'armor' => $armor,
      'shield' => $shield,
      'conditions' => $condition_total,
      'total' => max(0, $total),
    ];
  }

  /**
   * Calculate saving throw result.
   *
   * PF2e saving throw = d20 + ability modifier + proficiency + item + status.
   *
   * @param int $ability_mod
   *   Relevant ability modifier (Con for Fort, Dex for Ref, Wis for Will).
   * @param int $proficiency
   *   Save proficiency bonus (trained/expert/master/legendary + level).
   * @param int $item_bonus
   *   Item bonus to save (e.g. resilient rune).
   * @param array $other_bonuses
   *   Additional bonuses (status, circumstance).
   *
   * @return array
   *   ['roll' => int, 'modifier' => int, 'total' => int, 'is_natural_1' => bool,
   *    'is_natural_20' => bool]
   */
  public function rollSavingThrow($ability_mod, $proficiency = 0, $item_bonus = 0, array $other_bonuses = []) {
    $roll = $this->numberGeneration->rollPathfinderDie(20);
    $modifier = (int) $ability_mod + (int) $proficiency + (int) $item_bonus
              + BonusResolver::resolve($other_bonuses);
    $total = $roll + $modifier;

    return [
      'roll' => $roll,
      'modifier' => $modifier,
      'total' => $total,
      'is_natural_1' => $roll === 1,
      'is_natural_20' => $roll === 20,
    ];
  }

  /**
   * Calculate skill check result.
   *
   * PF2e skill check = d20 + ability modifier + proficiency + item + bonuses.
   *
   * @param int $ability_mod
   *   Relevant ability modifier.
   * @param int $proficiency
   *   Proficiency bonus (trained + level, etc.).
   * @param array $bonuses
   *   Additional bonuses.
   * @param array $penalties
   *   Additional penalties.
   *
   * @return array
   *   ['roll' => int, 'modifier' => int, 'total' => int, 'is_natural_1' => bool,
   *    'is_natural_20' => bool]
   */
  /**
   * Roll a flat check (req 2102–2107).
   *
   * PF2E flat check: roll d20, succeed if result ≥ DC.
   * DC ≤ 1 → automatic success. DC ≥ 21 → automatic failure.
   * Supports fortune (take higher of two rolls) and misfortune (take lower).
   * Fortune + misfortune together cancel to a single roll.
   *
   * @param int $dc
   *   Difficulty class.
   * @param array $options
   *   Optional flags:
   *   - 'fortune' (bool): roll twice, take higher
   *   - 'misfortune' (bool): roll twice, take lower
   *   - 'secret' (bool): mark result as a secret check (omit roll from response)
   *
   * @return array
   *   Keys: 'auto', 'success', 'roll' (null if auto or secret), 'dc', 'secret'
   */
  public function rollFlatCheck(int $dc, array $options = []): array {
    // DC bounds — req 2102.
    if ($dc <= 1) {
      return ['auto' => TRUE, 'success' => TRUE, 'roll' => NULL, 'dc' => $dc, 'secret' => FALSE];
    }
    if ($dc >= 21) {
      return ['auto' => TRUE, 'success' => FALSE, 'roll' => NULL, 'dc' => $dc, 'secret' => FALSE];
    }

    $fortune    = !empty($options['fortune']);
    $misfortune = !empty($options['misfortune']);
    $is_secret  = !empty($options['secret']);

    if ($fortune && $misfortune) {
      // Reqs 2107: cancel each other — single roll.
      $roll = $this->numberGeneration->rollPathfinderDie(20);
    }
    elseif ($fortune) {
      // Req 2105: take higher.
      $r1 = $this->numberGeneration->rollPathfinderDie(20);
      $r2 = $this->numberGeneration->rollPathfinderDie(20);
      $roll = max($r1, $r2);
    }
    elseif ($misfortune) {
      // Req 2106: take lower.
      $r1 = $this->numberGeneration->rollPathfinderDie(20);
      $r2 = $this->numberGeneration->rollPathfinderDie(20);
      $roll = min($r1, $r2);
    }
    else {
      $roll = $this->numberGeneration->rollPathfinderDie(20);
    }

    return [
      'auto'    => FALSE,
      'success' => $roll >= $dc,
      // Req 2104: omit roll value for secret checks.
      'roll'    => $is_secret ? NULL : $roll,
      'dc'      => $dc,
      'secret'  => $is_secret,
    ];
  }

  /**
   * REQ 2280: Hero Point reroll — spend 1 Hero Point to reroll any check.
   *
   * The second result MUST be used (it is a fortune effect — cannot stack with other fortune effects).
   *
   * @param int $original_roll  The original d20 roll value (before modifiers).
   * @return array Keys: original_roll, new_roll, used_result (= new_roll), is_fortune (true).
   */
  public function heroPointReroll(int $original_roll): array {
    $new_roll = $this->numberGeneration->rollPathfinderDie(20);
    return [
      'original_roll' => $original_roll,
      'new_roll'      => $new_roll,
      'used_result'   => $new_roll,
      'is_fortune'    => TRUE,
    ];
  }

  public function rollSkillCheck($ability_mod, $proficiency = 0, array $bonuses = [], array $penalties = []) {
    $roll = $this->numberGeneration->rollPathfinderDie(20);
    $modifier = (int) $ability_mod + (int) $proficiency
              + BonusResolver::resolve($bonuses)
              + BonusResolver::resolvePenalties($penalties);
    $total = $roll + $modifier;

    return [
      'roll' => $roll,
      'modifier' => $modifier,
      'total' => $total,
      'is_natural_1' => $roll === 1,
      'is_natural_20' => $roll === 20,
    ];
  }

  /**
   * DEF-2145: Proxy calculateDegreeOfSuccess to CombatCalculator.
   *
   * CounteractService and AfflictionManager call this method directly on
   * Calculator. Delegates to CombatCalculator to avoid duplicating logic.
   *
   * @param int $result
   *   The total roll + bonus value.
   * @param int $dc
   *   The difficulty class.
   * @param int|null $naturalRoll
   *   The raw die face (for nat-1 / nat-20 adjustments), or NULL.
   *
   * @return string
   *   'critical_success', 'success', 'failure', or 'critical_failure'.
   */
  public function calculateDegreeOfSuccess(int $result, int $dc, ?int $naturalRoll = NULL): string {
    return $this->combatCalculator->calculateDegreeOfSuccess($result, $dc, $naturalRoll);
  }

}
