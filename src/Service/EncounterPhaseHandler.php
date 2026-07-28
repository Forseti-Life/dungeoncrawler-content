<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles canonical action execution during the Encounter phase.
 *
 * Scope:
 * - Enforce initiative, turn order, and round progression.
 * - Enforce per-actor action legality/economy and apply encounter mutations.
 * - Generate authoritative encounter events/transcript metadata for chat output.
 * - Treat all actors uniformly in the turn loop (NPC, enemy, PC actor records).
 *
 * Player behavior note:
 * - The PC actor remains in the same authoritative turn system.
 * - Player room chat is accepted as canonical Talk actions on that actor's turn,
 *   while non-player turns are resolved by authoritative handler logic.
 */
class EncounterPhaseHandler implements EncounterMasterInterface {
  protected const ROOM_SCENE_ERR_MISSING_PLAYER_PARTICIPANT = 'room_scene_initiative_missing_player_participant';
  protected const ROOM_SCENE_ERR_RESEED_MISSING_ROOM = 'room_scene_reseed_failed_missing_room';
  protected const ROOM_SCENE_ERR_RESEED_NO_PLAYER_CANDIDATE = 'room_scene_reseed_failed_no_player_candidate';
  protected const ROOM_SCENE_ERR_START_MISSING_PLAYER = 'room_scene_start_failed_missing_player_participant';
  protected const NAVIGATION_TIMING_SLOW_THRESHOLD_MS = 500;
  protected const SOCIAL_PROGRESSION_POLICY_VERSION = 1;
  protected const MAX_LEAD_SEEK_INTERACTIONS_PER_ACTOR_PER_ROOM = 3;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEngine
   */
  protected CombatEngine $combatEngine;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected CombatEncounterStore $encounterStore;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected HPManager $hpManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected ConditionManager $conditionManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatCalculator
   */
  protected CombatCalculator $combatCalculator;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGenerationService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService
   */
  protected EncounterAiIntegrationService $encounterAiService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\AiGmService
   */
  protected AiGmService $aiGmService;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NpcPsychologyService
   */
  protected NpcPsychologyService $psychologyService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NarrationEngine|null
   */
  protected ?NarrationEngine $narrationEngine;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\MovementResolverService|null
   */
  protected ?MovementResolverService $movementResolver;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HazardService
   */
  protected HazardService $hazardService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\MagicItemService
   */
  protected MagicItemService $magicItemService;

  protected CharacterStateService $characterStateService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\SpellCatalogService
   */
  protected SpellCatalogService $spellCatalog;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\RoomChatService
   */
  protected RoomChatService $roomChatService;

  protected ?ExplorationPhaseHandler $explorationPhaseHandler;

  protected ?NavigationService $navigationService;
  protected ?NavigationRuntimeService $navigationRuntime;
  protected ?RuntimeGraphAssemblerService $runtimeGraphAssembler;
  protected ?H3ProjectionQueueService $h3ProjectionQueue;

  /**
   * Shared actor action-availability resolver.
   */
  protected ActorActionAvailabilityService $actionAvailability;

  /**
   * Shared encounter actor context builder.
   */
  protected EncounterActorContextBuilder $actorContextBuilder;

  /**
   * Shared actor autoplay coordinator.
   */
  protected ActorAutoplayCoordinator $actorAutoplayCoordinator;

  /**
   * Shared room-scene encounter coordinator.
   */
  protected RoomSceneEncounterCoordinator $roomSceneEncounterCoordinator;

  /**
   * Shared canonical projection coordinator.
   */
  protected CanonicalProjectionService $canonicalProjectionService;

  /**
   * Shared encounter action executor.
   */
  protected EncounterActionExecutor $encounterActionExecutor;

  /**
   * Shared encounter intent router.
   */
  protected EncounterIntentRouter $encounterIntentRouter;

  /**
   * Canonical client-facing encounter action definitions.
   */
  protected const CLIENT_ACTION_DEFINITIONS = [
    'strike' => [
      'label' => 'Strike',
      'cost' => 1,
      'category' => 'offense',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
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
    'cast_spell' => [
      'label' => 'Cast Spell',
      'cost' => 2,
      'category' => 'magic',
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
    'transition' => [
      'label' => 'Move to connected room',
      'cost' => 0,
      'category' => 'navigation',
      'requires_turn' => FALSE,
      'targeting' => 'connected_room',
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
    'delay' => [
      'label' => 'Delay',
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
  ];

  /**
   * Default time for connected-room movement when connection metadata is absent.
   */
  protected const DEFAULT_ROOM_TRANSITION_SECONDS = 60;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CombatEngine $combat_engine,
    CombatEncounterStore $encounter_store,
    HPManager $hp_manager,
    ConditionManager $condition_manager,
    CombatCalculator $combat_calculator,
    NumberGenerationService $number_generation_service,
    EncounterAiIntegrationService $encounter_ai_service,
    AiGmService $ai_gm_service,
    ConfigFactoryInterface $config_factory,
    NpcPsychologyService $psychology_service = NULL,
    ?NarrationEngine $narration_engine = NULL,
    ?MovementResolverService $movement_resolver = NULL,
    ?HazardService $hazard_service = NULL,
    ?MagicItemService $magic_item_service = NULL,
    ?CharacterStateService $character_state_service = NULL,
    ?SpellCatalogService $spell_catalog = NULL,
    ?RoomChatService $room_chat_service = NULL,
    ?ExplorationPhaseHandler $exploration_phase_handler = NULL,
    ?NavigationService $navigation_service = NULL,
    ?ActorActionAvailabilityService $action_availability = NULL,
    ?NavigationRuntimeService $navigation_runtime = NULL,
    ?EncounterActorContextBuilder $actor_context_builder = NULL,
    ?ActorAutoplayCoordinator $actor_autoplay_coordinator = NULL,
    ?RoomSceneEncounterCoordinator $room_scene_encounter_coordinator = NULL,
    ?CanonicalProjectionService $canonical_projection_service = NULL,
    ?EncounterActionExecutor $encounter_action_executor = NULL,
    ?EncounterIntentRouter $encounter_intent_router = NULL,
    ?RuntimeGraphAssemblerService $runtime_graph_assembler = NULL,
    ?H3ProjectionQueueService $h3_projection_queue = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->combatEngine = $combat_engine;
    $this->encounterStore = $encounter_store;
    $this->hpManager = $hp_manager;
    $this->conditionManager = $condition_manager;
    $this->combatCalculator = $combat_calculator;
    $this->numberGenerationService = $number_generation_service;
    $this->encounterAiService = $encounter_ai_service;
    $this->aiGmService = $ai_gm_service;
    $this->configFactory = $config_factory;
    $this->psychologyService = $psychology_service ?? new NpcPsychologyService($database, $logger_factory);
    $this->narrationEngine = $narration_engine;
    $this->movementResolver = $movement_resolver;
    $this->hazardService = $hazard_service ?? new HazardService($number_generation_service);
    $this->magicItemService = $magic_item_service ?? new MagicItemService($number_generation_service);
    $this->characterStateService = $character_state_service ?? \Drupal::service('dungeoncrawler_content.character_state');
    $this->spellCatalog = $spell_catalog ?? new SpellCatalogService();
    $this->roomChatService = $room_chat_service ?? \Drupal::service('dungeoncrawler_content.room_chat_service');
    $this->explorationPhaseHandler = $exploration_phase_handler;
    $this->navigationService = $navigation_service;
    $this->actionAvailability = $action_availability ?? new ActorActionAvailabilityService();
    $this->navigationRuntime = $navigation_runtime;
    $this->runtimeGraphAssembler = $runtime_graph_assembler;
    $this->h3ProjectionQueue = $h3_projection_queue;
    $this->actorContextBuilder = $actor_context_builder ?? new EncounterActorContextBuilder(
      $this->psychologyService,
      $this->actionAvailability
    );
    $this->actorAutoplayCoordinator = $actor_autoplay_coordinator ?? new ActorAutoplayCoordinator(
      $this->encounterAiService,
      $this->hazardService,
      $this->roomChatService,
      $this->configFactory,
      $logger_factory
    );
    $this->roomSceneEncounterCoordinator = $room_scene_encounter_coordinator ?? new RoomSceneEncounterCoordinator();
    $this->canonicalProjectionService = $canonical_projection_service ?? new CanonicalProjectionService(
      $this->encounterStore,
      $this->characterStateService,
      $logger_factory
    );
    $this->encounterActionExecutor = $encounter_action_executor ?? new EncounterActionExecutor(
      $this->encounterStore,
      $this->combatEngine,
      $this->magicItemService,
      $this->roomChatService,
      $this->characterStateService,
      $this->spellCatalog,
      $this->numberGenerationService,
      $this->combatCalculator,
      $this->canonicalProjectionService,
      $logger_factory,
      $this->movementResolver
    );
    $this->encounterIntentRouter = $encounter_intent_router ?? new EncounterIntentRouter();
  }

  /**
   * {@inheritdoc}
   */
  public function getPhaseName(): string {
    return 'encounter';
  }

  /**
   * {@inheritdoc}
   */
  public function getLegalIntents(): array {
    return [
      'strike',
      'stride',
      'cast_spell',
      'interact',
      'search',
      'talk',
      'skill',
      'feat',
      'consume_item',
      'transition',
      'end_turn',
      'choose_not_to_act',
      'delay',
      'delay_reenter',
      'ready',
      'reaction',
      'aid',
      'aid_setup',
      'crawl',
      'drop_prone',
      'escape',
      'leap',
      'release',
      'seek',
      'sense_motive',
      'stand',
      'step',
      'take_cover',
      // REQ 2221-2223: Specialty movement.
      'burrow',
      'fly',
      // REQ 2225: Mount/dismount.
      'mount',
      'dismount',
      // REQ 2227: Raise a Shield.
      'raise_shield',
      // REQ 2220: Avert Gaze.
      'avert_gaze',
      // REQ 2226: Point Out.
      'point_out',
      // REQ 2219: Arrest a Fall (reaction).
      'arrest_fall',
      // REQ 2224: Grab an Edge (reaction).
      'grab_edge',
      // REQ 2231-2232: Shield Block (reaction).
      'shield_block',
      // REQ 2228-2230: Attack of Opportunity (fighter reaction).
      'attack_of_opportunity',
      // REQ 2280: Hero Point reroll (free action during attack).
      'hero_point_reroll',
      // REQ 2281: Spend all Hero Points to stabilize (removes dying, no wounded).
      'heroic_recovery_all_points',
      // REQ 1619–1659: Athletics skill actions.
      'climb',
      'force_open',
      'grapple',
      'high_jump',
      'long_jump',
      'shove',
      'swim',
      'trip',
      'disarm',
      // REQ 1688–1694: Medicine skill actions (encounter-phase).
      'administer_first_aid',
      'treat_poison',
      // REQ: Battle Medicine [1 action, General Skill Feat, Trained Medicine].
      'battle_medicine',
      // REQ 1591–1594, 2329: Recall Knowledge [1 action, Secret].
      'recall_knowledge',
      // REQ 1715–1722: Stealth skill actions [encounter-phase].
      'hide',
      'sneak',
      'conceal_object',
      // REQ 1747–1756: Thievery skill actions [encounter-phase].
      'palm_object',
      'steal',
      'disable_device',
      'pick_lock',
      // REQ 1591: Acrobatics — Balance across difficult terrain.
      'balance',
      // REQ 1594: Acrobatics — Tumble Through an enemy's space.
      'tumble_through',
      // REQ 1598: Acrobatics — Maneuver in Flight (1 action, aerial combat).
      'maneuver_in_flight',
      // REQ 1657: Deception — Feint (2 actions).
      'feint',
      // REQ 1660: Deception — Create a Diversion (1 action).
      'create_diversion',
      // REQ 1677: Diplomacy — Request (1 action).
      'request',
      // REQ 1683: Intimidation — Demoralize (1 action).
      'demoralize',
      // REQ 1700: Nature — Command an Animal (encounter variant, 1 action).
      'command_animal',
      // REQ 1706: Performance — Perform (encounter variant, 1 action).
      'perform',
      // REQ 2373–2396: Hazard actions [encounter-phase].
      'disable_hazard',
      'attack_hazard',
      'counteract_hazard',
      // REQ 2410–2425: Activate magic item (encounter phase).
      'activate_item',
      // REQ 2416–2420: Sustain an activation.
      'sustain_activation',
      // REQ 2421–2424: Dismiss an activation.
      'dismiss_activation',
      // dc-cr-spells-ch07: Sustain a spell (Concentrate, 1 action).
      'sustain_spell',
      // dc-cr-spells-ch07: Dismiss a sustained/dismissible spell (Concentrate, 1 action).
      'dismiss_spell',
      // REQ 2478–2490: Cast from scroll.
      'cast_from_scroll',
      // REQ 2511–2520: Cast from staff.
      'cast_from_staff',
      // REQ 2521–2530: Cast from wand.
      'cast_from_wand',
      // REQ 2531–2535: Overcharge wand.
      'overcharge_wand',
      // REQ 2549: Activate talisman.
      'activate_talisman',
      // dc-cr-spells-ch07: Declare metamagic before a cast_spell action.
      'declare_metamagic',
      'treat_wounds',
      'refocus',
      'repair',
      'daily_preparations',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateIntent(array $intent, array $game_state, array $dungeon_data): array {
    $type = $intent['type'] ?? '';

    if (!in_array($type, $this->getLegalIntents(), TRUE)) {
      return [
        'valid' => FALSE,
        'reason' => "Action '$type' is not legal during encounter phase.",
      ];
    }

    if ($type === 'transition') {
      $current_entity = (string) ($game_state['turn']['entity'] ?? '');
      $actor_id = (string) ($intent['actor'] ?? '');
      if ($actor_id !== '' && $current_entity !== '' && $actor_id !== $current_entity) {
        return [
          'valid' => FALSE,
          'reason' => "It is not $actor_id's turn. Current turn: $current_entity.",
        ];
      }
      $target_room = $intent['params']['target_room_id'] ?? NULL;
      if (!is_string($target_room) || trim($target_room) === '') {
        return [
          'valid' => FALSE,
          'reason' => 'Room transition requires params.target_room_id.',
        ];
      }
      $target_room = trim($target_room);
      $room_exists = $this->findRoomById($dungeon_data, $target_room) !== NULL;
      $connection = $this->resolveRoomTransitionCapability($dungeon_data, $target_room, $intent['params'] ?? []);
      if ($connection === NULL) {
        $unreachable_diagnostics = $this->buildTransitionUnreachableDiagnostics($dungeon_data, $target_room);
        $this->logger->warning('Encounter transition validation rejected as unreachable: actor={actor} target_room={target_room} active_room={active_room} room_exists={room_exists} connection_id={connection_id} suggested_via_room={suggested_via_room} available_targets={available_targets}', [
          'actor' => $actor_id,
          'target_room' => $target_room,
          'active_room' => (string) ($dungeon_data['active_room_id'] ?? ''),
          'room_exists' => $room_exists ? 1 : 0,
          'connection_id' => (string) ($intent['params']['connection_id'] ?? ''),
          'suggested_via_room' => (string) ($unreachable_diagnostics['suggested_via_room_id'] ?? ''),
          'available_targets' => implode(', ', (array) ($unreachable_diagnostics['available_targets'] ?? [])),
        ]);
        $reason = "Room '$target_room' is not reachable from the active room.";
        $suggested_via_room_id = (string) ($unreachable_diagnostics['suggested_via_room_id'] ?? '');
        if ($suggested_via_room_id !== '') {
          $reason .= sprintf(
            " Route hint: transition to '%s' first, then to '%s'.",
            $this->resolveRoomLabelById($dungeon_data, $suggested_via_room_id),
            $this->resolveRoomLabelById($dungeon_data, $target_room)
          );
        }
        return [
          'valid' => FALSE,
          'reason' => $reason,
        ];
      }
      if (empty($connection['available'])) {
        $this->logger->warning('Encounter transition validation rejected as blocked: actor={actor} target_room={target_room} blocked_reason={blocked_reason} connection_id={connection_id}', [
          'actor' => $actor_id,
          'target_room' => $target_room,
          'blocked_reason' => (string) ($connection['blocked_reason'] ?? 'blocked'),
          'connection_id' => (string) ($connection['connection_id'] ?? ($intent['params']['connection_id'] ?? '')),
        ]);
        return [
          'valid' => FALSE,
          'reason' => sprintf(
            "Room '%s' is not available for transition: %s.",
            $target_room,
            (string) ($connection['blocked_reason'] ?? 'blocked')
          ),
        ];
      }
      if (!$room_exists) {
        $this->logger->info('Encounter transition validation approved with deferred room materialization: actor={actor} target_room={target_room} connection_id={connection_id}', [
          'actor' => $actor_id,
          'target_room' => $target_room,
          'connection_id' => (string) ($connection['connection_id'] ?? ($intent['params']['connection_id'] ?? '')),
        ]);
      }
      return ['valid' => TRUE, 'reason' => NULL];
    }

    $encounter_id = isset($game_state['encounter_id']) && is_numeric($game_state['encounter_id'])
      ? (int) $game_state['encounter_id']
      : 0;
    $canonical_turn = $encounter_id > 0 ? $this->loadCanonicalTurnState($encounter_id) : NULL;
    $current_entity = (string) ($canonical_turn['entity_id'] ?? ($game_state['turn']['entity'] ?? ''));
    $actions_remaining = isset($canonical_turn['actions_remaining']) && is_numeric($canonical_turn['actions_remaining'])
      ? (int) $canonical_turn['actions_remaining']
      : (int) ($game_state['turn']['actions_remaining'] ?? 0);
    if ($this->isRoomSceneMode($game_state)) {
      $room_scene_actions = array_merge(
        ['talk', 'search', 'interact', 'delay', 'end_turn', 'choose_not_to_act'],
        $this->getRestActionTypes()
      );
      if (!in_array($type, $room_scene_actions, TRUE)) {
        return [
          'valid' => FALSE,
          'reason' => "Action '$type' is not legal during room-scene encounter.",
        ];
      }
      if (
        $current_entity === '' ||
        empty($game_state['round']) ||
        !is_array($game_state['initiative_order'] ?? NULL) ||
        $game_state['initiative_order'] === []
      ) {
        return [
          'valid' => FALSE,
          'reason' => 'Encounter room-scene context is incomplete (missing round/turn/initiative).',
        ];
      }

      $actor_id = $intent['actor'] ?? NULL;
      if ($actor_id && $current_entity && $actor_id !== $current_entity) {
        return [
          'valid' => FALSE,
          'reason' => "It is not $actor_id's turn. Current turn: $current_entity.",
        ];
      }
      $available_actions = $this->getAvailableActions($game_state, $dungeon_data, $actor_id ?: NULL);
      if (!in_array($type, $available_actions, TRUE)) {
        return [
          'valid' => FALSE,
          'reason' => "Action '$type' is not currently available for this actor.",
        ];
      }

      if ($type === 'talk' && $this->isLeadSeekingAutomationTalkIntent($intent, $game_state)) {
        $lead_source_id = $this->resolveLeadSeekTalkTargetEntityId($intent);
        $room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ($dungeon_data['active_room_id'] ?? '')));
        if ($this->isActorLeadSourceExhaustedForRoom($game_state, $lead_source_id, $room_id)) {
          return [
            'valid' => FALSE,
            'reason' => sprintf(
              "Lead-seeking talk cap reached for lead source '%s' in room '%s'.",
              $lead_source_id,
              $room_id !== '' ? $room_id : 'unknown'
            ),
          ];
        }
      }

      return ['valid' => TRUE, 'reason' => NULL];
    }

    if (!$encounter_id) {
      return [
        'valid' => FALSE,
        'reason' => 'No active canonical encounter.',
      ];
    }

    // Validate it's the actor's turn (except for reactions).
    if (!in_array($type, ['reaction'], TRUE)) {
      $actor_id = $intent['actor'] ?? NULL;

      if ($actor_id && $current_entity && $actor_id !== $current_entity) {
        return [
          'valid' => FALSE,
          'reason' => $type === 'talk'
            ? "It's not your turn, please wait."
            : "It is not $actor_id's turn. Current turn: $current_entity.",
        ];
      }
    }

    $available_actions = $this->getAvailableActions($game_state, $dungeon_data, $intent['actor'] ?? NULL);
    if (!in_array($type, ['reaction', 'transition'], TRUE) && !in_array($type, $available_actions, TRUE)) {
      return [
        'valid' => FALSE,
        'reason' => "Action '$type' is not currently available for this actor.",
      ];
    }

    return ['valid' => TRUE, 'reason' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function processIntent(array $intent, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $result = $this->encounterIntentRouter->routeIntent(
      $intent,
      $game_state,
      $dungeon_data,
      $campaign_id,
      function (array $core_intent, array &$core_game_state, array &$core_dungeon_data, int $core_campaign_id): array {
        return $this->processIntentCore($core_intent, $core_game_state, $core_dungeon_data, $core_campaign_id);
      }
    );
    if (!is_array($result)) {
      throw new \RuntimeException('Encounter intent routing contract violation: routeIntent must return an array.');
    }
    if (!array_key_exists('mutation_envelope', $result)) {
      $result['mutation_envelope'] = $this->buildMutationEnvelopeFromRuntimeContext(
        $campaign_id,
        $game_state,
        $dungeon_data,
        is_array($result['mutations'] ?? NULL) ? $result['mutations'] : []
      );
    }
    return $result;
  }

  /**
   * Build typed mutation envelope from encounter runtime context.
   *
   * @return array<string,mixed>
   *   Mutation envelope compatible with coordinator persistence.
   */
  protected function buildMutationEnvelopeFromRuntimeContext(
    int $campaign_id,
    array $game_state,
    array $dungeon_data,
    array $mutations = []
  ): array {
    [$include_actor_entities, $include_rooms, $include_connections] = $this->inferMutationEnvelopeSliceNeeds($mutations);
    $targets = $this->extractMutationEnvelopeTargets($mutations);
    return [
      'campaign_id' => $campaign_id,
      'active_room_id' => (string) ($dungeon_data['active_room_id'] ?? ''),
      'campaign_state' => $game_state,
      'actor_entities' => $include_actor_entities && is_array($dungeon_data['entities'] ?? NULL)
        ? $this->selectMutationTargetedActorEntities($dungeon_data['entities'], $targets['entity_ids'])
        : [],
      'rooms' => $include_rooms && is_array($dungeon_data['rooms'] ?? NULL)
        ? $this->selectMutationTargetedRooms($dungeon_data['rooms'], $targets['room_ids'])
        : [],
      'connections' => $include_connections
        ? $this->selectMutationTargetedConnections(
          $dungeon_data,
          $targets['connection_ids'],
          $targets['room_ids']
        )
        : [],
    ];
  }

  /**
   * Infer which runtime slices are touched by mutation descriptors.
   *
   * @param array<int,mixed> $mutations
   *   Handler mutation descriptors.
   *
   * @return array{0: bool, 1: bool, 2: bool}
   *   include_actor_entities, include_rooms, include_connections.
   */
  protected function inferMutationEnvelopeSliceNeeds(array $mutations): array {
    if ($mutations === []) {
      return [FALSE, FALSE, FALSE];
    }

    $include_actor_entities = FALSE;
    $include_rooms = FALSE;
    $include_connections = FALSE;

    foreach ($mutations as $mutation) {
      if (!is_array($mutation)) {
        return [TRUE, TRUE, TRUE];
      }
      $field = strtolower(trim((string) ($mutation['field'] ?? $mutation['path'] ?? $mutation['type'] ?? '')));

      $actor_hint = isset($mutation['entity']) || isset($mutation['entity_id'])
        || str_contains($field, 'entity')
        || str_contains($field, 'actor')
        || str_contains($field, 'placement')
        || str_contains($field, 'condition')
        || str_contains($field, 'resource')
        || str_contains($field, 'hp');

      $room_hint = isset($mutation['room_id'])
        || str_contains($field, 'room')
        || str_contains($field, 'hazard')
        || str_contains($field, 'reveal');

      $connection_hint = isset($mutation['connection_id'])
        || str_contains($field, 'connection')
        || str_contains($field, 'door')
        || str_contains($field, 'passable')
        || str_contains($field, 'locked');

      if (!$actor_hint && !$room_hint && !$connection_hint) {
        return [TRUE, TRUE, TRUE];
      }

      $include_actor_entities = $include_actor_entities || $actor_hint;
      $include_rooms = $include_rooms || $room_hint;
      $include_connections = $include_connections || $connection_hint;
    }

    return [$include_actor_entities, $include_rooms, $include_connections];
  }

  /**
   * Extract targeted runtime identifiers from mutation descriptors.
   *
   * @param array<int,mixed> $mutations
   *   Handler mutation descriptors.
   *
   * @return array{entity_ids: array<int,string>, room_ids: array<int,string>, connection_ids: array<int,string>}
   *   Unique target IDs by runtime slice.
   */
  protected function extractMutationEnvelopeTargets(array $mutations): array {
    $entity_ids = [];
    $room_ids = [];
    $connection_ids = [];

    foreach ($mutations as $mutation) {
      if (!is_array($mutation)) {
        continue;
      }

      foreach ([
        $mutation['entity'] ?? NULL,
        $mutation['entity_id'] ?? NULL,
        $mutation['actor'] ?? NULL,
        $mutation['actor_id'] ?? NULL,
      ] as $candidate) {
        $normalized = $this->normalizeMutationTargetId($candidate);
        if ($normalized !== NULL) {
          $entity_ids[$normalized] = TRUE;
        }
      }

      foreach ([
        $mutation['room_id'] ?? NULL,
        $mutation['from_room'] ?? NULL,
        $mutation['to_room'] ?? NULL,
      ] as $candidate) {
        $normalized = $this->normalizeMutationTargetId($candidate);
        if ($normalized !== NULL) {
          $room_ids[$normalized] = TRUE;
        }
      }

      foreach ([
        $mutation['connection_id'] ?? NULL,
      ] as $candidate) {
        $normalized = $this->normalizeMutationTargetId($candidate);
        if ($normalized !== NULL) {
          $connection_ids[$normalized] = TRUE;
        }
      }
    }

    return [
      'entity_ids' => array_keys($entity_ids),
      'room_ids' => array_keys($room_ids),
      'connection_ids' => array_keys($connection_ids),
    ];
  }

  /**
   * Build actor-entity envelope payload with optional ID narrowing.
   *
   * @param array<int,mixed> $entities
   *   Runtime actor entity list.
   * @param array<int,string> $target_entity_ids
   *   Optional list of touched entity IDs.
   *
   * @return array<int,array<string,mixed>>
   *   Normalized actor entities to persist.
   */
  protected function selectMutationTargetedActorEntities(array $entities, array $target_entity_ids): array {
    $normalized_entities = array_values(array_filter($entities, static function ($entity): bool {
      return is_array($entity);
    }));
    if ($target_entity_ids === []) {
      return $normalized_entities;
    }

    $target_lookup = array_fill_keys($target_entity_ids, TRUE);
    $filtered = array_values(array_filter($normalized_entities, static function (array $entity) use ($target_lookup): bool {
      $entity_id = trim((string) (
        $entity['entity_instance_id']
        ?? $entity['instance_id']
        ?? $entity['id']
        ?? ''
      ));
      return $entity_id !== '' && isset($target_lookup[$entity_id]);
    }));

    return $filtered !== [] ? $filtered : $normalized_entities;
  }

  /**
   * Build room envelope payload with optional ID narrowing.
   *
   * @param array<int,mixed> $rooms
   *   Runtime room list.
   * @param array<int,string> $target_room_ids
   *   Optional list of touched room IDs.
   *
   * @return array<int,array<string,mixed>>
   *   Normalized rooms to persist.
   */
  protected function selectMutationTargetedRooms(array $rooms, array $target_room_ids): array {
    $normalized_rooms = array_values(array_filter($rooms, static function ($room): bool {
      return is_array($room);
    }));
    if ($target_room_ids === []) {
      return $normalized_rooms;
    }

    $target_lookup = array_fill_keys($target_room_ids, TRUE);
    $filtered = array_values(array_filter($normalized_rooms, static function (array $room) use ($target_lookup): bool {
      $room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
      return $room_id !== '' && isset($target_lookup[$room_id]);
    }));

    return $filtered !== [] ? $filtered : $normalized_rooms;
  }

  /**
   * Build connection envelope payload with optional ID/room narrowing.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime payload context.
   * @param array<int,string> $target_connection_ids
   *   Optional touched connection IDs.
   * @param array<int,string> $target_room_ids
   *   Optional touched room IDs.
   *
   * @return array<int,array<string,mixed>>
   *   Normalized connections to persist.
   */
  protected function selectMutationTargetedConnections(array $dungeon_data, array $target_connection_ids, array $target_room_ids): array {
    $connections = array_values(array_filter(array_merge(
      is_array($dungeon_data['connections'] ?? NULL) ? $dungeon_data['connections'] : [],
      is_array($dungeon_data['hex_map']['connections'] ?? NULL) ? $dungeon_data['hex_map']['connections'] : []
    ), static function ($connection): bool {
      return is_array($connection);
    }));

    if ($target_connection_ids === [] && $target_room_ids === []) {
      return $connections;
    }

    $connection_lookup = $target_connection_ids !== [] ? array_fill_keys($target_connection_ids, TRUE) : [];
    $room_lookup = $target_room_ids !== [] ? array_fill_keys($target_room_ids, TRUE) : [];

    $filtered = array_values(array_filter($connections, static function (array $connection) use ($connection_lookup, $room_lookup): bool {
      $connection_id = trim((string) (
        $connection['connection_id']
        ?? $connection['id']
        ?? ''
      ));
      if ($connection_id !== '' && isset($connection_lookup[$connection_id])) {
        return TRUE;
      }

      $from_room_id = trim((string) (
        $connection['from_room_id']
        ?? $connection['from']['room_id']
        ?? ''
      ));
      $to_room_id = trim((string) (
        $connection['to_room_id']
        ?? $connection['to']['room_id']
        ?? ''
      ));
      return ($from_room_id !== '' && isset($room_lookup[$from_room_id]))
        || ($to_room_id !== '' && isset($room_lookup[$to_room_id]));
    }));

    return $filtered !== [] ? $filtered : $connections;
  }

  /**
   * Normalize one mutation target identifier.
   */
  protected function normalizeMutationTargetId(mixed $candidate): ?string {
    $value = trim((string) $candidate);
    return $value !== '' ? $value : NULL;
  }

  /**
   * Core encounter intent orchestration implementation.
   */
  protected function processIntentCore(array $intent, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $type = $intent['type'] ?? '';
    $actor_id = $intent['actor'] ?? NULL;
    $target_id = $intent['target'] ?? NULL;
    $params = $intent['params'] ?? [];
    $encounter_id = isset($game_state['encounter_id']) && is_numeric($game_state['encounter_id'])
      ? (int) $game_state['encounter_id']
      : NULL;

    if ($encounter_id) {
      $canonical_turn = $this->loadCanonicalTurnState((int) $encounter_id);
      if ($canonical_turn !== NULL) {
        $this->syncGameStateWithCanonicalTurn($game_state, $canonical_turn);
      }
    }

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;
    $mechanical_result = NULL;
    $time_effects = [];

    // dc-cr-spells-ch07: Metamagic state machine — if a metamagic was declared
    // this turn and the next action is NOT cast_spell, the metamagic is wasted.
    if ($type !== 'cast_spell' && $type !== 'declare_metamagic' &&
        !empty($game_state['turn']['metamagic_pending'][$actor_id])) {
      unset($game_state['turn']['metamagic_pending'][$actor_id]);
    }
    $rest_route = $this->encounterIntentRouter->routeRestAction(
      $type,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      function (?string $aid, ?string $tid, array $action_params, array &$state, array &$dungeon, int $cid): array {
        return $this->processTreatWoundsRestAction($aid, $tid, $action_params, $state, $dungeon, $cid);
      },
      function (?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
        return $this->processRefocusRestAction($aid, $action_params, $state, $dungeon, $cid);
      },
      function (?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
        return $this->processRepairRestAction($aid, $action_params, $state, $dungeon, $cid);
      },
      function (?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
        return $this->processDailyPreparationsRestAction($aid, $action_params, $state, $dungeon, $cid);
      }
    );
    if (!empty($rest_route['handled'])) {
      $result = (array) ($rest_route['result'] ?? []);
      if (!empty($result['error'])) {
        return [
          'success' => FALSE,
          'result' => ['error' => $result['error']],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ];
      }
      $mutations = $result['mutations'] ?? [];
      $events = array_merge($events, $result['events'] ?? []);
      $time_effects = array_merge($time_effects, $result['time_effects'] ?? []);
      $narration = $result['narration'] ?? NULL;
    }
    else {
      $transition_route = $this->encounterIntentRouter->routeTransitionAction(
        $type,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (?string $aid, string $target_room_id, array $transition_params, array &$state, array &$dungeon, int $cid): array {
          return $this->enterRoomFramework($aid, $target_room_id, $transition_params, $state, $dungeon, $cid);
        }
      );
      if (!empty($transition_route['handled'])) {
        $result = (array) ($transition_route['result'] ?? []);
        if (!empty($result['error'])) {
          $this->logger->warning('Encounter transition execution failed: campaign={campaign_id} actor={actor} target_room={target_room} active_room={active_room} error={error}', [
            'campaign_id' => $campaign_id,
            'actor' => (string) ($actor_id ?? ''),
            'target_room' => (string) ($params['target_room_id'] ?? ''),
            'active_room' => (string) ($dungeon_data['active_room_id'] ?? ''),
            'error' => (string) ($result['error'] ?? 'unknown'),
          ]);
          return [
            'success' => FALSE,
            'result' => ['error' => $result['error']],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $mutations = $result['mutations'] ?? [];
        $events = array_merge($events, $result['events'] ?? []);
        $time_effects = array_merge($time_effects, $result['time_effects'] ?? []);
        $narration = $result['narration'] ?? NULL;
      }
      else {
      $primary_route = $this->encounterIntentRouter->routePrimaryCombatAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeStrikeIntentExecution($eid, $aid, $tid, $action_params, $state, $dungeon, $cid);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeStrideIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeCastSpellIntentExecution($eid, $aid, $tid, $action_params, $state, $dungeon, $cid);
        }
      );
      if (!empty($primary_route['handled'])) {
        $payload = (array) ($primary_route['payload'] ?? []);
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? [];
        $events = array_merge($events, (array) ($payload['events'] ?? []));
        if (array_key_exists('narration', $payload)) {
          $narration = $payload['narration'];
        }
      }
      else {
      $skill_feat_route = $this->encounterIntentRouter->routeSkillFeatAction(
        $type,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeSkillIntentExecution($aid, $action_params, $state, $dungeon);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeFeatIntentExecution($aid, $action_params, $state, $dungeon);
        }
      );
      if (!empty($skill_feat_route['handled'])) {
        $payload = (array) ($skill_feat_route['payload'] ?? []);
        $result = (array) ($payload['result'] ?? []);
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $consume_meta_route = $this->encounterIntentRouter->routeConsumableAndMetamagicAction(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeConsumeItemIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeDeclareMetamagicIntentExecution($aid, $action_params, $state);
        }
      );
      if (!empty($consume_meta_route['handled'])) {
        $payload = (array) ($consume_meta_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $interact_talk_route = $this->encounterIntentRouter->routeInteractTalkAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeInteractIntentExecution($eid, $aid, $tid, $action_params, $state, $dungeon, $cid);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeTalkIntentExecution($aid, $tid, $action_params, $state, $dungeon, $cid);
        }
      );
      if (!empty($interact_talk_route['handled'])) {
        $payload = (array) ($interact_talk_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
        if (array_key_exists('narration', $payload)) {
          $narration = $payload['narration'];
        }
      }
      else {
      $turn_flow_route = $this->encounterIntentRouter->routeTurnFlowAction(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (string $action_type, ?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeEndTurnIntentExecution($action_type, $eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeDelayIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?string $aid, array &$state): array {
          return $this->routeDelayReenterIntentExecution($aid, $state);
        }
      );
      if (!empty($turn_flow_route['handled'])) {
        $payload = (array) ($turn_flow_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
        $time_effects = array_merge($time_effects, (array) ($payload['time_effects'] ?? []));
        if (array_key_exists('narration', $payload)) {
          $narration = $payload['narration'];
        }
      }
      else {
      $ready_reaction_route = $this->encounterIntentRouter->routeReadyReactionAction(
        $type,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeReadyIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeReactionIntentExecution($aid, $tid, $action_params, $state);
        }
      );
      if (!empty($ready_reaction_route['handled'])) {
        $payload = (array) ($ready_reaction_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $aid_route = $this->encounterIntentRouter->routeAidAction(
        $type,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeAidSetupIntentExecution($aid, $tid, $action_params, $state);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeAidIntentExecution($aid, $tid, $action_params, $state);
        }
      );
      if (!empty($aid_route['handled'])) {
        $payload = (array) ($aid_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $hero_point_route = $this->encounterIntentRouter->routeHeroPointAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeHeroPointRerollIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, array &$state): array {
          return $this->routeHeroicRecoveryAllPointsIntentExecution($eid, $aid, $state);
        }
      );
      if (!empty($hero_point_route['handled'])) {
        $payload = (array) ($hero_point_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $movement_route = $this->encounterIntentRouter->routeMovementUtilityAction(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (?int $eid, ?string $aid, array &$state): array {
          return $this->routeStandIntentExecution($eid, $aid, $state);
        },
        function (?int $eid, ?string $aid, array &$state): array {
          return $this->routeDropProneIntentExecution($eid, $aid, $state);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeStepIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeCrawlIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeLeapIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        }
      );
      if (!empty($movement_route['handled'])) {
        $payload = (array) ($movement_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $defensive_route = $this->encounterIntentRouter->routeDefensiveReactionAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeArrestFallIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeGrabEdgeIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeShieldBlockIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeAttackOfOpportunityIntentExecution($eid, $aid, $tid, $action_params, $state);
        }
      );
      if (!empty($defensive_route['handled'])) {
        $payload = (array) ($defensive_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $utility_skill_route = $this->encounterIntentRouter->routeUtilitySkillAction(
        $type,
        $encounter_id,
        $actor_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeBalanceIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeTumbleThroughIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeManeuverInFlightIntentExecution($aid, $action_params, $state);
        }
      );
      if (!empty($utility_skill_route['handled'])) {
        $payload = (array) ($utility_skill_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $social_skill_route = $this->encounterIntentRouter->routeSocialSkillAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $campaign_id,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeFeintIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeCreateDiversionIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state, int $cid): array {
          return $this->routeRequestIntentExecution($aid, $tid, $action_params, $state, $cid);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state, int $cid): array {
          return $this->routeDemoralizeIntentExecution($eid, $aid, $tid, $action_params, $state, $cid);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeCommandAnimalIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routePerformIntentExecution($aid, $action_params, $state);
        }
      );
      if (!empty($social_skill_route['handled'])) {
        $payload = (array) ($social_skill_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $utility_route = $this->encounterIntentRouter->routeEncounterUtilityAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeEscapeIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeSeekIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeSearchIntentExecution($aid, $action_params, $state, $dungeon, $cid);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeSenseMotiveIntentExecution($aid, $tid, $action_params, $state);
        },
        function (?string $aid, array &$state): array {
          return $this->routeTakeCoverIntentExecution($aid, $state);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeReleaseIntentExecution($aid, $action_params, $state, $dungeon);
        }
      );
      if (!empty($utility_route['handled'])) {
        $payload = (array) ($utility_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
        if (array_key_exists('narration', $payload)) {
          $narration = $payload['narration'];
        }
        if (array_key_exists('mechanical_result', $payload)) {
          $mechanical_result = $payload['mechanical_result'];
        }
      }
      else {
      $athletics_route = $this->encounterIntentRouter->routeAthleticsTacticalAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeClimbIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeForceOpenIntentExecution($aid, $tid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeGrappleIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeHighJumpIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeLongJumpIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeShoveIntentExecution($eid, $aid, $tid, $action_params, $state);
        }
      );
      if (!empty($athletics_route['handled'])) {
        $payload = (array) ($athletics_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $athletics_maneuver_route = $this->encounterIntentRouter->routeAthleticsManeuverAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeSwimIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeTripIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeDisarmIntentExecution($eid, $aid, $tid, $action_params, $state);
        }
      );
      if (!empty($athletics_maneuver_route['handled'])) {
        $payload = (array) ($athletics_maneuver_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $medicine_knowledge_route = $this->encounterIntentRouter->routeMedicineKnowledgeAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeAdministerFirstAidIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeTreatPoisonIntentExecution($aid, $tid, $action_params, $state);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeBattleMedicineIntentExecution($aid, $tid, $action_params, $state);
        },
        function (?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeRecallKnowledgeIntentExecution($aid, $tid, $action_params, $state);
        }
      );
      if (!empty($medicine_knowledge_route['handled'])) {
        $payload = (array) ($medicine_knowledge_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $stealth_route = $this->encounterIntentRouter->routeStealthSubterfugeAction(
        $type,
        $actor_id,
        $params,
        $game_state,
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeHideIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeSneakIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeConcealObjectIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routePalmObjectIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeStealIntentExecution($aid, $action_params, $state);
        }
      );
      if (!empty($stealth_route['handled'])) {
        $payload = (array) ($stealth_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $device_hazard_route = $this->encounterIntentRouter->routeDeviceHazardAction(
        $type,
        $actor_id,
        $params,
        $game_state,
        $dungeon_data,
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeDisableDeviceIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routePickLockIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeDisableHazardIntentExecution($aid, $action_params, $state, $dungeon);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeAttackHazardIntentExecution($aid, $action_params, $state, $dungeon);
        },
        function (?string $aid, array $action_params, array &$state, array &$dungeon): array {
          return $this->routeCounteractHazardIntentExecution($aid, $action_params, $state, $dungeon);
        }
      );
      if (!empty($device_hazard_route['handled'])) {
        $payload = (array) ($device_hazard_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
        if (array_key_exists('phase_transition', $payload)) {
          $phase_transition = $payload['phase_transition'];
        }
      }
      else {
      $magic_activation_route = $this->encounterIntentRouter->routeMagicActivationAction(
        $type,
        $actor_id,
        $params,
        $game_state,
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeActivateItemIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeSustainActivationIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeDismissActivationIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeSustainSpellIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeDismissSpellIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeCastFromScrollIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeCastFromStaffIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeCastFromWandIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeOverchargeWandIntentExecution($aid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeActivateTalismanIntentExecution($aid, $action_params, $state);
        }
      );
      if (!empty($magic_activation_route['handled'])) {
        $payload = (array) ($magic_activation_route['payload'] ?? []);
        $abort_response = $this->mergeRouterRoutePayload($payload, $result, $mutations, $events);
        if ($abort_response !== NULL) {
          return $abort_response;
        }
      }
      else {
      $traversal_route = $this->encounterIntentRouter->routeTraversalUtilityAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        $dungeon_data,
        $campaign_id,
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeBurrowIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state, array &$dungeon, int $cid): array {
          return $this->routeFlyIntentExecution($eid, $aid, $action_params, $state, $dungeon, $cid);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routeMountIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeDismountIntentExecution($eid, $aid, $action_params, $state);
        }
      );
      if (!empty($traversal_route['handled'])) {
        $payload = (array) ($traversal_route['payload'] ?? []);
        if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
          return $payload['abort_response'];
        }
        $result = (array) ($payload['result'] ?? []);
        $mutations = $payload['mutations'] ?? $mutations;
        $events = array_merge($events, (array) ($payload['events'] ?? []));
      }
      else {
      $stance_route = $this->encounterIntentRouter->routeStanceAwarenessAction(
        $type,
        $encounter_id,
        $actor_id,
        $target_id,
        $params,
        $game_state,
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeRaiseShieldIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, array $action_params, array &$state): array {
          return $this->routeAvertGazeIntentExecution($eid, $aid, $action_params, $state);
        },
        function (?int $eid, ?string $aid, ?string $tid, array $action_params, array &$state): array {
          return $this->routePointOutIntentExecution($eid, $aid, $tid, $action_params, $state);
        },
        function (?string $aid, array $action_params, array &$state): array {
          return $this->routeMinorColorShiftIntentExecution($aid, $action_params, $state);
        }
      );
      if (!empty($stance_route['handled'])) {
        $payload = (array) ($stance_route['payload'] ?? []);
        $abort_response = $this->mergeRouterRoutePayload($payload, $result, $mutations, $events);
        if ($abort_response !== NULL) {
          return $abort_response;
        }
      }
      else {
      }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }
    }

    $social_progression_events = $this->applyRoomSceneSocialProgressionPolicy(
      $intent,
      $result,
      $game_state,
      $dungeon_data,
      $campaign_id
    );
    if ($social_progression_events !== []) {
      $events = array_merge($events, $social_progression_events);
    }

    $this->maybeQueueMechanicalSystemLogEntry([
      'campaign_id' => $campaign_id,
      'dungeon_data' => $dungeon_data,
      'type' => $type,
      'actor_id' => $actor_id,
      'target_id' => $target_id,
      'params' => $params,
      'result' => is_array($mechanical_result) ? $mechanical_result : $result,
      'game_state' => $game_state,
    ]);

    // Check for auto-end-turn (actions depleted + no movement remaining).
    // Delay is intentional initiative exit — do NOT auto-end-turn for it.
    $no_auto_end_types = ['end_turn', 'choose_not_to_act', 'delay', 'delay_reenter', 'release', 'aid'];
    if (!in_array($type, $no_auto_end_types, TRUE) && $this->shouldAutoEndTurn($game_state)) {
      $auto_end = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
      $time_effects = array_merge($time_effects, $this->buildRoundElapsedTimeEffects($auto_end, $actor_id, $dungeon_data));
      $events[] = GameEventLogger::buildEvent('auto_end_turn', 'encounter', $actor_id, [
        'round' => $game_state['round'] ?? NULL,
      ]);
      if (!empty($auto_end['npc_events'])) {
        $events = array_merge($events, $auto_end['npc_events']);
      }
    }

    // Keep combat_participants action economy in sync with the authoritative
    // encounter turn state used by the game coordinator.
    $this->syncEncounterParticipantTurnResources($encounter_id, $game_state);

    return [
      'success' => TRUE,
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'phase_transition' => $phase_transition,
      'narration' => $narration,
      'time_effects' => $time_effects,
    ];
  }

  /**
   * Applies a handled router payload into the intent orchestration response state.
   */
  protected function mergeRouterRoutePayload(array $payload, array &$result, array &$mutations, array &$events): ?array {
    if (!empty($payload['abort_response']) && is_array($payload['abort_response'])) {
      return $payload['abort_response'];
    }
    $result = (array) ($payload['result'] ?? []);
    $mutations = $payload['mutations'] ?? $mutations;
    $events = array_merge($events, (array) ($payload['events'] ?? []));
    return NULL;
  }

  /**
   * Router seam: execute end-turn intent block with legacy side effects.
   */
  protected function routeEndTurnIntentExecution(
    string $type,
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if ($type === 'choose_not_to_act') {
      $params['reason'] = trim((string) ($params['reason'] ?? 'chooses not to act'));
    }

    $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
    $result = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
    $mutations = $result['mutations'] ?? [];
    $narration = $result['narration'] ?? NULL;
    $time_effects = $this->buildRoundElapsedTimeEffects($result, $actor_id, $dungeon_data);

    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = (string) ($turn_ctx['actor_name'] ?? ($actor_id ? $this->resolveEntityName($actor_id, $game_state, $dungeon_data) : 'Narrator'));
    $fallback_narration = $type === 'choose_not_to_act'
      ? sprintf('%s chooses not to act.', $actor_name)
      : sprintf('%s ends their turn.', $actor_name);
    $resolved_narration = (is_string($narration) && trim($narration) !== '') ? $narration : $fallback_narration;
    $resolved_narration = $this->prefixEncounterChatLine($turn_ctx, $resolved_narration);

    $events = [
      GameEventLogger::buildEvent($type, 'encounter', $actor_id, [
        'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
        'room_id' => $resolved_room_id,
        'actor_name' => $actor_name,
        'turn_index' => $turn_ctx['turn_index_raw'] ?? NULL,
        'actions_remaining' => $result['actions_remaining_before_end'] ?? NULL,
        'reason' => $params['reason'] ?? NULL,
      ], $resolved_narration),
    ];

    if ($actor_id && ($result['actions_remaining_before_end'] ?? 0) > 0) {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'choose_not_to_act',
        'speaker' => 'Narrator',
        'speaker_type' => 'narrator',
        'speaker_ref' => '',
        'content' => $this->prefixEncounterChatLine($turn_ctx, sprintf('%s chooses not to use %d remaining action(s).', $actor_name, (int) $result['actions_remaining_before_end'])),
        'visibility' => 'public',
        'mechanical_data' => [
          'actor_id' => $actor_id,
          'actor_name' => $actor_name,
          'room_id' => $resolved_room_id,
          'actions_remaining' => (int) $result['actions_remaining_before_end'],
          'reason' => $params['reason'] ?? NULL,
        ],
      ], $resolved_room_id);
    }

    if (!empty($result['npc_events'])) {
      $events = array_merge($events, $result['npc_events']);
    }

    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
      'time_effects' => $time_effects,
    ];
  }

  /**
   * Router seam: execute delay intent block with legacy side effects.
   */
  protected function routeDelayIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $delay_remaining = $game_state['turn']['actions_remaining'] ?? 0;
    $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
    $result = [
      'delayed' => TRUE,
      'remaining_actions' => $delay_remaining,
    ];

    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = (string) ($turn_ctx['actor_name'] ?? ($actor_id ? $this->resolveEntityName($actor_id, $game_state, $dungeon_data) : 'Narrator'));
    $events = [
      GameEventLogger::buildEvent('delay', 'encounter', $actor_id, [
        'remaining_actions' => $delay_remaining,
        'round' => $game_state['round'] ?? NULL,
      ], $this->prefixEncounterChatLine($turn_ctx, sprintf('%s delays until the end of the round.', $actor_name))),
    ];

    $delay_after_actor_id = trim((string) ($params['delay_until_actor_id'] ?? ''));
    if ($actor_id && is_array($game_state['initiative_order'] ?? NULL) && $game_state['initiative_order'] !== []) {
      $delay_plan = $this->buildDelayedInitiativePlan(
        $game_state['initiative_order'],
        $actor_id,
        (int) ($game_state['turn']['index'] ?? 0),
        (int) $delay_remaining,
        $delay_after_actor_id !== '' ? $delay_after_actor_id : NULL
      );
      $game_state['initiative_order'] = $delay_plan['initiative_order'];
      $game_state['turn']['index'] = $delay_plan['pre_advance_index'];
      $game_state['turn']['delayed'] = TRUE;
      $game_state['turn']['delayed_actions_remaining'] = (int) $delay_remaining;
    }

    $game_state['turn']['actions_remaining'] = 0;
    $advance = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
    $time_effects = $this->buildRoundElapsedTimeEffects($advance, $actor_id, $dungeon_data);

    if ($actor_id && $delay_remaining > 0) {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'delay',
        'speaker' => 'Narrator',
        'speaker_type' => 'narrator',
        'speaker_ref' => '',
        'content' => $this->prefixEncounterChatLine($turn_ctx, sprintf('%s delays with %d action(s) still unused.', $actor_name, (int) $delay_remaining)),
        'visibility' => 'public',
        'mechanical_data' => [
          'actor_id' => $actor_id,
          'actor_name' => $actor_name,
          'room_id' => $resolved_room_id,
          'remaining_actions' => (int) $delay_remaining,
        ],
      ], $resolved_room_id);
    }
    if (!empty($advance['npc_events'])) {
      $events = array_merge($events, $advance['npc_events']);
    }

    return [
      'result' => $result,
      'mutations' => [],
      'events' => $events,
      'narration' => NULL,
      'time_effects' => $time_effects,
    ];
  }

  /**
   * Router seam: execute delay-reenter intent block with legacy side effects.
   */
  protected function routeDelayReenterIntentExecution(
    ?string $actor_id,
    array &$game_state
  ): array {
    if (empty($game_state['turn']['delayed'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Not currently delayed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $reenter_actions = $game_state['turn']['delayed_actions_remaining'] ?? 0;
    $game_state['turn']['delayed'] = FALSE;
    $game_state['turn']['actions_remaining'] = $reenter_actions;

    return [
      'result' => ['reentered' => TRUE, 'actions_restored' => $reenter_actions],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('delay_reenter', 'encounter', $actor_id, [
          'actions_restored' => $reenter_actions,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
      'narration' => NULL,
      'time_effects' => [],
    ];
  }

  /**
   * Router seam: execute ready intent block with legacy side effects.
   */
  protected function routeReadyIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $ready_action = $params['ready_action'] ?? NULL;
    $ready_trigger = $params['ready_trigger'] ?? NULL;
    if (!$ready_action || !$ready_trigger) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'ready_action and ready_trigger are required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (!empty($params['is_triggered_free_action'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot Ready a free action that already has a trigger.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $game_state['turn']['ready'] = [
      'action' => $ready_action,
      'trigger' => $ready_trigger,
      'map_at_ready' => $game_state['turn']['attacks_this_turn'] ?? 0,
    ];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);

    return [
      'result' => ['readied' => TRUE, 'action' => $ready_action, 'trigger' => $ready_trigger],
      'events' => [
        GameEventLogger::buildEvent('ready', 'encounter', $actor_id, [
          'action' => $ready_action,
          'trigger' => $ready_trigger,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute reaction intent block with legacy side effects.
   */
  protected function routeReactionIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $reaction_available = $game_state['turn']['reaction_available'] ?? TRUE;
    if (!$reaction_available) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent this round.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $game_state['turn']['reaction_available'] = FALSE;
    $ready_data = $game_state['turn']['ready'] ?? NULL;
    if ($ready_data && ($ready_data['action'] ?? '') === 'strike') {
      $game_state['turn']['attacks_this_turn'] = (int) ($ready_data['map_at_ready'] ?? 0);
    }

    return [
      'result' => ['reaction_used' => TRUE, 'reaction_type' => $params['reaction_type'] ?? 'generic'],
      'events' => [
        GameEventLogger::buildEvent('reaction', 'encounter', $actor_id, [
          'reaction_type' => $params['reaction_type'] ?? 'generic',
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute aid-setup intent block with legacy side effects.
   */
  protected function routeAidSetupIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (!isset($game_state['turn']['aid_prepared'])) {
      $game_state['turn']['aid_prepared'] = [];
    }
    $aid_skill = $params['skill'] ?? 'generic';
    $game_state['turn']['aid_prepared'][$actor_id][$target_id] = $aid_skill;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

    return [
      'result' => ['aid_prepared' => TRUE, 'target' => $target_id, 'skill' => $aid_skill],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('aid_setup', 'encounter', $actor_id, [
          'target' => $target_id,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute aid intent block with legacy side effects.
   */
  protected function routeAidIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $result = $this->processAid($actor_id, $target_id, $params, $game_state);
    $mutations = $result['mutations'] ?? [];

    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('aid', 'encounter', $actor_id, [
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'aid_bonus' => $result['aid_bonus'] ?? 0,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute hero-point-reroll intent block with legacy side effects.
   */
  protected function routeHeroPointRerollIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $original_roll = (int) ($params['original_roll'] ?? 0);
    $reroll = $this->calculator->heroPointReroll($original_roll);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if ($participant) {
      $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
      $hero_points = max(0, (int) ($entity_ref['hero_points'] ?? 0) - 1);
      $entity_ref['hero_points'] = $hero_points;
      $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_ref)]);
    }

    return [
      'result' => $reroll + ['hero_points_spent' => 1],
      'events' => [
        GameEventLogger::buildEvent('hero_point_reroll', 'encounter', $actor_id, [
          'original_roll' => $original_roll,
          'new_roll' => $reroll['new_roll'],
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute heroic-recovery-all-points intent block with legacy side effects.
   */
  protected function routeHeroicRecoveryAllPointsIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array &$game_state
  ): array {
    $participant_id = $actor_id;
    if (is_string($participant_id)) {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      $participant_id = $participant ? (int) $participant['id'] : NULL;
    }
    if (!$participant_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if ($participant) {
      $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
      $entity_ref['hero_points'] = 0;
      $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_ref)]);
    }

    $result = $this->hpManager->heroicRecoveryAllPoints($participant_id, $encounter_id);
    return [
      'result' => $result,
      'events' => [
        GameEventLogger::buildEvent('heroic_recovery_all_points', 'encounter', $actor_id, [
          'dying_removed' => $result['dying_removed'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute stand intent block with legacy side effects.
   */
  protected function routeStandIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if ($participant) {
      $participant_id = (int) $participant['id'];
      foreach ($this->conditionManager->getActiveConditions($participant_id, $encounter_id) as $condition_id => $condition_row) {
        if ($condition_row['condition_type'] === 'prone') {
          $this->conditionManager->removeCondition($participant_id, $condition_id, $encounter_id);
          break;
        }
      }
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['stood' => TRUE],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('stand', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]),
      ],
    ];
  }

  /**
   * Router seam: execute drop-prone intent block with legacy side effects.
   */
  protected function routeDropProneIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if ($participant) {
      $participant_id = (int) $participant['id'];
      $this->conditionManager->applyCondition($participant_id, 'prone', 1, 'persistent', 'drop_prone', $encounter_id);
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['prone' => TRUE],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('drop_prone', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]),
      ],
    ];
  }

  /**
   * Router seam: execute step intent block with legacy side effects.
   */
  protected function routeStepIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (empty($params['to_hex'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Missing to_hex.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if ($this->movementResolver && $this->movementResolver->isDifficultTerrain($params['to_hex'], $dungeon_data)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot Step into difficult terrain.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $move_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $move_result['mutations'] ?? [];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $game_state['turn']['last_move_type'] = 'step';

    return [
      'result' => ['stepped' => TRUE, 'to_hex' => $params['to_hex']],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('step', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute crawl intent block with legacy side effects.
   */
  protected function routeCrawlIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (empty($params['to_hex'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Missing to_hex.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $participant_id = (int) $participant['id'];
    if (!$this->conditionManager->hasCondition($participant_id, 'prone', $encounter_id)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Must be prone to Crawl.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if ((int) ($participant['speed'] ?? 25) < 10) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Speed is too low to Crawl (requires Speed >= 10 ft).'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $move_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $move_result['mutations'] ?? [];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

    return [
      'result' => ['crawled' => TRUE, 'to_hex' => $params['to_hex']],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('crawl', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute leap intent block with legacy side effects.
   */
  protected function routeLeapIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (empty($params['to_hex'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Missing to_hex.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $leap_speed = (int) ($participant['speed'] ?? 25);
    $max_leap_ft = $leap_speed >= 30 ? 15 : 10;

    $move_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $move_result['mutations'] ?? [];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

    return [
      'result' => ['leaped' => TRUE, 'to_hex' => $params['to_hex'], 'max_leap_ft' => $max_leap_ft],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('leap', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute arrest-fall intent block with legacy side effects.
   */
  protected function routeArrestFallIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    if (empty($entity_data['fly_speed'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Arrest a Fall requires fly Speed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $acrobatics_bonus = (int) ($params['acrobatics_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 15, $d20);
    $feet_fallen = (int) ($params['feet_fallen'] ?? 0);
    $fall_damage = 0;
    if ($degree === 'failure') {
      $fall_damage = (int) floor($feet_fallen / 2);
    }
    elseif ($degree === 'critical_failure') {
      $fall_damage = (int) ceil($feet_fallen / 20) * 10;
    }

    $game_state['turn']['reaction_available'] = FALSE;
    return [
      'result' => [
        'arrest_fall' => TRUE,
        'degree' => $degree,
        'fall_damage' => $fall_damage,
        'roll' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('arrest_fall', 'encounter', $actor_id, [
          'degree' => $degree,
          'fall_damage' => $fall_damage,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute grab-edge intent block with legacy side effects.
   */
  protected function routeGrabEdgeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $reflex_bonus = (int) ($params['reflex_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $reflex_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 15, $d20);
    $grabbed = in_array($degree, ['critical_success', 'success'], TRUE);

    if ($grabbed) {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      if ($participant) {
        $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
        $entity_data['clinging'] = TRUE;
        $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
      }
    }

    $game_state['turn']['reaction_available'] = FALSE;
    return [
      'result' => [
        'grab_edge' => TRUE,
        'degree' => $degree,
        'grabbed' => $grabbed,
        'roll' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('grab_edge', 'encounter', $actor_id, [
          'degree' => $degree,
          'grabbed' => $grabbed,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute shield-block intent block with legacy side effects.
   */
  protected function routeShieldBlockIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    if (empty($entity_data['shield_raised'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Shield must be raised to use Shield Block.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $shield = $this->findHeldShield($entity_data);
    if (!$shield) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'No shield in hand.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $incoming_damage = (int) ($params['incoming_damage'] ?? 0);
    $hardness = (int) ($shield['hardness'] ?? 0);
    $reduced = max(0, $incoming_damage - $hardness);
    $shield_takes = (int) floor($reduced / 2);
    $entity_takes = $reduced - $shield_takes;

    if ($entity_takes > 0 && $this->hpManager) {
      $this->hpManager->applyDamage((int) $participant['id'], $entity_takes, 'physical', ['source' => 'shield_block_residual'], $encounter_id);
    }

    $shield['hp'] = max(0, (int) ($shield['hp'] ?? $shield['max_hp'] ?? 10) - $shield_takes);
    if ($shield['hp'] <= 0) {
      $shield['broken'] = TRUE;
      $entity_data['shield_raised'] = FALSE;
    }
    $entity_data = $this->updateHeldShield($entity_data, $shield);
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);

    $game_state['turn']['reaction_available'] = FALSE;
    $result = [
      'shield_block' => TRUE,
      'incoming_damage' => $incoming_damage,
      'hardness' => $hardness,
      'entity_damage' => $entity_takes,
      'shield_damage' => $shield_takes,
      'shield_broken' => $shield['broken'] ?? FALSE,
    ];

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('shield_block', 'encounter', $actor_id, [
          'entity_damage' => $entity_takes,
          'shield_damage' => $shield_takes,
          'shield_broken' => $shield['broken'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute attack-of-opportunity intent block with legacy side effects.
   */
  protected function routeAttackOfOpportunityIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Reaction already spent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $class_features = $entity_data['class_features'] ?? [];
    if (!in_array('attack_of_opportunity', (array) $class_features, TRUE)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Character does not have Attack of Opportunity class feature.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (!$target_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'target required for Attack of Opportunity.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $weapon = $params['weapon'] ?? [];
    $weapon['skip_map_count'] = TRUE;
    $strike_result = $this->processStrike($encounter_id, $actor_id, $target_id, ['weapon' => $weapon, 'skip_map' => TRUE], $game_state);

    $trigger_type = $params['trigger_type'] ?? '';
    $disrupted = (($strike_result['degree'] ?? '') === 'critical_success' && $trigger_type === 'manipulate');
    $game_state['turn']['reaction_available'] = FALSE;
    $result = array_merge($strike_result, ['attack_of_opportunity' => TRUE, 'disrupted' => $disrupted]);

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('attack_of_opportunity', 'encounter', $actor_id, [
          'target' => $target_id,
          'degree' => $strike_result['degree'] ?? NULL,
          'damage' => $strike_result['damage'] ?? NULL,
          'disrupted' => $disrupted,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute balance intent block with legacy side effects.
   */
  protected function routeBalanceIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $dc = (int) ($params['dc'] ?? 15);
    $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $balanced = in_array($degree, ['success', 'critical_success'], TRUE);
    if ($degree === 'critical_failure' || $degree === 'failure') {
      $this->conditionManager->applyCondition(
        (int) $actor_id,
        'flat_footed',
        0,
        ['remaining_attacks' => PHP_INT_MAX],
        'balance_fail',
        (int) $encounter_id
      );
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['balanced' => $balanced, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('balance', 'encounter', $actor_id, $result),
      ],
    ];
  }

  /**
   * Router seam: execute tumble-through intent block with legacy side effects.
   */
  protected function routeTumbleThroughIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $target_ref = $params['target_id'] ?? '';
    $dc = (int) ($params['dc'] ?? 15);
    $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $passed_through = in_array($degree, ['success', 'critical_success'], TRUE);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['passed_through' => $passed_through, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('tumble_through', 'encounter', $actor_id, $result, NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute maneuver-in-flight intent block with legacy side effects.
   */
  protected function routeManeuverInFlightIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $dc = (int) ($params['dc'] ?? 15);
    $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $acrobatics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $maneuvered = in_array($degree, ['success', 'critical_success'], TRUE);
    if ($degree === 'critical_failure') {
      $game_state['encounter_state'][$actor_id . '_falling'] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['maneuvered' => $maneuvered, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('maneuver_in_flight', 'encounter', $actor_id, $result),
      ],
    ];
  }

  /**
   * Router seam: execute feint intent block with legacy side effects.
   */
  protected function routeFeintIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $target_ref = $params['target_id'] ?? '';
    $dc = (int) ($params['dc'] ?? 15);
    $deception = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $deception;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $feinted = FALSE;
    if ($degree === 'critical_success') {
      $feinted = TRUE;
      $this->conditionManager->applyCondition((int) $target_ref, 'flat_footed', 0, ['remaining_attacks' => PHP_INT_MAX], 'feint_crit', (int) $encounter_id);
    }
    elseif ($degree === 'success') {
      $feinted = TRUE;
      $this->conditionManager->applyCondition((int) $target_ref, 'flat_footed', 0, ['remaining_attacks' => 1], 'feint', (int) $encounter_id);
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 2);
    $result = ['feinted' => $feinted, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('feint', 'encounter', $actor_id, $result, NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute create-diversion intent block with legacy side effects.
   */
  protected function routeCreateDiversionIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $dc = (int) ($params['dc'] ?? 15);
    $deception = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $deception;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $diverted = in_array($degree, ['success', 'critical_success'], TRUE);
    if ($diverted) {
      $game_state['encounter_state'][$actor_id . '_created_diversion'] = TRUE;
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['diverted' => $diverted, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('create_diversion', 'encounter', $actor_id, $result),
      ],
    ];
  }

  /**
   * Router seam: execute request intent block with legacy side effects.
   */
  protected function routeRequestIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? '';
    $base_dc = (int) ($params['dc'] ?? 15);
    $dc_context = $this->applyNpcAttitudeToSocialDc($base_dc, $params, $target_id ?: $target_ref, $campaign_id);
    $dc = $dc_context['dc'];
    $diplomacy = (int) ($params['diplomacy_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $diplomacy;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $granted = in_array($degree, ['success', 'critical_success'], TRUE);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = [
      'granted' => $granted,
      'degree' => $degree,
      'roll' => $total,
      'dc' => $dc,
      'base_dc' => $base_dc,
      'attitude_dc_delta' => $dc_context['delta'],
    ];
    if ($dc_context['attitude'] !== NULL) {
      $result['npc_attitude'] = $dc_context['attitude'];
    }

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('request', 'encounter', $actor_id, $result, NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute demoralize intent block with legacy side effects.
   */
  protected function routeDemoralizeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    int $campaign_id
  ): array {
    $target_ref = $params['target_id'] ?? '';
    $base_dc = (int) ($params['dc'] ?? 15);
    $dc_context = $this->applyNpcAttitudeToSocialDc($base_dc, $params, $target_id ?: $target_ref, $campaign_id);
    $dc = $dc_context['dc'];
    $intimidation = (int) ($params['intimidation_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $intimidation;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $immune_key = 'demoralize_immune_' . $target_ref . '_' . $actor_id;
    $immune = !empty($game_state['encounter_state'][$immune_key]);
    $demoralized = FALSE;
    if (!$immune) {
      $game_state['encounter_state'][$immune_key] = TRUE;
      if ($degree === 'critical_success') {
        $demoralized = TRUE;
        $this->conditionManager->applyCondition((int) $target_ref, 'frightened', 2, [], 'demoralize_crit', (int) $encounter_id);
      }
      elseif ($degree === 'success') {
        $demoralized = TRUE;
        $this->conditionManager->applyCondition((int) $target_ref, 'frightened', 1, [], 'demoralize', (int) $encounter_id);
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = [
      'demoralized' => $demoralized,
      'immune' => $immune,
      'degree' => $degree,
      'roll' => $total,
      'dc' => $dc,
      'base_dc' => $base_dc,
      'attitude_dc_delta' => $dc_context['delta'],
    ];
    if ($dc_context['attitude'] !== NULL) {
      $result['npc_attitude'] = $dc_context['attitude'];
    }

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('demoralize', 'encounter', $actor_id, $result, NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute command-animal intent block with legacy side effects.
   */
  protected function routeCommandAnimalIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $target_ref = $params['target_id'] ?? $actor_id;
    $dc = (int) ($params['dc'] ?? 15);
    if (!empty($params['is_trained_companion'])) {
      $dc -= 5;
    }
    $nature = (int) ($params['nature_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $nature;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $obeyed = in_array($degree, ['success', 'critical_success'], TRUE);
    if ($degree === 'critical_failure') {
      $game_state['encounter_state']['animal_panicked_' . $target_ref] = TRUE;
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['obeyed' => $obeyed, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('command_animal', 'encounter', $actor_id, $result, NULL, $target_ref),
      ],
    ];
  }

  /**
   * Router seam: execute perform intent block with legacy side effects.
   */
  protected function routePerformIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $dc = (int) ($params['dc'] ?? 15);
    $performance = (int) ($params['performance_bonus'] ?? $params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $performance;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $entertained = in_array($degree, ['success', 'critical_success'], TRUE);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
    $result = ['entertained' => $entertained, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('perform', 'encounter', $actor_id, $result),
      ],
    ];
  }

  /**
   * Router seam: execute escape intent block with legacy side effects.
   */
  protected function routeEscapeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $result = $this->processEscape($encounter_id, $actor_id, $params, $game_state);
    $mutations = $result['mutations'] ?? [];
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('escape', 'encounter', $actor_id, [
          'degree' => $result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute seek intent block with legacy side effects.
   */
  protected function routeSeekIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $result = $this->processSeek($encounter_id, $actor_id, $params, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('seek', 'encounter', $actor_id, [
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute search intent block with legacy side effects.
   */
  protected function routeSearchIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (!$this->explorationPhaseHandler) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Room search handler is unavailable.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $search_result = $this->explorationPhaseHandler->processSearch($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mechanical_result = $search_result;
    $mutations = $search_result['mutations'] ?? [];
    $narration = $search_result['narration'] ?? NULL;
    $resolved_room_id = trim((string) (
      $params['room_id']
      ?? $dungeon_data['active_room_id']
      ?? $game_state['encounter_context']['room_id']
      ?? ''
    ));
    if (!empty($game_state['turn'])) {
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    }

    $events = [];
    $public_discoveries = $this->buildPublicSearchDiscoveries($search_result['discoveries'] ?? []);
    if ($public_discoveries !== [] || (is_string($narration) && trim($narration) !== '')) {
      $events[] = GameEventLogger::buildEvent('search', 'encounter', $actor_id, [
        'discoveries' => $public_discoveries,
        'round' => $game_state['round'] ?? NULL,
        'room_id' => $resolved_room_id !== '' ? $resolved_room_id : NULL,
      ], $narration);
    }

    return [
      'result' => $this->buildPublicSearchResult($search_result),
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
      'mechanical_result' => $mechanical_result,
    ];
  }

  /**
   * Router seam: execute sense-motive intent block with legacy side effects.
   */
  protected function routeSenseMotiveIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $bonus = (int) ($params['perception_bonus'] ?? 0);
    $dc = (int) ($params['deception_dc'] ?? 15);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    if (!isset($game_state['sense_motive'])) {
      $game_state['sense_motive'] = [];
    }
    if (!isset($game_state['sense_motive'][$actor_id])) {
      $game_state['sense_motive'][$actor_id] = [];
    }
    $game_state['sense_motive'][$actor_id][$target_id] = $game_state['round'] ?? 0;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

    return [
      'result' => ['sense_motive' => TRUE, 'degree' => $degree],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('sense_motive', 'encounter', $actor_id, [
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute take-cover intent block with legacy side effects.
   */
  protected function routeTakeCoverIntentExecution(
    ?string $actor_id,
    array &$game_state
  ): array {
    if (!isset($game_state['entities'])) {
      $game_state['entities'] = [];
    }
    if (!isset($game_state['entities'][$actor_id])) {
      $game_state['entities'][$actor_id] = [];
    }
    $current_cover = $game_state['entities'][$actor_id]['cover'] ?? 'none';
    $new_cover = ($current_cover === 'standard') ? 'greater' : 'standard';
    $game_state['entities'][$actor_id]['cover'] = $new_cover;
    $game_state['entities'][$actor_id]['cover_active'] = TRUE;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

    return [
      'result' => ['cover' => $new_cover, 'cover_active' => TRUE],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('take_cover', 'encounter', $actor_id, [
          'cover' => $new_cover,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute release intent block with legacy side effects.
   */
  protected function routeReleaseIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $item_id = $params['item_id'] ?? NULL;
    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $entity_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
        if ($entity_id === $actor_id) {
          if ($item_id && isset($entity['equipment']['held'][$item_id])) {
            unset($entity['equipment']['held'][$item_id]);
          }
          break;
        }
      }
      unset($entity);
    }

    return [
      'result' => ['released' => TRUE, 'item_id' => $item_id],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('release', 'encounter', $actor_id, [
          'item_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute climb intent block with legacy side effects.
   */
  protected function routeClimbIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $has_climb_speed = !empty($entity_ref['climb_speed']) && (int) $entity_ref['climb_speed'] > 0;
    $land_speed = (int) ($entity_ref['speed'] ?? 25);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $climb_dc = (int) ($params['climb_dc'] ?? 15);

    if ($has_climb_speed) {
      $athletics += 4;
      $d20 = 0;
      $total = 0;
      $degree = 'success';
      $feet_moved = (int) $entity_ref['climb_speed'];
    }
    else {
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $athletics;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $climb_dc, $d20);
      $feet_moved = 0;
      if ($degree === 'critical_success') {
        $feet_moved = max(10, (int) round($land_speed / 2));
      }
      elseif ($degree === 'success') {
        $feet_moved = max(5, (int) round($land_speed / 4));
      }
      elseif ($degree === 'critical_failure') {
        $feet_fallen = (int) ($params['height_ft'] ?? 10);
        $soft_surface = !empty($params['soft_surface']);
        if ($this->hpManager) {
          $this->hpManager->applyFallDamage((int) $participant['id'], $feet_fallen, $encounter_id, $soft_surface);
        }
      }
    }

    $fell = ($degree === 'critical_failure');
    if (!$has_climb_speed && !$fell) {
      $this->conditionManager->applyCondition((int) $participant['id'], 'flat_footed', 0, ['type' => 'encounter', 'remaining' => 1], 'climb', $encounter_id);
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $result = [
      'climbed' => !$fell,
      'degree' => $degree,
      'feet_moved' => $feet_moved,
      'fell' => $fell,
      'd20' => $d20,
      'total' => $total,
    ];

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('climb', 'encounter', $actor_id, [
          'degree' => $degree,
          'fell' => $fell,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute force-open intent block with legacy side effects.
   */
  protected function routeForceOpenIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $has_crowbar = !empty($params['has_crowbar']);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $item_penalty = $has_crowbar ? 0 : -2;
    $dc = (int) ($params['object_dc'] ?? 20);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $item_penalty + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $jammed = FALSE;
    $broken = FALSE;
    $opened = FALSE;
    if ($degree === 'critical_success') {
      $opened = TRUE;
    }
    elseif ($degree === 'success') {
      $opened = TRUE;
      $broken = TRUE;
    }
    elseif ($degree === 'critical_failure') {
      $jammed = TRUE;
      if (!isset($game_state['force_open_jammed'])) {
        $game_state['force_open_jammed'] = [];
      }
      $target_obj = $params['object_id'] ?? $target_id;
      $game_state['force_open_jammed'][$target_obj] = ($game_state['force_open_jammed'][$target_obj] ?? 0) - 2;
    }

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $result = [
      'opened' => $opened,
      'broken' => $broken,
      'jammed' => $jammed,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
    ];

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('force_open', 'encounter', $actor_id, [
          'degree' => $degree,
          'opened' => $opened,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute grapple intent block with legacy side effects.
   */
  protected function routeGrappleIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $result = $this->processGrapple($encounter_id, $actor_id, $target_id, $params, $game_state);
    $mutations = $result['mutations'] ?? [];
    $game_state['turn']['attacks_this_turn'] = ($game_state['turn']['attacks_this_turn'] ?? 0) + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('grapple', 'encounter', $actor_id, [
          'degree' => $result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute high-jump intent block with legacy side effects.
   */
  protected function routeHighJumpIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $prior_stride_ft = (int) ($game_state['turn']['last_stride_ft'] ?? 0);
    if ($prior_stride_ft < 10) {
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
      return [
        'result' => [
          'jumped' => FALSE,
          'auto_fail' => TRUE,
          'reason' => 'No prior Stride of ≥10 ft',
        ],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('high_jump', 'encounter', $actor_id, [
            'auto_fail' => TRUE,
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $dc = (int) ($params['dc'] ?? 30);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $height_ft = 0;
    $fell_prone = FALSE;
    if ($degree === 'critical_success') {
      $height_ft = 8;
    }
    elseif ($degree === 'success') {
      $height_ft = 5;
    }
    elseif ($degree === 'critical_failure') {
      $fell_prone = TRUE;
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      if ($participant) {
        $this->conditionManager->applyCondition((int) $participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'high_jump', $encounter_id);
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => [
        'jumped' => !$fell_prone,
        'height_ft' => $height_ft,
        'degree' => $degree,
        'fell_prone' => $fell_prone,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('high_jump', 'encounter', $actor_id, [
          'degree' => $degree,
          'height_ft' => $height_ft,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute long-jump intent block with legacy side effects.
   */
  protected function routeLongJumpIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $prior_stride_ft = (int) ($game_state['turn']['last_stride_ft'] ?? 0);
    if ($prior_stride_ft < 10) {
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
      return [
        'result' => [
          'jumped' => FALSE,
          'auto_fail' => TRUE,
          'reason' => 'No prior Stride of ≥10 ft',
        ],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, [
            'auto_fail' => TRUE,
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $target_ft = (int) ($params['target_ft'] ?? 10);
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $entity_ref = $participant && !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $speed = (int) ($entity_ref['speed'] ?? $participant['speed'] ?? 25);
    if ($target_ft > $speed) {
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
      return [
        'result' => [
          'jumped' => FALSE,
          'auto_fail' => TRUE,
          'reason' => 'Target distance exceeds Speed',
        ],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, [
            'auto_fail' => TRUE,
            'reason' => 'speed_cap',
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $dc = $target_ft;
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $distance_ft = 0;
    $fell_prone = FALSE;
    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $distance_ft = $target_ft;
    }
    elseif ($degree === 'critical_failure') {
      $fell_prone = TRUE;
      if ($participant) {
        $this->conditionManager->applyCondition((int) $participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'long_jump', $encounter_id);
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => [
        'jumped' => !$fell_prone || $distance_ft > 0,
        'distance_ft' => $distance_ft,
        'degree' => $degree,
        'fell_prone' => $fell_prone,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, [
          'degree' => $degree,
          'distance_ft' => $distance_ft,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute shove intent block with legacy side effects.
   */
  protected function routeShoveIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $dc = (int) ($params['fortitude_dc'] ?? 15);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $push_ft = 0;
    $attacker_prone = FALSE;
    if ($degree === 'critical_success') {
      $push_ft = 10;
    }
    elseif ($degree === 'success') {
      $push_ft = 5;
    }
    elseif ($degree === 'critical_failure') {
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
      if ($participant) {
        $this->conditionManager->applyCondition((int) $participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'shove', $encounter_id);
      }
      $attacker_prone = TRUE;
    }

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'shoved' => $push_ft > 0,
        'push_ft' => $push_ft,
        'degree' => $degree,
        'forced_movement' => TRUE,
        'attacker_prone' => $attacker_prone,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('shove', 'encounter', $actor_id, [
          'degree' => $degree,
          'push_ft' => $push_ft,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute swim intent block with legacy side effects.
   */
  protected function routeSwimIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_ref = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];

    $is_calm = !empty($params['calm_water']);
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $swim_dc = (int) ($params['swim_dc'] ?? 15);
    $land_speed = (int) ($entity_ref['speed'] ?? 25);
    $has_swim_speed = !empty($entity_ref['swim_speed']) && (int) $entity_ref['swim_speed'] > 0;
    if ($has_swim_speed) {
      $athletics += 4;
      $is_calm = TRUE;
    }

    $degree = 'success';
    $d20 = 0;
    $total = 0;
    if (!$is_calm) {
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $athletics;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $swim_dc, $d20);
    }

    $feet_moved = 0;
    $breath_lost = FALSE;
    if ($degree === 'critical_success') {
      $feet_moved = max(10, (int) round($land_speed / 2));
    }
    elseif ($degree === 'success') {
      $feet_moved = max(5, (int) round($land_speed / 4));
    }
    elseif ($degree === 'critical_failure') {
      $breath_lost = TRUE;
      $held_breath = max(0, (int) ($game_state['entities'][$actor_id]['held_breath_rounds'] ?? 0) - 1);
      if (!isset($game_state['entities'][$actor_id])) {
        $game_state['entities'][$actor_id] = [];
      }
      $game_state['entities'][$actor_id]['held_breath_rounds'] = $held_breath;
    }

    if (empty($entity_ref['water_breathing']) && !$has_swim_speed) {
      if (!isset($game_state['entities'][$actor_id])) {
        $game_state['entities'][$actor_id] = [];
      }
      $game_state['entities'][$actor_id]['submerged'] = TRUE;
    }

    if (!isset($game_state['turn']['swim_actions'])) {
      $game_state['turn']['swim_actions'] = [];
    }
    $game_state['turn']['swim_actions'][$actor_id] = ($game_state['turn']['swim_actions'][$actor_id] ?? 0) + 1;

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'swam' => $feet_moved > 0,
        'degree' => $degree,
        'feet_moved' => $feet_moved,
        'breath_lost' => $breath_lost,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('swim', 'encounter', $actor_id, [
          'degree' => $degree,
          'feet_moved' => $feet_moved,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute trip intent block with legacy side effects.
   */
  protected function routeTripIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $dc = (int) ($params['reflex_dc'] ?? 15);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $target_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $target_id) : NULL;
    $actor_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;

    $damage = 0;
    $attacker_prone = FALSE;
    if ($degree === 'critical_success') {
      $damage = $this->numberGenerationService->rollPathfinderDie(6);
      if ($target_participant) {
        $this->hpManager->applyDamage((int) $target_participant['id'], $damage, 'bludgeoning', 'trip', $encounter_id);
        $this->conditionManager->applyCondition((int) $target_participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
      }
    }
    elseif ($degree === 'success') {
      if ($target_participant) {
        $this->conditionManager->applyCondition((int) $target_participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
      }
    }
    elseif ($degree === 'critical_failure') {
      if ($actor_participant) {
        $this->conditionManager->applyCondition((int) $actor_participant['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
      }
      $attacker_prone = TRUE;
    }

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'tripped' => in_array($degree, ['critical_success', 'success'], TRUE),
        'degree' => $degree,
        'damage' => $damage,
        'attacker_prone' => $attacker_prone,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('trip', 'encounter', $actor_id, [
          'degree' => $degree,
          'damage' => $damage,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute disarm intent block with legacy side effects.
   */
  protected function routeDisarmIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $proficiency_rank = (int) ($params['athletics_proficiency_rank'] ?? 0);
    if ($proficiency_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Disarm requires Trained Athletics.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $athletics = (int) ($params['athletics_bonus'] ?? 0);
    $dc = (int) ($params['reflex_dc'] ?? 15);
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $actor_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;

    $item_dropped = FALSE;
    $grip_weakened = FALSE;
    $attacker_flat_footed = FALSE;

    if ($degree === 'critical_success') {
      $item_dropped = TRUE;
    }
    elseif ($degree === 'success') {
      $grip_weakened = TRUE;
      if (!isset($game_state['grip_weakened'])) {
        $game_state['grip_weakened'] = [];
      }
      $game_state['grip_weakened'][$target_id] = ($game_state['round'] ?? 0) + 1;
    }
    elseif ($degree === 'critical_failure') {
      if ($actor_participant) {
        $this->conditionManager->applyCondition((int) $actor_participant['id'], 'flat_footed', 0, ['type' => 'encounter', 'remaining' => 1], 'disarm', $encounter_id);
      }
      $attacker_flat_footed = TRUE;
    }

    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'disarmed' => $item_dropped,
        'grip_weakened' => $grip_weakened,
        'degree' => $degree,
        'attacker_flat_footed' => $attacker_flat_footed,
        'd20' => $d20,
        'total' => $total,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('disarm', 'encounter', $actor_id, [
          'degree' => $degree,
          'item_dropped' => $item_dropped,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute administer-first-aid intent block with legacy side effects.
   */
  protected function routeAdministerFirstAidIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $actor_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $target_participant = ($encounter && $target_id) ? $this->findEncounterParticipantByEntityId($encounter, $target_id) : NULL;

    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Administer First Aid requires Trained Medicine.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (empty($params['has_healers_tools'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Administer First Aid requires healer\'s tools.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $tools_penalty = !empty($params['is_improvised_tools']) ? -2 : 0;

    $mode = $params['mode'] ?? 'stabilize';
    if (!in_array($mode, ['stabilize', 'stop_bleeding'], TRUE)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => "Unknown First Aid mode '{$mode}'. Use 'stabilize' or 'stop_bleeding'."],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $first_aid_key = $target_id . ':' . $mode;
    $current_round = $game_state['round'] ?? 0;
    if (isset($game_state['first_aid_used'][$first_aid_key]) && $game_state['first_aid_used'][$first_aid_key] === $current_round) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot Administer First Aid on the same condition and target twice in the same round.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $medicine_bonus = (int) ($params['medicine_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $medicine_bonus + $tools_penalty;

    $afa_result = $this->processAdministerFirstAid(
      $target_participant,
      $actor_participant,
      $mode,
      $total,
      $d20,
      $params,
      $encounter_id
    );

    $game_state['first_aid_used'][$first_aid_key] = $current_round;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    $result = array_merge($afa_result, [
      'd20' => $d20,
      'total' => $total,
      'mode' => $mode,
      'tools_penalty' => $tools_penalty,
    ]);

    return [
      'result' => $result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('administer_first_aid', 'encounter', $actor_id, [
          'mode' => $mode,
          'degree' => $afa_result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute treat-poison intent block with legacy side effects.
   */
  protected function routeTreatPoisonIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Treat Poison requires Trained Medicine.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (empty($params['has_healers_tools'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Treat Poison requires healer\'s tools.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $poison_key = ($target_id ?? $actor_id) . ':poison';
    if (!empty($game_state['poison_treated'][$poison_key])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Can only treat one poison per save for this target.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $med_bonus = (int) ($params['medicine_bonus'] ?? 0);
    $poison_dc = (int) ($params['poison_dc'] ?? 15);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $med_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $poison_dc, $d20);

    $treated = in_array($degree, ['critical_success', 'success'], TRUE);
    if ($treated) {
      $game_state['poison_treated'][$poison_key] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'treated' => $treated,
        'degree' => $degree,
        'd20' => $d20,
        'total' => $total,
        'dc' => $poison_dc,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('treat_poison', 'encounter', $actor_id, [
          'degree' => $degree,
          'treated' => $treated,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute battle-medicine intent block with legacy side effects.
   */
  protected function routeBattleMedicineIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Battle Medicine requires Trained Medicine.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (empty($params['has_healers_tools'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Battle Medicine requires healer\'s tools.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $effective_target = $target_id ?? $actor_id;
    $immunity_key = $actor_id . ':' . $effective_target;
    if (!empty($game_state['battle_medicine_immune'][$immunity_key])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Target is immune to this healer\'s Battle Medicine for 1 day.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $dc_table = [1 => 15, 2 => 20, 3 => 30, 4 => 40];
    $hp_bonus = [1 => 0, 2 => 10, 3 => 30, 4 => 50];
    $rank_key = min(4, max(1, $med_rank));
    $dc = (int) ($params['override_dc'] ?? $dc_table[$rank_key]);
    $med_bonus = (int) ($params['medicine_bonus'] ?? 0);
    $item_bonus = !empty($params['is_improvised_tools']) ? -2 : 0;

    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $d8a = $this->numberGenerationService->rollPathfinderDie(8);
    $d8b = $this->numberGenerationService->rollPathfinderDie(8);
    $total = $d20 + $med_bonus + $item_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $healed = 0;
    $damage = 0;
    if ($degree === 'critical_success') {
      $healed = (($d8a + $d8b) + $hp_bonus[$rank_key]) * 2;
    }
    elseif ($degree === 'success') {
      $healed = ($d8a + $d8b) + $hp_bonus[$rank_key];
    }
    elseif ($degree === 'critical_failure') {
      $damage = $this->numberGenerationService->rollPathfinderDie(8);
    }

    $game_state['battle_medicine_immune'][$immunity_key] = TRUE;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'degree' => $degree,
        'healed' => $healed,
        'damage' => $damage,
        'dc' => $dc,
        'd20' => $d20,
        'total' => $total,
        'removes_wounded' => FALSE,
        'mutations' => [],
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('battle_medicine', 'encounter', $actor_id, [
          'degree' => $degree,
          'healed' => $healed,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $effective_target),
      ],
    ];
  }

  /**
   * Router seam: execute recall-knowledge intent block with legacy side effects.
   */
  protected function routeRecallKnowledgeIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (!empty($params['dc'])) {
      $dc = (int) $params['dc'];
    }
    else {
      $rk_service = new RecallKnowledgeService(new DcAdjustmentService());
      $dc_result = $rk_service->computeDc(
        $params['subject_type'] ?? 'general',
        (int) ($params['level'] ?? 0),
        $params['rarity'] ?? 'common',
        (int) ($params['spell_rank'] ?? 0),
        $params['availability'] ?? 'trained'
      );
      $dc = $dc_result['dc'];
    }

    $skill_used = $params['skill_used'] ?? 'arcana';
    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $skill_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $attempt_key = $actor_id . ':' . ($target_id ?? 'general');
    if (!empty($game_state['recall_knowledge_attempts'][$attempt_key])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Cannot re-attempt Recall Knowledge on the same target without new information.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $game_state['recall_knowledge_attempts'][$attempt_key] = TRUE;

    switch ($degree) {
      case 'critical_success':
        $player_msg = 'You recall detailed information about the subject.';
        $info = $params['known_info'] ?? NULL;
        $bonus_detail = $params['bonus_detail'] ?? NULL;
        break;

      case 'success':
        $player_msg = 'You recall accurate information about the subject.';
        $info = $params['known_info'] ?? NULL;
        $bonus_detail = NULL;
        break;

      case 'failure':
        $player_msg = 'You fail to recall anything useful.';
        $info = NULL;
        $bonus_detail = NULL;
        break;

      case 'critical_failure':
      default:
        $player_msg = 'You recall information about the subject.';
        $info = $params['false_info'] ?? NULL;
        $bonus_detail = NULL;
        break;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'degree' => $degree,
        'skill_used' => $skill_used,
        'dc' => $dc,
        'd20' => $d20,
        'total' => $total,
        'player_facing_message' => $player_msg,
        'info' => $info,
        'bonus_detail' => $bonus_detail,
        'secret' => TRUE,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('recall_knowledge', 'encounter', $actor_id, [
          'skill_used' => $skill_used,
          'degree' => $degree,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute hide intent block with legacy side effects.
   */
  protected function routeHideIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (empty($params['has_cover']) && empty($params['has_concealment'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hide requires cover or concealment.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $stealth_bonus = (int) ($params['stealth_bonus'] ?? 0);
    $chameleon_bonus = 0;
    if (($params['heritage'] ?? '') === 'chameleon') {
      $terrain_color = $params['terrain_color_tag'] ?? '';
      $char_color = $params['coloration_tag'] ?? '';
      if ($terrain_color !== '' && $char_color !== '' && $terrain_color === $char_color) {
        $existing_circumstance = (int) ($params['circumstance_bonus'] ?? 0);
        $chameleon_bonus = max(0, 2 - $existing_circumstance);
        $stealth_bonus += $chameleon_bonus;
      }
    }

    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dcs = $params['perception_dcs'] ?? [];
    if (!isset($game_state['visibility'])) {
      $game_state['visibility'] = [];
    }

    $hide_results = [];
    foreach ($observer_ids as $obs_id) {
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $stealth_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $game_state['visibility'][$obs_id][$actor_id] = 'hidden';
      }
      else {
        $game_state['visibility'][$obs_id][$actor_id] = 'observed';
      }
      $hide_results[$obs_id] = ['degree' => $degree, 'visibility' => $game_state['visibility'][$obs_id][$actor_id]];
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'hide_results' => $hide_results,
        'observer_count' => count($observer_ids),
        'secret' => TRUE,
        'chameleon_bonus_applied' => $chameleon_bonus > 0 ? $chameleon_bonus : NULL,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('hide', 'encounter', $actor_id, [
          'observer_count' => count($observer_ids),
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute sneak intent block with legacy side effects.
   */
  protected function routeSneakIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $is_hidden_to_any = FALSE;
    $observer_ids = $params['observer_ids'] ?? [];
    foreach ($observer_ids as $obs_id) {
      $visibility = $game_state['visibility'][$obs_id][$actor_id] ?? 'observed';
      if (in_array($visibility, ['hidden', 'undetected', 'unnoticed'], TRUE)) {
        $is_hidden_to_any = TRUE;
        break;
      }
    }
    if (!$is_hidden_to_any && !empty($observer_ids)) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Sneak requires Hidden (or Undetected) status. Use Hide first.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $speed = (int) ($params['speed'] ?? 25);
    $half_speed = (int) (floor($speed / 2 / 5) * 5);
    if (empty($params['ends_in_cover']) && empty($params['ends_in_concealment'])) {
      foreach ($observer_ids as $obs_id) {
        $game_state['visibility'][$obs_id][$actor_id] = 'observed';
      }
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      return [
        'result' => [
          'sneak_results' => [],
          'became_observed' => TRUE,
          'half_speed' => $half_speed,
          'reason' => 'Ended in open terrain.',
        ],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('sneak', 'encounter', $actor_id, [
            'became_observed' => TRUE,
            'round' => $game_state['round'] ?? NULL,
          ]),
        ],
      ];
    }

    $stealth_bonus = (int) ($params['stealth_bonus'] ?? 0);
    $chameleon_bonus = 0;
    if (($params['heritage'] ?? '') === 'chameleon') {
      $terrain_color = $params['terrain_color_tag'] ?? '';
      $char_color = $params['coloration_tag'] ?? '';
      if ($terrain_color !== '' && $char_color !== '' && $terrain_color === $char_color) {
        $existing_circumstance = (int) ($params['circumstance_bonus'] ?? 0);
        $chameleon_bonus = max(0, 2 - $existing_circumstance);
        $stealth_bonus += $chameleon_bonus;
      }
    }
    $perception_dcs = $params['perception_dcs'] ?? [];

    $sneak_results = [];
    foreach ($observer_ids as $obs_id) {
      $current_vis = $game_state['visibility'][$obs_id][$actor_id] ?? 'observed';
      if (!in_array($current_vis, ['hidden', 'undetected', 'unnoticed'], TRUE)) {
        $sneak_results[$obs_id] = ['degree' => NULL, 'visibility' => 'observed'];
        continue;
      }
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $stealth_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (!in_array($degree, ['critical_success', 'success'], TRUE)) {
        $game_state['visibility'][$obs_id][$actor_id] = 'observed';
      }
      $sneak_results[$obs_id] = ['degree' => $degree, 'visibility' => $game_state['visibility'][$obs_id][$actor_id]];
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'sneak_results' => $sneak_results,
        'half_speed' => $half_speed,
        'secret' => TRUE,
        'chameleon_bonus_applied' => $chameleon_bonus > 0 ? $chameleon_bonus : NULL,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('sneak', 'encounter', $actor_id, [
          'observer_count' => count($observer_ids),
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute conceal-object intent block with legacy side effects.
   */
  protected function routeConcealObjectIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $stealth_bonus = (int) ($params['stealth_bonus'] ?? 0);
    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dcs = $params['perception_dcs'] ?? [];
    $item_id = $params['item_id'] ?? NULL;

    if (!isset($game_state['concealed_objects'])) {
      $game_state['concealed_objects'] = [];
    }

    $conceal_results = [];
    $concealed_to_all = TRUE;
    foreach ($observer_ids as $obs_id) {
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $stealth_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $conceal_results[$obs_id] = ['degree' => $degree, 'concealed' => TRUE];
      }
      else {
        $conceal_results[$obs_id] = ['degree' => $degree, 'concealed' => FALSE];
        $concealed_to_all = FALSE;
      }
    }

    if ($item_id && $concealed_to_all && !empty($observer_ids)) {
      $game_state['concealed_objects'][$actor_id . ':' . $item_id] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'concealed_results' => $conceal_results,
        'item_id' => $item_id,
        'secret' => TRUE,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('conceal_object', 'encounter', $actor_id, [
          'item_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute palm-object intent block with legacy side effects.
   */
  protected function routePalmObjectIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dcs = $params['perception_dcs'] ?? [];
    $item_id = $params['item_id'] ?? NULL;

    if (!isset($game_state['palmed_objects'])) {
      $game_state['palmed_objects'] = [];
    }

    $palm_results = [];
    $palmed_from_all = TRUE;
    foreach ($observer_ids as $obs_id) {
      $perc_dc = (int) ($perception_dcs[$obs_id] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $thievery_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perc_dc, $d20);
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $palm_results[$obs_id] = ['degree' => $degree, 'hidden' => TRUE];
      }
      else {
        $palm_results[$obs_id] = ['degree' => $degree, 'hidden' => FALSE];
        $palmed_from_all = FALSE;
      }
    }

    if ($item_id && $palmed_from_all && !empty($observer_ids)) {
      $game_state['palmed_objects'][$actor_id . ':' . $item_id] = TRUE;
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'palm_results' => $palm_results,
        'item_id' => $item_id,
        'secret' => TRUE,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('palm_object', 'encounter', $actor_id, [
          'item_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute steal intent block with legacy side effects.
   */
  protected function routeStealIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $target_id = $params['target_id'] ?? NULL;
    $observer_ids = $params['observer_ids'] ?? [];
    $perception_dc = (int) ($params['perception_dc'] ?? 15);
    $item_id = $params['item_id'] ?? NULL;

    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $thievery_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $perception_dc, $d20);

    $stolen = FALSE;
    $observers_alerted = [];
    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $stolen = TRUE;
      if ($item_id) {
        if (!isset($game_state['stolen_items'])) {
          $game_state['stolen_items'] = [];
        }
        $game_state['stolen_items'][] = ['actor' => $actor_id, 'from' => $target_id, 'item_id' => $item_id];
      }
    }
    elseif ($degree === 'critical_failure') {
      $observers_alerted = array_merge([$target_id], $observer_ids);
      $observers_alerted = array_filter($observers_alerted);
      if (!isset($game_state['steal_awareness'])) {
        $game_state['steal_awareness'] = [];
      }
      foreach ($observers_alerted as $aware_id) {
        $game_state['steal_awareness'][$aware_id][$actor_id] = TRUE;
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => [
        'degree' => $degree,
        'stolen' => $stolen,
        'observers_alerted' => array_values($observers_alerted),
        'secret' => TRUE,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('steal', 'encounter', $actor_id, [
          'target_id' => $target_id,
          'degree' => $degree,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute disable-device intent block with legacy side effects.
   */
  protected function routeDisableDeviceIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_rank = (int) ($params['thievery_proficiency_rank'] ?? 0);
    if ($thievery_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Disable a Device requires Trained Thievery.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $device_id = $params['device_id'] ?? NULL;
    $dc = (int) ($params['dc'] ?? 20);
    $has_tools = !empty($params['has_thieves_tools']);
    if (!$has_tools) {
      $dc += 5;
    }

    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $thievery_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $disabled = FALSE;
    $triggered = FALSE;
    if (!isset($game_state['device_states'])) {
      $game_state['device_states'] = [];
    }
    if ($degree === 'critical_failure') {
      $triggered = TRUE;
      if ($device_id) {
        $game_state['device_states'][$device_id]['triggered'] = TRUE;
      }
    }
    elseif (in_array($degree, ['critical_success', 'success'], TRUE)) {
      if ($device_id) {
        $successes_needed = (int) ($params['successes_needed'] ?? 1);
        $successes_so_far = (int) ($game_state['device_states'][$device_id]['successes'] ?? 0) + 1;
        $game_state['device_states'][$device_id]['successes'] = $successes_so_far;
        if ($successes_so_far >= $successes_needed) {
          $disabled = TRUE;
          $game_state['device_states'][$device_id]['disabled'] = TRUE;
        }
      }
      else {
        $disabled = TRUE;
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => [
        'degree' => $degree,
        'disabled' => $disabled,
        'triggered' => $triggered,
        'used_tools' => $has_tools,
        'secret' => TRUE,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('disable_device', 'encounter', $actor_id, [
          'device_id' => $device_id,
          'degree' => $degree,
          'triggered' => $triggered,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute pick-lock intent block with legacy side effects.
   */
  protected function routePickLockIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $thievery_rank = (int) ($params['thievery_proficiency_rank'] ?? 0);
    if ($thievery_rank < 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Pick a Lock requires Trained Thievery.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $lock_id = $params['lock_id'] ?? NULL;
    if ($lock_id && !empty($game_state['lock_states'][$lock_id]['jammed'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'This lock is jammed and cannot be picked until repaired.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $lock_quality_dcs = ['simple' => 15, 'average' => 20, 'good' => 25, 'superior' => 30];
    $lock_quality = $params['lock_quality'] ?? 'average';
    $dc = $lock_quality_dcs[$lock_quality] ?? 20;
    $has_tools = !empty($params['has_thieves_tools']);
    if (!$has_tools) {
      $dc += 5;
    }

    $thievery_bonus = (int) ($params['thievery_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $thievery_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

    $unlocked = FALSE;
    $jammed = FALSE;
    if (!isset($game_state['lock_states'])) {
      $game_state['lock_states'] = [];
    }
    if ($degree === 'critical_failure') {
      $jammed = TRUE;
      if ($lock_id) {
        $game_state['lock_states'][$lock_id]['jammed'] = TRUE;
      }
    }
    elseif (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $unlocked = TRUE;
      if ($lock_id) {
        $game_state['lock_states'][$lock_id]['locked'] = FALSE;
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => [
        'degree' => $degree,
        'unlocked' => $unlocked,
        'jammed' => $jammed,
        'lock_quality' => $lock_quality,
        'used_tools' => $has_tools,
        'secret' => TRUE,
      ],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('pick_lock', 'encounter', $actor_id, [
          'lock_id' => $lock_id,
          'lock_quality' => $lock_quality,
          'degree' => $degree,
          'jammed' => $jammed,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute disable-hazard intent block with legacy side effects.
   */
  protected function routeDisableHazardIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $hazard_id = $params['hazard_id'] ?? NULL;
    if (!$hazard_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'hazard_id required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
    if ($hazard_ref === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hazard not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $skill_rank = (int) ($params['skill_proficiency_rank'] ?? 0);
    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
    $disable_result = $this->hazardService->disableHazard($hazard_ref, $skill_bonus, $skill_rank);
    $xp = 0;
    if (!empty($disable_result['disabled'])) {
      $xp = $this->hazardService->awardHazardXp($game_state, $hazard_ref, (int) ($game_state['party_level'] ?? 1));
    }

    $phase_transition = NULL;
    if (!empty($disable_result['triggered']) && (($hazard_ref['complexity'] ?? 'simple') === 'complex')) {
      $initiative = $this->hazardService->rollComplexHazardInitiative($hazard_ref);
      $phase_transition = ['type' => 'encounter_continue', 'hazard_initiative' => $initiative, 'hazard_id' => $hazard_id];
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => array_merge($disable_result, ['xp_awarded' => $xp, 'hazard_id' => $hazard_id]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('disable_hazard', 'encounter', $actor_id, [
          'hazard_id' => $hazard_id,
          'degree' => $disable_result['degree'],
          'disabled' => $disable_result['disabled'],
          'triggered' => $disable_result['triggered'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
      'phase_transition' => $phase_transition,
    ];
  }

  /**
   * Router seam: execute attack-hazard intent block with legacy side effects.
   */
  protected function routeAttackHazardIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $hazard_id = $params['hazard_id'] ?? NULL;
    if (!$hazard_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'hazard_id required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
    if ($hazard_ref === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hazard not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $damage_amount = (int) ($params['damage'] ?? 0);
    $damage_result = $this->hazardService->applyDamageToHazard($hazard_ref, $damage_amount);
    $xp = 0;
    if (!empty($damage_result['disabled'])) {
      $xp = $this->hazardService->awardHazardXp($game_state, $hazard_ref, (int) ($game_state['party_level'] ?? 1));
    }

    $phase_transition = NULL;
    if (!empty($damage_result['triggered']) && (($hazard_ref['complexity'] ?? 'simple') === 'complex')) {
      $initiative = $this->hazardService->rollComplexHazardInitiative($hazard_ref);
      $phase_transition = ['type' => 'encounter_continue', 'hazard_initiative' => $initiative, 'hazard_id' => $hazard_id];
    }

    $action_cost = (int) ($params['action_cost'] ?? 1);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);
    return [
      'result' => array_merge($damage_result, ['xp_awarded' => $xp, 'hazard_id' => $hazard_id]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('attack_hazard', 'encounter', $actor_id, [
          'hazard_id' => $hazard_id,
          'damage' => $damage_amount,
          'triggered' => $damage_result['triggered'] ?? FALSE,
          'disabled' => $damage_result['disabled'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
      'phase_transition' => $phase_transition,
    ];
  }

  /**
   * Router seam: execute counteract-hazard intent block with legacy side effects.
   */
  protected function routeCounteractHazardIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $hazard_id = $params['hazard_id'] ?? NULL;
    if (!$hazard_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'hazard_id required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
    if ($hazard_ref === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Hazard not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $counteract_level = (int) ($params['counteract_level'] ?? 0);
    $counteract_bonus = (int) ($params['counteract_bonus'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $counteract_bonus;
    $counteract_result = $this->hazardService->counteractMagicalHazard($hazard_ref, $counteract_level, $total, $d20);
    $xp = 0;
    if (!empty($counteract_result['counteracted'])) {
      $xp = $this->hazardService->awardHazardXp($game_state, $hazard_ref, (int) ($game_state['party_level'] ?? 1));
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
    return [
      'result' => array_merge($counteract_result, ['xp_awarded' => $xp, 'hazard_id' => $hazard_id]),
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('counteract_hazard', 'encounter', $actor_id, [
          'hazard_id' => $hazard_id,
          'degree' => $counteract_result['degree'],
          'counteracted' => $counteract_result['counteracted'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute activate-item intent block with legacy side effects.
   */
  protected function routeActivateItemIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $item_id = $params['item_instance_id'] ?? NULL;
    $item_data = $params['item_data'] ?? [];
    if (!$item_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'activate_item requires params.item_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $char_state = $params['char_state'] ?? [];
    $activate_result = $this->magicItemService->activateItem($actor_id, $item_id, $item_data, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($activate_result['actions_cost'] ?? 1));
    return [
      'result' => $activate_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('activate_item', 'encounter', $actor_id, [
          'item_instance_id' => $item_id,
          'success' => $activate_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute sustain-activation intent block with legacy side effects.
   */
  protected function routeSustainActivationIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $item_id = $params['item_instance_id'] ?? NULL;
    if (!$item_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'sustain_activation requires params.item_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $sustain_result = $this->magicItemService->sustainActivation($actor_id, $item_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => $sustain_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('sustain_activation', 'encounter', $actor_id, [
          'item_instance_id' => $item_id,
          'success' => $sustain_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute dismiss-activation intent block with legacy side effects.
   */
  protected function routeDismissActivationIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $item_id = $params['item_instance_id'] ?? NULL;
    if (!$item_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'dismiss_activation requires params.item_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $dismiss_result = $this->magicItemService->dismissActivation($actor_id, $item_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => $dismiss_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('dismiss_activation', 'encounter', $actor_id, [
          'item_instance_id' => $item_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute sustain-spell intent block with legacy side effects.
   */
  protected function routeSustainSpellIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $spell_id = $params['spell_id'] ?? NULL;
    if (!$spell_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'sustain_spell requires params.spell_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $current_round = (int) ($game_state['round'] ?? 1);
    $sustained = $game_state['spells']['sustained'][$actor_id][$spell_id] ?? NULL;
    if ($sustained === NULL) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => "Spell '{$spell_id}' is not currently sustained by this caster."],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $rounds_sustained = $current_round - (int) ($sustained['start_round'] ?? $current_round);
    if ($rounds_sustained >= MagicItemService::SUSTAIN_FATIGUE_ROUNDS) {
      unset($game_state['spells']['sustained'][$actor_id][$spell_id]);
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      return [
        'result' => ['sustained' => FALSE, 'ended' => TRUE, 'reason' => 'exceeded_100_rounds', 'fatigue_applied' => TRUE],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('sustain_spell', 'encounter', $actor_id, [
            'spell_id' => $spell_id,
            'ended' => TRUE,
            'reason' => 'exceeded_100_rounds',
            'round' => $current_round,
          ]),
        ],
      ];
    }

    $game_state['spells']['sustained'][$actor_id][$spell_id]['last_sustained_round'] = $current_round;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['sustained' => TRUE, 'rounds_sustained' => $rounds_sustained + 1],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('sustain_spell', 'encounter', $actor_id, [
          'spell_id' => $spell_id,
          'rounds_sustained' => $rounds_sustained + 1,
          'round' => $current_round,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute dismiss-spell intent block with legacy side effects.
   */
  protected function routeDismissSpellIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $spell_id = $params['spell_id'] ?? NULL;
    if (!$spell_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'dismiss_spell requires params.spell_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    unset($game_state['spells']['sustained'][$actor_id][$spell_id]);
    unset($game_state['spells']['durations'][$actor_id][$spell_id]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['dismissed' => TRUE, 'spell_id' => $spell_id],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('dismiss_spell', 'encounter', $actor_id, [
          'spell_id' => $spell_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute cast-from-scroll intent block with legacy side effects.
   */
  protected function routeCastFromScrollIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $scroll_id = $params['scroll_instance_id'] ?? NULL;
    $scroll_data = $params['scroll_data'] ?? [];
    if (!$scroll_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'cast_from_scroll requires params.scroll_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $char_state = $params['char_state'] ?? [];
    $scroll_result = $this->magicItemService->castFromScroll($scroll_data, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($scroll_result['actions_cost'] ?? 2));
    return [
      'result' => $scroll_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('cast_from_scroll', 'encounter', $actor_id, [
          'scroll_instance_id' => $scroll_id,
          'success' => $scroll_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute cast-from-staff intent block with legacy side effects.
   */
  protected function routeCastFromStaffIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $staff_id = $params['staff_instance_id'] ?? NULL;
    $staff_data = $params['staff_data'] ?? [];
    $spell_level = (int) ($params['spell_level'] ?? 1);
    if (!$staff_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'cast_from_staff requires params.staff_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $spell_id = $params['spell_id'] ?? '';
    $char_state = $params['char_state'] ?? [];
    $staff_result = $this->magicItemService->castFromStaff($staff_id, $actor_id, $staff_data, $spell_id, $spell_level, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($staff_result['actions_cost'] ?? 2));
    return [
      'result' => $staff_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('cast_from_staff', 'encounter', $actor_id, [
          'staff_instance_id' => $staff_id,
          'spell_level' => $spell_level,
          'success' => $staff_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute cast-from-wand intent block with legacy side effects.
   */
  protected function routeCastFromWandIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $wand_id = $params['wand_instance_id'] ?? NULL;
    $wand_data = $params['wand_data'] ?? [];
    if (!$wand_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'cast_from_wand requires params.wand_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $char_state = $params['char_state'] ?? [];
    $wand_result = $this->magicItemService->castFromWand($wand_id, $wand_data, $char_state, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($wand_result['actions_cost'] ?? 2));
    return [
      'result' => $wand_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('cast_from_wand', 'encounter', $actor_id, [
          'wand_instance_id' => $wand_id,
          'success' => $wand_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute overcharge-wand intent block with legacy side effects.
   */
  protected function routeOverchargeWandIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $wand_id = $params['wand_instance_id'] ?? NULL;
    if (!$wand_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'overcharge_wand requires params.wand_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $overcharge_result = $this->magicItemService->overchargeWand($wand_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($overcharge_result['actions_cost'] ?? 2));
    return [
      'result' => $overcharge_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('overcharge_wand', 'encounter', $actor_id, [
          'wand_instance_id' => $wand_id,
          'success' => $overcharge_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute activate-talisman intent block with legacy side effects.
   */
  protected function routeActivateTalismanIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $talisman_id = $params['talisman_instance_id'] ?? NULL;
    $host_item_id = $params['host_item_instance_id'] ?? $talisman_id;
    if (!$talisman_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'activate_talisman requires params.talisman_instance_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $talisman_result = $this->magicItemService->activateTalisman($host_item_id, $actor_id, $game_state);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($talisman_result['actions_cost'] ?? 1));
    return [
      'result' => $talisman_result,
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('activate_talisman', 'encounter', $actor_id, [
          'talisman_instance_id' => $talisman_id,
          'success' => $talisman_result['success'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute burrow intent block with legacy side effects.
   */
  protected function routeBurrowIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $burrow_speed = (int) ($entity_data['burrow_speed'] ?? 0);
    if ($burrow_speed <= 0) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'No burrow Speed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $params['movement_type'] = 'burrow';
    $burrow_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $burrow_result['mutations'] ?? [];
    $entity_data['underground'] = TRUE;
    if (!empty($entity_data['creates_tunnel'])) {
      $entity_data['tunnel_hex'] = $params['to_hex'] ?? NULL;
    }
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['burrowed' => TRUE, 'to_hex' => $params['to_hex'] ?? NULL],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('burrow', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]),
      ],
    ];
  }

  /**
   * Router seam: execute fly intent block with legacy side effects.
   */
  protected function routeFlyIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $fly_speed = (int) ($entity_data['fly_speed'] ?? 0);
    if ($fly_speed <= 0) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'No fly Speed.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $fly_distance = (int) ($params['distance'] ?? 0);
    if ($fly_distance === 0) {
      $entity_data['airborne'] = TRUE;
      $entity_data['fly_used_this_turn'] = TRUE;
      $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      return [
        'result' => ['hovered' => TRUE],
        'mutations' => [],
        'events' => [
          GameEventLogger::buildEvent('fly', 'encounter', $actor_id, ['hover' => TRUE, 'round' => $game_state['round'] ?? NULL]),
        ],
      ];
    }
    if (!empty($params['upward'])) {
      $params['movement_type'] = 'fly';
      $params['upward_movement'] = TRUE;
    }
    $params['movement_type'] = 'fly';
    $fly_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $fly_result['mutations'] ?? [];
    $entity_data['airborne'] = TRUE;
    $entity_data['fly_used_this_turn'] = TRUE;
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['fly_used'] = TRUE;
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['flew' => TRUE, 'to_hex' => $params['to_hex'] ?? NULL],
      'mutations' => $mutations,
      'events' => [
        GameEventLogger::buildEvent('fly', 'encounter', $actor_id, ['to' => $params['to_hex'] ?? NULL, 'round' => $game_state['round'] ?? NULL]),
      ],
    ];
  }

  /**
   * Router seam: execute mount intent block with legacy side effects.
   */
  protected function routeMountIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    $mount_participant = $encounter && $target_id ? $this->findEncounterParticipantByEntityId($encounter, $target_id) : NULL;
    if (!$participant || !$mount_participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant or mount not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $dist = $this->movementResolver ? $this->movementResolver->hexDistance(
      ['q' => (int) ($participant['position_q'] ?? 0), 'r' => (int) ($participant['position_r'] ?? 0)],
      ['q' => (int) ($mount_participant['position_q'] ?? 0), 'r' => (int) ($mount_participant['position_r'] ?? 0)]
    ) : 1;
    if ($dist > 1) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Mount must be adjacent.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $acrobatics_bonus = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $mount_roll = $this->numberGenerationService->rollPathfinderDie(20);
    $mount_total = $mount_roll + $acrobatics_bonus;
    if ($mount_total < 15) {
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Acrobatics check failed (DC 15).', 'roll' => $mount_roll, 'bonus' => $acrobatics_bonus, 'total' => $mount_total],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $actor_entity = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $actor_entity['mounted_on'] = $target_id;
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($actor_entity)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['mounted' => TRUE, 'mount_id' => $target_id, 'roll' => $mount_roll, 'total' => $mount_total],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('mount', 'encounter', $actor_id, ['mount' => $target_id, 'round' => $game_state['round'] ?? NULL], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute dismount intent block with legacy side effects.
   */
  protected function routeDismountIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $actor_entity = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $actor_entity['mounted_on'] = NULL;
    $dismount_to = $params['to_hex'] ?? NULL;
    $update = ['entity_ref' => json_encode($actor_entity)];
    if ($dismount_to) {
      $update['position_q'] = (int) ($dismount_to['q'] ?? $participant['position_q'] ?? 0);
      $update['position_r'] = (int) ($dismount_to['r'] ?? $participant['position_r'] ?? 0);
    }
    $this->encounterStore->updateParticipant((int) $participant['id'], $update);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['dismounted' => TRUE],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('dismount', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]),
      ],
    ];
  }

  /**
   * Router seam: execute raise-shield intent block with legacy side effects.
   */
  protected function routeRaiseShieldIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $shield = $this->findHeldShield($entity_data);
    if (!$shield) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'No shield in hand.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    if (!empty($shield['broken'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Shield is broken.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_data['shield_raised'] = TRUE;
    $entity_data['shield_raised_ac_bonus'] = (int) ($shield['ac_bonus'] ?? 0);
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['shield_raised' => TRUE, 'ac_bonus' => $entity_data['shield_raised_ac_bonus']],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('raise_shield', 'encounter', $actor_id, [
          'ac_bonus' => $entity_data['shield_raised_ac_bonus'],
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute avert-gaze intent block with legacy side effects.
   */
  protected function routeAvertGazeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $actor_id) : NULL;
    if (!$participant) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Participant not found.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $entity_data = !empty($participant['entity_ref']) ? json_decode($participant['entity_ref'], TRUE) : [];
    $entity_data['avert_gaze_active'] = TRUE;
    $this->encounterStore->updateParticipant((int) $participant['id'], ['entity_ref' => json_encode($entity_data)]);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['avert_gaze' => TRUE],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('avert_gaze', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]),
      ],
    ];
  }

  /**
   * Router seam: execute point-out intent block with legacy side effects.
   */
  protected function routePointOutIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state
  ): array {
    if (!$target_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'target required for point_out.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if ($encounter) {
      foreach ($encounter['participants'] ?? [] as $ally_participant) {
        $ally_eid = $ally_participant['entity_id'] ?? '';
        if ($ally_eid === $actor_id) {
          continue;
        }
        $ally_entity_data = !empty($ally_participant['entity_ref']) ? json_decode($ally_participant['entity_ref'], TRUE) : [];
        $ally_attacker_id = $ally_entity_data['entity_id'] ?? $ally_eid;
        $target_participant = $this->findEncounterParticipantByEntityId($encounter, $target_id);
        if ($target_participant) {
          $target_entity_data = !empty($target_participant['entity_ref']) ? json_decode($target_participant['entity_ref'], TRUE) : [];
          $current_state = $target_entity_data['detection_states'][$ally_attacker_id] ?? 'observed';
          if ($current_state === 'undetected' || $current_state === 'unnoticed') {
            $target_entity_data['detection_states'][$ally_attacker_id] = 'hidden';
            $this->encounterStore->updateParticipant((int) $target_participant['id'], ['entity_ref' => json_encode($target_entity_data)]);
          }
        }
      }
    }

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['pointed_out' => TRUE, 'target' => $target_id],
      'mutations' => [],
      'events' => [
        GameEventLogger::buildEvent('point_out', 'encounter', $actor_id, [
          'target' => $target_id,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id),
      ],
    ];
  }

  /**
   * Router seam: execute minor-color-shift intent block with legacy side effects.
   */
  protected function routeMinorColorShiftIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    if (($params['heritage'] ?? '') !== 'chameleon') {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'Minor Color Shift requires Chameleon Gnome heritage.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $terrain_color = trim($params['terrain_color_tag'] ?? '');
    if ($terrain_color === '') {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'terrain_color_tag is required.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    return [
      'result' => ['coloration_tag' => $terrain_color, 'action_cost' => 1],
      'mutations' => [['type' => 'char_state', 'key' => 'coloration_tag', 'value' => $terrain_color]],
      'events' => [
        GameEventLogger::buildEvent('minor_color_shift', 'encounter', $actor_id, [
          'new_coloration' => $terrain_color,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute consume-item intent block with legacy side effects.
   */
  protected function routeConsumeItemIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $character_id = $params['character_id'] ?? $params['characterId'] ?? NULL;
    $item = is_array($params['item'] ?? NULL) ? $params['item'] : [];
    $item_name = trim((string) ($item['name'] ?? $item['id'] ?? $item['item_id'] ?? 'consumable'));
    if (!$character_id || $item_name === '') {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'consume_item requires params.character_id and params.item.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $action_cost = $this->getActionCost('consume_item', $params);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

    try {
      $inventory = $this->characterStateService->updateInventory(
        (string) $character_id,
        'consume',
        $item,
        $campaign_id > 0 ? $campaign_id : NULL,
        $actor_id
      );
      $effects = $this->characterStateService->applyConsumableEffects(
        (string) $character_id,
        $item,
        $campaign_id > 0 ? $campaign_id : NULL,
        $actor_id
      );
    }
    catch (\InvalidArgumentException $exception) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => $exception->getMessage()],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    if (!empty($effects['focus_points']) || !empty($effects['spell_slots'])) {
      $this->syncCanonicalSpellcastingProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data);
    }
    if (!empty($effects['nutrition_days']) || !empty($effects['hydration_days'])) {
      $this->syncCanonicalSurvivalProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data);
    }

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $result = [
      'summary' => sprintf('%s uses %s.', $actor_name, $item_name),
      'item_name' => $item_name,
      'effects' => $effects,
      'inventory' => $inventory,
    ];

    return [
      'result' => $result,
      'events' => [
        GameEventLogger::buildEvent('consume_item', 'encounter', $actor_id, [
          'item_name' => $item_name,
          'effects' => $effects,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute declare-metamagic intent block with legacy side effects.
   */
  protected function routeDeclareMetamagicIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state
  ): array {
    $metamagic_id = $params['metamagic_id'] ?? NULL;
    if (!$metamagic_id) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => ['error' => 'declare_metamagic requires params.metamagic_id.'],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $game_state['turn']['metamagic_pending'][$actor_id] = $metamagic_id;
    return [
      'result' => ['declared' => TRUE, 'metamagic_id' => $metamagic_id],
      'events' => [
        GameEventLogger::buildEvent('declare_metamagic', 'encounter', $actor_id, [
          'metamagic_id' => $metamagic_id,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute interact intent block with legacy side effects.
   */
  protected function routeInteractIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $character_id = $this->resolveActorCharacterId($actor_id, $dungeon_data, $params);
    $events = [];
    $mutations = [];

    if (!$encounter_id || $this->isRoomSceneMode($game_state)) {
      $result = ['interacted' => TRUE];
      $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      $events[] = GameEventLogger::buildEvent('interact', 'encounter', $actor_id, [
        'target' => $target_id,
        'round' => $game_state['round'] ?? NULL,
      ]);

      $quest_touchpoint_result = $this->applyInteractQuestTouchpoint(
        $campaign_id,
        $character_id,
        $target_id,
        $game_state,
        $dungeon_data
      );
      if ($quest_touchpoint_result !== NULL) {
        if (empty($quest_touchpoint_result['success'])) {
          return [
            'abort_response' => [
              'success' => FALSE,
              'result' => [
                'error' => (string) ($quest_touchpoint_result['error'] ?? 'Failed to apply quest touchpoint.'),
                'quest_touchpoint' => $quest_touchpoint_result,
              ],
              'mutations' => [],
              'events' => $events,
              'phase_transition' => NULL,
              'narration' => NULL,
            ],
          ];
        }
        $result['quest_touchpoint'] = $quest_touchpoint_result;
      }

      return [
        'result' => $result,
        'mutations' => $mutations,
        'events' => $events,
      ];
    }

    $result = $this->processInteract($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $result['mutations'] ?? [];

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $events[] = GameEventLogger::buildEvent('interact', 'encounter', $actor_id, [
      'target' => $target_id,
      'interaction' => $params['interaction_type'] ?? 'generic',
      'round' => $game_state['round'] ?? NULL,
    ], NULL, $target_id);

    $quest_touchpoint_result = $this->applyInteractQuestTouchpoint(
      $campaign_id,
      $character_id,
      $target_id,
      $game_state,
      $dungeon_data
    );
    if ($quest_touchpoint_result !== NULL) {
      if (empty($quest_touchpoint_result['success'])) {
        return [
          'abort_response' => [
            'success' => FALSE,
            'result' => [
              'error' => (string) ($quest_touchpoint_result['error'] ?? 'Failed to apply quest touchpoint.'),
              'quest_touchpoint' => $quest_touchpoint_result,
            ],
            'mutations' => [],
            'events' => $events,
            'phase_transition' => NULL,
            'narration' => NULL,
          ],
        ];
      }
      $result['quest_touchpoint'] = $quest_touchpoint_result;
    }

    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
    ];
  }

  /**
   * Router seam: execute talk intent block with legacy side effects.
   */
  protected function routeTalkIntentExecution(
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $character_id = $this->resolveActorCharacterId($actor_id, $dungeon_data, $params);
    $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
    $params['_encounter_turn_ctx'] = $turn_ctx;

    $result = $this->processTalk($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    if (!empty($result['error'])) {
      return [
        'abort_response' => [
          'success' => FALSE,
          'result' => $result,
          'mutations' => $result['mutations'] ?? [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ],
      ];
    }

    $mutations = $result['mutations'] ?? [];
    $narration = $result['narration'] ?? NULL;

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    if ($this->isRoomSceneMode($game_state)) {
      $result['remaining_turn_prompt'] = $this->buildRemainingRoomSceneActionPrompt($actor_id, $game_state, $dungeon_data);
      if (is_array($result['remaining_turn_prompt'])) {
        $result['turn_logs'] = array_values(array_merge(
          is_array($result['turn_logs'] ?? NULL) ? $result['turn_logs'] : [],
          [$result['remaining_turn_prompt']]
        ));
      }
    }

    $events = [
      GameEventLogger::buildEvent('talk', 'encounter', $actor_id, [
        'message' => $result['message'] ?? '',
        'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
        'turn_index' => $turn_ctx['turn_index_raw'] ?? ($game_state['turn']['index'] ?? NULL),
        'actor_name' => $turn_ctx['actor_name'] ?? NULL,
        'gm_response_generated' => !empty($result['gm_response']),
        'state_diff_present' => !empty($result['state_diff']),
      ], $narration, $target_id),
    ];

    $quest_touchpoint_result = $this->applyInteractQuestTouchpoint(
      $campaign_id,
      $character_id,
      $target_id,
      $game_state,
      $dungeon_data
    );
    if ($quest_touchpoint_result !== NULL) {
      if (empty($quest_touchpoint_result['success'])) {
        return [
          'abort_response' => [
            'success' => FALSE,
            'result' => [
              'error' => (string) ($quest_touchpoint_result['error'] ?? 'Failed to apply quest touchpoint.'),
              'quest_touchpoint' => $quest_touchpoint_result,
            ],
            'mutations' => [],
            'events' => $events,
            'phase_transition' => NULL,
            'narration' => NULL,
          ],
        ];
      }
      $result['quest_touchpoint'] = $quest_touchpoint_result;
    }

    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
    ];
  }

  /**
   * Router seam: execute skill intent block with legacy side effects.
   */
  protected function routeSkillIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $skill_name = trim((string) ($params['skill_name'] ?? $params['skill'] ?? 'Skill'));
    $skill_bonus = NULL;
    if (isset($params['skill_bonus'])) {
      $skill_bonus = (int) $params['skill_bonus'];
    }
    elseif (isset($params['skill_modifier'])) {
      $skill_bonus = (int) $params['skill_modifier'];
    }

    $action_cost = $this->getActionCost('skill', $params);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $result = [
      'summary' => sprintf(
        '%s uses %s%s.',
        $actor_name,
        $skill_name,
        $skill_bonus !== NULL ? sprintf(' (%+d)', $skill_bonus) : ''
      ),
      'skill_name' => $skill_name,
      'skill_bonus' => $skill_bonus,
    ];

    return [
      'result' => $result,
      'events' => [
        GameEventLogger::buildEvent('skill', 'encounter', $actor_id, [
          'skill_name' => $skill_name,
          'skill_bonus' => $skill_bonus,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute feat intent block with legacy side effects.
   */
  protected function routeFeatIntentExecution(
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data
  ): array {
    $feat_name = trim((string) ($params['feat_name'] ?? $params['featName'] ?? 'Feat action'));
    $feat_id = $params['feat_id'] ?? $params['featId'] ?? NULL;

    $action_cost = $this->getActionCost('feat', $params);
    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $result = [
      'summary' => sprintf('%s uses %s.', $actor_name, $feat_name),
      'feat_name' => $feat_name,
      'feat_id' => $feat_id,
    ];

    return [
      'result' => $result,
      'events' => [
        GameEventLogger::buildEvent('feat', 'encounter', $actor_id, [
          'feat_name' => $feat_name,
          'feat_id' => $feat_id,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]),
      ],
    ];
  }

  /**
   * Router seam: execute strike intent block with legacy side effects.
   */
  protected function routeStrikeIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    ?string $target_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $result = $this->processStrike($encounter_id, (string) $actor_id, (string) $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $result['mutations'] ?? [];
    $narration = $result['narration'] ?? NULL;
    $events = [];

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    $game_state['turn']['attacks_this_turn'] = ($game_state['turn']['attacks_this_turn'] ?? 0) + 1;

    if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
      $game_state['entities'][$actor_id]['cover_active'] = FALSE;
    }

    {
      $enc_air_st = $this->encounterStore->loadEncounter($encounter_id);
      $ptcp_air_st = $enc_air_st ? $this->findEncounterParticipantByEntityId($enc_air_st, (string) $actor_id) : NULL;
      if ($ptcp_air_st) {
        $edata_air_st = !empty($ptcp_air_st['entity_ref']) ? json_decode((string) $ptcp_air_st['entity_ref'], TRUE) : [];
        if (!empty($edata_air_st['airborne'])) {
          $edata_air_st['air_decrement_this_turn'] = 2;
          $this->encounterStore->updateParticipant((int) $ptcp_air_st['id'], ['entity_ref' => json_encode($edata_air_st)]);
        }
      }
    }

    $events[] = GameEventLogger::buildEvent('strike', 'encounter', $actor_id, [
      'target' => $target_id,
      'roll' => $result['roll'] ?? NULL,
      'total' => $result['total'] ?? NULL,
      'dc' => $result['ac'] ?? NULL,
      'degree' => $result['degree'] ?? NULL,
      'damage' => $result['damage'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
    ], $narration, $target_id);

    $attacker_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $target_name = $this->resolveEntityName($target_id, $game_state, $dungeon_data);
    $degree_text = $result['degree'] ?? 'unknown';
    $damage_val = $result['damage'] ?? 0;
    $strike_desc = match ($degree_text) {
      'critical_success' => sprintf('%s critically strikes %s for %d damage!', $attacker_name, $target_name, $damage_val),
      'success' => sprintf('%s strikes %s for %d damage.', $attacker_name, $target_name, $damage_val),
      'failure' => sprintf('%s swings at %s but misses.', $attacker_name, $target_name),
      'critical_failure' => sprintf('%s fumbles an attack at %s!', $attacker_name, $target_name),
      default => sprintf('%s attacks %s.', $attacker_name, $target_name),
    };
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'action',
      'speaker' => $attacker_name,
      'speaker_type' => 'player',
      'speaker_ref' => '',
      'content' => $strike_desc,
      'visibility' => 'public',
      'mechanical_data' => [
        'attack_roll' => $result['roll'] ?? NULL,
        'total' => $result['total'] ?? NULL,
        'ac' => $result['ac'] ?? NULL,
        'degree' => $degree_text,
        'damage' => $damage_val,
        'weapon' => $params['weapon'] ?? NULL,
      ],
    ]);
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'dice_roll',
      'speaker' => 'System',
      'speaker_type' => 'system',
      'speaker_ref' => '',
      'content' => sprintf('%s attack roll %d vs AC %d: %s.', $attacker_name, (int) ($result['total'] ?? 0), (int) ($result['ac'] ?? 0), $degree_text),
      'mechanical_data' => [
        'action' => 'strike',
        'roll' => $result['roll'] ?? NULL,
        'total' => $result['total'] ?? NULL,
        'dc' => $result['ac'] ?? NULL,
        'degree' => $degree_text,
        'weapon' => $params['weapon'] ?? NULL,
        'target' => $target_id,
      ],
      'visibility' => 'public',
    ]);
    if ($damage_val > 0) {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'damage_applied',
        'speaker' => 'System',
        'speaker_type' => 'system',
        'speaker_ref' => $actor_id,
        'content' => sprintf('%s takes %d damage.', $target_name, $damage_val),
        'mechanical_data' => [
          'target' => $target_id,
          'damage' => $damage_val,
          'damage_type' => $result['damage_type'] ?? 'physical',
        ],
        'visibility' => 'public',
      ]);
    }

    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $narration,
    ];
  }

  /**
   * Router seam: execute stride intent block with legacy side effects.
   */
  protected function routeStrideIntentExecution(
    ?int $encounter_id,
    ?string $actor_id,
    array $params,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $result = $this->processStride($encounter_id, (string) $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $result['mutations'] ?? [];
    $events = [];

    $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
    if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
      $game_state['entities'][$actor_id]['cover_active'] = FALSE;
    }
    $game_state['turn']['last_stride_ft'] = (int) ($params['distance_ft'] ?? 25);

    $events[] = GameEventLogger::buildEvent('stride', 'encounter', $actor_id, [
      'from' => $params['from_hex'] ?? NULL,
      'to' => $params['to_hex'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
    ]);

    return [
      'result' => $result,
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
    $result = $this->processCastSpell($encounter_id, (string) $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
    $mutations = $result['mutations'] ?? [];
    $events = [];

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

    $events[] = GameEventLogger::buildEvent('cast_spell', 'encounter', $actor_id, [
      'spell' => $spell_name,
      'action_cost' => $action_cost,
      'round' => $game_state['round'] ?? NULL,
    ], $result['narration'] ?? NULL, $target_id);

    $caster_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $spell_target_name = $target_id ? $this->resolveEntityName($target_id, $game_state, $dungeon_data) : NULL;
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
        'spell_name' => $spell_name,
        'spell_level' => $params['spell_level'] ?? NULL,
        'action_cost' => $action_cost,
        'target' => $target_id,
      ],
    ]);

    return [
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'narration' => $result['narration'] ?? NULL,
    ];
  }

  /**
   * Enters a room and ensures an encounter-framework context is active.
   */
  public function enterRoomFramework(?string $actor_id, string $target_room_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $timing_started_at = microtime(TRUE);
    $timing_breakdown = [];
    $target_room_id = trim($target_room_id);
    if ($target_room_id === '') {
      return ['error' => 'No target room specified.'];
    }

    $rebuild_started_at = microtime(TRUE);
    $dungeon_data = $this->rebuildAuthoritativeRuntimeGraph($campaign_id, $dungeon_data, $target_room_id);
    $timing_breakdown['rebuild_runtime_graph_ms'] = (microtime(TRUE) - $rebuild_started_at) * 1000.0;

    $this->logger->info('Encounter transition requested: campaign={campaign_id} actor={actor} from_room={from_room} target_room={target_room} connection_id={connection_id}', [
      'campaign_id' => $campaign_id,
      'actor' => (string) ($actor_id ?? ''),
      'from_room' => (string) ($dungeon_data['active_room_id'] ?? ''),
      'target_room' => $target_room_id,
      'connection_id' => (string) ($params['connection_id'] ?? ''),
    ]);

    $capability = NULL;
    $capability_resolution_started_at = microtime(TRUE);
    if (!empty($dungeon_data['active_room_id']) && (string) $dungeon_data['active_room_id'] !== $target_room_id) {
      $capability = $this->resolveRoomTransitionCapability($dungeon_data, $target_room_id, $params);
      if ($capability === NULL) {
        $unreachable_diagnostics = $this->buildTransitionUnreachableDiagnostics($dungeon_data, $target_room_id);
        $this->logger->warning('Encounter transition capability missing: campaign={campaign_id} actor={actor} from_room={from_room} target_room={target_room} suggested_via_room={suggested_via_room} available_targets={available_targets}', [
          'campaign_id' => $campaign_id,
          'actor' => (string) ($actor_id ?? ''),
          'from_room' => (string) ($dungeon_data['active_room_id'] ?? ''),
          'target_room' => $target_room_id,
          'suggested_via_room' => (string) ($unreachable_diagnostics['suggested_via_room_id'] ?? ''),
          'available_targets' => implode(', ', (array) ($unreachable_diagnostics['available_targets'] ?? [])),
        ]);
        $error = "Room '$target_room_id' is not reachable from the active room.";
        $suggested_via_room_id = (string) ($unreachable_diagnostics['suggested_via_room_id'] ?? '');
        if ($suggested_via_room_id !== '') {
          $error .= sprintf(
            " Route hint: transition to '%s' first, then to '%s'.",
            $this->resolveRoomLabelById($dungeon_data, $suggested_via_room_id),
            $this->resolveRoomLabelById($dungeon_data, $target_room_id)
          );
        }

        return ['error' => $error];
      }
      if (empty($capability['available'])) {
        $this->logger->warning('Encounter transition capability blocked: campaign={campaign_id} actor={actor} target_room={target_room} blocked_reason={blocked_reason}', [
          'campaign_id' => $campaign_id,
          'actor' => (string) ($actor_id ?? ''),
          'target_room' => $target_room_id,
          'blocked_reason' => (string) ($capability['blocked_reason'] ?? 'blocked'),
        ]);
        return ['error' => sprintf("Room '%s' is not available for transition: %s.", $target_room_id, (string) ($capability['blocked_reason'] ?? 'blocked'))];
      }
    }
    $timing_breakdown['resolve_capability_ms'] = (microtime(TRUE) - $capability_resolution_started_at) * 1000.0;

    $materialize_room_started_at = microtime(TRUE);
    $room = $this->findRoomById($dungeon_data, $target_room_id);
    if ($room === NULL) {
      if (!$this->materializeCanonicalRoomForTransition($campaign_id, $dungeon_data, $target_room_id, $capability)) {
        $this->logger->warning('Encounter transition materialization failed: campaign={campaign_id} target_room={target_room} capability_connection_id={connection_id}', [
          'campaign_id' => $campaign_id,
          'target_room' => $target_room_id,
          'connection_id' => (string) ($capability['connection_id'] ?? ''),
        ]);
        return ['error' => "Room '$target_room_id' does not exist."];
      }
      $this->logger->info('Encounter transition materialized canonical room: campaign={campaign_id} target_room={target_room}', [
        'campaign_id' => $campaign_id,
        'target_room' => $target_room_id,
      ]);
      $room = $this->findRoomById($dungeon_data, $target_room_id);
      if ($room === NULL) {
        throw new \RuntimeException(sprintf('Encounter transition contract violation: materialized room %s was not present in dungeon payload after instantiation.', $target_room_id));
      }
    }
    $timing_breakdown['materialize_room_ms'] = (microtime(TRUE) - $materialize_room_started_at) * 1000.0;

    $neighbor_preseed_started_at = microtime(TRUE);
    $this->enqueueLinkedRoomNeighborPreseed($campaign_id, $dungeon_data, $target_room_id);
    $timing_breakdown['neighbor_preseed_ms'] = (microtime(TRUE) - $neighbor_preseed_started_at) * 1000.0;

    $from_room = $dungeon_data['active_room_id'] ?? NULL;
    $dungeon_data['active_room_id'] = $target_room_id;
    $dungeon_data['current_room_id'] = $target_room_id;
    $runtime_dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($runtime_dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: campaign %d target room %s has no runtime dungeon_id for launch-slice provisioning.',
        $campaign_id,
        $target_room_id
      ));
    }
    if (!$this->h3ProjectionQueue instanceof H3ProjectionQueueService) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: H3 projection queue service is required for campaign %d room %s transition provisioning.',
        $campaign_id,
        $target_room_id
      ));
    }
    $launch_slice_scope = $this->resolveLaunchSliceRoomScopeFromDungeonData($dungeon_data, is_scalar($from_room) ? (string) $from_room : '');
    if ($launch_slice_scope === []) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: launch-slice scope is empty for campaign %d dungeon %s room %s.',
        $campaign_id,
        $runtime_dungeon_id,
        $target_room_id
      ));
    }
    $launch_slice_started_at = microtime(TRUE);
    $this->h3ProjectionQueue->provisionLaunchSliceNow($campaign_id, $runtime_dungeon_id, $launch_slice_scope);
    $timing_breakdown['launch_slice_ms'] = (microtime(TRUE) - $launch_slice_started_at) * 1000.0;
    $game_state['phase'] = 'encounter';
    $game_state['exploration']['previous_room'] = $from_room;

    $entry_resolution_started_at = microtime(TRUE);
    $entry_hex = $this->resolveTransitionEntryHex($room, $params, $capability);
    $entry_facing = isset($params['entry_facing']) ? (int) $params['entry_facing'] : 0;
    if ($actor_id) {
      $this->moveEntityToRoom(
        $dungeon_data,
        $actor_id,
        $target_room_id,
        $entry_hex,
        $entry_facing
      );
    }
    $timing_breakdown['entry_and_actor_move_ms'] = (microtime(TRUE) - $entry_resolution_started_at) * 1000.0;

    $room_scene_events_started_at = microtime(TRUE);
    $events = [];
    if (!empty($game_state['encounter_id']) && $from_room !== NULL && (string) $from_room !== $target_room_id) {
      $events = array_merge($events, $this->onExit($game_state, $dungeon_data, $campaign_id));
    }

    $events[] = GameEventLogger::buildEvent('room_entered', 'encounter', $actor_id, [
      'from_room' => $from_room,
      'to_room' => $target_room_id,
    ], (string) ($room['description'] ?? $room['name'] ?? ''));
    $events = array_values($events);

    $combat_context = $this->buildCombatEncounterContext($target_room_id, $dungeon_data, $game_state);
    if (!empty($combat_context['should_trigger'])) {
      $events = array_merge($events, $this->onEnter($combat_context, $game_state, $dungeon_data, $campaign_id));
    }
    else {
      $events = array_merge(
        $events,
        $this->startRoomSceneEncounter($actor_id, $target_room_id, $game_state, $dungeon_data, $campaign_id, $room)
      );
    }
    $timing_breakdown['room_scene_events_ms'] = (microtime(TRUE) - $room_scene_events_started_at) * 1000.0;

    // Persist the room-scene intro into the instantiated room chat so the UI can
    // render the authoritative room description on room entry.
    $room_intro_started_at = microtime(TRUE);
    $this->roomChatService->injectRoomSceneNarratorIntroIfNeeded($dungeon_data, $target_room_id);
    $timing_breakdown['room_intro_ms'] = (microtime(TRUE) - $room_intro_started_at) * 1000.0;

    $receipt_build_started_at = microtime(TRUE);
    $transition_navigation_receipt = $this->buildTransitionNavigationReceipt(
      $dungeon_data,
      is_scalar($from_room) ? (string) $from_room : '',
      $target_room_id,
      $entry_hex,
      $room
    );
    $timing_breakdown['build_navigation_receipt_ms'] = (microtime(TRUE) - $receipt_build_started_at) * 1000.0;

    $total_ms = (microtime(TRUE) - $timing_started_at) * 1000.0;
    if ($total_ms >= self::NAVIGATION_TIMING_SLOW_THRESHOLD_MS) {
      $this->logger->notice(
        'Navigation timing: enterRoomFramework slow (campaign={campaign_id}, actor={actor}, from_room={from_room}, target_room={target_room}, total_ms={total_ms}, rebuild_runtime_graph_ms={rebuild_runtime_graph_ms}, resolve_capability_ms={resolve_capability_ms}, materialize_room_ms={materialize_room_ms}, neighbor_preseed_ms={neighbor_preseed_ms}, launch_slice_ms={launch_slice_ms}, entry_and_actor_move_ms={entry_and_actor_move_ms}, room_scene_events_ms={room_scene_events_ms}, room_intro_ms={room_intro_ms}, build_navigation_receipt_ms={build_navigation_receipt_ms}, event_count={event_count})',
        [
          'campaign_id' => $campaign_id,
          'actor' => (string) ($actor_id ?? ''),
          'from_room' => (string) ($from_room ?? ''),
          'target_room' => $target_room_id,
          'total_ms' => round($total_ms, 2),
          'rebuild_runtime_graph_ms' => round((float) ($timing_breakdown['rebuild_runtime_graph_ms'] ?? 0.0), 2),
          'resolve_capability_ms' => round((float) ($timing_breakdown['resolve_capability_ms'] ?? 0.0), 2),
          'materialize_room_ms' => round((float) ($timing_breakdown['materialize_room_ms'] ?? 0.0), 2),
          'neighbor_preseed_ms' => round((float) ($timing_breakdown['neighbor_preseed_ms'] ?? 0.0), 2),
          'launch_slice_ms' => round((float) ($timing_breakdown['launch_slice_ms'] ?? 0.0), 2),
          'entry_and_actor_move_ms' => round((float) ($timing_breakdown['entry_and_actor_move_ms'] ?? 0.0), 2),
          'room_scene_events_ms' => round((float) ($timing_breakdown['room_scene_events_ms'] ?? 0.0), 2),
          'room_intro_ms' => round((float) ($timing_breakdown['room_intro_ms'] ?? 0.0), 2),
          'build_navigation_receipt_ms' => round((float) ($timing_breakdown['build_navigation_receipt_ms'] ?? 0.0), 2),
          'event_count' => count($events),
        ]
      );
    }

    return [
      'transitioned' => $from_room !== $target_room_id,
      'from_room' => $from_room,
      'to_room' => $target_room_id,
      'entry_hex' => $entry_hex,
      'navigation_capabilities' => $transition_navigation_receipt['navigation_capabilities'],
      'navigation' => $transition_navigation_receipt,
      'events' => $events,
      'time_effects' => $this->buildTransitionTimeEffects($actor_id, $from_room, $target_room_id, $capability, $params),
      'mutations' => $actor_id ? [
        ['entity' => $actor_id, 'field' => 'placement.room_id', 'to' => $target_room_id],
        ['entity' => $actor_id, 'field' => 'placement.hex', 'to' => $entry_hex],
        ['entity' => $actor_id, 'field' => 'placement.facing', 'to' => $this->normalizeFacingDirection($entry_facing)],
        ['entity' => $actor_id, 'field' => 'placement.h3_index_res14', 'to' => $this->resolveRoomHexH3IndexRes14($dungeon_data, $target_room_id, $entry_hex)],
      ] : [],
    ];
  }

  /**
   * Bootstrap the active room into room-scene encounter mode without transition flow.
   *
   * This is a read-lane-safe initializer for fresh campaign startup. It avoids
   * transition validation, graph mutation/materialization, and launch-slice
   * provisioning while still establishing the room-scene encounter framework.
   */
  public function bootstrapRoomSceneFramework(string $room_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return ['error' => 'No room specified for room-scene bootstrap.'];
    }

    $room = $this->findRoomById($dungeon_data, $room_id);
    if ($room === NULL) {
      return ['error' => sprintf("Room '%s' does not exist.", $room_id)];
    }

    $dungeon_data['active_room_id'] = $room_id;
    $dungeon_data['current_room_id'] = $room_id;
    $game_state['phase'] = 'encounter';
    if (!isset($game_state['exploration']) || !is_array($game_state['exploration'])) {
      $game_state['exploration'] = [];
    }
    if (!array_key_exists('previous_room', $game_state['exploration'])) {
      $game_state['exploration']['previous_room'] = NULL;
    }

    $events = [
      GameEventLogger::buildEvent('room_entered', 'encounter', NULL, [
        'from_room' => NULL,
        'to_room' => $room_id,
      ], (string) ($room['description'] ?? $room['name'] ?? '')),
    ];

    $events = array_merge(
      $events,
      $this->startRoomSceneEncounter(NULL, $room_id, $game_state, $dungeon_data, $campaign_id, $room)
    );

    $this->roomChatService->injectRoomSceneNarratorIntroIfNeeded($dungeon_data, $room_id);

    return [
      'success' => TRUE,
      'events' => array_values($events),
      'mutations' => [],
      'time_effects' => [],
      'phase_transition' => NULL,
      'narration' => NULL,
    ];
  }

  /**
   * Builds a server-authoritative navigation receipt for in-session transitions.
   */
  protected function buildTransitionNavigationReceipt(
    array $dungeon_data,
    string $from_room_id,
    string $target_room_id,
    array $entry_hex,
    ?array $room = NULL
  ): array {
    $target_room_id = trim($target_room_id);
    if ($target_room_id === '') {
      throw new \RuntimeException('Encounter transition contract violation: target_room_id is required for navigation receipt.');
    }

    $room_payload = is_array($room) ? $room : ($this->findRoomById($dungeon_data, $target_room_id) ?? []);
    $navigation_capabilities = $this->requireNavigationService()
      ->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $target_room_id);

    $origin_room_id = trim($from_room_id);
    $receipt = [
      'target_room_id' => $target_room_id,
      'destination' => trim((string) ($room_payload['name'] ?? '')) !== ''
        ? (string) $room_payload['name']
        : $target_room_id,
      'room' => is_array($room_payload) ? $room_payload : [],
      'entities' => $this->collectTransitionRoomEntities($dungeon_data, $target_room_id),
      'connections' => $this->buildTransitionReceiptConnectionsFromCapabilities($navigation_capabilities, $target_room_id),
      'navigation_capabilities' => $navigation_capabilities,
      'entry_hex' => [
        'q' => (int) ($entry_hex['q'] ?? 0),
        'r' => (int) ($entry_hex['r'] ?? 0),
      ],
    ];

    if ($origin_room_id !== '') {
      $receipt['origin_room_id'] = $origin_room_id;
    }

    return $receipt;
  }

  /**
   * Build normalized runtime connections from navigation capabilities.
   *
   * @param array<int, array<string, mixed>> $capabilities
   *   Navigation capabilities authored for one active room.
   * @param string $active_room_id
   *   Active room owning those capabilities.
   *
   * @return array<int, array<string, mixed>>
   *   Connection payload rows keyed for client dedupe.
   */
  protected function buildTransitionReceiptConnectionsFromCapabilities(array $capabilities, string $active_room_id): array {
    $connections = [];
    $active_room_id = trim($active_room_id);

    foreach ($capabilities as $capability) {
      if (!is_array($capability)) {
        continue;
      }
      $target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
      if ($target_room_id === '') {
        continue;
      }

      $origin_room_id = trim((string) ($capability['origin_room_id'] ?? $active_room_id));
      if ($origin_room_id === '') {
        $origin_room_id = $active_room_id;
      }
      $connection_id = trim((string) ($capability['connection_id'] ?? ''));
      if ($connection_id === '') {
        $connection_id = sprintf('receipt-%s-%s', $origin_room_id, $target_room_id);
      }

      $available = !array_key_exists('available', $capability) || !empty($capability['available']);
      $connection = [
        'connection_id' => $connection_id,
        'from_room' => $origin_room_id,
        'to_room' => $target_room_id,
        'target_room_id' => $target_room_id,
        'available' => $available,
        'blocked' => !$available,
        'blocked_reason' => $available ? '' : (string) ($capability['blocked_reason'] ?? 'blocked'),
        'type' => (string) ($capability['type'] ?? $capability['connection_type'] ?? 'passage'),
      ];

      if (is_array($capability['origin_hex'] ?? NULL)) {
        $connection['from_hex'] = [
          'q' => (int) ($capability['origin_hex']['q'] ?? 0),
          'r' => (int) ($capability['origin_hex']['r'] ?? 0),
        ];
      }
      if (is_array($capability['target_hex'] ?? NULL)) {
        $connection['to_hex'] = [
          'q' => (int) ($capability['target_hex']['q'] ?? 0),
          'r' => (int) ($capability['target_hex']['r'] ?? 0),
        ];
      }

      $connections[] = $connection;
    }

    return $connections;
  }

  /**
   * Collect entities currently placed in one room for transition receipt sync.
   *
   * @return array<int, array<string, mixed>>
   *   Room-local entity payload rows.
   */
  protected function collectTransitionRoomEntities(array $dungeon_data, string $room_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return [];
    }

    $entities = [];
    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      if ($entity_room_id === $room_id) {
        $entities[] = $entity;
      }
    }

    return $entities;
  }

  /**
   * Rebuild runtime graph shape from campaign room and connector authority.
   *
   * This keeps transition consumers off stale payload graph snapshots while the
   * broader cutover away from direct dungeon_data graph ownership is in progress.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param array<string, mixed> $dungeon_data
   *   Current server snapshot payload.
   * @param string $requested_room_id
   *   Target room that may need to be included in the authoritative graph view.
   *
   * @return array<string, mixed>
   *   Rebuilt runtime graph payload.
   */
  protected function rebuildAuthoritativeRuntimeGraph(int $campaign_id, array $dungeon_data, string $requested_room_id = ''): array {
    if ($campaign_id <= 0 || !$this->runtimeGraphAssembler instanceof RuntimeGraphAssemblerService) {
      return $dungeon_data;
    }

    if (!$this->shouldRebuildTransitionNeighborhood($dungeon_data, $requested_room_id)) {
      return $dungeon_data;
    }

    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($dungeon_id === '') {
      return $dungeon_data;
    }

    $rebuilt = $this->runtimeGraphAssembler->buildRuntimeGraph($campaign_id, $dungeon_id, $dungeon_data, [
      'active_room_id' => trim((string) ($dungeon_data['active_room_id'] ?? '')),
      'requested_room_id' => trim($requested_room_id),
      // Transition rebuilds are bounded to the active-room frontier.
      'room_scope_depth' => 1,
    ]);
    if (!is_array($rebuilt)) {
      return $dungeon_data;
    }

    // Preserve coordinator persistence metadata across graph rebuild replacement.
    if (array_key_exists('__campaign_dungeon_row_id', $dungeon_data) && !array_key_exists('__campaign_dungeon_row_id', $rebuilt)) {
      $rebuilt['__campaign_dungeon_row_id'] = $dungeon_data['__campaign_dungeon_row_id'];
    }

    return $rebuilt;
  }

  /**
   * Decide whether transition should refresh its bounded neighborhood graph.
   *
   * Rebuild only when the requested transition room or one-hop connected room
   * for the active room is absent from the currently loaded payload.
   */
  protected function shouldRebuildTransitionNeighborhood(array $dungeon_data, string $requested_room_id): bool {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    $requested_room_id = trim($requested_room_id);
    if ($active_room_id === '') {
      return TRUE;
    }
    if ($this->findRoomById($dungeon_data, $active_room_id) === NULL) {
      return TRUE;
    }
    if ($requested_room_id !== '' && $this->findRoomById($dungeon_data, $requested_room_id) === NULL) {
      return TRUE;
    }

    $neighbor_room_ids = $this->collectActiveRoomNeighborIds($dungeon_data, $active_room_id);
    foreach ($neighbor_room_ids as $neighbor_room_id) {
      if ($this->findRoomById($dungeon_data, $neighbor_room_id) === NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Collect one-hop neighboring room IDs for the active room from connections.
   *
   * @return array<int, string>
   *   Directly connected room ids.
   */
  protected function collectActiveRoomNeighborIds(array $dungeon_data, string $active_room_id): array {
    $active_room_id = trim($active_room_id);
    if ($active_room_id === '') {
      return [];
    }

    $connections = is_array($dungeon_data['hex_map']['connections'] ?? NULL) ? $dungeon_data['hex_map']['connections'] : [];
    $neighbors = [];
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) (
        $connection['from_room']
        ?? $connection['from_room_id']
        ?? ($connection['from']['room_id'] ?? '')
      ));
      $to_room_id = trim((string) (
        $connection['to_room']
        ?? $connection['to_room_id']
        ?? ($connection['to']['room_id'] ?? '')
      ));
      if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
        continue;
      }
      if ($from_room_id === $active_room_id) {
        $neighbors[$to_room_id] = TRUE;
      }
      elseif ($to_room_id === $active_room_id) {
        $neighbors[$from_room_id] = TRUE;
      }
    }

    return array_values(array_keys($neighbors));
  }

  /**
   * Materialize a canonical room template into the campaign dungeon on first travel.
   */
  protected function materializeCanonicalRoomForTransition(
    int $campaign_id,
    array &$dungeon_data,
    string $target_room_id,
    ?array $capability
  ): bool {
    $target_room_id = trim($target_room_id);
    if ($campaign_id <= 0 || $target_room_id === '') {
      return FALSE;
    }
    if ((string) ($capability['destination_type'] ?? 'room') !== 'room') {
      return FALSE;
    }
    return $this->requireNavigationRuntime()->materializeCanonicalRoomForCampaign(
      $campaign_id,
      $dungeon_data,
      $target_room_id,
      [
        'origin_room_id' => trim((string) ($capability['origin_room_id'] ?? ($dungeon_data['active_room_id'] ?? ''))),
        'origin_hex' => is_array($capability['origin_hex'] ?? NULL) ? $capability['origin_hex'] : NULL,
        'target_hex' => is_array($capability['target_hex'] ?? NULL) ? $capability['target_hex'] : NULL,
        'explored' => TRUE,
        'visibility' => 'visible',
      ]
    );
  }

  /**
   * Materialize one-hop linked room neighbors after entering a room.
   *
   * This pre-seeds campaign room state for directly connected destinations to
   * avoid first-visit races on immediate follow-up navigation.
   */
  protected function materializeLinkedRoomNeighborsForCampaign(int $campaign_id, array &$dungeon_data, string $anchor_room_id): void {
    $anchor_room_id = trim($anchor_room_id);
    if ($campaign_id <= 0 || $anchor_room_id === '') {
      return;
    }

    $connections = is_array($dungeon_data['hex_map']['connections'] ?? NULL) ? $dungeon_data['hex_map']['connections'] : [];
    if ($connections === []) {
      return;
    }

    $neighbors = [];
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $destination_type = strtolower(trim((string) ($connection['destination_type'] ?? 'room')));
      if ($destination_type !== '' && $destination_type !== 'room') {
        continue;
      }

      $from_room_id = trim((string) (
        $connection['from_room']
        ?? $connection['from_room_id']
        ?? ($connection['from']['room_id'] ?? '')
      ));
      $to_room_id = trim((string) (
        $connection['to_room']
        ?? $connection['to_room_id']
        ?? ($connection['to']['room_id'] ?? '')
      ));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }
      if ($from_room_id === $to_room_id) {
        continue;
      }

      if ($from_room_id === $anchor_room_id) {
        $neighbor_room_id = $to_room_id;
        $origin_hex = is_array($connection['from_hex'] ?? NULL) ? $connection['from_hex'] : NULL;
        $target_hex = is_array($connection['to_hex'] ?? NULL) ? $connection['to_hex'] : NULL;
      }
      elseif ($to_room_id === $anchor_room_id) {
        $bidirectional = !array_key_exists('bidirectional', $connection) || !empty($connection['bidirectional']);
        if (!$bidirectional) {
          continue;
        }
        $neighbor_room_id = $from_room_id;
        $origin_hex = is_array($connection['to_hex'] ?? NULL) ? $connection['to_hex'] : NULL;
        $target_hex = is_array($connection['from_hex'] ?? NULL) ? $connection['from_hex'] : NULL;
      }
      else {
        continue;
      }

      if ($neighbor_room_id === '' || $neighbor_room_id === $anchor_room_id) {
        continue;
      }
      $neighbors[$neighbor_room_id] = [
        'origin_hex' => $origin_hex,
        'target_hex' => $target_hex,
      ];
    }

    foreach ($neighbors as $neighbor_room_id => $neighbor) {
      if ($this->findRoomById($dungeon_data, $neighbor_room_id) !== NULL) {
        continue;
      }

      $materialize_capability = [
        'destination_type' => 'room',
        'origin_room_id' => $anchor_room_id,
      ];
      if (is_array($neighbor['origin_hex'] ?? NULL) && isset($neighbor['origin_hex']['q'], $neighbor['origin_hex']['r'])) {
        $materialize_capability['origin_hex'] = [
          'q' => (int) $neighbor['origin_hex']['q'],
          'r' => (int) $neighbor['origin_hex']['r'],
        ];
      }
      if (is_array($neighbor['target_hex'] ?? NULL) && isset($neighbor['target_hex']['q'], $neighbor['target_hex']['r'])) {
        $materialize_capability['target_hex'] = [
          'q' => (int) $neighbor['target_hex']['q'],
          'r' => (int) $neighbor['target_hex']['r'],
        ];
      }

      if (!$this->materializeCanonicalRoomForTransition($campaign_id, $dungeon_data, $neighbor_room_id, $materialize_capability)) {
        throw new \RuntimeException(sprintf(
          'Encounter transition preseed contract violation: linked room %s from anchor room %s could not be materialized from canonical storage.',
          $neighbor_room_id,
          $anchor_room_id
        ));
      }

      $this->logger->info('Encounter transition preseed materialized linked room: campaign={campaign_id} anchor_room={anchor_room} linked_room={linked_room}', [
        'campaign_id' => $campaign_id,
        'anchor_room' => $anchor_room_id,
        'linked_room' => $neighbor_room_id,
      ]);
    }
  }

  /**
   * Queue non-blocking linked-room preseed after successful room entry.
   */
  protected function enqueueLinkedRoomNeighborPreseed(int $campaign_id, array $dungeon_data, string $anchor_room_id): void {
    $anchor_room_id = trim($anchor_room_id);
    if ($campaign_id <= 0 || $anchor_room_id === '') {
      return;
    }

    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($dungeon_id === '') {
      return;
    }

    \Drupal::queue('dungeoncrawler_content.navigation_neighbor_preseed')
      ->createItem([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'anchor_room_id' => $anchor_room_id,
      ]);
  }

  /**
   * Process one background neighbor-preseed queue item.
   */
  public function processLinkedRoomPreseedQueueItem(int $campaign_id, string $dungeon_id, string $anchor_room_id): void {
    $campaign_id = (int) $campaign_id;
    $dungeon_id = trim($dungeon_id);
    $anchor_room_id = trim($anchor_room_id);
    if ($campaign_id <= 0 || $dungeon_id === '' || $anchor_room_id === '') {
      throw new \InvalidArgumentException('Linked-room preseed queue contract violation: campaign_id, dungeon_id, and anchor_room_id are required.');
    }

    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf(
        'Linked-room preseed queue contract violation: campaign %d dungeon %s not found.',
        $campaign_id,
        $dungeon_id
      ));
    }

    $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Linked-room preseed queue contract violation: campaign %d dungeon %s has invalid dungeon_data JSON.',
        $campaign_id,
        $dungeon_id
      ));
    }

    $before_room_count = count((array) ($dungeon_data['rooms'] ?? []));
    $this->materializeLinkedRoomNeighborsForCampaign($campaign_id, $dungeon_data, $anchor_room_id);
    $after_room_count = count((array) ($dungeon_data['rooms'] ?? []));

    if ($after_room_count === $before_room_count) {
      return;
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated' => time(),
      ])
      ->condition('id', (int) $row['id'])
      ->execute();
  }

  /**
   * Resolve transition frontier as active room plus direct neighbors.
   *
   * @return array<int, string>
   *   Provisioning scope for launch slice.
   */
  protected function resolveLaunchSliceRoomScopeFromDungeonData(array $dungeon_data, string $previous_room_id = ''): array {
    $rooms = [];
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
      if ($room_id !== '') {
        $rooms[$room_id] = TRUE;
      }
    }

    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? $dungeon_data['current_room_id'] ?? ''));
    $previous_room_id = trim($previous_room_id);
    $scope = [];
    if ($active_room_id !== '' && isset($rooms[$active_room_id])) {
      $scope[$active_room_id] = TRUE;
    }
    if ($previous_room_id !== '' && isset($rooms[$previous_room_id])) {
      $scope[$previous_room_id] = TRUE;
    }

    if (
      isset($rooms['ltba-tavern-room'], $rooms['ltba-streets-room'])
      && (isset($scope['ltba-tavern-room']) || isset($scope['ltba-streets-room']))
    ) {
      $scope['ltba-tavern-room'] = TRUE;
      $scope['ltba-streets-room'] = TRUE;
    }

    if ($scope === [] && $active_room_id !== '') {
      $scope[$active_room_id] = TRUE;
    }
    if ($scope === [] && $rooms !== []) {
      $scope[(string) array_key_first($rooms)] = TRUE;
    }

    return array_values(array_keys($scope));
  }

  /**
   * Resolve canonical transition entry hex inside the destination room.
   */
  protected function resolveTransitionEntryHex(array $room, array $params, ?array $capability): array {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    $room_hexes = array_values(array_filter(
      (array) ($room['hexes'] ?? []),
      static fn($hex): bool => is_array($hex) && isset($hex['q'], $hex['r'])
    ));
    if ($room_hexes === []) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: room %s has no placement hexes.',
        $room_id !== '' ? $room_id : 'unknown'
      ));
    }

    $candidates = [
      $params['entry_hex'] ?? NULL,
      $params['target_hex'] ?? NULL,
      $capability['target_hex'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      $normalized = $this->normalizeTransitionHexCandidate($candidate);
      if ($normalized === NULL) {
        continue;
      }
      foreach ($room_hexes as $room_hex) {
        if ((int) $room_hex['q'] === $normalized['q'] && (int) $room_hex['r'] === $normalized['r']) {
          return $normalized;
        }
      }
    }

    foreach ($room_hexes as $room_hex) {
      if (!empty($room_hex['is_entry']) || !empty($room_hex['entry'])) {
        return [
          'q' => (int) $room_hex['q'],
          'r' => (int) $room_hex['r'],
        ];
      }
    }

    return [
      'q' => (int) ($room_hexes[0]['q'] ?? 0),
      'r' => (int) ($room_hexes[0]['r'] ?? 0),
    ];
  }

  /**
   * Normalize one transition-hex candidate payload.
   */
  protected function normalizeTransitionHexCandidate(mixed $candidate): ?array {
    if (!is_array($candidate) || !isset($candidate['q'], $candidate['r'])) {
      return NULL;
    }

    return [
      'q' => (int) $candidate['q'],
      'r' => (int) $candidate['r'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onEnter(array $context, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $game_state['phase'] = 'encounter';
    $events = [];

    $encounter_context = $context['encounter_context'] ?? [];
    $room_id = $encounter_context['room_id'] ?? ($dungeon_data['active_room_id'] ?? NULL);
    $game_state['encounter_context'] = $encounter_context + [
      'room_id' => $room_id,
      'mode' => 'hostile_combat',
      'started_at' => $game_state['encounter_context']['started_at'] ?? date('c'),
    ];
    $enemies = $encounter_context['enemies'] ?? [];

    try {
      // Build participant list from entities in the room.
      $participants = $this->buildParticipantList($dungeon_data, $room_id, $enemies);

      // Create encounter in the combat_encounters table.
      $encounter_id = $this->combatEngine->createEncounter($campaign_id, $room_id, $participants, [
        'room_id' => $room_id,
      ]);

      if ($encounter_id) {
        // Start the encounter (rolls initiative, sorts order, starts round 1).
        $start_result = $this->combatEngine->startEncounter($encounter_id);

        $game_state['encounter_id'] = $encounter_id;
        $game_state['round'] = 1;
        $events = array_merge($events, $this->buildRoundStartEvents(1, $game_state, $dungeon_data, $campaign_id, $room_id));

        // Set up the first turn.
        $initiative_order = $start_result['encounter']['participants'] ?? [];
        if (!empty($initiative_order)) {
          $first = $initiative_order[0];
          $game_state['turn'] = [
            'entity' => $first['entity_id'] ?? NULL,
            'index' => 0,
            'actions_remaining' => 3,
            'attacks_this_turn' => 0,
            'reaction_available' => TRUE,
            'delayed' => FALSE,
          ];
          if (!empty($first['entity_id'])) {
            $events = array_merge($events, $this->buildTurnStartEvents((string) $first['entity_id'], $game_state, $dungeon_data, $campaign_id, $room_id));
            $events = array_merge($events, $this->buildTurnStartSearchEvents((string) $first['entity_id'], $game_state, $dungeon_data, $campaign_id));
          }
        }

        $game_state['initiative_order'] = $initiative_order;

        $initial_turn_events = [];
        if (!empty($initiative_order)) {
          $first = $initiative_order[0];
          $first_entity = $first['entity_id'] ?? NULL;
          $first_team = $first['team'] ?? 'enemy';
          if ($first_entity && $first_team !== 'player') {
            $npc_result = $this->autoPlayNpcTurn($encounter_id, (string) $first_entity, $game_state, $dungeon_data, $campaign_id);
            $initial_turn_events = $npc_result['events'] ?? [];

            $initial_advance = $this->processEndTurn($encounter_id, (string) $first_entity, $game_state, $dungeon_data, $campaign_id);
            $initial_turn_events = array_merge($initial_turn_events, $initial_advance['npc_events'] ?? []);
          }
        }

        $events[] = GameEventLogger::buildEvent('encounter_started', 'encounter', NULL, [
          'encounter_id' => $encounter_id,
          'room_id' => $room_id,
          'participants' => count($participants),
          'initiative_order' => $initiative_order,
        ]);
        if (!empty($initial_turn_events)) {
          $events = array_merge($events, $initial_turn_events);
        }

        // AI GM narration for encounter start.
        $gm_narration = $this->aiGmService->narrateEncounterStart([
          'participants' => $participants,
          'room_name' => $room_id,
          'reason' => $context['reason'] ?? 'Hostile creatures detected',
        ], $dungeon_data, $campaign_id);
        if ($gm_narration) {
          $events[] = GameEventLogger::buildEvent('gm_narration', 'encounter', NULL, [
            'trigger' => 'encounter_start',
          ], $gm_narration);
        }

        // Queue encounter start for perception-filtered narration.
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => 'GM',
          'speaker_type' => 'gm',
          'speaker_ref' => '',
          'content' => sprintf('Combat begins! %s', $context['reason'] ?? 'Hostile creatures detected!'),
          'visibility' => 'public',
          'mechanical_data' => [
            'encounter_id' => $encounter_id,
            'participant_count' => count($participants),
            'round' => 1,
          ],
        ], $room_id);
        $initiative_summary = [];
        foreach ($participants as $participant) {
          $initiative_value = $participant['initiative'] ?? $participant['initiative_total'] ?? NULL;
          if (!is_numeric($initiative_value)) {
            continue;
          }
          $initiative_summary[] = [
            'name' => $participant['name'] ?? $participant['display_name'] ?? ($participant['entity_id'] ?? 'Unknown'),
            'initiative' => (int) $initiative_value,
            'roll' => isset($participant['initiative_roll']) && is_numeric($participant['initiative_roll']) ? (int) $participant['initiative_roll'] : NULL,
          ];
        }
        if ($initiative_summary !== []) {
          $initiative_text = implode(', ', array_map(
            static fn(array $entry): string => sprintf('%s %d', $entry['name'], $entry['initiative']),
            $initiative_summary
          ));
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'initiative_set',
            'speaker' => 'System',
            'speaker_type' => 'system',
            'speaker_ref' => '',
            'content' => sprintf('Initiative order: %s.', $initiative_text),
            'mechanical_data' => [
              'encounter_id' => $encounter_id,
              'order' => $initiative_summary,
            ],
            'visibility' => 'public',
          ], $room_id);
        }

        // Mark the room's encounter as triggered.
        $this->markRoomEncounterTriggered($dungeon_data, $room_id);
      }
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to create encounter: @error', ['@error' => $e->getMessage()]);
      $events[] = GameEventLogger::buildEvent('encounter_start_failed', 'encounter', NULL, [
        'error' => $e->getMessage(),
      ]);
    }

    return [
      'events' => $events,
      'mutation_envelope' => $this->buildMutationEnvelopeFromRuntimeContext($campaign_id, $game_state, $dungeon_data, []),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $encounter_id = $game_state['encounter_id'] ?? NULL;
    $events = [];

    if ($encounter_id) {
      try {
        // End the encounter in the combat engine.
        $this->combatEngine->endEncounter(
          $encounter_id,
          'victory',
          'encounter framework cleanup'
        );
      }
      catch (\Throwable $e) {
        $this->logger->error('Failed to end encounter: @error', ['@error' => $e->getMessage()]);
      }

      $events[] = GameEventLogger::buildEvent('encounter_ended', 'encounter', NULL, [
        'encounter_id' => $encounter_id,
        'final_round' => $game_state['round'] ?? NULL,
      ]);

      // AI GM narration for encounter end.
      $gm_narration = $this->aiGmService->narrateEncounterEnd([
        'encounter_id' => $encounter_id,
        'final_round' => $game_state['round'] ?? NULL,
        'victory' => TRUE,
      ], $dungeon_data, $campaign_id);
      if ($gm_narration) {
        $events[] = GameEventLogger::buildEvent('gm_narration', 'encounter', NULL, [
          'trigger' => 'encounter_end',
        ], $gm_narration);
      }

      // Queue encounter end for perception-filtered narration.
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'action',
        'speaker' => 'GM',
        'speaker_type' => 'gm',
        'speaker_ref' => '',
        'content' => sprintf('The encounter ends after %d rounds.', $game_state['round'] ?? 0),
        'visibility' => 'public',
        'mechanical_data' => [
          'encounter_id' => $encounter_id,
          'final_round' => $game_state['round'] ?? NULL,
        ],
      ]);
    }

    // Clean up encounter state from game_state, but preserve it for history.
    $game_state['last_encounter'] = [
      'encounter_id' => $encounter_id,
      'final_round' => $game_state['round'] ?? NULL,
      'ended_at' => date('c'),
    ];

    $game_state['encounter_id'] = NULL;
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['initiative_order'] = NULL;

    return [
      'events' => $events,
      'mutation_envelope' => $this->buildMutationEnvelopeFromRuntimeContext($campaign_id, $game_state, $dungeon_data, []),
    ];
  }

  /**
   * {@inheritdoc}
   */
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
        $class_data = CharacterManager::CLASSES[$class_id] ?? [];
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
    return $this->encounterActionExecutor->processStrike(
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      function (string $aid, array $action_params, array $dungeon, ?int $cid): array {
        return $this->resolveStrikeWeapon($aid, $action_params, $dungeon, $cid);
      }
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
      $dungeon_data
    );
  }

  /**
   * Processes a spell cast during encounter.
   */
  protected function processCastSpell(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->encounterActionExecutor->processCastSpell(
      $encounter_id,
      $actor_id,
      $target_id,
      $params,
      $game_state,
      $dungeon_data,
      $campaign_id,
      fn(array $prepared, string $name, string $id): bool => $this->preparedSpellListContainsSpell($prepared, $name, $id),
      function (array &$canonical_state, bool $is_focus_spell, int $slot_level, int $remaining): void {
        $this->applyCanonicalStateAfterSpellConsume($canonical_state, $is_focus_spell, $slot_level, $remaining);
      },
      function (?int $eid, string $aid, int $cid, array &$dungeon, ?array $canonical_state): void {
        $this->syncCanonicalSpellcastingProjectionForActor($eid, $aid, $cid, $dungeon, $canonical_state);
      },
      fn(string $message, bool $is_focus_spell, int $slot_level): string => $this->normalizeSpellResourceErrorMessage($message, $is_focus_spell, $slot_level)
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
   * Builds and queues narrator-visible round-start chat events.
   */
  protected function buildRoundStartEvents(int $round, array $game_state, array $dungeon_data, int $campaign_id, ?string $room_id = NULL): array {
    $round_narration = $this->aiGmService->narrateRoundStart($round, $game_state, $dungeon_data, $campaign_id);
    $content = $round_narration ?: sprintf('Round %d begins.', $round);

    $content = $this->prefixEncounterChatLine(
      $this->captureEncounterTurnContext($game_state, $dungeon_data, NULL, [
        'actor_name' => 'Narrator',
        'turn_index_raw' => NULL,
        'turn_index_human' => NULL,
      ]),
      $content
    );

    $resolved_room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL));
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'round_start',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => '',
      'content' => $content,
      'visibility' => 'public',
      'mechanical_data' => [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'actor_name' => 'Narrator',
      ],
    ], $resolved_room_id, $game_state);

    return [
      GameEventLogger::buildEvent('round_start', 'encounter', NULL, [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'actor_name' => 'Narrator',
        'turn_index' => -1,
      ], $content),
    ];
  }

  /**
   * Builds and queues actor turn-start chat events.
   */
  protected function buildTurnStartEvents(string $entity_id, array $game_state, array $dungeon_data, int $campaign_id, ?string $room_id = NULL): array {
    $actor_name = $this->resolveEntityName($entity_id, $game_state, $dungeon_data);
    $round = $game_state['round'] ?? NULL;
    $resolved_room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL));
    $turn_index = $game_state['turn']['index'] ?? NULL;
    $total_turns = is_array($game_state['initiative_order'] ?? NULL) ? count($game_state['initiative_order']) : NULL;
    $actions_available = $game_state['turn']['actions_remaining'] ?? NULL;
    $content = sprintf("%s's turn begins.", $actor_name);
    $content = $this->prefixEncounterChatLine(
      $this->captureEncounterTurnContext($game_state, $dungeon_data, $entity_id, ['actor_name' => $actor_name]),
      $content
    );
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'turn_start',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => $entity_id,
      'content' => $content,
      'visibility' => 'public',
      'mechanical_data' => [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'entity_id' => $entity_id,
        'actor_name' => $actor_name,
        'turn_index' => $turn_index,
        'total_turns' => $total_turns,
        'actions_available' => $actions_available,
      ],
    ], $resolved_room_id, $game_state);

    return [
      GameEventLogger::buildEvent('turn_start', 'encounter', $entity_id, [
        'round' => $round,
        'room_id' => $resolved_room_id,
        'actor_name' => $actor_name,
        'turn_index' => $turn_index,
        'total_turns' => $total_turns,
        'actions_available' => $actions_available,
      ], $content),
    ];
  }

  /**
   * Runs automatic Search at the start of an actor turn.
   */
  protected function buildTurnStartSearchEvents(string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$this->explorationPhaseHandler) {
      return [];
    }

    $room_id = (string) ($dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? ''));
    $params = [
      'search_mode' => 'automatic',
      'trigger' => 'turn_start',
    ];
    if ($room_id !== '') {
      $params['room_id'] = $room_id;
    }

    $result = $this->explorationPhaseHandler->processSearch($entity_id, $params, $game_state, $dungeon_data, $campaign_id);

    $this->maybeQueueMechanicalSystemLogEntry([
      'campaign_id' => $campaign_id,
      'dungeon_data' => $dungeon_data,
      'type' => 'search',
      'actor_id' => $entity_id,
      'target_id' => NULL,
      'params' => $params,
      'result' => $result,
      'game_state' => $game_state,
    ]);

    $discoveries = $this->buildPublicSearchDiscoveries($result['discoveries'] ?? []);
    $narration = $result['narration'] ?? NULL;
    if ($discoveries === [] && (!is_string($narration) || trim($narration) === '')) {
      return [];
    }

    return [
      GameEventLogger::buildEvent('search', 'encounter', $entity_id, [
        'discoveries' => $discoveries,
        'round' => $game_state['round'] ?? NULL,
        'trigger' => 'turn_start',
        'room_id' => $room_id !== '' ? $room_id : NULL,
      ], $narration),
    ];
  }

  /**
   * Processes end-of-turn: advance to next combatant, auto-play NPCs.
   */
  protected function processEndTurn(?int $encounter_id, ?string $actor_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
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
    $new_round = NULL;
    $round_advances = 0;

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
        'mutations' => [],
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

    // If next combatant is NPC/enemy, auto-play or explicitly pass their turn.
    if ($next_team !== 'player') {
      $npc_result = ($encounter_id && !$this->isRoomSceneMode($game_state))
        ? $this->autoPlayNpcTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id)
        : $this->passRoomActorTurn((string) $next_entity, $game_state, $dungeon_data, $campaign_id);
      $npc_events = array_merge($npc_events, $npc_result['events'] ?? []);

      // After NPC turn, recursively advance until the next player actor.
      if ($this->hasActivePlayerParticipant($game_state)) {
        $further = $this->processEndTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id);
        $npc_events = array_merge($npc_events, $further['npc_events'] ?? []);
        if (!$new_round && !empty($further['new_round'])) {
          $new_round = $further['new_round'];
        }
        $round_advances += (int) ($further['round_advances'] ?? 0);
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
      'mutations' => [],
      'actions_remaining_before_end' => $actions_remaining_before_end,
    ];
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

    $insert_at = count($original_order);
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

  /**
   * Auto-plays a non-player combatant's turn using AI or fallback logic.
   */
  protected function autoPlayNpcTurn(int $encounter_id, string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->actorAutoplayCoordinator->autoPlayTurn(
      $encounter_id,
      $entity_id,
      $game_state,
      $dungeon_data,
      $campaign_id,
      fn(string $actor_id, array $state, array $dungeon): array => $this->buildNpcContext($actor_id, $state, $dungeon),
      fn(string $actor_id, array $state, int $cid, ?array $ai_seed): array => $this->buildNpcTurnPlan($actor_id, $state, $cid, $ai_seed),
      function (int $eid, string $actor_id, string $target_id, array &$state, array &$dungeon, int $cid): array {
        return $this->processStrike($eid, $actor_id, $target_id, [], $state, $dungeon, $cid);
      },
      function (string $target_id, string $actor_id, array &$state, array &$events, array $dungeon, int $cid): void {
        $this->checkEntityDefeated($target_id, $actor_id, $state, $events, $dungeon, $cid);
      },
      fn(string $actor_id, array $state): ?string => $this->findNearestAlivePlayer($actor_id, $state),
      function (string $actor_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $cid): array {
        return $this->buildNpcChooseNotToActEvents($actor_id, $decision_reason, $decision_basis, $state, $dungeon, $cid);
      },
      function (string $actor_id, array $pending_dialogue, array &$state, array &$dungeon, int $cid, string $decision_intent): array {
        return $this->resolvePendingEncounterDialogueTurn($actor_id, $pending_dialogue, $state, $dungeon, $cid, $decision_intent);
      }
    );
  }

  /**
   * Room-scene NPCs must still make an explicit turn decision.
   */
  protected function passRoomActorTurn(string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->actorAutoplayCoordinator->passRoomActorTurn(
      $entity_id,
      $game_state,
      $dungeon_data,
      $campaign_id,
      function (string $actor_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $cid): array {
        return $this->buildNpcChooseNotToActEvents($actor_id, $decision_reason, $decision_basis, $state, $dungeon, $cid);
      },
      function (string $actor_id, array $pending_dialogue, array &$state, array &$dungeon, int $cid, string $decision_intent): array {
        return $this->resolvePendingEncounterDialogueTurn($actor_id, $pending_dialogue, $state, $dungeon, $cid, $decision_intent);
      }
    );
  }

  /**
   * Build the canonical explicit "choose not to act" turn closeout for NPCs.
   */
  protected function buildNpcChooseNotToActEvents(
    string $entity_id,
    string $decision_reason,
    array $decision_basis,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    return $this->actorAutoplayCoordinator->buildChooseNotToActEvents(
      $entity_id,
      $decision_reason,
      $decision_basis,
      $game_state,
      $dungeon_data,
      $campaign_id,
      fn(string $actor_id, array $state, array $dungeon): string => $this->resolveEntityName($actor_id, $state, $dungeon),
      function (int $cid, array &$dungeon, array $event, ?string $room_id, ?array $state_override): array {
        return $this->queueNarrationEvent($cid, $dungeon, $event, $room_id, $state_override);
      }
    );
  }

  protected function resolvePendingEncounterDialogueTurn(
    string $entity_id,
    array $pending_dialogue,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    string $decision_intent
  ): array {
    return $this->actorAutoplayCoordinator->resolvePendingEncounterDialogueTurn(
      $entity_id,
      $pending_dialogue,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $decision_intent,
      fn(string $actor_id, array $state, array $dungeon): string => $this->resolveEntityName($actor_id, $state, $dungeon),
      function (int $cid, array &$dungeon, array $event, ?string $room_id, ?array $state_override): array {
        return $this->queueNarrationEvent($cid, $dungeon, $event, $room_id, $state_override);
      }
    );
  }

  public function advanceNonPlayerTurnsToNextPlayer(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->roomSceneEncounterCoordinator->advanceNonPlayerTurnsToNextPlayer(
      $game_state,
      $dungeon_data,
      $campaign_id,
      self::ROOM_SCENE_ERR_MISSING_PLAYER_PARTICIPANT,
      fn(array $state): bool => $this->isRoomSceneMode($state),
      function (array $initiative_order, string $error_code): void {
        $this->assertInitiativeHasPlayer($initiative_order, $error_code);
      },
      fn(string $entity_id, array $state): string => $this->resolveInitiativeParticipantTeam($entity_id, $state),
      function (string $entity_id, array &$state, array &$dungeon, int $cid): array {
        return $this->passRoomActorTurn($entity_id, $state, $dungeon, $cid);
      },
      function (int $encounter_id, string $entity_id, array &$state, array &$dungeon, int $cid): array {
        return $this->processEndTurn($encounter_id, $entity_id, $state, $dungeon, $cid);
      }
    );
  }

  /**
   * Ensure room-scene encounter initiative includes at least one player actor.
   *
   * If the current room-scene encounter is missing player participants, the
   * encounter is rebuilt from current room entities.
   */
  public function ensureRoomScenePlayerParticipant(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->roomSceneEncounterCoordinator->ensureRoomScenePlayerParticipant(
      $game_state,
      $dungeon_data,
      $campaign_id,
      self::ROOM_SCENE_ERR_RESEED_MISSING_ROOM,
      self::ROOM_SCENE_ERR_RESEED_NO_PLAYER_CANDIDATE,
      fn(array $state): bool => $this->isRoomSceneMode($state),
      fn(array $initiative_order): bool => $this->initiativeOrderHasPlayer($initiative_order),
      fn(array $dungeon, string $room_id): array => $this->buildRoomEncounterTurnOrder($dungeon, $room_id),
      function (array $initiative_order, string $error_code): void {
        $this->assertInitiativeHasPlayer($initiative_order, $error_code);
      },
      function (int $encounter_id, string $status, string $reason): void {
        $this->combatEngine->endEncounter($encounter_id, $status, $reason);
      },
      function (?string $actor_id, string $room_id, array &$state, array &$dungeon, int $cid, ?array $room, ?string $narration): array {
        return $this->startRoomSceneEncounter($actor_id, $room_id, $state, $dungeon, $cid, $room, $narration);
      }
    );
  }

  protected function initiativeOrderHasPlayer(array $initiative_order): bool {
    return $this->roomSceneEncounterCoordinator->initiativeOrderHasPlayer($initiative_order);
  }

  protected function assertInitiativeHasPlayer(array $initiative_order, string $error_code): void {
    $this->roomSceneEncounterCoordinator->assertInitiativeHasPlayer($initiative_order, $error_code);
  }

  protected function resolveInitiativeParticipantTeam(string $entity_id, array $game_state): string {
    return $this->roomSceneEncounterCoordinator->resolveInitiativeParticipantTeam($entity_id, $game_state);
  }

  /**
   * Build canonical NPC tactical intent contract from psychology + tactical state.
   *
   * The returned envelope is deterministic and reused across all actions in an
   * NPC turn so intent continuity stays stable from action 1/2/3.
   */
  protected function buildNpcTacticalIntentContract(string $entity_id, array $game_state, int $campaign_id): array {
    $npc = $this->findCombatant($entity_id, $game_state);
    $profile = $this->loadCombatantPsychologyProfile($entity_id, $game_state, $campaign_id);
    $profile_present = is_array($profile) && $profile !== [];
    $axes = $this->normalizeDecisionPersonalityAxes(is_array($profile['personality_axes'] ?? NULL) ? $profile['personality_axes'] : []);
    $goals = $this->resolveActorGoals($profile);
    $attitude = $this->normalizeNpcAttitude((string) ($profile['attitude'] ?? 'indifferent')) ?? 'indifferent';
    $boldness = (int) ($axes['boldness'] ?? 5);
    $empathy = (int) ($axes['empathy'] ?? 5);
    $discipline = (int) ($axes['discipline'] ?? 5);
    $cunning = (int) ($axes['cunning'] ?? 5);
    $hp_ratio = $this->hpRatio($npc ?? []);
    $nearest_player = $this->findNearestAlivePlayer($entity_id, $game_state);
    $has_adjacent_player = $this->hasAdjacentAlivePlayer($npc, $game_state);

    $intent = 'aggressive_engage';
    $action_sequence = $has_adjacent_player
      ? ['strike', 'strike', 'strike']
      : ['stride', 'strike', 'strike'];
    $target_strategy = 'nearest';
    $decision_reason = 'Default aggressive engagement: close distance and pressure the nearest threat.';

    if ($nearest_player === NULL) {
      $intent = 'no_targets';
      $action_sequence = ['end_turn'];
      $target_strategy = 'none';
      $decision_reason = 'No valid player target is available.';
    }
    elseif (($boldness <= 4 || $this->motivationSignalsSelfPreservation($profile ?? [])) && $hp_ratio <= 0.35) {
      $intent = 'self_preserve';
      $action_sequence = ['stride', 'stride', 'interact'];
      $target_strategy = 'nearest';
      $decision_reason = 'Wounded or survival-motivated profile favors retreat/reposition over direct engagement.';
    }
    elseif (in_array($attitude, ['friendly', 'helpful'], TRUE) && $empathy >= 7 && $has_adjacent_player) {
      $intent = 'deescalate';
      $action_sequence = ['talk', 'stride', 'talk'];
      $target_strategy = 'nearest';
      $decision_reason = 'Friendly and empathetic profile attempts de-escalation before violence.';
    }
    elseif ($cunning >= 7 || $discipline >= 7) {
      $intent = 'finish_weakest';
      $action_sequence = ['strike', 'strike', 'stride'];
      $target_strategy = 'weakest_adjacent';
      $decision_reason = 'High cunning/discipline profile prioritizes focused pressure on weak targets.';
    }
    elseif ($profile_present && !$has_adjacent_player && $this->actorHasGoal($goals, 'gain treasure')) {
      $intent = 'treasure_seek';
      $action_sequence = ['interact', 'stride', 'interact'];
      $target_strategy = 'none';
      $decision_reason = 'Treasure-oriented goals bias this actor toward interact-driven objective play.';
    }

    return [
      'intent' => $intent,
      'action_sequence' => $action_sequence,
      'target_strategy' => $target_strategy,
      'decision_reason' => $decision_reason,
      'decision_basis' => [
        'attitude' => $attitude,
        'axes' => [
          'boldness' => $boldness,
          'empathy' => $empathy,
          'discipline' => $discipline,
          'cunning' => $cunning,
        ],
        'hp_ratio' => $hp_ratio,
        'goals' => $goals,
        'profile_present' => $profile_present,
        'has_adjacent_player' => $has_adjacent_player,
        'nearest_player' => $nearest_player,
      ],
    ];
  }

  /**
   * Build deterministic per-action NPC plan for the remaining turn economy.
   */
  protected function buildNpcTurnPlan(string $entity_id, array $game_state, int $campaign_id, ?array $ai_seed_action = NULL): array {
    $intent_contract = $this->buildNpcTacticalIntentContract($entity_id, $game_state, $campaign_id);
    return $this->actorAutoplayCoordinator->buildTurnPlan(
      $entity_id,
      $game_state,
      $campaign_id,
      $intent_contract,
      $ai_seed_action,
      fn(string $actor_id, array $state, string $action_type, array $contract, int $cid): ?string => $this->actorAutoplayCoordinator->resolveIntentTarget(
        $actor_id,
        $state,
        $cid,
        $action_type,
        $contract,
        fn(string $fallback_actor_id, array $fallback_state, int $fallback_campaign_id, string $fallback_action_type): ?string => $this->chooseFallbackTarget($fallback_actor_id, $fallback_state, $fallback_campaign_id, $fallback_action_type),
        fn(string $nearest_actor_id, array $nearest_state): ?string => $this->findNearestAlivePlayer($nearest_actor_id, $nearest_state)
      )
    );
  }

  /**
   * Normalize personality-axis values for deterministic tactical decisions.
   */
  protected function normalizeDecisionPersonalityAxes(array $axes): array {
    return $this->actorContextBuilder->normalizePersonalityAxes($axes);
  }

  /**
   * Returns true when at least one alive player is adjacent to this NPC.
   */
  protected function hasAdjacentAlivePlayer(?array $npc, array $game_state): bool {
    if (!$npc) {
      return FALSE;
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['team'] ?? '') !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      if ($this->hexDistance($npc_q, $npc_r, $pq, $pr) <= 1) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Whether the current turn order still has a non-defeated player actor.
   */
  protected function hasActivePlayerParticipant(array $game_state): bool {
    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if (($participant['team'] ?? '') === 'player' && empty($participant['is_defeated'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Choose a fallback action for NPC without AI.
   *
   * Basic tactical heuristic: if adjacent to player → strike; otherwise → stride.
   */
  protected function chooseFallbackAction(string $entity_id, array $game_state, int $campaign_id = 0): string {
    $intent_contract = $this->buildNpcTacticalIntentContract($entity_id, $game_state, $campaign_id);
    return $this->actorAutoplayCoordinator->resolveIntentActionType($intent_contract, 0);
  }

  /**
   * Choose a fallback target aligned to NPC psychology and tactical state.
   */
  protected function chooseFallbackTarget(string $entity_id, array $game_state, int $campaign_id, string $action_type): ?string {
    if (!in_array($action_type, ['strike', 'talk'], TRUE)) {
      return NULL;
    }

    $nearest = $this->findNearestAlivePlayer($entity_id, $game_state);
    if ($nearest === NULL) {
      return NULL;
    }

    $profile = $this->loadCombatantPsychologyProfile($entity_id, $game_state, $campaign_id);
    if (!$profile) {
      return $nearest;
    }

    $axes = is_array($profile['personality_axes'] ?? NULL) ? $profile['personality_axes'] : [];
    $cunning = (int) ($axes['cunning'] ?? 5);
    $discipline = (int) ($axes['discipline'] ?? 5);
    if ($cunning < 7 && $discipline < 7) {
      return $nearest;
    }

    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return $nearest;
    }
    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);

    $best_target = NULL;
    $best_ratio = 2.0;
    $best_distance = PHP_INT_MAX;
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['team'] ?? '') !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }

      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $distance = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($action_type === 'strike' && $distance > 1) {
        continue;
      }

      $ratio = $this->hpRatio($combatant);
      if ($ratio < $best_ratio || ($ratio === $best_ratio && $distance < $best_distance)) {
        $best_ratio = $ratio;
        $best_distance = $distance;
        $best_target = $combatant['entity_id'] ?? NULL;
      }
    }

    return $best_target ?? $nearest;
  }

  /**
   * Returns true when profile text signals a self-preservation motivation.
   */
  protected function motivationSignalsSelfPreservation(array $profile): bool {
    $motivation_text = strtolower(trim((string) ($profile['motivations'] ?? '')));
    $fear_text = strtolower(trim((string) ($profile['fears'] ?? '')));
    $haystack = trim($motivation_text . ' ' . $fear_text);
    if ($haystack === '') {
      return FALSE;
    }

    foreach (['survive', 'survival', 'escape', 'retreat', 'avoid conflict', 'stay alive'] as $needle) {
      if (str_contains($haystack, $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Find the nearest alive player to an NPC.
   */
  protected function findNearestAlivePlayer(string $entity_id, array $game_state): ?string {
    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return $this->findFirstAlivePlayer($game_state);
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);
    $closest = NULL;
    $closest_dist = PHP_INT_MAX;

    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['team'] ?? '') !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $dist = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($dist < $closest_dist) {
        $closest_dist = $dist;
        $closest = $combatant['entity_id'] ?? NULL;
      }
    }

    return $closest;
  }

  /**
   * Find a combatant in the initiative order by entity ID.
   */
  protected function findCombatant(string $entity_id, array $game_state): ?array {
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $combatant;
      }
    }
    return NULL;
  }

  /**
   * Calculate hex distance (cube coordinates).
   */
  protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {
    $dq = abs($q1 - $q2);
    $dr = abs($r1 - $r2);
    $ds = abs((-$q1 - $r1) - (-$q2 - $r2));
    return (int) max($dq, $dr, $ds);
  }

  /**
   * Check if an entity was defeated after damage and generate narration.
   *
   * @param string $entity_id
   *   The entity to check for defeat.
   * @param string $attacker_id
   *   The entity that dealt the killing blow.
   * @param array &$game_state
   *   Current game state (modified if entity defeated).
   * @param array &$events
   *   Events array to append defeat event to.
   * @param array $dungeon_data
   *   Dungeon data for AI narration context.
   */
  protected function checkEntityDefeated(string $entity_id, string $attacker_id, array &$game_state, array &$events, array $dungeon_data, int $campaign_id = 0): void {
    foreach ($game_state['initiative_order'] as &$combatant) {
      if (($combatant['entity_id'] ?? '') !== $entity_id) {
        continue;
      }

      $hp = (int) ($combatant['hp'] ?? 0);
      if ($hp <= 0 && empty($combatant['is_defeated'])) {
        $combatant['is_defeated'] = TRUE;
        $name = $combatant['name'] ?? $entity_id;
        $team = $combatant['team'] ?? 'unknown';

        // Resolve attacker name for narration.
        $attacker = $this->findCombatant($attacker_id, $game_state);
        $killer_name = $attacker['name'] ?? $attacker_id;

        $narration = $this->aiGmService->narrateEntityDefeated($name, $killer_name, $dungeon_data, $campaign_id);
        $events[] = GameEventLogger::buildEvent('entity_defeated', 'encounter', $entity_id, [
          'name' => $name,
          'team' => $team,
          'killed_by' => $killer_name,
        ], $narration);
      }
      break;
    }
    unset($combatant);
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Processes an Escape attempt (REQ 2197-2199).
   * Attack trait: applies MAP. Crit success: freed + may Stride 5 ft.
   * Crit fail: blocks further escape attempts this turn.
   */
  protected function processEscape(int $encounter_id, string $actor_id, array $params, array &$game_state): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if (!$encounter) {
      return ['error' => 'Encounter not found.', 'mutations' => []];
    }
    $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
    if (!$participant) {
      return ['error' => 'Participant not found.', 'mutations' => []];
    }
    $pid = (int) $participant['id'];

    // REQ 2198: crit fail blocks further escape this turn.
    if (!empty($game_state['turn']['escape_blocked'][$actor_id])) {
      return ['error' => 'Cannot attempt Escape again this turn (critical failure).', 'mutations' => []];
    }

    // Must have grabbed, immobilized, or restrained.
    $active = $this->conditionManager->getActiveConditions($pid, $encounter_id);
    $condition_row_id = NULL;
    foreach ($active as $row_id => $row) {
      if (in_array($row['condition_type'], ['grabbed', 'immobilized', 'restrained'], TRUE)) {
        $condition_row_id = $row_id;
        break;
      }
    }
    if ($condition_row_id === NULL) {
      return ['error' => 'Must be grabbed, immobilized, or restrained to Escape.', 'mutations' => []];
    }

    // REQ 2199: attack trait — apply MAP.
    // REQ 1619: Athletics modifier accepted as alternative to unarmed modifier.
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    // Prefer acrobatics_bonus or athletics_bonus if provided; fall back to skill_bonus (unarmed).
    $modifier = (int) ($params['acrobatics_bonus'] ?? $params['athletics_bonus'] ?? $params['skill_bonus'] ?? 0);
    $total = $d20 + $modifier + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, (int) ($params['grapple_dc'] ?? 15), $d20);

    // Increment MAP for future attacks.
    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;

    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $this->conditionManager->removeCondition($pid, $condition_row_id, $encounter_id);
    }
    if ($degree === 'critical_failure') {
      if (!isset($game_state['turn']['escape_blocked'])) {
        $game_state['turn']['escape_blocked'] = [];
      }
      $game_state['turn']['escape_blocked'][$actor_id] = TRUE;
    }

    return [
      'escaped' => in_array($degree, ['critical_success', 'success'], TRUE),
      'may_stride_5ft' => ($degree === 'critical_success'),
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Processes a Seek action (REQ 2207-2210).
   * Secret GM-side Perception roll vs each target's Stealth DC.
   * Updates visibility state in game_state['visibility'][$seeker_id][$target_id].
   */
  protected function processSeek(int $encounter_id, string $actor_id, array $params, array &$game_state): array {
    $perception_bonus = (int) ($params['perception_bonus'] ?? 0);
    $target_ids = $params['target_ids'] ?? [];
    $is_imprecise = !empty($params['imprecise_sense']);
    // stealth_dcs: assoc array of target_id → DC; defaults to 15 if not provided.
    $stealth_dcs = $params['stealth_dcs'] ?? [];

    // AC-001–004: Sensate Gnome scent range + wind modifier.
    // scent_ft: base scent range of the actor (0 = no scent sense).
    // target_distances: optional assoc array of target_id → distance_ft for range check.
    $base_scent_ft = (int) ($params['scent_ft'] ?? 0);
    $target_distances = $params['target_distances'] ?? [];
    $effective_scent_ft = $base_scent_ft;
    if ($base_scent_ft > 0) {
      $wind_direction = $game_state['environment']['wind_direction'] ?? 'neutral';
      if ($wind_direction === 'downwind') {
        $effective_scent_ft = $base_scent_ft * 2;
      }
      elseif ($wind_direction === 'upwind') {
        $effective_scent_ft = (int) round($base_scent_ft / 2);
      }
      // neutral: no change.
    }

    if (!isset($game_state['visibility'])) {
      $game_state['visibility'] = [];
    }
    if (!isset($game_state['visibility'][$actor_id])) {
      $game_state['visibility'][$actor_id] = [];
    }

    $seek_results = [];
    foreach ($target_ids as $tid) {
      $stealth_dc = (int) ($stealth_dcs[$tid] ?? 15);
      $current = $game_state['visibility'][$actor_id][$tid] ?? 'undetected';

      // +2 circumstance bonus when actor has scent and target is undetected and within scent range.
      $roll_perception = $perception_bonus;
      if ($effective_scent_ft > 0 && $current === 'undetected') {
        $target_dist = isset($target_distances[$tid]) ? (int) $target_distances[$tid] : NULL;
        if ($target_dist === NULL || $target_dist <= $effective_scent_ft) {
          $roll_perception += 2;
        }
      }

      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $roll_perception;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $stealth_dc, $d20);

      $new_visibility = $current;

      // REQ 2208: detection rules.
      if ($degree === 'critical_success' && in_array($current, ['undetected', 'hidden'], TRUE)) {
        $new_visibility = 'observed';
      }
      elseif ($degree === 'success' && $current === 'undetected') {
        $new_visibility = 'hidden';
      }
      // failure / crit fail: no change.

      // REQ 2210: Imprecise sense cap — cannot exceed hidden.
      if ($is_imprecise && $new_visibility === 'observed') {
        $new_visibility = 'hidden';
      }

      $game_state['visibility'][$actor_id][$tid] = $new_visibility;
      // Secret: d20/total not included in returned result (GM-only).
      $seek_results[$tid] = ['degree' => $degree, 'new_visibility' => $new_visibility];
    }

    return ['sought' => TRUE, 'results' => $seek_results];
  }

  /**
   * Processes an Aid reaction (REQ 2190-2191).
   * Requires prior aid_setup on a previous turn. Rolls vs DC 20.
   */
  protected function processAid(string $actor_id, ?string $target_id, array $params, array &$game_state): array {
    $reaction_available = $game_state['turn']['reaction_available'] ?? TRUE;
    if (!$reaction_available) {
      return ['error' => 'Reaction already spent.', 'mutations' => []];
    }

    $aiding_actor = $params['aiding_actor'] ?? $actor_id;
    $aid_prepared = $game_state['turn']['aid_prepared'][$aiding_actor][$target_id] ?? NULL;
    if (!$aid_prepared) {
      return ['error' => 'Aid has not been prepared for this target.', 'mutations' => []];
    }

    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
    // proficiency_rank: 0=untrained,1=trained,2=expert,3=master,4=legendary.
    $proficiency_rank = (int) ($params['proficiency_rank'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $skill_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 20, $d20);

    // REQ 2191: Aid bonus by degree and proficiency rank.
    $aid_bonus = 0;
    if ($degree === 'critical_success') {
      if ($proficiency_rank >= 4) {
        $aid_bonus = 4;
      }
      elseif ($proficiency_rank >= 3) {
        $aid_bonus = 3;
      }
      else {
        $aid_bonus = 2;
      }
    }
    elseif ($degree === 'success') {
      $aid_bonus = 1;
    }
    elseif ($degree === 'critical_failure') {
      $aid_bonus = -1;
    }

    // Store aid bonus for the aided actor's next action.
    if (!isset($game_state['aid_bonuses'])) {
      $game_state['aid_bonuses'] = [];
    }
    if (!isset($game_state['aid_bonuses'][$target_id])) {
      $game_state['aid_bonuses'][$target_id] = [];
    }
    $game_state['aid_bonuses'][$target_id][] = $aid_bonus;
    $game_state['turn']['reaction_available'] = FALSE;

    return [
      'aided' => TRUE,
      'aid_bonus' => $aid_bonus,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Gets the action cost for an intent type.
   */
  protected function getActionCost(string $type, array $params = []): int {
    switch ($type) {
      case 'strike':
      case 'stride':
      case 'interact':
      case 'stand':
      case 'drop_prone':
      case 'step':
      case 'crawl':
      case 'leap':
      case 'escape':
      case 'seek':
      case 'sense_motive':
      case 'take_cover':
      case 'aid_setup':
      case 'burrow':
      case 'fly':
      case 'mount':
      case 'dismount':
      case 'raise_shield':
      case 'avert_gaze':
      case 'point_out':
      // REQ 1619–1659: Athletics single-action skill actions.
      case 'climb':
      case 'force_open':
      case 'grapple':
      case 'shove':
      case 'swim':
      case 'trip':
      case 'disarm':
      // REQ 1695: Treat Poison costs 1 action.
      case 'treat_poison':
      // REQ 1591: Recall Knowledge costs 1 action.
      case 'recall_knowledge':
      // REQ 1715: Hide costs 1 action.
      case 'hide':
      // REQ 1719: Sneak is a 1-action move.
      case 'sneak':
      // REQ 1721: Conceal an Object costs 1 action.
      case 'conceal_object':
      // REQ 1747: Palm an Object costs 1 action.
      case 'palm_object':
      // REQ 1751: Steal costs 1 action.
      case 'steal':
      // REQ 1591: Balance / Tumble Through / Maneuver in Flight cost 1 action.
      case 'balance':
      case 'tumble_through':
      case 'maneuver_in_flight':
      // REQ 1660: Create a Diversion costs 1 action.
      case 'create_diversion':
      // REQ 1677: Request costs 1 action.
      case 'request':
      // REQ 1683: Demoralize costs 1 action.
      case 'demoralize':
      // REQ 1700: Command an Animal costs 1 action (encounter).
      case 'command_animal':
      // REQ 1706: Perform costs 1 action (encounter).
      case 'perform':
        return 1;

      case 'ready':
        return 2;

      // REQ 1632–1636, 1637–1640: High Jump / Long Jump are 2-action activities.
      case 'high_jump':
      case 'long_jump':
      // REQ 1688: Administer First Aid costs 2 actions.
      case 'administer_first_aid':
      // REQ 1748: Disable a Device costs 2 actions.
      case 'disable_device':
      // REQ 1753: Pick a Lock costs 2 actions.
      case 'pick_lock':
      // REQ 1657: Feint costs 2 actions.
      case 'feint':
        return 2;

      case 'cast_spell':
        return $params['action_cost'] ?? 2;

      case 'talk':
      case 'release':
      case 'aid':
      case 'delay_reenter':
      // Reactions: no action cost (they use the reaction resource, not action slots).
      case 'arrest_fall':
      case 'grab_edge':
      case 'shield_block':
      case 'attack_of_opportunity':
      // REQ 2280: Hero point reroll is a free action (costs 0 actions).
      case 'hero_point_reroll':
      // REQ 2281: Heroic recovery (spend all HP) is a reaction (costs 0 actions).
      case 'heroic_recovery_all_points':
        return 0;

      default:
        return 1;
    }
  }

  /**
   * Applies room-scene lead-seeking caps for automation/harness talk actions.
   */
  protected function applyRoomSceneSocialProgressionPolicy(
    array $intent,
    array $result,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    if (!$this->isRoomSceneMode($game_state)) {
      return [];
    }
    if (!$this->isLeadSeekingAutomationTalkIntent($intent, $game_state)) {
      return [];
    }
    if (!empty($result['error']) || array_key_exists('success', $result) && empty($result['success'])) {
      return [];
    }

    $lead_source_id = $this->resolveLeadSeekTalkTargetEntityId($intent);
    if ($lead_source_id === '') {
      return [];
    }
    $room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ($dungeon_data['active_room_id'] ?? '')));
    if ($room_id === '') {
      return [];
    }

    $social_progression = $this->getRoomSceneSocialProgressionState($game_state, $room_id);
    $lead_seek_counts = is_array($social_progression['lead_seek_counts'] ?? NULL)
      ? $social_progression['lead_seek_counts']
      : [];
    $current_count = max(0, (int) ($lead_seek_counts[$lead_source_id] ?? 0));
    $next_count = $current_count + 1;
    $lead_seek_counts[$lead_source_id] = $next_count;

    $exhausted = is_array($social_progression['exhausted_lead_sources'] ?? NULL)
      ? array_values(array_filter(array_map('strval', $social_progression['exhausted_lead_sources']), static fn(string $value): bool => trim($value) !== ''))
      : [];
    if ($next_count >= self::MAX_LEAD_SEEK_INTERACTIONS_PER_ACTOR_PER_ROOM && !in_array($lead_source_id, $exhausted, TRUE)) {
      $exhausted[] = $lead_source_id;
    }

    $social_progression['lead_seek_counts'] = $lead_seek_counts;
    $social_progression['exhausted_lead_sources'] = array_values(array_unique($exhausted));
    $social_progression['last_progress_signal'] = $this->resolveSocialProgressSignal($result);
    $game_state['encounter_context']['social_progression'] = $social_progression;

    if ($next_count < self::MAX_LEAD_SEEK_INTERACTIONS_PER_ACTOR_PER_ROOM) {
      return [];
    }

    $lead_source_name = $this->resolveEntityName($lead_source_id, $game_state, $dungeon_data);
    return [
      GameEventLogger::buildEvent('room_scene_lead_source_exhausted', 'encounter', $lead_source_id, [
        'room_id' => $room_id,
        'lead_source_id' => $lead_source_id,
        'lead_source_name' => $lead_source_name,
        'lead_seek_count' => $next_count,
        'lead_seek_cap' => self::MAX_LEAD_SEEK_INTERACTIONS_PER_ACTOR_PER_ROOM,
      ], sprintf(
        '%s has no additional rumor/quest leads to offer right now.',
        $lead_source_name !== '' ? $lead_source_name : 'This actor'
      )),
    ];
  }

  /**
   * Returns true when an automation talk intent is seeking rumors/quest leads.
   */
  protected function isLeadSeekingAutomationTalkIntent(array $intent, array $game_state): bool {
    if (strtolower(trim((string) ($intent['type'] ?? ''))) !== 'talk') {
      return FALSE;
    }
    $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
    if (!$this->isAutomationSocialIntent($intent, $params)) {
      return FALSE;
    }

    if (trim((string) ($params['objective_id'] ?? '')) !== '' || trim((string) ($params['quest_id'] ?? '')) !== '') {
      return FALSE;
    }

    $message = strtolower(trim((string) ($params['message'] ?? '')));
    if ($message === '') {
      return FALSE;
    }

    $turn_in_markers = [
      'turning them in',
      'turning this in',
      'turn this in',
      'turn in now',
      'i found the requested items',
      'objective complete',
      'quest complete',
    ];
    foreach ($turn_in_markers as $marker) {
      if (str_contains($message, $marker)) {
        return FALSE;
      }
    }

    $lead_markers = [
      'rumor',
      'rumour',
      'lead',
      'job',
      'work',
      'danger',
      'clue',
      'what should i do next',
      'what should i tackle',
      'where should i start',
      'additional work',
      'urgent problem',
      'storyline lead',
      'what next',
    ];
    foreach ($lead_markers as $marker) {
      if (str_contains($message, $marker)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Detects harness/automation-origin intents for social-cap enforcement.
   */
    protected function isAutomationSocialIntent(array $intent, array $params): bool {
      if (trim((string) ($intent['decision_id'] ?? '')) !== '') {
        return TRUE;
      }
      if (trim((string) ($params['automation_goal'] ?? '')) !== '') {
        return TRUE;
      }
    if (!empty($params['automation'])) {
      return TRUE;
    }
    $source = strtolower(trim((string) ($params['source'] ?? '')));
    return in_array($source, ['harness', 'automation', 'player_agent'], TRUE);
  }

  /**
   * Resolve the target actor/entity identifier for lead-seeking talk actions.
   */
  protected function resolveLeadSeekTalkTargetEntityId(array $intent): string {
    $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
    return trim((string) (
      $intent['target']
      ?? $params['target']
      ?? ''
    ));
  }

  /**
   * Returns current room-scene social progression state with safe defaults.
   */
  protected function getRoomSceneSocialProgressionState(array $game_state, string $room_id): array {
    $encounter_context = is_array($game_state['encounter_context'] ?? NULL)
      ? $game_state['encounter_context']
      : [];
    $social_progression = is_array($encounter_context['social_progression'] ?? NULL)
      ? $encounter_context['social_progression']
      : [];
    $progression_room_id = trim((string) ($social_progression['room_id'] ?? ''));
    if ($progression_room_id === '' || ($room_id !== '' && $progression_room_id !== $room_id)) {
      $social_progression = [];
    }

    return [
      'policy_version' => (int) ($social_progression['policy_version'] ?? self::SOCIAL_PROGRESSION_POLICY_VERSION),
      'room_id' => $room_id,
      'lead_seek_counts' => is_array($social_progression['lead_seek_counts'] ?? NULL)
        ? $social_progression['lead_seek_counts']
        : [],
      'exhausted_lead_sources' => is_array($social_progression['exhausted_lead_sources'] ?? NULL)
        ? $social_progression['exhausted_lead_sources']
        : [],
      'last_progress_signal' => (string) ($social_progression['last_progress_signal'] ?? 'none'),
    ];
  }

  /**
   * Checks whether an actor is exhausted for lead-seeking in the room.
   */
  protected function isActorLeadSourceExhaustedForRoom(array $game_state, string $actor_id, string $room_id): bool {
    if ($actor_id === '' || $room_id === '') {
      return FALSE;
    }
    $social_progression = $this->getRoomSceneSocialProgressionState($game_state, $room_id);
    $exhausted = is_array($social_progression['exhausted_lead_sources'] ?? NULL)
      ? $social_progression['exhausted_lead_sources']
      : [];
    return in_array($actor_id, array_map('strval', $exhausted), TRUE);
  }

  /**
   * Classifies whether the latest talk produced objective progression signals.
   */
  protected function resolveSocialProgressSignal(array $result): string {
    $result_blob = strtolower(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    if ($result_blob === '') {
      return 'none';
    }
    if (str_contains($result_blob, 'objective') || str_contains($result_blob, 'quest')) {
      return 'objective_update';
    }
    return 'none';
  }

  /**
   * Checks if auto-end-turn conditions are met.
   */
  protected function shouldAutoEndTurn(array $game_state): bool {
    $actions = $game_state['turn']['actions_remaining'] ?? 0;
    return $actions <= 0;
  }

  /**
   * Build the follow-up system prompt for partial room-scene turns.
   */
  protected function buildRemainingRoomSceneActionPrompt(?string $actor_id, array $game_state, array $dungeon_data): ?array {
    return $this->roomSceneEncounterCoordinator->buildRemainingRoomSceneActionPrompt(
      $actor_id,
      $game_state,
      $dungeon_data,
      fn(string $id, array $state, array $dungeon): string => $this->resolveEntityName($id, $state, $dungeon)
    );
  }

  /**
   * Checks if the encounter should end (all enemies defeated or all players defeated).
   */
  /**
   * Starts or resumes the room-scene encounter framework for a room.
   */
  protected function startRoomSceneEncounter(?string $actor_id, string $room_id, array &$game_state, array &$dungeon_data, int $campaign_id, ?array $room = NULL, ?string $narration = NULL): array {
    return $this->roomSceneEncounterCoordinator->startRoomSceneEncounter(
      $actor_id,
      $room_id,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $room,
      $narration,
      self::ROOM_SCENE_ERR_START_MISSING_PLAYER,
      function (array $dungeon, string $rid, ?string $aid): array {
        return $this->buildRoomEncounterTurnOrder($dungeon, $rid, $aid);
      },
      function (array $initiative_order, string $error_code): void {
        $this->assertInitiativeHasPlayer($initiative_order, $error_code);
      },
      function (array $dungeon, array $initiative_order): array {
        return $this->buildRoomSceneEncounterParticipants($dungeon, $initiative_order);
      },
      function (?int $cid, string $rid, array $participants, $context): int {
        return (int) $this->encounterStore->createEncounter($cid, $rid, $participants, $context);
      },
      fn(int $encounter_id): ?array => $this->loadCanonicalTurnState($encounter_id),
      function (array &$state, array $canonical): void {
        $this->syncGameStateWithCanonicalTurn($state, $canonical);
      },
      function (int $round, array &$state, array &$dungeon, int $cid, ?string $rid): array {
        return $this->buildRoundStartEvents($round, $state, $dungeon, $cid, $rid);
      },
      function (string $entity_id, array &$state, array &$dungeon, int $cid, ?string $rid): array {
        return $this->buildTurnStartEvents($entity_id, $state, $dungeon, $cid, $rid);
      },
      function (string $entity_id, array &$state, array &$dungeon, int $cid): array {
        return $this->buildTurnStartSearchEvents($entity_id, $state, $dungeon, $cid);
      }
    );
  }

  /**
   * Build persisted combat participants for room-scene canonical encounter state.
   */
  protected function buildRoomSceneEncounterParticipants(array $dungeon_data, array $initiative_order): array {
    return $this->roomSceneEncounterCoordinator->buildRoomSceneEncounterParticipants($dungeon_data, $initiative_order);
  }

  /**
   * Builds the room encounter turn order for every actor present in the room.
   */
  protected function buildRoomEncounterTurnOrder(array $dungeon_data, string $room_id, ?string $actor_id = NULL): array {
    return $this->roomSceneEncounterCoordinator->buildRoomEncounterTurnOrder(
      $dungeon_data,
      $room_id,
      $actor_id,
      fn(int $sides): int => (int) $this->numberGenerationService->rollPathfinderDie($sides)
    );
  }

  /**
   * Builds participant list from dungeon entities for encounter creation.
   */
  protected function buildParticipantList(array $dungeon_data, string $room_id, array $enemies = []): array {
    $participants = [];
    $entities = $dungeon_data['entities'] ?? [];
    $enemy_instance_ids = array_map(static function (array $enemy): string {
      return (string) ($enemy['entity_instance_id'] ?? $enemy['instance_id'] ?? $enemy['id'] ?? '');
    }, $enemies);

    foreach ($entities as $entity) {
      $entity_room = $entity['placement']['room_id'] ?? NULL;
      if ($entity_room !== $room_id) {
        continue;
      }

      $content_type = $entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? '');
      $instance_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
      $team = $this->resolveEncounterParticipantTeam($entity, $content_type, (string) $instance_id, $enemy_instance_ids);

      if ($content_type === 'player_character') {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
            'heritage' => is_string($entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL))
              ? strtolower(trim((string) ($entity['heritage'] ?? $entity['state']['heritage'])))
              : NULL,
          ]),
          'team' => 'player',
          'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? 'Unknown'),
          'hp' => $stats['currentHp'] ?? ($entity['state']['hit_points']['current'] ?? 20),
          'max_hp' => $stats['maxHp'] ?? ($entity['state']['hit_points']['max'] ?? 20),
          'ac' => $stats['ac'] ?? ($entity['state']['armor_class'] ?? 10),
          'perception' => $perception,
          'position_q' => $entity['placement']['hex']['q'] ?? 0,
          'position_r' => $entity['placement']['hex']['r'] ?? 0,
        ];
      }
      elseif (($content_type === 'creature' || $content_type === 'npc' || in_array((string) $instance_id, $enemy_instance_ids, TRUE)) && $team !== NULL) {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
            'heritage' => is_string($entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL))
              ? strtolower(trim((string) ($entity['heritage'] ?? $entity['state']['heritage'])))
              : NULL,
          ]),
          'team' => $team,
          'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? 'Unknown'),
          'hp' => $stats['currentHp'] ?? ($entity['state']['hit_points']['current'] ?? 10),
          'max_hp' => $stats['maxHp'] ?? ($entity['state']['hit_points']['max'] ?? 10),
          'ac' => $stats['ac'] ?? ($entity['state']['armor_class'] ?? 12),
          'perception' => $perception,
          'position_q' => $entity['placement']['hex']['q'] ?? 0,
          'position_r' => $entity['placement']['hex']['r'] ?? 0,
        ];
      }
    }

    return $participants;
  }

  /**
   * Syncs participant HP/defeat state back into dungeon entity runtime state.
   */
  protected function syncEncounterParticipantsToDungeonData(int $encounter_id, array &$dungeon_data): void {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if (empty($encounter['participants']) || empty($dungeon_data['entities'])) {
      return;
    }

    $participant_by_entity = [];
    foreach ((array) ($encounter['participants'] ?? []) as $participant) {
      $entity_id = (string) ($participant['entity_id'] ?? '');
      if ($entity_id !== '') {
        $participant_by_entity[$entity_id] = $participant;
      }
    }

    foreach ($dungeon_data['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($entity_id === '' || empty($participant_by_entity[$entity_id])) {
        continue;
      }

      $participant = $participant_by_entity[$entity_id];
      $hp = isset($participant['hp']) ? (int) $participant['hp'] : NULL;
      $max_hp = isset($participant['max_hp']) ? (int) $participant['max_hp'] : NULL;
      $is_defeated = !empty($participant['is_defeated']);

      if (!isset($entity['state']) || !is_array($entity['state'])) {
        $entity['state'] = [];
      }
      if (!isset($entity['state']['hit_points']) || !is_array($entity['state']['hit_points'])) {
        $entity['state']['hit_points'] = [];
      }
      if (!isset($entity['state']['metadata']) || !is_array($entity['state']['metadata'])) {
        $entity['state']['metadata'] = [];
      }
      if (!isset($entity['state']['metadata']['stats']) || !is_array($entity['state']['metadata']['stats'])) {
        $entity['state']['metadata']['stats'] = [];
      }

      if ($hp !== NULL) {
        $entity['state']['hit_points']['current'] = $hp;
        $entity['state']['metadata']['stats']['currentHp'] = $hp;
      }
      if ($max_hp !== NULL && $max_hp > 0) {
        $entity['state']['hit_points']['max'] = $max_hp;
        $entity['state']['metadata']['stats']['maxHp'] = $max_hp;
      }
      $entity['state']['is_defeated'] = $is_defeated;
    }
    unset($entity);
  }

  /**
   * Finds a room by ID in the dungeon payload.
   */
  protected function findRoomById(array $dungeon_data, string $room_id): ?array {
    return $this->requireNavigationService()->findRoomById($dungeon_data, $room_id);
  }

  /**
   * Resolves the requested transition through the existing navigation contract.
   */
  protected function resolveRoomTransitionCapability(array $dungeon_data, string $target_room_id, array $params): ?array {
    $active_room_id = (string) ($dungeon_data['active_room_id'] ?? '');
    if ($active_room_id === '') {
      return [
        'available' => TRUE,
        'target_room_id' => $target_room_id,
      ];
    }

    $capabilities = $this->requireNavigationService()
      ->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $active_room_id);
    $connection_id = isset($params['connection_id']) ? (string) $params['connection_id'] : '';
    if ($connection_id !== '') {
      foreach ($capabilities as $capability) {
        if ((string) ($capability['connection_id'] ?? '') !== $connection_id) {
          continue;
        }
        if ((string) ($capability['target_room_id'] ?? '') !== $target_room_id) {
          return NULL;
        }
        return $capability;
      }
      return NULL;
    }

    foreach ($capabilities as $capability) {
      if ((string) ($capability['target_room_id'] ?? '') === $target_room_id) {
        return $capability;
      }
    }

    return NULL;
  }

  /**
   * Build diagnostics for an unreachable transition attempt.
   *
   * Provides immediate available targets and an optional RoutingPlanner first-hop hint.
   */
  protected function buildTransitionUnreachableDiagnostics(array $dungeon_data, string $target_room_id): array {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    $diagnostics = [
      'active_room_id' => $active_room_id,
      'available_targets' => [],
      'suggested_via_room_id' => '',
    ];
    if ($active_room_id === '' || trim($target_room_id) === '') {
      return $diagnostics;
    }

    $capabilities = $this->requireNavigationService()
      ->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $active_room_id);
    $available_targets = [];
    foreach ($capabilities as $capability) {
      if (empty($capability['available'])) {
        continue;
      }
      $candidate_target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
      if ($candidate_target_room_id === '' || $candidate_target_room_id === $active_room_id) {
        continue;
      }
      $available_targets[] = $candidate_target_room_id;
    }
    $available_targets = array_values(array_unique($available_targets));
    $diagnostics['available_targets'] = array_slice($available_targets, 0, 12);

    $route_plan = $this->requireNavigationService()
      ->resolveRoomRoutePlan($dungeon_data, $active_room_id, trim($target_room_id));
    if (is_array($route_plan) && !empty($route_plan['next_room_id']) && empty($route_plan['is_direct'])) {
      $diagnostics['suggested_via_room_id'] = (string) $route_plan['next_room_id'];
    }

    return $diagnostics;
  }

  /**
   * Resolve a room display label with room_id fallback.
   */
  protected function resolveRoomLabelById(array $dungeon_data, string $room_id): string {
    $normalized_room_id = trim($room_id);
    if ($normalized_room_id === '') {
      return '';
    }
    $room = $this->findRoomById($dungeon_data, $normalized_room_id);
    if (!is_array($room)) {
      return $normalized_room_id;
    }
    $room_name = trim((string) ($room['name'] ?? ''));
    return $room_name !== '' ? $room_name : $normalized_room_id;
  }

  /**
   * Builds elapsed-time effects for room movement.
   */
  protected function buildTransitionTimeEffects(?string $actor_id, mixed $from_room, string $target_room_id, ?array $capability, array $params): array {
    $from_room_id = is_scalar($from_room) ? (string) $from_room : '';
    if ($from_room_id === '' || $from_room_id === $target_room_id) {
      return [];
    }

    $duration_seconds = $this->resolveTravelSeconds($capability ?? [], []);
    if ($duration_seconds <= 0) {
      return [];
    }

    return [[
      'mode' => 'elapsed',
      'phase' => 'encounter',
      'action_type' => 'room_transition',
      'actor_ids' => $actor_id ? [$actor_id] : [],
      'duration_seconds' => $duration_seconds,
      'concurrency_group' => 'party_travel',
      'location_context' => [
        'from_room_id' => $from_room_id,
        'to_room_id' => $target_room_id,
        'connection_id' => (string) ($capability['connection_id'] ?? ''),
      ],
      'advance_immediately' => TRUE,
    ]];
  }

  /**
   * Builds elapsed-time effects for completed encounter rounds.
   */
  protected function buildRoundElapsedTimeEffects(array $turn_result, ?string $actor_id, array $dungeon_data): array {
    $round_advances = (int) ($turn_result['round_advances'] ?? 0);
    if ($round_advances <= 0) {
      return [];
    }

    return [[
      'mode' => 'elapsed',
      'phase' => 'encounter',
      'action_type' => 'encounter_round',
      'actor_ids' => $actor_id ? [$actor_id] : [],
      'duration_seconds' => 6 * $round_advances,
      'concurrency_group' => 'encounter_rounds',
      'location_context' => [
        'room_id' => (string) ($dungeon_data['active_room_id'] ?? ''),
        'round_advances' => $round_advances,
      ],
      'advance_immediately' => TRUE,
    ]];
  }

  protected function buildRestTimeEffects(string $action_type, ?string $actor_id, int $duration_seconds, array $dungeon_data): array {
    if ($duration_seconds <= 0) {
      return [];
    }

    return [[
      'mode' => 'elapsed',
      'phase' => 'encounter',
      'action_type' => $action_type,
      'actor_ids' => $actor_id ? [$actor_id] : [],
      'duration_seconds' => $duration_seconds,
      'concurrency_group' => 'rest_activity',
      'location_context' => [
        'room_id' => (string) ($dungeon_data['active_room_id'] ?? ''),
      ],
      'advance_immediately' => TRUE,
    ]];
  }

  protected function isRestAction(string $type): bool {
    return in_array($type, $this->getRestActionTypes(), TRUE);
  }

  /**
   * Return encounter actions that represent rest activities.
   */
  protected function getRestActionTypes(): array {
    return ['treat_wounds', 'refocus', 'repair', 'daily_preparations'];
  }

  protected function isSafeRestAvailable(array $game_state, array $dungeon_data): bool {
    if (!empty($game_state['encounter_id'])) {
      return FALSE;
    }
    $room_id = (string) ($game_state['encounter_context']['room_id'] ?? $dungeon_data['active_room_id'] ?? '');
    if ($room_id === '') {
      return FALSE;
    }
    $room = $this->findRoomById($dungeon_data, $room_id);
    if (!is_array($room)) {
      return FALSE;
    }
    return !empty($room['gameplay_state']['safe_for_rest']);
  }

  protected function processTreatWoundsRestAction(?string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Treat Wounds requires an acting character.'];
    }
    $target_entity_id = $target_id ?: $actor_id;
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $target_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $target_entity_id);
    if ($actor_index === NULL || $target_index === NULL) {
      return ['error' => 'Treat Wounds requires valid room participants.'];
    }

    $actor_character_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $medicine_skill = $this->resolveCharacterSkillData($actor_character_state, 'medicine');
    $rank = (int) ($medicine_skill['rank'] ?? 0);
    if ($rank < 1) {
      return ['error' => 'Treat Wounds requires Medicine training.'];
    }
    if (!$this->characterHasInventoryItem($actor_character_state, $actor_entity, ['healers_tools'], ['healer', 'tool'])) {
      return ['error' => 'Treat Wounds requires healer\'s tools.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $target_entity = &$dungeon_data['entities'][$target_index];
    $now = $this->resolveCurrentCampaignTimestamp($game_state);
    $last_treated_at = (string) ($target_entity['state']['rest_activity']['last_treat_wounds_at'] ?? '');
    if ($last_treated_at !== '' && ($last_timestamp = strtotime($last_treated_at)) !== FALSE && ($now - $last_timestamp) < 3600) {
      return ['error' => 'That target has already benefited from Treat Wounds within the last hour.'];
    }

    $medicine_bonus = (int) ($medicine_skill['bonus'] ?? 0);
    $requested_dc = 15;
    $roll = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $roll + $medicine_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $requested_dc, $roll);
    $heal_amount = 0;
    if ($degree === 'critical_failure') {
      $heal_amount = -max(1, $this->rollSimpleDice('1d8'));
    }
    elseif ($degree === 'success') {
      $heal_amount = max(1, $this->rollSimpleDice('2d8'));
    }
    elseif ($degree === 'critical_success') {
      $heal_amount = max(1, $this->rollSimpleDice('4d8'));
    }

    $target_name = $this->resolveDungeonEntityName($target_entity);
    $actor_name = $this->resolveDungeonEntityName($actor_entity);
    $this->applyEntityHealing($target_entity, $heal_amount);
    $canonical_state = $this->loadCanonicalCharacterState($target_entity, $campaign_id);
    if (is_array($canonical_state)) {
      $this->applyCanonicalHealing($canonical_state, $heal_amount);
      $this->persistCanonicalCharacterState($target_entity, $campaign_id, $canonical_state);
    }
    $target_entity['state']['rest_activity']['last_treat_wounds_at'] = gmdate('c', $now);

    $summary = $heal_amount >= 0
      ? sprintf('%s treats %s\'s wounds for %d HP.', $actor_name, $target_name, $heal_amount)
      : sprintf('%s botches the treatment and %s takes %d damage.', $actor_name, $target_name, abs($heal_amount));

    return $this->finalizeRestActivity(
      $actor_id,
      'treat_wounds',
      600,
      $summary,
      [
        'target' => $target_entity_id,
        'roll' => $roll,
        'total' => $total,
        'dc' => $requested_dc,
        'degree' => $degree,
        'healing' => $heal_amount,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function processRefocusRestAction(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Refocus requires an acting character.'];
    }
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    if ($actor_index === NULL) {
      return ['error' => 'Refocus requires an active room participant.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $canonical_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $focus_max = max(0, $this->resolveCharacterFocusPoints($canonical_state, $actor_entity, 'max'));
    $focus_current = max(0, $this->resolveCharacterFocusPoints($canonical_state, $actor_entity, 'current'));
    if ($focus_max <= 0) {
      return ['error' => 'This character has no Focus Points to restore.'];
    }
    if ($focus_current >= $focus_max) {
      return ['error' => 'Focus Points are already full.'];
    }

    $restored = min(1, $focus_max - $focus_current);
    $this->writeEntityFocusPoints($actor_entity, $focus_current + $restored, $focus_max);
    if (is_array($canonical_state)) {
      $resources = $canonical_state['resources'] ?? [];
      $resources['focusPoints']['max'] = $focus_max;
      $resources['focusPoints']['current'] = min($focus_max, max(0, (int) ($resources['focusPoints']['current'] ?? $focus_current)) + $restored);
      $canonical_state['resources'] = $resources;
      $this->persistCanonicalCharacterState($actor_entity, $campaign_id, $canonical_state);
    }

    return $this->finalizeRestActivity(
      $actor_id,
      'refocus',
      600,
      sprintf('%s spends ten minutes refocusing and regains %d Focus Point.', $this->resolveDungeonEntityName($actor_entity), $restored),
      [
        'focus_restored' => $restored,
        'focus_points_current' => $focus_current + $restored,
        'focus_points_max' => $focus_max,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function processRepairRestAction(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Repair requires an acting character.'];
    }
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    if ($actor_index === NULL) {
      return ['error' => 'Repair requires an active room participant.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $shield = $this->findHeldShield($actor_entity);
    if (!$shield) {
      return ['error' => 'Repair currently requires a held shield or damaged gear in hand.'];
    }

    $current_hp = (int) ($shield['hp_current'] ?? $shield['hp']['current'] ?? 0);
    $max_hp = (int) ($shield['hp_max'] ?? $shield['hp']['max'] ?? 0);
    if ($max_hp <= 0 || $current_hp >= $max_hp) {
      return ['error' => 'There is no damaged shield or gear to repair.'];
    }

    $actor_character_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $crafting_bonus = (int) (($this->resolveCharacterSkillData($actor_character_state, 'crafting'))['bonus'] ?? 0);
    $roll = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $roll + $crafting_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 15, $roll);
    $repaired_hp = match ($degree) {
      'critical_success' => 10,
      'success' => 5,
      default => 0,
    };
    if ($repaired_hp <= 0) {
      return $this->finalizeRestActivity(
        $actor_id,
        'repair',
        600,
        sprintf('%s spends ten minutes attempting repairs, but %s is still damaged.', $this->resolveDungeonEntityName($actor_entity), (string) ($shield['name'] ?? 'the item')),
        [
          'item_name' => (string) ($shield['name'] ?? 'shield'),
          'repair_roll' => $roll,
          'repair_total' => $total,
          'repair_degree' => $degree,
          'repaired_hp' => 0,
        ],
        $game_state,
        $dungeon_data,
        $campaign_id
      );
    }

    $new_hp = min($max_hp, $current_hp + $repaired_hp);
    if (isset($shield['hp']) && is_array($shield['hp'])) {
      $shield['hp']['current'] = $new_hp;
      $shield['hp']['max'] = $max_hp;
    }
    $shield['hp_current'] = $new_hp;
    $shield['hp_max'] = $max_hp;
    if (isset($shield['broken_threshold']) && $new_hp > (int) $shield['broken_threshold']) {
      $shield['broken'] = FALSE;
    }
    $actor_entity = $this->updateHeldShield($actor_entity, $shield);

    return $this->finalizeRestActivity(
      $actor_id,
      'repair',
      600,
      sprintf('%s spends ten minutes repairing %s for %d HP.', $this->resolveDungeonEntityName($actor_entity), (string) ($shield['name'] ?? 'their shield'), $repaired_hp),
      [
        'item_name' => (string) ($shield['name'] ?? 'shield'),
        'repair_roll' => $roll,
        'repair_total' => $total,
        'repair_degree' => $degree,
        'repaired_hp' => $repaired_hp,
        'item_hp_current' => $new_hp,
        'item_hp_max' => $max_hp,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function processDailyPreparationsRestAction(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$actor_id) {
      return ['error' => 'Daily Preparations require an acting character.'];
    }
    $actor_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    if ($actor_index === NULL) {
      return ['error' => 'Daily Preparations require an active room participant.'];
    }

    $actor_entity = &$dungeon_data['entities'][$actor_index];
    $canonical_state = $this->loadCanonicalCharacterState($actor_entity, $campaign_id);
    $level = max(1, $this->resolveCharacterLevel($canonical_state, $actor_entity));
    $constitution_mod = $this->resolveCharacterConstitutionModifier($canonical_state, $actor_entity);
    $hit_point_recovery = max(1, $constitution_mod * $level);
    $this->applyEntityHealing($actor_entity, $hit_point_recovery);
    $this->restoreEntitySpellSlots($actor_entity);
    $entity_focus_max = $this->resolveCharacterFocusPoints($canonical_state, $actor_entity, 'max');
    if ($entity_focus_max > 0) {
      $this->writeEntityFocusPoints($actor_entity, $entity_focus_max, $entity_focus_max);
    }
    $condition_changes = $this->applyDailyPreparationConditionRecovery($actor_entity);

    if (is_array($canonical_state)) {
      $this->applyCanonicalHealing($canonical_state, $hit_point_recovery);
      $this->restoreCanonicalSpellSlots($canonical_state);
      $this->restoreCanonicalFocusPoints($canonical_state);
      $this->applyCanonicalDailyPreparationConditionRecovery($canonical_state);
      $this->persistCanonicalCharacterState($actor_entity, $campaign_id, $canonical_state);
    }
    $this->magicItemService->performDailyPreparations($actor_id, [], [], $game_state);

    $summary = sprintf(
      '%s completes daily preparations, recovering %d HP and resetting daily resources.',
      $this->resolveDungeonEntityName($actor_entity),
      $hit_point_recovery
    );

    return $this->finalizeRestActivity(
      $actor_id,
      'daily_preparations',
      28800,
      $summary,
      [
        'healing' => $hit_point_recovery,
        'conditions' => $condition_changes,
        'focus_points_restored' => $entity_focus_max,
      ],
      $game_state,
      $dungeon_data,
      $campaign_id
    );
  }

  protected function finalizeRestActivity(?string $actor_id, string $action_type, int $duration_seconds, string $narration, array $event_context, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $room_id = (string) ($game_state['encounter_context']['room_id'] ?? $dungeon_data['active_room_id'] ?? '');
    $resume_events = $room_id !== ''
      ? $this->startRoomSceneEncounter($actor_id, $room_id, $game_state, $dungeon_data, $campaign_id, NULL, 'The room scene resumes after the rest activity.')
      : [];

    return [
      'events' => array_merge([
        GameEventLogger::buildEvent($action_type, 'encounter', $actor_id, array_merge($event_context, [
          'round' => $game_state['round'] ?? NULL,
          'room_id' => $room_id,
        ]), $narration),
      ], $resume_events),
      'mutations' => [],
      'time_effects' => $this->buildRestTimeEffects($action_type, $actor_id, $duration_seconds, $dungeon_data),
      'narration' => $narration,
    ];
  }

  protected function findDungeonEntityIndexByInstanceId(array $dungeon_data, string $entity_id): ?int {
    return $this->canonicalProjectionService->findDungeonEntityIndexByInstanceId($dungeon_data, $entity_id);
  }

  protected function normalizeSkillRank(mixed $rank, mixed $proficiency): int {
    if (is_numeric($rank)) {
      return max(0, (int) $rank);
    }
    $normalized = strtolower(trim((string) ($proficiency ?? '')));
    return match ($normalized) {
      'trained' => 1,
      'expert' => 2,
      'master' => 3,
      'legendary' => 4,
      default => 0,
    };
  }

  protected function toBool(mixed $value): bool {
    if (is_bool($value)) {
      return $value;
    }
    if (is_numeric($value)) {
      return (int) $value !== 0;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], TRUE);
  }

  protected function resolveCurrentCampaignTimestamp(array $game_state): int {
    $datetime = (string) ($game_state['campaign_clock']['datetime'] ?? $game_state['game_time']['datetime'] ?? '');
    if ($datetime !== '' && ($timestamp = strtotime($datetime)) !== FALSE) {
      return $timestamp;
    }
    return time();
  }

  protected function resolveDungeonEntityName(array $entity): string {
    return (string) (
      $entity['state']['metadata']['display_name']
      ?? $entity['state']['metadata']['name']
      ?? $entity['name']
      ?? $entity['entity_ref']['name']
      ?? $entity['entity_ref']['content_id']
      ?? 'Unknown actor'
    );
  }

  protected function rollSimpleDice(string $notation): int {
    if (!preg_match('/^\s*(\d+)d(\d+)\s*$/i', $notation, $matches)) {
      return 0;
    }
    $count = max(1, (int) $matches[1]);
    $sides = max(1, (int) $matches[2]);
    $total = 0;
    for ($i = 0; $i < $count; $i++) {
      $total += $this->numberGenerationService->rollPathfinderDie($sides);
    }
    return $total;
  }

  protected function applyEntityHealing(array &$entity, int $delta): void {
    $current = (int) ($entity['state']['hit_points']['current'] ?? $entity['state']['hp_current'] ?? $entity['hit_points']['current'] ?? 0);
    $max = (int) ($entity['state']['hit_points']['max'] ?? $entity['state']['hp_max'] ?? $entity['hit_points']['max'] ?? $current);
    $next = max(0, min($max, $current + $delta));
    $entity['state']['hit_points']['current'] = $next;
    $entity['state']['hit_points']['max'] = $max;
    $entity['state']['hp_current'] = $next;
    $entity['state']['hp_max'] = $max;
    $entity['hit_points']['current'] = $next;
    $entity['hit_points']['max'] = $max;
  }

  protected function loadCanonicalCharacterState(array $entity, int $campaign_id): ?array {
    return $this->canonicalProjectionService->loadCanonicalCharacterState($entity, $campaign_id);
  }

  protected function persistCanonicalCharacterState(array $entity, int $campaign_id, array $character_state): void {
    $this->canonicalProjectionService->persistCanonicalCharacterState($entity, $campaign_id, $character_state);
  }

  /**
   * Resolve canonical character identity from runtime entity payload.
   *
   * @return array{character_id: string, instance_id: ?string}
   */
  protected function resolveCanonicalCharacterIdentity(array $entity): array {
    return $this->canonicalProjectionService->resolveCanonicalCharacterIdentity($entity);
  }

  /**
   * Resolve canonical character identity from participant entity_ref payload.
   *
   * @return array{character_id: string, instance_id: ?string}
   */
  protected function resolveCanonicalCharacterIdentityFromParticipantEntityRef(array $entity_ref, string $fallback_instance_id = ''): array {
    return $this->canonicalProjectionService->resolveCanonicalCharacterIdentityFromParticipantEntityRef($entity_ref, $fallback_instance_id);
  }

  protected function resolveCharacterSkillData(?array $character_state, string $skill_name): array {
    if (!is_array($character_state)) {
      return ['bonus' => 0, 'rank' => 0, 'proficiency' => ''];
    }
    $normalized = strtolower(trim($skill_name));
    $skills = $character_state['skills'] ?? [];
    if (isset($skills[$normalized]) && is_array($skills[$normalized])) {
      $entry = $skills[$normalized];
    }
    else {
      $entry = [];
      foreach ($skills as $candidate) {
        if (!is_array($candidate)) {
          continue;
        }
        $name = strtolower(trim((string) ($candidate['name'] ?? $candidate['id'] ?? '')));
        if ($name === $normalized) {
          $entry = $candidate;
          break;
        }
      }
    }

    $proficiency = (string) ($entry['proficiency'] ?? $entry['rank_name'] ?? '');
    return [
      'bonus' => (int) ($entry['bonus'] ?? $entry['modifier'] ?? $entry['total'] ?? 0),
      'rank' => $this->normalizeSkillRank($entry['rank'] ?? $entry['proficiency_rank'] ?? $entry['proficiencyRank'] ?? NULL, $proficiency),
      'proficiency' => $proficiency,
    ];
  }

  protected function characterHasInventoryItem(?array $character_state, array $entity, array $item_ids, array $name_fragments = []): bool {
    $search_roots = [];
    if (is_array($character_state)) {
      $search_roots[] = $character_state['inventory'] ?? NULL;
      $search_roots[] = $character_state['equipment'] ?? NULL;
    }
    $search_roots[] = $entity['inventory'] ?? NULL;
    $search_roots[] = $entity['equipment'] ?? NULL;
    $search_roots[] = $entity['state']['inventory'] ?? NULL;
    $search_roots[] = $entity['state']['equipment'] ?? NULL;

    foreach ($search_roots as $root) {
      if ($this->arrayContainsItemToken($root, $item_ids, $name_fragments)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  protected function arrayContainsItemToken(mixed $value, array $item_ids, array $name_fragments = []): bool {
    if (is_array($value)) {
      $item_id = strtolower(trim((string) ($value['item_id'] ?? $value['id'] ?? '')));
      $name = strtolower(trim((string) ($value['name'] ?? '')));
      if ($item_id !== '' && in_array($item_id, $item_ids, TRUE)) {
        return TRUE;
      }
      if ($name !== '') {
        $matched = TRUE;
        foreach ($name_fragments as $fragment) {
          if (!str_contains($name, strtolower($fragment))) {
            $matched = FALSE;
            break;
          }
        }
        if ($matched && !empty($name_fragments)) {
          return TRUE;
        }
      }
      foreach ($value as $child) {
        if ($this->arrayContainsItemToken($child, $item_ids, $name_fragments)) {
          return TRUE;
        }
      }
      return FALSE;
    }
    return FALSE;
  }

  protected function preparedSpellListContainsSpell(array $prepared_list, string $spell_name, string $spell_id = ''): bool {
    $needle_name = $this->normalizeSpellToken($spell_name);
    $needle_id = $this->normalizeSpellToken($spell_id);

    foreach ($prepared_list as $prepared_spell) {
      if (!is_scalar($prepared_spell)) {
        continue;
      }
      $candidate = $this->normalizeSpellToken((string) $prepared_spell);
      if ($candidate === '') {
        continue;
      }
      if ($needle_name !== '' && $candidate === $needle_name) {
        return TRUE;
      }
      if ($needle_id !== '' && $candidate === $needle_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  protected function normalizeSpellToken(string $value): string {
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
      return '';
    }
    $normalized = str_replace(['_', ' '], '-', $normalized);
    $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;
    return trim($normalized, '-');
  }

  protected function applyCanonicalStateAfterSpellConsume(array &$canonical_state, bool $is_focus_spell, int $slot_level, int $remaining): void {
    if (!isset($canonical_state['resources']) || !is_array($canonical_state['resources'])) {
      $canonical_state['resources'] = [];
    }
    $remaining = max(0, $remaining);

    if ($is_focus_spell) {
      if (!isset($canonical_state['resources']['focusPoints']) || !is_array($canonical_state['resources']['focusPoints'])) {
        $canonical_state['resources']['focusPoints'] = ['current' => $remaining, 'max' => $remaining];
        return;
      }
      $max = max($remaining, (int) ($canonical_state['resources']['focusPoints']['max'] ?? 0));
      $canonical_state['resources']['focusPoints']['max'] = $max;
      $canonical_state['resources']['focusPoints']['current'] = min($remaining, $max);
      return;
    }

    $slot_key = (string) max(1, $slot_level);
    if (!isset($canonical_state['resources']['spellSlots']) || !is_array($canonical_state['resources']['spellSlots'])) {
      $canonical_state['resources']['spellSlots'] = [];
    }
    if (!isset($canonical_state['resources']['spellSlots'][$slot_key]) || !is_array($canonical_state['resources']['spellSlots'][$slot_key])) {
      $canonical_state['resources']['spellSlots'][$slot_key] = ['current' => $remaining, 'max' => $remaining];
      return;
    }

    $max = max($remaining, (int) ($canonical_state['resources']['spellSlots'][$slot_key]['max'] ?? 0));
    $canonical_state['resources']['spellSlots'][$slot_key]['max'] = $max;
    $canonical_state['resources']['spellSlots'][$slot_key]['current'] = min($remaining, $max);
  }

  protected function syncCanonicalSpellcastingProjectionForActor(?int $encounter_id, string $actor_id, int $campaign_id, array &$dungeon_data, ?array $canonical_state = NULL): void {
    $this->canonicalProjectionService->syncCanonicalSpellcastingProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
  }

  protected function syncCanonicalSurvivalProjectionForActor(?int $encounter_id, string $actor_id, int $campaign_id, array &$dungeon_data, ?array $canonical_state = NULL): void {
    $this->canonicalProjectionService->syncCanonicalSurvivalProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
  }

  protected function applyCanonicalSurvivalResourcesToDungeonEntity(array &$entity, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSurvivalResourcesToDungeonEntity($entity, $character_state);
  }

  protected function applyCanonicalSurvivalResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSurvivalResourcesToParticipantEntityRef($entity_ref, $character_state);
  }

  /**
   * @return array{daysWithoutFood:int,daysWithoutWater:int,starvationDamagePhase:bool,thirstDamagePhase:bool}
   */
  protected function readCanonicalSurvivalStateFromCanonicalState(array $character_state): array {
    return $this->canonicalProjectionService->readCanonicalSurvivalStateFromCanonicalState($character_state);
  }

  protected function normalizeSpellSlotRankKey(string $slot_key): ?string {
    return $this->canonicalProjectionService->normalizeSpellSlotRankKey($slot_key);
  }

  protected function resolveEffectiveCantripLevel(?array $canonical_state, array $participant_entity_ref): int {
    return $this->canonicalProjectionService->resolveEffectiveCantripLevel($canonical_state, $participant_entity_ref);
  }

  protected function resolveParticipantFocusPointCurrent(array $participant_entity_ref): int {
    return $this->canonicalProjectionService->resolveParticipantFocusPointCurrent($participant_entity_ref);
  }

  protected function applyCanonicalSpellcastingResourcesToDungeonEntity(array &$entity, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSpellcastingResourcesToDungeonEntity($entity, $character_state);
  }

  protected function applyCanonicalSpellcastingResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    $this->canonicalProjectionService->applyCanonicalSpellcastingResourcesToParticipantEntityRef($entity_ref, $character_state);
  }

  protected function buildLegacySpellSlotProjection(array $spell_slots): array {
    return $this->canonicalProjectionService->buildLegacySpellSlotProjection($spell_slots);
  }

  protected function persistEncounterParticipantEntityRef(int $participant_id, array $entity_ref): void {
    $this->canonicalProjectionService->persistEncounterParticipantEntityRef($participant_id, $entity_ref);
  }

  protected function normalizeSpellResourceErrorMessage(string $message, bool $is_focus_spell, int $slot_level): string {
    $normalized = strtolower(trim($message));
    if (str_contains($normalized, 'focus point')) {
      return 'No Focus Points remaining.';
    }
    if (str_contains($normalized, 'spell slot')) {
      return sprintf('No level-%d spell slots remaining.', max(1, $slot_level));
    }

    $trimmed = trim($message);
    if ($trimmed !== '') {
      return str_ends_with($trimmed, '.') ? $trimmed : $trimmed . '.';
    }

    return $is_focus_spell
      ? 'No Focus Points remaining.'
      : sprintf('No level-%d spell slots remaining.', max(1, $slot_level));
  }

  protected function resolveCharacterFocusPoints(?array $character_state, array $entity, string $field): int {
    return $this->canonicalProjectionService->resolveCharacterFocusPoints($character_state, $entity, $field);
  }

  protected function resolveCharacterLevel(?array $character_state, array $entity): int {
    return $this->canonicalProjectionService->resolveCharacterLevel($character_state, $entity);
  }

  protected function resolveCharacterConstitutionModifier(?array $character_state, array $entity): int {
    return $this->canonicalProjectionService->resolveCharacterConstitutionModifier($character_state, $entity);
  }

  protected function applyCanonicalHealing(array &$character_state, int $delta): void {
    $this->canonicalProjectionService->applyCanonicalHealing($character_state, $delta);
  }

  protected function restoreCanonicalSpellSlots(array &$character_state): void {
    $this->canonicalProjectionService->restoreCanonicalSpellSlots($character_state);
  }

  protected function restoreCanonicalFocusPoints(array &$character_state): void {
    $this->canonicalProjectionService->restoreCanonicalFocusPoints($character_state);
  }

  protected function applyCanonicalDailyPreparationConditionRecovery(array &$character_state): void {
    $this->canonicalProjectionService->applyCanonicalDailyPreparationConditionRecovery($character_state);
  }

  protected function readEntityFocusPoints(array $entity, string $field): int {
    return $this->canonicalProjectionService->readEntityFocusPoints($entity, $field);
  }

  protected function writeEntityFocusPoints(array &$entity, int $current, int $max): void {
    $this->canonicalProjectionService->writeEntityFocusPoints($entity, $current, $max);
  }

  protected function restoreEntitySpellSlots(array &$entity): void {
    $this->canonicalProjectionService->restoreEntitySpellSlots($entity);
  }

  protected function applyDailyPreparationConditionRecovery(array &$entity): array {
    return $this->canonicalProjectionService->applyDailyPreparationConditionRecovery($entity);
  }

  /**
   * Resolves travel duration from request or connection metadata.
   */
  protected function resolveTravelSeconds(array $capability, array $params): int {
    foreach ([$params, $capability] as $source) {
      foreach (['travel_time_seconds', 'duration_seconds', 'time_cost_seconds'] as $key) {
        if (isset($source[$key]) && is_numeric($source[$key])) {
          return max(0, (int) $source[$key]);
        }
      }
      foreach (['travel_time_minutes', 'duration_minutes', 'time_cost_minutes', 'travel_minutes'] as $key) {
        if (isset($source[$key]) && is_numeric($source[$key])) {
          return max(0, (int) $source[$key]) * 60;
        }
      }
      if (isset($source['travel_time']) && is_array($source['travel_time'])) {
        $nested = $source['travel_time'];
        if (isset($nested['seconds']) && is_numeric($nested['seconds'])) {
          return max(0, (int) $nested['seconds']);
        }
        if (isset($nested['minutes']) && is_numeric($nested['minutes'])) {
          return max(0, (int) $nested['minutes']) * 60;
        }
      }
    }

    return self::DEFAULT_ROOM_TRANSITION_SECONDS;
  }

  /**
   * Fallback navigation capability builder for isolated tests.
   *
   * @deprecated
   *   Transitional fallback kept only for isolated EncounterPhaseHandler tests
   *   that run without injected NavigationService wiring. Primary runtime paths
   *   must stay on NavigationService::buildNavigationCapabilitiesWithRoadNetwork().
   */
  protected function buildFallbackNavigationCapabilities(array $dungeon_data, string $room_id): array {
    $service = $this->requireNavigationService();
    return $service->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $room_id);
  }

  protected function requireNavigationService(): NavigationService {
    if ($this->navigationService === NULL) {
      throw new \RuntimeException('Encounter navigation contract violation: NavigationService dependency is required; fallback navigation support is not allowed.');
    }

    return $this->navigationService;
  }

  protected function requireNavigationRuntime(): NavigationRuntimeService {
    if ($this->navigationRuntime instanceof NavigationRuntimeService) {
      return $this->navigationRuntime;
    }

    $service = \Drupal::service('dungeoncrawler_content.navigation_runtime');
    if (!($service instanceof NavigationRuntimeService)) {
      throw new \RuntimeException('Encounter navigation contract violation: NavigationRuntimeService dependency is required for room instantiation.');
    }
    $this->navigationRuntime = $service;
    return $this->navigationRuntime;
  }

  /**
   * Moves an entity placement into a room.
   */
  protected function moveEntityToRoom(array &$dungeon_data, string $actor_id, string $room_id, array $hex, int $facing = 0): void {
    $h3_index_res14 = $this->resolveRoomHexH3IndexRes14($dungeon_data, $room_id, $hex);
    foreach ($dungeon_data['entities'] ?? [] as &$entity) {
      $entity_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($entity_id !== $actor_id) {
        continue;
      }
      if (!isset($entity['placement']) || !is_array($entity['placement'])) {
        $entity['placement'] = [];
      }
      $entity['placement']['room_id'] = $room_id;
      $entity['placement']['hex'] = [
        'q' => (int) ($hex['q'] ?? 0),
        'r' => (int) ($hex['r'] ?? 0),
      ];
      $entity['placement']['facing'] = $this->normalizeFacingDirection($facing);
      $entity['placement']['h3_index_res14'] = $h3_index_res14;
      break;
    }
    unset($entity);
  }

  /**
   * Normalize one facing direction into canonical [0..5] range.
   */
  protected function normalizeFacingDirection(int $facing): int {
    $facing = $facing % 6;
    if ($facing < 0) {
      $facing += 6;
    }

    return $facing;
  }

  /**
   * Resolve Res14 H3 index for a room hex coordinate.
   */
  protected function resolveRoomHexH3IndexRes14(array &$dungeon_data, string $room_id, array $hex): string {
    $room = $this->findRoomById($dungeon_data, $room_id);
    if ($room === NULL) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: room %s not found while resolving placement H3 index.',
        $room_id
      ));
    }

    $target_q = (int) ($hex['q'] ?? 0);
    $target_r = (int) ($hex['r'] ?? 0);
    foreach ((array) ($room['hexes'] ?? []) as $room_hex) {
      if (!is_array($room_hex)) {
        continue;
      }
      if ((int) ($room_hex['q'] ?? 0) !== $target_q || (int) ($room_hex['r'] ?? 0) !== $target_r) {
        continue;
      }
      $h3_index = trim((string) ($room_hex['h3_index_res14'] ?? $room_hex['h3_index'] ?? ''));
      if ($h3_index === '') {
        $this->ensureRoomHexH3IndexesInDungeonPayload($dungeon_data, $room_id);
        $refreshed_room = $this->findRoomById($dungeon_data, $room_id);
        foreach ((array) ($refreshed_room['hexes'] ?? []) as $refreshed_hex) {
          if (!is_array($refreshed_hex)) {
            continue;
          }
          if ((int) ($refreshed_hex['q'] ?? 0) !== $target_q || (int) ($refreshed_hex['r'] ?? 0) !== $target_r) {
            continue;
          }
          $refreshed_h3_index = trim((string) ($refreshed_hex['h3_index_res14'] ?? $refreshed_hex['h3_index'] ?? ''));
          if ($refreshed_h3_index !== '') {
            return strtolower($refreshed_h3_index);
          }
          break;
        }

        throw new \RuntimeException(sprintf(
          'Encounter transition contract violation: room %s hex (%d,%d) missing h3_index_res14.',
          $room_id,
          $target_q,
          $target_r
        ));
      }

      return strtolower($h3_index);
    }

    throw new \RuntimeException(sprintf(
      'Encounter transition contract violation: room %s missing placement hex (%d,%d).',
      $room_id,
      $target_q,
      $target_r
    ));
  }

  /**
   * Ensure one room's hexes carry authoritative Res14 H3 indexes.
   */
  protected function ensureRoomHexH3IndexesInDungeonPayload(array &$dungeon_data, string $room_id): void {
    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: cannot backfill h3_index_res14 for room %s without dungeon_id.',
        $room_id
      ));
    }

    foreach ((array) ($dungeon_data['rooms'] ?? []) as $index => $room) {
      if (!is_array($room) || (string) ($room['room_id'] ?? '') !== $room_id) {
        continue;
      }
      /** @var \Drupal\dungeoncrawler_content\Service\MapGeneratorService $map_generator */
      $map_generator = \Drupal::service('dungeoncrawler_content.map_generator');
      $dungeon_data['rooms'][$index] = $map_generator->ensureRoomHexH3Indexes($dungeon_id, $room);
      return;
    }
  }

  /**
   * Builds combat encounter context if the room has untriggered hostile content.
   */
  protected function buildCombatEncounterContext(string $room_id, array $dungeon_data, array $game_state): array {
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if ((string) ($room['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $gameplay_state = is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : [];
      $encounter_template = $gameplay_state['encounter_template'] ?? NULL;
      if (!$encounter_template || !empty($gameplay_state['encounter_triggered'])) {
        return ['should_trigger' => FALSE];
      }
      $hostile_entities = [];
      foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
        if (!is_array($entity) || (string) ($entity['placement']['room_id'] ?? '') !== $room_id) {
          continue;
        }
        $entity_type = (string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''));
        $team = strtolower((string) ($entity['state']['metadata']['team'] ?? ($entity['state']['team'] ?? '')));
        if (in_array($team, ['enemy', 'hostile', 'monster'], TRUE) || $entity_type === 'creature') {
          $hostile_entities[] = $entity;
        }
      }
      if ($hostile_entities === []) {
        return ['should_trigger' => FALSE];
      }
      return [
        'should_trigger' => TRUE,
        'reason' => $encounter_template['reason'] ?? 'Hostile creatures detected!',
        'encounter_context' => [
          'template' => $encounter_template,
          'enemies' => $hostile_entities,
          'room_id' => $room_id,
        ],
      ];
    }

    return ['should_trigger' => FALSE];
  }

  /**
   * Resolves whether a room entity should participate in combat and on which team.
   */
  protected function resolveEncounterParticipantTeam(array $entity, string $content_type, string $instance_id, array $enemy_instance_ids): ?string {
    if ($content_type === 'player_character') {
      return 'player';
    }

    if (in_array($instance_id, $enemy_instance_ids, TRUE)) {
      return 'enemy';
    }

    $raw_team = strtolower(trim((string) (
      $entity['state']['metadata']['team']
      ?? $entity['state']['team']
      ?? ''
    )));

    if (in_array($raw_team, ['enemy', 'hostile', 'monster'], TRUE)) {
      return 'enemy';
    }
    if (in_array($raw_team, ['ally', 'friendly', 'companion'], TRUE)) {
      return 'ally';
    }
    if (in_array($raw_team, ['player', 'player_character', 'pc'], TRUE)) {
      return 'player';
    }
    if (in_array($raw_team, ['neutral', 'indifferent', ''], TRUE)) {
      return $content_type === 'creature' ? 'enemy' : NULL;
    }

    return $content_type === 'creature' ? 'enemy' : NULL;
  }

  /**
   * Marks a room's encounter as triggered.
   */
  protected function markRoomEncounterTriggered(array &$dungeon_data, string $room_id): void {
    if (empty($dungeon_data['rooms'])) {
      return;
    }

    foreach ($dungeon_data['rooms'] as &$room) {
      if (($room['room_id'] ?? '') === $room_id) {
        if (!isset($room['gameplay_state'])) {
          $room['gameplay_state'] = [];
        }
        $room['gameplay_state']['encounter_triggered'] = TRUE;
        break;
      }
    }
    unset($room);
  }

  /**
   * Builds context object for NPC AI decision-making.
   */
  protected function buildNpcContext(string $entity_id, array $game_state, array $dungeon_data): array {
    return $this->actorContextBuilder->buildActorContext(
      $entity_id,
      $game_state,
      $dungeon_data,
      $this->buildNpcTacticalIntentContract(
        $entity_id,
        $game_state,
        (int) ($game_state['campaign_id'] ?? 0)
      )
    );
  }

  /**
   * Build canonical actor-scoped turn action availability envelope.
   */
  protected function buildActorTurnActionAvailabilityEnvelope(
    string $entity_id,
    array $game_state,
    array $available_actions,
    array $action_contract
  ): array {
    $turn_entity = (string) ($game_state['turn']['entity'] ?? '');
    $effective_actions_remaining = $turn_entity !== '' && $turn_entity === $entity_id
      ? max(0, (int) ($game_state['turn']['actions_remaining'] ?? 0))
      : 0;

    return [
      'actor_instance_id' => $entity_id,
      'is_active_turn_actor' => $turn_entity !== '' && $turn_entity === $entity_id,
      'actions_remaining' => $effective_actions_remaining,
      'reaction_available' => $turn_entity !== '' && $turn_entity === $entity_id && !empty($game_state['turn']['reaction_available']),
      'available_actions' => array_values(array_unique(array_filter(array_map(
        static fn($action): string => strtolower(trim((string) $action)),
        $available_actions
      )))),
      'action_contract' => $action_contract,
    ];
  }

  /**
   * Build structured profile fields to steer NPC action recommendation.
   */
  protected function buildNpcDecisionProfile(string $entity_id, array $game_state): array {
    return $this->actorContextBuilder->buildActorDecisionProfile($entity_id, $game_state);
  }

  /**
   * Build psychology context string for an NPC in combat.
   *
   * Provides personality-driven combat behavior hints to the AI:
   * - Cowardly NPCs may flee when badly hurt
   * - Disciplined NPCs focus fire and protect allies
   * - Cunning NPCs target weak PCs
   * - NPC attitude affects willingness to parley / surrender
   *
   * @param string $entity_id
   *   Entity ID.
   * @param array $game_state
   *   Current game state.
   *
   * @return string
   *   Formatted psychology context or empty string.
   */
  protected function buildNpcPsychologyContext(string $entity_id, array $game_state): string {
    return $this->actorContextBuilder->buildActorPsychologyContext($entity_id, $game_state);
  }

  /**
   * Resolve combatant entity_ref from initiative context.
   */
  protected function resolveCombatantEntityRef(string $entity_id, array $game_state): string {
    return $this->actorContextBuilder->resolveActorEntityRef($entity_id, $game_state);
  }

  /**
   * Load psychology profile for a combatant using entity_ref first, then entity_id.
   */
  protected function loadCombatantPsychologyProfile(string $entity_id, array $game_state, int $campaign_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }
    return $this->actorContextBuilder->loadActorProfile($entity_id, $game_state);
  }

  /**
   * Resolve actor goals list, always including XP and treasure goals.
   */
  protected function resolveActorGoals(?array $profile): array {
    return $this->actorContextBuilder->resolveGoalsFromProfile($profile);
  }

  /**
   * Case-insensitive goal matching helper.
   */
  protected function actorHasGoal(array $goals, string $needle): bool {
    $needle = strtolower(trim($needle));
    if ($needle === '') {
      return FALSE;
    }
    foreach ($goals as $goal) {
      if (!is_string($goal)) {
        continue;
      }
      if (str_contains(strtolower($goal), $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Calculate HP ratio for tactical context.
   */
  protected function hpRatio(array $combatant): float {
    $max = (int) ($combatant['max_hp'] ?? 0);
    if ($max <= 0) {
      return 1.0;
    }
    $current = (int) ($combatant['hp'] ?? 0);
    return round($current / $max, 2);
  }

  /**
   * Finds the first alive player entity for NPC fallback targeting.
   */
  protected function findFirstAlivePlayer(array $game_state): ?string {
    $initiative_order = $game_state['initiative_order'] ?? [];

    foreach ($initiative_order as $combatant) {
      if (($combatant['team'] ?? '') === 'player' && empty($combatant['is_defeated'])) {
        return $combatant['entity_id'] ?? NULL;
      }
    }

    return NULL;
  }

  // =========================================================================
  // Encounter chat prefixing.
  // =========================================================================

  protected function captureEncounterTurnContext(array $game_state, array $dungeon_data, ?string $actor_id, array $overrides = []): array {
    $round = isset($game_state['round']) && is_numeric($game_state['round']) ? (int) $game_state['round'] : NULL;
    $turn_index_raw = isset($game_state['turn']['index']) && is_numeric($game_state['turn']['index']) ? (int) $game_state['turn']['index'] : NULL;
    $turn_index_human = $turn_index_raw !== NULL ? ($turn_index_raw + 1) : '?';

    $effective_actor_id = is_string($actor_id) && trim($actor_id) !== '' ? trim($actor_id) : NULL;
    if ($effective_actor_id !== NULL && is_array($game_state['initiative_order'] ?? NULL)) {
      $initiative_index = $this->findInitiativeActorIndex($game_state['initiative_order'], $effective_actor_id);
      if ($initiative_index !== NULL) {
        $turn_index_raw = $initiative_index;
        $turn_index_human = $initiative_index + 1;
      }
    }
    $actor_name = $effective_actor_id !== NULL
      ? $this->resolveEntityName($effective_actor_id, $game_state, $dungeon_data)
      : 'Unknown';

    $room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $turn_entity_id = trim((string) ($game_state['turn']['entity'] ?? ''));
    $actions_total = is_numeric($game_state['turn']['actions_total'] ?? NULL) ? max(0, (int) $game_state['turn']['actions_total']) : 3;
    $active_actions_remaining = is_numeric($game_state['turn']['actions_remaining'] ?? NULL)
      ? max(0, (int) $game_state['turn']['actions_remaining'])
      : $actions_total;
    $actions_remaining = ($effective_actor_id !== NULL && $turn_entity_id !== '' && $effective_actor_id === $turn_entity_id)
      ? $active_actions_remaining
      : $actions_total;

    $ctx = [
      'round' => $round,
      'turn_index_raw' => $turn_index_raw,
      'turn_index_human' => $turn_index_human,
      'actor_id' => $effective_actor_id,
      'actor_name' => $actor_name !== '' ? $actor_name : 'Unknown',
      'room_id' => is_string($room_id) ? $room_id : NULL,
      'actions_remaining' => $actions_remaining,
      'actions_total' => $actions_total,
    ];

    foreach ($overrides as $k => $v) {
      $ctx[$k] = $v;
    }

    if (!is_string($ctx['actor_name']) || trim((string) $ctx['actor_name']) === '') {
      $ctx['actor_name'] = 'Unknown';
    }
    $ctx['actions_total'] = is_numeric($ctx['actions_total'] ?? NULL) ? max(0, (int) $ctx['actions_total']) : 3;
    $ctx['actions_remaining'] = is_numeric($ctx['actions_remaining'] ?? NULL)
      ? max(0, (int) $ctx['actions_remaining'])
      : $ctx['actions_total'];

    return $ctx;
  }

  protected function buildEncounterChatPrefix(array $turn_ctx): string {
    $turn = array_key_exists('turn_index_human', $turn_ctx) ? $turn_ctx['turn_index_human'] : 1;
    $round = $turn_ctx['round'] ?? 1;

    $turn_display = $turn === NULL || $turn === ''
      ? NULL
      : (is_numeric($turn) ? (int) $turn : '?');
    $round_display = \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::displayRound($round);

    $actor_name = $turn_ctx['actor_name'] ?? 'Unknown';
    if (!is_string($actor_name) || trim($actor_name) === '') {
      $actor_name = 'Unknown';
    }

    $actions_remaining = $turn_ctx['actions_remaining'] ?? NULL;
    $actions_total = $turn_ctx['actions_total'] ?? NULL;

    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::formatPrefix(
      $round_display,
      $turn_display,
      $actor_name,
      $actions_remaining,
      $actions_total
    );
  }

  protected function isEncounterChatLinePrefixed(string $content): bool {
    return \Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix::isPrefixed($content);
  }

  protected function prefixEncounterChatLine(array $turn_ctx, string $content): string {
    $content = trim($content);
    if ($content === '' || $this->isEncounterChatLinePrefixed($content)) {
      return $content;
    }
    return $this->buildEncounterChatPrefix($turn_ctx) . $content;
  }

  // =========================================================================
  // NarrationEngine bridge.
  // =========================================================================

  /**
   * Queue a game event through the NarrationEngine for perception filtering.
   *
   * Silently skips if NarrationEngine is not available.
   *
   * @param int $campaign_id
   * @param array $dungeon_data
   * @param array $event
   *   Event array matching NarrationEngine::queueRoomEvent() format.
   * @param string|null $room_id
   *   Override room ID. NULL uses active_room_id.
   *
   * @return array
   *   NarrationEngine result, or empty array if engine unavailable.
   */
  protected function queueNarrationEvent(int $campaign_id, array $dungeon_data, array $event, ?string $room_id = NULL, ?array $game_state_override = NULL): array {
    if (!$this->narrationEngine) {
      return [];
    }

    // Server-authoritative transcript: stamp Turn/Round/Actor prefix.
    $game_state = is_array($game_state_override ?? NULL)
      ? $game_state_override
      : (is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : []);
    if (!empty($game_state['suppress_room_scene_narration'])) {
      return [];
    }
    if (isset($event['content']) && is_string($event['content'])) {
      $prefix_actor_id = NULL;
      if (isset($event['speaker_ref']) && is_string($event['speaker_ref']) && trim($event['speaker_ref']) !== '') {
        $prefix_actor_id = trim($event['speaker_ref']);
      }
      elseif (isset($game_state['turn']['entity']) && is_string($game_state['turn']['entity']) && trim($game_state['turn']['entity']) !== '') {
        $prefix_actor_id = trim($game_state['turn']['entity']);
      }

      $overrides = [];
      if (($event['type'] ?? '') === 'round_start') {
        $overrides['actor_name'] = 'Narrator';
        $overrides['turn_index_raw'] = NULL;
        $overrides['turn_index_human'] = NULL;
      }

      $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $prefix_actor_id, $overrides);
      $event['content'] = $this->prefixEncounterChatLine($turn_ctx, $event['content']);
    }

    $dungeon_id = $dungeon_data['dungeon_id'] ?? $dungeon_data['id'] ?? 0;
    $room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? '');
    $present_characters = NarrationEngine::buildPresentCharacters($dungeon_data, $room_id);

    try {
      return $this->narrationEngine->queueRoomEvent(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $event,
        $present_characters
      );
    }
    catch (\Throwable $e) {
      $this->logger->warning('NarrationEngine queue failed: @err', ['@err' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Strip secret Search mechanics from client-facing action responses.
   */
  protected function buildPublicSearchResult(array $result): array {
    $public = ['searched' => TRUE];
    $discoveries = $this->buildPublicSearchDiscoveries($result['discoveries'] ?? []);
    if ($discoveries !== []) {
      $public['discoveries'] = $discoveries;
    }
    return $public;
  }

  /**
   * Build discovery payloads without roll/DC/degree/sense metadata.
   */
  protected function buildPublicSearchDiscoveries(array $discoveries): array {
    return array_values(array_map(
      static fn(array $discovery): array => array_filter([
        'instance_id' => $discovery['instance_id'] ?? NULL,
        'id' => $discovery['id'] ?? NULL,
        'name' => $discovery['name'] ?? NULL,
        'quest_id' => $discovery['quest_id'] ?? NULL,
        'objective_id' => $discovery['objective_id'] ?? NULL,
      ], static fn($value): bool => $value !== NULL && $value !== ''),
      array_filter($discoveries, 'is_array')
    ));
  }

  /**
   * Mirror rolled encounter checks into the Dice Log.
   */
  protected function maybeQueueMechanicalSystemLogEntry(array $context): void {
    $campaign_id = (int) ($context['campaign_id'] ?? 0);
    $dungeon_data = is_array($context['dungeon_data'] ?? NULL) ? $context['dungeon_data'] : [];
    $type = (string) ($context['type'] ?? '');
    $actor_id = isset($context['actor_id']) ? (string) $context['actor_id'] : NULL;
    $target_id = isset($context['target_id']) ? (string) $context['target_id'] : NULL;
    $params = is_array($context['params'] ?? NULL) ? $context['params'] : [];
    $result = is_array($context['result'] ?? NULL) ? $context['result'] : [];
    $game_state = is_array($context['game_state'] ?? NULL) ? $context['game_state'] : [];

    if (
      !$this->narrationEngine
      || !$actor_id
      // Strike and seek already emit dedicated narration/system-log events.
      || in_array($type, ['strike', 'seek'], TRUE)
    ) {
      return;
    }

    $logged_action = $type;
    $check_mode = 'explicit';
    if ($type === 'search') {
      $requested_mode = strtolower(trim((string) ($params['search_mode'] ?? 'explicit')));
      $check_mode = $requested_mode !== '' ? $requested_mode : 'explicit';
    }

    $dc = isset($result['dc']) && is_numeric($result['dc']) ? (int) $result['dc'] : NULL;
    $total = NULL;
    if (isset($result['total']) && is_numeric($result['total'])) {
      $total = (int) $result['total'];
    }
    elseif (isset($result['roll']) && is_numeric($result['roll'])) {
      $total = (int) $result['roll'];
    }

    if ($dc === NULL || $total === NULL) {
      return;
    }

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $degree = isset($result['degree']) && is_string($result['degree']) ? $result['degree'] : 'resolved';
    $skill_name = $this->resolveMechanicalSkillName($logged_action, $params, $result);
    if ($skill_name === NULL && $logged_action === 'search') {
      $skill_name = 'perception';
    }

    if ($logged_action === 'search') {
      $detail_label = $check_mode === 'automatic'
        ? 'Automatic Search via Perception'
        : 'Search via Perception';
    }
    else {
      $action_label = ucwords(str_replace('_', ' ', $logged_action));
      $detail_label = $skill_name
        ? sprintf('%s via %s', $action_label, ucwords(str_replace('_', ' ', $skill_name)))
        : $action_label;
    }

    $metadata = [
      'action' => $logged_action,
      'skill' => $skill_name,
      'check_mode' => $check_mode,
      'roll' => isset($result['d20']) && is_numeric($result['d20']) ? (int) $result['d20'] : (isset($result['roll']) && is_numeric($result['roll']) ? (int) $result['roll'] : NULL),
      'total' => $total,
      'dc' => $dc,
      'degree' => $degree,
      'target' => $target_id,
    ];
    $metadata = array_filter($metadata, static fn($value) => $value !== NULL && $value !== '');

    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'skill_check_result',
      'speaker' => 'System',
      'speaker_type' => 'system',
      'speaker_ref' => $actor_id,
      'content' => sprintf('%s resolves %s (%d vs DC %d: %s).', $actor_name, $detail_label, $total, $dc, $degree),
      'mechanical_data' => $metadata,
      'visibility' => 'public',
    ], NULL, $game_state);
  }

  /**
   * Determine the most specific skill label available for a rolled encounter action.
   */
  protected function resolveMechanicalSkillName(string $type, array $params, array $result): ?string {
    foreach (['skill_used', 'skill', 'skill_name'] as $key) {
      $value = $result[$key] ?? $params[$key] ?? NULL;
      if (is_string($value) && $value !== '') {
        return strtolower($value);
      }
    }

    return match ($type) {
      'search' => 'perception',
      'hide', 'create_a_diversion', 'lie', 'feint' => 'deception',
      'recall_knowledge' => 'recall_knowledge',
      'trip', 'grapple', 'shove', 'reposition', 'disarm' => 'athletics',
      'treat_poison', 'battle_medicine', 'administer_first_aid' => 'medicine',
      'disable_hazard', 'pick_a_lock' => 'thievery',
      'perform' => 'performance',
      default => NULL,
    };
  }

  /**
   * Resolve a display name for an entity from initiative order or dungeon data.
   */
  protected function resolveEntityName(?string $entity_id, array $game_state, array $dungeon_data = []): string {
    if (!$entity_id) {
      return 'Unknown';
    }

    // Check initiative order first (encounter context).
    foreach ($game_state['initiative_order'] ?? [] as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $combatant['name'] ?? $combatant['display_name'] ?? $entity_id;
      }
    }

    // Check dungeon_data entities.
    foreach ($dungeon_data['entities'] ?? [] as $ent) {
      $ent_id = $ent['entity_instance_id'] ?? ($ent['entity_ref']['content_id'] ?? '');
      if ($ent_id === $entity_id) {
        return $ent['state']['metadata']['display_name'] ?? $ent['name'] ?? $entity_id;
      }
    }

    return $entity_id;
  }

  /**
   * Processes a Grapple action (REQ 1626–1631).
   * 1 action, Attack trait; size limit = target no more than 1 size larger.
   */
  protected function processGrapple(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state): array {
    $enc = $this->encounterStore->loadEncounter($encounter_id);
    if (!$enc) {
      return ['error' => 'Encounter not found.', 'mutations' => []];
    }
    $actor_ptcp = $this->findEncounterParticipantByEntityId($enc, $actor_id);
    if (!$actor_ptcp) {
      return ['error' => 'Actor not found.', 'mutations' => []];
    }

    // REQ 1626: Requires one free hand (or already grappling target).
    $has_free_hand = !empty($params['has_free_hand']);
    $already_grappling = !empty($params['already_grappling']);
    if (!$has_free_hand && !$already_grappling) {
      return ['error' => 'Grapple requires a free hand.', 'mutations' => []];
    }

    // REQ 1626: Size limit — target no more than one size larger.
    $size_order = ['tiny' => 0, 'small' => 1, 'medium' => 2, 'large' => 3, 'huge' => 4, 'gargantuan' => 5];
    $actor_size = strtolower($params['actor_size'] ?? 'medium');
    $target_size = strtolower($params['target_size'] ?? 'medium');
    $actor_rank = $size_order[$actor_size] ?? 2;
    $target_rank = $size_order[$target_size] ?? 2;
    if ($target_rank > $actor_rank + 1) {
      return ['error' => 'Target is too large to Grapple.', 'size_blocked' => TRUE, 'mutations' => []];
    }

    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $athletics_bonus = (int) ($params['athletics_bonus'] ?? 0);
    $fortitude_dc = (int) ($params['fortitude_dc'] ?? 15);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $athletics_bonus + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $fortitude_dc, $d20);

    $target_ptcp = $target_id ? $this->findEncounterParticipantByEntityId($enc, $target_id) : NULL;

    $condition_applied = NULL;
    $grappler_grabbed = FALSE;
    $grappler_prone = FALSE;

    if ($degree === 'critical_success') {
      // Restrained.
      if ($target_ptcp) {
        $this->conditionManager->applyCondition((int) $target_ptcp['id'], 'restrained', 0, ['type' => 'encounter', 'remaining' => 1], 'grapple', $encounter_id);
      }
      $condition_applied = 'restrained';
    }
    elseif ($degree === 'success') {
      // Grabbed.
      if ($target_ptcp) {
        $this->conditionManager->applyCondition((int) $target_ptcp['id'], 'grabbed', 0, ['type' => 'encounter', 'remaining' => 1], 'grapple', $encounter_id);
      }
      $condition_applied = 'grabbed';
    }
    elseif ($degree === 'failure') {
      // Release existing grapple.
      if ($target_ptcp) {
        $active = $this->conditionManager->getActiveConditions((int) $target_ptcp['id'], $encounter_id);
        foreach ($active as $row_id => $row) {
          if (in_array($row['condition_type'], ['grabbed', 'restrained'], TRUE) && ($row['source'] ?? '') === 'grapple') {
            $this->conditionManager->removeCondition((int) $target_ptcp['id'], $row_id, $encounter_id);
            break;
          }
        }
      }
    }
    elseif ($degree === 'critical_failure') {
      // Target may grab grappler or knock prone; default: grappler grabbed.
      if (!empty($params['target_grabs_back'])) {
        $grappler_grabbed = TRUE;
        $this->conditionManager->applyCondition((int) $actor_ptcp['id'], 'grabbed', 0, ['type' => 'encounter', 'remaining' => 1], 'grapple_retaliation', $encounter_id);
      }
      else {
        $grappler_prone = TRUE;
        $this->conditionManager->applyCondition((int) $actor_ptcp['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'grapple_retaliation', $encounter_id);
      }
    }

    return [
      'grappled' => in_array($degree, ['critical_success', 'success'], TRUE),
      'condition_applied' => $condition_applied,
      'grappler_grabbed' => $grappler_grabbed,
      'grappler_prone' => $grappler_prone,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Processes the Administer First Aid skill action result.
   *
   * REQ 1688–1693: Two modes — stabilize (removes dying) and stop_bleeding.
   * DC is 15 + dying value for stabilize; bleeding DC for stop bleeding.
   * Called AFTER the d20 roll and total are computed by the caller.
   *
   * @param array|null $target_ptcp   Encounter participant row for the target.
   * @param array|null $actor_ptcp    Encounter participant row for the actor.
   * @param string $mode              'stabilize' or 'stop_bleeding'.
   * @param int $total                Final roll total (d20 + medicine + penalties).
   * @param int $d20                  Raw d20 result (for crit detection).
   * @param array $params             Intent params (bleeding_dc, etc.).
   * @param int $encounter_id         Current encounter ID.
   *
   * @return array
   *   Keys: degree, stabilized, bleeding_stopped, error (optional).
   */
  protected function processAdministerFirstAid(?array $target_ptcp, ?array $actor_ptcp, string $mode, int $total, int $d20, array $params, int $encounter_id): array {
    if (!$target_ptcp) {
      return ['error' => 'Target participant not found.', 'degree' => NULL];
    }
    $target_pid = (int) $target_ptcp['id'];

    if ($mode === 'stabilize') {
      // REQ 1690: Target must be dying.
      $dying_value = $this->conditionManager->getConditionValue($target_pid, 'dying', $encounter_id);
      if ($dying_value === NULL || $dying_value <= 0) {
        return ['error' => 'Target is not dying; cannot stabilize.', 'degree' => NULL, 'stabilized' => FALSE];
      }

      // DC = 15 + dying value.
      $dc = 15 + $dying_value;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

      if ($degree === 'critical_success' || $degree === 'success') {
        // REQ 1688/1689: Remove dying condition; wounded +1 applied inside stabilizeCharacter.
        $this->hpManager->stabilizeCharacter($target_pid, $encounter_id);
        return ['degree' => $degree, 'stabilized' => TRUE, 'dc' => $dc, 'bleeding_stopped' => FALSE];
      }
      elseif ($degree === 'failure') {
        // REQ 1691: Failure — dying decreases by 1 (partial improvement, no stabilize).
        $this->conditionManager->decrementCondition($target_pid, 'dying', $encounter_id, 1);
        return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $dc, 'bleeding_stopped' => FALSE];
      }
      else {
        // REQ 1691: Critical failure — dying advances by 1.
        $current_dying = $this->conditionManager->getConditionValue($target_pid, 'dying', $encounter_id) ?? $dying_value;
        $this->conditionManager->applyCondition($target_pid, 'dying', $current_dying + 1, ['type' => 'persistent', 'remaining' => NULL], 'first_aid_crit_fail', $encounter_id);
        return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $dc, 'bleeding_stopped' => FALSE];
      }
    }

    // stop_bleeding mode.
    $bleeding_dc = (int) ($params['bleeding_dc'] ?? 15);
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $bleeding_dc, $d20);

    if ($degree === 'critical_success' || $degree === 'success') {
      // REQ 1693: Remove persistent bleeding condition.
      $active_conds = $this->conditionManager->getActiveConditions($target_pid, $encounter_id);
      foreach ($active_conds as $cond) {
        if ($cond['condition_type'] === 'persistent_bleed' || $cond['condition_type'] === 'bleeding') {
          $this->conditionManager->removeCondition($target_pid, (int) $cond['id'], $encounter_id);
        }
      }
      return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $bleeding_dc, 'bleeding_stopped' => TRUE];
    }
    elseif ($degree === 'critical_failure') {
      // REQ 1693: Crit fail triggers immediate bleed damage (1d4 default).
      $bleed_damage = $this->numberGenerationService->rollPathfinderDie(4);
      $this->hpManager->applyDamage($target_pid, $bleed_damage, 'bleed', ['source' => 'first_aid_crit_fail'], $encounter_id);
      return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $bleeding_dc, 'bleeding_stopped' => FALSE, 'bleed_damage' => $bleed_damage];
    }

    return ['degree' => $degree, 'stabilized' => FALSE, 'dc' => $bleeding_dc, 'bleeding_stopped' => FALSE];
  }

  /**
   * Calculates falling damage.
   * REQ 1641: Half of distance in feet as bludgeoning damage.
   * REQ 1642: Soft surfaces reduce effective distance by up to 20 ft.
   *
   * @param int $feet_fallen
   *   Total distance fallen in feet.
   * @param bool|int $soft_surface
   *   Whether landing on a soft surface; if int, treated as max reduction depth (default 20).
   *
   * @return int
   *   Bludgeoning damage.
   */
  protected function calculateFallingDamage(int $feet_fallen, bool|int $soft_surface = FALSE): int {
    if ($soft_surface !== FALSE) {
      $reduction = is_int($soft_surface) ? min($soft_surface, 20) : 20;
      $feet_fallen = max(0, $feet_fallen - $reduction);
    }
    return (int) floor($feet_fallen / 2);
  }

}
