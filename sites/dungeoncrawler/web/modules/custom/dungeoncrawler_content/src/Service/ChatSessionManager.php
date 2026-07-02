<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages the hierarchical chat session tree and normalized message storage.
 *
 * Session hierarchy (each node is a dc_chat_sessions row):
 *
 *   campaign (root)
 *   ├── dungeon (per-dungeon aggregate, stored copy)
 *   │   └── room (objective reality — GM "God view")
 *   │       ├── character_narrative (per-character GM narration)
 *   │       └── encounter (combat-mode narration)
 *   ├── party (proximity-gated huddle)
 *   ├── whisper (mundane adjacent private)
 *   ├── spell (spell-opened private: message/telepathy/sending)
 *   ├── gm_private (secret player actions: pickpocket, stealth)
 *   └── system_log (dice, checks, mechanical results)
 *
 * Feed-up rules:
 *   - ALL messages feed up to the campaign root (GM master memory).
 *   - Room messages feed up to dungeon.
 *   - character_narrative messages are the per-character filtered view.
 *   - Party/whisper/spell/gm_private feed up to campaign only.
 *   - System_log feeds to campaign + dungeon.
 *
 * Session keys are deterministic and hierarchical:
 *   campaign.{id}
 *   campaign.{id}.dungeon.{dungeon_id}
 *   campaign.{id}.dungeon.{dungeon_id}.room.{room_id}
 *   campaign.{id}.dungeon.{dungeon_id}.room.{room_id}.char.{char_id}
 *   campaign.{id}.party
 *   campaign.{id}.whisper.{entity_ref}
 *   campaign.{id}.spell.{spell_key}.{target_ref}
 *   campaign.{id}.gm_private.{char_id}
 *   campaign.{id}.system_log
 *   campaign.{id}.dungeon.{dungeon_id}.room.{room_id}.encounter
 *
 * @see dungeoncrawler_content_update_10024() for table creation.
 */
class ChatSessionManager {

  /**
   * Valid session types.
   */
  const SESSION_TYPES = [
    'campaign',
    'dungeon',
    'room',
    'character_narrative',
    'party',
    'whisper',
    'spell',
    'gm_private',
    'system_log',
    'encounter',
  ];

  /**
   * Session types that are always fed up to the campaign root.
   */
  const FEED_TO_CAMPAIGN = [
    'dungeon',
    'room',
    'character_narrative',
    'party',
    'whisper',
    'spell',
    'gm_private',
    'system_log',
    'encounter',
  ];

  /**
   * Session types that feed up to their parent dungeon.
   */
  const FEED_TO_DUNGEON = [
    'room',
    'encounter',
    'system_log',
  ];

  /**
   * Maximum messages to return in a single query.
   */
  const MAX_QUERY_MESSAGES = 200;

  /**
   * Maximum messages before triggering summary compression on a session.
   */
  const SUMMARY_THRESHOLD = 50;

  /**
   * Maximum summary text length.
   */
  const MAX_SUMMARY_LENGTH = 6000;

  protected Connection $database;
  protected LoggerInterface $logger;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_chat_session');
  }

  // =========================================================================
  // Session key builders.
  // =========================================================================

  /**
   * Campaign root session key.
   */
  public function campaignSessionKey(int $campaign_id): string {
    return "campaign.{$campaign_id}";
  }

  /**
   * Dungeon session key.
   */
  public function dungeonSessionKey(int $campaign_id, int|string $dungeon_id): string {
    return "campaign.{$campaign_id}.dungeon.{$dungeon_id}";
  }

  /**
   * Room session key.
   */
  public function roomSessionKey(int $campaign_id, int|string $dungeon_id, string $room_id): string {
    $safe_room = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $room_id);
    return "campaign.{$campaign_id}.dungeon.{$dungeon_id}.room.{$safe_room}";
  }

  /**
   * Per-character narrative session key (scoped to a room).
   */
  public function characterNarrativeKey(int $campaign_id, int|string $dungeon_id, string $room_id, int|string $character_id): string {
    $safe_room = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $room_id);
    return "campaign.{$campaign_id}.dungeon.{$dungeon_id}.room.{$safe_room}.char.{$character_id}";
  }

  /**
   * Party chat session key.
   */
  public function partySessionKey(int $campaign_id): string {
    return "campaign.{$campaign_id}.party";
  }

  /**
   * Whisper session key (mundane adjacent whisper).
   */
  public function whisperSessionKey(int $campaign_id, string $entity_ref): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $entity_ref);
    return "campaign.{$campaign_id}.whisper.{$safe}";
  }

  /**
   * Spell channel session key.
   */
  public function spellSessionKey(int $campaign_id, string $spell_key, string $target_ref): string {
    $safe_spell = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $spell_key);
    $safe_target = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $target_ref);
    return "campaign.{$campaign_id}.spell.{$safe_spell}.{$safe_target}";
  }

  /**
   * GM private channel session key (secret actions).
   */
  public function gmPrivateSessionKey(int $campaign_id, int|string $character_id): string {
    return "campaign.{$campaign_id}.gm_private.{$character_id}";
  }

  /**
   * System log session key.
   */
  public function systemLogSessionKey(int $campaign_id): string {
    return "campaign.{$campaign_id}.system_log";
  }

  /**
   * Encounter session key (combat narration scoped to a room).
   */
  public function encounterSessionKey(int $campaign_id, int|string $dungeon_id, string $room_id): string {
    $safe_room = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $room_id);
    return "campaign.{$campaign_id}.dungeon.{$dungeon_id}.room.{$safe_room}.encounter";
  }

  // =========================================================================
  // Session CRUD.
  // =========================================================================

  /**
   * Get or create a session, ensuring the full ancestor chain exists.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $session_type
   *   One of SESSION_TYPES.
   * @param string $session_key
   *   Deterministic session key.
   * @param string $label
   *   Human-readable label.
   * @param string $scope_ref
   *   Scope-specific reference.
   * @param int|null $parent_session_id
   *   Parent session ID, or NULL for campaign root.
   * @param array $metadata
   *   Type-specific metadata.
   *
   * @return array
   *   Session record.
   */
  public function getOrCreateSession(
    int $campaign_id,
    string $session_type,
    string $session_key,
    string $label = '',
    string $scope_ref = '',
    ?int $parent_session_id = NULL,
    array $metadata = []
  ): array {
    $existing = $this->loadSession($session_key);
    if ($existing !== NULL) {
      return $existing;
    }

    if (!in_array($session_type, self::SESSION_TYPES, TRUE)) {
      throw new \InvalidArgumentException("Invalid session type: {$session_type}");
    }

    $now = time();
    $this->database->insert('dc_chat_sessions')
      ->fields([
        'campaign_id' => $campaign_id,
        'parent_session_id' => $parent_session_id,
        'session_type' => $session_type,
        'session_key' => $session_key,
        'scope_ref' => $scope_ref,
        'label' => $label,
        'metadata' => json_encode($metadata),
        'status' => 'active',
        'message_count' => 0,
        'summary' => '',
        'last_message_at' => 0,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->logger->info('Created chat session @type: @key', [
      '@type' => $session_type,
      '@key' => $session_key,
    ]);

    return $this->loadSession($session_key) ?? [
      'session_key' => $session_key,
      'session_type' => $session_type,
      'campaign_id' => $campaign_id,
      'parent_session_id' => $parent_session_id,
      'status' => 'active',
      'message_count' => 0,
      'summary' => '',
    ];
  }

  /**
   * Load a session by key.
   */
  public function loadSession(string $session_key): ?array {
    $row = $this->database->select('dc_chat_sessions', 's')
      ->fields('s')
      ->condition('session_key', $session_key)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $row['metadata'] = json_decode($row['metadata'] ?: '{}', TRUE) ?: [];
    $row['message_count'] = (int) $row['message_count'];
    $row['parent_session_id'] = $row['parent_session_id'] ? (int) $row['parent_session_id'] : NULL;
    $row['id'] = (int) $row['id'];
    return $row;
  }

  /**
   * Load a session by ID.
   */
  public function loadSessionById(int $session_id): ?array {
    $row = $this->database->select('dc_chat_sessions', 's')
      ->fields('s')
      ->condition('id', $session_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $row['metadata'] = json_decode($row['metadata'] ?: '{}', TRUE) ?: [];
    $row['message_count'] = (int) $row['message_count'];
    $row['parent_session_id'] = $row['parent_session_id'] ? (int) $row['parent_session_id'] : NULL;
    $row['id'] = (int) $row['id'];
    return $row;
  }

  // =========================================================================
  // Campaign session bootstrapping.
  // =========================================================================

  /**
   * Ensure the full session hierarchy exists for a campaign.
   *
   * Creates the campaign root + system_log + party session.
   * Dungeon/room sessions are created lazily on first message.
   *
   * @return array
   *   The campaign root session record.
   */
  public function ensureCampaignSessions(int $campaign_id, string $campaign_name = ''): array {
    // Campaign root.
    $root_key = $this->campaignSessionKey($campaign_id);
    $root = $this->getOrCreateSession(
      $campaign_id,
      'campaign',
      $root_key,
      $campaign_name ?: "Campaign #{$campaign_id}",
      (string) $campaign_id,
      NULL,
      ['description' => 'GM-only master feed. All campaign messages aggregate here.']
    );

    $root_id = $root['id'];

    // System log.
    $this->getOrCreateSession(
      $campaign_id,
      'system_log',
      $this->systemLogSessionKey($campaign_id),
      'System Log',
      (string) $campaign_id,
      $root_id,
      ['description' => 'Dice rolls, skill checks, damage, mechanical results.']
    );

    // Party chat.
    $this->getOrCreateSession(
      $campaign_id,
      'party',
      $this->partySessionKey($campaign_id),
      'Party Chat',
      (string) $campaign_id,
      $root_id,
      ['description' => 'Party huddle. NPCs excluded. Proximity-gated.']
    );

    return $root;
  }

  /**
   * Ensure dungeon session exists (creates if needed).
   *
   * @return array
   *   Dungeon session record.
   */
  public function ensureDungeonSession(int $campaign_id, int|string $dungeon_id, string $dungeon_name = ''): array {
    $root_key = $this->campaignSessionKey($campaign_id);
    $root = $this->loadSession($root_key);
    $root_id = $root ? (int) $root['id'] : NULL;

    $key = $this->dungeonSessionKey($campaign_id, $dungeon_id);
    return $this->getOrCreateSession(
      $campaign_id,
      'dungeon',
      $key,
      $dungeon_name ?: "Dungeon #{$dungeon_id}",
      (string) $dungeon_id,
      $root_id,
      ['dungeon_id' => $dungeon_id]
    );
  }

  /**
   * Ensure room session exists under a dungeon session.
   *
   * @return array
   *   Room session record.
   */
  public function ensureRoomSession(int $campaign_id, int|string $dungeon_id, string $room_id, string $room_name = ''): array {
    $dungeon_session = $this->ensureDungeonSession($campaign_id, $dungeon_id);

    $key = $this->roomSessionKey($campaign_id, $dungeon_id, $room_id);
    return $this->getOrCreateSession(
      $campaign_id,
      'room',
      $key,
      $room_name ?: "Room {$room_id}",
      $room_id,
      (int) $dungeon_session['id'],
      ['dungeon_id' => $dungeon_id, 'room_id' => $room_id]
    );
  }

  /**
   * Ensure a per-character narrative session exists under a room.
   *
   * @return array
   *   Character narrative session record.
   */
  public function ensureCharacterNarrativeSession(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    int|string $character_id,
    string $character_name = ''
  ): array {
    $room_session = $this->ensureRoomSession($campaign_id, $dungeon_id, $room_id);

    $key = $this->characterNarrativeKey($campaign_id, $dungeon_id, $room_id, $character_id);
    return $this->getOrCreateSession(
      $campaign_id,
      'character_narrative',
      $key,
      $character_name ? "{$character_name}'s perspective" : "Character #{$character_id} perspective",
      (string) $character_id,
      (int) $room_session['id'],
      ['character_id' => $character_id, 'dungeon_id' => $dungeon_id, 'room_id' => $room_id]
    );
  }

  /**
   * Ensure a GM private channel for a character.
   */
  public function ensureGmPrivateSession(int $campaign_id, int|string $character_id, string $character_name = ''): array {
    $root = $this->loadSession($this->campaignSessionKey($campaign_id));
    $root_id = $root ? (int) $root['id'] : NULL;

    $key = $this->gmPrivateSessionKey($campaign_id, $character_id);
    return $this->getOrCreateSession(
      $campaign_id,
      'gm_private',
      $key,
      $character_name ? "GM ↔ {$character_name}" : "GM Private #{$character_id}",
      (string) $character_id,
      $root_id,
      ['character_id' => $character_id]
    );
  }

  // =========================================================================
  // Message posting.
  // =========================================================================

  /**
   * Post a message to a session and propagate to feed targets.
   *
   * @param int $session_id
   *   Target session ID.
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $speaker
   *   Display name.
   * @param string $speaker_type
   *   gm|player|npc|system
   * @param string $speaker_ref
   *   Character ID, entity_ref, or ''.
   * @param string $message
   *   Message content.
   * @param string $message_type
   *   narrative|dialogue|action|mechanical|system|inner_monologue|scene_beat
   * @param string $visibility
   *   public|private|gm_only
   * @param array $metadata
   *   Optional metadata (dice_rolls, actions, etc.)
   * @param bool $feed_up
   *   Whether to propagate copies to parent sessions.
   *
   * @return int
   *   The new message ID.
   */
  public function postMessage(
    int $session_id,
    int $campaign_id,
    string $speaker,
    string $speaker_type,
    string $speaker_ref,
    string $message,
    string $message_type = 'narrative',
    string $visibility = 'public',
    array $metadata = [],
    bool $feed_up = TRUE
  ): int {
    $now = time();
    $feed_targets = [];

    // Determine feed targets based on session type.
    if ($feed_up) {
      $feed_targets = $this->resolveFeedTargets($session_id, $campaign_id);
    }

    // Insert the primary message.
    $message_id = (int) $this->database->insert('dc_chat_messages')
      ->fields([
        'session_id' => $session_id,
        'campaign_id' => $campaign_id,
        'speaker' => $speaker,
        'speaker_type' => $speaker_type,
        'speaker_ref' => $speaker_ref,
        'message' => $message,
        'message_type' => $message_type,
        'visibility' => $visibility,
        'metadata' => json_encode($metadata),
        'feed_targets' => json_encode($feed_targets),
        'source_message_id' => NULL,
        'created' => $now,
      ])
      ->execute();

    // Update session stats.
    $this->database->update('dc_chat_sessions')
      ->expression('message_count', 'message_count + 1')
      ->fields([
        'last_message_at' => $now,
        'updated' => $now,
      ])
      ->condition('id', $session_id)
      ->execute();

    // Feed-up: create copies in parent sessions.
    foreach ($feed_targets as $target_session_id) {
      $this->database->insert('dc_chat_messages')
        ->fields([
          'session_id' => $target_session_id,
          'campaign_id' => $campaign_id,
          'speaker' => $speaker,
          'speaker_type' => $speaker_type,
          'speaker_ref' => $speaker_ref,
          'message' => $message,
          'message_type' => $message_type,
          'visibility' => $visibility,
          'metadata' => json_encode($metadata),
          'feed_targets' => '[]',
          'source_message_id' => $message_id,
          'created' => $now,
        ])
        ->execute();

      // Update feed target session stats.
      $this->database->update('dc_chat_sessions')
        ->expression('message_count', 'message_count + 1')
        ->fields([
          'last_message_at' => $now,
          'updated' => $now,
        ])
        ->condition('id', $target_session_id)
        ->execute();
    }

    return $message_id;
  }

  /**
   * Resolve which parent sessions a message should feed into.
   *
   * @return int[]
   *   Array of session IDs to receive feed copies.
   */
  protected function resolveFeedTargets(int $session_id, int $campaign_id): array {
    $session = $this->loadSessionById($session_id);
    if (!$session) {
      return [];
    }

    $targets = [];
    $type = $session['session_type'];

    // Walk up the parent chain collecting feed targets.
    // Room → feeds to dungeon parent.
    // Dungeon → feeds to campaign parent.
    // Everything feeds to campaign root eventually.
    if ($session['parent_session_id'] !== NULL) {
      $parent = $this->loadSessionById($session['parent_session_id']);
      if ($parent) {
        // Feed to immediate parent.
        $targets[] = (int) $parent['id'];

        // If parent is dungeon, also feed to campaign root.
        if ($parent['parent_session_id'] !== NULL) {
          $grandparent = $this->loadSessionById($parent['parent_session_id']);
          if ($grandparent && $grandparent['session_type'] === 'campaign') {
            $targets[] = (int) $grandparent['id'];
          }
        }

        // If parent IS the campaign root, we're already feeding there.
        // If this session is directly under campaign (party, gm_private, etc.),
        // parent_session_id already points to the campaign root.
      }
    }

    // Ensure campaign root is always included for eligible types.
    if (in_array($type, self::FEED_TO_CAMPAIGN, TRUE)) {
      $root = $this->loadSession($this->campaignSessionKey($campaign_id));
      if ($root) {
        $root_id = (int) $root['id'];
        if (!in_array($root_id, $targets, TRUE)) {
          $targets[] = $root_id;
        }
      }
    }

    // Remove self from targets (should never happen, but safety).
    $targets = array_values(array_filter($targets, fn($t) => $t !== $session_id));

    return $targets;
  }

  // =========================================================================
  // Message queries.
  // =========================================================================

  /**
   * Get messages for a session (most recent first by default).
   *
   * @param int $session_id
   *   Session ID.
   * @param int $limit
   *   Max messages.
   * @param int $before_id
   *   Only messages with ID < this (for pagination).
   * @param string|null $message_type_filter
   *   Optional filter by message_type.
   *
   * @return array
   *   Array of message records (newest first).
   */
  public function getMessages(int $session_id, int $limit = 50, int $before_id = 0, ?string $message_type_filter = NULL): array {
    $limit = min($limit, self::MAX_QUERY_MESSAGES);

    $query = $this->database->select('dc_chat_messages', 'm')
      ->fields('m')
      ->condition('session_id', $session_id)
      ->orderBy('id', 'DESC')
      ->range(0, $limit);

    if ($before_id > 0) {
      $query->condition('id', $before_id, '<');
    }

    if ($message_type_filter !== NULL) {
      $query->condition('message_type', $message_type_filter);
    }

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function ($row) {
      $row['metadata'] = json_decode($row['metadata'] ?: '{}', TRUE) ?: [];
      $row['feed_targets'] = json_decode($row['feed_targets'] ?: '[]', TRUE) ?: [];
      $row['id'] = (int) $row['id'];
      $row['session_id'] = (int) $row['session_id'];
      return $row;
    }, $rows);
  }

  /**
   * Get messages in chronological order (oldest first).
   */
  public function getMessagesChronological(int $session_id, int $limit = 50, int $after_id = 0): array {
    $query = $this->database->select('dc_chat_messages', 'm')
      ->fields('m')
      ->condition('session_id', $session_id)
      ->orderBy('id', 'ASC')
      ->range(0, min($limit, self::MAX_QUERY_MESSAGES));

    if ($after_id > 0) {
      $query->condition('id', $after_id, '>');
    }

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function ($row) {
      $row['metadata'] = json_decode($row['metadata'] ?: '{}', TRUE) ?: [];
      $row['feed_targets'] = json_decode($row['feed_targets'] ?: '[]', TRUE) ?: [];
      $row['id'] = (int) $row['id'];
      $row['session_id'] = (int) $row['session_id'];
      return $row;
    }, $rows);
  }

  /**
   * Build AI context from a session's summary + recent messages.
   *
   * @param int $session_id
   *   Session ID.
   * @param int $max_recent
   *   Max recent messages to include.
   *
   * @return string
   *   Formatted context string for AI prompts.
   */
  public function buildSessionContext(int $session_id, int $max_recent = 10): string {
    $session = $this->loadSessionById($session_id);
    if (!$session) {
      return '';
    }

    $parts = [];

    // Include summary if available.
    $summary = trim($session['summary'] ?? '');
    if ($summary !== '') {
      $parts[] = "PRIOR CONTEXT (summary of earlier events):\n" . $summary;
    }

    // Include recent messages.
    $recent = $this->getMessagesChronological($session_id, $max_recent);
    // getMessagesChronological returns oldest first, but we want the LAST N.
    // Actually, we want the most recent N in chronological order.
    // Since we ordered ASC, we actually want to get the last N.
    // Let's query differently:
    $all_recent = $this->getMessages($session_id, $max_recent);
    // getMessages returns DESC, so reverse for chronological.
    $all_recent = array_reverse($all_recent);

    if (!empty($all_recent)) {
      $lines = [];
      foreach ($all_recent as $msg) {
        $type_tag = strtoupper($msg['speaker_type'] ?? 'unknown');
        $speaker = $msg['speaker'] ?? 'Unknown';
        $content = $msg['message'] ?? '';
        if (strlen($content) > 500) {
          $content = substr($content, 0, 497) . '...';
        }
        $msg_type = $msg['message_type'] ?? 'narrative';
        if ($msg_type === 'mechanical' || $msg_type === 'system') {
          $lines[] = "[SYSTEM]: {$content}";
        }
        else {
          $lines[] = "[{$type_tag} - {$speaker}]: {$content}";
        }
      }
      $parts[] = "RECENT EVENTS:\n" . implode("\n", $lines);
    }

    return implode("\n\n", $parts);
  }

  // =========================================================================
  // Session queries.
  // =========================================================================

  /**
   * Get all sessions for a campaign of a given type.
   */
  public function getSessionsByType(int $campaign_id, string $session_type): array {
    $rows = $this->database->select('dc_chat_sessions', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('session_type', $session_type)
      ->condition('status', 'active')
      ->orderBy('created', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function ($row) {
      $row['metadata'] = json_decode($row['metadata'] ?: '{}', TRUE) ?: [];
      $row['id'] = (int) $row['id'];
      return $row;
    }, $rows);
  }

  /**
   * Get child sessions of a parent.
   */
  public function getChildSessions(int $parent_session_id): array {
    $rows = $this->database->select('dc_chat_sessions', 's')
      ->fields('s')
      ->condition('parent_session_id', $parent_session_id)
      ->condition('status', 'active')
      ->orderBy('created', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function ($row) {
      $row['metadata'] = json_decode($row['metadata'] ?: '{}', TRUE) ?: [];
      $row['id'] = (int) $row['id'];
      return $row;
    }, $rows);
  }

  /**
   * Get all character narrative sessions for a room.
   *
   * @return array
   *   Keyed by character_id => session record.
   */
  public function getCharacterNarrativesForRoom(int $campaign_id, int|string $dungeon_id, string $room_id): array {
    $room_key = $this->roomSessionKey($campaign_id, $dungeon_id, $room_id);
    $room_session = $this->loadSession($room_key);
    if (!$room_session) {
      return [];
    }

    $children = $this->getChildSessions((int) $room_session['id']);
    $narratives = [];
    foreach ($children as $child) {
      if ($child['session_type'] === 'character_narrative') {
        $char_id = $child['metadata']['character_id'] ?? $child['scope_ref'];
        $narratives[$char_id] = $child;
      }
    }

    return $narratives;
  }

  // =========================================================================
  // Session lifecycle.
  // =========================================================================

  /**
   * Archive a session (e.g. when dungeon is cleared/exited).
   */
  public function archiveSession(string $session_key): bool {
    return (bool) $this->database->update('dc_chat_sessions')
      ->fields([
        'status' => 'archived',
        'updated' => time(),
      ])
      ->condition('session_key', $session_key)
      ->execute();
  }

  /**
   * Close a session (e.g. spell channel ends).
   */
  public function closeSession(string $session_key): bool {
    return (bool) $this->database->update('dc_chat_sessions')
      ->fields([
        'status' => 'closed',
        'updated' => time(),
      ])
      ->condition('session_key', $session_key)
      ->execute();
  }

  /**
   * Delete all sessions and messages for a campaign.
   */
  public function deleteAllForCampaign(int $campaign_id): void {
    $this->database->delete('dc_chat_messages')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    $this->database->delete('dc_chat_sessions')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    $this->logger->info('Deleted all chat sessions and messages for campaign @id', [
      '@id' => $campaign_id,
    ]);
  }

  /**
   * Archive a dungeon session and all descendants when dungeon is cleared.
   *
   * This triggers summary compression: the campaign root session gets
   * a summary of the dungeon's events added to its summary field.
   *
   * @param string $dungeon_summary
   *   A text summary of the dungeon events (generated by AI or extractive).
   */
  public function archiveDungeonWithSummary(int $campaign_id, int|string $dungeon_id, string $dungeon_summary = ''): void {
    $dungeon_key = $this->dungeonSessionKey($campaign_id, $dungeon_id);
    $dungeon_session = $this->loadSession($dungeon_key);

    if (!$dungeon_session) {
      return;
    }

    // Archive all descendant sessions.
    $descendants = $this->database->select('dc_chat_sessions', 's')
      ->fields('s', ['id', 'session_key'])
      ->condition('session_key', "campaign.{$campaign_id}.dungeon.{$dungeon_id}%", 'LIKE')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($descendants as $desc) {
      $this->archiveSession($desc['session_key']);
    }

    // Append dungeon summary to campaign root.
    if ($dungeon_summary !== '') {
      $root_key = $this->campaignSessionKey($campaign_id);
      $root = $this->loadSession($root_key);
      if ($root) {
        $existing_summary = trim($root['summary'] ?? '');
        $new_summary = $existing_summary !== ''
          ? $existing_summary . "\n\n--- Dungeon #{$dungeon_id} Summary ---\n" . $dungeon_summary
          : "--- Dungeon #{$dungeon_id} Summary ---\n" . $dungeon_summary;

        // Enforce max length by trimming from front.
        if (strlen($new_summary) > self::MAX_SUMMARY_LENGTH) {
          $new_summary = '...' . substr($new_summary, strlen($new_summary) - self::MAX_SUMMARY_LENGTH + 3);
        }

        $this->database->update('dc_chat_sessions')
          ->fields([
            'summary' => $new_summary,
            'updated' => time(),
          ])
          ->condition('session_key', $root_key)
          ->execute();
      }
    }

    $this->logger->info('Archived dungeon @dungeon_id sessions for campaign @campaign_id', [
      '@dungeon_id' => $dungeon_id,
      '@campaign_id' => $campaign_id,
    ]);
  }

  /**
   * Get session message count.
   */
  public function getMessageCount(int $session_id): int {
    return (int) $this->database->select('dc_chat_messages', 'm')
      ->condition('session_id', $session_id)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
