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
  protected ConfigFactoryInterface $configFactory;
  protected LoggerInterface $logger;

  public function __construct(
    EncounterAiIntegrationService $encounter_ai_service,
    HazardService $hazard_service,
    ?RoomChatService $room_chat_service,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->encounterAiService = $encounter_ai_service;
    $this->hazardService = $hazard_service;
    $this->roomChatService = $room_chat_service;
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
    callable $check_entity_defeated,
    callable $find_nearest_alive_player,
    callable $build_choose_not_to_act_events,
    callable $resolve_pending_dialogue_turn
  ): array {
    $events = [];
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
      return ['events' => $events];
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
      $action_type = (string) ($plan_step['action_type'] ?? '');
      if ($action_type === '') {
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

      switch ($action_type) {
        case 'strike':
          if ($target) {
            $strike_result = $process_strike($encounter_id, $entity_id, $target, $game_state, $dungeon_data, $campaign_id);
            $events[] = GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [
              'target' => $target,
              'roll' => $strike_result['roll'] ?? NULL,
              'degree' => $strike_result['degree'] ?? NULL,
              'damage' => $strike_result['damage'] ?? NULL,
              'decision_reason' => $decision_reason,
              'decision_basis' => $decision_basis,
            ], $narration, $target);
            $check_entity_defeated($target, $entity_id, $game_state, $events, $dungeon_data, $campaign_id);
          }
          break;

        case 'stride':
          $nearest = $find_nearest_alive_player($entity_id, $game_state);
          $events[] = GameEventLogger::buildEvent('npc_stride', 'encounter', $entity_id, [
            'toward' => $nearest,
            'decision_reason' => $decision_reason,
            'decision_basis' => $decision_basis,
          ], $narration);
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
    if (!in_array($action_type, ['strike', 'talk'], TRUE)) {
      return NULL;
    }

    $target_strategy = (string) ($intent_contract['target_strategy'] ?? 'nearest');
    if ($target_strategy === 'weakest_adjacent') {
      return $choose_fallback_target($entity_id, $game_state, $campaign_id, $action_type);
    }

    return $find_nearest_alive_player($entity_id, $game_state);
  }

}
