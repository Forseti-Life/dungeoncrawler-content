<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * PF2E Critical Specialization effects (req 2116).
 *
 * Weapon group crit effects triggered on critical_success hits.
 * Each effect adds a secondary condition to the target in addition to damage.
 *
 * Group → effect mapping (Core Rulebook):
 *   bludgeoning  → prone
 *   slashing     → persistent bleed (1d6)
 *   piercing     → frightened 1
 *
 * @see /docs/dungeoncrawler/issues/combat-damage-rules.md
 */
class CritSpecializationService {

  /**
   * Condition manager service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected $conditionManager;

  /**
   * Constructor.
   */
  public function __construct(ConditionManager $condition_manager) {
    $this->conditionManager = $condition_manager;
  }

  /**
   * Apply crit specialization effect based on weapon group.
   *
   * @param string $target_id
   *   Combat participant ID.
   * @param string $weapon_group
   *   Weapon group (e.g. 'sword', 'axe', 'bow').  The relevant groupings are
   *   the damage-type families: 'bludgeoning', 'slashing', 'piercing'.
   *   Alternatively callers may pass the damage type directly.
   * @param string $damage_type
   *   The damage type dealt ('bludgeoning', 'slashing', 'piercing', ...).
   * @param mixed  $source
   *   Source reference passed through to ConditionManager.
   * @param string|int $encounter_id
   *   Encounter context.
   *
   * @return array
   *   ['applied' => bool, 'condition' => string|null, 'value' => int]
   */
  public function apply(string $target_id, string $weapon_group, string $damage_type, $source, $encounter_id): array {
    // Resolve which family governs the specialization effect.
    // Prefer $damage_type when it is a known family; fall back to $weapon_group.
    $family = $this->resolveFamily($damage_type) ?? $this->resolveFamily($weapon_group);

    if ($family === NULL) {
      return ['applied' => FALSE, 'condition' => NULL, 'value' => 0];
    }

    switch ($family) {
      case 'bludgeoning':
        $this->conditionManager->applyCondition(
          $target_id,
          'prone',
          1,
          ['type' => 'crit_specialization', 'remaining' => NULL],
          $source,
          $encounter_id
        );
        return ['applied' => TRUE, 'condition' => 'prone', 'value' => 1];

      case 'slashing':
        // Persistent bleed 1d6 — stored as a persistent_damage condition value.
        $bleed = random_int(1, 6);
        $this->conditionManager->applyCondition(
          $target_id,
          'persistent_bleed',
          $bleed,
          ['type' => 'crit_specialization', 'remaining' => NULL],
          $source,
          $encounter_id
        );
        return ['applied' => TRUE, 'condition' => 'persistent_bleed', 'value' => $bleed];

      case 'piercing':
        $this->conditionManager->applyCondition(
          $target_id,
          'frightened',
          1,
          ['type' => 'crit_specialization', 'remaining' => 1],
          $source,
          $encounter_id
        );
        return ['applied' => TRUE, 'condition' => 'frightened', 'value' => 1];

      default:
        return ['applied' => FALSE, 'condition' => NULL, 'value' => 0];
    }
  }

  /**
   * Resolve a string to a damage-type family.
   *
   * @param string $input
   *   Weapon group or damage type.
   *
   * @return string|null
   *   'bludgeoning', 'slashing', 'piercing', or NULL if not recognized.
   */
  protected function resolveFamily(string $input): ?string {
    $lower = strtolower($input);
    // Direct family names.
    if (in_array($lower, ['bludgeoning', 'slashing', 'piercing'], TRUE)) {
      return $lower;
    }
    // Common weapon group → family mappings.
    $group_map = [
      'club'     => 'bludgeoning',
      'flail'    => 'bludgeoning',
      'hammer'   => 'bludgeoning',
      'axe'      => 'slashing',
      'sword'    => 'slashing',
      'knife'    => 'piercing',
      'bow'      => 'piercing',
      'spear'    => 'piercing',
      'dart'     => 'piercing',
    ];
    return $group_map[$lower] ?? NULL;
  }

}
