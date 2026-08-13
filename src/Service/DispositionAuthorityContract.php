<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical disposition authority contract shared by social subsystems.
 */
final class DispositionAuthorityContract {

  public const SCORE_MIN = -1000;
  public const SCORE_MAX = 1000;
  public const HOSTILE_SCORE_THRESHOLD = -70;

  public const LABEL_HELPFUL = 'helpful';
  public const LABEL_FRIENDLY = 'friendly';
  public const LABEL_INDIFFERENT = 'indifferent';
  public const LABEL_UNFRIENDLY = 'unfriendly';
  public const LABEL_HOSTILE = 'hostile';

  /**
   * Ordered from most positive to most negative.
   */
  public const ATTITUDE_LADDER = [
    self::LABEL_HELPFUL,
    self::LABEL_FRIENDLY,
    self::LABEL_INDIFFERENT,
    self::LABEL_UNFRIENDLY,
    self::LABEL_HOSTILE,
  ];

  public const AUTHORITY_ACTOR_BASELINE_STATE = 'actor_baseline_state';
  public const AUTHORITY_RELATIONSHIP_EDGE_STATE = 'relationship_edge_state';
  public const AUTHORITY_SCENE_CONTEXT_STATE = 'scene_context_state';
  public const AUTHORITY_RESOLVER = 'disposition_resolver';

  /**
   * Normalize an attitude label to canonical form.
   */
  public static function normalizeAttitudeLabel(string $attitude): string {
    $normalized = strtolower(trim($attitude));
    return in_array($normalized, self::ATTITUDE_LADDER, TRUE) ? $normalized : '';
  }

  /**
   * Return whether a label is in the canonical disposition ladder.
   */
  public static function isValidAttitudeLabel(string $attitude): bool {
    return self::normalizeAttitudeLabel($attitude) !== '';
  }

  /**
   * Clamp one disposition score to canonical numeric range.
   */
  public static function clampScore(int $score): int {
    return max(self::SCORE_MIN, min(self::SCORE_MAX, $score));
  }

  /**
   * Normalize an arbitrary numeric-like value into canonical score bounds.
   */
  public static function normalizeScore(mixed $score): int {
    if (!is_numeric($score)) {
      return 0;
    }
    return self::clampScore((int) round((float) $score));
  }

  /**
   * Determine whether one score crosses canonical hostility threshold.
   */
  public static function isHostileScore(int $score): bool {
    return self::clampScore($score) <= self::HOSTILE_SCORE_THRESHOLD;
  }

  /**
   * Resolve deterministic numeric score from canonical attitude label.
   */
  public static function attitudeToScore(string $attitude): ?int {
    return match (self::normalizeAttitudeLabel($attitude)) {
      self::LABEL_HELPFUL => 100,
      self::LABEL_FRIENDLY => 50,
      self::LABEL_INDIFFERENT => 0,
      self::LABEL_UNFRIENDLY => -50,
      self::LABEL_HOSTILE => -100,
      default => NULL,
    };
  }

  /**
   * Project a canonical attitude label from one numeric score.
   */
  public static function scoreToAttitude(int $score): string {
    $score = self::clampScore($score);
    if ($score >= 75) {
      return self::LABEL_HELPFUL;
    }
    if ($score > 0) {
      return self::LABEL_FRIENDLY;
    }
    if ($score <= -75) {
      return self::LABEL_HOSTILE;
    }
    if ($score < 0) {
      return self::LABEL_UNFRIENDLY;
    }
    return self::LABEL_INDIFFERENT;
  }

}
