<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessInterface;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Editor-scoped GM harness entrypoint.
 *
 * This is the sibling of the runtime GM subsystem, not an extension of it. It
 * orchestrates grounded editor context and explicit tool execution for every
 * registered editor surface (20-gm-harness-extension.md). Every read and write
 * resolves through the surface's own editor authority; the harness itself
 * never touches editor state. Campaign runtime authority is deliberately
 * unreachable from here.
 */
class EditorGmHarnessService implements AgentHarnessInterface {

  public const REQUEST_CONTRACT_VERSION = 'editor-gm-request-v1';
  public const RESPONSE_CONTRACT_VERSION = 'editor-gm-response-v1';
  public const COMMAND_PLAN_CONTRACT_VERSION = 'editor-gm-command-plan-v1';
  public const RESPONSE_OPERATION = 'editor_gm_grounded_response';

  private const ROUTE_FAMILY_BY_TOOL_FAMILY = [
    EditorGmToolDefinition::FAMILY_CONTEXT => 'deterministic_editor_tool',
    EditorGmToolDefinition::FAMILY_VALIDATION => 'deterministic_editor_tool',
    EditorGmToolDefinition::FAMILY_PLANNING => 'deterministic_editor_tool',
    EditorGmToolDefinition::FAMILY_EXECUTION => 'editor_command_execution',
    EditorGmToolDefinition::FAMILY_DEFINITION => 'editor_definition_authority',
  ];

  /**
   * @var array<string, \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface>
   */
  private array $surfaces = [];
  private LoggerInterface $logger;
  protected AgentHarnessManagementService $harnessManagement;

  /**
   * @param iterable<\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface> $surfaces
   *   Every editor surface the harness serves.
   */
  public function __construct(
    iterable $surfaces,
    protected EditorGmIntentParser $intentParser,
    protected ?AIApiService $ai = NULL,
    ?LoggerChannelFactoryInterface $loggerFactory = NULL,
    ?AgentHarnessManagementService $harnessManagement = NULL,
  ) {
    $this->logger = $loggerFactory?->get('dungeoncrawler_content') ?? new NullLogger();
    $this->harnessManagement = $harnessManagement ?? new AgentHarnessManagementService($this->ai);
    foreach ($surfaces as $surface) {
      if (!$surface instanceof EditorGmSurfaceInterface) {
        throw new \LogicException('editor_gm_surface_invalid');
      }
      if (isset($this->surfaces[$surface->id()])) {
        throw new \LogicException(sprintf('editor_gm_surface_duplicate:%s', $surface->id()));
      }
      $this->surfaces[$surface->id()] = $surface;
    }
    if ($this->surfaces === []) {
      throw new \LogicException('editor_gm_surfaces_required');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function agentId(): string {
    return 'editor_gm';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Editor GM Harness';
  }

  /**
   * {@inheritdoc}
   */
  public function scope(): string {
    return AgentHarnessInterface::SCOPE_DRAFT;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultExecutionProfile(): string {
    return AgentHarnessManagementService::PROFILE_INTENT_ROUTING;
  }

  /**
   * {@inheritdoc}
   */
  public function listAgentTools(string $profile = 'default'): array {
    $tools_by_surface = [];
    foreach ($this->surfaces as $surface_id => $surface) {
      $tools_by_surface[$surface_id] = $surface->registry()->manifest();
    }
    $tools_by_surface['shared_harness_tools'] = [
      AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION => $this->harnessManagement->getBacklogSuggestionToolDescriptor(),
    ];
    return $tools_by_surface;
  }

  /**
   * Resolves one registered surface or hard-fails.
   */
  public function surface(string $surface_id): EditorGmSurfaceInterface {
    if (!isset($this->surfaces[$surface_id])) {
      throw new \InvalidArgumentException(sprintf('editor_gm_surface_unsupported:%s', $surface_id));
    }
    return $this->surfaces[$surface_id];
  }

  /**
   * Ids of every registered surface.
   *
   * @return string[]
   */
  public function surfaceIds(): array {
    return array_keys($this->surfaces);
  }

  /**
   * Returns the grounded context snapshot and tool manifest for one draft.
   */
  public function describe(string $surface_id, ?string $draft_id, string $profile = 'editing', array $scope = []): array {
    $surface = $this->surface($surface_id);
    $draft_id = $this->resolveDraftId($surface, $draft_id);
    $context = $this->createContext($surface, $draft_id, $profile, $scope);

    return $this->envelope(
      $surface,
      'editor_context_snapshot',
      $surface->assembler()->assemble($context),
      [],
      $context->validation(),
    );
  }

  /**
   * Executes one editor GM request envelope.
   *
   * The route binds the surface; the envelope's `tool_context.tool_id` must
   * agree with it so a client can never drive one surface's toolset through
   * another surface's endpoint.
   */
  public function handle(string $surface_id, ?string $draft_id, array $request, array $route_scope = []): array {
    $surface = $this->surface($surface_id);
    $draft_id = $this->resolveDraftId($surface, $draft_id);
    [$profile, $intent, $dry_run, $scope] = $this->parseRequest($surface, $draft_id, $request, $route_scope);

    if ($intent['type'] === 'natural_language') {
      return $this->handleUtterance(
        $surface,
        $draft_id,
        $profile,
        $intent['utterance'],
        $scope,
        $intent['author_message'],
        $dry_run,
      );
    }

    return $this->executeTool($surface, $draft_id, $profile, $intent['tool_name'], $intent['arguments'], $dry_run, '', $scope);
  }

  /**
   * Resolves an author utterance into a grounded tool call.
   *
   * Storyline and character requests execute through revision-safe authorities.
   * Other editor surfaces retain explicit approval for mutating tools.
   */
  private function handleUtterance(
    EditorGmSurfaceInterface $surface,
    ?string $draft_id,
    string $profile,
    string $utterance,
    array $scope,
    string $author_message,
    bool $dry_run,
  ): array {
    $context = $this->createContext($surface, $draft_id, $profile, $scope);
    $snapshot = $surface->assembler()->assemble($context);
    $conversation_context = trim((string) ($scope['conversation_context'] ?? ''));
    if ($conversation_context !== '') {
      $snapshot['conversation_context'] = $conversation_context;
    }
    $intent = $this->intentParser->parse($utterance, $snapshot, $surface->registry(), $surface->label());

    if ($intent['type'] === 'clarification') {
      $tool_calls = [$this->describeToolCall('clarification')];
      return $this->envelope(
        $surface,
        'editor_intent_proposal',
        $snapshot,
        [
          'intent' => 'clarification',
          'reasoning' => $intent['reasoning'],
          'question' => $intent['question'],
          'assistant_response' => $intent['question'],
          'options' => array_values((array) ($intent['options'] ?? [])),
          'tool_calls' => $tool_calls,
        ],
        $context->validation(),
        [['level' => 'info', 'text' => $intent['question']]],
        [],
        $tool_calls,
      );
    }
    if ($intent['type'] === 'direct_response') {
      return $this->respondFromManagementContext(
        $surface,
        $snapshot,
        $context->validation(),
        $author_message,
        (string) ($intent['focus'] ?? 'discussion'),
        (string) ($intent['reasoning'] ?? ''),
      );
    }
    if ($intent['type'] === 'task_queue') {
      return $this->executeTaskQueue(
        $surface,
        $draft_id,
        $profile,
        (array) ($intent['tasks'] ?? []),
        (string) ($intent['reasoning'] ?? ''),
        array_values((array) ($intent['next_steps'] ?? [])),
        $scope,
        $author_message,
        $dry_run,
      );
    }

    if ($intent['tool_name'] === AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $intent['tool_name'],
        $intent['arguments'],
        FALSE,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }

    $definition = $surface->registry()->get($intent['tool_name'])->definition();
    if ($surface->id() === CharacterCreationGmSurface::ID && in_array($definition->name, [
      'plan_character_draft_updates', 'apply_character_draft_updates',
    ], TRUE)) {
      return $this->executeTool(
        $surface, $draft_id, $profile, $definition->name,
        $intent['arguments'], $dry_run,
        $intent['reasoning'], $scope,
        array_values((array) ($intent['next_steps'] ?? [])), $author_message,
        apply_character_plan: TRUE,
      );
    }
    if (!$definition->mutating) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $intent['tool_name'],
        $intent['arguments'],
        FALSE,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }
    // Typed actor commands save immediately: the actor authority validates
    // and records them, and a failed contract changes nothing.
    if ($surface->id() === CharacterCreationGmSurface::ID && in_array($definition->name, ['apply_actor_changes', 'autofill_character_draft'], TRUE)) {
      return $this->executeTool(
        $surface, $draft_id, $profile, $definition->name,
        $intent['arguments'], FALSE,
        $intent['reasoning'], $scope,
        array_values((array) ($intent['next_steps'] ?? [])), $author_message,
      );
    }
    if ($surface->id() === StorylineBuilderGmSurface::ID) {
      if (!in_array($definition->name, [
        'publish_storyline_draft',
        'create_canonical_definition',
        'update_canonical_definition',
        'apply_actor_changes',
        'build_storyline_actor_sheets',
        'apply_storyline_work',
      ], TRUE)) {
        throw new \LogicException('storyline_gm_direct_mutation_tool_forbidden');
      }
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $intent['tool_name'],
        $intent['arguments'],
        $dry_run,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }

    $tool_calls = [$this->describeToolCall($definition->name, $intent['arguments'])];
    $proposal_result = [
      'intent' => 'proposed_execution',
      'reasoning' => $intent['reasoning'],
      'next_steps' => array_values((array) ($intent['next_steps'] ?? [])),
      'requires_approval' => TRUE,
      'proposed_execution' => [
        'tool_name' => $definition->name,
        'authority' => $definition->authority,
        'arguments' => $intent['arguments'],
      ],
      'tool_calls' => $tool_calls,
    ];
    if ($author_message !== '') {
      $response = $this->synthesizeGroundedResponse(
        $author_message,
        $definition->name,
        $proposal_result,
        $snapshot,
        $surface->label(),
      );
      $proposal_result['assistant_response'] = $response['answer'];
      $proposal_result['next_steps'] = $response['next_steps'];
    }
    return $this->envelope(
      $surface,
      'editor_intent_proposal',
      $snapshot,
      $proposal_result,
      $context->validation(),
      [[
        'level' => 'warning',
        'text' => sprintf('%s changes saved state and was not run. Approve it explicitly to apply.', $definition->name),
      ]],
      [],
      $tool_calls,
    );
  }

  /**
   * Executes a dependency-ordered task queue serially with time-budget guarding.
   */
  private function executeTaskQueue(
    EditorGmSurfaceInterface $surface,
    ?string $draft_id,
    string $profile,
    array $tasks,
    string $overall_reasoning,
    array $overall_next_steps,
    array $scope,
    string $author_message,
    bool $dry_run = FALSE,
  ): array {
    $ordered = $surface->id() === RoomEditorGmSurface::ID
      ? array_values($tasks)
      : $this->harnessManagement->orderTaskQueue($tasks);
    $initial_draft_id = $draft_id;
    $started_at = microtime(TRUE);
    $accumulated_tool_calls = [
      [
        'tool' => 'task_queue_preprocessor',
        'message' => sprintf('Now queuing %d ordered task(s) for serial execution…', count($ordered)),
      ],
    ];
    $last_envelope = NULL;
    $completed_count = 0;

    foreach ($ordered as $index => $task) {
      if ($index > 0 && (microtime(TRUE) - $started_at) >= AgentHarnessManagementService::DEFAULT_SERIAL_TIME_BUDGET_SECONDS) {
        $remaining = count($ordered) - $index;
        $accumulated_tool_calls[] = [
          'tool' => 'task_queue_budget',
          'message' => sprintf('Time budget reached after %d/%d task(s); %d task(s) deferred to next turn.', $completed_count, count($ordered), $remaining),
        ];
        break;
      }

      $task_tool_name = (string) ($task['tool_name'] ?? '');
      $task_arguments = is_array($task['arguments'] ?? NULL) ? $task['arguments'] : [];
      if ($surface->id() === CharacterCreationGmSurface::ID && $task_tool_name === 'apply_character_draft_updates') {
        $task_tool_name = 'plan_character_draft_updates';
        $task_arguments = ['updates' => $task_arguments['updates']];
      }
      if ($surface->id() === StorylineBuilderGmSurface::ID && array_key_exists('expected_revision', $task_arguments)) {
        $live_context = $this->createContext($surface, $draft_id, $profile, $scope);
        $task_arguments['expected_revision'] = (int) ($live_context->draft()['revision'] ?? $task_arguments['expected_revision']);
      }
      $is_last = ($index === count($ordered) - 1);
      $last_envelope = $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $task_tool_name,
        $task_arguments,
        $dry_run,
        (string) ($task['reasoning'] ?? $overall_reasoning),
        $scope,
        $is_last ? $overall_next_steps : array_values((array) ($task['next_steps'] ?? [])),
        $is_last ? $author_message : '',
        $accumulated_tool_calls,
        apply_character_plan: TRUE,
        require_approval: $surface->id() === RoomEditorGmSurface::ID,
      );
      $accumulated_tool_calls = array_values((array) ($last_envelope['tool_calls'] ?? $accumulated_tool_calls));
      $completed_count++;
      if ($surface->id() === RoomEditorGmSurface::ID) {
        if (array_key_exists('active_draft_id', $last_envelope['tool_result'])) {
          $draft_id = $last_envelope['tool_result']['active_draft_id'];
        }
        if (!empty($last_envelope['tool_result']['requires_approval'])
          || !empty($last_envelope['tool_result']['would_mutate'])) {
          $last_envelope['tool_result']['remaining_tasks'] = array_slice($ordered, $index + 1);
          break;
        }
      }
      if ($surface->id() === CharacterCreationGmSurface::ID && !empty($last_envelope['tool_result']['blocking_errors'])) {
        break;
      }
    }

    if ($last_envelope === NULL) {
      throw new \RuntimeException('editor_gm_task_queue_empty');
    }
    if ($surface->id() === RoomEditorGmSurface::ID && $draft_id !== $initial_draft_id) {
      $last_envelope['tool_result']['active_draft_id'] = $draft_id;
      $last_envelope['tool_result']['draft'] = $draft_id === NULL
        ? NULL
        : $this->createContext($surface, $draft_id, $profile, $scope)->draft();
    }

    return $last_envelope;
  }

  /**
   * Executes one registered tool against a freshly grounded context.
   */
  private function executeTool(
    EditorGmSurfaceInterface $surface,
    ?string $draft_id,
    string $profile,
    string $tool_name,
    array $arguments,
    bool $dry_run,
    string $reasoning = '',
    array $scope = [],
    array $next_steps = [],
    string $utterance = '',
    array $prior_tool_calls = [],
    bool $apply_character_plan = FALSE,
    bool $require_approval = FALSE,
  ): array {
    if ($tool_name === AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
      $tool_calls = array_values($prior_tool_calls);
      $tool_calls[] = $this->describeToolCall($tool_name, $arguments);
      $context = $this->createContext($surface, $draft_id, $profile, $scope);
      $snapshot = $surface->assembler()->assemble($context);
      $conversation_context = trim((string) ($scope['conversation_context'] ?? ''));
      if ($conversation_context !== '') {
        $snapshot['conversation_context'] = $conversation_context;
      }

      if ($dry_run) {
        return $this->envelope(
          $surface,
          'editor_management_response',
          $snapshot,
          [
            'tool' => $tool_name,
            'dry_run' => TRUE,
            'would_mutate' => FALSE,
            'authority' => 'AgentHarnessManagementService',
            'tool_calls' => $tool_calls,
          ],
          $context->validation(),
          [['level' => 'info', 'text' => sprintf('Dry run: %s was not executed.', $tool_name)]],
          [],
          $tool_calls,
        );
      }

      $submission_args = $arguments;
      if (trim((string) ($submission_args['original_message'] ?? '')) === '' && trim($utterance) !== '') {
        $submission_args['original_message'] = trim($utterance);
      }
      $result = $this->harnessManagement->submitBacklogSuggestion($submission_args, [
        'agent_id' => $this->agentId(),
        'surface_id' => $surface->id(),
        'surface_label' => $surface->label(),
        'draft_id' => $draft_id ?? '',
      ]);
      $result['tool_calls'] = $tool_calls;
      if ($reasoning !== '') {
        $result['reasoning'] = $reasoning;
      }
      $response = $this->synthesizeGroundedResponse(
        $utterance,
        $tool_name,
        $result,
        $snapshot,
        $surface->label(),
      );
      $result['assistant_response'] = $response['answer'];
      $result['next_steps'] = $next_steps !== [] ? $next_steps : $response['next_steps'];

      return $this->envelope(
        $surface,
        'editor_management_response',
        $snapshot,
        $result,
        $context->validation(),
        [['level' => 'success', 'text' => 'Logged defect/feedback to the project backlog.']],
        [],
        $tool_calls,
      );
    }

    $tool = $surface->registry()->get($tool_name);
    $definition = $tool->definition();
    $tool_calls = array_values($prior_tool_calls);
    $tool_calls[] = $this->describeToolCall($definition->name, $arguments);
    $context = $this->createContext($surface, $draft_id, $profile, $scope);
    $snapshot = $surface->assembler()->assemble($context);
    $conversation_context = trim((string) ($scope['conversation_context'] ?? ''));
    if ($conversation_context !== '') {
      $snapshot['conversation_context'] = $conversation_context;
    }

    if ($dry_run && $definition->mutating) {
      return $this->envelope(
        $surface,
        self::ROUTE_FAMILY_BY_TOOL_FAMILY[$definition->family],
        $snapshot,
        [
          'tool' => $definition->name,
          'dry_run' => TRUE,
          'would_mutate' => TRUE,
          'authority' => $definition->authority,
          'tool_calls' => $tool_calls,
        ],
        $context->validation(),
        [['level' => 'info', 'text' => sprintf('Dry run: %s was not executed.', $definition->name)]],
        [],
        $tool_calls,
      );
    }

    if ($require_approval && $definition->mutating) {
      return $this->envelope(
        $surface,
        'editor_intent_proposal',
        $snapshot,
        [
          'intent' => 'proposed_execution',
          'requires_approval' => TRUE,
          'proposed_execution' => [
            'tool_name' => $definition->name,
            'authority' => $definition->authority,
            'arguments' => $arguments,
          ],
          'assistant_response' => sprintf('%s is ready for approval; no changes have been applied for this task.', $definition->name),
          'tool_calls' => $tool_calls,
        ],
        $context->validation(),
        [],
        [],
        $tool_calls,
      );
    }

    $result = $tool->execute($arguments, $context);
    if ($surface->id() === RoomEditorGmSurface::ID && array_key_exists('active_draft_id', $result)) {
      $draft_id = $result['active_draft_id'];
      $context = $this->createContext($surface, $draft_id, $profile, $scope);
      $snapshot = $surface->assembler()->assemble($context);
    }
    if (is_array($result['tool_calls'] ?? NULL)) {
      foreach ($result['tool_calls'] as $sub_call) {
        if (is_array($sub_call) && trim((string) ($sub_call['message'] ?? '')) !== '') {
          $tool_calls[] = [
            'tool' => trim((string) ($sub_call['tool'] ?? $definition->name)),
            'message' => trim((string) $sub_call['message']),
          ];
        }
      }
    }
    $result['tool'] = $definition->name;
    $result['dry_run'] = FALSE;
    $result['tool_calls'] = $tool_calls;
    if ($reasoning !== '') {
      $result['reasoning'] = $reasoning;
    }
    // A saved character's next step comes from its validation, not from what
    // the model guessed before the save.
    $validation_owns_next_steps = $surface->id() === CharacterCreationGmSurface::ID
      && in_array($definition->name, ['apply_character_draft_updates', 'autofill_character_draft'], TRUE);
    if ($next_steps !== [] && !$validation_owns_next_steps) {
      $result['next_steps'] = $next_steps;
    }
    if ($surface->id() === CharacterCreationGmSurface::ID && !empty($result['blocking_errors'])) {
      $result['intent'] = 'validation_blocked';
      $result['assistant_response'] = "These changes failed validation and were not saved.\n"
        . implode("\n", array_map(static fn(array $issue): string => 'Step ' . $issue['step'] . ', ' . $issue['field'] . ': ' . $issue['message'], $result['blocking_errors']));
      $result['next_steps'] = ['Review the listed fields and their native candidate choices.', 'Ask the GM to revise the proposal or choose valid replacements.'];
      return $this->envelope(
        $surface, 'editor_validation_blocked', $snapshot, $result, $context->validation(),
        [['level' => 'error', 'text' => $result['assistant_response']]], [], $tool_calls,
      );
    }
    $proposed_execution = is_array($result['proposed_execution'] ?? NULL)
      ? $result['proposed_execution']
      : [];
    if ($surface->id() === CharacterCreationGmSurface::ID && $apply_character_plan
      && $definition->name === 'plan_character_draft_updates' && !$dry_run) {
      if (($proposed_execution['tool_name'] ?? '') !== 'apply_character_draft_updates'
        || !is_array($proposed_execution['arguments'] ?? NULL)) {
        throw new \LogicException('character_gm_plan_execution_invalid');
      }
      return $this->executeTool(
        $surface, $draft_id, $profile, $proposed_execution['tool_name'],
        $proposed_execution['arguments'], FALSE, $reasoning, $scope,
        $next_steps, $utterance, $tool_calls,
      );
    }
    if ($surface->id() === CharacterCreationGmSurface::ID
      && $definition->name === 'plan_character_draft_updates' && $dry_run) {
      $result['intent'] = 'preview';
      $result['dry_run'] = TRUE;
      $result['assistant_response'] = 'Preview only. These changes have not been saved.';
      return $this->envelope(
        $surface, self::ROUTE_FAMILY_BY_TOOL_FAMILY[$definition->family],
        $snapshot, $result, $context->validation(),
        [['level' => 'info', 'text' => $result['assistant_response']]], [], $tool_calls,
      );
    }
    if (
      $surface->id() === StorylineBuilderGmSurface::ID
      && !$definition->mutating
      && trim((string) ($proposed_execution['tool_name'] ?? '')) !== ''
      && is_array($proposed_execution['arguments'] ?? NULL)
    ) {
      if (!in_array((string) $proposed_execution['tool_name'], [
        'apply_storyline_draft_operations',
        'apply_storyline_work',
        'apply_storyline_remediation',
        'create_canonical_definition',
        'update_canonical_definition',
      ], TRUE)) {
        throw new \LogicException(sprintf(
          'storyline_gm_proposed_execution_unsupported:%s',
          (string) $proposed_execution['tool_name'],
        ));
      }
      if (
        in_array((string) $proposed_execution['tool_name'], [
          'apply_storyline_remediation',
          'create_canonical_definition',
          'update_canonical_definition',
        ], TRUE)
        && !$this->authorizesStorylineRemediation($utterance)
      ) {
        $response = $this->synthesizeGroundedResponse(
          $utterance,
          'governing_harness_validation_review',
          [
            'current_state' => $snapshot['current_state'] ?? [],
            'validation_summary' => $snapshot['validation_summary'] ?? [],
            'proposed_remediation' => $result,
            'remediation_applied' => FALSE,
          ],
          $snapshot,
          $surface->label(),
        );
        $result['requires_approval'] = TRUE;
        $result['assistant_response'] = $response['answer'];
        $result['next_steps'] = $response['next_steps'];
        return $this->envelope(
          $surface,
          'editor_management_response',
          $snapshot,
          $result,
          $context->validation(),
          [[
            'level' => 'info',
            'text' => 'Canonical remediation was not run because the author requested review rather than execution.',
          ]],
          [],
          $tool_calls,
        );
      }
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        (string) $proposed_execution['tool_name'],
        $proposed_execution['arguments'],
        $dry_run,
        $reasoning,
        $scope,
        $next_steps,
        $utterance,
        $tool_calls,
      );
    }

    if ($definition->mutating) {
      $context = $this->createContext($surface, $draft_id, $profile, $scope);
      $snapshot = $surface->assembler()->assemble($context);
      if ($conversation_context !== '') {
        $snapshot['conversation_context'] = $conversation_context;
      }
    }
    if ($utterance !== '' || $definition->name === 'apply_storyline_work') {
      $response = $this->synthesizeGroundedResponse(
        $utterance,
        $definition->name,
        $result,
        $snapshot,
        $surface->label(),
      );
      $result['assistant_response'] = $response['answer'];
      $result['next_steps'] = $response['next_steps'];
    }

    return $this->envelope(
      $surface,
      self::ROUTE_FAMILY_BY_TOOL_FAMILY[$definition->family],
      $snapshot,
      $result,
      $context->validation(),
      [[
        'level' => 'success',
        'text' => $reasoning !== '' ? $reasoning : sprintf('%s completed.', $definition->name),
      ]],
      $this->extractCommandPlan($surface, $result),
      $tool_calls,
    );
  }

  /**
   * Answers basic questions from the management layer's grounded snapshot.
   */
  private function respondFromManagementContext(
    EditorGmSurfaceInterface $surface,
    array $snapshot,
    array $validation,
    string $utterance,
    string $focus,
    string $reasoning,
  ): array {
    $tool_calls = [$this->describeToolCall('governing_harness_context', ['focus' => $focus])];
    $evidence = [
      'focus' => $focus,
      'current_state' => $snapshot['current_state'] ?? [],
      'dungeon' => $snapshot['dungeon'] ?? [],
      'room' => $snapshot['room'] ?? [],
      'validation_summary' => $snapshot['validation_summary'] ?? [],
      'publication' => $snapshot['publication'] ?? [],
      'authority_boundary' => $snapshot['authority_boundary'] ?? [],
      'authored_objects' => $snapshot['storyline']['objects'] ?? [],
    ];
    $response = $this->synthesizeGroundedResponse(
      $utterance,
      'governing_harness_context',
      $evidence,
      $snapshot,
      $surface->label(),
    );
    return $this->envelope(
      $surface,
      'editor_management_response',
      $snapshot,
      [
        'intent' => 'direct_response',
        'focus' => $focus,
        'reasoning' => $reasoning,
        'assistant_response' => $response['answer'],
        'next_steps' => $response['next_steps'],
        'tool_calls' => $tool_calls,
      ],
      $validation,
      [['level' => 'info', 'text' => 'Answered from the governing harness context without subsystem execution.']],
      [],
      $tool_calls,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function describeToolCall(string $tool_name, array $arguments = []): array {
    return $this->harnessManagement->describeToolCall($tool_name, $arguments);
  }

  /**
   * Maps an Editor GM tool name and arguments to a human-readable "Now ..." line.
   */
  private function describeToolCallMessage(string $tool_name, array $arguments = []): string {
    return (new AgentHarnessManagementService())->describeToolCallMessage($tool_name, $arguments);
  }

  /**
   * Requires explicit author authorization before canonical generation runs.
   */
  private function authorizesStorylineRemediation(string $utterance): bool {
    $normalized = mb_strtolower(trim($utterance));
    $normalized = preg_replace('/[^\p{L}\p{N}\s\']+/u', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? $normalized;
    $normalized = preg_replace('/^(?:\d+\s+)+/u', '', $normalized) ?? $normalized;
    if (preg_match('/\b(do not|don\'t|dont|never)\b(?:\s+\w+){0,3}\s+\b(apply|fix|resolve|remediate|repair|correct|generate|create|build|execute|run|proceed|publish|persist|commit|save)\b/u', $normalized) === 1) {
      return FALSE;
    }
    if (in_array($normalized, [
      'do it',
      'proceed',
      'apply it',
      'apply that',
      'fix it',
      'lets update the base name',
      "let's update the base name",
      'align the linked quest references',
      'apply them',
      'fix them',
      'resolve them',
      'generate them',
      'create them',
      'execute it',
      'execute them',
      'run it',
      'run them',
      'publish it',
      'publish them',
      'persist it',
      'persist them',
      'commit it',
      'commit them',
      'save it',
      'save them',
      'try again',
      'lets try again',
      'let\'s try again',
      'yes',
      'yep',
      'ok',
      'okay',
      'go ahead',
      'approve',
      'approved',
    ], TRUE)) {
      return TRUE;
    }
    if (preg_match('/\b(requesting|request|authorize|authorizing|approve|approving)\s+(?:the\s+|this\s+|that\s+)?(execution|remediation|publication|creation|commit|persistence)\b/u', $normalized) === 1) {
      return TRUE;
    }
    if (preg_match('/\b(try\s+again|get\s+(?:it|them|this|that|the\b).*?\b(?:published|persisted|committed|created|generated|remediated|fixed|resolved))\b/u', $normalized) === 1) {
      return TRUE;
    }
    if (preg_match('/\b(do|proceed|apply|fix|fixed|resolve|resolved|remediate|remediated|repair|correct|generate|create|build|execute|run|publish|published|persist|persisted|commit|committed|save|saved)\b(?:\s+\w+){0,4}\s+\b(it|them|this|that|now|again)\b/u', $normalized) === 1) {
      return TRUE;
    }
    return preg_match(
      '/\b(fix|fixed|resolve|resolved|remediate|remediated|repair|correct|generate|create|build|apply|execute|run|publish|published|persist|persisted|commit|committed|save|saved|add|added|store|stored)\b.*\b(warning|error|finding|asset|room|definition|item|actor|creature|obstacle|hazard|trap|journal|quest|remediation|canonical|library|template)s?\b/u',
      $normalized
    ) === 1;
  }

  /**
   * Has the agent analyze authoritative tool evidence before answering.
   */
  private function synthesizeGroundedResponse(
    string $utterance,
    string $tool_name,
    array $tool_result,
    array $context_snapshot,
    string $surface_label,
  ): array {
    if ($surface_label === 'Character Creation' && in_array($tool_name, ['apply_character_draft_updates', 'autofill_character_draft'], TRUE)) {
      return [
        'answer' => $tool_result['assistant_response'],
        'next_steps' => $tool_result['next_steps'],
      ];
    }
    $deterministic = $this->harnessManagement->synthesizeDeterministicMutationResponse(
      $tool_name,
      $tool_result,
      $context_snapshot,
      $surface_label,
    );
    if ($deterministic !== NULL) {
      return $deterministic;
    }

    if ($this->ai === NULL) {
      throw new \RuntimeException('editor_gm_response_synthesizer_unavailable');
    }

    $evidence = $tool_result;
    if (($evidence['schema_version'] ?? '') === 'storyline-definition-preview-v1') {
      unset($evidence['definition']);
    }
    $grounding = [
      'draft' => $context_snapshot['draft'] ?? [],
      'current_state' => $context_snapshot['current_state'] ?? [],
      'dungeon' => $context_snapshot['dungeon'] ?? [],
      'room' => $context_snapshot['room'] ?? [],
      'character' => $context_snapshot['character'] ?? [],
      'process_flow' => $context_snapshot['process_flow'] ?? [],
      'creation_contract' => $context_snapshot['creation_contract'] ?? [],
      'authoring_workflow' => [
        'identity_contract' => $context_snapshot['authoring_workflow']['identity_contract'] ?? [],
        'room_site_binding_plan' => $context_snapshot['authoring_workflow']['room_site_binding_plan'] ?? [],
        'linked_quest_reference_repairs' => $context_snapshot['authoring_workflow']['linked_quest_reference_repairs'] ?? [],
        'finalization' => $context_snapshot['authoring_workflow']['finalization'] ?? [],
        'bounded_work' => $context_snapshot['authoring_workflow']['bounded_work'] ?? [],
        'next_step_contract' => $context_snapshot['authoring_workflow']['next_step_contract'] ?? [],
        'branching_contract' => $context_snapshot['authoring_workflow']['branching_contract'] ?? [],
      ],
      'legacy_conversation' => $context_snapshot['legacy_conversation'] ?? [],
      'authority_boundary' => $context_snapshot['authority_boundary'] ?? [],
      'validation_summary' => $context_snapshot['validation_summary'] ?? [],
      'publication' => $context_snapshot['publication'] ?? [],
      'conversation_context' => $context_snapshot['conversation_context'] ?? '',
    ];
    // Status and validation reviews take their next step from saved state.
    // The model is not asked for one, so there is a single source for it.
    // A surface without a loaded draft has no saved state to take one from.
    $harness_owns_next_step = in_array($surface_label, ['Storyline Builder', 'Dungeon Editor', 'Room Editor'], TRUE)
      && is_array($context_snapshot['current_state'] ?? NULL) && (
      $tool_name === 'governing_harness_validation_review'
      || ($tool_name === 'governing_harness_context' && in_array($tool_result['focus'] ?? '', ['status', 'validation_review'], TRUE))
    );
    $execution_options = $this->harnessManagement->resolveExecutionOptions(
      AgentHarnessManagementService::PROFILE_RESPONSE_SYNTHESIS,
      ['system_prompt' => $this->responseSystemPrompt($surface_label, $harness_owns_next_step)],
    );
    // The answer is one required function call, generated against this
    // schema and checked against it again on receipt.
    $answer_schema = self::answerSchema($harness_owns_next_step);
    unset($execution_options['json_object'], $execution_options['response_format']);
    $execution_options['tools'] = [[
      'type' => 'function',
      'function' => [
        'name' => 'answer',
        'description' => 'The answer to the author, as short titled sections.',
        'parameters' => $answer_schema,
        'strict' => TRUE,
      ],
    ]];
    $execution_options['tool_choice'] = ['type' => 'function', 'function' => ['name' => 'answer']];
    $provider_result = $this->ai->invokeModelDirect(
      implode("\n\n", [
        '## Author request',
        trim($utterance),
        '## Selected tool',
        $tool_name,
        '## Authoritative tool result',
        $this->harnessManagement->encodeCompactJson($evidence),
        '## Relevant grounded context',
        $this->harnessManagement->encodeCompactJson($grounding),
        '## Your reply (call the answer function)',
      ]),
      EditorGmIntentParser::MODULE,
      self::RESPONSE_OPERATION,
      [
        'draft_id' => (string) ($context_snapshot['draft']['draft_id'] ?? ''),
        'tool_name' => $tool_name,
      ],
      $execution_options,
    );

    if (!is_array($provider_result) || empty($provider_result['success'])) {
      $this->logger->error('Editor GM grounded response failed: @error', [
        '@error' => is_array($provider_result) ? (string) ($provider_result['error'] ?? 'unknown') : 'malformed provider response',
      ]);
      throw new \RuntimeException('editor_gm_response_model_failed');
    }

    $decoded = $this->decodeAnswerCall($provider_result, $answer_schema);
    $raw_sections = $decoded['sections'] ?? NULL;
    if (!is_array($raw_sections) || $raw_sections === [] || count($raw_sections) > 6) {
      throw new \DomainException('editor_gm_response_sections_invalid');
    }
    $sections = [];
    foreach ($raw_sections as $section) {
      if (!is_array($section)) {
        throw new \DomainException('editor_gm_response_section_invalid');
      }
      $heading = trim((string) ($section['heading'] ?? ''));
      $body = trim((string) ($section['body'] ?? ''));
      if ($heading === '' || $body === '') {
        throw new \DomainException('editor_gm_response_section_incomplete');
      }
      $sections[] = $heading . "\n" . $body;
    }
    $answer = implode("\n\n", $sections);
    if ($harness_owns_next_step) {
      return [
        'answer' => $answer,
        'next_steps' => $this->harnessManagement->nextStepFromSavedState($context_snapshot, $surface_label),
      ];
    }
    if ($surface_label === 'Storyline Builder') {
      AgentHarnessManagementService::assertStorylineNextSteps($decoded['next_steps'] ?? []);
    }
    $next_steps = array_values(array_filter(
      (array) ($decoded['next_steps'] ?? []),
      static fn($step): bool => is_string($step) && trim($step) !== '',
    ));
    if ($surface_label === 'Storyline Builder' && count($next_steps) !== 1) {
      throw new \DomainException('editor_gm_response_next_steps_invalid');
    }

    return [
      'answer' => $answer,
      'next_steps' => array_map('trim', array_slice($next_steps, 0, 3)),
    ];
  }

  /**
   * The answer function's arguments.
   */
  private static function answerSchema(bool $harness_owns_next_step): array {
    $properties = [
      'sections' => [
        'type' => 'array',
        'items' => [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'required' => ['heading', 'body'],
          'properties' => [
            'heading' => ['type' => 'string', 'description' => 'Short heading.'],
            'body' => ['type' => 'string', 'description' => 'Focused evidence-based content.'],
          ],
        ],
      ],
    ];
    if (!$harness_owns_next_step) {
      $properties['next_steps'] = [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'description' => 'Concrete next steps.',
      ];
    }
    return [
      'type' => 'object',
      'additionalProperties' => FALSE,
      'required' => array_keys($properties),
      'properties' => $properties,
    ];
  }

  /**
   * Reads the answer function call, checked against its schema.
   */
  private function decodeAnswerCall(array $provider_result, array $schema): array {
    $call = is_array($provider_result['tool_call'] ?? NULL) ? $provider_result['tool_call'] : [];
    try {
      if (($call['name'] ?? '') !== 'answer') {
        throw new \DomainException('editor_gm_response_function_call_missing');
      }
      try {
        $decoded = json_decode((string) ($call['arguments'] ?? ''), TRUE, 32, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException) {
        throw new \DomainException('editor_gm_response_not_json');
      }
      if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
        throw new \DomainException('editor_gm_response_not_json');
      }
      $findings = (new DefinitionSchemaValidator())->validate($schema, $decoded);
      if ($findings !== []) {
        throw new \DomainException(sprintf(
          'editor_gm_response_schema_invalid:%s:%s',
          (string) ($findings[0]['pointer'] ?? ''),
          (string) ($findings[0]['message'] ?? ''),
        ));
      }
      return $decoded;
    }
    catch (\DomainException $exception) {
      $this->logger->error('Editor GM answer contract rejected: @error; model=@model; function=@function; reply=@reply', [
        '@error' => $exception->getMessage(),
        '@model' => (string) ($provider_result['model_id'] ?? ''),
        '@function' => (string) ($call['name'] ?? '(none)'),
        '@reply' => mb_substr((string) ($call['arguments'] ?? $provider_result['response'] ?? ''), 0, 4000),
      ]);
      throw $exception;
    }
  }

  /**
   * Contract for the harness's final grounded answer.
   */
  private function responseSystemPrompt(string $surface_label, bool $harness_owns_next_step = FALSE): string {
    $next_step_rule = $harness_owns_next_step
      ? <<<'RULE'
- Do not state, suggest or imply a next step, and do not ask the author a
  question. The harness adds the single next step from saved state after
  your answer. Report what the evidence shows and stop.
RULE
      : <<<'RULE'
- On Storyline Builder, return exactly ONE bounded next step. Ask only one
  creative decision at a time, never a list of changes for the author to
  specify. Do not hide multiple action items inside that single step or
  inside the answer's sections. Technical batching is agent-owned; never
  ask the author to break up a request, assign IDs, or repeat saved work.
  For unfinished bounded work, explain the saved progress and offer only
  resuming it. Do not suggest unrelated edits while authorized work remains.
- On other surfaces, return one to three concrete next steps grounded in the result.
RULE;
    return sprintf(<<<'PROMPT'
You are the Dungeoncrawler canonical %s agent.

The governing harness has provided authoritative management context or an
internal tool result. Analyze that evidence and answer the author's actual
request. Tools are internal capabilities, not the conversational interface.

Hard rules:
- Reply by calling the answer function. Never answer in plain text.
- Use only the tool result and grounded context as facts. Never invent content.
- conversation_context records what was said earlier; it is not evidence.
  State a validation rule or finding only when it appears in the tool result
  or validation_summary. An earlier reply that named a rule does not make it
  one.
- Answer directly and explain what the evidence means.
- Never mention tools, tool selection, tool results, schema keys, internal field
  names, or harness mechanics. Translate evidence into natural language.
- Never merely repeat raw output.
- If evidence is insufficient, say exactly what cannot yet be determined.
- Distinguish schema validity from narrative completeness.
- Preserve human authorship; do not make unstated creative choices.
- On Character Creation, present available choices as a numbered list, with one
  option per line. Requested valid edits are saved immediately; never ask for
  approval or suggest an Approve and Run button. Read-only inspection does not
  save anything. Players can revise saved choices later.
- On Character Creation, never ask the player to confirm, approve, or verify
  anything, and never end with a question that only seeks permission. When the
  player handed the decisions to you, report what was chosen as done. Return
  exactly one next step: the single next open decision, or the final review
  when nothing is open.
- On Character Creation, follow process_flow: guide current_step decisions in
  the native wizard order, using only their available choices. Do not jump to
  later missing steps unless the player explicitly requests them. A completed
  build awaits the wizard's final submission; never finalize it yourself.
- On Character Creation, a catalog entry is evidence about its stored rules,
  not proof that this character can select it. Only native wizard choices and
  validation establish eligibility. Explain missing or partial local text
  explicitly: description_source=description_snippet is partial, and fallback
  is not a complete rules description. Never fill gaps from model memory or
  outside websites. Informational lookups do not modify the character.
- On Storyline Builder, an object present in authored_objects.generation_specs
  is explicit intent to create that canonical id. Do not ask whether to repoint
  a missing asset when its exact id has an authored generation spec.
- Repeated room ids with distinct authored chapter, scene, or narrative roles
  are not duplicates unless validation reports a duplicate contract violation.
- Internal identifiers and site wiring are system responsibilities. Never
  tell the author to assign or invent IDs. Explain missing site links using
  room and place names. When authoring_workflow.room_site_binding_plan has
  operations, the existing canonical placements and authored links already
  resolve those entries: do not claim their sites cannot be determined or
  require a creative decision. For read-only reviews, offer applying those
  known links as the next action, not assigning IDs. Only unresolved entries
  require a genuine choice of named site; do not claim a proposed link is saved.
  Follow authoring_workflow.identity_contract when describing the next action.
  New draft IDs are allocated by the authority; the author supplies story
  intent, not IDs, uniqueness suffixes, or internal batch keys. Discuss
  creative choices in names and narrative terms, never as database decisions.
- Compare the author request with the exact persisted changes. Never claim the
  requested scope was completed when a requested change is absent. Never send
  an explicitly requested but unapplied change back as an ordinary next step;
  state that the turn is incomplete instead.
- Organize the answer into two to six short sections with concise headings.
- Keep each section focused and easy to scan. Use at most two short paragraphs
  or a compact newline-separated list in each section.
%s
PROMPT, $surface_label, $next_step_rule);
  }

  /**
   * Lifts a proposed command plan into the response envelope.
   *
   * Plans are surfaced as a separate, explicitly approvable envelope field so
   * the client can never mistake a proposal for something already applied.
   */
  private function extractCommandPlan(EditorGmSurfaceInterface $surface, array $result): array {
    $plan = $result['command_plan'] ?? NULL;
    if (!is_array($plan)) {
      return [];
    }
    if (($plan['schema_version'] ?? NULL) !== self::COMMAND_PLAN_CONTRACT_VERSION) {
      throw new \DomainException('editor_gm_command_plan_schema_version_invalid');
    }
    $steps = $plan['steps'] ?? NULL;
    if (!is_array($steps) || $steps === []) {
      throw new \DomainException('editor_gm_command_plan_empty');
    }
    $supported = $surface->supportedCommandTypes();
    foreach ($steps as $step) {
      $type = (string) ($step['command_type'] ?? '');
      if (!in_array($type, $supported, TRUE)) {
        throw new \DomainException(sprintf('editor_gm_command_plan_type_unsupported:%s', $type));
      }
    }
    return array_values($steps);
  }

  /**
   * Returns one surface's declared toolset without touching draft state.
   */
  public function manifest(string $surface_id): array {
    return $this->surface($surface_id)->registry()->manifest();
  }

  /**
   * Validates and unpacks a request envelope.
   */
  private function parseRequest(EditorGmSurfaceInterface $surface, ?string $draft_id, array $request, array $route_scope = []): array {
    if (($request['schema_version'] ?? NULL) !== self::REQUEST_CONTRACT_VERSION) {
      throw new \InvalidArgumentException('editor_gm_request_schema_version_invalid');
    }

    $tool_context = $request['tool_context'] ?? NULL;
    if (!is_array($tool_context)) {
      throw new \InvalidArgumentException('editor_gm_tool_context_required');
    }
    $tool_id = (string) ($tool_context['tool_id'] ?? '');
    if (!isset($this->surfaces[$tool_id])) {
      throw new \InvalidArgumentException(sprintf('editor_gm_tool_id_unsupported:%s', $tool_id));
    }
    if ($tool_id !== $surface->id()) {
      throw new \InvalidArgumentException(sprintf('editor_gm_tool_id_surface_mismatch:%s', $tool_id));
    }
    $scope = $route_scope;
    if ($surface->scope() === EditorGmSurfaceInterface::SCOPE_SUITE) {
      if (array_key_exists('draft_id', $tool_context)) {
        throw new \InvalidArgumentException(sprintf('editor_gm_draft_not_applicable:%s', $surface->id()));
      }
    }
    elseif ($surface->scope() === EditorGmSurfaceInterface::SCOPE_DEFINITION) {
      if (array_key_exists('draft_id', $tool_context)) {
        throw new \InvalidArgumentException(sprintf('editor_gm_draft_not_applicable:%s', $surface->id()));
      }
      $request_scope = $tool_context['scope'] ?? $route_scope;
      if (!is_array($request_scope)) {
        throw new \InvalidArgumentException('editor_gm_definition_scope_invalid:missing');
      }
      $scope = $request_scope;
    }
    elseif (($tool_context['draft_id'] ?? NULL) !== $draft_id) {
      throw new \InvalidArgumentException('editor_gm_draft_id_mismatch');
    }
    $profile = (string) ($tool_context['validation_profile'] ?? 'editing');
    if (!in_array($profile, $surface->validationProfiles(), TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }

    $intent = $request['intent'] ?? NULL;
    if (!is_array($intent)) {
      throw new \InvalidArgumentException('editor_gm_intent_required');
    }
    $intent_type = (string) ($intent['type'] ?? '');
    if ($intent_type === 'natural_language') {
      $utterance = trim((string) ($intent['utterance'] ?? ''));
      if ($utterance === '') {
        throw new \InvalidArgumentException('editor_gm_utterance_required');
      }
      $author_message = trim((string) ($intent['author_message'] ?? $utterance));
      if ($author_message === '') {
        throw new \InvalidArgumentException('editor_gm_author_message_required');
      }
      $parsed_intent = [
        'type' => 'natural_language',
        'utterance' => $utterance,
        'author_message' => $author_message,
      ];
    }
    elseif ($intent_type === 'tool_call') {
      $tool_name = (string) ($intent['tool_name'] ?? '');
      if ($tool_name === '') {
        throw new \InvalidArgumentException('editor_gm_tool_name_required');
      }
      $arguments = $intent['arguments'] ?? [];
      if (!is_array($arguments)) {
        throw new \InvalidArgumentException('editor_gm_tool_arguments_invalid');
      }
      $parsed_intent = ['type' => 'tool_call', 'tool_name' => $tool_name, 'arguments' => $arguments];
    }
    else {
      throw new \InvalidArgumentException('editor_gm_intent_type_unsupported');
    }

    $options = $request['options'] ?? [];
    if (!is_array($options)) {
      throw new \InvalidArgumentException('editor_gm_options_invalid');
    }

    return [$profile, $parsed_intent, !empty($options['dry_run']), $scope];
  }

  /**
   * Binds the route's draft id to the surface's scope, or refuses.
   */
  private function resolveDraftId(EditorGmSurfaceInterface $surface, ?string $draft_id): ?string {
    if ($surface->id() === RoomEditorGmSurface::ID && $draft_id === NULL) {
      return NULL;
    }
    if ($surface->scope() === EditorGmSurfaceInterface::SCOPE_SUITE || $surface->scope() === EditorGmSurfaceInterface::SCOPE_DEFINITION) {
      if ($draft_id !== NULL) {
        throw new \InvalidArgumentException(sprintf('editor_gm_draft_not_applicable:%s', $surface->id()));
      }
      return NULL;
    }
    if ($draft_id === NULL || $draft_id === '') {
      throw new \InvalidArgumentException(sprintf('editor_gm_draft_required:%s', $surface->id()));
    }
    return $draft_id;
  }

  /**
   * Builds grounded tool context for one surface.
   */
  private function createContext(EditorGmSurfaceInterface $surface, ?string $draft_id, string $profile, array $scope = []): EditorGmToolContext {
    if (!in_array($profile, $surface->validationProfiles(), TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    return $surface->createContext($draft_id, $profile, $scope);
  }

  /**
   * Projects one editor_gm_response envelope.
   */
  private function envelope(
    EditorGmSurfaceInterface $surface,
    string $route_family,
    array $context_snapshot,
    array $tool_result,
    ?array $validation,
    array $messages = [],
    array $command_plan = [],
    array $tool_calls = [],
  ): array {
    if ($surface->id() === StorylineBuilderGmSurface::ID) {
      AgentHarnessManagementService::assertStorylineNextSteps($tool_result['next_steps'] ?? []);
      foreach (['question', 'assistant_response'] as $field) {
        if (is_string($tool_result[$field] ?? NULL)) {
          StorylineAuthoringDraftService::assertNoIdAssignmentRequest($tool_result[$field]);
        }
      }
      foreach ((array) ($tool_result['next_steps'] ?? []) as $step) {
        if (is_string($step)) {
          StorylineAuthoringDraftService::assertNoIdAssignmentRequest($step);
        }
      }
    }
    if ($surface->id() === CharacterCreationGmSurface::ID && is_array($tool_result['next_steps'] ?? NULL)) {
      // One decision at a time on the character wizard.
      $tool_result['next_steps'] = array_slice(array_values($tool_result['next_steps']), 0, 1);
    }
    return [
      'schema_version' => self::RESPONSE_CONTRACT_VERSION,
      'tool_id' => $surface->id(),
      'route_family' => $route_family,
      'context_snapshot' => $context_snapshot,
      'tool_result' => $tool_result,
      'tool_calls' => array_values($tool_calls),
      'command_plan' => $command_plan,
      'validation' => $validation,
      'messages' => $messages,
      'errors' => [],
    ];
  }

}
