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

  /**
   * Shared actor action-availability resolver.
   */
  protected ActorActionAvailabilityService $actionAvailability;

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
    ?ActorActionAvailabilityService $action_availability = NULL
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
      if ($this->findRoomById($dungeon_data, trim($target_room)) === NULL) {
        return [
          'valid' => FALSE,
          'reason' => "Room '$target_room' does not exist.",
        ];
      }
      $connection = $this->resolveRoomTransitionCapability($dungeon_data, trim($target_room), $intent['params'] ?? []);
      if ($connection === NULL) {
        return [
          'valid' => FALSE,
          'reason' => "Room '$target_room' is not reachable from the active room.",
        ];
      }
      if (empty($connection['available'])) {
        return [
          'valid' => FALSE,
          'reason' => sprintf(
            "Room '%s' is not available for transition: %s.",
            $target_room,
            (string) ($connection['blocked_reason'] ?? 'blocked')
          ),
        ];
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
      $room_scene_actions = ['talk', 'search', 'interact', 'delay', 'end_turn', 'choose_not_to_act', 'treat_wounds', 'refocus', 'repair', 'daily_preparations'];
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

    switch ($type) {

      case 'transition':
        $result = $this->enterRoomFramework($actor_id, (string) ($params['target_room_id'] ?? ''), $params, $game_state, $dungeon_data, $campaign_id);
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
        break;

      case 'treat_wounds':
        $result = $this->processTreatWoundsRestAction($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
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
        break;

      case 'refocus':
        $result = $this->processRefocusRestAction($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
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
        break;

      case 'repair':
        $result = $this->processRepairRestAction($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
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
        break;

      case 'daily_preparations':
        $result = $this->processDailyPreparationsRestAction($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
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
        break;

      case 'strike':
        $result = $this->processStrike($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;

        // Consume 1 action.
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $game_state['turn']['attacks_this_turn'] = ($game_state['turn']['attacks_this_turn'] ?? 0) + 1;

        // DEF-2218: Attacking breaks cover — cover_active cleared on any attack.
        if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
          $game_state['entities'][$actor_id]['cover_active'] = FALSE;
        }

        // GAP-2265: Airborne creature attacking uses 2 air this turn (attack/spell = double air cost).
        {
          $enc_air_st = $this->encounterStore->loadEncounter($encounter_id);
          $ptcp_air_st = $enc_air_st ? $this->findEncounterParticipantByEntityId($enc_air_st, $actor_id) : NULL;
          if ($ptcp_air_st) {
            $edata_air_st = !empty($ptcp_air_st['entity_ref']) ? json_decode($ptcp_air_st['entity_ref'], TRUE) : [];
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

        // Queue strike for perception-filtered narration.
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
        // Also queue mechanical damage event if hit.
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

        break;

      case 'stride':
        $result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

        // DEF-2218: Moving breaks cover — cover_active cleared on any stride.
        if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
          $game_state['entities'][$actor_id]['cover_active'] = FALSE;
        }

        // Track stride distance for High Jump / Long Jump prerequisite checks.
        $game_state['turn']['last_stride_ft'] = (int) ($params['distance_ft'] ?? 25);

        $events[] = GameEventLogger::buildEvent('stride', 'encounter', $actor_id, [
          'from' => $params['from_hex'] ?? NULL,
          'to' => $params['to_hex'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'cast_spell':
        $spell_name = $params['spell_name'] ?? 'unknown';
        $action_cost = $params['action_cost'] ?? 2;
        $result = $this->processCastSpell($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

        // DEF-2218: Casting a spell breaks cover (manipulate trait — cover lost on any attacking action).
        if (!empty($game_state['entities'][$actor_id]['cover_active'])) {
          $game_state['entities'][$actor_id]['cover_active'] = FALSE;
        }

        // GAP-2265: Airborne creature casting a spell uses 2 air this turn.
        {
          $enc_air_cs = $this->encounterStore->loadEncounter($encounter_id);
          $ptcp_air_cs = $enc_air_cs ? $this->findEncounterParticipantByEntityId($enc_air_cs, $actor_id) : NULL;
          if ($ptcp_air_cs) {
            $edata_air_cs = !empty($ptcp_air_cs['entity_ref']) ? json_decode($ptcp_air_cs['entity_ref'], TRUE) : [];
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

        // Queue spell cast for narration.
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

        break;

      case 'skill': {
        $skill_name = trim((string) ($params['skill_name'] ?? $params['skill'] ?? 'Skill'));
        $skill_bonus = NULL;
        if (isset($params['skill_bonus'])) {
          $skill_bonus = (int) $params['skill_bonus'];
        }
        elseif (isset($params['skill_modifier'])) {
          $skill_bonus = (int) $params['skill_modifier'];
        }

        $action_cost = $this->getActionCost($type, $params);
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

        $events[] = GameEventLogger::buildEvent('skill', 'encounter', $actor_id, [
          'skill_name' => $skill_name,
          'skill_bonus' => $skill_bonus,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;
      }

      case 'feat': {
        $feat_name = trim((string) ($params['feat_name'] ?? $params['featName'] ?? 'Feat action'));
        $feat_id = $params['feat_id'] ?? $params['featId'] ?? NULL;

        $action_cost = $this->getActionCost($type, $params);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

        $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
        $result = [
          'summary' => sprintf('%s uses %s.', $actor_name, $feat_name),
          'feat_name' => $feat_name,
          'feat_id' => $feat_id,
        ];

        $events[] = GameEventLogger::buildEvent('feat', 'encounter', $actor_id, [
          'feat_name' => $feat_name,
          'feat_id' => $feat_id,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;
      }

      case 'consume_item': {
        $character_id_ci = $params['character_id'] ?? $params['characterId'] ?? NULL;
        $item_ci = is_array($params['item'] ?? NULL) ? $params['item'] : [];
        $item_name_ci = trim((string) ($item_ci['name'] ?? $item_ci['id'] ?? $item_ci['item_id'] ?? 'consumable'));
        if (!$character_id_ci || $item_name_ci === '') {
          return [
            'success' => FALSE,
            'result' => ['error' => 'consume_item requires params.character_id and params.item.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }

        $action_cost = $this->getActionCost($type, $params);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

        try {
          $inventory_ci = $this->characterStateService->updateInventory(
            (string) $character_id_ci,
            'consume',
            $item_ci,
            $campaign_id > 0 ? $campaign_id : NULL,
            $actor_id
          );
          $effects_ci = $this->characterStateService->applyConsumableEffects(
            (string) $character_id_ci,
            $item_ci,
            $campaign_id > 0 ? $campaign_id : NULL,
            $actor_id
          );
        }
        catch (\InvalidArgumentException $exception) {
          return [
            'success' => FALSE,
            'result' => ['error' => $exception->getMessage()],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }

        if (
          !empty($effects_ci['focus_points'])
          || !empty($effects_ci['spell_slots'])
        ) {
          $this->syncCanonicalSpellcastingProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data);
        }
        if (
          !empty($effects_ci['nutrition_days'])
          || !empty($effects_ci['hydration_days'])
        ) {
          $this->syncCanonicalSurvivalProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data);
        }

        $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
        $result = [
          'summary' => sprintf('%s uses %s.', $actor_name, $item_name_ci),
          'item_name' => $item_name_ci,
          'effects' => $effects_ci,
          'inventory' => $inventory_ci,
        ];

        $events[] = GameEventLogger::buildEvent('consume_item', 'encounter', $actor_id, [
          'item_name' => $item_name_ci,
          'effects' => $effects_ci,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;
      }

      // -----------------------------------------------------------------------
      // dc-cr-spells-ch07: Declare metamagic — free action before cast_spell.
      // Subsequent non-cast_spell action wastes the metamagic (cleared above).
      // -----------------------------------------------------------------------
      case 'declare_metamagic': {
        $metamagic_id_dm = $params['metamagic_id'] ?? NULL;
        if (!$metamagic_id_dm) {
          return ['success' => FALSE, 'result' => ['error' => 'declare_metamagic requires params.metamagic_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $game_state['turn']['metamagic_pending'][$actor_id] = $metamagic_id_dm;
        $result = ['declared' => TRUE, 'metamagic_id' => $metamagic_id_dm];
        $events[] = GameEventLogger::buildEvent('declare_metamagic', 'encounter', $actor_id, ['metamagic_id' => $metamagic_id_dm, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      case 'interact':
        if (!$encounter_id || $this->isRoomSceneMode($game_state)) {
          $result = ['interacted' => TRUE];
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          $events[] = GameEventLogger::buildEvent('interact', 'encounter', $actor_id, [
            'target' => $target_id,
            'round' => $game_state['round'] ?? NULL,
          ]);
          break;
        }
        $result = $this->processInteract($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

        $events[] = GameEventLogger::buildEvent('interact', 'encounter', $actor_id, [
          'target' => $target_id,
          'interaction' => $params['interaction_type'] ?? 'generic',
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      case 'talk':
        $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
        $params['_encounter_turn_ctx'] = $turn_ctx;

        $result = $this->processTalk($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        if (!empty($result['error'])) {
          return [
            'success' => FALSE,
            'result' => $result,
            'mutations' => $result['mutations'] ?? [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;

        // Talk consumes exactly 1 action in encounter.
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

        $events[] = GameEventLogger::buildEvent('talk', 'encounter', $actor_id, [
          'message' => $result['message'] ?? '',
          'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
          'turn_index' => $turn_ctx['turn_index_raw'] ?? ($game_state['turn']['index'] ?? NULL),
          'actor_name' => $turn_ctx['actor_name'] ?? NULL,
          'gm_response_generated' => !empty($result['gm_response']),
          'state_diff_present' => !empty($result['state_diff']),
        ], $narration, $target_id);
        break;

      case 'end_turn':
      case 'choose_not_to_act':
        if ($type === 'choose_not_to_act') {
          $params['reason'] = trim((string) ($params['reason'] ?? 'chooses not to act'));
        }

        // Capture turn context before any turn/round advance.
        $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);

        $result = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
        $time_effects = array_merge($time_effects, $this->buildRoundElapsedTimeEffects($result, $actor_id, $dungeon_data));
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
        $actor_name = (string) ($turn_ctx['actor_name'] ?? ($actor_id ? $this->resolveEntityName($actor_id, $game_state, $dungeon_data) : 'Narrator'));
        $fallback_narration = $type === 'choose_not_to_act'
          ? sprintf('%s chooses not to act.', $actor_name)
          : sprintf('%s ends their turn.', $actor_name);
        $resolved_narration = (is_string($narration) && trim($narration) !== '') ? $narration : $fallback_narration;
        $resolved_narration = $this->prefixEncounterChatLine($turn_ctx, $resolved_narration);

        $events[] = GameEventLogger::buildEvent($type, 'encounter', $actor_id, [
          'round' => $turn_ctx['round'] ?? ($game_state['round'] ?? NULL),
          'room_id' => $resolved_room_id,
          'actor_name' => $actor_name,
          'turn_index' => $turn_ctx['turn_index_raw'] ?? NULL,
          'actions_remaining' => $result['actions_remaining_before_end'] ?? NULL,
          'reason' => $params['reason'] ?? NULL,
        ], $resolved_narration);
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

        // End turn may trigger NPC auto-play, which generates additional events.
        if (!empty($result['npc_events'])) {
          $events = array_merge($events, $result['npc_events']);
        }

        break;

      case 'delay':
        $delay_remaining = $game_state['turn']['actions_remaining'] ?? 0;
        $turn_ctx = $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);
        $result = [
          'delayed' => TRUE,
          'remaining_actions' => $delay_remaining,
        ];
        $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
        $actor_name = (string) ($turn_ctx['actor_name'] ?? ($actor_id ? $this->resolveEntityName($actor_id, $game_state, $dungeon_data) : 'Narrator'));
        $events[] = GameEventLogger::buildEvent('delay', 'encounter', $actor_id, [
          'remaining_actions' => $delay_remaining,
          'round' => $game_state['round'] ?? NULL,
        ], $this->prefixEncounterChatLine($turn_ctx, sprintf('%s delays until the end of the round.', $actor_name)));
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
        $time_effects = array_merge($time_effects, $this->buildRoundElapsedTimeEffects($advance, $actor_id, $dungeon_data));
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
        break;

      case 'delay_reenter':
        // REQ 2193: Re-enter initiative after delay, restoring stored actions.
        if (empty($game_state['turn']['delayed'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Not currently delayed.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $reenter_actions = $game_state['turn']['delayed_actions_remaining'] ?? 0;
        $game_state['turn']['delayed'] = FALSE;
        $game_state['turn']['actions_remaining'] = $reenter_actions;
        $result = ['reentered' => TRUE, 'actions_restored' => $reenter_actions];
        $events[] = GameEventLogger::buildEvent('delay_reenter', 'encounter', $actor_id, [
          'actions_restored' => $reenter_actions,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'ready':
        // REQ 2203-2205: 2-action activity; store trigger action + MAP at time of readying.
        $ready_action = $params['ready_action'] ?? NULL;
        $ready_trigger = $params['ready_trigger'] ?? NULL;
        if (!$ready_action || !$ready_trigger) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'ready_action and ready_trigger are required.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        // REQ 2205: Cannot Ready a free action that already has its own trigger.
        if (!empty($params['is_triggered_free_action'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Cannot Ready a free action that already has a trigger.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $game_state['turn']['ready'] = [
          'action' => $ready_action,
          'trigger' => $ready_trigger,
          'map_at_ready' => $game_state['turn']['attacks_this_turn'] ?? 0,
        ];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = ['readied' => TRUE, 'action' => $ready_action, 'trigger' => $ready_trigger];
        $events[] = GameEventLogger::buildEvent('ready', 'encounter', $actor_id, [
          'action' => $ready_action,
          'trigger' => $ready_trigger,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'stand':
        // REQ 2213: Remove prone condition. 1 action.
        $enc_stand = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_stand = $enc_stand ? $this->findEncounterParticipantByEntityId($enc_stand, $actor_id) : NULL;
        if ($ptcp_stand) {
          $pid_stand = (int) $ptcp_stand['id'];
          foreach ($this->conditionManager->getActiveConditions($pid_stand, $encounter_id) as $cid => $crow) {
            if ($crow['condition_type'] === 'prone') {
              $this->conditionManager->removeCondition($pid_stand, $cid, $encounter_id);
              break;
            }
          }
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['stood' => TRUE];
        $events[] = GameEventLogger::buildEvent('stand', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]);
        break;

      case 'drop_prone':
        // REQ 2196: Apply prone condition. 1 action.
        $enc_dp = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_dp = $enc_dp ? $this->findEncounterParticipantByEntityId($enc_dp, $actor_id) : NULL;
        if ($ptcp_dp) {
          $pid_dp = (int) $ptcp_dp['id'];
          $this->conditionManager->applyCondition($pid_dp, 'prone', 1, 'persistent', 'drop_prone', $encounter_id);
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['prone' => TRUE];
        $events[] = GameEventLogger::buildEvent('drop_prone', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]);
        break;

      case 'step':
        // REQ 2214-2215: Move exactly 5 ft without triggering AoO. 1 action.
        // REQ 2251: Cannot Step into difficult terrain.
        if (empty($params['to_hex'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Missing to_hex.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        // REQ 2251: Reject if destination is difficult terrain.
        if ($this->movementResolver && $this->movementResolver->isDifficultTerrain($params['to_hex'], $dungeon_data)) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Cannot Step into difficult terrain.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $step_move = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $step_move['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $game_state['turn']['last_move_type'] = 'step';
        $result = ['stepped' => TRUE, 'to_hex' => $params['to_hex']];
        $events[] = GameEventLogger::buildEvent('step', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'crawl':
        // REQ 2192: Move 5 ft while prone; requires Speed >= 10. 1 action.
        if (empty($params['to_hex'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Missing to_hex.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $enc_crawl = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_crawl = $enc_crawl ? $this->findEncounterParticipantByEntityId($enc_crawl, $actor_id) : NULL;
        if (!$ptcp_crawl) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Participant not found.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $pid_crawl = (int) $ptcp_crawl['id'];
        if (!$this->conditionManager->hasCondition($pid_crawl, 'prone', $encounter_id)) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Must be prone to Crawl.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        if ((int) ($ptcp_crawl['speed'] ?? 25) < 10) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Speed is too low to Crawl (requires Speed >= 10 ft).'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $crawl_move = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $crawl_move['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['crawled' => TRUE, 'to_hex' => $params['to_hex']];
        $events[] = GameEventLogger::buildEvent('crawl', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'leap':
        // REQ 2201-2202: Jump up to 10 ft (Speed 15+) or 15 ft (Speed 30+). 1 action.
        if (empty($params['to_hex'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Missing to_hex.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $enc_leap = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_leap = $enc_leap ? $this->findEncounterParticipantByEntityId($enc_leap, $actor_id) : NULL;
        $leap_speed = (int) ($ptcp_leap['speed'] ?? 25);
        $max_leap_ft = $leap_speed >= 30 ? 15 : 10;
        $leap_move = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $leap_move['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['leaped' => TRUE, 'to_hex' => $params['to_hex'], 'max_leap_ft' => $max_leap_ft];
        $events[] = GameEventLogger::buildEvent('leap', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'escape':
        // REQ 2197-2199: Roll vs grapple DC; attack trait applies MAP. 1 action.
        $result = $this->processEscape($encounter_id, $actor_id, $params, $game_state);
        $mutations = $result['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $events[] = GameEventLogger::buildEvent('escape', 'encounter', $actor_id, [
          'degree' => $result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'seek':
        // REQ 2207-2210: Secret Perception roll vs each target's Stealth DC. 1 action.
        $result = $this->processSeek($encounter_id, $actor_id, $params, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $events[] = GameEventLogger::buildEvent('seek', 'encounter', $actor_id, [
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'search':
        if (!$this->explorationPhaseHandler) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Room search handler is unavailable.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $result = $this->explorationPhaseHandler->processSearch($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mechanical_result = $result;
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        if (!empty($game_state['turn'])) {
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        }
        $public_discoveries = $this->buildPublicSearchDiscoveries($result['discoveries'] ?? []);
        if ($public_discoveries !== [] || (is_string($narration) && trim($narration) !== '')) {
          $events[] = GameEventLogger::buildEvent('search', 'encounter', $actor_id, [
            'discoveries' => $public_discoveries,
            'round' => $game_state['round'] ?? NULL,
          ], $narration);
        }
        $result = $this->buildPublicSearchResult($result);
        break;

      case 'sense_motive':
        // REQ 2211-2212: Secret Perception vs Deception; track retry cooldown. 1 action.
        {
          $sm_bonus = (int) ($params['perception_bonus'] ?? 0);
          $sm_dc = (int) ($params['deception_dc'] ?? 15);
          $sm_d20 = $this->numberGenerationService->rollPathfinderDie(20);
          $sm_total = $sm_d20 + $sm_bonus;
          $sm_degree = $this->combatCalculator->calculateDegreeOfSuccess($sm_total, $sm_dc, $sm_d20);
          if (!isset($game_state['sense_motive'])) {
            $game_state['sense_motive'] = [];
          }
          if (!isset($game_state['sense_motive'][$actor_id])) {
            $game_state['sense_motive'][$actor_id] = [];
          }
          // Track last-used round for retry cooldown (REQ 2212).
          $game_state['sense_motive'][$actor_id][$target_id] = $game_state['round'] ?? 0;
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          // Secret result: return degree only (not raw d20) to caller.
          $result = ['sense_motive' => TRUE, 'degree' => $sm_degree];
          $events[] = GameEventLogger::buildEvent('sense_motive', 'encounter', $actor_id, [
            'round' => $game_state['round'] ?? NULL,
          ], NULL, $target_id);
        }
        break;

      case 'take_cover':
        // REQ 2218: Upgrade cover tier (none→standard, standard→greater). 1 action.
        if (!isset($game_state['entities'])) {
          $game_state['entities'] = [];
        }
        if (!isset($game_state['entities'][$actor_id])) {
          $game_state['entities'][$actor_id] = [];
        }
        $cur_cover = $game_state['entities'][$actor_id]['cover'] ?? 'none';
        $new_cover = ($cur_cover === 'standard') ? 'greater' : 'standard';
        $game_state['entities'][$actor_id]['cover'] = $new_cover;
        $game_state['entities'][$actor_id]['cover_active'] = TRUE;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['cover' => $new_cover, 'cover_active' => TRUE];
        $events[] = GameEventLogger::buildEvent('take_cover', 'encounter', $actor_id, [
          'cover' => $new_cover,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'release':
        // REQ 2206: Free action; drop held item; does not trigger manipulate-trait reactions.
        $rel_item = $params['item_id'] ?? NULL;
        if (!empty($dungeon_data['entities'])) {
          foreach ($dungeon_data['entities'] as &$rel_ent) {
            $rel_iid = $rel_ent['entity_instance_id'] ?? ($rel_ent['instance_id'] ?? ($rel_ent['id'] ?? NULL));
            if ($rel_iid === $actor_id) {
              if ($rel_item && isset($rel_ent['equipment']['held'][$rel_item])) {
                unset($rel_ent['equipment']['held'][$rel_item]);
              }
              break;
            }
          }
          unset($rel_ent);
        }
        // Free action: no standard action deducted.
        $result = ['released' => TRUE, 'item_id' => $rel_item];
        $events[] = GameEventLogger::buildEvent('release', 'encounter', $actor_id, [
          'item_id' => $rel_item,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'aid_setup':
        // REQ 2190: Prepare Aid for a target ally. 1 action (on a previous turn).
        if (!isset($game_state['turn']['aid_prepared'])) {
          $game_state['turn']['aid_prepared'] = [];
        }
        $aid_skill = $params['skill'] ?? 'generic';
        $game_state['turn']['aid_prepared'][$actor_id][$target_id] = $aid_skill;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['aid_prepared' => TRUE, 'target' => $target_id, 'skill' => $aid_skill];
        $events[] = GameEventLogger::buildEvent('aid_setup', 'encounter', $actor_id, [
          'target' => $target_id,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      case 'aid':
        // REQ 2190-2191: Reaction; verify aid was prepared, roll check vs DC 20.
        $result = $this->processAid($actor_id, $target_id, $params, $game_state);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('aid', 'encounter', $actor_id, [
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'aid_bonus' => $result['aid_bonus'] ?? 0,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      case 'reaction':
        // Reaction: spend reaction resource.
        $reaction_available = $game_state['turn']['reaction_available'] ?? TRUE;
        if (!$reaction_available) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Reaction already spent this round.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $game_state['turn']['reaction_available'] = FALSE;
        // GAP-2204: If firing a readied action that is a strike, restore MAP count
        // from map_at_ready so the attack uses the MAP that was active when Ready was declared.
        $ready_data = $game_state['turn']['ready'] ?? NULL;
        if ($ready_data && ($ready_data['action'] ?? '') === 'strike') {
          $game_state['turn']['attacks_this_turn'] = (int) ($ready_data['map_at_ready'] ?? 0);
        }
        $result = ['reaction_used' => TRUE, 'reaction_type' => $params['reaction_type'] ?? 'generic'];
        $events[] = GameEventLogger::buildEvent('reaction', 'encounter', $actor_id, [
          'reaction_type' => $params['reaction_type'] ?? 'generic',
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      // -----------------------------------------------------------------------
      // REQ 2280: Hero Point Reroll — spend 1 hero point to reroll an attack.
      // Free action; must be declared before the attack result is used.
      // -----------------------------------------------------------------------
      case 'hero_point_reroll': {
        $original_roll = (int) ($params['original_roll'] ?? 0);
        $reroll = $this->calculator->heroPointReroll($original_roll);
        // Deduct 1 hero point from entity_ref.
        $enc_hpr = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_hpr = $enc_hpr ? $this->findEncounterParticipantByEntityId($enc_hpr, $actor_id) : NULL;
        if ($ptcp_hpr) {
          $edata_hpr = !empty($ptcp_hpr['entity_ref']) ? json_decode($ptcp_hpr['entity_ref'], TRUE) : [];
          $hero_points = max(0, (int) ($edata_hpr['hero_points'] ?? 0) - 1);
          $edata_hpr['hero_points'] = $hero_points;
          $this->encounterStore->updateParticipant((int) $ptcp_hpr['id'], ['entity_ref' => json_encode($edata_hpr)]);
        }
        $result = $reroll + ['hero_points_spent' => 1];
        $events[] = GameEventLogger::buildEvent('hero_point_reroll', 'encounter', $actor_id, [
          'original_roll' => $original_roll,
          'new_roll'      => $reroll['new_roll'],
          'round'         => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2281: Heroic Recovery (spend all Hero Points) — removes dying,
      // does NOT add wounded, keeps HP at 0. Reaction; costs no actions.
      // -----------------------------------------------------------------------
      case 'heroic_recovery_all_points': {
        $ptcp_id_hrap = $actor_id;
        if (is_string($ptcp_id_hrap)) {
          // Resolve actor entity_id → participant DB id.
          $enc_hrap = $this->encounterStore->loadEncounter($encounter_id);
          $ptcp_hrap = $enc_hrap ? $this->findEncounterParticipantByEntityId($enc_hrap, $actor_id) : NULL;
          $ptcp_id_hrap = $ptcp_hrap ? (int) $ptcp_hrap['id'] : NULL;
        }
        if (!$ptcp_id_hrap) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // Clear hero_points in entity_ref (spend all).
        $enc_hrap2 = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_hrap2 = $enc_hrap2 ? $this->findEncounterParticipantByEntityId($enc_hrap2, $actor_id) : NULL;
        if ($ptcp_hrap2) {
          $edata_hrap = !empty($ptcp_hrap2['entity_ref']) ? json_decode($ptcp_hrap2['entity_ref'], TRUE) : [];
          $edata_hrap['hero_points'] = 0;
          $this->encounterStore->updateParticipant((int) $ptcp_hrap2['id'], ['entity_ref' => json_encode($edata_hrap)]);
        }
        $hrap_result = $this->hpManager->heroicRecoveryAllPoints($ptcp_id_hrap, $encounter_id);
        $result = $hrap_result;
        $events[] = GameEventLogger::buildEvent('heroic_recovery_all_points', 'encounter', $actor_id, [
          'dying_removed' => $hrap_result['dying_removed'] ?? FALSE,
          'round'         => $game_state['round'] ?? NULL,
        ]);
        break;
      }

      // -----------------------------------------------------------------------
      // -----------------------------------------------------------------------
      // REQ 1619–1620: Climb [1 action, Move] — Athletics vs climb DC.
      // -----------------------------------------------------------------------
      case 'climb': {
        $enc_cl = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_cl = $enc_cl ? $this->findEncounterParticipantByEntityId($enc_cl, $actor_id) : NULL;
        if (!$ptcp_cl) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_cl = !empty($ptcp_cl['entity_ref']) ? json_decode($ptcp_cl['entity_ref'], TRUE) : [];
        $has_climb_speed = !empty($entity_cl['climb_speed']) && (int) $entity_cl['climb_speed'] > 0;
        $land_speed = (int) ($entity_cl['speed'] ?? 25);
        $athletics_cl = (int) ($params['athletics_bonus'] ?? 0);
        $climb_dc = (int) ($params['climb_dc'] ?? 15);

        // GAP-2234: Characters with a climb Speed auto-succeed at Climb (no roll needed)
        // and gain a +4 circumstance bonus to Athletics if a check is required.
        if ($has_climb_speed) {
          $athletics_cl += 4;
          // Auto-succeed: skip the roll and treat as success.
          $d20_cl = 0;
          $total_cl = 0;
          $degree_cl = 'success';
          $feet_moved = (int) $entity_cl['climb_speed'];
        }
        else {
          $d20_cl = $this->numberGenerationService->rollPathfinderDie(20);
          $total_cl = $d20_cl + $athletics_cl;
          $degree_cl = $this->combatCalculator->calculateDegreeOfSuccess($total_cl, $climb_dc, $d20_cl);
          $feet_moved = 0;
          if ($degree_cl === 'critical_success') {
            $feet_moved = max(10, (int) round($land_speed / 2));
          }
          elseif ($degree_cl === 'success') {
            $feet_moved = max(5, (int) round($land_speed / 4));
          }
          elseif ($degree_cl === 'critical_failure') {
            // Character falls and lands prone.
            $feet_fallen = (int) ($params['height_ft'] ?? 10);
            $soft_surface = !empty($params['soft_surface']);
            if ($ptcp_cl && $this->hpManager) {
              $this->hpManager->applyFallDamage((int) $ptcp_cl['id'], $feet_fallen, $encounter_id, $soft_surface);
            }
          }
        }

        $fell = ($degree_cl === 'critical_failure');

        // Flat-footed during climb unless character has a climb Speed.
        if (!$has_climb_speed && !$fell) {
          $this->conditionManager->applyCondition((int) $ptcp_cl['id'], 'flat_footed', 0, ['type' => 'encounter', 'remaining' => 1], 'climb', $encounter_id);
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['climbed' => !$fell, 'degree' => $degree_cl, 'feet_moved' => $feet_moved, 'fell' => $fell, 'd20' => $d20_cl, 'total' => $total_cl];
        $events[] = GameEventLogger::buildEvent('climb', 'encounter', $actor_id, ['degree' => $degree_cl, 'fell' => $fell, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1621–1625: Force Open [1 action, Attack] — Athletics vs Fortitude DC.
      // -----------------------------------------------------------------------
      case 'force_open': {
        $has_crowbar = !empty($params['has_crowbar']);
        $athletics_fo = (int) ($params['athletics_bonus'] ?? 0);
        $item_penalty = $has_crowbar ? 0 : -2;
        $fo_dc = (int) ($params['object_dc'] ?? 20);
        $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
        $map_fo = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
        $d20_fo = $this->numberGenerationService->rollPathfinderDie(20);
        $total_fo = $d20_fo + $athletics_fo + $item_penalty + $map_fo;
        $degree_fo = $this->combatCalculator->calculateDegreeOfSuccess($total_fo, $fo_dc, $d20_fo);

        $jammed = FALSE;
        $broken = FALSE;
        $opened = FALSE;
        if ($degree_fo === 'critical_success') {
          $opened = TRUE;
        }
        elseif ($degree_fo === 'success') {
          $opened = TRUE;
          $broken = TRUE;
        }
        elseif ($degree_fo === 'critical_failure') {
          $jammed = TRUE;
          // Track jammed penalty for future attempts.
          if (!isset($game_state['force_open_jammed'])) {
            $game_state['force_open_jammed'] = [];
          }
          $target_obj = $params['object_id'] ?? $target_id;
          $game_state['force_open_jammed'][$target_obj] = ($game_state['force_open_jammed'][$target_obj] ?? 0) - 2;
        }

        $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['opened' => $opened, 'broken' => $broken, 'jammed' => $jammed, 'degree' => $degree_fo, 'd20' => $d20_fo, 'total' => $total_fo];
        $events[] = GameEventLogger::buildEvent('force_open', 'encounter', $actor_id, ['degree' => $degree_fo, 'opened' => $opened, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1626–1631: Grapple [1 action, Attack] — Athletics vs Fortitude DC.
      // -----------------------------------------------------------------------
      case 'grapple': {
        $result = $this->processGrapple($encounter_id, $actor_id, $target_id, $params, $game_state);
        $mutations = $result['mutations'] ?? [];
        $game_state['turn']['attacks_this_turn'] = ($game_state['turn']['attacks_this_turn'] ?? 0) + 1;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $events[] = GameEventLogger::buildEvent('grapple', 'encounter', $actor_id, ['degree' => $result['degree'] ?? NULL, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1632–1636: High Jump [2 actions] — Stride ≥10 ft + Athletics vs DC.
      // -----------------------------------------------------------------------
      case 'high_jump': {
        // Requires a prior Stride of ≥10 ft this turn.
        $prior_stride_ft = (int) ($game_state['turn']['last_stride_ft'] ?? 0);
        if ($prior_stride_ft < 10) {
          // Auto-fail — no prone applied.
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
          $result = ['jumped' => FALSE, 'auto_fail' => TRUE, 'reason' => 'No prior Stride of ≥10 ft'];
          $events[] = GameEventLogger::buildEvent('high_jump', 'encounter', $actor_id, ['auto_fail' => TRUE, 'round' => $game_state['round'] ?? NULL]);
          break;
        }

        $dc_hj = (int) ($params['dc'] ?? 30);
        $athletics_hj = (int) ($params['athletics_bonus'] ?? 0);
        $d20_hj = $this->numberGenerationService->rollPathfinderDie(20);
        $total_hj = $d20_hj + $athletics_hj;
        $degree_hj = $this->combatCalculator->calculateDegreeOfSuccess($total_hj, $dc_hj, $d20_hj);

        $height_ft = 0;
        $fell_prone = FALSE;
        if ($degree_hj === 'critical_success') {
          $height_ft = 8;
        }
        elseif ($degree_hj === 'success') {
          $height_ft = 5;
        }
        elseif ($degree_hj === 'failure') {
          // Normal Leap.
          $height_ft = 0;
        }
        elseif ($degree_hj === 'critical_failure') {
          $fell_prone = TRUE;
          $enc_hj = $this->encounterStore->loadEncounter($encounter_id);
          $ptcp_hj = $enc_hj ? $this->findEncounterParticipantByEntityId($enc_hj, $actor_id) : NULL;
          if ($ptcp_hj) {
            $this->conditionManager->applyCondition((int) $ptcp_hj['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'high_jump', $encounter_id);
          }
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = ['jumped' => !$fell_prone, 'height_ft' => $height_ft, 'degree' => $degree_hj, 'fell_prone' => $fell_prone, 'd20' => $d20_hj, 'total' => $total_hj];
        $events[] = GameEventLogger::buildEvent('high_jump', 'encounter', $actor_id, ['degree' => $degree_hj, 'height_ft' => $height_ft, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1637–1640: Long Jump [2 actions] — Stride ≥10 ft + Athletics vs DC.
      // -----------------------------------------------------------------------
      case 'long_jump': {
        $prior_stride_ft = (int) ($game_state['turn']['last_stride_ft'] ?? 0);
        if ($prior_stride_ft < 10) {
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
          $result = ['jumped' => FALSE, 'auto_fail' => TRUE, 'reason' => 'No prior Stride of ≥10 ft'];
          $events[] = GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, ['auto_fail' => TRUE, 'round' => $game_state['round'] ?? NULL]);
          break;
        }

        $target_ft = (int) ($params['target_ft'] ?? 10);
        $enc_lj = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_lj = $enc_lj ? $this->findEncounterParticipantByEntityId($enc_lj, $actor_id) : NULL;
        $entity_lj = $ptcp_lj && !empty($ptcp_lj['entity_ref']) ? json_decode($ptcp_lj['entity_ref'], TRUE) : [];
        $speed_lj = (int) ($entity_lj['speed'] ?? $ptcp_lj['speed'] ?? 25);

        // Cap at character Speed.
        if ($target_ft > $speed_lj) {
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
          $result = ['jumped' => FALSE, 'auto_fail' => TRUE, 'reason' => 'Target distance exceeds Speed'];
          $events[] = GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, ['auto_fail' => TRUE, 'reason' => 'speed_cap', 'round' => $game_state['round'] ?? NULL]);
          break;
        }

        $dc_lj = $target_ft; // DC = distance in feet.
        $athletics_lj = (int) ($params['athletics_bonus'] ?? 0);
        $d20_lj = $this->numberGenerationService->rollPathfinderDie(20);
        $total_lj = $d20_lj + $athletics_lj;
        $degree_lj = $this->combatCalculator->calculateDegreeOfSuccess($total_lj, $dc_lj, $d20_lj);

        $distance_ft = 0;
        $fell_prone = FALSE;
        if (in_array($degree_lj, ['critical_success', 'success'], TRUE)) {
          $distance_ft = $target_ft;
        }
        elseif ($degree_lj === 'failure') {
          // Normal Leap.
          $distance_ft = 0;
        }
        elseif ($degree_lj === 'critical_failure') {
          // Normal Leap + prone.
          $fell_prone = TRUE;
          if ($ptcp_lj) {
            $this->conditionManager->applyCondition((int) $ptcp_lj['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'long_jump', $encounter_id);
          }
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = ['jumped' => !$fell_prone || $distance_ft > 0, 'distance_ft' => $distance_ft, 'degree' => $degree_lj, 'fell_prone' => $fell_prone, 'd20' => $d20_lj, 'total' => $total_lj];
        $events[] = GameEventLogger::buildEvent('long_jump', 'encounter', $actor_id, ['degree' => $degree_lj, 'distance_ft' => $distance_ft, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1641–1644: Shove [1 action, Attack] — Athletics vs Fortitude DC.
      // -----------------------------------------------------------------------
      case 'shove': {
        $athletics_sh = (int) ($params['athletics_bonus'] ?? 0);
        $sh_dc = (int) ($params['fortitude_dc'] ?? 15);
        $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
        $map_sh = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
        $d20_sh = $this->numberGenerationService->rollPathfinderDie(20);
        $total_sh = $d20_sh + $athletics_sh + $map_sh;
        $degree_sh = $this->combatCalculator->calculateDegreeOfSuccess($total_sh, $sh_dc, $d20_sh);

        $push_ft = 0;
        $attacker_prone = FALSE;
        if ($degree_sh === 'critical_success') {
          $push_ft = 10;
        }
        elseif ($degree_sh === 'success') {
          $push_ft = 5;
        }
        elseif ($degree_sh === 'critical_failure') {
          // Attacker falls prone.
          $enc_sh = $this->encounterStore->loadEncounter($encounter_id);
          $ptcp_sh = $enc_sh ? $this->findEncounterParticipantByEntityId($enc_sh, $actor_id) : NULL;
          if ($ptcp_sh) {
            $this->conditionManager->applyCondition((int) $ptcp_sh['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'shove', $encounter_id);
          }
          $attacker_prone = TRUE;
        }

        // REQ 1643: Forced movement does NOT trigger movement reactions.
        $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['shoved' => $push_ft > 0, 'push_ft' => $push_ft, 'degree' => $degree_sh, 'forced_movement' => TRUE, 'attacker_prone' => $attacker_prone, 'd20' => $d20_sh, 'total' => $total_sh];
        $events[] = GameEventLogger::buildEvent('shove', 'encounter', $actor_id, ['degree' => $degree_sh, 'push_ft' => $push_ft, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1645–1649: Swim [1 action, Move] — no check in calm water.
      // -----------------------------------------------------------------------
      case 'swim': {
        $enc_sw = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_sw = $enc_sw ? $this->findEncounterParticipantByEntityId($enc_sw, $actor_id) : NULL;
        if (!$ptcp_sw) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_sw = !empty($ptcp_sw['entity_ref']) ? json_decode($ptcp_sw['entity_ref'], TRUE) : [];

        $is_calm = !empty($params['calm_water']);
        $athletics_sw = (int) ($params['athletics_bonus'] ?? 0);
        $swim_dc = (int) ($params['swim_dc'] ?? 15);
        $land_speed_sw = (int) ($entity_sw['speed'] ?? 25);
        $has_swim_speed = !empty($entity_sw['swim_speed']) && (int) $entity_sw['swim_speed'] > 0;

        // GAP-2235: Characters with a swim Speed auto-succeed at Swim (no roll needed)
        // and gain a +4 circumstance bonus to Athletics if a check is forced.
        if ($has_swim_speed) {
          $athletics_sw += 4;
          $is_calm = TRUE; // Auto-succeed: treat as calm water regardless of actual water state.
        }

        $degree_sw = 'success';
        $d20_sw = 0;
        $total_sw = 0;
        if (!$is_calm) {
          $d20_sw = $this->numberGenerationService->rollPathfinderDie(20);
          $total_sw = $d20_sw + $athletics_sw;
          $degree_sw = $this->combatCalculator->calculateDegreeOfSuccess($total_sw, $swim_dc, $d20_sw);
        }

        $feet_moved = 0;
        $breath_lost = FALSE;
        if ($degree_sw === 'critical_success') {
          $feet_moved = max(10, (int) round($land_speed_sw / 2));
        }
        elseif ($degree_sw === 'success') {
          $feet_moved = max(5, (int) round($land_speed_sw / 4));
        }
        elseif ($degree_sw === 'critical_failure') {
          // Costs 1 round of held breath.
          $breath_lost = TRUE;
          $held_breath = max(0, (int) ($game_state['entities'][$actor_id]['held_breath_rounds'] ?? 0) - 1);
          if (!isset($game_state['entities'][$actor_id])) {
            $game_state['entities'][$actor_id] = [];
          }
          $game_state['entities'][$actor_id]['held_breath_rounds'] = $held_breath;
        }

        // REQ 1647: Air-breathing characters must hold breath; track submerged state.
        if (empty($entity_sw['water_breathing']) && !$has_swim_speed) {
          if (!isset($game_state['entities'][$actor_id])) {
            $game_state['entities'][$actor_id] = [];
          }
          $game_state['entities'][$actor_id]['submerged'] = TRUE;
        }

        // Track swim action for end-of-turn sink rule (REQ 1648).
        if (!isset($game_state['turn']['swim_actions'])) {
          $game_state['turn']['swim_actions'] = [];
        }
        $game_state['turn']['swim_actions'][$actor_id] = ($game_state['turn']['swim_actions'][$actor_id] ?? 0) + 1;

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['swam' => $feet_moved > 0, 'degree' => $degree_sw, 'feet_moved' => $feet_moved, 'breath_lost' => $breath_lost, 'd20' => $d20_sw, 'total' => $total_sw];
        $events[] = GameEventLogger::buildEvent('swim', 'encounter', $actor_id, ['degree' => $degree_sw, 'feet_moved' => $feet_moved, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1650–1654: Trip [1 action, Attack] — Athletics vs Reflex DC.
      // -----------------------------------------------------------------------
      case 'trip': {
        $athletics_tr = (int) ($params['athletics_bonus'] ?? 0);
        $tr_dc = (int) ($params['reflex_dc'] ?? 15);
        $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
        $map_tr = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
        $d20_tr = $this->numberGenerationService->rollPathfinderDie(20);
        $total_tr = $d20_tr + $athletics_tr + $map_tr;
        $degree_tr = $this->combatCalculator->calculateDegreeOfSuccess($total_tr, $tr_dc, $d20_tr);

        $enc_tr = $this->encounterStore->loadEncounter($encounter_id);
        $target_ptcp_tr = $enc_tr ? $this->findEncounterParticipantByEntityId($enc_tr, $target_id) : NULL;
        $actor_ptcp_tr = $enc_tr ? $this->findEncounterParticipantByEntityId($enc_tr, $actor_id) : NULL;

        $damage_tr = 0;
        $attacker_prone = FALSE;
        if ($degree_tr === 'critical_success') {
          // 1d6 bludgeoning + prone to target.
          $damage_tr = $this->numberGenerationService->rollPathfinderDie(6);
          if ($target_ptcp_tr) {
            $this->hpManager->applyDamage((int) $target_ptcp_tr['id'], $damage_tr, 'bludgeoning', 'trip', $encounter_id);
            $this->conditionManager->applyCondition((int) $target_ptcp_tr['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
          }
        }
        elseif ($degree_tr === 'success') {
          // Prone only.
          if ($target_ptcp_tr) {
            $this->conditionManager->applyCondition((int) $target_ptcp_tr['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
          }
        }
        elseif ($degree_tr === 'critical_failure') {
          // Attacker falls prone.
          if ($actor_ptcp_tr) {
            $this->conditionManager->applyCondition((int) $actor_ptcp_tr['id'], 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'trip', $encounter_id);
          }
          $attacker_prone = TRUE;
        }

        $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['tripped' => in_array($degree_tr, ['critical_success', 'success'], TRUE), 'degree' => $degree_tr, 'damage' => $damage_tr, 'attacker_prone' => $attacker_prone, 'd20' => $d20_tr, 'total' => $total_tr];
        $events[] = GameEventLogger::buildEvent('trip', 'encounter', $actor_id, ['degree' => $degree_tr, 'damage' => $damage_tr, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1655–1659: Disarm [1 action, Attack, Trained] — Athletics vs Reflex DC.
      // -----------------------------------------------------------------------
      case 'disarm': {
        // REQ 1655: Trained Athletics required.
        $proficiency_rank = (int) ($params['athletics_proficiency_rank'] ?? 0);
        if ($proficiency_rank < 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Disarm requires Trained Athletics.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $athletics_di = (int) ($params['athletics_bonus'] ?? 0);
        $di_dc = (int) ($params['reflex_dc'] ?? 15);
        $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
        $map_di = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
        $d20_di = $this->numberGenerationService->rollPathfinderDie(20);
        $total_di = $d20_di + $athletics_di + $map_di;
        $degree_di = $this->combatCalculator->calculateDegreeOfSuccess($total_di, $di_dc, $d20_di);

        $enc_di = $this->encounterStore->loadEncounter($encounter_id);
        $actor_ptcp_di = $enc_di ? $this->findEncounterParticipantByEntityId($enc_di, $actor_id) : NULL;

        $item_dropped = FALSE;
        $grip_weakened = FALSE;
        $attacker_flat_footed = FALSE;

        if ($degree_di === 'critical_success') {
          $item_dropped = TRUE;
        }
        elseif ($degree_di === 'success') {
          // Grip weakened until start of target's next turn.
          $grip_weakened = TRUE;
          if (!isset($game_state['grip_weakened'])) {
            $game_state['grip_weakened'] = [];
          }
          $game_state['grip_weakened'][$target_id] = ($game_state['round'] ?? 0) + 1;
        }
        elseif ($degree_di === 'critical_failure') {
          // Attacker becomes flat-footed.
          if ($actor_ptcp_di) {
            $this->conditionManager->applyCondition((int) $actor_ptcp_di['id'], 'flat_footed', 0, ['type' => 'encounter', 'remaining' => 1], 'disarm', $encounter_id);
          }
          $attacker_flat_footed = TRUE;
        }

        $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['disarmed' => $item_dropped, 'grip_weakened' => $grip_weakened, 'degree' => $degree_di, 'attacker_flat_footed' => $attacker_flat_footed, 'd20' => $d20_di, 'total' => $total_di];
        $events[] = GameEventLogger::buildEvent('disarm', 'encounter', $actor_id, ['degree' => $degree_di, 'item_dropped' => $item_dropped, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1688–1692: Administer First Aid [2 actions, Manipulation, Trained]
      // -----------------------------------------------------------------------
      case 'administer_first_aid': {
        $enc_afa = $this->encounterStore->loadEncounter($encounter_id);
        $actor_ptcp_afa = $enc_afa ? $this->findEncounterParticipantByEntityId($enc_afa, $actor_id) : NULL;
        $target_ptcp_afa = ($enc_afa && $target_id) ? $this->findEncounterParticipantByEntityId($enc_afa, $target_id) : NULL;

        // REQ 1688: Trained Medicine required.
        $med_rank_afa = (int) ($params['medicine_proficiency_rank'] ?? 0);
        if ($med_rank_afa < 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Administer First Aid requires Trained Medicine.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // REQ 1688: Healer's tools required (improvised = -2 penalty).
        if (empty($params['has_healers_tools'])) {
          return ['success' => FALSE, 'result' => ['error' => 'Administer First Aid requires healer\'s tools.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $tools_penalty_afa = !empty($params['is_improvised_tools']) ? -2 : 0;

        // REQ 1692: Once per round per condition per target.
        $mode_afa = $params['mode'] ?? 'stabilize';
        if (!in_array($mode_afa, ['stabilize', 'stop_bleeding'], TRUE)) {
          return ['success' => FALSE, 'result' => ['error' => "Unknown First Aid mode '{$mode_afa}'. Use 'stabilize' or 'stop_bleeding'."], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $fa_used_key = $target_id . ':' . $mode_afa;
        $current_round_afa = $game_state['round'] ?? 0;
        if (isset($game_state['first_aid_used'][$fa_used_key]) && $game_state['first_aid_used'][$fa_used_key] === $current_round_afa) {
          return ['success' => FALSE, 'result' => ['error' => 'Cannot Administer First Aid on the same condition and target twice in the same round.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $med_bonus_afa = (int) ($params['medicine_bonus'] ?? 0);
        $d20_afa = $this->numberGenerationService->rollPathfinderDie(20);
        $total_afa = $d20_afa + $med_bonus_afa + $tools_penalty_afa;

        $afa_result = $this->processAdministerFirstAid(
          $target_ptcp_afa,
          $actor_ptcp_afa,
          $mode_afa,
          $total_afa,
          $d20_afa,
          $params,
          $encounter_id
        );

        $game_state['first_aid_used'][$fa_used_key] = $current_round_afa;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = array_merge($afa_result, ['d20' => $d20_afa, 'total' => $total_afa, 'mode' => $mode_afa, 'tools_penalty' => $tools_penalty_afa]);
        $events[] = GameEventLogger::buildEvent('administer_first_aid', 'encounter', $actor_id, ['mode' => $mode_afa, 'degree' => $afa_result['degree'] ?? NULL, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1695–1698: Treat Poison [1 action, Manipulation, Trained]
      // -----------------------------------------------------------------------
      case 'treat_poison': {
        // REQ 1695: Trained Medicine required.
        $med_rank_tp = (int) ($params['medicine_proficiency_rank'] ?? 0);
        if ($med_rank_tp < 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Treat Poison requires Trained Medicine.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // REQ 1695: Healer's tools required.
        if (empty($params['has_healers_tools'])) {
          return ['success' => FALSE, 'result' => ['error' => 'Treat Poison requires healer\'s tools.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        // REQ 1697: One attempt per creature per poison save.
        $poison_key_tp = ($target_id ?? $actor_id) . ':poison';
        if (!empty($game_state['poison_treated'][$poison_key_tp])) {
          return ['success' => FALSE, 'result' => ['error' => 'Can only treat one poison per save for this target.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $med_bonus_tp = (int) ($params['medicine_bonus'] ?? 0);
        $poison_dc_tp = (int) ($params['poison_dc'] ?? 15);
        $d20_tp = $this->numberGenerationService->rollPathfinderDie(20);
        $total_tp = $d20_tp + $med_bonus_tp;
        $degree_tp = $this->combatCalculator->calculateDegreeOfSuccess($total_tp, $poison_dc_tp, $d20_tp);

        $treated_tp = in_array($degree_tp, ['critical_success', 'success'], TRUE);
        if ($treated_tp) {
          // REQ 1696: Next poison save is one degree better.
          $game_state['poison_treated'][$poison_key_tp] = TRUE;
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['treated' => $treated_tp, 'degree' => $degree_tp, 'd20' => $d20_tp, 'total' => $total_tp, 'dc' => $poison_dc_tp];
        $events[] = GameEventLogger::buildEvent('treat_poison', 'encounter', $actor_id, ['degree' => $degree_tp, 'treated' => $treated_tp, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // Battle Medicine [1 action, Manipulate, General Skill Feat]
      // Requires: healer's tools + Trained Medicine; same DC/HP table as Treat Wounds.
      // Does NOT remove wounded condition. Per-healer 1-day immunity per target.
      // -----------------------------------------------------------------------
      case 'battle_medicine': {
        $med_rank_bm = (int) ($params['medicine_proficiency_rank'] ?? 0);
        if ($med_rank_bm < 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Battle Medicine requires Trained Medicine.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        if (empty($params['has_healers_tools'])) {
          return ['success' => FALSE, 'result' => ['error' => 'Battle Medicine requires healer\'s tools.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        // Per-healer 1-day immunity per target (keyed by actor+target pair).
        $effective_target_bm = $target_id ?? $actor_id;
        $bm_immune_key = $actor_id . ':' . $effective_target_bm;
        if (!empty($game_state['battle_medicine_immune'][$bm_immune_key])) {
          return ['success' => FALSE, 'result' => ['error' => 'Target is immune to this healer\'s Battle Medicine for 1 day.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $dc_table_bm   = [1 => 15, 2 => 20, 3 => 30, 4 => 40];
        $hp_bonus_bm   = [1 => 0,  2 => 10, 3 => 30, 4 => 50];
        $rank_key_bm   = min(4, max(1, $med_rank_bm));
        $dc_bm         = (int) ($params['override_dc'] ?? $dc_table_bm[$rank_key_bm]);
        $med_bonus_bm  = (int) ($params['medicine_bonus'] ?? 0);
        $item_bonus_bm = !empty($params['is_improvised_tools']) ? -2 : 0;

        $d20_bm  = $this->numberGenerationService->rollPathfinderDie(20);
        $d8a_bm  = $this->numberGenerationService->rollPathfinderDie(8);
        $d8b_bm  = $this->numberGenerationService->rollPathfinderDie(8);
        $total_bm = $d20_bm + $med_bonus_bm + $item_bonus_bm;
        $degree_bm = $this->combatCalculator->calculateDegreeOfSuccess($total_bm, $dc_bm, $d20_bm);

        $healed_bm = 0;
        $damage_bm = 0;
        $mutations_bm = [];

        if ($degree_bm === 'critical_success') {
          $healed_bm = (($d8a_bm + $d8b_bm) + $hp_bonus_bm[$rank_key_bm]) * 2;
        }
        elseif ($degree_bm === 'success') {
          $healed_bm = ($d8a_bm + $d8b_bm) + $hp_bonus_bm[$rank_key_bm];
        }
        elseif ($degree_bm === 'critical_failure') {
          $damage_bm = $this->numberGenerationService->rollPathfinderDie(8);
        }

        // Mark immunity (does not remove wounded; healer-specific).
        $game_state['battle_medicine_immune'][$bm_immune_key] = TRUE;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

        $result = [
          'degree'  => $degree_bm,
          'healed'  => $healed_bm,
          'damage'  => $damage_bm,
          'dc'      => $dc_bm,
          'd20'     => $d20_bm,
          'total'   => $total_bm,
          'removes_wounded' => FALSE,
          'mutations' => $mutations_bm,
        ];
        $events[] = GameEventLogger::buildEvent('battle_medicine', 'encounter', $actor_id, ['degree' => $degree_bm, 'healed' => $healed_bm, 'round' => $game_state['round'] ?? NULL], NULL, $effective_target_bm);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1591–1594, 2329: Recall Knowledge [1 action, Secret]
      // -----------------------------------------------------------------------
      case 'recall_knowledge': {
        // Use provided DC or compute via RecallKnowledgeService.
        if (!empty($params['dc'])) {
          $dc_rk = (int) $params['dc'];
        }
        else {
          $rk_service = new RecallKnowledgeService(new DcAdjustmentService());
          $dc_result_rk = $rk_service->computeDc(
            $params['subject_type'] ?? 'general',
            (int) ($params['level'] ?? 0),
            $params['rarity'] ?? 'common',
            (int) ($params['spell_rank'] ?? 0),
            $params['availability'] ?? 'trained'
          );
          $dc_rk = $dc_result_rk['dc'];
        }

        $skill_used_rk = $params['skill_used'] ?? 'arcana';
        $skill_bonus_rk = (int) ($params['skill_bonus'] ?? 0);
        $d20_rk = $this->numberGenerationService->rollPathfinderDie(20);
        $total_rk = $d20_rk + $skill_bonus_rk;
        $degree_rk = $this->combatCalculator->calculateDegreeOfSuccess($total_rk, $dc_rk, $d20_rk);

        // REQ 2329: Block re-attempts until new information is discovered.
        $attempt_key_rk = $actor_id . ':' . ($target_id ?? 'general');
        if (!empty($game_state['recall_knowledge_attempts'][$attempt_key_rk])) {
          return ['success' => FALSE, 'result' => ['error' => 'Cannot re-attempt Recall Knowledge on the same target without new information.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $game_state['recall_knowledge_attempts'][$attempt_key_rk] = TRUE;

        // Build player-facing output; crit fail presented as truthful (REQ 1594).
        switch ($degree_rk) {
          case 'critical_success':
            $player_msg_rk = 'You recall detailed information about the subject.';
            $info_rk = $params['known_info'] ?? NULL;
            $bonus_detail_rk = $params['bonus_detail'] ?? NULL;
            break;

          case 'success':
            $player_msg_rk = 'You recall accurate information about the subject.';
            $info_rk = $params['known_info'] ?? NULL;
            $bonus_detail_rk = NULL;
            break;

          case 'failure':
            $player_msg_rk = 'You fail to recall anything useful.';
            $info_rk = NULL;
            $bonus_detail_rk = NULL;
            break;

          case 'critical_failure':
          default:
            // REQ 1594: False info returned; player-facing message appears truthful.
            $player_msg_rk = 'You recall information about the subject.';
            $info_rk = $params['false_info'] ?? NULL;
            $bonus_detail_rk = NULL;
            break;
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = [
          'degree' => $degree_rk,
          'skill_used' => $skill_used_rk,
          'dc' => $dc_rk,
          'd20' => $d20_rk,
          'total' => $total_rk,
          'player_facing_message' => $player_msg_rk,
          'info' => $info_rk,
          'bonus_detail' => $bonus_detail_rk,
          // secret = true: raw d20 value is server-authoritative; not exposed to player.
          'secret' => TRUE,
        ];
        $events[] = GameEventLogger::buildEvent('recall_knowledge', 'encounter', $actor_id, ['skill_used' => $skill_used_rk, 'degree' => $degree_rk, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1715–1718: Hide [1 action]
      // Transitions actor from Observed → Hidden vs each observer's Perception DC.
      // Requires cover or concealment.
      // -----------------------------------------------------------------------
      case 'hide': {
        if (empty($params['has_cover']) && empty($params['has_concealment'])) {
          return ['success' => FALSE, 'result' => ['error' => 'Hide requires cover or concealment.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $stealth_bonus_h = (int) ($params['stealth_bonus'] ?? 0);
        // REQ dc-cr-gnome-heritage-chameleon: Chameleon Gnome +2 circumstance bonus to
        // Stealth when terrain color matches character coloration. PF2e rule: circumstance
        // bonuses don't stack; only the highest applies.
        $chameleon_bonus_h = 0;
        if (($params['heritage'] ?? '') === 'chameleon') {
          $terrain_color_h = $params['terrain_color_tag'] ?? '';
          $char_color_h    = $params['coloration_tag'] ?? '';
          if ($terrain_color_h !== '' && $char_color_h !== '' && $terrain_color_h === $char_color_h) {
            $existing_circumstance_h = (int) ($params['circumstance_bonus'] ?? 0);
            $chameleon_bonus_h = max(0, 2 - $existing_circumstance_h);
            $stealth_bonus_h += $chameleon_bonus_h;
          }
        }
        $observer_ids_h = $params['observer_ids'] ?? [];
        $perception_dcs_h = $params['perception_dcs'] ?? [];

        if (!isset($game_state['visibility'])) {
          $game_state['visibility'] = [];
        }

        $hide_results = [];
        foreach ($observer_ids_h as $obs_id) {
          $perc_dc_h = (int) ($perception_dcs_h[$obs_id] ?? 15);
          $d20_h = $this->numberGenerationService->rollPathfinderDie(20);
          $total_h = $d20_h + $stealth_bonus_h;
          $degree_h = $this->combatCalculator->calculateDegreeOfSuccess($total_h, $perc_dc_h, $d20_h);

          // REQ 1715: Success → Hidden; Failure → Observed (no change if already hidden/undetected).
          if (in_array($degree_h, ['critical_success', 'success'], TRUE)) {
            $game_state['visibility'][$obs_id][$actor_id] = 'hidden';
          }
          else {
            $game_state['visibility'][$obs_id][$actor_id] = 'observed';
          }
          // Secret: d20 not included in player-visible result.
          $hide_results[$obs_id] = ['degree' => $degree_h, 'visibility' => $game_state['visibility'][$obs_id][$actor_id]];
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['hide_results' => $hide_results, 'observer_count' => count($observer_ids_h), 'secret' => TRUE, 'chameleon_bonus_applied' => $chameleon_bonus_h > 0 ? $chameleon_bonus_h : NULL];
        $events[] = GameEventLogger::buildEvent('hide', 'encounter', $actor_id, ['observer_count' => count($observer_ids_h), 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1719–1722: Sneak [1 action, Move]
      // Actor must be Hidden; moves at half speed; Stealth vs Perception at end.
      // -----------------------------------------------------------------------
      case 'sneak': {
        // REQ 1719: Must already be Hidden to at least one observer.
        $is_hidden_to_any = FALSE;
        $observer_ids_sn = $params['observer_ids'] ?? [];
        foreach ($observer_ids_sn as $obs_id) {
          $vis = $game_state['visibility'][$obs_id][$actor_id] ?? 'observed';
          if (in_array($vis, ['hidden', 'undetected', 'unnoticed'], TRUE)) {
            $is_hidden_to_any = TRUE;
            break;
          }
        }
        if (!$is_hidden_to_any && !empty($observer_ids_sn)) {
          return ['success' => FALSE, 'result' => ['error' => 'Sneak requires Hidden (or Undetected) status. Use Hide first.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        // REQ 1720: Move at half speed (enforced by rounding client-provided distance).
        $speed_sn = (int) ($params['speed'] ?? 25);
        $half_speed_sn = (int) (floor($speed_sn / 2 / 5) * 5);

        // REQ 1722: Cannot end Sneak in an open location (no cover/concealment).
        if (empty($params['ends_in_cover']) && empty($params['ends_in_concealment'])) {
          // Sneak ending in open automatically becomes Observed to all observers.
          foreach ($observer_ids_sn as $obs_id) {
            $game_state['visibility'][$obs_id][$actor_id] = 'observed';
          }
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          $result = ['sneak_results' => [], 'became_observed' => TRUE, 'half_speed' => $half_speed_sn, 'reason' => 'Ended in open terrain.'];
          $events[] = GameEventLogger::buildEvent('sneak', 'encounter', $actor_id, ['became_observed' => TRUE, 'round' => $game_state['round'] ?? NULL]);
          break;
        }

        $stealth_bonus_sn = (int) ($params['stealth_bonus'] ?? 0);
        // REQ dc-cr-gnome-heritage-chameleon: Apply chameleon +2 circumstance bonus if terrain matches.
        $chameleon_bonus_sn = 0;
        if (($params['heritage'] ?? '') === 'chameleon') {
          $terrain_color_sn = $params['terrain_color_tag'] ?? '';
          $char_color_sn    = $params['coloration_tag'] ?? '';
          if ($terrain_color_sn !== '' && $char_color_sn !== '' && $terrain_color_sn === $char_color_sn) {
            $existing_circumstance_sn = (int) ($params['circumstance_bonus'] ?? 0);
            $chameleon_bonus_sn = max(0, 2 - $existing_circumstance_sn);
            $stealth_bonus_sn += $chameleon_bonus_sn;
          }
        }
        $perception_dcs_sn = $params['perception_dcs'] ?? [];

        $sneak_results = [];
        foreach ($observer_ids_sn as $obs_id) {
          $current_vis_sn = $game_state['visibility'][$obs_id][$actor_id] ?? 'observed';
          if (!in_array($current_vis_sn, ['hidden', 'undetected', 'unnoticed'], TRUE)) {
            // REQ 1721: Can only Sneak from a Hidden state vs an observer.
            $sneak_results[$obs_id] = ['degree' => NULL, 'visibility' => 'observed'];
            continue;
          }

          $perc_dc_sn = (int) ($perception_dcs_sn[$obs_id] ?? 15);
          $d20_sn = $this->numberGenerationService->rollPathfinderDie(20);
          $total_sn = $d20_sn + $stealth_bonus_sn;
          $degree_sn = $this->combatCalculator->calculateDegreeOfSuccess($total_sn, $perc_dc_sn, $d20_sn);

          // REQ 1720: Success → remain Hidden; Failure → Observed to this observer.
          if (in_array($degree_sn, ['critical_success', 'success'], TRUE)) {
            // Keep current visibility (hidden/undetected preserved).
          }
          else {
            $game_state['visibility'][$obs_id][$actor_id] = 'observed';
          }
          $sneak_results[$obs_id] = ['degree' => $degree_sn, 'visibility' => $game_state['visibility'][$obs_id][$actor_id]];
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['sneak_results' => $sneak_results, 'half_speed' => $half_speed_sn, 'secret' => TRUE, 'chameleon_bonus_applied' => $chameleon_bonus_sn > 0 ? $chameleon_bonus_sn : NULL];
        $events[] = GameEventLogger::buildEvent('sneak', 'encounter', $actor_id, ['observer_count' => count($observer_ids_sn), 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1721–1724: Conceal an Object [1 action, Manipulation]
      // Hides a carried/worn item; observers must Seek to discover it.
      // -----------------------------------------------------------------------
      case 'conceal_object': {
        $stealth_bonus_co = (int) ($params['stealth_bonus'] ?? 0);
        $observer_ids_co = $params['observer_ids'] ?? [];
        $perception_dcs_co = $params['perception_dcs'] ?? [];
        $item_id_co = $params['item_id'] ?? NULL;

        if (!isset($game_state['concealed_objects'])) {
          $game_state['concealed_objects'] = [];
        }

        $co_results = [];
        $concealed_to_all = TRUE;
        foreach ($observer_ids_co as $obs_id) {
          $perc_dc_co = (int) ($perception_dcs_co[$obs_id] ?? 15);
          $d20_co = $this->numberGenerationService->rollPathfinderDie(20);
          $total_co = $d20_co + $stealth_bonus_co;
          $degree_co = $this->combatCalculator->calculateDegreeOfSuccess($total_co, $perc_dc_co, $d20_co);

          if (in_array($degree_co, ['critical_success', 'success'], TRUE)) {
            $co_results[$obs_id] = ['degree' => $degree_co, 'concealed' => TRUE];
          }
          else {
            $co_results[$obs_id] = ['degree' => $degree_co, 'concealed' => FALSE];
            $concealed_to_all = FALSE;
          }
        }

        if ($item_id_co && $concealed_to_all && !empty($observer_ids_co)) {
          $game_state['concealed_objects'][$actor_id . ':' . $item_id_co] = TRUE;
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['concealed_results' => $co_results, 'item_id' => $item_id_co, 'secret' => TRUE];
        $events[] = GameEventLogger::buildEvent('conceal_object', 'encounter', $actor_id, ['item_id' => $item_id_co, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1747–1750: Palm an Object [1 action, Manipulation]
      // Hides a small item on the character's person; observers must Seek.
      // -----------------------------------------------------------------------
      case 'palm_object': {
        $stealth_bonus_po = (int) ($params['thievery_bonus'] ?? 0);
        $observer_ids_po = $params['observer_ids'] ?? [];
        $perception_dcs_po = $params['perception_dcs'] ?? [];
        $item_id_po = $params['item_id'] ?? NULL;

        if (!isset($game_state['palmed_objects'])) {
          $game_state['palmed_objects'] = [];
        }

        $po_results = [];
        $palmed_from_all = TRUE;
        foreach ($observer_ids_po as $obs_id) {
          $perc_dc_po = (int) ($perception_dcs_po[$obs_id] ?? 15);
          $d20_po = $this->numberGenerationService->rollPathfinderDie(20);
          $total_po = $d20_po + $stealth_bonus_po;
          $degree_po = $this->combatCalculator->calculateDegreeOfSuccess($total_po, $perc_dc_po, $d20_po);

          if (in_array($degree_po, ['critical_success', 'success'], TRUE)) {
            $po_results[$obs_id] = ['degree' => $degree_po, 'hidden' => TRUE];
          }
          else {
            $po_results[$obs_id] = ['degree' => $degree_po, 'hidden' => FALSE];
            $palmed_from_all = FALSE;
          }
        }

        // REQ 1747: On success vs all observers, item considered hidden until Seek reveals it.
        if ($item_id_po && $palmed_from_all && !empty($observer_ids_po)) {
          $game_state['palmed_objects'][$actor_id . ':' . $item_id_po] = TRUE;
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['palm_results' => $po_results, 'item_id' => $item_id_po, 'secret' => TRUE];
        $events[] = GameEventLogger::buildEvent('palm_object', 'encounter', $actor_id, ['item_id' => $item_id_po, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1751–1752: Steal [1 action, Manipulation]
      // Takes a small item from a target that is unaware of the attempt.
      // Crit Failure: target and nearby observers become aware of the attempt.
      // -----------------------------------------------------------------------
      case 'steal': {
        $thievery_bonus_st = (int) ($params['thievery_bonus'] ?? 0);
        $target_id_st = $params['target_id'] ?? NULL;
        $observer_ids_st = $params['observer_ids'] ?? [];
        $perception_dc_st = (int) ($params['perception_dc'] ?? 15);
        $item_id_st = $params['item_id'] ?? NULL;

        $d20_st = $this->numberGenerationService->rollPathfinderDie(20);
        $total_st = $d20_st + $thievery_bonus_st;
        $degree_st = $this->combatCalculator->calculateDegreeOfSuccess($total_st, $perception_dc_st, $d20_st);

        $stolen = FALSE;
        $observers_alerted = [];
        if (in_array($degree_st, ['critical_success', 'success'], TRUE)) {
          $stolen = TRUE;
          if ($item_id_st) {
            if (!isset($game_state['stolen_items'])) {
              $game_state['stolen_items'] = [];
            }
            $game_state['stolen_items'][] = ['actor' => $actor_id, 'from' => $target_id_st, 'item_id' => $item_id_st];
          }
        }
        elseif ($degree_st === 'critical_failure') {
          // REQ 1752: Crit Failure — target and nearby observers become aware.
          $observers_alerted = array_merge([$target_id_st], $observer_ids_st);
          $observers_alerted = array_filter($observers_alerted);
          if (!isset($game_state['steal_awareness'])) {
            $game_state['steal_awareness'] = [];
          }
          foreach ($observers_alerted as $aware_id) {
            $game_state['steal_awareness'][$aware_id][$actor_id] = TRUE;
          }
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['degree' => $degree_st, 'stolen' => $stolen, 'observers_alerted' => array_values($observers_alerted), 'secret' => TRUE];
        $events[] = GameEventLogger::buildEvent('steal', 'encounter', $actor_id, ['target_id' => $target_id_st, 'degree' => $degree_st, 'round' => $game_state['round'] ?? NULL], NULL, $target_id_st);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1748–1750: Disable a Device [2 actions, Manipulation, Trained]
      // Disarms traps/alarms. Complex devices may need multiple successes.
      // Crit Failure: triggers the device.
      // -----------------------------------------------------------------------
      case 'disable_device': {
        // REQ 1748: Trained Thievery required.
        $thievery_rank_dd = (int) ($params['thievery_proficiency_rank'] ?? 0);
        if ($thievery_rank_dd < 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Disable a Device requires Trained Thievery.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $device_id_dd = $params['device_id'] ?? NULL;
        $dc_dd = (int) ($params['dc'] ?? 20);

        // Improvised tools penalty.
        $has_tools_dd = !empty($params['has_thieves_tools']);
        if (!$has_tools_dd) {
          $dc_dd += 5;
        }

        $thievery_bonus_dd = (int) ($params['thievery_bonus'] ?? 0);
        $d20_dd = $this->numberGenerationService->rollPathfinderDie(20);
        $total_dd = $d20_dd + $thievery_bonus_dd;
        $degree_dd = $this->combatCalculator->calculateDegreeOfSuccess($total_dd, $dc_dd, $d20_dd);

        $disabled = FALSE;
        $triggered = FALSE;

        if (!isset($game_state['device_states'])) {
          $game_state['device_states'] = [];
        }

        if ($degree_dd === 'critical_failure') {
          // REQ 1750: Crit Failure triggers the device.
          $triggered = TRUE;
          if ($device_id_dd) {
            $game_state['device_states'][$device_id_dd]['triggered'] = TRUE;
          }
        }
        elseif (in_array($degree_dd, ['critical_success', 'success'], TRUE)) {
          if ($device_id_dd) {
            $successes_needed = (int) ($params['successes_needed'] ?? 1);
            $successes_so_far = (int) ($game_state['device_states'][$device_id_dd]['successes'] ?? 0);
            $successes_so_far++;
            $game_state['device_states'][$device_id_dd]['successes'] = $successes_so_far;
            if ($successes_so_far >= $successes_needed) {
              $disabled = TRUE;
              $game_state['device_states'][$device_id_dd]['disabled'] = TRUE;
            }
          }
          else {
            $disabled = TRUE;
          }
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = ['degree' => $degree_dd, 'disabled' => $disabled, 'triggered' => $triggered, 'used_tools' => $has_tools_dd, 'secret' => TRUE];
        $events[] = GameEventLogger::buildEvent('disable_device', 'encounter', $actor_id, ['device_id' => $device_id_dd, 'degree' => $degree_dd, 'triggered' => $triggered, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1753–1756: Pick a Lock [2 actions, Manipulation, Trained]
      // DC by lock quality: simple 15, average 20, good 25, superior 30.
      // No thieves' tools: DC +5.
      // Crit Failure: lock jammed; no further attempts until repaired.
      // -----------------------------------------------------------------------
      case 'pick_lock': {
        // REQ 1753: Trained Thievery required.
        $thievery_rank_pl = (int) ($params['thievery_proficiency_rank'] ?? 0);
        if ($thievery_rank_pl < 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Pick a Lock requires Trained Thievery.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $lock_id_pl = $params['lock_id'] ?? NULL;

        // REQ 1756: Jammed lock blocks further attempts.
        if ($lock_id_pl && !empty($game_state['lock_states'][$lock_id_pl]['jammed'])) {
          return ['success' => FALSE, 'result' => ['error' => 'This lock is jammed and cannot be picked until repaired.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        // REQ 1754: DC by lock quality.
        $lock_quality_dcs = ['simple' => 15, 'average' => 20, 'good' => 25, 'superior' => 30];
        $lock_quality_pl = $params['lock_quality'] ?? 'average';
        $dc_pl = $lock_quality_dcs[$lock_quality_pl] ?? 20;

        // REQ 1755: Without thieves' tools, DC +5 (improvised).
        $has_tools_pl = !empty($params['has_thieves_tools']);
        if (!$has_tools_pl) {
          $dc_pl += 5;
        }

        $thievery_bonus_pl = (int) ($params['thievery_bonus'] ?? 0);
        $d20_pl = $this->numberGenerationService->rollPathfinderDie(20);
        $total_pl = $d20_pl + $thievery_bonus_pl;
        $degree_pl = $this->combatCalculator->calculateDegreeOfSuccess($total_pl, $dc_pl, $d20_pl);

        $unlocked = FALSE;
        $jammed = FALSE;

        if (!isset($game_state['lock_states'])) {
          $game_state['lock_states'] = [];
        }

        if ($degree_pl === 'critical_failure') {
          // REQ 1756: Crit Failure jams the lock.
          $jammed = TRUE;
          if ($lock_id_pl) {
            $game_state['lock_states'][$lock_id_pl]['jammed'] = TRUE;
          }
        }
        elseif (in_array($degree_pl, ['critical_success', 'success'], TRUE)) {
          $unlocked = TRUE;
          if ($lock_id_pl) {
            $game_state['lock_states'][$lock_id_pl]['locked'] = FALSE;
          }
        }

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = ['degree' => $degree_pl, 'unlocked' => $unlocked, 'jammed' => $jammed, 'lock_quality' => $lock_quality_pl, 'used_tools' => $has_tools_pl, 'secret' => TRUE];
        $events[] = GameEventLogger::buildEvent('pick_lock', 'encounter', $actor_id, ['lock_id' => $lock_id_pl, 'lock_quality' => $lock_quality_pl, 'degree' => $degree_pl, 'jammed' => $jammed, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2373–2396: Hazard actions [encounter-phase].
      // -----------------------------------------------------------------------
      case 'disable_hazard': {
        $hazard_id_dh = $params['hazard_id'] ?? NULL;
        if (!$hazard_id_dh) {
          return ['success' => FALSE, 'result' => ['error' => 'hazard_id required.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $hazard_ref_dh = &$this->hazardService->findHazardByInstanceId($hazard_id_dh, $dungeon_data);
        if ($hazard_ref_dh === NULL) {
          return ['success' => FALSE, 'result' => ['error' => 'Hazard not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $skill_rank_dh = (int) ($params['skill_proficiency_rank'] ?? 0);
        $skill_bonus_dh = (int) ($params['skill_bonus'] ?? 0);
        // REQ 2384: disableHazard(entity, skill_bonus, skill_rank) — bonus before rank.
        $disable_result_dh = $this->hazardService->disableHazard($hazard_ref_dh, $skill_bonus_dh, $skill_rank_dh);
        $xp_dh = 0;
        if (!empty($disable_result_dh['disabled'])) {
          $xp_dh = $this->hazardService->awardHazardXp($game_state, $hazard_ref_dh, (int) ($game_state['party_level'] ?? 1));
        }
        $complexity_dh = $hazard_ref_dh['complexity'] ?? 'simple';
        $phase_transition_dh = NULL;
        if (!empty($disable_result_dh['triggered']) && $complexity_dh === 'complex') {
          $initiative_dh = $this->hazardService->rollComplexHazardInitiative($hazard_ref_dh);
          $phase_transition_dh = ['type' => 'encounter_continue', 'hazard_initiative' => $initiative_dh, 'hazard_id' => $hazard_id_dh];
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = array_merge($disable_result_dh, ['xp_awarded' => $xp_dh, 'hazard_id' => $hazard_id_dh]);
        $events[] = GameEventLogger::buildEvent('disable_hazard', 'encounter', $actor_id, ['hazard_id' => $hazard_id_dh, 'degree' => $disable_result_dh['degree'], 'disabled' => $disable_result_dh['disabled'], 'triggered' => $disable_result_dh['triggered'] ?? FALSE, 'round' => $game_state['round'] ?? NULL]);
        $phase_transition = $phase_transition_dh;
        break;
      }

      case 'attack_hazard': {
        $hazard_id_ah = $params['hazard_id'] ?? NULL;
        if (!$hazard_id_ah) {
          return ['success' => FALSE, 'result' => ['error' => 'hazard_id required.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $hazard_ref_ah = &$this->hazardService->findHazardByInstanceId($hazard_id_ah, $dungeon_data);
        if ($hazard_ref_ah === NULL) {
          return ['success' => FALSE, 'result' => ['error' => 'Hazard not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $damage_amount_ah = (int) ($params['damage'] ?? 0);
        $damage_result_ah = $this->hazardService->applyDamageToHazard($hazard_ref_ah, $damage_amount_ah);
        $xp_ah = 0;
        if (!empty($damage_result_ah['disabled'])) {
          $xp_ah = $this->hazardService->awardHazardXp($game_state, $hazard_ref_ah, (int) ($game_state['party_level'] ?? 1));
        }
        $complexity_ah = $hazard_ref_ah['complexity'] ?? 'simple';
        $phase_transition_ah = NULL;
        if (!empty($damage_result_ah['triggered']) && $complexity_ah === 'complex') {
          $initiative_ah = $this->hazardService->rollComplexHazardInitiative($hazard_ref_ah);
          $phase_transition_ah = ['type' => 'encounter_continue', 'hazard_initiative' => $initiative_ah, 'hazard_id' => $hazard_id_ah];
        }
        $action_cost_ah = (int) ($params['action_cost'] ?? 1);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost_ah);
        $result = array_merge($damage_result_ah, ['xp_awarded' => $xp_ah, 'hazard_id' => $hazard_id_ah]);
        $events[] = GameEventLogger::buildEvent('attack_hazard', 'encounter', $actor_id, ['hazard_id' => $hazard_id_ah, 'damage' => $damage_amount_ah, 'triggered' => $damage_result_ah['triggered'] ?? FALSE, 'disabled' => $damage_result_ah['disabled'] ?? FALSE, 'round' => $game_state['round'] ?? NULL]);
        $phase_transition = $phase_transition_ah;
        break;
      }

      case 'counteract_hazard': {
        $hazard_id_ch = $params['hazard_id'] ?? NULL;
        if (!$hazard_id_ch) {
          return ['success' => FALSE, 'result' => ['error' => 'hazard_id required.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $hazard_ref_ch = &$this->hazardService->findHazardByInstanceId($hazard_id_ch, $dungeon_data);
        if ($hazard_ref_ch === NULL) {
          return ['success' => FALSE, 'result' => ['error' => 'Hazard not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $counteract_level_ch = (int) ($params['counteract_level'] ?? 0);
        $counteract_bonus_ch = (int) ($params['counteract_bonus'] ?? 0);
        // REQ 2393: Roll d20 + bonus for total; pass natural roll for degree calculation.
        $d20_ch = $this->numberGenerationService->rollPathfinderDie(20);
        $total_ch = $d20_ch + $counteract_bonus_ch;
        $counteract_result_ch = $this->hazardService->counteractMagicalHazard($hazard_ref_ch, $counteract_level_ch, $total_ch, $d20_ch);
        $xp_ch = 0;
        if (!empty($counteract_result_ch['counteracted'])) {
          $xp_ch = $this->hazardService->awardHazardXp($game_state, $hazard_ref_ch, (int) ($game_state['party_level'] ?? 1));
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = array_merge($counteract_result_ch, ['xp_awarded' => $xp_ch, 'hazard_id' => $hazard_id_ch]);
        $events[] = GameEventLogger::buildEvent('counteract_hazard', 'encounter', $actor_id, ['hazard_id' => $hazard_id_ch, 'degree' => $counteract_result_ch['degree'], 'counteracted' => $counteract_result_ch['counteracted'] ?? FALSE, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2410–2425: Activate a magic item (encounter phase).
      // -----------------------------------------------------------------------
      case 'activate_item': {
        $item_id_ai   = $params['item_instance_id'] ?? NULL;
        $item_data_ai = $params['item_data'] ?? [];
        $component_ai = $params['component'] ?? 'command';
        if (!$item_id_ai) {
          return ['success' => FALSE, 'result' => ['error' => 'activate_item requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state_ai = $params['char_state'] ?? [];
        $activate_result_ai = $this->magicItemService->activateItem($actor_id, $item_id_ai, $item_data_ai, $char_state_ai, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($activate_result_ai['actions_cost'] ?? 1));
        $result = $activate_result_ai;
        $events[] = GameEventLogger::buildEvent('activate_item', 'encounter', $actor_id, ['item_instance_id' => $item_id_ai, 'success' => $activate_result_ai['success'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2416–2420: Sustain an activation (encounter phase).
      // -----------------------------------------------------------------------
      case 'sustain_activation': {
        $item_id_sa = $params['item_instance_id'] ?? NULL;
        if (!$item_id_sa) {
          return ['success' => FALSE, 'result' => ['error' => 'sustain_activation requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $sustain_result_sa = $this->magicItemService->sustainActivation($actor_id, $item_id_sa, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = $sustain_result_sa;
        $events[] = GameEventLogger::buildEvent('sustain_activation', 'encounter', $actor_id, ['item_instance_id' => $item_id_sa, 'success' => $sustain_result_sa['success'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2421–2424: Dismiss an activation (encounter phase).
      // -----------------------------------------------------------------------
      case 'dismiss_activation': {
        $item_id_da = $params['item_instance_id'] ?? NULL;
        if (!$item_id_da) {
          return ['success' => FALSE, 'result' => ['error' => 'dismiss_activation requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $dismiss_result_da = $this->magicItemService->dismissActivation($actor_id, $item_id_da, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = $dismiss_result_da;
        $events[] = GameEventLogger::buildEvent('dismiss_activation', 'encounter', $actor_id, ['item_instance_id' => $item_id_da, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // dc-cr-spells-ch07: Sustain a spell (1 action, Concentrate trait).
      // Rule: lasts until end of next turn; sustaining > 100 rounds → fatigue + ends.
      // -----------------------------------------------------------------------
      case 'sustain_spell': {
        $spell_id_ss = $params['spell_id'] ?? NULL;
        if (!$spell_id_ss) {
          return ['success' => FALSE, 'result' => ['error' => 'sustain_spell requires params.spell_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $current_round_ss = (int) ($game_state['round'] ?? 1);
        $sustained_ss = $game_state['spells']['sustained'][$actor_id][$spell_id_ss] ?? NULL;
        if ($sustained_ss === NULL) {
          return ['success' => FALSE, 'result' => ['error' => "Spell '{$spell_id_ss}' is not currently sustained by this caster."], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $rounds_sustained = $current_round_ss - (int) ($sustained_ss['start_round'] ?? $current_round_ss);
        if ($rounds_sustained >= MagicItemService::SUSTAIN_FATIGUE_ROUNDS) {
          // Fatigue applied; spell ends immediately.
          unset($game_state['spells']['sustained'][$actor_id][$spell_id_ss]);
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          $result = ['sustained' => FALSE, 'ended' => TRUE, 'reason' => 'exceeded_100_rounds', 'fatigue_applied' => TRUE];
          $events[] = GameEventLogger::buildEvent('sustain_spell', 'encounter', $actor_id, ['spell_id' => $spell_id_ss, 'ended' => TRUE, 'reason' => 'exceeded_100_rounds', 'round' => $current_round_ss]);
        }
        else {
          $game_state['spells']['sustained'][$actor_id][$spell_id_ss]['last_sustained_round'] = $current_round_ss;
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          $result = ['sustained' => TRUE, 'rounds_sustained' => $rounds_sustained + 1];
          $events[] = GameEventLogger::buildEvent('sustain_spell', 'encounter', $actor_id, ['spell_id' => $spell_id_ss, 'rounds_sustained' => $rounds_sustained + 1, 'round' => $current_round_ss]);
        }
        break;
      }

      // -----------------------------------------------------------------------
      // dc-cr-spells-ch07: Dismiss a sustained/dismissible spell (1 action, Concentrate).
      // -----------------------------------------------------------------------
      case 'dismiss_spell': {
        $spell_id_ds = $params['spell_id'] ?? NULL;
        if (!$spell_id_ds) {
          return ['success' => FALSE, 'result' => ['error' => 'dismiss_spell requires params.spell_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        unset($game_state['spells']['sustained'][$actor_id][$spell_id_ds]);
        // Also clear round-duration tracking for dismissed spells.
        unset($game_state['spells']['durations'][$actor_id][$spell_id_ds]);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['dismissed' => TRUE, 'spell_id' => $spell_id_ds];
        $events[] = GameEventLogger::buildEvent('dismiss_spell', 'encounter', $actor_id, ['spell_id' => $spell_id_ds, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2478–2490: Cast from scroll (encounter phase).
      // -----------------------------------------------------------------------
      case 'cast_from_scroll': {
        $scroll_id_enc   = $params['scroll_instance_id'] ?? NULL;
        $scroll_data_enc = $params['scroll_data'] ?? [];
        if (!$scroll_id_enc) {
          return ['success' => FALSE, 'result' => ['error' => 'cast_from_scroll requires params.scroll_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state_enc = $params['char_state'] ?? [];
        $scroll_result_enc = $this->magicItemService->castFromScroll($scroll_data_enc, $char_state_enc, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($scroll_result_enc['actions_cost'] ?? 2));
        $result = $scroll_result_enc;
        $events[] = GameEventLogger::buildEvent('cast_from_scroll', 'encounter', $actor_id, ['scroll_instance_id' => $scroll_id_enc, 'success' => $scroll_result_enc['success'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2511–2520: Cast from staff (encounter phase).
      // -----------------------------------------------------------------------
      case 'cast_from_staff': {
        $staff_id_enc   = $params['staff_instance_id'] ?? NULL;
        $staff_data_enc = $params['staff_data'] ?? [];
        $spell_level_enc = (int) ($params['spell_level'] ?? 1);
        if (!$staff_id_enc) {
          return ['success' => FALSE, 'result' => ['error' => 'cast_from_staff requires params.staff_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $spell_id_enc   = $params['spell_id'] ?? '';
        $char_state_enc = $params['char_state'] ?? [];
        $staff_result_enc = $this->magicItemService->castFromStaff($staff_id_enc, $actor_id, $staff_data_enc, $spell_id_enc, $spell_level_enc, $char_state_enc, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($staff_result_enc['actions_cost'] ?? 2));
        $result = $staff_result_enc;
        $events[] = GameEventLogger::buildEvent('cast_from_staff', 'encounter', $actor_id, ['staff_instance_id' => $staff_id_enc, 'spell_level' => $spell_level_enc, 'success' => $staff_result_enc['success'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2521–2530: Cast from wand (encounter phase).
      // -----------------------------------------------------------------------
      case 'cast_from_wand': {
        $wand_id_enc   = $params['wand_instance_id'] ?? NULL;
        $wand_data_enc = $params['wand_data'] ?? [];
        if (!$wand_id_enc) {
          return ['success' => FALSE, 'result' => ['error' => 'cast_from_wand requires params.wand_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state_wand = $params['char_state'] ?? [];
        $wand_result_enc = $this->magicItemService->castFromWand($wand_id_enc, $wand_data_enc, $char_state_wand, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($wand_result_enc['actions_cost'] ?? 2));
        $result = $wand_result_enc;
        $events[] = GameEventLogger::buildEvent('cast_from_wand', 'encounter', $actor_id, ['wand_instance_id' => $wand_id_enc, 'success' => $wand_result_enc['success'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2531–2535: Overcharge wand (encounter phase).
      // -----------------------------------------------------------------------
      case 'overcharge_wand': {
        $wand_id_ow   = $params['wand_instance_id'] ?? NULL;
        $wand_data_ow = $params['wand_data'] ?? [];
        if (!$wand_id_ow) {
          return ['success' => FALSE, 'result' => ['error' => 'overcharge_wand requires params.wand_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $overcharge_result_ow = $this->magicItemService->overchargeWand($wand_id_ow, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($overcharge_result_ow['actions_cost'] ?? 2));
        $result = $overcharge_result_ow;
        $events[] = GameEventLogger::buildEvent('overcharge_wand', 'encounter', $actor_id, ['wand_instance_id' => $wand_id_ow, 'success' => $overcharge_result_ow['success'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2549: Activate talisman (encounter phase).
      // -----------------------------------------------------------------------
      case 'activate_talisman': {
        $talisman_id_enc = $params['talisman_instance_id'] ?? NULL;
        $host_item_id_enc = $params['host_item_instance_id'] ?? $talisman_id_enc;
        if (!$talisman_id_enc) {
          return ['success' => FALSE, 'result' => ['error' => 'activate_talisman requires params.talisman_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $talisman_result_enc = $this->magicItemService->activateTalisman($host_item_id_enc, $actor_id, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - ($talisman_result_enc['actions_cost'] ?? 1));
        $result = $talisman_result_enc;
        $events[] = GameEventLogger::buildEvent('activate_talisman', 'encounter', $actor_id, ['talisman_instance_id' => $talisman_id_enc, 'success' => $talisman_result_enc['success'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2221: Burrow — move using burrow speed; tags entity as underground.
      // -----------------------------------------------------------------------
      case 'burrow': {
        $enc_b = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_b = $enc_b ? $this->findEncounterParticipantByEntityId($enc_b, $actor_id) : NULL;
        if (!$ptcp_b) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_data_b = !empty($ptcp_b['entity_ref']) ? json_decode($ptcp_b['entity_ref'], TRUE) : [];
        $burrow_speed = (int) ($entity_data_b['burrow_speed'] ?? 0);
        if ($burrow_speed <= 0) {
          return ['success' => FALSE, 'result' => ['error' => 'No burrow Speed.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $params['movement_type'] = 'burrow';
        $burrow_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $burrow_result['mutations'] ?? [];
        // Tag underground unless ability specifies tunnel creation.
        $entity_data_b['underground'] = TRUE;
        if (!empty($entity_data_b['creates_tunnel'])) {
          $entity_data_b['tunnel_hex'] = $params['to_hex'] ?? NULL;
        }
        $this->encounterStore->updateParticipant((int) $ptcp_b['id'], ['entity_ref' => json_encode($entity_data_b)]);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['burrowed' => TRUE, 'to_hex' => $params['to_hex'] ?? NULL];
        $events[] = GameEventLogger::buildEvent('burrow', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2222-2223: Fly — move using fly speed; tags airborne; hover at 0.
      // -----------------------------------------------------------------------
      case 'fly': {
        $enc_f = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_f = $enc_f ? $this->findEncounterParticipantByEntityId($enc_f, $actor_id) : NULL;
        if (!$ptcp_f) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_data_f = !empty($ptcp_f['entity_ref']) ? json_decode($ptcp_f['entity_ref'], TRUE) : [];
        $fly_speed = (int) ($entity_data_f['fly_speed'] ?? 0);
        if ($fly_speed <= 0) {
          return ['success' => FALSE, 'result' => ['error' => 'No fly Speed.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $fly_distance = (int) ($params['distance'] ?? 0);
        // REQ 2223: Fly 0 = hover (stay airborne, costs 1 action).
        if ($fly_distance === 0) {
          $entity_data_f['airborne'] = TRUE;
          $entity_data_f['fly_used_this_turn'] = TRUE;
          $this->encounterStore->updateParticipant((int) $ptcp_f['id'], ['entity_ref' => json_encode($entity_data_f)]);
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          $result = ['hovered' => TRUE];
          $events[] = GameEventLogger::buildEvent('fly', 'encounter', $actor_id, ['hover' => TRUE, 'round' => $game_state['round'] ?? NULL]);
          break;
        }
        // REQ 2222: Upward movement costs 2× (difficult terrain rule).
        if (!empty($params['upward'])) {
          $params['movement_type'] = 'fly';
          // Upward: double the hex cost — pass movement_cost_multiplier for MovementResolverService.
          $params['upward_movement'] = TRUE;
        }
        $params['movement_type'] = 'fly';
        $fly_result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $fly_result['mutations'] ?? [];
        $entity_data_f['airborne'] = TRUE;
        $entity_data_f['fly_used_this_turn'] = TRUE;
        $this->encounterStore->updateParticipant((int) $ptcp_f['id'], ['entity_ref' => json_encode($entity_data_f)]);
        $game_state['turn']['fly_used'] = TRUE;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['flew' => TRUE, 'to_hex' => $params['to_hex'] ?? NULL];
        $events[] = GameEventLogger::buildEvent('fly', 'encounter', $actor_id, ['to' => $params['to_hex'] ?? NULL, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2225: Mount — ride adjacent willing larger creature. Dismount = 1 action.
      // -----------------------------------------------------------------------
      case 'mount': {
        $enc_m = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_m = $enc_m ? $this->findEncounterParticipantByEntityId($enc_m, $actor_id) : NULL;
        $mount_ptcp = $enc_m && $target_id ? $this->findEncounterParticipantByEntityId($enc_m, $target_id) : NULL;
        if (!$ptcp_m || !$mount_ptcp) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant or mount not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // Must be adjacent (1-hex distance).
        $dist_m = $this->movementResolver ? $this->movementResolver->hexDistance(
          ['q' => (int) ($ptcp_m['position_q'] ?? 0), 'r' => (int) ($ptcp_m['position_r'] ?? 0)],
          ['q' => (int) ($mount_ptcp['position_q'] ?? 0), 'r' => (int) ($mount_ptcp['position_r'] ?? 0)]
        ) : 1;
        if ($dist_m > 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Mount must be adjacent.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // GAP-2225: Acrobatics DC 15 check required when mounting in encounter mode.
        $acrobatics_bonus_m = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
        $mount_roll = $this->numberGeneration->rollPathfinderDie(20);
        $mount_total = $mount_roll + $acrobatics_bonus_m;
        if ($mount_total < 15) {
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          return ['success' => FALSE, 'result' => ['error' => 'Acrobatics check failed (DC 15).', 'roll' => $mount_roll, 'bonus' => $acrobatics_bonus_m, 'total' => $mount_total], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $actor_entity_m = !empty($ptcp_m['entity_ref']) ? json_decode($ptcp_m['entity_ref'], TRUE) : [];
        $actor_entity_m['mounted_on'] = $target_id;
        $this->encounterStore->updateParticipant((int) $ptcp_m['id'], ['entity_ref' => json_encode($actor_entity_m)]);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['mounted' => TRUE, 'mount_id' => $target_id, 'roll' => $mount_roll, 'total' => $mount_total];
        $events[] = GameEventLogger::buildEvent('mount', 'encounter', $actor_id, ['mount' => $target_id, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      case 'dismount': {
        $enc_dm = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_dm = $enc_dm ? $this->findEncounterParticipantByEntityId($enc_dm, $actor_id) : NULL;
        if (!$ptcp_dm) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $actor_entity_dm = !empty($ptcp_dm['entity_ref']) ? json_decode($ptcp_dm['entity_ref'], TRUE) : [];
        $actor_entity_dm['mounted_on'] = NULL;
        // Move actor to adjacent hex if provided.
        $dismount_to = $params['to_hex'] ?? NULL;
        $update_dm = ['entity_ref' => json_encode($actor_entity_dm)];
        if ($dismount_to) {
          $update_dm['position_q'] = (int) ($dismount_to['q'] ?? $ptcp_dm['position_q'] ?? 0);
          $update_dm['position_r'] = (int) ($dismount_to['r'] ?? $ptcp_dm['position_r'] ?? 0);
        }
        $this->encounterStore->updateParticipant((int) $ptcp_dm['id'], $update_dm);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['dismounted' => TRUE];
        $events[] = GameEventLogger::buildEvent('dismount', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2227: Raise a Shield — 1 action; shield AC bonus active until start of next turn.
      // -----------------------------------------------------------------------
      case 'raise_shield': {
        $enc_rs = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_rs = $enc_rs ? $this->findEncounterParticipantByEntityId($enc_rs, $actor_id) : NULL;
        if (!$ptcp_rs) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_data_rs = !empty($ptcp_rs['entity_ref']) ? json_decode($ptcp_rs['entity_ref'], TRUE) : [];
        // Verify entity has a shield in held items.
        $shield_rs = $this->findHeldShield($entity_data_rs);
        if (!$shield_rs) {
          return ['success' => FALSE, 'result' => ['error' => 'No shield in hand.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        if (!empty($shield_rs['broken'])) {
          return ['success' => FALSE, 'result' => ['error' => 'Shield is broken.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_data_rs['shield_raised'] = TRUE;
        $entity_data_rs['shield_raised_ac_bonus'] = (int) ($shield_rs['ac_bonus'] ?? 0);
        $this->encounterStore->updateParticipant((int) $ptcp_rs['id'], ['entity_ref' => json_encode($entity_data_rs)]);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['shield_raised' => TRUE, 'ac_bonus' => $entity_data_rs['shield_raised_ac_bonus']];
        $events[] = GameEventLogger::buildEvent('raise_shield', 'encounter', $actor_id, ['ac_bonus' => $entity_data_rs['shield_raised_ac_bonus'], 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2220: Avert Gaze — 1 action; +2 circumstance vs gaze effects this turn.
      // -----------------------------------------------------------------------
      case 'avert_gaze': {
        $enc_ag = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_ag = $enc_ag ? $this->findEncounterParticipantByEntityId($enc_ag, $actor_id) : NULL;
        if (!$ptcp_ag) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_data_ag = !empty($ptcp_ag['entity_ref']) ? json_decode($ptcp_ag['entity_ref'], TRUE) : [];
        $entity_data_ag['avert_gaze_active'] = TRUE;
        $this->encounterStore->updateParticipant((int) $ptcp_ag['id'], ['entity_ref' => json_encode($entity_data_ag)]);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['avert_gaze' => TRUE];
        $events[] = GameEventLogger::buildEvent('avert_gaze', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2226: Point Out — 1 action; reveal undetected target's location to allies.
      // -----------------------------------------------------------------------
      case 'point_out': {
        if (!$target_id) {
          return ['success' => FALSE, 'result' => ['error' => 'target required for point_out.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $enc_po = $this->encounterStore->loadEncounter($encounter_id);
        if ($enc_po) {
          foreach ($enc_po['participants'] ?? [] as $ally_ptcp) {
            $ally_eid = $ally_ptcp['entity_id'] ?? '';
            if ($ally_eid === $actor_id) {
              continue;
            }
            // For each ally: upgrade target detection state from undetected → hidden.
            $ally_entity_data = !empty($ally_ptcp['entity_ref']) ? json_decode($ally_ptcp['entity_ref'], TRUE) : [];
            $ally_attacker_id = $ally_entity_data['entity_id'] ?? $ally_eid;
            // Load the target's detection states.
            $target_ptcp = $this->findEncounterParticipantByEntityId($enc_po, $target_id);
            if ($target_ptcp) {
              $target_entity_data = !empty($target_ptcp['entity_ref']) ? json_decode($target_ptcp['entity_ref'], TRUE) : [];
              $current_state = $target_entity_data['detection_states'][$ally_attacker_id] ?? 'observed';
              if ($current_state === 'undetected' || $current_state === 'unnoticed') {
                $target_entity_data['detection_states'][$ally_attacker_id] = 'hidden';
                $this->encounterStore->updateParticipant((int) $target_ptcp['id'], ['entity_ref' => json_encode($target_entity_data)]);
              }
            }
          }
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['pointed_out' => TRUE, 'target' => $target_id];
        $events[] = GameEventLogger::buildEvent('point_out', 'encounter', $actor_id, ['target' => $target_id, 'round' => $game_state['round'] ?? NULL], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2219: Arrest a Fall (reaction) — requires fly speed; Acrobatics DC 15.
      // -----------------------------------------------------------------------
      case 'arrest_fall': {
        if (empty($game_state['turn']['reaction_available'] ?? TRUE) === FALSE && ($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
          return ['success' => FALSE, 'result' => ['error' => 'Reaction already spent.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $enc_af = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_af = $enc_af ? $this->findEncounterParticipantByEntityId($enc_af, $actor_id) : NULL;
        if (!$ptcp_af) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_af = !empty($ptcp_af['entity_ref']) ? json_decode($ptcp_af['entity_ref'], TRUE) : [];
        if (empty($entity_af['fly_speed'])) {
          return ['success' => FALSE, 'result' => ['error' => 'Arrest a Fall requires fly Speed.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $acrobatics_bonus = (int) ($params['acrobatics_bonus'] ?? 0);
        $d20_af = $this->numberGenerationService->rollPathfinderDie(20);
        $total_af = $d20_af + $acrobatics_bonus;
        $degree_af = $this->combatCalculator->calculateDegreeOfSuccess($total_af, 15, $d20_af);
        $feet_fallen = (int) ($params['feet_fallen'] ?? 0);
        $damage_af = 0;
        if ($degree_af === 'failure') {
          // Normal fall damage.
          $damage_af = (int) floor($feet_fallen / 2);
        }
        elseif ($degree_af === 'critical_failure') {
          // 10 bludgeoning per 20 ft fallen so far.
          $damage_af = (int) ceil($feet_fallen / 20) * 10;
        }
        $game_state['turn']['reaction_available'] = FALSE;
        $result = ['arrest_fall' => TRUE, 'degree' => $degree_af, 'fall_damage' => $damage_af, 'roll' => $d20_af, 'total' => $total_af];
        $events[] = GameEventLogger::buildEvent('arrest_fall', 'encounter', $actor_id, ['degree' => $degree_af, 'fall_damage' => $damage_af, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2224: Grab an Edge (reaction) — Reflex DC 15 when falling past handhold.
      // -----------------------------------------------------------------------
      case 'grab_edge': {
        if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
          return ['success' => FALSE, 'result' => ['error' => 'Reaction already spent.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $reflex_bonus = (int) ($params['reflex_bonus'] ?? 0);
        $d20_ge = $this->numberGenerationService->rollPathfinderDie(20);
        $total_ge = $d20_ge + $reflex_bonus;
        $degree_ge = $this->combatCalculator->calculateDegreeOfSuccess($total_ge, 15, $d20_ge);
        $grabbed = in_array($degree_ge, ['critical_success', 'success'], TRUE);
        if ($grabbed) {
          // Mark entity clinging to edge.
          $enc_ge = $this->encounterStore->loadEncounter($encounter_id);
          $ptcp_ge = $enc_ge ? $this->findEncounterParticipantByEntityId($enc_ge, $actor_id) : NULL;
          if ($ptcp_ge) {
            $entity_ge = !empty($ptcp_ge['entity_ref']) ? json_decode($ptcp_ge['entity_ref'], TRUE) : [];
            $entity_ge['clinging'] = TRUE;
            $this->encounterStore->updateParticipant((int) $ptcp_ge['id'], ['entity_ref' => json_encode($entity_ge)]);
          }
        }
        $game_state['turn']['reaction_available'] = FALSE;
        $result = ['grab_edge' => TRUE, 'degree' => $degree_ge, 'grabbed' => $grabbed, 'roll' => $d20_ge, 'total' => $total_ge];
        $events[] = GameEventLogger::buildEvent('grab_edge', 'encounter', $actor_id, ['degree' => $degree_ge, 'grabbed' => $grabbed, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2231-2232: Shield Block (reaction) — reduce damage by hardness; split remainder.
      // -----------------------------------------------------------------------
      case 'shield_block': {
        if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
          return ['success' => FALSE, 'result' => ['error' => 'Reaction already spent.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $enc_sb = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_sb = $enc_sb ? $this->findEncounterParticipantByEntityId($enc_sb, $actor_id) : NULL;
        if (!$ptcp_sb) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_sb = !empty($ptcp_sb['entity_ref']) ? json_decode($ptcp_sb['entity_ref'], TRUE) : [];
        // REQ 2232: Shield must have been raised.
        if (empty($entity_sb['shield_raised'])) {
          return ['success' => FALSE, 'result' => ['error' => 'Shield must be raised to use Shield Block.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $shield_sb = $this->findHeldShield($entity_sb);
        if (!$shield_sb) {
          return ['success' => FALSE, 'result' => ['error' => 'No shield in hand.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $incoming_damage = (int) ($params['incoming_damage'] ?? 0);
        $hardness = (int) ($shield_sb['hardness'] ?? 0);
        $reduced = max(0, $incoming_damage - $hardness);
        $shield_takes = (int) floor($reduced / 2);
        $entity_takes = $reduced - $shield_takes;
        // Apply entity damage.
        if ($entity_takes > 0 && $this->hpManager) {
          $pid_sb = (int) $ptcp_sb['id'];
          $this->hpManager->applyDamage($pid_sb, $entity_takes, 'physical', ['source' => 'shield_block_residual'], $encounter_id);
        }
        // Apply shield damage.
        $shield_sb['hp'] = max(0, (int) ($shield_sb['hp'] ?? $shield_sb['max_hp'] ?? 10) - $shield_takes);
        if ($shield_sb['hp'] <= 0) {
          $shield_sb['broken'] = TRUE;
          $entity_sb['shield_raised'] = FALSE;
        }
        // Update shield in held items.
        $entity_sb = $this->updateHeldShield($entity_sb, $shield_sb);
        $this->encounterStore->updateParticipant((int) $ptcp_sb['id'], ['entity_ref' => json_encode($entity_sb)]);
        $game_state['turn']['reaction_available'] = FALSE;
        $result = [
          'shield_block' => TRUE,
          'incoming_damage' => $incoming_damage,
          'hardness' => $hardness,
          'entity_damage' => $entity_takes,
          'shield_damage' => $shield_takes,
          'shield_broken' => $shield_sb['broken'] ?? FALSE,
        ];
        $events[] = GameEventLogger::buildEvent('shield_block', 'encounter', $actor_id, [
          'entity_damage' => $entity_takes,
          'shield_damage' => $shield_takes,
          'shield_broken' => $shield_sb['broken'] ?? FALSE,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2228-2230: Attack of Opportunity (fighter class reaction).
      // -----------------------------------------------------------------------
      case 'attack_of_opportunity': {
        if (($game_state['turn']['reaction_available'] ?? TRUE) === FALSE) {
          return ['success' => FALSE, 'result' => ['error' => 'Reaction already spent.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // REQ 2228: Only available with 'attack_of_opportunity' class feature.
        $enc_aoo = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_aoo = $enc_aoo ? $this->findEncounterParticipantByEntityId($enc_aoo, $actor_id) : NULL;
        if (!$ptcp_aoo) {
          return ['success' => FALSE, 'result' => ['error' => 'Participant not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $entity_aoo = !empty($ptcp_aoo['entity_ref']) ? json_decode($ptcp_aoo['entity_ref'], TRUE) : [];
        $class_features = $entity_aoo['class_features'] ?? [];
        if (!in_array('attack_of_opportunity', (array) $class_features, TRUE)) {
          return ['success' => FALSE, 'result' => ['error' => 'Character does not have Attack of Opportunity class feature.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        if (!$target_id) {
          return ['success' => FALSE, 'result' => ['error' => 'target required for Attack of Opportunity.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // REQ 2230: AoO does NOT count toward or apply MAP; pass skip_map flag.
        $aoo_weapon = $params['weapon'] ?? [];
        $aoo_weapon['skip_map_count'] = TRUE;
        // Resolve as a melee Strike without consuming actions or MAP.
        $aoo_result = $this->processStrike($encounter_id, $actor_id, $target_id, ['weapon' => $aoo_weapon, 'skip_map' => TRUE], $game_state);
        // REQ 2230: Do NOT decrement attacks_this_turn — AoO is a reaction, not an action.
        // REQ 2229: Crit + manipulate trigger → disrupt the triggering action.
        $trigger_type = $params['trigger_type'] ?? '';
        $disrupted = FALSE;
        if (($aoo_result['degree'] ?? '') === 'critical_success' && $trigger_type === 'manipulate') {
          $disrupted = TRUE;
        }
        $game_state['turn']['reaction_available'] = FALSE;
        $result = array_merge($aoo_result, ['attack_of_opportunity' => TRUE, 'disrupted' => $disrupted]);
        $events[] = GameEventLogger::buildEvent('attack_of_opportunity', 'encounter', $actor_id, [
          'target' => $target_id,
          'degree' => $aoo_result['degree'] ?? NULL,
          'damage' => $aoo_result['damage'] ?? NULL,
          'disrupted' => $disrupted,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1591: Balance [1 action, Acrobatics — encounter, Secret roll]
      // Move across difficult terrain; failure = flat-footed for 1 round.
      // -----------------------------------------------------------------------
      case 'balance': {
        $dc        = (int) ($params['dc'] ?? 15);
        $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20       = $this->numberGenerationService->rollPathfinderDie(20);
        $total     = $d20 + $acrobatics;
        $degree    = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $balanced = in_array($degree, ['success', 'critical_success'], TRUE);
        if ($degree === 'critical_failure' || $degree === 'failure') {
          // Flat-footed until start of next turn.
          $this->conditionManager->applyCondition(
            (int) $actor_id, 'flat_footed', 0,
            ['remaining_attacks' => PHP_INT_MAX],
            'balance_fail',
            (int) $encounter_id
          );
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
        $result = ['balanced' => $balanced, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('balance', 'encounter', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ dc-cr-gnome-heritage-chameleon: Minor Color Shift [1 action]
      // Chameleon Gnome only. Instantly updates coloration_tag to match current terrain,
      // enabling the +2 circumstance bonus to Stealth checks in matching terrain.
      // -----------------------------------------------------------------------
      case 'minor_color_shift': {
        if (($params['heritage'] ?? '') !== 'chameleon') {
          return ['success' => FALSE, 'result' => ['error' => 'Minor Color Shift requires Chameleon Gnome heritage.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $terrain_color_mcs = trim($params['terrain_color_tag'] ?? '');
        if ($terrain_color_mcs === '') {
          return ['success' => FALSE, 'result' => ['error' => 'terrain_color_tag is required.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        // Update coloration_tag so subsequent Hide/Sneak checks can apply the bonus.
        $mutations[] = ['type' => 'char_state', 'key' => 'coloration_tag', 'value' => $terrain_color_mcs];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['coloration_tag' => $terrain_color_mcs, 'action_cost' => 1];
        $events[] = GameEventLogger::buildEvent('minor_color_shift', 'encounter', $actor_id, ['new_coloration' => $terrain_color_mcs, 'round' => $game_state['round'] ?? NULL]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1594: Tumble Through [1 action, Acrobatics — encounter]
      // Move through an enemy's space; fail = movement stops.
      // -----------------------------------------------------------------------
      case 'tumble_through': {
        $target_ref = $params['target_id'] ?? '';
        $dc         = (int) ($params['dc'] ?? 15);
        $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20        = $this->numberGenerationService->rollPathfinderDie(20);
        $total      = $d20 + $acrobatics;
        $degree     = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $passed_through = in_array($degree, ['success', 'critical_success'], TRUE);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
        $result = ['passed_through' => $passed_through, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('tumble_through', 'encounter', $actor_id, $result, NULL, $target_ref);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1598: Maneuver in Flight [1 action, Acrobatics — encounter, aerial]
      // Perform a difficult maneuver while flying.
      // -----------------------------------------------------------------------
      case 'maneuver_in_flight': {
        $dc         = (int) ($params['dc'] ?? 15);
        $acrobatics = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20        = $this->numberGenerationService->rollPathfinderDie(20);
        $total      = $d20 + $acrobatics;
        $degree     = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $maneuvered = in_array($degree, ['success', 'critical_success'], TRUE);
        if ($degree === 'critical_failure') {
          // Fall on critical failure.
          $game_state['encounter_state'][$actor_id . '_falling'] = TRUE;
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
        $result = ['maneuvered' => $maneuvered, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('maneuver_in_flight', 'encounter', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1657: Feint [2 actions, Deception — encounter]
      // Make target flat-footed: crit success = until end of turn; success = next attack.
      // -----------------------------------------------------------------------
      case 'feint': {
        $target_ref = $params['target_id'] ?? '';
        $dc         = (int) ($params['dc'] ?? 15);
        $deception  = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20        = $this->numberGenerationService->rollPathfinderDie(20);
        $total      = $d20 + $deception;
        $degree     = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $feinted = FALSE;
        if ($degree === 'critical_success') {
          $feinted = TRUE;
          // Flat-footed for all attacks through end of turn.
          $this->conditionManager->applyCondition(
            (int) $target_ref, 'flat_footed', 0,
            ['remaining_attacks' => PHP_INT_MAX],
            'feint_crit',
            (int) $encounter_id
          );
        }
        elseif ($degree === 'success') {
          $feinted = TRUE;
          // Flat-footed for next attack only.
          $this->conditionManager->applyCondition(
            (int) $target_ref, 'flat_footed', 0,
            ['remaining_attacks' => 1],
            'feint',
            (int) $encounter_id
          );
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 2);
        $result = ['feinted' => $feinted, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('feint', 'encounter', $actor_id, $result, NULL, $target_ref);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1660: Create a Diversion [1 action, Deception — encounter]
      // Allow actor to Hide by distracting observers; success = briefly hidden.
      // -----------------------------------------------------------------------
      case 'create_diversion': {
        $dc        = (int) ($params['dc'] ?? 15);
        $deception = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20       = $this->numberGenerationService->rollPathfinderDie(20);
        $total     = $d20 + $deception;
        $degree    = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $diverted = in_array($degree, ['success', 'critical_success'], TRUE);
        if ($diverted) {
          $game_state['encounter_state'][$actor_id . '_created_diversion'] = TRUE;
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
        $result = ['diverted' => $diverted, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('create_diversion', 'encounter', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1677: Request [1 action, Diplomacy — encounter]
      // Make a request of a willing or friendly target.
      // -----------------------------------------------------------------------
      case 'request': {
        $target_ref = $params['target_id'] ?? '';
        $base_dc    = (int) ($params['dc'] ?? 15);
        $dc_context = $this->applyNpcAttitudeToSocialDc($base_dc, $params, $target_id ?: $target_ref, $campaign_id);
        $dc         = $dc_context['dc'];
        $diplomacy  = (int) ($params['diplomacy_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20        = $this->numberGenerationService->rollPathfinderDie(20);
        $total      = $d20 + $diplomacy;
        $degree     = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

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
        $events[] = GameEventLogger::buildEvent('request', 'encounter', $actor_id, $result, NULL, $target_ref);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1683: Demoralize [1 action, Intimidation — encounter]
      // Apply Frightened condition; 10-min immunity per target.
      // -----------------------------------------------------------------------
      case 'demoralize': {
        $target_ref  = $params['target_id'] ?? '';
        $base_dc     = (int) ($params['dc'] ?? 15);
        $dc_context  = $this->applyNpcAttitudeToSocialDc($base_dc, $params, $target_id ?: $target_ref, $campaign_id);
        $dc          = $dc_context['dc'];
        $intimidation = (int) ($params['intimidation_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20         = $this->numberGenerationService->rollPathfinderDie(20);
        $total       = $d20 + $intimidation;
        $degree      = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $immune_key = 'demoralize_immune_' . $target_ref . '_' . $actor_id;
        $immune     = !empty($game_state['encounter_state'][$immune_key]);

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
        $events[] = GameEventLogger::buildEvent('demoralize', 'encounter', $actor_id, $result, NULL, $target_ref);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1700: Command an Animal [1 action, Nature — encounter]
      // Direct a trained animal; trained companions get DC − 5.
      // Panic on critical failure (attacks nearest creature).
      // -----------------------------------------------------------------------
      case 'command_animal': {
        $target_ref = $params['target_id'] ?? $actor_id;
        $dc         = (int) ($params['dc'] ?? 15);
        if (!empty($params['is_trained_companion'])) {
          $dc -= 5;
        }
        $nature     = (int) ($params['nature_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20        = $this->numberGenerationService->rollPathfinderDie(20);
        $total      = $d20 + $nature;
        $degree     = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $obeyed = in_array($degree, ['success', 'critical_success'], TRUE);
        if ($degree === 'critical_failure') {
          $game_state['encounter_state']['animal_panicked_' . $target_ref] = TRUE;
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
        $result = ['obeyed' => $obeyed, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('command_animal', 'encounter', $actor_id, $result, NULL, $target_ref);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1706: Perform [1 action, Performance — encounter]
      // Entertain during combat (e.g., inspire allies or distract enemies).
      // -----------------------------------------------------------------------
      case 'perform': {
        $dc          = (int) ($params['dc'] ?? 15);
        $performance = (int) ($params['performance_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20         = $this->numberGenerationService->rollPathfinderDie(20);
        $total       = $d20 + $performance;
        $degree      = $this->combatCalculator->calculateDegreeOfSuccess($total, $dc, $d20);

        $entertained = in_array($degree, ['success', 'critical_success'], TRUE);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 3) - 1);
        $result = ['entertained' => $entertained, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('perform', 'encounter', $actor_id, $result);
        break;
      }

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
   * Enters a room and ensures an encounter-framework context is active.
   */
  public function enterRoomFramework(?string $actor_id, string $target_room_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $target_room_id = trim($target_room_id);
    if ($target_room_id === '') {
      return ['error' => 'No target room specified.'];
    }

    $room = $this->findRoomById($dungeon_data, $target_room_id);
    if ($room === NULL) {
      return ['error' => "Room '$target_room_id' does not exist."];
    }

    $capability = NULL;
    if (!empty($dungeon_data['active_room_id']) && (string) $dungeon_data['active_room_id'] !== $target_room_id) {
      $capability = $this->resolveRoomTransitionCapability($dungeon_data, $target_room_id, $params);
      if ($capability === NULL) {
        return ['error' => "Room '$target_room_id' is not reachable from the active room."];
      }
      if (empty($capability['available'])) {
        return ['error' => sprintf("Room '%s' is not available for transition: %s.", $target_room_id, (string) ($capability['blocked_reason'] ?? 'blocked'))];
      }
    }

    $from_room = $dungeon_data['active_room_id'] ?? NULL;
    $dungeon_data['active_room_id'] = $target_room_id;
    $game_state['phase'] = 'encounter';
    $game_state['exploration']['previous_room'] = $from_room;

    $entry_hex = $params['entry_hex'] ?? ($params['target_hex'] ?? ['q' => 0, 'r' => 0]);
    if ($actor_id) {
      $this->moveEntityToRoom($dungeon_data, $actor_id, $target_room_id, is_array($entry_hex) ? $entry_hex : ['q' => 0, 'r' => 0]);
    }

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

    // Persist the room-scene intro into the instantiated room chat so the UI can
    // render the authoritative room description on room entry.
    $this->roomChatService->injectRoomSceneNarratorIntroIfNeeded($dungeon_data, $target_room_id);

    return [
      'transitioned' => $from_room !== $target_room_id,
      'from_room' => $from_room,
      'to_room' => $target_room_id,
      'events' => $events,
      'time_effects' => $this->buildTransitionTimeEffects($actor_id, $from_room, $target_room_id, $capability, $params),
      'mutations' => $actor_id ? [
        ['entity' => $actor_id, 'field' => 'placement.room_id', 'to' => $target_room_id],
      ] : [],
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

    return $events;
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

    return $events;
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
    $mode = strtolower(trim((string) ($game_state['encounter_context']['mode'] ?? '')));
    if ($mode === 'room_scene') {
      return TRUE;
    }

    return $mode === ''
      && empty($game_state['encounter_id'])
      && !empty($game_state['encounter_context']['room_id']);
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
    $turn_ctx = is_array($params['_encounter_turn_ctx'] ?? NULL)
      ? $params['_encounter_turn_ctx']
      : $this->captureEncounterTurnContext($game_state, $dungeon_data, $actor_id);

    $message = trim((string) ($params['message'] ?? ''));
    $room_id = $dungeon_data['active_room_id'] ?? NULL;
    $character_id = $this->resolveActorCharacterId($actor_id, $dungeon_data, $params);
    $speaker = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $target_name = trim((string) ($target_id ? $this->resolveEntityName($target_id, $game_state, $dungeon_data) : ''));

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

    $message = $this->prefixEncounterChatLine($turn_ctx, $message);
 
    $is_encounter_turn = (($game_state['phase'] ?? NULL) === 'encounter');
    // EncounterPhaseHandler owns turn order. Prevent room-chat harness from injecting
    // out-of-turn NPC turns during an actor's talk action.
    $defer_npc_interjections = $is_encounter_turn ? TRUE : !empty($params['defer_npc_interjections']);
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
          '_encounter_prefix' => $this->buildEncounterChatPrefix($turn_ctx),
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
    try {
      // Load encounter data.
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      if (!$encounter) {
        return ['error' => 'Encounter not found.'];
      }

      $attacker_participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
      $target_participant = $this->findEncounterParticipantByEntityId($encounter, $target_id);
      if (!$attacker_participant || !$target_participant) {
        return ['error' => 'Attacker or target is not present in the encounter.'];
      }

      $weapon = $this->resolveStrikeWeapon($actor_id, $params, $dungeon_data, $campaign_id);
      if (!empty($weapon['error'])) {
        return ['error' => $weapon['error']];
      }

      // REQ 2230: AoO skip_map flag — do not count this attack toward MAP.
      if (!empty($params['skip_map'])) {
        $weapon['skip_map'] = TRUE;
      }

      // Resolve attack through the combat engine, passing dungeon_data for cover/aquatic checks.
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

      // If damage was dealt, track mutations.
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

  /**
   * Find a combat participant by encounter entity_id.
   */
  protected function findEncounterParticipantByEntityId(array $encounter, string $entity_id): ?array {
    foreach (($encounter['participants'] ?? []) as $participant) {
      if ((string) ($participant['entity_id'] ?? '') === (string) $entity_id) {
        return $participant;
      }
    }

    return NULL;
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
    if ($encounter_id <= 0) {
      return NULL;
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participants = is_array($encounter['participants'] ?? NULL) ? array_values($encounter['participants']) : [];
    if ($participants === []) {
      return NULL;
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    if ($turn_index < 0 || $turn_index >= count($participants)) {
      $turn_index = 0;
    }

    $active = $participants[$turn_index] ?? NULL;
    if (!is_array($active)) {
      return NULL;
    }

    return [
      'encounter_id' => $encounter_id,
      'round' => max(1, (int) ($encounter['current_round'] ?? 1)),
      'turn_index' => $turn_index,
      'entity_id' => (string) ($active['entity_id'] ?? ''),
      'actions_remaining' => max(0, (int) ($active['actions_remaining'] ?? 3)),
      'attacks_this_turn' => max(0, (int) ($active['attacks_this_turn'] ?? 0)),
      'reaction_available' => !empty($active['reaction_available']),
      'participants' => $participants,
    ];
  }

  /**
   * Project canonical encounter state into game_state.
   */
  protected function syncGameStateWithCanonicalTurn(array &$game_state, array $canonical_turn): void {
    $entity_id = trim((string) ($canonical_turn['entity_id'] ?? ''));
    if ($entity_id === '') {
      return;
    }

    $game_state['encounter_id'] = (int) ($canonical_turn['encounter_id'] ?? ($game_state['encounter_id'] ?? 0));
    $game_state['round'] = max(1, (int) ($canonical_turn['round'] ?? ($game_state['round'] ?? 1)));
    if (is_array($canonical_turn['participants'] ?? NULL)) {
      $game_state['initiative_order'] = array_values($canonical_turn['participants']);
    }

    $existing_turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
    $game_state['turn'] = [
      'entity' => $entity_id,
      'index' => (int) ($canonical_turn['turn_index'] ?? 0),
      'actions_remaining' => max(0, (int) ($canonical_turn['actions_remaining'] ?? 3)),
      'attacks_this_turn' => max(0, (int) ($canonical_turn['attacks_this_turn'] ?? 0)),
      'reaction_available' => !empty($canonical_turn['reaction_available']),
      'delayed' => !empty($existing_turn['delayed']),
    ];
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
    $to_hex = $params['to_hex'] ?? NULL;
    if (!$to_hex) {
      return ['error' => 'Missing to_hex.', 'mutations' => []];
    }

    $is_forced = !empty($params['is_forced']);
    $movement_type = $params['movement_type'] ?? 'land';
    $encounter_for_actor = NULL;
    $actor_participant = NULL;

    // Validate movement cost vs speed if MovementResolverService is available.
    if ($this->movementResolver && !$is_forced) {
      // Load participant for speed lookup.
      $encounter_for_actor = $this->encounterStore->loadEncounter($encounter_id);
      $actor_participant = $encounter_for_actor ? $this->findEncounterParticipantByEntityId($encounter_for_actor, $actor_id) : NULL;

      if ($actor_participant) {
        $speed = $this->movementResolver->getCreatureSpeed($actor_participant, $movement_type);
        if ($speed <= 0) {
          return ['error' => "No {$movement_type} speed.", 'mutations' => []];
        }

        // Derive from_hex from participant's current position.
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

        // Track movement spent and diagonal count for this turn.
        $game_state['turn']['movement_spent'] = $movement_spent + $cost_info['cost'];
        $game_state['turn']['diagonal_count'] = $cost_info['new_diagonal_count'];
      }
    }

    // Update entity position in dungeon_data.
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

    // Also update the participant's position in the encounter store.
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

    // Check for snare trigger at the destination hex (dc-cr-snares).
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

  /**
   * Processes a spell cast during encounter.
   */
  protected function processCastSpell(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $spell_name = $params['spell_name'] ?? 'unknown';
    $spell_id = (string) ($params['spell_id'] ?? '');
    $spell_level = (int) ($params['spell_level'] ?? 0);
    $cast_at_level = (int) ($params['cast_at_level'] ?? $spell_level);
    $is_cantrip = !empty($params['is_cantrip']);
    $is_focus_spell = !empty($params['is_focus_spell']);
    $requires_attack_roll = !empty($params['requires_attack_roll']);
    $spell_tradition = $params['spell_tradition'] ?? NULL;

    // Load entity_ref for persistent spellcasting state.
    $enc_cs = $this->encounterStore->loadEncounter($encounter_id);
    $ptcp_cs = $enc_cs ? $this->findEncounterParticipantByEntityId($enc_cs, $actor_id) : NULL;
    if (!$ptcp_cs) {
      return ['cast' => FALSE, 'error' => 'Caster not found.', 'mutations' => [], 'narration' => NULL];
    }
    $edata_cs = !empty($ptcp_cs['entity_ref']) ? json_decode($ptcp_cs['entity_ref'], TRUE) : [];
    $actor_entity_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $has_actor_entity = $actor_entity_index !== NULL
      && isset($dungeon_data['entities'][$actor_entity_index])
      && is_array($dungeon_data['entities'][$actor_entity_index]);
    $canonical_state = NULL;
    $canonical_identity = ['character_id' => '', 'instance_id' => NULL];
    if ($has_actor_entity) {
      $actor_entity = $dungeon_data['entities'][$actor_entity_index];
      $canonical_state = $this->loadCanonicalCharacterState($actor_entity, (int) $campaign_id);
      $canonical_identity = $this->resolveCanonicalCharacterIdentity($actor_entity);
    }
    if ((string) ($canonical_identity['character_id'] ?? '') === '') {
      $canonical_identity = $this->resolveCanonicalCharacterIdentityFromParticipantEntityRef($edata_cs, $actor_id);
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

    // AC-002: Tradition validation (only if both tradition values are present).
    $char_tradition = $canonical_state['spells']['tradition']
      ?? $edata_cs['spellcasting_tradition']
      ?? NULL;
    if ($spell_tradition && $char_tradition && strtolower((string) $spell_tradition) !== strtolower((string) $char_tradition)) {
      return ['cast' => FALSE, 'error' => "Spell tradition '{$spell_tradition}' does not match character tradition '{$char_tradition}'.", 'mutations' => [], 'narration' => NULL];
    }

    // dc-cr-spells-ch07: Exploration cast time guard — spells with cast times
    // longer than 3 actions have the Exploration trait and cannot be used in encounters.
    $cast_time_param = $params['cast_time'] ?? NULL;
    if ($cast_time_param) {
      $phase_check = $this->spellCatalog->validateCastTimeForPhase($cast_time_param, 'encounter');
      if (!$phase_check['valid']) {
        return ['cast' => FALSE, 'error' => $phase_check['error'], 'mutations' => [], 'narration' => NULL];
      }
    }

    // dc-cr-spells-ch07: Polymorph battle form cast blocker — no casting while
    // polymorphed into a battle form (gear absorbed; no casting/speech/manipulate).
    if (!empty($edata_cs['polymorph_battle_form'])) {
      return ['cast' => FALSE, 'error' => 'Cannot cast spells while in a polymorph battle form.', 'mutations' => [], 'narration' => NULL];
    }

    // dc-cr-spells-ch07: Metamagic state machine.
    // If a metamagic feat was declared this turn (metamagic_pending set), apply and clear it.
    // Declare before resolving the cast — the cast_spell action consumes the pending metamagic.
    $metamagic_applied = NULL;
    if (!empty($game_state['turn']['metamagic_pending'][$actor_id])) {
      $metamagic_applied = $game_state['turn']['metamagic_pending'][$actor_id];
      unset($game_state['turn']['metamagic_pending'][$actor_id]);
    }

    // dc-cr-spells-ch07: Innate spells use Charisma for attack/DC.
    $is_innate_spell = !empty($params['is_innate_spell']);
    // Default spellcasting modifiers (overridden for innate spells below).
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

    // AC-006: Cantrips never expend slots; effective level = highest castable spell level.
    if ($is_cantrip) {
      $effective_level = $this->resolveEffectiveCantripLevel($canonical_state, $edata_cs);
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

    // AC-007: Focus spells consume 1 Focus Point, not a spell slot.
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
            $this->applyCanonicalStateAfterSpellConsume($canonical_state, TRUE, 0, max(0, (int) ($focus_remaining ?? 0)));
            $this->syncCanonicalSpellcastingProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
          }
          $canonical_consumed = TRUE;
        }
        catch (\InvalidArgumentException $exception) {
          return [
            'cast' => FALSE,
            'error' => $this->normalizeSpellResourceErrorMessage($exception->getMessage(), TRUE, 0),
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

    // Slot-consuming spell — determine slot level.
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

    // AC-003: Prepared casters must have the spell prepared in that slot level.
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
      if (!$this->preparedSpellListContainsSpell($prepared_cs, (string) $spell_name, $spell_id)) {
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
          $this->applyCanonicalStateAfterSpellConsume($canonical_state, FALSE, $slot_level, max(0, (int) ($slots_remaining ?? 0)));
          $this->syncCanonicalSpellcastingProjectionForActor($encounter_id, $actor_id, $campaign_id, $dungeon_data, $canonical_state);
        }
        $canonical_consumed = TRUE;
      }
      catch (\InvalidArgumentException $exception) {
        return [
          'cast' => FALSE,
          'error' => $this->normalizeSpellResourceErrorMessage($exception->getMessage(), FALSE, $slot_level),
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

    // dc-cr-spells-ch07: Incapacitation trait — downgrade degree of success when
    // target's level exceeds half the caster's level (PF2e Core ch07).
    $incapacitation_note = NULL;
    $is_incapacitation_spell = !empty($params['is_incapacitation']);
    if ($is_incapacitation_spell) {
      $caster_level = (int) ($edata_cs['level'] ?? $params['caster_level'] ?? 1);
      $target_level = (int) ($params['target_level'] ?? 0);
      if ($target_level > (int) floor($caster_level / 2)) {
        $incapacitation_note = "Incapacitation: target level ({$target_level}) exceeds half caster level (" . floor($caster_level / 2) . "); degrees of success shifted one step toward success.";
      }
    }

    // GAP-2220: Avert Gaze — if the effect has the gaze trait and the target has
    // avert_gaze_active, reduce effective DC by 2 (REQ 2220 +2 circumstance to save).
    $avert_gaze_note = NULL;
    if (!empty($params['is_gaze']) && $target_id) {
      $enc_ag2 = $this->encounterStore->loadEncounter($encounter_id);
      $ptcp_ag2 = $enc_ag2 ? $this->findEncounterParticipantByEntityId($enc_ag2, $target_id) : NULL;
      if ($ptcp_ag2) {
        $edata_ag2 = !empty($ptcp_ag2['entity_ref']) ? json_decode($ptcp_ag2['entity_ref'], TRUE) : [];
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

  /**
   * Processes an interact action during encounter (1 action).
   */
  protected function processInteract(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $interaction_type = $params['interaction_type'] ?? 'generic';

    // Handle door/passage opening.
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
        $this->conditionManager->tickConditions((int) $encounter_id, $actor_id);
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
    $events = [];
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $pending_dialogue = ($resolved_room_id && $this->roomChatService)
      ? $this->roomChatService->consumePendingEncounterRoomDialogue($campaign_id, (string) $resolved_room_id, $entity_id, $dungeon_data)
      : NULL;
    if (is_array($pending_dialogue)) {
      return $this->resolvePendingEncounterDialogueTurn($entity_id, $pending_dialogue, $game_state, $dungeon_data, $campaign_id, 'npc_pending_dialogue');
    }

    // REQ 2381: Complex hazard routine — execute per-round routine actions rather than NPC AI.
    $hazard_entity = $this->hazardService->findHazardByInstanceId($entity_id, $dungeon_data);
    if ($hazard_entity !== NULL && ($hazard_entity['type'] ?? '') === 'hazard') {
      $routine = $hazard_entity['routine'] ?? [];
      if (!empty($routine)) {
        foreach ($routine as $action_str) {
          $events[] = GameEventLogger::buildEvent('hazard_routine_action', 'encounter', $entity_id, [
            'action' => $action_str,
            'round' => $game_state['round'] ?? NULL,
          ]);
        }
      }
      else {
        $events[] = GameEventLogger::buildEvent('hazard_routine_action', 'encounter', $entity_id, [
          'action' => 'none',
          'round' => $game_state['round'] ?? NULL,
        ]);
      }
      return ['events' => $events];
    }

    $context = $this->buildNpcContext($entity_id, $game_state, $dungeon_data);

    // Check config flag — if AI autoplay disabled, always use fallback.
    $ai_enabled = (bool) $this->configFactory->get('dungeoncrawler_content.settings')
      ->get('encounter_ai_npc_autoplay_enabled');
    $ai_seed_action = NULL;

    if ($ai_enabled) {
      try {
        $result = $this->encounterAiService->requestNpcActionRecommendation($context);

        if (!empty($result['success']) && !empty($result['recommendation'])) {
          $rec = $result['recommendation'];
          $action = $rec['recommended_action'] ?? [];
          $valid = $result['validation']['valid'] ?? FALSE;

          if ($valid) {
            $ai_seed_action = [
              'type' => is_string($action['type'] ?? NULL) ? $action['type'] : '',
              'target_instance_id' => $action['target_instance_id'] ?? ($action['target'] ?? NULL),
              'narration' => is_string($rec['narration'] ?? NULL) ? $rec['narration'] : NULL,
              'rationale' => is_string($rec['rationale'] ?? NULL) ? $rec['rationale'] : '',
              'decision_reason' => is_string($rec['decision_reason'] ?? NULL) ? $rec['decision_reason'] : '',
              'decision_basis' => is_array($rec['decision_basis'] ?? NULL) ? $rec['decision_basis'] : [],
            ];
          }
          else {
            $this->logger->info('NPC AI recommendation invalid, using fallback. Errors: @errors', [
              '@errors' => implode('; ', $result['validation']['errors'] ?? []),
            ]);
          }
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('NPC AI failed, using fallback: @error', ['@error' => $e->getMessage()]);
      }
    }

    $turn_plan = $this->buildNpcTurnPlan($entity_id, $game_state, $campaign_id, $ai_seed_action);
    $intent_contract = is_array($turn_plan['intent_contract'] ?? NULL) ? $turn_plan['intent_contract'] : [];
    $plan_steps = is_array($turn_plan['steps'] ?? NULL) ? $turn_plan['steps'] : [];

    foreach ($plan_steps as $plan_step) {
      $action_type = (string) ($plan_step['action_type'] ?? '');
      if ($action_type === '') {
        continue;
      }
      $target = is_scalar($plan_step['target'] ?? NULL) ? trim((string) $plan_step['target']) : '';
      $target = $target !== '' ? $target : NULL;
      $narration = is_string($plan_step['narration'] ?? NULL) ? $plan_step['narration'] : NULL;
      $decision_reason = (string) ($plan_step['decision_reason'] ?? ($intent_contract['decision_reason'] ?? 'Intent-driven action.'));
      $decision_basis = is_array($plan_step['decision_basis'] ?? NULL) ? $plan_step['decision_basis'] : [];
      $decision_basis += [
        'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
      ];

      switch ($action_type) {
        case 'strike':
          if ($target) {
            $strike_result = $this->processStrike($encounter_id, $entity_id, $target, [], $game_state, $dungeon_data, $campaign_id);
            $events[] = GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [
              'target' => $target,
              'roll' => $strike_result['roll'] ?? NULL,
              'degree' => $strike_result['degree'] ?? NULL,
              'damage' => $strike_result['damage'] ?? NULL,
              'decision_reason' => $decision_reason,
              'decision_basis' => $decision_basis,
            ], $narration, $target);
            $this->checkEntityDefeated($target, $entity_id, $game_state, $events, $dungeon_data, $campaign_id);
          }
          break;

        case 'stride':
          $nearest = $this->findNearestAlivePlayer($entity_id, $game_state);
          $events[] = GameEventLogger::buildEvent('npc_stride', 'encounter', $entity_id, [
            'toward' => $nearest,
            'decision_reason' => $decision_reason,
            'decision_basis' => $decision_basis,
          ], $narration);
          break;

        case 'interact':
          $events[] = GameEventLogger::buildEvent('npc_interact', 'encounter', $entity_id, [
            'interaction' => 'raise_shield',
            'decision_reason' => $decision_reason,
            'decision_basis' => $decision_basis,
          ], $narration);
          break;

        case 'talk':
          $events[] = GameEventLogger::buildEvent('npc_talk', 'encounter', $entity_id, [
            'message' => $narration ?? 'The creature snarls at you.',
            'target' => $target,
            'decision_reason' => $decision_reason,
            'decision_basis' => $decision_basis,
          ], $narration);
          break;

        default:
          break;
      }

      $game_state['turn']['actions_remaining'] = max(0, (int) ($game_state['turn']['actions_remaining'] ?? 0) - 1);
      if (($game_state['turn']['actions_remaining'] ?? 0) <= 0) {
        break;
      }
    }

    $game_state['turn']['actions_remaining'] = 0;
    $terminal_decision_reason = is_string($intent_contract['decision_reason'] ?? NULL)
      ? $intent_contract['decision_reason']
      : 'No further action selected.';
    $terminal_decision_basis = is_array($intent_contract['decision_basis'] ?? NULL)
      ? $intent_contract['decision_basis']
      : [];
    $terminal_decision_basis += [
      'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
      'executed_steps' => count($plan_steps),
    ];
    return [
      'events' => array_merge(
        $events,
        $this->buildNpcChooseNotToActEvents(
          $entity_id,
          $terminal_decision_reason,
          $terminal_decision_basis,
          $game_state,
          $dungeon_data,
          $campaign_id
        )
      ),
    ];
  }

  /**
   * Room-scene NPCs must still make an explicit turn decision.
   */
  protected function passRoomActorTurn(string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $pending_dialogue = ($resolved_room_id && $this->roomChatService)
      ? $this->roomChatService->consumePendingEncounterRoomDialogue($campaign_id, (string) $resolved_room_id, $entity_id, $dungeon_data)
      : NULL;
    if (is_array($pending_dialogue)) {
      return $this->resolvePendingEncounterDialogueTurn($entity_id, $pending_dialogue, $game_state, $dungeon_data, $campaign_id, 'room_scene_pending_dialogue');
    }

    $game_state['turn']['actions_remaining'] = 0;
    return [
      'events' => $this->buildNpcChooseNotToActEvents(
        $entity_id,
        'No queued room-scene dialogue or deterministic room action was available for this actor turn.',
        [
          'intent' => 'room_scene_pass',
          'mode' => (string) ($game_state['encounter_context']['mode'] ?? 'room_scene'),
          'room_id' => $resolved_room_id,
        ],
        $game_state,
        $dungeon_data,
        $campaign_id
      ),
    ];
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
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = $this->resolveEntityName($entity_id, $game_state, $dungeon_data);
    $content = sprintf('%s chooses not to take any further actions.', $actor_name);

    $events = [
      GameEventLogger::buildEvent('npc_choose_not_to_act', 'encounter', $entity_id, [
        'round' => $game_state['round'] ?? NULL,
        'room_id' => $resolved_room_id,
        'actor_name' => $actor_name,
        'actions_remaining' => $game_state['turn']['actions_remaining'] ?? NULL,
        'reason' => 'No further action selected.',
        'decision_reason' => $decision_reason,
        'decision_basis' => $decision_basis,
      ], $content),
    ];
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'choose_not_to_act',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => $entity_id,
      'content' => $content,
      'visibility' => 'public',
      'mechanical_data' => [
        'actor_id' => $entity_id,
        'actor_name' => $actor_name,
        'room_id' => $resolved_room_id,
        'actions_remaining' => $game_state['turn']['actions_remaining'] ?? NULL,
        'decision_reason' => $decision_reason,
        'decision_basis' => $decision_basis,
      ],
    ], $resolved_room_id, $game_state);

    return $events;
  }

  protected function resolvePendingEncounterDialogueTurn(
    string $entity_id,
    array $pending_dialogue,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    string $decision_intent
  ): array {
    $resolved_room_id = $dungeon_data['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? NULL);
    $actor_name = $this->resolveEntityName($entity_id, $game_state, $dungeon_data);
    $narration = trim((string) ($pending_dialogue['narrative'] ?? ''));
    if ($narration === '') {
      return ['events' => []];
    }

    $events = [
      GameEventLogger::buildEvent('npc_talk', 'encounter', $entity_id, [
        'message' => $narration,
        'target' => NULL,
        'decision_reason' => 'Responding to the active room conversation on this actor turn.',
        'decision_basis' => [
          'intent' => $decision_intent,
          'conversation_entity_ref' => (string) ($pending_dialogue['entity_ref'] ?? ''),
          'player_message' => (string) ($pending_dialogue['player_message'] ?? ''),
        ],
      ], $narration),
    ];
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'talk',
      'speaker' => $actor_name,
      'speaker_type' => 'npc',
      'speaker_ref' => $entity_id,
      'content' => $narration,
      'visibility' => 'public',
      'mechanical_data' => [
        'actor_id' => $entity_id,
        'actor_name' => $actor_name,
        'room_id' => $resolved_room_id,
        'decision_reason' => 'Responding to the active room conversation on this actor turn.',
        'decision_basis' => [
          'intent' => $decision_intent,
          'conversation_entity_ref' => (string) ($pending_dialogue['entity_ref'] ?? ''),
          'player_message' => (string) ($pending_dialogue['player_message'] ?? ''),
        ],
      ],
    ], $resolved_room_id, $game_state);

    $remaining_before_end = max(0, ((int) ($game_state['turn']['actions_remaining'] ?? 0)) - 1);
    $game_state['turn']['actions_remaining'] = 0;
    $events[] = GameEventLogger::buildEvent('npc_choose_not_to_act', 'encounter', $entity_id, [
      'round' => $game_state['round'] ?? NULL,
      'room_id' => $resolved_room_id,
      'actor_name' => $actor_name,
      'actions_remaining' => 0,
      'reason' => 'No further action selected after replying on this turn.',
      'decision_reason' => 'Responded to the pending room conversation, then ended the turn.',
      'decision_basis' => [
        'intent' => $decision_intent,
        'remaining_actions_before_end' => $remaining_before_end,
      ],
    ], sprintf('%s chooses not to take any further actions.', $actor_name));
    $this->queueNarrationEvent($campaign_id, $dungeon_data, [
      'type' => 'choose_not_to_act',
      'speaker' => 'Narrator',
      'speaker_type' => 'narrator',
      'speaker_ref' => $entity_id,
      'content' => sprintf('%s chooses not to take any further actions.', $actor_name),
      'visibility' => 'public',
      'mechanical_data' => [
        'actor_id' => $entity_id,
        'actor_name' => $actor_name,
        'room_id' => $resolved_room_id,
        'actions_remaining' => 0,
        'decision_reason' => 'Responded to the pending room conversation, then ended the turn.',
        'decision_basis' => [
          'intent' => $decision_intent,
          'remaining_actions_before_end' => $remaining_before_end,
        ],
      ],
    ], $resolved_room_id, $game_state);

    return ['events' => $events];
  }

  public function advanceNonPlayerTurnsToNextPlayer(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    if (!$this->isRoomSceneMode($game_state)) {
      return ['events' => []];
    }

    $events = [];
    $safety = 0;
    while ($safety < 12) {
      $turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
      $current_entity = trim((string) ($turn['entity'] ?? ''));
      if ($current_entity === '') {
        break;
      }

      $current_team = $this->resolveInitiativeParticipantTeam($current_entity, $game_state);
      if ($current_team === 'player') {
        break;
      }

      $npc_result = $this->passRoomActorTurn($current_entity, $game_state, $dungeon_data, $campaign_id);
      $events = array_merge($events, $npc_result['events'] ?? []);

      $turn_result = $this->processEndTurn((int) ($game_state['encounter_id'] ?? 0), $current_entity, $game_state, $dungeon_data, $campaign_id);
      $events = array_merge($events, $turn_result['npc_events'] ?? []);
      $safety++;

      $next_entity = trim((string) ($game_state['turn']['entity'] ?? ''));
      if ($next_entity === '' || $this->resolveInitiativeParticipantTeam($next_entity, $game_state) === 'player') {
        break;
      }
    }

    return ['events' => $events];
  }

  protected function resolveInitiativeParticipantTeam(string $entity_id, array $game_state): string {
    foreach (($game_state['initiative_order'] ?? []) as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      if ((string) ($participant['entity_id'] ?? '') !== $entity_id) {
        continue;
      }
      return strtolower(trim((string) ($participant['team'] ?? '')));
    }

    return '';
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
    $actions_remaining = max(0, (int) ($game_state['turn']['actions_remaining'] ?? 0));
    $steps = [];

    for ($step_index = 0; $step_index < $actions_remaining; $step_index++) {
      $action_type = $this->resolveNpcIntentActionType($intent_contract, $step_index, $game_state);
      $target = $this->resolveNpcIntentTarget($entity_id, $game_state, $campaign_id, $action_type, $intent_contract);
      $decision_reason = (string) ($intent_contract['decision_reason'] ?? 'Intent-driven fallback action.');
      $decision_basis = [
        'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
        'plan_step' => $step_index + 1,
        'target_strategy' => (string) ($intent_contract['target_strategy'] ?? 'nearest'),
      ] + (is_array($intent_contract['decision_basis'] ?? NULL) ? $intent_contract['decision_basis'] : []);
      $narration = NULL;

      if ($step_index === 0 && is_array($ai_seed_action) && $this->isAiSeedActionCompatibleWithIntent($ai_seed_action, $intent_contract)) {
        $action_type = (string) ($ai_seed_action['type'] ?? $action_type);
        $seed_target = $ai_seed_action['target_instance_id'] ?? NULL;
        if (is_scalar($seed_target) && trim((string) $seed_target) !== '') {
          $target = trim((string) $seed_target);
        }
        $seed_reason = trim((string) ($ai_seed_action['decision_reason'] ?? $ai_seed_action['rationale'] ?? ''));
        if ($seed_reason !== '') {
          $decision_reason = $seed_reason;
        }
        $decision_basis = [
          'intent' => (string) ($intent_contract['intent'] ?? 'unknown'),
          'plan_step' => 1,
          'target_strategy' => (string) ($intent_contract['target_strategy'] ?? 'nearest'),
          'ai_seeded' => TRUE,
        ] + (is_array($ai_seed_action['decision_basis'] ?? NULL) ? $ai_seed_action['decision_basis'] : []) + (is_array($intent_contract['decision_basis'] ?? NULL) ? $intent_contract['decision_basis'] : []);
        $narration = isset($ai_seed_action['narration']) && is_string($ai_seed_action['narration'])
          ? $ai_seed_action['narration']
          : NULL;
      }

      if ($action_type === 'end_turn') {
        break;
      }

      if (in_array($action_type, ['strike', 'talk'], TRUE) && $target === NULL) {
        $action_type = ($intent_contract['intent'] ?? '') === 'self_preserve' ? 'stride' : 'end_turn';
      }
      if ($action_type === 'end_turn') {
        break;
      }

      $steps[] = [
        'action_type' => $action_type,
        'target' => $target,
        'narration' => $narration,
        'decision_reason' => $decision_reason,
        'decision_basis' => $decision_basis,
      ];
    }

    return [
      'intent_contract' => $intent_contract,
      'steps' => $steps,
    ];
  }

  /**
   * Resolve deterministic action type for a specific plan step.
   */
  protected function resolveNpcIntentActionType(array $intent_contract, int $step_index, array $game_state): string {
    $sequence = is_array($intent_contract['action_sequence'] ?? NULL) ? $intent_contract['action_sequence'] : [];
    if ($sequence === []) {
      return 'end_turn';
    }

    $resolved = $sequence[$step_index] ?? end($sequence);
    return is_string($resolved) && $resolved !== '' ? $resolved : 'end_turn';
  }

  /**
   * Resolve deterministic target for an intent step.
   */
  protected function resolveNpcIntentTarget(string $entity_id, array $game_state, int $campaign_id, string $action_type, array $intent_contract): ?string {
    if (!in_array($action_type, ['strike', 'talk'], TRUE)) {
      return NULL;
    }

    $target_strategy = (string) ($intent_contract['target_strategy'] ?? 'nearest');
    if ($target_strategy === 'weakest_adjacent') {
      return $this->chooseFallbackTarget($entity_id, $game_state, $campaign_id, $action_type);
    }

    return $this->findNearestAlivePlayer($entity_id, $game_state);
  }

  /**
   * Check whether AI-seeded first action is compatible with intent contract.
   */
  protected function isAiSeedActionCompatibleWithIntent(array $ai_seed_action, array $intent_contract): bool {
    $action_type = trim((string) ($ai_seed_action['type'] ?? ''));
    if ($action_type === '') {
      return FALSE;
    }

    $sequence = is_array($intent_contract['action_sequence'] ?? NULL) ? $intent_contract['action_sequence'] : [];
    if ($sequence === []) {
      return FALSE;
    }

    return in_array($action_type, $sequence, TRUE);
  }

  /**
   * Normalize personality-axis values for deterministic tactical decisions.
   */
  protected function normalizeDecisionPersonalityAxes(array $axes): array {
    $normalized = NpcPsychologyService::PERSONALITY_AXES;
    foreach ($normalized as $key => $default_value) {
      $value = is_numeric($axes[$key] ?? NULL) ? (int) $axes[$key] : (int) $default_value;
      $normalized[$key] = max(0, min(10, $value));
    }
    return $normalized;
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
    return $this->resolveNpcIntentActionType($intent_contract, 0, $game_state);
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
    if (!$this->isRoomSceneMode($game_state) || !$actor_id) {
      return NULL;
    }

    $remaining_actions = (int) ($game_state['turn']['actions_remaining'] ?? 0);
    if ($remaining_actions <= 0) {
      return NULL;
    }

    $current_turn_actor = (string) ($game_state['turn']['entity'] ?? '');
    if ($current_turn_actor !== '' && $current_turn_actor !== $actor_id) {
      return NULL;
    }

    $actor_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
    $turn_index = isset($game_state['turn']['index']) && is_numeric($game_state['turn']['index']) ? (int) $game_state['turn']['index'] : NULL;
    $round = isset($game_state['round']) && is_numeric($game_state['round']) ? (int) $game_state['round'] : NULL;
    $line_id = sprintf(
      'room-scene-actions-%s-%s-%s',
      $actor_id,
      $round ?? 'unknown',
      $turn_index ?? 'unknown'
    );

    return [
      'speaker' => 'System',
      'message' => sprintf(
        '%s still has %d action(s) this turn. What do you want to do next? If you only wanted to chat, use Delay to hold until the end of the round.',
        $actor_name !== '' ? $actor_name : 'This character',
        $remaining_actions
      ),
      'type' => 'system',
      'line_id' => $line_id,
      'lineId' => $line_id,
      'round' => $round,
      'turn_index' => $turn_index,
      'actor_name' => $actor_name,
      'turn_name' => $actor_name,
      'turn_role' => 'player',
      'remaining_actions' => $remaining_actions,
      'suggested_action' => 'delay',
    ];
  }

  /**
   * Checks if the encounter should end (all enemies defeated or all players defeated).
   */
  /**
   * Starts or resumes the room-scene encounter framework for a room.
   */
  protected function startRoomSceneEncounter(?string $actor_id, string $room_id, array &$game_state, array &$dungeon_data, int $campaign_id, ?array $room = NULL, ?string $narration = NULL): array {
    $initiative_order = $this->buildRoomEncounterTurnOrder($dungeon_data, $room_id, $actor_id);
    $participants = $this->buildRoomSceneEncounterParticipants($dungeon_data, $initiative_order);
    $encounter_id = $this->encounterStore->createEncounter(
      $campaign_id > 0 ? $campaign_id : NULL,
      $room_id,
      $participants,
      NULL
    );
    $canonical_turn = $this->loadCanonicalTurnState($encounter_id);
    if ($canonical_turn === NULL) {
      throw new \RuntimeException('Failed to initialize canonical room-scene encounter state.');
    }

    $game_state['phase'] = 'encounter';
    $this->syncGameStateWithCanonicalTurn($game_state, $canonical_turn);
    $game_state['round'] = (int) ($canonical_turn['round'] ?? 1);
    $game_state['encounter_context'] = [
      'room_id' => $room_id,
      'mode' => 'room_scene',
      'started_at' => date('c'),
    ];
    $game_state['encounter_id'] = $encounter_id;
    $initiative_order = is_array($game_state['initiative_order'] ?? NULL) ? $game_state['initiative_order'] : $initiative_order;

    $event_type = $narration === NULL ? 'encounter_framework_started' : 'encounter_framework_resumed';
    $events = [
      GameEventLogger::buildEvent($event_type, 'encounter', $actor_id, [
        'encounter_id' => $encounter_id,
        'room_id' => $room_id,
        'participants' => count($initiative_order),
      ], $narration ?? sprintf('The scene in %s is active.', (string) (($room['name'] ?? NULL) ?: $room_id))),
    ];
    $events = array_merge($events, $this->buildRoundStartEvents(1, $game_state, $dungeon_data, $campaign_id, $room_id));
    if (!empty($game_state['turn']['entity'])) {
      $events = array_merge($events, $this->buildTurnStartEvents((string) $game_state['turn']['entity'], $game_state, $dungeon_data, $campaign_id, $room_id));
      $events = array_merge($events, $this->buildTurnStartSearchEvents((string) $game_state['turn']['entity'], $game_state, $dungeon_data, $campaign_id));
    }

    return $events;
  }

  /**
   * Build persisted combat participants for room-scene canonical encounter state.
   */
  protected function buildRoomSceneEncounterParticipants(array $dungeon_data, array $initiative_order): array {
    $entities_by_id = [];
    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($entity_id !== '') {
        $entities_by_id[$entity_id] = $entity;
      }
    }

    $participants = [];
    foreach ($initiative_order as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $entity_id = (string) ($participant['entity_id'] ?? '');
      if ($entity_id === '') {
        continue;
      }

      $entity = $entities_by_id[$entity_id] ?? [];
      $stats = is_array($entity['state']['metadata']['stats'] ?? NULL) ? $entity['state']['metadata']['stats'] : [];
      $current_hp = $entity['state']['hit_points']['current']
        ?? $stats['currentHp']
        ?? 10;
      $max_hp = $entity['state']['hit_points']['max']
        ?? $stats['maxHp']
        ?? max(1, (int) $current_hp);
      $ac = $entity['state']['armor_class']
        ?? $stats['ac']
        ?? 10;

      $content_type = (string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''));
      $content_id = (string) ($entity['entity_ref']['content_id'] ?? $entity_id);
      $perception = isset($participant['perception']) && is_numeric($participant['perception'])
        ? (int) $participant['perception']
        : 0;

      $participants[] = [
        'entity_id' => $entity_id,
        'entity_ref' => [
          'content_type' => $content_type !== '' ? $content_type : (($participant['team'] ?? '') === 'player' ? 'player_character' : 'npc'),
          'content_id' => $content_id,
          'perception_modifier' => $perception,
          'heritage' => is_string($entity['heritage'] ?? ($entity['state']['heritage'] ?? NULL))
            ? strtolower(trim((string) ($entity['heritage'] ?? $entity['state']['heritage'])))
            : NULL,
        ],
        'team' => (string) ($participant['team'] ?? 'npc'),
        'name' => (string) ($participant['name'] ?? $entity_id),
        'status' => 'active',
        'initiative' => (int) ($participant['initiative_total'] ?? $participant['initiative'] ?? 0),
        'initiative_roll' => (int) ($participant['initiative_roll'] ?? 1),
        'ac' => (int) $ac,
        'hp' => (int) $current_hp,
        'max_hp' => (int) $max_hp,
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
        'reaction_available' => 1,
        'position_q' => (int) ($participant['position_q'] ?? 0),
        'position_r' => (int) ($participant['position_r'] ?? 0),
        'is_defeated' => !empty($entity['state']['is_defeated']),
      ];
    }

    if ($participants === []) {
      throw new \RuntimeException('Cannot initialize room-scene encounter without participants.');
    }

    return $participants;
  }

  /**
   * Builds the room encounter turn order for every actor present in the room.
   */
  protected function buildRoomEncounterTurnOrder(array $dungeon_data, string $room_id, ?string $actor_id = NULL): array {
    $participants = [];

    foreach (($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity) || (string) ($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $instance_id = (string) ($entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? '')));
      if ($instance_id === '') {
        continue;
      }
      $current_hp = $entity['state']['hit_points']['current']
        ?? $entity['state']['metadata']['stats']['currentHp']
        ?? NULL;
      if (!empty($entity['state']['is_defeated']) || (is_numeric($current_hp) && (int) $current_hp <= 0)) {
        continue;
      }
      $content_type = (string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''));
      $raw_team = strtolower(trim((string) (
        $entity['state']['metadata']['team']
        ?? $entity['state']['team']
        ?? ''
      )));
      $is_actor_type = in_array($content_type, ['player_character', 'npc', 'creature', 'character', 'monster', 'hazard'], TRUE);
      $has_actor_team = in_array($raw_team, ['player', 'player_character', 'pc', 'npc', 'enemy', 'hostile', 'monster', 'ally', 'friendly', 'companion'], TRUE);
      if (!$is_actor_type && !$has_actor_team) {
        continue;
      }
      $team = $content_type === 'player_character' || in_array($raw_team, ['player', 'player_character', 'pc'], TRUE)
        ? 'player'
        : 'npc';
      $perception = $this->resolveEntityPerceptionModifier($entity);
      $initiative_roll = (int) $this->numberGenerationService->rollPathfinderDie(20);
      if ($initiative_roll <= 0) {
        $initiative_roll = 1;
      }
      $initiative_total = $initiative_roll + $perception;
      $participants[] = [
        'entity_id' => $instance_id,
        'team' => $team,
        'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? $instance_id),
        'perception' => $perception,
        'initiative_roll' => $initiative_roll,
        'initiative_total' => $initiative_total,
        'initiative' => $initiative_total,
        'position_q' => $entity['placement']['hex']['q'] ?? 0,
        'position_r' => $entity['placement']['hex']['r'] ?? 0,
      ];
    }

    if ($actor_id && !array_filter($participants, static fn(array $participant): bool => (string) ($participant['entity_id'] ?? '') === $actor_id)) {
      $participants[] = [
        'entity_id' => $actor_id,
        'team' => 'player',
        'name' => $actor_id,
        'perception' => 0,
        'initiative_roll' => 1,
        'initiative_total' => 1,
        'initiative' => 1,
        'position_q' => 0,
        'position_r' => 0,
      ];
    }

    usort($participants, static function (array $left, array $right): int {
      $initiative_diff = (int) ($right['initiative_total'] ?? $right['initiative'] ?? 0) - (int) ($left['initiative_total'] ?? $left['initiative'] ?? 0);
      if ($initiative_diff !== 0) {
        return $initiative_diff;
      }

      $perception_diff = (int) ($right['perception'] ?? 0) - (int) ($left['perception'] ?? 0);
      if ($perception_diff !== 0) {
        return $perception_diff;
      }

      if (($left['team'] ?? '') !== ($right['team'] ?? '')) {
        return ($left['team'] ?? '') === 'player' ? -1 : 1;
      }

      return strcmp((string) ($left['entity_id'] ?? ''), (string) ($right['entity_id'] ?? ''));
    });

    return array_values($participants);
  }

  /**
   * Resolve perception modifier from entity runtime state.
   */
  protected function resolveEntityPerceptionModifier(array $entity): int {
    $stats = is_array($entity['state']['metadata']['stats'] ?? NULL) ? $entity['state']['metadata']['stats'] : [];
    if (isset($stats['perception']) && is_numeric($stats['perception'])) {
      return (int) $stats['perception'];
    }
    if (isset($entity['state']['perception']) && is_numeric($entity['state']['perception'])) {
      return (int) $entity['state']['perception'];
    }
    if (isset($entity['perception']) && is_numeric($entity['perception'])) {
      return (int) $entity['perception'];
    }
    return 0;
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
    if ($this->navigationService) {
      return $this->navigationService->findRoomById($dungeon_data, $room_id);
    }
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (is_array($room) && (string) ($room['room_id'] ?? '') === $room_id) {
        return $room;
      }
    }
    return NULL;
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

    $capabilities = $this->navigationService
      ? $this->navigationService->buildNavigationCapabilities($dungeon_data, $active_room_id)
      : $this->buildFallbackNavigationCapabilities($dungeon_data, $active_room_id);
    foreach ($capabilities as $capability) {
      if ((string) ($capability['target_room_id'] ?? '') === $target_room_id) {
        return $capability;
      }
    }

    $connection_id = isset($params['connection_id']) ? (string) $params['connection_id'] : '';
    if ($connection_id !== '') {
      foreach ($capabilities as $capability) {
        if ((string) ($capability['connection_id'] ?? '') === $connection_id) {
          return $capability;
        }
      }
    }

    return NULL;
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
    return in_array($type, ['treat_wounds', 'refocus', 'repair', 'daily_preparations'], TRUE);
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
    foreach (($dungeon_data['entities'] ?? []) as $index => $entity) {
      $candidates = [
        $entity['entity_instance_id'] ?? NULL,
        $entity['instance_id'] ?? NULL,
        $entity['id'] ?? NULL,
      ];
      foreach ($candidates as $candidate) {
        if (is_scalar($candidate) && (string) $candidate === $entity_id) {
          return $index;
        }
      }
    }
    return NULL;
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
    $identity = $this->resolveCanonicalCharacterIdentity($entity);
    $character_id = (string) ($identity['character_id'] ?? '');
    $instance_id = is_string($identity['instance_id'] ?? NULL) ? $identity['instance_id'] : NULL;
    if (!ctype_digit($character_id) || (int) $character_id <= 0) {
      return NULL;
    }
    return $this->characterStateService->getState($character_id, $campaign_id, $instance_id) ?: NULL;
  }

  protected function persistCanonicalCharacterState(array $entity, int $campaign_id, array $character_state): void {
    $identity = $this->resolveCanonicalCharacterIdentity($entity);
    $character_id = (string) ($identity['character_id'] ?? '');
    $instance_id = is_string($identity['instance_id'] ?? NULL) ? $identity['instance_id'] : NULL;
    if (!ctype_digit($character_id) || (int) $character_id <= 0) {
      return;
    }
    $this->characterStateService->setState($character_id, $character_state, NULL, $campaign_id, $instance_id);
  }

  /**
   * Resolve canonical character identity from runtime entity payload.
   *
   * @return array{character_id: string, instance_id: ?string}
   */
  protected function resolveCanonicalCharacterIdentity(array $entity): array {
    $character_id = (string) (
      $entity['state']['metadata']['campaign_character_id']
      ?? $entity['state']['metadata']['character_id']
      ?? $entity['character_id']
      ?? $entity['state']['character_id']
      ?? $entity['entity_ref']['character_id']
      ?? ''
    );
    $instance_id = trim((string) (
      $entity['state']['metadata']['runtime_entity_id']
      ?? $entity['instance_id']
      ?? $entity['entity_instance_id']
      ?? ''
    ));

    return [
      'character_id' => $character_id,
      'instance_id' => $instance_id !== '' ? $instance_id : NULL,
    ];
  }

  /**
   * Resolve canonical character identity from participant entity_ref payload.
   *
   * @return array{character_id: string, instance_id: ?string}
   */
  protected function resolveCanonicalCharacterIdentityFromParticipantEntityRef(array $entity_ref, string $fallback_instance_id = ''): array {
    $character_id = (string) (
      $entity_ref['state']['metadata']['campaign_character_id']
      ?? $entity_ref['state']['metadata']['character_id']
      ?? $entity_ref['character_id']
      ?? $entity_ref['state']['character_id']
      ?? $entity_ref['entity_ref']['character_id']
      ?? ''
    );
    $instance_id = trim((string) (
      $entity_ref['state']['metadata']['runtime_entity_id']
      ?? $entity_ref['instance_id']
      ?? $entity_ref['entity_instance_id']
      ?? $fallback_instance_id
    ));

    return [
      'character_id' => $character_id,
      'instance_id' => $instance_id !== '' ? $instance_id : NULL,
    ];
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
    $actor_entity_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $has_actor_entity = $actor_entity_index !== NULL
      && isset($dungeon_data['entities'][$actor_entity_index])
      && is_array($dungeon_data['entities'][$actor_entity_index]);
    $canonical_identity = ['character_id' => '', 'instance_id' => NULL];
    if ($has_actor_entity) {
      $canonical_identity = $this->resolveCanonicalCharacterIdentity($dungeon_data['entities'][$actor_entity_index]);
    }

    $encounter = NULL;
    $participant = NULL;
    if ($encounter_id) {
      $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
        if (
          (string) ($canonical_identity['character_id'] ?? '') === ''
          && is_array($participant)
        ) {
          $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
          if (is_array($participant_entity_ref)) {
            $canonical_identity = $this->resolveCanonicalCharacterIdentityFromParticipantEntityRef($participant_entity_ref, $actor_id);
          }
        }
      }
    }

    if (!is_array($canonical_state)) {
      $character_id = (string) ($canonical_identity['character_id'] ?? '');
      $instance_id = is_string($canonical_identity['instance_id'] ?? NULL) ? $canonical_identity['instance_id'] : NULL;
      if (!ctype_digit($character_id) || (int) $character_id <= 0) {
        return;
      }
      try {
        $canonical_state = $this->characterStateService->getState(
          $character_id,
          $campaign_id > 0 ? $campaign_id : NULL,
          $instance_id
        );
      }
      catch (\InvalidArgumentException $exception) {
        $this->logger->warning('Spellcasting projection sync skipped: @error', ['@error' => $exception->getMessage()]);
        return;
      }
      if (!is_array($canonical_state)) {
        return;
      }
    }

    if ($has_actor_entity) {
      $this->applyCanonicalSpellcastingResourcesToDungeonEntity($dungeon_data['entities'][$actor_entity_index], $canonical_state);
    }

    if (!$encounter_id) {
      return;
    }

    if (!is_array($participant)) {
      if (!is_array($encounter)) {
        $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      }
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
      }
    }
    if (!$participant) {
      return;
    }

    $participant_id = (int) ($participant['id'] ?? 0);
    if ($participant_id <= 0) {
      return;
    }
    $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
    if (!is_array($participant_entity_ref)) {
      $participant_entity_ref = [];
    }
    $this->applyCanonicalSpellcastingResourcesToParticipantEntityRef($participant_entity_ref, $canonical_state);
    $this->persistEncounterParticipantEntityRef($participant_id, $participant_entity_ref);
  }

  protected function syncCanonicalSurvivalProjectionForActor(?int $encounter_id, string $actor_id, int $campaign_id, array &$dungeon_data, ?array $canonical_state = NULL): void {
    $actor_entity_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $has_actor_entity = $actor_entity_index !== NULL
      && isset($dungeon_data['entities'][$actor_entity_index])
      && is_array($dungeon_data['entities'][$actor_entity_index]);
    $canonical_identity = ['character_id' => '', 'instance_id' => NULL];
    if ($has_actor_entity) {
      $canonical_identity = $this->resolveCanonicalCharacterIdentity($dungeon_data['entities'][$actor_entity_index]);
    }

    $encounter = NULL;
    $participant = NULL;
    if ($encounter_id) {
      $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
        if (
          (string) ($canonical_identity['character_id'] ?? '') === ''
          && is_array($participant)
        ) {
          $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
          if (is_array($participant_entity_ref)) {
            $canonical_identity = $this->resolveCanonicalCharacterIdentityFromParticipantEntityRef($participant_entity_ref, $actor_id);
          }
        }
      }
    }

    if (!is_array($canonical_state)) {
      $character_id = (string) ($canonical_identity['character_id'] ?? '');
      $instance_id = is_string($canonical_identity['instance_id'] ?? NULL) ? $canonical_identity['instance_id'] : NULL;
      if (!ctype_digit($character_id) || (int) $character_id <= 0) {
        return;
      }
      try {
        $canonical_state = $this->characterStateService->getState(
          $character_id,
          $campaign_id > 0 ? $campaign_id : NULL,
          $instance_id
        );
      }
      catch (\InvalidArgumentException $exception) {
        $this->logger->warning('Survival projection sync skipped: @error', ['@error' => $exception->getMessage()]);
        return;
      }
      if (!is_array($canonical_state)) {
        return;
      }
    }

    if ($has_actor_entity) {
      $this->applyCanonicalSurvivalResourcesToDungeonEntity($dungeon_data['entities'][$actor_entity_index], $canonical_state);
    }

    if (!$encounter_id) {
      return;
    }

    if (!is_array($participant)) {
      if (!is_array($encounter)) {
        $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      }
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
      }
    }
    if (!$participant) {
      return;
    }

    $participant_id = (int) ($participant['id'] ?? 0);
    if ($participant_id <= 0) {
      return;
    }
    $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
    if (!is_array($participant_entity_ref)) {
      $participant_entity_ref = [];
    }
    $this->applyCanonicalSurvivalResourcesToParticipantEntityRef($participant_entity_ref, $canonical_state);
    $this->persistEncounterParticipantEntityRef($participant_id, $participant_entity_ref);
  }

  protected function applyCanonicalSurvivalResourcesToDungeonEntity(array &$entity, array $character_state): void {
    if (!isset($entity['state']) || !is_array($entity['state'])) {
      $entity['state'] = [];
    }

    $survival = $this->readCanonicalSurvivalStateFromCanonicalState($character_state);
    $entity['state']['days_without_food'] = (int) ($survival['daysWithoutFood'] ?? 0);
    $entity['state']['days_without_water'] = (int) ($survival['daysWithoutWater'] ?? 0);
    $entity['state']['starvation_damage_phase'] = !empty($survival['starvationDamagePhase']);
    $entity['state']['thirst_damage_phase'] = !empty($survival['thirstDamagePhase']);

    if (is_array($character_state['resources']['hitPoints'] ?? NULL)) {
      $current = (int) ($character_state['resources']['hitPoints']['current'] ?? ($entity['state']['hit_points']['current'] ?? 0));
      $max = (int) ($character_state['resources']['hitPoints']['max'] ?? ($entity['state']['hit_points']['max'] ?? $current));
      $entity['state']['hit_points']['current'] = $current;
      $entity['state']['hit_points']['max'] = $max;
      $entity['state']['hp_current'] = $current;
      $entity['state']['hp_max'] = $max;
      if (isset($entity['hit_points']) && is_array($entity['hit_points'])) {
        $entity['hit_points']['current'] = $current;
        $entity['hit_points']['max'] = $max;
      }
    }
  }

  protected function applyCanonicalSurvivalResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    if (!isset($entity_ref['state']) || !is_array($entity_ref['state'])) {
      $entity_ref['state'] = [];
    }

    $survival = $this->readCanonicalSurvivalStateFromCanonicalState($character_state);
    $entity_ref['state']['days_without_food'] = (int) ($survival['daysWithoutFood'] ?? 0);
    $entity_ref['state']['days_without_water'] = (int) ($survival['daysWithoutWater'] ?? 0);
    $entity_ref['state']['starvation_damage_phase'] = !empty($survival['starvationDamagePhase']);
    $entity_ref['state']['thirst_damage_phase'] = !empty($survival['thirstDamagePhase']);

    if (is_array($character_state['resources']['hitPoints'] ?? NULL)) {
      $current = (int) ($character_state['resources']['hitPoints']['current'] ?? 0);
      $max = (int) ($character_state['resources']['hitPoints']['max'] ?? $current);
      if (!isset($entity_ref['state']['hit_points']) || !is_array($entity_ref['state']['hit_points'])) {
        $entity_ref['state']['hit_points'] = [];
      }
      $entity_ref['state']['hit_points']['current'] = $current;
      $entity_ref['state']['hit_points']['max'] = $max;
      $entity_ref['state']['hp_current'] = $current;
      $entity_ref['state']['hp_max'] = $max;
    }
  }

  /**
   * @return array{daysWithoutFood:int,daysWithoutWater:int,starvationDamagePhase:bool,thirstDamagePhase:bool}
   */
  protected function readCanonicalSurvivalStateFromCanonicalState(array $character_state): array {
    $survival = is_array($character_state['resources']['survival'] ?? NULL) ? $character_state['resources']['survival'] : [];

    return [
      'daysWithoutFood' => max(0, (int) ($survival['daysWithoutFood'] ?? 0)),
      'daysWithoutWater' => max(0, (int) ($survival['daysWithoutWater'] ?? 0)),
      'starvationDamagePhase' => (bool) ($survival['starvationDamagePhase'] ?? FALSE),
      'thirstDamagePhase' => (bool) ($survival['thirstDamagePhase'] ?? FALSE),
    ];
  }

  protected function normalizeSpellSlotRankKey(string $slot_key): ?string {
    $normalized = strtolower(trim($slot_key));
    return match ($normalized) {
      '1', '1st', 'first' => '1',
      '2', '2nd', 'second' => '2',
      '3', '3rd', 'third' => '3',
      '4', '4th', 'fourth' => '4',
      '5', '5th', 'fifth' => '5',
      '6', '6th', 'sixth' => '6',
      '7', '7th', 'seventh' => '7',
      '8', '8th', 'eighth' => '8',
      '9', '9th', 'ninth' => '9',
      '10', '10th', 'tenth' => '10',
      default => NULL,
    };
  }

  protected function resolveEffectiveCantripLevel(?array $canonical_state, array $participant_entity_ref): int {
    $levels = [];

    $canonical_slots = is_array($canonical_state['resources']['spellSlots'] ?? NULL)
      ? $canonical_state['resources']['spellSlots']
      : [];
    foreach ($canonical_slots as $rank_key => $slot_state) {
      if (!is_array($slot_state)) {
        continue;
      }
      $normalized_rank = $this->normalizeSpellSlotRankKey((string) $rank_key);
      if ($normalized_rank === NULL) {
        continue;
      }
      $max = (int) ($slot_state['max'] ?? $slot_state['current'] ?? 0);
      if ($max > 0) {
        $levels[] = (int) $normalized_rank;
      }
    }

    if ($levels === []) {
      $participant_slots = is_array($participant_entity_ref['spell_slots'] ?? NULL)
        ? $participant_entity_ref['spell_slots']
        : [];
      foreach ($participant_slots as $rank_key => $slot_state) {
        if (!is_array($slot_state)) {
          continue;
        }
        $normalized_rank = $this->normalizeSpellSlotRankKey((string) $rank_key);
        if ($normalized_rank === NULL) {
          continue;
        }
        $max = (int) ($slot_state['max'] ?? 0);
        if ($max > 0) {
          $levels[] = (int) $normalized_rank;
        }
      }
    }

    return $levels !== [] ? max($levels) : 1;
  }

  protected function resolveParticipantFocusPointCurrent(array $participant_entity_ref): int {
    $candidates = [
      $participant_entity_ref['focus_points'] ?? NULL,
      $participant_entity_ref['state']['focus_points']['current'] ?? NULL,
      $participant_entity_ref['state']['resources']['focusPoints']['current'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate)) {
        return max(0, (int) $candidate);
      }
    }
    return 0;
  }

  protected function applyCanonicalSpellcastingResourcesToDungeonEntity(array &$entity, array $character_state): void {
    $resources = is_array($character_state['resources'] ?? NULL) ? $character_state['resources'] : [];
    if (!isset($entity['state']) || !is_array($entity['state'])) {
      $entity['state'] = [];
    }

    $spell_slots = is_array($resources['spellSlots'] ?? NULL) ? $resources['spellSlots'] : [];
    if ($spell_slots !== []) {
      if (!isset($entity['state']['resources']) || !is_array($entity['state']['resources'])) {
        $entity['state']['resources'] = [];
      }
      $entity['state']['resources']['spellSlots'] = $spell_slots;
      $entity['state']['spell_slots'] = $this->buildLegacySpellSlotProjection($spell_slots);
    }

    if (is_array($resources['focusPoints'] ?? NULL)) {
      $focus_max = max(0, (int) ($resources['focusPoints']['max'] ?? 0));
      $focus_current = max(0, min((int) ($resources['focusPoints']['current'] ?? $focus_max), $focus_max));
      $this->writeEntityFocusPoints($entity, $focus_current, $focus_max);
    }
  }

  protected function applyCanonicalSpellcastingResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    $resources = is_array($character_state['resources'] ?? NULL) ? $character_state['resources'] : [];
    if (!isset($entity_ref['state']) || !is_array($entity_ref['state'])) {
      $entity_ref['state'] = [];
    }
    if (!isset($entity_ref['state']['resources']) || !is_array($entity_ref['state']['resources'])) {
      $entity_ref['state']['resources'] = [];
    }

    $spell_slots = is_array($resources['spellSlots'] ?? NULL) ? $resources['spellSlots'] : [];
    if ($spell_slots !== []) {
      $legacy_projection = $this->buildLegacySpellSlotProjection($spell_slots);
      $entity_ref['state']['resources']['spellSlots'] = $spell_slots;
      $entity_ref['state']['spell_slots'] = $legacy_projection;
      $entity_ref['spell_slots'] = $legacy_projection;
    }

    if (is_array($resources['focusPoints'] ?? NULL)) {
      $focus_max = max(0, (int) ($resources['focusPoints']['max'] ?? 0));
      $focus_current = max(0, min((int) ($resources['focusPoints']['current'] ?? $focus_max), $focus_max));
      $entity_ref['state']['resources']['focusPoints'] = [
        'current' => $focus_current,
        'max' => $focus_max,
      ];
      $entity_ref['state']['focus_points'] = [
        'current' => $focus_current,
        'max' => $focus_max,
      ];
      $entity_ref['focus_points'] = $focus_current;
    }
  }

  protected function buildLegacySpellSlotProjection(array $spell_slots): array {
    $projection = [];
    $append_projection = function (string $rank_key, array $slot_state) use (&$projection): void {
      $normalized_rank = $this->normalizeSpellSlotRankKey($rank_key);
      if ($normalized_rank === NULL) {
        return;
      }
      $max = max(0, (int) ($slot_state['max'] ?? 0));
      $current = max(0, min((int) ($slot_state['current'] ?? $max), $max));
      $projection[$normalized_rank] = [
        'max' => $max,
        'current' => $current,
        'used' => max(0, $max - $current),
      ];
    };

    foreach ($spell_slots as $rank_key => $slot_state) {
      if (!is_array($slot_state)) {
        continue;
      }
      if (array_key_exists('max', $slot_state) || array_key_exists('current', $slot_state)) {
        $append_projection((string) $rank_key, $slot_state);
        continue;
      }
      foreach ($slot_state as $nested_rank_key => $nested_slot_state) {
        if (is_array($nested_slot_state)) {
          $append_projection((string) $nested_rank_key, $nested_slot_state);
        }
      }
    }

    if ($projection !== []) {
      ksort($projection, SORT_NATURAL);
    }
    return $projection;
  }

  protected function persistEncounterParticipantEntityRef(int $participant_id, array $entity_ref): void {
    if ($participant_id <= 0) {
      return;
    }
    try {
      $this->encounterStore->updateParticipant($participant_id, ['entity_ref' => json_encode($entity_ref)]);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Encounter participant spell resource sync failed: @error', ['@error' => $e->getMessage()]);
    }
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
    if (is_array($character_state) && isset($character_state['resources']['focusPoints'][$field]) && is_numeric($character_state['resources']['focusPoints'][$field])) {
      return max(0, (int) $character_state['resources']['focusPoints'][$field]);
    }
    return $this->readEntityFocusPoints($entity, $field);
  }

  protected function resolveCharacterLevel(?array $character_state, array $entity): int {
    if (is_array($character_state) && isset($character_state['basicInfo']['level']) && is_numeric($character_state['basicInfo']['level'])) {
      return max(1, (int) $character_state['basicInfo']['level']);
    }
    return max(1, (int) ($entity['state']['level'] ?? $entity['level'] ?? 1));
  }

  protected function resolveCharacterConstitutionModifier(?array $character_state, array $entity): int {
    if (is_array($character_state)) {
      if (isset($character_state['abilities']['constitution']['modifier']) && is_numeric($character_state['abilities']['constitution']['modifier'])) {
        return (int) $character_state['abilities']['constitution']['modifier'];
      }
      if (isset($character_state['abilities']['constitution']) && is_numeric($character_state['abilities']['constitution'])) {
        return (int) floor(((int) $character_state['abilities']['constitution'] - 10) / 2);
      }
      if (isset($character_state['abilityScores']['constitution']['modifier']) && is_numeric($character_state['abilityScores']['constitution']['modifier'])) {
        return (int) $character_state['abilityScores']['constitution']['modifier'];
      }
    }
    return (int) ($entity['state']['constitution_modifier'] ?? 0);
  }

  protected function applyCanonicalHealing(array &$character_state, int $delta): void {
    $resources = $character_state['resources'] ?? [];
    $current = (int) ($resources['hitPoints']['current'] ?? 0);
    $max = (int) ($resources['hitPoints']['max'] ?? $current);
    $resources['hitPoints']['current'] = max(0, min($max, $current + $delta));
    $resources['hitPoints']['max'] = $max;
    $character_state['resources'] = $resources;
  }

  protected function restoreCanonicalSpellSlots(array &$character_state): void {
    if (empty($character_state['resources']['spellSlots']) || !is_array($character_state['resources']['spellSlots'])) {
      return;
    }
    foreach ($character_state['resources']['spellSlots'] as &$slot_group) {
      if (!is_array($slot_group)) {
        continue;
      }
      if (array_key_exists('max', $slot_group)) {
        $slot_group['current'] = (int) ($slot_group['max'] ?? $slot_group['current'] ?? 0);
      }
      else {
        foreach ($slot_group as &$slot_row) {
          if (is_array($slot_row) && array_key_exists('max', $slot_row)) {
            $slot_row['current'] = (int) ($slot_row['max'] ?? $slot_row['current'] ?? 0);
          }
        }
        unset($slot_row);
      }
    }
    unset($slot_group);
  }

  protected function restoreCanonicalFocusPoints(array &$character_state): void {
    if (!is_array($character_state['resources']['focusPoints'] ?? NULL)) {
      return;
    }
    $max = (int) ($character_state['resources']['focusPoints']['max'] ?? 0);
    $character_state['resources']['focusPoints']['current'] = $max;
  }

  protected function applyCanonicalDailyPreparationConditionRecovery(array &$character_state): void {
    $conditions = $character_state['conditions'] ?? [];
    foreach ($conditions as $index => $condition) {
      if (is_array($condition)) {
        $name = strtolower((string) ($condition['name'] ?? ''));
        if ($name === 'doomed') {
          $value = max(0, (int) ($condition['value'] ?? 1) - 1);
          if ($value <= 0) {
            unset($conditions[$index]);
          }
          else {
            $conditions[$index]['value'] = $value;
          }
        }
        if ($name === 'wounded') {
          unset($conditions[$index]);
        }
      }
      elseif (strtolower((string) $condition) === 'wounded') {
        unset($conditions[$index]);
      }
    }
    $character_state['conditions'] = array_values($conditions);
  }

  protected function readEntityFocusPoints(array $entity, string $field): int {
    $candidates = [
      $entity['state']['resources']['focusPoints'][$field] ?? NULL,
      $entity['state']['focus_points'][$field] ?? NULL,
      $field === 'current' ? ($entity['state']['focus_points'] ?? NULL) : NULL,
      $entity['focus_points'][$field] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate)) {
        return max(0, (int) $candidate);
      }
    }
    return 0;
  }

  protected function writeEntityFocusPoints(array &$entity, int $current, int $max): void {
    $entity['state']['resources']['focusPoints']['current'] = $current;
    $entity['state']['resources']['focusPoints']['max'] = $max;
    $entity['state']['focus_points']['current'] = $current;
    $entity['state']['focus_points']['max'] = $max;
    if (isset($entity['focus_points']) && !is_array($entity['focus_points'])) {
      $entity['focus_points'] = $current;
    }
  }

  protected function restoreEntitySpellSlots(array &$entity): void {
    if (!empty($entity['state']['resources']['spellSlots']) && is_array($entity['state']['resources']['spellSlots'])) {
      foreach ($entity['state']['resources']['spellSlots'] as &$slot_group) {
        if (!is_array($slot_group)) {
          continue;
        }
        if (array_key_exists('max', $slot_group)) {
          $slot_group['current'] = (int) ($slot_group['max'] ?? $slot_group['current'] ?? 0);
        }
      }
      unset($slot_group);
    }
    if (!empty($entity['state']['spell_slots']) && is_array($entity['state']['spell_slots'])) {
      foreach ($entity['state']['spell_slots'] as &$slot_group) {
        if (!is_array($slot_group)) {
          continue;
        }
        if (array_key_exists('max', $slot_group)) {
          $slot_group['current'] = (int) ($slot_group['max'] ?? $slot_group['current'] ?? 0);
          if (array_key_exists('used', $slot_group)) {
            $slot_group['used'] = 0;
          }
        }
      }
      unset($slot_group);
    }
  }

  protected function applyDailyPreparationConditionRecovery(array &$entity): array {
    $changes = [];
    if (!isset($entity['state']['conditions']) || !is_array($entity['state']['conditions'])) {
      return $changes;
    }
    foreach ($entity['state']['conditions'] as $key => $condition) {
      if (is_array($condition)) {
        $name = strtolower((string) ($condition['name'] ?? ''));
        if ($name === 'doomed') {
          $value = max(0, (int) ($condition['value'] ?? 1) - 1);
          if ($value <= 0) {
            unset($entity['state']['conditions'][$key]);
            $changes[] = 'removed doomed';
          }
          else {
            $entity['state']['conditions'][$key]['value'] = $value;
            $changes[] = sprintf('reduced doomed to %d', $value);
          }
        }
        if ($name === 'wounded') {
          unset($entity['state']['conditions'][$key]);
          $changes[] = 'removed wounded';
        }
      }
      elseif (strtolower((string) $condition) === 'wounded') {
        unset($entity['state']['conditions'][$key]);
        $changes[] = 'removed wounded';
      }
    }
    $entity['state']['conditions'] = array_values($entity['state']['conditions']);
    return $changes;
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
   */
  protected function buildFallbackNavigationCapabilities(array $dungeon_data, string $room_id): array {
    $connections = $dungeon_data['hex_map']['connections'] ?? ($dungeon_data['connections'] ?? []);
    $capabilities = [];
    foreach ((array) $connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room = (string) ($connection['from_room'] ?? ($connection['from']['room_id'] ?? ''));
      $to_room = (string) ($connection['to_room'] ?? ($connection['to']['room_id'] ?? ''));
      if ($from_room !== $room_id && $to_room !== $room_id) {
        continue;
      }
      $target_room_id = $from_room === $room_id ? $to_room : $from_room;
      $is_discovered = array_key_exists('is_discovered', $connection) ? !empty($connection['is_discovered']) : TRUE;
      $is_passable = array_key_exists('is_passable', $connection) ? !empty($connection['is_passable']) : TRUE;
      $blocked_reason = !$is_discovered ? 'undiscovered' : (!$is_passable ? 'blocked' : NULL);
      $capabilities[] = [
        'connection_id' => (string) ($connection['connection_id'] ?? ($from_room . '__' . $to_room)),
        'target_room_id' => $target_room_id,
        'available' => $blocked_reason === NULL,
        'blocked_reason' => $blocked_reason,
        'travel_time_seconds' => $this->resolveTravelSeconds($connection, []),
      ];
    }
    return $capabilities;
  }

  /**
   * Moves an entity placement into a room.
   */
  protected function moveEntityToRoom(array &$dungeon_data, string $actor_id, string $room_id, array $hex): void {
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
      break;
    }
    unset($entity);
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
    $initiative_order = $game_state['initiative_order'] ?? [];
    $npc = NULL;
    $allies = [];
    $enemies = [];

    foreach ($initiative_order as $combatant) {
      $cid = $combatant['entity_id'] ?? '';
      if ($cid === $entity_id) {
        $npc = $combatant;
        continue;
      }
      if (!empty($combatant['is_defeated'])) {
        continue;
      }
      $team = $combatant['team'] ?? 'enemy';
      if ($team === 'player') {
        $enemies[] = [
          'entity_id' => $cid,
          'name' => $combatant['name'] ?? $cid,
          'hp_ratio' => $this->hpRatio($combatant),
          'position_q' => (int) ($combatant['position_q'] ?? 0),
          'position_r' => (int) ($combatant['position_r'] ?? 0),
          'ac' => (int) ($combatant['ac'] ?? 10),
        ];
      }
      else {
        $allies[] = [
          'entity_id' => $cid,
          'name' => $combatant['name'] ?? $cid,
          'hp_ratio' => $this->hpRatio($combatant),
        ];
      }
    }

    $context_game_state = $game_state;
    if (!is_array($context_game_state['turn'] ?? NULL)) {
      $context_game_state['turn'] = [];
    }
    if (trim((string) ($context_game_state['turn']['entity'] ?? '')) === '') {
      $context_game_state['turn']['entity'] = $entity_id;
    }
    if (!is_numeric($context_game_state['turn']['actions_remaining'] ?? NULL)) {
      $context_game_state['turn']['actions_remaining'] = 3;
    }
    if (!array_key_exists('reaction_available', $context_game_state['turn'])) {
      $context_game_state['turn']['reaction_available'] = FALSE;
    }

    $availability = $this->actionAvailability->resolveEncounterAvailability($context_game_state, $dungeon_data, $entity_id);
    $allowed_actions = $availability['available_actions'];
    $action_contract = $availability['action_contract'];
    $actions_available_to_me_this_turn = $availability['availability_envelope'];

    return [
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'campaign_id' => $game_state['campaign_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'entity_id' => $entity_id,
      'current_actor' => $npc ? [
        'entity_id' => $entity_id,
        'entity_ref' => $entity_id,
        'name' => $npc['name'] ?? $entity_id,
        'team' => $npc['team'] ?? 'enemy',
        'hp' => (int) ($npc['hp'] ?? 0),
        'max_hp' => (int) ($npc['max_hp'] ?? 0),
        'hp_ratio' => $this->hpRatio($npc ?? []),
        'ac' => (int) ($npc['ac'] ?? 12),
        'position_q' => (int) ($npc['position_q'] ?? 0),
        'position_r' => (int) ($npc['position_r'] ?? 0),
        'actions_remaining' => (int) ($game_state['turn']['actions_remaining'] ?? 3),
      ] : ['entity_id' => $entity_id, 'entity_ref' => $entity_id],
      'current_actor_profile' => $this->buildNpcDecisionProfile($entity_id, $game_state),
      'participants' => $initiative_order,
      'allies' => $allies,
      'threats' => $enemies,
      'allowed_actions' => $allowed_actions,
      'action_contract' => $action_contract,
      'actions_available_to_me_this_turn' => $actions_available_to_me_this_turn,
      // NPC personality/psychology context for AI decision-making.
      'npc_psychology' => $this->buildNpcPsychologyContext($entity_id, $game_state),
      'current_actor_tactical_intent' => $this->buildNpcTacticalIntentContract(
        $entity_id,
        $game_state,
        (int) ($game_state['campaign_id'] ?? 0)
      ),
    ];
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
    $campaign_id = (int) ($game_state['campaign_id'] ?? 0);
    $profile = $this->loadCombatantPsychologyProfile($entity_id, $game_state, $campaign_id);
    if (!$profile) {
      return [
        'display_name' => $this->resolveEntityName($entity_id, $game_state, []),
        'attitude' => 'indifferent',
        'personality_traits' => '',
        'personality_axes' => $this->normalizeDecisionPersonalityAxes([]),
        'motivations' => '',
        'fears' => '',
        'bonds' => '',
        'goals' => $this->resolveActorGoals(NULL),
        'latest_thought' => NULL,
      ];
    }

    $latest_thought = NULL;
    $monologue = $profile['inner_monologue'] ?? [];
    if (!empty($monologue) && is_array($monologue)) {
      $last = end($monologue);
      if (is_array($last)) {
        $latest_thought = [
          'thought' => (string) ($last['thought'] ?? ''),
          'emotion' => (string) ($last['emotion'] ?? ''),
          'event_type' => (string) ($last['event_type'] ?? ''),
        ];
      }
    }

    return [
      'display_name' => (string) ($profile['display_name'] ?? $entity_id),
      'attitude' => $this->normalizeNpcAttitude((string) ($profile['attitude'] ?? 'indifferent')) ?? 'indifferent',
      'personality_traits' => (string) ($profile['personality_traits'] ?? ''),
      'personality_axes' => $this->normalizeDecisionPersonalityAxes(is_array($profile['personality_axes'] ?? NULL) ? $profile['personality_axes'] : []),
      'motivations' => (string) ($profile['motivations'] ?? ''),
      'fears' => (string) ($profile['fears'] ?? ''),
      'bonds' => (string) ($profile['bonds'] ?? ''),
      'goals' => $this->resolveActorGoals($profile),
      'latest_thought' => $latest_thought,
    ];
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
    $campaign_id = $game_state['campaign_id'] ?? 0;
    if (!$campaign_id) {
      return '';
    }

    $profile = $this->loadCombatantPsychologyProfile($entity_id, $game_state, (int) $campaign_id);
    if (!$profile) {
      return '';
    }

    $parts = [];
    $parts[] = "=== NPC COMBAT PERSONALITY ===";
    $parts[] = "Name: {$profile['display_name']}";
    $attitude = $this->normalizeNpcAttitude((string) ($profile['attitude'] ?? 'indifferent')) ?? 'indifferent';
    $parts[] = "Attitude toward party: {$attitude}";

    if (!empty($profile['personality_traits'])) {
      $parts[] = "Personality: {$profile['personality_traits']}";
    }
    if (!empty($profile['motivations'])) {
      $parts[] = "Fighting motivation: {$profile['motivations']}";
    }
    $goals = $this->resolveActorGoals($profile);
    if (!empty($goals)) {
      $parts[] = "Goals: " . implode(', ', $goals);
    }

    // Translate personality axes into combat behavioral hints.
    $axes = $this->normalizeDecisionPersonalityAxes(is_array($profile['personality_axes'] ?? NULL) ? $profile['personality_axes'] : []);
    $hints = [];
    $boldness = $axes['boldness'] ?? 5;
    if ($boldness <= 3) {
      $hints[] = 'Will try to flee or surrender if below 25% HP';
    }
    elseif ($boldness >= 8) {
      $hints[] = 'Fights recklessly to the death, never retreats';
    }

    $discipline = $axes['discipline'] ?? 5;
    if ($discipline >= 7) {
      $hints[] = 'Coordinates with allies, focuses fire on wounded targets';
    }
    elseif ($discipline <= 3) {
      $hints[] = 'Fights chaotically, may switch targets randomly';
    }

    $cunning = $axes['cunning'] ?? 5;
    if ($cunning >= 7) {
      $hints[] = 'Targets the weakest or most dangerous PC strategically';
    }

    $empathy = $axes['empathy'] ?? 5;
    if ($empathy >= 7 && in_array($attitude, ['friendly', 'helpful'], TRUE)) {
      $hints[] = 'May refuse to fight, or try to end combat through diplomacy';
    }

    if ($hints) {
      $parts[] = "Combat behavior: " . implode('; ', $hints);
    }

    // Recent relevant thoughts.
    $monologue = $profile['inner_monologue'] ?? [];
    if ($monologue) {
      $last = end($monologue);
      if ($last && !empty($last['thought'])) {
        $parts[] = "Current mindset: \"{$last['thought']}\" (feeling {$last['emotion']})";
      }
    }

    return implode("\n", $parts);
  }

  /**
   * Resolve combatant entity_ref from initiative context.
   */
  protected function resolveCombatantEntityRef(string $entity_id, array $game_state): string {
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return (string) ($combatant['entity_ref'] ?? $combatant['entity_id'] ?? $entity_id);
      }
    }
    return $entity_id;
  }

  /**
   * Load psychology profile for a combatant using entity_ref first, then entity_id.
   */
  protected function loadCombatantPsychologyProfile(string $entity_id, array $game_state, int $campaign_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $entity_ref = $this->resolveCombatantEntityRef($entity_id, $game_state);
    $profile = $this->psychologyService->loadProfile($campaign_id, $entity_ref);
    if (!$profile && $entity_ref !== $entity_id) {
      $profile = $this->psychologyService->loadProfile($campaign_id, $entity_id);
    }
    return $profile ?: NULL;
  }

  /**
   * Resolve actor goals list, always including XP and treasure goals.
   */
  protected function resolveActorGoals(?array $profile): array {
    $goals = [];
    if (is_array($profile)) {
      $sheet = is_array($profile['character_sheet'] ?? NULL) ? $profile['character_sheet'] : [];
      if (is_array($sheet['goals'] ?? NULL)) {
        foreach ($sheet['goals'] as $goal) {
          if (is_string($goal) && trim($goal) !== '') {
            $goals[] = trim($goal);
          }
        }
      }
      $motivations = trim((string) ($profile['motivations'] ?? ''));
      if ($motivations !== '') {
        $motivation_goals = preg_split('/[;\n\r]+/', $motivations) ?: [];
        foreach ($motivation_goals as $motivation_goal) {
          $trimmed = trim((string) $motivation_goal);
          if ($trimmed !== '') {
            $goals[] = $trimmed;
          }
        }
      }
    }

    $goals[] = 'Gain XP';
    $goals[] = 'Gain Treasure';

    $seen = [];
    $normalized = [];
    foreach ($goals as $goal) {
      $key = strtolower($goal);
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $normalized[] = $goal;
    }
    return $normalized;
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
