/**
 * @file panels/PortraitPanel.js
 *
 * Renders room occupant portraits (PCs and NPCs).
 *
 * Data source: canonical occupant API (MapVisualStateProjector output).
 * PC entries sort before NPCs; each sorted alphabetically within kind.
 *
 * Subscribes to bus events:
 *   room:occupants-changed  — re-render portrait cards for current room
 *
 * Fires no bus events (display only).
 *
 * Phase 6 implementation.
 * @see MerchantPanel — sibling panel, same data source
 */

export class PortraitPanel {
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
