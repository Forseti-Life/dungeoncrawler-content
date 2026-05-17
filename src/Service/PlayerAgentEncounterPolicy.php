<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic encounter policy for the player agent.
 */
class PlayerAgentEncounterPolicy implements PlayerAgentPolicyInterface {

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

    if (in_array('end_turn', $available_actions, TRUE)) {
      return [
        'type' => 'intent',
        'reason' => 'No configured legal attack is available; end the turn safely.',
        'intent' => [
          'type' => 'end_turn',
          'actor' => $actor_id,
          'params' => [],
        ],
      ];
    }

    return ['type' => 'wait', 'reason' => 'No safe encounter action was selected.'];
  }

}
