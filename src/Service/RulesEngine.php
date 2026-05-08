<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\Calculator;
use Drupal\dungeoncrawler_content\Service\LineOfEffectService;

/**
 * Rules Engine service - Validates actions against PF2e rules.
 *
 * @see /docs/dungeoncrawler/issues/combat-action-validation.md
 */
class RulesEngine {

  protected $database;
  protected $calculator;
  protected $losService;

  public function __construct(Connection $database, ?Calculator $calculator = NULL, ?LineOfEffectService $los_service = NULL) {
    $this->database = $database;
    $this->calculator = $calculator;
    $this->losService = $los_service;
  }

  /**
   * Validate action.
   *
   * 6-layer validation: state → economy → conditions → prerequisites → resources → targets
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md
   */
  public function validateAction($participant_id, $action, $encounter_id) {
    $action = is_array($action) ? $action : ['type' => $action];
    $action_type = strtolower($action['type'] ?? '');
    $action_cost = $action['cost'] ?? 1;

    // Load participant state from DB.
    $participant = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('p.id', (int) $participant_id)
      ->condition('p.encounter_id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$participant) {
      return ['is_valid' => FALSE, 'reason' => 'Participant not found in encounter.'];
    }

    // Layer 1: State — participant must be alive and active.
    if (!empty($participant['is_defeated'])) {
      return ['is_valid' => FALSE, 'reason' => 'Participant is defeated.'];
    }

    // Layer 2: Action economy — enough actions/reaction available.
    $economy = $this->validateActionEconomy($participant, $action_cost);
    if (!$economy['is_valid']) {
      return ['is_valid' => FALSE, 'reason' => $economy['reason']];
    }

    // Layer 3: Condition restrictions — paralyzed, unconscious, grabbed, etc.
    $participant['encounter_id'] = (int) $encounter_id;
    $cond_check = $this->checkConditionRestrictions($participant, $action_type);
    if (!$cond_check['can_act']) {
      return ['is_valid' => FALSE, 'reason' => $cond_check['restriction']];
    }

    // Layer 4: Prerequisites — weapon/spell slots/shield required checks.
    $target = NULL;
    if (!empty($action['target_id'])) {
      $target = $this->database->select('combat_participants', 'p')
        ->fields('p')
        ->condition('p.id', (int) $action['target_id'])
        ->condition('p.encounter_id', (int) $encounter_id)
        ->execute()
        ->fetchAssoc();
    }
    $prereq = $this->validateActionPrerequisites($participant, $action, $target);
    if (!$prereq['is_valid']) {
      return ['is_valid' => FALSE, 'reason' => $prereq['reason']];
    }

    // Layer 5: Type-specific validation (attack range, spell cast, etc.).
    if ($action_type === 'strike' && $target) {
      $weapon = (array) ($action['weapon'] ?? []);
      $attack_check = $this->validateAttack($participant, $target, $weapon, (int) $encounter_id);
      if (!$attack_check['is_valid']) {
        return ['is_valid' => FALSE, 'reason' => $attack_check['reason']];
      }
    }
    elseif ($action_type === 'cast_spell') {
      $spell = $action['spell'] ?? $action['spell_name'] ?? '';
      $spell_level = (int) ($action['spell_level'] ?? 1);
      $targets = [];
      if ($target) {
        $targets[] = $target;
      }
      $spell_check = $this->validateSpellCast($participant, $spell, $spell_level, $targets, (int) $encounter_id);
      if (!$spell_check['is_valid']) {
        return ['is_valid' => FALSE, 'reason' => $spell_check['reason']];
      }
    }

    // Layer 6: Immunities — check if target is immune to the effect.
    if ($target && !empty($action['effect_type'])) {
      $immunity = $this->checkImmunities($target, $action['effect_type'], $action_type);
      if ($immunity['is_immune']) {
        return ['is_valid' => FALSE, 'reason' => "Target is immune ({$immunity['immunity_type']})."];
      }
    }

    return ['is_valid' => TRUE, 'reason' => ''];
  }

  /**
   * Validate action economy.
   *
   * Enforces PF2E three-action economy: 3 actions + 1 reaction per turn.
   * Valid costs: 1, 2, 3 (integer actions), 'free' (no cost), 'reaction'.
   *
   * @param array $participant Participant state array with keys:
   *   - actions_remaining (int 0–3)
   *   - reaction_available (bool/int)
   * @param int|string $action_cost 1, 2, 3, 'free', or 'reaction'.
   *
   * @return array ['is_valid' => bool, 'reason' => string, 'actions_after' => int|null]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#action-economy-validation
   */
  public function validateActionEconomy($participant, $action_cost) {
    $valid_costs = [1, 2, 3, 'free', 'reaction'];
    if (!in_array($action_cost, $valid_costs, TRUE)) {
      return [
        'is_valid' => FALSE,
        'reason' => 'Invalid action cost: ' . json_encode($action_cost) . '. Must be 1, 2, 3, "free", or "reaction".',
        'actions_after' => NULL,
      ];
    }

    $actions_remaining = (int) ($participant['actions_remaining'] ?? 0);

    if ($action_cost === 'free') {
      return ['is_valid' => TRUE, 'reason' => '', 'actions_after' => $actions_remaining];
    }

    if ($action_cost === 'reaction') {
      $available = !empty($participant['reaction_available']);
      return [
        'is_valid' => $available,
        'reason' => $available ? '' : 'Reaction already used this turn.',
        'actions_after' => $actions_remaining,
      ];
    }

    // Integer action cost (1, 2, or 3).
    $cost = (int) $action_cost;
    if ($actions_remaining < $cost) {
      return [
        'is_valid' => FALSE,
        'reason' => 'Not enough actions. Need ' . $cost . ', have ' . $actions_remaining . '.',
        'actions_after' => $actions_remaining,
      ];
    }

    $after = max(0, $actions_remaining - $cost);
    return ['is_valid' => TRUE, 'reason' => '', 'actions_after' => $after];
  }

  /**
   * Validate action prerequisites.
   *
   * Checks that the participant has the requirements to perform the action:
   * - Strike: must have a weapon or unarmed attack available
   * - Cast Spell: must have spell slots remaining
   * - Raise a Shield: must have a shield equipped
   * - Move: must not be immobilized/restrained unless action overcomes
   *
   * @param array $participant
   *   Participant state array.
   * @param array|string $action
   *   Action data array with 'type' key, or action type string.
   * @param array|null $target
   *   Target participant array, if applicable.
   *
   * @return array
   *   ['is_valid' => bool, 'reason' => string]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#action-prerequisite-rules
   */
  public function validateActionPrerequisites($participant, $action, $target = NULL) {
    $participant = (array) $participant;
    $action_type = is_string($action) ? $action : ($action['type'] ?? '');
    $action_type = strtolower($action_type);
    $entity_ref = $this->decodeEntityRef($participant);

    switch ($action_type) {
      case 'strike':
        // Must have a weapon or natural/unarmed attack.
        $has_weapon = !empty($entity_ref['weapon'])
          || !empty($entity_ref['melee_attack'])
          || !empty($entity_ref['attack_bonus'])
          || !empty($entity_ref['natural_attack']);
        if (!$has_weapon) {
          return ['is_valid' => FALSE, 'reason' => 'No weapon or natural attack available.'];
        }
        break;

      case 'cast_spell':
        $action_data = is_array($action) ? $action : [];
        $spell_level = (int) ($action_data['spell_level'] ?? 1);
        $slots = (array) ($entity_ref['spell_slots'] ?? []);
        $remaining = (int) ($slots[$spell_level] ?? $slots['level_' . $spell_level] ?? 0);
        // Cantrips (level 0) are unlimited.
        if ($spell_level > 0 && $remaining <= 0) {
          return ['is_valid' => FALSE, 'reason' => "No spell slots remaining at level {$spell_level}."];
        }
        break;

      case 'raise_shield':
        if (empty($entity_ref['shield']) && empty($entity_ref['has_shield'])) {
          return ['is_valid' => FALSE, 'reason' => 'No shield equipped.'];
        }
        if (!empty($entity_ref['shield']['broken'])) {
          return ['is_valid' => FALSE, 'reason' => 'Shield is broken.'];
        }
        break;
    }

    return ['is_valid' => TRUE, 'reason' => ''];
  }

  /**
   * Check condition restrictions.
   *
   * Queries active combat_conditions for the participant and returns the most
   * restrictive applicable restriction for the given action type.
   *
   * Rules implemented:
   *   - paralyzed  → cannot_act (all actions blocked)
   *   - unconscious → cannot_act (all actions blocked)
   *   - petrified  → cannot_act (all actions blocked)
   *   - dying      → cannot_act (all actions blocked while dying)
   *   - grabbed    → cannot_move (movement actions blocked)
   *   - immobilized → cannot_move
   *   - restrained → cannot_move
   *
   * @param array|object $participant Participant row; must contain 'id' and 'encounter_id'.
   * @param string $action_type The action type being attempted.
   *
   * @return array ['can_act' => bool, 'restriction' => string]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#condition-restriction-rules
   */
  public function checkConditionRestrictions($participant, $action_type) {
    $participant = (array) $participant;
    $participant_id = (int) ($participant['id'] ?? 0);
    $encounter_id   = (int) ($participant['encounter_id'] ?? 0);

    if (!$participant_id || !$encounter_id) {
      return ['can_act' => TRUE, 'restriction' => ''];
    }

    $blocking_act  = ['paralyzed', 'unconscious', 'petrified', 'dying'];
    $blocking_move = ['grabbed', 'immobilized', 'restrained'];

    $rows = $this->database->select('combat_conditions', 'c')
      ->fields('c', ['condition_type'])
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->isNull('removed_at_round')
      ->execute()
      ->fetchCol();

    foreach ($blocking_act as $cond) {
      if (in_array($cond, $rows, TRUE)) {
        return ['can_act' => FALSE, 'restriction' => "Cannot act: {$cond}"];
      }
    }

    $move_actions = ['move', 'stride', 'step', 'crawl', 'fly', 'swim'];
    if (in_array($action_type, $move_actions, TRUE)) {
      foreach ($blocking_move as $cond) {
        if (in_array($cond, $rows, TRUE)) {
          return ['can_act' => FALSE, 'restriction' => "Cannot move: {$cond}"];
        }
      }
    }

    return ['can_act' => TRUE, 'restriction' => ''];
  }

  /**
   * Check immunities.
   *
   * PF2e immunities: Some creatures are immune to specific damage types,
   * conditions, or effects. Immunity means the effect has no impact.
   *
   * @param array $participant
   *   Participant state array.
   * @param string $effect_type
   *   Effect type to check immunity against (condition name or damage type).
   * @param string|array $effect_source
   *   Source of the effect.
   *
   * @return array
   *   ['is_immune' => bool, 'immunity_type' => string|null]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md
   */
  public function checkImmunities($participant, $effect_type, $effect_source = '') {
    $participant = (array) $participant;
    $entity_ref = $this->decodeEntityRef($participant);
    $immunities = (array) ($entity_ref['immunities'] ?? []);

    $check = strtolower((string) $effect_type);

    // Direct immunity match.
    $immunity_list = array_map('strtolower', $immunities);
    if (in_array($check, $immunity_list, TRUE)) {
      return ['is_immune' => TRUE, 'immunity_type' => $check];
    }

    // PF2E req 2118: critical_hits immunity — downgrade crit_success to success.
    // Callers should check immunity_type === 'critical_hits' to downgrade degree.
    if ($check === 'critical_hits' && in_array('critical_hits', $immunity_list, TRUE)) {
      return ['is_immune' => TRUE, 'immunity_type' => 'critical_hits'];
    }

    // PF2E req 2119: precision immunity — strip precision damage components.
    // Callers should check immunity_type === 'precision' to strip precision bonus.
    if ($check === 'precision' && in_array('precision', $immunity_list, TRUE)) {
      return ['is_immune' => TRUE, 'immunity_type' => 'precision'];
    }

    // Undead are immune to death effects, disease, poison, unconscious.
    $traits = array_map('strtolower', (array) ($entity_ref['traits'] ?? []));
    if (in_array('undead', $traits, TRUE)) {
      $undead_immunities = ['death', 'disease', 'poison', 'unconscious', 'paralyzed', 'fatigued'];
      if (in_array($check, $undead_immunities, TRUE)) {
        return ['is_immune' => TRUE, 'immunity_type' => 'undead_immunity'];
      }
    }

    // Constructs are immune to bleed, death, disease, doomed, drained,
    // fatigued, healing, necromancy, paralyzed, poison, sickened, unconscious.
    if (in_array('construct', $traits, TRUE)) {
      $construct_immunities = ['bleed', 'death', 'disease', 'doomed', 'drained',
        'fatigued', 'healing', 'necromancy', 'paralyzed', 'poison', 'sickened', 'unconscious'];
      if (in_array($check, $construct_immunities, TRUE)) {
        return ['is_immune' => TRUE, 'immunity_type' => 'construct_immunity'];
      }
    }

    return ['is_immune' => FALSE, 'immunity_type' => NULL];
  }

  /**
   * Validate attack.
   *
   * Multi-layer validation:
   * 1. Attacker is alive and not defeated.
   * 2. Target is alive and not already dead.
   * 3. Attacker has a weapon or natural attack.
   * 4. Target is within weapon range (hex distance).
   * 5. Condition restrictions (paralyzed, unconscious, etc.).
   *
   * @param array $attacker
   *   Attacker participant state.
   * @param array $target
   *   Target participant state.
   * @param array $weapon
   *   Weapon data: ['range' => int, 'type' => 'melee'|'ranged', ...].
   * @param int $encounter_id
   *
   * @return array
   *   ['is_valid' => bool, 'reason' => string, 'modifiers' => array]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#attack-validation
   */
  public function validateAttack($attacker, $target, $weapon = [], $encounter_id = 0) {
    $attacker = (array) $attacker;
    $target = (array) $target;
    $weapon = (array) $weapon;
    $modifiers = [];

    // 1. Attacker alive.
    if (!empty($attacker['is_defeated'])) {
      return ['is_valid' => FALSE, 'reason' => 'Attacker is defeated.', 'modifiers' => []];
    }

    // 2. Target alive.
    if (!empty($target['is_defeated'])) {
      return ['is_valid' => FALSE, 'reason' => 'Target is already defeated.', 'modifiers' => []];
    }

    // 3. Cannot target self.
    $attacker_id = (int) ($attacker['id'] ?? 0);
    $target_id = (int) ($target['id'] ?? 0);
    if ($attacker_id > 0 && $attacker_id === $target_id) {
      return ['is_valid' => FALSE, 'reason' => 'Cannot attack self.', 'modifiers' => []];
    }

    // 4. Condition restrictions.
    if ($encounter_id && $attacker_id) {
      $cond_check = $this->checkConditionRestrictions($attacker, 'strike');
      if (!$cond_check['can_act']) {
        return ['is_valid' => FALSE, 'reason' => $cond_check['restriction'], 'modifiers' => []];
      }
    }

    // 5. Range validation (hex distance).
    $aq = $attacker['position_q'] ?? NULL;
    $ar = $attacker['position_r'] ?? NULL;
    $tq = $target['position_q'] ?? NULL;
    $tr = $target['position_r'] ?? NULL;

    if ($aq !== NULL && $ar !== NULL && $tq !== NULL && $tr !== NULL) {
      $distance = $this->hexDistance((int) $aq, (int) $ar, (int) $tq, (int) $tr);
      $weapon_type = strtolower($weapon['type'] ?? 'melee');
      $weapon_range = (int) ($weapon['range'] ?? ($weapon_type === 'melee' ? 1 : 6));

      if ($distance > $weapon_range) {
        return [
          'is_valid' => FALSE,
          'reason' => "Target is out of range (distance: {$distance}, range: {$weapon_range}).",
          'modifiers' => [],
        ];
      }

      // Ranged attacks: range increment penalty (-2 per increment beyond first).
      // REQ 2093: Maximum effective range = 6× the range increment; attacks beyond that are invalid.
      if ($weapon_type === 'ranged') {
        $base_range = (int) ($weapon['range_increment'] ?? $weapon_range);
        if ($base_range > 0) {
          $max_effective_range = $base_range * 6;
          if ($distance > $max_effective_range) {
            return [
              'is_valid' => FALSE,
              'reason'   => "Target is beyond maximum effective range (distance: {$distance}, max: {$max_effective_range}).",
              'modifiers' => $modifiers,
            ];
          }
          if ($distance > $base_range) {
            $increments = (int) ceil($distance / $base_range) - 1;
            $range_penalty = $increments * -2;
            $modifiers['range_penalty'] = $range_penalty;
          }
        }
      }

      // Line of effect check (req 2130): attack fails if a solid obstacle
      // intervenes between attacker and target.
      if ($this->losService) {
        $attacker_pos = ['q' => (int) $aq, 'r' => (int) $ar];
        $target_pos   = ['q' => (int) $tq, 'r' => (int) $tr];
        $obstacles = $weapon['terrain_obstacles'] ?? [];
        if (!$this->losService->hasLineOfEffect($attacker_pos, $target_pos, $obstacles)) {
          return [
            'is_valid' => FALSE,
            'reason'   => 'No line of effect to target.',
            'modifiers' => $modifiers,
          ];
        }
      }
    }

    // PF2E reqs 2103: hidden (DC 11) and concealed (DC 5) target flat checks.
    if ($this->calculator) {
      $target_conditions = $this->getActiveConditionTypes((int) $target_id, (int) $encounter_id);
      if (in_array('hidden', $target_conditions, TRUE)) {
        $flat = $this->calculator->rollFlatCheck(11);
        if (!$flat['success']) {
          return [
            'is_valid' => FALSE,
            'reason' => 'Attack misses: flat check failed for hidden target (DC 11).',
            'modifiers' => $modifiers,
            'flat_check' => $flat,
          ];
        }
        $modifiers['hidden_flat_check'] = $flat;
      }
      elseif (in_array('concealed', $target_conditions, TRUE)) {
        $flat = $this->calculator->rollFlatCheck(5);
        if (!$flat['success']) {
          return [
            'is_valid' => FALSE,
            'reason' => 'Attack misses: flat check failed for concealed target (DC 5).',
            'modifiers' => $modifiers,
            'flat_check' => $flat,
          ];
        }
        $modifiers['concealed_flat_check'] = $flat;
      }
    }

    return ['is_valid' => TRUE, 'reason' => '', 'modifiers' => $modifiers];
  }

  /**
   * Validate spell cast.
   *
   * Checks:
   * 1. Caster is not silenced, unconscious, or paralyzed.
   * 2. Spell slots available for the level (cantrips are free).
   * 3. Targets are valid (exist, alive, within range).
   * 4. Action economy can afford the spell's cost.
   *
   * @param array $caster
   *   Caster participant state.
   * @param array|string $spell
   *   Spell data (or spell name string).
   * @param int $spell_level
   *   Spell slot level (0 = cantrip).
   * @param array $targets
   *   Target participant arrays.
   * @param int $encounter_id
   *
   * @return array
   *   ['is_valid' => bool, 'reason' => string]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#spell-validation
   */
  public function validateSpellCast($caster, $spell, $spell_level, array $targets = [], $encounter_id = 0) {
    $caster = (array) $caster;
    $spell_data = is_array($spell) ? $spell : ['name' => $spell];
    $level = (int) $spell_level;

    // 1. Caster alive.
    if (!empty($caster['is_defeated'])) {
      return ['is_valid' => FALSE, 'reason' => 'Caster is defeated.'];
    }

    // 2. Condition restrictions (paralyzed, unconscious blocks all; also check silenced).
    if ($encounter_id) {
      $cond_check = $this->checkConditionRestrictions($caster, 'cast_spell');
      if (!$cond_check['can_act']) {
        return ['is_valid' => FALSE, 'reason' => $cond_check['restriction']];
      }
    }

    // 3. Check stupefied condition (can cause flat check failure, but we don't
    //    block — just warn). Silenced check for verbal components.
    $caster_id = (int) ($caster['id'] ?? 0);
    if ($encounter_id && $caster_id) {
      $conditions = $this->database->select('combat_conditions', 'c')
        ->fields('c', ['condition_type'])
        ->condition('participant_id', $caster_id)
        ->condition('encounter_id', (int) $encounter_id)
        ->isNull('removed_at_round')
        ->execute()
        ->fetchCol();

      // Silenced blocks verbal spells (most spells have verbal components).
      $has_verbal = !isset($spell_data['components']) || in_array('verbal', (array) ($spell_data['components'] ?? []), TRUE);
      if (in_array('silenced', $conditions, TRUE) && $has_verbal) {
        return ['is_valid' => FALSE, 'reason' => 'Cannot cast verbal spells while silenced.'];
      }
    }

    // 4. Spell slot check (cantrips are unlimited).
    if ($level > 0) {
      $entity_ref = $this->decodeEntityRef($caster);
      $slots = (array) ($entity_ref['spell_slots'] ?? []);
      $remaining = (int) ($slots[$level] ?? $slots['level_' . $level] ?? 0);
      if ($remaining <= 0) {
        return ['is_valid' => FALSE, 'reason' => "No spell slots remaining at level {$level}."];
      }
    }

    // 5. Target validation.
    $spell_range = (int) ($spell_data['range'] ?? 6);
    foreach ($targets as $target) {
      $target = (array) $target;
      if (!empty($target['is_defeated'])) {
        $name = $target['name'] ?? 'Target';
        return ['is_valid' => FALSE, 'reason' => "{$name} is already defeated."];
      }

      // Range check if positions available.
      $cq = $caster['position_q'] ?? NULL;
      $cr = $caster['position_r'] ?? NULL;
      $tq = $target['position_q'] ?? NULL;
      $tr = $target['position_r'] ?? NULL;
      if ($cq !== NULL && $cr !== NULL && $tq !== NULL && $tr !== NULL) {
        $distance = $this->hexDistance((int) $cq, (int) $cr, (int) $tq, (int) $tr);
        if ($distance > $spell_range) {
          $name = $target['name'] ?? 'Target';
          return ['is_valid' => FALSE, 'reason' => "{$name} is out of spell range (distance: {$distance}, range: {$spell_range})."];
        }
      }
    }

    return ['is_valid' => TRUE, 'reason' => ''];
  }

  // -----------------------------------------------------------------------
  // Helper methods.
  // -----------------------------------------------------------------------

  /**
   * Hex distance between two axial coordinates.
   */
  protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {
    $dq = abs($q1 - $q2);
    $dr = abs($r1 - $r2);
    $ds = abs((-$q1 - $r1) - (-$q2 - $r2));
    return (int) (($dq + $dr + $ds) / 2);
  }

  /**
   * Decode entity_ref JSON from participant array.
   */
  protected function decodeEntityRef(array $participant): array {
    $ref = $participant['entity_ref'] ?? NULL;
    if ($ref && is_string($ref)) {
      $decoded = json_decode($ref, TRUE);
      return is_array($decoded) ? $decoded : [];
    }
    return is_array($ref) ? $ref : [];
  }

  /**
   * Get active condition type strings for a participant.
   *
   * @param int $participant_id
   * @param int $encounter_id
   *
   * @return string[]
   */
  protected function getActiveConditionTypes(int $participant_id, int $encounter_id): array {
    if (!$participant_id) {
      return [];
    }
    $query = $this->database->select('combat_conditions', 'cc')
      ->fields('cc', ['condition_type'])
      ->condition('participant_id', $participant_id)
      ->condition('is_active', 1);
    if ($encounter_id) {
      $query->condition('encounter_id', $encounter_id);
    }
    $result = $query->execute()->fetchCol();
    return array_map('strtolower', $result ?: []);
  }

}
