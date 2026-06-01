/**
 * @file
 * PhaseManager — client-side phase state machine.
 *
 * Mirrors the server's game phase state and provides a pub/sub interface
 * for the client to react to phase changes without polling. The server
 * remains authoritative — this is a local projection.
 *
 * Phases: 'encounter'
 *
 * Exploration is intentionally kept out of the live runtime path for now.
 * Server payloads that still report `exploration` are normalized to
 * `encounter` on the client so room entry always stays inside the encounter
 * framework.
 */

/**
 * Valid transitions: from → [to, ...]
 * Must match GameCoordinatorService::VALID_TRANSITIONS on the server.
 */
const VALID_TRANSITIONS = {
  encounter: [],
};

export class PhaseManager {
  constructor() {
    /** @type {'encounter'} */
    this.currentPhase = 'encounter';

    /** @type {number} */
    this.stateVersion = 0;

    /** @type {number|null} */
    this.round = null;

    /** @type {object|null} */
    this.turn = null;

    /** @type {number|null} */
    this.encounterId = null;

    /** @type {object|null} */
    this.encounterContext = null;

    /** @type {string|null} */
    this.activeRoomId = null;

    /** @type {Array|null} */
    this.initiativeOrder = null;

    /** @type {string[]} */
    this.availableActions = [];

    /** @type {object|null} */
    this.actionContract = null;

    /** @type {string[]} */
    this.legalIntents = [];

    /** @type {number} */
    this.eventLogCursor = 0;

    /** @type {object|null} */
    this.serverState = null;

    // Listeners keyed by event name.
    /** @private */
    this._listeners = {
      phaseChange: [],
      stateUpdate: [],
      turnChange: [],
      roundChange: [],
      encounterStart: [],
      encounterEnd: [],
      actionsUpdate: [],
    };
  }

  // =========================================================================
  // State Hydration (from server responses)
  // =========================================================================

  /**
   * Apply a full game state payload from the server.
   * Called on initial load and on every action response.
   *
   * @param {object} serverState - The game_state object from server
   * @param {string[]} [availableActions] - Legal action types
   * @param {object|null} [actionContract] - Canonical client action contract
   */
  applyServerState(serverState, availableActions, actionContract = null) {
    if (!serverState) return;

    const previousPhase = this.currentPhase;
    const previousEncounterId = Number(this.encounterId || 0) || null;
    const previousRound = this.round;
    const previousTurnEntity = this.turn?.entity;
    const mergedState = {
      ...(this.serverState || {}),
      ...serverState,
    };
    const normalizedPhase = this._normalizePhaseName(mergedState.phase);

    // Core state.
    this.serverState = mergedState;
    this.currentPhase = normalizedPhase;
    this.stateVersion = mergedState.state_version || 0;
    this.round = mergedState.round;
    this.turn = mergedState.turn;
    this.encounterId = mergedState.encounter_id;
    this.encounterContext = mergedState.encounter_context || null;
    this.activeRoomId = mergedState.active_room_id || this.activeRoomId || null;
    this.initiativeOrder = mergedState.initiative_order;
    this.eventLogCursor = mergedState.event_log_cursor || 0;

    if (availableActions) {
      this.availableActions = availableActions;
      this._emit('actionsUpdate', this.availableActions);
    }
    this.actionContract = actionContract;
    this.legalIntents = Array.isArray(mergedState.legal_intents) ? mergedState.legal_intents : this.legalIntents;

    // Emit phase change if phase actually changed.
    if (previousPhase !== this.currentPhase) {
      this._emit('phaseChange', {
        from: previousPhase,
        to: this.currentPhase,
        encounterId: this.encounterId,
      });
    }

    const nextEncounterId = Number(this.encounterId || 0) || null;
    if (previousEncounterId !== nextEncounterId) {
      if (previousEncounterId) {
        this._emit('encounterEnd', {
          encounterId: previousEncounterId,
        });
      }
      if (nextEncounterId) {
        this._emit('encounterStart', {
          encounterId: nextEncounterId,
          initiativeOrder: this.initiativeOrder,
        });
      }
    }

    // Turn changes.
    if (this.turn && previousTurnEntity !== this.turn.entity) {
      this._emit('turnChange', {
        entity: this.turn.entity,
        actionsRemaining: this.turn.actions_remaining,
        attacksThisTurn: this.turn.attacks_this_turn,
        reactionAvailable: this.turn.reaction_available,
        index: this.turn.index,
      });
    }

    // Round changes.
    if (this.round && previousRound !== this.round) {
      this._emit('roundChange', {
        round: this.round,
      });
    }

    // Generic state update (always fires).
    this._emit('stateUpdate', this.getSnapshot());
  }

  // =========================================================================
  // Queries
  // =========================================================================

  /**
   * Is the given action type legal in the current phase?
   * @param {string} actionType
   * @returns {boolean}
   */
  isActionLegal(actionType) {
    return this.availableActions.includes(actionType);
  }

  /**
   * Is a phase transition valid from the current phase?
   * @param {string} targetPhase
   * @returns {boolean}
   */
  canTransitionTo(targetPhase) {
    return (VALID_TRANSITIONS[this.currentPhase] || []).includes(this._normalizePhaseName(targetPhase));
  }

  /**
   * Are we in an active encounter?
   * @returns {boolean}
   */
  isInEncounter() {
    return this.currentPhase === 'encounter';
  }

  /**
   * Is it the given entity's turn?
   * @param {string} entityId
   * @returns {boolean}
   */
  isEntityTurn(entityId) {
    return this.turn?.entity === entityId;
  }

  /**
   * Get the currently active entity (whose turn it is).
   * @returns {string|null}
   */
  getCurrentTurnEntity() {
    return this.turn?.entity || null;
  }

  /**
   * Get a snapshot of the current state for external use.
   * @returns {object}
   */
  getSnapshot() {
    return {
      phase: this.currentPhase,
      stateVersion: this.stateVersion,
      round: this.round,
      turn: this.turn ? { ...this.turn } : null,
      encounterId: this.encounterId,
      encounterContext: this.encounterContext ? { ...this.encounterContext } : null,
      activeRoomId: this.activeRoomId,
      initiativeOrder: this.initiativeOrder ? [...this.initiativeOrder] : null,
      availableActions: [...this.availableActions],
      actionContract: this.actionContract,
      legalIntents: [...this.legalIntents],
      eventLogCursor: this.eventLogCursor,
      campaignClock: this.serverState?.campaign_clock || null,
      gameTime: this.serverState?.game_time || null,
      timedActivities: Array.isArray(this.serverState?.timed_activities) ? [...this.serverState.timed_activities] : [],
    };
  }

  // =========================================================================
  // Pub/Sub
  // =========================================================================

  /**
   * Subscribe to a phase manager event.
   *
   * Events:
   *  - 'phaseChange': { from, to, encounterId }
   *  - 'stateUpdate': full snapshot
   *  - 'turnChange': { entity, actionsRemaining, ... }
   *  - 'roundChange': { round }
   *  - 'encounterStart': { encounterId, initiativeOrder }
   *  - 'encounterEnd': { encounterId }
   *  - 'actionsUpdate': string[]
   *
   * @param {string} event
   * @param {Function} callback
   * @returns {Function} Unsubscribe function
   */
  on(event, callback) {
    if (!this._listeners[event]) {
      this._listeners[event] = [];
    }
    this._listeners[event].push(callback);
    return () => {
      this._listeners[event] = this._listeners[event].filter(cb => cb !== callback);
    };
  }

  /**
   * @private
   */
  _emit(event, data) {
    const listeners = this._listeners[event] || [];
    for (const cb of listeners) {
      try {
        cb(data);
      } catch (err) {
        console.error(`[PhaseManager] Listener error on '${event}':`, err);
      }
    }
  }

  /**
   * Normalize dormant exploration states to the live encounter runtime.
   *
   * @param {string|null|undefined} phase
   * @returns {'encounter'}
   * @private
   */
  _normalizePhaseName(phase) {
    return 'encounter';
  }
}

export default PhaseManager;
