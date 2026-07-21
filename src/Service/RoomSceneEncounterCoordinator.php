<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Coordinates room-scene encounter progression and reseed safeguards.
 */
class RoomSceneEncounterCoordinator {

  /**
   * Determine whether the current encounter context is room-scene mode.
   */
  public function isRoomSceneMode(array $game_state): bool {
    $mode = strtolower(trim((string) ($game_state['encounter_context']['mode'] ?? '')));
    if ($mode === 'room_scene') {
      return TRUE;
    }

    return $mode === ''
      && empty($game_state['encounter_id'])
      && !empty($game_state['encounter_context']['room_id']);
  }

  /**
   * Build follow-up system prompt for partial room-scene turns.
   */
  public function buildRemainingRoomSceneActionPrompt(
    ?string $actor_id,
    array $game_state,
    array $dungeon_data,
    callable $resolve_entity_name
  ): ?array {
    if (!$this->isRoomSceneMode($game_state) || !$actor_id) {
      return NULL;
    }

    $remaining_actions = (int) ($game_state['turn']['actions_remaining'] ?? 0);
    if ($remaining_actions <= 0) {
      return NULL;
    }

    $current_turn_actor = (string) ($game_state['turn']['entity'] ?? '');
    if ($current_turn_actor !== '' && $current_turn_actor !== $actor_id) {
      return NULL;
    }

    $actor_name = $resolve_entity_name($actor_id, $game_state, $dungeon_data);
    $turn_index = isset($game_state['turn']['index']) && is_numeric($game_state['turn']['index']) ? (int) $game_state['turn']['index'] : NULL;
    $round = isset($game_state['round']) && is_numeric($game_state['round']) ? (int) $game_state['round'] : NULL;
    $line_id = sprintf(
      'room-scene-actions-%s-%s-%s',
      $actor_id,
      $round ?? 'unknown',
      $turn_index ?? 'unknown'
    );

    return [
      'speaker' => 'System',
      'message' => sprintf(
        '%s still has %d action(s) this turn. What do you want to do next? If you only wanted to chat, use Delay to hold until the end of the round.',
        $actor_name !== '' ? $actor_name : 'This character',
        $remaining_actions
      ),
      'type' => 'system',
      'line_id' => $line_id,
      'lineId' => $line_id,
      'round' => $round,
      'turn_index' => $turn_index,
      'actor_name' => $actor_name,
      'turn_name' => $actor_name,
      'turn_role' => 'player',
      'remaining_actions' => $remaining_actions,
      'suggested_action' => 'delay',
    ];
  }

  /**
   * Starts or resumes room-scene encounter framework state.
   */
  public function startRoomSceneEncounter(
    ?string $actor_id,
    string $room_id,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    ?array $room,
    ?string $narration,
    string $start_missing_player_error_code,
    callable $build_room_encounter_turn_order,
    callable $assert_initiative_has_player,
    callable $build_room_scene_encounter_participants,
    callable $create_encounter,
    callable $load_canonical_turn_state,
    callable $sync_game_state_with_canonical_turn,
    callable $build_round_start_events,
    callable $build_turn_start_events,
    callable $build_turn_start_search_events
  ): array {
    $initiative_order = $build_room_encounter_turn_order($dungeon_data, $room_id, $actor_id);
    $assert_initiative_has_player(
      $initiative_order,
      sprintf('%s:%s', $start_missing_player_error_code, $room_id)
    );
    $participants = $build_room_scene_encounter_participants($dungeon_data, $initiative_order);
    $encounter_id = $create_encounter(
      $campaign_id > 0 ? $campaign_id : NULL,
      $room_id,
      $participants,
      NULL
    );
    $canonical_turn = $load_canonical_turn_state($encounter_id);
    if ($canonical_turn === NULL) {
      throw new \RuntimeException('Failed to initialize canonical room-scene encounter state.');
    }

    $game_state['phase'] = 'encounter';
    $sync_game_state_with_canonical_turn($game_state, $canonical_turn);
    $game_state['round'] = (int) ($canonical_turn['round'] ?? 1);
    $game_state['encounter_context'] = [
      'room_id' => $room_id,
      'mode' => 'room_scene',
      'started_at' => date('c'),
    ];
    $game_state['encounter_id'] = $encounter_id;
    $initiative_order = is_array($game_state['initiative_order'] ?? NULL) ? $game_state['initiative_order'] : $initiative_order;

    $event_type = $narration === NULL ? 'encounter_framework_started' : 'encounter_framework_resumed';
    $events = [
      GameEventLogger::buildEvent($event_type, 'encounter', $actor_id, [
        'encounter_id' => $encounter_id,
        'room_id' => $room_id,
        'participants' => count($initiative_order),
      ], $narration ?? sprintf('The scene in %s is active.', (string) (($room['name'] ?? NULL) ?: $room_id))),
    ];
    $events = array_merge($events, $build_round_start_events(1, $game_state, $dungeon_data, $campaign_id, $room_id));
    if (!empty($game_state['turn']['entity'])) {
      $events = array_merge($events, $build_turn_start_events((string) $game_state['turn']['entity'], $game_state, $dungeon_data, $campaign_id, $room_id));
      $events = array_merge($events, $build_turn_start_search_events((string) $game_state['turn']['entity'], $game_state, $dungeon_data, $campaign_id));
    }

    return $events;
  }

  /**
   * Advance non-player actors until the next player actor turn.
   */
  public function advanceNonPlayerTurnsToNextPlayer(
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    string $missing_player_error_code,
    callable $is_room_scene_mode,
    callable $assert_initiative_has_player,
    callable $resolve_initiative_participant_team,
    callable $pass_room_actor_turn,
    callable $process_end_turn
  ): array {
    if (!$is_room_scene_mode($game_state)) {
      return ['events' => []];
    }
    $assert_initiative_has_player(
      (array) ($game_state['initiative_order'] ?? []),
      $missing_player_error_code
    );

    $events = [];
    $safety = 0;
    while ($safety < 12) {
      $turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
      $current_entity = trim((string) ($turn['entity'] ?? ''));
      if ($current_entity === '') {
        break;
      }

      $current_team = $resolve_initiative_participant_team($current_entity, $game_state);
      if ($current_team === 'player') {
        break;
      }

      $actor_result = $pass_room_actor_turn($current_entity, $game_state, $dungeon_data, $campaign_id);
      $events = array_merge($events, $actor_result['events'] ?? []);

      $turn_result = $process_end_turn((int) ($game_state['encounter_id'] ?? 0), $current_entity, $game_state, $dungeon_data, $campaign_id);
      $events = array_merge($events, $turn_result['npc_events'] ?? []);
      $safety++;

      $next_entity = trim((string) ($game_state['turn']['entity'] ?? ''));
      if ($next_entity === '' || $resolve_initiative_participant_team($next_entity, $game_state) === 'player') {
        break;
      }
    }

    return ['events' => $events];
  }

  /**
   * Ensure room-scene initiative includes at least one player participant.
   */
  public function ensureRoomScenePlayerParticipant(
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    string $reseed_missing_room_error_code,
    string $reseed_no_player_candidate_error_code,
    callable $is_room_scene_mode,
    callable $initiative_order_has_player,
    callable $build_room_encounter_turn_order,
    callable $assert_initiative_has_player,
    callable $end_encounter,
    callable $start_room_scene_encounter
  ): array {
    if (!$is_room_scene_mode($game_state)) {
      return ['events' => []];
    }

    $current_initiative = (array) ($game_state['initiative_order'] ?? []);
    if ($initiative_order_has_player($current_initiative)) {
      return ['events' => []];
    }

    $room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ($dungeon_data['active_room_id'] ?? '')));
    if ($room_id === '') {
      throw new \RuntimeException($reseed_missing_room_error_code);
    }

    $candidate_initiative = $build_room_encounter_turn_order($dungeon_data, $room_id);
    $assert_initiative_has_player(
      $candidate_initiative,
      sprintf('%s:%s', $reseed_no_player_candidate_error_code, $room_id)
    );

    $previous_encounter_id = (int) ($game_state['encounter_id'] ?? 0);
    if ($previous_encounter_id > 0) {
      $end_encounter(
        $previous_encounter_id,
        'aborted',
        'room-scene reseed: initiative missing player participant'
      );
    }

    $events = $start_room_scene_encounter(
      NULL,
      $room_id,
      $game_state,
      $dungeon_data,
      $campaign_id,
      NULL,
      'Room-scene encounter reseeded after missing player participants in initiative.'
    );
    $events[] = GameEventLogger::buildEvent('room_scene_player_reseeded', 'encounter', NULL, [
      'room_id' => $room_id,
      'previous_encounter_id' => $previous_encounter_id ?: NULL,
      'new_encounter_id' => (int) ($game_state['encounter_id'] ?? 0),
      'reason' => 'initiative_missing_player_participant',
    ]);

    return ['events' => $events];
  }

  public function initiativeOrderHasPlayer(array $initiative_order): bool {
    foreach ($initiative_order as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      if (strtolower(trim((string) ($participant['team'] ?? ''))) === 'player') {
        return TRUE;
      }
    }

    return FALSE;
  }

  public function assertInitiativeHasPlayer(array $initiative_order, string $error_code): void {
    if (!$this->initiativeOrderHasPlayer($initiative_order)) {
      throw new \RuntimeException($error_code);
    }
  }

  public function resolveInitiativeParticipantTeam(string $entity_id, array $game_state): string {
    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      if ((string) ($participant['entity_id'] ?? '') !== $entity_id) {
        continue;
      }
      return strtolower(trim((string) ($participant['team'] ?? '')));
    }

    return '';
  }

  /**
   * Build persisted combat participants for room-scene canonical encounter state.
   */
  public function buildRoomSceneEncounterParticipants(array $dungeon_data, array $initiative_order): array {
    $entities_by_id = [];
    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($entity_id !== '') {
        $entities_by_id[$entity_id] = $entity;
      }
    }

    $participants = [];
    foreach ($initiative_order as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $entity_id = (string) ($participant['entity_id'] ?? '');
      if ($entity_id === '') {
        continue;
      }

      $entity = $entities_by_id[$entity_id] ?? [];
      $stats = is_array($entity['state']['metadata']['stats'] ?? NULL) ? $entity['state']['metadata']['stats'] : [];
      $current_hp = $entity['state']['hit_points']['current']
        ?? $stats['currentHp']
        ?? 10;
      $max_hp = $entity['state']['hit_points']['max']
        ?? $stats['maxHp']
        ?? max(1, (int) $current_hp);
      $ac = $entity['state']['armor_class']
        ?? $stats['ac']
        ?? 10;

      $content_type = (string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''));
      $content_id = (string) ($entity['entity_ref']['content_id'] ?? $entity_id);
      $perception = isset($participant['perception']) && is_numeric($participant['perception'])
        ? (int) $participant['perception']
        : 0;

      $participants[] = [
        'entity_id' => $entity_id,
        'entity_ref' => [
          'content_type' => $content_type !== '' ? $content_type : (($participant['team'] ?? '') === 'player' ? 'player_character' : 'npc'),
          'content_id' => $content_id,
          'perception_modifier' => $perception,
          'heritage' => is_string($entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL))
            ? strtolower(trim((string) ($entity['heritage'] ?? $entity['state']['heritage'])))
            : NULL,
        ],
        'team' => (string) ($participant['team'] ?? 'npc'),
        'name' => (string) ($participant['name'] ?? $entity_id),
        'status' => 'active',
        'initiative' => (int) ($participant['initiative_total'] ?? $participant['initiative'] ?? 0),
        'initiative_roll' => (int) ($participant['initiative_roll'] ?? 1),
        'ac' => (int) $ac,
        'hp' => (int) $current_hp,
        'max_hp' => (int) $max_hp,
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
        'reaction_available' => 1,
        'position_q' => (int) ($participant['position_q'] ?? 0),
        'position_r' => (int) ($participant['position_r'] ?? 0),
        'is_defeated' => !empty($entity['state']['is_defeated']),
      ];
    }

    if ($participants === []) {
      throw new \RuntimeException('Cannot initialize room-scene encounter without participants.');
    }

    return $participants;
  }

  /**
   * Builds the room encounter turn order for every actor present in the room.
   */
  public function buildRoomEncounterTurnOrder(array $dungeon_data, string $room_id, ?string $actor_id, callable $roll_pathfinder_die): array {
    $participants = [];

    foreach (($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity) || (string) ($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $instance_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($instance_id === '') {
        continue;
      }
      $current_hp = $entity['state']['hit_points']['current']
        ?? $entity['state']['metadata']['stats']['currentHp']
        ?? NULL;
      if (!empty($entity['state']['is_defeated']) || (is_numeric($current_hp) && (int) $current_hp <= 0)) {
        continue;
      }
      $content_type = (string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''));
      $raw_team = strtolower(trim((string) (
        $entity['state']['metadata']['team']
        ?? $entity['state']['team']
        ?? ''
      )));
      $is_actor_type = in_array($content_type, ['player_character', 'npc', 'creature', 'character', 'monster', 'hazard'], TRUE);
      $has_actor_team = in_array($raw_team, ['player', 'player_character', 'pc', 'npc', 'enemy', 'hostile', 'monster', 'ally', 'friendly', 'companion'], TRUE);
      if (!$is_actor_type && !$has_actor_team) {
        continue;
      }
      $team = $content_type === 'player_character' || in_array($raw_team, ['player', 'player_character', 'pc'], TRUE)
        ? 'player'
        : 'npc';
      $perception = $this->resolveEntityPerceptionModifier($entity);
      $initiative_roll = (int) $roll_pathfinder_die(20);
      if ($initiative_roll <= 0) {
        $initiative_roll = 1;
      }
      $initiative_total = $initiative_roll + $perception;
      $participants[] = [
        'entity_id' => $instance_id,
        'team' => $team,
        'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? $instance_id),
        'perception' => $perception,
        'initiative_roll' => $initiative_roll,
        'initiative_total' => $initiative_total,
        'initiative' => $initiative_total,
        'position_q' => $entity['placement']['hex']['q'] ?? 0,
        'position_r' => $entity['placement']['hex']['r'] ?? 0,
      ];
    }

    if ($actor_id && !array_filter($participants, static fn(array $participant): bool => (string) ($participant['entity_id'] ?? '') === $actor_id)) {
      $participants[] = [
        'entity_id' => $actor_id,
        'team' => 'player',
        'name' => $actor_id,
        'perception' => 0,
        'initiative_roll' => 1,
        'initiative_total' => 1,
        'initiative' => 1,
        'position_q' => 0,
        'position_r' => 0,
      ];
    }

    usort($participants, static function (array $left, array $right): int {
      $initiative_diff = (int) ($right['initiative_total'] ?? $right['initiative'] ?? 0) - (int) ($left['initiative_total'] ?? $left['initiative'] ?? 0);
      if ($initiative_diff !== 0) {
        return $initiative_diff;
      }

      $perception_diff = (int) ($right['perception'] ?? 0) - (int) ($left['perception'] ?? 0);
      if ($perception_diff !== 0) {
        return $perception_diff;
      }

      if (($left['team'] ?? '') !== ($right['team'] ?? '')) {
        return ($left['team'] ?? '') === 'player' ? -1 : 1;
      }

      return strcmp((string) ($left['entity_id'] ?? ''), (string) ($right['entity_id'] ?? ''));
    });

    return array_values($participants);
  }

  /**
   * Resolve perception modifier from entity runtime state.
   */
  protected function resolveEntityPerceptionModifier(array $entity): int {
    $stats = is_array($entity['state']['metadata']['stats'] ?? NULL) ? $entity['state']['metadata']['stats'] : [];
    if (isset($stats['perception']) && is_numeric($stats['perception'])) {
      return (int) $stats['perception'];
    }
    if (isset($entity['state']['perception']) && is_numeric($entity['state']['perception'])) {
      return (int) $entity['state']['perception'];
    }
    if (isset($entity['perception']) && is_numeric($entity['perception'])) {
      return (int) $entity['perception'];
    }
    return 0;
  }

}
