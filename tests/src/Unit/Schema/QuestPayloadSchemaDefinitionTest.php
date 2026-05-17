<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the formal quest payload schema definitions.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestPayloadSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies the hexmap quest summary schema is explicit and versioned.
   */
  public function testQuestSummarySchemaRequiresVersionedActiveAndAvailableBuckets(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/quest_summary.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['quest-summary-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('active', $schema['required'] ?? []);
    $this->assertContains('available', $schema['required'] ?? []);
    $this->assertContains('counts', $schema['required'] ?? []);
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

  /**
   * Verifies the room-chat quest update schema is explicit and source-aware.
   */
  public function testQuestUpdateSchemaRequiresSourceAndStorylineId(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/quest_update.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['quest-update-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('source', $schema['required'] ?? []);
    $this->assertContains('storyline_id', $schema['required'] ?? []);
    $this->assertSame(
      ['available_quest', 'brokered_storyline'],
      $schema['properties']['source']['enum'] ?? []
    );
  }

}
