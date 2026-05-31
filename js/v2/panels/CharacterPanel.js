/**
 * @file panels/CharacterPanel.js
 *
 * Character sheet embed and entity info display.
 *
 * Renders:
 *   - Embedded iframe character sheet (for launch character)
 *   - Launch character summary (name, class, level, portrait)
 *   - Selected entity info (stats, conditions, team)
 *
 * Subscribes to bus events:
 *   game:init         — render launch character summary
 *   entity:selected   — show entity info for selected entity
 *   entity:deselected — hide entity info panel
 *
 * Fires no bus events (display only).
 *
 * Phase 8 implementation.
 */

export class CharacterPanel {
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
