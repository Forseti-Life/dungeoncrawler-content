<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Persistence scaffold for actor process-flow transition history.
 */
class ProcessFlowEventStoreService {

  protected const MAX_EVENTS = 500;

  protected CampaignStateService $campaignStateService;
  protected ?Connection $database;

  public function __construct(CampaignStateService $campaign_state_service, ?Connection $database = NULL) {
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
  }

  /**
   * Record one process-flow transition event.
   *
   * @param array<string, mixed> $event
   *   Event payload.
   */
  public function recordProcessFlowEvent(int $campaign_id, string $entity_ref, array $event): array {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $events = is_array($state['process_flow_events'] ?? NULL) ? array_values($state['process_flow_events']) : [];

    $record = [
      'event_type' => (string) ($event['event_type'] ?? 'process_flow_selected'),
      'timestamp' => time(),
      'entity_ref' => $entity_ref,
      'flow_id' => (string) ($event['flow_id'] ?? ''),
      'summary' => is_array($event['summary'] ?? NULL) ? $event['summary'] : [],
      'context' => is_array($event['context'] ?? NULL) ? $event['context'] : [],
    ];

    $events[] = $record;
    if (count($events) > self::MAX_EVENTS) {
      $events = array_slice($events, count($events) - self::MAX_EVENTS);
    }

    $state['process_flow_events'] = $events;
    $version = isset($current['version']) ? (int) $current['version'] : NULL;
    $this->campaignStateService->setState($campaign_id, $state, $version);
    $this->persistEventRow($campaign_id, $record);

    return $record;
  }

  /**
   * Persist one process-flow event row when canonical table is available.
   *
   * @param array<string, mixed> $record
   *   Event record.
   */
  protected function persistEventRow(int $campaign_id, array $record): void {
    if (!$this->database || !$this->database->schema()->tableExists('dc_process_flow_events')) {
      return;
    }

    $this->database->insert('dc_process_flow_events')
      ->fields([
        'campaign_id' => $campaign_id,
        'entity_ref' => (string) ($record['entity_ref'] ?? ''),
        'event_type' => (string) ($record['event_type'] ?? 'process_flow_selected'),
        'flow_id' => (string) ($record['flow_id'] ?? ''),
        'summary_json' => json_encode(is_array($record['summary'] ?? NULL) ? $record['summary'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        'context_json' => json_encode(is_array($record['context'] ?? NULL) ? $record['context'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        'created' => (int) ($record['timestamp'] ?? time()),
      ])
      ->execute();
  }

}
