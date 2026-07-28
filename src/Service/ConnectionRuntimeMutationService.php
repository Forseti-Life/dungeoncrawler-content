<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Typed mutation lane for connection runtime state writes.
 */
class ConnectionRuntimeMutationService {

  public function __construct(
    protected readonly ConnectionRuntimeStateStore $connectionRuntimeStateStore,
  ) {}

  /**
   * Persist connection runtime payloads for a campaign.
   *
   * @param array<int,mixed> $connections
   *   Runtime connection rows.
   */
  public function persistConnections(int $campaign_id, array $connections): void {
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Connection runtime mutation contract violation: campaign_id must be > 0.');
    }
    $this->connectionRuntimeStateStore->syncFromConnections($campaign_id, $connections);
  }

}

