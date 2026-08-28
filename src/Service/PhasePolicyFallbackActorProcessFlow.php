<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Harness-only fallback flow that delegates to existing phase policies.
 */
class PhasePolicyFallbackActorProcessFlow implements ActorProcessFlowInterface {

  public function __construct(
    protected readonly PlayerAgentExplorationPolicy $explorationPolicy,
    protected readonly PlayerAgentEncounterPolicy $encounterPolicy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'phase_policy_fallback';
  }

  /**
   * {@inheritdoc}
   */
  public function priority(): int {
    return 1000;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(array $profile, array $snapshot, array $run_state, array $context = []): bool {
    return (string) ($context['planner_mode'] ?? 'harness') === 'harness';
  }

  /**
   * {@inheritdoc}
   */
  public function decide(array $profile, array $snapshot, array $run_state, array $context = []): ?array {
    $phase = (string) ($context['phase'] ?? ($snapshot['phase'] ?? ''));
    $policy = match ($phase) {
      'encounter' => $this->encounterPolicy,
      'exploration' => $this->explorationPolicy,
      default => NULL,
    };
    if (!$policy instanceof PlayerAgentPolicyInterface) {
      return NULL;
    }
    return $policy->chooseAction($profile, $snapshot, $run_state);
  }

}
