<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Support route execution methods split from EncounterPhaseHandlerRouteExecutionTrait.
 */
trait EncounterPhaseHandlerRouteExecutionSupportTrait {
  /**
   * Normalize combat team aliases to canonical buckets.
   */
  protected function normalizeCombatTeam(?string $team): string {
    $value = strtolower(trim((string) $team));
    if (in_array($value, ['player', 'player_character', 'pc', 'party', 'adventurer', 'hero'], TRUE)) {
      return 'player';
    }
    if (in_array($value, ['ally', 'friendly', 'companion'], TRUE)) {
      return 'ally';
    }
    if (in_array($value, ['enemy', 'hostile', 'monster', 'monsters', 'npc', 'creature'], TRUE)) {
      return 'enemy';
    }
    if (in_array($value, ['neutral', 'indifferent'], TRUE)) {
      return 'neutral';
    }
    return $value;
  }

  protected function initiativeOrderHasPlayer(array $initiative_order): bool {
    return $this->roomSceneEncounterCoordinator->initiativeOrderHasPlayer($initiative_order);
  }

  protected function assertInitiativeHasPlayer(array $initiative_order, string $error_code): void {
    $this->roomSceneEncounterCoordinator->assertInitiativeHasPlayer($initiative_order, $error_code);
  }

  protected function resolveInitiativeParticipantTeam(string $entity_id, array $game_state): string {
    return $this->roomSceneEncounterCoordinator->resolveInitiativeParticipantTeam($entity_id, $game_state);
  }

  /**
   * Build canonical NPC tactical intent contract from psychology + tactical state.
   *
   * The returned envelope is deterministic and reused across all actions in an
   * NPC turn so intent continuity stays stable from action 1/2/3.
   */
  protected function buildNpcTacticalIntentContract(string $entity_id, array $game_state, int $campaign_id): array {
    $npc = $this->findCombatant($entity_id, $game_state);
    $entity_ref = $this->resolveCombatantEntityRef($entity_id, $game_state);
    $profile = $this->loadCombatantPsychologyProfile($entity_id, $game_state, $campaign_id);
    $profile_present = is_array($profile) && $profile !== [];
    $axes = $this->normalizeDecisionPersonalityAxes(is_array($profile['personality_axes'] ?? NULL) ? $profile['personality_axes'] : []);
    $goals = $this->resolveActorGoals($profile);
    $attitude = NULL;
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService) {
      $summary = $actor_disposition->getDispositionSummary(
        $campaign_id,
        $entity_ref !== '' ? $entity_ref : $entity_id,
        is_array($npc) ? $npc : []
      );
      $attitude = $this->normalizeNpcAttitude((string) ($summary['current_attitude'] ?? NULL));
    }
    if ($attitude === NULL) {
      foreach (['current_attitude', 'attitude', 'initial_attitude'] as $attitude_key) {
        $attitude = $this->normalizeNpcAttitude((string) ($profile[$attitude_key] ?? NULL));
        if ($attitude !== NULL) {
          break;
        }
      }
      $attitude = $attitude ?? 'indifferent';
    }
    $boldness = (int) ($axes['boldness'] ?? 5);
    $empathy = (int) ($axes['empathy'] ?? 5);
    $discipline = (int) ($axes['discipline'] ?? 5);
    $cunning = (int) ($axes['cunning'] ?? 5);
    $hp_ratio = $this->hpRatio($npc ?? []);
    $team = strtolower(trim((string) (($npc['team'] ?? ''))));
    $nearest_player = $this->findNearestAlivePlayer($entity_id, $game_state);
    $has_adjacent_player = $this->hasAdjacentAlivePlayer($npc, $game_state);
    $nearest_target = $this->findNearestAliveOpponent($entity_id, $game_state);
    $has_adjacent_target = $this->hasAdjacentAliveOpponent($npc, $game_state);

    $intent = 'aggressive_engage';
    $action_sequence = $has_adjacent_target
      ? ['strike', 'strike', 'strike']
      : ['stride', 'strike', 'strike'];
    $target_strategy = 'nearest';
    $decision_reason = 'Default aggressive engagement: close distance and pressure the nearest threat.';

    if ($nearest_target === NULL) {
      $team_counts = [];
      foreach (($game_state['initiative_order'] ?? []) as $combatant) {
        if (!is_array($combatant) || !empty($combatant['is_defeated'])) {
          continue;
        }
        $normalized_team = $this->normalizeCombatTeam((string) ($combatant['team'] ?? ''));
        $bucket = $normalized_team !== '' ? $normalized_team : 'unknown';
        $team_counts[$bucket] = (int) ($team_counts[$bucket] ?? 0) + 1;
      }
      $this->logger->warning('NPC autoplay resolved no nearest target; defaulting to no_targets intent. actor={actor} team={team} mode={mode} team_counts={team_counts}', [
        'actor' => $entity_id,
        'team' => $team,
        'mode' => (string) ($game_state['encounter_context']['mode'] ?? ''),
        'team_counts' => json_encode($team_counts),
      ]);
      $intent = 'no_targets';
      $action_sequence = ['end_turn'];
      $target_strategy = 'none';
      $decision_reason = 'No valid hostile target is available.';
    }
    elseif (($boldness <= 4 || $this->motivationSignalsSelfPreservation($profile ?? [])) && $hp_ratio <= 0.35) {
      $intent = 'self_preserve';
      $action_sequence = ['stride', 'stride', 'interact'];
      $target_strategy = 'nearest';
      $decision_reason = 'Wounded or survival-motivated profile favors retreat/reposition over direct engagement.';
    }
    elseif (in_array($attitude, ['friendly', 'helpful'], TRUE) && $empathy >= 7 && $has_adjacent_target) {
      $intent = 'deescalate';
      $action_sequence = ['talk', 'stride', 'talk'];
      $target_strategy = 'nearest';
      $decision_reason = 'Friendly and empathetic profile attempts de-escalation before violence.';
    }
    elseif ($cunning >= 7 || $discipline >= 7) {
      $intent = 'finish_weakest';
      $action_sequence = ['strike', 'strike', 'stride'];
      $target_strategy = 'weakest_adjacent';
      $decision_reason = 'High cunning/discipline profile prioritizes focused pressure on weak targets.';
    }
    elseif ($profile_present && !$has_adjacent_target && $this->actorHasGoal($goals, 'gain treasure')) {
      $intent = 'treasure_seek';
      $action_sequence = ['interact', 'stride', 'interact'];
      $target_strategy = 'none';
      $decision_reason = 'Treasure-oriented goals bias this actor toward interact-driven objective play.';
    }

    return [
      'intent' => $intent,
      'action_sequence' => $action_sequence,
      'target_strategy' => $target_strategy,
      'decision_reason' => $decision_reason,
      'decision_basis' => [
        'attitude' => $attitude,
        'team' => $team,
        'axes' => [
          'boldness' => $boldness,
          'empathy' => $empathy,
          'discipline' => $discipline,
          'cunning' => $cunning,
        ],
        'hp_ratio' => $hp_ratio,
        'goals' => $goals,
        'profile_present' => $profile_present,
        'has_adjacent_player' => $has_adjacent_player,
        'nearest_player' => $nearest_player,
        'has_adjacent_target' => $has_adjacent_target,
        'nearest_target' => $nearest_target,
      ],
    ];
  }

  /**
   * Build a read-only next-action recommendation for any actor.
   *
   * This is the dry-run lane of the shared actor decision pipeline. It reuses
   * the same tactical intent contract, target selection and action
   * availability envelope that drive autoplay, but never mutates game state,
   * never emits events and never submits an intent. It is safe to call for
   * player-controlled actors to power "suggest next move".
   *
   * @param string $entity_id
   *   Actor to build a suggestion for. Defaults to the current turn actor.
   * @param array $game_state
   *   Canonical game state (passed by value; never mutated).
   * @param array $dungeon_data
   *   Dungeon data (passed by value; never mutated).
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Suggestion envelope with success flag.
   */
  public function suggestActorAction(string $entity_id, array $game_state, array $dungeon_data, int $campaign_id): array {
    $entity_id = trim($entity_id);
    if ($entity_id === '') {
      $entity_id = trim((string) ($game_state['turn']['entity'] ?? ''));
    }
    if ($entity_id === '') {
      return [
        'success' => FALSE,
        'error' => 'No actor is available to build a suggestion for.',
      ];
    }

    $combatant = $this->findCombatant($entity_id, $game_state);
    if ($combatant === NULL) {
      return [
        'success' => FALSE,
        'error' => sprintf('Actor "%s" is not part of the current initiative order.', $entity_id),
      ];
    }
    if (!empty($combatant['is_defeated'])) {
      return [
        'success' => FALSE,
        'error' => sprintf('Actor "%s" is defeated and cannot act.', $entity_id),
      ];
    }

    // Local read-only projection. When it is not this actor's turn we still
    // produce a hypothetical plan using a full action economy.
    $turn_actor = trim((string) ($game_state['turn']['entity'] ?? ''));
    $is_actor_turn = $turn_actor === $entity_id;
    $projected_state = $game_state;
    if (!is_array($projected_state['turn'] ?? NULL)) {
      $projected_state['turn'] = [];
    }
    $projected_state['turn']['entity'] = $entity_id;
    if (!$is_actor_turn || !is_numeric($projected_state['turn']['actions_remaining'] ?? NULL)) {
      $projected_state['turn']['actions_remaining'] = 3;
    }
    $actions_remaining = max(0, (int) $projected_state['turn']['actions_remaining']);

    $context = $this->buildNpcContext($entity_id, $projected_state, $dungeon_data);
    $allowed_actions = is_array($context['allowed_actions'] ?? NULL)
      ? array_values(array_filter(array_map('strval', $context['allowed_actions'])))
      : [];

    $plan = $this->buildNpcTurnPlan($entity_id, $projected_state, $campaign_id, NULL);
    $intent_contract = is_array($plan['intent_contract'] ?? NULL) ? $plan['intent_contract'] : [];
    $steps = is_array($plan['steps'] ?? NULL) ? $plan['steps'] : [];

    $suggested_steps = [];
    foreach ($steps as $step) {
      if (!is_array($step)) {
        continue;
      }
      $action_type = strtolower(trim((string) ($step['action_type'] ?? '')));
      if ($action_type === '') {
        continue;
      }
      // Never recommend an action the server would reject.
      if ($allowed_actions !== [] && !in_array($action_type, $allowed_actions, TRUE)) {
        continue;
      }
      $target_id = isset($step['target']) && is_scalar($step['target']) ? trim((string) $step['target']) : '';
      $suggested_steps[] = [
        'action_type' => $action_type,
        'target_instance_id' => $target_id !== '' ? $target_id : NULL,
        'target_name' => $target_id !== '' ? $this->resolveSuggestionTargetName($target_id, $game_state) : NULL,
        'decision_reason' => (string) ($step['decision_reason'] ?? ''),
        'decision_basis' => is_array($step['decision_basis'] ?? NULL) ? $step['decision_basis'] : [],
      ];
    }

    $primary = $suggested_steps[0] ?? NULL;

    return [
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'actor_id' => $entity_id,
      'actor_name' => (string) ($combatant['name'] ?? $entity_id),
      'actor_team' => $this->normalizeCombatTeam((string) ($combatant['team'] ?? '')),
      'is_actor_turn' => $is_actor_turn,
      'actions_remaining' => $actions_remaining,
      'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
      'intent_reason' => (string) ($intent_contract['decision_reason'] ?? ''),
      'target_strategy' => (string) ($intent_contract['target_strategy'] ?? 'nearest'),
      'allowed_actions' => $allowed_actions,
      'suggested_action' => $primary,
      'suggested_plan' => $suggested_steps,
      'has_suggestion' => $primary !== NULL,
      'fallback_reason' => $primary === NULL
        ? 'No legal tactical action is available; ending the turn is the recommended play.'
        : NULL,
    ];
  }

  /**
   * Resolve a display name for a suggested target from initiative order.
   */
  protected function resolveSuggestionTargetName(string $target_id, array $game_state): ?string {
    $target = $this->findCombatant($target_id, $game_state);
    if ($target === NULL) {
      return NULL;
    }
    $name = trim((string) ($target['name'] ?? ''));
    return $name !== '' ? $name : NULL;
  }

  /**
   * Build deterministic per-action NPC plan for the remaining turn economy.
   */
  protected function buildNpcTurnPlan(string $entity_id, array $game_state, int $campaign_id, ?array $ai_seed_action = NULL): array {
    $intent_contract = $this->buildNpcTacticalIntentContract($entity_id, $game_state, $campaign_id);
    return $this->actorAutoplayCoordinator->buildTurnPlan(
      $entity_id,
      $game_state,
      $campaign_id,
      $intent_contract,
      $ai_seed_action,
      fn(string $actor_id, array $state, string $action_type, array $contract, int $cid): ?string => $this->actorAutoplayCoordinator->resolveIntentTarget(
        $actor_id,
        $state,
        $cid,
        $action_type,
        $contract,
        fn(string $fallback_actor_id, array $fallback_state, int $fallback_campaign_id, string $fallback_action_type): ?string => $this->chooseFallbackTarget($fallback_actor_id, $fallback_state, $fallback_campaign_id, $fallback_action_type),
        fn(string $nearest_actor_id, array $nearest_state): ?string => $this->findNearestAliveOpponent($nearest_actor_id, $nearest_state)
      )
    );
  }

  /**
   * Normalize personality-axis values for deterministic tactical decisions.
   */
  protected function normalizeDecisionPersonalityAxes(array $axes): array {
    return $this->actorContextBuilder->normalizePersonalityAxes($axes);
  }

  /**
   * Returns true when at least one alive player is adjacent to this NPC.
   */
  protected function hasAdjacentAlivePlayer(?array $npc, array $game_state): bool {
    if (!$npc) {
      return FALSE;
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if ($this->normalizeCombatTeam((string) ($combatant['team'] ?? '')) !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      if ($this->hexDistance($npc_q, $npc_r, $pq, $pr) <= 1) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns true when at least one alive hostile opponent is adjacent.
   */
  protected function hasAdjacentAliveOpponent(?array $npc, array $game_state): bool {
    if (!$npc) {
      return FALSE;
    }

    $opposition_teams = $this->resolveNpcOpponentTeams($npc);
    if ($opposition_teams === []) {
      return FALSE;
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (!$this->combatantMatchesAnyTeam($combatant, $opposition_teams) || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      if ($this->hexDistance($npc_q, $npc_r, $pq, $pr) <= 1) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Whether the current turn order still has a non-defeated player actor.
   */
  protected function hasActivePlayerParticipant(array $game_state): bool {
    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if ($this->normalizeCombatTeam((string) ($participant['team'] ?? '')) === 'player' && empty($participant['is_defeated'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines whether the encounter has been decided.
   *
   * An encounter is over once at most one normalized team still has a
   * non-defeated combatant (mirrors PF2e "encounter ends when one side is
   * wiped" rules, and matches the equivalent check that used to gate the
   * turn-advance loop before it was lost in an earlier extraction refactor).
   */
  protected function isEncounterOver(array $game_state): bool {
    $teams_alive = [];
    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if (!is_array($participant) || !empty($participant['is_defeated'])) {
        continue;
      }
      $teams_alive[$this->normalizeCombatTeam((string) ($participant['team'] ?? ''))] = TRUE;
    }
    return count($teams_alive) <= 1;
  }

  /**
   * Resolves the encounter outcome label ('victory'/'defeat'/'draw') once
   * {@see isEncounterOver()} is true, based on which side (if any) survives.
   */
  protected function resolveEncounterOutcome(array $game_state): string {
    $teams_alive = [];
    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if (!is_array($participant) || !empty($participant['is_defeated'])) {
        continue;
      }
      $teams_alive[$this->normalizeCombatTeam((string) ($participant['team'] ?? ''))] = TRUE;
    }
    if (isset($teams_alive['player']) || isset($teams_alive['ally'])) {
      return 'victory';
    }
    if (isset($teams_alive['enemy'])) {
      return 'defeat';
    }
    return 'draw';
  }

  /**
   * Builds a per-combatant status/state summary once the encounter has
   * ended, for logging to the action log (game_event data payload) and the
   * chat narration, so a party wipe or full enemy defeat is always
   * accompanied by a clear record of who's standing and who's down.
   *
   * @return array{outcome: string, narration: string, participants: array}
   */
  protected function buildEncounterOutcomeSummary(array $game_state, string $outcome): array {
    $defeated_names = [];
    $standing_names = [];
    $participants_summary = [];

    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $name = trim((string) ($participant['name'] ?? ($participant['entity_id'] ?? 'Unknown')));
      $team = $this->normalizeCombatTeam((string) ($participant['team'] ?? ''));
      $is_defeated = !empty($participant['is_defeated']);
      $hp = is_numeric($participant['hp'] ?? NULL) ? (int) $participant['hp'] : NULL;
      $max_hp = is_numeric($participant['max_hp'] ?? NULL) ? (int) $participant['max_hp'] : NULL;

      $participants_summary[] = [
        'entity_id' => (string) ($participant['entity_id'] ?? ''),
        'name' => $name,
        'team' => $team,
        'status' => $is_defeated ? 'defeated' : 'active',
        'hp' => $hp,
        'max_hp' => $max_hp,
      ];

      if ($is_defeated) {
        $defeated_names[] = $name;
      }
      else {
        $standing_names[] = $name;
      }
    }

    $outcome_label = $outcome === 'victory'
      ? 'The party is victorious!'
      : ($outcome === 'defeat' ? 'The party has been defeated.' : 'The encounter has ended in a draw.');

    $status_lines = [$outcome_label . ' The encounter has ended.'];
    if ($standing_names !== []) {
      $status_lines[] = sprintf('Standing: %s.', implode(', ', $standing_names));
    }
    if ($defeated_names !== []) {
      $status_lines[] = sprintf('Defeated: %s.', implode(', ', $defeated_names));
    }

    return [
      'outcome' => $outcome,
      'narration' => implode(' ', $status_lines),
      'participants' => $participants_summary,
    ];
  }

  /**
   * Choose a fallback action for NPC without AI.
   *
   * Basic tactical heuristic: if adjacent to player → strike; otherwise → stride.
   */
  protected function chooseFallbackAction(string $entity_id, array $game_state, int $campaign_id = 0): string {
    $intent_contract = $this->buildNpcTacticalIntentContract($entity_id, $game_state, $campaign_id);
    return $this->actorAutoplayCoordinator->resolveIntentActionType($intent_contract, 0);
  }

  /**
   * Choose a fallback target aligned to NPC psychology and tactical state.
   */
  protected function chooseFallbackTarget(string $entity_id, array $game_state, int $campaign_id, string $action_type): ?string {
    if (!in_array($action_type, ['strike', 'talk'], TRUE)) {
      return NULL;
    }

    $nearest = $this->findNearestAliveOpponent($entity_id, $game_state);
    if ($nearest === NULL) {
      return NULL;
    }

    $profile = $this->loadCombatantPsychologyProfile($entity_id, $game_state, $campaign_id);
    if (!$profile) {
      return $nearest;
    }

    $axes = is_array($profile['personality_axes'] ?? NULL) ? $profile['personality_axes'] : [];
    $cunning = (int) ($axes['cunning'] ?? 5);
    $discipline = (int) ($axes['discipline'] ?? 5);
    if ($cunning < 7 && $discipline < 7) {
      return $nearest;
    }

    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return $nearest;
    }
    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);

    $best_target = NULL;
    $best_ratio = 2.0;
    $best_distance = PHP_INT_MAX;
    $opposition_teams = $this->resolveNpcOpponentTeams($npc);
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (!$this->combatantMatchesAnyTeam($combatant, $opposition_teams) || !empty($combatant['is_defeated'])) {
        continue;
      }

      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $distance = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($action_type === 'strike' && $distance > 1) {
        continue;
      }

      $ratio = $this->hpRatio($combatant);
      if ($ratio < $best_ratio || ($ratio === $best_ratio && $distance < $best_distance)) {
        $best_ratio = $ratio;
        $best_distance = $distance;
        $best_target = $combatant['entity_id'] ?? NULL;
      }
    }

    return $best_target ?? $nearest;
  }

  /**
   * Returns true when profile text signals a self-preservation motivation.
   */
  protected function motivationSignalsSelfPreservation(array $profile): bool {
    $motivation_text = strtolower(trim((string) ($profile['motivations'] ?? '')));
    $fear_text = strtolower(trim((string) ($profile['fears'] ?? '')));
    $haystack = trim($motivation_text . ' ' . $fear_text);
    if ($haystack === '') {
      return FALSE;
    }

    foreach (['survive', 'survival', 'escape', 'retreat', 'avoid conflict', 'stay alive'] as $needle) {
      if (str_contains($haystack, $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Find the nearest alive player to an NPC.
   */
  protected function findNearestAlivePlayer(string $entity_id, array $game_state): ?string {
    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return $this->findFirstAlivePlayer($game_state);
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);
    $closest = NULL;
    $closest_dist = PHP_INT_MAX;

    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if ($this->normalizeCombatTeam((string) ($combatant['team'] ?? '')) !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $dist = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($dist < $closest_dist) {
        $closest_dist = $dist;
        $closest = $combatant['entity_id'] ?? NULL;
      }
    }

    return $closest;
  }

  /**
   * Find the nearest alive hostile opponent for this combatant.
   */
  protected function findNearestAliveOpponent(string $entity_id, array $game_state): ?string {
    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return $this->findNearestAlivePlayer($entity_id, $game_state);
    }

    $opposition_teams = $this->resolveNpcOpponentTeams($npc);
    if ($opposition_teams === []) {
      return NULL;
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);
    $closest = NULL;
    $closest_dist = PHP_INT_MAX;

    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (!$this->combatantMatchesAnyTeam($combatant, $opposition_teams) || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $dist = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($dist < $closest_dist) {
        $closest_dist = $dist;
        $closest = $combatant['entity_id'] ?? NULL;
      }
    }

    if ($closest !== NULL) {
      return $closest;
    }

    // Safety fallback: if team metadata is malformed, choose the nearest
    // non-self, non-defeated combatant with a different normalized team.
    $actor_team = $this->normalizeCombatTeam((string) ($npc['team'] ?? ''));
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      $candidate_id = trim((string) ($combatant['entity_id'] ?? ''));
      if ($candidate_id === '' || $candidate_id === $entity_id || !empty($combatant['is_defeated'])) {
        continue;
      }
      $candidate_team = $this->normalizeCombatTeam((string) ($combatant['team'] ?? ''));
      if ($candidate_team !== '' && $candidate_team === $actor_team) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $dist = $this->hexDistance($npc_q, $npc_r, $pq, $pr);
      if ($dist < $closest_dist) {
        $closest_dist = $dist;
        $closest = $candidate_id;
      }
    }

    if ($closest !== NULL) {
      return $closest;
    }

    // Contract enforcement: in a hostile encounter every combatant must have a
    // resolvable opposing side. Reaching this point means participant team
    // metadata collapsed at the bootstrap write path. Fail loudly instead of
    // guessing a target, which would mask the originating defect and produce
    // silent pass-turn loops.
    $mode = strtolower(trim((string) ($game_state['encounter_context']['mode'] ?? '')));
    if ($mode === 'hostile_combat') {
      $team_census = [];
      foreach (($game_state['initiative_order'] ?? []) as $combatant) {
        $candidate_id = trim((string) ($combatant['entity_id'] ?? ''));
        if ($candidate_id === '' || !empty($combatant['is_defeated'])) {
          continue;
        }
        $team_census[] = sprintf(
          '%s=%s',
          $candidate_id,
          $this->normalizeCombatTeam((string) ($combatant['team'] ?? '')) ?: 'unresolved'
        );
      }

      throw new \RuntimeException(sprintf(
        'Encounter participant team contract violation: actor "%s" (team="%s") has no resolvable opposing combatant in hostile_combat encounter %d (campaign %d, round %s). Live team census: [%s]. Fix the participant classification write path (resolveEncounterParticipantTeam / buildRoomEncounterTurnOrder) rather than the target selector.',
        $entity_id,
        $actor_team !== '' ? $actor_team : 'unresolved',
        (int) ($game_state['encounter_id'] ?? 0),
        (int) ($game_state['campaign_id'] ?? 0),
        (string) ($game_state['round'] ?? 'unknown'),
        implode(', ', $team_census)
      ));
    }

    return $closest;
  }

  /**
   * Resolve opposing combat teams for one NPC combatant.
   *
   * @return array<int, string>
   *   Normalized team names considered hostile to this combatant.
   */
  protected function resolveNpcOpponentTeams(array $npc): array {
    $team = $this->normalizeCombatTeam((string) ($npc['team'] ?? ''));
    if (in_array($team, ['player', 'ally'], TRUE)) {
      return ['enemy'];
    }
    if ($team === 'enemy') {
      return ['player', 'ally'];
    }

    return ['player', 'ally'];
  }

  /**
   * Determine whether one combatant belongs to any normalized team name.
   */
  protected function combatantMatchesAnyTeam(array $combatant, array $teams): bool {
    $combatant_team = $this->normalizeCombatTeam((string) ($combatant['team'] ?? ''));
    if ($combatant_team === '') {
      return FALSE;
    }
    foreach ($teams as $team) {
      if ($this->normalizeCombatTeam((string) $team) === $combatant_team) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Find a combatant in the initiative order by entity ID.
   */
  protected function findCombatant(string $entity_id, array $game_state): ?array {
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $combatant;
      }
    }
    return NULL;
  }

  /**
   * Calculate hex distance (cube coordinates).
   */
  protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {
    $dq = abs($q1 - $q2);
    $dr = abs($r1 - $r2);
    $ds = abs((-$q1 - $r1) - (-$q2 - $r2));
    return (int) max($dq, $dr, $ds);
  }

  /**
   * Check if an entity was defeated after damage and generate narration.
   *
   * @param string $entity_id
   *   The entity to check for defeat.
   * @param string $attacker_id
   *   The entity that dealt the killing blow.
   * @param array &$game_state
   *   Current game state (modified if entity defeated).
   * @param array &$events
   *   Events array to append defeat event to.
   * @param array $dungeon_data
   *   Dungeon data for AI narration context.
   */
  protected function checkEntityDefeated(string $entity_id, string $attacker_id, array &$game_state, array &$events, array $dungeon_data, int $campaign_id = 0): void {
    foreach ($game_state['initiative_order'] as &$combatant) {
      if (($combatant['entity_id'] ?? '') !== $entity_id) {
        continue;
      }

      $hp = (int) ($combatant['hp'] ?? 0);
      if ($hp <= 0 && empty($combatant['is_defeated'])) {
        $combatant['is_defeated'] = TRUE;
        $name = $combatant['name'] ?? $entity_id;
        $team = $combatant['team'] ?? 'unknown';
        $this->applyForcedStanceTerminationOnDefeat($entity_id, $campaign_id, $dungeon_data, $events);

        // Resolve attacker name for narration.
        $attacker = $this->findCombatant($attacker_id, $game_state);
        $killer_name = $attacker['name'] ?? $attacker_id;

        $narration = $this->aiGmService->narrateEntityDefeated($name, $killer_name, $dungeon_data, $campaign_id);
        $events[] = GameEventLogger::buildEvent('entity_defeated', 'encounter', $entity_id, [
          'name' => $name,
          'team' => $team,
          'killed_by' => $killer_name,
        ], $narration);
      }
      break;
    }
    unset($combatant);
  }

  /**
   * Force-terminate active stances for defeated actors when canonical state is available.
   */
  protected function applyForcedStanceTerminationOnDefeat(string $entity_id, int $campaign_id, array $dungeon_data, array &$events): void {
    if (!$this->stanceRuntimeService || $campaign_id <= 0) {
      return;
    }
    $entity_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $entity_id);
    if ($entity_index === NULL || !is_array($dungeon_data['entities'][$entity_index] ?? NULL)) {
      return;
    }
    $entity = $dungeon_data['entities'][$entity_index];
    $character_state = $this->loadCanonicalCharacterState($entity, $campaign_id);
    if (!is_array($character_state)) {
      return;
    }
    $active_stances = is_array($character_state['stance_state']['active_stances'] ?? NULL)
      ? array_values($character_state['stance_state']['active_stances'])
      : [];
    $has_arcane = $this->stanceRuntimeService->isStanceActive($character_state, 'arcane_cascade');
    if ($active_stances === [] && !$has_arcane) {
      return;
    }

    $identity = $this->resolveCanonicalCharacterIdentity($entity);
    $entity_ref = trim((string) ($identity['instance_id'] ?? $entity_id));
    $updated_state = $this->stanceRuntimeService->clearAllStances($character_state, [
      'source_type' => 'defeat_forced_termination',
      'source_id' => $entity_id,
    ], $campaign_id, $entity_ref);
    $this->persistCanonicalCharacterState($entity, $campaign_id, $updated_state);
    $events[] = GameEventLogger::buildEvent('stance_forced_termination', 'encounter', $entity_id, [
      'stance_count' => count($active_stances),
      'arcane_cascade_active' => $has_arcane,
    ]);
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Processes an Escape attempt (REQ 2197-2199).
   * Attack trait: applies MAP. Crit success: freed + may Stride 5 ft.
   * Crit fail: blocks further escape attempts this turn.
   */
  protected function processEscape(int $encounter_id, string $actor_id, array $params, array &$game_state): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if (!$encounter) {
      return ['error' => 'Encounter not found.', 'mutations' => []];
    }
    $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
    if (!$participant) {
      return ['error' => 'Participant not found.', 'mutations' => []];
    }
    $pid = (int) $participant['id'];

    // REQ 2198: crit fail blocks further escape this turn.
    if (!empty($game_state['turn']['escape_blocked'][$actor_id])) {
      return ['error' => 'Cannot attempt Escape again this turn (critical failure).', 'mutations' => []];
    }

    // Must have grabbed, immobilized, or restrained.
    $active = $this->conditionManager->getActiveConditions($pid, $encounter_id);
    $condition_row_id = NULL;
    foreach ($active as $row_id => $row) {
      if (in_array($row['condition_type'], ['grabbed', 'immobilized', 'restrained'], TRUE)) {
        $condition_row_id = $row_id;
        break;
      }
    }
    if ($condition_row_id === NULL) {
      return ['error' => 'Must be grabbed, immobilized, or restrained to Escape.', 'mutations' => []];
    }

    // REQ 2199: attack trait — apply MAP.
    // REQ 1619: Athletics modifier accepted as alternative to unarmed modifier.
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    // Prefer acrobatics_bonus or athletics_bonus if provided; fall back to skill_bonus (unarmed).
    $modifier = (int) ($params['acrobatics_bonus'] ?? $params['athletics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $total = $d20 + $modifier + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, (int) ($params['grapple_dc'] ?? 15), $d20);

    // Increment MAP for future attacks.
    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;

    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $this->conditionManager->removeCondition($pid, $condition_row_id, $encounter_id);
    }
    if ($degree === 'critical_failure') {
      if (!isset($game_state['turn']['escape_blocked'])) {
        $game_state['turn']['escape_blocked'] = [];
      }
      $game_state['turn']['escape_blocked'][$actor_id] = TRUE;
    }

    return [
      'escaped' => in_array($degree, ['critical_success', 'success'], TRUE),
      'may_stride_5ft' => ($degree === 'critical_success'),
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Processes a Seek action (REQ 2207-2210).
   * Secret GM-side Perception roll vs each target's Stealth DC.
   * Updates visibility state in game_state['visibility'][$seeker_id][$target_id].
   */
  protected function processSeek(int $encounter_id, string $actor_id, array $params, array &$game_state): array {
    $perception_bonus = (int) ($params['perception_bonus'] ?? 0);
    $target_ids = $params['target_ids'] ?? [];
    $is_imprecise = !empty($params['imprecise_sense']);
    // stealth_dcs: assoc array of target_id → DC; defaults to 15 if not provided.
    $stealth_dcs = $params['stealth_dcs'] ?? [];

    // AC-001–004: Sensate Gnome scent range + wind modifier.
    // scent_ft: base scent range of the actor (0 = no scent sense).
    // target_distances: optional assoc array of target_id → distance_ft for range check.
    $base_scent_ft = (int) ($params['scent_ft'] ?? 0);
    $target_distances = $params['target_distances'] ?? [];
    $effective_scent_ft = $base_scent_ft;
    if ($base_scent_ft > 0) {
      $wind_direction = $game_state['environment']['wind_direction'] ?? 'neutral';
      if ($wind_direction === 'downwind') {
        $effective_scent_ft = $base_scent_ft * 2;
      }
      elseif ($wind_direction === 'upwind') {
        $effective_scent_ft = (int) round($base_scent_ft / 2);
      }
      // neutral: no change.
    }

    if (!isset($game_state['visibility'])) {
      $game_state['visibility'] = [];
    }
    if (!isset($game_state['visibility'][$actor_id])) {
      $game_state['visibility'][$actor_id] = [];
    }

    $seek_results = [];
    foreach ($target_ids as $tid) {
      $stealth_dc = (int) ($stealth_dcs[$tid] ?? 15);
      $current = $game_state['visibility'][$actor_id][$tid] ?? 'undetected';

      // +2 circumstance bonus when actor has scent and target is undetected and within scent range.
      $roll_perception = $perception_bonus;
      if ($effective_scent_ft > 0 && $current === 'undetected') {
        $target_dist = isset($target_distances[$tid]) ? (int) $target_distances[$tid] : NULL;
        if ($target_dist === NULL || $target_dist <= $effective_scent_ft) {
          $roll_perception += 2;
        }
      }

      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $roll_perception;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $stealth_dc, $d20);

      $new_visibility = $current;

      // REQ 2208: detection rules.
      if ($degree === 'critical_success' && in_array($current, ['undetected', 'hidden'], TRUE)) {
        $new_visibility = 'observed';
      }
      elseif ($degree === 'success' && $current === 'undetected') {
        $new_visibility = 'hidden';
      }
      // failure / crit fail: no change.

      // REQ 2210: Imprecise sense cap — cannot exceed hidden.
      if ($is_imprecise && $new_visibility === 'observed') {
        $new_visibility = 'hidden';
      }

      $game_state['visibility'][$actor_id][$tid] = $new_visibility;
      // Secret: d20/total not included in returned result (GM-only).
      $seek_results[$tid] = ['degree' => $degree, 'new_visibility' => $new_visibility];
    }

    return ['sought' => TRUE, 'results' => $seek_results];
  }

  /**
   * Processes an Aid reaction (REQ 2190-2191).
   * Requires prior aid_setup on a previous turn. Rolls vs DC 20.
   */
  protected function processAid(string $actor_id, ?string $target_id, array $params, array &$game_state): array {
    $reaction_available = $game_state['turn']['reaction_available'] ?? TRUE;
    if (!$reaction_available) {
      return ['error' => 'Reaction already spent.', 'mutations' => []];
    }

    $aiding_actor = $params['aiding_actor'] ?? $actor_id;
    $aid_prepared = $game_state['turn']['aid_prepared'][$aiding_actor][$target_id] ?? NULL;
    if (!$aid_prepared) {
      return ['error' => 'Aid has not been prepared for this target.', 'mutations' => []];
    }

    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
    // proficiency_rank: 0=untrained,1=trained,2=expert,3=master,4=legendary.
    $proficiency_rank = (int) ($params['proficiency_rank'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $skill_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 20, $d20);

    // REQ 2191: Aid bonus by degree and proficiency rank.
    $aid_bonus = 0;
    if ($degree === 'critical_success') {
      if ($proficiency_rank >= 4) {
        $aid_bonus = 4;
      }
      elseif ($proficiency_rank >= 3) {
        $aid_bonus = 3;
      }
      else {
        $aid_bonus = 2;
      }
    }
    elseif ($degree === 'success') {
      $aid_bonus = 1;
    }
    elseif ($degree === 'critical_failure') {
      $aid_bonus = -1;
    }

    // Store aid bonus for the aided actor's next action.
    if (!isset($game_state['aid_bonuses'])) {
      $game_state['aid_bonuses'] = [];
    }
    if (!isset($game_state['aid_bonuses'][$target_id])) {
      $game_state['aid_bonuses'][$target_id] = [];
    }
    $game_state['aid_bonuses'][$target_id][] = $aid_bonus;
    $game_state['turn']['reaction_available'] = FALSE;

    return [
      'aided' => TRUE,
      'aid_bonus' => $aid_bonus,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Gets the action cost for an intent type.
   */
  protected function getActionCost(string $type, array $params = []): int {
    if ($type === 'stride') {
      return max(1, min(3, (int) ($params['action_cost'] ?? 1)));
    }

    switch ($type) {
      case 'strike':
      case 'interact':
      case 'stand':
      case 'drop_prone':
      case 'step':
      case 'crawl':
      case 'leap':
      case 'escape':
      case 'seek':
      case 'sense_motive':
      case 'take_cover':
      case 'aid_setup':
      case 'burrow':
      case 'fly':
      case 'mount':
      case 'dismount':
      case 'raise_shield':
      case 'avert_gaze':
      case 'point_out':
      // REQ 1619–1659: Athletics single-action skill actions.
      case 'climb':
      case 'force_open':
      case 'grapple':
      case 'shove':
      case 'swim':
      case 'trip':
      case 'disarm':
      // REQ 1695: Treat Poison costs 1 action.
      case 'treat_poison':
      // REQ 1591: Recall Knowledge costs 1 action.
      case 'recall_knowledge':
      // REQ 1715: Hide costs 1 action.
      case 'hide':
      // REQ 1719: Sneak is a 1-action move.
      case 'sneak':
      // REQ 1721: Conceal an Object costs 1 action.
      case 'conceal_object':
      // REQ 1747: Palm an Object costs 1 action.
      case 'palm_object':
      // REQ 1751: Steal costs 1 action.
      case 'steal':
      // REQ 1591: Balance / Tumble Through / Maneuver in Flight cost 1 action.
      case 'balance':
      case 'tumble_through':
      case 'maneuver_in_flight':
      // REQ 1660: Create a Diversion costs 1 action.
      case 'create_diversion':
      // REQ 1677: Request costs 1 action.
      case 'request':
      // REQ 1683: Demoralize costs 1 action.
      case 'demoralize':
      // REQ 1700: Command an Animal costs 1 action (encounter).
      case 'command_animal':
      // REQ 1706: Perform costs 1 action (encounter).
      case 'perform':
        return 1;

      case 'ready':
        return 2;

      // REQ 1632–1636, 1637–1640: High Jump / Long Jump are 2-action activities.
      case 'high_jump':
      case 'long_jump':
      // REQ 1688: Administer First Aid costs 2 actions.
      case 'administer_first_aid':
      // REQ 1748: Disable a Device costs 2 actions.
      case 'disable_device':
      // REQ 1753: Pick a Lock costs 2 actions.
      case 'pick_lock':
      // REQ 1657: Feint costs 2 actions.
      case 'feint':
        return 2;

      case 'cast_spell':
        return $params['action_cost'] ?? 2;

      case 'talk':
      case 'release':
      case 'aid':
      case 'delay_reenter':
      // Reactions: no action cost (they use the reaction resource, not action slots).
      case 'arrest_fall':
      case 'grab_edge':
      case 'shield_block':
      case 'attack_of_opportunity':
      // REQ 2280: Hero point reroll is a free action (costs 0 actions).
      case 'hero_point_reroll':
      // REQ 2281: Heroic recovery (spend all HP) is a reaction (costs 0 actions).
      case 'heroic_recovery_all_points':
        return 0;

      default:
        return 1;
    }
  }

  /**
   * Applies room-scene lead-seeking caps for automation/harness talk actions.
   */
  protected function applyRoomSceneSocialProgressionPolicy(
    array $intent,
    array $result,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (!$this->isRoomSceneMode($game_state)) {
      return [];
    }
    if (!$this->isLeadSeekingAutomationTalkIntent($intent, $game_state)) {
      return [];
    }
    if (!empty($result['error']) || array_key_exists('success', $result) && empty($result['success'])) {
      return [];
    }

    $lead_source_id = $this->resolveLeadSeekTalkTargetEntityId($intent);
    if ($lead_source_id === '') {
      return [];
    }
    $room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ($dungeon_data['active_room_id'] ?? '')));
    if ($room_id === '') {
      return [];
    }

    $social_progression = $this->getRoomSceneSocialProgressionState($game_state, $room_id);
    $lead_seek_counts = is_array($social_progression['lead_seek_counts'] ?? NULL)
      ? $social_progression['lead_seek_counts']
      : [];
    $current_count = max(0, (int) ($lead_seek_counts[$lead_source_id] ?? 0));
    $next_count = $current_count + 1;
    $lead_seek_counts[$lead_source_id] = $next_count;

    $exhausted = is_array($social_progression['exhausted_lead_sources'] ?? NULL)
      ? array_values(array_filter(array_map('strval', $social_progression['exhausted_lead_sources']), static fn(string $value): bool => trim($value) !== ''))
      : [];
    if ($next_count >= self::MAX_LEAD_SEEK_INTERACTIONS_PER_ACTOR_PER_ROOM && !in_array($lead_source_id, $exhausted, TRUE)) {
      $exhausted[] = $lead_source_id;
    }

    $social_progression['lead_seek_counts'] = $lead_seek_counts;
    $social_progression['exhausted_lead_sources'] = array_values(array_unique($exhausted));
    $social_progression['last_progress_signal'] = $this->resolveSocialProgressSignal($result);
    $game_state['encounter_context']['social_progression'] = $social_progression;

    if ($next_count < self::MAX_LEAD_SEEK_INTERACTIONS_PER_ACTOR_PER_ROOM) {
      return [];
    }

    $lead_source_name = $this->resolveEntityName($lead_source_id, $game_state, $dungeon_data);
    return [
      GameEventLogger::buildEvent('room_scene_lead_source_exhausted', 'encounter', $lead_source_id, [
        'room_id' => $room_id,
        'lead_source_id' => $lead_source_id,
        'lead_source_name' => $lead_source_name,
        'lead_seek_count' => $next_count,
        'lead_seek_cap' => self::MAX_LEAD_SEEK_INTERACTIONS_PER_ACTOR_PER_ROOM,
      ], sprintf(
        '%s has no additional rumor/quest leads to offer right now.',
        $lead_source_name !== '' ? $lead_source_name : 'This actor'
      )),
    ];
  }

  /**
   * Returns true when an automation talk intent is seeking rumors/quest leads.
   */
  protected function isLeadSeekingAutomationTalkIntent(array $intent, array $game_state): bool {
    if (strtolower(trim((string) ($intent['type'] ?? ''))) !== 'talk') {
      return FALSE;
    }
    $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
    if (!$this->isAutomationSocialIntent($intent, $params)) {
      return FALSE;
    }

    if (trim((string) ($params['objective_id'] ?? '')) !== '' || trim((string) ($params['quest_id'] ?? '')) !== '') {
      return FALSE;
    }

    $message = strtolower(trim((string) ($params['message'] ?? '')));
    if ($message === '') {
      return FALSE;
    }

    $turn_in_markers = [
      'turning them in',
      'turning this in',
      'turn this in',
      'turn in now',
      'i found the requested items',
      'objective complete',
      'quest complete',
    ];
    foreach ($turn_in_markers as $marker) {
      if (str_contains($message, $marker)) {
        return FALSE;
      }
    }

    $lead_markers = [
      'rumor',
      'rumour',
      'lead',
      'job',
      'work',
      'danger',
      'clue',
      'what should i do next',
      'what should i tackle',
      'where should i start',
      'additional work',
      'urgent problem',
      'storyline lead',
      'what next',
    ];
    foreach ($lead_markers as $marker) {
      if (str_contains($message, $marker)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Detects harness/automation-origin intents for social-cap enforcement.
   */
    protected function isAutomationSocialIntent(array $intent, array $params): bool {
      if (trim((string) ($intent['decision_id'] ?? '')) !== '') {
        return TRUE;
      }
      if (trim((string) ($params['automation_goal'] ?? '')) !== '') {
        return TRUE;
      }
    if (!empty($params['automation'])) {
      return TRUE;
    }
    $source = strtolower(trim((string) ($params['source'] ?? '')));
    return in_array($source, ['harness', 'automation', 'player_agent'], TRUE);
  }

  /**
   * Resolve the target actor/entity identifier for lead-seeking talk actions.
   */
  protected function resolveLeadSeekTalkTargetEntityId(array $intent): string {
    $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
    return trim((string) (
      $intent['target']
      ?? $params['target']
      ?? ''
    ));
  }

  /**
   * Returns current room-scene social progression state with safe defaults.
   */
  protected function getRoomSceneSocialProgressionState(array $game_state, string $room_id): array {
    $encounter_context = is_array($game_state['encounter_context'] ?? NULL)
      ? $game_state['encounter_context']
      : [];
    $social_progression = is_array($encounter_context['social_progression'] ?? NULL)
      ? $encounter_context['social_progression']
      : [];
    $progression_room_id = trim((string) ($social_progression['room_id'] ?? ''));
    if ($progression_room_id === '' || ($room_id !== '' && $progression_room_id !== $room_id)) {
      $social_progression = [];
    }

    return [
      'policy_version' => (int) ($social_progression['policy_version'] ?? self::SOCIAL_PROGRESSION_POLICY_VERSION),
      'room_id' => $room_id,
      'lead_seek_counts' => is_array($social_progression['lead_seek_counts'] ?? NULL)
        ? $social_progression['lead_seek_counts']
        : [],
      'exhausted_lead_sources' => is_array($social_progression['exhausted_lead_sources'] ?? NULL)
        ? $social_progression['exhausted_lead_sources']
        : [],
      'last_progress_signal' => (string) ($social_progression['last_progress_signal'] ?? 'none'),
    ];
  }

  /**
   * Checks whether an actor is exhausted for lead-seeking in the room.
   */
  protected function isActorLeadSourceExhaustedForRoom(array $game_state, string $actor_id, string $room_id): bool {
    if ($actor_id === '' || $room_id === '') {
      return FALSE;
    }
    $social_progression = $this->getRoomSceneSocialProgressionState($game_state, $room_id);
    $exhausted = is_array($social_progression['exhausted_lead_sources'] ?? NULL)
      ? $social_progression['exhausted_lead_sources']
      : [];
    return in_array($actor_id, array_map('strval', $exhausted), TRUE);
  }

  /**
   * Classifies whether the latest talk produced objective progression signals.
   */
  protected function resolveSocialProgressSignal(array $result): string {
    $result_blob = strtolower(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    if ($result_blob === '') {
      return 'none';
    }
    if (str_contains($result_blob, 'objective') || str_contains($result_blob, 'quest')) {
      return 'objective_update';
    }
    return 'none';
  }

  /**
   * Checks if auto-end-turn conditions are met.
   */
  protected function shouldAutoEndTurn(array $game_state): bool {
    $actions = $game_state['turn']['actions_remaining'] ?? 0;
    return $actions <= 0;
  }

  /**
   * Build the follow-up system prompt for partial room-scene turns.
   */
  protected function buildRemainingRoomSceneActionPrompt(?string $actor_id, array $game_state, array $dungeon_data): ?array {
    return $this->roomSceneEncounterCoordinator->buildRemainingRoomSceneActionPrompt(
      $actor_id,
      $game_state,
      $dungeon_data,
      fn(string $id, array $state, array $dungeon): string => $this->resolveEntityName($id, $state, $dungeon)
    );
  }

  /**
   * Checks if the encounter should end (all enemies defeated or all players defeated).
   */
  /**
   * Starts or resumes the room-scene encounter framework for a room.
   */
  protected function startRoomSceneEncounter(?string $actor_id, string $room_id, array &$game_state, array &$dungeon_data, int $campaign_id, ?array $room = NULL, ?string $narration = NULL): array {
    return $this->roomSceneEncounterCoordinator->startRoomSceneEncounter(
      $actor_id,
      $room_id,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $room,
      $narration,
      self::ROOM_SCENE_ERR_START_MISSING_PLAYER,
      function (array $dungeon, string $rid, ?string $aid): array {
        return $this->buildRoomEncounterTurnOrder($dungeon, $rid, $aid);
      },
      function (array $initiative_order, string $error_code): void {
        $this->assertInitiativeHasPlayer($initiative_order, $error_code);
      },
      function (array $dungeon, array $initiative_order): array {
        return $this->buildRoomSceneEncounterParticipants($dungeon, $initiative_order);
      },
      function (?int $cid, string $rid, array $participants, $context): int {
        return (int) $this->encounterStore->createEncounter($cid, $rid, $participants, $context);
      },
      fn(int $encounter_id): ?array => $this->loadCanonicalTurnState($encounter_id),
      function (array &$state, array $canonical): void {
        $this->syncGameStateWithCanonicalTurn($state, $canonical);
      },
      function (int $round, array &$state, array &$dungeon, int $cid, ?string $rid): array {
        return $this->buildRoundStartEvents($round, $state, $dungeon, $cid, $rid);
      },
      function (string $entity_id, array &$state, array &$dungeon, int $cid, ?string $rid): array {
        return $this->buildTurnStartEvents($entity_id, $state, $dungeon, $cid, $rid);
      },
      function (string $entity_id, array &$state, array &$dungeon, int $cid): array {
        return $this->buildTurnStartSearchEvents($entity_id, $state, $dungeon, $cid);
      }
    );
  }

  /**
   * Build persisted combat participants for room-scene canonical encounter state.
   */
  protected function buildRoomSceneEncounterParticipants(array $dungeon_data, array $initiative_order): array {
    return $this->roomSceneEncounterCoordinator->buildRoomSceneEncounterParticipants($dungeon_data, $initiative_order);
  }

  /**
   * Builds the room encounter turn order for every actor present in the room.
   */
  protected function buildRoomEncounterTurnOrder(array $dungeon_data, string $room_id, ?string $actor_id = NULL): array {
    return $this->roomSceneEncounterCoordinator->buildRoomEncounterTurnOrder(
      $dungeon_data,
      $room_id,
      $actor_id,
      fn(int $sides): int => (int) $this->numberGenerationService->rollPathfinderDie($sides)
    );
  }

  /**
   * Builds participant list from dungeon entities for encounter creation.
   */
  protected function buildParticipantList(array $dungeon_data, string $room_id, array $enemies = []): array {
    $participants = [];
    $entities = $dungeon_data['entities'] ?? [];
    $enemy_instance_ids = array_map(static function (array $enemy): string {
      return (string) ($enemy['entity_instance_id'] ?? $enemy['instance_id'] ?? $enemy['id'] ?? '');
    }, $enemies);

    foreach ($entities as $entity) {
      $entity_room = $entity['placement']['room_id'] ?? NULL;
      if ($entity_room !== $room_id) {
        continue;
      }

      $content_type = $entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? '');
      $instance_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
      $team = $this->resolveEncounterParticipantTeam($entity, $content_type, (string) $instance_id, $enemy_instance_ids);

      if ($team === 'player') {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
            'heritage' => is_string($entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL))
              ? strtolower(trim((string) ($entity['heritage'] ?? $entity['state']['heritage'])))
              : NULL,
          ]),
          'team' => 'player',
          'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? 'Unknown'),
          'hp' => $stats['currentHp'] ?? ($entity['state']['hit_points']['current'] ?? 20),
          'max_hp' => $stats['maxHp'] ?? ($entity['state']['hit_points']['max'] ?? 20),
          'ac' => $stats['ac'] ?? ($entity['state']['armor_class'] ?? 10),
          'perception' => $perception,
          'position_q' => $entity['placement']['hex']['q'] ?? 0,
          'position_r' => $entity['placement']['hex']['r'] ?? 0,
        ];
      }
      elseif ($team !== NULL) {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
            'heritage' => is_string($entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL))
              ? strtolower(trim((string) ($entity['heritage'] ?? $entity['state']['heritage'])))
              : NULL,
          ]),
          'team' => $team,
          'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? 'Unknown'),
          'hp' => $stats['currentHp'] ?? ($entity['state']['hit_points']['current'] ?? 10),
          'max_hp' => $stats['maxHp'] ?? ($entity['state']['hit_points']['max'] ?? 10),
          'ac' => $stats['ac'] ?? ($entity['state']['armor_class'] ?? 12),
          'perception' => $perception,
          'position_q' => $entity['placement']['hex']['q'] ?? 0,
          'position_r' => $entity['placement']['hex']['r'] ?? 0,
        ];
      }
    }

    return $participants;
  }

  /**
   * Syncs participant HP/defeat state back into dungeon entity runtime state.
   */
  protected function syncEncounterParticipantsToDungeonData(int $encounter_id, array &$dungeon_data): void {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if (empty($encounter['participants']) || empty($dungeon_data['entities'])) {
      return;
    }

    $participant_by_entity = [];
    foreach ((array) ($encounter['participants'] ?? []) as $participant) {
      $entity_id = (string) ($participant['entity_id'] ?? '');
      if ($entity_id !== '') {
        $participant_by_entity[$entity_id] = $participant;
      }
    }

    foreach ($dungeon_data['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($entity_id === '' || empty($participant_by_entity[$entity_id])) {
        continue;
      }

      $participant = $participant_by_entity[$entity_id];
      $hp = isset($participant['hp']) ? (int) $participant['hp'] : NULL;
      $max_hp = isset($participant['max_hp']) ? (int) $participant['max_hp'] : NULL;
      $is_defeated = !empty($participant['is_defeated']);

      if (!isset($entity['state']) || !is_array($entity['state'])) {
        $entity['state'] = [];
      }
      if (!isset($entity['state']['hit_points']) || !is_array($entity['state']['hit_points'])) {
        $entity['state']['hit_points'] = [];
      }
      if (!isset($entity['state']['metadata']) || !is_array($entity['state']['metadata'])) {
        $entity['state']['metadata'] = [];
      }
      if (!isset($entity['state']['metadata']['stats']) || !is_array($entity['state']['metadata']['stats'])) {
        $entity['state']['metadata']['stats'] = [];
      }

      if ($hp !== NULL) {
        $entity['state']['hit_points']['current'] = $hp;
        $entity['state']['metadata']['stats']['currentHp'] = $hp;
      }
      if ($max_hp !== NULL && $max_hp > 0) {
        $entity['state']['hit_points']['max'] = $max_hp;
        $entity['state']['metadata']['stats']['maxHp'] = $max_hp;
      }
      $entity['state']['is_defeated'] = $is_defeated;
    }
    unset($entity);
  }

  /**
   * Finds a room by ID in the dungeon payload.
   */
  protected function findRoomById(array $dungeon_data, string $room_id): ?array {
    return $this->requireNavigationService()->findRoomById($dungeon_data, $room_id);
  }

  /**
   * Resolves the requested transition through the existing navigation contract.
   */
  protected function resolveRoomTransitionCapability(array $dungeon_data, string $target_room_id, array $params): ?array {
    $active_room_id = (string) ($dungeon_data['active_room_id'] ?? '');
    if ($active_room_id === '') {
      return [
        'available' => TRUE,
        'target_room_id' => $target_room_id,
      ];
    }

    $capabilities = $this->requireNavigationService()
      ->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $active_room_id);
    $connection_id = isset($params['connection_id']) ? (string) $params['connection_id'] : '';
    if ($connection_id !== '') {
      foreach ($capabilities as $capability) {
        if ((string) ($capability['connection_id'] ?? '') !== $connection_id) {
          continue;
        }
        if ((string) ($capability['target_room_id'] ?? '') !== $target_room_id) {
          return NULL;
        }
        return $capability;
      }
      return NULL;
    }

    foreach ($capabilities as $capability) {
      if ((string) ($capability['target_room_id'] ?? '') === $target_room_id) {
        return $capability;
      }
    }

    return NULL;
  }

  /**
   * Build diagnostics for an unreachable transition attempt.
   *
   * Provides immediate available targets and an optional RoutingPlanner first-hop hint.
   */
  protected function buildTransitionUnreachableDiagnostics(array $dungeon_data, string $target_room_id): array {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    $diagnostics = [
      'active_room_id' => $active_room_id,
      'available_targets' => [],
      'suggested_via_room_id' => '',
    ];
    if ($active_room_id === '' || trim($target_room_id) === '') {
      return $diagnostics;
    }

    $capabilities = $this->requireNavigationService()
      ->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $active_room_id);
    $available_targets = [];
    foreach ($capabilities as $capability) {
      if (empty($capability['available'])) {
        continue;
      }
      $candidate_target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
      if ($candidate_target_room_id === '' || $candidate_target_room_id === $active_room_id) {
        continue;
      }
      $available_targets[] = $candidate_target_room_id;
    }
    $available_targets = array_values(array_unique($available_targets));
    $diagnostics['available_targets'] = array_slice($available_targets, 0, 12);

    $route_plan = $this->requireNavigationService()
      ->resolveRoomRoutePlan($dungeon_data, $active_room_id, trim($target_room_id));
    if (is_array($route_plan) && !empty($route_plan['next_room_id']) && empty($route_plan['is_direct'])) {
      $diagnostics['suggested_via_room_id'] = (string) $route_plan['next_room_id'];
    }

    return $diagnostics;
  }

  /**
   * Resolve a room display label with room_id fallback.
   */
  protected function resolveRoomLabelById(array $dungeon_data, string $room_id): string {
    $normalized_room_id = trim($room_id);
    if ($normalized_room_id === '') {
      return '';
    }
    $room = $this->findRoomById($dungeon_data, $normalized_room_id);
    if (!is_array($room)) {
      return $normalized_room_id;
    }
    $room_name = trim((string) ($room['name'] ?? ''));
    return $room_name !== '' ? $room_name : $normalized_room_id;
  }

  /**
   * Builds elapsed-time effects for room movement.
   */
  protected function buildTransitionTimeEffects(?string $actor_id, mixed $from_room, string $target_room_id, ?array $capability, array $params): array {
    $from_room_id = is_scalar($from_room) ? (string) $from_room : '';
    if ($from_room_id === '' || $from_room_id === $target_room_id) {
      return [];
    }

    $duration_seconds = $this->resolveTravelSeconds($capability ?? [], []);
    if ($duration_seconds <= 0) {
      return [];
    }

    return [[
      'mode' => 'elapsed',
      'phase' => 'encounter',
      'action_type' => 'room_transition',
      'actor_ids' => $actor_id ? [$actor_id] : [],
      'duration_seconds' => $duration_seconds,
      'concurrency_group' => 'party_travel',
      'location_context' => [
        'from_room_id' => $from_room_id,
        'to_room_id' => $target_room_id,
        'connection_id' => (string) ($capability['connection_id'] ?? ''),
      ],
      'advance_immediately' => TRUE,
    ]];
  }

  /**
   * Builds elapsed-time effects for completed encounter rounds.
   */
  protected function buildRoundElapsedTimeEffects(array $turn_result, ?string $actor_id, array $dungeon_data): array {
    $round_advances = (int) ($turn_result['round_advances'] ?? 0);
    if ($round_advances <= 0) {
      return [];
    }

    return [[
      'mode' => 'elapsed',
      'phase' => 'encounter',
      'action_type' => 'encounter_round',
      'actor_ids' => $actor_id ? [$actor_id] : [],
      'duration_seconds' => 6 * $round_advances,
      'concurrency_group' => 'encounter_rounds',
      'location_context' => [
        'room_id' => (string) ($dungeon_data['active_room_id'] ?? ''),
        'round_advances' => $round_advances,
      ],
      'advance_immediately' => TRUE,
    ]];
  }

  protected function buildRestTimeEffects(string $action_type, ?string $actor_id, int $duration_seconds, array $dungeon_data): array {
    if ($duration_seconds <= 0) {
      return [];
    }

    return [[
      'mode' => 'elapsed',
      'phase' => 'encounter',
      'action_type' => $action_type,
      'actor_ids' => $actor_id ? [$actor_id] : [],
      'duration_seconds' => $duration_seconds,
      'concurrency_group' => 'rest_activity',
      'location_context' => [
        'room_id' => (string) ($dungeon_data['active_room_id'] ?? ''),
      ],
      'advance_immediately' => TRUE,
    ]];
  }

  protected function isRestAction(string $type): bool {
    return in_array($type, $this->getRestActionTypes(), TRUE);
  }

  /**
   * Return encounter actions that represent rest activities.
   */
  protected function getRestActionTypes(): array {
    return ['treat_wounds', 'refocus', 'repair', 'daily_preparations'];
  }

  protected function isSafeRestAvailable(array $game_state, array $dungeon_data): bool {
    if (!empty($game_state['encounter_id'])) {
      return FALSE;
    }
    $room_id = (string) ($game_state['encounter_context']['room_id'] ?? $dungeon_data['active_room_id'] ?? '');
    if ($room_id === '') {
      return FALSE;
    }
    $room = $this->findRoomById($dungeon_data, $room_id);
    if (!is_array($room)) {
      return FALSE;
    }
    return !empty($room['gameplay_state']['safe_for_rest']);
  }

  protected function processTreatWoundsRestAction(?string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Treat Wounds requires an acting character.'];
    }
    $target_entity_id = $target_id ?: $actor_id;
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $target_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $target_entity_id);
    if ($actor_index === NULL || $target_index === NULL) {
      return ['error' => 'Treat Wounds requires valid room participants.'];
    }

    $actor_character_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $medicine_skill = $this->resolveCharacterSkillData($actor_character_state, 'medicine');
    $rank = (int) ($medicine_skill['rank'] ?? 0);
    if ($rank < 1) {
      return ['error' => 'Treat Wounds requires Medicine training.'];
    }
    if (!$this->characterHasInventoryItem($actor_character_state, $actor_entity, ['healers_tools'], ['healer', 'tool'])) {
      return ['error' => 'Treat Wounds requires healer\'s tools.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $target_entity = &$dungeon_data['entities'][$target_index];
    $now = $this->resolveCurrentCampaignTimestamp($game_state);
    $last_treated_at = (string) ($target_entity['state']['rest_activity']['last_treat_wounds_at'] ?? '');
    if ($last_treated_at !== '' && ($last_timestamp = strtotime($last_treated_at)) !== FALSE && ($now - $last_timestamp) < 3600) {
      return ['error' => 'That target has already benefited from Treat Wounds within the last hour.'];
    }

    $medicine_bonus = (int) ($medicine_skill['bonus'] ?? 0);
    $requested_dc = 15;
    $roll = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $roll + $medicine_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $requested_dc, $roll);
    $heal_amount = 0;
    if ($degree === 'critical_failure') {
      $heal_amount = -max(1, $this->rollSimpleDice('1d8'));
    }
    elseif ($degree === 'success') {
      $heal_amount = max(1, $this->rollSimpleDice('2d8'));
    }
    elseif ($degree === 'critical_success') {
      $heal_amount = max(1, $this->rollSimpleDice('4d8'));
    }

    $target_name = $this->resolveDungeonEntityName($target_entity);
    $actor_name = $this->resolveDungeonEntityName($actor_entity);
    $this->applyEntityHealing($target_entity, $heal_amount);
    $canonical_state = $this->loadCanonicalCharacterState($target_entity, $campaign_id);
    if (is_array($canonical_state)) {
      $this->applyCanonicalHealing($canonical_state, $heal_amount);
      $this->persistCanonicalCharacterState($target_entity, $campaign_id, $canonical_state);
    }
    $target_entity['state']['rest_activity']['last_treat_wounds_at'] = gmdate('c', $now);

    $summary = $heal_amount >= 0
      ? sprintf('%s treats %s\'s wounds for %d HP.', $actor_name, $target_name, $heal_amount)
      : sprintf('%s botches the treatment and %s takes %d damage.', $actor_name, $target_name, abs($heal_amount));

    return $this->finalizeRestActivity(
      $actor_id,
      'treat_wounds',
      600,
      $summary,
      [
        'target' => $target_entity_id,
        'roll' => $roll,
        'total' => $total,
        'dc' => $requested_dc,
        'degree' => $degree,
        'healing' => $heal_amount,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function processRefocusRestAction(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Refocus requires an acting character.'];
    }
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    if ($actor_index === NULL) {
      return ['error' => 'Refocus requires an active room participant.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $canonical_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $focus_max = max(0, $this->resolveCharacterFocusPoints($canonical_state, $actor_entity, 'max'));
    $focus_current = max(0, $this->resolveCharacterFocusPoints($canonical_state, $actor_entity, 'current'));
    if ($focus_max <= 0) {
      return ['error' => 'This character has no Focus Points to restore.'];
    }
    if ($focus_current >= $focus_max) {
      return ['error' => 'Focus Points are already full.'];
    }

    $restored = min(1, $focus_max - $focus_current);
    $this->writeEntityFocusPoints($actor_entity, $focus_current + $restored, $focus_max);
    if (is_array($canonical_state)) {
      $resources = $canonical_state['resources'] ?? [];
      $resources['focusPoints']['max'] = $focus_max;
      $resources['focusPoints']['current'] = min($focus_max, max(0, (int) ($resources['focusPoints']['current'] ?? $focus_current)) + $restored);
      $canonical_state['resources'] = $resources;
      $this->persistCanonicalCharacterState($actor_entity, $campaign_id, $canonical_state);
    }

    return $this->finalizeRestActivity(
      $actor_id,
      'refocus',
      600,
      sprintf('%s spends ten minutes refocusing and regains %d Focus Point.', $this->resolveDungeonEntityName($actor_entity), $restored),
      [
        'focus_restored' => $restored,
        'focus_points_current' => $focus_current + $restored,
        'focus_points_max' => $focus_max,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function processRepairRestAction(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Repair requires an acting character.'];
    }
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    if ($actor_index === NULL) {
      return ['error' => 'Repair requires an active room participant.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $shield = $this->findHeldShield($actor_entity);
    if (!$shield) {
      return ['error' => 'Repair currently requires a held shield or damaged gear in hand.'];
    }

    $current_hp = (int) ($shield['hp_current'] ?? $shield['hp']['current'] ?? 0);
    $max_hp = (int) ($shield['hp_max'] ?? $shield['hp']['max'] ?? 0);
    if ($max_hp <= 0 || $current_hp >= $max_hp) {
      return ['error' => 'There is no damaged shield or gear to repair.'];
    }

    $actor_character_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $crafting_bonus = (int) (($this->resolveCharacterSkillData($actor_character_state, 'crafting'))['bonus'] ?? 0);
    $roll = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $roll + $crafting_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 15, $roll);
    $repaired_hp = match ($degree) {
      'critical_success' => 10,
      'success' => 5,
      default => 0,
    };
    if ($repaired_hp <= 0) {
      return $this->finalizeRestActivity(
        $actor_id,
        'repair',
        600,
        sprintf('%s spends ten minutes attempting repairs, but %s is still damaged.', $this->resolveDungeonEntityName($actor_entity), (string) ($shield['name'] ?? 'the item')),
        [
          'item_name' => (string) ($shield['name'] ?? 'shield'),
          'repair_roll' => $roll,
          'repair_total' => $total,
          'repair_degree' => $degree,
          'repaired_hp' => 0,
        ],
        $game_state,
        $dungeon_data,
        $campaign_id
      );
    }

    $new_hp = min($max_hp, $current_hp + $repaired_hp);
    if (isset($shield['hp']) && is_array($shield['hp'])) {
      $shield['hp']['current'] = $new_hp;
      $shield['hp']['max'] = $max_hp;
    }
    $shield['hp_current'] = $new_hp;
    $shield['hp_max'] = $max_hp;
    if (isset($shield['broken_threshold']) && $new_hp > (int) $shield['broken_threshold']) {
      $shield['broken'] = FALSE;
    }
    $actor_entity = $this->updateHeldShield($actor_entity, $shield);

    return $this->finalizeRestActivity(
      $actor_id,
      'repair',
      600,
      sprintf('%s spends ten minutes repairing %s for %d HP.', $this->resolveDungeonEntityName($actor_entity), (string) ($shield['name'] ?? 'their shield'), $repaired_hp),
      [
        'item_name' => (string) ($shield['name'] ?? 'shield'),
        'repair_roll' => $roll,
        'repair_total' => $total,
        'repair_degree' => $degree,
        'repaired_hp' => $repaired_hp,
        'item_hp_current' => $new_hp,
        'item_hp_max' => $max_hp,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function processDailyPreparationsRestAction(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Daily Preparations require an acting character.'];
    }
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    if ($actor_index === NULL) {
      return ['error' => 'Daily Preparations require an active room participant.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $canonical_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $level = max(1, $this->resolveCharacterLevel($canonical_state, $actor_entity));
    $constitution_mod = $this->resolveCharacterConstitutionModifier($canonical_state, $actor_entity);
    $hit_point_recovery = max(1, $constitution_mod * $level);
    $this->applyEntityHealing($actor_entity, $hit_point_recovery);
    $this->restoreEntitySpellSlots($actor_entity);
    $entity_focus_max = $this->resolveCharacterFocusPoints($canonical_state, $actor_entity, 'max');
    if ($entity_focus_max > 0) {
      $this->writeEntityFocusPoints($actor_entity, $entity_focus_max, $entity_focus_max);
    }
    $condition_changes = $this->applyDailyPreparationConditionRecovery($actor_entity);

    if (is_array($canonical_state)) {
      $this->applyCanonicalHealing($canonical_state, $hit_point_recovery);
      $this->restoreCanonicalSpellSlots($canonical_state);
      $this->restoreCanonicalFocusPoints($canonical_state);
      $this->applyCanonicalDailyPreparationConditionRecovery($canonical_state);
      $this->persistCanonicalCharacterState($actor_entity, $campaign_id, $canonical_state);
    }
    $this->magicItemService->performDailyPreparations($actor_id, [], [], $game_state);

    $summary = sprintf(
      '%s completes daily preparations, recovering %d HP and resetting daily resources.',
      $this->resolveDungeonEntityName($actor_entity),
      $hit_point_recovery
    );

    return $this->finalizeRestActivity(
      $actor_id,
      'daily_preparations',
      28800,
      $summary,
      [
        'healing' => $hit_point_recovery,
        'conditions' => $condition_changes,
        'focus_points_restored' => $entity_focus_max,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function finalizeRestActivity(?string $actor_id, string $action_type, int $duration_seconds, string $narration, array $event_context, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $room_id = (string) ($game_state['encounter_context']['room_id'] ?? $dungeon_data['active_room_id'] ?? '');
    $resume_events = $room_id !== ''
      ? $this->startRoomSceneEncounter($actor_id, $room_id, $game_state, $dungeon_data, $campaign_id, NULL, 'The room scene resumes after the rest activity.')
      : [];

    return [
      'events' => array_merge([
        GameEventLogger::buildEvent($action_type, 'encounter', $actor_id, array_merge($event_context, [
          'round' => $game_state['round'] ?? NULL,
          'room_id' => $room_id,
        ]), $narration),
      ], $resume_events),
      'mutations' => [],
      'time_effects' => $this->buildRestTimeEffects($action_type, $actor_id, $duration_seconds, $dungeon_data),
      'narration' => $narration,
    ];
  }

  protected function findDungeonEntityIndexByInstanceId(array $dungeon_data, string $entity_id): ?int {
    return $this->canonicalProjectionService->findDungeonEntityIndexByInstanceId($dungeon_data, $entity_id);
  }

  protected function normalizeSkillRank(mixed $rank, mixed $proficiency): int {
    if (is_numeric($rank)) {
      return max(0, (int) $rank);
    }
    $normalized = strtolower(trim((string) ($proficiency ?? '')));
    return match ($normalized) {
      'trained' => 1,
      'expert' => 2,
      'master' => 3,
      'legendary' => 4,
      default => 0,
    };
  }

  protected function toBool(mixed $value): bool {
    if (is_bool($value)) {
      return $value;
    }
    if (is_numeric($value)) {
      return (int) $value !== 0;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], TRUE);
  }

  protected function resolveCurrentCampaignTimestamp(array $game_state): int {
    $datetime = (string) ($game_state['campaign_clock']['datetime'] ?? $game_state['game_time']['datetime'] ?? '');
    if ($datetime !== '' && ($timestamp = strtotime($datetime)) !== FALSE) {
      return $timestamp;
    }
    return time();
  }

  protected function resolveDungeonEntityName(array $entity): string {
    return (string) (
      $entity['state']['metadata']['display_name']
      ?? $entity['state']['metadata']['name']
      ?? $entity['name']
      ?? $entity['entity_ref']['name']
      ?? $entity['entity_ref']['content_id']
      ?? 'Unknown actor'
    );
  }

  protected function rollSimpleDice(string $notation): int {
    if (!preg_match('/^\s*(\d+)d(\d+)\s*$/i', $notation, $matches)) {
      return 0;
    }
    $count = max(1, (int) $matches[1]);
    $sides = max(1, (int) $matches[2]);
    $total = 0;
    for ($i = 0; $i < $count; $i++) {
      $total += $this->numberGenerationService->rollPathfinderDie($sides);
    }
    return $total;
  }

  protected function applyEntityHealing(array &$entity, int $delta): void {
    $current = (int) ($entity['state']['hit_points']['current'] ?? $entity['state']['hp_current'] ?? $entity['hit_points']['current'] ?? 0);
    $max = (int) ($entity['state']['hit_points']['max'] ?? $entity['state']['hp_max'] ?? $entity['hit_points']['max'] ?? $current);
    $next = max(0, min($max, $current + $delta));
    $entity['state']['hit_points']['current'] = $next;
    $entity['state']['hit_points']['max'] = $max;
    $entity['state']['hp_current'] = $next;
    $entity['state']['hp_max'] = $max;
    $entity['hit_points']['current'] = $next;
    $entity['hit_points']['max'] = $max;
  }

  protected function loadCanonicalCharacterState(array $entity, int $campaign_id): ?array {
    return $this->canonicalProjectionService->loadCanonicalCharacterState($entity, $campaign_id);
  }

  protected function persistCanonicalCharacterState(array $entity, int $campaign_id, array $character_state): void {
    $this->canonicalProjectionService->persistCanonicalCharacterState($entity, $campaign_id, $character_state);
  }

  /**
   * Resolve canonical character identity from runtime entity payload.
   *
   * @return array{character_id: string, instance_id: ?string}
   */
  protected function resolveCanonicalCharacterIdentity(array $entity): array {
    return $this->canonicalProjectionService->resolveCanonicalCharacterIdentity($entity);
  }

  /**
   * Resolve canonical character identity from participant entity_ref payload.
   *
   * @return array{character_id: string, instance_id: ?string}
   */
  protected function resolveCanonicalCharacterIdentityFromParticipantEntityRef(array $entity_ref, string $fallback_instance_id = ''): array {
    return $this->canonicalProjectionService->resolveCanonicalCharacterIdentityFromParticipantEntityRef($entity_ref, $fallback_instance_id);
  }

  protected function resolveCharacterSkillData(?array $character_state, string $skill_name): array {
    if (!is_array($character_state)) {
      return ['bonus' => 0, 'rank' => 0, 'proficiency' => ''];
    }
    $normalized = strtolower(trim($skill_name));
    $skills = $character_state['skills'] ?? [];
    if (isset($skills[$normalized]) && is_array($skills[$normalized])) {
      $entry = $skills[$normalized];
    }
    else {
      $entry = [];
      foreach ($skills as $candidate) {
        if (!is_array($candidate)) {
          continue;
        }
        $name = strtolower(trim((string) ($candidate['name'] ?? $candidate['id'] ?? '')));
        if ($name === $normalized) {
          $entry = $candidate;
          break;
        }
      }
    }

    $proficiency = (string) ($entry['proficiency'] ?? $entry['rank_name'] ?? '');
    return [
      'bonus' => (int) ($entry['bonus'] ?? $entry['modifier'] ?? $entry['total'] ?? 0),
      'rank' => $this->normalizeSkillRank($entry['rank'] ?? $entry['proficiency_rank'] ?? $entry['proficiencyRank'] ?? NULL, $proficiency),
      'proficiency' => $proficiency,
    ];
  }

  protected function characterHasInventoryItem(?array $character_state, array $entity, array $item_ids, array $name_fragments = []): bool {
    $search_roots = [];
    if (is_array($character_state)) {
      $search_roots[] = $character_state['inventory'] ?? NULL;
      $search_roots[] = $character_state['equipment'] ?? NULL;
    }
    $search_roots[] = $entity['inventory'] ?? NULL;
    $search_roots[] = $entity['equipment'] ?? NULL;
    $search_roots[] = $entity['state']['inventory'] ?? NULL;
    $search_roots[] = $entity['state']['equipment'] ?? NULL;

    foreach ($search_roots as $root) {
      if ($this->arrayContainsItemToken($root, $item_ids, $name_fragments)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  protected function arrayContainsItemToken(mixed $value, array $item_ids, array $name_fragments = []): bool {
    if (is_array($value)) {
      $item_id = strtolower(trim((string) ($value['item_id'] ?? $value['id'] ?? '')));
      $name = strtolower(trim((string) ($value['name'] ?? '')));
      if ($item_id !== '' && in_array($item_id, $item_ids, TRUE)) {
        return TRUE;
      }
      if ($name !== '') {
        $matched = TRUE;
        foreach ($name_fragments as $fragment) {
          if (!str_contains($name, strtolower($fragment))) {
            $matched = FALSE;
            break;
          }
        }
        if ($matched && !empty($name_fragments)) {
          return TRUE;
        }
      }
      foreach ($value as $child) {
        if ($this->arrayContainsItemToken($child, $item_ids, $name_fragments)) {
          return TRUE;
        }
      }
      return FALSE;
    }
    return FALSE;
  }

  protected function preparedSpellListContainsSpell(array $prepared_list, string $spell_name, string $spell_id = ''): bool {
    $needle_name = $this->normalizeSpellToken($spell_name);
    $needle_id = $this->normalizeSpellToken($spell_id);

    foreach ($prepared_list as $prepared_spell) {
      if (!is_scalar($prepared_spell)) {
        continue;
      }
      $candidate = $this->normalizeSpellToken((string) $prepared_spell);
      if ($candidate === '') {
        continue;
      }
      if ($needle_name !== '' && $candidate === $needle_name) {
        return TRUE;
      }
      if ($needle_id !== '' && $candidate === $needle_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  protected function normalizeSpellToken(string $value): string {
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
      return '';
    }
    $normalized = str_replace(['_', ' '], '-', $normalized);
    $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;
    return trim($normalized, '-');
  }

  protected function applyCanonicalStateAfterSpellConsume(array &$canonical_state, bool $is_focus_spell, int $slot_level, int $remaining): void {
    if (!isset($canonical_state['resources']) || !is_array($canonical_state['resources'])) {
      $canonical_state['resources'] = [];
    }
    $remaining = max(0, $remaining);

    if ($is_focus_spell) {
      if (!isset($canonical_state['resources']['focusPoints']) || !is_array($canonical_state['resources']['focusPoints'])) {
        $canonical_state['resources']['focusPoints'] = ['current' => $remaining, 'max' => $remaining];
        return;
      }
      $max = max($remaining, (int) ($canonical_state['resources']['focusPoints']['max'] ?? 0));
      $canonical_state['resources']['focusPoints']['max'] = $max;
      $canonical_state['resources']['focusPoints']['current'] = min($remaining, $max);
      return;
    }

    $slot_key = (string) max(1, $slot_level);
    if (!isset($canonical_state['resources']['spellSlots']) || !is_array($canonical_state['resources']['spellSlots'])) {
      $canonical_state['resources']['spellSlots'] = [];
    }
    if (!isset($canonical_state['resources']['spellSlots'][$slot_key]) || !is_array($canonical_state['resources']['spellSlots'][$slot_key])) {
      $canonical_state['resources']['spellSlots'][$slot_key] = ['current' => $remaining, 'max' => $remaining];
      return;
    }

    $max = max($remaining, (int) ($canonical_state['resources']['spellSlots'][$slot_key]['max'] ?? 0));
    $canonical_state['resources']['spellSlots'][$slot_key]['max'] = $max;
    $canonical_state['resources']['spellSlots'][$slot_key]['current'] = min($remaining, $max);
  }

  protected function syncCanonicalSpellcastingProjectionForActor(?int $encounter_id, string $actor_id, int $campaign_id, array &$dungeon_data, ?array $canonical_state = NULL): void {
    $this->canonicalProjectionService->syncCanonicalSpellcastingProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
  }

  protected function syncCanonicalSurvivalProjectionForActor(?int $encounter_id, string $actor_id, int $campaign_id, array &$dungeon_data, ?array $canonical_state = NULL): void {
    $this->canonicalProjectionService->syncCanonicalSurvivalProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
  }

  protected function applyCanonicalSurvivalResourcesToDungeonEntity(array &$entity, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSurvivalResourcesToDungeonEntity($entity, $character_state);
  }

  protected function applyCanonicalSurvivalResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSurvivalResourcesToParticipantEntityRef($entity_ref, $character_state);
  }

  /**
   * @return array{daysWithoutFood:int,daysWithoutWater:int,starvationDamagePhase:bool,thirstDamagePhase:bool}
   */
  protected function readCanonicalSurvivalStateFromCanonicalState(array $character_state): array {
    return $this->canonicalProjectionService->readCanonicalSurvivalStateFromCanonicalState($character_state);
  }

  protected function normalizeSpellSlotRankKey(string $slot_key): ?string {
    return $this->canonicalProjectionService->normalizeSpellSlotRankKey($slot_key);
  }

  protected function resolveEffectiveCantripLevel(?array $canonical_state, array $participant_entity_ref): int {
    return $this->canonicalProjectionService->resolveEffectiveCantripLevel($canonical_state, $participant_entity_ref);
  }

  protected function resolveParticipantFocusPointCurrent(array $participant_entity_ref): int {
    return $this->canonicalProjectionService->resolveParticipantFocusPointCurrent($participant_entity_ref);
  }

  protected function applyCanonicalSpellcastingResourcesToDungeonEntity(array &$entity, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSpellcastingResourcesToDungeonEntity($entity, $character_state);
  }

  protected function applyCanonicalSpellcastingResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSpellcastingResourcesToParticipantEntityRef($entity_ref, $character_state);
  }

  protected function buildLegacySpellSlotProjection(array $spell_slots): array {
    return $this->canonicalProjectionService->buildLegacySpellSlotProjection($spell_slots);
  }

  protected function persistEncounterParticipantEntityRef(int $participant_id, array $entity_ref): void {
    $this->canonicalProjectionService->persistEncounterParticipantEntityRef($participant_id, $entity_ref);
  }

  protected function normalizeSpellResourceErrorMessage(string $message, bool $is_focus_spell, int $slot_level): string {
    $normalized = strtolower(trim($message));
    if (str_contains($normalized, 'focus point')) {
      return 'No Focus Points remaining.';
    }
    if (str_contains($normalized, 'spell slot')) {
      return sprintf('No level-%d spell slots remaining.', max(1, $slot_level));
    }

    $trimmed = trim($message);
    if ($trimmed !== '') {
      return str_ends_with($trimmed, '.') ? $trimmed : $trimmed . '.';
    }

    return $is_focus_spell
      ? 'No Focus Points remaining.'
      : sprintf('No level-%d spell slots remaining.', max(1, $slot_level));
  }

  protected function resolveCharacterFocusPoints(?array $character_state, array $entity, string $field): int {
    return $this->canonicalProjectionService->resolveCharacterFocusPoints($character_state, $entity, $field);
  }

  protected function resolveCharacterLevel(?array $character_state, array $entity): int {
    return $this->canonicalProjectionService->resolveCharacterLevel($character_state, $entity);
  }

  protected function resolveCharacterConstitutionModifier(?array $character_state, array $entity): int {
    return $this->canonicalProjectionService->resolveCharacterConstitutionModifier($character_state, $entity);
  }

  protected function applyCanonicalHealing(array &$character_state, int $delta): void {
    $this->canonicalProjectionService->applyCanonicalHealing($character_state, $delta);
  }

  protected function restoreCanonicalSpellSlots(array &$character_state): void {
    $this->canonicalProjectionService->restoreCanonicalSpellSlots($character_state);
  }

  protected function restoreCanonicalFocusPoints(array &$character_state): void {
    $this->canonicalProjectionService->restoreCanonicalFocusPoints($character_state);
  }

  protected function applyCanonicalDailyPreparationConditionRecovery(array &$character_state): void {
    $this->canonicalProjectionService->applyCanonicalDailyPreparationConditionRecovery($character_state);
  }

  protected function readEntityFocusPoints(array $entity, string $field): int {
    return $this->canonicalProjectionService->readEntityFocusPoints($entity, $field);
  }

  protected function writeEntityFocusPoints(array &$entity, int $current, int $max): void {
    $this->canonicalProjectionService->writeEntityFocusPoints($entity, $current, $max);
  }

  protected function restoreEntitySpellSlots(array &$entity): void {
    $this->canonicalProjectionService->restoreEntitySpellSlots($entity);
  }

  protected function applyDailyPreparationConditionRecovery(array &$entity): array {
    return $this->canonicalProjectionService->applyDailyPreparationConditionRecovery($entity);
  }

  /**
   * Resolves travel duration from request or connection metadata.
   */
  protected function resolveTravelSeconds(array $capability, array $params): int {
    foreach ([$params, $capability] as $source) {
      foreach (['travel_time_seconds', 'duration_seconds', 'time_cost_seconds'] as $key) {
        if (isset($source[$key]) && is_numeric($source[$key])) {
          return max(0, (int) $source[$key]);
        }
      }
      foreach (['travel_time_minutes', 'duration_minutes', 'time_cost_minutes', 'travel_minutes'] as $key) {
        if (isset($source[$key]) && is_numeric($source[$key])) {
          return max(0, (int) $source[$key]) * 60;
        }
      }
      if (isset($source['travel_time']) && is_array($source['travel_time'])) {
        $nested = $source['travel_time'];
        if (isset($nested['seconds']) && is_numeric($nested['seconds'])) {
          return max(0, (int) $nested['seconds']);
        }
        if (isset($nested['minutes']) && is_numeric($nested['minutes'])) {
          return max(0, (int) $nested['minutes']) * 60;
        }
      }
    }

    return self::DEFAULT_ROOM_TRANSITION_SECONDS;
  }

  /**
   * Fallback navigation capability builder for isolated tests.
   *
   * @deprecated
   *   Transitional fallback kept only for isolated EncounterPhaseHandler tests
   *   that run without injected NavigationService wiring. Primary runtime paths
   *   must stay on NavigationService::buildNavigationCapabilitiesWithRoadNetwork().
   */
  protected function buildFallbackNavigationCapabilities(array $dungeon_data, string $room_id): array {
    $service = $this->requireNavigationService();
    return $service->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $room_id);
  }

  protected function requireNavigationService(): NavigationService {
    if ($this->navigationService === NULL) {
      throw new \RuntimeException('Encounter navigation contract violation: NavigationService dependency is required; fallback navigation support is not allowed.');
    }

    return $this->navigationService;
  }

  protected function requireNavigationRuntime(): NavigationRuntimeService {
    if ($this->navigationRuntime instanceof NavigationRuntimeService) {
      return $this->navigationRuntime;
    }

    $service = \Drupal::service('dungeoncrawler_content.navigation_runtime');
    if (!($service instanceof NavigationRuntimeService)) {
      throw new \RuntimeException('Encounter navigation contract violation: NavigationRuntimeService dependency is required for room instantiation.');
    }
    $this->navigationRuntime = $service;
    return $this->navigationRuntime;
  }

  /**
   * Moves an entity placement into a room.
   */
  protected function moveEntityToRoom(array &$dungeon_data, string $actor_id, string $room_id, array $hex, int $facing = 0): void {
    $h3_index_res14 = $this->resolveRoomHexH3IndexRes14($dungeon_data, $room_id, $hex);
    foreach ($dungeon_data['entities'] ?? [] as &$entity) {
      $entity_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($entity_id !== $actor_id) {
        continue;
      }
      if (!isset($entity['placement']) || !is_array($entity['placement'])) {
        $entity['placement'] = [];
      }
      $entity['placement']['room_id'] = $room_id;
      $entity['placement']['hex'] = [
        'q' => (int) ($hex['q'] ?? 0),
        'r' => (int) ($hex['r'] ?? 0),
      ];
      $entity['placement']['facing'] = $this->normalizeFacingDirection($facing);
      $entity['placement']['h3_index_res14'] = $h3_index_res14;
      break;
    }
    unset($entity);
  }

  /**
   * Normalize one facing direction into canonical [0..5] range.
   */
  protected function normalizeFacingDirection(int $facing): int {
    $facing = $facing % 6;
    if ($facing < 0) {
      $facing += 6;
    }

    return $facing;
  }

  /**
   * Resolve Res14 H3 index for a room hex coordinate.
   */
  protected function resolveRoomHexH3IndexRes14(array &$dungeon_data, string $room_id, array $hex): string {
    $room = $this->findRoomById($dungeon_data, $room_id);
    if ($room === NULL) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: room %s not found while resolving placement H3 index.',
        $room_id
      ));
    }

    $target_q = (int) ($hex['q'] ?? 0);
    $target_r = (int) ($hex['r'] ?? 0);
    foreach ((array) ($room['hexes'] ?? []) as $room_hex) {
      if (!is_array($room_hex)) {
        continue;
      }
      if ((int) ($room_hex['q'] ?? 0) !== $target_q || (int) ($room_hex['r'] ?? 0) !== $target_r) {
        continue;
      }
      $h3_index = trim((string) ($room_hex['h3_index_res14'] ?? $room_hex['h3_index'] ?? ''));
      if ($h3_index === '') {
        $this->ensureRoomHexH3IndexesInDungeonPayload($dungeon_data, $room_id);
        $refreshed_room = $this->findRoomById($dungeon_data, $room_id);
        foreach ((array) ($refreshed_room['hexes'] ?? []) as $refreshed_hex) {
          if (!is_array($refreshed_hex)) {
            continue;
          }
          if ((int) ($refreshed_hex['q'] ?? 0) !== $target_q || (int) ($refreshed_hex['r'] ?? 0) !== $target_r) {
            continue;
          }
          $refreshed_h3_index = trim((string) ($refreshed_hex['h3_index_res14'] ?? $refreshed_hex['h3_index'] ?? ''));
          if ($refreshed_h3_index !== '') {
            return strtolower($refreshed_h3_index);
          }
          break;
        }

        throw new \RuntimeException(sprintf(
          'Encounter transition contract violation: room %s hex (%d,%d) missing h3_index_res14.',
          $room_id,
          $target_q,
          $target_r
        ));
      }

      return strtolower($h3_index);
    }

    throw new \RuntimeException(sprintf(
      'Encounter transition contract violation: room %s missing placement hex (%d,%d).',
      $room_id,
      $target_q,
      $target_r
    ));
  }

  /**
   * Ensure one room's hexes carry authoritative Res14 H3 indexes.
   */
  protected function ensureRoomHexH3IndexesInDungeonPayload(array &$dungeon_data, string $room_id): void {
    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: cannot backfill h3_index_res14 for room %s without dungeon_id.',
        $room_id
      ));
    }

    foreach ((array) ($dungeon_data['rooms'] ?? []) as $index => $room) {
      if (!is_array($room) || (string) ($room['room_id'] ?? '') !== $room_id) {
        continue;
      }
      /** @var \Drupal\dungeoncrawler_content\Service\MapGeneratorService $map_generator */
      $map_generator = \Drupal::service('dungeoncrawler_content.map_generator');
      $dungeon_data['rooms'][$index] = $map_generator->ensureRoomHexH3Indexes($dungeon_id, $room);
      return;
    }
  }

  /**
   * Builds combat encounter context if the room has untriggered hostile content.
   */
  protected function buildCombatEncounterContext(string $room_id, array &$dungeon_data, array $game_state, int $campaign_id, ?string $preferred_actor_id = NULL): array {
    $room = $this->findRoomById($dungeon_data, $room_id);
    $room_entities = $this->awaitBootstrapRoomEntityHydration($room_id, $dungeon_data, $campaign_id, $preferred_actor_id);
    $gameplay_state = is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : [];
    $encounter_template = $gameplay_state['encounter_template'] ?? NULL;
    if (!$this->roomHasBootstrapPlayerCombatant($room_entities, $room_id)) {
      $this->logger->warning('Encounter bootstrap deferred: no player combatant resolved for room {room_id}.', [
        'room_id' => $room_id,
      ]);
      return ['should_trigger' => FALSE];
    }
    if (!$this->hasHostileDispositionInRoom($room_id, $dungeon_data, $campaign_id, $room_entities)) {
      return ['should_trigger' => FALSE];
    }

    $hostile_entities = [];
    foreach ($room_entities as $entity) {
      if (!is_array($entity) || (string) ($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      if ($this->isBootstrapRoomHostileCombatant($entity, $room_entities, $campaign_id, $room_id)) {
        $hostile_entities[] = $entity;
      }
    }
    if ($hostile_entities === []) {
      return ['should_trigger' => FALSE];
    }

    return [
      'should_trigger' => TRUE,
      'reason' => $encounter_template['reason'] ?? 'Hostile disposition detected between room actors.',
      'encounter_context' => [
        'template' => $encounter_template,
        'enemies' => $hostile_entities,
        'room_id' => $room_id,
      ],
    ];
  }

  /**
   * Determine whether a room has at least one player-side combatant.
   *
   * @param array<int,array<string,mixed>> $room_entities
   *   Room-scoped runtime entities.
   */
  protected function roomHasBootstrapPlayerCombatant(array $room_entities, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }

    foreach ($room_entities as $entity) {
      if (!is_array($entity) || (string) ($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''))));
      $team = strtolower(trim((string) ($entity['state']['metadata']['team'] ?? ($entity['state']['team'] ?? ''))));
      if (
        $entity_type === 'player_character'
        || in_array($team, ['player', 'player_character', 'pc', 'party', 'adventurer', 'hero', 'ally', 'friendly', 'companion'], TRUE)
      ) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Determine whether any actor in the room is hostile toward another actor.
   */
  protected function hasHostileDispositionInRoom(string $room_id, array $dungeon_data, int $campaign_id, ?array $room_entities = NULL): bool {
    if ($campaign_id <= 0) {
      return FALSE;
    }

    if (!is_array($room_entities)) {
      $room_entities = $this->collectBootstrapRoomEntities($room_id, $dungeon_data, $campaign_id);
    }
    $room_entity_refs = [];
    foreach ($room_entities as $entity) {
      if (!is_array($entity) || (string) ($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $entity_ref = $this->resolveDispositionEntityRefFromRuntimeEntity($entity);
      if ($entity_ref !== '') {
        $room_entity_refs[] = $entity_ref;
      }
    }
    $room_entity_refs = array_values(array_unique($room_entity_refs));
    if (count($room_entity_refs) < 2) {
      return FALSE;
    }

    foreach ($room_entity_refs as $source_ref) {
      $targets = array_values(array_filter($room_entity_refs, static fn(string $candidate): bool => $candidate !== $source_ref));
      if ($targets === []) {
        continue;
      }

      foreach ($targets as $target_ref) {
        if ($this->hasHostileDispositionBetweenActorRefs($campaign_id, $source_ref, $target_ref, $room_id)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Determine whether one runtime entity should bootstrap onto the enemy team.
   *
   * @param array<int,array<string,mixed>> $room_entities
   *   Room-scoped runtime entities.
   */
  protected function isBootstrapRoomHostileCombatant(array $entity, array $room_entities, int $campaign_id, string $room_id): bool {
    $content_type = strtolower(trim((string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''))));
    $team = strtolower(trim((string) ($entity['state']['metadata']['team'] ?? ($entity['state']['team'] ?? ''))));
    if ($content_type === 'player_character' || in_array($team, ['player', 'player_character', 'pc', 'ally', 'friendly', 'companion'], TRUE)) {
      return FALSE;
    }
    if (in_array($team, ['enemy', 'hostile', 'monster'], TRUE) || $content_type === 'creature') {
      return TRUE;
    }

    $source_ref = $this->resolveDispositionEntityRefFromRuntimeEntity($entity);
    if ($source_ref === '') {
      return FALSE;
    }

    foreach ($room_entities as $target_entity) {
      if (!is_array($target_entity) || $target_entity === $entity) {
        continue;
      }
      $target_content_type = strtolower(trim((string) ($target_entity['entity_type'] ?? ($target_entity['entity_ref']['content_type'] ?? ''))));
      $target_team = strtolower(trim((string) ($target_entity['state']['metadata']['team'] ?? ($target_entity['state']['team'] ?? ''))));
      if (
        $target_content_type !== 'player_character'
        && !in_array($target_team, ['player', 'player_character', 'pc', 'ally', 'friendly', 'companion'], TRUE)
      ) {
        continue;
      }
      $target_ref = $this->resolveDispositionEntityRefFromRuntimeEntity($target_entity);
      if ($target_ref === '' || $target_ref === $source_ref) {
        continue;
      }
      if (
        $this->hasHostileDispositionBetweenActorRefs($campaign_id, $source_ref, $target_ref, $room_id)
        || $this->hasHostileDispositionBetweenActorRefs($campaign_id, $target_ref, $source_ref, $room_id)
      ) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Resolve whether one actor ref is hostile toward another in the same room.
   */
  protected function hasHostileDispositionBetweenActorRefs(int $campaign_id, string $source_ref, string $target_ref, string $room_id): bool {
    $source_ref = trim($source_ref);
    $target_ref = trim($target_ref);
    if ($campaign_id <= 0 || $source_ref === '' || $target_ref === '' || $source_ref === $target_ref) {
      return FALSE;
    }

    $disposition_resolver = $this->resolveDispositionResolverService();
    if ($disposition_resolver instanceof DispositionResolverService) {
      $dto = $disposition_resolver->resolveActorTargetDisposition(
        $campaign_id,
        $source_ref,
        $target_ref,
        $this->buildInstitutionAwareResolverContext($campaign_id, $source_ref, $target_ref, [
          'room_id' => $room_id,
        ])
      );
      if (is_array($dto)) {
        $hostile_flag = (bool) ($dto['policy_flags']['hostile'] ?? FALSE);
        $effective_score = isset($dto['effective_disposition_score']) && is_numeric($dto['effective_disposition_score'])
          ? DispositionAuthorityContract::clampScore((int) round((float) $dto['effective_disposition_score']))
          : 0;
        if ($hostile_flag || $this->isHostileDispositionScore($effective_score)) {
          return TRUE;
        }
      }
    }

    $relationship_attitude = $this->resolveRelationshipAttitudeService();
    if ($relationship_attitude instanceof RelationshipAttitudeService) {
      $edge = $relationship_attitude->resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id);
      $edge_score = isset($edge['score']) && is_numeric($edge['score'])
        ? DispositionAuthorityContract::clampScore((int) round((float) $edge['score']))
        : (DispositionAuthorityContract::attitudeToScore((string) ($edge['attitude'] ?? '')) ?? 0);
      if ($this->isHostileDispositionScore($edge_score)) {
        return TRUE;
      }
    }

    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService) {
      $summary = $actor_disposition->getDispositionSummary($campaign_id, $source_ref);
      $score = isset($summary['current_score']) && is_numeric($summary['current_score'])
        ? DispositionAuthorityContract::clampScore((int) round((float) $summary['current_score']))
        : (DispositionAuthorityContract::attitudeToScore((string) ($summary['current_attitude'] ?? '')) ?? 0);
      if ($this->isHostileDispositionScore($score)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Wait for canonical room actor hydration before bootstrap mode selection.
   *
   * @return array<int, array<string,mixed>>
   *   Hydrated active-room entities.
   */
  protected function awaitBootstrapRoomEntityHydration(string $room_id, array &$dungeon_data, int $campaign_id, ?string $preferred_actor_id = NULL): array {
    $room_id = trim($room_id);
    if ($campaign_id <= 0 || $room_id === '') {
      return [];
    }

    $room_hints = $this->collectRoomRuntimeEntityHints($room_id, $dungeon_data);
    $max_attempts = 8;
    $sleep_micros = 150000;
    $last_readiness = [
      'ready' => FALSE,
      'player_present' => FALSE,
      'expected_npc_count' => 0,
      'resolved_npc_count' => 0,
      'entities' => [],
    ];

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
      $this->synchronizeBootstrapRoomEntities($room_id, $dungeon_data, $campaign_id, $preferred_actor_id);
      $room_entities = $this->collectBootstrapRoomEntities($room_id, $dungeon_data, $campaign_id, $preferred_actor_id);
      $readiness = $this->evaluateBootstrapRoomEntityReadiness($room_entities, $room_hints);
      $last_readiness = $readiness + ['entities' => $room_entities];
      if (!empty($readiness['ready'])) {
        return $room_entities;
      }

      if ($attempt < $max_attempts) {
        $this->logger->notice(
          'Encounter bootstrap hydration pending: campaign={campaign_id} room={room_id} attempt={attempt}/{max_attempts} player_present={player_present} expected_npc_count={expected_npc_count} resolved_npc_count={resolved_npc_count}',
          [
            'campaign_id' => $campaign_id,
            'room_id' => $room_id,
            'attempt' => $attempt,
            'max_attempts' => $max_attempts,
            'player_present' => !empty($readiness['player_present']) ? 'yes' : 'no',
            'expected_npc_count' => (int) ($readiness['expected_npc_count'] ?? 0),
            'resolved_npc_count' => (int) ($readiness['resolved_npc_count'] ?? 0),
          ]
        );
        usleep($sleep_micros);
      }
    }

    throw new \RuntimeException(sprintf(
      'Encounter bootstrap hydration contract violation: campaign %d room %s actor hydration did not converge (player_present=%s expected_npc_count=%d resolved_npc_count=%d attempts=%d).',
      $campaign_id,
      $room_id,
      !empty($last_readiness['player_present']) ? 'yes' : 'no',
      (int) ($last_readiness['expected_npc_count'] ?? 0),
      (int) ($last_readiness['resolved_npc_count'] ?? 0),
      $max_attempts
    ));
  }

  /**
   * Synchronize canonical room actors into the compatibility payload/runtime slice.
   */
  protected function synchronizeBootstrapRoomEntities(string $room_id, array &$dungeon_data, int $campaign_id, ?string $preferred_actor_id = NULL): void {
    $room_id = trim($room_id);
    if ($campaign_id <= 0 || $room_id === '') {
      return;
    }

    $dungeon_data['active_room_id'] = $room_id;
    $dungeon_data['current_room_id'] = $room_id;
    $runtime_sync = $this->resolveCampaignCharacterRuntimeSyncService();
    if ($runtime_sync instanceof CampaignCharacterRuntimeSyncService) {
      $dungeon_data = $runtime_sync->syncActiveRoomPlayerEntities($dungeon_data, $campaign_id, $preferred_actor_id, [
        'trace_id' => 'bootstrap_room_entity_hydration',
      ]);
    }

    $actor_store = $this->resolveActorRuntimeStateStoreService();
    if ($actor_store instanceof ActorRuntimeStateStore && is_array($dungeon_data['entities'] ?? NULL)) {
      $actor_store->syncFromEntities($campaign_id, $dungeon_data['entities']);
    }
  }

  /**
   * Collect room runtime entities from canonical runtime APIs plus local payload.
   *
   * @return array<int, array<string,mixed>>
   *   Unique runtime entities scoped to one room.
   */
  protected function collectBootstrapRoomEntities(string $room_id, array $dungeon_data, int $campaign_id, ?string $preferred_actor_id = NULL): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return [];
    }

    $indexed = [];
    $actor_store = $this->resolveActorRuntimeStateStoreService();
    if ($actor_store instanceof ActorRuntimeStateStore) {
      foreach ($actor_store->loadActorEntities($campaign_id) as $entity) {
        if (!is_array($entity) || trim((string) ($entity['placement']['room_id'] ?? '')) !== $room_id) {
          continue;
        }
        $key = $this->buildBootstrapRoomEntityIdentityKey($entity);
        if ($key !== '') {
          $indexed[$key] = $entity;
        }
      }
    }

    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity) || trim((string) ($entity['placement']['room_id'] ?? '')) !== $room_id) {
        continue;
      }
      $key = $this->buildBootstrapRoomEntityIdentityKey($entity);
      if ($key !== '') {
        $indexed[$key] = $entity;
      }
    }

    $preferred_actor_id = trim((string) $preferred_actor_id);
    if ($preferred_actor_id !== '') {
      uasort($indexed, static function (array $left, array $right) use ($preferred_actor_id): int {
        $left_id = trim((string) ($left['entity_instance_id'] ?? $left['instance_id'] ?? $left['id'] ?? ''));
        $right_id = trim((string) ($right['entity_instance_id'] ?? $right['instance_id'] ?? $right['id'] ?? ''));
        $left_score = $left_id === $preferred_actor_id ? 0 : 1;
        $right_score = $right_id === $preferred_actor_id ? 0 : 1;
        return $left_score <=> $right_score;
      });
    }

    return array_values($indexed);
  }

  /**
   * Evaluate whether room actor hydration is ready for bootstrap mode choice.
   *
   * @param array<int,array<string,mixed>> $room_entities
   *   Candidate room entities.
   * @param array<string,mixed> $room_hints
   *   Expected actor hints derived from canonical room contents.
   *
   * @return array<string,mixed>
   *   Readiness metadata.
   */
  protected function evaluateBootstrapRoomEntityReadiness(array $room_entities, array $room_hints): array {
    $player_present = FALSE;
    $resolved_npc_content_ids = [];

    foreach ($room_entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''))));
      $team = strtolower(trim((string) ($entity['state']['metadata']['team'] ?? ($entity['state']['team'] ?? ''))));
      if ($entity_type === 'player_character' || in_array($team, ['player', 'player_character', 'pc'], TRUE)) {
        $player_present = TRUE;
      }

      $content_id = $this->normalizeBootstrapRoomActorContentId((string) (
        $entity['entity_ref']['content_id']
        ?? $entity['state']['metadata']['content_id']
        ?? ''
      ));
      if ($content_id !== '') {
        $resolved_npc_content_ids[$content_id] = TRUE;
      }
    }

    $expected_npc_content_ids = [];
    foreach ((array) ($room_hints['npc_content_ids'] ?? []) as $content_id) {
      $normalized = $this->normalizeBootstrapRoomActorContentId((string) $content_id);
      if ($normalized !== '') {
        $expected_npc_content_ids[$normalized] = TRUE;
      }
    }

    $expected_npc_count = count($expected_npc_content_ids);
    $resolved_npc_count = 0;
    foreach (array_keys($expected_npc_content_ids) as $content_id) {
      if (isset($resolved_npc_content_ids[$content_id])) {
        $resolved_npc_count++;
      }
    }

    return [
      'ready' => $player_present && $resolved_npc_count >= $expected_npc_count,
      'player_present' => $player_present,
      'expected_npc_count' => $expected_npc_count,
      'resolved_npc_count' => $resolved_npc_count,
    ];
  }

  /**
   * Collect expected room actor hints from runtime payload or campaign room rows.
   *
   * @return array<string,mixed>
   *   Room actor hint metadata.
   */
  protected function collectRoomRuntimeEntityHints(string $room_id, array $dungeon_data): array {
    $room = $this->findRoomById($dungeon_data, $room_id);
    $hints = is_array($room['runtime_entity_hints'] ?? NULL) ? $room['runtime_entity_hints'] : [];
    if ($hints !== []) {
      return $hints;
    }

    $row = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['contents_data'])
      ->condition('campaign_id', (int) ($dungeon_data['campaign_id'] ?? 0))
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return [];
    }

    return $this->buildRuntimeEntityHintsFromContentsData(
      json_decode((string) ($row['contents_data'] ?? '{}'), TRUE)
    );
  }

  /**
   * Build normalized room actor hints from canonical contents data.
   *
   * @param mixed $contents_data
   *   Decoded contents payload.
   *
   * @return array<string,mixed>
   *   Normalized hint payload.
   */
  protected function buildRuntimeEntityHintsFromContentsData($contents_data): array {
    $contents_data = is_array($contents_data) ? $contents_data : [];
    $hints = [
      'npc_content_ids' => [],
      'entity_count' => 0,
      'creature_count' => 0,
    ];

    foreach ((array) ($contents_data['npcs'] ?? []) as $npc) {
      if (!is_array($npc)) {
        continue;
      }
      $content_id = trim((string) ($npc['content_id'] ?? ''));
      if ($content_id !== '') {
        $hints['npc_content_ids'][] = $content_id;
      }
      $hints['entity_count']++;
    }
    foreach ((array) ($contents_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $content_id = trim((string) (
        $entity['entity_ref']['content_id']
        ?? $entity['content_id']
        ?? ''
      ));
      if ($content_id !== '') {
        $hints['npc_content_ids'][] = $content_id;
      }
      $hints['entity_count']++;
    }
    foreach ((array) ($contents_data['creatures'] ?? []) as $creature) {
      if (!is_array($creature)) {
        continue;
      }
      $content_id = trim((string) (
        $creature['entity_ref']['content_id']
        ?? $creature['content_id']
        ?? ''
      ));
      if ($content_id !== '') {
        $hints['npc_content_ids'][] = $content_id;
      }
      $hints['creature_count']++;
      $hints['entity_count']++;
    }

    $hints['npc_content_ids'] = array_values(array_unique(array_filter(array_map('strval', $hints['npc_content_ids']))));
    return $hints;
  }

  /**
   * Resolve a stable identity key for one bootstrap room entity.
   */
  protected function buildBootstrapRoomEntityIdentityKey(array $entity): string {
    $instance_id = trim((string) (
      $entity['entity_instance_id']
      ?? $entity['instance_id']
      ?? $entity['id']
      ?? ''
    ));
    if ($instance_id !== '') {
      return 'instance:' . $instance_id;
    }

    $content_id = $this->normalizeBootstrapRoomActorContentId((string) (
      $entity['entity_ref']['content_id']
      ?? $entity['state']['metadata']['content_id']
      ?? ''
    ));
    if ($content_id !== '') {
      return 'content:' . $content_id;
    }

    return '';
  }

  /**
   * Normalize room actor content identifiers across runtime/canonical payloads.
   */
  protected function normalizeBootstrapRoomActorContentId(string $content_id): string {
    $content_id = strtolower(trim($content_id));
    if (str_starts_with($content_id, 'npc_') && strlen($content_id) > 4) {
      return substr($content_id, 4);
    }
    if (str_starts_with($content_id, 'npc-') && strlen($content_id) > 4) {
      return substr($content_id, 4);
    }
    return $content_id;
  }

  /**
   * Resolve canonical runtime sync service when available.
   */
  protected function resolveCampaignCharacterRuntimeSyncService(): ?CampaignCharacterRuntimeSyncService {
    if (!\Drupal::hasService('dungeoncrawler_content.campaign_character_runtime_sync')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.campaign_character_runtime_sync');
    return $service instanceof CampaignCharacterRuntimeSyncService ? $service : NULL;
  }

  /**
   * Resolve actor runtime state store when available.
   */
  protected function resolveActorRuntimeStateStoreService(): ?ActorRuntimeStateStore {
    if (!\Drupal::hasService('dungeoncrawler_content.actor_runtime_state_store')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.actor_runtime_state_store');
    return $service instanceof ActorRuntimeStateStore ? $service : NULL;
  }

  /**
   * Resolve canonical entity-ref for disposition/relationship lookup.
   */
  protected function resolveDispositionEntityRefFromRuntimeEntity(array $entity): string {
    $candidate = trim((string) (
      $entity['entity_instance_id']
      ?? $entity['instance_id']
      ?? $entity['id']
      ?? ''
    ));
    if ($candidate !== '') {
      return $candidate;
    }

    return trim((string) (
      $entity['entity_ref']['content_id']
      ?? $entity['state']['metadata']['content_id']
      ?? ''
    ));
  }

  /**
   * Resolve actor disposition service only when available.
   */
  protected function resolveActorDispositionService(): ?ActorDispositionService {
    if (!\Drupal::hasService('dungeoncrawler_content.actor_disposition_service')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.actor_disposition_service');
    return $service instanceof ActorDispositionService ? $service : NULL;
  }

  /**
   * Resolve relationship-attitude service only when available.
   */
  protected function resolveRelationshipAttitudeService(): ?RelationshipAttitudeService {
    if (!\Drupal::hasService('dungeoncrawler_content.relationship_attitude_service')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.relationship_attitude_service');
    return $service instanceof RelationshipAttitudeService ? $service : NULL;
  }

  /**
   * Resolve disposition resolver service only when available.
   */
  protected function resolveDispositionResolverService(): ?DispositionResolverService {
    if (!\Drupal::hasService('dungeoncrawler_content.disposition_resolver_service')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.disposition_resolver_service');
    return $service instanceof DispositionResolverService ? $service : NULL;
  }

  /**
   * Build resolver context with centralized institutional contribution when available.
   *
   * @param array<string,mixed> $context
   *   Base resolver context.
   *
   * @return array<string,mixed>
   *   Institution-aware resolver context.
   */
  protected function buildInstitutionAwareResolverContext(
    int $campaign_id,
    string $source_ref,
    string $target_ref,
    array $context = []
  ): array {
    if (
      $campaign_id <= 0
      || trim($source_ref) === ''
      || trim($target_ref) === ''
      || !\Drupal::hasService('dungeoncrawler_content.institution_disposition_score_assembler')
    ) {
      return $context;
    }

    $service = \Drupal::service('dungeoncrawler_content.institution_disposition_score_assembler');
    if (!$service instanceof InstitutionDispositionScoreAssemblerService) {
      return $context;
    }

    $institution = $service->buildActorTargetInstitutionAdjustment($campaign_id, $source_ref, $target_ref);
    return $context + [
      'institution_score' => (int) ($institution['score'] ?? 0),
    ];
  }

  /**
   * Determine whether score crosses canonical hostility threshold.
   */
  protected function isHostileDispositionScore(int $score): bool {
    return DispositionAuthorityContract::isHostileScore($score);
  }

  /**
   * Resolves whether a room entity should participate in combat and on which team.
   */
  protected function resolveEncounterParticipantTeam(array $entity, string $content_type, string $instance_id, array $enemy_instance_ids): ?string {
    $content_type = strtolower(trim((string) $content_type));
    $metadata = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
    $role = strtolower(trim((string) ($metadata['role'] ?? $entity['state']['role'] ?? '')));
    $follower_kind = strtolower(trim((string) ($metadata['follower_kind'] ?? ($metadata['bond_contract']['follower_kind'] ?? ''))));
    $owner_character_id = (int) ($metadata['owner_character_id'] ?? ($metadata['bond_contract']['owner_character_id'] ?? 0));
    $is_follower_companion = $follower_kind !== ''
      || $owner_character_id > 0
      || str_contains($role, 'familiar')
      || str_contains($role, 'companion')
      || str_contains($role, 'follower');
    if (in_array($content_type, ['player_character', 'player'], TRUE)) {
      return 'player';
    }

    if (in_array($instance_id, $enemy_instance_ids, TRUE)) {
      return 'enemy';
    }

    $raw_team = strtolower(trim((string) (
      $metadata['team']
      ?? $entity['state']['team']
      ?? ''
    )));

    if ($is_follower_companion && !in_array($raw_team, ['enemy', 'hostile', 'monster', 'monsters', 'npc', 'creature'], TRUE)) {
      return 'ally';
    }

    if (in_array($raw_team, ['enemy', 'hostile', 'monster', 'monsters', 'npc', 'creature'], TRUE)) {
      return 'enemy';
    }
    if (in_array($raw_team, ['ally', 'friendly', 'companion'], TRUE)) {
      return 'ally';
    }
    if (in_array($raw_team, ['player', 'player_character', 'pc', 'party', 'adventurer', 'hero'], TRUE)) {
      return 'player';
    }
    if ($content_type === 'character') {
      return $is_follower_companion ? 'ally' : 'player';
    }
    if (in_array($raw_team, ['neutral', 'indifferent', ''], TRUE)) {
      return $content_type === 'creature' ? 'enemy' : NULL;
    }

    return $content_type === 'creature' ? 'enemy' : NULL;
  }

  /**
   * Marks a room's encounter as triggered.
   */
  protected function markRoomEncounterTriggered(array &$dungeon_data, string $room_id): void {
    if (empty($dungeon_data['rooms'])) {
      return;
    }

    foreach ($dungeon_data['rooms'] as &$room) {
      if (($room['room_id'] ?? '') === $room_id) {
        if (!isset($room['gameplay_state'])) {
          $room['gameplay_state'] = [];
        }
        $room['gameplay_state']['encounter_triggered'] = TRUE;
        break;
      }
    }
    unset($room);
  }

  /**
   * Builds context object for NPC AI decision-making.
   */
  protected function buildNpcContext(string $entity_id, array $game_state, array $dungeon_data): array {
    return $this->actorContextBuilder->buildActorContext(
      $entity_id,
      $game_state,
      $dungeon_data,
      $this->buildNpcTacticalIntentContract(
        $entity_id,
        $game_state,
        (int) ($game_state['campaign_id'] ?? 0)
      )
    );
  }

  /**
   * Build canonical actor-scoped turn action availability envelope.
   */
  protected function buildActorTurnActionAvailabilityEnvelope(
    string $entity_id,
    array $game_state,
    array $available_actions,
    array $action_contract
  ): array {
    $turn_entity = (string) ($game_state['turn']['entity'] ?? '');
    $effective_actions_remaining = $turn_entity !== '' && $turn_entity === $entity_id
      ? max(0, (int) ($game_state['turn']['actions_remaining'] ?? 0))
      : 0;

    return [
      'actor_instance_id' => $entity_id,
      'is_active_turn_actor' => $turn_entity !== '' && $turn_entity === $entity_id,
      'actions_remaining' => $effective_actions_remaining,
      'reaction_available' => $turn_entity !== '' && $turn_entity === $entity_id && !empty($game_state['turn']['reaction_available']),
      'available_actions' => array_values(array_unique(array_filter(array_map(
        static fn($action): string => strtolower(trim((string) $action)),
        $available_actions
      )))),
      'action_contract' => $action_contract,
    ];
  }

  /**
   * Build structured profile fields to steer NPC action recommendation.
   */
  protected function buildNpcDecisionProfile(string $entity_id, array $game_state): array {
    return $this->actorContextBuilder->buildActorDecisionProfile($entity_id, $game_state);
  }

  /**
   * Build psychology context string for an NPC in combat.
   *
   * Provides personality-driven combat behavior hints to the AI:
   * - Cowardly NPCs may flee when badly hurt
   * - Disciplined NPCs focus fire and protect allies
   * - Cunning NPCs target weak PCs
   * - NPC attitude affects willingness to parley / surrender
   *
   * @param string $entity_id
   *   Entity ID.
   * @param array $game_state
   *   Current game state.
   *
   * @return string
   *   Formatted psychology context or empty string.
   */
  protected function buildNpcPsychologyContext(string $entity_id, array $game_state): string {
    return $this->actorContextBuilder->buildActorPsychologyContext($entity_id, $game_state);
  }

  /**
   * Resolve combatant entity_ref from initiative context.
   */
  protected function resolveCombatantEntityRef(string $entity_id, array $game_state): string {
    return $this->actorContextBuilder->resolveActorEntityRef($entity_id, $game_state);
  }

  /**
   * Load psychology profile for a combatant using entity_ref first, then entity_id.
   */
  protected function loadCombatantPsychologyProfile(string $entity_id, array $game_state, int $campaign_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }
    return $this->actorContextBuilder->loadActorProfile($entity_id, $game_state);
  }

  /**
   * Resolve actor goals list, always including XP and treasure goals.
   */
  protected function resolveActorGoals(?array $profile): array {
    return $this->actorContextBuilder->resolveGoalsFromProfile($profile);
  }

  /**
   * Case-insensitive goal matching helper.
   */
  protected function actorHasGoal(array $goals, string $needle): bool {
    $needle = strtolower(trim($needle));
    if ($needle === '') {
      return FALSE;
    }
    foreach ($goals as $goal) {
      if (!is_string($goal)) {
        continue;
      }
      if (str_contains(strtolower($goal), $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Calculate HP ratio for tactical context.
   */
  protected function hpRatio(array $combatant): float {
    $max = (int) ($combatant['max_hp'] ?? 0);
    if ($max <= 0) {
      return 1.0;
    }
    $current = (int) ($combatant['hp'] ?? 0);
    return round($current / $max, 2);
  }

  /**
   * Finds the first alive player entity for NPC fallback targeting.
   */
  protected function findFirstAlivePlayer(array $game_state): ?string {
    $initiative_order = $game_state['initiative_order'] ?? [];

    foreach ($initiative_order as $combatant) {
      if (($combatant['team'] ?? '') === 'player' && empty($combatant['is_defeated'])) {
        return $combatant['entity_id'] ?? NULL;
      }
    }

    return NULL;
  }

  // =========================================================================
  // Encounter chat prefixing.
  // =========================================================================

  protected function captureEncounterTurnContext(array $game_state, array $dungeon_data, ?string $actor_id, array $overrides = []): array {
    $round = isset($game_state['round']) && is_numeric($game_state['round']) ? (int) $game_state['round'] : NULL;
    $turn_index_raw = isset($game_state['turn']['index']) && is_numeric($game_state['turn']['index']) ? (int) $game_state['turn']['index'] : NULL;
    $turn_index_human = $turn_index_raw !== NULL ? ($turn_index_raw + 1) : '?';

    $effective_actor_id = is_string($actor_id) && trim($actor_id) !== '' ? trim($actor_id) : NULL;
    if ($effective_actor_id !== NULL && is_array($game_state['initiative_order'] ?? NULL)) {
      $initiative_index = $this->findInitiativeActorIndex($game_state['initiative_order'], $effective_actor_id);
      if ($initiative_index !== NULL) {
        $turn_index_raw = $initiative_index;
        $turn_index_human = $initiative_index + 1;
      }
    }
    $actor_name = $effective_actor_id !== NULL
      ? $this->resolveEntityName($effective_actor_id, $game_state, $dungeon_data)
      : 'Unknown';

    $room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $turn_entity_id = trim((string) ($game_state['turn']['entity'] ?? ''));
    $actions_total = is_numeric($game_state['turn']['actions_total'] ?? NULL) ? max(0, (int) $game_state['turn']['actions_total']) : 3;
    $active_actions_remaining = is_numeric($game_state['turn']['actions_remaining'] ?? NULL)
      ? max(0, (int) $game_state['turn']['actions_remaining'])
      : $actions_total;
    $actions_remaining = ($effective_actor_id !== NULL && $turn_entity_id !== '' && $effective_actor_id === $turn_entity_id)
      ? $active_actions_remaining
      : $actions_total;

    $ctx = [
      'round' => $round,
      'turn_index_raw' => $turn_index_raw,
      'turn_index_human' => $turn_index_human,
      'actor_id' => $effective_actor_id,
      'actor_name' => $actor_name !== '' ? $actor_name : 'Unknown',
      'room_id' => is_string($room_id) ? $room_id : NULL,
      'actions_remaining' => $actions_remaining,
      'actions_total' => $actions_total,
    ];

    foreach ($overrides as $k => $v) {
      $ctx[$k] = $v;
    }

    if (!is_string($ctx['actor_name']) || trim((string) $ctx['actor_name']) === '') {
      $ctx['actor_name'] = 'Unknown';
    }
    $ctx['actions_total'] = is_numeric($ctx['actions_total'] ?? NULL) ? max(0, (int) $ctx['actions_total']) : 3;
    $ctx['actions_remaining'] = is_numeric($ctx['actions_remaining'] ?? NULL)
      ? max(0, (int) $ctx['actions_remaining'])
      : $ctx['actions_total'];

    return $ctx;
  }

  protected function buildEncounterChatPrefix(array $turn_ctx): string {
    $turn = array_key_exists('turn_index_human', $turn_ctx) ? $turn_ctx['turn_index_human'] : 1;
    $round = $turn_ctx['round'] ?? 1;

    $turn_display = $turn === NULL || $turn === ''
      ? NULL
      : (is_numeric($turn) ? (int) $turn : '?');
    $round_display = \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::displayRound($round);

    $actor_name = $turn_ctx['actor_name'] ?? 'Unknown';
    if (!is_string($actor_name) || trim($actor_name) === '') {
      $actor_name = 'Unknown';
    }

    $actions_remaining = $turn_ctx['actions_remaining'] ?? NULL;
    $actions_total = $turn_ctx['actions_total'] ?? NULL;

    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::formatPrefix(
      $round_display,
      $turn_display,
      $actor_name,
      $actions_remaining,
      $actions_total
    );
  }

  protected function isEncounterChatLinePrefixed(string $content): bool {
    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::isPrefixed($content);
  }

  protected function prefixEncounterChatLine(array $turn_ctx, string $content): string {
    $content = trim($content);
    if ($content === '' || $this->isEncounterChatLinePrefixed($content)) {
      return $content;
    }
    return $this->buildEncounterChatPrefix($turn_ctx) . $content;
  }

  // =========================================================================
  // NarrationEngine bridge.
  // =========================================================================

  /**
   * Queue a game event through the NarrationEngine for perception filtering.
   *
   * Silently skips if NarrationEngine is not available.
   *
   * @param int $campaign_id
   * @param array $dungeon_data
   * @param array $event
   *   Event array matching NarrationEngine::queueRoomEvent() format.
   * @param string|null $room_id
   *   Override room ID. NULL uses active_room_id.
   *
   * @return array
   *   NarrationEngine result, or empty array if engine unavailable.
   */
  protected function queueNarrationEvent(int $campaign_id, array $dungeon_data, array $event, ?string $room_id = NULL, ?array $game_state_override = NULL): array {
    if (!$this->narrationEngine) {
      return [];
    }

    // Server-authoritative transcript: stamp Turn/Round/Actor prefix.
    $game_state = is_array($game_state_override ?? NULL)
      ? $game_state_override
      : (is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : []);
    if (!empty($game_state['suppress_room_scene_narration'])) {
      return [];
    }
    if (isset($event['content']) && is_string($event['content'])) {
      $prefix_actor_id = NULL;
      if (isset($event['speaker_ref']) && is_string($event['speaker_ref']) && trim($event['speaker_ref']) !== '') {
        $prefix_actor_id = trim($event['speaker_ref']);
      }
      elseif (isset($game_state['turn']['entity']) && is_string($game_state['turn']['entity']) && trim($game_state['turn']['entity']) !== '') {
        $prefix_actor_id = trim($game_state['turn']['entity']);
      }

      $overrides = [];
      if (($event['type'] ?? '') === 'round_start') {
        $overrides['actor_name'] = 'Narrator';
        $overrides['turn_index_raw'] = NULL;
        $overrides['turn_index_human'] = NULL;
      }

      $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $prefix_actor_id, $overrides);
      $event['content'] = $this->prefixEncounterChatLine($turn_ctx, $event['content']);
    }

    $dungeon_id = $dungeon_data['dungeon_id'] ?? $dungeon_data['id'] ?? 0;
    $room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? '');
    $present_characters = NarrationEngine::buildPresentCharacters($dungeon_data, $room_id);

    try {
      return $this->narrationEngine->queueRoomEvent(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $event,
        $present_characters
      );
    }
    catch (\Throwable $e) {
      $this->logger->warning('NarrationEngine queue failed: @err', ['@err' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Strip secret Search mechanics from client-facing action responses.
   */
  protected function buildPublicSearchResult(array $result): array {
    $public = ['searched' => TRUE];
    $discoveries = $this->buildPublicSearchDiscoveries($result['discoveries'] ?? []);
    if ($discoveries !== []) {
      $public['discoveries'] = $discoveries;
    }
    return $public;
  }

  /**
   * Build discovery payloads without roll/DC/degree/sense metadata.
   */
  protected function buildPublicSearchDiscoveries(array $discoveries): array {
    return array_values(array_map(
      static fn(array $discovery): array => array_filter([
        'instance_id' => $discovery['instance_id'] ?? NULL,
        'id' => $discovery['id'] ?? NULL,
        'name' => $discovery['name'] ?? NULL,
        'quest_id' => $discovery['quest_id'] ?? NULL,
        'objective_id' => $discovery['objective_id'] ?? NULL,
      ], static fn($value): bool => $value !== NULL && $value !== ''),
      array_filter($discoveries, 'is_array')
    ));
  }

  /**
   * Mirror rolled encounter checks into the Dice Log.
   */
  protected function maybeQueueMechanicalSystemLogEntry(array $context): void {
    $campaign_id = (int) ($context['campaign_id'] ?? 0);
    $dungeon_data = is_array($context['dungeon_data'] ?? NULL) ? $context['dungeon_data'] : [];
    $type = (string) ($context['type'] ?? '');
    $actor_id = isset($context['actor_id']) ? (string) $context['actor_id'] : NULL;
    $target_id = isset($context['target_id']) ? (string) $context['target_id'] : NULL;
    $params = is_array($context['params'] ?? NULL) ? $context['params'] : [];
    $result = is_array($context['result'] ?? NULL) ? $context['result'] : [];
    $game_state = is_array($context['game_state'] ?? NULL) ? $context['game_state'] : [];

    if (
      !$this->narrationEngine
      || !$actor_id
      // Strike and seek already emit dedicated narration/system-log events.
      || in_array($type, ['strike', 'seek'], TRUE)
    ) {
      return;
    }

    $logged_action = $type;
    $check_mode = 'explicit';
    if ($type === 'search') {
      $requested_mode = strtolower(trim((string) ($params['search_mode'] ?? 'explicit')));
      $check_mode = $requested_mode !== '' ? $requested_mode : 'explicit';
    }

    $dc = isset($result['dc']) && is_numeric($result['dc']) ? (int) $result['dc'] : NULL;
    $total = NULL;
    if (isset($result['total']) && is_numeric($result['total'])) {
      $total = (int) $result['total'];
    }
    elseif (isset($result['roll']) && is_numeric($result['roll'])) {
      $total = (int) $result['roll'];
    }

    if ($dc === NULL || $total === NULL) {
      return;
    }

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $degree = isset($result['degree']) && is_string($result['degree']) ? $result['degree'] : 'resolved';
    $skill_name = $this->resolveMechanicalSkillName($logged_action, $params, $result);
    if ($skill_name === NULL && $logged_action === 'search') {
      $skill_name = 'perception';
    }

    if ($logged_action === 'search') {
      $detail_label = $check_mode === 'automatic'
        ? 'Automatic Search via Perception'
        : 'Search via Perception';
    }
    else {
      $action_label = ucwords(str_replace('_', ' ', $logged_action));
      $detail_label = $skill_name
        ? sprintf('%s via %s', $action_label, ucwords(str_replace('_', ' ', $skill_name)))
        : $action_label;
    }

    $metadata = [
      'action' => $logged_action,
      'skill' => $skill_name,
      'check_mode' => $check_mode,
      'roll' => isset($result['d20']) && is_numeric($result['d20']) ? (int) $result['d20'] : (isset($result['roll']) && is_numeric($result['roll']) ? (int) $result['roll'] : NULL),
      'total' => $total,
      'dc' => $dc,
      'degree' => $degree,
      'target' => $target_id,
    ];
    $metadata = array_filter($metadata, static fn($value) => $value !== NULL && $value !== '');

    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'skill_check_result',
      'speaker' => 'System',
      'speaker_type' => 'system',
      'speaker_ref' => $actor_id,
      'content' => sprintf('%s resolves %s (%d vs DC %d: %s).', $actor_name, $detail_label, $total, $dc, $degree),
      'mechanical_data' => $metadata,
      'visibility' => 'public',
    ], NULL, $game_state);
  }

  /**
   * Determine the most specific skill label available for a rolled encounter action.
   */
  protected function resolveMechanicalSkillName(string $type, array $params, array $result): ?string {
    foreach (['skill_used', 'skill', 'skill_name'] as $key) {
      $value = $result[$key] ?? $params[$key] ?? NULL;
      if (is_string($value) && $value !== '') {
        return strtolower($value);
      }
    }

    return match ($type) {
      'search' => 'perception',
      'hide', 'create_a_diversion', 'lie', 'feint' => 'deception',
      'recall_knowledge' => 'recall_knowledge',
      'trip', 'grapple', 'shove', 'reposition', 'disarm' => 'athletics',
      'treat_poison', 'battle_medicine', 'administer_first_aid' => 'medicine',
      'disable_hazard', 'pick_a_lock' => 'thievery',
      'perform' => 'performance',
      default => NULL,
    };
  }

  /**
   * Resolve a display name for an entity from initiative order or dungeon data.
   */
  protected function resolveEntityName(?string $entity_id, array $game_state, array $dungeon_data = []): string {
    if (!$entity_id) {
      return 'Unknown';
    }

    // Check initiative order first (encounter context).
    foreach ($game_state['initiative_order'] ?? [] as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $combatant['name'] ?? $combatant['display_name'] ?? $entity_id;
      }
    }

    // Check dungeon_data entities.
    foreach ($dungeon_data['entities'] ?? [] as $ent) {
      $ent_id = $ent['entity_instance_id'] ?? ($ent['entity_ref']['content_id'] ?? '');
      if ($ent_id === $entity_id) {
        return $ent['state']['metadata']['display_name'] ?? $ent['name'] ?? $entity_id;
      }
    }

    return $entity_id;
  }

  /**
   * Processes a Grapple action (REQ 1626–1631).
   * 1 action, Attack trait; size limit = target no more than 1 size larger.
   */
  protected function processGrapple(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state): array {
    $enc = $this->encounterStore->loadEncounter($encounter_id);
    if (!$enc) {
      return ['error' => 'Encounter not found.', 'mutations' => []];
    }
    $actor_ptcp = $this->findEncounterParticipantByEntityId($enc, $actor_id);
    if (!$actor_ptcp) {
      return ['error' => 'Actor not found.', 'mutations' => []];
    }

    // REQ 1626: Requires one free hand (or already grappling target).
    $has_free_hand = !empty($params['has_free_hand']);
    $already_grappling = !empty($params['already_grappling']);
    if (!$has_free_hand && !$already_grappling) {
      return ['error' => 'Grapple requires a free hand.', 'mutations' => []];
    }

    // REQ 1626: Size limit — target no more than one size larger.
    $size_order = ['tiny' => 0, 'small' => 1, 'medium' => 2, 'large' => 3, 'huge' => 4, 'gargantuan' => 5];
    $actor_size = strtolower($params['actor_size'] ?? 'medium');
    $target_size = strtolower($params['target_size'] ?? 'medium');
    $actor_rank = $size_order[$actor_size] ?? 2;
    $target_rank = $size_order[$target_size] ?? 2;
    if ($target_rank > $actor_rank + 1) {
      return ['error' => 'Target is too large to Grapple.', 'size_blocked' => TRUE, 'mutations' => []];
    }

    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $athletics_bonus = (int) ($params['athletics_bonus'] ?? 0);
    $fortitude_dc = (int) ($params['fortitude_dc'] ?? 15);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics_bonus + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $fortitude_dc, $d20);

    $target_ptcp = $target_id ? $this->findEncounterParticipantByEntityId($enc, $target_id) : NULL;

    $condition_applied = NULL;
    $grappler_grabbed = FALSE;
    $grappler_prone = FALSE;

    if ($degree === 'critical_success') {
      // Restrained.
      if ($target_ptcp) {
        $this->conditionManager->applyCondition((int) $target_ptcp['id'], 'restrained', 0, ['type' => 'encounter', 'remaining' => 1], 'grapple', $encounter_id);
      }
      $condition_applied = 'restrained';
    }
    elseif ($degree === 'success') {
      // Grabbed.
      if ($target_ptcp) {
        $this->conditionManager->applyCondition((int) $target_ptcp['id'], 'grabbed', 0, ['type' => 'encounter', 'remaining' => 1], 'grapple', $encounter_id);
      }
      $condition_applied = 'grabbed';
    }
    elseif ($degree === 'failure') {
      // Release existing grapple.
      if ($target_ptcp) {
        $active = $this->conditionManager->getActiveConditions((int) $target_ptcp['id'], $encounter_id);
        foreach ($active as $row_id => $row) {
          if (in_array($row['condition_type'], ['grabbed', 'restrained'], TRUE) && ($row['source'] ?? '') === 'grapple') {
            $this->conditionManager->removeCondition((int) $target_ptcp['id'], $row_id, $encounter_id);
            break;
          }
        }
      }
    }
    elseif ($degree === 'critical_failure') {
      // Target may grab grappler or knock prone; default: grappler grabbed.
      if (!empty($params['target_grabs_back'])) {
        $grappler_grabbed = TRUE;
        $this->conditionManager->applyCondition((int) $actor_ptcp['id'], 'grabbed', 0, ['type' => 'encounter', 'remaining' => 1], 'grapple_retaliation', $encounter_id);
      }
      else {
        $grappler_prone = TRUE;
        $this->conditionManager->applyCondition((int) $actor_ptcp['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'grapple_retaliation', $encounter_id);
      }
    }

    return [
      'grappled' => in_array($degree, ['critical_success', 'success'], TRUE),
      'condition_applied' => $condition_applied,
      'grappler_grabbed' => $grappler_grabbed,
      'grappler_prone' => $grappler_prone,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Processes the Administer First Aid skill action result.
   *
   * REQ 1688–1693: Two modes — stabilize (removes dying) and stop_bleeding.
   * DC is 15 + dying value for stabilize; bleeding DC for stop bleeding.
   * Called AFTER the d20 roll and total are computed by the caller.
   *
   * @param array|null $target_ptcp   Encounter participant row for the target.
   * @param array|null $actor_ptcp    Encounter participant row for the actor.
   * @param string $mode              'stabilize' or 'stop_bleeding'.
   * @param int $total                Final roll total (d20 + medicine + penalties).
   * @param int $d20                  Raw d20 result (for crit detection).
   * @param array $params             Intent params (bleeding_dc, etc.).
   * @param int $encounter_id         Current encounter ID.
   *
   * @return array
   *   Keys: degree, stabilized, bleeding_stopped, error (optional).
   */
  protected function processAdministerFirstAid(?array $target_ptcp, ?array $actor_ptcp, string $mode, int $total, int $d20, array $params, int $encounter_id): array {
    if (!$target_ptcp) {
      return ['error' => 'Target participant not found.', 'degree' => NULL];
    }
    $target_pid = (int) $target_ptcp['id'];

    if ($mode === 'stabilize') {
      // REQ 1690: Target must be dying.
      $dying_value = $this->conditionManager->getConditionValue($target_pid, 'dying', $encounter_id);
      if ($dying_value === NULL || $dying_value <= 0) {
        return ['error' => 'Target is not dying; cannot stabilize.', 'degree' => NULL, 'stabilized' => FALSE];
      }

      // DC = 15 + dying value.
      $dc = 15 + $dying_value;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

      if ($degree === 'critical_success' || $degree === 'success') {
        // REQ 1688/1689: Remove dying condition; wounded +1 applied inside stabilizeCharacter.
        $this->hpManager->stabilizeCharacter($target_pid, $encounter_id);
        return ['degree' => $degree, 'stabilized' => TRUE, 'dc' => $dc, 'bleeding_stopped' => FALSE];
      }
      elseif ($degree === 'failure') {
        // Failure has no dying-state mutation.
        return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $dc, 'bleeding_stopped' => FALSE];
      }
      else {
        // Critical failure increases dying by 1.
        $current_dying = $this->conditionManager->getConditionValue($target_pid, 'dying', $encounter_id) ?? $dying_value;
        $doomed = $this->conditionManager->getConditionValue($target_pid, 'doomed', $encounter_id) ?? 0;
        $new_dying = max(0, (int) $current_dying + 1);
        $death_threshold = max(1, 4 - (int) $doomed);

        if ($new_dying >= $death_threshold) {
          $this->database->update('combat_participants')
            ->fields([
              'status' => 'dead',
              'is_defeated' => 1,
              'updated' => time(),
            ])
            ->condition('id', $target_pid)
            ->condition('encounter_id', $encounter_id)
            ->execute();
          $active_conds = $this->conditionManager->getActiveConditions($target_pid, $encounter_id);
          foreach ($active_conds as $cond) {
            if (($cond['condition_type'] ?? '') === 'dying' || ($cond['condition_type'] ?? '') === 'unconscious') {
              $this->conditionManager->removeCondition($target_pid, (int) $cond['id'], $encounter_id);
            }
          }
          return [
            'degree' => $degree,
            'stabilized' => FALSE,
            'dc' => $dc,
            'bleeding_stopped' => FALSE,
            'dead' => TRUE,
          ];
        }

        $this->conditionManager->setConditionValueExact($target_pid, 'dying', $new_dying, ['type' => 'encounter', 'remaining' => NULL], 'first_aid_crit_fail', $encounter_id);
        return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $dc, 'bleeding_stopped' => FALSE];
      }
    }

    // stop_bleeding mode.
    $bleeding_dc = (int) ($params['bleeding_dc'] ?? 15);
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $bleeding_dc, $d20);

    if ($degree === 'critical_success' || $degree === 'success') {
      // REQ 1693: Remove persistent bleeding condition.
      $active_conds = $this->conditionManager->getActiveConditions($target_pid, $encounter_id);
      foreach ($active_conds as $cond) {
        if ($cond['condition_type'] === 'persistent_bleed' || $cond['condition_type'] === 'bleeding') {
          $this->conditionManager->removeCondition($target_pid, (int) $cond['id'], $encounter_id);
        }
      }
      return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $bleeding_dc, 'bleeding_stopped' => TRUE];
    }
    elseif ($degree === 'critical_failure') {
      // REQ 1693: Crit fail triggers immediate bleed damage (1d4 default).
      $bleed_damage = $this->numberGenerationService->rollPathfinderDie(4);
      $this->hpManager->applyDamage($target_pid, $bleed_damage, 'bleed', ['source' => 'first_aid_crit_fail'], $encounter_id);
      return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $bleeding_dc, 'bleeding_stopped' => FALSE, 'bleed_damage' => $bleed_damage];
    }

    return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $bleeding_dc, 'bleeding_stopped' => FALSE];
  }

  /**
   * Calculates falling damage.
   * REQ 1641: Half of distance in feet as bludgeoning damage.
   * REQ 1642: Soft surfaces reduce effective distance by up to 20 ft.
   *
   * @param int $feet_fallen
   *   Total distance fallen in feet.
   * @param bool|int $soft_surface
   *   Whether landing on a soft surface; if int, treated as max reduction depth (default 20).
   *
   * @return int
   *   Bludgeoning damage.
   */
  protected function calculateFallingDamage(int $feet_fallen, bool|int $soft_surface = FALSE): int {
    if ($soft_surface !== FALSE) {
      $reduction = is_int($soft_surface) ? min($soft_surface, 20) : 20;
      $feet_fallen = max(0, $feet_fallen - $reduction);
    }
    return (int) floor($feet_fallen / 2);
  }

}
