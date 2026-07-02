<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for room chat messages.
 * 
 * Provides REST endpoints for reading and posting chat messages in dungeon rooms.
 * All business logic is handled by RoomChatService.
 */
class RoomChatController extends ControllerBase {

  protected RoomChatService $chatService;

  /**
   * Constructor.
   */
  public function __construct(RoomChatService $chat_service) {
    $this->chatService = $chat_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_chat_service')
    );
  }

  /**
   * Get chat history for a room.
   * 
   * GET /api/campaign/{campaign_id}/room/{room_id}/chat?channel=room&character_id=85
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Chat history response.
   */
  public function getChatHistory(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      // Verify user has access to campaign
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }

      $channel = $request->query->get('channel', 'room');
      $character_id = $request->query->get('character_id') ? (int) $request->query->get('character_id') : NULL;

      $messages = $this->chatService->getChatHistory($campaign_id, $room_id, $channel, $character_id);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'roomId' => $room_id,
          'channel' => $channel,
          'messages' => $messages,
        ],
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 500;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $status === 404 ? 'Dungeon not found' : 'Invalid request',
      ], $status);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An error occurred',
      ], 500);
    }
  }

  /**
   * Post a new chat message to a room.
   * 
   * POST /api/campaign/{campaign_id}/room/{room_id}/chat
   * 
   * Payload: { "speaker": "Name", "message": "...", "type": "player", "character_id": 123 }
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Success response with message metadata.
   */
  public function postChatMessage(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      // Verify user has access to campaign
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }

      // Parse request body
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON payload',
        ], 400);
      }

      $speaker = $payload['speaker'] ?? '';
      $message = $payload['message'] ?? '';
      $type = $payload['type'] ?? 'player';
      $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : null;
      $channel = $payload['channel'] ?? 'room';

      $result = $this->chatService->postMessage(
        $campaign_id,
        $room_id,
        $speaker,
        $message,
        $type,
        $character_id,
        $channel
      );

      return new JsonResponse([
        'success' => TRUE,
        'data' => $result,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 400;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], $status);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An error occurred',
      ], 500);
    }
  }

  /**
   * Get available channels for a room.
   *
   * GET /api/campaign/{campaign_id}/room/{room_id}/channels?character_id=85
   */
  public function getChannels(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
      }

      $character_id = $request->query->get('character_id') ? (int) $request->query->get('character_id') : NULL;
      $result = $this->chatService->getChannelsForRoom($campaign_id, $room_id, $character_id);

      return new JsonResponse([
        'success' => TRUE,
        'data' => $result,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  /**
   * Open a new channel in a room.
   *
   * POST /api/campaign/{campaign_id}/room/{room_id}/channels
   *
   * Payload: {
   *   "channel_key": "whisper:goblin_1",
   *   "opened_by": "85",
   *   "target_entity": "goblin_guard_1",
   *   "target_name": "Goblin Guard",
   *   "source_ability": "whisper"
   * }
   */
  public function openChannel(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
      }

      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON payload'], 400);
      }

      $channel_key = $payload['channel_key'] ?? '';
      $opened_by = (string) ($payload['opened_by'] ?? '');
      $target_entity = $payload['target_entity'] ?? '';
      $target_name = $payload['target_name'] ?? 'Unknown';
      $source_ability = $payload['source_ability'] ?? 'whisper';

      if (empty($channel_key) || empty($opened_by) || empty($target_entity)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Missing required fields: channel_key, opened_by, target_entity'], 400);
      }

      $result = $this->chatService->openChannel(
        $campaign_id,
        $room_id,
        $channel_key,
        $opened_by,
        $target_entity,
        $target_name,
        $source_ability
      );

      $status = $result['success'] ? 200 : 400;
      return new JsonResponse(['success' => $result['success'], 'data' => $result], $status);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

  /**
   * Close a channel in a room.
   *
   * DELETE /api/campaign/{campaign_id}/room/{room_id}/channels/{channel_key}
   */
  public function closeChannel(int $campaign_id, string $room_id, string $channel_key): JsonResponse {
    try {
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
      }

      $closed = $this->chatService->closeChannel($campaign_id, $room_id, $channel_key);

      return new JsonResponse([
        'success' => $closed,
        'data' => ['channel_key' => $channel_key, 'closed' => $closed],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'An error occurred'], 500);
    }
  }

}
