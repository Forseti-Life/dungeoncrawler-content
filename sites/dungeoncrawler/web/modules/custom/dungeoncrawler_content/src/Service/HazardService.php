<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Core hazard logic: detection, triggering, disabling, HP tracking, XP.
 *
 * REQs 2373–2396 (PF2e Core Rulebook Ch10).
 *
 * Hazard entity structure (dungeon_data['entities'][]):
 * {
 *   "instance_id": "trap-001",
 *   "content_id": "floor-pit-trap",
 *   "type": "hazard",
 *   "name": "Pit Trap",
 *   "complexity": "simple",          // "simple" | "complex"
 *   "level": 1,
 *   "stealth_dc": 20,
 *   "stealth_modifier": 12,          // For complex initiative roll
 *   "disable": {
 *     "skill": "thievery",
 *     "dc": 20,
 *     "min_proficiency": "trained",  // Optional; "trained"|"expert"|"master"|"legendary"
 *     "successes_needed": 1          // > 1 for complex hazards
 *   },
 *   "trigger": {
 *     "type": "passive",             // "passive" | "active"
 *     "action": "open_door"          // Optional; for active triggers: which action fires it
 *   },
 *   "effect": {
 *     "damage": "2d6",
 *     "damage_type": "bludgeoning",
 *     "description": "Falls into pit"
 *   },
 *   "stats": {
 *     "ac": 10,
 *     "saves": {"fortitude": 1, "reflex": 1, "will": 0},
 *     "hardness": 0,
 *     "hp": 20,
 *     "bt": 10
 *   },
 *   "min_proficiency": "trained",    // Optional; min prof to attempt detection via Search
 *   "is_magical": false,
 *   "spell_level": null,             // If is_magical
 *   "counteract_dc": null,           // If is_magical
 *   "reset": "manual",              // "auto" | "manual" | null
 *   "routine": [],                   // Complex hazards: array of action strings per round
 *   "state": {
 *     "detected": false,
 *     "triggered": false,
 *     "disabled": false,
 *     "current_hp": 20,
 *     "successes": 0,
 *     "xp_awarded": false,
 *     "broken": false,
 *     "metadata": {"hidden": true}
 *   }
 * }
 */
class HazardService {

  /**
   * Proficiency rank name → numeric rank.
   */
  protected const PROFICIENCY_RANK = [
    'untrained'  => 0,
    'trained'    => 1,
    'expert'     => 2,
    'master'     => 3,
    'legendary'  => 4,
  ];

  /**
   * XP table: level differential → [simple_xp, complex_xp].
   * Based on PF2e Table 10-14 (hazard XP by level relative to party level).
   * Simple hazards = ~1/4 creature XP; complex hazards = creature XP.
   */
  protected const HAZARD_XP_TABLE = [
    -4 => [2,  10],
    -3 => [4,  15],
    -2 => [5,  20],
    -1 => [8,  30],
     0 => [10, 40],
     1 => [15, 60],
     2 => [20, 80],
     3 => [30, 120],
     4 => [40, 160],
  ];

  /**
   * Valid hazard_type values (GMG ch02).
   *
   * "haunt" type is distinct: deactivation is temporary; full destruction
   * requires resolving the underlying supernatural condition.
   */
  protected const VALID_HAZARD_TYPES = ['environmental', 'trap', 'haunt'];

  /**
   * APG hazard catalog templates (Advanced Player's Guide).
   *
   * Loaded alongside GMG hazards for GM encounter-building (dc-gmg-hazards AC).
   * Keys are content_id; values are stat-block templates following the same
   * entity structure as dungeon_data entities.
   */
  protected const APG_HAZARD_CATALOG = [
    'engulfing_snare' => [
      'content_id'        => 'engulfing_snare',
      'source'            => 'apg',
      'name'              => 'Engulfing Snare',
      'hazard_type'       => 'trap',
      'complexity'        => 'simple',
      'level'             => 2,
      'stealth_dc'        => 18,
      'stealth_modifier'  => 10,
      'disable'           => ['skill' => 'thievery', 'dc' => 18, 'successes_needed' => 1],
      'trigger'           => ['type' => 'passive', 'action' => NULL],
      'effect'            => [
        'damage'          => '1d10+6',
        'damage_type'     => 'piercing',
        'description'     => 'The snare springs shut and engulfs the triggering creature, dealing 1d10+6 piercing damage (DC 19 basic Reflex). On a failure the creature is also grabbed.',
        'save'            => ['type' => 'reflex', 'dc' => 19, 'basic' => TRUE],
        'conditions_applied' => ['grabbed'],
      ],
      'stats'             => ['ac' => NULL, 'saves' => [], 'hardness' => 0, 'hp' => NULL, 'bt' => NULL],
      'reset'             => 'manual',
      'is_magical'        => FALSE,
      'rarity'            => 'common',
      'traits'            => ['Mechanical', 'Trap', 'Snare'],
    ],
    'spike_trap_apg' => [
      'content_id'        => 'spike_trap_apg',
      'source'            => 'apg',
      'name'              => 'Spike Trap',
      'hazard_type'       => 'trap',
      'complexity'        => 'simple',
      'level'             => 4,
      'stealth_dc'        => 22,
      'stealth_modifier'  => 12,
      'disable'           => ['skill' => 'thievery', 'dc' => 22, 'successes_needed' => 1],
      'trigger'           => ['type' => 'passive', 'action' => NULL],
      'effect'            => [
        'damage'          => '2d8+8',
        'damage_type'     => 'piercing',
        'description'     => 'Spikes shoot upward dealing 2d8+8 piercing damage (DC 21 basic Reflex).',
        'save'            => ['type' => 'reflex', 'dc' => 21, 'basic' => TRUE],
        'conditions_applied' => [],
      ],
      'stats'             => ['ac' => NULL, 'saves' => [], 'hardness' => 0, 'hp' => NULL, 'bt' => NULL],
      'reset'             => 'manual',
      'is_magical'        => FALSE,
      'rarity'            => 'common',
      'traits'            => ['Mechanical', 'Trap'],
    ],
    'mirror_trap_apg' => [
      'content_id'        => 'mirror_trap_apg',
      'source'            => 'apg',
      'name'              => 'Mirror Trap',
      'hazard_type'       => 'trap',
      'complexity'        => 'simple',
      'level'             => 6,
      'stealth_dc'        => 26,
      'stealth_modifier'  => 15,
      'disable'           => ['skill' => 'thievery', 'dc' => 26, 'successes_needed' => 1],
      'trigger'           => ['type' => 'passive', 'action' => NULL],
      'effect'            => [
        'damage'          => NULL,
        'damage_type'     => NULL,
        'description'     => 'A magical mirror trap teleports the triggering creature to a random location within 30 feet (DC 24 Will; failure = random teleport).',
        'save'            => ['type' => 'will', 'dc' => 24, 'basic' => FALSE],
        'conditions_applied' => [],
      ],
      'stats'             => ['ac' => NULL, 'saves' => [], 'hardness' => 5, 'hp' => 20, 'bt' => 10],
      'reset'             => 'auto',
      'is_magical'        => TRUE,
      'spell_level'       => 3,
      'counteract_dc'     => 22,
      'rarity'            => 'uncommon',
      'traits'            => ['Magical', 'Trap', 'Teleportation'],
    ],
    'crushing_wall_apg' => [
      'content_id'        => 'crushing_wall_apg',
      'source'            => 'apg',
      'name'              => 'Crushing Wall',
      'hazard_type'       => 'environmental',
      'complexity'        => 'complex',
      'level'             => 8,
      'stealth_dc'        => 27,
      'stealth_modifier'  => 15,
      'disable'           => ['skill' => 'thievery', 'dc' => 27, 'successes_needed' => 2],
      'trigger'           => ['type' => 'passive', 'action' => NULL],
      'effect'            => [
        'damage'          => '3d10+15',
        'damage_type'     => 'bludgeoning',
        'description'     => 'The wall advances and crushes all creatures in its path for 3d10+15 bludgeoning (DC 28 basic Reflex per round).',
        'save'            => ['type' => 'reflex', 'dc' => 28, 'basic' => TRUE],
        'conditions_applied' => [],
      ],
      'stats'             => ['ac' => 28, 'saves' => ['fortitude' => 20], 'hardness' => 14, 'hp' => 60, 'bt' => 30],
      'reset'             => NULL,
      'is_magical'        => FALSE,
      'rarity'            => 'uncommon',
      'traits'            => ['Mechanical', 'Environmental'],
      'routine'           => ['Advance wall 5 feet and deal crush damage to all creatures in affected hexes.'],
    ],
  ];

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGenerator;

  public function __construct(NumberGenerationService $number_generation_service) {
    $this->numberGenerator = $number_generation_service;
  }

  // ---------------------------------------------------------------------------
  // Detection
  // ---------------------------------------------------------------------------

  /**
   * Returns the minimum proficiency rank integer for a hazard's detection/disable.
   *
   * @param array $hazard_entity
   *   Hazard entity array.
   * @param string $context
   *   'detection' or 'disable'.
   *
   * @return int
   *   0 = no minimum; 1 = Trained; 2 = Expert; etc.
   */
  public function getMinProfRank(array $hazard_entity, string $context = 'detection'): int {
    if ($context === 'disable') {
      $min = $hazard_entity['disable']['min_proficiency'] ?? NULL;
    }
    else {
      $min = $hazard_entity['min_proficiency'] ?? NULL;
    }
    if (!$min) {
      return 0;
    }
    return self::PROFICIENCY_RANK[strtolower($min)] ?? 0;
  }

  /**
   * Checks whether an actor meets the hazard's minimum proficiency requirement.
   *
   * @param array $hazard_entity
   *   Hazard entity.
   * @param int $actor_rank
   *   Actor's proficiency rank integer.
   * @param string $context
   *   'detection' or 'disable'.
   *
   * @return bool
   */
  public function meetsMinProficiency(array $hazard_entity, int $actor_rank, string $context = 'detection'): bool {
    return $actor_rank >= $this->getMinProfRank($hazard_entity, $context);
  }

  /**
   * Rolls secret Perception vs hazard's stealth_dc to attempt detection.
   *
   * REQ 2374: Auto-secret Perception vs stealth_dc on room entry for hazards
   * without min_proficiency. REQ 2375: With min_proficiency, only actors
   * actively Searching of qualifying rank may attempt.
   *
   * @param array $hazard_entity
   *   Hazard entity.
   * @param int $perception_bonus
   *   Actor's Perception bonus.
   * @param int $perception_rank
   *   Actor's Perception proficiency rank integer.
   * @param bool $is_searching
   *   TRUE if the character is actively using the Search exploration activity.
   *
   * @return array
   *   Keys: detected, roll, total, dc, degree, blocked (bool), blocked_reason.
   */
  public function rollHazardDetection(array $hazard_entity, int $perception_bonus, int $perception_rank, bool $is_searching): array {
    $min_rank = $this->getMinProfRank($hazard_entity, 'detection');
    $has_min_prof = $min_rank > 0;

    // REQ 2375: Min-proficiency hazards: only Searching chars of qualifying rank.
    if ($has_min_prof) {
      if (!$is_searching) {
        return [
          'detected'       => FALSE,
          'roll'           => 0,
          'total'          => 0,
          'dc'             => $hazard_entity['stealth_dc'] ?? 0,
          'degree'         => 'not_attempted',
          'blocked'        => TRUE,
          'blocked_reason' => 'Must be actively Searching to attempt detection of this hazard.',
        ];
      }
      if (!$this->meetsMinProficiency($hazard_entity, $perception_rank, 'detection')) {
        return [
          'detected'       => FALSE,
          'roll'           => 0,
          'total'          => 0,
          'dc'             => $hazard_entity['stealth_dc'] ?? 0,
          'degree'         => 'not_attempted',
          'blocked'        => TRUE,
          'blocked_reason' => 'Insufficient Perception proficiency to detect this hazard while Searching.',
        ];
      }
    }

    $dc = (int) ($hazard_entity['stealth_dc'] ?? 20);
    $roll = $this->numberGenerator->rollPathfinderDie(20);
    $total = $roll + $perception_bonus;
    $degree = $this->calculateDegreeOfSuccess($total, $dc, $roll);
    $detected = in_array($degree, ['success', 'critical_success'], TRUE);

    return [
      'detected'       => $detected,
      'roll'           => $roll,
      'total'          => $total,
      'dc'             => $dc,
      'degree'         => $degree,
      'blocked'        => FALSE,
      'blocked_reason' => NULL,
    ];
  }

  /**
   * Reveals a hazard: sets detected flag and removes hidden metadata.
   *
   * @param array $hazard_entity
   *   Hazard entity (passed by reference).
   */
  public function markDetected(array &$hazard_entity): void {
    $hazard_entity['state']['detected'] = TRUE;
    $hazard_entity['state']['metadata']['hidden'] = FALSE;
  }

  // ---------------------------------------------------------------------------
  // Triggering
  // ---------------------------------------------------------------------------

  /**
   * Triggers a hazard (passive or active): sets the triggered flag.
   *
   * REQ 2377: Passive triggers fire automatically on undetected entry.
   * REQ 2378: Active triggers fire when PC takes triggering action.
   *
   * @param array $hazard_entity
   *   Hazard entity (passed by reference).
   *
   * @return array
   *   Keys: triggered (bool), effect (array), already_triggered (bool).
   */
  public function triggerHazard(array &$hazard_entity): array {
    if (!empty($hazard_entity['state']['triggered'])) {
      return [
        'triggered'        => FALSE,
        'effect'           => [],
        'already_triggered' => TRUE,
      ];
    }
    if (!empty($hazard_entity['state']['disabled'])) {
      return [
        'triggered'         => FALSE,
        'effect'            => [],
        'already_triggered' => FALSE,
      ];
    }

    // GMG ch02: Destroyed haunts (supernatural condition resolved) cannot
    // re-activate.
    if (!empty($hazard_entity['state']['destroyed']) && $this->isHauntHazard($hazard_entity)) {
      return [
        'triggered'         => FALSE,
        'effect'            => [],
        'already_triggered' => FALSE,
        'blocked_reason'    => 'haunt_destroyed',
      ];
    }

    // GMG ch02: Deactivated haunts re-arm on re-trigger (deactivation is
    // temporary; the supernatural condition persists until resolved).
    if (!empty($hazard_entity['state']['deactivated']) && $this->isHauntHazard($hazard_entity)) {
      $hazard_entity['state']['deactivated'] = FALSE;
      $hazard_entity['state']['triggered'] = TRUE;
      return [
        'triggered'          => TRUE,
        'effect'             => $hazard_entity['effect'] ?? [],
        'already_triggered'  => FALSE,
        'haunt_reactivated'  => TRUE,
      ];
    }

    // REQ edge: Broken hazards cannot activate.
    if (!empty($hazard_entity['state']['broken'])) {
      return [
        'triggered'         => FALSE,
        'effect'            => [],
        'already_triggered' => FALSE,
        'blocked_reason'    => 'broken',
      ];
    }

    $hazard_entity['state']['triggered'] = TRUE;
    $effect = $hazard_entity['effect'] ?? [];

    return [
      'triggered'         => TRUE,
      'effect'            => $effect,
      'already_triggered' => FALSE,
    ];
  }

  // ---------------------------------------------------------------------------
  // Disabling
  // ---------------------------------------------------------------------------

  /**
   * Processes a Disable a Device attempt against a hazard.
   *
   * REQ 2384: Disable a Device action — 2 actions, skill check vs disable_dc.
   * REQ 2385: Crit fail triggers the hazard.
   * REQ 2386: Complex hazards may require multiple successes; crit success = 2.
   * REQ 2387: Min proficiency may apply.
   * REQ 2388: Actor must have detected the hazard.
   *
   * @param array $hazard_entity
   *   Hazard entity (passed by reference).
   * @param int $skill_bonus
   *   Actor's total skill bonus (proficiency + ability mod + item bonus).
   * @param int $skill_rank
   *   Actor's skill proficiency rank integer.
   * @param array $params
   *   Optional: has_thieves_tools (bool).
   *
   * @return array
   *   Keys: degree, disabled, triggered, blocked, blocked_reason, roll, total, dc, successes, successes_needed.
   */
  public function disableHazard(array &$hazard_entity, int $skill_bonus, int $skill_rank, array $params = []): array {
    // REQ 2388: Must have detected the hazard.
    if (empty($hazard_entity['state']['detected'])) {
      return [
        'degree'          => 'not_attempted',
        'disabled'        => FALSE,
        'triggered'       => FALSE,
        'blocked'         => TRUE,
        'blocked_reason'  => 'Cannot disable a hazard that has not been detected.',
        'roll'            => 0,
        'total'           => 0,
        'dc'              => 0,
        'successes'       => 0,
        'successes_needed' => 0,
      ];
    }

    if (!empty($hazard_entity['state']['disabled'])) {
      return [
        'degree'          => 'not_attempted',
        'disabled'        => TRUE,
        'triggered'       => FALSE,
        'blocked'         => FALSE,
        'blocked_reason'  => NULL,
        'roll'            => 0,
        'total'           => 0,
        'dc'              => 0,
        'successes'       => 0,
        'successes_needed' => 0,
      ];
    }

    // REQ 2387: Min proficiency gate.
    $min_rank = $this->getMinProfRank($hazard_entity, 'disable');
    if ($skill_rank < $min_rank) {
      return [
        'degree'          => 'not_attempted',
        'disabled'        => FALSE,
        'triggered'       => FALSE,
        'blocked'         => TRUE,
        'blocked_reason'  => 'Insufficient skill proficiency to attempt disabling this hazard.',
        'roll'            => 0,
        'total'           => 0,
        'dc'              => 0,
        'successes'       => 0,
        'successes_needed' => 0,
      ];
    }

    $dc = (int) ($hazard_entity['disable']['dc']
      ?? $hazard_entity['disable_dc']
      ?? $hazard_entity['disable']['thievery_dc']
      ?? 20);

    // Improvised tools penalty for Thievery-based hazards.
    $skill = strtolower($hazard_entity['disable']['skill'] ?? 'thievery');
    if ($skill === 'thievery' && empty($params['has_thieves_tools'])) {
      $dc += 5;
    }

    $roll = $this->numberGenerator->rollPathfinderDie(20);
    $total = $roll + $skill_bonus;
    $degree = $this->calculateDegreeOfSuccess($total, $dc, $roll);

    $successes_needed = (int) ($hazard_entity['disable']['successes_needed'] ?? 1);
    if (!isset($hazard_entity['state']['successes'])) {
      $hazard_entity['state']['successes'] = 0;
    }

    $triggered = FALSE;
    $disabled = FALSE;
    $deactivated = FALSE;

    if ($degree === 'critical_failure') {
      // REQ 2385: Crit fail on disable triggers the hazard.
      $triggered = TRUE;
      $trigger_result = $this->triggerHazard($hazard_entity);
    }
    elseif ($degree === 'critical_success') {
      // REQ 2386: Crit success = two successes.
      $hazard_entity['state']['successes'] += 2;
      if ($hazard_entity['state']['successes'] >= $successes_needed) {
        if ($this->isHauntHazard($hazard_entity)) {
          // GMG ch02: Haunt deactivation is temporary; not full destruction.
          $deactivated = TRUE;
          $hazard_entity['state']['deactivated'] = TRUE;
        }
        else {
          $disabled = TRUE;
          $hazard_entity['state']['disabled'] = TRUE;
        }
      }
    }
    elseif ($degree === 'success') {
      $hazard_entity['state']['successes']++;
      if ($hazard_entity['state']['successes'] >= $successes_needed) {
        if ($this->isHauntHazard($hazard_entity)) {
          // GMG ch02: Haunt deactivation is temporary; not full destruction.
          $deactivated = TRUE;
          $hazard_entity['state']['deactivated'] = TRUE;
        }
        else {
          $disabled = TRUE;
          $hazard_entity['state']['disabled'] = TRUE;
        }
      }
    }
    // Failure: no progress, no trigger.

    return [
      'degree'          => $degree,
      'disabled'        => $disabled,
      'deactivated'     => $deactivated,
      'triggered'       => $triggered,
      'blocked'         => FALSE,
      'blocked_reason'  => NULL,
      'roll'            => $roll,
      'total'           => $total,
      'dc'              => $dc,
      'successes'       => $hazard_entity['state']['successes'],
      'successes_needed' => $successes_needed,
    ];
  }

  // ---------------------------------------------------------------------------
  // Hazard HP / Damage
  // ---------------------------------------------------------------------------

  /**
   * Applies damage to a hazard, respecting Hardness. Updates HP, BT, broken.
   *
   * REQ 2390: BT/0-HP states.
   * REQ 2391: At BT: broken (non-functional); at 0 HP: destroyed.
   * REQ 2392: Hitting triggers unless the attack destroys it outright.
   *
   * @param array $hazard_entity
   *   Hazard entity (passed by reference).
   * @param int $raw_damage
   *   Raw damage before Hardness reduction.
   *
   * @return array
   *   Keys: effective_damage, hardness_absorbed, current_hp, broken, destroyed,
   *         triggered (bool — fires if hit but NOT destroyed outright).
   */
  public function applyDamageToHazard(array &$hazard_entity, int $raw_damage, bool $effect_can_target_objects = TRUE): array {
    // REQ: Hazards are immune to effects that cannot target objects.
    if (!$effect_can_target_objects) {
      return [
        'effective_damage'  => 0,
        'hardness_absorbed' => 0,
        'current_hp'        => (int) ($hazard_entity['state']['current_hp'] ?? $hazard_entity['stats']['hp'] ?? 0),
        'broken'            => $hazard_entity['state']['broken'] ?? FALSE,
        'destroyed'         => FALSE,
        'triggered'         => FALSE,
        'blocked_reason'    => 'Hazards are immune to effects that cannot target objects.',
      ];
    }

    $hardness = (int) ($hazard_entity['stats']['hardness'] ?? 0);
    $max_hp = (int) ($hazard_entity['stats']['hp'] ?? 0);
    $bt = (int) ($hazard_entity['stats']['bt'] ?? intdiv($max_hp, 2));

    // Initialize current_hp from state or stats.
    if (!isset($hazard_entity['state']['current_hp'])) {
      $hazard_entity['state']['current_hp'] = $max_hp;
    }
    $current_hp = (int) $hazard_entity['state']['current_hp'];

    $effective_damage = max(0, $raw_damage - $hardness);
    $current_hp -= $effective_damage;
    $hazard_entity['state']['current_hp'] = $current_hp;

    $destroyed = $current_hp <= 0;
    $broken = !$destroyed && $current_hp <= $bt;

    if ($broken) {
      $hazard_entity['state']['broken'] = TRUE;
    }
    if ($destroyed) {
      $hazard_entity['state']['broken'] = FALSE;
      $hazard_entity['state']['disabled'] = TRUE;
    }

    // REQ 2392: Attack triggers hazard unless it destroys outright.
    $triggered = FALSE;
    if (!$destroyed && $effective_damage > 0) {
      $trigger_result = $this->triggerHazard($hazard_entity);
      $triggered = $trigger_result['triggered'];
    }

    return [
      'effective_damage'  => $effective_damage,
      'hardness_absorbed' => min($raw_damage, $hardness),
      'current_hp'        => $hazard_entity['state']['current_hp'],
      'broken'            => $hazard_entity['state']['broken'] ?? FALSE,
      'destroyed'         => $destroyed,
      'triggered'         => $triggered,
    ];
  }

  // ---------------------------------------------------------------------------
  // Magical Hazards
  // ---------------------------------------------------------------------------

  /**
   * Attempts to counteract a magical hazard.
   *
   * REQ 2393: Magical hazards have spell_level and counteract_dc.
   * REQ 2394: Crit fail on counteract triggers the hazard.
   *
   * @param array $hazard_entity
   *   Hazard entity (passed by reference).
   * @param int $counteract_level
   *   The caster's counteract level (usually half spell rank, rounded up).
   * @param int $total
   *   Total check result (d20 + modifier).
   * @param int $natural_roll
   *   Natural d20 value.
   *
   * @return array
   *   Keys: degree, counteracted, triggered, blocked, spell_level, counteract_dc.
   */
  public function counteractMagicalHazard(array &$hazard_entity, int $counteract_level, int $total, int $natural_roll): array {
    if (empty($hazard_entity['is_magical'])) {
      return [
        'degree'       => 'not_attempted',
        'counteracted' => FALSE,
        'triggered'    => FALSE,
        'blocked'      => TRUE,
        'blocked_reason' => 'This hazard is not magical.',
        'spell_level'  => NULL,
        'counteract_dc' => NULL,
      ];
    }

    $spell_level = (int) ($hazard_entity['spell_level'] ?? 1);
    $counteract_dc = (int) ($hazard_entity['counteract_dc'] ?? 15);

    $degree = $this->calculateDegreeOfSuccess($total, $counteract_dc, $natural_roll);

    $counteracted = FALSE;
    $triggered = FALSE;

    if ($degree === 'critical_failure') {
      // REQ 2394: Crit fail counteract triggers the hazard.
      $triggered = TRUE;
      $this->triggerHazard($hazard_entity);
    }
    elseif ($degree === 'success' || $degree === 'critical_success') {
      // Counteract succeeds if counteract level >= spell_level (or >= spell_level - 3 for success).
      $level_diff = $counteract_level - $spell_level;
      if ($degree === 'critical_success' && $level_diff >= -3) {
        $counteracted = TRUE;
      }
      elseif ($degree === 'success' && $level_diff >= 0) {
        $counteracted = TRUE;
      }
      if ($counteracted) {
        $hazard_entity['state']['disabled'] = TRUE;
      }
    }

    return [
      'degree'        => $degree,
      'counteracted'  => $counteracted,
      'triggered'     => $triggered,
      'blocked'       => FALSE,
      'blocked_reason' => NULL,
      'spell_level'   => $spell_level,
      'counteract_dc' => $counteract_dc,
    ];
  }

  // ---------------------------------------------------------------------------
  // XP
  // ---------------------------------------------------------------------------

  /**
   * Awards hazard XP once per hazard per party, if not already awarded.
   *
   * REQ 2395: XP awarded on overcoming (disable, avoid, or endure).
   * REQ 2396: Awarded only once per hazard per party.
   *
   * @param array $game_state
   *   Game state (passed by reference); stores 'hazard_xp_awarded' tracking.
   * @param array $hazard_entity
   *   Hazard entity.
   * @param int $party_level
   *   Current average party level.
   *
   * @return int
   *   XP awarded (0 if already awarded or hazard not overcome).
   */
  public function awardHazardXp(array &$game_state, array $hazard_entity, int $party_level): int {
    $hazard_id = $hazard_entity['instance_id'] ?? $hazard_entity['id'] ?? NULL;
    if (!$hazard_id) {
      return 0;
    }

    if (!isset($game_state['hazard_xp_awarded'])) {
      $game_state['hazard_xp_awarded'] = [];
    }

    // REQ 2396: One-time only.
    if (!empty($game_state['hazard_xp_awarded'][$hazard_id])) {
      return 0;
    }

    $hazard_level = (int) ($hazard_entity['level'] ?? 0);
    $complexity = $hazard_entity['complexity'] ?? 'simple';
    $xp = $this->getHazardXpAmount($hazard_level, $party_level, $complexity);

    $game_state['hazard_xp_awarded'][$hazard_id] = TRUE;

    return $xp;
  }

  /**
   * Returns hazard XP for a given level differential and complexity.
   *
   * Based on PF2e Table 10-14 (Fourth Printing).
   *
   * @param int $hazard_level
   * @param int $party_level
   * @param string $complexity
   *   'simple' or 'complex'.
   *
   * @return int
   */
  protected function getHazardXpAmount(int $hazard_level, int $party_level, string $complexity): int {
    $diff = $hazard_level - $party_level;
    $diff = max(-4, min(4, $diff));

    $table = self::HAZARD_XP_TABLE[$diff] ?? [8, 30];
    return $complexity === 'complex' ? $table[1] : $table[0];
  }

  // ---------------------------------------------------------------------------
  // Reset
  // ---------------------------------------------------------------------------

  /**
   * Resets a hazard to its initial armed state (clears triggered/disabled flags).
   *
   * REQ: Hazards with reset="auto" or reset="manual" can be re-armed.
   * Broken/destroyed hazards are not reset by this method.
   *
   * @param array $hazard_entity
   *   Hazard entity (passed by reference).
   *
   * @return array
   *   Keys: reset (bool), blocked (bool), blocked_reason (string|null).
   */
  public function resetHazard(array &$hazard_entity): array {
    $reset_type = $hazard_entity['reset'] ?? NULL;
    if (!$reset_type) {
      return ['reset' => FALSE, 'blocked' => TRUE, 'blocked_reason' => 'This hazard has no reset property.'];
    }
    if (!empty($hazard_entity['state']['disabled']) && ($hazard_entity['state']['current_hp'] ?? 1) <= 0) {
      return ['reset' => FALSE, 'blocked' => TRUE, 'blocked_reason' => 'Destroyed hazards cannot be reset.'];
    }
    // GMG ch02: Haunts whose supernatural condition is resolved are permanently
    // destroyed; they cannot be reset.
    if (!empty($hazard_entity['state']['destroyed']) && $this->isHauntHazard($hazard_entity)) {
      return ['reset' => FALSE, 'blocked' => TRUE, 'blocked_reason' => 'This haunt\'s supernatural condition has been resolved; it is permanently destroyed.'];
    }
    // Clear triggered/disabled/deactivated; preserve broken so repair is still needed if applicable.
    $hazard_entity['state']['triggered'] = FALSE;
    $hazard_entity['state']['disabled'] = FALSE;
    $hazard_entity['state']['deactivated'] = FALSE;
    $hazard_entity['state']['successes'] = 0;
    return ['reset' => TRUE, 'blocked' => FALSE, 'blocked_reason' => NULL];
  }

  // ---------------------------------------------------------------------------
  // Room hazard helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns all hazard entities in the current active room.
   *
   * @param array $dungeon_data
   *   Dungeon data array.
   * @param bool $include_resolved
   *   Include disabled/triggered hazards. Default FALSE (only active hazards).
   *
   * @return array
   *   List of hazard entities (by reference not available here; caller must
   *   re-lookup by instance_id to mutate).
   */
  public function getRoomHazards(array $dungeon_data, bool $include_resolved = FALSE): array {
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;
    $hazards = [];

    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if (($entity['type'] ?? '') !== 'hazard') {
        continue;
      }
      if (($entity['placement']['room_id'] ?? NULL) !== $active_room_id) {
        continue;
      }
      if (!$include_resolved) {
        if (!empty($entity['state']['disabled']) || !empty($entity['state']['triggered'])) {
          continue;
        }
        // GMG ch02: Destroyed haunts are permanently gone; exclude them.
        if (!empty($entity['state']['destroyed']) && $this->isHauntHazard($entity)) {
          continue;
        }
        // GMG ch02: Deactivated haunts still "exist" in the room (the
        // supernatural condition is unresolved); include them so the GM can
        // see them and so re-trigger detection logic fires correctly.
      }
      $hazards[] = $entity;
    }

    return $hazards;
  }

  /**
   * Returns a reference to a hazard entity by instance_id within dungeon_data.
   *
   * @param string $instance_id
   * @param array $dungeon_data
   *   Passed by reference so the caller can mutate the found entity.
   *
   * @return array|null
   */
  public function &findHazardByInstanceId(string $instance_id, array &$dungeon_data): ?array {
    foreach ($dungeon_data['entities'] as &$entity) {
      if (($entity['instance_id'] ?? $entity['id'] ?? NULL) === $instance_id) {
        return $entity;
      }
    }
    unset($entity);
    $null = NULL;
    return $null;
  }

  // ---------------------------------------------------------------------------
  // Complex hazard: initiative
  // ---------------------------------------------------------------------------

  /**
   * Rolls initiative for a complex hazard using its Stealth modifier.
   *
   * REQ 2382: Complex hazard enters at its own initiative using Stealth modifier.
   *
   * @param array $hazard_entity
   *   Hazard entity.
   *
   * @return array
   *   Keys: roll, total, modifier.
   */
  public function rollComplexHazardInitiative(array $hazard_entity): array {
    $modifier = (int) ($hazard_entity['stealth_modifier'] ?? 0);
    $roll = $this->numberGenerator->rollPathfinderDie(20);
    $total = $roll + $modifier;
    return ['roll' => $roll, 'modifier' => $modifier, 'total' => $total];
  }

  // ---------------------------------------------------------------------------
  // Degree of Success helper
  // ---------------------------------------------------------------------------

  /**
   * Calculates PF2e degree of success.
   */
  protected function calculateDegreeOfSuccess(int $total, int $dc, int $natural_roll): string {
    $diff = $total - $dc;
    if ($diff >= 10 || $natural_roll === 20) {
      return 'critical_success';
    }
    if ($diff >= 0) {
      return 'success';
    }
    if ($diff >= -10 && $natural_roll !== 1) {
      return 'failure';
    }
    return 'critical_failure';
  }

  // ---------------------------------------------------------------------------
  // GMG ch02: Haunt hazard helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns TRUE if this hazard entity is a haunt type.
   *
   * GMG ch02: Haunts are supernatural hazards. Disabling one is temporary
   * (deactivation); the hazard can re-trigger until the underlying supernatural
   * condition is resolved via resolveHauntCondition().
   *
   * @param array $hazard_entity
   *   Hazard entity array.
   *
   * @return bool
   */
  public function isHauntHazard(array $hazard_entity): bool {
    $hazard_type = strtolower($hazard_entity['hazard_type'] ?? '');
    if ($hazard_type === 'haunt') {
      return TRUE;
    }
    // Fallback: check traits array for 'Haunt' (older entity format).
    $traits = array_map('strtolower', $hazard_entity['traits'] ?? []);
    return in_array('haunt', $traits, TRUE);
  }

  /**
   * Returns the display state string for a hazard entity (GMG ch02).
   *
   * For haunts: distinguishes "deactivated" (temporary) from "destroyed"
   * (supernatural condition resolved). For standard hazards: uses
   * "disabled"/"triggered"/"destroyed"/"active".
   *
   * @param array $hazard_entity
   *   Hazard entity array.
   *
   * @return string
   *   One of: active|deactivated|disabled|triggered|destroyed|broken.
   */
  public function getHazardDisplayState(array $hazard_entity): string {
    $state = $hazard_entity['state'] ?? [];

    if ($this->isHauntHazard($hazard_entity)) {
      if (!empty($state['destroyed'])) {
        return 'destroyed';
      }
      if (!empty($state['deactivated'])) {
        return 'deactivated';
      }
    }
    else {
      if (($state['current_hp'] ?? 1) <= 0) {
        return 'destroyed';
      }
      if (!empty($state['disabled'])) {
        return 'disabled';
      }
    }

    if (!empty($state['broken'])) {
      return 'broken';
    }
    if (!empty($state['triggered'])) {
      return 'triggered';
    }
    return 'active';
  }

  /**
   * Resolves the supernatural condition of a haunt, permanently destroying it.
   *
   * GMG ch02: A haunt is not truly destroyed until its underlying supernatural
   * cause is resolved (e.g., the spirit is put to rest, the cursed object is
   * removed). After this call the haunt will not re-trigger and cannot be reset.
   *
   * @param array $hazard_entity
   *   Haunt hazard entity (passed by reference).
   * @param string $resolution_method
   *   Description of how the condition was resolved (logged for traceability).
   *
   * @return array
   *   Keys: resolved (bool), blocked (bool), blocked_reason (string|null),
   *         resolution_method (string).
   */
  public function resolveHauntCondition(array &$hazard_entity, string $resolution_method = 'condition_resolved'): array {
    if (!$this->isHauntHazard($hazard_entity)) {
      return [
        'resolved'         => FALSE,
        'blocked'          => TRUE,
        'blocked_reason'   => 'resolveHauntCondition() only applies to haunt hazards.',
        'resolution_method' => $resolution_method,
      ];
    }

    $hazard_entity['state']['destroyed'] = TRUE;
    $hazard_entity['state']['deactivated'] = FALSE;
    $hazard_entity['state']['triggered'] = FALSE;
    $hazard_entity['state']['disabled'] = FALSE;
    $hazard_entity['state']['haunt_condition_resolved'] = TRUE;
    $hazard_entity['state']['resolution_method'] = $resolution_method;

    return [
      'resolved'          => TRUE,
      'blocked'           => FALSE,
      'blocked_reason'    => NULL,
      'resolution_method' => $resolution_method,
    ];
  }

  // ---------------------------------------------------------------------------
  // GMG ch02: APG hazard catalog
  // ---------------------------------------------------------------------------

  /**
   * Returns all APG hazard catalog templates, optionally filtered by type.
   *
   * These templates are merged into the hazard catalog alongside GMG hazards
   * for GM encounter-building (dc-gmg-hazards AC).
   *
   * @param string|null $hazard_type
   *   Optional filter: 'trap'|'environmental'|'haunt'. NULL returns all.
   *
   * @return array
   *   Array of hazard catalog entries keyed by content_id.
   */
  public function getApgHazardCatalog(?string $hazard_type = NULL): array {
    if ($hazard_type === NULL) {
      return self::APG_HAZARD_CATALOG;
    }
    return array_filter(
      self::APG_HAZARD_CATALOG,
      fn($entry) => ($entry['hazard_type'] ?? '') === $hazard_type
    );
  }

  /**
   * Returns a single APG hazard template by content_id, or NULL if not found.
   *
   * @param string $content_id
   *
   * @return array|null
   */
  public function getApgHazardTemplate(string $content_id): ?array {
    return self::APG_HAZARD_CATALOG[$content_id] ?? NULL;
  }

  /**
   * Returns TRUE if the given content_id is a valid hazard_type value.
   *
   * @param string $hazard_type
   *
   * @return bool
   */
  public function isValidHazardType(string $hazard_type): bool {
    return in_array(strtolower($hazard_type), self::VALID_HAZARD_TYPES, TRUE);
  }

}
