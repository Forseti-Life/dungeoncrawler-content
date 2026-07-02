<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves NPC ability scores from known runtime/profile data shapes.
 */
final class NpcAbilityScoreResolver {

  public const DEFAULT_ABILITY_SCORE = 10;
  public const DEFAULT_CHARISMA_SCORE = self::DEFAULT_ABILITY_SCORE;
  public const MIN_ABILITY_SCORE = 3;

  protected const ABILITY_ALIASES = [
    'charisma' => ['charisma', 'cha'],
  ];

  protected const ABILITY_SOURCE_PATHS = [
    'entity.state.abilities',
    'entity.abilities',
    'entity.character_data.abilities',
    'entity.state.character_data.abilities',
    'entity.state.pf2e_stats.ability_scores',
    'entity.pf2e_stats.ability_scores',
    'entity.character_data.ability_scores',
    'entity.state.character_data.ability_scores',
    'abilities',
    'ability_scores',
    'profile.abilities',
    'profile.stats',
    'profile.ability_scores',
  ];

  /**
   * Resolve one ability score, defaulting when no score is present.
   */
  public static function resolveAbilityScore(array $npc_profile, string $ability, int $default = self::DEFAULT_ABILITY_SCORE): int {
    $resolved = self::findAbilityScore($npc_profile, $ability);
    return self::normalizeAbilityScore($resolved ?? $default);
  }

  /**
   * Resolve Charisma score, defaulting when no score is present.
   */
  public static function resolveCharismaScore(array $npc_profile, int $default = self::DEFAULT_CHARISMA_SCORE): int {
    return self::resolveAbilityScore($npc_profile, 'charisma', $default);
  }

  /**
   * Find one ability score, or NULL when no numeric score is present.
   */
  public static function findAbilityScore(array $npc_profile, string $ability): ?int {
    $ability = strtolower(trim($ability));
    if ($ability === '') {
      return NULL;
    }

    foreach (self::resolveAbilitySources($npc_profile) as $source) {
      foreach (self::resolveAbilityLookupKeys($ability) as $ability_key) {
        if (!array_key_exists($ability_key, $source)) {
          continue;
        }

        $score = self::coerceAbilityScoreValue($source[$ability_key]);
        if ($score !== NULL) {
          return self::normalizeAbilityScore($score);
        }
      }
    }

    return NULL;
  }

  /**
   * Find Charisma score, or NULL when no numeric score is present.
   */
  public static function findCharismaScore(array $npc_profile): ?int {
    return self::findAbilityScore($npc_profile, 'charisma');
  }

  /**
   * @return array<int, string>
   *   Normalized ability keys in lookup priority order.
   */
  protected static function resolveAbilityLookupKeys(string $ability): array {
    $aliases = self::ABILITY_ALIASES[$ability] ?? [$ability];
    return array_values(array_unique(array_map(static fn(string $key): string => strtolower(trim($key)), $aliases)));
  }

  /**
   * @return array<int, array<string, mixed>>
   *   Candidate ability containers in lookup priority order.
   */
  protected static function resolveAbilitySources(array $npc_profile): array {
    $sources = [];
    foreach (self::ABILITY_SOURCE_PATHS as $path) {
      $candidate = self::resolveValueByPath($npc_profile, $path);
      if (is_array($candidate)) {
        $sources[] = $candidate;
      }
    }

    return $sources;
  }

  /**
   * Resolve a nested value from an array by dot-delimited path.
   */
  protected static function resolveValueByPath(array $source, string $path): mixed {
    if ($path === '') {
      return NULL;
    }

    $segments = explode('.', $path);
    $current = $source;
    foreach ($segments as $segment) {
      if (!is_array($current) || !array_key_exists($segment, $current)) {
        return NULL;
      }
      $current = $current[$segment];
    }

    return $current;
  }

  /**
   * Normalize one raw ability payload into a numeric score.
   */
  protected static function coerceAbilityScoreValue(mixed $raw_value): ?int {
    $value = $raw_value;
    if (is_array($value)) {
      $value = $value['score'] ?? $value['value'] ?? $value['base'] ?? NULL;
    }

    return is_numeric($value) ? (int) $value : NULL;
  }

  /**
   * Normalize scores to valid in-game minimums.
   */
  protected static function normalizeAbilityScore(int $score): int {
    return max(self::MIN_ABILITY_SCORE, $score);
  }

}
