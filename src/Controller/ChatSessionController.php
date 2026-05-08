<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for the hierarchical chat session system.
 *
 * Provides REST endpoints for reading/writing to the normalized
 * dc_chat_sessions / dc_chat_messages tables. This is the successor
 * to the legacy RoomChatController which stores messages in dungeon_data JSON.
 *
 * Endpoint summary:
 *   GET  /api/campaign/{cid}/sessions              — list session tree
 *   GET  /api/campaign/{cid}/sessions/{sid}/messages — paginated messages
 *   POST /api/campaign/{cid}/sessions/{sid}/messages — post message
 *   GET  /api/campaign/{cid}/narrative/{char_id}    — character's narrative feed
 *   GET  /api/campaign/{cid}/party-chat              — party chat messages
 *   GET  /api/campaign/{cid}/gm-private/{char_id}   — GM private channel
 *   GET  /api/campaign/{cid}/system-log              — system log messages
 *   POST /api/campaign/{cid}/narration/flush         — force-flush narration buffer
 */
class ChatSessionController extends ControllerBase {

  protected ChatSessionManager $sessionManager;
  protected NarrationEngine $narrationEngine;
  protected RoomChatService $chatService;

  public function __construct(
    ChatSessionManager $session_manager,
    NarrationEngine $narration_engine,
    RoomChatService $chat_service
  ) {
    $this->sessionManager = $session_manager;
    $this->narrationEngine = $narration_engine;
    $this->chatService = $chat_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.chat_session_manager'),
      $container->get('dungeoncrawler_content.narration_engine'),
      $container->get('dungeoncrawler_content.room_chat_service')
    );
  }

  // =========================================================================
  // Session tree.
  // =========================================================================

  /**
   * List all sessions for a campaign (tree structure).
   *
   * GET /api/campaign/{campaign_id}/sessions?type=room&status=active
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   { success: true, data: { sessions: [...], root_session_id: int } }
   */
  public function listSessions(int $campaign_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $type_filter = $request->query->get('type');
      $include_archived = $request->query->get('include_archived', '0') === '1';

      // Get the campaign root.
      $root_key = $this->sessionManager->campaignSessionKey($campaign_id);
      $root = $this->sessionManager->loadSession($root_key);

      if (!$root) {
        return new JsonResponse([
          'success' => TRUE,
          'data' => ['sessions' => [], 'root_session_id' => NULL],
        ]);
      }

      // If type filter, return only sessions of that type.
      if ($type_filter) {
        $sessions = $this->sessionManager->getSessionsByType($campaign_id, $type_filter);
      }
      else {
        // Build full tree from root.
        $sessions = $this->buildSessionTree($root, $include_archived);
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'sessions' => $sessions,
          'root_session_id' => (int) $root['id'],
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  // =========================================================================
  // Session messages.
  // =========================================================================

  /**
   * Get messages for a specific session.
   *
   * GET /api/campaign/{campaign_id}/sessions/{session_id}/messages
   *     ?limit=50&before_id=0&type=narrative&order=desc
   */
  public function getSessionMessages(int $campaign_id, int $session_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $session = $this->sessionManager->loadSessionById($session_id);
      if (!$session || (int) $session['campaign_id'] !== $campaign_id) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Session not found'], 404);
      }

      $limit = min((int) ($request->query->get('limit', 50)), 200);
      $before_id = (int) $request->query->get('before_id', 0);
      $type_filter = $request->query->get('type');
      $order = $request->query->get('order', 'desc');

      if ($order === 'asc') {
        $after_id = (int) $request->query->get('after_id', 0);
        $messages = $this->sessionManager->getMessagesChronological($session_id, $limit, $after_id);
      }
      else {
        $messages = $this->sessionManager->getMessages($session_id, $limit, $before_id, $type_filter);
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'session_id' => $session_id,
          'session_type' => $session['session_type'],
          'session_label' => $session['label'],
          'message_count' => $session['message_count'],
          'messages' => $this->formatMessages($messages),
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  /**
   * Post a message to a specific session.
   *
   * POST /api/campaign/{campaign_id}/sessions/{session_id}/messages
   *
   * Payload: {
   *   "speaker": "Torgar",
   *   "speaker_type": "player",
   *   "speaker_ref": "85",
   *   "message": "I search the chest.",
   *   "message_type": "dialogue",
   *   "visibility": "public"
   * }
   */
  public function postSessionMessage(int $campaign_id, int $session_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $session = $this->sessionManager->loadSessionById($session_id);
      if (!$session || (int) $session['campaign_id'] !== $campaign_id) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Session not found'], 404);
      }

      if ($session['status'] !== 'active') {
        return new JsonResponse(['success' => FALSE, 'error' => 'Session is not active'], 400);
      }

      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON payload'], 400);
      }

      $speaker = trim($payload['speaker'] ?? '');
      $message = trim($payload['message'] ?? '');
      if ($speaker === '' || $message === '') {
        return new JsonResponse(['success' => FALSE, 'error' => 'speaker and message are required'], 400);
      }

      $msg_id = $this->sessionManager->postMessage(
        $session_id,
        $campaign_id,
        $speaker,
        $payload['speaker_type'] ?? 'player',
        (string) ($payload['speaker_ref'] ?? ''),
        $message,
        $payload['message_type'] ?? 'dialogue',
        $payload['visibility'] ?? 'public',
        $payload['metadata'] ?? [],
        $payload['feed_up'] ?? TRUE
      );

      return new JsonResponse([
        'success' => TRUE,
        'data' => ['message_id' => $msg_id, 'session_id' => $session_id],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  // =========================================================================
  // Character narrative feed.
  // =========================================================================

  /**
   * Get a character's narrative feed (what they perceived).
   *
   * Aggregates messages from all character_narrative sessions for this
   * character across the current dungeon. This is the PRIMARY player view.
   *
   * GET /api/campaign/{campaign_id}/narrative/{character_id}
   *     ?dungeon_id=&room_id=&limit=50&before_id=0
   */
  public function getCharacterNarrative(int $campaign_id, int $character_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $dungeon_id = $request->query->get('dungeon_id');
      $room_id = $request->query->get('room_id');
      $limit = min((int) ($request->query->get('limit', 50)), 200);

      // If room_id + dungeon_id given, return that specific narrative session.
      if ($dungeon_id && $room_id) {
        $key = $this->sessionManager->characterNarrativeKey($campaign_id, $dungeon_id, $room_id, $character_id);
        $session = $this->sessionManager->loadSession($key);
        if (!$session) {
          return new JsonResponse([
            'success' => TRUE,
            'data' => [
              'character_id' => $character_id,
              'messages' => [],
              'session_id' => NULL,
            ],
          ]);
        }

        $messages = $this->sessionManager->getMessagesChronological((int) $session['id'], $limit);
        return new JsonResponse([
          'success' => TRUE,
          'data' => [
            'character_id' => $character_id,
            'session_id' => (int) $session['id'],
            'room_id' => $room_id,
            'dungeon_id' => $dungeon_id,
            'messages' => $this->formatMessages($messages),
          ],
        ]);
      }

      // If only dungeon_id, aggregate across all rooms in that dungeon.
      if ($dungeon_id) {
        $narratives = $this->sessionManager->getCharacterNarrativesForRoom($campaign_id, $dungeon_id, '*');
        // The above won't work with wildcard; instead query by type.
        $all_narrative_sessions = $this->sessionManager->getSessionsByType($campaign_id, 'character_narrative');
        $char_sessions = array_filter($all_narrative_sessions, function ($s) use ($character_id, $dungeon_id) {
          $meta = $s['metadata'] ?? [];
          return (string) ($meta['character_id'] ?? '') === (string) $character_id
            && (string) ($meta['dungeon_id'] ?? '') === (string) $dungeon_id;
        });

        $all_messages = [];
        foreach ($char_sessions as $s) {
          $msgs = $this->sessionManager->getMessagesChronological((int) $s['id'], $limit);
          $all_messages = array_merge($all_messages, $msgs);
        }

        // Sort by created time and limit.
        usort($all_messages, fn($a, $b) => ($a['created'] ?? 0) <=> ($b['created'] ?? 0));
        $all_messages = array_slice($all_messages, -$limit);

        return new JsonResponse([
          'success' => TRUE,
          'data' => [
            'character_id' => $character_id,
            'dungeon_id' => $dungeon_id,
            'session_count' => count($char_sessions),
            'messages' => $this->formatMessages($all_messages),
          ],
        ]);
      }

      // No scope specified: return all narrative sessions for this character.
      $all_sessions = $this->sessionManager->getSessionsByType($campaign_id, 'character_narrative');
      $char_sessions = array_filter($all_sessions, function ($s) use ($character_id) {
        return (string) ($s['metadata']['character_id'] ?? '') === (string) $character_id;
      });

      $sessions_summary = [];
      foreach ($char_sessions as $s) {
        $sessions_summary[] = [
          'session_id' => (int) $s['id'],
          'label' => $s['label'],
          'room_id' => $s['metadata']['room_id'] ?? NULL,
          'dungeon_id' => $s['metadata']['dungeon_id'] ?? NULL,
          'message_count' => $s['message_count'],
          'status' => $s['status'] ?? 'active',
        ];
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'character_id' => $character_id,
          'narrative_sessions' => $sessions_summary,
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  // =========================================================================
  // Party chat.
  // =========================================================================

  /**
   * Get party chat messages.
   *
   * GET /api/campaign/{campaign_id}/party-chat?limit=50&before_id=0
   */
  public function getPartyChat(int $campaign_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $key = $this->sessionManager->partySessionKey($campaign_id);
      $session = $this->sessionManager->loadSession($key);

      if (!$session) {
        return new JsonResponse([
          'success' => TRUE,
          'data' => [
            'session_id' => NULL,
            'messages' => [],
          ],
        ]);
      }

      $limit = min((int) ($request->query->get('limit', 50)), 200);
      $before_id = (int) $request->query->get('before_id', 0);
      $messages = $this->sessionManager->getMessages((int) $session['id'], $limit, $before_id);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'session_id' => (int) $session['id'],
          'messages' => $this->formatMessages(array_reverse($messages)),
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  /**
   * Post to party chat.
   *
   * POST /api/campaign/{campaign_id}/party-chat
   *
   * Payload: { "speaker": "Torgar", "speaker_ref": "85", "message": "Let's huddle." }
   */
  public function postPartyChat(int $campaign_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON payload'], 400);
      }

      $speaker = trim($payload['speaker'] ?? '');
      $message = trim($payload['message'] ?? '');
      if ($speaker === '' || $message === '') {
        return new JsonResponse(['success' => FALSE, 'error' => 'speaker and message are required'], 400);
      }

      // Ensure party session exists.
      $root = $this->sessionManager->ensureCampaignSessions($campaign_id);
      $key = $this->sessionManager->partySessionKey($campaign_id);
      $session = $this->sessionManager->loadSession($key);

      if (!$session) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Party session not found'], 500);
      }

      $msg_id = $this->sessionManager->postMessage(
        (int) $session['id'],
        $campaign_id,
        $speaker,
        'player',
        (string) ($payload['speaker_ref'] ?? ''),
        $message,
        'dialogue',
        'public',
        [],
        TRUE
      );

      return new JsonResponse([
        'success' => TRUE,
        'data' => ['message_id' => $msg_id, 'session_id' => (int) $session['id']],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  // =========================================================================
  // GM Private channel.
  // =========================================================================

  /**
   * Get GM private channel for a character.
   *
   * GET /api/campaign/{campaign_id}/gm-private/{character_id}?limit=50
   */
  public function getGmPrivate(int $campaign_id, int $character_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $key = $this->sessionManager->gmPrivateSessionKey($campaign_id, $character_id);
      $session = $this->sessionManager->loadSession($key);

      if (!$session) {
        return new JsonResponse([
          'success' => TRUE,
          'data' => ['session_id' => NULL, 'messages' => []],
        ]);
      }

      $limit = min((int) ($request->query->get('limit', 50)), 200);
      $messages = $this->sessionManager->getMessages((int) $session['id'], $limit);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'session_id' => (int) $session['id'],
          'character_id' => $character_id,
          'messages' => $this->formatMessages(array_reverse($messages)),
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  /**
   * Post a secret action to GM private channel.
   *
   * POST /api/campaign/{campaign_id}/gm-private/{character_id}
   *
   * Payload: { "speaker": "Torgar", "message": "I secretly pickpocket the merchant." }
   */
  public function postGmPrivate(int $campaign_id, int $character_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON payload'], 400);
      }

      $speaker = trim($payload['speaker'] ?? '');
      $message = trim($payload['message'] ?? '');
      if ($speaker === '' || $message === '') {
        return new JsonResponse(['success' => FALSE, 'error' => 'speaker and message are required'], 400);
      }

      $msg_id = $this->narrationEngine->recordSecretAction(
        $campaign_id,
        $character_id,
        $speaker,
        $message,
        $payload['metadata'] ?? []
      );

      return new JsonResponse([
        'success' => TRUE,
        'data' => ['message_id' => $msg_id, 'character_id' => $character_id],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  // =========================================================================
  // System log.
  // =========================================================================

  /**
   * Get system log (dice rolls, checks, mechanical results).
   *
   * GET /api/campaign/{campaign_id}/system-log?limit=50&before_id=0
   */
  public function getSystemLog(int $campaign_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $key = $this->sessionManager->systemLogSessionKey($campaign_id);
      $session = $this->sessionManager->loadSession($key);

      if (!$session) {
        return new JsonResponse([
          'success' => TRUE,
          'data' => ['session_id' => NULL, 'messages' => []],
        ]);
      }

      $limit = min((int) ($request->query->get('limit', 100)), 200);
      $before_id = (int) $request->query->get('before_id', 0);
      $messages = $this->sessionManager->getMessages((int) $session['id'], $limit, $before_id);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'session_id' => (int) $session['id'],
          'messages' => $this->formatMessages(array_reverse($messages)),
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  // =========================================================================
  // Narration controls.
  // =========================================================================

  /**
   * Force-flush the narration buffer for a room.
   *
   * POST /api/campaign/{campaign_id}/narration/flush
   *
   * Payload: {
   *   "dungeon_id": 42,
   *   "room_id": "room_a1b2",
   *   "present_characters": [{ "character_id": 85, "name": "Torgar", ... }]
   * }
   *
   * This triggers NarrationEngine::flushNarration() which generates
   * per-character scene beats from buffered events.
   */
  public function flushNarration(int $campaign_id, Request $request): JsonResponse {
    if (!$this->chatService->hasCampaignAccess($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON payload'], 400);
      }

      $dungeon_id = $payload['dungeon_id'] ?? NULL;
      $room_id = $payload['room_id'] ?? NULL;
      if ($dungeon_id === NULL || $room_id === NULL) {
        return new JsonResponse(['success' => FALSE, 'error' => 'dungeon_id and room_id are required'], 400);
      }

      $present_characters = $payload['present_characters'] ?? [];

      $scene_beats = $this->narrationEngine->flushNarration(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $present_characters
      );

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'flushed' => TRUE,
          'scene_beats' => $scene_beats,
          'characters_narrated' => count($scene_beats),
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Build a recursive session tree from a root session.
   */
  protected function buildSessionTree(array $root, bool $include_archived = FALSE): array {
    $node = $this->formatSession($root);

    $children = $this->sessionManager->getChildSessions((int) $root['id']);

    if (!$include_archived) {
      $children = array_filter($children, fn($c) => ($c['status'] ?? 'active') === 'active');
    }

    $node['children'] = [];
    foreach ($children as $child) {
      $node['children'][] = $this->buildSessionTree($child, $include_archived);
    }

    return $node;
  }

  /**
   * Format a session record for API output.
   */
  protected function formatSession(array $session): array {
    return [
      'id' => (int) $session['id'],
      'campaign_id' => (int) $session['campaign_id'],
      'parent_session_id' => $session['parent_session_id'] ? (int) $session['parent_session_id'] : NULL,
      'session_type' => $session['session_type'],
      'session_key' => $session['session_key'],
      'label' => $session['label'],
      'scope_ref' => $session['scope_ref'] ?? '',
      'status' => $session['status'] ?? 'active',
      'message_count' => (int) ($session['message_count'] ?? 0),
      'last_message_at' => isset($session['last_message_at']) ? (int) $session['last_message_at'] : 0,
      'created' => isset($session['created']) ? (int) $session['created'] : 0,
    ];
  }

  /**
   * Format message records for API output.
   */
  protected function formatMessages(array $messages): array {
    return array_map(function ($msg) {
      return [
        'id' => (int) $msg['id'],
        'session_id' => (int) $msg['session_id'],
        'speaker' => $msg['speaker'] ?? '',
        'speaker_type' => $msg['speaker_type'] ?? 'system',
        'speaker_ref' => $msg['speaker_ref'] ?? '',
        'message' => $msg['message'] ?? '',
        'message_type' => $msg['message_type'] ?? 'narrative',
        'visibility' => $msg['visibility'] ?? 'public',
        'metadata' => $msg['metadata'] ?? [],
        'source_message_id' => $msg['source_message_id'] ? (int) $msg['source_message_id'] : NULL,
        'created' => isset($msg['created']) ? (int) $msg['created'] : 0,
      ];
    }, $messages);
  }

}
