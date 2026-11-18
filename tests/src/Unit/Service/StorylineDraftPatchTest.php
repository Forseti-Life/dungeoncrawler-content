<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\ObjectiveTypeService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
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
   * Changing a roster actor's ID changes every reference to it in one save.
   */
  public function testChangingAnActorsIdentityRebindsEveryReference(): void {
    [$service] = $this->fixture();
    $plan = $service->planDraftOperations('draft-1', 12, [$this->patch(['actor_id' => 'library-scout'])]);
    $actors = $plan['payload']['objects']['actors'];

    $this->assertSame('library-scout', $actors['generation_specs'][0]['actor_id']);
    $this->assertSame('contact-first', $actors['generation_specs'][0]['contact_id'], 'An identity that did not change is left alone.');
    $this->assertSame('library-scout', $actors['relationships'][0]['source_actor_id']);
    $this->assertSame('actor-second', $actors['relationships'][0]['target_actor_id']);
    $this->assertStringNotContainsString('actor-first', json_encode($plan['payload']));
  }

  /**
   * IDs inside a comma-separated list are rebound too.
   */
  public function testIdentityChangeReachesCommaSeparatedLists(): void {
    $method = new \ReflectionMethod(StorylineAuthoringDraftService::class, 'rebindChangedIdentities');
    $method->setAccessible(TRUE);
    $payload = static fn(string $id): array => [
      'objects' => ['actors' => ['generation_specs' => [['actor_id' => $id, 'contact_id' => $id]]]],
      'process_flow' => ['journal_entries' => [['character_ids' => 'guide, ' . $id, 'summary' => 'The captain, weary, waits.']]],
      'canonical_template' => ['note' => 'captain'],
    ];
    $after = $method->invoke(NULL, $payload('captain'), ['process_flow' => $payload('captain')['process_flow'], 'canonical_template' => ['note' => 'captain']] + $payload('tal-mission-handler'));

    $this->assertSame('guide, tal-mission-handler', $after['process_flow']['journal_entries'][0]['character_ids']);
    $this->assertSame('The captain, weary, waits.', $after['process_flow']['journal_entries'][0]['summary'], 'Prose is not an ID list.');
    $this->assertSame('captain', $after['canonical_template']['note']);
  }

  /**
   * A character new to the story is placed; a library character need not be.
   */
  public function testNewCharactersMustBePlacedInTheStory(): void {
    [$service] = $this->fixture();
    $statement = new class {

      public function fetchCol(): array {
        return ['captain'];
      }

    };
    $select = $this->createMock(\Drupal\Core\Database\Query\SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    $database = $this->createMock(\Drupal\Core\Database\Connection::class);
    $database->method('select')->willReturn($select);
    (new \ReflectionProperty(StorylineAuthoringDraftService::class, 'database'))->setValue($service, $database);
    $method = new \ReflectionMethod($service, 'actorPlacementFindings');

    $findings = $method->invoke($service, ['created_at' => 100, 'payload' => ['objects' => [
      'rooms' => ['generation_specs' => [['room_id' => 'clan-hall']]],
      'dungeons' => ['generation_specs' => [['dungeon_id' => 'clan-town']]],
      'actors' => ['generation_specs' => [
        ['actor_id' => 'captain', 'display_name' => 'The Captain'],
        ['actor_id' => 'chieftain', 'display_name' => 'The Chieftain', 'location_id' => 'clan-hall'],
        ['actor_id' => 'heir', 'display_name' => 'The Heir', 'location_id' => 'clan-town'],
        ['actor_id' => 'scout', 'display_name' => 'The Scout'],
        ['actor_id' => 'exile', 'display_name' => 'The Exile', 'location_id' => 'old-hut'],
      ]],
    ]]]);

    $this->assertSame(
      ['scout' => 'actor_placement_missing', 'exile' => 'actor_placement_unknown'],
      array_column($findings, 'code', 'actor_id'),
    );
  }

  /**
   * A site's brief is what each storyline that names it needs from it.
   */
  public function testStoryBriefCollectsWhatTheStoryNeedsFromASite(): void {
    [$service] = $this->fixture();
    $payload = [
      'title' => 'The Clan\'s Blight',
      'premise' => 'A clan must leave its home.',
      'process_flow' => [
        'journal_entries' => [['scene_id' => 'scene-hall', 'title' => 'The Hall', 'summary' => 'The chieftain holds court.']],
        'tasks' => [['task_id' => 't1', 'scene_id' => 'scene-hall', 'goal_text' => 'Win the chieftain over.']],
        'objective_completion_criteria' => [['objective_id' => 'o1', 'location' => 'clan-hall', 'description' => 'Reach the hall.']],
      ],
      'objects' => [
        'dungeons' => ['generation_specs' => [['dungeon_id' => 'clan-town', 'purpose' => 'The clan\'s home.', 'entrance_room' => 'clan-hall']]],
        'rooms' => ['generation_specs' => [
          ['dungeon_id' => 'clan-town', 'room_id' => 'clan-hall', 'scene_id' => 'scene-hall', 'narrative' => 'Where the chieftain rules.'],
          ['dungeon_id' => 'kobold-caves', 'room_id' => 'cave-mouth', 'scene_id' => 'scene-hall', 'narrative' => 'Elsewhere.'],
        ]],
        'actors' => ['generation_specs' => [['actor_id' => 'chieftain', 'display_name' => 'Grubba', 'role' => 'clan_chieftain', 'location_id' => 'clan-hall']]],
      ],
    ];
    $statement = new class([
      ['draft_id' => 'd1', 'status' => 'editing', 'revision' => 3, 'draft_payload' => json_encode($payload)],
      ['draft_id' => 'd2', 'status' => 'editing', 'revision' => 1, 'draft_payload' => json_encode(['title' => 'Unrelated'])],
    ]) {

      public function __construct(private array $rows) {}

      public function fetchAll(int $mode): array {
        return $this->rows;
      }

    };
    $select = $this->createMock(\Drupal\Core\Database\Query\SelectInterface::class);
    foreach (['fields', 'orderBy'] as $method) {
      $select->method($method)->willReturnSelf();
    }
    $select->method('execute')->willReturn($statement);
    $database = $this->createMock(\Drupal\Core\Database\Connection::class);
    $database->method('select')->willReturn($select);
    $user = $this->createMock(\Drupal\Core\Session\AccountProxyInterface::class);
    $user->method('id')->willReturn(7);
    $user->method('hasPermission')->willReturn(TRUE);
    (new \ReflectionProperty(StorylineAuthoringDraftService::class, 'database'))->setValue($service, $database);
    (new \ReflectionProperty(StorylineAuthoringDraftService::class, 'currentUser'))->setValue($service, $user);

    $brief = $service->storyBriefs('dungeon', 'clan-town')['storylines'];

    $this->assertSame(['d1'], array_column($brief, 'draft_id'));
    $this->assertSame('clan-hall', $brief[0]['entrance_room']);
    $this->assertSame(['clan-hall'], array_column($brief[0]['rooms'], 'room_id'));
    $room = $brief[0]['rooms'][0];
    $this->assertSame('The Hall', $room['scene']);
    $this->assertSame(['Win the chieftain over.'], $room['tasks']);
    $this->assertSame(['Reach the hall.'], $room['objectives']);
    $this->assertSame(['Grubba'], array_column($room['starting_cast'], 'name'));
    $this->assertSame(['cave-mouth'], array_column($service->storyBriefs('room', 'cave-mouth')['storylines'][0]['rooms'], 'room_id'));
  }

  /**
   * A room keeps its references when renamed; a used room cannot be removed.
   */
  public function testRoomIdentityIsCarriedAndAUsedRoomCannotBeRemoved(): void {
    $payload = static fn(array $rooms, string $actor_room): array => ['objects' => [
      'rooms' => ['generation_specs' => $rooms],
      'dungeons' => ['generation_specs' => [['dungeon_id' => 'clan-town', 'entrance_room' => $actor_room]]],
      'actors' => ['generation_specs' => [['actor_id' => 'chieftain', 'contact_id' => 'chieftain', 'display_name' => 'Grubba', 'location_id' => $actor_room]]],
    ]];
    $room = static fn(string $id): array => ['room_id' => $id, 'dungeon_id' => 'clan-town'];
    $rebind = new \ReflectionMethod(StorylineAuthoringDraftService::class, 'rebindChangedIdentities');
    $assert = new \ReflectionMethod(StorylineAuthoringDraftService::class, 'assertRemovedPlacesAreUnreferenced');

    $after = $rebind->invoke(NULL, $payload([$room('hall')], 'hall'), ['objects' => ['rooms' => ['generation_specs' => [$room('great-hall')]]] + $payload([], 'hall')['objects']]);
    $this->assertSame('great-hall', $after['objects']['actors']['generation_specs'][0]['location_id']);
    $this->assertSame('great-hall', $after['objects']['dungeons']['generation_specs'][0]['entrance_room']);

    $assert->invoke(NULL, $payload([$room('hall'), $room('hut')], 'hall'), $payload([$room('hall')], 'hall'));
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage("room 'hall' is removed by this change but is still used by actor Grubba, entrance of site clan-town");
    $assert->invoke(NULL, $payload([$room('hall'), $room('hut')], 'hall'), $payload([$room('hut')], 'hall'));
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
   * String-array fields are preserved rather than cast to the literal "Array".
   */
  public function testSceneAnnotationCsvFieldsAcceptArraysAndStrings(): void {
    [$service] = $this->fixture();
    $method = new \ReflectionMethod($service, 'buildStorylineAnalysisMetadata');
    $method->setAccessible(TRUE);
    $scenes = [[
      'scene_id' => 'scene-1',
      'summary' => 'Scene summary',
    ]];

    $from_arrays = $method->invoke($service, [
      'process_flow' => [
        'journal_entries' => [[
          'scene_id' => 'scene-1',
          'summary' => 'Scene summary',
          'themes' => ['threat', 'survival'],
          'character_ids' => ['guide', 'rival'],
        ]],
      ],
    ], $scenes);
    $from_csv = $method->invoke($service, [
      'process_flow' => [
        'journal_entries' => [[
          'scene_id' => 'scene-1',
          'summary' => 'Scene summary',
          'themes' => 'threat, survival',
          'character_ids' => 'guide, rival',
        ]],
      ],
    ], $scenes);

    $array_annotation = $from_arrays['scene_annotations']['scene-1'];
    $csv_annotation = $from_csv['scene_annotations']['scene-1'];

    $this->assertSame(['threat', 'survival'], $array_annotation['themes']);
    $this->assertSame(['guide', 'rival'], $array_annotation['character_ids']);
    $this->assertSame($array_annotation['themes'], $csv_annotation['themes']);
    $this->assertSame($array_annotation['character_ids'], $csv_annotation['character_ids']);
    $this->assertNotContains('Array', $array_annotation['themes']);
    $this->assertNotContains('Array', $array_annotation['character_ids']);
  }

  /**
   * Backward links come from resolved forward edges, not authored unlocks_after.
   */
  public function testQuestlineProjectionDerivesUnlocksAfterFromResolvedEdges(): void {
    [$service] = $this->fixture();
    $method = new \ReflectionMethod($service, 'buildQuestlineFromTaskRows');
    $method->setAccessible(TRUE);

    $questline = $method->invoke($service, [
      [
        'task_id' => 'a',
        'chapter_id' => 'chapter-1',
        'scene_id' => 'scene-1',
        'unlocks_to' => 'b',
      ],
      [
        'task_id' => 'b',
        'chapter_id' => 'chapter-1',
        'scene_id' => 'scene-1',
        'unlocks_after' => 'z',
      ],
      [
        'task_id' => 'c',
        'chapter_id' => 'chapter-1',
        'scene_id' => 'scene-1',
      ],
    ], []);

    $nodes = [];
    foreach ($questline['quest_nodes'] as $node) {
      $nodes[$node['quest_id']] = $node;
    }
    $this->assertSame(['a'], $nodes['b']['unlocks_after']);
    $this->assertSame(['c'], $nodes['b']['unlocks_to']);
    $this->assertSame('complete_previous_quest', $nodes['b']['unlock_condition']);
    $this->assertSame([], $nodes['a']['unlocks_after']);
  }

  /**
   * Authored edge mismatch warnings explain which field to update.
   */
  public function testAuthoredQuestlineEdgeWarningsCallOutIgnoredFields(): void {
    [$service] = $this->fixture();
    $method = new \ReflectionMethod($service, 'validateAuthoredQuestlineEdgeProjection');
    $method->setAccessible(TRUE);
    $warnings = $method->invoke($service, [
      'process_flow' => [
        'tasks' => [
          ['task_id' => 'a', 'unlocks_to' => 'b,c'],
          ['task_id' => 'b', 'unlocks_after' => 'z'],
        ],
        'branch_choices' => [
          ['source_task_id' => 'a', 'target_task_id' => 'b', 'terminal' => FALSE],
        ],
      ],
    ], [
      'questline' => [
        'quest_nodes' => [
          ['quest_id' => 'a', 'unlocks_to' => ['b'], 'unlocks_after' => []],
          ['quest_id' => 'b', 'unlocks_to' => [], 'unlocks_after' => ['a']],
        ],
      ],
    ]);

    $this->assertSame(1, count($warnings));
    $this->assertStringContainsString("Task 'b' unlocks_after is not used", $warnings[0]);
  }

  /**
   * A choice that replaces a task's continuation is reported as the cause.
   */
  public function testChoiceThatDropsATasksContinuationIsReportedAsTheCause(): void {
    [$service] = $this->fixture();
    $method = new \ReflectionMethod($service, 'authoredChoiceFindings');
    $method->setAccessible(TRUE);
    $findings = $method->invoke($service, ['process_flow' => [
      'tasks' => [
        ['task_id' => 'answer-summons', 'unlocks_to' => 'meet-hunters, staging'],
        ['task_id' => 'meet-hunters'],
        ['task_id' => 'staging', 'unlocks_to' => 'meet-hunters'],
      ],
      'branch_choices' => [
        ['choice_id' => 'c1', 'label' => 'Clan lost', 'source_task_id' => 'answer-summons', 'target_task_id' => '', 'terminal' => TRUE],
        ['choice_id' => 'c2', 'label' => 'Detour', 'source_task_id' => 'answer-summons', 'target_task_id' => 'staging', 'terminal' => FALSE],
        ['choice_id' => 'c3', 'label' => 'Orphan', 'source_task_id' => 'no-such-task', 'target_task_id' => 'staging', 'terminal' => FALSE],
        ['choice_id' => 'c4', 'label' => 'Anywhere', 'source_task_id' => '', 'target_task_id' => 'staging', 'terminal' => FALSE],
      ],
    ]]);

    $this->assertSame(['choice_source_task_unknown', 'continuation_replaced_by_choices'], array_column($findings, 'code'));
    $this->assertSame('c3', $findings[0]['choice_id']);
    $this->assertSame('answer-summons', $findings[1]['task_id']);
    $this->assertSame(['meet-hunters'], $findings[1]['dropped_task_ids'], 'Only the continuation no choice leads to is dropped.');
  }

  /**
   * Routes rejoin on any one of them unless the task asks for all.
   */
  public function testRejoiningTaskOpensOnAnyRouteUnlessItAsksForAll(): void {
    [$service] = $this->fixture();
    $method = new \ReflectionMethod($service, 'buildQuestlineFromTaskRows');
    $method->setAccessible(TRUE);
    $tasks = [
      ['task_id' => 'caves', 'unlocks_to' => 'wolf'],
      ['task_id' => 'kin'],
      ['task_id' => 'wolf'],
    ];
    $nodes = array_column($method->invoke($service, $tasks, [])['quest_nodes'], NULL, 'quest_id');
    $this->assertSame(['caves', 'kin'], $nodes['wolf']['unlocks_after']);
    $this->assertSame('any', $nodes['wolf']['prerequisite_mode']);

    $tasks[2]['prerequisite_mode'] = 'all';
    $nodes = array_column($method->invoke($service, $tasks, [])['quest_nodes'], NULL, 'quest_id');
    $this->assertSame('all', $nodes['wolf']['prerequisite_mode']);
  }

  /**
   * An escort that can fail with nowhere for the story to go is reported.
   */
  public function testEscortWhoseFailureStrandsTheStoryIsReported(): void {
    [$service] = $this->fixture();
    $method = new \ReflectionMethod($service, 'authoredChoiceFindings');
    $method->setAccessible(TRUE);
    $payload = ['process_flow' => [
      'tasks' => [['task_id' => 'road'], ['task_id' => 'arrive'], ['task_id' => 'report']],
      'objective_completion_criteria' => [
        ['objective_id' => 'o1', 'task_id' => 'road', 'type' => 'escort'],
        ['objective_id' => 'o2', 'task_id' => 'report', 'type' => 'escort'],
      ],
      'branch_choices' => [],
    ]];
    $findings = $method->invoke($service, $payload);
    $this->assertSame(['task_failure_strands_story'], array_column($findings, 'code'), 'The last task leads nowhere, so its failure strands nothing.');
    $this->assertSame('road', $findings[0]['task_id']);
    $this->assertSame('warning', $findings[0]['severity']);

    $payload['process_flow']['branch_choices'][] = [
      'choice_id' => 'lost', 'label' => 'The escort is lost', 'source_task_id' => '', 'target_task_id' => 'report', 'terminal' => FALSE,
      'trigger' => ['when' => ['subject_type' => 'task', 'subject_id' => 'road', 'measure' => 'failed']],
    ];
    $this->assertSame([], $method->invoke($service, $payload));
  }

  /**
   * A choice with no source task is storyline-wide, not a choice on a task.
   */
  public function testChoiceWithoutSourceTaskProjectsAsStorylineWide(): void {
    [$service] = $this->fixture();
    $method = new \ReflectionMethod($service, 'buildQuestlineFromTaskRows');
    $method->setAccessible(TRUE);
    $choice = static fn(string $id, string $source, string $target): array => [
      'decision_id' => 'd-' . $id, 'choice_id' => $id, 'label' => $id, 'source_task_id' => $source,
      'trigger' => ['type' => 'gm_decision', 'condition' => 'c', 'source_id' => 's'],
      'effects' => ['summary' => 's', 'actors' => [], 'environment' => [], 'storyline' => []],
      'target_task_id' => $target, 'target_scene_id' => $target === '' ? '' : 'scene-1', 'terminal' => $target === '',
    ];
    $questline = $method->invoke($service, [
      ['task_id' => 'a', 'chapter_id' => 'c', 'scene_id' => 'scene-1'],
      ['task_id' => 'b', 'chapter_id' => 'c', 'scene_id' => 'scene-1'],
      ['task_id' => 'debrief', 'chapter_id' => 'c', 'scene_id' => 'scene-1'],
    ], [$choice('clan-lost', '', 'debrief')]);
    $nodes = array_column($questline['quest_nodes'], NULL, 'quest_id');

    $this->assertSame(['clan-lost'], array_column($questline['storyline_choices'], 'choice_id'));
    $this->assertSame([], $nodes['a']['choices']);
    $this->assertSame(['b'], $nodes['a']['unlocks_to'], 'The task keeps its normal continuation.');
    $this->assertArrayNotHasKey('storyline_choices', $method->invoke($service, [['task_id' => 'a']], []));
  }

  /**
   * A decision with a single choice is a decision.
   */
  public function testDecisionWithOneChoiceCountsAsADecision(): void {
    [$service] = $this->fixture();
    $state = $service->summarizeDraftState(['payload' => ['process_flow' => ['branch_choices' => [
      ['decision_id' => 'report', 'choice_id' => 'inform-captain'],
    ]]]], []);

    $this->assertSame(1, $state['counts']['decisions']);
    $this->assertSame(1, $state['counts']['choices']);
  }

  /**
   * Story text about a cast member is carried to its sheet; a build change is not.
   */
  public function testCastStoryTextIsCarriedToTheSheetOnSave(): void {
    $applied = [];
    $characters = $this->createMock(CharacterCreationGmService::class);
    $characters->method('getDraft')->willReturn(['revision' => 4, 'payload' => ['name' => 'Rilla']]);
    $characters->method('applyDraftUpdates')->willReturnCallback(static function (int $id, int $revision, array $updates) use (&$applied): array {
      $applied[] = [$id, $revision, $updates];
      return ['draft' => ['revision' => $revision + 1]];
    });
    $service = new StorylineAuthoringDraftService(
      $this->createMock(Connection::class),
      $this->createMock(StorylineManagerService::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      NULL, NULL, NULL, NULL, NULL,
      $characters,
    );
    $row = static function (string $id, array $changes = []): array {
      $actor = ['actor_id' => $id, 'display_name' => 'Rilla', 'role' => 'scout', 'motivation' => 'Find the cure.', 'attitude' => 'friendly',
        'knowledge' => 'The trails.', 'class' => 'ranger', 'ancestry' => 'goblin', 'level' => 1, 'character_id' => 70, 'character_sheet_revision' => 4];
      $actor['character_sheet_spec_hash'] = StorylineAuthoringDraftService::actorSpecHash($actor);
      return $changes + $actor;
    };
    $payload = static fn(array $actors): array => ['objects' => ['actors' => ['generation_specs' => $actors]]];
    $carry = new \ReflectionMethod($service, 'carryCastNarrativeToSheets');
    $carry->setAccessible(TRUE);

    $after = $carry->invoke($service, $payload([$row('rilla'), $row('vurn')]), $payload([
      $row('rilla', ['motivation' => 'Beat her brother to the cure.', 'alignment' => 'CG']),
      $row('vurn', ['class' => 'wizard']),
    ]))['objects']['actors']['generation_specs'];

    $this->assertCount(1, $applied);
    $this->assertSame(70, $applied[0][0]);
    $this->assertSame("Beat her brother to the cure.\nThe trails.", $applied[0][2]['backstory']);
    $this->assertSame('CG', $applied[0][2]['alignment']);
    $this->assertArrayNotHasKey('name', $applied[0][2]);
    $this->assertSame(5, $after[0]['character_sheet_revision']);
    $this->assertSame(StorylineAuthoringDraftService::actorSpecHash($after[0]), $after[0]['character_sheet_spec_hash']);
    // A different class is a different build: its sheet stays outdated.
    $this->assertNotSame(StorylineAuthoringDraftService::actorSpecHash($after[1]), $after[1]['character_sheet_spec_hash']);
  }

  /**
   * Institutions are stage 3: defined for a cast, peopled, and tied to it.
   */
  public function testInstitutionsStageFollowsTheCastsTies(): void {
    // 'veteran' was in the library before the draft, so it need not be tied.
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchCol')->willReturn(['veteran']);
    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);
    $service = new StorylineAuthoringDraftService(
      $database,
      $this->createMock(StorylineManagerService::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
    );
    $actor = static fn(string $id, array $affiliations = []): array => [
      'contact_id' => $id, 'actor_id' => $id, 'role' => 'scout', 'display_name' => ucfirst($id),
    ] + ($affiliations === [] ? [] : ['affiliations' => $affiliations]);
    $state = static fn(array $actors, ?array $institutions): array => $service->summarizeDraftState(['payload' => ['objects' => [
      'actors' => ['generation_specs' => $actors],
    ] + ($institutions === NULL ? [] : ['institutions' => $institutions])]], []);
    $stage = static fn(array $state): array => array_column($state['stages'], NULL, 'stage')[3];
    $clan = ['institution_id' => 'institution_kin_blackroot-clan', 'domain' => 'family', 'name' => 'Blackroot Clan'];
    $guard = ['institution_id' => 'institution_state_town-guard', 'domain' => 'security', 'name' => 'Town Guard', 'stances' => [
      ['toward_institution_id' => $clan['institution_id'], 'attitude' => 'hostile'],
    ]];

    $none = $state([$actor('grubba'), $actor('nikka'), $actor('veteran')], NULL);
    $this->assertSame([
      'Identity and premise', 'Progression and conflict', 'Institutions', 'Actors and relationships',
      'Dungeons and rooms', 'Items and prerequisites', 'Composition and validation', 'Canonical review and creation',
    ], array_column($none['stages'], 'label'));
    $this->assertSame(['institutions' => 0, 'cast_tied' => '0/3', 'stances' => 0], $stage($none)['metrics']);
    $this->assertSame(1, $stage($none)['complete_checks']);
    $this->assertContains('At least one institution', $none['missing_content']);
    $this->assertContains('Every new cast member is tied to an institution', $none['missing_content']);
    $this->assertNotContains('Every institution has a cast member', $none['missing_content']);

    $partial = $state([$actor('grubba', [['institution_id' => $clan['institution_id'], 'standing' => 'leader']]), $actor('nikka'), $actor('veteran')], [$clan, $guard]);
    $this->assertSame(['institutions' => 2, 'cast_tied' => '1/3', 'stances' => 1], $stage($partial)['metrics']);
    $this->assertSame(2, $partial['counts']['institutions']);
    $this->assertSame(1, $stage($partial)['complete_checks'], 'The guard has no member and Nikka has no institution.');

    $tied = $state([
      $actor('grubba', [['institution_id' => $clan['institution_id'], 'standing' => 'leader']]),
      $actor('nikka', [['institution_id' => $guard['institution_id'], 'standing' => 'ally']]),
      $actor('veteran'),
    ], [$clan, $guard]);
    $this->assertTrue($stage($tied)['complete'], 'A library character need not be tied.');
    $this->assertSame('2/3', $stage($tied)['metrics']['cast_tied']);

    $this->assertTrue($stage($state([$actor('grubba')], NULL))['complete'], 'A cast of one needs no institution.');
  }

  /**
   * New storyline-wide choices are independent decisions.
   */
  public function testNewStorylineWideChoicesGetSeparateDecisions(): void {
    [$service] = $this->fixture();
    $append = static fn(string $label): array => [
      'type' => 'append',
      'path' => '/process_flow/branch_choices',
      'value' => [
        'source_task_id' => '',
        'label' => $label,
        'trigger' => ['type' => 'gm_decision', 'condition' => 'The condition holds.', 'source_id' => 'clan'],
        'effects' => ['summary' => 'The story turns.', 'actors' => [], 'environment' => [], 'storyline' => []],
        'terminal' => TRUE,
      ],
    ];
    $plan = $service->planDraftOperations('draft-1', 12, [$append('Clan lost'), $append('Captain killed')]);
    $choices = $plan['payload']['process_flow']['branch_choices'];
    $this->assertNotSame($choices[0]['decision_id'], $choices[1]['decision_id']);
  }

  /**
   * Stage routes advertise whether work steps or remediation should fix them.
   */
  public function testValidationStageRoutesExposeFixPathContract(): void {
    $routes = (new \ReflectionClass(StorylineAuthoringDraftService::class))
      ->getConstant('STAGE_FIX_ROUTES');
    $this->assertSame('draft_operation', $routes['schema']);
    $this->assertSame('draft_operation', $routes['questline_progression']);
    $this->assertSame('draft_operation', $routes['objective_control_chain']);
    $this->assertSame('draft_operation', $routes['task_contracts']);
    $this->assertSame('dungeon_editor', $routes['authored_room_dungeon_bindings']);
    $this->assertSame('draft_operation', $routes['authored_questline_edges']);
  }

  /**
   * Publication writes one quest record per task, built from the task's rows.
   */
  public function testPublicationWritesEachTasksQuestRecordFromItsOwnRows(): void {
    $written = [];
    $quest_templates = $this->createMock(CanonicalQuestTemplateService::class);
    $quest_templates->method('saveStorylineQuestRecord')->willReturnCallback(static function (array $record) use (&$written): array {
      $written[] = $record;
      return ['template_id' => $record['template_id'], 'created' => TRUE];
    });
    $service = new StorylineAuthoringDraftService(
      $this->createMock(Connection::class),
      $this->createMock(StorylineManagerService::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      NULL,
      $quest_templates,
      new ObjectiveTypeService(),
    );
    $publish = new \ReflectionMethod($service, 'publishTaskQuestRecords');
    $publish->setAccessible(TRUE);
    $row = static fn(string $id, string $task): array => [
      'objective_id' => $id, 'task_id' => $task, 'type' => 'interact', 'target' => 'captain',
      'description' => 'Tell the captain.', 'next_step' => 'Speak with the captain.',
    ];
    $payload = ['process_flow' => [
      'tasks' => [['task_id' => 'report-in', 'chapter_id' => 'c', 'scene_id' => 's'], ['task_id' => 'debrief', 'chapter_id' => 'c', 'scene_id' => 's']],
      'objective_completion_criteria' => [$row('tell', 'report-in'), $row('listen', 'debrief'), $row('answer', 'debrief')],
    ]];
    $receipts = $publish->invoke($service, $payload, 'the-clans-blight');

    $this->assertSame(['report-in', 'debrief'], array_column($receipts, 'template_id'));
    $this->assertSame(['tell'], array_column($written[0]['objectives_schema'][0]['objectives'], 'objective_id'));
    $this->assertSame(['listen', 'answer'], array_column($written[1]['objectives_schema'][0]['objectives'], 'objective_id'));
    $this->assertSame('the-clans-blight', $written[1]['storyline_template_id']);

    // A task with no objective fails the publication; nothing is generated.
    $payload['process_flow']['tasks'][] = ['task_id' => 'no-objective'];
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('storyline_quest_record_objective_required:no-objective');
    $publish->invoke($service, $payload, 'the-clans-blight');
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
