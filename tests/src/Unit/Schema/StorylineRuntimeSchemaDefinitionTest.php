<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the formal storyline runtime schema definition.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class StorylineRuntimeSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies runtime storyline state is explicitly modeled as a questline.
   */
  public function testStorylineRuntimeSchemaRequiresQuestlineState(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/storyline_runtime.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertContains('storyline_type', $schema['required'] ?? []);
    $this->assertContains('questline', $schema['required'] ?? []);
    $this->assertSame(['questline'], $schema['properties']['storyline_type']['enum'] ?? []);
    $this->assertContains('quest_nodes', $schema['properties']['questline']['required'] ?? []);
  }

}
