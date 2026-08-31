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
   * REQ (2026-08-31 RCA, campaign 916): room payloads built by
   * RuntimeGraphAssemblerService::buildRoomPayload() carry their durable
   * per-room flags (e.g. encounter_triggered) under the "gameplay_state"
   * key -- there is no "state" key on these payloads. Reading "state" here
   * silently persisted an empty array on every write, meaning
   * gameplay_state mutations (like markRoomEncounterTriggered()) never
   * actually survived a request boundary, causing cleared encounters to
   * re-trigger on every room re-entry.
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
      $state = is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : [];
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

  /**
   * Load persisted room gameplay_state payloads for a campaign.
   *
   * REQ (2026-08-31 RCA, campaign 916): nothing previously read this table
   * back into the runtime graph's snapshot-room lookup
   * (RuntimeGraphAssemblerService::indexSnapshotRooms() only ever sees
   * whatever "rooms" already happen to be present on the in-memory
   * base_payload, which is empty on every fresh bootstrap/read). Without
   * this read, durable per-room flags such as encounter_triggered could
   * never survive a request boundary even after the write-side key bug is
   * fixed. Callers should merge the returned rows into
   * $dungeon_data['rooms'] (keyed by room_id) before calling
   * RuntimeGraphAssemblerService::buildRuntimeGraph().
   *
   * @return array<string,array{room_id:string,gameplay_state:array<string,mixed>}>
   *   Minimal room snapshot rows keyed by room_id.
   */
  public function loadRoomStates(int $campaign_id): array {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_room_runtime_state')) {
      return [];
    }
    $rows = $this->database->select('dc_campaign_room_runtime_state', 'r')
      ->fields('r', ['room_id', 'room_state'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAll();

    $result = [];
    foreach ($rows as $row) {
      $room_id = trim((string) ($row->room_id ?? ''));
      if ($room_id === '') {
        continue;
      }
      $decoded = json_decode((string) ($row->room_state ?? '{}'), TRUE);
      $result[$room_id] = [
        'room_id' => $room_id,
        'gameplay_state' => is_array($decoded) ? $decoded : [],
      ];
    }
    return $result;
  }

}
