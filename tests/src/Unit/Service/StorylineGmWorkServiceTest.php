<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\MemoryStorage;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\dungeoncrawler_content\Exception\StorylineDraftValidationException;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineGmWorkService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests bounded authoring checkpoints with original project-owned content.
 *
 * @group dungeoncrawler_content
 */
class StorylineGmWorkServiceTest extends UnitTestCase {

  /**
   * Sequential units see saved IDs; replay never generates or appends twice.
   */
  public function testSequentialWorkAndReplayUseFreshSavedState(): void {
    [$service, $state, $parser, $store] = $this->fixture();
    $parser->expects($this->exactly(2))->method('parse')->willReturnCallback(function ($instruction, $snapshot) use ($state) {
      $this->assertSame($state->draft['revision'], $snapshot['draft']['revision']);
      if ($snapshot['bounded_work_step']['current'] === 2) {
        $this->assertSame('scene-created-by-native-authority', $snapshot['storyline']['scene_id']);
      }
      return $this->intent($state->draft['revision']);
    });
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    $this->assertSame([], $store->getAll(), 'Planning is read-only.');
    $first = $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $this->assertSame(1, $first['work_progress']['step']);
    $replay = $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $this->assertTrue($replay['idempotent_replay']);
    $this->assertSame(1, $state->applied);
    $this->assertSame(1, $service->activeWork('draft-original')['step']);
    $final = $service->advance('draft-original', $args['job_id'], 4, 1);
    $this->assertSame('completed', $final['work_progress']['status']);
    $this->assertSame(5, $final['saved']['revision']);
    $this->assertNull($service->activeWork('draft-original'));
    $this->assertNotSame($state->operation_ids[0], $state->operation_ids[1]);
    $this->assertLessThanOrEqual(64, strlen($state->operation_ids[0]));
    $this->assertTrue($service->advance('draft-original', $args['job_id'], 4, 1)['idempotent_replay']);
  }

  /**
   * Work the draft has moved past is closed, reported, and stops blocking.
   */
  public function testSavedWorkIsClosedOnceTheDraftChangesElsewhere(): void {
    [$service, $state, $parser] = $this->fixture();
    $parser->method('parse')->willReturnCallback(fn () => $this->intent(3));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $this->assertNull($service->closeSupersededWork('draft-original'));

    // The author saves the draft some other way: the remaining unit is stale.
    $state->draft['revision'] = 9;
    $notice = $service->closeSupersededWork('draft-original');
    $this->assertSame(4, $notice['job_revision']);
    $this->assertSame(9, $notice['draft_revision']);
    $this->assertCount(1, $notice['completed']);
    $this->assertCount(1, $notice['not_done']);
    $this->assertNull($service->activeWork('draft-original'));
    // The notice stands until the draft changes again.
    $this->assertSame($notice, $service->closeSupersededWork('draft-original'));
    $state->draft['revision'] = 10;
    $this->assertNull($service->closeSupersededWork('draft-original'));
    // New work can be planned against the newer draft.
    $this->assertNotSame($args['job_id'], $service->plan('draft-original', 10, $this->steps())['proposed_execution']['arguments']['job_id']);
  }

  /**
   * Truncated later output preserves earlier saved content and its cursor.
   */
  public function testTruncatedLaterBatchPreservesCompletedWork(): void {
    [$service, $state, $parser] = $this->fixture();
    $parser->expects($this->exactly(2))->method('parse')->willReturnCallback(function () use ($state) {
      if ($state->applied === 1) {
        throw new \RuntimeException('editor_gm_intent_output_truncated');
      }
      return $this->intent(3);
    });
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    try {
      $service->advance('draft-original', $args['job_id'], 4, 1);
      $this->fail('Truncated JSON must not execute.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('editor_gm_intent_output_truncated', $exception->getMessage());
    }
    $this->assertSame(1, $state->applied);
    $this->assertSame(4, $state->draft['revision']);
    $this->assertSame(1, $service->activeWork('draft-original')['step']);
  }

  /**
   * A native save failure reuses its exact pending operations on manual retry.
   */
  public function testPendingOperationsAreNotRegeneratedAfterSaveFailure(): void {
    [$service, $state, $parser] = $this->fixture();
    $state->fail_save = TRUE;
    $parser->expects($this->once())->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('The failed save must remain visible.');
    }
    catch (\DomainException $exception) {
      $this->assertSame('fixture_native_save_failed', $exception->getMessage());
    }
    $state->fail_save = FALSE;
    $result = $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $this->assertSame(1, $result['work_progress']['step']);
    $this->assertSame($state->operation_ids[0], $state->operation_ids[1]);
  }

  /**
   * Invalid model patches never poison the durable execution checkpoint.
   */
  public function testRejectedPatchIsGroundedForExplicitResume(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $attempt = 0;
    $drafts->method('planDraftOperations')->willReturnCallback(static function () use (&$attempt) {
      if (++$attempt === 1) {
        throw new StorylineDraftValidationException('storyline_draft_schema_invalid', [
          ['path' => '/ending', 'message' => 'Expected a string.'],
        ]);
      }
      return [];
    });
    $parser->expects($this->exactly(2))->method('parse')->willReturnCallback(function ($instruction, $snapshot) use ($state) {
      if ($snapshot['bounded_work_step']['previous_rejected_attempt'] !== NULL) {
        $this->assertSame('storyline_draft_schema_invalid', $snapshot['bounded_work_step']['previous_rejected_attempt']['error']);
      }
      return $this->intent($state->draft['revision']);
    });
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('Invalid native schema must stop this request.');
    }
    catch (StorylineDraftValidationException $exception) {
      $this->assertSame('storyline_draft_schema_invalid', $exception->getMessage());
    }
    $this->assertSame(0, $state->applied);
    $this->assertNull($store->get($args['job_id'])['pending_operations']);
    $paused = $service->activeWork('draft-original');
    $this->assertSame('paused', $paused['status']);
    $this->assertSame(['resume', 'skip', 'cancel'], $paused['allowed_actions']);
    $this->assertSame(1, $paused['failed_attempts']);
    $this->assertSame('storyline_draft_schema_invalid', $paused['failure']['error']);
    $result = $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $this->assertSame(1, $result['work_progress']['step']);
    $this->assertSame(0, $result['work_progress']['failed_attempts']);
  }

  /**
   * Repeated unit-level failures eventually require an explicit stop and replan.
   */
  public function testExhaustsRetriesForOneUnitAfterThreeFailures(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $drafts->method('planDraftOperations')->willThrowException(new StorylineDraftValidationException('storyline_draft_schema_invalid', [
      ['path' => '/process_flow', 'message' => 'Still invalid.'],
    ]));
    $parser->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];

    for ($i = 0; $i < 3; $i++) {
      try {
        $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
        $this->fail('Expected validation rejection before save.');
      }
      catch (StorylineDraftValidationException $exception) {
        $this->assertSame('storyline_draft_schema_invalid', $exception->getMessage());
      }
    }
    $paused = $service->activeWork('draft-original');
    $this->assertSame('paused', $paused['status']);
    $this->assertSame(3, $paused['failed_attempts']);
    $this->assertSame(['skip', 'cancel'], $paused['allowed_actions']);
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('Retries should be exhausted for this unit.');
    }
    catch (\DomainException $exception) {
      $this->assertSame('storyline_work_unit_attempts_exhausted:0', $exception->getMessage());
    }
    $this->assertSame(0, $state->applied);
  }

  /**
   * Missing operation paths return actionable paused findings for explicit resume.
   */
  public function testMissingOperationPathRecordsActionableFailureFinding(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $drafts->method('planDraftOperations')->willThrowException(new \InvalidArgumentException('storyline_draft_operation_path_not_found'));
    $parser->method('parse')->willReturn($this->intentWithPath(3, '/storyline/player_hook'));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('A missing operation path must fail this unit.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame('storyline_draft_operation_path_not_found', $exception->getMessage());
    }
    $paused = $service->activeWork('draft-original');
    $this->assertSame('paused', $paused['status']);
    $this->assertSame(['resume', 'skip', 'cancel'], $paused['allowed_actions']);
    $this->assertSame('operation_path_not_found', $paused['failure']['findings'][0]['code'] ?? '');
    $this->assertStringContainsString('/storyline/player_hook', $paused['failure']['findings'][0]['message'] ?? '');
    $this->assertNull($store->get($args['job_id'])['pending_operations']);
    $this->assertSame(0, $state->applied);
  }

  /**
   * Duplicate append keys return actionable findings for explicit resume.
   */
  public function testDuplicateAppendKeysRecordActionableFailureFinding(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $drafts->method('planDraftOperations')->willThrowException(new \InvalidArgumentException('storyline_draft_batch_key_invalid_or_duplicate:1'));
    $parser->method('parse')->willReturn([
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => [
        'expected_revision' => 3,
        'operations' => [
          ['type' => 'append', 'path' => '/process_flow/branch_choices', 'key' => 'dup', 'value' => ['label' => 'One']],
          ['type' => 'append', 'path' => '/process_flow/branch_choices', 'key' => 'dup', 'value' => ['label' => 'Two']],
        ],
      ],
    ]);
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('Duplicate append keys must fail this unit.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame('storyline_draft_batch_key_invalid_or_duplicate:1', $exception->getMessage());
    }
    $paused = $service->activeWork('draft-original');
    $this->assertSame('paused', $paused['status']);
    $this->assertSame(['resume', 'skip', 'cancel'], $paused['allowed_actions']);
    $this->assertSame('operation_batch_key_invalid_or_duplicate', $paused['failure']['findings'][0]['code'] ?? '');
    $this->assertStringContainsString('dup', $paused['failure']['findings'][0]['message'] ?? '');
    $this->assertNull($store->get($args['job_id'])['pending_operations']);
    $this->assertSame(0, $state->applied);
  }

  /**
   * Non-unique row matches return actionable findings for explicit resume.
   */
  public function testNonUniqueMatchRecordsActionableFailureFinding(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $drafts->method('planDraftOperations')->willThrowException(new \InvalidArgumentException('storyline_draft_operation_match_count_invalid:0:2'));
    $parser->method('parse')->willReturn([
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => [
        'expected_revision' => 3,
        'operations' => [[
          'type' => 'patch',
          'path' => '/process_flow/journal_entries',
          'match' => ['field' => 'scene_id', 'value' => 'scene-dup'],
          'value' => ['summary' => 'Updated'],
        ]],
      ],
    ]);
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('Non-unique operation match must fail this unit.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame('storyline_draft_operation_match_count_invalid:0:2', $exception->getMessage());
    }
    $paused = $service->activeWork('draft-original');
    $this->assertSame('paused', $paused['status']);
    $this->assertSame(['resume', 'skip', 'cancel'], $paused['allowed_actions']);
    $this->assertSame('operation_match_not_unique', $paused['failure']['findings'][0]['code'] ?? '');
    $this->assertStringContainsString('/process_flow/journal_entries', $paused['failure']['findings'][0]['message'] ?? '');
    $this->assertStringContainsString('matched 2 rows', $paused['failure']['findings'][0]['message'] ?? '');
    $this->assertNull($store->get($args['job_id'])['pending_operations']);
    $this->assertSame(0, $state->applied);
  }

  /**
   * A lost model-call lease cannot overwrite another worker's checkpoint.
   */
  public function testLostLockCannotPersistTheGeneratedPatch(): void {
    [$service, $state, $parser, $store] = $this->fixture(TRUE);
    $parser->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('A lost lease must stop checkpoint persistence.');
    }
    catch (\DomainException $exception) {
      $this->assertStringStartsWith('storyline_work_lock_lost:', $exception->getMessage());
    }
    $this->assertSame(0, $state->applied);
    $this->assertNull($store->get($args['job_id'])['pending_operations']);
  }

  /**
   * Foreign work, stale revisions and invalid cursors cannot mutate drafts.
   *
   * @dataProvider invalidContinuations
   */
  public function testRejectsUnsafeContinuation(string $failure): void {
    [$service, $state, $parser] = $this->fixture();
    $parser->expects($this->once())->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    if ($failure === 'concurrent_edit') {
      $state->draft['revision']++;
    }
    $this->expectException(\DomainException::class);
    $service->advance(
      $failure === 'wrong_draft' ? 'draft-other' : 'draft-original',
      $args['job_id'],
      $failure === 'stale_revision' ? 3 : 4,
      $failure === 'invalid_cursor' ? 8 : 1,
    );
  }

  /**
   * Original unsafe-continuation cases.
   */
  public static function invalidContinuations(): array {
    return array_map(static fn(string $case): array => [$case], [
      'wrong_draft', 'stale_revision', 'invalid_cursor', 'concurrent_edit',
    ]);
  }

  /**
   * A plan unit outside its contract fails with the unit and the reason.
   *
   * @dataProvider invalidPlanUnits
   */
  public function testPlanRejectsUnitsOutsideTheContractWithSpecifics(array $unit, string $message): void {
    [$service] = $this->fixture();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    $service->plan('draft-original', 3, [
      ['label' => 'Arrival fork', 'instruction' => 'Add one alternative to the arrival scene.'],
      $unit,
    ]);
  }

  /**
   * Each contract violation names its own cause.
   */
  public static function invalidPlanUnits(): array {
    return [
      'unknown field' => [
        ['label' => 'A', 'instruction' => 'B', 'notes' => 'x', 'priority' => 1],
        'storyline_work_step_unknown_field:1:notes,priority',
      ],
      'label too long' => [
        ['label' => str_repeat('L', 81), 'instruction' => 'B'],
        'storyline_work_step_label_too_long:1:81',
      ],
      'instruction too long' => [
        ['label' => 'A', 'instruction' => str_repeat('I', 501)],
        'storyline_work_step_instruction_too_long:1:501',
      ],
      'missing instruction' => [
        ['label' => 'A'],
        'storyline_work_step_instruction_required:1',
      ],
      'both scene targets' => [
        ['label' => 'A', 'instruction' => 'B', 'scene_id' => 'x', 'scene_ids' => ['x']],
        'storyline_work_step_scene_id_and_scene_ids:1',
      ],
    ];
  }

  /**
   * Oversized patches fail before the native mutation authority is called.
   */
  public function testRejectsOversizedBatch(): void {
    [$service, $state, $parser] = $this->fixture();
    $intent = $this->intent(3);
    $intent['arguments']['operations'] = array_fill(0, 11, $intent['arguments']['operations'][0]);
    $parser->method('parse')->willReturn($intent);
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('Oversized work must not execute.');
    }
    catch (\DomainException $exception) {
      $this->assertSame('storyline_work_step_patch_too_large:0', $exception->getMessage());
    }
    $this->assertSame(0, $state->applied);
  }

  /**
   * Ten compact operations fit without requiring a second work unit.
   */
  public function testAcceptsTenCompactOperations(): void {
    [$service, $state, $parser] = $this->fixture();
    $intent = $this->intent(3);
    $intent['arguments']['operations'] = array_fill(0, 10, $intent['arguments']['operations'][0]);
    $parser->method('parse')->willReturn($intent);
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    $result = $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $this->assertSame(1, $result['work_progress']['step']);
    $this->assertSame(1, $state->applied);
  }

  /**
   * Multi-scene requests expand natively before generating operation payloads.
   */
  public function testExpandsSceneTargetsIntoStableWorkUnits(): void {
    [$service, $state] = $this->fixture();
    $state->draft['payload']['process_flow']['journal_entries'] = [
      ['scene_id' => 'village', 'title' => 'Village'],
      ['scene_id' => 'ridge', 'title' => 'Ridge'],
    ];
    $plan = $service->plan('draft-original', 3, [[
      'label' => 'Loss fork',
      'instruction' => 'Add one loss fork at this scene.',
      'scene_ids' => ['village', 'ridge'],
    ],
    ]);
    $steps = $plan['proposed_execution']['arguments']['steps'];
    $this->assertSame(['village', 'ridge'], array_column($steps, 'scene_id'));
    $this->assertSame(2, $plan['step_count']);
    $repeated = $service->plan('draft-original', 3, $steps);
    $this->assertSame($plan['proposed_execution']['arguments']['job_id'], $repeated['proposed_execution']['arguments']['job_id']);
  }

  /**
   * A model cannot broaden a scoped unit into edits elsewhere in the story.
   */
  public function testSceneScopedWorkRejectsGlobalChangesBeforeSaving(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $state->draft['payload']['process_flow']['journal_entries'] = [
      ['scene_id' => 'village', 'title' => 'Village'],
    ];
    $after = $state->draft['payload'];
    $after['title'] = 'Unrelated global rename';
    $drafts->method('planDraftOperations')->willReturn(['payload' => $after]);
    $parser->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, [[
      'label' => 'Village fork',
      'instruction' => 'Add one loss fork.',
      'scene_id' => 'village',
    ],
    ])['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('A scene-scoped unit must not rename the whole story.');
    }
    catch (\DomainException $exception) {
      $this->assertSame('storyline_work_patch_outside_scene_scope:village', $exception->getMessage());
    }
    $this->assertSame(0, $state->applied);
    $this->assertNull($store->get($args['job_id'])['pending_operations']);
  }

  /**
   * A scoped unit must mutate its scene payload, not return an empty no-op.
   */
  public function testSceneScopedWorkRejectsNoopPatchBeforeSaving(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $state->draft['payload']['process_flow'] = [
      'journal_entries' => [['scene_id' => 'village', 'title' => 'Village']],
      'tasks' => [['task_id' => 'quest-1', 'scene_id' => 'village']],
      'branch_choices' => [['choice_id' => 'choice-1', 'source_task_id' => 'quest-1']],
    ];
    $drafts->method('planDraftOperations')->willReturn(['payload' => $state->draft['payload']]);
    $parser->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, [[
      'label' => 'Village fork',
      'instruction' => 'Add one branch choice.',
      'scene_id' => 'village',
    ]])['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('A scoped unit that changes nothing should be rejected.');
    }
    catch (\DomainException $exception) {
      $this->assertSame('storyline_work_patch_scene_noop:village', $exception->getMessage());
    }
    $this->assertSame(0, $state->applied);
    $this->assertNull($store->get($args['job_id'])['pending_operations']);
  }

  /**
   * Explicit cancellation releases stale work without reverting authored data.
   */
  public function testCancellationRetainsSavedContentAndAllowsNewWork(): void {
    [$service, $state, $parser] = $this->fixture();
    $parser->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $state->draft['revision']++;
    $result = $service->cancel('draft-original', $args['job_id'], 4, 1);
    $this->assertSame('cancelled', $result['work_progress']['status']);
    $this->assertSame(5, $result['saved']['revision']);
    $this->assertSame(1, $state->applied);
    $this->assertNull($service->activeWork('draft-original'));
    $this->assertNotSame($args['job_id'], $service->plan('draft-original', 5, $this->steps())['proposed_execution']['arguments']['job_id']);
  }

  /**
   * Each saved batch reports cleared, introduced and remaining validation errors.
   */
  public function testAdvanceTracksValidationOutcome(): void {
    [$service, $state, $parser] = $this->fixture();
    $parser->method('parse')->willReturnCallback(function () use ($state) {
      return $this->intent($state->draft['revision']);
    });
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];

    $first = $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
    $outcome = $first['work_progress']['last_outcome'];
    $this->assertSame(1, $outcome['cleared']);
    $this->assertSame(1, $outcome['introduced_count']);
    $this->assertSame(2, $outcome['remaining']);
    $this->assertSame(1, $first['work_progress']['total_cleared_errors']);
    $this->assertSame(1, $first['work_progress']['total_introduced_errors']);

    $second = $service->advance('draft-original', $args['job_id'], 4, 1);
    $this->assertSame('completed', $second['work_progress']['status']);
    $this->assertSame(2, $second['work_progress']['total_cleared_errors']);
  }

  /**
   * A paused unit can be skipped explicitly without losing prior saved work.
   */
  public function testSkipPausedUnitAdvancesCursor(): void {
    [$service, $state, $parser, $store, $drafts] = $this->fixture();
    $drafts->method('planDraftOperations')->willThrowException(new StorylineDraftValidationException('storyline_draft_schema_invalid', [
      ['path' => '/process_flow', 'message' => 'Still invalid.'],
    ]));
    $parser->method('parse')->willReturn($this->intent(3));
    $args = $service->plan('draft-original', 3, $this->steps())['proposed_execution']['arguments'];
    try {
      $service->advance('draft-original', ...array_values($this->advanceArgs($args)));
      $this->fail('Expected paused unit before skip.');
    }
    catch (StorylineDraftValidationException) {
    }
    $paused = $service->activeWork('draft-original');
    $this->assertSame(['resume', 'skip', 'cancel'], $paused['allowed_actions']);
    $skipped = $service->skip('draft-original', $args['job_id'], 3, 0);
    $this->assertSame(1, $skipped['work_progress']['step']);
    $this->assertSame(['Arrival fork'], $skipped['work_progress']['skipped_labels']);
    $this->assertNull($store->get($args['job_id'])['last_failure'] ?? NULL);
  }

  /**
   * Supplies mocked native storage and a real isolated checkpoint collection.
   */
  private function fixture(bool $lose_lock = FALSE): array {
    $state = (object) [
      'draft' => [
        'draft_id' => 'draft-original',
        'revision' => 3,
        'status' => 'editing',
        'payload' => ['title' => 'Lantern Ridge'],
      ],
      'applied' => 0,
      'operation_ids' => [],
      'fail_save' => FALSE,
      'validation_errors' => [
        ['validation_error_one', 'validation_error_two'],
        ['validation_error_two', 'validation_error_three'],
        ['validation_error_three'],
      ],
    ];
    $drafts = $this->createMock(StorylineAuthoringDraftService::class);
    $drafts->method('getDraft')->willReturnCallback(static fn() => $state->draft);
    $drafts->method('validateDraft')->willReturnCallback(static function () use ($state) {
      $index = min($state->applied, count($state->validation_errors) - 1);
      return ['errors' => $state->validation_errors[$index]];
    });
    $drafts->method('applyDraftOperations')->willReturnCallback(static function ($id, $revision, $operation_id) use ($state) {
      $state->operation_ids[] = $operation_id;
      if ($state->fail_save) {
        throw new \DomainException('fixture_native_save_failed');
      }
      $state->applied++;
      $state->draft['revision']++;
      $state->draft['payload']['scene_id'] = 'scene-created-by-native-authority';
      return ['saved' => $state->draft, 'changed' => TRUE];
    });
    $store = new MemoryStorage('storyline-work-test');
    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);
    $lock = $this->createMock(LockBackendInterface::class);
    if ($lose_lock) {
      $lock->method('acquire')->willReturnOnConsecutiveCalls(TRUE, FALSE);
    }
    else {
      $lock->method('acquire')->willReturn(TRUE);
    }
    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturnCallback(static fn() => new class() extends Transaction {

      public function __construct() {}

      /**
       * {@inheritdoc}
       */
      public function __destruct() {}

      /**
       * {@inheritdoc}
       */
      public function rollBack() {}

      /**
       * {@inheritdoc}
       */
      public function commitOrRelease(): void {}

    });
    $parser = $this->createMock(EditorGmIntentParser::class);
    return [new StorylineGmWorkService($database, $factory, $lock, $drafts, $parser), $state, $parser, $store, $drafts];
  }

  /**
   * Supplies one compact native operation.
   */
  private function intent(int $revision): array {
    return $this->intentWithPath($revision, '/title');
  }

  /**
   * Supplies one compact native operation with an explicit target path.
   */
  private function intentWithPath(int $revision, string $path): array {
    return [
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => [
        'expected_revision' => $revision,
        'operations' => [['type' => 'set', 'path' => $path, 'value' => 'Lantern Ridge']],
      ],
    ];
  }

  /**
   * Original work units contain instructions rather than serialized patches.
   */
  private function steps(): array {
    return [
      ['label' => 'Arrival fork', 'instruction' => 'Add one alternative to the arrival scene.'],
      ['label' => 'Epilogue link', 'instruction' => 'Link the report to the epilogue using the saved scene IDs.'],
    ];
  }

  /**
   * Aligns plan arguments with the native advance method signature.
   */
  private function advanceArgs(array $arguments): array {
    return [$arguments['job_id'], $arguments['expected_revision'], $arguments['step'], $arguments['steps']];
  }

}
