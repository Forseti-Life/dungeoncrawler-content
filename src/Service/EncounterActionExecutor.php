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
  protected CombatResolutionContractService $combatResolutionContractService;
  protected UnifiedDamageEngine $unifiedDamageEngine;
  protected UnifiedMovementEngine $unifiedMovementEngine;
  protected ?MovementResolverService $movementResolver;
  protected ?ActorDispositionService $actorDispositionService;
  protected ?DispositionMutationClassifierService $dispositionMutationClassifierService;
  protected ?RelationshipAttitudeService $relationshipAttitudeService;
  protected ?RelationshipsActorIdentityResolverService $relationshipsActorIdentityResolverService;
  protected ?InstitutionMembershipService $institutionMembershipService;
  protected ActionTargetingService $actionTargetingService;
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
    CombatResolutionContractService $combat_resolution_contract_service,
    LoggerChannelFactoryInterface $logger_factory,
    ?MovementResolverService $movement_resolver = NULL,
    ?ActorDispositionService $actor_disposition_service = NULL,
    ?DispositionMutationClassifierService $disposition_mutation_classifier_service = NULL,
    ?ActionTargetingService $action_targeting_service = NULL,
    ?UnifiedDamageEngine $unified_damage_engine = NULL,
    ?UnifiedMovementEngine $unified_movement_engine = NULL,
    ?RelationshipAttitudeService $relationship_attitude_service = NULL,
    ?RelationshipsActorIdentityResolverService $relationships_actor_identity_resolver_service = NULL,
    ?InstitutionMembershipService $institution_membership_service = NULL
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
    $this->combatResolutionContractService = $combat_resolution_contract_service;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->movementResolver = $movement_resolver;
    $this->actorDispositionService = $actor_disposition_service;
    $this->dispositionMutationClassifierService = $disposition_mutation_classifier_service;
    $this->relationshipAttitudeService = $relationship_attitude_service;
    $this->relationshipsActorIdentityResolverService = $relationships_actor_identity_resolver_service;
    $this->institutionMembershipService = $institution_membership_service;
    $this->actionTargetingService = $action_targeting_service ?? new ActionTargetingService();
    $this->unifiedDamageEngine = $unified_damage_engine ?? new UnifiedDamageEngine(
      $encounter_store,
      $number_generation_service,
      $combat_resolution_contract_service
    );
    $this->unifiedMovementEngine = $unified_movement_engine ?? new UnifiedMovementEngine(
      $combat_resolution_contract_service
    );
  }

  protected function isMagicMissileSpell(string $spell_id, string $spell_name): bool {
    $identifier = strtolower(trim($spell_id !== '' ? $spell_id : $spell_name));
    $canonical = preg_replace('/[^a-z0-9]+/', '', $identifier) ?? '';
    return $canonical === 'magicmissile';
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
    $target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);
    $target_constraint_check = $this->actionTargetingService->validateTargetSelectionConstraints($target_refs, $params);
    if (empty($target_constraint_check['valid'])) {
      return [
        'talked' => FALSE,
        'error' => (string) ($target_constraint_check['error'] ?? 'Invalid target selection.'),
        'mutations' => [],
      ];
    }
    $target_id = $target_refs[0] ?? $target_id;
    $targeting_mode = $this->actionTargetingService->resolveTargetingMode('talk', $params);
    if ($this->actionTargetingService->requiresTarget('talk', $targeting_mode) && $target_id === NULL) {
      return [
        'talked' => FALSE,
        'error' => sprintf("Talk targeting mode '%s' requires a target.", $targeting_mode),
        'mutations' => [],
      ];
    }

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
        ],
        [
          'response_mode' => 'actor_scoped',
          'include_legacy_overlay' => FALSE,
        ]
      );

      if (is_array($chat_result['runtime_snapshot']['game_state'] ?? NULL)) {
        $game_state = $chat_result['runtime_snapshot']['game_state'];
      }
      elseif (is_array($chat_result['combat_transition']['game_state'] ?? NULL)) {
        $game_state = $chat_result['combat_transition']['game_state'];
      }

      $chat_response = [
        'gm_response' => $chat_result['gm_response'] ?? NULL,
        'npc_interjections' => $chat_result['npc_interjections'] ?? [],
        'quest_updates' => $chat_result['quest_updates'] ?? [],
        'state_diff' => $chat_result['state_diff'] ?? [],
        'combat_transition' => $chat_result['combat_transition'] ?? NULL,
        'aggression_summary' => $chat_result['aggression_summary'] ?? NULL,
        'combat_entry_summary' => $chat_result['combat_entry_summary'] ?? NULL,
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
      $disposition_change = NULL;
      if (
        $campaign_id > 0
        && is_string($actor_id)
        && trim($actor_id) !== ''
        && is_string($target_id)
        && trim($target_id) !== ''
      ) {
        $disposition_change = $this->applyClassifiedDispositionMutation(
          $campaign_id,
          'talk',
          $actor_id,
          $target_id,
          sprintf('Encounter talk with %s', $target_id),
          [
            'relationship_type' => 'conversation',
            'relationship_status' => 'known',
            'idempotency_key' => sha1(json_encode([
              'encounter_talk' => TRUE,
              'campaign_id' => $campaign_id,
              'source' => $actor_id,
              'target' => $target_id,
              'message' => $message,
              'turn' => $turn_ctx['turn_index_raw'] ?? ($game_state['turn']['index'] ?? NULL),
              'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          ]
        );
      }

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
        'aggression_summary' => $chat_response['aggression_summary'],
        'combat_entry_summary' => $chat_response['combat_entry_summary'],
        'canonical_actions' => $chat_response['canonical_actions'],
        'npc_interjections_deferred' => !empty($chat_result['npc_interjections_deferred']),
        'turn_log_key' => array_key_exists('turn_log_key', $chat_result) ? $chat_result['turn_log_key'] : NULL,
        'turn_logs' => array_values(array_filter($chat_result['turn_logs'] ?? [], 'is_array')),
        'chat_response' => $chat_response,
        'narration' => $chat_result['gm_response']['message'] ?? ($chat_result['gm_response']['text'] ?? NULL),
        'disposition_change' => $disposition_change,
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
      $target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);
      $target_constraint_check = $this->actionTargetingService->validateTargetSelectionConstraints($target_refs, $params);
      if (empty($target_constraint_check['valid'])) {
        return ['error' => (string) ($target_constraint_check['error'] ?? 'Invalid target selection.')];
      }
      $target_id = $target_refs[0] ?? NULL;
      if ($target_id === NULL) {
        return ['error' => 'Strike requires a target.'];
      }

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
      $damage_packet = NULL;
      $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
        'strike',
        $actor_id,
        $target_id,
        $params
      );
      $strike_damage = $this->unifiedDamageEngine->resolveStrikeDamage(
        $actor_id,
        $target_id,
        $encounter_id,
        $weapon,
        is_array($attack_result) ? $attack_result : [],
        is_array($target_participant) ? $target_participant : [],
        is_array($updated_target) ? $updated_target : []
      );
      $damage_packet = is_array($strike_damage['damage_packet'] ?? NULL) ? $strike_damage['damage_packet'] : NULL;
      if (!empty($strike_damage['mutations']) && is_array($strike_damage['mutations'])) {
        $mutations = array_merge($mutations, $strike_damage['mutations']);
      }
      $disposition_change = $this->applyClassifiedDispositionMutation(
        $campaign_id,
        'strike',
        $actor_id,
        $target_id,
        sprintf('Encounter strike against %s', $target_id),
        [
          'relationship_type' => 'combat',
          'relationship_status' => 'known',
          'idempotency_key' => sha1(json_encode([
            'encounter_id' => $encounter_id,
            'event_type' => 'attack',
            'source' => $actor_id,
            'target' => $target_id,
            'attack_result' => [
              'roll' => $attack_result['roll'] ?? NULL,
              'total' => $attack_result['total'] ?? NULL,
              'degree' => $attack_result['degree'] ?? NULL,
              'damage' => $attack_result['damage_dealt'] ?? NULL,
            ],
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          'requires_attack_roll' => TRUE,
        ]
      );
      $damage_disposition_change = $this->applyDamageTriggeredHostilityConsequences(
        $campaign_id,
        $encounter_id,
        $actor_id,
        $target_id,
        $damage_packet
      );
      if ($damage_disposition_change !== NULL) {
        $disposition_change = $damage_disposition_change;
      }

      return [
        'strike' => TRUE,
        'execution_request' => $execution_request,
        'target_id' => $target_id,
        'roll' => $attack_result['roll'] ?? NULL,
        'total' => $attack_result['total'] ?? NULL,
        'ac' => $attack_result['target_ac'] ?? NULL,
        'degree' => $attack_result['degree'] ?? NULL,
        'damage' => $attack_result['damage_dealt'] ?? NULL,
        'damage_type' => $weapon['damage_type'] ?? 'physical',
        'damage_packet' => $damage_packet,
        'resolution_envelope' => $this->combatResolutionContractService->buildResolutionEnvelope(
          $execution_request,
          array_values(array_filter([$damage_packet], 'is_array')),
          [
            'degree' => $attack_result['degree'] ?? NULL,
            'damage' => $attack_result['damage_dealt'] ?? NULL,
            'is_defeated' => !empty($updated_target['is_defeated']),
          ]
        ),
        'is_defeated' => !empty($updated_target['is_defeated']),
        'disposition_change' => $disposition_change,
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
    array &$dungeon_data,
    ?int $campaign_id = NULL
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

        $action_cost = max(1, min(3, (int) ($params['action_cost'] ?? 1)));
        $max_distance = $speed * $action_cost;

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

        if ($cost_info['cost'] > $max_distance) {
          return [
            'error' => "Movement cost ({$cost_info['cost']} ft) exceeds drag movement range ({$max_distance} ft).",
            'mutations' => [],
          ];
        }

        $game_state['turn']['movement_spent'] = $cost_info['cost'];
        $game_state['turn']['diagonal_count'] = $cost_info['new_diagonal_count'];
        $params['distance_ft'] = (int) $cost_info['cost'];
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

    $this->syncCampaignCharacterPlacement(
      $campaign_id ?? 0,
      $actor_id,
      (int) $to_hex['q'],
      (int) $to_hex['r'],
      (string) ($entity['placement']['room_id'] ?? $game_state['active_room_id'] ?? $dungeon_data['current_room_id'] ?? '')
    );

    $snare_trigger = NULL;
    $location_id_stride = $game_state['active_room_id'] ?? ($dungeon_data['current_room_id'] ?? NULL);
    if ($location_id_stride !== NULL && !$is_forced) {
      $snare_trigger = $this->magicItemService->checkSnareAtHex($actor_id, $location_id_stride, $to_hex, $game_state);
    }

    $resolved_from_hex = is_array($from_hex)
      ? ['q' => (int) ($from_hex['q'] ?? 0), 'r' => (int) ($from_hex['r'] ?? 0)]
      : ['q' => (int) ($to_hex['q'] ?? 0), 'r' => (int) ($to_hex['r'] ?? 0)];
    $resolved_to_hex = ['q' => (int) ($to_hex['q'] ?? 0), 'r' => (int) ($to_hex['r'] ?? 0)];
    $distance_ft = (int) ($params['distance_ft'] ?? 0);
    $resolved_action_cost = max(0, (int) ($params['action_cost'] ?? 1));
    $movement_packet = $this->unifiedMovementEngine->buildMovementResolutionPacket(
      $actor_id,
      $is_forced ? 'forced' : 'stride',
      $resolved_from_hex,
      $resolved_to_hex,
      $distance_ft,
      $resolved_action_cost,
      [
        'encounter_id' => $encounter_id,
        'snare_triggered' => !empty($snare_trigger),
      ]
    );
    $execution_request = $this->combatResolutionContractService->buildCombatExecutionRequest(
      $is_forced ? 'forced_movement' : 'stride',
      $actor_id,
      NULL,
      $params
    );

    return [
      'stride' => TRUE,
      'execution_request' => $execution_request,
      'from_hex' => $resolved_from_hex,
      'to_hex' => $resolved_to_hex,
      'distance_ft' => $distance_ft,
      'is_forced' => $is_forced,
      'movement_packet' => $movement_packet,
      'resolution_envelope' => $this->combatResolutionContractService->buildResolutionEnvelope(
        $execution_request,
        [$movement_packet],
        [
          'distance_ft' => $distance_ft,
          'is_forced' => $is_forced,
        ]
      ),
      'snare_triggered' => $snare_trigger,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'placement.hex', 'from' => $resolved_from_hex, 'to' => $resolved_to_hex],
      ],
    ];
  }

  /**
   * Keep dc_campaign_characters placement hot columns aligned with live runtime movement.
   */
  protected function syncCampaignCharacterPlacement(
    int $campaign_id,
    string $actor_id,
    int $q,
    int $r,
    string $room_id
  ): void {
    if ($campaign_id <= 0 || $actor_id === '') {
      return;
    }

    try {
      $database = \Drupal::database();
      if (!$database->schema()->tableExists('dc_campaign_characters')) {
        return;
      }

      $existing_state = $database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['state_data', 'last_room_id', 'location_ref'])
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $actor_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($existing_state)) {
        return;
      }

      $state_data = json_decode((string) ($existing_state['state_data'] ?? ''), TRUE);
      if (!is_array($state_data)) {
        $state_data = [];
      }
      $resolved_room_id = trim($room_id);
      if ($resolved_room_id === '') {
        $resolved_room_id = trim((string) (
          $state_data['placement']['room_id']
          ?? $existing_state['last_room_id']
          ?? $existing_state['location_ref']
          ?? ''
        ));
      }
      $state_data['placement'] = is_array($state_data['placement'] ?? NULL) ? $state_data['placement'] : [];
      $state_data['placement']['hex'] = [
        'q' => $q,
        'r' => $r,
      ];
      if ($resolved_room_id !== '') {
        $state_data['placement']['room_id'] = $resolved_room_id;
      }

      $update_fields = [
        'position_q' => $q,
        'position_r' => $r,
        'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated' => time(),
      ];
      if ($resolved_room_id !== '') {
        $update_fields['last_room_id'] = $resolved_room_id;
        $update_fields['location_type'] = 'room';
        $update_fields['location_ref'] = $resolved_room_id;
      }

      $database->update('dc_campaign_characters')
        ->fields($update_fields)
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $actor_id)
        ->execute();
    }
    catch (\Throwable $e) {
      $this->logger->warning('Failed to sync dc_campaign_characters placement for actor @actor: @error', [
        '@actor' => $actor_id,
        '@error' => $e->getMessage(),
      ]);
    }
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
    $target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);
    $target_constraint_check = $this->actionTargetingService->validateTargetSelectionConstraints($target_refs, $params);
    if (empty($target_constraint_check['valid'])) {
      return [
        'interacted' => FALSE,
        'error' => (string) ($target_constraint_check['error'] ?? 'Invalid target selection.'),
        'mutations' => [],
      ];
    }
    $target_id = $target_refs[0] ?? $target_id;
    $targeting_mode = $this->actionTargetingService->resolveTargetingMode('interact', $params);
    if ($this->actionTargetingService->requiresTarget('interact', $targeting_mode) && $target_id === NULL) {
      return [
        'interacted' => FALSE,
        'error' => sprintf("Interact targeting mode '%s' requires a target.", $targeting_mode),
        'mutations' => [],
      ];
    }

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
    \Drupal::logger('dungeoncrawler_targeting')->debug(
      'cast_spell start encounter=@encounter actor=@actor target=@target spell=@spell mode=@mode min=@min max=@max dup=@dup targets=@targets',
      [
        '@encounter' => (string) $encounter_id,
        '@actor' => $actor_id,
        '@target' => (string) ($target_id ?? ''),
        '@spell' => (string) ($params['spell_id'] ?? $params['spell_name'] ?? 'unknown'),
        '@mode' => (string) ($params['targeting_mode'] ?? $params['targeting'] ?? ''),
        '@min' => (string) ($params['min_targets'] ?? ''),
        '@max' => (string) ($params['max_targets'] ?? ''),
        '@dup' => !empty($params['allow_duplicate_targets']) ? '1' : '0',
        '@targets' => json_encode($params['targets'] ?? [], JSON_UNESCAPED_SLASHES),
      ]
    );
    $target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);
    \Drupal::logger('dungeoncrawler_targeting')->debug(
      'cast_spell normalized targets refs=@refs',
      ['@refs' => json_encode($target_refs, JSON_UNESCAPED_SLASHES)]
    );
    $target_constraint_check = $this->actionTargetingService->validateTargetSelectionConstraints($target_refs, $params);
    if (empty($target_constraint_check['valid'])) {
      \Drupal::logger('dungeoncrawler_targeting')->warning(
        'cast_spell rejected target constraints actor=@actor refs=@refs error=@error',
        [
          '@actor' => $actor_id,
          '@refs' => json_encode($target_refs, JSON_UNESCAPED_SLASHES),
          '@error' => (string) ($target_constraint_check['error'] ?? 'invalid target selection'),
        ]
      );
      return [
        'cast' => FALSE,
        'error' => (string) ($target_constraint_check['error'] ?? 'Invalid target selection.'),
        'mutations' => [],
        'narration' => NULL,
      ];
    }
    $target_id = $target_refs[0] ?? NULL;
    $targeting_mode = $this->actionTargetingService->resolveTargetingMode('cast_spell', $params);
    if ($this->actionTargetingService->requiresTarget('cast_spell', $targeting_mode) && $target_id === NULL) {
      \Drupal::logger('dungeoncrawler_targeting')->warning(
        'cast_spell rejected missing required target actor=@actor mode=@mode refs=@refs',
        [
          '@actor' => $actor_id,
          '@mode' => $targeting_mode,
          '@refs' => json_encode($target_refs, JSON_UNESCAPED_SLASHES),
        ]
      );
      return [
        'cast' => FALSE,
        'error' => sprintf("Spell targeting mode '%s' requires a target.", $targeting_mode),
        'mutations' => [],
        'narration' => NULL,
      ];
    }

    $spell_name = $params['spell_name'] ?? 'unknown';
    $spell_id = (string) ($params['spell_id'] ?? '');
    $spell_level = (int) ($params['spell_level'] ?? 0);
    $cast_at_level = (int) ($params['cast_at_level'] ?? $spell_level);
    $is_cantrip = !empty($params['is_cantrip']);
    $is_focus_spell = !empty($params['is_focus_spell']);
    $requires_attack_roll = !empty($params['requires_attack_roll']);
    $spell_tradition = $params['spell_tradition'] ?? NULL;
    if ($target_id === NULL && $this->isMagicMissileSpell($spell_id, (string) $spell_name)) {
      return [
        'cast' => FALSE,
        'error' => 'Magic Missile requires selecting a valid target.',
        'mutations' => [],
        'narration' => NULL,
      ];
    }

    $enc_cs = $this->encounterStore->loadEncounter($encounter_id);
    $ptcp_cs = $enc_cs ? $this->findEncounterParticipantByEntityId($enc_cs, $actor_id) : NULL;
    if (!$ptcp_cs) {
      \Drupal::logger('dungeoncrawler_targeting')->warning('cast_spell rejected caster not found actor=@actor encounter=@encounter', [
        '@actor' => $actor_id,
        '@encounter' => (string) $encounter_id,
      ]);
      return ['cast' => FALSE, 'error' => 'Caster not found.', 'mutations' => [], 'narration' => NULL];
    }
    $target_participant = NULL;
    if ($target_id !== NULL) {
      $target_participant = $this->findEncounterParticipantByEntityId($enc_cs ?: [], $target_id);
      if (!$target_participant) {
        \Drupal::logger('dungeoncrawler_targeting')->warning(
          'cast_spell rejected primary target missing target=@target actor=@actor encounter=@encounter',
          [
            '@target' => $target_id,
            '@actor' => $actor_id,
            '@encounter' => (string) $encounter_id,
          ]
        );
        return ['cast' => FALSE, 'error' => 'Target is not present in the encounter.', 'mutations' => [], 'narration' => NULL];
      }
      if (!isset($params['target_level'])) {
        $target_ref_data = !empty($target_participant['entity_ref']) ? json_decode((string) $target_participant['entity_ref'], TRUE) : [];
        $target_level = (int) ($target_ref_data['level'] ?? $target_ref_data['character_level'] ?? 0);
        if ($target_level > 0) {
          $params['target_level'] = $target_level;
        }
      }
    }
    $origin_hex = $this->resolveParticipantHex($ptcp_cs ?: []);
    if (count($target_refs) > 1) {
      foreach ($target_refs as $candidate_target_ref) {
        $candidate = $this->findEncounterParticipantByEntityId($enc_cs ?: [], $candidate_target_ref);
        if (!$candidate) {
          \Drupal::logger('dungeoncrawler_targeting')->warning(
            'cast_spell rejected selected target missing target=@target actor=@actor refs=@refs',
            [
              '@target' => $candidate_target_ref,
              '@actor' => $actor_id,
              '@refs' => json_encode($target_refs, JSON_UNESCAPED_SLASHES),
            ]
          );
          return [
            'cast' => FALSE,
            'error' => sprintf('One or more selected targets are not present in the encounter (%s).', $candidate_target_ref),
            'mutations' => [],
            'narration' => NULL,
          ];
        }
        $target_hex = $this->resolveParticipantHex($candidate);
        $range_check = $this->actionTargetingService->validateRangeConstraint($origin_hex, $target_hex, $params);
        if (empty($range_check['valid'])) {
          \Drupal::logger('dungeoncrawler_targeting')->warning(
            'cast_spell rejected range actor=@actor target=@target distance_ft=@distance error=@error',
            [
              '@actor' => $actor_id,
              '@target' => $candidate_target_ref,
              '@distance' => (string) ($range_check['distance_ft'] ?? ''),
              '@error' => (string) ($range_check['error'] ?? 'range validation failed'),
            ]
          );
          return [
            'cast' => FALSE,
            'error' => (string) ($range_check['error'] ?? 'Selected target is out of range.'),
            'mutations' => [],
            'narration' => NULL,
          ];
        }
      }
      $params['selected_targets'] = $target_refs;
    }
    elseif ($target_id !== NULL) {
      $target_participant = $this->findEncounterParticipantByEntityId($enc_cs ?: [], $target_id);
      if ($target_participant) {
        $target_hex = $this->resolveParticipantHex($target_participant);
        $range_check = $this->actionTargetingService->validateRangeConstraint($origin_hex, $target_hex, $params);
        if (empty($range_check['valid'])) {
          \Drupal::logger('dungeoncrawler_targeting')->warning(
            'cast_spell rejected range actor=@actor target=@target distance_ft=@distance error=@error',
            [
              '@actor' => $actor_id,
              '@target' => $target_id,
              '@distance' => (string) ($range_check['distance_ft'] ?? ''),
              '@error' => (string) ($range_check['error'] ?? 'range validation failed'),
            ]
          );
          return [
            'cast' => FALSE,
            'error' => (string) ($range_check['error'] ?? 'Selected target is out of range.'),
            'mutations' => [],
            'narration' => NULL,
          ];
        }
      }
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
    $spell_damage = NULL;
    $spell_damage_type = NULL;
    $spell_damage_mutations = [];
    $spell_damage_packet = NULL;
    $target_defeated = FALSE;
    $missiles_fired = NULL;
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

    $should_trigger_negative_spell_disposition = $target_id !== NULL
      && trim($target_id) !== ''
      && (
        !empty($params['is_negative_effect_spell'])
        || $requires_attack_roll
      );

    $disposition_change = NULL;
    if ($is_cantrip) {
      $effective_level = $this->canonicalProjectionService->resolveEffectiveCantripLevel($canonical_state, $edata_cs);
      if ($should_trigger_negative_spell_disposition) {
        $classified_disposition_change = $this->applyClassifiedDispositionMutation(
          $campaign_id > 0 ? $campaign_id : NULL,
          'cast_spell',
          $actor_id,
          $target_id,
          sprintf('Encounter spell cast against %s: %s', (string) $target_id, (string) $spell_name),
          [
            'relationship_type' => 'combat',
            'relationship_status' => 'known',
            'idempotency_key' => sha1(json_encode([
              'encounter_id' => $encounter_id,
              'event_type' => 'negative_effect_spell',
              'source' => $actor_id,
              'target' => (string) $target_id,
              'spell_name' => (string) $spell_name,
              'cast_at_level' => $cast_at_level,
              'attack_result' => $attack_result,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'is_negative_effect_spell' => !empty($params['is_negative_effect_spell']),
            'requires_attack_roll' => $requires_attack_roll,
          ]
        );
        if ($disposition_change === NULL && $classified_disposition_change !== NULL) {
          $disposition_change = $classified_disposition_change;
        }
      }
      return [
        'cast' => TRUE,
        'target_id' => $target_id,
        'spell' => $spell_name,
        'is_cantrip' => TRUE,
        'effective_level' => $effective_level,
        'spell_dc' => $spell_dc,
        'attack_result' => $attack_result,
        'disposition_change' => $disposition_change,
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

      if ($should_trigger_negative_spell_disposition) {
        $disposition_change = $this->applyClassifiedDispositionMutation(
          $campaign_id > 0 ? $campaign_id : NULL,
          'cast_spell',
          $actor_id,
          $target_id,
          sprintf('Encounter focus spell cast against %s: %s', (string) $target_id, (string) $spell_name),
          [
            'relationship_type' => 'combat',
            'relationship_status' => 'known',
            'idempotency_key' => sha1(json_encode([
              'encounter_id' => $encounter_id,
              'event_type' => 'negative_effect_spell',
              'source' => $actor_id,
              'target' => (string) $target_id,
              'spell_name' => (string) $spell_name,
              'cast_at_level' => $cast_at_level,
              'is_focus_spell' => TRUE,
              'attack_result' => $attack_result,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'is_negative_effect_spell' => !empty($params['is_negative_effect_spell']),
            'requires_attack_roll' => $requires_attack_roll,
          ]
        );
      }

      if ($target_id !== NULL && trim($target_id) !== '') {
        $damage_resolution = $this->applySupportedSpellDamageToEncounterTarget(
          $encounter_id,
          $actor_id,
          (string) $target_id,
          (string) $spell_id,
          (string) $spell_name,
          (int) $slot_level,
          (int) ($params['action_cost'] ?? 1),
          $target_participant
        );
        if (!empty($damage_resolution['error'])) {
          return [
            'cast' => FALSE,
            'error' => (string) $damage_resolution['error'],
            'mutations' => [],
            'narration' => NULL,
          ];
        }
        if (is_numeric($damage_resolution['damage'] ?? NULL)) {
          $spell_damage = (int) $damage_resolution['damage'];
        }
        $spell_damage_type = is_string($damage_resolution['damage_type'] ?? NULL) ? (string) $damage_resolution['damage_type'] : NULL;
        $spell_damage_mutations = is_array($damage_resolution['mutations'] ?? NULL) ? $damage_resolution['mutations'] : [];
        $spell_damage_packet = is_array($damage_resolution['damage_packet'] ?? NULL) ? $damage_resolution['damage_packet'] : NULL;
        $target_defeated = !empty($damage_resolution['is_defeated']);
        $missiles_fired = is_numeric($damage_resolution['missiles_fired'] ?? NULL) ? (int) $damage_resolution['missiles_fired'] : NULL;
        $damage_disposition_change = $this->applyDamageTriggeredHostilityConsequences(
          $campaign_id > 0 ? $campaign_id : NULL,
          $encounter_id,
          $actor_id,
          (string) $target_id,
          $spell_damage_packet
        );
        if ($damage_disposition_change !== NULL) {
          $disposition_change = $damage_disposition_change;
        }
      }

      return [
        'cast' => TRUE,
        'execution_request' => $this->combatResolutionContractService->buildCombatExecutionRequest(
          'cast_spell',
          $actor_id,
          $target_id,
          $params
        ),
        'target_id' => $target_id,
        'spell' => $spell_name,
        'is_focus_spell' => TRUE,
        'focus_points_remaining' => max(0, (int) ($focus_remaining ?? 0)),
        'spell_dc' => $spell_dc,
        'attack_result' => $attack_result,
        'damage' => $spell_damage,
        'damage_type' => $spell_damage_type,
        'damage_packet' => $spell_damage_packet,
        'missiles_fired' => $missiles_fired,
        'is_defeated' => $target_defeated,
        'disposition_change' => $disposition_change,
        'resolution_envelope' => $this->combatResolutionContractService->buildResolutionEnvelope(
          $this->combatResolutionContractService->buildCombatExecutionRequest(
            'cast_spell',
            $actor_id,
            $target_id,
            $params
          ),
          array_values(array_filter([$spell_damage_packet], 'is_array')),
          [
            'spell' => $spell_name,
            'damage' => $spell_damage,
            'damage_type' => $spell_damage_type,
            'missiles_fired' => $missiles_fired,
            'is_defeated' => $target_defeated,
          ]
        ),
        'narration' => NULL,
        'mutations' => array_merge([['entity_id' => $actor_id]], $spell_damage_mutations),
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

    if ($target_id !== NULL && trim($target_id) !== '') {
      $damage_resolution = $this->applySupportedSpellDamageToEncounterTarget(
        $encounter_id,
        $actor_id,
        (string) $target_id,
        (string) $spell_id,
        (string) $spell_name,
        (int) $slot_level,
        (int) ($params['action_cost'] ?? 1)
      );
      if (!empty($damage_resolution['error'])) {
        return [
          'cast' => FALSE,
          'error' => (string) $damage_resolution['error'],
          'mutations' => [],
          'narration' => NULL,
        ];
      }
      if (is_numeric($damage_resolution['damage'] ?? NULL)) {
        $spell_damage = (int) $damage_resolution['damage'];
      }
      $spell_damage_type = is_string($damage_resolution['damage_type'] ?? NULL) ? (string) $damage_resolution['damage_type'] : NULL;
      $spell_damage_mutations = is_array($damage_resolution['mutations'] ?? NULL) ? $damage_resolution['mutations'] : [];
      $spell_damage_packet = is_array($damage_resolution['damage_packet'] ?? NULL) ? $damage_resolution['damage_packet'] : NULL;
      $target_defeated = !empty($damage_resolution['is_defeated']);
      $missiles_fired = is_numeric($damage_resolution['missiles_fired'] ?? NULL) ? (int) $damage_resolution['missiles_fired'] : NULL;
      $damage_disposition_change = $this->applyDamageTriggeredHostilityConsequences(
        $campaign_id > 0 ? $campaign_id : NULL,
        $encounter_id,
        $actor_id,
        (string) $target_id,
        $spell_damage_packet
      );
      if ($damage_disposition_change !== NULL) {
        $disposition_change = $damage_disposition_change;
      }
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

    if ($should_trigger_negative_spell_disposition) {
      $disposition_change = $this->applyClassifiedDispositionMutation(
        $campaign_id > 0 ? $campaign_id : NULL,
        'cast_spell',
        $actor_id,
        $target_id,
        sprintf('Encounter spell cast against %s: %s', (string) $target_id, (string) $spell_name),
        [
          'relationship_type' => 'combat',
          'relationship_status' => 'known',
          'idempotency_key' => sha1(json_encode([
            'encounter_id' => $encounter_id,
            'event_type' => 'negative_effect_spell',
            'source' => $actor_id,
            'target' => (string) $target_id,
            'spell_name' => (string) $spell_name,
            'cast_at_level' => $slot_level,
            'attack_result' => $attack_result,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
          'is_negative_effect_spell' => !empty($params['is_negative_effect_spell']),
          'requires_attack_roll' => $requires_attack_roll,
        ]
      );
    }

    return [
      'cast' => TRUE,
      'execution_request' => $this->combatResolutionContractService->buildCombatExecutionRequest(
        'cast_spell',
        $actor_id,
        $target_id,
        $params
      ),
      'target_id' => $target_id,
      'spell' => $spell_name,
      'spell_level' => $spell_level,
      'cast_at_level' => $slot_level,
      'heightened' => $slot_level > $spell_level,
      'slots_remaining' => max(0, (int) ($slots_remaining ?? ($slots_avail - 1))),
      'spell_dc' => $spell_dc,
      'spell_attack_modifier' => $spell_attack_mod,
      'attack_result' => $attack_result,
      'damage' => $spell_damage,
      'damage_type' => $spell_damage_type,
      'damage_packet' => $spell_damage_packet,
      'missiles_fired' => $missiles_fired,
      'is_defeated' => $target_defeated,
      'disposition_change' => $disposition_change,
      'resolution_envelope' => $this->combatResolutionContractService->buildResolutionEnvelope(
        $this->combatResolutionContractService->buildCombatExecutionRequest(
          'cast_spell',
          $actor_id,
          $target_id,
          $params
        ),
        array_values(array_filter([$spell_damage_packet], 'is_array')),
        [
          'spell' => $spell_name,
          'damage' => $spell_damage,
          'damage_type' => $spell_damage_type,
          'missiles_fired' => $missiles_fired,
          'is_defeated' => $target_defeated,
        ]
      ),
      'metamagic_applied' => $metamagic_applied,
      'incapacitation_note' => $incapacitation_note,
      'avert_gaze_note' => $avert_gaze_note,
      'narration' => NULL,
      'mutations' => array_merge([['entity_id' => $actor_id]], $spell_damage_mutations),
    ];
  }

  /**
   * Apply direct spell damage for currently supported deterministic spells.
   */
  protected function applySupportedSpellDamageToEncounterTarget(
    int $encounter_id,
    string $source_actor_id,
    string $target_id,
    string $spell_id,
    string $spell_name,
    int $cast_rank,
    int $action_cost,
    ?array $target_participant_hint = NULL
  ): array {
    return $this->unifiedDamageEngine->applySupportedSpellDamageToEncounterTarget(
      $encounter_id,
      $source_actor_id,
      $target_id,
      $spell_id,
      $spell_name,
      $cast_rank,
      $action_cost,
      $target_participant_hint
    );
  }

  protected function findEncounterParticipantByEntityId(array $encounter, string $entity_id): ?array {
    $needle_raw = trim($entity_id);
    if ($needle_raw === '') {
      return NULL;
    }
    $needle_canonical = $this->normalizeEntityRefKey($needle_raw);

    foreach (($encounter['participants'] ?? []) as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      foreach ($this->collectParticipantEntityRefs($participant) as $candidate) {
        if ($candidate === $needle_raw || $this->normalizeEntityRefKey($candidate) === $needle_canonical) {
          return $participant;
        }
      }
    }

    return NULL;
  }

  /**
   * @return string[]
   */
  protected function collectParticipantEntityRefs(array $participant): array {
    $candidates = [];
    foreach (['entity_id', 'entity_ref'] as $key) {
      $value = $participant[$key] ?? NULL;
      if (is_scalar($value)) {
        $normalized = trim((string) $value);
        if ($normalized !== '') {
          $candidates[] = $normalized;
        }
      }
    }

    $entity_ref = $participant['entity_ref'] ?? NULL;
    if (is_scalar($entity_ref)) {
      $decoded = json_decode((string) $entity_ref, TRUE);
      if (is_array($decoded)) {
        foreach (['entity_id', 'instance_id', 'entity_instance_id', 'content_id', 'id'] as $key) {
          $value = $decoded[$key] ?? NULL;
          if (is_scalar($value)) {
            $normalized = trim((string) $value);
            if ($normalized !== '') {
              $candidates[] = $normalized;
            }
          }
        }
      }
    }

    $unique = [];
    $seen = [];
    foreach ($candidates as $candidate) {
      if (!isset($seen[$candidate])) {
        $seen[$candidate] = TRUE;
        $unique[] = $candidate;
      }
    }

    return $unique;
  }

  protected function normalizeEntityRefKey(string $value): string {
    $value = strtolower(trim($value));
    if ($value === '') {
      return '';
    }
    $normalized = preg_replace('/[^a-z0-9]+/', '', $value);
    if (is_string($normalized) && $normalized !== '') {
      return $normalized;
    }

    return $value;
  }

  /**
   * Resolve participant axial hex from encounter participant payload.
   *
   * @return array{q:int, r:int}|null
   *   Normalized hex payload or NULL when unavailable.
   */
  protected function resolveParticipantHex(array $participant): ?array {
    $q = $participant['position_q'] ?? NULL;
    $r = $participant['position_r'] ?? NULL;
    if (!is_scalar($q) || !is_scalar($r)) {
      return NULL;
    }
    return [
      'q' => (int) $q,
      'r' => (int) $r,
    ];
  }

  /**
   * Apply immediate hostility consequences from authoritative damage packets.
   */
  protected function applyDamageTriggeredHostilityConsequences(
    ?int $campaign_id,
    int $encounter_id,
    string $attacker_actor_ref,
    string $victim_actor_ref,
    ?array $damage_packet
  ): ?array {
    if (
      $campaign_id === NULL
      || $campaign_id <= 0
      || $encounter_id <= 0
      || $this->relationshipAttitudeService === NULL
      || $this->relationshipsActorIdentityResolverService === NULL
    ) {
      return NULL;
    }
    $attacker_actor_ref = trim($attacker_actor_ref);
    $victim_actor_ref = trim($victim_actor_ref);
    if ($attacker_actor_ref === '' || $victim_actor_ref === '' || $attacker_actor_ref === $victim_actor_ref) {
      return NULL;
    }

    $damage_amount = isset($damage_packet['amount']) && is_numeric($damage_packet['amount'])
      ? (int) $damage_packet['amount']
      : 0;
    if ($damage_amount <= 0) {
      return NULL;
    }

    $victim_identity = $this->relationshipsActorIdentityResolverService->resolveInstitutionActorIdentity($campaign_id, $victim_actor_ref);
    $attacker_identity = $this->relationshipsActorIdentityResolverService->resolveInstitutionActorIdentity($campaign_id, $attacker_actor_ref);
    if (!is_array($victim_identity) || !is_array($attacker_identity)) {
      return NULL;
    }

    $victim_undead_membership_subject = $this->actorHasInstitutionMembership($campaign_id, $victim_identity, 'institution_ancestry_undead')
      ? 'institution_ancestry_undead'
      : '';
    $witnesses = [];
    $this->upsertDamageHostilityEdge(
      $campaign_id,
      $victim_identity,
      $attacker_identity,
      $encounter_id,
      $damage_amount,
      TRUE,
      $victim_undead_membership_subject
    );
    $witnesses[] = $victim_actor_ref;

    if ($victim_undead_membership_subject !== '') {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participants = is_array($encounter['participants'] ?? NULL) ? $encounter['participants'] : [];
      foreach ($participants as $participant) {
        if (!is_array($participant)) {
          continue;
        }
        $peer_ref = trim((string) ($participant['entity_id'] ?? ''));
        if ($peer_ref === '' || $peer_ref === $victim_actor_ref || $peer_ref === $attacker_actor_ref) {
          continue;
        }
        if (!empty($participant['is_defeated'])) {
          continue;
        }
        $peer_identity = $this->relationshipsActorIdentityResolverService->resolveInstitutionActorIdentity($campaign_id, $peer_ref);
        if (!is_array($peer_identity) || !$this->actorHasInstitutionMembership($campaign_id, $peer_identity, 'institution_ancestry_undead')) {
          continue;
        }
        $this->upsertDamageHostilityEdge(
          $campaign_id,
          $peer_identity,
          $attacker_identity,
          $encounter_id,
          $damage_amount,
          FALSE,
          $victim_undead_membership_subject
        );
        $witnesses[] = $peer_ref;
      }
    }

    return [
      'source_actor_ref' => $victim_actor_ref,
      'target_actor_ref' => $attacker_actor_ref,
      'event_type' => 'damage_application_hostility_override',
      'reason' => 'Damage packet triggered immediate hostility for victim and undead peers.',
      'changed' => TRUE,
      'classification' => [
        'matched' => TRUE,
        'damage_amount' => $damage_amount,
        'witnesses' => array_values(array_unique($witnesses)),
      ],
    ];
  }

  /**
   * Persist one directed immediate-hostility edge after combat damage.
   *
   * @param array<string,string> $source_identity
   * @param array<string,string> $target_identity
   */
  protected function upsertDamageHostilityEdge(
    int $campaign_id,
    array $source_identity,
    array $target_identity,
    int $encounter_id,
    int $damage_amount,
    bool $is_direct_victim,
    string $witness_institution_subject_id = ''
  ): void {
    if ($this->relationshipAttitudeService === NULL) {
      return;
    }
    $source_type = trim((string) ($source_identity['source_type'] ?? ''));
    $source_id = trim((string) ($source_identity['source_id'] ?? ''));
    $target_type = trim((string) ($target_identity['source_type'] ?? ''));
    $target_id = trim((string) ($target_identity['source_id'] ?? ''));
    if ($source_type === '' || $source_id === '' || $target_type === '' || $target_id === '') {
      return;
    }
    $this->relationshipAttitudeService->upsertRelationshipAttitude(
      $campaign_id,
      $source_type,
      $source_id,
      $target_type,
      $target_id,
      DispositionAuthorityContract::LABEL_HOSTILE,
      'combat',
      'known',
      [
        'score' => -100,
        'score_source' => 'relationship_state_score',
        'trigger' => 'damage_application',
        'encounter_id' => $encounter_id,
        'damage_amount' => $damage_amount,
        'peer_witness' => !$is_direct_victim,
        'witness_institution_subject_id' => trim($witness_institution_subject_id),
      ]
    );
  }

  /**
   * Resolve whether an actor identity carries a specific institution membership.
   *
   * @param array<string,string> $identity
   */
  protected function actorHasInstitutionMembership(int $campaign_id, array $identity, string $target_subject_id): bool {
    if ($this->institutionMembershipService === NULL) {
      return FALSE;
    }
    $source_type = trim((string) ($identity['source_type'] ?? ''));
    $source_id = trim((string) ($identity['source_id'] ?? ''));
    if ($campaign_id <= 0 || $source_type === '' || $source_id === '' || trim($target_subject_id) === '') {
      return FALSE;
    }
    $memberships = $this->institutionMembershipService->listActorInstitutionMemberships($campaign_id, $source_type, $source_id);
    foreach ($memberships as $membership) {
      if (!is_array($membership)) {
        continue;
      }
      if (trim((string) ($membership['target_id'] ?? '')) === $target_subject_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Apply a classifier-scoped mutation for one encounter action.
   */
  protected function applyClassifiedDispositionMutation(
    ?int $campaign_id,
    string $action_type,
    string $source_actor_ref,
    ?string $target_actor_ref,
    string $event_description,
    array $context = []
  ): ?array {
    if ($campaign_id === NULL || $campaign_id <= 0 || $this->dispositionMutationClassifierService === NULL) {
      return NULL;
    }

    $source_actor_ref = trim($source_actor_ref);
    $target_actor_ref = trim((string) $target_actor_ref);
    if ($source_actor_ref === '' || $target_actor_ref === '' || $this->actorDispositionService === NULL) {
      return NULL;
    }

    $before_summary = $this->actorDispositionService->getDispositionSummary($campaign_id, $source_actor_ref, [], FALSE);
    $before_attitude = strtolower(trim((string) ($before_summary['current_attitude'] ?? '')));
    $before_score = isset($before_summary['current_score']) && is_numeric($before_summary['current_score'])
      ? (int) round((float) $before_summary['current_score'])
      : NULL;

    $classification = $this->dispositionMutationClassifierService->classifyActionMutationScope(
      $campaign_id,
      $action_type,
      $context + [
        'source_entity_ref' => $source_actor_ref,
        'target_entity_ref' => $target_actor_ref,
      ]
    );
    if (empty($classification['matched'])) {
      return NULL;
    }
    $event_type = trim((string) ($classification['event_type'] ?? ''));
    if ($event_type === '') {
      return NULL;
    }

    $after_summary = $this->applyDispositionTriggerMutation(
      $campaign_id,
      $source_actor_ref,
      $target_actor_ref,
      $event_type,
      $event_description,
      $context + ['trigger_classification' => $classification]
    );
    if (!is_array($after_summary)) {
      return NULL;
    }

    $after_attitude = strtolower(trim((string) ($after_summary['current_attitude'] ?? '')));
    $after_score = isset($after_summary['current_score']) && is_numeric($after_summary['current_score'])
      ? (int) round((float) $after_summary['current_score'])
      : NULL;

    return [
      'source_actor_ref' => $source_actor_ref,
      'target_actor_ref' => $target_actor_ref,
      'event_type' => $event_type,
      'reason' => $event_description,
      'changed' => $before_attitude !== $after_attitude || $before_score !== $after_score,
      'before' => [
        'attitude' => $before_attitude,
        'score' => $before_score,
      ],
      'after' => [
        'attitude' => $after_attitude,
        'score' => $after_score,
      ],
      'classification' => $classification,
    ];
  }

  /**
   * Apply one disposition-trigger mutation from encounter action execution.
   */
  protected function applyDispositionTriggerMutation(
    ?int $campaign_id,
    string $source_actor_ref,
    ?string $target_actor_ref,
    string $event_type,
    string $event_description,
    array $context = []
  ): ?array {
    if ($this->actorDispositionService === NULL || $campaign_id === NULL || $campaign_id <= 0) {
      return NULL;
    }
    $source_actor_ref = trim($source_actor_ref);
    $target_actor_ref = trim((string) $target_actor_ref);
    if ($source_actor_ref === '' || $target_actor_ref === '') {
      return NULL;
    }

    return $this->actorDispositionService->applyDispositionEvent(
      $campaign_id,
      $source_actor_ref,
      $event_type,
      $event_description,
      $context + [
        'target_entity_ref' => $target_actor_ref,
        'event_timestamp' => time(),
      ]
    );
  }

}
