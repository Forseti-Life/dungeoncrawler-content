<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;

/**
 * Normalizes and routes room-chat write endpoint orchestration paths.
 */
class RoomChatWriteEndpointOrchestrator {

  protected RoomChatService $chatService;

  protected GameMasterSubsystemService $gmSubsystem;

  public function __construct(RoomChatService $chat_service, GameMasterSubsystemService $gm_subsystem) {
    $this->chatService = $chat_service;
    $this->gmSubsystem = $gm_subsystem;
  }

  /**
   * Normalize room-chat POST payload into canonical request context.
   *
   * @return array<string,mixed>
   *   Normalized payload fields used by the post-chat route.
   */
  public function normalizePostChatPayload(array $payload): array {
    $speaker = (string) ($payload['speaker'] ?? '');
    $message = (string) ($payload['message'] ?? '');
    $type = (string) ($payload['type'] ?? 'player');
    $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : NULL;
    $channel = (string) ($payload['channel'] ?? 'room');
    $client_request_id = (string) ($payload['client_request_id'] ?? '');
    $is_player_turn = $type === 'player';

    // Room transcript lines are encounter-governed: clients cannot inject NPC/system
    // lines into the room channel. Player room chat must route via the canonical
    // encounter Talk action.
    if ($channel === 'room' && !$is_player_turn) {
      throw new \InvalidArgumentException('Only player messages may be posted to the room channel.', 400);
    }
    if (str_starts_with($channel, 'gm_private:') && !$is_player_turn) {
      throw new \InvalidArgumentException('Only player messages may be posted to a GM private channel.', 400);
    }

    // stream: use NDJSON streaming for player turns so the client can render
    // player ack, progress, primary reply, and any follow-up reactions
    // incrementally instead of waiting for one large JSON response.
    $stream = !empty($payload['stream']) && $is_player_turn;

    // suppress_gm: persist the player's room message but intentionally skip
    // response generation for this request because a turn is already in
    // flight. The queued player messages are folded into one later
    // continuation for the same channel.
    $suppress_gm = !empty($payload['suppress_gm']) && $is_player_turn;

    // continue_gm: run exactly one follow-up pass over queued player
    // messages after the active turn settles. This keeps AI analysis
    // serialized while still allowing the player to keep sending messages.
    $continue_gm = !empty($payload['continue_gm']) && $is_player_turn;

    return [
      'speaker' => $speaker,
      'message' => $message,
      'type' => $type,
      'character_id' => $character_id,
      'channel' => $channel,
      'client_request_id' => $client_request_id,
      'stream' => $stream,
      'suppress_gm' => $suppress_gm,
      'continue_gm' => $continue_gm,
    ];
  }

  public function isPlayerRoomChat(string $type, string $channel): bool {
    return $type === 'player' && $channel === 'room';
  }

  public function postPlayerRoomChatViaEncounterTalk(
    int $campaign_id,
    string $requested_room_id,
    ?int $character_id,
    string $speaker,
    string $message,
    bool $defer_npc_interjections = FALSE,
    bool $suppress_gm = FALSE
  ): array {
    return $this->gmSubsystem->handlePlayerRoomChat(
      $campaign_id,
      $requested_room_id,
      $character_id,
      $message,
      $defer_npc_interjections,
      $suppress_gm,
      $speaker
    );
  }

  public function continueQueuedRoomConversation(int $campaign_id, string $room_id, ?int $character_id, string $channel): array {
    return $this->chatService->continueQueuedRoomConversation(
      $campaign_id,
      $room_id,
      $character_id,
      $channel
    );
  }

  /**
   * Dispatch non-stream room-chat write into encounter or direct chat service.
   *
   * @return array<string,mixed>
   *   Canonical room-chat write result.
   */
  public function dispatchStandardPostChatWrite(
    int $campaign_id,
    string $room_id,
    string $speaker,
    string $message,
    string $type,
    ?int $character_id,
    string $channel,
    bool $suppress_gm
  ): array {
    if ($this->isPlayerRoomChat($type, $channel)) {
      return $this->postPlayerRoomChatViaEncounterTalk(
        $campaign_id,
        $room_id,
        $character_id,
        $speaker,
        $message,
        FALSE,
        $suppress_gm
      );
    }

    return $this->chatService->postMessage(
      $campaign_id,
      $room_id,
      $speaker,
      $message,
      $type,
      $character_id,
      $channel,
      FALSE,
      $suppress_gm
    );
  }

}
