<?php

namespace Drupal\dungeoncrawler_content\Service;

trait RoomChatServiceChannelAndSessionTrait {

  protected function generateChannelNpcReply(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    int|string $dungeon_id,
    array &$dungeon_data,
    ?int $character_id,
    string $channel_key,
    array $channel_def
  ): ?array {
    // Only respond if the channel allows NPC responses.
    if (empty($channel_def['npc_responds'])) {
      return NULL;
    }

    $target_name = $channel_def['target_name'] ?? 'Unknown NPC';
    $target_entity = $channel_def['target_entity'] ?? '';
    $source_ability = $channel_def['source_ability'] ?? 'whisper';

    // Gather channel-specific chat history (only messages on this channel).
    $all_chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $channel_chat = $this->channelManager->filterMessagesByChannel($all_chat, $channel_key);
    $recent = array_slice($channel_chat, -4);

    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      if (strlen($text) > 220) {
        $text = substr($text, 0, 217) . '...';
      }
      $history_lines[] = "{$speaker}: {$text}";
    }

    // Build NPC-scoped session context from AiSessionManager.
    $ai_session_key = $this->channelManager->getAiSessionKeyForChannel($campaign_id, $channel_key);
    $session_context = $this->buildCompactSessionContext($ai_session_key, $campaign_id, 3, 900, 320);

    // Build room context.
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $scene_parts = [];
    if (!empty($room_meta['name'])) {
      $scene_parts[] = 'Current room: ' . $room_meta['name'];
    }

    // Find the live entity instance for real-time stats.
    $live_entity = [];
    $entities = $room_meta['entities'] ?? [];
    foreach ($entities as $ent) {
      $ent_ref = $ent['entity_ref']['content_id'] ?? $ent['entity_ref'] ?? '';
      $ent_name = $ent['state']['metadata']['display_name'] ?? $ent['name'] ?? '';
      if ($ent_ref === $target_entity || $ent_name === $target_name) {
        $live_entity = $ent;
        break;
      }
    }

    // Ensure this NPC has a psychology profile (auto-create if needed).
    $npc_ref = $target_entity;
    if ($live_entity && !$npc_ref) {
      $npc_ref = $live_entity['entity_ref']['content_id']
        ?? $live_entity['entity_instance_id']
        ?? $target_entity;
    }
    if ($npc_ref) {
      $seed_data = [];
      if ($live_entity) {
        $meta = $live_entity['state']['metadata'] ?? [];
        $seed_data = [
          'display_name' => $meta['display_name'] ?? $target_name,
          'creature_type' => $live_entity['entity_ref']['content_id'] ?? $npc_ref,
          'level' => $live_entity['level'] ?? ($meta['stats']['level'] ?? 1),
          'description' => $live_entity['description'] ?? ($meta['description'] ?? ''),
          'stats' => $meta['stats'] ?? [],
          'role' => $live_entity['role'] ?? 'neutral',
          'initial_attitude' => $live_entity['attitude'] ?? 'indifferent',
        ];
      }
      $this->psychologyService->getOrCreateProfile($campaign_id, $npc_ref, $seed_data);
    }

    // Build full character sheet + psychology context for the AI.
    $npc_context = '';
    if ($npc_ref) {
      $npc_context = $this->psychologyService->buildNpcContextForPrompt(
        $campaign_id,
        $npc_ref,
        $live_entity
      );
    }
    // Fallback: use description from entity if no psychology profile.
    if (empty($npc_context) && $live_entity) {
      $npc_context = $live_entity['description'] ?? '';
    }

    // Build the prompt with full NPC context.
    // TODO(actor-action-availability): Replace this descriptive tool/action text
    // with the same canonical actor action-availability envelope used by
    // encounter AI so freeform actor prompts get authoritative legal actions.
    $prompt = NpcPromptAssembler::buildDirectReplyUserPrompt(
      $session_context,
      $scene_parts,
      $npc_context,
      $target_name,
      $source_ability,
      $history_lines
    );

    $context_data = [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel_key,
      'npc_entity' => $target_entity,
      'session_key' => $ai_session_key,
    ];

    // Get NPC's current attitude for system prompt.
    $npc_attitude = 'indifferent';
    if ($npc_ref) {
      $npc_attitude = $this->psychologyService->getAttitude($campaign_id, $npc_ref);
    }

    try {
      $result = $this->invokeTimedModelCall(
        $prompt,
        'dungeoncrawler_content',
        'channel_npc_reply',
        $context_data,
        [
          'system_prompt' => NpcPromptAssembler::buildDirectReplySystemPrompt($target_name, $npc_attitude),
          'max_tokens' => 400,
          'skip_cache' => TRUE,
        ],
        [
          'channel' => $channel_key,
          'target_name' => $target_name,
          'npc_entity' => $npc_ref ?: $target_entity,
          'history_line_count' => count($history_lines),
          'session_context_length' => strlen($session_context),
          'npc_context_length' => strlen($npc_context),
        ]
      );
    }
    catch (\Throwable $e) {
      $this->logger->error('AI API error generating NPC reply on channel @channel: @msg', [
        '@channel' => $channel_key,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      return NULL;
    }

    $dialogue_payload = $this->buildCharacterDialoguePayload(
      $campaign_id,
      $room_id,
      $npc_ref !== '' ? $npc_ref : $target_entity,
      $target_name,
      $channel_key,
      'direct_reply',
      trim((string) $result['response']),
      'model',
      $target_entity !== '' ? $target_entity : NULL,
      $source_ability !== '' ? $source_ability : NULL,
      FALSE,
      TRUE
    );
    $response_text = $dialogue_payload['text'];

    $npc_message = $this->buildCharacterDialogueChatMessage($dialogue_payload);

    // Persist the NPC reply.
    $dungeon_data['rooms'][$room_index]['chat'][] = $npc_message;

    // Enforce message limit.
    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
        $chat_count - self::MAX_MESSAGES_PER_ROOM
      );
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // Record in NPC-specific AI session.
    $player_msg = end($channel_chat)['message'] ?? '';
    $this->sessionManager->appendMessage($ai_session_key, $campaign_id, 'user', $player_msg);
    $this->sessionManager->appendMessage($ai_session_key, $campaign_id, 'assistant', $response_text);

    // Bridge NPC channel reply into hierarchical session system.
    $this->bridgeChannelReplyToSessionSystem(
      $campaign_id, $room_id, $channel_key, $target_name, $target_entity, $response_text
    );
    $this->applyConversationQuestTouchpoint($campaign_id, $character_id, $room_id, $npc_ref, $target_name);

    // Record inner monologue: NPC reacts privately to what the player said.
    if ($npc_ref) {
      $player_speaker = end($channel_chat)['speaker'] ?? 'the player';
      $this->psychologyService->recordInnerMonologue(
        $campaign_id,
        $npc_ref,
        'pc_action',
        "{$player_speaker} said via {$source_ability}: \"{$player_msg}\"",
        [
          'actor' => $player_speaker,
          'severity' => 'minor',
        ]
      );
    }

    $this->logger->info('NPC @npc reply on channel @channel (@chars chars)', [
      '@npc' => $target_name,
      '@channel' => $channel_key,
      '@chars' => strlen($response_text),
    ]);

    return [
      'message' => $npc_message,
      'state_diff' => NULL,
    ];
  }

  /**
   * Ensure all NPCs in a room have psychology profiles.
   *
   * Call this on room entry to auto-create personality matrices for NPCs
   * that don't already have one. This enables full character-sheet-aware
   * inner monologues and AI portrayal from the first interaction.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_entities
   *   Entities array from dungeon_data room.
   *
   * @return int
   *   Number of new profiles created.
   */

  public function ensureNpcProfiles(int $campaign_id, array $room_entities): int {
    return $this->psychologyService->ensureRoomNpcProfiles($campaign_id, $room_entities);
  }

  /**
   * Broadcast an event to all NPCs in a room for inner monologue processing.
   *
   * Use this when a significant event occurs (combat, diplomacy, death, etc.)
   * and nearby NPCs should react internally.
   *
   * @param int $campaign_id
   * @param array $npc_entity_refs
   * @param string $event_type
   * @param string $event_description
   * @param array $context
   *
   * @return array
   */

  public function broadcastNpcEvent(int $campaign_id, array $npc_entity_refs, string $event_type, string $event_description, array $context = []): array {
    return $this->psychologyService->broadcastEventToNpcs($campaign_id, $npc_entity_refs, $event_type, $event_description, $context);
  }

  /**
   * Get available channels for a room (for the channel selector UI).
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|null $character_id
   *   Character ID to filter visibility.
   *
   * @return array
   *   ['channels' => array, 'active_channel' => string]
   */

  public function getChannelsForRoom(int $campaign_id, string $room_id, ?int $character_id = NULL): array {
    try {
      $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    }
    catch (\InvalidArgumentException $e) {
      return ['channels' => [], 'active_channel' => 'room'];
    }

    $dungeon_data = $dungeon_snapshot['dungeon_data'];
    $rooms = $dungeon_data['rooms'] ?? [];
    $room_index = $this->roomLocator->findRoomIndex($rooms, $room_id);

    if ($room_index === NULL) {
      return ['channels' => ['room' => ['key' => 'room', 'label' => 'Room', 'type' => 'room', 'active' => TRUE]], 'active_channel' => 'room'];
    }

    $channels = $this->channelManager->getChannels($dungeon_data, $room_index);
    $visible = $this->channelManager->getVisibleChannels($channels, $character_id);

    // Only return active channels.
    $active_channels = array_filter($visible, fn($ch) => $ch['active'] ?? TRUE);

    return [
      'channels' => $active_channels,
      'active_channel' => 'room',
    ];
  }

  /**
   * Open a channel in a room (delegates to ChatChannelManager).
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param string $channel_key
   *   Channel key to open.
   * @param string $opened_by
   *   Character ID that opened it.
   * @param string $target_entity_ref
   *   Target entity ref.
   * @param string $target_name
   *   Target display name.
   * @param string $source_ability
   *   Spell/ability that opens the channel.
   *
   * @return array
   *   ['success' => bool, 'channel' => array|null, 'error' => string|null]
   */

  public function openChannel(
    int $campaign_id,
    string $room_id,
    string $channel_key,
    string $opened_by,
    string $target_entity_ref,
    string $target_name,
    string $source_ability = 'whisper'
  ): array {
    try {
      $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    }
    catch (\InvalidArgumentException $e) {
      return ['success' => FALSE, 'channel' => NULL, 'error' => 'Dungeon not found'];
    }

    $dungeon_id = $dungeon_snapshot['dungeon_id'];
    $dungeon_data = $dungeon_snapshot['dungeon_data'];
    if (!isset($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'], $room_id);
    if ($room_index === NULL) {
      return ['success' => FALSE, 'channel' => NULL, 'error' => 'Room not found'];
    }

    $result = $this->channelManager->openChannel(
      $dungeon_data,
      $room_index,
      $channel_key,
      $opened_by,
      $target_entity_ref,
      $target_name,
      $source_ability
    );

    if ($result['success']) {
      // Persist the updated dungeon_data.
      $this->database->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data),
          'updated' => time(),
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();

      // Post a system message on the channel.
      $channel_def = $result['channel'];
      $system_msg = [
        'speaker' => 'System',
        'message' => sprintf('%s channel opened with %s.', $channel_def['label'] ?? 'Private', $target_name),
        'type' => 'system',
        'channel' => $channel_key,
        'timestamp' => date('c'),
        'character_id' => NULL,
        'user_id' => 0,
      ];
      $dungeon_data['rooms'][$room_index]['chat'][] = $system_msg;

      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }

    return $result;
  }

  /**
   * Close a channel in a room.
   */

  public function closeChannel(int $campaign_id, string $room_id, string $channel_key): bool {
    try {
      $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }

    $dungeon_id = $dungeon_snapshot['dungeon_id'];
    $dungeon_data = $dungeon_snapshot['dungeon_data'];
    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    if ($room_index === NULL) {
      return FALSE;
    }

    $closed = $this->channelManager->closeChannel($dungeon_data, $room_index, $channel_key);

    if ($closed) {
      $this->database->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data),
          'updated' => time(),
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }

    return $closed;
  }

  // =========================================================================
  // Session system bridge methods.
  //
  // These methods dual-write from the legacy dungeon_data JSON chat storage
  // into the new normalized dc_chat_sessions / dc_chat_messages hierarchy.
  // The NarrationEngine handles event routing, perception filtering, and
  // per-character narrative generation via the ChatSessionManager.
  //
  // This is a transitional bridge — eventually the legacy JSON path will be
  // removed and all chat flows through the session system directly.
  // =========================================================================

  /**
   * Bridge a player message from the legacy path into the session system.
   *
   * Routes the message as a room event through NarrationEngine::queueRoomEvent().
   * For player speech (room channel), this triggers immediate per-character
   * narration via GenAI. For other channels, it records the message in the
   * appropriate session.
   *
   * @param int $campaign_id
   * @param int|string $dungeon_id
   * @param string $room_id
   * @param array $dungeon_data
   *   Current dungeon_data payload.
   * @param int|string $room_index
   *   Room index in dungeon_data['rooms'].
   * @param string $speaker
   * @param string $message
   * @param string $type
   * @param int|null $character_id
   * @param string $channel
   */

  protected function bridgeToSessionSystem(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    array $dungeon_data,
    int|string $room_index,
    string $speaker,
    string $message,
    string $type,
    ?int $character_id,
    string $channel
  ): void {
    if ($this->narrationEngine === NULL) {
      return;
    }

    try {
      if ($channel === 'room') {
        // Player room chat is already the authoritative dialogue surface, so
        // bridge it directly into the normalized room session instead of
        // triggering narration generation a second time.
        if ($type === 'player' && $this->chatSessionManager !== NULL) {
          $room_session = $this->ensureCanonicalRoomSession($campaign_id, $dungeon_id, $room_id, $dungeon_data);
          $this->chatSessionManager->postMessage(
            (int) $room_session['id'],
            $campaign_id,
            $speaker,
            'player',
            $character_id ? (string) $character_id : '',
            $message,
            'dialogue',
            'public',
            [],
            TRUE
          );
          return;
        }

        // Non-player room events still route through NarrationEngine for
        // perception-filtered narration.
        $event = [
          'type' => ($type === 'player') ? 'dialogue' : 'npc_speech',
          'speaker' => $speaker,
          'speaker_type' => $type,
          'speaker_ref' => $character_id ? (string) $character_id : '',
          'content' => $message,
          'language' => 'Common',
          'volume' => 'normal',
          'perception_dc' => NULL,
          'mechanical_data' => [],
          'visibility' => 'public',
        ];

        // Build present_characters from room entities and PC.
        $present_characters = $this->buildPresentCharactersFromDungeonData(
          $dungeon_data, $room_index, $campaign_id
        );

        $this->narrationEngine->queueRoomEvent(
          $campaign_id, $dungeon_id, $room_id, $event, $present_characters
        );
      }
      else {
        // Private channel (whisper/spell): record in dedicated session.
        $this->bridgeChannelMessageToSession(
          $campaign_id, $room_id, $channel, $speaker, $type, $character_id, $message
        );
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Session bridge error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Bridge a GM reply into the session system as a narrative event.
   */

  protected function bridgeGmReplyToSessionSystem(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    string $narrative,
    array $actions = [],
    array $dice_rolls = []
  ): void {
    if ($this->chatSessionManager === NULL) {
      return;
    }

    try {
      // Prefer canonical room session resolution via dungeon_data when available,
      // but allow direct invocation (e.g., integration tests) where the campaign
      // may not yet have any dungeon rows.
      $room_session = [];
      try {
        $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
        $room_session = $this->ensureCanonicalRoomSession(
          $campaign_id,
          $dungeon_id,
          $room_id,
          $dungeon_snapshot['dungeon_data'] ?? []
        );
      }
      catch (\InvalidArgumentException $e) {
        $room_session = $this->chatSessionManager->ensureRoomSession($campaign_id, $dungeon_id, $room_id);
      }

      if ($room_session === []) {
        return;
      }

      // Post the GM narrative to the room session.
      $this->chatSessionManager->postMessage(
        (int) $room_session['id'],
        $campaign_id,
        'Game Master',
        'gm',
        '',
        $narrative,
        'narrative',
        'public',
        [
          'actions' => array_map(fn($a) => ['type' => $a['type'] ?? '', 'name' => $a['name'] ?? ''], $actions),
          'dice_rolls' => $dice_rolls,
        ],
        TRUE // feed up to dungeon + campaign
      );

      // If there were mechanical actions, also log to system log.
      if (!empty($actions) || !empty($dice_rolls)) {
        $sys_key = $this->chatSessionManager->systemLogSessionKey($campaign_id);
        $sys_session = $this->chatSessionManager->loadSession($sys_key);
        if (!$sys_session) {
          // Ensure system log exists (tests may have only created the campaign root).
          $this->chatSessionManager->ensureCampaignSessions($campaign_id);
          $sys_session = $this->chatSessionManager->loadSession($sys_key);
        }
        if ($sys_session) {
          $mechanical_summary = [];
          foreach ($actions as $a) {
            $mechanical_summary[] = ($a['name'] ?? 'Unknown') . ' (' . ($a['type'] ?? '') . ')';
          }
          foreach ($dice_rolls as $roll) {
            $label = $roll['label'] ?? 'Roll';
            $total = $roll['total'] ?? '?';
            $mechanical_summary[] = "{$label}: {$total}";
          }
          $this->chatSessionManager->postMessage(
            (int) $sys_session['id'],
            $campaign_id,
            'System',
            'system',
            '',
            implode('; ', $mechanical_summary),
            'mechanical',
            'public',
            ['actions' => $actions, 'dice_rolls' => $dice_rolls],
            FALSE
          );
        }
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Session bridge GM reply error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Bridge a channel NPC reply into the session system.
   */

  protected function bridgeChannelReplyToSessionSystem(
    int $campaign_id,
    string $room_id,
    string $channel_key,
    string $npc_name,
    string $npc_entity_ref,
    string $response_text
  ): void {
    if ($this->chatSessionManager === NULL) {
      return;
    }

    try {
      // Parse channel type from key (whisper:entity → whisper session, spell:spell_key:target → spell session).
      $parts = explode(':', $channel_key);
      $channel_type = $parts[0] ?? 'whisper';

      $session = NULL;
      if ($channel_type === 'whisper') {
        $entity_ref = $parts[1] ?? $npc_entity_ref;
        $key = $this->chatSessionManager->whisperSessionKey($campaign_id, $entity_ref);
        $session = $this->chatSessionManager->loadSession($key);
        if (!$session) {
          $root = $this->chatSessionManager->loadSession(
            $this->chatSessionManager->campaignSessionKey($campaign_id)
          );
          $session = $this->chatSessionManager->getOrCreateSession(
            $campaign_id,
            'whisper',
            $key,
            "Whisper: {$npc_name}",
            $entity_ref,
            $root ? (int) $root['id'] : NULL,
            ['target_entity' => $npc_entity_ref, 'target_name' => $npc_name]
          );
        }
      }
      elseif ($channel_type === 'spell') {
        $spell_key = $parts[1] ?? 'generic';
        $target_ref = $parts[2] ?? $npc_entity_ref;
        $key = $this->chatSessionManager->spellSessionKey($campaign_id, $spell_key, $target_ref);
        $session = $this->chatSessionManager->loadSession($key);
        if (!$session) {
          $root = $this->chatSessionManager->loadSession(
            $this->chatSessionManager->campaignSessionKey($campaign_id)
          );
          $session = $this->chatSessionManager->getOrCreateSession(
            $campaign_id,
            'spell',
            $key,
            "Spell: {$spell_key} → {$npc_name}",
            $target_ref,
            $root ? (int) $root['id'] : NULL,
            ['spell_key' => $spell_key, 'target_entity' => $npc_entity_ref]
          );
        }
      }

      if ($session) {
        $this->chatSessionManager->postMessage(
          (int) $session['id'],
          $campaign_id,
          $npc_name,
          'npc',
          $npc_entity_ref,
          $response_text,
          'dialogue',
          'private',
          [],
          TRUE // feed up to campaign root
        );
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Session bridge channel reply error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Bridge a private channel message (player side) into the session system.
   */

  protected function bridgeChannelMessageToSession(
    int $campaign_id,
    string $room_id,
    string $channel_key,
    string $speaker,
    string $type,
    ?int $character_id,
    string $message
  ): void {
    if ($this->chatSessionManager === NULL) {
      return;
    }

    try {
      $parts = explode(':', $channel_key);
      $channel_type = $parts[0] ?? 'whisper';

      $session = NULL;
      if ($channel_type === 'whisper') {
        $entity_ref = $parts[1] ?? '';
        $key = $this->chatSessionManager->whisperSessionKey($campaign_id, $entity_ref);
        $session = $this->chatSessionManager->loadSession($key);
      }
      elseif ($channel_type === 'spell') {
        $spell_key = $parts[1] ?? 'generic';
        $target_ref = $parts[2] ?? '';
        $key = $this->chatSessionManager->spellSessionKey($campaign_id, $spell_key, $target_ref);
        $session = $this->chatSessionManager->loadSession($key);
      }

      if ($session) {
        $this->chatSessionManager->postMessage(
          (int) $session['id'],
          $campaign_id,
          $speaker,
          $type,
          $character_id ? (string) $character_id : '',
          $message,
          'dialogue',
          'private',
          [],
          TRUE
        );
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Session bridge channel message error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Build the present_characters array from dungeon_data for NarrationEngine.
   *
   * Extracts PC + NPC entities in the current room and formats them into
   * the shape expected by NarrationEngine::queueRoomEvent().
   *
   * @return array
   *   Array of character descriptors for perception filtering.
   */

  protected function buildPresentCharactersFromDungeonData(
    array $dungeon_data,
    int|string $room_index,
    int $campaign_id
  ): array {
    unset($campaign_id);

    $room = $dungeon_data['rooms'][$room_index] ?? [];
    $room_id = (string) ($room['room_id'] ?? $room['id'] ?? '');
    if ($room_id === '') {
      return [];
    }

    return NarrationEngine::buildPresentCharacters($dungeon_data, $room_id);
  }

  // =========================================================================
  // Validation and sanitization.
  // =========================================================================

  /**
   * Validate message content.
   * 
   * @param string $message
   *   Message to validate.
   * @param string $type
   *   Message type.
   * 
   * @throws \InvalidArgumentException
   *   If validation fails.
   */

  protected function validateMessage(string $message, string $type): void {
    $trimmed = trim($message);
    
    if (empty($trimmed)) {
      throw new \InvalidArgumentException('Message cannot be empty');
    }

    if (strlen($trimmed) > self::MAX_MESSAGE_LENGTH) {
      throw new \InvalidArgumentException(
        sprintf('Message exceeds maximum length of %d characters', self::MAX_MESSAGE_LENGTH)
      );
    }

    $valid_types = ['player', 'npc', 'system'];
    if (!in_array($type, $valid_types, TRUE)) {
      throw new \InvalidArgumentException(
        sprintf('Invalid message type. Must be one of: %s', implode(', ', $valid_types))
      );
    }
  }

  /**
   * Sanitize message content.
   * 
   * @param string $message
   *   Raw message.
   * 
   * @return string
   *   Sanitized message.
   */

  protected function sanitizeMessage(string $message): string {
    // Trim and normalize whitespace
    $sanitized = trim($message);
    $sanitized = preg_replace('/\s+/', ' ', $sanitized);
    
    // Remove any control characters except newlines
    $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $sanitized);
    
    return substr($sanitized, 0, self::MAX_MESSAGE_LENGTH);
  }

  /**
   * Sanitize speaker name.
   * 
   * @param string $speaker
   *   Raw speaker name.
   * 
   * @return string
   *   Sanitized speaker name.
   */

  protected function sanitizeSpeakerName(string $speaker): string {
    $sanitized = trim($speaker);
    $sanitized = preg_replace('/\s+/', ' ', $sanitized);
    return substr($sanitized, 0, 100);
  }

  /**
   * Check if user has access to campaign.
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * 
   * @return bool
   *   TRUE if user has access.
   */

  public function hasCampaignAccess(int $campaign_id): bool {
    return $this->roomChatAccessGuard->hasCampaignAccess($campaign_id);
  }

  /**
   * Determine whether the current user may access one specific character.
   */

  public function hasCharacterAccess(int $campaign_id, int $character_id): bool {
    return $this->roomChatAccessGuard->hasCharacterAccess($campaign_id, $character_id);
  }

  /**
   * Enforce encounter turn ownership for player room-chat messages.
   */
  protected function validateEncounterPlayerTurnForChat(
    array $dungeon_data,
    string $channel = 'room',
    ?int $character_id = NULL,
    string $type = 'player',
    string $speaker = ''
  ): ?string {
    return $this->encounterTurnGuard->validatePlayerTurnForChat(
      $dungeon_data,
      function (string $entity_instance_id, array $payload): ?array {
        foreach (($payload['entities'] ?? []) as $entity) {
          if (!is_array($entity)) {
            continue;
          }
          $candidate = trim((string) (
            $entity['entity_instance_id']
            ?? $entity['instance_id']
            ?? $entity['state']['metadata']['runtime_entity_id']
            ?? ''
          ));
          if ($candidate !== '' && $candidate === $entity_instance_id) {
            return $entity;
          }
        }
        return NULL;
      },
      $channel,
      $character_id,
      $type,
      $speaker
    );
  }

  /**
   * Determine whether a room is currently in encounter phase.
   */

  public function isEncounterActiveForRoom(int $campaign_id, string $room_id): bool {
    try {
      $snapshot = $this->loadLatestDungeonSnapshot($campaign_id, $room_id);
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }

    $dungeon_data = is_array($snapshot['dungeon_data'] ?? NULL) ? $snapshot['dungeon_data'] : [];
    return (($dungeon_data['game_state']['phase'] ?? '') === 'encounter');
  }

  // =========================================================================
  // NPC interjection: NPCs monitor room chat and participate when motivated.
  // =========================================================================

  /**
   * Evaluate whether any NPC in the room wants to interject after a GM reply.
   *
   * Each NPC in the room has a psychology profile with personality, attitude,
   * and motivations. After each player→GM exchange, we ask the AI whether any
   * NPC is motivated to speak. This uses a single AI call that evaluates all
   * NPCs at once, returning zero or more interjections.
   *
   * NPC interjections are persisted to both dungeon_data chat and per-NPC
   * AI sessions, so NPCs maintain their own conversation memory.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|string $room_index
   *   Room index in dungeon_data.
   * @param int|string $dungeon_id
   *   Dungeon record ID.
   * @param array &$dungeon_data
   *   Dungeon data (modified in place if NPCs speak).
   * @param string $player_message
   *   The player's original message text.
   * @param string $gm_narrative
   *   The GM's reply narrative text.
   *
   * @return array
   *   Array of NPC interjection message arrays, each with:
   *   - speaker: NPC name
   *   - message: What the NPC says
   *   - type: 'npc'
   *   - channel: 'room'
   */

  protected function evaluateNpcInterjections(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    int|string $dungeon_id,
    array &$dungeon_data,
    string $player_message,
    string $gm_narrative,
    ?array $active_character_data = NULL
  ): array {
    $result = $this->runRoomTurnHarness(
      $campaign_id,
      $room_id,
      $room_index,
      $dungeon_id,
      $dungeon_data,
      $player_message,
      $gm_narrative,
      $active_character_data
    );

    return $result['messages'] ?? [];
  }

  /**
   * Run the ordered room turn harness: narrator first, GM second, then NPC turns.
   *
   * This method also emits two troubleshooting outputs:
   * - player-visible room-chat system lines (`turn_logs`) for turn order and current speaker
   * - structured rows in `dc_room_turn_logs` for durable sequence debugging
   */

  protected function runRoomTurnHarness(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    int|string $dungeon_id,
    array &$dungeon_data,
    string $player_message,
    string $gm_narrative,
    ?array $active_character_data = NULL,
    ?string $encounter_prefix = NULL
  ): array {
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    // Gather room NPCs with psychology profiles.
    $room_npcs = $this->gatherRoomNpcsWithProfiles($campaign_id, $room_id, $dungeon_data);
    $turn_log_key = uniqid('room_turn_', TRUE);

    // Initialize conversation attention state for this room.
    $room_index_for_attention = $this->getRoomIndexFromRoomId($dungeon_data, $room_id);
    $conversation_state_ref = NULL;
    if ($room_index_for_attention !== NULL) {
      $conversation_state_ref = &$this->attentionService->ensureConversationAttentionState($dungeon_data, $room_index_for_attention);
    }

    // Record player speaker in conversation attention state.
    if ($conversation_state_ref !== NULL && is_array($active_character_data)) {
      $player_character_id = (string) ($active_character_data['id'] ?? '');
      $player_display_name = (string) ($active_character_data['name'] ?? 'Player');
      $player_speaker_id = $player_character_id !== '' ? "pc:$player_character_id" : 'pc:unknown';
      $this->attentionService->recordSpeaker($conversation_state_ref, $player_speaker_id, $player_display_name, 0);
    }

    // Always derive per-speaker encounter prefixes inside this harness.
    // Caller-provided prefixes typically reflect the active player and would
    // incorrectly label System/NPC turn-log lines.
    $encounter_prefix = NULL;

    $turn_plan = $this->buildNpcTurnPlan($room_npcs, $player_message, $gm_narrative, $dungeon_data, $room_id, $turn_log_key);
    $directly_addressed_npc = $turn_plan['directly_addressed_npc'];
    $gm_addressed = !empty($turn_plan['gm_addressed']);
    $stage_started_at = hrtime(true);
    $ordered_npcs = $turn_plan['ordered_npcs'];
    $speaking_npc_ref_map = [];
    foreach (($turn_plan['speaking_npc_refs'] ?? []) as $speaking_ref) {
      $normalized_ref = trim((string) $speaking_ref);
      if ($normalized_ref !== '') {
        $speaking_npc_ref_map[$normalized_ref] = TRUE;
      }
    }
    $this->recordDebugStage('npc.candidate_filter', $stage_started_at, [
      'room_npc_count' => count($room_npcs),
      'candidate_count' => count($ordered_npcs),
      'direct_addressed' => $directly_addressed_npc['entity_ref'] ?? NULL,
      'gm_addressed' => $gm_addressed,
    ]);
    $turn_logs = [];
    $turn_sequence = $this->buildRoomTurnSequence($ordered_npcs);
    $turn_order_log = $this->buildRoomTurnOrderLogMessage($turn_sequence, $gm_addressed);
    $ordered_npc_payload = array_values(array_map(static fn(array $npc): array => [
      'entity_ref' => (string) ($npc['entity_ref'] ?? ''),
      'display_name' => (string) ($npc['profile']['display_name'] ?? $npc['entity_ref'] ?? ''),
      'initiative_total' => isset($npc['initiative_total']) ? (int) $npc['initiative_total'] : NULL,
      'initiative_roll' => isset($npc['initiative_roll']) ? (int) $npc['initiative_roll'] : NULL,
      'initiative_modifier' => isset($npc['initiative_modifier']) ? (int) $npc['initiative_modifier'] : NULL,
      ], $ordered_npcs));
    $this->persistStructuredRoomTurnLog(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $turn_log_key,
      0,
      'turn_order',
      $directly_addressed_npc['entity_ref'] ?? NULL,
      'Narrator',
      [
        'gm_addressed' => $gm_addressed,
        'directly_addressed_npc' => $directly_addressed_npc['entity_ref'] ?? NULL,
        'ordered_npcs' => $ordered_npc_payload,
        'player_message' => $player_message,
        'gm_narrative' => $gm_narrative,
      ]
    );
    if ($turn_order_log !== '') {
      $turn_logs[] = $this->appendInternalRoomLogMessage($dungeon_data, $room_index, $turn_order_log, [
        'turn_role' => 'system',
        'turn_name' => 'Turn Order',
        'turn_index' => 1,
      ], $encounter_prefix, FALSE);
    }
    $this->logger->info('Room turn order for room @room (turn @turn_key): @order', [
      '@room' => $room_id,
      '@turn_key' => $turn_log_key,
      '@order' => $turn_order_log !== '' ? $turn_order_log : '[no turn order]',
    ]);
    $this->persistStructuredRoomTurnLog(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $turn_log_key,
      1,
      'current_turn',
      NULL,
      'Narrator',
      [
        'speaker_role' => 'narrator',
        'player_message' => $player_message,
        'gm_narrative' => $gm_narrative,
      ]
    );
    $this->logger->info('Room turn current speaker in room @room (turn @turn_key): Narrator', [
      '@room' => $room_id,
      '@turn_key' => $turn_log_key,
    ]);
    $turn_logs[] = $this->appendInternalRoomLogMessage($dungeon_data, $room_index, $this->buildRoomCurrentTurnLogMessage('Narrator'), [
      'turn_role' => 'narrator',
      'turn_name' => 'Narrator',
      'turn_index' => 1,
    ], $encounter_prefix, FALSE);

    $messages = [];
    $spoken_refs = [];

    // Structured logs have a dense sequence counter; chat/UX turn indices are
    // the stable speaker order within the harness.
    $turn_log_sequence = 2;
    $harness_turn_index = 2;

    foreach ($ordered_npcs as $npc) {
      $current_speaker = (string) ($npc['profile']['display_name'] ?? $npc['entity_ref'] ?? 'Unknown');
      $current_speaker_ref = (string) ($npc['entity_ref'] ?? '');
      $speaker_can_interject = isset($speaking_npc_ref_map[$current_speaker_ref]);
      $current_turn_index = $harness_turn_index++;

      $npc_encounter_prefix = (($game_state['phase'] ?? '') === 'encounter')
        ? $this->encounterTranscriptPrefixService->buildForSpeaker(
          $dungeon_data,
          $current_speaker,
          fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
        )
        : NULL;

      $this->persistStructuredRoomTurnLog(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $turn_log_key,
        $turn_log_sequence++,
        'current_turn',
        (string) ($npc['entity_ref'] ?? ''),
        $current_speaker,
        [
          'speaker_role' => 'npc',
          'can_interject' => $speaker_can_interject,
          'player_message' => $player_message,
          'gm_narrative' => $gm_narrative,
          'initiative_total' => isset($npc['initiative_total']) ? (int) $npc['initiative_total'] : NULL,
          'initiative_roll' => isset($npc['initiative_roll']) ? (int) $npc['initiative_roll'] : NULL,
          'initiative_modifier' => isset($npc['initiative_modifier']) ? (int) $npc['initiative_modifier'] : NULL,
        ]
      );
      $turn_logs[] = $this->appendInternalRoomLogMessage(
        $dungeon_data,
        $room_index,
        $this->buildRoomCurrentTurnLogMessage($current_speaker),
        [
          'turn_role' => 'npc',
          'turn_name' => $current_speaker,
          'turn_index' => $current_turn_index,
          'initiative_total' => isset($npc['initiative_total']) ? (int) $npc['initiative_total'] : NULL,
          'initiative_roll' => isset($npc['initiative_roll']) ? (int) $npc['initiative_roll'] : NULL,
          'initiative_modifier' => isset($npc['initiative_modifier']) ? (int) $npc['initiative_modifier'] : NULL,
        ],
        NULL,
        FALSE
      );
      $this->logger->info('Room turn current speaker in room @room (turn @turn_key): @speaker', [
        '@room' => $room_id,
        '@turn_key' => $turn_log_key,
        '@speaker' => $current_speaker,
      ]);
      $built_messages = [];
      if ($speaker_can_interject) {
        $built_messages = $this->buildNpcInterjectionMessage(
          $campaign_id,
          $room_id,
          $room_index,
          $dungeon_id,
          $dungeon_data,
          $player_message,
          $gm_narrative,
          $room_npcs,
          $current_speaker_ref,
          $npc['profile']['display_name'] ?? $current_speaker_ref,
          FALSE,
          $npc_encounter_prefix
        );
      }

        if (!empty($built_messages)) {
          $messages = array_merge($messages, $built_messages);
          $spoken_refs[] = $current_speaker_ref;

          // Record NPC speaker in conversation attention state
          if ($conversation_state_ref !== NULL) {
            $npc_display_name = (string) ($npc['profile']['display_name'] ?? $current_speaker_ref);
            $this->attentionService->recordSpeaker($conversation_state_ref, (string) $current_speaker_ref, $npc_display_name, 0);
            $this->attentionService->incrementFatiguePenalty($conversation_state_ref, (string) $current_speaker_ref);
          }

        $this->persistStructuredRoomTurnLog(
          $campaign_id,
          $dungeon_id,
          $room_id,
          $turn_log_key,
            $turn_log_sequence++,
            'speaker_completed',
            (string) ($npc['entity_ref'] ?? ''),
            $current_speaker,
            [
              'spoke' => TRUE,
              'message_count' => count($built_messages),
            'message_preview' => (string) (($built_messages[0]['message'] ?? '')),
            'can_interject' => TRUE,
          ]
        );
      }
      else {
        $this->persistStructuredRoomTurnLog(
          $campaign_id,
          $dungeon_id,
          $room_id,
          $turn_log_key,
            $turn_log_sequence++,
            'speaker_completed',
            (string) ($npc['entity_ref'] ?? ''),
            $current_speaker,
            [
              'spoke' => FALSE,
              'message_count' => 0,
             'can_interject' => $speaker_can_interject,
          ]
        );
      }
    }

    $player_label = trim((string) (
      $active_character_data['name']
      ?? $active_character_data['character_name']
      ?? $active_character_data['label']
      ?? $active_character_data['display_name']
      ?? ''
    ));
    if ($player_label === '') {
      $player_label = 'Player';
    }

    $this->persistStructuredRoomTurnLog(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $turn_log_key,
      $turn_log_sequence++,
      'current_turn',
      $active_character_data['character_id'] ?? NULL,
      $player_label,
      [
        'speaker_role' => 'player',
        'player_message' => $player_message,
        'gm_narrative' => $gm_narrative,
      ]
    );
    $turn_logs[] = $this->appendRoomSystemMessage(
      $dungeon_data,
      $room_index,
      $this->buildRoomCurrentTurnLogMessage($player_label),
      [
        'turn_role' => 'player',
        'turn_name' => $player_label,
        'turn_index' => $harness_turn_index,
        'turn_prompt' => TRUE,
      ],
      NULL,
      FALSE
    );
    $harness_turn_index++;

    if (empty($messages)) {
      $this->feedRoomChatToNpcSessions(
        $campaign_id,
        $room_npcs,
        $player_message,
        $gm_narrative,
        NULL,
        $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? [])
      );
      return $this->buildRoomTurnHarnessPayload([
        'player' => ['message' => $player_message],
        'gm' => ['narrative' => $gm_narrative],
        'gm_addressed' => $gm_addressed,
        'directly_addressed_npc' => $directly_addressed_npc['entity_ref'] ?? NULL,
        'npc_turns' => array_values(array_map(static fn(array $npc): array => [
          'entity_ref' => (string) ($npc['entity_ref'] ?? ''),
          'display_name' => (string) ($npc['profile']['display_name'] ?? $npc['entity_ref'] ?? ''),
          'spoke' => FALSE,
        ], $ordered_npcs)),
        'turn_log_key' => $turn_log_key,
        'turn_sequence' => $turn_sequence,
        'turn_logs' => $turn_logs,
        'messages' => [],
      ]);
    }

    $this->feedRoomChatToNpcSessions(
      $campaign_id,
      $room_npcs,
      $player_message,
      $gm_narrative,
      $spoken_refs,
      $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? [])
    );
    return $this->buildRoomTurnHarnessPayload([
      'player' => ['message' => $player_message],
      'gm' => ['narrative' => $gm_narrative],
      'gm_addressed' => $gm_addressed,
      'directly_addressed_npc' => $directly_addressed_npc['entity_ref'] ?? NULL,
      'turn_log_key' => $turn_log_key,
      'turn_sequence' => $turn_sequence,
      'npc_turns' => array_values(array_map(static function (array $npc) use ($spoken_refs): array {
        $entity_ref = (string) ($npc['entity_ref'] ?? '');
        return [
          'entity_ref' => $entity_ref,
          'display_name' => (string) ($npc['profile']['display_name'] ?? $entity_ref),
          'spoke' => in_array($entity_ref, $spoken_refs, TRUE),
          'initiative_total' => isset($npc['initiative_total']) ? (int) $npc['initiative_total'] : NULL,
          'initiative_roll' => isset($npc['initiative_roll']) ? (int) $npc['initiative_roll'] : NULL,
          'initiative_modifier' => isset($npc['initiative_modifier']) ? (int) $npc['initiative_modifier'] : NULL,
        ];
      }, $ordered_npcs)),
      'turn_logs' => $turn_logs,
      'messages' => $messages,
    ]);
  }

  /**
   * Build an ordered NPC turn plan for the current room round.
   */

  protected function buildNpcTurnPlan(
    array $room_npcs,
    string $player_message,
    string $gm_narrative,
    array $dungeon_data = [],
    string $room_id = '',
    string $turn_seed = ''
  ): array {
    $directly_addressed_npc = $this->resolveDirectlyAddressedNpc($room_npcs, $player_message);
    $gm_addressed = $this->isExplicitRoomGmAddress($player_message);
    $conversation_state_ref = NULL;
    $active_conversation_npc = NULL;
    $persisted_conversation_npc = NULL;
    $room_meta = [];
    if ($room_id !== '') {
      $room_index = $this->getRoomIndexFromRoomId($dungeon_data, $room_id);
      if ($room_index !== NULL) {
        $room_meta = is_array($dungeon_data['rooms'][$room_index] ?? NULL) ? $dungeon_data['rooms'][$room_index] : [];
      }
    }
    $persisted_conversation_npc = $this->resolveExplicitRoomConversationNpc($room_meta, $room_npcs);
    $active_conversation_npc = $persisted_conversation_npc;
    if ($active_conversation_npc === NULL && is_array($room_meta['chat'] ?? NULL)) {
      $active_conversation_npc = $this->resolveActiveDirectConversationNpc($room_meta['chat'], $room_npcs);
    }
    $continued_conversation = FALSE;
    if ($directly_addressed_npc === NULL && $active_conversation_npc !== NULL) {
      $normalized_message = $this->normalizeNpcNameForMatch($player_message);
      if ($this->shouldContinueActiveRoomConversation($player_message, $normalized_message, $active_conversation_npc)) {
        $directly_addressed_npc = $active_conversation_npc;
        $continued_conversation = TRUE;
      }
    }

    $ordered_npcs = $this->buildRoomNpcInitiativeOrder($room_npcs, $dungeon_data, $room_id, $turn_seed);
    if ($gm_addressed) {
      $speaking_npcs = [];
      $plan_source = 'gm_addressed';
    }
    else {
      $speaking_npcs = $this->filterAmbientNpcInterjectionOrder($ordered_npcs, $player_message, $gm_narrative, $dungeon_data, $room_id, $turn_seed, $conversation_state_ref);
      $plan_source = $directly_addressed_npc !== NULL
        ? ($continued_conversation ? 'active_conversation' : 'direct_plus_room')
        : 'room_wide';
      if ($directly_addressed_npc !== NULL) {
        $direct_ref = (string) ($directly_addressed_npc['entity_ref'] ?? '');
        $has_direct = FALSE;
        foreach ($speaking_npcs as $npc) {
          if ((string) ($npc['entity_ref'] ?? '') === $direct_ref) {
            $has_direct = TRUE;
            break;
          }
        }
        if (!$has_direct) {
          array_unshift($speaking_npcs, $directly_addressed_npc);
        }
      }
    }

    $speaking_npc_refs = array_values(array_filter(array_map(
      static fn(array $npc): string => (string) ($npc['entity_ref'] ?? ''),
      $speaking_npcs
    )));

    $turn_plan_meta = [
      'source' => $plan_source,
      'room_id' => $room_id,
      'directly_addressed_npc' => $directly_addressed_npc['entity_ref'] ?? NULL,
      'persisted_conversation_npc' => $persisted_conversation_npc['entity_ref'] ?? NULL,
      'active_conversation_npc' => $active_conversation_npc['entity_ref'] ?? NULL,
      'continued_conversation' => $continued_conversation,
      'gm_addressed' => $gm_addressed,
      'ordered_npc_count' => count($ordered_npcs),
      'ordered_npcs' => array_values(array_map(static fn(array $npc): string => (string) ($npc['entity_ref'] ?? ''), $ordered_npcs)),
      'speaking_npc_count' => count($speaking_npc_refs),
      'speaking_npc_refs' => $speaking_npc_refs,
      'player_message' => $this->truncateContextBlock($player_message, 140, 0.85),
      'gm_narrative' => $this->truncateContextBlock($gm_narrative, 140, 0.85),
    ];
    $this->recordDebugStage('npc.turn_plan', hrtime(true), $turn_plan_meta);
    $this->logger->info('Room turn plan resolved via @source for room @room (direct=@direct active=@active gm=@gm ordered=@ordered)', [
      '@source' => $plan_source,
      '@room' => $room_id !== '' ? $room_id : '[unknown]',
      '@direct' => $turn_plan_meta['directly_addressed_npc'] ?? 'none',
      '@active' => $turn_plan_meta['active_conversation_npc'] ?? 'none',
      '@gm' => $gm_addressed ? 'yes' : 'no',
      '@ordered' => implode(',', array_filter($turn_plan_meta['ordered_npcs'] ?? [])) ?: 'none',
    ]);

    return [
      'directly_addressed_npc' => $directly_addressed_npc,
      'active_conversation_npc' => $active_conversation_npc,
      'gm_addressed' => $gm_addressed,
      'ordered_npcs' => $speaking_npcs,
      'speaking_npc_refs' => $speaking_npc_refs,
    ];
  }

  /**
   * Determine whether the player is explicitly addressing the GM in room chat.
   */

  protected function isExplicitRoomGmAddress(string $player_message): bool {
    return (bool) preg_match('/(?:^|[.!?]\s+|,\s*)(?:gm|game master)\b\s*[,:-]?/iu', trim($player_message));
  }

  /**
   * Build the full room NPC speaking order for the current round.
   */

  protected function buildRoomNpcInitiativeOrder(array $room_npcs, array $dungeon_data = [], string $room_id = '', string $turn_seed = ''): array {
    if ($room_npcs === []) {
      return [];
    }

    $initiative_order = is_array($dungeon_data['game_state']['initiative_order'] ?? NULL)
      ? $dungeon_data['game_state']['initiative_order']
      : [];
    $ordered_npcs = [];
    $seen_refs = [];

    foreach ($initiative_order as $participant) {
      if (!is_array($participant)) {
        continue;
      }

      $matched_npc = $this->matchRoomNpcFromInitiativeParticipant($room_npcs, $participant, $room_id);
      if ($matched_npc === NULL) {
        continue;
      }

      $entity_ref = (string) ($matched_npc['entity_ref'] ?? '');
      if ($entity_ref === '' || isset($seen_refs[$entity_ref])) {
        continue;
      }

      $ordered_npcs[] = $this->decorateRoomNpcWithInitiative($matched_npc, $participant, $turn_seed);
      $seen_refs[$entity_ref] = TRUE;
    }

    foreach ($room_npcs as $npc) {
      $entity_ref = (string) ($npc['entity_ref'] ?? '');
      if ($entity_ref === '' || isset($seen_refs[$entity_ref])) {
        continue;
      }

      $ordered_npcs[] = $this->decorateRoomNpcWithInitiative($npc, [], $turn_seed);
      $seen_refs[$entity_ref] = TRUE;
    }

    usort($ordered_npcs, static function (array $left, array $right): int {
      $initiative_diff = (int) ($right['initiative_total'] ?? 0) - (int) ($left['initiative_total'] ?? 0);
      if ($initiative_diff !== 0) {
        return $initiative_diff;
      }

      $modifier_diff = (int) ($right['initiative_modifier'] ?? 0) - (int) ($left['initiative_modifier'] ?? 0);
      if ($modifier_diff !== 0) {
        return $modifier_diff;
      }

      return strcmp((string) ($left['entity_ref'] ?? ''), (string) ($right['entity_ref'] ?? ''));
    });

    return $ordered_npcs;
  }

  /**
   * Reduce off-topic side chatter using a Charisma-derived % gate.
   *
   * Directed or clearly referenced NPCs bypass this gate entirely. Ambient NPCs
   * must pass a deterministic per-turn roll against their chatter threshold.
   *
   * @param array<int, array<string, mixed>> $ordered_npcs
   *   Initiative-ordered room NPC rows.
   *
   * @return array<int, array<string, mixed>>
   *   Filtered initiative order for ambient interjection evaluation.
   */

}
