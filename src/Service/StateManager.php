<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * State Manager service - Manages combat state transitions and persistence.
 *
 * Wraps CombatEncounterStore for higher-level state operations: valid
 * transitions, snapshots for undo/recovery, initiative ordering, and
 * per-participant state queries.
 *
 * @see /docs/dungeoncrawler/issues/combat-state-machine.md
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (StateManager)
 */
class StateManager {

  /**
   * Valid encounter status values.
   */
  const STATUSES = ['pending', 'active', 'paused', 'ended'];

  /**
   * Allowed state transitions: from → [to, ...].
   */
  const TRANSITIONS = [
    'pending' => ['active', 'ended'],
    'active'  => ['paused', 'ended'],
    'paused'  => ['active', 'ended'],
    'ended'   => [],
  ];

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  public function __construct(Connection $database, CacheBackendInterface $cache) {
    $this->database = $database;
    $this->cache = $cache;
  }

  // -----------------------------------------------------------------------
  // State transitions.
  // -----------------------------------------------------------------------

  /**
   * Transition combat state with validation.
   *
   * @param int $encounter_id
   *   Encounter row ID.
   * @param string $new_state
   *   Target status (pending, active, paused, ended).
   * @param string $reason
   *   Human-readable reason for the transition.
   *
   * @return array
   *   ['success' => bool, 'new_state' => string, 'error' => string|null]
   *
   * @see /docs/dungeoncrawler/issues/combat-state-machine.md
   */
  public function transitionState($encounter_id, $new_state, $reason = '') {
    $current = $this->getCurrentState((int) $encounter_id);
    if (empty($current)) {
      return ['success' => FALSE, 'new_state' => $new_state, 'error' => "Encounter {$encounter_id} not found."];
    }

    $current_status = $current['status'] ?? 'pending';
    $allowed = self::TRANSITIONS[$current_status] ?? [];

    if (!in_array($new_state, $allowed, TRUE)) {
      return [
        'success' => FALSE,
        'new_state' => $new_state,
        'error' => "Invalid transition: '{$current_status}' → '{$new_state}'. Allowed: " . implode(', ', $allowed),
      ];
    }

    $now = time();
    $this->database->update('combat_encounters')
      ->fields(['status' => $new_state, 'updated' => $now])
      ->condition('id', (int) $encounter_id)
      ->execute();

    // Invalidate cache for this encounter.
    $this->invalidateCache((int) $encounter_id);

    return ['success' => TRUE, 'new_state' => $new_state, 'error' => NULL];
  }

  // -----------------------------------------------------------------------
  // State queries.
  // -----------------------------------------------------------------------

  /**
   * Get current state (encounter + participants).
   *
   * Uses cache layer for fast repeated reads within the same round.
   *
   * @param int $encounter_id
   *
   * @return array
   *   Full encounter state with 'status', 'current_round', 'turn_index',
   *   'participants', etc. Empty array if not found.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#getcurrentstate
   */
  public function getCurrentState($encounter_id) {
    $cid = "combat_state:{$encounter_id}";
    $cached = $this->cache->get($cid);
    if ($cached) {
      return $cached->data;
    }

    $encounter = $this->database->select('combat_encounters', 'e')
      ->fields('e')
      ->condition('id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$encounter) {
      return [];
    }

    $participants = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('encounter_id', (int) $encounter_id)
      ->orderBy('initiative', 'DESC')
      ->orderBy('initiative_roll', 'DESC')
      ->orderBy('name', 'ASC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $encounter['participants'] = array_values($participants);
    $encounter['encounter_id'] = $encounter['id'];

    // Cache for 60 seconds (one round is typically longer).
    $this->cache->set($cid, $encounter, time() + 60);

    return $encounter;
  }

  // -----------------------------------------------------------------------
  // Snapshots.
  // -----------------------------------------------------------------------

  /**
   * Save state snapshot for recovery/undo.
   *
   * Captures complete encounter + participant + condition state as JSON
   * in the combat_state_snapshots table.
   *
   * @param int $encounter_id
   * @param int $round
   *   Current round number.
   * @param int $turn_sequence
   *   Current turn index within the round.
   *
   * @return int
   *   Snapshot row ID.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#savestatensnapshot
   */
  public function saveStateSnapshot($encounter_id, $round, $turn_sequence) {
    $state = $this->getCurrentState((int) $encounter_id);
    if (empty($state)) {
      return 0;
    }

    // Capture conditions for each participant.
    $conditions = [];
    foreach ($state['participants'] as $p) {
      $pid = (int) $p['id'];
      $rows = $this->database->select('combat_conditions', 'c')
        ->fields('c')
        ->condition('participant_id', $pid)
        ->condition('encounter_id', (int) $encounter_id)
        ->isNull('removed_at_round')
        ->execute()
        ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
      $conditions[$pid] = array_values($rows);
    }

    $snapshot_data = [
      'encounter' => $state,
      'conditions' => $conditions,
      'round' => (int) $round,
      'turn_sequence' => (int) $turn_sequence,
      'timestamp' => time(),
    ];

    $now = time();

    // Try the dedicated snapshot table; fall back to encounter_data column.
    try {
      $snapshot_id = (int) $this->database->insert('combat_state_snapshots')
        ->fields([
          'encounter_id' => (int) $encounter_id,
          'round' => (int) $round,
          'turn_sequence' => (int) $turn_sequence,
          'state_data' => json_encode($snapshot_data),
          'created' => $now,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      // Table may not exist yet — store as JSON in cache as fallback.
      $cid = "combat_snapshot:{$encounter_id}:{$round}:{$turn_sequence}";
      $this->cache->set($cid, $snapshot_data, time() + 86400);
      $snapshot_id = crc32($cid);
    }

    return $snapshot_id;
  }

  /**
   * Restore state snapshot.
   *
   * Restores encounter state, participant HP/status, and conditions
   * from a previously saved snapshot.
   *
   * @param int $encounter_id
   * @param int $snapshot_id
   *   Snapshot row ID or cache key hash.
   *
   * @return array
   *   Restored state, or ['error' => '...'] on failure.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#restorestatesnapshot
   */
  public function restoreStateSnapshot($encounter_id, $snapshot_id) {
    $snapshot_data = NULL;

    // Try loading from snapshot table.
    try {
      $row = $this->database->select('combat_state_snapshots', 's')
        ->fields('s', ['state_data'])
        ->condition('id', (int) $snapshot_id)
        ->condition('encounter_id', (int) $encounter_id)
        ->execute()
        ->fetchField();

      if ($row) {
        $snapshot_data = json_decode($row, TRUE);
      }
    }
    catch (\Exception $e) {
      // Table doesn't exist; ignore.
    }

    if (!$snapshot_data) {
      return ['error' => "Snapshot {$snapshot_id} not found for encounter {$encounter_id}."];
    }

    $state = $snapshot_data['encounter'] ?? [];
    if (empty($state)) {
      return ['error' => 'Invalid snapshot data.'];
    }

    $now = time();

    // Restore encounter fields.
    $this->database->update('combat_encounters')
      ->fields([
        'status' => $state['status'] ?? 'active',
        'current_round' => (int) ($state['current_round'] ?? 1),
        'turn_index' => (int) ($state['turn_index'] ?? 0),
        'updated' => $now,
      ])
      ->condition('id', (int) $encounter_id)
      ->execute();

    // Restore participant states.
    foreach ($state['participants'] as $p) {
      $pid = (int) $p['id'];
      $this->database->update('combat_participants')
        ->fields([
          'hp' => (int) ($p['hp'] ?? 0),
          'max_hp' => (int) ($p['max_hp'] ?? 0),
          'ac' => $p['ac'] ?? NULL,
          'actions_remaining' => (int) ($p['actions_remaining'] ?? 3),
          'attacks_this_turn' => (int) ($p['attacks_this_turn'] ?? 0),
          'reaction_available' => (int) ($p['reaction_available'] ?? 1),
          'is_defeated' => (int) ($p['is_defeated'] ?? 0),
          'initiative' => (int) ($p['initiative'] ?? 0),
          'updated' => $now,
        ])
        ->condition('id', $pid)
        ->execute();
    }

    // Restore conditions: soft-delete all current, re-insert from snapshot.
    $conditions = $snapshot_data['conditions'] ?? [];
    foreach ($conditions as $pid => $cond_rows) {
      // Soft-delete all active conditions for this participant.
      $this->database->update('combat_conditions')
        ->fields(['removed_at_round' => $state['current_round'] ?? 0, 'updated' => $now])
        ->condition('participant_id', (int) $pid)
        ->condition('encounter_id', (int) $encounter_id)
        ->isNull('removed_at_round')
        ->execute();

      // Re-insert snapshot conditions.
      foreach ($cond_rows as $cond) {
        $this->database->insert('combat_conditions')
          ->fields([
            'participant_id' => (int) $pid,
            'encounter_id' => (int) $encounter_id,
            'condition_type' => $cond['condition_type'],
            'value' => $cond['value'] ?? NULL,
            'duration_type' => $cond['duration_type'] ?? NULL,
            'duration_remaining' => $cond['duration_remaining'] ?? NULL,
            'source' => $cond['source'] ?? NULL,
            'applied_at_round' => $cond['applied_at_round'] ?? 0,
            'removed_at_round' => NULL,
            'created' => $now,
            'updated' => $now,
          ])
          ->execute();
      }
    }

    // Invalidate cache.
    $this->invalidateCache((int) $encounter_id);

    return $this->getCurrentState((int) $encounter_id);
  }

  // -----------------------------------------------------------------------
  // Initiative & turn queries.
  // -----------------------------------------------------------------------

  /**
   * Get initiative order (sorted participants).
   *
   * @param int $encounter_id
   *
   * @return array
   *   Participants sorted by initiative descending.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#getinitiativeorder
   */
  public function getInitiativeOrder($encounter_id) {
    $state = $this->getCurrentState((int) $encounter_id);
    return $state['participants'] ?? [];
  }

  /**
   * Get current turn participant.
   *
   * @param int $encounter_id
   *
   * @return array
   *   Current participant array with stats, or empty if not found.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#getcurrentturnparticipant
   */
  public function getCurrentTurnParticipant($encounter_id) {
    $state = $this->getCurrentState((int) $encounter_id);
    if (empty($state['participants'])) {
      return [];
    }

    $turn_index = (int) ($state['turn_index'] ?? 0);
    $participants = $state['participants'];

    if ($turn_index >= count($participants)) {
      return [];
    }

    $participant = $participants[$turn_index];

    // Enrich with active conditions.
    $pid = (int) $participant['id'];
    $conditions = $this->database->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('participant_id', $pid)
      ->condition('encounter_id', (int) $encounter_id)
      ->isNull('removed_at_round')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $participant['conditions'] = array_values($conditions);
    return $participant;
  }

  /**
   * Get participant state enriched with conditions and calculated values.
   *
   * @param int $participant_id
   * @param int $encounter_id
   *
   * @return array
   *   Participant row + 'conditions' key with active conditions.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#getparticipantstate
   */
  public function getParticipantState($participant_id, $encounter_id) {
    $participant = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', (int) $participant_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$participant) {
      return [];
    }

    $conditions = $this->database->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('participant_id', (int) $participant_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->isNull('removed_at_round')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $participant['conditions'] = array_values($conditions);

    // Compute derived values.
    $max_hp = (int) ($participant['max_hp'] ?? 0);
    $hp = (int) ($participant['hp'] ?? 0);
    $participant['hp_percent'] = $max_hp > 0 ? round(($hp / $max_hp) * 100, 1) : 0;
    $participant['is_bloodied'] = $max_hp > 0 && $hp <= ($max_hp / 2);

    return $participant;
  }

  // -----------------------------------------------------------------------
  // Cache management.
  // -----------------------------------------------------------------------

  /**
   * Invalidate cached state for an encounter.
   *
   * @param int $encounter_id
   */
  public function invalidateCache(int $encounter_id): void {
    $this->cache->delete("combat_state:{$encounter_id}");
  }

}
