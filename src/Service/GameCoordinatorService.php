<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Game Coordinator Service — the central orchestrator ("main()").
 *
 * This is the single entry point for all game actions. It manages:
 * - Game phase state machine (exploration / encounter / downtime)
 * - Action validation and routing to the active phase handler
 * - Phase transitions (with onExit/onEnter lifecycle)
 * - Event logging for every action
 * - Dungeon data persistence
 * - State version tracking for optimistic concurrency
 *
 * Design principles:
 * 1. Server-authoritative: the server owns the game phase and all transitions.
 * 2. Phase-driven: delegates to the active PhaseHandler via strategy pattern.
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

  /**
   * Default game state structure for new sessions.
   */
  const DEFAULT_GAME_STATE = [
    'phase' => 'exploration',
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
    'downtime' => NULL,
    'timed_activities' => [],
    'state_version' => 1,
    'event_log_cursor' => 0,
    'last_encounter' => NULL,
  ];

  /**
   * Valid phase transitions.
   *
   * @var array
   */
  const VALID_TRANSITIONS = [
    'exploration' => ['encounter', 'downtime'],
    'encounter' => ['exploration'],
    'downtime' => ['exploration'],
  ];

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  protected CampaignCharacterRuntimeSyncService $campaignCharacterRuntimeSync;

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
    ExplorationPhaseHandler $exploration_handler,
    EncounterPhaseHandler $encounter_handler,
    DowntimePhaseHandler $downtime_handler,
    AiGmService $ai_gm_service,
    CampaignTimeResolverService $campaign_time_resolver,
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
    $this->narrationEngine = $narration_engine;
    $this->textToSpeechIntegration = $text_to_speech_integration;
    $this->fileUrlGenerator = $file_url_generator;

    // Register phase handlers by their phase name.
    $this->phaseHandlers['exploration'] = $exploration_handler;
    $this->phaseHandlers['encounter'] = $encounter_handler;
    $this->phaseHandlers['downtime'] = $downtime_handler;
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
    $actor_id = (string) ($intent['actor'] ?? '');

    // 1. Load dungeon data and game state.
    $dungeon_data = $this->loadDungeonData($campaign_id, $actor_id !== '' ? $actor_id : NULL);
    if (!$dungeon_data) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $game_state = $this->ensureGameState($dungeon_data);
    $phase = $game_state['phase'] ?? 'exploration';

    // 2. Optimistic concurrency check.
    $client_version = $intent['client_state_version'] ?? NULL;
    if ($client_version !== NULL && $client_version !== ($game_state['state_version'] ?? 0)) {
      return $this->errorResponse(
        'State version mismatch. Expected ' . ($game_state['state_version'] ?? 0) . ', got ' . $client_version . '. Refresh state.',
        $game_state
      );
    }

    // 3. Get the active phase handler.
    $handler = $this->getPhaseHandler($phase);
    if (!$handler) {
      return $this->errorResponse("No handler for phase: $phase", $game_state);
    }

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
    $action_result = $handler->processIntent($intent, $game_state, $dungeon_data, $campaign_id);
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
    $logged_events = [];
    if (!empty($events_to_log)) {
      $logged_events = $this->eventLogger->logEvents($dungeon_data, $events_to_log);
    }

    // 7. Handle phase transitions.
    $phase_transition = $action_result['phase_transition'] ?? NULL;
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
    }

    // 8. Increment state version.
    $game_state['state_version'] = ($game_state['state_version'] ?? 0) + 1;

    // 9. Persist the updated dungeon data.
    $dungeon_data['game_state'] = $game_state;
    $this->persistDungeonData($campaign_id, $dungeon_data);

    // 10. Build response.
    $current_phase = $game_state['phase'] ?? 'exploration';
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
      catch (\Exception $e) {
        $this->logger->warning('NarrationEngine flush failed: @err', ['@err' => $e->getMessage()]);
      }
    }

    return [
      'success' => $action_result['success'] ?? TRUE,
      'game_state' => $this->buildClientGameState($game_state),
      'result' => $action_result['result'] ?? [],
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
      'error' => NULL,
    ];
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
    $dungeon_data = $this->loadDungeonData($campaign_id);
    if (!$dungeon_data) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $had_game_state = isset($dungeon_data['game_state']) && is_array($dungeon_data['game_state']);
    $game_state = $this->ensureGameState($dungeon_data);
    $bootstrap_events = $this->bootstrapInitialRoomEntry($campaign_id, $dungeon_data, $game_state);
    if ($bootstrap_events !== []) {
      $game_state['event_log_cursor'] = max(array_map(
        static fn (array $event): int => (int) ($event['id'] ?? 0),
        $bootstrap_events
      ));
    }
    $initial_events = $bootstrap_events !== []
      ? $bootstrap_events
      : $this->collectUnseenInitialEvents($dungeon_data, $game_state);
    if (!$had_game_state || $bootstrap_events !== [] || $initial_events !== []) {
      $dungeon_data['game_state'] = $game_state;
      $this->persistDungeonData($campaign_id, $dungeon_data);
    }
    $phase = $game_state['phase'] ?? 'exploration';
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
      'exploration' => $game_state['exploration'] ?? NULL,
      'events' => $initial_events,
    ];
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
    $dungeon_data = $this->loadDungeonData($campaign_id, $actor_id);
    if (!$dungeon_data) {
      return [];
    }

    $game_state = $this->ensureGameState($dungeon_data);
    $phase = $game_state['phase'] ?? 'exploration';
    $handler = $this->getPhaseHandler($phase);

    return $handler ? $handler->getAvailableActions($game_state, $dungeon_data, $actor_id) : [];
  }

  /**
   * Manually transition to a new phase.
   *
   * Used for explicit transitions like: start combat, enter downtime, return
   * to exploration. Most transitions happen automatically (e.g., encounter
   * triggered on room entry), but this endpoint allows manual transitions too.
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
    $dungeon_data = $this->loadDungeonData($campaign_id);
    if (!$dungeon_data) {
      return $this->errorResponse('Campaign dungeon data not found.');
    }

    $game_state = $this->ensureGameState($dungeon_data);
    $current_phase = $game_state['phase'] ?? 'exploration';

    // Validate the transition.
    $valid_targets = self::VALID_TRANSITIONS[$current_phase] ?? [];
    if (!in_array($target_phase, $valid_targets)) {
      return $this->errorResponse(
        "Cannot transition from '$current_phase' to '$target_phase'. Valid targets: " . implode(', ', $valid_targets),
        $game_state
      );
    }

    $context['from_phase'] = $current_phase;

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
    $dungeon_data['game_state'] = $game_state;
    $this->persistDungeonData($campaign_id, $dungeon_data);

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
    $dungeon_data = $this->loadDungeonData($campaign_id);
    if (!$dungeon_data) {
      return ['success' => FALSE, 'events' => [], 'error' => 'Dungeon data not found.'];
    }

    $events = $this->eventLogger->getEventsSince($dungeon_data, $since_cursor);

    return [
      'success' => TRUE,
      'events' => $events,
      'cursor' => !empty($events) ? end($events)['id'] : $since_cursor,
      'state_version' => $dungeon_data['game_state']['state_version'] ?? 1,
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

    // 1. Exit the current phase.
    $from_handler = $this->getPhaseHandler($from_phase);
    if ($from_handler) {
      $exit_events = $from_handler->onExit($game_state, $dungeon_data, $campaign_id);
      $all_events = array_merge($all_events, $exit_events);
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
      catch (\Exception $e) {
        $this->logger->warning('NarrationEngine queue failed during phase transition: @err', ['@err' => $e->getMessage()]);
      }
    }

    // 3. Enter the new phase.
    $to_handler = $this->getPhaseHandler($to_phase);
    if ($to_handler) {
      $enter_events = $to_handler->onEnter($context, $game_state, $dungeon_data, $campaign_id);
      $all_events = array_merge($all_events, $enter_events);
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

    return ['events' => $all_events];
  }

  // =========================================================================
  // Data access.
  // =========================================================================

  /**
   * Loads dungeon_data from the database.
   */
  protected function loadDungeonData(int $campaign_id, ?string $preferred_actor_id = NULL): ?array {
    try {
      $row = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_data'])
        ->condition('d.campaign_id', $campaign_id)
        ->execute()
        ->fetchField();

      if ($row) {
        $decoded = json_decode($row, TRUE) ?: NULL;
        if (is_array($decoded)) {
          return $this->campaignCharacterRuntimeSync->syncActiveRoomPlayerEntities($decoded, $campaign_id, $preferred_actor_id);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load dungeon data for campaign @id: @error', [
        '@id' => $campaign_id,
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Persists dungeon_data to the database.
   */
  protected function persistDungeonData(int $campaign_id, array $dungeon_data): bool {
    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist dungeon data for campaign @id: @error', [
        '@id' => $campaign_id,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
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

    return $dungeon_data['game_state'];
  }

  /**
   * Bootstraps a one-time initial room-entered event for fresh campaigns.
   *
   * @return array<int, array<string, mixed>>
   *   Newly created events, if any.
   */
  protected function bootstrapInitialRoomEntry(int $campaign_id, array &$dungeon_data, array &$game_state): array {
    if (!empty($dungeon_data['event_log'])) {
      return [];
    }

    $room_id = $this->resolveStartupRoomId($dungeon_data);
    if ($room_id === NULL) {
      return [];
    }

    $room_data = $this->findRoomInDungeon($room_id, $dungeon_data);
    if ($room_data === NULL) {
      return [];
    }

    $narration = $this->aiGmService->narrateRoomEntry($room_data, $dungeon_data, TRUE, $campaign_id);
    $room_entered_data = [
      'from_room' => NULL,
      'to_room' => $room_id,
    ];
    $room_entry_audio = $this->buildRoomEntryNarrationAudio($room_data, $narration);
    if ($room_entry_audio !== NULL) {
      $room_entered_data += $room_entry_audio;
    }

    $game_state['exploration']['previous_room'] = $game_state['exploration']['previous_room'] ?? NULL;

    return $this->eventLogger->logEvents($dungeon_data, [
      GameEventLogger::buildEvent('room_entered', 'exploration', NULL, $room_entered_data, $narration),
    ]);
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
  protected function collectUnseenInitialEvents(array $dungeon_data, array &$game_state): array {
    $latest_event_id = 0;
    $event_log = $dungeon_data['event_log'] ?? [];
    if ($event_log !== []) {
      $last_event = end($event_log);
      $latest_event_id = (int) ($last_event['id'] ?? 0);
    }

    $cursor = (int) ($game_state['event_log_cursor'] ?? 0);
    if ($latest_event_id <= $cursor) {
      return [];
    }

    $events = $this->eventLogger->getEventsSince($dungeon_data, $cursor);
    $game_state['event_log_cursor'] = $latest_event_id;
    return $events;
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Gets the phase handler for a given phase name.
   */
  protected function getPhaseHandler(string $phase): ?PhaseHandlerInterface {
    return $this->phaseHandlers[$phase] ?? NULL;
  }

  /**
   * Builds a client-safe game state payload (strips internal data).
   */
  protected function buildClientGameState(array $game_state): array {
    return [
      'phase' => $game_state['phase'] ?? 'exploration',
      'session_id' => $game_state['session_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'turn' => $game_state['turn'] ?? NULL,
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'initiative_order' => $game_state['initiative_order'] ?? NULL,
      'exploration' => $game_state['exploration'] ?? NULL,
      'downtime' => $game_state['downtime'] ?? NULL,
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
   * Build an explicit client action contract when the phase handler supports it.
   */
  protected function buildActionContract(?PhaseHandlerInterface $handler, array $game_state, array $dungeon_data, ?string $actor_id = NULL): ?array {
    if ($handler !== NULL && method_exists($handler, 'getClientActionContract')) {
      return $handler->getClientActionContract($game_state, $dungeon_data, $actor_id);
    }

    return NULL;
  }

}
