<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for inventories.
 */
class InventoryStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'inventory';

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


  /**
   * {@inheritdoc}
   */
  public function objectType(): string {
    return self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $object_type): bool {
    return $object_type === self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
    $state = $this->getState(
      $ref->objectId,
      $ref->contextString('owner_type') ?? 'character',
      $ref->contextInt('campaign_id')
    );

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      InventoryManagementService::class,
      self::class,
      'item_instances',
      $state,
      isset($state['version']) ? (int) $state['version'] : NULL,
      isset($state['updated_at']) ? (string) $state['updated_at'] : NULL,
    );
  }

}
