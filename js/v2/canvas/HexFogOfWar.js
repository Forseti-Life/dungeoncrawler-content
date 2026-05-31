/**
 * @file canvas/HexFogOfWar.js
 *
 * Renders vision radius and fog-of-war overlay on the hex grid.
 *
 * Subscribes to bus events:
 *   entity:selected  — recalculate and render visible hex set
 *   room:changed     — reset fog for new room
 *
 * Fires no bus events (rendering only).
 *
 * Phase 3 implementation.
 */

/* global PIXI */

export class HexFogOfWar {
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
