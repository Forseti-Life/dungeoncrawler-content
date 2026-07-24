<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Campaign-scoped room runtime state persistence lane.
 */
class RoomRuntimeStateStore {

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * Sync room runtime state payloads from composed rooms.
   *
   * @param array<int,mixed> $rooms
   *   Runtime room payloads.
   */
  public function syncFromRooms(int $campaign_id, array $rooms): void {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_room_runtime_state')) {
      return;
    }
    $now = time();
    foreach ($rooms as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        continue;
      }
      $state = is_array($room['state'] ?? NULL) ? $room['state'] : [];
      $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($encoded) || $encoded === '') {
        throw new \RuntimeException(sprintf(
          'Room runtime state store contract violation: failed to encode room state for campaign %d room %s.',
          $campaign_id,
          $room_id
        ));
      }
      $this->database->merge('dc_campaign_room_runtime_state')
        ->keys([
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
        ])
        ->fields([
          'room_state' => $encoded,
          'updated' => $now,
        ])
        ->insertFields([
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'room_state' => $encoded,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
  }

}
