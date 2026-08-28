<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Core route execution methods part B.
 */
trait EncounterPhaseHandlerRouteExecutionCorePartBTrait {
  protected function routeTripIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $dc = (int) ($params['reflex_dc'] ?? 15);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $target_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $target_id) : NULL;
    $actor_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;

    $damage = 0;
    $damage_packet = NULL;
    $attacker_prone = FALSE;
    $state_effect_packets = [];
    if ($degree === 'critical_success') {
      $damage = $this->numberGenerationService->rollPathfinderDie(6);
      if ($target_participant) {
        $hp_before = is_numeric($target_participant['hp'] ?? NULL) ? (int) $target_participant['hp'] : 0;
        $damage_resolution = $this->hpManager->applyDamage((int) $target_participant['id'], $damage, 'bludgeoning', 'trip', $encounter_id);
        $applied_damage = max(0, (int) ($damage_resolution['hp_damage'] ?? $damage_resolution['final_damage'] ?? $damage));
        $hp_after = max(0, (int) ($damage_resolution['new_hp'] ?? ($hp_before - $applied_damage)));
        if ($applied_damage > 0) {
          $damage_packet = $this->unifiedDamageEngine->buildDamageApplicationPacket(
            (string) $actor_id,
            (string) $target_id,
            'effect',
            $applied_damage,
            'bludgeoning',
            ['trip'],
            [
              'encounter_id' => $encounter_id,
              'action' => 'trip',
              'degree' => $degree,
              'target_hp_before' => $hp_before,
              'target_hp_after' => $hp_after,
            ]
          );
          $damage = $applied_damage;
        }
        $this->conditionManager->applyCondition((int) $target_participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $target_id,
          'condition',
          'prone',
          'applied',
          0,
          ['encounter_id' => $encounter_id, 'action' => 'trip', 'degree' => $degree]
        );
      }
    }
    elseif ($degree === 'success') {
      if ($target_participant) {
        $this->conditionManager->applyCondition((int) $target_participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $target_id,
          'condition',
          'prone',
          'applied',
          0,
          ['encounter_id' => $encounter_id, 'action' => 'trip', 'degree' => $degree]
        );
      }
    }
    elseif ($degree === 'critical_failure') {
      if ($actor_participant) {
        $this->conditionManager->applyCondition((int) $actor_participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $actor_id,
          'condition',
          'prone',
          'applied',
          0,
          ['encounter_id' => $encounter_id, 'action' => 'trip', 'degree' => $degree, 'self_inflicted' => TRUE]
        );
      }
      $attacker_prone = TRUE;
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'trip',
      (string) $actor_id,
      (string) $target_id,
      $params
    );
    $resolution_packets = $state_effect_packets;
    if (is_array($damage_packet)) {
      $resolution_packets[] = $damage_packet;
    }
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $resolution_packets,
      [
        'degree' => $degree,
        'damage' => $damage,
        'attacker_prone' => $attacker_prone,
      ]
    );

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'tripped' => in_array($degree, ['critical_success', 'success'], TRUE),
        'degree' => $degree,
        'damage' => $damage,
        'damage_packet' => $damage_packet,
        'state_effect_packets' => $state_effect_packets,
        'attacker_prone' => $attacker_prone,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('trip', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'damage' => $damage,
          'damage_packet' => $damage_packet,
          'state_effect_packets' => $state_effect_packets,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute disarm intent block with legacy side effects.
   */
  protected function routeDisarmIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $proficiency_rank = (int) ($params['athletics_proficiency_rank'] ?? 0);
    if ($proficiency_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Disarm requires Trained Athletics.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $dc = (int) ($params['reflex_dc'] ?? 15);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $actor_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;

    $item_dropped = FALSE;
    $grip_weakened = FALSE;
    $attacker_flat_footed = FALSE;
    $state_effect_packets = [];

    if ($degree === 'critical_success') {
      $item_dropped = TRUE;
    }
    elseif ($degree === 'success') {
      $grip_weakened = TRUE;
      if (!isset($game_state['grip_weakened'])) {
        $game_state['grip_weakened'] = [];
      }
      $game_state['grip_weakened'][$target_id] = ($game_state['round'] ?? 0) + 1;
    }
    elseif ($degree === 'critical_failure') {
      if ($actor_participant) {
        $this->conditionManager->applyCondition((int) $actor_participant['id'], 'flat_footed', 0, ['type' => 'encounter', 'remaining' => 1], 'disarm', $encounter_id);
        $state_effect_packets[] = $this->unifiedStateEffectEngine->buildStateEffectChangePacket(
          (string) $actor_id,
          (string) $actor_id,
          'condition',
          'flat_footed',
          'applied',
          0,
          ['encounter_id' => $encounter_id, 'action' => 'disarm', 'degree' => $degree, 'self_inflicted' => TRUE]
        );
      }
      $attacker_flat_footed = TRUE;
    }

    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'disarm',
      (string) $actor_id,
      (string) $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $state_effect_packets,
      [
        'degree' => $degree,
        'item_dropped' => $item_dropped,
        'grip_weakened' => $grip_weakened,
        'attacker_flat_footed' => $attacker_flat_footed,
      ]
    );

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'disarmed' => $item_dropped,
        'grip_weakened' => $grip_weakened,
        'degree' => $degree,
        'attacker_flat_footed' => $attacker_flat_footed,
        'state_effect_packets' => $state_effect_packets,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('disarm', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'item_dropped' => $item_dropped,
          'grip_weakened' => $grip_weakened,
          'attacker_flat_footed' => $attacker_flat_footed,
          'state_effect_packets' => $state_effect_packets,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute administer-first-aid intent block with legacy side effects.
   */
  protected function routeAdministerFirstAidIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $actor_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $target_participant = ($encounter && $target_id) ? $this->findEncounterParticipantByEntityId($encounter, $target_id) : NULL;

    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Administer First Aid requires Trained Medicine.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (empty($params['has_healers_tools'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Administer First Aid requires healer\'s tools.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $tools_penalty = !empty($params['is_improvised_tools']) ? -2 : 0;

    $mode = $params['mode'] ?? 'stabilize';
    if (!in_array($mode, ['stabilize', 'stop_bleeding'], TRUE)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => "Unknown First Aid mode '{$mode}'. Use 'stabilize' or 'stop_bleeding'."],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $first_aid_key = $target_id . ':' . $mode;
    $current_round = $game_state['round'] ?? 0;
    if (isset($game_state['first_aid_used'][$first_aid_key]) && $game_state['first_aid_used'][$first_aid_key] === $current_round) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot Administer First Aid on the same condition and target twice in the same round.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $medicine_bonus = (int) ($params['medicine_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $medicine_bonus + $tools_penalty;

    $afa_result = $this->processAdministerFirstAid(
      $target_participant,
      $actor_participant,
      $mode,
      $total,
      $d20,
      $params,
      $encounter_id
    );

    $game_state['first_aid_used'][$first_aid_key] = $current_round;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    $result = array_merge($afa_result, [
      'd20' => $d20,
      'total' => $total,
      'mode' => $mode,
      'tools_penalty' => $tools_penalty,
    ]);
    $degree = strtolower(trim((string) ($afa_result['degree'] ?? '')));
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && !empty($target_id)) {
      $event_type = match ($degree) {
        'critical_success' => 'administer_first_aid_critical_success',
        'success' => 'administer_first_aid_success',
        'failure' => 'administer_first_aid_failure',
        'critical_failure' => 'administer_first_aid_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter administer first aid (%s) for %s (%s)', (string) $mode, (string) $target_id, $degree),
          [
            'target_entity_ref' => (string) $target_id,
            'relationship_type' => 'care',
            'relationship_status' => 'known',
            'degree' => $degree,
            'mode' => (string) $mode,
            'idempotency_key' => sha1(json_encode([
              'encounter_administer_first_aid' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $target_id,
              'degree' => $degree,
              'mode' => (string) $mode,
              'd20' => $d20,
              'total' => $total,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }

    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'administer_first_aid',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'mode' => $mode,
        'degree' => $afa_result['degree'] ?? NULL,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('administer_first_aid', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'mode' => $mode,
          'degree' => $afa_result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute treat-poison intent block with legacy side effects.
   */
  protected function routeTreatPoisonIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Treat Poison requires Trained Medicine.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (empty($params['has_healers_tools'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Treat Poison requires healer\'s tools.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $poison_key = ($target_id ?? $actor_id) . ':poison';
    if (!empty($game_state['poison_treated'][$poison_key])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Can only treat one poison per save for this target.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $med_bonus = (int) ($params['medicine_bonus'] ?? 0);
    $poison_dc = (int) ($params['poison_dc'] ?? 15);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $med_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $poison_dc, $d20);

    $treated = in_array($degree, ['critical_success', 'success'], TRUE);
    if ($treated) {
      $game_state['poison_treated'][$poison_key] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $actor_disposition = $this->resolveActorDispositionService();
    $effective_target = $target_id ?: $actor_id;
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && !empty($effective_target)) {
      $event_type = match ($degree) {
        'critical_success' => 'treat_poison_critical_success',
        'success' => 'treat_poison_success',
        'failure' => 'treat_poison_failure',
        'critical_failure' => 'treat_poison_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter treat poison for %s (%s)', (string) $effective_target, (string) $degree),
          [
            'target_entity_ref' => (string) $effective_target,
            'relationship_type' => 'care',
            'relationship_status' => 'known',
            'degree' => $degree,
            'treated' => $treated,
            'idempotency_key' => sha1(json_encode([
              'encounter_treat_poison' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $effective_target,
              'degree' => $degree,
              'd20' => $d20,
              'total' => $total,
              'dc' => $poison_dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'treat_poison',
      (string) $actor_id,
      $effective_target !== NULL ? (string) $effective_target : NULL,
      $params
    );
    $result = [
      'treated' => $treated,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'dc' => $poison_dc,
    ];
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
        GameEventLogger::buildEvent('treat_poison', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'treated' => $treated,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute battle-medicine intent block with legacy side effects.
   */
  protected function routeBattleMedicineIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Battle Medicine requires Trained Medicine.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (empty($params['has_healers_tools'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Battle Medicine requires healer\'s tools.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $effective_target = $target_id ?? $actor_id;
    $immunity_key = $actor_id . ':' . $effective_target;
    if (!empty($game_state['battle_medicine_immune'][$immunity_key])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Target is immune to this healer\'s Battle Medicine for 1 day.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $dc_table = [1 => 15, 2 => 20, 3 => 30, 4 => 40];
    $hp_bonus = [1 => 0, 2 => 10, 3 => 30, 4 => 50];
    $rank_key = min(4, max(1, $med_rank));
    $dc = (int) ($params['override_dc'] ?? $dc_table[$rank_key]);
    $med_bonus = (int) ($params['medicine_bonus'] ?? 0);
    $item_bonus = !empty($params['is_improvised_tools']) ? -2 : 0;

    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $d8a = $this->numberGenerationService->rollPathfinderDie(8);
    $d8b = $this->numberGenerationService->rollPathfinderDie(8);
    $total = $d20 + $med_bonus + $item_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $healed = 0;
    $damage = 0;
    if ($degree === 'critical_success') {
      $healed = (($d8a + $d8b) + $hp_bonus[$rank_key]) * 2;
    }
    elseif ($degree === 'success') {
      $healed = ($d8a + $d8b) + $hp_bonus[$rank_key];
    }
    elseif ($degree === 'critical_failure') {
      $damage = $this->numberGenerationService->rollPathfinderDie(8);
    }

    $game_state['battle_medicine_immune'][$immunity_key] = TRUE;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && !empty($effective_target)) {
      $event_type = match ($degree) {
        'critical_success' => 'battle_medicine_critical_success',
        'success' => 'battle_medicine_success',
        'failure' => 'battle_medicine_failure',
        'critical_failure' => 'battle_medicine_critical_failure',
        default => '',
      };
      if ($event_type !== '') {
        $actor_disposition->applyDispositionEvent(
          $campaign_id,
          (string) $actor_id,
          $event_type,
          sprintf('Encounter battle medicine for %s (%s)', (string) $effective_target, (string) $degree),
          [
            'target_entity_ref' => (string) $effective_target,
            'relationship_type' => 'care',
            'relationship_status' => 'known',
            'degree' => $degree,
            'healed' => $healed,
            'damage' => $damage,
            'idempotency_key' => sha1(json_encode([
              'encounter_battle_medicine' => TRUE,
              'event_type' => $event_type,
              'campaign_id' => $campaign_id,
              'source' => (string) $actor_id,
              'target' => (string) $effective_target,
              'degree' => $degree,
              'healed' => $healed,
              'damage' => $damage,
              'd20' => $d20,
              'total' => $total,
              'dc' => $dc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }
    }
    $result = [
      'degree' => $degree,
      'healed' => $healed,
      'damage' => $damage,
      'dc' => $dc,
      'd20' => $d20,
      'total' => $total,
      'removes_wounded' => FALSE,
      'mutations' => [],
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'battle_medicine',
      (string) $actor_id,
      $effective_target !== NULL ? (string) $effective_target : NULL,
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
        GameEventLogger::buildEvent('battle_medicine', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'degree' => $degree,
          'healed' => $healed,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $effective_target),
      ],
    ];
  }

  /**
   * Router seam: execute recall-knowledge intent block with legacy side effects.
   */
  protected function routeRecallKnowledgeIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (!empty($params['dc'])) {
      $dc = (int) $params['dc'];
    }
    else {
      $rk_service = new RecallKnowledgeService(new DcAdjustmentService());
      $dc_result = $rk_service->computeDc(
        $params['subject_type'] ?? 'general',
        (int) ($params['level'] ?? 0),
        $params['rarity'] ?? 'common',
        (int) ($params['spell_rank'] ?? 0),
        $params['availability'] ?? 'trained'
      );
      $dc = $dc_result['dc'];
    }

    $skill_used = $params['skill_used'] ?? 'arcana';
    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $skill_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $attempt_key = $actor_id . ':' . ($target_id ?? 'general');
    if (!empty($game_state['recall_knowledge_attempts'][$attempt_key])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot re-attempt Recall Knowledge on the same target without new information.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $game_state['recall_knowledge_attempts'][$attempt_key] = TRUE;

    switch ($degree) {
      case 'critical_success':
        $player_msg = 'You recall detailed information about the subject.';
        $info = $params['known_info'] ?? NULL;
        $bonus_detail = $params['bonus_detail'] ?? NULL;
        break;

      case 'success':
        $player_msg = 'You recall accurate information about the subject.';
        $info = $params['known_info'] ?? NULL;
        $bonus_detail = NULL;
        break;

      case 'failure':
        $player_msg = 'You fail to recall anything useful.';
        $info = NULL;
        $bonus_detail = NULL;
        break;

      case 'critical_failure':
      default:
        $player_msg = 'You recall information about the subject.';
        $info = $params['false_info'] ?? NULL;
        $bonus_detail = NULL;
        break;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $result = [
      'degree' => $degree,
      'skill_used' => $skill_used,
      'dc' => $dc,
      'd20' => $d20,
      'total' => $total,
      'player_facing_message' => $player_msg,
      'info' => $info,
      'bonus_detail' => $bonus_detail,
      'secret' => TRUE,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'recall_knowledge',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'degree' => $degree,
        'skill_used' => $skill_used,
        'secret' => TRUE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('recall_knowledge', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'skill_used' => $skill_used,
          'degree' => $degree,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute hide intent block with legacy side effects.
   */
  protected function routeHideIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (empty($params['has_cover']) && empty($params['has_concealment'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hide requires cover or concealment.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $stealth_bonus = (int) ($params['stealth_bonus'] ?? 0);
    $chameleon_bonus = 0;
    if (($params['heritage'] ?? '') === 'chameleon') {
      $terrain_color = $params['terrain_color_tag'] ?? '';
      $char_color = $params['coloration_tag'] ?? '';
      if ($terrain_color !== '' && $char_color !== '' && $terrain_color === $char_color) {
        $existing_circumstance = (int) ($params['circumstance_bonus'] ?? 0);
        $chameleon_bonus = max(0, 2 - $existing_circumstance);
        $stealth_bonus += $chameleon_bonus;
      }
    }

    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dcs = $params['perception_dcs'] ?? [];
    if (!isset($game_state['visibility'])) {
      $game_state['visibility'] = [];
    }

    $hide_results = [];
    foreach ($observer_ids as $obs_id) {
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $stealth_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $game_state['visibility'][$obs_id][$actor_id] = 'hidden';
      }
      else {
        $game_state['visibility'][$obs_id][$actor_id] = 'observed';
      }
      $hide_results[$obs_id] = ['degree' => $degree, 'visibility' => $game_state['visibility'][$obs_id][$actor_id]];
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'hide',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = [
      'hide_results' => $hide_results,
      'observer_count' => count($observer_ids),
      'secret' => TRUE,
      'chameleon_bonus_applied' => $chameleon_bonus > 0 ? $chameleon_bonus : NULL,
    ];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'observer_count' => count($observer_ids),
        'secret' => TRUE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('hide', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'observer_count' => count($observer_ids),
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute sneak intent block with legacy side effects.
   */
  protected function routeSneakIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $is_hidden_to_any = FALSE;
    $observer_ids = $params['observer_ids'] ?? [];
    foreach ($observer_ids as $obs_id) {
      $visibility = $game_state['visibility'][$obs_id][$actor_id] ?? 'observed';
      if (in_array($visibility, ['hidden', 'undetected', 'unnoticed'], TRUE)) {
        $is_hidden_to_any = TRUE;
        break;
      }
    }
    if (!$is_hidden_to_any && !empty($observer_ids)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Sneak requires Hidden (or Undetected) status. Use Hide first.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $speed = (int) ($params['speed'] ?? 25);
    $half_speed = (int) (floor($speed / 2 / 5) * 5);
    if (empty($params['ends_in_cover']) && empty($params['ends_in_concealment'])) {
      foreach ($observer_ids as $obs_id) {
        $game_state['visibility'][$obs_id][$actor_id] = 'observed';
      }
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
        'sneak',
        (string) $actor_id,
        NULL,
        $params
      );
      $open_result = [
        'sneak_results' => [],
        'became_observed' => TRUE,
        'half_speed' => $half_speed,
        'reason' => 'Ended in open terrain.',
      ];
      $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
        $execution_request,
        [],
        $open_result
      );
      return [
        'result' => array_merge($open_result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ]),
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('sneak', 'encounter', $actor_id, [
            'execution_request' => $execution_request,
            'resolution_envelope' => $resolution_envelope,
            'became_observed' => TRUE,
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $stealth_bonus = (int) ($params['stealth_bonus'] ?? 0);
    $chameleon_bonus = 0;
    if (($params['heritage'] ?? '') === 'chameleon') {
      $terrain_color = $params['terrain_color_tag'] ?? '';
      $char_color = $params['coloration_tag'] ?? '';
      if ($terrain_color !== '' && $char_color !== '' && $terrain_color === $char_color) {
        $existing_circumstance = (int) ($params['circumstance_bonus'] ?? 0);
        $chameleon_bonus = max(0, 2 - $existing_circumstance);
        $stealth_bonus += $chameleon_bonus;
      }
    }
    $perception_dcs = $params['perception_dcs'] ?? [];

    $sneak_results = [];
    foreach ($observer_ids as $obs_id) {
      $current_vis = $game_state['visibility'][$obs_id][$actor_id] ?? 'observed';
      if (!in_array($current_vis, ['hidden', 'undetected', 'unnoticed'], TRUE)) {
        $sneak_results[$obs_id] = ['degree' => NULL, 'visibility' => 'observed'];
        continue;
      }
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $stealth_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (!in_array($degree, ['critical_success', 'success'], TRUE)) {
        $game_state['visibility'][$obs_id][$actor_id] = 'observed';
      }
      $sneak_results[$obs_id] = ['degree' => $degree, 'visibility' => $game_state['visibility'][$obs_id][$actor_id]];
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'sneak',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = [
      'sneak_results' => $sneak_results,
      'half_speed' => $half_speed,
      'secret' => TRUE,
      'chameleon_bonus_applied' => $chameleon_bonus > 0 ? $chameleon_bonus : NULL,
    ];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'observer_count' => count($observer_ids),
        'secret' => TRUE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('sneak', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'observer_count' => count($observer_ids),
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute conceal-object intent block with legacy side effects.
   */
  protected function routeConcealObjectIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $stealth_bonus = (int) ($params['stealth_bonus'] ?? 0);
    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dcs = $params['perception_dcs'] ?? [];
    $item_id = $params['item_id'] ?? NULL;

    if (!isset($game_state['concealed_objects'])) {
      $game_state['concealed_objects'] = [];
    }

    $conceal_results = [];
    $concealed_to_all = TRUE;
    foreach ($observer_ids as $obs_id) {
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $stealth_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $conceal_results[$obs_id] = ['degree' => $degree, 'concealed' => TRUE];
      }
      else {
        $conceal_results[$obs_id] = ['degree' => $degree, 'concealed' => FALSE];
        $concealed_to_all = FALSE;
      }
    }

    if ($item_id && $concealed_to_all && !empty($observer_ids)) {
      $game_state['concealed_objects'][$actor_id . ':' . $item_id] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'conceal_object',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = [
      'concealed_results' => $conceal_results,
      'item_id' => $item_id,
      'secret' => TRUE,
    ];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'item_id' => $item_id,
        'observer_count' => count($observer_ids),
        'secret' => TRUE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('conceal_object', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'item_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute palm-object intent block with legacy side effects.
   */
  protected function routePalmObjectIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dcs = $params['perception_dcs'] ?? [];
    $item_id = $params['item_id'] ?? NULL;

    if (!isset($game_state['palmed_objects'])) {
      $game_state['palmed_objects'] = [];
    }

    $palm_results = [];
    $palmed_from_all = TRUE;
    foreach ($observer_ids as $obs_id) {
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $thievery_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $palm_results[$obs_id] = ['degree' => $degree, 'hidden' => TRUE];
      }
      else {
        $palm_results[$obs_id] = ['degree' => $degree, 'hidden' => FALSE];
        $palmed_from_all = FALSE;
      }
    }

    if ($item_id && $palmed_from_all && !empty($observer_ids)) {
      $game_state['palmed_objects'][$actor_id . ':' . $item_id] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'palm_object',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = [
      'palm_results' => $palm_results,
      'item_id' => $item_id,
      'secret' => TRUE,
    ];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'item_id' => $item_id,
        'observer_count' => count($observer_ids),
        'secret' => TRUE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('palm_object', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'item_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute steal intent block with legacy side effects.
   */
  protected function routeStealIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $target_id = $params['target_id'] ?? NULL;
    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dc = (int) ($params['perception_dc'] ?? 15);
    $item_id = $params['item_id'] ?? NULL;

    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $thievery_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perception_dc, $d20);

    $stolen = FALSE;
    $observers_alerted = [];
    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $stolen = TRUE;
      if ($item_id) {
        if (!isset($game_state['stolen_items'])) {
          $game_state['stolen_items'] = [];
        }
        $game_state['stolen_items'][] = ['actor' => $actor_id, 'from' => $target_id, 'item_id' => $item_id];
      }
    }
    elseif ($degree === 'critical_failure') {
      $observers_alerted = array_merge([$target_id], $observer_ids);
      $observers_alerted = array_filter($observers_alerted);
      if (!isset($game_state['steal_awareness'])) {
        $game_state['steal_awareness'] = [];
      }
      foreach ($observers_alerted as $aware_id) {
        $game_state['steal_awareness'][$aware_id][$actor_id] = TRUE;
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $result = [
      'degree' => $degree,
      'stolen' => $stolen,
      'observers_alerted' => array_values($observers_alerted),
      'secret' => TRUE,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'steal',
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
        GameEventLogger::buildEvent('steal', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'target_id' => $target_id,
          'degree' => $degree,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute disable-device intent block with legacy side effects.
   */
  protected function routeDisableDeviceIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_rank = (int) ($params['thievery_proficiency_rank'] ?? 0);
    if ($thievery_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Disable a Device requires Trained Thievery.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $device_id = $params['device_id'] ?? NULL;
    $dc = (int) ($params['dc'] ?? 20);
    $has_tools = !empty($params['has_thieves_tools']);
    if (!$has_tools) {
      $dc += 5;
    }

    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $thievery_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $disabled = FALSE;
    $triggered = FALSE;
    if (!isset($game_state['device_states'])) {
      $game_state['device_states'] = [];
    }
    if ($degree === 'critical_failure') {
      $triggered = TRUE;
      if ($device_id) {
        $game_state['device_states'][$device_id]['triggered'] = TRUE;
      }
    }
    elseif (in_array($degree, ['critical_success', 'success'], TRUE)) {
      if ($device_id) {
        $successes_needed = (int) ($params['successes_needed'] ?? 1);
        $successes_so_far = (int) ($game_state['device_states'][$device_id]['successes'] ?? 0) + 1;
        $game_state['device_states'][$device_id]['successes'] = $successes_so_far;
        if ($successes_so_far >= $successes_needed) {
          $disabled = TRUE;
          $game_state['device_states'][$device_id]['disabled'] = TRUE;
        }
      }
      else {
        $disabled = TRUE;
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    $result = [
      'degree' => $degree,
      'disabled' => $disabled,
      'triggered' => $triggered,
      'used_tools' => $has_tools,
      'secret' => TRUE,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'disable_device',
      (string) $actor_id,
      $device_id !== NULL ? (string) $device_id : NULL,
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
        GameEventLogger::buildEvent('disable_device', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'device_id' => $device_id,
          'degree' => $degree,
          'triggered' => $triggered,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute pick-lock intent block with legacy side effects.
   */
  protected function routePickLockIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_rank = (int) ($params['thievery_proficiency_rank'] ?? 0);
    if ($thievery_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Pick a Lock requires Trained Thievery.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $lock_id = $params['lock_id'] ?? NULL;
    if ($lock_id && !empty($game_state['lock_states'][$lock_id]['jammed'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'This lock is jammed and cannot be picked until repaired.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $lock_quality_dcs = ['simple' => 15, 'average' => 20, 'good' => 25, 'superior' => 30];
    $lock_quality = $params['lock_quality'] ?? 'average';
    $dc = $lock_quality_dcs[$lock_quality] ?? 20;
    $has_tools = !empty($params['has_thieves_tools']);
    if (!$has_tools) {
      $dc += 5;
    }

    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $thievery_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $unlocked = FALSE;
    $jammed = FALSE;
    if (!isset($game_state['lock_states'])) {
      $game_state['lock_states'] = [];
    }
    if ($degree === 'critical_failure') {
      $jammed = TRUE;
      if ($lock_id) {
        $game_state['lock_states'][$lock_id]['jammed'] = TRUE;
      }
    }
    elseif (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $unlocked = TRUE;
      if ($lock_id) {
        $game_state['lock_states'][$lock_id]['locked'] = FALSE;
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    $result = [
      'degree' => $degree,
      'unlocked' => $unlocked,
      'jammed' => $jammed,
      'lock_quality' => $lock_quality,
      'used_tools' => $has_tools,
      'secret' => TRUE,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'pick_lock',
      (string) $actor_id,
      $lock_id !== NULL ? (string) $lock_id : NULL,
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
        GameEventLogger::buildEvent('pick_lock', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'lock_id' => $lock_id,
          'lock_quality' => $lock_quality,
          'degree' => $degree,
          'jammed' => $jammed,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute disable-hazard intent block with legacy side effects.
   */
  protected function routeDisableHazardIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $hazard_id = $params['hazard_id'] ?? NULL;
    if (!$hazard_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'hazard_id required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
    if ($hazard_ref === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hazard not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $skill_rank = (int) ($params['skill_proficiency_rank'] ?? 0);
    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
    $disable_result = $this->hazardService->disableHazard($hazard_ref, $skill_bonus, $skill_rank);
    $xp = 0;
    if (!empty($disable_result['disabled'])) {
      $xp = $this->hazardService->awardHazardXp($game_state, $hazard_ref, (int) ($game_state['party_level'] ?? 1));
    }

    $phase_transition = NULL;
    if (!empty($disable_result['triggered']) && (($hazard_ref['complexity'] ?? 'simple') === 'complex')) {
      $initiative = $this->hazardService->rollComplexHazardInitiative($hazard_ref);
      $phase_transition = ['type' => 'encounter_continue', 'hazard_initiative' => $initiative, 'hazard_id' => $hazard_id];
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'disable_hazard',
      (string) $actor_id,
      $hazard_id !== NULL ? (string) $hazard_id : NULL,
      $params
    );
    $result = array_merge($disable_result, ['xp_awarded' => $xp, 'hazard_id' => $hazard_id]);
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'hazard_id' => $hazard_id,
        'degree' => $disable_result['degree'] ?? NULL,
        'disabled' => $disable_result['disabled'] ?? FALSE,
        'triggered' => $disable_result['triggered'] ?? FALSE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('disable_hazard', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'hazard_id' => $hazard_id,
          'degree' => $disable_result['degree'],
          'disabled' => $disable_result['disabled'],
          'triggered' => $disable_result['triggered'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
      'phase_transition' => $phase_transition,
    ];
  }

  /**
   * Router seam: execute attack-hazard intent block with legacy side effects.
   */
  protected function routeAttackHazardIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $hazard_id = $params['hazard_id'] ?? NULL;
    if (!$hazard_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'hazard_id required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
    if ($hazard_ref === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hazard not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $damage_amount = (int) ($params['damage'] ?? 0);
    $damage_result = $this->hazardService->applyDamageToHazard($hazard_ref, $damage_amount);
    $xp = 0;
    if (!empty($damage_result['disabled'])) {
      $xp = $this->hazardService->awardHazardXp($game_state, $hazard_ref, (int) ($game_state['party_level'] ?? 1));
    }

    $phase_transition = NULL;
    if (!empty($damage_result['triggered']) && (($hazard_ref['complexity'] ?? 'simple') === 'complex')) {
      $initiative = $this->hazardService->rollComplexHazardInitiative($hazard_ref);
      $phase_transition = ['type' => 'encounter_continue', 'hazard_initiative' => $initiative, 'hazard_id' => $hazard_id];
    }

    $action_cost = (int) ($params['action_cost'] ?? 1);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'attack_hazard',
      (string) $actor_id,
      (string) $hazard_id,
      $params
    );
    $result = array_merge($damage_result, ['xp_awarded' => $xp, 'hazard_id' => $hazard_id]);
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'hazard_id' => $hazard_id,
        'damage' => $damage_amount,
        'triggered' => $damage_result['triggered'] ?? FALSE,
        'disabled' => $damage_result['disabled'] ?? FALSE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('attack_hazard', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'hazard_id' => $hazard_id,
          'damage' => $damage_amount,
          'triggered' => $damage_result['triggered'] ?? FALSE,
          'disabled' => $damage_result['disabled'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
      'phase_transition' => $phase_transition,
    ];
  }

  /**
   * Router seam: execute counteract-hazard intent block with legacy side effects.
   */
  protected function routeCounteractHazardIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $hazard_id = $params['hazard_id'] ?? NULL;
    if (!$hazard_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'hazard_id required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
    if ($hazard_ref === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hazard not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $counteract_level = (int) ($params['counteract_level'] ?? 0);
    $counteract_bonus = (int) ($params['counteract_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $counteract_bonus;
    $counteract_result = $this->hazardService->counteractMagicalHazard($hazard_ref, $counteract_level, $total, $d20);
    $xp = 0;
    if (!empty($counteract_result['counteracted'])) {
      $xp = $this->hazardService->awardHazardXp($game_state, $hazard_ref, (int) ($game_state['party_level'] ?? 1));
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'counteract_hazard',
      (string) $actor_id,
      (string) $hazard_id,
      $params
    );
    $result = array_merge($counteract_result, ['xp_awarded' => $xp, 'hazard_id' => $hazard_id]);
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'hazard_id' => $hazard_id,
        'degree' => $counteract_result['degree'] ?? NULL,
        'counteracted' => $counteract_result['counteracted'] ?? FALSE,
      ]
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('counteract_hazard', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'hazard_id' => $hazard_id,
          'degree' => $counteract_result['degree'],
          'counteracted' => $counteract_result['counteracted'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute activate-item intent block with legacy side effects.
   */
  protected function routeActivateItemIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $item_id = $params['item_instance_id'] ?? NULL;
    $item_data = $params['item_data'] ?? [];
    if (!$item_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'activate_item requires params.item_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $char_state = $params['char_state'] ?? [];
    $activate_result = $this->magicItemService->activateItem($actor_id, $item_id, $item_data, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($activate_result['actions_cost'] ?? 1));
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'activate_item',
      (string) $actor_id,
      (string) $item_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['item_instance_id' => $item_id, 'success' => $activate_result['success'] ?? FALSE]
    );
    return [
      'result' => array_merge((array) $activate_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('activate_item', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'item_instance_id' => $item_id,
          'success' => $activate_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute sustain-activation intent block with legacy side effects.
   */
  protected function routeSustainActivationIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $item_id = $params['item_instance_id'] ?? NULL;
    if (!$item_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'sustain_activation requires params.item_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $sustain_result = $this->magicItemService->sustainActivation($actor_id, $item_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'sustain_activation',
      (string) $actor_id,
      (string) $item_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['item_instance_id' => $item_id, 'success' => $sustain_result['success'] ?? FALSE]
    );
    return [
      'result' => array_merge((array) $sustain_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('sustain_activation', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'item_instance_id' => $item_id,
          'success' => $sustain_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute dismiss-activation intent block with legacy side effects.
   */
  protected function routeDismissActivationIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $item_id = $params['item_instance_id'] ?? NULL;
    if (!$item_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'dismiss_activation requires params.item_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $dismiss_result = $this->magicItemService->dismissActivation($actor_id, $item_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'dismiss_activation',
      (string) $actor_id,
      (string) $item_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['item_instance_id' => $item_id, 'success' => $dismiss_result['success'] ?? TRUE]
    );
    return [
      'result' => array_merge((array) $dismiss_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('dismiss_activation', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'item_instance_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute sustain-spell intent block with legacy side effects.
   */
  protected function routeSustainSpellIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $spell_id = $params['spell_id'] ?? NULL;
    if (!$spell_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'sustain_spell requires params.spell_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $current_round = (int) ($game_state['round'] ?? 1);
    $sustained = $game_state['spells']['sustained'][$actor_id][$spell_id] ?? NULL;
    if ($sustained === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => "Spell '{$spell_id}' is not currently sustained by this caster."],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $rounds_sustained = $current_round - (int) ($sustained['start_round'] ?? $current_round);
    if ($rounds_sustained >= MagicItemService::SUSTAIN_FATIGUE_ROUNDS) {
      unset($game_state['spells']['sustained'][$actor_id][$spell_id]);
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
        'sustain_spell',
        (string) $actor_id,
        (string) $spell_id,
        $params
      );
      $fatigue_result = ['sustained' => FALSE, 'ended' => TRUE, 'reason' => 'exceeded_100_rounds', 'fatigue_applied' => TRUE];
      $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
        $execution_request,
        [],
        $fatigue_result
      );
      return [
        'result' => array_merge($fatigue_result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ]),
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('sustain_spell', 'encounter', $actor_id, [
            'execution_request' => $execution_request,
            'resolution_envelope' => $resolution_envelope,
            'spell_id' => $spell_id,
            'ended' => TRUE,
            'reason' => 'exceeded_100_rounds',
            'round' => $current_round,
          ]),
        ],
      ];
    }

    $game_state['spells']['sustained'][$actor_id][$spell_id]['last_sustained_round'] = $current_round;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'sustain_spell',
      (string) $actor_id,
      (string) $spell_id,
      $params
    );
    $result = ['sustained' => TRUE, 'rounds_sustained' => $rounds_sustained + 1];
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
        GameEventLogger::buildEvent('sustain_spell', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'spell_id' => $spell_id,
          'rounds_sustained' => $rounds_sustained + 1,
          'round' => $current_round,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute dismiss-spell intent block with legacy side effects.
   */
  protected function routeDismissSpellIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $spell_id = $params['spell_id'] ?? NULL;
    if (!$spell_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'dismiss_spell requires params.spell_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    unset($game_state['spells']['sustained'][$actor_id][$spell_id]);
    unset($game_state['spells']['durations'][$actor_id][$spell_id]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'dismiss_spell',
      (string) $actor_id,
      (string) $spell_id,
      $params
    );
    $result = ['dismissed' => TRUE, 'spell_id' => $spell_id];
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
        GameEventLogger::buildEvent('dismiss_spell', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'spell_id' => $spell_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute cast-from-scroll intent block with legacy side effects.
   */
  protected function routeCastFromScrollIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $scroll_id = $params['scroll_instance_id'] ?? NULL;
    $scroll_data = $params['scroll_data'] ?? [];
    if (!$scroll_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'cast_from_scroll requires params.scroll_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $char_state = $params['char_state'] ?? [];
    $scroll_result = $this->magicItemService->castFromScroll($scroll_data, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($scroll_result['actions_cost'] ?? 2));
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'cast_from_scroll',
      (string) $actor_id,
      (string) $scroll_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['scroll_instance_id' => $scroll_id, 'success' => $scroll_result['success'] ?? FALSE]
    );
    return [
      'result' => array_merge((array) $scroll_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('cast_from_scroll', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'scroll_instance_id' => $scroll_id,
          'success' => $scroll_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute cast-from-staff intent block with legacy side effects.
   */
  protected function routeCastFromStaffIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $staff_id = $params['staff_instance_id'] ?? NULL;
    $staff_data = $params['staff_data'] ?? [];
    $spell_level = (int) ($params['spell_level'] ?? 1);
    if (!$staff_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'cast_from_staff requires params.staff_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $spell_id = $params['spell_id'] ?? '';
    $char_state = $params['char_state'] ?? [];
    $staff_result = $this->magicItemService->castFromStaff($staff_id, $actor_id, $staff_data, $spell_id, $spell_level, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($staff_result['actions_cost'] ?? 2));
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'cast_from_staff',
      (string) $actor_id,
      (string) $staff_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['staff_instance_id' => $staff_id, 'spell_level' => $spell_level, 'success' => $staff_result['success'] ?? FALSE]
    );
    return [
      'result' => array_merge((array) $staff_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('cast_from_staff', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'staff_instance_id' => $staff_id,
          'spell_level' => $spell_level,
          'success' => $staff_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute cast-from-wand intent block with legacy side effects.
   */
  protected function routeCastFromWandIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $wand_id = $params['wand_instance_id'] ?? NULL;
    $wand_data = $params['wand_data'] ?? [];
    if (!$wand_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'cast_from_wand requires params.wand_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $char_state = $params['char_state'] ?? [];
    $wand_result = $this->magicItemService->castFromWand($wand_id, $wand_data, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($wand_result['actions_cost'] ?? 2));
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'cast_from_wand',
      (string) $actor_id,
      (string) $wand_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['wand_instance_id' => $wand_id, 'success' => $wand_result['success'] ?? FALSE]
    );
    return [
      'result' => array_merge((array) $wand_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('cast_from_wand', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'wand_instance_id' => $wand_id,
          'success' => $wand_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute overcharge-wand intent block with legacy side effects.
   */
  protected function routeOverchargeWandIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $wand_id = $params['wand_instance_id'] ?? NULL;
    if (!$wand_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'overcharge_wand requires params.wand_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $overcharge_result = $this->magicItemService->overchargeWand($wand_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($overcharge_result['actions_cost'] ?? 2));
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'overcharge_wand',
      (string) $actor_id,
      (string) $wand_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['wand_instance_id' => $wand_id, 'success' => $overcharge_result['success'] ?? FALSE]
    );
    return [
      'result' => array_merge((array) $overcharge_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('overcharge_wand', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'wand_instance_id' => $wand_id,
          'success' => $overcharge_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute activate-talisman intent block with legacy side effects.
   */
  protected function routeActivateTalismanIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $talisman_id = $params['talisman_instance_id'] ?? NULL;
    $host_item_id = $params['host_item_instance_id'] ?? $talisman_id;
    if (!$talisman_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'activate_talisman requires params.talisman_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $talisman_result = $this->magicItemService->activateTalisman($host_item_id, $actor_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($talisman_result['actions_cost'] ?? 1));
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'activate_talisman',
      (string) $actor_id,
      (string) $talisman_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['talisman_instance_id' => $talisman_id, 'success' => $talisman_result['success'] ?? FALSE]
    );
    return [
      'result' => array_merge((array) $talisman_result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('activate_talisman', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'talisman_instance_id' => $talisman_id,
          'success' => $talisman_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute burrow intent block with legacy side effects.
   */
  protected function routeBurrowIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
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
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $burrow_speed = (int) ($entity_data['burrow_speed'] ?? 0);
    if ($burrow_speed <= 0) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'No burrow Speed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $params['movement_type'] = 'burrow';
    $burrow_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $burrow_result['mutations'] ?? [];
    $entity_data['underground'] = TRUE;
    if (!empty($entity_data['creates_tunnel'])) {
      $entity_data['tunnel_hex'] = $params['to_hex'] ?? NULL;
    }
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $movement_execution_request = $this->requireOptionalContractPayload(
      $burrow_result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'burrow.movement.execution_request'
    );
    $movement_packet = $this->requireOptionalContractPayload(
      $burrow_result['movement_packet'] ?? NULL,
      'movement_resolution',
      CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION,
      'burrow.movement.movement_packet'
    );
    $movement_resolution_envelope = $this->requireOptionalContractPayload(
      $burrow_result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'burrow.movement.resolution_envelope'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'burrow',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['burrowed' => TRUE, 'to_hex' => $params['to_hex'] ?? NULL];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      is_array($movement_packet) ? [$movement_packet] : [],
      $result
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'movement_execution_request' => $movement_execution_request,
        'movement_packet' => $movement_packet,
        'movement_resolution_envelope' => $movement_resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('burrow', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'movement_execution_request' => $movement_execution_request,
          'movement_packet' => $movement_packet,
          'movement_resolution_envelope' => $movement_resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute fly intent block with legacy side effects.
   */
  protected function routeFlyIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
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
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $fly_speed = (int) ($entity_data['fly_speed'] ?? 0);
    if ($fly_speed <= 0) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'No fly Speed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $fly_distance = (int) ($params['distance'] ?? 0);
    if ($fly_distance === 0) {
      $entity_data['airborne'] = TRUE;
      $entity_data['fly_used_this_turn'] = TRUE;
      $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
        'fly',
        (string) $actor_id,
        NULL,
        $params
      );
      $result = ['hovered' => TRUE];
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
          GameEventLogger::buildEvent('fly', 'encounter', $actor_id, [
            'execution_request' => $execution_request,
            'resolution_envelope' => $resolution_envelope,
            'hover' => TRUE,
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }
    if (!empty($params['upward'])) {
      $params['movement_type'] = 'fly';
      $params['upward_movement'] = TRUE;
    }
    $params['movement_type'] = 'fly';
    $fly_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $fly_result['mutations'] ?? [];
    $entity_data['airborne'] = TRUE;
    $entity_data['fly_used_this_turn'] = TRUE;
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['fly_used'] = TRUE;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $movement_execution_request = $this->requireOptionalContractPayload(
      $fly_result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'fly.movement.execution_request'
    );
    $movement_packet = $this->requireOptionalContractPayload(
      $fly_result['movement_packet'] ?? NULL,
      'movement_resolution',
      CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION,
      'fly.movement.movement_packet'
    );
    $movement_resolution_envelope = $this->requireOptionalContractPayload(
      $fly_result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'fly.movement.resolution_envelope'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'fly',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['flew' => TRUE, 'to_hex' => $params['to_hex'] ?? NULL];
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      is_array($movement_packet) ? [$movement_packet] : [],
      $result
    );
    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'movement_execution_request' => $movement_execution_request,
        'movement_packet' => $movement_packet,
        'movement_resolution_envelope' => $movement_resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('fly', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'to' => $params['to_hex'] ?? NULL,
          'movement_execution_request' => $movement_execution_request,
          'movement_packet' => $movement_packet,
          'movement_resolution_envelope' => $movement_resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute mount intent block with legacy side effects.
   */
  protected function routeMountIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $mount_participant = $encounter && $target_id ? $this->findEncounterParticipantByEntityId($encounter, $target_id) : NULL;
    if (!$participant || !$mount_participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant or mount not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $dist = $this->movementResolver ? $this->movementResolver->hexDistance(
      ['q' => (int) ($participant['position_q'] ?? 0), 'r' => (int) ($participant['position_r'] ?? 0)],
      ['q' => (int) ($mount_participant['position_q'] ?? 0), 'r' => (int) ($mount_participant['position_r'] ?? 0)]
    ) : 1;
    if ($dist > 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Mount must be adjacent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $acrobatics_bonus = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $mount_roll = $this->numberGenerationService->rollPathfinderDie(20);
    $mount_total = $mount_roll + $acrobatics_bonus;
    if ($mount_total < 15) {
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Acrobatics check failed (DC 15).', 'roll' => $mount_roll, 'bonus' => $acrobatics_bonus, 'total' => $mount_total],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $actor_entity = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $actor_entity['mounted_on'] = $target_id;
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($actor_entity)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'mount',
      (string) $actor_id,
      $target_id,
      $params
    );
    $result = ['mounted' => TRUE, 'mount_id' => $target_id, 'roll' => $mount_roll, 'total' => $mount_total];
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
        GameEventLogger::buildEvent('mount', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'mount' => $target_id,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute dismount intent block with legacy side effects.
   */
  protected function routeDismountIntentExecution(
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
    $actor_entity = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $actor_entity['mounted_on'] = NULL;
    $dismount_to = $params['to_hex'] ?? NULL;
    $update = ['entity_ref' => json_encode($actor_entity)];
    if ($dismount_to) {
      $update['position_q'] = (int) ($dismount_to['q'] ?? $participant['position_q'] ?? 0);
      $update['position_r'] = (int) ($dismount_to['r'] ?? $participant['position_r'] ?? 0);
    }
    $this->encounterStore->updateParticipant((int) $participant['id'], $update);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'dismount',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['dismounted' => TRUE];
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
        GameEventLogger::buildEvent('dismount', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute raise-shield intent block with legacy side effects.
   */
  protected function routeRaiseShieldIntentExecution(
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
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
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
    if (!empty($shield['broken'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Shield is broken.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_data['shield_raised'] = TRUE;
    $entity_data['shield_raised_ac_bonus'] = (int) ($shield['ac_bonus'] ?? 0);
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'raise_shield',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['shield_raised' => TRUE, 'ac_bonus' => $entity_data['shield_raised_ac_bonus']];
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
        GameEventLogger::buildEvent('raise_shield', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'ac_bonus' => $entity_data['shield_raised_ac_bonus'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute avert-gaze intent block with legacy side effects.
   */
  protected function routeAvertGazeIntentExecution(
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
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $entity_data['avert_gaze_active'] = TRUE;
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'avert_gaze',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['avert_gaze' => TRUE];
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
        GameEventLogger::buildEvent('avert_gaze', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute point-out intent block with legacy side effects.
   */
  protected function routePointOutIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    if (!$target_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'target required for point_out.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if ($encounter) {
      foreach ($encounter['participants'] ?? [] as $ally_participant) {
        $ally_eid = $ally_participant['entity_id'] ?? '';
        if ($ally_eid === $actor_id) {
          continue;
        }
        $ally_entity_data = !empty($ally_participant['entity_ref']) ? json_decode($ally_participant['entity_ref'], TRUE) : [];
        $ally_attacker_id = $ally_entity_data['entity_id'] ?? $ally_eid;
        $target_participant = $this->findEncounterParticipantByEntityId($encounter, $target_id);
        if ($target_participant) {
          $target_entity_data = !empty($target_participant['entity_ref']) ? json_decode($target_participant['entity_ref'], TRUE) : [];
          $current_state = $target_entity_data['detection_states'][$ally_attacker_id] ?? 'observed';
          if ($current_state === 'undetected' || $current_state === 'unnoticed') {
            $target_entity_data['detection_states'][$ally_attacker_id] = 'hidden';
            $this->encounterStore->updateParticipant((int) $target_participant['id'], ['entity_ref' => json_encode($target_entity_data)]);
          }
        }
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService && $campaign_id > 0 && !empty($actor_id) && !empty($target_id)) {
      $actor_disposition->applyDispositionEvent(
        $campaign_id,
        (string) $actor_id,
        'point_out_success',
        sprintf('Encounter point out for allies against %s', (string) $target_id),
        [
          'target_entity_ref' => (string) $target_id,
          'relationship_type' => 'combat',
          'relationship_status' => 'known',
          'idempotency_key' => sha1(json_encode([
            'encounter_point_out' => TRUE,
            'campaign_id' => $campaign_id,
            'source' => (string) $actor_id,
            'target' => (string) $target_id,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        ]
      );
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'point_out',
      (string) $actor_id,
      (string) $target_id,
      $params
    );
    $result = ['pointed_out' => TRUE, 'target' => $target_id];
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
        GameEventLogger::buildEvent('point_out', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'target' => $target_id,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute minor-color-shift intent block with legacy side effects.
   */
  protected function routeMinorColorShiftIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (($params['heritage'] ?? '') !== 'chameleon') {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Minor Color Shift requires Chameleon Gnome heritage.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $terrain_color = trim($params['terrain_color_tag'] ?? '');
    if ($terrain_color === '') {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'terrain_color_tag is required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'minor_color_shift',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['coloration_tag' => $terrain_color, 'action_cost' => 1];
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
      'mutations' => [['type' => 'char_state', 'key' => 'coloration_tag', 'value' => $terrain_color]],
      'events' => [
        GameEventLogger::buildEvent('minor_color_shift', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'new_coloration' => $terrain_color,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute consume-item intent block with legacy side effects.
   */
  protected function routeConsumeItemIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $character_id = $params['character_id'] ?? $params['characterId'] ?? NULL;
    $item = is_array($params['item'] ?? NULL) ? $params['item'] : [];
    $item_name = trim((string) ($item['name'] ?? $item['id'] ?? $item['item_id'] ?? 'consumable'));
    if (!$character_id || $item_name === '') {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'consume_item requires params.character_id and params.item.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $action_cost = $this->getActionCost('consume_item', $params);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

    try {
      $inventory = $this->characterStateService->updateInventory(
        (string) $character_id,
        'consume',
        $item,
        $campaign_id > 0 ? $campaign_id : NULL,
        $actor_id
      );
      $effects = $this->characterStateService->applyConsumableEffects(
        (string) $character_id,
        $item,
        $campaign_id > 0 ? $campaign_id : NULL,
        $actor_id
      );
    }
    catch (\InvalidArgumentException $exception) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => $exception->getMessage()],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    if (!empty($effects['focus_points']) || !empty($effects['spell_slots'])) {
      $this->syncCanonicalSpellcastingProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data);
    }
    if (!empty($effects['nutrition_days']) || !empty($effects['hydration_days'])) {
      $this->syncCanonicalSurvivalProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data);
    }

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $result = [
      'summary' => sprintf('%s uses %s.', $actor_name, $item_name),
      'item_name' => $item_name,
      'effects' => $effects,
      'inventory' => $inventory,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'consume_item',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['item_name' => $item_name, 'action_cost' => $action_cost]
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'events' => [
        GameEventLogger::buildEvent('consume_item', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'item_name' => $item_name,
          'effects' => $effects,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute declare-metamagic intent block with legacy side effects.
   */
  protected function routeDeclareMetamagicIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $metamagic_id = $params['metamagic_id'] ?? NULL;
    if (!$metamagic_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'declare_metamagic requires params.metamagic_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $game_state['turn']['metamagic_pending'][$actor_id] = $metamagic_id;
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'declare_metamagic',
      (string) $actor_id,
      NULL,
      $params
    );
    $result = ['declared' => TRUE, 'metamagic_id' => $metamagic_id];
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
      'events' => [
        GameEventLogger::buildEvent('declare_metamagic', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'metamagic_id' => $metamagic_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute interact intent block with legacy side effects.
   */
  protected function routeInteractIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $character_id = $this->resolveActorCharacterId($actor_id, $dungeon_data, $params);
    $events = [];
    $mutations = [];

    if (!$encounter_id || $this->isRoomSceneMode($game_state)) {
      $result = ['interacted' => TRUE];
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
        'interact',
        (string) $actor_id,
        $target_id,
        $params
      );
      $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
        $execution_request,
        [],
        ['interacted' => TRUE, 'mode' => 'room_scene']
      );
      $events[] = GameEventLogger::buildEvent('interact', 'encounter', $actor_id, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'target' => $target_id,
        'round' => $game_state['round'] ?? NULL,
      ]);

      $quest_touchpoint_result = $this->applyInteractQuestTouchpoint(
        $campaign_id,
        $character_id,
        $target_id,
        $game_state,
        $dungeon_data
      );
      if ($quest_touchpoint_result !== NULL) {
        if (empty($quest_touchpoint_result['success'])) {
          return [
            'abort_response' => [
              'success' => FALSE,
              'result' => [
                'error' => (string) ($quest_touchpoint_result['error'] ?? 'Failed to apply quest touchpoint.'),
                'quest_touchpoint' => $quest_touchpoint_result,
              ],
              'mutations' => [],
              'events' => $events,
              'phase_transition' => NULL,
              'narration' => NULL,
            ],
          ];
        }
        $result['quest_touchpoint'] = $quest_touchpoint_result;
      }

      return [
        'result' => array_merge($result, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
        ]),
        'mutations' => $mutations,
        'events' => $events,
      ];
    }

    $result = $this->processInteract($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $result['mutations'] ?? [];

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'interact',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      ['interacted' => TRUE, 'interaction' => $params['interaction_type'] ?? 'generic']
    );
    $events[] = GameEventLogger::buildEvent('interact', 'encounter', $actor_id, [
      'execution_request' => $execution_request,
      'resolution_envelope' => $resolution_envelope,
      'target' => $target_id,
      'interaction' => $params['interaction_type'] ?? 'generic',
      'round' => $game_state['round'] ?? NULL,
    ], NULL, $target_id);

    $quest_touchpoint_result = $this->applyInteractQuestTouchpoint(
      $campaign_id,
      $character_id,
      $target_id,
      $game_state,
      $dungeon_data
    );
    if ($quest_touchpoint_result !== NULL) {
      if (empty($quest_touchpoint_result['success'])) {
        return [
          'abort_response' => [
            'success' => FALSE,
            'result' => [
              'error' => (string) ($quest_touchpoint_result['error'] ?? 'Failed to apply quest touchpoint.'),
              'quest_touchpoint' => $quest_touchpoint_result,
            ],
            'mutations' => [],
            'events' => $events,
            'phase_transition' => NULL,
            'narration' => NULL,
          ],
        ];
      }
      $result['quest_touchpoint'] = $quest_touchpoint_result;
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => $events,
    ];
  }

  /**
   * Router seam: execute talk intent block with legacy side effects.
   */
  protected function routeTalkIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $character_id = $this->resolveActorCharacterId($actor_id, $dungeon_data, $params);
    $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
    $params['_encounter_turn_ctx'] = $turn_ctx;

    $result = $this->processTalk($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    if (!empty($result['error'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => $result,
          'mutations' => $result['mutations'] ?? [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $mutations = $result['mutations'] ?? [];
    $narration = $result['narration'] ?? NULL;

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    if ($this->isRoomSceneMode($game_state)) {
      $result['remaining_turn_prompt'] = $this->buildRemainingRoomSceneActionPrompt($actor_id, $game_state, $dungeon_data);
      if (is_array($result['remaining_turn_prompt'])) {
        $result['turn_logs'] = array_values(array_merge(
          is_array($result['turn_logs'] ?? NULL) ? $result['turn_logs'] : [],
          [$result['remaining_turn_prompt']]
        ));
      }
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'talk',
      (string) $actor_id,
      $target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'message' => $result['message'] ?? '',
        'gm_response_generated' => !empty($result['gm_response']),
      ]
    );

    $events = [
      GameEventLogger::buildEvent('talk', 'encounter', $actor_id, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'message' => $result['message'] ?? '',
        'disposition_change' => is_array($result['disposition_change'] ?? NULL) ? $result['disposition_change'] : NULL,
        'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
        'turn_index' => $turn_ctx['turn_index_raw'] ?? ($game_state['turn']['index'] ?? NULL),
        'actor_name' => $turn_ctx['actor_name'] ?? NULL,
        'gm_response_generated' => !empty($result['gm_response']),
        'state_diff_present' => !empty($result['state_diff']),
      ], $narration, $target_id),
    ];

    $quest_touchpoint_result = $this->applyInteractQuestTouchpoint(
      $campaign_id,
      $character_id,
      $target_id,
      $game_state,
      $dungeon_data
    );
    if ($quest_touchpoint_result !== NULL) {
      if (empty($quest_touchpoint_result['success'])) {
        return [
          'abort_response' => [
            'success' => FALSE,
            'result' => [
              'error' => (string) ($quest_touchpoint_result['error'] ?? 'Failed to apply quest touchpoint.'),
              'quest_touchpoint' => $quest_touchpoint_result,
            ],
            'mutations' => [],
            'events' => $events,
            'phase_transition' => NULL,
            'narration' => NULL,
          ],
        ];
      }
      $result['quest_touchpoint'] = $quest_touchpoint_result;
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
    ];
  }

  /**
   * Router seam: execute skill intent block with legacy side effects.
   */
  protected function routeSkillIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $skill_name = trim((string) ($params['skill_name'] ?? $params['skill'] ?? 'Skill'));
    $skill_bonus = NULL;
    if (isset($params['skill_bonus'])) {
      $skill_bonus = (int) $params['skill_bonus'];
    }
    elseif (isset($params['skill_modifier'])) {
      $skill_bonus = (int) $params['skill_modifier'];
    }

    $action_cost = $this->getActionCost('skill', $params);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $result = [
      'summary' => sprintf(
        '%s uses %s%s.',
        $actor_name,
        $skill_name,
        $skill_bonus !== NULL ? sprintf(' (%+d)', $skill_bonus) : ''
      ),
      'skill_name' => $skill_name,
      'skill_bonus' => $skill_bonus,
    ];
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'skill',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'skill_name' => $skill_name,
        'skill_bonus' => $skill_bonus,
        'action_cost' => $action_cost,
      ]
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'events' => [
        GameEventLogger::buildEvent('skill', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'skill_name' => $skill_name,
          'skill_bonus' => $skill_bonus,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute feat intent block with legacy side effects.
   */
  protected function routeFeatIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $feat_name = trim((string) ($params['feat_name'] ?? $params['featName'] ?? $params['option_label'] ?? 'Feat action'));
    $feat_id = $params['feat_id'] ?? $params['featId'] ?? $params['option_id'] ?? $params['optionId'] ?? NULL;

    $action_cost = $this->getActionCost('feat', $params);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $result = [
      'summary' => sprintf('%s uses %s.', $actor_name, $feat_name),
      'feat_name' => $feat_name,
      'feat_id' => $feat_id,
    ];
    $stance_transition = $this->applyFeatStanceRuntimeTransition(
      $actor_id,
      $feat_id,
      $params,
      $campaign_id,
      $dungeon_data
    );
    if (is_array($stance_transition)) {
      $result['stance_transition'] = $stance_transition;
    }
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'feat',
      (string) $actor_id,
      $feat_id !== NULL ? (string) $feat_id : NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      [],
      [
        'feat_name' => $feat_name,
        'feat_id' => $feat_id,
        'action_cost' => $action_cost,
      ]
    );

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
      ]),
      'events' => [
        GameEventLogger::buildEvent('feat', 'encounter', $actor_id, [
          'execution_request' => $execution_request,
          'resolution_envelope' => $resolution_envelope,
          'feat_name' => $feat_name,
          'feat_id' => $feat_id,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
          'stance_transition' => $stance_transition,
        ]),
      ],
    ];
  }

  /**
   * Apply stance runtime transition for feat actions when explicitly requested.
   *
   * @return array<string,mixed>|null
   */
  protected function applyFeatStanceRuntimeTransition(
    ?string $actor_id,
    mixed $feat_id,
    array $params,
    int $campaign_id,
    array &$dungeon_data
  ): ?array {
    if (!$this->stanceRuntimeService || !$actor_id || $campaign_id <= 0) {
      return NULL;
    }

    $stance_transition = is_array($params['stance_transition'] ?? NULL)
      ? $params['stance_transition']
      : (is_array($params['stanceTransition'] ?? NULL) ? $params['stanceTransition'] : []);
    $legacy_stance_transition = $params['stanceTransition'] ?? NULL;
    $legacy_stance_action = (is_scalar($legacy_stance_transition) || $legacy_stance_transition === NULL)
      ? $legacy_stance_transition
      : NULL;
    $stance_action = strtolower(trim((string) (
      $params['stance_action']
      ?? $legacy_stance_action
      ?? $stance_transition['action']
      ?? ''
    )));
    if (!in_array($stance_action, ['enter', 'exit'], TRUE)) {
      $stance_action = $this->inferFeatStanceAction($params, $feat_id);
      if (!in_array($stance_action, ['enter', 'exit'], TRUE)) {
        return NULL;
      }
    }

    $stance_id = strtolower(trim((string) (
      $params['stance_id']
      ?? $params['stanceId']
      ?? $stance_transition['stance_id']
      ?? $stance_transition['stanceId']
      ?? $feat_id
      ?? $params['option_id']
      ?? $params['optionId']
      ?? ''
    )));
    if ($stance_id === '') {
      return NULL;
    }

    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    if ($actor_index === NULL || !is_array($dungeon_data['entities'][$actor_index] ?? NULL)) {
      return NULL;
    }

    $actor_entity = $dungeon_data['entities'][$actor_index];
    $character_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    if (!is_array($character_state)) {
      return NULL;
    }

    $identity = $this->resolveCanonicalCharacterIdentity($actor_entity);
    $entity_ref = trim((string) ($identity['instance_id'] ?? $actor_id));
    $source = [
      'source_type' => 'feat_action',
      'source_id' => (string) ($feat_id ?? $stance_id),
    ];

    if ($stance_action === 'enter') {
      $character_state = $this->stanceRuntimeService->enterStance($character_state, $stance_id, 1, $source, $campaign_id, $entity_ref);
    }
    else {
      $character_state = $this->stanceRuntimeService->exitStance($character_state, $stance_id, $source, $campaign_id, $entity_ref);
    }

    $this->persistCanonicalCharacterState($actor_entity, $campaign_id, $character_state);
    $dungeon_data['entities'][$actor_index]['state']['stance_state'] = $character_state['stance_state'] ?? [];
    $dungeon_data['entities'][$actor_index]['state']['som_state'] = $character_state['som_state'] ?? ($dungeon_data['entities'][$actor_index]['state']['som_state'] ?? []);

    return [
      'stance_action' => $stance_action,
      'stance_id' => $stance_id,
      'stance_state' => is_array($character_state['stance_state'] ?? NULL) ? $character_state['stance_state'] : [],
      'arcane_cascade_active' => $this->stanceRuntimeService->isStanceActive($character_state, 'arcane_cascade'),
    ];
  }

  /**
   * Infer stance transition action for stance-tagged feat executions.
   */
  protected function inferFeatStanceAction(array $params, mixed $feat_id): string {
    $candidate = strtolower(trim((string) ($params['feat_name'] ?? $params['featName'] ?? $params['option_label'] ?? $feat_id ?? '')));
    if ($candidate === '') {
      return '';
    }
    if (str_contains($candidate, 'dismiss') || str_contains($candidate, 'exit stance') || str_contains($candidate, 'leave stance')) {
      return 'exit';
    }
    if (str_contains($candidate, 'stance') || str_contains($candidate, 'arcane_cascade')) {
      return 'enter';
    }
    return '';
  }

  /**
   * Validate optional contract payload and fail loudly on mixed-shape values.
   *
   * @return array<string, mixed>|null
   */
  protected function requireOptionalContractPayload(
    mixed $payload,
    string $expected_kind,
    string $expected_contract_version,
    string $field_name
  ): ?array {
    if ($payload === NULL) {
      return NULL;
    }
    if (!is_array($payload)) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid %s payload: expected object, got %s.',
        $field_name,
        get_debug_type($payload)
      ));
    }
    $kind = (string) ($payload['kind'] ?? '');
    if ($kind !== $expected_kind) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid %s payload kind: expected %s, got %s.',
        $field_name,
        $expected_kind,
        $kind !== '' ? $kind : 'missing'
      ));
    }
    $version = (string) ($payload['contract_version'] ?? '');
    if ($version !== $expected_contract_version) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid %s contract version: expected %s, got %s.',
        $field_name,
        $expected_contract_version,
        $version !== '' ? $version : 'missing'
      ));
    }
    return $payload;
  }

  /**
   * Router seam: execute strike intent block with legacy side effects.
   */
  protected function routeStrikeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $result = $this->processStrike($encounter_id, (string) $actor_id, (string) $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    $resolved_target_id = is_string($result['target_id'] ?? NULL) && trim((string) $result['target_id']) !== ''
      ? (string) $result['target_id']
      : $target_id;
    $mutations = $result['mutations'] ?? [];
    $narration = $result['narration'] ?? NULL;
    $events = [];
    $reaction_packet = NULL;
    if (!empty($params['skip_map'])) {
      $reaction_packet = $this->unifiedReactionEngine->buildReactionResolutionPacket(
        (string) $actor_id,
        (string) $resolved_target_id,
        'attack_of_opportunity',
        'resolved',
        [
          'encounter_id' => $encounter_id,
          'source' => 'strike.skip_map',
        ]
      );
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $game_state['turn']['attacks_this_turn'] = ($game_state['turn']['attacks_this_turn'] ?? 0) + 1;

    if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
      $game_state['entities'][$actor_id]['cover_active'] = FALSE;
    }

    {
      $enc_air_st = $this->encounterStore->loadEncounter($encounter_id);
      $ptcp_air_st = $enc_air_st ? $this->findEncounterParticipantByEntityId($enc_air_st, (string) $actor_id) : NULL;
      if ($ptcp_air_st) {
        $edata_air_st = !empty($ptcp_air_st['entity_ref']) ? json_decode((string) $ptcp_air_st['entity_ref'], TRUE) : [];
        if (!empty($edata_air_st['airborne'])) {
          $edata_air_st['air_decrement_this_turn'] = 2;
          $this->encounterStore->updateParticipant((int) $ptcp_air_st['id'], ['entity_ref' => json_encode($edata_air_st)]);
        }
      }
    }

    $strike_execution_request = $this->requireOptionalContractPayload(
      $result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'strike.execution_request'
    );
    $strike_resolution_envelope = $this->requireOptionalContractPayload(
      $result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'strike.resolution_envelope'
    );
    $damage_packet = $this->requireOptionalContractPayload(
      $result['damage_packet'] ?? NULL,
      'damage_application',
      CombatResolutionContractService::DAMAGE_PACKET_CONTRACT_VERSION,
      'damage_packet'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'strike',
      (string) $actor_id,
      $resolved_target_id,
      $params
    );
    $resolution_packets = [];
    if (is_array($damage_packet)) {
      $resolution_packets[] = $damage_packet;
    }
    if (is_array($reaction_packet)) {
      $resolution_packets[] = $reaction_packet;
    }
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      $resolution_packets,
      [
        'target' => $resolved_target_id,
        'degree' => $result['degree'] ?? NULL,
        'damage' => $result['damage'] ?? NULL,
        'damage_type' => $result['damage_type'] ?? NULL,
      ]
    );

    $events[] = GameEventLogger::buildEvent('strike', 'encounter', $actor_id, [
      'execution_request' => $execution_request,
      'resolution_envelope' => $resolution_envelope,
      'strike_execution_request' => $strike_execution_request,
      'strike_resolution_envelope' => $strike_resolution_envelope,
      'disposition_change' => is_array($result['disposition_change'] ?? NULL) ? $result['disposition_change'] : NULL,
      'reaction_packet' => $reaction_packet,
      'target' => $resolved_target_id,
      'roll' => $result['roll'] ?? NULL,
      'total' => $result['total'] ?? NULL,
      'dc' => $result['ac'] ?? NULL,
      'degree' => $result['degree'] ?? NULL,
      'damage' => $result['damage'] ?? NULL,
      'damage_type' => $result['damage_type'] ?? NULL,
      'damage_packet' => $damage_packet,
      'round' => $game_state['round'] ?? NULL,
    ], $narration, $resolved_target_id);

    $attacker_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $target_name = $this->resolveEntityName((string) $resolved_target_id, $game_state, $dungeon_data);
    $degree_text = $result['degree'] ?? 'unknown';
    $damage_val = $result['damage'] ?? 0;
    $weapon_label = trim((string) ($result['weapon_name'] ?? ''));
    $with_weapon = $weapon_label !== '' ? sprintf(' with %s', $weapon_label) : '';
    $damage_type_label = trim((string) ($result['damage_type'] ?? ''));
    $damage_clause = $damage_type_label !== ''
      ? sprintf('%d %s damage', (int) $damage_val, $damage_type_label)
      : sprintf('%d damage', (int) $damage_val);
    $roll_suffix = (is_numeric($result['total'] ?? NULL) && is_numeric($result['ac'] ?? NULL))
      ? (is_numeric($result['roll'] ?? NULL)
        ? sprintf(' (attack %d vs AC %d, d20 %d)', (int) $result['total'], (int) $result['ac'], (int) $result['roll'])
        : sprintf(' (attack %d vs AC %d)', (int) $result['total'], (int) $result['ac']))
      : '';
    $strike_desc = match ($degree_text) {
      'critical_success' => sprintf('%s critically strikes %s%s for %s!%s', $attacker_name, $target_name, $with_weapon, $damage_clause, $roll_suffix),
      'success' => sprintf('%s strikes %s%s for %s.%s', $attacker_name, $target_name, $with_weapon, $damage_clause, $roll_suffix),
      'failure' => sprintf('%s swings at %s%s but misses.%s', $attacker_name, $target_name, $with_weapon, $roll_suffix),
      'critical_failure' => sprintf('%s fumbles an attack at %s%s!%s', $attacker_name, $target_name, $with_weapon, $roll_suffix),
      default => sprintf('%s attacks %s%s.%s', $attacker_name, $target_name, $with_weapon, $roll_suffix),
    };
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'action',
      'speaker' => $attacker_name,
      'speaker_type' => 'player',
      'speaker_ref' => '',
      'content' => $strike_desc,
      'visibility' => 'public',
      'mechanical_data' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'strike_execution_request' => $strike_execution_request,
        'strike_resolution_envelope' => $strike_resolution_envelope,
        'reaction_packet' => $reaction_packet,
        'attack_roll' => $result['roll'] ?? NULL,
        'total' => $result['total'] ?? NULL,
        'ac' => $result['ac'] ?? NULL,
        'degree' => $degree_text,
        'damage' => $damage_val,
        'damage_type' => $result['damage_type'] ?? NULL,
        'damage_packet' => $damage_packet,
        'weapon' => $params['weapon'] ?? NULL,
      ],
    ]);
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'dice_roll',
      'speaker' => 'System',
      'speaker_type' => 'system',
      'speaker_ref' => '',
      'content' => sprintf('%s attack roll %d vs AC %d: %s.', $attacker_name, (int) ($result['total'] ?? 0), (int) ($result['ac'] ?? 0), $degree_text),
      'mechanical_data' => [
        'action' => 'strike',
        'roll' => $result['roll'] ?? NULL,
        'total' => $result['total'] ?? NULL,
        'dc' => $result['ac'] ?? NULL,
        'degree' => $degree_text,
        'weapon' => $params['weapon'] ?? NULL,
        'target' => $resolved_target_id,
      ],
      'visibility' => 'public',
    ]);
    if ($damage_val > 0) {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'damage_applied',
        'speaker' => 'System',
        'speaker_type' => 'system',
        'speaker_ref' => $actor_id,
        'content' => sprintf('%s takes %d damage.', $target_name, $damage_val),
        'mechanical_data' => [
          'target' => $resolved_target_id,
          'damage' => $damage_val,
          'damage_type' => $result['damage_type'] ?? 'physical',
          'damage_packet' => $damage_packet,
        ],
        'visibility' => 'public',
      ]);
    }

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'strike_execution_request' => $strike_execution_request,
        'strike_resolution_envelope' => $strike_resolution_envelope,
        'damage_packet' => $damage_packet,
        'reaction_packet' => $reaction_packet,
      ]),
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
    ];
  }

  /**
   * Router seam: execute stride intent block with legacy side effects.
   */
}
