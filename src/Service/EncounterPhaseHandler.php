<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handles game actions during the Encounter (combat) phase.
 *
 * Wraps the existing CombatEngine, ActionProcessor, and related services.
 * Enforces PF2e encounter rules: initiative, turn order, 3-action economy,
 * MAP, degree of success, conditions, HP tracking.
 *
 * Also handles NPC auto-play by delegating to EncounterAiIntegrationService.
 */
class EncounterPhaseHandler implements PhaseHandlerInterface {

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
   * @var \Drupal\dungeoncrawler_content\Service\ActionProcessor
   */
  protected ActionProcessor $actionProcessor;

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
   * @var \Drupal\dungeoncrawler_content\Service\RulesEngine
   */
  protected RulesEngine $rulesEngine;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

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

  /**
   * @var \Drupal\dungeoncrawler_content\Service\SpellCatalogService
   */
  protected SpellCatalogService $spellCatalog;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CombatEngine $combat_engine,
    ActionProcessor $action_processor,
    CombatEncounterStore $encounter_store,
    HPManager $hp_manager,
    ConditionManager $condition_manager,
    CombatCalculator $combat_calculator,
    NumberGenerationService $number_generation_service,
    EncounterAiIntegrationService $encounter_ai_service,
    RulesEngine $rules_engine,
    EventDispatcherInterface $event_dispatcher,
    AiGmService $ai_gm_service,
    ConfigFactoryInterface $config_factory,
    NpcPsychologyService $psychology_service = NULL,
    ?NarrationEngine $narration_engine = NULL,
    ?MovementResolverService $movement_resolver = NULL,
    ?HazardService $hazard_service = NULL,
    ?MagicItemService $magic_item_service = NULL,
    ?SpellCatalogService $spell_catalog = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->combatEngine = $combat_engine;
    $this->actionProcessor = $action_processor;
    $this->encounterStore = $encounter_store;
    $this->hpManager = $hp_manager;
    $this->conditionManager = $condition_manager;
    $this->combatCalculator = $combat_calculator;
    $this->numberGenerationService = $number_generation_service;
    $this->encounterAiService = $encounter_ai_service;
    $this->rulesEngine = $rules_engine;
    $this->eventDispatcher = $event_dispatcher;
    $this->aiGmService = $ai_gm_service;
    $this->configFactory = $config_factory;
    $this->psychologyService = $psychology_service ?? new NpcPsychologyService($database, $logger_factory);
    $this->narrationEngine = $narration_engine;
    $this->movementResolver = $movement_resolver;
    $this->hazardService = $hazard_service ?? new HazardService($number_generation_service);
    $this->magicItemService = $magic_item_service ?? new MagicItemService($number_generation_service);
    $this->spellCatalog = $spell_catalog ?? new SpellCatalogService();
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
      'talk',
      'end_turn',
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

    $encounter_id = $game_state['encounter_id'] ?? NULL;
    if (!$encounter_id) {
      return [
        'valid' => FALSE,
        'reason' => 'No active encounter.',
      ];
    }

    // Validate it's the actor's turn (except for reactions and talk).
    if (!in_array($type, ['reaction', 'talk'])) {
      $actor_id = $intent['actor'] ?? NULL;
      $current_turn = $game_state['turn'] ?? [];
      $current_entity = $current_turn['entity'] ?? NULL;

      if ($actor_id && $current_entity && $actor_id !== $current_entity) {
        return [
          'valid' => FALSE,
          'reason' => "It is not $actor_id's turn. Current turn: $current_entity.",
        ];
      }
    }

    // Validate action economy.
    if (in_array($type, ['strike', 'stride', 'cast_spell', 'interact'])) {
      $actions_remaining = $game_state['turn']['actions_remaining'] ?? 0;
      $action_cost = $this->getActionCost($type, $intent['params'] ?? []);
      if ($actions_remaining < $action_cost) {
        return [
          'valid' => FALSE,
          'reason' => "Not enough actions remaining ($actions_remaining) for $type (costs $action_cost).",
        ];
      }
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
    $encounter_id = $game_state['encounter_id'] ?? NULL;

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;

    // dc-cr-spells-ch07: Metamagic state machine — if a metamagic was declared
    // this turn and the next action is NOT cast_spell, the metamagic is wasted.
    if ($type !== 'cast_spell' && $type !== 'declare_metamagic' &&
        !empty($game_state['turn']['metamagic_pending'][$actor_id])) {
      unset($game_state['turn']['metamagic_pending'][$actor_id]);
    }

    switch ($type) {

      case 'strike':
        $result = $this->processStrike($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data);
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
          'speaker_ref' => $actor_id,
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
        // Also queue mechanical damage event if hit.
        if ($damage_val > 0) {
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'damage_applied',
            'speaker' => 'System',
            'speaker_type' => 'system',
            'speaker_ref' => '',
            'content' => sprintf('%s takes %d damage.', $target_name, $damage_val),
            'mechanical_data' => [
              'target' => $target_id,
              'damage' => $damage_val,
              'damage_type' => $result['damage_type'] ?? 'physical',
            ],
            'visibility' => 'public',
          ]);
        }

        // Check for encounter end (all enemies defeated).
        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
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

        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
        break;

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
        // Talk is a free action in encounter mode.
        $message = $params['message'] ?? '';
        $result = [
          'talked' => TRUE,
          'message' => $message,
        ];
        $events[] = GameEventLogger::buildEvent('talk', 'encounter', $actor_id, [
          'message' => $message,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);

        // Queue talk as speech event for immediate narration.
        $talker_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'speech',
          'speaker' => $talker_name,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => $message,
          'language' => $params['language'] ?? 'Common',
          'volume' => $params['volume'] ?? 'normal',
          'visibility' => 'public',
        ]);
        break;

      case 'end_turn':
        $result = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;

        $events[] = GameEventLogger::buildEvent('end_turn', 'encounter', $actor_id, [
          'round' => $game_state['round'] ?? NULL,
          'turn_index' => $game_state['turn']['index'] ?? NULL,
        ], $narration);

        // End turn may trigger NPC auto-play, which generates additional events.
        if (!empty($result['npc_events'])) {
          $events = array_merge($events, $result['npc_events']);
        }

        // If round changed, add round event.
        if (!empty($result['new_round'])) {
          // AI GM narration for new round.
          $round_narration = $this->aiGmService->narrateRoundStart(
            (int) $result['new_round'],
            $game_state,
            $dungeon_data,
            $campaign_id
          );

          $events[] = GameEventLogger::buildEvent('round_start', 'encounter', NULL, [
            'round' => $result['new_round'],
          ], $round_narration);

          // Queue round start for perception-filtered narration.
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'action',
            'speaker' => 'GM',
            'speaker_type' => 'gm',
            'speaker_ref' => '',
            'content' => sprintf('Round %d begins.', (int) $result['new_round']),
            'visibility' => 'public',
            'mechanical_data' => ['round' => (int) $result['new_round']],
          ]);
        }

        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
        break;

      case 'delay':
        // REQ 2193-2195: Store remaining actions, set delayed flag.
        $delay_remaining = $game_state['turn']['actions_remaining'] ?? 0;
        $game_state['turn']['delayed'] = TRUE;
        $game_state['turn']['delayed_actions_remaining'] = $delay_remaining;
        $game_state['turn']['actions_remaining'] = 0;
        $result = ['delayed' => TRUE, 'remaining_actions' => $delay_remaining];
        $events[] = GameEventLogger::buildEvent('delay', 'encounter', $actor_id, [
          'remaining_actions' => $delay_remaining,
          'round' => $game_state['round'] ?? NULL,
        ]);
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

    // Check for auto-end-turn (actions depleted + no movement remaining).
    // Delay is intentional initiative exit — do NOT auto-end-turn for it.
    $no_auto_end_types = ['end_turn', 'delay', 'delay_reenter', 'release', 'aid'];
    if (!in_array($type, $no_auto_end_types, TRUE) && $this->shouldAutoEndTurn($game_state)) {
      $auto_end = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
      $events[] = GameEventLogger::buildEvent('auto_end_turn', 'encounter', $actor_id, [
        'round' => $game_state['round'] ?? NULL,
      ]);
      if (!empty($auto_end['npc_events'])) {
        $events = array_merge($events, $auto_end['npc_events']);
      }
      if (!$phase_transition) {
        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
      }
    }

    return [
      'success' => TRUE,
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'phase_transition' => $phase_transition,
      'narration' => $narration,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onEnter(array $context, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $game_state['phase'] = 'encounter';
    $events = [];

    // Create the encounter via CombatEngine.
    $encounter_context = $context['encounter_context'] ?? [];
    $room_id = $encounter_context['room_id'] ?? ($dungeon_data['active_room_id'] ?? NULL);
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
        }

        $game_state['initiative_order'] = $initiative_order;

        $events[] = GameEventLogger::buildEvent('encounter_started', 'encounter', NULL, [
          'encounter_id' => $encounter_id,
          'room_id' => $room_id,
          'participants' => count($participants),
          'initiative_order' => $initiative_order,
        ]);

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

        // Mark the room's encounter as triggered.
        $this->markRoomEncounterTriggered($dungeon_data, $room_id);
      }
    }
    catch (\Exception $e) {
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
          'phase transition to exploration'
        );
      }
      catch (\Exception $e) {
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
    $actions = [];
    $turn = $game_state['turn'] ?? [];
    $current_entity = $turn['entity'] ?? NULL;
    $actions_remaining = $turn['actions_remaining'] ?? 0;
    $reaction_available = $turn['reaction_available'] ?? FALSE;
    $actor_heritage = $this->resolveActorHeritage($actor_id, $dungeon_data);

    // If it's the actor's turn.
    if ($actor_id && $actor_id === $current_entity) {
      if ($actions_remaining >= 1) {
        $actions[] = 'strike';
        $actions[] = 'stride';
        $actions[] = 'interact';
        if ($actor_heritage === 'chameleon') {
          $actions[] = 'minor_color_shift';
        }
      }
      if ($actions_remaining >= 2) {
        $actions[] = 'cast_spell';
      }
      $actions[] = 'talk'; // Always free.
      $actions[] = 'end_turn';
      $actions[] = 'delay';
    }

    if ($reaction_available) {
      $actions[] = 'reaction';
    }

    return $actions;
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

  // =========================================================================
  // Action processors.
  // =========================================================================

  /**
   * Processes a strike action via the existing combat system.
   */
  protected function processStrike(int $encounter_id, string $actor_id, string $target_id, array $params, array &$game_state, array $dungeon_data = []): array {
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

      $weapon = is_array($params['weapon'] ?? NULL) ? $params['weapon'] : [];
      $weapon += [
        'attack_bonus' => (int) ($params['attack_bonus'] ?? 100),
        'damage_dice' => (string) ($params['damage_dice'] ?? '1d8+50'),
        'damage_type' => (string) ($params['damage_type'] ?? 'physical'),
        'is_agile' => !empty($params['is_agile']),
      ];
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
    catch (\Exception $e) {
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

    // Validate movement cost vs speed if MovementResolverService is available.
    if ($this->movementResolver && !$is_forced) {
      // Load participant for speed lookup.
      $enc = $this->encounterStore->loadEncounter($encounter_id);
      $ptcp = $enc ? $this->findEncounterParticipantByEntityId($enc, $actor_id) : NULL;

      if ($ptcp) {
        $speed = $this->movementResolver->getCreatureSpeed($ptcp, $movement_type);
        if ($speed <= 0) {
          return ['error' => "No {$movement_type} speed.", 'mutations' => []];
        }

        // Derive from_hex from participant's current position.
        $from_q = (int) ($ptcp['position_q'] ?? 0);
        $from_r = (int) ($ptcp['position_r'] ?? 0);
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
      $this->encounterStore->updateParticipant($encounter_id, $actor_id, [
        'position_q' => (int) $to_hex['q'],
        'position_r' => (int) $to_hex['r'],
      ]);
    }
    catch (\Exception $e) {
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

    // AC-002: Tradition validation (only if both tradition values are present).
    $char_tradition = $edata_cs['spellcasting_tradition'] ?? NULL;
    if ($spell_tradition && $char_tradition && $spell_tradition !== $char_tradition) {
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
      $slots_cs = $edata_cs['spell_slots'] ?? [];
      $effective_level = 1;
      if (!empty($slots_cs)) {
        $effective_level = max(array_keys(array_filter($slots_cs, function ($s) {
          return (int) ($s['max'] ?? 0) > 0;
        })) ?: [1]);
      }
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
      $fp_cs = (int) ($edata_cs['focus_points'] ?? $edata_cs['state']['focus_points'] ?? 0);
      if ($fp_cs < 1) {
        return ['cast' => FALSE, 'error' => 'No Focus Points remaining.', 'mutations' => [], 'narration' => NULL];
      }
      if (isset($edata_cs['focus_points'])) {
        $edata_cs['focus_points'] = $fp_cs - 1;
      }
      else {
        $edata_cs['state']['focus_points'] = $fp_cs - 1;
      }
      $this->encounterStore->updateParticipant((int) $ptcp_cs['id'], ['entity_ref' => json_encode($edata_cs)]);
      return [
        'cast' => TRUE,
        'spell' => $spell_name,
        'is_focus_spell' => TRUE,
        'focus_points_remaining' => $fp_cs - 1,
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
    if ($slots_avail < 1) {
      return ['cast' => FALSE, 'error' => "No level-{$slot_level} spell slots remaining.", 'mutations' => [], 'narration' => NULL];
    }

    // AC-003: Prepared casters must have the spell prepared in that slot level.
    $casting_type = $edata_cs['casting_type'] ?? 'spontaneous';
    if ($casting_type === 'prepared') {
      $prepared_cs = $edata_cs['prepared_spells'][$slot_key] ?? [];
      if (!in_array($spell_name, $prepared_cs, TRUE)) {
        return ['cast' => FALSE, 'error' => "'{$spell_name}' is not prepared in a level-{$slot_level} slot.", 'mutations' => [], 'narration' => NULL];
      }
    }

    // Deduct slot.
    $edata_cs['spell_slots'][$slot_key]['used'] = (int) ($slot_data_cs['used'] ?? 0) + 1;
    $this->encounterStore->updateParticipant((int) $ptcp_cs['id'], ['entity_ref' => json_encode($edata_cs)]);

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
      'slots_remaining' => $slots_avail - 1,
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
   * Processes end-of-turn: advance to next combatant, auto-play NPCs.
   */
  protected function processEndTurn(int $encounter_id, ?string $actor_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $initiative_order = $game_state['initiative_order'] ?? [];
    $current_index = $game_state['turn']['index'] ?? 0;
    $npc_events = [];
    $new_round = NULL;

    // Tick end-of-turn conditions for the current combatant.
    if ($encounter_id && $actor_id) {
      try {
        $this->conditionManager->tickConditions($encounter_id, $actor_id);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Condition tick failed: @error', ['@error' => $e->getMessage()]);
      }
    }

    // REQ 2222: Airborne entity that did NOT use a Fly action this turn begins falling.
    if ($actor_id) {
      try {
        $enc_fly_check = $this->encounterStore->loadEncounter($encounter_id);
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

    // Update game_state turn.
    $game_state['turn'] = [
      'entity' => $next_entity,
      'index' => $next_index,
      'actions_remaining' => 3,
      'attacks_this_turn' => 0,
      'reaction_available' => TRUE,
      'delayed' => FALSE,
    ];

    // Update the encounter store.
    try {
      $this->encounterStore->updateEncounter($encounter_id, [
        'turn_index' => $next_index,
        'current_round' => $game_state['round'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('Encounter store update failed: @error', ['@error' => $e->getMessage()]);
    }

    // If next combatant is NPC/enemy, auto-play their turn.
    if ($next_team !== 'player') {
      $npc_result = $this->autoPlayNpcTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id);
      $npc_events = $npc_result['events'] ?? [];

      // After NPC turn, recursively advance (NPC might be followed by another NPC).
      if (!$this->isEncounterOver($encounter_id, $game_state)) {
        $further = $this->processEndTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id);
        $npc_events = array_merge($npc_events, $further['npc_events'] ?? []);
        if (!$new_round && !empty($further['new_round'])) {
          $new_round = $further['new_round'];
        }
      }
    }

    return [
      'turn_advanced' => TRUE,
      'next_entity' => $next_entity,
      'next_team' => $next_team,
      'round' => $game_state['round'],
      'new_round' => $new_round,
      'npc_events' => $npc_events,
      'mutations' => [],
    ];
  }

  // =========================================================================
  // NPC Auto-play.
  // =========================================================================

  /**
   * Auto-plays a non-player combatant's turn using AI or fallback logic.
   */
  protected function autoPlayNpcTurn(int $encounter_id, string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $events = [];

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

    $action_type = NULL;
    $target = NULL;
    $narration = NULL;

    if ($ai_enabled) {
      try {
        $result = $this->encounterAiService->requestNpcActionRecommendation($context);

        if (!empty($result['success']) && !empty($result['recommendation'])) {
          $rec = $result['recommendation'];
          $action = $rec['recommended_action'] ?? [];
          $valid = $result['validation']['valid'] ?? FALSE;

          if ($valid) {
            $action_type = $action['type'] ?? NULL;
            $target = $action['target_instance_id'] ?? ($action['target'] ?? NULL);
            $narration = $rec['narration'] ?? NULL;
          }
          else {
            $this->logger->info('NPC AI recommendation invalid, using fallback. Errors: @errors', [
              '@errors' => implode('; ', $result['validation']['errors'] ?? []),
            ]);
          }
        }
      }
      catch (\Exception $e) {
        $this->logger->warning('NPC AI failed, using fallback: @error', ['@error' => $e->getMessage()]);
      }
    }

    // Fallback: choose a sensible action without AI.
    if ($action_type === NULL) {
      $action_type = $this->chooseFallbackAction($entity_id, $game_state);
      $target = ($action_type === 'strike')
        ? $this->findNearestAlivePlayer($entity_id, $game_state)
        : NULL;
    }

    // Execute the chosen action.
    switch ($action_type) {
      case 'strike':
        if ($target) {
          $strike_result = $this->processStrike($encounter_id, $entity_id, $target, [], $game_state);
          $events[] = GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [
            'target' => $target,
            'roll' => $strike_result['roll'] ?? NULL,
            'degree' => $strike_result['degree'] ?? NULL,
            'damage' => $strike_result['damage'] ?? NULL,
          ], $narration, $target);

          // Check for entity defeat after strike.
          $this->checkEntityDefeated($target, $entity_id, $game_state, $events, $dungeon_data, $campaign_id);
        }
        break;

      case 'stride':
        // Move toward the nearest player.
        $nearest = $this->findNearestAlivePlayer($entity_id, $game_state);
        $events[] = GameEventLogger::buildEvent('npc_stride', 'encounter', $entity_id, [
          'toward' => $nearest,
        ], $narration);
        break;

      case 'interact':
        $events[] = GameEventLogger::buildEvent('npc_interact', 'encounter', $entity_id, [
          'interaction' => 'raise_shield',
        ], $narration);
        break;

      case 'talk':
        $events[] = GameEventLogger::buildEvent('npc_talk', 'encounter', $entity_id, [
          'message' => $narration ?? 'The creature snarls at you.',
        ], $narration);
        break;

      default:
        // Unknown action — default to strike.
        $target = $target ?? $this->findFirstAlivePlayer($game_state);
        if ($target) {
          $strike_result = $this->processStrike($encounter_id, $entity_id, $target, [], $game_state);
          $events[] = GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [
            'target' => $target,
            'roll' => $strike_result['roll'] ?? NULL,
            'degree' => $strike_result['degree'] ?? NULL,
            'damage' => $strike_result['damage'] ?? NULL,
            'fallback' => TRUE,
          ], NULL, $target);

          // Check for entity defeat after fallback strike.
          $this->checkEntityDefeated($target, $entity_id, $game_state, $events, $dungeon_data, $campaign_id);
        }
        break;
    }

    return ['events' => $events];
  }

  /**
   * Choose a fallback action for NPC without AI.
   *
   * Basic tactical heuristic: if adjacent to player → strike; otherwise → stride.
   */
  protected function chooseFallbackAction(string $entity_id, array $game_state): string {
    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return 'strike';
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);

    // Check if any alive player is adjacent (distance = 1 hex).
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['team'] ?? '') !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $dist = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($dist <= 1) {
        return 'strike';
      }
    }

    return 'stride';
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
   * Checks if the encounter should end (all enemies defeated or all players defeated).
   */
  protected function checkEncounterEnd(int $encounter_id, array &$game_state): ?array {
    if ($this->isEncounterOver($encounter_id, $game_state)) {
      return [
        'from' => 'encounter',
        'to' => 'exploration',
        'reason' => 'All enemies have been defeated!',
        'encounter_result' => [
          'encounter_id' => $encounter_id,
          'final_round' => $game_state['round'] ?? NULL,
          'victory' => TRUE,
        ],
      ];
    }

    return NULL;
  }

  /**
   * Determines if the encounter is over.
   */
  protected function isEncounterOver(int $encounter_id, array $game_state): bool {
    $initiative_order = $game_state['initiative_order'] ?? [];
    $teams_alive = [];

    foreach ($initiative_order as $combatant) {
      if (empty($combatant['is_defeated'])) {
        $team = $combatant['team'] ?? 'enemy';
        $teams_alive[$team] = TRUE;
      }
    }

    // Encounter is over if only one team (or zero) remains.
    return count($teams_alive) <= 1;
  }

  /**
   * Builds participant list from dungeon entities for encounter creation.
   */
  protected function buildParticipantList(array $dungeon_data, string $room_id, array $enemies = []): array {
    $participants = [];
    $entities = $dungeon_data['entities'] ?? [];

    foreach ($entities as $entity) {
      $entity_room = $entity['placement']['room_id'] ?? NULL;
      if ($entity_room !== $room_id) {
        continue;
      }

      $content_type = $entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? '');
      $instance_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));

      if ($content_type === 'player_character') {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
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
      elseif ($content_type === 'creature' || $content_type === 'npc' || in_array($instance_id, array_column($enemies, 'entity_instance_id'))) {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
          ]),
          'team' => 'enemy',
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
      'participants' => $initiative_order,
      'allies' => $allies,
      'threats' => $enemies,
      'allowed_actions' => [
        'strike', 'stride', 'interact', 'talk', 'end_turn',
      ],
      // NPC personality/psychology context for AI decision-making.
      'npc_psychology' => $this->buildNpcPsychologyContext($entity_id, $game_state),
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

    // entity_id might be "entity_creature_2_1", entity_ref is the content_id like "goblin_warrior_1"
    // Try to find the entity's content_id from the initiative_order or use entity_id directly.
    $entity_ref = $entity_id;
    foreach ($game_state['initiative_order'] ?? [] as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        $entity_ref = $combatant['entity_ref'] ?? $combatant['entity_id'] ?? $entity_id;
        break;
      }
    }

    $profile = $this->psychologyService->loadProfile($campaign_id, $entity_ref);
    if (!$profile && $entity_ref !== $entity_id) {
      // Fall back to entity_id as ref.
      $profile = $this->psychologyService->loadProfile($campaign_id, $entity_id);
    }

    if (!$profile) {
      return '';
    }

    $parts = [];
    $parts[] = "=== NPC COMBAT PERSONALITY ===";
    $parts[] = "Name: {$profile['display_name']}";
    $parts[] = "Attitude toward party: {$profile['attitude']}";

    if (!empty($profile['personality_traits'])) {
      $parts[] = "Personality: {$profile['personality_traits']}";
    }
    if (!empty($profile['motivations'])) {
      $parts[] = "Fighting motivation: {$profile['motivations']}";
    }

    // Translate personality axes into combat behavioral hints.
    $axes = $profile['personality_axes'] ?? [];
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
    if ($empathy >= 7 && in_array($profile['attitude'], ['friendly', 'helpful'])) {
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
  protected function queueNarrationEvent(int $campaign_id, array $dungeon_data, array $event, ?string $room_id = NULL): array {
    if (!$this->narrationEngine) {
      return [];
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
    catch (\Exception $e) {
      $this->logger->warning('NarrationEngine queue failed: @err', ['@err' => $e->getMessage()]);
      return [];
    }
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
