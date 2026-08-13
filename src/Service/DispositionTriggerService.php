<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Applies canonical disposition triggers with deterministic deltas.
 */
class DispositionTriggerService {

  /**
   * Apply deterministic repeat-window damping and score caps.
   *
   * @param array<string,mixed> $trigger
   *   Catalog entry.
   * @param array<string,mixed> $event_context
   *   Event context payload.
   *
   * @return array<string,mixed>
   *   Normalized trigger with actor_delta/relationship_delta/relationship_score_override/idempotency_key.
   */
  public function normalizeTrigger(string $event_type, array $event_context = []): array {
    $trigger = DispositionTriggerCatalog::resolve($event_type);
    $event_time = isset($event_context['event_timestamp']) && is_numeric($event_context['event_timestamp'])
      ? (int) $event_context['event_timestamp']
      : time();
    $last_applied = isset($event_context['last_applied_timestamp']) && is_numeric($event_context['last_applied_timestamp'])
      ? (int) $event_context['last_applied_timestamp']
      : 0;
    $repeat_window_sec = (int) ($trigger['repeat_window_sec'] ?? 0);
    $actor_delta = (int) ($trigger['actor_delta'] ?? 0);
    $relationship_delta = (int) ($trigger['relationship_delta'] ?? 0);
    $relationship_score_override = isset($trigger['relationship_score_override']) && is_numeric($trigger['relationship_score_override'])
      ? DispositionAuthorityContract::clampScore((int) $trigger['relationship_score_override'])
      : NULL;

    if ($repeat_window_sec > 0 && $last_applied > 0 && ($event_time - $last_applied) < $repeat_window_sec) {
      // Dampen repeated low-latency trigger spam while preserving sign.
      $actor_delta = (int) round($actor_delta * 0.5);
      $relationship_delta = (int) round($relationship_delta * 0.5);
    }

    $actor_delta = DispositionAuthorityContract::clampScore($actor_delta);
    $relationship_delta = DispositionAuthorityContract::clampScore($relationship_delta);
    $idempotency_key = trim((string) ($event_context['idempotency_key'] ?? ''));
    if ($idempotency_key === '') {
      $idempotency_key = sha1(json_encode([
        'event_type' => strtolower(trim($event_type)),
        'campaign_id' => (int) ($event_context['campaign_id'] ?? 0),
        'source_entity_ref' => (string) ($event_context['source_entity_ref'] ?? ''),
        'target_entity_ref' => (string) ($event_context['target_entity_ref'] ?? ''),
        'event_time' => $event_time,
        'reason' => (string) ($event_context['reason'] ?? ''),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    return [
      'event_type' => (string) ($trigger['event_type'] ?? strtolower(trim($event_type))),
      'actor_delta' => $actor_delta,
      'relationship_delta' => $relationship_delta,
      'relationship_score_override' => $relationship_score_override,
      'durable' => !empty($trigger['durable']),
      'repeat_window_sec' => $repeat_window_sec,
      'idempotency_key' => $idempotency_key,
    ];
  }

}
