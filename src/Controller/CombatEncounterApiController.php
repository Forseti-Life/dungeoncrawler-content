<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * Legacy mutation error code.
   */
  protected const LEGACY_MUTATION_DISABLED_CODE = 'legacy_combat_mutation_disabled';

  /**
   * Encounter storage service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $encounterStore;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   */
  public function __construct(CombatEncounterStore $encounter_store, Connection $database) {
    $this->encounterStore = $encounter_store;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.combat_encounter_store'),
      $container->get('database')
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

    $active_encounter_ids = $this->loadActiveEncounterIdsForContext($campaign_id, $room_id);
    if ($active_encounter_ids === []) {
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

    $encounter_id = (int) $active_encounter_ids[0];

    $encounter = $this->normalizeEncounterForResponse($this->loadEncounter((int) $encounter_id));
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
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Advance turn for the active encounter.
   */
  public function endTurn(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * End an encounter.
   */
  public function end(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
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

    $encounter = $this->normalizeEncounterForResponse($this->loadEncounter((int) $encounter_id));
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    return new JsonResponse($this->buildEncounterResponse($encounter));
  }

  /**
   * Replace encounter state (turn index/status/participants) with optimistic lock.
   */
  public function set(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute a basic attack (stub).
   */
  public function attack(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute non-attack combat actions (interact/talk).
   */
  public function action(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Return standardized response for disabled legacy mutation endpoints.
   */
  protected function legacyMutationDisabledResponse(string $canonical_path): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error_code' => self::LEGACY_MUTATION_DISABLED_CODE,
      'error' => sprintf('Legacy combat mutation endpoints are disabled. Use %s as the single canonical turn/round authority.', $canonical_path),
      'canonical_endpoint' => $canonical_path,
    ], 409);
  }

  /**
   * Build response DTO for frontend consumption.
   */
  protected function buildEncounterResponse(array $encounter): array {
    $participants = $encounter['participants'] ?? [];
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
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
      'current_round' => (int) ($encounter['current_round'] ?? 0),
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
   * Load encounter state.
   */
  protected function loadEncounter(int $encounter_id): ?array {
    return $this->encounterStore->loadEncounter($encounter_id);
  }

  /**
   * Load active encounter ids for one campaign/room context, newest first.
   *
   * @return array<int>
   *   Encounter ids.
   */
  protected function loadActiveEncounterIdsForContext(int $campaign_id, string $room_id = ''): array {
    if ($campaign_id <= 0) {
      return [];
    }

    try {
      $query = $this->database->select('combat_encounters', 'e')
        ->fields('e', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('status', 'active');
      if ($room_id !== '') {
        $query->condition('room_id', $room_id);
      }

      $ids = $query
        ->orderBy('updated', 'DESC')
        ->orderBy('id', 'DESC')
        ->execute()
        ->fetchCol();
    }
    catch (\Exception $e) {
      return [];
    }

    return array_values(array_map('intval', is_array($ids) ? $ids : []));
  }

  /**
   * Normalize encounter payloads for read responses without mutating turn state.
   *
   * Round/turn progression is authoritative in the coordinator->encounter-handler
   * action chain. Read endpoints must remain side-effect free.
   */
  protected function normalizeEncounterForResponse(?array $encounter): ?array {
    if (!$encounter) {
      return NULL;
    }

    return $encounter;
  }

}
