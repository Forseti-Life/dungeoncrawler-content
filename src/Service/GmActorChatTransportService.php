<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Transport adapter for GM actor room-chat message writes.
 */
class GmActorChatTransportService {

  protected RoomChatService $roomChatService;

  public function __construct(RoomChatService $room_chat_service) {
    $this->roomChatService = $room_chat_service;
  }

  /**
   * Post one validated player room-chat message through room-chat transport.
   */
  public function postValidatedPlayerRoomChat(
    int $campaign_id,
    string $room_id,
    string $speaker,
    string $message,
    int $character_id,
    bool $suppress_gm = FALSE,
    array $response_options = []
  ): array {
    $defer_npc_interjections = FALSE;

    return $this->roomChatService->postMessage(
      $campaign_id,
      $room_id,
      $speaker,
      $message,
      'player',
      $character_id,
      'room',
      $defer_npc_interjections,
      $suppress_gm,
      NULL,
      [
        '_validated_encounter_room_chat' => TRUE,
      ],
      [
        'response_mode' => (string) ($response_options['response_mode'] ?? 'actor_scoped'),
        'include_legacy_overlay' => !empty($response_options['include_legacy_overlay']),
      ]
    );
  }

}
