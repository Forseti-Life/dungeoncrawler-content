<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Typed mutation-lane entrypoints for phase handler orchestration.
 */
interface MutationContextPhaseHandlerInterface extends PhaseHandlerInterface {

  /**
   * Process one action intent using typed mutation execution context.
   */
  public function processIntentWithMutationContext(
    array $intent,
    RuntimeMutationExecutionContext $mutation_context,
    int $campaign_id
  ): array;

  /**
   * Enter one phase using typed mutation execution context.
   */
  public function onEnterWithMutationContext(
    array $context,
    RuntimeMutationExecutionContext $mutation_context,
    int $campaign_id
  ): array;

  /**
   * Exit one phase using typed mutation execution context.
   */
  public function onExitWithMutationContext(
    RuntimeMutationExecutionContext $mutation_context,
    int $campaign_id
  ): array;

}
