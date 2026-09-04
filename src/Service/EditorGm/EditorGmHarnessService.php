<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Editor-scoped GM harness entrypoint.
 *
 * This is the sibling of the runtime GM subsystem, not an extension of it. It
 * orchestrates grounded editor context and explicit tool execution, while every
 * read and write resolves through RoomEditorService. Campaign runtime authority
 * is deliberately unreachable from here.
 */
class EditorGmHarnessService {

  public const REQUEST_CONTRACT_VERSION = 'editor-gm-request-v1';
  public const RESPONSE_CONTRACT_VERSION = 'editor-gm-response-v1';
  public const TOOL_ID_ROOM_EDITOR = 'room_editor';

  public const VALIDATION_PROFILES = ['editing', 'preview', 'publication'];

  private const ROUTE_FAMILY_BY_TOOL_FAMILY = [
    EditorGmToolDefinition::FAMILY_CONTEXT => 'deterministic_editor_tool',
    EditorGmToolDefinition::FAMILY_VALIDATION => 'deterministic_editor_tool',
    EditorGmToolDefinition::FAMILY_PLANNING => 'deterministic_editor_tool',
    EditorGmToolDefinition::FAMILY_EXECUTION => 'editor_command_execution',
    EditorGmToolDefinition::FAMILY_DEFINITION => 'editor_definition_authority',
  ];

  public function __construct(
    protected RoomEditorService $roomEditor,
    protected EditorGmToolRegistry $registry,
    protected RoomEditorGmContextAssembler $contextAssembler,
    protected EditorGmIntentParser $intentParser,
  ) {}

  /**
   * Returns the grounded context snapshot and tool manifest for one draft.
   */
  public function describe(string $draft_id, string $profile = 'editing'): array {
    $context = $this->createContext($draft_id, $profile);

    return $this->envelope(
      'editor_context_snapshot',
      $this->contextAssembler->assemble($context),
      [],
      $context->validation(),
    );
  }

  /**
   * Executes one editor GM request envelope.
   */
  public function handle(string $draft_id, array $request): array {
    [$profile, $intent, $dry_run] = $this->parseRequest($draft_id, $request);

    if ($intent['type'] === 'natural_language') {
      return $this->handleUtterance($draft_id, $profile, $intent['utterance']);
    }

    return $this->executeTool($draft_id, $profile, $intent['tool_name'], $intent['arguments'], $dry_run);
  }

  /**
   * Resolves an author utterance into a grounded tool call.
   *
   * Natural language may read and it may propose, but it may never mutate.
   * Read-only tools run immediately; anything mutating is returned as a
   * proposal the author must approve with an explicit tool call.
   */
  private function handleUtterance(string $draft_id, string $profile, string $utterance): array {
    $context = $this->createContext($draft_id, $profile);
    $snapshot = $this->contextAssembler->assemble($context);
    $intent = $this->intentParser->parse($utterance, $snapshot);

    if ($intent['type'] === 'clarification') {
      return $this->envelope(
        'editor_intent_proposal',
        $snapshot,
        ['intent' => 'clarification', 'question' => $intent['question']],
        $context->validation(),
        [['level' => 'info', 'text' => $intent['question']]],
      );
    }

    $definition = $this->registry->get($intent['tool_name'])->definition();
    if (!$definition->mutating) {
      return $this->executeTool($draft_id, $profile, $intent['tool_name'], $intent['arguments'], FALSE, $intent['reasoning']);
    }

    return $this->envelope(
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
    string $draft_id,
    string $profile,
    string $tool_name,
    array $arguments,
    bool $dry_run,
    string $reasoning = '',
  ): array {
    $tool = $this->registry->get($tool_name);
    $definition = $tool->definition();
    $context = $this->createContext($draft_id, $profile);

    if ($dry_run && $definition->mutating) {
      return $this->envelope(
        self::ROUTE_FAMILY_BY_TOOL_FAMILY[$definition->family],
        $this->contextAssembler->assemble($context),
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
      self::ROUTE_FAMILY_BY_TOOL_FAMILY[$definition->family],
      $this->contextAssembler->assemble($context),
      $result,
      $context->validation(),
      [[
        'level' => 'success',
        'text' => $reasoning !== '' ? $reasoning : sprintf('%s completed.', $definition->name),
      ]],
      $this->extractCommandPlan($result),
    );
  }

  /**
   * Lifts a proposed command plan into the response envelope.
   *
   * Plans are surfaced as a separate, explicitly approvable envelope field so
   * the client can never mistake a proposal for something already applied.
   */
  private function extractCommandPlan(array $result): array {
    $plan = $result['command_plan'] ?? NULL;
    if (!is_array($plan)) {
      return [];
    }
    if (($plan['schema_version'] ?? NULL) !== 'editor-gm-command-plan-v1') {
      throw new \DomainException('editor_gm_command_plan_schema_version_invalid');
    }
    $steps = $plan['steps'] ?? NULL;
    if (!is_array($steps) || $steps === []) {
      throw new \DomainException('editor_gm_command_plan_empty');
    }
    return array_values($steps);
  }

  /**
   * Returns the declared toolset without touching draft state.
   */
  public function manifest(): array {
    return $this->registry->manifest();
  }

  /**
   * Validates and unpacks a request envelope.
   */
  private function parseRequest(string $draft_id, array $request): array {
    if (($request['schema_version'] ?? NULL) !== self::REQUEST_CONTRACT_VERSION) {
      throw new \InvalidArgumentException('editor_gm_request_schema_version_invalid');
    }

    $tool_context = $request['tool_context'] ?? NULL;
    if (!is_array($tool_context)) {
      throw new \InvalidArgumentException('editor_gm_tool_context_required');
    }
    if (($tool_context['tool_id'] ?? NULL) !== self::TOOL_ID_ROOM_EDITOR) {
      throw new \InvalidArgumentException('editor_gm_tool_id_unsupported');
    }
    if ((string) ($tool_context['draft_id'] ?? '') !== $draft_id) {
      throw new \InvalidArgumentException('editor_gm_draft_id_mismatch');
    }
    $profile = (string) ($tool_context['validation_profile'] ?? 'editing');
    if (!in_array($profile, self::VALIDATION_PROFILES, TRUE)) {
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
   * Builds grounded tool context for one draft.
   */
  private function createContext(string $draft_id, string $profile): EditorGmToolContext {
    if (!in_array($profile, self::VALIDATION_PROFILES, TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    return new EditorGmToolContext($draft_id, $profile, $this->roomEditor);
  }

  /**
   * Projects one editor_gm_response envelope.
   */
  private function envelope(
    string $route_family,
    array $context_snapshot,
    array $tool_result,
    ?array $validation,
    array $messages = [],
    array $command_plan = [],
  ): array {
    return [
      'schema_version' => self::RESPONSE_CONTRACT_VERSION,
      'tool_id' => self::TOOL_ID_ROOM_EDITOR,
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
