<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical writer/reader façade for relationship-edge sentiment.
 */
class RelationshipAttitudeService {

  protected RelationshipManagerService $relationshipManager;
  protected RelationshipAttitudeEventStoreService $eventStoreService;
  protected RelationshipAttitudeStateStoreService $stateStoreService;

  public function __construct(
    RelationshipManagerService $relationship_manager,
    RelationshipAttitudeEventStoreService $event_store_service,
    RelationshipAttitudeStateStoreService $state_store_service
  ) {
    $this->relationshipManager = $relationship_manager;
    $this->eventStoreService = $event_store_service;
    $this->stateStoreService = $state_store_service;
  }

  /**
   * List outgoing relationships for one source entity.
   */
  public function listOutgoingRelationships(int $campaign_id, string $source_type, string $source_id): array {
    return $this->relationshipManager->listEntityRelationships($campaign_id, $source_type, $source_id);
  }

  /**
   * Upsert one relationship-edge attitude payload.
   */
  public function upsertRelationshipAttitude(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_type,
    string $target_id,
    string $attitude,
    string $relationship_type = 'knows',
    string $status = 'known',
    array $relationship_state = []
  ): int {
    $before = $this->stateStoreService->getEdgeState($campaign_id, $source_type, $source_id, $target_type, $target_id);
    $normalized_attitude = DispositionAuthorityContract::normalizeAttitudeLabel($attitude);
    $resolved_score = isset($relationship_state['score']) && is_numeric($relationship_state['score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $relationship_state['score']))
      : DispositionAuthorityContract::attitudeToScore($normalized_attitude);
    $score_source = isset($relationship_state['score']) && is_numeric($relationship_state['score'])
      ? 'relationship_state_score'
      : ($normalized_attitude !== '' ? 'attitude_bucket' : 'none');
    $inserted = $this->relationshipManager->upsertRuntimeRelationship($campaign_id, [
      'source_type' => $source_type,
      'source_id' => $source_id,
      'target_type' => $target_type,
      'target_id' => $target_id,
      'relationship_type' => $relationship_type,
      'attitude' => $normalized_attitude !== '' ? $normalized_attitude : $attitude,
      'status' => $status,
      'relationship_state' => $relationship_state,
    ]);
    $snapshot = $this->stateStoreService->storeLatestState(
      $campaign_id,
      $source_type,
      $source_id,
      $target_type,
      $target_id,
      $normalized_attitude !== '' ? $normalized_attitude : $attitude,
      [
        'relationship_type' => $relationship_type,
        'status' => $status,
        'score' => $resolved_score,
        'score_source' => $score_source,
      ] + $relationship_state
    );
    $this->eventStoreService->recordAttitudeEvent(
      $campaign_id,
      $source_type,
      $source_id,
      $target_type,
      $target_id,
      [
        'event_type' => 'relationship_attitude_upsert',
        'attitude_before' => (string) ($before['attitude'] ?? ''),
        'attitude_after' => (string) ($snapshot['attitude'] ?? $normalized_attitude),
        'score_before' => isset($before['score']) && is_numeric($before['score']) ? (int) $before['score'] : NULL,
        'score_after' => isset($snapshot['score']) && is_numeric($snapshot['score']) ? (int) $snapshot['score'] : $resolved_score,
        'summary' => $snapshot,
        'context' => [
          'relationship_type' => $relationship_type,
          'status' => $status,
          'score' => $resolved_score,
          'score_source' => $score_source,
        ],
      ]
    );
    return $inserted;
  }

  /**
   * Apply one deterministic score delta to a directed relationship edge.
   */
  public function applyRelationshipDispositionDelta(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_type,
    string $target_id,
    int $score_delta,
    string $reason = '',
    array $context = []
  ): int {
    $idempotency_key = trim((string) ($context['idempotency_key'] ?? ''));
    if (
      $idempotency_key !== ''
      && $this->eventStoreService->hasRelationshipAttitudeEventIdempotencyKey(
        $campaign_id,
        $source_type,
        $source_id,
        $target_type,
        $target_id,
        $idempotency_key
      )
    ) {
      return 0;
    }

    $before = $this->stateStoreService->getEdgeState($campaign_id, $source_type, $source_id, $target_type, $target_id);
    $before_score = isset($before['score']) && is_numeric($before['score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $before['score']))
      : (DispositionAuthorityContract::attitudeToScore((string) ($before['attitude'] ?? '')) ?? 0);
    $after_score = DispositionAuthorityContract::clampScore($before_score + $score_delta);
    $after_attitude = DispositionAuthorityContract::scoreToAttitude($after_score);

    return $this->upsertRelationshipAttitude(
      $campaign_id,
      $source_type,
      $source_id,
      $target_type,
      $target_id,
      $after_attitude,
      (string) ($context['relationship_type'] ?? 'knows'),
      (string) ($context['status'] ?? 'known'),
      [
        'score' => $after_score,
        'score_source' => 'relationship_state_score',
        'delta' => $score_delta,
        'reason' => $reason,
        'idempotency_key' => $idempotency_key,
      ] + $context
    );
  }

  /**
   * Resolve relationship-edge attitude from source actor ref to target refs.
   *
   * @deprecated Use resolveEdgeDispositionDetails() for score-first authority.
   *   This API is retained for compatibility projections only.
   */
  public function resolveEdgeAttitude(string $source_entity_ref, array $target_entity_refs, int $campaign_id): string {
    $source_candidates = $this->buildEntityRefCandidates($source_entity_ref);
    if ($campaign_id <= 0 || $source_candidates === []) {
      return '';
    }
    $target_candidates = [];
    foreach ($target_entity_refs as $target_ref) {
      $target_candidates = array_merge($target_candidates, $this->buildEntityRefCandidates((string) $target_ref));
    }
    $target_candidates = array_values(array_unique(array_filter($target_candidates, static fn($value): bool => trim((string) $value) !== '')));
    if ($target_candidates === []) {
      return '';
    }
    $stored = $this->stateStoreService->findStrongestAttitude($campaign_id, $source_candidates, $target_candidates);
    if ($stored !== '') {
      return $stored;
    }

    $source_types = ['campaign_npc', 'campaign_character'];
    $matches = [];
    foreach ($source_types as $source_type) {
      foreach ($source_candidates as $source_id) {
        $rows = $this->listOutgoingRelationships($campaign_id, $source_type, $source_id);
        foreach ($rows as $row) {
          $target_id = trim((string) ($row['target_id'] ?? ''));
          if ($target_id === '' || !in_array($target_id, $target_candidates, TRUE)) {
            continue;
          }
          $attitude = strtolower(trim((string) ($row['attitude'] ?? '')));
          if ($attitude !== '') {
            $matches[] = $attitude;
          }
        }
      }
    }

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
   * Resolve edge disposition details (attitude + score) for one source→target.
   *
   * @return array<string,mixed>
   *   Keys: attitude, score, score_source, relationship_type, status.
   */
  public function resolveEdgeDispositionDetails(string $source_entity_ref, string $target_entity_ref, int $campaign_id): array {
    $source_candidates = $this->buildEntityRefCandidates($source_entity_ref);
    $target_candidates = $this->buildEntityRefCandidates($target_entity_ref);
    if ($campaign_id <= 0 || $source_candidates === [] || $target_candidates === []) {
      return [
        'attitude' => '',
        'score' => NULL,
        'score_source' => 'none',
        'relationship_type' => '',
        'status' => '',
      ];
    }

    $stored = $this->stateStoreService->findStrongestDisposition($campaign_id, $source_candidates, $target_candidates);
    if (is_array($stored)) {
      return [
        'attitude' => DispositionAuthorityContract::normalizeAttitudeLabel((string) ($stored['attitude'] ?? '')),
        'score' => isset($stored['score']) && is_numeric($stored['score']) ? DispositionAuthorityContract::clampScore((int) $stored['score']) : NULL,
        'score_source' => (string) ($stored['score_source'] ?? 'none'),
        'relationship_type' => (string) ($stored['relationship_type'] ?? ''),
        'status' => (string) ($stored['status'] ?? ''),
      ];
    }

    $best = NULL;
    $source_types = ['campaign_npc', 'campaign_character'];
    foreach ($source_types as $source_type) {
      foreach ($source_candidates as $source_id) {
        $rows = $this->listOutgoingRelationships($campaign_id, $source_type, $source_id);
        foreach ($rows as $row) {
          if (!is_array($row)) {
            continue;
          }
          $target_id = trim((string) ($row['target_id'] ?? ''));
          if ($target_id === '' || !in_array($target_id, $target_candidates, TRUE)) {
            continue;
          }

          $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($row['attitude'] ?? ''));

          $state = is_array($row['relationship_state'] ?? NULL) ? $row['relationship_state'] : [];
          $state_score = NULL;
          if (isset($state['score']) && is_numeric($state['score'])) {
            $state_score = DispositionAuthorityContract::clampScore((int) round((float) $state['score']));
          }
          $fallback_score = DispositionAuthorityContract::attitudeToScore($attitude);
          $resolved_score = $state_score !== NULL ? $state_score : $fallback_score;
          $score_source = $state_score !== NULL ? 'relationship_state_score' : ($attitude !== '' ? 'attitude_bucket' : 'none');
          $updated_at = (int) ($row['updated_at'] ?? 0);

          $candidate = [
            'attitude' => $attitude,
            'score' => $resolved_score,
            'score_source' => $score_source,
            'relationship_type' => (string) ($row['relationship_type'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'updated_at' => $updated_at,
            'priority' => $this->scoreSourcePriority($score_source),
            'magnitude' => $resolved_score !== NULL ? abs((int) $resolved_score) : 0,
          ];

          if ($best === NULL) {
            $best = $candidate;
            continue;
          }
          if ((int) $candidate['priority'] > (int) ($best['priority'] ?? 0)) {
            $best = $candidate;
            continue;
          }
          if ((int) $candidate['priority'] === (int) ($best['priority'] ?? 0) && (int) $candidate['magnitude'] > (int) ($best['magnitude'] ?? 0)) {
            $best = $candidate;
            continue;
          }
          if (
            (int) $candidate['priority'] === (int) ($best['priority'] ?? 0)
            && (int) $candidate['magnitude'] === (int) ($best['magnitude'] ?? 0)
            && (int) $candidate['updated_at'] > (int) ($best['updated_at'] ?? 0)
          ) {
            $best = $candidate;
          }
        }
      }
    }

    if (!is_array($best)) {
      return [
        'attitude' => '',
        'score' => NULL,
        'score_source' => 'none',
        'relationship_type' => '',
        'status' => '',
      ];
    }

    return [
      'attitude' => (string) ($best['attitude'] ?? ''),
      'score' => isset($best['score']) ? (int) $best['score'] : NULL,
      'score_source' => (string) ($best['score_source'] ?? 'none'),
      'relationship_type' => (string) ($best['relationship_type'] ?? ''),
      'status' => (string) ($best['status'] ?? ''),
    ];
  }

  /**
   * Build acceptable canonical-id candidates from one actor/entity ref.
   *
   * @return array<int, string>
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

    return array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $candidates), static fn($value): bool => $value !== '')));
  }

  /**
   * Resolve deterministic score from canonical attitude.
   */
  protected function attitudeToScore(string $attitude): ?int {
    return DispositionAuthorityContract::attitudeToScore($attitude);
  }

  /**
   * Clamp one score to canonical disposition range.
   */
  protected function clampScore(int $score): int {
    return DispositionAuthorityContract::clampScore($score);
  }

  /**
   * Rank score sources for deterministic selection.
   */
  protected function scoreSourcePriority(string $score_source): int {
    return match (strtolower(trim($score_source))) {
      'relationship_state_score' => 3,
      'attitude_bucket' => 2,
      default => 1,
    };
  }

}
