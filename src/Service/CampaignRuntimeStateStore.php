<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Campaign-scoped runtime state persistence lane.
 */
class CampaignRuntimeStateStore {

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * Load persisted runtime game_state for a campaign.
   *
   * @return array<string,mixed>|null
   *   Decoded runtime state, or NULL when no state row exists.
   */
  public function loadGameState(int $campaign_id): ?array {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_runtime_state')) {
      return NULL;
    }

    $row = $this->database->select('dc_campaign_runtime_state', 's')
      ->fields('s', ['game_state'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      return NULL;
    }

    $decoded = json_decode((string) ($row['game_state'] ?? '{}'), TRUE);
    if (!is_array($decoded)) {
      throw new \RuntimeException(sprintf(
        'Campaign runtime state store contract violation: campaign %d game_state payload is invalid JSON.',
        $campaign_id
      ));
    }

    return $decoded;
  }

  /**
   * Persist runtime game_state for a campaign.
   */
  public function persistGameState(int $campaign_id, array $game_state, ?string $active_room_id = NULL): bool {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_runtime_state')) {
      return FALSE;
    }

    $encoded = json_encode($game_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || $encoded === '') {
      throw new \RuntimeException(sprintf(
        'Campaign runtime state store contract violation: failed to encode game_state for campaign %d.',
        $campaign_id
      ));
    }

    $resolved_active_room_id = trim((string) ($active_room_id ?? ($game_state['encounter_context']['room_id'] ?? '')));
    if ($resolved_active_room_id === '') {
      $resolved_active_room_id = NULL;
    }

    $now = time();
    $this->database->merge('dc_campaign_runtime_state')
      ->keys(['campaign_id' => $campaign_id])
      ->fields([
        'game_state' => $encoded,
        'state_version' => max(1, (int) ($game_state['state_version'] ?? 1)),
        'active_room_id' => $resolved_active_room_id,
        'updated' => $now,
      ])
      ->insertFields([
        'campaign_id' => $campaign_id,
        'game_state' => $encoded,
        'state_version' => max(1, (int) ($game_state['state_version'] ?? 1)),
        'active_room_id' => $resolved_active_room_id,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    return TRUE;
  }

}
