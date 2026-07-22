<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds runtime dungeon graph payloads from campaign room and connector tables.
 *
 * This service is the first cutover step away from taking graph truth from
 * dc_campaign_dungeons.dungeon_data. It still accepts an existing server-side
 * snapshot as input so non-graph delivery state can remain compatible while the
 * graph is rebuilt from authoritative campaign tables.
 */
class RuntimeGraphAssemblerService {

  public function __construct(
    protected readonly Connection $database,
    protected readonly GraphVersionService $graphVersionService,
  ) {}

  /**
   * Build a runtime graph payload for one campaign dungeon.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param string $dungeon_id
   *   Campaign dungeon identifier.
   * @param array<string, mixed> $base_payload
   *   Existing server-side snapshot payload used only for non-graph fields.
   * @param array<string, mixed> $options
   *   Optional overrides such as active_room_id and requested_room_id.
   *
   * @return array<string, mixed>
   *   Payload whose graph structure comes from campaign authority tables.
   */
  public function buildRuntimeGraph(int $campaign_id, string $dungeon_id, array $base_payload = [], array $options = []): array {
    $dungeon_id = trim($dungeon_id);
    if ($campaign_id <= 0 || $dungeon_id === '') {
      throw new \InvalidArgumentException('Runtime graph assembly requires campaign_id and dungeon_id.');
    }

    $active_room_id = trim((string) ($options['active_room_id'] ?? $base_payload['active_room_id'] ?? ''));
    $requested_room_id = trim((string) ($options['requested_room_id'] ?? ''));
    $scope_depth = max(0, (int) ($options['room_scope_depth'] ?? -1));

    $room_ids = $scope_depth >= 0 ? [] : $this->extractBasePayloadRoomIds($base_payload);
    if ($active_room_id !== '') {
      $room_ids[$active_room_id] = TRUE;
    }
    if ($requested_room_id !== '') {
      $room_ids[$requested_room_id] = TRUE;
    }

    $connections = $this->loadCampaignConnections($campaign_id, $dungeon_id);
    if ($scope_depth >= 0) {
      $connections = $this->filterConnectionsByRoomScope(
        $connections,
        array_values(array_filter([$active_room_id, $requested_room_id], static fn(string $room_id): bool => $room_id !== '')),
        $scope_depth
      );
    }
    foreach ($this->collectConnectionEndpointRoomIds($connections) as $connected_room_id) {
      $room_ids[$connected_room_id] = TRUE;
    }

    $snapshot_rooms = $this->indexSnapshotRooms($base_payload);
    $campaign_rooms = $this->loadCampaignRooms($campaign_id, array_keys($room_ids));
    if ($active_room_id !== '' && !isset($campaign_rooms[$active_room_id])) {
      throw new \RuntimeException(sprintf(
        'Runtime graph assembly contract violation: active room %s is missing from dc_campaign_rooms for campaign %d.',
        $active_room_id,
        $campaign_id
      ));
    }

    $room_names = [];
    foreach ($campaign_rooms as $room_id => $room_row) {
      $room_names[$room_id] = (string) ($room_row['name'] ?? $room_id);
    }
    $canonical_room_names = $this->loadCanonicalRoomNames(array_keys($room_ids));
    $connection_payloads = [];
    foreach ($connections as $connection) {
      $connection_payloads[] = $this->buildConnectionPayload($connection, $room_names, $canonical_room_names);
    }
    $room_exit_payloads = $this->buildRoomExitPayloadsByRoom($connection_payloads, $campaign_rooms, $canonical_room_names);

    $room_payloads = [];
    foreach ($campaign_rooms as $room_id => $room_row) {
      $room_payloads[] = $this->buildRoomPayload(
        $room_row,
        $snapshot_rooms[$room_id] ?? [],
        $room_exit_payloads[$room_id] ?? []
      );
    }

    $hex_map = is_array($base_payload['hex_map'] ?? NULL) ? $base_payload['hex_map'] : [];
    $hex_map['map_id'] = $dungeon_id;
    $hex_map['connections'] = $connection_payloads;

    $payload = $base_payload;
    $payload['schema_version'] = (string) ($base_payload['schema_version'] ?? '1.0.0');
    $payload['dungeon_id'] = $dungeon_id;
    $payload['map_id'] = (string) ($base_payload['map_id'] ?? $dungeon_id);
    $payload['active_room_id'] = $active_room_id;
    $payload['canonical_graph_version'] = $this->graphVersionService->resolveCanonicalGraphVersion($dungeon_id, array_keys($campaign_rooms));
    $payload['campaign_graph_version'] = $this->graphVersionService->resolveCampaignGraphVersion($campaign_id, $dungeon_id, array_keys($campaign_rooms));
    $payload['rooms'] = $room_payloads;
    $payload['connections'] = $connection_payloads;
    $payload['hex_map'] = $hex_map;

    return $payload;
  }

  /**
   * Filter connections to a bounded neighborhood around root room ids.
   *
   * @param array<int, array<string, mixed>> $connections
   *   Raw campaign connector rows.
   * @param array<int, string> $root_room_ids
   *   Starting room ids for scope expansion.
   * @param int $max_depth
   *   Maximum edge depth from root rooms.
   *
   * @return array<int, array<string, mixed>>
   *   Scope-filtered connector rows.
   */
  protected function filterConnectionsByRoomScope(array $connections, array $root_room_ids, int $max_depth): array {
    $root_room_ids = array_values(array_filter(array_map('trim', $root_room_ids), static fn(string $room_id): bool => $room_id !== ''));
    if ($connections === [] || $root_room_ids === [] || $max_depth < 0) {
      return $connections;
    }
    if ($max_depth === 0) {
      return [];
    }

    $adjacency = [];
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
        continue;
      }
      $adjacency[$from_room_id][$to_room_id] = TRUE;
      $adjacency[$to_room_id][$from_room_id] = TRUE;
    }

    $visited = [];
    $queue = [];
    foreach ($root_room_ids as $root_room_id) {
      $visited[$root_room_id] = 0;
      $queue[] = [$root_room_id, 0];
    }

    while ($queue !== []) {
      [$room_id, $depth] = array_shift($queue);
      if ($depth >= $max_depth) {
        continue;
      }
      foreach (array_keys($adjacency[$room_id] ?? []) as $neighbor_room_id) {
        if (isset($visited[$neighbor_room_id])) {
          continue;
        }
        $visited[$neighbor_room_id] = $depth + 1;
        $queue[] = [$neighbor_room_id, $depth + 1];
      }
    }

    return array_values(array_filter($connections, static function ($connection) use ($visited): bool {
      if (!is_array($connection)) {
        return FALSE;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? ''));
      return $from_room_id !== '' && $to_room_id !== ''
        && isset($visited[$from_room_id]) && isset($visited[$to_room_id]);
    }));
  }

  /**
   * @param array<string, mixed> $base_payload
   *
   * @return array<string, bool>
   *   Materialized room ids present in the current snapshot.
   */
  protected function extractBasePayloadRoomIds(array $base_payload): array {
    $room_ids = [];
    foreach ((array) ($base_payload['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    return $room_ids;
  }

  /**
   * @param array<string, mixed> $base_payload
   *
   * @return array<string, array<string, mixed>>
   *   Existing snapshot rooms keyed by room id.
   */
  protected function indexSnapshotRooms(array $base_payload): array {
    $indexed = [];
    foreach ((array) ($base_payload['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        $indexed[$room_id] = $room;
      }
    }
    return $indexed;
  }

  /**
   * Load campaign room rows for the current materialized room scope.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param array<int, string> $room_ids
   *   Requested room ids.
   *
   * @return array<string, array<string, mixed>>
   *   Room rows keyed by room_id.
   */
  protected function loadCampaignRooms(int $campaign_id, array $room_ids): array {
    if ($campaign_id <= 0 || $room_ids === []) {
      return [];
    }

    $rows = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data', 'source_room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', array_values(array_unique($room_ids)), 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $indexed = [];
    foreach ((array) $rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $room_id = trim((string) ($row['room_id'] ?? ''));
      if ($room_id !== '') {
        $indexed[$room_id] = $row;
      }
    }

    return $indexed;
  }

  /**
   * Build one raw room payload from a campaign room row.
   *
   * @param array<string, mixed> $room_row
   *   Room storage row.
   * @param array<string, mixed> $snapshot_room
   *   Existing snapshot room for non-graph compatibility fields.
   *
   * @return array<string, mixed>
   *   Raw room payload ready for HexMapController normalization.
   */
  protected function buildRoomPayload(array $room_row, array $snapshot_room = [], array $exit_payloads = []): array {
    $layout_data = json_decode((string) ($room_row['layout_data'] ?? '{}'), TRUE);
    if (!is_array($layout_data)) {
      $layout_data = [];
    }

    $payload = [
      'room_id' => (string) ($room_row['room_id'] ?? ''),
      'name' => (string) ($room_row['name'] ?? ''),
      'description' => (string) ($room_row['description'] ?? ''),
      'hexes' => is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [],
      'exits' => $exit_payloads,
      'entry_points' => is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [],
      'exit_points' => is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [],
      'terrain' => is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : [],
      'lighting' => $layout_data['lighting'] ?? 'normal',
      'room_type' => (string) ($layout_data['room_type'] ?? 'unknown'),
      'size_category' => (string) ($layout_data['size_category'] ?? 'medium'),
      'gameplay_state' => is_array($snapshot_room['gameplay_state'] ?? NULL) ? $snapshot_room['gameplay_state'] : [],
    ];

    if (isset($snapshot_room['chat']) && is_array($snapshot_room['chat'])) {
      $payload['chat'] = $snapshot_room['chat'];
    }

    return $payload;
  }

  /**
   * Build room-exit payloads from authoritative campaign connector rows.
   *
   * @param array<int, array<string, mixed>> $connection_payloads
   *   Payload-style connections built from campaign connector authority.
   * @param array<string, array<string, mixed>> $campaign_rooms
   *   Campaign room rows keyed by room id.
   * @param array<string, string> $canonical_room_names
   *   Canonical room names keyed by room id.
   *
   * @return array<string, array<int, array<string, mixed>>>
   *   Exit payloads keyed by owning room id.
   */
  protected function buildRoomExitPayloadsByRoom(array $connection_payloads, array $campaign_rooms, array $canonical_room_names): array {
    $authored_exit_metadata = $this->indexAuthoredExitMetadata($campaign_rooms);
    $indexed = [];

    foreach ($connection_payloads as $connection) {
      if (!is_array($connection)) {
        continue;
      }

      $from_room_id = trim((string) ($connection['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }

      $indexed[$from_room_id][] = $this->buildExitPayloadForRoom(
        $connection,
        $from_room_id,
        $to_room_id,
        $canonical_room_names[$to_room_id] ?? $to_room_id,
        $authored_exit_metadata[$from_room_id . '::' . $to_room_id] ?? []
      );

      if (!empty($connection['bidirectional'])) {
        $indexed[$to_room_id][] = $this->buildExitPayloadForRoom(
          $connection,
          $to_room_id,
          $from_room_id,
          $canonical_room_names[$from_room_id] ?? $from_room_id,
          $authored_exit_metadata[$to_room_id . '::' . $from_room_id] ?? [],
          TRUE
        );
      }
    }

    return $indexed;
  }

  /**
   * Index authored exit metadata from existing campaign room rows.
   *
   * @param array<string, array<string, mixed>> $campaign_rooms
   *   Campaign room rows keyed by room id.
   *
   * @return array<string, array<string, mixed>>
   *   Authored exit metadata keyed by source::target room ids.
   */
  protected function indexAuthoredExitMetadata(array $campaign_rooms): array {
    $indexed = [];
    foreach ($campaign_rooms as $room_id => $room_row) {
      if (!is_array($room_row)) {
        continue;
      }
      $layout_data = json_decode((string) ($room_row['layout_data'] ?? '{}'), TRUE);
      if (!is_array($layout_data)) {
        continue;
      }
      foreach ((array) ($layout_data['exits'] ?? []) as $exit) {
        if (!is_array($exit)) {
          continue;
        }
        $target_room_id = trim((string) ($exit['target_room_id'] ?? ''));
        if ($target_room_id === '') {
          continue;
        }
        $indexed[(string) $room_id . '::' . $target_room_id] = $exit;
      }
    }

    return $indexed;
  }

  /**
   * Build one room-owned exit payload from a payload-style connection.
   *
   * @param array<string, mixed> $connection
   *   Payload-style connection.
   * @param string $source_room_id
   *   Owning room id.
   * @param string $target_room_id
   *   Destination room id.
   * @param string $target_room_name
   *   Human-readable destination room name.
   * @param array<string, mixed> $authored_exit
   *   Optional authored exit metadata.
   * @param bool $reverse
   *   TRUE when constructing the reverse side of a bidirectional edge.
   *
   * @return array<string, mixed>
   *   Exit payload.
   */
  protected function buildExitPayloadForRoom(array $connection, string $source_room_id, string $target_room_id, string $target_room_name, array $authored_exit = [], bool $reverse = FALSE): array {
    $origin_hex = !$reverse ? (array) ($connection['from_hex'] ?? []) : (array) ($connection['to_hex'] ?? []);
    $target_hex = !$reverse ? (array) ($connection['to_hex'] ?? []) : (array) ($connection['from_hex'] ?? []);

    return [
      'connection_id' => (string) ($connection['connection_id'] ?? ''),
      'type' => (string) ($connection['type'] ?? 'hallway'),
      'target_room_id' => $target_room_id,
      'target_room_name' => $target_room_name,
      'origin_hex' => [
        'q' => (int) ($origin_hex['q'] ?? 0),
        'r' => (int) ($origin_hex['r'] ?? 0),
      ],
      'target_hex' => [
        'q' => (int) ($target_hex['q'] ?? 0),
        'r' => (int) ($target_hex['r'] ?? 0),
      ],
      'is_passable' => !empty($connection['is_passable']),
      'is_discovered' => !empty($connection['is_discovered']),
      'bidirectional' => !empty($connection['bidirectional']),
      'label' => trim((string) ($authored_exit['label'] ?? '')),
      'link_type' => trim((string) ($authored_exit['link_type'] ?? '')),
    ];
  }

  /**
   * Load runtime campaign connector rows without canonical fallback.
   *
   * @return array<int, array<string, mixed>>
   *   Campaign connector rows.
   */
  protected function loadCampaignConnections(int $campaign_id, string $dungeon_id): array {
    if ($campaign_id <= 0 || $dungeon_id === '') {
      return [];
    }

    return $this->database->select('dc_campaign_connections', 'c')
      ->fields('c', [
        'connection_id',
        'from_room_id',
        'to_room_id',
        'from_hex_q',
        'from_hex_r',
        'to_hex_q',
        'to_hex_r',
        'direction',
        'kind',
        'state',
        'travel_cost',
        'is_discovered',
        'is_passable',
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->orderBy('created', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * @param array<int, array<string, mixed>> $connections
   *
   * @return array<int, string>
   *   Unique room ids referenced by connector endpoints.
   */
  protected function collectConnectionEndpointRoomIds(array $connections): array {
    $room_ids = [];
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      foreach (['from_room_id', 'to_room_id'] as $field) {
        $room_id = trim((string) ($connection[$field] ?? ''));
        if ($room_id !== '') {
          $room_ids[$room_id] = TRUE;
        }
      }
    }
    return array_keys($room_ids);
  }

  /**
   * @param array<int, string> $room_ids
   *
   * @return array<string, string>
   *   Canonical room names keyed by room id.
   */
  protected function loadCanonicalRoomNames(array $room_ids): array {
    if ($room_ids === []) {
      return [];
    }

    $rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'name'])
      ->condition('room_id', $room_ids, 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $names = [];
    foreach ((array) $rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $room_id = trim((string) ($row['room_id'] ?? ''));
      if ($room_id !== '') {
        $names[$room_id] = (string) ($row['name'] ?? $room_id);
      }
    }

    return $names;
  }

  /**
   * Build one payload-style connection from a campaign connector row.
   *
   * @param array<string, mixed> $connection_row
   *   Connector storage row.
   * @param array<string, string> $campaign_room_names
   *   Campaign room names keyed by room id.
   * @param array<string, string> $canonical_room_names
   *   Canonical room names keyed by room id.
   *
   * @return array<string, mixed>
   *   Connection payload.
   */
  protected function buildConnectionPayload(array $connection_row, array $campaign_room_names, array $canonical_room_names): array {
    $from_room_id = trim((string) ($connection_row['from_room_id'] ?? ''));
    $to_room_id = trim((string) ($connection_row['to_room_id'] ?? ''));
    $state = trim((string) ($connection_row['state'] ?? 'open'));

    return [
      'connection_id' => trim((string) ($connection_row['connection_id'] ?? '')),
      'from_room' => $from_room_id,
      'from_room_id' => $from_room_id,
      'to_room' => $to_room_id,
      'to_room_id' => $to_room_id,
      'from_room_name' => $campaign_room_names[$from_room_id] ?? $canonical_room_names[$from_room_id] ?? $from_room_id,
      'to_room_name' => $campaign_room_names[$to_room_id] ?? $canonical_room_names[$to_room_id] ?? $to_room_id,
      'type' => trim((string) ($connection_row['kind'] ?? 'hallway')) ?: 'hallway',
      'kind' => trim((string) ($connection_row['kind'] ?? 'hallway')) ?: 'hallway',
      'state' => $state !== '' ? $state : 'open',
      'bidirectional' => strtolower(trim((string) ($connection_row['direction'] ?? 'bidirectional'))) !== 'one_way',
      'is_discovered' => !empty($connection_row['is_discovered']),
      'is_passable' => !empty($connection_row['is_passable']),
      'travel_cost' => (int) ($connection_row['travel_cost'] ?? 0),
      'destination_type' => 'room',
      'destination_id' => $to_room_id,
      'from_hex' => [
        'q' => (int) ($connection_row['from_hex_q'] ?? 0),
        'r' => (int) ($connection_row['from_hex_r'] ?? 0),
      ],
      'to_hex' => [
        'q' => (int) ($connection_row['to_hex_q'] ?? 0),
        'r' => (int) ($connection_row['to_hex_r'] ?? 0),
      ],
    ];
  }

}
