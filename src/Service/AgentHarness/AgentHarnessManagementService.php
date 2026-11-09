<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\AgentHarness;

use Drupal\ai_conversation\Service\AIApiService;

/**
 * Shared agentic harness management service across all DungeonCrawler agents.
 *
 * Centralizes:
 * - Task-scoped model execution profiles (bounded thinking, token budgets,
 *   JSON object response format, and hard per-call timeouts).
 * - Grounded context diet & compact serialization (stripping redundant
 *   snapshot copies and non-selectable internal tools).
 * - Multi-step task queue decomposition & dependency-ordered serial execution
 *   with time-budget guarding.
 * - Single-pass / deterministic grounded response synthesis after mutations.
 * - Shared defect / complaint / backlog suggestion tool (`submit_backlog_suggestion`)
 *   available to every harnessed agent.
 */
class AgentHarnessManagementService {

  public const TOOL_SUBMIT_BACKLOG_SUGGESTION = 'submit_backlog_suggestion';

  public const SUGGESTION_CATEGORIES = [
    'technical_improvement',
    'content_update',
    'safety_feature',
    'community_initiative',
    'partnership',
    'general_feedback',
    'other',
  ];

  public const PROFILE_INTENT_ROUTING = 'intent_routing';
  public const PROFILE_CREATIVE_REASONING = 'creative_reasoning';
  public const PROFILE_STRUCTURED_PATCH = 'structured_patch';
  public const PROFILE_RESPONSE_SYNTHESIS = 'response_synthesis';
  public const PROFILE_RUNTIME_DIALOGUE = 'runtime_dialogue';
  public const PROFILE_TACTICAL_DECISION = 'tactical_decision';
  public const PROFILE_CHARACTER_ASSISTANT = 'character_assistant';
  public const PROFILE_CLASSIFICATION_GATE = 'classification_gate';
  public const PROFILE_NPC_PSYCHOLOGY = 'npc_psychology';
  public const PROFILE_NARRATION_BEAT = 'narration_beat';
  public const PROFILE_SCHEMA_VALIDATION = 'schema_validation';
  public const PROFILE_CANONICAL_GENERATION = 'canonical_generation';

  /**
   * Default time budget (in seconds) before serial task execution yields.
   */
  public const DEFAULT_SERIAL_TIME_BUDGET_SECONDS = 45.0;

  /**
   * Storyline Builder natural-language selectable entry tools.
   */
  private const STORYLINE_NL_TOOLS = [
    'plan_storyline_draft_operations',
    'plan_storyline_remediation',
    'publish_storyline_draft',
    'generate_canonical_definition',
    'create_canonical_definition',
    'update_canonical_definition',
    'load_canonical_definition',
    'list_catalog_definitions',
    'route_to_editor',
    self::TOOL_SUBMIT_BACKLOG_SUGGESTION,
  ];

  public function __construct(
    protected ?AIApiService $aiApiService = NULL,
  ) {}

  /**
   * Returns the shared backlog defect/complaint/suggestion tool descriptor.
   *
   * @return array<string, mixed>
   */
  public function getBacklogSuggestionToolDescriptor(): array {
    return [
      'name' => self::TOOL_SUBMIT_BACKLOG_SUGGESTION,
      'family' => 'context',
      'mutating' => FALSE,
      'summary' => 'Log a confirmed defect, bug report, complaint, or feature suggestion to the DungeonCrawler project backlog.',
      'arguments' => [
        [
          'name' => 'summary',
          'type' => 'string',
          'required' => TRUE,
          'description' => 'Concise 1-3 sentence implementation-ready summary of the defect, complaint, or suggestion.',
        ],
        [
          'name' => 'category',
          'type' => 'string',
          'required' => FALSE,
          'description' => 'One of: technical_improvement (default for defects/bugs/complaints), content_update, safety_feature, community_initiative, partnership, general_feedback, other.',
        ],
        [
          'name' => 'original_message',
          'type' => 'string',
          'required' => FALSE,
          'description' => 'Original user complaint, defect report, or feedback text.',
        ],
      ],
    ];
  }

  /**
   * Logs a defect, complaint, or feature suggestion via AIApiService::createBacklogSuggestion().
   *
   * @param string|array<string, mixed> $summary_or_arguments
   *   Implementation-ready summary string, or an arguments array containing `summary`, `category`, and `original_message`.
   * @param string|array<string, mixed> $original_message_or_context
   *   Original user message string (when arg 1 is a string), or context array (when arg 1 is an arguments array).
   * @param string $category
   *   Suggestion category (defaults to 'technical_improvement' for defects/complaints).
   * @param array<string, mixed> $context
   *   Optional harness/surface/campaign/draft metadata.
   *
   * @return array<string, mixed>
   *   Result envelope containing `logged`, `suggestion_nid`, `title`, `summary`, `category`.
   */
  public function submitBacklogSuggestion(
    string|array $summary_or_arguments,
    string|array $original_message_or_context = '',
    string $category = 'technical_improvement',
    array $context = [],
  ): array {
    if (is_array($summary_or_arguments)) {
      $summary = trim((string) ($summary_or_arguments['summary'] ?? ''));
      $category = trim((string) ($summary_or_arguments['category'] ?? $category));
      $original_message = trim((string) ($summary_or_arguments['original_message'] ?? ''));
      if (is_array($original_message_or_context)) {
        $context = $original_message_or_context;
      }
    }
    else {
      $summary = trim($summary_or_arguments);
      $original_message = is_string($original_message_or_context) ? trim($original_message_or_context) : '';
    }
    if ($summary === '') {
      throw new \InvalidArgumentException('backlog_suggestion_summary_required');
    }
    $normalized_category = strtolower(trim($category));
    if (!in_array($normalized_category, self::SUGGESTION_CATEGORIES, TRUE)) {
      $normalized_category = 'technical_improvement';
    }
    $original_message = $original_message !== '' ? $original_message : $summary;

    $ai = $this->resolveAiApiService();
    $node = $ai?->createBacklogSuggestion($summary, $original_message, $normalized_category, $context);
    $nid = $node !== NULL ? (int) $node->id() : NULL;
    $title = mb_strlen($summary) > 100 ? mb_substr($summary, 0, 97) . '...' : $summary;

    return [
      'success' => $node !== NULL,
      'logged' => $node !== NULL,
      'tool' => self::TOOL_SUBMIT_BACKLOG_SUGGESTION,
      'dry_run' => FALSE,
      'suggestion_nid' => $nid,
      'title' => $title,
      'summary' => $summary,
      'category' => $normalized_category,
      'original_message' => $original_message,
      'context' => $context,
    ];
  }

  /**
   * Resolves AIApiService from constructor injection or Drupal container.
   */
  private function resolveAiApiService(): ?AIApiService {
    if ($this->aiApiService !== NULL) {
      return $this->aiApiService;
    }
    if (class_exists('\Drupal') && \Drupal::hasContainer() && \Drupal::getContainer()->has('ai_conversation.ai_api_service')) {
      $service = \Drupal::service('ai_conversation.ai_api_service');
      if ($service instanceof AIApiService) {
        return $service;
      }
    }
    return NULL;
  }

  /**
   * Returns canonical AIApiService invocation options for a harness task profile.
   *
   * @param string $profile
   *   One of the PROFILE_* constants.
   * @param array<string,mixed> $overrides
   *   Optional caller overrides (e.g. system_prompt, skip_cache, max_tokens).
   *
   * @return array<string,mixed>
   *   Normalized options for AIApiService::invokeModelDirect().
   */
  public function resolveExecutionOptions(string $profile, array $overrides = []): array {
    $defaults = match ($profile) {
      self::PROFILE_INTENT_ROUTING => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 4096,
        'timeout' => 45,
        'json_object' => TRUE,
      ],
      self::PROFILE_CREATIVE_REASONING => [
        'skip_cache' => TRUE,
        'thinking' => 'enabled',
        'thinking_budget_tokens' => 1024,
        'max_tokens' => 4096,
        'timeout' => 45,
        'json_object' => TRUE,
      ],
      self::PROFILE_STRUCTURED_PATCH => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 4096,
        'timeout' => 40,
        'json_object' => TRUE,
      ],
      self::PROFILE_RESPONSE_SYNTHESIS => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 2048,
        'timeout' => 30,
        'json_object' => TRUE,
      ],
      self::PROFILE_RUNTIME_DIALOGUE => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 1200,
        'timeout' => 25,
      ],
      self::PROFILE_TACTICAL_DECISION => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 1024,
        'timeout' => 20,
        'json_object' => TRUE,
      ],
      self::PROFILE_CHARACTER_ASSISTANT => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 2400,
        'timeout' => 30,
        'json_object' => TRUE,
      ],
      self::PROFILE_CLASSIFICATION_GATE => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 64,
        'timeout' => 10,
      ],
      self::PROFILE_NPC_PSYCHOLOGY => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 300,
        'timeout' => 15,
        'json_object' => TRUE,
      ],
      self::PROFILE_NARRATION_BEAT => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 400,
        'timeout' => 20,
      ],
      self::PROFILE_SCHEMA_VALIDATION => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 500,
        'timeout' => 20,
        'json_object' => TRUE,
      ],
      self::PROFILE_CANONICAL_GENERATION => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 4096,
        'timeout' => 60,
        'json_object' => TRUE,
      ],
      default => [
        'skip_cache' => TRUE,
        'thinking' => 'disabled',
        'max_tokens' => 4096,
        'timeout' => 45,
      ],
    };

    return array_merge($defaults, $overrides);
  }

  /**
   * Maps a known runtime operation name to its canonical execution profile.
   */
  public function resolveProfileForOperation(string $operation): string {
    return match (TRUE) {
      $operation === 'editor_gm_intent' => self::PROFILE_INTENT_ROUTING,
      $operation === 'editor_gm_assist' => self::PROFILE_SYNTHESIS,
      $operation === 'npc_interjection_eval_single' => self::PROFILE_CLASSIFICATION_GATE,
      $operation === 'player_action_recommendation',
      $operation === 'encounter_npc_recommendation' => self::PROFILE_TACTICAL_DECISION,
      $operation === 'npc_inner_monologue' => self::PROFILE_NPC_PSYCHOLOGY,
      $operation === 'room_description_metadata_alignment' => self::PROFILE_SCHEMA_VALIDATION,
      $operation === 'npc_character_sheet_generation',
      str_starts_with($operation, 'canonical_generation_') => self::PROFILE_CANONICAL_GENERATION,
      $operation === 'character_creation_gm_chat' => self::PROFILE_CHARACTER_ASSISTANT,
      str_starts_with($operation, 'ai_gm_'),
      $operation === 'speech_narration',
      $operation === 'scene_beat_narration',
      $operation === 'player_chat_suggestion' => self::PROFILE_NARRATION_BEAT,
      default => self::PROFILE_RUNTIME_DIALOGUE,
    };
  }

  /**
   * Builds a user-visible chat status line for any agent tool call.
   *
   * @param string $tool_name
   *   The internal tool name (Editor GM, Runtime GM, or Character Creation GM).
   * @param array<string,mixed> $arguments
   *   Tool arguments.
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
   * Maps any agent tool name and arguments to a human-readable "Now ..." line.
   */
  public function describeToolCallMessage(string $tool_name, array $arguments = []): string {
    $family = trim((string) ($arguments['family'] ?? ''));
    $definition_id = trim((string) ($arguments['definition_id'] ?? ''));
    $room_id = trim((string) ($arguments['room_id'] ?? $arguments['room_slot_id'] ?? ''));

    return match (TRUE) {
      $tool_name === self::TOOL_SUBMIT_BACKLOG_SUGGESTION => 'Now logging defect or feedback to project backlog…',
      $tool_name === 'governing_harness_context' => 'Now looking up current editor context…',
      $tool_name === 'clarification' => 'Now evaluating request options…',
      $tool_name === 'route_to_editor' => 'Now looking up target editor route…',
      $tool_name === 'query_campaign_database' => 'Now looking up campaign database records…',
      $tool_name === 'inspect_storyline_branches' => 'Now looking up storyline branch state…',
      $tool_name === 'resolve_storyline_branch' => 'Now resolving storyline branch decision…',
      $tool_name === 'create_character_faction' => 'Now generating canonical faction for character…',
      $tool_name === 'apply_character_draft_updates' => 'Now applying character sheet updates…',
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
      str_starts_with($tool_name, 'summarize_'),
      str_starts_with($tool_name, 'query_') => sprintf('Now looking up %s…', $this->humanizeToolLookupTarget($tool_name, $arguments)),
      str_starts_with($tool_name, 'validate_'),
      str_starts_with($tool_name, 'check_') => sprintf('Now validating %s…', $this->humanizeToolLookupTarget($tool_name, $arguments)),
      str_starts_with($tool_name, 'explain_'),
      str_starts_with($tool_name, 'diff_') => sprintf('Now analyzing %s…', $this->humanizeToolLookupTarget($tool_name, $arguments)),
      str_starts_with($tool_name, 'preview_') => sprintf('Now previewing %s…', $this->humanizeToolSuffix(substr($tool_name, 8))),
      str_starts_with($tool_name, 'plan_') => sprintf('Now planning %s…', $this->humanizeToolSuffix(substr($tool_name, 5))),
      str_starts_with($tool_name, 'apply_') => sprintf('Now applying %s…', $this->humanizeToolSuffix(substr($tool_name, 6))),
      str_starts_with($tool_name, 'modify_') => sprintf('Now updating %s…', $this->humanizeToolSuffix(substr($tool_name, 7))),
      str_starts_with($tool_name, 'publish_') => sprintf('Now publishing %s…', $this->humanizeToolSuffix(substr($tool_name, 8))),
      default => sprintf('Now running %s…', $this->humanizeToolSuffix($tool_name)),
    };
  }

  /**
   * Formats lookup/validation tool names into concise natural phrases.
   */
  private function humanizeToolLookupTarget(string $tool_name, array $arguments = []): string {
    $stripped = preg_replace('/^(get|load|list|find|inspect|describe|summarize|validate|check|explain|diff|query)_/', '', $tool_name) ?? $tool_name;
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
   * Selects the optimal execution profile for an utterance on an editor surface.
   *
   * Uses fast non-thinking JSON routing for standard edits, remediation, and
   * status/review turns; enables bounded creative reasoning only when the
   * author asks for open-ended creative ideation ("give me 3 options/ideas").
   */
  public function selectIntentExecutionProfile(string $utterance, string $surface_label): array {
    $normalized = mb_strtolower(trim($utterance));
    $is_open_creative_ideation = $surface_label === 'Storyline Builder'
      && preg_match('/\b(brainstorm|three options|3 options|pitch|what if|suggest ideas)\b/u', $normalized) === 1
      && preg_match('/\b(option [123]|i select|save|publish|apply|fix|update|add|set|create)\b/u', $normalized) !== 1;

    if ($is_open_creative_ideation) {
      return $this->resolveExecutionOptions(self::PROFILE_CREATIVE_REASONING);
    }

    return $this->resolveExecutionOptions(self::PROFILE_INTENT_ROUTING);
  }

  /**
   * Compacts a surface context snapshot for LLM intent parsing.
   *
   * Removes duplicate embedded trees (such as `storyline.canonical_template`
   * and heavy SVG/timeline graph projections) while preserving 100% of
   * authoritative fields, collection contracts, and validation findings.
   */
  public function compactContextForIntent(array $context_snapshot, string $surface_label = ''): array {
    $grounding = ['draft' => $context_snapshot['draft'] ?? []];
    foreach ($context_snapshot as $key => $value) {
      if (in_array($key, [
        'tool_id',
        'draft',
        'validation_summary',
        'publication',
        'authority_boundary',
        'assistant',
        'tools',
        'narrative_analysis',
      ], TRUE)) {
        continue;
      }
      if ($key === 'storyline' && is_array($value)) {
        unset($value['canonical_template']);
      }
      $grounding[$key] = $value;
    }

    if (isset($context_snapshot['narrative_analysis']['metrics'])) {
      $grounding['narrative_metrics'] = $context_snapshot['narrative_analysis']['metrics'];
    }
    $grounding['validation_summary'] = $context_snapshot['validation_summary'] ?? [];
    if (!empty($context_snapshot['publication'])) {
      $grounding['publication'] = $context_snapshot['publication'];
    }

    return $grounding;
  }

  /**
   * Filters a surface tool manifest to natural-language-selectable tools.
   */
  public function filterToolsForNaturalLanguage(array $manifest, string $surface_label): array {
    $tools = [];
    $allowlist = $surface_label === 'Storyline Builder' ? self::STORYLINE_NL_TOOLS : NULL;
    $has_backlog_tool = FALSE;

    foreach ((array) ($manifest['families'] ?? []) as $family => $definitions) {
      foreach ((array) $definitions as $definition) {
        $name = (string) ($definition['name'] ?? '');
        if ($name === '') {
          continue;
        }
        if ($allowlist !== NULL && !in_array($name, $allowlist, TRUE)) {
          continue;
        }
        if ($name === self::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
          $has_backlog_tool = TRUE;
        }
        $tools[] = [
          'name' => $name,
          'family' => $family,
          'mutating' => (bool) ($definition['mutating'] ?? FALSE),
          'summary' => (string) ($definition['summary'] ?? ''),
          'arguments' => (array) ($definition['arguments'] ?? []),
        ];
      }
    }

    if (!$has_backlog_tool) {
      $tools[] = $this->getBacklogSuggestionToolDescriptor();
    }

    return $tools;
  }

  /**
   * Encodes grounded prompt data compactly to minimize input token overhead.
   */
  public function encodeCompactJson(mixed $data): string {
    return (string) json_encode(
      $data,
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
  }

  /**
   * Orders a multi-step task queue by canonical dependency tier.
   *
   * Tier 1: Draft structural/narrative patches (`plan_storyline_draft_operations`,
   *         `apply_storyline_draft_operations`, room/dungeon command plans).
   * Tier 2: Canonical definition creation/updates (`generate_canonical_definition`,
   *         `create_canonical_definition`, `update_canonical_definition`).
   * Tier 3: Full storyline remediation (`plan_storyline_remediation`,
   *         `apply_storyline_remediation`) and room/dungeon layout generation.
   * Tier 4: Publication (`publish_storyline_draft`, `publish_room_version`,
   *         `publish_dungeon_version`).
   *
   * @param array<int,array<string,mixed>> $tasks
   *
   * @return array<int,array<string,mixed>>
   */
  public function orderTaskQueue(array $tasks): array {
    $indexed = [];
    foreach (array_values($tasks) as $idx => $task) {
      if (!is_array($task)) {
        continue;
      }
      $tool_name = (string) ($task['tool_name'] ?? '');
      $tier = match (TRUE) {
        in_array($tool_name, [
          'plan_storyline_draft_operations',
          'apply_storyline_draft_operations',
          'plan_room_commands',
          'plan_dungeon_commands',
        ], TRUE) => 1,
        in_array($tool_name, [
          'generate_canonical_definition',
          'create_canonical_definition',
          'update_canonical_definition',
          'plan_canonical_definition_patch',
        ], TRUE) => 2,
        in_array($tool_name, [
          'plan_storyline_remediation',
          'apply_storyline_remediation',
          'generate_room_layout',
          'generate_dungeon_layout',
        ], TRUE) => 3,
        str_starts_with($tool_name, 'publish_') => 4,
        default => 2,
      };
      $indexed[] = [
        'tier' => $tier,
        'index' => $idx,
        'task' => $task,
      ];
    }

    usort($indexed, static function (array $a, array $b): int {
      return ($a['tier'] <=> $b['tier']) ?: ($a['index'] <=> $b['index']);
    });

    return array_map(static fn(array $entry): array => $entry['task'], $indexed);
  }

  /**
   * Orders JSON-pointer draft operations so parent/referenced records land first.
   *
   * Ensures chapters & scenes precede actors/quests/conflicts, which precede
   * branch choices and effects.
   *
   * @param array<int,array<string,mixed>> $operations
   *
   * @return array<int,array<string,mixed>>
   */
  public function orderStorylineDraftOperations(array $operations): array {
    $indexed = [];
    foreach (array_values($operations) as $idx => $op) {
      if (!is_array($op)) {
        continue;
      }
      $path = (string) ($op['path'] ?? '');
      $priority = match (TRUE) {
        str_starts_with($path, '/title'),
        str_starts_with($path, '/description'),
        str_starts_with($path, '/premise'),
        str_starts_with($path, '/player_hook'),
        str_starts_with($path, '/ending'),
        str_starts_with($path, '/narrative_architecture') => 10,
        str_starts_with($path, '/process_flow/chapters'),
        str_starts_with($path, '/process_flow/journal_flow') => 20,
        str_starts_with($path, '/objects/dungeons'),
        str_starts_with($path, '/objects/rooms') => 30,
        str_starts_with($path, '/objects/actors'),
        str_starts_with($path, '/objects/items') => 40,
        str_starts_with($path, '/process_flow/task_flow'),
        str_starts_with($path, '/process_flow/objective_criteria'),
        str_starts_with($path, '/process_flow/conflicts'),
        str_starts_with($path, '/process_flow/relationship_changes') => 50,
        str_starts_with($path, '/process_flow/branch_choices') => 60,
        str_starts_with($path, '/process_flow/branch_') => 70,
        default => 45,
      };
      $indexed[] = [
        'priority' => $priority,
        'index' => $idx,
        'op' => $op,
      ];
    }

    usort($indexed, static function (array $a, array $b): int {
      return ($a['priority'] <=> $b['priority']) ?: ($a['index'] <=> $b['index']);
    });

    return array_map(static fn(array $entry): array => $entry['op'], $indexed);
  }

  /**
   * Builds a deterministic grounded response for completed draft mutations.
   *
   * Eliminates the redundant second 15k-token LLM round-trip when a mutation
   * or remediation tool has already executed deterministically and returned
   * exact persisted changes and fresh validation state.
   *
   * @return array{answer: string, next_steps: string[]}|null
   *   Deterministic response array, or NULL if LLM synthesis is required.
   */
  public function synthesizeDeterministicMutationResponse(
    string $tool_name,
    array $tool_result,
    array $context_snapshot,
    string $surface_label,
  ): ?array {
    if ($tool_name === self::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
      return $this->buildBacklogSuggestionSummary($tool_result, $context_snapshot);
    }

    if ($surface_label !== 'Storyline Builder') {
      return NULL;
    }

    if ($tool_name === 'apply_storyline_draft_operations') {
      return $this->buildStorylineDraftOperationsSummary($tool_result, $context_snapshot);
    }

    if ($tool_name === 'apply_storyline_remediation') {
      return $this->buildStorylineRemediationSummary($tool_result, $context_snapshot);
    }

    if (in_array($tool_name, ['create_canonical_definition', 'update_canonical_definition'], TRUE)) {
      return $this->buildCanonicalDefinitionMutationSummary($tool_name, $tool_result, $context_snapshot);
    }

    if ($tool_name === 'publish_storyline_draft') {
      return $this->buildStorylinePublishSummary($tool_result, $context_snapshot);
    }

    return NULL;
  }

  /**
   * Builds a deterministic confirmation response when a defect or complaint is logged.
   */
  private function buildBacklogSuggestionSummary(array $tool_result, array $context_snapshot): array {
    $summary = trim((string) ($tool_result['summary'] ?? ''));
    $category = trim((string) ($tool_result['category'] ?? 'technical_improvement'));
    $nid = $tool_result['suggestion_nid'] ?? NULL;
    $nid_suffix = $nid !== NULL ? sprintf(' (backlog item #%d)', (int) $nid) : '';

    return [
      'answer' => sprintf(
        "Logged To Project Backlog\nRecorded your feedback under category '%s'%s:\n%s",
        $category,
        $nid_suffix,
        $summary,
      ),
      'next_steps' => [
        'Continue working on the current draft or session.',
        'Provide any additional reproduction details if needed.',
      ],
    ];
  }

  /**
   * Summarizes applied Storyline draft operations without a second LLM call.
   */
  private function buildStorylineDraftOperationsSummary(array $tool_result, array $context_snapshot): array {
    $changes = is_array($tool_result['changes'] ?? NULL) ? $tool_result['changes'] : [];
    $revision = (int) ($tool_result['result_revision'] ?? $context_snapshot['draft']['revision'] ?? 0);
    $reasoning = trim((string) ($tool_result['reasoning'] ?? ''));

    $change_lines = [];
    foreach ($changes as $change) {
      if (!is_array($change)) {
        continue;
      }
      $type = (string) ($change['type'] ?? 'updated');
      $path = (string) ($change['path'] ?? '');
      $after = $change['after'] ?? NULL;
      $label = $this->describeStorylinePathChange($type, $path, $after);
      if ($label !== '') {
        $change_lines[] = $label;
      }
    }

    $applied_body = $reasoning !== '' ? $reasoning : sprintf('Saved %d update(s) to draft revision %d.', count($changes), $revision);
    if ($change_lines !== []) {
      $applied_body .= "\n" . implode("\n", array_slice(array_values(array_unique($change_lines)), 0, 8));
    }

    $status_body = $this->describeStorylineCurrentState($context_snapshot);
    $next_steps = $this->deriveStorylineNextSteps($tool_result, $context_snapshot);

    return [
      'answer' => "Applied Draft Updates\n{$applied_body}\n\nWhere The Draft Stands\n{$status_body}",
      'next_steps' => $next_steps,
    ];
  }

  /**
   * Summarizes applied Storyline remediation (definitions, rooms, draft ops).
   */
  private function buildStorylineRemediationSummary(array $tool_result, array $context_snapshot): array {
    $definitions = is_array($tool_result['definitions'] ?? NULL) ? $tool_result['definitions'] : [];
    $rooms = is_array($tool_result['rooms'] ?? NULL) ? $tool_result['rooms'] : [];
    $draft = is_array($tool_result['draft'] ?? NULL) ? $tool_result['draft'] : [];
    $deferred = is_array($tool_result['deferred_tasks'] ?? NULL) ? $tool_result['deferred_tasks'] : [];

    $parts = [];
    $created_defs = array_filter($definitions, static fn($d): bool => !empty($d['created']));
    if ($created_defs !== []) {
      $labels = array_map(
        static fn($d): string => sprintf("%s '%s'", (string) ($d['family'] ?? 'asset'), (string) ($d['definition_id'] ?? '')),
        $created_defs
      );
      $parts[] = 'Created canonical library definitions: ' . implode(', ', $labels) . '.';
    }
    $existing_defs = array_filter($definitions, static fn($d): bool => empty($d['created']));
    if ($existing_defs !== []) {
      $labels = array_map(
        static fn($d): string => sprintf("%s '%s'", (string) ($d['family'] ?? 'asset'), (string) ($d['definition_id'] ?? '')),
        $existing_defs
      );
      $parts[] = 'Verified existing canonical definitions: ' . implode(', ', $labels) . '.';
    }
    $created_rooms = array_filter($rooms, static fn($r): bool => !empty($r['created']));
    if ($created_rooms !== []) {
      $labels = array_map(static fn($r): string => "'" . (string) ($r['room_id'] ?? '') . "'", $created_rooms);
      $parts[] = 'Generated and published canonical rooms: ' . implode(', ', $labels) . '.';
    }
    if (!empty($draft['changes']) && is_array($draft['changes'])) {
      $parts[] = sprintf('Applied %d storyline draft operation(s) at revision %d.', count($draft['changes']), (int) ($draft['result_revision'] ?? 0));
    }
    if ($deferred !== []) {
      $parts[] = sprintf('Queued %d remaining asset generation task(s) for the next pass to stay within the turn time budget.', count($deferred));
    }
    if ($parts === []) {
      $parts[] = 'Completed canonical remediation check with no new mutations required.';
    }

    $status_body = $this->describeStorylineCurrentState($context_snapshot);
    $next_steps = $this->deriveStorylineNextSteps($tool_result, $context_snapshot);

    return [
      'answer' => "Canonical Library & Draft Remediation\n" . implode(' ', $parts) . "\n\nWhere The Draft Stands\n{$status_body}",
      'next_steps' => $next_steps,
    ];
  }

  /**
   * Summarizes standalone canonical definition creation/update.
   */
  private function buildCanonicalDefinitionMutationSummary(string $tool_name, array $tool_result, array $context_snapshot): array {
    $family = (string) ($tool_result['family'] ?? $tool_result['definition']['family'] ?? 'definition');
    $id = (string) ($tool_result['definition_id'] ?? $tool_result['definition']['definition_id'] ?? '');
    $action = $tool_name === 'create_canonical_definition' ? 'Created' : 'Updated';
    $body = sprintf("%s canonical %s definition '%s' in the catalog library.", $action, $family, $id);
    $status_body = $this->describeStorylineCurrentState($context_snapshot);
    $next_steps = $this->deriveStorylineNextSteps($tool_result, $context_snapshot);

    return [
      'answer' => "Canonical Catalog Updated\n{$body}\n\nWhere The Draft Stands\n{$status_body}",
      'next_steps' => $next_steps,
    ];
  }

  /**
   * Summarizes Storyline draft publication.
   */
  private function buildStorylinePublishSummary(array $tool_result, array $context_snapshot): array {
    $template_id = (string) ($tool_result['template_id'] ?? $context_snapshot['storyline']['storyline_id'] ?? 'storyline');
    $revision = (int) ($tool_result['published_revision'] ?? $context_snapshot['draft']['revision'] ?? 0);
    $body = sprintf("Published storyline template '%s' from draft revision %d into the canonical storyline library.", $template_id, $revision);

    return [
      'answer' => "Storyline Published\n{$body}",
      'next_steps' => [
        sprintf("Attach template '%s' to a campaign or open it in the Editor Suite to inspect linked rooms and actors.", $template_id),
      ],
    ];
  }

  /**
   * Translates a JSON-pointer change into concise human-readable text.
   */
  private function describeStorylinePathChange(string $type, string $path, mixed $after): string {
    $verb = match ($type) {
      'append' => 'Added',
      'remove' => 'Removed',
      default => 'Updated',
    };
    $id_hint = '';
    if (is_array($after)) {
      foreach (['display_name', 'title', 'label', 'item_id', 'actor_id', 'contact_id', 'choice_id', 'decision_id', 'conflict_id', 'scene_id', 'task_id', 'objective_id', 'room_id', 'dungeon_id'] as $key) {
        if (!empty($after[$key]) && is_scalar($after[$key])) {
          $id_hint = " '" . trim((string) $after[$key]) . "'";
          break;
        }
      }
    }

    return match (TRUE) {
      str_starts_with($path, '/process_flow/branch_choices') => sprintf('• %s branching choice%s.', $verb, $id_hint),
      str_starts_with($path, '/process_flow/branch_actor_effects') => sprintf('• %s branch actor effect%s.', $verb, $id_hint),
      str_starts_with($path, '/process_flow/branch_environment_effects') => sprintf('• %s branch environment effect%s.', $verb, $id_hint),
      str_starts_with($path, '/process_flow/branch_storyline_effects') => sprintf('• %s branch storyline effect%s.', $verb, $id_hint),
      str_starts_with($path, '/process_flow/conflicts') => sprintf('• %s narrative conflict%s.', $verb, $id_hint),
      str_starts_with($path, '/process_flow/journal_flow') => sprintf('• %s scene%s in journal flow.', $verb, $id_hint),
      str_starts_with($path, '/process_flow/task_flow') => sprintf('• %s progression task%s.', $verb, $id_hint),
      str_starts_with($path, '/process_flow/objective_criteria') => sprintf('• %s objective criterion%s.', $verb, $id_hint),
      str_starts_with($path, '/objects/actors/generation_specs') => sprintf('• %s actor spec%s.', $verb, $id_hint),
      str_starts_with($path, '/objects/items/generation_specs') => sprintf('• %s item spec%s.', $verb, $id_hint),
      str_starts_with($path, '/objects/rooms/generation_specs') => sprintf('• %s room spec%s.', $verb, $id_hint),
      str_starts_with($path, '/objects/dungeons/generation_specs') => sprintf('• %s dungeon spec%s.', $verb, $id_hint),
      $path === '/player_hook' => '• Updated the player hook.',
      $path === '/premise' => '• Updated the storyline premise.',
      $path === '/ending' => '• Updated the intended ending.',
      default => sprintf('• %s %s%s.', $verb, ltrim(str_replace('/', ' → ', $path), ' →'), $id_hint),
    };
  }

  /**
   * Formats grounded completion and validation state for Storyline Builder.
   */
  private function describeStorylineCurrentState(array $context_snapshot): string {
    $state = is_array($context_snapshot['current_state'] ?? NULL) ? $context_snapshot['current_state'] : [];
    $validation = is_array($context_snapshot['validation_summary'] ?? NULL) ? $context_snapshot['validation_summary'] : [];
    $revision = (int) ($context_snapshot['draft']['revision'] ?? $state['revision'] ?? 0);
    $percent = (int) ($state['completion_percent'] ?? 0);
    $errors = (int) ($validation['error_count'] ?? $state['error_count'] ?? 0);
    $warnings = (int) ($validation['warning_count'] ?? $state['warning_count'] ?? 0);
    $missing = array_values(array_filter((array) ($state['missing_content'] ?? []), 'is_string'));

    $summary = sprintf(
      'Revision %d is at %d%% completion with %d validation error(s) and %d warning(s).',
      $revision,
      $percent,
      $errors,
      $warnings,
    );
    if ($missing !== []) {
      $summary .= ' Remaining open checklist items: ' . implode('; ', array_slice($missing, 0, 4)) . '.';
    }
    else {
      $summary .= ' All minimum content checklist items are complete.';
    }

    return $summary;
  }

  /**
   * Derives 1–3 concrete next steps from grounded state and tool result.
   *
   * @return string[]
   */
  private function deriveStorylineNextSteps(array $tool_result, array $context_snapshot): array {
    $steps = array_values(array_filter(
      (array) ($tool_result['next_steps'] ?? []),
      static fn($s): bool => is_string($s) && trim($s) !== '',
    ));
    if ($steps !== []) {
      return array_map('trim', array_slice($steps, 0, 3));
    }

    $state = is_array($context_snapshot['current_state'] ?? NULL) ? $context_snapshot['current_state'] : [];
    $missing = array_values(array_filter((array) ($state['missing_content'] ?? []), 'is_string'));
    foreach (array_slice($missing, 0, 2) as $item) {
      $steps[] = 'Complete open requirement: ' . trim($item);
    }

    $validation = is_array($context_snapshot['validation_summary'] ?? NULL) ? $context_snapshot['validation_summary'] : [];
    foreach (array_slice((array) ($validation['errors'] ?? []), 0, 2) as $error) {
      if (is_array($error) && !empty($error['message'])) {
        $steps[] = 'Resolve validation error: ' . trim((string) $error['message']);
      }
      elseif (is_string($error) && trim($error) !== '') {
        $steps[] = 'Resolve validation error: ' . trim($error);
      }
    }

    if ($steps === []) {
      $steps[] = 'Review the updated storyline stages or publish the draft into the canonical library when ready.';
    }

    return array_slice($steps, 0, 3);
  }

}
