<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles game actions during the Exploration phase.
 *
 * Exploration is the default game phase where the player moves freely between
 * rooms, interacts with objects and NPCs, and the AI GM narrates discoveries.
 * Time is tracked in 10-minute intervals. Encounters can trigger when entering
 * rooms or interacting with certain entities.
 *
 * Wraps existing services: entity movement, room chat, room state, quest
 * tracking. Does NOT rewrite them — delegates and coordinates.
 */
class ExplorationPhaseHandler implements PhaseHandlerInterface {

  /**
   * AC-003: Maps each exploration activity to its initiative skill.
   * AC-005: Also used when computing surprise (Avoid Notice → Stealth).
   */
  protected const ACTIVITY_INITIATIVE_SKILLS = [
    'avoid_notice'   => 'stealth',
    'defend'         => 'perception',
    'detect_magic'   => 'perception',
    'follow_expert'  => 'perception',
    'hustle'         => 'athletics',
    'investigate'    => 'perception',
    'repeat_spell'   => 'perception',
    'scout'          => 'perception',
    'search'         => 'perception',
    'sense_direction' => 'survival',
  ];

  /**
   * AC-002: Hustle causes fatigue after this many hustle-minutes elapsed.
   */
  protected const HUSTLE_FATIGUE_MINUTES = 10;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\RoomChatService
   */
  protected RoomChatService $roomChatService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\DungeonStateService
   */
  protected DungeonStateService $dungeonStateService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CharacterStateService
   */
  protected CharacterStateService $characterStateService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGenerationService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\AiGmService
   */
  protected AiGmService $aiGmService;

  /**
   * Narration engine for per-character perception-filtered narration.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NarrationEngine|null
   */
  protected ?NarrationEngine $narrationEngine;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\KnowledgeAcquisitionService
   */
  protected KnowledgeAcquisitionService $knowledgeAcquisition;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HazardService
   */
  protected HazardService $hazardService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\MagicItemService
   */
  protected MagicItemService $magicItemService;

  /**
   * Constructs an ExplorationPhaseHandler.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    RoomChatService $room_chat_service,
    DungeonStateService $dungeon_state_service,
    CharacterStateService $character_state_service,
    NumberGenerationService $number_generation_service,
    AiGmService $ai_gm_service,
    ?NarrationEngine $narration_engine = NULL,
    ?KnowledgeAcquisitionService $knowledge_acquisition = NULL,
    ?HazardService $hazard_service = NULL,
    ?MagicItemService $magic_item_service = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->roomChatService = $room_chat_service;
    $this->dungeonStateService = $dungeon_state_service;
    $this->characterStateService = $character_state_service;
    $this->numberGenerationService = $number_generation_service;
    $this->aiGmService = $ai_gm_service;
    $this->narrationEngine = $narration_engine;
    $this->knowledgeAcquisition = $knowledge_acquisition
      ?? new KnowledgeAcquisitionService(
        $database,
        $character_state_service,
        new IdentifyMagicService(new DcAdjustmentService()),
        new LearnASpellService(new DcAdjustmentService()),
        new DcAdjustmentService()
      );
    $this->hazardService = $hazard_service ?? new HazardService($number_generation_service);
    $this->magicItemService = $magic_item_service ?? new MagicItemService($number_generation_service);
  }

  /**
   * {@inheritdoc}
   */
  public function getPhaseName(): string {
    return 'exploration';
  }

  /**
   * {@inheritdoc}
   */
  public function getLegalIntents(): array {
    return [
      'move',
      'interact',
      'talk',
      'search',
      'transition',
      'set_activity',
      'rest',
      'cast_spell',
      'open_door',
      'open_passage',
      'daily_prepare',
      'treat_wounds',
      'treat_disease',
      // REQ 1591–1594, 2329: Recall Knowledge [1 action, Secret].
      'recall_knowledge',
      // REQ for Occultism/Religion: Decipher Writing, Identify Magic, Learn a Spell.
      'decipher_writing',
      'identify_magic',
      'learn_a_spell',
      // AC-003, AC-005: Prepared spell assignment and Refocus.
      'prepare_spell',
      'refocus',
      // REQ 1591: Acrobatics — Squeeze through tight spaces.
      'squeeze',
      // REQ 1612: Arcana — Borrow an Arcane Spell.
      'borrow_arcane_spell',
      // REQ 1633: Crafting — Repair an item.
      'repair',
      // REQ 1641: Crafting — Identify Alchemy.
      'identify_alchemy',
      // REQ 1660: Deception — Impersonate.
      'impersonate',
      // REQ 1665: Deception — Lie.
      'lie',
      // REQ 1700: Nature — Command an Animal (exploration variant).
      'command_animal',
      // REQ 1706: Performance — Perform (exploration variant).
      'perform',
      // REQ 2384–2388: Disable a Device (hazard-targeting).
      'disable_hazard',
      // REQ 2393–2394: Counteract a magical hazard (exploration phase).
      'counteract_hazard',
      // REQ 2392: Attack a hazard to destroy it.
      'attack_hazard',
      // REQ 2397–2409: Magic item investment.
      'invest_item',
      // REQ 2410–2425: Activate a magic item.
      'activate_item',
      // REQ 2416–2420: Sustain an activation.
      'sustain_activation',
      // REQ 2421–2424: Dismiss an activation.
      'dismiss_activation',
      // REQ 2478–2490: Cast from scroll.
      'cast_from_scroll',
      // REQ 2501–2510: Prepare a staff (daily preparations context).
      'prepare_staff',
      // REQ 2511–2520: Cast from staff.
      'cast_from_staff',
      // REQ 2521–2530: Cast from wand.
      'cast_from_wand',
      // REQ 2531–2535: Overcharge wand.
      'overcharge_wand',
      // REQ 2536–2545: Craft and place snare.
      'craft_snare',
      // REQ 2517: Disable a detected snare (Thievery check).
      'disable_snare',
      // REQ 2546–2548: Affix talisman to item.
      'affix_talisman',
      // REQ 2549: Activate talisman.
      'activate_talisman',
      // REQ 2474–2477: Apply oil.
      'apply_oil',
      // REQ 2455–2467: Etch rune onto item.
      'etch_rune',
      // REQ 2468–2473: Transfer rune between items.
      'transfer_rune',
      // REQ 1730: Sense Direction [Exploration, free] — Survival (Wis).
      'sense_direction',
      // REQ 1733: Cover Tracks [Exploration, Trained] — Survival (Wis).
      'cover_tracks',
      // REQ 1734–1737: Track [Exploration, Trained] — Survival (Wis).
      'track',
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
        'reason' => "Action '$type' is not legal during exploration phase.",
      ];
    }

    // Validate actor exists.
    $actor_id = $intent['actor'] ?? NULL;
    if ($actor_id && !$this->findEntityInDungeon($actor_id, $dungeon_data)) {
      return [
        'valid' => FALSE,
        'reason' => "Actor entity '$actor_id' not found in dungeon data.",
      ];
    }

    // Transition-specific: validate the connection exists and is discoverable.
    if ($type === 'transition') {
      $target_room = $intent['params']['target_room_id'] ?? NULL;
      if (!$target_room) {
        return [
          'valid' => FALSE,
          'reason' => 'Room transition requires params.target_room_id.',
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

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;

    switch ($type) {

      case 'move':
        $result = $this->processMove($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('move', 'exploration', $actor_id, [
          'from' => $params['from_hex'] ?? NULL,
          'to' => $params['to_hex'] ?? NULL,
        ]);
        break;

      case 'interact':
        $result = $this->processInteract($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('interact', 'exploration', $actor_id, [
          'target' => $target_id,
          'interaction' => $params['interaction_type'] ?? 'generic',
        ], NULL, $target_id);

        // Queue interaction for narration.
        $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => $actor_entity['name'] ?? $actor_id,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => sprintf('%s interacts with %s (%s).', $actor_entity['name'] ?? $actor_id, $target_id, $params['interaction_type'] ?? 'generic'),
          'visibility' => 'public',
        ]);
        break;

      case 'talk':
        $result = $this->processTalk($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        $events[] = GameEventLogger::buildEvent('talk', 'exploration', $actor_id, [
          'target' => $target_id,
          'message' => $params['message'] ?? '',
        ], $narration, $target_id);
        break;

      case 'search':
        $result = $this->processSearch($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        // Searching advances time by 10 minutes.
        $this->advanceExplorationTime($game_state, 10);
        $events[] = GameEventLogger::buildEvent('search', 'exploration', $actor_id, [
          'roll' => $result['roll'] ?? NULL,
          'dc' => $result['dc'] ?? NULL,
          'degree' => $result['degree'] ?? NULL,
          'discoveries' => $result['discoveries'] ?? [],
        ], $narration);

        // Queue search roll as mechanical event + search action for narration.
        $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'skill_check_result',
          'speaker' => 'System',
          'speaker_type' => 'system',
          'speaker_ref' => '',
          'content' => sprintf('%s searches the area (Perception %d vs DC %d: %s).', $actor_entity['name'] ?? $actor_id, $result['total'] ?? 0, $result['dc'] ?? 15, $result['degree'] ?? 'unknown'),
          'mechanical_data' => [
            'skill' => 'perception',
            'roll' => $result['roll'] ?? NULL,
            'total' => $result['total'] ?? NULL,
            'dc' => $result['dc'] ?? NULL,
            'degree' => $result['degree'] ?? NULL,
          ],
          'visibility' => 'public',
        ]);
        // If discoveries were made, queue a narration event for them.
        if (!empty($result['discoveries'])) {
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'action',
            'speaker' => $actor_entity['name'] ?? $actor_id,
            'speaker_type' => 'player',
            'speaker_ref' => $actor_id,
            'content' => sprintf('%s discovers: %s', $actor_entity['name'] ?? $actor_id, implode(', ', array_map(fn($d) => $d['name'] ?? $d['id'] ?? 'something', $result['discoveries']))),
            'visibility' => 'public',
          ]);
        }
        break;

      case 'transition':
        $result = $this->processRoomTransition($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        // Room transition advances time.
        $this->advanceExplorationTime($game_state, 10);

        // AI GM narration for room entry.
        $target_room_id = $params['target_room_id'] ?? NULL;
        $room_data = $this->findRoomInDungeon($target_room_id, $dungeon_data);
        if ($room_data) {
          $first_visit = $this->isFirstVisit($target_room_id, $dungeon_data);
          $gm_narration = $this->aiGmService->narrateRoomEntry($room_data, $dungeon_data, $first_visit, $campaign_id);
          if ($gm_narration) {
            $narration = $gm_narration;
          }
        }

        $events[] = GameEventLogger::buildEvent('room_entered', 'exploration', $actor_id, [
          'from_room' => $game_state['exploration']['previous_room'] ?? NULL,
          'to_room' => $target_room_id,
        ], $narration);

        // Queue room entry for perception-filtered narration.
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => 'GM',
          'speaker_type' => 'gm',
          'speaker_ref' => '',
          'content' => sprintf('The party enters %s.', $room_data['name'] ?? $target_room_id),
          'visibility' => 'public',
          'mechanical_data' => [
            'from_room' => $game_state['exploration']['previous_room'] ?? NULL,
            'to_room' => $target_room_id,
            'first_visit' => $first_visit ?? TRUE,
          ],
        ], $target_room_id);

        // Check for encounter trigger on room entry.
        $encounter_check = $this->checkEncounterTrigger($params['target_room_id'] ?? '', $dungeon_data, $game_state);
        if ($encounter_check['should_trigger']) {
          // AC-001/AC-005: Transition sets time_unit to rounds; snapshot activity skills.
          $game_state['exploration']['time_unit'] = 'rounds';
          $encounter_ctx = $encounter_check['encounter_context'] ?? [];
          $encounter_ctx['initiative_skills'] = $encounter_check['initiative_skills'] ?? [];
          $encounter_ctx['surprised_enemies'] = $encounter_check['surprised_enemies'] ?? [];
          // Deactivate exploration activities when combat begins.
          $game_state['exploration']['pre_encounter_activities'] = $game_state['exploration']['character_activities'] ?? [];
          $game_state['exploration']['character_activities'] = [];
          $phase_transition = [
            'from' => 'exploration',
            'to' => 'encounter',
            'reason' => $encounter_check['reason'] ?? 'Hostile creatures detected!',
            'encounter_context' => $encounter_ctx,
          ];
          $events[] = GameEventLogger::buildEvent('encounter_triggered', 'exploration', $actor_id, [
            'room_id' => $params['target_room_id'],
            'reason' => $encounter_check['reason'],
          ]);

          // Queue encounter trigger event.
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'action',
            'speaker' => 'GM',
            'speaker_type' => 'gm',
            'speaker_ref' => '',
            'content' => $encounter_check['reason'] ?? 'Hostile creatures detected!',
            'visibility' => 'public',
          ], $target_room_id);
        }
        break;

      case 'set_activity':
        // REQ 2292-2300: Set a character's exploration activity (persists each move).
        $activity = $params['activity'] ?? 'search';
        $legal_activities = [
          'avoid_notice', 'defend', 'detect_magic', 'follow_expert',
          'hustle', 'scout', 'investigate', 'repeat_spell', 'search',
          'sense_direction',
        ];
        if (!in_array($activity, $legal_activities, TRUE)) {
          return [
            'success' => FALSE,
            'result' => ['error' => "Unknown exploration activity: $activity"],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $game_state['exploration']['character_activities'][$actor_id] = $activity;
        $result = ['activity' => $activity];
        $events[] = GameEventLogger::buildEvent('set_activity', 'exploration', $actor_id, [
          'activity' => $activity,
        ]);
        break;

      case 'rest':
        $result = $this->processRest($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        // Short rest advances 10 minutes, long rest transitions to downtime.
        $rest_type = $params['rest_type'] ?? 'short';
        if ($rest_type === 'long') {
          $phase_transition = [
            'from' => 'exploration',
            'to' => 'downtime',
            'reason' => 'Long rest initiated.',
            'context' => ['rest_type' => 'long'],
          ];
        }
        else {
          $this->advanceExplorationTime($game_state, 10);
        }
        $events[] = GameEventLogger::buildEvent('rest', 'exploration', $actor_id, [
          'rest_type' => $rest_type,
        ]);
        break;

      case 'open_door':
      case 'open_passage':
        $result = $this->processOpenPassage($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent($type, 'exploration', $actor_id, [
          'target' => $target_id,
        ], NULL, $target_id);
        break;

      case 'cast_spell':
        $result = $this->processCastSpell($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        $events[] = GameEventLogger::buildEvent('cast_spell', 'exploration', $actor_id, [
          'spell' => $params['spell_name'] ?? 'unknown',
        ], $narration);

        // Queue spell cast for narration.
        $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => $actor_entity['name'] ?? $actor_id,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => sprintf('%s casts %s.', $actor_entity['name'] ?? $actor_id, $params['spell_name'] ?? 'a spell'),
          'visibility' => 'public',
          'mechanical_data' => [
            'spell_name' => $params['spell_name'] ?? 'unknown',
            'spell_level' => $params['spell_level'] ?? NULL,
          ],
        ]);
        break;

      case 'daily_prepare':
        // REQ 2304-2305: Daily preparation — prepare spells, channel focus, etc.
        // Takes 1 hour. Restores focus points and marks daily abilities as ready.
        $result = $this->processDailyPrepare($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('daily_prepare', 'exploration', $actor_id, [
          'prepared' => $result['prepared'] ?? [],
        ]);
        break;

      // -----------------------------------------------------------------------
      // REQ 1553–1563: Treat Wounds [Exploration, 10 min, Trained, healer's tools]
      // -----------------------------------------------------------------------
      case 'treat_wounds': {
        $result = $this->processTreatWounds($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $this->advanceExplorationTime($game_state, 10);
        $events[] = GameEventLogger::buildEvent('treat_wounds', 'exploration', $actor_id, [
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'healed' => $result['healed'] ?? 0,
        ], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1563–1568: Treat Disease [Downtime, 8 hrs, Trained, healer's tools]
      // -----------------------------------------------------------------------
      case 'treat_disease': {
        $result = $this->processTreatDisease($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        // 8 hours of effort is tracked; downtime phase handles the rest period.
        $events[] = GameEventLogger::buildEvent('treat_disease', 'exploration', $actor_id, [
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'upgraded' => $result['upgraded'] ?? FALSE,
        ], NULL, $target_id);
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
          $rk_svc = new RecallKnowledgeService(new DcAdjustmentService());
          $dc_result_rk = $rk_svc->computeDc(
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

        // REQ 2329: Block re-attempts on same target until new info is found.
        $attempt_key_rk = $actor_id . ':' . ($target_id ?? 'general');
        if (!empty($game_state['recall_knowledge_attempts'][$attempt_key_rk])) {
          return ['success' => FALSE, 'result' => ['error' => 'Cannot re-attempt Recall Knowledge on the same target without new information.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $game_state['recall_knowledge_attempts'][$attempt_key_rk] = TRUE;

        $d20_rk = $this->numberGenerationService->rollPathfinderDie(20);
        $total_rk = $d20_rk + $skill_bonus_rk;
        $degree_rk = $this->calculateDegreeOfSuccess($total_rk, $dc_rk, $d20_rk);

        switch ($degree_rk) {
          case 'critical_success':
            $player_msg_rk = 'You recall detailed information about the subject.';
            $info_rk = $params['known_info'] ?? NULL;
            $bonus_rk = $params['bonus_detail'] ?? NULL;
            break;

          case 'success':
            $player_msg_rk = 'You recall accurate information about the subject.';
            $info_rk = $params['known_info'] ?? NULL;
            $bonus_rk = NULL;
            break;

          case 'failure':
            $player_msg_rk = 'You fail to recall anything useful.';
            $info_rk = NULL;
            $bonus_rk = NULL;
            break;

          case 'critical_failure':
          default:
            // REQ 1594: Crit fail returns false info presented as truthful.
            $player_msg_rk = 'You recall information about the subject.';
            $info_rk = $params['false_info'] ?? NULL;
            $bonus_rk = NULL;
            break;
        }

        $result = [
          'degree' => $degree_rk,
          'skill_used' => $skill_used_rk,
          'dc' => $dc_rk,
          'd20' => $d20_rk,
          'total' => $total_rk,
          'player_facing_message' => $player_msg_rk,
          'info' => $info_rk,
          'bonus_detail' => $bonus_rk,
          'secret' => TRUE,
        ];
        $events[] = GameEventLogger::buildEvent('recall_knowledge', 'exploration', $actor_id, ['skill_used' => $skill_used_rk, 'degree' => $degree_rk], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // Decipher Writing [Exploration, Secret, Trained] (dc-cr-decipher-identify-learn)
      // Skills: Arcana (arcane/esoteric), Occultism (metaphysical/occult),
      //         Religion (religious/divine), Society (coded/legal/historical).
      // Timing: 1 min/page standard; 60 min/page for ciphers.
      // Degrees: Crit Success = full meaning; Success = true meaning (coded = summary);
      //          Failure = blocked + –2 circumstance retry penalty; Crit Fail = false.
      // -----------------------------------------------------------------------
      case 'decipher_writing': {
        $dw_params = array_merge($params, [
          'text_id'       => $target_id ?? ($params['text_id'] ?? 'text_unknown'),
          'skill_used'    => $params['skill_used'] ?? 'society',
          'skill_bonus'   => (int) ($params['skill_bonus'] ?? 0),
        ]);
        $result_dw = $this->knowledgeAcquisition->processDecipherWriting(
          (string) $actor_id, $dw_params
        );
        $this->advanceExplorationTime($game_state, $result_dw['time_cost_minutes'] ?? 1);
        $result = $result_dw;
        $events[] = GameEventLogger::buildEvent(
          'decipher_writing', 'exploration', $actor_id,
          ['degree' => $result_dw['degree'], 'skill_used' => $result_dw['skill_used'] ?? NULL, 'is_false' => $result_dw['is_false']],
          NULL, $target_id
        );
        break;
      }

      // -----------------------------------------------------------------------
      // Identify Magic [Exploration, Trained] (dc-cr-decipher-identify-learn)
      // Skills: Arcana (arcane), Nature (primal), Occultism (occult), Religion (divine).
      // Wrong-tradition: +5 DC penalty (not blocked).
      // Degrees: Crit Success = full ID + bonus fact; Success = full ID;
      //          Failure = 1-day block same item; Crit Fail = false ID (secret).
      // -----------------------------------------------------------------------
      case 'identify_magic': {
        $im_params = array_merge($params, [
          'item_id'     => $target_id ?? ($params['item_id'] ?? 'item_unknown'),
          'skill_used'  => $params['skill_used'] ?? 'arcana',
          'skill_bonus' => (int) ($params['skill_bonus'] ?? 0),
        ]);
        $result_im = $this->knowledgeAcquisition->processIdentifyMagic(
          (string) $actor_id, $im_params
        );
        $this->advanceExplorationTime($game_state, $result_im['time_cost_minutes'] ?? 10);
        $result = $result_im;
        $events[] = GameEventLogger::buildEvent(
          'identify_magic', 'exploration', $actor_id,
          ['degree' => $result_im['degree'], 'tradition_match' => $result_im['tradition_match'] ?? TRUE, 'is_false' => $result_im['is_false'] ?? FALSE],
          NULL, $target_id
        );
        break;
      }

      // -----------------------------------------------------------------------
      // Learn a Spell [Exploration, Trained] (dc-cr-decipher-identify-learn)
      // Cost: spell_rank × 10 gp (deducted immediately; refunded on Failure).
      // Degrees: Crit Success = learn + refund 50%; Success = learn;
      //          Failure = NOT learned, NO cost; Crit Fail = not learned + cost lost.
      // -----------------------------------------------------------------------
      case 'learn_a_spell': {
        $las_actor_entity = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
        $las_entity_val   = $las_actor_entity ?: [];
        $las_params = array_merge($params, [
          'spell_id'    => $target_id ?? ($params['spell_id'] ?? 'spell_unknown'),
          'skill_used'  => $params['skill_used'] ?? 'arcana',
          'skill_bonus' => (int) ($params['skill_bonus'] ?? 0),
        ]);
        $result_las = $this->knowledgeAcquisition->processLearnASpell(
          (string) $actor_id,
          (string) $campaign_id,
          $las_entity_val,
          $las_params
        );
        $this->advanceExplorationTime($game_state, $result_las['time_cost_minutes'] ?? 60);
        $result = $result_las;
        $events[] = GameEventLogger::buildEvent(
          'learn_a_spell', 'exploration', $actor_id,
          ['degree' => $result_las['degree'], 'spell_learned' => $result_las['spell_learned'] ?? FALSE, 'gp_spent' => $result_las['gp_spent'] ?? 0],
          NULL, $target_id
        );
        break;
      }

      // -----------------------------------------------------------------------
      // AC-003: Prepare Spells [Exploration, part of daily_prepare]
      // Allows a prepared caster to assign spells to specific slot levels.
      // -----------------------------------------------------------------------
      case 'prepare_spell': {
        $entity_ps = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
        if (!$entity_ps) {
          return ['success' => FALSE, 'result' => ['error' => 'Character not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $casting_type_ps = $entity_ps['stats']['casting_type'] ?? 'spontaneous';
        if ($casting_type_ps !== 'prepared') {
          return ['success' => FALSE, 'result' => ['error' => 'Only prepared casters can prepare spells in advance.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        // params['prepared_spells']: {slot_level: [spell_name, ...], ...}
        $new_prepared_ps = $params['prepared_spells'] ?? [];
        if (!isset($entity_ps['state'])) {
          $entity_ps['state'] = [];
        }
        $entity_ps['state']['prepared_spells'] = $new_prepared_ps;

        $this->persistDungeonData($campaign_id, $dungeon_data);
        $events[] = GameEventLogger::buildEvent('prepare_spell', 'exploration', $actor_id, ['slot_count' => count($new_prepared_ps)]);
        break;
      }

      // -----------------------------------------------------------------------
      // AC-007: Refocus [Exploration, 10 minutes]
      // Restores 1 Focus Point (up to max 3).
      // -----------------------------------------------------------------------
      case 'refocus': {
        $entity_rf = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
        if (!$entity_rf) {
          return ['success' => FALSE, 'result' => ['error' => 'Character not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $fp_max_rf = (int) ($entity_rf['stats']['focus_points_max'] ?? 3);
        $fp_current_rf = (int) ($entity_rf['state']['focus_points'] ?? 0);

        if ($fp_current_rf >= $fp_max_rf) {
          $result = ['focus_points' => $fp_current_rf, 'restored' => 0, 'message' => 'Focus pool already full.'];
        }
        else {
          $fp_new_rf = min($fp_max_rf, $fp_current_rf + 1);
          if (!isset($entity_rf['state'])) {
            $entity_rf['state'] = [];
          }
          $entity_rf['state']['focus_points'] = $fp_new_rf;
          $this->persistDungeonData($campaign_id, $dungeon_data);
          $result = ['focus_points' => $fp_new_rf, 'restored' => 1];
        }

        // Refocus takes 10 minutes.
        $this->advanceExplorationTime($game_state, 10);
        $events[] = GameEventLogger::buildEvent('refocus', 'exploration', $actor_id, ['focus_points' => $result['focus_points']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1591: Squeeze [Exploration, ~1 min, Acrobatics]
      // Move through tight passages that are too small to walk through normally.
      // -----------------------------------------------------------------------
      case 'squeeze': {
        $dc           = (int) ($params['dc'] ?? 15);
        $acrobatics   = (int) ($params['acrobatics_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20          = $this->numberGenerationService->rollPathfinderDie(20);
        $total        = $d20 + $acrobatics;
        $degree       = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $squeezed   = in_array($degree, ['success', 'critical_success'], TRUE);
        $stuck      = ($degree === 'critical_failure');

        if ($stuck) {
          $game_state['exploration']['squeeze_stuck_' . $actor_id] = TRUE;
        }

        $this->advanceExplorationTime($game_state, 1);
        $result = ['squeezed' => $squeezed, 'stuck' => $stuck, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('squeeze', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1612: Borrow an Arcane Spell [Exploration, ~10 min, Arcana]
      // Study another wizard's spellbook to prepare a spell you don't know.
      // -----------------------------------------------------------------------
      case 'borrow_arcane_spell': {
        $entity_bas = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
        if (!$entity_bas) {
          return ['success' => FALSE, 'result' => ['error' => 'Character not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        if ($this->isBorrowArcaneSpellRetryBlocked($entity_bas)) {
          return ['success' => FALSE, 'result' => ['error' => 'Borrow Arcane Spell cannot be retried until the next daily preparation cycle.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $arcana_rank = $this->resolveArcanaProficiencyRank($params, $entity_bas);
        if ($arcana_rank < 1) {
          return ['success' => FALSE, 'result' => ['error' => 'Borrow Arcane Spell requires Trained Arcana.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        if (!$this->isArcanePreparedSpellcaster($entity_bas, $params)) {
          return ['success' => FALSE, 'result' => ['error' => 'Borrow Arcane Spell requires an arcane prepared spellcaster.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $dc         = (int) ($params['dc'] ?? 15);
        $arcana     = (int) ($params['arcana_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20        = $this->numberGenerationService->rollPathfinderDie(20);
        $total      = $d20 + $arcana;
        $degree     = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $borrowed   = in_array($degree, ['success', 'critical_success'], TRUE);
        $spell_name = $params['spell_name'] ?? '';

        if ($borrowed) {
          $game_state['exploration']['borrowed_spell_' . $actor_id] = $spell_name;
          $entity_bas['state']['borrowed_arcane_spell'] = [
            'spell_name' => $spell_name,
            'available_for_preparation' => TRUE,
          ];
          $this->clearBorrowArcaneSpellRetryBlock($entity_bas);
        }
        else {
          $this->setBorrowArcaneSpellRetryBlocked($entity_bas);
        }

        $this->advanceExplorationTime($game_state, 10);
        $result = [
          'borrowed' => $borrowed,
          'spell' => $spell_name,
          'degree' => $degree,
          'roll' => $total,
          'dc' => $dc,
          'slot_remains_open' => !$borrowed,
          'retry_blocked_until_next_prep' => !$borrowed,
        ];
        $events[] = GameEventLogger::buildEvent('borrow_arcane_spell', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1633: Repair [Exploration, ~10 min, Crafting]
      // Restore HP to a damaged item using Repair toolkit.
      // -----------------------------------------------------------------------
      case 'repair': {
        $dc         = (int) ($params['dc'] ?? 15);
        $crafting   = (int) ($params['crafting_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20        = $this->numberGenerationService->rollPathfinderDie(20);
        $total      = $d20 + $crafting;
        $degree     = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $repaired_hp = 0;
        switch ($degree) {
          case 'critical_success':
            $repaired_hp = 20;
            break;
          case 'success':
            $repaired_hp = 10;
            break;
        }

        $this->advanceExplorationTime($game_state, 10);
        $result = ['repaired_hp' => $repaired_hp, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('repair', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1641: Identify Alchemy [Exploration, ~10 min, Crafting]
      // Identify an alchemical item using the Identify Alchemy action.
      // -----------------------------------------------------------------------
      case 'identify_alchemy': {
        $dc        = (int) ($params['dc'] ?? 15);
        $crafting  = (int) ($params['crafting_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20       = $this->numberGenerationService->rollPathfinderDie(20);
        $total     = $d20 + $crafting;
        $degree    = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $identified = in_array($degree, ['success', 'critical_success'], TRUE);
        $item_id    = $params['item_id'] ?? '';

        if ($identified && !empty($item_id)) {
          $game_state['exploration']['identified_alchemy_' . $item_id] = TRUE;
        }

        $this->advanceExplorationTime($game_state, 10);
        $result = ['identified' => $identified, 'item_id' => $item_id, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('identify_alchemy', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1660: Impersonate [Exploration, ~10 min, Deception]
      // Adopt a disguise; contested by Perception of observers.
      // -----------------------------------------------------------------------
      case 'impersonate': {
        $dc        = (int) ($params['dc'] ?? 15);
        $deception = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20       = $this->numberGenerationService->rollPathfinderDie(20);
        $total     = $d20 + $deception;
        $degree    = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $disguised = in_array($degree, ['success', 'critical_success'], TRUE);

        if ($disguised) {
          $game_state['exploration']['impersonating_' . $actor_id] = TRUE;
        }

        $this->advanceExplorationTime($game_state, 10);
        $result = ['disguised' => $disguised, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('impersonate', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1665: Lie [Exploration, immediate, Deception]
      // Attempt to deceive a creature into believing a false statement.
      // -----------------------------------------------------------------------
      case 'lie': {
        $base_dc   = (int) ($params['dc'] ?? 15);
        $dc_context = $this->applyNpcAttitudeToSocialDc($base_dc, $params, $target_id, $dungeon_data);
        $dc        = $dc_context['dc'];
        $deception = (int) ($params['deception_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20       = $this->numberGenerationService->rollPathfinderDie(20);
        $total     = $d20 + $deception;
        $degree    = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $believed = in_array($degree, ['success', 'critical_success'], TRUE);

        $result = [
          'believed' => $believed,
          'degree' => $degree,
          'roll' => $total,
          'dc' => $dc,
          'base_dc' => $base_dc,
          'attitude_dc_delta' => $dc_context['delta'],
        ];
        if ($dc_context['attitude'] !== NULL) {
          $result['npc_attitude'] = $dc_context['attitude'];
        }
        $events[] = GameEventLogger::buildEvent('lie', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1700: Command an Animal [Exploration, ~1 min, Nature]
      // Direct a trained animal companion or untrained beast.
      // Trained companions get DC − 5.
      // -----------------------------------------------------------------------
      case 'command_animal': {
        $dc       = (int) ($params['dc'] ?? 15);
        if (!empty($params['is_trained_companion'])) {
          $dc -= 5;
        }
        $nature   = (int) ($params['nature_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20      = $this->numberGenerationService->rollPathfinderDie(20);
        $total    = $d20 + $nature;
        $degree   = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $obeyed = in_array($degree, ['success', 'critical_success'], TRUE);

        $this->advanceExplorationTime($game_state, 1);
        $result = ['obeyed' => $obeyed, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('command_animal', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2384–2388: Disable a Hazard [2 actions, skill vs disable_dc]
      // -----------------------------------------------------------------------
      case 'disable_hazard': {
        $hazard_id = $params['hazard_id'] ?? NULL;
        if (!$hazard_id) {
          return ['success' => FALSE, 'result' => ['error' => 'disable_hazard requires params.hazard_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
        if (!$hazard_ref) {
          return ['success' => FALSE, 'result' => ['error' => "Hazard '$hazard_id' not found."], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
        $skill_rank  = (int) ($params['skill_proficiency_rank'] ?? 0);
        $disable_result = $this->hazardService->disableHazard($hazard_ref, $skill_bonus, $skill_rank, $params);

        if ($disable_result['blocked']) {
          return ['success' => FALSE, 'result' => ['error' => $disable_result['blocked_reason']], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        // Award XP if disabled.
        $xp_awarded = 0;
        if ($disable_result['disabled']) {
          $party_level = (int) ($game_state['party_level'] ?? 1);
          $xp_awarded = $this->hazardService->awardHazardXp($game_state, $hazard_ref, $party_level);
        }
        // Complex hazard trigger may start encounter.
        if ($disable_result['triggered'] && ($hazard_ref['complexity'] ?? 'simple') === 'complex') {
          $phase_transition = ['from' => 'exploration', 'to' => 'encounter', 'reason' => sprintf('%s triggered!', $hazard_ref['name'] ?? $hazard_id)];
        }

        $result = $disable_result + ['xp_awarded' => $xp_awarded];
        $events[] = GameEventLogger::buildEvent('disable_hazard', 'exploration', $actor_id, ['hazard_id' => $hazard_id, 'degree' => $disable_result['degree'], 'disabled' => $disable_result['disabled']]);
        $this->persistDungeonData($campaign_id, $dungeon_data);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2393–2394: Counteract a Magical Hazard
      // -----------------------------------------------------------------------
      case 'counteract_hazard': {
        $hazard_id = $params['hazard_id'] ?? NULL;
        if (!$hazard_id) {
          return ['success' => FALSE, 'result' => ['error' => 'counteract_hazard requires params.hazard_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
        if (!$hazard_ref) {
          return ['success' => FALSE, 'result' => ['error' => "Hazard '$hazard_id' not found."], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $d20_ct = $this->numberGenerationService->rollPathfinderDie(20);
        $total_ct = $d20_ct + (int) ($params['skill_bonus'] ?? 0);
        $counteract_level = (int) ($params['counteract_level'] ?? 1);

        $ct_result = $this->hazardService->counteractMagicalHazard($hazard_ref, $counteract_level, $total_ct, $d20_ct);
        if ($ct_result['blocked']) {
          return ['success' => FALSE, 'result' => ['error' => $ct_result['blocked_reason']], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $xp_awarded = 0;
        if ($ct_result['counteracted']) {
          $party_level = (int) ($game_state['party_level'] ?? 1);
          $xp_awarded = $this->hazardService->awardHazardXp($game_state, $hazard_ref, $party_level);
        }

        $result = $ct_result + ['roll' => $d20_ct, 'total' => $total_ct, 'xp_awarded' => $xp_awarded];
        $events[] = GameEventLogger::buildEvent('counteract_hazard', 'exploration', $actor_id, ['hazard_id' => $hazard_id, 'degree' => $ct_result['degree']]);
        $this->persistDungeonData($campaign_id, $dungeon_data);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2390–2392: Attack a Hazard (Destroy it via HP damage)
      // -----------------------------------------------------------------------
      case 'attack_hazard': {
        $hazard_id = $params['hazard_id'] ?? NULL;
        if (!$hazard_id) {
          return ['success' => FALSE, 'result' => ['error' => 'attack_hazard requires params.hazard_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
        if (!$hazard_ref) {
          return ['success' => FALSE, 'result' => ['error' => "Hazard '$hazard_id' not found."], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $raw_damage = (int) ($params['damage'] ?? 0);
        $dmg_result = $this->hazardService->applyDamageToHazard($hazard_ref, $raw_damage);

        $xp_awarded = 0;
        if ($dmg_result['destroyed']) {
          $party_level = (int) ($game_state['party_level'] ?? 1);
          $xp_awarded = $this->hazardService->awardHazardXp($game_state, $hazard_ref, $party_level);
        }
        if ($dmg_result['triggered'] && ($hazard_ref['complexity'] ?? 'simple') === 'complex') {
          $phase_transition = ['from' => 'exploration', 'to' => 'encounter', 'reason' => sprintf('%s triggered!', $hazard_ref['name'] ?? $hazard_id)];
        }

        $result = $dmg_result + ['xp_awarded' => $xp_awarded];
        $events[] = GameEventLogger::buildEvent('attack_hazard', 'exploration', $actor_id, ['hazard_id' => $hazard_id, 'damage' => $raw_damage, 'destroyed' => $dmg_result['destroyed']]);
        $this->persistDungeonData($campaign_id, $dungeon_data);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2397–2409: Invest a magic item (exploration / daily prep).
      // -----------------------------------------------------------------------
      case 'invest_item': {
        $item_id   = $params['item_instance_id'] ?? NULL;
        $item_data = $params['item_data'] ?? [];
        if (!$item_id) {
          return ['success' => FALSE, 'result' => ['error' => 'invest_item requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $invest_result = $this->magicItemService->investItem($actor_id, $item_id, $item_data, $game_state);
        $result = $invest_result;
        $events[] = GameEventLogger::buildEvent('invest_item', 'exploration', $actor_id, ['item_instance_id' => $item_id, 'success' => $invest_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2410–2425: Activate a magic item.
      // -----------------------------------------------------------------------
      case 'activate_item': {
        $item_id   = $params['item_instance_id'] ?? NULL;
        $item_data = $params['item_data'] ?? [];
        $component = $params['component'] ?? 'command';
        if (!$item_id) {
          return ['success' => FALSE, 'result' => ['error' => 'activate_item requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state = $this->characterStateService->getState($actor_id);
        $activate_result = $this->magicItemService->activateItem($actor_id, $item_id, $item_data, $char_state, $game_state);
        $result = $activate_result;
        $events[] = GameEventLogger::buildEvent('activate_item', 'exploration', $actor_id, ['item_instance_id' => $item_id, 'success' => $activate_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2416–2420: Sustain an active activation.
      // -----------------------------------------------------------------------
      case 'sustain_activation': {
        $item_id = $params['item_instance_id'] ?? NULL;
        if (!$item_id) {
          return ['success' => FALSE, 'result' => ['error' => 'sustain_activation requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $sustain_result = $this->magicItemService->sustainActivation($actor_id, $item_id, $game_state);
        $result = $sustain_result;
        $events[] = GameEventLogger::buildEvent('sustain_activation', 'exploration', $actor_id, ['item_instance_id' => $item_id, 'success' => $sustain_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2421–2424: Dismiss an active activation.
      // -----------------------------------------------------------------------
      case 'dismiss_activation': {
        $item_id = $params['item_instance_id'] ?? NULL;
        if (!$item_id) {
          return ['success' => FALSE, 'result' => ['error' => 'dismiss_activation requires params.item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $dismiss_result = $this->magicItemService->dismissActivation($actor_id, $item_id, $game_state);
        $result = $dismiss_result;
        $events[] = GameEventLogger::buildEvent('dismiss_activation', 'exploration', $actor_id, ['item_instance_id' => $item_id]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2478–2490: Cast from scroll.
      // -----------------------------------------------------------------------
      case 'cast_from_scroll': {
        $scroll_id = $params['scroll_instance_id'] ?? NULL;
        $scroll_data = $params['scroll_data'] ?? [];
        if (!$scroll_id) {
          return ['success' => FALSE, 'result' => ['error' => 'cast_from_scroll requires params.scroll_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state = $this->characterStateService->getState($actor_id);
        $scroll_result = $this->magicItemService->castFromScroll($scroll_data, $char_state, $game_state);
        $result = $scroll_result;
        $events[] = GameEventLogger::buildEvent('cast_from_scroll', 'exploration', $actor_id, ['scroll_instance_id' => $scroll_id, 'success' => $scroll_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2501–2510: Prepare a staff (daily preparations).
      // -----------------------------------------------------------------------
      case 'prepare_staff': {
        $staff_id   = $params['staff_instance_id'] ?? NULL;
        $staff_data = $params['staff_data'] ?? [];
        if (!$staff_id) {
          return ['success' => FALSE, 'result' => ['error' => 'prepare_staff requires params.staff_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state = $this->characterStateService->getState($actor_id);
        $prepare_result = $this->magicItemService->prepareStaff($staff_id, $actor_id, $char_state, $game_state);
        $result = $prepare_result;
        $events[] = GameEventLogger::buildEvent('prepare_staff', 'exploration', $actor_id, ['staff_instance_id' => $staff_id, 'success' => $prepare_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2511–2520: Cast from staff.
      // -----------------------------------------------------------------------
      case 'cast_from_staff': {
        $staff_id   = $params['staff_instance_id'] ?? NULL;
        $staff_data = $params['staff_data'] ?? [];
        $spell_level = (int) ($params['spell_level'] ?? 1);
        if (!$staff_id) {
          return ['success' => FALSE, 'result' => ['error' => 'cast_from_staff requires params.staff_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $spell_id   = $params['spell_id'] ?? '';
        $char_state = $this->characterStateService->getState($actor_id);
        $cast_result = $this->magicItemService->castFromStaff($staff_id, $actor_id, $staff_data, $spell_id, $spell_level, $char_state, $game_state);
        $result = $cast_result;
        $events[] = GameEventLogger::buildEvent('cast_from_staff', 'exploration', $actor_id, ['staff_instance_id' => $staff_id, 'spell_level' => $spell_level, 'success' => $cast_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2521–2530: Cast from wand.
      // -----------------------------------------------------------------------
      case 'cast_from_wand': {
        $wand_id   = $params['wand_instance_id'] ?? NULL;
        $wand_data = $params['wand_data'] ?? [];
        if (!$wand_id) {
          return ['success' => FALSE, 'result' => ['error' => 'cast_from_wand requires params.wand_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state = $this->characterStateService->getState($actor_id);
        $cast_result = $this->magicItemService->castFromWand($wand_id, $wand_data, $char_state, $game_state);
        $result = $cast_result;
        $events[] = GameEventLogger::buildEvent('cast_from_wand', 'exploration', $actor_id, ['wand_instance_id' => $wand_id, 'success' => $cast_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2531–2535: Overcharge wand (risky extra use).
      // -----------------------------------------------------------------------
      case 'overcharge_wand': {
        $wand_id   = $params['wand_instance_id'] ?? NULL;
        $wand_data = $params['wand_data'] ?? [];
        if (!$wand_id) {
          return ['success' => FALSE, 'result' => ['error' => 'overcharge_wand requires params.wand_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $overcharge_result = $this->magicItemService->overchargeWand($wand_id, $game_state);
        $result = $overcharge_result;
        $events[] = GameEventLogger::buildEvent('overcharge_wand', 'exploration', $actor_id, ['wand_instance_id' => $wand_id, 'success' => $overcharge_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2536–2545: Craft and place a snare.
      // -----------------------------------------------------------------------
      case 'craft_snare': {
        $snare_data  = $params['snare_data'] ?? [];
        $location_id = $params['location_id'] ?? ($game_state['active_room_id'] ?? 'unknown');
        $char_state  = $this->characterStateService->getState($actor_id);
        $snare_result = $this->magicItemService->craftSnare($snare_data, $char_state, $location_id, $game_state);
        $result = $snare_result;
        $events[] = GameEventLogger::buildEvent('craft_snare', 'exploration', $actor_id, ['location_id' => $location_id, 'success' => $snare_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2517: Disable a detected snare (Thievery check vs disable_dc).
      // -----------------------------------------------------------------------
      case 'disable_snare': {
        $snare_location_ds = $params['location_id'] ?? ($game_state['active_room_id'] ?? 'unknown');
        $snare_index_ds    = (int) ($params['snare_index'] ?? -1);
        $thievery_bonus_ds = (int) ($params['thievery_bonus'] ?? $params['skill_bonus'] ?? 0);
        $thievery_rank_ds  = (int) ($params['thievery_rank'] ?? $params['proficiency_rank'] ?? 0);
        $result = $this->magicItemService->disableSnare(
          $actor_id,
          $snare_location_ds,
          $snare_index_ds,
          $thievery_bonus_ds,
          $thievery_rank_ds,
          $game_state
        );
        $events[] = GameEventLogger::buildEvent('disable_snare', 'exploration', $actor_id, ['location_id' => $snare_location_ds, 'snare_index' => $snare_index_ds, 'success' => $result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2546–2548: Affix talisman to item.
      // -----------------------------------------------------------------------
      case 'affix_talisman': {
        $talisman_id          = $params['talisman_instance_id'] ?? NULL;
        $talisman_data        = $params['talisman_data'] ?? [];
        $host_item_instance_id = $params['target_item_instance_id'] ?? NULL;
        if (!$talisman_id || !$host_item_instance_id) {
          return ['success' => FALSE, 'result' => ['error' => 'affix_talisman requires params.talisman_instance_id and params.target_item_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $affix_result = $this->magicItemService->affixTalisman($host_item_instance_id, $talisman_data, $game_state);
        $result = $affix_result;
        $events[] = GameEventLogger::buildEvent('affix_talisman', 'exploration', $actor_id, ['talisman_instance_id' => $talisman_id, 'success' => $affix_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2549: Activate talisman.
      // -----------------------------------------------------------------------
      case 'activate_talisman': {
        $talisman_id = $params['talisman_instance_id'] ?? NULL;
        $host_item_id = $params['host_item_instance_id'] ?? $talisman_id;
        if (!$talisman_id) {
          return ['success' => FALSE, 'result' => ['error' => 'activate_talisman requires params.talisman_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $activate_result = $this->magicItemService->activateTalisman($host_item_id, $actor_id, $game_state);
        $result = $activate_result;
        $events[] = GameEventLogger::buildEvent('activate_talisman', 'exploration', $actor_id, ['talisman_instance_id' => $talisman_id, 'success' => $activate_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2474–2477: Apply oil to an item.
      // -----------------------------------------------------------------------
      case 'apply_oil': {
        $oil_id       = $params['oil_instance_id'] ?? NULL;
        $oil_data     = $params['oil_data'] ?? [];
        $target_item  = $params['target_item_data'] ?? [];
        if (!$oil_id) {
          return ['success' => FALSE, 'result' => ['error' => 'apply_oil requires params.oil_instance_id.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $oil_result = $this->magicItemService->applyOil($actor_id, $oil_id, $oil_data, $target_item, $game_state);
        $result = $oil_result;
        $events[] = GameEventLogger::buildEvent('apply_oil', 'exploration', $actor_id, ['oil_instance_id' => $oil_id, 'success' => $oil_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2455–2467: Etch a rune onto an item.
      // -----------------------------------------------------------------------
      case 'etch_rune': {
        $item_id   = $params['item_instance_id'] ?? NULL;
        $rune_type = $params['rune_type'] ?? NULL;
        $rune_data = $params['rune_data'] ?? [];
        if (!$item_id || !$rune_type) {
          return ['success' => FALSE, 'result' => ['error' => 'etch_rune requires params.item_instance_id and params.rune_type.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $char_state  = $this->characterStateService->getState($actor_id);
        $item_data   = $params['item_data'] ?? [];
        $etch_result = $this->magicItemService->etchRune($item_data, $rune_type, $rune_data, $char_state);
        $result = $etch_result;
        $events[] = GameEventLogger::buildEvent('etch_rune', 'exploration', $actor_id, ['item_instance_id' => $item_id, 'rune_type' => $rune_type, 'success' => $etch_result['success']]);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 2468–2473: Transfer a rune between two items.
      // -----------------------------------------------------------------------
      case 'transfer_rune': {
        $source_id   = $params['source_item_instance_id'] ?? NULL;
        $dest_id     = $params['dest_item_instance_id'] ?? NULL;
        $rune_type   = $params['rune_type'] ?? NULL;
        $rune_data   = $params['rune_data'] ?? [];
        if (!$source_id || !$dest_id || !$rune_type) {
          return ['success' => FALSE, 'result' => ['error' => 'transfer_rune requires source_item_instance_id, dest_item_instance_id, and rune_type.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $source_item = $params['source_item_data'] ?? [];
        $dest_item   = $params['dest_item_data'] ?? [];
        $char_state  = $this->characterStateService->getState($actor_id);
        $craft_bonus = (int) ($char_state['skills']['crafting']['bonus'] ?? 0);
        $transfer_result = $this->magicItemService->transferRune($source_item, $dest_item, $rune_type, $rune_data, $craft_bonus);
        $result = $transfer_result;
        $events[] = GameEventLogger::buildEvent('transfer_rune', 'exploration', $actor_id, ['source_item_instance_id' => $source_id, 'dest_item_instance_id' => $dest_id, 'rune_type' => $rune_type, 'success' => $transfer_result['success']]);
        break;
      }


      // Entertain an audience or attempt to earn tips.
      // -----------------------------------------------------------------------
      case 'perform': {
        $dc          = (int) ($params['dc'] ?? 15);
        $performance = (int) ($params['performance_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20         = $this->numberGenerationService->rollPathfinderDie(20);
        $total       = $d20 + $performance;
        $degree      = $this->calculateDegreeOfSuccess($total, $dc, $d20);

        $entertained = in_array($degree, ['success', 'critical_success'], TRUE);

        $result = ['entertained' => $entertained, 'degree' => $degree, 'roll' => $total, 'dc' => $dc];
        $events[] = GameEventLogger::buildEvent('perform', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1730: Sense Direction [Exploration, free activity] — Survival (Wis)
      // No check in clear conditions; DC 15 base; +10 supernatural darkness/fog;
      // +5 featureless planes. Crit Success also returns distance estimate.
      // -----------------------------------------------------------------------
      case 'sense_direction': {
        $condition = $params['condition'] ?? 'clear';

        // No check required in normal conditions.
        if ($condition === 'clear') {
          $result = [
            'direction_known' => TRUE,
            'distance_estimate' => FALSE,
            'degree' => 'auto_success',
            'dc' => NULL,
            'condition' => $condition,
          ];
          $events[] = GameEventLogger::buildEvent('sense_direction', 'exploration', $actor_id, $result);
          break;
        }

        $dc_base    = 15;
        $dc_mods    = ['supernatural' => 10, 'featureless_plane' => 5];
        $dc_sd      = $dc_base + ($dc_mods[$condition] ?? 0);
        $survival   = (int) ($params['survival_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20_sd     = $this->numberGenerationService->rollPathfinderDie(20);
        $total_sd   = $d20_sd + $survival;
        $degree_sd  = $this->calculateDegreeOfSuccess($total_sd, $dc_sd, $d20_sd);

        $direction_known   = in_array($degree_sd, ['success', 'critical_success'], TRUE);
        $distance_estimate = ($degree_sd === 'critical_success');

        $result = [
          'direction_known'   => $direction_known,
          'distance_estimate' => $distance_estimate,
          'degree'            => $degree_sd,
          'dc'                => $dc_sd,
          'roll'              => $total_sd,
          'condition'         => $condition,
        ];
        $events[] = GameEventLogger::buildEvent('sense_direction', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1733: Cover Tracks [Exploration, Trained] — Survival (Wis)
      // Character moves at half speed; sets a cover_tracks flag on the entity
      // with a Survival DC (10 + survival_bonus) for pursuers.
      // -----------------------------------------------------------------------
      case 'cover_tracks': {
        $proficiency_rank_ct = (int) ($params['proficiency_rank'] ?? 0);
        if ($proficiency_rank_ct < 1) {
          return [
            'success' => FALSE,
            'result'  => ['error' => 'Cover Tracks requires at least Trained proficiency in Survival.'],
            'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL,
          ];
        }

        $survival_ct = (int) ($params['survival_bonus'] ?? $params['skill_bonus'] ?? 0);
        // DC for pursuers = 10 + survival_bonus (standard "set DC" rule).
        $pursuer_dc_ct = 10 + $survival_ct;

        // Record the covered-tracks flag on the actor's entity state.
        if (!isset($game_state['entity_states'][$actor_id])) {
          $game_state['entity_states'][$actor_id] = [];
        }
        $game_state['entity_states'][$actor_id]['tracks_covered']    = TRUE;
        $game_state['entity_states'][$actor_id]['tracks_pursuer_dc'] = $pursuer_dc_ct;

        // Movement is at half speed this exploration turn.
        $this->advanceExplorationTime($game_state, 20);

        $result = [
          'tracks_covered' => TRUE,
          'pursuer_dc'     => $pursuer_dc_ct,
        ];
        $events[] = GameEventLogger::buildEvent('cover_tracks', 'exploration', $actor_id, $result);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1734–1737: Track [Exploration, Trained] — Survival (Wis)
      // DC from terrain × trail-age matrix.
      // Crit Success: follow at full speed + reveal next waypoint + bonus detail.
      // Success: follow at half speed + reveal next waypoint.
      // Failure: no progress; may retry in same area.
      // Crit Failure: trail permanently lost for this tracker.
      // -----------------------------------------------------------------------
      case 'track': {
        $proficiency_rank_tr = (int) ($params['proficiency_rank'] ?? 0);
        if ($proficiency_rank_tr < 1) {
          return [
            'success' => FALSE,
            'result'  => ['error' => 'Track requires at least Trained proficiency in Survival.'],
            'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL,
          ];
        }

        $trail_id_tr = $params['trail_id'] ?? ($target_id ?? 'trail_unknown');

        // Block retry on a permanently-lost trail (crit fail recorded).
        if (!empty($game_state['track_lost'][$actor_id . ':' . $trail_id_tr])) {
          return [
            'success' => FALSE,
            'result'  => ['error' => 'Trail permanently lost; cannot retry this specific track.'],
            'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL,
          ];
        }

        // Cover Tracks override: use the pursuer_dc if set.
        if (!empty($params['cover_tracks_pursuer_dc'])) {
          $dc_tr = (int) $params['cover_tracks_pursuer_dc'];
        }
        else {
          // Trail-age base DCs.
          $age_dc = [
            'recent'  => 15,   // < 1 hour
            'today'   => 20,   // < 1 day
            'week'    => 25,   // < 1 week
            'old'     => 30,   // 1 week +
          ];
          // Terrain modifiers.
          $terrain_mod = [
            'forest'  => 2,
            'desert'  => 5,
            'urban'   => 5,
            'default' => 0,
          ];
          $age_tr     = $params['trail_age'] ?? 'today';
          $terrain_tr = $params['terrain'] ?? 'default';
          $dc_tr = ($age_dc[$age_tr] ?? $age_dc['today'])
                 + ($terrain_mod[$terrain_tr] ?? $terrain_mod['default']);
        }

        $survival_tr = (int) ($params['survival_bonus'] ?? $params['skill_bonus'] ?? 0);
        $d20_tr      = $this->numberGenerationService->rollPathfinderDie(20);
        $total_tr    = $d20_tr + $survival_tr;
        $degree_tr   = $this->calculateDegreeOfSuccess($total_tr, $dc_tr, $d20_tr);

        $progress_tr      = FALSE;
        $full_speed_tr    = FALSE;
        $bonus_detail_tr  = NULL;

        switch ($degree_tr) {
          case 'critical_success':
            $progress_tr   = TRUE;
            $full_speed_tr = TRUE;
            $bonus_detail_tr = $params['bonus_trail_detail'] ?? NULL;
            break;

          case 'success':
            $progress_tr   = TRUE;
            $full_speed_tr = FALSE;
            break;

          case 'failure':
            // No progress; retry allowed.
            break;

          case 'critical_failure':
            // Trail permanently lost for this tracker.
            $game_state['track_lost'][$actor_id . ':' . $trail_id_tr] = TRUE;
            break;
        }

        $result = [
          'degree'       => $degree_tr,
          'progress'     => $progress_tr,
          'full_speed'   => $full_speed_tr,
          'bonus_detail' => $bonus_detail_tr,
          'dc'           => $dc_tr,
          'roll'         => $total_tr,
          'trail_id'     => $trail_id_tr,
          'next_waypoint' => $progress_tr ? ($params['next_waypoint'] ?? NULL) : NULL,
        ];
        $events[] = GameEventLogger::buildEvent('track', 'exploration', $actor_id, ['degree' => $degree_tr, 'trail_id' => $trail_id_tr, 'progress' => $progress_tr]);
        break;
      }
        return [
          'success' => FALSE,
          'result' => ['error' => "Unknown exploration action: $type"],
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
    $game_state['phase'] = 'exploration';
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['encounter_id'] = NULL;

    // Initialize exploration sub-state if not present.
    if (!isset($game_state['exploration'])) {
      $game_state['exploration'] = [
        'time_elapsed_minutes' => 0,
        'time_unit' => 'minutes',
        'character_activities' => [],
        'hustle_minutes' => [],
        'previous_room' => NULL,
      ];
    }
    // AC-001: Always ensure time_unit = minutes (e.g. returning from encounter).
    $game_state['exploration']['time_unit'] = 'minutes';
    unset($game_state['round']);

    // Queue phase entry for perception-filtered narration.
    $from_phase = $context['from_phase'] ?? 'none';
    if ($from_phase !== 'none') {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'action',
        'speaker' => 'GM',
        'speaker_type' => 'gm',
        'speaker_ref' => '',
        'content' => $from_phase === 'encounter'
          ? 'The encounter ends. The party returns to exploration.'
          : 'Exploration begins.',
        'visibility' => 'public',
        'mechanical_data' => ['from_phase' => $from_phase],
      ]);
    }

    return [
      GameEventLogger::buildEvent('phase_entered', 'exploration', NULL, [
        'from_phase' => $from_phase,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Snapshot exploration state so it can be restored when re-entering.
    // The exploration sub-state persists in game_state.exploration.
    return [
      GameEventLogger::buildEvent('phase_exited', 'exploration', NULL, [
        'time_elapsed' => $game_state['exploration']['time_elapsed_minutes'] ?? 0,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableActions(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    // In exploration, most actions are always available.
    $actions = ['move', 'interact', 'talk', 'search', 'set_activity', 'rest', 'sense_direction', 'cover_tracks', 'track'];

    // Transition is available if the current room has passable connections.
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;
    if ($active_room_id && !empty($dungeon_data['connections'])) {
      foreach ($dungeon_data['connections'] as $conn) {
        $from_room = $conn['from']['room_id'] ?? NULL;
        $to_room = $conn['to']['room_id'] ?? NULL;
        $passable = $conn['is_passable'] ?? FALSE;
        if ($passable && ($from_room === $active_room_id || $to_room === $active_room_id)) {
          $actions[] = 'transition';
          break;
        }
      }
    }

    return array_unique($actions);
  }

  // =========================================================================
  // Action processors (delegate to existing services where possible).
  // =========================================================================

  /**
   * Process a movement action (free movement during exploration).
   */
  protected function processMove(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $to_hex = $params['to_hex'] ?? NULL;
    if (!$to_hex || !isset($to_hex['q'], $to_hex['r'])) {
      return ['error' => 'Missing to_hex with q,r coordinates.'];
    }

    // Find and update the entity's placement in dungeon_data.
    $entity = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if (!$entity) {
      return ['error' => "Entity $actor_id not found."];
    }

    $from_hex = $entity['placement']['hex'] ?? ['q' => 0, 'r' => 0];
    $entity['placement']['hex'] = ['q' => (int) $to_hex['q'], 'r' => (int) $to_hex['r']];

    $mutations = [
      ['entity' => $actor_id, 'field' => 'placement.hex', 'from' => $from_hex, 'to' => $to_hex],
    ];
    $result = [
      'moved' => TRUE,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'mutations' => $mutations,
    ];

    $activity = $game_state['exploration']['character_activities'][$actor_id] ?? 'search';

    // GAP-2290: Wire calculateTravelSpeed() into processMove so terrain multipliers apply.
    // GAP-2292: avoid_notice and defend halve speed (same as difficult terrain).
    $base_speed = (int) ($entity['stats']['speed'] ?? 25);
    $terrain = $params['terrain'] ?? 'normal';
    // avoid_notice and defend activities halve movement speed (PF2E CRB exploration rules).
    if (in_array($activity, ['avoid_notice', 'defend'], TRUE)) {
      // Apply half-speed by treating terrain as at-least-difficult.
      $terrain = ($terrain === 'normal') ? 'difficult' : $terrain;
    }
    $travel_speed = $this->calculateTravelSpeed($base_speed, $terrain, $activity);
    $result['travel_speed'] = $travel_speed;

    // GAP-2292: avoid_notice substitutes Stealth for Initiative on encounter start.
    if ($activity === 'avoid_notice') {
      $result['initiative_skill'] = 'stealth';
    }

    // AC-002: While Searching, each hex moved (≈ 10 ft) triggers a Perception check.
    if ($activity === 'search') {
      $perception_bonus = $entity['stats']['perception'] ?? ($entity['state']['skills']['perception'] ?? 0);
      $roll = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $roll + (int) $perception_bonus;
      $room = $this->getActiveRoom($dungeon_data);
      $search_dc = $room['gameplay_state']['search_dc'] ?? 15;
      $degree = $this->calculateDegreeOfSuccess($total, $search_dc, $roll);
      $discoveries = [];
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $discoveries = $this->revealHiddenEntities($dungeon_data, $degree === 'critical_success');
      }
      $result['search_on_move'] = [
        'roll' => $roll,
        'total' => $total,
        'dc' => $search_dc,
        'degree' => $degree,
        'discoveries' => $discoveries,
      ];
    }

    // AC-002: Hustle doubles speed; apply fatigue after HUSTLE_FATIGUE_MINUTES.
    if ($activity === 'hustle') {
      if (!isset($game_state['exploration']['hustle_minutes'][$actor_id])) {
        $game_state['exploration']['hustle_minutes'][$actor_id] = 0;
      }
      // Each move in Hustle counts as 1 exploration minute.
      $game_state['exploration']['hustle_minutes'][$actor_id]++;
      $hustle_elapsed = $game_state['exploration']['hustle_minutes'][$actor_id];
      if ($hustle_elapsed >= self::HUSTLE_FATIGUE_MINUTES
        && empty($entity['state']['conditions']['fatigued'])) {
        $entity['state']['conditions']['fatigued'] = TRUE;
        $result['fatigue_applied'] = TRUE;
        $result['hustle_minutes_elapsed'] = $hustle_elapsed;
        $mutations[] = ['entity' => $actor_id, 'field' => 'state.conditions.fatigued', 'from' => FALSE, 'to' => TRUE];
      }
      $result['speed_bonus'] = 2.0;
      $result['hustle_minutes_elapsed'] = $hustle_elapsed;
    }

    // AC-004: Resolve light level at destination hex.
    $result['visibility'] = $this->resolveCharacterVisibility($entity, $to_hex, $dungeon_data);

    $result['mutations'] = $mutations;

    // REQ 2374: Auto-secret Perception vs stealth_dc for non-min-prof hazards on movement.
    // REQ 2377: Passive hazards trigger if undetected on entry.
    $is_searching = ($activity === 'search');
    $perception_bonus_hz = (int) ($entity['stats']['perception'] ?? ($entity['state']['skills']['perception'] ?? 0));
    $perception_rank_hz = 1; // Default Trained; caller should pass actual rank in params.
    if (isset($params['perception_proficiency_rank'])) {
      $perception_rank_hz = (int) $params['perception_proficiency_rank'];
    }

    $room_hazards = $this->hazardService->getRoomHazards($dungeon_data);
    $hazard_events = [];
    foreach ($room_hazards as $hazard_snapshot) {
      $hazard_id = $hazard_snapshot['instance_id'] ?? $hazard_snapshot['id'] ?? NULL;
      if (!$hazard_id) {
        continue;
      }
      $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
      if (!$hazard_ref) {
        continue;
      }
      if (!empty($hazard_ref['state']['detected'])) {
        continue;
      }

      // Auto-detect roll.
      $detect = $this->hazardService->rollHazardDetection(
        $hazard_ref,
        $perception_bonus_hz,
        $perception_rank_hz,
        $is_searching
      );
      if ($detect['detected']) {
        $this->hazardService->markDetected($hazard_ref);
        $hazard_events[] = ['type' => 'hazard_detected', 'instance_id' => $hazard_id, 'name' => $hazard_ref['name'] ?? $hazard_id, 'roll' => $detect['roll'], 'total' => $detect['total'], 'dc' => $detect['dc']];
      }
      elseif (!$detect['blocked'] && ($hazard_ref['trigger']['type'] ?? 'passive') === 'passive') {
        // REQ 2377: Passive undetected hazard triggers.
        $trigger = $this->hazardService->triggerHazard($hazard_ref);
        if ($trigger['triggered']) {
          $hazard_events[] = ['type' => 'hazard_triggered', 'instance_id' => $hazard_id, 'name' => $hazard_ref['name'] ?? $hazard_id, 'effect' => $trigger['effect']];
        }
      }
    }
    if (!empty($hazard_events)) {
      $result['hazard_events'] = $hazard_events;
    }

    // REQ AC: Detect Magic activity reveals magical hazards on movement (no proficiency gate).
    if ($activity === 'detect_magic') {
      foreach ($this->hazardService->getRoomHazards($dungeon_data) as $hazard_snapshot) {
        $hazard_id = $hazard_snapshot['instance_id'] ?? $hazard_snapshot['id'] ?? NULL;
        if (!$hazard_id) {
          continue;
        }
        $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
        if (!$hazard_ref || empty($hazard_ref['is_magical']) || !empty($hazard_ref['state']['detected'])) {
          continue;
        }
        $this->hazardService->markDetected($hazard_ref);
        $hazard_events[] = ['type' => 'magical_hazard_revealed', 'instance_id' => $hazard_id, 'name' => $hazard_ref['name'] ?? $hazard_id, 'via' => 'detect_magic'];
      }
      if (!empty($hazard_events)) {
        $result['hazard_events'] = $hazard_events;
      }
    }

    // REQ 2517–2518: Snare detection on movement (per hex).
    // Active search → rolls Perception vs detection_dc; passive → auto-fails expert+ snares.
    $snare_location = $params['location_id'] ?? ($game_state['current_location_id'] ?? NULL);
    if ($snare_location !== NULL && !empty($game_state['snares'][$snare_location])) {
      $perception_bonus_sn = (int) ($entity['stats']['perception'] ?? ($entity['state']['skills']['perception'] ?? 0));
      $perception_rank_sn  = (int) ($params['perception_proficiency_rank'] ?? 1);
      $is_searching_sn     = ($activity === 'search');
      $snare_detections    = $this->magicItemService->detectSnareAtHex(
        $actor_id,
        $snare_location,
        $to_hex,
        $perception_bonus_sn,
        $perception_rank_sn,
        $is_searching_sn,
        $game_state
      );
      if (!empty($snare_detections)) {
        $result['snare_detections'] = $snare_detections;
      }
    }

    // Persist to DB.
    $this->persistDungeonData($campaign_id, $dungeon_data);

    return $result;
  }

  /**
   * Process an interact action (doors, objects, containers).
   */
  protected function processInteract(string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $interaction_type = $params['interaction_type'] ?? 'generic';

    // For now, delegate generic interactions.
    // Future: check for traps, locked doors, containers with loot.
    return [
      'interacted' => TRUE,
      'interaction_type' => $interaction_type,
      'target' => $target_id,
      'mutations' => [],
    ];
  }

  /**
   * Process a talk action (delegates to AI GM via RoomChatService).
   */
  protected function processTalk(?string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $message = $params['message'] ?? '';
    $room_id = $dungeon_data['active_room_id'] ?? NULL;
    $character_id = $params['character_id'] ?? NULL;
    $speaker = 'Player';

    if ($actor_id) {
      $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
      $speaker = $actor_entity['state']['metadata']['display_name']
        ?? $actor_entity['name']
        ?? $actor_id;
    }

    if (!$room_id) {
      return ['error' => 'No active room set.'];
    }

    // Delegate to the existing RoomChatService for AI GM interaction.
    // The chat service handles: AI prompt building, response parsing,
    // gameplay action extraction, character & room state mutations.
    try {
      $chat_result = $this->roomChatService->postMessage(
        $campaign_id,
        $room_id,
        $speaker,
        $message,
        'player',
        $character_id
      );

      if (!empty($chat_result['dungeon_data']) && is_array($chat_result['dungeon_data'])) {
        $dungeon_data = $chat_result['dungeon_data'];
        $game_state = $dungeon_data['game_state'] ?? $game_state;
      }

      return [
        'talked' => TRUE,
        'message' => $message,
        'gm_response' => $chat_result['gm_response'] ?? NULL,
        'narration' => $chat_result['gm_response']['message'] ?? ($chat_result['gm_response']['text'] ?? NULL),
        'npc_interjections' => $chat_result['npc_interjections'] ?? [],
        'state_diff' => $chat_result['state_diff'] ?? [],
        'combat_transition' => $chat_result['combat_transition'] ?? NULL,
        'canonical_actions' => $chat_result['canonical_actions'] ?? [],
        'mutations' => $chat_result['mutations'] ?? [],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Talk action failed: @error', ['@error' => $e->getMessage()]);
      return [
        'talked' => FALSE,
        'error' => 'Chat service error.',
        'mutations' => [],
      ];
    }
  }

  /**
   * Process a search action (Perception check to reveal hidden entities).
   */
  protected function processSearch(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Roll Perception check using server-authoritative dice.
    $perception_bonus = (int) ($params['perception_bonus'] ?? 0);
    $perception_rank = (int) ($params['perception_proficiency_rank'] ?? 1);
    $roll_result = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $roll_result + $perception_bonus;

    // Check against room's search DC (default 15).
    $room = $this->getActiveRoom($dungeon_data);
    $search_dc = $room['gameplay_state']['search_dc'] ?? 15;

    $degree = $this->calculateDegreeOfSuccess($total, $search_dc, $roll_result);

    $discoveries = [];
    // Reveal hidden entities based on degree of success.
    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $discoveries = $this->revealHiddenEntities($dungeon_data, $degree === 'critical_success');
    }

    // REQ 2375: While Searching, also check for min-proficiency hazards that
    // a passive walk-through check would not attempt.
    $hazard_events = [];
    foreach ($this->hazardService->getRoomHazards($dungeon_data) as $hazard_snapshot) {
      $hazard_id = $hazard_snapshot['instance_id'] ?? $hazard_snapshot['id'] ?? NULL;
      if (!$hazard_id) {
        continue;
      }
      $hazard_ref = &$this->hazardService->findHazardByInstanceId($hazard_id, $dungeon_data);
      if (!$hazard_ref || !empty($hazard_ref['state']['detected'])) {
        continue;
      }
      $detect = $this->hazardService->rollHazardDetection(
        $hazard_ref,
        $perception_bonus,
        $perception_rank,
        TRUE // is_searching = TRUE
      );
      if ($detect['detected']) {
        $this->hazardService->markDetected($hazard_ref);
        $hazard_events[] = [
          'type'        => 'hazard_detected',
          'instance_id' => $hazard_id,
          'name'        => $hazard_ref['name'] ?? $hazard_id,
          'roll'        => $detect['roll'],
          'total'       => $detect['total'],
          'dc'          => $detect['dc'],
        ];
        $discoveries[] = ['instance_id' => $hazard_id, 'name' => $hazard_ref['name'] ?? $hazard_id];
      }
    }

    return [
      'searched'     => TRUE,
      'roll'         => $roll_result,
      'total'        => $total,
      'dc'           => $search_dc,
      'degree'       => $degree,
      'discoveries'  => $discoveries,
      'hazard_events' => $hazard_events,
      'mutations'    => [],
    ];
  }

  /**
   * Process a room transition.
   */
  protected function processRoomTransition(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $target_room_id = $params['target_room_id'] ?? NULL;
    if (!$target_room_id) {
      return ['error' => 'No target room specified.'];
    }

    // Track the previous room for event logging.
    $game_state['exploration']['previous_room'] = $dungeon_data['active_room_id'] ?? NULL;

    // Update active room.
    $dungeon_data['active_room_id'] = $target_room_id;

    // Move the actor entity to the destination room's entry hex.
    $entry_hex = $params['entry_hex'] ?? ['q' => 0, 'r' => 0];
    $entity = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if ($entity) {
      $entity['placement']['hex'] = $entry_hex;
      $entity['placement']['room_id'] = $target_room_id;
    }

    // Persist.
    $this->persistDungeonData($campaign_id, $dungeon_data);

    // Auto-create psychology profiles for NPCs in the new room.
    $room_entities = [];
    foreach ($dungeon_data['entities'] ?? [] as $ent) {
      $ent_room = $ent['placement']['room_id'] ?? '';
      if ($ent_room === $target_room_id) {
        $room_entities[] = $ent;
      }
    }
    // Also check room-level entities if stored differently.
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? $room['id'] ?? '') === $target_room_id) {
        foreach ($room['entities'] ?? [] as $ent) {
          $room_entities[] = $ent;
        }
        break;
      }
    }
    if ($room_entities) {
      try {
        $this->roomChatService->ensureNpcProfiles($campaign_id, $room_entities);
      }
      catch (\Exception $e) {
        $this->logger->warning('Auto-profile creation failed on room entry: @err', ['@err' => $e->getMessage()]);
      }
    }

    return [
      'transitioned' => TRUE,
      'from_room' => $game_state['exploration']['previous_room'],
      'to_room' => $target_room_id,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'placement.room_id', 'to' => $target_room_id],
      ],
    ];
  }

  /**
   * Process opening a door or passage.
   */
  protected function processOpenPassage(?string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Find the connection and mark it passable.
    if (!empty($dungeon_data['connections'])) {
      foreach ($dungeon_data['connections'] as &$conn) {
        $conn_id = $conn['id'] ?? NULL;
        if ($conn_id === $target_id) {
          $conn['is_passable'] = TRUE;
          $conn['is_discovered'] = TRUE;
          break;
        }
      }
      unset($conn);
    }

    // Also check for door entities.
    if ($target_id) {
      $entity = &$this->findEntityInDungeon($target_id, $dungeon_data, TRUE);
      if ($entity) {
        $entity['state']['metadata']['passable'] = TRUE;
      }
    }

    $this->persistDungeonData($campaign_id, $dungeon_data);

    // REQ 2378: Active-trigger hazards fire when PC explicitly takes triggering action.
    $active_hazard_events = [];
    foreach ($this->hazardService->getRoomHazards($dungeon_data) as $hazard_snapshot) {
      $hazard_id_act = $hazard_snapshot['instance_id'] ?? $hazard_snapshot['id'] ?? NULL;
      if (!$hazard_id_act) {
        continue;
      }
      $hazard_ref_act = &$this->hazardService->findHazardByInstanceId($hazard_id_act, $dungeon_data);
      if (!$hazard_ref_act) {
        continue;
      }
      if (!empty($hazard_ref_act['state']['detected'])) {
        continue;
      }
      if (($hazard_ref_act['trigger']['type'] ?? 'passive') !== 'active') {
        continue;
      }
      $trigger_act = $this->hazardService->triggerHazard($hazard_ref_act);
      if ($trigger_act['triggered']) {
        $active_hazard_events[] = [
          'type' => 'hazard_triggered',
          'instance_id' => $hazard_id_act,
          'name' => $hazard_ref_act['name'] ?? $hazard_id_act,
          'effect' => $trigger_act['effect'],
          'via' => 'open_door',
        ];
      }
    }
    $this->persistDungeonData($campaign_id, $dungeon_data);

    return [
      'opened' => TRUE,
      'target' => $target_id,
      'hazard_events' => $active_hazard_events,
      'mutations' => [
        ['entity' => $target_id, 'field' => 'passable', 'to' => TRUE],
      ],
    ];
  }

  /**
   * Process a rest action.
   */
  protected function processRest(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $rest_type = $params['rest_type'] ?? 'short';

    if ($rest_type === 'short') {
      // AC-007: Short rest allows Refocus — restore focus points via refocus action.
      // Short rest itself (10 min) does not automatically restore focus; player must
      // take the Refocus exploration activity. This case is a fallback catch-breath rest.
      // REQ dc-cr-vivacious-conduit: 10-minute rest heals con_mod × floor(level/2) HP.
      $mutations = [];
      $vc_healed = 0;
      $entity_sr = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
      if ($entity_sr) {
        $feat_ids = array_column($entity_sr['feats'] ?? [], 'id');
        if (in_array('vivacious-conduit', $feat_ids, TRUE)) {
          $con_mod = (int) ($entity_sr['stats']['con_modifier'] ?? 0);
          $level_sr = max(1, (int) ($entity_sr['stats']['level'] ?? ($entity_sr['level'] ?? 1)));
          $half_level = (int) floor($level_sr / 2);
          $bonus = max(0, $con_mod) * $half_level;
          if ($bonus > 0) {
            $current_hp = (int) ($entity_sr['state']['hit_points']['current'] ?? 0);
            $max_hp = (int) ($entity_sr['state']['hit_points']['max'] ?? 20);
            $new_hp = min($max_hp, $current_hp + $bonus);
            $entity_sr['state']['hit_points']['current'] = $new_hp;
            $vc_healed = $new_hp - $current_hp;
            $this->persistDungeonData($campaign_id, $dungeon_data);
            $mutations[] = ['type' => 'entity_state', 'entity_id' => $actor_id, 'state' => $entity_sr['state']];
          }
        }
      }
      unset($entity_sr);
      return [
        'rested' => TRUE,
        'rest_type' => 'short',
        'vivacious_conduit_healed' => $vc_healed,
        'mutations' => $mutations,
      ];
    }

    // AC-001: Long rest (8 hours) restores all spell slots to maximum.
    $entity_lr = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if ($entity_lr) {
      if (!isset($entity_lr['state'])) {
        $entity_lr['state'] = [];
      }
      // Restore all spell slot 'used' counts to 0.
      if (!empty($entity_lr['state']['spell_slots'])) {
        foreach ($entity_lr['state']['spell_slots'] as $level => &$slot) {
          $slot['used'] = 0;
        }
        unset($slot);
      }
      // AC-007: Long rest also restores focus pool to max.
      $fp_max_lr = (int) ($entity_lr['stats']['focus_points_max'] ?? 0);
      if ($fp_max_lr > 0) {
        $entity_lr['state']['focus_points'] = $fp_max_lr;
      }
      $this->persistDungeonData($campaign_id, $dungeon_data);
    }

    return [
      'rested' => TRUE,
      'rest_type' => 'long',
      'spell_slots_restored' => TRUE,
      'mutations' => [],
    ];
  }

  /**
   * Process a spell casting action during exploration.
   */
  protected function processCastSpell(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $spell_name = $params['spell_name'] ?? 'unknown';
    $spell_level = (int) ($params['spell_level'] ?? 0);
    $cast_at_level = (int) ($params['cast_at_level'] ?? $spell_level);
    $is_cantrip = !empty($params['is_cantrip']);
    $is_focus_spell = !empty($params['is_focus_spell']);
    $spell_tradition = $params['spell_tradition'] ?? NULL;

    $entity_ep = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if (!$entity_ep) {
      return ['cast' => FALSE, 'error' => 'Character not found.', 'mutations' => [], 'narration' => NULL];
    }

    // AC-002: Tradition validation.
    $char_tradition_ep = $entity_ep['stats']['spellcasting_tradition'] ?? NULL;
    if ($spell_tradition && $char_tradition_ep && $spell_tradition !== $char_tradition_ep) {
      return ['cast' => FALSE, 'error' => "Spell tradition '{$spell_tradition}' does not match '{$char_tradition_ep}'.", 'mutations' => [], 'narration' => NULL];
    }

    // AC-006: Cantrips never consume slots.
    if ($is_cantrip) {
      return ['cast' => TRUE, 'spell' => $spell_name, 'is_cantrip' => TRUE, 'narration' => NULL, 'mutations' => []];
    }

    // AC-007: Focus spells consume 1 Focus Point.
    if ($is_focus_spell) {
      $fp_ep = (int) ($entity_ep['state']['focus_points'] ?? 0);
      if ($fp_ep < 1) {
        return ['cast' => FALSE, 'error' => 'No Focus Points remaining.', 'mutations' => [], 'narration' => NULL];
      }
      $entity_ep['state']['focus_points'] = $fp_ep - 1;
      $this->persistDungeonData($campaign_id, $dungeon_data);
      return ['cast' => TRUE, 'spell' => $spell_name, 'is_focus_spell' => TRUE, 'focus_points_remaining' => $fp_ep - 1, 'narration' => NULL, 'mutations' => []];
    }

    // Slot-consuming spell.
    $slot_level_ep = $cast_at_level > 0 ? $cast_at_level : $spell_level;
    if ($slot_level_ep < 1) {
      $slot_level_ep = 1;
    }
    $slot_key_ep = (string) $slot_level_ep;
    $slots_ep = $entity_ep['state']['spell_slots'] ?? [];
    $slot_data_ep = $slots_ep[$slot_key_ep] ?? ['max' => 0, 'used' => 0];
    $avail_ep = max(0, (int) ($slot_data_ep['max'] ?? 0) - (int) ($slot_data_ep['used'] ?? 0));
    if ($avail_ep < 1) {
      return ['cast' => FALSE, 'error' => "No level-{$slot_level_ep} spell slots remaining.", 'mutations' => [], 'narration' => NULL];
    }

    // AC-003: Prepared casters must have spell prepared.
    $casting_type_ep = $entity_ep['stats']['casting_type'] ?? 'spontaneous';
    if ($casting_type_ep === 'prepared') {
      $prepared_ep = $entity_ep['state']['prepared_spells'][$slot_key_ep] ?? [];
      if (!in_array($spell_name, $prepared_ep, TRUE)) {
        return ['cast' => FALSE, 'error' => "'{$spell_name}' is not prepared in a level-{$slot_level_ep} slot.", 'mutations' => [], 'narration' => NULL];
      }
    }

    // Deduct slot.
    $entity_ep['state']['spell_slots'][$slot_key_ep]['used'] = (int) ($slot_data_ep['used'] ?? 0) + 1;
    $this->persistDungeonData($campaign_id, $dungeon_data);

    return [
      'cast' => TRUE,
      'spell' => $spell_name,
      'spell_level' => $spell_level,
      'cast_at_level' => $slot_level_ep,
      'heightened' => $slot_level_ep > $spell_level,
      'slots_remaining' => $avail_ep - 1,
      'narration' => NULL,
      'mutations' => [],
    ];
  }

  // =========================================================================
  // Helper methods.
  // =========================================================================

  /**
   * Calculates travel speed in feet per minute, modified by terrain (REQ 2290-2291).
   *
   * Speed = base_speed × terrain_multiplier × 10 (10 min/move action assumed).
   * Hustle activity: multiplier ×2 but applies fatigue after 30 min.
   */
  public function calculateTravelSpeed(int $base_speed, string $terrain = 'normal', string $activity = 'search'): array {
    $terrain_multipliers = [
      'normal'   => 1.0,
      'difficult' => 0.5,
      'greater_difficult' => 0.3333,
      'rubble'   => 0.5,
      'crowd'    => 0.5,
    ];
    $multiplier = $terrain_multipliers[$terrain] ?? 1.0;

    $hustle = ($activity === 'hustle');
    if ($hustle) {
      $multiplier *= 2.0;
    }

    $feet_per_minute = $base_speed * $multiplier;

    return [
      'base_speed' => $base_speed,
      'terrain' => $terrain,
      'multiplier' => $multiplier,
      'feet_per_minute' => $feet_per_minute,
      'hustle' => $hustle,
      'fatigue_warning' => $hustle ? 'Hustle causes fatigue after 10 minutes.' : NULL,
    ];
  }

  /**
   * Processes daily preparation (REQ 2304-2305).
   * Takes 1 hour. Restores focus points, spell slots; marks daily abilities ready.
   * AC-001: Restores all spell slots to max.
   * AC-003: Stores prepared_spells from params for prepared casters.
   */
  protected function processDailyPrepare(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $prepared = [];

    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
        if ($iid === $actor_id) {
          if (!isset($entity['state'])) {
            $entity['state'] = [];
          }

          // AC-001: Restore all spell slots to max.
          if (!empty($entity['state']['spell_slots'])) {
            foreach ($entity['state']['spell_slots'] as $level => &$slot) {
              $slot['used'] = 0;
            }
            unset($slot);
            $prepared[] = 'spell_slots';
          }

          // Restore focus points to max.
          $max_focus = (int) ($entity['stats']['focus_points_max'] ?? 0);
          if ($max_focus > 0) {
            $entity['state']['focus_points'] = $max_focus;
            $prepared[] = 'focus_points';
          }

          // AC-003: Store prepared spells for prepared casters.
          $casting_type_dp = $entity['stats']['casting_type'] ?? 'spontaneous';
          if ($casting_type_dp === 'prepared' && !empty($params['prepared_spells'])) {
            $entity['state']['prepared_spells'] = $params['prepared_spells'];
            $prepared[] = 'prepared_spells';
          }

          // Mark daily abilities as ready.
          if (isset($entity['state']['daily_abilities'])) {
            foreach ($entity['state']['daily_abilities'] as &$ability) {
              $ability['used'] = FALSE;
            }
            unset($ability);
            $prepared[] = 'daily_abilities';
          }

          // Record prepare time (REQ 2305: takes 1 hour).
          $entity['state']['last_daily_prepare'] = $game_state['exploration']['time_elapsed_minutes'] ?? 0;
          $this->clearBorrowArcaneSpellRetryBlock($entity);
          break;
        }
      }
      unset($entity);
    }

    // Daily prepare takes 1 hour.
    $this->advanceExplorationTime($game_state, 60);

    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist daily prepare: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'prepared' => $prepared,
      'time_cost_minutes' => 60,
      'mutations' => [],
    ];
  }

  /**
   * Advances the exploration time tracker.
   */
  protected function advanceExplorationTime(array &$game_state, int $minutes): void {
    if (!isset($game_state['exploration']['time_elapsed_minutes'])) {
      $game_state['exploration']['time_elapsed_minutes'] = 0;
    }
    $game_state['exploration']['time_elapsed_minutes'] += $minutes;
  }

  /**
   * Checks whether entering a room should trigger an encounter.
   *
   * AC-003: Enriches encounter_context with per-character initiative skills.
   * AC-005: Computes surprised enemies for characters using Avoid Notice.
   */
  protected function checkEncounterTrigger(string $room_id, array $dungeon_data, array $game_state = []): array {
    // Check if the room has an encounter template that hasn't been triggered.
    $rooms = $dungeon_data['rooms'] ?? [];
    foreach ($rooms as $room) {
      if (($room['room_id'] ?? '') !== $room_id) {
        continue;
      }

      $gameplay_state = $room['gameplay_state'] ?? [];
      $encounter_template = $gameplay_state['encounter_template'] ?? NULL;
      $encounter_triggered = $gameplay_state['encounter_triggered'] ?? FALSE;

      if ($encounter_template && !$encounter_triggered) {
        // Find hostile entities in the room.
        $hostile_entities = [];
        $entities = $dungeon_data['entities'] ?? [];
        foreach ($entities as $entity) {
          $entity_room = $entity['placement']['room_id'] ?? NULL;
          $content_type = $entity['content_type'] ?? '';
          if ($entity_room === $room_id && $content_type === 'creature') {
            $hostile_entities[] = $entity;
          }
        }

        if (!empty($hostile_entities)) {
          // AC-003: Build per-character initiative skill map from current activities.
          $character_activities = $game_state['exploration']['character_activities'] ?? [];
          $initiative_skills = [];
          foreach ($character_activities as $char_id => $char_activity) {
            $initiative_skills[$char_id] = self::ACTIVITY_INITIATIVE_SKILLS[$char_activity] ?? 'perception';
          }

          // AC-005: Compute surprised enemies (Avoid Notice: Stealth vs enemy Perception).
          $surprised_enemy_ids = [];
          foreach ($character_activities as $char_id => $char_activity) {
            if ($char_activity !== 'avoid_notice') {
              continue;
            }
            // Find the player entity to get their Stealth bonus.
            $player_entity = NULL;
            foreach ($dungeon_data['entities'] ?? [] as $ent) {
              $eid = $ent['entity_instance_id'] ?? ($ent['instance_id'] ?? ($ent['id'] ?? NULL));
              if ($eid === $char_id) {
                $player_entity = $ent;
                break;
              }
            }
            $stealth_bonus = (int) ($player_entity['stats']['stealth']
              ?? $player_entity['state']['skills']['stealth']
              ?? 0);
            $stealth_roll = $this->numberGenerationService->rollPathfinderDie(20) + $stealth_bonus;
            foreach ($hostile_entities as $enemy) {
              $enemy_id = $enemy['entity_instance_id'] ?? ($enemy['instance_id'] ?? ($enemy['id'] ?? NULL));
              if ($enemy_id === NULL) {
                continue;
              }
              $perception_bonus = (int) ($enemy['stats']['perception'] ?? 0);
              $enemy_roll = $this->numberGenerationService->rollPathfinderDie(20) + $perception_bonus;
              if ($enemy_roll < $stealth_roll) {
                $surprised_enemy_ids[] = $enemy_id;
              }
            }
          }

          return [
            'should_trigger' => TRUE,
            'reason' => $encounter_template['reason'] ?? 'Hostile creatures detected!',
            'initiative_skills' => $initiative_skills,
            'surprised_enemies' => array_values(array_unique($surprised_enemy_ids)),
            'encounter_context' => [
              'template' => $encounter_template,
              'enemies' => $hostile_entities,
              'room_id' => $room_id,
            ],
          ];
        }
      }
    }

    return ['should_trigger' => FALSE];
  }

  /**
   * Finds an entity in dungeon_data by instance_id.
   *
   * @param string $entity_id
   *   The entity instance_id.
   * @param array &$dungeon_data
   *   The dungeon_data payload.
   * @param bool $by_reference
   *   If TRUE, returns by reference for mutation.
   *
   * @return array|null
   *   The entity array, or NULL if not found.
   */
  protected function &findEntityInDungeon(string $entity_id, array &$dungeon_data, bool $by_reference = FALSE): ?array {
    $null = NULL;
    if (empty($dungeon_data['entities'])) {
      return $null;
    }

    foreach ($dungeon_data['entities'] as &$entity) {
      $instance_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
      if ($instance_id === $entity_id) {
        return $entity;
      }
    }

    return $null;
  }

  /**
   * Resolves the Arcana proficiency rank for Borrow Arcane Spell gating.
   */
  protected function resolveArcanaProficiencyRank(array $params, array $entity): int {
    if (isset($params['arcana_proficiency_rank'])) {
      return (int) $params['arcana_proficiency_rank'];
    }
    if (isset($params['arcana_rank'])) {
      return (int) $params['arcana_rank'];
    }
    if (isset($params['proficiency_rank'])) {
      return (int) $params['proficiency_rank'];
    }
    if (isset($entity['state']['skills']['arcana']['rank'])) {
      return (int) $entity['state']['skills']['arcana']['rank'];
    }
    if (isset($entity['stats']['skills']['arcana']['rank'])) {
      return (int) $entity['stats']['skills']['arcana']['rank'];
    }

    return 0;
  }

  /**
   * Determines whether the actor qualifies as an arcane prepared spellcaster.
   */
  protected function isArcanePreparedSpellcaster(array $entity, array $params): bool {
    if (array_key_exists('is_arcane_prepared_spellcaster', $params)) {
      return !empty($params['is_arcane_prepared_spellcaster']);
    }

    $casting_type = strtolower((string) ($params['casting_type'] ?? ($entity['stats']['casting_type'] ?? '')));
    $tradition = strtolower((string) ($params['spellcasting_tradition'] ?? ($entity['stats']['spellcasting_tradition'] ?? '')));

    return $casting_type === 'prepared' && $tradition === 'arcane';
  }

  /**
   * Returns whether Borrow Arcane Spell is currently retry-blocked.
   */
  protected function isBorrowArcaneSpellRetryBlocked(array $entity): bool {
    return !empty($entity['state']['borrow_arcane_spell_retry_blocked']);
  }

  /**
   * Marks Borrow Arcane Spell as retry-blocked until daily preparation.
   */
  protected function setBorrowArcaneSpellRetryBlocked(array &$entity): void {
    if (!isset($entity['state'])) {
      $entity['state'] = [];
    }
    $entity['state']['borrow_arcane_spell_retry_blocked'] = TRUE;
  }

  /**
   * Clears the Borrow Arcane Spell retry block state.
   */
  protected function clearBorrowArcaneSpellRetryBlock(array &$entity): void {
    if (isset($entity['state']['borrow_arcane_spell_retry_blocked'])) {
      unset($entity['state']['borrow_arcane_spell_retry_blocked']);
    }
  }

  /**
   * Applies NPC attitude adjustments to social check DCs when target data exists.
   */
  protected function applyNpcAttitudeToSocialDc(int $base_dc, array $params, ?string $target_id, array &$dungeon_data): array {
    $attitude = $this->resolveNpcAttitude($params, $target_id, $dungeon_data);
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
   * Resolves a normalized NPC attitude from action params or target entity data.
   */
  protected function resolveNpcAttitude(array $params, ?string $target_id, array &$dungeon_data): ?string {
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

    $target_entity = $this->findEntityInDungeon((string) $npc_target_id, $dungeon_data);
    if (!is_array($target_entity)) {
      return NULL;
    }

    $candidates = [
      $target_entity['attitude'] ?? NULL,
      $target_entity['state']['attitude'] ?? NULL,
      $target_entity['state']['metadata']['attitude'] ?? NULL,
      $target_entity['npc_state']['attitude'] ?? NULL,
      $target_entity['npcPsychology']['attitude'] ?? NULL,
    ];

    foreach ($candidates as $candidate) {
      $normalized = $this->normalizeNpcAttitude($candidate);
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
   * Gets the currently active room from dungeon_data.
   */
  protected function getActiveRoom(array $dungeon_data): ?array {
    $active_id = $dungeon_data['active_room_id'] ?? NULL;
    if (!$active_id || empty($dungeon_data['rooms'])) {
      return NULL;
    }

    foreach ($dungeon_data['rooms'] as $room) {
      if (($room['room_id'] ?? '') === $active_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Reveals hidden entities based on a successful search.
   */
  protected function revealHiddenEntities(array &$dungeon_data, bool $reveal_all = FALSE): array {
    $discoveries = [];
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;

    if (empty($dungeon_data['entities'])) {
      return $discoveries;
    }

    foreach ($dungeon_data['entities'] as &$entity) {
      $entity_room = $entity['placement']['room_id'] ?? NULL;
      $is_hidden = $entity['state']['metadata']['hidden'] ?? FALSE;

      if ($entity_room === $active_room_id && $is_hidden) {
        $entity['state']['metadata']['hidden'] = FALSE;
        $discoveries[] = [
          'instance_id' => $entity['instance_id'] ?? $entity['id'] ?? NULL,
          'content_id' => $entity['content_id'] ?? 'unknown',
          'name' => $entity['name'] ?? 'Unknown object',
        ];

        // Only reveal one on normal success; reveal all on crit.
        if (!$reveal_all) {
          break;
        }
      }
    }
    unset($entity);

    return $discoveries;
  }

  /**
   * Calculates PF2e degree of success.
   */
  protected function calculateDegreeOfSuccess(int $total, int $dc, int $natural_roll): string {
    $diff = $total - $dc;
    if ($diff >= 10 || $natural_roll === 20) {
      return 'critical_success';
    }
    if ($diff >= 0) {
      return 'success';
    }
    if ($diff >= -10 && $natural_roll !== 1) {
      return 'failure';
    }
    return 'critical_failure';
  }

  /**
   * Persists dungeon_data to the database.
   */
  protected function persistDungeonData(int $campaign_id, array $dungeon_data): void {
    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist dungeon data: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Finds a room's data in the dungeon_data payload.
   *
   * @param string|null $room_id
   *   The room ID to find.
   * @param array $dungeon_data
   *   The dungeon data payload.
   *
   * @return array|null
   *   Room data array, or NULL if not found.
   */
  protected function findRoomInDungeon(?string $room_id, array $dungeon_data): ?array {
    if ($room_id === NULL) {
      return NULL;
    }

    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? NULL) === $room_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Checks if this is the first time the player has entered a given room.
   *
   * @param string $room_id
   *   The room ID.
   * @param array $dungeon_data
   *   The dungeon data payload.
   *
   * @return bool
   *   TRUE if no prior room_entered event exists for this room.
   */
  protected function isFirstVisit(string $room_id, array $dungeon_data): bool {
    foreach ($dungeon_data['event_log'] ?? [] as $event) {
      if (($event['type'] ?? '') === 'room_entered') {
        $to_room = $event['data']['to_room'] ?? NULL;
        if ($to_room === $room_id) {
          return FALSE;
        }
      }
    }
    return TRUE;
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
   * Processes Treat Wounds exploration activity.
   *
   * REQ 1553–1562: 10-min activity, Trained Medicine + healer's tools.
   * DC/HP restored table: Trained DC 15/2d8, Expert DC 20/2d8+10,
   * Master DC 30/2d8+30, Legendary DC 40/2d8+50.
   * Crit success = double HP. Crit fail = 1d8 damage.
   * 1-hour immunity per target tracked on dungeon_data entity state.
   *
   * @return array
   *   Keys: degree, healed, damage, dc, error (optional), mutations.
   */
  protected function processTreatWounds(string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return ['error' => 'Treat Wounds requires Trained Medicine.', 'degree' => NULL, 'healed' => 0, 'mutations' => []];
    }
    if (empty($params['has_healers_tools'])) {
      return ['error' => 'Treat Wounds requires healer\'s tools.', 'degree' => NULL, 'healed' => 0, 'mutations' => []];
    }

    $effective_target = $target_id ?? $actor_id;

    // REQ 1562: 1-hour immunity per target.
    $now_minutes = $game_state['exploration']['time_elapsed_minutes'] ?? 0;
    foreach ($dungeon_data['entities'] ?? [] as &$entity) {
      if (($entity['entity_id'] ?? $entity['id'] ?? '') === $effective_target) {
        $last_treated = $entity['state']['last_treated_wounds_at'] ?? NULL;
        if ($last_treated !== NULL && ($now_minutes - $last_treated) < 60) {
          $remaining = 60 - ($now_minutes - $last_treated);
          return ['error' => "Target cannot benefit from Treat Wounds for {$remaining} more minutes.", 'degree' => NULL, 'healed' => 0, 'mutations' => []];
        }
        break;
      }
    }
    unset($entity);

    // REQ 2348: Healing is blocked while target is in starvation or thirst damage phase.
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if (($entity['entity_id'] ?? $entity['id'] ?? '') === $effective_target) {
        if (!empty($entity['state']['thirst_damage_phase'])) {
          return ['error' => 'Healing blocked: target must quench thirst before healing takes effect.', 'degree' => NULL, 'healed' => 0, 'mutations' => []];
        }
        if (!empty($entity['state']['starvation_damage_phase'])) {
          return ['error' => 'Healing blocked: target must be fed before healing takes effect.', 'degree' => NULL, 'healed' => 0, 'mutations' => []];
        }
        break;
      }
    }

    // DC and healing table (rank: 1=Trained, 2=Expert, 3=Master, 4=Legendary).
    $dc_table   = [1 => 15, 2 => 20, 3 => 30, 4 => 40];
    $hp_bonus   = [1 => 0,  2 => 10, 3 => 30, 4 => 50];
    $rank_key   = min(4, max(1, $med_rank));
    $dc         = (int) ($params['override_dc'] ?? $dc_table[$rank_key]);
    $med_bonus  = (int) ($params['medicine_bonus'] ?? 0);
    $item_bonus = !empty($params['is_improvised_tools']) ? -2 : 0;

    $d20   = $this->numberGenerationService->rollPathfinderDie(20);
    $d8a   = $this->numberGenerationService->rollPathfinderDie(8);
    $d8b   = $this->numberGenerationService->rollPathfinderDie(8);
    $total = $d20 + $med_bonus + $item_bonus;

    // Degree of success (inline — ExplorationPhaseHandler has no combatCalculator).
    if ($d20 === 20 || $total >= $dc + 10) {
      $degree = 'critical_success';
    }
    elseif ($d20 === 1 || $total < $dc - 9) {
      $degree = 'critical_failure';
    }
    elseif ($total >= $dc) {
      $degree = 'success';
    }
    else {
      $degree = 'failure';
    }

    $healed = 0;
    $damage = 0;
    $mutations = [];

    if ($degree === 'critical_success') {
      $healed = (($d8a + $d8b) + $hp_bonus[$rank_key]) * 2;
    }
    elseif ($degree === 'success') {
      $healed = ($d8a + $d8b) + $hp_bonus[$rank_key];
    }
    elseif ($degree === 'critical_failure') {
      // REQ 1561: 1d8 damage instead of healing.
      $damage = $this->numberGenerationService->rollPathfinderDie(8);
    }

    // Record immunity timestamp on entity state.
    foreach ($dungeon_data['entities'] as &$entity) {
      if (($entity['entity_id'] ?? $entity['id'] ?? '') === $effective_target) {
        $entity['state']['last_treated_wounds_at'] = $now_minutes;
        // REQ dc-cr-halfling-heritage-hillock: snack rider — +level HP on a successful Treat Wounds action.
        // Applied once per action (one function call = one result). Success only; failures/crit-fails excluded.
        if ($healed > 0 && ($entity['heritage'] ?? '') === 'hillock') {
          $target_level = max(1, (int) ($entity['level'] ?? ($entity['stats']['level'] ?? 1)));
          $healed += $target_level;
        }
        $mutations[] = ['type' => 'entity_state', 'entity_id' => $effective_target, 'state' => $entity['state']];
        break;
      }
    }
    unset($entity);

    return [
      'degree'   => $degree,
      'healed'   => $healed,
      'damage'   => $damage,
      'dc'       => $dc,
      'd20'      => $d20,
      'total'    => $total,
      'mutations' => $mutations,
    ];
  }

  /**
   * Processes Treat Disease downtime activity.
   *
   * REQ 1563–1568: Requires Trained Medicine + healer's tools.
   * On success/crit-success, target's next disease save is one degree better.
   * Can only be applied once per disease per rest period per target.
   *
   * @return array
   *   Keys: degree, upgraded (bool), dc, error (optional), mutations.
   */
  protected function processTreatDisease(string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return ['error' => 'Treat Disease requires Trained Medicine.', 'degree' => NULL, 'upgraded' => FALSE, 'mutations' => []];
    }
    if (empty($params['has_healers_tools'])) {
      return ['error' => 'Treat Disease requires healer\'s tools.', 'degree' => NULL, 'upgraded' => FALSE, 'mutations' => []];
    }

    $effective_target = $target_id ?? $actor_id;
    $disease_id = $params['disease_id'] ?? 'disease';

    // REQ 1567: Once per disease per rest period per target.
    $disease_key = $effective_target . ':' . $disease_id;
    if (!empty($game_state['disease_treated'][$disease_key])) {
      return ['error' => 'Already treated this disease for this target during this rest period.', 'degree' => NULL, 'upgraded' => FALSE, 'mutations' => []];
    }

    $dc         = (int) ($params['disease_dc'] ?? 15);
    $med_bonus  = (int) ($params['medicine_bonus'] ?? 0);
    $item_bonus = !empty($params['is_improvised_tools']) ? -2 : 0;

    $d20   = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $med_bonus + $item_bonus;

    if ($d20 === 20 || $total >= $dc + 10) {
      $degree = 'critical_success';
    }
    elseif ($d20 === 1 || $total < $dc - 9) {
      $degree = 'critical_failure';
    }
    elseif ($total >= $dc) {
      $degree = 'success';
    }
    else {
      $degree = 'failure';
    }

    $upgraded = in_array($degree, ['critical_success', 'success'], TRUE);
    if ($upgraded) {
      // REQ 1565: Next disease save gets one degree better (checked by save handler).
      $game_state['disease_treated'][$disease_key] = TRUE;
    }

    return [
      'degree'   => $degree,
      'upgraded' => $upgraded,
      'dc'       => $dc,
      'd20'      => $d20,
      'total'    => $total,
      'mutations' => [],
    ];
  }

  /**
   * AC-004: Resolves what a character can see at a given hex.
   *
   * Uses dungeon_data['light_sources'] (bright_radius / dim_radius in feet)
   * and the character's vision type (darkvision / low_light_vision / normal).
   *
   * @param array $entity        The moving entity (for vision type).
   * @param array $hex           Destination hex {'q', 'r'}.
   * @param array $dungeon_data  Dungeon data payload.
   * @return array               Keys: light_level, can_see (bool), vision_type.
   */
  protected function resolveCharacterVisibility(array $entity, array $hex, array $dungeon_data): array {
    $light_level = $this->resolveLightLevel($hex, $dungeon_data);

    $greater_darkvision = !empty($entity['stats']['greater_darkvision'])
      || !empty($entity['state']['senses']['greater_darkvision']);
    $darkvision = !empty($entity['stats']['darkvision'])
      || !empty($entity['state']['senses']['darkvision']);
    $low_light   = !empty($entity['stats']['low_light_vision'])
      || !empty($entity['state']['senses']['low_light_vision']);

    if ($greater_darkvision) {
      $vision_type = 'greater_darkvision';
    }
    elseif ($darkvision) {
      $vision_type = 'darkvision';
    }
    elseif ($low_light) {
      $vision_type = 'low_light_vision';
    }
    else {
      $vision_type = 'normal';
    }

    $can_see = match ($light_level) {
      'bright' => TRUE,
      'dim'    => $vision_type !== 'normal',
      'dark'   => in_array($vision_type, ['darkvision', 'greater_darkvision'], TRUE),
      default  => TRUE,
    };

    return [
      'light_level' => $light_level,
      'can_see'     => $can_see,
      'vision_type' => $vision_type,
    ];
  }

  /**
   * AC-004: Resolves the effective light level at a hex position.
   *
   * Mirrors CombatEngine::resolveLightLevel() for use during exploration.
   * dungeon_data['light_sources'] = [['hex'=>{'q','r'},'bright_radius'=>ft,'dim_radius'=>ft],…]
   * Fallback: room ambient lighting → bright.
   *
   * @param array $hex          Target hex {'q', 'r'}.
   * @param array $dungeon_data Dungeon data payload.
   * @return string             'bright'|'dim'|'dark'.
   */
  protected function resolveLightLevel(array $hex, array $dungeon_data): string {
    foreach ($dungeon_data['light_sources'] ?? [] as $source) {
      if (!isset($source['hex'])) {
        continue;
      }
      $dq = (int) $hex['q'] - (int) $source['hex']['q'];
      $dr = (int) $hex['r'] - (int) $source['hex']['r'];
      $ds = -$dq - $dr;
      $dist = (int) max(abs($dq), abs($dr), abs($ds));
      // Radii given in feet; 5 ft = 1 hex.
      $bright_hexes = (int) ceil(($source['bright_radius'] ?? 0) / 5);
      $dim_hexes    = (int) ceil(($source['dim_radius'] ?? $bright_hexes * 2) / 5);
      if ($dist <= $bright_hexes) {
        return 'bright';
      }
      if ($dist <= $dim_hexes) {
        return 'dim';
      }
    }
    // Fall back to active room ambient lighting.
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? '') === $active_room_id) {
        return $room['lighting'] ?? $room['ambient_light'] ?? 'bright';
      }
    }
    return 'bright';
  }

}
