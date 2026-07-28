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

  protected RuntimeBootstrapService $runtimeBootstrap;

  public function __construct(GameCoordinatorService $game_coordinator, Connection $database, CampaignCharacterRuntimeSyncService $campaign_character_runtime_sync, RuntimeBootstrapService $runtime_bootstrap) {
    $this->gameCoordinator = $game_coordinator;
    $this->database = $database;
    $this->campaignCharacterRuntimeSync = $campaign_character_runtime_sync;
    $this->runtimeBootstrap = $runtime_bootstrap;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSnapshot(int $campaign_id, string $actor_id, array $run_state = []): array {
    $runtime_character_id = $this->runtimeBootstrap->resolveRuntimeCharacterIdForActor($campaign_id, $actor_id);
    if ($runtime_character_id !== NULL) {
      $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $runtime_character_id);
    }
    else {
      $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    }

    $state_payload = $this->gameCoordinator->getRuntimeReadState($campaign_id, $actor_id);
    if (empty($state_payload['success'])) {
      return [
        'success' => FALSE,
        'error' => (string) ($state_payload['error'] ?? 'Failed to load canonical game state.'),
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
    $active_room_id = (string) ($state_payload['active_room_id'] ?? '');
    $active_room = is_array($state_payload['active_room'] ?? NULL) ? $state_payload['active_room'] : NULL;
    $visible_entities = is_array($state_payload['visible_entities'] ?? NULL) ? $state_payload['visible_entities'] : [];
    $actor_entity = is_array($state_payload['actor_entity'] ?? NULL) ? $state_payload['actor_entity'] : NULL;
    $connected_rooms = is_array($state_payload['connected_rooms'] ?? NULL) ? $state_payload['connected_rooms'] : [];
    $hostile_targets = is_array($state_payload['hostile_targets'] ?? NULL) ? $state_payload['hostile_targets'] : [];
    $available_actions = is_array($state_payload['available_actions'] ?? NULL) ? $state_payload['available_actions'] : [];
    $action_contract = is_array($state_payload['action_contract'] ?? NULL) ? $state_payload['action_contract'] : NULL;
    $social_progression = is_array($state_payload['social_progression'] ?? NULL) ? $state_payload['social_progression'] : [];
    $last_encounter = $state_payload['last_encounter'] ?? ($game_state['last_encounter'] ?? NULL);
    $visible_npcs = is_array($state_payload['visible_npcs'] ?? NULL)
      ? $state_payload['visible_npcs']
      : array_values(array_filter($visible_entities, function (array $entity): bool {
        return strtolower((string) ($entity['entity_type'] ?? '')) === 'npc';
      }));

    return [
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'actor_id' => $actor_id,
      'phase' => (string) ($game_state['phase'] ?? 'encounter'),
      'game_state' => $game_state,
      'state_version' => (int) ($state_payload['state_version'] ?? ($game_state['state_version'] ?? 1)),
      'event_cursor' => $next_cursor,
      'new_events' => $new_events,
      'active_room_id' => $active_room_id,
      'active_room' => $active_room,
      'actor_entity' => $actor_entity,
      'visible_entities' => $visible_entities,
      'visible_npcs' => $visible_npcs,
      'connected_rooms' => $connected_rooms,
      'hostile_targets' => $hostile_targets,
      'available_actions' => $available_actions,
      'action_contract' => $action_contract,
      'social_progression' => $social_progression,
      'last_encounter' => $last_encounter,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitIntent(int $campaign_id, array $intent): array {
    $character_id = (int) ($intent['params']['character_id'] ?? $intent['character_id'] ?? 0);
    if ($character_id > 0) {
      $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $character_id);
    }
    else {
      $actor_id = trim((string) ($intent['actor'] ?? ''));
      $runtime_character_id = $this->runtimeBootstrap->resolveRuntimeCharacterIdForActor($campaign_id, $actor_id);
      if ($runtime_character_id !== NULL) {
        $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $runtime_character_id);
      }
      else {
        $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
      }
    }
    return $this->gameCoordinator->processAction($campaign_id, $intent);
  }

}
