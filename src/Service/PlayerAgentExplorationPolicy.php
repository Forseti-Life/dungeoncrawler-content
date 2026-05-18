<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Deterministic exploration policy for the player agent.
 */
class PlayerAgentExplorationPolicy implements PlayerAgentPolicyInterface {

  protected const PRIORITY_QUEST_SEARCH = 10;
  protected const PRIORITY_QUEST_TALK = 20;
  protected const PRIORITY_QUEST_SEARCH_FALLBACK = 30;
  protected const PRIORITY_QUEST_TRANSITION = 40;
  protected const PRIORITY_LEAD_FOLLOW_UP = 50;
  protected const PRIORITY_ROOM_SEARCH = 60;
  protected const PRIORITY_UNVISITED_NPC = 70;
  protected const PRIORITY_ROOM_TRANSITION = 80;
  protected const PRIORITY_PAID_WORK_FALLBACK = 90;
  protected const PRIORITY_REST = 100;
  protected const PRIORITY_WAIT = 110;

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

    $pending_lead = is_array($memory['pending_conversation_lead'] ?? NULL) ? $memory['pending_conversation_lead'] : NULL;
    $follow_up_decision = $this->chooseConversationFollowUpAction(
      $profile,
      $actor_id,
      $available_actions,
      $current_room_id,
      $visible_npcs,
      $memory,
      $pending_lead
    );
    if ($follow_up_decision !== NULL) {
      return $follow_up_decision;
    }

    if (in_array('search', $available_actions, TRUE)
      && $current_room_id !== ''
      && !in_array($current_room_id, $memory['searched_rooms'] ?? [], TRUE)) {
      return $this->attachDecisionMeta([
        'type' => 'intent',
        'reason' => 'Search the current room before moving on.',
        'intent' => [
          'type' => 'search',
          'actor' => $actor_id,
          'params' => [],
        ],
      ], 'room_search', self::PRIORITY_ROOM_SEARCH, $current_room_id);
    }

    if (in_array('talk', $available_actions, TRUE)) {

      foreach ($visible_npcs as $npc) {
        $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
        if ($npc_id === '' || in_array($npc_id, $memory['talked_entities'] ?? [], TRUE)) {
          continue;
        }

        return $this->attachDecisionMeta([
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
        ], 'unvisited_npc_talk', self::PRIORITY_UNVISITED_NPC, $current_room_id, $npc_id);
      }

    }

    if (in_array('transition', $available_actions, TRUE)) {
      $visited_rooms = $memory['visited_rooms'] ?? [];
      $connections = $snapshot['connected_rooms'] ?? [];

      foreach ($connections as $connection) {
        $target_room_id = (string) ($connection['room_id'] ?? '');
        if ($target_room_id !== '' && !in_array($target_room_id, $visited_rooms, TRUE)) {
          return $this->attachDecisionMeta([
            'type' => 'intent',
            'reason' => 'Advance exploration into an unvisited connected room.',
            'intent' => [
              'type' => 'transition',
              'actor' => $actor_id,
              'params' => [
                'target_room_id' => $target_room_id,
              ],
            ],
          ], 'room_transition', self::PRIORITY_ROOM_TRANSITION, $current_room_id, $target_room_id);
        }
      }

      if (!empty($connections[0]['room_id'])) {
        return $this->attachDecisionMeta([
          'type' => 'intent',
          'reason' => 'Continue exploration through the first available connection.',
          'intent' => [
            'type' => 'transition',
            'actor' => $actor_id,
            'params' => [
                'target_room_id' => (string) $connections[0]['room_id'],
              ],
            ],
        ], 'room_transition', self::PRIORITY_ROOM_TRANSITION, $current_room_id, (string) $connections[0]['room_id']);
      }
    }

    if (in_array('talk', $available_actions, TRUE)) {
      $consulted_rooms = is_array($memory['consulted_rooms'] ?? NULL) ? $memory['consulted_rooms'] : [];
      $fallback_npc = $visible_npcs[0] ?? NULL;
      $fallback_npc_id = (string) ($fallback_npc['entity_instance_id'] ?? $fallback_npc['instance_id'] ?? $fallback_npc['id'] ?? '');
      if ($fallback_npc_id !== ''
        && ($current_room_id === '' || !in_array($current_room_id, $consulted_rooms, TRUE))) {
        return $this->attachDecisionMeta([
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
        ], 'paid_work_fallback', self::PRIORITY_PAID_WORK_FALLBACK, $current_room_id, $fallback_npc_id);
      }
    }

    if (in_array('rest', $available_actions, TRUE)) {
      $rested_rooms = is_array($memory['rested_rooms'] ?? NULL) ? $memory['rested_rooms'] : [];
      if ($current_room_id !== '' && in_array($current_room_id, $rested_rooms, TRUE)) {
          return $this->attachDecisionMeta(['type' => 'wait', 'reason' => 'No new exploration action is available in the current room.'], 'wait', self::PRIORITY_WAIT, $current_room_id);
      }

      return $this->attachDecisionMeta([
        'type' => 'intent',
        'reason' => 'No higher-priority exploration action is available; take a short rest.',
        'intent' => [
          'type' => 'rest',
          'actor' => $actor_id,
          'params' => [
            'rest_type' => 'short',
          ],
        ],
      ], 'rest', self::PRIORITY_REST, $current_room_id);
    }

    return $this->attachDecisionMeta(['type' => 'wait', 'reason' => 'No safe exploration action was selected.'], 'wait', self::PRIORITY_WAIT, $current_room_id);
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
    array $memory,
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
    if ((int) ($pending_lead['follow_up_attempts'] ?? 0) >= 1) {
      return NULL;
    }
    $lead_signature = trim((string) ($pending_lead['signature'] ?? ''));
    if ($lead_signature !== '' && in_array($lead_signature, $memory['exhausted_conversation_leads'] ?? [], TRUE)) {
      return NULL;
    }

    foreach ($visible_npcs as $npc) {
      $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
      if ($npc_id !== $target_npc_id) {
        continue;
      }

      return $this->attachDecisionMeta([
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
      ], 'lead_follow_up', self::PRIORITY_LEAD_FOLLOW_UP, $current_room_id, $target_npc_id, [
        'lead_signature' => $lead_signature,
      ]);
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
    $objective_type = strtolower((string) ($quest_focus['objective_type'] ?? ''));
    $objective_summary = $this->buildQuestObjectiveSummary($quest_focus);
    $should_talk_for_quest = $this->objectiveSuggestsConversation($objective_text);
    $should_search_for_quest_item = $this->objectiveSuggestsQuestItemCollection($quest_focus);

    if ($should_search_for_quest_item
      && in_array('search', $available_actions, TRUE)
      && $current_room_id !== ''
      && !in_array($current_room_id, $memory['searched_rooms'] ?? [], TRUE)) {
      return $this->attachDecisionMeta([
        'type' => 'intent',
        'reason' => 'Search for active quest leads, locations, and target items first: ' . $objective_summary,
        'intent' => [
          'type' => 'search',
          'actor' => $actor_id,
          'params' => [
            'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
            'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
            'objective_type' => $objective_type,
            'objective_item' => (string) ($quest_focus['objective_item'] ?? ''),
            'objective_target' => (string) ($quest_focus['objective_target'] ?? ''),
          ],
        ],
      ], 'quest_search', self::PRIORITY_QUEST_SEARCH, $current_room_id, NULL, [
        'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
        'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
      ]);
    }

    if ($should_talk_for_quest && in_array('talk', $available_actions, TRUE)) {
      $targeted_npc = $this->findQuestTargetNpc($visible_npcs, $quest_focus);
      $npc_candidates = $targeted_npc !== NULL ? [$targeted_npc] : $visible_npcs;
      foreach ($npc_candidates as $npc) {
        $npc_id = (string) ($npc['entity_instance_id'] ?? $npc['instance_id'] ?? $npc['id'] ?? '');
        if ($npc_id === '' || in_array($npc_id, $memory['talked_entities'] ?? [], TRUE)) {
          continue;
        }

        return $this->attachDecisionMeta([
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
              'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
            ],
          ],
        ], 'quest_talk', self::PRIORITY_QUEST_TALK, $current_room_id, $npc_id, [
          'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
          'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
        ]);
      }
    }

    if (in_array('search', $available_actions, TRUE)
      && $current_room_id !== ''
      && !in_array($current_room_id, $memory['searched_rooms'] ?? [], TRUE)
      && ($this->objectiveSuggestsSearch($objective_text) || $visible_npcs === [])) {
      return $this->attachDecisionMeta([
        'type' => 'intent',
        'reason' => 'Search the room to advance active quest: ' . $objective_summary,
        'intent' => [
          'type' => 'search',
          'actor' => $actor_id,
          'params' => [
            'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
            'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
            'objective_type' => $objective_type,
            'objective_item' => (string) ($quest_focus['objective_item'] ?? ''),
          ],
        ],
      ], 'quest_search_fallback', self::PRIORITY_QUEST_SEARCH_FALLBACK, $current_room_id, NULL, [
        'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
        'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
      ]);
    }

    if (in_array('transition', $available_actions, TRUE)
      && ($this->objectiveSuggestsMovement($objective_text) || !$should_talk_for_quest)) {
      $visited_rooms = $memory['visited_rooms'] ?? [];
      $connections = $snapshot['connected_rooms'] ?? [];

      foreach ($connections as $connection) {
        $target_room_id = (string) ($connection['room_id'] ?? '');
        if ($target_room_id !== '' && !in_array($target_room_id, $visited_rooms, TRUE)) {
          return $this->attachDecisionMeta([
            'type' => 'intent',
            'reason' => 'Move to a new room to advance active quest: ' . $objective_summary,
            'intent' => [
              'type' => 'transition',
              'actor' => $actor_id,
              'params' => [
                'target_room_id' => $target_room_id,
                'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
                'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
                'objective_type' => $objective_type,
              ],
            ],
          ], 'quest_transition', self::PRIORITY_QUEST_TRANSITION, $current_room_id, $target_room_id, [
            'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
            'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
          ]);
        }
      }

      if (!empty($connections[0]['room_id'])) {
        return $this->attachDecisionMeta([
          'type' => 'intent',
          'reason' => 'Visit the next quest lead or location target: ' . $objective_summary,
          'intent' => [
            'type' => 'transition',
            'actor' => $actor_id,
            'params' => [
              'target_room_id' => (string) $connections[0]['room_id'],
              'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
              'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
                'objective_type' => $objective_type,
              ],
            ],
        ], 'quest_transition', self::PRIORITY_QUEST_TRANSITION, $current_room_id, (string) $connections[0]['room_id'], [
          'quest_id' => (string) ($quest_focus['quest_id'] ?? ''),
          'objective_id' => (string) ($quest_focus['objective_id'] ?? ''),
        ]);
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
    $target = trim((string) ($quest_focus['objective_target'] ?? ''));

    return sprintf(
      'Hello %s. I am %s, working on %s. My next objective is %s.%s What should I do next, and what in this room can help me advance it?',
      $npc_name !== '' ? $npc_name : 'there',
      $traveler_name,
      $quest_name,
      $objective !== '' ? $objective : 'the next step',
      $target !== '' ? ' I am specifically following the lead tied to ' . $target . '.' : ''
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
      if ($objective !== NULL) {
        return [
          'quest_id' => (string) ($quest['quest_id'] ?? ''),
          'quest_name' => trim((string) ($quest['quest_name'] ?? $quest['quest_id'] ?? 'Active quest')),
          'objective' => trim((string) ($objective['description'] ?? $objective['objective_id'] ?? '')),
          'objective_id' => trim((string) ($objective['objective_id'] ?? '')),
          'objective_type' => trim((string) ($objective['type'] ?? $objective['objective_type'] ?? '')),
          'objective_item' => trim((string) ($objective['item'] ?? '')),
          'objective_target' => trim((string) ($objective['target'] ?? $objective['npc_ref'] ?? '')),
          'objective_current' => (int) ($objective['current'] ?? 0),
          'objective_target_count' => (int) ($objective['target_count'] ?? 0),
        ];
      }
    }

    return NULL;
  }

  /**
   * Extract the first incomplete objective description for a phase.
   */
  protected function extractFirstIncompleteObjective(array $quest, int $phase): ?array {
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
          return $objective;
        }
      }
    }

    return NULL;
  }

  /**
   * Build a concise quest summary for decision reasons.
   */
  protected function buildQuestObjectiveSummary(array $quest_focus): string {
    $quest_name = trim((string) ($quest_focus['quest_name'] ?? 'Active quest'));
    $objective = trim((string) ($quest_focus['objective'] ?? 'advance the current objective'));
    $item = trim((string) ($quest_focus['objective_item'] ?? ''));
    $target = trim((string) ($quest_focus['objective_target'] ?? ''));
    $current = (int) ($quest_focus['objective_current'] ?? 0);
    $target_count = (int) ($quest_focus['objective_target_count'] ?? 0);
    $progress = $target_count > 0 ? sprintf(' (%d/%d)', $current, $target_count) : '';
    $item_suffix = $item !== '' ? sprintf(' [item: %s]', $item) : '';
    $target_suffix = $target !== '' ? sprintf(' [target: %s]', $target) : '';
    return sprintf('%s — %s%s%s%s', $quest_name, $objective, $progress, $item_suffix, $target_suffix);
  }

  /**
   * Prefer the specific NPC named by the quest objective when present in the room.
   */
  protected function findQuestTargetNpc(array $visible_npcs, array $quest_focus): ?array {
    $objective_target = strtolower(trim((string) ($quest_focus['objective_target'] ?? '')));
    if ($objective_target === '') {
      return NULL;
    }

    foreach ($visible_npcs as $npc) {
      $candidate_tokens = [
        strtolower(trim((string) ($npc['entity_instance_id'] ?? ''))),
        strtolower(trim((string) ($npc['instance_id'] ?? ''))),
        strtolower(trim((string) ($npc['id'] ?? ''))),
        strtolower(trim((string) ($npc['name'] ?? ''))),
        strtolower(trim((string) ($npc['display_name'] ?? ''))),
        strtolower(trim((string) ($npc['state']['metadata']['display_name'] ?? ''))),
        strtolower(trim((string) ($npc['content_id'] ?? ''))),
      ];

      foreach (array_filter($candidate_tokens) as $candidate) {
        if ($candidate === $objective_target || str_contains($candidate, $objective_target) || str_contains($objective_target, $candidate)) {
          return $npc;
        }
      }
    }

    return NULL;
  }

  /**
   * Heuristic: objective metadata indicates the primary blocker is a collectible, quest item, or target.
   */
  protected function objectiveSuggestsQuestItemCollection(array $quest_focus): bool {
    $objective_type = strtolower(trim((string) ($quest_focus['objective_type'] ?? '')));
    $objective_text = strtolower(trim((string) ($quest_focus['objective'] ?? '')));
    $objective_item = trim((string) ($quest_focus['objective_item'] ?? ''));

    if (in_array($objective_type, ['interact', 'talk', 'conversation', 'report'], TRUE)
      || $this->objectiveSuggestsConversation($objective_text)) {
      return FALSE;
    }

    if (in_array($objective_type, ['collect', 'gather', 'recover', 'retrieve'], TRUE)) {
      return TRUE;
    }

    if ($objective_item !== '') {
      return TRUE;
    }

    foreach (['collect', 'gather', 'recover', 'retrieve', 'find', 'locate', 'missing', 'quest item'] as $keyword) {
      if ($objective_text !== '' && str_contains($objective_text, $keyword)) {
        return TRUE;
      }
    }

    return FALSE;
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

  /**
   * Attach explicit decision metadata for downstream handoffs.
   */
  protected function attachDecisionMeta(array $decision, string $stage, int $priority, string $room_id = '', ?string $target = NULL, array $extra = []): array {
    $decision['decision_meta'] = array_filter([
      'stage' => $stage,
      'priority' => $priority,
      'room_id' => $room_id !== '' ? $room_id : NULL,
      'target' => $target !== NULL && $target !== '' ? $target : NULL,
    ] + $extra, static fn($value) => $value !== NULL && $value !== '');
    return $decision;
  }

}
