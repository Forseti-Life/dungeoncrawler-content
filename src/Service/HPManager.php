<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * HP Manager service - Manage hit point changes and dying/wounded conditions.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (HPManager)
 */
class HPManager {

  protected $database;
  protected $conditionManager;

  public function __construct(Connection $database, ConditionManager $condition_manager) {
    $this->database = $database;
    $this->conditionManager = $condition_manager;
  }

  /**
   * Apply damage.
   *
   * PF2e: Temp HP absorbs damage first. Remaining damage reduces current HP.
   * If HP drops to 0 or below, dying condition is applied.
   * REQ 2173: If damage >= 2×max_hp in one hit, instant death (massive damage).
   * REQ 2156: Nonlethal damage reduces to 0 → unconscious, not dying.
   * REQ 2172: death_effect flag → instant death, bypasses dying track.
   * REQ 2153: When dropped to 0 HP, initiative shifts to after attacker.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applydamage
   */
  public function applyDamage($participant_id, $damage, $damage_type, $source, $encounter_id, bool $is_nonlethal = FALSE, bool $is_critical = FALSE) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['final_damage' => 0, 'new_hp' => 0, 'new_status' => 'not_found'];
    }

    $now = time();
    $base_hp = (int) ($participant['hp'] ?? 0);
    $max_hp = (int) ($participant['max_hp'] ?? 0);
    $temp_hp = (int) ($participant['temp_hp'] ?? 0);
    $damage = max(0, (int) $damage);
    // DEF-2114: Capture original incoming damage BEFORE resistances so the
    // min-1 rule (PF2E req 2114) fires correctly when resistance fully absorbs
    // damage that started > 0.
    $original_damage = $damage;

    // PF2e: Apply damage type resistances/weaknesses if present on participant.
    $entity_data = !empty($participant['entity_data']) ? json_decode($participant['entity_data'], TRUE) : [];
    $resistances = $entity_data['resistances'] ?? [];
    $weaknesses  = $entity_data['weaknesses'] ?? [];
    $damage_type_str = strtolower((string) $damage_type);
    foreach ($resistances as $r) {
      if (strtolower((string) ($r['type'] ?? '')) === $damage_type_str) {
        $damage = max(0, $damage - (int) ($r['value'] ?? 0));
        break;
      }
    }
    // PF2E req 2114: after resistances, original damage > 0 means final must be >= 1.
    foreach ($weaknesses as $w) {
      if (strtolower((string) ($w['type'] ?? '')) === $damage_type_str) {
        $damage += (int) ($w['value'] ?? 0);
        break;
      }
    }
    if ($original_damage > 0 && $damage < 1) {
      $damage = 1;
    }

    // PF2e: Temp HP absorbs damage first.
    $temp_absorbed = 0;
    if ($temp_hp > 0 && $damage > 0) {
      $temp_absorbed = min($temp_hp, $damage);
      $remaining_damage = $damage - $temp_absorbed;
      $new_temp_hp = $temp_hp - $temp_absorbed;
    }
    else {
      $remaining_damage = $damage;
      $new_temp_hp = $temp_hp;
    }

    // DEF-2151: Clamp to 0 — HP must never be stored as negative.
    $new_hp = max(0, $base_hp - $remaining_damage);
    $is_defeated = $new_hp <= 0 ? 1 : (int) ($participant['is_defeated'] ?? 0);

    $txn = $this->database->startTransaction();

    $this->database->update('combat_participants')
      ->fields([
        'hp' => $new_hp,
        'temp_hp' => $new_temp_hp,
        'is_defeated' => $is_defeated,
        'updated' => $now,
      ])
      ->condition('id', $participant_id)
      ->execute();

    $this->database->insert('combat_damage_log')
      ->fields([
        'encounter_id' => $encounter_id,
        'participant_id' => $participant_id,
        'amount' => $damage,
        'damage_type' => $damage_type,
        'source' => is_string($source) ? $source : json_encode($source),
        'hp_before' => $base_hp,
        'hp_after' => $new_hp,
        'created' => $now,
      ])
      ->execute();

    $death_reason = NULL;
    $is_dead = FALSE;

    if ($is_defeated) {
      // REQ 2173: Massive damage check — single-hit damage >= 2×max_hp = instant death.
      if ($remaining_damage >= 2 * $max_hp) {
        $this->database->update('combat_participants')
          ->fields(['status' => 'dead'])
          ->condition('id', $participant_id)
          ->execute();
        $is_dead = TRUE;
        $death_reason = 'massive_damage';
      }
      // REQ 2172: death_effect source flag → instant death, bypasses dying track.
      elseif (!empty($source['death_effect'])) {
        $this->database->update('combat_participants')
          ->fields(['status' => 'dead'])
          ->condition('id', $participant_id)
          ->execute();
        $is_dead = TRUE;
        $death_reason = 'death_effect';
      }
      // REQ 2156: Nonlethal damage at 0 HP → unconscious, not dying.
      elseif ($is_nonlethal) {
        $this->conditionManager->applyCondition($participant_id, 'unconscious', 1, ['type' => 'encounter', 'remaining' => NULL], $source, $encounter_id);
      }
      else {
        // DEF-2154/2155: Route through applyDyingCondition() so wounded is
        // added and crits correctly apply dying 2 instead of dying 1.
        $this->applyDyingCondition($participant_id, 1, $encounter_id, $is_critical);
      }
    }

    // REQ 2170: Receiving damage wakes an unconscious character with HP > 0.
    if (!$is_defeated && $base_hp > 0 && $this->conditionManager->hasCondition($participant_id, 'unconscious', $encounter_id)) {
      $this->removeUnconsciousCondition($participant_id, $encounter_id);
    }

    // GAP-2178: If entity has regeneration_bypassed_by and the damage type matches,
    // flag regeneration as bypassed this turn so startTurn() skips regen healing.
    if ($damage > 0 && !empty($participant['entity_ref'])) {
      $entity_ref_data = json_decode($participant['entity_ref'], TRUE);
      if (!empty($entity_ref_data['regeneration_bypassed_by']) && !empty($entity_ref_data['regeneration'])) {
        $bypass_types = is_array($entity_ref_data['regeneration_bypassed_by'])
          ? $entity_ref_data['regeneration_bypassed_by']
          : [$entity_ref_data['regeneration_bypassed_by']];
        if (in_array($damage_type_str, array_map('strtolower', $bypass_types), TRUE)) {
          $entity_ref_data['regeneration_bypassed'] = TRUE;
          $this->database->update('combat_participants')
            ->fields(['entity_ref' => json_encode($entity_ref_data), 'updated' => $now])
            ->condition('id', $participant_id)
            ->execute();
        }
      }
    }

    return [
      'final_damage' => $damage,
      'hp_damage' => $remaining_damage,
      'temp_hp_used' => $temp_absorbed,
      'new_hp' => $new_hp,
      'new_temp_hp' => $new_temp_hp,
      'new_status' => $is_dead ? 'dead' : ($is_defeated ? 'defeated' : 'active'),
      'death_reason' => $death_reason,
    ];
  }

  /**
   * Apply healing.
   *
   * REQ 2164: Removes wounded if new HP = max HP (full heal).
   * REQ 2170: Wakes unconscious character if they have HP > 0.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyhealing
   */
  public function applyHealing($participant_id, $healing, $source, $encounter_id) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['healing_applied' => 0, 'new_hp' => 0];
    }

    $now = time();
    $base_hp = (int) ($participant['hp'] ?? 0);
    $max_hp = (int) ($participant['max_hp'] ?? $base_hp);
    $healing = max(0, (int) $healing);
    $new_hp = $max_hp > 0 ? min($base_hp + $healing, $max_hp) : $base_hp + $healing;

    $this->database->update('combat_participants')
      ->fields([
        'hp' => $new_hp,
        'is_defeated' => $new_hp > 0 ? 0 : (int) ($participant['is_defeated'] ?? 0),
        'updated' => $now,
      ])
      ->condition('id', $participant_id)
      ->execute();

    // REQ 2164: Full heal removes wounded condition.
    if ($new_hp >= $max_hp) {
      $this->removeConditionByType($participant_id, 'wounded', $encounter_id);
    }

    // REQ 2170: Healing wakes an unconscious character (HP > 0 after heal).
    if ($new_hp > 0 && $this->conditionManager->hasCondition($participant_id, 'unconscious', $encounter_id)) {
      $this->removeUnconsciousCondition($participant_id, $encounter_id);
    }

    return [
      'healing_applied' => $new_hp - $base_hp,
      'new_hp' => $new_hp,
    ];
  }

  /**
   * Apply temporary HP.
   *
   * PF2e: Temp HP does not stack. If the participant already has temp HP,
   * keep whichever value is higher (new or existing). Temp HP cannot be
   * restored by healing.
   *
   * @param int $participant_id
   *   The combat participant ID.
   * @param int $temp_hp
   *   The temp HP amount to grant.
   * @param string|array $source
   *   Source of temp HP (e.g. "False Life spell").
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return array
   *   Keys: temp_hp_before, temp_hp_after, applied (bool).
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applytemporaryhp
   */
  public function applyTemporaryHP($participant_id, $temp_hp, $source, $encounter_id) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['temp_hp_before' => 0, 'temp_hp_after' => 0, 'applied' => FALSE];
    }

    $current_temp = (int) ($participant['temp_hp'] ?? 0);
    $new_temp = max(0, (int) $temp_hp);

    // PF2e: Temp HP doesn't stack — take the higher value.
    if ($new_temp <= $current_temp) {
      return [
        'temp_hp_before' => $current_temp,
        'temp_hp_after' => $current_temp,
        'applied' => FALSE,
      ];
    }

    $this->database->update('combat_participants')
      ->fields([
        'temp_hp' => $new_temp,
        'updated' => time(),
      ])
      ->condition('id', $participant_id)
      ->execute();

    return [
      'temp_hp_before' => $current_temp,
      'temp_hp_after' => $new_temp,
      'applied' => TRUE,
    ];
  }

  /**
   * Check death condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#checkdeathcondition
   */
  public function checkDeathCondition($participant_id, $encounter_id, ?int $hp_override = NULL, ?int $max_hp_override = NULL) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['is_dead' => FALSE, 'death_reason' => ''];
    }

    $hp = $hp_override ?? (int) ($participant['hp'] ?? 0);
    $max_hp = $max_hp_override ?? (int) ($participant['max_hp'] ?? 0);

    return $this->evaluateDeath($hp, $max_hp);
  }

  /**
   * Apply dying condition.
   *
   * REQ 2154: On a critical hit, apply dying 2 (before wounded adjustment).
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applydyingcondition
   */
  public function applyDyingCondition($participant_id, $dying_value, $encounter_id, bool $is_critical = FALSE) {
    // REQ 2154: Critical hits apply dying 2 instead of dying 1.
    if ($is_critical && $dying_value < 2) {
      $dying_value = 2;
    }

    // PF2E: wounded condition increases dying value at start.
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);
    $wounded_value = 0;
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'wounded') {
        $wounded_value = max($wounded_value, (int) ($cond['value'] ?? 0));
      }
    }

    $effective_dying = $dying_value + $wounded_value;

    // GAP-2166: doomed reduces the dying death threshold; if effective_dying already
    // meets or exceeds the threshold, the character dies immediately without entering
    // the dying track (REQ 2165/2166).
    $doomed_value = 0;
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'doomed') {
        $doomed_value = max($doomed_value, (int) ($cond['value'] ?? 0));
      }
    }
    $death_threshold = max(1, 4 - $doomed_value);
    if ($effective_dying >= $death_threshold) {
      $now = time();
      $this->database->update('combat_participants')
        ->fields(['status' => 'dead', 'updated' => $now])
        ->condition('id', $participant_id)
        ->execute();
      return ['instant_death' => TRUE, 'doomed' => $doomed_value, 'effective_dying' => $effective_dying, 'threshold' => $death_threshold];
    }

    $this->conditionManager->applyCondition($participant_id, 'dying', $effective_dying, ['type' => 'encounter', 'remaining' => NULL], 'dying_condition', $encounter_id);
    $this->conditionManager->applyCondition($participant_id, 'unconscious', 0, ['type' => 'encounter', 'remaining' => NULL], 'dying_condition', $encounter_id);
    $this->conditionManager->applyCondition($participant_id, 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'dying_condition', $encounter_id);

    return TRUE;
  }

  /**
   * Stabilize character.
   *
   * REQ 2160: Character stays at 0 HP (unconscious, NOT healed to 1).
   * REQ 2161/2162: Each time dying is removed, wounded increases by 1 (additive).
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#stabilizecharacter
   */
  public function stabilizeCharacter($participant_id, $encounter_id) {
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);

    // Remove dying condition.
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'dying') {
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
      }
    }

    // REQ 2161/2162: wounded increases by 1 each time dying is removed.
    $current_wounded = 0;
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'wounded') {
        $current_wounded = max($current_wounded, (int) ($cond['value'] ?? 0));
      }
    }
    $new_wounded = $current_wounded + 1;
    $this->conditionManager->applyCondition($participant_id, 'wounded', $new_wounded, ['type' => 'persistent', 'remaining' => NULL], 'stabilize', $encounter_id);

    // REQ 2160: Character stays at 0 HP — do NOT set to 1. Remains unconscious.
    $this->database->update('combat_participants')
      ->fields(['is_defeated' => 1, 'updated' => time()])
      ->condition('id', $participant_id)
      ->execute();

    return TRUE;
  }

  protected function loadParticipant($participant_id): ?array {
    $record = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', $participant_id)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  /**
   * REQ 2173: evaluateDeath — kept for checkDeathCondition compatibility.
   * The main massive-damage check now lives in applyDamage().
   */
  protected function evaluateDeath(int $hp, int $max_hp): array {
    // Legacy: direct HP threshold check (used by checkDeathCondition standalone).
    if ($max_hp > 0 && $hp <= -1 * $max_hp) {
      return ['is_dead' => TRUE, 'death_reason' => 'hp_threshold'];
    }
    return ['is_dead' => FALSE, 'death_reason' => ''];
  }

  /**
   * REQ 2171: Heroic Recovery — spend a Hero Point to avoid dying.
   *
   * - Removes dying condition entirely (no wounded gain).
   * - HP restored = Con modifier (if positive, else 0).
   */
  public function heroicRecovery($participant_id, $encounter_id, int $con_modifier = 0): array {
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);

    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'dying') {
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
      }
      if ($cond['condition_type'] === 'unconscious') {
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
      }
    }

    $hp_gained = max(0, $con_modifier);
    if ($hp_gained > 0) {
      $this->applyHealing($participant_id, $hp_gained, 'hero_point_recovery', $encounter_id);
    }

    return [
      'heroic_recovery' => TRUE,
      'dying_removed' => TRUE,
      'wounded_added' => FALSE,
      'hp_gained' => $hp_gained,
    ];
  }

  /**
   * REQ 2281: Spend ALL Hero Points — stabilize without gaining wounded condition.
   *
   * Distinct from REQ 2171 heroicRecovery() (spend 1 HP): this spends every hero point the
   * character has, removes the dying condition, does NOT add wounded, and keeps HP at 0.
   * REQ 2282: Also usable for familiars/animal companions (caller must pass their participant_id).
   */
  public function heroicRecoveryAllPoints($participant_id, $encounter_id): array {
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);

    $dying_removed = FALSE;
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'dying') {
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
        $dying_removed = TRUE;
      }
      if ($cond['condition_type'] === 'unconscious') {
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
      }
    }

    // Ensure character stays at 0 HP (stabilized, not killed).
    $participant = $this->loadParticipant($participant_id);
    if ($participant && (int) ($participant['hp'] ?? 0) < 0) {
      $this->database->update('combat_participants')
        ->fields(['hp' => 0, 'updated' => time()])
        ->condition('id', $participant_id)
        ->execute();
    }

    return [
      'heroic_recovery_all_points' => TRUE,
      'dying_removed'              => $dying_removed,
      'wounded_added'              => FALSE,
      'hp'                         => 0,
    ];
  }

  /**
   * REQ 2169: Natural recovery — unconscious at 0 HP wakes after 10-min rest.
   * Grants 1 HP and removes unconscious condition.
   */
  public function naturalRecovery($participant_id, $encounter_id): array {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['error' => 'Participant not found'];
    }

    $hp = (int) ($participant['hp'] ?? 0);
    if ($hp > 0) {
      return ['error' => 'Character is not at 0 HP — natural recovery only applies to 0-HP unconscious characters'];
    }

    // Grant 1 HP.
    $this->database->update('combat_participants')
      ->fields(['hp' => 1, 'is_defeated' => 0, 'updated' => time()])
      ->condition('id', $participant_id)
      ->execute();

    $this->removeUnconsciousCondition($participant_id, $encounter_id);

    return ['natural_recovery' => TRUE, 'hp' => 1];
  }

  /**
   * REQ 2170: Wake on interact/loud noise — remove unconscious from HP>0 character.
   */
  public function wakeOnInteract($participant_id, $encounter_id): array {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['error' => 'Participant not found'];
    }
    if ((int) ($participant['hp'] ?? 0) <= 0) {
      return ['woken' => FALSE, 'reason' => 'Cannot wake — character at 0 HP'];
    }
    if (!$this->conditionManager->hasCondition($participant_id, 'unconscious', $encounter_id)) {
      return ['woken' => FALSE, 'reason' => 'Not unconscious'];
    }

    $this->removeUnconsciousCondition($participant_id, $encounter_id);
    return ['woken' => TRUE];
  }

  /**
   * Remove all active unconscious conditions for a participant.
   */
  protected function removeUnconsciousCondition(int $participant_id, int $encounter_id): void {
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'unconscious') {
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
      }
    }
  }

  /**
   * Remove all active instances of a condition type for a participant.
   */
  protected function removeConditionByType(int $participant_id, string $condition_type, int $encounter_id): void {
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);
    foreach ($active as $cond) {
      if ($cond['condition_type'] === $condition_type) {
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
      }
    }
  }

  /**
   * Apply fall damage to a participant.
   *
   * REQ 2243: damage = floor(feet / 2) bludgeoning; max 1500 ft = 750 damage.
   * REQ 2244: Soft surface (water/snow): treat fall as 20 ft shorter (30 ft if diving).
   * REQ 2245: REQ handled externally (Reflex DC 15 for landed-on creature).
   * REQ 2246: Land prone on any fall damage.
   *
   * @param int $participant_id
   *   DB participant ID.
   * @param int $feet
   *   Distance fallen in feet.
   * @param int $encounter_id
   *   Encounter ID for condition application.
   * @param bool $soft_surface
   *   TRUE if landing on water, snow, or similar.
   * @param bool $is_dive
   *   TRUE if creature dove intentionally (reduces by 30 ft instead of 20).
   *
   * @return array
   *   ['damage' => int, 'land_prone' => bool, 'hp_result' => array]
   */
  public function applyFallDamage(int $participant_id, int $feet, int $encounter_id = 0, bool $soft_surface = FALSE, bool $is_dive = FALSE): array {
    if ($soft_surface) {
      $reduction = $is_dive ? 30 : 20;
      $feet = max(0, $feet - $reduction);
    }
    $feet = min($feet, 1500);
    $damage = (int) floor($feet / 2);

    if ($damage <= 0) {
      return ['damage' => 0, 'land_prone' => FALSE, 'hp_result' => NULL];
    }

    $hp_result = $this->applyDamage($participant_id, $damage, 'bludgeoning', ['source' => 'fall'], $encounter_id);

    // REQ 2246: Land prone on any fall damage.
    if ($encounter_id) {
      $this->conditionManager->applyCondition($participant_id, 'prone', 1, 'persistent', 'fall', $encounter_id);
    }

    return ['damage' => $damage, 'land_prone' => TRUE, 'hp_result' => $hp_result];
  }

}
