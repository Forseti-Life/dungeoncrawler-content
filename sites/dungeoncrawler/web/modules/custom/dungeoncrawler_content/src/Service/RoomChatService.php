<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
use Psr\Log\LoggerInterface;

// Hierarchical chat session integration.
// These bridge legacy dungeon_data JSON chat into the normalized session tables.

/**
 * Manages room chat messages with proper state management.
 * 
 * Uses DungeonStateService for optimistic locking to prevent race conditions.
 */
class RoomChatService {

  const MAX_MESSAGE_LENGTH = 2000;
  const MAX_MESSAGES_PER_ROOM = 500;

  protected Connection $database;
  protected DungeonStateService $dungeonStateService;
  protected LoggerInterface $logger;
  protected AccountProxyInterface $currentUser;
  protected AIApiService $aiApiService;
  protected PromptManager $promptManager;
  protected GameplayActionProcessor $actionProcessor;
  protected AiSessionManager $sessionManager;
  protected ChatChannelManager $channelManager;
  protected NpcPsychologyService $psychologyService;
  protected ?NarrationEngine $narrationEngine;
  protected ?ChatSessionManager $chatSessionManager;
  protected ?MapGeneratorService $mapGenerator;
  protected CanonicalActionRegistryService $canonicalActionRegistry;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    DungeonStateService $dungeon_state_service,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user,
    AIApiService $ai_api_service,
    PromptManager $prompt_manager,
    GameplayActionProcessor $action_processor,
    AiSessionManager $session_manager,
    ChatChannelManager $channel_manager,
    NpcPsychologyService $psychology_service,
    ?NarrationEngine $narration_engine = NULL,
    ?ChatSessionManager $chat_session_manager = NULL,
    ?MapGeneratorService $map_generator = NULL,
    ?CanonicalActionRegistryService $canonical_action_registry = NULL
  ) {
    $this->database = $database;
    $this->dungeonStateService = $dungeon_state_service;
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
    $this->currentUser = $current_user;
    $this->aiApiService = $ai_api_service;
    $this->promptManager = $prompt_manager;
    $this->actionProcessor = $action_processor;
    $this->sessionManager = $session_manager;
    $this->channelManager = $channel_manager;
    $this->psychologyService = $psychology_service;
    $this->narrationEngine = $narration_engine;
    $this->chatSessionManager = $chat_session_manager;
    $this->mapGenerator = $map_generator;
    $this->canonicalActionRegistry = $canonical_action_registry ?? new CanonicalActionRegistryService($database, $current_user);
  }

  /**
   * Get chat history for a room.
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * 
   * @return array
   *   Array of chat messages.
   * 
   * @throws \InvalidArgumentException
   *   If dungeon not found.
   */
  public function getChatHistory(int $campaign_id, string $room_id, string $channel = 'room', ?int $character_id = NULL): array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Dungeon not found', 404);
    }

    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);
    if (!is_array($dungeon_data)) {
      $dungeon_data = [];
    }

    $rooms = $dungeon_data['rooms'] ?? [];
    $room_entry = $this->findRoomByRoomId($rooms, $room_id);
    $chat = $room_entry['chat'] ?? [];

    // Filter by channel.
    $chat = $this->channelManager->filterMessagesByChannel($chat, $channel);

    // For non-room channels, verify the character has access.
    if ($channel !== 'room' && $character_id !== NULL) {
      $room_index = $this->findRoomIndex($rooms, $room_id);
      if ($room_index !== NULL) {
        $channels = $this->channelManager->getChannels($dungeon_data, $room_index);
        if (isset($channels[$channel])) {
          $access = $this->channelManager->validateChannelAccess($channels[$channel], $character_id);
          if (!$access['valid']) {
            return [];
          }
        }
      }
    }

    // Ensure messages are properly structured
    return array_map(function($msg) {
      return [
        'speaker' => $msg['speaker'] ?? 'Unknown',
        'message' => $msg['message'] ?? '',
        'type' => $msg['type'] ?? 'npc',
        'channel' => $msg['channel'] ?? 'room',
        'timestamp' => $msg['timestamp'] ?? date('c'),
        'character_id' => $msg['character_id'] ?? null,
        'user_id' => $msg['user_id'] ?? null,
      ];
    }, $chat);
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
    string $channel = 'room'
  ): array {
    // Validate inputs
    $this->validateMessage($message, $type);

    // Load current dungeon data (need dungeon_id)
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Dungeon not found', 404);
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);
    if (!is_array($dungeon_data)) {
      $dungeon_data = [];
    }

    // Initialize rooms structure if needed
    if (!isset($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    // Find the room index — rooms may be keyed by room_id or numerically indexed.
    $room_index = $this->findRoomIndex($dungeon_data['rooms'], $room_id);
    if ($room_index === NULL) {
      // Room doesn't exist yet; append a new entry.
      $dungeon_data['rooms'][] = ['room_id' => $room_id, 'chat' => []];
      $room_index = array_key_last($dungeon_data['rooms']);
    }
    if (!isset($dungeon_data['rooms'][$room_index]['chat'])) {
      $dungeon_data['rooms'][$room_index]['chat'] = [];
    }

    // Validate channel access for non-room channels.
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
      'user_id' => $this->currentUser->id(),
    ];

    // Append message
    $dungeon_data['rooms'][$room_index]['chat'][] = $new_message;

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

    // Log chat activity
    $this->logger->info('Chat message posted in room @room by user @uid: @message', [
      '@room' => $room_id,
      '@uid' => $this->currentUser->id(),
      '@message' => substr($message, 0, 100),
    ]);

    // Bridge into the hierarchical chat session system.
    // This dual-writes to the normalized dc_chat_messages table via NarrationEngine.
    $this->bridgeToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $dungeon_data, $room_index,
      $speaker, $message, $type, $character_id, $channel
    );

    // Generate AI response (GM for room channel, NPC for private channels).
    $gm_response = NULL;
    $state_diff = NULL;
    $navigation = NULL;
    $npc_interjections = [];
    if ($type === 'player') {
      if ($channel === 'room') {
        $this->ensureCurrentRoomNpcProfiles($campaign_id, $room_id, $dungeon_data, $room_index);
        // Room channel: GM responds.
        $gm_result = $this->generateGmReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id);
      } else {
        // Private channel: target NPC responds.
        $channel_def = $dungeon_data['rooms'][$room_index]['channels'][$channel] ?? [];
        $gm_result = $this->generateChannelNpcReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id, $channel, $channel_def);
      }
      if ($gm_result !== NULL) {
        $gm_response = $gm_result['message'];
        $state_diff = $gm_result['state_diff'] ?? NULL;
        $navigation = $gm_result['navigation'] ?? NULL;
      }

      // After GM replies on the room channel, evaluate NPC interjections.
      // Room NPCs monitor the conversation and may chime in if motivated.
      if ($channel === 'room' && $gm_response !== NULL) {
        $npc_interjections = $this->evaluateNpcInterjections(
          $campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $message, $gm_response['message'] ?? '', $char_data
        );
      }
    }

    $result = [
      'message' => $new_message,
      'totalMessages' => count($dungeon_data['rooms'][$room_index]['chat']),
    ];
    if ($gm_response !== NULL) {
      $result['gm_response'] = $gm_response;
    }
    if ($state_diff !== NULL) {
      $result['state_diff'] = $state_diff;
    }
    if (!empty($npc_interjections)) {
      $result['npc_interjections'] = $npc_interjections;
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
    if ($navigation !== NULL && empty($navigation['error'])) {
      $result['navigation'] = $this->buildClientNavigationPayload(
        $navigation, $campaign_id, $dungeon_data
      );
    }
    return $result;
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
    catch (\Exception $e) {
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
  protected function generateGmReply(int $campaign_id, string $room_id, int|string $room_index, int|string $dungeon_id, array &$dungeon_data, ?int $character_id = NULL): ?array {
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];

    // Build scene context from the room definition.
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $scene_parts = [];
    if (!empty($room_meta['name'])) {
      $scene_parts[] = 'Current room: ' . $room_meta['name'];
    }
    if (!empty($room_meta['description'])) {
      $scene_parts[] = 'Room description: ' . $room_meta['description'];
    }

    // Add brief room inventory summary to user prompt context.
    $entities = $room_meta['entities'] ?? [];
    $entity_names = [];
    foreach (array_slice($entities, 0, 10) as $ent) {
      $ename = $ent['state']['metadata']['display_name']
        ?? $ent['name']
        ?? NULL;
      if ($ename) {
        $etype = $ent['type'] ?? 'npc';
        $entity_names[] = "{$ename} ({$etype})";
      }
    }
    if (!empty($entity_names)) {
      $scene_parts[] = 'Beings/objects present: ' . implode(', ', $entity_names);
    }

    // Build the user prompt from recent chat history (last 10 messages).
    $recent = array_slice($chat, -10);
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      $history_lines[] = "{$speaker}: {$text}";
    }

    // Build session context scoped to this room so NPC conversations from
    // previous rooms do not bleed into the current room's AI context.
    $session_key = $this->sessionManager->roomChatSessionKey($campaign_id, $room_id);
    $session_context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 6);

    $prompt = '';
    if ($session_context !== '') {
      $prompt .= $session_context . "\n\n---\n";
    }
    if (!empty($scene_parts)) {
      $prompt .= implode("\n", $scene_parts) . "\n\n";
    }
    $prompt .= "Recent conversation:\n" . implode("\n", $history_lines);
    if ($is_room_entry) {
      $prompt .= "\n\nTHIS IS A ROOM ENTRY — respond as the Game Master with a full environmental description as required by the ROOM ENTRY NARRATION RULES in your system prompt. Cover atmosphere, sight, sound, smell/taste, and all NPCs/creatures present (appearance + demeanour, no names yet). After the description, then address whatever the player said. Include the JSON action block only if the player triggered a mechanical action.";
    }
    else {
      $prompt .= "\n\nRespond in character as the Game Master. Keep your reply concise (2-4 sentences). If the player is performing a mechanical action (casting a spell, using a skill, using a feat, attacking, exploring), include the JSON action block as instructed in your system prompt.";
    }
    $prompt .= "\nIMPORTANT: Do NOT write dialogue for any NPC. Describe the scene, NPC body language and reactions, but let NPCs speak for themselves. Never put words in an NPC's mouth.";

    // Build enhanced system prompt with character abilities if character_id is available.
    $base_system_prompt = $this->promptManager->getBaseSystemPrompt();
    $system_prompt = $base_system_prompt;

    // Ensure room connections are backfilled from hex_map for older campaigns.
    if ($this->mapGenerator) {
      $this->mapGenerator->backfillRoomConnections($dungeon_data);
    }

    // Build full room inventory for GM awareness.
    $room_inventory = $this->actionProcessor->buildRoomInventory(
      $campaign_id, $room_id, $room_meta, $dungeon_data
    );

    $char_data = NULL;
    if ($character_id) {
      $char_data = $this->actionProcessor->loadCharacterData($character_id);
      if ($char_data) {
        $system_prompt = $this->actionProcessor->buildEnhancedSystemPrompt(
          $base_system_prompt,
          $char_data,
          $room_meta,
          $room_inventory,
          $dungeon_data,
          $room_index
        );
      }
    }

    $context_data = [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'session_key' => $session_key,
    ];

    $checked_response = $this->generateRealityCheckedGmResponse(
      $prompt,
      $system_prompt,
      $context_data,
      $campaign_id,
      $room_id,
      $character_id,
      $char_data,
      $room_inventory
    );
    if ($checked_response === NULL) {
      return NULL;
    }

    $narrative = $checked_response['narrative'] ?? '';
    $actions = $checked_response['actions'] ?? [];
    $dice_rolls = $checked_response['dice_rolls'] ?? [];
    $validation_errors = $checked_response['validation_errors'] ?? [];

    // Parse and process any [CREATE_SUGGESTION] tag the GM embedded.
    if (preg_match('/\[CREATE_SUGGESTION\](.*?)\[\/CREATE_SUGGESTION\]/s', $narrative, $suggestion_matches)) {
      $suggestion_text = $suggestion_matches[1];
      $s_summary  = '';
      $s_category = 'general_feedback';
      $s_original = end($chat)['message'] ?? '';
      if (preg_match('/Summary:\s*(.+?)(?=\nCategory:|\nOriginal:|$)/s', $suggestion_text, $m)) {
        $s_summary = trim($m[1]);
      }
      if (preg_match('/Category:\s*(\w+)/i', $suggestion_text, $m)) {
        $s_category = strtolower(trim($m[1]));
      }
      if (preg_match('/Original:\s*(.+?)$/s', $suggestion_text, $m)) {
        $s_original = trim($m[1]);
      }
      if (!empty($s_summary)) {
        $this->aiApiService->createBacklogSuggestion(
          $s_summary, $s_original, $s_category,
          ['campaign_id' => $campaign_id, 'room_id' => $room_id]
        );
      }
      // Strip the tag from the player-visible narrative.
      $narrative = trim(preg_replace('/\[CREATE_SUGGESTION\].*?\[\/CREATE_SUGGESTION\]/s', '', $narrative));
    }

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
      $canonical_execution = $this->executeCanonicalAuthoritativeActions(
        $campaign_id,
        $room_id,
        $room_meta,
        $character_id,
        $actions,
        $dungeon_data
      );
      $actions = $canonical_execution['actions'] ?? $actions;
      $canonical_results = $canonical_execution['results'] ?? $canonical_results;
      if (!empty($canonical_execution['errors'])) {
        $validation_errors = array_merge($validation_errors, $canonical_execution['errors']);
      }
      if (!empty($canonical_execution['reloaded_dungeon_data']) && is_array($canonical_execution['reloaded_dungeon_data'])) {
        $dungeon_data = $canonical_execution['reloaded_dungeon_data'];
      }
    }

    // Apply state mutations if there are mechanical actions.
    $char_diff = [];
    $room_diff = [];
    $state_diff = NULL;

    if (!empty($actions)) {
      // Apply character state changes.
      if ($character_id) {
        $char_diff = $this->actionProcessor->applyCharacterStateChanges($character_id, $actions, $campaign_id);
      }

      // Apply room/dungeon state changes.
      $room_diff = $this->actionProcessor->applyRoomStateChanges(
        $dungeon_id, $campaign_id, $room_index, $dungeon_data, $actions
      );

      // Build the state diff summary for the client.
      $state_diff = $this->actionProcessor->buildStateDiffSummary(
        $char_diff, $room_diff, $dice_rolls, $actions, $validation_errors
      );

      $this->logger->info('Mechanical actions processed: @count actions, @rolls dice rolls', [
        '@count' => count($actions),
        '@rolls' => count($dice_rolls),
      ]);

      $this->recordCanonicalActionBatch($campaign_id, $actions, 'executed', [
        'room_id' => $room_id,
        'character_id' => $character_id,
      ]);
    }
    elseif (!empty($validation_errors)) {
      $state_diff = $this->actionProcessor->buildStateDiffSummary(
        $char_diff, $room_diff, $dice_rolls, $actions, $validation_errors
      );
    }

    // Detect navigate_to_location actions and trigger map generation.
    $navigation_result = NULL;
    if (!empty($actions)) {
      $navigation_result = $this->handleNavigationActions(
        $actions, $campaign_id, $room_id, $dungeon_data, $narrative
      );

      // If navigation was successful, MapGeneratorService persisted its own
      // copy of dungeon_data with the new room/entities/connections. Adopt
      // the updated version so our subsequent persist doesn't clobber it.
      if ($navigation_result && empty($navigation_result['error']) && !empty($navigation_result['dungeon_data'])) {
        $dungeon_data = $navigation_result['dungeon_data'];
        // Re-resolve room_index since dungeon_data was replaced.
        $room_index = $this->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
        if ($room_index === NULL) {
          $room_index = 0;
        }
      }

      // Record location transition in dungeon_data for GM context.
      if ($navigation_result && empty($navigation_result['error'])) {
        $this->recordLocationTransition($dungeon_data, $room_meta, $navigation_result);
      }
    }

    $gm_message = [
      'speaker' => 'Game Master',
      'message' => $narrative,
      'type' => 'npc',
      'channel' => 'room',
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
    ];

    // If there were mechanical actions, attach a summary to the message.
    if (!empty($actions)) {
      $gm_message['mechanical_actions'] = array_map(function($a) {
        return [
          'type' => $a['type'] ?? 'unknown',
          'name' => $a['name'] ?? 'Unknown',
        ];
      }, $actions);
      if (!empty($dice_rolls)) {
        $gm_message['dice_rolls'] = $dice_rolls;
      }
    }

    // Persist the GM reply (and any dungeon_data state changes from actions).
    $dungeon_data['rooms'][$room_index]['chat'][] = $gm_message;

    // Enforce message limit again.
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

    // Record this exchange in the campaign room chat session for future context.
    $player_msg_text = end($chat)['message'] ?? '';
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $player_msg_text);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $narrative);

    // Bridge GM reply into hierarchical session system.
    $this->bridgeGmReplyToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $narrative, $actions, $dice_rolls
    );

    $this->logger->info('GM reply persisted in room @room (@chars chars, @actions_count mechanical actions)', [
      '@room' => $room_id,
      '@chars' => strlen($narrative),
      '@actions_count' => count($actions),
    ]);

    return [
      'message' => $gm_message,
      'state_diff' => $state_diff,
      'navigation' => $navigation_result,
      'canonical_actions' => $canonical_results,
    ];
  }

  /**
   * Generate a GM response and run centralized reality validation with retry.
   *
   * If the generated mechanics fail the authoritative resource checks, the
   * model receives a second prompt containing the validated state snapshot and
   * must regenerate before the text is finalized.
   */
  protected function generateRealityCheckedGmResponse(
    string $prompt,
    string $system_prompt,
    array $context_data,
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    ?array $character_data,
    array $room_inventory
  ): ?array {
    $attempt = $this->invokeGmModel($prompt, $system_prompt, $context_data, $room_id);
    if ($attempt === NULL) {
      return NULL;
    }

    $parsed = $this->actionProcessor->parseResponse($attempt);
    $actions = $parsed['actions'] ?? [];
    $validation_errors = [];

    $this->recordCanonicalActionBatch($campaign_id, $actions, 'proposed', [
      'room_id' => $room_id,
      'character_id' => $character_id,
      'attempt' => 1,
    ]);

    if (!empty($actions) && $character_id) {
      $validation = $this->actionProcessor->validateCharacterActionResources($character_id, $actions, $campaign_id);
      $actions = $validation['actions'] ?? [];
      $validation_errors = $validation['errors'] ?? [];

      if (!empty($validation_errors)) {
        $snapshot = $this->actionProcessor->buildRealitySnapshot($character_data, $room_inventory);
        $retry_prompt = $prompt . "\n\n---\n" . $this->actionProcessor->buildRealityRetryPrompt($validation_errors, $snapshot);
        $retry_context = $context_data + [
          'reality_retry' => 1,
          'campaign_id' => $campaign_id,
        ];

        $retry = $this->invokeGmModel($retry_prompt, $system_prompt, $retry_context, $room_id);
        if ($retry !== NULL) {
          $retry_parsed = $this->actionProcessor->parseResponse($retry);
          $retry_actions = $retry_parsed['actions'] ?? [];
          $retry_validation_errors = [];

          $this->recordCanonicalActionBatch($campaign_id, $retry_actions, 'proposed_retry', [
            'room_id' => $room_id,
            'character_id' => $character_id,
            'attempt' => 2,
          ]);

          if (!empty($retry_actions) && $character_id) {
            $retry_validation = $this->actionProcessor->validateCharacterActionResources($character_id, $retry_actions, $campaign_id);
            $retry_actions = $retry_validation['actions'] ?? [];
            $retry_validation_errors = $retry_validation['errors'] ?? [];
          }

          if (empty($retry_validation_errors)) {
            return [
              'narrative' => $retry_parsed['narrative'] ?? '',
              'actions' => $retry_actions,
              'dice_rolls' => $retry_parsed['dice_rolls'] ?? [],
              'validation_errors' => [],
            ];
          }

          $validation_errors = $retry_validation_errors;
          $parsed = $retry_parsed;
          $actions = [];
        }
        else {
          $actions = [];
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
    }

    return [
      'narrative' => $parsed['narrative'] ?? '',
      'actions' => $actions,
      'dice_rolls' => $parsed['dice_rolls'] ?? [],
      'validation_errors' => [],
    ];
  }

  /**
   * Invoke the GM model for room chat.
   */
  protected function invokeGmModel(string $prompt, string $system_prompt, array $context_data, string $room_id): ?string {
    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'room_chat_gm_reply',
        $context_data,
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 800,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
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
   * Execute canonical authoritative actions that live outside local deltas.
   */
  protected function executeCanonicalAuthoritativeActions(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    ?int $character_id,
    array $actions,
    array $dungeon_data
  ): array {
    $results = [
      'quest_turn_in' => [],
      'combat_initiation' => NULL,
    ];
    $errors = [];
    $remaining_actions = [];
    $reloaded_dungeon_data = NULL;

    foreach ($actions as $action) {
      $type = (string) ($action['type'] ?? 'other');
      if ($type === 'quest_turn_in') {
        $turn_in = $this->handleQuestTurnInAction($campaign_id, $room_id, $character_id, $action);
        $results['quest_turn_in'][] = $turn_in;
        if (!empty($turn_in['success'])) {
          $remaining_actions[] = $action;
        }
        else {
          $errors[] = [
            'action_name' => $action['name'] ?? 'quest_turn_in',
            'message' => $turn_in['error'] ?? 'Quest turn-in failed.',
          ];
        }
        continue;
      }

      if ($type === 'combat_initiation') {
        $combat = $this->handleCombatInitiationAction($campaign_id, $room_id, $room_meta, $dungeon_data, $action);
        $results['combat_initiation'] = $combat;
        if (!empty($combat['success'])) {
          $remaining_actions[] = $action;
          if (!empty($combat['dungeon_data']) && is_array($combat['dungeon_data'])) {
            $reloaded_dungeon_data = $combat['dungeon_data'];
          }
        }
        else {
          $errors[] = [
            'action_name' => $action['name'] ?? 'combat_initiation',
            'message' => $combat['error'] ?? 'Combat initiation failed.',
          ];
        }
        continue;
      }

      $remaining_actions[] = $action;
    }

    return [
      'actions' => $remaining_actions,
      'results' => $results,
      'errors' => $errors,
      'reloaded_dungeon_data' => $reloaded_dungeon_data,
    ];
  }

  /**
   * Validate and execute a quest turn-in action.
   */
  protected function handleQuestTurnInAction(int $campaign_id, string $room_id, ?int $character_id, array $action): array {
    $validation = $this->validateQuestTurnInAction($character_id, $action);
    if (empty($validation['valid'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'quest_turn_in', 'rejected', [
        'room_id' => $room_id,
        'character_id' => $character_id,
        'errors' => $validation['errors'] ?? [],
      ]);
      return [
        'success' => FALSE,
        'error' => implode(' ', $validation['errors'] ?? ['Quest turn-in validation failed.']),
      ];
    }

    $quest = $action['details']['quest'] ?? [];
    /** @var \Drupal\dungeoncrawler_content\Service\QuestTouchpointService $touchpoint_service */
    $touchpoint_service = \Drupal::service('dungeoncrawler_content.quest_touchpoint');
    $result = $touchpoint_service->ingestEvent($campaign_id, [
      'character_id' => $character_id,
      'touchpoint' => [
        'objective_type' => $quest['objective_type'] ?? '',
        'objective_id' => $quest['objective_id'] ?? '',
        'item_ref' => $quest['item_ref'] ?? '',
        'npc_ref' => $quest['npc_ref'] ?? '',
        'entity_ref' => $quest['npc_ref'] ?? ($quest['item_ref'] ?? ''),
        'quantity' => (int) ($quest['quantity'] ?? 1),
        'room_id' => $room_id,
        'confidence' => $quest['confidence'] ?? 'high',
      ],
    ]);

    if (empty($result['success'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'quest_turn_in', 'rejected', [
        'room_id' => $room_id,
        'character_id' => $character_id,
        'result' => $result,
      ]);
      return [
        'success' => FALSE,
        'error' => (string) ($result['error'] ?? 'Quest turn-in could not be applied.'),
      ];
    }

    return $result + ['success' => TRUE];
  }

  /**
   * Validate quest turn-in action payload.
   */
  protected function validateQuestTurnInAction(?int $character_id, array $action): array {
    $errors = [];
    if (!$character_id) {
      $errors[] = 'Quest turn-in requires an acting character.';
    }
    $quest = $action['details']['quest'] ?? NULL;
    if (!is_array($quest)) {
      $errors[] = 'Quest turn-in action is missing details.quest.';
    }
    elseif (empty($quest['objective_type'])) {
      $errors[] = 'Quest turn-in action is missing objective_type.';
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Validate and execute a combat initiation action.
   */
  protected function handleCombatInitiationAction(int $campaign_id, string $room_id, array $room_meta, array $dungeon_data, array $action): array {
    $validation = $this->validateCombatInitiationAction($room_id, $dungeon_data, $action);
    if (empty($validation['valid'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'combat_initiation', 'rejected', [
        'room_id' => $room_id,
        'errors' => $validation['errors'] ?? [],
      ]);
      return [
        'success' => FALSE,
        'error' => implode(' ', $validation['errors'] ?? ['Combat initiation validation failed.']),
      ];
    }

    $combat = $action['details']['combat'] ?? [];
    /** @var \Drupal\dungeoncrawler_content\Service\GameCoordinatorService $game_coordinator */
    $game_coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
    $result = $game_coordinator->transitionPhase($campaign_id, 'encounter', [
      'reason' => $combat['reason'] ?? 'Combat begins.',
      'encounter_context' => [
        'room_id' => $room_id,
        'room_name' => $room_meta['name'] ?? $room_id,
        'enemies' => $validation['enemies'] ?? [],
      ],
    ]);

    if (empty($result['success'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'combat_initiation', 'rejected', [
        'room_id' => $room_id,
        'result' => $result,
      ]);
      return [
        'success' => FALSE,
        'error' => (string) ($result['error'] ?? 'Combat could not be started.'),
      ];
    }

    return [
      'success' => TRUE,
      'transition' => $result,
      'dungeon_data' => $this->reloadDungeonData($campaign_id),
    ];
  }

  /**
   * Validate combat initiation action payload and resolve targets.
   */
  protected function validateCombatInitiationAction(string $room_id, array $dungeon_data, array $action): array {
    $game_state = $dungeon_data['game_state'] ?? [];
    if (($game_state['phase'] ?? 'exploration') === 'encounter') {
      return [
        'valid' => FALSE,
        'errors' => ['Combat is already active.'],
      ];
    }

    $combat = $action['details']['combat'] ?? NULL;
    if (!is_array($combat)) {
      return [
        'valid' => FALSE,
        'errors' => ['Combat initiation action is missing details.combat.'],
      ];
    }

    $enemies = $this->resolveCombatEnemyEntities($room_id, $dungeon_data, $combat);
    if (empty($enemies)) {
      return [
        'valid' => FALSE,
        'errors' => ['No valid enemy entities were found for combat initiation.'],
      ];
    }

    return [
      'valid' => TRUE,
      'errors' => [],
      'enemies' => $enemies,
    ];
  }

  /**
   * Resolve enemy entity payloads for combat initiation.
   */
  protected function resolveCombatEnemyEntities(string $room_id, array $dungeon_data, array $combat): array {
    $requested_ids = $combat['enemy_entity_ids'] ?? [];
    if (!is_array($requested_ids)) {
      $requested_ids = [];
    }
    if (!empty($combat['target_entity_id'])) {
      $requested_ids[] = $combat['target_entity_id'];
    }

    $requested_names = $combat['enemy_names'] ?? [];
    if (!is_array($requested_names)) {
      $requested_names = [];
    }
    if (!empty($combat['target_name'])) {
      $requested_names[] = $combat['target_name'];
    }

    $requested_ids = array_values(array_filter(array_map('strval', $requested_ids)));
    $requested_names = array_values(array_filter(array_map(static function ($value): string {
      return strtolower(trim((string) $value));
    }, $requested_names)));
    $entities = $dungeon_data['entities'] ?? [];
    $resolved = [];

    foreach ($entities as $entity) {
      $entity_room = $entity['placement']['room_id'] ?? '';
      if ($entity_room !== $room_id) {
        continue;
      }

      $entity_id = (string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? '');
      $entity_character_id = (string) ($entity['character_id'] ?? '');
      $entity_name = strtolower(trim((string) ($entity['state']['metadata']['display_name'] ?? $entity['name'] ?? '')));
      $team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));
      $is_hostile = in_array($team, ['hostile', 'enemy', 'monsters'], TRUE);

      if (!empty($requested_ids)) {
        $matchable_ids = array_values(array_filter([
          $entity_id,
          $entity_character_id,
        ], static fn($value): bool => $value !== ''));
        if (!empty(array_intersect($matchable_ids, $requested_ids))) {
          $resolved[] = $entity;
        }
        continue;
      }

      if (!empty($requested_names)) {
        if ($entity_name !== '' && in_array($entity_name, $requested_names, TRUE)) {
          $resolved[] = $entity;
        }
        continue;
      }

      if ($is_hostile) {
        $resolved[] = $entity;
      }
    }

    return $resolved;
  }

  /**
   * Reload latest dungeon_data from persistence.
   */
  protected function reloadDungeonData(int $campaign_id): array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);
    return is_array($dungeon_data) ? $dungeon_data : [];
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
    // Find navigate_to_location action(s).
    $nav_actions = array_filter($actions, fn($a) => ($a['type'] ?? '') === 'navigate_to_location');

    if (empty($nav_actions)) {
      return NULL;
    }

    if (!$this->mapGenerator) {
      $this->logger->warning('Navigation action detected but MapGeneratorService is not available');
      return NULL;
    }

    // Use the first navigation action (shouldn't be multiple).
    $nav = reset($nav_actions);
    $details = $nav['details'] ?? [];
    $destination = $details['destination'] ?? $details['destination_description'] ?? $nav['name'] ?? 'Unknown destination';
    $destination_desc = $details['destination_description'] ?? $destination;

    // Gather narrative context.
    $narrative_context = [
      'gm_narrative' => $gm_narrative,
      'campaign_theme' => $dungeon_data['theme'] ?? 'high fantasy',
      'party_level' => $dungeon_data['generation_rules']['party_level_target'] ?? 1,
      'time_of_day' => $this->inferTimeOfDay($dungeon_data),
      'travel_type' => $details['travel_type'] ?? 'walk',
      'estimated_distance' => $details['estimated_distance'] ?? 'short',
    ];

    try {
      $result = $this->mapGenerator->generateSetting(
        $campaign_id,
        $destination_desc,
        $origin_room_id,
        $narrative_context
      );

      $this->logger->info('Navigation triggered: @dest → room @name (index @idx, @hexes hexes)', [
        '@dest' => $destination,
        '@name' => $result['room']['name'] ?? 'Unknown',
        '@idx' => $result['room_index'] ?? '?',
        '@hexes' => count($result['room']['hexes'] ?? []),
      ]);

      return [
        'type' => 'navigate_to_location',
        'destination' => $destination,
        'new_room' => $result['room'],
        'new_room_index' => $result['room_index'],
        'entities' => $result['entities'] ?? [],
        'entities_added' => count($result['entities'] ?? []),
        'dungeon_data' => $result['dungeon_data'] ?? [],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to generate new setting for navigation to @dest: @err', [
        '@dest' => $destination,
        '@err' => $e->getMessage(),
      ]);
      return [
        'type' => 'navigate_to_location',
        'destination' => $destination,
        'error' => 'Failed to generate the new location. Try again.',
      ];
    }
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

    // Cap location_history to 50 entries.
    if (count($dungeon_data['location_history']) > 50) {
      $dungeon_data['location_history'] = array_slice($dungeon_data['location_history'], -50);
    }
  }

  /**
   * Build a client-consumable navigation payload.
   *
   * Normalizes the new room, its entities, and connection data into the same
   * format the client hexmap expects, so the JS can inject them into the
   * live dungeonData and call setActiveRoom().
   *
   * @param array $navigation
   *   Navigation result from handleNavigationActions().
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $dungeon_data
   *   Updated dungeon_data (already contains the new room + entities).
   *
   * @return array
   *   Client-ready navigation payload:
   *   - target_room_id: string (room to switch to)
   *   - room: array (normalized room data, same format as rooms[room_id])
   *   - entities: array (new entities for this room)
   *   - connections: array (new connections involving this room)
   *   - entry_hex: {q, r} (where to place the player)
   */
  protected function buildClientNavigationPayload(array $navigation, int $campaign_id, array $dungeon_data): array {
    $room = $navigation['new_room'] ?? [];
    $room_id = (string) ($room['room_id'] ?? '');

    // Normalize room into the client-expected format (matches HexMapController).
    $normalized_room = [
      'room_id' => $room_id,
      'name' => (string) ($room['name'] ?? ''),
      'description' => (string) ($room['description'] ?? ''),
      'hexes' => is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [],
      'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
      'lighting' => is_string($room['lighting'] ?? NULL)
        ? $room['lighting']
        : (is_array($room['lighting'] ?? NULL) && isset($room['lighting']['level'])
          ? (string) $room['lighting']['level']
          : 'normal'),
      'room_type' => (string) ($room['room_type'] ?? 'unknown'),
      'size_category' => (string) ($room['size_category'] ?? 'medium'),
      'gameplay_state' => is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : [],
    ];

    // Collect entities that belong to the new room.
    $room_entities = [];
    foreach (($navigation['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $room_entities[] = $entity;
    }

    // Extract connections involving this room from the updated dungeon_data.
    $new_connections = [];
    $hex_map_connections = $dungeon_data['hex_map']['connections'] ?? ($dungeon_data['connections'] ?? []);
    if (is_array($hex_map_connections)) {
      foreach ($hex_map_connections as $conn) {
        if (!is_array($conn)) {
          continue;
        }
        if (($conn['from_room'] ?? '') === $room_id || ($conn['to_room'] ?? '') === $room_id) {
          $new_connections[] = $conn;
        }
      }
    }

    // Determine entry hex: first hex in the new room, or the connection endpoint.
    $entry_hex = ['q' => 0, 'r' => 0];
    // Prefer the connection to_hex (the entry point from the origin room).
    foreach ($new_connections as $conn) {
      if (($conn['to_room'] ?? '') === $room_id && isset($conn['to_hex'])) {
        $entry_hex = [
          'q' => (int) ($conn['to_hex']['q'] ?? 0),
          'r' => (int) ($conn['to_hex']['r'] ?? 0),
        ];
        break;
      }
      if (($conn['from_room'] ?? '') === $room_id && isset($conn['from_hex'])) {
        $entry_hex = [
          'q' => (int) ($conn['from_hex']['q'] ?? 0),
          'r' => (int) ($conn['from_hex']['r'] ?? 0),
        ];
        break;
      }
    }
    // Fallback: use the first hex of the room.
    if ($entry_hex['q'] === 0 && $entry_hex['r'] === 0 && !empty($normalized_room['hexes'])) {
      $first_hex = $normalized_room['hexes'][0];
      $entry_hex = [
        'q' => (int) ($first_hex['q'] ?? 0),
        'r' => (int) ($first_hex['r'] ?? 0),
      ];
    }

    return [
      'target_room_id' => $room_id,
      'destination' => $navigation['destination'] ?? '',
      'room' => $normalized_room,
      'entities' => $room_entities,
      'connections' => $new_connections,
      'entry_hex' => $entry_hex,
    ];
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
    $recent = array_slice($channel_chat, -10);

    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      $history_lines[] = "{$speaker}: {$text}";
    }

    // Build NPC-scoped session context from AiSessionManager.
    $ai_session_key = $this->channelManager->getAiSessionKeyForChannel($campaign_id, $channel_key);
    $session_context = $this->sessionManager->buildSessionContext($ai_session_key, $campaign_id, 6);

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
    $prompt = '';
    if ($session_context !== '') {
      $prompt .= $session_context . "\n\n---\n";
    }
    if (!empty($scene_parts)) {
      $prompt .= implode("\n", $scene_parts) . "\n\n";
    }
    if ($npc_context) {
      $prompt .= $npc_context . "\n\n";
    }
    $prompt .= "You are {$target_name}, an NPC in a Pathfinder 2e dungeon crawl.\n";
    $prompt .= "The player character is communicating with you via {$source_ability}.\n";
    $prompt .= "Stay in character as {$target_name}. Do NOT respond as the Game Master.\n";
    $prompt .= "Your responses should reflect your personality traits, current attitude, and motivations as described above.\n\n";
    $prompt .= "Conversation so far:\n" . implode("\n", $history_lines);
    $prompt .= "\n\nRespond in character as {$target_name}. Keep your reply concise (1-3 sentences).";

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
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'channel_npc_reply',
        $context_data,
        [
          'system_prompt' => "You are {$target_name}, a character in a tabletop RPG. Your current attitude toward the party is: {$npc_attitude}. Use the character sheet and psychology profile provided in the user prompt to stay in character. Reflect your personality traits, motivations, and recent inner thoughts in your tone and word choice. Do not break the fourth wall. Do not mention that you are an AI.",
          'max_tokens' => 400,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->error('AI API error generating NPC reply on channel @channel: @msg', [
        '@channel' => $channel_key,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      return NULL;
    }

    $response_text = trim($result['response']);

    $npc_message = [
      'speaker' => $target_name,
      'message' => $response_text,
      'type' => 'npc',
      'channel' => $channel_key,
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
    ];

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
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return ['channels' => [], 'active_channel' => 'room'];
    }

    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE) ?: [];
    $rooms = $dungeon_data['rooms'] ?? [];
    $room_index = $this->findRoomIndex($rooms, $room_id);

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
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return ['success' => FALSE, 'channel' => NULL, 'error' => 'Dungeon not found'];
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE) ?: [];
    if (!isset($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    $room_index = $this->findRoomIndex($dungeon_data['rooms'], $room_id);
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
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return FALSE;
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE) ?: [];
    $room_index = $this->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
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
        // Room channel: route through NarrationEngine for perception-filtered narration.
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
    catch (\Exception $e) {
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
      $room_session = $this->chatSessionManager->ensureRoomSession($campaign_id, $dungeon_id, $room_id);

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
    catch (\Exception $e) {
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
    catch (\Exception $e) {
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
    catch (\Exception $e) {
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
    $characters = [];
    $room = $dungeon_data['rooms'][$room_index] ?? [];

    // PC characters in the room.
    $pc_characters = $room['characters'] ?? [];
    foreach ($pc_characters as $pc) {
      $char_id = $pc['character_id'] ?? $pc['id'] ?? NULL;
      if ($char_id === NULL) {
        continue;
      }
      $characters[] = [
        'character_id' => $char_id,
        'name' => $pc['name'] ?? $pc['display_name'] ?? 'Unknown',
        'perception' => $pc['perception'] ?? ($pc['stats']['perception'] ?? 0),
        'languages' => $pc['languages'] ?? ['Common'],
        'senses' => $pc['senses'] ?? [],
        'conditions' => $pc['conditions'] ?? [],
        'position' => $pc['position'] ?? NULL,
      ];
    }

    // NPC entities in the room.
    $entities = $room['entities'] ?? [];
    foreach ($entities as $ent) {
      $ent_ref = $ent['entity_ref']['content_id'] ?? $ent['entity_ref'] ?? '';
      $meta = $ent['state']['metadata'] ?? [];
      $stats = $meta['stats'] ?? [];

      $characters[] = [
        'character_id' => $ent['entity_instance_id'] ?? $ent_ref,
        'name' => $meta['display_name'] ?? $ent['name'] ?? 'Unknown Entity',
        'perception' => $stats['perception'] ?? 0,
        'languages' => $ent['languages'] ?? ['Common'],
        'senses' => $ent['senses'] ?? [],
        'conditions' => $ent['conditions'] ?? ($meta['conditions'] ?? []),
        'position' => $ent['position'] ?? NULL,
      ];
    }

    return $characters;
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
    $uid = $this->currentUser->id();
    $account = \Drupal\user\Entity\User::load($uid);
    
    // Admin users can access any campaign
    if ($account && $account->hasPermission('administer dungeoncrawler')) {
      return TRUE;
    }
    
    // Check if user owns the campaign
    $owner_uid = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    
    if ($owner_uid && $owner_uid == $uid) {
      return TRUE;
    }
    
    // Check if user has a character in this campaign
    $user_in_campaign = $this->database->select('dc_campaign_characters', 'c')
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    return $user_in_campaign > 0;
  }

  /**
   * Find a room entry by room_id in a rooms array (may be keyed or indexed).
   *
   * @param array $rooms
   *   The rooms array from dungeon_data.
   * @param string $room_id
   *   The room UUID to find.
   *
   * @return array
   *   The room entry, or empty array if not found.
   */
  protected function findRoomByRoomId(array $rooms, string $room_id): array {
    // Direct key match (rooms keyed by room_id).
    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $rooms[$room_id];
    }

    // Numeric/sequential array — search by room_id field.
    foreach ($rooms as $room) {
      if (is_array($room) && ($room['room_id'] ?? '') === $room_id) {
        return $room;
      }
    }

    return [];
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
    // Gather room NPCs with psychology profiles.
    $room_npcs = $this->gatherRoomNpcsWithProfiles($campaign_id, $room_id, $dungeon_data);

    if (empty($room_npcs)) {
      return [];
    }

    // Build the interjection evaluation prompt.
    $npc_descriptions = [];
    foreach ($room_npcs as $npc) {
      $profile = $npc['profile'];
      $desc = "{$profile['display_name']}";
      $desc .= " — Attitude: {$profile['attitude']}";
      if (!empty($profile['personality_traits'])) {
        $desc .= ", Personality: {$profile['personality_traits']}";
      }
      if (!empty($profile['motivations'])) {
        $desc .= ", Motivations: {$profile['motivations']}";
      }
      // Include recent inner monologue if any.
      $monologue = $profile['inner_monologue'] ?? [];
      if (!empty($monologue)) {
        $recent_thought = end($monologue);
        $thought_text = $recent_thought['thought'] ?? $recent_thought['text'] ?? '';
        if ($thought_text) {
          $desc .= ", Recent thought: \"{$thought_text}\"";
        }
      }
      // Include NPC session context (conversation memory).
      $session_key = $this->sessionManager->npcSessionKey($campaign_id, $npc['entity_ref']);
      $session_context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 3);
      if ($session_context) {
        $desc .= "\n  Prior conversations: {$session_context}";
      }
      $npc_descriptions[] = $desc;
    }

    // Get recent chat for broader context (last 6 messages).
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $recent = array_slice($chat, -6);
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      $history_lines[] = "{$speaker}: {$text}";
    }

    $system_prompt = <<<PROMPT
You are the NPC interjection evaluator for a tabletop RPG. Your job is to decide which NPCs present in the room, if any, are motivated to speak up after the latest exchange.

Rules:
- If the player DIRECTLY ADDRESSES an NPC by name (e.g., "Hey Gribbles" or "Lets talk to Eldric" or asks them a question), that NPC MUST respond. This is mandatory — a directly addressed NPC always speaks. If the addressed name is a nickname or an alias introduced in dialogue (e.g., another character said "That'd be Vorren"), match it to the closest NPC in the room by role or context.
- If the conversation mentions an NPC's interests, motivations, or expertise, they are likely to speak up.
- Hostile or unfriendly NPCs may interject with threats, complaints, or provocations when provoked.
- Friendly or helpful NPCs may offer information, greetings, or advice if the topic is relevant.
- Indifferent NPCs rarely speak UNLESS directly addressed or their interests are at stake.
- For general conversation that doesn't involve any NPC, output NONE.
- Maximum 1 NPC interjection per exchange (keep the pace natural).
- Each NPC has their own voice, personality, and knowledge. Their dialogue should reflect their character sheet, backstory, and current attitude.

CRITICAL NAME RULE: You MUST use the NPC's name EXACTLY as it appears in the "NPCs present in the room" list. Never invent a new name or use a name introduced only in dialogue by another character. If an NPC was called "Vorren" in conversation but is listed as "Mysterious Merchant", return {"speaker": "Mysterious Merchant"}.

Output format — respond with EXACTLY one of:
1. The word NONE (if no NPC wants to speak)
2. A JSON object: {"speaker": "NPC Name"} — use the EXACT name from the NPCs present list above.
PROMPT;

    $user_prompt = "NPCs present in the room:\n" . implode("\n", $npc_descriptions);
    $user_prompt .= "\n\nRecent conversation:\n" . implode("\n", $history_lines);

    // Include active PC's roleplay style so NPCs respond appropriately.
    if ($active_character_data) {
      $pc_name = $active_character_data['name'] ?? 'the player';
      $pc_style = $active_character_data['roleplay_style'] ?? 'balanced';
      $style_hints = [
        'talker'   => 'verbose and conversational — NPCs may be drawn into dialogue',
        'balanced' => 'balanced between speech and action',
        'doer'     => 'terse and action-oriented — NPCs may need to react to deeds rather than words',
        'observer' => 'quiet and watchful — NPCs may feel studied or be more guarded',
      ];
      $style_hint = $style_hints[$pc_style] ?? $style_hints['balanced'];
      $user_prompt .= "\n\nActive PC: {$pc_name} (roleplay style: {$pc_style} — {$style_hint}).";
      $user_prompt .= " Only one PC spoke this turn. Evaluate NPC responses accordingly.";
    }

    $user_prompt .= "\n\nLatest exchange:";
    $user_prompt .= "\nPlayer: {$player_message}";
    $user_prompt .= "\nGame Master: {$gm_narrative}";
    $user_prompt .= "\n\nShould any NPC speak? Respond with NONE or a JSON object with just the speaker name.";

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $user_prompt,
        'dungeoncrawler_content',
        'npc_interjection_eval',
        ['campaign_id' => $campaign_id, 'room_id' => $room_id],
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 200,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('NPC interjection eval failed: @err', ['@err' => $e->getMessage()]);
      return [];
    }

    if (empty($result['success']) || empty($result['response'])) {
      return [];
    }

    $response_text = trim($result['response']);

    // Parse response.
    if (strtoupper($response_text) === 'NONE' || stripos($response_text, 'none') === 0) {
      // Feed the conversation to NPC sessions even when they don't speak.
      $this->feedRoomChatToNpcSessions($campaign_id, $room_npcs, $player_message, $gm_narrative);
      return [];
    }

    // Try to parse JSON from the response (may have extra text around it).
    $json_match = [];
    if (preg_match('/\{[^}]+\}/', $response_text, $json_match)) {
      $parsed = json_decode($json_match[0], TRUE);
    }
    else {
      $parsed = json_decode($response_text, TRUE);
    }

    if (!is_array($parsed) || empty($parsed['speaker'])) {
      $this->feedRoomChatToNpcSessions($campaign_id, $room_npcs, $player_message, $gm_narrative);
      return [];
    }

    // Validate that the speaker is actually an NPC in the room.
    // First try exact match, then fall back to partial/alias match so that
    // names introduced only in dialogue (e.g. "Vorren" for "Mysterious Merchant")
    // still resolve to the correct room NPC.
    $valid_speaker = FALSE;
    $speaker_ref = '';
    $speaker_npc = NULL;
    foreach ($room_npcs as $npc) {
      if (strcasecmp($npc['profile']['display_name'], $parsed['speaker']) === 0) {
        $valid_speaker = TRUE;
        $speaker_ref = $npc['entity_ref'];
        $speaker_npc = $npc;
        break;
      }
    }

    // Partial/alias fallback: check if the returned name is a substring of a
    // room NPC name or vice-versa (catches "Vorren" ↔ "Mysterious Merchant"
    // only when there is exactly one non-matched NPC to avoid ambiguity).
    if (!$valid_speaker && count($room_npcs) === 1) {
      $only_npc = $room_npcs[0];
      $valid_speaker = TRUE;
      $speaker_ref = $only_npc['entity_ref'];
      $speaker_npc = $only_npc;
      $this->logger->info('NPC alias resolved: @alias → @canonical', [
        '@alias' => $parsed['speaker'],
        '@canonical' => $only_npc['profile']['display_name'],
      ]);
      // Override the speaker label with the canonical name.
      $parsed['speaker'] = $only_npc['profile']['display_name'];
    }

    if (!$valid_speaker) {
      $this->logger->warning('NPC interjection referenced unknown speaker: @speaker', [
        '@speaker' => $parsed['speaker'],
      ]);
      $this->feedRoomChatToNpcSessions($campaign_id, $room_npcs, $player_message, $gm_narrative);
      return [];
    }

    // Generate full-context NPC dialogue using the psychology system.
    $npc_dialogue = $this->generateNpcRoomDialogue(
      $campaign_id, $room_id, $room_index, $dungeon_data,
      $speaker_ref, $parsed['speaker'], $player_message, $gm_narrative
    );

    if (empty($npc_dialogue)) {
      $this->feedRoomChatToNpcSessions($campaign_id, $room_npcs, $player_message, $gm_narrative);
      return [];
    }

    // Build the NPC chat message.
    $npc_message = [
      'speaker' => $parsed['speaker'],
      'message' => $npc_dialogue,
      'type' => 'npc',
      'channel' => 'room',
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
      'interjection' => TRUE,
    ];

    // Persist the NPC interjection to dungeon_data chat.
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

    // Record the interjection in the NPC's own AI session.
    $session_key = $this->sessionManager->npcSessionKey($campaign_id, $speaker_ref);
    $context_for_npc = "Room conversation — Player: {$player_message} | GM: {$gm_narrative}";
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $context_for_npc);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $npc_dialogue);

    // Record inner monologue for the speaking NPC.
    $this->psychologyService->recordInnerMonologue(
      $campaign_id,
      $speaker_ref,
      'conversation',
      "I spoke up in the room chat: \"{$npc_dialogue}\"",
      ['trigger' => 'room_interjection', 'player_said' => substr($player_message, 0, 200)]
    );

    // Feed the conversation to ALL NPC sessions (including non-speakers).
    $this->feedRoomChatToNpcSessions($campaign_id, $room_npcs, $player_message, $gm_narrative, $speaker_ref);

    // Bridge into hierarchical session system.
    $this->bridgeNpcInterjectionToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $parsed['speaker'], $npc_dialogue, $speaker_ref
    );

    $this->logger->info('NPC interjection by @npc in room @room: @msg', [
      '@npc' => $parsed['speaker'],
      '@room' => $room_id,
      '@msg' => substr($npc_dialogue, 0, 100),
    ]);

    return [$npc_message];
  }

  /**
   * Generate NPC dialogue for a room chat interjection using full psychology context.
   *
   * This is the second step of the two-phase interjection system:
   * 1. evaluateNpcInterjections() decides WHO speaks.
   * 2. This method generates WHAT they say, using the NPC's full character sheet,
   *    personality, backstory, inner monologue, and session memory.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|string $room_index
   *   Room index in dungeon_data.
   * @param array $dungeon_data
   *   Full dungeon data.
   * @param string $entity_ref
   *   NPC entity reference (e.g., 'gribbles_rindsworth').
   * @param string $display_name
   *   NPC display name (e.g., 'Gribbles Rindsworth').
   * @param string $player_message
   *   The player's message that triggered this.
   * @param string $gm_narrative
   *   The GM's narrative response.
   *
   * @return string|null
   *   The NPC's dialogue text, or NULL on failure.
   */
  protected function generateNpcRoomDialogue(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    array $dungeon_data,
    string $entity_ref,
    string $display_name,
    string $player_message,
    string $gm_narrative
  ): ?string {
    // Find the live entity instance for real-time stats.
    $live_entity = [];
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $entities = $room_meta['entities'] ?? [];
    foreach ($entities as $ent) {
      $ent_ref = $ent['entity_ref']['content_id'] ?? $ent['entity_ref'] ?? '';
      if ($ent_ref === $entity_ref) {
        $live_entity = $ent;
        break;
      }
    }

    // Build full NPC psychology context (character sheet + personality + monologue).
    $npc_context = $this->psychologyService->buildNpcContextForPrompt(
      $campaign_id,
      $entity_ref,
      $live_entity
    );

    // Build NPC session context (conversation memory).
    $session_key = $this->sessionManager->npcSessionKey($campaign_id, $entity_ref);
    $session_context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 4);

    // Get recent room chat for conversational flow.
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $recent = array_slice($chat, -8);
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      $history_lines[] = "{$speaker}: {$text}";
    }

    // Room scene context.
    $scene = '';
    if (!empty($room_meta['name'])) {
      $scene .= 'Current room: ' . $room_meta['name'] . "\n";
    }

    // Build the user prompt.
    $prompt = '';
    if ($session_context !== '') {
      $prompt .= "=== YOUR CONVERSATION MEMORY ===\n{$session_context}\n\n---\n";
    }
    if ($scene) {
      $prompt .= $scene . "\n";
    }
    if ($npc_context) {
      $prompt .= $npc_context . "\n\n";
    }
    $prompt .= "=== CURRENT ROOM CONVERSATION ===\n" . implode("\n", $history_lines) . "\n\n";
    $prompt .= "The player just said: \"{$player_message}\"\n";
    $prompt .= "The Game Master narrated: \"{$gm_narrative}\"\n\n";
    $prompt .= "Respond in character as {$display_name}. Speak naturally in your own voice.\n";
    $prompt .= "Your response should reflect your personality, backstory, current attitude, and knowledge.\n";
    $prompt .= "Keep your reply concise (1-3 sentences). Do not narrate actions — just speak your dialogue.";

    // Get NPC's current attitude for system prompt.
    $npc_attitude = $this->psychologyService->getAttitude($campaign_id, $entity_ref) ?? 'indifferent';

    $system_prompt = "You are {$display_name}, a character in a tabletop RPG. "
      . "Your current attitude toward the party is: {$npc_attitude}. "
      . "Use the character sheet and psychology profile provided to stay fully in character. "
      . "Reflect your ancestry, background, personality traits, motivations, and recent inner thoughts in your tone and word choice. "
      . "Speak in your own distinct voice — you know who you are, where you come from, and what you want. "
      . "Do not break the fourth wall. Do not mention that you are an AI. Do not narrate — just speak.";

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'npc_room_dialogue',
        [
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'npc_entity' => $entity_ref,
        ],
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 400,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('NPC room dialogue generation failed for @npc: @err', [
        '@npc' => $entity_ref,
        '@err' => $e->getMessage(),
      ]);
      return NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      return NULL;
    }

    return trim($result['response']);
  }

  /**
   * Gather all NPCs in the current room that have psychology profiles.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param array $dungeon_data
   *   Full dungeon data.
   *
   * @return array
   *   Array of ['entity_ref' => string, 'entity' => array, 'profile' => array].
   */
  protected function gatherRoomNpcsWithProfiles(int $campaign_id, string $room_id, array $dungeon_data): array {
    $result = [];
    $seen_refs = [];
    $seen_names = [];

    // Gather NPCs from top-level entities.
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      $ent_room = $entity['placement']['room_id'] ?? '';
      $ent_type = $entity['entity_type'] ?? '';
      if ($ent_room !== $room_id || $ent_type !== 'npc') {
        continue;
      }

      $ref = $entity['entity_ref']['content_id'] ?? '';
      if (!$ref || isset($seen_refs[$ref])) {
        continue;
      }

      $profile = $this->psychologyService->loadProfile($campaign_id, $ref);
      if (!$profile) {
        continue;
      }

      $this->registerGatheredRoomNpc($result, $seen_refs, $seen_names, $ref, $entity, $profile);
    }

    // Also check dc_campaign_characters for NPCs in this room.
    try {
      foreach ($this->loadRoomCampaignNpcRows($campaign_id, $room_id, $dungeon_data) as $row) {
        $resolved = $this->resolveCampaignCharacterNpcProfile($campaign_id, $row, $seen_refs);
        if (empty($resolved['entity_ref']) || empty($resolved['profile'])) {
          continue;
        }

        $this->registerGatheredRoomNpc(
          $result,
          $seen_refs,
          $seen_names,
          $resolved['entity_ref'],
          [],
          $resolved['profile']
        );
      }
    }
    catch (\Exception $e) {
      // Non-critical; continue with entities already found.
    }

    // Narrative fallback: if the room has no registered NPC entities, scan the
    // room's name/description for NPC names from the dungeon's entity list.
    // This handles rooms that were generated from narrative context (e.g. an NPC
    // led the party to a new location) without formal entity placement.
    if (empty($result)) {
      $room_meta = NULL;
      foreach ($dungeon_data['rooms'] ?? [] as $r) {
        if (($r['room_id'] ?? '') === $room_id) {
          $room_meta = $r;
          break;
        }
      }

      if ($room_meta !== NULL) {
        $haystack = strtolower(
          ($room_meta['name'] ?? '') . ' ' . ($room_meta['description'] ?? '')
        );

        foreach ($dungeon_data['entities'] ?? [] as $entity) {
          if (($entity['entity_type'] ?? '') !== 'npc') {
            continue;
          }
          $ref = $entity['entity_ref']['content_id'] ?? '';
          if (!$ref || isset($seen_refs[$ref])) {
            continue;
          }
          $display_name = $entity['state']['metadata']['display_name']
            ?? $entity['name']
            ?? '';
          if ($display_name === '') {
            continue;
          }
          // Match on first word of the display name (e.g. "Gribbles" from
          // "Gribbles Rindsworth", or "Mysterious" from "Mysterious Merchant").
          $keyword = strtolower(strtok($display_name, ' '));
          if ($keyword !== '' && str_contains($haystack, $keyword)) {
            $profile = $this->psychologyService->loadProfile($campaign_id, $ref);
            if ($profile) {
              $this->registerGatheredRoomNpc($result, $seen_refs, $seen_names, $ref, $entity, $profile);
              $this->logger->info(
                'NPC @name found via room description in room @room (placement mismatch — entity in @src_room)',
                [
                  '@name' => $display_name,
                  '@room' => $room_id,
                  '@src_room' => $entity['placement']['room_id'] ?? 'unknown',
                ]
              );
            }
          }
        }
      }
    }

    return $result;
  }

  /**
   * Load room-local NPC rows from dc_campaign_characters.
   *
   * @return array
   *   Character rows keyed with name/role/instance_id.
   */
  protected function loadRoomCampaignNpcRows(int $campaign_id, string $room_id, array $dungeon_data): array {
    $room_slug = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data);
    if (!$room_slug) {
      return [];
    }

    return $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['name', 'role', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->condition('location_ref', $room_slug)
      ->execute()
      ->fetchAll();
  }

  /**
   * Resolve or seed a psychology profile for a room-local campaign NPC row.
   *
   * @param array $seen_refs
   *   Entity refs already added to the room NPC set.
   *
   * @return array
   *   ['entity_ref' => string, 'profile' => array|null]
   */
  protected function resolveCampaignCharacterNpcProfile(int $campaign_id, object $row, array $seen_refs = []): array {
    $candidates = array_values(array_filter([
      $row->instance_id ?: NULL,
      strtolower(str_replace(' ', '_', $row->name)),
    ]));

    $ref = '';
    $profile = NULL;
    foreach ($candidates as $candidate) {
      if (isset($seen_refs[$candidate])) {
        return [];
      }

      $profile = $this->psychologyService->loadProfile($campaign_id, $candidate);
      if ($profile) {
        $ref = $candidate;
        break;
      }
    }

    if ($ref === '' && !empty($candidates)) {
      $ref = (string) reset($candidates);
    }

    if ($ref !== '' && !$profile) {
      $profile = $this->psychologyService->getOrCreateProfile($campaign_id, $ref, [
        'display_name' => $row->name,
        'creature_type' => $row->instance_id ?: $ref,
        'role' => $row->role ?: 'npc',
        'initial_attitude' => 'indifferent',
      ]);
    }

    return ($ref !== '' && $profile)
      ? ['entity_ref' => $ref, 'profile' => $profile]
      : [];
  }

  /**
   * Register an NPC in the gathered room set, deduplicating by ref and name.
   */
  protected function registerGatheredRoomNpc(
    array &$result,
    array &$seen_refs,
    array &$seen_names,
    string $entity_ref,
    array $entity,
    array $profile
  ): void {
    if ($entity_ref === '' || isset($seen_refs[$entity_ref])) {
      return;
    }

    $display_name = trim((string) ($profile['display_name'] ?? ''));
    $display_key = $display_name !== '' ? strtolower($display_name) : '';
    if ($display_key !== '' && isset($seen_names[$display_key])) {
      $seen_refs[$entity_ref] = TRUE;
      return;
    }

    $result[] = [
      'entity_ref' => $entity_ref,
      'entity' => $entity,
      'profile' => $profile,
    ];
    $seen_refs[$entity_ref] = TRUE;
    if ($display_key !== '') {
      $seen_names[$display_key] = TRUE;
    }
  }

  /**
   * Feed room chat activity to all NPC AI sessions for passive awareness.
   *
   * Even when NPCs don't interject, they observe what's happening. This
   * records the conversation in their AI session so they can reference it
   * in future interactions.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_npcs
   *   Array from gatherRoomNpcsWithProfiles().
   * @param string $player_message
   *   Player's message.
   * @param string $gm_narrative
   *   GM's reply.
   * @param string|null $skip_ref
   *   Entity ref to skip (already recorded as speaker).
   */
  protected function feedRoomChatToNpcSessions(
    int $campaign_id,
    array $room_npcs,
    string $player_message,
    string $gm_narrative,
    ?string $skip_ref = NULL
  ): void {
    $observation = "Overheard in the room — Player: {$player_message} | GM reply: {$gm_narrative}";

    foreach ($room_npcs as $npc) {
      if ($skip_ref && $npc['entity_ref'] === $skip_ref) {
        continue;
      }

      $session_key = $this->sessionManager->npcSessionKey($campaign_id, $npc['entity_ref']);
      // Record as a system/observation message — the NPC "overhears" the exchange.
      $this->sessionManager->appendMessage(
        $session_key,
        $campaign_id,
        'user',
        "[Room observation] {$observation}"
      );
    }
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
      // Find the room session to post into.
      $room_session = $this->database->select('dc_chat_sessions', 's')
        ->fields('s', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('session_type', 'room')
        ->condition('status', 'active')
        ->orderBy('id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if (!$room_session) {
        return;
      }

      $this->chatSessionManager->postMessage(
        (int) $room_session,
        $campaign_id,
        $speaker,
        'npc',
        $speaker_ref,
        $message,
        'dialogue',
        'public'
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to bridge NPC interjection to session system: @err', [
        '@err' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Find the array index for a room by room_id.
   *
   * @param array $rooms
   *   The rooms array from dungeon_data.
   * @param string $room_id
   *   The room UUID to find.
   *
   * @return int|string|null
   *   The array key, or NULL if not found.
   */
  protected function findRoomIndex(array $rooms, string $room_id): int|string|null {
    // Direct key match.
    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $room_id;
    }

    // Numeric/sequential array — search by room_id field.
    foreach ($rooms as $key => $room) {
      if (is_array($room) && ($room['room_id'] ?? '') === $room_id) {
        return $key;
      }
    }

    return NULL;
  }

}
