<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Psr\Log\LoggerInterface;

/**
 * Translates author utterances into grounded editor tool calls.
 *
 * The parser is the only place natural language enters the harness, and it is
 * deliberately narrow: it may only select a tool that is already registered,
 * with arguments it declares. It cannot invent capabilities, cannot mutate
 * anything, and cannot fall back to a canned answer. If the model is
 * unavailable or returns something that is not a valid grounded tool call, the
 * request hard-fails so the author is never shown a fabricated result.
 */
class EditorGmIntentParser {

  public const MODULE = 'dungeoncrawler_content';
  public const OPERATION = 'editor_gm_intent';
  public const MODEL_MAX_TOKENS = 4096;

  private const MAX_UTTERANCE_LENGTH = 20000;

  protected LoggerInterface $logger;
  protected AgentHarnessManagementService $harnessManagement;

  public function __construct(
    protected ?AIApiService $ai,
    LoggerChannelFactoryInterface $loggerFactory,
    ?AgentHarnessManagementService $harnessManagement = NULL,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_content');
    $this->harnessManagement = $harnessManagement ?? new AgentHarnessManagementService($this->ai);
  }

  /**
   * Returns TRUE when a model backend is wired up.
   */
  public function isAvailable(): bool {
    return $this->ai !== NULL;
  }

  /**
   * Resolves one utterance into a tool call or a clarification request.
   *
   * The registry is passed per call because the parser is shared by every
   * surface while the toolset is per surface. The parser is grounded on that
   * registry only, so it can never select a tool the surface will not run.
   *
   * @return array
   *   Either ['type' => 'tool_call', 'tool_name' => ..., 'arguments' => [...],
   *   'reasoning' => ...] or ['type' => 'clarification', 'question' => ...].
   */
  public function parse(string $utterance, array $context_snapshot, EditorGmToolRegistry $registry, string $surface_label): array {
    $utterance = trim($utterance);
    if ($utterance === '') {
      throw new \InvalidArgumentException('editor_gm_utterance_required');
    }
    if (mb_strlen($utterance) > self::MAX_UTTERANCE_LENGTH) {
      throw new \InvalidArgumentException('editor_gm_utterance_too_long');
    }
    if ($surface_label === 'Storyline Builder' && !isset($context_snapshot['bounded_work_step'])
      && $this->isStorylineProposalOnlyRequest($utterance)) {
      return [
        'type' => 'direct_response',
        'focus' => 'validation_review',
        'reasoning' => 'The author asked for proposals without authorizing a draft change.',
      ];
    }
    if (
      in_array($surface_label, ['Storyline Builder', 'Dungeon Editor', 'Room Editor', 'Character Creation'], TRUE)
      && !isset($context_snapshot['bounded_work_step']) && $this->isStatusOnlyRequest($utterance)
    ) {
      return [
        'type' => 'direct_response',
        'focus' => 'status',
        'reasoning' => 'The author requested status or next-step guidance without authorizing changes.',
      ];
    }
    if (
      in_array($surface_label, ['Storyline Builder', 'Dungeon Editor', 'Room Editor', 'Character Creation'], TRUE)
      && !isset($context_snapshot['bounded_work_step']) && $this->isValidationReviewRequest($utterance, $context_snapshot)
    ) {
      return [
        'type' => 'direct_response',
        'focus' => 'validation_review',
        'reasoning' => 'The author requested review of current findings without authorizing remediation.',
      ];
    }
    if ($surface_label === 'Storyline Builder' && !isset($context_snapshot['bounded_work_step'])) {
      if ($registry->has('find_existing_institutions')
        && ($institution_lookup = $this->groundedInstitutionLookupIntent($utterance, $context_snapshot)) !== NULL) {
        return $institution_lookup;
      }
      if ($registry->has('publish_storyline_quest_templates')
        && ($quest_publication = $this->groundedQuestTemplatePublicationIntent($utterance, $context_snapshot)) !== NULL) {
        return $quest_publication;
      }
      $active_work = $context_snapshot['authoring_workflow']['bounded_work']['active_work'] ?? NULL;
      $cancel_work = in_array($this->normalizeGovernanceUtterance($utterance), ['cancel saved work', 'cancel the saved work', 'stop saved work', 'stop the saved work'], TRUE);
      $skip_work = in_array($this->normalizeGovernanceUtterance($utterance), ['skip this step', 'skip this step of the saved work', 'skip this unit', 'skip the paused step'], TRUE);
      if (is_array($active_work) && in_array($this->normalizeGovernanceUtterance($utterance), [
        'continue', 'resume', 'resume saved work', 'finish it', 'continue the work',
        'cancel saved work', 'cancel the saved work', 'stop saved work', 'stop the saved work',
        'skip this step', 'skip this step of the saved work', 'skip this unit', 'skip the paused step',
      ], TRUE)) {
        $action = $cancel_work ? 'cancel' : ($skip_work ? 'skip' : 'advance');
        return [
          'type' => 'tool_call',
          'tool_name' => 'apply_storyline_work',
          'arguments' => array_intersect_key($active_work, array_flip(['job_id', 'step', 'expected_revision'])) + ['action' => $action],
          'reasoning' => match ($action) {
            'cancel' => 'Stop unfinished work while retaining completed edits.',
            'skip' => 'Skip the paused unit and keep the rest of the saved work.',
            default => 'Resume the saved authoring work from its exact checkpoint.',
          },
          'next_steps' => [],
        ];
      }
      $finalization_intent = $this->storylineFinalizationIntent($utterance, $context_snapshot);
      if ($finalization_intent !== NULL) {
        return $finalization_intent;
      }
      $repair_intent = $this->groundedQuestReferenceRepairIntent($utterance, $context_snapshot);
      if ($repair_intent !== NULL) {
        return $repair_intent;
      }
    }
    if ($this->ai === NULL) {
      throw new \RuntimeException('editor_gm_intent_parser_unavailable');
    }
    if ($surface_label === 'Storyline Builder' && !isset($context_snapshot['bounded_work_step'])
      && $registry->has('plan_storyline_work') && $this->requiresBoundedStorylinePlan($utterance)) {
      $registry = new EditorGmToolRegistry([$registry->get('plan_storyline_work')], [], NULL);
    }

    $execution_options = $this->harnessManagement->selectIntentExecutionProfile($utterance, $surface_label);
    $execution_options['system_prompt'] = $this->systemPrompt($surface_label);
    if ($surface_label === 'Storyline Builder' && isset($context_snapshot['bounded_work_step'])) {
      $execution_options['system_prompt'] = <<<'STEP'
You are the native draft-operation planner for ONE CHECKPOINTED STORYLINE WORK UNIT.

The author has already approved the work. Call the
plan_storyline_draft_operations function once, with reasoning (one short
sentence describing this unit), expected_revision (the current saved integer
revision) and operations (the native operations).

No other reply is supported. Never answer in plain text, and never return an
execution result, bare operations, error object, or nested work plan.
Use no more than ten compact operations / 12000 JSON bytes. Prefer small patches
over repeating whole collections or unchanged fields. Do not publish.

Work only on the current instruction. The remaining plan is context, NOT work
to include now. When bounded_work_step.current_scene is present, edit ONLY that
scene and its own tasks/branch choices. Do not modify another scene or shared
actors, objectives, or conflicts. Actor/story effects inside its choices are
allowed. Use saved destination IDs from earlier prerequisite units.
A branch source_task_id must belong to current_scene_task_ids. If that list is
empty, append a source quest for this scene and use its {"$draft_ref":"key.task_id"}
as source_task_id. A destination quest is never the source_task_id.
Omit generated IDs on newly appended choices and quests. When creating a
playable task, create its objective in the same batch and link it with the
task's draft reference. A task with goal_text needs an authored approach or
an explicitly intended open resolution. Narrative-only text belongs in its
scene, not in an unfinishable task.
For separate endings, explicitly end each branch after its own closing beat.
Without a terminal choice, a closing task can fall through to the next ending.
Keep any shared prerequisite outside the approaches; never assign a single
objective to multiple approaches. Leave approach objectives empty only when
the GM judges that approach from the authored trigger.

Obey the selected tool's exact operation and collection contracts. Reuse saved
IDs. For new append rows omit server-generated identity fields, assign internal
batch keys, and use {"$draft_ref":"key.task_id"} or the appropriate identity
field for links inside this batch. Never guess a future generated ID.
Operation paths are rooted at the draft payload itself. Use /player_hook,
/ending, /process_flow/... and /objects/...; never prefix paths with
/storyline/. A denouement edit uses
/process_flow/narrative_architecture/beats/denouement/<saved field>, never
/narrative_architecture/... . The authored entry location is /entry_location_id. Generated
navigation (entry_point, entry_dungeon, progression_connectors) is not
writable. For a navigation finding, work from the authored entry location
and room-site binding. Never invent a patch to the generated outline.
For existing rows use ID-matched native patches containing changed fields only.
Branch choices use typed triggers, native effect enums, and real task/scene
destinations. Only a terminal choice has no destination. Do not infer actor
death unless the request and effects explicitly state it. Use the grounded
effect target enums exactly as published by the native contract.
When using append keys for $draft_ref links, each key must be unique in the
current operation batch.

If previous_rejected_attempt is present, this is an explicit new author request
to resume. Correct that rejected contract before proposing this unit again.
Do not repeat or undo saved work from completed units. Do not invent validation
results or further author chores.
STEP;
    }

    $lookup_evidence = $context_snapshot['lookup_evidence'] ?? [];
    $functions = $this->intentFunctions(
      $registry, $surface_label, isset($context_snapshot['bounded_work_step']),
      isset($context_snapshot['lookup_evidence']),
      array_intersect(['find_existing_institutions', 'find_library_characters', 'inspect_storyline_actor_sheets'], array_column($lookup_evidence, 'tool')),
      array_column($context_snapshot['storyline']['objects']['actors']['generation_specs'] ?? [], 'actor_id'),
    );
    unset($execution_options['json_object'], $execution_options['response_format']);
    $execution_options['tools'] = self::providerFunctions($functions);
    $execution_options['tool_choice'] = count($functions) === 1
      ? ['type' => 'function', 'function' => ['name' => (string) array_key_first($functions)]]
      : 'required';
    // Several tool calls in one reply are a queue, where a queue is offered.
    $execution_options['tool_calls_multiple'] = isset($functions['task_queue']);
    $result = $this->ai->invokeModelDirect(
      $this->buildPrompt($utterance, $context_snapshot, $registry, $functions, $surface_label),
      self::MODULE,
      self::OPERATION,
      ['draft_id' => (string) ($context_snapshot['draft']['draft_id'] ?? '')],
      $execution_options,
    );

    if (!is_array($result) || empty($result['success'])) {
      $this->logger->error('Editor GM intent parsing failed: @error', [
        '@error' => is_array($result) ? (string) ($result['error'] ?? 'unknown') : 'malformed provider response',
      ]);
      throw new \RuntimeException('editor_gm_intent_model_failed');
    }

    if (in_array($result['finish_reason'] ?? $result['stop_reason'] ?? '', ['length', 'max_tokens'], TRUE)) {
      // The provider's own counts decide the cause; estimates cannot.
      $usage = is_array($result['usage'] ?? NULL) ? $result['usage'] : [];
      $prompt_tokens = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : NULL;
      $reply_tokens = isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : NULL;
      $budget = (int) ($execution_options['max_tokens'] ?? 0);
      // Cut short of its own budget: the prompt left no room in the model's
      // context window. Cut at the budget: the reply itself was too long.
      $window_exhausted = $reply_tokens !== NULL && $reply_tokens < $budget;
      $this->logger->error('Editor GM intent reply was cut off for @surface/@draft (@model): @cause. Prompt @input tokens, reply @output of @tokens allowed. No intent was executed.', [
        '@surface' => $surface_label,
        '@draft' => (string) ($context_snapshot['draft']['draft_id'] ?? ''),
        '@model' => (string) ($result['model_id'] ?? ''),
        '@cause' => $window_exhausted ? 'the prompt filled the model context window' : 'the reply reached its token budget',
        '@input' => $prompt_tokens ?? 'unreported',
        '@output' => $reply_tokens ?? 'unreported',
        '@tokens' => $budget,
      ]);
      throw new \RuntimeException($window_exhausted ? 'editor_gm_context_window_exhausted' : 'editor_gm_intent_output_truncated', 502);
    }

    $calls = array_values(array_filter((array) ($result['tool_calls'] ?? [$result['tool_call'] ?? NULL]), 'is_array'));
    $call = $calls[0] ?? [];
    try {
      return $this->decodeIntent(
        count($calls) > 1 ? $this->queueFromFunctionCalls($calls, $functions) : $this->decodeFunctionCall($call, $functions),
        $registry,
        $surface_label === 'Storyline Builder',
      );
    }
    catch (\DomainException | \InvalidArgumentException $exception) {
      $this->logger->error('Editor GM intent contract rejected for @surface/@draft: @error; model=@model; function=@function; reply=@reply', [
        '@surface' => $surface_label,
        '@draft' => (string) ($context_snapshot['draft']['draft_id'] ?? ''),
        '@error' => $exception->getMessage(),
        '@model' => (string) ($result['model_id'] ?? ''),
        '@function' => (string) ($call['name'] ?? '(none)'),
        '@reply' => mb_substr((string) ($call['arguments'] ?? $result['response'] ?? ''), 0, 4000),
      ]);
      throw $exception;
    }
  }

  /**
   * The reply shapes the model may use, as functions it must call.
   *
   * Each schema is used twice: sent to the provider so the reply is generated
   * against it, and checked again on receipt.
   *
   * Every function takes a one-sentence reasoning and none takes a next
   * step: the next step is stated after the work has run, from its result.
   *
   * @return array<string, array{description:string, strict:bool, parameters:array}>
   */
  private function intentFunctions(EditorGmToolRegistry $registry, string $surface_label, bool $bounded_work_step, bool $lookup_continuation = FALSE, array $completed_lookups = [], array $saved_actor_ids = []): array {
    $string = static fn(string $description): array => ['type' => 'string', 'description' => $description];
    $tools = $bounded_work_step
      ? [$this->toolDefinition($registry, 'plan_storyline_draft_operations')]
      : $this->harnessManagement->filterToolsForNaturalLanguage($registry->manifest(), $surface_label);
    $tools = array_filter($tools, static fn(array $tool): bool => !in_array($tool['name'], $completed_lookups, TRUE));
    // Each tool is its own function, taking the tool's declared arguments
    // beside the reasoning. There is no generic call with an untyped
    // arguments object for a field to land in.
    $functions = [];
    $queue_items = [];
    foreach ($tools as $tool) {
      $properties = [];
      $required = [];
      foreach ($tool['arguments'] as $argument) {
        if ($bounded_work_step && $argument['name'] === 'preview_only') {
          continue;
        }
        $properties[$argument['name']] = ['type' => self::schemaType($argument['type']), 'description' => $argument['description']];
        if ($argument['name'] === 'operations' && in_array($tool['name'], ['plan_storyline_draft_operations', 'plan_storyline_remediation'], TRUE)) {
          $properties['operations']['items'] = StorylineAuthoringDraftService::draftOperationSchema($saved_actor_ids);
        }
        if ($argument['name'] === 'steps' && in_array($tool['name'], ['plan_storyline_work', 'apply_storyline_work'], TRUE)) {
          $properties['steps']['items'] = StorylineGmWorkService::stepSchema();
        }
        if ($tool['name'] === 'publish_storyline_quest_templates' && $argument['name'] === 'task_ids') {
          $properties['task_ids'] += [
            'minItems' => 1, 'maxItems' => 100, 'uniqueItems' => TRUE,
            'items' => ['type' => 'string', 'minLength' => 1],
          ];
        }
        if ($tool['name'] === 'repair_storyline_item_definitions' && $argument['name'] === 'item_ids') {
          $properties['item_ids'] += [
            'minItems' => 1, 'uniqueItems' => TRUE,
            'items' => ['type' => 'string', 'minLength' => 1],
          ];
        }
        if ($argument['required']) {
          $required[] = $argument['name'];
        }
      }
      $authorization = $lookup_continuation ? [
        'authorizes_changes' => [
          'type' => 'boolean',
          'description' => 'True only if the author explicitly requested or selected a change, not for questions, inspection, or an unresolved choice.',
        ],
      ] : [];
      $functions[$tool['name']] = [
        'description' => $tool['summary'],
        'strict' => FALSE,
        'tool' => $tool['name'],
        'parameters' => [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'required' => array_merge(['reasoning'], array_keys($authorization), $required),
          'properties' => ['reasoning' => $string('One short sentence.')] + $authorization + $properties,
        ],
      ];
      $queue_items[] = [
        'type' => 'object',
        'additionalProperties' => FALSE,
        'required' => ['tool_name', 'reasoning', 'arguments'],
        'properties' => [
          'tool_name' => ['const' => $tool['name']],
          'reasoning' => $string('Step summary.'),
          'arguments' => [
            'type' => 'object',
            'additionalProperties' => FALSE,
            'required' => $required,
            // Same constraints, without repeating argument descriptions.
            'properties' => array_map(static fn(array $property): array => array_diff_key($property, ['description' => TRUE]), $properties),
          ],
        ],
      ];
    }
    if ($bounded_work_step) {
      return $functions;
    }
    $functions['task_queue'] = [
      'description' => 'Select several registered tools to run in order for one author request.',
      'strict' => FALSE,
      'parameters' => [
        'type' => 'object',
        'additionalProperties' => FALSE,
        'required' => array_merge(['tasks', 'reasoning'], $lookup_continuation ? ['authorizes_changes'] : []),
        'properties' => [
          'reasoning' => $string('Overall summary.'),
          ...($lookup_continuation ? [
            'authorizes_changes' => [
              'type' => 'boolean',
              'description' => 'True only if the author explicitly requested or selected the queued changes; false for inspection or unresolved choices.',
            ],
          ] : []),
          'tasks' => [
            'type' => 'array',
            'minItems' => 1,
            'maxItems' => 8,
            'items' => ['anyOf' => $queue_items],
          ],
        ],
      ],
    ];
    $functions['direct_response'] = [
      'description' => 'Answer from the grounded context without running a tool: status, review of findings, or discussion.',
      'strict' => TRUE,
      'parameters' => [
        'type' => 'object',
        'additionalProperties' => FALSE,
        'required' => ['focus', 'reasoning'],
        'properties' => [
          'focus' => ['type' => 'string', 'enum' => ['status', 'validation_review', 'discussion']],
          'reasoning' => $string('One short sentence.'),
          'authorizes_changes' => [
            'type' => 'boolean',
            'description' => 'Optional provider carry-over. Direct responses never authorize a mutation; the value is ignored.',
          ],
        ],
      ],
    ];
    if ($surface_label !== 'Character Creation') {
      $functions['clarification'] = [
        'description' => 'Ask the author one decision the request cannot proceed without.',
        'strict' => TRUE,
        'parameters' => [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'required' => ['question', 'options', 'reasoning'],
          'properties' => [
            'question' => $string('The decision the author must make.'),
            'reasoning' => $string('One short sentence: why the request cannot proceed without this decision.'),
            'options' => [
              'type' => 'array',
              'items' => [
                'type' => 'object',
                'additionalProperties' => FALSE,
                'required' => ['label', 'description', 'tradeoff'],
                'properties' => [
                  'label' => $string('Short name.'),
                  'description' => $string('Self-contained direction: concept, purpose, relationships, and progression effect.'),
                  'tradeoff' => $string('Benefit and cost.'),
                ],
              ],
            ],
          ],
        ],
      ];
    }
    return $functions;
  }

  /**
   * The function definitions in the provider's request format.
   */
  private static function providerFunctions(array $functions): array {
    $tools = [];
    foreach ($functions as $name => $function) {
      $tools[] = ['type' => 'function', 'function' => [
        'name' => $name,
        'description' => $function['description'],
        'parameters' => self::schemaForProvider($function['parameters']),
      ] + ($function['strict'] ? ['strict' => TRUE] : [])];
    }
    return $tools;
  }

  /**
   * Reads several function calls in one reply as a task queue.
   *
   * The model asks for several tools at once by calling each; that is the
   * same request as task_queue. Every call must be a registered tool: an
   * answer or a question cannot be mixed with work.
   */
  private function queueFromFunctionCalls(array $calls, array $functions): array {
    $tasks = [];
    foreach ($calls as $call) {
      $task = $this->decodeFunctionCall($call, $functions);
      if ($task['type'] !== 'tool_call') {
        throw new \DomainException('editor_gm_intent_parallel_call_not_a_tool:' . (string) ($call['name'] ?? ''));
      }
      $tasks[] = array_diff_key($task, ['type' => TRUE]);
    }
    $queue = [
      'type' => 'task_queue',
      'reasoning' => implode(' ', array_unique(array_column($tasks, 'reasoning'))),
      'tasks' => $tasks,
    ];
    if (count(array_filter($tasks, static fn(array $task): bool => array_key_exists('authorizes_changes', $task))) === count($tasks)) {
      $queue['authorizes_changes'] = count(array_filter($tasks, static fn(array $task): bool => $task['authorizes_changes'] === TRUE)) === count($tasks);
    }
    return $queue;
  }

  /**
   * Reads one function call as an intent, checked against its own schema.
   *
   * @return array<string, mixed>
   *   The call's arguments with 'type' set to the function that was called.
   */
  private function decodeFunctionCall(array $call, array $functions): array {
    $name = (string) ($call['name'] ?? '');
    if ($name === '') {
      throw new \DomainException('editor_gm_intent_function_call_missing');
    }
    if (!isset($functions[$name])) {
      throw new \DomainException('editor_gm_intent_function_not_offered:' . $name);
    }
    $arguments = $this->decodeJsonObject((string) ($call['arguments'] ?? ''), 'editor_gm_intent_response_not_json');
    $findings = (new DefinitionSchemaValidator())->validate($functions[$name]['parameters'], $arguments);
    if ($findings !== []) {
      $finding = $findings[0];
      throw new \DomainException(sprintf(
        'editor_gm_intent_schema_invalid:%s:%s:%s',
        $name,
        (string) ($finding['pointer'] ?? ''),
        (string) ($finding['message'] ?? ''),
      ));
    }
    if (isset($functions[$name]['tool'])) {
      $intent = [
        'type' => 'tool_call',
        'tool_name' => $functions[$name]['tool'],
        'reasoning' => $arguments['reasoning'],
        'arguments' => array_diff_key($arguments, ['reasoning' => TRUE, 'authorizes_changes' => TRUE]),
      ];
      if (array_key_exists('authorizes_changes', $arguments)) {
        $intent['authorizes_changes'] = $arguments['authorizes_changes'];
      }
      return $intent;
    }
    return ['type' => $name] + $arguments;
  }

  /**
   * A schema as it is sent: an empty property set is an object, not a list.
   *
   * PHP encodes an empty array as a JSON list; the receipt check reads the
   * same schema as arrays, so the difference is made only here.
   */
  private static function schemaForProvider(array $schema): array {
    foreach ($schema as $key => $value) {
      if ($key === 'properties' && $value === []) {
        $schema[$key] = new \stdClass();
      }
      elseif (is_array($value)) {
        $schema[$key] = self::schemaForProvider($value);
      }
    }
    return $schema;
  }

  /**
   * The JSON Schema type of a declared tool argument type.
   *
   * A declaration such as "string|null" is a list of allowed types.
   *
   * @return string|string[]
   */
  private static function schemaType(string $declared): string|array {
    $types = explode('|', $declared);
    if (array_diff($types, ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null']) !== []) {
      throw new \LogicException('editor_gm_tool_argument_type_unsupported:' . $declared);
    }
    return count($types) === 1 ? $types[0] : $types;
  }

  /**
   * One registered tool's definition, by name.
   */
  private function toolDefinition(EditorGmToolRegistry $registry, string $tool_name): array {
    foreach ($registry->manifest()['families'] as $definitions) {
      foreach ($definitions as $definition) {
        if ($definition['name'] === $tool_name) {
          return $definition;
        }
      }
    }
    throw new \LogicException('editor_gm_tool_not_registered:' . $tool_name);
  }

  /**
   * Recognizes bounded read-only status questions before model tool selection.
   */
  private function isStatusOnlyRequest(string $utterance): bool {
    $normalized = $this->normalizeGovernanceUtterance($utterance);

    return in_array($normalized, [
      'status',
      'draft status',
      'next step',
      'ok next step',
      'okay next step',
      'what next',
      'what is next',
      'what is the next step',
      "what's next",
      "what's the next step",
      'ok what next',
      'ok what is next',
      'okay what next',
      'okay what is next',
      'where are we',
      'what remains',
      'what still needs work',
      'what needs review',
      'what is missing',
      'what is left',
      'how complete is this',
      'how complete is the draft',
      'check again',
      'recheck',
      'check the draft again',
      'validate again',
      'revalidate',
    ], TRUE);
  }

  /**
   * Recognizes requests to inspect findings without applying fixes.
   */
  private function isValidationReviewRequest(string $utterance, array $context_snapshot): bool {
    if ((int) ($context_snapshot['validation_summary']['warning_count'] ?? 0) === 0
      && (int) ($context_snapshot['validation_summary']['error_count'] ?? 0) === 0) {
      return FALSE;
    }
    $normalized = $this->normalizeGovernanceUtterance($utterance);
    return in_array($normalized, [
      'lets go through them',
      "let's go through them",
      'go through them',
      'lets review them',
      "let's review them",
      'review them',
      'show me the warnings',
      'show the warnings',
      'open the warnings',
      'review the warnings',
      'go through the warnings',
      'explain the warnings',
      'what are the warnings',
    ], TRUE);
  }

  private function normalizeGovernanceUtterance(string $utterance): string {
    $normalized = mb_strtolower(trim($utterance));
    $normalized = preg_replace('/[^\p{L}\p{N}\s\']+/u', ' ', $normalized) ?? $normalized;
    return preg_replace('/\s+/u', ' ', trim($normalized)) ?? $normalized;
  }

  private function groundedInstitutionLookupIntent(string $utterance, array $snapshot): ?array {
    foreach ((array) ($snapshot['lookup_evidence'] ?? []) as $lookup) {
      if (($lookup['tool'] ?? '') === 'find_existing_institutions') {
        return NULL;
      }
    }
    $normalized = $this->normalizeGovernanceUtterance($utterance);
    if (preg_match('/\b(?:institutional library|institution library|institution|town guard|guard)\b/', $normalized) !== 1
      || preg_match('/\b(?:look through|look in|search|find|which|what|sensible|makes sense|attachment)\b/', $normalized) !== 1) {
      return NULL;
    }
    $has_guard = str_contains($normalized, 'guard');
    $has_absalom = str_contains($normalized, 'absalom');
    if (!$has_guard && !$has_absalom) {
      return NULL;
    }
    $tasks = [];
    if ($has_guard) {
      $tasks[] = [
        'tool_name' => 'find_existing_institutions',
        'arguments' => ['search' => 'guard', 'domain' => 'organization', 'limit' => 25],
        'reasoning' => 'Find an existing guard or watch organization in the institution library.',
      ];
    }
    if ($has_absalom) {
      $tasks[] = [
        'tool_name' => 'find_existing_institutions',
        'arguments' => ['search' => 'Absalom', 'domain' => 'place', 'limit' => 25],
        'reasoning' => 'Find the canonical Absalom place needed for the actor home tie.',
      ];
    }
    if (count($tasks) === 1) {
      return [
        'type' => 'tool_call',
        'tool_name' => $tasks[0]['tool_name'],
        'arguments' => $tasks[0]['arguments'],
        'reasoning' => $tasks[0]['reasoning'],
        'next_steps' => [],
      ];
    }
    return [
      'type' => 'task_queue',
      'tasks' => $tasks,
      'reasoning' => 'Search guard organizations and the Absalom home place before proposing Celia’s attachment.',
      'authorizes_changes' => FALSE,
      'next_steps' => [],
    ];
  }

  private function isStorylineProposalOnlyRequest(string $utterance): bool {
    $normalized = $this->normalizeGovernanceUtterance(str_replace("\u{2019}", "'", $utterance));
    return preg_match('/\b(?:propose|suggest|recommend|give me|offer)\b/', $normalized) === 1
      && preg_match('/\b(?:do not|don\'t|dont|without)\s+(?:fix(?:ing)?|apply(?:ing)?|edit(?:ing)?|chang(?:e|ing)|sav(?:e|ing))\b/', $normalized) === 1;
  }

  /**
   * Resolves an accepted offer only while its saved scope still matches.
   */
  private function groundedQuestTemplatePublicationIntent(string $utterance, array $snapshot): ?array {
    $normalized = $this->normalizeGovernanceUtterance($utterance);
    if (preg_match('/^(?:please |lets |let s )?create (?:the )?(\d+|one|two|three|four|five|six|seven|eight|nine|ten) (?:canonical )?quest templates?(?: one at a time)?$/i', $normalized, $offer) !== 1) {
      return NULL;
    }
    $count = array_search(strtolower($offer[1]), ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'], TRUE);
    $count = $count === FALSE ? (int) $offer[1] : $count;
    $tasks = array_column($snapshot['storyline']['process_flow']['tasks'] ?? [], NULL, 'task_id');
    $missing = [];
    foreach ($snapshot['validation_summary']['warnings'] ?? [] as $warning) {
      if (preg_match("/^Linked quest '([^']+)' is missing from canonical quest templates\\.$/", $warning, $match) === 1
        && isset($tasks[$match[1]])) {
        $missing[$match[1]] = $match[1];
      }
    }
    if ($missing === [] || count($missing) !== $count) {
      return NULL;
    }
    return [
      'type' => 'tool_call',
      'tool_name' => 'publish_storyline_quest_templates',
      'arguments' => [
        'expected_revision' => (int) $snapshot['draft']['revision'],
        'task_ids' => array_values($missing),
      ],
      'reasoning' => 'Create the authorized set of missing quest templates from their saved tasks.',
      'next_steps' => [],
    ];
  }

  /**
   * Routes visibly broad edits before a model can emit their full patch.
   */
  private function requiresBoundedStorylinePlan(string $utterance): bool {
    $text = mb_strtolower($utterance);
    return preg_match('/\b(fork|branch|add|create|revise|rewrite|update|change|edit|build|develop)\b/u', $text) === 1
      && preg_match('/\b(every|each|all|multiple|several)\s+(?:of\s+the\s+|the\s+)?scenes?\b/u', $text) === 1;
  }

  /**
   * Routes review and explicit finalization to their existing native tools.
   */
  private function storylineFinalizationIntent(string $utterance, array $snapshot): ?array {
    $normalized = $this->normalizeGovernanceUtterance($utterance);
    if (in_array($normalized, [
      'review', 'final review', 'review the storyline', 'review for publication',
      'mark review complete', 'mark review completed', 'complete the review',
    ], TRUE)) {
      return [
        'type' => 'tool_call',
        'tool_name' => 'check_storyline_creation_readiness',
        'arguments' => [],
        'reasoning' => 'Complete canonical review of the current saved revision without publishing or changing story content.',
        'next_steps' => [],
      ];
    }
    if (!in_array($normalized, [
      'review and create', 'review and publish', 'review and create the storyline',
      'publish', 'publish it', 'publish the storyline', 'publish the draft',
      'create the storyline', 'create the final signoff', 'create final signoff',
      'complete the final signoff', 'complete final signoff',
      'record the final signoff', 'record final signoff', 'sign off',
      'finalize the storyline',
    ], TRUE)) {
      return NULL;
    }
    return [
      'type' => 'tool_call',
      'tool_name' => 'publish_storyline_draft',
      'arguments' => ['expected_revision' => (int) $snapshot['draft']['revision']],
      'reasoning' => 'Review and publish the explicitly approved saved revision, recording the native publication receipt as final signoff.',
      'next_steps' => [],
    ];
  }

  /**
   * Routes approval of a grounded quest-reference correction to its authority.
   */
  private function groundedQuestReferenceRepairIntent(string $utterance, array $snapshot): ?array {
    if (empty($snapshot['authoring_workflow']['linked_quest_reference_repairs'])) {
      return NULL;
    }
    $normalized = $this->normalizeGovernanceUtterance($utterance);
    $explicit = in_array($normalized, [
      'fix the quest references', 'repair the quest references',
      'align the linked quest references', 'apply the quest reference fix',
    ], TRUE);
    $continuation = in_array($normalized, [
      'apply it', 'apply that', 'do it', 'fix it', 'go ahead',
      'lets update the base name', "let's update the base name",
    ], TRUE);
    $conversation = (string) ($snapshot['conversation_context'] ?? '');
    $last_next_steps = strrpos($conversation, 'NEXT STEPS:');
    $proposed = $last_next_steps === FALSE ? '' : substr($conversation, $last_next_steps);
    $response_start = strpos($proposed, "\nRESPONSE:");
    if ($response_start !== FALSE) {
      $proposed = substr($proposed, 0, $response_start);
    }
    $grounded_proposal = preg_match('/\\bquest\\b[^\\n]{0,180}\\b(?:references?|story-impact|storyline|template)\\b/i', $proposed) === 1;
    if (!$explicit && !($continuation && $grounded_proposal)) {
      return NULL;
    }
    return [
      'type' => 'tool_call',
      'tool_name' => 'plan_storyline_remediation',
      'arguments' => [
        'expected_revision' => (int) $snapshot['draft']['revision'],
        'scope' => 'quest_storyline_references',
      ],
      'reasoning' => 'Apply the requested, grounded linked-quest reference correction without changing quest content or generating assets.',
      'next_steps' => [],
    ];
  }

  /**
   * Parses and validates the model's JSON response against the registry.
   */
  private function decodeIntent(array $decoded, EditorGmToolRegistry $registry, bool $requires_storyline_choices): array {
    if ($requires_storyline_choices) {
      $this->assertStorylineDecisionsValid($decoded);
    }

    $type = (string) ($decoded['type'] ?? '');
    if ($type === 'clarification') {
      $question = trim((string) ($decoded['question'] ?? ''));
      if ($question === '') {
        throw new \DomainException('editor_gm_intent_clarification_empty');
      }
      $options = $this->decodeDecisionOptions($decoded['options'] ?? [], $requires_storyline_choices);
      return [
        'type' => 'clarification',
        'question' => $question,
        'options' => $options,
        'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
      ];
    }
    if ($type === 'direct_response') {
      $focus = trim((string) ($decoded['focus'] ?? ''));
      if (!in_array($focus, ['status', 'validation_review', 'discussion'], TRUE)) {
        throw new \DomainException('editor_gm_intent_direct_response_focus_invalid');
      }
      return [
        'type' => 'direct_response',
        'focus' => $focus,
        'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
        'authorizes_changes' => FALSE,
      ];
    }
    if ($type === 'task_queue') {
      $raw_tasks = $decoded['tasks'] ?? NULL;
      if (!is_array($raw_tasks) || $raw_tasks === [] || count($raw_tasks) > 8) {
        throw new \DomainException('editor_gm_intent_task_queue_invalid');
      }
      $validated_tasks = [];
      foreach (array_values($raw_tasks) as $raw_task) {
        if (!is_array($raw_task)) {
          throw new \DomainException('editor_gm_intent_task_queue_item_invalid');
        }
        $validated_tasks[] = $this->validateToolCallPayload($raw_task, $registry);
      }
      return [
        'type' => 'task_queue',
        'tasks' => $this->harnessManagement->orderTaskQueue($validated_tasks),
        'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
        'next_steps' => [],
      ] + (array_key_exists('authorizes_changes', $decoded) ? ['authorizes_changes' => $decoded['authorizes_changes'] === TRUE] : []);
    }
    if ($type !== 'tool_call') {
      throw new \DomainException('editor_gm_intent_type_unsupported');
    }

    return $this->validateToolCallPayload($decoded, $registry);
  }

  /**
   * Invalid technical decisions must fail before any task can save changes.
   */
  private function assertStorylineDecisionsValid(array $intent): void {
    if (is_string($intent['question'] ?? NULL)) {
      StorylineAuthoringDraftService::assertNoIdAssignmentRequest($intent['question']);
    }
    foreach ((array) ($intent['options'] ?? []) as $option) {
      foreach (is_array($option) ? $option : [] as $text) {
        if (is_string($text)) {
          StorylineAuthoringDraftService::assertNoIdAssignmentRequest($text);
        }
      }
    }
  }

  /**
   * Validates one tool_call payload against the surface registry.
   */
  private function validateToolCallPayload(array $decoded, EditorGmToolRegistry $registry): array {
    $tool_name = (string) ($decoded['tool_name'] ?? '');
    $arguments = $decoded['arguments'] ?? [];
    if (!is_array($arguments)) {
      throw new \DomainException('editor_gm_intent_arguments_invalid');
    }

    if ($tool_name === AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
      $definition_arguments = $this->harnessManagement->getBacklogSuggestionToolDescriptor()['arguments'] ?? [];
      foreach ($definition_arguments as $argument) {
        if (!empty($argument['required']) && !array_key_exists($argument['name'], $arguments)) {
          throw new \DomainException(sprintf(
            'editor_gm_intent_argument_missing:%s.%s',
            $tool_name,
            $argument['name']
          ));
        }
      }
      return [
        'type' => 'tool_call',
        'tool_name' => $tool_name,
        'arguments' => $arguments,
        'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
        'next_steps' => [],
      ] + (array_key_exists('authorizes_changes', $decoded) ? ['authorizes_changes' => $decoded['authorizes_changes'] === TRUE] : []);
    }

    if (!$registry->has($tool_name)) {
      throw new \DomainException(sprintf('editor_gm_intent_tool_unsupported:%s', $tool_name));
    }

    $definition = $registry->get($tool_name)->definition();
    foreach ($definition->arguments as $argument) {
      if (!empty($argument['required']) && !array_key_exists($argument['name'], $arguments)) {
        throw new \DomainException(sprintf(
          'editor_gm_intent_argument_missing:%s.%s',
          $tool_name,
          $argument['name']
        ));
      }
    }

    if (
      in_array($tool_name, ['plan_storyline_draft_operations', 'apply_storyline_draft_operations', 'plan_storyline_remediation', 'apply_storyline_remediation'], TRUE)
      && is_array($arguments['operations'] ?? NULL)
    ) {
      $arguments['operations'] = $this->harnessManagement->orderStorylineDraftOperations($arguments['operations']);
    }

    return [
      'type' => 'tool_call',
      'tool_name' => $tool_name,
      'arguments' => $arguments,
      'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
      'next_steps' => [],
    ] + (array_key_exists('authorizes_changes', $decoded) ? ['authorizes_changes' => $decoded['authorizes_changes'] === TRUE] : []);
  }

  /**
   * Decodes a function call's arguments: one JSON object, nothing around it.
   */
  private function decodeJsonObject(string $response, string $error_code): array {
    try {
      $decoded = json_decode($response, TRUE, 64, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \DomainException($error_code);
    }
    if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
      throw new \DomainException($error_code);
    }
    return $decoded;
  }

  /**
   * Validates structured alternatives for human-led Storyline decisions.
   */
  private function decodeDecisionOptions(mixed $raw_options, bool $required): array {
    if (!$required && !is_array($raw_options)) {
      return [];
    }
    if (!is_array($raw_options)) {
      throw new \DomainException('editor_gm_intent_clarification_options_invalid');
    }
    $options = [];
    foreach (array_values($raw_options) as $option) {
      if (!is_array($option)) {
        throw new \DomainException('editor_gm_intent_clarification_options_invalid');
      }
      $normalized = [
        'label' => trim((string) ($option['label'] ?? '')),
        'description' => trim((string) ($option['description'] ?? '')),
        'tradeoff' => trim((string) ($option['tradeoff'] ?? '')),
      ];
      if (in_array('', $normalized, TRUE)) {
        throw new \DomainException('editor_gm_intent_clarification_option_incomplete');
      }
      $options[] = $normalized;
    }
    if ($required && count($options) !== 3) {
      throw new \DomainException('editor_gm_intent_clarification_requires_three_options');
    }
    if (count(array_unique(array_column($options, 'label'))) !== count($options)) {
      throw new \DomainException('editor_gm_intent_clarification_options_duplicate');
    }
    return $options;
  }

  /**
   * Returns the invariant behavioural contract for the parser.
   */
  private function systemPrompt(string $surface_label): string {
    $mutation_rule = match ($surface_label) {
      'Storyline Builder' => 'For Storyline Builder, a request to define, add or choose an institution (clan, family, settlement, faction, guard, guild, cult or other group) MUST first use find_existing_institutions so one that already exists is reused under the same domain and name; institutions and the cast\'s affiliations to them are then saved with plan_storyline_draft_operations. For Storyline Builder, a question about goals, tasks or objectives that were established earlier, or a request to write objectives for a task that already exists as a published quest, MUST first use load_existing_quests. For Storyline Builder, a request to bind, add, attach or use a character the author names, or one found earlier in this conversation, MUST use bind_library_characters with every name in one call; it looks the characters up itself, so never search first and never repeat a lookup already answered. For Storyline Builder, a request to suggest or choose a character, quest giver, contact or hook whose identity the author has not fixed MUST first use find_library_characters (quest_givers_only for a quest giver or hook) so library characters can be offered before a new one is invented. For Storyline Builder, explicit requests or selected options to build roster actor character sheets MUST use build_storyline_actor_sheets with the grounded expected_revision. Omit actor_ids for the whole roster; otherwise use grounded roster IDs. This tool creates missing character drafts, derives native classes from authored roles, completes and links the sheets itself; it needs no existing character context. Role-driven generation is already a complete decision: never ask for classes, more confirmation or a separate draft ID. Sheet inspection uses inspect_storyline_actor_sheets. These sheet tools take precedence over generic draft and remediation routing. For other draft changes use plan_storyline_draft_operations, and for other missing canonical assets use plan_storyline_remediation only when explicitly requested; the harness may immediately apply the authorized plan through the owning authorities.',
      'Character Creation' => 'For Character Creation, use plan_character_draft_updates for requested changes. The GM has authority to overwrite unsaved form edits and applies validated patches against the latest locked character; never ask for save/discard, conflict resolution, or approval. Present genuine unresolved player decisions as numbered choices, one option per line. Read-only inspection and local catalog lookups never save changes. Use inspect_actor for the canonical actor view, its revision and change history. Use apply_actor_changes only for typed actor commands: actor.narrative.patch and actor.identity.patch for free-text identity and narrative paths listed by inspect_actor, and actor.archive or actor.restore when the player explicitly asks to archive or restore the character; wizard choices always go through plan_character_draft_updates. When the player asks you to build, create, make, roll up, finish, complete or auto-fill the character, or to choose, pick or decide for them, use autofill_character_draft once: put every choice the player stated or plainly implied into preferences with native IDs from the grounded reference (an evoker is subclass evocation, a former miner is the closest listed background), write the free-text fields they did not give (an original in-world personal name of at least two characters, concept, appearance, personality, backstory, age, gender) yourself so they fit the request, and leave every other rules choice out so it is filled from the native options. Never return a clarification on Character Creation and never ask the player to confirm, approve or choose before acting: act on the most reasonable reading, save it, and let the player revise afterwards.',
      'Room Editor' => 'When the room belongs to a storyline, load_story_brief says what the story needs from it (its scene, tasks, objectives and starting cast); read it before generating a layout or placing contents, and build the room to serve it. In the Room Editor, "create/make/build X" means create the canonical placeable definition for X unless the request explicitly says room, chamber, hallway, dungeon, corridor, tunnel, or site; do not clarify between an object and a new room in that case. A request to shrink or reshape a street/district hub must use plan_room_commands goal reshape_street_footprint, never shrink_footprint without an explicit hex list; the street tool derives the valid band and preserves its sole entrance. A request to revert or restore the previous/published/base version loads the grounded base_version_id with restore_published_base; do not route it to undo without a command ID. When validation reports an empty room, present all five recommended_features and all five catalog_matches from empty_room_recommendations, distinguishing original room-fitting ideas from existing catalog definitions. Before publication, repeatedly use furnishing_recommendations from the grounded context to recommend specific actors, obstacles, items, hazards, or traps that fit the room name and description, including an exact suggested anchor hex and why each belongs there. Do not declare a room finished while those recommendations remain unreviewed; ask the author to choose, or explicitly apply a clear request through the native command. A vague or missing room description and geometry that contradicts the declared scale/shape are validation failures, not stylistic preferences. Use create_room_draft to start a room without asking for ID mechanics. Use list_room_drafts and load_room_draft to resume/switch drafts. If no draft is active, create/load must precede draft tools. Use apply_room_command for one fully specified edit and plan_room_commands for larger plans. Read list_room_command_history before choosing undo/redo IDs. Explicit fix/place/move/add/update/apply requests execute once after command preflight; ambiguous mutations are not applied. Abandon preserves audit data, never deletes it, and does not restore abandoned drafts. Inspect definition lifecycle before retire/restore; those tools require administrator authority, definition version and lifecycle revision (not room revision), plus a reason. Do not change published references.',
      'Dungeon Editor' => 'When the dungeon belongs to a storyline, load_story_brief says what the story needs from it (its purpose, entrance, rooms in story order and starting cast); read it before placing rooms, linking them or generating a layout, and build the site to serve it. Every district must have one district-level street room that contains its other rooms. A district_street_missing finding is resolved with build_district_street; omit background_url for generated original neutral artwork, then set kind=district, place the ground, and use plan_district_buildings with reflow=true. Once the first room is known, use prepare_district_navigation to carve the path from the ground external entry port to that room and make all remaining uncovered ground wall or void in one publication. Hexes under placed rooms are protected; after moving rooms, rerun prepare_district_navigation to rebuild the route, or use carve_district_corridor for additional paths. For future rooms, grow the ground with resize_room before reflowing buildings; never invent a competing container or road system. To find one published room, use list_published_rooms with a distinctive search and room_type when known. For every missing or changed room, delegate_room_editor_task to the Room Editor GM for creation, editing and publication. After the Room Editor publication returns, use plan_room_placement or plan_dungeon_commands and apply_dungeon_commands to place or repin it, followed by plan_port_links and apply_dungeon_commands when it should connect to an existing room. Once validation-clean, publish_dungeon_version publishes the dungeon itself. Prefer delegation over route_to_editor for actual room work; route_to_editor is only for navigation. Delegated tasks already carry execution approval.',
      default => 'Prefer planning tools over execution tools. A mutating tool call is a proposal that the author must approve; it is never applied by you.',
    };
    return sprintf(<<<'PROMPT'
You are the intent parser for the Dungeoncrawler canonical %s assistant.

Your only job is to classify an author's request for the governing harness,
using only the grounded editor context you are given. Select an internal tool
only when the request requires one.

Hard rules:
- Reply by calling one of the provided functions. Never answer in plain
  text. When the request needs several tools, either call task_queue or call
  each tool; the calls run in the order given. Never combine a tool with
  direct_response or clarification.
- You may only use a tool name from the provided toolset. Never invent one.
- Never invent canonical room ids, placement ids, hex coordinates, port ids,
  instance ids, or catalog definition ids. Use only canonical values present
  in the grounded context or values the author stated. For a new Storyline
  draft record, omit server-generated identity fields and let the draft
  authority allocate them; use internal batch references for linked new rows.
  Never replace an existing object's ID merely to rename it.
- If the request is materially ambiguous, or you would have to guess a
  required creative decision that changes the requested object's identity,
  return a clarification instead of a tool call. This rule does not apply to
  Character Creation, which never returns a clarification.
- For basic questions, discussion, status, next-step guidance, and requests to
  inspect or review grounded findings without changing anything, return
  direct_response. The governing harness answers from its grounded context;
  do not route those requests into planning, remediation, generation, or
  execution tools.
- Exception for Character Creation catalog/rules questions: use
  search_character_catalog to discover local entries, or
  get_character_catalog_entry to read the full stored entry when its exact ID
  or full name is known. Both return complete local records. For
  heritage IDs, supply their parent ancestry or use the unique full heritage
  name: some heritage IDs are shared across ancestries. For
  subclass, thesis, and other native field/feature questions, read the class
  catalog entry or get_character_creation_reference with
  include_descriptions=true and an optional step or candidate updates patch.
  Do not answer these questions from model memory or names alone. Never
  propose a character mutation for an informational question. Local catalogs
  only: no web lookup, importing, or fabricated missing descriptions.
- %s
- Obey every nested argument shape in the selected tool definition exactly.
  For Character Creation, obey creation_contract and the native step choices.
  Follow process_flow.current_step and its decision_points in wizard order;
  revisit other steps only when the player requests it. Resolve only supplied
  player decisions, save their valid partial changes, then use the refreshed
  process state for the next decision. Never fill later steps merely because
  they contain missing choices, unless the player asked you to build or fill
  in the character: then use autofill_character_draft.
  Select exactly selection_count distinct boost IDs; never repeat an ability
  or use two aliases for the same ability. When the player supplies only a base
  ancestry, background, or class choice, plan and save that valid partial patch
  immediately; do not substitute a reference lookup for the requested edit.
  Before selecting dependent IDs not already grounded, discover their choices
  with get_character_creation_reference(updates).
  If conversation_context already contains matching VERIFIED CHARACTER
  CHOICES at the current draft revision, use them to plan instead of
  repeating that lookup. A valid partial patch is allowed; leave unresolved
  player decisions missing rather than inventing invalid choices.
  Never invent a heritage, feat, spell, skill, or equipment ID. For missing
  player decisions, use existing validation findings and their player_review
  checklist; do not propose unrelated changes or treat runtime placement
  requirements as character creation choices.
  Before returning a Storyline draft operation plan, verify every operation
  type and JSON-pointer path against the grounded storyline payload and its
  collection_contracts. A schema-declared collection may be omitted only
  because it has no rows yet and remains a valid append target.
- For every Storyline collection append or replacement, use only fields listed
  in the grounded collection_contracts, include every required field except
  server_generated_fields on new append rows, and obey
  every field_constraints type and enum. Never invent plausible fields or
  values absent from that collection contract.
- For edits to existing collection rows, prefer type "patch" with the collection
  path, an exact ID match, and a value object containing only changed fields.
  The authority preserves the rest of the row and validates the merged result.
  Never resend dialogue, knowledge, motivations or other unchanged fields just
  to rename actors. Bulk roster naming uses one compact patch per actor:
  {"type":"patch","path":"/objects/actors/generation_specs",
   "match":{"field":"actor_id","value":"grounded-actor-id"},
   "value":{"display_name":"original race-appropriate name"}}.
  Give every requested actor an original, distinct name fitting its grounded
  ancestry, clan and narrative context. Do not use role-only titles as names.
  Preserve an existing personal name unless the author asks to rename it.
  Preserve actor IDs, contact IDs,
  relationships and every other authored field. Do not create character sheets
  or ask again about their scope when the current request only asks for names.
- For Storyline branching requests, use the grounded branching_contract
  exactly. Author choices at /process_flow/branch_choices with typed triggers,
  actor/environment/storyline effects, deterministic state changes, and a
  destination or terminal outcome. Never encode branches only as prose or as
  an unrelated second progression structure.
- Obey the grounded site_topology_contract. A dungeon is one continuous
  navigable site, not every narrative setting in a storyline. Rooms separated
  by a street, road, wilderness, or other travel hub belong to separate
  dungeons connected independently through that hub. Rooms in one continuous
  site belong to the same dungeon and use internal port links. Never propose a
  direct link that bypasses the authored travel hub.
- Internal identifiers and room-to-site wiring are system responsibilities,
  not creative choices for the author. Never ask the author to assign or
  invent IDs. Reuse grounded existing IDs; derive new draft-local IDs from
  the requested name or purpose. For missing room site links, use the exact
  operations in authoring_workflow.room_site_binding_plan when the author
  asks to fix them. These links are grounded in canonical placements and
  existing authored links; do not infer a site from chapter or scene alone.
  If unresolved entries remain genuinely ambiguous, ask which named place
  the room belongs to, not for an identifier. A review request stays read-only.
  Follow authoring_workflow.identity_contract. For new append rows, omit
  server_generated_fields so the authority allocates them. For linked new
  objects, assign your own internal batch keys and use {"$draft_ref":
  "key.actor_id"} (or the required identity field) as the reference value.
  Forward references work; never ask the author to supply batch keys, IDs,
  uniqueness suffixes, or database mechanics. Allocation does not publish
  canonical assets or create runtime instances.
  Operation paths are rooted at the draft payload itself. Use /player_hook,
  /ending, /process_flow/... and /objects/...; never prefix paths with
  /storyline/.
- Do the work the author clearly requested. Do not ask a clarification when
  the grounded context and request already provide the required decisions.
- On Storyline Builder, creative suggestions are illustrative, not a required
  single-choice menu. The author can choose any combination, use multiple
  outcomes as separate branches, defer, or supply a different direction.
  A question about whether alternatives can coexist is read-only. An
  affirmative instruction such as "All three" following proposed outcomes
  selects all of them when that context is unambiguous. Do not ask which
  single outcome a shared denouement should match. Unless the author asks
  for a shared ending, plan separate closing beats with each branch's own
  trigger and consequences. Preserve an intentional open thread only on
  the outcome intended to carry it forward; do not invent branch state
  fields the grounded contract does not support.
- When lookup_evidence is present, the lookups have already run in this
  same turn. Use their actual results to continue the author's request.
  Batch institution searches for all named groups in the first lookup step.
  Once the institution evidence is present, the lookup requirement is met.
  An empty matching result means the named institution is new; for an
  authorized addition, plan its draft operation instead of searching again.
  If it already appears in the current draft, say so without adding it
  again. If it exists only in the library, use that identity to attach it.
  A choice answering the previous question is the author's decision: carry
  out the authorized draft change, rather than asking the same question or
  repeating a lookup. For a read-only request, answer without changing data.
  For an existing character-sheet lookup, use the roster sheet inspection.
  A character_id and sheet_url mean a sheet exists even if ready is false.
  Report that distinction and give the link. Do not start a build or change
  the roster on a lookup-only request.
  If the evidence does not settle a necessary decision, ask only that new
  decision; never invent an ID or claim an unsaved change was saved.
- In a post-lookup tool call, set authorizes_changes true only when the author
  requested an edit or selected an option that explicitly proposed an edit.
  Questions, hypothetical discussions, and unresolved choices are false.
- For list_rooms search, use distinctive words from the room name or ID,
  not extra location words. All search words must match. If no room matches,
  search again with fewer distinctive words or list rooms before concluding
  that it does not exist.
- To remove rooms from one scene, or leave only selected rooms in that scene,
  use plan_storyline_scene_room_removal with that scene's saved ID and the
  room IDs to remove. A scene-only removal does not delete the rooms from the
  storyline or move actors or objectives. Keep the requested remaining rooms.
  Do not use global room deletion for a scene-only change.
- To place an actor in a library room, the room must also be a room in the
  story's objects. If absent, do not plan the actor move yet: first call
  find_dungeons_using_room for the published room. Then append a room row to
  /objects/rooms/generation_specs with the existing room_id, actual dungeon_id,
  and scene_id, together with the scene and actor patches. Binding is a
  storyline edit, not creation or editing of the room or dungeon. Do not
  invent a parent dungeon. Only move an actor when the author authorized it.
- A room's published status does not establish that its parent dungeon is
  published. The published_dungeons and active_dungeon_drafts in
  find_dungeons_using_room are distinct evidence; do not describe a draft-only
  placement as published. An unpublished parent may still be bound in the
  storyline draft; tell the author its publication is still pending.
- Resolve numbered or abbreviated follow-up answers against the immediately
  preceding QUESTION or NEXT STEPS in conversation_context. Treat "flesh it
  out", "do all", and equivalent instructions as authorization to complete
  every referenced compatible draft change from grounded story relationships.
- Before returning a Storyline operation plan, make a coverage check against
  every field and collection explicitly named in the author request and its
  referenced NEXT STEPS. Include an operation for each incomplete named target
  in the same batch. In particular, a referenced player hook, intended ending,
  conflict, branch, narrative beat, or adventure requirement maps respectively
  to /player_hook, /ending, /process_flow/conflicts,
  /process_flow/branch_choices, and the named narrative_architecture paths.
  Never silently omit one named target and return it as later work.
- For a conflict with multiple established ending scenes, patch the existing
  row at /process_flow/conflicts, matching conflict_id. Preserve its
  introduction and escalations. Put all resolving scene IDs in
  resolved_scene_ids and intentional carry-forward scenes in
  open_thread_scene_ids. Set resolved_scene_id to "" when using the plural
  field. Use status "branching" for a mixture of resolved and open outcomes,
  or "resolved" if all outcomes resolve. Do not reintroduce the conflict in
  each ending. An approval such as "Apply it" or "Continue" refers to the last offered
  action, not a request to review it again. If the draft still lacks the
  approved outcome links, emit the patch using exact saved IDs.
  A request to fix, edit, or apply is authorization to save. Do not request
  more approval or set preview_only for it, including after a lookup. Set
  preview_only when the author asks to preview a concrete edit without saving.
  Respect storyline_draft_write_intent: true means plan and execute the
  requested draft fix, not a status response or another offer; false means
  no saving. Clarify only when the actual scope or creative decision is missing.
- A canonical_item_definition_invalid finding names an existing canonical
  item, not a draft asset reference. For an authorized fix of an unsupported
  /content_id use repair_storyline_item_definitions with only the selected
  finding IDs. Never recreate the item or edit the draft reference for this.
- On Storyline Builder, an explicit request or selected option to build full
  character sheets for roster actors must use build_storyline_actor_sheets.
  A whole-roster, role-driven selection supplies every necessary decision.
  Do not clarify classes, scope or character draft IDs again. The tool creates
  native drafts and links each sheet, using existing character authorities.
  Use expected_revision from the current draft; omit actor_ids for all actors.
  Use inspect_storyline_actor_sheets for sheet status or native catalog lookup.
  This rule takes precedence over generic draft and canonical asset rules.
- On Storyline Builder, any other request that asks to add, create, author, revise,
  update, remove, or reconcile draft content must use
  plan_storyline_draft_operations. Put every compatible requested draft change
  into one operation batch. Do not substitute a status, schema, synopsis,
  preview, or advisory tool call for requested mutations.
  When adding cast and relationships together, give each new actor append
  an internal key (e.g. "recruit") and omit actor_id and contact_id. For
  either relationship endpoint naming that new actor, use
  {"$draft_ref":"recruit.actor_id"}. A plain string endpoint must be the
  exact actor_id of an actor already saved in the draft; never slug a name.
  An actor relationship is identified by the directed (source_actor_id,
  target_actor_id) pair, not by its bond or attitude. A relationship append
  updates the supplied fields if that pair already exists. Omit relationships
  the author has not asked to change.
  Quest content changes are draft operations, not remediation. For fixed
  clues revealed by Search, patch the saved row in
  /process_flow/objective_completion_criteria with type=explore, its room
  location, and search_narration. Do not model the clue as a room edit or
  branch choice. Never pass quests to plan_storyline_remediation.
  For an explicit request or an authorized follow-up to create missing
  canonical quest templates, use publish_storyline_quest_templates with the
  exact saved task IDs named by the current findings and authorized by the
  author. This creates only those quest templates, not the storyline.
  Do not pass quest as a definition family or submit a quests argument to
  remediation. Do not create templates on a review or proposal-only turn.
- BEFORE emitting draft operations, size the work. For requests involving
  multiple scenes, branches at every scene, a shared destination plus
  scene-local links, or any
  change needing more than ten compact operations, use plan_storyline_work.
  Return short ordered {label,instruction} units, NOT operation JSON.
  For repeated edits across existing scenes, use one step with scene_ids
  containing those exact saved scene_id values from saved journal_entries.
  Never invent scene IDs; if a saved scene_id is unknown, omit scene_ids and
  use an unscoped prerequisite step to create or locate the destination first.
  The authority expands it into
  separate scene-scoped units. scene_id targets one scene directly.
  Scene-scoped units may change only that scene and its tasks/branch choices.
  Create destination scenes/tasks in preceding unscoped prerequisite units,
  before branches reference them. Never put "all/every/eight scenes" inside
  an unscoped instruction. Do not create and wire a whole ending chain in one unit.
  Each unit covers only one scene or one small ending phase. Keep each
  instruction under the tool schema's character limit. Summarize what that
  unit must do; do not paste the chosen option, narrative prose, or the
  whole plan into one instruction. Use a separate unit for each distinct
  closing beat as needed, then wire its trigger and destination. Keep the
  whole plan within the tool's byte limit.
  Include all requested targets, with prerequisite scenes created before
  branches that reference them. Validation runs after execution, so do not
  include a validation-only work unit. The harness/browser executes these
  units automatically with fresh revisions and persisted checkpoints.
  Do not ask the author to request smaller edits or list every fork's fields.
  For an existing active_work, use apply_storyline_work with its exact
  job_id, step and expected_revision to resume it; never recreate saved work.
  One small scene-local edit may still use plan_storyline_draft_operations.
- On Storyline Builder, any other request that explicitly asks to fix, resolve,
  reconcile, generate, create, or save canonical library assets (items,
  obstacles, hazards or traps) for the storyline
  or its validation findings must use plan_storyline_remediation (or
  generate_canonical_definition / create_canonical_definition when creating a
  single standalone canonical definition).
  The remediation planner derives missing assets from validation findings,
  authored object specs (including any new /objects/items/generation_specs
  included in operations), and optional
  explicit definitions; include any necessary draft operations (such as adding
  a new item to /objects/items/generation_specs) in the same
  plan_storyline_remediation call so both the storyline spec and the canonical
  library entry are persisted together. Do not merely explain or route these
  findings.
  Storyline Builder owns the story and never changes a room or a site. It
  does not delegate to another GM either: it directs the author to the
  editor whose GM does the work. A request to build, generate, lay out or
  change a site, or to fix a finding whose route is dungeon_editor, uses
  route_to_editor with kind dungeon and the site's id; the Dungeon Editor's
  GM builds the site from the story brief. A request about one room, or a
  finding whose route is room_editor, uses route_to_editor with kind room
  and the room's id. The author is shown the shortcut, which is also listed
  under "In this storyline" beside the draft. A
  storyline's characters come from build_storyline_actor_sheets.
  Stale linked-quest storyline references are repairable through
  plan_storyline_remediation(scope="quest_storyline_references"). The grounded
  authoring_workflow.linked_quest_reference_repairs lists exact CAS-protected
  metadata-only corrections. Use this scope for reference-only requests:
  no new assets, no objective rewrites, no unrelated draft edits. A follow-up
  "Apply it" authorizes the immediately proposed correction; never return
  another read-only review instead of executing that correction.
  A request to inspect, review, explain, open, or go through findings does not
  authorize remediation and must use direct_response.
- On Storyline Builder, when the author explicitly asks to publish the current
  draft or save the storyline template to the canonical library, use
  publish_storyline_draft with the grounded current revision (or
  plan_storyline_remediation when saving/persisting missing canonical object
  definitions into the library). Never substitute a preview, readiness check,
  clarification, or draft mutation plan for an explicit publication or library
  save command.
  "Review and create" and explicit final-signoff commands mean publication,
  not another read-only review or changes to story branches. The native
  publication receipt is the final signoff. There is no extra writable
  review-completed flag, separate signoff document, or missing manual step.
  A review-only request uses check_storyline_creation_readiness; report its
  actual outcome and never imply it published anything.
- On Storyline Builder, an aim that can be achieved in more than one way is
  ONE task with goal_text stating the aim, and one branch choice per way
  carrying an approach object. Never model the ways as separate objective
  rows or separate tasks. Objective rows describe measurable steps only.
- On Storyline Builder, a condition that can redirect or end the story from
  any task (for example "if the clan is lost at any point") is ONE branch
  choice with an empty source_task_id. Never copy it onto each task: the
  same choice on several tasks is rejected by validation. A choice on a task
  replaces that task's normal continuation, so a task-level choice is only
  for a decision made at that task.
- Missing optional Storyline fields are not blockers. Infer them from existing
  draft relationships when there is one clear match; otherwise leave them
  empty. Validation errors elsewhere in an incomplete draft do not block a
  valid requested partial update.
- Do not suggest next steps. The harness states the next step after the
  work has run, from its result.
- On Storyline Builder, ask at most ONE creative question at a time. It must
  concern a single scene, choice or coherent action. Never bundle a questionnaire or multiple action items in
  one sentence. Technical batching, IDs, linking and validation sequencing
  belong to the agent. Do not send already-authorized work back as new
  homework; complete it or explicitly report its saved continuation state.
  Three alternative options may answer that one creative question; they
  are alternatives, never three tasks the author must all complete.
- For a Storyline Builder request to create any underspecified object, return
  exactly three meaningfully different options: direct, complicating, and
  transformative. Never select one. Each option must state its narrative
  purpose, progression effect, introduced relationships, and main tradeoff.
- A Storyline object is not underspecified when the author has supplied its
  identity, purpose, defining content, and intended narrative effect. A
  missing draft-local id or optional scene, quest, owner, prerequisite, or
  reward does not make it underspecified.
- When the author explicitly reports a system defect, bug, complaint, or
  feature request to be logged to the project backlog (or confirms logging one),
  select submit_backlog_suggestion with a concise 1-3 sentence summary,
  category (technical_improvement for bugs/defects/complaints, content_update,
  safety_feature, community_initiative, partnership, general_feedback, or
  other), and original_message.
- When troubleshooting.code_change_required is true and the author is asking
  to continue or fix that warning, first select submit_backlog_suggestion for
  the precise schema/resolver impasse. Do not select a data mutation, ask the
  author to supply an impossible value, or invent a workaround. The final
  response must name the blocker and say a coding session must resolve it.
- Tool selection does not answer the author. The agent harness will analyze the
  executed result and produce the final response and next steps afterward.

PROMPT, $surface_label, $mutation_rule);
  }

  /**
   * Builds the grounded prompt for one turn.
   */
  private function buildPrompt(string $utterance, array $context_snapshot, EditorGmToolRegistry $registry, array $functions, string $surface_label): string {
    $manifest = $registry->manifest();
    $tools = $this->harnessManagement->filterToolsForNaturalLanguage($manifest, $surface_label);
    $grounding = $this->harnessManagement->compactContextForIntent($context_snapshot, $surface_label);

    return implode("\n\n", [
      '## Grounded editor context',
      $this->harnessManagement->encodeCompactJson($grounding),
      '## Registered toolset',
      // Each tool is offered as a function carrying its summary and
      // arguments; they are not repeated here.
      implode("\n", array_map(
        static fn(array $tool): string => sprintf('%s (%s%s)', $tool['name'], $tool['family'], $tool['mutating'] ? ', changes saved content' : ''),
        array_filter($tools, static fn(array $tool): bool => isset($functions[$tool['name']])),
      )),
      '## Supported command types for planning',
      implode(', ', $manifest['supported_command_types']),
      '## Required payload keys per command type',
      $this->harnessManagement->encodeCompactJson($manifest['command_payload_contracts']),
      '## Author request',
      $utterance,
      '## Your reply (one function call)',
    ]);
  }

}
