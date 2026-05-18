<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests inventory bulk calculations.
 *
 * @group dungeoncrawler_content
 * @group inventory
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\InventoryManagementService
 */
class InventoryManagementServiceTest extends UnitTestCase {

  /**
   * @covers ::calculateCurrentBulk
   * @covers ::calculateCurrentBulkRecursive
   * @covers ::calculateItemBulk
   * @covers ::isContainer
   * @covers ::getContainerProperties
   */
  public function testCalculateCurrentBulkIncludesNestedContainerContents(): void {
    $service = new InspectableInventoryManagementService([
      'character:char-1' => [
        (object) [
          'item_instance_id' => 'pack-1',
          'quantity' => 1,
          'state_data' => json_encode([
            'id' => 'backpack',
            'name' => 'Backpack',
            'bulk' => 'L',
            'container_stats' => [
              'capacity' => 4,
              'capacity_reduction' => 1,
              'bulk_reduction' => 2,
              'access_time' => 'interact',
              'container_type' => 'backpack',
            ],
          ]),
        ],
      ],
      'container:pack-1' => [
        (object) [
          'item_instance_id' => 'rations-1',
          'quantity' => 3,
          'state_data' => json_encode([
            'id' => 'rations',
            'name' => 'Rations',
            'bulk' => '1',
          ]),
        ],
      ],
    ]);

    $this->assertSame(1.1, $service->calculateCurrentBulk('char-1', 'character', 42));
  }

  /**
   * @covers ::calculateCurrentBulk
   * @covers ::calculateCurrentBulkRecursive
   * @covers ::calculateItemBulk
   * @covers ::isContainer
   * @covers ::getContainerProperties
   */
  public function testBackpackBulkReductionDoesNotGoBelowZero(): void {
    $service = new InspectableInventoryManagementService([
      'character:char-1' => [
        (object) [
          'item_instance_id' => 'pack-1',
          'quantity' => 1,
          'state_data' => json_encode([
            'id' => 'backpack',
            'name' => 'Backpack',
            'bulk' => 'L',
            'container_stats' => [
              'capacity' => 4,
              'capacity_reduction' => 1,
              'bulk_reduction' => 2,
              'access_time' => 'interact',
              'container_type' => 'backpack',
            ],
          ]),
        ],
      ],
      'container:pack-1' => [
        (object) [
          'item_instance_id' => 'chalk-1',
          'quantity' => 1,
          'state_data' => json_encode([
            'id' => 'chalk',
            'name' => 'Chalk',
            'bulk' => '1',
          ]),
        ],
      ],
    ]);

    $this->assertSame(0.1, $service->calculateCurrentBulk('char-1', 'character', 42));
  }

  /**
   * @covers ::getBulkLocationTypesForOwnerType
   */
  public function testCharacterBulkCountsAllCharacterCarryLocations(): void {
    $service = new InspectableInventoryManagementService([]);

    $this->assertSame(
      ['carried', 'worn', 'equipped', 'stashed'],
      $service->exposeBulkLocationTypesForOwnerType('character')
    );
  }

  /**
   * @covers ::normalizeIncomingItemData
   * @covers ::buildPriceGpFromPrice
   * @covers ::normalizeBulkStorageValue
   */
  public function testNormalizeIncomingItemDataUsesCanonicalRegistryTemplate(): void {
    $service = new InspectableInventoryManagementService(
      [],
      [
        'longsword' => [
          'name' => 'Longsword',
          'schema' => [
            'item_type' => 'weapon',
            'hands' => '1',
            'bulk' => '1',
            'price' => ['gp' => 1, 'sp' => 0, 'cp' => 0, 'pp' => 0],
            'weapon_stats' => [
              'category' => 'martial',
              'group' => 'sword',
            ],
            'description' => 'Canonical longsword text.',
          ],
        ],
      ]
    );

    $item = $service->exposeNormalizeIncomingItemData([
      'id' => 'longsword',
      'name' => 'Longsword',
      'type' => 'weapon',
      'bulk' => 1,
      'damage' => '1d8 S',
    ]);

    $this->assertSame('longsword', $item['id']);
    $this->assertSame('weapon', $item['item_type']);
    $this->assertSame('weapon', $item['type']);
    $this->assertSame('1', $item['bulk']);
    $this->assertSame(1.0, $item['price_gp']);
    $this->assertSame('martial', $item['weapon_stats']['category']);
    $this->assertSame('Canonical longsword text.', $item['description']);
    $this->assertSame('1d8 S', $item['damage']);
    $this->assertSame([
      'equippable' => TRUE,
      'equip_slot' => 'held',
      'worn_slot' => NULL,
      'hand_slots_required' => 1,
      'consumable' => FALSE,
      'consumes_on_use' => FALSE,
      'container' => FALSE,
      'stackable' => FALSE,
    ], $item['inventory_metadata']);
  }

  /**
   * @covers ::validateItemData
   * @covers ::normalizeBulkValue
   */
  public function testValidateItemDataRejectsInvalidBulk(): void {
    $service = new InspectableInventoryManagementService([]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid bulk value: heavy');
    $service->exposeValidateItemData([
      'id' => 'bad-pack',
      'name' => 'Bad Pack',
      'type' => 'adventuring_gear',
      'bulk' => 'heavy',
    ]);
  }

  /**
   * @covers ::calculateItemBulk
   * @covers ::normalizeBulkValue
   */
  public function testCalculateItemBulkSupportsNumericBulkStrings(): void {
    $service = new InspectableInventoryManagementService([]);

    $this->assertSame(4.0, $service->exposeCalculateItemBulk([
      'bulk' => '2',
    ], 2));
  }

  /**
   * @covers ::validateAddCapacity
   * @covers ::calculateItemBulk
   */
  public function testValidateAddCapacityRejectsOverfilledContainer(): void {
    $service = new InspectableInventoryManagementService(
      [],
      [],
      [
        'container:pack-1' => 3.5,
      ],
      [
        'container:pack-1' => 4.0,
      ],
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot add item: would exceed capacity');
    $service->exposeValidateAddCapacity(
      'pack-1',
      'container',
      ['id' => 'rations', 'name' => 'Rations', 'type' => 'adventuring_gear', 'bulk' => '1'],
      1,
      42,
    );
  }

  /**
   * @covers ::normalizeIncomingItemData
   * @covers ::buildInventoryMetadata
   */
  public function testNormalizeIncomingItemDataBuildsConsumableAndContainerMetadata(): void {
    $service = new InspectableInventoryManagementService([]);

    $potion = $service->exposeNormalizeIncomingItemData([
      'id' => 'healing-potion',
      'name' => 'Healing Potion',
      'item_type' => 'consumable',
      'bulk' => 'L',
      'consumable_stats' => [
        'consumable_type' => 'potion',
        'activate' => ['actions' => '1'],
      ],
    ]);
    $this->assertTrue($potion['inventory_metadata']['consumable']);
    $this->assertTrue($potion['inventory_metadata']['consumes_on_use']);
    $this->assertFalse($potion['inventory_metadata']['container']);
    $this->assertTrue($potion['inventory_metadata']['stackable']);

    $backpack = $service->exposeNormalizeIncomingItemData([
      'id' => 'backpack',
      'name' => 'Backpack',
      'item_type' => 'adventuring_gear',
      'bulk' => 'L',
      'hands' => '0',
      'container_stats' => [
        'capacity' => 4,
      ],
    ]);
    $this->assertTrue($backpack['inventory_metadata']['equippable']);
    $this->assertSame('worn', $backpack['inventory_metadata']['equip_slot']);
    $this->assertSame('worn', $backpack['inventory_metadata']['worn_slot']);
    $this->assertSame(0, $backpack['inventory_metadata']['hand_slots_required']);
    $this->assertTrue($backpack['inventory_metadata']['container']);
    $this->assertFalse($backpack['inventory_metadata']['consumable']);
  }

  /**
   * @covers ::getCharacterInventoryFromInstances
   */
  public function testGetInventoryKeepsArmorAndShieldSeparate(): void {
    $service = new InspectableInventoryManagementService([
      'character:char-1' => [
        (object) [
          'item_instance_id' => 'armor-1',
          'item_id' => 'chain-mail',
          'quantity' => 1,
          'location_type' => 'worn',
          'state_data' => json_encode([
            'id' => 'chain-mail',
            'name' => 'Chain Mail',
            'item_type' => 'armor',
            'bulk' => '2',
          ]),
        ],
        (object) [
          'item_instance_id' => 'shield-1',
          'item_id' => 'wooden-shield',
          'quantity' => 1,
          'location_type' => 'worn',
          'state_data' => json_encode([
            'id' => 'wooden-shield',
            'name' => 'Wooden Shield',
            'item_type' => 'shield',
            'bulk' => '1',
          ]),
        ],
      ],
    ]);

    $inventory = $service->getInventory('char-1', 'character', 42);

    $this->assertSame('chain-mail', $inventory['worn']['armor']['id']);
    $this->assertSame('wooden-shield', $inventory['worn']['shield']['id']);
  }

}

/**
 * Inventory service test double that avoids the database layer.
 */
class InspectableInventoryManagementService extends InventoryManagementService {

  /**
   * @param array<string, array<int, object>> $rows
   *   Bulk rows keyed by owner_type:owner_id.
   * @param array<string, array{name: string, schema: array<string, mixed>}> $templates
   *   Registry item templates keyed by item id.
   */
  public function __construct(
    private array $rows,
    private array $templates = [],
    private array $bulkByOwner = [],
    private array $capacityByOwner = [],
  ) {}

  /**
   * Exposes bulk location mapping for tests.
   */
  public function exposeBulkLocationTypesForOwnerType(string $owner_type): array {
    return $this->getBulkLocationTypesForOwnerType($owner_type);
  }

  /**
   * Exposes item normalization for tests.
   */
  public function exposeNormalizeIncomingItemData(array $item): array {
    return $this->normalizeIncomingItemData($item);
  }

  /**
   * Exposes item validation for tests.
   */
  public function exposeValidateItemData(array $item): void {
    $this->validateItemData($item);
  }

  /**
   * Exposes item bulk calculation for tests.
   */
  public function exposeCalculateItemBulk(array $item_state, int $quantity = 1): float {
    return $this->calculateItemBulk($item_state, $quantity);
  }

  /**
   * Exposes add-capacity validation for tests.
   */
  public function exposeValidateAddCapacity(
    string $owner_id,
    string $owner_type,
    array $item,
    int $quantity = 1,
    ?int $campaign_id = NULL,
  ): void {
    $this->validateAddCapacity($owner_id, $owner_type, $item, $quantity, $campaign_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function loadBulkItemRows(
    string $owner_id,
    string $owner_type,
    ?int $campaign_id = NULL
  ): array {
    return $this->rows[$owner_type . ':' . $owner_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getCharacterInventoryFromInstances(
    string $character_id,
    ?int $campaign_id = NULL
  ): array {
    $inventory = [
      'worn' => [
        'weapons' => [],
        'armor' => NULL,
        'shield' => NULL,
        'accessories' => [],
      ],
      'carried' => [],
      'equipped' => [],
      'stashed' => [],
      'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      'totalBulk' => 0,
      'encumbrance' => 'unencumbered',
    ];

    foreach ($this->rows['character:' . $character_id] ?? [] as $item_row) {
      $state = json_decode($item_row->state_data ?? '', TRUE) ?? [];
      $item_data = [
        'item_instance_id' => $item_row->item_instance_id,
        'item_id' => $item_row->item_id ?? '',
        'quantity' => (int) ($item_row->quantity ?? 1),
        'location' => $item_row->location_type ?? 'carried',
        ...$state,
      ];
      $item_data['inventory_metadata'] = $this->buildInventoryMetadata($item_data);

      if (($item_row->location_type ?? '') !== 'worn') {
        $inventory['carried'][] = $item_data;
        continue;
      }

      $type = $state['type'] ?? $state['item_type'] ?? 'accessory';
      $equip_slot = (string) ($item_data['inventory_metadata']['equip_slot'] ?? '');
      if ($type === 'weapon' || $equip_slot === 'held') {
        $inventory['worn']['weapons'][] = $item_data;
      }
      elseif ($type === 'armor' || $equip_slot === 'armor') {
        $inventory['worn']['armor'] = $item_data;
      }
      elseif ($type === 'shield' || $equip_slot === 'shield') {
        $inventory['worn']['shield'] = $item_data;
      }
      else {
        $inventory['worn']['accessories'][] = $item_data;
      }
    }

    return \Drupal\dungeoncrawler_content\Service\CharacterEquipmentSlotHelper::normalizeInventory($inventory);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRegistryItemTemplate(string $item_id): ?array {
    return $this->templates[$item_id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateCurrentBulk(
    string $owner_id,
    string $owner_type = 'character',
    ?int $campaign_id = NULL
  ): float {
    $key = $owner_type . ':' . $owner_id;
    if (array_key_exists($key, $this->bulkByOwner)) {
      return $this->bulkByOwner[$key];
    }

    return parent::calculateCurrentBulk($owner_id, $owner_type, $campaign_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInventoryCapacity(
    string $owner_id,
    string $owner_type = 'character'
  ): float {
    $key = $owner_type . ':' . $owner_id;
    if (array_key_exists($key, $this->capacityByOwner)) {
      return $this->capacityByOwner[$key];
    }

    return parent::getInventoryCapacity($owner_id, $owner_type);
  }

}
