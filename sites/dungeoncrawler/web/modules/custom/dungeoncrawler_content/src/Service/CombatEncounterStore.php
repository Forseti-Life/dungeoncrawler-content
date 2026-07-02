<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Data access layer for combat encounters and participants.
 *
 * Storage-backed implementation outline; logic intentionally minimal.
 */
class CombatEncounterStore {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Create a new encounter and insert participants.
   *
   * @param int|null $campaign_id
   * @param string|null $room_id
   * @param array $participants
   *   Array of participant rows keyed by field names.
   *
   * @return int Encounter ID.
   */
  public function createEncounter(?int $campaign_id, ?string $room_id, array $participants, ?string $map_id = NULL): int {
    $txn = $this->database->startTransaction();
    $now = time();

    $encounter_id = (int) $this->database->insert('combat_encounters')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'map_id' => $map_id,
        'status' => 'active',
        'current_round' => 1,
        'turn_index' => 0,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    foreach ($participants as $participant) {
      $this->database->insert('combat_participants')
        ->fields([
          'encounter_id' => $encounter_id,
          'entity_id' => $participant['entity_id'] ?? ($participant['id'] ?? 0),
          'entity_ref' => $participant['entity_ref'] ?? NULL,
          'name' => $participant['name'] ?? 'Entity',
          'team' => $participant['team'] ?? NULL,
          'initiative' => (int) ($participant['initiative'] ?? 0),
          'initiative_roll' => $participant['initiative_roll'] ?? NULL,
          'ac' => $participant['ac'] ?? NULL,
          'hp' => $participant['hp'] ?? NULL,
          'max_hp' => $participant['max_hp'] ?? NULL,
          'actions_remaining' => $participant['actions_remaining'] ?? 3,
          'attacks_this_turn' => $participant['attacks_this_turn'] ?? 0,
          'reaction_available' => isset($participant['reaction_available']) ? (int) $participant['reaction_available'] : 1,
          'position_q' => $participant['position_q'] ?? NULL,
          'position_r' => $participant['position_r'] ?? NULL,
          'is_defeated' => !empty($participant['is_defeated']) ? 1 : 0,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }

    return $encounter_id;
  }

  /**
   * Load encounter with participants.
   *
   * @param int $encounter_id
   * @return array|null
   */
  public function loadEncounter(int $encounter_id): ?array {
    $encounter = $this->database->select('combat_encounters', 'e')
      ->fields('e')
      ->condition('id', $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$encounter) {
      return NULL;
    }

    $participants = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('encounter_id', $encounter_id)
      ->orderBy('initiative', 'DESC')
      ->orderBy('initiative_roll', 'DESC')
      ->orderBy('name', 'ASC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $encounter['participants'] = array_values($participants);
    $encounter['encounter_id'] = $encounter['id'];
    return $encounter;
  }

  /**
   * Update encounter core state (round/turn/status).
   *
   * @param int $encounter_id
   * @param array $fields
   * @return bool
   */
  public function updateEncounter(int $encounter_id, array $fields): bool {
    if (empty($fields)) {
      return TRUE;
    }

    $fields['updated'] = time();
    $count = $this->database->update('combat_encounters')
      ->fields($fields)
      ->condition('id', $encounter_id)
      ->execute();

    return $count > 0;
  }

  /**
   * Replace participants list (e.g., after initiative reorder).
   *
   * @param int $encounter_id
   * @param array $participants
   * @return bool
   */
  public function saveParticipants(int $encounter_id, array $participants): bool {
    $txn = $this->database->startTransaction();

    // Remove existing participants for this encounter.
    $this->database->delete('combat_participants')
      ->condition('encounter_id', $encounter_id)
      ->execute();

    $now = time();
    foreach ($participants as $participant) {
      $this->database->insert('combat_participants')
        ->fields([
          'encounter_id' => $encounter_id,
          'entity_id' => $participant['entity_id'] ?? ($participant['id'] ?? 0),
          'entity_ref' => $participant['entity_ref'] ?? NULL,
          'name' => $participant['name'] ?? 'Entity',
          'team' => $participant['team'] ?? NULL,
          'initiative' => (int) ($participant['initiative'] ?? 0),
          'initiative_roll' => $participant['initiative_roll'] ?? NULL,
          'ac' => $participant['ac'] ?? NULL,
          'hp' => $participant['hp'] ?? NULL,
          'max_hp' => $participant['max_hp'] ?? NULL,
          'actions_remaining' => $participant['actions_remaining'] ?? 3,
          'attacks_this_turn' => $participant['attacks_this_turn'] ?? 0,
          'reaction_available' => isset($participant['reaction_available']) ? (int) $participant['reaction_available'] : 1,
          'position_q' => $participant['position_q'] ?? NULL,
          'position_r' => $participant['position_r'] ?? NULL,
          'is_defeated' => !empty($participant['is_defeated']) ? 1 : 0,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }

    // Bump encounter updated to signal a new version of state.
    $this->database->update('combat_encounters')
      ->fields(['updated' => time()])
      ->condition('id', $encounter_id)
      ->execute();

    return TRUE;
  }

  /**
   * Persist participant HP/defeated changes.
   *
   * @param int $participant_id
   * @param array $fields
   * @return bool
   */
  public function updateParticipant(int $participant_id, array $fields): bool {
    if (empty($fields)) {
      return TRUE;
    }

    $fields['updated'] = time();
    $count = $this->database->update('combat_participants')
      ->fields($fields)
      ->condition('id', $participant_id)
      ->execute();

    return $count > 0;
  }

  /**
   * Insert a condition row.
   *
   * @param array $condition
   * @return int condition_id
   */
  public function addCondition(array $condition): int {
    $now = time();
    return (int) $this->database->insert('combat_conditions')
      ->fields([
        'participant_id' => $condition['participant_id'],
        'encounter_id' => $condition['encounter_id'],
        'condition_type' => $condition['condition_type'],
        'value' => $condition['value'] ?? NULL,
        'duration_type' => $condition['duration_type'] ?? NULL,
        'duration_remaining' => $condition['duration_remaining'] ?? NULL,
        'source' => $condition['source'] ?? NULL,
        'applied_at_round' => $condition['applied_at_round'] ?? 0,
        'removed_at_round' => $condition['removed_at_round'] ?? NULL,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
  }

  /**
   * Remove/mark condition as ended.
   *
   * @param int $condition_id
   * @param int|null $removed_at_round
   * @return bool
   */
  public function removeCondition(int $condition_id, ?int $removed_at_round = NULL): bool {
    $count = $this->database->update('combat_conditions')
      ->fields([
        'removed_at_round' => $removed_at_round,
        'updated' => time(),
      ])
      ->condition('id', $condition_id)
      ->execute();

    return $count > 0;
  }

  /**
   * List active conditions for a participant.
   *
   * @param int $participant_id
   * @return array
   */
  public function listActiveConditions(int $participant_id): array {
    return $this->database->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('participant_id', $participant_id)
      ->isNull('removed_at_round')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Log an action entry.
   *
   * @param array $action_row
   * @return int action_id
   */
  public function logAction(array $action_row): int {
    $now = time();
    return (int) $this->database->insert('combat_actions')
      ->fields([
        'encounter_id' => $action_row['encounter_id'],
        'participant_id' => $action_row['participant_id'],
        'action_type' => $action_row['action_type'],
        'target_id' => $action_row['target_id'] ?? NULL,
        'payload' => $action_row['payload'] ?? NULL,
        'result' => $action_row['result'] ?? NULL,
        'created' => $now,
      ])
      ->execute();
  }

  /**
   * Log a damage event.
   *
   * @param array $damage_row
   * @return int damage_id
   */
  public function logDamage(array $damage_row): int {
    $now = time();
    return (int) $this->database->insert('combat_damage_log')
      ->fields([
        'encounter_id' => $damage_row['encounter_id'],
        'participant_id' => $damage_row['participant_id'],
        'amount' => $damage_row['amount'],
        'damage_type' => $damage_row['damage_type'] ?? NULL,
        'source' => $damage_row['source'] ?? NULL,
        'hp_before' => $damage_row['hp_before'] ?? NULL,
        'hp_after' => $damage_row['hp_after'] ?? NULL,
        'created' => $now,
      ])
      ->execute();
  }

}
