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
   * Validates a single quest objective destination/location contract.
   *
   * @param array $objective
   *   The objective data with optional destination/location reference fields.
   * @param array $dungeon_data
   *   The dungeon data array containing 'rooms' definition.
   *
   * @throws \InvalidArgumentException
   *   If destination is specified but not found in dungeon.
   */
  public function validateQuestDestination(
    array $objective,
    array $dungeon_data,
    array $canonical_destinations = []
  ): void {
    $destination = trim((string) (
      $objective['destination'] ??
      $objective['destination_id'] ??
      $objective['location'] ??
      $objective['location_id'] ??
      ''
    ));
    
    if ($destination === '') {
      return; // No destination required
    }

    $room = $this->findRoomByIdOrName($dungeon_data, $destination);
    if (!$room && !isset($canonical_destinations[$destination])) {
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
    array $dungeon_data,
    array $canonical_destinations = []
  ): void {
    $objective_nodes = $this->collectQuestObjectiveNodes($quest);
    $quest_label = trim((string) ($quest['quest_id'] ?? ''));
    if ($quest_label === '') {
      $quest_label = 'unknown';
    }
    foreach ($objective_nodes as $node) {
      $objective = is_array($node['objective'] ?? NULL) ? $node['objective'] : [];
      $path = (string) ($node['path'] ?? 'objective');
      try {
        $this->validateQuestDestination($objective, $dungeon_data, $canonical_destinations);
      } catch (\InvalidArgumentException $e) {
        throw new \InvalidArgumentException(
          "Quest '{$quest_label}' {$path}: {$e->getMessage()}"
        );
      }
    }
  }

  /**
   * Collect quest objective nodes across phased and nested objective trees.
   *
   * Supports legacy flat quest['objectives'] payloads and phased payloads where
   * quest['objectives'] is a list of phase objects containing objectives[].
   *
   * @param array<string, mixed> $quest
   *   Quest payload.
   *
   * @return array<int, array{objective: array<string, mixed>, path: string}>
   *   Objective node list with diagnostic paths.
   */
  protected function collectQuestObjectiveNodes(array $quest): array {
    $nodes = [];
    $objectives = (array) ($quest['objectives'] ?? []);

    foreach ($objectives as $index => $entry) {
      if (!is_array($entry)) {
        continue;
      }

      if (is_array($entry['objectives'] ?? NULL)) {
        foreach ((array) $entry['objectives'] as $objective_index => $objective) {
          if (!is_array($objective)) {
            continue;
          }
          $this->collectObjectiveNodesRecursive($objective, "phase[{$index}].objective[{$objective_index}]", $nodes);
        }
        continue;
      }

      $this->collectObjectiveNodesRecursive($entry, "objective[{$index}]", $nodes);
    }

    return $nodes;
  }

  /**
   * Recursively collect one objective node and its child objective nodes.
   *
   * @param array<string, mixed> $objective
   *   Objective node.
   * @param string $path
   *   Objective path.
   * @param array<int, array{objective: array<string, mixed>, path: string}> $nodes
   *   Node list (mutated).
   */
  protected function collectObjectiveNodesRecursive(array $objective, string $path, array &$nodes): void {
    $nodes[] = [
      'objective' => $objective,
      'path' => $path,
    ];

    foreach ((array) ($objective['children'] ?? []) as $child_index => $child) {
      if (!is_array($child)) {
        continue;
      }
      $this->collectObjectiveNodesRecursive($child, "{$path}.children[{$child_index}]", $nodes);
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
