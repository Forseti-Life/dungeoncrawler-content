<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Extracted runtime/route execution methods for EncounterPhaseHandler.
 */
trait EncounterPhaseHandlerRuntimeTrait {
  use EncounterPhaseHandlerRouteExecutionTrait;

  protected function processIntentCore(array $intent, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $type = $intent['type'] ?? '';
    $actor_id = $intent['actor'] ?? NULL;
    $target_id = is_scalar($intent['target'] ?? NULL) ? trim((string) $intent['target']) : NULL;
    $target_id = $target_id !== '' ? $target_id : NULL;
    $params = $intent['params'] ?? [];
    $normalized_target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);
    if ($normalized_target_refs !== []) {
      $params['selected_targets'] = $normalized_target_refs;
      $target_id = $normalized_target_refs[0] ?? $target_id;
    }
    $encounter_id = isset($game_state['encounter_id']) && is_numeric($game_state['encounter_id'])
      ? (int) $game_state['encounter_id']
      : NULL;

    if ($encounter_id) {
      $canonical_turn = $this->loadCanonicalTurnState((int) $encounter_id);
      if ($canonical_turn !== NULL) {
        $this->syncGameStateWithCanonicalTurn($game_state, $canonical_turn);
      }
    }

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;
    $mechanical_result = NULL;
    $time_effects = [];

    // dc-cr-spells-ch07: Metamagic state machine — if a metamagic was declared
    // this turn and the next action is NOT cast_spell, the metamagic is wasted.
    if ($type !== 'cast_spell' && $type !== 'declare_metamagic' &&
        !empty($game_state['turn']['metamagic_pending'][$actor_id])) {
      unset($game_state['turn']['metamagic_pending'][$actor_id]);
    }
    $rest_route_response = $this->encounterIntentRouter->handleRestRoute(
      $type,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $this->processTreatWoundsRestAction(...),
      $this->processRefocusRestAction(...),
      $this->processRepairRestAction(...),
      $this->processDailyPreparationsRestAction(...),
      $mutations,
      $events,
      $time_effects,
      $narration
    );
    if ($rest_route_response !== FALSE) {
      if ($rest_route_response !== NULL) {
        return $rest_route_response;
      }
    }
    else {
      $transition_route_response = $this->encounterIntentRouter->handleTransitionRoute(
        $type,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->enterRoomFramework(...),
        fn(array $result): array => $this->buildTransitionExecutionErrorResponse($result, $campaign_id, $actor_id, $params, $dungeon_data),
        $mutations,
        $events,
        $time_effects,
        $narration
      );
      if ($transition_route_response !== FALSE) {
        if ($transition_route_response !== NULL) {
          return $transition_route_response;
        }
      }
      else {
      $primary_route_response = $this->encounterIntentRouter->handlePrimaryCombatRoute(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeStrikeIntentExecution(...),
        $this->routeStrideIntentExecution(...),
        $this->routeCastSpellIntentExecution(...),
        $result,
        $mutations,
        $events,
        $narration
      );
      if ($primary_route_response !== FALSE) {
        if ($primary_route_response !== NULL) {
          return $primary_route_response;
        }
      }
      else {
      $skill_feat_route_response = $this->encounterIntentRouter->handleSkillFeatRouteWithCampaignContext(
        $type,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeSkillIntentExecution(...),
        $this->routeFeatIntentExecution(...),
        $result,
        $events
      );
      if ($skill_feat_route_response !== FALSE) {
        if ($skill_feat_route_response !== NULL) {
          return $skill_feat_route_response;
        }
      }
      else {
      $consume_meta_response = $this->encounterIntentRouter->handleConsumableAndMetamagicRoute(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeConsumeItemIntentExecution(...),
        $this->routeDeclareMetamagicIntentExecution(...),
        $result,
        $events
      );
      if ($consume_meta_response !== FALSE) {
        if ($consume_meta_response !== NULL) {
          return $consume_meta_response;
        }
      }
      else {
      $interact_talk_response = $this->encounterIntentRouter->handleInteractTalkRouteWithNarration(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeInteractIntentExecution(...),
        $this->routeTalkIntentExecution(...),
        $result,
        $mutations,
        $events,
        $narration
      );
      if ($interact_talk_response !== FALSE) {
        if ($interact_talk_response !== NULL) {
          return $interact_talk_response;
        }
      }
      else {
      $turn_flow_response = $this->encounterIntentRouter->handleTurnFlowRouteWithEffects(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeEndTurnIntentExecution(...),
        $this->routeDelayIntentExecution(...),
        $this->routeDelayReenterIntentExecution(...),
        $result,
        $mutations,
        $events,
        $narration,
        $time_effects
      );
      if ($turn_flow_response !== FALSE) {
        if ($turn_flow_response !== NULL) {
          return $turn_flow_response;
        }
      }
      else {
      $ready_reaction_response = $this->encounterIntentRouter->handleReadyReactionRoute(
        $type,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $this->routeReadyIntentExecution(...),
        $this->routeReactionIntentExecution(...),
        $result,
        $events
      );
      if ($ready_reaction_response !== FALSE) {
        if ($ready_reaction_response !== NULL) {
          return $ready_reaction_response;
        }
      }
      else {
      $aid_route_response = $this->encounterIntentRouter->handleAidRouteWithCampaignContext(
        $type,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $campaign_id,
        $this->routeAidSetupIntentExecution(...),
        $this->routeAidIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($aid_route_response !== FALSE) {
        if ($aid_route_response !== NULL) {
          return $aid_route_response;
        }
      }
      else {
      $hero_point_response = $this->encounterIntentRouter->handleHeroPointRoute(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $this->routeHeroPointRerollIntentExecution(...),
        $this->routeHeroicRecoveryAllPointsIntentExecution(...),
        $result,
        $events
      );
      if ($hero_point_response !== FALSE) {
        if ($hero_point_response !== NULL) {
          return $hero_point_response;
        }
      }
      else {
      $movement_route_response = $this->encounterIntentRouter->handleMovementRoute(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeStandIntentExecution(...),
        $this->routeDropProneIntentExecution(...),
        $this->routeStepIntentExecution(...),
        $this->routeCrawlIntentExecution(...),
        $this->routeLeapIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($movement_route_response !== FALSE) {
        if ($movement_route_response !== NULL) {
          return $movement_route_response;
        }
      }
      else {
      $defensive_route_response = $this->encounterIntentRouter->handleDefensiveRoute(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $this->routeArrestFallIntentExecution(...),
        $this->routeGrabEdgeIntentExecution(...),
        $this->routeShieldBlockIntentExecution(...),
        $this->routeAttackOfOpportunityIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($defensive_route_response !== FALSE) {
        if ($defensive_route_response !== NULL) {
          return $defensive_route_response;
        }
      }
      else {
      $utility_skill_response = $this->encounterIntentRouter->handleUtilitySkillRoute(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        $this->routeBalanceIntentExecution(...),
        $this->routeTumbleThroughIntentExecution(...),
        $this->routeManeuverInFlightIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($utility_skill_response !== FALSE) {
        if ($utility_skill_response !== NULL) {
          return $utility_skill_response;
        }
      }
      else {
      $social_skill_response = $this->encounterIntentRouter->handleSocialSkillRouteWithTargetCampaignContext(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $campaign_id,
        $this->routeFeintIntentExecution(...),
        $this->routeCreateDiversionIntentExecution(...),
        $this->routeRequestIntentExecution(...),
        $this->routeDemoralizeIntentExecution(...),
        $this->routeCommandAnimalIntentExecution(...),
        $this->routePerformIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($social_skill_response !== FALSE) {
        if ($social_skill_response !== NULL) {
          return $social_skill_response;
        }
      }
      else {
      $utility_route_response = $this->encounterIntentRouter->handleEncounterUtilityRouteWithCampaignContext(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeEscapeIntentExecution(...),
        $this->routeSeekIntentExecution(...),
        $this->routeSearchIntentExecution(...),
        $this->routeSenseMotiveIntentExecution(...),
        $this->routeTakeCoverIntentExecution(...),
        $this->routeReleaseIntentExecution(...),
        $result,
        $mutations,
        $events,
        $narration,
        $mechanical_result
      );
      if ($utility_route_response !== FALSE) {
        if ($utility_route_response !== NULL) {
          return $utility_route_response;
        }
      }
      else {
      $athletics_route_response = $this->encounterIntentRouter->handleAthleticsTacticalRouteWithDungeonCampaignContext(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeClimbIntentExecution(...),
        $this->routeForceOpenIntentExecution(...),
        $this->routeGrappleIntentExecution(...),
        $this->routeHighJumpIntentExecution(...),
        $this->routeLongJumpIntentExecution(...),
        $this->routeShoveIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($athletics_route_response !== FALSE) {
        if ($athletics_route_response !== NULL) {
          return $athletics_route_response;
        }
      }
      else {
      $athletics_maneuver_response = $this->encounterIntentRouter->handleAthleticsManeuverRoute(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeSwimIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeTripIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeDisarmIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        $result,
        $mutations,
        $events
      );
      if ($athletics_maneuver_response !== FALSE) {
        if ($athletics_maneuver_response !== NULL) {
          return $athletics_maneuver_response;
        }
      }
      else {
      $medicine_knowledge_response = $this->encounterIntentRouter->handleMedicineKnowledgeRouteWithCampaignContext(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $campaign_id,
        $this->routeAdministerFirstAidIntentExecution(...),
        $this->routeTreatPoisonIntentExecution(...),
        $this->routeBattleMedicineIntentExecution(...),
        $this->routeRecallKnowledgeIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($medicine_knowledge_response !== FALSE) {
        if ($medicine_knowledge_response !== NULL) {
          return $medicine_knowledge_response;
        }
      }
      else {
      $stealth_route_response = $this->encounterIntentRouter->handleStealthSubterfugeRoute(
        $type,
        $actor_id,
        $params,
        $game_state,
        $this->routeHideIntentExecution(...),
        $this->routeSneakIntentExecution(...),
        $this->routeConcealObjectIntentExecution(...),
        $this->routePalmObjectIntentExecution(...),
        $this->routeStealIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($stealth_route_response !== FALSE) {
        if ($stealth_route_response !== NULL) {
          return $stealth_route_response;
        }
      }
      else {
      $device_hazard_route_response = $this->encounterIntentRouter->handleDeviceHazardRouteWithPhaseTransition(
        $type,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeDisableDeviceIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routePickLockIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeDisableHazardIntentExecution($aid, $action_params, $state, $dungeon);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeAttackHazardIntentExecution($aid, $action_params, $state, $dungeon);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeCounteractHazardIntentExecution($aid, $action_params, $state, $dungeon);
        },
        $result,
        $mutations,
        $events,
        $phase_transition
      );
      if ($device_hazard_route_response !== FALSE) {
        if ($device_hazard_route_response !== NULL) {
          return $device_hazard_route_response;
        }
      }
      else {
      $magic_activation_route_response = $this->encounterIntentRouter->handleMagicActivationRoute(
        $type,
        $actor_id,
        $params,
        $game_state,
        $this->routeActivateItemIntentExecution(...),
        $this->routeSustainActivationIntentExecution(...),
        $this->routeDismissActivationIntentExecution(...),
        $this->routeSustainSpellIntentExecution(...),
        $this->routeDismissSpellIntentExecution(...),
        $this->routeCastFromScrollIntentExecution(...),
        $this->routeCastFromStaffIntentExecution(...),
        $this->routeCastFromWandIntentExecution(...),
        $this->routeOverchargeWandIntentExecution(...),
        $this->routeActivateTalismanIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($magic_activation_route_response !== FALSE) {
        if ($magic_activation_route_response !== NULL) {
          return $magic_activation_route_response;
        }
      }
      else {
      $traversal_route_response = $this->encounterIntentRouter->handleTraversalRoute(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        $this->routeBurrowIntentExecution(...),
        $this->routeFlyIntentExecution(...),
        $this->routeMountIntentExecution(...),
        $this->routeDismountIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($traversal_route_response !== FALSE) {
        if ($traversal_route_response !== NULL) {
          return $traversal_route_response;
        }
      }
      else {
      $stance_route_response = $this->encounterIntentRouter->handleStanceAwarenessRouteWithCampaignContext(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $campaign_id,
        $this->routeRaiseShieldIntentExecution(...),
        $this->routeAvertGazeIntentExecution(...),
        $this->routePointOutIntentExecution(...),
        $this->routeMinorColorShiftIntentExecution(...),
        $result,
        $mutations,
        $events
      );
      if ($stance_route_response !== FALSE) {
        if ($stance_route_response !== NULL) {
          return $stance_route_response;
        }
      }
      else {
      }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }

    $social_progression_events = $this->applyRoomSceneSocialProgressionPolicy(
      $intent,
      $result,
      $game_state,
      $dungeon_data,
      $campaign_id
    );
    if ($social_progression_events !== []) {
      $events = array_merge($events, $social_progression_events);
    }

    $this->maybeQueueMechanicalSystemLogEntry([
      'campaign_id' => $campaign_id,
      'dungeon_data' => $dungeon_data,
      'type' => $type,
      'actor_id' => $actor_id,
      'target_id' => $target_id,
      'params' => $params,
      'result' => is_array($mechanical_result) ? $mechanical_result : $result,
      'game_state' => $game_state,
    ]);

    // Check for auto-end-turn (actions depleted + no movement remaining).
    // Delay is intentional initiative exit — do NOT auto-end-turn for it.
    $no_auto_end_types = ['end_turn', 'choose_not_to_act', 'delay', 'delay_reenter', 'release', 'aid'];
    if (!in_array($type, $no_auto_end_types, TRUE) && $this->shouldAutoEndTurn($game_state)) {
      $auto_end = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
      $time_effects = array_merge($time_effects, $this->buildRoundElapsedTimeEffects($auto_end, $actor_id, $dungeon_data));
      $events[] = GameEventLogger::buildEvent('auto_end_turn', 'encounter', $actor_id, [
        'round' => $game_state['round'] ?? NULL,
      ]);
      if (!empty($auto_end['npc_events'])) {
        $events = array_merge($events, $auto_end['npc_events']);
      }
    }

    // Keep combat_participants action economy in sync with the authoritative
    // encounter turn state used by the game coordinator.
    $this->syncEncounterParticipantTurnResources($encounter_id, $game_state);
    if ($encounter_id) {
      $canonical_turn = $this->loadCanonicalTurnState((int) $encounter_id);
      if ($canonical_turn !== NULL) {
        $this->syncGameStateWithCanonicalTurn($game_state, $canonical_turn);
      }
    }

    return [
      'success' => TRUE,
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'phase_transition' => $phase_transition,
      'narration' => $narration,
      'time_effects' => $time_effects,
    ];
  }

  /**
   * Builds a standardized transition execution failure response.
   */
  protected function buildTransitionExecutionErrorResponse(
    array $result,
    int $campaign_id,
    ?string $actor_id,
    array $params,
    array $dungeon_data
  ): array {
    $this->logger->warning('Encounter transition execution failed: campaign={campaign_id} actor={actor} target_room={target_room} active_room={active_room} error={error}', [
      'campaign_id' => $campaign_id,
      'actor' => (string) ($actor_id ?? ''),
      'target_room' => (string) ($params['target_room_id'] ?? ''),
      'active_room' => (string) ($dungeon_data['active_room_id'] ?? ''),
      'error' => (string) ($result['error'] ?? 'unknown'),
    ]);
    return [
      'success' => FALSE,
      'result' => ['error' => $result['error'] ?? 'unknown'],
      'mutations' => [],
      'events' => [],
      'phase_transition' => NULL,
      'narration' => NULL,
    ];
  }

  /**
   * Router seam: execute end-turn intent block with legacy side effects.
   */
}
