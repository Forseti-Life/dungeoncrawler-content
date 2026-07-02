<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for inventory transfer and management endpoints.
 *
 * @see docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md
 */
class InventoryManagementController extends ControllerBase {

  protected InventoryManagementService $inventoryService;
  protected CharacterStateService $characterStateService;
  protected Connection $database;

  /**
   * Constructor.
   */
  public function __construct(
    InventoryManagementService $inventory_service,
    CharacterStateService $character_state_service,
    Connection $database
  ) {
    $this->inventoryService = $inventory_service;
    $this->characterStateService = $character_state_service;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.inventory_management'),
      $container->get('dungeoncrawler_content.character_state'),
      $container->get('database')
    );
  }

  /**
   * Get inventory for character or container.
   *
   * GET /api/inventory/{ownerType}/{ownerId}
   *
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $owner_id
   *   Character or container ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Inventory response.
   */
  public function getInventory(
    string $owner_type,
    string $owner_id,
    Request $request
  ): JsonResponse {
    try {
      $campaign_id = $request->query->get('campaign_id') ? (int) $request->query->get('campaign_id') : NULL;

      $inventory = $this->inventoryService->getInventory($owner_id, $owner_type, $campaign_id);

      // Calculate bulk and encumbrance
      $current_bulk = $this->inventoryService->calculateCurrentBulk($owner_id, $owner_type);
      $capacity = $this->inventoryService->getInventoryCapacity($owner_id, $owner_type);
      $str_score = 10.0;
      if ($owner_type === 'character') {
        $char_state = $this->characterStateService->getState($owner_id);
        $str_score = (float) ($char_state['abilities']['strength'] ?? 10);
      }
      $encumbrance = $this->inventoryService->getEncumbranceStatus($current_bulk, $str_score);

      return new JsonResponse([
        'success' => TRUE,
        'inventory' => $inventory,
        'bulk' => [
          'current' => $current_bulk,
          'capacity' => $capacity,
          'encumbrance' => $encumbrance,
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Add item to inventory.
   *
   * POST /api/inventory/{ownerType}/{ownerId}/item
   *
   * Request body:
   * {
   *   "item": { "id": "...", "name": "...", "bulk": "L", ... },
   *   "quantity": 1,
   *   "location": "carried",
   *   "campaignId": null
   * }
   *
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $owner_id
   *   Character or container ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Operation result.
   */
  public function addItem(
    string $owner_type,
    string $owner_id,
    Request $request
  ): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE);

      $item = $data['item'] ?? [];
      $quantity = (int) ($data['quantity'] ?? 1);
      $location = $data['location'] ?? 'carried';
      $campaign_id = $data['campaignId'] ? (int) $data['campaignId'] : NULL;

      $result = $this->inventoryService->addItemToInventory(
        $owner_id,
        $owner_type,
        $item,
        $location,
        $quantity,
        $campaign_id
      );

      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Remove item from inventory.
   *
   * DELETE /api/inventory/{ownerType}/{ownerId}/item/{itemInstanceId}
   *
   * Query parameters:
   * - quantity: Number of items to remove (default: 1)
   * - campaign_id: Campaign ID (optional)
   *
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $owner_id
   *   Character or container ID.
   * @param string $item_instance_id
   *   Item instance ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Operation result.
   */
  public function removeItem(
    string $owner_type,
    string $owner_id,
    string $item_instance_id,
    Request $request
  ): JsonResponse {
    try {
      $quantity = (int) ($request->query->get('quantity') ?? 1);
      $campaign_id = $request->query->get('campaign_id') ? (int) $request->query->get('campaign_id') : NULL;

      $result = $this->inventoryService->removeItemFromInventory(
        $owner_id,
        $owner_type,
        $item_instance_id,
        $quantity,
        $campaign_id
      );

      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Sell an item from inventory, enforcing sell_taboo rules.
   *
   * POST /api/inventory/{ownerType}/{ownerId}/item/{itemInstanceId}/sell
   *
   * Request body (optional):
   * {
   *   "gm_override": false,
   *   "campaign_id": null
   * }
   *
   * On sell_taboo block (no GM override):
   * HTTP 403, { "success": false, "sell_taboo": true, "message": "..." }
   *
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $owner_id
   *   Character or container ID.
   * @param string $item_instance_id
   *   Item instance ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Operation result.
   */
  public function sellItem(
    string $owner_type,
    string $owner_id,
    string $item_instance_id,
    Request $request
  ): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE) ?: [];
      $gm_override = !empty($data['gm_override'])
        && \Drupal::currentUser()->hasPermission('administer dungeoncrawler campaigns');
      $campaign_id = isset($data['campaign_id']) ? (int) $data['campaign_id'] : NULL;

      $result = $this->inventoryService->sellItem(
        $owner_id,
        $owner_type,
        $item_instance_id,
        $gm_override,
        $campaign_id
      );

      if (!$result['success'] && !empty($result['sell_taboo'])) {
        return new JsonResponse($result, 403);
      }

      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Transfer items between two inventories.
   *
   * POST /api/inventory/transfer
   *
   * Request body:
   * {
   *   "sourceOwnerId": "...",
   *   "sourceOwnerType": "character|container",
   *   "destOwnerId": "...",
   *   "destOwnerType": "character|container",
   *   "itemInstanceId": "...",
   *   "quantity": 1,
   *   "campaignId": null
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Transfer result.
   */
  public function transferItems(Request $request): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE);

      $source_owner_id = $data['sourceOwnerId'] ?? '';
      $source_owner_type = $data['sourceOwnerType'] ?? 'character';
      $dest_owner_id = $data['destOwnerId'] ?? '';
      $dest_owner_type = $data['destOwnerType'] ?? 'character';
      $item_instance_id = $data['itemInstanceId'] ?? '';
      $quantity = (int) ($data['quantity'] ?? 1);
      $campaign_id = $data['campaignId'] ? (int) $data['campaignId'] : NULL;

      if (!$source_owner_id || !$dest_owner_id || !$item_instance_id) {
        throw new \InvalidArgumentException('Missing required fields');
      }

      $result = $this->inventoryService->transferItems(
        $source_owner_id,
        $source_owner_type,
        $dest_owner_id,
        $dest_owner_type,
        $item_instance_id,
        $quantity,
        $campaign_id
      );

      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Change item location (equip, unequip, stash, etc).
   *
   * POST /api/inventory/{ownerType}/{ownerId}/item/{itemInstanceId}/location
   *
   * Request body:
   * {
   *   "location": "equipped|worn|carried|stashed",
   *   "campaignId": null
   * }
   *
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $owner_id
   *   Character or container ID.
   * @param string $item_instance_id
   *   Item instance ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Operation result.
   */
  public function changeItemLocation(
    string $owner_type,
    string $owner_id,
    string $item_instance_id,
    Request $request
  ): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE);

      $location = $data['location'] ?? '';
      $campaign_id = $data['campaignId'] ? (int) $data['campaignId'] : NULL;

      if (!$location) {
        throw new \InvalidArgumentException('Location is required');
      }

      $result = $this->inventoryService->changeItemLocation(
        $owner_id,
        $owner_type,
        $item_instance_id,
        $location,
        $campaign_id
      );

      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Get bulk and capacity information.
   *
   * GET /api/inventory/{ownerType}/{ownerId}/capacity
   *
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $owner_id
   *   Character or container ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Capacity information.
   */
  public function getCapacity(
    string $owner_type,
    string $owner_id,
    Request $request
  ): JsonResponse {
    try {
      $campaign_id = $request->query->get('campaign_id') ? (int) $request->query->get('campaign_id') : NULL;

      $current_bulk = $this->inventoryService->calculateCurrentBulk($owner_id, $owner_type, $campaign_id);
      $capacity = $this->inventoryService->getInventoryCapacity($owner_id, $owner_type);
      $str_score = 10.0;
      if ($owner_type === 'character') {
        $char_state = $this->characterStateService->getState($owner_id);
        $str_score = (float) ($char_state['abilities']['strength'] ?? 10);
      }
      $encumbrance = $this->inventoryService->getEncumbranceStatus($current_bulk, $str_score);

      return new JsonResponse([
        'success' => TRUE,
        'bulk' => [
          'current' => $current_bulk,
          'capacity' => $capacity,
          'percentFilled' => ($capacity > 0) ? ($current_bulk / $capacity) * 100 : 0,
          'encumbrance' => $encumbrance,
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

}
