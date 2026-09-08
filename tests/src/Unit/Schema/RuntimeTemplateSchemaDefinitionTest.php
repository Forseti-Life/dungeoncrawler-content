<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\Tests\UnitTestCase;

/**
 * Locks the R7 canonical storyline/quest template schema freeze amendment.
 *
 * @group dungeoncrawler_content
 * @group generation
 */
final class RuntimeTemplateSchemaDefinitionTest extends UnitTestCase {

  public function testQuestTemplateMetadataGeneratedByShapeIsStrict(): void {
    $schema = $this->schema('quest_template');
    $generated_by = $schema['definitions']['runtime_metadata']['properties']['generated_by'];

    $this->assertFalse($schema['additionalProperties']);
    $this->assertFalse($generated_by['additionalProperties']);
    $this->assertSame(['source', 'service', 'tool', 'model', 'prompt_hash', 'seed', 'generated_at'], $generated_by['required']);
    $this->assertArrayHasKey('provider', $generated_by['properties']);
  }

  public function testStorylineTemplateMetadataGeneratedByShapeIsStrict(): void {
    $schema = $this->schema('storyline_template');
    $generated_by = $schema['definitions']['generated_by'];

    $this->assertFalse($schema['additionalProperties']);
    $this->assertFalse($generated_by['additionalProperties']);
    $this->assertSame(['source', 'service', 'tool', 'model', 'prompt_hash', 'seed', 'generated_at'], $generated_by['required']);
    $this->assertArrayHasKey('provider', $generated_by['properties']);
  }

  public function testRuntimeTemplateSchemasAcceptOnlyGeneratedByAmendment(): void {
    $validator = new DefinitionSchemaValidator();

    $quest = $this->questPayload();
    $this->assertSame([], $validator->validate($this->schema('quest_template'), $quest));
    $quest['unexpected'] = TRUE;
    $this->assertNotSame([], $validator->validate($this->schema('quest_template'), $quest));

    $storyline = $this->storylinePayload($this->questPayload());
    $this->assertSame([], $validator->validate($this->schema('storyline_template'), $storyline));
    $storyline['unexpected'] = TRUE;
    $this->assertNotSame([], $validator->validate($this->schema('storyline_template'), $storyline));
  }

  private function schema(string $name): array {
    $schema = json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/config/schemas/' . $name . '.schema.json'), TRUE);
    $this->assertIsArray($schema);
    return $schema;
  }

  private function generatedBy(): array {
    return [
      'source' => 'runtime_generated',
      'service' => 'canonical_generation',
      'tool' => 'generate_storyline_template_bundle',
      'model' => 'fixture-model',
      'provider' => 'fixture-provider',
      'prompt_hash' => 'sha256:' . str_repeat('a', 64),
      'seed' => 42,
      'generated_at' => '2026-09-08T00:00:00+00:00',
    ];
  }

  private function questPayload(): array {
    return [
      'schema_version' => 'quest-template-v1',
      'template_id' => 'r7-first-quest',
      'version' => '1.0.0',
      'name' => 'Generated First Quest',
      'description' => 'Follow the generated lead.',
      'quest_type' => 'main',
      'level_min' => 1,
      'level_max' => 4,
      'tags' => ['runtime_generated', 'gen-evidence'],
      'prerequisites' => [],
      'estimated_duration_minutes' => 30,
      'storyline_template_id' => 'r7-storyline',
      'entry_point_actor_id' => 'r7-guide',
      'entry_point_location_id' => 'r7-entry-room',
      'objectives_schema' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'follow-lead',
          'type' => 'explore',
          'description' => 'Follow the generated lead.',
          'next_step' => 'Travel to the generated room.',
          'location_id' => 'r7-entry-room',
          'depends_on' => [],
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'lead_followed',
            'description' => 'The lead is followed.',
            'required_value' => TRUE,
          ],
        ]],
      ]],
      'rewards_schema' => ['xp' => 80],
      'story_impact' => [],
      'metadata' => [
        'runtime_generated' => TRUE,
        'generated_by' => $this->generatedBy(),
      ],
    ];
  }

  private function storylinePayload(array $quest): array {
    return [
      'schema_version' => 'storyline-template-v1',
      'template_id' => 'r7-storyline',
      'version' => '1.0.0',
      'name' => 'Generated Storyline',
      'synopsis' => 'A generated storyline.',
      'level_range' => '1-4',
      'source' => 'runtime_generated',
      'tags' => ['runtime_generated', 'gen-evidence'],
      'storyline_type' => 'questline',
      'metadata' => [
        'runtime_generated' => TRUE,
        'generated_by' => $this->generatedBy(),
        'goal' => 'Follow the generated lead.',
        'generated_outline' => [
          'generation_phase' => 'bootstrap',
          'goal' => 'Follow the generated lead.',
          'entry_point' => [],
          'entry_dungeon' => [],
          'bootstrap_handoff' => [],
        ],
      ],
      'chapters' => [['chapter_id' => 'opening']],
      'linked_quests' => [$quest['template_id'] => ['quest_id' => $quest['template_id']]],
      'questline' => ['primary_quest_id' => $quest['template_id'], 'ordered_quest_ids' => [$quest['template_id']]],
      'asset_references' => [],
      'contacts' => [],
    ];
  }

}
