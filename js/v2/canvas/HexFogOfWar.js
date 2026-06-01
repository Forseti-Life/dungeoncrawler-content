/**
 * @file canvas/HexFogOfWar.js
 *
 * Renders vision radius and fog-of-war overlay on the hex grid.
 *
 * The fog overlay is a PIXI.Graphics in the fxContainer that darkens
 * any hex outside the selected entity's visible set.
 *
 * Visibility algorithm:
 *   1. Enumerate all hex tiles in hexContainer
 *   2. For each hex: compute hex-distance from actor origin
 *   3. If within visionRange AND has line-of-sight → visible
 *   4. Actor's own hex is always visible
 *
 * Vision range derivation (matches old hexmap):
 *   base=8 + clamp(floor(perception/4), -2, 2), clamped to [4, 12]
 *
 * Line-of-sight algorithm:
 *   Axial-linear interpolation; an obstacle with passable=false blocks LOS.
 *
 * Subscribes to bus events:
 *   entity:selected   — recalculate and render fog for selected entity
 *   entity:deselected — clear fog overlay
 *   room:changed      — clear fog overlay
 *
 * Fires no bus events (rendering only).
 */

/* global PIXI */

/** Cube-coordinate distance */
function hexDistance(q1, r1, q2, r2) {
  return Math.max(Math.abs(q1 - q2), Math.abs(r1 - r2), Math.abs((q1 + r1) - (q2 + r2)));
}

export class HexFogOfWar {
  /**
   * @param {import('./HexCanvas').HexCanvas} hexCanvas
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(hexCanvas, bus) {
    this.hexCanvas = hexCanvas;
    this.bus = bus;
    /** @type {PIXI.Graphics|null} Active fog overlay */
    this._fogOverlay = null;
    this._unsubs = [];
    this._enabled = true;
    this._selectedEntity = null;
  }

  init() {
    this._unsubs.push(
      this.bus.on('entity:selected', ({ entity } = {}) => {
        this._selectedEntity = entity ?? null;
        this._refresh(entity ?? null);
      }),
      this.bus.on('entity:deselected', () => {
        this._selectedEntity = null;
        this._clearFog();
      }),
      this.bus.on('room:changed', () => {
        this._selectedEntity = null;
        this._clearFog();
      }),
      this.bus.on('canvas:fog-toggled', ({ enabled } = {}) => {
        this._enabled = Boolean(enabled);
        if (!this._enabled) {
          this._clearFog();
          return;
        }
        this._refresh(this._selectedEntity);
      })
    );
  }

  destroy() {
    this._clearFog();
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Private
  // ---------------------------------------------------------------------------

  /**
   * Recompute visible set for the given actor and render fog overlay.
   * If actor is null or not a player entity, fog is cleared.
   * @param {object|null} entity - ECS Entity
   * @private
   */
  _refresh(entity) {
    const fxContainer = this.hexCanvas?.fxContainer;
    const hexContainer = this.hexCanvas?.hexContainer;
    if (!fxContainer || !hexContainer || !window.PIXI) return;
    if (!this._enabled) {
      this._clearFog();
      return;
    }

    // Only show fog for player-team entities
    const combat = entity?.getComponent?.('CombatComponent');
    const isPlayer = combat?.isPlayerTeam?.() || combat?.team === 'player';
    if (!entity || !isPlayer) {
      this._clearFog();
      return;
    }

    const actorPos = entity.getComponent?.('PositionComponent');
    if (!actorPos) {
      this._clearFog();
      return;
    }

    this._clearFog();

    const visibleSet = this._computeVisibleSet(entity, actorPos, hexContainer);
    const fogOverlay = this._buildFogOverlay(hexContainer, visibleSet);

    fxContainer.addChild(fogOverlay);
    this._fogOverlay = fogOverlay;
  }

  /**
   * Remove the current fog overlay PIXI.Graphics.
   * @private
   */
  _clearFog() {
    if (this._fogOverlay) {
      this._fogOverlay.parent?.removeChild(this._fogOverlay);
      this._fogOverlay.destroy();
      this._fogOverlay = null;
    }
  }

  /**
   * Build a PIXI.Graphics that darkens all non-visible hexes.
   * @param {PIXI.Container} hexContainer
   * @param {Set<string>} visibleSet - Set of "q_r" keys
   * @returns {PIXI.Graphics}
   * @private
   */
  _buildFogOverlay(hexContainer, visibleSet) {
    const hexCanvas = this.hexCanvas;
    const hexSize = hexCanvas.config.hexSize;
    const overlay = new PIXI.Graphics();
    overlay.interactive = false;
    overlay.eventMode = 'none';

    hexContainer.children.forEach((hex) => {
      const data = hex?.hexData;
      if (!data) return;
      if (visibleSet.has(`${data.q}_${data.r}`)) return;

      const pos = hexCanvas.axialToPixel(data.q, data.r, hexSize);
      overlay.beginFill(0x020617, 0.72);
      overlay.lineStyle(0, 0x000000, 0);
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = pos.x + hexSize * Math.cos(angle);
        const y = pos.y + hexSize * Math.sin(angle);
        i === 0 ? overlay.moveTo(x, y) : overlay.lineTo(x, y);
      }
      overlay.closePath();
      overlay.endFill();
    });

    return overlay;
  }

  /**
   * Compute the visible hex set for an entity using range + LOS.
   * @param {object} entity - ECS Entity
   * @param {object} actorPos - PositionComponent
   * @param {PIXI.Container} hexContainer
   * @returns {Set<string>} Set of "q_r" visible hex keys
   * @private
   */
  _computeVisibleSet(entity, actorPos, hexContainer) {
    const visible = new Set();
    const range = this._getVisionRange(entity);

    hexContainer.children.forEach((hex) => {
      const data = hex?.hexData;
      if (!data) return;
      const dist = hexDistance(actorPos.q, actorPos.r, data.q, data.r);
      if (dist > range) return;
      if (this._hasLineOfSight(actorPos.q, actorPos.r, data.q, data.r)) {
        visible.add(`${data.q}_${data.r}`);
      }
    });

    // Actor's own hex is always visible
    visible.add(`${actorPos.q}_${actorPos.r}`);
    return visible;
  }

  /**
   * Return vision range for an entity.
   * base=8 + clamp(floor(perception/4), -2, 2), result in [4, 12].
   * @param {object} entity
   * @returns {number}
   * @private
   */
  _getVisionRange(entity) {
    const stats = entity?.getComponent?.('StatsComponent');
    const perception = Number(stats?.perception ?? 0);
    const derived = 8 + Math.max(-2, Math.min(2, Math.floor(perception / 4)));
    return Math.max(4, Math.min(12, derived));
  }

  /**
   * Axial line-of-sight: return false if an impassable obstacle is in the path.
   * @param {number} fromQ
   * @param {number} fromR
   * @param {number} toQ
   * @param {number} toR
   * @returns {boolean}
   * @private
   */
  _hasLineOfSight(fromQ, fromR, toQ, toR) {
    if (fromQ === toQ && fromR === toR) return true;

    const line = this._axialLine(fromQ, fromR, toQ, toR);
    for (let i = 1; i < line.length - 1; i++) {
      const { q, r } = line[i];
      if (!this._isPassable(q, r)) return false;
    }
    return true;
  }

  /**
   * Return false if an impassable obstacle occupies hex (q, r).
   * Checks objectContainer children for entities tagged obstacle+!passable.
   * @param {number} q
   * @param {number} r
   * @returns {boolean}
   * @private
   */
  _isPassable(q, r) {
    const objectContainer = this.hexCanvas?.objectContainer;
    if (!objectContainer) return true;
    for (const child of objectContainer.children) {
      if (!child._entityType) continue;
      if (child._entityType !== 'obstacle') continue;
      // Derive hex from position (token container x/y → axialFromPixel)
      const pos = this.hexCanvas.pixelToAxial(child.x, child.y);
      if (pos.q === q && pos.r === r) return false; // obstacle at this hex blocks LOS
    }
    return true;
  }

  /**
   * Build an array of axial coordinates along the line from origin to target.
   * Uses cubic interpolation with cube-coordinate rounding.
   * @param {number} fromQ
   * @param {number} fromR
   * @param {number} toQ
   * @param {number} toR
   * @returns {Array<{q:number,r:number}>}
   * @private
   */
  _axialLine(fromQ, fromR, toQ, toR) {
    const dist = hexDistance(fromQ, fromR, toQ, toR);
    if (dist === 0) return [{ q: fromQ, r: fromR }];

    const toCube = (q, r) => ({ x: q, z: r, y: -q - r });
    const a = toCube(fromQ, fromR);
    const b = toCube(toQ, toR);
    const points = [];

    for (let step = 0; step <= dist; step++) {
      const t = step / dist;
      const fx = a.x + (b.x - a.x) * t;
      const fy = a.y + (b.y - a.y) * t;
      const fz = a.z + (b.z - a.z) * t;

      let rx = Math.round(fx);
      let ry = Math.round(fy);
      let rz = Math.round(fz);
      const dx = Math.abs(rx - fx);
      const dy = Math.abs(ry - fy);
      const dz = Math.abs(rz - fz);

      if (dx > dy && dx > dz) {
        rx = -ry - rz;
      } else if (dy > dz) {
        ry = -rx - rz;
      } else {
        rz = -rx - ry;
      }

      points.push({ q: rx, r: rz });
    }

    return points;
  }
}
