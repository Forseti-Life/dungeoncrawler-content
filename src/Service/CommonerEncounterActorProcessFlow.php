<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic encounter flow for low-agency civilian/commoner actors.
 */
class CommonerEncounterActorProcessFlow implements ActorProcessFlowInterface {

  public function __construct(
    protected readonly ActorProcessFlowIntentHelper $intentHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'default_commoner_encounter';
  }

  /**
   * {@inheritdoc}
   */
  public function priority(): int {
    return 30;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(array $profile, array $snapshot, array $run_state, array $context = []): bool {
    if (($context['phase'] ?? ($snapshot['phase'] ?? '')) !== 'encounter') {
      return FALSE;
    }
    if (!$this->intentHelper->isActorsTurn($profile, $snapshot)) {
      return FALSE;
    }
    $archetypes = is_array($context['archetypes'] ?? NULL) ? $context['archetypes'] : [];
    return in_array('commoner', $archetypes, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function decide(array $profile, array $snapshot, array $run_state, array $context = []): ?array {
    $process_flow = is_array($profile['process_flow'] ?? NULL) ? $profile['process_flow'] : [];
    return $this->intentHelper->chooseWarningTalkAction(
      $profile,
      $snapshot,
      (string) ($process_flow['commoner_warning'] ?? 'Stay back! I am not looking for a fight!'),
      'commoner_warning',
      20
    ) ?? $this->intentHelper->chooseTurnClosureAction(
      $profile,
      $snapshot,
      'The commoner flow avoids direct combat when no obvious safe action exists.',
      'Default commoner flow found no safe deterministic action beyond de-escalation.',
      'commoner_turn_close',
      90
    );
  }

}
