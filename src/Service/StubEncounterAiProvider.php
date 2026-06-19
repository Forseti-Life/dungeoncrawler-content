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
    $current_actor_ref = $this->resolveActorId($current_actor);
    $target = $this->findFirstAlivePlayer($participants);
    $target_ref = $target !== NULL ? $this->resolveActorId($target) : '';

    $action_type = $target !== NULL ? 'strike' : 'end_turn';
    $rationale = $target !== NULL
      ? 'Selected first available alive player target for deterministic preview.'
      : 'No valid player target available; fallback to end turn.';
    $decision_reason = $target !== NULL
      ? 'Deterministic fallback focuses first alive player target.'
      : 'No valid player target available.';

    return [
      'version' => 'v1',
      'provider' => $this->getProviderName(),
      'actor_instance_id' => $current_actor_ref,
      'recommended_action' => [
        'type' => $action_type,
        'target_instance_id' => $target_ref !== '' ? $target_ref : NULL,
        'action_cost' => $action_type === 'end_turn' ? 0 : 1,
        'parameters' => [
          'weapon' => 'basic_attack',
        ],
      ],
      'alternatives' => [],
      'rationale' => $rationale,
      'decision_reason' => $decision_reason,
      'decision_basis' => [
        'intent' => $target !== NULL ? 'aggressive_engage' : 'no_targets',
        'target_selection' => $target !== NULL ? 'first_alive_player' : 'none',
        'deterministic' => TRUE,
      ],
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

  /**
   * Resolve a stable actor id from preview/current actor payloads.
   */
  private function resolveActorId(array $actor): string {
    $entity_id = trim((string) ($actor['entity_id'] ?? ''));
    if ($entity_id !== '') {
      return $entity_id;
    }

    $entity_ref = $actor['entity_ref'] ?? NULL;
    if (is_string($entity_ref)) {
      $decoded = json_decode($entity_ref, TRUE);
      if (is_array($decoded)) {
        $content_id = trim((string) ($decoded['content_id'] ?? ''));
        if ($content_id !== '') {
          return $content_id;
        }
      }
      return trim($entity_ref);
    }

    if (is_array($entity_ref)) {
      $content_id = trim((string) ($entity_ref['content_id'] ?? ''));
      if ($content_id !== '') {
        return $content_id;
      }
    }

    return '';
  }

}
