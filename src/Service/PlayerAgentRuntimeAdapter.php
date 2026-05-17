<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * In-process runtime adapter for player-agent execution.
 */
class PlayerAgentRuntimeAdapter implements PlayerAgentRuntimeAdapterInterface {

  protected GameCoordinatorService $gameCoordinator;

  protected Connection $database;

  protected CampaignCharacterRuntimeSyncService $campaignCharacterRuntimeSync;

  public function __construct(GameCoordinatorService $game_coordinator, Connection $database, CampaignCharacterRuntimeSyncService $campaign_character_runtime_sync) {
    $this->gameCoordinator = $game_coordinator;
    $this->database = $database;
    $this->campaignCharacterRuntimeSync = $campaign_character_runtime_sync;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSnapshot(int $campaign_id, string $actor_id, array $run_state = []): array {
    $state_payload = $this->gameCoordinator->getFullState($campaign_id);
    if (empty($state_payload['success'])) {
      return [
        'success' => FALSE,
        'error' => (string) ($state_payload['error'] ?? 'Failed to load canonical game state.'),
      ];
    }

    $dungeon_data = $this->loadDungeonData($campaign_id);
    if ($dungeon_data === NULL) {
      return [
        'success' => FALSE,
        'error' => 'Failed to load dungeon runtime payload.',
      ];
    }

    $event_cursor = (int) ($run_state['event_cursor'] ?? 0);
    $events_payload = $this->gameCoordinator->getEventsSince($campaign_id, $event_cursor);
    $new_events = !empty($events_payload['success']) && is_array($events_payload['events'] ?? NULL)
      ? $events_payload['events']
      : [];
    $next_cursor = !empty($events_payload['success'])
      ? (int) ($events_payload['cursor'] ?? $event_cursor)
      : $event_cursor;

    $game_state = is_array($state_payload['game_state'] ?? NULL) ? $state_payload['game_state'] : [];
    $active_room_id = (string) ($state_payload['active_room_id'] ?? $dungeon_data['active_room_id'] ?? '');
    $active_room = $this->findRoom($dungeon_data, $active_room_id);
    $visible_entities = $this->findRoomEntities($dungeon_data, $active_room_id);
    $actor_entity = $this->findEntity($dungeon_data, $actor_id);

    return [
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'actor_id' => $actor_id,
      'phase' => (string) ($game_state['phase'] ?? 'exploration'),
      'game_state' => $game_state,
      'state_version' => (int) ($state_payload['state_version'] ?? ($game_state['state_version'] ?? 1)),
      'event_cursor' => $next_cursor,
      'new_events' => $new_events,
      'active_room_id' => $active_room_id,
      'active_room' => $active_room,
      'actor_entity' => $actor_entity,
      'visible_entities' => $visible_entities,
      'visible_npcs' => array_values(array_filter($visible_entities, function (array $entity): bool {
        return strtolower((string) ($entity['entity_type'] ?? '')) === 'npc';
      })),
      'connected_rooms' => $this->findConnectedRooms($dungeon_data, $active_room_id),
      'hostile_targets' => $this->findHostileTargets($game_state, $actor_id),
      'available_actions' => $this->gameCoordinator->getAvailableActionsForActor($campaign_id, $actor_id),
      'last_encounter' => $game_state['last_encounter'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitIntent(int $campaign_id, array $intent): array {
    return $this->gameCoordinator->processAction($campaign_id, $intent);
  }

  /**
   * Load the dungeon payload for a campaign.
   */
  protected function loadDungeonData(int $campaign_id): ?array {
    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$row) {
      return NULL;
    }

    $decoded = json_decode($row, TRUE) ?: NULL;
    if (!is_array($decoded)) {
      return NULL;
    }

    return $this->campaignCharacterRuntimeSync->syncActiveRoomPlayerEntities($decoded, $campaign_id);
  }

  /**
   * Find a room by room ID.
   */
  protected function findRoom(array $dungeon_data, string $room_id): ?array {
    if ($room_id === '') {
      return NULL;
    }
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? '') === $room_id) {
        return $room;
      }
    }
    return NULL;
  }

  /**
   * Find an entity by runtime entity ID.
   */
  protected function findEntity(array $dungeon_data, string $entity_id): ?array {
    if ($entity_id === '') {
      return NULL;
    }
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if ($this->resolveEntityId($entity) === $entity_id) {
        return $entity;
      }
    }
    return NULL;
  }

  /**
   * Find entities currently placed in the active room.
   *
   * @return array<int, array<string, mixed>>
   *   Visible entities.
   */
  protected function findRoomEntities(array $dungeon_data, string $room_id): array {
    if ($room_id === '') {
      return [];
    }

    $visible = [];
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if (($entity['placement']['room_id'] ?? '') === $room_id) {
        $visible[] = $entity;
      }
    }

    return array_values($visible);
  }

  /**
   * Build passable room transitions for the active room.
   *
   * @return array<int, array<string, mixed>>
   *   Connected room summaries.
   */
  protected function findConnectedRooms(array $dungeon_data, string $room_id): array {
    if ($room_id === '') {
      return [];
    }

    $connections = [];
    foreach ($dungeon_data['connections'] ?? [] as $connection) {
      if (empty($connection['is_passable'])) {
        continue;
      }

      $from_room = (string) ($connection['from']['room_id'] ?? '');
      $to_room = (string) ($connection['to']['room_id'] ?? '');

      if ($from_room === $room_id && $to_room !== '') {
        $connections[] = $this->buildConnectedRoomSummary($dungeon_data, $to_room, $connection);
      }
      elseif ($to_room === $room_id && $from_room !== '') {
        $connections[] = $this->buildConnectedRoomSummary($dungeon_data, $from_room, $connection);
      }
    }

    return array_values($connections);
  }

  /**
   * Build a connected room summary.
   */
  protected function buildConnectedRoomSummary(array $dungeon_data, string $room_id, array $connection): array {
    $room = $this->findRoom($dungeon_data, $room_id);

    return [
      'room_id' => $room_id,
      'name' => (string) ($room['name'] ?? $room_id),
      'description' => (string) ($room['description'] ?? ''),
      'connection' => $connection,
    ];
  }

  /**
   * Build a list of hostile targets from encounter initiative order.
   *
   * @return array<int, array<string, mixed>>
   *   Hostile target summaries.
   */
  protected function findHostileTargets(array $game_state, string $actor_id): array {
    $phase = (string) ($game_state['phase'] ?? 'exploration');
    if ($phase !== 'encounter') {
      return [];
    }

    $targets = [];
    foreach ($game_state['initiative_order'] ?? [] as $participant) {
      $target_id = (string) ($participant['entity_id'] ?? '');
      $team = strtolower((string) ($participant['team'] ?? ''));
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
   * Resolve the runtime entity ID for an entity payload.
   */
  protected function resolveEntityId(array $entity): string {
    return (string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? '');
  }

}
