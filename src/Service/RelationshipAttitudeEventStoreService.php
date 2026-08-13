<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Canonical persistence scaffold for relationship-attitude event history.
 */
class RelationshipAttitudeEventStoreService {

  protected const MAX_EVENTS = 500;

  protected CampaignStateService $campaignStateService;
  protected ?Connection $database;

  public function __construct(CampaignStateService $campaign_state_service, ?Connection $database = NULL) {
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
  }

  /**
   * Record one relationship-attitude mutation event.
   */
  public function recordAttitudeEvent(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_type,
    string $target_id,
    array $event
  ): array {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $events = is_array($state['relationship_attitude_events'] ?? NULL)
      ? array_values($state['relationship_attitude_events'])
      : [];

    $idempotency_key = $this->resolveIdempotencyKey($event);
    $record = [
      'event_type' => (string) ($event['event_type'] ?? 'relationship_attitude_upsert'),
      'timestamp' => time(),
      'source_type' => $source_type,
      'source_id' => $source_id,
      'target_type' => $target_type,
      'target_id' => $target_id,
      'idempotency_key' => $idempotency_key,
      'attitude_before' => (string) ($event['attitude_before'] ?? ''),
      'attitude_after' => (string) ($event['attitude_after'] ?? ''),
      'score_before' => isset($event['score_before']) && is_numeric($event['score_before']) ? DispositionAuthorityContract::clampScore((int) round((float) $event['score_before'])) : NULL,
      'score_after' => isset($event['score_after']) && is_numeric($event['score_after']) ? DispositionAuthorityContract::clampScore((int) round((float) $event['score_after'])) : NULL,
      'summary' => is_array($event['summary'] ?? NULL) ? $event['summary'] : [],
      'context' => is_array($event['context'] ?? NULL) ? $event['context'] : [],
    ];

    $events[] = $record;
    if (count($events) > self::MAX_EVENTS) {
      $events = array_slice($events, count($events) - self::MAX_EVENTS);
    }
    $state['relationship_attitude_events'] = $events;

    $version = isset($current['version']) ? (int) $current['version'] : NULL;
    $this->campaignStateService->setState($campaign_id, $state, $version);
    $this->persistEventRow($campaign_id, $record);

    return $record;
  }

  /**
   * Determine whether an event idempotency key already exists for one edge.
   */
  public function hasRelationshipAttitudeEventIdempotencyKey(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_type,
    string $target_id,
    string $idempotency_key
  ): bool {
    $idempotency_key = trim($idempotency_key);
    if (
      $campaign_id <= 0
      || trim($source_type) === ''
      || trim($source_id) === ''
      || trim($target_type) === ''
      || trim($target_id) === ''
      || $idempotency_key === ''
    ) {
      return FALSE;
    }

    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $events = is_array($state['relationship_attitude_events'] ?? NULL)
      ? array_values($state['relationship_attitude_events'])
      : [];
    for ($i = count($events) - 1; $i >= 0; $i--) {
      $event = is_array($events[$i] ?? NULL) ? $events[$i] : [];
      if (
        (string) ($event['source_type'] ?? '') !== $source_type
        || (string) ($event['source_id'] ?? '') !== $source_id
        || (string) ($event['target_type'] ?? '') !== $target_type
        || (string) ($event['target_id'] ?? '') !== $target_id
      ) {
        continue;
      }
      $candidate = $this->resolveIdempotencyKey($event);
      if ($candidate !== '' && $candidate === $idempotency_key) {
        return TRUE;
      }
    }

    if (!$this->database || !$this->database->schema()->tableExists('dc_relationship_attitude_events')) {
      return FALSE;
    }

    $needle = '"idempotency_key":"' . addcslashes($idempotency_key, '\\"') . '"';
    $row = $this->database->select('dc_relationship_attitude_events', 'e')
      ->fields('e', ['source_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('source_type', $source_type)
      ->condition('source_id', $source_id)
      ->condition('target_type', $target_type)
      ->condition('target_id', $target_id)
      ->condition('context_json', '%' . $needle . '%', 'LIKE')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $row !== FALSE && $row !== NULL;
  }

  /**
   * Persist one relationship-attitude event row to canonical table when available.
   */
  protected function persistEventRow(int $campaign_id, array $record): void {
    if (!$this->database || !$this->database->schema()->tableExists('dc_relationship_attitude_events')) {
      return;
    }

    $this->database->insert('dc_relationship_attitude_events')
      ->fields([
        'campaign_id' => $campaign_id,
        'source_type' => (string) ($record['source_type'] ?? ''),
        'source_id' => (string) ($record['source_id'] ?? ''),
        'target_type' => (string) ($record['target_type'] ?? ''),
        'target_id' => (string) ($record['target_id'] ?? ''),
        'event_type' => (string) ($record['event_type'] ?? 'relationship_attitude_upsert'),
        'attitude' => (string) ($record['attitude_after'] ?? ''),
        'context_json' => json_encode([
          'idempotency_key' => trim((string) ($record['idempotency_key'] ?? '')),
          'attitude_before' => (string) ($record['attitude_before'] ?? ''),
          'attitude_after' => (string) ($record['attitude_after'] ?? ''),
          'score_before' => $record['score_before'] ?? NULL,
          'score_after' => $record['score_after'] ?? NULL,
          'summary' => is_array($record['summary'] ?? NULL) ? $record['summary'] : [],
          'context' => is_array($record['context'] ?? NULL) ? $record['context'] : [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        'created' => (int) ($record['timestamp'] ?? time()),
      ])
      ->execute();
  }

  /**
   * Resolve idempotency key from event record/context payload when present.
   */
  protected function resolveIdempotencyKey(array $event): string {
    $direct = trim((string) ($event['idempotency_key'] ?? ''));
    if ($direct !== '') {
      return $direct;
    }
    $context = is_array($event['context'] ?? NULL) ? $event['context'] : [];
    return trim((string) ($context['idempotency_key'] ?? ''));
  }

}
