<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Shared merchant backend for panel and chat commerce flows.
 */
class MerchantTransactionService {

  protected const MERCHANT_KEYWORDS = [
    'merchant',
    'vendor',
    'shop',
    'shopkeeper',
    'barkeep',
    'bartender',
    'keeper',
    'innkeeper',
    'tavern',
    'bar',
    'blacksmith',
    'smith',
    'armorer',
    'apothecary',
    'alchemist',
    'herbalist',
    'trader',
  ];

  protected Connection $database;
  protected MerchantBotService $merchantBotService;
  protected InventoryManagementService $inventoryManagementService;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    MerchantBotService $merchant_bot_service,
    InventoryManagementService $inventory_management_service
  ) {
    $this->database = $database;
    $this->merchantBotService = $merchant_bot_service;
    $this->inventoryManagementService = $inventory_management_service;
  }

  /**
   * Load the panel-facing merchant context.
   */
  public function getMerchantPanelContext(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    ?int $character_id = NULL
  ): array {
    $merchant = $this->loadMerchantNpc($campaign_id, $room_id, $merchant_ref);
    $stock = $this->buildMerchantStock($merchant);
    $player = $this->buildPlayerTradeContext($character_id, $campaign_id);

    return [
      'merchant' => $this->buildMerchantSummary($merchant),
      'stock' => $stock,
      'player' => $player,
    ];
  }

  /**
   * Search wider merchant catalogs when the current stock filter returns nothing.
   */
  public function searchMerchantCatalog(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    string $item_query
  ): array {
    $merchant = $this->loadMerchantNpc($campaign_id, $room_id, $merchant_ref);
    return $this->resolveMerchantCatalogSearchItems($merchant, $item_query);
  }

  /**
   * Execute an explicit panel transaction.
   */
  public function executePanelTransaction(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    array $payload = []
  ): array {
    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : NULL;
    $quantity = max(1, (int) ($payload['quantity'] ?? 1));
    $quote_only = !empty($payload['quote_only']);

    if (!in_array($action, ['buy', 'sell'], TRUE)) {
      throw new \InvalidArgumentException('Merchant action must be buy or sell.');
    }

    $context = $this->getMerchantPanelContext($campaign_id, $room_id, $merchant_ref, $character_id);
    if ($character_id === NULL || $character_id <= 0) {
      return [
        'success' => FALSE,
        'status' => 'blocked',
        'message' => 'Select a character before trading.',
        'context' => $context,
      ];
    }

    if ($action === 'buy') {
      $item_id = trim((string) ($payload['item_id'] ?? ''));
      $stock_item = $this->findStockItem($context['stock'] ?? [], $item_id);
      $merchant = NULL;
      if ($stock_item === NULL) {
        $merchant = $this->loadMerchantNpc($campaign_id, $room_id, $merchant_ref);
        $stock_item = $this->resolveMerchantCatalogSearchItem($merchant, $item_id);
      }
      if ($stock_item === NULL) {
        return [
          'success' => FALSE,
          'status' => 'blocked',
          'message' => 'That item is not available from this merchant right now.',
          'context' => $context,
        ];
      }

      $price_cp = (int) ($stock_item['price_cp'] ?? 0) * $quantity;
      if ($quote_only) {
        return [
          'success' => TRUE,
          'status' => 'quoted',
          'message' => sprintf(
            'I can sell %s for %s.',
            $this->formatQuantityLabel($quantity, (string) ($stock_item['name'] ?? 'the item')),
            $this->formatCpAmount($price_cp)
          ),
          'context' => $context,
        ];
      }

      $result = $this->inventoryManagementService->purchaseItem(
        (string) $character_id,
        $this->buildPurchasableItem($stock_item),
        'downtime',
        $quantity,
        $campaign_id
      );
      if (empty($result['success'])) {
        return [
          'success' => FALSE,
          'status' => 'blocked',
          'message' => (string) ($result['message'] ?? 'The transaction failed.'),
          'context' => $context,
        ];
      }

      return [
        'success' => TRUE,
        'status' => 'completed_purchase',
        'message' => $this->buildMerchantPurchaseMessage(
          $merchant ?? $this->loadMerchantNpc($campaign_id, $room_id, $merchant_ref),
          $stock_item,
          $quantity,
          $price_cp
        ),
        'context' => $this->getMerchantPanelContext($campaign_id, $room_id, $merchant_ref, $character_id),
      ];
    }

    $item_instance_id = trim((string) ($payload['item_instance_id'] ?? ''));
    $sellable_item = $this->findSellableItem($context['player']['sellable_inventory'] ?? [], $item_instance_id);
    if ($sellable_item === NULL) {
      return [
        'success' => FALSE,
        'status' => 'blocked',
        'message' => 'That item is not available to sell from this inventory.',
        'context' => $context,
      ];
    }
    if (!empty($sellable_item['blocked'])) {
      return [
        'success' => FALSE,
        'status' => 'blocked',
        'message' => (string) ($sellable_item['blocked_message'] ?? 'That item cannot be sold.'),
        'context' => $context,
      ];
    }
    if ($quantity > (int) ($sellable_item['quantity'] ?? 0)) {
      return [
        'success' => FALSE,
        'status' => 'blocked',
        'message' => 'You are not carrying that many copies to sell.',
        'context' => $context,
      ];
    }

    $offer_cp = (int) ($sellable_item['offer_cp'] ?? 0) * $quantity;
    if ($quote_only) {
      return [
        'success' => TRUE,
        'status' => 'quoted',
        'message' => sprintf(
          'I can take %s for %s.',
          $this->formatQuantityLabel($quantity, (string) ($sellable_item['name'] ?? 'the item')),
          $this->formatCpAmount($offer_cp)
        ),
        'context' => $context,
      ];
    }

    $transaction = $this->database->startTransaction();
    try {
      for ($i = 0; $i < $quantity; $i++) {
        $result = $this->inventoryManagementService->sellItem(
          (string) $character_id,
          'character',
          $item_instance_id,
          FALSE,
          $campaign_id,
          'downtime'
        );
        if (empty($result['success'])) {
          throw new \RuntimeException((string) ($result['message'] ?? 'The sale failed.'));
        }
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      return [
        'success' => FALSE,
        'status' => 'blocked',
        'message' => rtrim($e->getMessage(), '. ') . '.',
        'context' => $context,
      ];
    }

    return [
      'success' => TRUE,
      'status' => 'completed_sale',
      'message' => sprintf(
        'Sold %s for %s.',
        $this->formatQuantityLabel($quantity, (string) ($sellable_item['name'] ?? 'the item')),
        $this->formatCpAmount($offer_cp)
      ),
      'context' => $this->getMerchantPanelContext($campaign_id, $room_id, $merchant_ref, $character_id),
    ];
  }

  /**
   * Execute a merchant request parsed from chat.
   */
  public function executeChatTransaction(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    string $player_message,
    ?int $character_id = NULL
  ): ?array {
    $plan = $this->merchantBotService->planMerchantTransaction($character_id, $player_message, $campaign_id);
    if ($plan === NULL) {
      return NULL;
    }

    $context = $this->getMerchantPanelContext($campaign_id, $room_id, $merchant_ref, $character_id);
    $status = (string) ($plan['status'] ?? '');
    if (in_array($status, ['needs_item', 'quoted', 'blocked'], TRUE)) {
      return [
        'success' => $status !== 'blocked',
        'status' => $status,
        'message' => (string) ($plan['message'] ?? ''),
        'context' => $context,
      ];
    }

    if ($character_id === NULL || $character_id <= 0) {
      return [
        'success' => FALSE,
        'status' => 'blocked',
        'message' => 'I need a specific character before I can complete that trade.',
        'context' => $context,
      ];
    }

    if ($status === 'ready_purchase') {
      $item = is_array($plan['item'] ?? NULL) ? $plan['item'] : NULL;
      if ($item === NULL) {
        return NULL;
      }

      $stock_item = $this->findStockItem($context['stock'] ?? [], (string) ($item['id'] ?? ''));
      $merchant = NULL;
      if ($stock_item === NULL) {
        $merchant = $this->loadMerchantNpc($campaign_id, $room_id, $merchant_ref);
        $stock_item = $this->resolveMerchantCatalogSearchItem($merchant, (string) ($item['id'] ?? ''));
      }
      if ($stock_item === NULL) {
        return [
          'success' => FALSE,
          'status' => 'blocked',
          'message' => 'I do not carry that item today.',
          'context' => $context,
        ];
      }

      $quantity = max(1, (int) ($plan['quantity'] ?? 1));
      $result = $this->inventoryManagementService->purchaseItem(
        (string) $character_id,
        $this->buildPurchasableItem($stock_item),
        'downtime',
        $quantity,
        $campaign_id
      );
      if (empty($result['success'])) {
        return [
          'success' => FALSE,
          'status' => 'blocked',
          'message' => (string) ($result['message'] ?? 'The transaction failed.'),
          'context' => $context,
        ];
      }

      return [
        'success' => TRUE,
        'status' => 'completed_purchase',
        'message' => sprintf(
          'Sold %s to you for %s.',
          $this->formatQuantityLabel($quantity, (string) ($stock_item['name'] ?? 'the item')),
          $this->formatCpAmount((int) ($stock_item['price_cp'] ?? 0) * $quantity)
        ),
        'context' => $this->getMerchantPanelContext($campaign_id, $room_id, $merchant_ref, $character_id),
      ];
    }

    if ($status === 'ready_sale') {
      $transaction = $this->database->startTransaction();
      try {
        foreach (($plan['sale_units'] ?? []) as $sale_unit) {
          $item_instance_id = (string) ($sale_unit['item_instance_id'] ?? '');
          $quantity = max(1, (int) ($sale_unit['quantity'] ?? 1));
          for ($i = 0; $i < $quantity; $i++) {
            $result = $this->inventoryManagementService->sellItem(
              (string) $character_id,
              'character',
              $item_instance_id,
              FALSE,
              $campaign_id,
              'downtime'
            );
            if (empty($result['success'])) {
              throw new \RuntimeException((string) ($result['message'] ?? 'The sale failed.'));
            }
          }
        }
      }
      catch (\Throwable $e) {
        $transaction->rollBack();
        return [
          'success' => FALSE,
          'status' => 'blocked',
          'message' => rtrim($e->getMessage(), '. ') . '.',
          'context' => $context,
        ];
      }

      return [
        'success' => TRUE,
        'status' => 'completed_sale',
        'message' => sprintf(
          'Bought %s from you for %s.',
          $this->formatQuantityLabel(
            max(1, (int) ($plan['quantity'] ?? 1)),
            (string) ($plan['item_name'] ?? 'the goods')
          ),
          $this->formatCpAmount((int) ($plan['offer_cp'] ?? 0))
        ),
        'context' => $this->getMerchantPanelContext($campaign_id, $room_id, $merchant_ref, $character_id),
      ];
    }

    return NULL;
  }

  /**
   * Load and validate a merchant NPC in the active room.
   */
  protected function loadMerchantNpc(int $campaign_id, string $room_id, string $merchant_ref): array {
    $records = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', [
        'id',
        'character_id',
        'instance_id',
        'name',
        'location_type',
        'location_ref',
        'character_data',
        'state_data',
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->condition('location_type', 'room')
      ->condition('location_ref', $room_id)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $merchant = NULL;
    foreach ($records as $record) {
      $state = json_decode($record['state_data'] ?? '{}', TRUE) ?: [];
      $character = json_decode($record['character_data'] ?? '{}', TRUE) ?: [];
      $refs = $this->collectMerchantRefs($record, $state, $character);
      if (in_array($merchant_ref, $refs, TRUE)) {
        $record['decoded_state'] = $state;
        $record['decoded_character'] = $character;
        $merchant = $record;
        break;
      }
    }

    if (!is_array($merchant)) {
      throw new \InvalidArgumentException('Merchant not found in the active room.');
    }

    $state = $merchant['decoded_state'] ?? [];
    $character = $merchant['decoded_character'] ?? [];
    if (!$this->npcLooksLikeMerchant($state, $character)) {
      throw new \InvalidArgumentException('That NPC is not configured as a merchant.');
    }

    return $merchant;
  }

  /**
   * Determine whether an NPC should expose merchant controls.
   */
  protected function npcLooksLikeMerchant(array $state, array $character): bool {
    $merchant_flags = [
      $state['merchant']['enabled'] ?? NULL,
      $state['merchant_enabled'] ?? NULL,
      $state['inventory']['merchant'] ?? NULL,
      $character['merchant']['enabled'] ?? NULL,
      $character['merchant_enabled'] ?? NULL,
    ];
    foreach ($merchant_flags as $flag) {
      if ($flag === TRUE || $flag === 1 || $flag === '1') {
        return TRUE;
      }
    }

    if ($this->extractExplicitStockEntries($state, $character) !== []) {
      return TRUE;
    }

    $descriptor = $this->buildMerchantDescriptor($state, $character);

    foreach (self::MERCHANT_KEYWORDS as $keyword) {
      if ($descriptor !== '' && str_contains($descriptor, $keyword)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Build merchant summary payload.
   */
  protected function buildMerchantSummary(array $merchant): array {
    $state = $merchant['decoded_state'] ?? [];
    $character = $merchant['decoded_character'] ?? [];
    $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];
    $profile = $this->resolveMerchantProfile($merchant);
    $merchant_config = $this->resolveMerchantBehaviorConfig($merchant);
    $summary = trim((string) ($merchant_config['greeting'] ?? $metadata['description'] ?? $state['description'] ?? $character['description'] ?? ''));
    return [
      'id' => (string) ($merchant['id'] ?? ''),
      'entity_ref' => $this->resolveMerchantEntityRef($merchant),
      'merchant_ref' => (string) ($merchant['instance_id'] ?? $merchant['id'] ?? ''),
      'name' => trim((string) ($metadata['display_name'] ?? $metadata['name'] ?? $merchant['name'] ?? $character['name'] ?? 'Merchant')),
      'role' => trim((string) ($metadata['role'] ?? $metadata['occupation'] ?? $profile['label'] ?? 'Merchant')),
      'summary' => $summary,
      'profile' => $profile['key'],
      'stock_mode' => $profile['stock_mode'],
    ];
  }

  /**
   * Build current player context for merchant rendering.
   */
  protected function buildPlayerTradeContext(?int $character_id, int $campaign_id): array {
    if ($character_id === NULL || $character_id <= 0) {
      return [
        'character_id' => NULL,
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
        'currency_cp' => 0,
        'currency_label' => '0 cp',
        'inventory' => [],
        'sellable_inventory' => [],
      ];
    }

    $inventory = $this->inventoryManagementService->getInventory((string) $character_id, 'character', $campaign_id);
    $currency = is_array($inventory['currency'] ?? NULL) ? $inventory['currency'] : ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    $currency_cp = $this->currencyArrayToCp($currency);

    return [
      'character_id' => $character_id,
      'currency' => $currency,
      'currency_cp' => $currency_cp,
      'currency_label' => $this->formatCpAmount($currency_cp),
      'inventory' => $inventory,
      'sellable_inventory' => $this->buildSellableInventory($inventory),
    ];
  }

  /**
   * Build sellable inventory list from the current inventory payload.
   */
  protected function buildSellableInventory(array $inventory): array {
    $items = [];

    foreach ($this->flattenInventoryItems($inventory) as $item) {
      $quantity = max(1, (int) ($item['quantity'] ?? 1));
      $price_gp = (float) ($item['price_gp'] ?? 0.0);
      $inventory_metadata = is_array($item['inventory_metadata'] ?? NULL) ? $item['inventory_metadata'] : [];
      $subtype = (string) ($item['subtype'] ?? $item['item_subtype'] ?? '');
      $item_id = (string) ($item['id'] ?? $item['item_id'] ?? '');
      $catalog_item = [];
      if ($item_id !== '') {
        $catalog_item = EquipmentCatalogService::CATALOG[$item_id] ?? $this->merchantBotService->lookupItem($item_id) ?? [];
      }
      elseif (!empty($item['name'])) {
        $catalog_item = $this->merchantBotService->lookupItem((string) $item['name']) ?? [];
      }
      $blocked = !empty($item['sell_taboo']);
      $is_full_price = in_array($subtype, InventoryManagementService::FULL_PRICE_SUBTYPES, TRUE);
      $offer_cp = (int) round(($is_full_price ? $price_gp : ($price_gp / 2.0)) * 100);

      $items[] = [
        'item_instance_id' => (string) ($item['item_instance_id'] ?? ''),
        'item_id' => $item_id,
        'name' => (string) ($item['name'] ?? 'Item'),
        'type' => (string) ($item['type'] ?? $item['item_type'] ?? ''),
        'subtype' => $subtype !== '' ? $subtype : (string) ($catalog_item['subtype'] ?? $catalog_item['item_subtype'] ?? ''),
        'quantity' => $quantity,
        'bulk' => (string) ($item['bulk'] ?? ''),
        'level' => isset($item['level']) ? (int) $item['level'] : (isset($catalog_item['level']) ? (int) $catalog_item['level'] : 0),
        'description' => trim((string) ($item['description'] ?? $inventory_metadata['description'] ?? $catalog_item['description'] ?? '')),
        'offer_cp' => $offer_cp,
        'offer_label' => $this->formatCpAmount($offer_cp),
        'blocked' => $blocked,
        'blocked_message' => $blocked
          ? (string) ($item['sell_taboo_message'] ?? 'This item has a sell taboo. A GM must authorize its sale.')
          : '',
        'catalog_item' => is_array($catalog_item) ? $catalog_item : [],
      ];
    }

    usort($items, static function (array $left, array $right): int {
      return [$left['blocked'] ? 1 : 0, $left['name']] <=> [$right['blocked'] ? 1 : 0, $right['name']];
    });

    return $items;
  }

  /**
   * Flatten nested inventory groups into a single item list.
   */
  protected function flattenInventoryItems(array $inventory): array {
    $items = [];
    foreach (['carried', 'equipped', 'stashed'] as $bucket) {
      foreach (($inventory[$bucket] ?? []) as $item) {
        if (is_array($item)) {
          $items[] = $item;
        }
      }
    }

    $worn = is_array($inventory['worn'] ?? NULL) ? $inventory['worn'] : [];
    foreach (($worn['weapons'] ?? []) as $item) {
      if (is_array($item)) {
        $items[] = $item;
      }
    }
    foreach (['armor', 'shield'] as $slot_key) {
      if (is_array($worn[$slot_key] ?? NULL)) {
        $items[] = $worn[$slot_key];
      }
    }
    foreach (($worn['accessories'] ?? []) as $item) {
      if (is_array($item)) {
        $items[] = $item;
      }
    }

    return $items;
  }

  /**
   * Build merchant stock for the active NPC.
   */
  protected function buildMerchantStock(array $merchant): array {
    $state = $merchant['decoded_state'] ?? [];
    $character = $merchant['decoded_character'] ?? [];
    $profile = $this->resolveMerchantProfile($merchant);
    $explicit = $this->extractExplicitStockEntries($state, $character);

    $stock = [];
    if ($explicit !== []) {
      foreach ($explicit as $entry) {
        $normalized = $this->normalizeStockEntry($entry, $profile);
        if ($normalized !== NULL) {
          $stock[$normalized['item_id']] = $normalized;
        }
      }
    }
    else {
      foreach (EquipmentCatalogService::CATALOG as $item) {
        $item_id = (string) ($item['id'] ?? '');
        if ($item_id === '') {
          continue;
        }
        if ($profile['item_ids'] !== [] && !in_array($item_id, $profile['item_ids'], TRUE)) {
          continue;
        }
        $item_type = (string) ($item['type'] ?? '');
        if ($profile['types'] !== [] && !in_array($item_type, $profile['types'], TRUE)) {
          continue;
        }
        $normalized = $this->normalizeStockEntry($item, $profile);
        if ($normalized !== NULL) {
          $stock[$normalized['item_id']] = $normalized;
        }
      }
    }

    $stock = array_values($stock);
    usort($stock, static function (array $left, array $right): int {
      return [$left['price_cp'] ?? 0, $left['name']] <=> [$right['price_cp'] ?? 0, $right['name']];
    });

    if (($profile['stock_mode'] ?? '') === 'catalog_all') {
      return $stock;
    }

    return array_slice($stock, 0, 24);
  }

  /**
   * Normalize an explicit or catalog-backed stock entry.
   */
  protected function normalizeStockEntry(array|string $entry, array $profile): ?array {
    $source = 'catalog';
    $definition = NULL;
    $override = is_array($entry) ? $entry : [];

    if (is_string($entry)) {
      $definition = EquipmentCatalogService::CATALOG[$entry] ?? $this->merchantBotService->lookupItem($entry);
    }
    else {
      $item_key = (string) ($entry['item_id'] ?? $entry['id'] ?? '');
      $item_name = (string) ($entry['name'] ?? '');
      if ($item_key !== '') {
        $definition = EquipmentCatalogService::CATALOG[$item_key] ?? $this->merchantBotService->lookupItem($item_key);
      }
      elseif ($item_name !== '') {
        $definition = $this->merchantBotService->lookupItem($item_name);
      }
      $source = 'merchant_override';
    }

    if (!is_array($definition) || ($definition['price_gp'] ?? NULL) === NULL) {
      return NULL;
    }

    $price_cp = array_key_exists('price_cp', $override)
      ? max(0, (int) $override['price_cp'])
      : (int) round((float) ($override['price_gp'] ?? ($definition['price_gp'] ?? 0.0)) * 100 * (float) ($override['price_modifier'] ?? $profile['price_modifier']));

    return [
      'item_id' => (string) ($definition['id'] ?? ''),
      'name' => (string) ($override['name'] ?? $definition['name'] ?? 'Item'),
      'type' => (string) ($definition['type'] ?? ''),
      'subtype' => (string) ($definition['subtype'] ?? $definition['item_subtype'] ?? ''),
      'bulk' => (string) ($definition['bulk'] ?? ''),
      'level' => isset($definition['level']) ? (int) $definition['level'] : 0,
      'description' => trim((string) ($override['description'] ?? $definition['description'] ?? '')),
      'price_cp' => $price_cp,
      'price_label' => $this->formatCpAmount($price_cp),
      'quantity_available' => isset($override['quantity']) || isset($override['quantity_available'])
        ? max(0, (int) ($override['quantity_available'] ?? $override['quantity']))
        : NULL,
      'source' => (string) ($override['source'] ?? $source),
      'catalog_item' => $definition,
    ];
  }

  /**
   * Resolve a wider-catalog search result that still fits this merchant profile.
   */
  protected function resolveMerchantCatalogSearchItem(array $merchant, string $item_query): ?array {
    $item_query = trim($item_query);
    if ($item_query === '') {
      return NULL;
    }

    $definition = $this->merchantBotService->lookupItem($item_query);
    $profile = $this->resolveMerchantProfile($merchant);
    return is_array($definition)
      ? $this->normalizeMerchantCatalogSearchDefinition($definition, $profile, $item_query)
      : NULL;
  }

  /**
   * Resolve all compatible wider-catalog search results for a merchant profile.
   *
   * @return array<int, array<string, mixed>>
   *   Compatible search results in ranked order.
   */
  protected function resolveMerchantCatalogSearchItems(array $merchant, string $item_query): array {
    $item_query = trim($item_query);
    if ($item_query === '') {
      return [];
    }

    $profile = $this->resolveMerchantProfile($merchant);
    $results = [];
    $seen = [];

    foreach ($this->merchantBotService->searchCatalogMatches($item_query, 12) as $definition) {
      if (!is_array($definition)) {
        continue;
      }
      $item_id = (string) ($definition['id'] ?? '');
      if ($item_id !== '' && isset($seen[$item_id])) {
        continue;
      }

      $normalized = $this->normalizeMerchantCatalogSearchDefinition($definition, $profile, $item_query);
      if ($normalized === NULL) {
        continue;
      }

      if ($item_id !== '') {
        $seen[$item_id] = TRUE;
      }
      $results[] = $normalized;
    }

    return $results;
  }

  /**
   * Normalize a catalog definition into a panel search result entry.
   */
  protected function normalizeMerchantCatalogSearchDefinition(array $definition, array $profile, string $item_query): ?array {
    if (($definition['price_gp'] ?? NULL) === NULL) {
      return NULL;
    }
    $profile_allowed = $this->merchantProfileAllowsCatalogItem($profile, $definition);
    $explicit_query_match = $this->isExplicitMerchantCatalogQueryMatch($definition, $item_query);
    if (!$profile_allowed && !$explicit_query_match) {
      return NULL;
    }

    $price_cp = (int) round((float) ($definition['price_gp'] ?? 0.0) * 100 * (float) ($profile['price_modifier'] ?? 1.0));
    $source = (string) ($definition['source'] ?? 'local');

    return [
      'item_id' => (string) ($definition['id'] ?? $item_query),
      'name' => (string) ($definition['name'] ?? $item_query),
      'type' => (string) ($definition['type'] ?? $definition['item_type'] ?? ''),
      'subtype' => (string) ($definition['subtype'] ?? $definition['item_subtype'] ?? ''),
      'bulk' => (string) ($definition['bulk'] ?? ''),
      'level' => isset($definition['level']) ? (int) $definition['level'] : 0,
      'description' => trim((string) ($definition['description'] ?? '')),
      'price_cp' => $price_cp,
      'price_label' => $this->formatCpAmount($price_cp),
      'quantity_available' => NULL,
      'source' => $source === 'aon' ? 'wider trade catalog' : 'catalog search',
      'catalog_item' => $definition,
      'search_result' => TRUE,
    ];
  }

  /**
   * Determine whether a definition is an explicit direct match for the query.
   */
  protected function isExplicitMerchantCatalogQueryMatch(array $definition, string $item_query): bool {
    $query_key = $this->normalizeMerchantSearchToken($item_query);
    if ($query_key === '') {
      return FALSE;
    }

    $name_key = $this->normalizeMerchantSearchToken((string) ($definition['name'] ?? ''));
    $id_key = $this->normalizeMerchantSearchToken((string) ($definition['id'] ?? $definition['item_id'] ?? ''));
    if ($name_key === '' && $id_key === '') {
      return FALSE;
    }

    if ($query_key === $name_key || $query_key === $id_key) {
      return TRUE;
    }

    $compact_query = str_replace(' ', '', $query_key);
    if ($compact_query === '') {
      return FALSE;
    }

    return $compact_query === str_replace(' ', '', $name_key)
      || $compact_query === str_replace(' ', '', $id_key);
  }

  /**
   * Normalize merchant search text for query/name/id comparisons.
   */
  protected function normalizeMerchantSearchToken(string $value): string {
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9\s\-_]+/u', ' ', $normalized) ?? '';
    $normalized = preg_replace('/[\-_]+/u', ' ', $normalized) ?? '';
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';
    return trim($normalized);
  }

  /**
   * Extract explicit stock definitions from merchant state.
   */
  protected function extractExplicitStockEntries(array $state, array $character): array {
    $candidates = [
      $state['merchant']['stock'] ?? NULL,
      $state['merchant_stock'] ?? NULL,
      $state['stock'] ?? NULL,
      $state['wares'] ?? NULL,
      $state['inventory']['stock'] ?? NULL,
      $character['merchant']['stock'] ?? NULL,
      $character['merchant_stock'] ?? NULL,
    ];

    foreach ($candidates as $candidate) {
      if (is_array($candidate) && $candidate !== []) {
        return array_values($candidate);
      }
    }

    return [];
  }

  /**
   * Resolve merchant stock profile from NPC metadata.
   */
  protected function resolveMerchantProfile(array $merchant): array {
    $state = $merchant['decoded_state'] ?? [];
    $character = $merchant['decoded_character'] ?? [];
    $descriptor = $this->buildMerchantDescriptor($state, $character);
    $merchant_config = $this->resolveMerchantBehaviorConfig($merchant);

    $profiles = [
      'blacksmith' => [
        'key' => 'blacksmith',
        'label' => 'Smithy stock',
        'types' => ['weapon', 'armor', 'shield', 'gear'],
        'item_ids' => [],
        'price_modifier' => 1.0,
        'stock_mode' => 'catalog_profile',
      ],
      'apothecary' => [
        'key' => 'apothecary',
        'label' => 'Alchemical stock',
        'types' => ['alchemical', 'consumable', 'gear'],
        'item_ids' => [],
        'price_modifier' => 1.0,
        'stock_mode' => 'catalog_profile',
      ],
      'innkeeper' => [
        'key' => 'innkeeper',
        'label' => 'Travel provisions',
        'types' => ['gear', 'consumable'],
        'item_ids' => [],
        'price_modifier' => 1.0,
        'stock_mode' => 'catalog_profile',
      ],
      'general' => [
        'key' => 'general',
        'label' => 'General goods',
        'types' => ['weapon', 'armor', 'shield', 'gear', 'alchemical', 'consumable'],
        'item_ids' => [],
        'price_modifier' => 1.0,
        'stock_mode' => 'catalog_profile',
      ],
      'catalog_all' => [
        'key' => 'catalog_all',
        'label' => 'Anything you can name',
        'types' => [],
        'item_ids' => [],
        'price_modifier' => 1.0,
        'stock_mode' => 'catalog_all',
      ],
    ];

    $profile_key = strtolower(trim((string) ($merchant_config['profile'] ?? $merchant_config['stock_mode'] ?? '')));
    if ($profile_key !== '' && isset($profiles[$profile_key])) {
      return $profiles[$profile_key];
    }
    if (!empty($merchant_config['all_items'])) {
      return $profiles['catalog_all'];
    }

    if (preg_match('/\b(?:blacksmith|smith|armorer|forge)\b/u', $descriptor)) {
      return $profiles['blacksmith'];
    }
    if (preg_match('/\b(?:apothecary|alchemist|herbalist)\b/u', $descriptor)) {
      return $profiles['apothecary'];
    }
    if (preg_match('/(?:innkeeper|barkeep|bartender|tavern[_ ]?keeper|tavern|bar|keeper)/u', $descriptor)) {
      return $profiles['innkeeper'];
    }

    return $profiles['general'];
  }

  /**
   * Determine whether a merchant profile can source a catalog-backed item.
   */
  protected function merchantProfileAllowsCatalogItem(array $profile, array $item): bool {
    if (($profile['stock_mode'] ?? '') === 'catalog_all') {
      return TRUE;
    }

    $item_id = (string) ($item['id'] ?? '');
    $item_type = (string) ($item['type'] ?? $item['item_type'] ?? '');
    $profile_item_ids = is_array($profile['item_ids'] ?? NULL) ? $profile['item_ids'] : [];
    $profile_types = is_array($profile['types'] ?? NULL) ? $profile['types'] : [];

    if ($profile_item_ids !== [] && in_array($item_id, $profile_item_ids, TRUE)) {
      return TRUE;
    }
    if ($profile_types !== [] && in_array($item_type, $profile_types, TRUE)) {
      return TRUE;
    }

    return $profile_item_ids === [] && $profile_types === [];
  }

  /**
   * Resolve merchant-specific behavior overrides from runtime state.
   */
  protected function resolveMerchantBehaviorConfig(array $merchant): array {
    $state = is_array($merchant['decoded_state'] ?? NULL) ? $merchant['decoded_state'] : [];
    $character = is_array($merchant['decoded_character'] ?? NULL) ? $merchant['decoded_character'] : [];
    $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];
    $state_merchant = is_array($state['merchant'] ?? NULL) ? $state['merchant'] : [];
    $character_merchant = is_array($character['merchant'] ?? NULL) ? $character['merchant'] : [];

    return array_filter([
      'profile' => (string) ($state_merchant['profile'] ?? $character_merchant['profile'] ?? $metadata['merchant_profile'] ?? ''),
      'stock_mode' => (string) ($state_merchant['stock_mode'] ?? $character_merchant['stock_mode'] ?? $metadata['merchant_stock_mode'] ?? ''),
      'greeting' => trim((string) ($state_merchant['greeting'] ?? $character_merchant['greeting'] ?? $metadata['merchant_greeting'] ?? '')),
      'purchase_taunt' => trim((string) ($state_merchant['purchase_taunt'] ?? $character_merchant['purchase_taunt'] ?? $metadata['merchant_purchase_taunt'] ?? '')),
      'all_items' => !empty($state_merchant['all_items']) || !empty($character_merchant['all_items']) || !empty($metadata['merchant_all_items']),
    ], static fn($value) => $value !== '' && $value !== NULL && $value !== FALSE);
  }

  /**
   * Build the player-facing purchase completion line for a merchant.
   */
  protected function buildMerchantPurchaseMessage(array $merchant, array $stock_item, int $quantity, int $price_cp): string {
    $merchant_config = $this->resolveMerchantBehaviorConfig($merchant);
    $message = sprintf(
      'Purchased %s for %s.',
      $this->formatQuantityLabel($quantity, (string) ($stock_item['name'] ?? 'the item')),
      $this->formatCpAmount($price_cp)
    );

    $taunt = trim((string) ($merchant_config['purchase_taunt'] ?? ''));
    if ($taunt === '') {
      return $message;
    }

    return $message . ' ' . $taunt;
  }

  /**
   * Build a normalized descriptor string for merchant detection/profile lookup.
   */
  protected function buildMerchantDescriptor(array $state, array $character): string {
    $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];

    return strtolower(trim(implode(' ', array_filter([
      (string) ($metadata['display_name'] ?? ''),
      (string) ($metadata['name'] ?? ''),
      (string) ($metadata['role'] ?? ''),
      (string) ($metadata['occupation'] ?? ''),
      (string) ($metadata['description'] ?? ''),
      (string) ($metadata['content_id'] ?? ''),
      (string) ($metadata['runtime_entity_id'] ?? ''),
      (string) ($state['content_id'] ?? ''),
      (string) ($state['runtime_entity_id'] ?? ''),
      (string) ($character['name'] ?? ''),
      (string) ($character['profile']['display_name'] ?? ''),
      (string) ($character['content_id'] ?? ''),
      (string) ($character['runtime_entity_id'] ?? ''),
    ]))));
  }

  /**
   * Collect aliases that may be used to refer to a merchant NPC.
   *
   * @return array<int, string>
   *   Distinct non-empty identifiers.
   */
  protected function collectMerchantRefs(array $merchant, array $state, array $character): array {
    $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];
    $raw_refs = [
      (string) ($merchant['instance_id'] ?? ''),
      (string) ($merchant['id'] ?? ''),
      (string) ($merchant['character_id'] ?? ''),
      (string) ($state['runtime_entity_id'] ?? ''),
      (string) ($state['content_id'] ?? ''),
      (string) ($metadata['runtime_entity_id'] ?? ''),
      (string) ($metadata['content_id'] ?? ''),
      (string) ($character['runtime_entity_id'] ?? ''),
      (string) ($character['content_id'] ?? ''),
    ];

    $refs = [];
    foreach ($raw_refs as $ref) {
      foreach ($this->expandMerchantRefAliases((string) $ref) as $alias) {
        $refs[$alias] = TRUE;
      }
    }

    return array_keys($refs);
  }

  /**
   * Expand runtime refs across underscore/hyphen variants used by the UI/DB.
   *
   * Hexmap payloads currently surface NPC instance ids like `npc-tavern_keeper`
   * while campaign runtime rows persist `npc_tavern_keeper`. Merchant matching
   * must accept both forms for the same actor.
   *
   * @return array<int, string>
   *   Distinct non-empty aliases for the supplied reference.
   */
  protected function expandMerchantRefAliases(string $ref): array {
    $ref = trim($ref);
    if ($ref === '') {
      return [];
    }

    $aliases = [$ref];
    if (str_contains($ref, '-')) {
      $aliases[] = str_replace('-', '_', $ref);
    }
    if (str_contains($ref, '_')) {
      $aliases[] = str_replace('_', '-', $ref);
    }
    if (preg_match('/^([a-z0-9]+)[_-](.+)$/i', $ref, $matches)) {
      $aliases[] = $matches[1] . '-' . $matches[2];
      $aliases[] = $matches[1] . '_' . $matches[2];
    }

    return array_values(array_unique(array_filter($aliases, static fn(string $value): bool => $value !== '')));
  }

  /**
   * Resolve the canonical content reference for merchant summary payloads.
   */
  protected function resolveMerchantEntityRef(array $merchant): string {
    $state = $merchant['decoded_state'] ?? [];
    $character = $merchant['decoded_character'] ?? [];
    $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];

    return (string) (
      $state['content_id']
      ?? $metadata['content_id']
      ?? $character['content_id']
      ?? $merchant['instance_id']
      ?? $merchant['id']
      ?? ''
    );
  }

  /**
   * Convert stock item back into a purchasable inventory definition.
   */
  protected function buildPurchasableItem(array $stock_item): array {
    $item = is_array($stock_item['catalog_item'] ?? NULL) ? $stock_item['catalog_item'] : [];
    $item['price_gp'] = round(((int) ($stock_item['price_cp'] ?? 0)) / 100, 2);
    return $item;
  }

  /**
   * Convert mixed currency keys into total copper pieces.
   */
  protected function currencyArrayToCp(array $currency): int {
    $rates = ['cp' => 1, 'sp' => 10, 'gp' => 100, 'pp' => 1000];
    $total = 0;
    foreach ($rates as $denomination => $rate) {
      $total += ((int) ($currency[$denomination] ?? 0)) * $rate;
    }
    return $total;
  }

  /**
   * Find a stock entry by item id.
   */
  protected function findStockItem(array $stock, string $item_id): ?array {
    foreach ($stock as $item) {
      if ((string) ($item['item_id'] ?? '') === $item_id) {
        return $item;
      }
    }
    return NULL;
  }

  /**
   * Find a sellable entry by item instance id.
   */
  protected function findSellableItem(array $items, string $item_instance_id): ?array {
    foreach ($items as $item) {
      if ((string) ($item['item_instance_id'] ?? '') === $item_instance_id) {
        return $item;
      }
    }
    return NULL;
  }

  /**
   * Format copper pieces into compact coin text.
   */
  protected function formatCpAmount(int $cp): string {
    if ($cp <= 0) {
      return '0 cp';
    }

    $parts = [];
    $pp = intdiv($cp, 1000);
    $cp -= $pp * 1000;
    $gp = intdiv($cp, 100);
    $cp -= $gp * 100;
    $sp = intdiv($cp, 10);
    $cp -= $sp * 10;

    if ($pp > 0) {
      $parts[] = $pp . ' pp';
    }
    if ($gp > 0) {
      $parts[] = $gp . ' gp';
    }
    if ($sp > 0) {
      $parts[] = $sp . ' sp';
    }
    if ($cp > 0) {
      $parts[] = $cp . ' cp';
    }

    return implode(' ', $parts);
  }

  /**
   * Format item quantity labels for merchant strings.
   */
  protected function formatQuantityLabel(int $quantity, string $item_name): string {
    $trimmed_name = trim($item_name);
    if ($quantity <= 1) {
      return '1 ' . $trimmed_name;
    }

    if (preg_match('/s$/i', $trimmed_name)) {
      return $quantity . ' ' . $trimmed_name;
    }

    return $quantity . ' ' . $trimmed_name . 's';
  }

}
