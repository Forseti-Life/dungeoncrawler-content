<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Wave 4 extraction: event projection coordination methods.
 */
trait EncounterEventProjectionCoordinatorTrait {

  /**
   * Builds and queues narrator-visible round-start chat events.
   */
  protected function buildRoundStartEvents(int $round, array $game_state, array $dungeon_data, int $campaign_id, ?string $room_id = NULL): array {
    $round_narration = $this->aiGmService->narrateRoundStart($round, $game_state, $dungeon_data, $campaign_id);
    $content = $round_narration ?: sprintf('Round %d begins.', $round);

    $content = $this->prefixEncounterChatLine(
      $this->captureEncounterTurnContext($game_state, $dungeon_data, NULL, [
        'actor_name' => 'Narrator',
        'turn_index_raw' => NULL,
        'turn_index_human' => NULL,
      ]),
      $content
    );

    $resolved_room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL));
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'round_start',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => '',
      'content' => $content,
      'visibility' => 'public',
      'mechanical_data' => [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'actor_name' => 'Narrator',
      ],
    ], $resolved_room_id, $game_state);

    return [
      GameEventLogger::buildEvent('round_start', 'encounter', NULL, [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'actor_name' => 'Narrator',
        'turn_index' => -1,
      ], $content),
    ];
  }

  /**
   * Builds and queues actor turn-start chat events.
   */


  /**
   * Builds and queues actor turn-start chat events.
   */
  protected function buildTurnStartEvents(string $entity_id, array $game_state, array $dungeon_data, int $campaign_id, ?string $room_id = NULL): array {
    $actor_name = $this->resolveEntityName($entity_id, $game_state, $dungeon_data);
    $round = $game_state['round'] ?? NULL;
    $resolved_room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL));
    $turn_index = $game_state['turn']['index'] ?? NULL;
    $total_turns = is_array($game_state['initiative_order'] ?? NULL) ? count($game_state['initiative_order']) : NULL;
    $actions_available = $game_state['turn']['actions_remaining'] ?? NULL;
    $content = sprintf("%s's turn begins.", $actor_name);
    $content = $this->prefixEncounterChatLine(
      $this->captureEncounterTurnContext($game_state, $dungeon_data, $entity_id, ['actor_name' => $actor_name]),
      $content
    );
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'turn_start',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => $entity_id,
      'content' => $content,
      'visibility' => 'public',
      'mechanical_data' => [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'entity_id' => $entity_id,
        'actor_name' => $actor_name,
        'turn_index' => $turn_index,
        'total_turns' => $total_turns,
        'actions_available' => $actions_available,
      ],
    ], $resolved_room_id, $game_state);

    return [
      GameEventLogger::buildEvent('turn_start', 'encounter', $entity_id, [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'actor_name' => $actor_name,
        'turn_index' => $turn_index,
        'total_turns' => $total_turns,
        'actions_available' => $actions_available,
      ], $content),
    ];
  }

  /**
   * Runs automatic Search at the start of an actor turn.
   */


  /**
   * Runs automatic Search at the start of an actor turn.
   */
  protected function buildTurnStartSearchEvents(string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$this->explorationPhaseHandler) {
      return [];
    }

    $room_id = (string) ($dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? ''));
    $params = [
      'search_mode' => 'automatic',
      'trigger' => 'turn_start',
    ];
    if ($room_id !== '') {
      $params['room_id'] = $room_id;
    }

    $result = $this->explorationPhaseHandler->processSearch($entity_id, $params, $game_state, $dungeon_data, $campaign_id);

    $this->maybeQueueMechanicalSystemLogEntry([
      'campaign_id' => $campaign_id,
      'dungeon_data' => $dungeon_data,
      'type' => 'search',
      'actor_id' => $entity_id,
      'target_id' => NULL,
      'params' => $params,
      'result' => $result,
      'game_state' => $game_state,
    ]);

    $discoveries = $this->buildPublicSearchDiscoveries($result['discoveries'] ?? []);
    $narration = $result['narration'] ?? NULL;
    if ($discoveries === [] && (!is_string($narration) || trim($narration) === '')) {
      return [];
    }

    return [
      GameEventLogger::buildEvent('search', 'encounter', $entity_id, [
        'discoveries' => $discoveries,
        'round' => $game_state['round'] ?? NULL,
        'trigger' => 'turn_start',
        'room_id' => $room_id !== '' ? $room_id : NULL,
      ], $narration),
    ];
  }

  /**
   * Processes end-of-turn: advance to next combatant, auto-play NPCs.
   */

}
