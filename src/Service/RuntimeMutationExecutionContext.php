<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Typed execution context for mutation-lane phase handler operations.
 */
final class RuntimeMutationExecutionContext {

  /**
   * @param array<string,mixed> $gameState
   * @param array<string,mixed> $dungeonData
   */
  public function __construct(
    public array $gameState,
    public array $dungeonData,
  ) {}

}
