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

  /**
   * Constructor.
   */
  public function __construct(GameCoordinatorService $coordinator) {
    $this->coordinator = $coordinator;
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
    bool $suppress_gm = FALSE
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

    $intent = $this->buildDeterministicTurnControlIntent($campaign_id, $actor_id, $character_id, $message);
    if ($intent === NULL) {
      $intent = [
        'type' => 'talk',
        'actor' => $actor_id,
        'target' => NULL,
        'params' => [
          'message' => $message,
          'character_id' => $character_id,
          'defer_npc_interjections' => $defer_npc_interjections,
          'suppress_gm' => $suppress_gm,
        ],
      ];
    }

    $action_response = $this->coordinator->processAction($campaign_id, $intent);
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

    return $talk_result;
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
