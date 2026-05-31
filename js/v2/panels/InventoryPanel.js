/**
 * @file panels/InventoryPanel.js
 *
 * Renders the character's inventory with item actions (use, drop, equip).
 *
 * Subscribes to bus events:
 *   game:init               — initial inventory render
 *   entity:inventory-changed — re-render after item use/drop/equip
 *   user:merchant-selected  — sync merchant context into inventory view
 *
 * Fires bus events:
 *   user:inventory-action  — { action: 'use'|'drop'|'equip', itemId }
 *
 * Phase 8 implementation.
 */

export class InventoryPanel {
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
