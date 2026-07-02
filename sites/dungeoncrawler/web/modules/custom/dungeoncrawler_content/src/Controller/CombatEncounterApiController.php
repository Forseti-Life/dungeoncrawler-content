<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Event\EntityDefeatedEvent;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lightweight combat encounter API for hexmap integration.
 *
 * Provides stubbed turn lifecycle endpoints while the full combat engine
 * services are being implemented. State is stored in a key/value store so the
 * frontend can rely on stable encounter IDs across requests.
 */
class CombatEncounterApiController extends ControllerBase {

  /**
   * Encounter storage service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $encounterStore;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Encounter AI integration service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService
   */
  protected $encounterAiIntegration;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Character state service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CharacterStateService
   */
  protected $characterStateService;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected $numberGeneration;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructor.
   */
  public function __construct(CombatEncounterStore $encounter_store, ConfigFactoryInterface $config_factory, EncounterAiIntegrationService $encounter_ai_integration, Connection $database, CharacterStateService $character_state_service, NumberGenerationService $number_generation, EventDispatcherInterface $event_dispatcher) {
    $this->encounterStore = $encounter_store;
    $this->configFactory = $config_factory;
    $this->encounterAiIntegration = $encounter_ai_integration;
    $this->database = $database;
    $this->characterStateService = $character_state_service;
    $this->numberGeneration = $number_generation;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.combat_encounter_store'),
      $container->get('config.factory'),
      $container->get('dungeoncrawler_content.encounter_ai_integration'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.character_state'),
      $container->get('dungeoncrawler_content.number_generation'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Return the current combat/encounter state for a campaign + room.
   *
   * Called periodically by the JS client for server-state sync.
   * Returns the latest active encounter if one exists, otherwise a
   * minimal "no active encounter" payload so the client can proceed.
   */
  public function currentState(Request $request): JsonResponse {
    $campaign_id = (int) $request->query->get('campaignId', 0);
    $room_id = (string) $request->query->get('roomId', '');

    if ($campaign_id <= 0) {
      return new JsonResponse([
        'success' => TRUE,
        'data' => ['encounter_id' => NULL, 'status' => 'idle'],
      ]);
    }

    // Look up the latest active encounter for this campaign + room.
    try {
      $encounter_id = $this->database->select('combat_encounters', 'e')
        ->fields('e', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('status', 'active')
        ->orderBy('updated', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
    }
    catch (\Exception $e) {
      // Table may not exist yet if no encounters have been created.
      $encounter_id = FALSE;
    }

    if (!$encounter_id) {
      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'encounter_id' => NULL,
          'status' => 'idle',
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
        ],
      ]);
    }

    $encounter = $this->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse([
        'success' => TRUE,
        'data' => ['encounter_id' => NULL, 'status' => 'idle'],
      ]);
    }

    return new JsonResponse([
      'success' => TRUE,
      'data' => $this->buildEncounterResponse($encounter),
    ]);
  }

  /**
   * Start a new encounter.
   */
  public function start(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];

    $participants = $this->normalizeParticipants($data['entities'] ?? []);
    if (empty($participants)) {
      return new JsonResponse([
        'error' => 'At least one participant is required',
      ], 400);
    }

    // Sort by initiative (desc, then roll, then name).
    usort($participants, function (array $a, array $b) {
      $cmp = ($b['initiative'] ?? 0) <=> ($a['initiative'] ?? 0);
      if ($cmp !== 0) {
        return $cmp;
      }
      $cmp = ($b['initiative_roll'] ?? 0) <=> ($a['initiative_roll'] ?? 0);
      if ($cmp !== 0) {
        return $cmp;
      }
      return strcmp((string) $a['name'], (string) $b['name']);
    });

    // Reset turn index to the first non-defeated participant.
    $turn_index = $this->findNextTurnIndex($participants, -1);

    $encounter_id = $this->encounterStore->createEncounter(
      $data['campaignId'] ?? NULL,
      $data['roomId'] ?? NULL,
      $participants,
      $data['mapId'] ?? NULL
    );

    // Persist the computed turn index.
    $this->encounterStore->updateEncounter($encounter_id, [
      'turn_index' => $turn_index,
      'current_round' => 1,
      'status' => 'active',
    ]);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $encounter['turn_index'] = $turn_index;

    return new JsonResponse($this->buildEncounterResponse($encounter), 201);
  }

  /**
   * Advance turn for the active encounter.
   */
  public function endTurn(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

      $encounter = $this->autoPlayNonPlayerTurns($this->loadEncounter($encounter_id));
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $participant_count = count($encounter['participants']);
    if ($participant_count === 0) {
      return new JsonResponse($this->buildEncounterResponse($encounter));
    }

    $next_index = $this->findNextTurnIndex($encounter['participants'], $encounter['turn_index']);

    // If we wrapped around, increment round.
    $fields = [
      'turn_index' => $next_index,
    ];
    if ($next_index <= $encounter['turn_index']) {
      $fields['current_round'] = (int) $encounter['current_round'] + 1;
      $encounter['current_round'] = $fields['current_round'];
    }
    $encounter['turn_index'] = $next_index;

    $this->encounterStore->updateEncounter($encounter_id, $fields);
    $next_participant = $encounter['participants'][$next_index] ?? NULL;
    if (!empty($next_participant['id'])) {
      $this->encounterStore->updateParticipant((int) $next_participant['id'], [
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
      ]);
    }
    $encounter = $this->autoPlayNonPlayerTurns($this->loadEncounter($encounter_id));

    return new JsonResponse($this->buildEncounterResponse($encounter));
  }

  /**
   * End an encounter.
   */
  public function end(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter($encounter_id);
    if ($encounter) {
      $this->encounterStore->updateEncounter($encounter_id, ['status' => 'ended']);
    }

    return new JsonResponse([
      'encounter_id' => $encounter_id,
      'ended' => TRUE,
    ]);
  }

  /**
   * Get encounter state for a given encounterId.
   */
  public function get(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    return new JsonResponse($this->buildEncounterResponse($encounter));
  }

  /**
   * Replace encounter state (turn index/status/participants) with optimistic lock.
   */
  public function set(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;
    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $expected_version = isset($data['expectedVersion']) ? (int) $data['expectedVersion'] : NULL;
    $current_version = (int) ($encounter['updated'] ?? 0);
    if ($expected_version !== NULL && $expected_version !== $current_version) {
      return new JsonResponse([
        'error' => 'Version conflict',
        'currentVersion' => $current_version,
        'state' => $this->buildEncounterResponse($encounter),
      ], 409);
    }

    // Core fields update
    $fields = [];
    if (isset($data['turn_index'])) {
      $fields['turn_index'] = (int) $data['turn_index'];
    }
    if (isset($data['current_round'])) {
      $fields['current_round'] = (int) $data['current_round'];
    }
    if (!empty($data['status'])) {
      $fields['status'] = $data['status'];
    }
    if ($fields) {
      $this->encounterStore->updateEncounter((int) $encounter_id, $fields);
    }

    // Replace participants when provided
    if (!empty($data['participants']) && is_array($data['participants'])) {
      $this->encounterStore->saveParticipants((int) $encounter_id, $data['participants']);
    }

    $fresh = $this->loadEncounter((int) $encounter_id);
    return new JsonResponse($this->buildEncounterResponse($fresh));
  }

  /**
   * Execute a basic attack (stub).
   */
  public function attack(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];

    $encounter_id = $data['encounterId'] ?? NULL;
    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter($encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $target_ref = $data['targetId'] ?? NULL;
    $attacker_ref = $data['attackerId'] ?? NULL;
    $target = $this->findParticipantByReference($encounter['participants'], $target_ref);
    $attacker = $this->findParticipantByReference($encounter['participants'], $attacker_ref);

    if (!$attacker) {
      return new JsonResponse(['error' => 'Attacker not found in encounter'], 400);
    }

    $attacker_actions_remaining = (int) ($attacker['actions_remaining'] ?? 0);
    if ($attacker_actions_remaining <= 0) {
      return new JsonResponse(['error' => 'No actions remaining for attacker'], 409);
    }

    $requested_damage = isset($data['damage']) ? (int) $data['damage'] : 0;
    $damage = $requested_damage > 0 ? $requested_damage : $this->numberGeneration->rollRange(1, 8);

    if ($target && $damage > 0) {
      $hp_before = $target['hp'] ?? NULL;
      $hp_after = $hp_before !== NULL ? max(0, $hp_before - $damage) : NULL;
      $was_already_defeated = !empty($target['is_defeated']);

      $this->encounterStore->updateParticipant((int) $target['id'], [
        'hp' => $hp_after,
        'is_defeated' => ($hp_after !== NULL && $hp_after <= 0) ? 1 : 0,
      ]);

      // Dispatch entity defeated event if this was the lethal blow
      $is_now_defeated = ($hp_after !== NULL && $hp_after <= 0);
      if ($is_now_defeated && !$was_already_defeated) {
        $target_updated = array_merge($target, [
          'hp' => $hp_after,
          'is_defeated' => 1,
        ]);
        
        $event = new EntityDefeatedEvent(
          (int) ($encounter['campaign_id'] ?? 0),
          (int) $encounter_id,
          (int) $target['id'],
          $target_updated,
          (int) ($attacker['id'] ?? 0),
          $damage
        );
        
        $this->eventDispatcher->dispatch($event, EntityDefeatedEvent::NAME);
      }

      $this->encounterStore->logDamage([
        'encounter_id' => $encounter_id,
        'participant_id' => (int) $target['id'],
        'amount' => $damage,
        'damage_type' => $data['action']['damage_type'] ?? NULL,
        'source' => $data['attackerId'] ?? NULL,
        'hp_before' => $hp_before,
        'hp_after' => $hp_after,
      ]);
    }

    $this->encounterStore->updateParticipant((int) $attacker['id'], [
      'actions_remaining' => max(0, $attacker_actions_remaining - 1),
      'attacks_this_turn' => (int) ($attacker['attacks_this_turn'] ?? 0) + 1,
    ]);

    $this->encounterStore->logAction([
      'encounter_id' => (int) $encounter_id,
      'participant_id' => (int) $attacker['id'],
      'action_type' => 'strike',
      'target_id' => $target['id'] ?? NULL,
      'payload' => json_encode([
        'attacker' => $attacker_ref,
        'target' => $target_ref,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'result' => json_encode([
        'hit' => !empty($target),
        'damage' => $damage,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $encounter = $this->loadEncounter($encounter_id);

    $response = $this->buildEncounterResponse($encounter);
    $response['success'] = TRUE;
    $response['action_result'] = [
      'type' => 'strike',
      'attacker_id' => $attacker_ref,
      'target_id' => $target_ref,
      'hit' => !empty($target),
      'damage' => $damage,
    ];

    return new JsonResponse($response);
  }

  /**
   * Execute non-attack combat actions (interact/talk).
   */
  public function action(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];

    $encounter_id = $data['encounterId'] ?? NULL;
    $actor_ref = $data['actorId'] ?? NULL;
    $action_type = (string) ($data['actionType'] ?? '');

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }
    if ($action_type === '') {
      return new JsonResponse(['error' => 'actionType is required'], 400);
    }

    $allowed_types = ['interact', 'talk'];
    if (!in_array($action_type, $allowed_types, TRUE)) {
      return new JsonResponse([
        'error' => 'Unsupported actionType',
        'supported' => $allowed_types,
      ], 400);
    }

    $encounter = $this->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    if (($encounter['status'] ?? '') !== 'active') {
      return new JsonResponse(['error' => 'Encounter is not active'], 409);
    }

    $actor = $this->findParticipantByReference($encounter['participants'] ?? [], $actor_ref);
    if (!$actor) {
      return new JsonResponse(['error' => 'Actor not found in encounter'], 400);
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $active_participant = $encounter['participants'][$turn_index] ?? NULL;
    if (!$active_participant || (int) ($active_participant['id'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
      return new JsonResponse(['error' => 'Actor is not the active turn participant'], 409);
    }

    // Server-authoritative action costs.
    $cost_by_type = [
      'interact' => 1,
      'talk' => 0,
    ];
    $cost = $cost_by_type[$action_type] ?? 1;

    if ($action_type === 'interact') {
      $target_hex = $data['targetHex'] ?? NULL;
      if (!is_array($target_hex) || !isset($target_hex['q']) || !isset($target_hex['r'])) {
        return new JsonResponse(['error' => 'targetHex {q,r} is required for interact'], 400);
      }
    }

    if ($action_type === 'talk') {
      $message = trim((string) ($data['message'] ?? ''));
      if ($message === '') {
        return new JsonResponse(['error' => 'message is required for talk'], 400);
      }
    }

    $actions_remaining = (int) ($actor['actions_remaining'] ?? 0);
    if ($cost > 0 && $actions_remaining < $cost) {
      return new JsonResponse(['error' => 'Not enough actions remaining'], 409);
    }

    if ($cost > 0) {
      $this->encounterStore->updateParticipant((int) $actor['id'], [
        'actions_remaining' => max(0, $actions_remaining - $cost),
      ]);
    }

    $world_delta = NULL;
    if ($action_type === 'interact') {
      $world_delta = $this->applyInteractionWorldMutation($encounter, $data, $data['mapId'] ?? NULL);
    }

    $this->encounterStore->logAction([
      'encounter_id' => (int) $encounter_id,
      'participant_id' => (int) $actor['id'],
      'action_type' => $action_type,
      'target_id' => NULL,
      'payload' => json_encode([
        'actor' => $actor_ref,
        'target' => $data['targetId'] ?? NULL,
        'interaction_type' => $data['interactionType'] ?? NULL,
        'target_hex' => $data['targetHex'] ?? NULL,
        'destination_hex' => $data['destinationHex'] ?? NULL,
        'message' => $data['message'] ?? NULL,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'result' => json_encode([
        'accepted' => TRUE,
        'cost' => $cost,
        'world_delta' => $world_delta,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $updated = $this->loadEncounter((int) $encounter_id);
    $response = $this->buildEncounterResponse($updated);
    $response['success'] = TRUE;
    $response['action_result'] = [
      'type' => $action_type,
      'actor_id' => $actor_ref,
      'cost' => $cost,
      'interaction_type' => $data['interactionType'] ?? NULL,
    ];
    if ($world_delta !== NULL) {
      $response['world_delta'] = $world_delta;
    }

    return new JsonResponse($response);
  }

  /**
   * Apply interaction world mutation and persist to campaign dungeon data.
   *
   * Uses deterministic composite key (campaign_id, map_id) to identify the correct
   * dungeon row, ensuring correctness in multi-dungeon campaign scenarios.
   *
   * @param array $encounter
   * @param array $data
   * @param string|null $map_id
   *
   * @return array|null
   */
  protected function applyInteractionWorldMutation(array $encounter, array $data, ?string $map_id = NULL): ?array {
    $campaign_id = isset($encounter['campaign_id']) ? (int) $encounter['campaign_id'] : 0;
    if ($campaign_id <= 0) {
      return NULL;
    }

    $target_hex = $data['targetHex'] ?? NULL;
    if (!is_array($target_hex) || !isset($target_hex['q']) || !isset($target_hex['r'])) {
      return NULL;
    }

    $interaction_type = (string) ($data['interactionType'] ?? '');
    $target_q = (int) $target_hex['q'];
    $target_r = (int) $target_hex['r'];
    $room_id = (string) ($encounter['room_id'] ?? '');

    // Build composite key query: (campaign_id, map_id) lookup.
    $query = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id);

    // Use map_id for deterministic lookup if provided.
    if (!empty($map_id)) {
      $query->condition('dungeon_id', $map_id);
    } else {
      // Fallback to most-recent if map_id not supplied (operational exception).
      $query->orderBy('updated', 'DESC')->orderBy('id', 'DESC')->range(0, 1);
    }

    $row = $query->execute()->fetchAssoc();

    if (!$row || empty($row['dungeon_data'])) {
      return NULL;
    }

    $payload = json_decode((string) $row['dungeon_data'], TRUE);
    if (!is_array($payload)) {
      return NULL;
    }

    $delta = NULL;
    if ($interaction_type === 'open_passage') {
      $delta = $this->openPassageInPayload($payload, $room_id, $target_q, $target_r);
    }
    elseif ($interaction_type === 'open_door') {
      $delta = $this->openDoorInPayload($payload, $room_id, $target_q, $target_r);
    }
    elseif ($interaction_type === 'move_object') {
      $destination_hex = $data['destinationHex'] ?? NULL;
      if (is_array($destination_hex) && isset($destination_hex['q']) && isset($destination_hex['r'])) {
        $delta = $this->moveObstacleInPayload(
          $payload,
          $room_id,
          $target_q,
          $target_r,
          (int) $destination_hex['q'],
          (int) $destination_hex['r']
        );
      }
    }

    if ($delta === NULL) {
      return NULL;
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated' => time(),
      ])
      ->condition('id', (int) $row['id'])
      ->execute();

    return $delta;
  }

  /**
   * Open a blocked connection in payload and return world delta.
   */
  protected function openPassageInPayload(array &$payload, string $room_id, int $q, int $r): ?array {
    if (!isset($payload['hex_map']['connections']) || !is_array($payload['hex_map']['connections'])) {
      return NULL;
    }

    foreach ($payload['hex_map']['connections'] as &$connection) {
      $from_match = (($connection['from_room'] ?? '') === $room_id)
        && ((int) ($connection['from_hex']['q'] ?? 0) === $q)
        && ((int) ($connection['from_hex']['r'] ?? 0) === $r);
      $to_match = (($connection['to_room'] ?? '') === $room_id)
        && ((int) ($connection['to_hex']['q'] ?? 0) === $q)
        && ((int) ($connection['to_hex']['r'] ?? 0) === $r);

      if (!$from_match && !$to_match) {
        continue;
      }

      $connection['is_passable'] = TRUE;
      $connection['is_discovered'] = TRUE;

      return [
        'type' => 'open_passage',
        'room_id' => $room_id,
        'target_hex' => ['q' => $q, 'r' => $r],
        'connection_id' => $connection['connection_id'] ?? NULL,
      ];
    }

    return NULL;
  }

  /**
   * Mark a door-like obstacle passable in payload and return world delta.
   */
  protected function openDoorInPayload(array &$payload, string $room_id, int $q, int $r): ?array {
    if (!isset($payload['entities']) || !is_array($payload['entities'])) {
      return NULL;
    }

    foreach ($payload['entities'] as &$entity) {
      if (($entity['entity_type'] ?? '') !== 'obstacle') {
        continue;
      }

      $placement = $entity['placement'] ?? [];
      if (($placement['room_id'] ?? '') !== $room_id) {
        continue;
      }

      if ((int) ($placement['hex']['q'] ?? 0) !== $q || (int) ($placement['hex']['r'] ?? 0) !== $r) {
        continue;
      }

      $entity['state'] = is_array($entity['state'] ?? NULL) ? $entity['state'] : [];
      $entity['state']['metadata'] = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
      $entity['state']['metadata']['passable'] = TRUE;

      return [
        'type' => 'open_door',
        'room_id' => $room_id,
        'target_hex' => ['q' => $q, 'r' => $r],
        'target_id' => $entity['instance_id'] ?? ($entity['entity_ref']['content_id'] ?? NULL),
      ];
    }

    return NULL;
  }

  /**
   * Move an obstacle in payload and return world delta.
   */
  protected function moveObstacleInPayload(array &$payload, string $room_id, int $from_q, int $from_r, int $to_q, int $to_r): ?array {
    if (!isset($payload['entities']) || !is_array($payload['entities'])) {
      return NULL;
    }

    foreach ($payload['entities'] as &$entity) {
      if (($entity['entity_type'] ?? '') !== 'obstacle') {
        continue;
      }

      $placement = $entity['placement'] ?? [];
      if (($placement['room_id'] ?? '') !== $room_id) {
        continue;
      }

      if ((int) ($placement['hex']['q'] ?? 0) !== $from_q || (int) ($placement['hex']['r'] ?? 0) !== $from_r) {
        continue;
      }

      $entity['placement']['hex']['q'] = $to_q;
      $entity['placement']['hex']['r'] = $to_r;

      return [
        'type' => 'move_object',
        'room_id' => $room_id,
        'target_hex' => ['q' => $from_q, 'r' => $from_r],
        'destination_hex' => ['q' => $to_q, 'r' => $to_r],
        'target_id' => $entity['instance_id'] ?? ($entity['entity_ref']['content_id'] ?? NULL),
      ];
    }

    return NULL;
  }

  /**
   * Generate a simple encounter ID.
   */
  protected function generateEncounterId(): int {
    return (int) round(microtime(TRUE) * 1000);
  }

  /**
   * Normalize participant payloads.
   */
  protected function normalizeParticipants(array $entities): array {
    $participants = [];

    foreach ($entities as $index => $entity) {
      $entity_id = $entity['entityId'] ?? $entity['id'] ?? $index + 1;
      $name = $entity['name'] ?? "Entity {$entity_id}";

      // Initiative: use provided value, otherwise roll d20 + perception + initiative_bonus.
      $initiative = $entity['initiative'] ?? NULL;
      $initiative_roll = NULL;
      if ($initiative === NULL) {
        $roll = $this->numberGeneration->rollPathfinderDie(20);
        $bonus = (int) ($entity['perception'] ?? 0) + (int) ($entity['initiative_bonus'] ?? 0);
        $initiative = $roll + $bonus;
        $initiative_roll = $roll;
      }

      $hp = isset($entity['hp']) ? (int) $entity['hp'] : NULL;
      $max_hp = isset($entity['max_hp']) ? (int) $entity['max_hp'] : NULL;

      $participants[] = [
        'entity_id' => isset($entity['characterId']) ? (int) $entity['characterId'] : $entity_id,
        'entity_ref' => $entity['entityRef'] ?? $entity['instanceId'] ?? ($entity['entity_ref'] ?? NULL),
        'name' => $name,
        'team' => $entity['team'] ?? NULL,
        'initiative' => $initiative,
        'initiative_roll' => $initiative_roll,
        'ac' => isset($entity['ac']) ? (int) $entity['ac'] : NULL,
        'hp' => $hp,
        'max_hp' => $max_hp,
        'actions_remaining' => isset($entity['actions_remaining']) ? (int) $entity['actions_remaining'] : 3,
        'position_q' => isset($entity['position_q']) ? (int) $entity['position_q'] : (isset($entity['position']['q']) ? (int) $entity['position']['q'] : NULL),
        'position_r' => isset($entity['position_r']) ? (int) $entity['position_r'] : (isset($entity['position']['r']) ? (int) $entity['position']['r'] : NULL),
        'is_defeated' => (bool) ($entity['is_defeated'] ?? FALSE),
      ];
    }

    return $participants;
  }

  /**
   * Build response DTO for frontend consumption.
   */
  protected function buildEncounterResponse(array $encounter): array {
    $participants = $encounter['participants'] ?? [];
    $turn_index = $encounter['turn_index'] ?? 0;
    $encounter_id = (int) ($encounter['id'] ?? $encounter['encounter_id'] ?? 0);

    $normalized_participants = [];
    $initiative_order = [];
    foreach ($participants as $idx => $participant) {
      $entity_id = $participant['entity_ref'] ?? ($participant['entity_id'] ?? $participant['id']);
      $is_defeated = (bool) ($participant['is_defeated'] ?? FALSE);

      $normalized = $participant;
      $normalized['entity_id'] = $entity_id;
      $normalized['is_defeated'] = $is_defeated;
      $normalized_participants[] = $normalized;

      $initiative_order[] = [
        'entity_id' => $entity_id,
        'name' => $participant['name'],
        'initiative' => $participant['initiative'],
        'is_current' => $idx === $turn_index,
        'is_defeated' => $is_defeated,
      ];
    }

    $current_participant = $normalized_participants[$turn_index] ?? NULL;
    $latest_ai_turn_plan = $encounter_id > 0 ? $this->loadLatestAiTurnPlan($encounter_id) : NULL;

    return [
      'encounter_id' => $encounter_id,
      'campaign_id' => $encounter['campaign_id'],
      'room_id' => $encounter['room_id'],
      'map_id' => $encounter['map_id'] ?? NULL,
      'status' => $encounter['status'],
      'current_round' => $encounter['current_round'],
      'turn_index' => $turn_index,
      'version' => (int) ($encounter['updated'] ?? 0),
      'initiative_order' => $initiative_order,
      'participants' => $normalized_participants,
      'current_participant' => $current_participant,
      'latest_ai_turn_plan' => $latest_ai_turn_plan,
    ];
  }

  /**
   * Load most recent ai_turn_plan timeline event for an encounter.
   */
  protected function loadLatestAiTurnPlan(int $encounter_id): ?array {
    $row = $this->database->select('combat_actions', 'a')
      ->fields('a', ['id', 'participant_id', 'payload', 'result', 'created'])
      ->condition('encounter_id', $encounter_id)
      ->condition('action_type', 'ai_turn_plan')
      ->orderBy('created', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $payload = json_decode((string) ($row['payload'] ?? ''), TRUE);
    $result = json_decode((string) ($row['result'] ?? ''), TRUE);

    return [
      'action_id' => (int) $row['id'],
      'participant_id' => (int) ($row['participant_id'] ?? 0),
      'created' => (int) ($row['created'] ?? 0),
      'payload' => is_array($payload) ? $payload : [],
      'result' => is_array($result) ? $result : [],
    ];
  }

  /**
   * Persist encounter state.
   */
  protected function saveEncounter(array $encounter): void {
    $this->store->set('encounter.' . $encounter['encounter_id'], $encounter);
  }

  /**
   * Load encounter state.
   */
  protected function loadEncounter(int $encounter_id): ?array {
    return $this->encounterStore->loadEncounter($encounter_id);
  }

  /**
   * Run a minimal server-side NPC loop: each non-player gets one swing at the first alive player.
   * Advances turn index until we hit a player or exhaust participants.
   */
  protected function autoPlayNonPlayerTurns(?array $encounter): ?array {
    if (!$encounter) {
      return NULL;
    }

    $limit = max(1, count($encounter['participants'] ?? []));
    for ($i = 0; $i < $limit; $i++) {
      $participants = $encounter['participants'] ?? [];
      $turn_index = (int) ($encounter['turn_index'] ?? 0);
      $current = $participants[$turn_index] ?? NULL;

      if (!$current || ($current['team'] ?? 'player') === 'player' || !empty($current['is_defeated'])) {
        break;
      }

      $this->runNpcTurnAction($encounter, $current, $participants);
      $encounter = $this->loadEncounter((int) $encounter['id']);

      // Advance turn index and round.
      $next_index = $this->findNextTurnIndex($encounter['participants'] ?? [], (int) $encounter['turn_index']);
      $fields = ['turn_index' => $next_index];
      if ($next_index <= $encounter['turn_index']) {
        $fields['current_round'] = (int) $encounter['current_round'] + 1;
        $encounter['current_round'] = $fields['current_round'];
      }
      $encounter['turn_index'] = $next_index;
      $this->encounterStore->updateEncounter((int) $encounter['id'], $fields);
      $encounter = $this->loadEncounter((int) $encounter['id']);
    }

    return $encounter;
  }

  /**
   * Find first alive player participant.
   */
  protected function findFirstAlivePlayerIndex(array $participants): ?int {
    foreach ($participants as $idx => $participant) {
      if (($participant['team'] ?? NULL) === 'player' && empty($participant['is_defeated'])) {
        return $idx;
      }
    }
    return NULL;
  }

  /**
   * Run current NPC turn action using AI recommendation when enabled.
   *
   * Falls back to deterministic first-alive-player strike if AI is disabled,
   * invalid, or unavailable.
   */
  protected function runNpcTurnAction(array $encounter, array $current, array $participants): void {
    $action_type = 'strike';
    $target_idx = $this->findFirstAlivePlayerIndex($participants);
    $ai_context = NULL;
    $ai_response = NULL;
    $action_parameters = [];

    try {
      $campaign_id = isset($encounter['campaign_id']) && $encounter['campaign_id'] !== NULL
        ? (int) $encounter['campaign_id']
        : 0;
      $encounter_id = isset($encounter['id']) ? (int) $encounter['id'] : (int) ($encounter['encounter_id'] ?? 0);

      $context = $this->buildActorTurnAiContext($encounter, $current, $participants, $campaign_id, $encounter_id);
      $ai_context = $context;

      if ($this->isEncounterAiNpcAutoplayEnabled()) {
        $ai_response = $this->encounterAiIntegration->requestNpcActionRecommendation($context);
        $validation = $ai_response['validation'] ?? [];

        if (!empty($validation['valid'])) {
          $recommendation = is_array($ai_response['recommendation'] ?? NULL) ? $ai_response['recommendation'] : [];
          $recommended_action = is_array($recommendation['recommended_action'] ?? NULL) ? $recommendation['recommended_action'] : [];
          $action_type = (string) ($recommended_action['type'] ?? 'strike');
          $action_parameters = is_array($recommended_action['parameters'] ?? NULL) ? $recommended_action['parameters'] : [];

          $target_ref = (string) ($recommended_action['target_instance_id'] ?? '');
          if ($target_ref !== '') {
            $target_idx = $this->findParticipantIndexByReference($participants, $target_ref);
          }

          if ($target_idx === NULL && $action_type !== 'end_turn') {
            $target_idx = $this->findFirstAlivePlayerIndex($participants);
          }
        }
      }
    }
    catch (\Throwable $exception) {
      $this->logger('dungeoncrawler_content')->warning('Encounter AI autoplay fallback: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }

    if (is_array($ai_response) && isset($current['id'])) {
      $this->persistAiTurnPlanEvent($encounter, (int) $current['id'], $ai_context ?? [], $ai_response);
    }

    if ($this->isEncounterAiNarrationEnabled() && is_array($ai_context) && isset($current['id'])) {
      $this->persistEncounterNarrationEvent($encounter, (int) $current['id'], $ai_context);
    }

    if ($action_type === 'talk') {
      $this->runNpcTalkAction($encounter, $current, $participants, $target_idx, $action_parameters);
      return;
    }

    if ($action_type !== 'strike' || $target_idx === NULL) {
      return;
    }

    $target = $participants[$target_idx];
    $damage = $this->numberGeneration->rollRange(1, 6);
    $hp_before = $target['hp'] ?? NULL;
    $hp_after = $hp_before !== NULL ? max(0, $hp_before - $damage) : NULL;

    $this->encounterStore->updateParticipant((int) $target['id'], [
      'hp' => $hp_after,
      'is_defeated' => ($hp_after !== NULL && $hp_after <= 0) ? 1 : 0,
    ]);

    $this->encounterStore->logDamage([
      'encounter_id' => $encounter['id'],
      'participant_id' => (int) $target['id'],
      'amount' => $damage,
      'damage_type' => 'bludgeoning',
      'source' => $current['entity_ref'] ?? $current['entity_id'] ?? NULL,
      'hp_before' => $hp_before,
      'hp_after' => $hp_after,
    ]);
  }

  /**
   * Build enriched AI context for non-player turn planning.
   */
  protected function buildActorTurnAiContext(array $encounter, array $current, array $participants, int $campaign_id, int $encounter_id): array {
    $context = $this->encounterAiIntegration->buildEncounterContext($campaign_id, $encounter_id, $encounter);
    $room_entities = $this->loadEncounterRoomEntities($encounter);
    $actor_profile = $this->buildActorProfile($encounter, $current, $room_entities);
    $visibility = $this->buildVisibleReferences($current, $participants, $room_entities, $actor_profile);

    $context['turn_phase'] = 'start_of_turn';
    $context['current_actor_profile'] = $actor_profile;
    $context['visible_references'] = $visibility['references'];
    $context['line_of_sight'] = $visibility['line_of_sight'];
    $context['conversation_options'] = $this->buildConversationOptions($current, $actor_profile, $visibility['references']);

    return $context;
  }

  /**
   * Build actor profile with full state payload when available.
   */
  protected function buildActorProfile(array $encounter, array $current, array $room_entities): array {
    $room_entity = $this->findRoomEntityForParticipant($current, $room_entities);
    $character_state = NULL;
    $character_id = isset($current['entity_id']) ? (int) $current['entity_id'] : 0;
    $campaign_id = isset($encounter['campaign_id']) ? (int) $encounter['campaign_id'] : 0;
    $instance_id = !empty($current['entity_ref']) ? (string) $current['entity_ref'] : NULL;

    if ($character_id > 0 && $campaign_id > 0) {
      try {
        $character_state = $this->characterStateService->getState((string) $character_id, $campaign_id, $instance_id);
      }
      catch (\Throwable $exception) {
        $character_state = NULL;
      }
    }

    $skills = $this->extractSkills($character_state, $room_entity);
    $motivations = $this->extractMotivations($character_state, $room_entity);
    $intelligence = $this->extractIntelligence($character_state, $room_entity);

    return [
      'entity_ref' => (string) ($current['entity_ref'] ?? ''),
      'entity_id' => (int) ($current['entity_id'] ?? 0),
      'name' => (string) ($current['name'] ?? 'Unknown'),
      'team' => (string) ($current['team'] ?? 'neutral'),
      'combat_snapshot' => $current,
      'character_state' => $character_state,
      'state_payload' => is_array($room_entity['state'] ?? NULL) ? $room_entity['state'] : [],
      'skills' => $skills,
      'motivations' => $motivations,
      'intelligence' => $intelligence,
    ];
  }

  /**
   * Build a visibility/line-of-sight envelope for AI turn planning.
   */
  protected function buildVisibleReferences(array $current, array $participants, array $room_entities, array $actor_profile): array {
    $position_map = $this->buildParticipantPositionMap($participants, $room_entities);
    $current_ref = (string) ($current['entity_ref'] ?? $current['entity_id'] ?? '');
    $origin = $position_map[$current_ref] ?? NULL;

    $intelligence = (int) ($actor_profile['intelligence'] ?? 10);
    $base_radius = max(4, min(12, 6 + intdiv(max(0, $intelligence - 10), 2)));
    $references = [];

    foreach ($participants as $participant) {
      if (!empty($participant['is_defeated'])) {
        continue;
      }

      $ref = (string) ($participant['entity_ref'] ?? $participant['entity_id'] ?? '');
      if ($ref === '' || $ref === $current_ref) {
        continue;
      }

      $target_pos = $position_map[$ref] ?? NULL;
      $distance = NULL;
      if (is_array($origin) && is_array($target_pos)) {
        $distance = $this->hexDistance((int) $origin['q'], (int) $origin['r'], (int) $target_pos['q'], (int) $target_pos['r']);
      }

      $line_of_sight = $distance === NULL ? TRUE : $distance <= $base_radius;
      if (!$line_of_sight) {
        continue;
      }

      $references[] = [
        'entity_ref' => $ref,
        'name' => (string) ($participant['name'] ?? 'Unknown'),
        'team' => (string) ($participant['team'] ?? 'neutral'),
        'distance' => $distance,
        'line_of_sight' => TRUE,
      ];
    }

    return [
      'references' => $references,
      'line_of_sight' => [
        'algorithm' => 'hex_radius',
        'radius' => $base_radius,
      ],
    ];
  }

  /**
   * Build conversation hint payload for AI action planning.
   */
  protected function buildConversationOptions(array $current, array $actor_profile, array $visible_references): array {
    $intelligence = (int) ($actor_profile['intelligence'] ?? 10);
    $skills = is_array($actor_profile['skills'] ?? NULL) ? $actor_profile['skills'] : [];
    $motivations = is_array($actor_profile['motivations'] ?? NULL) ? $actor_profile['motivations'] : [];
    $can_talk = !empty($visible_references) && $intelligence >= 6;

    return [
      'can_talk' => $can_talk,
      'preferred_tone' => !empty($motivations) ? 'goal_driven' : 'tactical',
      'skills' => $skills,
      'motivations' => $motivations,
      'default_message' => sprintf('%s calls out a tactical warning.', (string) ($current['name'] ?? 'The combatant')),
    ];
  }

  /**
   * Execute a non-damaging NPC talk action.
   */
  protected function runNpcTalkAction(array $encounter, array $current, array $participants, ?int $target_idx, array $parameters): void {
    $target = $target_idx !== NULL ? ($participants[$target_idx] ?? NULL) : NULL;
    $message = trim((string) ($parameters['message'] ?? $parameters['utterance'] ?? ''));
    if ($message === '') {
      $message = sprintf('%s barks an order across the battlefield.', (string) ($current['name'] ?? 'The combatant'));
    }

    $this->encounterStore->logAction([
      'encounter_id' => (int) ($encounter['id'] ?? $encounter['encounter_id']),
      'participant_id' => (int) $current['id'],
      'action_type' => 'talk',
      'target_id' => $target['id'] ?? NULL,
      'payload' => json_encode([
        'actor' => $current['entity_ref'] ?? $current['entity_id'] ?? NULL,
        'target' => $target['entity_ref'] ?? $target['entity_id'] ?? NULL,
        'message' => $message,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'result' => json_encode([
        'accepted' => TRUE,
        'delivered' => TRUE,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
  }

  /**
   * Persist AI-generated turn plan to encounter timeline.
   */
  protected function persistAiTurnPlanEvent(array $encounter, int $participant_id, array $context, array $ai_response): void {
    try {
      $this->encounterStore->logAction([
        'encounter_id' => (int) ($encounter['id'] ?? $encounter['encounter_id']),
        'participant_id' => $participant_id,
        'action_type' => 'ai_turn_plan',
        'target_id' => NULL,
        'payload' => json_encode([
          'visible_references' => $context['visible_references'] ?? [],
          'line_of_sight' => $context['line_of_sight'] ?? [],
          'conversation_options' => $context['conversation_options'] ?? [],
          'actor_skills' => $context['current_actor_profile']['skills'] ?? [],
          'actor_motivations' => $context['current_actor_profile']['motivations'] ?? [],
          'actor_intelligence' => $context['current_actor_profile']['intelligence'] ?? NULL,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'result' => json_encode([
          'provider' => $ai_response['provider'] ?? 'unknown',
          'validation' => $ai_response['validation'] ?? [],
          'recommendation' => $ai_response['recommendation'] ?? [],
          'requested_at' => $ai_response['requested_at'] ?? time(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      ]);
    }
    catch (\Throwable $exception) {
      $this->logger('dungeoncrawler_content')->warning('Failed to persist ai_turn_plan event: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * Load room entities for this encounter from campaign dungeon payload.
   */
  protected function loadEncounterRoomEntities(array $encounter): array {
    $campaign_id = isset($encounter['campaign_id']) ? (int) $encounter['campaign_id'] : 0;
    if ($campaign_id <= 0) {
      return [];
    }

    $query = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id);

    if (!empty($encounter['map_id'])) {
      $query->condition('dungeon_id', (string) $encounter['map_id']);
    }
    else {
      $query->orderBy('updated', 'DESC')->orderBy('id', 'DESC')->range(0, 1);
    }

    $row = $query->execute()->fetchAssoc();
    if (!$row || empty($row['dungeon_data'])) {
      return [];
    }

    $payload = json_decode((string) $row['dungeon_data'], TRUE);
    if (!is_array($payload)) {
      return [];
    }

    $entities = $payload['entities'] ?? [];
    return is_array($entities) ? $entities : [];
  }

  /**
   * Find corresponding room entity for encounter participant.
   */
  protected function findRoomEntityForParticipant(array $participant, array $room_entities): ?array {
    $ref = (string) ($participant['entity_ref'] ?? '');
    $name = (string) ($participant['name'] ?? '');

    foreach ($room_entities as $entity) {
      $instance_id = (string) ($entity['instance_id'] ?? '');
      $content_id = (string) ($entity['entity_ref']['content_id'] ?? '');
      $entity_name = (string) ($entity['state']['metadata']['display_name'] ?? $entity['state']['metadata']['name'] ?? '');

      if ($ref !== '' && ($instance_id === $ref || $content_id === $ref)) {
        return is_array($entity) ? $entity : NULL;
      }

      if ($name !== '' && $entity_name !== '' && $entity_name === $name) {
        return is_array($entity) ? $entity : NULL;
      }
    }

    return NULL;
  }

  /**
   * Build participant position map for LOS calculations.
   */
  protected function buildParticipantPositionMap(array $participants, array $room_entities): array {
    $map = [];

    foreach ($participants as $participant) {
      $ref = (string) ($participant['entity_ref'] ?? $participant['entity_id'] ?? '');
      if ($ref === '') {
        continue;
      }

      if (isset($participant['position_q'], $participant['position_r'])) {
        $map[$ref] = [
          'q' => (int) $participant['position_q'],
          'r' => (int) $participant['position_r'],
        ];
        continue;
      }

      $room_entity = $this->findRoomEntityForParticipant($participant, $room_entities);
      if (is_array($room_entity) && isset($room_entity['placement']['hex']['q'], $room_entity['placement']['hex']['r'])) {
        $map[$ref] = [
          'q' => (int) $room_entity['placement']['hex']['q'],
          'r' => (int) $room_entity['placement']['hex']['r'],
        ];
      }
    }

    return $map;
  }

  /**
   * Calculate hex distance using axial coordinates.
   */
  protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {
    $dq = $q2 - $q1;
    $dr = $r2 - $r1;
    $ds = (-$q2 - $r2) - (-$q1 - $r1);
    return max(abs($dq), abs($dr), abs($ds));
  }

  /**
   * Extract skills from actor state payload(s).
   */
  protected function extractSkills(?array $character_state, ?array $room_entity): array {
    $skills = [];

    if (is_array($character_state['skills'] ?? NULL)) {
      $skills = $character_state['skills'];
    }
    elseif (is_array($character_state['npcDefinition']['skills'] ?? NULL)) {
      $skills = $character_state['npcDefinition']['skills'];
    }

    if (empty($skills) && is_array($room_entity['state']['metadata']['skills'] ?? NULL)) {
      $skills = $room_entity['state']['metadata']['skills'];
    }

    return $skills;
  }

  /**
   * Extract motivations from actor state payload(s).
   */
  protected function extractMotivations(?array $character_state, ?array $room_entity): array {
    $motivations = [];

    if (is_array($character_state['npcDefinition']['motivations'] ?? NULL)) {
      $motivations = $character_state['npcDefinition']['motivations'];
    }
    elseif (!empty($character_state['basicInfo']['personality'])) {
      $motivations[] = (string) $character_state['basicInfo']['personality'];
    }

    if (empty($motivations) && is_array($room_entity['state']['metadata']['motivations'] ?? NULL)) {
      $motivations = $room_entity['state']['metadata']['motivations'];
    }

    return $motivations;
  }

  /**
   * Extract intelligence score from actor state payload(s).
   */
  protected function extractIntelligence(?array $character_state, ?array $room_entity): int {
    $score = 10;

    if (isset($character_state['abilities']['intelligence'])) {
      $score = (int) $character_state['abilities']['intelligence'];
    }
    elseif (isset($character_state['npcDefinition']['abilities']['intelligence'])) {
      $score = (int) $character_state['npcDefinition']['abilities']['intelligence'];
    }
    elseif (isset($character_state['npcDefinition']['intelligence'])) {
      $score = (int) $character_state['npcDefinition']['intelligence'];
    }

    if ($score <= 0 && isset($room_entity['state']['metadata']['intelligence'])) {
      $score = (int) $room_entity['state']['metadata']['intelligence'];
    }

    return $score > 0 ? $score : 10;
  }

  /**
   * Check if encounter AI-driven NPC auto-play is enabled in config.
   */
  protected function isEncounterAiNpcAutoplayEnabled(): bool {
    return (bool) $this->configFactory
      ->get('dungeoncrawler_content.settings')
      ->get('encounter_ai_npc_autoplay_enabled');
  }

  /**
   * Check if encounter narration event persistence is enabled in config.
   */
  protected function isEncounterAiNarrationEnabled(): bool {
    return (bool) $this->configFactory
      ->get('dungeoncrawler_content.settings')
      ->get('encounter_ai_narration_enabled');
  }

  /**
   * Persist AI narration event into encounter action timeline.
   */
  protected function persistEncounterNarrationEvent(array $encounter, int $participant_id, array $context): void {
    try {
      $narration_response = $this->encounterAiIntegration->requestEncounterNarration($context);
      $narration_payload = is_array($narration_response['narration'] ?? NULL)
        ? $narration_response['narration']
        : [];

      if (empty($narration_payload)) {
        return;
      }

      $this->encounterStore->logAction([
        'encounter_id' => (int) ($encounter['id'] ?? $encounter['encounter_id']),
        'participant_id' => $participant_id,
        'action_type' => 'ai_narration',
        'target_id' => NULL,
        'payload' => json_encode($narration_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'result' => json_encode([
          'provider' => $narration_response['provider'] ?? 'unknown',
          'requested_at' => $narration_response['requested_at'] ?? time(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      ]);
    }
    catch (\Throwable $exception) {
      $this->logger('dungeoncrawler_content')->warning('Encounter narration persistence skipped: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * Find participant index by entity reference or entity ID.
   */
  protected function findParticipantIndexByReference(array $participants, string $reference): ?int {
    if ($reference === '') {
      return NULL;
    }

    foreach ($participants as $idx => $participant) {
      $entity_ref = (string) ($participant['entity_ref'] ?? '');
      $entity_id = (string) ($participant['entity_id'] ?? '');
      if ($entity_ref === $reference || $entity_id === $reference) {
        return $idx;
      }
    }

    return NULL;
  }

  /**
   * Find the next non-defeated participant index, wrapping around.
   */
  protected function findNextTurnIndex(array $participants, int $current_index): int {
    $count = count($participants);
    if ($count === 0) {
      return 0;
    }

    for ($offset = 1; $offset <= $count; $offset++) {
      $candidate = ($current_index + $offset) % $count;
      if (empty($participants[$candidate]['is_defeated'])) {
        return $candidate;
      }
    }

    // All defeated; stay at current or zero.
    return max(0, $current_index);
  }

  /**
   * Find participant by entity_ref.
   */
  protected function findParticipantByReference(array $participants, $entity_ref): ?array {
    foreach ($participants as $participant) {
      if ((string) ($participant['entity_ref'] ?? '') === (string) $entity_ref || (string) ($participant['entity_id'] ?? '') === (string) $entity_ref || (string) ($participant['id'] ?? '') === (string) $entity_ref) {
        return $participant;
      }
    }
    return NULL;
  }

}
