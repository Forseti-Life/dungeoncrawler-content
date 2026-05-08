<?php

namespace Drupal\dungeoncrawler_content\Service;

use InvalidArgumentException;

/**
 * Deterministic pseudo-random sequence based on seed and counter.
 */
class SeededRandomSequence {

  /**
   * Seed used for deterministic sequence generation.
   */
  protected int $seed;

  /**
   * Sequence cursor.
   */
  protected int $counter = 0;

  /**
   * Constructs a deterministic random sequence.
   */
  public function __construct(int $seed) {
    $this->seed = $seed;
  }

  /**
   * Generate next random integer in inclusive range.
   */
  public function nextInt(int $minimum, int $maximum): int {
    if ($minimum > $maximum) {
      throw new InvalidArgumentException('Minimum must be less than or equal to maximum.');
    }

    if ($minimum === $maximum) {
      return $minimum;
    }

    $span = $maximum - $minimum + 1;
    $value = $this->nextUInt32() % $span;
    return $minimum + $value;
  }

  /**
   * Return true when a percentile chance succeeds.
   */
  public function chance(int $percent): bool {
    $percent = max(0, min(100, $percent));
    if ($percent === 0) {
      return FALSE;
    }
    if ($percent === 100) {
      return TRUE;
    }

    return $this->nextInt(1, 100) <= $percent;
  }

  /**
   * Select one random item from a non-empty list.
   *
   * @template T
   *
   * @param array $items
   *   Input list.
   *
   * @return mixed
   *   Selected item.
   */
  public function pick(array $items): mixed {
    if (empty($items)) {
      throw new InvalidArgumentException('Cannot pick from an empty array.');
    }

    $index = $this->nextInt(0, count($items) - 1);
    return $items[$index];
  }

  /**
   * Generate deterministic 32-bit integer from seed and counter.
   */
  protected function nextUInt32(): int {
    $payload = $this->seed . ':' . $this->counter;
    $digest = hash('sha256', $payload, TRUE);
    $this->counter++;

    $parts = unpack('Nvalue', substr($digest, 0, 4));
    return (int) ($parts['value'] ?? 0);
  }

}
