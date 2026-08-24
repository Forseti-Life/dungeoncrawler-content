<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical current-state retrieval owner for item instances.
 */
class ItemStateService {

  public function __construct(
    protected InventoryManagementService $inventoryManagementService,
  ) {}

  /**
   * Retrieve canonical current item-instance state.
   *
   * @return array<string,mixed>
   *   Item instance state.
   */
  public function getState(string $item_instance_id, ?int $campaign_id = NULL): array {
    return $this->inventoryManagementService->getItemState($item_instance_id, $campaign_id);
  }

}
