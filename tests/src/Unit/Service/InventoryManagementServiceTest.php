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
