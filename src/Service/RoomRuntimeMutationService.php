<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Typed mutation lane for room runtime state writes.
 */
class RoomRuntimeMutationService {

  public function __construct(
    protected readonly RoomRuntimeStateStore $roomRuntimeStateStore,
  ) {}

  /**
   * Persist room runtime payloads for a campaign.
   *
   * @param array<int,mixed> $rooms
   *   Runtime room rows.
   */
  public function persistRooms(int $campaign_id, array $rooms): void {
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Room runtime mutation contract violation: campaign_id must be > 0.');
    }
    $this->roomRuntimeStateStore->syncFromRooms($campaign_id, $rooms);
  }

}

