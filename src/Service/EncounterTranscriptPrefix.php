<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared encounter transcript prefix helpers.
 */
final class EncounterTranscriptPrefix {

  public const PREFIX_REGEX = '/^Round\s+(?:\d+|\?)\:\s+Turn\s+(?:\d+|\?)\:\s+Actor\s+.*\:\s+/u';

  /**
   * Return the 0-based round number for transcript display.
   */
  public static function displayRound(int|string|null $round_raw): int|string {
    if (!is_numeric($round_raw)) {
      return '?';
    }
    return max(0, ((int) $round_raw) - 1);
  }

  /**
   * Return the 1-based turn number for transcript display.
   */
  public static function displayTurnFromIndexRaw(int|string|null $turn_index_raw): int|string {
    if (!is_numeric($turn_index_raw)) {
      return '?';
    }
    return ((int) $turn_index_raw) + 1;
  }

  public static function formatPrefix(int|string $round_display, int|string $turn_display, string $actor_name): string {
    $actor_name = trim($actor_name);
    if ($actor_name === '') {
      $actor_name = 'Unknown';
    }

    return sprintf('Round %s: Turn %s: Actor %s: ', (string) $round_display, (string) $turn_display, $actor_name);
  }

  public static function isPrefixed(string $content): bool {
    return (bool) preg_match(self::PREFIX_REGEX, $content);
  }

}
