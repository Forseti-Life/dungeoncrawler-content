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
  constructor(hexCanvas, bus, options = {}) {
    this.hexCanvas = hexCanvas;
    this.bus = bus;
    this.options = options && typeof options === 'object' ? options : {};

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
    /** @type {string} Last processed room transition id */
    this._lastRoomTransitionId = '';
    /** @type {object|null} Active drag state */
    this._dragState = null;

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
      this.bus.on('room:changed', ({ transition } = {}) => {
        const transitionId = String(transition?.id || '').trim();
        if (transitionId && transitionId === this._lastRoomTransitionId) {
          return;
        }
        if (transitionId) {
          this._lastRoomTransitionId = transitionId;
        }
        this._clearAll();
      })
    );

    this.hexCanvas?.app?.stage?.on('pointermove', this._handleStagePointerMove, this);
    this.hexCanvas?.app?.stage?.on('pointerup', this._handleStagePointerUp, this);
    this.hexCanvas?.app?.stage?.on('pointerupoutside', this._handleStagePointerUp, this);
  }

  destroy() {
    this.hexCanvas?.app?.stage?.off('pointermove', this._handleStagePointerMove, this);
    this.hexCanvas?.app?.stage?.off('pointerup', this._handleStagePointerUp, this);
    this.hexCanvas?.app?.stage?.off('pointerupoutside', this._handleStagePointerUp, this);
    this._cancelDrag();
    this._clearAll();
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (this._tooltipEl) {
      this._tooltipEl.remove();
      this._tooltipEl = null;
    }
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
    this._hideEntityTooltip();
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
    container.eventMode = 'static';
    container.interactive = true;
    container.buttonMode = true;
    container.cursor = 'pointer';

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

    // Facing indicator (RenderComponent.orientation)
    const facing = new PIXI.Graphics();
    facing.name = 'facing';
    facing.visible = false;
    container.addChild(facing);
    this._setFacingIndicator(container, render, hexSize);

    // Selection/turn ring (starts hidden)
    const ring = new PIXI.Graphics();
    ring.name = 'ring';
    ring.visible = false;
    container.addChild(ring);

    // Unconscious/dead status badge (drawn atop the token, corner overlay)
    const status = new PIXI.Graphics();
    status.name = 'status';
    status.visible = false;
    container.addChild(status);
    const statusLabel = new PIXI.Text('\u2620', {
      fontFamily: 'Arial',
      fontSize: Math.max(8, hexSize * 0.32),
      fill: 0xffffff,
    });
    statusLabel.name = 'statusLabel';
    statusLabel.anchor.set(0.5);
    statusLabel.visible = false;
    container.addChild(statusLabel);
    this._setStatusIndicator(container, entity, hexSize);

    container.on('pointerdown', (event) => this._handleTokenPointerDown(container, entity, event));
    container.on('pointerover', () => this._showEntityTooltip(container, entity));
    container.on('pointerout', () => this._hideEntityTooltip(container));

    return container;
  }

  /**
   * Apply/refresh the dead/unconscious visual treatment (dimmed token + skull
   * badge) for a token based on the entity's dcIsDefeated flag. Called at
   * token build time; since tokens are fully rebuilt whenever the active
   * room's entities change, this naturally stays in sync with combat state.
   * @private
   */
  _setStatusIndicator(container, entity, hexSize) {
    if (!container || !window.PIXI) {
      return;
    }
    const status = container.getChildByName?.('status');
    const statusLabel = container.getChildByName?.('statusLabel');
    const isDefeated = Boolean(entity?.dcIsDefeated);

    container.alpha = isDefeated ? 0.5 : 1;

    if (!status || !statusLabel) {
      return;
    }
    if (!isDefeated) {
      status.visible = false;
      status.clear?.();
      statusLabel.visible = false;
      return;
    }

    const size = Number(hexSize) || 32;
    const badgeRadius = size * 0.22;
    const badgeX = size * 0.32;
    const badgeY = -size * 0.32;

    status.clear();
    status.lineStyle(1, 0xffffff, 0.9);
    status.beginFill(0x991b1b, 0.95);
    status.drawCircle(badgeX, badgeY, badgeRadius);
    status.endFill();
    status.visible = true;

    statusLabel.x = badgeX;
    statusLabel.y = badgeY;
    statusLabel.style.fontSize = Math.max(8, size * 0.28);
    statusLabel.visible = true;
  }

  /**
   * Build the hover-tooltip label listing an entity's incapacitation state
   * and active conditions (dcConditions, populated from combat_participants
   * via the map data sync pipeline).
   * @private
   */
  _buildEntityTooltipLines(entity) {
    const identity = entity?.getComponent?.('IdentityComponent');
    const name = identity?.name || 'Entity';
    const conditions = Array.isArray(entity?.dcConditions) ? entity.dcConditions : [];
    const lines = [name];

    if (entity?.dcIsDefeated) {
      const deadOrDying = conditions.find((c) => c?.condition_type === 'dead');
      lines.push(deadOrDying ? 'Dead' : 'Unconscious');
    }

    conditions.forEach((condition) => {
      const label = String(condition?.name || condition?.condition_type || '').trim();
      if (!label) {
        return;
      }
      const value = condition?.value;
      lines.push(Number.isFinite(Number(value)) && Number(value) !== 0 ? `${label} (${value})` : label);
    });

    return lines;
  }

  _ensureTooltipElement() {
    if (this._tooltipEl && this._tooltipEl.isConnected) {
      return this._tooltipEl;
    }
    const el = document.createElement('div');
    el.className = 'hex-token-tooltip';
    el.style.position = 'fixed';
    el.style.display = 'none';
    el.style.pointerEvents = 'none';
    el.style.zIndex = '10000';
    document.body.appendChild(el);
    this._tooltipEl = el;
    return el;
  }

  _showEntityTooltip(container, entity) {
    const isDefeated = Boolean(entity?.dcIsDefeated);
    const hasConditions = Array.isArray(entity?.dcConditions) && entity.dcConditions.length > 0;
    if (!isDefeated && !hasConditions) {
      return;
    }

    const lines = this._buildEntityTooltipLines(entity);
    const tooltip = this._ensureTooltipElement();
    tooltip.innerHTML = lines
      .map((line, index) => `<div class="hex-token-tooltip__line${index === 0 ? ' hex-token-tooltip__line--name' : ''}">${this._escapeTooltipText(line)}</div>`)
      .join('');

    const canvasRect = this.hexCanvas?.app?.view?.getBoundingClientRect?.();
    const globalPos = container.getGlobalPosition?.();
    if (!canvasRect || !globalPos) {
      return;
    }

    tooltip.style.display = 'block';
    tooltip.style.left = `${canvasRect.left + globalPos.x + 12}px`;
    tooltip.style.top = `${canvasRect.top + globalPos.y - 12}px`;
  }

  _hideEntityTooltip() {
    if (this._tooltipEl) {
      this._tooltipEl.style.display = 'none';
    }
  }

  _escapeTooltipText(text) {
    return String(text ?? '').replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[ch]));
  }

  _handleTokenPointerDown(token, entity, event) {
    if (Number(event?.data?.button ?? 0) !== 0) {
      return;
    }

    event?.stopPropagation?.();
    const global = event?.data?.global || null;
    this._dragState = {
      token,
      entity,
      pointerId: event?.data?.pointerId ?? null,
      originX: Number(token.x || 0),
      originY: Number(token.y || 0),
      originQ: Number(entity?.getComponent?.('PositionComponent')?.q || 0),
      originR: Number(entity?.getComponent?.('PositionComponent')?.r || 0),
      startGlobalX: Number(global?.x || 0),
      startGlobalY: Number(global?.y || 0),
      started: false,
      canDrag: this.options?.canDragEntity ? this.options.canDragEntity(entity) === true : false,
    };
  }

  _handleStagePointerMove(event) {
    const drag = this._dragState;
    if (!drag) {
      return;
    }
    if (drag.pointerId !== null && event?.data?.pointerId !== drag.pointerId) {
      return;
    }

    const global = event?.data?.global || null;
    const globalX = Number(global?.x || 0);
    const globalY = Number(global?.y || 0);
    if (!drag.started) {
      const deltaX = globalX - drag.startGlobalX;
      const deltaY = globalY - drag.startGlobalY;
      if (Math.hypot(deltaX, deltaY) < 8) {
        return;
      }
      if (!drag.canDrag) {
        return;
      }
      drag.started = true;
      drag.token.alpha = 0.88;
      drag.token.zIndex = 9999;
      drag.token.parent?.sortChildren?.();
      this.hexCanvas?.setPanEnabled(false);
      this._clearCrowdedHexHoverState();
      this.options?.onDragStart?.(drag.entity);
    }

    const worldPoint = this.hexCanvas?.globalToWorldPoint(globalX, globalY);
    if (!worldPoint) {
      return;
    }
    drag.token.x = worldPoint.x;
    drag.token.y = worldPoint.y;
  }

  async _handleStagePointerUp(event) {
    const drag = this._dragState;
    if (!drag) {
      return;
    }
    if (drag.pointerId !== null && event?.data?.pointerId !== drag.pointerId) {
      return;
    }

    this._dragState = null;
    this.hexCanvas?.setPanEnabled(true);

    if (!drag.started) {
      drag.token.x = drag.originX;
      drag.token.y = drag.originY;
      drag.token.alpha = 1;
      drag.token.zIndex = getZIndex(drag.token._entityType, drag.originQ, drag.originR);
      drag.token.parent?.sortChildren?.();
      this.options?.onTokenSelected?.(drag.entity);
      this.options?.onDragEnd?.(drag.entity, false);
      return;
    }

    const global = event?.data?.global || null;
    const droppedHex = this.hexCanvas?.globalToAxial(global?.x, global?.y);
    let success = false;
    if (droppedHex && Number.isFinite(Number(droppedHex.q)) && Number.isFinite(Number(droppedHex.r))) {
      success = await Promise.resolve(this.options?.onDropEntity?.({
        entity: drag.entity,
        sourceQ: drag.originQ,
        sourceR: drag.originR,
        targetQ: Number(droppedHex.q),
        targetR: Number(droppedHex.r),
      })) === true;
    }

    drag.token.alpha = 1;
    if (!success) {
      drag.token.x = drag.originX;
      drag.token.y = drag.originY;
    }
    drag.token.zIndex = getZIndex(drag.token._entityType, drag.originQ, drag.originR);
    drag.token.parent?.sortChildren?.();
    this.options?.onDragEnd?.(drag.entity, success);
  }

  _cancelDrag() {
    const drag = this._dragState;
    this._dragState = null;
    this.hexCanvas?.setPanEnabled(true);
    if (!drag?.token) {
      return;
    }
    drag.token.x = drag.originX;
    drag.token.y = drag.originY;
    drag.token.alpha = 1;
    drag.token.zIndex = getZIndex(drag.token._entityType, drag.originQ, drag.originR);
    drag.token.parent?.sortChildren?.();
    this.options?.onDragEnd?.(drag.entity, false);
  }

  _setFacingIndicator(container, render, hexSize) {
    if (!container || !window.PIXI) {
      return;
    }

    const facing = container.getChildByName?.('facing');
    if (!facing) {
      return;
    }

    const radians = orientationToRadians(render?.orientation);
    if (!Number.isFinite(radians)) {
      facing.visible = false;
      facing.clear?.();
      return;
    }

    const scale = Number.isFinite(Number(render?.scale)) ? Number(render.scale) : 1;
    const radius = Number(hexSize) * 0.55 * scale;
    const baseBack = Number(hexSize) * 0.18 * scale;
    const halfWidth = Number(hexSize) * 0.14 * scale;

    const tipX = Math.cos(radians) * radius;
    const tipY = Math.sin(radians) * radius;
    const baseCX = Math.cos(radians) * (radius - baseBack);
    const baseCY = Math.sin(radians) * (radius - baseBack);
    const perpX = -Math.sin(radians);
    const perpY = Math.cos(radians);

    const leftX = baseCX + perpX * halfWidth;
    const leftY = baseCY + perpY * halfWidth;
    const rightX = baseCX - perpX * halfWidth;
    const rightY = baseCY - perpY * halfWidth;

    facing.clear();
    facing.lineStyle(1, 0x0b1020, 0.55);
    facing.beginFill(0xffffff, 0.9);
    facing.drawPolygon([tipX, tipY, leftX, leftY, rightX, rightY]);
    facing.endFill();
    facing.visible = true;
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

function orientationToRadians(orientation) {
  const token = String(orientation || '').trim().toLowerCase();
  // Flat-top axial directions.
  // n=up, then clockwise: ne, se, s, sw, nw
  switch (token) {
    case 'n': return -Math.PI / 2;
    case 'ne': return -Math.PI / 6;
    case 'se': return Math.PI / 6;
    case 's': return Math.PI / 2;
    case 'sw': return (Math.PI * 5) / 6;
    case 'nw': return (-Math.PI * 5) / 6;
    default: return NaN;
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
