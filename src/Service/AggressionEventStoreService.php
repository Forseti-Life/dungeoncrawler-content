<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Phase-1 persistence scaffold for aggression/combat-entry event history.
 */
class AggressionEventStoreService {

  protected const MAX_EVENTS = 250;

  protected CampaignStateService $campaignStateService;
  protected ?Connection $database;

  public function __construct(CampaignStateService $campaign_state_service, ?Connection $database = NULL) {
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
  }

  /**
   * Record one combat-entry decision event in campaign state.
   */
  public function recordCombatEntryEvent(int $campaign_id, array $event): array {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $events = is_array($state['combat_entry_events'] ?? NULL) ? array_values($state['combat_entry_events']) : [];

    $record = [
      'event_type' => 'combat_entry_decision',
      'timestamp' => time(),
      'status' => (string) ($event['status'] ?? 'unknown'),
      'room_id' => (string) ($event['room_id'] ?? ''),
      'reason' => (string) ($event['reason'] ?? ''),
      'aggression' => is_array($event['aggression'] ?? NULL) ? $event['aggression'] : [],
      'enemy_count' => max(0, (int) ($event['enemy_count'] ?? 0)),
      'encounter_id' => (int) ($event['encounter_id'] ?? 0),
    ];

    $events[] = $record;
    if (count($events) > self::MAX_EVENTS) {
      $events = array_slice($events, count($events) - self::MAX_EVENTS);
    }

    $state['combat_entry_events'] = $events;
    $version = isset($current['version']) ? (int) $current['version'] : NULL;
    $this->campaignStateService->setState($campaign_id, $state, $version);
    $this->persistEventRow($campaign_id, $record);

    return $record;
  }

  /**
   * Persist one aggression event row to canonical table when available.
   */
  protected function persistEventRow(int $campaign_id, array $record): void {
    if (!$this->database || !$this->database->schema()->tableExists('dc_aggression_events')) {
      return;
    }

    $this->database->insert('dc_aggression_events')
      ->fields([
        'campaign_id' => $campaign_id,
        'event_type' => (string) ($record['event_type'] ?? 'combat_entry_decision'),
        'room_id' => (string) ($record['room_id'] ?? ''),
        'status' => (string) ($record['status'] ?? 'unknown'),
        'reason' => (string) ($record['reason'] ?? ''),
        'aggression_json' => json_encode(is_array($record['aggression'] ?? NULL) ? $record['aggression'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'enemy_count' => (int) ($record['enemy_count'] ?? 0),
        'encounter_id' => (int) ($record['encounter_id'] ?? 0),
        'created' => (int) ($record['timestamp'] ?? time()),
      ])
      ->execute();
  }

}
