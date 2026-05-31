/**
 * @file panels/StatusPanel.js
 *
 * HUD overlays and system status.
 *
 * Renders:
 *   - Server unavailability banner
 *   - Zoom level indicator
 *   - Hovered hex info (coordinates, terrain, objects)
 *   - Selected hex contents (entity list)
 *   - Fullscreen toggle
 *
 * Subscribes to bus events:
 *   game:server-unavailable     — show unavailability banner
 *   game:server-available       — hide unavailability banner
 *   hex:hovered                 — update hovered hex display
 *   hex:out                     — clear hovered hex display
 *   entity:selected             — update selected hex contents
 *   canvas:zoom-changed         — { scale } update zoom indicator
 *
 * Fires bus events:
 *   user:fullscreen-toggle  — user clicked fullscreen button
 *
 * Phase 9 implementation.
 */

export class StatusPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
  }
}
