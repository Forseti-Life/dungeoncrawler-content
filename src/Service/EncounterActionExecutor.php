<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Executes encounter actions while preserving handler-level contracts.
 */
class EncounterActionExecutor {

  protected CombatEncounterStore $encounterStore;
  protected CombatEngine $combatEngine;
  protected MagicItemService $magicItemService;
  protected RoomChatService $roomChatService;
  protected CharacterStateService $characterStateService;
  protected SpellCatalogService $spellCatalog;
  protected NumberGenerationService $numberGenerationService;
  protected CombatCalculator $combatCalculator;
  protected CanonicalProjectionService $canonicalProjectionService;
  protected ?MovementResolverService $movementResolver;
  protected LoggerInterface $logger;

  public function __construct(
    CombatEncounterStore $encounter_store,
    CombatEngine $combat_engine,
    MagicItemService $magic_item_service,
    RoomChatService $room_chat_service,
    CharacterStateService $character_state_service,
    SpellCatalogService $spell_catalog,
    NumberGenerationService $number_generation_service,
    CombatCalculator $combat_calculator,
    CanonicalProjectionService $canonical_projection_service,
    LoggerChannelFactoryInterface $logger_factory,
    ?MovementResolverService $movement_resolver = NULL
  ) {
    $this->encounterStore = $encounter_store;
    $this->combatEngine = $combat_engine;
    $this->magicItemService = $magic_item_service;
    $this->roomChatService = $room_chat_service;
    $this->characterStateService = $character_state_service;
    $this->spellCatalog = $spell_catalog;
    $this->numberGenerationService = $number_generation_service;
    $this->combatCalculator = $combat_calculator;
    $this->canonicalProjectionService = $canonical_projection_service;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->movementResolver = $movement_resolver;
  }

  public function processTalk(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $capture_encounter_turn_context,
    callable $resolve_actor_character_id,
    callable $resolve_entity_name,
    callable $prefix_encounter_chat_line,
    callable $build_encounter_chat_prefix
  ): array {
    $turn_ctx = is_array($params['_encounter_turn_ctx'] ?? NULL)
      ? $params['_encounter_turn_ctx']
      : $capture_encounter_turn_context($game_state, $dungeon_data, $actor_id);

    $message = trim((string) ($params['message'] ?? ''));
    $room_id = $dungeon_data['active_room_id'] ?? NULL;
    $character_id = $resolve_actor_character_id($actor_id, $dungeon_data, $params);
    $speaker = $resolve_entity_name($actor_id, $game_state, $dungeon_data);
    $target_name = trim((string) ($target_id ? $resolve_entity_name($target_id, $game_state, $dungeon_data) : ''));

    if (!$room_id) {
      return [
        'talked' => FALSE,
        'error' => 'No active room set.',
        'mutations' => [],
      ];
    }

    if ($message === '' && $target_name === '') {
      return [
        'talked' => FALSE,
        'error' => 'Talk requires a message or target.',
        'mutations' => [],
      ];
    }

    if ($message === '' && $character_id !== NULL) {
      $suggestion = $this->roomChatService->suggestPlayerAutomationMessage(
        $campaign_id,
        $room_id,
        $character_id,
        'room'
      );
      $message = trim((string) ($suggestion['message'] ?? ''));
    }

    if ($target_name !== '' && $message !== '' && stripos($message, $target_name) === FALSE) {
      $message = sprintf('%s, %s', $target_name, $message);
    }

    if ($message === '') {
      return [
        'talked' => FALSE,
        'error' => 'Automation could not produce a room chat message.',
        'mutations' => [],
      ];
    }

    $message = $prefix_encounter_chat_line($turn_ctx, $message);

    $is_encounter_turn = (($game_state['phase'] ?? NULL) === 'encounter');
    $is_direct_npc_dialogue = is_string($target_id) && trim($target_id) !== '';
    $defer_npc_interjections = $is_encounter_turn && !$is_direct_npc_dialogue
      ? TRUE
      : !empty($params['defer_npc_interjections']);
    $suppress_gm = !empty($params['suppress_gm']);

    try {
      $chat_result = $this->roomChatService->postMessage(
        $campaign_id,
        $room_id,
        $speaker,
        $message,
        'player',
        $character_id,
        'room',
        $defer_npc_interjections,
        $suppress_gm,
        NULL,
        [
          'objective_type' => (string) ($params['objective_type'] ?? ''),
          'objective_id' => (string) ($params['objective_id'] ?? ''),
          'entity_ref' => (string) ($target_id ?? ''),
          '_validated_encounter_talk' => TRUE,
          '_encounter_prefix' => $build_encounter_chat_prefix($turn_ctx),
        ]
      );

      if (!empty($chat_result['dungeon_data']) && is_array($chat_result['dungeon_data'])) {
        $dungeon_data = $chat_result['dungeon_data'];
        $game_state = $dungeon_data['game_state'] ?? $game_state;
      }

      $chat_response = [
        'gm_response' => $chat_result['gm_response'] ?? NULL,
        'npc_interjections' => $chat_result['npc_interjections'] ?? [],
        'quest_updates' => $chat_result['quest_updates'] ?? [],
        'state_diff' => $chat_result['state_diff'] ?? [],
        'combat_transition' => $chat_result['combat_transition'] ?? NULL,
        'canonical_actions' => $chat_result['canonical_actions'] ?? [],
      ];
      $this->logger->info('Encounter talk response quest handoff: campaign={campaign_id} character={character_id} room={room_id} quest_update_count={quest_update_count} quest_ids={quest_ids}', [
        'campaign_id' => $campaign_id,
        'character_id' => $character_id,
        'room_id' => $room_id,
        'quest_update_count' => count($chat_response['quest_updates']),
        'quest_ids' => implode(', ', array_map(static function (array $update): string {
          return (string) ($update['quest_id'] ?? $update['quest_key'] ?? $update['quest_name'] ?? 'unknown');
        }, $chat_response['quest_updates'])),
      ]);

      return [
        'talked' => TRUE,
        'message' => $message,
        'chat_message' => $chat_result['message'] ?? NULL,
        'gm_response' => $chat_response['gm_response'],
        'gm_deferred' => !empty($chat_result['gm_deferred']),
        'npc_interjections' => $chat_response['npc_interjections'],
        'quest_updates' => $chat_response['quest_updates'],
        'state_diff' => $chat_response['state_diff'],
        'combat_transition' => $chat_response['combat_transition'],
        'canonical_actions' => $chat_response['canonical_actions'],
        'npc_interjections_deferred' => !empty($chat_result['npc_interjections_deferred']),
        'turn_log_key' => array_key_exists('turn_log_key', $chat_result) ? $chat_result['turn_log_key'] : NULL,
        'turn_logs' => array_values(array_filter($chat_result['turn_logs'] ?? [], 'is_array')),
        'chat_response' => $chat_response,
        'narration' => $chat_result['gm_response']['message'] ?? ($chat_result['gm_response']['text'] ?? NULL),
        'mutations' => $chat_result['mutations'] ?? [],
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Encounter talk failed: @error', ['@error' => $e->getMessage()]);
      $error_message = $e instanceof \InvalidArgumentException
        ? $e->getMessage()
        : 'Chat service error.';

      return [
        'talked' => FALSE,
        'error' => $error_message,
        'mutations' => [],
      ];
    }
  }

  public function processStrike(
    int $encounter_id,
    string $actor_id,
    string $target_id,
    array $params,
    array &$game_state,
    array $dungeon_data,
    ?int $campaign_id,
    callable $resolve_strike_weapon
  ): array {
    try {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      if (!$encounter) {
        return ['error' => 'Encounter not found.'];
      }

      $attacker_participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
      $target_participant = $this->findEncounterParticipantByEntityId($encounter, $target_id);
      if (!$attacker_participant || !$target_participant) {
        return ['error' => 'Attacker or target is not present in the encounter.'];
      }

      $weapon = $resolve_strike_weapon($actor_id, $params, $dungeon_data, $campaign_id);
      if (!empty($weapon['error'])) {
        return ['error' => $weapon['error']];
      }

      if (!empty($params['skip_map'])) {
        $weapon['skip_map'] = TRUE;
      }

      $attack_result = $this->combatEngine->resolveAttack(
        (int) ($attacker_participant['id'] ?? 0),
        (int) ($target_participant['id'] ?? 0),
        $weapon,
        $encounter_id,
        $dungeon_data
      );

      $updated_encounter = $this->encounterStore->loadEncounter($encounter_id) ?: $encounter;
      $game_state['initiative_order'] = $updated_encounter['participants'] ?? ($game_state['initiative_order'] ?? []);
      $updated_target = $this->findEncounterParticipantByEntityId($updated_encounter, $target_id) ?? $target_participant;

      $mutations = [];
      if (!empty($attack_result['damage_dealt'])) {
        $mutations[] = [
          'entity' => $target_id,
          'field' => 'hp',
          'from' => $target_participant['hp'] ?? NULL,
          'to' => $updated_target['hp'] ?? ($attack_result['damage_result']['new_hp'] ?? NULL),
        ];
      }

      return [
        'strike' => TRUE,
        'roll' => $attack_result['roll'] ?? NULL,
        'total' => $attack_result['total'] ?? NULL,
        'ac' => $attack_result['target_ac'] ?? NULL,
        'degree' => $attack_result['degree'] ?? NULL,
        'damage' => $attack_result['damage_dealt'] ?? NULL,
        'damage_type' => $weapon['damage_type'] ?? 'physical',
        'is_defeated' => !empty($updated_target['is_defeated']),
        'mutations' => $mutations,
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Strike failed: @error', ['@error' => $e->getMessage()]);
      return ['error' => 'Strike resolution failed.', 'mutations' => []];
    }
  }

  public function processStride(
    int $encounter_id,
    string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $to_hex = $params['to_hex'] ?? NULL;
    if (!$to_hex) {
      return ['error' => 'Missing to_hex.', 'mutations' => []];
    }

    $is_forced = !empty($params['is_forced']);
    $movement_type = $params['movement_type'] ?? 'land';
    $encounter_for_actor = NULL;
    $actor_participant = NULL;

    if ($this->movementResolver && !$is_forced) {
      $encounter_for_actor = $this->encounterStore->loadEncounter($encounter_id);
      $actor_participant = $encounter_for_actor ? $this->findEncounterParticipantByEntityId($encounter_for_actor, $actor_id) : NULL;

      if ($actor_participant) {
        $speed = $this->movementResolver->getCreatureSpeed($actor_participant, $movement_type);
        if ($speed <= 0) {
          return ['error' => "No {$movement_type} speed.", 'mutations' => []];
        }

        $from_q = (int) ($actor_participant['position_q'] ?? 0);
        $from_r = (int) ($actor_participant['position_r'] ?? 0);
        $from_hex_calc = ['q' => $from_q, 'r' => $from_r];

        $diagonal_count = (int) ($game_state['turn']['diagonal_count'] ?? 0);
        $cost_info = $this->movementResolver->calculateMovementCost(
          $from_hex_calc,
          $to_hex,
          $dungeon_data,
          $diagonal_count,
          $movement_type
        );

        $movement_spent = (int) ($game_state['turn']['movement_spent'] ?? 0);
        if ($movement_spent + $cost_info['cost'] > $speed) {
          return [
            'error' => "Movement cost ({$cost_info['cost']} ft) exceeds remaining speed (" . ($speed - $movement_spent) . " ft).",
            'mutations' => [],
          ];
        }

        $game_state['turn']['movement_spent'] = $movement_spent + $cost_info['cost'];
        $game_state['turn']['diagonal_count'] = $cost_info['new_diagonal_count'];
      }
    }

    $entity = NULL;
    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$e) {
        $iid = $e['entity_instance_id'] ?? ($e['instance_id'] ?? ($e['id'] ?? NULL));
        if ($iid === $actor_id) {
          $entity = &$e;
          break;
        }
      }
      unset($e);
    }

    $from_hex = NULL;
    if ($entity) {
      $from_hex = $entity['placement']['hex'] ?? NULL;
      $entity['placement']['hex'] = ['q' => (int) $to_hex['q'], 'r' => (int) $to_hex['r']];
    }

    try {
      if (!$encounter_for_actor) {
        $encounter_for_actor = $this->encounterStore->loadEncounter($encounter_id);
      }
      if ($actor_participant === NULL && $encounter_for_actor) {
        $actor_participant = $this->findEncounterParticipantByEntityId($encounter_for_actor, $actor_id);
      }

      $participant_id = (int) ($actor_participant['id'] ?? 0);
      if ($participant_id > 0) {
        $this->encounterStore->updateParticipant($participant_id, [
          'position_q' => (int) $to_hex['q'],
          'position_r' => (int) $to_hex['r'],
        ]);
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to update participant position: @error', ['@error' => $e->getMessage()]);
    }

    $snare_trigger = NULL;
    $location_id_stride = $game_state['active_room_id'] ?? ($dungeon_data['current_room_id'] ?? NULL);
    if ($location_id_stride !== NULL && !$is_forced) {
      $snare_trigger = $this->magicItemService->checkSnareAtHex($actor_id, $location_id_stride, $to_hex, $game_state);
    }

    return [
      'stride' => TRUE,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'is_forced' => $is_forced,
      'snare_triggered' => $snare_trigger,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'placement.hex', 'from' => $from_hex, 'to' => $to_hex],
      ],
    ];
  }

  public function processInteract(
    int $encounter_id,
    string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $interaction_type = $params['interaction_type'] ?? 'generic';

    if (in_array($interaction_type, ['open_door', 'open_passage'])) {
      if (!empty($dungeon_data['connections'])) {
        foreach ($dungeon_data['connections'] as &$conn) {
          if (($conn['id'] ?? NULL) === $target_id) {
            $conn['is_passable'] = TRUE;
            $conn['is_discovered'] = TRUE;
            break;
          }
        }
        unset($conn);
      }
    }

    return [
      'interacted' => TRUE,
      'interaction_type' => $interaction_type,
      'target' => $target_id,
      'mutations' => [],
    ];
  }

  public function processCastSpell(
    int $encounter_id,
    string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    callable $prepared_spell_list_contains_spell,
    callable $apply_canonical_state_after_spell_consume,
    callable $sync_canonical_spellcasting_projection,
    callable $normalize_spell_resource_error_message
  ): array {
    $spell_name = $params['spell_name'] ?? 'unknown';
    $spell_id = (string) ($params['spell_id'] ?? '');
    $spell_level = (int) ($params['spell_level'] ?? 0);
    $cast_at_level = (int) ($params['cast_at_level'] ?? $spell_level);
    $is_cantrip = !empty($params['is_cantrip']);
    $is_focus_spell = !empty($params['is_focus_spell']);
    $requires_attack_roll = !empty($params['requires_attack_roll']);
    $spell_tradition = $params['spell_tradition'] ?? NULL;

    $enc_cs = $this->encounterStore->loadEncounter($encounter_id);
    $ptcp_cs = $enc_cs ? $this->findEncounterParticipantByEntityId($enc_cs, $actor_id) : NULL;
    if (!$ptcp_cs) {
      return ['cast' => FALSE, 'error' => 'Caster not found.', 'mutations' => [], 'narration' => NULL];
    }
    $edata_cs = !empty($ptcp_cs['entity_ref']) ? json_decode((string) $ptcp_cs['entity_ref'], TRUE) : [];
    $actor_entity_index = $this->canonicalProjectionService->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $has_actor_entity = $actor_entity_index !== NULL
      && isset($dungeon_data['entities'][$actor_entity_index])
      && is_array($dungeon_data['entities'][$actor_entity_index]);
    $canonical_state = NULL;
    $canonical_identity = ['character_id' => '', 'instance_id' => NULL];
    if ($has_actor_entity) {
      $actor_entity = $dungeon_data['entities'][$actor_entity_index];
      $canonical_state = $this->canonicalProjectionService->loadCanonicalCharacterState($actor_entity, (int) $campaign_id);
      $canonical_identity = $this->canonicalProjectionService->resolveCanonicalCharacterIdentity($actor_entity);
    }
    if ((string) ($canonical_identity['character_id'] ?? '') === '') {
      $canonical_identity = $this->canonicalProjectionService->resolveCanonicalCharacterIdentityFromParticipantEntityRef($edata_cs, $actor_id);
    }
    $canonical_character_id = (string) ($canonical_identity['character_id'] ?? '');
    $canonical_instance_id = is_string($canonical_identity['instance_id'] ?? NULL) ? $canonical_identity['instance_id'] : NULL;
    $has_canonical_sheet = $canonical_character_id !== '' && ctype_digit($canonical_character_id) && (int) $canonical_character_id > 0;
    if (!is_array($canonical_state) && $has_canonical_sheet) {
      try {
        $canonical_state = $this->characterStateService->getState(
          $canonical_character_id,
          $campaign_id > 0 ? $campaign_id : NULL,
          $canonical_instance_id
        );
      }
      catch (\InvalidArgumentException $exception) {
        $canonical_state = NULL;
      }
    }

    $char_tradition = $canonical_state['spells']['tradition']
      ?? $edata_cs['spellcasting_tradition']
      ?? NULL;
    if ($spell_tradition && $char_tradition && strtolower((string) $spell_tradition) !== strtolower((string) $char_tradition)) {
      return ['cast' => FALSE, 'error' => "Spell tradition '{$spell_tradition}' does not match character tradition '{$char_tradition}'.", 'mutations' => [], 'narration' => NULL];
    }

    $cast_time_param = $params['cast_time'] ?? NULL;
    if ($cast_time_param) {
      $phase_check = $this->spellCatalog->validateCastTimeForPhase($cast_time_param, 'encounter');
      if (!$phase_check['valid']) {
        return ['cast' => FALSE, 'error' => $phase_check['error'], 'mutations' => [], 'narration' => NULL];
      }
    }

    if (!empty($edata_cs['polymorph_battle_form'])) {
      return ['cast' => FALSE, 'error' => 'Cannot cast spells while in a polymorph battle form.', 'mutations' => [], 'narration' => NULL];
    }

    $metamagic_applied = NULL;
    if (!empty($game_state['turn']['metamagic_pending'][$actor_id])) {
      $metamagic_applied = $game_state['turn']['metamagic_pending'][$actor_id];
      unset($game_state['turn']['metamagic_pending'][$actor_id]);
    }

    $is_innate_spell = !empty($params['is_innate_spell']);
    $spell_attack_mod = (int) ($edata_cs['spell_attack_modifier'] ?? $params['spell_attack_modifier'] ?? 0);
    $spell_dc = (int) ($edata_cs['spell_dc'] ?? $params['spell_dc'] ?? (10 + ($params['proficiency_bonus'] ?? 0) + ($params['key_ability_mod'] ?? 0)));
    if ($is_innate_spell) {
      $cha_mod = (int) ($edata_cs['charisma_modifier'] ?? $params['charisma_modifier'] ?? 0);
      $innate_proficiency = (int) ($edata_cs['spell_proficiency_bonus'] ?? $params['proficiency_bonus'] ?? 2);
      $spell_attack_mod = $cha_mod + $innate_proficiency;
      $spell_dc = 10 + $cha_mod + $innate_proficiency;
    }
    $attack_result = NULL;
    if ($requires_attack_roll) {
      $d20_cs = $this->numberGenerationService->rollPathfinderDie(20);
      $total_cs = $d20_cs + $spell_attack_mod;
      $target_ac_cs = (int) ($params['target_ac'] ?? 15);
      $attack_result = [
        'roll' => $d20_cs,
        'total' => $total_cs,
        'degree' => $this->combatCalculator->calculateDegreeOfSuccess($total_cs, $target_ac_cs, $d20_cs),
      ];
    }

    if ($is_cantrip) {
      $effective_level = $this->canonicalProjectionService->resolveEffectiveCantripLevel($canonical_state, $edata_cs);
      return [
        'cast' => TRUE,
        'spell' => $spell_name,
        'is_cantrip' => TRUE,
        'effective_level' => $effective_level,
        'spell_dc' => $spell_dc,
        'attack_result' => $attack_result,
        'narration' => NULL,
        'mutations' => [],
      ];
    }

    if ($is_focus_spell) {
      $focus_remaining = NULL;
      $canonical_consumed = FALSE;

      if ($has_canonical_sheet) {
        try {
          $consume_result = $this->characterStateService->castSpell(
            $canonical_character_id,
            $spell_id !== '' ? $spell_id : (string) $spell_name,
            0,
            TRUE,
            $campaign_id > 0 ? $campaign_id : NULL,
            $canonical_instance_id
          );
          $focus_remaining = isset($consume_result['remaining']) ? max(0, (int) $consume_result['remaining']) : NULL;
          if (!is_array($canonical_state)) {
            $canonical_state = $this->characterStateService->getState(
              $canonical_character_id,
              $campaign_id > 0 ? $campaign_id : NULL,
              $canonical_instance_id
            );
          }
          if (is_array($canonical_state)) {
            $apply_canonical_state_after_spell_consume($canonical_state, TRUE, 0, max(0, (int) ($focus_remaining ?? 0)));
            $sync_canonical_spellcasting_projection($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
          }
          $canonical_consumed = TRUE;
        }
        catch (\InvalidArgumentException $exception) {
          return [
            'cast' => FALSE,
            'error' => $normalize_spell_resource_error_message($exception->getMessage(), TRUE, 0),
            'mutations' => [],
            'narration' => NULL,
          ];
        }
      }

      if (!$canonical_consumed) {
        return [
          'cast' => FALSE,
          'error' => 'Canonical character sheet is required for spellcasting resource updates.',
          'mutations' => [],
          'narration' => NULL,
        ];
      }

      return [
        'cast' => TRUE,
        'spell' => $spell_name,
        'is_focus_spell' => TRUE,
        'focus_points_remaining' => max(0, (int) ($focus_remaining ?? 0)),
        'spell_dc' => $spell_dc,
        'attack_result' => $attack_result,
        'narration' => NULL,
        'mutations' => [],
      ];
    }

    $slot_level = $cast_at_level > 0 ? $cast_at_level : $spell_level;
    if ($slot_level < 1) {
      $slot_level = 1;
    }
    $slot_key = (string) $slot_level;

    if (!isset($edata_cs['spell_slots'])) {
      $edata_cs['spell_slots'] = [];
    }
    $slot_data_cs = $edata_cs['spell_slots'][$slot_key] ?? ['max' => 0, 'used' => 0];
    $slots_avail = max(0, (int) ($slot_data_cs['max'] ?? 0) - (int) ($slot_data_cs['used'] ?? 0));
    if (!$has_canonical_sheet && $slots_avail < 1) {
      return ['cast' => FALSE, 'error' => "No level-{$slot_level} spell slots remaining.", 'mutations' => [], 'narration' => NULL];
    }

    $casting_type = strtolower((string) (
      $canonical_state['casting_type']
      ?? $canonical_state['spells']['casting_type']
      ?? $edata_cs['casting_type']
      ?? 'spontaneous'
    ));
    if ($casting_type === 'prepared') {
      $prepared_cs = $canonical_state['prepared_spells'][$slot_key]
        ?? $canonical_state['state']['prepared_spells'][$slot_key]
        ?? $canonical_state['spells']['prepared_spells'][$slot_key]
        ?? $edata_cs['prepared_spells'][$slot_key]
        ?? [];
      if (!$prepared_spell_list_contains_spell($prepared_cs, (string) $spell_name, $spell_id)) {
        return ['cast' => FALSE, 'error' => "'{$spell_name}' is not prepared in a level-{$slot_level} slot.", 'mutations' => [], 'narration' => NULL];
      }
    }

    $slots_remaining = NULL;
    $canonical_consumed = FALSE;

    if ($has_canonical_sheet) {
      try {
        $consume_result = $this->characterStateService->castSpell(
          $canonical_character_id,
          $spell_id !== '' ? $spell_id : (string) $spell_name,
          $slot_level,
          FALSE,
          $campaign_id > 0 ? $campaign_id : NULL,
          $canonical_instance_id
        );
        $slots_remaining = isset($consume_result['remaining']) ? max(0, (int) $consume_result['remaining']) : NULL;
        if (!is_array($canonical_state)) {
          $canonical_state = $this->characterStateService->getState(
            $canonical_character_id,
            $campaign_id > 0 ? $campaign_id : NULL,
            $canonical_instance_id
          );
        }
        if (is_array($canonical_state)) {
          $apply_canonical_state_after_spell_consume($canonical_state, FALSE, $slot_level, max(0, (int) ($slots_remaining ?? 0)));
          $sync_canonical_spellcasting_projection($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
        }
        $canonical_consumed = TRUE;
      }
      catch (\InvalidArgumentException $exception) {
        return [
          'cast' => FALSE,
          'error' => $normalize_spell_resource_error_message($exception->getMessage(), FALSE, $slot_level),
          'mutations' => [],
          'narration' => NULL,
        ];
      }
    }

    if (!$canonical_consumed) {
      return [
        'cast' => FALSE,
        'error' => 'Canonical character sheet is required for spellcasting resource updates.',
        'mutations' => [],
        'narration' => NULL,
      ];
    }

    $incapacitation_note = NULL;
    $is_incapacitation_spell = !empty($params['is_incapacitation']);
    if ($is_incapacitation_spell) {
      $caster_level = (int) ($edata_cs['level'] ?? $params['caster_level'] ?? 1);
      $target_level = (int) ($params['target_level'] ?? 0);
      if ($target_level > (int) floor($caster_level / 2)) {
        $incapacitation_note = "Incapacitation: target level ({$target_level}) exceeds half caster level (" . floor($caster_level / 2) . "); degrees of success shifted one step toward success.";
      }
    }

    $avert_gaze_note = NULL;
    if (!empty($params['is_gaze']) && $target_id) {
      $enc_ag2 = $this->encounterStore->loadEncounter($encounter_id);
      $ptcp_ag2 = $enc_ag2 ? $this->findEncounterParticipantByEntityId($enc_ag2, $target_id) : NULL;
      if ($ptcp_ag2) {
        $edata_ag2 = !empty($ptcp_ag2['entity_ref']) ? json_decode((string) $ptcp_ag2['entity_ref'], TRUE) : [];
        if (!empty($edata_ag2['avert_gaze_active'])) {
          $spell_dc = max(1, $spell_dc - 2);
          $avert_gaze_note = 'Avert Gaze active: spell_dc reduced by 2 (circumstance bonus to save).';
        }
      }
    }

    return [
      'cast' => TRUE,
      'spell' => $spell_name,
      'spell_level' => $spell_level,
      'cast_at_level' => $slot_level,
      'heightened' => $slot_level > $spell_level,
      'slots_remaining' => max(0, (int) ($slots_remaining ?? ($slots_avail - 1))),
      'spell_dc' => $spell_dc,
      'spell_attack_modifier' => $spell_attack_mod,
      'attack_result' => $attack_result,
      'metamagic_applied' => $metamagic_applied,
      'incapacitation_note' => $incapacitation_note,
      'avert_gaze_note' => $avert_gaze_note,
      'narration' => NULL,
      'mutations' => [],
    ];
  }

  protected function findEncounterParticipantByEntityId(array $encounter, string $entity_id): ?array {
    foreach (($encounter['participants'] ?? []) as $participant) {
      if ((string) ($participant['entity_id'] ?? '') === (string) $entity_id) {
        return $participant;
      }
    }

    return NULL;
  }

}
