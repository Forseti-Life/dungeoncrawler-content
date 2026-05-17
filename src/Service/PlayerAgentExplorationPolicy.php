<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic exploration policy for the player agent.
 */
class PlayerAgentExplorationPolicy implements PlayerAgentPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function supportsPhase(string $phase): bool {
    return $phase === 'exploration';
  }

  /**
   * {@inheritdoc}
   */
  public function chooseAction(array $profile, array $snapshot, array $run_state): array {
    $actor_id = (string) ($profile['actor_id'] ?? '');
    if ($actor_id === '') {
      return ['type' => 'wait', 'reason' => 'No actor_id configured for exploration policy.'];
    }

    $available_actions = array_values(array_unique($snapshot['available_actions'] ?? []));
    $memory = is_array($run_state['memory'] ?? NULL) ? $run_state['memory'] : [];
    $current_room_id = (string) ($snapshot['active_room_id'] ?? '');

    if (in_array('search', $available_actions, TRUE)
      && $current_room_id !== ''
      && !in_array($current_room_id, $memory['searched_rooms'] ?? [], TRUE)) {
      return [
        'type' => 'intent',
        'reason' => 'Search the current room before moving on.',
        'intent' => [
          'type' => 'search',
          'actor' => $actor_id,
          'params' => [],
        ],
      ];
    }

    $visible_npcs = is_array($snapshot['visible_npcs'] ?? NULL) ? $snapshot['visible_npcs'] : [];
    if (in_array('talk', $available_actions, TRUE)) {

      foreach ($visible_npcs as $npc) {
        $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
        if ($npc_id === '' || in_array($npc_id, $memory['talked_entities'] ?? [], TRUE)) {
          continue;
        }

        return [
          'type' => 'intent',
          'reason' => 'Speak to an unvisited NPC in character.',
          'intent' => [
            'type' => 'talk',
            'actor' => $actor_id,
            'target' => $npc_id,
            'params' => [
              'message' => $this->buildGreeting($profile, $npc),
              'character_id' => (int) ($profile['character_id'] ?? 0),
            ],
          ],
        ];
      }

    }

    if (in_array('transition', $available_actions, TRUE)) {
      $visited_rooms = $memory['visited_rooms'] ?? [];
      $connections = $snapshot['connected_rooms'] ?? [];

      foreach ($connections as $connection) {
        $target_room_id = (string) ($connection['room_id'] ?? '');
        if ($target_room_id !== '' && !in_array($target_room_id, $visited_rooms, TRUE)) {
          return [
            'type' => 'intent',
            'reason' => 'Advance exploration into an unvisited connected room.',
            'intent' => [
              'type' => 'transition',
              'actor' => $actor_id,
              'params' => [
                'target_room_id' => $target_room_id,
              ],
            ],
          ];
        }
      }

      if (!empty($connections[0]['room_id'])) {
        return [
          'type' => 'intent',
          'reason' => 'Continue exploration through the first available connection.',
          'intent' => [
            'type' => 'transition',
            'actor' => $actor_id,
            'params' => [
              'target_room_id' => (string) $connections[0]['room_id'],
            ],
          ],
        ];
      }
    }

    if (in_array('talk', $available_actions, TRUE)) {
      $consulted_rooms = is_array($memory['consulted_rooms'] ?? NULL) ? $memory['consulted_rooms'] : [];
      $fallback_npc = $visible_npcs[0] ?? NULL;
      $fallback_npc_id = (string) ($fallback_npc['entity_instance_id'] ?? $fallback_npc['instance_id'] ?? $fallback_npc['id'] ?? '');
      if ($fallback_npc_id !== ''
        && ($current_room_id === '' || !in_array($current_room_id, $consulted_rooms, TRUE))) {
        return [
          'type' => 'intent',
          'reason' => 'Ask an available NPC about paid work before moving on.',
          'intent' => [
            'type' => 'talk',
            'actor' => $actor_id,
            'target' => $fallback_npc_id,
            'params' => [
              'message' => $this->buildQuestInquiry($profile, $fallback_npc),
              'character_id' => (int) ($profile['character_id'] ?? 0),
              'automation_goal' => 'paid_work_fallback',
            ],
          ],
        ];
      }
    }

    if (in_array('rest', $available_actions, TRUE)) {
      $rested_rooms = is_array($memory['rested_rooms'] ?? NULL) ? $memory['rested_rooms'] : [];
      if ($current_room_id !== '' && in_array($current_room_id, $rested_rooms, TRUE)) {
        return ['type' => 'wait', 'reason' => 'No new exploration action is available in the current room.'];
      }

      return [
        'type' => 'intent',
        'reason' => 'No higher-priority exploration action is available; take a short rest.',
        'intent' => [
          'type' => 'rest',
          'actor' => $actor_id,
          'params' => [
            'rest_type' => 'short',
          ],
        ],
      ];
    }

    return ['type' => 'wait', 'reason' => 'No safe exploration action was selected.'];
  }

  /**
   * Build a simple in-character NPC greeting.
   */
  protected function buildGreeting(array $profile, array $npc): string {
    return $this->buildQuestInquiry($profile, $npc, TRUE);
  }

  /**
   * Build an in-character quest-seeking prompt for an NPC.
   */
  protected function buildQuestInquiry(array $profile, array $npc, bool $is_first_contact = FALSE): string {
    $character_name = trim((string) ($profile['character_name'] ?? $profile['persona']['name'] ?? ''));
    $npc_name = trim((string) (
      $npc['name']
      ?? $npc['display_name']
      ?? $npc['state']['metadata']['display_name']
      ?? $npc['profile']['display_name']
      ?? 'friend'
    ));
    $tone = strtolower(trim((string) ($profile['persona']['tone'] ?? 'curious')));
    $traveler_name = $character_name !== '' ? $character_name : 'a traveler';

    return match ($tone) {
      'cautious' => $is_first_contact
        ? sprintf('Hello %s. I am %s, and I am looking for safe paid work helping local folk and earning some gold.', $npc_name, $traveler_name)
        : sprintf('%s, I am still looking for safe paid work. Is there a nearby task, bounty, or local problem I can help with for coin?', $npc_name),
      'bold' => $is_first_contact
        ? sprintf('I am %s. %s, point me toward paid work, a bounty, or local trouble I can solve for gold.', $traveler_name, $npc_name)
        : sprintf('%s, I am ready for the next challenge. What paid job, bounty, or local trouble needs handling?', $npc_name),
      default => $is_first_contact
        ? sprintf('Hello %s. I am %s, and I am looking for work helping local people, taking on quests, and earning gold.', $npc_name, $traveler_name)
        : sprintf('%s, I am looking for more work. Is there a quest, bounty, or local problem I can help with for gold?', $npc_name),
    };
  }

}
