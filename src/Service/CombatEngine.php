<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\MovementResolverService;
use Drupal\dungeoncrawler_content\Service\AfflictionManager;

/**
 * Combat Engine service - Main orchestrator for combat operations.
 *
 * Coordinates encounter lifecycle, round management, and turn management.
 * @see /docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md
 */
class CombatEngine {

  // REQ 2276: Detection states per perceiver.
  const DETECTION_STATE_OBSERVED   = 'observed';
  const DETECTION_STATE_HIDDEN     = 'hidden';
  const DETECTION_STATE_UNDETECTED = 'undetected';
  const DETECTION_STATE_UNNOTICED  = 'unnoticed';

  // REQ 2267: Sense precision levels → detection state cap.
  const SENSE_PRECISION_PRECISE    = 'precise';
  const SENSE_PRECISION_IMPRECISE  = 'imprecise';
  const SENSE_PRECISION_VAGUE      = 'vague';

  // REQ 2268: Default senses for all creatures.
  const DEFAULT_SENSES = [
    'vision'  => self::SENSE_PRECISION_PRECISE,
    'hearing' => self::SENSE_PRECISION_IMPRECISE,
    'smell'   => self::SENSE_PRECISION_VAGUE,
  ];

  // REQ 2274: Light levels.
  const LIGHT_BRIGHT = 'bright';
  const LIGHT_DIM    = 'dim';
  const LIGHT_DARK   = 'dark';

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\StateManager
   */
  protected $stateManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ActionProcessor
   */
  protected $actionProcessor;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $store;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected $hpManager;
  protected $numberGeneration;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatCalculator
   */
  protected $combatCalculator;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected $conditionManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\MovementResolverService|null
   */
  protected ?MovementResolverService $movementResolver;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\AfflictionManager|null
   */
  protected ?AfflictionManager $afflictionManager;

  public function __construct(Connection $database, StateManager $state_manager, ActionProcessor $action_processor, CombatEncounterStore $store, HPManager $hp_manager, NumberGenerationService $number_generation, CombatCalculator $combat_calculator = NULL, ConditionManager $condition_manager = NULL, MovementResolverService $movement_resolver = NULL, AfflictionManager $affliction_manager = NULL) {
    $this->database = $database;
    $this->stateManager = $state_manager;
    $this->actionProcessor = $action_processor;
    $this->store = $store;
    $this->hpManager = $hp_manager;
    $this->numberGeneration = $number_generation;
    $this->combatCalculator = $combat_calculator ?? new CombatCalculator();
    $this->conditionManager = $condition_manager;
    $this->movementResolver = $movement_resolver;
    $this->afflictionManager = $affliction_manager;
  }

  /**
   * Create new combat encounter and insert participants.
   */
  public function createEncounter($campaign_id, $encounter_name, array $participants, array $settings = []) {
    $room_id = $settings['room_id'] ?? (is_string($encounter_name) ? $encounter_name : NULL);
    return $this->store->createEncounter($campaign_id, $room_id, $participants);
  }

  /**
   * Start combat encounter: auto-roll or apply custom initiatives, activate, and start round 1.
   *
   * Initiative = d20 + perception modifier for any participant without a custom initiative.
   * Ties are broken by perception modifier (higher wins), then arbitrarily by participant ID.
   */
  public function startEncounter($encounter_id, array $custom_initiatives = []) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    foreach ($encounter['participants'] as $participant) {
      $pid = (int) $participant['id'];
      if (isset($custom_initiatives[$pid])) {
        $this->store->updateParticipant($pid, ['initiative' => (int) $custom_initiatives[$pid]]);
      }
      else {
        // Auto-roll: Perception check = d20 + perception modifier.
        // Perception modifier is stored in entity_ref JSON or defaults to 0.
        $perception_mod = $this->resolvePerceptionModifier($participant);
        $roll = $this->numberGeneration->rollPathfinderDie(20);
        $initiative = $roll + $perception_mod;
        $this->store->updateParticipant($pid, [
          'initiative' => $initiative,
          'initiative_roll' => $roll,
        ]);
      }
    }

    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'active',
      'current_round' => 1,
      'turn_index' => 0,
    ]);

    $state = $this->startRound((int) $encounter_id, 1);
    return ['status' => 'ok', 'encounter' => $state];
  }

  /**
   * Begin a new round: sort initiative order, reset action economy, and set turn_index to 0.
   *
   * Sort: descending initiative. Ties: higher perception modifier wins; then lower participant ID.
   */
  public function startRound($encounter_id, $round_number) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    // Sort participants by initiative descending; ties broken by perception mod then ID.
    $participants = $encounter['participants'];
    usort($participants, function (array $a, array $b): int {
      $init_diff = (int) ($b['initiative'] ?? 0) - (int) ($a['initiative'] ?? 0);
      if ($init_diff !== 0) {
        return $init_diff;
      }
      $perc_diff = $this->resolvePerceptionModifier($b) - $this->resolvePerceptionModifier($a);
      if ($perc_diff !== 0) {
        return $perc_diff;
      }
      return (int) ($a['id'] ?? 0) - (int) ($b['id'] ?? 0);
    });

    foreach ($participants as $participant) {
      $this->store->updateParticipant((int) $participant['id'], [
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
      ]);
    }

    // REQ 2286: Each combat round = 6 seconds of in-world time (derived: round × 6).
    $in_world_seconds = (int) $round_number * 6;

    $this->store->updateEncounter((int) $encounter_id, [
      'current_round' => (int) $round_number,
      'turn_index'    => 0,
    ]);

    $encounter_state = $this->store->loadEncounter((int) $encounter_id) ?: [];
    $encounter_state['in_world_seconds'] = $in_world_seconds;
    return $encounter_state;
  }

  /**
   * End round and advance to the next.
   */
  public function endRound($encounter_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $next_round = ((int) ($encounter['current_round'] ?? 1)) + 1;
    $state = $this->startRound((int) $encounter_id, $next_round);

    return [
      'status' => 'ok',
      'next_round' => $next_round,
      'encounter' => $state,
    ];
  }

  /**
   * Start participant's turn.
   */
  public function startTurn($encounter_id, $participant_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $participants = $encounter['participants'] ?? [];
    $current = $participants[$turn_index] ?? NULL;

    if (!$current || (int) $current['id'] !== (int) $participant_id) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    // Req 2185: Adjust base action count for quickened/slowed/stunned conditions.
    $base_actions = 3;
    $pid = (int) $participant_id;
    $eid = (int) $encounter_id;

    if ($this->conditionManager) {
      // Quickened: +1 action.
      if ($this->conditionManager->hasCondition($pid, 'quickened', $eid)) {
        $base_actions += 1;
      }

      // Slowed X: lose X actions.
      $slowed_value = $this->conditionManager->getConditionValue($pid, 'slowed', $eid) ?? 0;
      $base_actions = max(0, $base_actions - $slowed_value);

      // Stunned X: lose X actions at start of turn, decrement condition by actions lost.
      $stunned_value = $this->conditionManager->getConditionValue($pid, 'stunned', $eid) ?? 0;
      if ($stunned_value > 0) {
        $reduce = min($stunned_value, $base_actions);
        $base_actions = max(0, $base_actions - $reduce);
        $this->conditionManager->decrementCondition($pid, 'stunned', $eid, $reduce);
      }
    }

    $this->store->updateParticipant($pid, [
      'actions_remaining' => $base_actions,
      'attacks_this_turn' => 0,
      'reaction_available' => 1,
    ]);

    // REQ 2237: Reset diagonal movement tracking and movement_spent for new turn.
    // These live in game_state which is managed by EncounterPhaseHandler;
    // here we record participant-level attack count reset for mounted MAP merging.

    // REQ 2258: Mounted combat — rider shares MAP with mount.
    // If this participant is mounted (entity_ref.mounted_on = mount_entity_id),
    // inherit mount's attacks_this_turn to share the MAP pool.
    $participant_row = $this->database->select('combat_participants', 'p')
      ->fields('p', ['entity_ref'])
      ->condition('id', $pid)
      ->execute()
      ->fetchAssoc();

    if ($participant_row) {
      $entity_data = !empty($participant_row['entity_ref']) ? json_decode($participant_row['entity_ref'], TRUE) : [];
      $mount_entity_id = $entity_data['mounted_on'] ?? NULL;
      if ($mount_entity_id) {
        // Find mount participant in this encounter.
        $mount_participant = $this->database->select('combat_participants', 'p')
          ->fields('p', ['attacks_this_turn'])
          ->condition('entity_id', (string) $mount_entity_id)
          ->condition('encounter_id', $eid)
          ->execute()
          ->fetchAssoc();
        if ($mount_participant) {
          // REQ 2258: Share mount's attacks_this_turn as base for MAP calculation.
          $this->store->updateParticipant($pid, [
            'attacks_this_turn' => (int) ($mount_participant['attacks_this_turn'] ?? 0),
          ]);
          $base_actions = max(0, $base_actions);
        }
      }
    }

    $result = [
      'status' => 'ok',
      'participant_id' => $pid,
      'turn_state' => 'awaiting_action',
      'actions_remaining' => $base_actions,
      'reaction_available' => TRUE,
      'attacks_this_turn' => 0,
      'current_round' => (int) ($encounter['current_round'] ?? 1),
    ];

    // Req 2186: Trigger recovery check if participant is dying at start of turn.
    if ($this->conditionManager) {
      $dying_value = $this->conditionManager->getConditionValue($pid, 'dying', $eid) ?? 0;
      if ($dying_value > 0) {
        $result['recovery_check'] = $this->conditionManager->processDying($pid, $eid);
      }
    }

    // REQ 2177: Fast Healing — restore fast_healing HP at start of each turn.
    // REQ 2178: Regeneration — same, but prevents permanent death unless bypassed.
    $participant_row = $this->database->select('combat_participants', 'p')
      ->fields('p', ['entity_ref'])
      ->condition('id', $pid)
      ->execute()
      ->fetchAssoc();
    if ($participant_row) {
      $entity_data = !empty($participant_row['entity_ref']) ? json_decode($participant_row['entity_ref'], TRUE) : [];
      $fast_healing = (int) ($entity_data['fast_healing'] ?? 0);
      $regeneration = (int) ($entity_data['regeneration'] ?? 0);
      $regen_bypassed_by = $entity_data['regeneration_bypassed_by'] ?? NULL;
      $regen_bypassed = !empty($entity_data['regeneration_bypassed']);

      if ($fast_healing > 0) {
        $result['fast_healing'] = $this->hpManager->applyHealing($pid, $fast_healing, 'fast_healing', $eid);
      }
      if ($regeneration > 0 && !$regen_bypassed) {
        $result['regeneration'] = $this->hpManager->applyHealing($pid, $regeneration, 'regeneration', $eid);
      }

      // GAP-2178: Clear the one-turn regeneration_bypassed flag now that regen
      // has been processed (or skipped) for this turn.
      if ($regen_bypassed) {
        $entity_data['regeneration_bypassed'] = FALSE;
        $this->database->update('combat_participants')
          ->fields(['entity_ref' => json_encode($entity_data)])
          ->condition('id', $pid)
          ->execute();
      }

      // REQ 2265-2266: Held breath / suffocation tracking.
      // Decrement air counter if participant is underwater.
      $is_underwater = !empty($entity_data['is_underwater']);
      if ($is_underwater) {
        $air_remaining = (int) ($entity_data['air_remaining'] ?? -1);
        if ($air_remaining < 0) {
          // Initialize: 5 + Con mod rounds.
          $con_mod = (int) ($entity_data['con_mod'] ?? 0);
          $air_remaining = 5 + $con_mod;
        }

        // Subtract 1 per turn; -2 if attacked or cast spells last turn; -all if spoke.
        $air_decrement = (int) ($entity_data['air_decrement_this_turn'] ?? 1);
        $air_remaining -= $air_decrement;
        $entity_data['air_remaining'] = $air_remaining;
        $entity_data['air_decrement_this_turn'] = 1;

        if ($air_remaining <= 0) {
          // REQ 2266: At 0 air — unconscious, begin suffocating.
          if ($this->conditionManager) {
            if (!$this->conditionManager->hasCondition($pid, 'unconscious', $eid)) {
              $this->conditionManager->applyCondition($pid, 'unconscious', 1, 'persistent', 'suffocation', $eid);
            }
          }
          $result['suffocating'] = TRUE;
          $result['air_remaining'] = $air_remaining;
        }
        else {
          $result['air_remaining'] = $air_remaining;
        }

        // Persist updated entity_data.
        $this->database->update('combat_participants')
          ->fields(['entity_ref' => json_encode($entity_data)])
          ->condition('id', $pid)
          ->execute();
      }
    }

    return $result;
  }

  /**
   * End participant's turn, apply end-of-turn effects, and advance initiative.
   */
  public function endTurn($encounter_id, $participant_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $participants = $encounter['participants'] ?? [];
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $current = $participants[$turn_index] ?? NULL;

    if (!$current || (int) $current['id'] !== (int) $participant_id) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $end_effects = $this->processEndOfTurnEffects((int) $participant_id, (int) $encounter_id, (int) ($encounter['current_round'] ?? 1));

    // Reload encounter state after effects to capture defeated participants.
    $encounter_after = $this->store->loadEncounter((int) $encounter_id);
    $participants_after = $encounter_after['participants'] ?? [];

    $outcome = $this->evaluateEncounterOutcome($participants_after);
    if ($outcome['ended']) {
      $summary = $this->endEncounter((int) $encounter_id, $outcome['outcome'], $outcome['victory_condition']);
      return [
        'status' => 'ok',
        'turn_ended' => TRUE,
        'end_of_turn_effects' => $end_effects,
        'encounter_ended' => TRUE,
        'summary' => $summary,
      ];
    }

    $next_index = $turn_index + 1;
    $current_round = (int) ($encounter['current_round'] ?? 1);
    if ($next_index >= count($participants_after)) {
      $current_round += 1;
      $this->startRound((int) $encounter_id, $current_round);
      $next_index = 0;
    }
    else {
      $this->store->updateEncounter((int) $encounter_id, [
        'turn_index' => $next_index,
        'current_round' => $current_round,
      ]);
    }

    $next_state = $this->store->loadEncounter((int) $encounter_id);
    $next_participant = $next_state['participants'][$next_index] ?? NULL;

    return [
      'status' => 'ok',
      'turn_ended' => TRUE,
      'end_of_turn_effects' => $end_effects,
      'next_turn' => [
        'participant_id' => $next_participant ? (int) $next_participant['id'] : NULL,
        'turn_index' => $next_index,
        'current_round' => (int) ($next_state['current_round'] ?? $current_round),
      ],
    ];
  }

  /**
   * Delay participant's turn (stub).
   */
  public function delayTurn($encounter_id, $participant_id) {
    return TRUE;
  }

  /**
   * Resume from delay (stub).
   */
  public function resumeFromDelay($encounter_id, $participant_id, $new_initiative) {
    return [];
  }

  /**
   * Pause combat encounter.
   */
  public function pauseEncounter($encounter_id, $reason) {
    return $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'paused',
    ]);
  }

  /**
   * Resume paused encounter.
   */
  public function resumeEncounter($encounter_id) {
    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'active',
    ]);

    return $this->store->loadEncounter((int) $encounter_id) ?: [];
  }

  /**
   * End combat encounter and return summary.
   */
  public function endEncounter($encounter_id, $outcome, $victory_condition) {
    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'ended',
      'updated' => time(),
    ]);

    $encounter = $this->store->loadEncounter((int) $encounter_id);
    $summary = [
      'encounter_id' => $encounter_id,
      'outcome' => $outcome,
      'victory_condition' => $victory_condition,
      'rounds' => $encounter['current_round'] ?? NULL,
      'participants' => $encounter['participants'] ?? [],
    ];

    // TODO: Compute XP awards based on defeated enemies (PF2e encounter XP tables) and attach to summary.
    return $summary;
  }

  /**
   * Apply persistent damage and decrement durations.
   * Also triggers end-of-turn valued condition tick via ConditionManager.
   */
  protected function processEndOfTurnEffects(int $participant_id, int $encounter_id, int $current_round): array {
    $effects = [
      'persistent_damage' => [],
      'expired_conditions' => [],
      'ticked_conditions' => [],
    ];

    $conditions = $this->store->listActiveConditions($participant_id);

    foreach ($conditions as $condition) {
      if ($condition['condition_type'] === 'persistent_damage') {
        $damage = (int) ($condition['value'] ?? 0);
        $assisted = !empty($condition['assisted']);
        $result = $this->hpManager->applyDamage($participant_id, $damage, 'persistent', ['condition' => 'persistent_damage'], $encounter_id);
        // Req 2103: DC 15 to clear; DC 10 when assisted.
        $flat_result = $this->calculator->rollFlatCheck($assisted ? 10 : 15);
        $cleared = $flat_result['success'];

        if ($cleared) {
          $this->store->removeCondition((int) $condition['id'], $current_round);
        }

        $effects['persistent_damage'][] = [
          'condition_id' => (int) $condition['id'],
          'damage' => $result,
          'flat_check' => $flat_result['roll'],
          'flat_dc' => $flat_result['dc'],
          'cleared' => $cleared,
        ];
      }

      if (!empty($condition['duration_type']) && $condition['duration_type'] === 'rounds' && $condition['duration_remaining'] !== NULL) {
        $remaining = (int) $condition['duration_remaining'] - 1;
        if ($remaining <= 0) {
          $this->store->removeCondition((int) $condition['id'], $current_round);
          $effects['expired_conditions'][] = [
            'condition_id' => (int) $condition['id'],
            'condition_type' => $condition['condition_type'],
          ];
        }
        else {
          $this->database->update('combat_conditions')
            ->fields([
              'duration_remaining' => $remaining,
              'updated' => time(),
            ])
            ->condition('id', (int) $condition['id'])
            ->execute();
        }
      }
    }

    // Tick valued end_of_turn conditions (frightened, clumsy, etc.) via ConditionManager.
    if ($this->conditionManager) {
      $effects['ticked_conditions'] = $this->conditionManager->tickConditions($participant_id, $encounter_id);
    }

    // GAP-AFFLICTION-1: Trigger per-turn saving throws for active afflictions (poison, disease, etc.).
    $effects['periodic_save_results'] = [];
    if ($this->afflictionManager) {
      $active_afflictions = $this->afflictionManager->getActiveAfflictions($participant_id, $encounter_id);
      foreach ($active_afflictions as $affliction) {
        $save_result = $this->afflictionManager->processPeriodicSave(
          $participant_id,
          (int) $affliction['id'],
          $encounter_id
        );
        $effects['periodic_save_results'][] = [
          'affliction_id'   => (int) $affliction['id'],
          'affliction_name' => $affliction['affliction_name'] ?? 'unknown',
          'save_result'     => $save_result,
        ];
      }
    }

    return $effects;
  }

  /**
   * Resolve an attack roll and apply damage on hit.
   *
   * PF2E rules:
   *   Roll = d20 + attack_bonus + MAP penalty
   *   vs. target AC; natural 20 bumps degree up; natural 1 bumps down.
   *
   * @param int $participant_id  Attacker participant row ID.
   * @param int $target_id       Defender participant row ID.
   * @param array $weapon        ['attack_bonus'=>int,'damage_dice'=>'1d6','damage_type'=>'slashing','is_agile'=>bool]
   * @param int $encounter_id
   * @param array $dungeon_data  Optional dungeon data for cover/aquatic checks.
   *
   * @return array ['roll','attack_bonus','map_penalty','total','target_ac','degree','damage_dealt','damage_result','error']
   */
  public function resolveAttack(int $participant_id, int $target_id, array $weapon, int $encounter_id, array $dungeon_data = []): array {
    $attacker = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$attacker) {
      return ['error' => "Attacker participant {$participant_id} not found in encounter {$encounter_id}"];
    }

    $target = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', $target_id)
      ->condition('encounter_id', $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$target) {
      return ['error' => "Target participant {$target_id} not found in encounter {$encounter_id}"];
    }

    // REQ 2230: AoO (skip_map) does not count toward MAP — use existing count, no penalty.
    $skip_map = !empty($weapon['skip_map']);
    if ($skip_map) {
      $attacks_this_turn = (int) ($attacker['attacks_this_turn'] ?? 0);
      $map_penalty = 0;
    }
    else {
      $attacks_this_turn = (int) ($attacker['attacks_this_turn'] ?? 0) + 1;
      $is_agile = !empty($weapon['is_agile']);
      $map_penalty = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, $is_agile);
    }

    $natural_roll = $this->numberGeneration->rollPathfinderDie(20);
    $attack_bonus = (int) ($weapon['attack_bonus'] ?? 0);
    $total = $natural_roll + $attack_bonus + $map_penalty;
    $target_ac = (int) ($target['ac'] ?? 10);

    $flanking = FALSE;
    $cover = ['tier' => 'none', 'ac_bonus' => 0];
    $aquatic_info = ['is_underwater' => FALSE, 'attack_blocked' => FALSE];

    if ($this->movementResolver) {
      $attacker_hex = ['q' => (int) ($attacker['position_q'] ?? 0), 'r' => (int) ($attacker['position_r'] ?? 0)];
      $target_hex   = ['q' => (int) ($target['position_q'] ?? 0),   'r' => (int) ($target['position_r'] ?? 0)];

      // REQ 2253-2254: Flanking — target is flat-footed to flanking melee attacks.
      $weapon_type = strtolower($weapon['type'] ?? 'melee');
      if ($weapon_type === 'melee') {
        $allies = $this->database->select('combat_participants', 'p')
          ->fields('p', ['id', 'team', 'position_q', 'position_r'])
          ->condition('encounter_id', $encounter_id)
          ->condition('is_defeated', 0)
          ->condition('id', [$participant_id, $target_id], 'NOT IN')
          ->execute()
          ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($allies as $ally) {
          if (($ally['team'] ?? '') === ($attacker['team'] ?? '')) {
            $ally_hex = ['q' => (int) $ally['position_q'], 'r' => (int) $ally['position_r']];
            if ($this->movementResolver->isFlanking($attacker_hex, $target_hex, $ally_hex)) {
              $flanking = TRUE;
              break;
            }
          }
        }
      }

      // REQ 2255-2257: Cover — apply circumstance bonus to target AC.
      if (!empty($dungeon_data)) {
        $cover = $this->movementResolver->calculateCover($attacker_hex, $target_hex, $dungeon_data);
        $target_ac += $cover['ac_bonus'];
      }

      // REQ 2262-2264: Aquatic combat modifiers.
      $attacker_aquatic = $this->movementResolver->getAquaticModifiers($attacker, $dungeon_data);
      $target_aquatic   = $this->movementResolver->getAquaticModifiers($target, $dungeon_data);

      $damage_type_str = strtolower($weapon['damage_type'] ?? '');
      $is_ranged = $weapon_type !== 'melee';

      // REQ 2263: Ranged bludgeoning/slashing auto-misses if either is underwater.
      if ($is_ranged && ($attacker_aquatic['is_underwater'] || $target_aquatic['is_underwater'])) {
        if (in_array($damage_type_str, ['bludgeoning', 'slashing'])) {
          $aquatic_info['attack_blocked'] = TRUE;
        }
      }

      // REQ 2262: Flat-footed if underwater without swim speed.
      if ($target_aquatic['flat_footed']) {
        $target_ac -= 2;
      }

      // REQ 2262: Slashing circumstance penalty underwater.
      if ($attacker_aquatic['is_underwater'] && $damage_type_str === 'slashing') {
        $attack_bonus += $attacker_aquatic['slashing_penalty'];
        $total = $natural_roll + $attack_bonus + $map_penalty;
      }

      $aquatic_info = array_merge($aquatic_info, [
        'attacker_underwater' => $attacker_aquatic['is_underwater'],
        'target_underwater'   => $target_aquatic['is_underwater'],
      ]);
    }

    // REQ 2254: Flanked target is flat-footed (–2 circumstance to AC).
    if ($flanking) {
      $target_ac -= 2;
    }

    // Block attack if aquatic rules prevent it.
    if ($aquatic_info['attack_blocked']) {
      return [
        'roll'            => $natural_roll,
        'attack_bonus'    => $attack_bonus,
        'map_penalty'     => $map_penalty,
        'total'           => $total,
        'target_ac'       => $target_ac,
        'degree'          => 'failure',
        'damage_dealt'    => NULL,
        'damage_result'   => NULL,
        'flanking'        => $flanking,
        'cover'           => $cover['tier'],
        'aquatic_blocked' => TRUE,
        'error'           => 'Ranged bludgeoning/slashing attacks auto-miss underwater.',
      ];
    }

    // REQ 2274-2278: Detection state and light level checks.
    $detection_info = ['state' => self::DETECTION_STATE_OBSERVED, 'light_level' => self::LIGHT_BRIGHT, 'flat_check' => NULL];
    $attacker_entity_data = !empty($attacker['entity_ref']) ? json_decode($attacker['entity_ref'], TRUE) : [];
    $target_entity_data   = !empty($target['entity_ref'])   ? json_decode($target['entity_ref'],   TRUE) : [];
    $attacker_entity_id   = $attacker_entity_data['entity_id'] ?? (string) $participant_id;

    // GAP-2227: Raise a Shield applies its AC bonus until start of target's next turn.
    if (!empty($target_entity_data['shield_raised'])) {
      $target_ac += (int) ($target_entity_data['shield_raised_ac_bonus'] ?? 0);
    }

    if (!empty($dungeon_data)) {
      $detection_info['light_level'] = $this->resolveLightLevel($dungeon_data, $attacker_hex ?? ['q' => 0, 'r' => 0]);
    }

    // REQ 2276: Check stored per-perceiver detection state.
    $stored_state = $this->getDetectionState($target, $attacker_entity_id);

    // REQ 2267-2273: Also resolve sense-based detection cap from light/invisible flags.
    $sense_state = $this->resolveSensePrecision($attacker_entity_data, $target_entity_data, $detection_info['light_level']);

    // Use the stricter (worse) of stored state and sense-derived state.
    $state_order = [self::DETECTION_STATE_OBSERVED => 0, self::DETECTION_STATE_HIDDEN => 1, self::DETECTION_STATE_UNDETECTED => 2, self::DETECTION_STATE_UNNOTICED => 3];
    $effective_state = ($state_order[$sense_state] ?? 0) >= ($state_order[$stored_state] ?? 0) ? $sense_state : $stored_state;
    $detection_info['state'] = $effective_state;

    // REQ 2276: Undetected/unnoticed target — attacker must guess; auto-miss if wrong position.
    if ($effective_state === self::DETECTION_STATE_UNDETECTED || $effective_state === self::DETECTION_STATE_UNNOTICED) {
      return [
        'roll'          => $natural_roll,
        'attack_bonus'  => $attack_bonus,
        'map_penalty'   => $map_penalty,
        'total'         => $total,
        'target_ac'     => $target_ac,
        'degree'        => 'failure',
        'damage_dealt'  => NULL,
        'damage_result' => NULL,
        'flanking'      => $flanking,
        'cover'         => $cover['tier'] ?? 'none',
        'detection'     => $detection_info,
        'error'         => "Cannot target {$effective_state} creature (attacker must guess position).",
      ];
    }

    // REQ 2276: Hidden target is flat-footed; attacker rolls DC 11 flat check.
    if ($effective_state === self::DETECTION_STATE_HIDDEN) {
      $target_ac -= 2;
      $hidden_flat = $this->rollFlatCheck(11);
      $detection_info['flat_check'] = $hidden_flat;
      if (!$hidden_flat['success']) {
        return [
          'roll'          => $natural_roll,
          'attack_bonus'  => $attack_bonus,
          'map_penalty'   => $map_penalty,
          'total'         => $total,
          'target_ac'     => $target_ac,
          'degree'        => 'failure',
          'damage_dealt'  => NULL,
          'damage_result' => NULL,
          'flanking'      => $flanking,
          'cover'         => $cover['tier'] ?? 'none',
          'detection'     => $detection_info,
          'error'         => 'Attack misses: DC 11 flat check failed for hidden target.',
        ];
      }
    }

    // REQ 2274/2277: Concealed condition or dim light → DC 5 flat check.
    // (concealed by condition is handled in RulesEngine.validateAttack; here we handle light-based concealment).
    if ($detection_info['light_level'] === self::LIGHT_DIM
      && $effective_state === self::DETECTION_STATE_OBSERVED
      && empty($attacker_entity_data['low_light_vision'])
      && empty($attacker_entity_data['darkvision'])
      && empty($attacker_entity_data['greater_darkvision'])
    ) {
      $dim_flat = $this->rollFlatCheck(5);
      $detection_info['flat_check'] = $dim_flat;
      if (!$dim_flat['success']) {
        return [
          'roll'          => $natural_roll,
          'attack_bonus'  => $attack_bonus,
          'map_penalty'   => $map_penalty,
          'total'         => $total,
          'target_ac'     => $target_ac,
          'degree'        => 'failure',
          'damage_dealt'  => NULL,
          'damage_result' => NULL,
          'flanking'      => $flanking,
          'cover'         => $cover['tier'] ?? 'none',
          'detection'     => $detection_info,
          'error'         => 'Attack misses: DC 5 flat check failed (dim light concealment).',
        ];
      }
    }



    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $target_ac, $natural_roll);

    // Record attack in participant state.
    // REQ 2230: AoO (skip_map) is a reaction — do not consume actions or MAP count.
    $updates = ['attacks_this_turn' => $attacks_this_turn];
    if (!$skip_map) {
      $updates['actions_remaining'] = max(0, (int) ($attacker['actions_remaining'] ?? 3) - 1);
    }
    $this->store->updateParticipant($participant_id, $updates);

    $damage_dealt = NULL;
    $damage_result = NULL;

    if ($degree === 'critical_success' || $degree === 'success') {
      $damage_roll = $this->numberGeneration->rollNotation($weapon['damage_dice'] ?? '1d4');
      $dice_total = array_sum($damage_roll['rolls'] ?? [$damage_roll['total'] ?? 1]);
      $ability_mod = (int) ($weapon['ability_modifier'] ?? $damage_roll['modifier'] ?? 0);
      $damage_type = $weapon['damage_type'] ?? 'untyped';

      // REQ 2264: Fire trait actions automatically fail underwater.
      if ($aquatic_info['attacker_underwater'] ?? FALSE) {
        if (in_array(strtolower($damage_type), ['fire']) || !empty($weapon['is_fire_trait'])) {
          return [
            'roll'          => $natural_roll,
            'attack_bonus'  => $attack_bonus,
            'map_penalty'   => $map_penalty,
            'total'         => $total,
            'target_ac'     => $target_ac,
            'degree'        => 'failure',
            'damage_dealt'  => NULL,
            'damage_result' => NULL,
            'flanking'      => $flanking,
            'cover'         => $cover['tier'],
            'error'         => 'Fire trait actions automatically fail underwater.',
          ];
        }
      }

      if ($degree === 'critical_success') {
        // PF2E req 2115: double dice only, then add flat bonuses once.
        $damage_dealt = $this->calculator->applyCriticalDamage($damage_roll['rolls'] ?? [], $ability_mod)['doubled_total'];
      }
      else {
        $damage_dealt = $dice_total + $ability_mod;
      }

      // REQ 2262: Resistance 5 to fire/acid for underwater targets.
      if (($target_aquatic['is_underwater'] ?? FALSE) && in_array(strtolower($damage_type), ['fire', 'acid'])) {
        $damage_dealt = max(0, $damage_dealt - 5);
      }

      $damage_result = $this->hpManager->applyDamage($target_id, $damage_dealt, $damage_type, ['attacker' => $participant_id], $encounter_id);

      // REQ 2154: Crit hit applies dying 2 when target is defeated (not dead from massive damage).
      // HPManager.applyDamage now applies dying internally, but crit needs dying 2.
      if (($damage_result['new_status'] ?? '') === 'defeated' && $degree === 'critical_success') {
        $this->hpManager->applyDyingCondition($target_id, 2, $encounter_id, TRUE);
      }

      // REQ 2153: Target dropped to 0 HP — shift initiative to just after attacker.
      if (($damage_result['new_status'] ?? '') === 'defeated') {
        $this->shiftInitiativeAfterAttacker($encounter_id, $target_id, $participant_id);
      }
    }

    return [
      'roll'          => $natural_roll,
      'attack_bonus'  => $attack_bonus,
      'map_penalty'   => $map_penalty,
      'total'         => $total,
      'target_ac'     => $target_ac,
      'degree'        => $degree,
      'damage_dealt'  => $damage_dealt,
      'damage_result' => $damage_result,
      'flanking'      => $flanking,
      'cover'         => $cover['tier'],
      'detection'     => $detection_info,
      'error'         => NULL,
    ];
  }

  /**
   * Resolve a participant's Perception modifier from entity_ref JSON (defaults to 0).
   */
  protected function resolvePerceptionModifier(array $participant): int {
    if (!empty($participant['entity_ref'])) {
      $entity = json_decode($participant['entity_ref'], TRUE);
      if (is_array($entity)) {
        return (int) ($entity['perception_modifier'] ?? $entity['perception_mod'] ?? 0);
      }
    }
    return 0;
  }

  /**
   * Determine whether the encounter has ended.
   */
  protected function evaluateEncounterOutcome(array $participants): array {
    $active_teams = [];
    foreach ($participants as $p) {
      if (empty($p['is_defeated'])) {
        $team = $p['team'] ?? 'neutral';
        $active_teams[$team] = TRUE;
      }
    }

    $team_count = count($active_teams);
    if ($team_count === 0) {
      return ['ended' => TRUE, 'outcome' => 'draw', 'victory_condition' => 'all combatants down'];
    }

    if ($team_count === 1) {
      $team = array_keys($active_teams)[0];
      return ['ended' => TRUE, 'outcome' => 'victory', 'victory_condition' => "team {$team} stands"];
    }

    return ['ended' => FALSE, 'outcome' => NULL, 'victory_condition' => NULL];
  }

  /**
   * REQ 2267/2276: Get the detection state of a target from the attacker's perspective.
   *
   * Detection states are stored in the target's entity_ref JSON:
   * entity_ref['detection_states'][attacker_entity_id] = 'observed'|'hidden'|'undetected'|'unnoticed'
   *
   * @param array $target_row         combat_participants row for the target.
   * @param string $attacker_entity_id Entity ID of the observing creature.
   * @return string One of: observed, hidden, undetected, unnoticed.
   */
  public function getDetectionState(array $target_row, string $attacker_entity_id): string {
    $entity_data = !empty($target_row['entity_ref']) ? json_decode($target_row['entity_ref'], TRUE) : [];
    return $entity_data['detection_states'][$attacker_entity_id] ?? self::DETECTION_STATE_OBSERVED;
  }

  /**
   * REQ 2276: Persist a detection state update in the target's entity_ref.
   */
  public function setDetectionState(int $target_participant_id, string $attacker_entity_id, string $state): void {
    $row = $this->database->select('combat_participants', 'p')
      ->fields('p', ['entity_ref'])
      ->condition('id', $target_participant_id)
      ->execute()
      ->fetchAssoc();
    $entity_data = !empty($row['entity_ref']) ? json_decode($row['entity_ref'], TRUE) : [];
    $entity_data['detection_states'][$attacker_entity_id] = $state;
    $this->database->update('combat_participants')
      ->fields(['entity_ref' => json_encode($entity_data), 'updated' => time()])
      ->condition('id', $target_participant_id)
      ->execute();
  }

  /**
   * REQ 2274/2275: Determine the effective light level at a hex position.
   *
   * dungeon_data['rooms'][room_id]['lighting'] = 'bright'|'dim'|'dark'
   * dungeon_data['light_sources'] = [['hex'=>['q','r'],'bright_radius'=>ft,'dim_radius'=>ft],...]
   *
   * @param array $dungeon_data
   * @param array $hex  ['q' => int, 'r' => int]
   * @return string 'bright'|'dim'|'dark'
   */
  public function resolveLightLevel(array $dungeon_data, array $hex): string {
    // Check explicit light sources first.
    foreach ($dungeon_data['light_sources'] ?? [] as $source) {
      if (!isset($source['hex'])) {
        continue;
      }
      // REQ 2275: bright radius is given in feet (5 ft = 1 hex); dim = 2× bright radius.
      $distance_hexes = $this->hexDistance($source['hex'], $hex);
      $bright_hexes   = (int) ceil(($source['bright_radius'] ?? 0) / 5);
      $dim_hexes      = (int) ceil(($source['dim_radius'] ?? $bright_hexes * 2) / 5);
      if ($distance_hexes <= $bright_hexes) {
        return self::LIGHT_BRIGHT;
      }
      if ($distance_hexes <= $dim_hexes) {
        return self::LIGHT_DIM;
      }
    }
    // Fall back to room ambient lighting.
    $room_id = $dungeon_data['current_room_id'] ?? NULL;
    if ($room_id && isset($dungeon_data['rooms'][$room_id]['lighting'])) {
      return $dungeon_data['rooms'][$room_id]['lighting'];
    }
    return self::LIGHT_BRIGHT;
  }

  /**
   * Cube-coordinate hex distance helper (delegates to MovementResolverService when available).
   */
  protected function hexDistance(array $a, array $b): int {
    if ($this->movementResolver) {
      return $this->movementResolver->hexDistance($a, $b);
    }
    $dq = (int) $a['q'] - (int) $b['q'];
    $dr = (int) $a['r'] - (int) $b['r'];
    $ds = -$dq - $dr;
    return (int) max(abs($dq), abs($dr), abs($ds));
  }

  /**
   * REQ 2267-2273: Resolve the sense-precision a creature has of a target given light/visibility.
   *
   * Returns the detection-state cap based on: primary senses, special senses, light level, invisible flag.
   *
   * @param array $attacker_entity  entity_ref data decoded for the attacker.
   * @param array $target_entity    entity_ref data decoded for the target.
   * @param string $light_level     One of LIGHT_BRIGHT, LIGHT_DIM, LIGHT_DARK.
   * @return string  Best detection state the attacker can achieve: observed|hidden|undetected|unnoticed.
   */
  public function resolveSensePrecision(array $attacker_entity, array $target_entity, string $light_level): string {
    // REQ 2278: Invisible → automatically undetected to sight-only perceivers.
    $target_invisible = !empty($target_entity['is_invisible']);

    // Determine best visual precision based on light level and special senses.
    if (!$target_invisible) {
      if ($light_level === self::LIGHT_BRIGHT) {
        $visual_state = self::DETECTION_STATE_OBSERVED;
      }
      elseif ($light_level === self::LIGHT_DIM) {
        // REQ 2271: Low-light vision treats dim as bright.
        if (!empty($attacker_entity['low_light_vision']) || !empty($attacker_entity['darkvision']) || !empty($attacker_entity['greater_darkvision'])) {
          $visual_state = self::DETECTION_STATE_OBSERVED;
        }
        else {
          // REQ 2274: Dim light → concealed (vision is imprecise in dim, best is hidden).
          $visual_state = self::DETECTION_STATE_HIDDEN;
        }
      }
      else {
        // Darkness.
        if (!empty($attacker_entity['greater_darkvision'])) {
          // REQ 2270: Greater darkvision sees through all magical darkness.
          $visual_state = self::DETECTION_STATE_OBSERVED;
        }
        elseif (!empty($attacker_entity['darkvision'])) {
          // REQ 2269: Darkvision sees normally in darkness.
          $visual_state = self::DETECTION_STATE_OBSERVED;
        }
        else {
          // No darkvision in complete darkness → cannot see (undetected via vision).
          $visual_state = self::DETECTION_STATE_UNDETECTED;
        }
      }
    }
    else {
      // Target invisible — vision yields undetected.
      $visual_state = self::DETECTION_STATE_UNDETECTED;
    }

    // REQ 2272: Tremorsense (imprecise) — detect via vibrations on same surface.
    // If attacker has tremorsense, best state from tremorsense is hidden.
    $tremorsense_range = (int) ($attacker_entity['tremorsense_ft'] ?? 0);
    if ($tremorsense_range > 0) {
      // Tremorsense = imprecise → best is hidden (not observed).
      if ($visual_state === self::DETECTION_STATE_UNDETECTED || $visual_state === self::DETECTION_STATE_UNNOTICED) {
        $visual_state = self::DETECTION_STATE_HIDDEN;
      }
    }

    // REQ 2278: Hearing (imprecise sense) — when target is invisible/undetected
    // via sight, hearing provides imprecise detection → best state is hidden.
    // All creatures have hearing by default (no special sense flag needed).
    if ($visual_state === self::DETECTION_STATE_UNDETECTED || $visual_state === self::DETECTION_STATE_UNNOTICED) {
      // Hearing is blocked by deafened condition or if target is silenced.
      $attacker_deafened = !empty($attacker_entity['deafened']);
      $target_silenced   = !empty($target_entity['silenced']);
      if (!$attacker_deafened && !$target_silenced) {
        // Hearing = imprecise → best cap is hidden.
        $visual_state = self::DETECTION_STATE_HIDDEN;
      }
    }

    // REQ 2273: Scent (vague) — detect by smell, best state is undetected.
    $scent_range = (int) ($attacker_entity['scent_ft'] ?? 0);
    if ($scent_range > 0) {
      if ($visual_state === self::DETECTION_STATE_UNNOTICED) {
        $visual_state = self::DETECTION_STATE_UNDETECTED;
      }
    }

    return $visual_state;
  }

  /**
   * Roll a flat check using the engine's number-generation service.
   *
   * REQ 2102: DC ≤ 1 auto-succeeds; DC ≥ 21 auto-fails.
   */
  protected function rollFlatCheck(int $dc): array {
    if ($dc <= 1) {
      return ['auto' => TRUE, 'success' => TRUE, 'roll' => NULL, 'dc' => $dc];
    }
    if ($dc >= 21) {
      return ['auto' => TRUE, 'success' => FALSE, 'roll' => NULL, 'dc' => $dc];
    }
    $roll = $this->numberGeneration->rollPathfinderDie(20);
    return ['auto' => FALSE, 'success' => $roll >= $dc, 'roll' => $roll, 'dc' => $dc];
  }

  /**
   * REQ 2153/2288: Shift target's initiative to just after the attacker's initiative.
   *
   * When a character drops to 0 HP mid-combat, they move in the initiative order
   * to just after the creature that reduced them to 0.
   */
  protected function shiftInitiativeAfterAttacker(int $encounter_id, int $target_id, int $attacker_id): void {
    $attacker = $this->database->select('combat_participants', 'p')
      ->fields('p', ['initiative'])
      ->condition('id', $attacker_id)
      ->execute()
      ->fetchAssoc();

    if (!$attacker) {
      return;
    }

    // Place target at attacker_initiative − 0.5 (stored as integer: attacker_initiative − 1 with tie-break).
    $new_initiative = max(0, (int) ($attacker['initiative'] ?? 0) - 1);

    $this->database->update('combat_participants')
      ->fields(['initiative' => $new_initiative])
      ->condition('id', $target_id)
      ->execute();
  }

}
