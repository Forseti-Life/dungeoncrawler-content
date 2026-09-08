<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;

/**
 * Projects published dungeon port links into canonical connector rows.
 */
final class ConnectorProjectionService {

  private const ID_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,99}$/';

  public function __construct(private readonly Connection $database) {}

  /**
   * @return array<int, array<string, mixed>>
   *   Rows ready for dungeoncrawler_content_connections insertion.
   */
  public function project(string $dungeon_id, array $aggregate): array {
    $dungeon_id = trim($dungeon_id);
    if ($dungeon_id === '' || !preg_match(self::ID_PATTERN, $dungeon_id)) {
      throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('dungeon_id_invalid', 'Projection requires a locked canonical dungeon_id.', [])]);
    }
    if (in_array($dungeon_id, ['runtime', 'campaign', 'campaign_runtime', 'generated'], TRUE)) {
      throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('dungeon_id_reserved', sprintf('Projection scope %s is reserved for runtime data.', $dungeon_id), [])]);
    }
    if ((string) ($aggregate['dungeon_id'] ?? '') !== $dungeon_id) {
      throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('dungeon_id_scope_mismatch', 'Aggregate dungeon_id must match the locked draft identity.', [['dungeon_id' => (string) ($aggregate['dungeon_id'] ?? '')]])]);
    }
    $this->assertConnectionSchema();

    $placements = [];
    $ports = [];
    foreach ($aggregate['room_placements'] ?? [] as $placement) {
      $placement_id = (string) ($placement['placement_id'] ?? '');
      $room = $this->roomVersion((string) ($placement['version_id'] ?? ''));
      if ($placement_id === '' || $room === NULL) {
        throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('placement_version_unresolved', sprintf('Placement %s has no resolvable published room version.', $placement_id !== '' ? $placement_id : 'unknown'), [['placement_id' => $placement_id]])]);
      }
      $placements[$placement_id] = $placement;
      foreach ($this->roomPorts($room) as $port) {
        $level = RoomPlacementTransformer::toLevelPort(['q' => $port['q'], 'r' => $port['r']], $port['edge'], $placement);
        $ports[$placement_id . ':' . $port['port_id']] = [
          'placement_id' => $placement_id,
          'room_id' => (string) $placement['room_id'],
          'version_id' => (string) $placement['version_id'],
          'port_id' => (string) $port['port_id'],
          'kind' => (string) $port['kind'],
          'local_q' => (int) $port['q'],
          'local_r' => (int) $port['r'],
          'edge' => $level['edge'],
          'q' => $level['q'],
          'r' => $level['r'],
          'h3_index_res14' => $this->lookupRoomHexH3IndexRes14((string) $placement['room_id'], (int) $port['q'], (int) $port['r']),
        ];
      }
    }

    $rows = [];
    $seen_ids = [];
    foreach ($aggregate['port_links'] ?? [] as $link) {
      $link_id = (string) ($link['link_id'] ?? '');
      $from_key = (string) ($link['from']['placement_id'] ?? '') . ':' . (string) ($link['from']['port_id'] ?? '');
      $to_key = (string) ($link['to']['placement_id'] ?? '') . ':' . (string) ($link['to']['port_id'] ?? '');
      if ($link_id === '' || !isset($ports[$from_key], $ports[$to_key])) {
        throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('port_link_endpoint_missing', sprintf('Link %s has an unresolved endpoint.', $link_id !== '' ? $link_id : 'unknown'), [['link_id' => $link_id]])]);
      }
      if (isset($seen_ids[$link_id])) {
        throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('connection_id_duplicate_in_dungeon', sprintf('Duplicate link_id %s cannot project to unique connection rows.', $link_id), [['link_id' => $link_id]])]);
      }
      $seen_ids[$link_id] = TRUE;

      $from = $ports[$from_key];
      $to = $ports[$to_key];
      if ($from['kind'] !== 'exit' || $to['kind'] !== 'entry') {
        throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('port_link_direction_invalid', sprintf('Link %s must project from exit to entry.', $link_id), [['link_id' => $link_id]])]);
      }
      if ($from['h3_index_res14'] === '' || $to['h3_index_res14'] === '') {
        throw new DungeonCommandRejectedException('connector_projection_failed', [$this->finding('connector_h3_endpoint_missing', sprintf('Link %s endpoint H3 indexes are unavailable.', $link_id), [['link_id' => $link_id]])]);
      }

      $conflict = $this->database->select('dungeoncrawler_content_connections', 'c')
        ->fields('c', ['dungeon_id'])
        ->condition('connection_id', $link_id)
        ->condition('dungeon_id', $dungeon_id, '<>')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($conflict !== FALSE && $conflict !== NULL) {
        throw new \RuntimeException('connector_identity_conflict');
      }

      $rows[] = [
        'connection_id' => $link_id,
        'dungeon_id' => $dungeon_id,
        'from_room_id' => $from['room_id'],
        'to_room_id' => $to['room_id'],
        'from_hex_q' => $from['q'],
        'from_hex_r' => $from['r'],
        'to_hex_q' => $to['q'],
        'to_hex_r' => $to['r'],
        'from_h3_index_res14' => $from['h3_index_res14'],
        'to_h3_index_res14' => $to['h3_index_res14'],
        'direction' => (string) ($link['direction'] ?? 'bidirectional'),
        'kind' => (string) ($link['kind'] ?? 'hallway'),
        'default_state' => (string) ($link['default_state'] ?? 'open'),
        'trap_data' => isset($link['trap_data']) ? $this->encode($link['trap_data']) : NULL,
        'lock_data' => isset($link['lock_data']) ? $this->encode($link['lock_data']) : NULL,
        'requirements_data' => isset($link['requirements']) ? $this->encode($link['requirements']) : NULL,
        'description' => isset($link['description']) ? (string) $link['description'] : NULL,
        'travel_cost' => max(0, (int) ($link['travel_cost'] ?? 0)),
        'is_discovered_default' => 1,
      ];
    }

    return $rows;
  }

  private function assertConnectionSchema(): void {
    $schema = $this->database->schema();
    $table = 'dungeoncrawler_content_connections';
    if (!$schema->tableExists($table)) {
      throw new \RuntimeException('connector_projection_schema_missing:' . $table);
    }
    foreach (['connection_id', 'dungeon_id', 'from_room_id', 'to_room_id', 'from_hex_q', 'from_hex_r', 'to_hex_q', 'to_hex_r', 'from_h3_index_res14', 'to_h3_index_res14'] as $field) {
      if (!$schema->fieldExists($table, $field)) {
        throw new \RuntimeException('connector_projection_schema_missing:' . $field);
      }
    }
  }

  private function roomVersion(string $version_id): ?array {
    $payload = $this->database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['room_payload'])
      ->condition('version_id', $version_id)
      ->execute()
      ->fetchField();
    return $payload ? json_decode((string) $payload, TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
  }

  private function lookupRoomHexH3IndexRes14(string $room_id, int $q, int $r): string {
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
    return is_array($row) ? strtolower(trim((string) ($row['h3_index'] ?? ''))) : '';
  }

  private function roomPorts(array $room): array {
    $ports = [];
    foreach (['entry' => 'entry_ports', 'exit' => 'exit_ports'] as $kind => $key) {
      foreach ($room[$key] ?? [] as $port) {
        $ports[] = [
          'port_id' => (string) $port['port_id'],
          'kind' => $kind,
          'q' => (int) $port['hex']['q'],
          'r' => (int) $port['hex']['r'],
          'edge' => (int) $port['edge'],
        ];
      }
    }
    return $ports;
  }

  private function finding(string $code, string $message, array $subjects): array {
    return [
      'severity' => 'error',
      'code' => $code,
      'message' => $message,
      'subjects' => $subjects,
    ];
  }

  private function encode(array $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}
