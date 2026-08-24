<?php

namespace Drupal\dungeoncrawler_content\Service;

use InvalidArgumentException;
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
class EncounterPhaseHandler implements EncounterMasterInterface, MutationContextPhaseHandlerInterface {
  use EncounterPhaseHandlerRuntimeTrait;

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
  protected ActionResolverRegistry $actionResolverRegistry;

  /**
   * Shared encounter intent router.
   */
  protected EncounterIntentRouter $encounterIntentRouter;
  protected ?StanceRuntimeService $stanceRuntimeService;
  protected CombatResolutionContractService $combatResolutionContractService;
  protected UnifiedStateEffectEngine $unifiedStateEffectEngine;
  protected UnifiedReactionEngine $unifiedReactionEngine;
  protected UnifiedDamageEngine $unifiedDamageEngine;
  protected ActionTargetingService $actionTargetingService;

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
    ?H3ProjectionQueueService $h3_projection_queue = NULL,
    ?StanceRuntimeService $stance_runtime_service = NULL,
    ?ActionResolverRegistry $action_resolver_registry = NULL,
    ?UnifiedStateEffectEngine $unified_state_effect_engine = NULL,
    ?UnifiedReactionEngine $unified_reaction_engine = NULL,
    ?UnifiedDamageEngine $unified_damage_engine = NULL,
    ?ActionTargetingService $action_targeting_service = NULL,
    ?CombatResolutionContractService $combat_resolution_contract_service = NULL
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
    if ($character_state_service === NULL) {
      throw new InvalidArgumentException('CharacterStateService is required for EncounterPhaseHandler construction.');
    }
    if ($room_chat_service === NULL) {
      throw new InvalidArgumentException('RoomChatService is required for EncounterPhaseHandler construction.');
    }
    $this->characterStateService = $character_state_service;
    $this->spellCatalog = $spell_catalog ?? new SpellCatalogService();
    $this->roomChatService = $room_chat_service;
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
    if ($combat_resolution_contract_service === NULL) {
      throw new InvalidArgumentException('CombatResolutionContractService is required for EncounterPhaseHandler construction.');
    }
    $this->combatResolutionContractService = $combat_resolution_contract_service;
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
      $this->combatResolutionContractService,
      $logger_factory,
      $this->movementResolver
    );
    $this->actionResolverRegistry = $action_resolver_registry ?? new ActionResolverRegistry();
    $this->actionResolverRegistry->register('strike', function (
      int $encounter_id,
      string $actor_id,
      string $target_id,
      array $params,
      array &$game_state,
      array $dungeon_data,
      ?int $campaign_id
    ): array {
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
    });
    $this->actionResolverRegistry->register('cast_spell', function (
      int $encounter_id,
      string $actor_id,
      ?string $target_id,
      array $params,
      array &$game_state,
      array &$dungeon_data,
      int $campaign_id
    ): array {
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
    });
    $this->encounterIntentRouter = $encounter_intent_router ?? new EncounterIntentRouter();
    $this->stanceRuntimeService = $stance_runtime_service;
    $this->unifiedStateEffectEngine = $unified_state_effect_engine ?? new UnifiedStateEffectEngine(
      $this->combatResolutionContractService
    );
    $this->unifiedReactionEngine = $unified_reaction_engine ?? new UnifiedReactionEngine(
      $this->combatResolutionContractService
    );
    $this->unifiedDamageEngine = $unified_damage_engine ?? new UnifiedDamageEngine(
      $this->encounterStore,
      $this->numberGenerationService,
      $this->combatResolutionContractService,
      $this->hpManager
    );
    $this->actionTargetingService = $action_targeting_service ?? new ActionTargetingService();
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
  public function processIntentWithMutationContext(
    array $intent,
    RuntimeMutationExecutionContext $mutation_context,
    int $campaign_id
  ): array {
    $game_state =& $mutation_context->gameState;
    $dungeon_data =& $mutation_context->dungeonData;
    return $this->processIntent($intent, $game_state, $dungeon_data, $campaign_id);
  }

  /**
   * {@inheritdoc}
   */
  public function processIntent(array $intent, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $pre_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);
    $intent_actor_id = is_string($intent['actor'] ?? NULL) ? trim((string) $intent['actor']) : '';
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
    if (!array_key_exists('mutation_envelope', $result) || !is_array($result['mutation_envelope'])) {
      $result_mutations = $this->normalizeMutationDescriptors(
        is_array($result['mutations'] ?? NULL) ? $result['mutations'] : [],
        $intent_actor_id !== '' ? $intent_actor_id : NULL,
        (string) ($dungeon_data['active_room_id'] ?? '')
      );
      $result['mutations'] = $result_mutations;
      $result['mutation_envelope'] = $this->buildMutationEnvelopeFromRuntimeContext(
        $campaign_id,
        $game_state,
        $dungeon_data,
        $result_mutations
      );
    }
    else {
      $result['mutations'] = $this->normalizeMutationDescriptors(
        is_array($result['mutations'] ?? NULL) ? $result['mutations'] : [],
        $intent_actor_id !== '' ? $intent_actor_id : NULL,
        (string) ($dungeon_data['active_room_id'] ?? '')
      );
    }
    $result['mutation_envelope'] = $this->ensureMutationEnvelopeIncludesChangedSlices(
      $result['mutation_envelope'],
      $pre_slice_fingerprints,
      $dungeon_data,
      is_array($result['mutations'] ?? NULL) ? $result['mutations'] : []
    );
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
   * Ensure typed mutation envelope carries all runtime slices that changed.
   *
   * @param array<string,mixed> $mutation_envelope
   *   Candidate mutation envelope.
   * @param array<string,string> $pre_slice_fingerprints
   *   Pre-mutation actor_entities/rooms/connections fingerprints.
   *
   * @return array<string,mixed>
   *   Envelope with changed runtime slices explicitly populated.
   */
  protected function ensureMutationEnvelopeIncludesChangedSlices(
    array $mutation_envelope,
    array $pre_slice_fingerprints,
    array $dungeon_data,
    array $mutations
  ): array {
    $after_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);
    $changed_slices = [];
    foreach (['actor_entities', 'rooms', 'connections'] as $slice_key) {
      $before = (string) ($pre_slice_fingerprints[$slice_key] ?? '');
      $after = (string) ($after_slice_fingerprints[$slice_key] ?? '');
      if ($before !== '' && $after !== '' && $before !== $after) {
        $changed_slices[] = $slice_key;
      }
    }

    if ($changed_slices === []) {
      return $mutation_envelope;
    }

    $targets = $this->extractMutationEnvelopeTargets($mutations);

    foreach ($changed_slices as $slice_key) {
      $slice_payload = is_array($mutation_envelope[$slice_key] ?? NULL) ? $mutation_envelope[$slice_key] : [];
      if ($slice_payload !== []) {
        continue;
      }
      if ($slice_key === 'actor_entities') {
        if ($targets['entity_ids'] === []) {
          throw new \RuntimeException('Encounter mutation envelope contract violation: actor_entities changed without entity mutation targets.');
        }
        $mutation_envelope['actor_entities'] = is_array($dungeon_data['entities'] ?? NULL)
          ? $this->selectMutationTargetedActorEntities($dungeon_data['entities'], $targets['entity_ids'])
          : [];
      }
      elseif ($slice_key === 'rooms') {
        if ($targets['room_ids'] === []) {
          throw new \RuntimeException('Encounter mutation envelope contract violation: rooms changed without room mutation targets.');
        }
        $mutation_envelope['rooms'] = is_array($dungeon_data['rooms'] ?? NULL)
          ? $this->selectMutationTargetedRooms($dungeon_data['rooms'], $targets['room_ids'])
          : [];
      }
      elseif ($slice_key === 'connections') {
        if ($targets['connection_ids'] === [] && $targets['room_ids'] === []) {
          throw new \RuntimeException('Encounter mutation envelope contract violation: connections changed without connection/room mutation targets.');
        }
        $mutation_envelope['connections'] = $this->selectMutationTargetedConnections(
          $dungeon_data,
          $targets['connection_ids'],
          $targets['room_ids']
        );
      }
    }

    return $mutation_envelope;
  }

  /**
   * Build deterministic fingerprints for runtime non-game-state slices.
   *
   * @return array{actor_entities: string, rooms: string, connections: string}
   *   Fingerprints for actor entities, rooms, and connections.
   */
  protected function computeRuntimeSliceFingerprints(array $dungeon_data): array {
    $actor_entities = is_array($dungeon_data['entities'] ?? NULL)
      ? $this->selectMutationTargetedActorEntities($dungeon_data['entities'], [])
      : [];
    $rooms = is_array($dungeon_data['rooms'] ?? NULL)
      ? $this->selectMutationTargetedRooms($dungeon_data['rooms'], [])
      : [];
    $connections = $this->selectMutationTargetedConnections($dungeon_data, [], []);

    $encode = static function (array $payload): string {
      $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($encoded)) {
        throw new \RuntimeException('Encounter runtime-slice fingerprint contract violation: unable to encode payload.');
      }
      return hash('sha256', $encoded);
    };

    return [
      'actor_entities' => $encode($actor_entities),
      'rooms' => $encode($rooms),
      'connections' => $encode($connections),
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
        $mutation['entity_id'] ?? NULL,
      ] as $candidate) {
        $normalized = $this->normalizeMutationTargetId($candidate);
        if ($normalized !== NULL) {
          $entity_ids[$normalized] = TRUE;
        }
      }

      foreach ([
        $mutation['room_id'] ?? NULL,
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
   * Normalize mutation descriptors so runtime targets are explicit.
   *
   * @param array<int,mixed> $mutations
   *   Raw mutation descriptors.
   *
   * @return array<int,mixed>
   *   Normalized mutation descriptors.
   */
  protected function normalizeMutationDescriptors(array $mutations, ?string $default_actor_id, string $default_room_id): array {
    $normalized_actor_id = trim((string) $default_actor_id);
    $normalized_room_id = trim($default_room_id);
    $normalized = [];
    foreach ($mutations as $mutation) {
      if (!is_array($mutation)) {
        $normalized[] = $mutation;
        continue;
      }

      $field = strtolower(trim((string) ($mutation['field'] ?? $mutation['path'] ?? $mutation['type'] ?? '')));
      if (!isset($mutation['field']) && isset($mutation['path']) && is_string($mutation['path'])) {
        $mutation['field'] = $mutation['path'];
      }

      $has_actor_target = trim((string) ($mutation['entity_id'] ?? '')) !== '';
      $has_room_target = trim((string) ($mutation['room_id'] ?? '')) !== '';

      if (
        !$has_actor_target
        && $normalized_actor_id !== ''
        && (str_contains($field, 'entity') || str_contains($field, 'actor') || str_contains($field, 'char') || str_contains($field, 'placement') || str_contains($field, 'condition') || str_contains($field, 'resource') || str_contains($field, 'hp'))
      ) {
        $mutation['entity_id'] = $normalized_actor_id;
      }

      if (
        !$has_room_target
        && $normalized_room_id !== ''
        && (str_contains($field, 'room') || str_contains($field, 'hazard') || str_contains($field, 'reveal'))
      ) {
        $mutation['room_id'] = $normalized_room_id;
      }

      $normalized[] = $mutation;
    }
    return $normalized;
  }

  /**
   * Core encounter intent orchestration implementation.
   */
}
