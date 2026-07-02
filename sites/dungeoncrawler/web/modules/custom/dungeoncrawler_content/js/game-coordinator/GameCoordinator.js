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

import { GameCoordinatorApi } from './GameCoordinatorApi.js';
import { PhaseManager } from './PhaseManager.js';
import { NarrationOverlay } from './NarrationOverlay.js';
import { ExplorationPhaseHandler } from './phases/ExplorationPhaseHandler.js';
import { EncounterPhaseHandler } from './phases/EncounterPhaseHandler.js';
import { DowntimePhaseHandler } from './phases/DowntimePhaseHandler.js';

export class GameCoordinator {
  /**
   * @param {number} campaignId - Campaign ID from launch context
   * @param {object} hexmap - Reference to Drupal.behaviors.hexMap
   */
  constructor(campaignId, hexmap) {
    this.campaignId = campaignId;
    this.hexmap = hexmap;

    /** @type {GameCoordinatorApi} */
    this.api = new GameCoordinatorApi(campaignId);

    /** @type {PhaseManager} */
    this.phaseManager = new PhaseManager();

    // Phase handlers (strategy pattern, keyed by phase name).
    /** @type {Object<string, ExplorationPhaseHandler|EncounterPhaseHandler|DowntimePhaseHandler>} */
    this.phaseHandlers = {};

    // Event timeline state.
    /** @type {number} */
    this.eventCursor = 0;

    /** @type {Array} */
    this.eventLog = [];

    /** @type {number|null} */
    this._eventPollInterval = null;

    /** @type {number} */
    this._eventPollMs = 5000;

    /** @type {boolean} */
    this._initialized = false;

    /** @type {NarrationOverlay|null} */
    this.narrationOverlay = null;

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

    this.phaseHandlers.exploration = new ExplorationPhaseHandler(deps);
    this.phaseHandlers.encounter = new EncounterPhaseHandler(deps);
    this.phaseHandlers.downtime = new DowntimePhaseHandler(deps);

    // Create narration overlay for AI GM narration.
    this.narrationOverlay = new NarrationOverlay();

    // Wire phase manager events.
    this._wirePhaseEvents();

    // Load initial state from server.
    try {
      const state = await this.api.getState();
      if (state?.success) {
        this.phaseManager.applyServerState(state.game_state, state.available_actions);
        this.eventCursor = state.game_state?.event_log_cursor || 0;
        console.log('[GameCoordinator] Initial state loaded:', this.phaseManager.currentPhase, 'v' + this.phaseManager.stateVersion);
      } else {
        console.warn('[GameCoordinator] Failed to load initial state:', state?.error);
      }
    } catch (err) {
      console.warn('[GameCoordinator] Server state fetch failed, using defaults:', err.message);
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

  /**
   * Cleanup — call when the page unloads or component detaches.
   */
  destroy() {
    this._stopEventPolling();
    if (this.narrationOverlay) {
      this.narrationOverlay.destroy();
      this.narrationOverlay = null;
    }
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
   * Perform a search action (exploration only).
   * @returns {Promise<object|null>}
   */
  async performSearch() {
    const handler = this.phaseHandlers.exploration;
    if (!handler || this.phaseManager.currentPhase !== 'exploration') {
      console.info('[GameCoordinator] Search only available in exploration phase.');
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
    const entity = this.hexmap.stateManager?.get('selectedEntity');
    if (this.phaseManager.currentPhase === 'downtime' && restType === 'long') {
      return this.phaseHandlers.downtime?.performLongRest(entity);
    }
    if (this.phaseManager.currentPhase === 'exploration') {
      return this.phaseHandlers.exploration?.performRest(entity, restType);
    }
    return null;
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
        this.phaseManager.applyServerState(result.game_state, result.available_actions);
        this._processNewEvents(result.events);
      }
      return result;
    } catch (err) {
      console.error('[GameCoordinator] Transition failed:', err);
      return null;
    }
  }

  // =========================================================================
  // Phase Handler Access
  // =========================================================================

  /**
   * Get the handler for the currently active phase.
   * @returns {ExplorationPhaseHandler|EncounterPhaseHandler|DowntimePhaseHandler|null}
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
        if (result?.events?.length > 0) {
          this._processNewEvents(result.events);
          this.eventCursor = result.latest_cursor || this.eventCursor;
        }
      } catch (err) {
        // Silently ignore polling errors — server may be temporarily unavailable.
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

    // Show narration overlay for GM narration events.
    this._showNarrations(events);

    // Emit custom event for UI listeners.
    window.dispatchEvent(new CustomEvent('dungeoncrawler:game-events', {
      detail: { events, total: this.eventLog.length },
    }));
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
    if (!this.narrationOverlay) return;

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
        this.narrationOverlay.show(text, { style });
      }
    }
  }

  // =========================================================================
  // Phase Event Wiring
  // =========================================================================

  /**
   * Wire up phase manager events to update the hexmap UI and state.
   * @private
   */
  _wirePhaseEvents() {
    // Phase change → update UI, toggle combat mode.
    this._unsubscribers.push(
      this.phaseManager.on('phaseChange', (data) => {
        console.log(`[GameCoordinator] Phase: ${data.from} → ${data.to}`);
        this._updatePhaseUI(data.to);

        // Sync with existing hexmap state for backward compatibility.
        if (data.to === 'encounter') {
          this.hexmap.stateManager?.set('serverCombatMode', true);
          this.hexmap.stateManager?.set('combatActive', true);
        } else {
          this.hexmap.stateManager?.set('serverCombatMode', false);
          this.hexmap.stateManager?.set('combatActive', false);
          this.hexmap.stateManager?.set('encounterId', null);
        }
      })
    );

    // Encounter start → sync with TurnManagementSystem.
    this._unsubscribers.push(
      this.phaseManager.on('encounterStart', (data) => {
        console.log('[GameCoordinator] Encounter started:', data.encounterId);
        this.hexmap.stateManager?.set('encounterId', data.encounterId);

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
            this.hexmap.selectEntity?.(entity);
            break;
          }
        }
      })
    );

    // Available actions update → update action button visibility.
    this._unsubscribers.push(
      this.phaseManager.on('actionsUpdate', (actions) => {
        this._updateActionButtons(actions);
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

    // Show exploration-specific UI.
    const explorationPanel = document.getElementById('exploration-actions');
    if (explorationPanel) {
      explorationPanel.style.display = phase === 'exploration' ? '' : 'none';
    }

    // Show encounter-specific action bar.
    const encounterPanel = document.getElementById('encounter-actions');
    if (encounterPanel) {
      encounterPanel.style.display = phase === 'encounter' ? '' : 'none';
    }
  }

  /**
   * Update action button visibility based on legal actions.
   * @param {string[]} legalActions
   * @private
   */
  _updateActionButtons(legalActions) {
    const buttonMap = {
      'move': 'action-move',
      'strike': 'action-attack',
      'attack': 'action-attack',
      'interact': 'action-interact',
      'talk': 'action-talk',
      'search': 'action-search',
      'rest': 'action-rest',
      'end_turn': 'end-turn',
    };

    for (const [action, elementId] of Object.entries(buttonMap)) {
      const el = document.getElementById(elementId);
      if (el) {
        const isLegal = legalActions.includes(action);
        el.style.display = isLegal ? '' : 'none';
        el.disabled = !isLegal;
      }
    }
  }

  /**
   * @private
   */
  _formatPhaseName(phase) {
    const names = {
      exploration: 'Exploration',
      encounter: 'Encounter',
      downtime: 'Downtime',
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
