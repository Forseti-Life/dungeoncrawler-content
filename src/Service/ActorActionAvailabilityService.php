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
    'use_feat' => [
      'label' => 'Use Feat',
      'cost' => 1,
      'category' => 'feat',
      'requires_turn' => TRUE,
      'targeting' => 'contextual',
    ],
    'use_consumable' => [
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
      $actions_remaining
    );
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

    if ($room_scene) {
      if ($is_active_turn_actor) {
        $actions[] = 'transition';
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
      $actions[] = 'transition';
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

    $spells = $this->resolveActorSpellOptions($state);
    $feats = $this->resolveActorFeatOptions($state);
    $consumables = $this->resolveActorConsumableOptions($state);
    $item_activations = $this->resolveActorItemActivationOptions($state);
    $hazard_actions = $this->resolveRoomHazardOptions($room);

    return [
      'cast_spell' => [
        'family' => 'spells',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($spells),
        'is_action_currently_legal' => $is_active_turn_actor && $actions_remaining >= 2 && $spells !== [],
        'options' => $spells,
      ],
      'use_feat' => [
        'family' => 'feats',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($feats),
        'is_action_currently_legal' => $is_active_turn_actor && $actions_remaining >= 1 && $feats !== [],
        'options' => $feats,
      ],
      'use_consumable' => [
        'family' => 'consumables',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($consumables),
        'is_action_currently_legal' => $is_active_turn_actor && $actions_remaining >= 1 && $consumables !== [],
        'options' => $consumables,
      ],
      'activate_item' => [
        'family' => 'item_activations',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($item_activations),
        'is_action_currently_legal' => $is_active_turn_actor && $actions_remaining >= 1 && $item_activations !== [],
        'options' => $item_activations,
      ],
      'trigger_hazard' => [
        'family' => 'hazard_actions',
        'requires_turn' => TRUE,
        'requires_llm_interpretation' => TRUE,
        'option_count' => count($hazard_actions),
        'is_action_currently_legal' => $is_active_turn_actor && $actions_remaining >= 1 && $hazard_actions !== [],
        'options' => $hazard_actions,
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
    int $actions_remaining
  ): array {
    if (!$is_active_turn_actor || $actions_remaining <= 0) {
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
    return array_values($spells);
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
    return [
      'id' => $option_id,
      'label' => $label !== '' ? $label : $option_id,
      'action_cost' => max(0, $action_cost),
      'targeting' => trim((string) ($row['targeting'] ?? $default_targeting)),
      'metadata' => $row,
    ];
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
