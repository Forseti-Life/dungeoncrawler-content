<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Refreshes the server-managed dungeon delivery snapshot from graph authority.
 */
class DungeonSnapshotRefresherService {

  public function __construct(
    protected readonly Connection $database,
    protected readonly RuntimeGraphAssemblerService $runtimeGraphAssembler,
  ) {}

  /**
   * Refresh one campaign dungeon snapshot when the rebuilt payload changed.
   *
   * @param int $campaign_dungeon_row_id
   *   Campaign dungeon row identifier.
   * @param int $campaign_id
   *   Campaign identifier.
   * @param string $dungeon_id
   *   Dungeon identifier.
   * @param array<string, mixed> $base_payload
   *   Current payload used only for non-graph compatibility fields.
   * @param string $original_encoded_payload
   *   Original stored JSON payload.
   * @param array<string, mixed> $options
   *   Optional runtime graph assembly overrides.
   */
  public function refreshIfChanged(
    int $campaign_dungeon_row_id,
    int $campaign_id,
    string $dungeon_id,
    array $base_payload,
    string $original_encoded_payload,
    array $options = []
  ): bool {
    if ($campaign_dungeon_row_id <= 0 || $campaign_id <= 0 || trim($dungeon_id) === '') {
      return FALSE;
    }

    $rebuilt_payload = $this->runtimeGraphAssembler->buildRuntimeGraph(
      $campaign_id,
      $dungeon_id,
      $base_payload,
      $options
    );
    $encoded_payload = json_encode($rebuilt_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded_payload) || $encoded_payload === '' || $encoded_payload === $original_encoded_payload) {
      return FALSE;
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => $encoded_payload,
        'updated' => time(),
      ])
      ->condition('id', $campaign_dungeon_row_id)
      ->execute();
    return TRUE;
  }

}
