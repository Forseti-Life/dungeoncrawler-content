<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Combat API controller for HP, conditions, and state management.
 *
 * Handles HP changes, condition management, and combat state queries as defined in:
 * /docs/dungeoncrawler/issues/combat-api-endpoints.md
 * /docs/dungeoncrawler/issues/combat-engine-service.md (HPManager, ConditionManager)
 *
 * @see /docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md
 */
class CombatApiController extends ControllerBase {

  /**
   * The HP manager service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected $hpManager;

  /**
   * The condition manager service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected $conditionManager;

  /**
   * The combat encounter store.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $encounterStore;

  /**
   * The number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected $numberGenerator;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   */
  public function __construct($hp_manager, $condition_manager, $encounter_store, $number_generator, Connection $database) {
    $this->hpManager = $hp_manager;
    $this->conditionManager = $condition_manager;
    $this->encounterStore = $encounter_store;
    $this->numberGenerator = $number_generator;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.hp_manager'),
      $container->get('dungeoncrawler_content.condition_manager'),
      $container->get('dungeoncrawler_content.combat_encounter_store'),
      $container->get('dungeoncrawler_content.number_generation'),
      $container->get('database')
    );
  }

  /**
   * Update participant HP (damage or healing).
   *
   * PATCH /encounters/{encounter_id}/participants/{participant_id}/hp
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HP change data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated HP and conditions.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md (HPManager)
   * @see /docs/dungeoncrawler/issues/combat-api-endpoints.md#update-hp
   */
  public function updateHP($encounter_id, $participant_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    $change_type = $data['change_type'] ?? 'damage';
    $amount = (int) ($data['amount'] ?? 0);
    $damage_type = $data['damage_type'] ?? 'untyped';
    $source = $data['source'] ?? 'unknown';

    if ($amount <= 0) {
      return new JsonResponse(['error' => 'Amount must be positive'], 400);
    }

    if ($change_type === 'healing') {
      $result = $this->hpManager->applyHealing($participant_id, $amount, $source, $encounter_id);
      return new JsonResponse([
        'participant_id' => (int) $participant_id,
        'healing_applied' => $result['healing_applied'],
        'hp_after' => $result['new_hp'],
        'temp_hp_used' => 0,
        'conditions_applied' => [],
        'message' => "Healed {$result['healing_applied']} HP",
      ]);
    }

    // Default: damage.
    $result = $this->hpManager->applyDamage($participant_id, $amount, $damage_type, $source, $encounter_id);
    $temp_note = $result['temp_hp_used'] > 0 ? " ({$result['temp_hp_used']} absorbed by temp HP)" : '';

    return new JsonResponse([
      'participant_id' => (int) $participant_id,
      'hp_before' => $result['new_hp'] + $result['hp_damage'],
      'hp_after' => $result['new_hp'],
      'temp_hp_used' => $result['temp_hp_used'],
      'new_status' => $result['new_status'],
      'conditions_applied' => [],
      'message' => "Took {$result['final_damage']} damage{$temp_note}",
    ]);
  }

  /**
   * Apply temporary HP.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/temp-hp
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Temp HP amount and source.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated temp HP.
   */
  public function applyTempHP($encounter_id, $participant_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    $amount = (int) ($data['amount'] ?? 0);
    $source = $data['source'] ?? 'unknown';

    if ($amount <= 0) {
      return new JsonResponse(['error' => 'Amount must be positive'], 400);
    }

    $result = $this->hpManager->applyTemporaryHP($participant_id, $amount, $source, $encounter_id);

    return new JsonResponse([
      'participant_id' => (int) $participant_id,
      'temp_hp_before' => $result['temp_hp_before'],
      'temp_hp_after' => $result['temp_hp_after'],
      'message' => $result['applied']
        ? "Gained {$result['temp_hp_after']} temporary HP"
        : "Kept existing {$result['temp_hp_after']} temp HP (new value not higher)",
    ]);
  }

  /**
   * Apply condition to participant.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/conditions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Condition data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Applied condition with effects.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ConditionManager)
   * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_conditions)
   */
  public function applyCondition($encounter_id, $participant_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    $condition_type = $data['condition_type'] ?? '';
    $value = isset($data['value']) ? (int) $data['value'] : 1;
    $source = $data['source'] ?? 'unknown';
    $duration_type = $data['duration_type'] ?? 'encounter';
    $duration_remaining = isset($data['duration_remaining']) ? (int) $data['duration_remaining'] : NULL;

    if (empty($condition_type)) {
      return new JsonResponse(['error' => 'condition_type is required'], 400);
    }

    $duration = [
      'type' => $duration_type,
      'remaining' => $duration_remaining,
    ];

    try {
      $result = $this->conditionManager->applyCondition(
        (int) $participant_id,
        $condition_type,
        $value,
        $duration,
        $source,
        (int) $encounter_id
      );
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }

    // applyCondition returns int (condition row ID) or FALSE (no-op).
    $condition_id = is_int($result) ? $result : 0;

    return new JsonResponse([
      'condition_id' => $condition_id,
      'condition_type' => $condition_type,
      'value' => $value,
      'applied_at_round' => 0,
      'effects' => [],
      'message' => "Applied {$condition_type}" . ($value > 1 ? " {$value}" : ''),
    ], 201);
  }

  /**
   * Remove condition from participant.
   *
   * DELETE /encounters/{encounter_id}/participants/{participant_id}/conditions/{condition_id}
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param int $condition_id
   *   The condition ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Removal confirmation.
   */
  public function removeCondition($encounter_id, $participant_id, $condition_id) {
    try {
      $removed = $this->conditionManager->removeCondition(
        (int) $participant_id,
        (int) $condition_id,
        (int) $encounter_id
      );
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }

    // removeCondition returns bool.
    return new JsonResponse([
      'condition_id' => (int) $condition_id,
      'removed' => (bool) $removed,
      'message' => $removed ? 'Condition removed' : 'Condition not found or already removed',
    ]);
  }

  /**
   * List active conditions for participant.
   *
   * GET /encounters/{encounter_id}/participants/{participant_id}/conditions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   List of active conditions.
   */
  public function listConditions($encounter_id, $participant_id) {
    $conditions = $this->conditionManager->getActiveConditions(
      (int) $participant_id,
      (int) $encounter_id
    );

    // Normalize to sequential array for JSON output.
    $list = [];
    foreach ($conditions as $cid => $cond) {
      $list[] = [
        'condition_id' => (int) $cid,
        'condition_type' => $cond['condition_type'],
        'value' => $cond['value'] !== NULL ? (int) $cond['value'] : NULL,
        'duration_type' => $cond['duration_type'],
        'duration_remaining' => $cond['duration_remaining'] !== NULL ? (int) $cond['duration_remaining'] : NULL,
        'source' => $cond['source'],
        'applied_at_round' => (int) ($cond['applied_at_round'] ?? 0),
      ];
    }

    return new JsonResponse([
      'participant_id' => (int) $participant_id,
      'encounter_id' => (int) $encounter_id,
      'conditions' => $list,
    ]);
  }

  /**
   * Get initiative order.
   *
   * GET /encounters/{encounter_id}/initiative
   *
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Sorted initiative order.
   *
   * @see /docs/dungeoncrawler/issues/combat-api-endpoints.md#get-initiative-order
   */
  public function getInitiative($encounter_id) {
    $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $order = [];
    foreach ($encounter['participants'] as $p) {
      $conditions = $this->conditionManager->getActiveConditions(
        (int) $p['id'],
        (int) $encounter_id
      );
      $order[] = [
        'participant_id' => (int) $p['id'],
        'name' => $p['name'],
        'team' => $p['team'],
        'initiative' => (int) $p['initiative'],
        'hp' => (int) $p['hp'],
        'max_hp' => (int) $p['max_hp'],
        'is_defeated' => (bool) $p['is_defeated'],
        'is_current_turn' => FALSE,
        'conditions' => array_values(array_map(function ($c) {
          return [
            'condition_type' => $c['condition_type'],
            'value' => $c['value'] !== NULL ? (int) $c['value'] : NULL,
          ];
        }, $conditions)),
      ];
    }

    // Mark current turn participant.
    $turnIndex = (int) ($encounter['turn_index'] ?? 0);
    if (isset($order[$turnIndex])) {
      $order[$turnIndex]['is_current_turn'] = TRUE;
    }

    return new JsonResponse([
      'encounter_id' => (int) $encounter_id,
      'current_round' => (int) ($encounter['current_round'] ?? 0),
      'turn_index' => $turnIndex,
      'initiative_order' => $order,
    ]);
  }

  /**
   * Reroll initiative for participants.
   *
   * POST /encounters/{encounter_id}/initiative/reroll
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Participant IDs to reroll.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   New initiative values.
   */
  public function rerollInitiative($encounter_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $participant_ids = $data['participant_ids'] ?? [];

    if (empty($participant_ids)) {
      return new JsonResponse(['error' => 'participant_ids[] is required'], 400);
    }

    $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $rerolled = [];
    $participants = $encounter['participants'];

    foreach ($participants as &$p) {
      if (in_array((int) $p['id'], array_map('intval', $participant_ids))) {
        // Roll d20 for new initiative.
        $roll = $this->numberGenerator->rollExpression('1d20');
        $newInit = $roll['total'];
        $p['initiative'] = $newInit;

        $this->encounterStore->updateParticipant((int) $p['id'], [
          'initiative' => $newInit,
          'initiative_roll' => $newInit,
        ]);

        $rerolled[] = [
          'participant_id' => (int) $p['id'],
          'name' => $p['name'],
          'old_initiative' => (int) ($encounter['participants'][array_search($p['id'], array_column($encounter['participants'], 'id'))]['initiative'] ?? 0),
          'new_initiative' => $newInit,
          'roll' => $roll,
        ];
      }
    }
    unset($p);

    // Re-sort by initiative DESC.
    usort($participants, function ($a, $b) {
      return (int) $b['initiative'] - (int) $a['initiative'];
    });

    return new JsonResponse([
      'encounter_id' => (int) $encounter_id,
      'rerolled' => $rerolled,
      'new_initiative_order' => array_map(function ($p) {
        return [
          'participant_id' => (int) $p['id'],
          'name' => $p['name'],
          'initiative' => (int) $p['initiative'],
        ];
      }, $participants),
    ]);
  }

  /**
   * Add participant to combat.
   *
   * POST /encounters/{encounter_id}/participants
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Participant data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   New participant info.
   */
  public function addParticipant($encounter_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $name = $data['name'] ?? '';
    if (empty($name)) {
      return new JsonResponse(['error' => 'name is required'], 400);
    }

    // Roll initiative if requested.
    $initiative = (int) ($data['initiative'] ?? 0);
    $initiativeRoll = NULL;
    if (!empty($data['roll_initiative'])) {
      $roll = $this->numberGenerator->rollExpression('1d20');
      $initiative = $roll['total'];
      $initiativeRoll = $roll['total'];
    }

    $now = \Drupal::time()->getRequestTime();
    $participant_id = $this->database->insert('combat_participants')
      ->fields([
        'encounter_id' => (int) $encounter_id,
        'entity_id' => (int) ($data['entity_id'] ?? 0),
        'entity_ref' => $data['entity_ref'] ?? NULL,
        'name' => $name,
        'team' => $data['team'] ?? 'enemy',
        'initiative' => $initiative,
        'initiative_roll' => $initiativeRoll,
        'ac' => isset($data['ac']) ? (int) $data['ac'] : NULL,
        'hp' => isset($data['hp']) ? (int) $data['hp'] : NULL,
        'max_hp' => isset($data['max_hp']) ? (int) $data['max_hp'] : NULL,
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
        'reaction_available' => 1,
        'position_q' => isset($data['position_q']) ? (int) $data['position_q'] : NULL,
        'position_r' => isset($data['position_r']) ? (int) $data['position_r'] : NULL,
        'is_defeated' => 0,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    // Log the addition.
    $this->encounterStore->logAction([
      'encounter_id' => (int) $encounter_id,
      'participant_id' => (int) $participant_id,
      'action_type' => 'join',
      'payload' => json_encode(['name' => $name, 'team' => $data['team'] ?? 'enemy']),
      'result' => json_encode(['initiative' => $initiative]),
    ]);

    return new JsonResponse([
      'participant_id' => (int) $participant_id,
      'name' => $name,
      'team' => $data['team'] ?? 'enemy',
      'initiative' => $initiative,
      'added_at_round' => (int) ($encounter['current_round'] ?? 0),
      'message' => "Participant '{$name}' added to encounter",
    ], 201);
  }

  /**
   * Remove participant from combat.
   *
   * DELETE /encounters/{encounter_id}/participants/{participant_id}
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Removal reason.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Removal confirmation.
   */
  public function removeParticipant($encounter_id, $participant_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $reason = $data['reason'] ?? 'removed';

    $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    // Verify participant belongs to this encounter.
    $found = FALSE;
    foreach ($encounter['participants'] as $p) {
      if ((int) $p['id'] === (int) $participant_id) {
        $found = TRUE;
        break;
      }
    }

    if (!$found) {
      return new JsonResponse(['error' => 'Participant not found in encounter'], 404);
    }

    // Mark defeated.
    $this->encounterStore->updateParticipant((int) $participant_id, [
      'is_defeated' => 1,
    ]);

    // Log removal.
    $this->encounterStore->logAction([
      'encounter_id' => (int) $encounter_id,
      'participant_id' => (int) $participant_id,
      'action_type' => 'removed',
      'payload' => json_encode(['reason' => $reason]),
      'result' => json_encode(['is_defeated' => TRUE]),
    ]);

    return new JsonResponse([
      'participant_id' => (int) $participant_id,
      'removed' => TRUE,
      'reason' => $reason,
      'message' => 'Participant removed from encounter',
    ]);
  }

  /**
   * Update participant stats.
   *
   * PATCH /encounters/{encounter_id}/participants/{participant_id}
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Updated fields.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Update confirmation.
   */
  public function updateParticipant($encounter_id, $participant_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    // Verify participant belongs to this encounter.
    $found = FALSE;
    foreach ($encounter['participants'] as $p) {
      if ((int) $p['id'] === (int) $participant_id) {
        $found = TRUE;
        break;
      }
    }

    if (!$found) {
      return new JsonResponse(['error' => 'Participant not found in encounter'], 404);
    }

    // Whitelist of updatable fields.
    $allowed = [
      'name', 'team', 'ac', 'hp', 'max_hp',
      'position_q', 'position_r', 'initiative',
      'actions_remaining', 'attacks_this_turn', 'reaction_available',
    ];

    $fields = [];
    foreach ($allowed as $key) {
      if (array_key_exists($key, $data)) {
        $fields[$key] = $data[$key];
      }
    }

    if (empty($fields)) {
      return new JsonResponse(['error' => 'No valid fields to update'], 400);
    }

    $this->encounterStore->updateParticipant((int) $participant_id, $fields);

    return new JsonResponse([
      'participant_id' => (int) $participant_id,
      'updated_fields' => array_keys($fields),
      'message' => 'Participant updated',
    ]);
  }

  /**
   * Get combat log.
   *
   * GET /encounters/{encounter_id}/log
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Filter and pagination params.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Combat log entries.
   *
   * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_actions)
   */
  public function getLog($encounter_id, Request $request) {
    $page = max(1, (int) $request->query->get('page', 1));
    $perPage = min(200, max(1, (int) $request->query->get('per_page', 50)));
    $offset = ($page - 1) * $perPage;

    // Build base query.
    $query = $this->database->select('combat_actions', 'ca')
      ->fields('ca')
      ->condition('ca.encounter_id', (int) $encounter_id)
      ->orderBy('ca.created', 'ASC')
      ->orderBy('ca.id', 'ASC');

    // Optional filters.
    $participantFilter = $request->query->get('participant_id');
    if ($participantFilter !== NULL) {
      $query->condition('ca.participant_id', (int) $participantFilter);
    }

    $actionTypeFilter = $request->query->get('action_type');
    if ($actionTypeFilter !== NULL) {
      $query->condition('ca.action_type', $actionTypeFilter);
    }

    // Count total before pagination.
    $countQuery = clone $query;
    $total = (int) $countQuery->countQuery()->execute()->fetchField();

    // Apply pagination.
    $query->range($offset, $perPage);
    $rows = $query->execute()->fetchAll();

    $entries = [];
    foreach ($rows as $row) {
      $entries[] = [
        'action_id' => (int) $row->id,
        'participant_id' => (int) $row->participant_id,
        'action_type' => $row->action_type,
        'target_id' => $row->target_id !== NULL ? (int) $row->target_id : NULL,
        'payload' => $row->payload ? json_decode($row->payload, TRUE) : NULL,
        'result' => $row->result ? json_decode($row->result, TRUE) : NULL,
        'created' => (int) $row->created,
      ];
    }

    return new JsonResponse([
      'encounter_id' => (int) $encounter_id,
      'log_entries' => $entries,
      'meta' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
      ],
    ]);
  }

  /**
   * Get combat statistics.
   *
   * GET /encounters/{encounter_id}/statistics
   *
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Aggregate statistics.
   */
  public function getStatistics($encounter_id) {
    $eid = (int) $encounter_id;

    $encounter = $this->encounterStore->loadEncounter($eid);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    // Total actions by type.
    $actionRows = $this->database->select('combat_actions', 'ca')
      ->fields('ca', ['action_type'])
      ->condition('ca.encounter_id', $eid)
      ->execute()
      ->fetchAll();

    $totalActions = count($actionRows);
    $actionsByType = [];
    foreach ($actionRows as $row) {
      $type = $row->action_type;
      $actionsByType[$type] = ($actionsByType[$type] ?? 0) + 1;
    }

    // Damage statistics from damage log.
    $damageRows = $this->database->select('combat_damage_log', 'dl')
      ->fields('dl', ['participant_id', 'amount', 'damage_type', 'source'])
      ->condition('dl.encounter_id', $eid)
      ->execute()
      ->fetchAll();

    $totalDamage = 0;
    $damageByType = [];
    $damageByParticipant = [];
    foreach ($damageRows as $row) {
      $amt = (int) $row->amount;
      $totalDamage += $amt;

      $dtype = $row->damage_type ?? 'untyped';
      $damageByType[$dtype] = ($damageByType[$dtype] ?? 0) + $amt;

      $pid = (int) $row->participant_id;
      $damageByParticipant[$pid] = ($damageByParticipant[$pid] ?? 0) + $amt;
    }

    // Top damage dealer (participant who dealt most — sourced from actions, not the target in damage_log).
    // damage_log records damage *received*. We derive top dealer from action log payload.
    $topDamageDealer = NULL;
    $dealerDamage = [];
    foreach ($actionRows as $row) {
      if ($row->action_type === 'attack' || $row->action_type === 'cast_spell') {
        // We count actions, not damage, as a proxy for dealer ranking.
        $pid = (int) ($row->participant_id ?? 0);
        $dealerDamage[$pid] = ($dealerDamage[$pid] ?? 0) + 1;
      }
    }
    if (!empty($dealerDamage)) {
      arsort($dealerDamage);
      $topDamageDealer = array_key_first($dealerDamage);
    }

    // Healing statistics (actions of type 'heal').
    $totalHealing = 0;
    $healingByParticipant = [];
    foreach ($actionRows as $row) {
      if ($row->action_type === 'heal') {
        $pid = (int) ($row->participant_id ?? 0);
        $healingByParticipant[$pid] = ($healingByParticipant[$pid] ?? 0) + 1;
        $totalHealing++;
      }
    }

    $roundsElapsed = (int) ($encounter['current_round'] ?? 0);

    return new JsonResponse([
      'encounter_id' => $eid,
      'rounds_elapsed' => $roundsElapsed,
      'total_actions' => $totalActions,
      'actions_by_type' => $actionsByType,
      'damage_statistics' => [
        'total_damage' => $totalDamage,
        'damage_by_type' => $damageByType,
        'damage_received_by_participant' => $damageByParticipant,
        'top_attack_actions_participant' => $topDamageDealer,
      ],
      'healing_statistics' => [
        'total_healing_actions' => $totalHealing,
        'healing_by_participant' => $healingByParticipant,
      ],
      'avg_actions_per_round' => $roundsElapsed > 0
        ? round($totalActions / $roundsElapsed, 1)
        : 0,
    ]);
  }

}
