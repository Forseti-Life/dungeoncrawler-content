/**
 * @file canvas/HexInputHandler.js
 *
 * Translates PIXI pointer events on the hex grid into GameEventBus events.
 *
 * Fires bus events:
 *   hex:hovered   — { q, r, entities }
 *   hex:clicked   — { q, r, entities, button }
 *   hex:out       — { q, r }
 *
 * NOT responsible for game logic responses to these events
 * (GameShell/systems handle those).
 *
 * Phase 2 implementation.
 */

export class HexInputHandler {
  /**
   * @param {import('./HexCanvas').HexCanvas} hexCanvas
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(hexCanvas, bus) {
    this.hexCanvas = hexCanvas;
    this.bus = bus;
  }

  init() {}

  destroy() {}
}
