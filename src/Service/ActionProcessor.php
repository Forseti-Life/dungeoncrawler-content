<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\RulesEngine;
use Drupal\dungeoncrawler_content\Service\AreaResolverService;
use Drupal\dungeoncrawler_content\Service\CounteractService;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Action Processor service - Executes and validates combat actions.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ActionProcessor)
 * @see /docs/dungeoncrawler/issues/combat-action-validation.md
 */
class ActionProcessor {

  protected $calculator;
  protected $hpManager;
  protected $conditionManager;
  protected $logger;
  protected $store;
  protected $numberGeneration;
  protected $rulesEngine;
  protected $areaResolver;
  protected $counteract;

  public function __construct(CombatCalculator $calculator, HPManager $hp_manager, ConditionManager $condition_manager, LoggerChannelFactoryInterface $logger_factory, CombatEncounterStore $store, NumberGenerationService $number_generation, RulesEngine $rules_engine, ?AreaResolverService $area_resolver = NULL, ?CounteractService $counteract = NULL) {
    $this->calculator = $calculator;
    $this->hpManager = $hp_manager;
    $this->conditionManager = $condition_manager;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->store = $store;
    $this->numberGeneration = $number_generation;
    $this->rulesEngine = $rules_engine;
    $this->areaResolver = $area_resolver;
    $this->counteract = $counteract;
  }

  /**
   * Execute combat action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executeaction
   */
  public function executeAction($encounter_id, $participant_id, $action_type, array $action_data) {
    // Req 2188/2189: Disrupted action — deduct cost but apply no effect.
    if (!empty($action_data['disrupted'])) {
      $state = $this->loadEncounterState($encounter_id);
      if ($state['status'] === 'error') {
        return $state;
      }
      [$encounter, $participants] = $state['data'];
      $actor = $this->findParticipant($participants, $participant_id);
      if ($actor) {
        $action_cost = (int) ($action_data['action_cost'] ?? 1);
        $actions_after = max(0, (int) ($actor['actions_remaining'] ?? 0) - $action_cost);
        $this->store->updateParticipant((int) $participant_id, ['actions_remaining' => $actions_after]);
        $this->logAction((int) $encounter_id, (int) $participant_id, 'disrupted', NULL, $action_data, ['reason' => 'disrupted']);
        return ['status' => 'ok', 'disrupted' => TRUE, 'actions_remaining' => $actions_after];
      }
    }

    switch ($action_type) {
      case 'stride':
        return $this->executeStride($participant_id, $action_data['distance'] ?? 0, $action_data['path'] ?? [], $encounter_id);

      case 'strike':
        return $this->executeStrike($participant_id, $action_data['target_id'] ?? NULL, $action_data, $encounter_id);

      case 'cast_spell':
        return $this->executeCastSpell($participant_id, $action_data['spell_id'] ?? $action_data['spell_name'] ?? '', $action_data['spell_level'] ?? 1, $action_data['targets'] ?? [], $encounter_id);

      case 'counteract':
      case 'dispel':
        return $this->executeCounteract($participant_id, $action_data, $encounter_id);

      case 'reaction':
        return $this->executeReactionAction($participant_id, $action_data, $encounter_id);

      case 'free_action':
        return $this->executeFreeAction($participant_id, $action_data, $encounter_id);

      case 'activity':
        return $this->executeActivity($participant_id, $action_data, $encounter_id);

      default:
        return ['status' => 'error', 'message' => 'Unsupported action type'];
    }
  }

  /**
   * Execute Strike action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestrike
   */
  public function executeStrike($attacker_id, $target_id, $weapon, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $attacker = $this->findParticipant($participants, $attacker_id);
    $target = $this->findParticipant($participants, $target_id);

    if (!$attacker || !$target) {
      return ['status' => 'error', 'message' => 'Attacker or target not found'];
    }

    if (!$this->isCurrentTurn($encounter, $participants, $attacker_id)) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $economy = $this->rulesEngine->validateActionEconomy($attacker, 1);
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    $attack_number = (int) ($attacker['attacks_this_turn'] ?? 0) + 1;
    $is_agile = !empty($weapon['is_agile']);
    $map_penalty = $this->calculator->calculateMultipleAttackPenalty($attack_number, $is_agile);

    $base_attack_bonus = (int) ($weapon['attack_bonus'] ?? 0);
    $is_nonlethal = !empty($weapon['is_nonlethal']);
    $attacker_mod = $this->conditionManager->getConditionModifiers($attacker_id, 'attack', $encounter_id);
    $target_ac_mod = $this->conditionManager->getConditionModifiers($target_id, 'ac', $encounter_id);

    // PF2E req 2120: nonlethal attacks take a -2 circumstance penalty.
    $nonlethal_penalty = $is_nonlethal ? -2 : 0;

    $roll_natural = isset($weapon['natural_roll'])
      ? max(1, min(20, (int) $weapon['natural_roll']))
      : $this->numberGeneration->rollPathfinderDie(20);
    $attack_total = $roll_natural + $base_attack_bonus + $attacker_mod + $map_penalty + $nonlethal_penalty;

    $target_ac = (int) ($target['ac'] ?? 10) + $target_ac_mod;
    $degree = $this->calculator->calculateDegreeOfSuccess($attack_total, $target_ac, $roll_natural);

    $base_damage = isset($weapon['damage']) ? (int) $weapon['damage'] : 0;
    $damage = 0;
    if ($degree === 'success') {
      $damage = $base_damage;
    }
    elseif ($degree === 'critical_success') {
      // PF2E req 2115: for pre-computed damage (no dice roll), double the base.
      // When weapon provides pre-rolled dice, those dice double; flat mods add once.
      // Since ActionProcessor uses $weapon['damage'] as a combined total, apply 2x.
      $damage = $base_damage * 2;
    }

    $damage_result = NULL;
    if ($damage > 0) {
      $damage_result = $this->hpManager->applyDamage($target_id, $damage, $weapon['damage_type'] ?? 'physical', ['action' => 'strike', 'attacker' => $attacker_id], $encounter_id, $is_nonlethal);
    }

    $actions_left = $economy['actions_after'];
    $this->store->updateParticipant($attacker_id, [
      'actions_remaining' => $actions_left,
      'attacks_this_turn' => $attack_number,
    ]);

    $this->logAction($encounter_id, $attacker_id, 'strike', $target_id, $weapon, [
      'roll' => $roll_natural,
      'total' => $attack_total,
      'map' => $map_penalty,
      'degree' => $degree,
      'target_ac' => $target_ac,
      'damage' => $damage,
      'damage_result' => $damage_result,
    ]);

    return [
      'status' => 'ok',
      'degree' => $degree,
      'attack_roll' => $attack_total,
      'natural_roll' => $roll_natural,
      'target_ac' => $target_ac,
      'damage' => $damage,
      'damage_result' => $damage_result,
      'actions_remaining' => $actions_left,
      'attacks_this_turn' => $attack_number,
    ];
  }

  /**
   * Execute Stride action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestride
   */
  public function executeStride($participant_id, $distance, array $path, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $actor = $this->findParticipant($participants, $participant_id);
    if (!$actor) {
      return ['status' => 'error', 'message' => 'Participant not found'];
    }

    if (!$this->isCurrentTurn($encounter, $participants, $participant_id)) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $economy = $this->rulesEngine->validateActionEconomy($actor, 1);
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    $end = $this->lastPathCoordinate($path);
    $actions_left = $economy['actions_after'];

    $this->store->updateParticipant($participant_id, [
      'actions_remaining' => $actions_left,
      'position_q' => $end['q'],
      'position_r' => $end['r'],
    ]);

    $this->logAction($encounter_id, $participant_id, 'stride', NULL, ['distance' => $distance, 'path' => $path], [
      'end_position' => $end,
    ]);

    return [
      'status' => 'ok',
      'end_position' => $end,
      'actions_remaining' => $actions_left,
    ];
  }

  /**
   * Execute Cast Spell action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executecastspell
   */
  public function executeCastSpell($caster_id, $spell_id, $spell_level, array $targets, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $caster = $this->findParticipant($participants, $caster_id);
    if (!$caster) {
      return ['status' => 'error', 'message' => 'Caster not found'];
    }

    if (!$this->isCurrentTurn($encounter, $participants, $caster_id)) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    // Spell data: cost defaults to 2 actions (PF2e standard).
    $spell = is_array($spell_id) ? $spell_id : ['name' => $spell_id];
    $spell_name = $spell['name'] ?? (is_string($spell_id) ? $spell_id : 'unknown');
    $spell_cost = (int) ($spell['cost'] ?? 2);
    $spell_level = (int) $spell_level;
    $delivery = $spell['delivery'] ?? 'save'; // 'attack', 'save', 'automatic'
    $damage_dice = $spell['damage'] ?? NULL;
    $damage_type = $spell['damage_type'] ?? 'untyped';
    $healing_dice = $spell['healing'] ?? NULL;
    $condition_to_apply = $spell['condition'] ?? NULL;
    $save_type = $spell['save_type'] ?? 'reflex'; // 'reflex', 'fortitude', 'will'

    // Action economy check.
    $economy = $this->rulesEngine->validateActionEconomy($caster, $spell_cost);
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    // Resolve targets.
    $resolved_targets = [];
    foreach ($targets as $tid) {
      $t = is_array($tid) ? $this->findParticipant($participants, (int) ($tid['id'] ?? $tid['target_id'] ?? 0)) : $this->findParticipant($participants, (int) $tid);
      if ($t) {
        $resolved_targets[] = $t;
      }
    }

    // Area of effect: override targets when spell has an area_type (reqs 2125–2128).
    $area_type = $spell['area_type'] ?? NULL;
    if ($area_type && $this->areaResolver) {
      $caster_pos = ['q' => (int) ($caster['position_q'] ?? 0), 'r' => (int) ($caster['position_r'] ?? 0)];
      $area_ids = [];
      switch ($area_type) {
        case 'burst':
          $origin_q = (int) ($spell['area_origin_q'] ?? $caster_pos['q']);
          $origin_r = (int) ($spell['area_origin_r'] ?? $caster_pos['r']);
          $radius = (int) ($spell['area_radius'] ?? $spell['area_length'] ?? 1);
          $area_ids = $this->areaResolver->resolveBurst($origin_q, $origin_r, $radius, $participants);
          break;

        case 'cone':
          $direction = (string) ($spell['area_direction'] ?? 'NE');
          $length = (int) ($spell['area_length'] ?? 1);
          $area_ids = $this->areaResolver->resolveCone($caster_pos, $direction, $length, $participants);
          break;

        case 'emanation':
          $radius = (int) ($spell['area_radius'] ?? $spell['area_length'] ?? 1);
          $include_origin = (bool) ($spell['area_include_origin'] ?? FALSE);
          $area_ids = $this->areaResolver->resolveEmanation($caster_pos, $radius, $participants, $include_origin);
          break;

        case 'line':
          $direction = (string) ($spell['area_direction'] ?? 'NE');
          $length = (int) ($spell['area_length'] ?? 1);
          $area_ids = $this->areaResolver->resolveLine($caster_pos, $direction, $length, $participants);
          break;
      }
      // Filter area IDs by LoE from burst origin or caster pos (req 2132).
      $origin_for_loe = ($area_type === 'burst')
        ? ['q' => (int) ($spell['area_origin_q'] ?? $caster_pos['q']), 'r' => (int) ($spell['area_origin_r'] ?? $caster_pos['r'])]
        : $caster_pos;
      $terrain_obstacles = $spell['terrain_obstacles'] ?? [];
      $area_ids = $this->areaResolver->filterByLoE($origin_for_loe, $area_ids, $participants, $terrain_obstacles);
      // Rebuild resolved_targets from area participant IDs.
      $resolved_targets = [];
      foreach ($area_ids as $aid) {
        $t = $this->findParticipant($participants, (int) $aid);
        if ($t) {
          $resolved_targets[] = $t;
        }
      }
    }

    // Spell cast validation via RulesEngine.
    $cast_check = $this->rulesEngine->validateSpellCast($caster, $spell_name, $spell_level, $resolved_targets, (int) $encounter_id);
    if (!$cast_check['is_valid']) {
      return ['status' => 'error', 'message' => $cast_check['reason']];
    }

    // Process each target.
    $target_results = [];
    $spell_attack_mod = (int) ($caster['spell_attack_bonus'] ?? $caster['level'] ?? 0);
    $spell_dc = (int) ($caster['spell_dc'] ?? (10 + ($caster['level'] ?? 0)));
    $caster_attack_mod = $this->conditionManager->getConditionModifiers($caster_id, 'attack', $encounter_id);

    foreach ($resolved_targets as $target) {
      $target_id = (int) $target['id'];
      $degree = 'success'; // default for automatic delivery
      $roll_natural = NULL;

      if ($delivery === 'attack') {
        // Spell attack roll vs target AC.
        $roll_natural = $this->numberGeneration->rollPathfinderDie(20);
        $attack_total = $roll_natural + $spell_attack_mod + $caster_attack_mod;
        $target_ac_mod = $this->conditionManager->getConditionModifiers($target_id, 'ac', $encounter_id);
        $target_ac = (int) ($target['ac'] ?? 10) + $target_ac_mod;
        $degree = $this->calculator->calculateDegreeOfSuccess($attack_total, $target_ac, $roll_natural);
      }
      elseif ($delivery === 'save') {
        // Target makes saving throw vs spell DC.
        $roll_natural = $this->numberGeneration->rollPathfinderDie(20);
        $save_bonus = (int) ($target[$save_type . '_save'] ?? $target['save_bonus'] ?? 0);
        $save_mod = $this->conditionManager->getConditionModifiers($target_id, $save_type, $encounter_id);
        // GAP-2261: Mounted rider takes -2 circumstance penalty to Reflex saves.
        if ($save_type === 'reflex') {
          $target_entity_ref = !empty($target['entity_ref']) ? json_decode($target['entity_ref'], TRUE) : [];
          if (!empty($target_entity_ref['mounted_on'])) {
            $save_mod -= 2;
          }
        }
        $save_total = $roll_natural + $save_bonus + $save_mod;
        // For saves, the target is rolling against spell DC. Invert the degree
        // so "success" means the spell hits (target failed save).
        $save_degree = $this->calculator->calculateDegreeOfSuccess($save_total, $spell_dc, $roll_natural);
        $degree_map = [
          'critical_success' => 'critical_failure',
          'success' => 'failure',
          'failure' => 'success',
          'critical_failure' => 'critical_success',
        ];
        $degree = $degree_map[$save_degree] ?? 'failure';
      }

      // Apply effects based on degree.
      $damage = 0;
      $healing = 0;
      $damage_result = NULL;
      $healing_result = NULL;
      $condition_result = NULL;

      if ($damage_dice) {
        $roll = $this->numberGeneration->rollExpression($damage_dice);
        $base_damage = (int) ($roll['total'] ?? 0);
        if ($degree === 'critical_success') {
          $damage = $base_damage * 2;
        }
        elseif ($degree === 'success') {
          $damage = $base_damage;
        }
        elseif ($degree === 'failure' && $delivery === 'save') {
          // Basic saving throw: success on save → half damage (PF2E Core p.449 req 2097).
          $damage = (int) floor($base_damage / 2);
        }
        else {
          $damage = 0;
        }
        if ($damage > 0) {
          $damage_result = $this->hpManager->applyDamage($target_id, $damage, $damage_type, [
            'action' => 'cast_spell',
            'caster' => $caster_id,
            'spell' => $spell_name,
          ], $encounter_id);
        }
      }

      if ($healing_dice) {
        $roll = $this->numberGeneration->rollExpression($healing_dice);
        $base_healing = (int) ($roll['total'] ?? 0);
        if ($degree === 'critical_success') {
          $healing = $base_healing * 2;
        }
        elseif ($degree === 'success') {
          $healing = $base_healing;
        }
        elseif ($degree === 'failure' && $delivery === 'save') {
          $healing = (int) floor($base_healing / 2);
        }
        else {
          $healing = 0;
        }
        if ($healing > 0) {
          $healing_result = $this->hpManager->applyHealing($target_id, $healing, [
            'action' => 'cast_spell',
            'caster' => $caster_id,
            'spell' => $spell_name,
          ], $encounter_id);
        }
      }

      // Conditions apply on full hit (success/crit); not on save success (failure) by default.
      if ($condition_to_apply && in_array($degree, ['success', 'critical_success'])) {
        $cond_name = is_array($condition_to_apply) ? ($condition_to_apply['name'] ?? '') : $condition_to_apply;
        $cond_value = is_array($condition_to_apply) ? (int) ($condition_to_apply['value'] ?? 1) : 1;
        if ($degree === 'critical_success') {
          $cond_value = min($cond_value + 1, 4);
        }
        if ($cond_name) {
          $condition_result = $this->conditionManager->applyCondition(
            $target_id, $cond_name, $cond_value,
            NULL, 'spell:' . $spell_name, $encounter_id
          );
        }
      }

      $target_results[] = [
        'target_id' => $target_id,
        'degree' => $degree,
        'natural_roll' => $roll_natural,
        'damage' => $damage,
        'damage_result' => $damage_result,
        'healing' => $healing,
        'healing_result' => $healing_result,
        'condition_result' => $condition_result,
      ];
    }

    // Consume actions.
    $actions_left = max(0, ((int) $caster['actions_remaining']) - $spell_cost);
    $this->store->updateParticipant($caster_id, [
      'actions_remaining' => $actions_left,
    ]);

    $this->logAction($encounter_id, $caster_id, 'cast_spell', $resolved_targets[0]['id'] ?? NULL, [
      'spell_name' => $spell_name,
      'spell_level' => $spell_level,
      'delivery' => $delivery,
      'cost' => $spell_cost,
      'targets' => array_column($resolved_targets, 'id'),
    ], [
      'target_results' => $target_results,
      'actions_remaining' => $actions_left,
    ]);

    return [
      'status' => 'ok',
      'spell_name' => $spell_name,
      'spell_level' => $spell_level,
      'target_results' => $target_results,
      'actions_remaining' => $actions_left,
    ];
  }

  /**
   * Execute a counteract or dispel action (reqs 2145–2150).
   *
   * Expected action_data keys:
   *   - 'target_effect': array with 'level', 'type', 'effect_id', optional 'counteract_dc'
   *   - 'spell_level': (int) the level of the spell used to counteract
   *   - 'action_cost': (int, default 2) number of actions spent
   */
  public function executeCounteract(int $caster_id, array $action_data, int $encounter_id): array {
    if (!$this->counteract) {
      return ['status' => 'error', 'message' => 'CounteractService not available'];
    }

    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }
    [$encounter, $participants] = $state['data'];

    $caster = $this->findParticipant($participants, $caster_id);
    if (!$caster) {
      return ['status' => 'error', 'message' => 'Caster not found'];
    }
    if (!$this->isCurrentTurn($encounter, $participants, $caster_id)) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $action_cost = (int) ($action_data['action_cost'] ?? 2);
    $economy = $this->rulesEngine->validateActionEconomy($caster, $action_cost);
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    // Inject spell_level into caster array for CounteractService.
    $caster_with_spell_level = $caster;
    $caster_with_spell_level['spell_level'] = (int) ($action_data['spell_level'] ?? $caster['level'] ?? 1);

    $target_effect = $action_data['target_effect'] ?? [];
    $result = $this->counteract->attemptCounteract($caster_with_spell_level, $target_effect, $encounter_id);

    // Consume actions.
    $actions_left = max(0, ((int) $caster['actions_remaining']) - $action_cost);
    $this->store->updateParticipant($caster_id, ['actions_remaining' => $actions_left]);

    $this->logAction($encounter_id, $caster_id, 'counteract', NULL, $action_data, $result);

    return [
      'status' => 'success',
      'counteract_result' => $result,
      'actions_remaining' => $actions_left,
    ];
  }

  protected function loadEncounterState(int $encounter_id): array {
    $encounter = $this->store->loadEncounter($encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }
    $participants = $encounter['participants'] ?? [];
    return ['status' => 'ok', 'data' => [$encounter, $participants]];
  }

  protected function findParticipant(array $participants, int $id): ?array {
    foreach ($participants as $p) {
      if ((int) $p['id'] === (int) $id) {
        return $p;
      }
    }
    return NULL;
  }

  protected function isCurrentTurn(array $encounter, array $participants, int $participant_id): bool {
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $current = $participants[$turn_index] ?? NULL;
    return $current && (int) $current['id'] === (int) $participant_id;
  }

  protected function lastPathCoordinate(array $path): array {
    if (empty($path)) {
      return ['q' => NULL, 'r' => NULL];
    }
    $last = end($path);
    return [
      'q' => isset($last['q']) ? (int) $last['q'] : NULL,
      'r' => isset($last['r']) ? (int) $last['r'] : NULL,
    ];
  }

  protected function logAction(int $encounter_id, int $participant_id, string $action_type, ?int $target_id, array $payload, array $result): void {
    try {
      $this->store->logAction([
        'encounter_id' => $encounter_id,
        'participant_id' => $participant_id,
        'action_type' => $action_type,
        'target_id' => $target_id,
        'payload' => json_encode($payload),
        'result' => json_encode($result),
      ]);
    }
    catch (\Throwable $t) {
      $this->logger->warning('Failed to log combat action: @msg', ['@msg' => $t->getMessage()]);
    }
  }

  /**
   * Execute a reaction action (spends reaction_available).
   *
   * Validates reaction availability, marks reaction as spent, and logs the action.
   * The caller is responsible for any triggering-condition checks.
   */
  public function executeReactionAction($participant_id, array $action_data, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $actor = $this->findParticipant($participants, $participant_id);
    if (!$actor) {
      return ['status' => 'error', 'message' => 'Participant not found'];
    }

    if (!$this->isCurrentTurn($encounter, $participants, $participant_id) && empty($action_data['allow_out_of_turn'])) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $economy = $this->rulesEngine->validateActionEconomy($actor, 'reaction');
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    $this->store->updateParticipant($participant_id, [
      'reaction_available' => 0,
    ]);

    $this->logAction($encounter_id, $participant_id, 'reaction', $action_data['target_id'] ?? NULL, $action_data, [
      'reaction_spent' => TRUE,
    ]);

    return [
      'status' => 'ok',
      'reaction_available' => FALSE,
      'actions_remaining' => (int) ($actor['actions_remaining'] ?? 0),
    ];
  }

  /**
   * Execute a free action (no cost; always passes if encounter is active).
   *
   * Free actions without triggers do not consume action budget or reaction slot.
   * Free actions WITH triggers behave like reactions — they require and consume
   * reaction_available (DEF-2182: PF2E Core: free actions with triggers use the
   * reaction slot, just without the "reaction" action type designation).
   */
  public function executeFreeAction($participant_id, array $action_data, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $actor = $this->findParticipant($participants, $participant_id);
    if (!$actor) {
      return ['status' => 'error', 'message' => 'Participant not found'];
    }

    $economy = $this->rulesEngine->validateActionEconomy($actor, 'free');
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    // DEF-2182: Free actions with a trigger consume reaction_available, just
    // like reactions. Validate availability and consume the slot.
    $has_trigger = !empty($action_data['has_trigger']) || !empty($action_data['trigger']);
    if ($has_trigger) {
      if (empty($actor['reaction_available'])) {
        return ['status' => 'error', 'message' => 'No reaction available for triggered free action.'];
      }
      $this->store->updateParticipant($participant_id, ['reaction_available' => 0]);
    }

    $this->logAction($encounter_id, $participant_id, 'free_action', $action_data['target_id'] ?? NULL, $action_data, [
      'free_action' => TRUE,
      'triggered' => $has_trigger,
    ]);

    return [
      'status' => 'ok',
      'actions_remaining' => (int) ($actor['actions_remaining'] ?? 0),
      'reaction_available' => $has_trigger ? FALSE : !empty($actor['reaction_available']),
    ];
  }

  /**
   * Execute a generic activity (1-, 2-, or 3-action).
   *
   * Allows dispatching non-spell activities with a configurable action cost.
   * The cost is read from $action_data['action_cost'] (int 1, 2, or 3; default 1).
   *
   * @param int $participant_id
   * @param array $action_data Keys: action_cost (int), activity_name (string, optional).
   * @param int $encounter_id
   */
  public function executeActivity($participant_id, array $action_data, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $actor = $this->findParticipant($participants, $participant_id);
    if (!$actor) {
      return ['status' => 'error', 'message' => 'Participant not found'];
    }

    if (!$this->isCurrentTurn($encounter, $participants, $participant_id)) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $action_cost = isset($action_data['action_cost']) ? $action_data['action_cost'] : 1;
    $economy = $this->rulesEngine->validateActionEconomy($actor, $action_cost);
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    $actions_left = $economy['actions_after'];
    $this->store->updateParticipant($participant_id, [
      'actions_remaining' => $actions_left,
    ]);

    $this->logAction($encounter_id, $participant_id, 'activity', $action_data['target_id'] ?? NULL, $action_data, [
      'action_cost' => $action_cost,
      'activity_name' => $action_data['activity_name'] ?? '',
    ]);

    return [
      'status' => 'ok',
      'actions_remaining' => $actions_left,
      'reaction_available' => !empty($actor['reaction_available']),
    ];
  }

}
