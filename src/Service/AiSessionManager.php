<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages per-campaign, per-NPC AI conversation sessions.
 *
 * Provides session isolation so that:
 * - Each campaign has its own GM narration thread.
 * - Each NPC within a campaign maintains its own dialogue history.
 * - Each encounter has scoped tactical AI context.
 *
 * Sessions store a rolling message history and a compressed summary of older
 * messages, mirroring the ai_conversation node pattern but in a lightweight
 * custom table (dc_ai_sessions) purpose-built for gameplay.
 *
 * Session key conventions (dot-separated):
 *   campaign.{id}.gm               — GM narration for a campaign
 *   campaign.{id}.npc.{entity_ref} — Individual NPC dialogue
 *   campaign.{id}.encounter        — Encounter tactical AI
 *   campaign.{id}.room_chat        — Room chat GM replies
 *
 * @see dungeoncrawler_content_update_10022() for table creation.
 */
class AiSessionManager {

  /**
   * Maximum recent messages kept in the messages JSON array.
   *
   * When exceeded, older messages are rolled into the summary.
   */
  const MAX_RECENT_MESSAGES = 30;

  /**
   * Number of messages to trigger a summary update cycle.
   */
  const SUMMARY_THRESHOLD = 15;

  /**
   * Maximum number of characters in summary text.
   */
  const MAX_SUMMARY_LENGTH = 4000;

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs the AiSessionManager.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_ai_session');
  }

  // =========================================================================
  // Session key builders.
  // =========================================================================

  /**
   * Build session key for campaign GM narration.
   */
  public function gmSessionKey(int $campaign_id): string {
    return "campaign.{$campaign_id}.gm";
  }

  /**
   * Build session key for an individual NPC within a campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $entity_ref
   *   NPC entity reference (e.g. 'goblin_guard_1', 'innkeeper_hilda').
   */
  public function npcSessionKey(int $campaign_id, string $entity_ref): string {
    // Normalize entity ref to a safe key segment.
    $safe_ref = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $entity_ref);
    return "campaign.{$campaign_id}.npc.{$safe_ref}";
  }

  /**
   * Build session key for encounter tactical AI within a campaign.
   */
  public function encounterSessionKey(int $campaign_id): string {
    return "campaign.{$campaign_id}.encounter";
  }

  /**
   * Build session key for room chat GM replies within a campaign.
   *
   * Scoped to the room so that NPC conversations from previous rooms do not
   * bleed into the current room's AI context.
   */
  public function roomChatSessionKey(int $campaign_id, string $room_id = ''): string {
    if ($room_id !== '') {
      return "campaign.{$campaign_id}.room_chat.{$room_id}";
    }
    return "campaign.{$campaign_id}.room_chat";
  }

  // =========================================================================
  // Session CRUD.
  // =========================================================================

  /**
   * Get or create a session record.
   *
   * @param string $session_key
   *   The dot-separated session key.
   * @param int $campaign_id
   *   Campaign ID for the index column.
   * @param array $metadata
   *   Optional metadata to store when creating a new session.
   *
   * @return array
   *   Session record with keys: id, session_key, campaign_id, messages,
   *   summary, metadata, message_count, created, updated.
   */
  public function getOrCreateSession(string $session_key, int $campaign_id, array $metadata = []): array {
    $record = $this->loadSession($session_key);
    if ($record !== NULL) {
      return $record;
    }

    // Create new session.
    $now = time();
    $this->database->insert('dc_ai_sessions')
      ->fields([
        'session_key' => $session_key,
        'campaign_id' => $campaign_id,
        'messages' => '[]',
        'summary' => '',
        'metadata' => json_encode($metadata),
        'message_count' => 0,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->logger->info('Created AI session @key for campaign @cid', [
      '@key' => $session_key,
      '@cid' => $campaign_id,
    ]);

    return $this->loadSession($session_key) ?? [
      'session_key' => $session_key,
      'campaign_id' => $campaign_id,
      'messages' => [],
      'summary' => '',
      'metadata' => $metadata,
      'message_count' => 0,
      'created' => $now,
      'updated' => $now,
    ];
  }

  /**
   * Load a session by key.
   *
   * @return array|null
   *   Decoded session record, or NULL if not found.
   */
  public function loadSession(string $session_key): ?array {
    $row = $this->database->select('dc_ai_sessions', 's')
      ->fields('s')
      ->condition('session_key', $session_key)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $row['messages'] = json_decode($row['messages'] ?: '[]', TRUE) ?: [];
    $row['metadata'] = json_decode($row['metadata'] ?: '{}', TRUE) ?: [];
    $row['message_count'] = (int) $row['message_count'];
    return $row;
  }

  // =========================================================================
  // Message management.
  // =========================================================================

  /**
   * Append a message exchange (user prompt + AI response) to a session.
   *
   * Automatically triggers summary compression when the message count
   * exceeds the threshold.
   *
   * @param string $session_key
   *   Session key.
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $role
   *   Message role: 'user', 'assistant', 'system'.
   * @param string $content
   *   Message content text.
   * @param array $extra
   *   Optional extra data to attach to the message (e.g. trigger type).
   */
  public function appendMessage(string $session_key, int $campaign_id, string $role, string $content, array $extra = []): void {
    $session = $this->getOrCreateSession($session_key, $campaign_id);
    $messages = $session['messages'];

    $message = [
      'role' => $role,
      'content' => $content,
      'timestamp' => date('c'),
    ];
    if (!empty($extra)) {
      $message['extra'] = $extra;
    }

    $messages[] = $message;
    $message_count = $session['message_count'] + 1;

    // Check if we need to compress old messages into the summary.
    $summary = $session['summary'] ?? '';
    if (count($messages) > self::MAX_RECENT_MESSAGES) {
      $summary = $this->compressMessages($messages, $summary);
      // Keep only the most recent messages after compression.
      $messages = array_slice($messages, -self::SUMMARY_THRESHOLD);
    }

    $this->database->update('dc_ai_sessions')
      ->fields([
        'messages' => json_encode($messages),
        'summary' => $summary,
        'message_count' => $message_count,
        'updated' => time(),
      ])
      ->condition('session_key', $session_key)
      ->execute();
  }

  /**
   * Build a context string from the session's summary + recent messages.
   *
   * This is designed to be prepended to AI prompts to give the model
   * continuity of the conversation.
   *
   * @param string $session_key
   *   Session key.
   * @param int $campaign_id
   *   Campaign ID (used if session doesn't exist yet).
   * @param int $max_recent
   *   Max number of recent messages to include (default 10).
   *
   * @return string
   *   Formatted context string (may be empty for brand-new sessions).
   */
  public function buildSessionContext(string $session_key, int $campaign_id, int $max_recent = 10): string {
    $session = $this->loadSession($session_key);
    if ($session === NULL) {
      return '';
    }

    $parts = [];

    // Include rolling summary if available.
    $summary = trim($session['summary'] ?? '');
    if ($summary !== '') {
      $parts[] = "PRIOR SESSION CONTEXT (summary of earlier interactions):\n" . $summary;
    }

    // Include recent messages.
    $messages = $session['messages'] ?? [];
    $recent = array_slice($messages, -$max_recent);
    if (!empty($recent)) {
      $lines = [];
      foreach ($recent as $msg) {
        $role = strtoupper($msg['role'] ?? 'unknown');
        $content = $msg['content'] ?? '';
        // Truncate very long messages for context efficiency.
        if (strlen($content) > 500) {
          $content = substr($content, 0, 497) . '...';
        }
        $lines[] = "[{$role}]: {$content}";
      }
      $parts[] = "RECENT CONVERSATION:\n" . implode("\n", $lines);
    }

    return implode("\n\n", $parts);
  }

  /**
   * Compress older messages into a summary string.
   *
   * Uses a simple extractive approach: keep the first and last sentence
   * of each message, plus key event references. This avoids an extra LLM
   * call for summarization (which would add latency and cost).
   *
   * @param array $messages
   *   Full message array.
   * @param string $existing_summary
   *   Existing summary text to append to.
   *
   * @return string
   *   Updated summary.
   */
  protected function compressMessages(array &$messages, string $existing_summary): string {
    // Messages to compress: everything except the last SUMMARY_THRESHOLD.
    $to_compress = array_slice($messages, 0, count($messages) - self::SUMMARY_THRESHOLD);

    if (empty($to_compress)) {
      return $existing_summary;
    }

    $new_summary_parts = [];
    foreach ($to_compress as $msg) {
      $role = $msg['role'] ?? 'unknown';
      $content = trim($msg['content'] ?? '');
      if ($content === '') {
        continue;
      }

      // Extract a brief representation.
      $brief = $this->extractBrief($content, $role);
      if ($brief !== '') {
        $new_summary_parts[] = $brief;
      }
    }

    $new_fragment = implode(' ', $new_summary_parts);

    // Combine with existing summary.
    if ($existing_summary !== '') {
      $combined = $existing_summary . ' ' . $new_fragment;
    }
    else {
      $combined = $new_fragment;
    }

    // Enforce max length by trimming from the front (oldest context).
    if (strlen($combined) > self::MAX_SUMMARY_LENGTH) {
      $combined = '...' . substr($combined, strlen($combined) - self::MAX_SUMMARY_LENGTH + 3);
    }

    return $combined;
  }

  /**
   * Extract a brief representation from a message for the summary.
   *
   * @param string $content
   *   Message content.
   * @param string $role
   *   Message role.
   *
   * @return string
   *   Compressed representation.
   */
  protected function extractBrief(string $content, string $role): string {
    // For JSON prompts (from AiGmService), extract the trigger and key context.
    $decoded = json_decode($content, TRUE);
    if (is_array($decoded) && isset($decoded['context']['trigger'])) {
      $trigger = $decoded['context']['trigger'];
      $room = $decoded['context']['room']['name'] ?? '';
      if ($room) {
        return "[{$role}: {$trigger} in {$room}]";
      }
      return "[{$role}: {$trigger}]";
    }

    // For plain text, take first sentence (up to 120 chars).
    $first_sentence = strtok($content, ".!?\n");
    if ($first_sentence !== FALSE) {
      $brief = trim($first_sentence);
      if (strlen($brief) > 120) {
        $brief = substr($brief, 0, 117) . '...';
      }
      return "[{$role}]: {$brief}.";
    }

    return '';
  }

  // =========================================================================
  // Session lifecycle.
  // =========================================================================

  /**
   * Delete all sessions for a campaign (e.g. when campaign is deleted/reset).
   */
  public function deleteSessionsForCampaign(int $campaign_id): int {
    return (int) $this->database->delete('dc_ai_sessions')
      ->condition('campaign_id', $campaign_id)
      ->execute();
  }

  /**
   * Delete a specific session.
   */
  public function deleteSession(string $session_key): void {
    $this->database->delete('dc_ai_sessions')
      ->condition('session_key', $session_key)
      ->execute();
  }

  /**
   * Get all NPC session keys for a campaign.
   *
   * @return string[]
   *   Array of session keys matching the NPC pattern.
   */
  public function getNpcSessionKeys(int $campaign_id): array {
    $prefix = "campaign.{$campaign_id}.npc.";
    return $this->database->select('dc_ai_sessions', 's')
      ->fields('s', ['session_key'])
      ->condition('session_key', $prefix . '%', 'LIKE')
      ->execute()
      ->fetchCol();
  }

}
