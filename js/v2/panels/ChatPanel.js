/**
 * @file panels/ChatPanel.js
 *
 * Multi-channel chat log with GM message rendering, pending turn status,
 * session view tabs, round-order lines, and scroll behavior.
 *
 * Channels: room chat, session log, GM responses.
 * Caches rendered message sets per room/session context with TTL.
 *
 * Subscribes to bus events:
 *   chat:message-received      — { line, channel }
 *   chat:turn-status-changed   — { status, pending }
 *   chat:pending-request       — { requestId, summary }
 *   chat:request-settled       — { requestId, result }
 *   room:changed               — invalidate room chat cache
 *
 * Fires bus events:
 *   user:chat-submitted  — { message, channel }
 *
 * Phase 7 implementation. (Highest complexity panel — treat as standalone sprint.)
 */

export class ChatPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    /** @type {Map<string, object>} cacheKey → { payload, expiresAt } */
    this._cache = new Map();
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._cache.clear();
  }
}
