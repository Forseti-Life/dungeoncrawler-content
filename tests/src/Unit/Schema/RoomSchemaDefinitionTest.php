<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests room schema definitions that back canonical room payloads.
 *
 * @group dungeoncrawler_content
 * @group map
 */
class RoomSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies room hex objects expose canonical object reference fields.
   */
  public function testHexObjectSchemaIncludesCanonicalPlacementFields(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/room.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $hex_object = $schema['definitions']['hex_object']['properties'];

    $this->assertSame('string', $hex_object['object_id']['type']);
    $this->assertSame('^[A-Za-z0-9][A-Za-z0-9_-]*$', $hex_object['object_id']['pattern']);
    $this->assertSame('string', $hex_object['label']['type']);
    $this->assertSame('string', $hex_object['category']['type']);
    $this->assertSame(['n', 'ne', 'e', 'se', 's', 'sw', 'w', 'nw'], $hex_object['orientation']['enum']);
  }

}
