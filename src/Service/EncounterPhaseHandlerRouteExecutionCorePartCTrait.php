<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Core route execution methods part C.
 */
trait EncounterPhaseHandlerRouteExecutionCorePartCTrait {
  use EncounterNavigationTransitionCoordinatorTrait;
  use EncounterNpcTurnCoordinatorTrait;
  use EncounterEventProjectionCoordinatorTrait;

  protected function routeStrideIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $result = $this->processStride($encounter_id, (string) $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    if (!empty($result['error'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => (string) $result['error']],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $mutations = $result['mutations'] ?? [];
    $events = [];
    $action_cost = $this->getActionCost('stride', $params);

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);
    if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
      $game_state['entities'][$actor_id]['cover_active'] = FALSE;
    }
    $game_state['turn']['last_stride_ft'] = (int) ($result['distance_ft'] ?? $params['distance_ft'] ?? 25);

    $movement_execution_request = $this->requireOptionalContractPayload(
      $result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'stride.movement.execution_request'
    );
    $movement_resolution_envelope = $this->requireOptionalContractPayload(
      $result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'stride.movement.resolution_envelope'
    );
    $movement_packet = $this->requireOptionalContractPayload(
      $result['movement_packet'] ?? NULL,
      'movement_resolution',
      CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION,
      'movement_packet'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'stride',
      (string) $actor_id,
      NULL,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      is_array($movement_packet) ? [$movement_packet] : [],
      [
        'from' => $result['from_hex'] ?? NULL,
        'to' => $result['to_hex'] ?? NULL,
        'distance_ft' => $result['distance_ft'] ?? NULL,
        'is_forced' => !empty($result['is_forced']),
      ]
    );

    $events[] = GameEventLogger::buildEvent('stride', 'encounter', $actor_id, [
      'execution_request' => $execution_request,
      'resolution_envelope' => $resolution_envelope,
      'movement_execution_request' => $movement_execution_request,
      'movement_resolution_envelope' => $movement_resolution_envelope,
      'from' => $result['from_hex'] ?? NULL,
      'to' => $result['to_hex'] ?? NULL,
      'distance_ft' => $result['distance_ft'] ?? NULL,
      'is_forced' => !empty($result['is_forced']),
      'movement_packet' => $movement_packet,
      'movement_execution_request' => $movement_execution_request,
      'movement_resolution_envelope' => $movement_resolution_envelope,
      'action_cost' => $action_cost,
      'round' => $game_state['round'] ?? NULL,
    ]);

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'movement_execution_request' => $movement_execution_request,
        'movement_packet' => $movement_packet,
        'movement_resolution_envelope' => $movement_resolution_envelope,
      ]),
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $result['narration'] ?? NULL,
    ];
  }

  /**
   * Router seam: execute cast-spell intent block with legacy side effects.
   */
  protected function routeCastSpellIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $spell_name = $params['spell_name'] ?? 'unknown';
    $action_cost = $params['action_cost'] ?? 2;
    $target_hp_before = NULL;
    if ($encounter_id && is_string($target_id) && trim($target_id) !== '') {
      $enc_before = $this->encounterStore->loadEncounter((int) $encounter_id);
      $ptcp_before = $enc_before ? $this->findEncounterParticipantByEntityId($enc_before, (string) $target_id) : NULL;
      if (is_array($ptcp_before) && is_numeric($ptcp_before['hp'] ?? NULL)) {
        $target_hp_before = (int) $ptcp_before['hp'];
      }
    }

    $result = $this->processCastSpell($encounter_id, (string) $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    if (empty($result['cast'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => [
            'error' => (string) ($result['error'] ?? 'Spell cast failed.'),
          ],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => $result['narration'] ?? NULL,
        ],
      ];
    }

    $resolved_target_id = is_string($result['target_id'] ?? NULL) && trim((string) $result['target_id']) !== ''
      ? (string) $result['target_id']
      : $target_id;
    $target_hp_after = NULL;
    if ($encounter_id && is_string($resolved_target_id) && trim($resolved_target_id) !== '') {
      $enc_after = $this->encounterStore->loadEncounter((int) $encounter_id);
      $ptcp_after = $enc_after ? $this->findEncounterParticipantByEntityId($enc_after, (string) $resolved_target_id) : NULL;
      if (is_array($ptcp_after) && is_numeric($ptcp_after['hp'] ?? NULL)) {
        $target_hp_after = (int) $ptcp_after['hp'];
      }
    }

    $resolved_damage = is_numeric($result['damage'] ?? NULL) ? (int) $result['damage'] : NULL;
    if ($resolved_damage === NULL && is_numeric($target_hp_before) && is_numeric($target_hp_after)) {
      $hp_delta = (int) $target_hp_before - (int) $target_hp_after;
      if ($hp_delta > 0) {
        $resolved_damage = $hp_delta;
      }
    }
    $mutations = $result['mutations'] ?? [];
    $events = [];
    $caster_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $spell_target_name = $resolved_target_id ? $this->resolveEntityName($resolved_target_id, $game_state, $dungeon_data) : NULL;

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);
    if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
      $game_state['entities'][$actor_id]['cover_active'] = FALSE;
    }

    {
      $enc_air_cs = $this->encounterStore->loadEncounter($encounter_id);
      $ptcp_air_cs = $enc_air_cs ? $this->findEncounterParticipantByEntityId($enc_air_cs, (string) $actor_id) : NULL;
      if ($ptcp_air_cs) {
        $edata_air_cs = !empty($ptcp_air_cs['entity_ref']) ? json_decode((string) $ptcp_air_cs['entity_ref'], TRUE) : [];
        if (!empty($edata_air_cs['airborne'])) {
          $edata_air_cs['air_decrement_this_turn'] = 2;
          $this->encounterStore->updateParticipant((int) $ptcp_air_cs['id'], ['entity_ref' => json_encode($edata_air_cs)]);
        }
      }
    }
    $spell_execution_request = $this->requireOptionalContractPayload(
      $result['execution_request'] ?? NULL,
      'combat_execution_request',
      CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION,
      'cast_spell.execution_request'
    );
    $spell_resolution_envelope = $this->requireOptionalContractPayload(
      $result['resolution_envelope'] ?? NULL,
      'combat_resolution_envelope',
      CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'cast_spell.resolution_envelope'
    );
    $damage_packet = $this->requireOptionalContractPayload(
      $result['damage_packet'] ?? NULL,
      'damage_application',
      CombatResolutionContractService::DAMAGE_PACKET_CONTRACT_VERSION,
      'cast_spell.damage_packet'
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      'cast_spell',
      (string) $actor_id,
      $resolved_target_id,
      $params
    );
    $resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope(
      $execution_request,
      is_array($damage_packet) ? [$damage_packet] : [],
      [
        'spell' => $spell_name,
        'action_cost' => $action_cost,
        'damage' => $resolved_damage,
        'damage_type' => is_string($result['damage_type'] ?? NULL) ? (string) $result['damage_type'] : NULL,
        'missiles_fired' => is_numeric($result['missiles_fired'] ?? NULL) ? (int) $result['missiles_fired'] : NULL,
      ]
    );

    $events[] = GameEventLogger::buildEvent('cast_spell', 'encounter', $actor_id, [
      'execution_request' => $execution_request,
      'resolution_envelope' => $resolution_envelope,
      'spell_execution_request' => $spell_execution_request,
      'spell_resolution_envelope' => $spell_resolution_envelope,
      'disposition_change' => is_array($result['disposition_change'] ?? NULL) ? $result['disposition_change'] : NULL,
      'spell' => $spell_name,
      'action_cost' => $action_cost,
      'damage' => $resolved_damage,
      'damage_type' => is_string($result['damage_type'] ?? NULL) ? (string) $result['damage_type'] : NULL,
      'damage_packet' => $damage_packet,
      'missiles_fired' => is_numeric($result['missiles_fired'] ?? NULL) ? (int) $result['missiles_fired'] : NULL,
      'target_name' => $spell_target_name,
      'round' => $game_state['round'] ?? NULL,
    ], $result['narration'] ?? NULL, $resolved_target_id);

    $spell_desc = $spell_target_name
      ? sprintf('%s casts %s targeting %s.', $caster_name, $spell_name, $spell_target_name)
      : sprintf('%s casts %s.', $caster_name, $spell_name);
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'action',
      'speaker' => $caster_name,
      'speaker_type' => 'player',
      'speaker_ref' => $actor_id,
      'content' => $spell_desc,
      'visibility' => 'public',
      'mechanical_data' => [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'spell_execution_request' => $spell_execution_request,
        'spell_resolution_envelope' => $spell_resolution_envelope,
        'spell_name' => $spell_name,
        'spell_level' => $params['spell_level'] ?? NULL,
        'action_cost' => $action_cost,
        'damage' => $resolved_damage,
        'damage_type' => is_string($result['damage_type'] ?? NULL) ? (string) $result['damage_type'] : NULL,
        'damage_packet' => $damage_packet,
        'missiles_fired' => is_numeric($result['missiles_fired'] ?? NULL) ? (int) $result['missiles_fired'] : NULL,
        'target' => $resolved_target_id,
      ],
    ]);

    return [
      'result' => array_merge($result, [
        'execution_request' => $execution_request,
        'resolution_envelope' => $resolution_envelope,
        'spell_execution_request' => $spell_execution_request,
        'spell_resolution_envelope' => $spell_resolution_envelope,
        'damage_packet' => $damage_packet,
      ]),
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $result['narration'] ?? NULL,
    ];
  }
  public function getAvailableActions(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    return $this->actionAvailability
      ->resolveEncounterAvailability($game_state, $dungeon_data, $actor_id)['available_actions'];
  }

  /**
   * Build the canonical encounter action contract for client consumers.
   */
  public function getClientActionContract(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    return $this->actionAvailability
      ->resolveEncounterAvailability($game_state, $dungeon_data, $actor_id)['action_contract'];
  }

  /**
   * Determine whether the current encounter context is room-scene mode.
   */
  protected function isRoomSceneMode(array $game_state): bool {
    return $this->roomSceneEncounterCoordinator->isRoomSceneMode($game_state);
  }

  /**
   * Resolves actor heritage from dungeon entity data when available.
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
      if ($entity_id === $actor_id) {
        $heritage = $entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL);
        return is_string($heritage) ? $heritage : NULL;
      }
    }

    return NULL;
  }

  /**
   * Process encounter talk through the room-chat pipeline.
   */
  protected function processTalk(?string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->encounterActionExecutor->processTalk(
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      fn(array $state, array $dungeon, ?string $aid): array => $this->captureEncounterTurnContext($state, $dungeon, $aid),
      fn(?string $aid, array $dungeon, array $talk_params): ?int => $this->resolveActorCharacterId($aid, $dungeon, $talk_params),
      fn(?string $id, array $state, array $dungeon): string => $this->resolveEntityName($id, $state, $dungeon),
      fn(array $turn_ctx, string $content): string => $this->prefixEncounterChatLine($turn_ctx, $content),
      fn(array $turn_ctx): string => $this->buildEncounterChatPrefix($turn_ctx)
    );
  }

  /**
   * Resolve a character ID for the acting entity when available.
   */
  protected function resolveActorCharacterId(?string $actor_id, array $dungeon_data, array $params = []): ?int {
    if (isset($params['character_id']) && is_numeric($params['character_id'])) {
      return (int) $params['character_id'];
    }
    if (!$actor_id || empty($dungeon_data['entities']) || !is_array($dungeon_data['entities'])) {
      return NULL;
    }

    foreach ($dungeon_data['entities'] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $instance_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
      if ((string) $instance_id !== (string) $actor_id) {
        continue;
      }
      $content_id = $entity['entity_ref']['content_id'] ?? NULL;
      return is_numeric($content_id) ? (int) $content_id : NULL;
    }

    return NULL;
  }

  // =========================================================================
  // Action processors.
  // =========================================================================

  /**
   * Resolve and normalize weapon data for a strike.
   *
   * Preferred contract:
   * - params.weapon.weapon_id (and optional weapon_name)
   *
   * When weapon_id is omitted, we attempt to default to the actor's first
   * equipped weapon from canonical character state (when available).
   */
  protected function resolveStrikeWeapon(string $actor_id, array $params, array $dungeon_data, ?int $campaign_id): array {
    $weapon_input = is_array($params['weapon'] ?? NULL) ? $params['weapon'] : [];

    $weapon_id = trim((string) (
      $weapon_input['weapon_id']
      ?? $weapon_input['weaponId']
      ?? $params['weapon_id']
      ?? $params['weaponId']
      ?? ''
    ));
    $weapon_name = trim((string) (
      $weapon_input['weapon_name']
      ?? $weapon_input['weaponName']
      ?? $params['weapon_name']
      ?? $params['weaponName']
      ?? ''
    ));

    $canonical_state = NULL;

    // Default weapon_id from canonical state when the caller didn't provide one.
    if ($weapon_id === '' && $campaign_id && !empty($dungeon_data['entities']) && is_array($dungeon_data['entities'])) {
      $idx = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
      if ($idx !== NULL && !empty($dungeon_data['entities'][$idx]) && is_array($dungeon_data['entities'][$idx])) {
        $actor_entity = $dungeon_data['entities'][$idx];
        $canonical_state = $this->loadCanonicalCharacterState($actor_entity, (int) $campaign_id);

        $worn_weapons = $canonical_state['inventory']['worn']['weapons'] ?? NULL;
        if (is_array($worn_weapons) && !empty($worn_weapons[0]) && is_array($worn_weapons[0])) {
          $weapon_id = trim((string) (
            $worn_weapons[0]['item_id']
            ?? $worn_weapons[0]['id']
            ?? $worn_weapons[0]['weapon_id']
            ?? ''
          ));
        }
      }
    }

    if ($weapon_id !== '') {
      $weapon_def = EquipmentCatalogService::CATALOG[$weapon_id] ?? NULL;
      if (!is_array($weapon_def) || (($weapon_def['type'] ?? '') !== 'weapon')) {
        return ['error' => "Unknown weapon_id for strike: {$weapon_id}"];
      }

      if ($weapon_name === '') {
        $weapon_name = (string) ($weapon_def['name'] ?? $weapon_id);
      }

      $weapon_stats = is_array($weapon_def['weapon_stats'] ?? NULL) ? $weapon_def['weapon_stats'] : [];
      $traits = is_array($weapon_stats['traits'] ?? NULL) ? $weapon_stats['traits'] : [];

      $is_thrown = FALSE;
      foreach ($traits as $trait) {
        $t = strtolower(trim((string) $trait));
        if (str_starts_with($t, 'thrown-')) {
          $is_thrown = TRUE;
          break;
        }
      }

      $is_ranged = $is_thrown || isset($weapon_stats['range']);

      // If we haven't loaded canonical state yet, attempt it now for attack bonus.
      if ($canonical_state === NULL && $campaign_id && !empty($dungeon_data['entities']) && is_array($dungeon_data['entities'])) {
        $idx = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
        if ($idx !== NULL && !empty($dungeon_data['entities'][$idx]) && is_array($dungeon_data['entities'][$idx])) {
          $canonical_state = $this->loadCanonicalCharacterState($dungeon_data['entities'][$idx], (int) $campaign_id);
        }
      }

      $level = (int) (
        $canonical_state['basicInfo']['level']
        ?? $canonical_state['basic_info']['level']
        ?? $canonical_state['level']
        ?? 1
      );

      $ability_score = function (?array $state, string $ability): int {
        if (!is_array($state)) {
          return 10;
        }
        $abilities = $state['abilities'] ?? [];
        $raw = $abilities[$ability] ?? $abilities[strtolower($ability)] ?? NULL;
        if (is_numeric($raw)) {
          return (int) $raw;
        }
        if (is_array($raw)) {
          $candidate = $raw['score'] ?? $raw['value'] ?? $raw['total'] ?? NULL;
          if (is_numeric($candidate)) {
            return (int) $candidate;
          }
        }
        return 10;
      };

      $ability_mod = function (int $score): int {
        return (int) floor(((int) $score - 10) / 2);
      };

      $str_mod = $ability_mod($ability_score($canonical_state, 'strength'));
      $dex_mod = $ability_mod($ability_score($canonical_state, 'dexterity'));
      $attack_ability_mod = $is_ranged ? $dex_mod : $str_mod;

      // Resolve weapon proficiency rank from class text + explicit weapon mentions.
      $rank = 'untrained';
      if (is_array($canonical_state)) {
        $class_value = $canonical_state['class']
          ?? $canonical_state['basicInfo']['class']
          ?? $canonical_state['basic_info']['class']
          ?? '';
        if (is_array($class_value)) {
          $class_value = $class_value['id'] ?? $class_value['machine_name'] ?? $class_value['name'] ?? '';
        }
        $class_id = strtolower(trim((string) $class_value));
        $class_data = CharacterRulesCatalog::CLASSES[$class_id] ?? [];
        $weapons_text = strtolower(trim((string) ($class_data['weapons'] ?? '')));

        $category = strtolower(trim((string) ($weapon_stats['category'] ?? '')));

        $has = fn(string $needle) => $needle !== '' && str_contains($weapons_text, $needle);
        $rank_for_category = function (string $cat) use ($has): string {
          if ($cat === 'simple') {
            if ($has('expert in simple and martial')) return 'expert';
            if ($has('master in simple and martial')) return 'master';
            if ($has('legendary in simple and martial')) return 'legendary';
            if ($has('expert in simple weapons') || $has('expert in simple')) return 'expert';
            if ($has('trained in simple weapons') || $has('trained in simple')) return 'trained';
          }
          if ($cat === 'martial') {
            if ($has('expert in simple and martial')) return 'expert';
            if ($has('master in simple and martial')) return 'master';
            if ($has('legendary in simple and martial')) return 'legendary';
            if ($has('expert in martial weapons') || $has('expert in martial')) return 'expert';
            if ($has('trained in martial weapons') || $has('trained in martial')) return 'trained';
          }
          if ($cat === 'advanced') {
            if ($has('expert in advanced weapons') || $has('expert in advanced')) return 'expert';
            if ($has('trained in advanced weapons') || $has('trained in advanced')) return 'trained';
          }
          return 'untrained';
        };

        if (in_array($category, ['simple', 'martial', 'advanced'], TRUE)) {
          $rank = $rank_for_category($category);
        }

        // Classes like Wizard/Rogue list specific weapons instead of categories.
        if ($rank === 'untrained') {
          $weapon_needle = strtolower($weapon_id);
          $name_needle = strtolower($weapon_name);
          if (($weapon_needle !== '' && str_contains($weapons_text, $weapon_needle))
            || ($name_needle !== '' && str_contains($weapons_text, $name_needle))) {
            $rank = 'trained';
          }
        }
      }

      $rank_bonus = match (strtolower($rank)) {
        'trained' => 2,
        'expert' => 4,
        'master' => 6,
        'legendary' => 8,
        default => 0,
      };

      $attack_bonus = $attack_ability_mod + $rank_bonus + max(0, $level);

      $damage_dice = (string) ($weapon_stats['damage_dice'] ?? '1d4');
      $damage_type = (string) ($weapon_stats['damage_type'] ?? 'physical');

      // PF2e: melee and thrown weapons add STR modifier to damage.
      $damage_mod = (!$is_ranged || $is_thrown) ? $str_mod : 0;
      if ($damage_mod !== 0) {
        $sign = $damage_mod > 0 ? '+' : '';
        $damage_dice .= $sign . (string) $damage_mod;
      }

      $is_agile = FALSE;
      foreach ($traits as $trait) {
        if (strtolower(trim((string) $trait)) === 'agile') {
          $is_agile = TRUE;
          break;
        }
      }

      return [
        'weapon_id' => $weapon_id,
        'weapon_name' => $weapon_name,
        'attack_bonus' => $attack_bonus,
        'damage_dice' => $damage_dice,
        'damage_type' => $damage_type,
        'is_agile' => $is_agile,
      ];
    }

    // Legacy fallback: accept a fully specified weapon object.
    if (!empty($weapon_input)) {
      $weapon = $weapon_input + [
        'attack_bonus' => (int) ($params['attack_bonus'] ?? 0),
        'damage_dice' => (string) ($params['damage_dice'] ?? '1d8'),
        'damage_type' => (string) ($params['damage_type'] ?? 'physical'),
        'is_agile' => !empty($params['is_agile']),
      ];
      return $weapon;
    }

    return ['error' => 'Strike requires params.weapon.weapon_id (preferred) or a fully specified params.weapon object.'];
  }

  /**
   * Processes a strike action via the existing combat system.
   */
  protected function processStrike(int $encounter_id, string $actor_id, string $target_id, array $params, array &$game_state, array $dungeon_data = [], ?int $campaign_id = NULL): array {
    return $this->actionResolverRegistry->resolve(
      'strike',
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  /**
   * Find a combat participant by encounter entity_id.
   */
  protected function findEncounterParticipantByEntityId(array $encounter, string $entity_id): ?array {
    return $this->canonicalProjectionService->findEncounterParticipantByEntityId($encounter, $entity_id);
  }

  /**
   * Persist active-turn action economy to combat_participants.
   */
  protected function syncEncounterParticipantTurnResources(?int $encounter_id, array $game_state): void {
    if (!$encounter_id || !is_array($game_state['turn'] ?? NULL)) {
      return;
    }

    $entity_id = trim((string) ($game_state['turn']['entity'] ?? ''));
    if ($entity_id === '') {
      return;
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if (!$encounter) {
      return;
    }

    $participant = $this->findEncounterParticipantByEntityId($encounter, $entity_id);
    $participant_id = (int) ($participant['id'] ?? 0);
    if ($participant_id <= 0) {
      return;
    }

    $fields = [
      'actions_remaining' => max(0, (int) ($game_state['turn']['actions_remaining'] ?? 0)),
      'attacks_this_turn' => max(0, (int) ($game_state['turn']['attacks_this_turn'] ?? 0)),
      'reaction_available' => !empty($game_state['turn']['reaction_available']) ? 1 : 0,
    ];

    try {
      $this->encounterStore->updateParticipant($participant_id, $fields);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Encounter participant action sync failed: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Load canonical round/turn state from the encounter store.
   */
  protected function loadCanonicalTurnState(int $encounter_id): ?array {
    return $this->canonicalProjectionService->loadCanonicalTurnState($encounter_id);
  }

  /**
   * Project canonical encounter state into game_state.
   */
  protected function syncGameStateWithCanonicalTurn(array &$game_state, array $canonical_turn): void {
    $this->canonicalProjectionService->syncGameStateWithCanonicalTurn($game_state, $canonical_turn);
  }

  /**
   * Applies NPC attitude adjustments to social check DCs when available.
   */
  protected function applyNpcAttitudeToSocialDc(int $base_dc, array $params, ?string $target_id, int $campaign_id): array {
    $attitude = $this->resolveNpcAttitude($params, $target_id, $campaign_id);
    if ($attitude === NULL) {
      return [
        'dc' => $base_dc,
        'base_dc' => $base_dc,
        'delta' => 0,
        'attitude' => NULL,
      ];
    }

    $dc_adjustments = new DcAdjustmentService();
    $delta = $dc_adjustments->attitudeDelta($attitude);

    return [
      'dc' => $dc_adjustments->adjustDcForNpcAttitude($base_dc, $attitude),
      'base_dc' => $base_dc,
      'delta' => $delta,
      'attitude' => $attitude,
    ];
  }

  /**
   * Resolves a normalized NPC attitude from explicit params or psychology data.
   */
  protected function resolveNpcAttitude(array $params, ?string $target_id, int $campaign_id): ?string {
    foreach (['npc_attitude', 'target_attitude', 'attitude'] as $key) {
      $normalized = $this->normalizeNpcAttitude($params[$key] ?? NULL);
      if ($normalized !== NULL) {
        return $normalized;
      }
    }

    $npc_target_id = $target_id ?: ($params['target_id'] ?? NULL);
    if (!$npc_target_id) {
      return NULL;
    }

    $actor_disposition = $this->resolveActorDispositionService();
    if ($actor_disposition instanceof ActorDispositionService) {
      $summary = $actor_disposition->getDispositionSummary($campaign_id, (string) $npc_target_id);
      $normalized = $this->normalizeNpcAttitude($summary['current_attitude'] ?? NULL);
      if ($normalized !== NULL) {
        return $normalized;
      }
    }

    try {
      $profile = $this->psychologyService->loadProfile($campaign_id, (string) $npc_target_id);
    }
    catch (\Throwable $e) {
      return NULL;
    }

    foreach (['current_attitude', 'attitude', 'initial_attitude'] as $key) {
      $normalized = $this->normalizeNpcAttitude($profile[$key] ?? NULL);
      if ($normalized !== NULL) {
        return $normalized;
      }
    }

    return NULL;
  }

  /**
   * Normalizes a candidate NPC attitude value.
   */
  protected function normalizeNpcAttitude(mixed $attitude): ?string {
    if (!is_string($attitude)) {
      return NULL;
    }

    $normalized = strtolower(trim($attitude));
    return isset(DcAdjustmentService::ATTITUDE_ADJUSTMENT[$normalized]) ? $normalized : NULL;
  }

  /**
   * REQ 2227/2231: Find a held shield in entity_ref equipment.
   *
   * Checks entity_ref['equipment']['held'] for any item with type 'shield'.
   * Returns the first found shield array, or NULL if none.
   */
  protected function findHeldShield(array $entity_data): ?array {
    $held = $entity_data['equipment']['held'] ?? [];
    foreach ($held as $item) {
      if (is_array($item) && ($item['type'] ?? '') === 'shield') {
        return $item;
      }
    }
    // Also check legacy flat shield slot.
    if (!empty($entity_data['shield']) && ($entity_data['shield']['type'] ?? '') === 'shield') {
      return $entity_data['shield'];
    }
    return NULL;
  }

  /**
   * REQ 2231: Write an updated shield back into entity_data['equipment']['held'].
   */
  protected function updateHeldShield(array $entity_data, array $updated_shield): array {
    $held = $entity_data['equipment']['held'] ?? [];
    foreach ($held as $key => $item) {
      if (is_array($item) && ($item['type'] ?? '') === 'shield') {
        $entity_data['equipment']['held'][$key] = $updated_shield;
        return $entity_data;
      }
    }
    // Legacy flat shield slot.
    if (isset($entity_data['shield'])) {
      $entity_data['shield'] = $updated_shield;
    }
    return $entity_data;
  }

  /**
   * Processes a stride action (movement during encounter, costs 1 action).
   *
   * REQ 2233-2236: Validates movement type and speed.
   * REQ 2237: Tracks diagonal count for 1-2-1-2 diagonal rule.
   * REQ 2247: is_forced flag skips speed validation (forced movement).
   * REQ 2249-2250: Difficult and greater difficult terrain cost applied.
   */
  protected function processStride(int $encounter_id, string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->encounterActionExecutor->processStride(
      $encounter_id,
      $actor_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  /**
   * Processes a spell cast during encounter.
   */
  protected function processCastSpell(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->actionResolverRegistry->resolve(
      'cast_spell',
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  /**
   * Processes an interact action during encounter (1 action).
   */
  protected function processInteract(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->encounterActionExecutor->processInteract(
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  /**
   * Apply a deterministic interact quest touchpoint when a target is present.
   */
  protected function applyInteractQuestTouchpoint(
    int $campaign_id,
    ?int $character_id,
    ?string $target_id,
    array $game_state,
    array $dungeon_data
  ): ?array {
    if (
      $campaign_id <= 0
      || !$character_id
      || $character_id <= 0
      || !is_string($target_id)
      || trim($target_id) === ''
    ) {
      return NULL;
    }

    /** @var \Drupal\dungeoncrawler_content\Service\QuestTouchpointService $quest_touchpoint_service */
    $quest_touchpoint_service = \Drupal::service('dungeoncrawler_content.quest_touchpoint');
    $room_id = (string) ($dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? ''));
    $payload = [
      'character_id' => $character_id,
      'occurred_at' => time(),
      'touchpoint' => [
        'objective_type' => 'interact',
        'entity_ref' => $target_id,
        'npc_ref' => $target_id,
        'room_id' => $room_id,
        'confidence' => 'high',
        'matching_mode' => 'direct_npc_dialogue',
      ],
    ];

    return $quest_touchpoint_service->ingestEvent($campaign_id, $payload);
  }
  /**
   * Concludes the encounter (if not already ended) and emits a chat
   * narration + action-log event describing the final party/enemy
   * status so a full party wipe or full enemy defeat is always clearly
   * recorded, not just silently transitioned.
   *
   * @return array
   *   Event list to merge into the caller's npc_events (empty if the
   *   encounter was already concluded or there's no encounter to end).
   */
  protected function concludeEncounterWithOutcomeLog(?int $encounter_id, array &$game_state, array &$dungeon_data, int $campaign_id, mixed &$narration): array {
    if (!$encounter_id) {
      return [];
    }
    $encounter_row = $this->encounterStore->loadEncounter((int) $encounter_id);
    if (!is_array($encounter_row) || (string) ($encounter_row['status'] ?? '') !== 'active') {
      return [];
    }

    $outcome = $this->resolveEncounterOutcome($game_state);
    $this->combatEngine->endEncounter((int) $encounter_id, $outcome, 'all_but_one_team_defeated');

    $summary = $this->buildEncounterOutcomeSummary($game_state, $outcome);
    $narration = $summary['narration'];
    $room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);

    $reward_events = [];
    if ($outcome === 'victory') {
      $rewards = $this->awardEncounterVictoryRewards($game_state, $campaign_id);
      if ($rewards !== NULL) {
        $narration .= ' ' . $rewards['narration'];
        $reward_events[] = GameEventLogger::buildEvent('encounter_rewards', 'encounter', NULL, [
          'xp_awarded' => $rewards['xp_awarded'],
          'currency_gp_awarded' => $rewards['currency_gp_awarded'],
          'recipients' => $rewards['recipients'],
        ], $rewards['narration']);
      }
    }

    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'encounter_end',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => '',
      'content' => $narration,
      'visibility' => 'public',
    ], $room_id);

    return array_merge([
      GameEventLogger::buildEvent('encounter_end', 'encounter', NULL, [
        'outcome' => $outcome,
        'participants' => $summary['participants'],
      ], $narration),
    ], $reward_events);
  }

  /**
   * Awards PF2e-standard encounter-victory XP and currency to every
   * surviving player-team character once an encounter concludes in
   * victory. Familiars/companions do not receive XP independently in
   * core rules (they scale with their master's level), so only 'player'
   * team combatants are rewarded.
   *
   * XP per defeated enemy uses the same delta-based table already used
   * to budget encounter difficulty ({@see CharacterManager::computeCreatureXp()}).
   * Currency uses the same per-encounter GP budget already used for
   * wealth-by-level ({@see TreasureByLevelService::getLevelBudget()}),
   * split evenly across the surviving player characters.
   *
   * @return array{narration:string,xp_awarded:int,currency_gp_awarded:float,recipients:array}|null
   *   NULL if there's nothing to award (no defeated enemies or no
   *   resolvable player characters).
   */
  protected function awardEncounterVictoryRewards(array $game_state, int $campaign_id): ?array {
    $defeated_enemy_ids = [];
    $player_ids = [];
    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $entity_id = trim((string) ($participant['entity_id'] ?? ''));
      if ($entity_id === '') {
        continue;
      }
      $team = $this->normalizeCombatTeam((string) ($participant['team'] ?? ''));
      if ($team === 'enemy' && !empty($participant['is_defeated'])) {
        $defeated_enemy_ids[] = $entity_id;
      }
      elseif ($team === 'player') {
        $player_ids[] = $entity_id;
      }
    }
    if ($defeated_enemy_ids === [] || $player_ids === []) {
      return NULL;
    }

    $rows = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'instance_id', 'level', 'experience_points', 'character_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', array_merge($defeated_enemy_ids, $player_ids), 'IN')
      ->execute()
      ->fetchAllAssoc('instance_id', \PDO::FETCH_ASSOC);

    $player_rows = array_intersect_key($rows, array_flip($player_ids));
    if ($player_rows === []) {
      return NULL;
    }

    $player_levels = array_map(static fn(array $row): int => max(1, (int) ($row['level'] ?? 1)), $player_rows);
    $party_level = (int) round(array_sum($player_levels) / count($player_levels));
    $party_size = count($player_rows);

    $total_xp = 0;
    foreach ($defeated_enemy_ids as $enemy_id) {
      $enemy_level = max(1, (int) ($rows[$enemy_id]['level'] ?? 1));
      $total_xp += CharacterManager::computeCreatureXp($enemy_level, $party_level) ?? 0;
    }

    $treasure_service = \Drupal::service('dungeoncrawler_content.treasure_by_level');
    $budget = $treasure_service->getLevelBudget($party_level, $party_size);
    $currency_gp_total = (float) $budget['per_encounter_gp'];
    $currency_gp_share = $party_size > 0 ? round($currency_gp_total / $party_size, 2) : 0.0;

    $character_manager = \Drupal::service('dungeoncrawler_content.character_manager');
    $recipients = [];
    foreach ($player_rows as $instance_id => $row) {
      $character_data = json_decode((string) ($row['character_data'] ?? '{}'), TRUE);
      if (!is_array($character_data)) {
        $character_data = [];
      }
      $new_xp = (int) ($row['experience_points'] ?? 0) + $total_xp;
      $character_data['basicInfo']['experiencePoints'] = $new_xp;
      $current_gp = (float) ($character_data['currency']['gp'] ?? 0);
      $character_data['currency']['gp'] = $current_gp + $currency_gp_share;

      $character_manager->updateCharacter((int) $row['id'], [
        'experience_points' => $new_xp,
        'character_data' => $character_data,
      ]);
      $recipients[] = [
        'entity_id' => $instance_id,
        'xp_awarded' => $total_xp,
        'currency_gp_awarded' => $currency_gp_share,
      ];
    }

    $narration = $total_xp > 0 || $currency_gp_total > 0
      ? sprintf(
        'The party gains %d XP and recovers %s gp (%s gp each).',
        $total_xp,
        rtrim(rtrim(number_format($currency_gp_total, 2, '.', ''), '0'), '.'),
        rtrim(rtrim(number_format($currency_gp_share, 2, '.', ''), '0'), '.')
      )
      : '';

    return [
      'narration' => $narration,
      'xp_awarded' => $total_xp,
      'currency_gp_awarded' => $currency_gp_total,
      'recipients' => $recipients,
    ];
  }

  protected function processEndTurn(?int $encounter_id, ?string $actor_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // processEndTurn() is the single universal entry point for turn-advance
    // logic — called from the auto-end-turn tail of processIntentCore()
    // (after ANY action type: strike, cast_spell, feat, hazard attack,
    // disarm/trip/shove, etc.), from routeEndTurnIntentExecution() for an
    // explicit end-turn, and recursively from itself after NPC autoplay.
    // Refresh the in-memory initiative order from the DB here, once, rather
    // than duplicating a refresh inside every individual action handler —
    // that was the bug: only processStrike() refreshed it, so a spell (or
    // any other non-strike action) that defeated the last combatant on a
    // team left is_defeated stale in game_state, and this function's
    // turn-advance loop kept selecting the (actually dead) combatant as
    // "next", freezing the encounter.
    // NOTE: loadEncounter() always re-sorts combat_participants by the DB
    // 'initiative' column (descending), and combat_participants has no
    // column for the in-memory-only 'delayed'/'delayed_actions_remaining'/
    // 'delay_until_actor_id' markers that buildDelayedInitiativePlan() sets
    // on $game_state['initiative_order'] entries. A wholesale replacement
    // here would silently discard BOTH the delay reorder AND the delayed
    // flag itself on every single call (this function runs after every
    // action), so a delayed actor's turn always reverted straight back to
    // its original initiative-order slot instead of resuming after its
    // chosen target. Merge fresh DB fields (hp/is_defeated/status/etc.)
    // into the existing in-memory order/positions instead, so any pending
    // delay reorder and its flags survive the refresh.
    if ($encounter_id) {
      $encounter_for_turn_refresh = $this->encounterStore->loadEncounter((int) $encounter_id);
      if ($encounter_for_turn_refresh && is_array($encounter_for_turn_refresh['participants'] ?? NULL)) {
        $game_state['initiative_order'] = $this->mergeRefreshedParticipantsPreservingOrder(
          $game_state['initiative_order'] ?? [],
          $encounter_for_turn_refresh['participants']
        );
        // Project the same fresh HP/is_defeated/conditions onto the map's
        // dungeon-entity runtime state so the map tab's unconscious/dead
        // indicator and hover tooltip stay in sync after every action, not
        // only after the narrow shove/forced-movement path that previously
        // called this projection directly.
        $this->applyEncounterParticipantsToDungeonData($encounter_for_turn_refresh['participants'], $dungeon_data);
      }
    }

    $initiative_order = $game_state['initiative_order'] ?? [];
    if (empty($initiative_order)) {
      return [
        'turn_advanced' => FALSE,
        'next_entity' => NULL,
        'next_team' => NULL,
        'round' => $game_state['round'] ?? NULL,
        'new_round' => NULL,
        'round_advances' => 0,
        'npc_events' => [],
        'mutations' => [],
        'actions_remaining_before_end' => $game_state['turn']['actions_remaining'] ?? NULL,
      ];
    }
    $current_index = $game_state['turn']['index'] ?? 0;
    $actions_remaining_before_end = $game_state['turn']['actions_remaining'] ?? NULL;
    $npc_events = [];
    $npc_mutations = [];
    $new_round = NULL;
    $round_advances = 0;
    $narration = NULL;
    $phase_transition = NULL;

    // The action that just completed (a player strike/spell, or an NPC's
    // autoplayed attack from a prior recursive call) may already have
    // defeated the last combatant on an opposing team. Check for that
    // before doing any further turn-advance work so a party wipe or full
    // enemy defeat is concluded and logged immediately, rather than
    // waiting for (or silently skipping) a future turn transition.
    if ($this->isEncounterOver($game_state)) {
      $conclusion_events = $this->concludeEncounterWithOutcomeLog($encounter_id, $game_state, $dungeon_data, $campaign_id, $narration);
      return [
        'turn_advanced' => FALSE,
        'next_entity' => NULL,
        'next_team' => NULL,
        'round' => $game_state['round'] ?? NULL,
        'new_round' => NULL,
        'round_advances' => 0,
        'npc_events' => $conclusion_events,
        'mutations' => [],
        'actions_remaining_before_end' => $actions_remaining_before_end,
        'narration' => $narration,
        // Concluding the encounter must also move the campaign out of
        // 'encounter' phase — otherwise game_state['phase'] stays stuck
        // at 'encounter' forever (verified against every historically
        // "ended" encounter in the DB), which in turn keeps any
        // phase==='encounter'-gated logic (e.g. EncounterTurnGuard for
        // chat) permanently active even though the fight is over.
        'phase_transition' => $conclusion_events !== []
          ? ['from' => 'encounter', 'to' => 'exploration', 'reason' => 'Encounter concluded.']
          : NULL,
      ];
    }

    // Tick end-of-turn conditions for the current combatant.
    if ($encounter_id && $actor_id) {
      try {
        $encounter_row = $this->encounterStore->loadEncounter((int) $encounter_id);
        $participant = $encounter_row ? $this->findEncounterParticipantByEntityId($encounter_row, $actor_id) : NULL;
        $participant_id = (int) ($participant['id'] ?? 0);
        if ($participant_id > 0) {
          $this->conditionManager->tickConditions($participant_id, (int) $encounter_id);
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Condition tick failed: @error', ['@error' => $e->getMessage()]);
      }
    }

    // REQ 2222: Airborne entity that did NOT use a Fly action this turn begins falling.
    if ($actor_id) {
      try {
        $enc_fly_check = $encounter_id ? $this->encounterStore->loadEncounter($encounter_id) : NULL;
        $ptcp_fly_check = $enc_fly_check ? $this->findEncounterParticipantByEntityId($enc_fly_check, $actor_id) : NULL;
        if ($ptcp_fly_check) {
          $entity_fly = !empty($ptcp_fly_check['entity_ref']) ? json_decode($ptcp_fly_check['entity_ref'], TRUE) : [];
          if (!empty($entity_fly['airborne']) && empty($entity_fly['fly_used_this_turn'])) {
            // Trigger fall — apply fall damage (default 10 ft if elevation not tracked).
            $fall_feet = (int) ($entity_fly['elevation_ft'] ?? 10);
            if ($this->hpManager && $fall_feet > 0) {
              $this->hpManager->applyFallDamage((int) $ptcp_fly_check['id'], $fall_feet, $encounter_id);
            }
            $entity_fly['airborne'] = FALSE;
          }
          // Clear fly_used_this_turn for next turn.
          $entity_fly['fly_used_this_turn'] = FALSE;
          // Clear shield_raised (expires at start of next turn, cleared here).
          $entity_fly['shield_raised'] = FALSE;
          // Clear avert_gaze_active (expires at start of next turn).
          $entity_fly['avert_gaze_active'] = FALSE;
          $this->encounterStore->updateParticipant((int) $ptcp_fly_check['id'], ['entity_ref' => json_encode($entity_fly)]);
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('End-of-turn entity state clear failed: @error', ['@error' => $e->getMessage()]);
      }
    }

    // REQ 1648: Submerged character who did NOT Swim this turn sinks 10 ft at turn end.
    // Not applied on the turn they first entered water (swim_entered_water_this_turn flag).
    if ($actor_id) {
      try {
        $swim_actions = $game_state['turn']['swim_actions'][$actor_id] ?? 0;
        $entered_this_turn = !empty($game_state['turn']['entered_water'][$actor_id]);
        $submerged = !empty($game_state['entities'][$actor_id]['submerged']);
        if ($submerged && !$entered_this_turn && $swim_actions === 0) {
          // Sink 10 ft — record in game state; environment effects handled by GM/AI.
          if (!isset($game_state['entities'][$actor_id])) {
            $game_state['entities'][$actor_id] = [];
          }
          $game_state['entities'][$actor_id]['depth_ft'] = ((int) ($game_state['entities'][$actor_id]['depth_ft'] ?? 0)) + 10;
        }
        // Clear per-turn water entry flag.
        if (isset($game_state['turn']['entered_water'][$actor_id])) {
          unset($game_state['turn']['entered_water'][$actor_id]);
        }
        // Clear per-turn swim action counter.
        if (isset($game_state['turn']['swim_actions'][$actor_id])) {
          unset($game_state['turn']['swim_actions'][$actor_id]);
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Swim end-of-turn check failed: @error', ['@error' => $e->getMessage()]);
      }
    }

    // Advance to next non-defeated combatant.
    $next_index = $current_index + 1;
    $wrapped = FALSE;

    // dc-cr-spells-ch07: Decrement round-based spell durations at start of caster's turn.
    // Spells stored in game_state['spells']['durations'][$actor_id][$spell_id]['rounds_remaining'].
    if ($actor_id && isset($game_state['spells']['durations'][$actor_id])) {
      foreach ($game_state['spells']['durations'][$actor_id] as $dur_spell_id => &$dur_data) {
        if (isset($dur_data['rounds_remaining'])) {
          $dur_data['rounds_remaining'] = (int) $dur_data['rounds_remaining'] - 1;
          if ($dur_data['rounds_remaining'] <= 0) {
            unset($game_state['spells']['durations'][$actor_id][$dur_spell_id]);
            // Also remove from sustained list if present.
            unset($game_state['spells']['sustained'][$actor_id][$dur_spell_id]);
          }
        }
      }
      unset($dur_data);
    }

    while (TRUE) {
      if ($next_index >= count($initiative_order)) {
        // Wrap to next round.
        $next_index = 0;
        $game_state['round'] = ($game_state['round'] ?? 1) + 1;
        $new_round = $game_state['round'];
        $round_advances++;
        $wrapped = TRUE;
      }

      // Safety: don't loop forever.
      if ($wrapped && $next_index > $current_index) {
        break;
      }

      $next_combatant = $initiative_order[$next_index] ?? NULL;
      if ($next_combatant && empty($next_combatant['is_defeated'])) {
        break;
      }
      $next_index++;
    }

    $next_entity = $initiative_order[$next_index]['entity_id'] ?? NULL;
    $next_team = $initiative_order[$next_index]['team'] ?? 'enemy';
    if (!$next_entity) {
      return [
        'turn_advanced' => FALSE,
        'next_entity' => NULL,
        'next_team' => NULL,
        'round' => $game_state['round'],
        'new_round' => $new_round,
        'round_advances' => $round_advances,
        'npc_events' => $npc_events,
        'mutations' => $npc_mutations,
        'actions_remaining_before_end' => $actions_remaining_before_end,
      ];
    }

    $restored_delayed_actions = NULL;
    if (!empty($initiative_order[$next_index]['delayed'])) {
      $restored_delayed_actions = max(0, (int) ($initiative_order[$next_index]['delayed_actions_remaining'] ?? 0));
      $initiative_order[$next_index]['delayed'] = FALSE;
      unset($initiative_order[$next_index]['delayed_actions_remaining'], $initiative_order[$next_index]['delay_until_actor_id']);
      $game_state['initiative_order'] = $initiative_order;
    }

    // Update game_state turn.
    $game_state['turn'] = [
      'entity' => $next_entity,
      'index' => $next_index,
      'actions_remaining' => $restored_delayed_actions !== NULL ? $restored_delayed_actions : 3,
      'attacks_this_turn' => 0,
      'reaction_available' => TRUE,
      'delayed' => FALSE,
    ];

    if ($encounter_id) {
      try {
        $this->encounterStore->updateEncounter($encounter_id, [
          'turn_index' => $next_index,
          'current_round' => $game_state['round'],
        ]);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Encounter store update failed: @error', ['@error' => $e->getMessage()]);
      }
    }

    if ($new_round) {
      $npc_events = array_merge($npc_events, $this->buildRoundStartEvents((int) $new_round, $game_state, $dungeon_data, $campaign_id));
    }
    if ($next_entity) {
      $npc_events = array_merge($npc_events, $this->buildTurnStartEvents((string) $next_entity, $game_state, $dungeon_data, $campaign_id));
      $npc_events = array_merge($npc_events, $this->buildTurnStartSearchEvents((string) $next_entity, $game_state, $dungeon_data, $campaign_id));
    }

    if ($next_team !== 'player' && $this->isRoomSceneMode($game_state)) {
      $room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ($dungeon_data['active_room_id'] ?? '')));
      if ($room_id !== '') {
        $bootstrap_context = $this->resolveBootstrapEncounterInitialization($room_id, $game_state, $dungeon_data, $campaign_id, (string) $next_entity);
        if (!empty($bootstrap_context['combat_context']['should_trigger'])) {
          $exit_result = $this->onExit($game_state, $dungeon_data, $campaign_id);
          $npc_events = array_merge($npc_events, is_array($exit_result['events'] ?? NULL) ? $exit_result['events'] : []);
          $enter_result = $this->onEnter($bootstrap_context['combat_context'], $game_state, $dungeon_data, $campaign_id);
          $npc_events = array_merge($npc_events, is_array($enter_result['events'] ?? NULL) ? $enter_result['events'] : []);
          $current_turn_entity = (string) ($game_state['turn']['entity'] ?? '');
          return [
            'turn_advanced' => TRUE,
            'next_entity' => $current_turn_entity !== '' ? $current_turn_entity : $next_entity,
            'next_team' => $current_turn_entity !== '' ? $this->resolveInitiativeParticipantTeam($current_turn_entity, $game_state) : $next_team,
            'round' => $game_state['round'],
            'new_round' => $new_round,
            'round_advances' => $round_advances,
            'npc_events' => $npc_events,
            'mutations' => $npc_mutations,
            'actions_remaining_before_end' => $actions_remaining_before_end,
          ];
        }
      }
    }

    // If next combatant is non-player, auto-play hostile turns and pass room
    // dialogue turns for neutral/friendly actors while in room-scene mode.
    if ($next_team !== 'player') {
      $normalized_next_team = $this->normalizeCombatTeam((string) $next_team);
      $should_autoplay_in_room_scene = $this->isRoomSceneMode($game_state) && $normalized_next_team === 'enemy';
      $npc_result = ($encounter_id && (!$this->isRoomSceneMode($game_state) || $should_autoplay_in_room_scene))
        ? $this->autoPlayNpcTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id)
        : $this->passRoomActorTurn((string) $next_entity, $game_state, $dungeon_data, $campaign_id);
      if ($normalized_next_team === 'enemy') {
        $npc_events_for_turn = is_array($npc_result['events'] ?? NULL) ? $npc_result['events'] : [];
        $has_non_pass_action = FALSE;
        foreach ($npc_events_for_turn as $npc_event) {
          if (!is_array($npc_event)) {
            continue;
          }
          $event_type = strtolower(trim((string) ($npc_event['type'] ?? '')));
          if (!in_array($event_type, ['npc_choose_not_to_act', 'choose_not_to_act', 'auto_end_turn', 'end_turn'], TRUE)) {
            $has_non_pass_action = TRUE;
            break;
          }
        }
        if (!$has_non_pass_action) {
          $this->logger->warning('Enemy autoplay produced pass-only turn. actor={actor} mode={mode} encounter_id={encounter_id} round={round} team={team}', [
            'actor' => (string) $next_entity,
            'mode' => (string) ($game_state['encounter_context']['mode'] ?? ''),
            'encounter_id' => (int) ($game_state['encounter_id'] ?? 0),
            'round' => (int) ($game_state['round'] ?? 0),
            'team' => $normalized_next_team,
          ]);
        }
      }
      $npc_events = array_merge($npc_events, $npc_result['events'] ?? []);
      if (is_array($npc_result['mutations'] ?? NULL) && $npc_result['mutations'] !== []) {
        $npc_mutations = array_merge($npc_mutations, $npc_result['mutations']);
      }

      // After NPC turn, recursively advance. The recursive call's own
      // isEncounterOver() guard at the top of the function will conclude
      // and log the encounter outcome the moment one side is fully wiped,
      // rather than needing a duplicate check here.
      $further = $this->processEndTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id);
      $npc_events = array_merge($npc_events, $further['npc_events'] ?? []);
      if (is_array($further['mutations'] ?? NULL) && $further['mutations'] !== []) {
        $npc_mutations = array_merge($npc_mutations, $further['mutations']);
      }
      if (!$new_round && !empty($further['new_round'])) {
        $new_round = $further['new_round'];
      }
      $round_advances += (int) ($further['round_advances'] ?? 0);
      if ($narration === NULL && !empty($further['narration'])) {
        $narration = $further['narration'];
      }
      if ($phase_transition === NULL && !empty($further['phase_transition'])) {
        $phase_transition = $further['phase_transition'];
      }
    }

    return [
      'turn_advanced' => TRUE,
      'next_entity' => $next_entity,
      'next_team' => $next_team,
      'round' => $game_state['round'],
      'new_round' => $new_round,
      'round_advances' => $round_advances,
      'npc_events' => $npc_events,
      'mutations' => $npc_mutations,
      'actions_remaining_before_end' => $actions_remaining_before_end,
      'narration' => $narration,
      'phase_transition' => $phase_transition,
    ];
  }

  /**
   * Refresh combatant fields from freshly loaded DB rows without discarding
   * the current in-memory ordering or any in-memory-only annotations (e.g.
   * the delay-turn 'delayed'/'delayed_actions_remaining'/
   * 'delay_until_actor_id' markers, which have no backing DB column).
   *
   * combat_participants has no persisted "turn order" concept beyond the
   * 'initiative' column, so loadEncounter() always re-sorts by initiative.
   * A delayed actor's reordering therefore only exists in
   * $game_state['initiative_order'] — this merge keeps that array's order
   * and annotations intact while still picking up authoritative HP/
   * is_defeated/status/position/conditions updates from the DB.
   *
   * @param array $current_order
   *   The in-memory initiative order (authoritative for ordering/flags).
   * @param array $refreshed_participants
   *   Freshly loaded DB participant rows (authoritative for live combat state).
   *
   * @return array<int, array>
   */
  protected function mergeRefreshedParticipantsPreservingOrder(array $current_order, array $refreshed_participants): array {
    if ($current_order === []) {
      return array_values($refreshed_participants);
    }

    $refreshed_by_entity_id = [];
    foreach ($refreshed_participants as $refreshed_row) {
      $entity_id = trim((string) ($refreshed_row['entity_id'] ?? ''));
      if ($entity_id !== '') {
        $refreshed_by_entity_id[$entity_id] = $refreshed_row;
      }
    }

    $merged = [];
    $seen_entity_ids = [];
    foreach ($current_order as $existing_entry) {
      if (!is_array($existing_entry)) {
        continue;
      }
      $entity_id = trim((string) ($existing_entry['entity_id'] ?? ''));
      if ($entity_id !== '' && isset($refreshed_by_entity_id[$entity_id])) {
        // DB fields win for live combat state; in-memory-only annotations
        // (delayed/delayed_actions_remaining/delay_until_actor_id, or any
        // other key the DB row doesn't carry) are preserved from the
        // existing entry since array_merge() only overwrites matching keys.
        $merged[] = array_merge($existing_entry, $refreshed_by_entity_id[$entity_id]);
        $seen_entity_ids[$entity_id] = TRUE;
      }
      else {
        $merged[] = $existing_entry;
      }
    }

    // Append any DB participant not already represented in-memory (e.g. a
    // reinforcement added mid-encounter after the in-memory order was built).
    foreach ($refreshed_participants as $refreshed_row) {
      $entity_id = trim((string) ($refreshed_row['entity_id'] ?? ''));
      if ($entity_id !== '' && !isset($seen_entity_ids[$entity_id])) {
        $merged[] = $refreshed_row;
      }
    }

    return $merged;
  }

  /**
   * Reorder initiative for a delayed actor and determine the next turn anchor.
   */
  protected function buildDelayedInitiativePlan(
    array $initiative_order,
    string $actor_id,
    int $current_index,
    int $remaining_actions,
    ?string $delay_after_actor_id = NULL
  ): array {
    $original_order = array_values($initiative_order);
    $actor_index = $this->findInitiativeActorIndex($original_order, $actor_id);
    if ($actor_index === NULL) {
      $actor_index = max(0, $current_index);
    }

    $actor_entry = $original_order[$actor_index] ?? ['entity_id' => $actor_id];
    $actor_entry['delayed'] = TRUE;
    $actor_entry['delayed_actions_remaining'] = max(0, $remaining_actions);
    if ($delay_after_actor_id !== NULL && $delay_after_actor_id !== '') {
      $actor_entry['delay_until_actor_id'] = $delay_after_actor_id;
    }

    array_splice($original_order, $actor_index, 1);

    // Default (no explicit delay-until target): delay only until immediately
    // after the very next actor in turn order, not until the end of the
    // round. After splicing the delayed actor out, the actor who was next
    // shifted down to $actor_index, so inserting at $actor_index + 1 places
    // the delayed actor right behind them -- matching PF2e-style delay
    // semantics of "step aside for one actor, then resume your turn."
    $insert_at = min($actor_index + 1, count($original_order));
    if ($delay_after_actor_id !== NULL && $delay_after_actor_id !== '') {
      $target_original_index = $this->findInitiativeActorIndex($initiative_order, $delay_after_actor_id);
      if ($target_original_index !== NULL && $target_original_index > $actor_index) {
        $target_reordered_index = $this->findInitiativeActorIndex($original_order, $delay_after_actor_id);
        if ($target_reordered_index !== NULL) {
          $insert_at = $target_reordered_index + 1;
        }
      }
    }

    array_splice($original_order, $insert_at, 0, [$actor_entry]);
    $reordered = array_values($original_order);

    $all_delayed = $this->allActiveInitiativeActorsDelayed($reordered);
    if ($all_delayed) {
      return [
        'initiative_order' => $reordered,
        'pre_advance_index' => max(0, count($reordered) - 1),
      ];
    }

    $reduced_without_actor = array_values(array_filter(
      $reordered,
      static fn(array $participant): bool => (string) ($participant['entity_id'] ?? '') !== $actor_id
    ));
    $next_actor = NULL;
    if ($actor_index < count($reduced_without_actor)) {
      $next_actor = (string) ($reduced_without_actor[$actor_index]['entity_id'] ?? '');
    }
    if ($next_actor === '' || $next_actor === NULL) {
      $next_actor = (string) ($reduced_without_actor[0]['entity_id'] ?? '');
    }

    $next_index = $this->findInitiativeActorIndex($reordered, $next_actor);
    if ($next_index === NULL) {
      $next_index = 0;
    }

    $pre_advance_index = $next_index - 1;
    if ($pre_advance_index < 0) {
      $pre_advance_index = -1;
    }
    if ($next_index > 0 && $pre_advance_index < 0) {
      $pre_advance_index = max(0, count($reordered) - 1);
    }

    return [
      'initiative_order' => $reordered,
      'pre_advance_index' => $pre_advance_index,
    ];
  }

  /**
   * Find an actor inside the initiative order.
   */
  protected function findInitiativeActorIndex(array $initiative_order, string $actor_id): ?int {
    foreach ($initiative_order as $index => $participant) {
      if ((string) ($participant['entity_id'] ?? '') === $actor_id) {
        return (int) $index;
      }
    }

    return NULL;
  }

  /**
   * Determine whether every active participant is currently delayed.
   */
  protected function allActiveInitiativeActorsDelayed(array $initiative_order): bool {
    $active_count = 0;
    foreach ($initiative_order as $participant) {
      if (!is_array($participant) || !empty($participant['is_defeated'])) {
        continue;
      }
      $active_count++;
      if (empty($participant['delayed'])) {
        return FALSE;
      }
    }

    return $active_count > 0;
  }

  // =========================================================================
  // NPC Auto-play.
  // =========================================================================
}
