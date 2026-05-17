<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\ItemCombatDataService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests canonical item-registry combat extraction.
 *
 * @group dungeoncrawler_content
 * @group inventory
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ItemCombatDataService
 */
class ItemCombatDataServiceTest extends UnitTestCase {

  /**
   * @covers ::getWeaponCombatData
   * @covers ::extractWeaponDamage
   * @covers ::extractWeaponTraits
   * @covers ::extractWeaponRange
   * @covers ::normalizeHands
   */
  public function testGetWeaponCombatDataReadsCanonicalWeaponStats(): void {
    $service = new ItemCombatDataService($this->buildDatabaseRow([
      'name' => 'Longsword',
      'schema_data' => json_encode([
        'item_type' => 'weapon',
        'hands' => '1',
        'traits' => ['martial', 'sword'],
        'weapon_stats' => [
          'category' => 'martial',
          'group' => 'sword',
          'damage' => [
            'dice_count' => 1,
            'die_size' => 'd8',
            'damage_type' => 'slashing',
          ],
          'range' => NULL,
          'weapon_traits' => ['versatile P'],
        ],
      ]),
    ]));

    $weapon = $service->getWeaponCombatData('longsword');

    $this->assertNotNull($weapon);
    $this->assertSame('Longsword', $weapon['name']);
    $this->assertSame('1d8', $weapon['damage']);
    $this->assertSame('slashing', $weapon['damage_type']);
    $this->assertSame('martial', $weapon['category']);
    $this->assertSame('sword', $weapon['group']);
    $this->assertSame(1, $weapon['hands']);
    $this->assertSame(['Martial', 'Sword', 'Versatile P'], $weapon['traits']);
    $this->assertNull($weapon['range']);
  }

  /**
   * @covers ::getWeaponCombatData
   * @covers ::extractWeaponRange
   */
  public function testGetWeaponCombatDataUsesCanonicalRangeAndPropulsiveTrait(): void {
    $service = new ItemCombatDataService($this->buildDatabaseRow([
      'name' => 'Longbow',
      'schema_data' => json_encode([
        'item_type' => 'weapon',
        'hands' => '2',
        'traits' => ['martial', 'bow'],
        'weapon_stats' => [
          'category' => 'martial',
          'group' => 'bow',
          'damage' => [
            'dice_count' => 1,
            'die_size' => 'd8',
            'damage_type' => 'piercing',
          ],
          'range' => 100,
          'weapon_traits' => ['deadly-d10', 'propulsive'],
        ],
      ]),
    ]));

    $weapon = $service->getWeaponCombatData('longbow');

    $this->assertNotNull($weapon);
    $this->assertSame('100 feet', $weapon['range']);
    $this->assertSame('half_positive', $weapon['damage_str_mode']);
    $this->assertContains('Deadly-d10', $weapon['traits']);
    $this->assertContains('Propulsive', $weapon['traits']);
  }

  /**
   * Builds a database stub that returns one registry row.
   */
  private function buildDatabaseRow(array $row): Connection {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn($row);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $database = $this->createMock(Connection::class);
    $database->method('select')
      ->with('dungeoncrawler_content_registry', 'r')
      ->willReturn($query);

    return $database;
  }

}
