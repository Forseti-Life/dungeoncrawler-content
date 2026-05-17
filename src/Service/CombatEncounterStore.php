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
    if ($participants === []) {
      throw new \InvalidArgumentException('Cannot create encounter without participants.');
    }

    $txn = $this->database->startTransaction();
    $now = time();

    try {
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
        $participant = $this->normalizeParticipantRow($participant);
        $this->database->insert('combat_participants')
          ->fields([
            'encounter_id' => $encounter_id,
            'entity_id' => $participant['entity_id'] ?? ($participant['id'] ?? 0),
            'entity_ref' => $participant['entity_ref'] ?? NULL,
            'name' => $participant['name'] ?? 'Entity',
            'team' => $participant['team'] ?? NULL,
            'status' => $participant['status'] ?? 'active',
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
    catch (\Throwable $e) {
      $txn->rollBack();
      throw $e;
    }
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
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    usort($participants, function (array $a, array $b): int {
      $init_diff = (int) ($b['initiative'] ?? 0) - (int) ($a['initiative'] ?? 0);
      if ($init_diff !== 0) {
        return $init_diff;
      }
      $perc_diff = $this->resolvePerceptionModifier($b) - $this->resolvePerceptionModifier($a);
      if ($perc_diff !== 0) {
        return $perc_diff;
      }
      return (int) ($a['id'] ?? 0) - (int) ($b['id'] ?? 0);
    });

    $encounter['participants'] = $participants;
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

    try {
      // Remove existing participants for this encounter.
      $this->database->delete('combat_participants')
        ->condition('encounter_id', $encounter_id)
        ->execute();

      $now = time();
      foreach ($participants as $participant) {
        $participant = $this->normalizeParticipantRow($participant);
        $this->database->insert('combat_participants')
          ->fields([
            'encounter_id' => $encounter_id,
            'entity_id' => $participant['entity_id'] ?? ($participant['id'] ?? 0),
            'entity_ref' => $participant['entity_ref'] ?? NULL,
            'name' => $participant['name'] ?? 'Entity',
            'team' => $participant['team'] ?? NULL,
            'status' => $participant['status'] ?? 'active',
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
        ->fields([
          'updated' => time(),
        ])
        ->condition('id', $encounter_id)
        ->execute();

      return TRUE;
    }
    catch (\Throwable $e) {
      $txn->rollBack();
      throw $e;
    }
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

    if (array_key_exists('entity_ref', $fields)) {
      $fields['entity_ref'] = $this->normalizeEntityRef($fields['entity_ref']);
    }

    $fields['updated'] = time();
    $count = $this->database->update('combat_participants')
      ->fields($fields)
      ->condition('id', $participant_id)
      ->execute();

    return $count > 0;
  }

  /**
   * Normalize participant rows before persistence.
   */
  protected function normalizeParticipantRow(array $participant): array {
    if (array_key_exists('entity_ref', $participant)) {
      $participant['entity_ref'] = $this->normalizeEntityRef($participant['entity_ref']);
    }
    $participant['status'] = (string) ($participant['status'] ?? 'active');
    return $participant;
  }

  /**
   * Normalize entity_ref payloads to the stored string format.
   */
  protected function normalizeEntityRef($entity_ref): ?string {
    if ($entity_ref === NULL || $entity_ref === '') {
      return NULL;
    }
    if (is_array($entity_ref)) {
      return json_encode($entity_ref, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    if (is_object($entity_ref)) {
      return json_encode($entity_ref, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return (string) $entity_ref;
  }

  /**
   * Resolve the stored perception modifier used for initiative tie-breaks.
   */
  protected function resolvePerceptionModifier(array $participant): int {
    $entity_ref = $participant['entity_ref'] ?? NULL;
    if (!is_string($entity_ref) || $entity_ref === '') {
      return 0;
    }

    $decoded = json_decode($entity_ref, TRUE);
    if (!is_array($decoded)) {
      return 0;
    }

    return (int) ($decoded['perception_modifier'] ?? 0);
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
