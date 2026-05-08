<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic encounter AI provider for read-only integration scaffolding.
 */
class StubEncounterAiProvider implements EncounterAiProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function recommendNpcAction(array $context): array {
    $participants = is_array($context['participants'] ?? NULL) ? $context['participants'] : [];
    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];
    $current_actor_ref = (string) ($current_actor['entity_ref'] ?? $current_actor['entity_id'] ?? '');
    $target = $this->findFirstAlivePlayer($participants);
    $target_ref = $target !== NULL ? (string) ($target['entity_ref'] ?? $target['entity_id'] ?? '') : '';

    $action_type = $target !== NULL ? 'strike' : 'end_turn';
    $rationale = $target !== NULL
      ? 'Selected first available alive player target for deterministic preview.'
      : 'No valid player target available; fallback to end turn.';

    return [
      'version' => 'v1',
      'provider' => $this->getProviderName(),
      'actor_instance_id' => $current_actor_ref,
      'recommended_action' => [
        'type' => $action_type,
        'target_instance_id' => $target_ref !== '' ? $target_ref : NULL,
        'action_cost' => 1,
        'parameters' => [
          'weapon' => 'basic_attack',
        ],
      ],
      'alternatives' => [],
      'rationale' => $rationale,
      'confidence' => $target !== NULL ? 0.6 : 0.4,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function generateEncounterNarration(array $context): array {
    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];
    $actor_name = (string) ($current_actor['name'] ?? 'Unknown combatant');
    $round = (int) ($context['current_round'] ?? 1);

    return [
      'provider' => $this->getProviderName(),
      'round' => $round,
      'narration' => sprintf('Round %d: %s studies the battlefield and prepares a measured move.', $round, $actor_name),
      'style' => 'neutral-tactical',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderName(): string {
    return 'stub';
  }

  /**
   * Locate first alive player target.
   *
   * @param array<int, array<string, mixed>> $participants
   *   Encounter participants.
   *
   * @return array<string, mixed>|null
   *   Target row or NULL.
   */
  private function findFirstAlivePlayer(array $participants): ?array {
    foreach ($participants as $participant) {
      if (($participant['team'] ?? NULL) === 'player' && empty($participant['is_defeated'])) {
        return $participant;
      }
    }

    return NULL;
  }

}
