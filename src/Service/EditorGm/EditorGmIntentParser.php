<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
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
    $this->harnessManagement = $harnessManagement ?? new AgentHarnessManagementService();
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
    if (
      in_array($surface_label, ['Storyline Builder', 'Dungeon Editor', 'Room Editor'], TRUE)
      && $this->isStatusOnlyRequest($utterance)
    ) {
      return [
        'type' => 'direct_response',
        'focus' => 'status',
        'reasoning' => 'The author requested status or next-step guidance without authorizing changes.',
      ];
    }
    if (
      in_array($surface_label, ['Storyline Builder', 'Dungeon Editor', 'Room Editor'], TRUE)
      && $this->isValidationReviewRequest($utterance, $context_snapshot)
    ) {
      return [
        'type' => 'direct_response',
        'focus' => 'validation_review',
        'reasoning' => 'The author requested review of current findings without authorizing remediation.',
      ];
    }
    if ($this->ai === NULL) {
      throw new \RuntimeException('editor_gm_intent_parser_unavailable');
    }

    $execution_options = $this->harnessManagement->selectIntentExecutionProfile($utterance, $surface_label);
    $execution_options['system_prompt'] = $this->systemPrompt($surface_label);

    $result = $this->ai->invokeModelDirect(
      $this->buildPrompt($utterance, $context_snapshot, $registry, $surface_label),
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

    return $this->decodeIntent(
      (string) ($result['response'] ?? ''),
      $registry,
      $surface_label === 'Storyline Builder',
    );
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
      'what is left',
      'how complete is this',
      'how complete is the draft',
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

  /**
   * Parses and validates the model's JSON response against the registry.
   */
  private function decodeIntent(string $response, EditorGmToolRegistry $registry, bool $requires_storyline_choices): array {
    $decoded = $this->decodeJsonObject($response, 'editor_gm_intent_response_not_json');

    $type = (string) ($decoded['type'] ?? '');
    if ($type === 'clarification') {
      $question = trim((string) ($decoded['question'] ?? ''));
      if ($question === '') {
        throw new \DomainException('editor_gm_intent_clarification_empty');
      }
      $options = $this->decodeDecisionOptions($decoded['options'] ?? [], $requires_storyline_choices);
      return ['type' => 'clarification', 'question' => $question, 'options' => $options];
    }
    if ($type === 'direct_response') {
      $focus = trim((string) ($decoded['focus'] ?? 'discussion'));
      if (!in_array($focus, ['status', 'validation_review', 'discussion'], TRUE)) {
        throw new \DomainException('editor_gm_intent_direct_response_focus_invalid');
      }
      return [
        'type' => 'direct_response',
        'focus' => $focus,
        'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
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
      $next_steps = array_values(array_filter(
        (array) ($decoded['next_steps'] ?? []),
        static fn($step): bool => is_string($step) && trim($step) !== '',
      ));
      return [
        'type' => 'task_queue',
        'tasks' => $this->harnessManagement->orderTaskQueue($validated_tasks),
        'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
        'next_steps' => array_map('trim', $next_steps),
      ];
    }
    if ($type !== 'tool_call') {
      throw new \DomainException('editor_gm_intent_type_unsupported');
    }

    return $this->validateToolCallPayload($decoded, $registry);
  }

  /**
   * Validates one tool_call payload against the surface registry.
   */
  private function validateToolCallPayload(array $decoded, EditorGmToolRegistry $registry): array {
    $tool_name = (string) ($decoded['tool_name'] ?? '');
    if (!$registry->has($tool_name)) {
      throw new \DomainException(sprintf('editor_gm_intent_tool_unsupported:%s', $tool_name));
    }
    $arguments = $decoded['arguments'] ?? [];
    if (!is_array($arguments)) {
      throw new \DomainException('editor_gm_intent_arguments_invalid');
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

    $next_steps = array_values(array_filter(
      (array) ($decoded['next_steps'] ?? []),
      static fn($step): bool => is_string($step) && trim($step) !== '',
    ));
    return [
      'type' => 'tool_call',
      'tool_name' => $tool_name,
      'arguments' => $arguments,
      'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
      'next_steps' => array_map('trim', $next_steps),
    ];
  }

  /**
   * Decodes one model-emitted JSON object.
   */
  private function decodeJsonObject(string $response, string $error_code): array {
    $json = trim($response);
    if (str_starts_with($json, '```')) {
      $json = trim(preg_replace('/^```(?:json)?|```$/m', '', $json));
    }
    $start = strpos($json, '{');
    $end = strrpos($json, '}');
    if ($start === FALSE || $end === FALSE || $end < $start) {
      throw new \DomainException($error_code);
    }

    try {
      $decoded = json_decode(substr($json, $start, $end - $start + 1), TRUE, 32, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \DomainException($error_code);
    }
    if (!is_array($decoded)) {
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
        'selection' => trim((string) ($option['selection'] ?? '')),
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
    $mutation_rule = $surface_label === 'Storyline Builder'
      ? 'For Storyline Builder, use plan_storyline_draft_operations for explicitly requested draft changes and plan_storyline_remediation only when the author explicitly asks to fix or create missing canonical assets; the harness may immediately apply that authorized plan through the owning authorities.'
      : 'Prefer planning tools over execution tools. A mutating tool call is a proposal that the author must approve; it is never applied by you.';
    return sprintf(<<<'PROMPT'
You are the intent parser for the Dungeoncrawler canonical %s assistant.

Your only job is to classify an author's request for the governing harness,
using only the grounded editor context you are given. Select an internal tool
only when the request requires one.

Hard rules:
- Respond with a single JSON object and nothing else. No prose, no code fences.
- You may only use a tool name from the provided toolset. Never invent one.
- Never invent canonical room ids, placement ids, hex coordinates, port ids,
  instance ids, or catalog definition ids. Use only canonical values present
  in the grounded context or values the author stated. For a new Storyline
  draft record, derive its required draft-local id deterministically from the
  author-provided name or purpose when the author did not supply one.
- If the request is materially ambiguous, or you would have to guess a
  required creative decision that changes the requested object's identity,
  return a clarification instead of a tool call.
- For basic questions, discussion, status, next-step guidance, and requests to
  inspect or review grounded findings without changing anything, return
  direct_response. The governing harness answers from its grounded context;
  do not route those requests into planning, remediation, generation, or
  execution tools.
- %s
- Obey every nested argument shape in the selected tool definition exactly.
  Before returning a Storyline draft operation plan, verify every operation
  type and JSON-pointer path against the grounded storyline payload and its
  collection_contracts. A schema-declared collection may be omitted only
  because it has no rows yet and remains a valid append target.
- For every Storyline collection append or replacement, use only fields listed
  in the grounded collection_contracts, include every required field, and obey
  every field_constraints type and enum. Never invent plausible fields or
  values absent from that collection contract.
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
- Do the work the author clearly requested. Do not ask a clarification when
  the grounded context and request already provide the required decisions.
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
- On Storyline Builder, any request that asks to add, create, author, revise,
  update, remove, or reconcile draft content must use
  plan_storyline_draft_operations. Put every compatible requested draft change
  into one operation batch. Do not substitute a status, schema, synopsis,
  preview, or advisory tool call for requested mutations.
- On Storyline Builder, a request that explicitly asks to fix, resolve,
  reconcile, generate, create, or save canonical library assets (items,
  actors, creatures, obstacles, hazards, traps, or rooms) for the storyline
  or its validation findings must use plan_storyline_remediation (or
  generate_canonical_definition / create_canonical_definition when creating a
  single standalone canonical definition).
  The remediation planner derives missing assets from validation findings,
  authored object specs (including any new /objects/items/generation_specs or
  /objects/actors/generation_specs included in operations), and optional
  explicit definitions; include any necessary draft operations (such as adding
  a new item to /objects/items/generation_specs) in the same
  plan_storyline_remediation call so both the storyline spec and the canonical
  library entry are persisted together. Do not merely explain or route these
  findings.
  A request to inspect, review, explain, open, or go through findings does not
  authorize remediation and must use direct_response.
- On Storyline Builder, when the author explicitly asks to publish the current
  draft or save the storyline template to the canonical library, use
  publish_storyline_draft with the grounded current revision (or
  plan_storyline_remediation when saving/persisting missing canonical object
  definitions into the library). Never substitute a preview, readiness check,
  clarification, or draft mutation plan for an explicit publication or library
  save command.
- Missing optional Storyline fields are not blockers. Infer them from existing
  draft relationships when there is one clear match; otherwise leave them
  empty. Validation errors elsewhere in an incomplete draft do not block a
  valid requested partial update.
- For a Storyline Builder request to create any underspecified object, return
  exactly three meaningfully different options: direct, complicating, and
  transformative. Never select one. Each option must state its narrative
  purpose, progression effect, introduced relationships, and main tradeoff.
- A Storyline object is not underspecified when the author has supplied its
  identity, purpose, defining content, and intended narrative effect. A
  missing draft-local id or optional scene, quest, owner, prerequisite, or
  reward does not make it underspecified.
- Tool selection does not answer the author. The agent harness will analyze the
  executed result and produce the final response and next steps afterward.

Response shapes:
{"type":"tool_call","tool_name":"<registered tool>","arguments":{...},"reasoning":"<one short sentence>","next_steps":["<up to 3 concrete next steps>"]}
{"type":"task_queue","tasks":[{"tool_name":"<registered tool>","arguments":{...},"reasoning":"<step summary>"}],"reasoning":"<overall summary>","next_steps":["<up to 3 concrete next steps>"]}
{"type":"direct_response","focus":"status|validation_review|discussion","reasoning":"<one short sentence>"}
{"type":"clarification","question":"<decision the author must make>","options":[{"label":"<short name>","description":"<concept, purpose, relationships, and progression effect>","tradeoff":"<benefit and cost>","selection":"<self-contained direction if selected>"}]}
PROMPT, $surface_label, $mutation_rule);
  }

  /**
   * Builds the grounded prompt for one turn.
   */
  private function buildPrompt(string $utterance, array $context_snapshot, EditorGmToolRegistry $registry, string $surface_label = ''): string {
    $manifest = $registry->manifest();
    $tools = $this->harnessManagement->filterToolsForNaturalLanguage($manifest, $surface_label);
    $grounding = $this->harnessManagement->compactContextForIntent($context_snapshot, $surface_label);

    return implode("\n\n", [
      '## Grounded editor context',
      $this->harnessManagement->encodeCompactJson($grounding),
      '## Registered toolset',
      $this->harnessManagement->encodeCompactJson($tools),
      '## Supported command types for planning',
      implode(', ', $manifest['supported_command_types']),
      '## Required payload keys per command type',
      $this->harnessManagement->encodeCompactJson($manifest['command_payload_contracts']),
      '## Author request',
      $utterance,
      '## Your response (single JSON object)',
    ]);
  }

}
