<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Headless harness for running an in-character player agent.
 */
class PlayerAgentHarnessService {

  protected PlayerAgentRuntimeAdapterInterface $runtimeAdapter;

  protected PlayerAgentExplorationPolicy $explorationPolicy;

  protected PlayerAgentEncounterPolicy $encounterPolicy;

  protected PlayerAgentProgressTracker $progressTracker;

  protected ?RoomChatService $roomChatService;

  public function __construct(
    PlayerAgentRuntimeAdapterInterface $runtime_adapter,
    PlayerAgentExplorationPolicy $exploration_policy,
    PlayerAgentEncounterPolicy $encounter_policy,
    PlayerAgentProgressTracker $progress_tracker,
    ?RoomChatService $room_chat_service = NULL
  ) {
    $this->runtimeAdapter = $runtime_adapter;
    $this->explorationPolicy = $exploration_policy;
    $this->encounterPolicy = $encounter_policy;
    $this->progressTracker = $progress_tracker;
    $this->roomChatService = $room_chat_service;
  }

  /**
   * Run a single player-agent step.
   */
  public function runStep(int $campaign_id, array $profile, array $run_state = []): array {
    $profile = $this->normalizeProfile($profile);
    $run_state = $this->normalizeRunState($run_state);
    $actor_id = (string) ($profile['actor_id'] ?? '');
    if ($actor_id === '') {
      return [
        'success' => FALSE,
        'error' => 'Player agent profile requires actor_id.',
        'run_state' => $run_state,
      ];
    }

    $snapshot = $this->runtimeAdapter->buildSnapshot($campaign_id, $actor_id, $run_state);
    if (empty($snapshot['success'])) {
      return [
        'success' => FALSE,
        'error' => (string) ($snapshot['error'] ?? 'Failed to build player-agent snapshot.'),
        'snapshot' => $snapshot,
        'run_state' => $run_state,
      ];
    }

    $policy = $this->resolvePolicy((string) ($snapshot['phase'] ?? 'exploration'));
    if ($policy === NULL) {
      return [
        'success' => FALSE,
        'error' => 'No player-agent policy for phase ' . ($snapshot['phase'] ?? 'unknown') . '.',
        'snapshot' => $snapshot,
        'run_state' => $run_state,
      ];
    }

    $decision = $policy->chooseAction($profile, $snapshot, $run_state);
    $decision = $this->maybeOverrideRestDecision($campaign_id, $profile, $snapshot, $run_state, $decision);
    $response = NULL;
    $this->logAutomationDecision($campaign_id, $profile, $snapshot, $run_state, $decision, 'pre_submit');

    if (($decision['type'] ?? 'wait') === 'intent') {
      $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
      $intent['client_state_version'] = $intent['client_state_version'] ?? ($snapshot['state_version'] ?? 1);
      $response = $this->runtimeAdapter->submitIntent($campaign_id, $intent);
      $this->logAutomationResponse($campaign_id, $profile, $snapshot, $decision, $response, 'initial_submit');

      if ($this->isStateVersionMismatch($response)) {
        $retry_snapshot = $this->runtimeAdapter->buildSnapshot($campaign_id, $actor_id, $run_state);
        if (!empty($retry_snapshot['success'])) {
          $snapshot = $retry_snapshot;
          $intent['client_state_version'] = $retry_snapshot['state_version'] ?? $intent['client_state_version'];
          $response = $this->runtimeAdapter->submitIntent($campaign_id, $intent);
          $this->logAutomationResponse($campaign_id, $profile, $snapshot, $decision, $response, 'retry_submit');
        }
      }
    }

    $run_state = $this->progressTracker->updateRunState(
      $profile,
      $snapshot,
      $decision,
      $response,
      $run_state
    );

    return [
      'success' => $response['success'] ?? TRUE,
      'profile' => $profile,
      'snapshot' => $snapshot,
      'decision' => $decision,
      'response' => $response,
      'run_state' => $run_state,
      'error' => $response['error'] ?? NULL,
      'stop_reason' => ($decision['type'] ?? '') === 'stop' ? (string) ($decision['reason'] ?? 'Automation paused.') : NULL,
    ];
  }

  /**
   * Emit a structured trace for the chosen automation decision.
   */
  protected function logAutomationDecision(int $campaign_id, array $profile, array $snapshot, array $run_state, array $decision, string $stage): void {
    $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
    $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
    \Drupal::logger('dungeoncrawler_player_agent')->info(
      'Player-agent decision @stage: campaign @campaign actor @actor room @room phase @phase type @type intent @intent target @target goal @goal reason "@reason" talked=@talked pending=@pending active=@active',
      [
        '@stage' => $stage,
        '@campaign' => $campaign_id,
        '@actor' => (string) ($profile['actor_id'] ?? ''),
        '@room' => (string) ($snapshot['active_room_id'] ?? ''),
        '@phase' => (string) ($snapshot['phase'] ?? ''),
        '@type' => (string) ($decision['type'] ?? 'wait'),
        '@intent' => (string) ($intent['type'] ?? ''),
        '@target' => (string) ($intent['target'] ?? ''),
        '@goal' => (string) ($params['automation_goal'] ?? ''),
        '@reason' => substr((string) ($decision['reason'] ?? ''), 0, 240),
        '@talked' => implode(',', array_slice(array_map('strval', (array) ($run_state['memory']['talked_entities'] ?? [])), -5)) ?: 'none',
        '@pending' => is_array($run_state['memory']['pending_conversation_lead'] ?? NULL)
          ? json_encode($run_state['memory']['pending_conversation_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
          : 'none',
        '@active' => is_array($run_state['memory']['active_npc_lead'] ?? NULL)
          ? json_encode($run_state['memory']['active_npc_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
          : 'none',
      ]
    );
  }

  /**
   * Emit a structured trace for the runtime adapter response.
   */
  protected function logAutomationResponse(int $campaign_id, array $profile, array $snapshot, array $decision, ?array $response, string $stage): void {
    $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
    $result = is_array($response['result'] ?? NULL) ? $response['result'] : [];
    \Drupal::logger('dungeoncrawler_player_agent')->info(
      'Player-agent response @stage: campaign @campaign actor @actor room @room intent @intent target @target success @success talked @talked searched @searched rested @rested error "@error" message "@message" gm "@gm"',
      [
        '@stage' => $stage,
        '@campaign' => $campaign_id,
        '@actor' => (string) ($profile['actor_id'] ?? ''),
        '@room' => (string) ($snapshot['active_room_id'] ?? ''),
        '@intent' => (string) ($intent['type'] ?? ''),
        '@target' => (string) ($intent['target'] ?? ''),
        '@success' => !empty($response['success']) ? 'yes' : 'no',
        '@talked' => !empty($result['talked']) ? 'yes' : 'no',
        '@searched' => !empty($result['searched']) ? 'yes' : 'no',
        '@rested' => !empty($result['rested']) ? 'yes' : 'no',
        '@error' => substr((string) ($response['error'] ?? ''), 0, 240),
        '@message' => substr((string) ($result['message'] ?? ''), 0, 240),
        '@gm' => substr((string) (($result['gm_response']['message'] ?? $result['narration'] ?? '')), 0, 240),
      ]
    );
  }

  /**
   * Run multiple sequential player-agent steps.
   */
  public function runSteps(int $campaign_id, array $profile, int $steps = 1, array $run_state = [], bool $stop_on_failure = TRUE): array {
    $steps = max(1, $steps);
    $run_state = $this->normalizeRunState($run_state);
    $results = [];
    $stop_reason = NULL;

    for ($index = 0; $index < $steps; $index++) {
      $step_result = $this->runStep($campaign_id, $profile, $run_state);
      $results[] = $step_result;
      $run_state = $step_result['run_state'] ?? $run_state;

      if ($stop_on_failure && empty($step_result['success'])) {
        $stop_reason = 'step_failure';
        break;
      }

      if (($run_state['guardrails']['consecutive_waits'] ?? 0) >= (int) ($run_state['guardrails']['max_consecutive_waits'] ?? 3)) {
        $stop_reason = 'max_consecutive_waits';
        break;
      }

      if (($run_state['guardrails']['consecutive_failures'] ?? 0) >= (int) ($run_state['guardrails']['max_consecutive_failures'] ?? 2)) {
        $stop_reason = 'max_consecutive_failures';
        break;
      }
    }

    return [
      'success' => !empty($results) ? !empty(end($results)['success']) : TRUE,
      'results' => $results,
      'run_state' => $run_state,
      'stop_reason' => $stop_reason,
    ];
  }

  /**
   * Normalize a player-agent profile to safe defaults.
   */
  protected function normalizeProfile(array $profile): array {
    $profile['goals'] = is_array($profile['goals'] ?? NULL)
      ? $profile['goals']
      : ['explore', 'gain_xp', 'level_up'];
    $profile['persona'] = is_array($profile['persona'] ?? NULL) ? $profile['persona'] : [];
    $profile['persona']['tone'] = (string) ($profile['persona']['tone'] ?? 'curious');
    $profile['combat_loadout'] = is_array($profile['combat_loadout'] ?? NULL) ? $profile['combat_loadout'] : [];

    return $profile;
  }

  /**
   * Normalize run-state defaults and guardrails.
   */
  protected function normalizeRunState(array $run_state): array {
    $run_state['memory'] = is_array($run_state['memory'] ?? NULL) ? $run_state['memory'] : [];
    $run_state['progress'] = is_array($run_state['progress'] ?? NULL) ? $run_state['progress'] : [];
    $run_state['trace'] = is_array($run_state['trace'] ?? NULL) ? $run_state['trace'] : [];
    $run_state['guardrails'] = is_array($run_state['guardrails'] ?? NULL) ? $run_state['guardrails'] : [];
    $run_state['guardrails']['max_consecutive_waits'] = (int) ($run_state['guardrails']['max_consecutive_waits'] ?? 3);
    $run_state['guardrails']['max_consecutive_failures'] = (int) ($run_state['guardrails']['max_consecutive_failures'] ?? 2);
    $run_state['guardrails']['consecutive_waits'] = (int) ($run_state['guardrails']['consecutive_waits'] ?? 0);
    $run_state['guardrails']['consecutive_failures'] = (int) ($run_state['guardrails']['consecutive_failures'] ?? 0);

    return $run_state;
  }

  /**
   * Detect a canonical state-version mismatch error response.
   */
  protected function isStateVersionMismatch(?array $response): bool {
    if (!is_array($response) || !empty($response['success'])) {
      return FALSE;
    }

    $error = strtolower((string) ($response['error'] ?? ''));
    return str_contains($error, 'state version mismatch');
  }

  /**
   * Resolve the correct phase policy.
   */
  protected function resolvePolicy(string $phase): ?PlayerAgentPolicyInterface {
    foreach ([$this->explorationPolicy, $this->encounterPolicy] as $policy) {
      if ($policy->supportsPhase($phase)) {
        return $policy;
      }
    }
    return NULL;
  }

  /**
   * Replace deterministic rest selections with an analysis-backed decision.
   */
  protected function maybeOverrideRestDecision(int $campaign_id, array $profile, array $snapshot, array $run_state, array $decision): array {
    if (($snapshot['phase'] ?? '') !== 'exploration' || !$this->roomChatService) {
      return $decision;
    }
    if (($decision['type'] ?? '') !== 'intent' || (string) (($decision['intent']['type'] ?? '')) !== 'rest') {
      return $decision;
    }

    $character_id = (int) ($profile['character_id'] ?? 0);
    $room_id = (string) ($snapshot['active_room_id'] ?? '');
    if ($campaign_id <= 0 || $character_id <= 0 || $room_id === '') {
      return $decision;
    }

    try {
      return $this->roomChatService->suggestPlayerAutomationFallbackDecision(
        $campaign_id,
        $room_id,
        $character_id,
        $snapshot,
        $run_state
      );
    }
    catch (\Throwable $exception) {
      return [
        'type' => 'wait',
        'reason' => 'Fallback rest analysis failed: ' . $exception->getMessage(),
        'decision_meta' => [
          'stage' => 'rest_analysis_error',
          'priority' => 14,
          'room_id' => $room_id,
          'analysis_fallback' => TRUE,
        ],
      ];
    }
  }

}
