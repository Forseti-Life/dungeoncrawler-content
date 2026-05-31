/**
 * @file canvas/HexTokenRenderer.js
 *
 * Renders ECS entities as PIXI sprites on the hex grid.
 *
 * Subscribes to bus events:
 *   room:entities-changed  — re-render tokens for new entity set
 *   entity:selected        — update selected token highlight
 *   entity:moved           — animate token to new hex
 *   combat:turn-changed    — update active-turn token highlight
 *
 * Fires no bus events (rendering only).
 *
 * Phase 3 implementation.
 */

/* global PIXI */

export class HexTokenRenderer {
  /**
   * @param {import('./HexCanvas').HexCanvas} hexCanvas
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(hexCanvas, bus) {
    this.hexCanvas = hexCanvas;
    this.bus = bus;
    this._unsubs = [];
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
  }
}
