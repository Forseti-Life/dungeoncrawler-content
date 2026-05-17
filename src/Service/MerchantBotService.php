<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Resolves merchant buy/sell requests against local content and AoN fallback.
 */
class MerchantBotService {

  protected const AON_SEARCH_URL = 'https://elasticsearch.aonprd.com/aon/_search?stats=aggregations';
  protected const MERCHANT_KEYWORDS = [
    'buy', 'purchase', 'sell', 'price', 'cost', 'quote', 'rent', 'tab', 'wares',
    'trade in', 'looking for', 'how much for', 'how much is',
  ];

  protected Connection $database;
  protected ?ClientInterface $httpClient;
  protected ?LoggerInterface $logger;

  public function __construct(
    Connection $database,
    ?LoggerChannelFactoryInterface $logger_factory = NULL,
    ?ClientInterface $http_client = NULL
  ) {
    $this->database = $database;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory?->get('dungeoncrawler_chat');
  }

  /**
   * Build a grounded merchant reply when the player is clearly trading.
   */
  public function buildMerchantReply(string $player_message): ?string {
    $request = $this->extractMerchantRequest($player_message);
    if ($request === NULL) {
      return NULL;
    }

    if ($request['item_query'] === '') {
      return "Tell me the item and quantity, and I'll quote it cleanly.";
    }

    $match = $this->lookupItem($request['item_query']);
    if ($match === NULL) {
      return 'I do not have that item pinned down yet. Give me the exact name and I will check my stock and the wider trade catalogs.';
    }
    if (!array_key_exists('price_gp', $match) || $match['price_gp'] === NULL) {
      return 'That item does not have a listed market price, so I cannot quote or sell it normally.';
    }

    $quantity = max(1, (int) ($request['quantity'] ?? 1));
    $quoted_gp = ($match['price_gp'] ?? 0.0) * $quantity;
    $quoted_text = $this->formatGpAmount($quoted_gp);
    $source_note = ($match['source'] ?? 'local') === 'aon'
      ? ' I had to pull that from the wider trade catalogs.'
      : '';
    $level_note = isset($match['level']) && $match['level'] !== NULL && (int) $match['level'] > 0
      ? ' Level ' . (int) $match['level'] . '.'
      : '';
    $bulk_note = !empty($match['bulk'])
      ? ' Bulk ' . $match['bulk'] . '.'
      : '';

    if ($request['action'] === 'sell') {
      $offer_gp = round($quoted_gp * 0.5, 4);
      return sprintf(
        "I can take %s for %s.%s%s%s",
        $this->formatQuantityLabel($quantity, (string) $match['name']),
        $this->formatGpAmount($offer_gp),
        $level_note,
        $bulk_note,
        $source_note
      );
    }

    return sprintf(
      "I can sell %s for %s.%s%s%s",
      $this->formatQuantityLabel($quantity, (string) $match['name']),
      $quoted_text,
      $level_note,
      $bulk_note,
      $source_note
    );
  }

  /**
   * Plan a merchant transaction or quote for room-chat execution.
   */
  public function planMerchantTransaction(?int $character_id, string $player_message, ?int $campaign_id = NULL): ?array {
    $request = $this->extractMerchantRequest($player_message);
    if ($request === NULL) {
      return NULL;
    }

    if ($request['item_query'] === '') {
      return [
        'status' => 'needs_item',
        'message' => "Tell me the item and quantity, and I'll quote it cleanly.",
      ];
    }

    if ($request['action'] === 'sell') {
      if (!$request['commit']) {
        $match = $this->lookupItem($request['item_query']);
        if ($match === NULL) {
          return [
            'status' => 'blocked',
            'message' => 'I do not have that item pinned down yet. Give me the exact name and I will check my stock and the wider trade catalogs.',
          ];
        }
        if (!array_key_exists('price_gp', $match) || $match['price_gp'] === NULL) {
          return [
            'status' => 'blocked',
            'message' => 'That item does not have a listed market price, so I cannot quote or buy it normally.',
          ];
        }

        $offer_cp = (int) round(((float) ($match['price_gp'] ?? 0.0)) * 100 * max(1, (int) $request['quantity']) * 0.5);
        return [
          'status' => 'quoted',
          'message' => sprintf(
            'I can take %s for %s.',
            $this->formatQuantityLabel(max(1, (int) $request['quantity']), (string) ($match['name'] ?? $request['item_query'])),
            $this->formatCpAmount($offer_cp)
          ),
        ];
      }

      if ($character_id === NULL) {
        return [
          'status' => 'blocked',
          'message' => 'I need a specific character inventory before I can complete that sale.',
        ];
      }

      $sale_plan = $this->buildCharacterSalePlan($character_id, $request['item_query'], max(1, (int) $request['quantity']), $campaign_id);
      if ($sale_plan === NULL) {
        return [
          'status' => 'blocked',
          'message' => 'You are not carrying enough of that item to sell it here.',
        ];
      }

      if (!empty($sale_plan['taboo_message'])) {
        return [
          'status' => 'blocked',
          'message' => (string) $sale_plan['taboo_message'],
        ];
      }

      return [
        'status' => 'ready_sale',
        'message' => sprintf(
          'I can take %s for %s.',
          $this->formatQuantityLabel((int) $sale_plan['quantity'], (string) $sale_plan['item_name']),
          $this->formatCpAmount((int) $sale_plan['offer_cp'])
        ),
        'item_name' => (string) $sale_plan['item_name'],
        'quantity' => (int) $sale_plan['quantity'],
        'offer_cp' => (int) $sale_plan['offer_cp'],
        'sale_units' => $sale_plan['sale_units'],
      ];
    }

    $item = $this->lookupItem($request['item_query']);
    if ($item === NULL) {
      return [
        'status' => 'blocked',
        'message' => 'I do not have that item pinned down yet. Give me the exact name and I will check my stock and the wider trade catalogs.',
      ];
    }
    if (!array_key_exists('price_gp', $item) || $item['price_gp'] === NULL) {
      return [
        'status' => 'blocked',
        'message' => 'That item does not have a listed market price and cannot be bought normally.',
      ];
    }

    $quantity = max(1, (int) ($request['quantity'] ?? 1));
    $price_cp = (int) round(((float) ($item['price_gp'] ?? 0.0)) * 100 * $quantity);

    if (!$request['commit']) {
      return [
        'status' => 'quoted',
        'message' => sprintf(
          'I can sell %s for %s.',
          $this->formatQuantityLabel($quantity, (string) ($item['name'] ?? $request['item_query'])),
          $this->formatCpAmount($price_cp)
        ),
        'item' => $item,
        'quantity' => $quantity,
        'price_cp' => $price_cp,
      ];
    }

    if ($character_id === NULL) {
      return [
        'status' => 'blocked',
        'message' => 'I need a specific buyer before I can complete that purchase.',
      ];
    }

    $available_cp = $this->loadCharacterCurrencyCp($character_id);
    if ($available_cp < $price_cp) {
      return [
        'status' => 'blocked',
        'message' => sprintf(
          'You do not have enough coin for %s. Price: %s. Available: %s.',
          $this->formatQuantityLabel($quantity, (string) ($item['name'] ?? $request['item_query'])),
          $this->formatCpAmount($price_cp),
          $this->formatCpAmount($available_cp)
        ),
        'item' => $item,
        'quantity' => $quantity,
        'price_cp' => $price_cp,
      ];
    }

    return [
      'status' => 'ready_purchase',
      'message' => sprintf(
        'I can sell %s for %s.',
        $this->formatQuantityLabel($quantity, (string) ($item['name'] ?? $request['item_query'])),
        $this->formatCpAmount($price_cp)
      ),
      'item' => $item,
      'quantity' => $quantity,
      'price_cp' => $price_cp,
    ];
  }

  /**
   * Extract the trading intent, quantity, and requested item from free text.
   *
   * @return array{action:string, quantity:int, item_query:string, commit:bool}|null
   *   Parsed request, or NULL when the text is not merchant-focused.
   */
  public function extractMerchantRequest(string $player_message): ?array {
    $normalized = $this->normalizeText($player_message);
    if ($normalized === '' || !$this->containsMerchantLanguage($normalized)) {
      return NULL;
    }

    $action = preg_match('/\b(?:sell|sold|offload|trade in|trade|pawn)\b/u', $normalized) ? 'sell' : 'buy';
    $commit = $action === 'sell'
      ? (bool) preg_match('/\b(?:sell|offload|trade in|pawn)\b/u', $normalized)
      : (bool) preg_match('/\b(?:buy|purchase|get|acquire)\b/u', $normalized);
    $item_query = '';

    $patterns = [
      '/\b(?:i want to|i would like to|i d like to|let me|can i|could i|please)?\s*(?:buy|purchase|get|acquire|sell|trade in|trade|offload|pawn)\s+(.+)$/ui',
      '/\b(?:price|cost)\s+(?:for\s+|of\s+)?(.+)$/ui',
      '/\b(?:looking for|need)\s+(.+)$/ui',
      '/\bhow much\s+(?:for|is)\s+(.+)$/ui',
    ];
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $player_message, $matches)) {
        $item_query = trim((string) ($matches[1] ?? ''));
        break;
      }
    }

    if ($item_query === '' && preg_match('/\b(?:buy|purchase|sell|trade|price|cost)\b/ui', $player_message)) {
        return [
          'action' => $action,
          'quantity' => 1,
          'item_query' => '',
          'commit' => $commit,
        ];
      }

    $quantity = 1;
    if (preg_match('/^\s*(\d+)\s+(.+)$/u', $item_query, $matches)) {
      $quantity = max(1, (int) $matches[1]);
      $item_query = trim((string) $matches[2]);
    }

    $item_query = preg_replace('/^(?:a|an|the)\s+/ui', '', $item_query) ?? $item_query;
    $item_query = preg_replace('/\b(?:please|thanks|thank you|for me|today|right now|from you)\b/ui', '', $item_query) ?? $item_query;
    $item_query = preg_replace('/^[\s,.;:-]+|[\s,.;:-]+$/u', '', $item_query) ?? $item_query;

    return [
      'action' => $action,
      'quantity' => $quantity,
      'item_query' => trim($item_query),
      'commit' => $commit,
    ];
  }

  /**
   * Resolve an item from local content first, then AoN.
   */
  public function lookupItem(string $item_query): ?array {
    $item_query = trim($item_query);
    if ($item_query === '') {
      return NULL;
    }

    $local = $this->lookupLocalItem($item_query);
    if ($local !== NULL) {
      return $local;
    }

    return $this->lookupAonItem($item_query);
  }

  /**
   * Determine whether a line looks merchant-oriented.
   */
  public function containsMerchantLanguage(string $normalized_message): bool {
    foreach (self::MERCHANT_KEYWORDS as $keyword) {
      if (str_contains($normalized_message, $keyword)) {
        return TRUE;
      }
    }

    return (bool) preg_match('/\b(?:pay|paid|change)\b.+\b(?:for|with)\b/u', $normalized_message)
      || (bool) preg_match('/\b(?:coin|gold|silver|copper)\b.+\b(?:for|price|cost|buy|sell)\b/u', $normalized_message);
  }

  /**
   * Look for an item in local registry content and the embedded equipment catalog.
   */
  protected function lookupLocalItem(string $item_query): ?array {
    $query_key = $this->normalizeLookupKey($item_query);
    $best_catalog_match = NULL;
    $best_catalog_score = PHP_INT_MIN;

    foreach (EquipmentCatalogService::CATALOG as $item) {
      $score = $this->scoreLocalItemCandidate(
        $query_key,
        (string) ($item['name'] ?? ''),
        (string) ($item['id'] ?? '')
      );
      if ($score > $best_catalog_score) {
        $best_catalog_score = $score;
        $best_catalog_match = [
          'id' => (string) ($item['id'] ?? $this->slugify((string) ($item['name'] ?? $item_query))),
          'name' => (string) ($item['name'] ?? $item['id'] ?? $item_query),
          'item_type' => (string) ($item['item_type'] ?? $item['type'] ?? 'gear'),
          'type' => (string) ($item['type'] ?? $item['item_type'] ?? 'gear'),
          'price_gp' => (float) ($item['price_gp'] ?? 0.0),
          'bulk' => isset($item['bulk']) ? (string) $item['bulk'] : '',
          'level' => isset($item['level']) && is_numeric($item['level']) ? (int) $item['level'] : 0,
          'source' => 'local',
        ];
      }
    }

    if ($best_catalog_score > 0) {
      return $best_catalog_match;
    }

    try {
      $schema = $this->database->schema();
      if ($schema === NULL || !$schema->tableExists('dungeoncrawler_content_registry')) {
        return NULL;
      }
    }
    catch (\Throwable) {
      return NULL;
    }

    $name_like = '%' . $this->database->escapeLike($item_query) . '%';
    $id_like = '%' . $this->database->escapeLike(str_replace(' ', '-', $query_key)) . '%';
    $query = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'schema_data'])
      ->condition('content_type', 'item')
      ->range(0, 25);
    $group = $query->orConditionGroup()
      ->condition('name', $name_like, 'LIKE')
      ->condition('content_id', $id_like, 'LIKE');
    $rows = $query
      ->condition($group)
      ->execute()
      ->fetchAllAssoc('content_id');

    $best = NULL;
    $best_score = PHP_INT_MIN;
    foreach ($rows as $row) {
      $name = (string) ($row->name ?? '');
      $content_id = (string) ($row->content_id ?? '');
      $score = $this->scoreLocalItemCandidate($query_key, $name, $content_id);
      if ($score <= $best_score) {
        continue;
      }

      $schema_data = json_decode((string) ($row->schema_data ?? '{}'), TRUE);
      if (!is_array($schema_data)) {
        $schema_data = [];
      }
      $best = [
        'id' => $content_id !== '' ? $content_id : $this->slugify($name !== '' ? $name : $item_query),
        'name' => $name !== '' ? $name : $item_query,
        'item_type' => (string) ($schema_data['item_type'] ?? $schema_data['type'] ?? $this->mapItemTypeFromSchema($schema_data)),
        'type' => (string) ($schema_data['type'] ?? $schema_data['item_type'] ?? $this->mapItemTypeFromSchema($schema_data)),
        'price_gp' => $this->extractLocalPriceGp($schema_data),
        'bulk' => isset($schema_data['bulk']) ? (string) $schema_data['bulk'] : '',
        'level' => is_numeric($schema_data['level'] ?? NULL) ? (int) $schema_data['level'] : NULL,
        'source' => 'local',
      ];
      $best_score = $score;
    }

    return $best_score > 0 ? $best : NULL;
  }

  /**
   * Query AoN's public search endpoint for missing equipment.
   */
  protected function lookupAonItem(string $item_query): ?array {
    if ($this->httpClient === NULL) {
      return NULL;
    }

    $payload = [
      'size' => 8,
      '_source' => [
        'name', 'url', 'level', 'price', 'price_raw', 'bulk', 'bulk_raw',
        'rarity', 'category', 'item_category', 'summary', 'markdown',
      ],
      'query' => [
        'bool' => [
          'must' => [[
            'multi_match' => [
              'query' => $item_query,
              'fields' => ['name^6', 'item_category^2', 'category^2', 'markdown', 'summary'],
              'type' => 'best_fields',
            ],
          ]],
          'filter' => [[
            'terms' => [
              'category' => ['equipment', 'weapon', 'armor', 'shield'],
            ],
          ]],
        ],
      ],
    ];

    try {
      $response = $this->httpClient->request('POST', self::AON_SEARCH_URL, ['json' => $payload, 'timeout' => 8.0]);
      $decoded = json_decode((string) $response->getBody(), TRUE);
    }
    catch (GuzzleException|\Throwable $e) {
      $this->logger?->warning('AoN merchant lookup failed for @item: @error', [
        '@item' => $item_query,
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }

    $hits = $decoded['hits']['hits'] ?? [];
    if (!is_array($hits) || $hits === []) {
      return NULL;
    }

    $best = NULL;
    $best_score = PHP_INT_MIN;
    $query_key = $this->normalizeLookupKey($item_query);
    foreach ($hits as $hit) {
      $source = is_array($hit['_source'] ?? NULL) ? $hit['_source'] : [];
      $name = (string) ($source['name'] ?? '');
      $score = $this->scoreLocalItemCandidate($query_key, $name, '');
      if (!empty($source['price_raw'])) {
        $score += 5;
      }
      if ($score <= $best_score) {
        continue;
      }

      $best = [
        'id' => $this->slugify($name !== '' ? $name : $item_query),
        'name' => $name !== '' ? $name : $item_query,
        'item_type' => $this->mapAonItemType($source),
        'type' => $this->mapAonItemType($source),
        'price_gp' => $this->extractAonPriceGp($source),
        'bulk' => (string) ($source['bulk_raw'] ?? $source['bulk'] ?? ''),
        'level' => is_numeric($source['level'] ?? NULL) ? (int) $source['level'] : NULL,
        'source' => 'aon',
        'url' => isset($source['url']) ? (string) $source['url'] : '',
      ];
      $best_score = $score;
    }

    return $best;
  }

  protected function scoreLocalItemCandidate(string $query_key, string $name, string $content_id): int {
    $name_key = $this->normalizeLookupKey($name);
    $id_key = $this->normalizeLookupKey($content_id);
    if ($query_key === '') {
      return 0;
    }
    if ($query_key === $name_key || $query_key === $id_key) {
      return 100;
    }
    if ($name_key !== '' && str_contains($name_key, $query_key)) {
      return 70;
    }
    if ($query_key !== '' && str_contains($query_key, $name_key) && $name_key !== '') {
      return 60;
    }
    similar_text($query_key, $name_key, $percent);
    return $percent >= 60 ? (int) round($percent) - 10 : 0;
  }

  protected function extractLocalPriceGp(array $schema_data): ?float {
    if (isset($schema_data['price_gp']) && is_numeric($schema_data['price_gp'])) {
      return (float) $schema_data['price_gp'];
    }
    if (isset($schema_data['price']) && is_array($schema_data['price'])) {
      $gp = 0.0;
      $multipliers = ['pp' => 10.0, 'gp' => 1.0, 'sp' => 0.1, 'cp' => 0.01];
      foreach ($multipliers as $denomination => $multiplier) {
        if (isset($schema_data['price'][$denomination]) && is_numeric($schema_data['price'][$denomination])) {
          $gp += ((float) $schema_data['price'][$denomination]) * $multiplier;
        }
      }
      return $gp;
    }
    return NULL;
  }

  protected function extractAonPriceGp(array $source): ?float {
    if (isset($source['price']) && is_numeric($source['price'])) {
      return (float) $source['price'];
    }

    $price_raw = strtolower(trim((string) ($source['price_raw'] ?? '')));
    if ($price_raw === '') {
      return NULL;
    }

    if (preg_match_all('/(\d+(?:\.\d+)?)\s*(pp|gp|sp|cp)/u', $price_raw, $matches, \PREG_SET_ORDER)) {
      $gp = 0.0;
      $multipliers = ['pp' => 10.0, 'gp' => 1.0, 'sp' => 0.1, 'cp' => 0.01];
      foreach ($matches as $match) {
        $gp += ((float) $match[1]) * ($multipliers[$match[2]] ?? 0.0);
      }
      return $gp;
    }

    return NULL;
  }

  protected function formatQuantityLabel(int $quantity, string $item_name): string {
    return $quantity > 1 ? $quantity . ' x ' . $item_name : $item_name;
  }

  protected function formatGpAmount(float $gp): string {
    return $this->formatCpAmount((int) round($gp * 100));
  }

  protected function formatCpAmount(int $cp): string {
    if ($cp <= 0) {
      return '0 cp';
    }

    $parts = [];
    $pp = intdiv($cp, 1000);
    $cp -= $pp * 1000;
    $gp_units = intdiv($cp, 100);
    $cp -= $gp_units * 100;
    $sp = intdiv($cp, 10);
    $cp -= $sp * 10;

    if ($pp > 0) {
      $parts[] = $pp . ' pp';
    }
    if ($gp_units > 0) {
      $parts[] = $gp_units . ' gp';
    }
    if ($sp > 0) {
      $parts[] = $sp . ' sp';
    }
    if ($cp > 0) {
      $parts[] = $cp . ' cp';
    }

    return implode(' ', $parts);
  }

  protected function buildCharacterSalePlan(int $character_id, string $item_query, int $quantity, ?int $campaign_id = NULL): ?array {
    $query_key = $this->normalizeLookupKey($item_query);
    $query = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['item_instance_id', 'item_id', 'quantity', 'state_data'])
      ->condition('location_ref', (string) $character_id);
    if ($campaign_id !== NULL) {
      $query->condition('campaign_id', $campaign_id);
    }
    $rows = $query->execute()->fetchAllAssoc('item_instance_id');

    $candidates = [];
    foreach ($rows as $row) {
      $state = json_decode((string) ($row->state_data ?? '{}'), TRUE);
      if (!is_array($state)) {
        $state = [];
      }
      $name = (string) ($state['name'] ?? $row->item_id ?? '');
      $score = $this->scoreLocalItemCandidate($query_key, $name, (string) ($row->item_id ?? ''));
      if ($score <= 0) {
        continue;
      }

      $candidates[] = [
        'item_instance_id' => (string) ($row->item_instance_id ?? ''),
        'item_id' => (string) ($row->item_id ?? ''),
        'quantity' => max(0, (int) ($row->quantity ?? 0)),
        'name' => $name,
        'state' => $state,
        'score' => $score,
      ];
    }

    if ($candidates === []) {
      return NULL;
    }

    usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $top = $candidates[0];
    $target_name_key = $this->normalizeLookupKey((string) ($top['name'] ?? ''));
    $target_id = (string) ($top['item_id'] ?? '');
    $matching = array_values(array_filter($candidates, function (array $candidate) use ($target_name_key, $target_id): bool {
      return $this->normalizeLookupKey((string) ($candidate['name'] ?? '')) === $target_name_key
        || ((string) ($candidate['item_id'] ?? '') !== '' && (string) ($candidate['item_id'] ?? '') === $target_id);
    }));

    $remaining = $quantity;
    $sale_units = [];
    $offer_cp = 0;
    $taboo_message = NULL;
    foreach ($matching as $candidate) {
      if (!empty($candidate['state']['sell_taboo'])) {
        $taboo_message = (string) ($candidate['state']['sell_taboo_message'] ?? 'This item has a sell taboo. A GM must authorize its sale.');
        continue;
      }
      if ($remaining < 1) {
        break;
      }

      $available_qty = max(0, (int) ($candidate['quantity'] ?? 0));
      if ($available_qty < 1) {
        continue;
      }

      $take = min($remaining, $available_qty);
      $unit_price_gp = isset($candidate['state']['price_gp']) && is_numeric($candidate['state']['price_gp'])
        ? (float) $candidate['state']['price_gp']
        : $this->extractLocalPriceGp($candidate['state']);
      $item_subtype = (string) ($candidate['state']['subtype'] ?? $candidate['state']['item_subtype'] ?? '');
      $multiplier = in_array($item_subtype, InventoryManagementService::FULL_PRICE_SUBTYPES, TRUE) ? 1.0 : 0.5;
      $offer_cp += (int) round($unit_price_gp * 100 * $take * $multiplier);
      $sale_units[] = [
        'item_instance_id' => (string) ($candidate['item_instance_id'] ?? ''),
        'quantity' => $take,
      ];
      $remaining -= $take;
    }

    if ($remaining > 0) {
      return $taboo_message !== NULL && $sale_units === []
        ? ['taboo_message' => $taboo_message]
        : NULL;
    }

    return [
      'item_name' => (string) ($top['name'] ?? $item_query),
      'quantity' => $quantity,
      'offer_cp' => $offer_cp,
      'sale_units' => $sale_units,
      'taboo_message' => $taboo_message,
    ];
  }

  protected function loadCharacterCurrencyCp(int $character_id): int {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data', 'state_data'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return 0;
    }

    $character_data = json_decode((string) ($record['character_data'] ?? '{}'), TRUE);
    $runtime_state = json_decode((string) ($record['state_data'] ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }
    if (!is_array($runtime_state)) {
      $runtime_state = [];
    }

    $currency = $character_data['character']['equipment']['currency']
      ?? $character_data['equipment']['currency']
      ?? $character_data['currency']
      ?? $runtime_state['equipment']['currency']
      ?? $runtime_state['currency']
      ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];

    if (!isset($currency['gp']) && isset($currency['gold'])) {
      $currency = [
        'pp' => (int) ($currency['pp'] ?? 0),
        'gp' => (int) ($currency['gold'] ?? 0),
        'sp' => (int) ($currency['silver'] ?? 0),
        'cp' => (int) ($currency['copper'] ?? 0),
      ];
    }

    return ((int) ($currency['pp'] ?? 0)) * 1000
      + ((int) ($currency['gp'] ?? 0)) * 100
      + ((int) ($currency['sp'] ?? 0)) * 10
      + (int) ($currency['cp'] ?? 0);
  }

  protected function mapItemTypeFromSchema(array $schema_data): string {
    $category = strtolower((string) ($schema_data['category'] ?? $schema_data['item_category'] ?? ''));
    return match ($category) {
      'weapon' => 'weapon',
      'armor' => 'armor',
      'shield' => 'shield',
      default => 'gear',
    };
  }

  protected function mapAonItemType(array $source): string {
    $category = strtolower((string) ($source['category'] ?? $source['item_category'] ?? ''));
    return match ($category) {
      'weapon' => 'weapon',
      'armor' => 'armor',
      'shield' => 'shield',
      default => 'gear',
    };
  }

  protected function slugify(string $value): string {
    $value = $this->normalizeLookupKey($value);
    return trim(str_replace(' ', '-', $value), '-');
  }

  protected function normalizeLookupKey(string $value): string {
    $value = $this->normalizeText($value);
    return str_replace(['-', '_'], ' ', $value);
  }

  protected function normalizeText(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\s\-\']+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
  }

}
