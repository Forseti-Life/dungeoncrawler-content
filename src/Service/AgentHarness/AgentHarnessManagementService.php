<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\AgentHarness;

use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
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
    'describe_storyline_authoring_contract',
    'load_storyline_draft_snapshot',
    'get_storyline_draft_status',
    'get_storyline_branching',
    'get_storyline_synopsis',
    'find_library_characters',
    'bind_library_characters',
    'find_existing_institutions',
    'load_existing_quests',
    'inspect_storyline_actor_sheets',
    'build_storyline_actor_sheets',
    'preview_storyline_definition',
    'validate_draft',
    'explain_validation_findings',
    'preview_storyline_narrative_analysis',
    'check_storyline_creation_readiness',
    'plan_storyline_draft_operations',
    'plan_storyline_scene_room_removal',
    'plan_storyline_work',
    'apply_storyline_work',
    'apply_storyline_draft_operations',
    'plan_storyline_remediation',
    'apply_storyline_remediation',
    'publish_storyline_draft',
    'publish_storyline_quest_templates',
    'repair_storyline_item_definitions',
    'generate_canonical_definition',
    'create_canonical_definition',
    'update_canonical_definition',
    'describe_canonical_definition_schema',
    'load_canonical_definition',
    'list_catalog_definitions',
    'inspect_catalog_entry',
    'validate_canonical_definition',
    'plan_canonical_definition_patch',
    'list_rooms',
    'list_dungeons',
    'find_dungeons_using_room',
    'inspect_actor',
    'plan_actor_changes',
    'apply_actor_changes',
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
      $tool_name === 'resolve_quest_goal' => 'Now recording how the quest goal was achieved…',
      $tool_name === 'reveal_quest_approach' => 'Now revealing a new way forward…',
      $tool_name === 'create_character_faction' => 'Now generating canonical faction for character…',
      $tool_name === 'lookup_pathfinder_character_reference' => 'Now looking up Pathfinder character-creation reference file…',
      $tool_name === 'apply_character_draft_updates' => 'Now applying character sheet updates…',
      $tool_name === 'autofill_character_draft' => 'Now building the character from the wizard\'s valid choices…',
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
    $validation_summary = (array) ($context_snapshot['validation_summary'] ?? []);
    if (is_array($validation_summary['stages'] ?? NULL) && $validation_summary['stages'] !== []) {
      // Every error and warning is listed under its stage, with the stage's
      // fix route; the flat lists repeat them and are not sent twice.
      unset($validation_summary['errors'], $validation_summary['warnings']);
    }
    // A finding carries its message; the same sentence is not also sent in
    // the stage's plain error and warning lists.
    foreach ((array) ($validation_summary['stages'] ?? []) as $index => $stage) {
      $stated = array_flip(array_filter(array_column(array_filter((array) ($stage['findings'] ?? []), 'is_array'), 'message'), 'is_string'));
      foreach (['errors', 'warnings'] as $list) {
        if ($stated !== [] && is_array($stage[$list] ?? NULL)) {
          $validation_summary['stages'][$index][$list] = array_values(array_filter(
            $stage[$list],
            static fn(mixed $message): bool => !is_string($message) || !isset($stated[$message]),
          ));
        }
      }
    }
    $grounding['validation_summary'] = $validation_summary;
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
          'plan_storyline_scene_room_removal',
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
    if ($tool_name === 'route_to_editor') {
      $kind = (string) ($tool_result['kind'] ?? '');
      $id = (string) ($tool_result['id'] ?? '');
      $route = (string) ($tool_result['route'] ?? '');
      if ($kind === 'room') {
        return [
          'answer' => "Room Editor Required\nCreate and save the canonical room '{$id}' in the Room Editor. This must happen before the storyline can place it in a dungeon.",
          'next_steps' => [$route !== ''
            ? "Open {$route} and create the room '{$id}'. Then return here to revalidate."
            : "Open the Room Editor and create the room '{$id}'. Then return here to revalidate."],
        ];
      }
      if ($kind === 'dungeon') {
        $room_id = (string) ($context_snapshot['troubleshooting']['finding']['room_id'] ?? '');
        $placement = $room_id !== '' ? "Place room '{$room_id}' in dungeon '{$id}'." : "Open dungeon '{$id}' and save the required placement.";
        return [
          'answer' => "Dungeon Editor Required\n{$placement} The storyline will not recreate or bypass that placement.",
          'next_steps' => [$route !== ''
            ? "Open {$route} and save the room placement. Then return here to revalidate."
            : "Open the Dungeon Editor and save the room placement. Then return here to revalidate."],
        ];
      }
    }
    if ($tool_name === self::TOOL_SUBMIT_BACKLOG_SUGGESTION) {
      $response = $this->buildBacklogSuggestionSummary($tool_result, $context_snapshot);
      if ($surface_label === 'Storyline Builder') {
        $response['next_steps'] = $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE);
      }
      return $response;
    }
    if ($tool_name === 'create_canonical_definition') {
      $family = trim((string) ($tool_result['family'] ?? ''));
      $definition_id = trim((string) ($tool_result['definition_id'] ?? ''));
      $payload = is_array($tool_result['payload'] ?? NULL) ? $tool_result['payload'] : [];
      $name = trim((string) ($payload['name'] ?? $payload['label'] ?? $payload['display_name'] ?? $definition_id));
      $version = trim((string) ($tool_result['version'] ?? '1.0.0'));
      $answer = sprintf(
        "%s created\nThe %s definition '%s' is now available in the canonical %s catalog at version %s and passed canonical validation.",
        $name,
        $family !== '' ? $family : 'object',
        $definition_id,
        $family !== '' ? $family : 'definition',
        $version,
      );
      return [
        'answer' => $answer,
        'next_steps' => $surface_label === 'Room Editor'
          ? ['Place the new definition in the current room, or ask the Room GM to choose an available hex.']
          : [],
      ];
    }
    if ($surface_label === 'Room Editor' && $tool_name === 'publish_room_version') {
      $publication = is_array($tool_result['publication'] ?? NULL) ? $tool_result['publication'] : [];
      $version = trim((string) ($publication['version'] ?? $tool_result['published_version'] ?? ''));
      $room_id = trim((string) ($publication['room_id'] ?? ''));
      return [
        'answer' => sprintf(
          "%s published\nThe validated room was published%s with three authoritative version checks intact%s.",
          $version !== '' ? 'Room version ' . $version : 'Room',
          $room_id !== '' ? " as {$room_id}" : '',
          !empty($publication['rebased_from_version_id'])
            ? '; the stale base was safely fast-forwarded because all upstream changes were already present'
            : '',
        ),
        'next_steps' => [],
      ];
    }
    if ($surface_label === 'Room Editor' && $tool_name === 'apply_room_commands') {
      $receipts = is_array($tool_result['receipts'] ?? NULL) ? $tool_result['receipts'] : [];
      if ($receipts !== []
        && count($receipts) === count(array_filter(
          $receipts,
          static fn(mixed $receipt): bool => is_array($receipt)
            && ($receipt['command_type'] ?? '') === 'restore_published_base',
        ))) {
        return [
          'answer' => 'Published room version restored\nThe active draft now reloads the exact published base revision as one audited command.',
          'next_steps' => [],
        ];
      }
    }

    if ($surface_label !== 'Storyline Builder') {
      return NULL;
    }

    if ($tool_name === 'apply_storyline_work') {
      $progress = $tool_result['work_progress'];
      $done = $progress['step'];
      $total = $progress['total_steps'];
      $last_outcome = is_array($progress['last_outcome'] ?? NULL) ? $progress['last_outcome'] : [];
      $batch_summary = sprintf(
        'Last batch cleared %d validation error(s) and introduced %d; %d remain.',
        (int) ($last_outcome['cleared'] ?? 0),
        (int) ($last_outcome['introduced_count'] ?? 0),
        (int) ($last_outcome['remaining'] ?? 0),
      );
      $introduced = array_values(array_filter(array_map('strval', (array) ($last_outcome['introduced'] ?? []))));
      $totals = sprintf(
        'Total cleared: %d. Total introduced: %d.',
        (int) ($progress['total_cleared_errors'] ?? 0),
        (int) ($progress['total_introduced_errors'] ?? 0),
      );
      if ($progress['status'] === 'cancelled') {
        $lines = [sprintf('Stopped the saved work. The %d completed batches remain saved; no draft edits were undone.', $done)];
        if ($last_outcome !== []) {
          $lines[] = $batch_summary;
          $lines[] = $totals;
        }
        return [
          'answer' => implode("\n", $lines),
          'next_steps' => ['Review the saved storyline.'],
        ];
      }
      if ($progress['status'] === 'paused') {
        $failure = is_array($progress['failure'] ?? NULL) ? $progress['failure'] : [];
        $label = trim((string) ($failure['label'] ?? ''));
        $error = trim((string) ($failure['error'] ?? 'storyline_work_paused'));
        $details = array_values(array_filter(array_map(static fn($row) => trim((string) ($row['message'] ?? '')), (array) ($failure['findings'] ?? []))));
        $summary = $details[0] ?? $error;
        $lead = $label === '' ? 'Saved work paused.' : sprintf('Saved work paused at "%s".', $label);
        $exhausted = ((int) ($progress['failed_attempts'] ?? 0)) >= ((int) ($progress['max_failed_attempts'] ?? 0));
        $pause_answer = $exhausted
          ? sprintf('%s %s Attempts are exhausted; skip this unit or stop saved work. (%d/%d attempts)', $lead, $summary, (int) ($progress['failed_attempts'] ?? 0), (int) ($progress['max_failed_attempts'] ?? 0))
          : sprintf('%s %s Saved work is ready to resume. (%d/%d attempts)', $lead, $summary, (int) ($progress['failed_attempts'] ?? 0), (int) ($progress['max_failed_attempts'] ?? 0));
        $lines = [$pause_answer];
        if ($last_outcome !== []) {
          $lines[] = $batch_summary;
          $lines[] = $totals;
        }
        if ($introduced !== []) {
          $lines[] = "Introduced findings:\n- " . implode("\n- ", array_slice($introduced, 0, 5));
        }
        return [
          'answer' => implode("\n", $lines),
          'next_steps' => [$exhausted ? 'Skip this unit or stop the saved work.' : 'Resume the saved authoring work.'],
        ];
      }
      if ($progress['status'] !== 'completed') {
        $lines = [sprintf('Saved %d of %d authoring batches. Remaining work is checkpointed and ready to continue.', $done, $total)];
        if ($last_outcome !== []) {
          $lines[] = $batch_summary;
          $lines[] = $totals;
        }
        if ($introduced !== []) {
          $lines[] = "Introduced findings:\n- " . implode("\n- ", array_slice($introduced, 0, 5));
        }
        return [
          'answer' => implode("\n", $lines),
          'next_steps' => ['Continue the saved authoring work.'],
        ];
      }
      $completed_labels = array_values(array_filter(array_map('strval', (array) ($progress['completed_labels'] ?? []))));
      $skipped_labels = array_values(array_filter(array_map('strval', (array) ($progress['skipped_labels'] ?? []))));
      $sections = [sprintf("Requested Draft Updates Saved\nCompleted all %d authoring batches:\n%s", $total, $completed_labels === [] ? '- (none)' : implode("\n", array_map(static fn(string $label): string => '- ' . $label, $completed_labels)))];
      if ($skipped_labels !== []) {
        $sections[] = "Skipped batches:\n" . implode("\n", array_map(static fn(string $label): string => '- ' . $label, $skipped_labels));
      }
      $sections[] = $totals;
      if ((int) ($progress['total_cleared_errors'] ?? 0) === 0 && (int) ($last_outcome['remaining'] ?? 0) > 0) {
        $sections[] = 'This work did not clear any validation error.';
      }
      if ($introduced !== []) {
        $sections[] = "Introduced findings:\n- " . implode("\n- ", array_slice($introduced, 0, 5));
      }
      $sections[] = sprintf("Current State\n%s", $this->describeStorylineCurrentState($context_snapshot));
      return [
        'answer' => implode("\n\n", $sections),
        'next_steps' => $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE),
      ];
    }

    if ($tool_name === 'check_storyline_creation_readiness') {
      $revision = (int) $tool_result['revision'];
      $receipt = $tool_result['publication_receipt'] ?? NULL;
      if (is_array($receipt)) {
        return [
          'answer' => "Review Complete\nThe storyline is already published; its final signoff is recorded in the native publication receipt.",
          'next_steps' => ['Open the published storyline.'],
        ];
      }
      if ($tool_result['ready']) {
        return [
          'answer' => sprintf("Review Complete\nRevision %d passes canonical validation. No separate review flag or signoff document is required. Publication records the final signoff; this review did not publish or change the draft.", $revision),
          'next_steps' => ['Say "Review and create" to publish this revision and record its final signoff.'],
        ];
      }
      return [
        'answer' => "Review Blocked\n" . implode("\n", array_column($tool_result['blockers'], 'message')),
        'next_steps' => ['Have the GM address the next publication blocker.'],
      ];
    }

    if ($tool_name === 'apply_storyline_draft_operations'
      || ($tool_name === 'plan_storyline_draft_operations' && empty($tool_result['has_changes']) && !empty($tool_result['already_present_institutions']))) {
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
    if ($tool_name === 'publish_storyline_quest_templates') {
      $records = $tool_result['quest_records'];
      $created = count(array_filter(array_column($records, 'created')));
      return [
        'answer' => sprintf(
          "Quest templates\nCreated %d of %d requested templates. Any that already existed were left unchanged. The storyline draft was not changed or published.",
          $created,
          count($records),
        ),
        'next_steps' => $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE),
      ];
    }
    if ($tool_name === 'repair_storyline_item_definitions') {
      $resolved = (array) ($tool_result['already_resolved'] ?? []);
      $items = array_filter((array) $tool_result['items']);
      $answer = $items === []
        ? 'Canonical item definitions already repaired'
        : "Canonical item definitions updated\nRemoved the redundant registry ID from " . implode(', ', $items) . '.';
      if ($resolved !== []) {
        $answer .= ' Already valid: ' . implode(', ', $resolved) . '.';
      }
      return [
        'answer' => $answer . ' The storyline draft was not changed.',
        'next_steps' => $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE),
      ];
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
    $existing_lines = array_map(
      static fn(string $name): string => sprintf('%s already exists in this storyline.', $name),
      $tool_result['already_present_institutions'] ?? [],
    );
    if ($changes === [] && $existing_lines !== []) {
      return [
        'answer' => implode("\n", [...$existing_lines, 'No changes were made.']),
        'next_steps' => [],
      ];
    }
    $revision = (int) ($tool_result['result_revision'] ?? $context_snapshot['draft']['revision'] ?? 0);
    $reasoning = trim((string) ($tool_result['reasoning'] ?? ''));

    $change_lines = $existing_lines;
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
    $next_steps = $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE);

    return [
      'answer' => "Applied Draft Updates\n{$applied_body}\n\nWhere The Draft Stands\n{$status_body}",
      'next_steps' => $next_steps,
    ];
  }

  /**
   * Summarizes applied Storyline remediation (definitions, quest references, draft ops).
   */
  private function buildStorylineRemediationSummary(array $tool_result, array $context_snapshot): array {
    $definitions = is_array($tool_result['definitions'] ?? NULL) ? $tool_result['definitions'] : [];
    $quests = is_array($tool_result['quests'] ?? NULL) ? $tool_result['quests'] : [];
    $draft = is_array($tool_result['draft'] ?? NULL) ? $tool_result['draft'] : [];
    $deferred = is_array($tool_result['deferred_tasks'] ?? NULL) ? $tool_result['deferred_tasks'] : [];

    $parts = [];
    foreach ($quests as $quest) {
      if (!empty($quest['storyline_references_repaired'])) {
        $parts[] = sprintf("Aligned linked quest '%s' with its storyline, preserving its objectives, rewards, and narrative.", $quest['template_id']);
      }
    }
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
    $next_steps = $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE);

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
    $next_steps = $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE);

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
    $body = sprintf("Published storyline template '%s' from draft revision %d into the canonical storyline library. Canonical review is complete and the final signoff is stored in its publication receipt.", $template_id, $revision);

    return [
      'answer' => "Storyline Published\n{$body}",
      'next_steps' => [
        'Open the published storyline.',
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
      $summary .= sprintf(' %d minimum-content check(s) remain open.', count($missing));
    }
    else {
      $summary .= ' All minimum content checklist items are complete.';
    }

    return $summary;
  }

  /**
   * Rejects multi-item guidance before execution or presentation.
   */
  public static function assertStorylineNextSteps(mixed $steps): void {
    if (!is_array($steps) || !array_is_list($steps)) {
      throw new \DomainException('storyline_gm_next_step_invalid');
    }
    if (count($steps) > 1) {
      throw new \DomainException('storyline_gm_next_step_must_be_single');
    }
    foreach ($steps as $step) {
      if (!is_string($step) || trim($step) === '' || preg_match('/\R\s*(?:\d+[.)]|[-*])\s/u', $step) === 1) {
        throw new \DomainException('storyline_gm_next_step_invalid');
      }
    }
  }

  private const SURFACE_STORYLINE = 'Storyline Builder';

  /**
   * Per authoring surface: who maps missing content to an action, and what
   * the author is working on, as worded in error and warning options.
   */
  private const SURFACE_GUIDANCE = [
    self::SURFACE_STORYLINE => [
      'recommend' => [StorylineAuthoringDraftService::class, 'recommendNextAuthoringAction'],
      'work' => 'story',
      'warning_work' => 'story content',
    ],
    'Dungeon Editor' => [
      'recommend' => [DungeonEditorService::class, 'recommendNextAuthoringAction'],
      'work' => 'dungeon',
      'warning_work' => 'dungeon',
    ],
    'Room Editor' => [
      'recommend' => [RoomEditorService::class, 'recommendNextAuthoringAction'],
      'work' => 'room',
      'warning_work' => 'room',
    ],
  ];

  /**
   * Next-step wording per finding route, in the order errors are offered:
   * those a draft edit fixes first, then those another authority fixes.
   * Arguments: the finding, the surface's work noun, the room to open.
   */
  private const ERROR_ROUTE_STEPS = [
    'draft_operation' => 'Validation finding: %s Options: ask the GM to fix this finding now, skip to the next finding, or switch focus to warnings.',
    'draft_command' => 'Validation finding: %s Options: ask the GM to fix this finding now, skip to the next finding, or switch focus to warnings.',
    'actor_sheet_build' => 'Validation finding: %s Options: ask the GM to build the character sheets for the roster, or continue %s changes.',
    'dungeon_editor' => 'Validation finding: %s This needs the site built in the Dungeon Editor, not a draft edit. Options: ask the GM to open that site in the Dungeon Editor, where its GM builds it from the story brief, or continue %s changes.',
    'room_editor' => 'Validation finding: %s This needs work in the Room Editor, not a %s edit. Options: open %3$s in the Room Editor, or continue %2$s changes.',
    'definition_editor' => 'Validation finding: %s This needs the object\'s definition fixed, not a %s edit. Options: fix the definition in the Definition Editor, or continue %2$s changes.',
    'outside_editor' => 'Validation finding: %s No edit in this editor changes this. Options: continue %s changes.',
  ];

  /**
   * The Storyline Builder's next step from saved state.
   *
   * @return string[]
   */
  public function storylineNextStepFromSavedState(array $context_snapshot): array {
    return $this->nextStepFromSavedState($context_snapshot, self::SURFACE_STORYLINE);
  }

  /**
   * The single next step an authoring surface's saved draft state calls for.
   *
   * In order: saved work in progress, missing content, the first error a
   * draft edit fixes, the first error another authority fixes, the first
   * warning. Never stale advice: only the snapshot is read.
   *
   * @return string[]
   */
  public function nextStepFromSavedState(array $context_snapshot, string $surface_label): array {
    $guidance = self::SURFACE_GUIDANCE[$surface_label]
      ?? throw new \LogicException('next_step_surface_unsupported:' . $surface_label);
    if (!empty($context_snapshot['authoring_workflow']['bounded_work']['active_work'])) {
      return ['Resume the saved authoring work.'];
    }
    $state = is_array($context_snapshot['current_state'] ?? NULL) ? $context_snapshot['current_state'] : [];
    $validation = is_array($context_snapshot['validation_summary'] ?? NULL) ? $context_snapshot['validation_summary'] : [];
    $state['error_count'] = $validation['error_count'] ?? $state['error_count'] ?? count($validation['errors'] ?? []);
    $state['warning_count'] = $validation['warning_count'] ?? $state['warning_count'] ?? count($validation['warnings'] ?? []);
    $next_steps = $guidance['recommend']($state);
    $next = trim((string) ($next_steps[0] ?? ''));
    if ($next === 'Have the GM build the character sheets for the roster.') {
      // A sheet cannot be built for a second identity of a library actor,
      // so that finding comes before the build.
      foreach ((array) ($validation['stages'] ?? []) as $stage) {
        foreach ((array) ($stage['findings'] ?? []) as $finding) {
          if (is_array($finding) && ($finding['code'] ?? '') === 'actor_duplicates_library_actor') {
            return [sprintf(
              'Validation finding: %s Options: ask the GM to use the existing library actor, or rename this actor.',
              (string) $finding['message'],
            )];
          }
        }
      }
    }
    if ($next === 'Have the GM address the next validation finding.') {
      foreach (self::ERROR_ROUTE_STEPS as $route => $step) {
        $found = $this->firstValidationFinding($validation, 'error', $route);
        if ($found['message'] === '') {
          continue;
        }
        return [sprintf(
          $step,
          // A sheet finding already names its actor.
          $route === 'actor_sheet_build' ? $found['message'] : $this->describeFinding($found, $context_snapshot),
          $guidance['work'],
          $this->roomToOpen($found, $context_snapshot),
        )];
      }
    }
    if ($next === 'Have the GM address the next validation warning.') {
      $found = !empty($state['accepted_warning_count'])
        ? $this->firstValidationFinding($validation, 'warning', required_message: (string) ($state['first_open_warning'] ?? ''))
        : $this->firstValidationFinding($validation, 'warning');
      if ($found['message'] !== '') {
        if ($surface_label === self::SURFACE_STORYLINE) {
          return [$this->storylineWarningStep($found, $context_snapshot)];
        }
        return [sprintf(
          'Validation warning: %s Options: ask the GM to fix this warning, accept it under Review warnings, or continue %s changes.',
          $this->describeFinding($found, $context_snapshot),
          $guidance['warning_work'],
        )];
      }
    }
    return $next_steps;
  }

  private function storylineWarningStep(array $selected, array $snapshot): string {
    $message = $this->describeFinding($selected, $snapshot);
    $finding = $selected['finding'] ?? [];
    if (($finding['code'] ?? '') === 'canonical_item_definition_invalid') {
      if ($finding['definition_pointer'] === '/content_id' && $finding['schema_error_code'] === 'additional_property') {
        return sprintf('Repair the canonical item definition for "%s" by removing its redundant content_id. The storyline reference stays unchanged.', $finding['entity_label']);
      }
      return sprintf('Review the canonical item definition for "%s": %s', $finding['entity_label'], $message);
    }
    if (($selected['finding']['code'] ?? '') === 'DCV_NARRATIVE_CONFLICT_ARC_MISSING') {
      return "Validation warning: $message Proposed resolution: Link the scene to the existing conflict at the point it introduces, escalates, resolves, or deliberately carries it forward. Alternative endings can resolve the same conflict differently.";
    }
    if (($selected['finding']['code'] ?? '') === 'DCV_NARRATIVE_CONFLICT_UNRESOLVED') {
      $conflict_id = $selected['finding']['entity_id'] ?? '';
      $conflicts = array_column($snapshot['storyline']['process_flow']['conflicts'] ?? [], NULL, 'conflict_id');
      $title = trim((string) ($conflicts[$conflict_id]['title'] ?? ''));
      if ($title !== '') {
        $message = sprintf('The conflict "%s" is still open at the end.', $title);
      }
      return "Validation warning: $message Proposed resolution: Write an outcome in a scene after the final escalation, then record that scene as the resolution. If the story is meant to leave the conflict open, mark it as an intentional open thread instead.";
    }
    if (preg_match('/^(?:Storyline entry_point primary location |\\[DCV_NAV_(?:ENTRY_ROOM|CONNECTOR_TARGET_ROOM)_NOT_IN_DUNGEON\\])/', $selected['message']) === 1) {
      return "Validation warning: $message Proposed resolution: Check the room's canonical site and correct the authored binding if exactly one placement supports it.";
    }
    return "Validation warning: $message Address it as part of authoring, or accept it under Review warnings if it is intentional.";
  }

  /**
   * Returns one concise validation finding by severity for next-step guidance.
   */
  private function firstValidationFinding(array $validation, string $severity, ?string $required_route = NULL, ?string $required_message = NULL): array {
    $stages = (array) ($validation['stages'] ?? []);
    if (is_array($validation['findings'] ?? NULL)) {
      // Dungeon and Room validation is one routed list, not stages.
      $stages[] = ['findings' => $validation['findings']];
    }
    foreach ($stages as $stage) {
      if (!is_array($stage)) {
        continue;
      }
      $stage_route = trim((string) ($stage['route'] ?? ''));
      // A structured finding carries its own route; the same message in the
      // stage's plain list must not be read again under the stage's route.
      $structured = [];
      foreach ((array) ($stage['findings'] ?? []) as $finding) {
        if (!is_array($finding)) {
          continue;
        }
        $message = trim((string) ($finding['message'] ?? ''));
        $structured[$message] = TRUE;
        $route = trim((string) ($finding['route'] ?? $stage_route));
        if (($finding['severity'] ?? '') !== $severity || $message === ''
          || ($required_route !== NULL && $route !== $required_route)
          || ($required_message !== NULL && $message !== $required_message)) {
          continue;
        }
        return ['message' => $message, 'route' => $route, 'finding' => $finding];
      }
      if ($required_route !== NULL && $stage_route !== $required_route) {
        continue;
      }
      $stage_list = $severity === 'error' ? (array) ($stage['errors'] ?? []) : (array) ($stage['warnings'] ?? []);
      foreach ($stage_list as $entry) {
        $message = trim((string) $entry);
        if ($message !== '' && !isset($structured[$message])
          && ($required_message === NULL || $message === $required_message)) {
          return ['message' => $message, 'route' => $stage_route];
        }
      }
    }
    if ($required_route !== NULL) {
      return ['message' => '', 'route' => $required_route];
    }
    $list = $severity === 'error' ? (array) ($validation['errors'] ?? []) : (array) ($validation['warnings'] ?? []);
    foreach ($list as $entry) {
      $message = trim((string) $entry);
      if ($message !== '' && ($required_message === NULL || $message === $required_message)) {
        return ['message' => $message, 'route' => 'unclassified'];
      }
    }
    return ['message' => '', 'route' => 'unclassified'];
  }

  /**
   * States one finding in the author's terms, without internal identifiers.
   */
  private function describeFinding(array $selected, array $context_snapshot): string {
    $finding = is_array($selected['finding'] ?? NULL) ? $selected['finding'] : [];
    $process_flow = (array) ($context_snapshot['storyline']['process_flow'] ?? []);
    return match ((string) ($finding['code'] ?? '')) {
      'quest_objective_missing' => sprintf(
        'The task "%s" has no objective, so the game cannot tell when it is done.',
        $this->questLabel((string) ($finding['quest_id'] ?? ''), $process_flow),
      ),
      'objective_metric_shared_across_tasks' => sprintf(
        'The objectives %s track the same result on different tasks. One aim reached in several ways is one task with a goal, and one choice per way.',
        implode(', ', array_map(
          fn(string $id): string => '"' . $this->objectiveLabel($id, $process_flow) . '"',
          array_map('strval', (array) ($finding['objective_ids'] ?? [])),
        )),
      ),
      'choice_cloned_across_tasks' => sprintf(
        'The choice "%s" is repeated on %d tasks with the same trigger and outcome. A condition that applies across the story is written once, as a storyline-wide choice.',
        (string) ($finding['label'] ?? ''),
        count((array) ($finding['task_ids'] ?? [])),
      ),
      'continuation_replaced_by_choices' => sprintf(
        'The task "%s" is meant to continue to %s, but it has choices and none of them leads there, so that part of the story cannot be reached. Either add a choice that continues there, or make the conditional choice storyline-wide.',
        $this->questLabel((string) ($finding['task_id'] ?? ''), $process_flow),
        implode(', ', array_map(
          fn(string $id): string => '"' . $this->questLabel($id, $process_flow) . '"',
          array_map('strval', (array) ($finding['dropped_task_ids'] ?? [])),
        )),
      ),
      'choice_source_task_unknown' => sprintf(
        'The choice "%s" belongs to a task that does not exist.',
        (string) ($finding['label'] ?? ''),
      ),
      'objective_unlinked' => sprintf(
        'The objective "%s" is not attached to any task.',
        $this->objectiveLabel((string) ($finding['objective_id'] ?? ''), $process_flow),
      ),
      'placement_version_unresolved' => sprintf(
        'The place %s uses a version of %s that is not published.',
        $this->placeNames($finding, $context_snapshot),
        $this->roomToOpen($selected, $context_snapshot),
      ),
      'placement_overlap' => sprintf(
        'The places %s overlap on the map.',
        $this->placeNames($finding, $context_snapshot),
      ),
      'port_link_self_reference' => sprintf(
        'A link %s joins a place to itself.',
        $this->linkEnds($finding, $context_snapshot),
      ),
      'port_link_endpoint_missing' => sprintf(
        'The link %s points at a place or doorway that does not exist.',
        $this->linkEnds($finding, $context_snapshot),
      ),
      'port_link_direction_invalid' => sprintf(
        'The link %s must run from an exit to an entrance.',
        $this->linkEnds($finding, $context_snapshot),
      ),
      'port_already_linked' => sprintf(
        'The exit "%s" on %s is used by %d links; at most one is allowed.',
        implode('', $this->subjectIds($finding, 'port_id')),
        $this->placeNames($finding, $context_snapshot),
        count($this->subjectIds($finding, 'link_id')),
      ),
      'region_placement_unresolved' => sprintf(
        'The region %s includes a place that no longer exists.',
        $this->regionNames($finding, $context_snapshot),
      ),
      default => $this->roomFindingSubject($finding, $context_snapshot) . (preg_replace(
        '/draft-[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}-(?:task|scene|choice|objective|decision|conflict)-/',
        '',
        (string) $selected['message'],
      ) ?? (string) $selected['message']),
    };
  }

  /**
   * IDs of one kind among a Dungeon Editor finding's subjects.
   *
   * @return string[]
   */
  private function subjectIds(array $finding, string $key): array {
    return array_values(array_filter(array_map(
      static fn($subject): string => is_array($subject) ? (string) ($subject[$key] ?? '') : '',
      (array) ($finding['subjects'] ?? []),
    )));
  }

  /**
   * One dungeon placement as the author named it, or NULL when it is gone.
   */
  private function dungeonPlacement(string $placement_id, array $context_snapshot): ?array {
    foreach ((array) ($context_snapshot['dungeon']['placements'] ?? []) as $placement) {
      if (is_array($placement) && ($placement['placement_id'] ?? NULL) === $placement_id) {
        return $placement;
      }
    }
    return NULL;
  }

  private function placeName(string $placement_id, array $context_snapshot): string {
    $placement = $this->dungeonPlacement($placement_id, $context_snapshot);
    return $placement === NULL ? 'a place that no longer exists' : '"' . $placement['label'] . '"';
  }

  /**
   * The place names a finding is about, joined for a sentence.
   */
  private function placeNames(array $finding, array $context_snapshot): string {
    return implode(' and ', array_map(
      fn(string $id): string => $this->placeName($id, $context_snapshot),
      $this->subjectIds($finding, 'placement_id'),
    ));
  }

  /**
   * A link stated by the two places it joins.
   */
  private function linkEnds(array $finding, array $context_snapshot): string {
    $link_id = $this->subjectIds($finding, 'link_id')[0] ?? '';
    foreach ((array) ($context_snapshot['dungeon']['port_links'] ?? []) as $link) {
      if (is_array($link) && ($link['link_id'] ?? NULL) === $link_id) {
        return sprintf(
          'from %s to %s',
          $this->placeName((string) $link['from']['placement_id'], $context_snapshot),
          $this->placeName((string) $link['to']['placement_id'], $context_snapshot),
        );
      }
    }
    throw new \LogicException('finding_link_not_in_snapshot:' . $link_id);
  }

  private function regionNames(array $finding, array $context_snapshot): string {
    $names = [];
    foreach ((array) ($context_snapshot['dungeon']['regions'] ?? []) as $region) {
      if (is_array($region) && in_array($region['region_id'] ?? NULL, $this->subjectIds($finding, 'region_id'), TRUE)) {
        $names[] = '"' . $region['name'] . '"';
      }
    }
    return implode(' and ', $names);
  }

  /**
   * The room a room_editor finding sends the author to, by display name.
   */
  private function roomToOpen(array $selected, array $context_snapshot): string {
    $finding = is_array($selected['finding'] ?? NULL) ? $selected['finding'] : [];
    $names = [];
    foreach ($this->subjectIds($finding, 'placement_id') as $placement_id) {
      $name = $this->dungeonPlacement($placement_id, $context_snapshot)['room_name'] ?? NULL;
      if (is_string($name) && $name !== '') {
        $names['the room "' . $name . '"'] = TRUE;
      }
    }
    // A finding about a link, or about a room no longer in the library,
    // names no single room.
    return $names === [] ? 'the room' : implode(' and ', array_keys($names));
  }

  /**
   * Names what a Room Editor finding's path points at: an object or a port.
   */
  private function roomFindingSubject(array $finding, array $context_snapshot): string {
    $room = $context_snapshot['room'] ?? NULL;
    if (!is_array($room) || preg_match('#^/(placements|entry_ports|exit_ports)/(\d+)#', (string) ($finding['path'] ?? ''), $match) !== 1) {
      return '';
    }
    $row = $room[$match[1]][(int) $match[2]] ?? NULL;
    if (!is_array($row)) {
      return '';
    }
    return match ($match[1]) {
      'placements' => sprintf('The object "%s": ', $this->readableName((string) ($row['definition_ref']['definition_id'] ?? ''))),
      'entry_ports' => sprintf('The entrance "%s": ', (string) ($row['label'] ?? $row['port_id'] ?? '')),
      'exit_ports' => sprintf('The exit "%s": ', (string) ($row['label'] ?? $row['port_id'] ?? '')),
    };
  }

  /**
   * A quest's authored goal, or its readable name when it states no goal.
   */
  private function questLabel(string $quest_id, array $process_flow): string {
    foreach ((array) ($process_flow['tasks'] ?? []) as $task) {
      if (is_array($task) && ($task['task_id'] ?? NULL) === $quest_id) {
        $goal = trim((string) ($task['goal_text'] ?? ''));
        if ($goal !== '') {
          // The goal's first sentence names the task; nothing is cut mid-word.
          return rtrim(preg_split('/(?<=[.!?])\s+/u', $goal)[0], '.');
        }
      }
    }
    return $this->readableName($quest_id);
  }

  /**
   * An objective's readable name.
   */
  private function objectiveLabel(string $objective_id, array $process_flow): string {
    return $this->readableName(preg_replace('/^objective-/', '', $this->stripDraftPrefix($objective_id)) ?? $objective_id);
  }

  private function stripDraftPrefix(string $id): string {
    return preg_replace('/^draft-[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}-(?:task|objective)-/', '', $id) ?? $id;
  }

  private function readableName(string $id): string {
    return trim(str_replace(['-', '_'], ' ', $this->stripDraftPrefix($id)));
  }

}
