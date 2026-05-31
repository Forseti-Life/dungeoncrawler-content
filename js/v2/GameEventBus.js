/**
 * @file GameEventBus.js
 *
 * Central pub/sub event bus for hexmap-v2.
 *
 * All game state changes and user actions flow through this bus.
 * No module holds a direct reference to another module — all
 * communication is via named events.
 *
 * Event namespaces:
 *   game:*    — lifecycle (init, reset, server-sync)
 *   room:*    — room changes, occupant updates
 *   combat:*  — turn changes, round changes, state changes
 *   entity:*  — selection, movement, attack results
 *   quest:*   — progress updates, completion
 *   chat:*    — new messages, pending requests
 *   user:*    — user-initiated actions (action rail, navigation, merchant)
 */

export class GameEventBus {
  constructor() {
    /** @type {Map<string, Set<Function>>} */
    this._listeners = new Map();
  }

  /**
   * Subscribe to an event.
   * @param {string} event
   * @param {Function} handler
   * @returns {Function} Unsubscribe function
   */
  on(event, handler) {
    if (!this._listeners.has(event)) {
      this._listeners.set(event, new Set());
    }
    this._listeners.get(event).add(handler);
    return () => this.off(event, handler);
  }

  /**
   * Unsubscribe from an event.
   * @param {string} event
   * @param {Function} handler
   */
  off(event, handler) {
    this._listeners.get(event)?.delete(handler);
  }

  /**
   * Emit an event to all subscribers.
   * @param {string} event
   * @param {*} [payload]
   */
  emit(event, payload) {
    this._listeners.get(event)?.forEach((handler) => {
      try {
        handler(payload);
      } catch (err) {
        console.error(`[GameEventBus] Error in handler for "${event}":`, err);
      }
    });
  }

  /**
   * Remove all listeners (use on destroy).
   */
  destroy() {
    this._listeners.clear();
  }
}
