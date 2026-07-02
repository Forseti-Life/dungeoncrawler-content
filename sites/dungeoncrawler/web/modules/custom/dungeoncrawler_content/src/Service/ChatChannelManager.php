<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages chat channels within dungeon rooms.
 *
 * Channel types:
 *   - room:    The main channel. GM + all characters present. Always open.
 *              Objective reality — GM "God view". All audible events recorded.
 *   - whisper: Private 1:1 with an NPC or character. Requires proximity
 *              or a spell/ability. GM is *aware* but does not auto-respond.
 *              Perception-checkable by nearby characters.
 *   - spell:   Opened by a spell (Message, Telepathy, Sending, etc.).
 *              The spell defines range, word limit, and whether the GM
 *              overhears. AI responds as the target NPC.
 *   - party:   Party huddle. NPCs excluded. Proximity-gated (same room).
 *              GM is aware the party is conferring but doesn't see content.
 *   - gm_private: Secret player actions. Only the acting player + GM see
 *              these (pickpocket attempts, stealth declarations, etc.).
 *   - system_log: Dice rolls, skill checks, damage numbers. Structured
 *              mechanical data, not narrative.
 *
 * Channel lifecycle:
 *   1. "room" channel is always present — never created or destroyed.
 *   2. Characters open additional channels via abilities/spells.
 *   3. Channels may auto-close when the spell duration ends, the character
 *      leaves the room, or the NPC dies.
 *
 * Storage:
 *   Channels are stored in dungeon_data.rooms[idx].channels{}.
 *   Messages in dungeon_data.rooms[idx].chat[] carry a `channel` field
 *   that defaults to "room" for backward compatibility.
 *
 * Channel key conventions:
 *   "room"                        — The main room channel.
 *   "whisper:{entity_ref}"        — Whisper to an NPC/character.
 *   "spell:{spell_key}:{target}"  — Spell-opened channel.
 */
class ChatChannelManager {

  /**
   * Maximum number of non-room channels a character can have open.
   */
  const MAX_OPEN_CHANNELS = 4;

  /**
   * All valid channel types in the hierarchy.
   */
  const CHANNEL_TYPES = [
    'room',
    'whisper',
    'spell',
    'party',
    'gm_private',
    'system_log',
  ];

  /**
   * Channel types visible to the player in the UI.
   */
  const PLAYER_VISIBLE_TYPES = [
    'room',
    'whisper',
    'spell',
    'party',
    'gm_private',
  ];

  /**
   * Built-in channel definitions that may be opened by spells/abilities.
   *
   * Keys map to the Pathfinder 2e spell/ability name → channel behavior.
   *
   * 'gm_aware'      — TRUE if GM sees messages in this channel.
   * 'gm_responds'    — TRUE if the AI GM auto-generates a response.
   * 'npc_responds'   — TRUE if the AI responds as the target NPC.
   * 'word_limit'     — 0 for unlimited, >0 for a per-message cap.
   * 'range'          — 'adjacent', 'room', 'unlimited', or a number (feet).
   * 'duration'       — 'instant', 'sustained', 'scene', 'permanent'.
   */
  const SPELL_CHANNEL_DEFS = [
    'message' => [
      'label' => 'Message',
      'spell_level' => 0,
      'gm_aware' => FALSE,
      'gm_responds' => FALSE,
      'npc_responds' => TRUE,
      'word_limit' => 0,
      'range' => 120,
      'duration' => 'sustained',
      'description' => 'Whisper a message to a creature within 120 feet. Only you and the target hear it.',
    ],
    'telepathy' => [
      'label' => 'Telepathy',
      'spell_level' => 4,
      'gm_aware' => FALSE,
      'gm_responds' => FALSE,
      'npc_responds' => TRUE,
      'word_limit' => 0,
      'range' => 'unlimited',
      'duration' => 'permanent',
      'description' => 'Communicate mentally with a creature. No one else can hear.',
    ],
    'sending' => [
      'label' => 'Sending',
      'spell_level' => 5,
      'gm_aware' => FALSE,
      'gm_responds' => FALSE,
      'npc_responds' => TRUE,
      'word_limit' => 25,
      'range' => 'unlimited',
      'duration' => 'instant',
      'description' => 'Send a message of 25 words or fewer to a known creature. They can reply once.',
    ],
    'whisper' => [
      'label' => 'Whisper',
      'spell_level' => 0,
      'gm_aware' => TRUE,
      'gm_responds' => FALSE,
      'npc_responds' => TRUE,
      'word_limit' => 0,
      'range' => 'adjacent',
      'duration' => 'scene',
      'description' => 'Speak quietly to an adjacent creature. The GM may overhear.',
    ],
  ];

  protected Connection $database;
  protected LoggerInterface $logger;
  protected AccountProxyInterface $currentUser;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_chat_channel');
    $this->currentUser = $current_user;
  }

  // =========================================================================
  // Channel key builders.
  // =========================================================================

  /**
   * Build the room channel key (always "room").
   */
  public function roomChannelKey(): string {
    return 'room';
  }

  /**
   * Build a whisper channel key.
   *
   * @param string $entity_ref
   *   Target entity reference (e.g. "goblin_guard_1").
   */
  public function whisperChannelKey(string $entity_ref): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $entity_ref);
    return "whisper:{$safe}";
  }

  /**
   * Build a spell-based channel key.
   *
   * @param string $spell_key
   *   Spell identifier (e.g. "message", "telepathy", "sending").
   * @param string $target_ref
   *   Target entity reference.
   */
  public function spellChannelKey(string $spell_key, string $target_ref): string {
    $safe_spell = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $spell_key);
    $safe_target = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $target_ref);
    return "spell:{$safe_spell}:{$safe_target}";
  }

  /**
   * Parse a channel key into its components.
   *
   * @return array
   *   ['type' => 'room'|'whisper'|'spell'|'party'|'gm_private'|'system_log',
   *    'target' => string|null, 'spell_key' => string|null]
   */
  public function parseChannelKey(string $channel_key): array {
    if ($channel_key === 'room') {
      return ['type' => 'room', 'target' => NULL, 'spell_key' => NULL];
    }
    if ($channel_key === 'party') {
      return ['type' => 'party', 'target' => NULL, 'spell_key' => NULL];
    }
    if ($channel_key === 'system_log') {
      return ['type' => 'system_log', 'target' => NULL, 'spell_key' => NULL];
    }
    if (str_starts_with($channel_key, 'gm_private:')) {
      return [
        'type' => 'gm_private',
        'target' => substr($channel_key, 11),
        'spell_key' => NULL,
      ];
    }
    if (str_starts_with($channel_key, 'whisper:')) {
      return [
        'type' => 'whisper',
        'target' => substr($channel_key, 8),
        'spell_key' => NULL,
      ];
    }
    if (str_starts_with($channel_key, 'spell:')) {
      $parts = explode(':', $channel_key, 3);
      return [
        'type' => 'spell',
        'spell_key' => $parts[1] ?? NULL,
        'target' => $parts[2] ?? NULL,
      ];
    }
    return ['type' => 'unknown', 'target' => NULL, 'spell_key' => NULL];
  }

  /**
   * Build a party channel key.
   */
  public function partyChannelKey(): string {
    return 'party';
  }

  /**
   * Build a GM private channel key for a character.
   *
   * @param string|int $character_id
   *   Character ID for the private channel.
   */
  public function gmPrivateChannelKey(string|int $character_id): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $character_id);
    return "gm_private:{$safe}";
  }

  // =========================================================================
  // Channel lifecycle.
  // =========================================================================

  /**
   * Get all channels for a room, ensuring the "room" channel always exists.
   *
   * @param array $dungeon_data
   *   Full dungeon_data JSON.
   * @param int|string $room_index
   *   Room index in dungeon_data['rooms'].
   *
   * @return array
   *   Keyed by channel key → channel definition.
   */
  public function getChannels(array $dungeon_data, int|string $room_index): array {
    $channels = $dungeon_data['rooms'][$room_index]['channels'] ?? [];

    // Ensure the room channel is always present.
    if (!isset($channels['room'])) {
      $channels['room'] = [
        'key' => 'room',
        'type' => 'room',
        'label' => 'Room',
        'description' => 'Main room dialogue. Everyone present can hear.',
        'participants' => ['*'],
        'always_open' => TRUE,
        'gm_responds' => TRUE,
        'active' => TRUE,
      ];
    }

    return $channels;
  }

  /**
   * Get channels visible to a specific character.
   *
   * @param array $channels
   *   All channels (from getChannels()).
   * @param string|int|null $character_id
   *   The character checking access, or NULL for all.
   *
   * @return array
   *   Filtered channels.
   */
  public function getVisibleChannels(array $channels, string|int|null $character_id = NULL): array {
    if ($character_id === NULL) {
      return $channels;
    }

    $char_key = (string) $character_id;
    $visible = [];

    foreach ($channels as $key => $channel) {
      // Room channel is always visible.
      if ($key === 'room') {
        $visible[$key] = $channel;
        continue;
      }

      // Check if character is a participant.
      $participants = $channel['participants'] ?? [];
      if (in_array('*', $participants, TRUE) || in_array($char_key, $participants, TRUE)) {
        $visible[$key] = $channel;
      }
    }

    return $visible;
  }

  /**
   * Open a new channel in a room.
   *
   * @param array &$dungeon_data
   *   Full dungeon_data JSON (modified in place).
   * @param int|string $room_index
   *   Room index.
   * @param string $channel_key
   *   Channel key (e.g. "whisper:goblin_1" or "spell:message:oracle").
   * @param string $opened_by
   *   Character ID or entity ref that opened the channel.
   * @param string $target_entity_ref
   *   Entity reference of the target.
   * @param string $target_name
   *   Display name of the target.
   * @param string $source_ability
   *   The spell or ability that opened this channel ("whisper", "message", etc).
   * @param array $extra_meta
   *   Additional metadata to store on the channel.
   *
   * @return array
   *   ['success' => bool, 'channel' => array|null, 'error' => string|null]
   */
  public function openChannel(
    array &$dungeon_data,
    int|string $room_index,
    string $channel_key,
    string $opened_by,
    string $target_entity_ref,
    string $target_name,
    string $source_ability = 'whisper',
    array $extra_meta = []
  ): array {
    // Initialize channels if needed.
    if (!isset($dungeon_data['rooms'][$room_index]['channels'])) {
      $dungeon_data['rooms'][$room_index]['channels'] = [];
    }

    $channels = &$dungeon_data['rooms'][$room_index]['channels'];

    // If this channel already exists and is active, just return it.
    if (isset($channels[$channel_key]) && ($channels[$channel_key]['active'] ?? FALSE)) {
      return [
        'success' => TRUE,
        'channel' => $channels[$channel_key],
        'error' => NULL,
      ];
    }

    // Count open channels for this character (enforce limit).
    $open_count = 0;
    foreach ($channels as $k => $ch) {
      if ($k !== 'room' && ($ch['active'] ?? FALSE) && ($ch['opened_by'] ?? '') === $opened_by) {
        $open_count++;
      }
    }

    if ($open_count >= self::MAX_OPEN_CHANNELS) {
      return [
        'success' => FALSE,
        'channel' => NULL,
        'error' => sprintf('Maximum of %d open channels reached. Close a channel first.', self::MAX_OPEN_CHANNELS),
      ];
    }

    // Resolve the spell/ability definition.
    $parsed = $this->parseChannelKey($channel_key);
    $ability_key = $parsed['spell_key'] ?? $source_ability;
    $def = self::SPELL_CHANNEL_DEFS[$ability_key] ?? self::SPELL_CHANNEL_DEFS['whisper'];

    $channel = array_merge([
      'key' => $channel_key,
      'type' => $parsed['type'],
      'label' => $def['label'] . ' — ' . $target_name,
      'description' => $def['description'],
      'source_ability' => $ability_key,
      'opened_by' => $opened_by,
      'target_entity' => $target_entity_ref,
      'target_name' => $target_name,
      'participants' => [$opened_by, $target_entity_ref],
      'gm_aware' => $def['gm_aware'],
      'gm_responds' => $def['gm_responds'] ?? FALSE,
      'npc_responds' => $def['npc_responds'] ?? TRUE,
      'word_limit' => $def['word_limit'],
      'range' => $def['range'],
      'duration' => $def['duration'],
      'opened_at' => date('c'),
      'active' => TRUE,
      'always_open' => FALSE,
    ], $extra_meta);

    $channels[$channel_key] = $channel;

    $this->logger->info('Channel @key opened by @by targeting @target (ability: @ability)', [
      '@key' => $channel_key,
      '@by' => $opened_by,
      '@target' => $target_entity_ref,
      '@ability' => $ability_key,
    ]);

    return [
      'success' => TRUE,
      'channel' => $channel,
      'error' => NULL,
    ];
  }

  /**
   * Close a channel.
   *
   * @param array &$dungeon_data
   *   Full dungeon_data JSON.
   * @param int|string $room_index
   *   Room index.
   * @param string $channel_key
   *   Channel key to close.
   *
   * @return bool
   *   TRUE if the channel was closed, FALSE if it didn't exist or is "room".
   */
  public function closeChannel(array &$dungeon_data, int|string $room_index, string $channel_key): bool {
    if ($channel_key === 'room') {
      return FALSE; // Room channel can never be closed.
    }

    $channels = &$dungeon_data['rooms'][$room_index]['channels'];
    if (!isset($channels[$channel_key])) {
      return FALSE;
    }

    $channels[$channel_key]['active'] = FALSE;
    $channels[$channel_key]['closed_at'] = date('c');

    $this->logger->info('Channel @key closed', ['@key' => $channel_key]);
    return TRUE;
  }

  /**
   * Validate that a character can post to a specific channel.
   *
   * @param array $channel
   *   Channel definition.
   * @param string|int $character_id
   *   Character ID posting.
   * @param string $message
   *   Message text.
   *
   * @return array
   *   ['valid' => bool, 'error' => string|null]
   */
  public function validateChannelAccess(array $channel, string|int $character_id, string $message = ''): array {
    if (!($channel['active'] ?? FALSE)) {
      return ['valid' => FALSE, 'error' => 'This channel is no longer active.'];
    }

    $participants = $channel['participants'] ?? [];
    $char_str = (string) $character_id;

    if (!in_array('*', $participants, TRUE) && !in_array($char_str, $participants, TRUE)) {
      return ['valid' => FALSE, 'error' => 'You are not a participant in this channel.'];
    }

    // Check word limit (for Sending, etc.).
    $word_limit = $channel['word_limit'] ?? 0;
    if ($word_limit > 0) {
      $word_count = str_word_count($message);
      if ($word_count > $word_limit) {
        return [
          'valid' => FALSE,
          'error' => sprintf('Message exceeds %d word limit (%d words).', $word_limit, $word_count),
        ];
      }
    }

    return ['valid' => TRUE, 'error' => NULL];
  }

  /**
   * Filter chat messages by channel.
   *
   * @param array $messages
   *   All chat messages from a room.
   * @param string $channel_key
   *   Channel to filter by.
   *
   * @return array
   *   Messages matching the channel.
   */
  public function filterMessagesByChannel(array $messages, string $channel_key): array {
    return array_values(array_filter($messages, function ($msg) use ($channel_key) {
      $msg_channel = $msg['channel'] ?? 'room';
      return $msg_channel === $channel_key;
    }));
  }

  /**
   * Get the AI session key for a channel (for NPC response memory).
   *
   * Links channels to the AiSessionManager's per-NPC session keys.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $channel_key
   *   Channel key.
   *
   * @return string
   *   Session key compatible with AiSessionManager.
   */
  public function getAiSessionKeyForChannel(int $campaign_id, string $channel_key): string {
    $parsed = $this->parseChannelKey($channel_key);

    switch ($parsed['type']) {
      case 'room':
        return "campaign.{$campaign_id}.room_chat";

      case 'whisper':
      case 'spell':
        $target = $parsed['target'] ?? 'unknown';
        $safe_target = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $target);
        return "campaign.{$campaign_id}.npc.{$safe_target}";

      case 'party':
        return "campaign.{$campaign_id}.party";

      case 'gm_private':
        $target = $parsed['target'] ?? 'unknown';
        return "campaign.{$campaign_id}.gm_private.{$target}";

      case 'system_log':
        return "campaign.{$campaign_id}.system_log";

      default:
        return "campaign.{$campaign_id}.room_chat";
    }
  }

  /**
   * Get available spell/ability channel definitions.
   *
   * Used by the client to show what channel types can be opened.
   *
   * @return array
   *   The SPELL_CHANNEL_DEFS constant.
   */
  public function getAvailableChannelTypes(): array {
    return self::SPELL_CHANNEL_DEFS;
  }

}
