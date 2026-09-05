<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

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

  /**
   * @param iterable<\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface> $surfaces
   *   Every editor surface the harness serves.
   */
  public function __construct(
    iterable $surfaces,
    protected EditorGmIntentParser $intentParser,
  ) {
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
  public function describe(string $surface_id, ?string $draft_id, string $profile = 'editing'): array {
    $surface = $this->surface($surface_id);
    $draft_id = $this->resolveDraftId($surface, $draft_id);
    $context = $this->createContext($surface, $draft_id, $profile);

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
  public function handle(string $surface_id, ?string $draft_id, array $request): array {
    $surface = $this->surface($surface_id);
    $draft_id = $this->resolveDraftId($surface, $draft_id);
    [$profile, $intent, $dry_run] = $this->parseRequest($surface, $draft_id, $request);

    if ($intent['type'] === 'natural_language') {
      return $this->handleUtterance($surface, $draft_id, $profile, $intent['utterance']);
    }

    return $this->executeTool($surface, $draft_id, $profile, $intent['tool_name'], $intent['arguments'], $dry_run);
  }

  /**
   * Resolves an author utterance into a grounded tool call.
   *
   * Natural language may read and it may propose, but it may never mutate.
   * Read-only tools run immediately; anything mutating is returned as a
   * proposal the author must approve with an explicit tool call.
   */
  private function handleUtterance(EditorGmSurfaceInterface $surface, ?string $draft_id, string $profile, string $utterance): array {
    $context = $this->createContext($surface, $draft_id, $profile);
    $snapshot = $surface->assembler()->assemble($context);
    $intent = $this->intentParser->parse($utterance, $snapshot, $surface->registry(), $surface->label());

    if ($intent['type'] === 'clarification') {
      return $this->envelope(
        $surface,
        'editor_intent_proposal',
        $snapshot,
        ['intent' => 'clarification', 'question' => $intent['question']],
        $context->validation(),
        [['level' => 'info', 'text' => $intent['question']]],
      );
    }

    $definition = $surface->registry()->get($intent['tool_name'])->definition();
    if (!$definition->mutating) {
      return $this->executeTool($surface, $draft_id, $profile, $intent['tool_name'], $intent['arguments'], FALSE, $intent['reasoning']);
    }

    return $this->envelope(
      $surface,
      'editor_intent_proposal',
      $snapshot,
      [
        'intent' => 'proposed_execution',
        'reasoning' => $intent['reasoning'],
        'requires_approval' => TRUE,
        'proposed_execution' => [
          'tool_name' => $definition->name,
          'authority' => $definition->authority,
          'arguments' => $intent['arguments'],
        ],
      ],
      $context->validation(),
      [[
        'level' => 'warning',
        'text' => sprintf('%s changes saved state and was not run. Approve it explicitly to apply.', $definition->name),
      ]],
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
  ): array {
    $tool = $surface->registry()->get($tool_name);
    $definition = $tool->definition();
    $context = $this->createContext($surface, $draft_id, $profile);

    if ($dry_run && $definition->mutating) {
      return $this->envelope(
        $surface,
        self::ROUTE_FAMILY_BY_TOOL_FAMILY[$definition->family],
        $surface->assembler()->assemble($context),
        [
          'tool' => $definition->name,
          'dry_run' => TRUE,
          'would_mutate' => TRUE,
          'authority' => $definition->authority,
        ],
        $context->validation(),
        [['level' => 'info', 'text' => sprintf('Dry run: %s was not executed.', $definition->name)]],
      );
    }

    $result = $tool->execute($arguments, $context);
    $result['tool'] = $definition->name;
    $result['dry_run'] = FALSE;
    if ($reasoning !== '') {
      $result['reasoning'] = $reasoning;
    }

    return $this->envelope(
      $surface,
      self::ROUTE_FAMILY_BY_TOOL_FAMILY[$definition->family],
      $surface->assembler()->assemble($context),
      $result,
      $context->validation(),
      [[
        'level' => 'success',
        'text' => $reasoning !== '' ? $reasoning : sprintf('%s completed.', $definition->name),
      ]],
      $this->extractCommandPlan($surface, $result),
    );
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
  private function parseRequest(EditorGmSurfaceInterface $surface, ?string $draft_id, array $request): array {
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
    if ($surface->scope() === EditorGmSurfaceInterface::SCOPE_SUITE) {
      if (array_key_exists('draft_id', $tool_context)) {
        throw new \InvalidArgumentException(sprintf('editor_gm_draft_not_applicable:%s', $surface->id()));
      }
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
      $parsed_intent = ['type' => 'natural_language', 'utterance' => $utterance];
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

    return [$profile, $parsed_intent, !empty($options['dry_run'])];
  }

  /**
   * Binds the route's draft id to the surface's scope, or refuses.
   */
  private function resolveDraftId(EditorGmSurfaceInterface $surface, ?string $draft_id): ?string {
    if ($surface->scope() === EditorGmSurfaceInterface::SCOPE_SUITE) {
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
  private function createContext(EditorGmSurfaceInterface $surface, ?string $draft_id, string $profile): EditorGmToolContext {
    if (!in_array($profile, $surface->validationProfiles(), TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    return $surface->createContext($draft_id, $profile);
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
  ): array {
    return [
      'schema_version' => self::RESPONSE_CONTRACT_VERSION,
      'tool_id' => $surface->id(),
      'route_family' => $route_family,
      'context_snapshot' => $context_snapshot,
      'tool_result' => $tool_result,
      'command_plan' => $command_plan,
      'validation' => $validation,
      'messages' => $messages,
      'errors' => [],
    ];
  }

}
