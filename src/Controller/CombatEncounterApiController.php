<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\MapGeneratorService;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomStateService;
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
   * Navigation payload builder.
   *
   * @var \Drupal\dungeoncrawler_content\Service\MapGeneratorService|null
   */
  protected $mapGenerator;

  /**
   * Room state service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomStateService|null
   */
  protected $roomStateService;

  /**
   * Navigation capability resolver.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NavigationService|null
   */
  protected $navigationService;

  /**
   * Constructor.
   */
  public function __construct(CombatEncounterStore $encounter_store, ConfigFactoryInterface $config_factory, EncounterAiIntegrationService $encounter_ai_integration, Connection $database, CharacterStateService $character_state_service, NumberGenerationService $number_generation, EventDispatcherInterface $event_dispatcher, ?MapGeneratorService $map_generator = NULL, ?RoomStateService $room_state_service = NULL, ?NavigationService $navigation_service = NULL) {
    $this->encounterStore = $encounter_store;
    $this->configFactory = $config_factory;
    $this->encounterAiIntegration = $encounter_ai_integration;
    $this->database = $database;
    $this->characterStateService = $character_state_service;
    $this->numberGeneration = $number_generation;
    $this->eventDispatcher = $event_dispatcher;
    $this->mapGenerator = $map_generator;
    $this->roomStateService = $room_state_service;
    $this->navigationService = $navigation_service;
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
      $container->get('event_dispatcher'),
      $container->get('dungeoncrawler_content.map_generator'),
      $container->get('dungeoncrawler_content.room_state_service'),
      $container->get('dungeoncrawler_content.navigation_service')
    );
  }

  /**
   * Navigate to a connected room using the formalized navigation contract.
   */
  public function navigate(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $campaign_id = (int) ($data['campaignId'] ?? 0);
    $current_room_id = trim((string) ($data['currentRoomId'] ?? ''));
    $map_id = trim((string) ($data['mapId'] ?? ''));
    $connection_id = trim((string) ($data['connectionId'] ?? ''));
    $target_hex = is_array($data['targetHex'] ?? NULL) ? $data['targetHex'] : NULL;

    if ($campaign_id <= 0 || $current_room_id === '') {
      return new JsonResponse(['error' => 'campaignId and currentRoomId are required'], 400);
    }
    if ($this->mapGenerator === NULL || $this->roomStateService === NULL || $this->navigationService === NULL) {
      return new JsonResponse(['error' => 'Navigation services are unavailable'], 500);
    }

    $record = $this->loadDungeonPayloadRecord($campaign_id, $map_id);
    if ($record === NULL) {
      return new JsonResponse(['error' => 'Dungeon payload not found'], 404);
    }

    $dungeon_data = $record['payload'];
    $capability = $this->navigationService->resolveRequestedCapability($dungeon_data, $current_room_id, $connection_id, $target_hex);
    if ($capability === NULL) {
      return new JsonResponse(['error' => 'No navigation capability matched that request'], 409);
    }
    if (empty($capability['available'])) {
      return new JsonResponse([
        'error' => 'That route is not available right now.',
        'navigation_capability' => $capability,
      ], 409);
    }

    $target_room_id = (string) ($capability['target_room_id'] ?? '');
    $room = $this->navigationService->findRoomById($dungeon_data, $target_room_id);
    if ($target_room_id === '' || $room === NULL) {
      return new JsonResponse(['error' => 'Destination room could not be resolved'], 404);
    }

    $room_state = [
      'roomId' => $target_room_id,
      'dungeonId' => (string) $record['dungeon_id'],
      'explored' => TRUE,
      'visibility' => 'visible',
      'isCleared' => FALSE,
    ];

    try {
      $this->roomStateService->setState($campaign_id, $target_room_id, (string) $record['dungeon_id'], $room_state, NULL);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['error' => 'Failed to persist destination room state: ' . $e->getMessage()], 500);
    }

    $receipt = $this->mapGenerator->buildClientNavigationPayload([
      'destination' => (string) ($room['name'] ?? $target_room_id),
      'destination_description' => (string) ($room['description'] ?? $room['name'] ?? $target_room_id),
      'travel_type' => 'walk',
      'estimated_distance' => 'adjacent',
      'source' => 'room-connection',
      'origin_room_id' => $current_room_id,
      'new_room' => $room,
      'entities' => [],
      'dungeon_data' => $dungeon_data,
    ]);

    return new JsonResponse([
      'success' => TRUE,
      'data' => $receipt,
    ]);
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
    if (count($active_encounter_ids) > 1) {
      $this->retireActiveEncounters($active_encounter_ids, $encounter_id);
    }

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
   * Load one dungeon payload row for navigation/mutation work.
   *
   * @return array<string, mixed>|null
   *   Canonical dungeon payload row or NULL.
   */
  protected function loadDungeonPayloadRecord(int $campaign_id, string $map_id = ''): ?array {
    $query = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id);

    if ($map_id !== '') {
      $query->condition('dungeon_id', $map_id);
    }
    else {
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

    $row['payload'] = $payload;
    return $row;
  }

  /**
   * Normalize participant payloads.
   */
  protected function normalizeParticipants(array $entities): array {
    $participants = [];

    foreach ($entities as $index => $entity) {
      $entity_id = $entity['entityId'] ?? $entity['id'] ?? $index + 1;
      $name = $entity['name'] ?? "Entity {$entity_id}";
      $team = $this->normalizeParticipantTeam($entity['team'] ?? NULL);
      if ($team === NULL) {
        continue;
      }

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
        'team' => $team,
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
   * Normalize a participant team label for server-authoritative combat.
   */
  protected function normalizeParticipantTeam($team): ?string {
    $normalized = strtolower(trim((string) $team));
    if ($normalized === '') {
      return NULL;
    }
    if (in_array($normalized, ['neutral', 'indifferent'], TRUE)) {
      return 'neutral';
    }
    if (in_array($normalized, ['player', 'player_character', 'pc'], TRUE)) {
      return 'player';
    }
    if (in_array($normalized, ['ally', 'friendly', 'companion'], TRUE)) {
      return 'ally';
    }
    if (in_array($normalized, ['enemy', 'hostile', 'monster'], TRUE)) {
      return 'enemy';
    }

    return NULL;
  }

  /**
   * Require at least one player-side participant before creating an encounter.
   */
  protected function hasPlayerParticipant(array $participants): bool {
    $has_player = FALSE;

    foreach ($participants as $participant) {
      $team = $participant['team'] ?? NULL;
      if ($team === 'player') {
        $has_player = TRUE;
      }
    }

    return $has_player;
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
   * Mark stale or invalid encounters ended.
   *
   * @param array<int> $encounter_ids
   *   Encounter ids to retire.
   * @param int|null $preserve_id
   *   Optional encounter id to keep active.
   */
  protected function retireActiveEncounters(array $encounter_ids, ?int $preserve_id = NULL): void {
    foreach ($encounter_ids as $encounter_id) {
      $encounter_id = (int) $encounter_id;
      if ($encounter_id <= 0 || ($preserve_id !== NULL && $encounter_id === $preserve_id)) {
        continue;
      }

      $this->encounterStore->updateEncounter($encounter_id, ['status' => 'ended']);
    }
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
   * Determine whether an encounter still has opposing combat sides.
   */
  protected function evaluateEncounterOutcome(array $participants): array {
    $active_sides = [];
    foreach ($participants as $participant) {
      if (!empty($participant['is_defeated'])) {
        continue;
      }

      $side = $this->normalizeEncounterOutcomeSide((string) ($participant['team'] ?? ''));
      if ($side !== NULL) {
        $active_sides[$side] = TRUE;
      }
    }

    return ['ended' => count($active_sides) <= 1];
  }

  /**
   * Collapse encounter teams to hero/enemy sides for stale-encounter cleanup.
   */
  protected function normalizeEncounterOutcomeSide(string $team): ?string {
    $normalized = strtolower(trim($team));
    return match ($normalized) {
      'player', 'ally', 'friendly', 'party' => 'heroes',
      'enemy', 'hostile', 'monster', 'monsters' => 'enemies',
      'neutral', 'indifferent' => 'social',
      default => NULL,
    };
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
