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
      $capability = $this->buildCapabilityFromConnection($connection, $room_id);
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
  protected function buildCapabilityFromConnection(array $connection, string $room_id): ?array {
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

    $blocked_reason = NULL;
    if ($target_room_id === '') {
      $blocked_reason = 'unresolved_destination';
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
      'type' => $type,
      'available' => $blocked_reason === NULL,
      'blocked_reason' => $blocked_reason,
      'is_discovered' => $is_discovered,
      'is_passable' => $is_passable,
      'bidirectional' => $bidirectional,
      'requires_interaction' => $requires_interaction,
      'origin_hex' => $origin_hex,
      'target_hex' => $target_hex,
    ];
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
    return $from_room . '__' . $to_room;
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

}
