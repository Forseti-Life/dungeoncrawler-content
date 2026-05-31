/**
 * @file canvas/HexCanvas.js
 *
 * PIXI.js application setup and hex grid rendering.
 *
 * Responsibilities:
 *   - Initialize PIXI.Application and attach to DOM container
 *   - Generate hex grid geometry (axial coordinates)
 *   - Render terrain tiles, room connections, compass rose, room banner
 *   - Hex coordinate math: axialToPixel, pixelToAxial, roundAxial
 *
 * NOT responsible for:
 *   - Entity tokens (HexTokenRenderer)
 *   - Fog of war (HexFogOfWar)
 *   - Pointer/input events (HexInputHandler)
 *
 * Subscribes to bus events:
 *   room:changed  — re-renders hex grid for new room
 *
 * @see HexTokenRenderer
 * @see HexFogOfWar
 * @see HexInputHandler
 */

/* global PIXI */

export class HexCanvas {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   * @param {object} config - { hexSize, gridWidth, gridHeight, minZoom, maxZoom }
   */
  constructor(container, bus, config = {}) {
    this.container = container;
    this.bus = bus;
    this.config = config;
    this.app = null;
    this._unsubs = [];
  }

  /**
   * Initialize PIXI app and subscribe to bus events.
   * Phase 2 implementation.
   */
  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this.app?.destroy(true);
    this.app = null;
  }
}
