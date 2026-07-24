<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Applies mutable-state updates without taking graph authority from snapshots.
 */
class DungeonPayloadStatePersistenceService {

  public function __construct(
    protected readonly Connection $database,
    protected readonly RuntimeGraphAssemblerService $runtimeGraphAssembler,
  ) {}

  /**
   * Mutate one dungeon snapshot row.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param int $dungeon_row_id
   *   Campaign dungeon row id.
   * @param callable $mutator
   *   Receives the decoded payload and must return the mutated payload array.
   *
   * @param array<string, mixed> $options
   *   Optional persistence options:
   *   - rebuild_graph (bool): TRUE to rebuild graph from authority (default).
   *   - room_batch_size (int): batch size passed to runtime graph assembly.
   *
   * @return bool
   *   TRUE when one row was updated.
   */
  public function mutateByRowId(int $campaign_id, int $dungeon_row_id, callable $mutator, array $options = []): bool {
    if ($campaign_id <= 0 || $dungeon_row_id <= 0) {
      return FALSE;
    }

    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_id', 'dungeon_data'])
      ->condition('id', $dungeon_row_id)
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return FALSE;
    }

    $payload = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($payload)) {
      $payload = [];
    }

    $mutated = $mutator($payload);
    if (!is_array($mutated)) {
      throw new \RuntimeException('Dungeon payload state persistence contract violation: mutator must return an array payload.');
    }

    $rebuild_graph = !array_key_exists('rebuild_graph', $options) || !empty($options['rebuild_graph']);
    $persist_payload = $mutated;
    if ($rebuild_graph) {
      $persist_payload = $this->runtimeGraphAssembler->buildRuntimeGraph(
        $campaign_id,
        (string) ($row['dungeon_id'] ?? ''),
        $mutated,
        [
          'active_room_id' => trim((string) ($mutated['active_room_id'] ?? $payload['active_room_id'] ?? '')),
          'room_batch_size' => max(1, (int) ($options['room_batch_size'] ?? 8)),
        ]
      );
    }

    $persist_payload = $this->normalizePersistedPayload($persist_payload);
    $encoded = json_encode($persist_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || $encoded === '') {
      throw new \RuntimeException('Dungeon payload state persistence contract violation: failed to encode persisted payload.');
    }

    $updated = (int) $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => $encoded,
        'updated' => time(),
      ])
      ->condition('id', $dungeon_row_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    return $updated === 1;
  }

  /**
   * Mutate one dungeon snapshot row addressed by campaign + dungeon id.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param string $dungeon_id
   *   Dungeon identifier.
   * @param callable $mutator
   *   Receives the decoded payload and must return the mutated payload array.
   *
   * @return bool
   *   TRUE when one row was updated.
   */
  public function mutateByDungeonId(int $campaign_id, string $dungeon_id, callable $mutator, array $options = []): bool {
    $dungeon_id = trim($dungeon_id);
    if ($campaign_id <= 0 || $dungeon_id === '') {
      return FALSE;
    }

    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row) || empty($row['id'])) {
      return FALSE;
    }

    return $this->mutateByRowId($campaign_id, (int) $row['id'], $mutator, $options);
  }

  /**
   * Mutate one dungeon snapshot row without graph reconstruction.
   *
   * This is for runtime-only state updates (chat/effects/encounter flags) where
   * structural room/connection authority is unchanged.
   */
  public function mutateStateByRowId(int $campaign_id, int $dungeon_row_id, callable $mutator): bool {
    return $this->mutateByRowId($campaign_id, $dungeon_row_id, $mutator, [
      'rebuild_graph' => FALSE,
    ]);
  }

  /**
   * Mutate one dungeon snapshot by campaign+dungeon id without graph rebuild.
   */
  public function mutateStateByDungeonId(int $campaign_id, string $dungeon_id, callable $mutator): bool {
    return $this->mutateByDungeonId($campaign_id, $dungeon_id, $mutator, [
      'rebuild_graph' => FALSE,
    ]);
  }

  /**
   * Normalize stored dungeon payloads to identifier-only graph membership.
   *
   * Runtime rows persist graph identifiers + metadata; composed room/connection
   * payloads are rebuilt from authority tables on read.
   *
   * @param array<string, mixed> $payload
   *   Candidate persisted payload.
   *
   * @return array<string, mixed>
   *   Storage-normalized payload.
   */
  protected function normalizePersistedPayload(array $payload): array {
    $room_ids = [];
    foreach ((array) ($payload['room_ids'] ?? []) as $room_id) {
      $room_id = trim((string) $room_id);
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    if ($room_ids === [] && is_array($payload['rooms'] ?? NULL)) {
      foreach ($payload['rooms'] as $room) {
        if (!is_array($room)) {
          continue;
        }
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id !== '') {
          $room_ids[$room_id] = TRUE;
        }
      }
    }

    $connection_ids = [];
    foreach ((array) ($payload['connection_ids'] ?? []) as $connection_id) {
      $connection_id = trim((string) $connection_id);
      if ($connection_id !== '') {
        $connection_ids[$connection_id] = TRUE;
      }
    }
    if ($connection_ids === []) {
      foreach ([
        $payload['connections'] ?? [],
        $payload['hex_map']['connections'] ?? [],
      ] as $bucket) {
        foreach ((array) $bucket as $connection) {
          if (!is_array($connection)) {
            continue;
          }
          $connection_id = trim((string) ($connection['connection_id'] ?? ''));
          if ($connection_id !== '') {
            $connection_ids[$connection_id] = TRUE;
          }
        }
      }
    }

    $payload['room_ids'] = array_values(array_keys($room_ids));
    $payload['connection_ids'] = array_values(array_keys($connection_ids));
    if ($payload['room_ids'] === []) {
      throw new \RuntimeException('Dungeon payload state persistence contract violation: room_ids cannot be empty.');
    }
    $active_room_id = trim((string) ($payload['active_room_id'] ?? ''));
    if ($active_room_id !== '' && !in_array($active_room_id, $payload['room_ids'], TRUE)) {
      throw new \RuntimeException(sprintf(
        'Dungeon payload state persistence contract violation: active_room_id %s is not present in room_ids.',
        $active_room_id
      ));
    }

    unset($payload['rooms'], $payload['connections'], $payload['entities']);
    if (isset($payload['hex_map']) && is_array($payload['hex_map'])) {
      unset($payload['hex_map']['connections']);
    }

    return $payload;
  }

}
