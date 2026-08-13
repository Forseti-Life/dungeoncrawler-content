<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical trigger catalog for numeric disposition mutation deltas.
 */
final class DispositionTriggerCatalog {

  /**
   * Resolve canonical trigger definition for one domain event type.
   *
   * @return array<string,mixed>
   *   Keys: event_type, actor_delta, relationship_delta, relationship_score_override,
   *   durable, repeat_window_sec.
   */
  public static function resolve(string $event_type): array {
    $event = strtolower(trim($event_type));
    $fallback = self::resolveFamilyFallback($event);
    return match ($event) {
      'diplomacy_critical_success' => self::entry($event, 10, 20, TRUE, 900),
      'diplomacy_success', 'help' => self::entry($event, 6, 12, TRUE, 900),
      'gift' => self::entry($event, 4, 10, TRUE, 900),
      'conversation', 'small_talk' => self::entry($event, 1, 2, TRUE, 300),
      'diplomacy_failure' => self::entry($event, -4, -8, TRUE, 900),
      'intimidation_critical_success' => self::entry($event, -10, -18, TRUE, 900),
      'intimidation_success', 'threat' => self::entry($event, -6, -12, TRUE, 900),
      'intimidation_failure' => self::entry($event, -3, -6, TRUE, 900),
      'intimidation_critical_failure' => self::entry($event, -8, -14, TRUE, 1200),
      'betrayal', 'theft' => self::entry($event, -12, -22, TRUE, 1800),
      'combat_initiation_declared' => self::entry($event, -4, 0, TRUE, 900),
      'harm', 'attack', 'damage', 'negative_effect_spell', 'combat_outcome' => self::entry($event, -15, -100, TRUE, 1800, -100),
      default => $fallback ?? self::entry($event, 0, 0, FALSE, 300),
    };
  }

  /**
   * Resolve a family-level fallback so new actions do not require per-action rows.
   *
   * @return array<string,mixed>|null
   */
  private static function resolveFamilyFallback(string $event): ?array {
    if ($event === '') {
      return NULL;
    }

    if (
      str_contains($event, 'attack')
      || str_contains($event, 'damage')
      || str_contains($event, 'harm')
      || str_contains($event, 'negative_effect_spell')
      || str_contains($event, 'combat_')
    ) {
      return self::entry($event, -15, -100, TRUE, 1800, -100);
    }

    if (
      str_contains($event, 'aid')
      || str_contains($event, 'help')
      || str_contains($event, 'heal')
      || str_contains($event, 'first_aid')
      || str_contains($event, 'battle_medicine')
      || str_contains($event, 'treat_poison')
    ) {
      return self::resolveOutcomeScaledEntry($event, 2, 4, TRUE, 900);
    }

    if (
      str_contains($event, 'diplomacy')
      || str_contains($event, 'persuasion')
      || str_contains($event, 'perform')
      || str_contains($event, 'conversation')
      || str_contains($event, 'sense_motive')
      || str_contains($event, 'point_out')
      || str_contains($event, 'command_animal')
    ) {
      return self::resolveOutcomeScaledEntry($event, 1, 2, TRUE, 900);
    }

    if (
      str_contains($event, 'intimidation')
      || str_contains($event, 'deception')
      || str_contains($event, 'threat')
    ) {
      return self::resolveOutcomeScaledEntry($event, -2, -4, TRUE, 900);
    }

    return NULL;
  }

  /**
   * Apply outcome-aware scaling for *_critical_success/success/failure/... events.
   *
   * @return array<string,mixed>
   */
  private static function resolveOutcomeScaledEntry(
    string $event,
    int $base_actor_delta,
    int $base_relationship_delta,
    bool $durable,
    int $repeat_window_sec
  ): array {
    $outcome = self::resolveOutcomeSuffix($event);
    return match ($outcome) {
      'critical_success' => self::entry($event, $base_actor_delta * 2, $base_relationship_delta * 2, $durable, $repeat_window_sec),
      'success' => self::entry($event, $base_actor_delta, $base_relationship_delta, $durable, $repeat_window_sec),
      'failure' => self::entry($event, 0, 0, $durable, $repeat_window_sec),
      'critical_failure' => self::entry($event, -abs($base_actor_delta), -abs($base_relationship_delta), $durable, 1200),
      default => self::entry($event, $base_actor_delta, $base_relationship_delta, $durable, $repeat_window_sec),
    };
  }

  /**
   * Resolve success/failure outcome suffix from event key.
   */
  private static function resolveOutcomeSuffix(string $event): string {
    if (str_ends_with($event, '_critical_success')) {
      return 'critical_success';
    }
    if (str_ends_with($event, '_success')) {
      return 'success';
    }
    if (str_ends_with($event, '_critical_failure')) {
      return 'critical_failure';
    }
    if (str_ends_with($event, '_failure')) {
      return 'failure';
    }
    return '';
  }

  /**
   * @return array<string,mixed>
   */
  private static function entry(string $event_type, int $actor_delta, int $relationship_delta, bool $durable, int $repeat_window_sec, ?int $relationship_score_override = NULL): array {
    $entry = [
      'event_type' => $event_type,
      'actor_delta' => DispositionAuthorityContract::clampScore($actor_delta),
      'relationship_delta' => DispositionAuthorityContract::clampScore($relationship_delta),
      'durable' => $durable,
      'repeat_window_sec' => max(0, $repeat_window_sec),
    ];
    if ($relationship_score_override !== NULL) {
      $entry['relationship_score_override'] = DispositionAuthorityContract::clampScore($relationship_score_override);
    }

    return $entry;
  }

}
