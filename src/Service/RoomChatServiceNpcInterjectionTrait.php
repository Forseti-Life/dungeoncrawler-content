<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\RoomChat\DeterministicNarrativeHelper;

trait RoomChatServiceNpcInterjectionTrait {

  protected function filterAmbientNpcInterjectionOrder(
    array $ordered_npcs,
    string $player_message,
    string $gm_narrative,
    array $dungeon_data = [],
    string $room_id = '',
    string $turn_seed = '',
    ?array &$conversation_state = NULL
  ): array {
    if ($ordered_npcs === []) {
      return [];
    }

    $combined_text = $this->normalizeNpcNameForMatch($player_message . ' ' . $gm_narrative);
    $effective_seed = $turn_seed !== ''
      ? $turn_seed
      : $this->normalizeNpcNameForMatch($room_id . '|' . $player_message . '|' . $gm_narrative);

    // Extract game state from dungeon data
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];

    // Ensure conversation attention state exists
    // Defensive initialization: attempt to initialize from dungeon_data if state not passed
    if ($conversation_state === NULL) {
      if (!empty($dungeon_data) && $room_id !== '') {
        $room_index = $this->getRoomIndexFromRoomId($dungeon_data, $room_id);
        if ($room_index !== NULL) {
          $this->attentionService->ensureConversationAttentionState($dungeon_data, $room_index);
          $normalized_room_index = (int) $room_index;
          if (isset($dungeon_data['rooms'][$normalized_room_index]['conversation_state'])
            && is_array($dungeon_data['rooms'][$normalized_room_index]['conversation_state'])) {
            $conversation_state = $dungeon_data['rooms'][$normalized_room_index]['conversation_state'];
          }
        }
      }
    }

    // If attention state still unavailable, use legacy fallback gate
    // This allows the system to gracefully degrade if room data is unavailable
    if ($conversation_state === NULL) {
      // Fall through to legacy ambient chatter gate (see line 4499+)
      $use_legacy_gate = TRUE;
    }
    else {
      $use_legacy_gate = FALSE;
    }

    // Detect topic and update conversation state only if state is available
    if ($use_legacy_gate === FALSE && $player_message !== '') {
      $topic_data = $this->attentionService->detectTopic($player_message);
      if ($topic_data['topic'] !== NULL) {
        $this->attentionService->updateTopic($conversation_state, $topic_data['topic'], 0);
      }
    }

    $filtered = [];
    foreach ($ordered_npcs as $npc) {
      // Direct reference always speaks (unchanged behavior)
      if ($this->isNpcDirectlyReferencedForAmbientInterjection($npc, $combined_text)) {
        $filtered[] = $npc;
        continue;
      }

      // Use attention score system if available
      if ($use_legacy_gate === FALSE && $conversation_state !== NULL) {
        // Extract player speaker ID from last speaker in conversation state
        $player_speaker_id = $conversation_state['last_speaker'] ?? '';
        $score_result = $this->attentionService->calculateAttentionScore(
          $npc,
          $conversation_state,
          $player_message,
          $game_state,
          $player_speaker_id
        );

        if ($score_result['qualified']) {
          $filtered[] = $npc + [
            'attention_score' => $score_result['total_score'],
            'attention_components' => $score_result['component_scores'],
          ];
        }
        continue;
      }

      // Fallback to legacy roll-based gate
      $threshold = $this->resolveAmbientNpcInterjectionPercent($npc);
      $roll = $this->computeAmbientNpcInterjectionRoll($npc, $effective_seed);
      if ($roll < $threshold) {
        $filtered[] = $npc + [
          'ambient_chatter_threshold' => $threshold,
          'ambient_chatter_roll' => $roll,
        ];
      }
    }

    // Decay fatigue for NPCs that didn't speak this turn
    if ($conversation_state !== NULL) {
      $this->attentionService->decayFatiguePenalties($conversation_state);
    }

    return $filtered;
  }

  /**
   * Gets room index from room_id by searching dungeon data.
   *
   * @param array $dungeon_data
   *   The dungeon data structure containing rooms.
   * @param string $room_id
   *   The room identifier to search for.
   *
   * @return int|null
   *   The room array index if found, NULL otherwise.
   */

  protected function getRoomIndexFromRoomId(array $dungeon_data, string $room_id): ?int {
    if ($room_id === '' || empty($dungeon_data['rooms'])) {
      return NULL;
    }

    foreach ($dungeon_data['rooms'] as $index => $room) {
      if ((string) ($room['room_id'] ?? '') === $room_id) {
        return $index;
      }
    }

    return NULL;
  }

  /**
   * Determine whether the current exchange is explicitly about this NPC.
   */

  protected function isNpcDirectlyReferencedForAmbientInterjection(array $npc, string $combined_text): bool {
    if ($combined_text === '') {
      return FALSE;
    }

    $candidates = array_values(array_unique(array_filter([
      (string) ($npc['profile']['display_name'] ?? ''),
      (string) ($npc['entity_ref'] ?? ''),
      (string) ($npc['entity']['name'] ?? ''),
      (string) ($npc['entity']['state']['metadata']['display_name'] ?? ''),
    ], static fn(string $value): bool => trim($value) !== '')));

    foreach ($candidates as $candidate) {
      $normalized_candidate = $this->normalizeNpcNameForMatch($candidate);
      if ($normalized_candidate !== '' && $this->textContainsNpcReferenceCandidate($combined_text, $normalized_candidate)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Match a likely noun-style NPC reference while avoiding common verb collisions.
   */

  protected function textContainsNpcReferenceCandidate(string $combined_text, string $normalized_candidate): bool {
    if ($combined_text === '' || $normalized_candidate === '') {
      return FALSE;
    }

    if (!preg_match('/\b' . preg_quote($normalized_candidate, '/') . '\b/u', $combined_text)) {
      return FALSE;
    }

    $candidate_tokens = preg_split('/\s+/', $normalized_candidate) ?: [];
    if (count($candidate_tokens) !== 1) {
      return TRUE;
    }

    $text_tokens = preg_split('/\s+/', trim($combined_text)) ?: [];
    if ($text_tokens === []) {
      return FALSE;
    }

    $verbish_previous_tokens = [
      'i', 'we', 'they', 'to', 'will', 'would', 'can', 'could', 'should',
      'must', 'may', 'might', 'll',
    ];
    $candidate_token = $candidate_tokens[0];
    foreach ($text_tokens as $index => $token) {
      if ($token !== $candidate_token) {
        continue;
      }

      $previous = strtolower((string) ($text_tokens[$index - 1] ?? ''));
      if (!$this->isSingleTokenNpcVerbCollision($candidate_token, $previous)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Detect obvious verb-phrase collisions for single-word NPC names.
   */

  protected function isSingleTokenNpcVerbCollision(string $candidate_token, string $previous_token): bool {
    if ($candidate_token === '') {
      return FALSE;
    }

    static $verbish_previous_tokens = [
      'i', 'we', 'they', 'to', 'will', 'would', 'can', 'could', 'should',
      'must', 'may', 'might', 'll',
    ];

    return in_array(strtolower($previous_token), $verbish_previous_tokens, TRUE);
  }

  /**
   * Resolve ambient chatter chance as 4x Charisma (capped 0-100).
   */

  protected function resolveAmbientNpcInterjectionPercent(array $npc): int {
    $charisma = $this->resolveNpcCharismaScore($npc);
    return max(
      0,
      min(
        self::AMBIENT_INTERJECTION_PERCENT_CAP,
        $charisma * self::AMBIENT_INTERJECTION_CHARISMA_MULTIPLIER
      )
    );
  }

  /**
   * Resolve one NPC's Charisma score for ambient chatter gating.
   *
   * If charisma is unavailable across all known actor/profile shapes, defaults
   * to a baseline score of 10.
   */

  protected function resolveNpcCharismaScore(array $npc): int {
    return NpcAbilityScoreResolver::resolveCharismaScore($npc);
  }

  /**
   * Deterministic 0-99 roll for ambient chatter gating.
   */

  protected function computeAmbientNpcInterjectionRoll(array $npc, string $turn_seed): int {
    $entity_ref = trim((string) ($npc['entity_ref'] ?? 'npc'));
    $hash = hash('sha256', $turn_seed . '|' . $entity_ref);
    return hexdec(substr($hash, 0, 8)) % 100;
  }

  /**
   * Match one initiative participant back to a gathered room NPC.
   */

  protected function matchRoomNpcFromInitiativeParticipant(array $room_npcs, array $participant, string $room_id = ''): ?array {
    $participant_room_id = (string) ($participant['room_id'] ?? $participant['placement']['room_id'] ?? '');
    if ($room_id !== '' && $participant_room_id !== '' && $participant_room_id !== $room_id) {
      return NULL;
    }

    $candidate_ids = array_filter([
      (string) ($participant['entity_ref'] ?? ''),
      (string) ($participant['entity_id'] ?? ''),
      (string) ($participant['participant_ref'] ?? ''),
      (string) ($participant['id'] ?? ''),
    ]);
    $candidate_names = array_filter([
      (string) ($participant['display_name'] ?? ''),
      (string) ($participant['name'] ?? ''),
    ]);

    foreach ($room_npcs as $npc) {
      $entity_ref = (string) ($npc['entity_ref'] ?? '');
      $display_name = (string) ($npc['profile']['display_name'] ?? '');
      if ($entity_ref !== '' && in_array($entity_ref, $candidate_ids, TRUE)) {
        return $npc;
      }
      if ($display_name !== '' && in_array($display_name, $candidate_names, TRUE)) {
        return $npc;
      }
    }

    return NULL;
  }

  /**
   * Build an internal system log message for the current turn order.
   */

  protected function buildRoomTurnOrderLogMessage(array $turn_sequence, bool $gm_addressed): string {
    $speaker_names = array_map(static function (array $turn): string {
      $display_name = (string) ($turn['display_name'] ?? 'Unknown');
      $initiative_total = $turn['initiative_total'] ?? NULL;
      if ($initiative_total !== NULL && $turn['role'] === 'npc') {
        return sprintf('%s %d', $display_name, (int) $initiative_total);
      }
      return $display_name;
    }, $turn_sequence);

    $summary = 'Turn order: ' . implode(' -> ', $speaker_names) . '.';
    if ($gm_addressed) {
      return $summary . ' GM was addressed directly, so initiative NPC turns are skipped this round.';
    }
    return $summary;
  }

  /**
   * Build an internal system log message for the active speaker.
   */

  protected function buildRoomCurrentTurnLogMessage(string $speaker): string {
    $normalized_speaker = trim($speaker);
    return 'Current turn: ' . ($normalized_speaker !== '' ? $normalized_speaker : 'Unknown') . '.';
  }

  /**
   * Append an internal turn-log system message to room chat.
   */

  protected function appendRoomSystemMessage(array &$dungeon_data, int|string $room_index, string $message, array $extra = [], ?string $encounter_prefix = NULL, bool $persist_to_chat = TRUE): array {
    $extra['internal_log'] = FALSE;
    return $this->appendInternalRoomLogMessage($dungeon_data, $room_index, $message, $extra, $encounter_prefix, $persist_to_chat);
  }


  protected function appendInternalRoomLogMessage(array &$dungeon_data, int|string $room_index, string $message, array $extra = [], ?string $encounter_prefix = NULL, bool $persist_to_chat = TRUE): array {
    $internal_log = array_key_exists('internal_log', $extra) ? (bool) $extra['internal_log'] : TRUE;

    if ($encounter_prefix === NULL) {
      $turn_index_human = array_key_exists('turn_index', $extra) && is_numeric($extra['turn_index']) ? (int) $extra['turn_index'] : NULL;
      $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];

      if (($game_state['phase'] ?? '') === 'encounter' && $turn_index_human !== NULL) {
        $round_raw = $game_state['round'] ?? 1;
        $round_display = is_numeric($round_raw) ? max(0, ((int) $round_raw) - 1) : '?';
        $encounter_prefix = $this->encounterTranscriptPrefixService->format($round_display, $turn_index_human, 'System');
      }
      else {
        $encounter_prefix = $this->encounterTranscriptPrefixService->buildForSpeaker(
          $dungeon_data,
          'System',
          fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
        );
      }
    }

    $system_message = [
      'speaker' => 'System',
      'message' => $this->encounterTranscriptPrefixService->prefixChatText($message, $encounter_prefix),
      'type' => 'system',
      'channel' => 'room',
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
      'internal_log' => $internal_log,
    ];
    foreach (['turn_role', 'turn_name', 'turn_index', 'initiative_total', 'initiative_roll', 'initiative_modifier', 'turn_prompt'] as $field) {
      if (array_key_exists($field, $extra)) {
        $system_message[$field] = $extra[$field];
      }
    }
    if ($persist_to_chat) {
      $dungeon_data['rooms'][$room_index]['chat'][] = $system_message;
      $system_message['sequence_index'] = count($dungeon_data['rooms'][$room_index]['chat']);
      $dungeon_data['rooms'][$room_index]['chat'][array_key_last($dungeon_data['rooms'][$room_index]['chat'])] = $system_message;

      $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
      if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
        $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
          $dungeon_data['rooms'][$room_index]['chat'],
          $chat_count - self::MAX_MESSAGES_PER_ROOM
        );
      }
    }

    return $system_message;
  }

  /**
   * Attach initiative metadata to a gathered room NPC.
   */

  protected function decorateRoomNpcWithInitiative(array $npc, array $participant = [], string $turn_seed = ''): array {
    $entity_ref = (string) ($npc['entity_ref'] ?? '');
    $initiative_total = $participant['initiative'] ?? $participant['initiative_total'] ?? NULL;
    $initiative_roll = $participant['initiative_roll'] ?? $participant['roll'] ?? NULL;
    $initiative_modifier = $this->resolveRoomNpcInitiativeModifier($npc);

    if (!is_numeric($initiative_total)) {
      $initiative_roll = $this->buildDeterministicRoomInitiativeRoll($turn_seed, $entity_ref);
      $initiative_total = $initiative_roll + $initiative_modifier;
    } elseif (!is_numeric($initiative_roll)) {
      $initiative_roll = (int) $initiative_total - $initiative_modifier;
    }

    $npc['initiative_total'] = (int) $initiative_total;
    $npc['initiative_roll'] = (int) $initiative_roll;
    $npc['initiative_modifier'] = (int) $initiative_modifier;
    return $npc;
  }

  /**
   * Build the explicit room turn sequence.
   */

  protected function buildRoomTurnSequence(array $ordered_npcs): array {
    $sequence = [
      [
        'actor_key' => 'narrator',
        'actor_ref' => NULL,
        'display_name' => 'Narrator',
        'role' => 'narrator',
        'turn_index' => 1,
        'initiative_total' => NULL,
        'initiative_roll' => NULL,
        'initiative_modifier' => NULL,
        'spoke' => TRUE,
      ],
    ];

    foreach (array_values($ordered_npcs) as $index => $npc) {
      $sequence[] = [
        'actor_key' => (string) ($npc['entity_ref'] ?? ('npc-' . $index)),
        'actor_ref' => (string) ($npc['entity_ref'] ?? ''),
        'display_name' => (string) ($npc['profile']['display_name'] ?? $npc['entity_ref'] ?? 'Unknown'),
        'role' => 'npc',
        'turn_index' => $index + 2,
        'initiative_total' => isset($npc['initiative_total']) ? (int) $npc['initiative_total'] : NULL,
        'initiative_roll' => isset($npc['initiative_roll']) ? (int) $npc['initiative_roll'] : NULL,
        'initiative_modifier' => isset($npc['initiative_modifier']) ? (int) $npc['initiative_modifier'] : NULL,
        'spoke' => FALSE,
      ];
    }

    return $sequence;
  }

  /**
   * Build a deterministic d20 initiative roll for room-turn ordering.
   */

  protected function buildDeterministicRoomInitiativeRoll(string $turn_seed, string $actor_key): int {
    $seed = ($turn_seed !== '' ? $turn_seed : 'room-turn') . '|' . $actor_key;
    return (abs(crc32($seed)) % 20) + 1;
  }

  /**
   * Resolve an NPC initiative modifier from room entity state.
   */

  protected function resolveRoomNpcInitiativeModifier(array $npc): int {
    $entity = is_array($npc['entity'] ?? NULL) ? $npc['entity'] : [];
    $candidates = [
      $entity['state']['metadata']['stats']['perception'] ?? NULL,
      $entity['state']['perception'] ?? NULL,
      $entity['perception'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate)) {
        return (int) $candidate;
      }
    }
    return 0;
  }

  /**
   * Persist the current dungeon chat state.
   */

  protected function persistDungeonChatState(int $campaign_id, int|string $dungeon_id, array $dungeon_data): void {
    $updated = $this->persistRoomChatSnapshotState($campaign_id, (string) $dungeon_id, $dungeon_data);
    if (!$updated) {
      throw new \RuntimeException(sprintf(
        'Room chat interjection persistence contract violation: expected shared state lane update for campaign %d dungeon %s.',
        $campaign_id,
        (string) $dungeon_id
      ));
    }
  }

  /**
   * Determine whether the structured room turn log table is available.
   */

  protected function isRoomTurnLogStoreAvailable(): bool {
    if ($this->roomTurnLogStoreAvailable !== NULL) {
      return $this->roomTurnLogStoreAvailable;
    }

    try {
      $this->roomTurnLogStoreAvailable = $this->database->schema()->tableExists('dc_room_turn_logs');
    }
    catch (\Throwable $e) {
      $this->roomTurnLogStoreAvailable = FALSE;
    }

    return $this->roomTurnLogStoreAvailable;
  }

  /**
   * Persist one structured room turn log row for troubleshooting.
   */

  protected function persistStructuredRoomTurnLog(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    string $turn_key,
    int $sequence_index,
    string $event_type,
    ?string $actor_ref = NULL,
    ?string $actor_name = NULL,
    array $payload = []
  ): void {
    if (!$this->isRoomTurnLogStoreAvailable()) {
      return;
    }

    try {
      $this->database->insert('dc_room_turn_logs')
        ->fields([
          'campaign_id' => $campaign_id,
          'dungeon_id' => is_numeric((string) $dungeon_id) ? (int) $dungeon_id : NULL,
          'room_id' => $room_id,
          'channel' => 'room',
          'turn_key' => $turn_key,
          'sequence_index' => $sequence_index,
          'event_type' => $event_type,
          'actor_ref' => $actor_ref,
          'actor_name' => $actor_name,
          'message_preview' => isset($payload['message_preview']) ? substr((string) $payload['message_preview'], 0, 500) : NULL,
          'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}',
          'created' => time(),
        ])
        ->execute();
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to persist room turn log for room @room: @err', [
        '@room' => $room_id,
        '@err' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Decide whether one specific NPC takes a turn, using current round context.
   */

  protected function shouldNpcTakeTurnThisRound(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    array $dungeon_data,
    array $npc,
    ?array $active_character_data,
    string $player_message,
    string $gm_narrative
  ): bool {
    if (!$this->aiApiService) {
      return FALSE;
    }

    $profile = is_array($npc['profile'] ?? NULL) ? $npc['profile'] : [];
    $resolved_attitude = $this->resolveActorDispositionAttitude(
      $campaign_id,
      (string) ($npc['entity_ref'] ?? ''),
      [
        'profile' => $profile,
        'entity' => is_array($npc['entity'] ?? NULL) ? $npc['entity'] : [],
        'attitude' => $profile['attitude'] ?? NULL,
      ]
    );

    $cache_key = 'dungeoncrawler_content:npc_turn:' . sha1(json_encode([
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'npc' => $npc['entity_ref'] ?? '',
      'player' => $player_message,
      'gm' => $gm_narrative,
      'transcript' => $this->buildRoomConversationTranscript($dungeon_data['rooms'][$room_index]['chat'] ?? [], 4),
      'attitude' => $resolved_attitude,
    ]));
    $cache_started_at = hrtime(true);
    $cache = \Drupal::cache('default')->get($cache_key);
    if ($cache && isset($cache->data['speak'])) {
      $this->recordDebugStage('npc.turn_cache_hit', $cache_started_at, [
        'npc' => $npc['entity_ref'] ?? '',
      ]);
      return (bool) $cache->data['speak'];
    }

    $desc = (string) ($profile['display_name'] ?? $npc['entity_ref']);
    $desc .= " — Attitude: " . $resolved_attitude;
    if (!empty($profile['personality_traits'])) {
      $desc .= ", Personality: {$profile['personality_traits']}";
    }
    if (!empty($profile['motivations'])) {
      $desc .= ", Motivations: {$profile['motivations']}";
    }
    $monologue = $profile['inner_monologue'] ?? [];
    if (!empty($monologue)) {
      $recent_thought = end($monologue);
      $thought_text = $recent_thought['thought'] ?? $recent_thought['text'] ?? '';
      if ($thought_text !== '') {
        $desc .= ", Recent thought: \"{$thought_text}\"";
      }
    }

    $session_key = $this->sessionManager->npcSessionKey($campaign_id, $npc['entity_ref']);
    $session_context = $this->buildCompactSessionContext($session_key, $campaign_id, 2, 650, 260);

    $user_prompt = "NPC considering whether to speak this turn:\n{$desc}";
    if ($session_context) {
      $user_prompt .= "\nPrior conversations: {$session_context}";
    }
    $user_prompt .= "\n\nCurrent room conversation:\n" . $this->buildRoomConversationTranscript($dungeon_data['rooms'][$room_index]['chat'] ?? [], 4);
    $user_prompt .= "\n\nLatest exchange:\nPlayer: {$player_message}\nGame Master: {$gm_narrative}";

    if ($active_character_data) {
      $pc_name = $active_character_data['name'] ?? 'the player';
      $pc_style = $active_character_data['roleplay_style'] ?? 'balanced';
      $user_prompt .= "\nActive PC: {$pc_name} ({$pc_style}).";
    }

    $user_prompt .= "\n\nShould this NPC take their turn and speak now? Reply with exactly SPEAK or PASS.";

    $system_prompt = <<<PROMPT
You are evaluating whether a single NPC chooses to speak during a room conversation.

Rules:
- Consider the full conversation so far, including prior NPC interjections from this same round.
- NPCs speak when directly relevant, personally motivated, emotionally provoked, or uniquely informed.
- NPCs do not need to speak every round. PASS is correct when they would reasonably stay quiet.
- Friendly/helpful NPCs are more likely to add useful context. Hostile/unfriendly NPCs may challenge, mock, or provoke.
- Indifferent NPCs stay quiet unless the topic clearly concerns them.

Output ONLY one word: SPEAK or PASS.
PROMPT;

    try {
      $result = $this->invokeTimedModelCall(
        $user_prompt,
        'dungeoncrawler_content',
        'npc_interjection_eval_single',
        ['campaign_id' => $campaign_id, 'room_id' => $room_id, 'npc' => $npc['entity_ref']],
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 20,
          'skip_cache' => TRUE,
        ],
        [
          'npc_entity' => $npc['entity_ref'],
          'session_context_length' => strlen($session_context),
          'prompt_character_count' => strlen($user_prompt),
        ]
      );
    }
    catch (\Throwable $e) {
      $this->logger->warning('NPC interjection single-eval failed: @err', ['@err' => $e->getMessage()]);
      return FALSE;
    }

    $should_speak = strtoupper(trim((string) ($result['response'] ?? ''))) === 'SPEAK';
    \Drupal::cache('default')->set($cache_key, ['speak' => $should_speak], time() + 300, [
      'dungeoncrawler_content:campaign:' . $campaign_id,
    ]);
    return $should_speak;
  }

  /**
   * Build and persist a room NPC interjection message.
   */

  protected function buildNpcInterjectionMessage(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    int|string $dungeon_id,
    array &$dungeon_data,
    string $player_message,
    string $gm_narrative,
    array $room_npcs,
    string $speaker_ref,
    string $speaker_name,
    bool $feed_room_sessions = TRUE,
    ?string $encounter_prefix = NULL,
    ?string $consumption_key_override = NULL
  ): array {
    $interjection_stage_started_at = hrtime(true);
    $normalized_consumption_key = trim((string) ($consumption_key_override ?? ''));
    if ($normalized_consumption_key !== '' && $this->hasConsumedNpcResponse($dungeon_data, $room_index, $normalized_consumption_key)) {
      $this->recordDebugStage('npc.interjection_skipped_consumed', $interjection_stage_started_at, [
        'npc_entity' => $speaker_ref,
        'mode' => 'consumption_key_override',
      ]);
      return [];
    }

    $stage_started_at = hrtime(true);
    $dialogue_payload = $this->generateNpcRoomDialogue(
      $campaign_id, $room_id, $room_index, $dungeon_data,
      $speaker_ref, $speaker_name, $player_message, $gm_narrative
    );
    $this->recordDebugStage('npc.interjection_generate_dialogue', $stage_started_at, [
      'npc_entity' => $speaker_ref,
    ]);

    if (empty($dialogue_payload['text'])) {
      $stage_started_at = hrtime(true);
      $this->feedRoomChatToNpcSessions($campaign_id, $room_npcs, $player_message, $gm_narrative);
      $this->recordDebugStage('npc.interjection_feed_sessions_empty_dialogue', $stage_started_at, [
        'npc_entity' => $speaker_ref,
        'room_npc_count' => count($room_npcs),
      ]);
      $this->recordDebugStage('npc.interjection_total', $interjection_stage_started_at, [
        'npc_entity' => $speaker_ref,
        'spoke' => FALSE,
        'reason' => 'empty_dialogue',
      ]);
      return [];
    }

    $npc_dialogue = (string) $dialogue_payload['text'];
    if ($normalized_consumption_key !== '') {
      $this->markConsumedNpcResponse($dungeon_data, $room_index, $normalized_consumption_key);
    }
    else {
      $stage_started_at = hrtime(true);
      $consumed = $this->consumeNpcResponseOnce(
        $dungeon_data,
        $room_index,
        $room_id,
        $speaker_ref,
        $player_message,
        $npc_dialogue
      );
      $this->recordDebugStage('npc.interjection_consume_guard', $stage_started_at, [
        'npc_entity' => $speaker_ref,
        'consumed' => $consumed,
      ]);
      if (!$consumed) {
        $this->recordDebugStage('npc.interjection_total', $interjection_stage_started_at, [
          'npc_entity' => $speaker_ref,
          'spoke' => FALSE,
          'reason' => 'duplicate_consumption_guard',
        ]);
        return [];
      }
    }

    // Build the NPC chat message.
    if ($encounter_prefix === NULL) {
      $stage_started_at = hrtime(true);
      $encounter_prefix = $this->encounterTranscriptPrefixService->buildForSpeaker(
        $dungeon_data,
        $speaker_name,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
      );
      $this->recordDebugStage('npc.interjection_build_encounter_prefix', $stage_started_at, [
        'npc_entity' => $speaker_ref,
      ]);
    }
    $stage_started_at = hrtime(true);
    $npc_message = $this->buildCharacterDialogueChatMessage($dialogue_payload, NULL, $encounter_prefix);
    $this->recordDebugStage('npc.interjection_build_chat_message', $stage_started_at, [
      'npc_entity' => $speaker_ref,
      'message_length' => strlen((string) ($npc_message['message'] ?? '')),
    ]);

    // Persist the NPC interjection to dungeon_data chat.
    $stage_started_at = hrtime(true);
    $dungeon_data['rooms'][$room_index]['chat'][] = $npc_message;
    $npc_message['sequence_index'] = count($dungeon_data['rooms'][$room_index]['chat']);
    $dungeon_data['rooms'][$room_index]['chat'][array_key_last($dungeon_data['rooms'][$room_index]['chat'])] = $npc_message;

    // Enforce message limit.
    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
        $chat_count - self::MAX_MESSAGES_PER_ROOM
      );
    }
    $this->recordDebugStage('npc.interjection_update_chat_buffer', $stage_started_at, [
      'npc_entity' => $speaker_ref,
      'chat_count' => $chat_count,
    ]);

    $stage_started_at = hrtime(true);
    $this->persistDungeonChatState($campaign_id, $dungeon_id, $dungeon_data);
    $this->recordDebugStage('npc.interjection_persist_dungeon_chat_state', $stage_started_at, [
      'npc_entity' => $speaker_ref,
    ]);

    // Record the interjection in the NPC's own AI session.
    $stage_started_at = hrtime(true);
    $session_key = $this->sessionManager->npcSessionKey($campaign_id, $speaker_ref);
    $this->recordDebugStage('npc.interjection_resolve_session_key', $stage_started_at, [
      'npc_entity' => $speaker_ref,
    ]);
    $stage_started_at = hrtime(true);
    $context_for_npc = $this->buildRoomObservationFromChat(array_slice($dungeon_data['rooms'][$room_index]['chat'] ?? [], 0, -1));
    $this->recordDebugStage('npc.interjection_build_room_observation', $stage_started_at, [
      'npc_entity' => $speaker_ref,
      'observation_length' => strlen($context_for_npc),
    ]);
    $stage_started_at = hrtime(true);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $context_for_npc);
    $this->recordDebugStage('npc.interjection_append_session_user', $stage_started_at, [
      'npc_entity' => $speaker_ref,
    ]);
    $stage_started_at = hrtime(true);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $npc_dialogue);
    $this->recordDebugStage('npc.interjection_append_session_assistant', $stage_started_at, [
      'npc_entity' => $speaker_ref,
      'dialogue_length' => strlen($npc_dialogue),
    ]);

    // Apply deterministic disposition mutation for the speaking NPC.
    $stage_started_at = hrtime(true);
    $event_description = "I spoke up in the room chat: \"{$npc_dialogue}\"";
    if (\Drupal::hasService('dungeoncrawler_content.actor_disposition_service')) {
      $service = \Drupal::service('dungeoncrawler_content.actor_disposition_service');
      if ($service instanceof ActorDispositionService) {
        $service->applyDispositionEvent(
          $campaign_id,
          (string) $speaker_ref,
          'conversation',
          $event_description,
          [
            'relationship_type' => 'conversation',
            'relationship_status' => 'known',
            'idempotency_key' => sha1(json_encode([
              'room_interjection' => TRUE,
              'campaign_id' => $campaign_id,
              'room_id' => (string) $room_id,
              'speaker_ref' => (string) $speaker_ref,
              'player_message' => $player_message,
              'npc_dialogue' => $npc_dialogue,
              'trace_id' => (string) ($this->activeDebugTrace['trace_id'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'trigger' => 'room_interjection',
            'player_said' => substr($player_message, 0, 200),
            'trace_id' => (string) ($this->activeDebugTrace['trace_id'] ?? ''),
          ]
        );
      }
      else {
        $this->psychologyService->recordInnerMonologue(
          $campaign_id,
          $speaker_ref,
          'conversation',
          $event_description,
          [
            'trigger' => 'room_interjection',
            'player_said' => substr($player_message, 0, 200),
            'trace_id' => (string) ($this->activeDebugTrace['trace_id'] ?? ''),
          ]
        );
      }
    }
    else {
      $this->psychologyService->recordInnerMonologue(
        $campaign_id,
        $speaker_ref,
        'conversation',
        $event_description,
        [
          'trigger' => 'room_interjection',
          'player_said' => substr($player_message, 0, 200),
          'trace_id' => (string) ($this->activeDebugTrace['trace_id'] ?? ''),
        ]
      );
    }
    $this->recordDebugStage('npc.interjection_record_monologue', $stage_started_at, [
      'npc_entity' => $speaker_ref,
    ]);

    if ($feed_room_sessions) {
      $stage_started_at = hrtime(true);
      $room_observation = $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? []);
      $this->recordDebugStage('npc.interjection_build_room_observation_feed', $stage_started_at, [
        'npc_entity' => $speaker_ref,
        'observation_length' => strlen($room_observation),
      ]);

      $stage_started_at = hrtime(true);
      $this->feedRoomChatToNpcSessions(
        $campaign_id,
        $room_npcs,
        $player_message,
        $gm_narrative,
        [$speaker_ref],
        $room_observation
      );
      $this->recordDebugStage('npc.interjection_feed_room_sessions', $stage_started_at, [
        'npc_entity' => $speaker_ref,
        'room_npc_count' => count($room_npcs),
      ]);
    }

    // Bridge into hierarchical session system.
    $stage_started_at = hrtime(true);
    $this->bridgeNpcInterjectionToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $speaker_name, $npc_dialogue, $speaker_ref
    );
    $this->recordDebugStage('npc.interjection_bridge_session_system', $stage_started_at, [
      'npc_entity' => $speaker_ref,
    ]);

    $this->logger->info('NPC interjection by @npc in room @room: @msg', [
      '@npc' => $speaker_name,
      '@room' => $room_id,
      '@msg' => substr($npc_dialogue, 0, 100),
    ]);
    $this->recordDebugStage('npc.interjection_total', $interjection_stage_started_at, [
      'npc_entity' => $speaker_ref,
      'spoke' => TRUE,
    ]);

    return [$npc_message];
  }

  /**
   * Resolve a directly addressed NPC from player text without relying on the LLM.
   */

  protected function resolveDirectlyAddressedNpc(array $room_npcs, string $player_message, bool $allow_unclear_fallback = TRUE): ?array {
    $message = $this->normalizeNpcNameForMatch($player_message);
    if ($message === '') {
      return NULL;
    }

    $matches = [];
    foreach ($room_npcs as $npc) {
      $display_name = (string) ($npc['profile']['display_name'] ?? '');
      if ($display_name === '') {
        continue;
      }

      $score = max(
        $this->scoreNpcDirectAddressMatch($display_name, $message),
        $this->scoreNpcAliasAddressMatch($npc, $message)
      );
      if ($score <= 0) {
        continue;
      }

      $matches[] = [
        'score' => $score,
        'npc' => $npc,
      ];
    }

    if ($matches === []) {
      return $allow_unclear_fallback ? $this->selectHighestCharismaNpc($room_npcs) : NULL;
    }

    return $this->selectHighestScoredNpc($matches, $room_npcs);
  }

  /**
   * Score direct-address matches against NPC alias signals beyond display name.
   */
  protected function scoreNpcAliasAddressMatch(array $npc, string $normalized_message): int {
    $aliases = [];

    $entity_ref = trim((string) ($npc['entity_ref'] ?? ''));
    if ($entity_ref !== '') {
      $aliases[] = $this->normalizeNpcNameForMatch(preg_replace('/^npc[_-]?/', '', $entity_ref) ?? $entity_ref);
    }

    $entity = is_array($npc['entity'] ?? NULL) ? $npc['entity'] : [];
    $entity_names = [
      (string) ($entity['name'] ?? ''),
      (string) ($entity['state']['metadata']['display_name'] ?? ''),
      (string) ($entity['state']['metadata']['name'] ?? ''),
    ];
    foreach ($entity_names as $candidate) {
      $normalized_candidate = $this->normalizeNpcNameForMatch($candidate);
      if ($normalized_candidate !== '') {
        $aliases[] = $normalized_candidate;
      }
    }

    $expanded_aliases = [];
    foreach ($aliases as $alias) {
      $expanded_aliases[$alias] = TRUE;
      if (str_contains($alias, 'grandmother') || str_contains($alias, 'grandma')) {
        $expanded_aliases['grandmother'] = TRUE;
        $expanded_aliases['grandma'] = TRUE;
        $expanded_aliases['old lady'] = TRUE;
        $expanded_aliases['kind old lady'] = TRUE;
        $expanded_aliases['nice old lady'] = TRUE;
      }
      if (str_contains($alias, 'old lady')) {
        $expanded_aliases['grandmother'] = TRUE;
        $expanded_aliases['grandma'] = TRUE;
      }
    }

    foreach (array_keys($expanded_aliases) as $alias) {
      if ($alias === '') {
        continue;
      }
      if (preg_match('/\b' . preg_quote($alias, '/') . '\b/u', $normalized_message)) {
        return 95;
      }
    }

    foreach (array_keys($expanded_aliases) as $alias) {
      $tokens = preg_split('/\s+/', $alias) ?: [];
      foreach ($tokens as $token) {
        if (strlen($token) < self::NPC_FUZZY_MATCH_MIN_TOKEN_LENGTH) {
          continue;
        }
        if (preg_match('/\b' . preg_quote($token, '/') . '\b/u', $normalized_message)) {
          return 85;
        }
      }
    }

    return 0;
  }

  /**
   * Deterministically shortlist NPCs worth evaluating for room interjections.
   *
   * This avoids serial LLM evaluation for every room NPC on every turn.
   *
   * @return array
   *   Candidate NPC rows in priority order.
   */

  protected function buildNpcInterjectionCandidates(
    int $campaign_id,
    array $room_npcs,
    string $player_message,
    string $gm_narrative,
    ?array $directly_addressed_npc = NULL
  ): array {
    if ($directly_addressed_npc !== NULL) {
      return [$directly_addressed_npc];
    }

    $combined_text = $this->normalizeNpcNameForMatch($player_message . ' ' . $gm_narrative);
    if ($combined_text === '') {
      return [];
    }

    $scored = [];
    foreach ($room_npcs as $npc) {
      $score = $this->scoreNpcInterjectionCandidate($npc, $campaign_id, $combined_text, $player_message, $gm_narrative);
      if ($score < 40) {
        continue;
      }
      $scored[] = [
        'score' => $score,
        'npc' => $npc,
      ];
    }

    if ($scored === []) {
      return [];
    }

    usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    return array_map(static fn(array $row): array => $row['npc'], array_slice($scored, 0, 2));
  }

  /**
   * Score how likely an NPC is to matter for the current exchange.
   */

  protected function scoreNpcInterjectionCandidate(
    array $npc,
    int $campaign_id,
    string $combined_text,
    string $player_message,
    string $gm_narrative
  ): int {
    $profile = is_array($npc['profile'] ?? NULL) ? $npc['profile'] : [];
    $display_name = (string) ($profile['display_name'] ?? '');
    $role = strtolower((string) ($profile['role'] ?? ''));
    $attitude = $this->resolveActorDispositionAttitude(
      $campaign_id,
      (string) ($npc['entity_ref'] ?? ''),
      [
        'profile' => $profile,
        'entity' => is_array($npc['entity'] ?? NULL) ? $npc['entity'] : [],
        'attitude' => $profile['attitude'] ?? NULL,
      ]
    );
    $motivations = strtolower((string) ($profile['motivations'] ?? ''));
    $normalized_player_message = $this->normalizeNpcNameForMatch($player_message);

    $score = 0;
    if ($display_name !== '') {
      $score = max($score, $this->scoreNpcDirectAddressMatch($display_name, $normalized_player_message) - 20);
    }

    if ($this->textContainsAny($combined_text, ['quest', 'job', 'task', 'mission', 'objective', 'reward', 'deliver', 'gather', 'help'])) {
      if ($this->npcSupportsQuestOrLeadDialogue($npc)) {
        $score += 50;
      }
      if (str_contains($motivations, 'help') || str_contains($motivations, 'answer')) {
        $score += 15;
      }
    }

    if ($this->textContainsAny($combined_text, ['buy', 'sell', 'price', 'cost', 'coin', 'gold', 'silver', 'copper', 'change', 'pay', 'torch', 'ale', 'drink', 'room', 'rent', 'tab'])) {
      if ($this->textContainsAny(strtolower($display_name . ' ' . $role . ' ' . $motivations), ['keeper', 'merchant', 'shop', 'vendor', 'tavern', 'inn', 'sell', 'bar'])) {
        $score += 55;
      }
    }

    if ($this->textContainsAny($combined_text, ['where', 'go', 'lead', 'guide', 'direction', 'path', 'way'])) {
      if ($role === 'guide' || $this->npcSupportsQuestOrLeadDialogue($npc)) {
        $score += 45;
      }
    }

    if ($this->textContainsAny($combined_text, ['you', 'your', 'yours', 'hello', 'hi', 'hey', 'thanks', 'thank'])) {
      if ($attitude === 'helpful' || $attitude === 'friendly') {
        $score += 10;
      }
    }

    if ($this->textContainsAny($this->normalizeNpcNameForMatch($player_message), ['who', 'someone', 'anyone', 'everyone'])) {
      $score += 5;
    }

    return $score;
  }

  /**
   * Check if normalized text contains any keyword fragment.
   */

  protected function textContainsAny(string $normalized_text, array $keywords): bool {
    $expanded_text = $this->expandCommonNormalizedContractions($normalized_text);
    foreach ($keywords as $keyword) {
      $normalized_keyword = $this->normalizeNpcNameForMatch($keyword);
      if ($normalized_keyword !== '' && (
        str_contains($normalized_text, $normalized_keyword)
        || str_contains($expanded_text, $normalized_keyword)
      )) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Expand a small set of common contractions after normalization.
   */

  protected function expandCommonNormalizedContractions(string $normalized_text): string {
    return preg_replace([
      '/\b(who|what|where|when|why|how|that|there|here|it|he|she)\s+s\b/u',
      '/\bi\s+m\b/u',
    ], [
      '$1 is',
      'i am',
    ], $normalized_text) ?? $normalized_text;
  }

  /**
   * Resolve selected NPCs from an interjection-evaluator response.
   *
   * @return array
   *   Array of room NPC entries in resolver order.
   */

  protected function resolveSelectedRoomNpcs(array $room_npcs, string $response_text): array {
    if ($response_text === '' || strtoupper($response_text) === 'NONE' || stripos($response_text, 'none') === 0) {
      return [];
    }

    $json_match = [];
    if (preg_match('/\{.*\}/s', $response_text, $json_match)) {
      $parsed = json_decode($json_match[0], TRUE);
    }
    else {
      $parsed = json_decode($response_text, TRUE);
    }

    if (!is_array($parsed)) {
      return [];
    }

    $speaker_names = [];
    if (!empty($parsed['speakers']) && is_array($parsed['speakers'])) {
      foreach ($parsed['speakers'] as $speaker_name) {
        if (is_string($speaker_name) && trim($speaker_name) !== '') {
          $speaker_names[] = trim($speaker_name);
        }
      }
    }
    elseif (!empty($parsed['speaker']) && is_string($parsed['speaker'])) {
      $speaker_names[] = trim($parsed['speaker']);
    }

    $resolved = [];
    foreach ($speaker_names as $speaker_name) {
      $npc = $this->resolveNamedRoomNpc($room_npcs, $speaker_name);
      if ($npc === NULL) {
        $this->logger->warning('NPC interjection referenced unknown speaker: @speaker', [
          '@speaker' => $speaker_name,
        ]);
        continue;
      }

      $resolved[$npc['entity_ref']] = $npc;
    }

    return array_values($resolved);
  }

  /**
   * Score how strongly a player message appears to address an NPC by name.
   */

  protected function scoreNpcDirectAddressMatch(string $display_name, string $normalized_message): int {
    $normalized_name = $this->normalizeNpcNameForMatch($display_name);
    if ($normalized_name === '') {
      return 0;
    }

    $name_tokens = preg_split('/\s+/', $normalized_name) ?: [];
    $message_tokens = preg_split('/\s+/', $normalized_message) ?: [];

    if (count($name_tokens) === 1) {
      $candidate_token = $name_tokens[0];
      foreach ($message_tokens as $index => $message_token) {
        if ($message_token !== $candidate_token) {
          continue;
        }

        $previous_token = strtolower((string) ($message_tokens[$index - 1] ?? ''));
        if ($this->isSingleTokenNpcVerbCollision($candidate_token, $previous_token)) {
          continue;
        }

        return 100;
      }

      return 0;
    }
    elseif (preg_match('/\b' . preg_quote($normalized_name, '/') . '\b/u', $normalized_message)) {
      return 100;
    }

    $tokens = $name_tokens;
    foreach ($tokens as $token) {
      if (strlen($token) < self::NPC_FUZZY_MATCH_MIN_TOKEN_LENGTH) {
        continue;
      }
      if (preg_match('/\b' . preg_quote($token, '/') . '\b/u', $normalized_message)) {
        return 90;
      }
    }

    foreach ($tokens as $token) {
      if (strlen($token) < self::NPC_FUZZY_MATCH_MIN_TOKEN_LENGTH) {
        continue;
      }
      foreach ($message_tokens as $message_token) {
        if (strlen($message_token) < self::NPC_FUZZY_MATCH_MIN_TOKEN_LENGTH) {
          continue;
        }
        $distance = levenshtein($token, $message_token);
        if ($distance <= 1) {
          return 80 - $distance;
        }
      }
    }

    return 0;
  }

  /**
   * Classify the current room turn for deterministic shortcuts.
   */

  protected function classifyRoomTurnIntent(
    string $player_message,
    array $room_npcs = [],
    ?array $directly_addressed_npc = NULL,
    ?array $active_conversation_npc = NULL
  ): string {
    $normalized = $this->normalizeNpcNameForMatch($player_message);
    $routing_context = [
      'player_message' => $this->truncateContextBlock($player_message, 160, 0.85),
      'normalized' => $this->truncateContextBlock($normalized, 160, 0.85),
      'room_npc_count' => count($room_npcs),
      'directly_addressed_npc' => $directly_addressed_npc['entity_ref'] ?? NULL,
      'active_conversation_npc' => $active_conversation_npc['entity_ref'] ?? NULL,
    ];
    if ($normalized === '') {
      return $this->finalizeRoomIntentDecision('gm_narration', 'empty_message', $routing_context);
    }

    if ($this->looksLikeGmRoleBoundaryCorrection($normalized)) {
      return $this->finalizeRoomIntentDecision('gm_role_correction', 'gm_role_boundary', $routing_context);
    }

    if ($this->textContainsAny($normalized, ['ooc', 'out of character', 'meta'])) {
      return $this->finalizeRoomIntentDecision('ooc_meta', 'ooc_meta', $routing_context);
    }

    if ($this->looksLikeGmAdjudicationQuery($player_message, $normalized)) {
      return $this->finalizeRoomIntentDecision('gm_adjudication_query', 'gm_adjudication', $routing_context);
    }

    if ($this->looksLikeRoomRosterQuery($normalized)) {
      return $this->finalizeRoomIntentDecision('room_roster_query', 'room_roster_query', $routing_context);
    }

    if ($this->looksLikeRoomDescriptionQuery($normalized)) {
      return $this->finalizeRoomIntentDecision('room_description_query', 'room_description_query', $routing_context);
    }

    if ($this->looksLikeNavigationQuery($normalized)) {
      return $this->finalizeRoomIntentDecision('navigation_query', 'navigation_query', $routing_context);
    }

    if ($this->looksLikeNavigationTurn($normalized)) {
      return $this->finalizeRoomIntentDecision('navigation_travel', 'navigation_travel', $routing_context);
    }

    if ($this->looksLikeCombatEngagementTurn($normalized)) {
      return $this->finalizeRoomIntentDecision('combat_engagement', 'combat_engagement', $routing_context);
    }

    if ($directly_addressed_npc !== NULL) {
      if ($this->looksLikeQuestOrLeadRequest($normalized)) {
        return $this->finalizeRoomIntentDecision('direct_npc_dialogue', 'direct_address_quest', $routing_context);
      }
      if ($this->looksLikeMerchantTransactionText($normalized) && $this->npcSupportsMerchantDialogue($directly_addressed_npc)) {
        return $this->finalizeRoomIntentDecision('direct_npc_transaction', 'direct_address_merchant', $routing_context);
      }
      return $this->finalizeRoomIntentDecision('direct_npc_dialogue', 'direct_address_default', $routing_context);
    }

    if (
      $active_conversation_npc !== NULL
      && $this->shouldContinueActiveRoomConversation($player_message, $normalized, $active_conversation_npc)
    ) {
      if ($this->looksLikeMerchantTransactionText($normalized) && $this->npcSupportsMerchantDialogue($active_conversation_npc)) {
        return $this->finalizeRoomIntentDecision('direct_npc_transaction', 'active_conversation_merchant', $routing_context);
      }
      return $this->finalizeRoomIntentDecision('direct_npc_dialogue', 'active_conversation_continue', $routing_context);
    }

    if ($this->looksLikeQuestOrLeadRequest($normalized)) {
      foreach ($room_npcs as $npc) {
        if ($this->npcSupportsQuestOrLeadDialogue($npc)) {
          return $this->finalizeRoomIntentDecision('quest_query', 'room_quest_query', $routing_context + [
            'matched_npc' => $npc['entity_ref'] ?? NULL,
          ]);
        }
      }
      return $this->finalizeRoomIntentDecision('quest_query', 'room_quest_query_generic', $routing_context);
    }

    if ($this->looksLikeMerchantTransactionText($normalized)) {
      foreach ($room_npcs as $npc) {
        if ($this->npcSupportsMerchantDialogue($npc)) {
          return $this->finalizeRoomIntentDecision('merchant_inquiry', 'room_merchant_inquiry', $routing_context + [
            'matched_npc' => $npc['entity_ref'] ?? NULL,
          ]);
        }
      }
    }

    return $this->finalizeRoomIntentDecision('gm_narration', 'default_fallback', $routing_context);
  }

  /**
   * Log the winning room-intent route with enough detail to debug condition order.
   */

  protected function finalizeRoomIntentDecision(string $intent, string $reason, array $context = []): string {
    $meta = $context + [
      'intent' => $intent,
      'reason' => $reason,
    ];
    $this->recordDebugStage('gm.intent_route', hrtime(true), $meta);
    $this->logger->info('Room intent routed to @intent via @reason (direct=@direct active=@active npcs=@npcs)', [
      '@intent' => $intent,
      '@reason' => $reason,
      '@direct' => (string) ($meta['directly_addressed_npc'] ?? 'none'),
      '@active' => (string) ($meta['active_conversation_npc'] ?? 'none'),
      '@npcs' => (string) ($meta['room_npc_count'] ?? 0),
    ]);
    return $intent;
  }

  /**
   * Build a deterministic GM narrative for short-path intents.
   *
   * This is the safest place to grow automatic low-variance room responses.
   * Use it for grounded informational turns before expanding LLM caching.
   */

  protected function buildDeterministicGmResponse(
    int $campaign_id,
    string $intent,
    array $room_npcs,
    ?array $directly_addressed_npc,
    string $channel,
    string $player_message,
    array $room_meta = [],
    string $room_id = '',
    array $dungeon_data = [],
    bool $is_room_entry = FALSE,
    ?array $character_data = NULL,
    ?int $character_id = NULL
  ): ?array {
    if ($intent === 'room_description_query' || ($is_room_entry && $intent === 'gm_narration')) {
      $room_description = $this->buildDeterministicRoomDescriptionNarrative($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
      if ($room_description !== '') {
        return [
          'narrative' => $room_description,
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
          'suppress_npc_interjections' => TRUE,
        ];
      }
    }

    if ($intent === 'combat_engagement') {
      $hostiles = $this->findRoomHostileEntities($room_id, $dungeon_data, $player_message);
      if ($hostiles !== []) {
        $source_entity_ref = $this->resolvePlayerEntityRefForRoomAction($room_id, $dungeon_data, $character_id);
        $hostile_ids = [];
        $hostile_names = [];
        foreach ($hostiles as $hostile) {
          $hostile_id = (string) ($hostile['entity_instance_id'] ?? $hostile['instance_id'] ?? $hostile['id'] ?? '');
          $hostile_name = trim((string) ($hostile['state']['metadata']['display_name'] ?? $hostile['name'] ?? ''));
          if ($hostile_id !== '') {
            $hostile_ids[] = $hostile_id;
          }
          if ($hostile_name !== '') {
            $hostile_names[] = $hostile_name;
          }
        }
        $target_summary = $hostile_names !== [] ? implode(', ', array_unique($hostile_names)) : 'the hostiles in the room';
        $normalized_message = $this->normalizeNpcNameForMatch($player_message);
        $narrative = $this->textContainsAny($normalized_message, ['cast sleep', 'i cast sleep', 'sleep spell'])
          ? 'The room lurches from tense silence into open combat around ' . $target_summary . '.'
          : 'The standoff breaks, and the room erupts into combat around ' . $target_summary . '.';
        return [
          'narrative' => $narrative,
          'actions' => [[
            'type' => 'combat_initiation',
            'name' => 'Engage hostiles',
            'details' => [
              'combat' => [
                'reason' => 'The player commits to immediate violence against the hostile creatures in the room.',
                'enemy_entity_ids' => array_values(array_unique($hostile_ids)),
                'source_entity_ref' => $source_entity_ref,
              ],
              'result_description' => 'Combat begins immediately from the player declaration.',
            ],
          ]],
          'dice_rolls' => [],
          'validation_errors' => [],
        ];
      }

      return [
        'narrative' => 'You commit to the attack, but no clear hostile target is grounded in the current room state yet.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
      ];
    }

    if ($intent === 'navigation_query') {
      $navigation_narrative = $this->buildDeterministicNavigationQueryNarrative($room_meta, $room_id, $dungeon_data);
      if ($navigation_narrative !== '') {
        return [
          'narrative' => $navigation_narrative,
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
        ];
      }
    }

    if ($intent === 'navigation_travel') {
      $navigation_action = $this->buildDeterministicNavigationAction(
        $campaign_id,
        $character_id,
        $player_message,
        $room_meta,
        $room_id,
        $dungeon_data
      );
      if ($navigation_action !== NULL) {
        return [
          'narrative' => $navigation_action['narrative'],
          'actions' => [$navigation_action['action']],
          'dice_rolls' => [],
          'validation_errors' => [],
        ];
      }
    }

    if ($intent === 'room_roster_query') {
      $normalized_message = $this->normalizeNpcNameForMatch($player_message);
      if ($room_npcs === [] && $this->looksLikeExpectedOccupantsIssue($normalized_message)) {
        return [
          'narrative' => 'No named occupants are grounded in this room state right now, so the expected meetup NPCs are currently missing from the active room roster.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
        ];
      }
      $roster_narrative = $this->buildDeterministicRoomRosterNarrative($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
      if ($roster_narrative === '') {
        return [
          'narrative' => $this->looksLikeExpectedOccupantsIssue($normalized_message)
            ? 'No named occupants are grounded in this room state right now, so the expected meetup NPCs are currently missing from the active room roster.'
            : 'No other grounded named occupants are clearly established in this room right now.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
        ];
      }
      return [
        'narrative' => $roster_narrative,
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
      ];
    }

    if ($intent === 'ooc_meta') {
      return [
        'narrative' => 'Out of character: NPCs can answer directly when you address them, and other people in the room may chime in when the topic concerns them.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
      ];
    }

    if ($intent === 'gm_role_correction') {
      return [
        'narrative' => 'Acknowledged. The GM layer will stay in referee narration and leave your character\'s words, thoughts, and choices to you.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_npc_interjections' => TRUE,
      ];
    }

    if ($intent === 'gm_adjudication_query') {
      return [
        'narrative' => $this->buildDeterministicGmAdjudicationNarrative($player_message, $campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs, $character_data),
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
      ];
    }

    if ($intent === 'merchant_inquiry') {
      $merchant_response = $this->buildDeterministicMerchantResponse(
        $campaign_id,
        $room_id,
        $this->findMerchantNpc($room_npcs),
        $player_message,
        $character_id
      );
      if ($merchant_response !== NULL) {
        return $merchant_response;
      }

      if ($channel !== 'room') {
        return [
          'narrative' => $this->buildDeterministicGmAdjudicationNarrative($player_message, $campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs, $character_data),
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
        ];
      }

      return [
        'narrative' => '',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_visible_gm_response' => TRUE,
      ];
    }

    if (
      $channel === 'room'
      && ($intent === 'direct_npc_dialogue' || $intent === 'direct_npc_transaction')
      && $directly_addressed_npc !== NULL
    ) {
      return [
        'narrative' => '',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_visible_gm_response' => TRUE,
      ];
    }

    if ($intent === 'quest_query') {
      foreach ($room_npcs as $npc) {
        if ($this->npcSupportsQuestOrLeadDialogue($npc)) {
          if ($channel !== 'room') {
            return [
              'narrative' => $this->buildDeterministicGmAdjudicationNarrative($player_message, $campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs, $character_data),
              'actions' => [],
              'dice_rolls' => [],
              'validation_errors' => [],
            ];
          }
          return [
            'narrative' => '',
            'actions' => [],
            'dice_rolls' => [],
            'validation_errors' => [],
            'suppress_visible_gm_response' => TRUE,
          ];
        }
      }
      $quest_givers = [];
      foreach ($room_npcs as $npc) {
        if ($this->npcSupportsQuestOrLeadDialogue($npc)) {
          $name = trim((string) ($npc['profile']['display_name'] ?? ''));
          if ($name !== '') {
            $quest_givers[] = $name;
          }
        }
      }
      if ($quest_givers !== []) {
        return [
          'narrative' => 'If you want work, ask ' . implode(' or ', array_slice($quest_givers, 0, 2)) . ' directly about available leads or active objectives.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
          'suppress_npc_interjections' => TRUE,
        ];
      }
    }

    if ($intent === 'gm_narration') {
      $normalized_message = $this->normalizeNpcNameForMatch($player_message);
      if ($this->looksLikeGmBackstopQuery($player_message, $normalized_message)) {
        if ($this->looksLikeNavigationQuery($normalized_message)) {
          $navigation_narrative = $this->buildDeterministicNavigationQueryNarrative($room_meta, $room_id, $dungeon_data);
          if ($navigation_narrative !== '') {
            return [
              'narrative' => $navigation_narrative,
              'actions' => [],
              'dice_rolls' => [],
              'validation_errors' => [],
              'suppress_npc_interjections' => TRUE,
            ];
          }
        }

        if ($this->looksLikeRoomRosterQuery($normalized_message)) {
          if ($room_npcs === [] && $this->looksLikeExpectedOccupantsIssue($normalized_message)) {
            return [
              'narrative' => 'No named occupants are grounded in this room state right now, so the expected meetup NPCs are currently missing from the active room roster.',
              'actions' => [],
              'dice_rolls' => [],
              'validation_errors' => [],
              'suppress_npc_interjections' => TRUE,
            ];
          }
          $roster_narrative = $this->buildDeterministicRoomRosterNarrative($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
          return [
            'narrative' => $roster_narrative !== ''
              ? $roster_narrative
              : ($this->looksLikeExpectedOccupantsIssue($normalized_message)
                ? 'No named occupants are grounded in this room state right now, so the expected meetup NPCs are currently missing from the active room roster.'
                : 'No other grounded named occupants are clearly established in this room right now.'),
            'actions' => [],
            'dice_rolls' => [],
            'validation_errors' => [],
            'suppress_npc_interjections' => TRUE,
          ];
        }

        return [
          'narrative' => $this->buildDeterministicGmAdjudicationNarrative($player_message, $campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs, $character_data),
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
          'suppress_npc_interjections' => TRUE,
        ];
      }
    }

    return NULL;
  }

  /**
   * Build an observational room-roster response without dialogue.
   */

  protected function buildDeterministicRoomRosterNarrative(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $room_npcs = []
  ): string {
    return DeterministicNarrativeHelper::buildRoomRosterNarrative(
      $campaign_id,
      $room_id,
      $room_meta,
      $dungeon_data,
      $room_npcs,
      fn(string $text, int $max, float $ratio): string => $this->truncateContextBlock($text, $max, $ratio),
      fn(int $campaign_id, string $room_id, array $dungeon_data): string => $this->buildRoomActorGroundingSummary($campaign_id, $room_id, $dungeon_data)
    );
  }

  /**
   * Build a grounded referee answer for explicit GM/adjudication questions.
   */

  protected function buildDeterministicGmAdjudicationNarrative(
    string $player_message,
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $room_npcs = [],
    ?array $character_data = NULL
  ): string {
    return DeterministicNarrativeHelper::buildGmAdjudicationNarrative(
      $player_message,
      $campaign_id,
      $room_id,
      $room_meta,
      $dungeon_data,
      $room_npcs,
      $character_data,
      fn(string $text): string => $this->normalizeNpcNameForMatch($text),
      fn(?array $character_data): string => $this->extractPlayerCharacterName($character_data),
      fn(string $normalized): bool => $this->looksLikeExpectedOccupantsIssue($normalized),
      fn(int $campaign_id, string $room_id, array $room_meta, array $dungeon_data, array $room_npcs): string => $this->buildDeterministicRoomRosterNarrative($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs),
      fn(string $entity_id, array $dungeon_data): array => $this->roomLocator->findEncounterTurnEntity($entity_id, $dungeon_data) ?? [],
      fn(string $haystack, array $needles): bool => $this->textContainsAny($haystack, $needles),
      fn(string $text, int $max, float $ratio): string => $this->truncateContextBlock($text, $max, $ratio)
    );
  }

  /**
   * Determine whether the player is leaving the current location.
   */

  protected function looksLikeNavigationTurn(string $normalized_message): bool {
    return DeterministicNarrativeHelper::looksLikeNavigationTurn(
      $normalized_message,
      fn(string $message): ?string => $this->extractNavigationDestination($message),
      fn(string $haystack, array $needles): bool => $this->textContainsAny($haystack, $needles)
    );
  }

  /**
   * Resolve player entity_ref in-room for deterministic combat initiation payloads.
   */
  protected function resolvePlayerEntityRefForRoomAction(string $room_id, array $dungeon_data, ?int $character_id): string {
    if ($character_id === NULL) {
      return '';
    }
    foreach (($dungeon_data['entities'] ?? []) as $entity) {
      if (($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      if ((int) ($entity['character_id'] ?? 0) !== $character_id) {
        continue;
      }
      $entity_ref = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($entity_ref !== '') {
        return $entity_ref;
      }
    }
    return '';
  }

  /**
   * Determine whether the player is asking about nearby rooms or exits.
   */

}
