<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages dungeon state snapshots with optimistic versioning backed by DB.
 */
class DungeonStateService {

  private Connection $database;
  private LoggerInterface $logger;

  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
  }

  /**
   * Retrieve current dungeon state from dc_campaign_dungeons.
   *
   * @param string $dungeon_id
   *   Dungeon/map identifier.
   * @param int|null $campaign_id
   *   Optional campaign scoping.
   */
  public function getState(string $dungeon_id, int $campaign_id): array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data', 'campaign_id', 'dungeon_id', 'created', 'updated', 'name', 'description', 'theme'])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Dungeon not found', 404);
    }

    $decoded = json_decode($record['dungeon_data'] ?? '', TRUE);
    if (!is_array($decoded)) {
      $decoded = [];
    }

    $state = $decoded['state'] ?? ($decoded['dungeon_state'] ?? []);
    $meta = $decoded['state_meta'] ?? [];
    $version = (int) ($meta['version'] ?? $record['updated'] ?? $record['created'] ?? 0);
    $updated_at = $meta['updatedAt'] ?? ($record['updated'] ? date('c', (int) $record['updated']) : date('c'));

    return [
      'dungeonId' => (string) ($decoded['dungeon_id'] ?? $record['dungeon_id'] ?? $dungeon_id),
      'campaignId' => (int) $campaign_id,
      'name' => $record['name'] ?? ($decoded['name'] ?? ''),
      'description' => $record['description'] ?? ($decoded['description'] ?? ''),
      'theme' => $record['theme'] ?? ($decoded['theme'] ?? NULL),
      'state' => $state,
      'version' => $version,
      'updatedAt' => $updated_at,
    ];
  }

  /**
   * Replace dungeon state with optimistic locking using dc_campaign_dungeons.
   */
  public function setState(string $dungeon_id, array $state, ?int $expected_version, int $campaign_id): array {
    $current = NULL;
    try {
      $current = $this->getState($dungeon_id, $campaign_id);
    }
    catch (\InvalidArgumentException $e) {
      // Missing record is allowed for first save; conflicts still bubble.
    }

    $current_version = (int) ($current['version'] ?? 0);

    if ($expected_version !== NULL && $expected_version !== $current_version) {
      $this->logger->warning('Dungeon state version conflict for {id}: expected {expected} got {current}', [
        'id' => $dungeon_id,
        'expected' => $expected_version,
        'current' => $current_version,
      ]);
      throw new \InvalidArgumentException('Version conflict', 409);
    }

    $query = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data', 'campaign_id', 'name', 'description', 'theme'])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1);

    $record = $query->execute()->fetchAssoc();

    $now = time();
    $dungeon_data = [];
    if ($record && !empty($record['dungeon_data'])) {
      $decoded = json_decode($record['dungeon_data'], TRUE);
      if (is_array($decoded)) {
        $dungeon_data = $decoded;
      }
    }

    $next_version = $current_version + 1;
    $meta = [
      'version' => $next_version,
      'updatedAt' => date('c'),
    ];

    $dungeon_data['state'] = $state;
    $dungeon_data['state_meta'] = $meta;
    $dungeon_data['dungeon_id'] = $dungeon_data['dungeon_id'] ?? $dungeon_id;
    $dungeon_data['campaign_id'] = $campaign_id;

    $name = $record['name'] ?? ($dungeon_data['name'] ?? ('Dungeon ' . $dungeon_id));
    $description = $record['description'] ?? ($dungeon_data['description'] ?? NULL);
    $theme = $record['theme'] ?? ($dungeon_data['theme'] ?? NULL);

    if ($record) {
      $this->database->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'name' => $name,
          'description' => $description,
          'theme' => $theme,
          'updated' => $now,
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    else {
      $this->database->insert('dc_campaign_dungeons')
        ->fields([
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
          'name' => $name,
          'description' => $description,
          'theme' => $theme,
          'dungeon_data' => json_encode($dungeon_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }

    return [
      'dungeonId' => (string) $dungeon_id,
      'campaignId' => $campaign_id,
      'state' => $state,
      'version' => $next_version,
      'updatedAt' => date('c', $now),
    ];
  }

}
