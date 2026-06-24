<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Centralizes navigation capability resolution over dungeon room connections.
 */
class NavigationService {

  /**
   * Build formalized navigation capabilities for one active room.
   *
   * @return array<int, array<string, mixed>>
   *   Navigation capabilities.
   */
  public function buildNavigationCapabilities(array $dungeon_data, string $room_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return [];
    }

    $capabilities = [];
    foreach ($this->extractConnections($dungeon_data) as $connection) {
      $capability = $this->buildCapabilityFromConnection($connection, $room_id, $dungeon_data);
      if ($capability !== NULL) {
        $capabilities[] = $capability;
      }
    }

    usort($capabilities, static function (array $left, array $right): int {
      $left_available = !empty($left['available']) ? 0 : 1;
      $right_available = !empty($right['available']) ? 0 : 1;
      if ($left_available !== $right_available) {
        return $left_available <=> $right_available;
      }

      return strcmp((string) ($left['target_room_id'] ?? ''), (string) ($right['target_room_id'] ?? ''));
    });

    return $capabilities;
  }

  /**
   * Resolve one requested navigation capability from the current room.
   */
  public function resolveRequestedCapability(
    array $dungeon_data,
    string $room_id,
    ?string $connection_id = NULL,
    ?array $target_hex = NULL
  ): ?array {
    $capabilities = $this->buildNavigationCapabilities($dungeon_data, $room_id);
    $connection_id = trim((string) $connection_id);

    if ($connection_id !== '') {
      foreach ($capabilities as $capability) {
        if ((string) ($capability['connection_id'] ?? '') === $connection_id) {
          return $capability;
        }
      }
    }

    $normalized_target_hex = $this->normalizeHex($target_hex);
    if ($normalized_target_hex === NULL) {
      return NULL;
    }

    foreach ($capabilities as $capability) {
      $origin_hex = $this->normalizeHex($capability['origin_hex'] ?? NULL);
      if ($origin_hex === NULL) {
        continue;
      }
      if ($origin_hex['q'] === $normalized_target_hex['q'] && $origin_hex['r'] === $normalized_target_hex['r']) {
        return $capability;
      }
    }

    return NULL;
  }

  /**
   * Resolve one room by id from a dungeon payload.
   */
  public function findRoomById(array $dungeon_data, string $room_id): ?array {
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (is_array($room) && (string) ($room['room_id'] ?? '') === $room_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Build one navigation capability from one connection if it touches the room.
   */
  protected function buildCapabilityFromConnection(array $connection, string $room_id, array $dungeon_data = []): ?array {
    $from_room = trim((string) ($connection['from_room'] ?? ''));
    $to_room = trim((string) ($connection['to_room'] ?? ''));
    if ($from_room !== $room_id && $to_room !== $room_id) {
      return NULL;
    }

    $travels_forward = $from_room === $room_id;
    $target_room_id = $travels_forward ? $to_room : $from_room;
    $origin_hex = $this->normalizeHex($travels_forward ? ($connection['from_hex'] ?? NULL) : ($connection['to_hex'] ?? NULL));
    $target_hex = $this->normalizeHex($travels_forward ? ($connection['to_hex'] ?? NULL) : ($connection['from_hex'] ?? NULL));
    $type = trim((string) ($connection['type'] ?? 'passage')) ?: 'passage';
    $is_discovered = array_key_exists('is_discovered', $connection) ? !empty($connection['is_discovered']) : TRUE;
    $is_passable = array_key_exists('is_passable', $connection) ? !empty($connection['is_passable']) : TRUE;
    $bidirectional = array_key_exists('bidirectional', $connection) ? !empty($connection['bidirectional']) : ($type !== 'one_way');
    $requires_interaction = !$is_passable || in_array($type, ['door', 'locked_door', 'secret_door', 'trapped_door', 'barricade', 'collapsed', 'magical_barrier'], TRUE);
    $destination_type = $this->resolveDestinationType($connection);
    $destination_id = $this->resolveDestinationId($connection, $target_room_id, $destination_type);
    $distance = $this->resolveDistance($connection, $destination_type);

    $blocked_reason = NULL;
    if ($target_room_id === '') {
      $blocked_reason = 'unresolved_destination';
    }
    elseif ($destination_type === '' || $destination_id === '') {
      $blocked_reason = 'missing_destination_metadata';
    }
    elseif ($destination_type === 'room' && $distance !== 0) {
      $blocked_reason = 'invalid_distance_contract';
    }
    elseif ($destination_type === 'road' && !$this->hasRoomRoadAnchor($dungeon_data, $room_id)) {
      $blocked_reason = 'missing_road_anchor';
    }
    elseif (!$is_discovered) {
      $blocked_reason = 'undiscovered';
    }
    elseif (!$is_passable) {
      $blocked_reason = 'blocked';
    }

    return [
      'connection_id' => $this->deriveConnectionId($connection),
      'origin_room_id' => $room_id,
      'target_room_id' => $target_room_id,
      'destination_type' => $destination_type,
      'destination_id' => $destination_id,
      'type' => $type,
      'available' => $blocked_reason === NULL,
      'blocked_reason' => $blocked_reason,
      'is_discovered' => $is_discovered,
      'is_passable' => $is_passable,
      'bidirectional' => $bidirectional,
      'requires_interaction' => $requires_interaction,
      'distance' => $distance,
      'origin_hex' => $origin_hex,
      'target_hex' => $target_hex,
      'travel_time_seconds' => $this->resolveTravelSeconds($connection),
    ];
  }

  /**
   * Resolves travel time metadata from a connection.
   */
  protected function resolveTravelSeconds(array $connection): ?int {
    foreach (['travel_time_seconds', 'duration_seconds', 'time_cost_seconds'] as $key) {
      if (isset($connection[$key]) && is_numeric($connection[$key])) {
        return max(0, (int) $connection[$key]);
      }
    }
    foreach (['travel_time_minutes', 'duration_minutes', 'time_cost_minutes', 'travel_minutes'] as $key) {
      if (isset($connection[$key]) && is_numeric($connection[$key])) {
        return max(0, (int) $connection[$key]) * 60;
      }
    }
    if (isset($connection['travel_time']) && is_array($connection['travel_time'])) {
      if (isset($connection['travel_time']['seconds']) && is_numeric($connection['travel_time']['seconds'])) {
        return max(0, (int) $connection['travel_time']['seconds']);
      }
      if (isset($connection['travel_time']['minutes']) && is_numeric($connection['travel_time']['minutes'])) {
        return max(0, (int) $connection['travel_time']['minutes']) * 60;
      }
    }

    return NULL;
  }

  /**
   * Extract canonical connection records from a dungeon payload.
   *
   * @return array<int, array<string, mixed>>
   *   Connection records.
   */
  protected function extractConnections(array $dungeon_data): array {
    $connections = $dungeon_data['hex_map']['connections'] ?? ($dungeon_data['connections'] ?? []);
    if (!is_array($connections)) {
      return [];
    }

    return array_values(array_filter($connections, 'is_array'));
  }

  /**
   * Derive a stable connection identifier.
   */
  protected function deriveConnectionId(array $connection): string {
    $explicit = trim((string) ($connection['connection_id'] ?? ''));
    if ($explicit !== '') {
      return $explicit;
    }

    $from_room = trim((string) ($connection['from_room'] ?? 'unknown'));
    $to_room = trim((string) ($connection['to_room'] ?? 'unknown'));
    $type = trim((string) ($connection['type'] ?? 'passage')) ?: 'passage';
    $from_hex = $this->normalizeHex($connection['from_hex'] ?? NULL);
    $to_hex = $this->normalizeHex($connection['to_hex'] ?? NULL);
    $scope_suffix = 'unscoped';
    if ($from_hex !== NULL && $to_hex !== NULL) {
      $scope_suffix = $from_hex['q'] . ':' . $from_hex['r'] . '__' . $to_hex['q'] . ':' . $to_hex['r'];
    }

    return $from_room . '__' . $to_room . '__' . $type . '__' . $scope_suffix;
  }

  /**
   * Normalize one hex coordinate payload.
   *
   * @return array<string, int>|null
   *   Canonical hex or NULL.
   */
  protected function normalizeHex(mixed $hex): ?array {
    if (!is_array($hex) || !array_key_exists('q', $hex) || !array_key_exists('r', $hex)) {
      return NULL;
    }

    return [
      'q' => (int) $hex['q'],
      'r' => (int) $hex['r'],
    ];
  }

  /**
   * Resolves canonical destination type for one connection.
   */
  protected function resolveDestinationType(array $connection): string {
    $raw_type = strtolower(trim((string) ($connection['destination_type'] ?? $connection['to_type'] ?? '')));
    if (in_array($raw_type, ['road', 'room'], TRUE)) {
      return $raw_type;
    }

    return 'room';
  }

  /**
   * Resolves canonical destination id for one connection.
   */
  protected function resolveDestinationId(array $connection, string $target_room_id, string $destination_type): string {
    if ($destination_type === 'road') {
      return trim((string) ($connection['road_node_id'] ?? $connection['road_id'] ?? $connection['to_id'] ?? ''));
    }

    return $target_room_id;
  }

  /**
   * Resolves canonical edge distance for one connection.
   */
  protected function resolveDistance(array $connection, string $destination_type): int {
    foreach (['distance', 'travel_distance', 'distance_units'] as $key) {
      if (isset($connection[$key]) && is_numeric($connection[$key])) {
        return max(0, (int) $connection[$key]);
      }
    }

    if ($destination_type === 'road') {
      foreach (['road_distance', 'road_access_distance'] as $key) {
        if (isset($connection[$key]) && is_numeric($connection[$key])) {
          return max(0, (int) $connection[$key]);
        }
      }
    }

    return 0;
  }

  /**
   * Checks whether a room has a road anchor mapping for connections to roads.
   */
  protected function hasRoomRoadAnchor(array $dungeon_data, string $room_id): bool {
    $anchors = (array) ($dungeon_data['room_road_anchors'] ?? $dungeon_data['road_anchors'] ?? []);
    foreach ($anchors as $anchor) {
      if (is_array($anchor) && (string) ($anchor['room_id'] ?? '') === $room_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Build navigation capabilities + quest destination targets.
   *
   * Enhances navigation with quest objectives that specify destinations.
   * Ensures all quest destinations are visible in action rail.
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $room_id
   *   The current room ID.
   * @param array|null $active_quests
   *   Active quest data with 'objectives' field per quest.
   *
   * @return array<int, array<string, mixed>>
   *   Navigation capabilities including quest-referenced destinations.
   */
  public function buildNavigationCapabilitiesWithQuestTargets(
    array $dungeon_data,
    string $room_id,
    ?array $active_quests = NULL
  ): array {
    $capabilities = $this->buildNavigationCapabilities($dungeon_data, $room_id);

    if (empty($active_quests) || !is_array($active_quests)) {
      return $capabilities;
    }

    // Collect all quest destinations
    $quest_destinations = $this->extractQuestDestinations($active_quests);

    // For each quest destination, ensure it's in capabilities
    foreach ($quest_destinations as $quest_ref) {
      $destination_identifier = $quest_ref['destination'];
      $quest_id = $quest_ref['quest_id'];

      // Resolve destination to room_id
      $target_room = $this->findRoomByIdOrName($dungeon_data, $destination_identifier);
      if (!$target_room) {
        continue; // Destination doesn't exist, skip
      }

      $target_room_id = (string) ($target_room['room_id'] ?? '');
      if ($target_room_id === '') {
        continue;
      }

      // Check if this room is already in capabilities
      $already_present = FALSE;
      foreach ($capabilities as $cap) {
        if ((string) ($cap['target_room_id'] ?? '') === $target_room_id) {
          $already_present = TRUE;
          // Mark as quest-referenced
          $cap['quest_reference'] = TRUE;
          if (!isset($cap['quest_ids'])) {
            $cap['quest_ids'] = [];
          }
          if (!in_array($quest_id, $cap['quest_ids'], TRUE)) {
            $cap['quest_ids'][] = $quest_id;
          }
          break;
        }
      }

      // If not present, add as synthetic capability
      if (!$already_present) {
        $capabilities[] = [
          'connection_id' => "quest-synthetic-{$target_room_id}",
          'origin_room_id' => $room_id,
          'target_room_id' => $target_room_id,
          'destination_type' => 'room',
          'destination_id' => $target_room_id,
          'distance' => 0,
          'type' => 'synthetic',
          'available' => TRUE,
          'blocked_reason' => NULL,
          'is_discovered' => TRUE,
          'is_passable' => TRUE,
          'bidirectional' => FALSE,
          'requires_interaction' => FALSE,
          'quest_reference' => TRUE,
          'quest_ids' => [$quest_id],
        ];
      }
    }

    return $capabilities;
  }

  /**
   * Extracts destination references from active quests.
   *
   * @param array $active_quests
   *   Array of quest data, each with 'quest_id' and 'objectives' fields.
   *
   * @return array<int, array<string, string>>
   *   Array of ['destination' => ..., 'quest_id' => ...] references.
   */
  protected function extractQuestDestinations(array $active_quests): array {
    $destinations = [];

    foreach ($active_quests as $quest) {
      $quest_id = (string) ($quest['quest_id'] ?? '');
      if ($quest_id === '') {
        continue;
      }

      $objectives = (array) ($quest['objectives'] ?? []);
      foreach ($objectives as $objective) {
        $destination = trim((string) ($objective['destination'] ?? 
                                      $objective['destination_id'] ?? ''));
        if ($destination !== '') {
          $destinations[] = [
            'destination' => $destination,
            'quest_id' => $quest_id,
          ];
        }
      }
    }

    return $destinations;
  }

  /**
   * Finds a room by room_id or room name.
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $identifier
   *   The room_id or room name to find.
   *
   * @return array|null
   *   The room data if found, null otherwise.
   */
  protected function findRoomByIdOrName(array $dungeon_data, string $identifier): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') {
      return NULL;
    }

    $rooms = (array) ($dungeon_data['rooms'] ?? []);

    // Try exact match on room_id first
    foreach ($rooms as $room) {
      if ((string) ($room['room_id'] ?? '') === $identifier) {
        return $room;
      }
    }

    // Try exact match on room name
    foreach ($rooms as $room) {
      if ((string) ($room['name'] ?? '') === $identifier) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Build navigation capabilities with road network access.
   *
   * Extends buildNavigationCapabilities() with road network logic:
   * - If current room has a road connection, gain access to all other road-connected rooms
   * - If current room has no road connection, limited to direct connections only
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $room_id
   *   The current room ID.
   *
   * @return array<int, array<string, mixed>>
   *   Navigation capabilities including road network destinations.
   */
  public function buildNavigationCapabilitiesWithRoadNetwork(
    array $dungeon_data,
    string $room_id
  ): array {
    // Start with normal capabilities
    $capabilities = $this->buildNavigationCapabilities($dungeon_data, $room_id);

    // Check if current room has any road connection
    $current_room_has_road = $this->hasRoadConnection($dungeon_data, $room_id);
    if (!$current_room_has_road) {
      return $capabilities; // No road access, limited to direct connections
    }

    // Current room has road access — add all other road-connected rooms
    $road_network_rooms = $this->extractRoadNetworkRooms($dungeon_data, $room_id);

    foreach ($road_network_rooms as $target_room_id) {
      // Check if already in capabilities (direct connection)
      $already_present = FALSE;
      foreach ($capabilities as $cap) {
        if ((string) ($cap['target_room_id'] ?? '') === $target_room_id) {
          $already_present = TRUE;
          break;
        }
      }

      if (!$already_present) {
        // Add synthetic road network capability
        $capability = $this->synthesizeRoadCapability($dungeon_data, $room_id, $target_room_id);
        if ($capability !== NULL) {
          $capabilities[] = $capability;
        }
      }
    }

    // Re-sort after adding road network destinations
    usort($capabilities, static function (array $left, array $right): int {
      $left_available = !empty($left['available']) ? 0 : 1;
      $right_available = !empty($right['available']) ? 0 : 1;
      if ($left_available !== $right_available) {
        return $left_available <=> $right_available;
      }

      return strcmp((string) ($left['target_room_id'] ?? ''), (string) ($right['target_room_id'] ?? ''));
    });

    return $capabilities;
  }

  /**
   * Check if a room has any road connection.
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $room_id
   *   The room ID to check.
   *
   * @return bool
   *   TRUE if room has a road connection, FALSE otherwise.
   */
  protected function hasRoadConnection(array $dungeon_data, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }

    foreach ($this->extractConnections($dungeon_data) as $connection) {
      $from = trim((string) ($connection['from_room'] ?? ''));
      if ($from !== $room_id) {
        continue;
      }

      $destination_type = $this->resolveDestinationType($connection);
      if ($destination_type === 'road') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Extract all rooms with road connections (road network membership).
   *
   * Returns all rooms that have at least one road connection, excluding
   * the current room (since it already has direct access to its own room).
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $room_id
   *   The current room ID (excluded from results).
   *
   * @return array<int, string>
   *   Array of room IDs that are part of the road network.
   */
  protected function extractRoadNetworkRooms(
    array $dungeon_data,
    string $room_id
  ): array {
    $room_id = trim($room_id);
    $road_rooms = [];

    foreach ($this->extractConnections($dungeon_data) as $connection) {
      $from = trim((string) ($connection['from_room'] ?? ''));
      if ($from === '') {
        continue;
      }

      // Skip current room (already accessible)
      if ($from === $room_id) {
        continue;
      }

      $destination_type = $this->resolveDestinationType($connection);
      if ($destination_type === 'road') {
        // This room has a road connection
        if (!in_array($from, $road_rooms, TRUE)) {
          $road_rooms[] = $from;
        }
      }
    }

    return $road_rooms;
  }

  /**
   * Synthesize a road network navigation capability.
   *
   * Creates a synthetic capability for navigating to another room via the
   * road network. These are always marked as type='road_network'.
   *
   * @param array $dungeon_data
   *   The dungeon data.
   * @param string $from_room_id
   *   The current room ID.
   * @param string $to_room_id
   *   The destination room ID (via road network).
   *
   * @return array<string, mixed>|null
   *   Synthetic capability, or NULL if room not found.
   */
  protected function synthesizeRoadCapability(
    array $dungeon_data,
    string $from_room_id,
    string $to_room_id
  ): ?array {
    $to_room = $this->findRoomById($dungeon_data, $to_room_id);
    if ($to_room === NULL) {
      return NULL;
    }

    return [
      'connection_id' => "road-network-synthetic-{$from_room_id}-to-{$to_room_id}",
      'origin_room_id' => $from_room_id,
      'target_room_id' => $to_room_id,
      'destination_type' => 'room',
      'destination_id' => $to_room_id,
      'distance' => 0, // Road network is abstract (distance handled by road system)
      'type' => 'road_network',
      'available' => TRUE,
      'blocked_reason' => NULL,
      'is_discovered' => FALSE, // Not discovered until explicitly visited
      'is_passable' => TRUE,
      'bidirectional' => TRUE, // Road network is bidirectional
      'requires_interaction' => FALSE,
      'is_road_network' => TRUE, // Mark as road network synthetic
    ];
  }

}
