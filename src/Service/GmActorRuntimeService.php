<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\GmSubsystem\ActorResponseProjector;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\ActorTurnContextLoader;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\LegacyRoomChatCompatibilityAdapter;

/**
 * Runtime execution boundary for GM actor orchestration.
 */
class GmActorRuntimeService {

  protected const RUNTIME_CONTRACT_VERSION = 'gm-actor-runtime-v1';

  protected GameCoordinatorService $coordinator;
  protected GmActorChatTransportService $chatTransport;
  protected RuntimeBootstrapService $runtimeBootstrap;
  protected ActorTurnContextLoader $actorTurnContextLoader;
  protected ActorResponseProjector $actorResponseProjector;
  protected LegacyRoomChatCompatibilityAdapter $legacyCompatibilityAdapter;

  public function __construct(
    GameCoordinatorService $coordinator,
    GmActorChatTransportService $chat_transport,
    RuntimeBootstrapService $runtime_bootstrap,
    ?ActorTurnContextLoader $actor_turn_context_loader = NULL,
    ?ActorResponseProjector $actor_response_projector = NULL,
    ?LegacyRoomChatCompatibilityAdapter $legacy_compatibility_adapter = NULL
  ) {
    $this->coordinator = $coordinator;
    $this->chatTransport = $chat_transport;
    $this->runtimeBootstrap = $runtime_bootstrap;
    $this->actorTurnContextLoader = $actor_turn_context_loader ?? new ActorTurnContextLoader();
    $this->actorResponseProjector = $actor_response_projector ?? new ActorResponseProjector();
    $this->legacyCompatibilityAdapter = $legacy_compatibility_adapter ?? new LegacyRoomChatCompatibilityAdapter();
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
    array $route = [],
    array $options = []
  ): array {
    $overall_started_at = hrtime(true);
    $timings = [];
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      throw new \InvalidArgumentException('GM actor runtime requires actor_id.', 400);
    }
    if ($character_id <= 0) {
      throw new \InvalidArgumentException('GM actor runtime requires character_id.', 400);
    }
    $stage_started_at = hrtime(true);
    $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $character_id);
    $timings['ensure_runtime_ready_ms'] = $this->elapsedMs($stage_started_at);

    $stage_started_at = hrtime(true);
    $resolved_speaker = trim((string) $this->coordinator->resolveActorDisplayName($campaign_id, $actor_id));
    $timings['resolve_actor_display_name_ms'] = $this->elapsedMs($stage_started_at);
    if ($resolved_speaker === '') {
      $resolved_speaker = trim($speaker) !== '' ? trim($speaker) : 'Player';
    }
    $response_mode = $this->resolveResponseMode($route, $options);
    $include_legacy_overlay = $this->shouldIncludeLegacyOverlay($response_mode, $options);

    $stage_started_at = hrtime(true);
    $chat_result = $this->chatTransport->postValidatedPlayerRoomChat(
      $campaign_id,
      $room_id,
      $resolved_speaker,
      $message,
      $character_id,
      $suppress_gm,
      [
        'response_mode' => $response_mode,
        'include_legacy_overlay' => $include_legacy_overlay,
      ]
    );
    $timings['post_validated_player_room_chat_ms'] = $this->elapsedMs($stage_started_at);

    $stage_started_at = hrtime(true);
    $state = $this->coordinator->getRuntimeReadState($campaign_id, $actor_id);
    $timings['load_runtime_read_state_ms'] = $this->elapsedMs($stage_started_at);
    $stage_started_at = hrtime(true);
    $action_availability = $this->coordinator->getActionAvailabilityForActor($campaign_id, $actor_id);
    $timings['load_action_availability_ms'] = $this->elapsedMs($stage_started_at);
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
    $stage_started_at = hrtime(true);
    $actor_turn_context = $this->actorTurnContextLoader->load(
      $state,
      $action_availability,
      $actor_id,
      $character_id,
      $resolved_speaker
    );
    $timings['build_actor_turn_context_ms'] = $this->elapsedMs($stage_started_at);
    $stage_started_at = hrtime(true);
    $actor_response = $this->actorResponseProjector->project($chat_result, $actor_turn_context);
    $timings['project_actor_response_ms'] = $this->elapsedMs($stage_started_at);
    $chat_result['actor_response'] = $actor_response;
    if ($include_legacy_overlay) {
      $stage_started_at = hrtime(true);
      $chat_result['compatibility_overlay'] = $this->legacyCompatibilityAdapter->buildOverlay($chat_result);
      $timings['build_legacy_overlay_ms'] = $this->elapsedMs($stage_started_at);
    }
    $chat_result['response_mode'] = $response_mode;
    if ($response_mode === 'actor_scoped') {
      $actor_scoped = $actor_response;
      if ($include_legacy_overlay) {
        $actor_scoped = $actor_scoped + $chat_result['compatibility_overlay'];
      }
      if (isset($chat_result['gm_subsystem']) && !isset($actor_scoped['gm_subsystem']) && is_array($chat_result['gm_subsystem'])) {
        $actor_scoped['gm_subsystem'] = $chat_result['gm_subsystem'];
      }
      return $this->appendInvocationTiming($actor_scoped, 'gm_actor_runtime', $timings, $overall_started_at);
    }
    if ($response_mode === 'dual_transition') {
      unset($chat_result['dungeon_data'], $chat_result['debug_trace']);
    }
    if (!array_key_exists('runtime_snapshot', $chat_result)) {
      $chat_result['runtime_snapshot'] = is_array($actor_response['runtime_snapshot'] ?? NULL)
        ? $actor_response['runtime_snapshot']
        : [];
    }

    return $this->appendInvocationTiming($chat_result, 'gm_actor_runtime', $timings, $overall_started_at);
  }

  /**
   * Resolve room-chat response mode for one actor turn.
   */
  protected function resolveResponseMode(array $route, array $options): string {
    $intent_params = is_array($route['intent']['params'] ?? NULL)
      ? $route['intent']['params']
      : [];
    $candidate = trim((string) (
      $options['response_mode']
      ?? $route['response_mode']
      ?? $intent_params['response_mode']
      ?? ''
    ));
    $candidate = strtolower($candidate);
    if ($candidate === '') {
      return 'actor_scoped';
    }
    if (!in_array($candidate, ['legacy', 'dual_transition', 'actor_scoped'], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Invalid room chat response mode "%s".', $candidate), 400);
    }

    return $candidate;
  }

  /**
   * Decide whether the explicit compatibility overlay is required.
   */
  protected function shouldIncludeLegacyOverlay(string $response_mode, array $options): bool {
    return $response_mode === 'legacy';
  }

  /**
   * Convert an hrtime timestamp to elapsed milliseconds.
   */
  protected function elapsedMs(int $started_at): float {
    return round((hrtime(true) - $started_at) / 1000000, 2);
  }

  /**
   * Attach invocation timing data to one response payload.
   *
   * @param array<string,mixed> $payload
   * @param array<string,float> $stages
   *
   * @return array<string,mixed>
   */
  protected function appendInvocationTiming(array $payload, string $scope, array $stages, int $overall_started_at): array {
    $timing = is_array($payload['invocation_timing'] ?? NULL) ? $payload['invocation_timing'] : [];
    $timing[$scope] = [
      'total_ms' => $this->elapsedMs($overall_started_at),
      'stages_ms' => $stages,
    ];
    $payload['invocation_timing'] = $timing;
    return $payload;
  }

}
