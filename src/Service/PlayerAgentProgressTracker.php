<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Tracks progress, memory, and observability for player-agent runs.
 */
class PlayerAgentProgressTracker {

  protected SessionService $sessionService;

  public function __construct(SessionService $session_service) {
    $this->sessionService = $session_service;
  }

  /**
   * Update the run state after a step attempt.
   */
  public function updateRunState(array $profile, array $snapshot, array $decision, ?array $response, array $run_state): array {
    $run_state['step_count'] = (int) ($run_state['step_count'] ?? 0) + 1;
    $run_state['memory'] = is_array($run_state['memory'] ?? NULL) ? $run_state['memory'] : [];
    $run_state['progress'] = is_array($run_state['progress'] ?? NULL) ? $run_state['progress'] : [];
    $run_state['trace'] = is_array($run_state['trace'] ?? NULL) ? $run_state['trace'] : [];

    $room_id = (string) ($snapshot['active_room_id'] ?? '');
    if ($room_id !== '' && !in_array($room_id, $run_state['memory']['visited_rooms'] ?? [], TRUE)) {
      $run_state['memory']['visited_rooms'][] = $room_id;
    }

    $decision_type = (string) ($decision['type'] ?? 'wait');
    if ($decision_type === 'intent') {
      $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
      $intent_type = (string) ($intent['type'] ?? '');

      if ($intent_type === 'search' && $room_id !== '') {
        $run_state['memory']['searched_rooms'] = array_values(array_unique(array_merge(
          $run_state['memory']['searched_rooms'] ?? [],
          [$room_id]
        )));
      }

      if ($intent_type === 'talk' && !empty($intent['target'])) {
        $run_state['memory']['talked_entities'] = array_values(array_unique(array_merge(
          $run_state['memory']['talked_entities'] ?? [],
          [(string) $intent['target']]
        )));

        $automation_goal = (string) ($intent['params']['automation_goal'] ?? '');
        if ($automation_goal === 'paid_work_fallback' && ($response['success'] ?? FALSE) && $room_id !== '') {
          $run_state['memory']['consulted_rooms'] = array_values(array_unique(array_merge(
            $run_state['memory']['consulted_rooms'] ?? [],
            [$room_id]
          )));
        }
      }

      if ($intent_type === 'talk' && !empty($snapshot['game_state']['encounter_id'])) {
        $run_state['memory']['encounter_battle_cries'][(string) $snapshot['game_state']['encounter_id']] = TRUE;
      }

      if ($intent_type === 'transition' && ($response['success'] ?? FALSE)) {
        $target_room_id = (string) ($intent['params']['target_room_id'] ?? '');
        if ($target_room_id === '') {
          foreach ($response['events'] ?? [] as $event) {
            if (($event['type'] ?? '') === 'room_entered') {
              $target_room_id = (string) ($event['data']['to_room'] ?? '');
              break;
            }
          }
        }

        if ($target_room_id !== '' && !in_array($target_room_id, $run_state['memory']['visited_rooms'] ?? [], TRUE)) {
          $run_state['memory']['visited_rooms'][] = $target_room_id;
        }
      }

      if ($intent_type === 'rest' && ($response['success'] ?? FALSE) && $room_id !== '') {
        $run_state['memory']['rested_rooms'] = array_values(array_unique(array_merge(
          $run_state['memory']['rested_rooms'] ?? [],
          [$room_id]
        )));
      }
    }

    $run_state['progress']['successful_actions'] = (int) ($run_state['progress']['successful_actions'] ?? 0)
      + (($response['success'] ?? FALSE) ? 1 : 0);
    $run_state['progress']['failed_actions'] = (int) ($run_state['progress']['failed_actions'] ?? 0)
      + (($response !== NULL && empty($response['success'])) ? 1 : 0);
    $run_state['progress']['wait_actions'] = (int) ($run_state['progress']['wait_actions'] ?? 0)
      + ($decision_type === 'wait' ? 1 : 0);

    if ($decision_type === 'wait') {
      $run_state['guardrails']['consecutive_waits'] = (int) ($run_state['guardrails']['consecutive_waits'] ?? 0) + 1;
    }
    else {
      $run_state['guardrails']['consecutive_waits'] = 0;
    }

    if ($response !== NULL && empty($response['success'])) {
      $run_state['guardrails']['consecutive_failures'] = (int) ($run_state['guardrails']['consecutive_failures'] ?? 0) + 1;
    }
    else {
      $run_state['guardrails']['consecutive_failures'] = 0;
    }

    if (!empty($response['phase_transition']['to']) && $response['phase_transition']['to'] === 'encounter') {
      $run_state['progress']['encounters_started'] = (int) ($run_state['progress']['encounters_started'] ?? 0) + 1;
    }

    if (($snapshot['phase'] ?? '') === 'encounter'
      && !empty($response['phase_transition']['to'])
      && $response['phase_transition']['to'] === 'exploration') {
      $run_state['progress']['encounters_completed'] = (int) ($run_state['progress']['encounters_completed'] ?? 0) + 1;
    }

    $event_cursor = (int) ($snapshot['event_cursor'] ?? ($run_state['event_cursor'] ?? 0));
    foreach ($response['events'] ?? [] as $event) {
      $event_cursor = max($event_cursor, (int) ($event['id'] ?? 0));
    }
    $run_state['event_cursor'] = $event_cursor;

    $current_phase = $response['game_state']['phase'] ?? $snapshot['phase'] ?? 'exploration';
    $run_state['progress']['current_phase'] = $current_phase;
    $run_state['progress']['current_room_id'] = $room_id;
    $run_state['progress']['visited_room_count'] = count($run_state['memory']['visited_rooms'] ?? []);

    $character_id = (int) ($profile['character_id'] ?? 0);
    $campaign_id = (int) ($snapshot['campaign_id'] ?? 0);
    if ($campaign_id > 0 && $character_id > 0) {
      $run_state['progress']['campaign_xp_total'] = $this->sessionService->getCampaignCharacterXp($campaign_id, $character_id);
    }

    $run_state['trace'][] = [
      'step' => $run_state['step_count'],
      'phase' => $snapshot['phase'] ?? 'exploration',
      'decision' => $decision,
      'success' => $response['success'] ?? TRUE,
      'error' => $response['error'] ?? NULL,
      'room_id' => $room_id,
      'response_phase' => $response['game_state']['phase'] ?? NULL,
    ];

    return $run_state;
  }

}
