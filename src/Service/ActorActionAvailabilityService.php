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
    'seek' => [
      'label' => 'Seek',
      'cost' => 1,
      'category' => 'perception',
      'requires_turn' => TRUE,
      'targeting' => 'room',
    ],
    'sense_motive' => [
      'label' => 'Sense Motive',
      'cost' => 1,
      'category' => 'social',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_room',
    ],
    'recall_knowledge' => [
      'label' => 'Recall Knowledge',
      'cost' => 1,
      'category' => 'knowledge',
      'requires_turn' => TRUE,
      'targeting' => 'contextual',
    ],
    'talk' => [
      'label' => 'Talk',
      'cost' => 1,
      'category' => 'conversation',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_room',
    ],
    'request' => [
      'label' => 'Request',
      'cost' => 1,
      'category' => 'conversation',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_room',
    ],
    'perform' => [
      'label' => 'Perform',
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
    'feat' => [
      'label' => 'Use Feat',
      'cost' => 1,
      'category' => 'feat',
      'requires_turn' => TRUE,
      'targeting' => 'contextual',
    ],
    'skill' => [
      'label' => 'Use Skill',
      'cost' => 1,
      'category' => 'skill',
      'requires_turn' => TRUE,
      'targeting' => 'contextual',
    ],
    'consume_item' => [
      'label' => 'Use Consumable',
      'cost' => 1,
      'category' => 'item',
      'requires_turn' => TRUE,
      'targeting' => 'self_or_target',
    ],
    'activate_item' => [
      'label' => 'Activate Item',
      'cost' => 1,
      'category' => 'item',
      'requires_turn' => TRUE,
      'targeting' => 'contextual',
    ],
    'trigger_hazard' => [
      'label' => 'Trigger Hazard Action',
      'cost' => 1,
      'category' => 'hazard',
      'requires_turn' => TRUE,
      'targeting' => 'room_hazard',
    ],
    'demoralize' => [
      'label' => 'Demoralize',
      'cost' => 1,
      'category' => 'social',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
    ],
    'create_diversion' => [
      'label' => 'Create Diversion',
      'cost' => 1,
      'category' => 'social',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_room',
    ],
    'feint' => [
      'label' => 'Feint',
      'cost' => 2,
      'category' => 'social',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
    ],
    'command_animal' => [
      'label' => 'Command Animal',
      'cost' => 1,
      'category' => 'companion',
      'requires_turn' => TRUE,
      'targeting' => 'ally',
    ],
    'raise_shield' => [
      'label' => 'Raise Shield',
      'cost' => 1,
      'category' => 'defense',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'take_cover' => [
      'label' => 'Take Cover',
      'cost' => 1,
      'category' => 'defense',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'point_out' => [
      'label' => 'Point Out',
      'cost' => 1,
      'category' => 'support',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
    ],
    'aid_setup' => [
      'label' => 'Prepare Aid',
      'cost' => 1,
      'category' => 'support',
      'requires_turn' => TRUE,
      'targeting' => 'ally',
    ],
    'administer_first_aid' => [
      'label' => 'Administer First Aid',
      'cost' => 2,
      'category' => 'care',
      'requires_turn' => TRUE,
      'targeting' => 'ally',
    ],
    'treat_poison' => [
      'label' => 'Treat Poison',
      'cost' => 1,
      'category' => 'care',
      'requires_turn' => TRUE,
      'targeting' => 'ally_or_self',
    ],
    'battle_medicine' => [
      'label' => 'Battle Medicine',
      'cost' => 1,
      'category' => 'care',
      'requires_turn' => TRUE,
      'targeting' => 'ally_or_self',
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
    $high_option_families = $this->normalizeHighOptionFamilies($this->buildHighOptionActionFamilies(
      $game_state,
      $dungeon_data,
      $effective_actor_id,
      $is_active_turn_actor,
      $actions_remaining
    ));

    $available_actions = $this->resolveAvailableActionsFromTurnState(
      $room_scene,
      $effective_actor_id,
      $current_entity !== '' ? $current_entity : $effective_actor_id,
      $actions_remaining,
      $reaction_available,
      $heritage,
      $safe_rest_available
    );
    $available_actions = $this->mergeHighOptionAvailability(
      $available_actions,
      $high_option_families,
      $is_active_turn_actor,
      $actions_remaining,
      !$room_scene
    );
    $transition_option_count = (int) (($high_option_families['transition']['option_count'] ?? 0));
    if ($transition_option_count <= 0) {
      $available_actions = array_values(array_filter(
        $available_actions,
        static fn(string $action_id): bool => $action_id !== 'transition'
      ));
    }
    $action_contract = $this->buildActionContractFromAvailableActions(
      $available_actions,
      $effective_actor_id,
      $current_entity !== '' ? $current_entity : $effective_actor_id,
      $high_option_families
    );
    $availability_envelope = $this->buildAvailabilityEnvelopeFromAvailableActions(
      $effective_actor_id,
      $is_active_turn_actor,
      $actions_remaining,
      $reaction_available,
      $available_actions,
      $action_contract,
      $high_option_families
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
    $actions = [];
    $is_active_turn_actor = $actor_id !== NULL
      && $actor_id !== ''
      && $current_entity !== NULL
      && $current_entity !== ''
      && $actor_id === $current_entity;
    $combat_active = !$room_scene;
    $can_use_turn_actions = !$combat_active || $is_active_turn_actor;
    if (!$can_use_turn_actions) {
      return $this->normalizeActionIds($actions);
    }

    $actions[] = 'transition';
    // Product policy: Search should remain available throughout encounter turns.
    $actions = array_merge($actions, ['search', 'seek', 'talk', 'request', 'sense_motive', 'recall_knowledge']);

    $has_single_action_budget = !$combat_active || $actions_remaining >= 1;
    if ($has_single_action_budget) {
      $actions = array_merge($actions, [
        'strike',
        'step',
        'stride',
        'interact',
        'demoralize',
        'create_diversion',
        'command_animal',
        'perform',
        'take_cover',
        'point_out',
        'aid_setup',
        'treat_poison',
        'battle_medicine',
        'raise_shield',
        'delay',
      ]);
      if ($heritage === 'chameleon') {
        $actions[] = 'minor_color_shift';
      }
    }

    if (!$combat_active || $actions_remaining >= 2) {
      $actions = array_merge($actions, ['cast_spell', 'feint', 'administer_first_aid']);
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

    if ($reaction_available) {
      $actions[] = 'reaction';
    }

    return $this->normalizeActionIds($actions);
  }

  /**
   * Build the shared structured action contract for one actor.
   */
  public function buildActionContractFromAvailableActions(array $available_actions, ?string $actor_id = NULL, ?string $current_turn_entity = NULL, array $high_option_families = []): array {
    $normalized_available = array_fill_keys($this->normalizeActionIds($available_actions), TRUE);
    $normalized_families = $this->normalizeHighOptionFamilies($high_option_families);
    $actions = [];

    foreach (self::ACTION_DEFINITIONS as $action_id => $definition) {
      $family = $normalized_families[$action_id] ?? NULL;
      $actions[] = [
        'id' => $action_id,
        'label' => $definition['label'],
        'cost' => $definition['cost'],
        'category' => $definition['category'],
        'requires_turn' => $definition['requires_turn'],
        'targeting' => $definition['targeting'],
        'available' => !empty($normalized_available[$action_id]),
        'option_count' => is_array($family) ? (int) ($family['option_count'] ?? 0) : 0,
        'resolved_options' => is_array($family) ? (array) ($family['options'] ?? []) : [],
      ];
    }

    return [
      'phase' => 'encounter',
      'actor_id' => $actor_id,
      'current_turn_entity' => $current_turn_entity,
      'available_actions' => array_values(array_keys($normalized_available)),
      'actions' => $actions,
      'action_option_families' => $normalized_families,
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
    array $action_contract,
    array $high_option_families = []
  ): array {
    return [
      'actor_instance_id' => $actor_id,
      'is_active_turn_actor' => $is_active_turn_actor,
      'actions_remaining' => $is_active_turn_actor ? max(0, $actions_remaining) : 0,
      'reaction_available' => $is_active_turn_actor && $reaction_available,
      'available_actions' => $this->normalizeActionIds($available_actions),
      'action_contract' => $action_contract,
      'action_option_families' => $high_option_families,
    ];
  }

  /**
   * Build normalized high-option action families for one actor.
   *
   * @return array<string, array<string, mixed>>
   *   Family payloads keyed by canonical action id.
   */
  protected function buildHighOptionActionFamilies(
    array $game_state,
    array $dungeon_data,
    ?string $actor_id,
    bool $is_active_turn_actor,
    int $actions_remaining
  ): array {
    if (!$actor_id || trim($actor_id) === '') {
      return [];
    }

    $entity = $this->resolveActorEntity($actor_id, $dungeon_data);
    $state = is_array($entity['state'] ?? NULL) ? $entity['state'] : [];
    $room_id = (string) ($game_state['encounter_context']['room_id'] ?? $dungeon_data['active_room_id'] ?? '');
    $room = $this->resolveRoomById($room_id, $dungeon_data);
    $room_scene = $this->isRoomSceneMode($game_state);
    $can_use_turn_actions = $room_scene || ($is_active_turn_actor && $actions_remaining > 0);

    $spells = $this->resolveActorSpellOptions($state);
    $skills = $this->resolveActorSkillOptions($state);
    $feats = $this->resolveActorFeatOptions($state);
    $consumables = $this->resolveActorConsumableOptions($state);
    $item_activations = $this->resolveActorItemActivationOptions($state);
    $hazard_actions = $this->resolveRoomHazardOptions($room);
    $transition_options = $this->resolveTransitionOptions($game_state, $dungeon_data);

    return [
      'cast_spell' => [
        'family' => 'spells',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($spells),
        'is_action_currently_legal' => $can_use_turn_actions,
        'options' => $spells,
      ],
      'skill' => [
        'family' => 'skills',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($skills),
        'is_action_currently_legal' => $can_use_turn_actions,
        'options' => $skills,
      ],
      'feat' => [
        'family' => 'feats',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($feats),
        'is_action_currently_legal' => $can_use_turn_actions,
        'options' => $feats,
      ],
      'consume_item' => [
        'family' => 'consumables',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($consumables),
        'is_action_currently_legal' => $can_use_turn_actions,
        'options' => $consumables,
      ],
      'activate_item' => [
        'family' => 'item_activations',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($item_activations),
        'is_action_currently_legal' => $can_use_turn_actions,
        'options' => $item_activations,
      ],
      'trigger_hazard' => [
        'family' => 'hazard_actions',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($hazard_actions),
        'is_action_currently_legal' => $can_use_turn_actions,
        'options' => $hazard_actions,
      ],
      'transition' => [
        'family' => 'connected_rooms',
        'requires_turn' => FALSE,
        'requires_llm_interpretation' => FALSE,
        'option_count' => count($transition_options),
        'is_action_currently_legal' => count($transition_options) > 0,
        'options' => $transition_options,
      ],
    ];
  }

  /**
   * Merge high-option family actions onto base availability.
   */
  protected function mergeHighOptionAvailability(
    array $available_actions,
    array $high_option_families,
    bool $is_active_turn_actor,
    int $actions_remaining,
    bool $enforce_turn_gating = TRUE
  ): array {
    if ($enforce_turn_gating && (!$is_active_turn_actor || $actions_remaining <= 0)) {
      return $this->normalizeActionIds($available_actions);
    }

    foreach ($high_option_families as $action_id => $family) {
      if (!empty($family['is_action_currently_legal']) && (int) ($family['option_count'] ?? 0) > 0) {
        $available_actions[] = $action_id;
      }
    }

    return $this->normalizeActionIds($available_actions);
  }

  /**
   * Normalize family payloads keyed by action id.
   *
   * @return array<string, array<string, mixed>>
   *   Normalized family payloads.
   */
  protected function normalizeHighOptionFamilies(array $families): array {
    $normalized = [];
    foreach ($families as $action_id => $family) {
      $action_id = strtolower(trim((string) $action_id));
      if ($action_id === '' || !is_array($family) || !array_key_exists($action_id, self::ACTION_DEFINITIONS)) {
        continue;
      }
      $options_by_id = [];
      foreach ((array) ($family['options'] ?? []) as $option) {
        if (!is_array($option)) {
          continue;
        }
        $option_id = strtolower(trim((string) ($option['id'] ?? '')));
        if ($option_id === '') {
          continue;
        }
        if (array_key_exists($option_id, $options_by_id)) {
          continue;
        }
        $options_by_id[$option_id] = [
          'id' => $option_id,
          'label' => trim((string) ($option['label'] ?? $option_id)),
          'action_cost' => is_numeric($option['action_cost'] ?? NULL) ? (int) $option['action_cost'] : 1,
          'targeting' => trim((string) ($option['targeting'] ?? 'contextual')),
          'metadata' => is_array($option['metadata'] ?? NULL) ? $option['metadata'] : [],
        ];
      }
      $options = array_values($options_by_id);
      usort($options, static fn(array $a, array $b): int => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));
      $normalized[$action_id] = [
        'family' => trim((string) ($family['family'] ?? 'contextual')),
        'requires_turn' => !empty($family['requires_turn']),
        'requires_llm_interpretation' => !empty($family['requires_llm_interpretation']),
        'is_action_currently_legal' => !empty($family['is_action_currently_legal']),
        'option_count' => count($options),
        'options' => array_slice($options, 0, 25),
      ];
    }
    ksort($normalized);
    return $normalized;
  }

  /**
   * Resolve actor entity row from dungeon state entities.
   */
  protected function resolveActorEntity(string $actor_id, array $dungeon_data): array {
    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      if ($this->entityMatchesActorId($entity, $actor_id)) {
        return $entity;
      }
    }
    return [];
  }

  /**
   * Determine whether an entity row matches the requested actor id.
   */
  protected function entityMatchesActorId(array $entity, string $actor_id): bool {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      return FALSE;
    }

    $entity_ref = $entity['entity_ref'] ?? NULL;
    $decoded_ref = is_string($entity_ref) ? json_decode($entity_ref, TRUE) : (is_array($entity_ref) ? $entity_ref : []);
    $candidates = [
      (string) ($entity['entity_instance_id'] ?? ''),
      (string) ($entity['instance_id'] ?? ''),
      (string) ($entity['id'] ?? ''),
      (string) ($entity['content_id'] ?? ''),
      is_array($decoded_ref) ? (string) ($decoded_ref['content_id'] ?? '') : '',
      is_array($decoded_ref) ? (string) ($decoded_ref['id'] ?? '') : '',
      is_string($entity_ref) ? trim($entity_ref) : '',
    ];

    foreach ($candidates as $candidate) {
      if (trim($candidate) !== '' && trim($candidate) === $actor_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Resolve room row by room id.
   */
  protected function resolveRoomById(string $room_id, array $dungeon_data): array {
    if ($room_id === '') {
      return [];
    }
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      if ((string) ($room['room_id'] ?? '') === $room_id) {
        return $room;
      }
    }
    return [];
  }

  /**
   * Resolve castable spell options from actor state payload.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Normalized spell options.
   */
  protected function resolveActorSpellOptions(array $actor_state): array {
    $spells = [];
    $containers = [
      $actor_state['spells']['prepared'] ?? NULL,
      $actor_state['spells']['known'] ?? NULL,
      $actor_state['spells']['cantrips'] ?? NULL,
      $actor_state['spells']['first_level'] ?? NULL,
      $actor_state['spells']['second_level'] ?? NULL,
      $actor_state['spells']['third_level'] ?? NULL,
      $actor_state['spells']['fourth_level'] ?? NULL,
      $actor_state['spells']['fifth_level'] ?? NULL,
      $actor_state['spells']['sixth_level'] ?? NULL,
      $actor_state['spells']['seventh_level'] ?? NULL,
      $actor_state['spells']['eighth_level'] ?? NULL,
      $actor_state['spells']['ninth_level'] ?? NULL,
      $actor_state['spellbook']['prepared'] ?? NULL,
      $actor_state['spellbook']['known'] ?? NULL,
      $actor_state['spellbook']['spells'] ?? NULL,
      $actor_state['known_spells'] ?? NULL,
    ];
    foreach ($containers as $container) {
      foreach ($this->normalizeOptionContainer($container, 'spell') as $option) {
        $spells[$option['id']] = $option;
      }
    }
    $ranked_spell_groups = [
      'cantrips' => 0,
      'first_level' => 1,
      'second_level' => 2,
      'third_level' => 3,
      'fourth_level' => 4,
      'fifth_level' => 5,
      'sixth_level' => 6,
      'seventh_level' => 7,
      'eighth_level' => 8,
      'ninth_level' => 9,
    ];
    foreach ($ranked_spell_groups as $group_key => $spell_level) {
      $group = $actor_state['spells'][$group_key] ?? NULL;
      if (!is_array($group)) {
        continue;
      }
      foreach ($group as $spell_entry) {
        $row = is_array($spell_entry)
          ? $spell_entry
          : ['id' => (string) $spell_entry, 'name' => (string) $spell_entry];
        if (!isset($row['spell_level']) && !isset($row['level'])) {
          $row['spell_level'] = $spell_level;
        }
        $option = $this->normalizeOptionRow($row, 'spell', 2, 'contextual');
        if ($option !== NULL) {
          $spells[$option['id']] = $option;
        }
      }
    }
    return array_values($spells);
  }

  /**
   * Resolve skill options from actor state payload.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Normalized skill options.
   */
  protected function resolveActorSkillOptions(array $actor_state): array {
    $skill_map = [];
    $skills = $actor_state['skills'] ?? NULL;
    if (is_array($skills)) {
      foreach ($skills as $name => $skill_state) {
        if (is_array($skill_state)) {
          $raw_name = is_string($name) ? $name : (string) ($skill_state['name'] ?? $skill_state['label'] ?? $skill_state['id'] ?? '');
          $normalized_name = trim(str_replace('_', ' ', (string) $raw_name));
          if ($normalized_name === '') {
            continue;
          }
          $skill_map[strtolower($normalized_name)] = [
            'name' => $normalized_name,
            'modifier' => is_numeric($skill_state['bonus'] ?? NULL)
              ? (int) $skill_state['bonus']
              : (is_numeric($skill_state['modifier'] ?? NULL) ? (int) $skill_state['modifier'] : 0),
            'proficiency' => trim((string) ($skill_state['proficiency'] ?? $skill_state['proficiencyRank'] ?? $skill_state['rank'] ?? '')),
          ];
          continue;
        }
        if (is_string($skill_state) || is_numeric($skill_state)) {
          $normalized_name = trim(str_replace('_', ' ', (string) $name));
          if ($normalized_name === '') {
            continue;
          }
          $skill_map[strtolower($normalized_name)] = [
            'name' => $normalized_name,
            'modifier' => 0,
            'proficiency' => trim((string) $skill_state),
          ];
        }
      }
    }

    $feat_training = is_array($actor_state['features']['featTraining'] ?? NULL) ? $actor_state['features']['featTraining'] : [];
    foreach ((array) ($feat_training['skills'] ?? []) as $skill_name) {
      $normalized_name = trim(str_replace('_', ' ', (string) $skill_name));
      if ($normalized_name === '') {
        continue;
      }
      $key = strtolower($normalized_name);
      $existing = $skill_map[$key] ?? ['name' => $normalized_name, 'modifier' => 0, 'proficiency' => ''];
      $skill_map[$key] = [
        'name' => $existing['name'],
        'modifier' => (int) ($existing['modifier'] ?? 0),
        'proficiency' => trim((string) ($existing['proficiency'] ?: 'trained')),
      ];
    }

    foreach ((array) ($feat_training['lore'] ?? []) as $lore_name) {
      $normalized_name = trim(str_replace('_', ' ', (string) $lore_name));
      if ($normalized_name === '') {
        continue;
      }
      $display_name = str_ends_with(strtolower($normalized_name), ' lore') ? $normalized_name : ($normalized_name . ' Lore');
      $key = strtolower($display_name);
      $existing = $skill_map[$key] ?? ['name' => $display_name, 'modifier' => 0, 'proficiency' => ''];
      $skill_map[$key] = [
        'name' => $existing['name'],
        'modifier' => (int) ($existing['modifier'] ?? 0),
        'proficiency' => trim((string) ($existing['proficiency'] ?: 'trained')),
      ];
    }

    $conditional_mods = (array) ($actor_state['features']['featConditionalModifiers']['skills'] ?? []);
    foreach ($conditional_mods as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $target_name = trim(str_replace('_', ' ', (string) ($entry['target'] ?? $entry['skill'] ?? $entry['name'] ?? '')));
      if ($target_name === '') {
        continue;
      }
      $key = strtolower($target_name);
      $existing = $skill_map[$key] ?? ['name' => $target_name, 'modifier' => 0, 'proficiency' => ''];
      $modifier_delta = is_numeric($entry['modifier'] ?? NULL)
        ? (int) $entry['modifier']
        : (is_numeric($entry['value'] ?? NULL) ? (int) $entry['value'] : 0);
      $skill_map[$key] = [
        'name' => $existing['name'],
        'modifier' => (int) ($existing['modifier'] ?? 0) + $modifier_delta,
        'proficiency' => trim((string) ($existing['proficiency'] ?? '')),
      ];
    }

    $options = [];
    foreach ($skill_map as $skill) {
      $skill_name = trim((string) ($skill['name'] ?? ''));
      if ($skill_name === '') {
        continue;
      }
      $skill_id = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '_', $skill_name), '_'));
      if ($skill_id === '') {
        continue;
      }
      $modifier = (int) ($skill['modifier'] ?? 0);
      $proficiency = trim((string) ($skill['proficiency'] ?? ''));
      $options[$skill_id] = [
        'id' => $skill_id,
        'label' => $skill_name,
        'action_cost' => 1,
        'targeting' => 'contextual',
        'metadata' => [
          'id' => $skill_id,
          'skill_name' => $skill_name,
          'name' => $skill_name,
          'modifier' => $modifier,
          'bonus' => $modifier,
          'proficiency' => $proficiency !== '' ? $proficiency : 'untrained',
        ],
      ];
    }

    return array_values($options);
  }

  /**
   * Resolve feat options from actor state payload.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Normalized feat options.
   */
  protected function resolveActorFeatOptions(array $actor_state): array {
    $feats = [];
    $containers = [
      $actor_state['feats'] ?? NULL,
      $actor_state['class_features'] ?? NULL,
      $actor_state['actions']['feats'] ?? NULL,
    ];
    foreach ($containers as $container) {
      foreach ($this->normalizeOptionContainer($container, 'feat') as $option) {
        $feats[$option['id']] = $option;
      }
    }
    return array_values($feats);
  }

  /**
   * Resolve consumable options from actor inventory payload.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Normalized consumable options.
   */
  protected function resolveActorConsumableOptions(array $actor_state): array {
    $items = (array) ($actor_state['inventory']['items'] ?? []);
    $consumables = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $item_type = strtolower(trim((string) ($item['item_type'] ?? ($item['type'] ?? ''))));
      if ($item_type !== 'consumable' && !is_array($item['consumable_stats'] ?? NULL)) {
        continue;
      }
      $option = $this->normalizeOptionRow($item, 'consumable', 1, 'self_or_target');
      if ($option !== NULL) {
        $consumables[$option['id']] = $option;
      }
    }
    return array_values($consumables);
  }

  /**
   * Resolve activatable non-consumable inventory items.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Normalized activatable item options.
   */
  protected function resolveActorItemActivationOptions(array $actor_state): array {
    $items = (array) ($actor_state['inventory']['items'] ?? []);
    $activations = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $item_type = strtolower(trim((string) ($item['item_type'] ?? ($item['type'] ?? ''))));
      if ($item_type === 'consumable') {
        continue;
      }
      $has_activation = !empty($item['activatable'])
        || !empty($item['activation'])
        || !empty($item['activations'])
        || is_array($item['activation_stats'] ?? NULL);
      if (!$has_activation) {
        continue;
      }
      $option = $this->normalizeOptionRow($item, 'item', 1, 'contextual');
      if ($option !== NULL) {
        $activations[$option['id']] = $option;
      }
    }
    return array_values($activations);
  }

  /**
   * Resolve room hazard actions for the actor context.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Normalized hazard action options.
   */
  protected function resolveRoomHazardOptions(array $room): array {
    $hazards = [];
    $containers = [
      $room['hazards'] ?? NULL,
      $room['contents_data']['hazards'] ?? NULL,
    ];
    foreach ($containers as $container) {
      if (!is_array($container)) {
        continue;
      }
      foreach ($container as $key => $hazard) {
        if (!is_array($hazard)) {
          continue;
        }
        if (is_string($key) && trim($key) !== '' && !isset($hazard['id']) && !isset($hazard['hazard_id'])) {
          $hazard['id'] = $key;
        }
        $discovered = !array_key_exists('is_discovered', $hazard) || !empty($hazard['is_discovered']);
        if (!$discovered) {
          continue;
        }
        $option = $this->normalizeOptionRow($hazard, 'hazard', 1, 'room_hazard');
        if ($option !== NULL) {
          $hazards[$option['id']] = $option;
        }
      }
    }
    return array_values($hazards);
  }

  /**
   * Resolve room transition options from passable connections in active context.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Canonical connected-room transition options.
   */
  protected function resolveTransitionOptions(array $game_state, array $dungeon_data): array {
    $active_room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? $dungeon_data['active_room_id'] ?? ''));
    if ($active_room_id === '') {
      return [];
    }

    $options = [];
    foreach ((array) ($dungeon_data['connections'] ?? []) as $connection) {
      if (!is_array($connection) || empty($connection['is_passable'])) {
        continue;
      }
      $from_room = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? $connection['from']['room_id'] ?? ''));
      $to_room = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? $connection['to']['room_id'] ?? ''));
      $target_room_id = '';
      if ($from_room === $active_room_id && $to_room !== '') {
        $target_room_id = $to_room;
      }
      elseif ($to_room === $active_room_id && $from_room !== '') {
        $target_room_id = $from_room;
      }
      if ($target_room_id === '' || $target_room_id === $active_room_id) {
        continue;
      }
      if (array_key_exists($target_room_id, $options)) {
        continue;
      }

      $room = $this->resolveRoomById($target_room_id, $dungeon_data);
      $label = trim((string) ($room['name'] ?? ''));
      if ($label === '') {
        $label = $this->humanizeOptionId($target_room_id);
      }
      $options[$target_room_id] = [
        'id' => $target_room_id,
        'label' => $label,
        'action_cost' => 0,
        'targeting' => 'connected_room',
        'metadata' => [
          'room_id' => $target_room_id,
          'connection_id' => trim((string) ($connection['connection_id'] ?? '')),
        ],
      ];
    }

    return array_values($options);
  }

  /**
   * Normalize flexible option containers into option rows.
   *
   * @return array<int, array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}>
   *   Normalized options.
   */
  protected function normalizeOptionContainer(mixed $container, string $kind): array {
    $options = [];
    if (is_array($container)) {
      foreach ($container as $key => $value) {
        if (is_array($value)) {
          if (is_string($key) && trim($key) !== '' && !isset($value['id']) && !isset($value[$kind . '_id'])) {
            $value['id'] = $key;
          }
          $option = $this->normalizeOptionRow($value, $kind, 1, 'contextual');
          if ($option !== NULL) {
            $options[$option['id']] = $option;
          }
          continue;
        }
        if (is_string($value) || is_numeric($value)) {
          $raw = [
            'id' => is_string($key) ? $key : (string) $value,
            'name' => (string) $value,
          ];
          $option = $this->normalizeOptionRow($raw, $kind, 1, 'contextual');
          if ($option !== NULL) {
            $options[$option['id']] = $option;
          }
        }
      }
    }
    return array_values($options);
  }

  /**
   * Normalize one option row into canonical option shape.
   *
   * @return array{id: string, label: string, action_cost: int, targeting: string, metadata: array<string,mixed>}|null
   *   Normalized option or NULL when missing identifiers.
   */
  protected function normalizeOptionRow(array $row, string $kind, int $default_cost, string $default_targeting): ?array {
    $option_id = strtolower(trim((string) ($row['id'] ?? $row[$kind . '_id'] ?? $row['content_id'] ?? $row['item_id'] ?? $row['name'] ?? '')));
    if ($option_id === '') {
      return NULL;
    }
    $label = trim((string) ($row['label'] ?? $row['name'] ?? $row['display_name'] ?? ''));
    if ($label === '') {
      $label = $this->humanizeOptionId($option_id);
    }
    $action_cost = is_numeric($row['action_cost'] ?? NULL)
      ? (int) $row['action_cost']
      : (is_numeric($row['actions'] ?? NULL) ? (int) $row['actions'] : $default_cost);
    $targeting = $this->resolveOptionTargeting($row, $kind, $default_targeting);

    return [
      'id' => $option_id,
      'label' => $label !== '' ? $label : $option_id,
      'action_cost' => max(0, $action_cost),
      'targeting' => $targeting,
      'metadata' => $row,
    ];
  }

  /**
   * Resolve canonical targeting token for an option row.
   */
  protected function resolveOptionTargeting(array $row, string $kind, string $default_targeting): string {
    $explicit = trim((string) ($row['targeting'] ?? $row['targeting_mode'] ?? ''));
    if ($explicit !== '') {
      return $explicit;
    }

    if ($kind === 'spell') {
      return $this->inferSpellTargetingFromMetadata($row, $default_targeting);
    }

    if (in_array($kind, ['skill', 'feat', 'item', 'consumable'], TRUE)) {
      return $this->inferNonSpellTargetingFromMetadata($row, $default_targeting, $kind);
    }

    return trim((string) $default_targeting);
  }

  /**
   * Infer spell targeting mode from spell metadata when explicit mode is absent.
   */
  protected function inferSpellTargetingFromMetadata(array $spell, string $fallback): string {
    $target_text = strtolower(trim((string) (
      $spell['target']
      ?? $spell['targets']
      ?? $spell['targeting_text']
      ?? $spell['range_text']
      ?? ''
    )));
    $area_text = strtolower(trim((string) (
      $spell['area']
      ?? $spell['area_of_effect']
      ?? ''
    )));
    $traits = array_map(
      static fn($trait): string => strtolower(trim((string) $trait)),
      is_array($spell['traits'] ?? NULL) ? $spell['traits'] : []
    );

    if (in_array('self', $traits, TRUE) || $target_text === 'self' || strpos($target_text, 'yourself') !== FALSE) {
      return 'self';
    }
    if (strpos($target_text, 'ally') !== FALSE || strpos($target_text, 'willing') !== FALSE) {
      return 'ally_or_self';
    }
    if (
      $area_text !== ''
      || strpos($target_text, 'burst') !== FALSE
      || strpos($target_text, 'cone') !== FALSE
      || strpos($target_text, 'line') !== FALSE
      || strpos($target_text, 'emanation') !== FALSE
      || strpos($target_text, 'radius') !== FALSE
    ) {
      return 'contextual';
    }
    if (
      strpos($target_text, 'creature') !== FALSE
      || strpos($target_text, 'enemy') !== FALSE
      || strpos($target_text, 'target') !== FALSE
    ) {
      return 'hostile_entity';
    }

    return trim((string) $fallback);
  }

  /**
   * Infer non-spell targeting mode from option metadata when explicit mode is absent.
   */
  protected function inferNonSpellTargetingFromMetadata(array $row, string $fallback, string $kind = ''): string {
    $text = strtolower(trim(implode(' ', array_filter([
      (string) ($row['target'] ?? ''),
      (string) ($row['targets'] ?? ''),
      (string) ($row['targeting_text'] ?? ''),
      (string) ($row['range_text'] ?? ''),
      (string) ($row['description'] ?? ''),
      (string) ($row['desc'] ?? ''),
      (string) ($row['effect'] ?? ''),
      (string) ($row['benefit'] ?? ''),
      (string) ($row['name'] ?? ''),
      (string) ($row['label'] ?? ''),
      (string) ($row['id'] ?? ''),
    ], static fn($value): bool => trim($value) !== ''))));

    if ($text === '') {
      return trim((string) $fallback);
    }
    if (
      (strpos($text, 'self only') !== FALSE || strpos($text, 'self-only') !== FALSE || strpos($text, 'yourself') !== FALSE)
      && strpos($text, 'ally') === FALSE
    ) {
      return 'self';
    }
    if (
      strpos($text, 'ally or self') !== FALSE
      || strpos($text, 'self or ally') !== FALSE
      || strpos($text, 'willing creature') !== FALSE
    ) {
      return 'ally_or_self';
    }
    if (strpos($text, 'ally') !== FALSE) {
      return 'ally';
    }
    if (
      strpos($text, 'hostile') !== FALSE
      || strpos($text, 'enemy') !== FALSE
      || strpos($text, 'foe') !== FALSE
      || strpos($text, 'opponent') !== FALSE
    ) {
      return 'hostile_entity';
    }
    if (strpos($text, 'room hazard') !== FALSE || strpos($text, 'hazard') !== FALSE) {
      return 'room_hazard';
    }
    if (
      strpos($text, 'connected room') !== FALSE
      || strpos($text, 'adjacent room') !== FALSE
      || strpos($text, 'next room') !== FALSE
    ) {
      return 'connected_room';
    }
    if (strpos($text, 'area origin') !== FALSE || strpos($text, 'origin hex') !== FALSE) {
      return 'area_origin';
    }
    if (
      strpos($text, 'hex') !== FALSE
      || strpos($text, 'tile') !== FALSE
      || strpos($text, 'grid') !== FALSE
      || strpos($text, 'destination') !== FALSE
    ) {
      return 'hex';
    }
    if (
      strpos($text, 'object') !== FALSE
      || strpos($text, 'barrier') !== FALSE
      || strpos($text, 'door') !== FALSE
      || strpos($text, 'container') !== FALSE
      || strpos($text, 'lever') !== FALSE
      || strpos($text, 'switch') !== FALSE
    ) {
      return 'entity_or_object';
    }
    if (strpos($text, 'room') !== FALSE) {
      return 'room';
    }
    if (
      strpos($text, 'target') !== FALSE
      || strpos($text, 'creature') !== FALSE
      || strpos($text, 'entity') !== FALSE
      || strpos($text, 'npc') !== FALSE
    ) {
      $normalized_kind = strtolower(trim((string) $kind));
      if (in_array($normalized_kind, ['item', 'consumable'], TRUE)) {
        return 'self_or_target';
      }
      return 'entity_or_room';
    }

    return trim((string) $fallback);
  }

  /**
   * Convert a canonical option id into a readable default label.
   */
  protected function humanizeOptionId(string $option_id): string {
    $normalized = trim(str_replace(['_', '-'], ' ', strtolower($option_id)));
    return $normalized !== '' ? ucwords($normalized) : $option_id;
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
