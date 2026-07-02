<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * GM Running Guide operational services.
 *
 * Covers:
 *  - Session zero records (party links, character hooks, player preferences)
 *  - GM dashboard modifier cache (PC Perception/Will/Recall Knowledge)
 *  - Ruling records with provisional flags, precedent linkage, review workflow
 *  - Safety configuration (X-Card, Lines/Veils, lethality, TPK handling)
 *  - Story Point economy (bounded narrative interventions)
 *  - Content rarity allowlist (campaign-level uncommon/rare access grants)
 *  - Encounter narrative metadata (purpose, adversary rationale, setup profile)
 *  - Adventure / campaign design records (hooks, scene-type tracking, scope templates)
 *  - Secret check reveal (GM override for Calculator::roll secret results)
 *
 * Implements: dc-gmg-running-guide
 */
class GmRunningGuideService {

  const LETHALITY_LEVELS     = ['heroic', 'gritty', 'deadly'];
  const TPK_HANDLING         = ['pause_and_discuss', 'consequence_mode'];
  const STORY_POINT_ACTIONS  = ['minor_twist', 'scene_fact', 'npc_attitude_shift'];
  const RARITY_TIERS         = ['common', 'uncommon', 'rare', 'unique'];
  const SCENE_TYPES          = ['combat', 'social', 'problem_solving', 'stealth'];
  const SETUP_PROFILES       = ['ambush', 'negotiation_collapse', 'duel', 'chase_transition', 'retreat', 'surrender'];
  const CAMPAIGN_SCOPE_NAMES = ['one_shot', 'brief', 'extended', 'epic'];
  const DOWNTIME_DEPTHS      = ['light', 'medium', 'deep'];

  public function __construct(
    private readonly Connection     $database,
    private readonly AccountInterface $currentUser
  ) {}

  // ── Session Zero ─────────────────────────────────────────────────────────

  /**
   * Create or update the session-zero record for a campaign.
   *
   * @param int $campaign_id
   * @param array $data
   *   Keys: pc_links (array), character_hooks (array), player_prefs (array),
   *         adventure_hooks (array).
   * @return array
   */
  public function setSessionZero(int $campaign_id, array $data): array {
    $existing = $this->getSessionZero($campaign_id);
    $now = time();
    $payload = [
      'pc_links_json'       => json_encode($data['pc_links'] ?? []),
      'character_hooks_json'=> json_encode($data['character_hooks'] ?? []),
      'player_prefs_json'   => json_encode($data['player_prefs'] ?? []),
      'adventure_hooks_json'=> json_encode($data['adventure_hooks'] ?? []),
    ];

    if ($existing) {
      $this->database->update('dc_gm_session_zero')
        ->fields($payload + ['updated' => $now])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    else {
      $this->database->insert('dc_gm_session_zero')
        ->fields($payload + ['campaign_id' => $campaign_id, 'created' => $now, 'updated' => $now])
        ->execute();
    }
    return $this->getSessionZero($campaign_id);
  }

  /**
   * Get session-zero record for a campaign.
   */
  public function getSessionZero(int $campaign_id): ?array {
    $row = $this->database->select('dc_gm_session_zero', 'z')
      ->fields('z')->condition('campaign_id', $campaign_id)
      ->execute()->fetchAssoc();
    return $row ? $this->decodeJsonFields($row, ['pc_links_json', 'character_hooks_json', 'player_prefs_json', 'adventure_hooks_json']) : NULL;
  }

  // ── GM Dashboard Modifier Cache ───────────────────────────────────────────

  /**
   * Refresh the GM dashboard cache with current PC modifier snapshots.
   *
   * @param int $campaign_id
   * @param array $pc_snapshots
   *   Array of [ 'pc_id' => int, 'name' => string, 'perception' => int,
   *               'will_save' => int, 'recall_skills' => array ] per PC.
   * @return array  Updated dashboard.
   */
  public function refreshDashboard(int $campaign_id, array $pc_snapshots): array {
    $now = time();
    $payload = [
      'campaign_id'        => $campaign_id,
      'pc_snapshots_json'  => json_encode($pc_snapshots),
      'refreshed_at'       => $now,
    ];

    $existing = $this->database->select('dc_gm_dashboard', 'd')
      ->fields('d', ['id'])->condition('campaign_id', $campaign_id)
      ->execute()->fetchField();

    if ($existing) {
      $this->database->update('dc_gm_dashboard')
        ->fields(['pc_snapshots_json' => $payload['pc_snapshots_json'], 'refreshed_at' => $now])
        ->condition('campaign_id', $campaign_id)->execute();
    }
    else {
      $this->database->insert('dc_gm_dashboard')->fields($payload)->execute();
    }
    return $this->getDashboard($campaign_id);
  }

  /**
   * Get the GM dashboard cache for a campaign.
   */
  public function getDashboard(int $campaign_id): ?array {
    $row = $this->database->select('dc_gm_dashboard', 'd')
      ->fields('d')->condition('campaign_id', $campaign_id)
      ->execute()->fetchAssoc();
    return $row ? $this->decodeJsonFields($row, ['pc_snapshots_json']) : NULL;
  }

  // ── Secret Check Reveal ───────────────────────────────────────────────────

  /**
   * Record a GM decision to reveal (or keep hidden) a secret check result.
   *
   * The Calculator already omits roll from response when secret=true. This
   * method persists a GM override decision so it can be surfaced in the
   * encounter log.
   *
   * @param int $campaign_id
   * @param int $session_id
   * @param string $roll_ref   Opaque reference to the roll (e.g. "action_123").
   * @param bool   $reveal     TRUE = reveal result to players; FALSE = keep hidden.
   * @param int|null $actual_roll  The actual roll value to optionally disclose.
   * @return array
   */
  public function recordSecretCheckReveal(int $campaign_id, int $session_id, string $roll_ref, bool $reveal, ?int $actual_roll = NULL): array {
    $record = [
      'campaign_id' => $campaign_id,
      'session_id'  => $session_id,
      'roll_ref'    => $roll_ref,
      'revealed'    => (int) $reveal,
      'actual_roll' => $actual_roll,
      'gm_id'       => (int) $this->currentUser->id(),
      'created'     => time(),
    ];
    $this->database->insert('dc_gm_secret_reveal')->fields($record)->execute();
    return $record;
  }

  // ── Ruling Records ───────────────────────────────────────────────────────

  /**
   * Create a new ruling record.
   *
   * @param int $campaign_id
   * @param array $data
   *   Keys: title (required), description, is_provisional (bool), precedent_ref (int|null),
   *         is_exception (bool).
   */
  public function createRuling(int $campaign_id, array $data): array {
    if (empty($data['title'])) {
      throw new \InvalidArgumentException('title is required', 400);
    }
    $id = $this->database->insert('dc_gm_ruling')->fields([
      'campaign_id'          => $campaign_id,
      'gm_id'                => (int) $this->currentUser->id(),
      'title'                => $data['title'],
      'description'          => $data['description'] ?? '',
      'is_provisional'       => (int) ($data['is_provisional'] ?? 0),
      'deferred_review'      => (int) ($data['is_provisional'] ?? 0),
      'precedent_ref'        => $data['precedent_ref'] ?? NULL,
      'is_exception'         => (int) ($data['is_exception'] ?? 0),
      'review_notes'         => NULL,
      'reviewed_at'          => NULL,
      'created'              => time(),
      'updated'              => time(),
    ])->execute();
    return $this->getRuling((int) $id);
  }

  /**
   * Get a single ruling.
   */
  public function getRuling(int $ruling_id): ?array {
    $row = $this->database->select('dc_gm_ruling', 'r')
      ->fields('r')->condition('id', $ruling_id)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  /**
   * List all rulings for a campaign.
   *
   * @param array $filters  Optional: is_provisional (bool), is_exception (bool).
   */
  public function listRulings(int $campaign_id, array $filters = []): array {
    $query = $this->database->select('dc_gm_ruling', 'r')
      ->fields('r')->condition('campaign_id', $campaign_id);
    if (isset($filters['is_provisional'])) {
      $query->condition('is_provisional', (int) $filters['is_provisional']);
    }
    if (isset($filters['is_exception'])) {
      $query->condition('is_exception', (int) $filters['is_exception']);
    }
    return $query->orderBy('created', 'DESC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Mark a provisional ruling as reviewed and publish the clarification.
   */
  public function markRulingReviewed(int $ruling_id, string $review_notes): array {
    $ruling = $this->getRuling($ruling_id);
    if (!$ruling) {
      throw new \InvalidArgumentException("Ruling {$ruling_id} not found", 404);
    }
    $this->database->update('dc_gm_ruling')->fields([
      'is_provisional'  => 0,
      'deferred_review' => 0,
      'review_notes'    => $review_notes,
      'reviewed_at'     => time(),
      'updated'         => time(),
    ])->condition('id', $ruling_id)->execute();
    return $this->getRuling($ruling_id);
  }

  // ── Safety Configuration ─────────────────────────────────────────────────

  /**
   * Get or create the safety configuration for a campaign.
   */
  public function getSafety(int $campaign_id): array {
    $row = $this->database->select('dc_gm_safety', 's')
      ->fields('s')->condition('campaign_id', $campaign_id)
      ->execute()->fetchAssoc();
    if (!$row) {
      return [
        'campaign_id'          => $campaign_id,
        'lethality_level'      => 'heroic',
        'tpk_handling'         => 'pause_and_discuss',
        'xcard_enabled'        => TRUE,
        'lines_json'           => [],
        'veils_json'           => [],
        'governance_policy'    => '',
        'zero_tolerance_json'  => [],
      ];
    }
    return $this->decodeJsonFields($row, ['lines_json', 'veils_json', 'zero_tolerance_json']);
  }

  /**
   * Set safety configuration for a campaign.
   *
   * @param int $campaign_id
   * @param array $data
   *   Keys: lethality_level, tpk_handling, xcard_enabled (bool), lines (array),
   *         veils (array), governance_policy (string), zero_tolerance (array).
   */
  public function setSafety(int $campaign_id, array $data): array {
    if (isset($data['lethality_level']) && !in_array($data['lethality_level'], self::LETHALITY_LEVELS, TRUE)) {
      throw new \InvalidArgumentException('Invalid lethality_level', 400);
    }
    if (isset($data['tpk_handling']) && !in_array($data['tpk_handling'], self::TPK_HANDLING, TRUE)) {
      throw new \InvalidArgumentException('Invalid tpk_handling', 400);
    }
    $now = time();
    $payload = [
      'lethality_level'     => $data['lethality_level']     ?? 'heroic',
      'tpk_handling'        => $data['tpk_handling']        ?? 'pause_and_discuss',
      'xcard_enabled'       => (int) ($data['xcard_enabled'] ?? 1),
      'lines_json'          => json_encode($data['lines']         ?? []),
      'veils_json'          => json_encode($data['veils']         ?? []),
      'governance_policy'   => $data['governance_policy']   ?? '',
      'zero_tolerance_json' => json_encode($data['zero_tolerance'] ?? []),
    ];

    $existing = $this->database->select('dc_gm_safety', 's')
      ->fields('s', ['id'])->condition('campaign_id', $campaign_id)
      ->execute()->fetchField();

    if ($existing) {
      $this->database->update('dc_gm_safety')
        ->fields($payload + ['updated' => $now])
        ->condition('campaign_id', $campaign_id)->execute();
    }
    else {
      $this->database->insert('dc_gm_safety')
        ->fields($payload + ['campaign_id' => $campaign_id, 'created' => $now, 'updated' => $now])
        ->execute();
    }
    return $this->getSafety($campaign_id);
  }

  // ── Story Points ─────────────────────────────────────────────────────────

  /**
   * Get current story points for a player in a campaign.
   */
  public function getStoryPoints(int $campaign_id, int $player_id): array {
    $row = $this->database->select('dc_story_point', 'sp')
      ->fields('sp')
      ->condition('campaign_id', $campaign_id)
      ->condition('player_id', $player_id)
      ->execute()->fetchAssoc();
    return $row ?? [
      'campaign_id'      => $campaign_id,
      'player_id'        => $player_id,
      'points_available' => 0,
      'points_used'      => 0,
    ];
  }

  /**
   * Award story points to a player (GM action).
   *
   * @param int $amount  Number of points to grant (default 1).
   */
  public function awardStoryPoints(int $campaign_id, int $player_id, int $amount = 1): array {
    $current = $this->getStoryPoints($campaign_id, $player_id);
    return $this->upsertStoryPoints($campaign_id, $player_id,
      ($current['points_available'] ?? 0) + $amount,
      $current['points_used'] ?? 0
    );
  }

  /**
   * Spend a story point for a player (validates available points).
   *
   * @param string $action  One of STORY_POINT_ACTIONS.
   */
  public function spendStoryPoint(int $campaign_id, int $player_id, string $action): array {
    if (!in_array($action, self::STORY_POINT_ACTIONS, TRUE)) {
      throw new \InvalidArgumentException('Invalid story point action: ' . $action, 400);
    }
    $current = $this->getStoryPoints($campaign_id, $player_id);
    if (($current['points_available'] ?? 0) < 1) {
      throw new \InvalidArgumentException('No story points available', 400);
    }
    return $this->upsertStoryPoints($campaign_id, $player_id,
      $current['points_available'] - 1,
      ($current['points_used'] ?? 0) + 1
    );
  }

  /**
   * Reset story points for all players in a campaign (start of session).
   *
   * @param int $points_per_player
   */
  public function resetSessionStoryPoints(int $campaign_id, int $points_per_player = 1): int {
    // Update existing rows.
    $this->database->update('dc_story_point')
      ->fields(['points_available' => $points_per_player, 'points_used' => 0, 'last_updated' => time()])
      ->condition('campaign_id', $campaign_id)
      ->execute();
    // Return count of players with active records.
    return (int) $this->database->select('dc_story_point', 'sp')
      ->condition('campaign_id', $campaign_id)
      ->countQuery()->execute()->fetchField();
  }

  private function upsertStoryPoints(int $campaign_id, int $player_id, int $available, int $used): array {
    $now = time();
    $existing = $this->database->select('dc_story_point', 'sp')
      ->fields('sp', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('player_id', $player_id)
      ->execute()->fetchField();

    if ($existing) {
      $this->database->update('dc_story_point')
        ->fields(['points_available' => $available, 'points_used' => $used, 'last_updated' => $now])
        ->condition('id', $existing)->execute();
    }
    else {
      $this->database->insert('dc_story_point')->fields([
        'campaign_id'      => $campaign_id,
        'player_id'        => $player_id,
        'points_available' => $available,
        'points_used'      => $used,
        'last_updated'     => $now,
      ])->execute();
    }
    return $this->getStoryPoints($campaign_id, $player_id);
  }

  // ── Content Rarity Allowlist ──────────────────────────────────────────────

  /**
   * Get the campaign rarity allowlist.
   */
  public function getRarityAllowlist(int $campaign_id): array {
    $row = $this->database->select('dc_gm_rarity', 'r')
      ->fields('r')->condition('campaign_id', $campaign_id)
      ->execute()->fetchAssoc();
    return $row ? $this->decodeJsonFields($row, ['allowlist_json', 'denylist_json']) : [
      'campaign_id'   => $campaign_id,
      'allowlist_json'=> [],
      'denylist_json' => [],
      'organized_play'=> FALSE,
    ];
  }

  /**
   * Set the rarity allowlist/denylist for a campaign.
   *
   * @param array $data
   *   Keys: allowlist (array of { item_id, rarity, reason }), denylist (array),
   *         organized_play (bool).
   */
  public function setRarityAllowlist(int $campaign_id, array $data): array {
    $now = time();
    $payload = [
      'allowlist_json' => json_encode($data['allowlist'] ?? []),
      'denylist_json'  => json_encode($data['denylist']  ?? []),
      'organized_play' => (int) ($data['organized_play'] ?? 0),
    ];

    $existing = $this->database->select('dc_gm_rarity', 'r')
      ->fields('r', ['id'])->condition('campaign_id', $campaign_id)
      ->execute()->fetchField();

    if ($existing) {
      $this->database->update('dc_gm_rarity')
        ->fields($payload + ['updated' => $now])
        ->condition('campaign_id', $campaign_id)->execute();
    }
    else {
      $this->database->insert('dc_gm_rarity')
        ->fields($payload + ['campaign_id' => $campaign_id, 'created' => $now, 'updated' => $now])
        ->execute();
    }
    return $this->getRarityAllowlist($campaign_id);
  }

  /**
   * Evaluate whether an item's rarity allows access in this campaign.
   *
   * @param int    $campaign_id
   * @param string $item_id
   * @param string $rarity       'common'|'uncommon'|'rare'|'unique'
   * @return array  { allowed: bool, reason: string, via: 'default'|'allowlist'|'denylist' }
   */
  public function evaluateRarity(int $campaign_id, string $item_id, string $rarity): array {
    $config = $this->getRarityAllowlist($campaign_id);
    $allowlist = $config['allowlist_json'] ?? [];
    $denylist  = $config['denylist_json']  ?? [];

    // Check denylist first.
    foreach ($denylist as $entry) {
      if (($entry['item_id'] ?? '') === $item_id) {
        return ['allowed' => FALSE, 'reason' => $entry['reason'] ?? 'On denylist', 'via' => 'denylist'];
      }
    }
    // Check allowlist.
    foreach ($allowlist as $entry) {
      if (($entry['item_id'] ?? '') === $item_id) {
        return ['allowed' => TRUE, 'reason' => $entry['reason'] ?? 'On allowlist', 'via' => 'allowlist'];
      }
    }
    // Default access semantics.
    $allowed = in_array($rarity, ['common'], TRUE);
    return [
      'allowed' => $allowed,
      'reason'  => $allowed ? 'Freely available (common)' : 'Requires GM allowlist entry for ' . $rarity,
      'via'     => 'default',
    ];
  }

  // ── Encounter Narrative Metadata ─────────────────────────────────────────

  /**
   * Set narrative metadata for an encounter (purpose, adversary rationale, etc.).
   *
   * @param int $campaign_id
   * @param int $encounter_id
   * @param array $data
   *   Keys: purpose (string), adversary_rationale (string), location_hooks (array),
   *         setup_profile (string), dynamic_twists (array), emotional_beat (string).
   */
  public function setEncounterMetadata(int $campaign_id, int $encounter_id, array $data): array {
    if (isset($data['setup_profile']) && !in_array($data['setup_profile'], self::SETUP_PROFILES, TRUE)) {
      throw new \InvalidArgumentException('Invalid setup_profile', 400);
    }
    $now = time();
    $payload = [
      'purpose'              => $data['purpose']           ?? '',
      'adversary_rationale'  => $data['adversary_rationale'] ?? '',
      'location_hooks_json'  => json_encode($data['location_hooks']  ?? []),
      'setup_profile'        => $data['setup_profile']     ?? NULL,
      'dynamic_twists_json'  => json_encode($data['dynamic_twists']  ?? []),
      'emotional_beat'       => $data['emotional_beat']    ?? '',
    ];

    $existing = $this->database->select('dc_encounter_metadata', 'em')
      ->fields('em', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('encounter_id', $encounter_id)
      ->execute()->fetchField();

    if ($existing) {
      $this->database->update('dc_encounter_metadata')
        ->fields($payload + ['updated' => $now])
        ->condition('id', $existing)->execute();
    }
    else {
      $this->database->insert('dc_encounter_metadata')
        ->fields($payload + [
          'campaign_id'  => $campaign_id,
          'encounter_id' => $encounter_id,
          'created'      => $now,
          'updated'      => $now,
        ])->execute();
    }
    return $this->getEncounterMetadata($campaign_id, $encounter_id);
  }

  /**
   * Get narrative metadata for an encounter.
   */
  public function getEncounterMetadata(int $campaign_id, int $encounter_id): ?array {
    $row = $this->database->select('dc_encounter_metadata', 'em')
      ->fields('em')
      ->condition('campaign_id', $campaign_id)
      ->condition('encounter_id', $encounter_id)
      ->execute()->fetchAssoc();
    return $row ? $this->decodeJsonFields($row, ['location_hooks_json', 'dynamic_twists_json']) : NULL;
  }

  // ── Adventure Design Records ──────────────────────────────────────────────

  /**
   * Upsert adventure design record for a campaign.
   *
   * @param array $data
   *   Keys: session_id (optional), scene_type (one of SCENE_TYPES),
   *         pc_hooks (array), spotlight_target_pc (int|null), emotional_beat (string).
   */
  public function recordSceneDesign(int $campaign_id, array $data): array {
    if (isset($data['scene_type']) && !in_array($data['scene_type'], self::SCENE_TYPES, TRUE)) {
      throw new \InvalidArgumentException('Invalid scene_type', 400);
    }
    $id = $this->database->insert('dc_gm_adventure_design')->fields([
      'campaign_id'         => $campaign_id,
      'session_id'          => $data['session_id'] ?? NULL,
      'scene_type'          => $data['scene_type'] ?? 'combat',
      'pc_hooks_json'       => json_encode($data['pc_hooks'] ?? []),
      'spotlight_pc'        => $data['spotlight_target_pc'] ?? NULL,
      'emotional_beat'      => $data['emotional_beat'] ?? '',
      'created'             => time(),
    ])->execute();

    return $this->database->select('dc_gm_adventure_design', 'ad')
      ->fields('ad')->condition('id', $id)
      ->execute()->fetchAssoc();
  }

  /**
   * Get scene-type diversity summary for a campaign (warns on over-concentration).
   *
   * @return array  [ 'counts' => [ scene_type => count ], 'warnings' => string[] ]
   */
  public function getSceneTypeSummary(int $campaign_id): array {
    $rows = $this->database->select('dc_gm_adventure_design', 'ad')
      ->fields('ad', ['scene_type'])
      ->condition('campaign_id', $campaign_id)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $counts = array_fill_keys(self::SCENE_TYPES, 0);
    foreach ($rows as $row) {
      $type = $row['scene_type'] ?? 'combat';
      $counts[$type] = ($counts[$type] ?? 0) + 1;
    }

    $total    = array_sum($counts);
    $warnings = [];
    if ($total > 0) {
      foreach ($counts as $type => $count) {
        $pct = ($count / $total) * 100;
        if ($pct > 60) {
          $warnings[] = "Scene type '{$type}' is {$pct}% of all scenes — consider diversifying.";
        }
      }
    }

    return ['counts' => $counts, 'total' => $total, 'warnings' => $warnings];
  }

  // ── Campaign Design Records ───────────────────────────────────────────────

  /**
   * Set campaign design preferences (scope template, intake data).
   *
   * @param array $data
   *   Keys: scope (one of CAMPAIGN_SCOPE_NAMES), level_ceiling (int),
   *         downtime_depth (one of DOWNTIME_DEPTHS), collaboration_mode (string),
   *         touchstones (array), character_goals (array).
   */
  public function setCampaignDesign(int $campaign_id, array $data): array {
    if (isset($data['scope']) && !in_array($data['scope'], self::CAMPAIGN_SCOPE_NAMES, TRUE)) {
      throw new \InvalidArgumentException('Invalid scope', 400);
    }
    if (isset($data['downtime_depth']) && !in_array($data['downtime_depth'], self::DOWNTIME_DEPTHS, TRUE)) {
      throw new \InvalidArgumentException('Invalid downtime_depth', 400);
    }
    $now = time();
    $payload = [
      'scope'                => $data['scope']          ?? 'extended',
      'level_ceiling'        => (int) ($data['level_ceiling']  ?? 20),
      'downtime_depth'       => $data['downtime_depth'] ?? 'medium',
      'collaboration_mode'   => $data['collaboration_mode'] ?? 'gm_led',
      'touchstones_json'     => json_encode($data['touchstones']     ?? []),
      'character_goals_json' => json_encode($data['character_goals'] ?? []),
    ];

    $existing = $this->database->select('dc_gm_campaign_design', 'cd')
      ->fields('cd', ['id'])->condition('campaign_id', $campaign_id)
      ->execute()->fetchField();

    if ($existing) {
      $this->database->update('dc_gm_campaign_design')
        ->fields($payload + ['updated' => $now])
        ->condition('campaign_id', $campaign_id)->execute();
    }
    else {
      $this->database->insert('dc_gm_campaign_design')
        ->fields($payload + ['campaign_id' => $campaign_id, 'created' => $now, 'updated' => $now])
        ->execute();
    }
    return $this->getCampaignDesign($campaign_id);
  }

  /**
   * Get campaign design preferences.
   */
  public function getCampaignDesign(int $campaign_id): ?array {
    $row = $this->database->select('dc_gm_campaign_design', 'cd')
      ->fields('cd')->condition('campaign_id', $campaign_id)
      ->execute()->fetchAssoc();
    return $row ? $this->decodeJsonFields($row, ['touchstones_json', 'character_goals_json']) : NULL;
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  private function decodeJsonFields(array $row, array $fields): array {
    foreach ($fields as $field) {
      if (isset($row[$field])) {
        $row[str_replace('_json', '', $field)] = json_decode($row[$field], TRUE) ?? [];
      }
    }
    return $row;
  }

}
