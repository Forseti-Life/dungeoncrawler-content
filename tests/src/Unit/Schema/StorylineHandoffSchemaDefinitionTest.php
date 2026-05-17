<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the formal storyline handoff schemas.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class StorylineHandoffSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies the bootstrap request schema requires the normalized handoff fields.
   */
  public function testBootstrapRequestSchemaRequiresAnchorFields(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/storyline_bootstrap_request.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertContains('prompt', $schema['required'] ?? []);
    $this->assertContains('speaker_npc_id', $schema['required'] ?? []);
    $this->assertContains('lead_location_id', $schema['required'] ?? []);
    $this->assertContains('first_quest_id', $schema['required'] ?? []);
  }

  /**
   * Verifies the queued expansion job schema wraps a normalized request payload.
   */
  public function testExpansionJobSchemaRequiresNestedRequestPayload(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/storyline_expansion_job.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertContains('schema_version', $schema['required'] ?? []);
    $this->assertContains('request', $schema['required'] ?? []);
    $this->assertContains('entry_dungeon_id', $schema['properties']['request']['required'] ?? []);
    $this->assertContains('first_quest_id', $schema['properties']['request']['required'] ?? []);
  }

}
