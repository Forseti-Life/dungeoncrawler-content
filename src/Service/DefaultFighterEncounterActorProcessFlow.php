<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic encounter flow for obvious fighter/default-melee actors.
 */
class DefaultFighterEncounterActorProcessFlow implements ActorProcessFlowInterface {

  public function __construct(
    protected readonly ActorProcessFlowIntentHelper $intentHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'default_fighter_encounter';
  }

  /**
   * {@inheritdoc}
   */
  public function priority(): int {
    return 20;
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
    return in_array('fighter', $archetypes, TRUE) || in_array('default_melee', $archetypes, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function decide(array $profile, array $snapshot, array $run_state, array $context = []): ?array {
    return $this->intentHelper->chooseBattleCryAction($profile, $snapshot, $run_state, 'fighter_battle_cry', 20)
      ?? $this->intentHelper->chooseWeaponStrikeAction($profile, $snapshot, 'fighter_strike', 30)
      ?? $this->intentHelper->chooseAdvanceTowardHostileAction($profile, $snapshot, 'fighter_advance', 40)
      ?? $this->intentHelper->chooseTurnClosureAction(
        $profile,
        $snapshot,
        'No further deterministic fighter action was legal this turn.',
        'Default fighter flow found no additional legal attack or advance action.',
        'fighter_turn_close',
        90
      );
  }

}
