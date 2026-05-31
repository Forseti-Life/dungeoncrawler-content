/**
 * @file panels/PartyRailPanel.js
 *
 * Renders the party member quick-select rail.
 *
 * Shows portrait thumbnails for all party members in the current
 * room. Clicking a member fires entity:selected on the bus.
 *
 * Subscribes to bus events:
 *   room:occupants-changed  — rebuild party rail for current room
 *   entity:selected         — highlight selected member's tile
 *
 * Fires bus events:
 *   entity:selected  — { entityId } when party member tile clicked
 *
 * Phase 9 implementation.
 */

export class PartyRailPanel {
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
