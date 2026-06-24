<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Validates quest objective destinations against dungeon data.
 * 
 * Enforces hard-fail contract: quest destinations MUST exist in the dungeon
 * and match exactly by room_id or room name. No silent failures, no fallbacks.
 */
class QuestDestinationValidatorService {

  /**
   * Validates a single quest objective destination.
   *
   * @param array $objective
   *   The objective data with optional 'destination' or 'destination_id' field.
   * @param array $dungeon_data
   *   The dungeon data array containing 'rooms' definition.
   *
   * @throws \InvalidArgumentException
   *   If destination is specified but not found in dungeon.
   */
  public function validateQuestDestination(
    array $objective,
    array $dungeon_data
  ): void {
    $destination = trim((string) ($objective['destination'] ?? 
                                  $objective['destination_id'] ?? ''));
    
    if ($destination === '') {
      return; // No destination required
    }

    $room = $this->findRoomByIdOrName($dungeon_data, $destination);
    if (!$room) {
      throw new \InvalidArgumentException(
        "Quest destination '{$destination}' not found in dungeon. " .
        "Must match a room_id or room name exactly (case-sensitive)."
      );
    }
  }

  /**
   * Validates all objectives in a quest.
   *
   * @param array $quest
   *   The quest data with 'objectives' array.
   * @param array $dungeon_data
   *   The dungeon data.
   *
   * @throws \InvalidArgumentException
   *   If any objective destination is invalid.
   */
  public function validateQuestObjectives(
    array $quest,
    array $dungeon_data
  ): void {
    $objectives = (array) ($quest['objectives'] ?? []);
    
    foreach ($objectives as $index => $objective) {
      try {
        $this->validateQuestDestination($objective, $dungeon_data);
      } catch (\InvalidArgumentException $e) {
        throw new \InvalidArgumentException(
          "Quest '{$quest['quest_id']}' objective {$index}: {$e->getMessage()}"
        );
      }
    }
  }

  /**
   * Finds a room by room_id or room name.
   *
   * Tries room_id first (more specific), then room name (fallback).
   * Matching is exact and case-sensitive.
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $identifier
   *   The room_id or room name to find.
   *
   * @return array|null
   *   The room data if found, null otherwise.
   */
  protected function findRoomByIdOrName(
    array $dungeon_data,
    string $identifier
  ): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') {
      return null;
    }

    $rooms = (array) ($dungeon_data['rooms'] ?? []);
    
    // Try exact match on room_id first (most specific)
    foreach ($rooms as $room) {
      if ((string) ($room['room_id'] ?? '') === $identifier) {
        return $room;
      }
    }
    
    // Try exact match on room name (fallback)
    foreach ($rooms as $room) {
      if ((string) ($room['name'] ?? '') === $identifier) {
        return $room;
      }
    }
    
    return null;
  }

  /**
   * Resolves a destination identifier to its room_id.
   *
   * Used to normalize quest destination references to actual room IDs.
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $identifier
   *   The destination identifier (room_id or room name).
   *
   * @return string|null
   *   The room_id if found, null otherwise.
   */
  public function resolveDestinationToRoomId(
    array $dungeon_data,
    string $identifier
  ): ?string {
    $room = $this->findRoomByIdOrName($dungeon_data, $identifier);
    return $room ? (string) ($room['room_id'] ?? null) : null;
  }

}
