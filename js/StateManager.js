/**
 * @file StateManager.js
 *
 * Lightweight client projection store for hexmap UI/runtime coordination.
 * Holds ephemeral client-side state only; server authority remains elsewhere.
 */

export class StateManager {
  constructor() {
    this.state = {
      // Selection state
      selectedEntity: null,
      selectedHex: null,
      hoveredHex: null,

      // Movement state
      movementRange: null,
      movementRangeOverlay: null,

      // Combat state
      combatActive: false,
      serverCombatMode: false,
      attackTarget: null,

      // Drag state
      draggedObject: null,

      // Flags
      assetsLoaded: false,
      showCoordinates: false,
      showGrid: true,
    };

    this.listeners = {};
  }

  /**
   * Get state value.
   */
  get(key) {
    return this.state[key];
  }

  /**
   * Set state value and notify listeners.
   */
  set(key, value) {
    const oldValue = this.state[key];
    this.state[key] = value;

    if (this.listeners[key]) {
      this.listeners[key].forEach(callback => callback(value, oldValue));
    }
  }

  /**
   * Subscribe to state changes.
   */
  subscribe(key, callback) {
    if (!this.listeners[key]) {
      this.listeners[key] = [];
    }
    this.listeners[key].push(callback);

    return () => {
      this.listeners[key] = this.listeners[key].filter(cb => cb !== callback);
    };
  }

  /**
   * Reset all state to defaults.
   */
  reset() {
    this.state = {
      selectedEntity: null,
      selectedHex: null,
      hoveredHex: null,
      movementRange: null,
      movementRangeOverlay: null,
      combatActive: false,
      serverCombatMode: false,
      attackTarget: null,
      draggedObject: null,
      assetsLoaded: false,
      showCoordinates: false,
      showGrid: true,
      showFog: true,
      fogOverlay: null,
      visibleHexes: null,
    };
  }
}

export default StateManager;
