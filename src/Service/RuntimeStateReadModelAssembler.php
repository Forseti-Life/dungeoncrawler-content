<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds runtime read-model payloads from coordinator request context.
 */
class RuntimeStateReadModelAssembler {

  protected const DEFAULT_ACTIVE_PHASE = 'encounter';

  /**
   * Build compact runtime snapshot payload from current request context.
   *
   * @param array<string,mixed> $game_state
   *   Runtime game state payload.
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   * @param array<string,mixed> $client_game_state
   *   Client-safe game state payload.
   * @param string|null $actor_id
   *   Optional actor context.
   *
   * @return array<string,mixed>
   *   Runtime snapshot object suitable for transition/action consumers.
   */
  public function buildRuntimeSnapshotPayload(
    array $game_state,
    array $dungeon_data,
    array $client_game_state,
    ?string $actor_id = NULL
  ): array {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    $visible_entities = [];
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      if (trim((string) ($entity['placement']['room_id'] ?? '')) === $active_room_id) {
        $visible_entities[] = $entity;
      }
    }

    $actor_entity = NULL;
    $actor_id = trim((string) ($actor_id ?? ''));
    if ($actor_id !== '') {
      foreach ($dungeon_data['entities'] ?? [] as $entity) {
        if (!is_array($entity)) {
          continue;
        }
        $entity_id = trim((string) (
          $entity['entity_instance_id']
          ?? $entity['instance_id']
          ?? $entity['id']
          ?? ''
        ));
        if ($entity_id === $actor_id) {
          $actor_entity = $entity;
          break;
        }
      }
    }

    return [
      'success' => TRUE,
      'game_state' => $client_game_state,
      'phase' => $game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE,
      'state_version' => $game_state['state_version'] ?? 1,
      'active_room_id' => $active_room_id !== '' ? $active_room_id : NULL,
      'active_room' => $this->findRoomInDungeon($active_room_id !== '' ? $active_room_id : NULL, $dungeon_data),
      'actor_entity' => $actor_entity,
      'visible_entities' => array_values($visible_entities),
      'visible_npcs' => array_values(array_filter($visible_entities, static function (array $entity): bool {
        return strtolower((string) ($entity['entity_type'] ?? '')) === 'npc';
      })),
      'connected_rooms' => $this->findConnectedRoomsForReadState($dungeon_data, $active_room_id),
      'hostile_targets' => $this->findHostileTargetsFromGameState($game_state, $actor_id),
      'social_progression' => $this->extractRoomSceneSocialProgressionFromGameState($game_state, $active_room_id),
      'last_encounter' => $game_state['last_encounter'] ?? NULL,
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'turn' => $game_state['turn'] ?? NULL,
      'initiative_order' => is_array($game_state['initiative_order'] ?? NULL)
        ? $game_state['initiative_order']
        : [],
    ];
  }

  /**
   * Find one room payload by room id.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   *
   * @return array<string,mixed>|null
   *   Room payload or NULL when missing.
   */
  protected function findRoomInDungeon(?string $room_id, array $dungeon_data): ?array {
    if ($room_id === NULL || $room_id === '') {
      return NULL;
    }

    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? NULL) === $room_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Build passable connected-room summaries for read-state payloads.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   *
   * @return array<int, array<string,mixed>>
   *   Connected room summaries.
   */
  protected function findConnectedRoomsForReadState(array $dungeon_data, string $room_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return [];
    }

    $connections = [];
    foreach ($dungeon_data['connections'] ?? [] as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      if (empty($connection['is_passable'])) {
        continue;
      }
      $from_room = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? $connection['from']['room_id'] ?? ''));
      $to_room = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? $connection['to']['room_id'] ?? ''));
      if ($from_room === $room_id && $to_room !== '') {
        $connections[] = $this->buildConnectedRoomSummaryForReadState($dungeon_data, $to_room, $connection);
      }
      elseif ($to_room === $room_id && $from_room !== '') {
        $connections[] = $this->buildConnectedRoomSummaryForReadState($dungeon_data, $from_room, $connection);
      }
    }

    return array_values($connections);
  }

  /**
   * Build one connected-room summary row.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   * @param array<string,mixed> $connection
   *   Connection payload.
   *
   * @return array<string,mixed>
   *   Connected room summary row.
   */
  protected function buildConnectedRoomSummaryForReadState(array $dungeon_data, string $room_id, array $connection): array {
    $room = $this->findRoomInDungeon($room_id, $dungeon_data);
    return [
      'room_id' => $room_id,
      'name' => (string) ($room['name'] ?? $room_id),
      'description' => (string) ($room['description'] ?? ''),
      'connection' => $connection,
    ];
  }

  /**
   * Build hostile target list from initiative order.
   *
   * @param array<string,mixed> $game_state
   *   Runtime game state payload.
   *
   * @return array<int, array<string,mixed>>
   *   Hostile participants.
   */
  protected function findHostileTargetsFromGameState(array $game_state, string $actor_id): array {
    if ((string) ($game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE) !== self::DEFAULT_ACTIVE_PHASE) {
      return [];
    }
    $targets = [];
    foreach ($game_state['initiative_order'] ?? [] as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $target_id = trim((string) ($participant['entity_id'] ?? ''));
      $team = strtolower(trim((string) ($participant['team'] ?? '')));
      if ($target_id === '' || $target_id === $actor_id || !empty($participant['is_defeated'])) {
        continue;
      }
      if (in_array($team, ['enemy', 'hostile', 'monsters'], TRUE)) {
        $targets[] = $participant;
      }
    }
    return array_values($targets);
  }

  /**
   * Extract room-scene social progression diagnostics.
   *
   * @param array<string,mixed> $game_state
   *   Runtime game state payload.
   *
   * @return array<string,mixed>
   *   Room-scene progression state.
   */
  protected function extractRoomSceneSocialProgressionFromGameState(array $game_state, string $active_room_id): array {
    $encounter_context = is_array($game_state['encounter_context'] ?? NULL)
      ? $game_state['encounter_context']
      : [];
    $social_progression = is_array($encounter_context['social_progression'] ?? NULL)
      ? $encounter_context['social_progression']
      : [];
    if ($social_progression === []) {
      return [];
    }
    $room_id = trim((string) ($social_progression['room_id'] ?? ($encounter_context['room_id'] ?? '')));
    if ($room_id !== '' && $active_room_id !== '' && $room_id !== $active_room_id) {
      return [];
    }
    $lead_seek_counts = is_array($social_progression['lead_seek_counts'] ?? NULL)
      ? $social_progression['lead_seek_counts']
      : [];
    $exhausted = is_array($social_progression['exhausted_lead_sources'] ?? NULL)
      ? array_values(array_unique(array_filter(array_map('strval', $social_progression['exhausted_lead_sources']), static fn(string $value): bool => trim($value) !== '')))
      : [];
    return [
      'policy_version' => (int) ($social_progression['policy_version'] ?? 1),
      'room_id' => $room_id,
      'lead_seek_counts' => $lead_seek_counts,
      'exhausted_lead_sources' => $exhausted,
      'last_progress_signal' => (string) ($social_progression['last_progress_signal'] ?? 'none'),
    ];
  }

}
