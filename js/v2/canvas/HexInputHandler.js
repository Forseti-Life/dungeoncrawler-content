/**
 * @file canvas/HexInputHandler.js
 *
 * Translates low-level PIXI pointer events from HexCanvas into enriched
 * interaction events that panels and GameShell can consume.
 */

export class HexInputHandler {
  /**
   * @param {import('./HexCanvas').HexCanvas} hexCanvas
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(hexCanvas, bus) {
    this.hexCanvas = hexCanvas;
    this.bus = bus;
    this._unsubs = [];
  }

  init() {
    this._unsubs.push(
      this.bus.on('canvas:hex-hovered', ({ q, r } = {}) => {
        this.bus.emit('hex:hovered', {
          q,
          r,
          entities: this._getEntitiesAtHex(q, r),
        });
      }),
      this.bus.on('canvas:hex-out', ({ q, r } = {}) => {
        this.bus.emit('hex:out', { q, r });
      }),
      this.bus.on('canvas:hex-clicked', ({ q, r, button = 0 } = {}) => {
        this.bus.emit('hex:clicked', {
          q,
          r,
          button,
          entities: this._getEntitiesAtHex(q, r),
        });
      }),
    );
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _getEntitiesAtHex(q, r) {
    const objectContainer = this.hexCanvas?.objectContainer;
    if (!objectContainer || !Number.isFinite(Number(q)) || !Number.isFinite(Number(r))) {
      return [];
    }

    return objectContainer.children
      .map((child) => child?.dcEntity || null)
      .filter((entity) => {
        const position = entity?.getComponent?.('PositionComponent');
        return position && Number(position.q) === Number(q) && Number(position.r) === Number(r);
      });
  }
}
