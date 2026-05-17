<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages campaign-level state snapshots with optimistic versioning in DB.
 */
class CampaignStateService {

  private Connection $database;
  private LoggerInterface $logger;
  private CampaignClockService $campaignClockService;

  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory, CampaignClockService $campaign_clock_service) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->campaignClockService = $campaign_clock_service;
  }

  /**
   * Retrieve current campaign state from dc_campaigns.
   */
  public function getState(int $campaign_id): array {
    $record = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['campaign_data', 'changed', 'created'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Campaign not found', 404);
    }

    $campaign_data = json_decode($record['campaign_data'] ?? '', TRUE);
    if (!is_array($campaign_data)) {
      $campaign_data = [];
    }

    $state = $campaign_data['state'] ?? [];
    if (!is_array($state)) {
      $state = [];
    }

    $fallback_timestamp = isset($record['created']) ? (int) $record['created'] : NULL;
    $this->campaignClockService->ensureClock($state, $fallback_timestamp);
    $this->campaignClockService->syncLegacyGameTime($state);
    $state['updated_at'] = $state['updated_at'] ?? ($record['changed'] ? gmdate('c', (int) $record['changed']) : gmdate('c'));
    $state['created_at'] = $state['created_at'] ?? ($record['created'] ? gmdate('c', (int) $record['created']) : gmdate('c'));

    $meta = $campaign_data['state_meta'] ?? [];
    $version = (int) ($meta['version'] ?? $record['changed'] ?? $record['created'] ?? 0);
    $updated_at = $meta['updatedAt'] ?? ($record['changed'] ? date('c', (int) $record['changed']) : date('c'));

    return [
      'campaignId' => (string) $campaign_id,
      'state' => $state,
      'version' => $version,
      'updatedAt' => $updated_at,
    ];
  }

  /**
   * Replace campaign state with optimistic locking.
   */
  public function setState(int $campaign_id, array $state, ?int $expected_version = NULL): array {
    $current = $this->getState($campaign_id);
    $current_version = (int) ($current['version'] ?? 0);

    if ($expected_version !== NULL && $expected_version !== $current_version) {
      $this->logger->warning('Campaign state version conflict for {id}: expected {expected} got {current}', [
        'id' => $campaign_id,
        'expected' => $expected_version,
        'current' => $current_version,
      ]);
      throw new \InvalidArgumentException('Version conflict', 409);
    }

    $record = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['campaign_data', 'created'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchAssoc();

    $campaign_data = json_decode($record['campaign_data'] ?? '', TRUE);
    if (!is_array($campaign_data)) {
      $campaign_data = [];
    }

    $existing_state = $campaign_data['state'] ?? [];
    if (!is_array($existing_state)) {
      $existing_state = [];
    }
    $fallback_timestamp = isset($record['created']) ? (int) $record['created'] : NULL;
    $existing_clock = $this->campaignClockService->ensureClock($existing_state, $fallback_timestamp);
    if (!isset($state[CampaignClockService::STATE_KEY]) && !isset($state['game_time'])) {
      $state[CampaignClockService::STATE_KEY] = $existing_clock;
    }
    $this->campaignClockService->ensureClock($state, $fallback_timestamp);
    $this->campaignClockService->syncLegacyGameTime($state);
    $state['created_at'] = $state['created_at'] ?? ($fallback_timestamp ? gmdate('c', $fallback_timestamp) : gmdate('c'));
    $state['updated_at'] = gmdate('c');

    $next_version = $current_version + 1;
    $campaign_data['state'] = $state;
    $campaign_data['state_meta'] = [
      'version' => $next_version,
      'updatedAt' => date('c'),
    ];

    $now = time();
    $this->database->update('dc_campaigns')
      ->fields([
        'campaign_data' => json_encode($campaign_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    return [
      'campaignId' => (string) $campaign_id,
      'state' => $state,
      'version' => $next_version,
      'updatedAt' => date('c', $now),
    ];
  }

}
