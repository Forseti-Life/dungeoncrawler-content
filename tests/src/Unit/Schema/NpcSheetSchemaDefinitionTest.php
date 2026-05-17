<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the formal NPC sheet schema definition.
 *
 * @group dungeoncrawler_content
 * @group npc
 */
class NpcSheetSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies the psychology contract is explicitly defined in schema.
   */
  public function testNpcSheetSchemaRequiresStructuredPsychology(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/npc_sheet.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertContains('psychology', $schema['required'] ?? []);
    $this->assertSame('object', $schema['properties']['psychology']['type'] ?? NULL);
    $this->assertFalse($schema['properties']['psychology']['additionalProperties'] ?? TRUE);
    $this->assertSame(
      [
        'inner_conflict',
        'coping_mechanism',
        'stress_response',
        'insecurity',
        'secret',
        'desire',
        'need',
        'trigger',
        'anchor',
      ],
      $schema['properties']['psychology']['required'] ?? []
    );
  }

}
