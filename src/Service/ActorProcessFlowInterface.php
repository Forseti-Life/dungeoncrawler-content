<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Contract for deterministic actor process-flow planners.
 */
interface ActorProcessFlowInterface {

  /**
   * Return the canonical flow id.
   */
  public function id(): string;

  /**
   * Return the selection priority; lower runs first.
   */
  public function priority(): int;

  /**
   * Determine whether this flow is eligible for the current context.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   * @param array<string,mixed> $run_state
   * @param array<string,mixed> $context
   */
  public function supports(array $profile, array $snapshot, array $run_state, array $context = []): bool;

  /**
   * Return a deterministic harness decision when this flow can decide.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   * @param array<string,mixed> $run_state
   * @param array<string,mixed> $context
   *
   * @return array<string,mixed>|null
   *   Harness decision payload or NULL when unresolved.
   */
  public function decide(array $profile, array $snapshot, array $run_state, array $context = []): ?array;

}
