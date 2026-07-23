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
   * Mutate one dungeon snapshot row while rebuilding graph structure from authority.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param int $dungeon_row_id
   *   Campaign dungeon row id.
   * @param callable $mutator
   *   Receives the decoded payload and must return the mutated payload array.
   *
   * @return bool
   *   TRUE when one row was updated.
   */
  public function mutateByRowId(int $campaign_id, int $dungeon_row_id, callable $mutator): bool {
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

    $rebuilt = $this->runtimeGraphAssembler->buildRuntimeGraph(
      $campaign_id,
      (string) ($row['dungeon_id'] ?? ''),
      $mutated,
      [
        'active_room_id' => trim((string) ($mutated['active_room_id'] ?? $payload['active_room_id'] ?? '')),
      ]
    );

    $encoded = json_encode($rebuilt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || $encoded === '') {
      throw new \RuntimeException('Dungeon payload state persistence contract violation: failed to encode rebuilt payload.');
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
  public function mutateByDungeonId(int $campaign_id, string $dungeon_id, callable $mutator): bool {
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

    return $this->mutateByRowId($campaign_id, (int) $row['id'], $mutator);
  }

}
