<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical coordinator for mode-aware actor process-flow selection.
 */
class ActorProcessFlowCoordinatorService {

  public function __construct(
    protected readonly ?ProcessFlowStateStoreService $processFlowStateStoreService = NULL,
    protected readonly ?ProcessFlowEventStoreService $processFlowEventStoreService = NULL,
  ) {}

  /**
   * Select one active flow envelope from a resolved stance + context.
   *
   * @param array<string, mixed> $stance
   *   Canonical stance envelope.
   * @param array<string, mixed> $context
   *   Runtime flow selection context.
   *
   * @return array<string, mixed>
   *   Canonical process-flow envelope.
   */
  public function selectActiveFlow(int $campaign_id, string $actor_ref, array $stance, array $context = []): array {
    $actor_ref = trim($actor_ref);
    $mode = $this->normalizeMode((string) ($stance['mode'] ?? $context['mode'] ?? 'room'));
    $stance_name = strtolower(trim((string) ($stance['stance'] ?? 'observe')));
    $trigger = trim((string) ($context['trigger'] ?? 'stance_update'));
    if ($trigger === '') {
      $trigger = 'stance_update';
    }

    $eligible_flows = $this->resolveEligibleFlows($stance_name, $mode, $context);
    $active_flow = $this->selectByPrecedence($eligible_flows, $mode);
    $blocking_map = is_array($context['flow_blockers'] ?? NULL) ? $context['flow_blockers'] : [];
    $blocking_conditions = [];
    if ($active_flow === '') {
      $active_flow = $mode === 'encounter' ? 'encounter-turn-flow' : 'room-scene-pass-flow';
      $blocking_conditions[] = 'no_eligible_flow_for_mode';
    }
    elseif (is_array($blocking_map[$active_flow] ?? NULL)) {
      $blocking_conditions = array_values(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        (array) $blocking_map[$active_flow]
      )));
    }

    $previous_state = $this->processFlowStateStoreService instanceof ProcessFlowStateStoreService
      ? $this->processFlowStateStoreService->loadLatestState($campaign_id, $actor_ref)
      : NULL;
    $previous_flow = is_array($previous_state['summary'] ?? NULL)
      ? (string) (($previous_state['summary']['active_flow'] ?? '') ?: '')
      : '';

    $selection_reason = $this->buildSelectionReason($active_flow, $stance_name, $mode, $blocking_conditions);
    $envelope = [
      'contract_version' => 'actor_process_flow_contract_v1',
      'actor_ref' => $actor_ref,
      'campaign_id' => $campaign_id,
      'mode' => $mode,
      'stance' => $stance_name,
      'active_flow' => $active_flow,
      'trigger' => $trigger,
      'entered_at' => gmdate('c'),
      'handoff_ready' => in_array($active_flow, ['room-ambient-observer-flow', 'room-scene-pass-flow'], TRUE),
      'metadata' => [
        'selection_reason' => $selection_reason,
        'selected_by' => 'ActorProcessFlowCoordinator',
        'previous_flow' => $previous_flow !== '' ? $previous_flow : NULL,
        'executor_requirements' => $this->resolveExecutorRequirements($active_flow, $mode),
        'target_actor_ref' => (string) ($stance['target_actor_ref'] ?? ''),
        'blocking_conditions' => $blocking_conditions,
      ],
    ];

    if ($this->processFlowStateStoreService instanceof ProcessFlowStateStoreService && $campaign_id > 0 && $actor_ref !== '') {
      $this->processFlowStateStoreService->storeLatestState($campaign_id, $actor_ref, [
        'mode' => $mode,
        'stance' => $stance_name,
        'active_flow' => $active_flow,
        'trigger' => $trigger,
        'entered_at' => (string) ($envelope['entered_at'] ?? ''),
        'metadata' => is_array($envelope['metadata'] ?? NULL) ? $envelope['metadata'] : [],
      ], [
        'source_type' => 'actor_process_flow_coordinator',
        'source_id' => 'actor_process_flow_contract_v1',
      ]);
    }
    if ($this->processFlowEventStoreService instanceof ProcessFlowEventStoreService && $campaign_id > 0 && $actor_ref !== '') {
      $this->processFlowEventStoreService->recordProcessFlowEvent($campaign_id, $actor_ref, [
        'event_type' => 'actor_process_flow_selected',
        'flow_id' => $active_flow,
        'summary' => [
          'mode' => $mode,
          'stance' => $stance_name,
          'trigger' => $trigger,
        ],
        'context' => [
          'selection_reason' => $selection_reason,
          'target_actor_ref' => (string) ($stance['target_actor_ref'] ?? ''),
          'blocking_conditions' => $blocking_conditions,
        ],
      ]);
    }

    return $envelope;
  }

  /**
   * Resolve candidate flows from stance, mode, and override context.
   *
   * @param array<string, mixed> $context
   *   Selection context.
   *
   * @return array<int, string>
   *   Candidate flows.
   */
  protected function resolveEligibleFlows(string $stance, string $mode, array $context): array {
    if (!empty($context['scripted_scene_required'])) {
      return ['scripted-scene-flow'];
    }

    $candidates = match ($mode) {
      'scripted_scene' => ['scripted-scene-flow'],
      'encounter' => ['encounter-turn-flow'],
      'combat_entry' => $this->resolveCombatEntryCandidates($stance, $context),
      default => $this->resolveRoomCandidates($stance, $context),
    };

    return array_values(array_unique(array_filter(array_map(
      static fn($value): string => strtolower(trim((string) $value)),
      $candidates
    ))));
  }

  /**
   * Resolve room-mode candidates.
   *
   * @param array<string, mixed> $context
   *   Selection context.
   *
   * @return array<int, string>
   *   Candidate flows.
   */
  protected function resolveRoomCandidates(string $stance, array $context): array {
    $quest_brokered = !empty($context['quest_brokered_dialogue']);
    $aggression_gate = !empty($context['combat_entry_threshold_gate']);

    return match ($stance) {
      'engage_dialogue', 'deescalate', 'warn' => $quest_brokered
        ? ['quest-brokered-dialogue-flow', 'room-dialogue-flow']
        : ['room-dialogue-flow'],
      'threaten' => $aggression_gate
        ? ['combat-entry-flow', 'room-dialogue-flow']
        : ['room-dialogue-flow'],
      'self_preserve', 'pass', 'flee' => ['room-scene-pass-flow'],
      'aggressive_engage', 'finish_weakest' => ['combat-entry-flow'],
      default => ['room-ambient-observer-flow', 'room-scene-pass-flow'],
    };
  }

  /**
   * Resolve combat-entry-mode candidates.
   *
   * @param array<string, mixed> $context
   *   Selection context.
   *
   * @return array<int, string>
   *   Candidate flows.
   */
  protected function resolveCombatEntryCandidates(string $stance, array $context): array {
    $threshold_gate = !empty($context['combat_entry_threshold_gate']) || !empty($context['explicit_attack_declared']);
    return match ($stance) {
      'aggressive_engage', 'finish_weakest' => ['combat-entry-flow'],
      'threaten', 'warn' => $threshold_gate
        ? ['combat-entry-flow', 'room-dialogue-flow']
        : ['room-dialogue-flow', 'room-scene-pass-flow'],
      'self_preserve', 'flee', 'pass' => ['room-scene-pass-flow'],
      default => ['room-dialogue-flow', 'room-scene-pass-flow'],
    };
  }

  /**
   * Select highest-precedence flow by mode.
   *
   * @param array<int, string> $eligible
   *   Candidate flow names.
   */
  protected function selectByPrecedence(array $eligible, string $mode): string {
    if ($eligible === []) {
      return '';
    }

    $precedence = match ($mode) {
      'encounter' => [
        'scripted-scene-flow',
        'encounter-turn-flow',
      ],
      'combat_entry' => [
        'scripted-scene-flow',
        'combat-entry-flow',
        'room-dialogue-flow',
        'room-scene-pass-flow',
      ],
      default => [
        'scripted-scene-flow',
        'quest-brokered-dialogue-flow',
        'room-dialogue-flow',
        'room-ambient-observer-flow',
        'room-scene-pass-flow',
      ],
    };

    foreach ($precedence as $flow) {
      if (in_array($flow, $eligible, TRUE)) {
        return $flow;
      }
    }
    return '';
  }

  /**
   * Resolve minimum executor requirements for one flow.
   *
   * @return array<string, mixed>
   *   Requirement summary.
   */
  protected function resolveExecutorRequirements(string $flow, string $mode): array {
    return match ($flow) {
      'room-dialogue-flow' => [
        'chat_allowed' => TRUE,
        'room_context_required' => TRUE,
      ],
      'room-ambient-observer-flow' => [
        'room_context_required' => TRUE,
        'primary_responder_required' => FALSE,
      ],
      'room-scene-pass-flow' => [
        'no_meaningful_action_required' => TRUE,
      ],
      'combat-entry-flow' => [
        'aggression_lane_available' => TRUE,
        'combat_entry_mode' => $mode === 'combat_entry' || $mode === 'room',
      ],
      'encounter-turn-flow' => [
        'active_encounter_required' => TRUE,
      ],
      'scripted-scene-flow' => [
        'scripted_constraints_required' => TRUE,
      ],
      'quest-brokered-dialogue-flow' => [
        'quest_broker_required' => TRUE,
      ],
      default => [
        'known_flow' => FALSE,
      ],
    };
  }

  /**
   * Build deterministic human-readable selection reason.
   *
   * @param array<int, string> $blocking_conditions
   *   Blocking conditions.
   */
  protected function buildSelectionReason(string $flow, string $stance, string $mode, array $blocking_conditions): string {
    if ($blocking_conditions !== []) {
      return sprintf(
        'Flow selected with blocking conditions (%s) for stance=%s mode=%s.',
        implode(', ', $blocking_conditions),
        $stance,
        $mode
      );
    }
    return sprintf('Selected %s from stance=%s mode=%s via precedence routing.', $flow, $stance, $mode);
  }

  /**
   * Normalize mode.
   */
  protected function normalizeMode(string $mode): string {
    $mode = strtolower(trim($mode));
    return in_array($mode, ['room', 'combat_entry', 'encounter', 'scripted_scene'], TRUE)
      ? $mode
      : 'room';
  }

}
