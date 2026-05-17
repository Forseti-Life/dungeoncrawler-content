<?php

namespace Drupal\dungeoncrawler_content\Service;

use DateTimeImmutable;

/**
 * Resolves elapsed campaign time and tracks timed activities.
 */
class CampaignTimeResolverService {

  /**
   * Public state key for timed activities.
   */
  public const TIMED_ACTIVITIES_KEY = 'timed_activities';

  /**
   * Internal key for queued time effects.
   */
  public const INTERNAL_PENDING_EFFECTS_KEY = '_pending_time_effects';

  /**
   * Internal key that tells handlers to defer direct clock mutation.
   */
  public const INTERNAL_DEFER_KEY = '_defer_time_effects';

  /**
   * Campaign clock helper.
   */
  protected CampaignClockService $campaignClockService;

  /**
   * Constructs the resolver.
   */
  public function __construct(CampaignClockService $campaign_clock_service) {
    $this->campaignClockService = $campaign_clock_service;
  }

  /**
   * Ensures the game state has canonical time fields.
   */
  public function ensureTimeState(array &$game_state, ?int $fallback_timestamp = NULL): void {
    $this->campaignClockService->ensureClock($game_state, $fallback_timestamp);
    $this->campaignClockService->syncLegacyGameTime($game_state);

    if (!isset($game_state[self::TIMED_ACTIVITIES_KEY]) || !is_array($game_state[self::TIMED_ACTIVITIES_KEY])) {
      $game_state[self::TIMED_ACTIVITIES_KEY] = [];
    }
  }

  /**
   * Marks a game-state mutation cycle as time-deferred.
   */
  public function beginDeferredTimeEffects(array &$game_state, ?int $fallback_timestamp = NULL): void {
    $this->ensureTimeState($game_state, $fallback_timestamp);
    $game_state[self::INTERNAL_DEFER_KEY] = TRUE;
    if (!isset($game_state[self::INTERNAL_PENDING_EFFECTS_KEY]) || !is_array($game_state[self::INTERNAL_PENDING_EFFECTS_KEY])) {
      $game_state[self::INTERNAL_PENDING_EFFECTS_KEY] = [];
    }
  }

  /**
   * Returns true when handlers should queue time effects instead of applying them.
   */
  public function shouldDeferTimeEffects(array $game_state): bool {
    return !empty($game_state[self::INTERNAL_DEFER_KEY]);
  }

  /**
   * Queues an elapsed-time effect.
   */
  public function queueElapsedEffect(array &$game_state, array $effect): void {
    $effect['mode'] = $effect['mode'] ?? 'elapsed';
    $game_state[self::INTERNAL_PENDING_EFFECTS_KEY][] = $effect;
  }

  /**
   * Consumes any pending handler-queued time effects.
   */
  public function consumePendingTimeEffects(array &$game_state): array {
    $effects = array_values(array_filter(
      $game_state[self::INTERNAL_PENDING_EFFECTS_KEY] ?? [],
      static fn ($effect) => is_array($effect)
    ));

    unset($game_state[self::INTERNAL_PENDING_EFFECTS_KEY], $game_state[self::INTERNAL_DEFER_KEY]);

    return $effects;
  }

  /**
   * Applies immediate effects and records any scheduled activities.
   */
  public function applyTimeEffects(array &$game_state, array $effects, ?int $fallback_timestamp = NULL): array {
    $this->ensureTimeState($game_state, $fallback_timestamp);

    $normalized_effects = [];
    foreach ($effects as $effect) {
      if (is_array($effect)) {
        $normalized_effects[] = $this->normalizeEffect($effect, $game_state['phase'] ?? 'exploration');
      }
    }

    if ($normalized_effects === []) {
      return [
        'elapsed_minutes' => 0,
        'activities_created' => 0,
        'activities_completed' => 0,
      ];
    }

    $now = $this->campaignClockService->clockToDateTime($game_state[CampaignClockService::STATE_KEY]);
    $elapsed_effects = [];
    $created_activities = 0;

    foreach ($normalized_effects as $effect) {
      $activity = $this->buildActivityRecord($effect, $now);
      $game_state[self::TIMED_ACTIVITIES_KEY][] = $activity;
      $created_activities++;

      if (($effect['mode'] ?? 'elapsed') === 'elapsed' || !empty($effect['advance_immediately'])) {
        $elapsed_effects[] = $effect;
      }
    }

    $elapsed_minutes = $this->resolveElapsedMinutes($elapsed_effects);
    if ($elapsed_minutes > 0) {
      $this->applyCompatibilityCounters($game_state, $normalized_effects, $elapsed_minutes);
      $this->campaignClockService->advanceClock($game_state, $elapsed_minutes);
      $this->campaignClockService->syncLegacyGameTime($game_state);
    }

    $completed = $this->completeReadyActivities($game_state);

    return [
      'elapsed_minutes' => $elapsed_minutes,
      'activities_created' => $created_activities,
      'activities_completed' => $completed,
    ];
  }

  /**
   * Normalizes a queued effect shape.
   */
  protected function normalizeEffect(array $effect, string $default_phase): array {
    $duration_minutes = max(0, (int) ($effect['duration_minutes'] ?? 0));
    $duration_days = max(0, (int) ($effect['duration_days'] ?? 0));
    $total_minutes = $duration_minutes + ($duration_days * 1440);

    $actor_ids = $effect['actor_ids'] ?? [];
    if (!is_array($actor_ids)) {
      $actor_ids = [$actor_ids];
    }
    $actor_ids = array_values(array_unique(array_filter(array_map(
      static fn ($value) => is_scalar($value) ? (string) $value : '',
      $actor_ids
    ))));

    return [
      'mode' => $effect['mode'] ?? 'elapsed',
      'phase' => $effect['phase'] ?? $default_phase,
      'action_type' => $effect['action_type'] ?? 'unknown',
      'actor_ids' => $actor_ids,
      'duration_minutes' => $total_minutes,
      'concurrency_group' => (string) ($effect['concurrency_group'] ?? ''),
      'location_context' => is_array($effect['location_context'] ?? NULL) ? $effect['location_context'] : [],
      'advance_immediately' => !empty($effect['advance_immediately']),
    ];
  }

  /**
   * Creates a stored activity record from a time effect.
   */
  protected function buildActivityRecord(array $effect, DateTimeImmutable $now): array {
    $duration_minutes = (int) ($effect['duration_minutes'] ?? 0);
    $end = $duration_minutes > 0 ? $now->modify('+' . $duration_minutes . ' minutes') : $now;
    $is_completed = ($effect['mode'] ?? 'elapsed') === 'elapsed' || $duration_minutes === 0;

    $record = [
      'activity_id' => uniqid('activity_', TRUE),
      'actor_ids' => $effect['actor_ids'] ?? [],
      'phase' => (string) ($effect['phase'] ?? 'exploration'),
      'action_type' => (string) ($effect['action_type'] ?? 'unknown'),
      'status' => $is_completed ? 'completed' : 'active',
      'started_at' => $now->format('Y-m-d\TH:i:s\Z'),
      'ends_at' => $end->format('Y-m-d\TH:i:s\Z'),
      'duration_minutes' => $duration_minutes,
      'concurrency_group' => (string) ($effect['concurrency_group'] ?? ''),
      'location_context' => is_array($effect['location_context'] ?? NULL) ? $effect['location_context'] : [],
    ];

    if ($is_completed) {
      $record['completed_at'] = $end->format('Y-m-d\TH:i:s\Z');
    }

    return $record;
  }

  /**
   * Resolves how much campaign time a batch of immediate effects should consume.
   */
  protected function resolveElapsedMinutes(array $effects): int {
    $lanes = [];

    foreach ($effects as $effect) {
      $duration = (int) ($effect['duration_minutes'] ?? 0);
      if ($duration <= 0) {
        continue;
      }

      $group = (string) ($effect['concurrency_group'] ?? '');
      $actor_ids = $effect['actor_ids'] ?? [];
      $matched = FALSE;

      foreach ($lanes as &$lane) {
        if ($group !== '' && $lane['concurrency_group'] === $group) {
          $lane['duration_minutes'] = max($lane['duration_minutes'], $duration);
          $lane['actor_ids'] = array_values(array_unique(array_merge($lane['actor_ids'], $actor_ids)));
          $matched = TRUE;
          break;
        }

        if ($actor_ids !== [] && array_intersect($lane['actor_ids'], $actor_ids)) {
          $lane['duration_minutes'] += $duration;
          $lane['actor_ids'] = array_values(array_unique(array_merge($lane['actor_ids'], $actor_ids)));
          $matched = TRUE;
          break;
        }
      }
      unset($lane);

      if (!$matched) {
        $lanes[] = [
          'concurrency_group' => $group,
          'actor_ids' => $actor_ids,
          'duration_minutes' => $duration,
        ];
      }
    }

    $elapsed = 0;
    foreach ($lanes as $lane) {
      $elapsed = max($elapsed, (int) ($lane['duration_minutes'] ?? 0));
    }

    return $elapsed;
  }

  /**
   * Keeps legacy phase-local counters synchronized with resolved elapsed time.
   */
  protected function applyCompatibilityCounters(array &$game_state, array $effects, int $elapsed_minutes): void {
    $phases = array_values(array_unique(array_map(
      static fn (array $effect) => (string) ($effect['phase'] ?? 'exploration'),
      $effects
    )));

    if (in_array('exploration', $phases, TRUE)) {
      if (!isset($game_state['exploration']) || !is_array($game_state['exploration'])) {
        $game_state['exploration'] = [];
      }
      $game_state['exploration']['time_elapsed_minutes'] = (int) ($game_state['exploration']['time_elapsed_minutes'] ?? 0) + $elapsed_minutes;
    }

    if (in_array('downtime', $phases, TRUE)) {
      if (!isset($game_state['downtime']) || !is_array($game_state['downtime'])) {
        $game_state['downtime'] = [];
      }
      $game_state['downtime']['days_elapsed'] = (int) ($game_state['downtime']['days_elapsed'] ?? 0) + intdiv($elapsed_minutes, 1440);
    }
  }

  /**
   * Marks active activities complete once the campaign clock reaches their end.
   */
  protected function completeReadyActivities(array &$game_state): int {
    $this->ensureTimeState($game_state);

    $current = $this->campaignClockService->clockToDateTime($game_state[CampaignClockService::STATE_KEY]);
    $completed = 0;

    foreach ($game_state[self::TIMED_ACTIVITIES_KEY] as &$activity) {
      if (!is_array($activity) || ($activity['status'] ?? '') !== 'active' || empty($activity['ends_at'])) {
        continue;
      }

      try {
        $ends_at = new DateTimeImmutable((string) $activity['ends_at']);
      }
      catch (\Exception) {
        continue;
      }

      if ($ends_at <= $current) {
        $activity['status'] = 'completed';
        $activity['completed_at'] = $current->format('Y-m-d\TH:i:s\Z');
        $completed++;
      }
    }
    unset($activity);

    return $completed;
  }

}
