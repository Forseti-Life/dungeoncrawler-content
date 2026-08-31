<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Core route execution methods part A.
 */
trait EncounterPhaseHandlerRouteExecutionCorePartATrait {
  protected function routeEndTurnIntentExecution(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if ($type === 'choose_not_to_act') {
      $params['reason'] = trim((string) ($params['reason'] ?? 'chooses not to act'));
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      $type,
      (string) $actor_id,
      NULL,
      $params
    );

    $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
    $result = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
    $mutations = $result['mutations'] ?? [];
    $narration = $result['narration'] ?? NULL;
    $time_effects = $this->buildRoundElapsedTimeEffects($result, $actor_id, $dungeon_data);

    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = (string) ($turn_ctx['actor_name'] ?? ($actor_id ? $this->resolveEntityName($actor_id, $game_state, $dungeon_data) : 'Narrator'));
    $fallback_narration = $type === 'choose_not_to_act'
      ? sprintf('%s chooses not to act.', $actor_name)
      : sprintf('%s ends their turn.', $actor_name);
    $resolved_narration = (is_string($narration) && trim($narration) !== '') ? $narration : $fallback_narration;
    $resolved_narration = $this->prefixEncounterChatLine($turn_ctx, $resolved_narration);
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'actions_remaining_before_end' => $result['actions_remaining_before_end'] ?? NULL,
        'actor_alive' => $result['actor_alive'] ?? NULL,
        'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
      ]
    );

    $events = [
      GameEventLogger::buildEvent($type, 'encounter', $actor_id, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
        'room_id' => $resolved_room_id,
        'actor_name' => $actor_name,
        'turn_index' => $turn_ctx['turn_index_raw'] ?? NULL,
        'actions_remaining' => $result['actions_remaining_before_end'] ?? NULL,
        'reason' => $params['reason'] ?? NULL,
      ], $resolved_narration),
    ];

    if ($actor_id && ($result['actions_remaining_before_end'] ?? 0) > 0) {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'choose_not_to_act',
        'speaker' => 'Narrator',
        'speaker_type' => 'narrator',
        'speaker_ref' => '',
        'content' => $this->prefixEncounterChatLine($turn_ctx, sprintf('%s chooses not to use %d remaining action(s).', $actor_name, (int) $result['actions_remaining_before_end'])),
        'visibility' => 'public',
        'mechanical_data' => [
          'actor_id' => $actor_id,
          'actor_name' => $actor_name,
          'room_id' => $resolved_room_id,
          'actions_remaining' => (int) $result['actions_remaining_before_end'],
          'reason' => $params['reason'] ?? NULL,
        ],
      ], $resolved_room_id);
    }

    if (!empty($result['npc_events'])) {
      $events = array_merge($events, $result['npc_events']);
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
      'time_effects' => $time_effects,
      // Bubble up any phase_transition produced by processEndTurn()'s own
      // isEncounterOver() conclusion check (e.g. an explicit end_turn that
      // exhausted the last combatant's team). Without this, the top-level
      // 'phase_transition' key that GameCoordinatorService::processAction()
      // looks for was silently dropped in transit — it only lived nested
      // inside 'result', which is never read for that purpose.
      'phase_transition' => $result['phase_transition'] ?? NULL,
    ];
  }

  /**
   * Core logic for the player-triggered "Recover Party" action.
   *
   * This heals all player- and ally-team combatants to full HP and clears
   * their defeated/dying/unconscious/prone conditions. It is intentionally
   * NOT automatic — it must be explicitly invoked by the player via the
   * `party_recovery` intent (see routePartyRecoveryIntentExecution()) so the
   * player chooses when to spend the action rather than it firing silently
   * on every turn boundary.
   *
   * @return array
   *   Narration events describing the recovery, or [] if nobody needed it.
   */
  protected function restorePlayerPartyToFullHealth(?int $encounter_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$encounter_id || !$this->hpManager || !$this->conditionManager || !$this->encounterStore) {
      return [];
    }

    $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return [];
    }

    $healed_names = [];
    foreach (($game_state['initiative_order'] ?? []) as &$participant) {
      if (!is_array($participant)) {
        continue;
      }
      $team = $this->normalizeCombatTeam((string) ($participant['team'] ?? ''));
      if (!in_array($team, ['player', 'ally'], TRUE)) {
        continue;
      }

      $entity_id = trim((string) ($participant['entity_id'] ?? ''));
      if ($entity_id === '') {
        continue;
      }

      $row = $this->findEncounterParticipantByEntityId($encounter, $entity_id);
      $participant_id = (int) ($row['id'] ?? 0);
      $max_hp = (int) ($row['max_hp'] ?? $participant['max_hp'] ?? 0);
      if ($participant_id <= 0 || $max_hp <= 0) {
        continue;
      }

      $current_hp = (int) ($row['hp'] ?? $participant['hp'] ?? 0);
      $was_defeated = !empty($row['is_defeated']) || !empty($participant['is_defeated']);
      if ($current_hp >= $max_hp && !$was_defeated) {
        // Already at full health and standing — nothing to recover.
        continue;
      }

      // applyHealing() refuses to touch a participant already marked
      // 'dead'; this stopgap explicitly overrides that so a wipe is always
      // recoverable until real recovery handling exists.
      if ((string) ($row['status'] ?? 'active') === 'dead') {
        $this->encounterStore->updateParticipant($participant_id, ['status' => 'active']);
      }

      $this->hpManager->applyHealing($participant_id, $max_hp, 'party_recovery_end_turn_stopgap', (int) $encounter_id);
      foreach ($this->conditionManager->getActiveConditions($participant_id, (int) $encounter_id) as $condition_id => $condition_row) {
        $this->conditionManager->removeCondition($participant_id, (int) $condition_id, (int) $encounter_id);
      }
      // is_defeated may still be set if applyHealing's own internal reload
      // predates our status override above; make sure it's cleared explicitly.
      $this->encounterStore->updateParticipant($participant_id, ['is_defeated' => 0]);

      $participant['hp'] = $max_hp;
      $participant['is_defeated'] = FALSE;
      $participant['status'] = 'active';
      $healed_names[] = (string) ($participant['name'] ?? $entity_id);
    }
    unset($participant);

    if ($healed_names === []) {
      return [];
    }

    $narration = sprintf('%s recover to full health.', implode(' and ', $healed_names));
    $room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'party_recovery',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => '',
      'content' => $narration,
      'visibility' => 'public',
    ], $room_id);

    return [
      GameEventLogger::buildEvent('party_recovery', 'encounter', NULL, [
        'healed' => $healed_names,
        'reason' => 'player_triggered_party_recovery_action',
      ], $narration),
    ];
  }

  /**
   * Router seam: execute the player-triggered "Recover Party" action.
   *
   * Zero-cost, does not end the actor's turn (mirrors 'delay' in that
   * respect) — it simply heals the player party to full HP on demand.
   */
  protected function routePartyRecoveryIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
    $recovery_events = $this->restorePlayerPartyToFullHealth($encounter_id, $game_state, $dungeon_data, $campaign_id);

    $actor_name = (string) ($turn_ctx['actor_name'] ?? ($actor_id ? $this->resolveEntityName($actor_id, $game_state, $dungeon_data) : 'Narrator'));
    $resolved_narration = $recovery_events !== []
      ? (string) ($recovery_events[0]['narration'] ?? sprintf('%s calls for the party to recover.', $actor_name))
      : sprintf('%s calls for the party to recover, but nobody needs healing.', $actor_name);
    $resolved_narration = $this->prefixEncounterChatLine($turn_ctx, $resolved_narration);

    $events = [
      GameEventLogger::buildEvent('party_recovery_action', 'encounter', $actor_id, [
        'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
        'actor_name' => $actor_name,
        'healed' => $recovery_events !== [] ? ($recovery_events[0]['data']['healed'] ?? []) : [],
      ], $resolved_narration),
    ];
    if ($recovery_events !== []) {
      $events = array_merge($events, $recovery_events);
    }

    return [
      'result' => [
        'action' => 'party_recovery',
        'healed' => $recovery_events !== [] ? ($recovery_events[0]['data']['healed'] ?? []) : [],
      ],
      'mutations' => [],
      'events' => $events,
      'narration' => $resolved_narration,
      'time_effects' => [],
    ];
  }

  /**
   * Router seam: execute delay intent block with legacy side effects.
   */
  protected function routeDelayIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'delay',
      (string) $actor_id,
      NULL,
      $params
    );
    $delay_remaining = $game_state['turn']['actions_remaining'] ?? 0;
    $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
    $result = [
      'delayed' => TRUE,
      'remaining_actions' => $delay_remaining,
    ];

    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = (string) ($turn_ctx['actor_name'] ?? ($actor_id ? $this->resolveEntityName($actor_id, $game_state, $dungeon_data) : 'Narrator'));
    $delay_after_actor_id = trim((string) ($params['delay_until_actor_id'] ?? ''));
    $delay_narration = $delay_after_actor_id !== ''
      ? sprintf('%s delays their turn until after %s acts.', $actor_name, $this->resolveEntityName($delay_after_actor_id, $game_state, $dungeon_data))
      : sprintf('%s steps aside, delaying their turn until immediately after the next combatant acts.', $actor_name);
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'delayed' => TRUE,
        'remaining_actions' => $delay_remaining,
        'delay_until_actor_id' => $params['delay_until_actor_id'] ?? NULL,
      ]
    );
    $events = [
      GameEventLogger::buildEvent('delay', 'encounter', $actor_id, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'remaining_actions' => $delay_remaining,
        'round' => $game_state['round'] ?? NULL,
      ], $this->prefixEncounterChatLine($turn_ctx, $delay_narration)),
    ];

    if ($actor_id && is_array($game_state['initiative_order'] ?? NULL) && $game_state['initiative_order'] !== []) {
      $delay_plan = $this->buildDelayedInitiativePlan(
        $game_state['initiative_order'],
        $actor_id,
        (int) ($game_state['turn']['index'] ?? 0),
        (int) $delay_remaining,
        $delay_after_actor_id !== '' ? $delay_after_actor_id : NULL
      );
      $game_state['initiative_order'] = $delay_plan['initiative_order'];
      $game_state['turn']['index'] = $delay_plan['pre_advance_index'];
      $game_state['turn']['delayed'] = TRUE;
      $game_state['turn']['delayed_actions_remaining'] = (int) $delay_remaining;
    }

    $game_state['turn']['actions_remaining'] = 0;
    $advance = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
    $time_effects = $this->buildRoundElapsedTimeEffects($advance, $actor_id, $dungeon_data);

    if ($actor_id && $delay_remaining > 0) {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'delay',
        'speaker' => 'Narrator',
        'speaker_type' => 'narrator',
        'speaker_ref' => '',
        'content' => $this->prefixEncounterChatLine($turn_ctx, sprintf('%s delays with %d action(s) still unused.', $actor_name, (int) $delay_remaining)),
        'visibility' => 'public',
        'mechanical_data' => [
          'actor_id' => $actor_id,
          'actor_name' => $actor_name,
          'room_id' => $resolved_room_id,
          'remaining_actions' => (int) $delay_remaining,
        ],
      ], $resolved_room_id);
    }
    if (!empty($advance['npc_events'])) {
      $events = array_merge($events, $advance['npc_events']);
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => is_array($advance['mutations'] ?? NULL) ? $advance['mutations'] : [],
      'events' => $events,
      'narration' => NULL,
      'time_effects' => $time_effects,
      // Delaying gives up remaining actions and immediately advances the
      // turn via processEndTurn() above -- bubble up any phase_transition
      // that advance produced (e.g. delaying happened to be the action
      // that finally exhausted the last combatant's team) the same way
      // routeEndTurnIntentExecution() does.
      'phase_transition' => $advance['phase_transition'] ?? NULL,
    ];
  }

  /**
   * Router seam: execute delay-reenter intent block with legacy side effects.
   */
  protected function routeDelayReenterIntentExecution(
    ?string $actor_id,
    array &$game_state
  ): array {
    if (empty($game_state['turn']['delayed'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Not currently delayed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $reenter_actions = $game_state['turn']['delayed_actions_remaining'] ?? 0;
    $game_state['turn']['delayed'] = FALSE;
    $game_state['turn']['actions_remaining'] = $reenter_actions;
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'delay_reenter',
      (string) $actor_id,
      NULL,
      []
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'reentered' => TRUE,
        'actions_restored' => $reenter_actions,
      ]
    );

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'reentered' => TRUE,
        'actions_restored' => $reenter_actions,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('delay_reenter', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'actions_restored' => $reenter_actions,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
      'narration' => NULL,
      'time_effects' => [],
    ];
  }

  /**
   * Router seam: execute ready intent block with legacy side effects.
   */
  protected function routeReadyIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $ready_action = $params['ready_action'] ?? NULL;
    $ready_trigger = $params['ready_trigger'] ?? NULL;
    if (!$ready_action || !$ready_trigger) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'ready_action and ready_trigger are required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (!empty($params['is_triggered_free_action'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot Ready a free action that already has a trigger.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $game_state['turn']['ready'] = [
      'action' => $ready_action,
      'trigger' => $ready_trigger,
      'map_at_ready' => $game_state['turn']['attacks_this_turn'] ?? 0,
    ];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'ready',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'readied' => TRUE,
        'action' => $ready_action,
        'trigger' => $ready_trigger,
      ]
    );

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'readied' => TRUE,
        'action' => $ready_action,
        'trigger' => $ready_trigger,
      ],
      'events' => [
        GameEventLogger::buildEvent('ready', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'action' => $ready_action,
          'trigger' => $ready_trigger,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute reaction intent block with legacy side effects.
   */
  protected function routeReactionIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $reaction_available = $game_state['turn']['reaction_available'] ?? TRUE;
    if (!$reaction_available) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent this round.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $game_state['turn']['reaction_available'] = FALSE;
    $ready_data = $game_state['turn']['ready'] ?? NULL;
    if ($ready_data && ($ready_data['action'] ?? '') === 'strike') {
      $game_state['turn']['attacks_this_turn'] = (int) ($ready_data['map_at_ready'] ?? 0);
    }
    $reaction_type = (string) ($params['reaction_type'] ?? 'generic');
    $reaction_packet = $this->unifiedReactionEngine->buildReactionResolutionPacket(
      (string) $actor_id,
      is_string($target_id) && trim($target_id) !== '' ? (string) $target_id : (string) $actor_id,
      $reaction_type,
      'resolved',
      ['source' => 'routeReactionIntentExecution']
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'reaction',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [$reaction_packet],
      ['reaction_used' => TRUE, 'reaction_type' => $reaction_type]
    );

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'reaction_packet' => $reaction_packet,
        'reaction_used' => TRUE,
        'reaction_type' => $reaction_type,
      ],
      'events' => [
        GameEventLogger::buildEvent('reaction', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'reaction_packet' => $reaction_packet,
          'reaction_type' => $reaction_type,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute aid-setup intent block with legacy side effects.
   */
  protected function routeAidSetupIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    if (!isset($game_state['turn']['aid_prepared'])) {
      $game_state['turn']['aid_prepared'] = [];
    }
    $aid_skill = $params['skill'] ?? 'generic';
    $game_state['turn']['aid_prepared'][$actor_id][$target_id] = $aid_skill;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && !empty($target_id)) {
      $actor_disposition->applyDispositionEvent(
        $campaign_id,
        (string) $actor_id,
        'aid_setup_prepared',
        sprintf('Encounter aid setup for %s using %s', (string) $target_id, (string) $aid_skill),
        [
          'target_entity_ref' => (string) $target_id,
          'relationship_type' => 'combat',
          'relationship_status' => 'known',
          'skill' => (string) $aid_skill,
          'idempotency_key' => sha1(json_encode([
            'encounter_aid_setup' => TRUE,
            'campaign_id' => $campaign_id,
            'source' => (string) $actor_id,
            'target' => (string) $target_id,
            'skill' => (string) $aid_skill,
            'round' => (int) ($game_state['round'] ?? 0),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        ]
      );
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'aid_setup',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['aid_prepared' => TRUE, 'target' => $target_id, 'skill' => $aid_skill]
    );

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'aid_prepared' => TRUE,
        'target' => $target_id,
        'skill' => $aid_skill,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('aid_setup', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'target' => $target_id,
          'skill' => $aid_skill,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute aid intent block with legacy side effects.
   */
  protected function routeAidIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $result = $this->processAid($actor_id, $target_id, $params, $game_state);
    $mutations = $result['mutations'] ?? [];
    $degree = strtolower(trim((string) ($result['degree'] ?? '')));
    $actor_disposition = $this->resolveActorDispositionService();
    if (
      $actor_disposition instanceof ActorDispositionService
      && $campaign_id > 0
      && !empty($actor_id)
      && !empty($target_id)
      && empty($result['error'])
    ) {
      $event_type = match ($degree) {
        'critical_success' => 'aid_critical_success',
        'success' => 'aid_success',
        'failure' => 'aid_failure',
        'critical_failure' => 'aid_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter aid for %s (%s)', (string) $target_id, (string) $degree),
          [
            'target_entity_ref' => (string) $target_id,
            'relationship_type' => 'combat',
            'relationship_status' => 'known',
            'degree' => $degree,
            'aid_bonus' => (int) ($result['aid_bonus'] ?? 0),
            'idempotency_key' => sha1(json_encode([
              'encounter_aid' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $target_id,
              'degree' => $degree,
              'aid_bonus' => (int) ($result['aid_bonus'] ?? 0),
              'd20' => (int) ($result['d20'] ?? 0),
              'total' => (int) ($result['total'] ?? 0),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'aid',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'degree' => $result['degree'] ?? NULL,
        'aid_bonus' => $result['aid_bonus'] ?? 0,
        'error' => $result['error'] ?? NULL,
      ]
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('aid', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'aid_bonus' => $result['aid_bonus'] ?? 0,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute hero-point-reroll intent block with legacy side effects.
   */
  protected function routeHeroPointRerollIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $original_roll = (int) ($params['original_roll'] ?? 0);
    $reroll = $this->calculator->heroPointReroll($original_roll);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if ($participant) {
      $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
      $hero_points = max(0, (int) ($entity_ref['hero_points'] ?? 0) - 1);
      $entity_ref['hero_points'] = $hero_points;
      $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_ref)]);
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'hero_point_reroll',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'original_roll' => $original_roll,
        'new_roll' => $reroll['new_roll'] ?? NULL,
        'hero_points_spent' => 1,
      ]
    );

    return [
      'result' => $reroll + [
        'hero_points_spent' => 1,
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ],
      'events' => [
        GameEventLogger::buildEvent('hero_point_reroll', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'original_roll' => $original_roll,
          'new_roll' => $reroll['new_roll'],
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute heroic-recovery-all-points intent block with legacy side effects.
   */
  protected function routeHeroicRecoveryAllPointsIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array &$game_state
  ): array {
    $participant_id = $actor_id;
    if (is_string($participant_id)) {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      $participant_id = $participant ? (int) $participant['id'] : NULL;
    }
    if (!$participant_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if ($participant) {
      $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
      $entity_ref['hero_points'] = 0;
      $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_ref)]);
    }

    $result = $this->hpManager->heroicRecoveryAllPoints($participant_id, $encounter_id);
    $state_effect_packets = [];
    if (!empty($result['dying_removed'])) {
      $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
        (string) $actor_id,
        (string) $actor_id,
        'condition',
        'dying',
        'removed',
        0,
        ['encounter_id' => $encounter_id, 'action' => 'heroic_recovery_all_points']
      );
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'heroic_recovery_all_points',
      (string) $actor_id,
      NULL,
      []
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      ['dying_removed' => $result['dying_removed'] ?? FALSE]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'state_effect_packets' => $state_effect_packets,
      ]),
      'events' => [
        GameEventLogger::buildEvent('heroic_recovery_all_points', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packets' => $state_effect_packets,
          'dying_removed' => $result['dying_removed'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute stand intent block with legacy side effects.
   */
  protected function routeStandIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $state_effect_packets = [];
    if ($participant) {
      $participant_id = (int) $participant['id'];
      foreach ($this->conditionManager->getActiveConditions($participant_id, $encounter_id) as $condition_id => $condition_row) {
        if ($condition_row['condition_type'] === 'prone') {
          $this->conditionManager->removeCondition($participant_id, $condition_id, $encounter_id);
          $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
            (string) $actor_id,
            (string) $actor_id,
            'condition',
            'prone',
            'removed',
            0,
            ['encounter_id' => $encounter_id, 'action' => 'stand']
          );
          break;
        }
      }
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'stand',
      (string) $actor_id,
      NULL,
      []
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      [
        'stood' => TRUE,
      ]
    );
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'stood' => TRUE,
        'state_effect_packets' => $state_effect_packets,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('stand', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packets' => $state_effect_packets,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute drop-prone intent block with legacy side effects.
   */
  protected function routeDropProneIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $state_effect_packets = [];
    if ($participant) {
      $participant_id = (int) $participant['id'];
      $this->conditionManager->applyCondition($participant_id, 'prone', 1, 'persistent', 'drop_prone', $encounter_id);
      $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
        (string) $actor_id,
        (string) $actor_id,
        'condition',
        'prone',
        'applied',
        1,
        ['encounter_id' => $encounter_id, 'action' => 'drop_prone']
      );
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'drop_prone',
      (string) $actor_id,
      NULL,
      []
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      [
        'prone' => TRUE,
      ]
    );
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'prone' => TRUE,
        'state_effect_packets' => $state_effect_packets,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('drop_prone', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packets' => $state_effect_packets,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute step intent block with legacy side effects.
   */
  protected function routeStepIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (empty($params['to_hex'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Missing to_hex.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if ($this->movementResolver && $this->movementResolver->isDifficultTerrain($params['to_hex'], $dungeon_data)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot Step into difficult terrain.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $move_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    if (!empty($move_result['error'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => (string) $move_result['error']],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $movement_execution_request = $this->requireOptionalContractPayload(
      $move_result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'step.movement.execution_request'
    );
    $movement_packet = $this->requireOptionalContractPayload(
      $move_result['movement_packet'] ?? NULL,
      'movement_resolution',
      CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION,
      'step.movement.movement_packet'
    );
    $movement_resolution_envelope = $this->requireOptionalContractPayload(
      $move_result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'step.movement.resolution_envelope'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'step',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      is_array($movement_packet) ? [$movement_packet] : [],
      [
        'stepped' => TRUE,
        'to_hex' => $params['to_hex'],
      ]
    );
    $mutations = $move_result['mutations'] ?? [];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $game_state['turn']['last_move_type'] = 'step';

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'stepped' => TRUE,
        'to_hex' => $params['to_hex'],
        'movement_execution_request' => $movement_execution_request,
        'movement_packet' => $movement_packet,
        'movement_resolution_envelope' => $movement_resolution_envelope,
      ],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('step', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'to' => $params['to_hex'],
          'movement_execution_request' => $movement_execution_request,
          'movement_packet' => $movement_packet,
          'movement_resolution_envelope' => $movement_resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute crawl intent block with legacy side effects.
   */
  protected function routeCrawlIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (empty($params['to_hex'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Missing to_hex.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $participant_id = (int) $participant['id'];
    if (!$this->conditionManager->hasCondition($participant_id, 'prone', $encounter_id)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Must be prone to Crawl.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if ((int) ($participant['speed'] ?? 25) < 10) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Speed is too low to Crawl (requires Speed >= 10 ft).'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $move_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    if (!empty($move_result['error'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => (string) $move_result['error']],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $movement_execution_request = $this->requireOptionalContractPayload(
      $move_result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'crawl.movement.execution_request'
    );
    $movement_packet = $this->requireOptionalContractPayload(
      $move_result['movement_packet'] ?? NULL,
      'movement_resolution',
      CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION,
      'crawl.movement.movement_packet'
    );
    $movement_resolution_envelope = $this->requireOptionalContractPayload(
      $move_result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'crawl.movement.resolution_envelope'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'crawl',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      is_array($movement_packet) ? [$movement_packet] : [],
      [
        'crawled' => TRUE,
        'to_hex' => $params['to_hex'],
      ]
    );
    $mutations = $move_result['mutations'] ?? [];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'crawled' => TRUE,
        'to_hex' => $params['to_hex'],
        'movement_execution_request' => $movement_execution_request,
        'movement_packet' => $movement_packet,
        'movement_resolution_envelope' => $movement_resolution_envelope,
      ],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('crawl', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'to' => $params['to_hex'],
          'movement_execution_request' => $movement_execution_request,
          'movement_packet' => $movement_packet,
          'movement_resolution_envelope' => $movement_resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute leap intent block with legacy side effects.
   */
  protected function routeLeapIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (empty($params['to_hex'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Missing to_hex.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $leap_speed = (int) ($participant['speed'] ?? 25);
    $max_leap_ft = $leap_speed >= 30 ? 15 : 10;

    $move_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    if (!empty($move_result['error'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => (string) $move_result['error']],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $movement_execution_request = $this->requireOptionalContractPayload(
      $move_result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'leap.movement.execution_request'
    );
    $movement_packet = $this->requireOptionalContractPayload(
      $move_result['movement_packet'] ?? NULL,
      'movement_resolution',
      CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION,
      'leap.movement.movement_packet'
    );
    $movement_resolution_envelope = $this->requireOptionalContractPayload(
      $move_result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'leap.movement.resolution_envelope'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'leap',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      is_array($movement_packet) ? [$movement_packet] : [],
      [
        'leaped' => TRUE,
        'to_hex' => $params['to_hex'],
        'max_leap_ft' => $max_leap_ft,
      ]
    );
    $mutations = $move_result['mutations'] ?? [];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'leaped' => TRUE,
        'to_hex' => $params['to_hex'],
        'max_leap_ft' => $max_leap_ft,
        'movement_execution_request' => $movement_execution_request,
        'movement_packet' => $movement_packet,
        'movement_resolution_envelope' => $movement_resolution_envelope,
      ],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('leap', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'to' => $params['to_hex'],
          'movement_execution_request' => $movement_execution_request,
          'movement_packet' => $movement_packet,
          'movement_resolution_envelope' => $movement_resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute arrest-fall intent block with legacy side effects.
   */
  protected function routeArrestFallIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    if (empty($entity_data['fly_speed'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Arrest a Fall requires fly Speed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $acrobatics_bonus = (int) ($params['acrobatics_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 15, $d20);
    $feet_fallen = (int) ($params['feet_fallen'] ?? 0);
    $fall_damage = 0;
    if ($degree === 'failure') {
      $fall_damage = (int) floor($feet_fallen / 2);
    }
    elseif ($degree === 'critical_failure') {
      $fall_damage = (int) ceil($feet_fallen / 20) * 10;
    }

    $game_state['turn']['reaction_available'] = FALSE;
    $reaction_packet = $this->unifiedReactionEngine->buildReactionResolutionPacket(
      (string) $actor_id,
      (string) $actor_id,
      'arrest_fall',
      $fall_damage > 0 ? 'partial' : 'resolved',
      ['encounter_id' => $encounter_id, 'degree' => $degree]
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'arrest_fall',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = [
      'arrest_fall' => TRUE,
      'degree' => $degree,
      'fall_damage' => $fall_damage,
      'roll' => $d20,
      'total' => $total,
    ];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [$reaction_packet],
      $result
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'reaction_packet' => $reaction_packet,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('arrest_fall', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'reaction_packet' => $reaction_packet,
          'degree' => $degree,
          'fall_damage' => $fall_damage,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute grab-edge intent block with legacy side effects.
   */
  protected function routeGrabEdgeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $reflex_bonus = (int) ($params['reflex_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $reflex_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 15, $d20);
    $grabbed = in_array($degree, ['critical_success', 'success'], TRUE);

    if ($grabbed) {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      if ($participant) {
        $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
        $entity_data['clinging'] = TRUE;
        $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
      }
    }

    $game_state['turn']['reaction_available'] = FALSE;
    $reaction_packet = $this->unifiedReactionEngine->buildReactionResolutionPacket(
      (string) $actor_id,
      (string) $actor_id,
      'grab_edge',
      $grabbed ? 'resolved' : 'failed',
      ['encounter_id' => $encounter_id, 'degree' => $degree]
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'grab_edge',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = [
      'grab_edge' => TRUE,
      'degree' => $degree,
      'grabbed' => $grabbed,
      'roll' => $d20,
      'total' => $total,
    ];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [$reaction_packet],
      $result
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'reaction_packet' => $reaction_packet,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('grab_edge', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'reaction_packet' => $reaction_packet,
          'degree' => $degree,
          'grabbed' => $grabbed,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute shield-block intent block with legacy side effects.
   */
  protected function routeShieldBlockIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    if (empty($entity_data['shield_raised'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Shield must be raised to use Shield Block.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $shield = $this->findHeldShield($entity_data);
    if (!$shield) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'No shield in hand.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $incoming_damage = (int) ($params['incoming_damage'] ?? 0);
    $hardness = (int) ($shield['hardness'] ?? 0);
    $reduced = max(0, $incoming_damage - $hardness);
    $shield_takes = (int) floor($reduced / 2);
    $entity_takes = $reduced - $shield_takes;

    if ($entity_takes > 0 && $this->hpManager) {
      $this->hpManager->applyDamage((int) $participant['id'], $entity_takes, 'physical', ['source' => 'shield_block_residual'], $encounter_id);
    }

    $shield['hp'] = max(0, (int) ($shield['hp'] ?? $shield['max_hp'] ?? 10) - $shield_takes);
    if ($shield['hp'] <= 0) {
      $shield['broken'] = TRUE;
      $entity_data['shield_raised'] = FALSE;
    }
    $entity_data = $this->updateHeldShield($entity_data, $shield);
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);

    $game_state['turn']['reaction_available'] = FALSE;
    $result = [
      'shield_block' => TRUE,
      'incoming_damage' => $incoming_damage,
      'hardness' => $hardness,
      'entity_damage' => $entity_takes,
      'shield_damage' => $shield_takes,
      'shield_broken' => $shield['broken'] ?? FALSE,
    ];
    $reaction_packet = $this->unifiedReactionEngine->buildReactionResolutionPacket(
      (string) $actor_id,
      is_string($target_id) && trim($target_id) !== '' ? (string) $target_id : (string) $actor_id,
      'shield_block',
      'resolved',
      ['encounter_id' => $encounter_id, 'entity_damage' => $entity_takes, 'shield_damage' => $shield_takes]
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'shield_block',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [$reaction_packet],
      $result
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'reaction_packet' => $reaction_packet,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('shield_block', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'reaction_packet' => $reaction_packet,
          'entity_damage' => $entity_takes,
          'shield_damage' => $shield_takes,
          'shield_broken' => $shield['broken'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute attack-of-opportunity intent block with legacy side effects.
   */
  protected function routeAttackOfOpportunityIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $class_features = $entity_data['class_features'] ?? [];
    if (!in_array('attack_of_opportunity', (array) $class_features, TRUE)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Character does not have Attack of Opportunity class feature.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (!$target_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'target required for Attack of Opportunity.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $weapon = $params['weapon'] ?? [];
    $weapon['skip_map_count'] = TRUE;
    $strike_result = $this->processStrike($encounter_id, $actor_id, $target_id, ['weapon' => $weapon, 'skip_map' => TRUE], $game_state);

    $trigger_type = $params['trigger_type'] ?? '';
    $disrupted = (($strike_result['degree'] ?? '') === 'critical_success' && $trigger_type === 'manipulate');
    $game_state['turn']['reaction_available'] = FALSE;
    $reaction_packet = $this->unifiedReactionEngine->buildReactionResolutionPacket(
      (string) $actor_id,
      (string) $target_id,
      'attack_of_opportunity',
      $disrupted ? 'disrupted' : 'resolved',
      ['encounter_id' => $encounter_id, 'trigger_type' => $trigger_type]
    );
    $strike_execution_request = $this->requireOptionalContractPayload(
      $strike_result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'attack_of_opportunity.strike.execution_request'
    );
    $strike_resolution_envelope = $this->requireOptionalContractPayload(
      $strike_result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'attack_of_opportunity.strike.resolution_envelope'
    );
    $strike_damage_packet = $this->requireOptionalContractPayload(
      $strike_result['damage_packet'] ?? NULL,
      'damage_application',
      CombatResolutionContractService::DAMAGE_PACKET_CONTRACT_VERSION,
      'attack_of_opportunity.strike.damage_packet'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'attack_of_opportunity',
      (string) $actor_id,
      (string) $target_id,
      $params
    );
    $resolution_packets = [$reaction_packet];
    if (is_array($strike_damage_packet)) {
      $resolution_packets[] = $strike_damage_packet;
    }
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $resolution_packets,
      [
        'degree' => $strike_result['degree'] ?? NULL,
        'damage' => $strike_result['damage'] ?? NULL,
        'disrupted' => $disrupted,
      ]
    );
    $result = array_merge($strike_result, [
      'execution_request' => $execution_request,
      'resolution_envelope' => $resolution_envelope,
      'reaction_packet' => $reaction_packet,
      'strike_execution_request' => $strike_execution_request,
      'strike_resolution_envelope' => $strike_resolution_envelope,
      'attack_of_opportunity' => TRUE,
      'disrupted' => $disrupted,
    ]);

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('attack_of_opportunity', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'reaction_packet' => $reaction_packet,
          'strike_execution_request' => $strike_execution_request,
          'strike_resolution_envelope' => $strike_resolution_envelope,
          'target' => $target_id,
          'degree' => $strike_result['degree'] ?? NULL,
          'damage' => $strike_result['damage'] ?? NULL,
          'disrupted' => $disrupted,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute balance intent block with legacy side effects.
   */
  protected function routeBalanceIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $dc = (int) ($params['dc'] ?? 15);
    $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $balanced = in_array($degree, ['success', 'critical_success'], TRUE);
    $state_effect_packets = [];
    if ($degree === 'critical_failure' || $degree === 'failure') {
      $this->conditionManager->applyCondition(
        (int) $actor_id,
        'flat_footed',
        0,
        ['remaining_attacks' => PHP_INT_MAX],
        'balance_fail',
        (int) $encounter_id
      );
      $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
        (string) $actor_id,
        (string) $actor_id,
        'condition',
        'flat_footed',
        'applied',
        0,
        ['encounter_id' => $encounter_id, 'action' => 'balance', 'degree' => $degree]
      );
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'balance',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['balanced' => $balanced, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      $result
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'state_effect_packets' => $state_effect_packets,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('balance', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packets' => $state_effect_packets,
        ])),
      ],
    ];
  }

  /**
   * Router seam: execute tumble-through intent block with legacy side effects.
   */
  protected function routeTumbleThroughIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $target_ref = $params['target_id'] ?? '';
    $dc = (int) ($params['dc'] ?? 15);
    $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $passed_through = in_array($degree, ['success', 'critical_success'], TRUE);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'tumble_through',
      (string) $actor_id,
      $target_ref !== '' ? (string) $target_ref : NULL,
      $params
    );
    $result = ['passed_through' => $passed_through, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('tumble_through', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ]), NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute maneuver-in-flight intent block with legacy side effects.
   */
  protected function routeManeuverInFlightIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $dc = (int) ($params['dc'] ?? 15);
    $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $maneuvered = in_array($degree, ['success', 'critical_success'], TRUE);
    if ($degree === 'critical_failure') {
      $game_state['encounter_state'][$actor_id . '_falling'] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'maneuver_in_flight',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['maneuvered' => $maneuvered, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('maneuver_in_flight', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ])),
      ],
    ];
  }

  /**
   * Router seam: execute feint intent block with legacy side effects.
   */
  protected function routeFeintIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? $target_id ?? '';
    $dc = (int) ($params['dc'] ?? 15);
    $deception = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $deception;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $feinted = FALSE;
    $state_effect_packets = [];
    if ($degree === 'critical_success') {
      $feinted = TRUE;
      $this->conditionManager->applyCondition((int) $target_ref, 'flat_footed', 0, ['remaining_attacks' => PHP_INT_MAX], 'feint_crit', (int) $encounter_id);
      $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
        (string) $actor_id,
        (string) $target_ref,
        'condition',
        'flat_footed',
        'applied',
        0,
        ['encounter_id' => $encounter_id, 'action' => 'feint', 'degree' => $degree]
      );
    }
    elseif ($degree === 'success') {
      $feinted = TRUE;
      $this->conditionManager->applyCondition((int) $target_ref, 'flat_footed', 0, ['remaining_attacks' => 1], 'feint', (int) $encounter_id);
      $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
        (string) $actor_id,
        (string) $target_ref,
        'condition',
        'flat_footed',
        'applied',
        0,
        ['encounter_id' => $encounter_id, 'action' => 'feint', 'degree' => $degree]
      );
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 2);
    $result = ['feinted' => $feinted, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'feint',
      (string) $actor_id,
      $target_ref !== '' ? (string) $target_ref : NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      $result
    );
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && $target_ref !== '') {
      $event_type = match ($degree) {
        'critical_success' => 'deception_critical_success',
        'success' => 'deception_success',
        'failure' => 'deception_failure',
        'critical_failure' => 'deception_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter feint against %s (%s)', (string) $target_ref, (string) $degree),
          [
            'target_entity_ref' => (string) $target_ref,
            'relationship_type' => 'combat',
            'relationship_status' => 'known',
            'degree' => $degree,
            'idempotency_key' => sha1(json_encode([
              'encounter_feint' => TRUE,
              'encounter_id' => $encounter_id,
              'event_type' => $event_type,
              'source' => (string) $actor_id,
              'target' => (string) $target_ref,
              'degree' => $degree,
              'roll' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'state_effect_packets' => $state_effect_packets,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('feint', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packets' => $state_effect_packets,
        ]), NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute create-diversion intent block with legacy side effects.
   */
  protected function routeCreateDiversionIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? $target_id ?? '';
    $dc = (int) ($params['dc'] ?? 15);
    $deception = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $deception;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $diverted = in_array($degree, ['success', 'critical_success'], TRUE);
    if ($diverted) {
      $game_state['encounter_state'][$actor_id . '_created_diversion'] = TRUE;
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['diverted' => $diverted, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'create_diversion',
      (string) $actor_id,
      $target_ref !== '' ? (string) $target_ref : NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id)) {
      $event_type = match ($degree) {
        'critical_success' => 'deception_critical_success',
        'success' => 'deception_success',
        'failure' => 'deception_failure',
        'critical_failure' => 'deception_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter create diversion%s (%s)', $target_ref !== '' ? ' against ' . $target_ref : '', (string) $degree),
          [
            'target_entity_ref' => (string) $target_ref,
            'relationship_type' => 'conversation',
            'relationship_status' => 'known',
            'degree' => $degree,
            'idempotency_key' => sha1(json_encode([
              'encounter_create_diversion' => TRUE,
              'encounter_id' => $encounter_id,
              'event_type' => $event_type,
              'source' => (string) $actor_id,
              'target' => (string) $target_ref,
              'degree' => $degree,
              'roll' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('create_diversion', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ])),
      ],
    ];
  }

  /**
   * Router seam: execute request intent block with legacy side effects.
   */
  protected function routeRequestIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? '';
    $base_dc = (int) ($params['dc'] ?? 15);
    $dc_context = $this->applyNpcAttitudeToSocialDc($base_dc, $params, $target_id ?: $target_ref, $campaign_id);
    $dc = $dc_context['dc'];
    $diplomacy = (int) ($params['diplomacy_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $diplomacy;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $granted = in_array($degree, ['success', 'critical_success'], TRUE);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = [
      'granted' => $granted,
      'degree' => $degree,
      'roll' => $total,
      'dc' => $dc,
      'base_dc' => $base_dc,
      'attitude_dc_delta' => $dc_context['delta'],
    ];
    if ($dc_context['attitude'] !== NULL) {
      $result['npc_attitude'] = $dc_context['attitude'];
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'request',
      (string) $actor_id,
      $target_ref !== '' ? (string) $target_ref : NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && $target_ref !== '') {
      $event_type = match ($degree) {
        'critical_success' => 'diplomacy_critical_success',
        'success' => 'diplomacy_success',
        'failure', 'critical_failure' => 'diplomacy_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter request against %s (%s)', (string) $target_ref, (string) $degree),
          [
            'target_entity_ref' => (string) $target_ref,
            'relationship_type' => 'conversation',
            'relationship_status' => 'known',
            'degree' => $degree,
            'idempotency_key' => sha1(json_encode([
              'encounter_request' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $target_ref,
              'degree' => $degree,
              'roll' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('request', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ]), NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute demoralize intent block with legacy side effects.
   */
  protected function routeDemoralizeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? '';
    $base_dc = (int) ($params['dc'] ?? 15);
    $dc_context = $this->applyNpcAttitudeToSocialDc($base_dc, $params, $target_id ?: $target_ref, $campaign_id);
    $dc = $dc_context['dc'];
    $intimidation = (int) ($params['intimidation_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $intimidation;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $immune_key = 'demoralize_immune_' . $target_ref . '_' . $actor_id;
    $immune = !empty($game_state['encounter_state'][$immune_key]);
    $demoralized = FALSE;
    $state_effect_packets = [];
    if (!$immune) {
      $game_state['encounter_state'][$immune_key] = TRUE;
      if ($degree === 'critical_success') {
        $demoralized = TRUE;
        $this->conditionManager->applyCondition((int) $target_ref, 'frightened', 2, [], 'demoralize_crit', (int) $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $target_ref,
          'condition',
          'frightened',
          'applied',
          2,
          ['encounter_id' => $encounter_id, 'action' => 'demoralize', 'degree' => $degree]
        );
      }
      elseif ($degree === 'success') {
        $demoralized = TRUE;
        $this->conditionManager->applyCondition((int) $target_ref, 'frightened', 1, [], 'demoralize', (int) $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $target_ref,
          'condition',
          'frightened',
          'applied',
          1,
          ['encounter_id' => $encounter_id, 'action' => 'demoralize', 'degree' => $degree]
        );
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = [
      'demoralized' => $demoralized,
      'immune' => $immune,
      'degree' => $degree,
      'roll' => $total,
      'dc' => $dc,
      'base_dc' => $base_dc,
      'attitude_dc_delta' => $dc_context['delta'],
    ];
    if ($dc_context['attitude'] !== NULL) {
      $result['npc_attitude'] = $dc_context['attitude'];
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'demoralize',
      (string) $actor_id,
      $target_ref !== '' ? (string) $target_ref : NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      $result
    );
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && $target_ref !== '' && !$immune) {
      $event_type = match ($degree) {
        'critical_success' => 'intimidation_critical_success',
        'success' => 'intimidation_success',
        'failure' => 'intimidation_failure',
        'critical_failure' => 'intimidation_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter demoralize against %s (%s)', (string) $target_ref, (string) $degree),
          [
            'target_entity_ref' => (string) $target_ref,
            'relationship_type' => 'combat',
            'relationship_status' => 'known',
            'degree' => $degree,
            'idempotency_key' => sha1(json_encode([
              'encounter_id' => $encounter_id,
              'event_type' => $event_type,
              'source' => (string) $actor_id,
              'target' => (string) $target_ref,
              'degree' => $degree,
              'roll' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'state_effect_packets' => $state_effect_packets,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('demoralize', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packets' => $state_effect_packets,
        ]), NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute command-animal intent block with legacy side effects.
   */
  protected function routeCommandAnimalIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? $target_id ?? $actor_id;
    $dc = (int) ($params['dc'] ?? 15);
    if (!empty($params['is_trained_companion'])) {
      $dc -= 5;
    }
    $nature = (int) ($params['nature_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $nature;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $obeyed = in_array($degree, ['success', 'critical_success'], TRUE);
    if ($degree === 'critical_failure') {
      $game_state['encounter_state']['animal_panicked_' . $target_ref] = TRUE;
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['obeyed' => $obeyed, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'command_animal',
      (string) $actor_id,
      $target_ref !== '' ? (string) $target_ref : NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && $target_ref !== '') {
      $event_type = match ($degree) {
        'critical_success' => 'command_animal_critical_success',
        'success' => 'command_animal_success',
        'failure' => 'command_animal_failure',
        'critical_failure' => 'command_animal_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter command animal for %s (%s)', (string) $target_ref, (string) $degree),
          [
            'target_entity_ref' => (string) $target_ref,
            'relationship_type' => 'companion',
            'relationship_status' => 'known',
            'degree' => $degree,
            'idempotency_key' => sha1(json_encode([
              'encounter_command_animal' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $target_ref,
              'degree' => $degree,
              'roll' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('command_animal', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ]), NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute perform intent block with legacy side effects.
   */
  protected function routePerformIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? $target_id ?? '';
    $dc = (int) ($params['dc'] ?? 15);
    $performance = (int) ($params['performance_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $performance;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $entertained = in_array($degree, ['success', 'critical_success'], TRUE);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['entertained' => $entertained, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'perform',
      (string) $actor_id,
      $target_ref !== '' ? (string) $target_ref : NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id)) {
      $event_type = match ($degree) {
        'critical_success' => 'perform_critical_success',
        'success' => 'perform_success',
        'failure' => 'perform_failure',
        'critical_failure' => 'perform_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter perform%s (%s)', $target_ref !== '' ? ' for ' . $target_ref : '', (string) $degree),
          [
            'target_entity_ref' => (string) $target_ref,
            'relationship_type' => 'conversation',
            'relationship_status' => 'known',
            'degree' => $degree,
            'idempotency_key' => sha1(json_encode([
              'encounter_perform' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $target_ref,
              'degree' => $degree,
              'roll' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('perform', 'encounter', $actor_id, array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ]), NULL, $target_ref !== '' ? $target_ref : NULL),
      ],
    ];
  }

  /**
   * Router seam: execute escape intent block with legacy side effects.
   */
  protected function routeEscapeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $result = $this->processEscape($encounter_id, $actor_id, $params, $game_state);
    $mutations = $result['mutations'] ?? [];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'escape',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'degree' => $result['degree'] ?? NULL,
        'escaped' => $result['escaped'] ?? NULL,
      ]
    );
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => array_merge((array) $result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('escape', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute seek intent block with legacy side effects.
   */
  protected function routeSeekIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $result = $this->processSeek($encounter_id, $actor_id, $params, $game_state);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'seek',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['seek' => TRUE]
    );
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => array_merge((array) $result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('seek', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute search intent block with legacy side effects.
   */
  protected function routeSearchIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (!$this->explorationPhaseHandler) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Room search handler is unavailable.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $search_result = $this->explorationPhaseHandler->processSearch($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mechanical_result = $search_result;
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'search',
      (string) $actor_id,
      NULL,
      $params
    );
    $mutations = $search_result['mutations'] ?? [];
    $narration = $search_result['narration'] ?? NULL;
    $resolved_room_id = trim((string) (
      $params['room_id']
      ?? $dungeon_data['active_room_id']
      ?? $game_state['encounter_context']['room_id']
      ?? ''
    ));
    if (!empty($game_state['turn'])) {
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    }

    $events = [];
    $public_discoveries = $this->buildPublicSearchDiscoveries($search_result['discoveries'] ?? []);
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'search' => TRUE,
        'discoveries_count' => count($public_discoveries),
        'room_id' => $resolved_room_id !== '' ? $resolved_room_id : NULL,
      ]
    );
    if ($public_discoveries !== [] || (is_string($narration) && trim($narration) !== '')) {
      $events[] = GameEventLogger::buildEvent('search', 'encounter', $actor_id, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'discoveries' => $public_discoveries,
        'round' => $game_state['round'] ?? NULL,
        'room_id' => $resolved_room_id !== '' ? $resolved_room_id : NULL,
      ], $narration);
    }

    $public_result = $this->buildPublicSearchResult($search_result);
    return [
      'result' => array_merge((array) $public_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
      'mechanical_result' => $mechanical_result,
    ];
  }

  /**
   * Router seam: execute sense-motive intent block with legacy side effects.
   */
  protected function routeSenseMotiveIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $bonus = (int) ($params['perception_bonus'] ?? 0);
    $dc = (int) ($params['deception_dc'] ?? 15);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    if (!isset($game_state['sense_motive'])) {
      $game_state['sense_motive'] = [];
    }
    if (!isset($game_state['sense_motive'][$actor_id])) {
      $game_state['sense_motive'][$actor_id] = [];
    }
    $game_state['sense_motive'][$actor_id][$target_id] = $game_state['round'] ?? 0;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'sense_motive',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['sense_motive' => TRUE, 'degree' => $degree]
    );
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && !empty($target_id)) {
      $event_type = match ($degree) {
        'critical_success' => 'sense_motive_critical_success',
        'success' => 'sense_motive_success',
        'failure' => 'sense_motive_failure',
        'critical_failure' => 'sense_motive_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter sense motive against %s (%s)', (string) $target_id, (string) $degree),
          [
            'target_entity_ref' => (string) $target_id,
            'relationship_type' => 'conversation',
            'relationship_status' => 'known',
            'degree' => $degree,
            'idempotency_key' => sha1(json_encode([
              'encounter_sense_motive' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $target_id,
              'degree' => $degree,
              'roll' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'sense_motive' => TRUE,
        'degree' => $degree,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('sense_motive', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute take-cover intent block with legacy side effects.
   */
  protected function routeTakeCoverIntentExecution(
    ?string $actor_id,
    array &$game_state
  ): array {
    if (!isset($game_state['entities'])) {
      $game_state['entities'] = [];
    }
    if (!isset($game_state['entities'][$actor_id])) {
      $game_state['entities'][$actor_id] = [];
    }
    $current_cover = $game_state['entities'][$actor_id]['cover'] ?? 'none';
    $new_cover = ($current_cover === 'standard') ? 'greater' : 'standard';
    $game_state['entities'][$actor_id]['cover'] = $new_cover;
    $game_state['entities'][$actor_id]['cover_active'] = TRUE;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'take_cover',
      (string) $actor_id,
      NULL,
      []
    );
    $result = ['cover' => $new_cover, 'cover_active' => TRUE];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('take_cover', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'cover' => $new_cover,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute release intent block with legacy side effects.
   */
  protected function routeReleaseIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $item_id = $params['item_id'] ?? NULL;
    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $entity_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
        if ($entity_id === $actor_id) {
          if ($item_id && isset($entity['equipment']['held'][$item_id])) {
            unset($entity['equipment']['held'][$item_id]);
          }
          break;
        }
      }
      unset($entity);
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'release',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['released' => TRUE, 'item_id' => $item_id];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('release', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'item_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute climb intent block with legacy side effects.
   */
  protected function routeClimbIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $has_climb_speed = !empty($entity_ref['climb_speed']) && (int) $entity_ref['climb_speed'] > 0;
    $land_speed = (int) ($entity_ref['speed'] ?? 25);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $climb_dc = (int) ($params['climb_dc'] ?? 15);

    if ($has_climb_speed) {
      $athletics += 4;
      $d20 = 0;
      $total = 0;
      $degree = 'success';
      $feet_moved = (int) $entity_ref['climb_speed'];
    }
    else {
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $athletics;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $climb_dc, $d20);
      $feet_moved = 0;
      if ($degree === 'critical_success') {
        $feet_moved = max(10, (int) round($land_speed / 2));
      }
      elseif ($degree === 'success') {
        $feet_moved = max(5, (int) round($land_speed / 4));
      }
      elseif ($degree === 'critical_failure') {
        $feet_fallen = (int) ($params['height_ft'] ?? 10);
        $soft_surface = !empty($params['soft_surface']);
        if ($this->hpManager) {
          $this->hpManager->applyFallDamage((int) $participant['id'], $feet_fallen, $encounter_id, $soft_surface);
        }
      }
    }

    $fell = ($degree === 'critical_failure');
    $state_effect_packets = [];
    if (!$has_climb_speed && !$fell) {
      $this->conditionManager->applyCondition((int) $participant['id'], 'flat_footed', 0, ['type' => 'encounter', 'remaining' => 1], 'climb', $encounter_id);
      $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
        (string) $actor_id,
        (string) $actor_id,
        'condition',
        'flat_footed',
        'applied',
        0,
        ['encounter_id' => $encounter_id, 'action' => 'climb', 'degree' => $degree]
      );
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $result = [
      'climbed' => !$fell,
      'degree' => $degree,
      'feet_moved' => $feet_moved,
      'fell' => $fell,
      'd20' => $d20,
      'total' => $total,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'climb',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      $result
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'state_effect_packets' => $state_effect_packets,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('climb', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packets' => $state_effect_packets,
          'degree' => $degree,
          'fell' => $fell,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute force-open intent block with legacy side effects.
   */
  protected function routeForceOpenIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $has_crowbar = !empty($params['has_crowbar']);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $item_penalty = $has_crowbar ? 0 : -2;
    $dc = (int) ($params['object_dc'] ?? 20);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $item_penalty + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $jammed = FALSE;
    $broken = FALSE;
    $opened = FALSE;
    if ($degree === 'critical_success') {
      $opened = TRUE;
    }
    elseif ($degree === 'success') {
      $opened = TRUE;
      $broken = TRUE;
    }
    elseif ($degree === 'critical_failure') {
      $jammed = TRUE;
      if (!isset($game_state['force_open_jammed'])) {
        $game_state['force_open_jammed'] = [];
      }
      $target_obj = $params['object_id'] ?? $target_id;
      $game_state['force_open_jammed'][$target_obj] = ($game_state['force_open_jammed'][$target_obj] ?? 0) - 2;
    }

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $result = [
      'opened' => $opened,
      'broken' => $broken,
      'jammed' => $jammed,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'force_open',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      $result
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('force_open', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'opened' => $opened,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute grapple intent block with legacy side effects.
   */
  protected function routeGrappleIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $result = $this->processGrapple($encounter_id, $actor_id, $target_id, $params, $game_state);
    $mutations = $result['mutations'] ?? [];
    $state_effect_packet = NULL;
    if (is_string($result['condition_applied'] ?? NULL) && trim((string) $result['condition_applied']) !== '' && is_string($target_id) && trim($target_id) !== '') {
      $state_effect_packet = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
        (string) $actor_id,
        (string) $target_id,
        'condition',
        (string) $result['condition_applied'],
        'applied',
        NULL,
        [
          'encounter_id' => $encounter_id,
          'action' => 'grapple',
          'degree' => $result['degree'] ?? NULL,
        ]
      );
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'grapple',
      (string) $actor_id,
      (string) $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      array_values(array_filter([$state_effect_packet], 'is_array')),
      [
        'condition_applied' => $result['condition_applied'] ?? NULL,
        'degree' => $result['degree'] ?? NULL,
      ]
    );
    $game_state['turn']['attacks_this_turn'] = ($game_state['turn']['attacks_this_turn'] ?? 0) + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('grapple', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'state_effect_packet' => $state_effect_packet,
          'condition_applied' => $result['condition_applied'] ?? NULL,
          'degree' => $result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute high-jump intent block with legacy side effects.
   */
  protected function routeHighJumpIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'high_jump',
      (string) $actor_id,
      NULL,
      $params
    );
    $prior_stride_ft = (int) ($game_state['turn']['last_stride_ft'] ?? 0);
    if ($prior_stride_ft < 10) {
      $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
        $execution_request,
        [],
        ['jumped' => FALSE, 'auto_fail' => TRUE, 'reason' => 'No prior Stride of ≥10 ft']
      );
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
      return [
        'result' => [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'jumped' => FALSE,
          'auto_fail' => TRUE,
          'reason' => 'No prior Stride of ≥10 ft',
        ],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('high_jump', 'encounter', $actor_id, [
            'execution_request' => $execution_request,
            'resolution_envelope' => $resolution_envelope,
            'auto_fail' => TRUE,
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $dc = (int) ($params['dc'] ?? 30);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $height_ft = 0;
    $fell_prone = FALSE;
    $state_effect_packets = [];
    if ($degree === 'critical_success') {
      $height_ft = 8;
    }
    elseif ($degree === 'success') {
      $height_ft = 5;
    }
    elseif ($degree === 'critical_failure') {
      $fell_prone = TRUE;
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      if ($participant) {
        $this->conditionManager->applyCondition((int) $participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'high_jump', $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $actor_id,
          'condition',
          'prone',
          'applied',
          0,
          ['encounter_id' => $encounter_id, 'action' => 'high_jump', 'degree' => $degree, 'self_inflicted' => TRUE]
        );
      }
    }

    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      [
        'jumped' => !$fell_prone,
        'height_ft' => $height_ft,
        'degree' => $degree,
        'fell_prone' => $fell_prone,
      ]
    );
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'jumped' => !$fell_prone,
        'height_ft' => $height_ft,
        'degree' => $degree,
        'fell_prone' => $fell_prone,
        'state_effect_packets' => $state_effect_packets,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('high_jump', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'height_ft' => $height_ft,
          'fell_prone' => $fell_prone,
          'state_effect_packets' => $state_effect_packets,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute long-jump intent block with legacy side effects.
   */
  protected function routeLongJumpIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'long_jump',
      (string) $actor_id,
      NULL,
      $params
    );
    $prior_stride_ft = (int) ($game_state['turn']['last_stride_ft'] ?? 0);
    if ($prior_stride_ft < 10) {
      $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
        $execution_request,
        [],
        ['jumped' => FALSE, 'auto_fail' => TRUE, 'reason' => 'No prior Stride of ≥10 ft']
      );
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
      return [
        'result' => [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'jumped' => FALSE,
          'auto_fail' => TRUE,
          'reason' => 'No prior Stride of ≥10 ft',
        ],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, [
            'execution_request' => $execution_request,
            'resolution_envelope' => $resolution_envelope,
            'auto_fail' => TRUE,
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $target_ft = (int) ($params['target_ft'] ?? 10);
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $entity_ref = $participant && !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $speed = (int) ($entity_ref['speed'] ?? $participant['speed'] ?? 25);
    if ($target_ft > $speed) {
      $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
        $execution_request,
        [],
        ['jumped' => FALSE, 'auto_fail' => TRUE, 'reason' => 'Target distance exceeds Speed']
      );
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
      return [
        'result' => [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'jumped' => FALSE,
          'auto_fail' => TRUE,
          'reason' => 'Target distance exceeds Speed',
        ],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, [
            'execution_request' => $execution_request,
            'resolution_envelope' => $resolution_envelope,
            'auto_fail' => TRUE,
            'reason' => 'speed_cap',
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $dc = $target_ft;
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $distance_ft = 0;
    $fell_prone = FALSE;
    $state_effect_packets = [];
    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $distance_ft = $target_ft;
    }
    elseif ($degree === 'critical_failure') {
      $fell_prone = TRUE;
      if ($participant) {
        $this->conditionManager->applyCondition((int) $participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'long_jump', $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $actor_id,
          'condition',
          'prone',
          'applied',
          0,
          ['encounter_id' => $encounter_id, 'action' => 'long_jump', 'degree' => $degree, 'self_inflicted' => TRUE]
        );
      }
    }

    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      [
        'jumped' => !$fell_prone || $distance_ft > 0,
        'distance_ft' => $distance_ft,
        'degree' => $degree,
        'fell_prone' => $fell_prone,
      ]
    );
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'jumped' => !$fell_prone || $distance_ft > 0,
        'distance_ft' => $distance_ft,
        'degree' => $degree,
        'fell_prone' => $fell_prone,
        'state_effect_packets' => $state_effect_packets,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'distance_ft' => $distance_ft,
          'fell_prone' => $fell_prone,
          'state_effect_packets' => $state_effect_packets,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute shove intent block with legacy side effects.
   */
  protected function routeShoveIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $dc = (int) ($params['fortitude_dc'] ?? 15);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $push_ft = 0;
    $attacker_prone = FALSE;
    $target_participant = NULL;
    if ($degree === 'critical_success') {
      $push_ft = 10;
    }
    elseif ($degree === 'success') {
      $push_ft = 5;
    }
    elseif ($degree === 'critical_failure') {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      if ($participant) {
        $this->conditionManager->applyCondition((int) $participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'shove', $encounter_id);
      }
      $attacker_prone = TRUE;
    }

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $forced_result = [];
    $forced_mutations = [];
    $hazard_events = [];
    $hazard_damage_packet = NULL;
    $hazard_execution_request = NULL;
    $hazard_resolution_envelope = NULL;
    $forced_execution_request = NULL;
    $forced_resolution_envelope = NULL;
    $forced_movement_packet = NULL;
    if ($push_ft > 0 && $encounter_id && $actor_id !== NULL && $target_id !== NULL) {
      $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      $actor_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, (string) $actor_id) : NULL;
      $target_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, (string) $target_id) : NULL;
      if ($actor_participant && $target_participant) {
        $forced_destination = $this->resolveForcedShoveDestinationHex($actor_participant, $target_participant, (int) $push_ft, $dungeon_data);
        if ($forced_destination !== NULL) {
          $forced_result = $this->processStride(
            (int) $encounter_id,
            (string) $target_id,
            [
              'to_hex' => $forced_destination['to_hex'],
              'is_forced' => TRUE,
              'distance_ft' => (int) ($forced_destination['distance_ft'] ?? 0),
              'action_cost' => 0,
            ],
            $game_state,
            $dungeon_data,
            $campaign_id
          );
          if (empty($forced_result['error'])) {
            $forced_mutations = (array) ($forced_result['mutations'] ?? []);
            $forced_execution_request = $this->requireOptionalContractPayload(
              $forced_result['execution_request'] ?? NULL,
              'combat_execution_request',
              CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
              'shove.forced_movement.execution_request'
            );
            $forced_resolution_envelope = $this->requireOptionalContractPayload(
              $forced_result['resolution_envelope'] ?? NULL,
              'combat_resolution_envelope',
              CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
              'shove.forced_movement.resolution_envelope'
            );
            $forced_movement_packet = $this->requireOptionalContractPayload(
              $forced_result['movement_packet'] ?? NULL,
              'movement_resolution',
              CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION,
              'shove.forced_movement.movement_packet'
            );
            $terrain_hazard = $this->resolveEncounterTerrainHazardForMovement(
              (int) $encounter_id,
              (string) $actor_id,
              (string) $target_id,
              (array) $target_participant,
              (array) ($forced_result['to_hex'] ?? []),
              (string) ($game_state['active_room_id'] ?? ($dungeon_data['current_room_id'] ?? '')),
              $dungeon_data
            );
            $hazard_events = (array) ($terrain_hazard['events'] ?? []);
            $hazard_damage_packet = $this->requireOptionalContractPayload(
              $terrain_hazard['damage_packet'] ?? NULL,
              'damage_application',
              CombatResolutionContractService::DAMAGE_PACKET_CONTRACT_VERSION,
              'terrain_hazard.damage_packet'
            );
            $hazard_execution_request = $this->requireOptionalContractPayload(
              $terrain_hazard['execution_request'] ?? NULL,
              'combat_execution_request',
              CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
              'terrain_hazard.execution_request'
            );
            $hazard_resolution_envelope = $this->requireOptionalContractPayload(
              $terrain_hazard['resolution_envelope'] ?? NULL,
              'combat_resolution_envelope',
              CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
              'terrain_hazard.resolution_envelope'
            );
            if (!empty($terrain_hazard['mutations']) && is_array($terrain_hazard['mutations'])) {
              $forced_mutations = array_merge($forced_mutations, $terrain_hazard['mutations']);
            }
            $this->syncEncounterParticipantsToDungeonData((int) $encounter_id, $dungeon_data);
          }
        }
      }
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'shove',
      (string) $actor_id,
      (string) $target_id,
      $params
    );
    $resolution_packets = [];
    if (is_array($forced_movement_packet)) {
      $resolution_packets[] = $forced_movement_packet;
    }
    if (is_array($hazard_damage_packet)) {
      $resolution_packets[] = $hazard_damage_packet;
    }
    $resolution_result = [
      'degree' => $degree,
      'push_ft' => $push_ft,
      'forced_movement' => !empty($forced_result['stride']),
      'forced_to' => $forced_result['to_hex'] ?? NULL,
      'attacker_prone' => $attacker_prone,
      'hazard_count' => is_array($hazard_events) ? count($hazard_events) : 0,
    ];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $resolution_packets,
      $resolution_result
    );

    $shove_event_payload = [
      'execution_request' => $execution_request,
      'resolution_envelope' => $resolution_envelope,
      'degree' => $degree,
      'push_ft' => $push_ft,
      'round' => $game_state['round'] ?? NULL,
      'forced_movement' => !empty($forced_result['stride']),
      'forced_to' => $forced_result['to_hex'] ?? NULL,
      'forced_execution_request' => $forced_execution_request,
      'movement_packet' => $forced_movement_packet,
      'forced_resolution_envelope' => $forced_resolution_envelope,
      'hazard_execution_request' => $hazard_execution_request,
      'hazard_resolution_envelope' => $hazard_resolution_envelope,
      'damage_packet' => $hazard_damage_packet,
    ];
    if ($hazard_events !== []) {
      $shove_event_payload['hazard_events'] = $hazard_events;
    }

    $events = [
      GameEventLogger::buildEvent('shove', 'encounter', $actor_id, $shove_event_payload, NULL, $target_id),
    ];
    foreach ($hazard_events as $hazard_event) {
      if (!is_array($hazard_event)) {
        continue;
      }
      $events[] = GameEventLogger::buildEvent('hazard_triggered', 'encounter', (string) $target_id, [
        'hazard' => $hazard_event,
        'target' => (string) $target_id,
        'target_name' => (string) ($target_participant['name'] ?? $target_id),
        'execution_request' => $hazard_execution_request,
        'resolution_envelope' => $hazard_resolution_envelope,
        'damage_packet' => $hazard_damage_packet,
        'round' => $game_state['round'] ?? NULL,
      ], NULL, (string) $target_id);
    }

    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'shoved' => $push_ft > 0,
        'push_ft' => $push_ft,
        'degree' => $degree,
        'forced_movement' => TRUE,
        'attacker_prone' => $attacker_prone,
        'd20' => $d20,
        'total' => $total,
        'forced_to' => $forced_result['to_hex'] ?? NULL,
        'forced_execution_request' => $forced_execution_request,
        'movement_packet' => $forced_movement_packet,
        'forced_resolution_envelope' => $forced_resolution_envelope,
        'hazard_execution_request' => $hazard_execution_request,
        'hazard_resolution_envelope' => $hazard_resolution_envelope,
        'hazard_events' => $hazard_events,
        'damage_packet' => $hazard_damage_packet,
      ],
      'mutations' => $forced_mutations,
      'events' => $events,
    ];
  }

  /**
   * Resolve one forced shove destination from actor and target encounter positions.
   */
  protected function resolveForcedShoveDestinationHex(array $actor_participant, array $target_participant, int $push_ft, array $dungeon_data): ?array {
    $actor_hex = [
      'q' => (int) ($actor_participant['position_q'] ?? 0),
      'r' => (int) ($actor_participant['position_r'] ?? 0),
    ];
    $target_hex = [
      'q' => (int) ($target_participant['position_q'] ?? 0),
      'r' => (int) ($target_participant['position_r'] ?? 0),
    ];
    $direction_hex = [
      'q' => (int) ($target_hex['q'] + ($target_hex['q'] - $actor_hex['q'])),
      'r' => (int) ($target_hex['r'] + ($target_hex['r'] - $actor_hex['r'])),
    ];
    if ($this->movementResolver) {
      $forced = $this->movementResolver->computeForcedMovement(
        $target_hex,
        $direction_hex,
        max(0, $push_ft),
        $dungeon_data
      );
      $final_hex = is_array($forced['final_hex'] ?? NULL) ? $forced['final_hex'] : NULL;
      if ($final_hex !== NULL) {
        return [
          'to_hex' => [
            'q' => (int) ($final_hex['q'] ?? $target_hex['q']),
            'r' => (int) ($final_hex['r'] ?? $target_hex['r']),
          ],
          'distance_ft' => (int) ($forced['actual_feet'] ?? 0),
        ];
      }
    }

    return [
      'to_hex' => $direction_hex,
      'distance_ft' => 5,
    ];
  }

  /**
   * Apply movement-into-terrain consequences for encounter forced movement.
   *
   * @return array<string, mixed>
   */
  protected function resolveEncounterTerrainHazardForMovement(
    int $encounter_id,
    string $source_actor_id,
    string $target_id,
    array $target_participant,
    array $to_hex,
    string $room_id,
    array $dungeon_data
  ): array {
    $room_id = trim((string) $room_id);
    if ($room_id === '') {
      return ['events' => [], 'execution_request' => NULL, 'resolution_envelope' => NULL, 'damage_packet' => NULL, 'mutations' => []];
    }
    $terrain = strtolower(trim((string) ($this->resolveRoomHexTerrainTypeForEncounter($dungeon_data, $room_id, $to_hex) ?? '')));
    if (!str_contains($terrain, 'lava')) {
      return ['events' => [], 'execution_request' => NULL, 'resolution_envelope' => NULL, 'damage_packet' => NULL, 'mutations' => []];
    }

    $target_participant_id = (int) ($target_participant['id'] ?? 0);
    $hp_before = isset($target_participant['hp']) ? (int) $target_participant['hp'] : 0;
    if ($target_participant_id <= 0 || $hp_before <= 0) {
      return ['events' => [], 'execution_request' => NULL, 'resolution_envelope' => NULL, 'damage_packet' => NULL, 'mutations' => []];
    }

    $damage = 6;
    $damage_result = $this->hpManager->applyDamage(
      $target_participant_id,
      $damage,
      'fire',
      [
        'type' => 'terrain_hazard',
        'terrain' => 'lava',
        'source_actor' => $source_actor_id,
      ],
      $encounter_id
    );
    $applied = max(0, (int) ($damage_result['hp_damage'] ?? $damage_result['final_damage'] ?? 0));
    $hp_after = max(0, (int) ($damage_result['new_hp'] ?? ($hp_before - $applied)));
    $damage_packet = $this->unifiedDamageEngine->buildDamageApplicationPacket(
      $source_actor_id,
      $target_id,
      'terrain_hazard',
      $applied,
      'fire',
      ['lava'],
      [
        'terrain' => 'lava',
        'encounter_id' => $encounter_id,
        'target_hp_before' => $hp_before,
        'target_hp_after' => $hp_after,
      ]
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'terrain_hazard',
      $source_actor_id,
      $target_id,
      [
        'terrain' => 'lava',
        'room_id' => $room_id,
        'to_hex' => [
          'q' => (int) ($to_hex['q'] ?? 0),
          'r' => (int) ($to_hex['r'] ?? 0),
        ],
      ]
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [$damage_packet],
      [
        'terrain' => 'lava',
        'damage' => $applied,
        'damage_type' => 'fire',
        'target_hp_before' => $hp_before,
        'target_hp_after' => $hp_after,
      ]
    );

    return [
      'events' => [[
        'type' => 'hazard_triggered',
        'instance_id' => 'terrain:lava',
        'name' => 'Lava',
        'room_id' => $room_id,
        'hex' => [
          'q' => (int) ($to_hex['q'] ?? 0),
          'r' => (int) ($to_hex['r'] ?? 0),
        ],
        'effect' => [
          'description' => 'Molten terrain scorches the creature.',
          'damage' => $damage,
          'damage_type' => 'fire',
          'resolved_damage' => $applied,
          'damage_applied' => $applied,
        ],
      ]],
      'execution_request' => $execution_request,
      'resolution_envelope' => $resolution_envelope,
      'damage_packet' => $damage_packet,
      'mutations' => [[
        'entity' => $target_id,
        'field' => 'hp',
        'from' => $hp_before,
        'to' => $hp_after,
      ]],
    ];
  }

  /**
   * Resolve one room hex terrain label from the active encounter dungeon payload.
   */
  protected function resolveRoomHexTerrainTypeForEncounter(array $dungeon_data, string $room_id, array $to_hex): ?string {
    $room = $this->findRoomById($dungeon_data, $room_id);
    if (!is_array($room)) {
      return NULL;
    }
    $target_q = (int) ($to_hex['q'] ?? 0);
    $target_r = (int) ($to_hex['r'] ?? 0);
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (!is_array($hex)) {
        continue;
      }
      if ((int) ($hex['q'] ?? 0) !== $target_q || (int) ($hex['r'] ?? 0) !== $target_r) {
        continue;
      }
      $terrain = trim((string) ($hex['terrain_type'] ?? $hex['terrain'] ?? $hex['tile_type'] ?? ''));
      if ($terrain !== '') {
        return $terrain;
      }
    }

    return NULL;
  }

  /**
   * Router seam: execute swim intent block with legacy side effects.
   */
  protected function routeSwimIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];

    $is_calm = !empty($params['calm_water']);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $swim_dc = (int) ($params['swim_dc'] ?? 15);
    $land_speed = (int) ($entity_ref['speed'] ?? 25);
    $has_swim_speed = !empty($entity_ref['swim_speed']) && (int) $entity_ref['swim_speed'] > 0;
    if ($has_swim_speed) {
      $athletics += 4;
      $is_calm = TRUE;
    }

    $degree = 'success';
    $d20 = 0;
    $total = 0;
    if (!$is_calm) {
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $athletics;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $swim_dc, $d20);
    }

    $feet_moved = 0;
    $breath_lost = FALSE;
    if ($degree === 'critical_success') {
      $feet_moved = max(10, (int) round($land_speed / 2));
    }
    elseif ($degree === 'success') {
      $feet_moved = max(5, (int) round($land_speed / 4));
    }
    elseif ($degree === 'critical_failure') {
      $breath_lost = TRUE;
      $held_breath = max(0, (int) ($game_state['entities'][$actor_id]['held_breath_rounds'] ?? 0) - 1);
      if (!isset($game_state['entities'][$actor_id])) {
        $game_state['entities'][$actor_id] = [];
      }
      $game_state['entities'][$actor_id]['held_breath_rounds'] = $held_breath;
    }

    if (empty($entity_ref['water_breathing']) && !$has_swim_speed) {
      if (!isset($game_state['entities'][$actor_id])) {
        $game_state['entities'][$actor_id] = [];
      }
      $game_state['entities'][$actor_id]['submerged'] = TRUE;
    }

    if (!isset($game_state['turn']['swim_actions'])) {
      $game_state['turn']['swim_actions'] = [];
    }
    $game_state['turn']['swim_actions'][$actor_id] = ($game_state['turn']['swim_actions'][$actor_id] ?? 0) + 1;

    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'swim',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'swam' => $feet_moved > 0,
        'degree' => $degree,
        'feet_moved' => $feet_moved,
        'breath_lost' => $breath_lost,
      ]
    );

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'swam' => $feet_moved > 0,
        'degree' => $degree,
        'feet_moved' => $feet_moved,
        'breath_lost' => $breath_lost,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('swim', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'feet_moved' => $feet_moved,
          'breath_lost' => $breath_lost,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute trip intent block with legacy side effects.
   */
}
