<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Canonical persistence scaffold for latest actor disposition state.
 */
class DispositionStateStoreService {

  protected CampaignStateService $campaignStateService;
  protected ?Connection $database;

  public function __construct(CampaignStateService $campaign_state_service, ?Connection $database = NULL) {
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
  }

  /**
   * Persist latest disposition summary for one actor.
   */
  public function storeLatestState(int $campaign_id, string $entity_ref, array $summary, array $meta = []): array {
    $summary = $this->normalizeSummary($summary);
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $registry = is_array($state['disposition_state'] ?? NULL) ? $state['disposition_state'] : [];

    $key = trim($entity_ref) !== '' ? trim($entity_ref) : '_unknown_actor';
    $snapshot = [
      'entity_ref' => $entity_ref,
      'updated_at' => time(),
      'summary' => $summary,
      'meta' => $meta,
    ];
    $registry[$key] = $snapshot;
    $state['disposition_state'] = $registry;

    $version = isset($current['version']) ? (int) $current['version'] : NULL;
    $this->campaignStateService->setState($campaign_id, $state, $version);
    $this->persistStateRow($campaign_id, $snapshot);

    return $snapshot;
  }

  /**
   * Load latest persisted disposition summary for one actor when available.
   */
  public function loadLatestState(int $campaign_id, string $entity_ref): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $candidates = $this->buildEntityRefCandidates($entity_ref);
    if ($candidates === []) {
      return NULL;
    }

    if ($this->database && $this->database->schema()->tableExists('dc_disposition_state')) {
      $rows = $this->database->select('dc_disposition_state', 's')
        ->fields('s', ['entity_ref', 'summary_json', 'meta_json', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('entity_ref', $candidates, 'IN')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      $by_entity_ref = [];
      foreach ((array) $rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $by_entity_ref[trim((string) ($row['entity_ref'] ?? ''))] = $row;
      }
      foreach ($candidates as $candidate) {
        $row = $by_entity_ref[$candidate] ?? NULL;
        if (!is_array($row)) {
          continue;
        }
        return [
          'entity_ref' => (string) ($row['entity_ref'] ?? $candidate),
          'updated_at' => (int) ($row['updated'] ?? 0),
          'summary' => $this->decodeJsonObject($row['summary_json'] ?? '', []),
          'meta' => $this->decodeJsonObject($row['meta_json'] ?? '', []),
        ];
      }
    }

    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $registry = is_array($state['disposition_state'] ?? NULL) ? $state['disposition_state'] : [];
    foreach ($candidates as $candidate) {
      $entry = $registry[$candidate] ?? NULL;
      if (!is_array($entry)) {
        continue;
      }
      return [
        'entity_ref' => (string) ($entry['entity_ref'] ?? $candidate),
        'updated_at' => (int) ($entry['updated_at'] ?? 0),
        'summary' => is_array($entry['summary'] ?? NULL) ? $entry['summary'] : [],
        'meta' => is_array($entry['meta'] ?? NULL) ? $entry['meta'] : [],
      ];
    }

    return NULL;
  }

  /**
   * Persist latest disposition snapshot to canonical table when available.
   */
  protected function persistStateRow(int $campaign_id, array $snapshot): void {
    if (!$this->database || !$this->database->schema()->tableExists('dc_disposition_state')) {
      return;
    }

    $this->database->merge('dc_disposition_state')
      ->keys([
        'campaign_id' => $campaign_id,
        'entity_ref' => (string) ($snapshot['entity_ref'] ?? ''),
      ])
      ->fields([
        'summary_json' => json_encode(is_array($snapshot['summary'] ?? NULL) ? $snapshot['summary'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        'meta_json' => json_encode(is_array($snapshot['meta'] ?? NULL) ? $snapshot['meta'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        'updated' => (int) ($snapshot['updated_at'] ?? time()),
      ])
      ->execute();
  }

  /**
   * Decode JSON payload with array fallback.
   *
   * @param mixed $raw
   *   Raw JSON payload.
   * @param array<string, mixed> $fallback
   *   Fallback when decode fails.
   *
   * @return array<string, mixed>
   *   Decoded object payload.
   */
  protected function decodeJsonObject(mixed $raw, array $fallback = []): array {
    if (is_array($raw)) {
      return $raw;
    }
    if (!is_string($raw) || trim($raw) === '') {
      return $fallback;
    }
    $decoded = json_decode($raw, TRUE);
    return is_array($decoded) ? $decoded : $fallback;
  }

  /**
   * Build canonical entity-ref candidate keys for disposition state lookup.
   *
   * @return array<int, string>
   *   Candidate keys in lookup order.
   */
  protected function buildEntityRefCandidates(string $entity_ref): array {
    $entity_ref = trim($entity_ref);
    if ($entity_ref === '') {
      return [];
    }

    $candidates = [$entity_ref];
    $colon_pos = strpos($entity_ref, ':');
    if ($colon_pos !== FALSE && $colon_pos < strlen($entity_ref) - 1) {
      $candidates[] = substr($entity_ref, $colon_pos + 1);
    }
    foreach (array_values($candidates) as $candidate) {
      if (!str_starts_with($candidate, 'npc_')) {
        $candidates[] = 'npc_' . $candidate;
      }
      else {
        $unprefixed = substr($candidate, 4);
        if ($unprefixed !== '') {
          $candidates[] = $unprefixed;
        }
      }
    }

    return array_values(array_unique(array_filter(array_map(
      static fn($value): string => trim((string) $value),
      $candidates
    ), static fn(string $value): bool => $value !== '')));
  }

  /**
   * Normalize summary payload into canonical disposition keys.
   *
   * @param array<string,mixed> $summary
   *   Raw summary payload.
   *
   * @return array<string,mixed>
   *   Normalized summary payload.
   */
  protected function normalizeSummary(array $summary): array {
    $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($summary['current_attitude'] ?? ''));
    if ($attitude === '') {
      $attitude = DispositionAuthorityContract::LABEL_INDIFFERENT;
    }
    $score = isset($summary['current_score']) && is_numeric($summary['current_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $summary['current_score']))
      : (DispositionAuthorityContract::attitudeToScore($attitude) ?? 0);

    $summary['current_attitude'] = $attitude;
    $summary['current_score'] = $score;
    $summary['score_source'] = isset($summary['score_source']) && trim((string) $summary['score_source']) !== ''
      ? (string) $summary['score_source']
      : 'attitude_projection';

    return $summary;
  }

}
