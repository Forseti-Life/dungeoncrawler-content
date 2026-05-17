<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the formal storyline definition schema.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class StorylineDefinitionSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies the schema requires the generated boss/dungeon outline and questline graph.
   */
  public function testStorylineDefinitionSchemaRequiresGeneratedOutline(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/storyline_definition.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertContains('metadata', $schema['required'] ?? []);
    $this->assertContains('storyline_type', $schema['required'] ?? []);
    $this->assertContains('questline', $schema['required'] ?? []);
    $this->assertContains('generated_outline', $schema['properties']['metadata']['required'] ?? []);
    $this->assertContains('generation_phase', $schema['properties']['metadata']['properties']['generated_outline']['required'] ?? []);
    $this->assertArrayHasKey('entry_dungeon', $schema['properties']['metadata']['properties']['generated_outline']['properties'] ?? []);
    $this->assertArrayHasKey('bootstrap_handoff', $schema['properties']['metadata']['properties']['generated_outline']['properties'] ?? []);
    $this->assertContains('quest_nodes', $schema['properties']['questline']['required'] ?? []);
  }

}
