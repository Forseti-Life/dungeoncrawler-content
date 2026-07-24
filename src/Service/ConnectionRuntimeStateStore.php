<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Campaign-scoped connection runtime state persistence lane.
 */
class ConnectionRuntimeStateStore {

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * Sync connection runtime state payloads from composed connections.
   *
   * @param array<int,mixed> $connections
   *   Runtime connection payloads.
   */
  public function syncFromConnections(int $campaign_id, array $connections): void {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_connection_runtime_state')) {
      return;
    }
    $now = time();
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $connection_id = trim((string) ($connection['connection_id'] ?? ''));
      if ($connection_id === '') {
        continue;
      }

      $runtime_state = [
        'state' => trim((string) ($connection['state'] ?? 'open')) ?: 'open',
        'is_discovered' => !empty($connection['is_discovered']),
        'is_passable' => !empty($connection['is_passable']),
        'bidirectional' => !empty($connection['bidirectional']),
      ];
      $encoded = json_encode($runtime_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($encoded) || $encoded === '') {
        throw new \RuntimeException(sprintf(
          'Connection runtime state store contract violation: failed to encode runtime state for campaign %d connection %s.',
          $campaign_id,
          $connection_id
        ));
      }

      $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
      $upsert_fields = [
        'from_room_id' => $from_room_id !== '' ? $from_room_id : NULL,
        'to_room_id' => $to_room_id !== '' ? $to_room_id : NULL,
        'connection_state' => $encoded,
        'updated' => $now,
      ];
      $this->database->merge('dc_campaign_connection_runtime_state')
        ->keys([
          'campaign_id' => $campaign_id,
          'connection_id' => $connection_id,
        ])
        ->fields($upsert_fields)
        ->insertFields($upsert_fields + [
          'campaign_id' => $campaign_id,
          'connection_id' => $connection_id,
          'created' => $now,
        ])
        ->execute();
    }
  }

}
