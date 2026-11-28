<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\RepairStorylineItemDefinitionsTool;
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
  private const APPROVE_REMEDIATION = 'Apply this remediation batch.';
  public const RESPONSE_CONTRACT_VERSION = 'editor-gm-response-v1';
  public const COMMAND_PLAN_CONTRACT_VERSION = 'editor-gm-command-plan-v1';
  public const RESPONSE_OPERATION = 'editor_gm_grounded_response';
  private const ANSWER_ATTEMPTS = 2;

  private const STORYLINE_DIRECT_MUTATIONS = [
    'publish_storyline_draft',
    'publish_storyline_quest_templates',
    'repair_storyline_item_definitions',
    'create_canonical_definition',
    'update_canonical_definition',
    'apply_actor_changes',
    'build_storyline_actor_sheets',
    'bind_library_characters',
    'apply_storyline_work',
  ];
  private const DIRECT_EDITOR_FINALIZATIONS = [
    'publish_room_version',
    'publish_dungeon_version',
  ];
  private const DIRECT_EDITOR_COMMANDS = [
    'apply_room_command',
    'apply_room_commands',
  ];

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
    [$utterance, $author_message] = $this->operativeAuthorDirective($utterance, $author_message);
    $context = $this->createContext($surface, $draft_id, $profile, $scope);
    $snapshot = $surface->assembler()->assemble($context);
    $district_navigation = $this->roomDistrictNavigationBridge(
      $surface,
      $profile,
      $author_message,
      $context,
      $scope,
    );
    if ($district_navigation !== NULL) {
      return $district_navigation;
    }
    if ($surface->id() === StorylineBuilderGmSurface::ID) {
      $scope['storyline_draft_write_intent'] = $snapshot['storyline_draft_write_intent'] = $this->storylineDraftWriteIntent($author_message);
      $scope['storyline_request_mode'] = $snapshot['storyline_request_mode'] = $this->storylineRequestMode($author_message, $scope);
      $dry_run = $dry_run || $scope['storyline_draft_write_intent'] === FALSE;
      $troubleshooting = is_array($snapshot['troubleshooting'] ?? NULL) ? $snapshot['troubleshooting'] : [];
      if (!empty($troubleshooting['code_change_required'])
        && in_array($scope['storyline_request_mode'], ['mutation', 'approval', 'orphan_approval'], TRUE)) {
        $finding = (array) ($troubleshooting['finding'] ?? []);
        $handoff = (array) ($troubleshooting['coding_handoff'] ?? []);
        return $this->executeTool(
          $surface,
          $draft_id,
          $profile,
          AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION,
          [
            'summary' => sprintf(
              'Storyline validation %s is blocked for owner %s on route %s. %s',
              (string) ($finding['code'] ?? 'unknown'),
              (string) ($troubleshooting['owner'] ?? 'unknown'),
              (string) ($troubleshooting['route'] ?? 'unknown'),
              (string) ($handoff['scope'] ?? 'Add the missing object-specific troubleshooting capability.'),
            ),
            'category' => 'technical_improvement',
            'original_message' => $author_message,
          ],
          FALSE,
          'Log the schema/resolver impasse as a system defect instead of attempting a data workaround.',
          $scope,
          [],
          $utterance,
        );
      }
      if (in_array($scope['storyline_request_mode'], ['mutation', 'approval'], TRUE)
        && is_array($troubleshooting['editor_route'] ?? NULL)
        && ($troubleshooting['editor_route']['tool'] ?? '') === 'route_to_editor') {
        return $this->executeTool(
          $surface,
          $draft_id,
          $profile,
          'route_to_editor',
          (array) $troubleshooting['editor_route']['arguments'],
          FALSE,
          'Open the owning editor for the selected validation prerequisite.',
          $scope,
          [],
          $author_message,
        );
      }
      $repair = $this->resolveOfferedStorylineNavigationWarning(
        $utterance,
        StorylineBuilderGmToolContext::of($context),
      );
      if ($repair !== NULL) {
        if (isset($repair['plan'])) {
          return $this->executeTool(
            $surface, $draft_id, $profile, 'plan_storyline_draft_operations',
            $repair['plan'], $dry_run, 'Fix the entry room binding using its unique canonical placement.', $scope, [], $author_message,
          );
        }
        return $this->envelope(
          $surface, 'editor_management_response', $snapshot,
          [
            'intent' => 'direct_response',
            'assistant_response' => $repair['answer'],
            'next_steps' => $repair['next_steps'] ?? $this->harnessManagement->nextStepFromSavedState($snapshot, $surface->label()),
          ],
          $context->validation(),
        );
      }
    }
    $approval = $this->normalizeStorylineDraftApproval($utterance);
    if ($surface->id() === StorylineBuilderGmSurface::ID
      && ($approval === 'apply the proposed draft edit' || (isset($scope['pending_storyline_draft_operations'])
        && $this->isStorylineDraftApproval($approval)))) {
      $proposal = $scope['pending_storyline_draft_operations'] ?? NULL;
      if ($proposal === NULL) {
        throw new \DomainException('No pending draft edit. Ask the GM to preview the edit first.');
      }
      if (($proposal['tool_name'] ?? NULL) !== 'apply_storyline_draft_operations'
        || !is_array($proposal['arguments'] ?? NULL)) {
        throw new \LogicException('storyline_gm_pending_draft_operations_invalid');
      }
      if (($proposal['arguments']['expected_revision'] ?? NULL) !== ($snapshot['draft']['revision'] ?? NULL)) {
        throw new \DomainException('The draft changed after this preview. Review a new draft edit before applying it.');
      }
      return $this->executeTool(
        $surface, $draft_id, $profile, 'apply_storyline_draft_operations',
        $proposal['arguments'], $dry_run, 'Applied the approved draft edit.', $scope, [], $author_message,
      );
    }
    if ($surface->id() === StorylineBuilderGmSurface::ID
      && in_array(strtolower(rtrim(trim($utterance), '.!')), ['apply this remediation batch', 'apply this batch', 'apply the batch'], TRUE)) {
      $proposal = $scope['pending_storyline_remediation'] ?? NULL;
      if (($proposal['tool_name'] ?? NULL) !== 'apply_storyline_remediation'
        || !is_array($proposal['arguments'] ?? NULL)) {
        throw new \DomainException('No pending remediation batch. Ask the GM to preview one first.');
      }
      if (($proposal['arguments']['expected_revision'] ?? NULL) !== ($snapshot['draft']['revision'] ?? NULL)) {
        throw new \DomainException('The draft changed after this preview. Review a new remediation batch before applying it.');
      }
      return $this->executeTool(
        $surface, $draft_id, $profile, 'apply_storyline_remediation',
        $proposal['arguments'], $dry_run, 'Applied the approved remediation batch.', $scope, [], $utterance,
      );
    }
    if ($surface->id() === StorylineBuilderGmSurface::ID && $surface->registry()->has('repair_storyline_item_definitions')
      && ($scope['storyline_draft_write_intent'] ?? NULL) !== FALSE) {
      $item_ids = $this->resolveCanonicalItemRepair($author_message, $snapshot);
      if ($item_ids !== []) {
        return $this->executeTool(
          $surface, $draft_id, $profile, 'repair_storyline_item_definitions',
          ['expected_revision' => $snapshot['draft']['revision'], 'item_ids' => $item_ids],
          $dry_run, 'Remove only the verified duplicate registry IDs from the selected canonical items.', $scope, [], $author_message,
        );
      }
    }
    if (
      $surface->id() === 'definition_editor'
      && $this->isExplicitApplyDirective($author_message)
      && is_array($scope['pending_definition_execution'] ?? NULL)
    ) {
      $proposal = $scope['pending_definition_execution'];
      $tool_name = trim((string) ($proposal['tool_name'] ?? ''));
      $arguments = is_array($proposal['arguments'] ?? NULL) ? $proposal['arguments'] : [];
      if ($tool_name === '' || !$surface->registry()->has($tool_name) || $arguments === []) {
        throw new \LogicException('editor_gm_pending_definition_execution_invalid');
      }
      if (!$surface->registry()->get($tool_name)->definition()->mutating) {
        throw new \LogicException('editor_gm_pending_definition_execution_nonmutating');
      }
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $tool_name,
        $arguments,
        $dry_run,
        'Applied the explicitly approved pending definition proposal.',
        $scope,
        [],
        $author_message,
      );
    }
    if (
      in_array($surface->id(), [RoomEditorGmSurface::ID, DungeonEditorGmSurface::ID], TRUE)
      && $this->isExplicitApplyDirective($author_message)
      && is_array($scope['pending_editor_execution'] ?? NULL)
    ) {
      $proposal = $scope['pending_editor_execution'];
      $tool_name = trim((string) ($proposal['tool_name'] ?? ''));
      $arguments = is_array($proposal['arguments'] ?? NULL) ? $proposal['arguments'] : [];
      if ($tool_name === '' || !$surface->registry()->has($tool_name) || $arguments === []) {
        throw new \LogicException('editor_gm_pending_execution_invalid');
      }
      $definition = $surface->registry()->get($tool_name)->definition();
      if (!$definition->mutating) {
        throw new \LogicException('editor_gm_pending_execution_nonmutating');
      }
      $expected_revision = $arguments['expected_revision'] ?? NULL;
      if ($expected_revision !== NULL
        && (int) $expected_revision !== (int) ($snapshot['draft']['revision'] ?? -1)) {
        throw new \DomainException('The draft changed after this proposal. Review a new proposal before applying it.');
      }
      $preflight = $this->preflightRoomMutation($surface, $tool_name, $arguments, $context);
      if ($preflight !== NULL) {
        return $this->envelope(
          $surface,
          'editor_validation_blocked',
          $snapshot,
          $preflight,
          $context->validation(),
          [['level' => 'error', 'text' => $preflight['assistant_response']]],
          [],
          [],
        );
      }
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $tool_name,
        $arguments,
        $dry_run,
        'Applied the explicitly approved pending editor proposal.',
        $scope,
        [],
        $author_message,
      );
    }
    if (isset($scope['lookup_evidence'])) {
      $snapshot['lookup_evidence'] = $scope['lookup_evidence'];
    }
    $revert_plan = $this->roomPublishedBaseRevertPlan(
      $surface,
      $draft_id,
      $profile,
      $author_message,
    );
    if ($revert_plan !== NULL) {
      if (($revert_plan['no_changes'] ?? FALSE) === TRUE) {
        $tool_calls = [$this->describeToolCall('load_published_snapshot')];
        return $this->envelope(
          $surface,
          'deterministic_editor_tool',
          $snapshot,
          [
            'assistant_response' => 'The active draft already matches its exact published base version. No draft change was needed.',
            'next_steps' => [],
            'changed' => FALSE,
            'tool_calls' => $tool_calls,
          ],
          $context->validation(),
          [['level' => 'info', 'text' => 'Published base already loaded.']],
          [],
          $tool_calls,
        );
      }
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        'apply_room_commands',
        $revert_plan,
        $dry_run,
        'Restore the active room draft to its published base without losing command audit history.',
        $scope,
        [],
        $author_message,
      );
    }
    $street_plan = $this->roomStreetFootprintCommandPlan(
      $surface,
      $draft_id,
      $profile,
      $author_message,
    );
    if ($street_plan !== NULL) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        'apply_room_commands',
        $street_plan,
        $dry_run,
        'Reshape the district street into a long band while preserving its entrance.',
        $scope,
        [],
        $author_message,
      );
    }
    $furnishing_lookups = $this->roomFurnishingLookupTasks($surface, $author_message);
    if ($furnishing_lookups !== []) {
      return $this->continueStorylineLookups(
        $surface,
        $draft_id,
        $profile,
        $furnishing_lookups,
        $utterance,
        $author_message,
        $scope,
        $dry_run,
      );
    }
    $conversation_context = trim((string) ($scope['conversation_context'] ?? ''));
    if ($conversation_context !== '') {
      $snapshot['conversation_context'] = $conversation_context;
    }
    $intent = $this->roomCanonicalDefinitionRequest($surface, $author_message, $context)
      ?? $this->intentParser->parse($utterance, $snapshot, $surface->registry(), $surface->label());

    if (($intent['existing_definition'] ?? FALSE) === TRUE) {
      $load = $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        'load_canonical_definition',
        (array) $intent['arguments'],
        FALSE,
        (string) ($intent['reasoning'] ?? ''),
        $scope,
        [],
        '',
      );
      $entry = (array) ($load['tool_result']['entry'] ?? []);
      $name = trim((string) ($entry['label'] ?? $entry['name'] ?? $intent['arguments']['definition_id']));
      $load['tool_result']['assistant_response'] = sprintf(
        "%s already exists\nThe %s definition '%s' is already present in the canonical catalog, so no duplicate was created. It is ready to place in this room.",
        $name,
        (string) $intent['arguments']['family'],
        (string) $intent['arguments']['definition_id'],
      );
      $load['tool_result']['next_steps'] = ['Place the existing definition in the current room, or ask the Room GM to choose an available hex.'];
      return $load;
    }

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
      $lookups = in_array($surface->id(), [
        StorylineBuilderGmSurface::ID,
        DungeonEditorGmSurface::ID,
        RoomEditorGmSurface::ID,
      ], TRUE)
        ? $this->leadingLookupTasks($surface, (array) $intent['tasks']) : [];
      if ($lookups !== []) {
        return $this->continueStorylineLookups($surface, $draft_id, $profile, $lookups, $utterance, $author_message, $scope, $dry_run);
      }
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
    $preflight = $this->preflightRoomMutation(
      $surface,
      $definition->name,
      (array) $intent['arguments'],
      $context,
    );
    if ($preflight !== NULL) {
      return $this->envelope(
        $surface,
        'editor_validation_blocked',
        $snapshot,
        $preflight,
        $context->validation(),
        [['level' => 'error', 'text' => $preflight['assistant_response']]],
        [],
        [],
      );
    }
    if (in_array($surface->id(), [StorylineBuilderGmSurface::ID, DungeonEditorGmSurface::ID, RoomEditorGmSurface::ID], TRUE)
      && !$definition->mutating && $definition->family === EditorGmToolDefinition::FAMILY_CONTEXT) {
      return $this->continueStorylineLookups($surface, $draft_id, $profile, [$intent], $utterance, $author_message, $scope, $dry_run);
    }
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
        $dry_run,
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
    if ($surface->id() === DungeonEditorGmSurface::ID && $definition->name === 'delegate_room_editor_task') {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $definition->name,
        $intent['arguments'],
        $dry_run,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }
    if ($surface->id() === DungeonEditorGmSurface::ID
      && !empty($scope['delegated_authorization'])
      && $definition->mutating) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $definition->name,
        $intent['arguments'],
        $dry_run,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }
    if ($surface->id() === RoomEditorGmSurface::ID && !empty($scope['delegated_authorization'])) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $definition->name,
        $intent['arguments'],
        $dry_run,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }
    if (
      in_array($surface->id(), [DungeonEditorGmSurface::ID, RoomEditorGmSurface::ID], TRUE)
      && $definition->mutating
      && $this->isDirectEditorMutationDirective($author_message)
    ) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $definition->name,
        $intent['arguments'],
        FALSE,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }
    if ($surface->id() === StorylineBuilderGmSurface::ID) {
      if (!in_array($definition->name, self::STORYLINE_DIRECT_MUTATIONS, TRUE)) {
        throw new \LogicException('storyline_gm_direct_mutation_tool_forbidden');
      }
      if (!$this->authorizesDirectStorylineMutation($scope, $snapshot, $definition->name)) {
        return $this->envelope(
          $surface,
          'editor_management_response',
          $snapshot,
          [
            'intent' => 'direct_response',
            'assistant_response' => 'No changes were made. A mutating tool cannot be selected from read-only or stale assistant text without a current write request or structured pending action.',
            'next_steps' => $this->harnessManagement->nextStepFromSavedState($snapshot, $surface->label()),
          ],
          $context->validation(),
        );
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
    if (in_array($definition->name, self::DIRECT_EDITOR_FINALIZATIONS, TRUE)
      && $this->isExplicitPublicationDirective($author_message)) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $definition->name,
        $intent['arguments'],
        $dry_run,
        $intent['reasoning'],
        $scope,
        array_values((array) ($intent['next_steps'] ?? [])),
        $author_message,
      );
    }
    if (in_array($definition->name, self::DIRECT_EDITOR_COMMANDS, TRUE)
      && $this->isDirectEditorMutationDirective($author_message)) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        $definition->name,
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
   * Returns the user's final directive, excluding pasted UI transcript text.
   */
  private function operativeAuthorDirective(string $utterance, string $author_message): array {
    $message = trim($author_message);
    $lines = preg_split('/\R/u', $message) ?: [];
    if (!array_filter($lines, static fn(string $line): bool => preg_match(
      '/^(?:Next steps?|Assistant connected to .* GM tools \\(\\d+\\)\\.?)$/i',
      trim($line),
    ) === 1)) {
      return [$utterance, $message];
    }

    $directive = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool =>
      $line !== ''
      && $line !== 'Next step'
      && $line !== 'Next steps'
      && !preg_match('/^Assistant connected to .* GM tools \\(\\d+\\)\\.?$/i', $line)
    ));
    $directive = trim((string) end($directive));
    if ($directive === '') {
      return [$utterance, $message];
    }
    return [$directive, $directive];
  }

  private function isExplicitPublicationDirective(string $author_message): bool {
    $message = mb_strtolower(trim($author_message));
    if ($message === '') {
      return FALSE;
    }
    return preg_match(
      '/(?:^|\\b)(?:publish (?:it|this)|publish (?:the |this )?(?:room|dungeon|draft|version)|review and (?:publish|create)|finalize (?:the )?(?:room|dungeon)|publish now)(?:\\b|$)/u',
      $message,
    ) === 1;
  }

  private function isExplicitApplyDirective(string $author_message): bool {
    $message = mb_strtolower(trim($author_message));
    if ($message === '') {
      return FALSE;
    }

    return preg_match(
      '/^(?:apply\\b.*|(?:yes,?\\s*)?(?:go ahead|confirm|execute it|do it))(?:[.!])?$/u',
      $message,
    ) === 1;
  }

  private function preflightRoomMutation(
    EditorGmSurfaceInterface $surface,
    string $tool_name,
    array $arguments,
    EditorGmToolContext $context,
  ): ?array {
    if ($surface->id() !== RoomEditorGmSurface::ID) {
      return NULL;
    }
    $commands = match ($tool_name) {
      'apply_room_command' => [[
        'type' => (string) ($arguments['type'] ?? ''),
        'payload' => is_array($arguments['payload'] ?? NULL) ? $arguments['payload'] : [],
      ]],
      'apply_room_commands' => is_array($arguments['commands'] ?? NULL) ? $arguments['commands'] : [],
      default => NULL,
    };
    if ($commands === NULL || $commands === []) {
      return NULL;
    }
    $room_context = RoomEditorGmToolContext::of($context);
    $simulation = $room_context->roomEditor->simulateCommands(
      $room_context->draftId,
      $commands,
      $room_context->validationProfile,
    );
    if (($simulation['applies_cleanly'] ?? FALSE) === TRUE) {
      return NULL;
    }
    $failed = array_values(array_filter(
      (array) ($simulation['steps'] ?? []),
      static fn(array $step): bool => ($step['applies'] ?? TRUE) === FALSE,
    ));
    $error = (string) ($failed[0]['error'] ?? 'room_command_preflight_failed');
    return [
      'intent' => 'validation_blocked',
      'assistant_response' => sprintf(
        'I did not save this room change because the command is invalid in the current draft: %s. The draft is unchanged.',
        $error,
      ),
      'next_steps' => [
        'Ask for a new plan that uses a boundary edge, valid target, and current draft revision.',
      ],
      'validation' => $simulation['validation'] ?? [],
      'command_preflight' => $simulation,
      'tool' => $tool_name,
      'changed' => FALSE,
    ];
  }

  private function isDirectEditorMutationDirective(string $author_message): bool {
    $message = mb_strtolower(trim($author_message));
    if ($message === '') {
      return FALSE;
    }

    return preg_match(
      '/\\b(?:fix|resolve|address|move|place|put|add|remove|delete|rotate|set|update|change|create|generate|build|furnish|apply|go ahead|do it|select|choose|option\\s+\\d+)\\b/u',
      $message,
    ) === 1
      || preg_match('/\\bwork\\s+(?:through|on)\\s+(?:the\\s+)?warnings?\\b/u', $message) === 1;
  }

  private function roomDistrictNavigationBridge(
    EditorGmSurfaceInterface $surface,
    string $profile,
    string $author_message,
    EditorGmToolContext $context,
    array $scope,
  ): ?array {
    if ($surface->id() !== RoomEditorGmSurface::ID
      || !$this->isDirectEditorMutationDirective($author_message)
      || !preg_match('/\\b(?:flood|waterlog|waterlogged|shallow-water|cistern)\\b/iu', $author_message)) {
      return NULL;
    }
    $room_context = RoomEditorGmToolContext::of($context);
    $room = $room_context->room();
    if (($room['room_type'] ?? '') !== 'street') {
      return NULL;
    }
    $parents = $room_context->roomEditor->parentDungeons($room_context->roomId());
    if (count($parents) !== 1) {
      throw new DomainException('district_navigation_parent_ambiguous');
    }
    $dungeon_surface = $this->surface(DungeonEditorGmSurface::ID);
    $dungeon_draft = $room_context->roomEditor->parentDungeonDraft($room_context->roomId());
    $dungeon_context = DungeonEditorGmToolContext::of($dungeon_surface->createContext(
      (string) $dungeon_draft['draft_id'],
      $profile,
    ));
    $model = $dungeon_context->model();
    $street = array_values(array_filter(
      (array) ($model['placements'] ?? []),
      static fn(array $placement): bool => ($placement['room_id'] ?? '') === $room_context->roomId(),
    ))[0] ?? NULL;
    $first_room = array_values(array_filter(
      (array) ($model['placements'] ?? []),
      static fn(array $placement): bool => !empty($placement['is_level_entrance'])
        && ($placement['room']['room_type'] ?? '') !== 'street',
    ))[0] ?? NULL;
    if (!is_array($street) || !is_array($first_room)) {
      throw new DomainException('district_navigation_endpoints_unresolved');
    }
    return $this->executeTool(
      $dungeon_surface,
      (string) $dungeon_draft['draft_id'],
      $profile,
      'prepare_district_navigation',
      [
        'street_placement_id' => (string) $street['placement_id'],
        'first_room_placement_id' => (string) $first_room['placement_id'],
        'width' => 3,
        'path_terrain' => 'water_shallow',
        'blocked_terrain' => 'wall',
        'reason' => 'Flood the district service lane from the external entry to the first room, retaining wall ground elsewhere.',
      ],
      FALSE,
      'Prepare flooded district navigation through the Dungeon Editor authority.',
      $scope + ['delegated_authorization' => TRUE, 'delegated_from_surface' => RoomEditorGmSurface::ID],
      [],
      $author_message,
    );
  }

  private function storylineRequestMode(string $message, array $scope): string {
    $normalized = $this->normalizeStorylineDraftApproval($message);
    if ($this->isStorylineDraftApproval($normalized)) {
      $pending = array_filter([
        $scope['pending_storyline_draft_operations'] ?? NULL,
        $scope['pending_storyline_remediation'] ?? NULL,
      ], 'is_array');
      return $pending === [] ? 'orphan_approval' : 'approval';
    }
    if ($this->storylineDraftWriteIntent($message) === TRUE) {
      if (preg_match('/\b(?:review and (?:create|publish)|publish|finalize the storyline|final signoff)\b/', $normalized) === 1) {
        return 'mutation';
      }
      return 'mutation';
    }
    if ($this->storylineDraftWriteIntent($message) === FALSE) {
      return 'read_only';
    }
    return 'discussion';
  }

  private function authorizesDirectStorylineMutation(array $scope, array $snapshot, string $tool_name): bool {
    if (($scope['storyline_request_mode'] ?? '') === 'mutation') {
      return TRUE;
    }
    return ($scope['storyline_request_mode'] ?? '') === 'approval'
      && (
        ($snapshot['authoring_workflow']['bounded_work']['active_work'] ?? NULL) !== NULL
        && $tool_name === 'apply_storyline_work'
      );
  }

  /**
   * Binds an item repair request to current findings, not to draft references.
   */
  private function resolveCanonicalItemRepair(string $utterance, array $snapshot): array {
    $request = $this->normalizeStorylineDraftApproval($utterance);
    if ($this->isStorylineDraftApproval($request)) {
      return [];
    }
    if (preg_match('/^(?:please )?(?:fix|repair|resolve) (?:this|that|the) warning$/D', $request) === 1) {
      $items = RepairStorylineItemDefinitionsTool::repairableFindings($snapshot['validation_summary'] ?? []);
      $first = $snapshot['current_state']['first_open_warning'] ?? '';
      return array_keys(array_filter($items, static fn(array $finding): bool =>
        $finding['message'] === $first
      ));
    }
    if (preg_match("/^(?:please |let'?s |resume the saved work: )*(?:fix|repair|remove|strip|clean up|resolve)\\b[^?]*\\b(?:redundant|duplicate|undefined|unsupported|canonical item definitions?|content_id)\\b/u", $request) !== 1) {
      return [];
    }
    $items = RepairStorylineItemDefinitionsTool::repairableFindings($snapshot['validation_summary'] ?? []);
    return array_column(array_filter($items, static function (array $finding) use ($request): bool {
      foreach ([$finding['entity_id'], $finding['entity_label']] as $name) {
        if ($name !== '' && preg_match('/(?<![\p{L}\p{N}_])' . preg_quote(mb_strtolower($name), '/') . '(?![\p{L}\p{N}_])/u', $request) === 1) {
          return TRUE;
        }
      }
      return FALSE;
    }), 'entity_id');
  }

  /**
   * Resolves an offered navigation warning through the authored room binding.
   */
  private function resolveOfferedStorylineNavigationWarning(
    string $utterance,
    StorylineBuilderGmToolContext $context,
  ): ?array {
    if (preg_match('/^(?:please\s+)?(?:fix|resolve|repair)\s+(?:this|that|the)\s+warning(?:\s+please)?[.!]?$/i', trim($utterance)) !== 1) {
      return NULL;
    }
    $offered_warning = (string) ($context->status()['first_open_warning'] ?? '');
    if (preg_match("/^((?:Storyline entry_point primary location|\\[DCV_NAV_ENTRY_ROOM_NOT_IN_DUNGEON\\] entry_dungeon\\.entrance_room_id|\\[DCV_NAV_CONNECTOR_TARGET_ROOM_NOT_IN_DUNGEON\\] progression_connectors\\[0\\]: target_room_id) '([^']+)' is not linked to (?:canonical dungeon|dungeon|target_dungeon_id) '[^']+'\\.)$/", $offered_warning, $match) !== 1) {
      return NULL;
    }
    [, $warning, $room_id] = $match;
    $validation = $context->validation();
    if (!in_array($warning, array_merge($validation['warnings'] ?? [], $validation['errors'] ?? []), TRUE)) {
      return ['answer' => 'I revalidated the current draft. The warning you selected is no longer present. No changes were needed.'];
    }

    $draft = $context->draft();
    if (($draft['payload']['entry_location_id'] ?? '') === $room_id) {
      $binding = $context->drafts->planRoomDungeonBindings($context->draftId, $room_id);
      if ($binding['operations'] !== []) {
        return ['plan' => [
          'expected_revision' => (int) $draft['revision'],
          'operations' => $binding['operations'],
        ]];
      }
    }

    return [
      'answer' => 'The warning is still present, but validation did not establish a safe edit to the authored entry room binding. The entry dungeon and connector are generated, so I did not patch them or change the draft.',
      'next_steps' => ["Review the entry room's placement and the storyline's site binding."],
    ];
  }

  /**
   * Continues an author request after its required read-only lookups.
   */
  private function continueStorylineLookups(
    EditorGmSurfaceInterface $surface,
    ?string $draft_id,
    string $profile,
    array $tasks,
    string $utterance,
    string $author_message,
    array $scope,
    bool $dry_run,
  ): array {
    $evidence = [];
    $tool_calls = [];
    $seen = [];
    while (TRUE) {
      foreach ($tasks as $task) {
        $name = (string) $task['tool_name'];
        $arguments = (array) $task['arguments'];
        $key = $name . ':' . json_encode(self::normalizeLookupArguments($arguments), JSON_THROW_ON_ERROR);
        if (isset($seen[$key]) || count($seen) >= 5) {
          throw new \DomainException('storyline_gm_lookup_continuation_limit:' . $name);
        }
        $seen[$key] = TRUE;
        $result = $this->executeTool(
          $surface, $draft_id, $profile, $name, $arguments, TRUE,
          (string) ($task['reasoning'] ?? ''), $scope, [], '', $tool_calls,
        );
        if (!empty($result['tool_result']['would_mutate'])) {
          throw new \LogicException('storyline_gm_lookup_proposed_execution_forbidden:' . $name);
        }
        $tool_calls = $result['tool_calls'];
        $tool_result = $result['tool_result'];
        unset($tool_result['tool_calls'], $tool_result['reasoning'], $tool_result['next_steps'], $tool_result['dry_run']);
        $evidence[] = ['tool' => $name, 'arguments' => $arguments, 'result' => $tool_result];
      }
      $scope['lookup_evidence'] = $evidence;
      $furnishing_plan = $this->roomFurnishingCommandPlan(
        $surface,
        $draft_id,
        $profile,
        $author_message,
        $evidence,
        (string) ($scope['conversation_context'] ?? ''),
      );
      if ($furnishing_plan !== NULL) {
        if (($furnishing_plan['no_changes'] ?? FALSE) === TRUE) {
          $missing = array_values((array) ($furnishing_plan['missing_concepts'] ?? []));
          $result['tool_result']['assistant_response'] = $missing === []
            ? 'The requested catalog objects are already placed in this room. The draft is unchanged.'
            : sprintf(
              'No catalog-backed changes were needed. The requested catalog entries are already present, and no definitions are available for: %s. The draft is unchanged.',
              implode(', ', $missing),
            );
          $result['tool_result']['next_steps'] = [];
          $result['tool_result']['tool_calls'] = $tool_calls;
          $result['tool_result']['changed'] = FALSE;
          $result['tool_calls'] = $tool_calls;
          return $this->envelope(
            $surface,
            $result['route_family'],
            $result['context_snapshot'],
            $result['tool_result'],
            $result['validation'],
            $result['messages'],
            $result['command_plan'],
            $tool_calls,
          );
        }
        $execution = $this->executeTool(
          $surface,
          $draft_id,
          $profile,
          'apply_room_commands',
          $furnishing_plan,
          $dry_run,
          'Apply the available catalog matches for the explicitly requested room furnishings.',
          $scope,
          [],
          '',
          $tool_calls,
        );
        $placed = array_values((array) ($furnishing_plan['placed_labels'] ?? []));
        $missing = array_values((array) ($furnishing_plan['missing_concepts'] ?? []));
        $label_counts = array_count_values($placed);
        $labels = array_map(
          static fn(string $label): string => $label_counts[$label] > 1
            ? sprintf('%d x %s', $label_counts[$label], $label)
            : $label,
          array_keys($label_counts),
        );
        $execution['tool_result']['assistant_response'] = $dry_run
          ? sprintf(
            'Preview only: would place %d catalog-backed object%s in the room: %s. No draft changes were saved.',
            count($placed),
            count($placed) === 1 ? '' : 's',
            implode(', ', $labels),
          )
          : sprintf(
            'Placed %d catalog-backed object%s in the room: %s. The draft is saved at revision %d.',
            count($placed),
            count($placed) === 1 ? '' : 's',
            implode(', ', $labels),
            (int) ($execution['tool_result']['final_revision'] ?? 0),
          );
        if ($missing !== []) {
          $execution['tool_result']['assistant_response'] .= sprintf(
            ' No catalog definitions were available for: %s.',
            implode(', ', $missing),
          );
        }
        $execution['tool_result']['next_steps'] = $dry_run
          ? ['Turn off Dry Run and repeat the same furnishing request to apply the validated placement plan.']
          : ($missing === []
          ? []
          : ['Add or select an available catalog definition for the unmatched request before publishing.']);
        return $execution;
      }
      $room_route = $this->missingPublishedRoomEditorRoute($surface, $author_message, $evidence);
      if ($room_route !== NULL) {
        if ($room_route !== '') {
          $room_name = ucwords(str_replace('_', ' ', $room_route));
          return $this->executeTool(
            $surface,
            $draft_id,
            $profile,
            'delegate_room_editor_task',
            [
              'room_id' => $room_route,
              'name' => $room_name,
              'task' => sprintf(
                'Create and publish room %s (%s) for the Dungeon Editor. Use its Storyline story brief and the author direction: %s',
                $room_name,
                $room_route,
                $author_message,
              ),
            ],
            FALSE,
            'Delegate the missing canonical room to the Room Editor GM before dungeon placement.',
            $scope,
            [],
            $author_message,
            $tool_calls,
          );
        }
        return $this->executeTool(
          $surface,
          $draft_id,
          $profile,
          'route_to_editor',
          ['kind' => 'room', 'id' => $room_route],
          FALSE,
          'The requested room has no published version; continue in the Room Editor before placement.',
          $scope,
          [],
          $author_message,
          $tool_calls,
        );
      }
      $context = $this->createContext($surface, $draft_id, $profile, $scope);
      $snapshot = $surface->assembler()->assemble($context);
      $snapshot['lookup_evidence'] = $evidence;
      $snapshot['storyline_draft_write_intent'] = $scope['storyline_draft_write_intent'] ?? NULL;
      if (!empty($scope['conversation_context'])) {
        $snapshot['conversation_context'] = $scope['conversation_context'];
      }
      $intent = $this->intentParser->parse($utterance, $snapshot, $surface->registry(), $surface->label());
      if ($intent['type'] === 'direct_response') {
        $answer = $this->synthesizeGroundedResponse($author_message, 'storyline_lookup', ['lookups' => $evidence], $snapshot, $surface->label());
        $result['tool_result']['assistant_response'] = $answer['answer'];
        $result['tool_result']['next_steps'] = $answer['next_steps'];
        $result['tool_result']['tool_calls'] = $tool_calls;
        $result['tool_calls'] = $tool_calls;
        return $this->envelope(
          $surface, $result['route_family'], $result['context_snapshot'], $result['tool_result'],
          $result['validation'], $result['messages'], $result['command_plan'], $tool_calls,
        );
      }
      if ($intent['type'] === 'clarification') {
        $tool_calls[] = $this->describeToolCall('clarification');
        return $this->envelope($surface, 'editor_intent_proposal', $snapshot, [
          'intent' => 'clarification',
          'reasoning' => $intent['reasoning'],
          'question' => $intent['question'],
          'assistant_response' => $intent['question'],
          'options' => $intent['options'],
          'tool_calls' => $tool_calls,
        ], $context->validation(), [['level' => 'info', 'text' => $intent['question']]], [], $tool_calls);
      }
      if ($intent['type'] === 'task_queue') {
        $tasks = $this->leadingLookupTasks($surface, (array) $intent['tasks']);
        if ($tasks !== []) {
          continue;
        }
        $preview = $dry_run || !$this->authorizesEditorLookupChanges(
          $surface,
          $intent,
          $scope,
          $author_message,
        );
        return $this->executeTaskQueue($surface, $draft_id, $profile, (array) $intent['tasks'], (string) $intent['reasoning'], (array) ($intent['next_steps'] ?? []), $scope, $author_message, $preview, $tool_calls);
      }
      $name = (string) $intent['tool_name'];
      if ($name === AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
        return $this->executeTool(
          $surface, $draft_id, $profile, $name, (array) $intent['arguments'],
          $dry_run || ($intent['authorizes_changes'] ?? FALSE) !== TRUE,
          (string) $intent['reasoning'], $scope, [], $author_message, $tool_calls,
        );
      }
      $definition = $surface->registry()->get($name)->definition();
      if (!$definition->mutating && $definition->family === EditorGmToolDefinition::FAMILY_CONTEXT) {
        $tasks = [$intent];
        continue;
      }
      if ($surface->id() === StorylineBuilderGmSurface::ID
        && $definition->mutating && !in_array($name, self::STORYLINE_DIRECT_MUTATIONS, TRUE)) {
        throw new \LogicException('storyline_gm_direct_mutation_tool_forbidden');
      }
      $authorized = $this->authorizesEditorLookupChanges($surface, $intent, $scope, $author_message);
      $execution_scope = $scope;
      if ($surface->id() === 'definition_editor' && $authorized) {
        $execution_scope['definition_write_intent'] = TRUE;
      }
      return $this->executeTool(
        $surface, $draft_id, $profile, $name, (array) $intent['arguments'],
        $dry_run || !$authorized,
        (string) $intent['reasoning'], $execution_scope, (array) ($intent['next_steps'] ?? []), $author_message, $tool_calls,
      );
    }
  }

  private function authorizesStorylineLookupChanges(array $intent, array $scope): bool {
    if (!empty($scope['delegated_authorization'])) {
      return TRUE;
    }
    if (($intent['authorizes_changes'] ?? FALSE) === TRUE) {
      return TRUE;
    }
    if (($scope['storyline_draft_write_intent'] ?? NULL) !== TRUE) {
      return FALSE;
    }
    $tools = ($intent['type'] ?? '') === 'task_queue'
      ? array_column($intent['tasks'], 'tool_name')
      : [$intent['tool_name']];
    return $tools !== [] && array_diff($tools, [
      'plan_storyline_draft_operations', 'plan_storyline_work', 'plan_storyline_remediation',
    ]) === [];
  }

  private function roomCanonicalDefinitionRequest(
    EditorGmSurfaceInterface $surface,
    string $author_message,
    ?EditorGmToolContext $context = NULL,
  ): ?array {
    if ($surface->id() !== RoomEditorGmSurface::ID) {
      return NULL;
    }
    $message = trim((string) preg_replace('/\s+/', ' ', $author_message));
    if (preg_match(
      '/^(?:please\s+)?(?:create|generate|build|make)\s+(?:an?\s+|the\s+)?(.+?)(?:\s+as\s+(?:an?\s+)?(obstacle|item|hazard|trap|actor|object))?[.!]?$/iu',
      $message,
      $matches,
    ) !== 1) {
      return NULL;
    }
    $object_name = trim((string) $matches[1]);
    $explicit_family = strtolower((string) ($matches[2] ?? ''));
    if ($object_name === ''
      || ($explicit_family === '' && preg_match('/\b(?:room|chamber|hall|dungeon|corridor|tunnel|site)\b/iu', $object_name) === 1)) {
      return NULL;
    }
    $family = match (TRUE) {
      in_array($explicit_family, ['obstacle', 'item', 'hazard', 'trap', 'actor'], TRUE) => $explicit_family,
      str_contains($explicit_family, 'object') => 'obstacle',
      preg_match('/\b(?:hazard|fissure|lava|fire|lightning|poison gas|radiation)\b/iu', $object_name) === 1 => 'hazard',
      preg_match('/\b(?:trap|snare|pressure plate|dart|tripwire)\b/iu', $object_name) === 1 => 'trap',
      preg_match('/\b(?:nest|ledge|perch|wall|hoard|cage|trophy|bed|bench|table|frame|rack|pillar|obelisk)\b/iu', $object_name) === 1 => 'obstacle',
      preg_match('/\b(?:creature|dragon|monster|npc|keeper|merchant|guard|villager)\b/iu', $object_name) === 1 => 'actor',
      preg_match('/\b(?:item|sword|shield|potion|scroll|ring|amulet|armor|armour|cup|coin|key|tool)\b/iu', $object_name) === 1 => 'item',
      default => 'obstacle',
    };
    $definition_id = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $object_name), '_'));
    if ($context !== NULL && $context->definitions->currentVersion($family, $definition_id) !== NULL) {
      return [
        'type' => 'tool_call',
        'tool_name' => 'load_canonical_definition',
        'arguments' => [
          'family' => $family,
          'definition_id' => $definition_id,
        ],
        'reasoning' => 'Load the canonical definition that already exists instead of creating a duplicate.',
        'next_steps' => [],
        'existing_definition' => TRUE,
      ];
    }
    return [
      'type' => 'tool_call',
      'tool_name' => 'generate_canonical_definition',
      'arguments' => [
        'family' => $family,
        'definition_id' => $definition_id,
        'prompt' => $object_name . ' for the current room.',
      ],
      'reasoning' => sprintf('Generate and create the explicitly requested %s definition.', $family),
      'next_steps' => [],
      'authorizes_changes' => TRUE,
    ];
  }

  private function roomStreetFootprintCommandPlan(
    EditorGmSurfaceInterface $surface,
    ?string $draft_id,
    string $profile,
    string $author_message,
  ): ?array {
    if ($surface->id() !== RoomEditorGmSurface::ID
      || $draft_id === NULL
      || preg_match('/\b(?:shrink|reshape|street-shaped|longer than wide|fix the footprint|keep the street)\b/iu', $author_message) !== 1) {
      return NULL;
    }
    $context = $surface->createContext($draft_id, $profile);
    $room_context = RoomEditorGmToolContext::of($context);
    if (($room_context->room()['room_type'] ?? '') !== 'street') {
      return NULL;
    }
    $draft = $room_context->draft();
    $plan = $surface->registry()->get('plan_room_commands')->execute(
      [
        'goal' => 'reshape_street_footprint',
        'parameters' => [
          'axis' => 'q',
          'width_hexes' => 5,
          'length_hexes' => 41,
        ],
      ],
      $context,
    );
    $commands = array_map(
      static fn(array $step): array => [
        'type' => (string) $step['command_type'],
        'payload' => (array) $step['payload'],
      ],
      (array) ($plan['command_plan']['steps'] ?? []),
    );
    return [
      'expected_revision' => (int) ($draft['revision'] ?? 0),
      'commands' => $commands,
    ];
  }

  private function roomPublishedBaseRevertPlan(
    EditorGmSurfaceInterface $surface,
    ?string $draft_id,
    string $profile,
    string $author_message,
  ): ?array {
    if ($surface->id() !== RoomEditorGmSurface::ID
      || $draft_id === NULL
      || preg_match('/\b(?:revert|restore)\b.*\b(?:previous|prior|published|base)\s+(?:version|state|draft)\b/iu', $author_message) !== 1) {
      return NULL;
    }
    $context = RoomEditorGmToolContext::of($surface->createContext($draft_id, $profile));
    $draft = $context->draft();
    $version_id = trim((string) ($draft['base_version_id'] ?? ''));
    if ($version_id === '') {
      throw new \DomainException('room_revert_published_base_unavailable');
    }
    $base = $context->roomEditor->publishedVersionPayload($version_id);
    if ($base === $context->room()) {
      return ['no_changes' => TRUE];
    }
    return [
      'expected_revision' => (int) ($draft['revision'] ?? 0),
      'commands' => [[
        'type' => 'restore_published_base',
        'payload' => ['version_id' => $version_id],
      ]],
    ];
  }

  private function roomFurnishingCommandPlan(
      EditorGmSurfaceInterface $surface,
      ?string $draft_id,
      string $profile,
      string $author_message,
      array $evidence,
      string $conversation_context = '',
    ): ?array {
      if ($surface->id() !== RoomEditorGmSurface::ID
        || $draft_id === NULL
        || !$this->isDirectEditorMutationDirective($author_message)) {
        return NULL;
      }
      $text = mb_strtolower(trim($conversation_context . ' ' . $author_message));
      $definitions = [];
      foreach ($evidence as $lookup) {
        foreach ((array) ($lookup['result']['definitions'] ?? []) as $definition) {
          if (is_array($definition)) {
            $definitions[] = $definition;
          }
        }
      }
      $concepts = [
        'broken column' => ['fallen pillar'],
        'unbroken column' => ['stone pillar'],
        'wheel' => ['wheel', 'rimmed oak wheels'],
        'tool' => ['tool rack', 'tool belt', 'tool (long)', 'tool (short)'],
        'metal strip' => ['metal strip', 'iron strip'],
      ];
      $wanted = [];
      $missing = [];
      $mentions_broken_column = preg_match('/\b(?:broken|fallen|collapsed|ruined)\s+(?:columns?|pillars?)\b/u', $text) === 1;
      $mentions_unbroken_column = preg_match('/\b(?:unbroken|standing|upright|intact)\s+(?:columns?|pillars?)\b/u', $text) === 1;
      $mentions_column = preg_match('/\b(?:columns?|pillars?)\b/u', $text) === 1;
      foreach ($concepts as $requested => $aliases) {
        $column_match = match ($requested) {
          'broken column' => $mentions_broken_column,
          'unbroken column' => $mentions_unbroken_column || ($mentions_column && !$mentions_broken_column),
          default => str_contains($text, $requested)
            || !empty(array_filter($aliases, static fn(string $alias): bool => str_contains($text, $alias))),
        };
        if (!$column_match) {
          continue;
        }
        $matches = array_values(array_filter(
          $definitions,
          static function (array $definition) use ($aliases): bool {
            $label = mb_strtolower((string) ($definition['label'] ?? $definition['definition_id'] ?? ''));
            foreach ($aliases as $alias) {
              if (str_contains($label, $alias)) {
                return TRUE;
              }
            }
            return FALSE;
          },
        ));
        usort($matches, static function (array $a, array $b) use ($aliases): int {
          $rank = static fn(array $entry): int => (
            (string) ($entry['family'] ?? '') === 'actor' ? 100 : 0
          ) + (
            str_contains(mb_strtolower((string) ($entry['label'] ?? '')), $aliases[0]) ? 0 : 10
          );
          return $rank($a) <=> $rank($b);
        });
        if ($matches !== []) {
          $wanted[] = [
            'definition' => $matches[0],
            'quantity' => $this->requestedFurnishingQuantity($text, $requested, $aliases),
          ];
        }
        else {
          $missing[] = $requested;
        }
      }
      if ($wanted === []) {
        return NULL;
      }
      $context = $surface->createContext($draft_id, $profile);
      $room_context = RoomEditorGmToolContext::of($context);
      $draft = $room_context->draft();
      $room = $room_context->room();
      $occupied = [];
      $occupied_definitions = [];
      foreach ((array) ($room['placements'] ?? []) as $placement) {
        $anchor = $placement['anchor_hex'] ?? NULL;
        if (is_array($anchor)) {
          $occupied[(int) ($anchor['q'] ?? 0) . ':' . (int) ($anchor['r'] ?? 0)] = TRUE;
        }
        $definition_ref = $placement['definition_ref'] ?? NULL;
        if (is_array($definition_ref)) {
          $occupied_definitions[(string) ($definition_ref['family'] ?? '') . ':' . (string) ($definition_ref['definition_id'] ?? '')] = TRUE;
        }
      }
      foreach (array_merge(
        (array) ($room['entry_ports'] ?? []),
        (array) ($room['exit_ports'] ?? []),
      ) as $port) {
        $anchor = $port['hex'] ?? NULL;
        if (is_array($anchor)) {
          $occupied[(int) ($anchor['q'] ?? 0) . ':' . (int) ($anchor['r'] ?? 0)] = TRUE;
        }
      }
      $available = array_values(array_filter(
        (array) ($room['hexes'] ?? []),
        static fn(mixed $hex): bool => is_array($hex)
          && !isset($occupied[(int) ($hex['q'] ?? 0) . ':' . (int) ($hex['r'] ?? 0)]),
      ));
      usort($available, static fn(array $a, array $b): int =>
        (abs((int) ($a['q'] ?? 0)) + abs((int) ($a['r'] ?? 0)))
        <=> (abs((int) ($b['q'] ?? 0)) + abs((int) ($b['r'] ?? 0))));
      if (count($available) < count($wanted)) {
        return NULL;
      }
      $placements = [];
      $placed_labels = [];
      $placement_index = 0;
      $is_additive = preg_match('/\b(?:add|more)\b/u', $text) === 1;
      $placement_count = 0;
      foreach ($wanted as $specification) {
        $definition = (array) $specification['definition'];
        $definition_key = (string) ($definition['family'] ?? '') . ':' . (string) ($definition['definition_id'] ?? '');
        if ($is_additive || !isset($occupied_definitions[$definition_key])) {
          $placement_count += max(1, (int) $specification['quantity']);
        }
      }
      $placement_hexes = $this->furnishingPlacementHexes(
        $available,
        $placement_count,
        preg_match('/\b(?:spread|scatter(?:ed)?|around|evenly)\b/u', $text) === 1,
      );
      foreach ($wanted as $specification) {
        $definition = (array) $specification['definition'];
        $quantity = max(1, (int) $specification['quantity']);
        $definition_key = (string) ($definition['family'] ?? '') . ':' . (string) ($definition['definition_id'] ?? '');
        if (!$is_additive && isset($occupied_definitions[$definition_key])) {
          continue;
        }
        for ($copy = 0; $copy < $quantity; $copy++) {
          if (!isset($placement_hexes[$placement_index])) {
            return NULL;
          }
          $version = trim((string) ($definition['definition_version'] ?? $definition['version'] ?? ''));
          $hex = $placement_hexes[$placement_index++];
          $placements[] = [
            'family' => (string) $definition['family'],
            'definition_id' => (string) $definition['definition_id'],
            'version' => $version !== '' ? $version : '1.0.0',
            'anchor_hex' => ['q' => (int) $hex['q'], 'r' => (int) $hex['r']],
            'facing' => 0,
          ];
          $placed_labels[] = (string) ($definition['label'] ?? $definition['definition_id']);
        }
      }
      if ($placements === []) {
        return [
          'no_changes' => TRUE,
          'missing_concepts' => $missing,
        ];
      }

      $plan = $surface->registry()->get('plan_room_commands')->execute(
        [
          'goal' => 'place_objects',
          'parameters' => ['placements' => $placements],
        ],
        $context,
      );
      $commands = array_map(
        static fn(array $step): array => [
          'type' => (string) $step['command_type'],
          'payload' => (array) $step['payload'],
        ],
        (array) ($plan['command_plan']['steps'] ?? []),
      );
      return [
        'expected_revision' => (int) ($draft['revision'] ?? 0),
        'commands' => $commands,
        'placed_labels' => $placed_labels,
        'missing_concepts' => $missing,
      ];
    }

  private function requestedFurnishingQuantity(string $text, string $requested, array $aliases): int {
    $numbers = [
      'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
      'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
      'eleven' => 11, 'twelve' => 12,
    ];
    $terms = array_values(array_unique(array_merge([$requested], $aliases)));
    $patterns = array_map(
      static fn(string $term): string => str_replace('\ ', '\s+', preg_quote($term, '/')),
      $terms,
    );
    if (str_contains($requested, 'column')) {
      $patterns[] = 'columns?';
      $patterns[] = 'pillars?';
    }
    $concept_pattern = implode('|', $patterns);
    if ($concept_pattern !== '' && preg_match(
      '/\b(\d+|' . implode('|', array_keys($numbers)) . ')\s+(?:more\s+)?(?:broken\s+|fallen\s+|collapsed\s+|ruined\s+|unbroken\s+|standing\s+|upright\s+|intact\s+)?(?:' . $concept_pattern . ')\b/iu',
      $text,
      $matches,
    ) === 1) {
      return $numbers[strtolower($matches[1])] ?? (int) $matches[1];
    }
    if (preg_match('/\badd\s+(\d+|' . implode('|', array_keys($numbers)) . ')\s+more\b/iu', $text, $matches) === 1) {
      return $numbers[strtolower($matches[1])] ?? (int) $matches[1];
    }
    return 1;
  }

  private function furnishingPlacementHexes(array $available, int $count, bool $spread): array {
    if ($count <= 0 || count($available) < $count) {
      return [];
    }
    if (!$spread || $count === 1) {
      return array_slice($available, 0, $count);
    }
    $center_q = array_sum(array_column($available, 'q')) / count($available);
    $center_r = array_sum(array_column($available, 'r')) / count($available);
    $points = array_map(static fn(array $hex): array => [
      'hex' => $hex,
      'x' => (float) ($hex['q'] - $center_q) + 0.5 * (float) ($hex['r'] - $center_r),
      'y' => sqrt(3) * 0.5 * (float) ($hex['r'] - $center_r),
    ], $available);
    $max_radius = max(array_map(
      static fn(array $point): float => hypot($point['x'], $point['y']),
      $points,
    ));
    $target_radius = $max_radius * 0.7;
    $selected = [];
    $remaining = $points;
    for ($index = 0; $index < $count; $index++) {
      $target_angle = -M_PI + (2 * M_PI * $index / $count);
      usort($remaining, static function (array $a, array $b) use ($target_angle, $target_radius, $max_radius): int {
        $score = static function (array $point) use ($target_angle, $target_radius, $max_radius): float {
          $angle = atan2($point['y'], $point['x']);
          $delta = abs($angle - $target_angle);
          $angle_delta = min($delta, (2 * M_PI) - $delta);
          return abs(hypot($point['x'], $point['y']) - $target_radius) / max($max_radius, 1.0)
            + ($angle_delta / M_PI);
        };
        return $score($a) <=> $score($b);
      });
      $choice = array_shift($remaining);
      $selected[] = $choice['hex'];
    }
    return $selected;
  }

  private function roomFurnishingLookupTasks(
    EditorGmSurfaceInterface $surface,
    string $author_message,
  ): array {
    if ($surface->id() !== RoomEditorGmSurface::ID
      || !$this->isDirectEditorMutationDirective($author_message)) {
      return [];
    }
    $text = mb_strtolower($author_message);
    $searches = [
      'fallen pillar' => 'pillar',
      'column' => 'pillar',
      'wheel' => 'wheel',
      'tool' => 'tool',
      'metal strip' => 'metal strip',
    ];
    $tasks = [];
    $seen_searches = [];
    foreach ($searches as $concept => $search) {
      if ($concept === 'wheel' && !preg_match('/\\bwheels?\\b/u', $text)) {
        continue;
      }
      if ($concept === 'column' && !preg_match('/\b(?:columns?|pillars?)\b/u', $text)) {
        continue;
      }
      if (!in_array($concept, ['wheel', 'column'], TRUE) && !str_contains($text, $concept)) {
        continue;
      }
      if (isset($seen_searches[$search])) {
        continue;
      }
      $seen_searches[$search] = TRUE;
      $tasks[] = [
        'tool_name' => 'list_catalog_definitions',
        'arguments' => ['search' => $search, 'limit' => 250],
        'reasoning' => sprintf('Ground the requested %s in the placeable catalog.', $concept),
      ];
    }
    return $tasks;
  }

  private function authorizesEditorLookupChanges(
    EditorGmSurfaceInterface $surface,
    array $intent,
    array $scope,
    string $author_message,
  ): bool {
    if (in_array($surface->id(), [RoomEditorGmSurface::ID, DungeonEditorGmSurface::ID], TRUE)) {
      return $this->isDirectEditorMutationDirective($author_message);
    }
    if ($surface->id() === 'definition_editor') {
      return ($intent['authorizes_changes'] ?? FALSE) === TRUE;
    }
    return $this->authorizesStorylineLookupChanges($intent, $scope);
  }

  private function leadingLookupTasks(EditorGmSurfaceInterface $surface, array $tasks): array {
    $lookups = [];
    foreach ($tasks as $task) {
      $name = (string) ($task['tool_name'] ?? '');
      if ($name === AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
        break;
      }
      $definition = $surface->registry()->get($name)->definition();
      if ($definition->mutating || $definition->family !== EditorGmToolDefinition::FAMILY_CONTEXT) {
        break;
      }
      $lookups[] = $task;
    }
    return $lookups;
  }

  private function missingPublishedRoomEditorRoute(
    EditorGmSurfaceInterface $surface,
    string $author_message,
    array $evidence,
  ): ?string {
    if ($surface->id() !== DungeonEditorGmSurface::ID) {
      return NULL;
    }
    foreach ($evidence as $lookup) {
      if (($lookup['tool'] ?? '') !== 'list_published_rooms'
        || (int) ($lookup['result']['room_count'] ?? -1) !== 0) {
        continue;
      }
      $search = mb_strtolower(trim((string) ($lookup['arguments']['search'] ?? '')));
      $identifiers = [];
      if (preg_match_all('/\b[a-z0-9]+(?:_[a-z0-9]+)+\b/i', $author_message, $matches) === 1
        || $matches[0] !== []) {
        $identifiers = $matches[0];
      }
      foreach ($identifiers as $identifier) {
        $identifier = mb_strtolower($identifier);
        if ($search === '' || str_contains($identifier, preg_replace('/\s+/', '_', $search) ?? '')
          || array_filter(preg_split('/\s+/', $search) ?: [], static fn(string $term): bool => str_contains($identifier, $term)) !== []) {
          return $identifier;
        }
      }
      if (str_contains($search, '_')) {
        return $search;
      }
      return '';
    }
    return NULL;
  }

  private static function normalizeLookupArguments(array $arguments): array {
    if (!array_is_list($arguments)) {
      ksort($arguments);
    }
    foreach ($arguments as $key => $value) {
      if (is_array($value)) {
        $arguments[$key] = self::normalizeLookupArguments($value);
      }
    }
    return $arguments;
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
    array $prior_tool_calls = [],
  ): array {
    if ($surface->id() === StorylineBuilderGmSurface::ID) {
      foreach ($tasks as $task) {
        $name = (string) ($task['tool_name'] ?? '');
        if ($name === AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
          continue;
        }
        $definition = $surface->registry()->get($name)->definition();
        if ($definition->mutating && !in_array($name, self::STORYLINE_DIRECT_MUTATIONS, TRUE)) {
          throw new \LogicException('storyline_gm_direct_mutation_tool_forbidden');
        }
      }
    }
    $ordered = $surface->id() === RoomEditorGmSurface::ID
      ? array_values($tasks)
      : $this->harnessManagement->orderTaskQueue($tasks);
    $initial_draft_id = $draft_id;
    $started_at = microtime(TRUE);
    $delegated_room_batch = $surface->id() === DungeonEditorGmSurface::ID
      && $ordered !== []
      && array_diff(array_column($ordered, 'tool_name'), ['delegate_room_editor_task']) === [];
    $accumulated_tool_calls = array_merge($prior_tool_calls, [
      [
        'tool' => 'task_queue_preprocessor',
        'message' => sprintf('Now queuing %d ordered task(s) for serial execution…', count($ordered)),
      ],
    ]);
    $last_envelope = NULL;
    $completed_count = 0;

    foreach ($ordered as $index => $task) {
      if (!$delegated_room_batch && $index > 0 && (microtime(TRUE) - $started_at) >= AgentHarnessManagementService::DEFAULT_SERIAL_TIME_BUDGET_SECONDS) {
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
        require_approval: $surface->id() === RoomEditorGmSurface::ID && empty($scope['delegated_authorization']),
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
    if ($delegated_room_batch) {
      $last_envelope['tool_result']['task_queue_status'] = [
        'completed' => $completed_count,
        'total' => count($ordered),
        'complete' => $completed_count === count($ordered),
      ];
      $last_envelope['tool_result']['assistant_response'] = sprintf(
        'Room Agent completed %d of %d rooms in the selected continuation.',
        $completed_count,
        count($ordered),
      );
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
   * Delegates one authorized room task to the Room Editor GM authority.
   */
  private function executeRoomEditorDelegation(
    EditorGmSurfaceInterface $source_surface,
    ?string $draft_id,
    string $profile,
    array $arguments,
    array $scope,
    string $author_message,
    array $prior_tool_calls,
    bool $dry_run,
  ): array {
    if ($source_surface->id() !== DungeonEditorGmSurface::ID) {
      throw new \LogicException('room_editor_delegation_requires_dungeon_surface');
    }
    $room_id = isset($arguments['room_id']) ? EditorGmToolContext::requireString($arguments, 'room_id') : '';
    $name = EditorGmToolContext::requireString($arguments, 'name');
    $task = EditorGmToolContext::requireString($arguments, 'task');
    $source_context = $this->createContext($source_surface, $draft_id, $profile, $scope);
    if ($dry_run) {
      return $this->envelope(
        $source_surface,
        'editor_intent_proposal',
        $source_surface->assembler()->assemble($source_context),
        [
          'tool' => 'delegate_room_editor_task',
          'dry_run' => TRUE,
          'would_mutate' => TRUE,
          'room_id' => $room_id,
          'name' => $name,
          'task' => $task,
        ],
        $source_context->validation(),
      );
    }
    $room_surface = $this->surface(RoomEditorGmSurface::ID);
    $tool_calls = array_values($prior_tool_calls);
    $tool_calls[] = $this->describeToolCall('delegate_room_editor_task', $arguments);
    $room_seed = [];
    if ($room_id !== '') {
      $room_seed['room_id'] = $room_id;
    }
    $room_context = $room_surface->createContext(NULL, 'editing');
    if ($room_id === '' || RoomEditorGmToolContext::of($room_context)->roomEditor->publishedRoom($room_id) === NULL) {
      $room_seed['name'] = $name;
    }

    $created = $this->executeTool(
      $room_surface,
      NULL,
      'editing',
      'create_room_draft',
      $room_seed,
      FALSE,
      'Create or resume the reserved room draft for the delegated task.',
      ['delegated_authorization' => TRUE],
      [],
      '',
      $tool_calls,
    );
    $room_draft_id = (string) ($created['tool_result']['active_draft_id'] ?? '');
    if ($room_draft_id === '') {
      throw new \LogicException('room_editor_delegation_draft_missing');
    }
    $room_draft = RoomEditorGmToolContext::of($room_surface->createContext($room_draft_id, 'editing'))->draft();
    $room_id = trim((string) ($room_draft['room']['room_id'] ?? $room_id));
    if ($room_id === '') {
      throw new \LogicException('room_editor_delegation_room_identity_missing');
    }
    if (($room_draft['base_version_id'] ?? NULL) === NULL) {
      $generated = $this->executeTool(
        $room_surface,
        $room_draft_id,
        'editing',
        'generate_room_layout',
        [
          'prompt' => sprintf('%s Room name: %s.', $task, $name),
          'theme' => 'water_ruins',
          'size_category' => 'medium',
          // The room generator validates metadata commands against the blank
          // footprint before it applies generated geometry. Linear room types
          // can therefore fail on a compact blank draft. Generate as chamber,
          // then the layout command still owns the requested semantics.
          'room_type' => 'chamber',
          'min_hexes' => 16,
          'max_hexes' => 45,
          'placeable_families' => ['actor', 'item', 'obstacle', 'trap', 'hazard'],
        ],
        FALSE,
        'Generate the delegated new room through the Room Editor GM.',
        ['delegated_authorization' => TRUE],
        [],
        '',
        $tool_calls,
      );
      $tool_calls = array_values((array) ($generated['tool_calls'] ?? $tool_calls));
      $plan = (array) ($generated['tool_result']['command_plan'] ?? []);
      $steps = array_values((array) ($plan['steps'] ?? []));
      if (preg_match('/\b(?:terminal|final|no onward exit|no exit)\b/iu', $task) === 1) {
        $steps = array_values(array_filter(
          $steps,
          static fn(array $step): bool => ($step['command_type'] ?? '') !== 'add_exit_port',
        ));
      }
      $commands = array_map(
        static fn(array $step): array => [
          'type' => (string) ($step['command_type'] ?? ''),
          'payload' => (array) ($step['payload'] ?? []),
        ],
        $steps,
      );
      if ($commands === [] || !is_int($plan['base_revision'] ?? NULL)) {
        throw new LogicException('delegated_room_generation_plan_invalid:' . $room_id);
      }
      $applied = $this->executeTool(
        $room_surface,
        $room_draft_id,
        'editing',
        'apply_room_commands',
        [
          'expected_revision' => $plan['base_revision'],
          'commands' => $commands,
        ],
        FALSE,
        'Apply the generated Room Editor command plan.',
        ['delegated_authorization' => TRUE],
        [],
        '',
        $tool_calls,
      );
      $tool_calls = array_values((array) ($applied['tool_calls'] ?? $tool_calls));
      $child_result = (array) ($applied['tool_result'] ?? []);
      $child_result['assistant_response'] = sprintf('Room Agent generated %s for dungeon placement.', $name);
    }
    else {
      $child = $this->handle(
        RoomEditorGmSurface::ID,
        $room_draft_id,
        [
          'schema_version' => self::REQUEST_CONTRACT_VERSION,
          'tool_context' => [
            'tool_id' => RoomEditorGmSurface::ID,
            'draft_id' => $room_draft_id,
            'validation_profile' => 'editing',
          ],
          'intent' => [
            'type' => 'natural_language',
            'utterance' => $task,
            'author_message' => $author_message !== '' ? $author_message : $task,
          ],
        ],
        [
          'delegated_authorization' => TRUE,
          'delegated_from_surface' => DungeonEditorGmSurface::ID,
        ],
      );
      $tool_calls = array_merge($tool_calls, (array) ($child['tool_calls'] ?? []));
      $child_result = (array) ($child['tool_result'] ?? []);
    }
    if (preg_match('/\b(?:publish(?:ed|ing)?|create|build)\b/i', $task) === 1) {
      $room_context = $room_surface->createContext($room_draft_id, 'publication');
      $room_draft = $room_context->draft();
      $validation = $room_context->validation();
      if (($room_draft['status'] ?? '') === 'active'
        && !($validation['valid'] ?? $validation['is_valid'] ?? FALSE)) {
        throw new DomainException('delegated_room_publication_validation_failed:' . $room_id);
      }
      if (($validation['valid'] ?? $validation['is_valid'] ?? FALSE) === TRUE
        && ($room_draft['status'] ?? '') === 'active') {
        $publication = $this->executeTool(
          $room_surface,
          $room_draft_id,
          'publication',
          'publish_room_version',
          [
            'version' => '1.0.0',
            'expected_revision' => (int) $room_draft['revision'],
            'publication_note' => 'Published by the Room Editor GM for Dungeon Editor placement.',
          ],
          FALSE,
          'Publish the delegated room so the Dungeon Editor can place it.',
          ['delegated_authorization' => TRUE],
          [],
          '',
          $tool_calls,
        );
        $tool_calls = array_values((array) ($publication['tool_calls'] ?? $tool_calls));
        $child_result = (array) ($publication['tool_result'] ?? []);
        $room_draft = $room_surface->createContext($room_draft_id, 'publication')->draft();
      }
      if (($room_draft['status'] ?? '') === 'active') {
        throw new LogicException('delegated_room_publication_not_recorded:' . $room_id);
      }
      $room_seed_context = RoomEditorGmToolContext::of($room_surface->createContext(NULL, 'editing'));
      if ($room_seed_context->roomEditor->publishedRoom($room_id) !== NULL) {
        $fresh_source = $this->createContext($source_surface, $draft_id, $profile, []);
        $fresh_dungeon = DungeonEditorGmToolContext::of($fresh_source)->model();
        $already_placed = array_filter(
          (array) ($fresh_dungeon['placements'] ?? []),
          static fn(array $placement): bool => ($placement['room_id'] ?? '') === $room_id,
        ) !== [];
        $is_district = ($fresh_dungeon['kind'] ?? 'dungeon') === 'district';
        $street_placement_id = $is_district ? $this->districtStreetPlacementId($fresh_dungeon) : '';
        $anchor_room_id = $this->delegatedAnchorRoomId($fresh_source, $task);
        if (!$already_placed && (array) ($fresh_dungeon['placements'] ?? []) === []) {
          $base_revision = (int) ($fresh_dungeon['revision'] ?? 0);
          $placement_plan = $this->executeTool(
            $source_surface,
            $draft_id,
            $profile,
            'plan_dungeon_commands',
            [
              'goal' => 'place_rooms',
              'parameters' => [
                'placements' => [[
                  'room_id' => $room_id,
                  'origin' => ['q' => 0, 'r' => 0],
                  'rotation_steps' => 0,
                  'label' => $name,
                ]],
              ],
            ],
            FALSE,
            'Plan the first published room at the dungeon origin.',
            ['delegated_authorization' => TRUE],
            [],
            '',
            $tool_calls,
          );
          $tool_calls = array_values((array) ($placement_plan['tool_calls'] ?? $tool_calls));
          $command_plan = (array) ($placement_plan['tool_result']['command_plan'] ?? []);
          $commands = array_map(
            static fn(array $step): array => [
              'type' => (string) ($step['command_type'] ?? ''),
              'payload' => (array) ($step['payload'] ?? []),
            ],
            array_values((array) ($command_plan['steps'] ?? [])),
          );
          $plan_revision = $command_plan['base_revision'] ?? $base_revision;
          if ($commands === [] || !is_int($plan_revision)) {
            throw new LogicException('delegated_room_initial_placement_plan_invalid');
          }
          $placement = $this->executeTool(
            $source_surface,
            $draft_id,
            $profile,
            'apply_dungeon_commands',
            ['expected_revision' => $plan_revision, 'commands' => $commands],
            FALSE,
            'Apply the first delegated room placement at the dungeon origin.',
            ['delegated_authorization' => TRUE],
            [],
            '',
            $tool_calls,
          );
          $tool_calls = array_values((array) ($placement['tool_calls'] ?? $tool_calls));
          $child_result['dungeon_placement'] = $placement['tool_result'] ?? [];
        }
        elseif (!$already_placed && $is_district && $street_placement_id !== '') {
          $placement_plan = $this->executeTool(
            $source_surface,
            $draft_id,
            $profile,
            'plan_district_building',
            [
              'room_id' => $room_id,
              'street_placement_id' => $street_placement_id,
            ],
            FALSE,
            'Plan the published room beside its named anchor.',
            ['delegated_authorization' => TRUE],
            [],
            '',
            $tool_calls,
          );
          $tool_calls = array_values((array) ($placement_plan['tool_calls'] ?? $tool_calls));
          $command_plan = (array) ($placement_plan['tool_result']['command_plan'] ?? []);
          $commands = array_map(
            static fn(array $step): array => [
              'type' => (string) ($step['command_type'] ?? ''),
              'payload' => (array) ($step['payload'] ?? []),
            ],
            array_values((array) ($command_plan['steps'] ?? [])),
          );
          if ($commands === [] || !is_int($command_plan['base_revision'] ?? NULL)) {
            throw new \LogicException('delegated_room_placement_plan_invalid');
          }
          $placement = $this->executeTool(
            $source_surface,
            $draft_id,
            $profile,
            'apply_dungeon_commands',
            [
              'expected_revision' => $command_plan['base_revision'],
              'commands' => $commands,
            ],
            FALSE,
            'Apply the exact delegated room placement.',
            ['delegated_authorization' => TRUE],
            [],
            '',
            $tool_calls,
          );
          $tool_calls = array_values((array) ($placement['tool_calls'] ?? $tool_calls));
          $child_result['dungeon_placement'] = $placement['tool_result'] ?? [];
        }
        elseif (!$already_placed && $anchor_room_id !== '') {
          $placement_plan = $this->executeTool(
            $source_surface,
            $draft_id,
            $profile,
            'plan_room_placement',
            [
              'room_id' => $room_id,
              'near_room_id' => $anchor_room_id,
            ],
            FALSE,
            'Plan the published room beside its named anchor.',
            ['delegated_authorization' => TRUE],
            [],
            '',
            $tool_calls,
          );
          $tool_calls = array_values((array) ($placement_plan['tool_calls'] ?? $tool_calls));
          $command_plan = (array) ($placement_plan['tool_result']['command_plan'] ?? []);
          $commands = array_map(
            static fn(array $step): array => [
              'type' => (string) ($step['command_type'] ?? ''),
              'payload' => (array) ($step['payload'] ?? []),
            ],
            array_values((array) ($command_plan['steps'] ?? [])),
          );
          if ($commands === [] || !is_int($command_plan['base_revision'] ?? NULL)) {
            throw new \LogicException('delegated_room_placement_plan_invalid');
          }
          $placement = $this->executeTool(
            $source_surface,
            $draft_id,
            $profile,
            'apply_dungeon_commands',
            [
              'expected_revision' => $command_plan['base_revision'],
              'commands' => $commands,
            ],
            FALSE,
            'Apply the exact delegated room placement.',
            ['delegated_authorization' => TRUE],
            [],
            '',
            $tool_calls,
          );
          $tool_calls = array_values((array) ($placement['tool_calls'] ?? $tool_calls));
          $child_result['dungeon_placement'] = $placement['tool_result'] ?? [];
          if (!$is_district && $anchor_room_id !== '') {
            $linked_source = $this->createContext($source_surface, $draft_id, $profile, []);
            $linked_model = DungeonEditorGmToolContext::of($linked_source)->model();
            $from_placement = array_values(array_filter(
              (array) ($linked_model['placements'] ?? []),
              static fn(array $candidate): bool => ($candidate['room_id'] ?? '') === $anchor_room_id,
            ))[0] ?? NULL;
            $to_placement = array_values(array_filter(
              (array) ($linked_model['placements'] ?? []),
              static fn(array $candidate): bool => ($candidate['room_id'] ?? '') === $room_id,
            ))[0] ?? NULL;
            if (is_array($from_placement) && is_array($to_placement)) {
              $link_plan = $this->executeTool(
                $source_surface,
                $draft_id,
                $profile,
                'plan_port_links',
                [
                  'kind' => 'door',
                  'direction' => 'bidirectional',
                  'default_state' => 'open',
                  'placement_id' => (string) $from_placement['placement_id'],
                  'to_placement_id' => (string) $to_placement['placement_id'],
                ],
                FALSE,
                'Link the delegated room to its predecessor.',
                ['delegated_authorization' => TRUE],
                [],
                '',
                $tool_calls,
              );
              $tool_calls = array_values((array) ($link_plan['tool_calls'] ?? $tool_calls));
              $link_command_plan = (array) ($link_plan['tool_result']['command_plan'] ?? []);
              $link_commands = array_map(
                static fn(array $step): array => [
                  'type' => (string) ($step['command_type'] ?? ''),
                  'payload' => (array) ($step['payload'] ?? []),
                ],
                array_values((array) ($link_command_plan['steps'] ?? [])),
              );
              if ($link_commands !== [] && is_int($link_command_plan['base_revision'] ?? NULL)) {
                $linked = $this->executeTool(
                  $source_surface,
                  $draft_id,
                  $profile,
                  'apply_dungeon_commands',
                  [
                    'expected_revision' => $link_command_plan['base_revision'],
                    'commands' => $link_commands,
                  ],
                  FALSE,
                  'Apply the predecessor link for the delegated room.',
                  ['delegated_authorization' => TRUE],
                  [],
                  '',
                  $tool_calls,
                );
                $tool_calls = array_values((array) ($linked['tool_calls'] ?? $tool_calls));
                $child_result['dungeon_link'] = $linked['tool_result'] ?? [];
              }
            }
          }
        }
      }
    }
    return $this->envelope(
      $source_surface,
      'editor_agent_delegation',
      $source_surface->assembler()->assemble($source_context),
      [
        'tool' => 'delegate_room_editor_task',
        'room_id' => $room_id,
        'room_draft_id' => $room_draft_id,
        'delegated_result' => $child_result,
        'assistant_response' => sprintf(
          "Delegated to Room Editor\n%s",
          (string) (($child_result['assistant_response'] ?? '') ?: 'The Room Editor GM completed its delegated task.'),
        ),
        'next_steps' => array_values((array) ($child_result['next_steps'] ?? [])),
        'tool_calls' => $tool_calls,
      ],
      $source_context->validation(),
      [[
        'level' => 'success',
        'text' => sprintf('Delegated room work for %s to the Room Editor GM.', $room_id),
      ]],
      [],
      $tool_calls,
    );
  }

  private function delegatedAnchorRoomId(EditorGmToolContext $context, string $task): string {
    $context = DungeonEditorGmToolContext::of($context);
    $text = mb_strtolower($task);
    $candidates = [];
    foreach ((array) ($context->dungeon()['room_placements'] ?? []) as $placement) {
      $room_id = trim((string) ($placement['room_id'] ?? ''));
      $name = mb_strtolower(trim((string) $context->dungeonEditor->roomDisplayName(
        $room_id,
        (string) ($placement['version_id'] ?? ''),
      )));
      if ($room_id !== '' && ($name !== '' && str_contains($text, $name) || str_contains($text, mb_strtolower($room_id)))) {
        $candidates[$room_id] = TRUE;
      }

    }
    if (count($candidates) === 1) {
      return (string) array_key_first($candidates);
    }
    $placements = array_values((array) ($context->dungeon()['room_placements'] ?? []));
    $last = end($placements);
    return is_array($last) ? trim((string) ($last['room_id'] ?? '')) : '';
  }

  private function districtStreetPlacementId(array $model): string {
    $streets = [];
    foreach ((array) ($model['placements'] ?? []) as $placement) {
      if (($placement['resolved'] ?? FALSE) !== TRUE
        || ($placement['room']['room_type'] ?? '') !== 'street') {
        continue;
      }
      $streets[] = (string) ($placement['placement_id'] ?? '');
    }
    return count($streets) === 1 ? $streets[0] : '';
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
    if ($tool_name === 'delegate_room_editor_task') {
      return $this->executeRoomEditorDelegation(
        $surface,
        $draft_id,
        $profile,
        $arguments,
        $scope,
        $utterance,
        $prior_tool_calls,
        $dry_run,
      );
    }
    if ($tool_name === AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
      $tool_calls = array_values($prior_tool_calls);
      $tool_calls[] = $this->describeToolCall($tool_name, $arguments);
      $context = $this->createContext($surface, $draft_id, $profile, $scope);
      $snapshot = $surface->assembler()->assemble($context);
      if (isset($scope['lookup_evidence'])) {
        $snapshot['lookup_evidence'] = $scope['lookup_evidence'];
      }
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
    if ($definition->name === 'plan_storyline_draft_operations' && ($scope['storyline_draft_write_intent'] ?? NULL) !== NULL) {
      $arguments['preview_only'] = !$scope['storyline_draft_write_intent'];
    }
    $tool_calls = array_values($prior_tool_calls);
    $tool_calls[] = $this->describeToolCall($definition->name, $arguments);
    $context = $this->createContext($surface, $draft_id, $profile, $scope);
    $snapshot = $surface->assembler()->assemble($context);
    if (isset($scope['lookup_evidence'])) {
      $snapshot['lookup_evidence'] = $scope['lookup_evidence'];
    }
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
    if (
      $surface->id() === 'definition_editor'
      && !$dry_run
      && $definition->name === 'plan_definition_patch'
      && ($scope['definition_write_intent'] ?? NULL) === TRUE
      && ($result['valid'] ?? FALSE) === TRUE
      && ($proposed_execution['tool_name'] ?? '') === 'update_definition'
      && is_array($proposed_execution['arguments'] ?? NULL)
    ) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        'update_definition',
        $proposed_execution['arguments'],
        FALSE,
        'Apply the explicitly authorized definition patch.',
        $scope,
        $next_steps,
        $utterance,
        $tool_calls,
      );
    }
    if (in_array($surface->id(), [RoomEditorGmSurface::ID, DungeonEditorGmSurface::ID], TRUE)
      && !empty($scope['delegated_authorization'])
      && ($proposed_execution['tool_name'] ?? '') !== ''
      && is_array($proposed_execution['arguments'] ?? NULL)) {
      $proposed_tool = $surface->registry()->get((string) $proposed_execution['tool_name'])->definition();
      if (!$proposed_tool->mutating) {
        throw new \LogicException('editor_delegation_proposed_nonmutating_tool');
      }
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        (string) $proposed_execution['tool_name'],
        $proposed_execution['arguments'],
        FALSE,
        $reasoning,
        $scope,
        $next_steps,
        $utterance,
        $tool_calls,
      );
    }
    $command_plan = is_array($result['command_plan'] ?? NULL)
      ? $result['command_plan']
      : NULL;
    if (
      $surface->id() === DungeonEditorGmSurface::ID
      && !$dry_run
      && $definition->mutating
      && $command_plan !== NULL
      && $this->isDirectEditorMutationDirective($utterance)
    ) {
      $commands = array_map(
        static fn(array $step): array => [
          'type' => (string) ($step['command_type'] ?? ''),
          'payload' => (array) ($step['payload'] ?? []),
        ],
        array_values((array) ($command_plan['steps'] ?? [])),
      );
      if ($commands === [] || !is_int($command_plan['base_revision'] ?? NULL)) {
        throw new LogicException('editor_execution_command_plan_invalid:' . $definition->name);
      }
      $applied = $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        'apply_dungeon_commands',
        [
          'expected_revision' => $command_plan['base_revision'],
          'commands' => $commands,
        ],
        FALSE,
        'Apply the exact repin/reflow plan returned by the completed dungeon mutation.',
        $scope,
        [],
        $utterance,
        $tool_calls,
      );
      $result['applied_command_plan'] = $applied['tool_result'] ?? [];
      $result['final_revision'] = $applied['tool_result']['final_revision'] ?? NULL;
      $result['assistant_response'] = sprintf(
        '%s completed and its %d dungeon command%s were applied.',
        $definition->name,
        count($commands),
        count($commands) === 1 ? '' : 's',
      );
      $result['next_steps'] = ['Validate the dungeon and review the remaining findings.'];
      return $this->envelope(
        $surface,
        'editor_command_execution',
        $applied['context_snapshot'] ?? $snapshot,
        $result,
        $applied['validation'] ?? $context->validation(),
        [['level' => 'success', 'text' => $result['assistant_response']]],
        [],
        $applied['tool_calls'] ?? $tool_calls,
      );
    }
    if ($definition->name === 'plan_storyline_remediation'
      && ($result['has_changes'] ?? NULL) === FALSE && $utterance !== ''
      && ($scope['storyline_draft_write_intent'] ?? NULL) === TRUE) {
      $items = RepairStorylineItemDefinitionsTool::repairableFindings($context->validation());
      $result['assistant_response'] = 'No remediation was saved or queued: the planner found no missing assets, linked-quest reference repairs, or draft edits in this plan.';
      $result['next_steps'] = [];
      if ($items !== []) {
        $labels = implode(', ', array_column($items, 'entity_label'));
        $result['assistant_response'] .= ' The flagged items already exist; the unsupported field is in their canonical definitions, not in this draft.';
        $result['next_steps'] = ["Repair the canonical item definitions for {$labels} by removing the redundant content_id."];
      }
      return $this->envelope(
        $surface, 'editor_management_response', $snapshot, $result, $context->validation(),
        [], [], $tool_calls,
      );
    }
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
    if ($surface->id() === RoomEditorGmSurface::ID
      && $definition->name === 'generate_canonical_definition'
      && ($proposed_execution['tool_name'] ?? '') === 'create_canonical_definition'
      && is_array($proposed_execution['arguments'] ?? NULL)
      && $this->isDirectEditorMutationDirective($utterance)) {
      return $this->executeTool(
        $surface,
        $draft_id,
        $profile,
        'create_canonical_definition',
        (array) $proposed_execution['arguments'],
        $dry_run,
        $reasoning !== '' ? $reasoning : 'Create the requested canonical room object.',
        $scope,
        [],
        $utterance,
        $tool_calls,
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
      if ($definition->name === 'plan_storyline_draft_operations' && ($result['requires_approval'] ?? FALSE) === TRUE) {
        $result['assistant_response'] = trim($reasoning . "\n\nNothing was saved. The draft edit is ready for approval.");
        $result['next_steps'] = ['Apply the proposed draft edit.'];
        return $this->envelope(
          $surface, 'editor_intent_proposal', $snapshot, $result, $context->validation(),
          [], [], $tool_calls,
        );
      }
      if (
        in_array((string) $proposed_execution['tool_name'], [
          'apply_storyline_remediation',
          'create_canonical_definition',
          'update_canonical_definition',
        ], TRUE)
        && !(($scope['storyline_draft_write_intent'] ?? NULL) === TRUE
          && $proposed_execution['tool_name'] === 'apply_storyline_remediation')
        && !$this->authorizesStorylineRemediation($utterance)
      ) {
        $is_remediation = $proposed_execution['tool_name'] === 'apply_storyline_remediation';
        $preview = $is_remediation ? [
          'draft_changes' => $result['draft_changes'],
          'definitions_to_prepare' => array_map(
            static fn(array $definition): array => array_intersect_key($definition, array_flip(['family', 'definition_id'])),
            $proposed_execution['arguments']['definitions'],
          ),
          'linked_quest_ids_to_realign' => array_column($proposed_execution['arguments']['quests'], 'template_id'),
        ] : array_diff_key($result, ['proposed_execution' => TRUE]);
        $response = $this->synthesizeGroundedResponse(
          $utterance,
          'governing_harness_validation_review',
          [
            'current_state' => $snapshot['current_state'] ?? [],
            'validation_summary' => $snapshot['validation_summary'] ?? [],
            'proposed_remediation' => $preview,
            'remediation_applied' => FALSE,
          ],
          $snapshot,
          $surface->label(),
        );
        $result['requires_approval'] = TRUE;
        $result['assistant_response'] = $response['answer'];
        $result['next_steps'] = $is_remediation ? [self::APPROVE_REMEDIATION] : $response['next_steps'];
        return $this->envelope(
          $surface,
          'editor_management_response',
          $snapshot,
          $result,
          $context->validation(),
          [[
            'level' => 'info',
            'text' => 'Preview only. No changes were saved.',
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
      if (isset($scope['lookup_evidence'])) {
        $snapshot['lookup_evidence'] = $scope['lookup_evidence'];
      }
      if ($conversation_context !== '') {
        $snapshot['conversation_context'] = $conversation_context;
      }
    }
    if (empty($scope['delegated_authorization']) && !(
      $surface->id() === DungeonEditorGmSurface::ID
      && $definition->mutating
      && $this->isDirectEditorMutationDirective($utterance)
    ) && ($utterance !== '' || $definition->name === 'apply_storyline_work')) {
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
    elseif (
      $surface->id() === DungeonEditorGmSurface::ID
      && $definition->mutating
      && $this->isDirectEditorMutationDirective($utterance)
    ) {
      $result['assistant_response'] = sprintf('%s completed.', $definition->name);
      $result['next_steps'] ??= ['Validate the dungeon.'];
    }
    elseif (!empty($scope['delegated_authorization'])) {
      $result['assistant_response'] = sprintf(
        '%s completed through the delegated %s authority.',
        $definition->name,
        $surface->label(),
      );
      $result['next_steps'] = $next_steps;
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
    ];
    if (isset($snapshot['troubleshooting']) && is_array($snapshot['troubleshooting'])) {
      $packet = $snapshot['troubleshooting'] ?? [];
      $evidence['troubleshooting'] = is_array($packet) ? $packet : [];
      $evidence['finding_scope'] = 'Resolve or explain only troubleshooting.finding. Do not inventory unrelated warnings.';
    }
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

  private function normalizeStorylineDraftApproval(string $utterance): string {
    $text = mb_strtolower(str_replace("\u{2019}", "'", trim($utterance)));
    return preg_replace('/\s+/u', ' ', rtrim($text, '.! ')) ?? $text;
  }

  /**
   * Recognizes short approvals of an already pending draft edit.
   */
  private function isStorylineDraftApproval(string $approval): bool {
    $target = '(?:it|this|that|(?:the|this|that) (?:proposed )?(?:draft )?(?:edit|changes?|update|plan|proposal))';
    $execution = "(?:apply|approve|accept|execute|save|commit|do|fix) $target";
    return preg_match(
      "/^(?:(?:yes|ok(?:ay)?),? |please |let'?s )*(?:"
      . "(?:continue|proceed|resume|go ahead|carry on|keep going|move forward)(?: with $target| and $execution)?"
      . "|$execution|go for it"
      . "|yes|ok(?:ay)?|approve|approved|confirmed|sounds good)(?:,? please)?$/D",
      $approval,
    ) === 1;
  }

  /**
   * Recognizes explicit author directions that must survive model planning.
   */
  private function storylineDraftWriteIntent(string $utterance): ?bool {
    $text = $this->normalizeStorylineDraftApproval($utterance);
    $polite = "(?:please |let'?s |(?:can|could|would|will) you (?:please )?)*";
    if (preg_match("/^{$polite}(?:preview|propose|suggest|explain|show me)\\b/u", $text) === 1
      || preg_match("/\\b(?:do not|don't|dont|without)\\s+(?:actually\\s+)?(?:fix|apply|save|edit|change|update|write|publish|execute|modify|remove|strip)\\b/u", $text) === 1
      || preg_match('/\b(?:dry run|preview only|for review only)\b/u', $text) === 1) {
      return FALSE;
    }
    if ($this->isStorylineDraftApproval($text)
      || preg_match('/\b(?:review and (?:create|publish)|finalize the storyline|publish the (?:storyline|draft))\b/', $text) === 1
      || preg_match("/^{$polite}(?:fix|resolve|repair|correct|address|apply|save|edit|update|change|add|remove|replace|set|record|link|create|write|implement)\\s+\\S/u", $text) === 1) {
      return TRUE;
    }
    return NULL;
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
      'lookup_evidence' => $tool_name === 'storyline_lookup' ? [] : ($context_snapshot['lookup_evidence'] ?? []),
      'troubleshooting' => $context_snapshot['troubleshooting'] ?? [],
    ];
    // Status and validation reviews take their next step from saved state.
    // The model is not asked for one, so there is a single source for it.
    // A surface without a loaded draft has no saved state to take one from.
    $harness_owns_next_step = in_array($surface_label, ['Storyline Builder', 'Dungeon Editor', 'Room Editor'], TRUE)
      && is_array($context_snapshot['current_state'] ?? NULL) && (
      $tool_name === 'governing_harness_validation_review'
      || ($tool_name === 'governing_harness_context' && in_array($tool_result['focus'] ?? '', ['status', 'validation_review'], TRUE))
    );
    $saved_next_steps = $harness_owns_next_step
      ? $this->harnessManagement->nextStepFromSavedState($context_snapshot, $surface_label)
      : [];
    if ($surface_label === 'Storyline Builder' && $tool_name === 'governing_harness_context') {
      $storyline = $context_snapshot['storyline'] ?? [];
      $grounding['storyline_advice'] = [
        'saved_next_step' => $saved_next_steps[0] ?? '',
        'premise' => $storyline['premise'] ?? '',
        'ending' => $storyline['ending'] ?? '',
        'focus' => 'Use only troubleshooting for the selected warning and its owning object.',
      ];
      $selected = $context_snapshot['troubleshooting']['finding']['message'] ?? '';
      $grounding['validation_summary'] = [
        'valid' => (bool) ($context_snapshot['validation_summary']['valid'] ?? FALSE),
        'error_count' => (int) ($context_snapshot['validation_summary']['error_count'] ?? 0),
        'warning_count' => (int) ($context_snapshot['validation_summary']['warning_count'] ?? 0),
        'selected_warning' => $selected,
        'selected_stage' => $context_snapshot['troubleshooting']['stage'] ?? '',
      ];
    }
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
    // The model occasionally emits a malformed function call. Each sample is
    // checked in full and every rejection is logged; the reply is sampled
    // again once, then the contract failure is raised.
    for ($attempt = 1; $attempt <= self::ANSWER_ATTEMPTS; $attempt++) {
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
          'attempt' => $attempt,
        ],
        $execution_options + ['skip_cache' => TRUE],
      );

      if (!is_array($provider_result) || empty($provider_result['success'])) {
        $this->logger->error('Editor GM grounded response failed: @error', [
          '@error' => is_array($provider_result) ? (string) ($provider_result['error'] ?? 'unknown') : 'malformed provider response',
        ]);
        throw new \RuntimeException('editor_gm_response_model_failed');
      }

      try {
        $decoded = $this->decodeAnswerCall($provider_result, $answer_schema);
        break;
      }
      catch (\DomainException $exception) {
        if ($attempt === self::ANSWER_ATTEMPTS || !str_starts_with($exception->getMessage(), 'editor_gm_response_')) {
          throw $exception;
        }
      }
    }
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
        'next_steps' => $saved_next_steps,
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
- Do not append a next-step instruction or ask the author a question. The
  harness adds the single next step from saved state after your answer.
  Read-only resolution proposals belong in the answer itself.
RULE
      : <<<'RULE'
- On Storyline Builder, return exactly ONE bounded next step. Ask only one
  creative decision at a time, never a list of changes for the author to
  specify. One next step does not mean one possible outcome. Do not turn
  suggestions into an instruction to pick exactly one. Do not hide multiple
  action items inside that single step or the answer's sections. Technical batching is agent-owned; never
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
- An author's stated choice is authoritative story intent, not a technical
  fact or an unanswered question. Never ask again for a decision already made.
  Distinguish the choice from a change actually saved to the draft.
- A published room and a published dungeon are separate facts. A placement
  found only in an active dungeon draft does not mean the dungeon is published.
- Answer directly and explain what the evidence means.
- Never mention tools, tool selection, tool results, schema keys, internal field
  names, or harness mechanics. Translate evidence into natural language.
- Never merely repeat raw output.
- If evidence is insufficient, say exactly what cannot yet be determined.
- Distinguish schema validity from narrative completeness.
- Preserve human authorship; do not make unstated creative choices.
- On Storyline Builder, when reporting a warning as the next open issue or
  answering a request to discuss one, propose a concrete resolution in the
  answer. Focus on the warning the author named, or the saved next warning
  if none was named. Explain what change would clear it; do not hand the
  problem back as "ask the GM to fix it." For a creative decision, recommend
  grounded outcomes as optional suggestions, not an exhaustive menu. Say
  explicitly that the author can combine, branch, defer, or replace them
  when the story allows it. Distinguish branch-specific triggers from
  objective completion criteria. If the author already selected multiple
  outcomes, keep all of them; don't ask which single outcome to keep.
  Label invented outcomes as proposals, not facts or saved changes. For
  example, a conflict left open needs a resolving scene or an explicit
  intentional open thread, not objective completion criteria. If evidence
  is insufficient, identify the specific missing fact instead of guessing.
- When troubleshooting is present, it is the complete scope for the turn.
  Use only its selected finding, owning object, allowed fields and evidence.
  Do not inventory unrelated warnings or summarize the rest of the storyline.
  Ask at most one precise question when troubleshooting.user_question is
  present. If troubleshooting gives schema-valid suggested_values and the
  author selected them, state that the scoped edit is ready; never suggest a
  field outside troubleshooting.editable.fields.
- Never ask for data that the owning schema cannot store. A schema/validator
  mismatch is a system contract defect: identify it as such and stop.
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
