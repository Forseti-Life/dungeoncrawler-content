<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\RoomChat\GmPromptArtifactCacheBuilder;
use Drupal\dungeoncrawler_content\Service\RoomChat\SessionContextCompactor;

trait RoomChatServiceQuestUpdateAndDiagnosticsTrait {

  protected function attachStorylineReferenceToQuestRow(int $campaign_id, string $quest_id, string $storyline_id, array $quest_link): void {
    if ($campaign_id <= 0 || $quest_id === '' || $storyline_id === '') {
      return;
    }

    $this->database->update('dc_campaign_quests')
      ->fields([
        'storyline_id' => $storyline_id,
        'storyline_chapter_id' => !empty($quest_link['chapter_id']) ? (string) $quest_link['chapter_id'] : NULL,
        'storyline_scene_id' => !empty($quest_link['scene_id']) ? (string) $quest_link['scene_id'] : NULL,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->execute();
  }

  /**
   * Build compact objective lines for quest update payloads.
   *
   * @return array<int, string>
   */

  protected function extractQuestObjectiveDescriptions(array $quest): array {
    $generated_objectives = $quest['generated_objectives'] ?? [];
    $phases = is_array($generated_objectives)
      ? $generated_objectives
      : json_decode((string) $generated_objectives, TRUE);
    if (!is_array($phases)) {
      return [];
    }

    foreach ($phases as $phase) {
      $objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      if ($objectives === []) {
        continue;
      }

      $lines = [];
      foreach (array_slice($objectives, 0, 3) as $objective) {
        $description = trim((string) ($objective['description'] ?? $objective['objective_id'] ?? ''));
        if ($description !== '') {
          $lines[] = $description;
        }
      }
      return $lines;
    }

    return [];
  }

  /**
   * Build one canonical quest update payload for the client runtime.
   */

  protected function buildQuestUpdatePayload(
    string $quest_id,
    string $quest_name,
    string $status,
    array $objectives,
    string $source,
    string $storyline_id = '',
    string $type = 'quest_started'
  ): array {
    $normalized_quest_id = $this->truncateContractString($quest_id, 160, 'unknown-quest');
    $normalized_storyline_id = $this->truncateContractString($storyline_id, 160);
    $normalized_source = in_array($source, self::QUEST_UPDATE_ALLOWED_SOURCES, TRUE) ? $source : 'available_quest';
    $normalized_type = in_array($type, ['quest_started', 'quest_surfaced'], TRUE) ? $type : 'quest_started';
    $normalized_objectives = [];
    foreach (array_slice($objectives, 0, 10) as $objective) {
      $normalized_objective = $this->truncateContractString((string) $objective, 1000);
      if ($normalized_objective !== '') {
        $normalized_objectives[] = $normalized_objective;
      }
    }

    $payload = [
      'schema_version' => self::QUEST_UPDATE_SCHEMA_VERSION,
      'type' => $normalized_type,
      'quest_id' => $normalized_quest_id,
      'quest_name' => $this->truncateContractString($quest_name, 255, $normalized_quest_id),
      'status' => $this->truncateContractString($status, 64, 'active'),
      'objectives' => $normalized_objectives,
      'source' => $normalized_source,
      'storyline_id' => $normalized_storyline_id !== '' ? $normalized_storyline_id : NULL,
    ];

    if (!$this->stateValidationService) {
      return $payload;
    }

    $validation = $this->stateValidationService->validateQuestUpdate($payload);
    if (!empty($validation['valid'])) {
      return $payload;
    }

    throw new \RuntimeException('Quest update contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Surface a dialogue-mentioned quest as a lead or offer instead of auto-starting it.
   */

  protected function surfaceMentionedQuestForDialogue(
    int $campaign_id,
    array $quest,
    string $dialogue_text,
    string $source,
    string $storyline_id = '',
    string $status_signal_text = ''
  ): ?array {
    $quest_id = trim((string) ($quest['quest_id'] ?? ''));
    if ($campaign_id <= 0 || $quest_id === '') {
      return NULL;
    }

    $current_status = strtolower(trim((string) ($quest['status'] ?? 'lead')));
    $status_signal_text = trim($status_signal_text) !== '' ? $status_signal_text : $dialogue_text;
    $promote_from_player_signal = $current_status === 'lead' && $this->looksLikeQuestActivationRequest($status_signal_text);
    $surfaced_status = $this->resolveSurfacedQuestStatus($current_status, $dialogue_text, $promote_from_player_signal);
    if ($surfaced_status === '') {
      return NULL;
    }

    if ($surfaced_status !== $current_status) {
      $this->database->update('dc_campaign_quests')
        ->fields(['status' => $surfaced_status])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->execute();
      $quest['status'] = $surfaced_status;
    }

    $objectives = $this->extractQuestObjectiveDescriptions($quest);
    $update_type = $surfaced_status === 'active' ? 'quest_started' : 'quest_surfaced';

    return $this->buildQuestUpdatePayload(
      $quest_id,
      (string) ($quest['quest_name'] ?? $quest_id),
      $surfaced_status,
      $objectives,
      $source,
      $storyline_id,
      $update_type
    );
  }

  /**
   * Resolve the surfaced quest state from NPC dialogue.
   */

  protected function resolveSurfacedQuestStatus(string $current_status, string $dialogue_text, bool $promote_from_player_signal = FALSE): string {
    $normalized = strtolower(trim($current_status));
    if (in_array($normalized, ['active', 'offered', 'ready_for_turn_in', 'completed', 'failed', 'expired', 'rejected'], TRUE)) {
      return $normalized;
    }

    if ($normalized === 'lead' && $promote_from_player_signal) {
      return 'offered';
    }

    if ($this->looksLikeExplicitQuestOffer($dialogue_text)) {
      return 'offered';
    }

    return 'lead';
  }

  /**
   * Detect when NPC dialogue contains a concrete quest offer instead of a rumor.
   */

  protected function looksLikeExplicitQuestOffer(string $text): bool {
    $normalized = strtolower($this->normalizeQuestLeadMatchText($text));
    if ($normalized === '') {
      return FALSE;
    }

    foreach ([
      'can you help',
      'i need you',
      'will you take',
      'will you help',
      'take this job',
      'take the job',
      'accept this quest',
      'accept the quest',
      'do you want to take',
      'are you willing to',
      'i can pay you',
      'bring me',
      'recover the',
    ] as $needle) {
      if (str_contains($normalized, $needle)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Detect a player request to actively work on a referenced lead quest now.
   */

  protected function looksLikeQuestActivationRequest(string $text): bool {
    $normalized = strtolower($this->normalizeQuestLeadMatchText($text));
    if ($normalized === '') {
      return FALSE;
    }

    $progress_cues = [
      'how do i',
      'how can i',
      'what do i do',
      'where do i',
      'where can i',
      'how do we',
      'how can we',
      'how do i complete',
      'how do i search',
      'how do i find',
      'how do i get',
    ];
    $has_progress_cue = FALSE;
    foreach ($progress_cues as $cue) {
      if (str_contains($normalized, $cue)) {
        $has_progress_cue = TRUE;
        break;
      }
    }
    if (!$has_progress_cue) {
      return FALSE;
    }

    foreach (['quest', 'objective', 'lead', 'mission', 'search', 'find', 'collect', 'recover', 'get'] as $anchor) {
      if (str_contains($normalized, $anchor)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Normalize freeform dialogue into a search-friendly quest lead string.
   */

  protected function normalizeQuestLeadMatchText(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['_', '-', ';', ':', ',', '.', '"', "'", '(', ')', '!', '?'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
  }

  /**
   * Resolve a reasonable party level for generated brokered storyline quests.
   */

  protected function loadCharacterQuestLevel(int $campaign_id, int $character_id): int {
    if ($campaign_id <= 0 || $character_id <= 0) {
      return 1;
    }

    $level = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['level'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $character_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return max(1, (int) $level);
  }

  /**
   * Resolve the best campaign NPC id for a brokered storyline contact.
   */

  protected function resolveCampaignNpcIdForBrokeredContact(int $campaign_id, array $contact): ?int {
    if ($campaign_id <= 0) {
      return NULL;
    }

    foreach (['quest_giver', 'broker'] as $field) {
      $entity = is_array($contact[$field] ?? NULL) ? $contact[$field] : [];
      $entity_id = trim((string) ($entity['entity_id'] ?? ''));
      if ($entity_id === '') {
        continue;
      }

      $npc_id = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $entity_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($npc_id !== FALSE) {
        return (int) $npc_id;
      }
    }

    return NULL;
  }

  /**
   * Extract incomplete objective descriptions for the current quest phase.
   */

  protected function extractIncompleteObjectivesForPhase(array $quest, int $phase): array {
    $objective_states = json_decode((string) ($quest['objective_states'] ?? '[]'), TRUE);
    $generated_objectives = json_decode((string) ($quest['generated_objectives'] ?? '[]'), TRUE);
    $phase_rows = is_array($objective_states) && $objective_states !== [] ? $objective_states : $generated_objectives;

    foreach ($phase_rows as $phase_row) {
      if ((int) ($phase_row['phase'] ?? 0) !== $phase) {
        continue;
      }

      return $this->collectIncompleteObjectiveDescriptions(is_array($phase_row['objectives'] ?? NULL) ? $phase_row['objectives'] : []);
    }

    return [];
  }

  /**
   * Collect incomplete objective descriptions from a nested objective tree.
   */

  protected function collectIncompleteObjectiveDescriptions(array $objectives): array {
    $descriptions = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }

      $children = is_array($objective['children'] ?? NULL) ? $objective['children'] : [];
      if ($children !== []) {
        $descriptions = array_merge($descriptions, $this->collectIncompleteObjectiveDescriptions($children));
        continue;
      }

      if (!empty($objective['completed'])) {
        continue;
      }

      $target_count = (int) ($objective['target_count'] ?? 0);
      $current = (int) ($objective['current'] ?? 0);
      if ($target_count > 0 && $current >= $target_count) {
        continue;
      }

      $description = trim((string) ($objective['description'] ?? $objective['objective_id'] ?? ''));
      if ($description === '') {
        continue;
      }

      if ($target_count > 0) {
        $description .= sprintf(' (%d/%d)', $current, $target_count);
      }
      $next_step = trim((string) ($objective['next_step'] ?? ''));
      $completion = trim((string) ($objective['completion_criteria']['description'] ?? ''));
      $target = trim((string) ($objective['target'] ?? $objective['npc_ref'] ?? ''));
      $item = trim((string) ($objective['item'] ?? ''));
      if ($next_step !== '') {
        $description .= ' [next: ' . $next_step . ']';
      }
      if ($completion !== '') {
        $description .= ' [complete when: ' . $completion . ']';
      }
      if ($target !== '') {
        $description .= ' [target: ' . $target . ']';
      }
      if ($item !== '') {
        $description .= ' [item: ' . $item . ']';
      }
      $descriptions[] = $description;
    }

    return $descriptions;
  }

  /**
   * Normalize a generated player-automation suggestion into chat-ready text.
   */

  protected function sanitizePlayerAutomationSuggestion(string $text): string {
    $text = trim($text);
    $text = preg_replace('/^```(?:text)?\s*/i', '', $text) ?? $text;
    $text = preg_replace('/\s*```$/', '', $text) ?? $text;
    $text = preg_replace('/^(?:message|reply|chat message)\s*:\s*/i', '', $text) ?? $text;
    $text = trim($text, " \t\n\r\0\x0B\"'");
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    if (strlen($text) > 240) {
      $text = rtrim(substr($text, 0, 237)) . '...';
    }
    return trim($text);
  }

  /**
   * Parse a JSON object from a model response body.
   */

  protected function parseJsonObjectFromText(string $text): ?array {
    $trimmed = trim($text);
    if ($trimmed === '') {
      return NULL;
    }

    $decoded = json_decode($trimmed, TRUE);
    if (is_array($decoded)) {
      return $decoded;
    }

    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $trimmed, $matches) === 1) {
      $decoded = json_decode($matches[1], TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }

    $start = strpos($trimmed, '{');
    $end = strrpos($trimmed, '}');
    if ($start !== FALSE && $end !== FALSE && $end > $start) {
      $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }

    return NULL;
  }

  /**
   * Normalize the fallback automation recommendation into a policy decision shape.
   */

  /**
   * Apply quest interact progress when the player has a substantive NPC exchange.
   */

  protected function applyConversationQuestTouchpoint(int $campaign_id, ?int $character_id, string $room_id, string $npc_ref, string $target_name = '', array $quest_touchpoint_hint = []): array {
    if ($campaign_id <= 0 || !$character_id || $character_id <= 0 || !$this->questTouchpointService) {
      return [
        'success' => TRUE,
        'decision' => 'NO_ACTION',
        'reason' => 'Quest touchpoint prerequisites were not met.',
      ];
    }

    $objective_type = strtolower(trim((string) ($quest_touchpoint_hint['objective_type'] ?? 'interact')));
    if ($objective_type === '') {
      $objective_type = 'interact';
    }
    $objective_id = trim((string) ($quest_touchpoint_hint['objective_id'] ?? ''));
    $hint_entity_ref = trim((string) ($quest_touchpoint_hint['entity_ref'] ?? ''));
    $resolved_npc_ref = trim($target_name);
    if ($resolved_npc_ref === '') {
      $resolved_npc_ref = trim($npc_ref);
    }
    $resolved_entity_ref = $hint_entity_ref !== '' ? $hint_entity_ref : trim($npc_ref);
    if ($resolved_entity_ref === '') {
      $resolved_entity_ref = $resolved_npc_ref;
    }
    if ($resolved_npc_ref === '' && $resolved_entity_ref === '' && $objective_id === '') {
      return [
        'success' => TRUE,
        'decision' => 'NO_ACTION',
        'reason' => 'Quest touchpoint was missing entity and objective identifiers.',
      ];
    }

    $matching_mode = strtolower(trim((string) ($quest_touchpoint_hint['matching_mode'] ?? '')));
    if ($matching_mode === '') {
      $matching_mode = $objective_id !== '' ? 'typed_receipt' : 'direct_npc_dialogue';
    }

    $result = $this->questTouchpointService->ingestEvent($campaign_id, [
      'character_id' => $character_id,
      'touchpoint' => [
        'objective_type' => $objective_type,
        'objective_id' => $objective_id,
        'npc_ref' => $resolved_npc_ref,
        'entity_ref' => $resolved_entity_ref,
        'room_id' => $room_id,
        'confidence' => 'high',
        'quantity' => 1,
        'matching_mode' => $matching_mode,
      ],
    ]);

    $this->logger->info('Conversation quest touchpoint result: campaign={campaign_id} character={character_id} room={room_id} npc_ref={npc_ref} target_name={target_name} objective_type={objective_type} objective_id={objective_id} matching_mode={matching_mode} decision={decision} quest_id={quest_id} matched_objective_id={matched_objective_id} reason={reason}', [
      'campaign_id' => $campaign_id,
      'character_id' => (int) $character_id,
      'room_id' => $room_id,
      'npc_ref' => trim($npc_ref),
      'target_name' => trim($target_name),
      'objective_type' => $objective_type,
      'objective_id' => $objective_id,
      'matching_mode' => $matching_mode,
      'decision' => (string) ($result['decision'] ?? 'unknown'),
      'quest_id' => (string) ($result['quest_id'] ?? ''),
      'matched_objective_id' => (string) ($result['objective_id'] ?? ''),
      'reason' => (string) ($result['reason'] ?? $result['error'] ?? ''),
    ]);

    return $result;
  }

  /**
   * Build a smaller structured session context block for chat prompts.
   */

  protected function buildCompactSessionContext(
    string $session_key,
    int $campaign_id,
    int $max_recent = 3,
    int $max_chars = 1200,
    int $max_summary_chars = 400,
    bool $include_recent_messages = TRUE
  ): string {
    $context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, $max_recent);
    if ($context === '') {
      return '';
    }
    return SessionContextCompactor::compact(
      $context,
      $max_recent,
      $max_chars,
      $max_summary_chars,
      $include_recent_messages
    );
  }

  /**
   * Build an NPC session observation string from recent room chat.
   */

  protected function buildRoomObservationFromChat(array $chat, int $limit = 8): string {
    return 'Overheard in the room — ' . $this->buildRoomConversationTranscript($chat, $limit);
  }

  /**
   * Start a per-request debug trace when timing telemetry is enabled.
   */

  protected function startDebugTrace(array $context): void {
    if (!$this->isChatTimingDebugEnabled()) {
      $this->activeDebugTrace = NULL;
      return;
    }

    $this->activeDebugTrace = [
      'trace_id' => uniqid('room_chat_', TRUE),
      'started_at' => date('c'),
      'request' => $context,
      'stages' => [],
      'llm_calls' => [],
    ];
  }

  /**
   * Record a named timing stage on the active debug trace.
   */

  protected function recordDebugStage(string $stage, int $started_at, array $meta = []): void {
    if ($this->activeDebugTrace === NULL) {
      return;
    }

    $this->activeDebugTrace['stages'][] = [
      'stage' => $stage,
      'duration_ms' => $this->elapsedMs($started_at),
      'meta' => $meta,
    ];
  }

  /**
   * Finalize and log the current debug trace.
   */

  protected function finalizeDebugTrace(int $started_at, array $summary = []): ?array {
    if ($this->activeDebugTrace === NULL) {
      return NULL;
    }

    $trace = $this->activeDebugTrace;
    $trace['total_ms'] = $this->elapsedMs($started_at);
    if (!empty($summary)) {
      $trace['summary'] = $summary;
    }

    $this->logger->info('Room chat debug trace @trace_id: @summary', [
      '@trace_id' => $trace['trace_id'],
      '@summary' => json_encode($this->buildTraceLogSummary($trace), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $this->activeDebugTrace = NULL;
    return $trace;
  }

  /**
   * Build a compact client-safe timing summary from the full debug trace.
   */

  protected function buildClientTimingSummary(array $trace): array {
    $stages = is_array($trace['stages'] ?? NULL) ? $trace['stages'] : [];
    $gm_stage = NULL;
    $cache_stage = NULL;
    $primary_flow_stage = NULL;
    foreach ($stages as $stage) {
      if (!is_array($stage)) {
        continue;
      }
      if ($gm_stage === NULL && in_array((string) ($stage['stage'] ?? ''), ['gm.total', 'generate_gm_reply'], TRUE)) {
        $gm_stage = $stage;
      }
      if ($cache_stage === NULL && (string) ($stage['stage'] ?? '') === 'gm.response_cache') {
        $cache_stage = $stage;
      }
      if ($primary_flow_stage === NULL && (string) ($stage['stage'] ?? '') === 'gm.primary_flow') {
        $primary_flow_stage = $stage;
      }
    }

    $cache_status = is_array($cache_stage['meta'] ?? NULL)
      ? (string) ($cache_stage['meta']['cache'] ?? '')
      : '';

    return [
      'trace_id' => (string) ($trace['trace_id'] ?? ''),
      'total_ms' => (int) round((float) ($trace['total_ms'] ?? 0)),
      'gm_ms' => $gm_stage !== NULL ? (int) round((float) ($gm_stage['duration_ms'] ?? 0)) : NULL,
      'cache_hit' => $cache_status === 'hit' ? TRUE : ($cache_status !== '' ? FALSE : NULL),
      'cache_status' => $cache_status !== '' ? $cache_status : NULL,
      'response_source' => is_array($primary_flow_stage['meta'] ?? NULL)
        ? ($primary_flow_stage['meta']['response_source'] ?? NULL)
        : NULL,
      'cluster_hints' => is_array($primary_flow_stage['meta']['cluster_hints'] ?? NULL)
        ? array_values($primary_flow_stage['meta']['cluster_hints'])
        : [],
      'stage_count' => count($stages),
    ];
  }

  /**
   * Build heuristic labels for the GM defect clusters we are tracking.
   */

  protected function buildGmDefectClusterHints(string $turn_intent, string $response_source): array {
    $clusters = [];

    if ($response_source === 'reality_checked_generation' && in_array($turn_intent, [
      'combat_engagement',
      'navigation_travel',
      'room_roster_query',
      'direct_npc_dialogue',
      'direct_npc_transaction',
      'quest_query',
      'ooc_meta',
    ], TRUE)) {
      $clusters[] = 'deterministic_coverage_gap';
    }

    if ($response_source === 'reality_checked_generation' && $turn_intent === 'gm_narration') {
      $clusters[] = 'prompt_fallback_path';
    }

    if ($response_source === 'unresolved') {
      $clusters[] = 'generation_failure';
    }

    return $clusters;
  }

  /**
   * Time and record a direct LLM call used by room chat.
   */

  protected function invokeTimedModelCall(
    string $prompt,
    string $provider,
    string $operation,
    array $context_data,
    array $options,
    array $debug_meta = []
  ): array {
    $started_at = hrtime(true);
    $provider_started_at = hrtime(true);
    try {
      $result = $this->aiApiService->invokeModelDirect($prompt, $provider, $operation, $context_data, $options);
      $provider_wait_ms = $this->elapsedMs($provider_started_at);
    }
    catch (\Throwable $e) {
      $provider_wait_ms = $this->elapsedMs($provider_started_at);
      $record_started_at = hrtime(true);
      if ($this->activeDebugTrace !== NULL) {
        $call = [
          'operation' => $operation,
          'provider' => $provider,
          'duration_ms' => $this->elapsedMs($started_at),
          'provider_wait_ms' => $provider_wait_ms,
          'context_data' => $context_data,
          'options' => $this->summarizeModelOptions($options),
          'prompt' => $this->summarizePromptText($prompt),
          'system_prompt' => $this->summarizePromptText((string) ($options['system_prompt'] ?? '')),
          'result' => [
            'success' => FALSE,
            'error' => $e->getMessage(),
          ],
        ];
        if (!empty($debug_meta)) {
          $call['meta'] = $debug_meta;
        }
        if ($this->shouldCapturePromptBodies()) {
          $call['prompt_body'] = $prompt;
          $call['system_prompt_body'] = (string) ($options['system_prompt'] ?? '');
        }
        $call['local_postprocess_ms'] = $this->elapsedMs($record_started_at);
        $this->activeDebugTrace['llm_calls'][] = $call;
      }
      throw $e;
    }

    if ($this->activeDebugTrace !== NULL) {
      $record_started_at = hrtime(true);
      $call = [
        'operation' => $operation,
        'provider' => $provider,
        'duration_ms' => $this->elapsedMs($started_at),
        'provider_wait_ms' => $provider_wait_ms,
        'context_data' => $context_data,
        'options' => $this->summarizeModelOptions($options),
        'prompt' => $this->summarizePromptText($prompt),
        'system_prompt' => $this->summarizePromptText((string) ($options['system_prompt'] ?? '')),
        'result' => [
          'success' => !empty($result['success']),
          'response_length' => strlen((string) ($result['response'] ?? '')),
        ],
      ];
      if (!empty($debug_meta)) {
        $call['meta'] = $debug_meta;
      }
      if ($this->shouldCapturePromptBodies()) {
        $call['prompt_body'] = $prompt;
        $call['system_prompt_body'] = (string) ($options['system_prompt'] ?? '');
      }
      $call['local_postprocess_ms'] = $this->elapsedMs($record_started_at);
      $this->activeDebugTrace['llm_calls'][] = $call;
    }

    return $result;
  }

  /**
   * Determine whether chat timing telemetry is enabled.
   */

  protected function isChatTimingDebugEnabled(): bool {
    return (bool) (\Drupal::config('dungeoncrawler_content.settings')->get('chat_timing_debug_enabled') ?? TRUE);
  }

  /**
   * Determine whether full prompt bodies should be captured.
   */

  protected function shouldCapturePromptBodies(): bool {
    return $this->shouldExposeDebugTrace()
      && (bool) (\Drupal::config('dungeoncrawler_content.settings')->get('chat_timing_debug_include_prompts') ?? FALSE);
  }

  /**
   * Only expose response debug traces to admins.
   */

  protected function shouldExposeDebugTrace(): bool {
    return $this->isChatTimingDebugEnabled()
      && $this->currentUser->hasPermission('administer dungeoncrawler content');
  }

  /**
   * Convert a started hrtime value to milliseconds.
   */

  protected function elapsedMs(int $started_at): float {
    return round((hrtime(true) - $started_at) / 1000000, 2);
  }

  /**
   * Summarize prompt text without always logging the full body.
   */

  protected function summarizePromptText(string $text): array {
    return [
      'length' => strlen($text),
      'line_count' => $text === '' ? 0 : substr_count($text, "\n") + 1,
      'preview' => substr($text, 0, 240),
    ];
  }

  /**
   * Summarize model options for trace readability.
   */

  protected function summarizeModelOptions(array $options): array {
    return [
      'max_tokens' => $options['max_tokens'] ?? NULL,
      'skip_cache' => !empty($options['skip_cache']),
      'system_prompt_length' => strlen((string) ($options['system_prompt'] ?? '')),
    ];
  }

  /**
   * Build a compact room inventory summary for timing logs.
   */

  protected function summarizeRoomInventory(array $room_inventory): array {
    $summary = [
      'top_level_keys' => array_keys($room_inventory),
      'encoded_bytes' => strlen(json_encode($room_inventory) ?: ''),
    ];

    foreach (['entities', 'items', 'npcs', 'hazards', 'loot', 'exits'] as $key) {
      if (isset($room_inventory[$key]) && is_array($room_inventory[$key])) {
        $summary[$key . '_count'] = count($room_inventory[$key]);
      }
    }

    return $summary;
  }

  /**
   * Build cached prompt artifacts for a room.
   */

  protected function buildCachedRoomPromptArtifacts(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $room_npcs = []
  ): array {
    return GmPromptArtifactCacheBuilder::build(
      $campaign_id,
      $room_id,
      $room_meta,
      $dungeon_data,
      $room_npcs,
      fn(string $value, int $max_chars, float $compress_ratio = 1.0): string => $this->truncateContextBlock($value, $max_chars, $compress_ratio),
      fn(array $npc): bool => $this->npcSupportsQuestOrLeadDialogue($npc),
      fn(string $haystack, array $needles): bool => $this->textContainsAny($haystack, $needles)
    );
  }

  /**
   * Decide whether the main GM response is cacheable.
   *
   * Response caching is the fallback optimization layer for low-variance turns
   * that still require LLM narration after deterministic handling is bypassed.
   */

  protected function shouldUseGmResponseCache(string $turn_intent, string $latest_player_message, bool $is_room_entry): bool {
    if ($is_room_entry || $turn_intent !== 'gm_narration') {
      return FALSE;
    }

    $normalized = $this->normalizeNpcNameForMatch($latest_player_message);
    if ($normalized === '' || strlen($normalized) > 180) {
      return FALSE;
    }

    if ($this->textContainsAny($normalized, ['attack', 'cast', 'roll', 'stealth', 'initiative', 'search', 'investigate', 'pick lock', 'unlock', 'use', 'skill check'])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Build a stable cache key for low-variance GM replies.
   */

  protected function buildGmResponseCacheKey(
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    string $turn_intent,
    array $history_lines,
    array $prompt_artifacts,
    string $prompt,
    string $system_prompt
  ): string {
    $cache_state = [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'character_id' => $character_id,
      'turn_intent' => $turn_intent,
      'history_lines' => array_slice($history_lines, -3),
      'prompt_artifacts' => $prompt_artifacts,
      'prompt_hash' => sha1($prompt),
      'system_prompt_hash' => sha1($system_prompt),
    ];

    return 'dungeoncrawler_content:gm_response:' . sha1(json_encode($cache_state));
  }

  /**
   * Strip large prompt bodies from the logged summary unless explicitly enabled.
   */

  protected function buildTraceLogSummary(array $trace): array {
    $summary = $trace;
    if (!$this->shouldCapturePromptBodies()) {
      foreach ($summary['llm_calls'] as &$call) {
        unset($call['prompt_body'], $call['system_prompt_body']);
      }
      unset($call);
    }
    return $summary;
  }

  /**
   * Resolve room UUID to a DB-friendly slug for dc_campaign_characters queries.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID from dungeon_data.
   * @param array $dungeon_data
   *   Full dungeon data for name lookups.
   *
   * @return string|null
   *   Room slug or NULL if not resolvable.
   */

  protected function resolveRoomSlugForQuery(int $campaign_id, string $room_id, array $dungeon_data): ?string {
    // Try exact match first.
    $exists = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->execute()
      ->fetchField();

    if ($exists) {
      return $room_id;
    }

    // Look up room name from dungeon_data and match by name.
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? '') === $room_id && !empty($room['name'])) {
        $slug = $this->database->select('dc_campaign_rooms', 'r')
          ->fields('r', ['room_id'])
          ->condition('campaign_id', $campaign_id)
          ->condition('name', $room['name'])
          ->execute()
          ->fetchField();
        if ($slug) {
          return $slug;
        }
      }
    }

    // Some runtime dungeon payloads use generated UUID room ids and in-world room
    // names (for example "The Gilded Tankard"), while dc_campaign_rooms stores the
    // canonical campaign slug/name pair (for example tavern_entrance / Tavern
    // Entrance). Bridge those via the containing hex-map region when available.
    foreach (($dungeon_data['hex_map']['regions'] ?? []) as $region) {
      $region_room_ids = $region['room_ids'] ?? [];
      if (!is_array($region_room_ids) || !in_array($room_id, $region_room_ids, TRUE)) {
        continue;
      }

      $region_name = (string) ($region['name'] ?? '');
      if ($region_name === '') {
        continue;
      }

      $slug = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('name', $region_name)
        ->execute()
        ->fetchField();
      if ($slug) {
        return $slug;
      }
    }

    // Cannot resolve — return NULL to avoid loading NPCs from the wrong room.
    // (Falling back to the first campaign room would bleed tavern NPCs like
    // Eldric into every unindexed room.)
    return NULL;
  }

  /**
   * Bridge an NPC interjection message into the hierarchical session system.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int|string $dungeon_id
   *   Dungeon record ID.
   * @param string $room_id
   *   Room UUID.
   * @param string $speaker
   *   NPC display name.
   * @param string $message
   *   The interjection text.
   * @param string $speaker_ref
   *   NPC entity reference.
   */

  protected function bridgeNpcInterjectionToSessionSystem(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    string $speaker,
    string $message,
    string $speaker_ref
  ): void {
    if (!$this->chatSessionManager) {
      return;
    }

    try {
      $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
      $room_session = $this->ensureCanonicalRoomSession($campaign_id, $dungeon_id, $room_id, $dungeon_snapshot['dungeon_data'] ?? []);
      if ($room_session === []) {
        return;
      }

      $this->chatSessionManager->postMessage(
        (int) $room_session['id'],
        $campaign_id,
        $speaker,
        'npc',
        $speaker_ref,
        $message,
        'dialogue',
        'public'
      );
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to bridge NPC interjection to session system: @err', [
        '@err' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Resolve and create the canonical room session for a room identifier.
   */

  protected function ensureCanonicalRoomSession(int $campaign_id, int|string $dungeon_id, string $room_id, array $dungeon_data): array {
    if ($this->chatSessionManager === NULL) {
      return [];
    }

    $canonical_room_id = $room_id;
    if ($dungeon_data !== []) {
      $canonical_room_id = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data) ?? $room_id;
    }

    return $this->chatSessionManager->ensureRoomSession($campaign_id, $dungeon_id, $canonical_room_id);
  }

  /**
   * Build the canonical encounter transcript prefix for a campaign speaker using
   * the latest persisted dungeon snapshot (including room turn-cycle state).
   */

  public function buildEncounterPrefixForCampaignSpeaker(int $campaign_id, string $speaker, ?string $room_id = NULL): ?string {
    try {
      $snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
      $dungeon_data = is_array($snapshot['dungeon_data'] ?? NULL) ? $snapshot['dungeon_data'] : [];
      return $this->encounterTranscriptPrefixService->buildForSpeaker(
        $dungeon_data,
        $speaker,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
      );
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }


}
