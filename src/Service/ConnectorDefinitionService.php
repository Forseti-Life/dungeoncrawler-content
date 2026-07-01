<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * CRUD and sync for canonical dungeon connector definitions.
 *
 * Authority boundary:
 * - dungeoncrawler_content_connections = canonical template (source of truth)
 * - dc_campaign_connections = campaign-runtime instance (copied from canonical)
 *
 * Callers must never write connection data directly to dungeon_data JSON after
 * this service is initialized. The JSON blob is a legacy read path only.
 */
class ConnectorDefinitionService {

  /**
   * Valid connector kind values.
   */
  public const KINDS = [
    'hallway',
    'archway',
    'door',
    'hatch',
    'portcullis',
    'secret_door',
    'magical_barrier',
    'collapsed',
    'bridge',
    'one_way_drop',
  ];

  /**
   * Valid direction values.
   */
  public const DIRECTIONS = ['bidirectional', 'one_way'];

  /**
   * Valid state values.
   */
  public const STATES = ['open', 'closed', 'locked', 'barred', 'trapped', 'triggered', 'destroyed'];

  public function __construct(
    protected readonly Connection $database,
  ) {}

  // ---------------------------------------------------------------------------
  // Canonical table: dungeoncrawler_content_connections
  // ---------------------------------------------------------------------------

  /**
   * Upsert one canonical connector. Returns the stable connection_id.
   *
   * @param array $data
   *   Keys: dungeon_id, from_room_id, to_room_id, kind, direction, default_state,
   *         trap_data, lock_data, requirements_data, description, travel_cost,
   *         is_discovered_default. connection_id is auto-derived when absent.
   *
   * @throws \InvalidArgumentException
   */
  public function saveCanonicalConnector(array $data): string {
    $this->validateConnectorData($data);
    $connection_id = $this->deriveConnectionId($data);
    $now = time();

    $this->database->merge('dungeoncrawler_content_connections')
      ->keys(['connection_id' => $connection_id])
      ->fields([
        'dungeon_id' => (string) $data['dungeon_id'],
        'from_room_id' => (string) $data['from_room_id'],
        'to_room_id' => (string) $data['to_room_id'],
        'direction' => (string) ($data['direction'] ?? 'bidirectional'),
        'kind' => (string) ($data['kind'] ?? 'hallway'),
        'default_state' => (string) ($data['default_state'] ?? 'open'),
        'trap_data' => isset($data['trap_data']) ? json_encode($data['trap_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
        'lock_data' => isset($data['lock_data']) ? json_encode($data['lock_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
        'requirements_data' => isset($data['requirements_data']) ? json_encode($data['requirements_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
        'description' => isset($data['description']) ? (string) $data['description'] : NULL,
        'travel_cost' => max(0, (int) ($data['travel_cost'] ?? 0)),
        'is_discovered_default' => empty($data['is_discovered_default']) ? 0 : 1,
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();

    return $connection_id;
  }

  /**
   * Load one canonical connector by connection_id. Returns null if not found.
   */
  public function loadCanonicalConnector(string $connection_id): ?array {
    $row = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c')
      ->condition('connection_id', $connection_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $row ? $this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']) : NULL;
  }

  /**
   * Load all canonical connectors for a dungeon.
   *
   * @return array<int, array<string, mixed>>
   */
  public function loadCanonicalConnectorsForDungeon(string $dungeon_id): array {
    $rows = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c')
      ->condition('dungeon_id', $dungeon_id)
      ->execute()
      ->fetchAllAssoc('connection_id', \PDO::FETCH_ASSOC);

    return array_values(array_map(
      fn(array $row) => $this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']),
      $rows
    ));
  }

  /**
   * Load all canonical connectors where a room is either endpoint.
   *
   * @return array<int, array<string, mixed>>
   */
  public function loadCanonicalConnectorsForRoom(string $room_id): array {
    $query = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c');
    $or = $query->orConditionGroup()
      ->condition('from_room_id', $room_id)
      ->condition('to_room_id', $room_id);
    $query->condition($or);

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_values(array_map(
      fn(array $row) => $this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']),
      $rows
    ));
  }

  /**
   * Delete one canonical connector.
   */
  public function deleteCanonicalConnector(string $connection_id): void {
    $this->database->delete('dungeoncrawler_content_connections')
      ->condition('connection_id', $connection_id)
      ->execute();
  }

  // ---------------------------------------------------------------------------
  // Sync: import from legacy dungeon_data JSON blobs
  // ---------------------------------------------------------------------------

  /**
   * Sync canonical connectors from a dungeon's JSON blob.
   *
   * Reads dungeon_data.hex_map.connections and persists each to the canonical
   * table. Safe to run multiple times (upsert). Returns count of synced rows.
   *
   * @param string $dungeon_id
   * @param array $dungeon_data
   *   Decoded dungeon_data payload.
   */
  public function syncFromDungeonPayload(string $dungeon_id, array $dungeon_data): int {
    $raw_connections = [];
    if (is_array($dungeon_data['hex_map']['connections'] ?? NULL)) {
      $raw_connections = array_merge($raw_connections, $dungeon_data['hex_map']['connections']);
    }
    if (is_array($dungeon_data['connections'] ?? NULL)) {
      $raw_connections = array_merge($raw_connections, $dungeon_data['connections']);
    }

    $count = 0;
    foreach ($raw_connections as $raw) {
      if (!is_array($raw)) {
        continue;
      }

      $from = trim((string) ($raw['from_room_id'] ?? $raw['from_room'] ?? ''));
      $to = trim((string) ($raw['to_room_id'] ?? $raw['to_room'] ?? ''));
      if ($from === '' || $to === '') {
        continue;
      }

      $normalized = ConnectorGenerationPolicy::normalizeFromRawJson($dungeon_id, $raw);
      $this->saveCanonicalConnector($normalized);
      $count++;
    }

    return $count;
  }

  /**
   * Sync all 19 canonical dungeons from their JSON blobs.
   *
   * @return array<string, int>
   *   Map of dungeon_id => synced connector count.
   */
  public function syncAllCanonicalDungeons(): array {
    $rows = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $summary = [];
    foreach ($rows as $row) {
      $dungeon_id = (string) $row['dungeon_id'];
      $dungeon_data = json_decode((string) $row['dungeon_data'], TRUE) ?? [];
      $summary[$dungeon_id] = $this->syncFromDungeonPayload($dungeon_id, $dungeon_data);
    }

    return $summary;
  }

  // ---------------------------------------------------------------------------
  // Campaign table: dc_campaign_connections
  // ---------------------------------------------------------------------------

  /**
   * Upsert one campaign-scoped connector instance. Returns connection_id.
   */
  public function saveCampaignConnector(int $campaign_id, array $data): string {
    $this->validateConnectorData($data);
    $connection_id = $this->deriveConnectionId($data);
    $now = time();

    $this->database->merge('dc_campaign_connections')
      ->keys(['campaign_id' => $campaign_id, 'connection_id' => $connection_id])
      ->fields([
        'dungeon_id' => (string) $data['dungeon_id'],
        'from_room_id' => (string) $data['from_room_id'],
        'to_room_id' => (string) $data['to_room_id'],
        'direction' => (string) ($data['direction'] ?? 'bidirectional'),
        'kind' => (string) ($data['kind'] ?? 'hallway'),
        'state' => (string) ($data['state'] ?? $data['default_state'] ?? 'open'),
        'trap_data' => isset($data['trap_data']) ? json_encode($data['trap_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
        'lock_data' => isset($data['lock_data']) ? json_encode($data['lock_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
        'requirements_data' => isset($data['requirements_data']) ? json_encode($data['requirements_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
        'description' => isset($data['description']) ? (string) $data['description'] : NULL,
        'travel_cost' => max(0, (int) ($data['travel_cost'] ?? 0)),
        'is_discovered' => empty($data['is_discovered_default']) && empty($data['is_discovered']) ? 0 : 1,
        'is_passable' => $this->computeIsPassable((string) ($data['state'] ?? $data['default_state'] ?? 'open')),
        'source_connection_id' => isset($data['connection_id']) ? (string) $data['connection_id'] : NULL,
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();

    return $connection_id;
  }

  /**
   * Load one campaign connector by connection_id. Falls back to canonical.
   *
   * Lookup priority: dc_campaign_connections → dungeoncrawler_content_connections.
   */
  public function loadCampaignConnector(int $campaign_id, string $connection_id): ?array {
    $row = $this->database->select('dc_campaign_connections', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id)
      ->condition('connection_id', $connection_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($row) {
      return $this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']);
    }

    return $this->loadCanonicalConnector($connection_id);
  }

  /**
   * Load all campaign connectors for a dungeon. Falls back to canonical rows.
   *
   * @return array<int, array<string, mixed>>
   */
  public function loadCampaignConnectorsForDungeon(int $campaign_id, string $dungeon_id): array {
    $campaign_rows = $this->database->select('dc_campaign_connections', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->execute()
      ->fetchAllAssoc('connection_id', \PDO::FETCH_ASSOC);

    if (!empty($campaign_rows)) {
      return array_values(array_map(
        fn(array $row) => $this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']),
        $campaign_rows
      ));
    }

    return $this->loadCanonicalConnectorsForDungeon($dungeon_id);
  }

  /**
   * Load all campaign connectors where a room is either endpoint.
   *
   * @return array<int, array<string, mixed>>
   */
  public function loadCampaignConnectorsForRoom(int $campaign_id, string $room_id): array {
    $query = $this->database->select('dc_campaign_connections', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id);
    $or = $query->orConditionGroup()
      ->condition('from_room_id', $room_id)
      ->condition('to_room_id', $room_id);
    $query->condition($or);

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($rows)) {
      return array_values(array_map(
        fn(array $row) => $this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']),
        $rows
      ));
    }

    return $this->loadCanonicalConnectorsForRoom($room_id);
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Derive a stable connection_id slug from connector data.
   */
  public function deriveConnectionId(array $data): string {
    if (!empty($data['connection_id'])) {
      return trim((string) $data['connection_id']);
    }

    $dungeon = trim((string) ($data['dungeon_id'] ?? 'unknown'));
    $from = trim((string) ($data['from_room_id'] ?? 'unknown'));
    $to = trim((string) ($data['to_room_id'] ?? 'unknown'));
    $kind = trim((string) ($data['kind'] ?? 'hallway'));

    return $dungeon . '::' . $from . '::' . $to . '::' . $kind;
  }

  /**
   * Compute is_passable from a state string.
   */
  public function computeIsPassable(string $state): int {
    return in_array($state, ['locked', 'barred', 'collapsed', 'destroyed', 'trapped'], TRUE) ? 0 : 1;
  }

  /**
   * Validate required connector fields.
   *
   * @throws \InvalidArgumentException
   */
  protected function validateConnectorData(array $data): void {
    foreach (['dungeon_id', 'from_room_id', 'to_room_id'] as $required) {
      if (empty($data[$required])) {
        throw new \InvalidArgumentException("ConnectorDefinitionService: '{$required}' is required.");
      }
    }

    if (isset($data['kind']) && !in_array($data['kind'], self::KINDS, TRUE)) {
      throw new \InvalidArgumentException("ConnectorDefinitionService: invalid kind '{$data['kind']}'.");
    }

    if (isset($data['direction']) && !in_array($data['direction'], self::DIRECTIONS, TRUE)) {
      throw new \InvalidArgumentException("ConnectorDefinitionService: invalid direction '{$data['direction']}'.");
    }

    $state_key = $data['state'] ?? $data['default_state'] ?? NULL;
    if ($state_key !== NULL && !in_array($state_key, self::STATES, TRUE)) {
      throw new \InvalidArgumentException("ConnectorDefinitionService: invalid state '{$state_key}'.");
    }
  }

  /**
   * Decode JSON blob fields on a database row.
   */
  protected function decodeJsonFields(array $row, array $fields): array {
    foreach ($fields as $field) {
      if (isset($row[$field]) && is_string($row[$field])) {
        $row[$field] = json_decode($row[$field], TRUE);
      }
    }
    return $row;
  }

}
