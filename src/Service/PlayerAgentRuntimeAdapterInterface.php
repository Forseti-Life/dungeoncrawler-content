<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Adapter interface for headless player-agent runtime access.
 */
interface PlayerAgentRuntimeAdapterInterface {

  /**
   * Build an agent-friendly snapshot of the current runtime state.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $actor_id
   *   The controlled actor entity ID.
   * @param array $run_state
   *   Current in-memory run state.
   *
   * @return array
   *   Snapshot envelope.
   */
  public function buildSnapshot(int $campaign_id, string $actor_id, array $run_state = []): array;

  /**
   * Submit a canonical gameplay intent.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param array $intent
   *   Canonical intent payload for GameCoordinatorService::processAction().
   *
   * @return array
   *   Gameplay response envelope.
   */
  public function submitIntent(int $campaign_id, array $intent): array;

}
