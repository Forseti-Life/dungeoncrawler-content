<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Coordinates non-player actor autoplay and room-scene actor pass turns.
 */
class ActorAutoplayCoordinator {

  protected EncounterAiIntegrationService $encounterAiService;
  protected HazardService $hazardService;
  protected ?RoomChatService $roomChatService;
  protected ?MovementResolverService $movementResolver;
  protected ConfigFactoryInterface $configFactory;
  protected LoggerInterface $logger;

  public function __construct(
    EncounterAiIntegrationService $encounter_ai_service,
    HazardService $hazard_service,
    ?RoomChatService $room_chat_service,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    ?MovementResolverService $movement_resolver = NULL
  ) {
    $this->encounterAiService = $encounter_ai_service;
    $this->hazardService = $hazard_service;
    $this->roomChatService = $room_chat_service;
    $this->movementResolver = $movement_resolver;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('dungeoncrawler');
  }

  /**
   * Auto-play a non-player actor turn.
   *
   * @param callable $build_actor_context
   *   fn(string $entity_id, array $game_state, array $dungeon_data): array
   * @param callable $build_turn_plan
   *   fn(string $entity_id, array $game_state, int $campaign_id, ?array $ai_seed_action): array
   * @param callable $process_strike
   *   fn(int $encounter_id, string $entity_id, string $target, array &$game_state, array &$dungeon_data, int $campaign_id): array
   * @param callable $process_stride
   *   fn(int $encounter_id, string $entity_id, array $to_hex, array &$game_state, array &$dungeon_data, int $campaign_id): array
   * @param callable $check_entity_defeated
   *   fn(string $target, string $entity_id, array &$game_state, array &$events, array $dungeon_data, int $campaign_id): void
   * @param callable $find_nearest_alive_player
   *   fn(string $entity_id, array $game_state): ?string
   * @param callable $build_choose_not_to_act_events
   *   fn(string $entity_id, string $decision_reason, array $decision_basis, array &$game_state, array &$dungeon_data, int $campaign_id): array
   * @param callable $resolve_pending_dialogue_turn
   *   fn(string $entity_id, array $pending_dialogue, array &$game_state, array &$dungeon_data, int $campaign_id, string $decision_intent): array
   */
  public function autoPlayTurn(
    int $encounter_id,
    string $entity_id,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $build_actor_context,
    callable $build_turn_plan,
    callable $process_strike,
    callable $process_stride,
    callable $check_entity_defeated,
    callable $find_nearest_alive_player,
    callable $build_choose_not_to_act_events,
    callable $resolve_pending_dialogue_turn
  ): array {
    $events = [];
    $mutations = [];
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $pending_dialogue = ($resolved_room_id && $this->roomChatService)
      ? $this->roomChatService->consumePendingEncounterRoomDialogue($campaign_id, (string) $resolved_room_id, $entity_id, $dungeon_data)
      : NULL;
    if (is_array($pending_dialogue)) {
      return $resolve_pending_dialogue_turn($entity_id, $pending_dialogue, $game_state, $dungeon_data, $campaign_id, 'npc_pending_dialogue');
    }

    $hazard_entity = $this->hazardService->findHazardByInstanceId($entity_id, $dungeon_data);
    if ($hazard_entity !== NULL && ($hazard_entity['type'] ?? '') === 'hazard') {
      $routine = $hazard_entity['routine'] ?? [];
      if (!empty($routine)) {
        foreach ($routine as $action_str) {
          $events[] = GameEventLogger::buildEvent('hazard_routine_action', 'encounter', $entity_id, [
            'action' => $action_str,
            'round' => $game_state['round'] ?? NULL,
          ]);
        }
      }
      else {
        $events[] = GameEventLogger::buildEvent('hazard_routine_action', 'encounter', $entity_id, [
          'action' => 'none',
          'round' => $game_state['round'] ?? NULL,
        ]);
      }
      return ['events' => $events, 'mutations' => []];
    }

    // REQ (2026-08-31 RCA, campaign 919): a combatant that is defeated (dead,
    // dying/unconscious, or otherwise incapacitated) can never legally act.
    // Previously this function fell straight through into AI recommendation
    // request / turn-plan construction for ANY non-player entity_id whose
    // turn came up, with no is_defeated short-circuit. Since applyDamage()'s
    // zero-HP handling can mark a combatant (including a non-enemy ally like
    // a familiar) is_defeated in the same request that later advances the
    // turn pointer onto them, autoplay ended up trying to plan/execute a
    // turn for an incapacitated actor. When that attempt failed (AI
    // recommendation contract violation, missing valid target, etc.) the
    // resulting exception aborted before the recursive processEndTurn()
    // call that would otherwise have advanced past them, permanently
    // freezing game_state['turn']['entity'] on the defeated combatant with
    // no client able to ever act on their behalf again. Short-circuit here
    // with the same "chooses not to take any further actions" closeout used
    // for a genuinely voluntary pass, so the caller's turn-advance loop
    // immediately recurses past this actor instead of getting stuck.
    $current_combatant = $this->findCombatantByEntityId($entity_id, $game_state);
    if ($current_combatant !== NULL && !empty($current_combatant['is_defeated'])) {
      $game_state['turn']['actions_remaining'] = 0;
      return [
        'events' => $build_choose_not_to_act_events(
          $entity_id,
          'Actor is defeated/incapacitated and cannot act.',
          ['intent' => 'incapacitated_pass'],
          $game_state,
          $dungeon_data,
          $campaign_id
        ),
        'mutations' => [],
      ];
    }

    $context = $build_actor_context($entity_id, $game_state, $dungeon_data);

    $ai_enabled = (bool) $this->configFactory->get('dungeoncrawler_content.settings')
      ->get('encounter_ai_npc_autoplay_enabled');
    $ai_seed_action = NULL;

    if ($ai_enabled) {
      $result = $this->encounterAiService->requestNpcActionRecommendation($context);
      if (empty($result['success']) || empty($result['recommendation'])) {
        $this->logger->error('Encounter AI recommendation contract violation: empty recommendation payload for actor {actor}.', [
          'actor' => $entity_id,
        ]);
        throw new \RuntimeException('Encounter AI recommendation contract violation: missing recommendation payload.');
      }
      $rec = $result['recommendation'];
      $action = $rec['recommended_action'] ?? [];
      $ai_seed_action = [
        'type' => is_string($action['type'] ?? NULL) ? $action['type'] : '',
        'target_instance_id' => $action['target_instance_id'] ?? ($action['target'] ?? NULL),
        'narration' => is_string($rec['narration'] ?? NULL) ? $rec['narration'] : NULL,
        'rationale' => is_string($rec['rationale'] ?? NULL) ? $rec['rationale'] : '',
        'decision_reason' => is_string($rec['decision_reason'] ?? NULL) ? $rec['decision_reason'] : '',
        'decision_basis' => is_array($rec['decision_basis'] ?? NULL) ? $rec['decision_basis'] : [],
      ];
    }

    $turn_plan = $build_turn_plan($entity_id, $game_state, $campaign_id, $ai_seed_action);
    $intent_contract = is_array($turn_plan['intent_contract'] ?? NULL) ? $turn_plan['intent_contract'] : [];
    $plan_steps = is_array($turn_plan['steps'] ?? NULL) ? $turn_plan['steps'] : [];

    foreach ($plan_steps as $plan_step) {
      $planned_action_type = (string) ($plan_step['action_type'] ?? '');
      if ($planned_action_type === '') {
        continue;
      }
      $target = is_scalar($plan_step['target'] ?? NULL) ? trim((string) $plan_step['target']) : '';
      $target = $target !== '' ? $target : NULL;
      $narration = is_string($plan_step['narration'] ?? NULL) ? $plan_step['narration'] : NULL;
      $decision_reason = (string) ($plan_step['decision_reason'] ?? ($intent_contract['decision_reason'] ?? 'Intent-driven action.'));
      $decision_basis = is_array($plan_step['decision_basis'] ?? NULL) ? $plan_step['decision_basis'] : [];
      $decision_basis += [
        'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
      ];
      $action_type = $this->resolveGoalAlignedActionType(
        $planned_action_type,
        $entity_id,
        $target,
        $game_state,
        $dungeon_data,
        $intent_contract
      );
      if ($action_type !== $planned_action_type) {
        $decision_basis['goal_replanned_from'] = $planned_action_type;
        $decision_basis['goal_replanned_to'] = $action_type;
      }

      switch ($action_type) {
        case 'strike':
          if ($target) {
            $strike_result = $process_strike($encounter_id, $entity_id, $target, $game_state, $dungeon_data, $campaign_id);
            $resolved_degree = is_string($strike_result['degree'] ?? NULL) ? strtolower((string) $strike_result['degree']) : '';
            $resolved_hit = $strike_result['hit'] ?? NULL;
            if (!is_bool($resolved_hit) && $resolved_degree !== '') {
              $resolved_hit = in_array($resolved_degree, ['success', 'critical_success'], TRUE);
            }
            if (is_string($strike_result['error'] ?? NULL) && trim((string) $strike_result['error']) !== '') {
              $decision_basis['strike_error'] = trim((string) $strike_result['error']);
              $this->logger->warning('NPC strike execution failed for {actor}: {error}', [
                'actor' => $entity_id,
                'error' => $decision_basis['strike_error'],
              ]);
            }
            $event_narration = $this->resolveAutoplayActionNarration(
              'strike',
              $entity_id,
              $target,
              $game_state,
              $dungeon_data,
              $narration,
              [
                'roll' => $strike_result['roll'] ?? NULL,
                'total' => $strike_result['total'] ?? NULL,
                'ac' => $strike_result['ac'] ?? NULL,
                'degree' => $strike_result['degree'] ?? NULL,
                'damage' => $strike_result['damage'] ?? NULL,
                'damage_type' => $strike_result['damage_type'] ?? NULL,
                'weapon_name' => $strike_result['weapon_name'] ?? NULL,
              ]
            );
            $events[] = GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [
              'target' => $target,
              'roll' => $strike_result['roll'] ?? NULL,
              'total' => $strike_result['total'] ?? NULL,
              'ac' => $strike_result['ac'] ?? NULL,
              'hit' => is_bool($resolved_hit) ? $resolved_hit : NULL,
              'degree' => $strike_result['degree'] ?? NULL,
              'damage' => $strike_result['damage'] ?? NULL,
              'decision_reason' => $decision_reason,
              'decision_basis' => $decision_basis,
            ], $event_narration, $target);
            $check_entity_defeated($target, $entity_id, $game_state, $events, $dungeon_data, $campaign_id);
          }
          break;

        case 'stride':
          $movement_target = $target ?? $find_nearest_alive_player($entity_id, $game_state);
          $stride_to_hex = $this->resolveStrideDestinationHex($entity_id, $movement_target, $game_state, $dungeon_data, $intent_contract);
          $stride_succeeded = FALSE;
          if (is_array($stride_to_hex)) {
            $stride_result = $process_stride($encounter_id, $entity_id, $stride_to_hex, $game_state, $dungeon_data, $campaign_id);
            if (is_string($stride_result['error'] ?? NULL) && trim((string) $stride_result['error']) !== '') {
              $decision_basis['stride_error'] = trim((string) $stride_result['error']);
              $this->logger->warning('NPC stride execution failed for {actor}: {error}', [
                'actor' => $entity_id,
                'error' => $decision_basis['stride_error'],
              ]);
            }
            else {
              $resolved_to_hex = is_array($stride_result['to_hex'] ?? NULL) ? $stride_result['to_hex'] : $stride_to_hex;
              $decision_basis['stride_to_hex'] = [
                'q' => (int) ($resolved_to_hex['q'] ?? 0),
                'r' => (int) ($resolved_to_hex['r'] ?? 0),
              ];
              if (is_array($stride_result['from_hex'] ?? NULL)) {
                $decision_basis['stride_from_hex'] = [
                  'q' => (int) ($stride_result['from_hex']['q'] ?? 0),
                  'r' => (int) ($stride_result['from_hex']['r'] ?? 0),
                ];
              }
              if (is_array($stride_result['mutations'] ?? NULL) && $stride_result['mutations'] !== []) {
                $mutations = array_merge($mutations, $stride_result['mutations']);
              }
              $stride_succeeded = !$this->hexesEqual(
                $decision_basis['stride_from_hex'] ?? NULL,
                $decision_basis['stride_to_hex'] ?? NULL
              );
            }
          }
          else {
            $decision_basis['stride_error'] = 'no_destination';
          }
          if ($stride_succeeded) {
            $event_narration = $this->resolveAutoplayActionNarration(
              'stride',
              $entity_id,
              $movement_target,
              $game_state,
              $dungeon_data,
              $narration,
              [
                'from_hex' => $decision_basis['stride_from_hex'] ?? NULL,
                'to_hex' => $decision_basis['stride_to_hex'] ?? NULL,
              ]
            );
            $events[] = GameEventLogger::buildEvent('npc_stride', 'encounter', $entity_id, [
              'toward' => $movement_target,
              'from' => $decision_basis['stride_from_hex'] ?? NULL,
              'to' => $decision_basis['stride_to_hex'] ?? NULL,
              'to_hex' => $decision_basis['stride_to_hex'] ?? NULL,
              'decision_reason' => $decision_reason,
              'decision_basis' => $decision_basis,
            ], $event_narration);
          }
          break;

        case 'interact':
          $events[] = GameEventLogger::buildEvent('npc_interact', 'encounter', $entity_id, [
            'interaction' => 'raise_shield',
            'decision_reason' => $decision_reason,
            'decision_basis' => $decision_basis,
          ], $narration);
          break;

        case 'talk':
          $events[] = GameEventLogger::buildEvent('npc_talk', 'encounter', $entity_id, [
            'message' => $narration ?? 'The creature snarls at you.',
            'target' => $target,
            'decision_reason' => $decision_reason,
            'decision_basis' => $decision_basis,
          ], $narration);
          break;

        default:
          break;
      }

      $game_state['turn']['actions_remaining'] = max(0, (int) ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      if (($game_state['turn']['actions_remaining'] ?? 0) <= 0) {
        break;
      }
    }

    $game_state['turn']['actions_remaining'] = 0;
    $terminal_decision_reason = is_string($intent_contract['decision_reason'] ?? NULL)
      ? $intent_contract['decision_reason']
      : 'No further action selected.';
    $terminal_decision_basis = is_array($intent_contract['decision_basis'] ?? NULL)
      ? $intent_contract['decision_basis']
      : [];
    $terminal_decision_basis += [
      'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
      'executed_steps' => count($plan_steps),
    ];
    return [
      'events' => array_merge(
        $events,
        $build_choose_not_to_act_events(
          $entity_id,
          $terminal_decision_reason,
          $terminal_decision_basis,
          $game_state,
          $dungeon_data,
          $campaign_id
        )
      ),
      'mutations' => $mutations,
    ];
  }

  /**
   * Resolve a room-scene non-player actor pass turn.
   *
   * @param callable $build_choose_not_to_act_events
   *   fn(string $entity_id, string $decision_reason, array $decision_basis, array &$game_state, array &$dungeon_data, int $campaign_id): array
   * @param callable $resolve_pending_dialogue_turn
   *   fn(string $entity_id, array $pending_dialogue, array &$game_state, array &$dungeon_data, int $campaign_id, string $decision_intent): array
   */
  public function passRoomActorTurn(
    string $entity_id,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $build_choose_not_to_act_events,
    callable $resolve_pending_dialogue_turn
  ): array {
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $pending_dialogue = ($resolved_room_id && $this->roomChatService)
      ? $this->roomChatService->consumePendingEncounterRoomDialogue($campaign_id, (string) $resolved_room_id, $entity_id, $dungeon_data)
      : NULL;
    if (is_array($pending_dialogue)) {
      return $resolve_pending_dialogue_turn($entity_id, $pending_dialogue, $game_state, $dungeon_data, $campaign_id, 'room_scene_pending_dialogue');
    }

    $game_state['turn']['actions_remaining'] = 0;
    return [
      'events' => $build_choose_not_to_act_events(
        $entity_id,
        'No queued room-scene dialogue or deterministic room action was available for this actor turn.',
        [
          'intent' => 'room_scene_pass',
          'mode' => (string) ($game_state['encounter_context']['mode'] ?? 'room_scene'),
          'room_id' => $resolved_room_id,
        ],
        $game_state,
        $dungeon_data,
        $campaign_id
      ),
    ];
  }

  /**
   * Build deterministic per-action actor plan for remaining turn actions.
   *
   * @param callable $resolve_intent_target
   *   fn(string $entity_id, array $game_state, string $action_type, array $intent_contract, int $campaign_id): ?string
   */
  public function buildTurnPlan(
    string $entity_id,
    array $game_state,
    int $campaign_id,
    array $intent_contract,
    ?array $ai_seed_action,
    callable $resolve_intent_target
  ): array {
    $actions_remaining = max(0, (int) ($game_state['turn']['actions_remaining'] ?? 0));
    $steps = [];

    for ($step_index = 0; $step_index < $actions_remaining; $step_index++) {
      $action_type = $this->resolveIntentActionType($intent_contract, $step_index);
      $target = $resolve_intent_target($entity_id, $game_state, $action_type, $intent_contract, $campaign_id);
      $decision_reason = (string) ($intent_contract['decision_reason'] ?? 'Intent-driven fallback action.');
      $decision_basis = [
        'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
        'plan_step' => $step_index + 1,
        'target_strategy' => (string) ($intent_contract['target_strategy'] ?? 'nearest'),
      ] + (is_array($intent_contract['decision_basis'] ?? NULL) ? $intent_contract['decision_basis'] : []);
      $narration = NULL;

      if ($step_index === 0 && is_array($ai_seed_action) && $this->isAiSeedActionCompatibleWithIntent($ai_seed_action, $intent_contract)) {
        $action_type = (string) ($ai_seed_action['type'] ?? $action_type);
        $seed_target = $ai_seed_action['target_instance_id'] ?? NULL;
        if (is_scalar($seed_target) && trim((string) $seed_target) !== '') {
          $target = trim((string) $seed_target);
        }
        $seed_reason = trim((string) ($ai_seed_action['decision_reason'] ?? $ai_seed_action['rationale'] ?? ''));
        if ($seed_reason !== '') {
          $decision_reason = $seed_reason;
        }
        $decision_basis = [
          'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
          'plan_step' => 1,
          'target_strategy' => (string) ($intent_contract['target_strategy'] ?? 'nearest'),
          'ai_seeded' => TRUE,
        ] + (is_array($ai_seed_action['decision_basis'] ?? NULL) ? $ai_seed_action['decision_basis'] : []) + (is_array($intent_contract['decision_basis'] ?? NULL) ? $intent_contract['decision_basis'] : []);
        $narration = isset($ai_seed_action['narration']) && is_string($ai_seed_action['narration'])
          ? $ai_seed_action['narration']
          : NULL;
      }

      if ($action_type === 'end_turn') {
        break;
      }

      if (in_array($action_type, ['strike', 'talk'], TRUE) && $target === NULL) {
        $action_type = ($intent_contract['intent'] ?? '') === 'self_preserve' ? 'stride' : 'end_turn';
      }
      if ($action_type === 'end_turn') {
        break;
      }

      $steps[] = [
        'action_type' => $action_type,
        'target' => $target,
        'narration' => $narration,
        'decision_reason' => $decision_reason,
        'decision_basis' => $decision_basis,
      ];
    }

    return [
      'intent_contract' => $intent_contract,
      'steps' => $steps,
    ];
  }

  /**
   * Build canonical explicit "choose not to act" closeout events.
   *
   * @param callable $resolve_entity_name
   *   fn(string $entity_id, array $game_state, array $dungeon_data): string
   * @param callable $queue_narration_event
   *   fn(int $campaign_id, array &$dungeon_data, array $event, ?string $room_id, ?array $game_state_override): array
   */
  public function buildChooseNotToActEvents(
    string $entity_id,
    string $decision_reason,
    array $decision_basis,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $resolve_entity_name,
    callable $queue_narration_event
  ): array {
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = $resolve_entity_name($entity_id, $game_state, $dungeon_data);
    $content = sprintf('%s chooses not to take any further actions.', $actor_name);

    $events = [
      GameEventLogger::buildEvent('npc_choose_not_to_act', 'encounter', $entity_id, [
        'round' => $game_state['round'] ?? NULL,
        'room_id' => $resolved_room_id,
        'actor_name' => $actor_name,
        'actions_remaining' => $game_state['turn']['actions_remaining'] ?? NULL,
        'reason' => 'No further action selected.',
        'decision_reason' => $decision_reason,
        'decision_basis' => $decision_basis,
      ], $content),
    ];
    $queue_narration_event($campaign_id, $dungeon_data, [
      'type' => 'choose_not_to_act',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => $entity_id,
      'content' => $content,
      'visibility' => 'public',
      'mechanical_data' => [
        'actor_id' => $entity_id,
        'actor_name' => $actor_name,
        'room_id' => $resolved_room_id,
        'actions_remaining' => $game_state['turn']['actions_remaining'] ?? NULL,
        'decision_reason' => $decision_reason,
        'decision_basis' => $decision_basis,
      ],
    ], $resolved_room_id, $game_state);

    return $events;
  }

  /**
   * Resolve a pending encounter dialogue reply turn for a non-player actor.
   *
   * @param callable $resolve_entity_name
   *   fn(string $entity_id, array $game_state, array $dungeon_data): string
   * @param callable $queue_narration_event
   *   fn(int $campaign_id, array &$dungeon_data, array $event, ?string $room_id, ?array $game_state_override): array
   */
  public function resolvePendingEncounterDialogueTurn(
    string $entity_id,
    array $pending_dialogue,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    string $decision_intent,
    callable $resolve_entity_name,
    callable $queue_narration_event
  ): array {
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = $resolve_entity_name($entity_id, $game_state, $dungeon_data);
    $narration = trim((string) ($pending_dialogue['narrative'] ?? ''));
    if ($narration === '') {
      return ['events' => []];
    }

    $events = [
      GameEventLogger::buildEvent('npc_talk', 'encounter', $entity_id, [
        'message' => $narration,
        'target' => NULL,
        'decision_reason' => 'Responding to the active room conversation on this actor turn.',
        'decision_basis' => [
          'intent' => $decision_intent,
          'conversation_entity_ref' => (string) ($pending_dialogue['entity_ref'] ?? ''),
          'player_message' => (string) ($pending_dialogue['player_message'] ?? ''),
        ],
      ], $narration),
    ];
    $queue_narration_event($campaign_id, $dungeon_data, [
      'type' => 'talk',
      'speaker' => $actor_name,
      'speaker_type' => 'npc',
      'speaker_ref' => $entity_id,
      'content' => $narration,
      'visibility' => 'public',
      'mechanical_data' => [
        'actor_id' => $entity_id,
        'actor_name' => $actor_name,
        'room_id' => $resolved_room_id,
        'decision_reason' => 'Responding to the active room conversation on this actor turn.',
        'decision_basis' => [
          'intent' => $decision_intent,
          'conversation_entity_ref' => (string) ($pending_dialogue['entity_ref'] ?? ''),
          'player_message' => (string) ($pending_dialogue['player_message'] ?? ''),
        ],
      ],
    ], $resolved_room_id, $game_state);

    $remaining_before_end = max(0, ((int) ($game_state['turn']['actions_remaining'] ?? 0)) - 1);
    $game_state['turn']['actions_remaining'] = 0;
    $events[] = GameEventLogger::buildEvent('npc_choose_not_to_act', 'encounter', $entity_id, [
      'round' => $game_state['round'] ?? NULL,
      'room_id' => $resolved_room_id,
      'actor_name' => $actor_name,
      'actions_remaining' => 0,
      'reason' => 'No further action selected after replying on this turn.',
      'decision_reason' => 'Responded to the pending room conversation, then ended the turn.',
      'decision_basis' => [
        'intent' => $decision_intent,
        'remaining_actions_before_end' => $remaining_before_end,
      ],
    ], sprintf('%s chooses not to take any further actions.', $actor_name));
    $queue_narration_event($campaign_id, $dungeon_data, [
      'type' => 'choose_not_to_act',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => $entity_id,
      'content' => sprintf('%s chooses not to take any further actions.', $actor_name),
      'visibility' => 'public',
      'mechanical_data' => [
        'actor_id' => $entity_id,
        'actor_name' => $actor_name,
        'room_id' => $resolved_room_id,
        'actions_remaining' => 0,
        'decision_reason' => 'Responded to the pending room conversation, then ended the turn.',
        'decision_basis' => [
          'intent' => $decision_intent,
          'remaining_actions_before_end' => $remaining_before_end,
        ],
      ],
    ], $resolved_room_id, $game_state);

    return ['events' => $events];
  }

  public function resolveIntentActionType(array $intent_contract, int $step_index): string {
    $sequence = is_array($intent_contract['action_sequence'] ?? NULL) ? $intent_contract['action_sequence'] : [];
    if ($sequence === []) {
      return 'end_turn';
    }

    $resolved = $sequence[$step_index] ?? end($sequence);
    return is_string($resolved) && $resolved !== '' ? $resolved : 'end_turn';
  }

  public function isAiSeedActionCompatibleWithIntent(array $ai_seed_action, array $intent_contract): bool {
    $action_type = trim((string) ($ai_seed_action['type'] ?? ''));
    if ($action_type === '') {
      return FALSE;
    }

    $sequence = is_array($intent_contract['action_sequence'] ?? NULL) ? $intent_contract['action_sequence'] : [];
    if ($sequence === []) {
      return FALSE;
    }

    return in_array($action_type, $sequence, TRUE);
  }

  /**
   * Resolve deterministic target for an intent step.
   *
   * @param callable $choose_fallback_target
   *   fn(string $entity_id, array $game_state, int $campaign_id, string $action_type): ?string
   * @param callable $find_nearest_alive_player
   *   fn(string $entity_id, array $game_state): ?string
   */
  public function resolveIntentTarget(
    string $entity_id,
    array $game_state,
    int $campaign_id,
    string $action_type,
    array $intent_contract,
    callable $choose_fallback_target,
    callable $find_nearest_alive_player
  ): ?string {
    if (!in_array($action_type, ['strike', 'talk', 'stride'], TRUE)) {
      return NULL;
    }

    $target_strategy = (string) ($intent_contract['target_strategy'] ?? 'nearest');
    if ($target_strategy === 'weakest_adjacent') {
      return $choose_fallback_target($entity_id, $game_state, $campaign_id, $action_type);
    }

    return $find_nearest_alive_player($entity_id, $game_state);
  }

  protected function resolveAutoplayActionNarration(
    string $action_type,
    string $entity_id,
    ?string $target_id,
    array $game_state,
    array $dungeon_data,
    ?string $fallback_narration = NULL,
    array $action_context = []
  ): ?string {
    $fallback_narration = is_string($fallback_narration) ? trim($fallback_narration) : '';
    if ($fallback_narration !== '') {
      return $fallback_narration;
    }

    $actor_name = $this->resolveAutoplayEntityName($entity_id, $game_state, $dungeon_data);
    $target_name = $target_id !== NULL
      ? $this->resolveAutoplayEntityName($target_id, $game_state, $dungeon_data)
      : '';

    return match ($action_type) {
      'strike' => $this->buildAutoplayStrikeNarration($actor_name, $target_name, $action_context),
      'stride' => $this->buildAutoplayStrideNarration($actor_name, $target_name, $action_context),
      default => NULL,
    };
  }

  /**
   * Build strike narration including the resolved roll, weapon and damage.
   *
   * The mechanical detail comes from the authoritative strike result, so the
   * transcript reports what actually happened rather than a generic
   * "X attacks Y." line.
   */
  protected function buildAutoplayStrikeNarration(string $actor_name, string $target_name, array $action_context = []): string {
    $subject = $target_name !== '' ? $target_name : 'a nearby foe';
    $weapon_name = trim((string) ($action_context['weapon_name'] ?? ''));
    $with_weapon = $weapon_name !== '' ? sprintf(' with %s', $weapon_name) : '';

    $total = $action_context['total'] ?? NULL;
    $ac = $action_context['ac'] ?? NULL;
    $roll = $action_context['roll'] ?? NULL;
    $roll_suffix = '';
    if (is_numeric($total) && is_numeric($ac)) {
      $roll_suffix = is_numeric($roll)
        ? sprintf(' (attack %d vs AC %d, d20 %d)', (int) $total, (int) $ac, (int) $roll)
        : sprintf(' (attack %d vs AC %d)', (int) $total, (int) $ac);
    }

    $degree = strtolower(trim((string) ($action_context['degree'] ?? '')));
    $damage = $action_context['damage'] ?? NULL;
    $damage_type = trim((string) ($action_context['damage_type'] ?? ''));
    $damage_text = '';
    if (is_numeric($damage) && (int) $damage > 0) {
      $damage_text = $damage_type !== ''
        ? sprintf(' for %d %s damage', (int) $damage, $damage_type)
        : sprintf(' for %d damage', (int) $damage);
    }

    return match ($degree) {
      'critical_success' => sprintf('%s critically strikes %s%s%s!%s', $actor_name, $subject, $with_weapon, $damage_text, $roll_suffix),
      'success' => sprintf('%s strikes %s%s%s.%s', $actor_name, $subject, $with_weapon, $damage_text, $roll_suffix),
      'failure' => sprintf('%s swings at %s%s and misses.%s', $actor_name, $subject, $with_weapon, $roll_suffix),
      'critical_failure' => sprintf('%s fumbles an attack at %s%s!%s', $actor_name, $subject, $with_weapon, $roll_suffix),
      default => $target_name !== ''
        ? sprintf('%s attacks %s%s.%s', $actor_name, $target_name, $with_weapon, $roll_suffix)
        : sprintf('%s attacks%s.%s', $actor_name, $with_weapon, $roll_suffix),
    };
  }

  protected function buildAutoplayStrideNarration(string $actor_name, string $target_name, array $action_context = []): string {
    $from_hex = $this->formatAutoplayHexLabel(is_array($action_context['from_hex'] ?? NULL) ? $action_context['from_hex'] : NULL);
    $to_hex = $this->formatAutoplayHexLabel(is_array($action_context['to_hex'] ?? NULL) ? $action_context['to_hex'] : NULL);
    $movement_suffix = ($from_hex !== '' && $to_hex !== '')
      ? sprintf(' from %s to %s', $from_hex, $to_hex)
      : '';

    if ($target_name !== '') {
      return sprintf('%s moves toward %s%s.', $actor_name, $target_name, $movement_suffix);
    }

    return sprintf('%s repositions%s.', $actor_name, $movement_suffix);
  }

  protected function formatAutoplayHexLabel(?array $hex): string {
    if (!is_array($hex)) {
      return '';
    }

    $q = $hex['q'] ?? NULL;
    $r = $hex['r'] ?? NULL;
    if (!is_numeric($q) || !is_numeric($r)) {
      return '';
    }

    return sprintf('(%d,%d)', (int) $q, (int) $r);
  }

  protected function resolveAutoplayEntityName(string $entity_id, array $game_state, array $dungeon_data): string {
    $entity_id = trim($entity_id);
    if ($entity_id === '') {
      return 'Unknown';
    }

    foreach ((array) ($game_state['initiative_order'] ?? []) as $combatant) {
      if (!is_array($combatant) || trim((string) ($combatant['entity_id'] ?? '')) !== $entity_id) {
        continue;
      }
      $name = trim((string) ($combatant['name'] ?? $combatant['display_name'] ?? ''));
      if ($name !== '') {
        return $name;
      }
    }

    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $candidate_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($candidate_id !== $entity_id) {
        continue;
      }
      $name = trim((string) (
        $entity['state']['metadata']['display_name']
        ?? $entity['entity_ref']['content_id']
        ?? ''
      ));
      if ($name !== '') {
        return $name;
      }
    }

    return $entity_id;
  }

  protected function resolveStrideDestinationHex(
    string $entity_id,
    ?string $target_id,
    array $game_state,
    array $dungeon_data = [],
    array $intent_contract = []
  ): ?array {
    $target_id = is_string($target_id) ? trim($target_id) : '';
    if ($target_id === '') {
      return NULL;
    }

    $actor = $this->findCombatantByEntityId($entity_id, $game_state);
    $target = $this->findCombatantByEntityId($target_id, $game_state);
    if (!$actor || !$target) {
      return NULL;
    }

    $origin_hex = $this->resolveCombatantHex($entity_id, $actor, $dungeon_data);
    $target_hex = $this->resolveCombatantHex($target_id, $target, $dungeon_data);
    if ($origin_hex === NULL || $target_hex === NULL) {
      return NULL;
    }
    $intent = strtolower(trim((string) ($intent_contract['intent'] ?? 'aggressive_engage')));
    $movement_budget = $this->resolveStrideBudgetFeet($actor);
    if ($movement_budget <= 0) {
      return NULL;
    }
    $movement_scope = $this->buildMovementScope($dungeon_data);

    $reachable_hexes = $this->collectReachableStrideHexes($entity_id, $origin_hex, $movement_budget, $game_state, $dungeon_data, $movement_scope);
    if ($reachable_hexes === []) {
      return NULL;
    }

    return $this->selectBestStrideGoalHex($reachable_hexes, $origin_hex, $target_hex, $intent);
  }

  protected function findCombatantByEntityId(string $entity_id, array $game_state): ?array {
    $entity_id = trim($entity_id);
    if ($entity_id === '') {
      return NULL;
    }

    foreach ((array) ($game_state['initiative_order'] ?? []) as $combatant) {
      if (!is_array($combatant)) {
        continue;
      }
      if (trim((string) ($combatant['entity_id'] ?? '')) === $entity_id) {
        return $combatant;
      }
    }

    return NULL;
  }

  protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {
    $x1 = $q1;
    $z1 = $r1;
    $y1 = -$x1 - $z1;
    $x2 = $q2;
    $z2 = $r2;
    $y2 = -$x2 - $z2;
    return max(abs($x1 - $x2), abs($y1 - $y2), abs($z1 - $z2));
  }

  /**
   * Resolve the current stride budget in feet for one actor.
   */
  protected function resolveStrideBudgetFeet(array $actor): int {
    if ($this->movementResolver instanceof MovementResolverService) {
      $resolved_speed = $this->movementResolver->getCreatureSpeed($actor, 'land');
      if ($resolved_speed > 0) {
        return $resolved_speed;
      }
    }

    $fallback_speed = (int) ($actor['speed'] ?? 25);
    return $fallback_speed > 0 ? $fallback_speed : 25;
  }

  /**
   * Collect all reachable unoccupied stride destinations within the move budget.
   *
   * @return array<int,array{hex:array{q:int,r:int},cost:int}>
   *   Reachable candidate destinations with path cost.
   */
  protected function collectReachableStrideHexes(
    string $entity_id,
    array $origin_hex,
    int $movement_budget,
    array $game_state,
    array $dungeon_data,
    array $movement_scope = []
  ): array {
    if ($movement_budget < 5) {
      return [];
    }

    $occupied = $this->buildOccupiedHexIndex($entity_id, $game_state, $dungeon_data);
    $best_cost_by_hex = [
      $origin_hex['q'] . ':' . $origin_hex['r'] => 0,
    ];
    $queue = [
      ['hex' => $origin_hex, 'cost' => 0],
    ];
    $reachable = [];

    while ($queue !== []) {
      $current = array_shift($queue);
      if (!is_array($current)) {
        continue;
      }

      $current_hex = is_array($current['hex'] ?? NULL) ? $current['hex'] : NULL;
      $current_cost = (int) ($current['cost'] ?? 0);
      if ($current_hex === NULL) {
        continue;
      }

      foreach ($this->buildAdjacentHexes($current_hex['q'], $current_hex['r']) as $candidate_hex) {
        $candidate_key = $candidate_hex['q'] . ':' . $candidate_hex['r'];
        if (!empty($occupied[$candidate_key])) {
          continue;
        }
        if (!$this->isStrideHexPassable($candidate_hex, $movement_scope)) {
          continue;
        }

        $step_cost = $this->resolveStrideStepCost($current_hex, $candidate_hex, $movement_scope);
        $candidate_cost = $current_cost + $step_cost;
        if ($step_cost <= 0 || $candidate_cost > $movement_budget) {
          continue;
        }

        $known_cost = $best_cost_by_hex[$candidate_key] ?? NULL;
        if ($known_cost !== NULL && $candidate_cost >= $known_cost) {
          continue;
        }

        $best_cost_by_hex[$candidate_key] = $candidate_cost;
        $queue[] = [
          'hex' => $candidate_hex,
          'cost' => $candidate_cost,
        ];
        $reachable[$candidate_key] = [
          'hex' => $candidate_hex,
          'cost' => $candidate_cost,
        ];
      }
    }

    return array_values($reachable);
  }

  /**
   * Select the best reachable stride destination for the current movement goal.
   */
  protected function selectBestStrideGoalHex(
    array $reachable_hexes,
    array $origin_hex,
    array $target_hex,
    string $intent
  ): ?array {
    $best = NULL;
    $best_score = NULL;

    foreach ($reachable_hexes as $candidate) {
      $candidate_hex = is_array($candidate['hex'] ?? NULL) ? $candidate['hex'] : NULL;
      if ($candidate_hex === NULL) {
        continue;
      }

      $distance_to_target = $this->hexDistance($candidate_hex['q'], $candidate_hex['r'], $target_hex['q'], $target_hex['r']);
      $distance_from_origin = $this->hexDistance($origin_hex['q'], $origin_hex['r'], $candidate_hex['q'], $candidate_hex['r']);
      $path_cost = (int) ($candidate['cost'] ?? 0);

      $score = match ($intent) {
        'self_preserve', 'flee', 'deescalate' => [
          $distance_to_target,
          $distance_from_origin,
          $path_cost,
          -abs((int) $candidate_hex['q']),
          -abs((int) $candidate_hex['r']),
        ],
        default => [
          $distance_to_target <= 1 ? 1 : 0,
          -$distance_to_target,
          $distance_from_origin,
          $path_cost,
          -abs((int) $candidate_hex['q']),
          -abs((int) $candidate_hex['r']),
        ],
      };

      if ($best_score === NULL || $score > $best_score) {
        $best = $candidate_hex;
        $best_score = $score;
      }
    }

    return $best;
  }

  /**
   * Resolve the best action type for the current state and turn goal.
   */
  protected function resolveGoalAlignedActionType(
    string $planned_action_type,
    string $entity_id,
    ?string $target_id,
    array $game_state,
    array $dungeon_data,
    array $intent_contract
  ): string {
    $intent = strtolower(trim((string) ($intent_contract['intent'] ?? '')));
    $aggressive_intents = ['aggressive_engage', 'finish_weakest'];
    if (!in_array($intent, $aggressive_intents, TRUE) || $target_id === NULL || $target_id === '') {
      return $planned_action_type;
    }

    $can_reach_target = $this->isTargetWithinDefaultStrikeRange($entity_id, $target_id, $game_state, $dungeon_data);
    $can_stride_closer = $this->resolveStrideDestinationHex($entity_id, $target_id, $game_state, $dungeon_data, $intent_contract) !== NULL;

    if ($can_reach_target) {
      return 'strike';
    }

    if ($can_stride_closer) {
      return 'stride';
    }

    return $planned_action_type;
  }

  /**
   * Build adjacent axial hexes for one origin hex.
   *
   * @return array<int,array{q:int,r:int}>
   *   Neighboring axial coordinates.
   */
  protected function buildAdjacentHexes(int $q, int $r): array {
    return [
      ['q' => $q + 1, 'r' => $r],
      ['q' => $q + 1, 'r' => $r - 1],
      ['q' => $q, 'r' => $r - 1],
      ['q' => $q - 1, 'r' => $r],
      ['q' => $q - 1, 'r' => $r + 1],
      ['q' => $q, 'r' => $r + 1],
    ];
  }

  /**
   * Build occupied hex index from live encounter participants.
   *
   * Defeated combatants are included: a downed body still occupies its hex and
   * must not be treated as a free stride destination, otherwise two tokens end
   * up rendered on the same hex.
   *
   * @return array<string,bool>
   *   Occupancy keyed by "q:r".
   */
  protected function buildOccupiedHexIndex(string $entity_id, array $game_state, array $dungeon_data = []): array {
    $occupied = [];
    foreach ((array) ($game_state['initiative_order'] ?? []) as $combatant) {
      if (!is_array($combatant)) {
        continue;
      }

      $combatant_id = trim((string) ($combatant['entity_id'] ?? ''));
      if ($combatant_id === '' || $combatant_id === $entity_id) {
        continue;
      }

      $hex = $this->resolveCombatantHex($combatant_id, $combatant, $dungeon_data);
      if ($hex === NULL) {
        continue;
      }
      $occupied[$hex['q'] . ':' . $hex['r']] = TRUE;
    }
    return $occupied;
  }

  /**
   * Resolve one stride step cost between adjacent hexes.
   */
  protected function resolveStrideStepCost(array $from_hex, array $to_hex, array $movement_scope): int {
    if ($this->movementResolver instanceof MovementResolverService) {
      $cost_info = $this->movementResolver->calculateMovementCost($from_hex, $to_hex, $movement_scope, 0, 'land');
      $resolved_cost = (int) ($cost_info['cost'] ?? 0);
      if ($resolved_cost > 0) {
        return $resolved_cost;
      }
    }

    return 5;
  }

  /**
   * Whether a candidate stride hex is passable when map metadata exists.
   */
  protected function isStrideHexPassable(array $hex, array $movement_scope): bool {
    if (!($this->movementResolver instanceof MovementResolverService)) {
      // Treating every hex as passable here silently diverges from the
      // authoritative movement validator used by stride execution, which then
      // rejects the chosen destination and burns the actor's whole turn with
      // no events. Fail loudly instead of masking the missing dependency.
      throw new \RuntimeException(
        'Actor autoplay movement contract violation: MovementResolverService is not injected into ActorAutoplayCoordinator, so stride destinations cannot be validated against room bounds. Wire "@dungeoncrawler_content.movement_resolver" into dungeoncrawler_content.actor_autoplay_coordinator.'
      );
    }
    return $this->movementResolver->isPassable($hex, $movement_scope);
  }

  /**
   * Determine whether a target is already in default melee strike range.
   */
  protected function isTargetWithinDefaultStrikeRange(string $entity_id, string $target_id, array $game_state, array $dungeon_data = []): bool {
    $actor = $this->findCombatantByEntityId($entity_id, $game_state);
    $target = $this->findCombatantByEntityId($target_id, $game_state);
    if (!$actor || !$target || !empty($target['is_defeated'])) {
      return FALSE;
    }

    $actor_hex = $this->resolveCombatantHex($entity_id, $actor, $dungeon_data);
    $target_hex = $this->resolveCombatantHex($target_id, $target, $dungeon_data);
    if ($actor_hex === NULL || $target_hex === NULL) {
      return FALSE;
    }

    return $this->hexDistance(
      $actor_hex['q'],
      $actor_hex['r'],
      $target_hex['q'],
      $target_hex['r']
    ) <= 1;
  }

  protected function resolveCombatantHex(string $entity_id, ?array $combatant, array $dungeon_data): ?array {
    $initiative_hex = $this->normalizeAutoplayHex([
      'q' => $combatant['position_q'] ?? NULL,
      'r' => $combatant['position_r'] ?? NULL,
    ]);
    if ($initiative_hex !== NULL) {
      return $initiative_hex;
    }

    $entity_id = trim($entity_id);
    if ($entity_id === '') {
      return NULL;
    }

    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $candidate_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($candidate_id !== $entity_id) {
        continue;
      }

      $entity_hex = $this->normalizeAutoplayHex($entity['placement']['hex'] ?? NULL);
      if ($entity_hex !== NULL) {
        return $entity_hex;
      }
    }

    return NULL;
  }

  protected function normalizeAutoplayHex(?array $hex): ?array {
    if (!is_array($hex)) {
      return NULL;
    }

    $q = $hex['q'] ?? NULL;
    $r = $hex['r'] ?? NULL;
    if (!is_numeric($q) || !is_numeric($r)) {
      return NULL;
    }

    return [
      'q' => (int) $q,
      'r' => (int) $r,
    ];
  }

  protected function hexesEqual(?array $left, ?array $right): bool {
    if (!is_array($left) || !is_array($right)) {
      return FALSE;
    }

    return (int) ($left['q'] ?? PHP_INT_MIN) === (int) ($right['q'] ?? PHP_INT_MAX)
      && (int) ($left['r'] ?? PHP_INT_MIN) === (int) ($right['r'] ?? PHP_INT_MAX);
  }

  /**
   * Build minimal movement lookup scope from runtime dungeon payload.
   */
  protected function buildMovementScope(array $dungeon_data): array {
    if ($this->movementResolver instanceof MovementResolverService) {
      return $this->movementResolver->buildMovementScope($dungeon_data);
    }

    return [
      '__scope_type' => 'movement',
      'active_room_id' => (string) ($dungeon_data['active_room_id'] ?? $dungeon_data['current_room_id'] ?? ''),
      'room_hexes' => [],
      'hexes' => is_array($dungeon_data['hexes'] ?? NULL) ? $dungeon_data['hexes'] : [],
      'is_underwater' => !empty($dungeon_data['is_underwater']),
    ];
  }

}
