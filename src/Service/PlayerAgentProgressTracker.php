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
    $pre_memory = [
      'talked_entities' => array_values(array_map('strval', (array) ($run_state['memory']['talked_entities'] ?? []))),
      'pending_conversation_lead' => is_array($run_state['memory']['pending_conversation_lead'] ?? NULL)
        ? $run_state['memory']['pending_conversation_lead']
        : NULL,
      'active_npc_lead' => is_array($run_state['memory']['active_npc_lead'] ?? NULL)
        ? $run_state['memory']['active_npc_lead']
        : NULL,
    ];
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

        $lead_excerpt = ($response['success'] ?? FALSE) ? $this->extractActionableLeadExcerpt($response) : '';
        $existing_pending_lead = is_array($run_state['memory']['pending_conversation_lead'] ?? NULL)
          ? $run_state['memory']['pending_conversation_lead']
          : NULL;
        if ($automation_goal === 'conversation_follow_up' && $existing_pending_lead !== NULL) {
          $existing_pending_lead['follow_up_attempts'] = (int) ($existing_pending_lead['follow_up_attempts'] ?? 0) + 1;
          $run_state['memory']['pending_conversation_lead'] = $existing_pending_lead;
        }
        $existing_lead_signature = $this->buildLeadSignature($existing_pending_lead);
        $new_lead_signature = $this->buildLeadSignature([
          'target' => (string) $intent['target'],
          'room_id' => $room_id,
          'excerpt' => $lead_excerpt,
        ]);
        if ($automation_goal === 'conversation_follow_up' && ($response['success'] ?? FALSE)) {
          if ($existing_lead_signature !== '') {
            $run_state['memory']['exhausted_conversation_leads'] = array_values(array_unique(array_merge(
              $run_state['memory']['exhausted_conversation_leads'] ?? [],
              [$existing_lead_signature]
            )));
          }
          if ($lead_excerpt !== '') {
            $run_state['memory']['active_npc_lead'] = [
              'target' => (string) $intent['target'],
              'room_id' => $room_id,
              'automation_goal' => $automation_goal,
              'excerpt' => $lead_excerpt,
              'signature' => $new_lead_signature,
            ];
          }
          elseif (is_array($run_state['memory']['pending_conversation_lead'] ?? NULL)) {
            $run_state['memory']['active_npc_lead'] = $run_state['memory']['pending_conversation_lead'];
          }
          unset($run_state['memory']['pending_conversation_lead']);
        }
        elseif ($lead_excerpt !== '' && !$this->isExhaustedLeadSignature($run_state, $new_lead_signature)) {
          $run_state['memory']['pending_conversation_lead'] = [
            'target' => (string) $intent['target'],
            'room_id' => $room_id,
            'automation_goal' => $automation_goal,
            'excerpt' => $lead_excerpt,
            'signature' => $new_lead_signature,
            'follow_up_attempts' => 0,
          ];
        }
      }

      if ($intent_type === 'talk' && !empty($snapshot['game_state']['encounter_id'])) {
        $run_state['memory']['encounter_battle_cries'][(string) $snapshot['game_state']['encounter_id']] = TRUE;
      }

      if ($intent_type === 'transition' && ($response['success'] ?? FALSE)) {
        unset($run_state['memory']['pending_conversation_lead']);
        unset($run_state['memory']['active_npc_lead']);
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
        unset($run_state['memory']['pending_conversation_lead']);
        unset($run_state['memory']['active_npc_lead']);
        $run_state['memory']['rested_rooms'] = array_values(array_unique(array_merge(
          $run_state['memory']['rested_rooms'] ?? [],
          [$room_id]
        )));
      }
    }

    if ($decision_type === 'intent') {
      $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
      $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
      \Drupal::logger('dungeoncrawler_player_agent')->info(
        'Player-agent run-state update: actor @actor room @room intent @intent target @target goal @goal success @success talked_before=@talked_before talked_after=@talked_after pending_before=@pending_before pending_after=@pending_after active_before=@active_before active_after=@active_after',
        [
          '@actor' => (string) ($profile['actor_id'] ?? ''),
          '@room' => $room_id,
          '@intent' => (string) ($intent['type'] ?? ''),
          '@target' => (string) ($intent['target'] ?? ''),
          '@goal' => (string) ($params['automation_goal'] ?? ''),
          '@success' => ($response['success'] ?? FALSE) ? 'yes' : 'no',
          '@talked_before' => implode(',', array_slice($pre_memory['talked_entities'] ?? [], -5)) ?: 'none',
          '@talked_after' => implode(',', array_slice(array_map('strval', (array) ($run_state['memory']['talked_entities'] ?? [])), -5)) ?: 'none',
          '@pending_before' => $pre_memory['pending_conversation_lead'] !== NULL
            ? json_encode($pre_memory['pending_conversation_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : 'none',
          '@pending_after' => is_array($run_state['memory']['pending_conversation_lead'] ?? NULL)
            ? json_encode($run_state['memory']['pending_conversation_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : 'none',
          '@active_before' => $pre_memory['active_npc_lead'] !== NULL
            ? json_encode($pre_memory['active_npc_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : 'none',
          '@active_after' => is_array($run_state['memory']['active_npc_lead'] ?? NULL)
            ? json_encode($run_state['memory']['active_npc_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : 'none',
        ]
      );
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
      'decision_meta' => is_array($decision['decision_meta'] ?? NULL) ? $decision['decision_meta'] : [],
      'success' => $response['success'] ?? TRUE,
      'error' => $response['error'] ?? NULL,
      'room_id' => $room_id,
      'response_phase' => $response['game_state']['phase'] ?? NULL,
    ];

    return $run_state;
  }

  /**
   * Extract a concise actionable lead from a talk response.
   */
  protected function extractActionableLeadExcerpt(?array $response): string {
    if (!is_array($response)) {
      return '';
    }

    $candidates = [];
    foreach (($response['result']['npc_interjections'] ?? []) as $interjection) {
      if (!is_array($interjection)) {
        continue;
      }
      $candidates[] = [
        'text' => (string) ($interjection['message'] ?? ''),
        'speaker_type' => (string) ($interjection['type'] ?? 'npc'),
      ];
    }
    $candidates[] = [
      'text' => (string) ($response['result']['gm_response']['message'] ?? ''),
      'speaker_type' => (string) ($response['result']['gm_response']['type'] ?? 'gm'),
    ];

    foreach ($candidates as $candidate_row) {
      $candidate = trim(preg_replace('/\s+/', ' ', (string) ($candidate_row['text'] ?? '')) ?? (string) ($candidate_row['text'] ?? ''), " \t\n\r\0\x0B\"'");
      $speaker_type = strtolower((string) ($candidate_row['speaker_type'] ?? ''));
      if ($candidate === '' || $this->isIgnorableAutomationReply($candidate) || !$this->containsActionableLeadCue($candidate, $speaker_type)) {
        continue;
      }

      return strlen($candidate) > 220
        ? rtrim(substr($candidate, 0, 217)) . '...'
        : $candidate;
    }

    return '';
  }

  /**
   * Build a stable signature for a conversational lead.
   */
  protected function buildLeadSignature(?array $lead): string {
    if (!is_array($lead)) {
      return '';
    }
    $target = trim((string) ($lead['target'] ?? ''));
    $room_id = trim((string) ($lead['room_id'] ?? ''));
    $excerpt = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($lead['excerpt'] ?? '')) ?? ''));
    if ($target === '' || $excerpt === '') {
      return '';
    }
    return sha1($target . '|' . $room_id . '|' . $excerpt);
  }

  /**
   * Determine if a lead has already been fully followed.
   */
  protected function isExhaustedLeadSignature(array $run_state, string $signature): bool {
    if ($signature === '') {
      return FALSE;
    }
    return in_array($signature, $run_state['memory']['exhausted_conversation_leads'] ?? [], TRUE);
  }

  /**
   * Heuristic: the response contains an actionable lead or direction.
   */
  protected function containsActionableLeadCue(string $text, string $speaker_type = ''): bool {
    $normalized = strtolower($text);
    foreach ([
      'go to', 'head', 'north', 'south', 'east', 'west', 'cemetery', 'vault',
      'speak with', 'talk to', 'ask', 'find', 'look for', 'check', 'follow',
      'trail', 'proof', 'elders', 'guard', 'lights', 'missing', 'where',
    ] as $cue) {
      if (str_contains($normalized, $cue)) {
        return TRUE;
      }
    }

    return $speaker_type === 'npc' && strlen($normalized) >= 120;
  }

  /**
   * Ignore boilerplate narrative and non-actionable acknowledgements.
   */
  protected function isIgnorableAutomationReply(string $text): bool {
    $normalized = strtolower(trim($text, " \t\n\r\0\x0B\"'.!*"));

    foreach ([
      'the scene remains grounded around you, with the visible room occupants and current situation still before you',
      'the space narrows to a direct conversation',
      'hello what can i do for you',
      'what do you need',
      'make it quick',
      'you\'re welcome',
      'mm',
    ] as $ignored) {
      if ($normalized === $ignored) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
