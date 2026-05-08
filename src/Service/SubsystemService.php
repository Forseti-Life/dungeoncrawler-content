<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Subsystem session management for PF2e GMG structured non-combat challenges.
 *
 * Supported types: chase, influence, research, infiltration, reputation,
 * vehicle, hexploration, duel.
 *
 * Each subsystem session stores a flexible progress_state JSON document whose
 * schema is governed by the subsystem type. Domain rules for each type are
 * encoded in the takeTurn() dispatcher and checkWinCondition() /
 * checkFailCondition() methods.
 *
 * Implements: dc-gmg-subsystems
 */
class SubsystemService {

  const VALID_TYPES = [
    'chase',
    'influence',
    'research',
    'infiltration',
    'reputation',
    'vehicle',
    'hexploration',
    'duel',
  ];

  const VALID_OUTCOMES = ['win', 'fail', 'abandoned'];

  // Chase obstacle distance per side — chase ends when either side reaches 0
  // (catcher) or max (escapee).
  const CHASE_DEFAULT_STAGES = 5;

  // Influence: critical success multiplier.
  const INFLUENCE_CRIT_MULTIPLIER = 2;

  // Research: default DC tier unlock thresholds.
  const RESEARCH_TIERS = [
    'basic'    => 5,
    'standard' => 15,
    'advanced' => 30,
    'rare'     => 50,
  ];

  // Hexploration: discovery status values.
  const HEX_STATUS = ['unknown', 'revealed', 'explored'];

  // Duel types.
  const DUEL_TYPES = ['combat', 'skill', 'honor'];

  public function __construct(
    private readonly Connection      $database,
    private readonly AccountInterface $currentUser
  ) {}

  // ── Session CRUD ─────────────────────────────────────────────────────────

  /**
   * Initiate a new subsystem session.
   *
   * @param int $campaign_id
   * @param string $subsystem_type
   *   One of VALID_TYPES.
   * @param array $config
   *   Type-specific configuration merged into initial progress_state.
   * @return array
   */
  public function initiate(int $campaign_id, string $subsystem_type, array $config = []): array {
    if (!in_array($subsystem_type, self::VALID_TYPES, TRUE)) {
      throw new BadRequestHttpException("Invalid subsystem_type '$subsystem_type'. Valid: " . implode(', ', self::VALID_TYPES));
    }
    $now   = time();
    $state = $this->buildInitialState($subsystem_type, $config);
    $id    = $this->database->insert('dc_subsystem_session')
      ->fields([
        'campaign_id'     => $campaign_id,
        'subsystem_type'  => $subsystem_type,
        'status'          => 'active',
        'progress_state'  => json_encode($state),
        'initiated_by'    => (int) $this->currentUser->id(),
        'created'         => $now,
        'updated'         => $now,
      ])
      ->execute();
    return $this->getSession((int) $id);
  }

  /**
   * Get a single subsystem session.
   */
  public function getSession(int $session_id): array {
    $row = $this->database->select('dc_subsystem_session', 's')
      ->fields('s')
      ->condition('id', $session_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      throw new NotFoundHttpException("Subsystem session $session_id not found.");
    }
    $row['progress_state'] = json_decode($row['progress_state'], TRUE) ?? [];
    return $row;
  }

  /**
   * List subsystem sessions for a campaign, optionally filtered by type/status.
   */
  public function listSessions(int $campaign_id, ?string $type = NULL, ?string $status = NULL): array {
    $q = $this->database->select('dc_subsystem_session', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('created', 'DESC');
    if ($type !== NULL) {
      if (!in_array($type, self::VALID_TYPES, TRUE)) {
        throw new BadRequestHttpException("Invalid subsystem_type '$type'.");
      }
      $q->condition('subsystem_type', $type);
    }
    if ($status !== NULL) {
      $q->condition('status', $status);
    }
    $rows = $q->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      $row['progress_state'] = json_decode($row['progress_state'], TRUE) ?? [];
    }
    return array_values($rows);
  }

  /**
   * Return only active sessions (status = 'active') for a campaign.
   */
  public function getActiveSessions(int $campaign_id): array {
    return $this->listSessions($campaign_id, NULL, 'active');
  }

  /**
   * Resolve a session with a final outcome.
   */
  public function resolveSession(int $session_id, string $outcome): array {
    if (!in_array($outcome, self::VALID_OUTCOMES, TRUE)) {
      throw new BadRequestHttpException("Invalid outcome '$outcome'. Valid: " . implode(', ', self::VALID_OUTCOMES));
    }
    $session = $this->getSession($session_id);
    if ($session['status'] !== 'active') {
      throw new BadRequestHttpException("Session $session_id is already {$session['status']}.");
    }
    $state = $session['progress_state'];
    $state['outcome'] = $outcome;
    $this->database->update('dc_subsystem_session')
      ->fields([
        'status'         => 'resolved',
        'progress_state' => json_encode($state),
        'updated'        => time(),
      ])
      ->condition('id', $session_id)
      ->execute();
    return $this->getSession($session_id);
  }

  // ── Turn Processing ───────────────────────────────────────────────────────

  /**
   * Process a turn action within an active subsystem session.
   *
   * @param int $session_id
   * @param int $character_id
   *   The acting character.
   * @param array $action_data
   *   Type-specific action payload. Required keys vary by subsystem type.
   * @return array
   *   Updated session state including win/fail status appended.
   */
  public function takeTurn(int $session_id, int $character_id, array $action_data): array {
    $session = $this->getSession($session_id);
    if ($session['status'] !== 'active') {
      throw new BadRequestHttpException("Session $session_id is not active.");
    }
    $type  = $session['subsystem_type'];
    $state = $session['progress_state'];

    switch ($type) {
      case 'chase':
        $state = $this->processChaseTurn($state, $character_id, $action_data);
        break;
      case 'influence':
        $state = $this->processInfluenceTurn($state, $character_id, $action_data);
        break;
      case 'research':
        $state = $this->processResearchTurn($state, $character_id, $action_data);
        break;
      case 'infiltration':
        $state = $this->processInfiltrationTurn($state, $character_id, $action_data);
        break;
      case 'reputation':
        $state = $this->processReputationTurn($state, $character_id, $action_data);
        break;
      case 'vehicle':
        $state = $this->processVehicleTurn($state, $character_id, $action_data);
        break;
      case 'hexploration':
        $state = $this->processHexplorationTurn($state, $character_id, $action_data);
        break;
      case 'duel':
        $state = $this->processDuelTurn($state, $character_id, $action_data);
        break;
    }

    $state = $this->appendTurnLog($state, $character_id, $action_data);
    $this->database->update('dc_subsystem_session')
      ->fields([
        'progress_state' => json_encode($state),
        'updated'        => time(),
      ])
      ->condition('id', $session_id)
      ->execute();

    $updated = $this->getSession($session_id);
    $updated['win_condition_met']  = $this->checkWinCondition($session_id);
    $updated['fail_condition_met'] = $this->checkFailCondition($session_id);
    return $updated;
  }

  // ── Win/Fail Conditions ───────────────────────────────────────────────────

  /**
   * Check whether win conditions are met for a session.
   */
  public function checkWinCondition(int $session_id): bool {
    $session = $this->getSession($session_id);
    $state   = $session['progress_state'];
    switch ($session['subsystem_type']) {
      case 'chase':
        // Catcher side reaches stage 0 OR escapee side reaches max_stages.
        $catcher  = (int) ($state['sides']['catcher']['position'] ?? 999);
        $escapee  = (int) ($state['sides']['escapee']['position'] ?? 0);
        $max      = (int) ($state['max_stages'] ?? self::CHASE_DEFAULT_STAGES);
        return $catcher <= 0 || $escapee >= $max;
      case 'influence':
        // All NPCs in the influence pool have reached their threshold.
        foreach ($state['npcs'] ?? [] as $npc) {
          if (($npc['points'] ?? 0) < ($npc['threshold'] ?? PHP_INT_MAX)) {
            return FALSE;
          }
        }
        return !empty($state['npcs']);
      case 'research':
        $tier_req = (int) ($state['target_tier_points'] ?? self::RESEARCH_TIERS['advanced']);
        return ($state['total_points'] ?? 0) >= $tier_req;
      case 'infiltration':
        // Win = successfully reached the objective before detection.
        return (bool) ($state['objective_reached'] ?? FALSE);
      case 'reputation':
        // Win = faction reputation reached target threshold.
        return ($state['reputation'] ?? 0) >= ($state['win_threshold'] ?? PHP_INT_MAX);
      case 'vehicle':
        // Win = destination reached without vehicle destruction.
        return (bool) ($state['destination_reached'] ?? FALSE) && ($state['vehicle_hp'] ?? 1) > 0;
      case 'hexploration':
        // Win = target hex(es) fully explored.
        $targets = $state['target_hexes'] ?? [];
        if (empty($targets)) {
          return FALSE;
        }
        foreach ($targets as $hex_id) {
          $status = $state['hexes'][$hex_id]['status'] ?? 'unknown';
          if ($status !== 'explored') {
            return FALSE;
          }
        }
        return TRUE;
      case 'duel':
        $type = $state['duel_type'] ?? 'combat';
        if ($type === 'combat') {
          return ($state['opponent_hp'] ?? 1) <= 0;
        }
        // Skill/honor duel: win threshold met.
        return ($state['pc_score'] ?? 0) >= ($state['win_score'] ?? PHP_INT_MAX);
    }
    return FALSE;
  }

  /**
   * Check whether fail conditions are met for a session.
   */
  public function checkFailCondition(int $session_id): bool {
    $session = $this->getSession($session_id);
    $state   = $session['progress_state'];
    switch ($session['subsystem_type']) {
      case 'chase':
        // Escapee side reaches 0 (caught) or catcher side reaches max (escaped — fail for catcher).
        $catcher = (int) ($state['sides']['catcher']['position'] ?? 0);
        $escapee = (int) ($state['sides']['escapee']['position'] ?? 0);
        $max     = (int) ($state['max_stages'] ?? self::CHASE_DEFAULT_STAGES);
        // Fail for whichever side the session is tracking as "party":
        $party_side = $state['party_side'] ?? 'catcher';
        if ($party_side === 'catcher') {
          return $escapee >= $max; // party failed to catch
        }
        return $catcher <= 0; // party failed to escape
      case 'influence':
        // Fail = any NPC exceeded their resistance limit (became hostile).
        foreach ($state['npcs'] ?? [] as $npc) {
          if (!empty($npc['hostile'])) {
            return TRUE;
          }
        }
        return FALSE;
      case 'research':
        return ($state['rounds_used'] ?? 0) >= ($state['round_limit'] ?? PHP_INT_MAX)
          && ($state['total_points'] ?? 0) < ($state['target_tier_points'] ?? PHP_INT_MAX);
      case 'infiltration':
        // Fail = awareness score hit the terminal threshold.
        return ($state['awareness'] ?? 0) >= ($state['detection_threshold'] ?? PHP_INT_MAX);
      case 'reputation':
        return ($state['reputation'] ?? 0) <= ($state['fail_threshold'] ?? PHP_INT_MIN);
      case 'vehicle':
        return ($state['vehicle_hp'] ?? 1) <= 0;
      case 'hexploration':
        // Fail = party HP depleted from hazards (if tracked).
        return (bool) ($state['party_incapacitated'] ?? FALSE);
      case 'duel':
        $type = $state['duel_type'] ?? 'combat';
        if ($type === 'combat') {
          return ($state['pc_hp'] ?? 1) <= 0;
        }
        return ($state['pc_score'] ?? 0) <= ($state['fail_score'] ?? PHP_INT_MIN);
    }
    return FALSE;
  }

  // ── Domain: Chase ─────────────────────────────────────────────────────────

  private function processChaseTurn(array $state, int $char_id, array $action): array {
    $side        = $action['side'] ?? 'catcher'; // 'catcher' or 'escapee'
    $check_result = $action['check_result'] ?? 'failure'; // success|failure|critical_success|critical_failure
    $stage_key   = $side === 'catcher' ? 'catcher' : 'escapee';

    if (!isset($state['sides'])) {
      $state['sides'] = [
        'catcher' => ['position' => (int) ($state['max_stages'] ?? self::CHASE_DEFAULT_STAGES)],
        'escapee' => ['position' => 0],
      ];
    }

    $advance = match ($check_result) {
      'critical_success' => 2,
      'success'          => 1,
      'failure'          => 0,
      'critical_failure' => -1, // complication
      default            => 0,
    };

    if ($side === 'catcher') {
      $state['sides']['catcher']['position'] = max(0,
        $state['sides']['catcher']['position'] - $advance);
    }
    else {
      $max = $state['max_stages'] ?? self::CHASE_DEFAULT_STAGES;
      $state['sides']['escapee']['position'] = min((int) $max,
        $state['sides']['escapee']['position'] + $advance);
    }

    if ($check_result === 'critical_failure') {
      $state['complications'][] = [
        'character_id' => $char_id,
        'round'        => ($state['round'] ?? 1),
        'description'  => $action['complication_note'] ?? 'Automatic complication triggered.',
      ];
    }

    $state['round'] = ($state['round'] ?? 0) + 1;
    return $state;
  }

  // ── Domain: Influence ─────────────────────────────────────────────────────

  private function processInfluenceTurn(array $state, int $char_id, array $action): array {
    $npc_id      = $action['npc_id'] ?? NULL;
    $skill_used  = $action['skill'] ?? '';
    $check_result = $action['check_result'] ?? 'failure';
    $discovery    = $action['discovery'] ?? FALSE; // spent turn discovering preferred skills

    if ($discovery) {
      // Discovery action: no influence gain, just logs the attempt.
      $state['discovery_log'][] = [
        'character_id' => $char_id,
        'npc_id'       => $npc_id,
        'skill'        => $skill_used,
      ];
      return $state;
    }

    if ($npc_id === NULL) {
      return $state;
    }

    // Find the NPC in the pool.
    foreach ($state['npcs'] as &$npc) {
      if ((int) $npc['npc_id'] !== (int) $npc_id) {
        continue;
      }

      $preferred = $npc['preferred_skills'] ?? [];
      $opposed   = $npc['opposed_skills'] ?? [];

      $is_preferred = in_array($skill_used, $preferred, TRUE);
      $is_opposed   = in_array($skill_used, $opposed, TRUE);

      $gain = match ($check_result) {
        'critical_success' => ($is_preferred ? 2 : 1) * self::INFLUENCE_CRIT_MULTIPLIER,
        'success'          => $is_preferred ? 2 : 1,
        'failure'          => 0,
        'critical_failure' => $is_opposed ? -2 : -1,
        default            => 0,
      };

      $npc['points'] = ($npc['points'] ?? 0) + $gain;

      // Check resistance limit (negative influence accumulation).
      $resistance_limit = (int) ($npc['resistance_limit'] ?? -3);
      if ($npc['points'] <= $resistance_limit) {
        $npc['hostile'] = TRUE;
      }

      break;
    }
    unset($npc);

    $state['round'] = ($state['round'] ?? 0) + 1;
    return $state;
  }

  // ── Domain: Research ─────────────────────────────────────────────────────

  private function processResearchTurn(array $state, int $char_id, array $action): array {
    $topic       = $action['topic'] ?? '';
    $check_result = $action['check_result'] ?? 'failure';
    $dc          = $action['dc'] ?? 15;
    $roll        = $action['roll'] ?? 0;

    // Determine points earned based on check result vs DC.
    $points = match ($check_result) {
      'critical_success' => 2,
      'success'          => 1,
      'failure'          => 0,
      'critical_failure' => 0,
      default            => 0,
    };

    // Cap points per library entry.
    $cap_per_entry  = (int) ($state['cap_per_entry'] ?? 10);
    $current_on_topic = (int) ($state['topic_points'][$topic] ?? 0);
    $allowed        = max(0, $cap_per_entry - $current_on_topic);
    $earned         = min($points, $allowed);

    $state['topic_points'][$topic] = $current_on_topic + $earned;
    $state['total_points'] = ($state['total_points'] ?? 0) + $earned;
    $state['rounds_used']  = ($state['rounds_used'] ?? 0) + 1;

    // Unlock tiers based on total cumulative points.
    $state['unlocked_tiers'] = $this->computeUnlockedTiers(
      (int) $state['total_points'],
      $state['tier_thresholds'] ?? self::RESEARCH_TIERS
    );

    return $state;
  }

  private function computeUnlockedTiers(int $total, array $thresholds): array {
    $unlocked = [];
    foreach ($thresholds as $tier => $required) {
      if ($total >= $required) {
        $unlocked[] = $tier;
      }
    }
    return $unlocked;
  }

  // ── Domain: Infiltration ─────────────────────────────────────────────────

  private function processInfiltrationTurn(array $state, int $char_id, array $action): array {
    $check_result  = $action['check_result'] ?? 'failure';
    $awareness_add = $action['awareness_add'] ?? 0;
    $objective     = $action['objective_reached'] ?? FALSE;

    $base_gain = match ($check_result) {
      'critical_success' => 0,
      'success'          => 0,
      'failure'          => (int) ($state['awareness_per_failure'] ?? 2),
      'critical_failure' => (int) ($state['awareness_per_failure'] ?? 2) * 2, // immediate complication
      default            => 0,
    };

    $total_gain = $base_gain + (int) $awareness_add;
    $state['awareness'] = ($state['awareness'] ?? 0) + $total_gain;

    if ($check_result === 'critical_failure') {
      $state['complications'][] = [
        'character_id' => $char_id,
        'type'         => 'immediate_detection_check',
        'note'         => $action['complication_note'] ?? 'Critical failure triggered immediate complication.',
      ];
    }

    if ($objective) {
      $state['objective_reached'] = TRUE;
    }

    $state['round'] = ($state['round'] ?? 0) + 1;
    return $state;
  }

  // ── Domain: Reputation ───────────────────────────────────────────────────

  private function processReputationTurn(array $state, int $char_id, array $action): array {
    $faction      = $action['faction'] ?? 'default';
    $reputation_delta = (int) ($action['reputation_delta'] ?? 0);

    if (!isset($state['factions'][$faction])) {
      $state['factions'][$faction] = ['reputation' => 0];
    }
    $state['factions'][$faction]['reputation'] += $reputation_delta;
    // Maintain overall reputation as the primary faction's value for win/fail checks.
    $state['reputation'] = $state['factions'][$faction]['reputation'];
    $state['round'] = ($state['round'] ?? 0) + 1;
    return $state;
  }

  // ── Domain: Vehicle ───────────────────────────────────────────────────────

  private function processVehicleTurn(array $state, int $char_id, array $action): array {
    $action_type  = $action['action_type'] ?? 'pilot'; // pilot | collision | repair | passenger_action
    $check_result = $action['check_result'] ?? 'success';

    switch ($action_type) {
      case 'pilot':
        // Failed Piloting check causes damage or loss of control.
        $damage = match ($check_result) {
          'critical_failure' => (int) ($state['vehicle_speed'] ?? 30),
          'failure'          => (int) (($state['vehicle_speed'] ?? 30) / 2),
          default            => 0,
        };
        if ($damage > 0) {
          $state['vehicle_hp'] = max(0, ($state['vehicle_hp'] ?? 100) - $damage);
          $state['control_lost'] = $check_result === 'critical_failure';
        }
        else {
          $state['control_lost'] = FALSE;
          $state['distance_traveled'] = ($state['distance_traveled'] ?? 0) + ($state['vehicle_speed'] ?? 30);
          if (($state['distance_traveled'] ?? 0) >= ($state['destination_distance'] ?? PHP_INT_MAX)) {
            $state['destination_reached'] = TRUE;
          }
        }
        break;
      case 'collision':
        $target_size = $action['target_size'] ?? 'medium';
        $speed       = (int) ($state['vehicle_speed'] ?? 30);
        $damage_dealt = $this->vehicleCollisionDamage($state['vehicle_size'] ?? 'large', $speed);
        $damage_taken = (int) ($action['collision_damage_taken'] ?? 0);
        $state['collision_damage_dealt'] = $damage_dealt;
        $state['vehicle_hp'] = max(0, ($state['vehicle_hp'] ?? 100) - $damage_taken);
        $state['collision_log'][] = [
          'round'        => ($state['round'] ?? 1),
          'target'       => $target_size,
          'damage_dealt' => $damage_dealt,
          'damage_taken' => $damage_taken,
        ];
        break;
      case 'passenger_action':
        // Passengers act independently; no vehicle state change needed.
        break;
    }

    // HP thresholds affect speed and maneuverability.
    $max_hp = (int) ($state['vehicle_max_hp'] ?? $state['vehicle_hp'] ?? 100);
    $cur_hp = (int) ($state['vehicle_hp'] ?? 100);
    if ($max_hp > 0) {
      $pct = $cur_hp / $max_hp;
      if ($pct < 0.25) {
        $state['vehicle_speed']         = (int) (($state['vehicle_base_speed'] ?? 30) * 0.5);
        $state['maneuverability_penalty'] = 4;
      }
      elseif ($pct < 0.5) {
        $state['vehicle_speed']         = (int) (($state['vehicle_base_speed'] ?? 30) * 0.75);
        $state['maneuverability_penalty'] = 2;
      }
    }

    $state['round'] = ($state['round'] ?? 0) + 1;
    return $state;
  }

  private function vehicleCollisionDamage(string $size, int $speed): int {
    $base = match ($size) {
      'large'  => 4,
      'huge'   => 6,
      'gargan' => 8,
      default  => 2,
    };
    return (int) ceil(($base * $speed) / 30);
  }

  // ── Domain: Hexploration ─────────────────────────────────────────────────

  private function processHexplorationTurn(array $state, int $char_id, array $action): array {
    $activity    = $action['activity'] ?? 'travel'; // travel | reconnoiter | detect_magic | map
    $hex_id      = $action['hex_id'] ?? NULL;
    $terrain     = $action['terrain'] ?? 'open'; // open | difficult | greater_difficult | hazardous

    if ($hex_id === NULL) {
      return $state;
    }

    // Entering a hex reveals it.
    if (!isset($state['hexes'][$hex_id])) {
      $state['hexes'][$hex_id] = ['status' => 'unknown', 'terrain' => $terrain];
    }

    if ($activity === 'travel' || $activity === 'reconnoiter') {
      if ($state['hexes'][$hex_id]['status'] === 'unknown') {
        $state['hexes'][$hex_id]['status'] = 'revealed';
      }
    }

    if ($activity === 'reconnoiter') {
      $state['hexes'][$hex_id]['status'] = 'explored';
    }

    // Track exploration actions consumed based on terrain.
    $actions_used = match ($terrain) {
      'greater_difficult' => 4,
      'difficult'         => 2,
      'hazardous'         => 2,
      default             => 1,
    };
    $state['exploration_actions_used'] = ($state['exploration_actions_used'] ?? 0) + $actions_used;

    // Party incapacitation check for hazardous terrain (caller must supply result).
    if ($terrain === 'hazardous' && !empty($action['party_incapacitated'])) {
      $state['party_incapacitated'] = TRUE;
    }

    $state['round'] = ($state['round'] ?? 0) + 1;
    return $state;
  }

  // ── Domain: Duel ─────────────────────────────────────────────────────────

  private function processDuelTurn(array $state, int $char_id, array $action): array {
    $duel_type    = $state['duel_type'] ?? 'combat';
    $check_result = $action['check_result'] ?? 'success';

    if ($duel_type === 'combat') {
      $damage_dealt = (int) ($action['damage_dealt'] ?? 0);
      $damage_taken = (int) ($action['damage_taken'] ?? 0);
      $state['opponent_hp'] = max(0, ($state['opponent_hp'] ?? 10) - $damage_dealt);
      $state['pc_hp']       = max(0, ($state['pc_hp'] ?? 10) - $damage_taken);
    }
    else {
      // Skill or honor duel: opposed check.
      $delta = match ($check_result) {
        'critical_success' => 2,
        'success'          => 1,
        'failure'          => 0,
        'critical_failure' => -1,
        default            => 0,
      };
      $state['pc_score'] = ($state['pc_score'] ?? 0) + $delta;
    }

    // Honor tracking: deviation from duel terms applies penalty.
    if (!empty($action['term_violated'])) {
      $state['honor_penalties'][] = [
        'character_id' => $char_id,
        'term'         => $action['term_violated'],
        'round'        => ($state['round'] ?? 1),
      ];
      $state['honor'] = max(0, ($state['honor'] ?? 10) - 2);
    }

    $state['round'] = ($state['round'] ?? 0) + 1;
    return $state;
  }

  // ── Internal helpers ──────────────────────────────────────────────────────

  /**
   * Build the initial progress_state for a new session from config.
   */
  private function buildInitialState(string $type, array $config): array {
    $defaults = match ($type) {
      'chase'       => [
        'max_stages'    => self::CHASE_DEFAULT_STAGES,
        'party_side'    => 'catcher',
        'sides'         => ['catcher' => ['position' => self::CHASE_DEFAULT_STAGES], 'escapee' => ['position' => 0]],
        'round'         => 1,
        'complications' => [],
      ],
      'influence' => [
        'npcs'          => [],
        'round'         => 1,
        'discovery_log' => [],
      ],
      'research'  => [
        'total_points'     => 0,
        'topic_points'     => [],
        'rounds_used'      => 0,
        'round_limit'      => 10,
        'cap_per_entry'    => 10,
        'tier_thresholds'  => self::RESEARCH_TIERS,
        'target_tier_points' => self::RESEARCH_TIERS['advanced'],
        'unlocked_tiers'   => [],
      ],
      'infiltration' => [
        'awareness'           => 0,
        'awareness_per_failure' => 2,
        'detection_threshold' => 8,
        'preparation_points'  => 0,
        'objective_reached'   => FALSE,
        'complications'       => [],
        'round'               => 1,
      ],
      'reputation' => [
        'factions'       => [],
        'reputation'     => 0,
        'win_threshold'  => 10,
        'fail_threshold' => -10,
        'round'          => 1,
      ],
      'vehicle' => [
        'vehicle_hp'          => 50,
        'vehicle_max_hp'      => 50,
        'vehicle_speed'       => 30,
        'vehicle_base_speed'  => 30,
        'vehicle_size'        => 'large',
        'destination_distance'=> 300,
        'distance_traveled'   => 0,
        'destination_reached' => FALSE,
        'control_lost'        => FALSE,
        'collision_log'       => [],
        'round'               => 1,
      ],
      'hexploration' => [
        'hexes'                    => [],
        'target_hexes'             => [],
        'exploration_actions_used' => 0,
        'party_incapacitated'      => FALSE,
        'round'                    => 1,
      ],
      'duel' => [
        'duel_type'      => 'combat',
        'pc_hp'          => 10,
        'opponent_hp'    => 10,
        'pc_score'       => 0,
        'win_score'      => 3,
        'fail_score'     => -3,
        'honor'          => 10,
        'honor_penalties'=> [],
        'round'          => 1,
      ],
      default => [],
    };

    // Merge caller config over defaults; caller config wins.
    return array_replace_recursive($defaults, $config);
  }

  /**
   * Append an entry to the turn log within progress_state.
   */
  private function appendTurnLog(array $state, int $char_id, array $action): array {
    $state['turn_log'][] = [
      'character_id' => $char_id,
      'round'        => $state['round'] ?? 1,
      'action'       => $action,
      'timestamp'    => time(),
    ];
    return $state;
  }

}
