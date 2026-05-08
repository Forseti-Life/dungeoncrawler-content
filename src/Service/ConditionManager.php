<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Condition Manager service - Manage combat conditions and effects.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ConditionManager)
 * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_conditions)
 */
class ConditionManager {

  protected $database;
  protected $numberGeneration;

  /**
   * Full PF2E condition catalog.
   *
   * Keys: condition slug. Values:
   *   - is_valued: bool — condition has a numeric severity value (e.g. frightened 2)
   *   - max_value: int — highest allowed value (0 for non-valued)
   *   - end_trigger: string — when the condition is removed/reduced
   *       'end_of_turn' = auto-decrements each end-of-turn via tickConditions()
   *       'save'        = removed on a successful save
   *       'action'      = removed by spending an action (e.g. Stand up)
   *       'rest'        = removed only on rest/sleep
   *       'recovery'    = special rule (dying recovery check)
   *       'persistent'  = persists until explicitly removed
   *   - effects: key-value of effect type → base modifier (value multiplied for valued conditions)
   */
  const CONDITIONS = [
    'blinded'      => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'save',        'effects' => ['flat_check_attacks' => TRUE]],
    'clumsy'       => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'end_of_turn', 'effects' => ['dex_checks' => -1, 'ac' => -1]],
    'concealed'    => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => []],
    'confused'     => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'end_of_turn', 'effects' => []],
    'controlled'   => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'save',        'effects' => []],
    'dazzled'      => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'save',        'effects' => []],
    'deafened'     => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'save',        'effects' => []],
    'doomed'       => ['is_valued' => TRUE,  'max_value' => 3, 'end_trigger' => 'persistent',  'effects' => []],
    'drained'      => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'rest',        'effects' => ['max_hp_per_level' => -1]],
    'dying'        => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'recovery',    'effects' => ['cannot_act' => TRUE]],
    'encumbered'   => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => []],
    'enfeebled'    => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'end_of_turn', 'effects' => ['str_checks' => -1, 'melee_attack' => -1]],
    'fascinated'   => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'save',        'effects' => []],
    'fatigued'     => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'rest',        'effects' => ['ac' => -1, 'saves' => -1]],
    'flat_footed'  => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => ['ac' => -2]],
    'fleeing'      => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'end_of_turn', 'effects' => []],
    'frightened'   => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'end_of_turn', 'effects' => ['checks' => -1, 'dcs' => -1]],
    'grabbed'      => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'action',      'effects' => ['cannot_move' => TRUE, 'flat_footed' => TRUE]],
    'hidden'       => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => []],
    'immobilized'  => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => ['cannot_move' => TRUE]],
    'invisible'    => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => []],
    'observed'     => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => []],
    'paralyzed'    => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'save',        'effects' => ['cannot_act' => TRUE, 'flat_footed' => TRUE]],
    'petrified'    => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => ['cannot_act' => TRUE]],
    'prone'        => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'action',      'effects' => ['ac' => -2, 'attack' => -2]],
    'quickened'    => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'end_of_turn', 'effects' => ['extra_action' => 1]],
    'restrained'   => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'action',      'effects' => ['cannot_move' => TRUE, 'flat_footed' => TRUE]],
    'sickened'     => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'save',        'effects' => ['checks' => -1, 'dcs' => -1]],
    'slowed'       => ['is_valued' => TRUE,  'max_value' => 3, 'end_trigger' => 'end_of_turn', 'effects' => []],
    'stunned'      => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'end_of_turn', 'effects' => []],
    'stupefied'    => ['is_valued' => TRUE,  'max_value' => 4, 'end_trigger' => 'end_of_turn', 'effects' => ['spell_dc' => -1, 'spell_attack' => -1]],
    // REQ 2168: unconscious applies −4 status penalty to AC, Perception, Reflex saves.
    'unconscious'  => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'save',        'effects' => ['cannot_act' => TRUE, 'flat_footed' => TRUE, 'blinded' => TRUE, 'status_penalty' => ['ac' => -4, 'perception' => -4, 'reflex_save' => -4]]],
    'undetected'   => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => []],
    'unnoticed'    => ['is_valued' => FALSE, 'max_value' => 0, 'end_trigger' => 'persistent',  'effects' => []],
    'wounded'      => ['is_valued' => TRUE,  'max_value' => 3, 'end_trigger' => 'persistent',  'effects' => []],
  ];

  public function __construct(Connection $database, NumberGenerationService $number_generation = NULL) {
    $this->database = $database;
    $this->numberGeneration = $number_generation;
  }

  /**
   * Apply condition to participant.
   *
   * Validates the condition type against the catalog.
   * For valued conditions already present: updates value (capped at max_value).
   * For non-valued conditions already present: no-op (idempotent).
   *
   * @return int|false Condition row ID on insert/update, FALSE on no-op, or throws on unknown type.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applycondition
   */
  public function applyCondition($participant_id, $condition_type, $value, $duration, $source, $encounter_id) {
    if (!array_key_exists($condition_type, self::CONDITIONS)) {
      throw new \InvalidArgumentException("Unknown condition type: '{$condition_type}'. Must be one of: " . implode(', ', array_keys(self::CONDITIONS)));
    }

    $catalog = self::CONDITIONS[$condition_type];
    $now = time();
    [$duration_type, $duration_remaining] = $this->normalizeDuration($duration);
    $applied_at_round = $this->getCurrentRound($encounter_id);

    // Check for an existing active row.
    $existing = $this->database->select('combat_conditions', 'c')
      ->fields('c', ['id', 'value'])
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->condition('condition_type', $condition_type)
      ->isNull('removed_at_round')
      ->execute()
      ->fetchObject();

    if ($existing) {
      if (!$catalog['is_valued']) {
        // Non-valued: idempotent — already applied, do nothing.
        return FALSE;
      }
      // Valued: update to the higher value, capped at max.
      $new_value = min((int) $catalog['max_value'], (int) $existing->value + (int) $value);
      $this->database->update('combat_conditions')
        ->fields(['value' => $new_value, 'updated' => $now])
        ->condition('id', $existing->id)
        ->execute();
      return (int) $existing->id;
    }

    $insert_value = $catalog['is_valued']
      ? min((int) $catalog['max_value'], max(1, (int) $value))
      : NULL;

    return (int) $this->database->insert('combat_conditions')
      ->fields([
        'participant_id'   => $participant_id,
        'encounter_id'     => $encounter_id,
        'condition_type'   => $condition_type,
        'value'            => $insert_value,
        'duration_type'    => $duration_type,
        'duration_remaining' => $duration_remaining,
        'source'           => is_string($source) ? $source : json_encode($source),
        'applied_at_round' => $applied_at_round,
        'removed_at_round' => NULL,
        'created'          => $now,
        'updated'          => $now,
      ])
      ->execute();
  }

  /**
   * Remove condition (soft delete — sets removed_at_round).
   * Returns FALSE (no-op) if the condition does not exist on this participant.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#removecondition
   */
  public function removeCondition($participant_id, $condition_id, $encounter_id) {
    $now = time();
    $removed_at_round = $this->getCurrentRound($encounter_id);

    $count = $this->database->update('combat_conditions')
      ->fields([
        'removed_at_round' => $removed_at_round,
        'updated' => $now,
      ])
      ->condition('id', $condition_id)
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->isNull('removed_at_round')
      ->execute();

    return $count > 0;
  }

  /**
   * Get all active (not-removed) conditions for a participant.
   *
   * @return array Associative array keyed by condition row ID.
   */
  /**
   * Returns TRUE if the participant has an active instance of $condition_type.
   *
   * @param int $participant_id
   * @param string $condition_type
   * @param int $encounter_id
   */
  public function hasCondition(int $participant_id, string $condition_type, int $encounter_id): bool {
    foreach ($this->getActiveConditions($participant_id, $encounter_id) as $row) {
      if ($row['condition_type'] === $condition_type) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the value of the first active instance of $condition_type, or NULL.
   *
   * @param int $participant_id
   * @param string $condition_type
   * @param int $encounter_id
   *
   * @return int|null
   */
  public function getConditionValue(int $participant_id, string $condition_type, int $encounter_id): ?int {
    foreach ($this->getActiveConditions($participant_id, $encounter_id) as $row) {
      if ($row['condition_type'] === $condition_type) {
        return (int) $row['value'];
      }
    }
    return NULL;
  }

  /**
   * Decrement the value of a valued condition by $amount.
   *
   * If the resulting value reaches 0 or below, the condition is removed.
   * No-op if the participant has no active instance of $condition_type.
   *
   * @param int $participant_id
   * @param string $condition_type
   * @param int $encounter_id
   * @param int $amount  How much to decrement (default 1).
   */
  public function decrementCondition(int $participant_id, string $condition_type, int $encounter_id, int $amount = 1): void {
    $now = time();
    $removed_at_round = $this->getCurrentRound($encounter_id);
    foreach ($this->getActiveConditions($participant_id, $encounter_id) as $row) {
      if ($row['condition_type'] !== $condition_type) {
        continue;
      }
      $new_value = (int) $row['value'] - $amount;
      if ($new_value <= 0) {
        $this->database->update('combat_conditions')
          ->fields(['removed_at_round' => $removed_at_round, 'updated' => $now])
          ->condition('id', $row['id'])
          ->execute();
      }
      else {
        $this->database->update('combat_conditions')
          ->fields(['value' => $new_value, 'updated' => $now])
          ->condition('id', $row['id'])
          ->execute();
      }
      break;
    }
  }

  public function getActiveConditions(int $participant_id, int $encounter_id): array {
    return $this->database->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->isNull('removed_at_round')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * Decrement valued end_of_turn conditions by 1 at end of a participant's turn.
   *
   * Frightened 2 → frightened 1 → removed. Slowed/stunned/clumsy/enfeebled/stupefied
   * and other 'end_of_turn' valued conditions all follow this rule.
   *
   * @param int $participant_id
   * @param int $encounter_id
   *
   * @return array List of ['condition_type' => string, 'old_value' => int, 'new_value' => int|'removed'] for each ticked condition.
   */
  public function tickConditions(int $participant_id, int $encounter_id): array {
    $conditions = $this->getActiveConditions($participant_id, $encounter_id);
    $now = time();
    $removed_at_round = $this->getCurrentRound($encounter_id);
    $ticked = [];

    foreach ($conditions as $row) {
      $type = $row['condition_type'];
      $catalog = self::CONDITIONS[$type] ?? NULL;

      if (!$catalog || !$catalog['is_valued'] || $catalog['end_trigger'] !== 'end_of_turn') {
        continue;
      }

      $old_value = (int) $row['value'];
      $new_value = $old_value - 1;

      if ($new_value <= 0) {
        // Remove the condition entirely.
        $this->database->update('combat_conditions')
          ->fields(['removed_at_round' => $removed_at_round, 'updated' => $now])
          ->condition('id', $row['id'])
          ->execute();
        $ticked[] = ['condition_type' => $type, 'old_value' => $old_value, 'new_value' => 'removed'];
      }
      else {
        $this->database->update('combat_conditions')
          ->fields(['value' => $new_value, 'updated' => $now])
          ->condition('id', $row['id'])
          ->execute();
        $ticked[] = ['condition_type' => $type, 'old_value' => $old_value, 'new_value' => $new_value];
      }
    }

    return $ticked;
  }

  /**
   * Process the dying/recovery rules at start of a dying participant's turn.
   *
   * PF2E rules:
   *   Roll 1d20 flat check against DC 10 (no modifiers).
   *   1          = critical failure → dying value +2
   *   2–9        = failure          → dying value +1
   *   10–19      = success          → dying value -1
   *   20         = critical success → dying value -2 (0 or below → conscious, remove dying)
   *   dying 4    = dead (regardless of doomed modifier — simplified)
   *
   * @param int $participant_id
   * @param int $encounter_id
   *
   * @return array [
   *   'roll'         => int (1–20),
   *   'outcome'      => 'critical_failure'|'failure'|'success'|'critical_success',
   *   'dying_before' => int,
   *   'dying_after'  => int,
   *   'dead'         => bool,
   *   'conscious'    => bool,
   * ]
   */
  public function processDying(int $participant_id, int $encounter_id): array {
    $conditions = $this->getActiveConditions($participant_id, $encounter_id);
    $dying_row = NULL;
    foreach ($conditions as $row) {
      if ($row['condition_type'] === 'dying') {
        $dying_row = $row;
        break;
      }
    }

    if (!$dying_row) {
      return ['error' => 'Participant does not have the dying condition.'];
    }

    $dying_before = (int) $dying_row['value'];
    $roll = mt_rand(1, 20);

    // REQ 2158: Recovery flat check DC = 10 + dying value (not hardcoded 10).
    $dc = 10 + $dying_before;

    // Standard PF2e degree-of-success for flat checks:
    // nat 20 OR roll ≥ dc+10 = crit success
    // roll ≥ dc = success
    // nat 1 OR roll ≤ dc-10 = crit failure
    // otherwise = failure
    if ($roll === 20 || $roll >= $dc + 10) {
      $outcome = 'critical_success';
      $delta = -2;
    }
    elseif ($roll >= $dc) {
      $outcome = 'success';
      $delta = -1;
    }
    elseif ($roll === 1 || $roll <= $dc - 10) {
      $outcome = 'critical_failure';
      $delta = +2;
    }
    else {
      $outcome = 'failure';
      $delta = +1;
    }

    $dying_after = max(0, $dying_before + $delta);
    $now = time();
    $current_round = $this->getCurrentRound($encounter_id);

    // REQ 2165/2166: doomed reduces the dying death threshold (dying 4 − doomed = death).
    $doomed_value = 0;
    foreach ($conditions as $row) {
      if ($row['condition_type'] === 'doomed') {
        $doomed_value = max($doomed_value, (int) ($row['value'] ?? 0));
      }
    }
    $death_threshold = max(1, 4 - $doomed_value);

    if ($dying_after <= 0) {
      // Participant stabilizes — remove the dying condition.
      $this->database->update('combat_conditions')
        ->fields(['removed_at_round' => $current_round, 'updated' => $now])
        ->condition('id', $dying_row['id'])
        ->execute();
      return [
        'roll' => $roll, 'outcome' => $outcome,
        'dying_before' => $dying_before, 'dying_after' => 0,
        'dead' => FALSE, 'conscious' => TRUE,
      ];
    }

    if ($dying_after >= $death_threshold) {
      // Participant dies — remove dying, mark participant dead.
      $this->database->update('combat_conditions')
        ->fields(['removed_at_round' => $current_round, 'updated' => $now])
        ->condition('id', $dying_row['id'])
        ->execute();
      // Mark participant as removed from encounter (dead).
      $this->database->update('combat_participants')
        ->fields(['removed_at_round' => $current_round, 'status' => 'dead'])
        ->condition('id', $participant_id)
        ->execute();
      return [
        'roll' => $roll, 'outcome' => $outcome,
        'dying_before' => $dying_before, 'dying_after' => $dying_after,
        'dead' => TRUE, 'conscious' => FALSE,
      ];
    }

    // Update dying value.
    $this->database->update('combat_conditions')
      ->fields(['value' => $dying_after, 'updated' => $now])
      ->condition('id', $dying_row['id'])
      ->execute();

    return [
      'roll' => $roll, 'outcome' => $outcome,
      'dying_before' => $dying_before, 'dying_after' => $dying_after,
      'dead' => FALSE, 'conscious' => FALSE,
    ];
  }

  /**
   * Apply condition effects.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyconditioneffects
   */
  public function applyConditionEffects($participant, $condition_type, $value) {
    $value = (int) ($value ?? 1);
    $effects = [];

    switch ($condition_type) {
      case 'frightened':
        $penalty = -abs($value);
        $effects['checks'] = $penalty; // All checks
        $effects['dcs'] = $penalty; // All DCs including AC
        break;

      case 'clumsy':
        $penalty = -abs($value);
        $effects['dex_checks'] = $penalty;
        $effects['ac'] = $penalty;
        break;

      case 'enfeebled':
        $penalty = -abs($value);
        $effects['str_checks'] = $penalty;
        $effects['melee_attack'] = $penalty;
        break;

      case 'stupefied':
        $penalty = -abs($value);
        $effects['spell_dc'] = $penalty;
        $effects['spell_attack'] = $penalty;
        break;

      case 'flat_footed':
        $effects['ac'] = -2;
        break;

      case 'prone':
        $effects['ac'] = -2;
        $effects['attack'] = -2;
        break;

      default:
        break;
    }

    return $effects;
  }

  /**
   * Get condition modifiers.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#getconditionmodifiers
   */
  public function getConditionModifiers($participant_id, $stat_type, $encounter_id) {
    $conditions = $this->database->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->condition('removed_at_round', NULL, 'IS')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $modifier = 0;
    foreach ($conditions as $condition) {
      $effects = $this->applyConditionEffects($condition, $condition['condition_type'], $condition['value']);
      foreach ($effects as $effect_type => $value) {
        if ($effect_type === $stat_type || $effect_type === 'all') {
          $modifier += $value;
        }

        if ($effect_type === 'checks' && in_array($stat_type, ['attack', 'melee_attack', 'ranged_attack', 'skill', 'perception', 'saving_throw'], TRUE)) {
          $modifier += $value;
        }

        if ($effect_type === 'dcs' && in_array($stat_type, ['ac', 'spell_dc', 'save_dc'], TRUE)) {
          $modifier += $value;
        }

        if ($stat_type === 'ac' && $effect_type === 'dex_checks') {
          $modifier += $value;
        }
      }
    }

    return $modifier;
  }

  /**
   * Process persistent damage.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#processpersistentdamage
   */
  public function processPersistentDamage($participant_id, $encounter_id) {
    // Query active persistent damage conditions.
    $conditions = $this->database->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('participant_id', (int) $participant_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->condition('removed_at_round', NULL, 'IS')
      ->condition('condition_type', 'persistent_damage%', 'LIKE')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    if (empty($conditions)) {
      return [];
    }

    // Load current participant HP.
    $participant = $this->database->select('combat_participants', 'p')
      ->fields('p', ['id', 'hp', 'max_hp'])
      ->condition('id', (int) $participant_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$participant) {
      return [];
    }

    $current_round = $this->getCurrentRound((int) $encounter_id);
    $results = [];

    foreach ($conditions as $cond_id => $condition) {
      $damage_value = (int) ($condition['value'] ?? 0);
      $damage_expression = $condition['source'] ?? NULL;

      // Roll damage if source contains a dice expression, otherwise use value.
      if ($damage_expression && $this->numberGeneration && preg_match('/\d+d\d+/', $damage_expression)) {
        // Extract dice expression from source like "fire:2d6" or "2d6 fire".
        preg_match('/(\d+d\d+(?:\+\d+)?)/', $damage_expression, $dice_match);
        if (!empty($dice_match[1])) {
          $roll = $this->numberGeneration->rollExpression($dice_match[1]);
          $damage_value = (int) ($roll['total'] ?? $damage_value);
        }
      }

      // Apply damage directly via DB (HPManager creates circular dependency).
      $new_hp = max(0, ((int) $participant['hp']) - $damage_value);
      $this->database->update('combat_participants')
        ->fields(['hp' => $new_hp])
        ->condition('id', (int) $participant_id)
        ->execute();

      // Log the damage.
      try {
        $this->database->insert('combat_actions')
          ->fields([
            'encounter_id' => (int) $encounter_id,
            'participant_id' => (int) $participant_id,
            'action_type' => 'persistent_damage',
            'target_id' => NULL,
            'payload' => json_encode([
              'condition_id' => (int) $cond_id,
              'condition_type' => $condition['condition_type'],
            ]),
            'result' => json_encode([
              'damage' => $damage_value,
              'hp_before' => (int) $participant['hp'],
              'hp_after' => $new_hp,
            ]),
            'created' => time(),
          ])
          ->execute();
      }
      catch (\Throwable $e) {
        // Non-critical: log failure doesn't block processing.
      }

      // Roll flat check DC 15 to end the persistent damage.
      $flat_check_roll = $this->numberGeneration
        ? $this->numberGeneration->rollPathfinderDie(20)
        : mt_rand(1, 20);
      $ended = $flat_check_roll >= 15;

      if ($ended) {
        $this->database->update('combat_conditions')
          ->fields(['removed_at_round' => $current_round])
          ->condition('id', (int) $cond_id)
          ->execute();
      }

      $results[] = [
        'condition_id' => (int) $cond_id,
        'condition_type' => $condition['condition_type'],
        'damage' => $damage_value,
        'hp_before' => (int) $participant['hp'],
        'hp_after' => $new_hp,
        'flat_check_roll' => $flat_check_roll,
        'ended' => $ended,
      ];

      // Update running HP for subsequent iterations.
      $participant['hp'] = $new_hp;
    }

    return $results;
  }

  /**
   * Process dying condition (compatibility wrapper).
   *
   * @deprecated Use processDying() directly.
   */
  public function processDyingCondition($participant_id, $constitution_modifier, $encounter_id) {
    return $this->processDying((int) $participant_id, (int) $encounter_id);
  }

  protected function getCurrentRound(int $encounter_id): int {
    $round = $this->database->select('combat_encounters', 'e')
      ->fields('e', ['current_round'])
      ->condition('id', $encounter_id)
      ->execute()
      ->fetchField();

    return $round !== FALSE ? (int) $round : 0;
  }

  protected function normalizeDuration($duration): array {
    if (is_array($duration)) {
      $type = $duration['type'] ?? ($duration['duration_type'] ?? NULL);
      $remaining = $duration['remaining'] ?? ($duration['duration'] ?? $duration['value'] ?? NULL);
      return [$type, $remaining !== NULL ? (int) $remaining : NULL];
    }

    if (is_string($duration)) {
      return [$duration, NULL];
    }

    if (is_numeric($duration)) {
      return [NULL, (int) $duration];
    }

    return [NULL, NULL];
  }

  /**
   * Get fortune/misfortune flags for a participant (req 2105-2107).
   *
   * Returns whether active conditions on the participant grant fortune or
   * misfortune. Callers pass these flags to rollFlatCheck() / rollSkillCheck()
   * / rollSavingThrow() options.
   *
   * @param int|string $participant_id
   * @param int|string $encounter_id
   *
   * @return array
   *   ['has_fortune' => bool, 'has_misfortune' => bool]
   */
  public function getFortuneFlags($participant_id, $encounter_id): array {
    $conditions = $this->database->select('combat_conditions', 'c')
      ->fields('c', ['condition_type'])
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->condition('is_active', 1)
      ->execute()
      ->fetchCol();

    $types = array_map('strtolower', $conditions ?: []);
    return [
      'has_fortune'    => in_array('fortune', $types, TRUE),
      'has_misfortune' => in_array('misfortune', $types, TRUE),
    ];
  }

}
