<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Encounter-authoritative action chain entrypoint.
 *
 * This is the single server authority for action execution. It manages:
 * - Game phase state machine (encounter-only active runtime)
 * - Action validation and routing to the active phase handler
 * - Phase transitions (with onExit/onEnter lifecycle)
 * - Event logging for every action
 * - Dungeon data persistence
 * - State version tracking for optimistic concurrency
 * - Canonical round/turn/current-actor ownership
 *
 * Model note:
 * - All actors participate in the same authoritative turn framework.
 * - The player character is represented in that same actor loop; on the PC turn,
 *   player chat input is routed as canonical actions rather than bypassing state
 *   authority.
 *
 * Design principles:
 * 1. Server-authoritative: the server owns the game phase and all transitions.
 * 2. Phase-driven: delegates to active PhaseHandlers via strategy pattern.
 * 3. Incremental: wraps existing services, does not rewrite them.
 * 4. Event-sourced: every action produces an event in the game log.
 */
class GameCoordinatorService {

  /**
   * Voice used for room-entry narration audio.
   */
  protected const ROOM_ENTRY_NARRATOR_VOICE = 'en-US-Standard-D';
  protected const ROOM_ENTRY_NARRATOR_SPEAKING_RATE = 0.85;
  protected const ROOM_ENTRY_NARRATOR_PITCH = -6.0;
  protected const ROOM_ENTRY_NARRATOR_VOLUME_GAIN_DB = 2.0;
  protected const FULL_RUNTIME_PROJECTION_REASON_ALLOWLIST = [
    'legacy_runtime_hydration_api',
    'compatibility_adapter',
    'runtime_repair',
    'incident_diagnostics',
  ];
  protected const FULL_RUNTIME_PROJECTION_REASON_PREFIX_ALLOWLIST = [
    'debug:',
    'compat:',
    'migration:',
    'incident:',
  ];
  protected const DEFAULT_ACTIVE_PHASE = 'encounter';
  protected const DEPRECATED_PHASES = ['exploration'];
  protected const FORBIDDEN_INTENT_CAPABILITY_KEYS = [
    'cmd',
    'command',
    'shell',
    'script',
    'exec',
    'execute',
    'subprocess',
    'terminal',
    'tool',
    'tool_call',
    'tool_name',
    'bash',
    'sh',
    'powershell',
    'drush',
    'git',
    'curl',
    'wget',
    'system',
  ];
  protected const NAVIGATION_TIMING_SLOW_THRESHOLD_MS = 250;

  /**
   * Default game state structure for new sessions.
   */
  const DEFAULT_GAME_STATE = [
    'phase' => self::DEFAULT_ACTIVE_PHASE,
    'session_id' => NULL,
    'started_at' => NULL,
    'round' => NULL,
    'turn' => NULL,
    'encounter_id' => NULL,
    'initiative_order' => NULL,
    'exploration' => [
      'time_elapsed_minutes' => 0,
      'character_activities' => [],
      'previous_room' => NULL,
    ],
    'timed_activities' => [],
    'state_version' => 1,
    'active_room_id' => NULL,
    'event_log_cursor' => 0,
    'initial_room_entry_room_id' => NULL,
    'initial_room_entry_completed_at' => NULL,
    'last_encounter' => NULL,
  ];

  /**
   * Valid phase transitions.
   *
   * @var array
   */
  const VALID_TRANSITIONS = [
    'encounter' => [],
  ];

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  protected CampaignCharacterRuntimeSyncService $campaignCharacterRuntimeSync;
  protected RuntimeBootstrapService $runtimeBootstrap;
  protected CoordinatorRuntimeReadService $coordinatorRuntimeReadService;
  protected RuntimeStateReadModelAssembler $runtimeStateReadModelAssembler;
  protected DungeonPayloadStatePersistenceService $dungeonPayloadStatePersistence;
  protected RuntimeGraphAssemblerService $runtimeGraphAssembler;
  protected CampaignRuntimeStateStore $campaignRuntimeStateStore;
  protected CampaignRuntimeMutationService $campaignRuntimeMutationService;
  protected ActorRuntimeStateStore $actorRuntimeStateStore;
  protected RoomRuntimeStateStore $roomRuntimeStateStore;
  protected ConnectionRuntimeStateStore $connectionRuntimeStateStore;
  protected ActorRuntimeMutationService $actorRuntimeMutationService;
  protected RoomRuntimeMutationService $roomRuntimeMutationService;
  protected ConnectionRuntimeMutationService $connectionRuntimeMutationService;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\GameEventLogger
   */
  protected GameEventLogger $eventLogger;

  /**
   * Phase handlers keyed by phase name.
   *
   * @var \Drupal\dungeoncrawler_content\Service\PhaseHandlerInterface[]
   */
  protected array $phaseHandlers = [];

  /**
   * AI GM narration service.
   *
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
   * Central campaign time resolver.
   */
  protected CampaignTimeResolverService $campaignTimeResolver;

  /**
   * Optional TTS bridge for room-entry narrator audio.
   */
  protected ?TextToSpeechIntegrationService $textToSpeechIntegration;

  /**
   * File URL generator for narrator audio playback URLs.
   */
  protected ?FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a GameCoordinatorService.
   */
  public function __construct(
    Connection $database,
    CampaignCharacterRuntimeSyncService $campaign_character_runtime_sync,
    LoggerChannelFactoryInterface $logger_factory,
    GameEventLogger $event_logger,
    EncounterPhaseHandler $encounter_handler,
    AiGmService $ai_gm_service,
    CampaignTimeResolverService $campaign_time_resolver,
    RuntimeBootstrapService $runtime_bootstrap,
    CoordinatorRuntimeReadService $coordinator_runtime_read_service,
    RuntimeStateReadModelAssembler $runtime_state_read_model_assembler,
    DungeonPayloadStatePersistenceService $dungeon_payload_state_persistence,
    RuntimeGraphAssemblerService $runtime_graph_assembler,
    CampaignRuntimeStateStore $campaign_runtime_state_store,
    CampaignRuntimeMutationService $campaign_runtime_mutation_service,
    ActorRuntimeStateStore $actor_runtime_state_store,
    RoomRuntimeStateStore $room_runtime_state_store,
    ConnectionRuntimeStateStore $connection_runtime_state_store,
    ActorRuntimeMutationService $actor_runtime_mutation_service,
    RoomRuntimeMutationService $room_runtime_mutation_service,
    ConnectionRuntimeMutationService $connection_runtime_mutation_service,
    ?NarrationEngine $narration_engine = NULL,
    ?TextToSpeechIntegrationService $text_to_speech_integration = NULL,
    ?FileUrlGeneratorInterface $file_url_generator = NULL
  ) {
    $this->database = $database;
    $this->campaignCharacterRuntimeSync = $campaign_character_runtime_sync;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->eventLogger = $event_logger;
    $this->aiGmService = $ai_gm_service;
    $this->campaignTimeResolver = $campaign_time_resolver;
    $this->runtimeBootstrap = $runtime_bootstrap;
    $this->coordinatorRuntimeReadService = $coordinator_runtime_read_service;
    $this->runtimeStateReadModelAssembler = $runtime_state_read_model_assembler;
    $this->dungeonPayloadStatePersistence = $dungeon_payload_state_persistence;
    $this->runtimeGraphAssembler = $runtime_graph_assembler;
    $this->campaignRuntimeStateStore = $campaign_runtime_state_store;
    $this->campaignRuntimeMutationService = $campaign_runtime_mutation_service;
    $this->actorRuntimeStateStore = $actor_runtime_state_store;
    $this->roomRuntimeStateStore = $room_runtime_state_store;
    $this->connectionRuntimeStateStore = $connection_runtime_state_store;
    $this->actorRuntimeMutationService = $actor_runtime_mutation_service;
    $this->roomRuntimeMutationService = $room_runtime_mutation_service;
    $this->connectionRuntimeMutationService = $connection_runtime_mutation_service;
    $this->narrationEngine = $narration_engine;
    $this->textToSpeechIntegration = $text_to_speech_integration;
    $this->fileUrlGenerator = $file_url_generator;

    $this->phaseHandlers['encounter'] = $encounter_handler;
  }

  protected function autoResolveRoomSceneNonPlayerTurns(
    int $campaign_id,
    array &$game_state,
    array &$dungeon_data,
    ?PhaseHandlerInterface $handler
  ): array {
    if (!$handler instanceof EncounterPhaseHandler) {
      return [];
    }
    if (($game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE) !== self::DEFAULT_ACTIVE_PHASE) {
      return [];
    }

    $reseed_events = $handler->ensureRoomScenePlayerParticipant($game_state, $dungeon_data, $campaign_id)['events'] ?? [];
    $advance_events = $handler->advanceNonPlayerTurnsToNextPlayer($game_state, $dungeon_data, $campaign_id)['events'] ?? [];
    $events = array_merge($reseed_events, $advance_events);
    if ($events === []) {
      return [];
    }

    return $this->eventLogger->logEvents($dungeon_data, $events);
  }

  // =========================================================================
  // Public API — these map to controller endpoints.
  // =========================================================================

  /**
   * Process a player action intent.
   *
   * This is the main game loop entry point. All player actions flow through
   * here, regardless of phase.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param array $intent
   *   The action intent with keys:
   *   - type: string (e.g., 'move', 'strike', 'talk')
   *   - actor: string (entity ID)
   *   - target: string|null (target entity ID)
   *   - params: array (action-specific parameters)
   *   - client_state_version: int|null (for optimistic concurrency)
   *
   * @return array
   *   Unified response:
   *   - success: bool
   *   - game_state: array (current game state after action)
   *   - result: array (action-specific result)
   *   - mutations: array (state changes applied)
   *   - events: array (events logged)
   *   - phase_transition: array|null
   *   - available_actions: string[]
   *   - state_version: int
   *   - error: string|null
   */
  public function processAction(int $campaign_id, array $intent): array {
    $runtime_character_id = (int) (
      $intent['params']['character_id']
      ?? $intent['character_id']
      ?? 0
    );
    if ($runtime_character_id > 0) {
      $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $runtime_character_id);
    }
    else {
      $runtime_actor_id = trim((string) ($intent['actor'] ?? ''));
      $resolved_runtime_character_id = $this->runtimeBootstrap->resolveRuntimeCharacterIdForActor($campaign_id, $runtime_actor_id);
      if ($resolved_runtime_character_id !== NULL) {
        $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $resolved_runtime_character_id);
      }
      else {
        $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
      }
    }

    $actor_id = trim((string) ($intent['actor'] ?? ''));
    if ($actor_id === '') {
      $character_hint = (int) (
        $intent['params']['character_id']
        ?? $intent['character_id']
        ?? 0
      );
      if ($character_hint > 0) {
        $resolved_actor_id = $this->resolveActorIdForCharacterId($campaign_id, $character_hint);
        if (is_string($resolved_actor_id) && trim($resolved_actor_id) !== '') {
          $actor_id = trim($resolved_actor_id);
          $intent['actor'] = $actor_id;
        }
      }
    }
    $capability_violation = $this->validateActorCapabilityBoundary($intent);
    if (is_string($capability_violation) && $capability_violation !== '') {
      return $this->errorResponse('actor_capability_violation:' . $capability_violation);
    }

    // 1. Load dungeon data and game state.
    $requested_room_id = $this->resolveActorRoomIdFromRuntimeStore($campaign_id, $actor_id !== '' ? $actor_id : NULL);
    $dungeon_data = $this->loadDungeonData(
      $campaign_id,
      $actor_id !== '' ? $actor_id : NULL,
      TRUE,
      1,
      $requested_room_id
    );
    if (!$dungeon_data) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $game_state = $this->ensureGameState($dungeon_data);
    $phase = $game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE;

    // Bootstrap the initial room-entered encounter framework when needed.
    // Materialized full-state reads do this too; processAction() must also do
    // it so the first action cannot fail with "No active encounter".
    $bootstrap_events = $this->bootstrapInitialRoomEntry($campaign_id, $dungeon_data, $game_state);
    if ($bootstrap_events !== []) {
      $game_state['event_log_cursor'] = max(array_map(
        static fn (array $event): int => (int) ($event['id'] ?? 0),
        $bootstrap_events
      ));
      $this->persistGameStateSlice($campaign_id, $game_state, (string) ($dungeon_data['active_room_id'] ?? ''));
    }

    // 2. Optimistic concurrency check.
    $client_version = $intent['client_state_version'] ?? NULL;
    if ($client_version !== NULL && $client_version !== ($game_state['state_version'] ?? 0)) {
      return $this->errorResponse(
        'State version mismatch. Expected ' . ($game_state['state_version'] ?? 0) . ', got ' . $client_version . '. Refresh state.',
        $game_state
      );
    }

    if ($actor_id === '') {
      $turn_actor_id = trim((string) ($game_state['turn']['entity'] ?? ''));
      if ($turn_actor_id !== '') {
        $actor_id = $turn_actor_id;
        $intent['actor'] = $turn_actor_id;
      }
    }

    // 3. Get the active phase handler.
    $handler = $this->getPhaseHandler($phase);
    if (!$handler) {
      return $this->errorResponse("No handler for phase: $phase", $game_state);
    }

    $autoplay_events = $bootstrap_events === []
      ? $this->autoResolveRoomSceneNonPlayerTurns($campaign_id, $game_state, $dungeon_data, $handler)
      : [];
    if ($autoplay_events !== []) {
      $game_state['event_log_cursor'] = max(array_map(
        static fn (array $event): int => (int) ($event['id'] ?? 0),
        $autoplay_events
      ));
      $this->persistGameStateSlice($campaign_id, $game_state, (string) ($dungeon_data['active_room_id'] ?? ''));
    }
    $pre_action_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);

    // 4. Validate the action.
    $validation = $handler->validateIntent($intent, $game_state, $dungeon_data);
    if (!($validation['valid'] ?? FALSE)) {
      return $this->errorResponse(
        $validation['reason'] ?? 'Action validation failed.',
        $game_state
      );
    }

    // 5. Process the action.
    $this->campaignTimeResolver->beginDeferredTimeEffects($game_state);
    $action_result = $this->normalizeHandlerActionResult(
      $handler->processIntent($intent, $game_state, $dungeon_data, $campaign_id),
      $campaign_id,
      $phase
    );
    $time_effects = array_merge(
      $this->campaignTimeResolver->consumePendingTimeEffects($game_state),
      array_values(array_filter($action_result['time_effects'] ?? [], 'is_array'))
    );

    // Resolve elapsed time before any phase transition mutates the live phase.
    if ($time_effects !== []) {
      $this->campaignTimeResolver->applyTimeEffects($game_state, $time_effects);
    }

    // 6. Log events.
    $events_to_log = $action_result['events'] ?? [];
    $logged_events = array_merge($bootstrap_events ?? [], $autoplay_events ?? []);
    if (!empty($events_to_log)) {
      $logged_events = array_merge(
        $logged_events,
        $this->eventLogger->logEvents($dungeon_data, $events_to_log)
      );
    }
    // 7. Handle phase transitions.
    $phase_transition = $action_result['phase_transition'] ?? NULL;
    $phase_transition_mutation_envelope = NULL;
    if ($phase_transition) {
      $transition_result = $this->executePhaseTransition(
        $phase_transition['from'] ?? $phase,
        $phase_transition['to'],
        $phase_transition,
        $game_state,
        $dungeon_data,
        $campaign_id
      );
      $logged_events = array_merge($logged_events, $transition_result['events'] ?? []);
      $phase_transition_mutation_envelope = is_array($transition_result['mutation_envelope'] ?? NULL)
        ? $transition_result['mutation_envelope']
        : NULL;
    }
    $this->synchronizeActiveRoomAuthority($game_state, $dungeon_data);

    // 8. Increment state version.
    $game_state['state_version'] = ($game_state['state_version'] ?? 0) + 1;

    // 9. Persist state through the minimal required lane.
    $mutation_envelope = $this->resolveMutationEnvelopeForPersistence(
      $campaign_id,
      $phase_transition_mutation_envelope ?? ($action_result['mutation_envelope'] ?? NULL),
      $game_state,
      $dungeon_data,
      $pre_action_slice_fingerprints
    );
    $this->persistStateWithMinimalWrite(
      $campaign_id,
      $dungeon_data,
      $game_state,
      $mutation_envelope
    );
    $this->ensurePersistedRuntimeStateMatches($campaign_id, $game_state, (string) ($dungeon_data['active_room_id'] ?? ''));

    // 10. Build response.
    $current_phase = $game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE;
    $current_handler = $this->getPhaseHandler($current_phase);
    $actor_id = $intent['actor'] ?? NULL;
    $action_contract = $this->buildActionContract($current_handler, $game_state, $dungeon_data, $actor_id);

    // Collect any pending scene beats from NarrationEngine.
    $session_narration = NULL;
    if ($this->narrationEngine) {
      $dungeon_id = $dungeon_data['dungeon_id'] ?? $dungeon_data['id'] ?? 0;
      $room_id = $dungeon_data['active_room_id'] ?? '';
      try {
        $present = NarrationEngine::buildPresentCharacters($dungeon_data, $room_id);
        $flush_result = $this->narrationEngine->flushNarration(
          $campaign_id,
          $dungeon_id,
          $room_id,
          $present
        );
        if (!empty($flush_result)) {
          $session_narration = $flush_result;
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('NarrationEngine flush failed: @err', ['@err' => $e->getMessage()]);
      }
    }

    $success = $action_result['success'] ?? TRUE;
    $result_payload = $action_result['result'] ?? [];
    $result_payload = $this->enrichTransitionResultPayloadIfNeeded(
      $success,
      $intent,
      $result_payload,
      $action_result,
      $dungeon_data
    );
    $error_message = $action_result['error']
      ?? (is_array($result_payload) ? ($result_payload['error'] ?? NULL) : NULL)
      ?? NULL;

    return [
      'success' => $success,
      'game_state' => $this->buildClientGameState($game_state),
      'active_room_id' => $game_state['active_room_id'] ?? NULL,
      'result' => $result_payload,
      'mutations' => $action_result['mutations'] ?? [],
      'events' => $logged_events,
      'phase_transition' => $phase_transition,
      'narration' => $action_result['narration'] ?? NULL,
      'session_narration' => $session_narration,
      'available_actions' => $current_handler
        ? $current_handler->getAvailableActions($game_state, $dungeon_data, $actor_id)
        : [],
      'action_contract' => $action_contract,
      'state_version' => $game_state['state_version'],
      'time_effects' => $time_effects,
      'runtime_snapshot' => $this->buildRuntimeSnapshotPayload($game_state, $dungeon_data, $actor_id),
      'mutation_envelope' => $mutation_envelope,
      'error' => $success ? NULL : (is_string($error_message) ? trim($error_message) : NULL),
    ];
  }

  /**
   * Return lightweight encounter progress state for read-mostly callers.
   *
   * This avoids runtime-graph assembly and only loads the mutable runtime slice.
     */
  public function getEncounterProgressState(int $campaign_id): array {
    if ($campaign_id <= 0) {
      return [];
    }

    $game_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id);
    return is_array($game_state) ? $game_state : [];
  }

  /**
   * Get the full game state for client sync.
   *
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return array
   *   Full game state payload for the client.
   */
  public function getFullState(int $campaign_id): array {
    $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    $context = $this->coordinatorRuntimeReadService->resolveFullStateReadContext($campaign_id);
    if ($context === NULL) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $dungeon_data = $context['dungeon_data'];
    $game_state = $context['game_state'];
    return $this->buildFullStateResponse($campaign_id, $dungeon_data, $game_state, FALSE);
  }

  /**
   * Get a materialized full game state for runtime bootstrap-compatible callers.
   *
   * This path preserves legacy behavior by allowing initial room-entry
   * bootstrap and cursor/materialization persistence during the read request.
   */
  public function getMaterializedFullState(int $campaign_id): array {
    $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    $context = $this->coordinatorRuntimeReadService->resolveFullStateReadContext($campaign_id);
    if ($context === NULL) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $dungeon_data = $context['dungeon_data'];
    $game_state = $context['game_state'];
    return $this->buildFullStateResponse($campaign_id, $dungeon_data, $game_state, TRUE);
  }

  /**
   * Returns the currently available actions for a specific actor.
   *
   * This mirrors the phase handler action surface used by the client, but lets
   * headless runtimes ask for actor-scoped actions without issuing a gameplay
   * mutation.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string|null $actor_id
   *   The actor entity ID to scope available actions for.
   *
   * @return string[]
   *   Legal actions for the current phase and actor.
   */
  public function getAvailableActionsForActor(int $campaign_id, ?string $actor_id = NULL): array {
    $context = $this->resolveActionAvailabilityContext($campaign_id, $actor_id);
    if ($context === NULL || $context['handler'] === NULL) {
      return [];
    }

    /** @var \Drupal\dungeoncrawler_content\Service\PhaseHandlerInterface $handler */
    $handler = $context['handler'];

    return $handler->getAvailableActions($context['game_state'], $context['dungeon_data'], $actor_id);
  }

  /**
   * Return the shared actor-scoped action-availability contract.
   *
   * This is the coordinator-facing query surface that GM/NPC/UI tooling can use
   * to ask for one actor's current authoritative action availability.
   *
   * @return array{available_actions: string[], action_contract: ?array<string,mixed>}
   *   Shared actor-scoped availability payload.
   */
  public function getActionAvailabilityForActor(int $campaign_id, ?string $actor_id = NULL): array {
    $context = $this->resolveActionAvailabilityContext($campaign_id, $actor_id);
    if ($context === NULL || $context['handler'] === NULL) {
      return $this->emptyActionAvailabilityPayload();
    }

    /** @var \Drupal\dungeoncrawler_content\Service\PhaseHandlerInterface $handler */
    $handler = $context['handler'];
    return [
      'available_actions' => $handler->getAvailableActions($context['game_state'], $context['dungeon_data'], $actor_id),
      'action_contract' => $this->buildActionContract($handler, $context['game_state'], $context['dungeon_data'], $actor_id),
    ];
  }

  /**
   * Return runtime state context for read-only consumers without persistence.
   *
   * This is intentionally side-effect free: it does not bootstrap encounters,
   * auto-resolve turns, or persist cursor updates during read calls.
   */
  public function getRuntimeReadState(int $campaign_id, ?string $actor_id = NULL): array {
    $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    $context = $this->resolveActionAvailabilityContext($campaign_id, $actor_id);
    if ($context === NULL) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $dungeon_data = $context['dungeon_data'];
    $game_state = $context['game_state'];
    $actor_id = trim((string) $actor_id);

    /** @var \Drupal\dungeoncrawler_content\Service\PhaseHandlerInterface|null $handler */
    $handler = $context['handler'];
    $runtime_snapshot = $this->buildRuntimeSnapshotPayload($game_state, $dungeon_data, $actor_id);
    $runtime_snapshot['available_actions'] = $handler
      ? $handler->getAvailableActions($game_state, $dungeon_data, $actor_id)
      : [];
    $runtime_snapshot['action_contract'] = $this->buildActionContract($handler, $game_state, $dungeon_data, $actor_id);
    return $runtime_snapshot;
  }

  /**
   * Return explicit full runtime projection (heavy read lane).
   *
   * This is an opt-in compatibility/debug projection path and should not be
   * used by default runtime reads.
   */
  public function getFullRuntimeProjection(int $campaign_id, ?string $actor_id = NULL, string $reason = 'unspecified'): ?array {
    $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    $reason = trim($reason);
    if ($reason === '') {
      throw new \RuntimeException(sprintf(
        'Full runtime projection contract violation: reason is required for campaign %d.',
        $campaign_id
      ));
    }
    $reason = strtolower($reason);
    if (!$this->isAllowedFullRuntimeProjectionReason($reason)) {
      throw new \RuntimeException(sprintf(
        'Full runtime projection contract violation: reason "%s" is not allowlisted for campaign %d.',
        $reason,
        $campaign_id
      ));
    }
    $this->logger->notice('Full runtime projection requested for campaign @id (reason=@reason).', [
      '@id' => $campaign_id,
      '@reason' => $reason,
    ]);
    $dungeon_data = $this->loadDungeonData($campaign_id, $actor_id, TRUE);
    return is_array($dungeon_data) ? $dungeon_data : NULL;
  }

  /**
   * Legacy alias for full runtime projection reads.
   *
   * Prefer getFullRuntimeProjection() with an explicit reason.
   */
  public function getRuntimeHydratedDungeonData(int $campaign_id, ?string $actor_id = NULL): ?array {
    return $this->getFullRuntimeProjection($campaign_id, $actor_id, 'legacy_runtime_hydration_api');
  }

  /**
   * Check whether a full-runtime projection reason is explicitly allowlisted.
   */
  protected function isAllowedFullRuntimeProjectionReason(string $reason): bool {
    if (in_array($reason, self::FULL_RUNTIME_PROJECTION_REASON_ALLOWLIST, TRUE)) {
      return TRUE;
    }
    foreach (self::FULL_RUNTIME_PROJECTION_REASON_PREFIX_ALLOWLIST as $prefix) {
      if (str_starts_with($reason, $prefix)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Resolve the display name for a runtime actor entity.
   */
  public function resolveActorDisplayName(int $campaign_id, string $actor_id): ?string {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      return NULL;
    }

    $game_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id) ?? [];
    foreach ($game_state['initiative_order'] ?? [] as $combatant) {
      if (($combatant['entity_id'] ?? '') === $actor_id) {
        $name = trim((string) ($combatant['name'] ?? $combatant['display_name'] ?? ''));
        return $name !== '' ? $name : $actor_id;
      }
    }

    foreach ($this->actorRuntimeStateStore->loadActorEntities($campaign_id) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_instance_id = trim((string) (
        $entity['entity_instance_id']
        ?? $entity['instance_id']
        ?? $entity['id']
        ?? $entity['entity_ref']['content_id']
        ?? ''
      ));
      if ($entity_instance_id !== $actor_id) {
        continue;
      }

      $name = trim((string) ($entity['state']['metadata']['display_name'] ?? ($entity['name'] ?? '')));
      return $name !== '' ? $name : $actor_id;
    }

    return $actor_id;
  }

  /**
   * Resolve the runtime actor entity ID for a campaign character.
   */
  public function resolveActorIdForCharacterId(int $campaign_id, int $character_id): ?string {
    if ($character_id <= 0) {
      return NULL;
    }

    $entities = $this->actorRuntimeStateStore->loadActorEntities($campaign_id);
    if ($entities === []) {
      return NULL;
    }

    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }

      $candidate_character_ids = [
        $entity['state']['metadata']['campaign_character_id'] ?? NULL,
        $entity['state']['metadata']['source_character_id'] ?? NULL,
        $entity['state']['metadata']['character_id'] ?? NULL,
        $entity['character_id'] ?? NULL,
        $entity['source_character_id'] ?? NULL,
        $entity['state']['character_id'] ?? NULL,
        $entity['entity_ref']['character_id'] ?? NULL,
        $entity['entity_ref']['content_id'] ?? NULL,
      ];
      $matches_character = FALSE;
      foreach ($candidate_character_ids as $candidate_character_id) {
        $candidate_character_id = trim((string) $candidate_character_id);
        if ($candidate_character_id !== '' && (int) $candidate_character_id === $character_id) {
          $matches_character = TRUE;
          break;
        }
      }
      if (!$matches_character) {
        continue;
      }

      $instance_id = (string) (
        $entity['state']['metadata']['runtime_entity_id']
        ?? $entity['entity_instance_id']
        ?? $entity['instance_id']
        ?? $entity['id']
        ?? ''
      );
      $instance_id = trim($instance_id);
      if ($instance_id !== '') {
        return $instance_id;
      }
    }

    return NULL;
  }

  public function getActiveRoomId(int $campaign_id, ?string $actor_id = NULL): ?string {
    $runtime_game_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id) ?? [];
    $room_id = trim((string) (
      $runtime_game_state['active_room_id']
      ?? $runtime_game_state['encounter_context']['room_id']
      ?? ''
    ));
    if ($room_id === '' && $this->database->schema()->tableExists('dc_campaign_runtime_state')) {
      $runtime_state_row = $this->database->select('dc_campaign_runtime_state', 's')
        ->fields('s', ['active_room_id'])
        ->condition('campaign_id', $campaign_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      $room_id = trim((string) ($runtime_state_row['active_room_id'] ?? ''));
    }
    if ($room_id !== '') {
      return $room_id;
    }

    $actor_id = trim((string) $actor_id);
    if ($actor_id !== '') {
      foreach ($this->actorRuntimeStateStore->loadActorEntities($campaign_id) as $entity) {
        if (!is_array($entity)) {
          continue;
        }
        $entity_instance_id = trim((string) (
          $entity['entity_instance_id']
          ?? $entity['instance_id']
          ?? $entity['id']
          ?? ''
        ));
        if ($entity_instance_id !== $actor_id) {
          continue;
        }
        $actor_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
        return $actor_room_id !== '' ? $actor_room_id : NULL;
      }
    }

    return NULL;
  }

  /**
   * Manually transition to a new phase.
   *
   * Used for explicit transitions when additional live phases are present.
   * Room entry and combat escalation already route through the encounter
   * framework automatically.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $target_phase
   *   The phase to transition to.
   * @param array $context
   *   Transition context (e.g., encounter_context for encounter phase).
   *
   * @return array
   *   Transition result.
   */
  public function transitionPhase(int $campaign_id, string $target_phase, array $context = []): array {
    $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    $requested_room_id = trim((string) ($context['encounter_context']['room_id'] ?? ''));
    $dungeon_data = $this->loadDungeonData(
      $campaign_id,
      NULL,
      TRUE,
      1,
      $requested_room_id !== '' ? $requested_room_id : NULL
    );
    if (!$dungeon_data) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $game_state = $this->ensureGameState($dungeon_data);
    $current_phase = $game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE;

    if (in_array($target_phase, self::DEPRECATED_PHASES, TRUE)) {
      return $this->errorResponse(
        "Phase '$target_phase' is deprecated and disabled. Active gameplay must remain in the encounter framework.",
        $game_state
      );
    }

    // Validate the transition.
    $valid_targets = self::VALID_TRANSITIONS[$current_phase] ?? [];
    if (!in_array($target_phase, $valid_targets)) {
      return $this->errorResponse(
        "Cannot transition from '$current_phase' to '$target_phase'. Valid targets: " . implode(', ', $valid_targets),
        $game_state
      );
    }

    $context['from_phase'] = $current_phase;
    $pre_transition_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);

    // Execute the transition.
    $result = $this->executePhaseTransition(
      $current_phase,
      $target_phase,
      $context,
      $game_state,
      $dungeon_data,
      $campaign_id
    );

    // Increment version and persist.
    $game_state['state_version'] = ($game_state['state_version'] ?? 0) + 1;
    $mutation_envelope = $this->resolveMutationEnvelopeForPersistence(
      $campaign_id,
      $result['mutation_envelope'] ?? NULL,
      $game_state,
      $dungeon_data,
      $pre_transition_slice_fingerprints
    );
    $this->persistStateWithMinimalWrite(
      $campaign_id,
      $dungeon_data,
      $game_state,
      $mutation_envelope
    );
    $this->ensurePersistedRuntimeStateMatches($campaign_id, $game_state, (string) ($dungeon_data['active_room_id'] ?? ''));

    $handler = $this->getPhaseHandler($target_phase);
    $action_contract = $this->buildActionContract($handler, $game_state, $dungeon_data);

    return [
      'success' => TRUE,
      'game_state' => $this->buildClientGameState($game_state),
      'phase' => $target_phase,
      'events' => $result['events'] ?? [],
      'available_actions' => $handler
        ? $handler->getAvailableActions($game_state, $dungeon_data)
        : [],
      'action_contract' => $action_contract,
      'state_version' => $game_state['state_version'],
      'mutation_envelope' => $mutation_envelope,
      'runtime_snapshot' => $this->buildRuntimeSnapshotPayload($game_state, $dungeon_data),
    ];
  }

  /**
   * Escalates the active room-scene encounter into hostile combat.
   *
   * This keeps gameplay in the canonical encounter phase while layering the
   * persisted combat engine onto the current room encounter framework.
   */
  public function startCombatEncounter(int $campaign_id, array $context = []): array {
    $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    $requested_room_id = trim((string) (
      $context['encounter_context']['room_id']
      ?? ''
    ));
    $dungeon_data = $this->loadDungeonData(
      $campaign_id,
      NULL,
      TRUE,
      1,
      $requested_room_id !== '' ? $requested_room_id : NULL
    );
    if (!$dungeon_data) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $game_state = $this->ensureGameState($dungeon_data);
    $room_id = (string) (
      $context['encounter_context']['room_id']
      ?? $dungeon_data['active_room_id']
      ?? $this->resolveStartupRoomId($dungeon_data)
      ?? ''
    );
    if ($room_id === '') {
      return $this->errorResponse('Combat initiation requires an active room.', $game_state);
    }

    $handler = $this->getPhaseHandler(self::DEFAULT_ACTIVE_PHASE);
    if (!$handler instanceof EncounterPhaseHandler) {
      return $this->errorResponse('Encounter handler unavailable.', $game_state);
    }
    $pre_combat_slice_fingerprints = $this->computeRuntimeSliceFingerprints($dungeon_data);
    $combat_mutation_envelope = is_array($context['mutation_envelope'] ?? NULL)
      ? $context['mutation_envelope']
      : NULL;

    $logged_events = [];
    $encounter_mode = strtolower(trim((string) ($game_state['encounter_context']['mode'] ?? '')));
    $force_hostile_start = FALSE;
    if (!empty($game_state['encounter_id'])) {
      if ($encounter_mode === 'room_scene') {
        $exit_result = $this->normalizePhaseLifecycleResult(
          $handler->onExit($game_state, $dungeon_data, $campaign_id),
          self::DEFAULT_ACTIVE_PHASE,
          'onExit',
          $campaign_id
        );
        if ($exit_result['events'] !== []) {
          $logged_events = array_merge(
            $logged_events,
            $this->eventLogger->logEvents($dungeon_data, $exit_result['events'])
          );
        }
        if (is_array($exit_result['mutation_envelope'])) {
          $combat_mutation_envelope = $exit_result['mutation_envelope'];
        }
        $force_hostile_start = TRUE;
      }
      else {
        return $this->errorResponse('Combat is already active.', $game_state);
      }
    }

    $needs_room_framework = ($game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE) !== self::DEFAULT_ACTIVE_PHASE
      || (string) ($game_state['encounter_context']['room_id'] ?? '') !== $room_id
      || empty($game_state['turn'])
      || !is_array($game_state['initiative_order'] ?? NULL);

    if ($needs_room_framework && !$force_hostile_start) {
      $room_result = $handler->enterRoomFramework(NULL, $room_id, [], $game_state, $dungeon_data, $campaign_id);
      if (!empty($room_result['error'])) {
        return $this->errorResponse((string) $room_result['error'], $game_state);
      }
      if (!empty($room_result['events'])) {
        $logged_events = array_merge(
          $logged_events,
          $this->eventLogger->logEvents($dungeon_data, $room_result['events'])
        );
      }
    }

    if (empty($game_state['encounter_id']) || $force_hostile_start) {
      $enter_result = $this->normalizePhaseLifecycleResult(
        $handler->onEnter($context, $game_state, $dungeon_data, $campaign_id),
        self::DEFAULT_ACTIVE_PHASE,
        'onEnter',
        $campaign_id
      );
      if ($enter_result['events'] !== []) {
        $logged_events = array_merge(
          $logged_events,
          $this->eventLogger->logEvents($dungeon_data, $enter_result['events'])
        );
      }
      if (is_array($enter_result['mutation_envelope'])) {
        $combat_mutation_envelope = $enter_result['mutation_envelope'];
      }
    }

    $game_state['state_version'] = ($game_state['state_version'] ?? 0) + 1;
    $mutation_envelope = $this->resolveMutationEnvelopeForPersistence(
      $campaign_id,
      $combat_mutation_envelope,
      $game_state,
      $dungeon_data,
      $pre_combat_slice_fingerprints
    );
    $this->persistStateWithMinimalWrite(
      $campaign_id,
      $dungeon_data,
      $game_state,
      $mutation_envelope
    );
    $this->ensurePersistedRuntimeStateMatches($campaign_id, $game_state, (string) ($dungeon_data['active_room_id'] ?? ''));

    $action_contract = $this->buildActionContract($handler, $game_state, $dungeon_data);

    return [
      'success' => TRUE,
      'game_state' => $this->buildClientGameState($game_state),
      'phase' => self::DEFAULT_ACTIVE_PHASE,
      'events' => $logged_events,
      'available_actions' => $handler->getAvailableActions($game_state, $dungeon_data),
      'action_contract' => $action_contract,
      'state_version' => $game_state['state_version'],
      'mutation_envelope' => $mutation_envelope,
      'runtime_snapshot' => $this->buildRuntimeSnapshotPayload($game_state, $dungeon_data),
    ];
  }

  /**
   * Get events since a cursor (for client polling / SSE).
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param int $since_cursor
   *   Return events with id > this value.
   *
   * @return array
   *   Array of events.
   */
  public function getEventsSince(int $campaign_id, int $since_cursor = 0): array {
    $this->runtimeBootstrap->assertCampaignRuntimeReady($campaign_id);
    $events = $this->eventLogger->getEventsSince([], $since_cursor, $campaign_id);
    $runtime_game_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id);
    $state_version = is_array($runtime_game_state) && isset($runtime_game_state['state_version']) && is_numeric($runtime_game_state['state_version'])
      ? (int) $runtime_game_state['state_version']
      : 1;

    return [
      'success' => TRUE,
      'events' => $events,
      'cursor' => !empty($events) ? end($events)['id'] : $since_cursor,
      'state_version' => $state_version,
    ];
  }

  // =========================================================================
  // Phase transition lifecycle.
  // =========================================================================

  /**
   * Executes a phase transition with full lifecycle (onExit → onEnter).
   */
  protected function executePhaseTransition(
    string $from_phase,
    string $to_phase,
    array $context,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    $all_events = [];
    $transition_mutation_envelope = NULL;

    // 1. Exit the current phase.
    $from_handler = $this->getPhaseHandler($from_phase);
    if ($from_handler) {
      $exit_result = $this->normalizePhaseLifecycleResult(
        $from_handler->onExit($game_state, $dungeon_data, $campaign_id),
        $from_phase,
        'onExit',
        $campaign_id
      );
      $all_events = array_merge($all_events, $exit_result['events']);
      if (is_array($exit_result['mutation_envelope'])) {
        $transition_mutation_envelope = $exit_result['mutation_envelope'];
      }
    }

    // 2. Log the transition event with AI GM narration.
    $transition_narration = $this->aiGmService->narratePhaseTransition(
      $from_phase,
      $to_phase,
      $context['reason'] ?? '',
      $dungeon_data,
      $campaign_id
    );
    $transition_event = GameEventLogger::buildEvent('phase_transition', $from_phase, NULL, [
      'from' => $from_phase,
      'to' => $to_phase,
      'reason' => $context['reason'] ?? NULL,
    ], $transition_narration);
    $all_events[] = $transition_event;

    // Queue phase transition for perception-filtered narration.
    if ($this->narrationEngine) {
      $dungeon_id = $dungeon_data['dungeon_id'] ?? $dungeon_data['id'] ?? 0;
      $room_id = $dungeon_data['active_room_id'] ?? '';
      $present = NarrationEngine::buildPresentCharacters($dungeon_data, $room_id);
      try {
        $this->narrationEngine->queueRoomEvent(
          $campaign_id,
          $dungeon_id,
          $room_id,
          [
            'type' => 'action',
            'speaker' => 'GM',
            'speaker_type' => 'gm',
            'speaker_ref' => '',
            'content' => sprintf('Phase transitions from %s to %s. %s', $from_phase, $to_phase, $context['reason'] ?? ''),
            'visibility' => 'public',
            'mechanical_data' => [
              'from_phase' => $from_phase,
              'to_phase' => $to_phase,
            ],
          ],
          $present
        );
      }
      catch (\Throwable $e) {
        $this->logger->warning('NarrationEngine queue failed during phase transition: @err', ['@err' => $e->getMessage()]);
      }
    }

    // 3. Enter the new phase.
    $to_handler = $this->getPhaseHandler($to_phase);
    if ($to_handler) {
      $enter_result = $this->normalizePhaseLifecycleResult(
        $to_handler->onEnter($context, $game_state, $dungeon_data, $campaign_id),
        $to_phase,
        'onEnter',
        $campaign_id
      );
      $all_events = array_merge($all_events, $enter_result['events']);
      if (is_array($enter_result['mutation_envelope'])) {
        $transition_mutation_envelope = $enter_result['mutation_envelope'];
      }
    }

    // 4. Log all transition events.
    if (!empty($all_events)) {
      $this->eventLogger->logEvents($dungeon_data, $all_events);
    }

    $this->logger->info('Phase transition: @from → @to (campaign @id)', [
      '@from' => $from_phase,
      '@to' => $to_phase,
      '@id' => $campaign_id,
    ]);

    return [
      'events' => $all_events,
      'mutation_envelope' => $transition_mutation_envelope,
    ];
  }

  // =========================================================================
  // Data access.
  // =========================================================================

  /**
   * Loads dungeon_data from the database.
   */
  protected function loadDungeonData(
    int $campaign_id,
    ?string $preferred_actor_id = NULL,
    bool $rebuild_runtime_graph = TRUE,
    int $room_scope_depth = -1,
    ?string $requested_room_id = NULL
  ): ?array {
    try {
      $runtime_character_id = NULL;
      if (is_string($preferred_actor_id) && trim($preferred_actor_id) !== '') {
        $runtime_character_id = $this->runtimeBootstrap->resolveRuntimeCharacterIdForActor($campaign_id, trim($preferred_actor_id));
      }
      $row = $this->runtimeBootstrap->loadAuthoritativeDungeonRowForRuntimeRead($campaign_id, $runtime_character_id);

      if (!empty($row['dungeon_data'])) {
        $decoded = json_decode($row['dungeon_data'], TRUE) ?: NULL;
        if (is_array($decoded)) {
          $decoded['campaign_id'] = $campaign_id;
          if (trim((string) ($decoded['dungeon_id'] ?? '')) === '') {
            $decoded['dungeon_id'] = trim((string) ($row['dungeon_id'] ?? ''));
          }
          $runtime_game_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id);
          if (is_array($runtime_game_state)) {
            $decoded['game_state'] = $runtime_game_state;
            $authoritative_active_room_id = trim((string) (
              $runtime_game_state['active_room_id']
              ?? $runtime_game_state['encounter_context']['room_id']
              ?? ''
            ));
            if ($authoritative_active_room_id !== '') {
              $decoded['active_room_id'] = $authoritative_active_room_id;
              $decoded['current_room_id'] = $authoritative_active_room_id;
            }
          }
          $resolved_dungeon_id = trim((string) ($decoded['dungeon_id'] ?? $row['dungeon_id'] ?? ''));
          if ($rebuild_runtime_graph && $resolved_dungeon_id !== '') {
            $resolved_requested_room_id = trim((string) ($requested_room_id ?? ''));
            $decoded = $this->runtimeGraphAssembler->buildRuntimeGraph(
              $campaign_id,
              $resolved_dungeon_id,
              $decoded,
              [
                'active_room_id' => trim((string) ($decoded['active_room_id'] ?? '')),
                'room_batch_size' => 8,
                'room_scope_depth' => $room_scope_depth,
                'requested_room_id' => $resolved_requested_room_id,
              ]
            );
          }
          if (empty($decoded['active_room_id'])) {
            $this->resolveStartupRoomId($decoded);
          }
          $runtime_entities = $this->actorRuntimeStateStore->loadActorEntities($campaign_id);
          if ($room_scope_depth >= 0 && $runtime_entities !== []) {
            $scoped_room_ids = [];
            foreach ($decoded['rooms'] ?? [] as $room) {
              if (!is_array($room)) {
                continue;
              }
              $room_id = trim((string) ($room['room_id'] ?? ''));
              if ($room_id !== '') {
                $scoped_room_ids[$room_id] = TRUE;
              }
            }
            $preferred_actor_id = trim((string) ($preferred_actor_id ?? ''));
            $runtime_entities = array_values(array_filter($runtime_entities, function ($entity) use ($scoped_room_ids, $preferred_actor_id): bool {
              if (!is_array($entity)) {
                return FALSE;
              }
              $entity_id = trim((string) (
                $entity['entity_instance_id']
                ?? $entity['instance_id']
                ?? $entity['id']
                ?? ''
              ));
              if ($preferred_actor_id !== '' && $entity_id === $preferred_actor_id) {
                return TRUE;
              }
              $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
              return $entity_room_id !== '' && isset($scoped_room_ids[$entity_room_id]);
            }));
          }
          if ($runtime_entities !== []) {
            $decoded['entities'] = $runtime_entities;
          }
          $decoded['__campaign_dungeon_row_id'] = (int) ($row['id'] ?? 0);
          return $this->campaignCharacterRuntimeSync->syncActiveRoomPlayerEntities($decoded, $campaign_id, $preferred_actor_id);
        }
      }
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to load dungeon data for campaign @id: @error', [
        '@id' => $campaign_id,
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Ensures the game_state key exists in dungeon_data with defaults.
   *
   * @param array &$dungeon_data
   *   The dungeon data array (modified in place).
   *
   * @return array
   *   The game_state (reference into dungeon_data).
   */
  protected function &ensureGameState(array &$dungeon_data): array {
    if (!isset($dungeon_data['game_state']) || !is_array($dungeon_data['game_state'])) {
      $dungeon_data['game_state'] = self::DEFAULT_GAME_STATE;
      $dungeon_data['game_state']['started_at'] = date('c');
      $dungeon_data['game_state']['session_id'] = 'sess_' . date('Ymd_His');
    }

    // Ensure all default keys exist (forward compatibility).
    foreach (self::DEFAULT_GAME_STATE as $key => $default) {
      if (!array_key_exists($key, $dungeon_data['game_state'])) {
        $dungeon_data['game_state'][$key] = $default;
      }
    }

    $this->campaignTimeResolver->ensureTimeState($dungeon_data['game_state']);
    $phase = (string) ($dungeon_data['game_state']['phase'] ?? self::DEFAULT_ACTIVE_PHASE);
    if ($phase === '' || in_array($phase, self::DEPRECATED_PHASES, TRUE)) {
      $dungeon_data['game_state']['phase'] = self::DEFAULT_ACTIVE_PHASE;
    }
    $this->synchronizeActiveRoomAuthority($dungeon_data['game_state'], $dungeon_data);

    return $dungeon_data['game_state'];
  }

  /**
   * Mirror the authoritative room id into runtime game_state.
   */
  protected function synchronizeActiveRoomAuthority(array &$game_state, array $dungeon_data): void {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    if ($active_room_id === '') {
      $active_room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ''));
    }
    if ($active_room_id === '') {
      return;
    }

    $game_state['active_room_id'] = $active_room_id;
    if (
      isset($game_state['encounter_context'])
      && is_array($game_state['encounter_context'])
      && trim((string) ($game_state['encounter_context']['room_id'] ?? '')) === ''
    ) {
      $game_state['encounter_context']['room_id'] = $active_room_id;
    }
  }

  /**
   * Bootstraps a one-time initial room-entered event for fresh campaigns.
   *
   * @return array<int, array<string, mixed>>
   *   Newly created events, if any.
   */
  protected function bootstrapInitialRoomEntry(int $campaign_id, array &$dungeon_data, array &$game_state): array {
    $room_id = $this->resolveStartupRoomId($dungeon_data);
    if ($room_id === NULL) {
      return [];
    }

    if ($this->hasBootstrappedInitialRoomEntry($game_state, $room_id)) {
      return [];
    }

    if ($this->hasActiveEncounterContextForRoom($game_state, $room_id)) {
      $this->markInitialRoomEntryBootstrapped($game_state, $room_id);
      return [];
    }

    $event_log_cursor = (int) ($game_state['event_log_cursor'] ?? 0);
    if ($event_log_cursor > 0) {
      $this->markInitialRoomEntryBootstrapped($game_state, $room_id);
      return [];
    }

    $latest_event_cursor = $this->eventLogger->getLatestCursor($dungeon_data, $campaign_id);
    if ($latest_event_cursor > 0) {
      $game_state['event_log_cursor'] = max($event_log_cursor, $latest_event_cursor);
      $this->markInitialRoomEntryBootstrapped($game_state, $room_id);
      return [];
    }

    $room_data = $this->findRoomInDungeon($room_id, $dungeon_data);
    if ($room_data === NULL) {
      return [];
    }

    $handler = $this->getPhaseHandler(self::DEFAULT_ACTIVE_PHASE);
    if ($handler instanceof EncounterPhaseHandler) {
      $game_state['suppress_room_scene_narration'] = TRUE;
      try {
        $result = $handler->bootstrapRoomSceneFramework($room_id, $game_state, $dungeon_data, $campaign_id);
        $events = $result['events'] ?? [];
        $bootstrap_events = $events !== [] ? $this->eventLogger->logEvents($dungeon_data, $events) : [];
        $autoplay_events = $this->autoResolveRoomSceneNonPlayerTurns($campaign_id, $game_state, $dungeon_data, $handler);
      }
      finally {
        unset($game_state['suppress_room_scene_narration']);
      }

      if ($bootstrap_events === [] && $autoplay_events === []) {
        return [];
      }

      $this->markInitialRoomEntryBootstrapped($game_state, $room_id);
      return array_merge($bootstrap_events, $autoplay_events);
    }

    return [];
  }

  /**
   * Determine whether initial room entry has already been bootstrapped.
   */
  protected function hasBootstrappedInitialRoomEntry(array $game_state, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }

    $bootstrapped_room_id = trim((string) ($game_state['initial_room_entry_room_id'] ?? ''));
    if ($bootstrapped_room_id !== '' && $bootstrapped_room_id === $room_id) {
      return TRUE;
    }

    return (int) ($game_state['event_log_cursor'] ?? 0) > 0;
  }

  /**
   * Determine whether runtime state already owns a live encounter context.
   */
  protected function hasActiveEncounterContextForRoom(array $game_state, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }

    $phase = trim((string) ($game_state['phase'] ?? ''));
    $context_room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ''));
    if ($phase !== self::DEFAULT_ACTIVE_PHASE || $context_room_id !== $room_id) {
      return FALSE;
    }

    if (!empty($game_state['encounter_id'])) {
      return TRUE;
    }

    return !empty($game_state['initiative_order']) || !empty($game_state['turn']);
  }

  /**
   * Mark initial room-entry bootstrap as complete for the active room.
   */
  protected function markInitialRoomEntryBootstrapped(array &$game_state, string $room_id): void {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return;
    }

    $game_state['initial_room_entry_room_id'] = $room_id;
    if (trim((string) ($game_state['initial_room_entry_completed_at'] ?? '')) === '') {
      $game_state['initial_room_entry_completed_at'] = date('c');
    }
  }

  /**
   * Resolves and persists the startup active room ID when absent.
   */
  protected function resolveStartupRoomId(array &$dungeon_data): ?string {
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;
    if (is_string($active_room_id) && $active_room_id !== '') {
      return $active_room_id;
    }

    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      $candidate = $room['room_id'] ?? NULL;
      if (is_string($candidate) && $candidate !== '') {
        $dungeon_data['active_room_id'] = $candidate;
        return $candidate;
      }
    }

    return NULL;
  }

  /**
   * Persist the campaign runtime state slice without rewriting dungeon payloads.
   */
  protected function persistGameStateSlice(int $campaign_id, array $game_state, ?string $active_room_id = NULL): bool {
    try {
      return $this->campaignRuntimeMutationService->persistGameState($campaign_id, $game_state, $active_room_id);
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to persist campaign runtime state for campaign @id: @error', [
        '@id' => $campaign_id,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Persist through slice-only lane when non-game-state payload did not change.
   */
  protected function persistStateWithMinimalWrite(
    int $campaign_id,
    array $dungeon_data,
    array $game_state,
    ?array $mutation_envelope = NULL
  ): bool {
    if ($mutation_envelope === NULL) {
      $mutation_envelope = $this->buildMutationEnvelopeFromPayload(
        $campaign_id,
        $game_state,
        $dungeon_data,
        TRUE
      );
    }
    return $this->persistMutationEnvelope($mutation_envelope);
  }

  /**
   * Verify the runtime state row reflects the authoritative in-memory game state.
   *
   * Transition/navigation actions proved able to return success while the
   * persisted runtime-state slice remained on the previous room/version. Make
   * the coordinator contract explicit here: repair one time, then fail loudly
   * if the authoritative slice still does not match.
   */
  protected function ensurePersistedRuntimeStateMatches(int $campaign_id, array $game_state, ?string $active_room_id = NULL): void {
    $expected_state_version = max(1, (int) ($game_state['state_version'] ?? 1));
    $expected_active_room_id = trim((string) (
      $active_room_id
      ?? ($game_state['active_room_id'] ?? NULL)
      ?? ($game_state['encounter_context']['room_id'] ?? '')
    ));
    $persisted_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id) ?? [];
    $persisted_state_version = max(1, (int) ($persisted_state['state_version'] ?? 1));
    $persisted_active_room_id = trim((string) (
      $persisted_state['active_room_id']
      ?? ($persisted_state['encounter_context']['room_id'] ?? '')
    ));

    if (
      $persisted_state_version === $expected_state_version
      && $persisted_active_room_id === $expected_active_room_id
    ) {
      return;
    }

    $this->logger->warning(
      'Coordinator runtime-state mismatch detected after persist; repairing slice write for campaign {campaign_id} (expected_version={expected_version}, persisted_version={persisted_version}, expected_room={expected_room}, persisted_room={persisted_room})',
      [
        'campaign_id' => $campaign_id,
        'expected_version' => $expected_state_version,
        'persisted_version' => $persisted_state_version,
        'expected_room' => $expected_active_room_id,
        'persisted_room' => $persisted_active_room_id,
      ]
    );

    if (!$this->persistGameStateSlice(
      $campaign_id,
      $game_state,
      $expected_active_room_id !== '' ? $expected_active_room_id : NULL
    )) {
      throw new \RuntimeException(sprintf(
        'Coordinator runtime-state repair failed for campaign %d: could not persist authoritative game_state slice.',
        $campaign_id
      ));
    }

    $reloaded_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id) ?? [];
    $reloaded_state_version = max(1, (int) ($reloaded_state['state_version'] ?? 1));
    $reloaded_active_room_id = trim((string) (
      $reloaded_state['active_room_id']
      ?? ($reloaded_state['encounter_context']['room_id'] ?? '')
    ));
    if (
      $reloaded_state_version !== $expected_state_version
      || $reloaded_active_room_id !== $expected_active_room_id
    ) {
      throw new \RuntimeException(sprintf(
        'Coordinator runtime-state contract violation after repair for campaign %d (expected version=%d room=%s, persisted version=%d room=%s).',
        $campaign_id,
        $expected_state_version,
        $expected_active_room_id !== '' ? $expected_active_room_id : '<none>',
        $reloaded_state_version,
        $reloaded_active_room_id !== '' ? $reloaded_active_room_id : '<none>'
      ));
    }
  }

  /**
   * Build a deterministic fingerprint excluding game_state lane content.
   */
  protected function computeNonGameStatePayloadFingerprint(array $dungeon_data): string {
    unset(
      $dungeon_data['game_state'],
      $dungeon_data['event_log'],
      $dungeon_data['__campaign_dungeon_row_id']
    );
    $encoded = json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw new \RuntimeException('GameCoordinator payload fingerprint contract violation: unable to encode payload.');
    }
    return hash('sha256', $encoded);
  }

  /**
   * Build deterministic fingerprints for runtime non-game-state slices.
   *
   * @return array{actor_entities: string, rooms: string, connections: string}
   *   Fingerprints for explicit mutation-envelope slices.
   */
  protected function computeRuntimeSliceFingerprints(array $dungeon_data): array {
    $actor_entities = is_array($dungeon_data['entities'] ?? NULL) ? $dungeon_data['entities'] : [];
    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    $connections = $this->collectRuntimeConnectionsFromPayload($dungeon_data);

    $encode = static function (array $payload): string {
      $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($encoded)) {
        throw new \RuntimeException('GameCoordinator runtime-slice fingerprint contract violation: unable to encode payload.');
      }
      return hash('sha256', $encoded);
    };

    return [
      'actor_entities' => $encode($actor_entities),
      'rooms' => $encode($rooms),
      'connections' => $encode($connections),
    ];
  }

  /**
   * Collect canonical runtime connection payloads from snapshot buckets.
   *
   * @return array<int, array<string,mixed>>
   *   Normalized connection payload list.
   */
  protected function collectRuntimeConnectionsFromPayload(array $dungeon_data): array {
    $by_id = [];
    foreach ([
      $dungeon_data['connections'] ?? [],
      $dungeon_data['hex_map']['connections'] ?? [],
    ] as $bucket) {
      foreach ((array) $bucket as $connection) {
        if (!is_array($connection)) {
          continue;
        }
        $connection_id = trim((string) ($connection['connection_id'] ?? ''));
        if ($connection_id === '') {
          continue;
        }
        $by_id[$connection_id] = $connection;
      }
    }
    return array_values($by_id);
  }

  /**
   * Finds a room in dungeon data by ID.
   */
  protected function findRoomInDungeon(?string $room_id, array $dungeon_data): ?array {
    if ($room_id === NULL || $room_id === '') {
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
   * Ensure transition actions return an authoritative navigation receipt.
   *
   * @param array<string,mixed> $intent
   *   Canonical intent payload.
   * @param array<string,mixed> $result_payload
   *   Handler result payload.
   * @param array<string,mixed> $action_result
   *   Full normalized handler action result.
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon payload after action mutation.
   *
   * @return array<string,mixed>
   *   Result payload with canonical navigation receipt when applicable.
   */
  protected function enrichTransitionResultPayloadIfNeeded(
    bool $action_success,
    array $intent,
    array $result_payload,
    array $action_result,
    array $dungeon_data
  ): array {
    $timing_started_at = microtime(TRUE);
    $intent_type = strtolower(trim((string) ($intent['type'] ?? '')));
    if (!$action_success || $intent_type !== 'transition') {
      return $result_payload;
    }

    $target_room_id = trim((string) (
      $result_payload['to_room']
      ?? $result_payload['target_room_id']
      ?? $dungeon_data['active_room_id']
      ?? ''
    ));
    if ($target_room_id === '') {
      throw new \RuntimeException('Transition response contract violation: target_room_id is required for successful transition results.');
    }

    $entry_hex = $this->resolveTransitionEntryHexForResultPayload($intent, $action_result, $result_payload, $dungeon_data, $target_room_id);
    $navigation_receipt = $this->buildTransitionNavigationReceiptPayload(
      $dungeon_data,
      trim((string) ($result_payload['from_room'] ?? '')),
      $target_room_id,
      $entry_hex
    );

    $result_payload['entry_hex'] = $entry_hex;
    $result_payload['navigation_capabilities'] = $navigation_receipt['navigation_capabilities'];
    $result_payload['navigation'] = $navigation_receipt;

    $total_ms = (microtime(TRUE) - $timing_started_at) * 1000.0;
    if ($total_ms >= self::NAVIGATION_TIMING_SLOW_THRESHOLD_MS) {
      $this->logger->notice(
        'Navigation timing: enrichTransitionResultPayloadIfNeeded slow (campaign=@campaign_id, actor=@actor, target_room_id=@target_room_id, total_ms=@total_ms, capability_count=@capability_count)',
        [
          '@campaign_id' => (int) ($dungeon_data['campaign_id'] ?? 0),
          '@actor' => trim((string) ($intent['actor'] ?? '')),
          '@target_room_id' => $target_room_id,
          '@total_ms' => round($total_ms, 2),
          '@capability_count' => count((array) ($navigation_receipt['navigation_capabilities'] ?? [])),
        ]
      );
    }
    return $result_payload;
  }

  /**
   * Build canonical navigation payload for transition action responses.
   *
   * @return array<string,mixed>
   *   Navigation receipt consumed by NavigationSystem.js.
   */
  protected function buildTransitionNavigationReceiptPayload(
    array $dungeon_data,
    string $origin_room_id,
    string $target_room_id,
    array $entry_hex
  ): array {
    $navigation_service = \Drupal::service('dungeoncrawler_content.navigation_service');
    if (!($navigation_service instanceof NavigationService)) {
      throw new \RuntimeException('Transition response contract violation: navigation service is unavailable.');
    }

    $target_room = $this->findRoomInDungeon($target_room_id, $dungeon_data);
    if (!is_array($target_room)) {
      throw new \RuntimeException(sprintf(
        'Transition response contract violation: target room %s missing from runtime payload.',
        $target_room_id
      ));
    }

    $navigation_capabilities = $navigation_service
      ->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $target_room_id);

    $receipt = [
      'target_room_id' => $target_room_id,
      'destination' => trim((string) ($target_room['name'] ?? '')) !== ''
        ? (string) $target_room['name']
        : $target_room_id,
      'room' => $target_room,
      'entities' => $this->collectRoomEntitiesForTransitionReceipt($dungeon_data, $target_room_id),
      'connections' => $this->buildTransitionConnectionsFromCapabilities($navigation_capabilities, $target_room_id),
      'navigation_capabilities' => $navigation_capabilities,
      'entry_hex' => [
        'q' => (int) ($entry_hex['q'] ?? 0),
        'r' => (int) ($entry_hex['r'] ?? 0),
      ],
    ];

    if ($origin_room_id !== '') {
      $receipt['origin_room_id'] = $origin_room_id;
    }

    return $receipt;
  }

  /**
   * Resolve entry hex for one transition result payload.
   *
   * Priority:
   * 1. explicit result payload entry_hex
   * 2. actor placement from post-mutation runtime payload
   * 3. intent params target_hex/entry_hex
   * 4. zero origin fallback
   *
   * @return array{q:int,r:int}
   *   Canonical entry hex coordinate.
   */
  protected function resolveTransitionEntryHexForResultPayload(
    array $intent,
    array $action_result,
    array $result_payload,
    array $dungeon_data,
    string $target_room_id
  ): array {
    $target_room_id = trim($target_room_id);
    if (is_array($result_payload['entry_hex'] ?? NULL)) {
      return [
        'q' => (int) ($result_payload['entry_hex']['q'] ?? 0),
        'r' => (int) ($result_payload['entry_hex']['r'] ?? 0),
      ];
    }

    $actor_id = trim((string) ($intent['actor'] ?? ''));
    if ($actor_id !== '') {
      foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
        if (!is_array($entity)) {
          continue;
        }
        $entity_id = $this->resolveEntityIdFromPayload($entity);
        if ($entity_id !== $actor_id) {
          continue;
        }
        $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
        if ($entity_room_id !== $target_room_id) {
          continue;
        }
        if (is_array($entity['placement']['hex'] ?? NULL)) {
          return [
            'q' => (int) ($entity['placement']['hex']['q'] ?? 0),
            'r' => (int) ($entity['placement']['hex']['r'] ?? 0),
          ];
        }
      }
    }

    foreach ((array) ($action_result['mutations'] ?? []) as $mutation) {
      if (!is_array($mutation)) {
        continue;
      }
      if ((string) ($mutation['field'] ?? '') !== 'placement.hex') {
        continue;
      }
      if (is_array($mutation['to'] ?? NULL)) {
        return [
          'q' => (int) ($mutation['to']['q'] ?? 0),
          'r' => (int) ($mutation['to']['r'] ?? 0),
        ];
      }
    }

    $params = is_array($intent['params'] ?? NULL) ? $intent['params'] : [];
    if (is_array($params['target_hex'] ?? NULL)) {
      return [
        'q' => (int) ($params['target_hex']['q'] ?? 0),
        'r' => (int) ($params['target_hex']['r'] ?? 0),
      ];
    }
    if (is_array($params['entry_hex'] ?? NULL)) {
      return [
        'q' => (int) ($params['entry_hex']['q'] ?? 0),
        'r' => (int) ($params['entry_hex']['r'] ?? 0),
      ];
    }

    return ['q' => 0, 'r' => 0];
  }

  /**
   * Collect entities currently placed in one room for transition receipts.
   *
   * @return array<int,array<string,mixed>>
   *   Room-local entity payload rows.
   */
  protected function collectRoomEntitiesForTransitionReceipt(array $dungeon_data, string $room_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return [];
    }

    $entities = [];
    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      if ($entity_room_id === $room_id) {
        $entities[] = $entity;
      }
    }
    return $entities;
  }

  /**
   * Build connection payload rows from navigation capability projections.
   *
   * @param array<int,array<string,mixed>> $capabilities
   *   Navigation capabilities for active room.
   * @param string $active_room_id
   *   Active room id.
   *
   * @return array<int,array<string,mixed>>
   *   Connection payload rows.
   */
  protected function buildTransitionConnectionsFromCapabilities(array $capabilities, string $active_room_id): array {
    $active_room_id = trim($active_room_id);
    $connections = [];

    foreach ($capabilities as $capability) {
      if (!is_array($capability)) {
        continue;
      }
      $target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
      if ($target_room_id === '') {
        continue;
      }

      $origin_room_id = trim((string) ($capability['origin_room_id'] ?? $active_room_id));
      if ($origin_room_id === '') {
        $origin_room_id = $active_room_id;
      }

      $connection_id = trim((string) ($capability['connection_id'] ?? ''));
      if ($connection_id === '') {
        $connection_id = sprintf('receipt-%s-%s', $origin_room_id, $target_room_id);
      }

      $available = !array_key_exists('available', $capability) || !empty($capability['available']);
      $connection = [
        'connection_id' => $connection_id,
        'from_room' => $origin_room_id,
        'to_room' => $target_room_id,
        'target_room_id' => $target_room_id,
        'available' => $available,
        'blocked' => !$available,
        'blocked_reason' => $available ? '' : (string) ($capability['blocked_reason'] ?? 'blocked'),
        'type' => (string) ($capability['type'] ?? $capability['connection_type'] ?? 'passage'),
      ];
      if (is_array($capability['origin_hex'] ?? NULL)) {
        $connection['from_hex'] = [
          'q' => (int) ($capability['origin_hex']['q'] ?? 0),
          'r' => (int) ($capability['origin_hex']['r'] ?? 0),
        ];
      }
      if (is_array($capability['target_hex'] ?? NULL)) {
        $connection['to_hex'] = [
          'q' => (int) ($capability['target_hex']['q'] ?? 0),
          'r' => (int) ($capability['target_hex']['r'] ?? 0),
        ];
      }
      $connections[] = $connection;
    }

    return $connections;
  }

  /**
   * Builds narrator audio for a room entry event.
   */
  protected function buildRoomEntryNarrationAudio(array $room, ?string $narration_text = NULL): ?array {
    if (!$this->textToSpeechIntegration || !$this->fileUrlGenerator) {
      return NULL;
    }

    $narration_text = trim((string) $narration_text);
    $description = trim((string) ($room['description'] ?? ''));
    $text = $narration_text !== ''
      ? $narration_text
      : trim(sprintf('%s. %s', (string) ($room['name'] ?? 'New area'), $description));

    if ($text === '') {
      return NULL;
    }

    $result = $this->textToSpeechIntegration->synthesizeSpeech($text, [
      'voice_name' => self::ROOM_ENTRY_NARRATOR_VOICE,
      'audio_encoding' => 'MP3',
      'speaking_rate' => self::ROOM_ENTRY_NARRATOR_SPEAKING_RATE,
      'pitch' => self::ROOM_ENTRY_NARRATOR_PITCH,
      'volume_gain_db' => self::ROOM_ENTRY_NARRATOR_VOLUME_GAIN_DB,
    ]);
    if (empty($result['success'])) {
      $this->logger->warning('Startup room narration synthesis failed for %room: %message', [
        '%room' => (string) ($room['name'] ?? $room['room_id'] ?? 'unknown room'),
        '%message' => (string) ($result['message'] ?? 'Unknown synthesis error'),
      ]);
      return NULL;
    }

    $stored = $this->textToSpeechIntegration->storeAudioResult($result, 'public://forseti-tts-room-entry');
    if (empty($stored['success']) || empty($stored['uri'])) {
      $this->logger->warning('Startup room narration storage failed for %room: %message', [
        '%room' => (string) ($room['name'] ?? $room['room_id'] ?? 'unknown room'),
        '%message' => (string) ($stored['message'] ?? 'Unknown storage error'),
      ]);
      return NULL;
    }

    return [
      'narration_audio_url' => $this->fileUrlGenerator->generateString((string) $stored['uri']),
      'narration_audio_uri' => (string) ($stored['uri']),
      'narration_audio_text' => $text,
      'narration_audio_voice' => self::ROOM_ENTRY_NARRATOR_VOICE,
      'narration_audio_speaking_rate' => self::ROOM_ENTRY_NARRATOR_SPEAKING_RATE,
      'narration_audio_pitch' => self::ROOM_ENTRY_NARRATOR_PITCH,
      'narration_audio_volume_gain_db' => self::ROOM_ENTRY_NARRATOR_VOLUME_GAIN_DB,
      'narration_audio_source' => $narration_text !== '' ? 'gm_narration' : 'room_description',
    ];
  }

  /**
   * Returns unseen initial events and advances the cursor to the latest event ID.
   *
   * @return array<int, array<string, mixed>>
   *   Events the client has not yet received from initial state.
   */
  protected function collectUnseenInitialEvents(array $dungeon_data, array &$game_state, bool $advance_cursor = TRUE): array {
    $campaign_id = (int) ($dungeon_data['campaign_id'] ?? 0);
    $latest_event_id = $this->eventLogger->getLatestCursor(
      $dungeon_data,
      $campaign_id > 0 ? $campaign_id : NULL
    );

    $cursor = (int) ($game_state['event_log_cursor'] ?? 0);
    if ($latest_event_id <= $cursor) {
      return [];
    }

    $events = $this->eventLogger->getEventsSince(
      $dungeon_data,
      $cursor,
      $campaign_id > 0 ? $campaign_id : NULL
    );
    if ($advance_cursor) {
      $game_state['event_log_cursor'] = $latest_event_id;
    }
    return $events;
  }

  /**
   * Build a typed mutation envelope from current payload context.
   *
   * @return array<string,mixed>
   *   Mutation envelope for mutation services.
   */
  protected function buildMutationEnvelopeFromPayload(
    int $campaign_id,
    array $game_state,
    array $dungeon_data,
    bool $include_non_game_state_mutations
  ): array {
    $envelope = [
      'campaign_id' => $campaign_id,
      'active_room_id' => (string) ($dungeon_data['active_room_id'] ?? ''),
      'campaign_state' => $game_state,
      'actor_entities' => [],
      'rooms' => [],
      'connections' => [],
    ];
    if ($include_non_game_state_mutations) {
      $envelope['actor_entities'] = is_array($dungeon_data['entities'] ?? NULL) ? $dungeon_data['entities'] : [];
      $envelope['rooms'] = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
      $envelope['connections'] = $this->collectRuntimeConnectionsFromPayload($dungeon_data);
    }
    return $envelope;
  }

  /**
   * Resolve the mutation envelope to persist for one runtime write.
   *
   * @param array<string,mixed>|null $candidate_envelope
   *   Handler-provided candidate envelope.
   * @param array<string,string>|null $before_slice_fingerprints
   *   Optional pre-mutation fingerprints for actor_entities/rooms/connections.
   *
   * @return array<string,mixed>
   *   Normalized envelope.
   */
  protected function resolveMutationEnvelopeForPersistence(
    int $campaign_id,
    ?array $candidate_envelope,
    array $game_state,
    array $dungeon_data,
    ?array $before_slice_fingerprints = NULL
  ): array {
    $slice_aliases = [
      'actor_entities' => 'entities',
      'rooms' => 'rooms',
      'connections' => 'connections',
    ];
    $after_slice_fingerprints = $before_slice_fingerprints !== NULL
      ? $this->computeRuntimeSliceFingerprints($dungeon_data)
      : NULL;
    $changed_slices = [];
    if ($before_slice_fingerprints !== NULL && $after_slice_fingerprints !== NULL) {
      foreach ($slice_aliases as $slice_key => $_ignored_alias) {
        $before = (string) ($before_slice_fingerprints[$slice_key] ?? '');
        $after = (string) ($after_slice_fingerprints[$slice_key] ?? '');
        if ($before !== '' && $after !== '' && $before !== $after) {
          $changed_slices[] = $slice_key;
        }
      }
    }
    $non_game_state_changed = $changed_slices !== [];

    $assertExplicitSlices = function (array $envelope) use ($changed_slices, $slice_aliases, $campaign_id): void {
      foreach ($changed_slices as $slice_key) {
        $slice_payload = is_array($envelope[$slice_key] ?? NULL) ? $envelope[$slice_key] : [];
        if ($slice_payload === []) {
          throw new \RuntimeException(sprintf(
            'Mutation envelope contract violation: %s payload changed without explicit typed "%s" slice for campaign %d.',
            (string) ($slice_aliases[$slice_key] ?? $slice_key),
            $slice_key,
            $campaign_id
          ));
        }
      }
    };

    if (!is_array($candidate_envelope) || $candidate_envelope === []) {
      if ($non_game_state_changed) {
        throw new \RuntimeException(sprintf(
          'Mutation envelope contract violation: non-game-state payload changed without explicit typed slices for campaign %d.',
          $campaign_id
        ));
      }
      return $this->buildMutationEnvelopeFromPayload(
        $campaign_id,
        $game_state,
        $dungeon_data,
        FALSE
      );
    }

    $envelope_campaign_id = (int) ($candidate_envelope['campaign_id'] ?? $campaign_id);
    if ($envelope_campaign_id !== $campaign_id) {
      throw new \RuntimeException(sprintf(
        'Mutation envelope contract violation: campaign_id mismatch (expected=%d, got=%d).',
        $campaign_id,
        $envelope_campaign_id
      ));
    }
    if (!array_key_exists('campaign_state', $candidate_envelope) || !is_array($candidate_envelope['campaign_state'])) {
      throw new \RuntimeException(sprintf(
        'Mutation envelope contract violation: campaign_state must be an array for campaign %d.',
        $campaign_id
      ));
    }
    foreach (['actor_entities', 'rooms', 'connections'] as $collection_key) {
      if (array_key_exists($collection_key, $candidate_envelope) && !is_array($candidate_envelope[$collection_key])) {
        throw new \RuntimeException(sprintf(
          'Mutation envelope contract violation: %s must be an array for campaign %d.',
          $collection_key,
          $campaign_id
        ));
      }
    }

    $normalized = [
      'campaign_id' => $campaign_id,
      'active_room_id' => (string) (
        $candidate_envelope['active_room_id']
        ?? $dungeon_data['active_room_id']
        ?? ''
      ),
      'campaign_state' => $game_state,
      'actor_entities' => is_array($candidate_envelope['actor_entities'] ?? NULL)
        ? $candidate_envelope['actor_entities']
        : [],
      'rooms' => is_array($candidate_envelope['rooms'] ?? NULL)
        ? $candidate_envelope['rooms']
        : [],
      'connections' => is_array($candidate_envelope['connections'] ?? NULL)
        ? $candidate_envelope['connections']
        : [],
    ];

    // Strict contract: changed runtime slices must be carried explicitly in
    // the typed mutation envelope produced by handlers.
    if ($non_game_state_changed && $normalized['actor_entities'] === [] && $normalized['rooms'] === [] && $normalized['connections'] === []) {
      throw new \RuntimeException(sprintf(
        'Mutation envelope contract violation: non-game-state payload changed without explicit typed slices for campaign %d.',
        $campaign_id
      ));
    }

    if ($non_game_state_changed) {
      $assertExplicitSlices($normalized);
    }

    return $normalized;
  }

  /**
   * Normalize one handler processIntent() result payload.
   *
   * @param mixed $raw_result
   *   Raw handler result.
   *
   * @return array<string,mixed>
   *   Normalized action result contract.
   */
  protected function normalizeHandlerActionResult(mixed $raw_result, int $campaign_id, string $phase): array {
    if (!is_array($raw_result)) {
      throw new \RuntimeException(sprintf(
        'Phase handler contract violation: %s::processIntent must return an array (campaign=%d).',
        $phase,
        $campaign_id
      ));
    }

    $result = $raw_result;
    foreach (['result', 'mutations', 'events', 'time_effects'] as $array_key) {
      if (array_key_exists($array_key, $result) && !is_array($result[$array_key])) {
        throw new \RuntimeException(sprintf(
          'Phase handler contract violation: %s::processIntent key "%s" must be an array (campaign=%d).',
          $phase,
          $array_key,
          $campaign_id
        ));
      }
    }
    if (array_key_exists('success', $result) && !is_bool($result['success'])) {
      throw new \RuntimeException(sprintf(
        'Phase handler contract violation: %s::processIntent key "success" must be a boolean (campaign=%d).',
        $phase,
        $campaign_id
      ));
    }
    if (array_key_exists('phase_transition', $result) && $result['phase_transition'] !== NULL) {
      if (!is_array($result['phase_transition'])) {
        throw new \RuntimeException(sprintf(
          'Phase handler contract violation: %s::processIntent key "phase_transition" must be an array or null (campaign=%d).',
          $phase,
          $campaign_id
        ));
      }
      $target_phase = trim((string) ($result['phase_transition']['to'] ?? ''));
      if ($target_phase === '') {
        throw new \RuntimeException(sprintf(
          'Phase handler contract violation: %s::processIntent phase_transition.to is required when phase_transition is present (campaign=%d).',
          $phase,
          $campaign_id
        ));
      }
    }
    if (array_key_exists('mutation_envelope', $result) && $result['mutation_envelope'] !== NULL && !is_array($result['mutation_envelope'])) {
      throw new \RuntimeException(sprintf(
        'Phase handler contract violation: %s::processIntent key "mutation_envelope" must be an array or null (campaign=%d).',
        $phase,
        $campaign_id
      ));
    }

    $result['success'] = array_key_exists('success', $result) ? (bool) $result['success'] : TRUE;
    $result['result'] = is_array($result['result'] ?? NULL) ? $result['result'] : [];
    $result['mutations'] = is_array($result['mutations'] ?? NULL) ? $result['mutations'] : [];
    $result['events'] = is_array($result['events'] ?? NULL) ? $result['events'] : [];
    $result['time_effects'] = is_array($result['time_effects'] ?? NULL) ? $result['time_effects'] : [];
    $result['phase_transition'] = is_array($result['phase_transition'] ?? NULL) ? $result['phase_transition'] : NULL;
    $result['mutation_envelope'] = is_array($result['mutation_envelope'] ?? NULL) ? $result['mutation_envelope'] : NULL;

    return $result;
  }

  /**
   * Normalize onEnter()/onExit() lifecycle hook returns.
   *
   * Supports legacy event-list returns and newer structured envelopes.
   *
   * @param mixed $raw_result
   *   Raw hook result.
   *
   * @return array{events: array<int,array<string,mixed>>, mutation_envelope: ?array<string,mixed>}
   *   Normalized lifecycle result.
   */
  protected function normalizePhaseLifecycleResult(mixed $raw_result, string $phase, string $hook, int $campaign_id): array {
    if (!is_array($raw_result)) {
      throw new \RuntimeException(sprintf(
        'Phase lifecycle contract violation: %s::%s must return an array (campaign=%d).',
        $phase,
        $hook,
        $campaign_id
      ));
    }

    $has_structured_keys = array_key_exists('events', $raw_result) || array_key_exists('mutation_envelope', $raw_result);
    $events = $has_structured_keys
      ? (is_array($raw_result['events'] ?? NULL) ? $raw_result['events'] : [])
      : $raw_result;
    $mutation_envelope = $has_structured_keys ? ($raw_result['mutation_envelope'] ?? NULL) : NULL;

    if ($has_structured_keys && array_key_exists('events', $raw_result) && !is_array($raw_result['events'])) {
      throw new \RuntimeException(sprintf(
        'Phase lifecycle contract violation: %s::%s key "events" must be an array (campaign=%d).',
        $phase,
        $hook,
        $campaign_id
      ));
    }
    if ($mutation_envelope !== NULL && !is_array($mutation_envelope)) {
      throw new \RuntimeException(sprintf(
        'Phase lifecycle contract violation: %s::%s key "mutation_envelope" must be an array or null (campaign=%d).',
        $phase,
        $hook,
        $campaign_id
      ));
    }

    foreach ($events as $event_index => $event) {
      if (!is_array($event)) {
        throw new \RuntimeException(sprintf(
          'Phase lifecycle contract violation: %s::%s event at index %d must be an array (campaign=%d).',
          $phase,
          $hook,
          (int) $event_index,
          $campaign_id
        ));
      }
    }

    return [
      'events' => array_values($events),
      'mutation_envelope' => is_array($mutation_envelope) ? $mutation_envelope : NULL,
    ];
  }

  /**
   * Persist a typed mutation envelope through mutation services.
   *
   * @param array<string,mixed> $mutation_envelope
   *   Mutation envelope.
   */
  protected function persistMutationEnvelope(array $mutation_envelope): bool {
    $campaign_id = (int) ($mutation_envelope['campaign_id'] ?? 0);
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Mutation envelope contract violation: campaign_id must be > 0.');
    }

    $campaign_state = is_array($mutation_envelope['campaign_state'] ?? NULL)
      ? $mutation_envelope['campaign_state']
      : NULL;
    if ($campaign_state === NULL) {
      throw new \RuntimeException(sprintf(
        'Mutation envelope contract violation: campaign_state is required for campaign %d.',
        $campaign_id
      ));
    }

    $active_room_id = trim((string) ($mutation_envelope['active_room_id'] ?? ''));
    if (!$this->persistGameStateSlice(
      $campaign_id,
      $campaign_state,
      $active_room_id !== '' ? $active_room_id : NULL
    )) {
      throw new \RuntimeException(sprintf(
        'Mutation envelope contract violation: failed to persist campaign_state for campaign %d.',
        $campaign_id
      ));
    }

    $actor_entities = is_array($mutation_envelope['actor_entities'] ?? NULL)
      ? $mutation_envelope['actor_entities']
      : [];
    if ($actor_entities !== []) {
      $this->actorRuntimeMutationService->persistEntities($campaign_id, $actor_entities);
    }

    $rooms = is_array($mutation_envelope['rooms'] ?? NULL) ? $mutation_envelope['rooms'] : [];
    if ($rooms !== []) {
      $this->roomRuntimeMutationService->persistRooms($campaign_id, $rooms);
    }

    $connections = is_array($mutation_envelope['connections'] ?? NULL)
      ? $mutation_envelope['connections']
      : [];
    if ($connections !== []) {
      $this->connectionRuntimeMutationService->persistConnections($campaign_id, $connections);
    }

    return TRUE;
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Gets the phase handler for a given phase name.
   */
  protected function getPhaseHandler(string $phase): ?PhaseHandlerInterface {
    if (in_array($phase, self::DEPRECATED_PHASES, TRUE)) {
      return NULL;
    }
    return $this->phaseHandlers[$phase] ?? NULL;
  }

  /**
   * Builds a client-safe game state payload (strips internal data).
   */
  protected function buildClientGameState(array $game_state): array {
    return [
      'phase' => $game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE,
      'session_id' => $game_state['session_id'] ?? NULL,
      'active_room_id' => $game_state['active_room_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'turn' => $game_state['turn'] ?? NULL,
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'encounter_context' => $game_state['encounter_context'] ?? NULL,
      'initiative_order' => $game_state['initiative_order'] ?? NULL,
      'campaign_clock' => $game_state['campaign_clock'] ?? NULL,
      'game_time' => $game_state['game_time'] ?? NULL,
      'timed_activities' => $game_state['timed_activities'] ?? [],
      'state_version' => $game_state['state_version'] ?? 1,
      'event_log_cursor' => $game_state['event_log_cursor'] ?? 0,
      'last_encounter' => $game_state['last_encounter'] ?? NULL,
    ];
  }

  /**
   * Builds a standardized error response.
   */
  protected function errorResponse(string $message, ?array $game_state = NULL): array {
    return [
      'success' => FALSE,
      'error' => $message,
      'game_state' => $game_state ? $this->buildClientGameState($game_state) : NULL,
      'active_room_id' => $game_state['active_room_id'] ?? NULL,
      'result' => [],
      'mutations' => [],
      'events' => [],
      'phase_transition' => NULL,
      'narration' => NULL,
      'available_actions' => [],
      'action_contract' => NULL,
      'state_version' => $game_state['state_version'] ?? NULL,
    ];
  }

  /**
   * Build full-state response from current runtime context.
   *
   * @param bool $materialize_initial_room_entry
   *   TRUE to allow bootstrap/materialization writes for compatibility lanes.
   */
  protected function buildFullStateResponse(
    int $campaign_id,
    array &$dungeon_data,
    array &$game_state,
    bool $materialize_initial_room_entry
  ): array {
    $initial_events = [];
    if ($materialize_initial_room_entry) {
      $had_game_state = isset($dungeon_data['game_state']) && is_array($dungeon_data['game_state']);
      $bootstrap_events = $this->bootstrapInitialRoomEntry($campaign_id, $dungeon_data, $game_state);
      if ($bootstrap_events !== []) {
        $game_state['event_log_cursor'] = max(array_map(
          static fn (array $event): int => (int) ($event['id'] ?? 0),
          $bootstrap_events
        ));
      }
      $this->synchronizeActiveRoomAuthority($game_state, $dungeon_data);
      $initial_events = $bootstrap_events !== []
        ? $bootstrap_events
        : $this->collectUnseenInitialEvents($dungeon_data, $game_state, FALSE);

      if ($bootstrap_events !== []) {
        $this->persistMutationEnvelope($this->buildMutationEnvelopeFromPayload(
          $campaign_id,
          $game_state,
          $dungeon_data,
          TRUE
        ));
      }
      elseif (!$had_game_state) {
        $this->persistMutationEnvelope($this->buildMutationEnvelopeFromPayload(
          $campaign_id,
          $game_state,
          $dungeon_data,
          FALSE
        ));
      }
    }
    else {
      $this->synchronizeActiveRoomAuthority($game_state, $dungeon_data);
      $initial_events = $this->collectUnseenInitialEvents($dungeon_data, $game_state, FALSE);
    }

    $phase = $game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE;
    $handler = $this->getPhaseHandler($phase);
    $action_contract = $this->buildActionContract($handler, $game_state, $dungeon_data);

    return [
      'success' => TRUE,
      'game_state' => $this->buildClientGameState($game_state),
      'phase' => $phase,
      'available_actions' => $handler
        ? $handler->getAvailableActions($game_state, $dungeon_data)
        : [],
      'action_contract' => $action_contract,
      'legal_intents' => $handler ? $handler->getLegalIntents() : [],
      'state_version' => $game_state['state_version'] ?? 1,
      'active_room_id' => $dungeon_data['active_room_id'] ?? NULL,
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'turn' => $game_state['turn'] ?? NULL,
      'events' => $initial_events,
    ];
  }

  /**
   * Resolve shared action-availability context for actor-scoped queries.
   *
   * @return array<string, mixed>|null
   *   Context payload with dungeon_data, game_state, and phase handler; NULL
   *   when dungeon data is unavailable.
   */
  protected function resolveActionAvailabilityContext(int $campaign_id, ?string $actor_id = NULL): ?array {
    $context = $this->coordinatorRuntimeReadService->resolveActionAvailabilityContext($campaign_id, $actor_id);
    if ($context === NULL) {
      return NULL;
    }
    $phase = $context['game_state']['phase'] ?? self::DEFAULT_ACTIVE_PHASE;

    return [
      'dungeon_data' => $context['dungeon_data'],
      'game_state' => $context['game_state'],
      'handler' => $this->getPhaseHandler($phase),
    ];
  }

  /**
   * Build an empty actor-scoped action-availability payload.
   *
   * @return array{available_actions: string[], action_contract: null}
   *   Empty payload used when availability cannot be resolved.
   */
  protected function emptyActionAvailabilityPayload(): array {
    return [
      'available_actions' => [],
      'action_contract' => NULL,
    ];
  }

  /**
   * Build an explicit client action contract when the phase handler supports it.
   */
  protected function buildActionContract(?PhaseHandlerInterface $handler, array $game_state, array $dungeon_data, ?string $actor_id = NULL): ?array {
    if ($handler !== NULL && method_exists($handler, 'getClientActionContract')) {
      return $handler->getClientActionContract($game_state, $dungeon_data, $actor_id);
    }

    return NULL;
  }

  /**
   * Build compact runtime snapshot payload from current request context.
   *
   * @return array<string,mixed>
   *   Runtime snapshot object suitable for transition/action consumers.
   */
  protected function buildRuntimeSnapshotPayload(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    return $this->runtimeStateReadModelAssembler->buildRuntimeSnapshotPayload(
      $game_state,
      $dungeon_data,
      $this->buildClientGameState($game_state),
      $actor_id
    );
  }

  /**
   * Enforce deny-by-default capability boundary for gameplay intents.
   *
   * Returns a violation code when the intent payload attempts to include
   * command/system capability hints that are outside canonical gameplay scope.
   */
  protected function validateActorCapabilityBoundary(array $intent): ?string {
    $params = $intent['params'] ?? [];
    if ($params !== [] && !is_array($params)) {
      return 'intent_params_not_object';
    }

    $intent_type = strtolower(trim((string) ($intent['type'] ?? '')));
    if ($intent_type !== '' && in_array($intent_type, self::FORBIDDEN_INTENT_CAPABILITY_KEYS, TRUE)) {
      return 'forbidden_intent_type_' . $intent_type;
    }

    $forbidden_key_path = $this->findForbiddenCapabilityKeyPath($intent, 'intent');
    if (is_string($forbidden_key_path) && $forbidden_key_path !== '') {
      return 'forbidden_capability_key_' . $forbidden_key_path;
    }

    return NULL;
  }

  /**
   * Find the first forbidden capability key path in an intent payload.
   */
  protected function findForbiddenCapabilityKeyPath(array $payload, string $path_prefix): ?string {
    foreach ($payload as $key => $value) {
      $normalized_key = strtolower(trim((string) $key));
      $current_path = $path_prefix . '.' . $normalized_key;
      if (in_array($normalized_key, self::FORBIDDEN_INTENT_CAPABILITY_KEYS, TRUE)) {
        return $current_path;
      }
      if (is_array($value)) {
        $nested_path = $this->findForbiddenCapabilityKeyPath($value, $current_path);
        if (is_string($nested_path) && $nested_path !== '') {
          return $nested_path;
        }
      }
    }

    return NULL;
  }

  /**
   * Resolve runtime actor entity id from a payload entity row.
   */
  protected function resolveEntityIdFromPayload(array $entity): string {
    return trim((string) (
      $entity['entity_instance_id']
      ?? $entity['instance_id']
      ?? $entity['id']
      ?? ''
    ));
  }

  /**
   * Resolve one actor's current room id from actor runtime slice rows.
   */
  protected function resolveActorRoomIdFromRuntimeStore(int $campaign_id, ?string $actor_id = NULL): ?string {
    $actor_id = trim((string) $actor_id);
    if ($actor_id === '') {
      return NULL;
    }
    foreach ($this->actorRuntimeStateStore->loadActorEntities($campaign_id) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      if ($this->resolveEntityIdFromPayload($entity) !== $actor_id) {
        continue;
      }
      $room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      return $room_id !== '' ? $room_id : NULL;
    }
    return NULL;
  }

}
