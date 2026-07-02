<?php

namespace Drupal\dungeoncrawler_content\Service;

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
   * Constructs a GameEventLogger.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('dungeoncrawler');
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

    // Determine the next event ID.
    $next_id = 1;
    if (!empty($dungeon_data['event_log'])) {
      $last_event = end($dungeon_data['event_log']);
      $next_id = ($last_event['id'] ?? 0) + 1;
    }

    $logged = [];
    $timestamp = date('c');

    foreach ($events as $event) {
      $record = [
        'id' => $next_id++,
        'timestamp' => $timestamp,
        'phase' => $event['phase'] ?? 'unknown',
        'type' => $event['type'] ?? 'unknown',
        'actor' => $event['actor'] ?? NULL,
        'target' => $event['target'] ?? NULL,
        'data' => $event['data'] ?? [],
        'narration' => $event['narration'] ?? NULL,
      ];

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
    if (isset($dungeon_data['game_state'])) {
      $dungeon_data['game_state']['event_log_cursor'] = $next_id - 1;
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
  public function getEventsSince(array $dungeon_data, int $since_cursor): array {
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
    if (empty($dungeon_data['event_log'])) {
      return [];
    }

    return array_slice($dungeon_data['event_log'], -$count);
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
