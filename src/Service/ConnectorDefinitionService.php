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
    $this->assertConnectorEndpointColumnsPresent('dungeoncrawler_content_connections');
    $endpoint_hexes = $this->requireConnectorEndpointHexes($data);
    $endpoint_h3 = $this->resolveConnectorEndpointH3Indexes($data, $endpoint_hexes);
    $connection_id = $this->deriveConnectionId($data);
    $now = time();

    $fields = [
      'dungeon_id' => (string) $data['dungeon_id'],
      'from_room_id' => (string) $data['from_room_id'],
      'to_room_id' => (string) $data['to_room_id'],
      'from_hex_q' => $endpoint_hexes['from_hex']['q'],
      'from_hex_r' => $endpoint_hexes['from_hex']['r'],
      'to_hex_q' => $endpoint_hexes['to_hex']['q'],
      'to_hex_r' => $endpoint_hexes['to_hex']['r'],
      'from_h3_index_res14' => $endpoint_h3['from_h3_index_res14'],
      'to_h3_index_res14' => $endpoint_h3['to_h3_index_res14'],
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
    ];
    $updated_rows = $this->database->update('dungeoncrawler_content_connections')
      ->fields($fields)
      ->condition('connection_id', $connection_id)
      ->execute();
    if ((int) $updated_rows === 0) {
      $this->database->insert('dungeoncrawler_content_connections')
        ->fields($fields + [
          'connection_id' => $connection_id,
          'created' => $now,
        ])
        ->execute();
    }

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

    if (!$row) {
      return NULL;
    }
    $row = $this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']);
    return $this->hydrateEndpointHexes($row);
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
      fn(array $row) => $this->hydrateEndpointHexes($this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data'])),
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
      fn(array $row) => $this->hydrateEndpointHexes($this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data'])),
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

    $layout_map = $this->loadRoomLayoutMapForDungeonPayload($dungeon_data);
    $normalized_connectors = [];
    $count = 0;
    foreach ($raw_connections as $index => $raw) {
      if (!is_array($raw)) {
        throw new \InvalidArgumentException(sprintf(
          'Connector sync contract violation: %s connection[%d] must be an object payload.',
          $dungeon_id,
          (int) $index
        ));
      }

      $from = trim((string) ($raw['from_room_id'] ?? $raw['from_room'] ?? ''));
      $to = trim((string) ($raw['to_room_id'] ?? $raw['to_room'] ?? ''));
      if ($from === '' || $to === '') {
        throw new \InvalidArgumentException(sprintf(
          'Connector sync contract violation: %s connection[%d] missing from_room_id/to_room_id.',
          $dungeon_id,
          (int) $index
        ));
      }

      $normalized = ConnectorGenerationPolicy::normalizeFromRawJson($dungeon_id, $raw);
      $normalized = $this->enforceEndpointHexContract($normalized, $layout_map, $dungeon_id, 'json_connection');
      $connection_id = $this->deriveConnectionId($normalized);
      $normalized_connectors[$connection_id] = $normalized;
    }

    foreach ($this->synthesizeConnectorsFromRoomExitLayouts($dungeon_id, $dungeon_data) as $synthesized) {
      $synthesized = $this->enforceEndpointHexContract($synthesized, $layout_map, $dungeon_id, 'layout_exit');
      $connection_id = $this->deriveConnectionId($synthesized);
      if (!isset($normalized_connectors[$connection_id])) {
        $normalized_connectors[$connection_id] = $synthesized;
      }
    }

    foreach ($normalized_connectors as $normalized) {
      $this->saveCanonicalConnector($normalized);
      $count++;
    }

    return $count;
  }

  /**
   * Build canonical connector rows from room layout_data.exits when JSON
   * connection arrays are incomplete.
   *
   * All synthesized connectors default to bidirectional unless authoring
   * explicitly provides one-way edges through connector tables.
   *
   * @return array<int, array<string, mixed>>
   *   Canonical connector payload rows.
   */
  protected function synthesizeConnectorsFromRoomExitLayouts(string $dungeon_id, array $dungeon_data): array {
    $room_ids = [];
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room_entry) {
      $room_id = '';
      if (is_array($room_entry)) {
        $room_id = trim((string) ($room_entry['room_id'] ?? ''));
      }
      elseif (is_string($room_entry)) {
        $room_id = trim($room_entry);
      }
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    if ($room_ids === []) {
      return [];
    }

    $rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'layout_data'])
      ->condition('room_id', array_keys($room_ids), 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($rows) || $rows === []) {
      throw new \InvalidArgumentException(sprintf(
        'Connector sync contract violation: canonical room layout rows are missing for dungeon %s.',
        $dungeon_id
      ));
    }

    $connectors = [];
    $seen_pairs = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $from_room_id = trim((string) ($row['room_id'] ?? ''));
      if ($from_room_id === '') {
        continue;
      }

      $layout_data = json_decode((string) ($row['layout_data'] ?? ''), TRUE);
      if (!is_array($layout_data)) {
        throw new \InvalidArgumentException(sprintf(
          'Connector sync contract violation: room %s in dungeon %s has invalid layout_data JSON.',
          $from_room_id,
          $dungeon_id
        ));
      }
      $layout_hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
      if ($layout_hexes === []) {
        throw new \InvalidArgumentException(sprintf(
          'Connector sync contract violation: room %s in dungeon %s has no layout_data.hexes.',
          $from_room_id,
          $dungeon_id
        ));
      }
      $exit_links = is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [];
      foreach ($exit_links as $exit_link) {
        if (!is_array($exit_link)) {
          continue;
        }
        $to_room_id = trim((string) ($exit_link['target_room_id'] ?? ''));
        if ($to_room_id === '' || $to_room_id === $from_room_id) {
          continue;
        }

        $pair = [$from_room_id, $to_room_id];
        sort($pair, SORT_STRING);
        $pair_key = $pair[0] . '::' . $pair[1];
        if (isset($seen_pairs[$pair_key])) {
          continue;
        }
        $seen_pairs[$pair_key] = TRUE;

        $connectors[] = [
          'dungeon_id' => $dungeon_id,
          'from_room_id' => $from_room_id,
          'to_room_id' => $to_room_id,
          'kind' => 'hallway',
          'direction' => 'bidirectional',
          'default_state' => 'open',
          'trap_data' => NULL,
          'lock_data' => NULL,
          'requirements_data' => NULL,
          'description' => 'Synthesized from canonical layout_data.exits',
          'travel_cost' => 0,
          'is_discovered_default' => 1,
        ];
      }
    }

    return $connectors;
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
    $this->assertConnectorEndpointColumnsPresent('dc_campaign_connections');
    $endpoint_hexes = $this->requireConnectorEndpointHexes($data);
    $endpoint_h3 = $this->resolveConnectorEndpointH3Indexes($data, $endpoint_hexes);
    $connection_id = $this->deriveConnectionId($data);
    $now = time();

    $fields = [
      'dungeon_id' => (string) $data['dungeon_id'],
      'from_room_id' => (string) $data['from_room_id'],
      'to_room_id' => (string) $data['to_room_id'],
      'from_hex_q' => $endpoint_hexes['from_hex']['q'],
      'from_hex_r' => $endpoint_hexes['from_hex']['r'],
      'to_hex_q' => $endpoint_hexes['to_hex']['q'],
      'to_hex_r' => $endpoint_hexes['to_hex']['r'],
      'from_h3_index_res14' => $endpoint_h3['from_h3_index_res14'],
      'to_h3_index_res14' => $endpoint_h3['to_h3_index_res14'],
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
    ];
    $updated_rows = $this->database->update('dc_campaign_connections')
      ->fields($fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('connection_id', $connection_id)
      ->execute();
    if ((int) $updated_rows === 0) {
      $this->database->insert('dc_campaign_connections')
        ->fields($fields + [
          'campaign_id' => $campaign_id,
          'connection_id' => $connection_id,
          'created' => $now,
        ])
        ->execute();
    }

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
      return $this->hydrateEndpointHexes($this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data']));
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

    $canonical_rows = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c')
      ->condition('dungeon_id', $dungeon_id)
      ->execute()
      ->fetchAllAssoc('connection_id', \PDO::FETCH_ASSOC);

    $merged_rows = $canonical_rows;
    foreach ($campaign_rows as $connection_id => $campaign_row) {
      $merged_rows[$connection_id] = $campaign_row;
    }
    $merged_rows = $this->augmentCampaignConnectorRowsFromCampaignRoomExits($campaign_id, $dungeon_id, $merged_rows);

    return array_values(array_map(
      fn(array $row) => $this->hydrateEndpointHexes($this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data'])),
      $merged_rows
    ));
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

    $campaign_rows = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $connection_id = trim((string) ($row['connection_id'] ?? ''));
      if ($connection_id === '') {
        continue;
      }
      $campaign_rows[$connection_id] = $row;
    }

    $canonical_query = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c');
    $canonical_or = $canonical_query->orConditionGroup()
      ->condition('from_room_id', $room_id)
      ->condition('to_room_id', $room_id);
    $canonical_query->condition($canonical_or);
    $canonical_rows = $canonical_query->execute()->fetchAllAssoc('connection_id', \PDO::FETCH_ASSOC);

    $merged_rows = $canonical_rows;
    foreach ($campaign_rows as $connection_id => $campaign_row) {
      $merged_rows[$connection_id] = $campaign_row;
    }
    $merged_rows = $this->augmentCampaignConnectorRowsFromCampaignRoomExits($campaign_id, '', $merged_rows);

    return array_values(array_map(
      fn(array $row) => $this->hydrateEndpointHexes($this->decodeJsonFields($row, ['trap_data', 'lock_data', 'requirements_data'])),
      $merged_rows
    ));
  }

  /**
   * Backfill missing connector rows from authored campaign room exits.
   *
   * This keeps navigation authoritative to table rows while preventing
   * partially-copied campaign connector sets from hiding valid authored exits.
   *
   * @param array<string, array<string, mixed>> $rows_by_connection_id
   *   Existing connector rows keyed by connection_id.
   *
   * @return array<string, array<string, mixed>>
   *   Augmented connector rows keyed by connection_id.
   */
  protected function augmentCampaignConnectorRowsFromCampaignRoomExits(int $campaign_id, string $dungeon_id, array $rows_by_connection_id): array {
    if ($campaign_id <= 0) {
      return $rows_by_connection_id;
    }

    $room_rows = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'layout_data'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($room_rows) || $room_rows === []) {
      return $rows_by_connection_id;
    }

    $edge_keys = [];
    $candidate_room_ids = [];
    foreach ($rows_by_connection_id as $row) {
      if (!is_array($row)) {
        continue;
      }
      $from_room_id = trim((string) ($row['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($row['to_room_id'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }
      $edge_keys[$from_room_id . '>' . $to_room_id] = TRUE;
      $candidate_room_ids[$from_room_id] = TRUE;
      $candidate_room_ids[$to_room_id] = TRUE;
      $direction = strtolower(trim((string) ($row['direction'] ?? '')));
      if ($direction !== 'one_way') {
        $edge_keys[$to_room_id . '>' . $from_room_id] = TRUE;
      }
    }

    $authored_exits = [];
    $room_name_map = [];
    foreach ($room_rows as $room_row) {
      if (!is_array($room_row)) {
        continue;
      }
      $from_room_id = trim((string) ($room_row['room_id'] ?? ''));
      if ($from_room_id === '') {
        continue;
      }
      $from_room_name = trim((string) ($room_row['name'] ?? ''));
      if ($from_room_name !== '' && !isset($room_name_map[$from_room_id])) {
        $room_name_map[$from_room_id] = $from_room_name;
      }
      $layout_data = json_decode((string) ($room_row['layout_data'] ?? '{}'), TRUE);
      if (!is_array($layout_data)) {
        continue;
      }
      foreach ((array) ($layout_data['exits'] ?? []) as $exit) {
        if (!is_array($exit)) {
          continue;
        }
        $to_room_id = trim((string) ($exit['target_room_id'] ?? ''));
        if ($to_room_id === '' || $to_room_id === $from_room_id) {
          continue;
        }
        $authored_exits[] = [
          'from_room_id' => $from_room_id,
          'to_room_id' => $to_room_id,
          'from_hex_q' => is_numeric($exit['q'] ?? NULL) ? (int) $exit['q'] : NULL,
          'from_hex_r' => is_numeric($exit['r'] ?? NULL) ? (int) $exit['r'] : NULL,
        ];
        $candidate_room_ids[$from_room_id] = TRUE;
        $candidate_room_ids[$to_room_id] = TRUE;
      }
    }
    if ($authored_exits === []) {
      return $rows_by_connection_id;
    }

    $canonical_by_edge = [];
    if ($candidate_room_ids !== []) {
      $missing_name_ids = array_values(array_filter(array_keys($candidate_room_ids), static function (string $room_id) use ($room_name_map): bool {
        return !isset($room_name_map[$room_id]);
      }));
      if ($missing_name_ids !== []) {
        $name_rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
          ->fields('r', ['room_id', 'name'])
          ->condition('room_id', $missing_name_ids, 'IN')
          ->execute()
          ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ((array) $name_rows as $name_row) {
          if (!is_array($name_row)) {
            continue;
          }
          $room_id = trim((string) ($name_row['room_id'] ?? ''));
          $room_name = trim((string) ($name_row['name'] ?? ''));
          if ($room_id !== '' && $room_name !== '' && !isset($room_name_map[$room_id])) {
            $room_name_map[$room_id] = $room_name;
          }
        }
      }

      $canonical_query = $this->database->select('dungeoncrawler_content_connections', 'c')
        ->fields('c');
      $canonical_query->condition(
        $canonical_query->orConditionGroup()
          ->condition('from_room_id', array_keys($candidate_room_ids), 'IN')
          ->condition('to_room_id', array_keys($candidate_room_ids), 'IN')
      );
      $canonical_rows = $canonical_query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ((array) $canonical_rows as $canonical_row) {
        if (!is_array($canonical_row)) {
          continue;
        }
        $from_room_id = trim((string) ($canonical_row['from_room_id'] ?? ''));
        $to_room_id = trim((string) ($canonical_row['to_room_id'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '') {
          continue;
        }
        $canonical_by_edge[$from_room_id . '>' . $to_room_id] = $canonical_row;
      }
    }

    foreach ($authored_exits as $authored_exit) {
      $from_room_id = (string) $authored_exit['from_room_id'];
      $to_room_id = (string) $authored_exit['to_room_id'];
      $edge_key = $from_room_id . '>' . $to_room_id;
      if (isset($edge_keys[$edge_key])) {
        continue;
      }

      $canonical_forward = $canonical_by_edge[$edge_key] ?? NULL;
      $canonical_reverse = $canonical_by_edge[$to_room_id . '>' . $from_room_id] ?? NULL;

      $template_row = NULL;
      $reverse = FALSE;
      if (is_array($canonical_forward)) {
        $template_row = $canonical_forward;
      }
      elseif (is_array($canonical_reverse)) {
        $reverse_direction = strtolower(trim((string) ($canonical_reverse['direction'] ?? 'bidirectional')));
        if ($reverse_direction !== 'one_way') {
          $template_row = $canonical_reverse;
          $reverse = TRUE;
        }
      }

      $from_hex_q = $authored_exit['from_hex_q'];
      $from_hex_r = $authored_exit['from_hex_r'];
      $to_hex_q = NULL;
      $to_hex_r = NULL;
      $from_h3_index_res14 = '';
      $to_h3_index_res14 = '';
      $direction = 'bidirectional';
      $kind = 'hallway';
      $state = 'open';
      $travel_cost = 0;
      $is_discovered = 1;
      $source_connection_id = NULL;

      if (is_array($template_row)) {
        $direction = (string) ($template_row['direction'] ?? $direction);
        $kind = (string) ($template_row['kind'] ?? $kind);
        $state = (string) ($template_row['default_state'] ?? $state);
        $travel_cost = max(0, (int) ($template_row['travel_cost'] ?? $travel_cost));
        $is_discovered = !empty($template_row['is_discovered_default']) ? 1 : 0;
        $source_connection_id = (string) ($template_row['connection_id'] ?? '');
        if (!$reverse) {
          $to_hex_q = is_numeric($template_row['to_hex_q'] ?? NULL) ? (int) $template_row['to_hex_q'] : NULL;
          $to_hex_r = is_numeric($template_row['to_hex_r'] ?? NULL) ? (int) $template_row['to_hex_r'] : NULL;
          $from_h3_index_res14 = strtolower(trim((string) ($template_row['from_h3_index_res14'] ?? '')));
          $to_h3_index_res14 = strtolower(trim((string) ($template_row['to_h3_index_res14'] ?? '')));
          if ($from_hex_q === NULL && is_numeric($template_row['from_hex_q'] ?? NULL)) {
            $from_hex_q = (int) $template_row['from_hex_q'];
          }
          if ($from_hex_r === NULL && is_numeric($template_row['from_hex_r'] ?? NULL)) {
            $from_hex_r = (int) $template_row['from_hex_r'];
          }
        }
        else {
          $to_hex_q = is_numeric($template_row['from_hex_q'] ?? NULL) ? (int) $template_row['from_hex_q'] : NULL;
          $to_hex_r = is_numeric($template_row['from_hex_r'] ?? NULL) ? (int) $template_row['from_hex_r'] : NULL;
          $from_h3_index_res14 = strtolower(trim((string) ($template_row['to_h3_index_res14'] ?? '')));
          $to_h3_index_res14 = strtolower(trim((string) ($template_row['from_h3_index_res14'] ?? '')));
          if ($from_hex_q === NULL && is_numeric($template_row['to_hex_q'] ?? NULL)) {
            $from_hex_q = (int) $template_row['to_hex_q'];
          }
          if ($from_hex_r === NULL && is_numeric($template_row['to_hex_r'] ?? NULL)) {
            $from_hex_r = (int) $template_row['to_hex_r'];
          }
        }
      }

      $synthesized = [
        'connection_id' => $this->deriveConnectionId([
          'dungeon_id' => $dungeon_id !== '' ? $dungeon_id : ($template_row['dungeon_id'] ?? 'unknown'),
          'from_room_id' => $from_room_id,
          'to_room_id' => $to_room_id,
          'kind' => $kind,
        ]),
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id !== '' ? $dungeon_id : (string) ($template_row['dungeon_id'] ?? ''),
        'from_room_id' => $from_room_id,
        'to_room_id' => $to_room_id,
        'from_room_name' => $room_name_map[$from_room_id] ?? '',
        'to_room_name' => $room_name_map[$to_room_id] ?? '',
        'from_hex_q' => $from_hex_q ?? 0,
        'from_hex_r' => $from_hex_r ?? 0,
        'to_hex_q' => $to_hex_q ?? 0,
        'to_hex_r' => $to_hex_r ?? 0,
        'from_h3_index_res14' => $from_h3_index_res14,
        'to_h3_index_res14' => $to_h3_index_res14,
        'direction' => $direction,
        'kind' => $kind,
        'state' => $state,
        'trap_data' => NULL,
        'lock_data' => NULL,
        'requirements_data' => NULL,
        'description' => 'Synthesized from campaign room layout_data.exits',
        'travel_cost' => $travel_cost,
        'is_discovered' => $is_discovered,
        'is_passable' => $this->computeIsPassable($state),
        'source_connection_id' => $source_connection_id,
      ];

      $rows_by_connection_id[(string) $synthesized['connection_id']] = $synthesized;
      $edge_keys[$edge_key] = TRUE;
      if (strtolower($direction) !== 'one_way') {
        $edge_keys[$to_room_id . '>' . $from_room_id] = TRUE;
      }
    }

    return $rows_by_connection_id;
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

  /**
   * Ensure connector payload carries both endpoint hex anchors.
   *
   * @param array<string,mixed> $data
   *   Connector payload.
   *
   * @return array{from_hex: array{q:int,r:int}, to_hex: array{q:int,r:int}}
   *   Normalized endpoint hexes.
   */
  protected function requireConnectorEndpointHexes(array $data): array {
    $from_hex = $this->extractConnectorHex($data, 'from');
    $to_hex = $this->extractConnectorHex($data, 'to');
    if ($from_hex === NULL || $to_hex === NULL) {
      throw new \InvalidArgumentException('ConnectorDefinitionService: endpoint hexes are required (from_hex/to_hex).');
    }

    return ['from_hex' => $from_hex, 'to_hex' => $to_hex];
  }

  /**
   * Enforce endpoint-hex contract using explicit data or room layout derivation.
   *
   * @param array<string,mixed> $connector
   *   Connector payload.
   * @param array<string, array<string,mixed>> $layout_map
   *   Layout map keyed by room_id.
   * @param string $dungeon_id
   *   Dungeon id for errors.
   * @param string $source
   *   Contract source label.
   *
   * @return array<string,mixed>
   *   Connector with explicit from_hex/to_hex.
   */
  protected function enforceEndpointHexContract(array $connector, array $layout_map, string $dungeon_id, string $source): array {
    $from_room_id = trim((string) ($connector['from_room_id'] ?? ''));
    $to_room_id = trim((string) ($connector['to_room_id'] ?? ''));
    if ($from_room_id === '' || $to_room_id === '') {
      throw new \InvalidArgumentException(sprintf(
        'Connector endpoint contract violation (%s): %s missing from_room_id/to_room_id.',
        $source,
        $dungeon_id
      ));
    }

    $from_hex = $this->extractConnectorHex($connector, 'from');
    $to_hex = $this->extractConnectorHex($connector, 'to');
    $from_layout = $layout_map[$from_room_id] ?? [];
    $to_layout = $layout_map[$to_room_id] ?? [];

    if ($from_hex === NULL) {
      $from_hex = $this->extractExitHexForTarget($from_layout, $to_room_id);
    }
    if ($to_hex === NULL) {
      $to_hex = $this->extractExitHexForTarget($to_layout, $from_room_id);
    }
    if ($from_hex === NULL) {
      $from_hex = $this->resolveRoomAnchorHex($from_layout, TRUE);
    }
    if ($to_hex === NULL) {
      $to_hex = $this->resolveRoomAnchorHex($to_layout, FALSE);
    }

    if ($from_hex === NULL || $to_hex === NULL) {
      throw new \InvalidArgumentException(sprintf(
        'Connector endpoint contract violation (%s): %s %s -> %s missing resolvable endpoint hexes.',
        $source,
        $dungeon_id,
        $from_room_id,
        $to_room_id
      ));
    }

    $connector['from_hex'] = $from_hex;
    $connector['to_hex'] = $to_hex;
    return $connector;
  }

  /**
   * Load canonical room layout map for rooms present in one dungeon payload.
   *
   * @param array<string,mixed> $dungeon_data
   *   Dungeon payload.
   *
   * @return array<string, array<string,mixed>>
   *   Layout map keyed by room_id.
   */
  protected function loadRoomLayoutMapForDungeonPayload(array $dungeon_data): array {
    $room_ids = [];
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room_entry) {
      if (is_array($room_entry)) {
        $room_id = trim((string) ($room_entry['room_id'] ?? ''));
      }
      else {
        $room_id = trim((string) $room_entry);
      }
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    if ($room_ids === []) {
      return [];
    }

    $rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'layout_data'])
      ->condition('room_id', array_keys($room_ids), 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($rows) || $rows === []) {
      throw new \InvalidArgumentException('Connector layout contract violation: no canonical room layout rows found for dungeon payload room IDs.');
    }
    $layout_map = [];
    $seen_room_ids = [];
    foreach ((array) $rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $room_id = trim((string) ($row['room_id'] ?? ''));
      if ($room_id === '') {
        continue;
      }
      $seen_room_ids[$room_id] = TRUE;
      $layout = json_decode((string) ($row['layout_data'] ?? ''), TRUE);
      if (!is_array($layout)) {
        throw new \InvalidArgumentException(sprintf(
          'Connector layout contract violation: canonical room %s has invalid layout_data JSON.',
          $room_id
        ));
      }
      $layout_hexes = is_array($layout['hexes'] ?? NULL) ? $layout['hexes'] : [];
      if ($layout_hexes === []) {
        throw new \InvalidArgumentException(sprintf(
          'Connector layout contract violation: canonical room %s has no layout_data.hexes.',
          $room_id
        ));
      }
      $layout_map[$room_id] = $layout;
    }
    $missing_room_ids = array_values(array_diff(array_keys($room_ids), array_keys($seen_room_ids)));
    if ($missing_room_ids !== []) {
      throw new \InvalidArgumentException(sprintf(
        'Connector layout contract violation: missing canonical room rows for room IDs: %s',
        implode(', ', $missing_room_ids)
      ));
    }

    return $layout_map;
  }

  /**
   * Extract endpoint hex from connector payload.
   *
   * Supports from_hex/to_hex arrays and q/r scalar columns.
   *
   * @param array<string,mixed> $connector
   *   Connector payload.
   * @param string $endpoint
   *   from|to
   *
   * @return array{q:int,r:int}|null
   *   Parsed coordinate.
   */
  protected function extractConnectorHex(array $connector, string $endpoint): ?array {
    $hex = $connector[$endpoint . '_hex'] ?? NULL;
    if (is_array($hex) && isset($hex['q'], $hex['r']) && is_numeric($hex['q']) && is_numeric($hex['r'])) {
      return ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
    }

    $q_key = $endpoint . '_hex_q';
    $r_key = $endpoint . '_hex_r';
    if (array_key_exists($q_key, $connector) && array_key_exists($r_key, $connector) && is_numeric($connector[$q_key]) && is_numeric($connector[$r_key])) {
      return ['q' => (int) $connector[$q_key], 'r' => (int) $connector[$r_key]];
    }

    return NULL;
  }

  /**
   * Extract explicit exit-link coordinate for one target room.
   *
   * @param array<string,mixed> $layout
   *   Room layout_data.
   * @param string $target_room_id
   *   Linked target room id.
   *
   * @return array{q:int,r:int}|null
   *   Exit coordinate.
   */
  protected function extractExitHexForTarget(array $layout, string $target_room_id): ?array {
    foreach ((array) ($layout['exits'] ?? []) as $link) {
      if (!is_array($link)) {
        continue;
      }
      if (trim((string) ($link['target_room_id'] ?? '')) !== $target_room_id) {
        continue;
      }

      if (isset($link['q'], $link['r']) && is_numeric($link['q']) && is_numeric($link['r'])) {
        return ['q' => (int) $link['q'], 'r' => (int) $link['r']];
      }
      if (
        is_array($link['hex'] ?? NULL)
        && isset($link['hex']['q'], $link['hex']['r'])
        && is_numeric($link['hex']['q'])
        && is_numeric($link['hex']['r'])
      ) {
        return ['q' => (int) $link['hex']['q'], 'r' => (int) $link['hex']['r']];
      }
    }

    return NULL;
  }

  /**
   * Resolve deterministic fallback room anchor.
   *
   * @param array<string,mixed> $layout
   *   Room layout_data.
   * @param bool $prefer_exit
   *   TRUE picks exit_points first; FALSE picks entry_points first.
   *
   * @return array{q:int,r:int}|null
   *   Anchor coordinate.
   */
  protected function resolveRoomAnchorHex(array $layout, bool $prefer_exit): ?array {
    $point_sets = $prefer_exit
      ? [(array) ($layout['exit_points'] ?? []), (array) ($layout['entry_points'] ?? [])]
      : [(array) ($layout['entry_points'] ?? []), (array) ($layout['exit_points'] ?? [])];
    foreach ($point_sets as $points) {
      foreach ($points as $point) {
        if (is_array($point) && isset($point['q'], $point['r']) && is_numeric($point['q']) && is_numeric($point['r'])) {
          return ['q' => (int) $point['q'], 'r' => (int) $point['r']];
        }
      }
    }

    foreach ((array) ($layout['hexes'] ?? []) as $hex) {
      if (is_array($hex) && isset($hex['q'], $hex['r']) && is_numeric($hex['q']) && is_numeric($hex['r'])) {
        return ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
      }
    }

    return NULL;
  }

  /**
   * Backward-compat hydrate endpoint hex arrays from scalar DB columns.
   *
   * @param array<string,mixed> $row
   *   DB row.
   *
   * @return array<string,mixed>
   *   Row with from_hex/to_hex arrays.
   */
  protected function hydrateEndpointHexes(array $row): array {
    $from_hex = $this->extractConnectorHex($row, 'from');
    $to_hex = $this->extractConnectorHex($row, 'to');
    if ($from_hex !== NULL) {
      $row['from_hex'] = $from_hex;
    }
    if ($to_hex !== NULL) {
      $row['to_hex'] = $to_hex;
    }

    return $row;
  }

  /**
   * Ensure connector table has endpoint-hex scalar columns before write paths.
   *
   * @throws \RuntimeException
   */
  protected function assertConnectorEndpointColumnsPresent(string $table_name): void {
    $schema = $this->database->schema();
    if (!$schema->tableExists($table_name)) {
      throw new \RuntimeException(sprintf('ConnectorDefinitionService: required table "%s" is missing.', $table_name));
    }

    foreach (['from_hex_q', 'from_hex_r', 'to_hex_q', 'to_hex_r', 'from_h3_index_res14', 'to_h3_index_res14'] as $field_name) {
      if (!$schema->fieldExists($table_name, $field_name)) {
        throw new \RuntimeException(sprintf(
          'ConnectorDefinitionService: table "%s" is missing required field "%s". Run update hooks 10159 and 10177.',
          $table_name,
          $field_name
        ));
      }
    }
  }

  /**
   * Resolve deterministic H3 endpoint indexes for one connector payload.
   *
   * @param array<string,mixed> $data
   *   Connector payload.
   * @param array{from_hex: array{q:int,r:int}, to_hex: array{q:int,r:int}} $endpoint_hexes
   *   Resolved endpoint q/r coordinates.
   *
   * @return array{from_h3_index_res14:string,to_h3_index_res14:string}
   *   Normalized endpoint H3 values.
   */
  protected function resolveConnectorEndpointH3Indexes(array $data, array $endpoint_hexes): array {
    $from_h3 = strtolower(trim((string) ($data['from_h3_index_res14'] ?? $data['from_h3_index'] ?? '')));
    $to_h3 = strtolower(trim((string) ($data['to_h3_index_res14'] ?? $data['to_h3_index'] ?? '')));
    $from_room_id = trim((string) ($data['from_room_id'] ?? ''));
    $to_room_id = trim((string) ($data['to_room_id'] ?? ''));
    $dungeon_id = trim((string) ($data['dungeon_id'] ?? ''));

    if ($from_h3 === '') {
      $from_h3 = $this->lookupRoomHexH3IndexRes14($from_room_id, (int) $endpoint_hexes['from_hex']['q'], (int) $endpoint_hexes['from_hex']['r']);
    }
    if ($to_h3 === '') {
      $to_h3 = $this->lookupRoomHexH3IndexRes14($to_room_id, (int) $endpoint_hexes['to_hex']['q'], (int) $endpoint_hexes['to_hex']['r']);
    }
    if ($from_h3 === '' || $to_h3 === '') {
      throw new \RuntimeException(sprintf(
        'ConnectorDefinitionService contract violation: missing endpoint H3 index for %s (%s -> %s).',
        $dungeon_id !== '' ? $dungeon_id : 'unknown_dungeon',
        $from_room_id !== '' ? $from_room_id : 'unknown_from',
        $to_room_id !== '' ? $to_room_id : 'unknown_to'
      ));
    }

    return [
      'from_h3_index_res14' => $from_h3,
      'to_h3_index_res14' => $to_h3,
    ];
  }

  /**
   * Resolve room-owned endpoint H3 index from canonical sparse room-cell authority.
   */
  protected function lookupRoomHexH3IndexRes14(string $room_id, int $q, int $r): string {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return '';
    }
    $row = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['h3_index', 'h3_resolution'])
      ->condition('room_id', $room_id)
      ->condition('source_q', $q)
      ->condition('source_r', $r)
      ->condition('cell_role', ['room_hex', 'exit_gateway'], 'IN')
      ->orderBy('h3_resolution', 'DESC')
      ->orderBy('id', 'ASC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return '';
    }
    return strtolower(trim((string) ($row['h3_index'] ?? '')));
  }

}
