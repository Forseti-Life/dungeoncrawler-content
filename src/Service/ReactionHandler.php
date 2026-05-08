<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Reaction Handler service - Handle reaction triggers and execution.
 *
 * PF2e reactions: Each participant gets ONE reaction per round (refreshed at
 * the start of their turn). Reactions fire in response to specific triggers.
 *
 * Implemented reactions:
 * - Attack of Opportunity (AoO): Triggered by manipulate/move/ranged actions
 *   within reach. Fighter class feature; some monsters have it.
 * - Shield Block: Triggered by taking physical damage while shield is raised.
 *   Reduces damage by shield hardness; excess damages the shield.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ReactionHandler)
 */
class ReactionHandler {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatCalculator
   */
  protected $calculator;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected $hpManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $store;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected $numberGeneration;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected $conditionManager;

  /**
   * Action traits that trigger Attack of Opportunity.
   */
  const AOO_TRIGGERS = ['manipulate', 'move', 'concentrate'];

  /**
   * Damage types that Shield Block can reduce.
   */
  const SHIELD_BLOCK_TYPES = ['slashing', 'piercing', 'bludgeoning', 'physical'];

  public function __construct(
    Connection $database,
    CombatCalculator $calculator,
    HPManager $hp_manager,
    CombatEncounterStore $store,
    NumberGenerationService $number_generation,
    ConditionManager $condition_manager
  ) {
    $this->database = $database;
    $this->calculator = $calculator;
    $this->hpManager = $hp_manager;
    $this->store = $store;
    $this->numberGeneration = $number_generation;
    $this->conditionManager = $condition_manager;
  }

  // -----------------------------------------------------------------------
  // Reaction checking.
  // -----------------------------------------------------------------------

  /**
   * Check for reactions triggered by an action.
   *
   * Scans all participants (except the actor) for available reactions
   * matching the trigger action's traits.
   *
   * @param int $encounter_id
   *   Encounter ID.
   * @param array $action
   *   Action being performed: ['type' => string, 'traits' => string[],
   *   'target_id' => int|null].
   * @param int $actor_id
   *   Participant performing the action.
   *
   * @return array
   *   List of available reactions: [['participant_id', 'reaction_type',
   *   'trigger_match'], ...]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#checkforreactions
   */
  public function checkForReactions($encounter_id, $action, $actor_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return [];
    }

    $action_traits = array_map('strtolower', (array) ($action['traits'] ?? []));
    $action_type = strtolower($action['type'] ?? '');

    // Infer traits from action type if not explicitly provided.
    if (empty($action_traits)) {
      $action_traits = $this->inferTraitsFromAction($action_type);
    }

    $available = [];

    foreach ($encounter['participants'] as $participant) {
      $pid = (int) $participant['id'];

      // Skip actor and defeated participants.
      if ($pid === (int) $actor_id || !empty($participant['is_defeated'])) {
        continue;
      }

      // Check if reaction is available.
      if (empty($participant['reaction_available'])) {
        continue;
      }

      // Check Attack of Opportunity.
      if ($this->hasAttackOfOpportunity($participant)) {
        $trigger_match = array_intersect($action_traits, self::AOO_TRIGGERS);
        if (!empty($trigger_match)) {
          // Check reach: actor must be within melee reach.
          if ($this->isInReach($participant, $actor_id, $encounter)) {
            $available[] = [
              'participant_id' => $pid,
              'participant_name' => $participant['name'] ?? 'Unknown',
              'reaction_type' => 'attack_of_opportunity',
              'trigger_match' => array_values($trigger_match),
            ];
          }
        }
      }
    }

    return $available;
  }

  /**
   * Check for Shield Block reactions triggered by incoming damage.
   *
   * @param int $encounter_id
   * @param int $target_id
   *   Participant taking damage.
   * @param int $damage
   *   Incoming damage amount.
   * @param string $damage_type
   *   Damage type.
   *
   * @return array|null
   *   Shield Block reaction opportunity, or NULL if not available.
   */
  public function checkForShieldBlock($encounter_id, $target_id, $damage, $damage_type) {
    $target = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', (int) $target_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$target || empty($target['reaction_available'])) {
      return NULL;
    }

    if (!$this->hasShieldRaised($target)) {
      return NULL;
    }

    // Shield Block works on physical damage types.
    $type = strtolower((string) $damage_type);
    if (!in_array($type, self::SHIELD_BLOCK_TYPES, TRUE)) {
      return NULL;
    }

    return [
      'participant_id' => (int) $target['id'],
      'participant_name' => $target['name'] ?? 'Unknown',
      'reaction_type' => 'shield_block',
      'incoming_damage' => (int) $damage,
      'damage_type' => $type,
    ];
  }

  // -----------------------------------------------------------------------
  // Reaction execution.
  // -----------------------------------------------------------------------

  /**
   * Execute a reaction.
   *
   * @param int $participant_id
   *   Participant using their reaction.
   * @param string $reaction_type
   *   'attack_of_opportunity' or 'shield_block'.
   * @param array $trigger_action
   *   The action/event that triggered this reaction.
   * @param int $encounter_id
   *
   * @return array
   *   Reaction result with relevant details.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executereaction
   */
  public function executeReaction($participant_id, $reaction_type, $trigger_action, $encounter_id) {
    // Mark reaction as used.
    $this->store->updateParticipant((int) $participant_id, [
      'reaction_available' => 0,
    ]);

    // Log the reaction.
    $this->store->logAction([
      'encounter_id' => (int) $encounter_id,
      'participant_id' => (int) $participant_id,
      'action_type' => $reaction_type,
      'target_id' => $trigger_action['actor_id'] ?? $trigger_action['target_id'] ?? NULL,
      'payload' => json_encode($trigger_action),
      'result' => NULL,
    ]);

    switch ($reaction_type) {
      case 'attack_of_opportunity':
        return $this->processAttackOfOpportunity(
          (int) $participant_id,
          $trigger_action,
          (int) ($trigger_action['actor_id'] ?? 0),
          (int) $encounter_id
        );

      case 'shield_block':
        return $this->processShieldBlock(
          (int) $participant_id,
          (int) ($trigger_action['damage'] ?? 0),
          $trigger_action['damage_type'] ?? 'physical',
          (int) $encounter_id
        );

      default:
        return ['error' => "Unknown reaction type: {$reaction_type}"];
    }
  }

  // -----------------------------------------------------------------------
  // Attack of Opportunity.
  // -----------------------------------------------------------------------

  /**
   * Process Attack of Opportunity.
   *
   * PF2e AoO (Core Rulebook p. 142):
   * - Triggered by move/manipulate/concentrate actions within reach.
   * - Make a melee Strike at current MAP (typically 0 since it's off-turn).
   * - On critical hit with a move trigger, target stops moving.
   * - On critical hit with a manipulate trigger, action is disrupted.
   *
   * @param int $participant_id
   *   Participant making the AoO.
   * @param array $triggering_action
   *   The action that triggered AoO.
   * @param int $target_id
   *   The participant being struck.
   * @param int $encounter_id
   *
   * @return array
   *   ['type' => 'attack_of_opportunity', 'roll' => int, 'total' => int,
   *    'degree' => string, 'damage' => int, 'disrupted' => bool,
   *    'movement_stopped' => bool]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#processattackofopportunity
   */
  public function processAttackOfOpportunity($participant_id, $triggering_action, $target_id, $encounter_id) {
    $attacker = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', (int) $participant_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    $target = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', (int) $target_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$attacker || !$target) {
      return ['type' => 'attack_of_opportunity', 'error' => 'Participant not found'];
    }

    // AoO uses no MAP (it's a reaction, not part of normal turn actions).
    $attack_bonus = $this->resolveAttackBonus($attacker);
    $roll = $this->numberGeneration->rollPathfinderDie(20);
    $attack_mod = $this->conditionManager->getConditionModifiers((int) $participant_id, 'attack', (int) $encounter_id);
    $total = $roll + $attack_bonus + $attack_mod;

    $target_ac = (int) ($target['ac'] ?? 10);
    $target_ac_mod = $this->conditionManager->getConditionModifiers((int) $target_id, 'ac', (int) $encounter_id);
    $target_ac += $target_ac_mod;

    $degree = $this->calculator->calculateDegreeOfSuccess($total, $target_ac, $roll);

    $damage = 0;
    $damage_result = NULL;
    $disrupted = FALSE;
    $movement_stopped = FALSE;

    if ($degree === 'success' || $degree === 'critical_success') {
      $damage = $this->resolveWeaponDamage($attacker);
      if ($degree === 'critical_success') {
        $damage *= 2;

        // On crit: disrupt manipulate actions or stop movement.
        $trigger_traits = array_map('strtolower', (array) ($triggering_action['traits'] ?? []));
        if (in_array('manipulate', $trigger_traits, TRUE) || in_array('concentrate', $trigger_traits, TRUE)) {
          $disrupted = TRUE;
        }
        if (in_array('move', $trigger_traits, TRUE)) {
          $movement_stopped = TRUE;
        }
      }

      $damage_result = $this->hpManager->applyDamage(
        (int) $target_id,
        $damage,
        'physical',
        ['reaction' => 'attack_of_opportunity', 'attacker' => (int) $participant_id],
        (int) $encounter_id
      );
    }

    // Update action log with result.
    $result = [
      'type' => 'attack_of_opportunity',
      'attacker_id' => (int) $participant_id,
      'target_id' => (int) $target_id,
      'roll' => $roll,
      'attack_bonus' => $attack_bonus,
      'total' => $total,
      'target_ac' => $target_ac,
      'degree' => $degree,
      'damage' => $damage,
      'damage_result' => $damage_result,
      'disrupted' => $disrupted,
      'movement_stopped' => $movement_stopped,
    ];

    return $result;
  }

  // -----------------------------------------------------------------------
  // Shield Block.
  // -----------------------------------------------------------------------

  /**
   * Process Shield Block.
   *
   * PF2e Shield Block (Core Rulebook p. 266):
   * - Prerequisite: Shield is raised (Raise a Shield action used this turn).
   * - Reduce incoming damage by shield's Hardness.
   * - Remaining damage is split: participant takes it AND shield takes it.
   * - If shield HP reaches 0, it's broken. If it reaches -BT, destroyed.
   *
   * Shield defaults used if entity_ref doesn't specify:
   *   Hardness 3, HP 12, BT 6 (wooden shield).
   *
   * @param int $participant_id
   *   Participant blocking with shield.
   * @param int $incoming_damage
   *   Total incoming damage.
   * @param string $damage_type
   *   Damage type (must be physical for Shield Block).
   * @param int $encounter_id
   *
   * @return array
   *   ['type' => 'shield_block', 'damage_blocked' => int,
   *    'damage_to_participant' => int, 'damage_to_shield' => int,
   *    'shield_hp' => int, 'shield_broke' => bool, 'shield_destroyed' => bool]
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#processshieldblock
   */
  public function processShieldBlock($participant_id, $incoming_damage, $damage_type, $encounter_id) {
    $participant = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', (int) $participant_id)
      ->condition('encounter_id', (int) $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$participant) {
      return ['type' => 'shield_block', 'error' => 'Participant not found', 'damage_blocked' => 0, 'shield_broke' => FALSE];
    }

    $shield = $this->resolveShieldStats($participant);
    $hardness = (int) $shield['hardness'];
    $shield_hp = (int) $shield['hp'];
    $shield_bt = (int) $shield['bt'];

    $incoming = (int) $incoming_damage;

    // Reduce damage by hardness (hardness absorbs).
    $damage_blocked = min($hardness, $incoming);
    $remaining_damage = max(0, $incoming - $hardness);

    // Remaining damage applies to BOTH the participant AND the shield.
    $damage_to_participant = $remaining_damage;
    $damage_to_shield = $remaining_damage;

    $new_shield_hp = $shield_hp - $damage_to_shield;
    $shield_broke = $new_shield_hp <= $shield_bt && $shield_hp > $shield_bt;
    $shield_destroyed = $new_shield_hp <= 0;

    // Persist shield HP change in entity_ref JSON.
    $this->updateShieldHP($participant, max(0, $new_shield_hp));

    // Apply remaining damage to participant.
    $damage_result = NULL;
    if ($damage_to_participant > 0) {
      $damage_result = $this->hpManager->applyDamage(
        (int) $participant_id,
        $damage_to_participant,
        $damage_type,
        ['reaction' => 'shield_block'],
        (int) $encounter_id
      );
    }

    return [
      'type' => 'shield_block',
      'damage_blocked' => $damage_blocked,
      'damage_to_participant' => $damage_to_participant,
      'damage_to_shield' => $damage_to_shield,
      'shield_hp_before' => $shield_hp,
      'shield_hp_after' => max(0, $new_shield_hp),
      'shield_broke' => $shield_broke,
      'shield_destroyed' => $shield_destroyed,
      'damage_result' => $damage_result,
    ];
  }

  // -----------------------------------------------------------------------
  // Helper methods.
  // -----------------------------------------------------------------------

  /**
   * Check if participant has Attack of Opportunity ability.
   *
   * PF2e: Fighters get AoO at level 1. Other classes/monsters may have it
   * if specified in their features.
   *
   * @param array $participant
   * @return bool
   */
  protected function hasAttackOfOpportunity(array $participant): bool {
    $entity_ref = $this->decodeEntityRef($participant);

    // Check explicit ability list.
    $abilities = (array) ($entity_ref['abilities'] ?? $entity_ref['features'] ?? []);
    foreach ($abilities as $ability) {
      $name = strtolower(is_string($ability) ? $ability : ($ability['name'] ?? ''));
      if (strpos($name, 'attack of opportunity') !== FALSE || $name === 'aoo') {
        return TRUE;
      }
    }

    // Fighter class gets AoO at level 1.
    $class = strtolower($entity_ref['class'] ?? '');
    if ($class === 'fighter') {
      return TRUE;
    }

    // Some creatures have it flagged.
    return !empty($entity_ref['has_aoo']) || !empty($entity_ref['attack_of_opportunity']);
  }

  /**
   * Check if participant has a shield raised.
   *
   * @param array $participant
   * @return bool
   */
  protected function hasShieldRaised(array $participant): bool {
    $entity_ref = $this->decodeEntityRef($participant);
    return !empty($entity_ref['shield_raised']);
  }

  /**
   * Resolve shield stats from participant entity_ref.
   *
   * @param array $participant
   * @return array ['hardness' => int, 'hp' => int, 'bt' => int]
   */
  protected function resolveShieldStats(array $participant): array {
    $entity_ref = $this->decodeEntityRef($participant);
    $shield = $entity_ref['shield'] ?? [];

    return [
      'hardness' => (int) ($shield['hardness'] ?? 3),
      'hp' => (int) ($shield['hp'] ?? $shield['current_hp'] ?? 12),
      'bt' => (int) ($shield['bt'] ?? $shield['broken_threshold'] ?? 6),
    ];
  }

  /**
   * Check if actor is within melee reach of the reactor.
   *
   * Uses hex distance (position_q, position_r). Default reach = 1 hex (5ft).
   *
   * @param array $reactor
   * @param int $actor_id
   * @param array $encounter
   * @return bool
   */
  protected function isInReach(array $reactor, int $actor_id, array $encounter): bool {
    $actor = NULL;
    foreach ($encounter['participants'] as $p) {
      if ((int) $p['id'] === $actor_id) {
        $actor = $p;
        break;
      }
    }

    if (!$actor) {
      return FALSE;
    }

    // If positions aren't set, assume in reach (theater of mind).
    $rq = $reactor['position_q'] ?? NULL;
    $rr = $reactor['position_r'] ?? NULL;
    $aq = $actor['position_q'] ?? NULL;
    $ar = $actor['position_r'] ?? NULL;

    if ($rq === NULL || $rr === NULL || $aq === NULL || $ar === NULL) {
      return TRUE;
    }

    $distance = $this->hexDistance((int) $rq, (int) $rr, (int) $aq, (int) $ar);
    $entity_ref = $this->decodeEntityRef($reactor);
    $reach = (int) ($entity_ref['reach'] ?? 1);

    return $distance <= $reach;
  }

  /**
   * Hex distance between two axial coordinates.
   */
  protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {
    $dq = abs($q1 - $q2);
    $dr = abs($r1 - $r2);
    $ds = abs((-$q1 - $r1) - (-$q2 - $r2));
    return (int) (($dq + $dr + $ds) / 2);
  }

  /**
   * Resolve attack bonus for a participant (from entity_ref).
   *
   * @param array $participant
   * @return int
   */
  protected function resolveAttackBonus(array $participant): int {
    $entity_ref = $this->decodeEntityRef($participant);
    return (int) ($entity_ref['attack_bonus'] ?? $entity_ref['melee_attack'] ?? 0);
  }

  /**
   * Resolve base weapon damage for AoO strikes.
   *
   * @param array $participant
   * @return int
   */
  protected function resolveWeaponDamage(array $participant): int {
    $entity_ref = $this->decodeEntityRef($participant);

    // Try to roll damage dice if specified.
    $dice = $entity_ref['damage_dice'] ?? $entity_ref['melee_damage'] ?? NULL;
    if ($dice && is_string($dice)) {
      try {
        $result = $this->numberGeneration->rollNotation($dice);
        return (int) $result['total'];
      }
      catch (\Exception $e) {
        // Fall through to static damage.
      }
    }

    return (int) ($entity_ref['damage'] ?? $entity_ref['base_damage'] ?? 4);
  }

  /**
   * Infer action traits from action type.
   *
   * @param string $action_type
   * @return string[]
   */
  protected function inferTraitsFromAction(string $action_type): array {
    $map = [
      'stride' => ['move'],
      'step' => ['move'],
      'crawl' => ['move'],
      'fly' => ['move'],
      'swim' => ['move'],
      'tumble_through' => ['move'],
      'interact' => ['manipulate'],
      'draw_weapon' => ['manipulate'],
      'reload' => ['manipulate'],
      'drink_potion' => ['manipulate'],
      'activate_item' => ['manipulate'],
      'cast_spell' => ['manipulate', 'concentrate'],
      'recall_knowledge' => ['concentrate'],
      'seek' => ['concentrate'],
    ];

    return $map[$action_type] ?? [];
  }

  /**
   * Update shield HP in participant entity_ref JSON.
   *
   * @param array $participant
   * @param int $new_hp
   */
  protected function updateShieldHP(array $participant, int $new_hp): void {
    $entity_ref = $this->decodeEntityRef($participant);
    if (!isset($entity_ref['shield'])) {
      $entity_ref['shield'] = [];
    }
    $entity_ref['shield']['hp'] = $new_hp;
    $entity_ref['shield']['current_hp'] = $new_hp;

    if (isset($entity_ref['shield']['bt']) && $new_hp <= (int) $entity_ref['shield']['bt']) {
      $entity_ref['shield']['broken'] = TRUE;
    }

    $this->database->update('combat_participants')
      ->fields([
        'entity_ref' => json_encode($entity_ref),
        'updated' => time(),
      ])
      ->condition('id', (int) $participant['id'])
      ->execute();
  }

  /**
   * Decode entity_ref JSON from participant.
   *
   * @param array $participant
   * @return array
   */
  protected function decodeEntityRef(array $participant): array {
    $ref = $participant['entity_ref'] ?? NULL;
    if ($ref && is_string($ref)) {
      $decoded = json_decode($ref, TRUE);
      return is_array($decoded) ? $decoded : [];
    }
    return is_array($ref) ? $ref : [];
  }

}
