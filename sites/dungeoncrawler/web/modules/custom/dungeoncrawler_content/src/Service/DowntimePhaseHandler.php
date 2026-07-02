<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles game actions during the Downtime phase.
 *
 * Downtime is the between-adventure phase where characters perform long-duration
 * activities: crafting items, earning income, retraining feats/skills, and
 * recovering from conditions.
 *
 * Implemented actions: long_rest, downtime_rest, craft, earn_income, retrain,
 * advance_day, talk, return_to_exploration, assign_watch, advance_starvation.
 */
class DowntimePhaseHandler implements PhaseHandlerInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CharacterStateService
   */
  protected CharacterStateService $characterStateService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CraftingService
   */
  protected CraftingService $craftingService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NpcPsychologyService
   */
  protected NpcPsychologyService $npcPsychology;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\MagicItemService
   */
  protected MagicItemService $magicItemService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGenerationService;

  /**
   * Constructs a DowntimePhaseHandler.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CharacterStateService $character_state_service,
    CraftingService $crafting_service,
    NpcPsychologyService $npc_psychology,
    ?MagicItemService $magic_item_service = NULL,
    ?NumberGenerationService $number_generation_service = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->characterStateService = $character_state_service;
    $this->craftingService = $crafting_service;
    $this->npcPsychology = $npc_psychology;
    $this->numberGenerationService = $number_generation_service ?? new NumberGenerationService();
    $this->magicItemService = $magic_item_service ?? new MagicItemService($this->numberGenerationService);
  }

  /**
   * {@inheritdoc}
   */
  public function getPhaseName(): string {
    return 'downtime';
  }

  /**
   * {@inheritdoc}
   */
  public function getLegalIntents(): array {
    return [
      'long_rest',
      'downtime_rest',
      'craft',
      'earn_income',
      'retrain',
      'advance_day',
      'talk',
      'return_to_exploration',
      // REQ 1669–1677: Diplomacy downtime actions.
      'gather_information',
      'make_impression',
      // REQ 1678–1683: Intimidation downtime action.
      'coerce',
      // REQ 1705–1708: Performance Earn Income (downtime).
      'perform',
      // AC-005: Subsist, Treat Disease, Run Business.
      'subsist',
      'treat_disease',
      'run_business',
      // REQ 2397–2409: Magic item investment during downtime.
      'invest_item',
      // REQ 2501–2510: Prepare staff at daily preparations.
      'prepare_staff',
      // REQ 2455–2467: Etch rune (downtime Craft activity).
      'etch_rune',
      // REQ 2468–2473: Transfer rune (downtime Craft activity).
      'transfer_rune',
      // REQ 2536–2545: Craft and place snare (1-minute craft).
      'craft_snare',
      // REQ 2397: Daily Preparations — reset magic item state.
      'daily_preparations',
      // REQ 2346: Watch assignment during rest.
      'assign_watch',
      // REQ 2347–2349: Starvation and thirst advancement.
      'advance_starvation',
      // REQ 1731–1736: Create a Forgery [Downtime, Secret, Trained Society].
      'create_forgery',
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
        'reason' => "Action '$type' is not legal during downtime phase.",
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
    $params = $intent['params'] ?? [];

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;

    switch ($type) {

      case 'long_rest':
        $result = $this->processLongRest($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('long_rest', 'downtime', $actor_id, [
          'hp_restored' => $result['hp_restored'] ?? 0,
          'conditions_removed' => $result['conditions_removed'] ?? [],
          'spell_slots_restored' => $result['spell_slots_restored'] ?? FALSE,
        ]);
        break;

      case 'return_to_exploration':
        $phase_transition = [
          'from' => 'downtime',
          'to' => 'exploration',
          'reason' => 'Returning to adventure.',
        ];
        $events[] = GameEventLogger::buildEvent('downtime_ended', 'downtime', $actor_id, []);
        break;

      case 'downtime_rest':
        // REQ 2306: Long-term rest during downtime (8 hours recovers Con×2×level HP).
        $result = $this->processDowntimeRest($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('downtime_rest', 'downtime', $actor_id, [
          'hp_restored' => $result['hp_restored'] ?? 0,
        ]);
        break;

      case 'retrain':
        // REQ 2307-2310: Retrain a feat, skill, or class choice.
        $result = $this->processRetrain($actor_id, $params, $game_state);
        $events[] = GameEventLogger::buildEvent('retrain', 'downtime', $actor_id, [
          'retrain_type' => $params['retrain_type'] ?? NULL,
          'from' => $params['retrain_from'] ?? NULL,
          'to' => $params['retrain_to'] ?? NULL,
        ]);
        break;

      case 'advance_day':
        $result = $this->processAdvanceDay($actor_id, $game_state, $dungeon_data);
        $events[] = GameEventLogger::buildEvent('advance_day', 'downtime', $actor_id, [
          'days_elapsed' => $result['days_elapsed'] ?? NULL,
          'retrain_completed' => $result['retrain_completed'] ?? NULL,
        ]);
        break;

      case 'craft':
        $result = $this->processCraft($actor_id, $params, $game_state, $campaign_id);
        $events[] = GameEventLogger::buildEvent($type, 'downtime', $actor_id, [
          'degree'       => $params['degree'] ?? NULL,
          'item_granted' => $result['item_granted'] ?? FALSE,
        ]);
        break;

      case 'earn_income':
        $result = $this->processEarnIncome($actor_id, $params, $game_state, $campaign_id);
        if (isset($result['success']) && $result['success'] === FALSE) {
          return [
            'success'          => FALSE,
            'result'           => $result,
            'mutations'        => [],
            'events'           => [],
            'phase_transition' => NULL,
            'narration'        => NULL,
          ];
        }
        $events[] = GameEventLogger::buildEvent($type, 'downtime', $actor_id, [
          'task_level'  => $params['task_level'] ?? NULL,
          'skill'       => $params['skill'] ?? NULL,
          'degree'      => $params['degree'] ?? NULL,
          'earned_cp'   => $result['earned_cp'] ?? 0,
        ]);
        break;

      case 'talk':
        $result = ['talked' => TRUE, 'message' => $params['message'] ?? ''];
        $events[] = GameEventLogger::buildEvent('talk', 'downtime', $actor_id, [
          'message' => $params['message'] ?? '',
        ]);
        break;

      // -----------------------------------------------------------------------
      // REQ 1669–1672: Gather Information [Downtime, ~2 hrs, Diplomacy]
      // Yields rumors/info about a topic. Crit Fail reveals investigation.
      // -----------------------------------------------------------------------
      case 'gather_information':
        $result = $this->processGatherInformation($actor_id, $params, $game_state);
        $events[] = GameEventLogger::buildEvent('gather_information', 'downtime', $actor_id, [
          'degree' => $params['degree'] ?? NULL,
          'topic'  => $params['topic'] ?? NULL,
        ]);
        break;

      // -----------------------------------------------------------------------
      // REQ 1673–1677: Make an Impression [Downtime, ~10 min, Diplomacy]
      // Shifts target NPC attitude. Degrees: Crit = +2 steps, Success = +1.
      // -----------------------------------------------------------------------
      case 'make_impression':
        $result = $this->processMakeImpression($actor_id, $params, $game_state, $campaign_id);
        $events[] = GameEventLogger::buildEvent('make_impression', 'downtime', $actor_id, [
          'degree' => $result['degree'] ?? NULL,
          'target' => $params['target_id'] ?? NULL,
        ]);
        break;

      // -----------------------------------------------------------------------
      // REQ 1678–1683: Coerce [Downtime, ~10 min, Intimidation]
      // Produces compliance; target becomes Unfriendly after 7 days.
      // -----------------------------------------------------------------------
      case 'coerce':
        $result = $this->processCoerce($actor_id, $params, $game_state, $campaign_id);
        $events[] = GameEventLogger::buildEvent('coerce', 'downtime', $actor_id, [
          'degree' => $result['degree'] ?? NULL,
          'target' => $params['target_id'] ?? NULL,
        ]);
        break;

      // REQ 1705–1708: Perform [Downtime — Earn Income via Performance]
      // Routes to earn_income logic with skill = 'performance'.
      // -----------------------------------------------------------------------
      case 'perform':
        $result = $this->processEarnIncome($actor_id, array_merge($params, ['skill' => 'performance']), $game_state, $campaign_id);
        $events[] = GameEventLogger::buildEvent('perform', 'downtime', $actor_id, [
          'task_level' => $params['task_level'] ?? NULL,
          'degree'     => $params['degree'] ?? NULL,
          'earned_cp'  => $result['earned_cp'] ?? 0,
        ]);
        break;

      // -----------------------------------------------------------------------
      // AC-005: Subsist [Downtime, 1 day, Survival or Society]
      // Check vs environment DC to cover living expenses.
      // -----------------------------------------------------------------------
      case 'subsist':
        $result = $this->processSubsist($actor_id, $params, $game_state);
        $events[] = GameEventLogger::buildEvent('subsist', 'downtime', $actor_id, [
          'skill'  => $params['skill'] ?? NULL,
          'degree' => $params['degree'] ?? NULL,
        ]);
        break;

      // -----------------------------------------------------------------------
      // REQ 1731–1736: Create a Forgery [Downtime, Secret, Trained Society]
      // Caller passes the degree; result is coarsened (secret roll convention).
      // -----------------------------------------------------------------------
      case 'create_forgery':
        $result = $this->processCreateForgery($actor_id, $params, $game_state);
        $events[] = GameEventLogger::buildEvent('create_forgery', 'downtime', $actor_id, [
          'document_type' => $params['document_type'] ?? NULL,
          'outcome'       => $result['outcome'] ?? NULL,
        ]);
        break;

      // -----------------------------------------------------------------------
      // AC-005: Treat Disease [Downtime, 1 day, Medicine]
      // Reduces disease stage on success.
      // -----------------------------------------------------------------------
      case 'treat_disease':
        $result = $this->processTreatDisease($actor_id, $params, $game_state);
        if (isset($result['success']) && $result['success'] === FALSE) {
          return [
            'success'          => FALSE,
            'result'           => $result,
            'mutations'        => [],
            'events'           => [],
            'phase_transition' => NULL,
            'narration'        => NULL,
          ];
        }
        $events[] = GameEventLogger::buildEvent('treat_disease', 'downtime', $actor_id, [
          'affliction_id' => $params['affliction_id'] ?? NULL,
          'degree'        => $params['degree'] ?? NULL,
        ]);
        break;

      // -----------------------------------------------------------------------
      // AC-005: Run Business / Crafts for Sale [Downtime]
      // Routes to earn_income logic with the provided skill and table.
      // -----------------------------------------------------------------------
      case 'run_business':
        $result = $this->processRunBusiness($actor_id, $params, $game_state, $campaign_id);
        if (isset($result['success']) && $result['success'] === FALSE) {
          return [
            'success'          => FALSE,
            'result'           => $result,
            'mutations'        => [],
            'events'           => [],
            'phase_transition' => NULL,
            'narration'        => NULL,
          ];
        }
        $events[] = GameEventLogger::buildEvent('run_business', 'downtime', $actor_id, [
          'skill'      => $params['skill'] ?? NULL,
          'task_level' => $params['task_level'] ?? NULL,
          'degree'     => $params['degree'] ?? NULL,
          'earned_cp'  => $result['earned_cp'] ?? 0,
        ]);
        break;

      // -----------------------------------------------------------------------
      // REQ 2397–2409: Invest a magic item (daily preparations context).
      // -----------------------------------------------------------------------
      case 'invest_item': {
        $item_id_dt   = $params['item_instance_id'] ?? NULL;
        $item_data_dt = $params['item_data'] ?? [];
        if (!$item_id_dt) {
          return ['success' => FALSE, 'result' => ['error' => 'invest_item requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $result = $this->magicItemService->investItem($actor_id, $item_id_dt, $item_data_dt, $game_state);
        $events[] = GameEventLogger::buildEvent('invest_item', 'downtime', $actor_id, ['item_instance_id' => $item_id_dt, 'success' => $result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2501–2510: Prepare a staff (daily preparations).
      // -----------------------------------------------------------------------
      case 'prepare_staff': {
        $staff_id_dt   = $params['staff_instance_id'] ?? NULL;
        $staff_data_dt = $params['staff_data'] ?? [];
        if (!$staff_id_dt) {
          return ['success' => FALSE, 'result' => ['error' => 'prepare_staff requires params.staff_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state_dt = $this->characterStateService->getState($actor_id);
        $result = $this->magicItemService->prepareStaff($staff_id_dt, $actor_id, $char_state_dt, $game_state);
        $events[] = GameEventLogger::buildEvent('prepare_staff', 'downtime', $actor_id, ['staff_instance_id' => $staff_id_dt, 'success' => $result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2455–2467: Etch a rune onto an item (downtime Craft activity).
      // -----------------------------------------------------------------------
      case 'etch_rune': {
        $item_id_er   = $params['item_instance_id'] ?? NULL;
        $rune_type_er = $params['rune_type'] ?? NULL;
        $rune_data_er = $params['rune_data'] ?? [];
        $item_data_er = $params['item_data'] ?? [];
        if (!$item_id_er || !$rune_type_er) {
          return ['success' => FALSE, 'result' => ['error' => 'etch_rune requires params.item_instance_id and params.rune_type.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state_er = $this->characterStateService->getState($actor_id);
        $result = $this->magicItemService->etchRune($item_data_er, $rune_type_er, $rune_data_er, $char_state_er);
        $events[] = GameEventLogger::buildEvent('etch_rune', 'downtime', $actor_id, ['item_instance_id' => $item_id_er, 'rune_type' => $rune_type_er, 'success' => $result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2468–2473: Transfer a rune between two items (downtime Craft).
      // -----------------------------------------------------------------------
      case 'transfer_rune': {
        $source_id_tr = $params['source_item_instance_id'] ?? NULL;
        $dest_id_tr   = $params['dest_item_instance_id'] ?? NULL;
        $rune_type_tr = $params['rune_type'] ?? NULL;
        $rune_data_tr = $params['rune_data'] ?? [];
        if (!$source_id_tr || !$dest_id_tr || !$rune_type_tr) {
          return ['success' => FALSE, 'result' => ['error' => 'transfer_rune requires source_item_instance_id, dest_item_instance_id, and rune_type.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $source_item_tr = $params['source_item_data'] ?? [];
        $dest_item_tr   = $params['dest_item_data'] ?? [];
        $char_state_tr  = $this->characterStateService->getState($actor_id);
        $craft_bonus_tr = (int) ($char_state_tr['skills']['crafting']['bonus'] ?? 0);
        $result = $this->magicItemService->transferRune($source_item_tr, $dest_item_tr, $rune_type_tr, $rune_data_tr, $craft_bonus_tr);
        $events[] = GameEventLogger::buildEvent('transfer_rune', 'downtime', $actor_id, ['source' => $source_id_tr, 'dest' => $dest_id_tr, 'rune_type' => $rune_type_tr, 'success' => $result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2536–2545: Craft and place a snare (1-minute quick craft).
      // -----------------------------------------------------------------------
      case 'craft_snare': {
        $snare_data_dt  = $params['snare_data'] ?? [];
        $location_id_dt = $params['location_id'] ?? 'unknown';
        $char_state_dt  = $this->characterStateService->getState($actor_id);
        $result = $this->magicItemService->craftSnare($snare_data_dt, $char_state_dt, $location_id_dt, $game_state);
        $events[] = GameEventLogger::buildEvent('craft_snare', 'downtime', $actor_id, ['location_id' => $location_id_dt, 'success' => $result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2397: Daily Preparations — reset investments, staff, wand state.
      // -----------------------------------------------------------------------
      case 'daily_preparations': {
        $char_ids_dp = $params['char_ids'] ?? [$actor_id];
        $prep_results = [];
        foreach ($char_ids_dp as $char_id_dp) {
          $char_data_dp   = $this->characterStateService->getState($char_id_dp);
          $owned_items_dp = $params['owned_item_instances'][$char_id_dp] ?? [];
          $this->magicItemService->performDailyPreparations($char_id_dp, $char_data_dp, $owned_items_dp, $game_state);
          $prep_results[$char_id_dp] = ['prepared' => TRUE];
        }
        $result = ['prepared' => $prep_results];
        $events[] = GameEventLogger::buildEvent('daily_preparations', 'downtime', $actor_id, ['chars' => array_keys($prep_results)]);
        break;
      }

      case 'assign_watch': {
        // REQ 2346: Assign watch rotations; track minimum watch segments.
        $result = $this->processAssignWatch($actor_id, $params, $game_state);
        $events[] = GameEventLogger::buildEvent('assign_watch', 'downtime', $actor_id, [
          'assignments' => $result['assignments'] ?? [],
          'hours_per_watch' => $result['hours_per_watch'] ?? NULL,
        ]);
        break;
      }

      case 'advance_starvation': {
        // REQ 2347–2349: Advance starvation/thirst tracking; apply conditions and damage.
        $result = $this->processAdvanceStarvation($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('advance_starvation', 'downtime', $actor_id, [
          'results' => $result['results'] ?? [],
        ]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ dc-cr-gnome-heritage-chameleon: Dramatic Color Shift [Downtime, ~1 hour]
      // Chameleon Gnome only. Updates coloration_tag on char state to match target terrain.
      // -----------------------------------------------------------------------
      case 'dramatic_color_shift': {
        if (($params['heritage'] ?? '') !== 'chameleon') {
          return ['success' => FALSE, 'result' => ['error' => 'Dramatic Color Shift requires Chameleon Gnome heritage.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $new_terrain_color = trim($params['target_terrain_color'] ?? '');
        if ($new_terrain_color === '') {
          return ['success' => FALSE, 'result' => ['error' => 'target_terrain_color is required.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $mutations[] = ['type' => 'char_state', 'key' => 'coloration_tag', 'value' => $new_terrain_color];
        $result = ['coloration_tag' => $new_terrain_color, 'duration' => 'up to 1 hour'];
        $events[] = GameEventLogger::buildEvent('dramatic_color_shift', 'downtime', $actor_id, ['new_coloration' => $new_terrain_color]);
        break;
      }

      default:
        return [
          'success' => FALSE,
          'result' => ['error' => "Unknown downtime action: $type"],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ];
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
    $game_state['phase'] = 'downtime';
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['encounter_id'] = NULL;

    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [
        'days_elapsed' => 0,
        'activities' => [],
      ];
    }

    return [
      GameEventLogger::buildEvent('phase_entered', 'downtime', NULL, [
        'from_phase' => $context['from_phase'] ?? 'none',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return [
      GameEventLogger::buildEvent('phase_exited', 'downtime', NULL, [
        'days_elapsed' => $game_state['downtime']['days_elapsed'] ?? 0,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableActions(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    $actions = ['long_rest', 'downtime_rest', 'earn_income', 'craft', 'craft_snare', 'subsist', 'talk', 'return_to_exploration'];
    if ($this->resolveActorHeritage($actor_id, $dungeon_data) === 'chameleon') {
      $actions[] = 'dramatic_color_shift';
    }
    if (!empty($game_state['downtime']['retraining'])) {
      $actions[] = 'advance_day';
    }
    else {
      $actions[] = 'retrain';
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
   * Processes watch assignment for the rest phase.
   *
   * REQ 2346: Watch duration by party size; all party members share watch duty;
   * minimum watch segments tracked.
   *
   * @param array $params
   *   - party_ids: array of character IDs in the party.
   *   - assignments: (optional) explicit array of {watcher_id, period, duration_hours}.
   *
   * @return array
   *   Keys: assignments, hours_per_watch, watch_periods, error (on failure).
   */
  protected function processAssignWatch(?string $actor_id, array $params, array &$game_state): array {
    $party_ids = $params['party_ids'] ?? [];
    if (empty($party_ids)) {
      return ['error' => 'No party members provided.', 'assignments' => []];
    }

    $party_size = count($party_ids);
    // Divide 8-hour rest equally; enforce minimum 2-hour shift.
    $hours_per_watcher = max(2, (int) floor(8 / $party_size));
    $watch_periods = (int) ceil(8 / $hours_per_watcher);

    if (!empty($params['assignments'])) {
      // Use explicit caller-provided assignments; validate each watcher is in party.
      $assignments = $params['assignments'];
      foreach ($assignments as $a) {
        if (!in_array($a['watcher_id'] ?? '', $party_ids, TRUE)) {
          return ['error' => 'Invalid watcher: ' . ($a['watcher_id'] ?? 'unknown') . ' is not in the party.', 'assignments' => []];
        }
      }
    }
    else {
      // Auto-assign round-robin.
      $assignments = [];
      for ($period = 0; $period < $watch_periods; $period++) {
        $assignments[] = [
          'period'         => $period + 1,
          'watcher_id'     => $party_ids[$period % $party_size],
          'duration_hours' => $hours_per_watcher,
        ];
      }
    }

    $game_state['rest']['watch_assignments'] = $assignments;
    $game_state['rest']['hours_per_watch'] = $hours_per_watcher;
    $game_state['rest']['party_size'] = $party_size;

    return [
      'assignments'    => $assignments,
      'hours_per_watch' => $hours_per_watcher,
      'watch_periods'  => $watch_periods,
    ];
  }

  /**
   * Advances starvation and thirst tracking by one day for the given characters.
   *
   * REQ 2347: Without water — immediate fatigue; 1d4 damage per day after threshold.
   * REQ 2348: Without food — immediate fatigue; 1 damage per day after threshold.
   * REQ 2349: Con mod ≤ 0 → minimum threshold of 1 day; both tracks are independent.
   * Healing is blocked while in the damage phase (tracked via entity state flags).
   *
   * @param array $params
   *   - char_ids: array of character entity IDs to advance.
   *   - resource: 'food' | 'water' | 'both' (default 'both').
   *
   * @return array
   *   Keys: results (per-char), mutations.
   */
  protected function processAdvanceStarvation(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $char_ids = $params['char_ids'] ?? ($actor_id ? [$actor_id] : []);
    $resource = $params['resource'] ?? 'both';

    $results  = [];
    $mutations = [];

    foreach ($dungeon_data['entities'] ?? [] as &$entity) {
      $eid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
      if (!in_array($eid, $char_ids, TRUE)) {
        continue;
      }

      $con_mod = (int) ($entity['stats']['con_modifier'] ?? 0);
      // REQ 2349: Con modifier ≤ 0 means minimum of 1 day before damage onset.
      $threshold = max(1, $con_mod + 1);

      $char_result = [
        'entity_id'          => $eid,
        'damage_taken'       => 0,
        'conditions_applied' => [],
      ];

      if (in_array($resource, ['water', 'both'], TRUE)) {
        $days_without_water = (int) ($entity['state']['days_without_water'] ?? 0) + 1;
        $entity['state']['days_without_water'] = $days_without_water;

        // Immediate fatigue on first day without water.
        if ($days_without_water === 1) {
          $this->applyFatigued($entity, 'dehydration');
          $char_result['conditions_applied'][] = 'fatigued';
        }

        // REQ 2348: After threshold days: 1d4 damage (abstracted to daily tick).
        // REQ 2349: healing blocked tracked via entity state flag.
        if ($days_without_water > $threshold) {
          $entity['state']['thirst_damage_phase'] = TRUE;
          $dmg = $this->numberGenerationService->rollPathfinderDie(4);
          $current_hp = (int) ($entity['state']['hit_points']['current'] ?? 0);
          $entity['state']['hit_points']['current'] = max(0, $current_hp - $dmg);
          $char_result['damage_taken'] += $dmg;
          $char_result['thirst_hp'] = $entity['state']['hit_points']['current'];
        }
      }

      if (in_array($resource, ['food', 'both'], TRUE)) {
        $days_without_food = (int) ($entity['state']['days_without_food'] ?? 0) + 1;
        $entity['state']['days_without_food'] = $days_without_food;

        // Immediate fatigue on first day without food.
        if ($days_without_food === 1) {
          $this->applyFatigued($entity, 'starvation');
          if (!in_array('fatigued', $char_result['conditions_applied'], TRUE)) {
            $char_result['conditions_applied'][] = 'fatigued';
          }
        }

        // REQ 2348: After threshold days: 1 damage per day.
        if ($days_without_food > $threshold) {
          $entity['state']['starvation_damage_phase'] = TRUE;
          $current_hp = (int) ($entity['state']['hit_points']['current'] ?? 0);
          $entity['state']['hit_points']['current'] = max(0, $current_hp - 1);
          $char_result['damage_taken'] += 1;
          $char_result['starvation_hp'] = $entity['state']['hit_points']['current'];
        }
      }

      $mutations[] = ['type' => 'entity_state', 'entity_id' => $eid, 'state' => $entity['state']];
      $results[$eid] = $char_result;
    }
    unset($entity);

    // Persist dungeon_data.
    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('advance_starvation persist failed: @error', ['@error' => $e->getMessage()]);
    }

    return ['results' => $results, 'mutations' => $mutations];
  }

  /**
   * Applies the fatigued condition to an entity if not already present.
   */
  protected function applyFatigued(array &$entity, string $source): void {
    if (!isset($entity['state']['conditions'])) {
      $entity['state']['conditions'] = [];
    }
    foreach ($entity['state']['conditions'] as $cond) {
      if (($cond['name'] ?? ($cond['type'] ?? '')) === 'fatigued') {
        return;
      }
    }
    $entity['state']['conditions'][] = ['name' => 'fatigued', 'source' => $source];
  }

  /**
   * Processes a long rest: restore HP, spell slots, remove conditions.
   */
  protected function processLongRest(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Long rest: 8 hours of rest. Per PF2e rules (REQ 2301):
    // - Restore HP equal to Con modifier × level (minimum 1 per level)
    // - Regain all spell slots
    // - Remove the wounded condition
    // - Reduce the value of doomed by 1

    $hp_restored = 0;
    $conditions_removed = [];
    $new_hp = NULL;

    // Find the character entity and restore HP.
    if ($actor_id && !empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
        if ($iid === $actor_id) {
          $current_hp = (int) ($entity['state']['hit_points']['current'] ?? 0);
          $max_hp = (int) ($entity['state']['hit_points']['max'] ?? 20);

          // REQ 2348: Healing blocked while in starvation or thirst damage phase.
          $healing_blocked = !empty($entity['state']['starvation_damage_phase']) || !empty($entity['state']['thirst_damage_phase']);

          // REQ 2301: HP regained = Con modifier × level (minimum 1).
          $con_mod = (int) ($entity['stats']['con_modifier'] ?? 0);
          $level = max(1, (int) ($entity['stats']['level'] ?? 1));
          $hp_per_rest = max(1, $con_mod) * $level;
          // REQ dc-cr-halfling-heritage-hillock: Hillock Halfling regains extra HP = level on overnight rest.
          if (($entity['heritage'] ?? '') === 'hillock') {
            $hp_per_rest += $level;
          }
          if (!$healing_blocked) {
            $new_hp = min($max_hp, $current_hp + $hp_per_rest);
            $entity['state']['hit_points']['current'] = $new_hp;
            $hp_restored = $new_hp - $current_hp;
          }
          else {
            $new_hp = $current_hp;
            $hp_restored = 0;
          }

          // Remove wounded condition.
          if (isset($entity['state']['conditions'])) {
            $entity['state']['conditions'] = array_filter(
              $entity['state']['conditions'],
              function ($condition) use (&$conditions_removed) {
                $name = $condition['name'] ?? ($condition['type'] ?? '');
                if ($name === 'wounded') {
                  $conditions_removed[] = 'wounded';
                  return FALSE;
                }
                return TRUE;
              }
            );
            $entity['state']['conditions'] = array_values($entity['state']['conditions']);
          }

          // REQ 2302: Sleeping in medium/heavy armor applies fatigued.
          if ($this->hasArmorEquipped($entity, ['medium', 'heavy'])) {
            if (!isset($entity['state']['conditions'])) {
              $entity['state']['conditions'] = [];
            }
            $already_fatigued = FALSE;
            foreach ($entity['state']['conditions'] as $cond) {
              if (($cond['name'] ?? ($cond['type'] ?? '')) === 'fatigued') {
                $already_fatigued = TRUE;
                break;
              }
            }
            if (!$already_fatigued) {
              $entity['state']['conditions'][] = ['name' => 'fatigued', 'source' => 'sleeping_in_armor'];
              $conditions_removed[] = '(fatigued from armor applied)';
            }
          }

          // REQ 2303: Reset sleep deprivation tracking.
          $entity['state']['hours_since_rest'] = 0;

          // REQ 2167: Reduce doomed by 1 per long rest (remove if reaches 0).
          if (isset($entity['state']['conditions'])) {
            foreach ($entity['state']['conditions'] as &$cond) {
              $cname = $cond['name'] ?? ($cond['type'] ?? '');
              if ($cname === 'doomed') {
                $cond['value'] = max(0, (int) ($cond['value'] ?? 1) - 1);
                if ($cond['value'] <= 0) {
                  $conditions_removed[] = 'doomed';
                  $cond['_remove'] = TRUE;
                }
                break;
              }
            }
            unset($cond);
            $entity['state']['conditions'] = array_values(array_filter(
              $entity['state']['conditions'],
              fn($c) => empty($c['_remove'])
            ));
          }

          break;
        }
      }
      unset($entity);
    }

    // Advance downtime days.
    if (isset($game_state['downtime'])) {
      $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;
    }

    // Persist.
    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist long rest: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'rested' => TRUE,
      'hp_restored' => $hp_restored,
      'new_hp' => $new_hp,
      'conditions_removed' => $conditions_removed,
      'spell_slots_restored' => TRUE,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'hit_points.current', 'to' => $new_hp],
      ],
    ];
  }

  /**
   * Returns TRUE if the entity has medium or heavy armor equipped.
   */
  protected function hasArmorEquipped(array $entity, array $armor_categories): bool {
    $equipped = $entity['equipment']['armor'] ?? ($entity['state']['equipped_armor'] ?? NULL);
    if (!$equipped) {
      return FALSE;
    }
    $category = $equipped['category'] ?? ($equipped['armor_type'] ?? '');
    return in_array($category, $armor_categories, TRUE);
  }

  /**
   * Processes a crafting action (AC-001 through AC-006).
   *
   * Dispatches to CraftingService based on the 'sub_action' param:
   *   - 'begin':        Start a new crafting project (validate + pay half price).
   *   - 'resolve':      Apply check degree after 4-day minimum.
   *   - 'advance_day':  Progress an in-progress success project.
   *   - 'add_formula':  Add a formula to the formula book.
   *
   * @param string $actor_id    Character ID.
   * @param array  $params      Action parameters (sub_action, item, degree, item_id, source, campaign_id).
   * @param array  $game_state  Current game state (phase must be 'downtime').
   * @param int    $campaign_id Campaign context.
   *
   * @return array  Result array.
   */
  protected function processCraft(string $actor_id, array $params, array &$game_state, int $campaign_id): array {
    $in_downtime = ($game_state['phase'] ?? '') === 'downtime';
    $sub_action  = $params['sub_action'] ?? 'begin';

    switch ($sub_action) {
      case 'begin':
        $item = $params['item'] ?? [];
        if (empty($item)) {
          return ['success' => FALSE, 'error' => 'missing_item', 'message' => 'No item specified for crafting.'];
        }
        return $this->craftingService->beginCrafting($actor_id, $item, $campaign_id, $in_downtime);

      case 'resolve':
        $degree = $params['degree'] ?? '';
        if (!in_array($degree, ['critical_success', 'success', 'failure', 'critical_failure'], TRUE)) {
          return ['success' => FALSE, 'error' => 'invalid_degree', 'message' => "Invalid degree: {$degree}."];
        }
        return $this->craftingService->resolveCrafting($actor_id, $degree, $campaign_id);

      case 'advance_day':
        return $this->craftingService->advanceCraftingDay($actor_id, $campaign_id);

      case 'add_formula':
        $item_id = $params['item_id'] ?? '';
        $source  = $params['source'] ?? 'purchased';
        if (empty($item_id)) {
          return ['success' => FALSE, 'error' => 'missing_item_id', 'message' => 'No item_id specified for formula.'];
        }
        return $this->craftingService->addFormula($actor_id, $item_id, $campaign_id, $source);

      default:
        return ['success' => FALSE, 'error' => 'unknown_sub_action', 'message' => "Unknown craft sub_action: {$sub_action}."];
    }
  }

  /**
   * Processes a downtime long-term rest (REQ 2306).
   * Restores Con mod × (2 × level) HP.
   */
  protected function processDowntimeRest(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $hp_restored = 0;
    $new_hp = NULL;

    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
        if ($iid === $actor_id) {
          $current_hp = (int) ($entity['state']['hit_points']['current'] ?? 0);
          $max_hp = (int) ($entity['state']['hit_points']['max'] ?? 20);
          $con_mod = (int) ($entity['stats']['con_modifier'] ?? 0);
          $level = max(1, (int) ($entity['stats']['level'] ?? 1));
          $hp_restored_calc = max(1, $con_mod) * (2 * $level);
          $new_hp = min($max_hp, $current_hp + $hp_restored_calc);
          $entity['state']['hit_points']['current'] = $new_hp;
          $hp_restored = $new_hp - $current_hp;
          break;
        }
      }
      unset($entity);
    }

    if (isset($game_state['downtime'])) {
      $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;
    }

    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist downtime rest: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'downtime_rest' => TRUE,
      'hp_restored' => $hp_restored,
      'new_hp' => $new_hp,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'hit_points.current', 'to' => $new_hp],
      ],
    ];
  }

  /**
   * Processes the retrain action (REQ 2307-2310).
   */
  protected function processRetrain(string $actor_id, array $params, array &$game_state): array {
    $retrain_type = $params['retrain_type'] ?? '';
    $retrain_from = $params['retrain_from'] ?? '';
    $retrain_to = $params['retrain_to'] ?? '';

    // REQ 2308: Cannot retrain locked elements.
    $prohibited = ['ancestry', 'heritage', 'background', 'class', 'ability_score'];
    if (in_array($retrain_type, $prohibited, TRUE)) {
      return ['error' => "Cannot retrain '$retrain_type': ancestry, heritage, background, class, and ability scores cannot be retrained."];
    }

    // REQ 2310: Block if already retraining.
    if (!empty($game_state['downtime']['retraining'])) {
      return ['error' => 'Already retraining. Complete or cancel current retraining before starting a new one.'];
    }

    // REQ 2309 / AC-003: Duration by retrain type:
    //   - feat: 7 days × feat level (AC-003: "1 week per feat level")
    //   - skill: 7 days flat
    //   - major class choices (druid_order, wizard_school, sorcerer_bloodline): 30 days
    //   - all others: 7 days
    $major_choices = ['druid_order', 'wizard_school', 'sorcerer_bloodline'];
    if (in_array($retrain_type, $major_choices, TRUE)) {
      $days_required = 30;
    }
    elseif ($retrain_type === 'feat') {
      // feat_level is required; default to 1 if omitted (guards against missing param).
      $feat_level    = max(1, (int) ($params['feat_level'] ?? 1));
      $days_required = 7 * $feat_level;
    }
    else {
      $days_required = 7;
    }

    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [];
    }
    $game_state['downtime']['retraining'] = [
      'actor_id' => $actor_id,
      'type' => $retrain_type,
      'from' => $retrain_from,
      'to' => $retrain_to,
      'days_remaining' => $days_required,
      'days_required' => $days_required,
    ];

    return [
      'retrain_started' => TRUE,
      'type' => $retrain_type,
      'from' => $retrain_from,
      'to' => $retrain_to,
      'days_required' => $days_required,
    ];
  }

  /**
   * Processes advance_day: decrements active retrain timer and applies on completion.
   */
  protected function processAdvanceDay(string $actor_id, array &$game_state, array &$dungeon_data): array {
    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [];
    }
    $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;

    $retrain_result = NULL;
    if (!empty($game_state['downtime']['retraining'])) {
      $rt = &$game_state['downtime']['retraining'];
      $rt['days_remaining']--;
      if ($rt['days_remaining'] <= 0) {
        // Apply retrain: update entity feat/skill in dungeon_data.
        if (!empty($dungeon_data['entities'])) {
          foreach ($dungeon_data['entities'] as &$entity) {
            $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
            if ($iid === ($rt['actor_id'] ?? $actor_id)) {
              if ($rt['type'] === 'feat') {
                if (!isset($entity['stats']['feats'])) {
                  $entity['stats']['feats'] = [];
                }
                $entity['stats']['feats'] = array_filter(
                  $entity['stats']['feats'],
                  fn($f) => ($f['name'] ?? $f) !== $rt['from']
                );
                $entity['stats']['feats'][] = ['name' => $rt['to'], 'source' => 'retrain'];
              }
              elseif ($rt['type'] === 'skill') {
                $entity['stats']['trained_skills'][$rt['to']] = TRUE;
                unset($entity['stats']['trained_skills'][$rt['from']]);
              }
              break;
            }
          }
          unset($entity);
        }
        $retrain_result = $rt;
        unset($game_state['downtime']['retraining']);
      }
    }

    return [
      'advanced' => TRUE,
      'days_elapsed' => $game_state['downtime']['days_elapsed'],
      'retrain_completed' => $retrain_result,
    ];
  }

  // =========================================================================
  // Earn Income.
  // =========================================================================

  /**
   * PF2e CRB Table 4-2 — Earn Income DC and per-day income in copper pieces.
   *
   * Structure: task_level => [dc, failure_cp, success_cp_by_rank]
   * success_cp_by_rank index: 0=Untrained, 1=Trained, 2=Expert, 3=Master, 4=Legendary
   * NULL means that proficiency rank cannot access that task level.
   */
  private const EARN_INCOME_TABLE = [
    0  => ['dc' => 14, 'failure_cp' => 1,   'success_cp' => [1,    5,    NULL,  NULL,  NULL]],
    1  => ['dc' => 15, 'failure_cp' => 2,   'success_cp' => [2,    20,   NULL,  NULL,  NULL]],
    2  => ['dc' => 16, 'failure_cp' => 4,   'success_cp' => [4,    30,   NULL,  NULL,  NULL]],
    3  => ['dc' => 18, 'failure_cp' => 8,   'success_cp' => [8,    50,   50,    NULL,  NULL]],
    4  => ['dc' => 19, 'failure_cp' => 10,  'success_cp' => [10,   70,   80,    NULL,  NULL]],
    5  => ['dc' => 20, 'failure_cp' => 20,  'success_cp' => [20,   90,   100,   100,   NULL]],
    6  => ['dc' => 22, 'failure_cp' => 30,  'success_cp' => [30,   150,  200,   200,   NULL]],
    7  => ['dc' => 23, 'failure_cp' => 40,  'success_cp' => [40,   200,  250,   250,   NULL]],
    8  => ['dc' => 24, 'failure_cp' => 50,  'success_cp' => [50,   250,  300,   300,   NULL]],
    9  => ['dc' => 26, 'failure_cp' => 60,  'success_cp' => [60,   300,  400,   400,   NULL]],
    10 => ['dc' => 27, 'failure_cp' => 70,  'success_cp' => [70,   400,  500,   600,   NULL]],
    11 => ['dc' => 28, 'failure_cp' => 80,  'success_cp' => [80,   500,  600,   800,   NULL]],
    12 => ['dc' => 30, 'failure_cp' => 90,  'success_cp' => [90,   600,  800,   1000,  NULL]],
    13 => ['dc' => 31, 'failure_cp' => 100, 'success_cp' => [100,  700,  1000,  1500,  NULL]],
    14 => ['dc' => 32, 'failure_cp' => 150, 'success_cp' => [150,  800,  1500,  2000,  NULL]],
    15 => ['dc' => 34, 'failure_cp' => 200, 'success_cp' => [200,  1000, 2000,  2800,  3600]],
  ];

  /**
   * Processes the earn_income downtime action (REQ 2326, CRB Table 4-2).
   *
   * Params:
   *   - skill (string): Skill used (e.g. 'crafting', 'performance', 'lore').
   *   - proficiency_rank (int): 0=Untrained, 1=Trained, 2=Expert, 3=Master, 4=Legendary.
   *   - task_level (int): Task difficulty level (0–15). Must not exceed character level.
   *   - degree (string): critical_success | success | failure | critical_failure.
   *   - days (int): Number of downtime days spent (default 1).
   *
   * @return array  Result with earned_cp, earned_gp_display, days_elapsed.
   */
  protected function processEarnIncome(?string $actor_id, array $params, array &$game_state, int $campaign_id): array {
    $skill             = $params['skill'] ?? 'lore';
    $proficiency_rank  = (int) ($params['proficiency_rank'] ?? 0);
    $task_level        = (int) ($params['task_level'] ?? 0);
    $degree            = $params['degree'] ?? 'failure';
    $days              = max(1, (int) ($params['days'] ?? 1));

    // Validate task level.
    if (!array_key_exists($task_level, self::EARN_INCOME_TABLE)) {
      return ['success' => FALSE, 'error' => 'invalid_task_level', 'message' => "Invalid task level: {$task_level}. Must be 0–15."];
    }

    $proficiency_rank = max(0, min(4, $proficiency_rank));
    $row = self::EARN_INCOME_TABLE[$task_level];

    // Check that this rank can access this task level.
    if ($row['success_cp'][$proficiency_rank] === NULL) {
      return [
        'success' => FALSE,
        'error'   => 'rank_insufficient',
        'message' => "Proficiency rank {$proficiency_rank} cannot access task level {$task_level}.",
      ];
    }

    // REQ 2326: Enforce critical failure cooldown.
    $cooldown_key = "earn_income_cooldown_{$skill}";
    if (!empty($game_state['downtime'][$cooldown_key])) {
      $cooldown_remaining = (int) $game_state['downtime'][$cooldown_key];
      if ($cooldown_remaining > 0) {
        return [
          'success'  => FALSE,
          'error'    => 'critical_failure_cooldown',
          'message'  => "Critical failure cooldown: cannot use {$skill} for Earn Income for {$cooldown_remaining} more day(s).",
          'cooldown' => $cooldown_remaining,
        ];
      }
    }

    $earned_cp = 0;

    switch ($degree) {
      case 'critical_success':
        // Critical success: earn income as if succeeded at the next higher task level.
        $crit_level = min(15, $task_level + 1);
        $crit_row   = self::EARN_INCOME_TABLE[$crit_level];
        $per_day    = $crit_row['success_cp'][$proficiency_rank] ?? $row['success_cp'][$proficiency_rank];
        $earned_cp  = $per_day * $days;
        break;

      case 'success':
        $earned_cp = $row['success_cp'][$proficiency_rank] * $days;
        break;

      case 'failure':
        $earned_cp = $row['failure_cp'] * $days;
        break;

      case 'critical_failure':
        $earned_cp = 0;
        // REQ 2326: 7-day cooldown on this skill for Earn Income after critical failure.
        if (!isset($game_state['downtime'])) {
          $game_state['downtime'] = [];
        }
        $game_state['downtime'][$cooldown_key] = 7;
        break;

      default:
        return ['success' => FALSE, 'error' => 'invalid_degree', 'message' => "Invalid degree: {$degree}."];
    }

    // Advance downtime days.
    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [];
    }
    $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + $days;

    // Decrement any active cooldowns by days spent.
    foreach ($game_state['downtime'] as $key => &$val) {
      if (str_starts_with($key, 'earn_income_cooldown_') && $key !== $cooldown_key) {
        $val = max(0, (int) $val - $days);
      }
    }
    unset($val);

    // Award income to the character.
    if ($earned_cp > 0 && $actor_id) {
      $award_result = $this->addCurrency($actor_id, $earned_cp);
      if (!$award_result['success']) {
        return array_merge(['success' => FALSE], $award_result);
      }
    }

    return [
      'success'          => TRUE,
      'skill'            => $skill,
      'task_level'       => $task_level,
      'task_dc'          => $row['dc'],
      'degree'           => $degree,
      'proficiency_rank' => $proficiency_rank,
      'days'             => $days,
      'earned_cp'        => $earned_cp,
      'earned_gp'        => round($earned_cp / 100, 2),
      'days_elapsed'     => $game_state['downtime']['days_elapsed'],
    ];
  }


  // ---------------------------------------------------------------------------
  // REQ 1669–1672: Gather Information [Downtime, ~2 hrs, Diplomacy]
  // ---------------------------------------------------------------------------

  /**
   * Processes a Gather Information downtime action.
   *
   * @param string $actor_id   Participant ID.
   * @param array  $params     Action params (degree, topic, diplomacy_bonus).
   * @param array  $game_state Current game state (modified by reference).
   *
   * @return array  Result with keys: gathered, community_aware, degree.
   */
  protected function processGatherInformation(string $actor_id, array $params, array &$game_state): array {
    $topic  = $params['topic'] ?? 'unknown';
    $degree = $params['degree'] ?? 'failure';

    // Track hours elapsed in downtime state.
    $game_state['downtime']['hours_elapsed'] = ($game_state['downtime']['hours_elapsed'] ?? 0) + 2;

    $gathered       = FALSE;
    $community_aware = FALSE;

    switch ($degree) {
      case 'critical_success':
      case 'success':
        $gathered = TRUE;
        break;

      case 'critical_failure':
        // Investigation is exposed — community becomes aware.
        $community_aware = TRUE;
        break;

      // failure: no info, no exposure.
    }

    return [
      'gathered'        => $gathered,
      'community_aware' => $community_aware,
      'topic'           => $topic,
      'degree'          => $degree,
    ];
  }

  // ---------------------------------------------------------------------------
  // REQ 1673–1677: Make an Impression [Downtime, ~10 min, Diplomacy]
  // ---------------------------------------------------------------------------

  /**
   * Processes a Make an Impression downtime action.
   *
   * Shifts target NPC attitude: Crit = +2, Success = +1, Fail = 0, Crit Fail = −1.
   *
   * @param string $actor_id    Participant ID.
   * @param array  $params      Action params (degree, target_id).
   * @param array  $game_state  Current game state (modified by reference).
   * @param string $campaign_id Active campaign ID.
   *
   * @return array  Result with keys: degree, old_attitude, new_attitude, shift.
   */
  protected function processMakeImpression(string $actor_id, array $params, array &$game_state, string $campaign_id): array {
    $degree    = $params['degree'] ?? 'failure';
    $target_id = $params['target_id'] ?? '';

    $game_state['downtime']['hours_elapsed'] = ($game_state['downtime']['hours_elapsed'] ?? 0);

    $shift_map = [
      'critical_success' => 2,
      'success'          => 1,
      'failure'          => 0,
      'critical_failure' => -1,
    ];
    $shift = $shift_map[$degree] ?? 0;

    $old_attitude = 'indifferent';
    $new_attitude = 'indifferent';

    if (!empty($target_id)) {
      $profile      = $this->npcPsychology->getOrCreateProfile((int) $campaign_id, $target_id);
      $old_attitude = $profile['attitude'] ?? 'indifferent';
      $new_attitude = $this->npcPsychology->shiftAttitude($old_attitude, $shift);
      $this->npcPsychology->updateProfile((int) $campaign_id, $target_id, ['attitude' => $new_attitude]);
    }

    return [
      'degree'       => $degree,
      'old_attitude' => $old_attitude,
      'new_attitude' => $new_attitude,
      'shift'        => $shift,
    ];
  }

  // ---------------------------------------------------------------------------
  // REQ 1678–1683: Coerce [Downtime, ~10 min, Intimidation]
  // ---------------------------------------------------------------------------

  /**
   * Processes a Coerce downtime action.
   *
   * Produces compliance; target becomes Unfriendly after 7 days.
   * 7-day immunity prevents the same target from being coerced again.
   *
   * @param string $actor_id    Participant ID.
   * @param array  $params      Action params (degree, target_id).
   * @param array  $game_state  Current game state (modified by reference).
   * @param string $campaign_id Active campaign ID.
   *
   * @return array  Result with keys: degree, compliant, compliance_days, immune.
   */
  protected function processCoerce(string $actor_id, array $params, array &$game_state, string $campaign_id): array {
    $degree    = $params['degree'] ?? 'failure';
    $target_id = $params['target_id'] ?? '';

    // Check 7-day immunity.
    $immune_key = 'coerce_immune_' . $actor_id . '_' . $target_id;
    if (!empty($game_state['downtime'][$immune_key])) {
      return [
        'degree'          => $degree,
        'compliant'       => FALSE,
        'compliance_days' => 0,
        'immune'          => TRUE,
      ];
    }

    $compliant       = FALSE;
    $compliance_days = 0;

    switch ($degree) {
      case 'critical_success':
        $compliant       = TRUE;
        $compliance_days = 30;
        break;

      case 'success':
        $compliant       = TRUE;
        $compliance_days = 7;
        break;

      case 'critical_failure':
        // Target becomes Hostile and tells others; set immune.
        $game_state['downtime'][$immune_key] = TRUE;
        if (!empty($target_id)) {
          $profile = $this->npcPsychology->getOrCreateProfile((int) $campaign_id, $target_id);
          $new_attitude = $this->npcPsychology->shiftAttitude($profile['attitude'] ?? 'indifferent', -2);
          $this->npcPsychology->updateProfile((int) $campaign_id, $target_id, ['attitude' => $new_attitude]);
        }
        break;

      // failure: target becomes Unfriendly, set immune.
      case 'failure':
        $game_state['downtime'][$immune_key] = TRUE;
        if (!empty($target_id)) {
          $profile = $this->npcPsychology->getOrCreateProfile((int) $campaign_id, $target_id);
          $new_attitude = $this->npcPsychology->shiftAttitude($profile['attitude'] ?? 'indifferent', -1);
          $this->npcPsychology->updateProfile((int) $campaign_id, $target_id, ['attitude' => $new_attitude]);
        }
        break;
    }

    if ($compliant) {
      // Set 7-day immunity; target becomes Unfriendly once compliance expires
      // (tracked externally via compliance_days).
      $game_state['downtime'][$immune_key] = TRUE;
    }

    return [
      'degree'          => $degree,
      'compliant'       => $compliant,
      'compliance_days' => $compliance_days,
      'immune'          => FALSE,
    ];
  }

  // =========================================================================
  // AC-005: Other Downtime Activities.
  // =========================================================================

  /**
   * PF2e CRB Subsist environment DCs by settlement type.
   * CRB Table 5-2 (Downtime Activities — Subsist).
   *
   * Keys: environment type string → DC.
   */
  private const SUBSIST_DC = [
    'thriving_city' => 10,
    'settled_town'  => 12,
    'rural_area'    => 14,
    'wilderness'    => 16,
    'extreme'       => 20,
  ];

  /**
   * Processes the Subsist downtime action (AC-005).
   *
   * Characters make a Survival or Society check vs the local environment DC
   * to cover their living expenses for a day (or fail and pay 1 sp).
   *
   * Params:
   *   - skill (string):        'survival' | 'society' (default 'survival').
   *   - degree (string):       critical_success | success | failure | critical_failure.
   *   - environment (string):  Key from SUBSIST_DC table (default 'settled_town').
   *
   * @param string|null $actor_id
   * @param array       $params
   * @param array       $game_state  Modified by reference (days_elapsed +1).
   *
   * @return array  Result with keys: success, degree, skill, environment, dc, covered, penalty_cp.
   */
  protected function processSubsist(?string $actor_id, array $params, array &$game_state): array {
    $skill       = in_array($params['skill'] ?? '', ['survival', 'society'], TRUE) ? $params['skill'] : 'survival';
    $degree      = $params['degree'] ?? 'failure';
    $environment = $params['environment'] ?? 'settled_town';

    $dc = self::SUBSIST_DC[$environment] ?? self::SUBSIST_DC['settled_town'];

    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [];
    }
    $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;

    switch ($degree) {
      case 'critical_success':
        // CRB: crit success provides enough food/shelter for self AND one other.
        return [
          'success'     => TRUE,
          'degree'      => $degree,
          'skill'       => $skill,
          'environment' => $environment,
          'dc'          => $dc,
          'covered'     => TRUE,
          'extra_covered' => 1,
          'penalty_cp'  => 0,
          'days_elapsed' => $game_state['downtime']['days_elapsed'],
        ];

      case 'success':
        return [
          'success'     => TRUE,
          'degree'      => $degree,
          'skill'       => $skill,
          'environment' => $environment,
          'dc'          => $dc,
          'covered'     => TRUE,
          'extra_covered' => 0,
          'penalty_cp'  => 0,
          'days_elapsed' => $game_state['downtime']['days_elapsed'],
        ];

      case 'failure':
        // CRB: failure = must pay 1 sp (= 10 cp) for subsistence.
        return [
          'success'     => TRUE,
          'degree'      => $degree,
          'skill'       => $skill,
          'environment' => $environment,
          'dc'          => $dc,
          'covered'     => FALSE,
          'extra_covered' => 0,
          'penalty_cp'  => 10,
          'days_elapsed' => $game_state['downtime']['days_elapsed'],
        ];

      case 'critical_failure':
        // CRB: crit failure = starving / no shelter. Fatigued condition applied.
        $game_state['downtime']['subsist_crit_fail_days'] = ($game_state['downtime']['subsist_crit_fail_days'] ?? 0) + 1;
        return [
          'success'       => TRUE,
          'degree'        => $degree,
          'skill'         => $skill,
          'environment'   => $environment,
          'dc'            => $dc,
          'covered'       => FALSE,
          'extra_covered' => 0,
          'penalty_cp'    => 0,
          'fatigued'      => TRUE,
          'message'       => 'Critical failure: character goes without food/shelter. Fatigue applied.',
          'days_elapsed'  => $game_state['downtime']['days_elapsed'],
        ];

      default:
        return ['success' => FALSE, 'error' => 'invalid_degree', 'message' => "Invalid degree: {$degree}."];
    }
  }

  /**
   * Processes the Treat Disease downtime action (AC-005).
   *
   * A character with Medicine training attempts to reduce the current stage
   * of a disease (tracked in combat_afflictions table).
   *
   * Degrees (CRB Treat Disease):
   *   - critical_success: reduce stage by 2.
   *   - success:          reduce stage by 1.
   *   - failure:          no change.
   *   - critical_failure: increase stage by 1.
   *
   * Params:
   *   - affliction_id (int):  ID in the combat_afflictions table.
   *   - degree (string):      critical_success | success | failure | critical_failure.
   *
   * @param string|null $actor_id
   * @param array       $params
   * @param array       $game_state  Modified by reference (days_elapsed +1).
   *
   * @return array  Result with keys: success, old_stage, new_stage, degree, cured.
   */
  protected function processTreatDisease(?string $actor_id, array $params, array &$game_state): array {
    $affliction_id = isset($params['affliction_id']) ? (int) $params['affliction_id'] : NULL;
    $degree        = $params['degree'] ?? 'failure';

    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [];
    }
    $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;

    if ($affliction_id === NULL) {
      return ['success' => FALSE, 'error' => 'missing_affliction_id', 'message' => 'affliction_id param required.'];
    }

    $row = $this->database->select('combat_afflictions', 'a')
      ->fields('a', ['id', 'current_stage', 'max_stage', 'affliction_type'])
      ->condition('id', $affliction_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return ['success' => FALSE, 'error' => 'affliction_not_found', 'message' => "Affliction {$affliction_id} not found."];
    }
    if ($row['affliction_type'] !== 'disease') {
      return ['success' => FALSE, 'error' => 'not_a_disease', 'message' => "Affliction {$affliction_id} is not a disease."];
    }

    $old_stage = (int) $row['current_stage'];
    $max_stage = (int) $row['max_stage'];

    $stage_delta = match ($degree) {
      'critical_success' => -2,
      'success'          => -1,
      'failure'          =>  0,
      'critical_failure' => +1,
      default            => NULL,
    };

    if ($stage_delta === NULL) {
      return ['success' => FALSE, 'error' => 'invalid_degree', 'message' => "Invalid degree: {$degree}."];
    }

    $new_stage = max(0, min($max_stage, $old_stage + $stage_delta));
    $cured     = ($new_stage === 0);

    $this->database->update('combat_afflictions')
      ->fields(['current_stage' => $new_stage])
      ->condition('id', $affliction_id)
      ->execute();

    return [
      'success'       => TRUE,
      'affliction_id' => $affliction_id,
      'degree'        => $degree,
      'old_stage'     => $old_stage,
      'new_stage'     => $new_stage,
      'cured'         => $cured,
      'days_elapsed'  => $game_state['downtime']['days_elapsed'],
    ];
  }

  /**
   * Processes Run Business / Crafts for Sale downtime action (AC-005).
   *
   * Routes to processEarnIncome using the provided skill (Crafting, Lore,
   * Performance, etc.) and task level. The income calculation uses the same
   * Earn Income table (CRB Table 4-2).
   *
   * Params: same as earn_income (skill, proficiency_rank, task_level, degree, days).
   *
   * @param string|null $actor_id
   * @param array       $params
   * @param array       $game_state  Modified by reference.
   * @param int         $campaign_id
   *
   * @return array  Result with earned_cp, earned_gp, activity: 'run_business'.
   */
  protected function processRunBusiness(?string $actor_id, array $params, array &$game_state, int $campaign_id): array {
    // Default to 'crafting' for a business that sells crafted goods.
    $params['skill'] = $params['skill'] ?? 'crafting';
    $result = $this->processEarnIncome($actor_id, $params, $game_state, $campaign_id);
    $result['activity'] = 'run_business';
    return $result;
  }

  // =========================================================================
  // Currency helper.
  // =========================================================================

  /**
   * Adds copper pieces to a character's currency (REQ 2326).
   *
   * @param string $character_id  Character ID.
   * @param int    $amount_cp     Amount to add in copper pieces.
   *
   * @return array  ['success' => bool, 'new_currency' => array, ...]
   */
  private function addCurrency(string $character_id, int $amount_cp): array {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return ['success' => FALSE, 'error' => 'character_not_found', 'message' => "Character not found: {$character_id}"];
    }

    $char_data = json_decode($record['character_data'] ?? '{}', TRUE) ?: [];
    $currency  = $char_data['character']['equipment']['currency']
      ?? $char_data['equipment']['currency']
      ?? $char_data['currency']
      ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];

    if (!isset($currency['gp']) && isset($currency['gold'])) {
      $currency = [
        'pp' => (int) ($currency['pp'] ?? 0),
        'gp' => (int) $currency['gold'],
        'sp' => (int) ($currency['silver'] ?? 0),
        'cp' => (int) ($currency['copper'] ?? 0),
      ];
    }

    $rates = ['cp' => 1, 'sp' => 10, 'gp' => 100, 'pp' => 1000];
    $total_cp = 0;
    foreach ($rates as $denom => $rate) {
      $total_cp += ((int) ($currency[$denom] ?? 0)) * $rate;
    }
    $total_cp += $amount_cp;

    $new_currency = ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    foreach (['pp', 'gp', 'sp', 'cp'] as $denom) {
      $new_currency[$denom] = intdiv($total_cp, $rates[$denom]);
      $total_cp %= $rates[$denom];
    }

    if (isset($char_data['character']['equipment']['currency'])) {
      $char_data['character']['equipment']['currency'] = $new_currency;
    }
    elseif (isset($char_data['equipment']['currency'])) {
      $char_data['equipment']['currency'] = $new_currency;
    }
    else {
      $char_data['currency'] = $new_currency;
    }

    $this->database->update('dc_campaign_characters')
      ->fields(['character_data' => json_encode($char_data)])
      ->condition('id', $character_id)
      ->execute();

    return ['success' => TRUE, 'new_currency' => $new_currency, 'added_cp' => $amount_cp];
  }

  // ---------------------------------------------------------------------------
  // REQ 1731–1736: Create a Forgery [Downtime, Secret, Trained Society]
  // ---------------------------------------------------------------------------

  /**
   * Processes a Create a Forgery downtime action (REQs 1731–1736).
   *
   * This is a Secret roll per PF2e rules: the outcome is stored server-side
   * and only a coarsened result is returned to the caller.  The raw degree
   * is prefixed with '_' and must never be sent to the client.
   *
   * DCs (CRB p.251):
   *   - common:        DC 20
   *   - specialist:    DC 30
   *   - official_seal: DC 40
   *
   * Outcomes (coarsened):
   *   - success              (critical_success or success)
   *   - failure              (failure — detectable, but character does not know)
   *   - critical_failure_revealed (crit fail — character is aware; may retry)
   *
   * Detection: when a viewer examines the forgery later, they roll Society
   * vs the forger's Deception DC (stored server-side as detection_dc).
   *
   * Params:
   *   - document_type (string):    common | specialist | official_seal.
   *   - degree (string):           critical_success | success | failure | critical_failure.
   *   - deception_modifier (int):  forger's Deception modifier for detection DC.
   *   - proficiency_rank (int):    1=Trained, 2=Expert, 3=Master, 4=Legendary (0 = untrained).
   *   - forgery_id (string|null):  caller-supplied ID; auto-generated if absent.
   *
   * @param string|null $actor_id
   * @param array       $params
   * @param array       $game_state  Modified by reference (forgeries registry updated).
   *
   * @return array  Result with keys: success, outcome, forgery_id, dc, actor_aware.
   */
  protected function processCreateForgery(?string $actor_id, array $params, array &$game_state): array {
    $proficiency_rank  = (int) ($params['proficiency_rank'] ?? 0);
    $document_type     = $params['document_type'] ?? 'common';
    $degree            = $params['degree'] ?? 'failure';
    $deception_mod     = (int) ($params['deception_modifier'] ?? 0);

    // REQ 1731: Requires Trained Society (rank >= 1).
    if ($proficiency_rank < 1) {
      return [
        'success' => FALSE,
        'error'   => 'untrained',
        'message' => 'Create a Forgery requires at least Trained proficiency in Society.',
      ];
    }

    // REQ 1732: DC by document type.
    $dc_map = [
      'common'        => 20,
      'specialist'    => 30,
      'official_seal' => 40,
    ];
    $dc = $dc_map[$document_type] ?? $dc_map['common'];

    // Detection DC: viewer rolls Society vs forger's Deception DC (10 + mod).
    $detection_dc = 10 + $deception_mod;

    // Assign or generate a stable forgery ID for this document.
    $forgery_id = $params['forgery_id'] ?? uniqid('forgery_', TRUE);

    if (!isset($game_state['forgeries'])) {
      $game_state['forgeries'] = [];
    }

    // REQ 1733–1736: Resolve outcome.
    // Store the raw degree server-side (prefixed _degree — never expose to caller).
    switch ($degree) {
      case 'critical_success':
      case 'success':
        // REQ 1733: Success — forgery passes casual inspection.
        $outcome    = 'success';
        $detectable = FALSE;
        $actor_aware = FALSE;
        break;

      case 'failure':
        // REQ 1734: Failure — forgery is detectable to trained eyes.
        $outcome    = 'failure';
        $detectable = TRUE;
        $actor_aware = FALSE;
        break;

      case 'critical_failure':
        // REQ 1735: Crit failure — obviously fake; character is aware and may retry.
        $outcome    = 'critical_failure_revealed';
        $detectable = TRUE;
        $actor_aware = TRUE;
        break;

      default:
        return ['success' => FALSE, 'error' => 'invalid_degree', 'message' => "Invalid degree: {$degree}."];
    }

    // Persist server-side state (raw degree and detection DC are secret).
    $game_state['forgeries'][$forgery_id] = [
      '_degree'      => $degree,
      'document_type' => $document_type,
      'dc'           => $dc,
      'detectable'   => $detectable,
      'detection_dc' => $detection_dc,
      'actor_id'     => $actor_id,
    ];

    $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;

    return [
      'success'     => TRUE,
      'forgery_id'  => $forgery_id,
      'outcome'     => $outcome,
      'dc'          => $dc,
      'actor_aware' => $actor_aware,
    ];
  }


}
