<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\RoomChat\FallbackAutomationDecisionBuilder;

trait RoomChatServiceCoreFlowTrait {

  public function getChatHistory(int $campaign_id, string $room_id, string $channel = 'room', ?int $character_id = NULL): array {
    $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    $dungeon_data = $dungeon_snapshot['dungeon_data'];
    return $this->roomChatHistoryProjector->projectHistory(
      $dungeon_data,
      $room_id,
      $channel,
      $character_id,
      $this->channelManager,
      $this->encounterTranscriptPrefixService
    );
  }

  /**
   * Persist the deterministic room scene intro into instantiated room chat.
   *
   * This is a write-time injection used by the encounter/navigation framework
   * so the chat UI can render the room description from the authoritative room
   * instance. We intentionally do NOT synthesize this at read time in
   * getChatHistory().
   *
   * To avoid spam, this only injects when the room has no visible (non-internal)
   * chat messages yet.
   *
   * @return array|null
   *   The injected chat message, or NULL if nothing was injected.
   */

  public function injectRoomSceneNarratorIntroIfNeeded(array &$dungeon_data, string $room_id): ?array {
    return $this->roomChatHistoryProjector->injectRoomSceneNarratorIntroIfNeeded(
      $dungeon_data,
      $room_id,
      self::MAX_MESSAGES_PER_ROOM
    );
  }

  /**
   * Generate a single in-character player chat suggestion for automation.
   */

  public function suggestPlayerAutomationMessage(
    int $campaign_id,
    string $room_id,
    int $character_id,
    string $channel = 'room'
  ): array {
    if ($character_id <= 0) {
      throw new \InvalidArgumentException('Character ID is required.', 400);
    }
    if ($channel !== 'room') {
      throw new \InvalidArgumentException('Player automation only supports the room channel.', 400);
    }

    $character_data = $this->actionProcessor->loadCharacterData($character_id);
    if (!is_array($character_data) || $character_data === []) {
      throw new \InvalidArgumentException('Character not found.', 404);
    }

    $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
    $character_name = trim((string) ($basic_info['name'] ?? $character_data['name'] ?? 'Adventurer'));
    if ($character_name === '') {
      $character_name = 'Adventurer';
    }

    $history = $this->getChatHistory($campaign_id, $room_id, $channel, $character_id);
    $session_key = $this->sessionManager->roomChatSessionKey($campaign_id, $room_id);
    $session_context = $this->buildCompactSessionContext($session_key, $campaign_id, 4, 1200, 400, TRUE);
    $character_context = $this->buildPlayerAutomationCharacterContext($character_data);
    $quest_context = $this->buildPlayerAutomationQuestContext($campaign_id, $character_id);
    $conversation_context = $this->buildRoomConversationTranscript($history, self::PLAYER_AUTOMATION_ROOM_CHAT_LIMIT);

    $prompt = '';
    if ($session_context !== '') {
      $prompt .= "=== CAMPAIGN CHAT HISTORY ===\n{$session_context}\n\n---\n";
    }
    $prompt .= "=== RECENT ROOM CHAT ===\n{$conversation_context}\n\n";
    if ($quest_context !== '') {
      $prompt .= "=== ACTIVE QUEST OBJECTIVES ===\n{$quest_context}\n\n";
    }
    $prompt .= "=== PLAYER CHARACTER SHEET ===\n{$character_context}\n\n";
    $prompt .= "Before writing the line, answer internally: what is my next action, which active quest objective matters most, and how does this message help advance it?\n";
    $prompt .= "Write the single next room-chat message this character should send.\n";
    $prompt .= "Choose the next action and wording internally, then output only the exact message text to post.\n";
    $prompt .= "Your core goal on every call is to review active quests, identify the next action, and use the available talk tool to advance that action.\n";
    $prompt .= "Identify named quest characters, locations, targets, and items from the active objectives. Follow those leads first, in order, before generic exploration.\n";
    $prompt .= "If an NPC just gave a concrete lead, clue, direction, or suggested destination, acknowledge it and ask the most useful follow-up question before changing subjects or asking someone else for unrelated work.\n";
    $prompt .= "If a current quest objective or newly discovered lead is relevant, advance that objective instead of restarting the same generic inquiry.\n";
    $prompt .= "Stay fully in character. Keep it concise (1-2 sentences, max 240 characters). ";
    $prompt .= "Do not mention rules, dice, UI controls, JSON, or hidden GM knowledge.\n";

    $result = $this->invokeTimedModelCall(
      $prompt,
      'dungeoncrawler_content',
      'player_chat_suggestion',
      [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'character_id' => $character_id,
        'channel' => $channel,
      ],
      [
        'system_prompt' => "You are writing the next in-character chat line for {$character_name}. "
          . "Base it only on the supplied character sheet, personality, quest objectives, and campaign chat history. "
          . "On every call, decide the next action first and make the line advance that action using the room-chat tool. "
          . "If an NPC just helped, follow that lead or clarify it before starting a different topic. "
          . "Output only the message text to send.",
        'max_tokens' => 180,
        'skip_cache' => TRUE,
      ],
      [
        'character_name' => $character_name,
        'history_line_count' => count($history),
        'transcript_line_limit' => self::PLAYER_AUTOMATION_ROOM_CHAT_LIMIT,
        'session_context_length' => strlen($session_context),
        'quest_context_length' => strlen($quest_context),
        'character_context_length' => strlen($character_context),
      ]
    );

    $message = $this->sanitizePlayerAutomationSuggestion((string) ($result['response'] ?? ''));
    if ($message === '') {
      throw new \RuntimeException('Suggestion generation returned an empty message.');
    }

    return [
      'message' => $message,
      'character_name' => $character_name,
      'channel' => $channel,
    ];
  }

  /**
   * Suggest a non-rest fallback automation decision after repeated rest choices.
   */

  public function suggestPlayerAutomationFallbackDecision(
    int $campaign_id,
    string $room_id,
    int $character_id,
    array $snapshot = [],
    array $run_state = []
  ): array {
    $character_data = $this->actionProcessor->loadCharacterData($character_id);
    if (!is_array($character_data) || $character_data === []) {
      throw new \InvalidArgumentException('Character not found.', 404);
    }

    $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
    $character_name = trim((string) ($basic_info['name'] ?? $character_data['name'] ?? 'Adventurer'));
    if ($character_name === '') {
      $character_name = 'Adventurer';
    }

    $available_actions = array_values(array_unique(array_filter(
      array_map(static fn($action): string => strtolower(trim((string) $action)), $snapshot['available_actions'] ?? []),
      static fn(string $action): bool => $action !== ''
    )));
    if ($available_actions === []) {
      return [
        'type' => 'stop',
        'reason' => 'No exploration actions are currently available, so automation is pausing.',
        'decision_meta' => [
          'stage' => 'rest_analysis_stop',
          'priority' => 14,
          'room_id' => $room_id,
          'analysis_fallback' => TRUE,
        ],
      ];
    }

    $history = $this->getChatHistory($campaign_id, $room_id, 'room', $character_id);
    $session_key = $this->sessionManager->roomChatSessionKey($campaign_id, $room_id);
    $session_context = $this->buildCompactSessionContext($session_key, $campaign_id, 4, 1200, 400, TRUE);
    $character_context = $this->buildPlayerAutomationCharacterContext($character_data);
    $quest_context = $this->buildPlayerAutomationQuestContext($campaign_id, $character_id);
    $conversation_context = $this->buildRoomConversationTranscript($history, self::PLAYER_AUTOMATION_ROOM_CHAT_LIMIT);
    $room_context = $this->truncateContextBlock(json_encode([
      'room' => [
        'room_id' => $room_id,
        'name' => (string) ($snapshot['active_room']['name'] ?? ''),
        'description' => (string) ($snapshot['active_room']['description'] ?? ''),
      ],
      'available_actions' => $available_actions,
      'visible_npcs' => array_values(array_map(static function (array $npc): array {
        return array_filter([
          'entity_instance_id' => (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? ''),
          'name' => (string) ($npc['name'] ?? $npc['display_name'] ?? $npc['state']['metadata']['display_name'] ?? ''),
          'content_id' => (string) ($npc['content_id'] ?? ''),
        ], static fn($value) => $value !== '');
      }, $snapshot['visible_npcs'] ?? [])),
      'connected_rooms' => array_values(array_map(static function (array $room): array {
        return array_filter([
          'room_id' => (string) ($room['room_id'] ?? ''),
          'name' => (string) ($room['name'] ?? ''),
          'description' => (string) ($room['description'] ?? ''),
        ], static fn($value) => $value !== '');
      }, $snapshot['connected_rooms'] ?? [])),
      'memory' => [
        'pending_conversation_lead' => is_array($run_state['memory']['pending_conversation_lead'] ?? NULL) ? $run_state['memory']['pending_conversation_lead'] : NULL,
        'active_npc_lead' => is_array($run_state['memory']['active_npc_lead'] ?? NULL) ? $run_state['memory']['active_npc_lead'] : NULL,
        'talked_entities' => $run_state['memory']['talked_entities'] ?? [],
        'searched_rooms' => $run_state['memory']['searched_rooms'] ?? [],
        'visited_rooms' => $run_state['memory']['visited_rooms'] ?? [],
      ],
      'recent_trace' => array_slice(array_map(static function (array $trace): array {
        return array_filter([
          'decision_meta' => is_array($trace['decision_meta'] ?? NULL) ? $trace['decision_meta'] : [],
          'reason' => (string) (($trace['decision']['reason'] ?? $trace['reason'] ?? '') ?: ''),
          'success' => $trace['success'] ?? NULL,
        ], static fn($value) => $value !== NULL && $value !== '');
      }, $run_state['trace'] ?? []), -4),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}', 3000, 0.8);

    $prompt = '';
    if ($session_context !== '') {
      $prompt .= "=== CAMPAIGN CHAT HISTORY ===\n{$session_context}\n\n---\n";
    }
    $prompt .= "=== RECENT ROOM CHAT ===\n{$conversation_context}\n\n";
    if ($quest_context !== '') {
      $prompt .= "=== ACTIVE QUEST OBJECTIVES ===\n{$quest_context}\n\n";
    }
    $prompt .= "=== PLAYER CHARACTER SHEET ===\n{$character_context}\n\n";
    $prompt .= "=== ROOM / NPC / ACTION CONTEXT ===\n{$room_context}\n\n";
    $prompt .= "The deterministic automation selected rest. Re-evaluate the full room, NPC, quest, and campaign context and choose the single best next action for this player character.\n";
    $prompt .= "Prioritize in order: active quest target -> actionable NPC lead -> search for quest item/clue -> transition to the next useful room -> talk to the best NPC -> rest only if there is genuinely no better progression path -> wait.\n";
    $prompt .= "Use exact IDs from the provided room context for any target_id.\n";
    $prompt .= "Return ONLY strict JSON with this shape:\n";
    $prompt .= "{\"action_type\":\"talk|search|transition|rest|wait\",\"target_id\":\"\",\"message\":\"\",\"reason\":\"short justification\"}\n";
    $prompt .= "Rules: only choose an action from available_actions; talk requires a target_id and short in-character message; transition requires a room_id target_id; search uses empty target_id; rest and wait use empty target_id and message.\n";

    $result = $this->invokeTimedModelCall(
      $prompt,
      'dungeoncrawler_content',
      'player_action_recommendation',
      [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'character_id' => $character_id,
      ],
      [
        'system_prompt' => "You are selecting the next automation action for {$character_name} after a rest candidate was detected. "
          . "Use the supplied quest, room, NPC, and campaign context. "
          . "Return only JSON and prefer concrete quest advancement over generic idle behavior. "
          . "Choose rest only if there is truly no better progression action.",
        'max_tokens' => 260,
        'skip_cache' => TRUE,
      ],
      [
        'character_name' => $character_name,
        'available_actions' => $available_actions,
      ]
    );

    $parsed = $this->parseJsonObjectFromText((string) ($result['response'] ?? ''));
    if (!is_array($parsed)) {
      return FallbackAutomationDecisionBuilder::buildDeterministicDecision(
        $snapshot,
        $run_state,
        $character_id,
        'Fallback analysis returned invalid JSON.'
      );
    }

    return FallbackAutomationDecisionBuilder::normalizeDecision(
      $parsed,
      $snapshot,
      $run_state,
      $character_id,
      static fn(array $snapshot, array $run_state, int $character_id, string $reason): array => FallbackAutomationDecisionBuilder::buildDeterministicDecision(
        $snapshot,
        $run_state,
        $character_id,
        $reason
      ),
      fn(string $message): string => $this->sanitizePlayerAutomationSuggestion($message)
    );
  }

  /**
   * Post a new chat message to a room.
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param string $speaker
   *   Speaker name.
   * @param string $message
   *   Message content.
   * @param string $type
   *   Message type (player|npc|system).
   * @param int|null $character_id
   *   Optional character ID.
   * 
   * @param bool $defer_npc_interjections
   *   When TRUE, skip optional room NPC interjections so they can be completed
   *   after the primary reply has been returned.
   *
   * @return array
   *   The created message with metadata.
   * 
   * @throws \InvalidArgumentException
   *   If validation fails or dungeon not found.
   */

  public function postMessage(
    int $campaign_id,
    string $room_id,
    string $speaker,
    string $message,
    string $type = 'player',
    ?int $character_id = null,
    string $channel = 'room',
    bool $defer_npc_interjections = FALSE,
    bool $suppress_gm = FALSE,
    ?callable $progress_callback = NULL,
    array $quest_touchpoint_hint = []
  ): array {
    $request_started_at = hrtime(true);
    $this->startDebugTrace([
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel,
      'type' => $type,
      'character_id' => $character_id,
      'speaker' => $speaker,
      'message_length' => strlen($message),
      'user_id' => (int) $this->currentUser->id(),
    ]);

    $stage_started_at = hrtime(true);
    $this->validateMessage($message, $type);
    $this->recordDebugStage('validate_message', $stage_started_at, [
      'message_length' => strlen($message),
      'speaker_length' => strlen($speaker),
    ]);

    $stage_started_at = hrtime(true);
    $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    $dungeon_id = $dungeon_snapshot['dungeon_id'];
    $dungeon_data = $dungeon_snapshot['dungeon_data'];
    $this->recordDebugStage('load_dungeon_data', $stage_started_at, [
      'dungeon_id' => $dungeon_id,
      'encoded_bytes' => $dungeon_snapshot['encoded_bytes'],
      'room_count' => count($dungeon_data['rooms'] ?? []),
    ]);

    // Initialize rooms structure if needed
    $stage_started_at = hrtime(true);
    if (!isset($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    // Find the room index — rooms may be keyed by room_id or numerically indexed.
    $created_room = FALSE;
    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'], $room_id);
    if ($room_index === NULL) {
      // Room doesn't exist yet; append a new entry.
      $dungeon_data['rooms'][] = ['room_id' => $room_id, 'chat' => []];
      $room_index = array_key_last($dungeon_data['rooms']);
      $created_room = TRUE;
    }
    if (!isset($dungeon_data['rooms'][$room_index]['chat'])) {
      $dungeon_data['rooms'][$room_index]['chat'] = [];
    }
    $this->recordDebugStage('resolve_room', $stage_started_at, [
      'room_index' => $room_index,
      'created_room' => $created_room,
    ]);

    $stage_started_at = hrtime(true);
    // EncounterPhaseHandler has already validated canonical Talk intents. Skip the
    // duplicate room-turn gate in that internal path while keeping this guard for
    // all other room-chat callers.
    $validated_encounter_talk = (
      $type === 'player'
      && $channel === 'room'
      && !empty($quest_touchpoint_hint['_validated_encounter_talk'])
    );
    $validated_encounter_room_chat = (
      $type === 'player'
      && $channel === 'room'
      && !empty($quest_touchpoint_hint['_validated_encounter_room_chat'])
    );
    $skip_encounter_turn_validation = $validated_encounter_talk || $validated_encounter_room_chat;

    $encounter_turn_error = $skip_encounter_turn_validation
      ? NULL
      : $this->encounterTurnGuard->validatePlayerTurnForChat(
        $dungeon_data,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data),
        $channel,
        $character_id,
        $type,
        $speaker
      );
    if ($encounter_turn_error !== NULL) {
      throw new \InvalidArgumentException($encounter_turn_error, 409);
    }

    $encounter_prefix = isset($quest_touchpoint_hint['_encounter_prefix']) && is_string($quest_touchpoint_hint['_encounter_prefix'])
      ? trim($quest_touchpoint_hint['_encounter_prefix'])
      : NULL;
    if ($encounter_prefix === '') {
      $encounter_prefix = NULL;
    }

    // Room chat is governed by the encounter engine (EncounterPhaseHandler).
    // Player room messages must be posted via the Talk action so the turn/action
    // framework remains authoritative.
    if ($type === 'player' && $channel === 'room' && $encounter_prefix === NULL && !$validated_encounter_room_chat) {
      throw new \InvalidArgumentException('Room chat must be sent as the Talk encounter action.', 409);
    }

    // Enforce canonical room-turn transcript prefixes during encounter room chat.
    if ($channel === 'room' && $type === 'player') {
      $canonical_player_prefix = $this->encounterTranscriptPrefixService->buildForSpeaker(
        $dungeon_data,
        $speaker,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
      );
      if (is_string($canonical_player_prefix) && trim($canonical_player_prefix) !== '') {
        $encounter_prefix = $canonical_player_prefix;
      }
    }

    // Non-player room lines also receive canonical turn-loop prefixes.
    if ($channel === 'room' && $type !== 'player' && $encounter_prefix === NULL) {
      $encounter_prefix = $this->encounterTranscriptPrefixService->buildForSpeaker(
        $dungeon_data,
        $speaker,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
      );
    }

    $message = $this->encounterTranscriptPrefixService->prefixChatText($message, $encounter_prefix);

    $this->recordDebugStage('validate_encounter_turn', $stage_started_at, [
      'phase' => $dungeon_data['game_state']['phase'] ?? NULL,
      'channel' => $channel,
      'type' => $type,
      'character_id' => $character_id,
    ]);

    // Validate channel access for non-room channels.
    $stage_started_at = hrtime(true);
    if ($channel !== 'room') {
      $channels = $this->channelManager->getChannels($dungeon_data, $room_index);
      if (!isset($channels[$channel])) {
        throw new \InvalidArgumentException('Channel not found: ' . $channel);
      }
      if ($character_id !== null) {
        $access = $this->channelManager->validateChannelAccess($channels[$channel], $character_id, $message);
        if (!$access['valid']) {
          throw new \InvalidArgumentException($access['error']);
        }
      }
    }
    $this->recordDebugStage('validate_channel_access', $stage_started_at, [
      'channel' => $channel,
      'character_id' => $character_id,
    ]);

    // Detect room entry BEFORE appending: true when this is the first message in this room.
    $is_room_entry = empty($dungeon_data['rooms'][$room_index]['chat']);

    // Create new message
    $new_message = [
      'speaker' => $this->sanitizeSpeakerName($speaker),
      'message' => $this->sanitizeMessage($message),
      'type' => $type,
      'channel' => $channel,
      'timestamp' => date('c'),
      'character_id' => $character_id,
      'user_id' => (int) $this->currentUser->id(),
    ];

    $stage_started_at = hrtime(true);
    $dungeon_data['rooms'][$room_index]['chat'][] = $new_message;
    $new_message['sequence_index'] = count($dungeon_data['rooms'][$room_index]['chat']);
    $dungeon_data['rooms'][$room_index]['chat'][array_key_last($dungeon_data['rooms'][$room_index]['chat'])] = $new_message;

    // Enforce message limit
    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
        $chat_count - self::MAX_MESSAGES_PER_ROOM
      );
    }

    // Update via direct database call (room chat doesn't need state versioning)
    // If this becomes a bottleneck, we could batch updates or use a separate table
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();
    $this->recordDebugStage('persist_player_message', $stage_started_at, [
      'total_messages' => count($dungeon_data['rooms'][$room_index]['chat']),
      'room_entry' => $is_room_entry,
    ]);
    $this->reportProgress($progress_callback, 'conversation_persisted', [
      'channel' => $channel,
      'room_entry' => $is_room_entry,
      'total_messages' => count($dungeon_data['rooms'][$room_index]['chat']),
    ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));

    // Log chat activity
    $this->logger->info('Chat message posted in room @room by user @uid: @message', [
      '@room' => $room_id,
      '@uid' => $this->currentUser->id(),
      '@message' => substr($message, 0, 100),
    ]);

    // Bridge into the hierarchical chat session system.
    // This dual-writes to the normalized dc_chat_messages table via NarrationEngine.
    $stage_started_at = hrtime(true);
    $this->bridgeToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $dungeon_data, $room_index,
      $speaker, $message, $type, $character_id, $channel
    );
    $this->recordDebugStage('bridge_to_session_system', $stage_started_at);
    $this->reportProgress($progress_callback, 'conversation_bridged', [
      'channel' => $channel,
    ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));

    // Generate AI response (GM for room channel, NPC for private channels).
    $gm_result = [];
    $gm_response = NULL;
    $state_diff = NULL;
    $navigation = NULL;
    $npc_interjections = [];
    $turn_harness_result = NULL;
    $quest_updates_pre_npc = [];
    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;
    if ($type === 'player' && !$suppress_gm) {
      if ($channel === 'room') {
        $stage_started_at = hrtime(true);
        $this->ensureCurrentRoomNpcProfiles($campaign_id, $room_id, $dungeon_data, $room_index);
        $this->recordDebugStage('ensure_room_npc_profiles', $stage_started_at);
        $this->reportProgress($progress_callback, 'npc_context_prepared', [
          'channel' => $channel,
        ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));
        // Room channel: GM responds.
        $this->reportProgress($progress_callback, 'gm_reply_generating', [
          'channel' => $channel,
        ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));
        $stage_started_at = hrtime(true);
        $gm_result = $this->generateGmReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id, $encounter_prefix);
        $this->recordDebugStage('generate_gm_reply', $stage_started_at, [
          'generated' => $gm_result !== NULL,
        ]);
      } else {
        // Private channel: target NPC responds.
        $channel_def = $dungeon_data['rooms'][$room_index]['channels'][$channel] ?? [];
        $this->reportProgress($progress_callback, 'gm_reply_generating', [
          'channel' => $channel,
        ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));
        $stage_started_at = hrtime(true);
        $gm_result = $this->generateChannelNpcReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id, $channel, $channel_def);
        $this->recordDebugStage('generate_channel_npc_reply', $stage_started_at, [
          'generated' => $gm_result !== NULL,
          'channel' => $channel,
        ]);
      }
      if ($gm_result !== NULL) {
        $gm_response = $gm_result['message'];
        $state_diff = $gm_result['state_diff'] ?? NULL;
        $navigation = $gm_result['navigation'] ?? NULL;
      }

      // Quest state must be evaluated before NPC turn generation so NPC dialogue
      // sees current authoritative quest status for this turn.
      if ($channel === 'room' && $gm_result !== NULL) {
        $quest_updates_pre_npc = $this->activateMentionedAvailableQuests(
          $campaign_id,
          $room_id,
          $character_id,
          $dungeon_data,
          $gm_response,
          [],
          $quest_touchpoint_hint,
          $message
        );
      }

      // After GM replies on the room channel, evaluate NPC interjections.
      // Room NPCs monitor the conversation and may chime in if motivated.
      if ($channel === 'room' && $gm_result !== NULL && empty($gm_result['suppress_npc_interjections'])) {
        $stage_started_at = hrtime(true);
        if ($defer_npc_interjections) {
          $this->recordDebugStage('evaluate_npc_interjections', $stage_started_at, [
            'count' => 0,
            'deferred' => TRUE,
          ]);
        }
        else {
          $stage_started_at = hrtime(true);
          $npc_turn_result = $this->runRoomTurnHarness(
            $campaign_id,
            $room_id,
            $room_index,
            $dungeon_id,
            $dungeon_data,
            $message,
            (string) ($gm_response['message'] ?? ''),
            $char_data,
            $encounter_prefix
          );
          $turn_harness_result = $npc_turn_result;
          $npc_interjections = $npc_turn_result['messages'] ?? [];
          $this->recordDebugStage('evaluate_npc_interjections', $stage_started_at, [
            'count' => count($npc_interjections),
            'deferred' => FALSE,
          ]);
        }
      }
    }

    $result = [
      'message' => $new_message,
      'totalMessages' => count($dungeon_data['rooms'][$room_index]['chat']),
      'dungeon_data' => $dungeon_data,
    ];
    if ($gm_response !== NULL) {
      $result['gm_response'] = $gm_response;
    }
    if ($suppress_gm && $type === 'player' && $channel === 'room') {
      $result['gm_deferred'] = TRUE;
    }
    if ($state_diff !== NULL) {
      $result['state_diff'] = $state_diff;
    }
    if (!empty($npc_interjections)) {
      $result['npc_interjections'] = $npc_interjections;
    }
    $quest_updates = $quest_updates_pre_npc;
    if ($channel === 'room' && $gm_result !== NULL) {
      if (!empty($npc_interjections)) {
        $post_npc_quest_updates = $this->activateMentionedAvailableQuests(
          $campaign_id,
          $room_id,
          $character_id,
          $dungeon_data,
          $gm_response,
          $npc_interjections,
          $quest_touchpoint_hint,
          $message
        );
        $quest_updates = $this->mergeQuestUpdatePayloads($quest_updates, $post_npc_quest_updates);
      }
    }
    else {
      $quest_updates = $this->activateMentionedAvailableQuests(
        $campaign_id,
        $room_id,
        $character_id,
        $dungeon_data,
        $gm_response,
        $npc_interjections,
        $quest_touchpoint_hint,
        $message
      );
    }
    if (
      $defer_npc_interjections
      && $turn_harness_result !== NULL
      && !empty($turn_harness_result['messages'])
      && is_array($turn_harness_result['messages'])
    ) {
      $deferred_quest_updates = $this->activateMentionedAvailableQuests(
        $campaign_id,
        $room_id,
        $character_id,
        $dungeon_data,
        $gm_response,
        $turn_harness_result['messages'],
        $quest_touchpoint_hint,
        $message
      );
      if ($deferred_quest_updates !== []) {
        $quest_updates = $this->mergeQuestUpdatePayloads($quest_updates, $deferred_quest_updates);
      }
      $this->logger->info('Deferred NPC turn quest handoff: campaign={campaign_id} room={room_id} character={character_id} deferred_message_count={deferred_message_count} deferred_quest_update_count={deferred_quest_update_count} deferred_quest_ids={deferred_quest_ids}', [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'character_id' => $character_id,
        'deferred_message_count' => count($turn_harness_result['messages']),
        'deferred_quest_update_count' => count($deferred_quest_updates),
        'deferred_quest_ids' => implode(', ', array_map(static function (array $update): string {
          return (string) ($update['quest_id'] ?? $update['quest_key'] ?? $update['quest_name'] ?? 'unknown');
        }, $deferred_quest_updates)),
      ]);
    }
    $this->logger->info('Room chat quest update handoff: campaign={campaign_id} room={room_id} character={character_id} quest_update_count={quest_update_count} quest_ids={quest_ids} gm_present={gm_present} npc_interjection_count={npc_interjection_count}', [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'character_id' => $character_id,
      'quest_update_count' => count($quest_updates),
      'quest_ids' => implode(', ', array_map(static function (array $update): string {
        return (string) ($update['quest_id'] ?? $update['quest_key'] ?? $update['quest_name'] ?? 'unknown');
      }, $quest_updates)),
      'gm_present' => $gm_response !== NULL ? 'yes' : 'no',
      'npc_interjection_count' => count($npc_interjections),
    ]);
    if ($quest_updates !== []) {
      $result['quest_updates'] = $quest_updates;
    }
    if ($turn_harness_result !== NULL) {
      $result['turn_harness'] = $turn_harness_result;
      if (!empty($turn_harness_result['turn_log_key'])) {
        $result['turn_log_key'] = $turn_harness_result['turn_log_key'];
      }
      if (!empty($turn_harness_result['turn_logs'])) {
        $result['turn_logs'] = $turn_harness_result['turn_logs'];
      }
    }
    if ($defer_npc_interjections && $channel === 'room' && $gm_response !== NULL) {
      $result['npc_interjections_deferred'] = TRUE;
    }
    if (!empty($gm_result['canonical_actions'])) {
      $result['canonical_actions'] = $gm_result['canonical_actions'];

      $combat_transition = $gm_result['canonical_actions']['combat_initiation']['transition'] ?? NULL;
      if (is_array($combat_transition) && !empty($combat_transition['success'])) {
        $result['combat_transition'] = $combat_transition;
        $result['dungeon_data'] = $this->reloadDungeonData($campaign_id);
      }
    }
    // Include navigation data so the client can switch to the new room.
    if ($navigation !== NULL && empty($navigation['error']) && $this->mapGenerator) {
      $result['navigation'] = $this->mapGenerator->buildClientNavigationPayload($navigation);
    }
    $debug_trace = $this->finalizeDebugTrace($request_started_at, [
      'gm_reply_generated' => $gm_response !== NULL,
      'npc_interjection_count' => count($npc_interjections),
      'npc_interjections_deferred' => $defer_npc_interjections && $channel === 'room' && $gm_response !== NULL,
      'total_messages' => count($dungeon_data['rooms'][$room_index]['chat']),
    ]);
    if ($debug_trace !== NULL) {
      $result['timing'] = $this->buildClientTimingSummary($debug_trace);
    }
    if ($debug_trace !== NULL && $this->shouldExposeDebugTrace()) {
      $result['debug_trace'] = $debug_trace;
    }
    return $this->buildRoomChatResponsePayload($result);
  }

  /**
   * Continue the room GM turn after one or more player messages were queued.
   */

  public function continueQueuedRoomConversation(
    int $campaign_id,
    string $room_id,
    ?int $character_id = NULL,
    string $channel = 'room',
    bool $defer_npc_interjections = FALSE,
    ?callable $progress_callback = NULL
  ): array {
    $request_started_at = hrtime(true);
    $this->startDebugTrace([
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel,
      'type' => 'gm_continuation',
      'character_id' => $character_id,
      'user_id' => (int) $this->currentUser->id(),
    ]);

    $stage_started_at = hrtime(true);
    $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    $dungeon_id = $dungeon_snapshot['dungeon_id'];
    $dungeon_data = $dungeon_snapshot['dungeon_data'];
    $this->recordDebugStage('load_dungeon_data', $stage_started_at, [
      'dungeon_id' => $dungeon_id,
      'encoded_bytes' => $dungeon_snapshot['encoded_bytes'],
      'room_count' => count($dungeon_data['rooms'] ?? []),
    ]);

    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    if ($room_index === NULL) {
      return $this->buildQueuedRoomContinuationPayload([
        'continued' => FALSE,
        'queued_player_count' => 0,
        'queued_player_summary' => '',
        'channel' => $channel,
      ]);
    }

    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $queued_player_messages = [];
    for ($i = count($chat) - 1; $i >= 0; $i--) {
      $entry = $chat[$i] ?? [];
      $entry_channel = (string) ($entry['channel'] ?? 'room');
      if ($entry_channel !== $channel) {
        continue;
      }
      if (($entry['type'] ?? '') === 'player') {
        array_unshift($queued_player_messages, $entry);
        continue;
      }
      break;
    }

    if ($queued_player_messages === []) {
      return $this->buildQueuedRoomContinuationPayload([
        'continued' => FALSE,
        'queued_player_count' => 0,
        'queued_player_summary' => '',
        'channel' => $channel,
      ]);
    }

    $queued_player_summary = implode("\n", array_map(static fn(array $entry): string => (string) ($entry['message'] ?? ''), $queued_player_messages));
    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;
    $this->reportProgress($progress_callback, 'queued_messages_loaded', [
      'queued_player_count' => count($queued_player_messages),
      'channel' => $channel,
    ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));

    $stage_started_at = hrtime(true);
    $this->ensureCurrentRoomNpcProfiles($campaign_id, $room_id, $dungeon_data, $room_index);
    $this->recordDebugStage('ensure_room_npc_profiles', $stage_started_at);
    $this->reportProgress($progress_callback, 'npc_context_prepared', [
      'queued_player_count' => count($queued_player_messages),
      'channel' => $channel,
    ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));

    $this->reportProgress($progress_callback, 'gm_reply_generating', [
      'queued_player_count' => count($queued_player_messages),
      'channel' => $channel,
    ] + $this->buildEncounterProgressSnapshotFromDungeonData($dungeon_data));

    $encounter_prefix = ($channel === 'room')
      ? $this->encounterTranscriptPrefixService->buildFromDungeonData(
        $dungeon_data,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
      )
      : NULL;

    $stage_started_at = hrtime(true);
    $gm_result = $channel === 'room'
      ? $this->generateGmReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id, $encounter_prefix)
      : $this->generateQueuedChannelReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id, $channel);
    $this->recordDebugStage('generate_gm_reply', $stage_started_at, [
      'generated' => $gm_result !== NULL,
      'queued_player_count' => count($queued_player_messages),
      'channel' => $channel,
    ]);

    $gm_response = $gm_result['message'] ?? NULL;
    $result = [
      'continued' => $gm_response !== NULL,
      'queued_player_count' => count($queued_player_messages),
      'queued_player_summary' => $queued_player_summary,
      'channel' => $channel,
    ];

    if ($gm_response !== NULL) {
      $result['gm_response'] = $gm_response;
      if (($gm_result['state_diff'] ?? NULL) !== NULL) {
        $result['state_diff'] = $gm_result['state_diff'];
      }
      if (!empty($gm_result['canonical_actions'])) {
        $result['canonical_actions'] = $gm_result['canonical_actions'];
      }
      $navigation = $gm_result['navigation'] ?? NULL;
      if ($navigation !== NULL && empty($navigation['error']) && $this->mapGenerator) {
        $result['navigation'] = $this->mapGenerator->buildClientNavigationPayload($navigation);
      }
      if ($defer_npc_interjections && $channel === 'room') {
        $result['npc_interjections_deferred'] = TRUE;
      }
    }

    $quest_updates = $this->activateMentionedAvailableQuests(
      $campaign_id,
      $room_id,
      $character_id,
      $dungeon_data,
      is_array($gm_response) ? $gm_response : NULL,
      [],
      [],
      (string) $queued_player_summary
    );
    $this->logger->info('Queued room continuation quest handoff: campaign={campaign_id} room={room_id} character={character_id} quest_update_count={quest_update_count} quest_ids={quest_ids} gm_present={gm_present}', [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'character_id' => $character_id ?? 0,
      'quest_update_count' => count($quest_updates),
      'quest_ids' => implode(', ', array_map(static function (array $update): string {
        return (string) ($update['quest_id'] ?? $update['quest_key'] ?? $update['quest_name'] ?? 'unknown');
      }, $quest_updates)),
      'gm_present' => $gm_response !== NULL ? 'yes' : 'no',
    ]);
    if ($quest_updates !== []) {
      $result['quest_updates'] = $quest_updates;
    }

    $debug_trace = $this->finalizeDebugTrace($request_started_at, [
      'gm_reply_generated' => $gm_response !== NULL,
      'queued_player_count' => count($queued_player_messages),
      'npc_interjections_deferred' => $defer_npc_interjections && $channel === 'room' && $gm_response !== NULL,
    ]);
    if ($debug_trace !== NULL) {
      $result['timing'] = $this->buildClientTimingSummary($debug_trace);
    }
    if ($debug_trace !== NULL && $this->shouldExposeDebugTrace()) {
      $result['debug_trace'] = $debug_trace;
    }

    return $this->buildQueuedRoomContinuationPayload($result);
  }

  /**
   * Complete deferred NPC room reactions after the main reply has been returned.
   */

  public function completeDeferredNpcInterjections(
    int $campaign_id,
    string $room_id,
    string $player_message,
    string $gm_narrative,
    ?int $character_id = NULL
  ): array {
    try {
      $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    }
    catch (\InvalidArgumentException $e) {
      return [];
    }

    $dungeon_id = $dungeon_snapshot['dungeon_id'];
    $dungeon_data = $dungeon_snapshot['dungeon_data'];

    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    if ($room_index === NULL) {
      return [];
    }

    $this->ensureCurrentRoomNpcProfiles($campaign_id, $room_id, $dungeon_data, $room_index);
    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;
    $turn_result = $this->runRoomTurnHarness(
      $campaign_id,
      $room_id,
      $room_index,
      $dungeon_id,
      $dungeon_data,
      $player_message,
      $gm_narrative,
      $char_data
    );

    $deferred_messages = is_array($turn_result['messages'] ?? NULL) ? $turn_result['messages'] : [];
    $quest_updates = $this->activateMentionedAvailableQuests(
      $campaign_id,
      $room_id,
      $character_id ?? 0,
      $dungeon_data,
      ['message' => $gm_narrative],
      $deferred_messages,
      [],
      $player_message
    );
    $this->logger->info('Deferred NPC completion quest handoff: campaign={campaign_id} room={room_id} character={character_id} deferred_message_count={deferred_message_count} deferred_quest_update_count={deferred_quest_update_count} deferred_quest_ids={deferred_quest_ids}', [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'character_id' => $character_id ?? 0,
      'deferred_message_count' => count($deferred_messages),
      'deferred_quest_update_count' => count($quest_updates),
      'deferred_quest_ids' => implode(', ', array_map(static function (array $update): string {
        return (string) ($update['quest_id'] ?? $update['quest_key'] ?? $update['quest_name'] ?? 'unknown');
      }, $quest_updates)),
    ]);
    if ($quest_updates !== []) {
      $turn_result['quest_updates'] = $quest_updates;
    }

    return $turn_result;
  }

  /**
   * Report a coarse progress update back to a streaming caller.
   */

  protected function reportProgress(?callable $progress_callback, string $stage, array $context = []): void {
    if ($progress_callback === NULL) {
      return;
    }

    $progress_callback([
      'stage' => $stage,
      'context' => $context,
    ]);
  }

  /**
   * Build encounter round/turn snapshot fields for streamed progress events.
   */

  protected function buildEncounterProgressSnapshotFromDungeonData(array $dungeon_data): array {
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    if ($game_state === []) {
      return [];
    }

    $round_raw = is_numeric($game_state['round'] ?? NULL) ? (int) $game_state['round'] : NULL;
    $turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
    $turn_index_raw = isset($turn['index']) && is_numeric($turn['index']) ? (int) $turn['index'] : NULL;

    $snapshot = [];
    if ($round_raw !== NULL) {
      $snapshot['encounter_round_raw'] = $round_raw;
    }
    if ($turn_index_raw !== NULL) {
      $snapshot['encounter_turn_index_raw'] = $turn_index_raw;
    }

    return $snapshot;
  }

  /**
   * Generate a queued reply for a non-room channel using its current transcript.
   */

  protected function generateQueuedChannelReply(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    int|string $dungeon_id,
    array &$dungeon_data,
    ?int $character_id,
    string $channel
  ): ?array {
    $channel_def = $dungeon_data['rooms'][$room_index]['channels'][$channel] ?? [];
    return $this->generateChannelNpcReply(
      $campaign_id,
      $room_id,
      $room_index,
      $dungeon_id,
      $dungeon_data,
      $character_id,
      $channel,
      $channel_def
    );
  }

  /**
   * Ensure NPC psychology profiles exist for the current room before chat.
   *
   * The tavern / starting room can be active before any room-transition logic
   * runs, which means NPC interjection logic may have no psychology profiles to
   * evaluate against. This method backfills profiles opportunistically during
   * room chat so directly addressed NPCs can speak.
   */

  protected function ensureCurrentRoomNpcProfiles(int $campaign_id, string $room_id, array $dungeon_data, int|string $room_index): void {
    $room_entities = [];

    foreach (($dungeon_data['entities'] ?? []) as $entity) {
      if (($entity['placement']['room_id'] ?? '') === $room_id) {
        $room_entities[] = $entity;
      }
    }

    foreach (($dungeon_data['rooms'][$room_index]['entities'] ?? []) as $entity) {
      $room_entities[] = $entity;
    }

    try {
      if (!empty($room_entities)) {
        $this->ensureNpcProfiles($campaign_id, $room_entities);
      }

      foreach ($this->loadRoomCampaignNpcRows($campaign_id, $room_id, $dungeon_data) as $row) {
        $this->resolveCampaignCharacterNpcProfile($campaign_id, $row);
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Room chat NPC profile ensure failed: @err', [
        '@err' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Generate a GM reply via the AI and persist it, processing mechanical actions.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|string $room_index
   *   Array index of the room in dungeon_data['rooms'].
   * @param int $dungeon_id
   *   Dungeon record ID (for DB update).
   * @param array $dungeon_data
   *   Current dungeon_data payload (already contains the player message).
   * @param int|null $character_id
   *   The acting character's ID (for mechanical state updates).
   *
   * @return array|null
   *   ['message' => array, 'state_diff' => array|null], or NULL on failure.
   */

}
