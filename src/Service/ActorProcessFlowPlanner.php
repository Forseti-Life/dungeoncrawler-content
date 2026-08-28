<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Orchestrates deterministic actor process flows before LLM fallback lanes.
 */
class ActorProcessFlowPlanner {

  /**
   * @param array<int,\Drupal\dungeoncrawler_content\Service\ActorProcessFlowInterface> $flows
   *   Registered deterministic flows.
   */
  public function __construct(
    protected readonly ActorProcessFlowArchetypeResolver $archetypeResolver,
    protected readonly array $flows,
    protected readonly ?ProcessFlowStateStoreService $processFlowStateStoreService = NULL,
  ) {}

  /**
   * Resolve ordered archetype tags for one actor.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   *
   * @return array<int,string>
   *   Ordered archetype list.
   */
  public function resolveArchetypes(array $profile, array $snapshot): array {
    return $this->archetypeResolver->resolveArchetypes($profile, $snapshot);
  }

  /**
   * Plan a deterministic harness decision when one is available.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   * @param array<string,mixed> $run_state
   * @param array<string,mixed> $options
   *
   * @return array<string,mixed>|null
   *   Harness decision or NULL.
   */
  public function planDecision(array $profile, array $snapshot, array $run_state = [], array $options = []): ?array {
    $context = [
      'phase' => (string) ($snapshot['phase'] ?? 'exploration'),
      'planner_mode' => (string) ($options['planner_mode'] ?? 'harness'),
      'archetypes' => $this->resolveArchetypes($profile, $snapshot),
      'persist_state' => array_key_exists('persist_state', $options)
        ? !empty($options['persist_state'])
        : (string) ($options['planner_mode'] ?? 'harness') === 'harness',
    ];

    $flows = array_values(array_filter($this->flows, static fn($flow): bool => $flow instanceof ActorProcessFlowInterface));
    usort($flows, static fn(ActorProcessFlowInterface $a, ActorProcessFlowInterface $b): int => $a->priority() <=> $b->priority());

    foreach ($flows as $flow) {
      if (!$flow->supports($profile, $snapshot, $run_state, $context)) {
        continue;
      }

      $decision = $flow->decide($profile, $snapshot, $run_state, $context);
      if (!is_array($decision) || $decision === []) {
        continue;
      }

      $decision = $this->decorateDecision($decision, $flow, $context);
      $this->persistPlannerState($profile, $snapshot, $decision, $flow, $context);
      return $decision;
    }

    return NULL;
  }

  /**
   * Decorate a flow decision with planner metadata.
   *
   * @param array<string,mixed> $decision
   * @param array<string,mixed> $context
   *
   * @return array<string,mixed>
   *   Decorated decision.
   */
  protected function decorateDecision(array $decision, ActorProcessFlowInterface $flow, array $context): array {
    $existing = is_array($decision['decision_meta'] ?? NULL) ? $decision['decision_meta'] : [];
    $decision['decision_meta'] = array_filter([
      'flow_id' => $flow->id(),
      'planner_mode' => (string) ($context['planner_mode'] ?? 'harness'),
      'phase' => (string) ($context['phase'] ?? ''),
      'archetype' => (string) (($context['archetypes'][0] ?? 'default')),
      'archetypes' => is_array($context['archetypes'] ?? NULL) ? $context['archetypes'] : ['default'],
      'deterministic' => TRUE,
    ] + $existing, static fn($value): bool => $value !== NULL && $value !== '');
    return $decision;
  }

  /**
   * Persist the selected flow for downstream runtime inspection.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   * @param array<string,mixed> $decision
   * @param array<string,mixed> $context
   */
  protected function persistPlannerState(array $profile, array $snapshot, array $decision, ActorProcessFlowInterface $flow, array $context): void {
    if (empty($context['persist_state'])) {
      return;
    }
    if (!$this->processFlowStateStoreService instanceof ProcessFlowStateStoreService) {
      return;
    }

    $campaign_id = (int) ($snapshot['campaign_id'] ?? 0);
    $actor_ref = $this->resolveActorRef($snapshot, $profile);
    if ($campaign_id <= 0 || $actor_ref === '') {
      return;
    }

    $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
    $this->processFlowStateStoreService->storeLatestState($campaign_id, $actor_ref, [
      'mode' => (string) (($context['phase'] ?? '') === 'encounter' ? 'encounter' : 'room'),
      'stance' => (string) (($context['archetypes'][0] ?? 'default')),
      'active_flow' => $flow->id(),
      'trigger' => (string) (($context['planner_mode'] ?? 'harness') . '_deterministic_planner'),
      'entered_at' => gmdate('c'),
      'metadata' => [
        'planner_mode' => (string) ($context['planner_mode'] ?? 'harness'),
        'decision_type' => (string) ($decision['type'] ?? ''),
        'intent_type' => (string) ($intent['type'] ?? ''),
        'reason' => (string) ($decision['reason'] ?? ''),
        'archetypes' => is_array($context['archetypes'] ?? NULL) ? $context['archetypes'] : ['default'],
      ],
    ], [
      'source_type' => 'actor_process_flow_planner',
      'source_id' => $flow->id(),
    ]);
  }

  /**
   * Resolve a stable actor-ref key for persisted planner state.
   *
   * @param array<string,mixed> $snapshot
   * @param array<string,mixed> $profile
   */
  protected function resolveActorRef(array $snapshot, array $profile): string {
    $actor_entity = is_array($snapshot['actor_entity'] ?? NULL) ? $snapshot['actor_entity'] : [];
    $entity_ref = is_array($actor_entity['entity_ref'] ?? NULL) ? $actor_entity['entity_ref'] : [];
    $content_type = trim((string) ($entity_ref['content_type'] ?? ''));
    $content_id = trim((string) ($entity_ref['content_id'] ?? ''));
    if ($content_type !== '' && $content_id !== '') {
      return $content_type . ':' . $content_id;
    }
    if ($content_id !== '') {
      return $content_id;
    }
    return trim((string) ($profile['actor_id'] ?? $snapshot['actor_id'] ?? ''));
  }

}
