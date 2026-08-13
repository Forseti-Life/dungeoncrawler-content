<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\GmSubsystem\ActorResponseProjector;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\ActorTurnContextLoader;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\LegacyRoomChatCompatibilityAdapter;
use Drupal\dungeoncrawler_content\Service\RuntimeBootstrapService;

/**
 * GM subsystem facade for player room-chat orchestration.
 *
 * This is the first explicit subsystem boundary between transport/controllers
 * and the deterministic-engine / GM-backstop decision flow.
 */
class GameMasterSubsystemService {

  protected const ENVELOPE_CONTRACT_VERSION = 'gm_subsystem_route_v1';
  protected const WORKFLOW_AUTHORITATIVE_ROOM_ACTION = 'authoritative_room_action';
  protected const WORKFLOW_AUTHORITATIVE_ROOM_CHAT = 'authoritative_room_chat';
  protected const ROUTE_DETERMINISTIC_TURN_CONTROL = 'deterministic_turn_control';
  protected const ROUTE_FREE_PLAYER_ROOM_CHAT = 'free_player_room_chat';
  protected const ROUTE_FAMILY_DETERMINISTIC_ACTION = 'deterministic_action';
  protected const ROUTE_FAMILY_GM_BACKSTOP_CHAT = 'gm_backstop_chat';
  protected const HANDOFF_REASON_DETERMINISTIC_TURN_CONTROL_PHRASE = 'deterministic_turn_control_phrase';
  protected const HANDOFF_REASON_DETERMINISTIC_END_TURN_PHRASE = 'deterministic_end_turn_phrase';
  protected const HANDOFF_REASON_NO_TURN_CONTROL_MATCH = 'no_deterministic_turn_control_match';

  protected GameCoordinatorService $coordinator;
  protected GmActorHarnessService $gmActorHarness;
  protected ActorTurnContextLoader $actorTurnContextLoader;
  protected ActorResponseProjector $actorResponseProjector;
  protected LegacyRoomChatCompatibilityAdapter $legacyCompatibilityAdapter;
  protected ?RuntimeBootstrapService $runtimeBootstrap;

  /**
   * Constructor.
   */
  public function __construct(
    GameCoordinatorService $coordinator,
    GmActorHarnessService $gm_actor_harness,
    ?ActorTurnContextLoader $actor_turn_context_loader = NULL,
    ?ActorResponseProjector $actor_response_projector = NULL,
    ?LegacyRoomChatCompatibilityAdapter $legacy_compatibility_adapter = NULL,
    ?RuntimeBootstrapService $runtime_bootstrap = NULL
  ) {
    $this->coordinator = $coordinator;
    $this->gmActorHarness = $gm_actor_harness;
    $this->actorTurnContextLoader = $actor_turn_context_loader ?? new ActorTurnContextLoader();
    $this->actorResponseProjector = $actor_response_projector ?? new ActorResponseProjector();
    $this->legacyCompatibilityAdapter = $legacy_compatibility_adapter ?? new LegacyRoomChatCompatibilityAdapter();
    $this->runtimeBootstrap = $runtime_bootstrap;
  }

  /**
   * Handle one player room-chat message through deterministic-first orchestration.
   */
  public function handlePlayerRoomChat(
    int $campaign_id,
    string $requested_room_id,
    ?int $character_id,
    string $message,
    bool $defer_npc_interjections = FALSE,
    bool $suppress_gm = FALSE,
    string $speaker = '',
    array $options = []
  ): array {
    $overall_started_at = hrtime(true);
    $timings = [];
    if ($character_id === NULL || $character_id <= 0) {
      throw new \InvalidArgumentException('character_id is required for player room chat.', 400);
    }

    if ($this->runtimeBootstrap !== NULL) {
      $stage_started_at = hrtime(true);
      $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $character_id);
      $timings['ensure_runtime_ready_ms'] = $this->elapsedMs($stage_started_at);
    }

    $stage_started_at = hrtime(true);
    $actor_id = $this->coordinator->resolveActorIdForCharacterId($campaign_id, $character_id);
    $timings['resolve_actor_id_ms'] = $this->elapsedMs($stage_started_at);
    if (!$actor_id) {
      throw new \InvalidArgumentException('Unable to resolve encounter actor for character.', 409);
    }

    $stage_started_at = hrtime(true);
    $active_room_id = $this->coordinator->getActiveRoomId($campaign_id, $actor_id);
    $timings['resolve_active_room_ms'] = $this->elapsedMs($stage_started_at);
    if ($active_room_id !== NULL && $active_room_id !== '' && $active_room_id !== $requested_room_id) {
      throw new \InvalidArgumentException('Cannot post room chat: requested room does not match active room.', 409);
    }

    $stage_started_at = hrtime(true);
    $route = $this->buildPlayerRoomChatRouteEnvelope(
      $campaign_id,
      $requested_room_id,
      $actor_id,
      $character_id,
      $message,
      $defer_npc_interjections,
      $suppress_gm,
      $speaker,
      $options
    );
    $timings['build_route_envelope_ms'] = $this->elapsedMs($stage_started_at);

    if (!empty($route['deterministic'])) {
      $stage_started_at = hrtime(true);
      $action_response = $this->coordinator->processAction($campaign_id, $route['intent']);
      $timings['deterministic_process_action_ms'] = $this->elapsedMs($stage_started_at);
      if (empty($action_response['success'])) {
        $error = trim((string) (
          $action_response['error']
          ?? ($action_response['result']['error'] ?? NULL)
          ?? 'Talk failed.'
        ));
        throw new \InvalidArgumentException($error !== '' ? $error : 'Talk failed.', 409);
      }

      $talk_result = is_array($action_response['result'] ?? NULL) ? $action_response['result'] : [];
      foreach (['game_state', 'available_actions', 'action_contract', 'events', 'phase_transition', 'dungeon_data'] as $response_key) {
        if (array_key_exists($response_key, $action_response)) {
          $talk_result[$response_key] = $action_response[$response_key];
        }
      }
      if (isset($talk_result['chat_message']) && is_array($talk_result['chat_message'])) {
        $talk_result['message'] = $talk_result['chat_message'];
        unset($talk_result['chat_message']);
      }
      $stage_started_at = hrtime(true);
      $talk_result['gm_subsystem'] = $this->buildResponseEnvelope($route, $active_room_id ?: $requested_room_id);
      $timings['build_response_envelope_ms'] = $this->elapsedMs($stage_started_at);
      $stage_started_at = hrtime(true);
      $projected = $this->applyActorResponseProjection(
        $talk_result,
        $campaign_id,
        $actor_id,
        $character_id,
        (string) ($talk_result['message']['speaker'] ?? $speaker),
        $route,
        $options
      );
      $timings['apply_actor_response_projection_ms'] = $this->elapsedMs($stage_started_at);
      return $this->appendInvocationTiming($projected, 'gm_subsystem', $timings, $overall_started_at);
    }

    $room_id = $active_room_id ?: $requested_room_id;
    $requested_speaker = trim($speaker);
    $stage_started_at = hrtime(true);
    $speaker = trim((string) $this->coordinator->resolveActorDisplayName($campaign_id, $actor_id));
    $timings['resolve_actor_display_name_ms'] = $this->elapsedMs($stage_started_at);
    if ($speaker === '') {
      $speaker = $requested_speaker !== '' ? $requested_speaker : 'Player';
    }
    $route['intent']['params']['speaker'] = $speaker;
    $stage_started_at = hrtime(true);
    $chat_result = $this->gmActorHarness->handlePlayerRoomChat(
      $campaign_id,
      $room_id,
      $actor_id,
      $character_id,
      $message,
      $suppress_gm,
      $speaker,
      $route,
      $options
    );
    $timings['gm_actor_harness_ms'] = $this->elapsedMs($stage_started_at);
    $stage_started_at = hrtime(true);
    $chat_result['gm_subsystem'] = $this->buildResponseEnvelope($route, $room_id);
    $timings['build_response_envelope_ms'] = $this->elapsedMs($stage_started_at);

    return $this->appendInvocationTiming($chat_result, 'gm_subsystem', $timings, $overall_started_at);
  }

  /**
   * Apply actor-scoped response projection and transition-mode overlays.
   */
  protected function applyActorResponseProjection(
    array $result,
    int $campaign_id,
    string $actor_id,
    ?int $character_id,
    string $speaker,
    array $route,
    array $options
  ): array {
    $overall_started_at = hrtime(true);
    $timings = [];
    $stage_started_at = hrtime(true);
    $state = $this->coordinator->getRuntimeReadState($campaign_id, $actor_id);
    $timings['load_runtime_state_ms'] = $this->elapsedMs($stage_started_at);
    $stage_started_at = hrtime(true);
    $action_availability = $this->coordinator->getActionAvailabilityForActor($campaign_id, $actor_id);
    $timings['load_action_availability_ms'] = $this->elapsedMs($stage_started_at);
    $stage_started_at = hrtime(true);
    $actor_turn_context = $this->actorTurnContextLoader->load(
      $state,
      $action_availability,
      $actor_id,
      $character_id,
      $speaker
    );
    $timings['build_actor_turn_context_ms'] = $this->elapsedMs($stage_started_at);
    $stage_started_at = hrtime(true);
    $actor_response = $this->actorResponseProjector->project($result, $actor_turn_context);
    $timings['project_actor_response_ms'] = $this->elapsedMs($stage_started_at);
    $result['actor_response'] = $actor_response;

    $response_mode = $this->resolveResponseMode($route, $options);
    $include_legacy_overlay = $this->shouldIncludeLegacyOverlay($response_mode, $options);
    if ($include_legacy_overlay) {
      $stage_started_at = hrtime(true);
      $result['compatibility_overlay'] = $this->legacyCompatibilityAdapter->buildOverlay($result);
      $timings['build_legacy_overlay_ms'] = $this->elapsedMs($stage_started_at);
    }
    $result['response_mode'] = $response_mode;

    if ($response_mode !== 'actor_scoped') {
      if (!array_key_exists('runtime_snapshot', $result)) {
        $result['runtime_snapshot'] = is_array($actor_response['runtime_snapshot'] ?? NULL)
          ? $actor_response['runtime_snapshot']
          : [];
      }
      if (!array_key_exists('available_actions', $result)) {
        $result['available_actions'] = is_array($actor_response['available_actions'] ?? NULL)
          ? $actor_response['available_actions']
          : [];
      }
      if (!array_key_exists('action_contract', $result)) {
        $result['action_contract'] = is_array($actor_response['action_contract'] ?? NULL)
          ? $actor_response['action_contract']
          : NULL;
      }
      if (!array_key_exists('action_option_families', $result)) {
        $result['action_option_families'] = is_array($actor_response['action_option_families'] ?? NULL)
          ? $actor_response['action_option_families']
          : [];
      }
      if (!array_key_exists('aggression_summary', $result)) {
        $result['aggression_summary'] = is_array($actor_response['aggression_summary'] ?? NULL)
          ? $actor_response['aggression_summary']
          : NULL;
      }
      if (!array_key_exists('combat_entry_summary', $result)) {
        $result['combat_entry_summary'] = is_array($actor_response['combat_entry_summary'] ?? NULL)
          ? $actor_response['combat_entry_summary']
          : NULL;
      }
      foreach (['aggression_state', 'disposition_state', 'resolved_disposition_by_target', 'relationship_attitudes', 'stance_state'] as $state_slice_key) {
        if (!array_key_exists($state_slice_key, $result)) {
          $result[$state_slice_key] = is_array($actor_response[$state_slice_key] ?? NULL)
            ? $actor_response[$state_slice_key]
            : NULL;
        }
      }
      if (!array_key_exists('resolved_actor_context', $result)) {
        $result['resolved_actor_context'] = is_array($actor_response['resolved_actor_context'] ?? NULL)
          ? $actor_response['resolved_actor_context']
          : NULL;
      }
      if ($response_mode === 'dual_transition') {
        unset($result['dungeon_data'], $result['debug_trace']);
      }
      return $this->appendInvocationTiming($result, 'gm_subsystem_projection', $timings, $overall_started_at);
    }

    $actor_scoped = $actor_response;
    if ($include_legacy_overlay) {
      $actor_scoped = $actor_scoped + $result['compatibility_overlay'];
    }
    if (isset($result['gm_subsystem']) && is_array($result['gm_subsystem']) && !isset($actor_scoped['gm_subsystem'])) {
      $actor_scoped['gm_subsystem'] = $result['gm_subsystem'];
    }
    return $this->appendInvocationTiming($actor_scoped, 'gm_subsystem_projection', $timings, $overall_started_at);
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

  /**
   * Resolve room-chat response mode for deterministic and chat routes.
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
   * Decide whether explicit compatibility overlay data is required.
   */
  protected function shouldIncludeLegacyOverlay(string $response_mode, array $options): bool {
    return $response_mode === 'legacy';
  }

  /**
   * Return authoritative action availability for one actor on demand.
   *
   * This is the GM-facing query surface for the shared actor availability
   * subsystem. Callers may provide an actor id directly or a character id that
   * resolves to the active runtime actor.
   *
   * @return array{available_actions: string[], action_contract: ?array<string,mixed>}
   *   Shared actor-scoped availability payload.
   */
  public function getActorActionAvailability(int $campaign_id, ?string $actor_id = NULL, ?int $character_id = NULL): array {
    $resolved_actor_id = $actor_id;
    if (($resolved_actor_id === NULL || trim($resolved_actor_id) === '') && $character_id !== NULL && $character_id > 0) {
      $resolved_actor_id = $this->coordinator->resolveActorIdForCharacterId($campaign_id, $character_id);
    }

    return $this->coordinator->getActionAvailabilityForActor(
      $campaign_id,
      is_string($resolved_actor_id) && trim($resolved_actor_id) !== '' ? trim($resolved_actor_id) : NULL
    );
  }

  /**
   * Build the normalized subsystem route envelope for player room chat.
   */
  protected function buildPlayerRoomChatRouteEnvelope(
    int $campaign_id,
    string $requested_room_id,
    string $actor_id,
    ?int $character_id,
    string $message,
    bool $defer_npc_interjections,
    bool $suppress_gm,
    string $speaker,
    array $options = []
  ): array {
    $deterministic_route = $this->buildDeterministicTurnControlRoute($campaign_id, $actor_id, $character_id, $message);
    if ($deterministic_route !== NULL) {
      return $this->buildRouteEnvelope(
        self::WORKFLOW_AUTHORITATIVE_ROOM_ACTION,
        self::ROUTE_DETERMINISTIC_TURN_CONTROL,
        self::ROUTE_FAMILY_DETERMINISTIC_ACTION,
        TRUE,
        (string) ($deterministic_route['handoff_reason'] ?? self::HANDOFF_REASON_DETERMINISTIC_TURN_CONTROL_PHRASE),
        $requested_room_id,
        $actor_id,
        $character_id,
        is_array($deterministic_route['intent'] ?? NULL) ? $deterministic_route['intent'] : []
      );
    }

    return $this->buildRouteEnvelope(
      self::WORKFLOW_AUTHORITATIVE_ROOM_CHAT,
      self::ROUTE_FREE_PLAYER_ROOM_CHAT,
      self::ROUTE_FAMILY_GM_BACKSTOP_CHAT,
      FALSE,
      self::HANDOFF_REASON_NO_TURN_CONTROL_MATCH,
      $requested_room_id,
      $actor_id,
      $character_id,
      [
        'type' => 'room_chat',
        'actor' => $actor_id,
        'target' => NULL,
        'params' => [
          'speaker' => $speaker,
          'message' => $message,
          'character_id' => $character_id,
          'defer_npc_interjections' => $defer_npc_interjections,
          'suppress_gm' => $suppress_gm,
          'response_mode' => (string) ($options['response_mode'] ?? 'actor_scoped'),
          'include_legacy_overlay' => !empty($options['include_legacy_overlay']),
        ],
      ],
      $options
    );
  }

  /**
   * Build deterministic turn-control route metadata when the message is explicit.
   */
  protected function buildDeterministicTurnControlRoute(
    int $campaign_id,
    string $actor_id,
    ?int $character_id,
    string $message
  ): ?array {
    $deterministic_intent = $this->buildDeterministicTurnControlIntent($campaign_id, $actor_id, $character_id, $message);
    if ($deterministic_intent !== NULL) {
      return [
        'handoff_reason' => self::HANDOFF_REASON_DETERMINISTIC_TURN_CONTROL_PHRASE,
        'intent' => $deterministic_intent,
      ];
    }

    $end_turn_intent = $this->buildDeterministicEndTurnIntent($campaign_id, $actor_id, $character_id, $message);
    if ($end_turn_intent !== NULL) {
      return [
        'handoff_reason' => self::HANDOFF_REASON_DETERMINISTIC_END_TURN_PHRASE,
        'intent' => $end_turn_intent,
      ];
    }

    return NULL;
  }

  /**
   * Build the response metadata exposed by the explicit GM subsystem boundary.
   */
  protected function buildResponseEnvelope(array $route, string $resolved_room_id): array {
    return [
      'contract_version' => self::ENVELOPE_CONTRACT_VERSION,
      'workflow' => (string) ($route['workflow'] ?? self::WORKFLOW_AUTHORITATIVE_ROOM_CHAT),
      'route' => (string) ($route['route'] ?? self::ROUTE_FREE_PLAYER_ROOM_CHAT),
      'route_family' => (string) ($route['route_family'] ?? self::ROUTE_FAMILY_GM_BACKSTOP_CHAT),
      'deterministic' => !empty($route['deterministic']),
      'handoff_reason' => (string) ($route['handoff_reason'] ?? 'unspecified'),
      'resolved_room_id' => $resolved_room_id,
      'requested_room_id' => (string) ($route['requested_room_id'] ?? $resolved_room_id),
      'actor_id' => (string) ($route['actor_id'] ?? ''),
      'character_id' => isset($route['character_id']) ? (int) $route['character_id'] : NULL,
      'intent' => $this->normalizeIntentEnvelope(is_array($route['intent'] ?? NULL) ? $route['intent'] : []),
    ];
  }

  /**
   * Build the internal route envelope shape before response normalization.
   */
  protected function buildRouteEnvelope(
    string $workflow,
    string $route,
    string $route_family,
    bool $deterministic,
    string $handoff_reason,
    string $requested_room_id,
    string $actor_id,
    ?int $character_id,
    array $intent,
    array $options = []
  ): array {
    return [
      'workflow' => $workflow,
      'route' => $route,
      'route_family' => $route_family,
      'deterministic' => $deterministic,
      'handoff_reason' => $handoff_reason,
      'requested_room_id' => $requested_room_id,
      'actor_id' => $actor_id,
      'character_id' => $character_id,
      'intent' => $intent,
      'response_mode' => (string) ($options['response_mode'] ?? 'actor_scoped'),
      'include_legacy_overlay' => !empty($options['include_legacy_overlay']),
    ];
  }

  /**
   * Normalize an authoritative action intent into a stable envelope shape.
   */
  protected function normalizeIntentEnvelope(array $intent): array {
    return [
      'type' => (string) ($intent['type'] ?? ''),
      'actor' => (string) ($intent['actor'] ?? ''),
      'target' => array_key_exists('target', $intent) ? $intent['target'] : NULL,
      'params' => is_array($intent['params'] ?? NULL) ? $intent['params'] : [],
    ];
  }

  /**
   * Convert deterministic room-chat turn-control phrasing into canonical actions.
   */
  protected function buildDeterministicTurnControlIntent(
    int $campaign_id,
    string $actor_id,
    ?int $character_id,
    string $message
  ): ?array {
    $trimmed = trim($message);
    if ($trimmed === '') {
      return NULL;
    }

    $normalized = $this->normalizeTurnControlText($trimmed);
    $matches_delay = preg_match('/^(?:delay|wait|waiting|hold(?:\s+my)?\s+turn)\b/u', $normalized) === 1
      || preg_match('/^(?:i(?:ll| will|m| am)\s+(?:wait|waiting|delay|delaying)\b)/u', $normalized) === 1
      || preg_match('/^(?:i(?:ll| will)\s+go\s+after)\b/u', $normalized) === 1;
    if (!$matches_delay) {
      return NULL;
    }

    $availability = $this->coordinator->getActionAvailabilityForActor($campaign_id, $actor_id);
    $available_actions = array_values(array_unique(array_filter(
      array_map(static fn($action): string => strtolower(trim((string) $action)), $availability['available_actions'] ?? []),
      static fn(string $action): bool => $action !== ''
    )));
    if (!in_array('delay', $available_actions, TRUE)) {
      return NULL;
    }

    $state = $this->coordinator->getRuntimeReadState($campaign_id, $actor_id);
    $initiative_order = is_array($state['initiative_order'] ?? NULL)
      ? $state['initiative_order']
      : [];
    $after_actor_id = $this->resolveDelayAfterActorId($normalized, $initiative_order, $actor_id);

    return [
      'type' => 'delay',
      'actor' => $actor_id,
      'target' => NULL,
      'params' => array_filter([
        'character_id' => $character_id,
        'delay_until_actor_id' => $after_actor_id,
        'source' => 'room_chat',
      ], static fn($value): bool => $value !== NULL && $value !== ''),
    ];
  }

  /**
   * Convert explicit end-turn phrasing into canonical end_turn action.
   */
  protected function buildDeterministicEndTurnIntent(
    int $campaign_id,
    string $actor_id,
    ?int $character_id,
    string $message
  ): ?array {
    $trimmed = trim($message);
    if ($trimmed === '') {
      return NULL;
    }

    $normalized = $this->normalizeTurnControlText($trimmed);
    $matches_end_turn = preg_match('/^(?:end(?:\s+my)?\s+turn|finish(?:\s+my)?\s+turn|im\s+done|i\s+am\s+done|done)\b/u', $normalized) === 1
      || preg_match('/^(?:i(?:ll| will)\s+end(?:\s+my)?\s+turn)\b/u', $normalized) === 1;
    if (!$matches_end_turn) {
      return NULL;
    }

    $availability = $this->coordinator->getActionAvailabilityForActor($campaign_id, $actor_id);
    $available_actions = array_values(array_unique(array_filter(
      array_map(static fn($action): string => strtolower(trim((string) $action)), $availability['available_actions'] ?? []),
      static fn(string $action): bool => $action !== ''
    )));
    if (!in_array('end_turn', $available_actions, TRUE)) {
      return NULL;
    }

    return [
      'type' => 'end_turn',
      'actor' => $actor_id,
      'target' => NULL,
      'params' => array_filter([
        'character_id' => $character_id,
        'source' => 'room_chat',
      ], static fn($value): bool => $value !== NULL && $value !== ''),
    ];
  }

  /**
   * Resolve a named delay target from room-chat text.
   */
  protected function resolveDelayAfterActorId(string $normalized_message, array $initiative_order, string $actor_id): ?string {
    if (preg_match('/\b(?:after|behind)\s+(.+?)\s*$/u', $normalized_message, $matches) !== 1) {
      return NULL;
    }

    $target_label = $this->normalizeTurnControlText((string) ($matches[1] ?? ''));
    $target_label = preg_replace('/\b(?:response|reply|turn)\b.*$/u', '', $target_label) ?? $target_label;
    $target_label = trim($target_label);
    if ($target_label === '') {
      return NULL;
    }

    foreach ($initiative_order as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $participant_actor_id = trim((string) ($participant['entity_id'] ?? ''));
      if ($participant_actor_id === '' || $participant_actor_id === $actor_id) {
        continue;
      }

      $name = $this->normalizeTurnControlText((string) ($participant['name'] ?? ''));
      if ($name === '') {
        continue;
      }

      if ($name === $target_label || str_contains($name, $target_label) || str_contains($target_label, $name)) {
        return $participant_actor_id;
      }
    }

    return NULL;
  }

  /**
   * Normalize chat turn-control text for intent matching.
   */
  protected function normalizeTurnControlText(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\'\s-]+/u', ' ', $text) ?? $text;
    $text = str_replace("'", '', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
  }

}
