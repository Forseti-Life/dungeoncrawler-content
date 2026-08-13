<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Coordinates top-level room GM turn orchestration stages.
 */
class GmTurnCoordinatorService {

  protected TurnIntentRouter $turnIntentRouter;
  protected GmResponseRoutingService $gmResponseRouting;
  protected GmGenerationPolicy $gmGenerationPolicy;

  public function __construct(
    ?TurnIntentRouter $turn_intent_router = NULL,
    ?GmResponseRoutingService $gm_response_routing = NULL,
    ?GmGenerationPolicy $gm_generation_policy = NULL
  ) {
    $this->turnIntentRouter = $turn_intent_router ?? new TurnIntentRouter();
    $this->gmResponseRouting = $gm_response_routing ?? new GmResponseRoutingService();
    $this->gmGenerationPolicy = $gm_generation_policy ?? new GmGenerationPolicy();
  }

  /**
   * Prepare intent classification and scene context for a GM turn.
   *
   * @return array{
   *   recent: array<int,array<string,mixed>>,
   *   latest_player_message: string,
   *   history_lines: array<int,string>,
   *   room_npcs: array<int,array<string,mixed>>,
   *   effective_direct_npc: ?array<string,mixed>,
   *   turn_intent: string,
   *   route_decision: array<string,mixed>,
   *   prompt_artifacts: array<string,mixed>,
   *   scene_parts: array<int,string>
   * }
   */
  public function prepareTurnContext(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $chat,
    bool $is_room_entry,
    GmTurnCoordinatorCallbacks $callbacks
  ): array {
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

    $stage_started_at = hrtime(true);
    $room_npcs = $callbacks->gatherRoomNpcsWithProfiles($campaign_id, $room_id, $dungeon_data);
    $directly_addressed_npc = $callbacks->resolveDirectlyAddressedNpc($room_npcs, $latest_player_message);
    $persisted_conversation_npc = $callbacks->resolveExplicitRoomConversationNpc($room_meta, $room_npcs);
    $active_conversation_npc = $persisted_conversation_npc ?? $callbacks->resolveActiveDirectConversationNpc($chat, $room_npcs);
    $effective_direct_npc = $directly_addressed_npc;
    $continued_conversation = FALSE;
    $implicit_single_npc_question = FALSE;
    $charisma_fallback_npc = FALSE;
    if ($effective_direct_npc === NULL && $active_conversation_npc !== NULL) {
      $normalized_player_message = $callbacks->normalizeNpcNameForMatch($latest_player_message);
      if ($callbacks->shouldContinueActiveRoomConversation($latest_player_message, $normalized_player_message, $active_conversation_npc)) {
        $effective_direct_npc = $active_conversation_npc;
        $continued_conversation = TRUE;
      }
    }
    if ($effective_direct_npc === NULL) {
      $single_room_npc = $this->resolveImplicitSingleRoomNpcQuestionTarget($latest_player_message, $room_npcs);
      if ($single_room_npc !== NULL) {
        $effective_direct_npc = $single_room_npc;
        $implicit_single_npc_question = TRUE;
      }
    }
    if ($effective_direct_npc === NULL) {
      $effective_direct_npc = $this->selectHighestCharismaNpc($room_npcs);
      $charisma_fallback_npc = $effective_direct_npc !== NULL;
    }
    $turn_intent = $callbacks->classifyRoomTurnIntent($latest_player_message, $room_npcs, $effective_direct_npc, $active_conversation_npc);
    $route_decision = $this->turnIntentRouter->routeFromIntent($turn_intent, $is_room_entry);
    $callbacks->recordDebugStage('gm.intent_classification', $stage_started_at, [
      'intent' => $turn_intent,
      'route_family' => $route_decision['route_family'] ?? 'llm_fallback',
      'resolution_outcome' => $route_decision['resolution_outcome'] ?? 'fallback_to_llm',
      'room_npc_count' => count($room_npcs),
      'direct_addressed' => $effective_direct_npc['entity_ref'] ?? NULL,
      'persisted_conversation_npc' => $persisted_conversation_npc['entity_ref'] ?? NULL,
      'active_conversation_npc' => $active_conversation_npc['entity_ref'] ?? NULL,
      'continued_conversation' => $continued_conversation,
      'implicit_single_npc_question' => $implicit_single_npc_question,
      'charisma_fallback_npc' => $charisma_fallback_npc,
    ]);

    $stage_started_at = hrtime(true);
    $prompt_artifacts = $callbacks->buildCachedRoomPromptArtifacts($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
    $scene_parts = $prompt_artifacts['scene_parts'] ?? [];
    $callbacks->recordDebugStage('gm.scene_context', $stage_started_at, [
      'scene_part_count' => count($scene_parts),
      'entity_count' => $prompt_artifacts['entity_count'] ?? 0,
      'entity_summary_count' => $prompt_artifacts['entity_summary_count'] ?? 0,
      'cache' => $prompt_artifacts['cache'] ?? 'unknown',
    ]);

    return [
      'recent' => $recent,
      'latest_player_message' => $latest_player_message,
      'history_lines' => $history_lines,
      'room_npcs' => $room_npcs,
      'effective_direct_npc' => $effective_direct_npc,
      'turn_intent' => $turn_intent,
      'route_decision' => is_array($route_decision) ? $route_decision : [],
      'prompt_artifacts' => is_array($prompt_artifacts) ? $prompt_artifacts : [],
      'scene_parts' => is_array($scene_parts) ? $scene_parts : [],
    ];
  }

  /**
   * Resolve one implicit direct-NPC target when exactly one room NPC is present.
   */
  protected function resolveImplicitSingleRoomNpcQuestionTarget(string $latest_player_message, array $room_npcs): ?array {
    if (count($room_npcs) !== 1) {
      return NULL;
    }
    if (!$this->isLikelyNpcQuestion($latest_player_message)) {
      return NULL;
    }

    $npc = $room_npcs[0] ?? NULL;
    return is_array($npc) ? $npc : NULL;
  }

  /**
   * Heuristic for chat questions that should implicitly address a sole room NPC.
   */
  protected function isLikelyNpcQuestion(string $message): bool {
    $trimmed = trim($message);
    if ($trimmed === '') {
      return FALSE;
    }

    if (str_ends_with($trimmed, '?')) {
      return TRUE;
    }

    return preg_match('/^(who|what|when|where|why|how|can|could|would|will|do|does|did|is|are|am|should|tell)\b/i', $trimmed) === 1;
  }

  /**
   * Resolve one NPC by highest Charisma, randomizing ties among leaders.
   */
  protected function selectHighestCharismaNpc(array $npcs): ?array {
    if ($npcs === []) {
      return NULL;
    }

    $top_score = NULL;
    $leaders = [];
    foreach ($npcs as $npc) {
      if (!is_array($npc)) {
        continue;
      }
      $charisma = \Drupal\dungeoncrawler_content\Service\NpcAbilityScoreResolver::resolveCharismaScore($npc);
      if ($top_score === NULL || $charisma > $top_score) {
        $top_score = $charisma;
        $leaders = [$npc];
        continue;
      }
      if ($charisma === $top_score) {
        $leaders[] = $npc;
      }
    }

    if ($leaders === []) {
      return NULL;
    }
    if (count($leaders) === 1) {
      return $leaders[0];
    }

    return $leaders[random_int(0, count($leaders) - 1)];
  }

  /**
   * Execute cache-aware LLM generation handoff sequencing for a GM turn.
   *
   * @param array<int,string> $history_lines
   * @param array<string,mixed> $prompt_artifacts
   *
   * @return array{
   *   checked_response: ?array<string,mixed>,
   *   response_source: string,
   *   gm_response_cache_key: ?string
   * }
   */
  public function handoffLlmGeneration(
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    string $turn_intent,
    string $latest_player_message,
    bool $is_room_entry,
    array $history_lines,
    array $prompt_artifacts,
    string $prompt,
    string $system_prompt,
    callable $normalize_text,
    callable $text_contains_any,
    callable $generator,
    callable $record_debug_stage
  ): array {
    $record_debug_stage_cb = \Closure::fromCallable($record_debug_stage);

    $gm_response_cache_key = NULL;
    $cache_stage_started_at = hrtime(true);
    $should_use_cache = $this->gmResponseRouting->shouldUseResponseCache(
      $turn_intent,
      $latest_player_message,
      $is_room_entry,
      $normalize_text,
      $text_contains_any
    );
    if ($should_use_cache) {
      $gm_response_cache_key = $this->gmResponseRouting->buildResponseCacheKey(
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
      $generator
    );
    $checked_response = $policy_result['checked_response'] ?? NULL;
    $response_source = (string) ($policy_result['response_source'] ?? 'unresolved');

    $record_debug_stage_cb('gm.response_cache', $cache_stage_started_at, [
      'cache' => (string) ($policy_result['cache_status'] ?? ($gm_response_cache_key ? 'miss' : 'bypass')),
      'turn_intent' => $turn_intent,
    ]);

    if (!empty($policy_result['generation_attempted'])) {
      $record_debug_stage_cb('gm.reality_checked_generation', $generation_stage_started_at, [
        'success' => $checked_response !== NULL,
      ]);
    }

    return [
      'checked_response' => is_array($checked_response) ? $checked_response : NULL,
      'response_source' => $response_source,
      'gm_response_cache_key' => $gm_response_cache_key,
    ];
  }

}
