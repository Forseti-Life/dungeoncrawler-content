<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmRealityCheckCallbacks;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmModelInvocationCallbacks;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmRealityCheckPolicyAdapter;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmTurnCoordinatorCallbacks;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmTurnFinalizationCallbacks;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatGmGuardrailText;

trait RoomChatServiceGmPipelineTrait {

  protected function generateGmReply(int $campaign_id, string $room_id, int|string $room_index, int|string $dungeon_id, array &$dungeon_data, ?int $character_id = NULL, ?string $encounter_prefix = NULL, string $channel = 'room'): ?array {
    $gm_started_at = hrtime(true);
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $is_room_entry = $this->isEffectiveRoomEntryTurn($chat);
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $gm_response_cache_key = NULL;
    $checked_response = NULL;
    $response_source = 'unresolved';

    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;

    $turn_context = $this->gmTurnCoordinator->prepareTurnContext(
      $campaign_id,
      $room_id,
      $room_meta,
      $dungeon_data,
      $chat,
      $is_room_entry,
      new GmTurnCoordinatorCallbacks(
        fn (int $callback_campaign_id, string $callback_room_id, array $callback_dungeon_data): array => $this->gatherRoomNpcsWithProfiles($callback_campaign_id, $callback_room_id, $callback_dungeon_data),
        fn (array $callback_room_npcs, string $callback_latest_player_message): ?array => $this->resolveDirectlyAddressedNpc($callback_room_npcs, $callback_latest_player_message, FALSE),
        fn (array $callback_room_meta, array $callback_room_npcs): ?array => $this->resolveExplicitRoomConversationNpc($callback_room_meta, $callback_room_npcs),
        fn (array $callback_chat, array $callback_room_npcs): ?array => $this->resolveActiveDirectConversationNpc($callback_chat, $callback_room_npcs),
        fn (string $callback_text): string => $this->normalizeNpcNameForMatch($callback_text),
        fn (string $callback_latest_player_message, string $callback_normalized_player_message, array $callback_active_conversation_npc): bool => $this->shouldContinueActiveRoomConversation($callback_latest_player_message, $callback_normalized_player_message, $callback_active_conversation_npc),
        fn (string $callback_latest_player_message, array $callback_room_npcs, ?array $callback_effective_direct_npc, ?array $callback_active_conversation_npc): string => $this->classifyRoomTurnIntent($callback_latest_player_message, $callback_room_npcs, $callback_effective_direct_npc, $callback_active_conversation_npc),
        fn (int $callback_campaign_id, string $callback_room_id, array $callback_room_meta, array $callback_dungeon_data, array $callback_room_npcs): array => $this->buildCachedRoomPromptArtifacts($callback_campaign_id, $callback_room_id, $callback_room_meta, $callback_dungeon_data, $callback_room_npcs),
        function (string $callback_stage, int $callback_started_at, array $callback_meta = []): void {
          $this->recordDebugStage($callback_stage, $callback_started_at, $callback_meta);
        }
      )
    );
    $recent = is_array($turn_context['recent'] ?? NULL) ? $turn_context['recent'] : [];
    $latest_player_message = (string) ($turn_context['latest_player_message'] ?? '');
    $history_lines = is_array($turn_context['history_lines'] ?? NULL) ? $turn_context['history_lines'] : [];
    $room_npcs = is_array($turn_context['room_npcs'] ?? NULL) ? $turn_context['room_npcs'] : [];
    $effective_direct_npc = is_array($turn_context['effective_direct_npc'] ?? NULL) ? $turn_context['effective_direct_npc'] : NULL;
    $turn_intent = (string) ($turn_context['turn_intent'] ?? 'gm_narration');
    $route_decision = is_array($turn_context['route_decision'] ?? NULL) ? $turn_context['route_decision'] : [];
    $prompt_artifacts = is_array($turn_context['prompt_artifacts'] ?? NULL) ? $turn_context['prompt_artifacts'] : [];
    $scene_parts = is_array($turn_context['scene_parts'] ?? NULL) ? $turn_context['scene_parts'] : [];

    $session_key = $this->sessionManager->roomChatSessionKey($campaign_id, $room_id);
    $stage_started_at = hrtime(true);
    $deterministic_response = $this->buildDeterministicGmResponse(
      $campaign_id,
      $turn_intent,
      $room_npcs,
      $effective_direct_npc,
      $channel,
      $latest_player_message,
      $room_meta,
      $room_id,
      $dungeon_data,
      $is_room_entry,
      $char_data,
      $character_id
    );
    $deterministic_branch = $this->gmResponseRouting->resolveDeterministicBranch(
      is_array($deterministic_response) ? $deterministic_response : NULL,
      $char_data,
      $turn_intent,
      fn (string $callback_narrative, ?array $callback_character_data): array => $this->validateGmNarrativeRoleBoundary($callback_narrative, $callback_character_data),
      fn (string $callback_player_character_name): string => $this->buildSafeGmBoundaryFallbackNarrative($callback_player_character_name),
      fn (?array $callback_character_data): string => $this->extractPlayerCharacterName($callback_character_data)
    );
    if (!empty($deterministic_branch['handled'])) {
      $checked_response = is_array($deterministic_branch['checked_response'] ?? NULL)
        ? $deterministic_branch['checked_response']
        : NULL;
      $response_source = (string) ($deterministic_branch['response_source'] ?? 'deterministic');
      $this->recordDebugStage('gm.deterministic_short_path', $stage_started_at, is_array($deterministic_branch['debug_meta'] ?? NULL) ? $deterministic_branch['debug_meta'] : []);
    } else {
      $quest_prompt_context = '';
      if ($this->questTracker
        && $latest_player_message !== ''
        && in_array($turn_intent, ['gm_narration', 'quest_query'], TRUE)) {
        $quest_prompt_context = $this->questTracker->buildRelevantQuestPromptContext(
          $campaign_id,
          $character_id,
          $latest_player_message
        );
        if ($quest_prompt_context !== '') {
          $quest_prompt_context = $this->truncateContextBlock($quest_prompt_context, 520, 0.75);
        }
      }

      // Build read-only prompt context scoped to this room so prior-room
      // conversations and unrelated campaign notes do not bleed into this turn.
      $stage_started_at = hrtime(true);
      $session_context = $this->buildCompactSessionContext($session_key, $campaign_id, 2, 900, 320, FALSE);
      $actor_grounding = $this->buildRoomActorGroundingSummary($campaign_id, $room_id, $dungeon_data);
      $room_quest_context = $this->buildRoomQuestbookPromptContext($campaign_id, $room_id, $character_id);

      $prompt_artifact_payload = $this->gmPromptOrchestration->buildPromptArtifacts(
        $session_context,
        $scene_parts,
        $prompt_artifacts,
        $actor_grounding,
        $room_quest_context,
        $quest_prompt_context,
        $history_lines,
        $is_room_entry,
        $turn_intent,
        $this->buildGmPromptGuardrails(),
        count($recent)
      );
      $prompt = (string) ($prompt_artifact_payload['prompt'] ?? '');
      $this->recordDebugStage('gm.user_prompt_assembly', $stage_started_at, (array) ($prompt_artifact_payload['user_prompt_debug_meta'] ?? []));

      // Build enhanced system prompt with character abilities if character_id is available.
      $stage_started_at = hrtime(true);
      $base_system_prompt = $this->promptManager->getBaseSystemPrompt();
      $system_prompt = $base_system_prompt;
      $action_availability = [];

      // Ensure room connections are backfilled from hex_map for older campaigns.
      if ($this->mapGenerator) {
        $this->mapGenerator->backfillRoomConnections($dungeon_data);
      }

      // Build full room inventory for GM awareness.
      $room_inventory = $this->actionProcessor->buildRoomInventory(
        $campaign_id, $room_id, $room_meta, $dungeon_data
      );
      $this->recordDebugStage('gm.room_inventory', $stage_started_at, [
        'summary' => $this->summarizeRoomInventory($room_inventory),
      ]);

      if ($char_data) {
        $action_availability = $this->loadCharacterActionAvailabilityContext($campaign_id, $character_id);
        $system_prompt = $this->actionProcessor->buildEnhancedSystemPrompt(
          $base_system_prompt,
          $char_data,
          $room_meta,
          $room_inventory,
          $dungeon_data,
          $room_index,
          $action_availability
        );
      }
      $system_prompt .= $this->buildGmSystemGuardrails();
      $this->recordDebugStage('gm.system_prompt_assembly', $stage_started_at, [
        'base_system_prompt_length' => strlen($base_system_prompt),
        'system_prompt_length' => strlen($system_prompt),
        'has_character_context' => $char_data !== NULL,
        'has_action_availability' => $action_availability !== [],
        'room_inventory' => $this->summarizeRoomInventory($room_inventory),
      ]);

      $context_data = [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'session_key' => $session_key,
      ];

      $prompt_debug_meta = $this->gmPromptOrchestration->buildPromptDebugMeta(
        count($recent),
        $history_lines,
        $session_context,
        $scene_parts,
        $is_room_entry,
        $quest_prompt_context,
        $this->summarizeRoomInventory($room_inventory),
        $char_data !== NULL
      );

      $llm_resolution = $this->gmTurnCoordinator->handoffLlmGeneration(
        $campaign_id,
        $room_id,
        $character_id,
        $turn_intent,
        $latest_player_message,
        $is_room_entry,
        $history_lines,
        $prompt_artifacts,
        $prompt,
        $system_prompt,
        fn(string $value): string => $this->normalizeNpcNameForMatch($value),
        fn(string $haystack, array $needles): bool => $this->textContainsAny($haystack, $needles),
        function () use (
          $prompt,
          $system_prompt,
          $context_data,
          $campaign_id,
          $room_id,
          $character_id,
          $char_data,
          $room_inventory,
          $prompt_debug_meta
        ): ?array {
          return $this->generateRealityCheckedGmResponse(
            $prompt,
            $system_prompt,
            $context_data,
            $campaign_id,
            $room_id,
            $character_id,
            $char_data,
            $room_inventory,
            $prompt_debug_meta
          );
        },
        function (string $stage_key, int $started_at, array $meta = []): void {
          $this->recordDebugStage($stage_key, $started_at, $meta);
        }
      );
      $checked_response = is_array($llm_resolution['checked_response'] ?? NULL) ? $llm_resolution['checked_response'] : NULL;
      $response_source = (string) ($llm_resolution['response_source'] ?? 'unresolved');
      $gm_response_cache_key = is_string($llm_resolution['gm_response_cache_key'] ?? NULL) ? $llm_resolution['gm_response_cache_key'] : NULL;
    }
    if ($checked_response === NULL) {
      $this->recordDebugStage('gm.primary_flow', $gm_started_at, [
        'intent' => $turn_intent,
        'route_family' => $route_decision['route_family'] ?? 'llm_fallback',
        'resolution_outcome' => $route_decision['resolution_outcome'] ?? 'fallback_to_llm',
        'response_source' => $response_source,
        'room_entry' => $is_room_entry,
        'cluster_hints' => $this->buildGmDefectClusterHints($turn_intent, $response_source),
      ]);
      return NULL;
    }

    $finalization_result = $this->gmTurnFinalization->finalizeTurn(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room_index,
      $dungeon_data,
      $chat,
      $session_key,
      (string) ($checked_response['narrative'] ?? ''),
      is_array($checked_response['actions'] ?? NULL) ? $checked_response['actions'] : [],
      is_array($checked_response['dice_rolls'] ?? NULL) ? $checked_response['dice_rolls'] : [],
      is_array($checked_response['validation_errors'] ?? NULL) ? $checked_response['validation_errors'] : [],
      $checked_response,
      $character_id,
      $turn_intent,
      $effective_direct_npc,
      $room_npcs,
      $latest_player_message,
      $gm_response_cache_key ?? '',
      new GmTurnFinalizationCallbacks(
        fn (string $callback_content): string => $this->stripPlayerVisibleActionBlocks($callback_content),
        fn (string $callback_content): string => $this->trimIncompleteNarrative($callback_content),
        fn (string $callback_content): string => $this->sanitizePlayerVisibleNarrative($callback_content),
        function (string $callback_stage, int $callback_started_at, array $callback_meta = []): void {
          $this->recordDebugStage($callback_stage, $callback_started_at, $callback_meta);
        },
        function (int $callback_campaign_id, array $callback_actions, string $callback_status, array $callback_context = []): void {
          $this->recordCanonicalActionBatch($callback_campaign_id, $callback_actions, $callback_status, $callback_context);
        },
        fn (array $callback_actions, array $callback_validation_errors): array => $this->filterChatBlockedNavigationActions($callback_actions, $callback_validation_errors),
        function (array &$callback_dungeon_data, int|string $callback_room_index, string $callback_turn_intent, ?array $callback_effective_direct_npc, array $callback_room_npcs, string $callback_latest_player_message, ?int $callback_character_id, array $callback_checked_response): void {
          $this->synchronizeExplicitRoomConversationState($callback_dungeon_data, $callback_room_index, $callback_turn_intent, $callback_effective_direct_npc, $callback_room_npcs, $callback_latest_player_message, $callback_character_id, $callback_checked_response);
        },
        fn (string $callback_narrative, array $callback_actions = [], ?array $callback_state_diff = NULL, ?array $callback_navigation_result = NULL): string => $this->buildVisibleGmNarrative($callback_narrative, $callback_actions, $callback_state_diff, $callback_navigation_result),
        fn (string $callback_narrative, array $callback_actions = [], array $callback_dice_rolls = [], bool $callback_suppress_npc_interjections = FALSE): array => $this->buildGmRoomResponsePayload($callback_narrative, $callback_actions, $callback_dice_rolls, $callback_suppress_npc_interjections),
        fn (int $callback_campaign_id, string $callback_room_id, string $callback_content, string $callback_speaker): ?array => $this->bridgeGmReplyToSessionSystem($callback_campaign_id, $callback_room_id, $callback_content, $callback_speaker)
      ),
      self::MAX_MESSAGES_PER_ROOM
    );
    $canonical_results = is_array($finalization_result['canonical_actions'] ?? NULL) ? $finalization_result['canonical_actions'] : [
      'quest_turn_in' => [],
      'combat_initiation' => NULL,
    ];
    $state_diff = $finalization_result['state_diff'] ?? NULL;
    $navigation_result = $finalization_result['navigation'] ?? NULL;
    $gm_message = is_array($finalization_result['message'] ?? NULL) ? $finalization_result['message'] : NULL;
    $suppress_npc_interjections = !empty($finalization_result['suppress_npc_interjections']);
    $narrative = (string) ($finalization_result['narrative'] ?? ($checked_response['narrative'] ?? ''));
    $actions = is_array($finalization_result['actions'] ?? NULL) ? $finalization_result['actions'] : (is_array($checked_response['actions'] ?? NULL) ? $checked_response['actions'] : []);
    $dice_rolls = is_array($finalization_result['dice_rolls'] ?? NULL) ? $finalization_result['dice_rolls'] : (is_array($checked_response['dice_rolls'] ?? NULL) ? $checked_response['dice_rolls'] : []);
    $validation_errors = is_array($finalization_result['validation_errors'] ?? NULL) ? $finalization_result['validation_errors'] : (is_array($checked_response['validation_errors'] ?? NULL) ? $checked_response['validation_errors'] : []);
    $this->recordDebugStage('gm.primary_flow', $gm_started_at, [
      'intent' => $turn_intent,
      'route_family' => $route_decision['route_family'] ?? 'llm_fallback',
      'resolution_outcome' => $route_decision['resolution_outcome'] ?? 'fallback_to_llm',
      'response_source' => $response_source,
      'room_entry' => $is_room_entry,
      'cluster_hints' => $this->buildGmDefectClusterHints($turn_intent, $response_source),
    ]);
    $this->recordDebugStage('gm.total', $gm_started_at, [
      'action_count' => count($actions),
      'validation_error_count' => count($validation_errors),
      'narrative_length' => strlen($narrative),
    ]);

    return [
      'message' => $gm_message,
      'state_diff' => $state_diff,
      'navigation' => $navigation_result,
      'canonical_actions' => $canonical_results,
      'suppress_npc_interjections' => $suppress_npc_interjections,
    ];
  }

  /**
   * Stable user-prompt guardrails for the GM layer.
   */

  protected function buildGmPromptGuardrails(): string {
    return RoomChatGmGuardrailText::buildPromptGuardrails();
  }

  /**
   * Stable system-prompt guardrails for the GM layer.
   */

  protected function buildGmSystemGuardrails(): string {
    return RoomChatGmGuardrailText::buildSystemGuardrails();
  }

  /**
   * Pass through chat actions after normalization-time filtering.
   *
   * Navigation intents are allowed from chat and executed through the same
   * canonical action pipeline as action-rail transitions.
   *
   * @param array<int, array<string, mixed>> $actions
   * @param array<int, string> $validation_errors
   *
   * @return array<int, array<string, mixed>>
   */
  protected function filterChatBlockedNavigationActions(array $actions, array &$validation_errors): array {
    return $actions;
  }

  /**
   * Generate a GM response and run centralized reality validation with retry.
   *
   * If the generated mechanics fail the authoritative resource checks, the
   * model receives a second prompt containing the validated state snapshot and
   * must regenerate before the text is finalized.
   *
   * This is the authoritative generation wrapper for room GM replies. It owns
   * parsing, validation, retry, and fallback correction text. The lower-level
   * invokeGmModel() helper only performs the raw model call and token-budget
   * trimming used by this wrapper.
   */

  protected function generateRealityCheckedGmResponse(
    string $prompt,
    string $system_prompt,
    array $context_data,
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    ?array $character_data,
    array $room_inventory,
    array $prompt_debug_meta = []
  ): ?array {
    $callbacks = new GmRealityCheckCallbacks(
      fn(string $prompt_text, string $system_prompt_text, array $callback_context_data, string $callback_room_id, string $operation, array $debug_meta): ?string => $this->invokeGmModel(
        $prompt_text,
        $system_prompt_text,
        $callback_context_data,
        $callback_room_id,
        $operation,
        $debug_meta
      ),
      function (string $stage, int $started_at, array $meta = []): void {
        $this->recordDebugStage($stage, $started_at, $meta);
      },
      function (int $batch_campaign_id, array $actions, string $status, array $batch_context = []): void {
        $this->recordCanonicalActionBatch($batch_campaign_id, $actions, $status, $batch_context);
      }
    );
    $policy = new GmRealityCheckPolicyAdapter(
      fn(string $response): array => $this->actionProcessor->parseResponse($response),
      fn(string $narrative, ?array $callback_character_data): array => $this->validateGmNarrativeRoleBoundary($narrative, $callback_character_data),
      fn(int $callback_character_id, array $actions, int $callback_campaign_id): array => $this->actionProcessor->validateCharacterActionResources($callback_character_id, $actions, $callback_campaign_id),
      fn(?array $callback_character_data, array $inventory): array => $this->actionProcessor->buildRealitySnapshot($callback_character_data, $inventory),
      fn(array $errors, array $snapshot): string => $this->actionProcessor->buildRealityRetryPrompt($errors, $snapshot),
      fn(string $player_character_name, array $role_boundary_errors): string => $this->buildGmRoleBoundaryRetryPrompt($player_character_name, $role_boundary_errors),
      fn(string $player_character_name): string => $this->buildSafeGmBoundaryFallbackNarrative($player_character_name),
      fn(array $validation_errors): string => $this->actionProcessor->buildValidationFailureSummary($validation_errors),
      fn(?array $callback_character_data): string => $this->extractPlayerCharacterName($callback_character_data)
    );

    return $this->gmRealityCheckGeneration->generate(
      $prompt,
      $system_prompt,
      $context_data,
      $campaign_id,
      $room_id,
      $character_id,
      $character_data,
      $room_inventory,
      $prompt_debug_meta,
      $callbacks,
      $policy
    );
  }

  /**
   * Extract the active player-character display name from character data.
   */

  protected function extractPlayerCharacterName(?array $character_data): string {
    return $this->gmRoleBoundaryPolicy->extractPlayerCharacterName($character_data);
  }

  /**
   * Detect when the GM narrative has slipped into player-character roleplay.
   *
   * @return string[]
   *   Stable error codes describing the boundary violation.
   */

  protected function validateGmNarrativeRoleBoundary(string $narrative, ?array $character_data): array {
    return $this->gmRoleBoundaryPolicy->validateNarrative($narrative, $character_data);
  }

  /**
   * Build a retry prompt when the GM speaks as the player or in-world actor.
   */

  protected function buildGmRoleBoundaryRetryPrompt(string $player_character_name, array $role_boundary_errors): string {
    return $this->gmRoleBoundaryPolicy->buildRetryPrompt($player_character_name, $role_boundary_errors);
  }

  /**
   * Safe fallback when repeated retries still cross the GM/player boundary.
   */

  protected function buildSafeGmBoundaryFallbackNarrative(string $player_character_name = ''): string {
    return $this->gmRoleBoundaryPolicy->buildSafeFallbackNarrative($player_character_name);
  }

  /**
   * Invoke the GM model for room chat.
   *
   * This helper is intentionally narrow: fit the prompt into budget, perform
   * the raw model call, and return the unparsed text. It does not validate or
   * correct actions; generateRealityCheckedGmResponse() is the policy layer.
   */

  protected function invokeGmModel(string $prompt, string $system_prompt, array $context_data, string $room_id, string $operation = 'room_chat_gm_reply', array $debug_meta = []): ?string {
    $callbacks = new GmModelInvocationCallbacks(
      fn(string $callback_prompt, string $callback_system_prompt): array => $this->fitRoomChatContextBudget($callback_prompt, $callback_system_prompt),
      fn(
        string $callback_prompt,
        string $callback_provider,
        string $callback_operation,
        array $callback_context_data,
        array $callback_options,
        array $callback_debug_meta
      ): array => $this->invokeTimedModelCall(
        $callback_prompt,
        $callback_provider,
        $callback_operation,
        $callback_context_data,
        $callback_options,
        $callback_debug_meta
      ),
      function (string $message, array $context = []): void {
        $this->logger->error($message, $context);
      },
      function (string $message, array $context = []): void {
        $this->logger->warning($message, $context);
      }
    );

    return $this->gmModelInvocation->invoke(
      $prompt,
      $system_prompt,
      $context_data,
      $room_id,
      $operation,
      $debug_meta,
      self::ROOM_CHAT_GM_MAX_TOKENS,
      $callbacks
    );
  }

  /**
   * Constrain room-chat prompts to fit smaller local-model context windows.
   */

  protected function fitRoomChatContextBudget(string $prompt, string $system_prompt): array {
    return $this->gmPromptBudget->fit(
      $prompt,
      $system_prompt,
      self::ROOM_CHAT_MAX_INPUT_CHARS,
      self::ROOM_CHAT_MAX_USER_PROMPT_CHARS,
      self::ROOM_CHAT_MAX_SYSTEM_PROMPT_CHARS
    );
  }

  /**
   * Truncate a context block while preserving both rules and recent detail.
   */

  protected function truncateContextBlock(string $text, int $max_chars, float $head_ratio = 0.6): string {
    if ($max_chars <= 0 || strlen($text) <= $max_chars) {
      return $text;
    }

    $separator = "\n[...truncated for model context budget...]\n";
    $available = $max_chars - strlen($separator);
    if ($available <= 40) {
      return substr($text, 0, max(0, $max_chars - 3)) . '...';
    }

    $head_chars = (int) floor($available * $head_ratio);
    $tail_chars = max(0, $available - $head_chars);

    return rtrim(substr($text, 0, $head_chars))
      . $separator
      . ltrim(substr($text, -1 * $tail_chars));
  }

  /**
   * Record canonical action usage entries for observability.
   */

  protected function recordCanonicalActionBatch(int $campaign_id, array $actions, string $status, array $context = []): void {
    $this->gmReplyOrchestration->recordCanonicalActionBatch(
      $this->canonicalActionRegistry,
      $campaign_id,
      $actions,
      $status,
      $context
    );
  }

  /**
   * Load the latest dungeon row and decoded dungeon_data for a campaign.
   *
   * This keeps room chat entry points aligned on one persistence contract.
   */

  protected function loadLatestDungeonSnapshot(
    int $campaign_id,
    ?string $room_id = NULL,
    ?string $preferred_dungeon_id = NULL,
    bool $allow_room_absent = FALSE
  ): array {
    $decoded_record_data = NULL;
    $preferred_dungeon_id = trim((string) $preferred_dungeon_id);
    if ($preferred_dungeon_id !== '') {
      $preferred_record = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'dungeon_data', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('dungeon_id', $preferred_dungeon_id)
        ->orderBy('updated', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (is_array($preferred_record)) {
        $preferred_data = json_decode($preferred_record['dungeon_data'] ?? '{}', TRUE);
        if (is_array($preferred_data)) {
          if (
            $room_id === NULL
            || $room_id === ''
            || $this->payloadContainsRoomId($preferred_data, $room_id)
            || $allow_room_absent
          ) {
            $preferred_data = $this->hydrateSnapshotRoomsFromCampaignAuthority(
              $campaign_id,
              $preferred_data,
              $room_id,
              (string) ($preferred_record['dungeon_id'] ?? ''),
              !$allow_room_absent
            );
            return [
              'dungeon_id' => $preferred_record['dungeon_id'] ?? '',
              'dungeon_data' => $preferred_data,
              'encoded_bytes' => strlen((string) ($preferred_record['dungeon_data'] ?? '')),
            ];
          }
        }
      }
    }

    $records = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if ($records === []) {
      throw new \InvalidArgumentException('Dungeon not found', 404);
    }

    $record = $records[0];
    if ($room_id !== NULL && $room_id !== '') {
      $matched_record = NULL;
      $matched_record_data = NULL;
      $active_room_match = NULL;
      $active_room_match_data = NULL;
      foreach ($records as $candidate) {
        $candidate_record = $this->database->select('dc_campaign_dungeons', 'd')
          ->fields('d', ['dungeon_id', 'dungeon_data', 'updated'])
          ->condition('campaign_id', $campaign_id)
          ->condition('dungeon_id', (string) ($candidate['dungeon_id'] ?? ''))
          ->orderBy('updated', 'DESC')
          ->range(0, 1)
          ->execute()
          ->fetchAssoc();
        if (!is_array($candidate_record)) {
          continue;
        }
        $candidate_data = json_decode($candidate_record['dungeon_data'] ?? '{}', TRUE);
        if (!is_array($candidate_data)) {
          $this->logger->warning('Room snapshot scan skipped malformed dungeon payload: campaign={campaign_id} requested_room={room_id} dungeon_id={dungeon_id} payload_bytes={payload_bytes} decoded_type={decoded_type}', [
            'campaign_id' => $campaign_id,
            'room_id' => $room_id,
            'dungeon_id' => (string) ($candidate_record['dungeon_id'] ?? ''),
            'payload_bytes' => strlen((string) ($candidate_record['dungeon_data'] ?? '')),
            'decoded_type' => get_debug_type($candidate_data),
          ]);
          continue;
        }
        if ($this->payloadContainsRoomId($candidate_data, $room_id)) {
          if ($preferred_dungeon_id !== '' && (string) ($candidate_record['dungeon_id'] ?? '') === $preferred_dungeon_id) {
            $matched_record = $candidate_record;
            $matched_record_data = $candidate_data;
            break;
          }
          $active_room_id = trim((string) ($candidate_data['active_room_id'] ?? $candidate_data['current_room_id'] ?? ''));
          if ($active_room_match === NULL && $active_room_id === $room_id) {
            $active_room_match = $candidate_record;
            $active_room_match_data = $candidate_data;
          }
          if ($matched_record === NULL) {
            $matched_record = $candidate_record;
            $matched_record_data = $candidate_data;
          }
        }
      }
      if ($active_room_match !== NULL) {
        $matched_record = $active_room_match;
        $matched_record_data = $active_room_match_data;
      }
      if ($matched_record === NULL) {
        if ($allow_room_absent) {
          $latest_record = $this->database->select('dc_campaign_dungeons', 'd')
            ->fields('d', ['dungeon_id', 'dungeon_data', 'updated'])
            ->condition('campaign_id', $campaign_id)
            ->orderBy('updated', 'DESC')
            ->range(0, 1)
            ->execute()
            ->fetchAssoc();
          if (!is_array($latest_record)) {
            throw new \InvalidArgumentException('Dungeon not found', 404);
          }
          $record = $latest_record;
        }
        else {
          throw new \InvalidArgumentException(sprintf('Room %s not found in any dungeon', $room_id), 404);
        }
      }
      if ($matched_record !== NULL) {
        $record = $matched_record;
        $decoded_record_data = is_array($matched_record_data) ? $matched_record_data : NULL;
      }
    }
    else {
      $latest_record = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'dungeon_data', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->orderBy('updated', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($latest_record)) {
        throw new \InvalidArgumentException('Dungeon not found', 404);
      }
      $record = $latest_record;
    }

    if (!is_array($decoded_record_data)) {
      $decoded_record_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);
    }
    if (is_array($decoded_record_data)) {
      $decoded_record_data = $this->hydrateSnapshotRoomsFromCampaignAuthority(
        $campaign_id,
        $decoded_record_data,
        $room_id,
        (string) ($record['dungeon_id'] ?? ''),
        !$allow_room_absent
      );
    }

    return [
      'dungeon_id' => $record['dungeon_id'] ?? '',
      'dungeon_data' => is_array($decoded_record_data) ? $decoded_record_data : [],
      'encoded_bytes' => strlen((string) ($record['dungeon_data'] ?? '')),
    ];
  }

  /**
   * Determine whether a dungeon payload contains a room identifier.
   */
  protected function payloadContainsRoomId(array $dungeon_data, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }

    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    if ($this->roomLocator->findRoomIndex($rooms, $room_id) !== NULL) {
      return TRUE;
    }

    $hex_rooms = is_array($dungeon_data['hex_map']['rooms'] ?? NULL) ? $dungeon_data['hex_map']['rooms'] : [];
    if ($this->roomLocator->findRoomIndex($hex_rooms, $room_id) !== NULL) {
      return TRUE;
    }

    foreach ((array) ($dungeon_data['room_ids'] ?? []) as $listed_room_id) {
      if (trim((string) $listed_room_id) === $room_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Rehydrate dungeon_data rooms from campaign-room authority when snapshot rooms are sparse.
   */
  protected function hydrateSnapshotRoomsFromCampaignAuthority(
    int $campaign_id,
    array $dungeon_data,
    ?string $requested_room_id = NULL,
    ?string $dungeon_id = NULL,
    bool $require_requested_room = TRUE
  ): array {
    $requested_room_id = trim((string) $requested_room_id);
    $resolved_dungeon_id = trim((string) (
      $dungeon_id
      ?? $dungeon_data['dungeon_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($resolved_dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'Room chat hydration contract violation: campaign %d snapshot has no resolvable dungeon_id.',
        $campaign_id
      ));
    }

    $active_room_id = trim((string) (
      $dungeon_data['active_room_id']
      ?? $dungeon_data['current_room_id']
      ?? ''
    ));
    $hydrated = $this->runtimeGraphAssembler->buildRuntimeGraph(
      $campaign_id,
      $resolved_dungeon_id,
      $dungeon_data,
      [
        'active_room_id' => $active_room_id,
        'requested_room_id' => $requested_room_id,
      ]
    );

    if ($requested_room_id !== '' && $require_requested_room) {
      $rooms = is_array($hydrated['rooms'] ?? NULL) ? $hydrated['rooms'] : [];
      if ($this->roomLocator->findRoomIndex($rooms, $requested_room_id) === NULL) {
        throw new \RuntimeException(sprintf(
          'Room chat hydration contract violation: campaign %d dungeon %s requested room %s was not materialized (hydrated_room_count=%d).',
          $campaign_id,
          $resolved_dungeon_id,
          $requested_room_id,
          count($rooms)
        ));
      }
    }

    return $hydrated;
  }

  /**
   * Generate an NPC reply for a private channel (whisper/spell).
   *
   * The AI responds as the target NPC rather than the GM. Uses the
   * per-NPC AI session from AiSessionManager for conversation memory.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|string $room_index
   *   Room index.
   * @param int|string $dungeon_id
   *   Dungeon record ID.
   * @param array &$dungeon_data
   *   Dungeon data (modified in place).
   * @param int|null $character_id
   *   Acting character ID.
   * @param string $channel_key
   *   Channel key (e.g. "whisper:goblin_1").
   * @param array $channel_def
   *   Channel definition from dungeon_data.
   *
   * @return array|null
   *   ['message' => array, 'state_diff' => array|null], or NULL.
   */

}
