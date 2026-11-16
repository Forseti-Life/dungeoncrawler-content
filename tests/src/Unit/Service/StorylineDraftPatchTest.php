<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Exception\StorylineDraftValidationException;
use Drupal\Tests\UnitTestCase;

/**
 * Tests revision-safe, identity-matched partial roster edits.
 *
 * @group dungeoncrawler_content
 */
class StorylineDraftPatchTest extends UnitTestCase {

  /**
   * Partial renaming must not overwrite existing links or narrative.
   */
  public function testRenamingPreservesEveryOtherFieldAndRelationship(): void {
    [$service, $draft] = $this->fixture();
    $plan = $service->planDraftOperations('draft-1', 12, [$this->patch(['display_name' => 'Rikka'])]);
    $expected = $draft['payload'];
    $expected['objects']['actors']['generation_specs'][0]['display_name'] = 'Rikka';
    $this->assertSame($expected, $plan['payload']);
    $this->assertSame(13, $plan['result_revision']);
    $this->assertCount(1, $plan['changes']);
    $this->assertSame('Unnamed scout', $draft['payload']['objects']['actors']['generation_specs'][0]['display_name']);
  }

  /**
   * Repeating a field value must leave the revision unchanged.
   */
  public function testUnchangedPatchDoesNotAdvanceRevision(): void {
    [$service, $draft] = $this->fixture();
    $plan = $service->planDraftOperations('draft-1', 12, [$this->patch(['display_name' => 'Unnamed scout'])]);
    $this->assertFalse($plan['has_changes']);
    $this->assertSame(12, $plan['result_revision']);
    $this->assertSame([], $plan['changes']);
    $this->assertSame($draft['payload'], $plan['payload']);
  }

  /**
   * Partial edits require a nonempty field map.
   *
   * @dataProvider invalidPatchValues
   */
  public function testRejectsNonObjectOrEmptyPatch(mixed $value): void {
    [$service] = $this->fixture();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('storyline_draft_operation_patch_object_required:0');
    $service->planDraftOperations('draft-1', 12, [$this->patch($value)]);
  }

  /**
   * Provides malformed partial edits.
   */
  public static function invalidPatchValues(): array {
    return [
      'empty' => [[]],
      'list' => [['Rikka']],
      'null' => [NULL],
      'scalar' => ['Rikka'],
    ];
  }

  /**
   * A patch cannot bypass required field types.
   */
  public function testMergedRowMustStillConformToSchema(): void {
    [$service] = $this->fixture();
    $this->expectException(StorylineDraftValidationException::class);
    $service->planDraftOperations('draft-1', 12, [$this->patch(['display_name' => NULL])]);
  }

  /**
   * A patch cannot introduce undeclared actor fields.
   */
  public function testUnknownFieldIsRejectedBySchema(): void {
    [$service] = $this->fixture();
    $this->expectException(StorylineDraftValidationException::class);
    $service->planDraftOperations('draft-1', 12, [$this->patch(['invented_field' => 'value'])]);
  }

  /**
   * Missing target actors must fail rather than creating new rows.
   */
  public function testUnknownActorMatchIsRejected(): void {
    [$service] = $this->fixture();
    $operation = $this->patch(['display_name' => 'Rikka']);
    $operation['match']['value'] = 'unknown-actor';
    $this->expectException(\InvalidArgumentException::class);
    $service->planDraftOperations('draft-1', 12, [$operation]);
  }

  /**
   * Nonunique matches must fail rather than picking the first actor.
   */
  public function testAmbiguousMatchIsRejected(): void {
    [$service] = $this->fixture();
    $operation = $this->patch(['display_name' => 'Rikka']);
    $operation['match'] = ['field' => 'role', 'value' => 'scout'];
    $this->expectException(\InvalidArgumentException::class);
    $service->planDraftOperations('draft-1', 12, [$operation]);
  }

  /**
   * Composite identities must preserve every nonmatched row.
   */
  public function testCompositeMatchSelectsOneRow(): void {
    [$service, $draft] = $this->fixture();
    $operation = $this->patch(['display_name' => 'Tavvi']);
    $operation['match'] = ['fields' => ['actor_id' => 'actor-second', 'role' => 'scout']];
    $plan = $service->planDraftOperations('draft-1', 12, [$operation]);
    $expected = $draft['payload'];
    $expected['objects']['actors']['generation_specs'][1]['display_name'] = 'Tavvi';
    $this->assertSame($expected, $plan['payload']);
  }

  /**
   * Partial edits retain optimistic revision checks.
   */
  public function testRevisionConflictRejectsPatch(): void {
    [$service] = $this->fixture();
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('storyline_draft_revision_conflict');
    $service->planDraftOperations('draft-1', 11, [$this->patch(['display_name' => 'Rikka'])]);
  }

  /**
   * New objects can be linked before the author or model knows their IDs.
   */
  public function testAllocatesIdsAndResolvesForwardReferencesDeterministically(): void {
    [$service] = $this->fixture();
    $operations = [
      [
        'type' => 'append',
        'path' => '/objects/actors/generation_specs',
        'key' => 'guide',
        'value' => [
          'role' => 'guide',
          'display_name' => 'Ena',
        ],
      ],
      [
        'type' => 'append',
        'path' => '/process_flow/tasks',
        'key' => 'task',
        'value' => [
          'chapter_id' => 'arrival',
          'scene_id' => ['$draft_ref' => 'scene.scene_id'],
        ],
      ],
      [
        'type' => 'append',
        'path' => '/objects/rooms/generation_specs',
        'key' => 'hall',
        'value' => [
          'dungeon_id' => ['$draft_ref' => 'site.dungeon_id'],
          'scene_id' => ['$draft_ref' => 'scene.scene_id'],
          'narrative' => 'meeting-room',
        ],
      ],
      [
        'type' => 'append',
        'path' => '/process_flow/journal_entries',
        'key' => 'scene',
        'value' => [
          'chapter_id' => 'arrival',
          'title' => 'Meet the watch',
          'summary' => 'The guide introduces the village watch.',
        ],
      ],
      [
        'type' => 'append',
        'path' => '/objects/dungeons/generation_specs',
        'key' => 'site',
        'value' => [
          'chapter_id' => 'arrival',
          'purpose' => 'village-watch-house',
        ],
      ],
      [
        'type' => 'append',
        'path' => '/objects/actors/relationships',
        'value' => [
          'source_actor_id' => ['$draft_ref' => 'guide.actor_id'],
          'target_actor_id' => 'actor-first',
          'attitude' => 'friendly',
          'bond' => 'ally',
          'notes' => 'They serve the same watch.',
        ],
      ],
    ];
    $plan = $service->planDraftOperations('draft-1', 12, $operations);
    $payload = $plan['payload'];
    $this->assertCount(6, $plan['allocated_ids']);
    $this->assertSame($plan, $service->planDraftOperations('draft-1', 12, $operations));
    $this->assertSame(
      $payload['process_flow']['journal_entries'][0]['scene_id'],
      $payload['process_flow']['tasks'][0]['scene_id'],
    );
    $this->assertSame(
      $payload['objects']['dungeons']['generation_specs'][0]['dungeon_id'],
      $payload['objects']['rooms']['generation_specs'][0]['dungeon_id'],
    );
    $this->assertSame(
      $payload['objects']['actors']['generation_specs'][2]['actor_id'],
      $payload['objects']['actors']['relationships'][1]['source_actor_id'],
    );
    $this->assertStringNotContainsString('$draft_ref', json_encode($payload));
    $this->assertArrayNotHasKey('key', $payload['objects']['actors']['generation_specs'][2]);
  }

  /**
   * Allocation must account for IDs supplied later in the same batch.
   */
  public function testGeneratedIdsAvoidExplicitSameBatchCollisions(): void {
    [$service] = $this->fixture();
    $append = [
      'type' => 'append',
      'path' => '/objects/actors/generation_specs',
      'value' => [
        'role' => 'guide',
        'display_name' => 'New guide',
      ],
    ];
    $first = $service->planDraftOperations('draft-1', 12, [$append]);
    $reserved = $first['payload']['objects']['actors']['generation_specs'][2]['actor_id'];
    $explicit = $append;
    $explicit['value'] = [
      'actor_id' => $reserved,
      'contact_id' => 'contact-explicit',
      'role' => 'guide',
      'display_name' => 'Other guide',
    ];
    $plan = $service->planDraftOperations('draft-1', 12, [$append, $explicit]);
    $actors = $plan['payload']['objects']['actors']['generation_specs'];
    $this->assertSame($reserved . '-2', $actors[2]['actor_id']);
    $this->assertSame($reserved, $actors[3]['actor_id']);
  }

  /**
   * Bad symbolic references must fail rather than reach persisted payloads.
   *
   * @dataProvider invalidBatchReferences
   */
  public function testRejectsInvalidBatchReferences(array $reference, string $message): void {
    [$service] = $this->fixture();
    $operation = [
      'type' => 'append',
      'path' => '/objects/actors/generation_specs',
      'key' => 'guide',
      'value' => [
        'actor_id' => $reference,
        'role' => 'guide',
        'display_name' => 'Ena',
      ],
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    $service->planDraftOperations('draft-1', 12, [$operation]);
  }

  /**
   * Covers absent, cyclic, malformed, and non-identity reference targets.
   */
  public static function invalidBatchReferences(): array {
    return [
      'unknown key' => [['$draft_ref' => 'missing.actor_id'], 'storyline_draft_batch_reference_invalid'],
      'unknown field' => [['$draft_ref' => 'guide.missing_id'], 'storyline_draft_batch_reference_target_invalid'],
      'self cycle' => [['$draft_ref' => 'guide.actor_id'], 'storyline_draft_batch_reference_invalid'],
      'extra reference data' => [
        ['$draft_ref' => 'guide.actor_id', 'other' => TRUE],
        'storyline_draft_batch_reference_invalid',
      ],
      'not an identity' => [['$draft_ref' => 'guide.display_name'], 'storyline_draft_batch_reference_invalid'],
    ];
  }

  /**
   * A duplicate batch key must never silently select one of two new objects.
   */
  public function testRejectsDuplicateBatchKeys(): void {
    [$service] = $this->fixture();
    $append = [
      'type' => 'append',
      'path' => '/objects/actors/generation_specs',
      'key' => 'guide',
      'value' => [
        'role' => 'guide',
        'display_name' => 'Ena',
      ],
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('storyline_draft_batch_key_invalid_or_duplicate:1');
    $service->planDraftOperations('draft-1', 12, [$append, $append]);
  }

  /**
   * Automatically allocated choices must belong to the same decision group.
   */
  public function testNewChoicesShareTheirAutomaticallyAllocatedDecision(): void {
    [$service] = $this->fixture();
    $append = [
      'type' => 'append',
      'path' => '/process_flow/branch_choices',
      'value' => [
        'source_task_id' => 'watch-task',
        'label' => 'Negotiate a truce',
        'trigger' => [
          'type' => 'player_choice',
          'condition' => 'The party selects this approach.',
          'source_id' => 'watch-task',
        ],
        'effects' => ['summary' => 'The watch dispute ends.', 'actors' => [], 'environment' => [], 'storyline' => []],
        'terminal' => TRUE,
      ],
    ];
    $second = $append;
    $second['value']['label'] = 'Challenge the captain';
    $plan = $service->planDraftOperations('draft-1', 12, [$append, $second]);
    $choices = $plan['payload']['process_flow']['branch_choices'];
    $this->assertSame($choices[0]['decision_id'], $choices[1]['decision_id']);
    $this->assertNotSame($choices[0]['choice_id'], $choices[1]['choice_id']);
    $second['value']['decision_id'] = 'grounded-existing-decision';
    $mixed = $service->planDraftOperations('draft-1', 12, [$append, $second]);
    $this->assertSame(
      ['grounded-existing-decision', 'grounded-existing-decision'],
      array_column($mixed['payload']['process_flow']['branch_choices'], 'decision_id'),
    );
  }

  /**
   * Constructs an identity-matched native operation.
   */
  private function patch(mixed $value): array {
    return [
      'type' => 'patch',
      'path' => '/objects/actors/generation_specs',
      'match' => ['field' => 'actor_id', 'value' => 'actor-first'],
      'value' => $value,
    ];
  }

  /**
   * Provides an original draft with sheet links and an actor relationship.
   */
  private function fixture(): array {
    $service = $this->getMockBuilder(StorylineAuthoringDraftService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getDraft'])
      ->getMock();
    $architecture = (new \ReflectionMethod($service, 'emptyNarrativeArchitecture'))->invoke($service);
    $draft = [
      'draft_id' => 'draft-1',
      'revision' => 12,
      'status' => 'editing',
      'payload' => [
        'schema_version' => 'storyline-authoring-draft-v1',
        'storyline_id' => 'original-story',
        'title' => 'Original fixture',
        'description' => 'An original village adventure.',
        'premise' => 'Two scouts watch their village.',
        'process_flow' => [
          'journal_entries' => [],
          'tasks' => [],
          'objective_completion_criteria' => [],
          'conflicts' => [],
          'narrative_architecture' => $architecture,
        ],
        'objects' => [
          'actors' => [
            'generation_specs' => [
              [
                'contact_id' => 'contact-first',
                'actor_id' => 'actor-first',
                'role' => 'scout',
                'display_name' => 'Unnamed scout',
                'motivation' => 'Protect the village.',
                'dialogue' => 'The road is clear.',
                'knowledge' => 'Knows the watch paths.',
                'character_id' => 42,
                'character_sheet_revision' => 3,
              ],
              [
                'contact_id' => 'contact-second',
                'actor_id' => 'actor-second',
                'role' => 'scout',
                'display_name' => 'Second scout',
              ],
            ],
            'validation_criteria' => [],
            'relationships' => [
              [
                'source_actor_id' => 'actor-first',
                'target_actor_id' => 'actor-second',
                'attitude' => 'friendly',
                'bond' => 'ally',
                'notes' => 'They share the village watch.',
              ],
            ],
          ],
          'dungeons' => ['generation_specs' => []],
          'rooms' => ['generation_specs' => [], 'validation_criteria' => []],
          'items' => ['generation_specs' => [], 'validation_criteria' => []],
        ],
        'orchestration' => ['generation' => [], 'validation' => []],
      ],
    ];
    $service->method('getDraft')->with('draft-1')->willReturn($draft);
    return [$service, $draft];
  }

}
