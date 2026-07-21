<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic encounter policy for the player agent.
 */
class PlayerAgentEncounterPolicy implements PlayerAgentPolicyInterface {

  /**
   * HARD CONTRACT — Burasco harness deterministic action workflow.
   *
   * This sequence is intentionally hardcoded and is not equivalent to dynamic
   * objective-driven policy behavior.
   *
   * MAINTAINER DIRECTIVE:
   * - Do NOT remove this list.
   * - Do NOT reorder this list.
   * - Do NOT replace this list with generic/dynamic quest selection logic.
   * - Do NOT gate this list behind optional flags unless product explicitly
   *   changes the contract.
   *
   * If behavior must change, treat it as a product-contract change and update:
   * 1) this constant,
   * 2) the matching constant in PlayerAgentExplorationPolicy, and
   * 3) harness validation expectations.
   */
  private const HARDCODED_TAVERN_SCRIPT = [
    [
      'target' => 'npc_gribbles_rindsworth',
      'message' => 'Gribbles, what jobs or dangers should I tackle first for pay around here?',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_talk_gribbles',
    ],
    [
      'target' => 'scholar_npc',
      'message' => 'Marta, what urgent problem needs attention and where should I start?',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_talk_marta',
    ],
    [
      'target' => 'tavern_keeper',
      'message' => 'Eldric, what work or danger do you know about that pays coin?',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_talk_eldric',
    ],
    [
      'action' => 'search',
      'goal' => 'hardcoded_tavern_search',
    ],
    [
      'target' => 'tavern_keeper',
      'message' => 'Eldric, I found the requested items. I am turning them in now.',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_turn_in',
    ],
    [
      'target' => 'scholar_npc',
      'message' => 'Marta, I found the requested items. I am turning them in now.',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_turn_in_marta',
    ],
    [
      'target' => 'npc_gribbles_rindsworth',
      'message' => 'Gribbles, I found the requested items. I am turning them in now.',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_turn_in_gribbles',
    ],
    [
      'target' => 'tavern_keeper',
      'message' => 'Eldric, I am ready for additional work. What should I do next?',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_ask_eldric_additional_work',
    ],
    [
      'action' => 'transition',
      'target_room_id' => 'tpl_room_absalom_streets',
      'goal' => 'hardcoded_tavern_navigate_absalom_streets',
    ],
    [
      'action' => 'transition',
      'target_room_id' => 'ltba-grandmas-house-parlor',
      'goal' => 'hardcoded_tavern_navigate_grandmas_parlor',
    ],
    [
      'target' => 'ltba-grandmother',
      'message' => 'Grandmother, I need details about the hedge trimmer job.',
      'action' => 'talk',
      'goal' => 'hardcoded_tavern_talk_grandmother_hedge_trimmers',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function supportsPhase(string $phase): bool {
    return $phase === 'encounter';
  }

  /**
   * {@inheritdoc}
   */
  public function chooseAction(array $profile, array $snapshot, array $run_state): array {
    $actor_id = (string) ($profile['actor_id'] ?? '');
    if ($actor_id === '') {
      return ['type' => 'wait', 'reason' => 'No actor_id configured for encounter policy.'];
    }

    $turn_actor = (string) ($snapshot['game_state']['turn']['entity'] ?? '');
    if ($turn_actor === '' || $turn_actor !== $actor_id) {
      return ['type' => 'wait', 'reason' => 'It is not this actor\'s turn.'];
    }

    $available_actions = array_values(array_unique($snapshot['available_actions'] ?? []));
    $encounter_id = (string) ($snapshot['game_state']['encounter_id'] ?? '');
    $battle_cries = is_array($run_state['memory']['encounter_battle_cries'] ?? NULL)
      ? $run_state['memory']['encounter_battle_cries']
      : [];
    // Contract order is critical: execute hardcoded workflow BEFORE any
    // adaptive encounter fallbacks.
    $scripted = $this->chooseHardcodedTavernScriptAction(
      $actor_id,
      $available_actions,
      $run_state,
      (int) ($profile['character_id'] ?? 0)
    );
    if ($scripted !== NULL) {
      return $scripted;
    }

    if (in_array('talk', $available_actions, TRUE)
      && $encounter_id !== ''
      && empty($battle_cries[$encounter_id])
      && !empty($profile['combat_loadout']['battle_cry'])) {
      return [
        'type' => 'intent',
        'reason' => 'Open the encounter in character before committing other actions.',
        'intent' => [
          'type' => 'talk',
          'actor' => $actor_id,
          'params' => [
            'message' => (string) $profile['combat_loadout']['battle_cry'],
          ],
        ],
      ];
    }

    $weapon = is_array($profile['combat_loadout']['weapon'] ?? NULL)
      ? $profile['combat_loadout']['weapon']
      : [];
    $hostile_target = $snapshot['hostile_targets'][0] ?? NULL;
    $target_id = is_array($hostile_target) ? (string) ($hostile_target['entity_id'] ?? '') : '';

    if ($target_id !== '' && in_array('strike', $available_actions, TRUE) && $weapon !== []) {
      return [
        'type' => 'intent',
        'reason' => 'Attack the first active hostile target using the configured combat loadout.',
        'intent' => [
          'type' => 'strike',
          'actor' => $actor_id,
          'target' => $target_id,
          'params' => [
            'weapon' => $weapon,
          ],
        ],
      ];
    }

    if (in_array('choose_not_to_act', $available_actions, TRUE)) {
      return [
        'type' => 'intent',
        'reason' => 'No configured legal action is available; explicitly choose not to act.',
        'intent' => [
          'type' => 'choose_not_to_act',
          'actor' => $actor_id,
          'params' => [
            'reason' => 'Player agent found no configured legal action.',
          ],
        ],
      ];
    }

    if (in_array('end_turn', $available_actions, TRUE)) {
      return [
        'type' => 'intent',
        'reason' => 'No configured legal action is available; end the turn explicitly.',
        'intent' => [
          'type' => 'end_turn',
          'actor' => $actor_id,
          'params' => [],
        ],
      ];
    }

    return ['type' => 'wait', 'reason' => 'No safe encounter action was selected.'];
  }

  /**
   * Force deterministic tavern NPC order before fallback actions.
   *
   * Do not "simplify" this into objective-driven selection. This method is the
   * canonical contract implementation for the Burasco scripted harness flow.
   */
  protected function chooseHardcodedTavernScriptAction(string $actor_id, array $available_actions, array $run_state, int $character_id = 0): ?array {
    if ($actor_id === '') {
      return NULL;
    }
    $searched_rooms = array_values(array_map('strval', (array) ($run_state['memory']['searched_rooms'] ?? [])));
    $current_room_id = trim((string) ($run_state['progress']['current_room_id'] ?? ''));
    $talked_entities = array_values(array_map('strval', (array) ($run_state['memory']['talked_entities'] ?? [])));
    foreach (self::HARDCODED_TAVERN_SCRIPT as $step) {
      $action = (string) ($step['action'] ?? 'talk');
      $goal = (string) ($step['goal'] ?? '');
      if ($goal !== '' && $this->hasExecutedHardcodedGoal($run_state, $goal)) {
        continue;
      }
      if (!in_array($action, $available_actions, TRUE)) {
        continue;
      }
      if ($action === 'search') {
        if ($current_room_id !== '' && in_array($current_room_id, $searched_rooms, TRUE)) {
          continue;
        }
        return [
          'type' => 'intent',
          'reason' => 'Execute hardcoded tavern action list in deterministic order.',
          'intent' => [
            'type' => 'search',
            'actor' => $actor_id,
            'params' => [
              'character_id' => $character_id,
              'automation_goal' => $goal,
            ],
          ],
        ];
      }
      if ($action === 'transition') {
        $target_room_id = trim((string) ($step['target_room_id'] ?? ''));
        if ($target_room_id === '') {
          continue;
        }
        if ($current_room_id !== '' && $current_room_id === $target_room_id) {
          continue;
        }
        return [
          'type' => 'intent',
          'reason' => 'Execute hardcoded tavern action list in deterministic order.',
          'intent' => [
            'type' => 'transition',
            'actor' => $actor_id,
            'params' => [
              'character_id' => $character_id,
              'target_room_id' => $target_room_id,
              'automation_goal' => $goal,
            ],
          ],
        ];
      }
      $target = (string) ($step['target'] ?? '');
      if ($target === '') {
        continue;
      }
      if (str_starts_with($goal, 'hardcoded_tavern_talk_') && in_array($target, $talked_entities, TRUE)) {
        continue;
      }
      return [
        'type' => 'intent',
        'reason' => 'Execute hardcoded tavern action list in deterministic order.',
        'intent' => [
          'type' => $action,
          'actor' => $actor_id,
          'target' => $target,
          'params' => [
            'character_id' => $character_id,
            'message' => (string) ($step['message'] ?? ''),
            'automation_goal' => $goal !== '' ? $goal : 'hardcoded_tavern_script',
          ],
        ],
      ];
    }
    return NULL;
  }

  /**
   * Determine whether the hardcoded action goal already executed in this run.
   */
  protected function hasExecutedHardcodedGoal(array $run_state, string $goal): bool {
    if ($goal === '') {
      return FALSE;
    }
    foreach ((array) ($run_state['trace'] ?? []) as $trace_row) {
      if (!is_array($trace_row)) {
        continue;
      }
      $decision = is_array($trace_row['decision'] ?? NULL) ? $trace_row['decision'] : [];
      $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
      $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
      if ((string) ($params['automation_goal'] ?? '') !== $goal) {
        continue;
      }
      if (!empty($trace_row['success'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
