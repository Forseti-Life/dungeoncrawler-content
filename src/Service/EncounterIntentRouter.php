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
    callable $handle_delay_reenter
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
