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
  protected const ROOM_CHAT_MAX_INPUT_CHARS = 6800;
  protected const ROOM_CHAT_MAX_SYSTEM_PROMPT_CHARS = 7600;
  protected const ROOM_CHAT_MAX_USER_PROMPT_CHARS = 4000;
  protected const ROOM_CHAT_GM_MAX_TOKENS = 200;

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
  protected GmOrchestrationBrokerService $gmOrchestrationBroker;
  protected ?QuestTrackerService $questTracker;
  protected ?array $activeDebugTrace = NULL;

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
    ?CanonicalActionRegistryService $canonical_action_registry = NULL,
    ?GmOrchestrationBrokerService $gm_orchestration_broker = NULL,
    ?QuestTrackerService $quest_tracker = NULL
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
    $this->gmOrchestrationBroker = $gm_orchestration_broker ?? new GmOrchestrationBrokerService($database, $this->canonicalActionRegistry);
    $this->questTracker = $quest_tracker;
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
    bool $suppress_gm = FALSE
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
    $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id);
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
    $room_index = $this->findRoomIndex($dungeon_data['rooms'], $room_id);
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
      'user_id' => $this->currentUser->id(),
    ];

    $stage_started_at = hrtime(true);
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
    $this->recordDebugStage('persist_player_message', $stage_started_at, [
      'total_messages' => count($dungeon_data['rooms'][$room_index]['chat']),
      'room_entry' => $is_room_entry,
    ]);

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

    // Generate AI response (GM for room channel, NPC for private channels).
    $gm_result = [];
    $gm_response = NULL;
    $state_diff = NULL;
    $navigation = NULL;
    $npc_interjections = [];
    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;
    if ($type === 'player' && !$suppress_gm) {
      if ($channel === 'room') {
        $stage_started_at = hrtime(true);
        $this->ensureCurrentRoomNpcProfiles($campaign_id, $room_id, $dungeon_data, $room_index);
        $this->recordDebugStage('ensure_room_npc_profiles', $stage_started_at);
        // Room channel: GM responds.
        $stage_started_at = hrtime(true);
        $gm_result = $this->generateGmReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id);
        $this->recordDebugStage('generate_gm_reply', $stage_started_at, [
          'generated' => $gm_result !== NULL,
        ]);
      } else {
        // Private channel: target NPC responds.
        $channel_def = $dungeon_data['rooms'][$room_index]['channels'][$channel] ?? [];
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

      // After GM replies on the room channel, evaluate NPC interjections.
      // Room NPCs monitor the conversation and may chime in if motivated.
      if ($channel === 'room' && $gm_response !== NULL) {
        $stage_started_at = hrtime(true);
        if ($defer_npc_interjections) {
          $this->recordDebugStage('evaluate_npc_interjections', $stage_started_at, [
            'count' => 0,
            'deferred' => TRUE,
          ]);
        }
        else {
          $stage_started_at = hrtime(true);
          $npc_interjections = $this->evaluateNpcInterjections(
            $campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $message, $gm_response['message'] ?? '', $char_data
          );
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
    return $result;
  }

  /**
   * Continue the room GM turn after one or more player messages were queued.
   */
  public function continueQueuedRoomConversation(
    int $campaign_id,
    string $room_id,
    ?int $character_id = NULL,
    bool $defer_npc_interjections = FALSE
  ): array {
    $request_started_at = hrtime(true);
    $this->startDebugTrace([
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => 'room',
      'type' => 'gm_continuation',
      'character_id' => $character_id,
      'user_id' => (int) $this->currentUser->id(),
    ]);

    $stage_started_at = hrtime(true);
    $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id);
    $dungeon_id = $dungeon_snapshot['dungeon_id'];
    $dungeon_data = $dungeon_snapshot['dungeon_data'];
    $this->recordDebugStage('load_dungeon_data', $stage_started_at, [
      'dungeon_id' => $dungeon_id,
      'encoded_bytes' => $dungeon_snapshot['encoded_bytes'],
      'room_count' => count($dungeon_data['rooms'] ?? []),
    ]);

    $room_index = $this->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    if ($room_index === NULL) {
      return [
        'continued' => FALSE,
        'queued_player_count' => 0,
      ];
    }

    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $queued_player_messages = [];
    for ($i = count($chat) - 1; $i >= 0; $i--) {
      $entry = $chat[$i] ?? [];
      $entry_channel = (string) ($entry['channel'] ?? 'room');
      if ($entry_channel !== 'room') {
        continue;
      }
      if (($entry['type'] ?? '') === 'player') {
        array_unshift($queued_player_messages, $entry);
        continue;
      }
      break;
    }

    if ($queued_player_messages === []) {
      return [
        'continued' => FALSE,
        'queued_player_count' => 0,
      ];
    }

    $queued_player_summary = implode("\n", array_map(static fn(array $entry): string => (string) ($entry['message'] ?? ''), $queued_player_messages));
    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;

    $stage_started_at = hrtime(true);
    $this->ensureCurrentRoomNpcProfiles($campaign_id, $room_id, $dungeon_data, $room_index);
    $this->recordDebugStage('ensure_room_npc_profiles', $stage_started_at);

    $stage_started_at = hrtime(true);
    $gm_result = $this->generateGmReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id);
    $this->recordDebugStage('generate_gm_reply', $stage_started_at, [
      'generated' => $gm_result !== NULL,
      'queued_player_count' => count($queued_player_messages),
    ]);

    $gm_response = $gm_result['message'] ?? NULL;
    $result = [
      'continued' => $gm_response !== NULL,
      'queued_player_count' => count($queued_player_messages),
      'queued_player_summary' => $queued_player_summary,
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
      if ($defer_npc_interjections) {
        $result['npc_interjections_deferred'] = TRUE;
      }
    }

    $debug_trace = $this->finalizeDebugTrace($request_started_at, [
      'gm_reply_generated' => $gm_response !== NULL,
      'queued_player_count' => count($queued_player_messages),
      'npc_interjections_deferred' => $defer_npc_interjections && $gm_response !== NULL,
    ]);
    if ($debug_trace !== NULL) {
      $result['timing'] = $this->buildClientTimingSummary($debug_trace);
    }
    if ($debug_trace !== NULL && $this->shouldExposeDebugTrace()) {
      $result['debug_trace'] = $debug_trace;
    }

    return $result;
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
      $dungeon_snapshot = $this->loadLatestDungeonSnapshot($campaign_id);
    }
    catch (\InvalidArgumentException $e) {
      return [];
    }

    $dungeon_id = $dungeon_snapshot['dungeon_id'];
    $dungeon_data = $dungeon_snapshot['dungeon_data'];

    $room_index = $this->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    if ($room_index === NULL) {
      return [];
    }

    $this->ensureCurrentRoomNpcProfiles($campaign_id, $room_id, $dungeon_data, $room_index);
    $char_data = $character_id ? $this->actionProcessor->loadCharacterData($character_id) : NULL;

    return $this->evaluateNpcInterjections(
      $campaign_id,
      $room_id,
      $room_index,
      $dungeon_id,
      $dungeon_data,
      $player_message,
      $gm_narrative,
      $char_data
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
    $gm_started_at = hrtime(true);
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $is_room_entry = count($chat) === 1;
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

    $stage_started_at = hrtime(true);
    $room_npcs = $this->gatherRoomNpcsWithProfiles($campaign_id, $room_id, $dungeon_data);
    $directly_addressed_npc = $this->resolveDirectlyAddressedNpc($room_npcs, $latest_player_message);
    $turn_intent = $this->classifyRoomTurnIntent($latest_player_message, $room_npcs, $directly_addressed_npc);
    $this->recordDebugStage('gm.intent_classification', $stage_started_at, [
      'intent' => $turn_intent,
      'room_npc_count' => count($room_npcs),
      'direct_addressed' => $directly_addressed_npc['entity_ref'] ?? NULL,
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
      $directly_addressed_npc,
      $latest_player_message,
      $room_meta,
      $room_id,
      $dungeon_data
    );
    if ($deterministic_response !== NULL) {
      $checked_response = $deterministic_response;
      $response_source = 'deterministic';
      $this->recordDebugStage('gm.deterministic_short_path', $stage_started_at, [
        'intent' => $turn_intent,
        'narrative_length' => strlen((string) ($deterministic_response['narrative'] ?? '')),
        'action_count' => count($deterministic_response['actions'] ?? []),
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
      $session_context = $this->buildCompactSessionContext($session_key, $campaign_id, 2, 900, 320);
      $actor_grounding = $this->buildRoomActorGroundingSummary($campaign_id, $room_id, $dungeon_data);

      $prompt = '';
      if ($session_context !== '') {
        $prompt .= $session_context . "\n\n---\n";
      }
      if (!empty($scene_parts)) {
        $prompt .= implode("\n", $scene_parts) . "\n\n";
      }
      if (!empty($prompt_artifacts['npc_roster_summary'])) {
        $prompt .= $prompt_artifacts['npc_roster_summary'] . "\n\n";
      }
      if (!empty($prompt_artifacts['npc_profile_summary'])) {
        $prompt .= $prompt_artifacts['npc_profile_summary'] . "\n\n";
      }
      if ($actor_grounding !== '') {
        $prompt .= $actor_grounding . "\n\n";
      }
      if (!empty($prompt_artifacts['merchant_summary']) && $turn_intent === 'gm_narration') {
        $prompt .= $prompt_artifacts['merchant_summary'] . "\n\n";
      }
      if (!empty($prompt_artifacts['quest_summary']) && in_array($turn_intent, ['gm_narration', 'quest_query'], TRUE)) {
        $prompt .= $prompt_artifacts['quest_summary'] . "\n\n";
      }
      if ($quest_prompt_context !== '') {
        $prompt .= $quest_prompt_context . "\n\n";
      }
      $prompt .= "Recent conversation:\n" . implode("\n", $history_lines);
      if ($is_room_entry) {
        $prompt .= "\n\nTHIS IS A ROOM ENTRY — respond as the Game Master with a vivid but concise room-entry description (4-6 sentences, under 140 words). Cover atmosphere, sight, sound, smell/taste, and declare the named characters visibly present in the room on first load, along with their appearance/activity/demeanour. Keep the primary GM response focused on setting, consequences, and mechanics only. Include the JSON action block only if the player triggered a mechanical action.";
      }
      else {
        $prompt .= "\n\nRespond as the Game Master referee. Keep your reply concise (2-4 sentences) and limit it to setting, observable consequences, and mechanics. If the player is performing a mechanical action (casting a spell, using a skill, using a feat, attacking, exploring), include the JSON action block as instructed in your system prompt.";
      }
      $prompt .= "\nIMPORTANT: The primary GM response is NOT character dialogue. It is scene framing, rulings, consequences, and mechanics only.";
      $prompt .= "\nIMPORTANT: Do NOT write dialogue for any NPC. Describe the scene, NPC body language and reactions, but let NPCs speak for themselves. Never put words in an NPC's mouth.";
      $prompt .= "\nIMPORTANT: If the player addresses an NPC directly, the GM should only frame the handoff or visible reaction. Do NOT answer the conversation in that NPC's voice from the GM layer.";
      $prompt .= "\nIMPORTANT: Do NOT write dialogue for the player character, companions, or party members. Never decide what they say, agree to, feel, or choose beyond the action the player explicitly stated.";
      $prompt .= "\nIMPORTANT: For informational questions about who is present, demeanor, or what the room looks like, answer with direct observations only. Do NOT invent a scene, conversation, toast, agreement, plan, or travel setup.";
      $prompt .= "\nIMPORTANT: Named characters and NPCs must stay grounded in their provided canonical notes. If appearance, personality, attitude, motivations, role, or capabilities are not provided, do NOT invent them.";
      $prompt .= "\nIMPORTANT: Questions about whether an action is possible, wise, or legal are not actions. Answer those verbally and do NOT emit or mention any JSON, action block, code fence, or structured output unless the player is clearly taking the action right now.";
      $this->recordDebugStage('gm.user_prompt_assembly', $stage_started_at, [
        'recent_message_count' => count($recent),
        'history_line_count' => count($history_lines),
        'session_context_length' => strlen($session_context),
        'prompt_length' => strlen($prompt),
        'room_entry' => $is_room_entry,
        'quest_context_length' => strlen($quest_prompt_context),
        'actor_grounding_length' => strlen($actor_grounding),
        'artifact_bytes' => strlen(json_encode($prompt_artifacts) ?: ''),
      ]);

      // Build enhanced system prompt with character abilities if character_id is available.
      $stage_started_at = hrtime(true);
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
      $this->recordDebugStage('gm.room_inventory', $stage_started_at, [
        'summary' => $this->summarizeRoomInventory($room_inventory),
      ]);

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
      $this->recordDebugStage('gm.system_prompt_assembly', $stage_started_at, [
        'base_system_prompt_length' => strlen($base_system_prompt),
        'system_prompt_length' => strlen($system_prompt),
        'has_character_context' => $char_data !== NULL,
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
      if ($this->shouldUseGmResponseCache($turn_intent, $latest_player_message, $is_room_entry)) {
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
        $cached_gm_response = \Drupal::cache('default')->get($gm_response_cache_key);
        if ($cached_gm_response && is_array($cached_gm_response->data)) {
          $checked_response = $cached_gm_response->data;
          $response_source = 'cache';
          $this->recordDebugStage('gm.response_cache', $cache_stage_started_at, [
            'cache' => 'hit',
            'turn_intent' => $turn_intent,
          ]);
        }
      }
      if ($checked_response === NULL) {
        $this->recordDebugStage('gm.response_cache', $cache_stage_started_at, [
          'cache' => $gm_response_cache_key ? 'miss' : 'bypass',
          'turn_intent' => $turn_intent,
        ]);
        $stage_started_at = hrtime(true);
        $checked_response = $this->generateRealityCheckedGmResponse(
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
        if ($checked_response !== NULL) {
          $response_source = 'reality_checked_generation';
        }
        $this->recordDebugStage('gm.reality_checked_generation', $stage_started_at, [
          'success' => $checked_response !== NULL,
        ]);
      }
    }
    $this->recordDebugStage('gm.primary_flow', $gm_started_at, [
      'intent' => $turn_intent,
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

    // Parse and process any [CREATE_SUGGESTION] tag the GM embedded.
    $stage_started_at = hrtime(true);
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
    $narrative = $this->stripPlayerVisibleActionBlocks($narrative);
    $narrative = $this->trimIncompleteNarrative($narrative);
    $this->recordDebugStage('gm.suggestion_extraction', $stage_started_at);

    if (!empty($gm_response_cache_key)
      && empty($actions)
      && empty($dice_rolls)
      && empty($validation_errors)
      && strpos($narrative, '[CREATE_SUGGESTION]') === FALSE) {
      \Drupal::cache('default')->set($gm_response_cache_key, [
        'narrative' => $narrative,
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
      ], time() + 300, [
        'dungeoncrawler_content:campaign:' . $campaign_id,
      ]);
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
      $stage_started_at = hrtime(true);
      $canonical_execution = $this->gmOrchestrationBroker->executeCanonicalAuthoritativeActions(
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
      $this->recordDebugStage('gm.execute_canonical_actions', $stage_started_at, [
        'action_count' => count($actions),
        'error_count' => count($canonical_execution['errors'] ?? []),
      ]);
    }

    // Apply state mutations if there are mechanical actions.
    $char_diff = [];
    $room_diff = [];
    $state_diff = NULL;

    if (!empty($actions)) {
      // Apply character state changes.
      $stage_started_at = hrtime(true);
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
    elseif (!empty($validation_errors)) {
      $state_diff = $this->actionProcessor->buildStateDiffSummary(
        $char_diff, $room_diff, $dice_rolls, $actions, $validation_errors
      );
    }

    // Detect navigate_to_location actions and trigger map generation.
    $navigation_result = NULL;
    if (!empty($actions)) {
      $stage_started_at = hrtime(true);
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
      $this->recordDebugStage('gm.handle_navigation', $stage_started_at, [
        'navigation_success' => !empty($navigation_result) && empty($navigation_result['error']),
      ]);
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

    $stage_started_at = hrtime(true);
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();
    $this->recordDebugStage('gm.persist_reply', $stage_started_at, [
      'narrative_length' => strlen($narrative),
      'action_count' => count($actions),
    ]);

    // Record this exchange in the campaign room chat session for future context.
    $player_msg_text = end($chat)['message'] ?? '';
    $stage_started_at = hrtime(true);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $player_msg_text);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $narrative);

    // Bridge GM reply into hierarchical session system.
    $this->bridgeGmReplyToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $narrative, $actions, $dice_rolls
    );
    $this->recordDebugStage('gm.session_bridge', $stage_started_at, [
      'session_key' => $session_key,
    ]);

    $this->logger->info('GM reply persisted in room @room (@chars chars, @actions_count mechanical actions)', [
      '@room' => $room_id,
      '@chars' => strlen($narrative),
      '@actions_count' => count($actions),
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
    ];
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
    $this->recordDebugStage('gm.parse_primary_response', $stage_started_at, [
      'action_count' => count($actions),
      'dice_roll_count' => count($parsed['dice_rolls'] ?? []),
      'narrative_length' => strlen((string) ($parsed['narrative'] ?? '')),
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

      if (!empty($validation_errors)) {
        $snapshot = $this->actionProcessor->buildRealitySnapshot($character_data, $room_inventory);
        $retry_prompt = $prompt . "\n\n---\n" . $this->actionProcessor->buildRealityRetryPrompt($validation_errors, $snapshot);
        $retry_context = $context_data + [
          'reality_retry' => 1,
          'campaign_id' => $campaign_id,
        ];

        $stage_started_at = hrtime(true);
        $retry = $this->invokeGmModel($retry_prompt, $system_prompt, $retry_context, $room_id, 'room_chat_gm_retry', $prompt_debug_meta + [
          'attempt' => 2,
          'validation_error_count' => count($validation_errors),
          'snapshot_length' => strlen($snapshot),
        ]);
        $this->recordDebugStage('gm.llm_retry', $stage_started_at, [
          'success' => $retry !== NULL,
        ]);
        if ($retry !== NULL) {
          $stage_started_at = hrtime(true);
          $retry_parsed = $this->actionProcessor->parseResponse($retry);
          $retry_actions = $retry_parsed['actions'] ?? [];
          $retry_validation_errors = [];
          $this->recordDebugStage('gm.parse_retry_response', $stage_started_at, [
            'action_count' => count($retry_actions),
            'dice_roll_count' => count($retry_parsed['dice_rolls'] ?? []),
            'narrative_length' => strlen((string) ($retry_parsed['narrative'] ?? '')),
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
  protected function loadLatestDungeonSnapshot(int $campaign_id): array {
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
      $result = $this->invokeTimedModelCall(
        $prompt,
        'dungeoncrawler_content',
        'channel_npc_reply',
        $context_data,
        [
          'system_prompt' => "You are {$target_name}, a character in a tabletop RPG. Your current attitude toward the party is: {$npc_attitude}. Use the character sheet and psychology profile provided in the user prompt to stay in character. Reflect your personality traits, motivations, and recent inner thoughts in your tone and word choice. Do not break the fourth wall. Do not mention that you are an AI.",
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

    $directly_addressed_npc = $this->resolveDirectlyAddressedNpc($room_npcs, $player_message);
    $stage_started_at = hrtime(true);
    $ordered_npcs = $this->buildNpcInterjectionCandidates($room_npcs, $player_message, $gm_narrative, $directly_addressed_npc);
    $this->recordDebugStage('npc.candidate_filter', $stage_started_at, [
      'room_npc_count' => count($room_npcs),
      'candidate_count' => count($ordered_npcs),
      'direct_addressed' => $directly_addressed_npc['entity_ref'] ?? NULL,
    ]);
    if ($ordered_npcs === []) {
      $this->feedRoomChatToNpcSessions(
        $campaign_id,
        $room_npcs,
        $player_message,
        $gm_narrative,
        NULL,
        $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? [])
      );
      return [];
    }

    $messages = [];
    $spoken_refs = [];
    foreach ($ordered_npcs as $npc) {
      $is_directly_addressed = $directly_addressed_npc !== NULL
        && $npc['entity_ref'] === $directly_addressed_npc['entity_ref'];

      if (!$is_directly_addressed && !$this->shouldNpcTakeTurnThisRound(
        $campaign_id,
        $room_id,
        $room_index,
        $dungeon_data,
        $npc,
        $active_character_data,
        $player_message,
        $gm_narrative
      )) {
        continue;
      }

      $built_messages = $this->buildNpcInterjectionMessage(
        $campaign_id,
        $room_id,
        $room_index,
        $dungeon_id,
        $dungeon_data,
        $player_message,
        $gm_narrative,
        $room_npcs,
        $npc['entity_ref'],
        $npc['profile']['display_name'] ?? $npc['entity_ref'],
        FALSE
      );

      if (!empty($built_messages)) {
        $messages = array_merge($messages, $built_messages);
        $spoken_refs[] = $npc['entity_ref'];
      }
    }

    if (empty($messages)) {
      $this->feedRoomChatToNpcSessions(
        $campaign_id,
        $room_npcs,
        $player_message,
        $gm_narrative,
        NULL,
        $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? [])
      );
      return [];
    }

    $this->feedRoomChatToNpcSessions(
      $campaign_id,
      $room_npcs,
      $player_message,
      $gm_narrative,
      $spoken_refs,
      $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? [])
    );
    return $messages;
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

    $cache_key = 'dungeoncrawler_content:npc_turn:' . sha1(json_encode([
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'npc' => $npc['entity_ref'] ?? '',
      'player' => $player_message,
      'gm' => $gm_narrative,
      'transcript' => $this->buildRoomConversationTranscript($dungeon_data['rooms'][$room_index]['chat'] ?? [], 4),
      'attitude' => $npc['profile']['attitude'] ?? '',
    ]));
    $cache_started_at = hrtime(true);
    $cache = \Drupal::cache('default')->get($cache_key);
    if ($cache && isset($cache->data['speak'])) {
      $this->recordDebugStage('npc.turn_cache_hit', $cache_started_at, [
        'npc' => $npc['entity_ref'] ?? '',
      ]);
      return (bool) $cache->data['speak'];
    }

    $profile = $npc['profile'] ?? [];
    $desc = (string) ($profile['display_name'] ?? $npc['entity_ref']);
    $desc .= " — Attitude: " . ($profile['attitude'] ?? 'indifferent');
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
    catch (\Exception $e) {
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
    bool $feed_room_sessions = TRUE
  ): array {
    $npc_dialogue = $this->generateNpcRoomDialogue(
      $campaign_id, $room_id, $room_index, $dungeon_data,
      $speaker_ref, $speaker_name, $player_message, $gm_narrative
    );

    if (empty($npc_dialogue)) {
      $this->feedRoomChatToNpcSessions($campaign_id, $room_npcs, $player_message, $gm_narrative);
      return [];
    }

    // Build the NPC chat message.
    $npc_message = [
      'speaker' => $speaker_name,
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
    $context_for_npc = $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? []);
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

    if ($feed_room_sessions) {
      $this->feedRoomChatToNpcSessions(
        $campaign_id,
        $room_npcs,
        $player_message,
        $gm_narrative,
        [$speaker_ref],
        $this->buildRoomObservationFromChat($dungeon_data['rooms'][$room_index]['chat'] ?? [])
      );
    }

    // Bridge into hierarchical session system.
    $this->bridgeNpcInterjectionToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $speaker_name, $npc_dialogue, $speaker_ref
    );

    $this->logger->info('NPC interjection by @npc in room @room: @msg', [
      '@npc' => $speaker_name,
      '@room' => $room_id,
      '@msg' => substr($npc_dialogue, 0, 100),
    ]);

    return [$npc_message];
  }

  /**
   * Resolve a directly addressed NPC from player text without relying on the LLM.
   */
  protected function resolveDirectlyAddressedNpc(array $room_npcs, string $player_message): ?array {
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

      $score = $this->scoreNpcDirectAddressMatch($display_name, $message);
      if ($score <= 0) {
        continue;
      }

      $matches[] = [
        'score' => $score,
        'npc' => $npc,
      ];
    }

    if ($matches === []) {
      return NULL;
    }

    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    if (count($matches) > 1 && $matches[0]['score'] === $matches[1]['score']) {
      return NULL;
    }

    return $matches[0]['npc'];
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
      $score = $this->scoreNpcInterjectionCandidate($npc, $combined_text, $player_message, $gm_narrative);
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
    string $combined_text,
    string $player_message,
    string $gm_narrative
  ): int {
    $profile = $npc['profile'] ?? [];
    $display_name = (string) ($profile['display_name'] ?? '');
    $role = strtolower((string) ($profile['role'] ?? ''));
    $attitude = strtolower((string) ($profile['attitude'] ?? 'indifferent'));
    $motivations = strtolower((string) ($profile['motivations'] ?? ''));
    $normalized_player_message = $this->normalizeNpcNameForMatch($player_message);

    $score = 0;
    if ($display_name !== '') {
      $score = max($score, $this->scoreNpcDirectAddressMatch($display_name, $normalized_player_message) - 20);
    }

    if ($this->textContainsAny($combined_text, ['quest', 'job', 'task', 'mission', 'objective', 'reward', 'deliver', 'gather', 'help'])) {
      if (in_array($role, ['quest_giver', 'guide'], TRUE)) {
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
      if ($role === 'guide') {
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
    foreach ($keywords as $keyword) {
      $normalized_keyword = $this->normalizeNpcNameForMatch($keyword);
      if ($normalized_keyword !== '' && str_contains($normalized_text, $normalized_keyword)) {
        return TRUE;
      }
    }
    return FALSE;
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

    if (preg_match('/\b' . preg_quote($normalized_name, '/') . '\b/u', $normalized_message)) {
      return 100;
    }

    $tokens = preg_split('/\s+/', $normalized_name) ?: [];
    foreach ($tokens as $token) {
      if (strlen($token) < 4) {
        continue;
      }
      if (preg_match('/\b' . preg_quote($token, '/') . '\b/u', $normalized_message)) {
        return 90;
      }
    }

    $message_tokens = preg_split('/\s+/', $normalized_message) ?: [];
    foreach ($tokens as $token) {
      if (strlen($token) < 4) {
        continue;
      }
      foreach ($message_tokens as $message_token) {
        if (strlen($message_token) < 4) {
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
  protected function classifyRoomTurnIntent(string $player_message, array $room_npcs = [], ?array $directly_addressed_npc = NULL): string {
    $normalized = $this->normalizeNpcNameForMatch($player_message);
    if ($normalized === '') {
      return 'gm_narration';
    }

    if ($this->textContainsAny($normalized, ['ooc', 'out of character', 'meta'])) {
      return 'ooc_meta';
    }

    if ($this->textContainsAny($normalized, [
      'who is here',
      'who else is here',
      'who all is here',
      'who is in here',
      'who is in the room',
      'who all is in the room',
      'who is present',
      'who is around',
      'who can we talk',
      'who can i talk',
      'their demeanor',
      'their demeanour',
      'what is their demeanor',
      'what is their demeanour',
      'describe everyone here',
      'who is in the room and',
    ])) {
      return 'room_roster_query';
    }

    if ($this->looksLikeNavigationQuery($normalized)) {
      return 'navigation_query';
    }

    if ($this->looksLikeNavigationTurn($normalized)) {
      return 'navigation_travel';
    }

    if ($this->looksLikeCombatEngagementTurn($normalized)) {
      return 'combat_engagement';
    }

    if ($directly_addressed_npc !== NULL) {
      if ($this->textContainsAny($normalized, ['buy', 'sell', 'price', 'cost', 'coin', 'gold', 'silver', 'copper', 'change', 'pay', 'paid', 'torch', 'ale', 'drink', 'room', 'rent', 'tab'])) {
        return 'direct_npc_transaction';
      }
      return 'direct_npc_dialogue';
    }

    if ($this->textContainsAny($normalized, ['quest', 'job', 'task', 'mission', 'reward', 'objective'])) {
      foreach ($room_npcs as $npc) {
        $role = strtolower((string) (($npc['profile']['role'] ?? $npc['entity']['role'] ?? '')));
        if (in_array($role, ['quest_giver', 'guide'], TRUE)) {
          return 'quest_query';
        }
      }
    }

    return 'gm_narration';
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
    string $player_message,
    array $room_meta = [],
    string $room_id = '',
    array $dungeon_data = []
  ): ?array {
    if ($intent === 'combat_engagement') {
      $hostiles = $this->findRoomHostileEntities($room_id, $dungeon_data, $player_message);
      if ($hostiles !== []) {
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
          ? 'You open the fight by casting Sleep into the swarm and ordering your allies to strike. Combat begins against ' . $target_summary . '.'
          : 'The moment the order is given, the standoff breaks and combat begins against ' . $target_summary . '.';
        return [
          'narrative' => $narrative,
          'actions' => [[
            'type' => 'combat_initiation',
            'name' => 'Engage hostiles',
            'details' => [
              'combat' => [
                'reason' => 'The player commits to immediate violence against the hostile creatures in the room.',
                'enemy_entity_ids' => array_values(array_unique($hostile_ids)),
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
      $navigation_action = $this->buildDeterministicNavigationAction($player_message, $room_meta, $room_id, $dungeon_data);
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
      $roster_narrative = $this->buildDeterministicRoomRosterNarrative($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
      if ($roster_narrative === '') {
        return [
          'narrative' => 'No one in this room looks ready to answer right now.',
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

    if (($intent === 'direct_npc_dialogue' || $intent === 'direct_npc_transaction') && $directly_addressed_npc !== NULL) {
      $name = $directly_addressed_npc['profile']['display_name'] ?? $directly_addressed_npc['entity_ref'] ?? 'The NPC';
      if ($intent === 'direct_npc_transaction') {
        return [
          'narrative' => "{$name} turns toward you, ready to answer the practical details directly.",
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
        ];
      }
      return [
        'narrative' => "{$name} shifts attention to you, ready to answer in their own words.",
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
      ];
    }

    if ($intent === 'quest_query') {
      $quest_givers = [];
      foreach ($room_npcs as $npc) {
        $role = strtolower((string) (($npc['profile']['role'] ?? $npc['entity']['role'] ?? '')));
        if (in_array($role, ['quest_giver', 'guide'], TRUE)) {
          $name = trim((string) ($npc['profile']['display_name'] ?? ''));
          if ($name !== '') {
            $quest_givers[] = $name;
          }
        }
      }
      if ($quest_givers !== []) {
        return [
          'narrative' => implode(' and ', array_slice($quest_givers, 0, 2)) . ' both look like the people most likely to have useful leads or work for you.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
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
    $descriptions = [];
    foreach ($room_npcs as $npc) {
      $name = trim((string) ($npc['profile']['display_name'] ?? ''));
      if ($name === '') {
        continue;
      }

      $profile = is_array($npc['profile'] ?? NULL) ? $npc['profile'] : [];
      $role = trim((string) ($profile['role'] ?? ''));
      $attitude = trim((string) ($profile['attitude'] ?? ''));
      $personality = trim((string) ($profile['personality'] ?? $profile['personality_traits'] ?? ''));

      $parts = [];
      if ($role !== '') {
        $parts[] = $role;
      }
      if ($attitude !== '') {
        $parts[] = 'demeanor: ' . $this->truncateContextBlock($attitude, 48, 0.9);
      }
      elseif ($personality !== '') {
        $parts[] = 'demeanor: ' . $this->truncateContextBlock($personality, 64, 0.8);
      }

      $descriptions[] = $parts === []
        ? $name . ' is present.'
        : $name . ' (' . implode('; ', $parts) . ').';
    }

    if ($descriptions === []) {
      $actor_notes = $this->buildRoomActorGroundingSummary($campaign_id, $room_id, $dungeon_data);
      if ($actor_notes !== '') {
        return 'Visible named occupants in ' . ($room_meta['name'] ?? 'the room') . ": \n" . preg_replace('/^- /m', '', $actor_notes);
      }
      return '';
    }

    return 'In ' . ($room_meta['name'] ?? 'the room') . ', ' . implode(' ', $descriptions);
  }

  /**
   * Determine whether the player is leaving the current location.
   */
  protected function looksLikeNavigationTurn(string $normalized_message): bool {
    if ($this->extractNavigationDestination($normalized_message) !== NULL) {
      return TRUE;
    }

    return $this->textContainsAny($normalized_message, [
      'leave the ',
      'leave this ',
      'i leave',
      'we leave',
      'go to the next room',
      'head to the next room',
      'move to the next room',
      'go deeper',
      'head deeper',
      'move deeper',
      'press farther',
      'press further',
      'push on',
      'go in',
      'go inside',
      'head in',
      'head inside',
      'enter the ',
      'enter through',
      'open the door',
      'open that door',
      'open this door',
      'break down the door',
      'kick in the door',
      'bust it loose',
      'lets open the door',
      'let us open the door',
      'go outside',
      'head outside',
      'step outside',
      'meet you there',
    ]);
  }

  /**
   * Determine whether the player is asking about nearby rooms or exits.
   */
  protected function looksLikeNavigationQuery(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'what is the next room',
      'what s the next room',
      'what is in the next room',
      'what s in the next room',
      'what do we find in the next room',
      'what is beyond the door',
      'what s beyond the door',
      'what is through the door',
      'what s through the door',
      'where does the door go',
      'where does this door go',
      'where does that door go',
      'what is ahead',
      'what s ahead',
    ]);
  }

  /**
   * Determine whether the player is clearly starting a fight.
   */
  protected function looksLikeCombatEngagementTurn(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'i attack',
      'we attack',
      'attack the ',
      'kill the ',
      'kill all these ',
      'kill all the ',
      'kill those ',
      'fight the ',
      'fight those ',
      'engage the ',
      'engage those ',
      'lets kill',
      'let us kill',
      'lets fight',
      'let us fight',
      'smash them',
      'start smashing',
      'wipe them out',
      'take them down',
      'start combat',
      'begin combat',
    ]);
  }

  /**
   * Build a deterministic navigation action from a travel-style player turn.
   */
  protected function buildDeterministicNavigationAction(string $player_message, array $room_meta = [], string $room_id = '', array $dungeon_data = []): ?array {
    $destination = $this->extractNavigationDestination($player_message, $room_meta, $room_id, $dungeon_data);
    if ($destination === NULL) {
      return NULL;
    }

    $origin_name = trim((string) ($room_meta['name'] ?? 'the room'));
    $destination_description = 'A new area reached from ' . $origin_name . ' by moving toward ' . $destination . '.';
    $normalized = $this->normalizeNpcNameForMatch($player_message);
    $door_move = $this->textContainsAny($normalized, [
      'open the door',
      'go in',
      'go inside',
      'head in',
      'head inside',
      'enter the ',
      'enter through',
    ]);
    $narrative = $door_move
      ? 'You move to the door, open it, and press onward toward ' . $destination . '.'
      : 'You leave ' . $origin_name . ' and head toward ' . $destination . '.';

    return [
      'narrative' => $narrative,
      'action' => [
        'type' => 'navigate_to_location',
        'name' => 'Travel to ' . $destination,
        'details' => [
          'destination' => $destination,
          'destination_description' => $destination_description,
          'travel_type' => 'walk',
          'estimated_distance' => 'short',
        ],
        'state_changes' => [
          'character' => [],
          'room' => [],
        ],
      ],
    ];
  }

  /**
   * Extract a destination phrase from a player navigation message.
   */
  protected function extractNavigationDestination(string $player_message, array $room_meta = [], string $room_id = '', array $dungeon_data = []): ?string {
    $patterns = [
      '/(?:leave(?:\s+for)?|head(?:ing)?\s+(?:to|for)|travel(?:ing)?\s+(?:to|for)|journey(?:ing)?\s+(?:to|for)|set out for|depart for|go to|navigation to|navigating to)\s+(?:the\s+)?([a-z0-9][a-z0-9\'\-\s]+)/i',
      '/(?:meet you there\.?\s*then i leave for)\s+(?:the\s+)?([a-z0-9][a-z0-9\'\-\s]+)/i',
      '/(?:open(?:ing)?\s+(?:the\s+)?door\s+and\s+(?:go|head|step|move|walk|enter)\s+(?:in|inside|through)|enter(?:ing)?\s+(?:the\s+)?door|go(?:ing)?\s+(?:in|inside)|head(?:ing)?\s+(?:in|inside))\b/i',
    ];
    foreach ($patterns as $pattern) {
      if (!preg_match($pattern, $player_message, $matches)) {
        continue;
      }
      if (count($matches) < 2) {
        return 'Beyond the door';
      }
      $destination = trim((string) ($matches[1] ?? ''));
      $destination = preg_replace('/\s+(?:with|and|then|after|before)\b.*$/i', '', $destination) ?? $destination;
      $destination = preg_replace('/\s+(?:again|now|please|today|tonight|tomorrow|asap|immediately|right now)\b.*$/i', '', $destination) ?? $destination;
      $destination = trim($destination, " \t\n\r\0\x0B.,!?;:\"'");
      if ($destination !== '') {
        return ucwords(strtolower($destination));
      }
    }

    $normalized = $this->normalizeNpcNameForMatch($player_message);
    if ($this->textContainsAny($normalized, ['go outside', 'head outside', 'step outside', 'leave the tavern'])) {
      $origin_name = trim((string) ($room_meta['name'] ?? 'the building'));
      return 'Outside ' . $origin_name;
    }

    $preferred_exit = $this->resolvePreferredNavigationExit($room_meta, $room_id, $dungeon_data);
    if ($preferred_exit !== NULL && $this->textContainsAny($normalized, [
      'next room',
      'through the door',
      'beyond the door',
      'go deeper',
      'head deeper',
      'move deeper',
      'press farther',
      'press further',
      'push on',
      'break down the door',
      'kick in the door',
      'bust it loose',
    ])) {
      return $preferred_exit['name'];
    }

    return NULL;
  }

  /**
   * Build a grounded answer for nearby-room / exit questions.
   */
  protected function buildDeterministicNavigationQueryNarrative(array $room_meta = [], string $room_id = '', array $dungeon_data = []): string {
    $current_room_id = $room_id !== '' ? $room_id : (string) ($room_meta['room_id'] ?? '');
    if ($current_room_id === '') {
      return '';
    }

    $exits = $this->actionProcessor->getResolvedRoomExits($dungeon_data, $current_room_id);
    if ($exits === []) {
      return 'No grounded exit is mapped from this room yet.';
    }

    $origin_name = trim((string) ($room_meta['name'] ?? 'this room'));
    $preferred_exit = $this->resolvePreferredNavigationExit($room_meta, $room_id, $dungeon_data);
    $formatted_exits = array_map(function (array $exit): string {
      $name = trim((string) ($exit['name'] ?? 'Unknown passage'));
      $type = trim((string) ($exit['connection_type'] ?? 'passage'));
      $status = !empty($exit['explored']) ? 'visited' : 'unexplored';
      return "{$name} ({$type}, {$status})";
    }, $exits);

    $narrative = 'From ' . $origin_name . ', the grounded exits are ' . implode('; ', $formatted_exits) . '.';
    if ($preferred_exit !== NULL) {
      $preferred_name = trim((string) ($preferred_exit['name'] ?? 'the next passage'));
      $preferred_status = !empty($preferred_exit['explored']) ? 'already explored' : 'the next unexplored room';
      $narrative .= ' If you press forward, ' . $preferred_name . ' is ' . $preferred_status . '.';
    }

    return $narrative;
  }

  /**
   * Choose the most likely forward exit for generic travel language.
   */
  protected function resolvePreferredNavigationExit(array $room_meta = [], string $room_id = '', array $dungeon_data = []): ?array {
    $current_room_id = $room_id !== '' ? $room_id : (string) ($room_meta['room_id'] ?? '');
    if ($current_room_id === '') {
      return NULL;
    }

    $exits = $this->actionProcessor->getResolvedRoomExits($dungeon_data, $current_room_id);
    if ($exits === []) {
      return NULL;
    }

    foreach ($exits as $exit) {
      if (empty($exit['explored']) && !empty($exit['name'])) {
        return $exit;
      }
    }

    $backtrack_room_id = (string) ($dungeon_data['last_navigation']['from_room_id'] ?? '');
    foreach ($exits as $exit) {
      if (($exit['room_id'] ?? '') !== $backtrack_room_id && !empty($exit['name'])) {
        return $exit;
      }
    }

    foreach ($exits as $exit) {
      if (!empty($exit['name'])) {
        return $exit;
      }
    }

    return NULL;
  }

  /**
   * Collect hostile entities present in the current room.
   */
  protected function findRoomHostileEntities(string $room_id, array $dungeon_data, string $player_message = ''): array {
    if ($room_id === '') {
      return [];
    }

    $room_entities = [];
    $hostiles = [];
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if (($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $room_entities[] = $entity;
      $team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));
      if (in_array($team, ['hostile', 'enemy', 'monsters'], TRUE)) {
        $hostiles[] = $entity;
      }
    }

    if ($hostiles !== []) {
      return $hostiles;
    }

    $normalized_message = $this->normalizeNpcNameForMatch($player_message);
    if ($normalized_message === '' || $room_entities === []) {
      return [];
    }

    $keywords = [];
    foreach (['rat', 'vermin', 'goblin', 'spider', 'skeleton', 'zombie', 'wolf', 'bat'] as $keyword) {
      if (str_contains($normalized_message, $keyword)) {
        $keywords[] = $keyword;
      }
    }
    if ($keywords === []) {
      return [];
    }

    $matched = [];
    foreach ($room_entities as $entity) {
      $team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));
      if (in_array($team, ['player', 'ally', 'friendly', 'party'], TRUE)) {
        continue;
      }

      $haystack = strtolower(implode(' ', array_filter([
        (string) ($entity['state']['metadata']['display_name'] ?? ''),
        (string) ($entity['name'] ?? ''),
        (string) ($entity['entity_ref']['content_id'] ?? ''),
        (string) ($entity['state']['metadata']['creature_type'] ?? ''),
        (string) ($entity['type'] ?? ''),
      ])));
      foreach ($keywords as $keyword) {
        if (str_contains($haystack, $keyword)) {
          $matched[] = $entity;
          break;
        }
      }
    }

    if ($matched !== []) {
      return $matched;
    }

    return $hostiles;
  }

  /**
   * Trim clearly incomplete GM output back to the last complete sentence.
   */
  protected function trimIncompleteNarrative(string $narrative): string {
    $narrative = trim($narrative);
    if ($narrative === '') {
      return '';
    }

    if (preg_match('/[.!?]["\')\]}]*$/u', $narrative)) {
      return $narrative;
    }

    $looks_truncated = (bool) preg_match('/\b[\pL\pN]{1,3}$/u', $narrative);
    $length = strlen($narrative);
    for ($i = $length - 1; $i >= 0; $i--) {
      if (!in_array($narrative[$i], ['.', '!', '?'], TRUE)) {
        continue;
      }
      if (!$looks_truncated && $i < (int) floor($length * 0.55)) {
        break;
      }
      return trim(substr($narrative, 0, $i + 1));
    }

    return $narrative;
  }

  /**
   * Remove visible JSON/code-block action output from player-facing narrative.
   */
  protected function stripPlayerVisibleActionBlocks(string $narrative): string {
    $narrative = preg_replace('/\n?```(?:json)?[\s\S]*$/i', '', $narrative) ?? $narrative;
    $narrative = preg_replace('/\n?\{\s*"actions"\s*:\s*\[[\s\S]*$/i', '', $narrative) ?? $narrative;
    $narrative = preg_replace('/\s*(?:Here(?:\'s| is)\s+the\s+JSON\s+action\s+block.*|JSON\s+action\s+block:.*)$/i', '', $narrative) ?? $narrative;

    return trim($narrative);
  }

  /**
   * Resolve an NPC by model-returned speaker name.
   */
  protected function resolveNamedRoomNpc(array $room_npcs, string $speaker_name): ?array {
    $normalized_speaker = $this->normalizeNpcNameForMatch($speaker_name);
    if ($normalized_speaker === '') {
      return NULL;
    }

    foreach ($room_npcs as $npc) {
      $display_name = (string) ($npc['profile']['display_name'] ?? '');
      if ($display_name !== '' && strcasecmp($display_name, $speaker_name) === 0) {
        return $npc;
      }
    }

    $matches = [];
    foreach ($room_npcs as $npc) {
      $display_name = (string) ($npc['profile']['display_name'] ?? '');
      $score = $this->scoreNpcDirectAddressMatch($display_name, $normalized_speaker);
      if ($score > 0) {
        $matches[] = ['score' => $score, 'npc' => $npc];
      }
    }

    if ($matches === []) {
      if (count($room_npcs) === 1) {
        $only_npc = $room_npcs[0];
        $this->logger->info('NPC alias resolved: @alias → @canonical', [
          '@alias' => $speaker_name,
          '@canonical' => $only_npc['profile']['display_name'] ?? $only_npc['entity_ref'],
        ]);
        return $only_npc;
      }

      return NULL;
    }

    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    if (count($matches) > 1 && $matches[0]['score'] === $matches[1]['score']) {
      return NULL;
    }

    return $matches[0]['npc'];
  }

  /**
   * Normalize free text for forgiving NPC name matching.
   */
  protected function normalizeNpcNameForMatch(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\s]+/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
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
    $deterministic_reply = $this->buildDeterministicNpcDialogue($campaign_id, $entity_ref, $display_name, $player_message);
    if ($deterministic_reply !== NULL) {
      $this->recordDebugStage('npc.deterministic_reply', hrtime(true), [
        'npc_entity' => $entity_ref,
        'length' => strlen($deterministic_reply),
      ]);
      return $deterministic_reply;
    }

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
    $session_context = $this->buildCompactSessionContext($session_key, $campaign_id, 3, 900, 320);

    // Get recent room chat for conversational flow.
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $recent = array_slice($chat, -4);
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      if (strlen($text) > 220) {
        $text = substr($text, 0, 217) . '...';
      }
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
      $result = $this->invokeTimedModelCall(
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
        ],
        [
          'npc_entity' => $entity_ref,
          'display_name' => $display_name,
          'history_line_count' => count($history_lines),
          'session_context_length' => strlen($session_context),
          'npc_context_length' => strlen($npc_context),
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('NPC room dialogue generation failed for @npc: @err', [
        '@npc' => $entity_ref,
        '@err' => $e->getMessage(),
      ]);
      return $this->buildFallbackNpcRoomDialogue($campaign_id, $entity_ref, $display_name, $player_message);
    }

    if (empty($result['success']) || empty($result['response'])) {
      return $this->buildFallbackNpcRoomDialogue($campaign_id, $entity_ref, $display_name, $player_message);
    }

    return trim($result['response']);
  }

  /**
   * Build deterministic NPC dialogue for common low-variance turns.
   */
  protected function buildDeterministicNpcDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $player_message
  ): ?string {
    $normalized = $this->normalizeNpcNameForMatch($player_message);
    if ($normalized === '') {
      return NULL;
    }

    $profile = $this->psychologyService->loadProfile($campaign_id, $entity_ref) ?? [];
    $attitude = strtolower((string) ($profile['attitude'] ?? 'indifferent'));
    $role = strtolower((string) ($profile['role'] ?? ''));
    $descriptor = strtolower($display_name . ' ' . $entity_ref . ' ' . ($profile['motivations'] ?? ''));

    if ($this->textContainsAny($normalized, ['hello', 'hi', 'hey', 'greetings'])) {
      return match ($attitude) {
        'friendly', 'helpful' => "\"Hello. What can I do for you?\"",
        'unfriendly', 'hostile' => "\"Make it quick.\"",
        default => "\"What do you need?\"",
      };
    }

    if ($this->textContainsAny($normalized, ['thanks', 'thank you'])) {
      return match ($attitude) {
        'friendly', 'helpful' => "\"You're welcome.\"",
        'unfriendly', 'hostile' => "\"Mm.\"",
        default => "\"Right.\"",
      };
    }

    if ($this->textContainsAny($normalized, ['quest', 'job', 'task', 'mission', 'work', 'reward', 'objective']) && in_array($role, ['quest_giver', 'guide'], TRUE)) {
      return "\"If you're asking about work, be specific — I can point you toward leads, objectives, or anything ready to turn in.\"";
    }

    if ($this->textContainsAny($normalized, ['buy', 'sell', 'price', 'cost', 'coin', 'gold', 'silver', 'copper', 'change', 'pay', 'paid', 'torch', 'ale', 'drink', 'room', 'rent', 'tab'])
      && $this->textContainsAny($descriptor, ['keeper', 'merchant', 'vendor', 'shop', 'tavern', 'inn', 'bar', 'sell'])) {
      if ($this->textContainsAny($normalized, ['change', 'pay', 'paid', 'coin', 'gold', 'silver', 'copper'])) {
        return "\"State the item, quantity, and what coin you're paying with, and I'll settle the amount cleanly.\"";
      }
      return "\"Name what you want and how much of it, and I'll give you the price plainly.\"";
    }

    return NULL;
  }

  /**
   * Provide a deterministic line when NPC dialogue generation fails.
   */
  protected function buildFallbackNpcRoomDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $player_message
  ): string {
    $attitude = $this->psychologyService->getAttitude($campaign_id, $entity_ref) ?? 'indifferent';
    $player_message = trim($player_message);

    return match ($attitude) {
      'helpful', 'friendly' => sprintf('%s nods. "I hear you. What do you need?"', $display_name),
      'hostile' => sprintf('%s glares. "Choose your next words carefully."', $display_name),
      'unfriendly' => sprintf('%s looks up with obvious reluctance. "I am listening. Speak quickly."', $display_name),
      default => sprintf('%s looks up. "You have my attention. What is it?"', $display_name),
    };
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
   * Build canonical grounding notes for named campaign actors in the active room.
   */
  protected function buildRoomActorGroundingSummary(int $campaign_id, string $room_id, array $dungeon_data): string {
    $room_slug = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data);
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['name', 'type', 'role', 'class', 'ancestry', 'instance_id', 'character_data', 'last_room_id', 'location_ref'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', ['pc', 'npc'], 'IN')
      ->range(0, 8);

    $room_match = $query->orConditionGroup()
      ->condition('last_room_id', $room_id);
    if ($room_slug) {
      $room_match->condition('location_ref', $room_slug);
    }
    $rows = $query
      ->condition($room_match)
      ->execute()
      ->fetchAll();

    if (!$rows) {
      return '';
    }

    $lines = [];
    $seen_names = [];
    foreach ($rows as $row) {
      $name = trim((string) ($row->name ?? ''));
      if ($name === '') {
        continue;
      }
      $name_key = strtolower($name);
      if (isset($seen_names[$name_key])) {
        continue;
      }
      $seen_names[$name_key] = TRUE;

      $character_data = json_decode((string) ($row->character_data ?? '{}'), TRUE);
      if (!is_array($character_data)) {
        $character_data = [];
      }
      $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
      $profile = is_array($character_data['profile'] ?? NULL) ? $character_data['profile'] : [];
      $sheet = is_array($profile['character_sheet'] ?? NULL)
        ? $profile['character_sheet']
        : (is_array($character_data['character_sheet'] ?? NULL) ? $character_data['character_sheet'] : []);

      $type = strtolower((string) ($row->type ?? ''));
      $role = trim((string) ($profile['role'] ?? $row->role ?? ''));
      $ancestry = trim((string) ($basic_info['ancestry'] ?? $row->ancestry ?? ''));
      $class = trim((string) ($basic_info['class'] ?? $row->class ?? ''));
      $appearance = trim((string) ($basic_info['appearance'] ?? $profile['appearance'] ?? $sheet['appearance'] ?? $sheet['description'] ?? $character_data['appearance'] ?? ''));
      $personality = trim((string) ($basic_info['personality'] ?? $profile['personality_traits'] ?? $profile['personality'] ?? $character_data['personality'] ?? ''));
      $attitude = trim((string) ($profile['attitude'] ?? $character_data['attitude'] ?? ''));
      $motivations = trim((string) ($profile['motivations'] ?? $character_data['motivations'] ?? ''));

      $parts = [];
      if ($type === 'pc') {
        $identity = trim(implode(' ', array_filter([$ancestry, $class])));
        if ($identity !== '') {
          $parts[] = $identity;
        }
      }
      elseif ($role !== '') {
        $parts[] = 'role: ' . $this->truncateContextBlock($role, 72, 0.9);
      }
      if ($appearance !== '') {
        $parts[] = 'appearance: ' . $this->truncateContextBlock($appearance, 120, 0.8);
      }
      if ($personality !== '') {
        $parts[] = 'personality: ' . $this->truncateContextBlock($personality, 96, 0.8);
      }
      if ($attitude !== '') {
        $parts[] = 'attitude: ' . $this->truncateContextBlock($attitude, 72, 0.9);
      }
      if ($motivations !== '') {
        $parts[] = 'motivations: ' . $this->truncateContextBlock($motivations, 96, 0.8);
      }

      if ($parts !== []) {
        $lines[] = '- ' . $name . ' — ' . implode('; ', $parts);
      }
    }

    return $lines !== []
      ? "Canonical actor notes for named room occupants:\n" . implode("\n", $lines)
      : '';
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
      !empty($row->instance_id) ? preg_replace('/^npc_/', '', (string) $row->instance_id) : NULL,
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
    array|string|null $skip_refs = NULL,
    ?string $room_observation = NULL
  ): void {
    $observation = $room_observation ?: "Overheard in the room — Player: {$player_message} | GM reply: {$gm_narrative}";
    $skip_lookup = [];
    if (is_string($skip_refs) && $skip_refs !== '') {
      $skip_lookup[$skip_refs] = TRUE;
    }
    elseif (is_array($skip_refs)) {
      foreach ($skip_refs as $skip_ref) {
        if (is_string($skip_ref) && $skip_ref !== '') {
          $skip_lookup[$skip_ref] = TRUE;
        }
      }
    }

    foreach ($room_npcs as $npc) {
      if (isset($skip_lookup[$npc['entity_ref']])) {
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
   * Build a concise room-conversation transcript for NPC prompting.
   */
  protected function buildRoomConversationTranscript(array $chat, int $limit = 8): string {
    $recent = array_slice($chat, -1 * max(1, $limit));
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      if (strlen($text) > 180) {
        $text = substr($text, 0, 177) . '...';
      }
      $history_lines[] = "{$speaker}: {$text}";
    }

    return $history_lines === [] ? '[No recent room dialogue]' : implode("\n", $history_lines);
  }

  /**
   * Build a smaller structured session context block for chat prompts.
   */
  protected function buildCompactSessionContext(
    string $session_key,
    int $campaign_id,
    int $max_recent = 3,
    int $max_chars = 1200,
    int $max_summary_chars = 400
  ): string {
    $context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, $max_recent);
    if ($context === '') {
      return '';
    }

    $sections = preg_split("/\n\s*\n/", $context) ?: [];
    $parts = [];
    foreach ($sections as $section) {
      $section = trim($section);
      if ($section === '') {
        continue;
      }

      if (str_starts_with($section, 'PRIOR SESSION CONTEXT')) {
        [$heading, $body] = array_pad(explode("\n", $section, 2), 2, '');
        $body = trim($body);
        if (strlen($body) > $max_summary_chars) {
          $body = substr($body, 0, $max_summary_chars - 3) . '...';
        }
        if ($body !== '') {
          $parts[] = $heading . "\n" . $body;
        }
        continue;
      }

      if (str_starts_with($section, 'RECENT CONVERSATION')) {
        [$heading, $body] = array_pad(explode("\n", $section, 2), 2, '');
        $lines = preg_split("/\r?\n/", trim($body)) ?: [];
        $lines = array_slice(array_values(array_filter(array_map('trim', $lines))), -$max_recent);
        foreach ($lines as &$line) {
          if (strlen($line) > 180) {
            $line = substr($line, 0, 177) . '...';
          }
        }
        unset($line);
        if ($lines !== []) {
          $parts[] = $heading . "\n" . implode("\n", $lines);
        }
        continue;
      }

      $parts[] = $section;
    }

    $compact = implode("\n\n", $parts);
    if (strlen($compact) > $max_chars) {
      $compact = substr($compact, 0, $max_chars - 3) . '...';
    }

    return $compact;
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
    catch (\Exception $e) {
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
    $cache_state = [
      'room_id' => $room_id,
      'room_name' => $room_meta['name'] ?? '',
      'room_description' => $room_meta['description'] ?? '',
      'room_entities' => $room_meta['entities'] ?? [],
      'top_entities' => $dungeon_data['entities'] ?? [],
      'room_npcs' => array_map(static function (array $npc): array {
        return [
          'entity_ref' => $npc['entity_ref'] ?? '',
          'display_name' => $npc['profile']['display_name'] ?? '',
          'role' => $npc['profile']['role'] ?? ($npc['entity']['role'] ?? ''),
          'attitude' => $npc['profile']['attitude'] ?? '',
        ];
      }, $room_npcs),
    ];
    $cache_key = 'dungeoncrawler_content:room_prompt_artifacts:' . $campaign_id . ':' . sha1(json_encode($cache_state));
    $cache = \Drupal::cache('default')->get($cache_key);
    if ($cache && is_array($cache->data)) {
      $cache->data['cache'] = 'hit';
      return $cache->data;
    }

    $scene_parts = [];
    if (!empty($room_meta['name'])) {
      $scene_parts[] = 'Current room: ' . $this->truncateContextBlock((string) $room_meta['name'], 96, 0.85);
    }
    if (!empty($room_meta['description'])) {
      $scene_parts[] = 'Room description: ' . $this->truncateContextBlock((string) $room_meta['description'], 240, 0.75);
    }

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
      $scene_parts[] = 'Beings/objects present: ' . $this->truncateContextBlock(implode(', ', $entity_names), 260, 0.7);
    }

    $npc_names = [];
    $quest_givers = [];
    $merchants = [];
    $npc_profile_lines = [];
    foreach ($room_npcs as $npc) {
      $name = trim((string) ($npc['profile']['display_name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $npc_names[] = $name;
      $role = strtolower((string) ($npc['profile']['role'] ?? ($npc['entity']['role'] ?? '')));
      $descriptor = strtolower($name . ' ' . ($npc['entity_ref'] ?? '') . ' ' . ($npc['profile']['motivations'] ?? ''));
      if (in_array($role, ['quest_giver', 'guide'], TRUE)) {
        $quest_givers[] = $name;
      }
      if ($this->textContainsAny($descriptor, ['keeper', 'merchant', 'vendor', 'shop', 'tavern', 'inn', 'bar', 'sell'])) {
        $merchants[] = $name;
      }
      if (count($npc_profile_lines) < 4) {
        $profile_parts = [];
        $sheet = $npc['profile']['character_sheet'] ?? [];
        $occupation = trim((string) ($sheet['occupation'] ?? ''));
        $attitude = trim((string) ($npc['profile']['attitude'] ?? ''));
        $traits = trim((string) ($npc['profile']['personality_traits'] ?? ''));
        $motivations = trim((string) ($npc['profile']['motivations'] ?? ''));
        // character_sheet.description is a visual/appearance note, not a generic profile blob.
        $appearance = trim((string) ($sheet['appearance'] ?? $sheet['description'] ?? ''));
        if ($occupation !== '') {
          $profile_parts[] = 'occupation: ' . $this->truncateContextBlock($occupation, 72, 0.9);
        }
        if ($attitude !== '') {
          $profile_parts[] = 'attitude: ' . $this->truncateContextBlock($attitude, 72, 0.9);
        }
        if ($traits !== '') {
          $profile_parts[] = 'traits: ' . $this->truncateContextBlock($traits, 96, 0.8);
        }
        if ($motivations !== '') {
          $profile_parts[] = 'motivations: ' . $this->truncateContextBlock($motivations, 96, 0.8);
        }
        if ($appearance !== '') {
          $profile_parts[] = 'appearance: ' . $this->truncateContextBlock($appearance, 120, 0.8);
        }
        if ($profile_parts !== []) {
          $npc_profile_lines[] = '- ' . $name . ' — ' . implode('; ', $profile_parts);
        }
      }
    }

    $artifacts = [
      'scene_parts' => $scene_parts,
      'entity_count' => count($entities),
      'entity_summary_count' => count($entity_names),
      'npc_roster_summary' => $npc_names !== []
        ? $this->truncateContextBlock('People ready to answer in this room: ' . implode(', ', array_slice($npc_names, 0, 4)) . '.', 180, 0.85)
        : '',
      'npc_profile_summary' => $npc_profile_lines !== []
        ? $this->truncateContextBlock("NPC profile notes for GM use:\n" . implode("\n", $npc_profile_lines), 520, 0.7)
        : '',
      'merchant_summary' => $merchants !== []
        ? $this->truncateContextBlock('Likely merchants or practical sellers here: ' . implode(', ', array_slice($merchants, 0, 3)) . '.', 180, 0.85)
        : '',
      'quest_summary' => $quest_givers !== []
        ? $this->truncateContextBlock('Likely quest or guidance contacts here: ' . implode(', ', array_slice($quest_givers, 0, 3)) . '.', 180, 0.85)
        : '',
      'cache' => 'miss',
    ];

    \Drupal::cache('default')->set($cache_key, $artifacts, time() + 600, [
      'dungeoncrawler_content:campaign:' . $campaign_id,
    ]);

    return $artifacts;
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
