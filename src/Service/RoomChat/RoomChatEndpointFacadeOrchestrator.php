<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\dungeoncrawler_content\Service\RoomChatService;

/**
 * Coordinates suggestion/channel endpoint facade operations.
 */
class RoomChatEndpointFacadeOrchestrator {

  protected RoomChatService $chatService;

  public function __construct(RoomChatService $chat_service) {
    $this->chatService = $chat_service;
  }

  /**
   * Execute player automation suggestion request.
   *
   * @param array<string,mixed> $payload
   *   Suggestion endpoint payload.
   *
   * @return array<string,mixed>
   *   Suggestion response payload.
   */
  public function suggestPlayerAutomationMessage(int $campaign_id, string $room_id, array $payload): array {
    $character_id = $this->getRequiredCharacterIdFromPayload($payload);
    $channel = (string) ($payload['channel'] ?? 'room');

    return $this->chatService->suggestPlayerAutomationMessage(
      $campaign_id,
      $room_id,
      $character_id,
      $channel
    );
  }

  public function getChannelsForRoom(int $campaign_id, string $room_id, ?int $character_id): array {
    return $this->chatService->getChannelsForRoom($campaign_id, $room_id, $character_id);
  }

  /**
   * Open a room channel from request payload.
   *
   * @param array<string,mixed> $payload
   *   Open-channel endpoint payload.
   *
   * @return array<string,mixed>
   *   Open-channel service result.
   */
  public function openChannelFromPayload(int $campaign_id, string $room_id, array $payload): array {
    $channel_key = (string) ($payload['channel_key'] ?? '');
    $opened_by = (string) ($payload['opened_by'] ?? '');
    $target_entity = (string) ($payload['target_entity'] ?? '');
    $target_name = (string) ($payload['target_name'] ?? 'Unknown');
    $source_ability = (string) ($payload['source_ability'] ?? 'whisper');

    if ($channel_key === '' || $opened_by === '' || $target_entity === '') {
      throw new \InvalidArgumentException('Missing required fields: channel_key, opened_by, target_entity', 400);
    }

    return $this->chatService->openChannel(
      $campaign_id,
      $room_id,
      $channel_key,
      $opened_by,
      $target_entity,
      $target_name,
      $source_ability
    );
  }

  public function closeChannel(int $campaign_id, string $room_id, string $channel_key): bool {
    return $this->chatService->closeChannel($campaign_id, $room_id, $channel_key);
  }

  /**
   * @param array<string,mixed> $payload
   *   Request payload that must include character_id.
   */
  protected function getRequiredCharacterIdFromPayload(array $payload): int {
    $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : 0;
    if ($character_id <= 0) {
      throw new \InvalidArgumentException('character_id is required', 400);
    }
    return $character_id;
  }

}
