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
        const entities = this._getEntitiesAtHex(q, r);
        const top = this._pickTopEntity(entities);
        this.bus.emit('hex:clicked', {
          q,
          r,
          button,
          entities: top ? [top] : [],
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

  _resolveStackRank(entity) {
    const identityType = entity?.getComponent?.('IdentityComponent')?.entityType ?? entity?.dcEntityType ?? null;
    const objectCategory = entity?.getComponent?.('RenderComponent')?.objectCategory ?? null;
    const type = String(identityType || objectCategory || '').trim().toLowerCase();

    if (type === 'player_character' || type === 'npc' || type === 'creature' || type === 'character') {
      return 3;
    }
    if (type === 'item') {
      return 2;
    }
    if (type === 'obstacle' || type === 'terrain') {
      return 1;
    }
    return 0;
  }

  _pickTopEntity(entities) {
    if (!Array.isArray(entities) || entities.length === 0) {
      return null;
    }
    if (entities.length === 1) {
      return entities[0];
    }

    const sorted = entities.slice().sort((a, b) => {
      const rankA = this._resolveStackRank(a);
      const rankB = this._resolveStackRank(b);
      if (rankA !== rankB) {
        return rankB - rankA;
      }
      const idA = String(a?.id ?? '');
      const idB = String(b?.id ?? '');
      if (idA < idB) return -1;
      if (idA > idB) return 1;
      return 0;
    });

    return sorted[0] || null;
  }
}
