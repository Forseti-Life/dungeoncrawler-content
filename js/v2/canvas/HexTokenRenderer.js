/**
 * @file canvas/HexTokenRenderer.js
 *
 * Renders ECS entities as PIXI sprites/graphics on the hex grid.
 *
 * Each entity with PositionComponent + RenderComponent gets a token container
 * in the objectContainer layer. Tokens are keyed by entity.id.
 *
 * Rendering strategy:
 *   - If RenderComponent.spriteKey resolves to a loaded PIXI texture → PIXI.Sprite
 *   - Fallback: PIXI.Graphics circle colored by entity type
 *
 * Fires no bus events (rendering only).
 *
 * Subscribes to bus events:
 *   room:entities-changed  — rebuild tokens for full entity set
 *   entity:selected        — highlight selected entity token
 *   entity:deselected      — remove selected highlight
 *   entity:moved           — reposition token to new hex
 *   combat:turn-changed    — highlight active-turn entity token
 */

/* global PIXI */

/** @import { HexCanvas } from './HexCanvas.js' */

/** Fill colors by entity type (fallback circle rendering) */
const TYPE_COLORS = {
  player_character: 0x3b82f6, // blue
  npc: 0x22c55e,              // green
  creature: 0xef4444,         // red
  item: 0xf59e0b,             // amber
  obstacle: 0x6b7280,         // gray
};

/** Entity type z-index layers (match old hexmap getEntityRenderZIndex) */
function getZIndex(entityType, q = 0, r = 0) {
  const depth = r * 100 + q;
  switch (entityType) {
    case 'obstacle':        return 1000 + depth;
    case 'item':            return 2000 + depth;
    case 'creature':
    case 'npc':             return 3000 + depth;
    case 'player_character': return 4000 + depth;
    default:                return 2500 + depth;
  }
}

export class HexTokenRenderer {
  /**
   * @param {HexCanvas} hexCanvas
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(hexCanvas, bus) {
    this.hexCanvas = hexCanvas;
    this.bus = bus;

    /** @type {Map<string|number, PIXI.Container>} entityId → token container */
    this._tokens = new Map();
    /** @type {string|number|null} Currently selected entity id */
    this._selectedId = null;
    /** @type {string|number|null} Active combat turn entity id */
    this._activeTurnId = null;

    this._unsubs = [];
  }

  init() {
    this._unsubs.push(
      this.bus.on('room:entities-changed', ({ entities } = {}) => {
        this._rebuildAll(entities ?? []);
      }),
      this.bus.on('entity:selected', ({ entity } = {}) => {
        const prev = this._selectedId;
        this._selectedId = entity?.id ?? null;
        if (prev != null) this._refreshTokenHighlight(prev);
        if (this._selectedId != null) this._refreshTokenHighlight(this._selectedId);
      }),
      this.bus.on('entity:deselected', () => {
        const prev = this._selectedId;
        this._selectedId = null;
        if (prev != null) this._refreshTokenHighlight(prev);
      }),
      this.bus.on('entity:moved', ({ entity } = {}) => {
        if (entity) this._repositionToken(entity);
      }),
      this.bus.on('combat:turn-changed', ({ entity } = {}) => {
        const prev = this._activeTurnId;
        this._activeTurnId = entity?.id ?? null;
        if (prev != null) this._refreshTokenHighlight(prev);
        if (this._activeTurnId != null) this._refreshTokenHighlight(this._activeTurnId);
      }),
      this.bus.on('room:changed', () => {
        this._clearAll();
      })
    );
  }

  destroy() {
    this._clearAll();
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Private
  // ---------------------------------------------------------------------------

  /**
   * Clear all tokens from the object container.
   * @private
   */
  _clearAll() {
    this._tokens.forEach((container) => {
      container.parent?.removeChild(container);
      container.destroy({ children: true });
    });
    this._tokens.clear();
    this._selectedId = null;
    this._activeTurnId = null;
  }

  /**
   * Rebuild token set from a list of ECS entities.
   * @param {Array<object>} entities - ECS Entity objects
   * @private
   */
  _rebuildAll(entities) {
    this._clearAll();

    const objectContainer = this.hexCanvas?.objectContainer;
    if (!objectContainer || !window.PIXI) return;

    for (const entity of entities) {
      const pos = entity.getComponent?.('PositionComponent');
      const render = entity.getComponent?.('RenderComponent');
      const identity = entity.getComponent?.('IdentityComponent');
      if (!pos || !render || render.visible === false) continue;

      const token = this._buildTokenContainer(entity, pos, render, identity);
      token.zIndex = getZIndex(identity?.entityType ?? 'unknown', pos.q, pos.r);
      objectContainer.addChild(token);
      this._tokens.set(entity.id, token);
    }

    objectContainer.sortableChildren = true;
    objectContainer.sortChildren?.();
  }

  /**
   * Build a PIXI.Container token for a single entity.
   * @param {object} entity
   * @param {object} pos PositionComponent
   * @param {object} render RenderComponent
   * @param {object|null} identity IdentityComponent
   * @returns {PIXI.Container}
   * @private
   */
  _buildTokenContainer(entity, pos, render, identity) {
    const hexCanvas = this.hexCanvas;
    const hexSize = hexCanvas.config.hexSize;
    const pixelPos = hexCanvas.axialToPixel(pos.q, pos.r, hexSize);

    const container = new PIXI.Container();
    container.x = pixelPos.x;
    container.y = pixelPos.y;
    container._entityId = entity.id;
    container._entityType = identity?.entityType ?? 'unknown';

    // Attempt sprite; fall back to circle
    const spriteKey = render.spriteKey;
    const texture = spriteKey && PIXI.utils.TextureCache?.[spriteKey]
      ? PIXI.Texture.from(spriteKey)
      : null;

    if (texture) {
      const sprite = new PIXI.Sprite(texture);
      const scale = render.scale ?? 1.0;
      sprite.anchor.set(0.5);
      sprite.scale.set((hexSize * 1.6 * scale) / Math.max(sprite.width, sprite.height));
      container.addChild(sprite);
    } else {
      // Fallback: colored primitive
      const g = new PIXI.Graphics();
      const color = parseHexColor(render.objectColor) ?? TYPE_COLORS[identity?.entityType] ?? 0xffffff;
      const scale = Number.isFinite(render.scale) ? render.scale : 1;
      const radius = hexSize * 0.38 * scale;
      g.beginFill(color, 0.85);
      if (identity?.entityType === 'obstacle' || render.objectCategory === 'obstacle') {
        const side = radius * 2;
        const cornerRadius = Math.max(4, radius * 0.22);
        g.drawRoundedRect(-radius, -radius, side, side, cornerRadius);
      } else {
        g.drawCircle(0, 0, radius);
      }
      g.endFill();
      container.addChild(g);
    }

    // Name label
    if (identity?.name) {
      const label = new PIXI.Text(identity.name, {
        fontFamily: 'Arial',
        fontSize: Math.max(8, hexSize * 0.28),
        fill: 0xffffff,
        align: 'center',
      });
      label.anchor.set(0.5, 0);
      label.y = hexSize * 0.45;
      container.addChild(label);
    }

    // Selection/turn ring (starts hidden)
    const ring = new PIXI.Graphics();
    ring.name = 'ring';
    ring.visible = false;
    container.addChild(ring);

    return container;
  }

  /**
   * Reposition a token after entity moves to a new hex.
   * @param {object} entity
   * @private
   */
  _repositionToken(entity) {
    const token = this._tokens.get(entity.id);
    if (!token) return;
    const pos = entity.getComponent?.('PositionComponent');
    if (!pos) return;
    const { x, y } = this.hexCanvas.axialToPixel(pos.q, pos.r, this.hexCanvas.config.hexSize);
    token.x = x;
    token.y = y;
  }

  /**
   * Refresh the ring highlight on a token based on current selection/turn state.
   * @param {string|number} entityId
   * @private
   */
  _refreshTokenHighlight(entityId) {
    const token = this._tokens.get(entityId);
    if (!token || !window.PIXI) return;

    const ring = token.getChildByName?.('ring');
    if (!ring) return;

    const isSelected = entityId === this._selectedId;
    const isActiveTurn = entityId === this._activeTurnId;
    const hexSize = this.hexCanvas.config.hexSize;
    const radius = hexSize * 0.44;

    ring.clear();
    ring.visible = isSelected || isActiveTurn;

    if (!ring.visible) return;

    const color = isActiveTurn ? 0xfbbf24 : 0x60a5fa; // gold for active turn, blue for selection
    ring.lineStyle(2, color, 0.9);
    ring.drawCircle(0, 0, radius);
  }
}

function parseHexColor(value) {
  if (typeof value !== 'string') return null;
  const normalized = value.trim();
  if (/^#[0-9a-f]{6}$/i.test(normalized)) {
    return Number.parseInt(normalized.slice(1), 16);
  }
  if (/^0x[0-9a-f]{6}$/i.test(normalized)) {
    return Number.parseInt(normalized.slice(2), 16);
  }
  return null;
}
