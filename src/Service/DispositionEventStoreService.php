<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Canonical persistence scaffold for actor disposition event history.
 */
class DispositionEventStoreService {

  protected const MAX_EVENTS = 500;

  protected CampaignStateService $campaignStateService;
  protected ?Connection $database;

  public function __construct(CampaignStateService $campaign_state_service, ?Connection $database = NULL) {
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
  }

  /**
   * Record one disposition event for an actor.
   */
  public function recordDispositionEvent(int $campaign_id, string $entity_ref, array $event): array {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $events = is_array($state['disposition_events'] ?? NULL) ? array_values($state['disposition_events']) : [];

    $idempotency_key = $this->resolveIdempotencyKey($event);
    $record = [
      'event_type' => (string) ($event['event_type'] ?? 'disposition_event'),
      'timestamp' => time(),
      'entity_ref' => $entity_ref,
      'idempotency_key' => $idempotency_key,
      'reason' => (string) ($event['reason'] ?? ''),
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

    $state['disposition_events'] = $events;
    $version = isset($current['version']) ? (int) $current['version'] : NULL;
    $this->campaignStateService->setState($campaign_id, $state, $version);
    $this->persistEventRow($campaign_id, $record);

    return $record;
  }

  /**
   * Determine whether an event idempotency key already exists for actor scope.
   */
  public function hasDispositionEventIdempotencyKey(int $campaign_id, string $entity_ref, string $idempotency_key): bool {
    $idempotency_key = trim($idempotency_key);
    if ($campaign_id <= 0 || trim($entity_ref) === '' || $idempotency_key === '') {
      return FALSE;
    }

    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $events = is_array($state['disposition_events'] ?? NULL) ? array_values($state['disposition_events']) : [];
    for ($i = count($events) - 1; $i >= 0; $i--) {
      $event = is_array($events[$i] ?? NULL) ? $events[$i] : [];
      if ((string) ($event['entity_ref'] ?? '') !== $entity_ref) {
        continue;
      }
      $candidate = $this->resolveIdempotencyKey($event);
      if ($candidate !== '' && $candidate === $idempotency_key) {
        return TRUE;
      }
    }

    if (!$this->database || !$this->database->schema()->tableExists('dc_disposition_events')) {
      return FALSE;
    }

    $needle = '"idempotency_key":"' . addcslashes($idempotency_key, '\\"') . '"';
    $row = $this->database->select('dc_disposition_events', 'e')
      ->fields('e', ['entity_ref'])
      ->condition('campaign_id', $campaign_id)
      ->condition('entity_ref', $entity_ref)
      ->condition('context_json', '%' . $needle . '%', 'LIKE')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $row !== FALSE && $row !== NULL;
  }

  /**
   * Persist one disposition event row to canonical table when available.
   */
  protected function persistEventRow(int $campaign_id, array $record): void {
    if (!$this->database || !$this->database->schema()->tableExists('dc_disposition_events')) {
      return;
    }

    $this->database->insert('dc_disposition_events')
      ->fields([
        'campaign_id' => $campaign_id,
        'entity_ref' => (string) ($record['entity_ref'] ?? ''),
        'event_type' => (string) ($record['event_type'] ?? 'disposition_event'),
        'attitude' => (string) ($record['attitude_after'] ?? ''),
        'summary_json' => json_encode(is_array($record['summary'] ?? NULL) ? $record['summary'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        'context_json' => json_encode([
          'idempotency_key' => trim((string) ($record['idempotency_key'] ?? '')),
          'reason' => (string) ($record['reason'] ?? ''),
          'attitude_before' => (string) ($record['attitude_before'] ?? ''),
          'attitude_after' => (string) ($record['attitude_after'] ?? ''),
          'score_before' => $record['score_before'] ?? NULL,
          'score_after' => $record['score_after'] ?? NULL,
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
    $context_key = trim((string) ($context['idempotency_key'] ?? ''));
    if ($context_key !== '') {
      return $context_key;
    }
    $trigger = is_array($context['trigger'] ?? NULL) ? $context['trigger'] : [];
    return trim((string) ($trigger['idempotency_key'] ?? ''));
  }

}
