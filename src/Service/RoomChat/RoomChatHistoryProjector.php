<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\dungeoncrawler_content\Service\ChatChannelManager;

/**
 * Projects room chat history into client-visible transcript lines.
 */
final class RoomChatHistoryProjector {

  /**
   * Build normalized history for one room channel.
   */
  public function projectHistory(
    array $dungeon_data,
    string $room_id,
    string $channel,
    ?int $character_id,
    ChatChannelManager $channel_manager,
    EncounterTranscriptPrefixService $prefix_service
  ): array {
    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    $room_entry = $this->findRoomByRoomId($rooms, $room_id);
    $chat = is_array($room_entry['chat'] ?? NULL) ? $room_entry['chat'] : [];

    $chat = $channel_manager->filterMessagesByChannel($chat, $channel);
    if ($channel === 'room') {
      $chat = $this->normalizeOpeningNarratorLine($chat, $room_entry);
      $chat = $this->suppressLeadingDuplicateRoomSceneIntro($chat, $room_entry, $prefix_service);
    }

    if ($channel !== 'room' && $character_id !== NULL) {
      $room_index = $this->findRoomIndex($rooms, $room_id);
      if ($room_index !== NULL) {
        $channels = $channel_manager->getChannels($dungeon_data, $room_index);
        if (isset($channels[$channel])) {
          $access = $channel_manager->validateChannelAccess($channels[$channel], $character_id);
          if (!$access['valid']) {
            return [];
          }
        }
      }
    }

    $normalized_messages = [];
    $sequence_index = 0;
    foreach (array_values($chat) as $msg) {
      if (!is_array($msg)) {
        continue;
      }
      if ($channel === 'room' && (!empty($msg['internal_log']) || !empty($msg['turn_prompt']))) {
        continue;
      }
      $speaker = (string) ($msg['speaker'] ?? 'Unknown');
      $message = (string) ($msg['message'] ?? '');
      if ($channel === 'room') {
        $message = $prefix_service->normalizeLegacyTurnOrderPrefix($message);
      }
      $sequence_index++;

      $normalized_messages[] = [
        'speaker' => $speaker !== '' ? $speaker : 'Unknown',
        'message' => $message,
        'type' => $msg['type'] ?? 'npc',
        'message_class' => trim((string) ($msg['message_class'] ?? '')),
        'channel' => $msg['channel'] ?? 'room',
        'timestamp' => $msg['timestamp'] ?? date('c'),
        'sequence_index' => $sequence_index,
        'character_id' => $msg['character_id'] ?? NULL,
        'user_id' => $msg['user_id'] ?? NULL,
        'internal_log' => !empty($msg['internal_log']),
      ];
    }

    return $normalized_messages;
  }

  /**
   * Inject deterministic narrator scene intro when room chat is still empty.
   */
  public function injectRoomSceneNarratorIntroIfNeeded(
    array &$dungeon_data,
    string $room_id,
    int $max_messages_per_room
  ): ?array {
    if (!isset($dungeon_data['rooms']) || !is_array($dungeon_data['rooms'])) {
      return NULL;
    }

    $room_index = $this->findRoomIndex($dungeon_data['rooms'], $room_id);
    if ($room_index === NULL) {
      return NULL;
    }

    if (!isset($dungeon_data['rooms'][$room_index]['chat']) || !is_array($dungeon_data['rooms'][$room_index]['chat'])) {
      $dungeon_data['rooms'][$room_index]['chat'] = [];
    }

    $chat = $dungeon_data['rooms'][$room_index]['chat'];
    if ($this->roomChatHasVisibleMessages($chat)) {
      return NULL;
    }

    $updated = $this->ensureRoomSceneNarratorIntro($chat, $dungeon_data['rooms'][$room_index]);
    if ($updated === $chat) {
      return NULL;
    }

    $dungeon_data['rooms'][$room_index]['chat'] = $updated;

    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > $max_messages_per_room) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
        $chat_count - $max_messages_per_room
      );
    }

    return $dungeon_data['rooms'][$room_index]['chat'][0] ?? NULL;
  }

  /**
   * Returns TRUE when a room chat log already contains player-visible content.
   */
  private function roomChatHasVisibleMessages(array $chat): bool {
    foreach ($chat as $message) {
      if (!is_array($message)) {
        continue;
      }
      if (!empty($message['internal_log'])) {
        continue;
      }
      $existing = trim((string) ($message['message'] ?? ''));
      if ($existing !== '') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Ensure room chat opens with the same grounded scene shown in the room view.
   */
  private function ensureRoomSceneNarratorIntro(array $chat, array $room_entry): array {
    $description = trim((string) ($room_entry['description'] ?? ''));
    if ($description === '') {
      return $chat;
    }

    $intro_message = $this->buildRoomSceneNarratorIntro($room_entry);
    if ($intro_message === '') {
      return $chat;
    }

    foreach ($chat as $message) {
      if (!is_array($message)) {
        continue;
      }
      $existing = trim((string) ($message['message'] ?? ''));
      if ($existing === '') {
        continue;
      }
      $existing_speaker = trim((string) ($message['speaker'] ?? ''));
      if ($existing === $intro_message && strcasecmp($existing_speaker, 'Narrator') === 0) {
        return $chat;
      }
    }

    array_unshift($chat, [
      'speaker' => 'Narrator',
      'message' => $intro_message,
      'type' => 'narrator',
      'channel' => 'room',
      'timestamp' => $this->resolveRoomSceneIntroTimestamp($chat),
      'character_id' => NULL,
      'user_id' => 0,
      'scene_intro' => TRUE,
    ]);

    return $chat;
  }

  /**
   * Re-label legacy "Game Master" opening line to "Narrator".
   */
  private function normalizeOpeningNarratorLine(array $chat, array $room_entry): array {
    $description = trim((string) ($room_entry['description'] ?? ''));
    if ($description === '') {
      return $chat;
    }

    foreach ($chat as $index => $message) {
      if (!is_array($message) || !empty($message['internal_log'])) {
        continue;
      }

      $speaker = trim((string) ($message['speaker'] ?? ''));
      $body = trim((string) ($message['message'] ?? ''));
      if ($body === '') {
        continue;
      }
      if (strcasecmp($speaker, 'Narrator') === 0) {
        return $chat;
      }
      if (strcasecmp($speaker, 'Game Master') !== 0) {
        return $chat;
      }
      if (!str_contains($body, $description)) {
        return $chat;
      }

      $chat[$index]['speaker'] = 'Narrator';
      $chat[$index]['type'] = 'narrator';
      $chat[$index]['message'] = preg_replace(
        '/^(Round\s+(?:\d+|\?)\s*:\s*Turn\s+(?:\d+|\?)\s*:\s*)Game Master:/i',
        '$1Narrator:',
        $body,
        1
      ) ?? $body;
      return $chat;
    }

    return $chat;
  }

  /**
   * Remove the leading scene-intro chat line when it duplicates room chrome.
   */
  private function suppressLeadingDuplicateRoomSceneIntro(
    array $chat,
    array $room_entry,
    EncounterTranscriptPrefixService $prefix_service
  ): array {
    $description = trim((string) ($room_entry['description'] ?? ''));
    if ($description === '') {
      return $chat;
    }

    $scene_intro = $this->normalizeSceneIntroComparisonText(
      $this->buildRoomSceneNarratorIntro($room_entry),
      $prefix_service
    );
    $description_only = $this->normalizeSceneIntroComparisonText($description, $prefix_service);
    if ($scene_intro === '' && $description_only === '') {
      return $chat;
    }

    foreach ($chat as $index => $message) {
      if (!is_array($message) || !empty($message['internal_log'])) {
        continue;
      }

      $body = trim((string) ($message['message'] ?? ''));
      if ($body === '') {
        continue;
      }

      $speaker = strtolower(trim((string) ($message['speaker'] ?? '')));
      if (!in_array($speaker, ['narrator', 'game master'], TRUE)) {
        return $chat;
      }

      $normalized_body = $this->normalizeSceneIntroComparisonText($body, $prefix_service);
      if ($normalized_body === $scene_intro || $normalized_body === $description_only) {
        unset($chat[$index]);
        return array_values($chat);
      }

      return $chat;
    }

    return $chat;
  }

  /**
   * Build deterministic narrator scene intro for a room.
   */
  private function buildRoomSceneNarratorIntro(array $room_entry): string {
    $description = trim((string) ($room_entry['description'] ?? ''));
    if ($description === '') {
      return '';
    }

    $name = trim((string) ($room_entry['name'] ?? $room_entry['room_id'] ?? 'Current room'));
    $meta = $this->buildRoomSceneMetaLine($room_entry);
    $parts = [$name !== '' ? $name : 'Current room'];
    if ($meta !== '') {
      $parts[] = $meta;
    }
    $parts[] = $description;

    return implode("\n\n", $parts);
  }

  /**
   * Normalize intro text for duplicate-comparison checks.
   */
  private function normalizeSceneIntroComparisonText(
    string $content,
    EncounterTranscriptPrefixService $prefix_service
  ): string {
    $content = $prefix_service->stripPrefix(trim($content));
    $content = preg_replace('/^(Narrator|Game Master):\s*/iu', '', $content, 1) ?? $content;
    $content = preg_replace('/\s+/u', ' ', trim($content)) ?? $content;
    return mb_strtolower(trim($content));
  }

  /**
   * Place synthetic room intro before the first persisted room message.
   */
  private function resolveRoomSceneIntroTimestamp(array $chat): string {
    foreach ($chat as $message) {
      if (!is_array($message)) {
        continue;
      }
      $timestamp = trim((string) ($message['timestamp'] ?? ''));
      if ($timestamp === '') {
        continue;
      }
      $unix = strtotime($timestamp);
      if ($unix !== FALSE) {
        return date('c', max(0, $unix - 1));
      }
    }

    return date('c');
  }

  /**
   * Build compact room metadata line.
   */
  private function buildRoomSceneMetaLine(array $room_entry): string {
    $parts = [];
    $room_type = $this->formatRoomSceneMetaValue($room_entry['room_type'] ?? '');
    if ($room_type !== '' && $room_type !== 'unknown') {
      $parts[] = $room_type;
    }

    $size = $this->formatRoomSceneMetaValue($room_entry['size_category'] ?? '');
    if ($size !== '') {
      $parts[] = $size;
    }

    $terrain = $this->formatRoomSceneMetaValue($room_entry['terrain'] ?? '');
    if ($terrain !== '') {
      $parts[] = $terrain;
    }

    $lighting = $this->formatRoomSceneMetaValue($room_entry['lighting'] ?? '');
    if ($lighting !== '') {
      $parts[] = 'lighting: ' . $lighting;
    }

    return implode(' • ', array_values(array_filter($parts)));
  }

  /**
   * Normalize room scene metadata for player-facing text.
   */
  private function formatRoomSceneMetaValue(mixed $value): string {
    if (is_array($value)) {
      if (isset($value['type']) || isset($value['level']) || isset($value['name'])) {
        $value = $value['type'] ?? $value['level'] ?? $value['name'];
      }
      else {
        $value = implode(', ', array_map([$this, 'formatRoomSceneMetaValue'], $value));
      }
    }

    return trim(str_replace('_', ' ', (string) $value));
  }

  /**
   * Find room entry by runtime id or canonical source id.
   */
  private function findRoomByRoomId(array $rooms, string $room_id): array {
    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $rooms[$room_id];
    }

    foreach ($rooms as $room) {
      if (is_array($room) && $this->roomIdentifierMatches($room, $room_id)) {
        return $room;
      }
    }

    return [];
  }

  /**
   * Find room index by runtime id or canonical source id.
   */
  private function findRoomIndex(array $rooms, string $room_id): int|string|null {
    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $room_id;
    }

    foreach ($rooms as $key => $room) {
      if (is_array($room) && $this->roomIdentifierMatches($room, $room_id)) {
        return $key;
      }
    }

    return NULL;
  }

  /**
   * Match room by runtime id or source room id.
   */
  private function roomIdentifierMatches(array $room, string $room_id): bool {
    $candidate_room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
    $candidate_source_room_id = trim((string) ($room['source_room_id'] ?? ''));
    return $candidate_room_id === $room_id || ($candidate_source_room_id !== '' && $candidate_source_room_id === $room_id);
  }

}
