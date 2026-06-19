<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared actor-scoped action-availability resolver.
 *
 * This is the convergence point for actor action availability so encounter UI,
 * NPC AI, GM tooling, and validation can read from one authoritative envelope.
 */
class ActorActionAvailabilityService {

  /**
   * Canonical encounter-facing action definitions.
   */
  protected const ACTION_DEFINITIONS = [
    'strike' => [
      'label' => 'Strike',
      'cost' => 1,
      'category' => 'offense',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
    ],
    'step' => [
      'label' => 'Step',
      'cost' => 1,
      'category' => 'movement',
      'requires_turn' => TRUE,
      'targeting' => 'hex',
    ],
    'stride' => [
      'label' => 'Stride',
      'cost' => 1,
      'category' => 'movement',
      'requires_turn' => TRUE,
      'targeting' => 'hex',
    ],
    'interact' => [
      'label' => 'Interact',
      'cost' => 1,
      'category' => 'utility',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_object',
    ],
    'search' => [
      'label' => 'Search',
      'cost' => 1,
      'category' => 'perception',
      'requires_turn' => TRUE,
      'targeting' => 'room',
    ],
    'talk' => [
      'label' => 'Talk',
      'cost' => 1,
      'category' => 'conversation',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_room',
    ],
    'cast_spell' => [
      'label' => 'Cast Spell',
      'cost' => 2,
      'category' => 'magic',
      'requires_turn' => TRUE,
      'targeting' => 'contextual',
    ],
    'demoralize' => [
      'label' => 'Demoralize',
      'cost' => 1,
      'category' => 'social',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
    ],
    'raise_shield' => [
      'label' => 'Raise Shield',
      'cost' => 1,
      'category' => 'defense',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'delay' => [
      'label' => 'Delay',
      'cost' => 0,
      'category' => 'turn',
      'requires_turn' => TRUE,
      'targeting' => 'none',
    ],
    'end_turn' => [
      'label' => 'End Turn',
      'cost' => 0,
      'category' => 'turn',
      'requires_turn' => TRUE,
      'targeting' => 'none',
    ],
    'choose_not_to_act' => [
      'label' => 'Choose Not to Act',
      'cost' => 0,
      'category' => 'turn',
      'requires_turn' => TRUE,
      'targeting' => 'none',
    ],
    'reaction' => [
      'label' => 'Reaction',
      'cost' => 'reaction',
      'category' => 'reaction',
      'requires_turn' => FALSE,
      'targeting' => 'contextual',
    ],
    'minor_color_shift' => [
      'label' => 'Minor Color Shift',
      'cost' => 1,
      'category' => 'heritage',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'treat_wounds' => [
      'label' => 'Treat Wounds',
      'cost' => 0,
      'category' => 'recovery',
      'requires_turn' => TRUE,
      'targeting' => 'ally_or_self',
    ],
    'refocus' => [
      'label' => 'Refocus',
      'cost' => 0,
      'category' => 'recovery',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'repair' => [
      'label' => 'Repair',
      'cost' => 0,
      'category' => 'recovery',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'daily_preparations' => [
      'label' => 'Daily Preparations',
      'cost' => 0,
      'category' => 'recovery',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'transition' => [
      'label' => 'Move to connected room',
      'cost' => 0,
      'category' => 'navigation',
      'requires_turn' => FALSE,
      'targeting' => 'connected_room',
    ],
  ];

  /**
   * Resolve shared encounter action availability for one actor.
   *
   * @return array{available_actions: string[], action_contract: array<string,mixed>, availability_envelope: array<string,mixed>}
   *   Shared actor-scoped availability payload.
   */
  public function resolveEncounterAvailability(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    $turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
    $current_entity = trim((string) ($turn['entity'] ?? ''));
    $effective_actor_id = $actor_id ?? ($current_entity !== '' ? $current_entity : NULL);
    $actions_remaining = max(0, (int) ($turn['actions_remaining'] ?? 0));
    $reaction_available = !empty($turn['reaction_available']);
    $is_active_turn_actor = $effective_actor_id !== NULL
      && $effective_actor_id !== ''
      && $current_entity !== ''
      && $effective_actor_id === $current_entity;
    $room_scene = $this->isRoomSceneMode($game_state);
    $safe_rest_available = $room_scene && $this->isSafeRestAvailable($game_state, $dungeon_data);
    $heritage = $this->resolveActorHeritage($effective_actor_id, $dungeon_data);

    $available_actions = $this->resolveAvailableActionsFromTurnState(
      $room_scene,
      $effective_actor_id,
      $current_entity !== '' ? $current_entity : $effective_actor_id,
      $actions_remaining,
      $reaction_available,
      $heritage,
      $safe_rest_available
    );
    $action_contract = $this->buildActionContractFromAvailableActions(
      $available_actions,
      $effective_actor_id,
      $current_entity !== '' ? $current_entity : $effective_actor_id
    );
    $availability_envelope = $this->buildAvailabilityEnvelopeFromAvailableActions(
      $effective_actor_id,
      $is_active_turn_actor,
      $actions_remaining,
      $reaction_available,
      $available_actions,
      $action_contract
    );

    return [
      'available_actions' => $available_actions,
      'action_contract' => $action_contract,
      'availability_envelope' => $availability_envelope,
    ];
  }

  /**
   * Resolve actor actions directly from turn-state primitives.
   *
   * This allows non-handler callers like preview tooling and GM/NPC consumers to
   * ask the same shared resolver for a concrete actor turn surface.
   *
   * @return string[]
   *   Canonical available action ids.
   */
  public function resolveAvailableActionsFromTurnState(
    bool $room_scene,
    ?string $actor_id,
    ?string $current_entity,
    int $actions_remaining,
    bool $reaction_available,
    ?string $heritage = NULL,
    bool $safe_rest_available = FALSE
  ): array {
    $actions = ['transition'];
    $is_active_turn_actor = $actor_id !== NULL
      && $actor_id !== ''
      && $current_entity !== NULL
      && $current_entity !== ''
      && $actor_id === $current_entity;

    if ($room_scene) {
      if ($is_active_turn_actor) {
        if ($actions_remaining >= 1) {
          $actions = array_merge($actions, [
            'talk',
            'search',
            'interact',
            'delay',
          ]);
        }
        if ($safe_rest_available) {
          $actions = array_merge($actions, [
            'treat_wounds',
            'refocus',
            'repair',
            'daily_preparations',
          ]);
        }
        $actions[] = 'end_turn';
        $actions[] = 'choose_not_to_act';
      }

      return $this->normalizeActionIds($actions);
    }

    if ($is_active_turn_actor) {
      if ($actions_remaining >= 1) {
        $actions = array_merge($actions, [
          'strike',
          'step',
          'stride',
          'interact',
          'search',
          'talk',
          'demoralize',
          'raise_shield',
        ]);
        if ($heritage === 'chameleon') {
          $actions[] = 'minor_color_shift';
        }
      }
      if ($actions_remaining >= 2) {
        $actions[] = 'cast_spell';
      }
      $actions[] = 'end_turn';
      $actions[] = 'choose_not_to_act';
      $actions[] = 'delay';
    }

    if ($is_active_turn_actor && $reaction_available) {
      $actions[] = 'reaction';
    }

    return $this->normalizeActionIds($actions);
  }

  /**
   * Build the shared structured action contract for one actor.
   */
  public function buildActionContractFromAvailableActions(array $available_actions, ?string $actor_id = NULL, ?string $current_turn_entity = NULL): array {
    $normalized_available = array_fill_keys($this->normalizeActionIds($available_actions), TRUE);
    $actions = [];

    foreach (self::ACTION_DEFINITIONS as $action_id => $definition) {
      $actions[] = [
        'id' => $action_id,
        'label' => $definition['label'],
        'cost' => $definition['cost'],
        'category' => $definition['category'],
        'requires_turn' => $definition['requires_turn'],
        'targeting' => $definition['targeting'],
        'available' => !empty($normalized_available[$action_id]),
      ];
    }

    return [
      'phase' => 'encounter',
      'actor_id' => $actor_id,
      'current_turn_entity' => $current_turn_entity,
      'available_actions' => array_values(array_keys($normalized_available)),
      'actions' => $actions,
    ];
  }

  /**
   * Build the canonical actor-scoped availability envelope.
   */
  public function buildAvailabilityEnvelopeFromAvailableActions(
    ?string $actor_id,
    bool $is_active_turn_actor,
    int $actions_remaining,
    bool $reaction_available,
    array $available_actions,
    array $action_contract
  ): array {
    return [
      'actor_instance_id' => $actor_id,
      'is_active_turn_actor' => $is_active_turn_actor,
      'actions_remaining' => $is_active_turn_actor ? max(0, $actions_remaining) : 0,
      'reaction_available' => $is_active_turn_actor && $reaction_available,
      'available_actions' => $this->normalizeActionIds($available_actions),
      'action_contract' => $action_contract,
    ];
  }

  /**
   * Determine whether current encounter context is room-scene mode.
   */
  protected function isRoomSceneMode(array $game_state): bool {
    $mode = strtolower(trim((string) ($game_state['encounter_context']['mode'] ?? '')));
    if ($mode === 'room_scene') {
      return TRUE;
    }

    return $mode === ''
      && empty($game_state['encounter_id'])
      && !empty($game_state['encounter_context']['room_id']);
  }

  /**
   * Resolve actor heritage from dungeon entity data when available.
   */
  protected function resolveActorHeritage(?string $actor_id, array $dungeon_data): ?string {
    if (!$actor_id || empty($dungeon_data['entities']) || !is_array($dungeon_data['entities'])) {
      return NULL;
    }

    foreach ($dungeon_data['entities'] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
      if ($entity_id !== $actor_id) {
        continue;
      }
      $heritage = $entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL);
      return is_string($heritage) ? strtolower(trim($heritage)) : NULL;
    }

    return NULL;
  }

  /**
   * Determine whether a room-scene currently allows safe rest activities.
   */
  protected function isSafeRestAvailable(array $game_state, array $dungeon_data): bool {
    if (!empty($game_state['encounter_id'])) {
      return FALSE;
    }
    $room_id = (string) ($game_state['encounter_context']['room_id'] ?? $dungeon_data['active_room_id'] ?? '');
    if ($room_id === '') {
      return FALSE;
    }

    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room) || (string) ($room['room_id'] ?? '') !== $room_id) {
        continue;
      }
      return !empty($room['gameplay_state']['safe_for_rest']);
    }

    return FALSE;
  }

  /**
   * Normalize and deduplicate action ids.
   *
   * @return string[]
   *   Canonical action ids.
   */
  protected function normalizeActionIds(array $actions): array {
    return array_values(array_unique(array_filter(array_map(
      static fn($action): string => strtolower(trim((string) $action)),
      $actions
    ))));
  }

  /**
   * Resolve heritage-like actor metadata from stored participant references.
   */
  public function resolveActorHeritageFromReference(mixed $entity_ref, ?string $fallback = NULL): ?string {
    $decoded = is_string($entity_ref)
      ? json_decode($entity_ref, TRUE)
      : (is_array($entity_ref) ? $entity_ref : []);
    $heritage = $decoded['heritage'] ?? $fallback;
    return is_string($heritage) && trim($heritage) !== ''
      ? strtolower(trim($heritage))
      : NULL;
  }

}
