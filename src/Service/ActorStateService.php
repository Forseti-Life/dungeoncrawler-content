<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical current-state retrieval owner for actors.
 *
 * This service is the single actor-state read authority exposed to
 * ObjectStateService. Internal composition can evolve without changing the
 * object-state gateway contract.
 */
class ActorStateService {

  public function __construct(
    protected CharacterStateService $characterStateService,
  ) {}

  /**
   * Retrieve canonical current actor state.
   *
   * @return array<string,mixed>
   *   Canonical actor state payload.
   */
  public function getState(
    string $actor_id,
    ?int $campaign_id = NULL,
    ?string $instance_id = NULL
  ): array {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      throw new \InvalidArgumentException('Actor id is required.');
    }

    return $this->characterStateService->getState($actor_id, $campaign_id, $instance_id);
  }

}
