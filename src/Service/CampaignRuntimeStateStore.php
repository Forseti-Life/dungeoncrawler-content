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
      ->fields('s', ['game_state', 'active_room_id'])
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

    $active_room_id = trim((string) ($row['active_room_id'] ?? ''));
    if ($active_room_id !== '' && trim((string) ($decoded['active_room_id'] ?? '')) === '') {
      $decoded['active_room_id'] = $active_room_id;
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

    $resolved_active_room_id = trim((string) (
      $active_room_id
      ?? ($game_state['active_room_id'] ?? NULL)
      ?? ($game_state['encounter_context']['room_id'] ?? '')
    ));
    if ($resolved_active_room_id === '') {
      $resolved_active_room_id = NULL;
    }

    $incoming_state_version = max(1, (int) ($game_state['state_version'] ?? 1));
    $existing_row = $this->database->select('dc_campaign_runtime_state', 's')
      ->fields('s', ['state_version', 'active_room_id'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($existing_row)) {
      $existing_state_version = max(1, (int) ($existing_row['state_version'] ?? 1));
      $existing_active_room_id = trim((string) ($existing_row['active_room_id'] ?? ''));
      if ($incoming_state_version < $existing_state_version) {
        throw new \RuntimeException(sprintf(
          'Campaign runtime state store contract violation: refusing stale downgrade for campaign %d (incoming version=%d, persisted version=%d).',
          $campaign_id,
          $incoming_state_version,
          $existing_state_version
        ));
      }
      if (
        $incoming_state_version === $existing_state_version
        && $existing_active_room_id !== ''
        && $resolved_active_room_id !== NULL
        && $existing_active_room_id !== $resolved_active_room_id
      ) {
        throw new \RuntimeException(sprintf(
          'Campaign runtime state store contract violation: refusing conflicting same-version room rewrite for campaign %d (version=%d, persisted room=%s, incoming room=%s).',
          $campaign_id,
          $incoming_state_version,
          $existing_active_room_id,
          $resolved_active_room_id
        ));
      }
    }

    $now = time();
    $this->database->merge('dc_campaign_runtime_state')
      ->keys(['campaign_id' => $campaign_id])
      ->fields([
        'game_state' => $encoded,
        'state_version' => $incoming_state_version,
        'active_room_id' => $resolved_active_room_id,
        'updated' => $now,
      ])
      ->insertFields([
        'campaign_id' => $campaign_id,
        'game_state' => $encoded,
        'state_version' => $incoming_state_version,
        'active_room_id' => $resolved_active_room_id,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    return TRUE;
  }

}
