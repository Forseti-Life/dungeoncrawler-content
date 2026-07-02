<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Manages inventory and item transfers between characters and containers.
 *
 * This service provides a comprehensive inventory management system supporting:
 * - Item transfers between characters and containers
 * - Bulk calculation and encumbrance tracking
 * - Item state preservation (equipped, worn, runes, conditions)
 * - Transfer validation and authorization
 * - Operation logging
 *
 * @see docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md
 */
class InventoryManagementService {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;
  protected CharacterStateService $characterStateService;

  /**
   * Item subtypes that sell at full price (CRB Ch10: gems, art objects, raw materials).
   */
  public const FULL_PRICE_SUBTYPES = ['gem', 'art_object', 'raw_material'];

  /**
   * Bulk weight mappings per PF2e spec.
   */
  private const BULK_MAP = [
    'negligible' => 0,
    'light' => 0.1,
    'L' => 0.1,
    '1' => 1,
    'medium' => 1,
  ];

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    AccountProxyInterface $current_user,
    CharacterStateService $character_state_service
  ) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->characterStateService = $character_state_service;
  }

  /**
   * Get inventory for a character or container.
   *
   * This is the unified inventory method that pulls from dc_campaign_item_instances
   * as the source of truth, then syncs with character state JSON.
   *
   * @param string $owner_id
   *   Character ID or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param int $campaign_id
   *   Campaign ID (required for campaign instances).
   *
   * @return array
   *   Inventory array with items grouped by location.
   *
   * @throws \InvalidArgumentException
   *   If owner not found or invalid type.
   */
  public function getInventory(
    string $owner_id,
    string $owner_type = 'character',
    ?int $campaign_id = NULL
  ): array {
    if (!in_array($owner_type, ['character', 'container', 'room'])) {
      throw new \InvalidArgumentException("Invalid owner type: {$owner_type}");
    }

    // For characters, load from item instances table (source of truth)
    if ($owner_type === 'character') {
      return $this->getCharacterInventoryFromInstances($owner_id, $campaign_id);
    }

    // For containers and rooms, load from items table
    return $this->getContainerInventory($owner_id, $campaign_id);
  }

  /**
   * Get character inventory from item instances table.
   *
   * This is the source of truth for character inventory.
   *
   * @param string $character_id
   *   Character ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Inventory organized by location.
   */
  protected function getCharacterInventoryFromInstances(
    string $character_id,
    ?int $campaign_id = NULL
  ): array {
    $query = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('location_ref', $character_id);

    if ($campaign_id !== NULL) {
      $query->condition('campaign_id', $campaign_id);
    }

    $items = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    // Organize by location type
    $inventory = [
      'worn' => [
        'weapons' => [],
        'armor' => [],
        'accessories' => [],
      ],
      'carried' => [],
      'equipped' => [],
      'stashed' => [],
      'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      'totalBulk' => 0,
      'encumbrance' => 'unencumbered',
    ];

    foreach ($items as $item_row) {
      $state = json_decode($item_row['state_data'], TRUE) ?? [];
      
      $item_data = [
        'item_instance_id' => $item_row['item_instance_id'],
        'item_id' => $item_row['item_id'],
        'quantity' => (int) $item_row['quantity'],
        'location' => $item_row['location_type'],
        ...($state ?? []),
      ];

      $location = $item_row['location_type'];

      switch ($location) {
        case 'worn':
          $type = $state['type'] ?? 'accessory';
          if ($type === 'weapon') {
            $inventory['worn']['weapons'][] = $item_data;
          }
          elseif ($type === 'armor') {
            $inventory['worn']['armor'] = $item_data;
          }
          else {
            $inventory['worn']['accessories'][] = $item_data;
          }
          break;

        case 'equipped':
          $inventory['equipped'][] = $item_data;
          break;

        case 'stashed':
          $inventory['stashed'][] = $item_data;
          break;

        case 'carried':
        default:
          $inventory['carried'][] = $item_data;
          break;
      }
    }

    // Calculate bulk and encumbrance
    $current_bulk = $this->calculateCurrentBulk($character_id, 'character', $campaign_id);
    $char_state = $this->characterStateService->getState($character_id);
    $str_score = (float) ($char_state['abilities']['strength'] ?? 10);

    $inventory['totalBulk'] = $current_bulk;
    $inventory['capacity'] = $this->getInventoryCapacity($character_id, 'character');
    $inventory['encumbrance'] = $this->getEncumbranceStatus($current_bulk, $str_score);

    return $inventory;
  }

  /**
   * Add item to inventory.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param array $item
   *   Item data to add.
   * @param string $location
   *   Item location: 'carried', 'stash', 'equipped'.
   * @param int $quantity
   *   Number of items to add.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Updated inventory state.
   *
   * @throws \Exception
   *   On validation or persistence errors.
   */
  public function addItemToInventory(
    string $owner_id,
    string $owner_type,
    array $item,
    string $location = 'carried',
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    $this->validateItemData($item);
    $this->validateOwner($owner_id, $owner_type);

    if ($quantity < 1) {
      throw new \InvalidArgumentException('Quantity must be at least 1');
    }

    try {
      $transaction = $this->database->startTransaction();

      // Persist item instance for tracking
      $item_instance_id = $this->createItemInstance(
        $owner_id,
        $owner_type,
        $item,
        $location,
        $quantity,
        $campaign_id
      );

      // Update character state
      $inventory = $this->getInventory($owner_id, $owner_type, $campaign_id);

      // Sync character state if character owner
      if ($owner_type === 'character') {
        $this->syncCharacterStateInventory($owner_id, $campaign_id);
      }

      // Log operation
      $this->logInventoryOperation(
        'add_item',
        $owner_id,
        $owner_type,
        $campaign_id,
        [
          'item_id' => $item['id'] ?? '',
          'item_instance_id' => $item_instance_id,
          'quantity' => $quantity,
          'location' => $location,
        ]
      );


      return [
        'success' => TRUE,
        'inventory' => $inventory,
        'item_instance_id' => $item_instance_id,
        'message' => "Added {$quantity} of '{$item['name']}' to {$owner_type}",
      ];
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Remove item from inventory.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $item_instance_id
   *   Item instance ID to remove.
   * @param int $quantity
   *   Number of items to remove (partial removal).
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Updated inventory state.
   *
   * @throws \Exception
   *   On validation or persistence errors.
   */
  public function removeItemFromInventory(
    string $owner_id,
    string $owner_type,
    string $item_instance_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($owner_id, $owner_type);

    if ($quantity < 1) {
      throw new \InvalidArgumentException('Remove quantity must be at least 1');
    }

    try {
      $transaction = $this->database->startTransaction();

      // Get current item
      $item = $this->database->select('dc_campaign_item_instances', 'i')
        ->fields('i')
        ->condition('item_instance_id', $item_instance_id)
        ->condition('location_ref', $owner_id)
        ->execute()
        ->fetchAssoc();

      if (!$item) {
        throw new \InvalidArgumentException("Item instance not found: {$item_instance_id}");
      }

      $current_qty = (int) $item['quantity'];

      if ($quantity > $current_qty) {
        throw new \InvalidArgumentException(
          "Cannot remove {$quantity} items; only {$current_qty} available"
        );
      }

      if ($quantity === $current_qty) {
        // Remove entirely
        $this->database->delete('dc_campaign_item_instances')
          ->condition('item_instance_id', $item_instance_id)
          ->execute();
      }
      else {
        // Partial removal
        $this->database->update('dc_campaign_item_instances')
          ->fields(['quantity' => $current_qty - $quantity])
          ->condition('item_instance_id', $item_instance_id)
          ->execute();
      }

      // Sync character state if character owner
      if ($owner_type === 'character') {
        $this->syncCharacterStateInventory($owner_id, $campaign_id);
      }

      $inventory = $this->getInventory($owner_id, $owner_type, $campaign_id);

      // Log operation
      $this->logInventoryOperation(
        'remove_item',
        $owner_id,
        $owner_type,
        $campaign_id,
        [
          'item_instance_id' => $item_instance_id,
          'quantity_removed' => $quantity,
          'quantity_remaining' => max(0, $current_qty - $quantity),
        ]
      );


      return [
        'success' => TRUE,
        'inventory' => $inventory,
        'message' => "Removed {$quantity} items",
      ];
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Attempt to sell an item from inventory, enforcing sell_taboo rules.
   *
   * If the item carries sell_taboo: true in its state data, this method
   * returns a structured taboo-block response unless $gm_override is TRUE.
   * The sell taboo check only fires here — not in removeItemFromInventory —
   * so that dropping/losing an item bypasses the taboo consequence.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $item_instance_id
   *   Item instance ID to sell.
   * @param bool $gm_override
   *   When TRUE, bypasses sell_taboo and removes the item regardless.
   * @param int|null $campaign_id
   *   Campaign ID (NULL = library record, i.e., campaign_id = 0).
   *
   * @return array
   *   Operation result array. On taboo block without override:
   *   ['success' => false, 'sell_taboo' => true, 'message' => '...'].
   *   On success: same as removeItemFromInventory return value.
   */
  public function sellItem(
    string $owner_id,
    string $owner_type,
    string $item_instance_id,
    bool $gm_override = FALSE,
    ?int $campaign_id = NULL,
    string $game_phase = 'downtime'
  ): array {
    // Sell/purchase transactions are downtime-only (CRB Chapter 6).
    if (in_array($game_phase, ['encounter', 'exploration'], TRUE)) {
      return [
        'success' => FALSE,
        'error' => 'downtime_only',
        'message' => 'Selling items is only permitted during downtime (not encounter or exploration).',
      ];
    }

    $this->validateOwner($owner_id, $owner_type);

    // Load item instance to check sell_taboo flag.
    $row = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data', 'item_id'])
      ->condition('item_instance_id', $item_instance_id)
      ->condition('location_ref', $owner_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      throw new \InvalidArgumentException("Item instance not found: {$item_instance_id}");
    }

    $state = json_decode($row['state_data'] ?? '{}', TRUE) ?: [];
    $has_taboo = !empty($state['sell_taboo']);

    if ($has_taboo && !$gm_override) {
      $message = $state['sell_taboo_message']
        ?? 'This item has a sell taboo. A GM must authorize its sale.';
      return [
        'success' => FALSE,
        'sell_taboo' => TRUE,
        'item_id' => $row['item_id'] ?? ($state['id'] ?? $item_instance_id),
        'message' => $message,
      ];
    }

    // Taboo waived (no taboo, or GM override) — compute sell value and credit currency.
    // CRB Ch10: gems, art objects, and raw materials sell at full Price;
    // all standard items sell at half Price.
    $item_subtype = $state['subtype'] ?? ($state['item_subtype'] ?? '');
    $price_gp = isset($state['price_gp']) ? (float) $state['price_gp'] : 0.0;
    $is_full_price = in_array($item_subtype, self::FULL_PRICE_SUBTYPES, TRUE);
    $sell_value_gp = $is_full_price ? $price_gp : $price_gp / 2.0;
    $sell_value_cp = (int) round($sell_value_gp * 100);

    // Remove item first (within a transaction so currency credit is atomic).
    $transaction = $this->database->startTransaction();
    try {
      $remove_result = $this->removeItemFromInventory(
        $owner_id,
        $owner_type,
        $item_instance_id,
        1,
        $campaign_id
      );

      // Credit currency to seller (characters only; containers have no currency).
      if ($owner_type === 'character' && $sell_value_cp > 0) {
        $char_record = $this->database->select('dc_campaign_characters', 'c')
          ->fields('c', ['character_data'])
          ->condition('id', $owner_id)
          ->execute()
          ->fetchAssoc();

        if ($char_record) {
          $char_data = json_decode($char_record['character_data'] ?? '{}', TRUE) ?: [];
          $currency = $char_data['character']['equipment']['currency']
            ?? $char_data['equipment']['currency']
            ?? $char_data['currency']
            ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
          if (!isset($currency['gp']) && isset($currency['gold'])) {
            $currency = [
              'pp' => (int) ($currency['pp'] ?? 0),
              'gp' => (int) $currency['gold'],
              'sp' => (int) ($currency['silver'] ?? 0),
              'cp' => (int) ($currency['copper'] ?? 0),
            ];
          }
          $rates = ['cp' => 1, 'sp' => 10, 'gp' => 100, 'pp' => 1000];
          $total_cp = $sell_value_cp;
          foreach ($rates as $denom => $rate) {
            $total_cp += ((int) ($currency[$denom] ?? 0)) * $rate;
          }
          $new_currency = ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
          foreach (['pp', 'gp', 'sp', 'cp'] as $denom) {
            $new_currency[$denom] = intdiv($total_cp, $rates[$denom]);
            $total_cp %= $rates[$denom];
          }
          if (isset($char_data['character']['equipment']['currency'])) {
            $char_data['character']['equipment']['currency'] = $new_currency;
          }
          elseif (isset($char_data['equipment']['currency'])) {
            $char_data['equipment']['currency'] = $new_currency;
          }
          else {
            $char_data['currency'] = $new_currency;
          }
          $this->database->update('dc_campaign_characters')
            ->fields(['character_data' => json_encode($char_data)])
            ->condition('id', $owner_id)
            ->execute();
          $remove_result['currency_credited_cp'] = $sell_value_cp;
          $remove_result['sell_price_type'] = $is_full_price ? 'full' : 'half';
        }
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
    unset($transaction);
    return $remove_result;
  }

  /**
   * Purchase an item for a character, enforcing economy rules.
   *
   * Rules enforced:
   * - Items with price_gp = NULL (Price "—") cannot be purchased (not_for_sale).
   * - Items with price_gp = 0 are free; no currency deducted.
   * - Purchase/sell transactions blocked during encounter or exploration phases.
   * - Negative balance prevented (returns insufficient_funds if character cannot afford).
   *
   * @param string $character_id Character ID (owner).
   * @param array $item Item data from EquipmentCatalogService::CATALOG (must include 'price_gp').
   * @param string $game_phase Current game phase: 'downtime', 'encounter', 'exploration'.
   * @param int $quantity Quantity to purchase.
   * @param int|null $campaign_id Campaign ID.
   *
   * @return array Result: ['success' => bool, 'error' => string|null, 'message' => string].
   */
  public function purchaseItem(
    string $character_id,
    array $item,
    string $game_phase = 'downtime',
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    // Purchase transactions are downtime-only (CRB Chapter 6).
    if (in_array($game_phase, ['encounter', 'exploration'], TRUE)) {
      return [
        'success' => FALSE,
        'error' => 'downtime_only',
        'message' => 'Purchasing items is only permitted during downtime.',
      ];
    }

    // Price "—" items are not for sale.
    if (!array_key_exists('price_gp', $item) || $item['price_gp'] === NULL) {
      return [
        'success' => FALSE,
        'error' => 'not_for_sale',
        'item_id' => $item['id'] ?? 'unknown',
        'message' => 'This item has no purchase price and cannot be bought.',
      ];
    }

    $price_gp = (float) $item['price_gp'];
    $total_price_cp = (int) round($price_gp * 100 * $quantity);

    // Free item (price = 0) — add without currency check.
    if ($total_price_cp === 0) {
      return $this->addItemToInventory($character_id, 'character', $item, 'carried', $quantity, $campaign_id);
    }

    // Load character currency and check balance.
    $char_record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$char_record) {
      throw new \InvalidArgumentException("Character not found: {$character_id}");
    }

    $char_data = json_decode($char_record['character_data'] ?? '{}', TRUE) ?: [];
    // Currency may be nested under equipment or at top level.
    $currency = $char_data['character']['equipment']['currency']
      ?? $char_data['equipment']['currency']
      ?? $char_data['currency']
      ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    // Normalise gold/silver/copper key aliases from buildCharacterJson.
    if (!isset($currency['gp']) && isset($currency['gold'])) {
      $currency = [
        'pp' => (int) ($currency['pp'] ?? 0),
        'gp' => (int) $currency['gold'],
        'sp' => (int) ($currency['silver'] ?? 0),
        'cp' => (int) ($currency['copper'] ?? 0),
      ];
    }

    $rates = ['cp' => 1, 'sp' => 10, 'gp' => 100, 'pp' => 1000];
    $total_owned_cp = 0;
    foreach ($rates as $denom => $rate) {
      $total_owned_cp += ((int) ($currency[$denom] ?? 0)) * $rate;
    }

    if ($total_owned_cp < $total_price_cp) {
      return [
        'success' => FALSE,
        'error' => 'insufficient_funds',
        'required_cp' => $total_price_cp,
        'available_cp' => $total_owned_cp,
        'message' => "Insufficient funds: need {$total_price_cp} cp, have {$total_owned_cp} cp.",
      ];
    }

    // Deduct currency and persist updated character data.
    $new_total_cp = $total_owned_cp - $total_price_cp;
    $new_currency = ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    foreach (['pp', 'gp', 'sp', 'cp'] as $denom) {
      $new_currency[$denom] = intdiv($new_total_cp, $rates[$denom]);
      $new_total_cp %= $rates[$denom];
    }

    // Write new currency back into character_data preserving nested structure.
    if (isset($char_data['character']['equipment']['currency'])) {
      $char_data['character']['equipment']['currency'] = $new_currency;
    }
    elseif (isset($char_data['equipment']['currency'])) {
      $char_data['equipment']['currency'] = $new_currency;
    }
    else {
      $char_data['currency'] = $new_currency;
    }

    $transaction = $this->database->startTransaction();
    try {
      $this->database->update('dc_campaign_characters')
        ->fields(['character_data' => json_encode($char_data)])
        ->condition('id', $character_id)
        ->execute();

      $result = $this->addItemToInventory($character_id, 'character', $item, 'carried', $quantity, $campaign_id);
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
    unset($transaction); // Commit on scope exit.
    return $result;
  }

  /**
   * Transfer items between two inventories.
   *
   * @param string $source_owner_id
   *   Source character/container ID.
   * @param string $source_owner_type
   *   'character' or 'container'.
   * @param string $dest_owner_id
   *   Destination character/container ID.
   * @param string $dest_owner_type
   *   'character' or 'container'.
   * @param string $item_instance_id
   *   Item instance to transfer.
   * @param int $quantity
   *   Number of items to transfer.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Transfer result with both source and dest inventories.
   *
   * @throws \Exception
   *   On validation or transfer errors.
   */
  public function transferItems(
    string $source_owner_id,
    string $source_owner_type,
    string $dest_owner_id,
    string $dest_owner_type,
    string $item_instance_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($source_owner_id, $source_owner_type);
    $this->validateOwner($dest_owner_id, $dest_owner_type);
    $this->validateTransferPermission($source_owner_id, $source_owner_type);

    if ($quantity < 1) {
      throw new \InvalidArgumentException('Transfer quantity must be at least 1');
    }

    return $this->transferItemTransaction(
      [
        'owner_id' => $source_owner_id,
        'owner_type' => $source_owner_type,
      ],
      [
        'owner_id' => $dest_owner_id,
        'owner_type' => $dest_owner_type,
        'location_type' => $this->defaultLocationTypeForOwnerType($dest_owner_type),
      ],
      $item_instance_id,
      $quantity,
      $campaign_id
    );
  }

  /**
   * Execute a campaign-scoped transfer transaction between storage objects.
   *
   * This is the authoritative transfer path for character/container/room item
   * movement. It performs pre-checks, mutation-time verification, post-write
   * verification, and audit logging within a single transaction.
   *
   * @param array $source_storage
   *   Source storage reference with keys:
   *   - owner_id: string
   *   - owner_type: character|container|room
   *   - location_type: optional explicit item location/slot
   * @param array $dest_storage
   *   Destination storage reference with keys:
   *   - owner_id: string
   *   - owner_type: character|container|room
   *   - location_type: optional explicit item location/slot
   * @param string $item_instance_id
   *   The item instance being moved.
   * @param int $quantity
   *   Quantity to move.
   * @param int|null $campaign_id
   *   Campaign scope.
   *
   * @return array
   *   Transaction result and verification snapshots.
   */
  public function transferItemTransaction(
    array $source_storage,
    array $dest_storage,
    string $item_instance_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    if ($quantity < 1) {
      throw new \InvalidArgumentException('Transfer quantity must be at least 1');
    }

    $source = $this->normalizeStorageReference($source_storage, 'source');
    $dest = $this->normalizeStorageReference($dest_storage, 'destination');
    $transaction_id = uniqid('inv_tx_', TRUE);

    $this->validateOwner($source['owner_id'], $source['owner_type']);
    $this->validateOwner($dest['owner_id'], $dest['owner_type']);
    $this->validateTransferPermission($source['owner_id'], $source['owner_type']);

    $this->logInventoryOperation(
      'transfer_transaction_start',
      $source['owner_id'],
      $source['owner_type'],
      $campaign_id,
      [
        'transaction_id' => $transaction_id,
        'item_instance_id' => $item_instance_id,
        'quantity' => $quantity,
        'source' => $source,
        'destination' => $dest,
      ]
    );

    try {
      $transaction = $this->database->startTransaction();

      $source_before = $this->buildStorageSnapshot($source, $campaign_id, $item_instance_id);
      $dest_before = $this->buildStorageSnapshot($dest, $campaign_id);

      $source_item_row = $this->loadTransferItemRecord($item_instance_id, $source, $campaign_id);
      $preflight = $this->verifyTransferPreconditions($source, $dest, $source_item_row, $quantity, $campaign_id);
      if (empty($preflight['valid'])) {
        throw new \InvalidArgumentException(implode(' ', $preflight['errors'] ?? ['Transfer preflight failed.']));
      }

      $move_result = $this->applyTransferMutation($source, $dest, $source_item_row, $quantity, $campaign_id, $transaction_id);

      $source_after_mutation = $this->buildStorageSnapshot($source, $campaign_id, $item_instance_id);
      $dest_after_mutation = $this->buildStorageSnapshot($dest, $campaign_id, $move_result['moved_item_instance_id']);

      $mutation_check = $this->verifyTransferPostconditions(
        $move_result,
        $source_after_mutation,
        $dest_after_mutation,
        FALSE
      );
      if (empty($mutation_check['valid'])) {
        throw new \RuntimeException(implode(' ', $mutation_check['errors'] ?? ['Transfer verification failed after mutation.']));
      }

      if ($source['owner_type'] === 'character') {
        $this->syncCharacterStateInventory($source['owner_id'], $campaign_id);
      }
      if ($dest['owner_type'] === 'character' && ($dest['owner_id'] !== $source['owner_id'] || $dest['owner_type'] !== $source['owner_type'])) {
        $this->syncCharacterStateInventory($dest['owner_id'], $campaign_id);
      }

      $source_after_write = $this->buildStorageSnapshot($source, $campaign_id, $item_instance_id);
      $dest_after_write = $this->buildStorageSnapshot($dest, $campaign_id, $move_result['moved_item_instance_id']);
      $post_write_check = $this->verifyTransferPostconditions(
        $move_result,
        $source_after_write,
        $dest_after_write,
        TRUE
      );
      if (empty($post_write_check['valid'])) {
        throw new \RuntimeException(implode(' ', $post_write_check['errors'] ?? ['Transfer verification failed after write.']));
      }

      $source_inventory = $this->getInventory($source['owner_id'], $source['owner_type'], $campaign_id);
      $dest_inventory = $this->getInventory($dest['owner_id'], $dest['owner_type'], $campaign_id);

      $this->logInventoryOperation(
        'transfer_transaction_complete',
        $source['owner_id'],
        $source['owner_type'],
        $campaign_id,
        [
          'transaction_id' => $transaction_id,
          'from' => $source,
          'to' => $dest,
          'item_instance_id' => $item_instance_id,
          'moved_item_instance_id' => $move_result['moved_item_instance_id'],
          'quantity' => $quantity,
          'source_expected_quantity' => $move_result['expected_source_quantity'],
          'destination_expected_quantity' => $move_result['expected_destination_quantity'],
        ]
      );


      return [
        'success' => TRUE,
        'transaction_id' => $transaction_id,
        'source' => $source,
        'destination' => $dest,
        'verification' => [
          'source_before' => $source_before,
          'destination_before' => $dest_before,
          'source_after_mutation' => $source_after_mutation,
          'destination_after_mutation' => $dest_after_mutation,
          'source_after_write' => $source_after_write,
          'destination_after_write' => $dest_after_write,
        ],
        'source_inventory' => $source_inventory,
        'dest_inventory' => $dest_inventory,
        'moved_item_instance_id' => $move_result['moved_item_instance_id'],
        'item_id' => $move_result['item_id'] ?? '',
        'item_name' => $move_result['item_name'] ?? 'Item',
        'message' => "Transferred {$quantity} items from {$source['owner_type']} to {$dest['owner_type']}",
      ];
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logInventoryOperation(
        'transfer_transaction_failed',
        $source['owner_id'],
        $source['owner_type'],
        $campaign_id,
        [
          'transaction_id' => $transaction_id,
          'item_instance_id' => $item_instance_id,
          'quantity' => $quantity,
          'source' => $source,
          'destination' => $dest,
          'error' => $e->getMessage(),
        ]
      );
      throw $e;
    }
  }

  /**
   * Validate a transfer transaction without mutating storage.
   */
  public function validateTransferTransaction(
    array $source_storage,
    array $dest_storage,
    string $item_instance_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    if ($quantity < 1) {
      return [
        'valid' => FALSE,
        'errors' => ['Transfer quantity must be at least 1.'],
      ];
    }

    try {
      $source = $this->normalizeStorageReference($source_storage, 'source');
      $dest = $this->normalizeStorageReference($dest_storage, 'destination');
      $this->validateOwner($source['owner_id'], $source['owner_type']);
      $this->validateOwner($dest['owner_id'], $dest['owner_type']);
      $source_item_row = $this->loadTransferItemRecord($item_instance_id, $source, $campaign_id);
      $preflight = $this->verifyTransferPreconditions($source, $dest, $source_item_row, $quantity, $campaign_id);
      $state = json_decode($source_item_row['state_data'] ?? '{}', TRUE) ?: [];

      return [
        'valid' => !empty($preflight['valid']),
        'errors' => $preflight['errors'] ?? [],
        'source' => $source,
        'destination' => $dest,
        'item' => [
          'item_instance_id' => $source_item_row['item_instance_id'] ?? $item_instance_id,
          'item_id' => $source_item_row['item_id'] ?? '',
          'item_name' => $state['name'] ?? ($source_item_row['item_id'] ?? 'Item'),
          'available_quantity' => (int) ($source_item_row['quantity'] ?? 0),
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'valid' => FALSE,
        'errors' => [$e->getMessage()],
      ];
    }
  }

  /**
   * Batch transfer multiple items at once.
   *
   * More efficient than multiple individual transfers.
   *
   * @param string $source_owner_id
   *   Source character/container ID.
   * @param string $source_owner_type
   *   'character' or 'container'.
   * @param string $dest_owner_id
   *   Destination character/container ID.
   * @param string $dest_owner_type
   *   'character' or 'container'.
   * @param array $items
   *   Array of items: [['item_instance_id' => '...', 'quantity' => 1], ...]
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Batch transfer results.
   *
   * @throws \Exception
   *   On validation or transfer errors.
   */
  public function batchTransferItems(
    string $source_owner_id,
    string $source_owner_type,
    string $dest_owner_id,
    string $dest_owner_type,
    array $items,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($source_owner_id, $source_owner_type);
    $this->validateOwner($dest_owner_id, $dest_owner_type);
    $this->validateTransferPermission($source_owner_id, $source_owner_type);

    if (empty($items)) {
      throw new \InvalidArgumentException('No items specified for batch transfer');
    }

    try {
      $transaction = $this->database->startTransaction();

      $transferred = 0;
      $failed = [];

      foreach ($items as $item) {
        try {
          $this->transferItems(
            $source_owner_id,
            $source_owner_type,
            $dest_owner_id,
            $dest_owner_type,
            $item['item_instance_id'],
            $item['quantity'] ?? 1,
            $campaign_id
          );
          $transferred++;
        }
        catch (\Exception $e) {
          $failed[] = [
            'item_instance_id' => $item['item_instance_id'],
            'error' => $e->getMessage(),
          ];
        }
      }


      return [
        'success' => empty($failed),
        'transferred_count' => $transferred,
        'failed_count' => count($failed),
        'failed_items' => $failed,
        'message' => "Batch transfer: {$transferred} succeeded, " . count($failed) . " failed",
      ];
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Drop items into a room.
   *
   * @param string $character_id
   *   Character ID dropping items.
   * @param string $item_instance_id
   *   Item instance to drop.
   * @param string $room_id
   *   Room ID where items are dropped.
   * @param int $quantity
   *   Number to drop.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Operation result.
   *
   * @throws \Exception
   *   On errors.
   */
  public function dropItemInRoom(
    string $character_id,
    string $item_instance_id,
    string $room_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    return $this->transferItems(
      $character_id,
      'character',
      $room_id,
      'room',
      $item_instance_id,
      $quantity,
      $campaign_id
    );
  }

  /**
   * Pick up items from a room.
   *
   * @param string $character_id
   *   Character ID picking up items.
   * @param string $item_instance_id
   *   Item instance to pick up.
   * @param string $room_id
   *   Room ID where items are located.
   * @param int $quantity
   *   Number to pick up.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Operation result.
   *
   * @throws \Exception
   *   On errors.
   */
  public function pickUpItemFromRoom(
    string $character_id,
    string $item_instance_id,
    string $room_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    return $this->transferItems(
      $room_id,
      'room',
      $character_id,
      'character',
      $item_instance_id,
      $quantity,
      $campaign_id
    );
  }

  /**
   * Change item location within same owner (e.g., equip/unequip).
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $item_instance_id
   *   Item instance ID.
   * @param string $new_location
   *   New location: 'carried', 'equipped', 'worn', etc.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Updated inventory.
   *
   * @throws \Exception
   *   On validation errors.
   */
  public function changeItemLocation(
    string $owner_id,
    string $owner_type,
    string $item_instance_id,
    string $new_location,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($owner_id, $owner_type);

    $valid_locations = ['carried', 'equipped', 'worn', 'stashed', 'dropped'];
    if (!in_array($new_location, $valid_locations)) {
      throw new \InvalidArgumentException("Invalid location: {$new_location}");
    }

    try {
      $transaction = $this->database->startTransaction();

      // Update location in item instance
      $updated = $this->database->update('dc_campaign_item_instances')
        ->fields([
          'location_type' => $new_location,
          'updated' => time(),
        ])
        ->condition('item_instance_id', $item_instance_id)
        ->condition('location_ref', $owner_id)
        ->execute();

      if (!$updated) {
        throw new \InvalidArgumentException("Item instance not found or not in this inventory");
      }

      // Apply STR requirement penalty when equipping/wearing armor.
      if ($owner_type === 'character' && in_array($new_location, ['worn', 'equipped'], TRUE)) {
        $this->applyArmorStrPenalty($owner_id, $item_instance_id);
      }

      // Sync character state if character owner
      if ($owner_type === 'character') {
        $this->syncCharacterStateInventory($owner_id, $campaign_id);
      }

      $inventory = $this->getInventory($owner_id, $owner_type, $campaign_id);

      // Log operation
      $this->logInventoryOperation(
        'change_location',
        $owner_id,
        $owner_type,
        $campaign_id,
        [
          'item_instance_id' => $item_instance_id,
          'new_location' => $new_location,
        ]
      );


      return [
        'success' => TRUE,
        'inventory' => $inventory,
        'message' => "Item location changed to {$new_location}",
      ];
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Calculate Bulk contributed by coins (PF2e: 1,000 coins = 1 Bulk, floor division).
   *
   * @param int $total_coins Total coin count (all denominations summed).
   * @return int Bulk value (integer, floor division).
   */
  public static function calculateCoinBulk(int $total_coins): int {
    return (int) floor($total_coins / 1000);
  }

  /**
   * Calculate total bulk for an inventory.

   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return float
   *   Total bulk.
   */
  public function calculateCurrentBulk(
    string $owner_id,
    string $owner_type = 'character',
    ?int $campaign_id = NULL
  ): float {
    $items = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data', 'quantity'])
      ->condition('location_ref', $owner_id)
      ->condition('location_type', $this->ownerTypeToLocationType($owner_type))
      ->execute()
      ->fetchAll();

    $total_bulk = 0.0;
    foreach ($items as $item) {
      $state = json_decode($item->state_data, TRUE) ?? [];
      $qty = (int) $item->quantity;
      $total_bulk += $this->calculateItemBulk($state, $qty);
    }

    return $total_bulk;
  }

  /**
   * Apply STR requirement check penalty flag when armor is equipped/worn.
   *
   * If the character's STR score is below the armor's str_req, sets
   * str_penalty_active: true on the item instance's state_data. The check
   * penalty value is stored alongside for downstream skill-check resolution.
   * Equipping is never blocked — only the flag is applied.
   *
   * @param string $character_id
   *   Character ID.
   * @param string $item_instance_id
   *   Item instance ID just moved to worn/equipped.
   */
  protected function applyArmorStrPenalty(string $character_id, string $item_instance_id): void {
    $row = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data'])
      ->condition('item_instance_id', $item_instance_id)
      ->execute()
      ->fetchObject();

    if (!$row) {
      return;
    }

    $state = json_decode($row->state_data, TRUE) ?? [];
    $item_type = $state['type'] ?? ($state['item_type'] ?? '');
    if ($item_type !== 'armor') {
      return;
    }

    $str_req = (int) ($state['armor_stats']['str_req'] ?? 0);
    if ($str_req === 0) {
      return;
    }

    $char_state = $this->characterStateService->getState($character_id);
    $char_str = (int) ($char_state['abilities']['strength'] ?? 10);

    // Remove stale penalty flag regardless, then re-evaluate.
    unset($state['str_penalty_active'], $state['str_penalty_check_penalty']);

    if ($char_str < $str_req) {
      $state['str_penalty_active'] = TRUE;
      $state['str_penalty_check_penalty'] = (int) ($state['armor_stats']['check_penalty'] ?? 0);
    }

    $this->database->update('dc_campaign_item_instances')
      ->fields(['state_data' => json_encode($state), 'updated' => time()])
      ->condition('item_instance_id', $item_instance_id)
      ->execute();
  }

  /**
   * Get encumbrance status based on PF2E rules.
   *
   * @param float $current_bulk
   *   Current total bulk.
   * @param float $str_score
   *   Character Strength ability score.
   *
   * @return string
   *   Encumbrance status: 'unencumbered', 'encumbered', or 'immobilized'.
   */
  public function getEncumbranceStatus(float $current_bulk, float $str_score): string {
    $encumbered_threshold = floor($str_score / 2) + 5;
    $immobilized_threshold = $str_score + 5;
    if ($current_bulk >= $immobilized_threshold) {
      return 'immobilized';
    }
    if ($current_bulk >= $encumbered_threshold) {
      return 'encumbered';
    }
    return 'unencumbered';
  }

  /**
   * Get inventory capacity for an owner.
   *
   * @param string $owner_id
   *   Character, container, or room ID.
   * @param string $owner_type
   *   'character', 'container', or 'room'.
   *
   * @return float
   *   Maximum bulk capacity (PHP_FLOAT_MAX for rooms).
   */
  public function getInventoryCapacity(
    string $owner_id,
    string $owner_type = 'character'
  ): float {
    if ($owner_type === 'character') {
      // Get character STR ability
      $state = $this->characterStateService->getState($owner_id);
      $str = $state['abilities']['strength'] ?? 10;
      $str_mod = floor(($str - 10) / 2);

      // PF2e base capacity = 10 + STR mod (max unencumbered bulk)
      return 10 + $str_mod;
    }

    if ($owner_type === 'room') {
      // Rooms have unlimited capacity
      return PHP_FLOAT_MAX;
    }

    // For containers, get capacity from container_stats
    $container = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data'])
      ->condition('item_instance_id', $owner_id)
      ->execute()
      ->fetchObject();

    if (!$container) {
      return 10; // Default fallback
    }

    $state = json_decode($container->state_data, TRUE) ?? [];
    
    // Check for container_stats.capacity (standard location per item.schema.json)
    if (!empty($state['container_stats']['capacity'])) {
      return (float) $state['container_stats']['capacity'];
    }
    
    // Fallback to legacy capacity field for backwards compatibility
    if (!empty($state['capacity'])) {
      return (float) $state['capacity'];
    }
    
    // Default capacity if not specified
    return 10.0;
  }

  /**
   * Validate item data structure.
   *
   * @param array $item
   *   Item to validate.
   *
   * @throws \InvalidArgumentException
   *   If item is invalid.
   */
  protected function validateItemData(array $item): void {
    if (empty($item['id'])) {
      throw new \InvalidArgumentException('Item must have an id');
    }
    if (empty($item['name'])) {
      throw new \InvalidArgumentException('Item must have a name');
    }
  }

  /**
   * Validate owner exists.
   *
   * @param string $owner_id
   *   Character, container, or room ID.
   * @param string $owner_type
   *   'character', 'container', or 'room'.
   *
   * @throws \InvalidArgumentException
   *   If owner not found.
   */
  protected function validateOwner(string $owner_id, string $owner_type): void {
    if (!in_array($owner_type, ['character', 'container', 'room'], TRUE)) {
      throw new \InvalidArgumentException("Invalid owner type: {$owner_type}");
    }

    if ($owner_type === 'character') {
      try {
        $this->characterStateService->getState($owner_id);
      }
      catch (\Exception) {
        throw new \InvalidArgumentException("Character not found: {$owner_id}");
      }
    }
    elseif ($owner_type === 'container') {
      $exists = $this->database->select('dc_campaign_item_instances', 'i')
        ->fields('i', ['item_instance_id'])
        ->condition('item_instance_id', $owner_id)
        ->execute()
        ->fetchField();

      if (!$exists) {
        throw new \InvalidArgumentException("Container not found: {$owner_id}");
      }
    }
    elseif ($owner_type === 'room') {
      // Rooms have unlimited capacity, but verify they exist
      $exists = $this->database->select('dc_dungeon_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('room_id', $owner_id)
        ->execute()
        ->fetchField();

      if (!$exists) {
        throw new \InvalidArgumentException("Room not found: {$owner_id}");
      }
    }
  }

  /**
   * Validate transfer permissions.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   *
   * @throws \Exception
   *   If user lacks permission.
   */
  protected function validateTransferPermission(
    string $owner_id,
    string $owner_type
  ): void {
    if ($owner_type === 'character') {
      $uid = $this->currentUser->id();
      $owner_uid = $this->database->select('dc_campaign_characters', 'c')
        ->fields('c', ['uid'])
        ->condition('id', $owner_id)
        ->execute()
        ->fetchField();

      if ($owner_uid && $owner_uid != $uid) {
        throw new \Exception('You do not have permission to modify this character\'s inventory');
      }
    }
  }

  /**
   * Create item instance record.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param array $item
   *   Item data.
   * @param string $location
   *   Item location.
   * @param int $quantity
   *   Item quantity.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return string
   *   Item instance ID.
   */
  protected function createItemInstance(
    string $owner_id,
    string $owner_type,
    array $item,
    string $location,
    int $quantity,
    ?int $campaign_id
  ): string {
    $item_instance_id = uniqid('item_', TRUE);

    $this->database->insert('dc_campaign_item_instances')
      ->fields([
        'campaign_id' => $campaign_id ?? 0,
        'item_instance_id' => $item_instance_id,
        'item_id' => $item['id'] ?? '',
        'location_type' => $location,
        'location_ref' => $owner_id,
        'quantity' => $quantity,
        'state_data' => json_encode($item),
        'created' => time(),
        'updated' => time(),
      ])
      ->execute();

    return $item_instance_id;
  }

  /**
   * Create a moved item instance record that preserves state metadata.
   */
  protected function createTransferredItemInstance(
    string $owner_id,
    string $location_type,
    array $source_item_row,
    int $quantity,
    ?int $campaign_id,
    string $transaction_id
  ): string {
    $item_instance_id = uniqid('item_', TRUE);
    $state = json_decode($source_item_row['state_data'] ?? '{}', TRUE) ?: [];
    $state['_transfer'] = [
      'transaction_id' => $transaction_id,
      'moved_from_instance_id' => $source_item_row['item_instance_id'] ?? '',
      'moved_at' => date('c'),
    ];

    $this->database->insert('dc_campaign_item_instances')
      ->fields([
        'campaign_id' => $campaign_id ?? (int) ($source_item_row['campaign_id'] ?? 0),
        'item_instance_id' => $item_instance_id,
        'item_id' => $source_item_row['item_id'] ?? ($state['id'] ?? ''),
        'location_type' => $location_type,
        'location_ref' => $owner_id,
        'quantity' => $quantity,
        'state_data' => json_encode($state),
        'created' => time(),
        'updated' => time(),
      ])
      ->execute();

    return $item_instance_id;
  }

  /**
   * Calculate bulk for item(s).
   *
   * @param array $item_state
   *   Item state including bulk info.
   * @param int $quantity
   *   Quantity being calculated.
   *
   * @return float
   *   Total bulk for this item quantity.
   */
  protected function calculateItemBulk(array $item_state, int $quantity = 1): float {
    $bulk_value = $item_state['bulk'] ?? 'light';
    $bulk = self::BULK_MAP[$bulk_value] ?? 0;
    return ($bulk * $quantity);
  }

  /**
   * Check if an item is a container.
   *
   * @param array $item_state
   *   Item state data.
   *
   * @return bool
   *   TRUE if item has container_stats defined.
   */
  protected function isContainer(array $item_state): bool {
    return !empty($item_state['container_stats']) && 
           !empty($item_state['container_stats']['capacity']);
  }

  /**
   * Get container properties from item state.
   *
   * @param array $item_state
   *   Item state data.
   *
   * @return array
   *   Container properties or empty array if not a container.
   */
  protected function getContainerProperties(array $item_state): array {
    if (!$this->isContainer($item_state)) {
      return [];
    }

    $stats = $item_state['container_stats'];
    return [
      'capacity' => (float) $stats['capacity'],
      'capacity_reduction' => (float) ($stats['capacity_reduction'] ?? 1.0),
      'can_lock' => (bool) ($stats['can_lock'] ?? FALSE),
      'lock_dc' => (int) ($stats['lock_dc'] ?? 0),
      'is_locked' => (bool) ($stats['is_locked'] ?? FALSE),
      'access_time' => $stats['access_time'] ?? 'interact',
      'water_resistant' => (bool) ($stats['water_resistant'] ?? FALSE),
      'extradimensional' => (bool) ($stats['extradimensional'] ?? FALSE),
      'container_type' => $stats['container_type'] ?? 'backpack',
    ];
  }

  /**
   * Convert owner type to location type for database.
   *
   * @param string $owner_type
   *   'character', 'container', or 'room'.
   *
   * @return string
   *   Location type for database.
   */
  protected function ownerTypeToLocationType(string $owner_type): string {
    $map = [
      'character' => 'inventory',
      'container' => 'container',
      'room' => 'room',
    ];
    return $map[$owner_type] ?? 'inventory';
  }

  /**
   * Default item location for a destination owner type.
   */
  protected function defaultLocationTypeForOwnerType(string $owner_type): string {
    return match ($owner_type) {
      'character' => 'carried',
      'container' => 'container',
      'room' => 'room',
      default => 'carried',
    };
  }

  /**
   * Normalize a storage reference for transfer use.
   */
  protected function normalizeStorageReference(array $storage, string $label): array {
    $owner_id = (string) ($storage['owner_id'] ?? '');
    $owner_type = (string) ($storage['owner_type'] ?? '');
    if ($owner_id === '' || $owner_type === '') {
      throw new \InvalidArgumentException("{$label} storage must include owner_id and owner_type");
    }

    if (!in_array($owner_type, ['character', 'container', 'room'], TRUE)) {
      throw new \InvalidArgumentException("Invalid {$label} owner type: {$owner_type}");
    }

    $location_type = $storage['location_type'] ?? NULL;
    if ($location_type !== NULL && !is_string($location_type)) {
      throw new \InvalidArgumentException("{$label} location_type must be a string when provided");
    }

    if ($location_type === NULL && $label === 'destination') {
      $location_type = $this->defaultLocationTypeForOwnerType($owner_type);
    }

    return [
      'owner_id' => $owner_id,
      'owner_type' => $owner_type,
      'location_type' => $location_type,
    ];
  }

  /**
   * Load the source item record being transferred.
   */
  protected function loadTransferItemRecord(string $item_instance_id, array $source, ?int $campaign_id = NULL): array {
    $query = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('item_instance_id', $item_instance_id)
      ->condition('location_ref', $source['owner_id']);

    if ($campaign_id !== NULL) {
      $query->condition('campaign_id', $campaign_id);
    }
    if (!empty($source['location_type'])) {
      $query->condition('location_type', $source['location_type']);
    }

    $row = $query->execute()->fetchAssoc();
    if (!$row) {
      throw new \InvalidArgumentException("Item instance not found in source storage: {$item_instance_id}");
    }

    return $row;
  }

  /**
   * Verify preconditions before mutating the transfer transaction.
   */
  protected function verifyTransferPreconditions(
    array $source,
    array $dest,
    array $source_item_row,
    int $quantity,
    ?int $campaign_id = NULL
  ): array {
    $errors = [];
    $source_qty = (int) ($source_item_row['quantity'] ?? 0);
    if ($source_qty < $quantity) {
      $errors[] = "Cannot transfer {$quantity} items; only {$source_qty} available.";
    }

    $item_state = json_decode($source_item_row['state_data'] ?? '{}', TRUE) ?: [];
    $item_bulk = $this->calculateItemBulk($item_state, $quantity);
    $dest_capacity = $this->getInventoryCapacity($dest['owner_id'], $dest['owner_type']);
    $dest_current_bulk = $this->calculateCurrentBulk($dest['owner_id'], $dest['owner_type'], $campaign_id);
    if (($dest_current_bulk + $item_bulk) > $dest_capacity) {
      $errors[] = "Transfer would exceed destination capacity (current: {$dest_current_bulk}, capacity: {$dest_capacity}, item bulk: {$item_bulk}).";
    }

    if ($dest['owner_type'] === 'character' && !in_array($dest['location_type'], ['carried', 'equipped', 'worn', 'stashed'], TRUE)) {
      $errors[] = 'Character destination location_type must be one of carried, equipped, worn, or stashed.';
    }

    if ($dest['owner_type'] !== 'character' && $dest['location_type'] !== $this->defaultLocationTypeForOwnerType($dest['owner_type'])) {
      $errors[] = "Destination {$dest['owner_type']} storage must use location_type '{$this->defaultLocationTypeForOwnerType($dest['owner_type'])}'.";
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Apply the transfer mutation inside the active database transaction.
   */
  protected function applyTransferMutation(
    array $source,
    array $dest,
    array $source_item_row,
    int $quantity,
    ?int $campaign_id,
    string $transaction_id
  ): array {
    $source_qty = (int) ($source_item_row['quantity'] ?? 0);
    $source_location_type = (string) ($source_item_row['location_type'] ?? $this->defaultLocationTypeForOwnerType($source['owner_type']));
    $dest_location_type = (string) ($dest['location_type'] ?? $this->defaultLocationTypeForOwnerType($dest['owner_type']));
    $same_storage = $source['owner_id'] === $dest['owner_id'] && $source['owner_type'] === $dest['owner_type'];
    $state = json_decode($source_item_row['state_data'] ?? '{}', TRUE) ?: [];
    $item_name = $state['name'] ?? ($source_item_row['item_id'] ?? 'Item');

    $expected_source_quantity = max(0, $source_qty - $quantity);
    $moved_item_instance_id = (string) $source_item_row['item_instance_id'];

    if ($same_storage && $source_location_type === $dest_location_type) {
      return [
        'source_owner' => $source,
        'dest_owner' => $dest,
        'source_item_instance_id' => $source_item_row['item_instance_id'],
        'moved_item_instance_id' => $moved_item_instance_id,
        'expected_source_quantity' => $source_qty,
        'expected_destination_quantity' => $source_qty,
        'item_id' => $source_item_row['item_id'] ?? '',
        'item_name' => $item_name,
      ];
    }

    if ($quantity === $source_qty) {
      $this->database->update('dc_campaign_item_instances')
        ->fields([
          'location_ref' => $dest['owner_id'],
          'location_type' => $dest_location_type,
          'updated' => time(),
        ])
        ->condition('item_instance_id', $source_item_row['item_instance_id'])
        ->execute();

      $expected_source_quantity = 0;
    }
    else {
      $this->database->update('dc_campaign_item_instances')
        ->fields([
          'quantity' => $expected_source_quantity,
          'updated' => time(),
        ])
        ->condition('item_instance_id', $source_item_row['item_instance_id'])
        ->execute();

      $moved_item_instance_id = $this->createTransferredItemInstance(
        $dest['owner_id'],
        $dest_location_type,
        $source_item_row,
        $quantity,
        $campaign_id,
        $transaction_id
      );
    }

    return [
      'source_owner' => $source,
      'dest_owner' => $dest,
      'source_item_instance_id' => $source_item_row['item_instance_id'],
      'moved_item_instance_id' => $moved_item_instance_id,
      'expected_source_quantity' => $expected_source_quantity,
      'expected_destination_quantity' => $quantity,
      'item_id' => $source_item_row['item_id'] ?? '',
      'item_name' => $item_name,
    ];
  }

  /**
   * Verify source and destination state after transfer mutation/write.
   */
  protected function verifyTransferPostconditions(
    array $move_result,
    array $source_snapshot,
    array $dest_snapshot,
    bool $after_write
  ): array {
    $errors = [];
    $expected_source_quantity = (int) ($move_result['expected_source_quantity'] ?? 0);
    $expected_dest_quantity = (int) ($move_result['expected_destination_quantity'] ?? 0);

    if ((int) ($source_snapshot['tracked_item_quantity'] ?? 0) !== $expected_source_quantity) {
      $errors[] = $after_write
        ? 'Source storage verification failed after write.'
        : 'Source storage verification failed after mutation.';
    }

    if ((int) ($dest_snapshot['tracked_item_quantity'] ?? 0) !== $expected_dest_quantity) {
      $errors[] = $after_write
        ? 'Destination storage verification failed after write.'
        : 'Destination storage verification failed after mutation.';
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Build a verification snapshot for a storage object.
   */
  protected function buildStorageSnapshot(array $storage, ?int $campaign_id = NULL, ?string $tracked_item_instance_id = NULL): array {
    $query = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['item_instance_id', 'quantity'])
      ->condition('location_ref', $storage['owner_id']);

    if ($campaign_id !== NULL) {
      $query->condition('campaign_id', $campaign_id);
    }
    if (!empty($storage['location_type']) && $storage['owner_type'] !== 'character') {
      $query->condition('location_type', $storage['location_type']);
    }

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $total_quantity = 0;
    $tracked_quantity = 0;
    foreach ($rows as $row) {
      $row_quantity = (int) ($row['quantity'] ?? 0);
      $total_quantity += $row_quantity;
      if ($tracked_item_instance_id !== NULL && ($row['item_instance_id'] ?? '') === $tracked_item_instance_id) {
        $tracked_quantity += $row_quantity;
      }
    }

    return [
      'owner_id' => $storage['owner_id'],
      'owner_type' => $storage['owner_type'],
      'location_type' => $storage['location_type'],
      'item_rows' => count($rows),
      'total_quantity' => $total_quantity,
      'tracked_item_instance_id' => $tracked_item_instance_id,
      'tracked_item_quantity' => $tracked_quantity,
      'total_bulk' => $this->calculateCurrentBulk($storage['owner_id'], $storage['owner_type'], $campaign_id),
      'captured_at' => date('c'),
    ];
  }

  /**
   * Get items in a container.
   *
   * @param string $container_id
   *   Container ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Container inventory.
   */
  protected function getContainerInventory(string $container_id, ?int $campaign_id = NULL): array {
    $items = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('location_ref', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return [
      'items' => array_map(function ($item) {
        return [
          'item_instance_id' => $item['item_instance_id'],
          'item_id' => $item['item_id'],
          'quantity' => (int) $item['quantity'],
          'state' => json_decode($item['state_data'], TRUE),
        ];
      }, $items),
      'totalBulk' => $this->calculateCurrentBulk($container_id, 'container'),
    ];
  }

  /**
   * Normalize inventory response format.
   *
   * @param array $inventory
   *   Raw inventory from character state.
   *
   * @return array
   *   Normalized response.
   */
  protected function normalizeInventoryResponse(array $inventory): array {
    return [
      'worn' => $inventory['worn'] ?? [],
      'carried' => $inventory['carried'] ?? [],
      'currency' => $inventory['currency'] ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      'totalBulk' => (float) ($inventory['totalBulk'] ?? 0),
      'encumbrance' => $inventory['encumbrance'] ?? 'unencumbered',
    ];
  }

  /**
   * Sync character state inventory from item instances table.
   *
   * This ensures the character state JSON matches the item instances table
   * (source of truth). Called after any inventory modification.
   *
   * @param string $character_id
   *   Character ID.
   * @param int $campaign_id
   *   Campaign ID.
   */
  protected function syncCharacterStateInventory(
    string $character_id,
    ?int $campaign_id = NULL
  ): void {
    try {
      // Get current inventory from instances
      $inventory = $this->getCharacterInventoryFromInstances($character_id, $campaign_id);

      // Get character state
      $state = $this->characterStateService->getState($character_id, $campaign_id);

      // Update inventory section
      $state['inventory'] = $inventory;

      // Save back to character state
      $this->characterStateService->setState(
        $character_id,
        $state,
        NULL, // Don't enforce version check for sync
        $campaign_id
      );
    }
    catch (\Exception $e) {
      // Log but don't fail - inventory instances are source of truth
      \Drupal::logger('dungeoncrawler_content')->warning(
        'Failed to sync character state inventory for @char_id: @message',
        [
          '@char_id' => $character_id,
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Log inventory operation for audit trail.
   *
   * @param string $operation
   *   Operation type.
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $context
   *   Operation context.
   */
  protected function logInventoryOperation(
    string $operation,
    string $owner_id,
    string $owner_type,
    ?int $campaign_id,
    array $context
  ): void {
    $this->database->insert('dc_campaign_log')
      ->fields([
        'campaign_id' => $campaign_id ?? 0,
        'log_type' => 'inventory',
        'message' => "{$owner_type}:{$owner_id} - {$operation}",
        'context' => json_encode([
          'operation' => $operation,
          'owner_id' => $owner_id,
          'owner_type' => $owner_type,
          'uid' => $this->currentUser->id(),
          'timestamp' => date('c'),
          ...($context ?? []),
        ]),
        'created' => time(),
      ])
      ->execute();
  }

}
