<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Builds normalized fallback automation decisions for rest-loop recovery.
 */
final class FallbackAutomationDecisionBuilder {

  /**
   * Normalize an LLM fallback payload into a deterministic decision envelope.
   *
   * @param callable(array,array,int,string):array $deterministic_fallback
   * @param callable(string):string $sanitize_message
   */
  public static function normalizeDecision(
    array $payload,
    array $snapshot,
    array $run_state,
    int $character_id,
    callable $deterministic_fallback,
    callable $sanitize_message
  ): array {
    $available_actions = array_values(array_unique(array_filter(
      array_map(static fn($action): string => strtolower(trim((string) $action)), $snapshot['available_actions'] ?? []),
      static fn(string $action): bool => $action !== ''
    )));
    $action_type = strtolower(trim((string) ($payload['action_type'] ?? '')));
    $reason = trim((string) ($payload['reason'] ?? 'Fallback analysis selected the next action.'));
    $target_id = trim((string) ($payload['target_id'] ?? ''));
    $actor_id = (string) ($snapshot['actor_id'] ?? '');

    if ($action_type === '' || !in_array($action_type, ['talk', 'search', 'transition', 'rest', 'wait'], TRUE) || ($action_type !== 'wait' && !in_array($action_type, $available_actions, TRUE))) {
      return $deterministic_fallback($snapshot, $run_state, $character_id, $reason !== '' ? $reason : 'Fallback analysis returned an invalid action.');
    }

    $decision_meta = [
      'stage' => 'rest_loop_llm_recovery',
      'priority' => 15,
      'room_id' => (string) ($snapshot['active_room_id'] ?? ''),
      'analysis_fallback' => TRUE,
    ];

    if ($action_type === 'rest') {
      return [
        'type' => 'stop',
        'reason' => $reason !== '' ? $reason : 'Fallback analysis recommends rest; automation should pause.',
        'decision_meta' => [
          'stage' => 'rest_analysis_stop',
          'priority' => 14,
          'room_id' => (string) ($snapshot['active_room_id'] ?? ''),
          'analysis_fallback' => TRUE,
        ],
      ];
    }

    if ($action_type === 'search') {
      return [
        'type' => 'intent',
        'reason' => $reason,
        'intent' => [
          'type' => 'search',
          'actor' => $actor_id,
          'params' => [
            'automation_goal' => 'rest_loop_recovery',
          ],
        ],
        'decision_meta' => $decision_meta,
      ];
    }

    if ($action_type === 'transition') {
      $valid_room_ids = array_values(array_filter(array_map(static fn(array $room): string => (string) ($room['room_id'] ?? ''), $snapshot['connected_rooms'] ?? [])));
      if ($target_id === '' || !in_array($target_id, $valid_room_ids, TRUE)) {
        return $deterministic_fallback($snapshot, $run_state, $character_id, 'Fallback analysis selected an invalid room target.');
      }
      return [
        'type' => 'intent',
        'reason' => $reason,
        'intent' => [
          'type' => 'transition',
          'actor' => $actor_id,
          'params' => [
            'target_room_id' => $target_id,
            'automation_goal' => 'rest_loop_recovery',
          ],
        ],
        'decision_meta' => $decision_meta + ['target' => $target_id],
      ];
    }

    if ($action_type === 'talk') {
      $valid_npcs = [];
      foreach (($snapshot['visible_npcs'] ?? []) as $npc) {
        $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
        if ($npc_id !== '') {
          $valid_npcs[] = $npc_id;
        }
      }
      $talked_entities = array_values(array_map('strval', (array) ($run_state['memory']['talked_entities'] ?? [])));
      $has_active_lead = is_array($run_state['memory']['pending_conversation_lead'] ?? NULL)
        || is_array($run_state['memory']['active_npc_lead'] ?? NULL);
      $message = $sanitize_message((string) ($payload['message'] ?? ''));
      if ($target_id === '' || $message === '' || !in_array($target_id, $valid_npcs, TRUE)) {
        return $deterministic_fallback($snapshot, $run_state, $character_id, 'Fallback analysis selected an invalid NPC talk action.');
      }
      if (in_array($target_id, $talked_entities, TRUE) && !$has_active_lead) {
        return $deterministic_fallback($snapshot, $run_state, $character_id, 'Fallback analysis repeated an exhausted NPC target without a new actionable lead.');
      }
      return [
        'type' => 'intent',
        'reason' => $reason,
        'intent' => [
          'type' => 'talk',
          'actor' => $actor_id,
          'target' => $target_id,
          'params' => [
            'message' => $message,
            'character_id' => $character_id,
            'automation_goal' => 'rest_loop_recovery',
          ],
        ],
        'decision_meta' => $decision_meta + ['target' => $target_id],
      ];
    }

    return [
      'type' => 'wait',
      'reason' => $reason !== '' ? $reason : 'Fallback analysis chose to wait.',
      'decision_meta' => $decision_meta,
    ];
  }

  /**
   * Deterministic non-rest fallback when LLM recovery returns no valid action.
   */
  public static function buildDeterministicDecision(array $snapshot, array $run_state, int $character_id, string $reason): array {
    $available_actions = array_values(array_unique(array_filter(
      array_map(static fn($action): string => strtolower(trim((string) $action)), $snapshot['available_actions'] ?? []),
      static fn(string $action): bool => $action !== '' && $action !== 'rest'
    )));
    $actor_id = (string) ($snapshot['actor_id'] ?? '');
    $decision_meta = [
      'stage' => 'rest_loop_deterministic_recovery',
      'priority' => 16,
      'room_id' => (string) ($snapshot['active_room_id'] ?? ''),
      'analysis_fallback' => TRUE,
    ];
    $talked_entities = $run_state['memory']['talked_entities'] ?? [];
    $searched_rooms = $run_state['memory']['searched_rooms'] ?? [];
    $current_room_id = (string) ($snapshot['active_room_id'] ?? '');

    if (in_array('search', $available_actions, TRUE) && $current_room_id !== '' && !in_array($current_room_id, $searched_rooms, TRUE)) {
      return [
        'type' => 'intent',
        'reason' => $reason !== '' ? $reason : 'Avoid repeating rest; search the current room instead.',
        'intent' => [
          'type' => 'search',
          'actor' => $actor_id,
          'params' => [
            'automation_goal' => 'rest_loop_recovery',
          ],
        ],
        'decision_meta' => $decision_meta,
      ];
    }

    if (in_array('transition', $available_actions, TRUE)) {
      foreach (($snapshot['connected_rooms'] ?? []) as $room) {
        $target_room_id = (string) ($room['room_id'] ?? '');
        if ($target_room_id !== '') {
          return [
            'type' => 'intent',
            'reason' => $reason !== '' ? $reason : 'Avoid repeating rest; move to the next connected room.',
            'intent' => [
              'type' => 'transition',
              'actor' => $actor_id,
              'params' => [
                'target_room_id' => $target_room_id,
                'automation_goal' => 'rest_loop_recovery',
              ],
            ],
            'decision_meta' => $decision_meta + ['target' => $target_room_id],
          ];
        }
      }
    }

    if (in_array('talk', $available_actions, TRUE)) {
      foreach (($snapshot['visible_npcs'] ?? []) as $npc) {
        $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
        $npc_name = (string) ($npc['name'] ?? $npc['display_name'] ?? $npc['state']['metadata']['display_name'] ?? 'friend');
        if ($npc_id === '' || in_array($npc_id, $talked_entities, TRUE)) {
          continue;
        }
        return [
          'type' => 'intent',
          'reason' => $reason !== '' ? $reason : 'Avoid repeating rest; ask the best available NPC for direction.',
          'intent' => [
            'type' => 'talk',
            'actor' => $actor_id,
            'target' => $npc_id,
            'params' => [
              'message' => sprintf('Hello %s. I need the clearest next step to advance my current objective. What should I do right now?', $npc_name),
              'character_id' => $character_id,
              'automation_goal' => 'rest_loop_recovery',
            ],
          ],
          'decision_meta' => $decision_meta + ['target' => $npc_id],
        ];
      }
    }

    return [
      'type' => 'wait',
      'reason' => $reason !== '' ? $reason : 'No valid non-rest fallback action is available.',
      'decision_meta' => $decision_meta,
    ];
  }

}
