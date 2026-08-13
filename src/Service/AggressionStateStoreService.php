<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Canonical persistence scaffold for latest aggression/combat-entry state.
 */
class AggressionStateStoreService {

  protected CampaignStateService $campaignStateService;
  protected ?Connection $database;

  public function __construct(CampaignStateService $campaign_state_service, ?Connection $database = NULL) {
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
  }

  /**
   * Persist the latest aggression state snapshot for a room context.
   */
  public function storeLatestState(
    int $campaign_id,
    string $room_id,
    string $status,
    array $aggression_summary = [],
    array $combat_entry_summary = []
  ): array {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $registry = is_array($state['aggression_state'] ?? NULL) ? $state['aggression_state'] : [];

    $room_key = trim($room_id) !== '' ? trim($room_id) : '_unknown';
    $snapshot = [
      'status' => $status,
      'room_id' => trim($room_id),
      'updated_at' => time(),
      'aggression_summary' => $aggression_summary,
      'combat_entry_summary' => $combat_entry_summary,
    ];
    $registry[$room_key] = $snapshot;
    $state['aggression_state'] = $registry;

    $version = isset($current['version']) ? (int) $current['version'] : NULL;
    $this->campaignStateService->setState($campaign_id, $state, $version);
    $this->persistStateRow($campaign_id, $snapshot);

    return $snapshot;
  }

  /**
   * Load latest aggression snapshot for a room context when available.
   */
  public function loadLatestState(int $campaign_id, string $room_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $room_key = trim($room_id) !== '' ? trim($room_id) : '_unknown';
    if ($this->database && $this->database->schema()->tableExists('dc_aggression_state')) {
      $row = $this->database->select('dc_aggression_state', 's')
        ->fields('s', ['room_id', 'status', 'aggression_summary_json', 'combat_entry_summary_json', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_key)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (is_array($row)) {
        return [
          'status' => (string) ($row['status'] ?? 'unknown'),
          'room_id' => (string) ($row['room_id'] ?? $room_key),
          'updated_at' => (int) ($row['updated'] ?? 0),
          'aggression_summary' => $this->decodeJsonObject($row['aggression_summary_json'] ?? '', []),
          'combat_entry_summary' => $this->decodeJsonObject($row['combat_entry_summary_json'] ?? '', []),
        ];
      }
    }

    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $registry = is_array($state['aggression_state'] ?? NULL) ? $state['aggression_state'] : [];
    $entry = $registry[$room_key] ?? NULL;
    if (!is_array($entry)) {
      return NULL;
    }

    return [
      'status' => (string) ($entry['status'] ?? 'unknown'),
      'room_id' => (string) ($entry['room_id'] ?? $room_key),
      'updated_at' => (int) ($entry['updated_at'] ?? 0),
      'aggression_summary' => is_array($entry['aggression_summary'] ?? NULL) ? $entry['aggression_summary'] : [],
      'combat_entry_summary' => is_array($entry['combat_entry_summary'] ?? NULL) ? $entry['combat_entry_summary'] : [],
    ];
  }

  /**
   * Persist latest aggression state snapshot to canonical table when available.
   */
  protected function persistStateRow(int $campaign_id, array $snapshot): void {
    if (!$this->database || !$this->database->schema()->tableExists('dc_aggression_state')) {
      return;
    }

    $this->database->merge('dc_aggression_state')
      ->keys([
        'campaign_id' => $campaign_id,
        'room_id' => (string) ($snapshot['room_id'] ?? ''),
      ])
      ->fields([
        'status' => (string) ($snapshot['status'] ?? 'unknown'),
        'aggression_summary_json' => json_encode(is_array($snapshot['aggression_summary'] ?? NULL) ? $snapshot['aggression_summary'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'combat_entry_summary_json' => json_encode(is_array($snapshot['combat_entry_summary'] ?? NULL) ? $snapshot['combat_entry_summary'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated' => (int) ($snapshot['updated_at'] ?? time()),
      ])
      ->execute();
  }

  /**
   * Decode JSON payload with array fallback.
   *
   * @param mixed $raw
   *   Raw JSON payload.
   * @param array<string, mixed> $fallback
   *   Fallback when decode fails.
   *
   * @return array<string, mixed>
   *   Decoded object payload.
   */
  protected function decodeJsonObject(mixed $raw, array $fallback = []): array {
    if (is_array($raw)) {
      return $raw;
    }
    if (!is_string($raw) || trim($raw) === '') {
      return $fallback;
    }
    $decoded = json_decode($raw, TRUE);
    return is_array($decoded) ? $decoded : $fallback;
  }

}
