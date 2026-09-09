<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for item instances.
 */
class ItemStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'item';

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
    $state = $this->getState($ref->objectId, $ref->contextInt('campaign_id'));

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
