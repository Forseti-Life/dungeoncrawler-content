<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Runtime execution boundary for GM actor orchestration.
 */
class GmActorRuntimeService {

  protected const RUNTIME_CONTRACT_VERSION = 'gm-actor-runtime-v1';

  protected GameCoordinatorService $coordinator;
  protected GmActorChatTransportService $chatTransport;
  protected RuntimeBootstrapService $runtimeBootstrap;

  public function __construct(GameCoordinatorService $coordinator, GmActorChatTransportService $chat_transport, RuntimeBootstrapService $runtime_bootstrap) {
    $this->coordinator = $coordinator;
    $this->chatTransport = $chat_transport;
    $this->runtimeBootstrap = $runtime_bootstrap;
  }

  /**
   * Execute one room-chat GM actor turn.
   */
  public function handlePlayerRoomChat(
    int $campaign_id,
    string $room_id,
    string $actor_id,
    int $character_id,
    string $message,
    bool $suppress_gm = FALSE,
    string $speaker = '',
    array $route = []
  ): array {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      throw new \InvalidArgumentException('GM actor runtime requires actor_id.', 400);
    }
    if ($character_id <= 0) {
      throw new \InvalidArgumentException('GM actor runtime requires character_id.', 400);
    }
    $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $character_id);

    $resolved_speaker = trim((string) $this->coordinator->resolveActorDisplayName($campaign_id, $actor_id));
    if ($resolved_speaker === '') {
      $resolved_speaker = trim($speaker) !== '' ? trim($speaker) : 'Player';
    }

    $chat_result = $this->chatTransport->postValidatedPlayerRoomChat(
      $campaign_id,
      $room_id,
      $resolved_speaker,
      $message,
      $character_id,
      $suppress_gm
    );

    $state = $this->coordinator->getRuntimeReadState($campaign_id, $actor_id);
    $action_availability = $this->coordinator->getActionAvailabilityForActor($campaign_id, $actor_id);
    foreach (['game_state', 'available_actions', 'action_contract', 'events', 'phase', 'encounter_id', 'round', 'turn', 'state_version', 'active_room_id'] as $response_key) {
      if (array_key_exists($response_key, $state)) {
        $chat_result[$response_key] = $state[$response_key];
      }
    }
    $chat_result['available_actions'] = is_array($action_availability['available_actions'] ?? NULL)
      ? $action_availability['available_actions']
      : [];
    $chat_result['action_contract'] = is_array($action_availability['action_contract'] ?? NULL)
      ? $action_availability['action_contract']
      : NULL;
    $chat_result['action_option_families'] = is_array($chat_result['action_contract']['action_option_families'] ?? NULL)
      ? $chat_result['action_contract']['action_option_families']
      : [];
    if (isset($chat_result['message']) && !is_array($chat_result['message']) && isset($chat_result['speaker'])) {
      $chat_result['message'] = [
        'speaker' => (string) $chat_result['speaker'],
        'message' => (string) $chat_result['message'],
        'type' => 'player',
      ];
    }

    $chat_result['gm_actor_runtime'] = [
      'contract_version' => self::RUNTIME_CONTRACT_VERSION,
      'route' => (string) ($route['route'] ?? 'free_player_room_chat'),
      'route_family' => (string) ($route['route_family'] ?? 'gm_backstop_chat'),
      'workflow' => (string) ($route['workflow'] ?? 'authoritative_room_chat'),
      'resolved_room_id' => $room_id,
      'actor_id' => $actor_id,
      'character_id' => $character_id,
    ];

    return $chat_result;
  }

}
