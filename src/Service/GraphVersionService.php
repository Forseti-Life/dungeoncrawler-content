<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Resolves lightweight canonical and campaign graph version tokens.
 */
class GraphVersionService {

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * Resolve the canonical library graph version token for a dungeon scope.
   */
  public function resolveCanonicalGraphVersion(string $dungeon_id, array $room_ids = []): string {
    $dungeon_id = trim($dungeon_id);
    if ($dungeon_id === '') {
      return 'canonical:none';
    }

    $room_scope = array_values(array_unique(array_filter(array_map('strval', $room_ids), static fn(string $id): bool => trim($id) !== '')));

    $room_query = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'updated'])
      ->orderBy('room_id', 'ASC');
    if ($room_scope !== []) {
      $room_query->condition('room_id', $room_scope, 'IN');
    }
    $room_rows = $room_query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $connection_query = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c', ['connection_id', 'updated'])
      ->condition('dungeon_id', $dungeon_id)
      ->orderBy('connection_id', 'ASC');
    $connection_rows = $connection_query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return 'canonical:' . sha1(json_encode([
      'dungeon_id' => $dungeon_id,
      'rooms' => array_map(
        static fn(array $row): array => [
          'room_id' => (string) ($row['room_id'] ?? ''),
          'updated' => (int) ($row['updated'] ?? 0),
        ],
        $room_rows
      ),
      'connections' => array_map(
        static fn(array $row): array => [
          'connection_id' => (string) ($row['connection_id'] ?? ''),
          'updated' => (int) ($row['updated'] ?? 0),
        ],
        $connection_rows
      ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Resolve the campaign-instantiated graph version token for a dungeon scope.
   */
  public function resolveCampaignGraphVersion(int $campaign_id, string $dungeon_id, array $room_ids = []): string {
    $dungeon_id = trim($dungeon_id);
    if ($campaign_id <= 0 || $dungeon_id === '') {
      return 'campaign:none';
    }

    $room_scope = array_values(array_unique(array_filter(array_map('strval', $room_ids), static fn(string $id): bool => trim($id) !== '')));

    $room_query = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('room_id', 'ASC');
    if ($room_scope !== []) {
      $room_query->condition('room_id', $room_scope, 'IN');
    }
    $room_rows = $room_query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $connection_rows = $this->database->select('dc_campaign_connections', 'c')
      ->fields('c', ['connection_id', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->orderBy('connection_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return 'campaign:' . sha1(json_encode([
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'rooms' => array_map(
        static fn(array $row): array => [
          'room_id' => (string) ($row['room_id'] ?? ''),
          'updated' => (int) ($row['updated'] ?? 0),
        ],
        $room_rows
      ),
      'connections' => array_map(
        static fn(array $row): array => [
          'connection_id' => (string) ($row['connection_id'] ?? ''),
          'updated' => (int) ($row['updated'] ?? 0),
        ],
        $connection_rows
      ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Build lightweight version metadata for one campaign dungeon.
   *
   * @return array<string, mixed>
   *   Lightweight version metadata for client freshness confirmation.
   */
  public function buildVersionMetadata(int $campaign_id, string $dungeon_id): array {
    $dungeon_id = trim($dungeon_id);
    if ($campaign_id <= 0 || $dungeon_id === '') {
      throw new \InvalidArgumentException('Graph version metadata requires campaign_id and dungeon_id.');
    }

    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $payload = is_array($row)
      ? json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE)
      : [];
    if (!is_array($payload)) {
      $payload = [];
    }

    $room_ids = [];
    foreach ((array) ($payload['rooms'] ?? []) as $room) {
      if (is_array($room)) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id !== '') {
          $room_ids[$room_id] = TRUE;
        }
      }
    }

    $connection_rows = $this->database->select('dc_campaign_connections', 'c')
      ->fields('c', ['from_room_id', 'to_room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    foreach ($connection_rows as $connection_row) {
      if (!is_array($connection_row)) {
        continue;
      }
      foreach (['from_room_id', 'to_room_id'] as $field) {
        $room_id = trim((string) ($connection_row[$field] ?? ''));
        if ($room_id !== '') {
          $room_ids[$room_id] = TRUE;
        }
      }
    }

    $scoped_room_ids = array_keys($room_ids);

    return [
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'active_room_id' => trim((string) ($payload['active_room_id'] ?? '')),
      'canonical_graph_version' => $this->resolveCanonicalGraphVersion($dungeon_id, $scoped_room_ids),
      'campaign_graph_version' => $this->resolveCampaignGraphVersion($campaign_id, $dungeon_id, $scoped_room_ids),
    ];
  }

}
