/**
 * @file
 * GameCoordinator — the client-side entry point for the game coordinator engine.
 *
 * This is the "main()" for the client game loop. It:
 *  1. Initializes the API client, phase manager, and phase handlers
 *  2. Intercepts hex clicks from hexmap.js and routes to the active phase handler
 *  3. Manages phase transitions (server-authoritative) with client UI updates
 *  4. Polls for game events and feeds them into the timeline
 *  5. Syncs server state with the existing ECS systems
 *
 * Usage from hexmap.js:
 *   import { GameCoordinator } from './game-coordinator/GameCoordinator.js';
 *   this.gameCoordinator = new GameCoordinator(campaignId, this);
 *   await this.gameCoordinator.init();
 *
 * Then in onHexClick:
 *   if (this.gameCoordinator.handleHexClick(q, r)) return;
 */

import { GameCoordinatorApi } from './GameCoordinatorApi.js?v=20260804-v2-action-bar-rca-logs-2';
import { PhaseManager } from './PhaseManager.js';
import { NarrationOverlay } from './NarrationOverlay.js';
import { ExplorationPhaseHandler } from './phases/ExplorationPhaseHandler.js';
import { EncounterPhaseHandler } from './phases/EncounterPhaseHandler.js';
import { RuntimeStateStore } from './RuntimeStateStore.js';

export class GameCoordinator {
  /**
   * @param {number} campaignId - Campaign ID from launch context
   * @param {object} hexmap - Reference to Drupal.behaviors.hexMap
   */
  constructor(campaignId, hexmap) {
    console.info('[GameCoordinator] module loaded', {
      version: '20260804-v2-action-bar-rca-logs-3',
    });
    this.campaignId = campaignId;
    this.hexmap = hexmap;

    /** @type {GameCoordinatorApi} */
    this.api = new GameCoordinatorApi(campaignId);

    /** @type {PhaseManager} */
    this.phaseManager = new PhaseManager();
    /** @type {RuntimeStateStore} */
    this.runtimeStateStore = new RuntimeStateStore();

    // Phase handlers (strategy pattern, keyed by active phase name).
    /** @type {Object<string, EncounterPhaseHandler>} */
    this.phaseHandlers = {};
    /** @type {ExplorationPhaseHandler|null} retained for helper reuse only. */
    this.deprecatedExplorationActions = null;

    // Event timeline state.
    /** @type {number} */
    this.eventCursor = 0;

    /** @type {Array} */
    this.eventLog = [];

    /** @type {number|null} */
    this._eventPollInterval = null;

    /** @type {number} */
    this._eventPollMs = 5000;
    /** @type {number} */
    this._lastEventPollErrorAt = 0;

    /** @type {boolean} */
    this._initialized = false;

    /** @type {NarrationOverlay|null} */
    this.narrationOverlay = null;

    /** @type {HTMLAudioElement|null} */
    this.narrationAudio = null;

    /** @type {string|null} */
    this.pendingNarrationAudioUrl = null;

    /** @type {Function|null} */
    this._interactionUnlockHandler = null;

    // State subscriptions for cleanup.
    /** @type {Function[]} */
    this._unsubscribers = [];
  }

  // =========================================================================
  // Initialization
  // =========================================================================

  /**
   * Initialize the game coordinator — loads server state and wires up handlers.
   * Call once after ECS initialization.
   *
   * @returns {Promise<void>}
   */
  async init() {
    if (this._initialized) return;
    this._initialized = true;

    console.log('[GameCoordinator] Initializing for campaign', this.campaignId);

    // Create phase handlers with shared dependencies.
    const deps = {
      api: this.api,
      phaseManager: this.phaseManager,
      hexmap: this.hexmap,
    };

    this.deprecatedExplorationActions = new ExplorationPhaseHandler(deps);
    this.phaseHandlers.encounter = new EncounterPhaseHandler(deps);

    // Create narration overlay for AI GM narration.
    this.narrationOverlay = new NarrationOverlay();

    // Wire phase manager events.
    this._wirePhaseEvents();
    this._armNarrationAudioUnlock();

    const bootstrapState = this._getBootstrapState();
    const bootstrapAvailableActions = Array.isArray(this.hexmap?.dungeonData?.available_actions)
      ? this.hexmap.dungeonData.available_actions
      : [];
    const bootstrapActionContract = this.hexmap?.dungeonData?.action_contract || null;
    if (bootstrapState) {
      this._commitRuntimeState({
        game_state: bootstrapState,
        available_actions: bootstrapAvailableActions,
        action_contract: bootstrapActionContract || null,
      }, 'bootstrap');
      this._syncEncounterCacheFromAuthoritativeResult({
        game_state: bootstrapState,
        available_actions: bootstrapAvailableActions,
        action_contract: bootstrapActionContract || null,
      });
      this.phaseManager.applyServerState(
        bootstrapState,
        bootstrapAvailableActions.length > 0 ? bootstrapAvailableActions : (this.phaseManager.availableActions || []),
        bootstrapActionContract || this.phaseManager.actionContract || null
      );
      this.eventCursor = bootstrapState.event_log_cursor || 0;
      console.info('[GameCoordinator] bootstrap payload applied', {
        phase: this.phaseManager.currentPhase,
        stateVersion: this.phaseManager.stateVersion,
        bootstrapAvailableActionCount: bootstrapAvailableActions.length,
        bootstrapAvailableActions,
        bootstrapContractActionCount: Array.isArray(bootstrapActionContract?.actions) ? bootstrapActionContract.actions.length : 0,
        bootstrapFamilyKeys: bootstrapActionContract?.action_option_families ? Object.keys(bootstrapActionContract.action_option_families) : [],
      });
    }

    const shouldFetchInitialState = !bootstrapState
      || bootstrapAvailableActions.length === 0
      || !bootstrapActionContract;

    if (shouldFetchInitialState) {
      // Load initial state from server only when bootstrap state is absent.
      try {
        const runtimeContext = this.hexmap?.resolveLaunchCharacterRuntimeContext?.() || {};
        const state = await this.api.getState({
          actor: runtimeContext.instanceId || null,
          characterId: Number(runtimeContext.characterId || 0) || null,
        });
        if (state?.success) {
          console.info('[GameCoordinator] initial /state response', {
            actor: runtimeContext.instanceId || null,
            characterId: Number(runtimeContext.characterId || 0) || null,
            availableActionCount: Array.isArray(state.available_actions) ? state.available_actions.length : 0,
            availableActions: Array.isArray(state.available_actions) ? state.available_actions : [],
            actionContractCount: Array.isArray(state.action_contract?.actions) ? state.action_contract.actions.length : 0,
            familySummary: state.action_contract?.action_option_families
              ? Object.entries(state.action_contract.action_option_families).map(([key, family]) => `${key}:${Number(family?.option_count ?? (Array.isArray(family?.options) ? family.options.length : 0))}`)
              : [],
          });
          this._commitRuntimeState(state, 'initial-state');
          this._syncEncounterCacheFromAuthoritativeResult(state);
          this.phaseManager.applyServerState(this._buildStatePayloadFromResponse(state), state.available_actions, state.action_contract || null);
          this.eventCursor = state.game_state?.event_log_cursor || 0;
          if (state.events?.length) {
            const latestBootstrapEventId = Math.max(...state.events.map((event) => Number(event?.id || 0)));
            if (latestBootstrapEventId > this.eventCursor) {
              this.eventCursor = latestBootstrapEventId;
            }
            this._processNewEvents(state.events);
          }
          console.log('[GameCoordinator] Initial state loaded:', this.phaseManager.currentPhase, 'v' + this.phaseManager.stateVersion);
        } else {
          this.runtimeStateStore.noteSyncFailure({
            code: 'initial_state_failed',
            error: state?.error || 'unknown',
          });
          console.warn('[GameCoordinator] Failed to load initial state:', state?.error);
        }
      } catch (err) {
        this.runtimeStateStore.noteSyncFailure({
          code: 'initial_state_fetch_error',
          error: err?.message || String(err || ''),
        });
        console.warn('[GameCoordinator] Server state fetch failed, using defaults:', err.message);
      }
    }

    // Update UI to reflect initial phase.
    this._updatePhaseUI(this.phaseManager.currentPhase);

    // Show the phase indicator.
    const indicator = document.getElementById('game-phase-indicator');
    if (indicator) indicator.style.display = '';

    // Start event polling.
    this._startEventPolling();

    console.log('[GameCoordinator] Ready. Phase:', this.phaseManager.currentPhase);
  }

  _getBootstrapState() {
    const state = this.hexmap?.dungeonData?.game_state;
    if (!state || typeof state !== 'object') {
      return null;
    }
    return {
      ...state,
      active_room_id: state.active_room_id || this.hexmap?.resolveActiveRoomId?.() || null,
      encounter_id: state.encounter_id || null,
      round: state.round ?? null,
      turn: state.turn ?? null,
      legal_intents: Array.isArray(state.legal_intents) ? state.legal_intents : [],
    };
  }

  /**
   * Cleanup — call when the page unloads or component detaches.
   */
  destroy() {
    this._stopEventPolling();
    if (this.narrationOverlay) {
      this.narrationOverlay.destroy();
      this.narrationOverlay = null;
    }
    if (this.narrationAudio) {
      this.narrationAudio.pause();
      this.narrationAudio = null;
    }
    this.pendingNarrationAudioUrl = null;
    this._disarmNarrationAudioUnlock();
    for (const unsub of this._unsubscribers) {
      unsub();
    }
    this._unsubscribers = [];
    this._initialized = false;
  }

  // =========================================================================
  // Click Routing (called from hexmap.js onHexClick)
  // =========================================================================

  /**
   * Route a hex click to the active phase handler.
   * Returns true if the click was consumed.
   *
   * @param {number} q
   * @param {number} r
   * @returns {boolean}
   */
  handleHexClick(q, r) {
    const handler = this.getActiveHandler();
    if (!handler) return false;

    const selectedEntity = this.hexmap.stateManager?.get('selectedEntity') || null;
    const actionMode = this.hexmap.stateManager?.get('actionMode') || 'attack';

    return handler.handleHexClick(q, r, selectedEntity, actionMode);
  }

  // =========================================================================
  // Action Dispatch (called from hexmap.js button handlers)
  // =========================================================================

  /**
   * Perform a search action in the live room encounter flow.
   * @returns {Promise<object|null>}
   */
  async performSearch() {
    const handler = this.deprecatedExplorationActions;
    if (!handler || this.phaseManager.currentPhase !== 'encounter') {
      console.info('[GameCoordinator] Search only available in active room encounter mode.');
      return null;
    }
    const entity = this.hexmap.stateManager?.get('selectedEntity');
    return handler.performSearch(entity);
  }

  /**
   * Perform a rest action.
   * @param {string} [restType='short']
   * @returns {Promise<object|null>}
   */
  async performRest(restType = 'short') {
    if (this.phaseManager.currentPhase !== 'encounter') {
      return null;
    }
    const entity = this.hexmap.stateManager?.get('selectedEntity');
    const actorRef = entity?.dcEntityRef || entity?.dcEntityInstanceId || null;
    if (!actorRef) {
      return null;
    }
    const actionType = restType === 'long' ? 'daily_preparations' : 'refocus';
    return this.api.sendAction(actionType, actorRef, {}, {
      stateVersion: this.phaseManager?.stateVersion,
    });
  }

  /**
   * End the current turn (encounter only).
   * @returns {Promise<object|null>}
   */
  async performEndTurn() {
    const handler = this.phaseHandlers.encounter;
    if (!handler || this.phaseManager.currentPhase !== 'encounter') {
      console.info('[GameCoordinator] End turn only available in encounter phase.');
      return null;
    }
    const entity = this.hexmap.stateManager?.get('selectedEntity');
    return handler.performEndTurn(entity);
  }

  /**
   * Request a phase transition.
   * @param {string} targetPhase
   * @param {object} [context={}]
   * @returns {Promise<object|null>}
   */
  async requestTransition(targetPhase, context = {}) {
    if (!this.phaseManager.canTransitionTo(targetPhase)) {
      console.warn(`[GameCoordinator] Cannot transition from ${this.phaseManager.currentPhase} to ${targetPhase}`);
      return null;
    }

    try {
      const result = await this.api.transitionPhase(targetPhase, context);
      if (result?.success) {
        this.phaseManager.applyServerState(this._buildStatePayloadFromResponse(result), result.available_actions, result.action_contract || null);
        this._processNewEvents(result.events);
      }
      return result;
    } catch (err) {
      console.error('[GameCoordinator] Transition failed:', err);
      return null;
    }
  }

  /**
   * Apply an authoritative coordinator payload returned by the server.
   *
   * Accepts either full-state responses or action responses that include
   * { game_state, available_actions, events }.
   *
   * @param {object} result
   */
  applyAuthoritativeUpdate(result = {}) {
    if (!result || typeof result !== 'object') {
      return;
    }

    const normalizedResult = this._normalizeAuthoritativeResult(result);
    this._commitRuntimeState(normalizedResult, 'authoritative-update');
    this._syncEncounterCacheFromAuthoritativeResult(normalizedResult);

    if (normalizedResult.game_state) {
      this.phaseManager.applyServerState(
        this._buildStatePayloadFromResponse(normalizedResult),
        normalizedResult.available_actions,
        normalizedResult.action_contract || null
      );
      const cursor = Number(
        normalizedResult.game_state?.event_log_cursor
        ?? normalizedResult.event_log_cursor
        ?? this.eventCursor
        ?? 0
      );
      if (cursor > this.eventCursor) {
        this.eventCursor = cursor;
      }
    }

    if (Array.isArray(normalizedResult.events) && normalizedResult.events.length > 0) {
      this._processNewEvents(normalizedResult.events);
    }
  }

  /**
   * Normalize coordinator-compatible server payloads.
   *
   * Accepts runtime-snapshot and combat-transition payloads and projects them
   * into the canonical { game_state, available_actions, action_contract } shape
   * consumed by the phase manager.
   *
   * @param {object} response
   * @returns {object}
   * @private
   */
  _normalizeAuthoritativeResult(response = {}) {
    if (!response || typeof response !== 'object') {
      return {};
    }
    if (response.game_state && typeof response.game_state === 'object') {
      return response;
    }

    const runtimeSnapshot = response.runtime_snapshot && typeof response.runtime_snapshot === 'object'
      ? response.runtime_snapshot
      : null;
    const combatTransition = response.combat_transition && typeof response.combat_transition === 'object'
      ? response.combat_transition
      : null;
    const projected = runtimeSnapshot || combatTransition;
    if (!projected || !projected.game_state || typeof projected.game_state !== 'object') {
      return response;
    }

    return {
      ...response,
      game_state: projected.game_state,
      available_actions: response.available_actions ?? runtimeSnapshot?.available_actions ?? null,
      action_contract: response.action_contract ?? runtimeSnapshot?.action_contract ?? null,
      active_room_id: response.active_room_id
        ?? runtimeSnapshot?.active_room_id
        ?? runtimeSnapshot?.active_room?.room_id
        ?? projected?.game_state?.encounter_context?.room_id
        ?? null,
      encounter_id: response.encounter_id
        ?? runtimeSnapshot?.encounter_id
        ?? projected?.game_state?.encounter_id
        ?? null,
      round: response.round
        ?? runtimeSnapshot?.round
        ?? projected?.game_state?.round
        ?? null,
      turn: response.turn
        ?? runtimeSnapshot?.turn
        ?? projected?.game_state?.turn
        ?? null,
      legal_intents: response.legal_intents
        ?? runtimeSnapshot?.legal_intents
        ?? null,
      event_log_cursor: response.event_log_cursor
        ?? projected?.game_state?.event_log_cursor
        ?? null,
    };
  }

  /**
   * Merge top-level coordinator response fields into the projected game_state.
   *
   * @param {object} response
   * @returns {object|null}
   * @private
   */
  _buildStatePayloadFromResponse(response = {}) {
    if (!response?.game_state || typeof response.game_state !== 'object') {
      return null;
    }
    return {
      ...response.game_state,
      state_version: response.state_version ?? response.game_state.state_version,
      phase: response.phase ?? response.game_state.phase,
      active_room_id: response.active_room_id ?? response.game_state.active_room_id,
      encounter_id: response.encounter_id ?? response.game_state.encounter_id,
      round: response.round ?? response.game_state.round,
      turn: response.turn ?? response.game_state.turn,
      event_log_cursor: response.event_log_cursor ?? response.game_state.event_log_cursor,
      legal_intents: response.legal_intents ?? response.game_state.legal_intents,
    };
  }

  _commitRuntimeState(response = {}, source = 'unknown') {
    try {
      const { snapshot, integrityIssues } = this.runtimeStateStore.commitFromResponse(response, { source });
      if (snapshot?.activeRoomId) {
        this.phaseManager.activeRoomId = snapshot.activeRoomId;
      }
      this.hexmap?.stateManager?.set?.('runtimeSnapshotId', snapshot?.snapshotId || null);
      this.hexmap?.stateManager?.set?.('runtimeSyncHealth', this.runtimeStateStore.getSyncHealth());
      this.hexmap?.bus?.emit?.('runtime:state-committed', {
        source,
        snapshot,
        integrityIssues,
        syncHealth: this.runtimeStateStore.getSyncHealth(),
      });
    } catch (error) {
      this.runtimeStateStore.noteSyncFailure({
        code: 'runtime_snapshot_commit_failed',
        source,
        error: error?.message || String(error || ''),
      });
      this.hexmap?.stateManager?.set?.('runtimeSyncHealth', this.runtimeStateStore.getSyncHealth());
      this.hexmap?.bus?.emit?.('runtime:sync-health-changed', {
        syncHealth: this.runtimeStateStore.getSyncHealth(),
        source,
        error: error?.message || String(error || ''),
      });
      console.error('[GameCoordinator] runtime snapshot commit failed', { source, error });
    }
  }

  _syncEncounterCacheFromAuthoritativeResult(response = {}) {
    const encounterState = this._extractEncounterStateFromResponse(response);
    if (!encounterState) {
      return;
    }
    this.hexmap?.cacheEncounterServerState?.(encounterState);
  }

  _extractEncounterStateFromResponse(response = {}) {
    if (!response || typeof response !== 'object') {
      return null;
    }

    const directEncounterState = response?.encounter_state && typeof response.encounter_state === 'object'
      ? response.encounter_state
      : null;
    if (directEncounterState && Number(directEncounterState?.encounter_id || 0) > 0) {
      return directEncounterState;
    }

    const gameState = response?.game_state && typeof response.game_state === 'object'
      ? response.game_state
      : null;
    const presentation = gameState?.encounter_presentation && typeof gameState.encounter_presentation === 'object'
      ? gameState.encounter_presentation
      : null;
    const encounterId = Number(
      response?.encounter_id
      ?? gameState?.encounter_id
      ?? presentation?.encounter_id
      ?? 0
    ) || 0;
    if (encounterId <= 0) {
      return null;
    }

    const currentRound = Number(
      response?.round
      ?? gameState?.round
      ?? presentation?.current_round
      ?? 0
    ) || null;

    return {
      encounter_id: encounterId,
      status: String(presentation?.status || 'active').trim().toLowerCase() || 'active',
      current_round: currentRound,
      version: Number(response?.state_version ?? gameState?.state_version ?? 0) || 0,
      initiative_order: Array.isArray(gameState?.initiative_order)
        ? gameState.initiative_order
        : (Array.isArray(presentation?.initiative_order) ? presentation.initiative_order : []),
      participants: Array.isArray(response?.participants)
        ? response.participants
        : (Array.isArray(gameState?.initiative_order)
          ? gameState.initiative_order
          : (Array.isArray(presentation?.initiative_order) ? presentation.initiative_order : [])),
      encounter_presentation: presentation || {
        encounter_id: encounterId,
        status: 'active',
        current_round: currentRound,
        initiative_order: Array.isArray(gameState?.initiative_order) ? gameState.initiative_order : [],
      },
    };
  }

  getAuthoritativePhaseSnapshot() {
    return this.runtimeStateStore.getSnapshot() || this.phaseManager.getSnapshot();
  }

  /**
   * Project combat API state onto the phase manager so encounter UI stays in
   * sync even when combat starts outside the coordinator action pipeline.
   *
   * @param {object} serverState
   */
  syncCombatEncounterState(serverState = {}) {
    if (!this._initialized || !serverState || typeof serverState !== 'object') {
      return;
    }

    const presentation = serverState?.encounter_presentation && typeof serverState.encounter_presentation === 'object'
      ? serverState.encounter_presentation
      : null;
    const encounterId = Number(
      presentation?.encounter_id
      ?? serverState.encounter_id
      ?? 0
    ) || null;
    const status = String(
      presentation?.status
      ?? serverState.status
      ?? ''
    ).trim().toLowerCase();
    const isActiveEncounter = encounterId !== null && status === 'active';

    if (isActiveEncounter) {
      const currentRound = Number(
        presentation?.current_round
        ?? serverState.current_round
      );
      const projectedTurn = this._buildProjectedEncounterTurn(serverState);
      const availableActions = Array.isArray(serverState.available_actions)
        ? serverState.available_actions
        : this.phaseManager.availableActions;
      const actionContract = serverState.action_contract || this.phaseManager.actionContract || null;
      const initiativeOrder = Array.isArray(presentation?.initiative_order)
        ? presentation.initiative_order
        : (Array.isArray(serverState.initiative_order) ? serverState.initiative_order : []);
      this.phaseManager.applyServerState({
        phase: 'encounter',
        state_version: Number(serverState.version) || this.phaseManager.stateVersion || 0,
        round: Number.isFinite(currentRound) && currentRound > 0 ? currentRound : 1,
        turn: projectedTurn,
        encounter_id: encounterId,
        initiative_order: initiativeOrder,
        event_log_cursor: this.eventCursor || 0,
      }, availableActions, actionContract);
      return;
    }

    if (this.phaseManager.currentPhase === 'encounter') {
      this.phaseManager.applyServerState({
        phase: 'encounter',
        state_version: Number(serverState.version) || this.phaseManager.stateVersion || 0,
        round: null,
        turn: null,
        encounter_id: null,
        initiative_order: null,
        event_log_cursor: this.eventCursor || 0,
      }, this.phaseManager.availableActions || [], this.phaseManager.actionContract || null);
    }
  }

  // =========================================================================
  // Phase Handler Access
  // =========================================================================

  /**
   * Get the handler for the currently active phase.
   * @returns {ExplorationPhaseHandler|EncounterPhaseHandler|null}
   */
  getActiveHandler() {
    return this.phaseHandlers[this.phaseManager.currentPhase] || null;
  }

  /**
   * Is the game coordinator active and should intercept clicks?
   * @returns {boolean}
   */
  isActive() {
    return this._initialized && this.campaignId > 0;
  }

  /**
   * @param {object} serverState
   * @returns {object|null}
   * @private
   */
  _buildProjectedEncounterTurn(serverState) {
    const presentation = serverState?.encounter_presentation && typeof serverState.encounter_presentation === 'object'
      ? serverState.encounter_presentation
      : null;
    const turnIndex = Number(
      presentation?.turn_index
      ?? serverState.turn_index
    );
    const currentParticipant = serverState.current_participant
      || (Array.isArray(serverState.participants) && Number.isFinite(turnIndex) ? serverState.participants[turnIndex] : null)
      || null;
    const currentEntity = currentParticipant?.entity_id
      || (Array.isArray(presentation?.initiative_order) && Number.isFinite(turnIndex) ? presentation.initiative_order[turnIndex]?.entity_id : null)
      || (Array.isArray(serverState.initiative_order) && Number.isFinite(turnIndex) ? serverState.initiative_order[turnIndex]?.entity_id : null)
      || String(presentation?.current_entity_id || '').trim()
      || null;

    if (!currentEntity) {
      return null;
    }

    return {
      entity: currentEntity,
      actions_remaining: Number(currentParticipant?.actions_remaining ?? 0),
      attacks_this_turn: Number(currentParticipant?.attacks_this_turn ?? 0),
      reaction_available: Boolean(currentParticipant?.reaction_available),
      index: Number.isFinite(turnIndex) ? turnIndex : 0,
    };
  }

  // =========================================================================
  // Event Polling
  // =========================================================================

  /**
   * Start polling the server for new game events.
   * @private
   */
  _startEventPolling() {
    if (this._eventPollInterval) return;

    this._eventPollInterval = setInterval(async () => {
      try {
        const result = await this.api.getEventsSince(this.eventCursor);
        this.runtimeStateStore.noteSyncSuccess({
          code: 'event_poll_ok',
        });
        if (result?.events?.length > 0) {
          this._processNewEvents(result.events);
          this.eventCursor = result.latest_cursor || result.cursor || this.eventCursor;
        }
      } catch (err) {
        this.runtimeStateStore.noteSyncFailure({
          code: 'event_poll_failed',
          error: err?.message || String(err || ''),
        });
        const now = Date.now();
        if ((now - this._lastEventPollErrorAt) > 15000) {
          this._lastEventPollErrorAt = now;
          console.warn('[GameCoordinator] event poll failed', err);
        }
      }
    }, this._eventPollMs);
  }

  /**
   * Stop event polling.
   * @private
   */
  _stopEventPolling() {
    if (this._eventPollInterval) {
      clearInterval(this._eventPollInterval);
      this._eventPollInterval = null;
    }
  }

  /**
   * Process newly received events.
   * @param {Array} events
   * @private
   */
  _processNewEvents(events) {
    if (!events?.length) return;

    for (const event of events) {
      this.eventLog.push(event);

      // Update cursor.
      if (event.id > this.eventCursor) {
        this.eventCursor = event.id;
      }
    }

    // Cap local event log at 200.
    if (this.eventLog.length > 200) {
      this.eventLog = this.eventLog.slice(-200);
    }

    this._logEncounterConsoleEvents(events);

    // Show narration overlay for GM narration events.
    this._showNarrations(events);

    // Emit custom event for UI listeners.
    window.dispatchEvent(new CustomEvent('dungeoncrawler:game-events', {
      detail: { events, total: this.eventLog.length },
    }));
  }

  /**
   * Mirror key encounter lifecycle events to the browser console.
   *
   * @param {Array} events
   * @private
   */
  _logEncounterConsoleEvents(events) {
    for (const event of events) {
      const type = String(event?.type || '').trim();
      const data = event?.data || {};

      if (type === 'encounter_framework_started' || type === 'encounter_framework_resumed') {
        console.info('[Encounter]', type, {
          roomId: data.room_id ?? null,
          participants: data.participants ?? null,
          narration: event?.narration ?? null,
        });
        continue;
      }

      if (type === 'round_start') {
        console.info('[Encounter] round_start', {
          round: Number(data.round ?? 0),
          roomId: data.room_id ?? null,
          narration: event?.narration ?? null,
        });
        continue;
      }

      if (type === 'turn_start') {
        console.info('[Encounter] turn_start', {
          round: Number(data.round ?? 0),
          actorId: event?.actor ?? data.entity_id ?? null,
          actorName: data.actor_name ?? null,
          roomId: data.room_id ?? null,
          narration: event?.narration ?? null,
        });
      }
    }
  }

  /**
   * Extract and display narration from new events.
   * Handles two patterns:
   *   1. Dedicated gm_narration events (from encounter start/end)
   *   2. Events with a narration field (room_entered, round_start, phase_transition)
   *
   * @param {Array} events
   * @private
   */
  _showNarrations(events) {
    for (const event of events) {
      let text = null;
      let style = event.type || 'default';

      if (event.type === 'gm_narration' && event.narration) {
        text = event.narration;
        style = event.data?.trigger || 'default';
      } else if (event.narration) {
        text = event.narration;
      }

      if (text) {
        this.hexmap?.bus?.emit?.('chat:system-message', {
          speaker: 'Narrator',
          kind: 'gm',
          text,
          view: 'room',
          channel: 'room',
          source: 'encounter-narration',
          authority: 'authoritative',
          messageClass: 'authoritative_transcript',
        });
      }

      const audioUrl = event.data?.narration_audio_url || null;
      if (audioUrl) {
        this._playNarrationAudio(audioUrl);
      }
    }
  }

  /**
   * Play narrator audio for a newly received event.
   *
   * @param {string} audioUrl
   * @private
   */
  _playNarrationAudio(audioUrl) {
    if (!audioUrl) return;

    this.pendingNarrationAudioUrl = null;

    if (this.narrationAudio) {
      this.narrationAudio.pause();
      this.narrationAudio = null;
    }

    const audio = new Audio(audioUrl);
    audio.preload = 'auto';
    this.narrationAudio = audio;
    audio.addEventListener('ended', () => {
      if (this.narrationAudio === audio) {
        this.narrationAudio = null;
      }
    }, { once: true });
    audio.play().catch((err) => {
      const blockedByAutoplay = err?.name === 'NotAllowedError';
      if (blockedByAutoplay) {
        this.pendingNarrationAudioUrl = audioUrl;
      }
      console.warn('[GameCoordinator] Narration audio playback failed:', err);
    });
  }

  /**
   * Retry blocked narration audio once the user interacts with the page.
   * @private
   */
  _armNarrationAudioUnlock() {
    if (this._interactionUnlockHandler) {
      return;
    }

    this._interactionUnlockHandler = () => {
      if (!this.pendingNarrationAudioUrl) {
        return;
      }
      const pendingUrl = this.pendingNarrationAudioUrl;
      this.pendingNarrationAudioUrl = null;
      this._playNarrationAudio(pendingUrl);
    };

    for (const eventName of ['pointerdown', 'keydown', 'touchstart']) {
      document.addEventListener(eventName, this._interactionUnlockHandler, { passive: true });
    }
  }

  /**
   * Remove user-interaction listeners used for deferred narration playback.
   * @private
   */
  _disarmNarrationAudioUnlock() {
    if (!this._interactionUnlockHandler) {
      return;
    }

    for (const eventName of ['pointerdown', 'keydown', 'touchstart']) {
      document.removeEventListener(eventName, this._interactionUnlockHandler);
    }
    this._interactionUnlockHandler = null;
  }

  // =========================================================================
  // Phase Event Wiring
  // =========================================================================

  /**
   * Wire up phase manager events to update the hexmap UI and state.
   * @private
   */
  _wirePhaseEvents() {
    this._unsubscribers.push(
      this.runtimeStateStore.onSyncHealthChanged(({ syncHealth, reason }) => {
        this.hexmap?.stateManager?.set?.('runtimeSyncHealth', syncHealth);
        this.hexmap?.bus?.emit?.('runtime:sync-health-changed', {
          syncHealth,
          reason: reason || null,
        });
      })
    );

    // Phase change → update UI, toggle combat mode.
    this._unsubscribers.push(
      this.phaseManager.on('phaseChange', (data) => {
        console.log(`[GameCoordinator] Phase: ${data.from} → ${data.to}`);
        this._updatePhaseUI(data.to);

        // Sync legacy flags from authoritative encounter fields.
        if (data.to === 'encounter') {
          this.hexmap.stateManager?.set('serverCombatMode', true);
          this.hexmap.stateManager?.set('combatActive', Boolean(data.encounterId));
        } else {
          this.hexmap.stateManager?.set('serverCombatMode', false);
          this.hexmap.stateManager?.set('combatActive', false);
          this.hexmap.stateManager?.set('encounterId', null);
        }
      })
    );

    this._unsubscribers.push(
      this.phaseManager.on('actionsUpdate', (availableActions) => {
        console.info('[GameCoordinator] phaseManager actionsUpdate', {
          count: Array.isArray(availableActions) ? availableActions.length : 0,
          availableActions: Array.isArray(availableActions) ? availableActions : [],
          contractActorId: this.phaseManager.actionContract?.actor_id || null,
        });
        const authoritativeSnapshot = this.getAuthoritativePhaseSnapshot();
        this.hexmap?.bus?.emit?.('game:state-refreshed', {
          availableActions: Array.isArray(availableActions) ? availableActions : [],
          actionContract: this.phaseManager.actionContract || null,
          phaseSnapshot: authoritativeSnapshot,
          runtimeSyncHealth: this.runtimeStateStore.getSyncHealth(),
        });
      })
    );

    this._unsubscribers.push(
      this.phaseManager.on('stateUpdate', (snapshot) => {
        const authoritativeSnapshot = this.getAuthoritativePhaseSnapshot() || snapshot || null;
        console.info('[GameCoordinator] phaseManager stateUpdate', {
          phase: snapshot?.phase || null,
          actorId: snapshot?.actionContract?.actor_id || snapshot?.turn?.entity || null,
          availableActionCount: Array.isArray(snapshot?.availableActions) ? snapshot.availableActions.length : 0,
          contractActionCount: Array.isArray(snapshot?.actionContract?.actions) ? snapshot.actionContract.actions.length : 0,
          runtimeSyncHealth: this.runtimeStateStore.getSyncHealth(),
        });
        this.hexmap?.bus?.emit?.('game:state-refreshed', {
          availableActions: Array.isArray(authoritativeSnapshot?.availableActions) ? authoritativeSnapshot.availableActions : [],
          actionContract: authoritativeSnapshot?.actionContract || null,
          phaseSnapshot: authoritativeSnapshot,
          runtimeSyncHealth: this.runtimeStateStore.getSyncHealth(),
        });
      })
    );

    // Encounter start → sync with TurnManagementSystem.
    this._unsubscribers.push(
      this.phaseManager.on('encounterStart', (data) => {
        console.log('[GameCoordinator] Encounter started:', data.encounterId);
        this.hexmap.stateManager?.set('encounterId', data.encounterId);
        this.hexmap.stateManager?.set('serverCombatMode', true);
        this.hexmap.stateManager?.set('combatActive', Boolean(data.encounterId));

        if (this.hexmap.turnManagementSystem && typeof this.hexmap.turnManagementSystem.hydrateFromServer === 'function') {
          this.hexmap.turnManagementSystem.hydrateFromServer({
            encounter_id: data.encounterId,
            initiative_order: data.initiativeOrder || [],
          });
        }
      })
    );

    // Encounter end → clean up combat state.
    this._unsubscribers.push(
      this.phaseManager.on('encounterEnd', () => {
        console.log('[GameCoordinator] Encounter ended.');
        if (this.hexmap.turnManagementSystem?.endCombat) {
          this.hexmap.turnManagementSystem.endCombat();
        }
        this.hexmap.stateManager?.set('encounterId', null);
        this.hexmap.stateManager?.set('serverCombatMode', false);
        this.hexmap.stateManager?.set('combatActive', false);
      })
    );

    // Turn change → update turn HUD.
    this._unsubscribers.push(
      this.phaseManager.on('turnChange', (data) => {
        // Find the ECS entity matching this turn.
        const entities = this.hexmap.entityManager?.getEntitiesWith('IdentityComponent') || [];
        for (const entity of entities) {
          const ref = entity.dcEntityRef || entity.dcEntityInstanceId;
          if (ref === data.entity) {
            this.hexmap.selectEntity?.(entity, { suppressCoordinatorResync: true });
            break;
          }
        }
      })
    );

  }

  // =========================================================================
  // UI Updates
  // =========================================================================

  /**
   * Update the phase indicator and toggle UI elements based on the current phase.
   * @param {string} phase
   * @private
   */
  _updatePhaseUI(phase) {
    // Update phase indicator badge.
    const indicator = document.getElementById('game-phase-indicator');
    if (indicator) {
      indicator.textContent = this._formatPhaseName(phase);
      indicator.className = `phase-indicator phase-${phase}`;
    }

    // Toggle combat controls visibility.
    const combatControls = document.getElementById('combat-controls');
    if (combatControls) {
      combatControls.style.display = phase === 'encounter' ? '' : 'none';
    }

    // Toggle initiative tracker.
    const initiativeTracker = document.getElementById('initiative-tracker');
    if (initiativeTracker) {
      initiativeTracker.style.display = phase === 'encounter' ? '' : 'none';
    }

    // Toggle turn HUD.
    const turnHud = document.getElementById('turn-hud');
    if (turnHud) {
      turnHud.style.display = phase === 'encounter' ? '' : 'none';
    }

  }

  /**
   * @private
   */
  _formatPhaseName(phase) {
    const names = {
      exploration: 'Encounter',
      encounter: 'Encounter',
    };
    return names[phase] || phase;
  }

  // =========================================================================
  // Timeline Access
  // =========================================================================

  /**
   * Get the recent event log for timeline rendering.
   * @param {number} [count=50]
   * @returns {Array}
   */
  getRecentEvents(count = 50) {
    return this.eventLog.slice(-count);
  }

  /**
   * Get a snapshot of the current game state for debugging/display.
   * @returns {object}
   */
  getStateSnapshot() {
    return {
      campaignId: this.campaignId,
      ...this.phaseManager.getSnapshot(),
      eventLogLength: this.eventLog.length,
      eventCursor: this.eventCursor,
    };
  }
}

export default GameCoordinator;
