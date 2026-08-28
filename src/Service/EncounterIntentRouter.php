<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Routes encounter intents through a dedicated orchestration seam.
 */
class EncounterIntentRouter {

  public function routeIntent(
    array $intent,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $process_intent_core
  ): array {
    return $process_intent_core($intent, $game_state, $dungeon_data, $campaign_id);
  }

  /**
   * Applies a handled route payload into orchestration response state.
   */
  public function applyHandledRoutePayload(
    array $route,
    array &$result,
    array &$mutations,
    array &$events,
    bool $include_mutations = TRUE,
    bool $reset_mutations_when_absent = FALSE,
    array &$payload = []
  ): ?array {
    $payload = (array) ($route['payload'] ?? []);
    if (!$include_mutations) {
      return $this->mergeRoutePayloadWithoutMutations($payload, $result, $events);
    }
    $abort_response = $this->mergeRoutePayload($payload, $result, $mutations, $events);
    if ($abort_response !== NULL) {
      return $abort_response;
    }
    if ($reset_mutations_when_absent && !array_key_exists('mutations', $payload)) {
      $mutations = [];
    }
    return NULL;
  }

  /**
   * Routes utility branch with campaign-bound sense motive callback.
   *
   * @return array<string,mixed>|bool|null
   */
  public function handleEncounterUtilityRouteWithCampaignContext(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_escape,
    callable $handle_seek,
    callable $handle_search,
    callable $handle_sense_motive_with_campaign,
    callable $handle_take_cover,
    callable $handle_release,
    array &$result,
    array &$mutations,
    array &$events,
    mixed &$narration,
    mixed &$mechanical_result
  ): array|bool|null {
    return $this->handleEncounterUtilityRouteWithEffects(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_escape,
      $handle_seek,
      $handle_search,
      function (?string $aid, ?string $tid, array $action_params, array &$state) use ($campaign_id, $handle_sense_motive_with_campaign): array {
        return $handle_sense_motive_with_campaign($aid, $tid, $action_params, $state, $campaign_id);
      },
      $handle_take_cover,
      $handle_release,
      $result,
      $mutations,
      $events,
      $narration,
      $mechanical_result
    );
  }

  /**
   * Applies a handled route payload and captures selected payload keys.
   *
   * @param array<int,string> $capture_keys
   *   Keys to copy from route payload when present.
   * @param array<string,mixed> $captures
   *   Captured key/value map populated by reference.
   */
  public function applyHandledRoutePayloadAndCaptureKeys(
    array $route,
    array &$result,
    array &$mutations,
    array &$events,
    array $capture_keys,
    array &$captures,
    bool $include_mutations = TRUE,
    bool $reset_mutations_when_absent = FALSE
  ): ?array {
    $payload = [];
    $abort_response = $this->applyHandledRoutePayload(
      $route,
      $result,
      $mutations,
      $events,
      $include_mutations,
      $reset_mutations_when_absent,
      $payload
    );
    if ($abort_response !== NULL) {
      return $abort_response;
    }

    foreach ($capture_keys as $key) {
      if (is_string($key) && array_key_exists($key, $payload)) {
        $captures[$key] = $payload[$key];
      }
    }

    return NULL;
  }

  /**
   * Applies a handled route payload and propagates mutations/events.
   */
  public function applyHandledRouteWithMutations(
    array $route,
    array &$result,
    array &$mutations,
    array &$events,
    bool $reset_mutations_when_absent = FALSE,
    array &$payload = []
  ): ?array {
    return $this->applyHandledRoutePayload(
      $route,
      $result,
      $mutations,
      $events,
      TRUE,
      $reset_mutations_when_absent,
      $payload
    );
  }

  /**
   * Applies a handled route payload without mutation propagation.
   */
  public function applyHandledRouteWithoutMutations(
    array $route,
    array &$result,
    array &$events,
    array &$payload = []
  ): ?array {
    $discarded_mutations = [];
    return $this->applyHandledRoutePayload(
      $route,
      $result,
      $discarded_mutations,
      $events,
      FALSE,
      FALSE,
      $payload
    );
  }

  /**
   * Applies a handled payload with mutation propagation.
   */
  protected function mergeRoutePayload(
    array $payload,
    array &$result,
    array &$mutations,
    array &$events
  ): ?array {
    if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
      return $payload['abort_response'];
    }
    $result = (array) ($payload['result'] ?? []);
    $mutations = $payload['mutations'] ?? $mutations;
    $events = array_merge($events, (array) ($payload['events'] ?? []));
    return NULL;
  }

  /**
   * Applies a handled payload without mutation propagation.
   */
  protected function mergeRoutePayloadWithoutMutations(
    array $payload,
    array &$result,
    array &$events
  ): ?array {
    if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
      return $payload['abort_response'];
    }
    $result = (array) ($payload['result'] ?? []);
    $events = array_merge($events, (array) ($payload['events'] ?? []));
    return NULL;
  }

  public function routeRestAction(
    string $type,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $process_treat_wounds,
    callable $process_refocus,
    callable $process_repair,
    callable $process_daily_preparations
  ): array {
    return match ($type) {
      'treat_wounds' => [
        'handled' => TRUE,
        'result' => $process_treat_wounds($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'refocus' => [
        'handled' => TRUE,
        'result' => $process_refocus($actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'repair' => [
        'handled' => TRUE,
        'result' => $process_repair($actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'daily_preparations' => [
        'handled' => TRUE,
        'result' => $process_daily_preparations($actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      default => ['handled' => FALSE, 'result' => []],
    };
  }

  public function routeTransitionAction(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $enter_room_framework
  ): array {
    if ($type !== 'transition') {
      return ['handled' => FALSE, 'result' => []];
    }

    return [
      'handled' => TRUE,
      'result' => $enter_room_framework(
        $actor_id,
        (string) ($params['target_room_id'] ?? ''),
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id
      ),
    ];
  }

  /**
   * Routes and applies rest-action branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleRestRoute(
    string $type,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $process_treat_wounds,
    callable $process_refocus,
    callable $process_repair,
    callable $process_daily_preparations,
    array &$mutations,
    array &$events,
    array &$time_effects,
    mixed &$narration
  ): array|bool|null {
    $route = $this->routeRestAction(
      $type,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $process_treat_wounds,
      $process_refocus,
      $process_repair,
      $process_daily_preparations
    );
    if (empty($route['handled'])) {
      return FALSE;
    }

    $result = (array) ($route['result'] ?? []);
    if (!empty($result['error'])) {
      return [
        'success' => FALSE,
        'result' => ['error' => $result['error']],
        'mutations' => [],
        'events' => [],
        'phase_transition' => NULL,
        'narration' => NULL,
      ];
    }

    $mutations = $result['mutations'] ?? [];
    $events = array_merge($events, $result['events'] ?? []);
    $time_effects = array_merge($time_effects, $result['time_effects'] ?? []);
    $narration = $result['narration'] ?? NULL;
    return NULL;
  }

  /**
   * Routes and applies transition-action branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleTransitionRoute(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $enter_room_framework,
    callable $on_error,
    array &$mutations,
    array &$events,
    array &$time_effects,
    mixed &$narration
  ): array|bool|null {
    $route = $this->routeTransitionAction(
      $type,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $enter_room_framework
    );
    if (empty($route['handled'])) {
      return FALSE;
    }

    $result = (array) ($route['result'] ?? []);
    if (!empty($result['error'])) {
      return $on_error($result);
    }

    $mutations = $result['mutations'] ?? [];
    $events = array_merge($events, $result['events'] ?? []);
    $time_effects = array_merge($time_effects, $result['time_effects'] ?? []);
    $narration = $result['narration'] ?? NULL;
    return NULL;
  }

  public function routePrimaryCombatAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_strike,
    callable $handle_stride,
    callable $handle_cast_spell
  ): array {
    return match ($type) {
      'strike' => [
        'handled' => TRUE,
        'payload' => $handle_strike($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'stride' => [
        'handled' => TRUE,
        'payload' => $handle_stride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'cast_spell' => [
        'handled' => TRUE,
        'payload' => $handle_cast_spell($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeSkillFeatAction(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    callable $handle_skill,
    callable $handle_feat
  ): array {
    return match ($type) {
      'skill' => [
        'handled' => TRUE,
        'payload' => $handle_skill($actor_id, $params, $game_state, $dungeon_data),
      ],
      'feat' => [
        'handled' => TRUE,
        'payload' => $handle_feat($actor_id, $params, $game_state, $dungeon_data),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  /**
   * Routes and applies skill/feat branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleSkillFeatRoute(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    callable $handle_skill,
    callable $handle_feat,
    array &$result,
    array &$events
  ): array|bool|null {
    $route = $this->routeSkillFeatAction(
      $type,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $handle_skill,
      $handle_feat
    );
    if (empty($route['handled'])) {
      return FALSE;
    }

    return $this->applyHandledRouteWithoutMutations($route, $result, $events);
  }

  /**
   * Routes skill/feat branch with campaign-bound feat callback.
   *
   * @return array<string,mixed>|bool|null
   */
  public function handleSkillFeatRouteWithCampaignContext(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_skill,
    callable $handle_feat_with_campaign,
    array &$result,
    array &$events
  ): array|bool|null {
    return $this->handleSkillFeatRoute(
      $type,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $handle_skill,
      function (?string $aid, array $action_params, array &$state, array &$dungeon) use ($campaign_id, $handle_feat_with_campaign): array {
        return $handle_feat_with_campaign($aid, $action_params, $state, $dungeon, $campaign_id);
      },
      $result,
      $events
    );
  }

  public function routeConsumableAndMetamagicAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_consume_item,
    callable $handle_declare_metamagic
  ): array {
    return match ($type) {
      'consume_item' => [
        'handled' => TRUE,
        'payload' => $handle_consume_item($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'declare_metamagic' => [
        'handled' => TRUE,
        'payload' => $handle_declare_metamagic($actor_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeInteractTalkAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_interact,
    callable $handle_talk
  ): array {
    return match ($type) {
      'interact' => [
        'handled' => TRUE,
        'payload' => $handle_interact($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'talk' => [
        'handled' => TRUE,
        'payload' => $handle_talk($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeTurnFlowAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_end_turn,
    callable $handle_delay,
    callable $handle_delay_reenter,
    ?callable $handle_party_recovery = NULL
  ): array {
    return match ($type) {
      'end_turn', 'choose_not_to_act' => [
        'handled' => TRUE,
        'payload' => $handle_end_turn($type, $encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'delay' => [
        'handled' => TRUE,
        'payload' => $handle_delay($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'delay_reenter' => [
        'handled' => TRUE,
        'payload' => $handle_delay_reenter($actor_id, $game_state),
      ],
      'party_recovery' => $handle_party_recovery !== NULL ? [
        'handled' => TRUE,
        'payload' => $handle_party_recovery($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ] : ['handled' => FALSE, 'payload' => []],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeReadyReactionAction(
    string $type,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_ready,
    callable $handle_reaction
  ): array {
    return match ($type) {
      'ready' => [
        'handled' => TRUE,
        'payload' => $handle_ready($actor_id, $params, $game_state),
      ],
      'reaction' => [
        'handled' => TRUE,
        'payload' => $handle_reaction($actor_id, $target_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeAidAction(
    string $type,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_aid_setup,
    callable $handle_aid
  ): array {
    return match ($type) {
      'aid_setup' => [
        'handled' => TRUE,
        'payload' => $handle_aid_setup($actor_id, $target_id, $params, $game_state),
      ],
      'aid' => [
        'handled' => TRUE,
        'payload' => $handle_aid($actor_id, $target_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeHeroPointAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_hero_point_reroll,
    callable $handle_heroic_recovery_all_points
  ): array {
    return match ($type) {
      'hero_point_reroll' => [
        'handled' => TRUE,
        'payload' => $handle_hero_point_reroll($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      'heroic_recovery_all_points' => [
        'handled' => TRUE,
        'payload' => $handle_heroic_recovery_all_points($encounter_id, $actor_id, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeMovementUtilityAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_stand,
    callable $handle_drop_prone,
    callable $handle_step,
    callable $handle_crawl,
    callable $handle_leap
  ): array {
    return match ($type) {
      'stand' => [
        'handled' => TRUE,
        'payload' => $handle_stand($encounter_id, $actor_id, $game_state),
      ],
      'drop_prone' => [
        'handled' => TRUE,
        'payload' => $handle_drop_prone($encounter_id, $actor_id, $game_state),
      ],
      'step' => [
        'handled' => TRUE,
        'payload' => $handle_step($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'crawl' => [
        'handled' => TRUE,
        'payload' => $handle_crawl($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'leap' => [
        'handled' => TRUE,
        'payload' => $handle_leap($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  /**
   * Routes and applies consume/metamagic branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleConsumableAndMetamagicRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_consume_item,
    callable $handle_declare_metamagic,
    array &$result,
    array &$events
  ): array|bool|null {
    $route = $this->routeConsumableAndMetamagicAction(
      $type,
      $encounter_id,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_consume_item,
      $handle_declare_metamagic
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithoutMutations($route, $result, $events);
  }

  /**
   * Routes and applies ready/reaction branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleReadyReactionRoute(
    string $type,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_ready,
    callable $handle_reaction,
    array &$result,
    array &$events
  ): array|bool|null {
    $route = $this->routeReadyReactionAction(
      $type,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_ready,
      $handle_reaction
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithoutMutations($route, $result, $events);
  }

  /**
   * Routes and applies aid branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleAidRoute(
    string $type,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_aid_setup,
    callable $handle_aid,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeAidAction(
      $type,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_aid_setup,
      $handle_aid
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes aid branch behavior with campaign-bound execution callbacks.
   *
   * @return array<string,mixed>|bool|null
   */
  public function handleAidRouteWithCampaignContext(
    string $type,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id,
    callable $handle_aid_setup_with_campaign,
    callable $handle_aid_with_campaign,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    return $this->handleAidRoute(
      $type,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      function (?string $aid, ?string $tid, array $action_params, array &$state) use ($campaign_id, $handle_aid_setup_with_campaign): array {
        return $handle_aid_setup_with_campaign($aid, $tid, $action_params, $state, $campaign_id);
      },
      function (?string $aid, ?string $tid, array $action_params, array &$state) use ($campaign_id, $handle_aid_with_campaign): array {
        return $handle_aid_with_campaign($aid, $tid, $action_params, $state, $campaign_id);
      },
      $result,
      $mutations,
      $events
    );
  }

  /**
   * Routes and applies hero-point branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleHeroPointRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_hero_point_reroll,
    callable $handle_heroic_recovery_all_points,
    array &$result,
    array &$events
  ): array|bool|null {
    $route = $this->routeHeroPointAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_hero_point_reroll,
      $handle_heroic_recovery_all_points
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithoutMutations($route, $result, $events);
  }

  /**
   * Routes and applies movement branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleMovementRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_stand,
    callable $handle_drop_prone,
    callable $handle_step,
    callable $handle_crawl,
    callable $handle_leap,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeMovementUtilityAction(
      $type,
      $encounter_id,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_stand,
      $handle_drop_prone,
      $handle_step,
      $handle_crawl,
      $handle_leap
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes athletics tactical branch with dungeon/campaign-bound shove callback.
   *
   * @return array<string,mixed>|bool|null
   */
  public function handleAthleticsTacticalRouteWithDungeonCampaignContext(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_climb,
    callable $handle_force_open,
    callable $handle_grapple,
    callable $handle_high_jump,
    callable $handle_long_jump,
    callable $handle_shove_with_dungeon_campaign,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    return $this->handleAthleticsTacticalRoute(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_climb,
      $handle_force_open,
      $handle_grapple,
      $handle_high_jump,
      $handle_long_jump,
      function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state) use (&$dungeon_data, $campaign_id, $handle_shove_with_dungeon_campaign): array {
        return $handle_shove_with_dungeon_campaign($eid, $aid, $tid, $action_params, $state, $dungeon_data, $campaign_id);
      },
      $result,
      $mutations,
      $events
    );
  }

  /**
   * Routes and applies defensive-reaction branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleDefensiveRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_arrest_fall,
    callable $handle_grab_edge,
    callable $handle_shield_block,
    callable $handle_attack_of_opportunity,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeDefensiveReactionAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_arrest_fall,
      $handle_grab_edge,
      $handle_shield_block,
      $handle_attack_of_opportunity
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes medicine/knowledge branch with campaign-bound callbacks.
   *
   * @return array<string,mixed>|bool|null
   */
  public function handleMedicineKnowledgeRouteWithCampaignContext(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id,
    callable $handle_administer_first_aid_with_campaign,
    callable $handle_treat_poison_with_campaign,
    callable $handle_battle_medicine_with_campaign,
    callable $handle_recall_knowledge,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    return $this->handleMedicineKnowledgeRoute(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state) use ($campaign_id, $handle_administer_first_aid_with_campaign): array {
        return $handle_administer_first_aid_with_campaign($eid, $aid, $tid, $action_params, $state, $campaign_id);
      },
      function (?string $aid, ?string $tid, array $action_params, array &$state) use ($campaign_id, $handle_treat_poison_with_campaign): array {
        return $handle_treat_poison_with_campaign($aid, $tid, $action_params, $state, $campaign_id);
      },
      function (?string $aid, ?string $tid, array $action_params, array &$state) use ($campaign_id, $handle_battle_medicine_with_campaign): array {
        return $handle_battle_medicine_with_campaign($aid, $tid, $action_params, $state, $campaign_id);
      },
      $handle_recall_knowledge,
      $result,
      $mutations,
      $events
    );
  }

  /**
   * Routes and applies utility-skill branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleUtilitySkillRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    callable $handle_balance,
    callable $handle_tumble_through,
    callable $handle_maneuver_in_flight,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeUtilitySkillAction(
      $type,
      $encounter_id,
      $actor_id,
      $params,
      $game_state,
      $handle_balance,
      $handle_tumble_through,
      $handle_maneuver_in_flight
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes stance/awareness branch with campaign-bound point-out callback.
   *
   * @return array<string,mixed>|bool|null
   */
  public function handleStanceAwarenessRouteWithCampaignContext(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id,
    callable $handle_raise_shield,
    callable $handle_avert_gaze,
    callable $handle_point_out_with_campaign,
    callable $handle_minor_color_shift,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    return $this->handleStanceAwarenessRoute(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_raise_shield,
      $handle_avert_gaze,
      function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state) use ($campaign_id, $handle_point_out_with_campaign): array {
        return $handle_point_out_with_campaign($eid, $aid, $tid, $action_params, $state, $campaign_id);
      },
      $handle_minor_color_shift,
      $result,
      $mutations,
      $events
    );
  }

  /**
   * Routes and applies social-skill branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleSocialSkillRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id,
    callable $handle_feint,
    callable $handle_create_diversion,
    callable $handle_request,
    callable $handle_demoralize,
    callable $handle_command_animal,
    callable $handle_perform,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeSocialSkillAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $campaign_id,
      $handle_feint,
      $handle_create_diversion,
      $handle_request,
      $handle_demoralize,
      $handle_command_animal,
      $handle_perform
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes social-skill branch with bound target/campaign context.
   *
   * @return array<string,mixed>|bool|null
   */
  public function handleSocialSkillRouteWithTargetCampaignContext(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id,
    callable $handle_feint_with_target_campaign,
    callable $handle_create_diversion_with_target_campaign,
    callable $handle_request,
    callable $handle_demoralize,
    callable $handle_command_animal_with_target_campaign,
    callable $handle_perform_with_target_campaign,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    return $this->handleSocialSkillRoute(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $campaign_id,
      function (?int $eid, ?string $aid, array $action_params, array &$state) use ($target_id, $campaign_id, $handle_feint_with_target_campaign): array {
        return $handle_feint_with_target_campaign($eid, $aid, $target_id, $action_params, $state, $campaign_id);
      },
      function (?string $aid, array $action_params, array &$state) use ($target_id, $campaign_id, $handle_create_diversion_with_target_campaign): array {
        return $handle_create_diversion_with_target_campaign(NULL, $aid, $target_id, $action_params, $state, $campaign_id);
      },
      $handle_request,
      $handle_demoralize,
      function (?string $aid, array $action_params, array &$state) use ($target_id, $campaign_id, $handle_command_animal_with_target_campaign): array {
        return $handle_command_animal_with_target_campaign($aid, $target_id, $action_params, $state, $campaign_id);
      },
      function (?string $aid, array $action_params, array &$state) use ($target_id, $campaign_id, $handle_perform_with_target_campaign): array {
        return $handle_perform_with_target_campaign($aid, $target_id, $action_params, $state, $campaign_id);
      },
      $result,
      $mutations,
      $events
    );
  }

  /**
   * Routes and applies athletics tactical branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleAthleticsTacticalRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_climb,
    callable $handle_force_open,
    callable $handle_grapple,
    callable $handle_high_jump,
    callable $handle_long_jump,
    callable $handle_shove,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeAthleticsTacticalAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_climb,
      $handle_force_open,
      $handle_grapple,
      $handle_high_jump,
      $handle_long_jump,
      $handle_shove
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes and applies athletics maneuver branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleAthleticsManeuverRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_swim,
    callable $handle_trip,
    callable $handle_disarm,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeAthleticsManeuverAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_swim,
      $handle_trip,
      $handle_disarm
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes and applies medicine/knowledge branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleMedicineKnowledgeRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_administer_first_aid,
    callable $handle_treat_poison,
    callable $handle_battle_medicine,
    callable $handle_recall_knowledge,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeMedicineKnowledgeAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_administer_first_aid,
      $handle_treat_poison,
      $handle_battle_medicine,
      $handle_recall_knowledge
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes and applies stealth/subterfuge branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleStealthSubterfugeRoute(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    callable $handle_hide,
    callable $handle_sneak,
    callable $handle_conceal_object,
    callable $handle_palm_object,
    callable $handle_steal,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeStealthSubterfugeAction(
      $type,
      $actor_id,
      $params,
      $game_state,
      $handle_hide,
      $handle_sneak,
      $handle_conceal_object,
      $handle_palm_object,
      $handle_steal
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes and applies magic activation branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleMagicActivationRoute(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    callable $handle_activate_item,
    callable $handle_sustain_activation,
    callable $handle_dismiss_activation,
    callable $handle_sustain_spell,
    callable $handle_dismiss_spell,
    callable $handle_cast_from_scroll,
    callable $handle_cast_from_staff,
    callable $handle_cast_from_wand,
    callable $handle_overcharge_wand,
    callable $handle_activate_talisman,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeMagicActivationAction(
      $type,
      $actor_id,
      $params,
      $game_state,
      $handle_activate_item,
      $handle_sustain_activation,
      $handle_dismiss_activation,
      $handle_sustain_spell,
      $handle_dismiss_spell,
      $handle_cast_from_scroll,
      $handle_cast_from_staff,
      $handle_cast_from_wand,
      $handle_overcharge_wand,
      $handle_activate_talisman
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes and applies traversal utility branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleTraversalRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_burrow,
    callable $handle_fly,
    callable $handle_mount,
    callable $handle_dismount,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeTraversalUtilityAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_burrow,
      $handle_fly,
      $handle_mount,
      $handle_dismount
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes and applies stance/awareness branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleStanceAwarenessRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_raise_shield,
    callable $handle_avert_gaze,
    callable $handle_point_out,
    callable $handle_minor_color_shift,
    array &$result,
    array &$mutations,
    array &$events
  ): array|bool|null {
    $route = $this->routeStanceAwarenessAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $handle_raise_shield,
      $handle_avert_gaze,
      $handle_point_out,
      $handle_minor_color_shift
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    return $this->applyHandledRouteWithMutations($route, $result, $mutations, $events);
  }

  /**
   * Routes and applies primary-combat branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handlePrimaryCombatRoute(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_strike,
    callable $handle_stride,
    callable $handle_cast_spell,
    array &$result,
    array &$mutations,
    array &$events,
    mixed &$narration
  ): array|bool|null {
    $route = $this->routePrimaryCombatAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_strike,
      $handle_stride,
      $handle_cast_spell
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    $captures = [];
    $abort_response = $this->applyHandledRoutePayloadAndCaptureKeys(
      $route,
      $result,
      $mutations,
      $events,
      ['narration'],
      $captures,
      TRUE,
      TRUE
    );
    if ($abort_response !== NULL) {
      return $abort_response;
    }
    if (array_key_exists('narration', $captures)) {
      $narration = $captures['narration'];
    }
    return NULL;
  }

  /**
   * Routes and applies interact/talk branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleInteractTalkRouteWithNarration(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_interact,
    callable $handle_talk,
    array &$result,
    array &$mutations,
    array &$events,
    mixed &$narration
  ): array|bool|null {
    $route = $this->routeInteractTalkAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_interact,
      $handle_talk
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    $captures = [];
    $abort_response = $this->applyHandledRoutePayloadAndCaptureKeys(
      $route,
      $result,
      $mutations,
      $events,
      ['narration'],
      $captures
    );
    if ($abort_response !== NULL) {
      return $abort_response;
    }
    if (array_key_exists('narration', $captures)) {
      $narration = $captures['narration'];
    }
    return NULL;
  }

  /**
   * Routes and applies turn-flow branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleTurnFlowRouteWithEffects(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_end_turn,
    callable $handle_delay,
    callable $handle_delay_reenter,
    array &$result,
    array &$mutations,
    array &$events,
    mixed &$narration,
    array &$time_effects,
    ?callable $handle_party_recovery = NULL
  ): array|bool|null {
    $route = $this->routeTurnFlowAction(
      $type,
      $encounter_id,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_end_turn,
      $handle_delay,
      $handle_delay_reenter,
      $handle_party_recovery
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    $captures = [];
    $abort_response = $this->applyHandledRoutePayloadAndCaptureKeys(
      $route,
      $result,
      $mutations,
      $events,
      ['time_effects', 'narration'],
      $captures
    );
    if ($abort_response !== NULL) {
      return $abort_response;
    }
    $time_effects = array_merge($time_effects, (array) ($captures['time_effects'] ?? []));
    if (array_key_exists('narration', $captures)) {
      $narration = $captures['narration'];
    }
    return NULL;
  }

  /**
   * Routes and applies utility branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleEncounterUtilityRouteWithEffects(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_escape,
    callable $handle_seek,
    callable $handle_search,
    callable $handle_sense_motive,
    callable $handle_take_cover,
    callable $handle_release,
    array &$result,
    array &$mutations,
    array &$events,
    mixed &$narration,
    mixed &$mechanical_result
  ): array|bool|null {
    $route = $this->routeEncounterUtilityAction(
      $type,
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $handle_escape,
      $handle_seek,
      $handle_search,
      $handle_sense_motive,
      $handle_take_cover,
      $handle_release
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    $captures = [];
    $abort_response = $this->applyHandledRoutePayloadAndCaptureKeys(
      $route,
      $result,
      $mutations,
      $events,
      ['narration', 'mechanical_result'],
      $captures
    );
    if ($abort_response !== NULL) {
      return $abort_response;
    }
    if (array_key_exists('narration', $captures)) {
      $narration = $captures['narration'];
    }
    if (array_key_exists('mechanical_result', $captures)) {
      $mechanical_result = $captures['mechanical_result'];
    }
    return NULL;
  }

  /**
   * Routes and applies device/hazard branch behavior.
   *
   * @return array<string,mixed>|bool|null
   *   FALSE when unhandled, NULL when handled without abort, abort response array.
   */
  public function handleDeviceHazardRouteWithPhaseTransition(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    callable $handle_disable_device,
    callable $handle_pick_lock,
    callable $handle_disable_hazard,
    callable $handle_attack_hazard,
    callable $handle_counteract_hazard,
    array &$result,
    array &$mutations,
    array &$events,
    mixed &$phase_transition
  ): array|bool|null {
    $route = $this->routeDeviceHazardAction(
      $type,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $handle_disable_device,
      $handle_pick_lock,
      $handle_disable_hazard,
      $handle_attack_hazard,
      $handle_counteract_hazard
    );
    if (empty($route['handled'])) {
      return FALSE;
    }
    $captures = [];
    $abort_response = $this->applyHandledRoutePayloadAndCaptureKeys(
      $route,
      $result,
      $mutations,
      $events,
      ['phase_transition'],
      $captures
    );
    if ($abort_response !== NULL) {
      return $abort_response;
    }
    if (array_key_exists('phase_transition', $captures)) {
      $phase_transition = $captures['phase_transition'];
    }
    return NULL;
  }

  public function routeDefensiveReactionAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_arrest_fall,
    callable $handle_grab_edge,
    callable $handle_shield_block,
    callable $handle_attack_of_opportunity
  ): array {
    return match ($type) {
      'arrest_fall' => [
        'handled' => TRUE,
        'payload' => $handle_arrest_fall($encounter_id, $actor_id, $params, $game_state),
      ],
      'grab_edge' => [
        'handled' => TRUE,
        'payload' => $handle_grab_edge($encounter_id, $actor_id, $params, $game_state),
      ],
      'shield_block' => [
        'handled' => TRUE,
        'payload' => $handle_shield_block($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      'attack_of_opportunity' => [
        'handled' => TRUE,
        'payload' => $handle_attack_of_opportunity($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeUtilitySkillAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    callable $handle_balance,
    callable $handle_tumble_through,
    callable $handle_maneuver_in_flight
  ): array {
    return match ($type) {
      'balance' => [
        'handled' => TRUE,
        'payload' => $handle_balance($encounter_id, $actor_id, $params, $game_state),
      ],
      'tumble_through' => [
        'handled' => TRUE,
        'payload' => $handle_tumble_through($actor_id, $params, $game_state),
      ],
      'maneuver_in_flight' => [
        'handled' => TRUE,
        'payload' => $handle_maneuver_in_flight($actor_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeSocialSkillAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id,
    callable $handle_feint,
    callable $handle_create_diversion,
    callable $handle_request,
    callable $handle_demoralize,
    callable $handle_command_animal,
    callable $handle_perform
  ): array {
    return match ($type) {
      'feint' => [
        'handled' => TRUE,
        'payload' => $handle_feint($encounter_id, $actor_id, $params, $game_state),
      ],
      'create_diversion' => [
        'handled' => TRUE,
        'payload' => $handle_create_diversion($actor_id, $params, $game_state),
      ],
      'request' => [
        'handled' => TRUE,
        'payload' => $handle_request($actor_id, $target_id, $params, $game_state, $campaign_id),
      ],
      'demoralize' => [
        'handled' => TRUE,
        'payload' => $handle_demoralize($encounter_id, $actor_id, $target_id, $params, $game_state, $campaign_id),
      ],
      'command_animal' => [
        'handled' => TRUE,
        'payload' => $handle_command_animal($actor_id, $params, $game_state),
      ],
      'perform' => [
        'handled' => TRUE,
        'payload' => $handle_perform($actor_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeEncounterUtilityAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_escape,
    callable $handle_seek,
    callable $handle_search,
    callable $handle_sense_motive,
    callable $handle_take_cover,
    callable $handle_release
  ): array {
    return match ($type) {
      'escape' => [
        'handled' => TRUE,
        'payload' => $handle_escape($encounter_id, $actor_id, $params, $game_state),
      ],
      'seek' => [
        'handled' => TRUE,
        'payload' => $handle_seek($encounter_id, $actor_id, $params, $game_state),
      ],
      'search' => [
        'handled' => TRUE,
        'payload' => $handle_search($actor_id, $params, $game_state, $dungeon_data, $campaign_id),
      ],
      'sense_motive' => [
        'handled' => TRUE,
        'payload' => $handle_sense_motive($actor_id, $target_id, $params, $game_state),
      ],
      'take_cover' => [
        'handled' => TRUE,
        'payload' => $handle_take_cover($actor_id, $game_state),
      ],
      'release' => [
        'handled' => TRUE,
        'payload' => $handle_release($actor_id, $params, $game_state, $dungeon_data),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeAthleticsTacticalAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_climb,
    callable $handle_force_open,
    callable $handle_grapple,
    callable $handle_high_jump,
    callable $handle_long_jump,
    callable $handle_shove
  ): array {
    return match ($type) {
      'climb' => [
        'handled' => TRUE,
        'payload' => $handle_climb($encounter_id, $actor_id, $params, $game_state),
      ],
      'force_open' => [
        'handled' => TRUE,
        'payload' => $handle_force_open($actor_id, $target_id, $params, $game_state),
      ],
      'grapple' => [
        'handled' => TRUE,
        'payload' => $handle_grapple($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      'high_jump' => [
        'handled' => TRUE,
        'payload' => $handle_high_jump($encounter_id, $actor_id, $params, $game_state),
      ],
      'long_jump' => [
        'handled' => TRUE,
        'payload' => $handle_long_jump($encounter_id, $actor_id, $params, $game_state),
      ],
      'shove' => [
        'handled' => TRUE,
        'payload' => $handle_shove($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeAthleticsManeuverAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_swim,
    callable $handle_trip,
    callable $handle_disarm
  ): array {
    return match ($type) {
      'swim' => [
        'handled' => TRUE,
        'payload' => $handle_swim($encounter_id, $actor_id, $params, $game_state),
      ],
      'trip' => [
        'handled' => TRUE,
        'payload' => $handle_trip($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      'disarm' => [
        'handled' => TRUE,
        'payload' => $handle_disarm($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeMedicineKnowledgeAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_administer_first_aid,
    callable $handle_treat_poison,
    callable $handle_battle_medicine,
    callable $handle_recall_knowledge
  ): array {
    return match ($type) {
      'administer_first_aid' => [
        'handled' => TRUE,
        'payload' => $handle_administer_first_aid($encounter_id, $actor_id, $target_id, $params, $game_state),
      ],
      'treat_poison' => [
        'handled' => TRUE,
        'payload' => $handle_treat_poison($actor_id, $target_id, $params, $game_state),
      ],
      'battle_medicine' => [
        'handled' => TRUE,
        'payload' => $handle_battle_medicine($actor_id, $target_id, $params, $game_state),
      ],
      'recall_knowledge' => [
        'handled' => TRUE,
        'payload' => $handle_recall_knowledge($actor_id, $target_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeStealthSubterfugeAction(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    callable $handle_hide,
    callable $handle_sneak,
    callable $handle_conceal_object,
    callable $handle_palm_object,
    callable $handle_steal
  ): array {
    return match ($type) {
      'hide' => [
        'handled' => TRUE,
        'payload' => $handle_hide($actor_id, $params, $game_state),
      ],
      'sneak' => [
        'handled' => TRUE,
        'payload' => $handle_sneak($actor_id, $params, $game_state),
      ],
      'conceal_object' => [
        'handled' => TRUE,
        'payload' => $handle_conceal_object($actor_id, $params, $game_state),
      ],
      'palm_object' => [
        'handled' => TRUE,
        'payload' => $handle_palm_object($actor_id, $params, $game_state),
      ],
      'steal' => [
        'handled' => TRUE,
        'payload' => $handle_steal($actor_id, $params, $game_state),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeDeviceHazardAction(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    callable $handle_disable_device,
    callable $handle_pick_lock,
    callable $handle_disable_hazard,
    callable $handle_attack_hazard,
    callable $handle_counteract_hazard
  ): array {
    return match ($type) {
      'disable_device' => [
        'handled' => TRUE,
        'payload' => $handle_disable_device($actor_id, $params, $game_state),
      ],
      'pick_lock' => [
        'handled' => TRUE,
        'payload' => $handle_pick_lock($actor_id, $params, $game_state),
      ],
      'disable_hazard' => [
        'handled' => TRUE,
        'payload' => $handle_disable_hazard($actor_id, $params, $game_state, $dungeon_data),
      ],
      'attack_hazard' => [
        'handled' => TRUE,
        'payload' => $handle_attack_hazard($actor_id, $params, $game_state, $dungeon_data),
      ],
      'counteract_hazard' => [
        'handled' => TRUE,
        'payload' => $handle_counteract_hazard($actor_id, $params, $game_state, $dungeon_data),
      ],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeMagicActivationAction(
    string $type,
    ?string $actor_id,
    array $params,
    array &$game_state,
    callable $handle_activate_item,
    callable $handle_sustain_activation,
    callable $handle_dismiss_activation,
    callable $handle_sustain_spell,
    callable $handle_dismiss_spell,
    callable $handle_cast_from_scroll,
    callable $handle_cast_from_staff,
    callable $handle_cast_from_wand,
    callable $handle_overcharge_wand,
    callable $handle_activate_talisman
  ): array {
    return match ($type) {
      'activate_item' => ['handled' => TRUE, 'payload' => $handle_activate_item($actor_id, $params, $game_state)],
      'sustain_activation' => ['handled' => TRUE, 'payload' => $handle_sustain_activation($actor_id, $params, $game_state)],
      'dismiss_activation' => ['handled' => TRUE, 'payload' => $handle_dismiss_activation($actor_id, $params, $game_state)],
      'sustain_spell' => ['handled' => TRUE, 'payload' => $handle_sustain_spell($actor_id, $params, $game_state)],
      'dismiss_spell' => ['handled' => TRUE, 'payload' => $handle_dismiss_spell($actor_id, $params, $game_state)],
      'cast_from_scroll' => ['handled' => TRUE, 'payload' => $handle_cast_from_scroll($actor_id, $params, $game_state)],
      'cast_from_staff' => ['handled' => TRUE, 'payload' => $handle_cast_from_staff($actor_id, $params, $game_state)],
      'cast_from_wand' => ['handled' => TRUE, 'payload' => $handle_cast_from_wand($actor_id, $params, $game_state)],
      'overcharge_wand' => ['handled' => TRUE, 'payload' => $handle_overcharge_wand($actor_id, $params, $game_state)],
      'activate_talisman' => ['handled' => TRUE, 'payload' => $handle_activate_talisman($actor_id, $params, $game_state)],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeTraversalUtilityAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $handle_burrow,
    callable $handle_fly,
    callable $handle_mount,
    callable $handle_dismount
  ): array {
    return match ($type) {
      'burrow' => ['handled' => TRUE, 'payload' => $handle_burrow($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id)],
      'fly' => ['handled' => TRUE, 'payload' => $handle_fly($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id)],
      'mount' => ['handled' => TRUE, 'payload' => $handle_mount($encounter_id, $actor_id, $target_id, $params, $game_state)],
      'dismount' => ['handled' => TRUE, 'payload' => $handle_dismount($encounter_id, $actor_id, $params, $game_state)],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

  public function routeStanceAwarenessAction(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    callable $handle_raise_shield,
    callable $handle_avert_gaze,
    callable $handle_point_out,
    callable $handle_minor_color_shift
  ): array {
    return match ($type) {
      'raise_shield' => ['handled' => TRUE, 'payload' => $handle_raise_shield($encounter_id, $actor_id, $params, $game_state)],
      'avert_gaze' => ['handled' => TRUE, 'payload' => $handle_avert_gaze($encounter_id, $actor_id, $params, $game_state)],
      'point_out' => ['handled' => TRUE, 'payload' => $handle_point_out($encounter_id, $actor_id, $target_id, $params, $game_state)],
      'minor_color_shift' => ['handled' => TRUE, 'payload' => $handle_minor_color_shift($actor_id, $params, $game_state)],
      default => ['handled' => FALSE, 'payload' => []],
    };
  }

}
