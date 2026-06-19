<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * GM subsystem facade for player room-chat orchestration.
 *
 * This is the first explicit subsystem boundary between transport/controllers
 * and the deterministic-engine / GM-backstop decision flow.
 */
class GameMasterSubsystemService {

  protected GameCoordinatorService $coordinator;
  protected RoomChatService $roomChatService;

  /**
   * Constructor.
   */
  public function __construct(GameCoordinatorService $coordinator, RoomChatService $room_chat_service) {
    $this->coordinator = $coordinator;
    $this->roomChatService = $room_chat_service;
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
    string $speaker = ''
  ): array {
    if ($character_id === NULL || $character_id <= 0) {
      throw new \InvalidArgumentException('character_id is required for player room chat.', 400);
    }

    $actor_id = $this->coordinator->resolveActorIdForCharacterId($campaign_id, $character_id);
    if (!$actor_id) {
      throw new \InvalidArgumentException('Unable to resolve encounter actor for character.', 409);
    }

    $active_room_id = $this->coordinator->getActiveRoomId($campaign_id, $actor_id);
    if ($active_room_id !== NULL && $active_room_id !== '' && $active_room_id !== $requested_room_id) {
      throw new \InvalidArgumentException('Cannot post room chat: requested room does not match active room.', 409);
    }

    $route = $this->buildPlayerRoomChatRouteEnvelope(
      $campaign_id,
      $requested_room_id,
      $actor_id,
      $character_id,
      $message,
      $defer_npc_interjections,
      $suppress_gm,
      $speaker
    );

    if (!empty($route['deterministic'])) {
      $action_response = $this->coordinator->processAction($campaign_id, $route['intent']);
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
      $talk_result['gm_subsystem'] = $this->buildResponseEnvelope($route, $active_room_id ?: $requested_room_id);

      return $talk_result;
    }

    $room_id = $active_room_id ?: $requested_room_id;
    $requested_speaker = trim($speaker);
    $speaker = trim((string) $this->coordinator->resolveActorDisplayName($campaign_id, $actor_id));
    if ($speaker === '') {
      $speaker = $requested_speaker !== '' ? $requested_speaker : 'Player';
    }
    $defer_npc_interjections = TRUE;
    $chat_result = $this->roomChatService->postMessage(
      $campaign_id,
      $room_id,
      $speaker,
      $message,
      'player',
      $character_id,
      'room',
      $defer_npc_interjections,
      $suppress_gm,
      NULL,
      [
        '_validated_encounter_room_chat' => TRUE,
      ]
    );
    $state = $this->coordinator->getFullState($campaign_id);
    foreach (['game_state', 'available_actions', 'action_contract', 'events', 'phase', 'encounter_id', 'round', 'turn', 'state_version', 'active_room_id'] as $response_key) {
      if (array_key_exists($response_key, $state)) {
        $chat_result[$response_key] = $state[$response_key];
      }
    }
    if (isset($chat_result['message']) && !is_array($chat_result['message']) && isset($chat_result['speaker'])) {
      $chat_result['message'] = [
        'speaker' => (string) $chat_result['speaker'],
        'message' => (string) $chat_result['message'],
        'type' => 'player',
      ];
    }
    $chat_result['gm_subsystem'] = $this->buildResponseEnvelope($route, $room_id);

    return $chat_result;
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
    string $speaker
  ): array {
    $deterministic_intent = $this->buildDeterministicTurnControlIntent($campaign_id, $actor_id, $character_id, $message);
    if ($deterministic_intent !== NULL) {
      return [
        'workflow' => 'authoritative_room_action',
        'route' => 'deterministic_turn_control',
        'deterministic' => TRUE,
        'requested_room_id' => $requested_room_id,
        'actor_id' => $actor_id,
        'character_id' => $character_id,
        'intent' => $deterministic_intent,
      ];
    }

    return [
      'workflow' => 'authoritative_room_chat',
      'route' => 'free_player_room_chat',
      'deterministic' => FALSE,
      'requested_room_id' => $requested_room_id,
      'actor_id' => $actor_id,
      'character_id' => $character_id,
      'intent' => [
        'type' => 'room_chat',
        'actor' => $actor_id,
        'target' => NULL,
        'params' => [
          'speaker' => $speaker,
          'message' => $message,
          'character_id' => $character_id,
          'defer_npc_interjections' => TRUE,
          'suppress_gm' => $suppress_gm,
        ],
      ],
    ];
  }

  /**
   * Build the response metadata exposed by the explicit GM subsystem boundary.
   */
  protected function buildResponseEnvelope(array $route, string $resolved_room_id): array {
    return [
      'workflow' => (string) ($route['workflow'] ?? 'authoritative_room_chat'),
      'route' => (string) ($route['route'] ?? 'free_player_room_chat'),
      'deterministic' => !empty($route['deterministic']),
      'resolved_room_id' => $resolved_room_id,
      'requested_room_id' => (string) ($route['requested_room_id'] ?? $resolved_room_id),
      'actor_id' => (string) ($route['actor_id'] ?? ''),
      'character_id' => isset($route['character_id']) ? (int) $route['character_id'] : NULL,
      'intent' => $this->normalizeIntentEnvelope(is_array($route['intent'] ?? NULL) ? $route['intent'] : []),
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

    $state = $this->coordinator->getFullState($campaign_id);
    $available_actions = array_values(array_unique(array_filter(
      array_map(static fn($action): string => strtolower(trim((string) $action)), $state['available_actions'] ?? []),
      static fn(string $action): bool => $action !== ''
    )));
    if (!in_array('delay', $available_actions, TRUE)) {
      return NULL;
    }

    $after_actor_id = $this->resolveDelayAfterActorId($normalized, $state['initiative_order'] ?? [], $actor_id);

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
