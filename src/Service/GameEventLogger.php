<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Append-only event logger for the game coordinator.
 *
 * Every game action produces an event record stored in
 * dungeon_data.event_log[]. Events are used for:
 * - Timeline UI (scrollable game history)
 * - AI context window (recent events fed to GM prompts)
 * - Replay and undo capabilities (future)
 *
 * Events are capped at MAX_EVENTS to prevent unbounded growth.
 */
class GameEventLogger {

  /**
   * Maximum number of events to retain in the event log.
   */
  const MAX_EVENTS = 500;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Optional database connection for persistent event storage.
   */
  protected ?Connection $database;

  /**
   * Optional ConditionManager reference used to drain buffered
   * condition-change events (applied/updated/removed) so they are always
   * logged to the action log/chat, regardless of which call site in the
   * codebase actually triggered the condition change.
   *
   * @see ConditionManager::drainPendingConditionEvents()
   */
  protected ?ConditionManager $conditionManager;

  /**
   * Constructs a GameEventLogger.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Database\Connection|null $database
   *   Optional database connection for dc_campaign_log writes/reads.
   * @param \Drupal\dungeoncrawler_content\Service\ConditionManager|null $condition_manager
   *   Optional condition manager to drain pending condition-change events from.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    ?Connection $database = NULL,
    ?ConditionManager $condition_manager = NULL
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->database = $database;
    $this->conditionManager = $condition_manager;
  }

  /**
   * Appends one or more events to the dungeon_data event log.
   *
   * @param array &$dungeon_data
   *   The full dungeon_data payload (mutated in place).
   * @param array $events
   *   Array of event arrays, each with keys:
   *   - type: string (e.g., 'strike', 'room_entered', 'phase_transition')
   *   - phase: string (current game phase)
   *   - actor: string|null (entity ID of the acting entity)
   *   - target: string|null (entity ID of the target, if any)
   *   - data: array (action-specific data)
   *   - narration: string|null (AI GM narration text)
   *
   * @return array
   *   The events as they were logged (with id and timestamp added).
   */
  public function logEvents(array &$dungeon_data, array $events): array {
    if (!isset($dungeon_data['event_log'])) {
      $dungeon_data['event_log'] = [];
    }

    // Always drain any condition-change events (applied/updated/removed)
    // that ConditionManager queued while resolving this action, regardless
    // of which of its ~40 call sites across the codebase triggered them.
    // Appended after the action's own events so they read as a consequence
    // of it (e.g. "...strikes for 6 damage. Skeleton becomes frightened 2.").
    if ($this->conditionManager) {
      $condition_events = $this->conditionManager->drainPendingConditionEvents();
      if ($condition_events !== []) {
        $events = array_merge($events, $condition_events);
      }
    }

    $logged = [];
    $campaign_id = $this->resolveCampaignId($dungeon_data);
    $next_id = $this->getLatestCursor($dungeon_data, $campaign_id) + 1;

    foreach ($events as $event) {
      if (!is_array($event)) {
        continue;
      }
      $record = $this->normalizeEvent($event);
      $record['id'] = $next_id++;
      if ($campaign_id !== NULL && $this->database) {
        $persisted_id = $this->insertPersistentEventRow($campaign_id, $record);
        if ($persisted_id !== NULL) {
          $record['id'] = $persisted_id;
          $next_id = $persisted_id + 1;
        }
      }
      $this->emitConditionTelemetryLogs($campaign_id, $record);

      $dungeon_data['event_log'][] = $record;
      $logged[] = $record;
    }

    // Cap the event log to prevent unbounded growth.
    if (count($dungeon_data['event_log']) > self::MAX_EVENTS) {
      $dungeon_data['event_log'] = array_slice(
        $dungeon_data['event_log'],
        -self::MAX_EVENTS
      );
      // Re-index to maintain a clean array (not sparse).
      $dungeon_data['event_log'] = array_values($dungeon_data['event_log']);
    }

    // Update the cursor to point to the latest event.
    if (isset($dungeon_data['game_state']) && is_array($dungeon_data['game_state'])) {
      $dungeon_data['game_state']['event_log_cursor'] = !empty($logged)
        ? (int) end($logged)['id']
        : (int) ($dungeon_data['game_state']['event_log_cursor'] ?? 0);
    }

    return $logged;
  }

  /**
   * Retrieves events since a given cursor (for polling).
   *
   * @param array $dungeon_data
   *   The full dungeon_data payload.
   * @param int $since_cursor
   *   The event ID to start from (exclusive).
   *
   * @return array
   *   Events with id > $since_cursor.
   */
  public function getEventsSince(array $dungeon_data, int $since_cursor, ?int $campaign_id = NULL): array {
    $campaign_id = $campaign_id ?? $this->resolveCampaignId($dungeon_data);
    if ($campaign_id !== NULL && $this->database) {
      return $this->getPersistentEventsSince($campaign_id, $since_cursor);
    }

    if (empty($dungeon_data['event_log'])) {
      return [];
    }

    return array_values(array_filter(
      $dungeon_data['event_log'],
      function ($event) use ($since_cursor) {
        return ($event['id'] ?? 0) > $since_cursor;
      }
    ));
  }

  /**
   * Gets the last N events (for AI context window).
   *
   * @param array $dungeon_data
   *   The full dungeon_data payload.
   * @param int $count
   *   Number of recent events to return.
   *
   * @return array
   *   The last $count events.
   */
  public function getRecentEvents(array $dungeon_data, int $count = 20): array {
    $campaign_id = $this->resolveCampaignId($dungeon_data);
    if ($campaign_id !== NULL && $this->database) {
      return $this->getPersistentRecentEvents($campaign_id, $count);
    }

    if (empty($dungeon_data['event_log'])) {
      return [];
    }

    return array_slice($dungeon_data['event_log'], -$count);
  }

  /**
   * Return the latest known event cursor.
   */
  public function getLatestCursor(array $dungeon_data, ?int $campaign_id = NULL): int {
    if ($campaign_id !== NULL && $this->database) {
      try {
        $query = $this->database->select('dc_campaign_log', 'l')
          ->condition('campaign_id', $campaign_id)
          ->condition('log_type', 'game_event');
        $query->addExpression('MAX(id)', 'max_id');
        $max_id = $query
          ->execute()
          ->fetchField();
        return max(0, (int) $max_id);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Failed to load latest event cursor for campaign @id: @err', [
          '@id' => $campaign_id,
          '@err' => $e->getMessage(),
        ]);
      }
    }

    $event_log = $dungeon_data['event_log'] ?? [];
    if ($event_log === []) {
      return 0;
    }
    $last_event = end($event_log);
    return max(0, (int) ($last_event['id'] ?? 0));
  }

  /**
   * Resolve campaign id from hydrated dungeon payload.
   */
  protected function resolveCampaignId(array $dungeon_data): ?int {
    $campaign_id = (int) ($dungeon_data['campaign_id'] ?? 0);
    return $campaign_id > 0 ? $campaign_id : NULL;
  }

  /**
   * Normalize event payload to stable types.
   */
  protected function normalizeEvent(array $event, bool $strict = TRUE): array {
    $actor = $event['actor'] ?? NULL;
    $target = $event['target'] ?? NULL;
    $phase = trim((string) ($event['phase'] ?? ''));
    $type = trim((string) ($event['type'] ?? ''));
    $data = $event['data'] ?? [];

    if ($strict) {
      if ($phase === '') {
        throw new \InvalidArgumentException('Game event contract violation: missing non-empty phase.');
      }
      if ($type === '') {
        throw new \InvalidArgumentException('Game event contract violation: missing non-empty type.');
      }
      if (!is_array($data)) {
        throw new \InvalidArgumentException('Game event contract violation: data must be an array.');
      }
    }

    return [
      'timestamp' => is_string($event['timestamp'] ?? NULL) && trim((string) $event['timestamp']) !== ''
        ? trim((string) $event['timestamp'])
        : date('c'),
      'phase' => $phase !== '' ? $phase : 'unknown',
      'type' => $type !== '' ? $type : 'unknown',
      'actor' => $actor === NULL ? NULL : (string) $actor,
      'target' => $target === NULL ? NULL : (string) $target,
      'data' => is_array($data) ? $data : [],
      'narration' => isset($event['narration']) ? (string) $event['narration'] : NULL,
    ];
  }

  /**
   * Insert event into dc_campaign_log and return row id.
   */
  protected function insertPersistentEventRow(int $campaign_id, array $record): ?int {
    try {
      $context = $record;
      $context_json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($context_json)) {
        throw new \RuntimeException('Failed to encode game_event context JSON.');
      }
      $data = is_array($record['data'] ?? NULL) ? $record['data'] : [];
      $encounter_instance_id = trim((string) ($data['encounter_instance_id'] ?? $data['encounter_id'] ?? ''));
      $room_id = trim((string) ($data['room_id'] ?? $data['active_room_id'] ?? ''));
      $id = $this->database->insert('dc_campaign_log')
        ->fields([
          'campaign_id' => $campaign_id,
          'log_type' => 'game_event',
          'message' => (string) ($record['type'] ?? 'unknown'),
          'context' => $context_json,
          'encounter_instance_id' => $encounter_instance_id !== '' ? $encounter_instance_id : NULL,
          'room_id' => $room_id !== '' ? $room_id : NULL,
          'created' => time(),
        ])
        ->execute();
      return is_numeric($id) ? (int) $id : NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to persist game event for campaign @id: @err', [
        '@id' => $campaign_id,
        '@err' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Load persistent game events from dc_campaign_log.
   *
   * @return array<int, array<string,mixed>>
   */
  protected function getPersistentEventsSince(int $campaign_id, int $since_cursor): array {
    try {
      $rows = $this->database->select('dc_campaign_log', 'l')
        ->fields('l', ['id', 'context', 'created'])
        ->condition('campaign_id', $campaign_id)
        ->condition('log_type', 'game_event')
        ->condition('id', $since_cursor, '>')
        ->orderBy('id', 'ASC')
        ->range(0, self::MAX_EVENTS * 2)
        ->execute()
        ->fetchAllAssoc('id');
      $events = [];
      foreach ($rows as $id => $row) {
        $events[] = $this->decodePersistentEventRow($row, (int) $id);
      }
      return $events;
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to fetch game events for campaign @id: @err', [
        '@id' => $campaign_id,
        '@err' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Load persistent recent game events from dc_campaign_log.
   *
   * @return array<int, array<string,mixed>>
   */
  protected function getPersistentRecentEvents(int $campaign_id, int $count): array {
    try {
      $rows = $this->database->select('dc_campaign_log', 'l')
        ->fields('l', ['id', 'context', 'created'])
        ->condition('campaign_id', $campaign_id)
        ->condition('log_type', 'game_event')
        ->orderBy('id', 'DESC')
        ->range(0, max(1, $count))
        ->execute()
        ->fetchAllAssoc('id');
      $events = [];
      foreach ($rows as $id => $row) {
        $events[] = $this->decodePersistentEventRow($row, (int) $id);
      }
      return array_reverse($events);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to fetch recent game events for campaign @id: @err', [
        '@id' => $campaign_id,
        '@err' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Decode dc_campaign_log context row into canonical event shape.
   */
  protected function decodePersistentEventRow(object $row, int $fallback_id): array {
    $context = json_decode((string) ($row->context ?? ''), TRUE);
    if (!is_array($context)) {
      $context = [];
    }
    $normalized = $this->normalizeEvent($context, FALSE);
    $normalized['id'] = $fallback_id;
    if (trim((string) ($normalized['timestamp'] ?? '')) === '') {
      $normalized['timestamp'] = date('c', (int) ($row->created ?? time()));
    }
    return $normalized;
  }

  /**
   * Emit structured application logs for condition state-effect changes.
   */
  protected function emitConditionTelemetryLogs(?int $campaign_id, array $record): void {
    $event_data = is_array($record['data'] ?? NULL) ? $record['data'] : [];
    foreach ($this->extractStateEffectPackets($event_data) as $packet) {
      $effect_kind = strtolower(trim((string) ($packet['effect_kind'] ?? '')));
      if ($effect_kind !== 'condition') {
        continue;
      }
      $this->logger->notice(
        'Condition change event: campaign=@campaign event_id=@event_id event_type=@event_type actor=@actor target=@target condition=@condition change_type=@change_type value=@value encounter=@encounter',
        [
          '@campaign' => $campaign_id !== NULL ? $campaign_id : 0,
          '@event_id' => (int) ($record['id'] ?? 0),
          '@event_type' => (string) ($record['type'] ?? ''),
          '@actor' => (string) ($packet['actor_entity_ref'] ?? ''),
          '@target' => (string) ($packet['target_entity_ref'] ?? ''),
          '@condition' => (string) ($packet['effect_name'] ?? ''),
          '@change_type' => (string) ($packet['change_type'] ?? ''),
          '@value' => is_numeric($packet['value'] ?? NULL) ? (int) $packet['value'] : 'n/a',
          '@encounter' => (string) (
            $event_data['encounter_instance_id']
            ?? $event_data['encounter_id']
            ?? ($event_data['execution_request']['encounter_id'] ?? '')
          ),
        ]
      );
    }
  }

  /**
   * Extract canonical state-effect packets from known event-data envelopes.
   *
   * @return array<int, array<string,mixed>>
   *   Normalized packet list.
   */
  protected function extractStateEffectPackets(array $event_data): array {
    $packets = [];
    $envelope_keys = [
      'resolution_envelope',
      'strike_resolution_envelope',
      'spell_resolution_envelope',
      'hazard_resolution_envelope',
    ];
    foreach ($envelope_keys as $envelope_key) {
      $envelope = is_array($event_data[$envelope_key] ?? NULL) ? $event_data[$envelope_key] : NULL;
      if (!is_array($envelope)) {
        continue;
      }
      foreach ((array) ($envelope['packets'] ?? []) as $packet) {
        if (!is_array($packet)) {
          continue;
        }
        if (strtolower(trim((string) ($packet['kind'] ?? ''))) !== 'state_effect_change') {
          continue;
        }
        $packets[] = $packet;
      }
    }

    foreach ((array) ($event_data['state_effect_packets'] ?? []) as $packet) {
      if (!is_array($packet)) {
        continue;
      }
      if (strtolower(trim((string) ($packet['kind'] ?? ''))) !== 'state_effect_change') {
        continue;
      }
      $packets[] = $packet;
    }

    if (is_array($event_data['state_effect_packet'] ?? NULL)) {
      $packet = $event_data['state_effect_packet'];
      if (strtolower(trim((string) ($packet['kind'] ?? ''))) === 'state_effect_change') {
        $packets[] = $packet;
      }
    }

    return array_values($packets);
  }

  /**
   * Builds a single event array from parameters.
   *
   * Convenience method for constructing event payloads.
   *
   * @param string $type
   *   Event type (e.g., 'strike', 'move', 'phase_transition').
   * @param string $phase
   *   Current game phase.
   * @param string|null $actor
   *   Entity ID of the actor.
   * @param array $data
   *   Action-specific data.
   * @param string|null $narration
   *   Optional AI GM narration.
   * @param string|null $target
   *   Optional target entity ID.
   *
   * @return array
   *   Event array ready for logEvents().
   */
  public static function buildEvent(
    string $type,
    string $phase,
    ?string $actor = NULL,
    array $data = [],
    ?string $narration = NULL,
    ?string $target = NULL
  ): array {
    return [
      'type' => $type,
      'phase' => $phase,
      'actor' => $actor,
      'target' => $target,
      'data' => $data,
      'narration' => $narration,
    ];
  }

}
