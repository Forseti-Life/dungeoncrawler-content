/**
 * @file
 * PhaseManager — client-side phase state machine.
 *
 * Mirrors the server's game phase state and provides a pub/sub interface
 * for the client to react to phase changes without polling. The server
 * remains authoritative — this is a local projection.
 *
 * Phases: 'exploration' | 'encounter' | 'downtime'
 */

/**
 * Valid transitions: from → [to, ...]
 * Must match GameCoordinatorService::VALID_TRANSITIONS on the server.
 */
const VALID_TRANSITIONS = {
  exploration: ['encounter', 'downtime'],
  encounter: ['exploration'],
  downtime: ['exploration'],
};

export class PhaseManager {
  constructor() {
    /** @type {'exploration'|'encounter'|'downtime'} */
    this.currentPhase = 'exploration';

    /** @type {number} */
    this.stateVersion = 0;

    /** @type {number|null} */
    this.round = null;

    /** @type {object|null} */
    this.turn = null;

    /** @type {number|null} */
    this.encounterId = null;

    /** @type {Array|null} */
    this.initiativeOrder = null;

    /** @type {string[]} */
    this.availableActions = [];

    /** @type {number} */
    this.eventLogCursor = 0;

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
   */
  applyServerState(serverState, availableActions) {
    if (!serverState) return;

    const previousPhase = this.currentPhase;
    const previousRound = this.round;
    const previousTurnEntity = this.turn?.entity;

    // Core state.
    this.currentPhase = serverState.phase || 'exploration';
    this.stateVersion = serverState.state_version || 0;
    this.round = serverState.round;
    this.turn = serverState.turn;
    this.encounterId = serverState.encounter_id;
    this.initiativeOrder = serverState.initiative_order;
    this.eventLogCursor = serverState.event_log_cursor || 0;

    if (availableActions) {
      this.availableActions = availableActions;
      this._emit('actionsUpdate', this.availableActions);
    }

    // Emit phase change if phase actually changed.
    if (previousPhase !== this.currentPhase) {
      this._emit('phaseChange', {
        from: previousPhase,
        to: this.currentPhase,
        encounterId: this.encounterId,
      });

      // Specific encounter events.
      if (this.currentPhase === 'encounter') {
        this._emit('encounterStart', {
          encounterId: this.encounterId,
          initiativeOrder: this.initiativeOrder,
        });
      }
      if (previousPhase === 'encounter') {
        this._emit('encounterEnd', {
          encounterId: this.encounterId,
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
    return (VALID_TRANSITIONS[this.currentPhase] || []).includes(targetPhase);
  }

  /**
   * Are we in an active encounter?
   * @returns {boolean}
   */
  isInEncounter() {
    return this.currentPhase === 'encounter' && this.encounterId != null;
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
      initiativeOrder: this.initiativeOrder ? [...this.initiativeOrder] : null,
      availableActions: [...this.availableActions],
      eventLogCursor: this.eventLogCursor,
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
}

export default PhaseManager;
