<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
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
   * Constructs a GameCoordinatorService.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    GameEventLogger $event_logger,
    ExplorationPhaseHandler $exploration_handler,
    EncounterPhaseHandler $encounter_handler,
    DowntimePhaseHandler $downtime_handler,
    AiGmService $ai_gm_service,
    ?NarrationEngine $narration_engine = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->eventLogger = $event_logger;
    $this->aiGmService = $ai_gm_service;
    $this->narrationEngine = $narration_engine;

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
    // 1. Load dungeon data and game state.
    $dungeon_data = $this->loadDungeonData($campaign_id);
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
    $action_result = $handler->processIntent($intent, $game_state, $dungeon_data, $campaign_id);

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
      'state_version' => $game_state['state_version'],
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

    $game_state = $this->ensureGameState($dungeon_data);
    $phase = $game_state['phase'] ?? 'exploration';
    $handler = $this->getPhaseHandler($phase);

    return [
      'success' => TRUE,
      'game_state' => $this->buildClientGameState($game_state),
      'phase' => $phase,
      'available_actions' => $handler
        ? $handler->getAvailableActions($game_state, $dungeon_data)
        : [],
      'legal_intents' => $handler ? $handler->getLegalIntents() : [],
      'state_version' => $game_state['state_version'] ?? 1,
      'active_room_id' => $dungeon_data['active_room_id'] ?? NULL,
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'turn' => $game_state['turn'] ?? NULL,
      'exploration' => $game_state['exploration'] ?? NULL,
    ];
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

    return [
      'success' => TRUE,
      'game_state' => $this->buildClientGameState($game_state),
      'phase' => $target_phase,
      'events' => $result['events'] ?? [],
      'available_actions' => $handler
        ? $handler->getAvailableActions($game_state, $dungeon_data)
        : [],
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
  protected function loadDungeonData(int $campaign_id): ?array {
    try {
      $row = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_data'])
        ->condition('d.campaign_id', $campaign_id)
        ->execute()
        ->fetchField();

      if ($row) {
        return json_decode($row, TRUE) ?: NULL;
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

    return $dungeon_data['game_state'];
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
      'state_version' => $game_state['state_version'] ?? NULL,
    ];
  }

}
