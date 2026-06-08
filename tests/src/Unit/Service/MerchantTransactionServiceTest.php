<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\dungeoncrawler_content\Service\MerchantBotService;
use Drupal\dungeoncrawler_content\Service\MerchantTransactionService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers merchant runtime matching and profile detection helpers.
 *
 * @group dungeoncrawler_content
 * @group merchant
 */
class MerchantTransactionServiceTest extends UnitTestCase {

  /**
   * Verifies tavern-keeper runtime metadata is recognized as merchant-capable.
   */
  public function testNpcLooksLikeMerchantRecognizesTavernKeeperMetadata(): void {
    $service = $this->buildService();

    $this->assertTrue($service->exposeNpcLooksLikeMerchant([
      'content_id' => 'tavern_keeper',
      'runtime_entity_id' => 'npc_tavern_keeper',
      'description' => 'A stout, friendly man with a bushy beard and warm smile',
    ], []));
  }

  /**
   * Verifies tavern-keeper refs map to the innkeeper stock profile.
   */
  public function testResolveMerchantProfileUsesInnkeeperProfileForTavernKeeperRefs(): void {
    $service = $this->buildService();

    $profile = $service->exposeResolveMerchantProfile([
      'instance_id' => 'npc_tavern_keeper',
      'decoded_state' => [
        'content_id' => 'tavern_keeper',
        'runtime_entity_id' => 'npc_tavern_keeper',
      ],
      'decoded_character' => [],
    ]);

    $this->assertSame('innkeeper', $profile['key']);
  }

  /**
   * Verifies merchant summaries expose content refs without dead entity_ref data.
   */
  public function testBuildMerchantSummaryUsesContentIdAsEntityRef(): void {
    $service = $this->buildService();

    $summary = $service->exposeBuildMerchantSummary([
      'id' => '325',
      'instance_id' => 'npc_tavern_keeper',
      'decoded_state' => [
        'content_id' => 'tavern_keeper',
        'metadata' => [
          'display_name' => 'Eldric',
          'description' => 'The tavern barkeep.',
        ],
      ],
      'decoded_character' => [],
    ]);

    $this->assertSame('tavern_keeper', $summary['entity_ref']);
    $this->assertSame('npc_tavern_keeper', $summary['merchant_ref']);
    $this->assertSame('Eldric', $summary['name']);
  }

  /**
   * Verifies merchant refs accept both UI and runtime instance-id variants.
   */
  public function testCollectMerchantRefsExpandsHyphenAndUnderscoreAliases(): void {
    $service = $this->buildService();

    $refs = $service->exposeCollectMerchantRefs([
      'id' => '325',
      'instance_id' => 'npc_tavern_keeper',
      'character_id' => '0',
    ], [
      'runtime_entity_id' => 'npc_tavern_keeper',
      'content_id' => 'tavern_keeper',
      'metadata' => [],
    ], []);

    $this->assertContains('npc_tavern_keeper', $refs);
    $this->assertContains('npc-tavern-keeper', $refs);
    $this->assertContains('npc-tavern_keeper', $refs);
  }

  /**
   * Verifies sellable inventory entries inherit catalog metadata for the panel.
   */
  public function testBuildSellableInventoryIncludesCatalogMetadata(): void {
    $merchant_bot_service = $this->createMock(MerchantBotService::class);
    $merchant_bot_service->expects($this->once())
      ->method('lookupItem')
      ->with('Trail Ration')
      ->willReturn([
        'id' => 'trail_ration',
        'name' => 'Trail Ration',
        'type' => 'consumable',
        'subtype' => 'food',
        'level' => 0,
        'description' => 'A preserved meal for travel.',
      ]);

    $service = $this->buildService($merchant_bot_service);
    $items = $service->exposeBuildSellableInventory([
      'carried' => [[
        'item_instance_id' => 'ration_1',
        'name' => 'Trail Ration',
        'type' => 'consumable',
        'price_gp' => 0.5,
        'bulk' => 'L',
      ]],
    ]);

    $this->assertCount(1, $items);
    $this->assertSame('food', $items[0]['subtype']);
    $this->assertSame('A preserved meal for travel.', $items[0]['description']);
    $this->assertSame(0, $items[0]['level']);
    $this->assertSame('trail_ration', $items[0]['catalog_item']['id']);
  }

  /**
   * Verifies merchant runtime metadata can force the catalog-all stock profile.
   */
  public function testResolveMerchantProfileUsesCatalogAllOverride(): void {
    $service = $this->buildService();

    $profile = $service->exposeResolveMerchantProfile([
      'instance_id' => 'npc_bousterous',
      'decoded_state' => [
        'metadata' => [
          'merchant_profile' => 'catalog_all',
        ],
      ],
      'decoded_character' => [],
    ]);

    $this->assertSame('catalog_all', $profile['key']);
    $this->assertSame('catalog_all', $profile['stock_mode']);
    $this->assertSame([], $profile['types']);
  }

  /**
   * Verifies wider catalog search respects merchant profile item types.
   */
  public function testResolveMerchantCatalogSearchItemReturnsProfileCompatibleLookup(): void {
    $merchant_bot_service = $this->createMock(MerchantBotService::class);
    $merchant_bot_service->expects($this->once())
      ->method('lookupItem')
      ->with('canteen')
      ->willReturn([
        'id' => 'canteen',
        'name' => 'Canteen',
        'type' => 'gear',
        'subtype' => 'adventuring',
        'price_gp' => 0.5,
        'bulk' => 'L',
        'level' => 0,
        'description' => 'A rugged travel canteen.',
        'source' => 'aon',
      ]);

    $service = $this->buildService($merchant_bot_service);
    $item = $service->exposeResolveMerchantCatalogSearchItem([
      'instance_id' => 'npc_tavern_keeper',
      'decoded_state' => [
        'content_id' => 'tavern_keeper',
        'metadata' => [
          'role' => 'Innkeeper',
        ],
      ],
      'decoded_character' => [],
    ], 'canteen');

    $this->assertSame('canteen', $item['item_id']);
    $this->assertSame('wider trade catalog', $item['source']);
    $this->assertTrue($item['search_result']);
    $this->assertSame(50, $item['price_cp']);
  }

  /**
   * Verifies innkeeper profile search can source baseline weapon requests.
   */
  public function testResolveMerchantCatalogSearchItemAllowsInnkeeperWeaponLookup(): void {
    $merchant_bot_service = $this->createMock(MerchantBotService::class);
    $merchant_bot_service->expects($this->once())
      ->method('lookupItem')
      ->with('short sword')
      ->willReturn([
        'id' => 'shortsword',
        'name' => 'Shortsword',
        'type' => 'weapon',
        'item_type' => 'weapon',
        'subtype' => 'sword',
        'price_gp' => 0.9,
        'bulk' => 'L',
        'level' => 0,
        'description' => 'A basic martial shortsword.',
        'source' => 'local',
      ]);

    $service = $this->buildService($merchant_bot_service);
    $item = $service->exposeResolveMerchantCatalogSearchItem([
      'instance_id' => 'npc_tavern_keeper',
      'decoded_state' => [
        'content_id' => 'tavern_keeper',
        'metadata' => [
          'role' => 'Innkeeper',
        ],
      ],
      'decoded_character' => [],
    ], 'short sword');

    $this->assertNotNull($item);
    $this->assertSame('shortsword', $item['item_id']);
    $this->assertSame('Shortsword', $item['name']);
    $this->assertSame('weapon', $item['type']);
    $this->assertSame(90, $item['price_cp']);
  }

  /**
   * Verifies panel purchases can fall through to wider catalog search matches.
   */
  public function testExecutePanelTransactionPurchasesFallbackSearchResult(): void {
    $merchant_bot_service = $this->createMock(MerchantBotService::class);
    $merchant_bot_service->expects($this->once())
      ->method('lookupItem')
      ->with('canteen')
      ->willReturn([
        'id' => 'canteen',
        'name' => 'Canteen',
        'type' => 'gear',
        'item_type' => 'gear',
        'price_gp' => 0.5,
        'source' => 'aon',
      ]);

    $inventory_management = $this->createMock(InventoryManagementService::class);
    $inventory_management->expects($this->once())
      ->method('purchaseItem')
      ->with('17', $this->callback(static function (array $item): bool {
        return ($item['id'] ?? '') === 'canteen'
          && ($item['name'] ?? '') === 'Canteen'
          && (float) ($item['price_gp'] ?? 0.0) === 0.5;
      }), 'downtime', 1, 22)
      ->willReturn(['success' => TRUE]);

    $service = new MerchantTransactionServiceTestDouble(
      $this->createMock(Connection::class),
      $merchant_bot_service,
      $inventory_management,
    );
    $service->merchantOverride = [
      'instance_id' => 'npc_tavern_keeper',
      'decoded_state' => [
        'content_id' => 'tavern_keeper',
        'metadata' => [
          'display_name' => 'Eldric',
          'role' => 'Innkeeper',
        ],
      ],
      'decoded_character' => [],
    ];
    $service->contextOverride = [
      'merchant' => [
        'merchant_ref' => 'merchant-1',
        'name' => 'Eldric',
      ],
      'stock' => [],
      'player' => [
        'character_id' => 17,
      ],
    ];

    $result = $service->executePanelTransaction(22, 'room-tavern', 'merchant-1', [
      'action' => 'buy',
      'item_id' => 'canteen',
      'character_id' => 17,
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('completed_purchase', $result['status']);
    $this->assertStringContainsString('Canteen', $result['message']);
  }

  /**
   * Builds the merchant transaction service test double.
   */
  private function buildService(?MerchantBotService $merchant_bot_service = NULL): MerchantTransactionServiceTestDouble {
    return new MerchantTransactionServiceTestDouble(
      $this->createMock(Connection::class),
      $merchant_bot_service ?? $this->createMock(MerchantBotService::class),
      $this->createMock(InventoryManagementService::class),
    );
  }

}

/**
 * Test double exposing protected merchant helper methods.
 */
class MerchantTransactionServiceTestDouble extends MerchantTransactionService {

  public ?array $merchantOverride = NULL;

  public ?array $contextOverride = NULL;

  /**
   * Exposes npcLooksLikeMerchant for unit coverage.
   */
  public function exposeNpcLooksLikeMerchant(array $state, array $character): bool {
    return $this->npcLooksLikeMerchant($state, $character);
  }

  /**
   * Exposes resolveMerchantProfile for unit coverage.
   */
  public function exposeResolveMerchantProfile(array $merchant): array {
    return $this->resolveMerchantProfile($merchant);
  }

  /**
   * Exposes buildMerchantSummary for unit coverage.
   */
  public function exposeBuildMerchantSummary(array $merchant): array {
    return $this->buildMerchantSummary($merchant);
  }

  /**
   * Exposes collectMerchantRefs for unit coverage.
   */
  public function exposeCollectMerchantRefs(array $merchant, array $state, array $character): array {
    return $this->collectMerchantRefs($merchant, $state, $character);
  }

  /**
   * Exposes buildSellableInventory for unit coverage.
   */
  public function exposeBuildSellableInventory(array $inventory): array {
    return $this->buildSellableInventory($inventory);
  }

  /**
   * Exposes catalog fallback search normalization for unit coverage.
   */
  public function exposeResolveMerchantCatalogSearchItem(array $merchant, string $item_query): ?array {
    return $this->resolveMerchantCatalogSearchItem($merchant, $item_query);
  }

  /**
   * {@inheritdoc}
   */
  public function getMerchantPanelContext(
    int $campaign_id,
    string $room_id,
    string $merchant_ref,
    ?int $character_id = NULL
  ): array {
    return $this->contextOverride ?? parent::getMerchantPanelContext($campaign_id, $room_id, $merchant_ref, $character_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function loadMerchantNpc(int $campaign_id, string $room_id, string $merchant_ref): array {
    return $this->merchantOverride ?? parent::loadMerchantNpc($campaign_id, $room_id, $merchant_ref);
  }

}
