<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic exploration policy for the player agent.
 */
class PlayerAgentExplorationPolicy implements PlayerAgentPolicyInterface {

  protected ?QuestTrackerService $questTracker;

  public function __construct(?QuestTrackerService $quest_tracker = NULL) {
    $this->questTracker = $quest_tracker;
  }

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
    $visible_npcs = is_array($snapshot['visible_npcs'] ?? NULL) ? $snapshot['visible_npcs'] : [];
    $pending_lead = is_array($memory['pending_conversation_lead'] ?? NULL) ? $memory['pending_conversation_lead'] : NULL;
    $follow_up_decision = $this->chooseConversationFollowUpAction(
      $profile,
      $actor_id,
      $available_actions,
      $current_room_id,
      $visible_npcs,
      $pending_lead
    );
    if ($follow_up_decision !== NULL) {
      return $follow_up_decision;
    }
    $quest_focus = $this->resolveQuestFocus(
      (int) ($snapshot['campaign_id'] ?? 0),
      (int) ($profile['character_id'] ?? 0)
    );

    $quest_driven_decision = $this->chooseQuestDrivenAction(
      $profile,
      $actor_id,
      $available_actions,
      $memory,
      $current_room_id,
      $visible_npcs,
      $snapshot,
      $quest_focus
    );
    if ($quest_driven_decision !== NULL) {
      return $quest_driven_decision;
    }

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
   * Continue an in-progress NPC back-and-forth before changing course.
   */
  protected function chooseConversationFollowUpAction(
    array $profile,
    string $actor_id,
    array $available_actions,
    string $current_room_id,
    array $visible_npcs,
    ?array $pending_lead
  ): ?array {
    if ($pending_lead === NULL || !in_array('talk', $available_actions, TRUE)) {
      return NULL;
    }

    if ((string) ($pending_lead['room_id'] ?? '') !== '' && (string) ($pending_lead['room_id'] ?? '') !== $current_room_id) {
      return NULL;
    }

    $target_npc_id = (string) ($pending_lead['target'] ?? '');
    if ($target_npc_id === '') {
      return NULL;
    }

    foreach ($visible_npcs as $npc) {
      $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
      if ($npc_id !== $target_npc_id) {
        continue;
      }

      return [
        'type' => 'intent',
        'reason' => 'Follow up on the NPC guidance before changing direction.',
        'intent' => [
          'type' => 'talk',
          'actor' => $actor_id,
          'target' => $target_npc_id,
          'params' => [
            'message' => $this->buildLeadFollowUpPrompt($profile, $npc, $pending_lead),
            'character_id' => (int) ($profile['character_id'] ?? 0),
            'automation_goal' => 'conversation_follow_up',
          ],
        ],
      ];
    }

    return NULL;
  }

  /**
   * Choose a quest-driven action before falling back to generic exploration.
   */
  protected function chooseQuestDrivenAction(
    array $profile,
    string $actor_id,
    array $available_actions,
    array $memory,
    string $current_room_id,
    array $visible_npcs,
    array $snapshot,
    ?array $quest_focus
  ): ?array {
    if ($quest_focus === NULL) {
      return NULL;
    }

    $objective_text = strtolower((string) ($quest_focus['objective'] ?? ''));
    $objective_summary = $this->buildQuestObjectiveSummary($quest_focus);
    $should_talk_for_quest = $this->objectiveSuggestsConversation($objective_text);

    if ($should_talk_for_quest && in_array('talk', $available_actions, TRUE)) {
      foreach ($visible_npcs as $npc) {
        $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
        if ($npc_id === '' || in_array($npc_id, $memory['talked_entities'] ?? [], TRUE)) {
          continue;
        }

        return [
          'type' => 'intent',
          'reason' => 'Advance active quest via conversation: ' . $objective_summary,
          'intent' => [
            'type' => 'talk',
            'actor' => $actor_id,
            'target' => $npc_id,
            'params' => [
              'message' => $this->buildQuestProgressInquiry($profile, $npc, $quest_focus),
              'character_id' => (int) ($profile['character_id'] ?? 0),
              'automation_goal' => 'active_quest_progress',
              'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
            ],
          ],
        ];
      }
    }

    if (in_array('search', $available_actions, TRUE)
      && $current_room_id !== ''
      && !in_array($current_room_id, $memory['searched_rooms'] ?? [], TRUE)
      && ($this->objectiveSuggestsSearch($objective_text) || $visible_npcs === [])) {
      return [
        'type' => 'intent',
        'reason' => 'Search the room to advance active quest: ' . $objective_summary,
        'intent' => [
          'type' => 'search',
          'actor' => $actor_id,
          'params' => [
            'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
          ],
        ],
      ];
    }

    if (in_array('transition', $available_actions, TRUE)
      && ($this->objectiveSuggestsMovement($objective_text) || !$should_talk_for_quest)) {
      $visited_rooms = $memory['visited_rooms'] ?? [];
      $connections = $snapshot['connected_rooms'] ?? [];

      foreach ($connections as $connection) {
        $target_room_id = (string) ($connection['room_id'] ?? '');
        if ($target_room_id !== '' && !in_array($target_room_id, $visited_rooms, TRUE)) {
          return [
            'type' => 'intent',
            'reason' => 'Move to a new room to advance active quest: ' . $objective_summary,
            'intent' => [
              'type' => 'transition',
              'actor' => $actor_id,
              'params' => [
                'target_room_id' => $target_room_id,
                'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
              ],
            ],
          ];
        }
      }
    }

    return NULL;
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

  /**
   * Build a quest-focused NPC prompt.
   */
  protected function buildQuestProgressInquiry(array $profile, array $npc, array $quest_focus): string {
    $character_name = trim((string) ($profile['character_name'] ?? $profile['persona']['name'] ?? ''));
    $npc_name = trim((string) (
      $npc['name']
      ?? $npc['display_name']
      ?? $npc['state']['metadata']['display_name']
      ?? $npc['profile']['display_name']
      ?? 'friend'
    ));
    $traveler_name = $character_name !== '' ? $character_name : 'a traveler';
    $quest_name = trim((string) ($quest_focus['quest_name'] ?? 'this quest'));
    $objective = trim((string) ($quest_focus['objective'] ?? 'the next step'));

    return sprintf(
      'Hello %s. I am %s, working on %s. My next objective is %s. What should I do next, and what in this room can help me advance it?',
      $npc_name !== '' ? $npc_name : 'there',
      $traveler_name,
      $quest_name,
      $objective !== '' ? $objective : 'the next step'
    );
  }

  /**
   * Build a follow-up line when an NPC just provided actionable guidance.
   */
  protected function buildLeadFollowUpPrompt(array $profile, array $npc, array $pending_lead): string {
    $character_name = trim((string) ($profile['character_name'] ?? $profile['persona']['name'] ?? ''));
    $npc_name = trim((string) (
      $npc['name']
      ?? $npc['display_name']
      ?? $npc['state']['metadata']['display_name']
      ?? $npc['profile']['display_name']
      ?? 'friend'
    ));
    $traveler_name = $character_name !== '' ? $character_name : 'a traveler';
    $excerpt = trim((string) ($pending_lead['excerpt'] ?? ''));
    if (strlen($excerpt) > 180) {
      $excerpt = rtrim(substr($excerpt, 0, 177)) . '...';
    }

    return sprintf(
      'Thanks, %s. I am %s. You mentioned: "%s" What should I check first, and who or where should I go next?',
      $npc_name !== '' ? $npc_name : 'friend',
      $traveler_name,
      $excerpt !== '' ? $excerpt : 'the lead you just gave me'
    );
  }

  /**
   * Load the most relevant active quest and current objective.
   */
  protected function resolveQuestFocus(int $campaign_id, int $character_id): ?array {
    if ($campaign_id <= 0 || $character_id <= 0 || !$this->questTracker) {
      return NULL;
    }

    $quests = $this->questTracker->getActiveQuests($campaign_id, $character_id);
    if ($quests === []) {
      return NULL;
    }

    usort($quests, static function (array $a, array $b): int {
      return ((int) ($b['last_updated'] ?? 0)) <=> ((int) ($a['last_updated'] ?? 0));
    });

    foreach ($quests as $quest) {
      $current_phase = max(1, (int) ($quest['current_phase'] ?? 1));
      $objective = $this->extractFirstIncompleteObjective($quest, $current_phase);
      if ($objective !== '') {
        return [
          'quest_id' => (string) ($quest['quest_id'] ?? ''),
          'quest_name' => trim((string) ($quest['quest_name'] ?? $quest['quest_id'] ?? 'Active quest')),
          'objective' => $objective,
        ];
      }
    }

    return NULL;
  }

  /**
   * Extract the first incomplete objective description for a phase.
   */
  protected function extractFirstIncompleteObjective(array $quest, int $phase): string {
    $objective_states = json_decode((string) ($quest['objective_states'] ?? '[]'), TRUE);
    $generated_objectives = json_decode((string) ($quest['generated_objectives'] ?? '[]'), TRUE);
    $phase_rows = is_array($objective_states) && $objective_states !== []
      ? $objective_states
      : (is_array($generated_objectives) ? $generated_objectives : []);

    foreach ($phase_rows as $phase_row) {
      if ((int) ($phase_row['phase'] ?? 0) !== $phase) {
        continue;
      }

      foreach (($phase_row['objectives'] ?? []) as $objective) {
        if (!is_array($objective) || !empty($objective['completed'])) {
          continue;
        }

        $target_count = (int) ($objective['target_count'] ?? 0);
        $current = (int) ($objective['current'] ?? 0);
        if ($target_count > 0 && $current >= $target_count) {
          continue;
        }

        $description = trim((string) ($objective['description'] ?? $objective['objective_id'] ?? ''));
        if ($description !== '') {
          return $description;
        }
      }
    }

    return '';
  }

  /**
   * Build a concise quest summary for decision reasons.
   */
  protected function buildQuestObjectiveSummary(array $quest_focus): string {
    $quest_name = trim((string) ($quest_focus['quest_name'] ?? 'Active quest'));
    $objective = trim((string) ($quest_focus['objective'] ?? 'advance the current objective'));
    return sprintf('%s — %s', $quest_name, $objective);
  }

  /**
   * Heuristic: objective text implies the room should be searched/investigated.
   */
  protected function objectiveSuggestsSearch(string $objective_text): bool {
    foreach (['search', 'find', 'recover', 'locate', 'investigate', 'inspect', 'explore', 'look', 'track', 'collect', 'gather', 'retrieve'] as $keyword) {
      if ($objective_text !== '' && str_contains($objective_text, $keyword)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Heuristic: objective text implies the next step is to ask or report.
   */
  protected function objectiveSuggestsConversation(string $objective_text): bool {
    foreach (['ask', 'speak', 'talk', 'report', 'inform', 'meet', 'convince', 'question', 'interview', 'learn from'] as $keyword) {
      if ($objective_text !== '' && str_contains($objective_text, $keyword)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Heuristic: objective text implies moving onward is the next action.
   */
  protected function objectiveSuggestsMovement(string $objective_text): bool {
    foreach (['reach', 'enter', 'travel', 'journey', 'head', 'go to', 'delve', 'return to', 'make your way'] as $keyword) {
      if ($objective_text !== '' && str_contains($objective_text, $keyword)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
