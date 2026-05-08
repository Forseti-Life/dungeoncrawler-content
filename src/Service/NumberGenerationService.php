<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use InvalidArgumentException;

/**
 * Service for Pathfinder-compatible number and dice generation.
 */
class NumberGenerationService {

  /**
   * Pathfinder dice sizes used by this module.
   */
  public const PATHFINDER_DICE = [4, 6, 8, 10, 12, 20, 100];

  /**
   * Database connection for roll logging.
   */
  protected ?Connection $database;

  /**
   * Current user for roll logging.
   */
  protected ?AccountInterface $currentUser;

  public function __construct(?Connection $database = NULL, ?AccountInterface $current_user = NULL) {
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * Roll a Pathfinder die.
   *
   * Supported: d4, d6, d8, d10, d12, d20, d100.
   *
   * @param int $sides
   *   Die sides.
   *
   * @return int
   *   Roll result.
   */
  public function rollPathfinderDie(int $sides): int {
    if (!in_array($sides, self::PATHFINDER_DICE, TRUE)) {
      throw new InvalidArgumentException(sprintf('Unsupported Pathfinder die: d%d', $sides));
    }

    return random_int(1, $sides);
  }

  /**
   * Roll percentile (1-100).
   */
  public function rollPercentile(): int {
    return $this->rollPathfinderDie(100);
  }

  /**
   * Roll a random integer inside an inclusive range.
   *
   * @param int $minimum
   *   Lower bound (inclusive).
   * @param int $maximum
   *   Upper bound (inclusive).
   *
   * @return int
   *   Random integer in range.
   */
  public function rollRange(int $minimum, int $maximum): int {
    if ($minimum > $maximum) {
      throw new InvalidArgumentException('Minimum must be less than or equal to maximum.');
    }

    return random_int($minimum, $maximum);
  }

  /**
   * Alias for rollRange() to match older service expectations.
   *
   * @param int $minimum
   *   Lower bound (inclusive).
   * @param int $maximum
   *   Upper bound (inclusive).
   *
   * @return int
   *   Random integer in range.
   */
  public function randomInt(int $minimum, int $maximum): int {
    return $this->rollRange($minimum, $maximum);
  }

  /**
   * Roll one or more dice in standard notation.
   *
   * Examples: 1d20, 2d6+3, 4d8-1, 1d100.
   *
   * @param string $notation
   *   Dice notation.
   *
   * @return array
   *   Array with keys: notation, count, sides, modifier, rolls, subtotal, total.
   */
  public function rollNotation(string $notation): array {
    $notation = strtolower(trim($notation));
    $pattern = '/^(\d+)d(\d+)([+-]\d+)?$/';
    if (!preg_match($pattern, $notation, $matches)) {
      throw new InvalidArgumentException(sprintf('Invalid dice notation: %s', $notation));
    }

    $count = (int) $matches[1];
    $sides = (int) $matches[2];
    $modifier = isset($matches[3]) ? (int) $matches[3] : 0;

    if ($count < 1) {
      throw new InvalidArgumentException('Dice count must be at least 1.');
    }
    if ($count > 100) {
      throw new InvalidArgumentException('Dice count cannot exceed 100.');
    }

    $rolls = $this->rollMultiple($sides, $count);
    $subtotal = array_sum($rolls);

    return [
      'notation' => $notation,
      'count' => $count,
      'sides' => $sides,
      'modifier' => $modifier,
      'rolls' => $rolls,
      'subtotal' => $subtotal,
      'total' => $subtotal + $modifier,
    ];
  }

  /**
   * Roll multiple dice with the same number of sides.
   *
   * Supports side ranges from 1 to 100.
   *
   * @param int $sides
   *   Number of sides.
   * @param int $count
   *   Number of dice to roll.
   *
   * @return int[]
   *   Individual roll results.
   */
  public function rollMultiple(int $sides, int $count = 1): array {
    if ($sides < 1 || $sides > 100) {
      throw new InvalidArgumentException('Supported die sides range is 1-100.');
    }
    if ($count < 1) {
      throw new InvalidArgumentException('Dice count must be at least 1.');
    }

    $results = [];
    for ($index = 0; $index < $count; $index++) {
      $results[] = random_int(1, $sides);
    }

    return $results;
  }

  /**
   * Parse and roll a dice expression.
   *
   * Supported formats:
   *   NdX           e.g. 2d6
   *   NdX+M / NdX-M e.g. 1d20+5
   *   d%            percentile (two d10: tens + ones → 1-100)
   *   NdXkhK        keep highest K dice (e.g. 4d6kh3)
   *   NdXklK        keep lowest K dice
   *
   * @param string $expression  Dice expression string.
   * @param int|null $characterId  Optional character ID for roll log.
   * @param string $rollType    Roll type context (attack/skill/damage/save/initiative).
   *
   * @return array [
   *   'expression' => string,
   *   'dice'       => int[],   // all individual rolls
   *   'kept'       => int[],   // kept rolls (same as dice unless kh/kl)
   *   'modifier'   => int,
   *   'total'      => int,
   *   'error'      => string|null,
   * ]
   */
  public function rollExpression(string $expression, ?int $characterId = NULL, string $rollType = 'general'): array {
    $expr = strtolower(trim($expression));

    // d% → two d10 (tens + ones), producing 1-100.
    if ($expr === 'd%') {
      $tens = random_int(0, 9) * 10;
      $ones = random_int(0, 9);
      $total = ($tens + $ones) === 0 ? 100 : $tens + $ones;
      $result = [
        'expression' => $expression,
        'dice'       => [$tens, $ones],
        'kept'       => [$total],
        'modifier'   => 0,
        'total'      => $total,
        'error'      => NULL,
      ];
      $this->logRoll($expression, $total, $characterId, $rollType);
      return $result;
    }

    // Parse NdX[kh/kl K][+/-M]
    // Pattern: (N)d(X)[kh|kl(K)][+/-M]
    $pattern = '/^(\d*)d(\d+)(k[hl]\d+)?([+-]\d+)?$/';
    if (!preg_match($pattern, $expr, $m)) {
      return ['expression' => $expression, 'dice' => [], 'kept' => [], 'modifier' => 0, 'total' => 0,
        'error' => "Invalid dice expression: {$expression}. Use formats like 2d6, 1d20+5, 4d6kh3, or d%."];
    }

    $count    = $m[1] === '' ? 1 : (int) $m[1];
    $sides    = (int) $m[2];
    $keepSpec = $m[3] ?? '';
    $modifier = isset($m[4]) ? (int) $m[4] : 0;

    if ($count <= 0) {
      return ['expression' => $expression, 'dice' => [], 'kept' => [], 'modifier' => 0, 'total' => 0,
        'error' => "Dice count must be a positive integer, got: {$count}."];
    }
    if ($sides < 1) {
      return ['expression' => $expression, 'dice' => [], 'kept' => [], 'modifier' => 0, 'total' => 0,
        'error' => "Die sides must be at least 1."];
    }

    // Roll all dice.
    $rolls = [];
    for ($i = 0; $i < $count; $i++) {
      $rolls[] = random_int(1, $sides);
    }

    // Apply keep-highest / keep-lowest.
    $kept = $rolls;
    if ($keepSpec !== '') {
      $keepType = substr($keepSpec, 1, 1); // 'h' or 'l'
      $keepN    = (int) substr($keepSpec, 2);
      if ($keepN < 1 || $keepN > $count) {
        return ['expression' => $expression, 'dice' => $rolls, 'kept' => $rolls, 'modifier' => $modifier, 'total' => 0,
          'error' => "Keep count {$keepN} is out of range for {$count} dice."];
      }
      $sorted = $rolls;
      sort($sorted);
      $kept = $keepType === 'h'
        ? array_slice($sorted, -$keepN)
        : array_slice($sorted, 0, $keepN);
    }

    $total = array_sum($kept) + $modifier;
    $result = [
      'expression' => $expression,
      'dice'       => $rolls,
      'kept'       => $kept,
      'modifier'   => $modifier,
      'total'      => $total,
      'error'      => NULL,
    ];

    $this->logRoll($expression, $total, $characterId, $rollType);
    return $result;
  }

  /**
   * Log a roll to dc_roll_log (insert-only, immutable).
   *
   * @param string $expression  Dice expression rolled.
   * @param int $total          Final total result.
   * @param int|null $characterId  Optional character ID.
   * @param string $rollType    Context: attack/skill/damage/save/initiative/general.
   */
  public function logRoll(string $expression, int $total, ?int $characterId = NULL, string $rollType = 'general'): void {
    if ($this->database === NULL) {
      return;
    }
    try {
      $uid = $this->currentUser ? (int) $this->currentUser->id() : 0;
      $this->database->insert('dc_roll_log')
        ->fields([
          'expression'   => substr($expression, 0, 64),
          'total'        => $total,
          'character_id' => $characterId,
          'uid'          => $uid,
          'roll_type'    => substr($rollType, 0, 32),
          'created'      => \Drupal::time()->getRequestTime(),
        ])
        ->execute();
    }
    catch (\Exception $e) {
      // Non-fatal: log silently so a missing table never breaks gameplay.
      \Drupal::logger('dungeoncrawler_content')->warning('Roll log failed: @msg', ['@msg' => $e->getMessage()]);
    }
  }

}
