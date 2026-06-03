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
    /** @type {string|null} Expanded crowded-hex key */
    this._spreadExpandedHexKey = null;
    /** @type {string|null} Hover anchor key while moving between base hex and spread targets */
    this._spreadHoverAnchorKey = null;
    /** @type {number|null} Deferred crowded-hex clear timer */
    this._spreadClearTimer = null;

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
      this.bus.on('hex:hovered', ({ q, r, entities = [] } = {}) => {
        this._handleCrowdedHexHover(q, r, entities);
      }),
      this.bus.on('hex:out', ({ q, r } = {}) => {
        this._scheduleSpreadHoverClear(q, r);
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
    this._clearCrowdedHexHoverState();
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

    this._applyStackingVisibility();
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
    container._baseX = pixelPos.x;
    container._baseY = pixelPos.y;
    container._entityId = entity.id;
    container._entityType = identity?.entityType ?? 'unknown';
    container.dcEntity = entity;

    // Attempt sprite; fall back to circle
    const spriteKey = render.spriteKey;
    const resolvedSpriteKey = spriteKey && (
      PIXI.utils.TextureCache?.[spriteKey]
      ? spriteKey
      : (PIXI.utils.TextureCache?.[`gen_${spriteKey}`] ? `gen_${spriteKey}` : null)
    );
    const texture = resolvedSpriteKey
      ? PIXI.Texture.from(resolvedSpriteKey)
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
    token._baseX = x;
    token._baseY = y;
    token.x = x;
    token.y = y;

    this._applyStackingVisibility();

    if (this._spreadExpandedHexKey === `${Number(pos.q)}:${Number(pos.r)}`) {
      this._setEntitySpreadForHex(pos.q, pos.r, true);
    }
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

  _applyStackingVisibility() {
    const occupied = new Set();
    this._tokens.forEach((token) => {
      const position = token?.dcEntity?.getComponent?.('PositionComponent');
      if (!position) return;
      occupied.add(`${Number(position.q)}:${Number(position.r)}`);
    });

    occupied.forEach((hexKey) => {
      const [q, r] = hexKey.split(':').map((value) => Number(value));
      this._applyStackingVisibilityForHex(q, r);
    });
  }

  _applyStackingVisibilityForHex(q, r) {
    const hexKey = `${Number(q)}:${Number(r)}`;
    const entities = this._getEntitiesAtHex(q, r);

    if (entities.length <= 1) {
      entities.forEach((entity) => {
        const token = this._tokens.get(entity.id);
        if (token) token.visible = true;
      });
      return;
    }

    if (this._spreadExpandedHexKey === hexKey) {
      entities.forEach((entity) => {
        const token = this._tokens.get(entity.id);
        if (token) token.visible = true;
      });
      return;
    }

    const sorted = entities.slice().sort((a, b) => {
      const resolveRank = (entity) => {
        const identityType = entity?.getComponent?.('IdentityComponent')?.entityType ?? entity?.dcEntityType ?? null;
        const objectCategory = entity?.getComponent?.('RenderComponent')?.objectCategory ?? null;
        const type = String(identityType || objectCategory || '').trim().toLowerCase();
        if (type === 'player_character' || type === 'npc' || type === 'creature' || type === 'character') return 3;
        if (type === 'item') return 2;
        if (type === 'obstacle' || type === 'terrain') return 1;
        return 0;
      };

      const rankA = resolveRank(a);
      const rankB = resolveRank(b);
      if (rankA !== rankB) return rankB - rankA;

      const idA = String(a?.id ?? '');
      const idB = String(b?.id ?? '');
      if (idA < idB) return -1;
      if (idA > idB) return 1;
      return 0;
    });

    const topId = sorted[0]?.id ?? null;
    entities.forEach((entity) => {
      const token = this._tokens.get(entity.id);
      if (token) token.visible = entity.id === topId;
    });
  }

  _showHexInspection(q, r, entities = []) {
    if (!window.PIXI) {
      return;
    }

    const qNum = Number(q);
    const rNum = Number(r);
    if (!Number.isFinite(qNum) || !Number.isFinite(rNum)) {
      this._clearHexInspectionState();
      return;
    }

    if (this._inspectionClearTimer) {
      window.clearTimeout(this._inspectionClearTimer);
      this._inspectionClearTimer = null;
    }

    const nextKey = `${qNum}:${rNum}`;
    if (this._inspectionHexKey !== nextKey) {
      this._clearHexInspectionState();
      this._inspectionHexKey = nextKey;
    }

    const inspectedEntities = Array.isArray(entities) && entities.length
      ? entities
      : this._getEntitiesAtHex(qNum, rNum);

    this._inspectionEntityIds = inspectedEntities
      .map((entity) => entity?.id)
      .filter((id) => id != null);

    inspectedEntities.forEach((entity) => this._renderEntityInspectionBadges(entity));
  }

  _scheduleInspectionClear(q, r) {
    if (this._inspectionClearTimer) {
      window.clearTimeout(this._inspectionClearTimer);
    }

    const targetKey = Number.isFinite(Number(q)) && Number.isFinite(Number(r))
      ? `${Number(q)}:${Number(r)}`
      : this._inspectionHexKey;

    this._inspectionClearTimer = window.setTimeout(() => {
      this._inspectionClearTimer = null;

      if (!targetKey) {
        return;
      }
      if (this._spreadHoverAnchorKey === targetKey) {
        return;
      }
      if (this._inspectionHexKey !== targetKey) {
        return;
      }

      this._clearHexInspectionState();
    }, 120);
  }

  _clearHexInspectionState() {
    if (this._inspectionClearTimer) {
      window.clearTimeout(this._inspectionClearTimer);
      this._inspectionClearTimer = null;
    }

    (Array.isArray(this._inspectionEntityIds) ? this._inspectionEntityIds : []).forEach((entityId) => {
      const token = this._tokens.get(entityId);
      const badges = token?.getChildByName?.('attrBadges');
      if (!badges) {
        return;
      }

      badges.visible = false;
      const removed = badges.removeChildren?.() || [];
      removed.forEach((child) => child?.destroy?.({ children: true }));
    });

    this._inspectionEntityIds = [];
    this._inspectionHexKey = null;
  }

  _renderEntityInspectionBadges(entity) {
    const token = this._tokens.get(entity?.id);
    if (!token || !window.PIXI) {
      return;
    }

    const meta = entity?.dcStatePayload?.state?.metadata || entity?.dcStatePayload?.metadata || {};
    const passable = typeof meta.passable === 'boolean' ? meta.passable : null;
    const blocksMovement = typeof meta.blocks_movement === 'boolean' ? meta.blocks_movement : null;
    const movable = typeof meta.movable === 'boolean' ? meta.movable : null;
    const collectible = typeof meta.collectible === 'boolean' ? meta.collectible : null;
    const stackable = typeof meta.stackable === 'boolean' ? meta.stackable : null;

    const show = passable !== null || blocksMovement !== null || movable !== null || collectible !== null || stackable !== null;

    let badges = token.getChildByName?.('attrBadges') || null;
    if (!badges && show) {
      badges = new PIXI.Container();
      badges.name = 'attrBadges';
      token.addChild(badges);
    }

    if (!badges) {
      return;
    }

    const removed = badges.removeChildren?.() || [];
    removed.forEach((child) => child?.destroy?.({ children: true }));

    badges.visible = Boolean(show);
    if (!show) {
      return;
    }

    const hexSize = Number(this.hexCanvas?.config?.hexSize || 30);
    const radius = Math.max(6, hexSize * 0.12);
    const spacing = Math.max(2, radius * 0.4);

    badges.x = -hexSize * 0.55;
    badges.y = -hexSize * 0.55;

    const entries = [
      { label: 'P', value: passable, trueColor: 0x22c55e, falseColor: 0xef4444 },
      { label: 'B', value: blocksMovement, trueColor: 0xef4444, falseColor: 0x22c55e },
      { label: 'M', value: movable, trueColor: 0xfbbf24, falseColor: 0x64748b },
      { label: 'C', value: collectible, trueColor: 0x38bdf8, falseColor: 0x64748b },
      { label: 'S', value: stackable, trueColor: 0xa855f7, falseColor: 0x64748b },
    ].filter((entry) => typeof entry.value === 'boolean');

    entries.forEach((entry, index) => {
      const g = new PIXI.Graphics();
      const color = entry.value ? entry.trueColor : entry.falseColor;
      g.beginFill(color, 0.9);
      g.drawCircle(0, 0, radius);
      g.endFill();
      g.x = index * (radius * 2 + spacing);
      g.y = 0;

      const text = new PIXI.Text(entry.label, {
        fontFamily: 'Arial',
        fontSize: Math.max(8, radius * 1.2),
        fill: 0x0b1020,
        align: 'center',
      });
      text.anchor.set(0.5);
      text.x = g.x;
      text.y = 0;

      badges.addChild(g);
      badges.addChild(text);
    });
  }

  _handleCrowdedHexHover(q, r, entities = []) {
    if (!Number.isFinite(Number(q)) || !Number.isFinite(Number(r))) {
      this._clearCrowdedHexHoverState();
      return;
    }

    const crowdedEntities = Array.isArray(entities) && entities.length
      ? entities
      : this._getEntitiesAtHex(q, r);
    const nextKey = `${Number(q)}:${Number(r)}`;
    if (this._spreadClearTimer) {
      window.clearTimeout(this._spreadClearTimer);
      this._spreadClearTimer = null;
    }
    this._spreadHoverAnchorKey = crowdedEntities.length > 1 ? nextKey : null;

    if (this._spreadExpandedHexKey && this._spreadExpandedHexKey !== nextKey) {
      this._clearCrowdedHexHoverState();
    }
    if (crowdedEntities.length > 1) {
      this._setEntitySpreadForHex(q, r, true);
    } else if (this._spreadExpandedHexKey === nextKey) {
      this._clearCrowdedHexHoverState();
    }
  }

  _getEntitiesAtHex(q, r) {
    return Array.from(this._tokens.values())
      .map((token) => token?.dcEntity || null)
      .filter((entity) => {
        const position = entity?.getComponent?.('PositionComponent');
        return position && Number(position.q) === Number(q) && Number(position.r) === Number(r);
      });
  }

  _setEntitySpreadForHex(q, r, active) {
    const entities = this._getEntitiesAtHex(q, r);
    if (!entities.length) {
      return;
    }

    const hexKey = `${Number(q)}:${Number(r)}`;
    const spreadRadius = Number(this.hexCanvas?.config?.hexSize || 30);
    if (!active || entities.length <= 1) {
      entities.forEach((entity) => {
        const token = this._tokens.get(entity.id);
        if (!token) {
          return;
        }
        token.x = Number(token._baseX || 0);
        token.y = Number(token._baseY || 0);
      });
      if (this._spreadExpandedHexKey === hexKey) {
        this._spreadExpandedHexKey = null;
      }
      this._clearSpreadInteractionTargets();
      this._applyStackingVisibilityForHex(q, r);
      return;
    }

    entities.forEach((entity, index) => {
      const token = this._tokens.get(entity.id);
      if (!token) {
        return;
      }
      const angle = ((Math.PI * 2) / entities.length) * index - (Math.PI / 2);
      token.x = Number(token._baseX || 0) + Math.cos(angle) * spreadRadius;
      token.y = Number(token._baseY || 0) + Math.sin(angle) * spreadRadius;
      token.visible = true;
    });

    this._spreadExpandedHexKey = hexKey;
    this._applyStackingVisibilityForHex(q, r);
    this._refreshSpreadInteractionTargets(q, r);
  }

  _clearSpreadInteractionTargets() {
    const interactionContainer = this.hexCanvas?.interactionContainer;
    if (!interactionContainer) {
      return;
    }
    interactionContainer.removeChildren().forEach((child) => {
      child.destroy?.({ children: true });
    });
  }

  _getRenderedEntityCenter(entity) {
    const token = this._tokens.get(entity?.id);
    if (!token) {
      return null;
    }
    return {
      x: Number(token.x || 0),
      y: Number(token.y || 0),
    };
  }

  _refreshSpreadInteractionTargets(q, r) {
    this._clearSpreadInteractionTargets();

    const interactionContainer = this.hexCanvas?.interactionContainer;
    if (!interactionContainer || !window.PIXI) {
      return;
    }

    const entities = this._getEntitiesAtHex(q, r);
    if (entities.length <= 1) {
      return;
    }

    const hexKey = `${Number(q)}:${Number(r)}`;
    entities.forEach((entity) => {
      const center = this._getRenderedEntityCenter(entity);
      if (!center) {
        return;
      }

      const target = new PIXI.Graphics();
      target.beginFill(0xffffff, 0.001);
      target.drawCircle(0, 0, this.hexCanvas.config.hexSize * 0.42);
      target.endFill();
      target.x = center.x;
      target.y = center.y;
      target.zIndex = 9500;
      target.eventMode = 'static';
      target.interactive = true;
      target.cursor = 'pointer';

      target.on('pointerover', () => {
        if (this._spreadClearTimer) {
          window.clearTimeout(this._spreadClearTimer);
          this._spreadClearTimer = null;
        }
        this._spreadHoverAnchorKey = hexKey;
        this.bus.emit('hex:hovered', {
          q: Number(q),
          r: Number(r),
          entities: this._getEntitiesAtHex(q, r),
          source: 'spread-target',
        });
      });

      target.on('pointerout', () => {
        if (this._spreadHoverAnchorKey === hexKey) {
          this._spreadHoverAnchorKey = null;
        }
        this._scheduleSpreadHoverClear(q, r);
      });

      target.on('pointertap', (event) => {
        event?.stopPropagation?.();
        this.bus.emit('hex:clicked', {
          q: Number(q),
          r: Number(r),
          button: 0,
          entities: [entity],
          source: 'spread-target',
        });
      });

      interactionContainer.addChild(target);
    });
  }

  _scheduleSpreadHoverClear(q, r) {
    if (this._spreadClearTimer) {
      window.clearTimeout(this._spreadClearTimer);
    }

    const targetKey = Number.isFinite(Number(q)) && Number.isFinite(Number(r))
      ? `${Number(q)}:${Number(r)}`
      : this._spreadExpandedHexKey;
    this._spreadClearTimer = window.setTimeout(() => {
      this._spreadClearTimer = null;
      if (!targetKey || this._spreadHoverAnchorKey === targetKey) {
        return;
      }
      if (this._spreadExpandedHexKey !== targetKey) {
        return;
      }
      this._clearCrowdedHexHoverState();
      this.bus.emit('hex:out', { q: Number(q), r: Number(r), source: 'spread-target' });
    }, 120);
  }

  _clearCrowdedHexHoverState() {
    if (this._spreadClearTimer) {
      window.clearTimeout(this._spreadClearTimer);
      this._spreadClearTimer = null;
    }

    const expandedKey = this._spreadExpandedHexKey;
    if (expandedKey) {
      const [q, r] = expandedKey.split(':').map((value) => Number(value));
      this._setEntitySpreadForHex(q, r, false);
    }

    this._clearSpreadInteractionTargets();
    this._spreadExpandedHexKey = null;
    this._spreadHoverAnchorKey = null;
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
