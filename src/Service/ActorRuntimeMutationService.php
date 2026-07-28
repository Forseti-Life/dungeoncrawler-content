<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Typed mutation lane for actor runtime state writes.
 */
class ActorRuntimeMutationService {

  public function __construct(
    protected readonly ActorRuntimeStateStore $actorRuntimeStateStore,
  ) {}

  /**
   * Persist actor runtime payloads for a campaign.
   *
   * @param array<int,mixed> $entities
   *   Actor payload rows.
   */
  public function persistEntities(int $campaign_id, array $entities): void {
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Actor runtime mutation contract violation: campaign_id must be > 0.');
    }
    $this->actorRuntimeStateStore->syncFromEntities($campaign_id, $entities);
  }

}

