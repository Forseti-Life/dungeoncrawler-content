<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * PF2E Typed Bonus/Penalty Resolution Engine.
 *
 * Implements PF2E stacking rules (Core Rulebook pp. 444–446):
 * - Typed bonuses (circumstance, item, status): only the highest of the same
 *   type applies.
 * - Untyped bonuses: always stack.
 * - Typed penalties: the worst (most negative) of the same type applies.
 * - Different penalty types: all apply (they stack across types).
 * - Untyped penalties: always stack.
 *
 * Backwards compatible: plain integers in the input array are treated as
 * 'untyped' bonuses/penalties.
 *
 * Req 2079, Req 2082 (core ch09 Core Check Mechanics).
 */
class BonusResolver {

  /**
   * Typed bonus types that do NOT stack (highest wins).
   */
  const TYPED_BONUS_TYPES = ['circumstance', 'item', 'status'];

  /**
   * Typed penalty types that do NOT stack within the same type (worst wins).
   */
  const TYPED_PENALTY_TYPES = ['circumstance', 'item', 'status'];

  /**
   * Resolve a bonus array to a single integer using PF2E stacking rules.
   *
   * For typed bonuses (circumstance, item, status): only the highest value of
   * each type is applied.  For 'untyped' (or plain integers): all values sum.
   *
   * @param array $bonuses
   *   Array of bonus entries. Each entry may be:
   *   - An int (treated as untyped).
   *   - An array with keys 'type' (string) and 'value' (int).
   *
   * @return int
   *   Net bonus to add to the roll.
   */
  public static function resolve(array $bonuses): int {
    $by_type = [];
    $untyped_sum = 0;

    foreach ($bonuses as $entry) {
      if (is_int($entry) || is_numeric($entry)) {
        $untyped_sum += (int) $entry;
        continue;
      }
      if (is_array($entry)) {
        $type  = strtolower(trim($entry['type'] ?? 'untyped'));
        $value = (int) ($entry['value'] ?? 0);
        if ($type === 'untyped' || !in_array($type, self::TYPED_BONUS_TYPES, TRUE)) {
          $untyped_sum += $value;
        }
        else {
          // Keep only the highest of this typed bonus.
          if (!isset($by_type[$type]) || $value > $by_type[$type]) {
            $by_type[$type] = $value;
          }
        }
      }
    }

    return $untyped_sum + array_sum($by_type);
  }

  /**
   * Resolve a penalty array to a single negative integer using PF2E rules.
   *
   * For typed penalties: only the worst (most negative) value per type applies.
   * Different types and untyped penalties always stack.
   *
   * @param array $penalties
   *   Array of penalty entries. Each entry may be:
   *   - An int (treated as untyped; pass as positive or negative — both handled).
   *   - An array with keys 'type' (string) and 'value' (int, expected <= 0).
   *
   * @return int
   *   Net penalty (will be <= 0).
   */
  public static function resolvePenalties(array $penalties): int {
    $by_type = [];
    $untyped_sum = 0;

    foreach ($penalties as $entry) {
      if (is_int($entry) || is_numeric($entry)) {
        // Normalise: ensure negative.
        $untyped_sum -= abs((int) $entry);
        continue;
      }
      if (is_array($entry)) {
        $type  = strtolower(trim($entry['type'] ?? 'untyped'));
        $value = (int) ($entry['value'] ?? 0);
        // Normalise to negative.
        $value = -abs($value);
        if ($value === 0) {
          continue;
        }
        if ($type === 'untyped' || !in_array($type, self::TYPED_PENALTY_TYPES, TRUE)) {
          $untyped_sum += $value;
        }
        else {
          // Keep only the worst (most negative) of this typed penalty.
          if (!isset($by_type[$type]) || $value < $by_type[$type]) {
            $by_type[$type] = $value;
          }
        }
      }
    }

    return $untyped_sum + array_sum($by_type);
  }

}
