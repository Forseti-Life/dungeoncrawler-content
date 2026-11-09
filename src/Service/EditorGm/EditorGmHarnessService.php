<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
class EditorGmHarnessService {

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

  /**
   * @param iterable<\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface> $surfaces
   *   Every editor surface the harness serves.
   */
  public function __construct(
    iterable $surfaces,
    protected EditorGmIntentParser $intentParser,
    protected ?AIApiService $ai = NULL,
    ?LoggerChannelFactoryInterface $loggerFactory = NULL,
  ) {
    $this->logger = $loggerFactory?->get('dungeoncrawler_content') ?? new NullLogger();
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
      );
    }

    return $this->executeTool($surface, $draft_id, $profile, $intent['tool_name'], $intent['arguments'], $dry_run, '', $scope);
  }

  /**
   * Resolves an author utterance into a grounded tool call.
   *
   * Storyline author requests execute through its revision-safe authority.
   * Other editor surfaces retain explicit approval for mutating tools.
   */
  private function handleUtterance(
    EditorGmSurfaceInterface $surface,
    ?string $draft_id,
    string $profile,
    string $utterance,
    array $scope,
    string $author_message,
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

    $definition = $surface->registry()->get($intent['tool_name'])->definition();
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
    if ($surface->id() === StorylineBuilderGmSurface::ID) {
      if (!in_array($definition->name, [
        'publish_storyline_draft',
        'create_canonical_definition',
        'update_canonical_definition',
      ], TRUE)) {
        throw new \LogicException('storyline_gm_direct_mutation_tool_forbidden');
      }
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
  ): array {
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

    $result = $tool->execute($arguments, $context);
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
    if ($next_steps !== []) {
      $result['next_steps'] = $next_steps;
    }
    $proposed_execution = is_array($result['proposed_execution'] ?? NULL)
      ? $result['proposed_execution']
      : [];
    if (
      $surface->id() === StorylineBuilderGmSurface::ID
      && !$definition->mutating
      && trim((string) ($proposed_execution['tool_name'] ?? '')) !== ''
      && is_array($proposed_execution['arguments'] ?? NULL)
    ) {
      if (!in_array((string) $proposed_execution['tool_name'], [
        'apply_storyline_draft_operations',
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
        FALSE,
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
    if ($utterance !== '') {
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
   * Builds a user-visible chat status line for an Editor GM tool call.
   *
   * @return array{tool: string, message: string}
   */
  public function describeToolCall(string $tool_name, array $arguments = []): array {
    return [
      'tool' => $tool_name,
      'message' => $this->describeToolCallMessage($tool_name, $arguments),
    ];
  }

  /**
   * Maps an Editor GM tool name and arguments to a human-readable "Now ..." line.
   */
  private function describeToolCallMessage(string $tool_name, array $arguments = []): string {
    $family = trim((string) ($arguments['family'] ?? ''));
    $definition_id = trim((string) ($arguments['definition_id'] ?? ''));
    $room_id = trim((string) ($arguments['room_id'] ?? $arguments['room_slot_id'] ?? ''));

    return match (TRUE) {
      $tool_name === 'governing_harness_context' => 'Now looking up current editor context…',
      $tool_name === 'clarification' => 'Now evaluating request options…',
      $tool_name === 'route_to_editor' => 'Now looking up target editor route…',
      $tool_name === 'generate_canonical_definition' => $family !== '' && $definition_id !== ''
        ? sprintf("Now generating canonical %s definition '%s'…", $family, $definition_id)
        : ($family !== '' ? sprintf('Now generating canonical %s definition…', $family) : 'Now generating canonical definition…'),
      $tool_name === 'create_canonical_definition' => $family !== '' && $definition_id !== ''
        ? sprintf("Now creating canonical %s definition '%s'…", $family, $definition_id)
        : 'Now creating canonical definition…',
      $tool_name === 'update_canonical_definition' => $family !== '' && $definition_id !== ''
        ? sprintf("Now updating canonical %s definition '%s'…", $family, $definition_id)
        : 'Now updating canonical definition…',
      $tool_name === 'generate_room_layout' => $room_id !== ''
        ? sprintf("Now generating room layout for '%s'…", $room_id)
        : 'Now generating room layout…',
      $tool_name === 'generate_dungeon_layout' => 'Now generating dungeon layout…',
      str_starts_with($tool_name, 'generate_') => sprintf('Now generating %s…', $this->humanizeToolSuffix(substr($tool_name, 9))),
      str_starts_with($tool_name, 'get_'),
      str_starts_with($tool_name, 'load_'),
      str_starts_with($tool_name, 'list_'),
      str_starts_with($tool_name, 'find_'),
      str_starts_with($tool_name, 'inspect_'),
      str_starts_with($tool_name, 'describe_'),
      str_starts_with($tool_name, 'summarize_') => sprintf('Now looking up %s…', $this->humanizeToolLookupTarget($tool_name, $arguments)),
      str_starts_with($tool_name, 'validate_'),
      str_starts_with($tool_name, 'check_') => sprintf('Now validating %s…', $this->humanizeToolLookupTarget($tool_name, $arguments)),
      str_starts_with($tool_name, 'explain_'),
      str_starts_with($tool_name, 'diff_') => sprintf('Now analyzing %s…', $this->humanizeToolLookupTarget($tool_name, $arguments)),
      str_starts_with($tool_name, 'preview_') => sprintf('Now previewing %s…', $this->humanizeToolSuffix(substr($tool_name, 8))),
      str_starts_with($tool_name, 'plan_') => sprintf('Now planning %s…', $this->humanizeToolSuffix(substr($tool_name, 5))),
      str_starts_with($tool_name, 'apply_') => sprintf('Now applying %s…', $this->humanizeToolSuffix(substr($tool_name, 6))),
      str_starts_with($tool_name, 'publish_') => sprintf('Now publishing %s…', $this->humanizeToolSuffix(substr($tool_name, 8))),
      default => sprintf('Now running %s…', $this->humanizeToolSuffix($tool_name)),
    };
  }

  /**
   * Formats lookup/validation tool names into concise natural phrases.
   */
  private function humanizeToolLookupTarget(string $tool_name, array $arguments = []): string {
    $stripped = preg_replace('/^(get|load|list|find|inspect|describe|summarize|validate|check|explain|diff)_/', '', $tool_name) ?? $tool_name;
    $label = $this->humanizeToolSuffix($stripped);
    $family = trim((string) ($arguments['family'] ?? ''));
    $definition_id = trim((string) ($arguments['definition_id'] ?? ''));
    if ($family !== '' && $definition_id !== '') {
      return sprintf("%s (%s '%s')", $label, $family, $definition_id);
    }
    if ($family !== '') {
      return sprintf('%s (%s)', $label, $family);
    }
    return $label;
  }

  /**
   * Converts a snake_case tool suffix into readable lower-case words.
   */
  private function humanizeToolSuffix(string $suffix): string {
    return trim(str_replace('_', ' ', $suffix));
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
      'validation_summary' => $context_snapshot['validation_summary'] ?? [],
      'publication' => $context_snapshot['publication'] ?? [],
      'conversation_context' => $context_snapshot['conversation_context'] ?? '',
    ];
    $provider_result = $this->ai->invokeModelDirect(
      implode("\n\n", [
        '## Author request',
        trim($utterance),
        '## Selected tool',
        $tool_name,
        '## Authoritative tool result',
        json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        '## Relevant grounded context',
        json_encode($grounding, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        '## Your response (single JSON object)',
      ]),
      EditorGmIntentParser::MODULE,
      self::RESPONSE_OPERATION,
      [
        'draft_id' => (string) ($context_snapshot['draft']['draft_id'] ?? ''),
        'tool_name' => $tool_name,
      ],
      [
        'skip_cache' => TRUE,
        'max_tokens' => EditorGmIntentParser::MODEL_MAX_TOKENS,
        'system_prompt' => $this->responseSystemPrompt($surface_label),
      ],
    );

    if (!is_array($provider_result) || empty($provider_result['success'])) {
      $this->logger->error('Editor GM grounded response failed: @error', [
        '@error' => is_array($provider_result) ? (string) ($provider_result['error'] ?? 'unknown') : 'malformed provider response',
      ]);
      throw new \RuntimeException('editor_gm_response_model_failed');
    }

    $decoded = $this->decodeModelJson((string) ($provider_result['response'] ?? ''));
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
    $next_steps = array_values(array_filter(
      (array) ($decoded['next_steps'] ?? []),
      static fn($step): bool => is_string($step) && trim($step) !== '',
    ));
    if ($surface_label === 'Storyline Builder' && ($next_steps === [] || count($next_steps) > 3)) {
      throw new \DomainException('editor_gm_response_next_steps_invalid');
    }

    return [
      'answer' => $answer,
      'next_steps' => array_map('trim', array_slice($next_steps, 0, 3)),
    ];
  }

  /**
   * Decodes one strict JSON object from a model response.
   */
  private function decodeModelJson(string $response): array {
    $json = trim($response);
    if (str_starts_with($json, '```')) {
      $json = trim(preg_replace('/^```(?:json)?|```$/m', '', $json));
    }
    $start = strpos($json, '{');
    $end = strrpos($json, '}');
    if ($start === FALSE || $end === FALSE || $end < $start) {
      throw new \DomainException('editor_gm_response_not_json');
    }
    try {
      $decoded = json_decode(substr($json, $start, $end - $start + 1), TRUE, 32, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \DomainException('editor_gm_response_not_json');
    }
    if (!is_array($decoded)) {
      throw new \DomainException('editor_gm_response_not_json');
    }
    return $decoded;
  }

  /**
   * Contract for the harness's final grounded answer.
   */
  private function responseSystemPrompt(string $surface_label): string {
    return sprintf(<<<'PROMPT'
You are the Dungeoncrawler canonical %s agent.

The governing harness has provided authoritative management context or an
internal tool result. Analyze that evidence and answer the author's actual
request. Tools are internal capabilities, not the conversational interface.

Hard rules:
- Respond with one JSON object and nothing else.
- Use only the tool result and grounded context as facts. Never invent content.
- Answer directly and explain what the evidence means.
- Never mention tools, tool selection, tool results, schema keys, internal field
  names, or harness mechanics. Translate evidence into natural language.
- Never merely repeat raw output.
- If evidence is insufficient, say exactly what cannot yet be determined.
- Distinguish schema validity from narrative completeness.
- Preserve human authorship; do not make unstated creative choices.
- On Storyline Builder, an object present in authored_objects.generation_specs
  is explicit intent to create that canonical id. Do not ask whether to repoint
  a missing asset when its exact id has an authored generation spec.
- Repeated room ids with distinct authored chapter, scene, or narrative roles
  are not duplicates unless validation reports a duplicate contract violation.
- Compare the author request with the exact persisted changes. Never claim the
  requested scope was completed when a requested change is absent. Never send
  an explicitly requested but unapplied change back as an ordinary next step;
  state that the turn is incomplete instead.
- Organize the answer into two to six short sections with concise headings.
- Keep each section focused and easy to scan. Use at most two short paragraphs
  or a compact newline-separated list in each section.
- Return one to three concrete next steps grounded in the result.

Response shape:
{"sections":[{"heading":"<short heading>","body":"<focused evidence-based content>"}],"next_steps":["<concrete next step>"]}
PROMPT, $surface_label);
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
    elseif ((string) ($tool_context['draft_id'] ?? '') !== $draft_id) {
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
