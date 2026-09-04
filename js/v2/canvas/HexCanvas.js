/**
 * @file canvas/HexCanvas.js
 *
 * PIXI.js application setup, hex grid rendering, and coordinate math.
 *
 * Scene layer contract (deterministic z-order):
 *   background   |  5  | world  | Room art
 *   hex          | 10  | world  | Terrain base hexes
 *   grid         | 20  | world  | Grid lines, coordinates
 *   props        | 25  | world  | Static scene props
 *   object       | 30  | world  | Entity tokens
 *   fx           | 35  | world  | Fog, lighting, FX
 *   ui           | 40  | world  | World-space UI overlays
 *   interaction  | 45  | world  | Hit areas, pointer capture
 *   hud          | 50  | screen | Fixed HUD (compass, banner)
 *
 * World-space layers (5–45) move together on pan/zoom.
 * Screen-space layers (50+) stay fixed.
 *
 * Fires bus events:
 *   canvas:hex-hovered  — { q, r }
 *   canvas:hex-out      — { q, r }
 *   canvas:hex-clicked  — { q, r, button }
 *   canvas:zoom-changed — { scale }
 *
 * Subscribes to bus events:
 *   room:changed               — regenerate hex grid for new room
 *   map:changed                — regenerate hex grid for a level map aggregate
 *                                ({ mapId, placements, ports, links, occupancy });
 *                                additive to room:changed, see _renderMapAggregate
 *   canvas:coordinates-toggled — redraw grid labels
 *   canvas:grid-toggled        — redraw grid lines
 *   canvas:reset-view          — restore default camera transform
 */

/* global PIXI */

export class HexCanvas {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   * @param {object} config
   * @param {number} [config.hexSize=30]
   * @param {number} [config.gridWidth=20]
   * @param {number} [config.gridHeight=20]
   * @param {number} [config.minZoom=0.5]
   * @param {number} [config.maxZoom=3.0]
   * @param {number} [config.backgroundColor=0x1a1a2e]
   */
  constructor(container, bus, config = {}) {
    this.container = container;
    this.bus = bus;
    this.config = {
      hexSize: config.hexSize ?? 30,
      gridWidth: config.gridWidth ?? 20,
      gridHeight: config.gridHeight ?? 20,
      minZoom: config.minZoom ?? 0.5,
      maxZoom: config.maxZoom ?? 3.0,
      backgroundColor: config.backgroundColor ?? 0x1a1a2e,
      showCoordinates: config.showCoordinates ?? false,
      showGrid: config.showGrid ?? true,
      showHexIndicators: config.showHexIndicators ?? true,
    };

    /** @type {PIXI.Application|null} */
    this.app = null;

    // World-space layer containers (pan/zoom together)
    this.backgroundContainer = null;
    this.hexContainer = null;
    this.gridContainer = null;
    this.propsContainer = null;
    this.objectContainer = null;
    this.fxContainer = null;
    this.uiContainer = null;
    this.interactionContainer = null;

    // Screen-space HUD (fixed)
    this.hudContainer = null;

    /** @type {PIXI.Container|null} Banner container in HUD */
    this._roomBanner = null;
    this.currentRoom = null;
    this.currentRoomId = null;

    /**
     * Level-map aggregate from the additive `map:changed` contract. When set it
     * takes precedence over currentRoom in generateHexGrid(). The Room Editor
     * and the gameplay Map tab never emit map:changed, so their rendering path
     * is untouched. The canvas knows nothing about dungeons, drafts, or
     * commands: every hex here is already in level space.
     * @type {{mapId: string|null, placements: Array, ports: Array, links: Array, occupancy: object}|null}
     */
    this.currentMap = null;

    this._unsubs = [];
    this._lastRoomTransitionId = '';
    this._wheelHandler = null;
    this._leaveHandler = null;
    this._contextMenuHandler = null;
    this._panEnabled = true;

    // World-space hover inspector UI
    this._hoverHexOutline = null;
    this._hoverHexTooltip = null;
    this._hoverHexTooltipBg = null;
    this._hoverHexTooltipText = null;
    this._movementBandOverlay = null;
  }

  // ---------------------------------------------------------------------------
  // Public: lifecycle
  // ---------------------------------------------------------------------------

  /** Initialize PIXI app, build scene layers, generate initial hex grid. */
  init() {
    if (!window.PIXI) {
      console.warn('[HexCanvas] PIXI not loaded — canvas will not initialize');
      return;
    }

    this._initPixiApp();
    this._buildSceneLayers();
    this._setupPanZoom();
    this._contextMenuHandler = (event) => {
      event.preventDefault();
    };
    this.app?.view?.addEventListener('contextmenu', this._contextMenuHandler);
    this.generateHexGrid();
    this.drawCompassRose();

    this._unsubs.push(
      this.bus.on('room:changed', ({ roomId, room, transition } = {}) => {
        const transitionId = String(transition?.id || '').trim();
        if (transitionId && transitionId === this._lastRoomTransitionId) {
          return;
        }
        if (transitionId) {
          this._lastRoomTransitionId = transitionId;
        }
        this.currentRoomId = roomId || room?.room_id || null;
        this.currentRoom = room || null;
        this.generateHexGrid();
        if (room?.name) {
          this.showRoomBanner(room.name, room.subtitle ?? null);
        }
      }),
      this.bus.on('map:changed', ({ mapId = null, placements = null, ports = [], links = [], occupancy = {} } = {}) => {
        this.currentMap = Array.isArray(placements)
          ? {
            mapId: mapId ? String(mapId) : null,
            placements,
            ports: Array.isArray(ports) ? ports : [],
            links: Array.isArray(links) ? links : [],
            occupancy: occupancy && typeof occupancy === 'object' ? occupancy : {},
          }
          : null;
        this.generateHexGrid();
      }),
      this.bus.on('canvas:coordinates-toggled', ({ enabled } = {}) => {
        this.config.showCoordinates = Boolean(enabled);
        this.generateHexGrid();
      }),
      this.bus.on('canvas:grid-toggled', ({ enabled } = {}) => {
        this.config.showGrid = Boolean(enabled);
        this.generateHexGrid();
      }),
      this.bus.on('canvas:reset-view', () => {
        const centerX = this.app?.screen?.width ? this.app.screen.width / 2 : 0;
        const centerY = this.app?.screen?.height ? this.app.screen.height / 2 : 0;
        this.setWorldScale(1);
        this.setWorldPosition(centerX, centerY);
        this.bus.emit('canvas:zoom-changed', { scale: 1 });
      }),
      this.bus.on('room:changed', () => {
        this.clearMovementBandOverlay();
      }),
    );
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];

    if (this.app) {
      if (this._wheelHandler) {
        this.app.view.removeEventListener('wheel', this._wheelHandler);
      }
      if (this._leaveHandler) {
        this.app.view.removeEventListener('mouseleave', this._leaveHandler);
      }
      if (this._contextMenuHandler) {
        this.app.view.removeEventListener('contextmenu', this._contextMenuHandler);
      }
      this.app.destroy(true, { children: true, texture: true, baseTexture: true });
      this.app = null;
    }
  }

  // ---------------------------------------------------------------------------
  // Public: world manipulation (called by GameShell for pan/zoom)
  // ---------------------------------------------------------------------------

  /**
   * Move all world-space layers together (pan).
   * @param {number} x
   * @param {number} y
   */
  setWorldPosition(x, y) {
    for (const layer of this._worldLayers()) {
      layer.x = x;
      layer.y = y;
    }
  }

  /**
   * Scale all world-space layers together (zoom).
   * @param {number} scale
   */
  setWorldScale(scale) {
    for (const layer of this._worldLayers()) {
      layer.scale.set(scale);
    }
  }

  setPanEnabled(enabled) {
    this._panEnabled = enabled !== false;
  }

  globalToWorldPoint(globalX, globalY) {
    if (!this.hexContainer || !window.PIXI || !Number.isFinite(Number(globalX)) || !Number.isFinite(Number(globalY))) {
      return null;
    }
    const point = new PIXI.Point(Number(globalX), Number(globalY));
    const local = this.hexContainer.toLocal(point);
    return {
      x: Number(local?.x || 0),
      y: Number(local?.y || 0),
    };
  }

  globalToAxial(globalX, globalY) {
    const worldPoint = this.globalToWorldPoint(globalX, globalY);
    if (!worldPoint) {
      return null;
    }
    return this.pixelToAxial(worldPoint.x, worldPoint.y);
  }

  /**
   * Resize the PIXI renderer to the current container box.
   *
   * Hidden-tab initialization can produce stale canvas dimensions; this
   * method rebinds renderer geometry once the map panel is visible.
   */
  resizeToContainer() {
    if (!this.app || !this.container) {
      return;
    }

    const width = Math.max(1, Number(this.container.clientWidth || 800));
    const height = Math.max(1, Number(this.container.clientHeight || 600));
    const previousWidth = Number(this.app.screen?.width || width);
    const previousHeight = Number(this.app.screen?.height || height);
    if (width === previousWidth && height === previousHeight) {
      return;
    }

    const previousCenterX = previousWidth / 2;
    const previousCenterY = previousHeight / 2;
    const anchorLayer = this.hexContainer;
    const previousOffsetX = Number(anchorLayer?.x || 0) - previousCenterX;
    const previousOffsetY = Number(anchorLayer?.y || 0) - previousCenterY;

    this.app.renderer.resize(width, height);
    this.app.stage.hitArea = this.app.screen;

    const nextCenterX = width / 2;
    const nextCenterY = height / 2;
    this.setWorldPosition(nextCenterX + previousOffsetX, nextCenterY + previousOffsetY);
    this.drawCompassRose();
    if (this.currentRoom?.name) {
      this.showRoomBanner(this.currentRoom.name, this.currentRoom.subtitle ?? null);
    }
  }

  // ---------------------------------------------------------------------------
  // Public: hex grid
  // ---------------------------------------------------------------------------

  /**
   * (Re)generate the full hex grid.
   * Clears existing hexes, emits no bus events.
   */
  generateHexGrid() {
    if (!this.hexContainer) return;

    this.hexContainer.removeChildren();
    this.gridContainer.removeChildren();
    this.propsContainer?.removeChildren();
    this._hideHexHoverInfo();

    const { hexSize, gridWidth, gridHeight } = this.config;

    if (this.currentMap) {
      this._renderMapAggregate(this.currentMap, hexSize);
      return;
    }

    const roomHexes = _getRoomHexes(this.currentRoom);

    if (roomHexes.length) {
      roomHexes.forEach((roomHex) => {
        this._createHex(
          Number(roomHex.q),
          Number(roomHex.r),
          hexSize,
          _resolveHexStyleForCanvas(_resolveRoomHexStyle(roomHex), this.config),
          roomHex,
        );
      });
      return;
    }

    for (let q = -Math.floor(gridWidth / 2); q < Math.ceil(gridWidth / 2); q += 1) {
      for (let r = -Math.floor(gridHeight / 2); r < Math.ceil(gridHeight / 2); r += 1) {
        this._createHex(q, r, hexSize, _resolveHexStyleForCanvas(DEFAULT_HEX_STYLE, this.config), null);
      }
    }
  }

  /**
   * Render a level-map aggregate: footprints, ports, and links.
   *
   * Layers, bottom to top, inside the world container so pan/zoom apply
   * uniformly: footprint hexes (hexContainer), port markers and link lines
   * (propsContainer). A hex claimed by more than one placement is drawn in
   * the overlap style regardless of terrain; the author must see it.
   *
   * @param {{placements: Array, ports: Array, links: Array, occupancy: object}} map
   * @param {number} hexSize
   */
  _renderMapAggregate(map, hexSize) {
    const occupancy = map.occupancy || {};
    map.placements.forEach((placement, index) => {
      const tint = _placementTint(placement, index);
      const hexes = Array.isArray(placement?.hexes) ? placement.hexes : [];
      hexes.forEach((hex) => {
        const q = Number(hex?.q);
        const r = Number(hex?.r);
        if (!Number.isInteger(q) || !Number.isInteger(r)) {
          return;
        }
        const claimants = occupancy[`${q}:${r}`];
        const overlapping = Array.isArray(claimants) && claimants.length > 1;
        const terrainStyle = _resolveRoomHexStyle(hex);
        const style = overlapping
          ? MAP_OVERLAP_HEX_STYLE
          : { ...terrainStyle, fillColor: _blendColor(terrainStyle.fillColor, tint, 0.45), lineColor: tint };
        const mapHex = {
          ...hex,
          hex_id: `${placement.placementId || placement.placement_id || index}:${q}:${r}`,
          placement_id: placement.placementId || placement.placement_id || null,
          placement_label: placement.label || null,
        };
        const graphic = this._createHex(q, r, hexSize, _resolveHexStyleForCanvas(style, this.config), mapHex);
        graphic.mapHexData = mapHex;
      });
    });

    if (!this.propsContainer) {
      return;
    }
    const overlay = new PIXI.Graphics();
    overlay.name = 'mapAggregateOverlay';
    overlay.eventMode = 'none';
    map.links.forEach((link) => {
      const from = link?.from;
      const to = link?.to;
      if (!from || !to) {
        return;
      }
      const a = this.axialToPixel(Number(from.q), Number(from.r), hexSize);
      const b = this.axialToPixel(Number(to.q), Number(to.r), hexSize);
      overlay.lineStyle(3, link?.kind === 'secret_door' ? 0xa855f7 : 0xf8fafc, 0.9);
      overlay.moveTo(a.x, a.y);
      overlay.lineTo(b.x, b.y);
    });
    map.ports.forEach((port) => {
      const q = Number(port?.q);
      const r = Number(port?.r);
      const edge = Number(port?.edge);
      if (!Number.isInteger(q) || !Number.isInteger(r) || !Number.isInteger(edge)) {
        return;
      }
      const center = this.axialToPixel(q, r, hexSize);
      // Edge e faces EDGE_DIRECTIONS[e]; marker sits on that edge's midpoint.
      const angle = (Math.PI / 3) * edge + Math.PI / 6;
      const x = center.x + Math.cos(angle) * hexSize * 0.8;
      const y = center.y + Math.sin(angle) * hexSize * 0.8;
      const isEntry = port?.kind === 'entry';
      const color = isEntry ? 0x38bdf8 : (port?.linked ? 0x22c55e : 0xf59e0b);
      overlay.lineStyle(2, color, 1);
      if (isEntry || port?.linked) {
        overlay.beginFill(color, 0.9);
      }
      overlay.drawCircle(x, y, hexSize * 0.18);
      if (isEntry || port?.linked) {
        overlay.endFill();
      }
    });
    this.propsContainer.addChild(overlay);
  }

  /**
   * Find a rendered hex PIXI.Graphics by axial coordinates.
   * @param {number} q
   * @param {number} r
   * @returns {PIXI.Graphics|null}
   */
  findHexByCoords(q, r) {
    return (
      this.hexContainer?.children.find(
        (c) => c.hexData && c.hexData.q === q && c.hexData.r === r
      ) ?? null
    );
  }

  /**
   * Redraw a hex with a new style without removing it.
   * @param {PIXI.Graphics} hex
   * @param {number} fillColor
   * @param {number} lineWidth
   * @param {number} lineColor
   * @param {number} [alpha=1]
   */
  drawHexStyle(hex, fillColor, lineWidth, lineColor, alpha = 1) {
    hex.clear();
    hex.beginFill(fillColor, alpha);
    hex.lineStyle(lineWidth, lineColor, 1);
    this._drawHexShape(hex, this.config.hexSize);
    hex.endFill();
  }

  clearMovementBandOverlay() {
    if (this._movementBandOverlay?.parent) {
      this._movementBandOverlay.parent.removeChild(this._movementBandOverlay);
    }
    this._movementBandOverlay?.destroy?.({ children: true });
    this._movementBandOverlay = null;
  }

  renderMovementBandOverlay(bands = {}) {
    this.clearMovementBandOverlay();
    if (!this.uiContainer || !window.PIXI) {
      return;
    }

    const overlay = new PIXI.Graphics();
    overlay.name = 'movementBandOverlay';
    overlay.eventMode = 'none';
    overlay.zIndex = 405;
    [
      { key: 'step', fill: 0x38bdf8, line: 0xe0f2fe, alpha: 0.42 },
      { key: 'stride1', fill: 0x22c55e, line: 0xbbf7d0, alpha: 0.34 },
      { key: 'stride2', fill: 0xf59e0b, line: 0xfef3c7, alpha: 0.30 },
      { key: 'stride3', fill: 0xef4444, line: 0xfee2e2, alpha: 0.28 },
    ].forEach(({ key, fill, line, alpha }) => {
      const hexes = Array.isArray(bands?.[key]) ? bands[key] : [];
      hexes.forEach((hex) => {
        const q = Number(hex?.q);
        const r = Number(hex?.r);
        if (!Number.isFinite(q) || !Number.isFinite(r)) {
          return;
        }
        const pos = this.axialToPixel(q, r, this.config.hexSize);
        overlay.beginFill(fill, alpha);
        overlay.lineStyle(2, line, Math.min(1, alpha + 0.2));
        overlay.drawPolygon(this._createHexPolygonPoints(pos.x, pos.y, this.config.hexSize * 0.92));
        overlay.endFill();
      });
    });

    this.uiContainer.addChild(overlay);
    this._movementBandOverlay = overlay;
  }

  // ---------------------------------------------------------------------------
  // Public: HUD
  // ---------------------------------------------------------------------------

  /**
   * Draw the compass rose in the bottom-right HUD corner.
   * Safe to call multiple times — clears and redraws.
   */
  drawCompassRose() {
    if (!this.hudContainer || !this.app) return;

    // Remove prior compass if any
    const prev = this.hudContainer.getChildByName('compass');
    if (prev) {
      this.hudContainer.removeChild(prev);
      prev.destroy({ children: true });
    }

    const root = new PIXI.Container();
    root.name = 'compass';

    const cx = this.app.screen.width - 50;
    const cy = this.app.screen.height - 55;
    const r = 22;
    const edgeDirections = [
      { key: 'N', angle: -Math.PI / 2, color: 0xe53e3e },
      { key: 'NE', angle: -Math.PI / 6, color: 0xa0aec0 },
      { key: 'SE', angle: Math.PI / 6, color: 0xa0aec0 },
      { key: 'S', angle: Math.PI / 2, color: 0xa0aec0 },
      { key: 'SW', angle: (5 * Math.PI) / 6, color: 0xa0aec0 },
      { key: 'NW', angle: -(5 * Math.PI) / 6, color: 0xa0aec0 },
    ];

    const g = new PIXI.Graphics();
    // Background disc
    g.beginFill(0x1a202c, 0.7);
    g.drawCircle(cx, cy, r + 6);
    g.endFill();
    g.lineStyle(1, 0x4a5568, 0.8);
    g.drawCircle(cx, cy, r + 6);
    // Hex orientation guide (flat-top)
    g.lineStyle(1, 0x64748b, 0.45);
    for (let i = 0; i < 6; i++) {
      const angle = (Math.PI / 3) * i;
      const x = cx + r * Math.cos(angle);
      const y = cy + r * Math.sin(angle);
      i === 0 ? g.moveTo(x, y) : g.lineTo(x, y);
    }
    g.closePath();
    // Direction arrows
    edgeDirections.forEach(({ angle, color }) => {
      const tipX = cx + Math.cos(angle) * (r + 2);
      const tipY = cy + Math.sin(angle) * (r + 2);
      const baseCx = cx + Math.cos(angle) * (r - 6);
      const baseCy = cy + Math.sin(angle) * (r - 6);
      const perpX = -Math.sin(angle) * 4;
      const perpY = Math.cos(angle) * 4;
      g.beginFill(color);
      g.drawPolygon([tipX, tipY, baseCx + perpX, baseCy + perpY, baseCx - perpX, baseCy - perpY]);
      g.endFill();
    });
    root.addChild(g);

    // Cardinal labels (centred on a constant radius so all are equidistant)
    const labelStyle = { fontFamily: 'Arial', fontSize: 11, fill: 0xe2e8f0, fontWeight: 'bold' };
    const labelRadius = r + 14;
    edgeDirections.forEach(({ key, angle }) => {
      const label = new PIXI.Text(key, labelStyle);
      if (label.anchor && typeof label.anchor.set === 'function') {
        label.anchor.set(0.5);
      }
      label.x = cx + Math.cos(angle) * labelRadius;
      label.y = cy + Math.sin(angle) * labelRadius;
      root.addChild(label);
    });

    this.hudContainer.addChild(root);
  }

  /**
   * Show (or update) the room name banner at the top of the canvas HUD.
   * @param {string} roomName
   * @param {string|null} [subtitle]
   */
  showRoomBanner(roomName, subtitle = null) {
    if (!this.hudContainer || !this.app) return;

    if (this._roomBanner) {
      this.hudContainer.removeChild(this._roomBanner);
      this._roomBanner.destroy({ children: true });
      this._roomBanner = null;
    }

    const container = new PIXI.Container();
    const screenW = this.app.screen.width;

    const bg = new PIXI.Graphics();
    bg.beginFill(0x1a202c, 0.85);
    bg.drawRoundedRect(10, 8, screenW - 20, subtitle ? 46 : 32, 6);
    bg.endFill();
    container.addChild(bg);

    const title = new PIXI.Text(roomName, {
      fontFamily: 'Arial',
      fontSize: 16,
      fontWeight: 'bold',
      fill: 0xf7fafc,
    });
    title.x = 20;
    title.y = 12;
    container.addChild(title);

    if (subtitle) {
      const sub = new PIXI.Text(subtitle, { fontFamily: 'Arial', fontSize: 11, fill: 0xa0aec0 });
      sub.x = 20;
      sub.y = 32;
      container.addChild(sub);
    }

    this.hudContainer.addChild(container);
    this._roomBanner = container;
  }

  // ---------------------------------------------------------------------------
  // Public: hex coordinate math
  // ---------------------------------------------------------------------------

  /**
   * Convert flat-top axial coordinates to pixel position (relative to world center).
   * @param {number} q
   * @param {number} r
   * @param {number} [size]
   * @returns {{ x: number, y: number }}
   */
  axialToPixel(q, r, size = this.config.hexSize) {
    return {
      x: size * (1.5 * q),
      y: size * (Math.sqrt(3) / 2 * q + Math.sqrt(3) * r),
    };
  }

  /**
   * Convert pixel position to axial coordinates (snapped to nearest hex).
   * @param {number} x
   * @param {number} y
   * @param {number} [size]
   * @returns {{ q: number, r: number }}
   */
  pixelToAxial(x, y, size = this.config.hexSize) {
    const q = (2 / 3 * x) / size;
    const r = (-1 / 3 * x + Math.sqrt(3) / 3 * y) / size;
    return this.roundAxial(q, r);
  }

  /**
   * Round fractional axial coordinates to the nearest integer hex.
   * @param {number} q
   * @param {number} r
   * @returns {{ q: number, r: number }}
   */
  roundAxial(q, r) {
    const s = -q - r;
    let rq = Math.round(q);
    let rr = Math.round(r);
    const rs = Math.round(s);
    const qDiff = Math.abs(rq - q);
    const rDiff = Math.abs(rr - r);
    const sDiff = Math.abs(rs - s);
    if (qDiff > rDiff && qDiff > sDiff) {
      rq = -rr - rs;
    } else if (rDiff > sDiff) {
      rr = -rq - rs;
    }
    return { q: rq, r: rr };
  }

  // ---------------------------------------------------------------------------
  // Private: PIXI initialization
  // ---------------------------------------------------------------------------

  _initPixiApp() {
    this.app = new PIXI.Application({
      width: this.container.clientWidth || 800,
      height: this.container.clientHeight || 600,
      backgroundColor: this.config.backgroundColor,
      antialias: true,
      resolution: window.devicePixelRatio || 1,
      autoDensity: true,
    });
    if (this.container.firstChild) {
      this.container.insertBefore(this.app.view, this.container.firstChild);
    } else {
      this.container.appendChild(this.app.view);
    }
    this.app.stage.interactive = true;
    this.app.stage.hitArea = this.app.screen;
  }

  _buildSceneLayers() {
    const stage = this.app.stage;
    stage.sortableChildren = true;

    const makeLayer = (zIndex) => {
      const c = new PIXI.Container();
      c.zIndex = zIndex;
      stage.addChild(c);
      return c;
    };

    this.backgroundContainer = makeLayer(5);
    this.hexContainer        = makeLayer(10);
    this.gridContainer       = makeLayer(20);
    this.propsContainer      = makeLayer(25);
    this.objectContainer     = makeLayer(30);
    this.fxContainer         = makeLayer(35);
    this.uiContainer         = makeLayer(40);
    this.interactionContainer = makeLayer(45);
    this.hudContainer        = makeLayer(50);

    // Interaction layer needs to capture pointer events
    this.interactionContainer.eventMode = 'passive';
    this.interactionContainer.interactiveChildren = true;

    // Center world-space layers
    const cx = this.app.screen.width / 2;
    const cy = this.app.screen.height / 2;
    for (const layer of this._worldLayers()) {
      layer.x = cx;
      layer.y = cy;
    }
  }

  _setupPanZoom() {
    let isDragging = false;
    let dragStart = { x: 0, y: 0 };
    const bus = this.bus;
    const cfg = this.config;

    this.app.stage.on('pointerdown', (e) => {
      if (!this._panEnabled || (e?.target && e.target !== this.app.stage)) {
        return;
      }
      isDragging = true;
      dragStart = { x: e.data.global.x, y: e.data.global.y };
    });
    this.app.stage.on('pointerup', () => { isDragging = false; });
    this.app.stage.on('pointerupoutside', () => { isDragging = false; });
    this.app.stage.on('pointermove', (e) => {
      if (!isDragging || !this._panEnabled) return;
      const dx = e.data.global.x - dragStart.x;
      const dy = e.data.global.y - dragStart.y;
      this.setWorldPosition(this.hexContainer.x + dx, this.hexContainer.y + dy);
      dragStart = { x: e.data.global.x, y: e.data.global.y };
    });

    this._wheelHandler = (e) => {
      e.preventDefault();
      const delta = e.deltaY < 0 ? 1.1 : 0.9;
      const newScale = this.hexContainer.scale.x * delta;
      if (newScale >= cfg.minZoom && newScale <= cfg.maxZoom) {
        this.setWorldScale(newScale);
        bus.emit('canvas:zoom-changed', { scale: newScale });
      }
    };
    this.app.view.addEventListener('wheel', this._wheelHandler, { passive: false });

    this._leaveHandler = () => { isDragging = false; };
    this.app.view.addEventListener('mouseleave', this._leaveHandler);
  }

  // ---------------------------------------------------------------------------
  // Private: hex creation
  // ---------------------------------------------------------------------------

  /**
   * Create a single hex PIXI.Graphics at (q, r) and wire pointer events → bus.
   * @param {number} q
   * @param {number} r
   * @param {number} size
   */
  _createHex(q, r, size, style = null, roomHex = null) {
    const hex = new PIXI.Graphics();
    const pos = this.axialToPixel(q, r, size);
    const resolvedStyle = style || DEFAULT_HEX_STYLE;

    hex.beginFill(resolvedStyle.fillColor, resolvedStyle.fillAlpha);
    hex.lineStyle(resolvedStyle.lineWidth, resolvedStyle.lineColor, resolvedStyle.lineAlpha);
    this._drawHexShape(hex, size);
    hex.endFill();

    hex.x = pos.x;
    hex.y = pos.y;
    hex.hexData = { q, r };
    hex.roomHexData = roomHex;

    // PIXI v7 pointer contract: use eventMode for hit testing.
    hex.eventMode = 'static';
    hex.cursor = 'pointer';
    hex.interactive = true;
    hex.buttonMode = true;

    hex.on('pointerover', () => {
      this._showHexHoverInfo(q, r, roomHex);
      this.bus.emit('canvas:hex-hovered', { q, r });
    });
    hex.on('pointerout',  () => {
      this._hideHexHoverInfo();
      this.bus.emit('canvas:hex-out', { q, r });
    });
    hex.on('pointerdown', (event) => {
      const originalEvent = event?.data?.originalEvent || null;
      const clientX = Number(originalEvent?.clientX);
      const clientY = Number(originalEvent?.clientY);
      this.bus.emit('canvas:hex-clicked', {
        q,
        r,
        button: event.data?.button ?? 0,
        clientX: Number.isFinite(clientX) ? clientX : null,
        clientY: Number.isFinite(clientY) ? clientY : null,
      });
    });

    this.hexContainer.addChild(hex);
    this._renderHexAttributeIndicators(pos, size, roomHex);

    if (resolvedStyle.showCoordinates) {
      const label = new PIXI.Text(`${q},${r}`, {
        fontFamily: 'Arial',
        fontSize: 10,
        fill: 0x718096,
        align: 'center',
      });
      label.anchor.set(0.5);
      label.x = pos.x;
      label.y = pos.y;
      this.gridContainer.addChild(label);
      hex.hexCoordText = label;
    }
    return hex;
  }

  _renderHexAttributeIndicators(pos, size, roomHex = null) {
    if (!this.propsContainer || !window.PIXI || !this.config.showHexIndicators) {
      return;
    }
    if (!roomHex) {
      return;
    }

    const isDiscovered = roomHex?.is_discovered !== false;
    const isVisible = roomHex?.is_visible !== false;
    const isEntry = roomHex?.is_entry === true;

    const objects = Array.isArray(roomHex?.objects) ? roomHex.objects : [];
    const objectCount = objects.length;

    const elev = Number(roomHex?.elevation_ft);
    const hasElevation = Number.isFinite(elev) && Math.abs(elev) >= 0.5;

    const alpha = !isDiscovered ? 0.55 : (isVisible ? 0.95 : 0.7);

    const root = new PIXI.Container();
    root.x = pos.x;
    root.y = pos.y;
    root.eventMode = 'none';

    if (!isDiscovered) {
      const badge = new PIXI.Graphics();
      badge.beginFill(0x0b1020, 0.8);
      badge.lineStyle(1, 0x94a3b8, 0.35);
      badge.drawCircle(0, 0, Math.max(10, size * 0.18));
      badge.endFill();

      const text = new PIXI.Text('?', {
        fontFamily: 'Arial',
        fontSize: Math.max(12, size * 0.22),
        fill: 0xe2e8f0,
        fontWeight: 'bold',
      });
      text.anchor?.set?.(0.5);

      root.addChild(badge);
      root.addChild(text);

      this.propsContainer.addChild(root);
      return;
    }

    if (!isVisible) {
      const g = new PIXI.Graphics();
      g.lineStyle(2, 0x94a3b8, 0.55 * alpha);
      g.drawCircle(-size * 0.48, -size * 0.48, Math.max(6, size * 0.11));
      root.addChild(g);
    }

    if (isEntry) {
      const g = new PIXI.Graphics();
      g.lineStyle(1, 0x0b1020, 0.55 * alpha);
      g.beginFill(0x22c55e, 0.9 * alpha);
      const tipY = -size * 0.18;
      const baseY = size * 0.14;
      const halfW = size * 0.16;
      g.drawPolygon([0, tipY, -halfW, baseY, halfW, baseY]);
      g.endFill();
      root.addChild(g);
    }

    if (objectCount > 0) {
      const label = objectCount > 9 ? '9+' : String(objectCount);
      const g = new PIXI.Graphics();
      g.beginFill(0xa855f7, 0.9 * alpha);
      g.lineStyle(1, 0x0b1020, 0.55 * alpha);
      g.drawCircle(size * 0.48, -size * 0.48, Math.max(8, size * 0.13));
      g.endFill();

      const text = new PIXI.Text(label, {
        fontFamily: 'Arial',
        fontSize: Math.max(10, size * 0.18),
        fill: 0x0b1020,
        fontWeight: 'bold',
      });
      text.anchor?.set?.(0.5);
      text.x = size * 0.48;
      text.y = -size * 0.48;

      root.addChild(g);
      root.addChild(text);
    }

    if (hasElevation) {
      const sign = elev > 0 ? '+' : '';
      const text = new PIXI.Text(`${sign}${Math.round(elev)}ft`, {
        fontFamily: 'Arial',
        fontSize: Math.max(9, size * 0.16),
        fill: 0xe2e8f0,
        fontWeight: 'bold',
      });
      text.anchor?.set?.(0, 1);
      text.x = -size * 0.58;
      text.y = size * 0.58;
      text.alpha = 0.95 * alpha;
      root.addChild(text);
    }

    this.propsContainer.addChild(root);
  }

  _ensureHexHoverUI() {
    if (!window.PIXI || !this.uiContainer) {
      return;
    }

    if (!this._hoverHexOutline || !this._hoverHexTooltip || !this._hoverHexTooltipBg || !this._hoverHexTooltipText) {
      const outline = new PIXI.Graphics();
      outline.name = 'hoverHexOutline';
      outline.visible = false;
      outline.eventMode = 'none';
      this.uiContainer.addChild(outline);
      this._hoverHexOutline = outline;
    }

    if (!this._hoverHexTooltip) {
      const tooltip = new PIXI.Container();
      tooltip.name = 'hoverHexTooltip';
      tooltip.visible = false;
      tooltip.eventMode = 'none';

      const bg = new PIXI.Graphics();
      bg.name = 'bg';
      bg.eventMode = 'none';

      const text = new PIXI.Text('', {
        fontFamily: 'Arial',
        fontSize: 12,
        fill: 0xffffff,
        align: 'left',
      });
      text.name = 'text';
      text.eventMode = 'none';

      tooltip.addChild(bg);
      tooltip.addChild(text);
      this.uiContainer.addChild(tooltip);

      this._hoverHexTooltip = tooltip;
      this._hoverHexTooltipBg = bg;
      this._hoverHexTooltipText = text;
    }
  }

  _showHexHoverInfo(q, r, roomHex = null) {
    this._ensureHexHoverUI();

    if (!this._hoverHexOutline) {
      return;
    }

    const qNum = Number(q);
    const rNum = Number(r);
    if (!Number.isFinite(qNum) || !Number.isFinite(rNum)) {
      this._hideHexHoverInfo();
      return;
    }

    const hexSize = Number(this.config?.hexSize || 30);
    const pos = this.axialToPixel(qNum, rNum, hexSize);

    const outline = this._hoverHexOutline;
    outline.clear();
    outline.lineStyle(3, 0xfbbf24, 0.9);
    this._drawHexShape(outline, hexSize);
    outline.x = pos.x;
    outline.y = pos.y;
    outline.visible = true;

    const fallbackHexId = this.currentRoomId ? `${this.currentRoomId}:${qNum}:${rNum}` : `${qNum}:${rNum}`;
    const hexId = String(roomHex?.hex_id || fallbackHexId);
    const terrainType = String(roomHex?.terrain_type || 'unknown');
    const lighting = String(roomHex?.lighting || 'unknown');
    const elevation = Number.isFinite(Number(roomHex?.elevation_ft)) ? Number(roomHex.elevation_ft) : 0;
    const flags = [
      roomHex?.is_entry === true ? 'entry' : null,
      roomHex?.is_visible === true ? 'visible' : null,
      roomHex?.is_discovered === true ? 'discovered' : null,
    ].filter(Boolean).join(', ');
    const objectCount = Array.isArray(roomHex?.objects) ? roomHex.objects.length : 0;

    const text = this._hoverHexTooltipText;
    text.text = [
      `hex: ${hexId}`,
      `q=${qNum} r=${rNum}${flags ? ` (${flags})` : ''}`,
      `terrain=${terrainType} | light=${lighting} | elev_ft=${elevation}`,
      `objects=${objectCount}`,
    ].join('\n');
    text.x = 10;
    text.y = 8;

    const bg = this._hoverHexTooltipBg;
    const paddingX = 12;
    const paddingY = 10;
    const width = Math.max(140, text.width + paddingX * 2);
    const height = Math.max(44, text.height + paddingY * 2);
    bg.clear();
    bg.beginFill(0x0b1020, 0.86);
    bg.lineStyle(1, 0xffffff, 0.18);
    bg.drawRoundedRect(0, 0, width, height, 8);
    bg.endFill();

    const tooltip = this._hoverHexTooltip;
    tooltip.x = pos.x + hexSize * 0.65;
    tooltip.y = pos.y - hexSize * 0.75;
    tooltip.visible = true;
  }

  _hideHexHoverInfo() {
    if (this._hoverHexOutline) {
      this._hoverHexOutline.visible = false;
      this._hoverHexOutline.clear();
    }
    if (this._hoverHexTooltip) {
      this._hoverHexTooltip.visible = false;
    }
  }

  /**
   * Draw flat-top hexagon vertices on a Graphics object (no fill/line state set).
   * @param {PIXI.Graphics} g
   * @param {number} size
   */
  _drawHexShape(g, size) {
    for (let i = 0; i < 6; i++) {
      const angle = (Math.PI / 3) * i;
      const x = size * Math.cos(angle);
      const y = size * Math.sin(angle);
      i === 0 ? g.moveTo(x, y) : g.lineTo(x, y);
    }
    g.closePath();
  }

  _createHexPolygonPoints(centerX, centerY, size) {
    const points = [];
    for (let i = 0; i < 6; i += 1) {
      const angle = (Math.PI / 3) * i;
      points.push(
        centerX + size * Math.cos(angle),
        centerY + size * Math.sin(angle),
      );
    }
    return points;
  }

  // ---------------------------------------------------------------------------
  // Private: helpers
  // ---------------------------------------------------------------------------

  /** @returns {PIXI.Container[]} All world-space (pan/zoom) layers */
  _worldLayers() {
    return [
      this.backgroundContainer,
      this.hexContainer,
      this.gridContainer,
      this.propsContainer,
      this.objectContainer,
      this.fxContainer,
      this.uiContainer,
      this.interactionContainer,
    ].filter(Boolean);
  }
}

const DEFAULT_HEX_STYLE = {
  fillColor: 0x2d3748,
  fillAlpha: 1,
  lineColor: 0x4a5568,
  lineAlpha: 1,
  lineWidth: 1,
  showCoordinates: false,
};

const MAP_OVERLAP_HEX_STYLE = {
  fillColor: 0xdc2626,
  fillAlpha: 0.95,
  lineColor: 0xfecaca,
  lineAlpha: 1,
  lineWidth: 2,
  showCoordinates: false,
};

const MAP_PLACEMENT_TINTS = [
  0x60a5fa, 0x34d399, 0xfbbf24, 0xf472b6, 0xa78bfa, 0xfb923c, 0x2dd4bf, 0xe879f9,
];

function _placementTint(placement, index) {
  const explicit = Number(placement?.tint);
  if (Number.isInteger(explicit) && explicit >= 0 && explicit <= 0xffffff) {
    return explicit;
  }
  return MAP_PLACEMENT_TINTS[index % MAP_PLACEMENT_TINTS.length];
}

function _blendColor(base, tint, weight) {
  const mix = (shift) => {
    const a = (base >> shift) & 0xff;
    const b = (tint >> shift) & 0xff;
    return Math.round(a * (1 - weight) + b * weight) & 0xff;
  };
  return (mix(16) << 16) | (mix(8) << 8) | mix(0);
}

function _getRoomHexes(room = null) {
  return Array.isArray(room?.hexes)
    ? room.hexes.filter((hex) => Number.isFinite(Number(hex?.q)) && Number.isFinite(Number(hex?.r)))
    : [];
}

function _resolveRoomHexStyle(roomHex = {}) {
  const objects = Array.isArray(roomHex?.objects) ? roomHex.objects : [];
  const objectCategories = objects
    .map((object) => String(object?.category || object?.type || object?.object_type || '').toLowerCase())
    .filter(Boolean);
  const terrain = String(roomHex?.terrain_type || '').toLowerCase();
  const lighting = String(roomHex?.lighting || '').toLowerCase();
  const blocked = objects.some((object) => object?.blocks_movement === true || object?.passable === false);
  const isWall = blocked || objectCategories.some((category) => ['wall', 'barrier', 'barricade', 'collapsed'].some((token) => category.includes(token)));
  const isDoor = objectCategories.some((category) => category.includes('door'));
  const isWater = terrain.includes('water');
  const isHazard = terrain.includes('lava') || terrain.includes('hazard') || objectCategories.some((category) => ['trap', 'hazard'].some((token) => category.includes(token)));

  let style = DEFAULT_HEX_STYLE;

  if (isWall) {
    style = {
      fillColor: 0x1f2937,
      fillAlpha: 0.95,
      lineColor: 0x94a3b8,
      lineAlpha: 1,
      lineWidth: 2,
      showCoordinates: false,
    };
  } else if (isDoor) {
    style = {
      fillColor: 0x3f3f46,
      fillAlpha: 0.95,
      lineColor: 0xfbbf24,
      lineAlpha: 1,
      lineWidth: 2,
      showCoordinates: false,
    };
  } else if (isHazard) {
    style = {
      fillColor: 0x7f1d1d,
      fillAlpha: 0.88,
      lineColor: 0xf97316,
      lineAlpha: 1,
      lineWidth: 1.5,
      showCoordinates: false,
    };
  } else if (isWater) {
    style = {
      fillColor: 0x1d4ed8,
      fillAlpha: 0.72,
      lineColor: 0x93c5fd,
      lineAlpha: 0.9,
      lineWidth: 1,
      showCoordinates: false,
    };
  } else if (lighting === 'dark') {
    style = {
      fillColor: 0x1e293b,
      fillAlpha: 0.9,
      lineColor: 0x475569,
      lineAlpha: 1,
      lineWidth: 1,
      showCoordinates: false,
    };
  } else if (lighting === 'dim') {
    style = {
      fillColor: 0x24324a,
      fillAlpha: 0.92,
      lineColor: 0x64748b,
      lineAlpha: 1,
      lineWidth: 1,
      showCoordinates: false,
    };
  } else if (objects.length > 0) {
    style = {
      fillColor: 0x334155,
      fillAlpha: 0.92,
      lineColor: 0x64748b,
      lineAlpha: 1,
      lineWidth: 1,
      showCoordinates: false,
    };
  }

  const isDiscovered = roomHex?.is_discovered !== false;
  const isVisible = roomHex?.is_visible !== false;

  if (!isDiscovered) {
    return {
      fillColor: 0x0b1020,
      fillAlpha: 0.98,
      lineColor: 0x0b1020,
      lineAlpha: 0.7,
      lineWidth: Math.max(0.5, style.lineWidth ?? 1),
      showCoordinates: false,
    };
  }

  if (!isVisible) {
    return {
      ...style,
      fillAlpha: Math.min(style.fillAlpha ?? 1, 0.55),
      lineAlpha: Math.min(style.lineAlpha ?? 1, 0.55),
      lineColor: 0x334155,
    };
  }

  return style;
}

function _resolveHexStyleForCanvas(style = DEFAULT_HEX_STYLE, config = {}) {
  return {
    ...style,
    lineWidth: config.showGrid === false ? 0 : style.lineWidth,
    lineAlpha: config.showGrid === false ? 0 : style.lineAlpha,
    showCoordinates: Boolean(config.showCoordinates),
  };
}
