<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages individual play sessions (one-shot and campaign chapters).
 *
 * Satisfies dc-cr-session-structure AC-001 through AC-005:
 * - Session start/end lifecycle with character state commit
 * - One-shot (no campaign) and campaign-chapter modes
 * - State restoration for resumed campaign sessions
 * - AI GM context: prior session summaries and NPC relationship log
 */
class SessionService {

  private Connection $database;
  private LoggerInterface $logger;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
  }

  /**
   * Start a new session.
   *
   * @param array $data
   *   Keys: gm_uid (int), mode (one-shot|campaign-chapter),
   *         campaign_id (int|null), player_uids (int[]),
   *         narrative_summary (string, optional).
   *
   * @return array Session record.
   * @throws \InvalidArgumentException On bad input.
   */
  public function startSession(array $data): array {
    $mode = $data['mode'] ?? 'one-shot';
    if (!in_array($mode, ['one-shot', 'campaign-chapter'], TRUE)) {
      throw new \InvalidArgumentException('mode must be one-shot or campaign-chapter');
    }
    $campaign_id = isset($data['campaign_id']) ? (int) $data['campaign_id'] : NULL;
    if ($mode === 'campaign-chapter' && $campaign_id === NULL) {
      throw new \InvalidArgumentException('campaign_id is required for campaign-chapter sessions');
    }
    $gm_uid = (int) ($data['gm_uid'] ?? 0);
    if ($gm_uid <= 0) {
      throw new \InvalidArgumentException('gm_uid is required');
    }
    $player_uids = array_values(array_filter(array_map('intval', $data['player_uids'] ?? [])));

    // Build starting narrative state. For campaign-chapter, carry over
    // the prior session summary and NPC log.
    $narrative_state = [
      'summary' => $data['narrative_summary'] ?? '',
      'npcs' => [],
      'prior_session_summary' => '',
    ];
    if ($mode === 'campaign-chapter' && $campaign_id !== NULL) {
      $last = $this->getLastEndedSession($campaign_id);
      if ($last) {
        $prev = json_decode($last['narrative_state'], TRUE) ?? [];
        $narrative_state['prior_session_summary'] = $prev['summary'] ?? '';
        $narrative_state['npcs'] = $prev['npcs'] ?? [];
      }
    }

    $uuid = \Drupal::service('uuid')->generate();
    $now = time();
    $id = $this->database->insert('dc_sessions')
      ->fields([
        'uuid' => $uuid,
        'campaign_id' => $campaign_id,
        'mode' => $mode,
        'gm_uid' => $gm_uid,
        'player_uids' => json_encode($player_uids),
        'narrative_state' => json_encode($narrative_state),
        'character_state_snapshot' => json_encode(new \stdClass()),
        'session_xp' => 0,
        'status' => 'active',
        'started_at' => $now,
        'ended_at' => NULL,
      ])
      ->execute();

    $this->logger->info('Session {id} started (mode={mode}, campaign={cid})', [
      'id' => $id, 'mode' => $mode, 'cid' => $campaign_id ?? 'none',
    ]);

    return $this->getSession((int) $id);
  }

  /**
   * End a session and commit character state.
   *
   * @param int $session_id Session ID.
   * @param array $data
   *   Keys: character_states (array of {character_id, xp, hp, conditions,
   *         inventory}), session_xp (int), narrative_summary (string),
   *         npcs (array of {id, name, last_known_state, relationship_status}).
   *
   * @return array Updated session record.
   * @throws \InvalidArgumentException
   */
  public function endSession(int $session_id, array $data): array {
    $session = $this->getSession($session_id);
    if (!$session) {
      throw new \InvalidArgumentException('Session not found', 404);
    }
    if ($session['status'] === 'ended') {
      throw new \InvalidArgumentException('Session already ended', 409);
    }

    // Build character state snapshot keyed by character_id.
    $snapshot = [];
    foreach ($data['character_states'] ?? [] as $cs) {
      $char_id = (int) ($cs['character_id'] ?? 0);
      if ($char_id <= 0) {
        continue;
      }
      $snapshot[$char_id] = [
        'xp' => (int) ($cs['xp'] ?? 0),
        'hp' => (int) ($cs['hp'] ?? 0),
        'conditions' => $cs['conditions'] ?? [],
        'inventory' => $cs['inventory'] ?? [],
      ];
    }

    // Merge NPC state into narrative.
    $narrative_state = json_decode($session['narrative_state'], TRUE) ?? [];
    $narrative_state['summary'] = $data['narrative_summary'] ?? ($narrative_state['summary'] ?? '');
    if (!empty($data['npcs'])) {
      $narrative_state['npcs'] = $data['npcs'];
    }

    $now = time();
    $this->database->update('dc_sessions')
      ->fields([
        'status' => 'ended',
        'character_state_snapshot' => json_encode($snapshot),
        'session_xp' => (int) ($data['session_xp'] ?? 0),
        'narrative_state' => json_encode($narrative_state),
        'ended_at' => $now,
      ])
      ->condition('id', $session_id)
      ->execute();

    $this->logger->info('Session {id} ended; {n} characters committed', [
      'id' => $session_id, 'n' => count($snapshot),
    ]);

    return $this->getSession($session_id);
  }

  /**
   * Get a session by ID.
   *
   * @param int $session_id
   * @return array|null Session record or NULL if not found.
   */
  public function getSession(int $session_id): ?array {
    $row = $this->database->select('dc_sessions', 's')
      ->fields('s')
      ->condition('id', $session_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    return $this->normalizeRow($row);
  }

  /**
   * List sessions for a campaign in chronological order (AC-002).
   *
   * @param int $campaign_id
   * @param int $limit
   * @param int $offset
   * @return array[]
   */
  public function listCampaignSessions(int $campaign_id, int $limit = 50, int $offset = 0): array {
    $rows = $this->database->select('dc_sessions', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('started_at', 'ASC')
      ->range($offset, $limit)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    return array_values(array_map([$this, 'normalizeRow'], $rows));
  }

  /**
   * Get the last ended session for a campaign (for state restore on resume).
   *
   * AC-003: campaign session start loads character state from last session.
   *
   * @param int $campaign_id
   * @return array|null
   */
  public function getLastEndedSession(int $campaign_id): ?array {
    $row = $this->database->select('dc_sessions', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('status', 'ended')
      ->orderBy('ended_at', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $row ? $this->normalizeRow($row) : NULL;
  }

  /**
   * Calculate cumulative XP for a character across all campaign sessions.
   *
   * AC-002: XP totals accumulate correctly across sessions.
   *
   * @param int $campaign_id
   * @param int $character_id
   * @return int Total XP earned in campaign.
   */
  public function getCampaignCharacterXp(int $campaign_id, int $character_id): int {
    $rows = $this->database->select('dc_sessions', 's')
      ->fields('s', ['character_state_snapshot'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', 'ended')
      ->execute()
      ->fetchAll();

    $total = 0;
    foreach ($rows as $row) {
      $snapshot = json_decode($row->character_state_snapshot, TRUE) ?? [];
      $char_state = $snapshot[$character_id] ?? ($snapshot[(string) $character_id] ?? []);
      $total += (int) ($char_state['xp'] ?? 0);
    }
    return $total;
  }

  /**
   * Build the AI GM context block from prior sessions (AC-001, AC-005).
   *
   * Returns up to 5 prior-session summaries (recent-first), concatenated and
   * truncated to fit the AI context window. Also returns the NPC log from the
   * most recent session.
   *
   * TC-GNE-02 fix: previously returned LIMIT 1; now returns multiple summaries
   * prioritising recent sessions per AC-001 acceptance criteria.
   *
   * @param int $campaign_id
   * @return array
   *   Keys: prior_session_summary (string, concatenated + truncated), npcs (array).
   */
  public function buildAiGmContext(int $campaign_id): array {
    // Fetch up to 5 ended sessions, most recent first.
    $rows = $this->database->select('dc_sessions', 's')
      ->fields('s', ['narrative_state'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', 'ended')
      ->orderBy('ended_at', 'DESC')
      ->range(0, 5)
      ->execute()
      ->fetchAll();

    if (empty($rows)) {
      return ['prior_session_summary' => '', 'npcs' => []];
    }

    // Maximum total character length for all summaries combined (context window
    // budget — keeps AI prompt within safe LLM limits).
    $context_window_limit = 3000;

    $summaries = [];
    $npcs = [];
    $npc_loaded = FALSE;
    foreach ($rows as $row) {
      $narrative = json_decode($row->narrative_state ?? '{}', TRUE) ?? [];
      // Collect NPCs from the most recent session only.
      if (!$npc_loaded) {
        $npcs = $narrative['npcs'] ?? [];
        $npc_loaded = TRUE;
      }
      $summary = trim((string) ($narrative['summary'] ?? ''));
      if ($summary !== '') {
        $summaries[] = $summary;
      }
    }

    if (empty($summaries)) {
      return ['prior_session_summary' => '', 'npcs' => $npcs];
    }

    // Concatenate recent-first summaries, truncating to fit context window.
    $parts = [];
    $total_length = 0;
    foreach ($summaries as $index => $summary) {
      $label = 'Session -' . ($index + 1) . ': ';
      $entry = $label . $summary;
      if ($total_length + strlen($entry) > $context_window_limit) {
        // Truncate this entry to fill remaining budget.
        $remaining = $context_window_limit - $total_length - strlen($label) - 3;
        if ($remaining > 0) {
          $parts[] = $label . substr($summary, 0, $remaining) . '...';
        }
        break;
      }
      $parts[] = $entry;
      $total_length += strlen($entry) + 1;
    }

    return [
      'prior_session_summary' => implode("\n", $parts),
      'npcs' => $npcs,
    ];
  }

  /**
   * Normalize a raw DB row.
   */
  private function normalizeRow(array $row): array {
    return [
      'id' => (int) $row['id'],
      'uuid' => $row['uuid'] ?? '',
      'campaign_id' => isset($row['campaign_id']) ? (int) $row['campaign_id'] : NULL,
      'mode' => $row['mode'] ?? 'one-shot',
      'gm_uid' => (int) ($row['gm_uid'] ?? 0),
      'player_uids' => json_decode($row['player_uids'] ?? '[]', TRUE) ?? [],
      'narrative_state' => json_decode($row['narrative_state'] ?? '{}', TRUE) ?? [],
      'character_state_snapshot' => json_decode($row['character_state_snapshot'] ?? '{}', TRUE) ?? [],
      'session_xp' => (int) ($row['session_xp'] ?? 0),
      'status' => $row['status'] ?? 'active',
      'started_at' => (int) ($row['started_at'] ?? 0),
      'ended_at' => isset($row['ended_at']) ? (int) $row['ended_at'] : NULL,
    ];
  }

}
