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

    this._unsubs = [];
    this._wheelHandler = null;
    this._leaveHandler = null;

    // World-space hover inspector UI
    this._hoverHexOutline = null;
    this._hoverHexTooltip = null;
    this._hoverHexTooltipBg = null;
    this._hoverHexTooltipText = null;
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
    this.generateHexGrid();
    this.drawCompassRose();

    this._unsubs.push(
      this.bus.on('room:changed', ({ roomId, room } = {}) => {
        this.currentRoomId = roomId || room?.room_id || null;
        this.currentRoom = room || null;
        this.generateHexGrid();
        if (room?.name) {
          this.showRoomBanner(room.name, room.subtitle ?? null);
        }
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
      isDragging = true;
      dragStart = { x: e.data.global.x, y: e.data.global.y };
    });
    this.app.stage.on('pointerup', () => { isDragging = false; });
    this.app.stage.on('pointerupoutside', () => { isDragging = false; });
    this.app.stage.on('pointermove', (e) => {
      if (!isDragging) return;
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
    hex.on('pointerdown', (event) =>
      this.bus.emit('canvas:hex-clicked', { q, r, button: event.data?.button ?? 0 })
    );

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

    if (!this._hoverHexOutline) {
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

    if (!this._hoverHexOutline || !this._hoverHexTooltip || !this._hoverHexTooltipBg || !this._hoverHexTooltipText) {
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
