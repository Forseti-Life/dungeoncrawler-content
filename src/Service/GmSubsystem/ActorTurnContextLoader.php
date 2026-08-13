<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Builds one actor-scoped turn context from runtime read-state surfaces.
 */
class ActorTurnContextLoader {

  protected ?\Drupal\dungeoncrawler_content\Service\ActorNarrativeContextService $actorNarrativeContextService;

  public function __construct(?\Drupal\dungeoncrawler_content\Service\ActorNarrativeContextService $actor_narrative_context_service = NULL) {
    $this->actorNarrativeContextService = $actor_narrative_context_service;
  }

  /**
   * Build one actor-scoped room turn context payload.
   *
   * @param array<string,mixed> $runtime_state
   *   Runtime read-state payload from coordinator read lanes.
   * @param array<string,mixed> $action_availability
   *   Actor action availability payload.
   *
   * @return array<string,mixed>
   *   Actor turn context for response projection and routing.
   */
  public function load(
    array $runtime_state,
    array $action_availability,
    ?string $actor_id = NULL,
    ?int $character_id = NULL,
    ?string $display_name = NULL
  ): array {
    $actor_entity = is_array($runtime_state['actor_entity'] ?? NULL)
      ? $runtime_state['actor_entity']
      : NULL;

    $actor_id = trim((string) $actor_id);
    if ($actor_id === '' && is_array($actor_entity)) {
      $actor_id = trim((string) (
        $actor_entity['entity_instance_id']
        ?? $actor_entity['instance_id']
        ?? $actor_entity['id']
        ?? ''
      ));
    }

    $resolved_character_id = $character_id !== NULL && $character_id > 0
      ? $character_id
      : NULL;
    if ($resolved_character_id === NULL && is_array($actor_entity)) {
      $candidate_character_id = (int) ($actor_entity['character_id'] ?? 0);
      if ($candidate_character_id > 0) {
        $resolved_character_id = $candidate_character_id;
      }
    }

    $resolved_display_name = trim((string) ($display_name ?? ''));
    if ($resolved_display_name === '' && is_array($actor_entity)) {
      $resolved_display_name = trim((string) (
        $actor_entity['state']['metadata']['display_name']
        ?? $actor_entity['name']
        ?? ''
      ));
    }
    if ($resolved_display_name === '') {
      $resolved_display_name = 'Player';
    }

    $runtime_snapshot = [
      'game_state' => is_array($runtime_state['game_state'] ?? NULL)
        ? $runtime_state['game_state']
        : [],
      'phase' => $runtime_state['phase'] ?? NULL,
      'encounter_id' => $runtime_state['encounter_id'] ?? NULL,
      'round' => $runtime_state['round'] ?? NULL,
      'turn' => is_array($runtime_state['turn'] ?? NULL)
        ? $runtime_state['turn']
        : ($runtime_state['turn'] ?? NULL),
      'state_version' => $runtime_state['state_version'] ?? NULL,
      'active_room_id' => $runtime_state['active_room_id'] ?? NULL,
      'active_room' => is_array($runtime_state['active_room'] ?? NULL)
        ? $runtime_state['active_room']
        : NULL,
      'actor_entity' => $actor_entity,
      'visible_entities' => array_values(is_array($runtime_state['visible_entities'] ?? NULL)
        ? $runtime_state['visible_entities']
        : []),
      'visible_npcs' => array_values(is_array($runtime_state['visible_npcs'] ?? NULL)
        ? $runtime_state['visible_npcs']
        : []),
      'connected_rooms' => array_values(is_array($runtime_state['connected_rooms'] ?? NULL)
        ? $runtime_state['connected_rooms']
        : []),
      'hostile_targets' => array_values(is_array($runtime_state['hostile_targets'] ?? NULL)
        ? $runtime_state['hostile_targets']
        : []),
      'social_progression' => is_array($runtime_state['social_progression'] ?? NULL)
        ? $runtime_state['social_progression']
        : [],
    ];
    $quest_context = [];
    $campaign_id = (int) ($runtime_snapshot['game_state']['campaign_id'] ?? 0);
    $context_room_id = (string) ($runtime_snapshot['active_room_id'] ?? '');
    if ($campaign_id > 0 && $actor_id !== '' && $this->actorNarrativeContextService) {
      $quest_context = $this->actorNarrativeContextService->buildContextEnvelope($campaign_id, $actor_id, $context_room_id);
    }

    return [
      'actor' => [
        'actor_id' => $actor_id,
        'character_id' => $resolved_character_id,
        'display_name' => $resolved_display_name,
        'entity' => $actor_entity,
      ],
      'room' => [
        'room_id' => $runtime_snapshot['active_room_id'],
        'name' => (string) ($runtime_snapshot['active_room']['name'] ?? ''),
        'description' => (string) ($runtime_snapshot['active_room']['description'] ?? ''),
        'connected_rooms' => $runtime_snapshot['connected_rooms'],
      ],
      'runtime_snapshot' => $runtime_snapshot,
      'recent_transcript' => [],
      'quest_context' => $quest_context,
      'legal_actions' => [
        'available_actions' => array_values(is_array($action_availability['available_actions'] ?? NULL)
          ? $action_availability['available_actions']
          : []),
        'action_contract' => is_array($action_availability['action_contract'] ?? NULL)
          ? $action_availability['action_contract']
          : NULL,
        'action_option_families' => is_array($action_availability['action_contract']['action_option_families'] ?? NULL)
          ? $action_availability['action_contract']['action_option_families']
          : [],
      ],
    ];
  }

}
