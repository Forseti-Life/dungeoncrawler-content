<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\Calculator;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\ConditionManager;

/**
 * Handles PF2E counteract rules (Core Rulebook p.458–459).
 *
 * Counteracting is how spells/effects are ended early. The counteracting
 * participant makes a spellcasting check vs. the counteract DC of the
 * target effect. The result determines whether the effect is neutralised
 * based on the relative counteract levels of caster and target.
 *
 * Implements reqs 2145–2150.
 */
class CounteractService {

  protected Calculator $calculator;
  protected NumberGenerationService $numberGeneration;
  protected ConditionManager $conditionManager;

  public function __construct(Calculator $calculator, NumberGenerationService $number_generation, ConditionManager $condition_manager) {
    $this->calculator = $calculator;
    $this->numberGeneration = $number_generation;
    $this->conditionManager = $condition_manager;
  }

  /**
   * Get the counteract level for an effect.
   *
   * - Spells/abilities: use level directly (req 2146).
   * - Creatures and other effects: ceil(level / 2) (req 2146).
   *
   * @param string $type
   *   One of: 'spell', 'ability', 'creature', or any other type.
   * @param int $level
   *   The raw level of the effect or creature.
   *
   * @return int
   *   Counteract level.
   */
  public function getCounteractLevel(string $type, int $level): int {
    if ($type === 'spell') {
      return $level;
    }
    // All non-spell effects (abilities, creatures, etc.) use ceil(level/2) (req 2146).
    return (int) ceil($level / 2);
  }

  /**
   * Attempt to counteract an ongoing effect or spell.
   *
   * Rolls a d20 spellcasting check vs. the target effect's counteract DC.
   * Returns degree of success and whether counteracting succeeds based on
   * relative counteract levels (reqs 2147–2150).
   *
   * Counteract DC = 10 + target effect's counteract level + proficiency bonus
   * (simplified: we accept a pre-computed $target_dc or derive it from the
   * target_effect array using counteract_dc key, falling back to
   * 10 + counteract_level).
   *
   * @param array $caster
   *   Participant making the counteract attempt.
   *   Relevant keys: spell_attack_bonus, level, spell_level (for
   *   determining caster's counteract level).
   * @param array $target_effect
   *   Effect being counteracted. Keys:
   *   - 'level' (int): raw level of the effect.
   *   - 'type' (string): 'spell'|'ability'|'creature'|other.
   *   - 'effect_id' (mixed): identifier for the effect instance.
   *   - 'counteract_dc' (int, optional): pre-computed DC; if absent,
   *     derived as 10 + counteract_level.
   * @param int $encounter_id
   *   Current encounter ID (used for condition modifiers).
   *
   * @return array
   *   Keys:
   *   - 'natural_roll' (int): d20 result.
   *   - 'check_total' (int): roll + modifiers.
   *   - 'degree' (string): critical_success|success|failure|critical_failure.
   *   - 'counteract_level' (int): caster's counteract level.
   *   - 'target_level' (int): target effect's counteract level.
   *   - 'target_dc' (int): DC rolled against.
   *   - 'success' (bool): TRUE if the effect is counteracted.
   */
  public function attemptCounteract(array $caster, array $target_effect, int $encounter_id): array {
    $caster_id = (int) ($caster['id'] ?? 0);

    // Caster's counteract level uses their active spell's level (req 2145).
    $caster_spell_level = (int) ($caster['spell_level'] ?? $caster['level'] ?? 1);
    $caster_counteract_level = $this->getCounteractLevel('spell', $caster_spell_level);

    // Target effect's counteract level (req 2146).
    $target_type = $target_effect['type'] ?? 'spell';
    $target_raw_level = (int) ($target_effect['level'] ?? 1);
    $target_level = $this->getCounteractLevel($target_type, $target_raw_level);

    // Counteract DC (req 2147).
    $target_dc = isset($target_effect['counteract_dc'])
      ? (int) $target_effect['counteract_dc']
      : 10 + $target_level;

    // Spellcasting check: d20 + spell_attack_bonus + condition mods (req 2147).
    $natural_roll = $this->numberGeneration->rollPathfinderDie(20);
    $spell_mod = (int) ($caster['spell_attack_bonus'] ?? $caster['level'] ?? 0);
    $condition_mod = $this->conditionManager->getConditionModifiers($caster_id, 'spell_attack', $encounter_id);
    $check_total = $natural_roll + $spell_mod + $condition_mod;

    $degree = $this->calculator->calculateDegreeOfSuccess($check_total, $target_dc, $natural_roll);

    // Determine success based on degree and relative counteract levels
    // (reqs 2148–2150).
    $can_counteract = match($degree) {
      'critical_success' => $target_level <= ($caster_counteract_level + 3),
      'success'          => $target_level <= ($caster_counteract_level + 1),
      'failure'          => $target_level < $caster_counteract_level,
      'critical_failure' => FALSE,
      default            => FALSE,
    };

    return [
      'natural_roll'      => $natural_roll,
      'check_total'       => $check_total,
      'degree'            => $degree,
      'counteract_level'  => $caster_counteract_level,
      'target_level'      => $target_level,
      'target_dc'         => $target_dc,
      'success'           => $can_counteract,
    ];
  }

}
