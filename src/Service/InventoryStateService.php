<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical current-state retrieval owner for inventories.
 */
class InventoryStateService {

  public function __construct(
    protected InventoryManagementService $inventoryManagementService,
  ) {}

  /**
   * Retrieve canonical current inventory state.
   *
   * @return array<string,mixed>
   *   Inventory payload.
   */
  public function getState(
    string $owner_id,
    string $owner_type = 'character',
    ?int $campaign_id = NULL
  ): array {
    return $this->inventoryManagementService->getInventory($owner_id, $owner_type, $campaign_id);
  }

}
