<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Canonical persistence scaffold for latest relationship-attitude state.
 */
class RelationshipAttitudeStateStoreService {

  protected CampaignStateService $campaignStateService;
  protected ?Connection $database;

  public function __construct(CampaignStateService $campaign_state_service, ?Connection $database = NULL) {
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
  }

  /**
   * Persist latest relationship attitude snapshot for one edge.
   */
  public function storeLatestState(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_type,
    string $target_id,
    string $attitude,
    array $meta = []
  ): array {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $registry = is_array($state['relationship_attitude_state'] ?? NULL)
      ? $state['relationship_attitude_state']
      : [];

    $key = $this->composeEdgeKey($source_type, $source_id, $target_type, $target_id);
    $normalized_attitude = DispositionAuthorityContract::normalizeAttitudeLabel($attitude);
    $meta_score = isset($meta['score']) && is_numeric($meta['score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $meta['score']))
      : NULL;
    $resolved_score = $meta_score ?? DispositionAuthorityContract::attitudeToScore($normalized_attitude);
    $score_source = $meta_score !== NULL
      ? 'relationship_state_score'
      : ($normalized_attitude !== '' ? 'attitude_bucket' : 'none');

    $snapshot = [
      'source_type' => $source_type,
      'source_id' => $source_id,
      'target_type' => $target_type,
      'target_id' => $target_id,
      'attitude' => $normalized_attitude,
      'score' => $resolved_score,
      'score_source' => $score_source,
      'updated_at' => time(),
      'meta' => $meta,
    ];
    $registry[$key] = $snapshot;
    $state['relationship_attitude_state'] = $registry;

    $version = isset($current['version']) ? (int) $current['version'] : NULL;
    $this->campaignStateService->setState($campaign_id, $state, $version);
    $this->persistStateRow($campaign_id, $key, $snapshot);

    return $snapshot;
  }

  /**
   * Read latest edge snapshot if present.
   */
  public function getEdgeState(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_type,
    string $target_id
  ): ?array {
    $state_row = $this->campaignStateService->getState($campaign_id);
    $state = is_array($state_row['state'] ?? NULL) ? $state_row['state'] : [];
    $registry = is_array($state['relationship_attitude_state'] ?? NULL)
      ? $state['relationship_attitude_state']
      : [];
    $key = $this->composeEdgeKey($source_type, $source_id, $target_type, $target_id);
    $entry = $registry[$key] ?? NULL;
    return is_array($entry) ? $entry : NULL;
  }

  /**
   * Resolve strongest stored attitude across source/target candidates.
   */
  public function findStrongestAttitude(int $campaign_id, array $source_candidates, array $target_candidates): string {
    $best = $this->findStrongestDisposition($campaign_id, $source_candidates, $target_candidates);
    return (string) ($best['attitude'] ?? '');
  }

  /**
   * Resolve strongest stored edge disposition across source/target candidates.
   *
   * @return array<string,mixed>|null
   *   Keys: attitude, score, score_source, updated_at.
   */
  public function findStrongestDisposition(int $campaign_id, array $source_candidates, array $target_candidates): ?array {
    $state_row = $this->campaignStateService->getState($campaign_id);
    $state = is_array($state_row['state'] ?? NULL) ? $state_row['state'] : [];
    $registry = is_array($state['relationship_attitude_state'] ?? NULL)
      ? $state['relationship_attitude_state']
      : [];
    if ($registry === []) {
      return NULL;
    }

    $source_lookup = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $source_candidates), static fn($value): bool => $value !== '')));
    $target_lookup = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $target_candidates), static fn($value): bool => $value !== '')));
    if ($source_lookup === [] || $target_lookup === []) {
      return NULL;
    }

    $best = NULL;
    foreach ($registry as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $source_id = trim((string) ($entry['source_id'] ?? ''));
      $target_id = trim((string) ($entry['target_id'] ?? ''));
      if (!in_array($source_id, $source_lookup, TRUE) || !in_array($target_id, $target_lookup, TRUE)) {
        continue;
      }
      $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($entry['attitude'] ?? ''));
      $score = isset($entry['score']) && is_numeric($entry['score'])
        ? DispositionAuthorityContract::clampScore((int) round((float) $entry['score']))
        : NULL;
      if ($score === NULL && $attitude !== '') {
        $score = DispositionAuthorityContract::attitudeToScore($attitude);
      }
      $score_source = strtolower(trim((string) ($entry['score_source'] ?? ($score !== NULL ? 'attitude_bucket' : 'none'))));
      $meta = is_array($entry['meta'] ?? NULL) ? $entry['meta'] : [];
      $candidate = [
        'attitude' => $attitude,
        'score' => $score,
        'score_source' => $score_source !== '' ? $score_source : 'none',
        'updated_at' => (int) ($entry['updated_at'] ?? 0),
        'relationship_type' => (string) ($meta['relationship_type'] ?? ''),
        'status' => (string) ($meta['status'] ?? ''),
      ];
      if ($best === NULL) {
        $best = $candidate;
        continue;
      }
      $candidate_priority = $this->scoreSourcePriority((string) $candidate['score_source']);
      $best_priority = $this->scoreSourcePriority((string) ($best['score_source'] ?? 'none'));
      if ($candidate_priority > $best_priority) {
        $best = $candidate;
        continue;
      }
      $candidate_magnitude = $candidate['score'] !== NULL ? abs((int) $candidate['score']) : 0;
      $best_magnitude = isset($best['score']) && $best['score'] !== NULL ? abs((int) $best['score']) : 0;
      if ($candidate_priority === $best_priority && $candidate_magnitude > $best_magnitude) {
        $best = $candidate;
        continue;
      }
      if (
        $candidate_priority === $best_priority
        && $candidate_magnitude === $best_magnitude
        && (int) $candidate['updated_at'] > (int) ($best['updated_at'] ?? 0)
      ) {
        $best = $candidate;
      }
    }

    return is_array($best) ? $best : NULL;
  }

  protected function composeEdgeKey(string $source_type, string $source_id, string $target_type, string $target_id): string {
    return strtolower(trim($source_type)) . ':' . trim($source_id) . '->' . strtolower(trim($target_type)) . ':' . trim($target_id);
  }

  protected function pickStrongestAttitude(array $matches): string {
    if ($matches === []) {
      return '';
    }
    $ranked = ['hostile', 'unfriendly', 'indifferent', 'friendly', 'helpful'];
    foreach ($ranked as $candidate) {
      if (in_array($candidate, $matches, TRUE)) {
        return $candidate;
      }
    }
    return '';
  }

  /**
   * Rank score sources for deterministic precedence.
   */
  protected function scoreSourcePriority(string $score_source): int {
    return match (strtolower(trim($score_source))) {
      'relationship_state_score' => 3,
      'attitude_bucket' => 2,
      default => 1,
    };
  }

  /**
   * Persist latest relationship-attitude snapshot to canonical table when available.
   */
  protected function persistStateRow(int $campaign_id, string $edge_key, array $snapshot): void {
    if (!$this->database || !$this->database->schema()->tableExists('dc_relationship_attitude_state')) {
      return;
    }

    $this->database->merge('dc_relationship_attitude_state')
      ->keys([
        'campaign_id' => $campaign_id,
        'edge_key' => $edge_key,
      ])
      ->fields([
        'source_type' => (string) ($snapshot['source_type'] ?? ''),
        'source_id' => (string) ($snapshot['source_id'] ?? ''),
        'target_type' => (string) ($snapshot['target_type'] ?? ''),
        'target_id' => (string) ($snapshot['target_id'] ?? ''),
        'attitude' => (string) ($snapshot['attitude'] ?? ''),
        'meta_json' => json_encode(is_array($snapshot['meta'] ?? NULL) ? $snapshot['meta'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        'updated' => (int) ($snapshot['updated_at'] ?? time()),
      ])
      ->execute();
  }

}
