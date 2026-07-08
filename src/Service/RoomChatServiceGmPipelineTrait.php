<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatGmGuardrailText;

trait RoomChatServiceGmPipelineTrait {

  protected function generateGmReply(int $campaign_id, string $room_id, int|string $room_index, int|string $dungeon_id, array &$dungeon_data, ?int $character_id = NULL, ?string $encounter_prefix = NULL): ?array {
    $gm_started_at = hrtime(true);
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $is_room_entry = $this->isEffectiveRoomEntryTurn($chat);
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $gm_response_cache_key = NULL;
    $checked_response = NULL;
    $response_source = 'unresolved';

    // Build the user prompt from recent chat history.
    $stage_started_at = hrtime(true);
    $recent = array_slice($chat, -3);
    $latest_chat_entry = end($chat);
    $latest_player_message = is_array($latest_chat_entry) ? trim((string) ($latest_chat_entry['message'] ?? '')) : '';
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      if (strlen($text) > 240) {
        $text = substr($text, 0, 237) . '...';
      }
      $history_lines[] = "{$speaker}: {$text}";
    }

    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;

    $stage_started_at = hrtime(true);
    $room_npcs = $this->gatherRoomNpcsWithProfiles($campaign_id, $room_id, $dungeon_data);
    $directly_addressed_npc = $this->resolveDirectlyAddressedNpc($room_npcs, $latest_player_message);
    $persisted_conversation_npc = $this->resolveExplicitRoomConversationNpc($room_meta, $room_npcs);
    $active_conversation_npc = $persisted_conversation_npc ?? $this->resolveActiveDirectConversationNpc($chat, $room_npcs);
    $effective_direct_npc = $directly_addressed_npc;
    $continued_conversation = FALSE;
    if ($effective_direct_npc === NULL && $active_conversation_npc !== NULL) {
      $normalized_player_message = $this->normalizeNpcNameForMatch($latest_player_message);
      if ($this->shouldContinueActiveRoomConversation($latest_player_message, $normalized_player_message, $active_conversation_npc)) {
        $effective_direct_npc = $active_conversation_npc;
        $continued_conversation = TRUE;
      }
    }
    $turn_intent = $this->classifyRoomTurnIntent($latest_player_message, $room_npcs, $effective_direct_npc, $active_conversation_npc);
    $route_decision = $this->turnIntentRouter->routeFromIntent($turn_intent, $is_room_entry);
    $this->recordDebugStage('gm.intent_classification', $stage_started_at, [
      'intent' => $turn_intent,
      'route_family' => $route_decision['route_family'] ?? 'llm_fallback',
      'resolution_outcome' => $route_decision['resolution_outcome'] ?? 'fallback_to_llm',
      'room_npc_count' => count($room_npcs),
      'direct_addressed' => $effective_direct_npc['entity_ref'] ?? NULL,
      'persisted_conversation_npc' => $persisted_conversation_npc['entity_ref'] ?? NULL,
      'active_conversation_npc' => $active_conversation_npc['entity_ref'] ?? NULL,
      'continued_conversation' => $continued_conversation,
    ]);

    $stage_started_at = hrtime(true);
    $prompt_artifacts = $this->buildCachedRoomPromptArtifacts($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
    $scene_parts = $prompt_artifacts['scene_parts'] ?? [];
    $this->recordDebugStage('gm.scene_context', $stage_started_at, [
      'scene_part_count' => count($scene_parts),
      'entity_count' => $prompt_artifacts['entity_count'] ?? 0,
      'entity_summary_count' => $prompt_artifacts['entity_summary_count'] ?? 0,
      'cache' => $prompt_artifacts['cache'] ?? 'unknown',
    ]);

    $session_key = $this->sessionManager->roomChatSessionKey($campaign_id, $room_id);
    $stage_started_at = hrtime(true);
    $deterministic_response = $this->buildDeterministicGmResponse(
      $campaign_id,
      $turn_intent,
      $room_npcs,
      $effective_direct_npc,
      $latest_player_message,
      $room_meta,
      $room_id,
      $dungeon_data,
      $is_room_entry,
      $char_data,
      $character_id
    );
    if ($deterministic_response !== NULL) {
      $deterministic_boundary_errors = $this->validateGmNarrativeRoleBoundary((string) ($deterministic_response['narrative'] ?? ''), $char_data);
      if ($deterministic_boundary_errors !== []) {
        $deterministic_response['narrative'] = $this->buildSafeGmBoundaryFallbackNarrative($this->extractPlayerCharacterName($char_data));
        $deterministic_response['actions'] = [];
        $deterministic_response['dice_rolls'] = [];
        $deterministic_response['validation_errors'] = array_values(array_unique(array_merge(
          $deterministic_response['validation_errors'] ?? [],
          $deterministic_boundary_errors
        )));
      }
      $checked_response = $deterministic_response;
      $response_source = 'deterministic';
      $this->recordDebugStage('gm.deterministic_short_path', $stage_started_at, [
        'intent' => $turn_intent,
        'narrative_length' => strlen((string) ($deterministic_response['narrative'] ?? '')),
        'action_count' => count($deterministic_response['actions'] ?? []),
        'role_boundary_violation_count' => count($deterministic_boundary_errors ?? []),
      ]);
    }
    else {
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

      $prompt_assembly = $this->promptContextAssembler->assemble([
        'session_context' => $session_context,
        'scene_parts' => $scene_parts,
        'npc_roster_summary' => (string) ($prompt_artifacts['npc_roster_summary'] ?? ''),
        'npc_profile_summary' => (string) ($prompt_artifacts['npc_profile_summary'] ?? ''),
        'actor_grounding' => $actor_grounding,
        'room_quest_context' => $room_quest_context,
        'merchant_summary' => (string) ($prompt_artifacts['merchant_summary'] ?? ''),
        'quest_summary' => (string) ($prompt_artifacts['quest_summary'] ?? ''),
        'quest_prompt_context' => $quest_prompt_context,
        'history_lines' => $history_lines,
        'is_room_entry' => $is_room_entry,
        'turn_intent' => $turn_intent,
        'guardrails' => $this->buildGmPromptGuardrails(),
        'recent_message_count' => count($recent),
        'artifact_bytes' => strlen(json_encode($prompt_artifacts) ?: ''),
      ]);
      $prompt = (string) ($prompt_assembly['prompt'] ?? '');
      $this->recordDebugStage('gm.user_prompt_assembly', $stage_started_at, $prompt_assembly['debug_meta'] ?? []);

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

      $prompt_debug_meta = [
        'recent_message_count' => count($recent),
        'history_line_count' => count($history_lines),
        'session_context_length' => strlen($session_context),
        'scene_part_count' => count($scene_parts),
        'room_entry' => $is_room_entry,
        'quest_context_length' => strlen($quest_prompt_context),
        'room_inventory' => $this->summarizeRoomInventory($room_inventory),
        'has_character_context' => $char_data !== NULL,
      ];

      $gm_response_cache_key = NULL;
      $cache_stage_started_at = hrtime(true);
      $should_use_cache = $this->shouldUseGmResponseCache($turn_intent, $latest_player_message, $is_room_entry);
      if ($should_use_cache) {
        $gm_response_cache_key = $this->buildGmResponseCacheKey(
          $campaign_id,
          $room_id,
          $character_id,
          $turn_intent,
          $history_lines,
          $prompt_artifacts,
          $prompt,
          $system_prompt
        );
      }
      $generation_stage_started_at = hrtime(true);
      $policy_result = $this->gmGenerationPolicy->resolve(
        $should_use_cache,
        $gm_response_cache_key,
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
        }
      );
      $checked_response = $policy_result['checked_response'] ?? NULL;
      $response_source = (string) ($policy_result['response_source'] ?? 'unresolved');

      $this->recordDebugStage('gm.response_cache', $cache_stage_started_at, [
        'cache' => (string) ($policy_result['cache_status'] ?? ($gm_response_cache_key ? 'miss' : 'bypass')),
        'turn_intent' => $turn_intent,
      ]);

      if (!empty($policy_result['generation_attempted'])) {
        $this->recordDebugStage('gm.reality_checked_generation', $generation_stage_started_at, [
          'success' => $checked_response !== NULL,
        ]);
      }
    }
    $this->recordDebugStage('gm.primary_flow', $gm_started_at, [
      'intent' => $turn_intent,
      'route_family' => $route_decision['route_family'] ?? 'llm_fallback',
      'resolution_outcome' => $route_decision['resolution_outcome'] ?? 'fallback_to_llm',
      'response_source' => $response_source,
      'room_entry' => $is_room_entry,
      'cluster_hints' => $this->buildGmDefectClusterHints($turn_intent, $response_source),
    ]);
    if ($checked_response === NULL) {
      return NULL;
    }

    $narrative = $checked_response['narrative'] ?? '';
    $actions = $checked_response['actions'] ?? [];
    $dice_rolls = $checked_response['dice_rolls'] ?? [];
    $validation_errors = $checked_response['validation_errors'] ?? [];

    $stage_started_at = hrtime(true);
    $post_process_result = $this->gmNarrativePostProcessor->process(
      $campaign_id,
      $room_id,
      $chat,
      $narrative,
      $actions,
      $dice_rolls,
      $validation_errors,
      $gm_response_cache_key,
      fn (string $value): string => $this->stripPlayerVisibleActionBlocks($value),
      fn (string $value): string => $this->trimIncompleteNarrative($value),
      fn (string $value): string => $this->sanitizePlayerVisibleNarrative($value)
    );
    $narrative = (string) ($post_process_result['narrative'] ?? $narrative);
    $this->recordDebugStage('gm.suggestion_extraction', $stage_started_at);

    $this->recordCanonicalActionBatch($campaign_id, $actions, 'validated', [
      'room_id' => $room_id,
      'character_id' => $character_id,
    ]);
    if (!empty($validation_errors)) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'validation_failure', 'rejected', [
        'room_id' => $room_id,
        'character_id' => $character_id,
        'errors' => $validation_errors,
      ]);
    }

    $canonical_results = [
      'quest_turn_in' => [],
      'combat_initiation' => NULL,
    ];
    if (!empty($actions)) {
      $stage_started_at = hrtime(true);
      $canonical_execution = $this->canonicalExecutionPipeline->execute(
        $campaign_id,
        $room_id,
        $room_meta,
        $character_id,
        $actions,
        $dungeon_data,
        $validation_errors
      );
      $actions = is_array($canonical_execution['actions'] ?? NULL) ? $canonical_execution['actions'] : $actions;
      $canonical_results = is_array($canonical_execution['canonical_results'] ?? NULL) ? $canonical_execution['canonical_results'] : $canonical_results;
      $validation_errors = is_array($canonical_execution['validation_errors'] ?? NULL) ? $canonical_execution['validation_errors'] : $validation_errors;
      $dungeon_data = is_array($canonical_execution['dungeon_data'] ?? NULL) ? $canonical_execution['dungeon_data'] : $dungeon_data;
      $this->recordDebugStage('gm.execute_canonical_actions', $stage_started_at, [
        'action_count' => count($actions),
        'error_count' => (int) ($canonical_execution['error_count'] ?? 0),
      ]);
    }

    // Apply state mutations if there are mechanical actions.
    $stage_started_at = hrtime(true);
    $mutation_result = $this->stateMutationPipeline->apply(
      $dungeon_id,
      $campaign_id,
      $room_index,
      $dungeon_data,
      $character_id,
      $actions,
      $dice_rolls,
      $validation_errors
    );
    $dungeon_data = is_array($mutation_result['dungeon_data'] ?? NULL) ? $mutation_result['dungeon_data'] : $dungeon_data;
    $state_diff = $mutation_result['state_diff'] ?? NULL;
    $char_diff = is_array($mutation_result['char_diff'] ?? NULL) ? $mutation_result['char_diff'] : [];
    $room_diff = is_array($mutation_result['room_diff'] ?? NULL) ? $mutation_result['room_diff'] : [];

    if (!empty($actions)) {
      $this->recordDebugStage('gm.apply_state_changes', $stage_started_at, [
        'action_count' => count($actions),
        'dice_roll_count' => count($dice_rolls),
      ]);

      $this->logger->info('Mechanical actions processed: @count actions, @rolls dice rolls', [
        '@count' => count($actions),
        '@rolls' => count($dice_rolls),
      ]);

      $this->recordCanonicalActionBatch($campaign_id, $actions, 'executed', [
        'room_id' => $room_id,
        'character_id' => $character_id,
      ]);
    }

    // Detect navigate_to_location actions and trigger map generation.
    $navigation_result = NULL;
    if (!empty($actions)) {
      $stage_started_at = hrtime(true);
      $navigation_pipeline = $this->navigationTransitionPipeline->apply(
        $actions,
        $campaign_id,
        $room_id,
        $dungeon_id,
        $room_meta,
        $dungeon_data,
        $room_index,
        $narrative,
        fn (array $value_actions, int $value_campaign_id, string $value_origin_room_id, array $value_dungeon_data, string $value_gm_narrative): ?array => $this->handleNavigationActions(
          $value_actions,
          $value_campaign_id,
          $value_origin_room_id,
          $value_dungeon_data,
          $value_gm_narrative
        ),
        fn (array $value_dungeon_data, string $value_room_id): ?int => $this->resolveNavigationTransitionRoomIndex($value_dungeon_data, $value_room_id),
        function (int $value_campaign_id, int|string $value_dungeon_id, array &$value_dungeon_data, array $value_navigation_result): void {
          $this->appendDestinationArrivalNarration(
            $value_campaign_id,
            $value_dungeon_id,
            $value_dungeon_data,
            $value_navigation_result
          );
        },
        function (array &$value_dungeon_data, array $value_origin_room_meta, array $value_navigation_result): void {
          $this->recordLocationTransition(
            $value_dungeon_data,
            $value_origin_room_meta,
            $value_navigation_result
          );
        }
      );
      $navigation_result = $navigation_pipeline['navigation_result'] ?? NULL;
      $dungeon_data = is_array($navigation_pipeline['dungeon_data'] ?? NULL) ? $navigation_pipeline['dungeon_data'] : $dungeon_data;
      $room_index = $navigation_pipeline['room_index'] ?? $room_index;
      $this->recordDebugStage('gm.handle_navigation', $stage_started_at, [
        'navigation_success' => !empty($navigation_pipeline['navigation_success']),
      ]);
    }

    $this->synchronizeExplicitRoomConversationState(
      $dungeon_data,
      $room_index,
      $turn_intent,
      $effective_direct_npc,
      $room_npcs,
      $latest_player_message,
      $character_id,
      is_array($checked_response) ? $checked_response : []
    );

    $projection_result = $this->gmTranscriptProjector->project(
      $narrative,
      $actions,
      $state_diff,
      $navigation_result,
      is_array($checked_response) ? $checked_response : [],
      $dungeon_data,
      fn (string $value_narrative, array $value_actions = [], ?array $value_state_diff = NULL, ?array $value_navigation_result = NULL): string => $this->buildVisibleGmNarrative(
        $value_narrative,
        $value_actions,
        $value_state_diff,
        $value_navigation_result
      ),
      fn (array $value_dungeon_data, string $value_speaker): ?string => $this->encounterTranscriptPrefixService->buildForSpeaker(
        $value_dungeon_data,
        $value_speaker,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
      ),
      fn (string $value_content, ?string $value_encounter_prefix): string => $this->encounterTranscriptPrefixService->prefixChatText($value_content, $value_encounter_prefix)
    );
    $suppress_visible_gm_response = !empty($projection_result['suppress_visible_gm_response']);
    $suppress_npc_interjections = !empty($projection_result['suppress_npc_interjections']);
    $visible_gm_narrative = (string) ($projection_result['visible_gm_narrative'] ?? '');
    $gm_message = NULL;
    if (!$suppress_visible_gm_response) {
      $stage_started_at = hrtime(true);
      $persistence_result = $this->gmTranscriptPersistencePipeline->persistVisibleReply(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $room_index,
        $dungeon_data,
        $chat,
        $session_key,
        $narrative,
        $visible_gm_narrative,
        $actions,
        $dice_rolls,
        is_array($checked_response) ? $checked_response : [],
        $suppress_npc_interjections,
        self::MAX_MESSAGES_PER_ROOM,
        fn (string $value_narrative, array $value_actions = [], array $value_dice_rolls = [], bool $value_suppress_npc_interjections = FALSE): array => $this->buildGmRoomResponsePayload(
          $value_narrative,
          $value_actions,
          $value_dice_rolls,
          $value_suppress_npc_interjections
        ),
        function (int $value_campaign_id, int|string $value_dungeon_id, string $value_room_id, string $value_narrative, array $value_actions = [], array $value_dice_rolls = []): void {
          $this->bridgeGmReplyToSessionSystem(
            $value_campaign_id,
            $value_dungeon_id,
            $value_room_id,
            $value_narrative,
            $value_actions,
            $value_dice_rolls
          );
        }
      );
      $gm_message = is_array($persistence_result['gm_message'] ?? NULL) ? $persistence_result['gm_message'] : NULL;
      $dungeon_data = is_array($persistence_result['dungeon_data'] ?? NULL) ? $persistence_result['dungeon_data'] : $dungeon_data;
      $this->recordDebugStage('gm.persist_reply', $stage_started_at, [
        'narrative_length' => strlen($narrative),
        'action_count' => count($actions),
      ]);

      $stage_started_at = hrtime(true);
      $this->recordDebugStage('gm.session_bridge', $stage_started_at, [
        'session_key' => $session_key,
      ]);

      $this->logger->info('GM reply persisted in room @room (@chars chars, @actions_count mechanical actions)', [
        '@room' => $room_id,
        '@chars' => strlen($narrative),
        '@actions_count' => count($actions),
      ]);
    }
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
   * Navigation-transition callback wrapper for room-index re-resolution.
   */

  protected function resolveNavigationTransitionRoomIndex(array $dungeon_data, string $room_id): ?int {
    return $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
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
    $player_character_name = $this->extractPlayerCharacterName($character_data);
    $stage_started_at = hrtime(true);
    $attempt = $this->invokeGmModel($prompt, $system_prompt, $context_data, $room_id, 'room_chat_gm_reply', $prompt_debug_meta + [
      'attempt' => 1,
    ]);
    $this->recordDebugStage('gm.llm_primary', $stage_started_at, [
      'success' => $attempt !== NULL,
    ]);
    if ($attempt === NULL) {
      return NULL;
    }

    $stage_started_at = hrtime(true);
    $parsed = $this->actionProcessor->parseResponse($attempt);
    $actions = $parsed['actions'] ?? [];
    $validation_errors = [];
    $role_boundary_errors = $this->validateGmNarrativeRoleBoundary((string) ($parsed['narrative'] ?? ''), $character_data);
    $this->recordDebugStage('gm.parse_primary_response', $stage_started_at, [
      'action_count' => count($actions),
      'dice_roll_count' => count($parsed['dice_rolls'] ?? []),
      'narrative_length' => strlen((string) ($parsed['narrative'] ?? '')),
    ]);
    $this->recordDebugStage('gm.validate_primary_narrative_boundary', hrtime(true), [
      'violation_count' => count($role_boundary_errors),
    ]);

    $this->recordCanonicalActionBatch($campaign_id, $actions, 'proposed', [
      'room_id' => $room_id,
      'character_id' => $character_id,
      'attempt' => 1,
    ]);

    if (!empty($actions) && $character_id) {
      $stage_started_at = hrtime(true);
      $validation = $this->actionProcessor->validateCharacterActionResources($character_id, $actions, $campaign_id);
      $actions = $validation['actions'] ?? [];
      $validation_errors = $validation['errors'] ?? [];
      $this->recordDebugStage('gm.validate_primary_actions', $stage_started_at, [
        'action_count' => count($actions),
        'validation_error_count' => count($validation_errors),
      ]);
    }

    if (!empty($validation_errors) || !empty($role_boundary_errors)) {
      $retry_prompt = $prompt;
      if (!empty($validation_errors) && $character_id) {
        $snapshot = $this->actionProcessor->buildRealitySnapshot($character_data, $room_inventory);
        $retry_prompt .= "\n\n---\n" . $this->actionProcessor->buildRealityRetryPrompt($validation_errors, $snapshot);
      }
      if (!empty($role_boundary_errors)) {
        $retry_prompt .= "\n\n---\n" . $this->buildGmRoleBoundaryRetryPrompt($player_character_name, $role_boundary_errors);
      }
      $retry_context = $context_data + [
        'reality_retry' => 1,
        'campaign_id' => $campaign_id,
      ];

      $stage_started_at = hrtime(true);
      $retry = $this->invokeGmModel($retry_prompt, $system_prompt, $retry_context, $room_id, 'room_chat_gm_retry', $prompt_debug_meta + [
        'attempt' => 2,
        'validation_error_count' => count($validation_errors),
        'role_boundary_error_count' => count($role_boundary_errors),
      ]);
      $this->recordDebugStage('gm.llm_retry', $stage_started_at, [
        'success' => $retry !== NULL,
      ]);
      if ($retry !== NULL) {
        $stage_started_at = hrtime(true);
        $retry_parsed = $this->actionProcessor->parseResponse($retry);
        $retry_actions = $retry_parsed['actions'] ?? [];
        $retry_validation_errors = [];
        $retry_role_boundary_errors = $this->validateGmNarrativeRoleBoundary((string) ($retry_parsed['narrative'] ?? ''), $character_data);
        $this->recordDebugStage('gm.parse_retry_response', $stage_started_at, [
          'action_count' => count($retry_actions),
          'dice_roll_count' => count($retry_parsed['dice_rolls'] ?? []),
          'narrative_length' => strlen((string) ($retry_parsed['narrative'] ?? '')),
        ]);
        $this->recordDebugStage('gm.validate_retry_narrative_boundary', hrtime(true), [
          'violation_count' => count($retry_role_boundary_errors),
        ]);

        $this->recordCanonicalActionBatch($campaign_id, $retry_actions, 'proposed_retry', [
          'room_id' => $room_id,
          'character_id' => $character_id,
          'attempt' => 2,
        ]);

        if (!empty($retry_actions) && $character_id) {
          $stage_started_at = hrtime(true);
          $retry_validation = $this->actionProcessor->validateCharacterActionResources($character_id, $retry_actions, $campaign_id);
          $retry_actions = $retry_validation['actions'] ?? [];
          $retry_validation_errors = $retry_validation['errors'] ?? [];
          $this->recordDebugStage('gm.validate_retry_actions', $stage_started_at, [
            'action_count' => count($retry_actions),
            'validation_error_count' => count($retry_validation_errors),
          ]);
        }

        if (empty($retry_validation_errors) && empty($retry_role_boundary_errors)) {
          return [
            'narrative' => $retry_parsed['narrative'] ?? '',
            'actions' => $retry_actions,
            'dice_rolls' => $retry_parsed['dice_rolls'] ?? [],
            'validation_errors' => [],
          ];
        }

        $validation_errors = $retry_validation_errors;
        $role_boundary_errors = $retry_role_boundary_errors;
        $parsed = $retry_parsed;
        $actions = [];
      }
      else {
        $actions = [];
      }

      if (!empty($role_boundary_errors)) {
        return [
          'narrative' => $this->buildSafeGmBoundaryFallbackNarrative($player_character_name),
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => array_values(array_unique(array_merge($validation_errors, $role_boundary_errors))),
        ];
      }

      $narrative = rtrim((string) ($parsed['narrative'] ?? ''));
      $correction = $this->actionProcessor->buildValidationFailureSummary($validation_errors);
      if ($correction !== '') {
        $narrative .= ($narrative !== '' ? "\n\n" : '') . $correction;
      }

      return [
        'narrative' => $narrative,
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => $validation_errors,
      ];
    }

    return [
      'narrative' => $parsed['narrative'] ?? '',
      'actions' => $actions,
      'dice_rolls' => $parsed['dice_rolls'] ?? [],
      'validation_errors' => [],
    ];
  }

  /**
   * Extract the active player-character display name from character data.
   */

  protected function extractPlayerCharacterName(?array $character_data): string {
    if (!is_array($character_data)) {
      return '';
    }

    $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
    return trim((string) ($basic_info['name'] ?? $character_data['name'] ?? ''));
  }

  /**
   * Detect when the GM narrative has slipped into player-character roleplay.
   *
   * @return string[]
   *   Stable error codes describing the boundary violation.
   */

  protected function validateGmNarrativeRoleBoundary(string $narrative, ?array $character_data): array {
    $trimmed = trim($narrative);
    if ($trimmed === '') {
      return [];
    }

    $errors = [];
    if (preg_match('/(^|[\s\(\["“‘])I(?:\'m| am|\'ve|\'ll|\'d)?\b/ui', $trimmed)
      || preg_match('/(^|[\s\(\["“‘])(?:me|my|mine)\b/ui', $trimmed)) {
      $errors[] = 'gm_role_boundary_first_person_voice';
    }

    $player_character_name = $this->extractPlayerCharacterName($character_data);
    if ($player_character_name !== '') {
      $escaped_name = preg_quote($player_character_name, '/');
      if (preg_match('/\b' . $escaped_name . '\b.{0,120}\b(?:say|says|said|ask|asks|asked|reply|replies|replied|lean|leans|leaned|gesture|gestures|gestured|grin|grins|grinned|smile|smiles|smiled|nod|nods|nodded|flash|flashes|flashed|brace|braces|braced|tap|taps|tapped|wave|waves|waved|look|looks|looked|keep|keeps|kept|drum|drums|drummed)\b/uis', $trimmed)
        || preg_match('/\b' . $escaped_name . '\b.{0,120}(?:["“]|\'[A-Za-z])/uis', $trimmed)) {
        $errors[] = 'gm_role_boundary_player_character_roleplay';
      }
    }

    if (preg_match('/^\s*(?:\*.*?\*|(?:He|She|They)\s+(?:leans|braces|gestures|smiles|grins|nods|taps|waves|looks|keeps|drums|lets|takes|flashes)\b)/uis', $trimmed)) {
      $errors[] = 'gm_role_boundary_staged_in_world_roleplay';
    }

    return array_values(array_unique($errors));
  }

  /**
   * Build a retry prompt when the GM speaks as the player or in-world actor.
   */

  protected function buildGmRoleBoundaryRetryPrompt(string $player_character_name, array $role_boundary_errors): string {
    $character_label = $player_character_name !== '' ? $player_character_name : 'the player character';
    $codes = implode(', ', array_values(array_unique($role_boundary_errors)));

    return "Your previous response violated the GM role boundary ({$codes})."
      . "\nRegenerate the entire response as the Game Master referee layer only."
      . "\nDo NOT speak as {$character_label}."
      . "\nDo NOT write first-person player-character dialogue, inner thoughts, body language, or staged in-world performance."
      . "\nDo NOT write dialogue for NPCs from the GM layer."
      . "\nReturn only grounded scene narration/adjudication from the GM perspective.";
  }

  /**
   * Safe fallback when repeated retries still cross the GM/player boundary.
   */

  protected function buildSafeGmBoundaryFallbackNarrative(string $player_character_name = ''): string {
    return 'The scene remains grounded around you, with the visible room occupants and current situation still before you.';
  }

  /**
   * Invoke the GM model for room chat.
   *
   * This helper is intentionally narrow: fit the prompt into budget, perform
   * the raw model call, and return the unparsed text. It does not validate or
   * correct actions; generateRealityCheckedGmResponse() is the policy layer.
   */

  protected function invokeGmModel(string $prompt, string $system_prompt, array $context_data, string $room_id, string $operation = 'room_chat_gm_reply', array $debug_meta = []): ?string {
    ['prompt' => $prompt, 'system_prompt' => $system_prompt, 'trim_meta' => $trim_meta] = $this->fitRoomChatContextBudget($prompt, $system_prompt);
    if ($trim_meta['trimmed']) {
      $debug_meta['context_trim'] = $trim_meta;
    }

    try {
      $result = $this->invokeTimedModelCall(
        $prompt,
        'dungeoncrawler_content',
        $operation,
        $context_data,
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => self::ROOM_CHAT_GM_MAX_TOKENS,
          'skip_cache' => TRUE,
        ],
        $debug_meta
      );
    }
    catch (\Throwable $e) {
      $this->logger->error('AI API error generating GM reply: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      $this->logger->warning('AI API returned unsuccessful or empty response for GM reply in room @room', [
        '@room' => $room_id,
      ]);
      return NULL;
    }

    return (string) $result['response'];
  }

  /**
   * Constrain room-chat prompts to fit smaller local-model context windows.
   */

  protected function fitRoomChatContextBudget(string $prompt, string $system_prompt): array {
    $original_prompt_length = strlen($prompt);
    $original_system_length = strlen($system_prompt);

    $trimmed_prompt = $this->truncateContextBlock($prompt, self::ROOM_CHAT_MAX_USER_PROMPT_CHARS, 0.45);
    $trimmed_system = $this->truncateContextBlock($system_prompt, self::ROOM_CHAT_MAX_SYSTEM_PROMPT_CHARS, 0.65);

    $total_length = strlen($trimmed_prompt) + strlen($trimmed_system);
    if ($total_length > self::ROOM_CHAT_MAX_INPUT_CHARS) {
      $remaining_for_prompt = max(1200, self::ROOM_CHAT_MAX_INPUT_CHARS - strlen($trimmed_system));
      $trimmed_prompt = $this->truncateContextBlock($trimmed_prompt, $remaining_for_prompt, 0.4);
      $total_length = strlen($trimmed_prompt) + strlen($trimmed_system);
      if ($total_length > self::ROOM_CHAT_MAX_INPUT_CHARS) {
        $remaining_for_system = max(3200, self::ROOM_CHAT_MAX_INPUT_CHARS - strlen($trimmed_prompt));
        $trimmed_system = $this->truncateContextBlock($trimmed_system, $remaining_for_system, 0.7);
      }
    }

    return [
      'prompt' => $trimmed_prompt,
      'system_prompt' => $trimmed_system,
      'trim_meta' => [
        'trimmed' => $trimmed_prompt !== $prompt || $trimmed_system !== $system_prompt,
        'original_prompt_length' => $original_prompt_length,
        'final_prompt_length' => strlen($trimmed_prompt),
        'original_system_length' => $original_system_length,
        'final_system_length' => strlen($trimmed_system),
      ],
    ];
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
    foreach ($actions as $action) {
      $action_type = (string) ($action['type'] ?? 'other');
      $this->canonicalActionRegistry->recordUsage($campaign_id, $action_type, $status, $context + [
        'action_name' => $action['name'] ?? $action_type,
        'details' => $action['details'] ?? [],
      ]);
    }
  }

  /**
   * Load the latest dungeon row and decoded dungeon_data for a campaign.
   *
   * This keeps room chat entry points aligned on one persistence contract.
   */

  protected function loadLatestDungeonSnapshot(int $campaign_id, ?string $room_id = NULL): array {
    $records = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
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
      foreach ($records as $candidate) {
        $candidate_data = json_decode($candidate['dungeon_data'] ?? '{}', TRUE);
        if (!is_array($candidate_data)) {
          $this->logger->warning('Room snapshot scan skipped malformed dungeon payload: campaign={campaign_id} requested_room={room_id} dungeon_id={dungeon_id} payload_bytes={payload_bytes} decoded_type={decoded_type}', [
            'campaign_id' => $campaign_id,
            'room_id' => $room_id,
            'dungeon_id' => (string) ($candidate['dungeon_id'] ?? ''),
            'payload_bytes' => strlen((string) ($candidate['dungeon_data'] ?? '')),
            'decoded_type' => get_debug_type($candidate_data),
          ]);
          continue;
        }
        $rooms = is_array($candidate_data['rooms'] ?? NULL) ? $candidate_data['rooms'] : [];
        if ($this->roomLocator->findRoomIndex($rooms, $room_id) !== NULL) {
          $matched_record = $candidate;
          break;
        }
      }
      if ($matched_record === NULL) {
        throw new \InvalidArgumentException(sprintf('Room %s not found in any dungeon', $room_id), 404);
      }
      $record = $matched_record;
    }

    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);

    return [
      'dungeon_id' => $record['dungeon_id'] ?? '',
      'dungeon_data' => is_array($dungeon_data) ? $dungeon_data : [],
      'encoded_bytes' => strlen((string) ($record['dungeon_data'] ?? '')),
    ];
  }

  /**
   * Reload latest dungeon_data from persistence.
   */

  protected function reloadDungeonData(int $campaign_id): array {
    return $this->loadLatestDungeonSnapshot($campaign_id)['dungeon_data'];
  }

  /**
   * Detect and handle navigate_to_location actions from GM response.
   *
   * When the GM emits a navigate_to_location action, this triggers the
   * MapGeneratorService to create a new room/setting for the destination.
   *
   * @param array $actions
   *   Parsed actions from the GM response.
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $origin_room_id
   *   Current room UUID.
   * @param array $dungeon_data
   *   Current dungeon data.
   * @param string $gm_narrative
   *   The GM's transition narrative.
   *
   * @return array|null
   *   Navigation result with new room data, or NULL if no navigation.
   */

  protected function handleNavigationActions(
    array $actions,
    int $campaign_id,
    string $origin_room_id,
    array $dungeon_data,
    string $gm_narrative
  ): ?array {
    $this->logger->notice('Navigation handoff enter: campaign=@campaign_id origin_room_id=@origin_room_id action_count=@action_count gm_narrative_chars=@gm_narrative_chars', [
      '@campaign_id' => $campaign_id,
      '@origin_room_id' => $origin_room_id,
      '@action_count' => count($actions),
      '@gm_narrative_chars' => strlen($gm_narrative),
    ]);

    // Find navigate_to_location action(s).
    $nav_actions = array_filter($actions, fn($a) => ($a['type'] ?? '') === 'navigate_to_location');

    if (empty($nav_actions)) {
      $this->logger->notice('Navigation handoff exit: campaign=@campaign_id origin_room_id=@origin_room_id result=no_navigation_action', [
        '@campaign_id' => $campaign_id,
        '@origin_room_id' => $origin_room_id,
      ]);
      return NULL;
    }

    if (!$this->mapGenerator) {
      $this->logger->warning('Navigation action detected but MapGeneratorService is not available');
      return NULL;
    }

    // Use the first navigation action (shouldn't be multiple).
    $nav = reset($nav_actions);
    $nav_payload = $this->buildCanonicalNavigationActionPayload(
      is_array($nav) ? $nav : [],
      $campaign_id,
      $origin_room_id
    );
    $this->validateNavigationActionPayload($nav_payload);
    $details = $nav_payload['details'];
    $destination = $details['destination'];
    $destination_desc = $details['destination_description'];
    $this->logger->notice('Navigation action parsed: campaign=@campaign_id origin_room_id=@origin_room_id destination=@destination destination_description=@destination_description nav_name=@nav_name', [
      '@campaign_id' => $campaign_id,
      '@origin_room_id' => $origin_room_id,
      '@destination' => $destination,
      '@destination_description' => $destination_desc,
      '@nav_name' => (string) ($nav['name'] ?? ''),
    ]);

    // Gather narrative context.
    $narrative_context = [
      'gm_narrative' => $gm_narrative,
      'campaign_theme' => $dungeon_data['theme'] ?? 'high fantasy',
      'party_level' => $dungeon_data['generation_rules']['party_level_target'] ?? 1,
      'time_of_day' => $this->inferTimeOfDay($dungeon_data),
      'travel_type' => $details['travel_type'],
      'estimated_distance' => $details['estimated_distance'],
      'destination_description' => $destination_desc,
    ];

    try {
      $result = $this->mapGenerator->generateSetting(
        $campaign_id,
        $destination,
        $origin_room_id,
        $narrative_context
      );

      $this->logger->info('Navigation triggered: @dest → room @name (index @idx, @hexes hexes)', [
        '@dest' => $destination,
        '@name' => $result['room']['name'] ?? 'Unknown',
        '@idx' => $result['room_index'] ?? '?',
        '@hexes' => count($result['room']['hexes'] ?? []),
      ]);
      $this->logger->notice('Navigation handoff exit: campaign=@campaign_id origin_room_id=@origin_room_id destination=@destination result_room_id=@result_room_id result_room_name=@result_room_name result_source=@result_source entities_added=@entities_added', [
        '@campaign_id' => $campaign_id,
        '@origin_room_id' => $origin_room_id,
        '@destination' => $destination,
        '@result_room_id' => (string) ($result['room']['room_id'] ?? ''),
        '@result_room_name' => (string) ($result['room']['name'] ?? ''),
        '@result_source' => (string) ($result['source'] ?? 'unknown'),
        '@entities_added' => count($result['entities'] ?? []),
      ]);

      return [
        'type' => 'navigate_to_location',
        'origin_room_id' => $origin_room_id,
        'destination' => $destination,
        'destination_description' => $destination_desc,
        'travel_type' => $details['travel_type'],
        'estimated_distance' => $details['estimated_distance'],
        'new_room' => $result['room'],
        'new_room_index' => $result['room_index'],
        'entities' => $result['entities'] ?? [],
        'entities_added' => count($result['entities'] ?? []),
        'dungeon_data' => $result['dungeon_data'] ?? [],
        'source' => $result['source'] ?? NULL,
        'template_id' => $result['template_id'] ?? NULL,
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to generate new setting for navigation to @dest: @err', [
        '@dest' => $destination,
        '@err' => $e->getMessage(),
      ]);
      $this->logger->notice('Navigation handoff exit: campaign=@campaign_id origin_room_id=@origin_room_id destination=@destination result=error error=@error', [
        '@campaign_id' => $campaign_id,
        '@origin_room_id' => $origin_room_id,
        '@destination' => $destination,
        '@error' => $e->getMessage(),
      ]);
      return [
        'type' => 'navigate_to_location',
        'destination' => $destination,
        'error' => 'Failed to generate the new location. Try again.',
      ];
    }
  }

  /**
   * Normalize a navigation action into the canonical navigation contract.
   */

  protected function buildCanonicalNavigationActionPayload(
    array $action,
    int $campaign_id = 0,
    string $source_room_id = '',
    ?string $actor_id = NULL
  ): array {
    $details = is_array($action['details'] ?? NULL) ? $action['details'] : [];
    $state_changes = is_array($action['state_changes'] ?? NULL) ? $action['state_changes'] : [];
    $destination = trim((string) ($details['destination'] ?? ''));
    $normalized_source_room_id = $this->normalizeNavigationRoomId(
      $source_room_id !== '' ? $source_room_id : (string) ($details['source_room_id'] ?? $action['source_room_id'] ?? '')
    );
    $target_room_id = $this->normalizeNavigationRoomId(
      (string) ($details['destination_room_id'] ?? $details['target_room_id'] ?? $action['target_room_id'] ?? '')
    );
    if ($target_room_id === '') {
      $target_room_id = $this->buildNavigationRoomIdFromDestination($destination);
    }
    $resolved_actor_id = $this->normalizeNavigationActorId(
      $actor_id
      ?? (string) ($action['actor_id'] ?? $details['actor_id'] ?? ($state_changes['character']['actor_id'] ?? ''))
    );
    if ($resolved_actor_id === '') {
      $resolved_actor_id = 'party_lead';
    }

    $payload = [
      'schema_version' => self::NAVIGATION_ACTION_SCHEMA_VERSION,
      'campaign_id' => max(1, $campaign_id),
      'actor_id' => $resolved_actor_id,
      'source_room_id' => $normalized_source_room_id,
      'target_room_id' => $target_room_id,
      'transition_mode' => 'in_session',
      'type' => 'navigate_to_location',
      'name' => trim((string) ($action['name'] ?? 'Travel')),
      'details' => [
        'destination' => $destination,
        'destination_description' => trim((string) ($details['destination_description'] ?? '')),
        'travel_type' => trim((string) ($details['travel_type'] ?? 'walk')),
        'estimated_distance' => trim((string) ($details['estimated_distance'] ?? 'short')),
        'destination_room_id' => $target_room_id,
      ],
      'state_changes' => [
        'character' => is_array($state_changes['character'] ?? NULL) ? $state_changes['character'] : [],
        'room' => is_array($state_changes['room'] ?? NULL) ? $state_changes['room'] : [],
      ],
    ];

    if ($payload['details']['destination_description'] === '') {
      $payload['details']['destination_description'] = $payload['details']['destination'];
    }

    if ($payload['name'] === '') {
      $payload['name'] = 'Travel to ' . $payload['details']['destination'];
    }

    return $payload;
  }

  /**
   * Normalize actor identity used by navigation contracts.
   */

  protected function normalizeNavigationActorId(string $actor_id): string {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      return '';
    }

    $normalized = strtolower($actor_id);
    $normalized = preg_replace('/[^a-z0-9_-]+/', '_', $normalized) ?? '';
    $normalized = trim($normalized, '_');

    return $normalized;
  }

  /**
   * Normalize room identity used by navigation contracts.
   */

  protected function normalizeNavigationRoomId(string $room_id): string {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return '';
    }
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $room_id)) {
      return $room_id;
    }

    $normalized = strtolower($room_id);
    $normalized = preg_replace('/[^a-z0-9_-]+/', '_', $normalized) ?? '';
    $normalized = trim($normalized, '_');

    return $normalized;
  }

  /**
   * Build a deterministic room identifier when only destination text is provided.
   */

  protected function buildNavigationRoomIdFromDestination(string $destination): string {
    $candidate = $this->normalizeNavigationRoomId($destination);
    if ($candidate !== '') {
      return $candidate;
    }

    throw new \RuntimeException('Navigation action contract violation: unable to derive target_room_id from destination text.');
  }

  /**
   * Enforce the canonical navigation action contract.
   */

  protected function validateNavigationActionPayload(array $payload): void {
    if (!$this->stateValidationService) {
      return;
    }

    $validation = $this->stateValidationService->validateNavigationAction($payload);
    if (!empty($validation['valid'])) {
      return;
    }

    throw new \RuntimeException('Navigation action contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Record a location transition in dungeon_data.
   *
   * Updates location_history and last_navigation so the GM has arrival
   * context and can reference where the party has been.
   *
   * @param array &$dungeon_data
   *   Dungeon data (modified in place).
   * @param array $origin_room_meta
   *   Room metadata for the origin room.
   * @param array $navigation_result
   *   Navigation result from handleNavigationActions().
   */

  protected function recordLocationTransition(array &$dungeon_data, array $origin_room_meta, array $navigation_result): void {
    $origin_name = $origin_room_meta['name'] ?? 'Unknown';
    $origin_id = $origin_room_meta['room_id'] ?? '';
    $dest_name = $navigation_result['new_room']['name'] ?? $navigation_result['destination'] ?? 'Unknown';
    $dest_id = $navigation_result['new_room']['room_id'] ?? '';
    $timestamp = date('c');

    // Initialize location_history if not present.
    if (!isset($dungeon_data['location_history'])) {
      $dungeon_data['location_history'] = [];
    }

    // If this is the first navigation, also record the starting room.
    if (empty($dungeon_data['location_history'])) {
      $dungeon_data['location_history'][] = [
        'room_id' => $origin_id,
        'room_name' => $origin_name,
        'action' => 'started at',
        'timestamp' => $timestamp,
      ];
    }

    // Record the departure from origin.
    $dungeon_data['location_history'][] = [
      'room_id' => $origin_id,
      'room_name' => $origin_name,
      'action' => 'departed',
      'timestamp' => $timestamp,
    ];

    // Record the arrival at destination.
    $dungeon_data['location_history'][] = [
      'room_id' => $dest_id,
      'room_name' => $dest_name,
      'action' => 'arrived at',
      'timestamp' => $timestamp,
    ];

    // Set last_navigation context for the next GM prompt.
    $dungeon_data['last_navigation'] = [
      'from_room_id' => $origin_id,
      'from_room_name' => $origin_name,
      'to_room_id' => $dest_id,
      'to_room_name' => $dest_name,
      'travel_type' => $navigation_result['travel_type'] ?? 'traveled',
      'timestamp' => $timestamp,
    ];
    if ($dest_id !== '') {
      $dungeon_data['current_room_id'] = $dest_id;
      $dungeon_data['active_room_id'] = $dest_id;
    }

    // Cap location_history to 50 entries.
    if (count($dungeon_data['location_history']) > 50) {
      $dungeon_data['location_history'] = array_slice($dungeon_data['location_history'], -50);
    }
  }

  /**
   * Append an arrival or return narration into the destination room chat.
   */

  protected function appendDestinationArrivalNarration(
    int $campaign_id,
    int|string $dungeon_id,
    array &$dungeon_data,
    array $navigation_result
  ): void {
    $destination_room = is_array($navigation_result['new_room'] ?? NULL) ? $navigation_result['new_room'] : [];
    $destination_room_id = (string) ($destination_room['room_id'] ?? '');
    if ($destination_room_id === '') {
      return;
    }

    if (!isset($dungeon_data['rooms']) || !is_array($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'], $destination_room_id);
    if ($room_index === NULL) {
      $dungeon_data['rooms'][] = [
        'room_id' => $destination_room_id,
        'name' => $destination_room['name'] ?? $navigation_result['destination'] ?? $destination_room_id,
        'chat' => [],
      ];
      $room_index = array_key_last($dungeon_data['rooms']);
    }

    if (!isset($dungeon_data['rooms'][$room_index]['chat']) || !is_array($dungeon_data['rooms'][$room_index]['chat'])) {
      $dungeon_data['rooms'][$room_index]['chat'] = [];
    }

    $destination_name = trim((string) ($destination_room['name'] ?? $navigation_result['destination'] ?? $destination_room_id));
    $is_return_trip = $this->hasVisitedRoomId($dungeon_data, $destination_room_id);
    $arrival_text = $is_return_trip
      ? 'You return to ' . $destination_name . '.'
      : 'You arrive at ' . $destination_name . '.';

    $latest = end($dungeon_data['rooms'][$room_index]['chat']);
    if (!is_array($latest) || ($latest['message'] ?? '') !== $arrival_text || ($latest['speaker'] ?? '') !== 'Game Master') {
      $dungeon_data['rooms'][$room_index]['chat'][] = [
        'speaker' => 'System',
        'message' => $arrival_text,
        'type' => 'system',
        'channel' => 'room',
        'timestamp' => date('c'),
        'character_id' => NULL,
        'user_id' => 0,
      ];

      $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
      if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
        $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
          $dungeon_data['rooms'][$room_index]['chat'],
          $chat_count - self::MAX_MESSAGES_PER_ROOM
        );
      }
    }

    try {
      $destination_session_key = $this->sessionManager->roomChatSessionKey($campaign_id, $destination_room_id);
      $this->sessionManager->appendMessage($destination_session_key, $campaign_id, 'system', $arrival_text);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to append destination arrival to room session @room: @msg', [
        '@room' => $destination_room_id,
        '@msg' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Determine whether a room id has already been visited.
   */

  protected function hasVisitedRoomId(array $dungeon_data, string $room_id): bool {
    if ($room_id === '') {
      return FALSE;
    }

    foreach ($dungeon_data['location_history'] ?? [] as $entry) {
      if (is_array($entry) && (string) ($entry['room_id'] ?? '') === $room_id) {
        return TRUE;
      }
    }

    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    return $room_index !== NULL && !empty($dungeon_data['rooms'][$room_index]['chat']);
  }

  /**
   * Determine whether the named destination corresponds to a prior visit.
   */

  protected function hasVisitedDestinationName(array $dungeon_data, string $destination): bool {
    $needle = $this->normalizeNavigationLocationName($destination);
    if ($needle === '') {
      return FALSE;
    }

    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (!is_array($room)) {
        continue;
      }
      if ($this->normalizeNavigationLocationName((string) ($room['name'] ?? '')) !== $needle) {
        continue;
      }
      $room_id = (string) ($room['room_id'] ?? $room['id'] ?? '');
      return $room_id !== '' ? $this->hasVisitedRoomId($dungeon_data, $room_id) : !empty($room['chat']);
    }

    foreach ($dungeon_data['location_history'] ?? [] as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      if ($this->normalizeNavigationLocationName((string) ($entry['room_name'] ?? '')) === $needle) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Normalize location names for revisit matching.
   */

  protected function normalizeNavigationLocationName(string $value): string {
    $value = strtolower(trim($value));
    return preg_replace('/\s+/', ' ', $value) ?? '';
  }

  /**
   * Infer time of day from dungeon state or gameplay context.
   */

  protected function inferTimeOfDay(array $dungeon_data): string {
    // Check room gameplay_state for time hints.
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      $changes = $room['gameplay_state']['environmental_changes'] ?? [];
      foreach (array_reverse($changes) as $change) {
        $details = $change['details'] ?? [];
        if (!empty($details['time_of_day'])) {
          return $details['time_of_day'];
        }
      }
    }
    // Default to day.
    return 'day';
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
