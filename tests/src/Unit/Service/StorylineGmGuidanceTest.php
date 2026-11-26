<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineGmWorkService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PublishStorylineQuestTemplatesTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\StorylineWorkTool;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests compact initial routing and single-action author guidance.
 *
 * @group dungeoncrawler_content
 */
class StorylineGmGuidanceTest extends UnitTestCase {

  /**
   * Broad edits expose the compact planner before any full patch is generated.
   */
  public function testBroadRequestExposesOnlyWorkPlanner(): void {
    $intent = [
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_work',
      'arguments' => [
        'expected_revision' => 3,
        'steps' => [
        ['label' => 'Arrival fork', 'instruction' => 'Add one arrival alternative.'],
        ['label' => 'Shared destination', 'instruction' => 'Link scene outcomes to a shared destination.'],
        ],
      ],
      'reasoning' => 'Plan the forks as short units.',
    ];
    $parser = $this->parser($intent, function (string $prompt) {
      $tools = explode('## Supported command types', explode('## Registered toolset', $prompt)[1])[0];
      $this->assertStringContainsString('plan_storyline_work', $tools);
      $this->assertStringNotContainsString('plan_storyline_draft_operations', $tools);
    });
    $registry = new EditorGmToolRegistry([
      new PlanStorylineDraftOperationsTool(),
      new StorylineWorkTool($this->createMock(StorylineGmWorkService::class), FALSE),
    ], [], NULL);
    $result = $parser->parse('Add a fork at every scene and route each to one shared destination ending.', ['draft' => ['revision' => 3]], $registry, 'Storyline Builder');
    $this->assertSame('plan_storyline_work', $result['tool_name']);
  }

  /**
   * @dataProvider closingBeatPlans
   */
  public function testClosingBeatWorkPlanEnforcesTheNativeInstructionLimit(bool $oversized): void {
    $steps = [
      ['label' => 'Earn the signature', 'instruction' => 'Write the closing beat when the party meets the unwritten standard.'],
      ['label' => 'Publish the standard', 'instruction' => 'Write the closing beat when the party persuades Celia to state the standard.'],
      ['label' => 'Carry the disagreement forward', 'instruction' => 'Write the closing beat that earns the signature but leaves a deliberate open thread.'],
    ];
    if ($oversized) {
      $steps[1]['instruction'] = str_repeat('x', 571);
    }
    $parser = $this->parser([
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_work',
      'arguments' => ['expected_revision' => 35, 'steps' => $steps],
      'reasoning' => 'Keep each selected outcome in its own closing beat.',
    ], function (string $prompt, array $options): void {
      $tools = array_column(array_column($options['tools'], 'function'), NULL, 'name');
      $items = $tools['plan_storyline_work']['parameters']['properties']['steps']['items'];
      $this->assertSame(StorylineGmWorkService::MAX_INSTRUCTION_LENGTH, $items['properties']['instruction']['maxLength']);
      $this->assertStringContainsString('selects all of them', $options['system_prompt']);
    });
    if ($oversized) {
      $this->expectException(\DomainException::class);
      $this->expectExceptionMessage('editor_gm_intent_schema_invalid:plan_storyline_work:/steps/1/instruction');
    }
    $result = $parser->parse('Write all three as separate closing beats.', ['draft' => ['revision' => 35]], new EditorGmToolRegistry([
      new PlanStorylineDraftOperationsTool(),
      new StorylineWorkTool($this->createMock(StorylineGmWorkService::class), FALSE),
    ], [], NULL), 'Storyline Builder');
    $this->assertSame($steps, $result['arguments']['steps']);
  }

  public static function closingBeatPlans(): array {
    return ['three bounded beats' => [FALSE], '571-character work unit' => [TRUE]];
  }

  /**
   * @dataProvider denouementPaths
   */
  public function testBoundedDenouementPatchRequiresTheDraftPath(string $path, bool $valid): void {
    $operation = ['type' => 'set', 'path' => $path, 'value' => 'Each outcome has a separate close.'];
    $parser = $this->parser([
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => ['expected_revision' => 42, 'operations' => [$operation]],
      'reasoning' => 'Align the shared denouement.',
    ], function (string $prompt, array $options): void {
      $this->assertStringContainsString('/process_flow/narrative_architecture/beats/denouement/', $options['system_prompt']);
    });
    if (!$valid) {
      $this->expectException(\DomainException::class);
      $this->expectExceptionMessage('editor_gm_intent_schema_invalid:plan_storyline_draft_operations:/operations/0/path');
    }
    $intent = $parser->parse('Align the denouement beat.', [
      'draft' => ['revision' => 42],
      'bounded_work_step' => ['current' => 8, 'total' => 8],
    ], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
    $this->assertSame([$operation], $intent['arguments']['operations']);
  }

  public static function denouementPaths(): array {
    return [
      'saved draft path' => ['/process_flow/narrative_architecture/beats/denouement/new_equilibrium', TRUE],
      'rejected root from the paused job' => ['/narrative_architecture/beats/denouement/new_equilibrium', FALSE],
    ];
  }

  /**
   * Inner work cannot be hijacked by outer deterministic review routing.
   */
  public function testInnerUnitKeepsItsBoundedModeAndGrounding(): void {
    // The reply shape the model produced on 2026-11-17: the tool's own
    // arguments with the reasoning beside them, and no wrapper object.
    $intent = [
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => [
        'expected_revision' => 4,
        'operations' => [
          ['type' => 'set', 'path' => '/ending', 'value' => 'The lantern returns home.'],
        ],
      ],
      'reasoning' => 'Set the ending.',
    ];
    $parser = $this->parser($intent, function (string $prompt, array $options) {
      $this->assertStringContainsString('"bounded_work_step"', $prompt);
      $this->assertStringContainsString('ONE CHECKPOINTED STORYLINE WORK UNIT', $options['system_prompt']);
      // A work unit may only produce one reply shape, for one tool.
      $this->assertSame(['plan_storyline_draft_operations'], array_column(array_column($options['tools'], 'function'), 'name'));
      $this->assertSame(['type' => 'function', 'function' => ['name' => 'plan_storyline_draft_operations']], $options['tool_choice']);
      $parameters = $options['tools'][0]['function']['parameters'];
      $this->assertSame(['reasoning', 'expected_revision', 'operations'], array_keys($parameters['properties']));
      $this->assertSame(['reasoning', 'expected_revision', 'operations'], $parameters['required']);
      $this->assertFalse($parameters['additionalProperties']);
    });
    $result = $parser->parse('review', [
      'draft' => ['revision' => 4],
      'bounded_work_step' => ['current' => 2, 'total' => 3],
    ], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
    $this->assertSame('plan_storyline_draft_operations', $result['tool_name']);
    $this->assertSame(['expected_revision', 'operations'], array_keys($result['arguments']));
    $this->assertSame('Set the ending.', $result['reasoning']);
  }

  /**
   * Author-facing checkpoints resume without another model interpretation.
   */
  public function testResumeUsesGroundedCursor(): void {
    $parser = (new \ReflectionClass(EditorGmIntentParser::class))->newInstanceWithoutConstructor();
    $result = $parser->parse('Resume saved work', [
      'authoring_workflow' => [
        'bounded_work' => [
          'active_work' => [
            'job_id' => 'verified-job',
            'expected_revision' => 9,
            'step' => 2,
          ],
        ],
      ],
    ], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
    $this->assertSame('apply_storyline_work', $result['tool_name']);
    $this->assertSame(2, $result['arguments']['step']);
    $this->assertSame(9, $result['arguments']['expected_revision']);
    $this->assertSame('advance', $result['arguments']['action']);
    $skip = $parser->parse('Skip this step of the saved work', [
      'authoring_workflow' => ['bounded_work' => ['active_work' => ['job_id' => 'verified-job', 'expected_revision' => 9, 'step' => 2]]],
    ], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
    $this->assertSame('skip', $skip['arguments']['action']);
    $this->assertSame(2, $skip['arguments']['step']);
  }

  /**
   * An intent carries no next step: the next step follows the work.
   */
  public function testIntentContractHasNoNextStep(): void {
    $parser = $this->parser([
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => ['expected_revision' => 4, 'operations' => [['type' => 'set', 'path' => '/ending', 'value' => 'Resolved.']]],
      'reasoning' => 'Set the ending.',
      'next_steps' => ['Write scenes.'],
    ], function (string $prompt, array $options): void {
      foreach (array_column($options['tools'], 'function') as $function) {
        $this->assertArrayNotHasKey('next_steps', $function['parameters']['properties'], $function['name']);
        $this->assertContains('reasoning', $function['parameters']['required'], $function['name']);
      }
    });
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('editor_gm_intent_schema_invalid:plan_storyline_draft_operations:/next_steps:');
    $parser->parse('Set ending', ['draft' => ['revision' => 4]], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
  }

  /**
   * A clarification option is a name, a direction and a trade-off.
   */
  public function testClarificationOptionsCarryLabelDescriptionAndTradeoff(): void {
    $option = static fn(string $label): array => ['label' => $label, 'description' => 'Fold it into the road task.', 'tradeoff' => 'Fewer rows, more rewiring.'];
    $parser = $this->parser([
      'type' => 'clarification',
      'question' => 'Which task should be folded?',
      'options' => [$option('Ambush'), $option('Staging'), $option('Approach')],
      'reasoning' => 'Three tasks could be meant.',
    ], function (string $prompt, array $options): void {
      $functions = array_column(array_column($options['tools'], 'function'), NULL, 'name');
      $this->assertSame(['plan_storyline_draft_operations', 'submit_backlog_suggestion', 'task_queue', 'direct_response', 'clarification'], array_keys($functions));
      $this->assertSame('required', $options['tool_choice']);
      $this->assertTrue($functions['clarification']['strict']);
      $this->assertSame(
        ['label', 'description', 'tradeoff'],
        $functions['clarification']['parameters']['properties']['options']['items']['required'],
      );
      $this->assertStringNotContainsString('Response shapes', $options['system_prompt']);
    });
    $result = $parser->parse('Fold that task into the larger one', ['draft' => ['revision' => 4]], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
    $this->assertSame('clarification', $result['type']);
    $this->assertSame(['label', 'description', 'tradeoff'], array_keys($result['options'][0]));
  }

  /**
   * An option with no trade-off is rejected, not repaired.
   */
  public function testClarificationOptionWithoutTradeoffIsRejected(): void {
    $parser = $this->parser([
      'type' => 'clarification',
      'question' => 'Which task should be folded?',
      'options' => [['label' => 'Ambush', 'description' => 'Fold it.']],
      'reasoning' => 'Three tasks could be meant.',
    ], static function (): void {});
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage("editor_gm_intent_schema_invalid:clarification:/options/0/tradeoff");
    $parser->parse('Fold that task into the larger one', ['draft' => ['revision' => 4]], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
  }

  /**
   * A call to a function that was not offered is rejected, not mapped.
   */
  public function testRejectsFunctionOutsideTheContract(): void {
    $parser = $this->parser([
      'type' => 'tool_object',
      'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => ['expected_revision' => 4, 'operations' => [['type' => 'set', 'path' => '/ending', 'value' => 'Resolved.']]],
      'reasoning' => 'Set the ending.',
    ], static function (): void {});
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('editor_gm_intent_function_not_offered:tool_object');
    $parser->parse('Set ending', ['draft' => ['revision' => 4]], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
  }

  /**
   * A reply is checked against the schema it was generated from.
   *
   * @dataProvider repliesOutsideTheirSchema
   */
  public function testRejectsReplyOutsideItsSchema(array $intent, string $message): void {
    $parser = $this->parser($intent, static function (): void {});
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage($message);
    $parser->parse('Set ending', ['draft' => ['revision' => 4]], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
  }

  /**
   * Each violation names the function, the place and the reason.
   */
  public static function repliesOutsideTheirSchema(): array {
    return [
      'focus outside the enum' => [
        ['type' => 'direct_response', 'focus' => 'direct_answer', 'reasoning' => 'x'],
        'editor_gm_intent_schema_invalid:direct_response:/focus:',
      ],
      'missing required field' => [
        ['type' => 'direct_response', 'focus' => 'status'],
        'editor_gm_intent_schema_invalid:direct_response:/reasoning:',
      ],
      'undeclared field' => [
        ['type' => 'direct_response', 'focus' => 'status', 'reasoning' => 'x', 'answer' => 'Here you go.'],
        'editor_gm_intent_schema_invalid:direct_response:/answer:',
      ],
      'tool not offered' => [
        ['type' => 'tool_call', 'tool_name' => 'publish_everything', 'arguments' => ['a' => 1], 'reasoning' => 'x'],
        'editor_gm_intent_function_not_offered:publish_everything',
      ],
      'tool argument of the wrong type' => [
        ['type' => 'tool_call', 'tool_name' => 'plan_storyline_draft_operations', 'arguments' => ['expected_revision' => '4', 'operations' => []], 'reasoning' => 'x'],
        'editor_gm_intent_schema_invalid:plan_storyline_draft_operations:/expected_revision:',
      ],
      'tool argument not declared' => [
        ['type' => 'tool_call', 'tool_name' => 'plan_storyline_draft_operations', 'arguments' => ['expected_revision' => 4, 'operations' => [], 'publish' => TRUE], 'reasoning' => 'x'],
        'editor_gm_intent_schema_invalid:plan_storyline_draft_operations:/publish:',
      ],
      'queued tool with an undeclared argument' => [
        ['type' => 'task_queue', 'reasoning' => 'x', 'tasks' => [[
          'tool_name' => 'plan_storyline_draft_operations', 'reasoning' => 'x',
          'arguments' => ['expected_revision' => 4, 'operations' => [], 'reasoning' => 'misplaced'],
        ]]],
        'editor_gm_intent_schema_invalid:task_queue:/tasks/0',
      ],
    ];
  }

  /**
   * A reply that is not a function call never becomes an intent.
   */
  public function testRejectsReplyThatIsNotAFunctionCall(): void {
    $ai = $this->createMock(AIApiService::class);
    $ai->method('invokeModelDirect')->willReturn(['success' => TRUE, 'response' => '{"type":"direct_response","focus":"status","reasoning":"x"}']);
    $logger = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('editor_gm_intent_function_call_missing');
    (new EditorGmIntentParser($ai, $logger))->parse('Set ending', ['draft' => ['revision' => 4]], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), 'Storyline Builder');
  }

  /**
   * The final envelope enforces the same guidance contract for native tools.
   */
  public function testRejectsMultipleNextStepsAtFinalBoundary(): void {
    $harness = (new \ReflectionClass(EditorGmHarnessService::class))->newInstanceWithoutConstructor();
    $surface = $this->createMock(EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $envelope = new \ReflectionMethod($harness, 'envelope');
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('storyline_gm_next_step_must_be_single');
    $result = ['next_steps' => ['Write scenes.', 'Build actors.']];
    $envelope->invoke($harness, $surface, 'editor_management_response', [], $result, []);
  }

  /**
   * Saved-state guidance supersedes stale pre-save suggestions.
   */
  public function testSavedStateOwnsTheSingleNextAction(): void {
    $management = new AgentHarnessManagementService();
    $result = $management->synthesizeDeterministicMutationResponse('apply_storyline_draft_operations', [
      'next_steps' => ['Create the scene just saved.', 'Create six actors.'],
    ], [
      'current_state' => ['missing_content' => ['An ending', 'A cast']],
    ], 'Storyline Builder');
    $this->assertSame(['Develop the next missing story element: An ending.'], $result['next_steps']);
    $running = $management->synthesizeDeterministicMutationResponse('apply_storyline_work', [
      'work_progress' => ['step' => 2, 'total_steps' => 5, 'status' => 'running'],
    ], [], 'Storyline Builder');
    $this->assertStringContainsString('2 of 5', $running['answer']);
    $this->assertSame(['Continue the saved authoring work.'], $running['next_steps']);
    $validation = $management->synthesizeDeterministicMutationResponse('apply_storyline_draft_operations', [], [
      'current_state' => ['error_count' => 2, 'warning_count' => 1],
      'validation_summary' => [
        'errors' => ['storyline_branch_target_scene_required'],
        'warnings' => ['storyline_optional_field_missing'],
        'stages' => [[
          'stage' => 'questline_progression',
          'route' => 'draft_operation',
          'errors' => ['storyline_branch_target_scene_required'],
          'warnings' => [],
        ]],
      ],
    ], 'Storyline Builder');
    $this->assertStringStartsWith('Validation finding: storyline_branch_target_scene_required', $validation['next_steps'][0]);
    $this->assertStringContainsString('Options: ask the GM to fix this finding now, skip to the next finding, or switch focus to warnings.', $validation['next_steps'][0]);
  }

  /**
   * Canonical-remediation findings are framed as publish actions, not draft edits.
   */
  public function testNextStepUsesRemediationWordingWhenOnlyRemediationErrorsExist(): void {
    $management = new AgentHarnessManagementService();
    $response = $management->synthesizeDeterministicMutationResponse('apply_storyline_draft_operations', [], [
      'current_state' => ['error_count' => 1, 'warning_count' => 0],
      'validation_summary' => [
        'errors' => ['objects.rooms.generation_specs[0]: declared dungeon missing canonical contract'],
        'warnings' => [],
        'stages' => [[
          'stage' => 'authored_room_dungeon_bindings',
          'route' => 'dungeon_editor',
          'errors' => ['objects.rooms.generation_specs[0]: declared dungeon missing canonical contract'],
          'warnings' => [],
        ]],
      ],
    ], 'Storyline Builder');
    $this->assertStringContainsString('This needs the site built in the Dungeon Editor, not a draft edit.', $response['next_steps'][0]);
  }

  public function testNextConflictWarningProposesAnAuthoredResolution(): void {
    $warning = "Conflict 'celia-test' remains unresolved at the end of the authored timeline.";
    $step = (new AgentHarnessManagementService())->storylineNextStepFromSavedState([
      'current_state' => ['accepted_warning_count' => 1, 'open_warning_count' => 1, 'first_open_warning' => $warning],
      'storyline' => ['process_flow' => ['conflicts' => [
        ['conflict_id' => 'celia-test', 'title' => "Celia's standards"],
      ]]],
      'validation_summary' => ['warnings' => ['Earlier accepted warning.', $warning], 'stages' => [[
        'warnings' => ['Earlier accepted warning.', $warning],
        'findings' => [[
          'severity' => 'warning',
          'code' => 'DCV_NARRATIVE_CONFLICT_UNRESOLVED',
          'message' => $warning,
          'entity_id' => 'celia-test',
        ]],
      ]]],
    ]);
    $this->assertCount(1, $step);
    $this->assertStringContainsString("Celia's standards", $step[0]);
    $this->assertStringContainsString('after the final escalation', $step[0]);
    $this->assertStringContainsString('intentional open thread', $step[0]);
    $this->assertStringNotContainsString('ask the GM', $step[0]);
  }

  public function testProposalOnlyRequestNeverEntersToolSelection(): void {
    $ai = $this->createMock(AIApiService::class);
    $ai->expects($this->never())->method('invokeModelDirect');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $parser = new EditorGmIntentParser($ai, $logger_factory);
    $intent = $parser->parse(
      "How do we resolve Celia's warning? Do we need completion criteria? Don't fix it, but propose answers.",
      ['validation_summary' => ['warning_count' => 1]],
      new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL),
      'Storyline Builder',
    );
    $this->assertSame('direct_response', $intent['type']);
    $this->assertSame('validation_review', $intent['focus']);
  }

  public function testAcceptedQuestCreationOfferUsesOnlyMissingSavedTasks(): void {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $parser = new EditorGmIntentParser(NULL, $logger_factory);
    $registry = new EditorGmToolRegistry([new PublishStorylineQuestTemplatesTool()], [], NULL);
    $snapshot = [
      'draft' => ['revision' => 45],
      'storyline_offered_next_step' => 'Create the two canonical quest templates for the closing tasks.',
      'storyline' => ['process_flow' => ['tasks' => [
        ['task_id' => 'already-saved'],
        ['task_id' => 'earn-signature'],
        ['task_id' => 'write-standard'],
      ]]],
      'validation_summary' => ['warnings' => [
        "Linked quest 'earn-signature' is missing from canonical quest templates.",
        "Linked quest 'write-standard' is missing from canonical quest templates.",
        "Linked quest 'not-a-task' is missing from canonical quest templates.",
      ]],
    ];

    foreach (['Create two quest templates', 'Create the two canonical quest templates one at a time'] as $request) {
      $intent = $parser->parse($request, $snapshot, $registry, 'Storyline Builder');
      $this->assertSame('publish_storyline_quest_templates', $intent['tool_name']);
      $this->assertSame([
        'expected_revision' => 45,
        'task_ids' => ['earn-signature', 'write-standard'],
      ], $intent['arguments']);
    }

    $intent = $parser->parse("Propose quest templates, but don't save them.", $snapshot, $registry, 'Storyline Builder');
    $this->assertSame('direct_response', $intent['type']);

    $snapshot['validation_summary']['warnings'] = ["Linked quest 'write-standard' is missing from canonical quest templates."];
    $this->expectExceptionMessage('editor_gm_intent_parser_unavailable');
    $parser->parse('Create them', $snapshot, $registry, 'Storyline Builder');
  }

  /**
   * An actor without a character sheet is fixed by the sheet build.
   */
  public function testActorWithoutSheetNamesTheSheetBuild(): void {
    $payload = ['objects' => ['actors' => ['generation_specs' => [
      ['actor_id' => 'scout', 'display_name' => 'Pikk Nettlefoot', 'role' => 'scout'],
    ]]]];
    $findings = \Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::actorSheetFindings($payload);
    $this->assertSame(['actor_sheet_missing'], array_column($findings, 'code'));

    $step = (new AgentHarnessManagementService())->storylineNextStepFromSavedState([
      'current_state' => ['error_count' => 1, 'warning_count' => 0],
      'validation_summary' => ['error_count' => 1, 'warning_count' => 0, 'stages' => [[
        'stage' => 'actor_character_sheets',
        'route' => 'actor_sheet_build',
        'errors' => array_column($findings, 'message'),
        'findings' => array_map(static fn(array $finding): array => $finding + ['route' => 'actor_sheet_build'], $findings),
      ]]],
    ])[0];
    $this->assertStringContainsString("Actor 'Pikk Nettlefoot' has no character sheet.", $step);
    $this->assertStringContainsString('ask the GM to build the character sheets for the roster', $step);
  }

  /**
   * A duplicate of a library actor is resolved before sheets are built.
   */
  public function testLibraryActorDuplicateComesBeforeTheSheetBuild(): void {
    $step = (new AgentHarnessManagementService())->storylineNextStepFromSavedState([
      'current_state' => ['missing_content' => ['Every actor has a current character sheet'], 'error_count' => 2],
      'validation_summary' => ['error_count' => 2, 'warning_count' => 0, 'stages' => [[
        'stage' => 'actor_character_sheets',
        'route' => 'actor_sheet_build',
        'findings' => [
          ['severity' => 'error', 'code' => 'actor_duplicates_library_actor', 'route' => 'draft_operation', 'message' => "Actor 'Celia' already exists in the library as 'tal-mission-handler'."],
          ['severity' => 'error', 'code' => 'actor_sheet_missing', 'route' => 'actor_sheet_build', 'message' => "Actor 'Celia' has no character sheet."],
        ],
      ]]],
    ])[0];
    $this->assertStringContainsString("already exists in the library as 'tal-mission-handler'", $step);
    $this->assertStringContainsString('use the existing library actor', $step);
  }

  /**
   * A sheet is current only while the authored actor is unchanged.
   */
  public function testEditedActorMakesItsSheetOutdated(): void {
    $service = \Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class;
    $spec = ['actor_id' => 'scout', 'display_name' => 'Pikk Nettlefoot', 'role' => 'scout', 'character_id' => 7, 'character_sheet_revision' => 2];
    $spec['character_sheet_spec_hash'] = $service::actorSpecHash($spec);
    $payload = static fn(array $actor): array => ['objects' => ['actors' => ['generation_specs' => [$actor]]]];

    $this->assertSame([], $service::actorSheetFindings($payload($spec)));
    $this->assertSame(
      ['actor_sheet_outdated'],
      array_column($service::actorSheetFindings($payload(['role' => 'shaman'] + $spec)), 'code'),
    );
  }

  /**
   * A task without an objective is a draft gap, stated in the author's terms.
   */
  public function testTaskWithoutObjectiveIsADraftFinding(): void {
    $management = new AgentHarnessManagementService();
    $step = $management->storylineNextStepFromSavedState([
      'current_state' => ['error_count' => 1, 'warning_count' => 0],
      'storyline' => ['process_flow' => ['tasks' => [
        ['task_id' => 'draft-12345678-1234-1234-1234-1234567890ab-task-cross-the-ford', 'goal_text' => 'Cross the flooded ford.'],
      ]]],
      'validation_summary' => ['error_count' => 1, 'stages' => [[
        'stage' => 'objective_control_chain',
        'route' => 'draft_operation',
        'errors' => ['raw one'],
        'findings' => [[
          'severity' => 'error', 'code' => 'quest_objective_missing', 'route' => 'draft_operation',
          'quest_id' => 'draft-12345678-1234-1234-1234-1234567890ab-task-cross-the-ford', 'message' => 'raw one',
        ]],
      ]]],
    ])[0];
    $this->assertSame('Validation finding: The task "Cross the flooded ford" has no objective, so the game cannot tell when it is done. Options: ask the GM to fix this finding now, skip to the next finding, or switch focus to warnings.', $step);
    AgentHarnessManagementService::assertStorylineNextSteps([$step]);
    StorylineAuthoringDraftService::assertNoIdAssignmentRequest($step);
  }

  /**
   * A finding's own route overrides its stage's, and draft fixes come first.
   */
  public function testFindingRouteOverridesStageRoute(): void {
    $management = new AgentHarnessManagementService();
    $publish = ['severity' => 'error', 'code' => 'needs_publication', 'route' => 'dungeon_editor', 'message' => 'A site is not published.'];
    $edit = ['severity' => 'error', 'code' => 'objective_unlinked', 'route' => 'draft_operation', 'objective_id' => 'objective-light-the-beacon', 'message' => 'raw two'];
    $snapshot = [
      'current_state' => ['error_count' => 2, 'warning_count' => 0],
      'validation_summary' => ['error_count' => 2, 'stages' => [[
        'stage' => 'objective_control_chain',
        'route' => 'draft_operation',
        'errors' => ['A site is not published.', 'raw two'],
        'findings' => [$publish, $edit],
      ]]],
    ];
    $this->assertSame(
      'Validation finding: The objective "light the beacon" is not attached to any task. Options: ask the GM to fix this finding now, skip to the next finding, or switch focus to warnings.',
      $management->storylineNextStepFromSavedState($snapshot)[0],
    );

    $snapshot['validation_summary']['stages'][0]['errors'] = ['A site is not published.'];
    $snapshot['validation_summary']['stages'][0]['findings'] = [$publish];
    $this->assertSame(
      'Validation finding: A site is not published. This needs the site built in the Dungeon Editor, not a draft edit. Options: ask the GM to open that site in the Dungeon Editor, where its GM builds it from the story brief, or continue story changes.',
      $management->storylineNextStepFromSavedState($snapshot)[0],
    );
  }

  /**
   * The cause of unreachable tasks is named before the tasks it strands.
   */
  public function testDroppedContinuationIsNamedBeforeItsSymptoms(): void {
    $management = new AgentHarnessManagementService();
    $step = $management->storylineNextStepFromSavedState([
      'current_state' => ['error_count' => 2],
      'storyline' => ['process_flow' => ['tasks' => [
        ['task_id' => 'answer-summons', 'goal_text' => 'Answer the summons.'],
        ['task_id' => 'meet-hunters', 'goal_text' => 'Meet the hunting party.'],
      ]]],
      'validation_summary' => ['error_count' => 2, 'stages' => [[
        'stage' => 'questline_progression',
        'route' => 'draft_operation',
        'errors' => ['raw cause', "Questline node 'meet-hunters' is unreachable from primary quest 'x'."],
        'findings' => [[
          'severity' => 'error', 'code' => 'continuation_replaced_by_choices', 'route' => 'draft_operation',
          'task_id' => 'answer-summons', 'dropped_task_ids' => ['meet-hunters'], 'message' => 'raw cause',
        ]],
      ]]],
    ])[0];
    $this->assertStringStartsWith('Validation finding: The task "Answer the summons" is meant to continue to "Meet the hunting party", but it has choices and none of them leads there', $step);
    StorylineAuthoringDraftService::assertNoIdAssignmentRequest($step);
  }

  /**
   * The model is sent each validation finding once, under its stage.
   */
  public function testIntentContextDoesNotRepeatValidationFindings(): void {
    $management = new AgentHarnessManagementService();
    $compact = $management->compactContextForIntent([
      'draft' => ['draft_id' => 'd'],
      'validation_summary' => [
        'error_count' => 1, 'warning_count' => 1,
        'errors' => ['An error.'], 'warnings' => ['A warning.'],
        'stages' => [['stage' => 'schema', 'route' => 'draft_operation', 'errors' => ['An error.'], 'warnings' => ['A warning.']]],
      ],
    ], 'Storyline Builder');
    $this->assertSame(['error_count', 'warning_count', 'stages'], array_keys($compact['validation_summary']));
    $this->assertSame(['An error.'], $compact['validation_summary']['stages'][0]['errors']);
  }

  /**
   * Findings are stated whole and without draft-internal ID prefixes.
   */
  public function testFindingTextIsNotTruncatedAndDropsDraftPrefixes(): void {
    $management = new AgentHarnessManagementService();
    $message = 'Questline progression cycle detected (draft-12345678-1234-1234-1234-1234567890ab-task-first -> draft-12345678-1234-1234-1234-1234567890ab-task-second -> draft-12345678-1234-1234-1234-1234567890ab-task-first). ' . str_repeat('Detail. ', 30) . 'End of finding.';
    $step = $management->storylineNextStepFromSavedState([
      'current_state' => ['error_count' => 1],
      'validation_summary' => ['error_count' => 1, 'stages' => [[
        'stage' => 'questline_progression', 'route' => 'draft_operation', 'errors' => [$message],
      ]]],
    ])[0];
    $this->assertStringContainsString('(first -> second -> first)', $step);
    $this->assertStringContainsString('End of finding. Options:', $step);
  }

  /**
   * Exhausted paused work now offers skip-or-stop guidance.
   */
  public function testPausedExhaustedWorkGuidanceOffersSkip(): void {
    $management = new AgentHarnessManagementService();
    $response = $management->synthesizeDeterministicMutationResponse('apply_storyline_work', [
      'work_progress' => [
        'step' => 1,
        'total_steps' => 3,
        'status' => 'paused',
        'failed_attempts' => 3,
        'max_failed_attempts' => 3,
        'failure' => ['label' => 'Fix links', 'error' => 'storyline_draft_schema_invalid'],
      ],
    ], [], 'Storyline Builder');
    $this->assertStringContainsString('Attempts are exhausted; skip this unit or stop saved work.', $response['answer']);
    $this->assertSame(['Skip this unit or stop the saved work.'], $response['next_steps']);
  }

  /**
   * Native status recommends one story action, not technical binding chores.
   */
  public function testNativeStatusOwnsHumanScaleGuidance(): void {
    $this->assertSame(['Have the GM develop the first story character.'], StorylineAuthoringDraftService::recommendNextAuthoringAction([
      'missing_content' => ['At least one actor binding', 'At least one dungeon binding', 'At least one room binding'],
    ]));
    foreach ([
      'At least one institution' => "Have the GM define the story's institutions.",
      'Every institution has a cast member' => 'Have the GM tie a cast member to each institution.',
      'Every new cast member is tied to an institution' => "Have the GM tie the cast to the story's institutions.",
    ] as $missing => $step) {
      $this->assertSame([$step], StorylineAuthoringDraftService::recommendNextAuthoringAction([
        'missing_content' => [$missing, 'Every actor has a current character sheet'],
        'error_count' => 3,
      ]));
    }
    $this->assertSame(['Have the GM address the next validation finding.'], StorylineAuthoringDraftService::recommendNextAuthoringAction([
      'error_count' => 28,
      'warning_count' => 10,
    ]));
  }

  /**
   * Invokes the real parser with a controlled model boundary.
   */
  private function parser(array $intent, callable $inspect): EditorGmIntentParser {
    $ai = $this->createMock(AIApiService::class);
    $ai->expects($this->once())->method('invokeModelDirect')->willReturnCallback(static function ($prompt, $module, $operation, $metadata, $options) use ($intent, $inspect) {
      $inspect($prompt, $options);
      // The model replies with one function call: its name is the reply
      // type and its arguments are the rest.
      // A tool is called as its own function, with its arguments beside
      // the reasoning.
      $name = $intent['type'] === 'tool_call' ? $intent['tool_name'] : $intent['type'];
      $arguments = json_encode((object) ($intent['type'] === 'tool_call'
        ? array_diff_key($intent, ['type' => TRUE, 'tool_name' => TRUE, 'arguments' => TRUE]) + $intent['arguments']
        : array_diff_key($intent, ['type' => TRUE])));
      return ['success' => TRUE, 'response' => $arguments, 'tool_call' => ['name' => $name, 'arguments' => $arguments]];
    });
    $logger = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return new EditorGmIntentParser($ai, $logger);
  }

}
