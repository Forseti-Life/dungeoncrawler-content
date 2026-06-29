<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\AiSessionManager;

/**
 * Persists GM transcript entries and session bridge side effects.
 */
class GmTranscriptPersistencePipeline {

  protected Connection $database;
  protected AiSessionManager $sessionManager;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, AiSessionManager $session_manager) {
    $this->database = $database;
    $this->sessionManager = $session_manager;
  }

  /**
   * Persist visible GM narrative and session bridge side effects.
   *
   * @return array{
   *   gm_message: array,
   *   dungeon_data: array
   * }
   */
  public function persistVisibleReply(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    int|string $room_index,
    array $dungeon_data,
    array $chat,
    string $session_key,
    string $narrative,
    string $visible_gm_narrative,
    array $actions,
    array $dice_rolls,
    array $checked_response,
    bool $suppress_npc_interjections,
    int $max_messages_per_room,
    callable $build_gm_room_response_payload,
    callable $bridge_gm_reply_to_session_system
  ): array {
    $gm_payload = $build_gm_room_response_payload($visible_gm_narrative, $actions, $dice_rolls, $suppress_npc_interjections);
    $gm_message = [
      'speaker' => 'Game Master',
      'message' => $visible_gm_narrative,
      'type' => 'npc',
      'channel' => 'room',
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
      'gm_payload' => $gm_payload,
    ];
    if (!empty($checked_response['speaker_name'])) {
      $gm_message['speaker_name'] = (string) $checked_response['speaker_name'];
    }
    if (!empty($checked_response['entity_ref'])) {
      $gm_message['entity_ref'] = (string) $checked_response['entity_ref'];
    }

    if ($actions !== []) {
      $gm_message['mechanical_actions'] = array_map(static function ($action): array {
        return [
          'type' => $action['type'] ?? 'unknown',
          'name' => $action['name'] ?? 'Unknown',
        ];
      }, $actions);
      if ($dice_rolls !== []) {
        $gm_message['dice_rolls'] = $dice_rolls;
      }
    }

    $dungeon_data['rooms'][$room_index]['chat'][] = $gm_message;
    $gm_message['sequence_index'] = count($dungeon_data['rooms'][$room_index]['chat']);
    $dungeon_data['rooms'][$room_index]['chat'][array_key_last($dungeon_data['rooms'][$room_index]['chat'])] = $gm_message;

    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > $max_messages_per_room) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
        $chat_count - $max_messages_per_room
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

    $player_msg_text = end($chat)['message'] ?? '';
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $player_msg_text);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $visible_gm_narrative);
    $bridge_gm_reply_to_session_system($campaign_id, $dungeon_id, $room_id, $visible_gm_narrative, $actions, $dice_rolls);

    return [
      'gm_message' => $gm_message,
      'dungeon_data' => $dungeon_data,
    ];
  }

}

