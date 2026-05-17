<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Phase-specific decision policy for the player agent.
 */
interface PlayerAgentPolicyInterface {

  /**
   * Returns TRUE when this policy can handle the current phase.
   */
  public function supportsPhase(string $phase): bool;

  /**
   * Choose the next agent action.
   *
   * @param array $profile
   *   Normalized player-agent profile.
   * @param array $snapshot
   *   Current runtime snapshot.
   * @param array $run_state
   *   Current in-memory run state.
   *
   * @return array
   *   Decision envelope. Either:
   *   - ['type' => 'intent', 'intent' => [...], 'reason' => '...']
   *   - ['type' => 'wait', 'reason' => '...']
   */
  public function chooseAction(array $profile, array $snapshot, array $run_state): array;

}
