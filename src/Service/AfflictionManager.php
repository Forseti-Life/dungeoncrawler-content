<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Affliction Manager service — PF2E poison, disease, curse, virulent (reqs 2135–2144).
 *
 * Manages multi-stage afflictions that progress or regress based on periodic saves.
 *
 * @see /docs/dungeoncrawler/issues/combat-afflictions.md
 */
class AfflictionManager {

  protected $database;
  protected $calculator;
  protected $conditionManager;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, Calculator $calculator, ConditionManager $condition_manager) {
    $this->database = $database;
    $this->calculator = $calculator;
    $this->conditionManager = $condition_manager;
  }

  /**
   * Apply an affliction to a participant from initial exposure.
   *
   * Rolls the initial saving throw and sets the starting stage.
   * Critical success = unaffected (stage 0). Critical failure = start at stage 2.
   * Success = unaffected (stage 0). Failure = stage 1.
   * Onset: if present, effects don't apply until onset has elapsed.
   *
   * Req 2135 (initial save), req 2136 (crit fail = stage 2),
   * req 2137 (onset delay).
   *
   * @param int $participant_id
   *   Combat participant ID.
   * @param array $affliction_def
   *   Affliction definition:
   *   - name (string, required)
   *   - type (string): 'poison', 'disease', 'curse'
   *   - save_type (string): 'Fortitude', 'Reflex', 'Will'
   *   - save_dc (int)
   *   - max_stage (int)
   *   - stages (array of stage definitions, each: ['name'=>string,'effects'=>string])
   *   - onset (string|null): e.g. '1 round', '1 minute'
   *   - max_duration (int|null): rounds
   *   - level (int): affliction level for save modifier context
   *   - is_virulent (bool): virulent flag
   *   - save_ability_mod (int): target's relevant save modifier
   *   - save_proficiency (int): target's proficiency bonus
   * @param int $encounter_id
   *
   * @return array
   *   ['affliction_id' => int|null, 'stage' => int, 'degree' => string,
   *    'applied' => bool, 'save_roll' => array]
   */
  public function applyAffliction(int $participant_id, array $affliction_def, int $encounter_id): array {
    $save_roll = $this->calculator->rollSavingThrow(
      (int) ($affliction_def['save_ability_mod'] ?? 0),
      (int) ($affliction_def['save_proficiency'] ?? 0)
    );
    $degree = $this->calculator->calculateDegreeOfSuccess(
      $save_roll['total'],
      (int) ($affliction_def['save_dc'] ?? 15),
      $save_roll['roll']
    );

    $initial_stage = match ($degree) {
      'critical_success' => 0,
      'success'          => 0,
      'failure'          => 1,
      'critical_failure' => 2,
      default            => 1,
    };

    if ($initial_stage === 0) {
      return [
        'affliction_id' => NULL,
        'stage'         => 0,
        'degree'        => $degree,
        'applied'       => FALSE,
        'save_roll'     => $save_roll,
      ];
    }

    $max_stage = (int) ($affliction_def['max_stage'] ?? 3);
    $initial_stage = min($initial_stage, $max_stage);
    $now = time();

    $affliction_id = $this->database->insert('combat_afflictions')
      ->fields([
        'encounter_id'          => $encounter_id,
        'participant_id'        => $participant_id,
        'affliction_type'       => $affliction_def['type'] ?? 'poison',
        'affliction_name'       => $affliction_def['name'] ?? 'Unknown Affliction',
        'affliction_level'      => (int) ($affliction_def['level'] ?? 1),
        'save_type'             => $affliction_def['save_type'] ?? 'Fortitude',
        'save_dc'               => (int) ($affliction_def['save_dc'] ?? 15),
        'current_stage'         => $initial_stage,
        'max_stage'             => $max_stage,
        'onset'                 => $affliction_def['onset'] ?? NULL,
        'onset_elapsed'         => empty($affliction_def['onset']) ? 1 : 0,
        'max_duration'          => isset($affliction_def['max_duration']) ? (int) $affliction_def['max_duration'] : NULL,
        'duration_elapsed'      => 0,
        'is_virulent'           => !empty($affliction_def['is_virulent']) ? 1 : 0,
        'consecutive_successes' => 0,
        'stages_json'           => json_encode($affliction_def['stages'] ?? []),
        'status'                => 'active',
        'created'               => $now,
        'updated'               => $now,
      ])
      ->execute();

    // Apply conditions from the current stage immediately (unless onset pending).
    if (empty($affliction_def['onset'])) {
      $this->applyStageConditions($participant_id, $affliction_def, $initial_stage, $encounter_id);
    }

    return [
      'affliction_id' => (int) $affliction_id,
      'stage'         => $initial_stage,
      'degree'        => $degree,
      'applied'       => TRUE,
      'save_roll'     => $save_roll,
    ];
  }

  /**
   * Process a periodic saving throw for an active affliction.
   *
   * Called at the end of each stage interval (typically end of turn).
   * Adjusts current_stage per degree of success:
   *   crit_success: −2 (virulent: only −1; requires 2 consecutive non-crit successes to drop 1 stage)
   *   success:      −1 (virulent: track consecutive_successes, only apply at 2nd)
   *   failure:      +1
   *   crit_failure: +2
   *
   * Reqs 2138, 2139, 2141, 2142 (virulent two-consecutive rule).
   *
   * @param int $participant_id
   * @param int $affliction_id
   *   Row ID in combat_afflictions.
   * @param int $encounter_id
   * @param int $save_ability_mod
   *   Target's current save modifier.
   * @param int $save_proficiency
   *
   * @return array
   *   ['new_stage' => int, 'degree' => string, 'ended' => bool,
   *    'consecutive_successes' => int, 'save_roll' => array]
   */
  public function processPeriodicSave(
    int $participant_id,
    int $affliction_id,
    int $encounter_id,
    int $save_ability_mod = 0,
    int $save_proficiency = 0
  ): array {
    $row = $this->loadAffliction($affliction_id);
    if (!$row || $row['status'] !== 'active') {
      return ['ended' => TRUE, 'new_stage' => 0, 'degree' => 'n/a', 'consecutive_successes' => 0, 'save_roll' => []];
    }

    $save_roll = $this->calculator->rollSavingThrow($save_ability_mod, $save_proficiency);
    $degree = $this->calculator->calculateDegreeOfSuccess($save_roll['total'], (int) $row['save_dc'], $save_roll['roll']);

    $current_stage      = (int) $row['current_stage'];
    $max_stage          = (int) $row['max_stage'];
    $is_virulent        = (bool) $row['is_virulent'];
    $consec             = (int) $row['consecutive_successes'];

    // Req 2141/2142: virulent tracking.
    if ($is_virulent && in_array($degree, ['success', 'critical_success'], TRUE)) {
      $consec++;
    }
    else {
      $consec = 0;
    }

    $stage_delta = match ($degree) {
      'critical_success' => $is_virulent ? -1 : -2,
      'success'          => ($is_virulent && $consec < 2) ? 0 : -1,
      'failure'          => 1,
      'critical_failure' => 2,
      default            => 0,
    };

    $new_stage = $current_stage + $stage_delta;

    $ended = FALSE;
    if ($new_stage <= 0) {
      $new_stage = 0;
      $ended = TRUE;
      $this->endAffliction($affliction_id);
    }
    else {
      // Clamp at max (req 2140: above max = repeat max effects, no further advance).
      $new_stage = min($new_stage, $max_stage);
      $this->database->update('combat_afflictions')
        ->fields([
          'current_stage'         => $new_stage,
          'consecutive_successes' => $consec,
          'duration_elapsed'      => (int) $row['duration_elapsed'] + 1,
          'updated'               => time(),
        ])
        ->condition('id', $affliction_id)
        ->execute();

      $stages_def = json_decode($row['stages_json'], TRUE) ?? [];
      $affliction_def_mini = [
        'stages' => $stages_def,
        'name'   => $row['affliction_name'],
        'type'   => $row['affliction_type'],
      ];
      $this->applyStageConditions($participant_id, $affliction_def_mini, $new_stage, $encounter_id);
    }

    return [
      'new_stage'             => $new_stage,
      'degree'                => $degree,
      'ended'                 => $ended,
      'consecutive_successes' => $consec,
      'save_roll'             => $save_roll,
    ];
  }

  /**
   * Handle re-exposure to an affliction while already afflicted.
   *
   * Req 2143: disease/curse re-exposure while afflicted → no change.
   * Req 2144: poison re-exposure fail → +1 stage (crit fail +2); duration unchanged.
   *
   * @param int $participant_id
   * @param int $affliction_id
   *   Existing affliction row ID.
   * @param array $affliction_def
   *   Same shape as applyAffliction $affliction_def.
   * @param string $save_degree
   *   Pre-computed degree of success for the re-exposure save.
   *
   * @return array
   *   ['changed' => bool, 'new_stage' => int, 'reason' => string]
   */
  public function handleReExposure(int $participant_id, int $affliction_id, array $affliction_def, string $save_degree, int $encounter_id = 0): array {
    $type = strtolower($affliction_def['type'] ?? 'poison');

    // Req 2143: disease and curse — no change on re-exposure.
    if (in_array($type, ['disease', 'curse'], TRUE)) {
      return ['changed' => FALSE, 'new_stage' => -1, 'reason' => 'disease/curse re-exposure ignored'];
    }

    // Req 2144: poison — advance stage on failure/crit failure.
    $row = $this->loadAffliction($affliction_id);
    if (!$row || $row['status'] !== 'active') {
      return ['changed' => FALSE, 'new_stage' => -1, 'reason' => 'affliction not active'];
    }

    $delta = match ($save_degree) {
      'failure'          => 1,
      'critical_failure' => 2,
      default            => 0,
    };

    if ($delta === 0) {
      return ['changed' => FALSE, 'new_stage' => (int) $row['current_stage'], 'reason' => 'save succeeded — no stage change'];
    }

    $max_stage = (int) $row['max_stage'];
    $new_stage = min((int) $row['current_stage'] + $delta, $max_stage);

    $this->database->update('combat_afflictions')
      ->fields(['current_stage' => $new_stage, 'updated' => time()])
      ->condition('id', $affliction_id)
      ->execute();

    $stages_def = json_decode($row['stages_json'], TRUE) ?? [];
    $this->applyStageConditions($participant_id, ['stages' => $stages_def], $new_stage, $encounter_id);

    return ['changed' => TRUE, 'new_stage' => $new_stage, 'reason' => 'poison re-exposure advanced stage'];
  }

  /**
   * Mark an affliction as ended and clean up.
   *
   * @param int $affliction_id
   */
  public function endAffliction(int $affliction_id): void {
    $this->database->update('combat_afflictions')
      ->fields(['status' => 'ended', 'updated' => time()])
      ->condition('id', $affliction_id)
      ->execute();
  }

  /**
   * Load affliction row by ID.
   *
   * @param int $affliction_id
   *
   * @return array|null
   */
  public function loadAffliction(int $affliction_id): ?array {
    $row = $this->database->select('combat_afflictions', 'a')
      ->fields('a')
      ->condition('id', $affliction_id)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  /**
   * Get all active afflictions for a participant in an encounter.
   *
   * @param int $participant_id
   * @param int $encounter_id
   *
   * @return array
   */
  public function getActiveAfflictions(int $participant_id, int $encounter_id): array {
    return $this->database->select('combat_afflictions', 'a')
      ->fields('a')
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->condition('status', 'active')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * Apply conditions from a given affliction stage via ConditionManager.
   *
   * Stage definitions in stages_json:
   *   [['name' => 'Stage 1', 'effects' => 'sickened 1, enfeebled 1'], ...]
   * Index 0 = stage 1. Parses comma-separated condition strings.
   *
   * @param int $participant_id
   * @param array $affliction_def
   * @param int $stage
   *   1-based stage number.
   * @param int $encounter_id
   */
  protected function applyStageConditions(int $participant_id, array $affliction_def, int $stage, int $encounter_id): void {
    if ($stage <= 0) {
      return;
    }
    $stages = $affliction_def['stages'] ?? [];
    $stage_idx = $stage - 1;
    if (!isset($stages[$stage_idx])) {
      return;
    }

    $effects_str = $stages[$stage_idx]['effects'] ?? '';
    if (!$effects_str) {
      return;
    }

    // Parse comma-separated effects like "sickened 1, enfeebled 2".
    $parts = array_map('trim', explode(',', $effects_str));
    foreach ($parts as $part) {
      // Match "condition_name optional_value" e.g. "sickened 1", "drained 2", "slowed"
      if (preg_match('/^([a-z_]+)\s*(\d+)?$/i', $part, $m)) {
        $cond_type = strtolower($m[1]);
        $cond_value = isset($m[2]) ? (int) $m[2] : 1;
        $this->conditionManager->applyCondition(
          $participant_id,
          $cond_type,
          $cond_value,
          ['type' => 'affliction', 'remaining' => NULL],
          ['affliction' => $affliction_def['name'] ?? 'affliction'],
          $encounter_id
        );
      }
    }
  }

}
