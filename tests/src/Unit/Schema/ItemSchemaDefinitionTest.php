<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests item schema definitions that power the canonical content library.
 *
 * @group dungeoncrawler_content
 * @group inventory
 */
class ItemSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies the item schema accepts canonical slug identifiers.
   */
  public function testItemSchemaUsesSlugBasedItemIds(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/item.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame('string', $schema['properties']['item_id']['type']);
    $this->assertSame(1, $schema['properties']['item_id']['minLength']);
    $this->assertSame(100, $schema['properties']['item_id']['maxLength']);
    $this->assertSame(
      '^[a-z0-9]+(?:[_-][a-z0-9]+)*$',
      $schema['properties']['item_id']['pattern']
    );
    $this->assertArrayNotHasKey('format', $schema['properties']['item_id']);
  }

  /**
   * Verifies the canonical backpack item is modeled as a container.
   */
  public function testBackpackDefinitionIncludesContainerStats(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/item.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);
    $item_path = dirname(__DIR__, 4) . '/content/items/backpack.json';
    $item = json_decode((string) file_get_contents($item_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame('number', $schema['properties']['container_stats']['properties']['bulk_reduction']['type']);
    $this->assertIsArray($item);
    $this->assertSame('backpack', $item['item_id']);
    $this->assertSame('adventuring_gear', $item['item_type']);
    $this->assertSame(4, $item['container_stats']['capacity']);
    $this->assertSame(1, $item['container_stats']['capacity_reduction']);
    $this->assertSame(2, $item['container_stats']['bulk_reduction']);
    $this->assertSame('backpack', $item['container_stats']['container_type']);
  }

}
