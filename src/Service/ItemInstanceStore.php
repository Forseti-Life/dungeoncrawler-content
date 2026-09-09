<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Low-level persistence/query component for item-instance rows (Phase 3).
 *
 * This is the raw persistence lane for `dc_campaign_item_instances`. It only
 * knows how to load a single canonical item-instance row by its canonical
 * instance id (optionally scoped to a campaign). It performs no assembly, no
 * definition resolution, no campaign-lifecycle guard and returns no envelope:
 * those belong to the canonical current-state owner, ItemStateService.
 *
 * It exists solely to break the dependency cycle that would otherwise appear
 * when the canonical item owner needs raw persistence while
 * InventoryManagementService (inventory collection/writes) delegates its single
 * item reads to that owner. Extracting this pure query component keeps exactly
 * one current-state authority (ItemStateService) without either service
 * reaching into the other.
 *
 * It is deliberately NOT a public current-state read surface. Only the
 * canonical owner may inject it; the container guard freezes that allowlist.
 */
class ItemInstanceStore {

  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Load a single raw item-instance row by canonical instance id.
   *
   * @param string $item_instance_id
   *   Canonical item instance id.
   * @param int|null $campaign_id
   *   Optional campaign scope. When provided, a row in a different campaign is
   *   treated as not found (never silently substituted).
   *
   * @return array<string,mixed>|null
   *   The raw row, or NULL when no matching instance exists.
   */
  public function loadInstanceRow(string $item_instance_id, ?int $campaign_id = NULL): ?array {
    $item_instance_id = trim($item_instance_id);
    if ($item_instance_id === '') {
      return NULL;
    }

    $query = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('item_instance_id', $item_instance_id)
      ->range(0, 1);
    if ($campaign_id !== NULL) {
      $query->condition('campaign_id', $campaign_id);
    }

    $row = $query->execute()->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

}
