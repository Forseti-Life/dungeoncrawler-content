<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Locates room entries and encounter turn entities in dungeon payloads.
 */
final class RoomLocator {

  /**
   * Find room entry by runtime id or canonical source id.
   */
  public function findRoomByRoomId(array $rooms, string $room_id): array {
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
  public function findRoomIndex(array $rooms, string $room_id): int|string|null {
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
   * Determine whether a room entry matches runtime or source id.
   */
  public function roomIdentifierMatches(array $room, string $room_id): bool {
    $candidate_room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
    $candidate_source_room_id = trim((string) ($room['source_room_id'] ?? ''));
    return $candidate_room_id === $room_id || ($candidate_source_room_id !== '' && $candidate_source_room_id === $room_id);
  }

  /**
   * Resolve encounter turn entity from dungeon payload.
   */
  public function findEncounterTurnEntity(string $turn_entity_id, array $dungeon_data): ?array {
    foreach (($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }

      $entity_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($entity_id === $turn_entity_id) {
        return $entity;
      }
    }

    return NULL;
  }

}
