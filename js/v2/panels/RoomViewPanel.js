/**
 * @file panels/RoomViewPanel.js
 *
 * Renders the room view image and NPC responder context.
 *
 * Caches room view payloads with a TTL to avoid redundant fetches.
 * Retries on failure with exponential backoff.
 *
 * Subscribes to bus events:
 *   room:changed  — load and render view for new room
 *
 * Fires no bus events (display only).
 *
 * Phase 9 implementation.
 */

export class RoomViewPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._retryTimer = null;
    /** @type {Map<string, { payload: object, expiresAt: number }>} */
    this._cache = new Map();
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
    if (this._retryTimer) {
      clearTimeout(this._retryTimer);
    }
    this._cache.clear();
  }
}
