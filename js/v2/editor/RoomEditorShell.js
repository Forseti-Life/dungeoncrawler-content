/**
 * @file editor/RoomEditorShell.js
 *
 * Canonical Room Editor shell.
 *
 * Composes the shared GameEventBus and HexCanvas primitives with a
 * self-contained authoring UI: header actions, a searchable placeable
 * catalog, editing tools, and an inspector/validation panel.
 *
 * This module owns NO shared renderer logic beyond what HexCanvas already
 * exposes publicly (room:changed grid rendering, objectContainer layer,
 * axial/pixel math, movement-band overlay reused for hex selection).
 * Placement tokens are drawn here with plain PIXI.Graphics because the
 * canonical room-placement schema is not an ECS entity and must not be
 * routed through GameShell's gameplay orchestration.
 *
 * Server authority contract (see RoomEditorController / RoomEditorService):
 *   POST  create   { room_id|null }                       -> { data: draft }
 *   GET   draft     /{draft_id}                           -> { data: draft }
 *   POST  command   /{draft_id}/commands                  -> { data: { command_id, draft, idempotent } }
 *   POST  validate  /{draft_id}/validate                  -> { data: validation_result }
 *   POST  publish   /{draft_id}/publish                   -> { data: publication_result }
 *   GET   catalog    ?family=&search=&limit=&offset=      -> { data: catalog_page }
 *
 * Every mutation is an explicit, revisioned command. Local state is only
 * ever updated from a confirmed server response — there is no optimistic
 * local-success fallback. Failures surface verbatim (code + message) in
 * the status/validation area.
 *
 * Fires no bus events beyond what HexCanvas consumes (room:changed).
 */

/* global PIXI */

import { GameEventBus } from '../GameEventBus.js';
import { HexCanvas } from '../canvas/HexCanvas.js';

const FAMILIES = ['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard'];
const SOLID_FAMILIES = ['actor', 'creature', 'obstacle'];

const FAMILY_COLORS = {
  creature: 0xef4444,
  actor: 0x22c55e,
  item: 0xf59e0b,
  obstacle: 0x6b7280,
  trap: 0xa855f7,
  hazard: 0xf97316,
};

const TERRAIN_TYPES = [
  'stone_floor', 'rough_stone', 'smooth_stone', 'dirt', 'mud', 'sand',
  'water_shallow', 'water_deep', 'ice', 'lava', 'fungal_growth', 'bone',
  'crystal', 'metal_grate', 'wooden_floor', 'carpet', 'rubble', 'void',
];

const LIGHTING_LEVELS = ['bright_light', 'dim_light', 'darkness', 'magical_darkness'];

const ROOM_TYPES = [
  'corridor', 'chamber', 'cavern', 'hall', 'shrine', 'vault', 'lair', 'nest',
  'workshop', 'library', 'prison', 'throne_room', 'armory', 'pantry', 'garden',
  'pool', 'mine', 'crypt', 'laboratory', 'barracks', 'marketplace', 'arena',
  'boss_chamber', 'entrance', 'exit', 'stairwell', 'crossroads', 'dead_end',
  'trap_room', 'puzzle_room', 'vault_room', 'safe_room',
];

const SIZE_CATEGORIES = ['tiny', 'small', 'medium', 'large', 'huge', 'gargantuan'];

const TOOLS = ['select', 'add_hex', 'remove_hex', 'terrain', 'elevation', 'place', 'entry_port', 'exit_port'];

/** Generate an RFC 4122 v4 UUID without requiring crypto.randomUUID. */
function uuidv4() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 16; i += 1) {
      bytes[i] = Math.floor(Math.random() * 256);
    }
  }
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0'));
  return `${hex[0]}${hex[1]}${hex[2]}${hex[3]}-${hex[4]}${hex[5]}-${hex[6]}${hex[7]}-${hex[8]}${hex[9]}-${hex[10]}${hex[11]}${hex[12]}${hex[13]}${hex[14]}${hex[15]}`;
}

function debounce(fn, wait) {
  let timer = null;
  return (...args) => {
    if (timer) {
      clearTimeout(timer);
    }
    timer = setTimeout(() => fn(...args), wait);
  };
}

function clearElement(el) {
  while (el && el.firstChild) {
    el.removeChild(el.firstChild);
  }
}

function makeOption(value, label, selected) {
  const option = document.createElement('option');
  option.value = value;
  option.textContent = label;
  if (selected) {
    option.selected = true;
  }
  return option;
}

export class RoomEditorShell {
  /**
   * @param {HTMLElement} container Root element carrying [data-room-editor].
   * @param {object} settings drupalSettings.dungeoncrawlerContent.roomEditor
   */
  constructor(container, settings = {}) {
    this.container = container;
    this.settings = settings && typeof settings === 'object' ? settings : {};
    this.urls = this.settings.urls || {};
    this.csrfToken = String(this.settings.csrfToken || '');

    this.bus = new GameEventBus();
    this.hexCanvas = null;

    /** @type {object|null} Current room-editor draft (room-editor-draft-v1). */
    this.draft = null;
    this.tool = 'select';
    this.selection = null; // Room, hex, placement, or room-local port selection.
    this._selectedCatalogDefinition = null;

    this._history = []; // stack of applied forward command ids
    this._redoStack = []; // stack of { undoCommandId, forwardCommandId }

    this.catalog = { items: [], total: 0, limit: 40, offset: 0, family: '', search: '' };
    this._catalogEntryCache = new Map(); // "family:definition_id" -> normalized definition (inspector lookups)

    this._busy = false;
    this._dom = {};
    this._keydownHandler = null;
    this._stageClickHandler = null;
    this._stageMoveHandler = null;
    this._stageUpHandler = null;
    this._canvasLeaveDragHandler = null;
    this._resizeObserver = null;

    // Catalog-to-canvas drag-drop gesture state (see _bindCatalogDragSource).
    this._catalogDragCleanup = null;

    // Transient drag-to-move gesture state for placement tokens. Never
    // written into `draft.room` directly — only the confirmed `move_object`
    // command response mutates room state; this just drives the live token
    // visual while the pointer is down (see _beginPlacementDrag/_handlePlacementDragMove).
    this._dragState = null;
  }

  // ---------------------------------------------------------------------------
  // Lifecycle
  // ---------------------------------------------------------------------------

  init() {
    this._bindDom();
    this._bindHeaderEvents();
    this._bindToolbarEvents();
    this._bindCatalogEvents();
    this._bindInspectorEvents();
    this._initCanvas();
    this._setTool('select');
    this._renderInspector();
    this._loadCatalog(true);

    this._keydownHandler = (event) => this._handleKeydown(event);
    document.addEventListener('keydown', this._keydownHandler);

    const initialRoomId = this.settings.selectedRoomId || null;
    if (initialRoomId) {
      this.loadRoom(initialRoomId);
    } else {
      this._setStatus('Select a room to edit, or start a new one.', 'info');
    }
  }

  destroy() {
    if (this._keydownHandler) {
      document.removeEventListener('keydown', this._keydownHandler);
      this._keydownHandler = null;
    }
    if (this._resizeObserver) {
      this._resizeObserver.disconnect();
      this._resizeObserver = null;
    }
    if (this.hexCanvas?.app?.stage) {
      if (this._stageClickHandler) {
        this.hexCanvas.app.stage.off('pointerdown', this._stageClickHandler);
      }
      if (this._stageMoveHandler) {
        this.hexCanvas.app.stage.off('pointermove', this._stageMoveHandler);
      }
      if (this._stageUpHandler) {
        this.hexCanvas.app.stage.off('pointerup', this._stageUpHandler);
        this.hexCanvas.app.stage.off('pointerupoutside', this._stageUpHandler);
      }
    }
    if (this.hexCanvas?.app?.view && this._canvasLeaveDragHandler) {
      this.hexCanvas.app.view.removeEventListener('mouseleave', this._canvasLeaveDragHandler);
    }
    this._stageClickHandler = null;
    this._stageMoveHandler = null;
    this._stageUpHandler = null;
    this._canvasLeaveDragHandler = null;
    this._dragState = null;
    if (this._catalogDragCleanup) {
      this._catalogDragCleanup();
      this._catalogDragCleanup = null;
    }
    this.hexCanvas?.destroy();
    this.hexCanvas = null;
    this.bus.destroy();
  }

  // ---------------------------------------------------------------------------
  // DOM binding
  // ---------------------------------------------------------------------------

  _bindDom() {
    const q = (selector) => this.container.querySelector(selector);
    this._dom = {
      status: q('[data-room-editor-status]'),
      roomSelect: q('[data-room-editor-room-select]'),
      undoBtn: q('[data-room-editor-action="undo"]'),
      redoBtn: q('[data-room-editor-action="redo"]'),
      newRoomBtn: q('[data-room-editor-action="new-room"]'),
      loadRoomBtn: q('[data-room-editor-action="load-room"]'),
      saveBtn: q('[data-room-editor-action="save"]'),
      publishBtn: q('[data-room-editor-action="publish"]'),
      validateBtn: q('[data-room-editor-action="validate"]'),
      validateProfile: q('[data-room-editor-validate-profile]'),
      publishVersion: q('[data-room-editor-publish-version]'),
      publishNote: q('[data-room-editor-publish-note]'),
      toolbar: q('[data-room-editor-toolbar]'),
      terrainValue: q('[data-room-editor-terrain-value]'),
      elevationValue: q('[data-room-editor-elevation-value]'),
      canvasContainer: q('[data-room-editor-canvas]'),
      catalogSearch: q('[data-room-editor-catalog-search]'),
      catalogFamily: q('[data-room-editor-catalog-family]'),
      catalogList: q('[data-room-editor-catalog-list]'),
      catalogEmpty: q('[data-room-editor-catalog-empty]'),
      catalogMoreBtn: q('[data-room-editor-action="catalog-more"]'),
      catalogSelectedLabel: q('[data-room-editor-catalog-selected]'),
      inspectorBody: q('[data-room-editor-inspector-body]'),
      validationList: q('[data-room-editor-validation-list]'),
    };
    TERRAIN_TYPES.forEach((value) => {
      this._dom.terrainValue?.appendChild(makeOption(value, value.replace(/_/g, ' '), value === 'stone_floor'));
    });
  }

  _bindHeaderEvents() {
    this._dom.newRoomBtn?.addEventListener('click', () => this.createNewRoom());
    this._dom.loadRoomBtn?.addEventListener('click', () => {
      const roomId = this._dom.roomSelect?.value || '';
      if (roomId) {
        this.loadRoom(roomId);
      } else {
        this._setStatus('Choose a room from the list first.', 'error');
      }
    });
    this._dom.undoBtn?.addEventListener('click', () => this.undo());
    this._dom.redoBtn?.addEventListener('click', () => this.redo());
    this._dom.saveBtn?.addEventListener('click', () => this.save());
    this._dom.publishBtn?.addEventListener('click', () => this.publish());
    this._dom.validateBtn?.addEventListener('click', () => {
      this.validate(this._dom.validateProfile?.value || 'editing');
    });
  }

  _bindToolbarEvents() {
    this._dom.toolbar?.querySelectorAll('[data-room-editor-tool]').forEach((btn) => {
      btn.addEventListener('click', () => this._setTool(btn.getAttribute('data-room-editor-tool')));
    });
  }

  _bindCatalogEvents() {
    const debouncedSearch = debounce(() => {
      this.catalog.search = this._dom.catalogSearch?.value.trim() || '';
      this._loadCatalog(true);
    }, 300);
    this._dom.catalogSearch?.addEventListener('input', debouncedSearch);
    this._dom.catalogFamily?.addEventListener('change', () => {
      this.catalog.family = this._dom.catalogFamily?.value || '';
      this._loadCatalog(true);
    });
    this._dom.catalogMoreBtn?.addEventListener('click', () => this._loadCatalog(false));
  }

  _bindInspectorEvents() {
    // Inspector content is rebuilt on selection change; delegate clicks so
    // handlers survive re-renders.
    this._dom.inspectorBody?.addEventListener('click', (event) => this._handleInspectorClick(event));
  }

  // ---------------------------------------------------------------------------
  // Canvas
  // ---------------------------------------------------------------------------

  _initCanvas() {
    if (!this._dom.canvasContainer) {
      return;
    }
    this.hexCanvas = new HexCanvas(this._dom.canvasContainer, this.bus, {
      hexSize: 32,
      showGrid: true,
      showHexIndicators: true,
    });
    this.hexCanvas.init();
    this.bus.on('room:changed', () => this._renderPlacements());
    this._bindCanvasResize();

    if (this.hexCanvas.app?.stage) {
      this._stageClickHandler = (event) => this._handleStageClick(event);
      this.hexCanvas.app.stage.on('pointerdown', this._stageClickHandler);
      // Placement drag-to-move: tokens start the gesture (see _renderPlacements),
      // but move/end are tracked on the stage so the drag survives the pointer
      // leaving the token's small hit area mid-gesture.
      this._stageMoveHandler = (event) => this._handlePlacementDragMove(event);
      this.hexCanvas.app.stage.on('pointermove', this._stageMoveHandler);
      this._stageUpHandler = (event) => this._handlePlacementDragEnd(event);
      this.hexCanvas.app.stage.on('pointerup', this._stageUpHandler);
      this.hexCanvas.app.stage.on('pointerupoutside', this._stageUpHandler);
    }
    if (this.hexCanvas.app?.view) {
      this._canvasLeaveDragHandler = () => this._cancelPlacementDrag();
      this.hexCanvas.app.view.addEventListener('mouseleave', this._canvasLeaveDragHandler);
    }
    this._logCanvasDiagnostics('init');
  }

  _bindCanvasResize() {
    if (!this._dom.canvasContainer || typeof ResizeObserver === 'undefined') {
      this._logCanvasDiagnostics('resize-observer-unavailable');
      return;
    }
    this._resizeObserver = new ResizeObserver(() => {
      window.requestAnimationFrame(() => {
        this.hexCanvas?.resizeToContainer();
        this._fitRoomToView();
      });
    });
    this._resizeObserver.observe(this._dom.canvasContainer);
  }

  _fitRoomToView() {
    const hexes = Array.isArray(this.draft?.room?.hexes) ? this.draft.room.hexes : [];
    if (!this.hexCanvas?.app || hexes.length === 0) {
      this._logCanvasDiagnostics('fit-skipped');
      return;
    }
    const hexSize = Number(this.hexCanvas.config.hexSize || 32);
    const points = hexes
      .map((hex) => this.hexCanvas.axialToPixel(Number(hex.q), Number(hex.r), hexSize))
      .filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y));
    if (points.length === 0) {
      this._logCanvasDiagnostics('fit-no-valid-points', { hexCount: hexes.length });
      return;
    }
    const bounds = points.reduce((acc, point) => ({
      minX: Math.min(acc.minX, point.x - hexSize),
      maxX: Math.max(acc.maxX, point.x + hexSize),
      minY: Math.min(acc.minY, point.y - hexSize),
      maxY: Math.max(acc.maxY, point.y + hexSize),
    }), { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity });
    const roomWidth = Math.max(1, bounds.maxX - bounds.minX);
    const roomHeight = Math.max(1, bounds.maxY - bounds.minY);
    const screenWidth = Number(this.hexCanvas.app.screen?.width || this._dom.canvasContainer?.clientWidth || 800);
    const screenHeight = Number(this.hexCanvas.app.screen?.height || this._dom.canvasContainer?.clientHeight || 600);
    const padding = 80;
    const fitScale = Math.min(
      (screenWidth - padding) / roomWidth,
      (screenHeight - padding) / roomHeight,
    );
    const scale = Math.max(
      this.hexCanvas.config.minZoom,
      Math.min(this.hexCanvas.config.maxZoom, Number.isFinite(fitScale) ? fitScale : 1),
    );
    const centerX = (bounds.minX + bounds.maxX) / 2;
    const centerY = (bounds.minY + bounds.maxY) / 2;
    this.hexCanvas.setWorldScale(scale);
    this.hexCanvas.setWorldPosition(
      (screenWidth / 2) - (centerX * scale),
      (screenHeight / 2) - (centerY * scale),
    );
    this._logCanvasDiagnostics('fit-applied', { hexCount: hexes.length, bounds, scale });
  }

  _logCanvasDiagnostics(phase, extra = {}) {
    const container = this._dom.canvasContainer;
    const canvas = container?.querySelector('canvas') || null;
    const containerRect = container?.getBoundingClientRect?.();
    const canvasRect = canvas?.getBoundingClientRect?.();
    console.info('[RoomEditor] canvas diagnostics', {
      phase,
      hasPixi: Boolean(window.PIXI),
      hasHexCanvas: Boolean(this.hexCanvas),
      hasApp: Boolean(this.hexCanvas?.app),
      draftId: this.draft?.draft_id || null,
      roomId: this.draft?.room?.room_id || null,
      hexCount: Array.isArray(this.draft?.room?.hexes) ? this.draft.room.hexes.length : 0,
      container: containerRect ? {
        width: Math.round(containerRect.width),
        height: Math.round(containerRect.height),
      } : null,
      canvas: canvasRect ? {
        width: Math.round(canvasRect.width),
        height: Math.round(canvasRect.height),
        attrWidth: canvas?.width || null,
        attrHeight: canvas?.height || null,
      } : null,
      ...extra,
    });
  }

  _handleStageClick(event) {
    if (!this.draft || this._busy) {
      return;
    }
    const global = event?.data?.global;
    if (!global) {
      return;
    }
    const axial = this.hexCanvas?.globalToAxial(global.x, global.y);
    if (!axial || !Number.isFinite(axial.q) || !Number.isFinite(axial.r)) {
      return;
    }
    this._handleHexInteraction(axial.q, axial.r);
  }

  _handleHexInteraction(q, r) {
    switch (this.tool) {
      case 'select':
        this._selectHexAt(q, r);
        break;
      case 'add_hex':
        this._sendCommand('add_hex', { hex: { q, r, terrain_type: 'stone_floor', elevation_ft: 0, lighting: 'bright_light' } });
        break;
      case 'remove_hex':
        this._sendCommand('remove_hex', { hex: { q, r } });
        break;
      case 'terrain': {
        const terrainType = this._dom.terrainValue?.value || 'stone_floor';
        this._sendCommand('set_hex_terrain', { hex: { q, r }, terrain_type: terrainType });
        break;
      }
      case 'elevation': {
        const elevation = Number(this._dom.elevationValue?.value || 0);
        this._sendCommand('set_hex_elevation', { hex: { q, r }, elevation_ft: elevation });
        break;
      }
      case 'place':
        this._placeSelectedDefinitionAt(q, r);
        break;
      case 'entry_port':
        this._addPortAt('entry', q, r);
        break;
      case 'exit_port':
        this._addPortAt('exit', q, r);
        break;
      default:
        break;
    }
  }

  _placeSelectedDefinitionAt(q, r) {
    const definition = this._selectedCatalogDefinition;
    if (!definition) {
      this._setStatus('Select a catalog object before placing.', 'error');
      return;
    }
    const blocker = this._findSolidPlacementAt(q, r);
    if (this._isSolidFamily(definition.family) && blocker) {
      this._showPlacementCollision(q, r, blocker, 'Cannot place object');
      return;
    }
    this._sendCommand('place_object', {
      instance_id: uuidv4(),
      definition_ref: {
        family: definition.family,
        definition_id: definition.definition_id,
        version: definition.definition_version,
      },
      anchor_hex: { q, r },
      facing: 0,
      elevation_ft: 0,
      overrides: {},
    });
  }

  _findRoomHex(q, r) {
    const hexes = Array.isArray(this.draft?.room?.hexes) ? this.draft.room.hexes : [];
    return hexes.find((hex) => Number(hex.q) === Number(q) && Number(hex.r) === Number(r)) || null;
  }

  _findPlacement(instanceId) {
    const placements = Array.isArray(this.draft?.room?.placements) ? this.draft.room.placements : [];
    return placements.find((placement) => placement.instance_id === instanceId) || null;
  }

  _isSolidFamily(family) {
    return SOLID_FAMILIES.includes(String(family || ''));
  }

  _findSolidPlacementAt(q, r, ignoredInstanceId = null) {
    const placements = Array.isArray(this.draft?.room?.placements) ? this.draft.room.placements : [];
    return placements.find((placement) => {
      if (ignoredInstanceId && placement.instance_id === ignoredInstanceId) {
        return false;
      }
      return this._isSolidFamily(placement.definition_ref?.family)
        && Number(placement.anchor_hex?.q) === Number(q)
        && Number(placement.anchor_hex?.r) === Number(r);
    }) || null;
  }

  _describePlacement(placement) {
    return [
      placement.definition_ref?.family || 'placement',
      placement.definition_ref?.definition_id || placement.instance_id || 'unknown',
    ].filter(Boolean).join(':');
  }

  _showPlacementCollision(q, r, blocker, prefix = 'Placement collision') {
    const message = `${prefix}: hex (${q}, ${r}) already contains ${this._describePlacement(blocker)}.`;
    this._setStatus(message, 'error');
    console.warn('[RoomEditor] placement collision preflight', {
      q,
      r,
      blocker,
      selectedCatalogDefinition: this._selectedCatalogDefinition,
      selectedPlacement: this.selection?.type === 'placement' ? this._findPlacement(this.selection.instanceId) : null,
    });
  }

  _findPort(family, portId) {
    const bucket = family === 'entry' ? 'entry_ports' : 'exit_ports';
    const ports = Array.isArray(this.draft?.room?.[bucket]) ? this.draft.room[bucket] : [];
    return ports.find((port) => port.port_id === portId) || null;
  }

  _selectHexAt(q, r) {
    const hex = this._findRoomHex(q, r);
    if (!hex) {
      this.selection = null;
      this.hexCanvas?.clearMovementBandOverlay();
      this._setStatus(`No hex at (${q}, ${r}).`, 'info');
      this._renderInspector();
      return;
    }
    this.selection = { type: 'hex', q, r };
    this.hexCanvas?.renderMovementBandOverlay({ step: [{ q, r }] });
    this._renderInspector();
  }

  _selectPlacement(instanceId) {
    if (!this._findPlacement(instanceId)) {
      return;
    }
    this.selection = { type: 'placement', instanceId };
    this.hexCanvas?.clearMovementBandOverlay();
    this._renderInspector();
    this._renderPlacements();
  }

  _selectPort(family, portId) {
    if (!this._findPort(family, portId)) {
      return;
    }
    this.selection = { type: 'port', family, portId };
    this.hexCanvas?.clearMovementBandOverlay();
    this._renderInspector();
    this._renderPlacements();
  }

  _selectRoom() {
    this.selection = { type: 'room' };
    this.hexCanvas?.clearMovementBandOverlay();
    this._renderInspector();
  }

  _renderPlacements() {
    const objectContainer = this.hexCanvas?.objectContainer;
    if (!objectContainer) {
      return;
    }
    objectContainer.removeChildren();
    if (!window.PIXI || !this.draft?.room) {
      return;
    }
    const hexSize = this.hexCanvas.config.hexSize;
    const placements = Array.isArray(this.draft.room.placements) ? this.draft.room.placements : [];
    const selectedId = this.selection?.type === 'placement' ? this.selection.instanceId : null;

    placements.forEach((placement) => {
      const family = String(placement?.definition_ref?.family || '');
      const color = FAMILY_COLORS[family] ?? 0x94a3b8;
      const pos = this.hexCanvas.axialToPixel(Number(placement.anchor_hex?.q), Number(placement.anchor_hex?.r), hexSize);
      const isSelected = placement.instance_id === selectedId;

      const token = new PIXI.Container();
      token.x = pos.x;
      token.y = pos.y;
      token.eventMode = 'static';
      token.cursor = 'pointer';

      const body = new PIXI.Graphics();
      body.beginFill(color, 0.9);
      body.lineStyle(isSelected ? 3 : 1.5, isSelected ? 0xffffff : 0x0f172a, 1);
      body.drawCircle(0, 0, hexSize * 0.32);
      body.endFill();
      token.addChild(body);

      const facing = Number(placement.facing || 0);
      const angle = (Math.PI / 3) * facing - Math.PI / 2;
      const arrow = new PIXI.Graphics();
      arrow.beginFill(0x0f172a, 0.9);
      arrow.drawPolygon([
        Math.cos(angle) * hexSize * 0.32, Math.sin(angle) * hexSize * 0.32,
        Math.cos(angle + 2.5) * hexSize * 0.14, Math.sin(angle + 2.5) * hexSize * 0.14,
        Math.cos(angle - 2.5) * hexSize * 0.14, Math.sin(angle - 2.5) * hexSize * 0.14,
      ]);
      arrow.endFill();
      token.addChild(arrow);

      const label = new PIXI.Text(family.slice(0, 1).toUpperCase() || '?', {
        fontFamily: 'Arial',
        fontSize: Math.max(10, hexSize * 0.28),
        fill: 0xf8fafc,
        fontWeight: 'bold',
      });
      label.anchor.set(0.5);
      token.addChild(label);

      token.on('pointerdown', (event) => {
        event.stopPropagation();
        this._selectPlacement(placement.instance_id);
        this._beginPlacementDrag(placement, token, event);
      });

      objectContainer.addChild(token);
    });

    [
      ['entry', this.draft.room.entry_ports || [], 0x38bdf8],
      ['exit', this.draft.room.exit_ports || [], 0x2563eb],
    ].forEach(([family, ports, color]) => {
      ports.forEach((port) => {
        const position = this.hexCanvas.axialToPixel(Number(port.hex?.q), Number(port.hex?.r), hexSize);
        const angle = (Math.PI / 3) * Number(port.edge || 0) - Math.PI / 2;
        const marker = new PIXI.Container();
        marker.x = position.x + Math.cos(angle) * hexSize * 0.68;
        marker.y = position.y + Math.sin(angle) * hexSize * 0.68;
        marker.eventMode = 'static';
        marker.cursor = 'pointer';

        const body = new PIXI.Graphics();
        const selected = this.selection?.type === 'port'
          && this.selection.family === family
          && this.selection.portId === port.port_id;
        body.beginFill(color, 0.95);
        body.lineStyle(selected ? 3 : 1.5, selected ? 0xffffff : 0x0f172a, 1);
        if (family === 'entry') {
          body.drawPolygon([0, -9, 9, 8, -9, 8]);
        } else {
          body.drawRect(-7, -7, 14, 14);
        }
        body.endFill();
        marker.addChild(body);
        marker.on('pointerdown', (event) => {
          event.stopPropagation();
          this._selectPort(family, port.port_id);
        });
        objectContainer.addChild(marker);
      });
    });
  }

  // ---------------------------------------------------------------------------
  // Placement drag-to-move
  //
  // Dragging never mutates `draft.room` directly — the token is repositioned
  // purely as a visual preview while the pointer is down, driven by
  // globalToWorldPoint()/globalToAxial() so it tracks correctly under pan/zoom.
  // On drop, a single authoritative `move_object` command is sent (same
  // command + validation path as the inspector's manual q/r fields), and
  // _renderPlacements() is re-run afterwards so the token always ends up
  // exactly where the confirmed server state says it is - on success that's
  // the new hex, on failure it snaps back to the original hex.
  // ---------------------------------------------------------------------------

  _beginPlacementDrag(placement, token, event) {
    if (this.tool !== 'select' || !this.hexCanvas) {
      return;
    }
    const global = event?.data?.global;
    if (!global) {
      return;
    }
    this._dragState = {
      instanceId: String(placement.instance_id),
      family: String(placement.definition_ref?.family || ''),
      token,
      originQ: Number(placement.anchor_hex?.q),
      originR: Number(placement.anchor_hex?.r),
      currentQ: Number(placement.anchor_hex?.q),
      currentR: Number(placement.anchor_hex?.r),
      moved: false,
    };
    token.alpha = 0.6;
    token.cursor = 'grabbing';
    this.hexCanvas.setPanEnabled(false);
  }

  _handlePlacementDragMove(event) {
    const dragState = this._dragState;
    if (!dragState || !this.hexCanvas) {
      return;
    }
    const global = event?.data?.global;
    if (!global) {
      return;
    }
    const worldPoint = this.hexCanvas.globalToWorldPoint(global.x, global.y);
    if (worldPoint) {
      dragState.token.x = worldPoint.x;
      dragState.token.y = worldPoint.y;
    }
    const axial = this.hexCanvas.globalToAxial(global.x, global.y);
    if (!axial) {
      return;
    }
    dragState.moved = true;
    dragState.currentQ = axial.q;
    dragState.currentR = axial.r;
    const valid = Boolean(this._findRoomHex(axial.q, axial.r))
      && !(this._isSolidFamily(dragState.family) && this._findSolidPlacementAt(axial.q, axial.r, dragState.instanceId));
    this.hexCanvas.renderMovementBandOverlay(
      valid ? { step: [{ q: axial.q, r: axial.r }] } : { stride3: [{ q: axial.q, r: axial.r }] },
    );
  }

  _handlePlacementDragEnd(event) {
    const dragState = this._dragState;
    if (!dragState) {
      return;
    }
    this._dragState = null;
    this.hexCanvas?.setPanEnabled(this.tool === 'select');
    this.hexCanvas?.clearMovementBandOverlay();

    const global = event?.data?.global;
    if (global && this.hexCanvas) {
      const axial = this.hexCanvas.globalToAxial(global.x, global.y);
      if (axial) {
        dragState.currentQ = axial.q;
        dragState.currentR = axial.r;
        dragState.moved = true;
      }
    }

    if (!dragState.moved || (dragState.currentQ === dragState.originQ && dragState.currentR === dragState.originR)) {
      this._renderPlacements();
      return;
    }
    this._movePlacementTo(dragState.instanceId, dragState.family, dragState.currentQ, dragState.currentR);
  }

  _cancelPlacementDrag() {
    if (!this._dragState) {
      return;
    }
    this._dragState = null;
    this.hexCanvas?.setPanEnabled(this.tool === 'select');
    this.hexCanvas?.clearMovementBandOverlay();
    this._renderPlacements();
  }

  async _movePlacementTo(instanceId, family, q, r) {
    if (!this._findRoomHex(q, r)) {
      this._setStatus(`Cannot move placement outside the room at (${q}, ${r}).`, 'error');
      this._renderPlacements();
      return;
    }
    const blocker = this._isSolidFamily(family) ? this._findSolidPlacementAt(q, r, instanceId) : null;
    if (blocker) {
      this._showPlacementCollision(q, r, blocker, 'Cannot move placement');
      this._renderPlacements();
      return;
    }
    await this._sendCommand('move_object', { instance_id: instanceId, anchor_hex: { q, r } });
    this._renderPlacements();
  }

  _addPortAt(family, q, r) {
    if (!this._findRoomHex(q, r)) {
      this._setStatus(`Cannot add a port outside the room at (${q}, ${r}).`, 'error');
      return;
    }
    const existing = family === 'entry' ? this.draft.room.entry_ports : this.draft.room.exit_ports;
    let suffix = existing.length + 1;
    let portId = `${family}-${suffix}`;
    while (existing.some((port) => port.port_id === portId)) {
      suffix += 1;
      portId = `${family}-${suffix}`;
    }
    const port = family === 'entry'
      ? {
        port_id: portId,
        hex: { q, r },
        edge: 0,
        label: `Entry ${suffix}`,
        arrival_facing: 3,
        is_default: false,
        tags: [],
      }
      : {
        port_id: portId,
        hex: { q, r },
        edge: 0,
        label: `Exit ${suffix}`,
        kind: 'door',
        direction: 'bidirectional',
        default_state: 'closed',
        destination_hint: null,
        linked_placement_id: null,
        requirements: [],
        tags: [],
      };
    this._sendCommand(`add_${family}_port`, { port });
  }

  // ---------------------------------------------------------------------------
  // Tools
  // ---------------------------------------------------------------------------

  _setTool(tool) {
    if (!TOOLS.includes(tool)) {
      return;
    }
    this.tool = tool;
    this.hexCanvas?.setPanEnabled(tool === 'select');
    this._dom.toolbar?.querySelectorAll('[data-room-editor-tool]').forEach((btn) => {
      const active = btn.getAttribute('data-room-editor-tool') === tool;
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      btn.classList.toggle('room-editor__tool--active', active);
    });
    if (this._dom.terrainValue) {
      this._dom.terrainValue.hidden = tool !== 'terrain';
    }
    if (this._dom.elevationValue) {
      this._dom.elevationValue.hidden = tool !== 'elevation';
    }
  }

  // ---------------------------------------------------------------------------
  // Room lifecycle
  // ---------------------------------------------------------------------------

  async createNewRoom() {
    await this._runExclusive(async () => {
      const result = await this._postJson(this.urls.create, { room_id: null });
      this._setDraft(result.data, 'Started a new untitled room.');
    });
  }

  async loadRoom(roomId) {
    await this._runExclusive(async () => {
      const result = await this._postJson(this.urls.create, { room_id: roomId });
      this._setDraft(result.data, `Loaded draft for "${roomId}".`);
      if (this._dom.roomSelect) {
        this._dom.roomSelect.value = roomId;
      }
    });
  }

  async save() {
    if (!this.draft) {
      this._setStatus('Nothing to save yet.', 'error');
      return;
    }
    await this._runExclusive(async () => {
      const url = this._urlFor('draft', this.draft.draft_id);
      const result = await this._getJson(url);
      const server = result.data;
      if (server.payload_hash === this.draft.payload_hash && server.revision === this.draft.revision) {
        this._setStatus(`All changes saved (revision ${this.draft.revision}).`, 'success');
      } else {
        this._setDraft(server, 'Synced local view with the server draft.');
      }
    });
  }

  async validate(profile = 'editing') {
    if (!this.draft) {
      this._setStatus('Load or create a room before validating.', 'error');
      return;
    }
    await this._runExclusive(async () => {
      const url = this._urlFor('validate', this.draft.draft_id);
      const result = await this._postJson(url, { profile });
      this._renderValidation(result.data);
      this._setStatus(
        result.data.valid ? `Validation passed (${profile}).` : `Validation found ${result.data.errors.length} error(s).`,
        result.data.valid ? 'success' : 'error',
      );
    });
  }

  async publish() {
    if (!this.draft) {
      this._setStatus('Load or create a room before publishing.', 'error');
      return;
    }
    const version = String(this._dom.publishVersion?.value || '').trim();
    if (!/^\d+\.\d+\.\d+$/.test(version)) {
      this._setStatus('Enter a semantic version (e.g. 1.0.0) before publishing.', 'error');
      return;
    }
    const note = String(this._dom.publishNote?.value || '');
    await this._runExclusive(async () => {
      const url = this._urlFor('publish', this.draft.draft_id);
      const result = await this._postJson(url, {
        expected_revision: this.draft.revision,
        version,
        publication_note: note,
        expected_base_version_id: this.draft.base_version_id,
      });
      this._setStatus(`Published ${result.data.room_id} as version ${result.data.version}.`, 'success');
      this.draft.status = 'published';
      this.draft.published_version_id = result.data.version_id;
      this._renderInspector();
    });
  }

  // ---------------------------------------------------------------------------
  // Commands / undo-redo
  // ---------------------------------------------------------------------------

  async _sendCommand(type, payload) {
    if (!this.draft) {
      this._setStatus('Load or create a room before editing.', 'error');
      return null;
    }
    const commandId = uuidv4();
    return this._runExclusive(async () => {
      const url = this._urlFor('command', this.draft.draft_id);
      const body = {
        command_id: commandId,
        expected_revision: this.draft.revision,
        type,
        payload,
        issued_at: new Date().toISOString(),
      };
      this._logCommandDiagnostics('dispatch', body);
      let result = null;
      try {
        result = await this._postJson(url, body);
      } catch (err) {
        this._logCommandDiagnostics('rejected', body, err);
        throw err;
      }
      this._setDraft(result.data.draft, null);
      if (type !== 'undo' && type !== 'redo') {
        this._history.push(commandId);
        this._redoStack = [];
      }
      this._setStatus(`Applied ${type} (revision ${this.draft.revision}).`, 'success');
      this._updateHistoryButtons();
      return result.data;
    });
  }

  async undo() {
    if (!this.draft || this._history.length === 0) {
      this._setStatus('Nothing to undo.', 'info');
      return;
    }
    const forwardCommandId = this._history[this._history.length - 1];
    const undoCommandId = uuidv4();
    await this._runExclusive(async () => {
      const url = this._urlFor('command', this.draft.draft_id);
      const body = {
        command_id: undoCommandId,
        expected_revision: this.draft.revision,
        type: 'undo',
        payload: { target_command_id: forwardCommandId },
        issued_at: new Date().toISOString(),
      };
      const result = await this._postJson(url, body);
      this._history.pop();
      this._redoStack.push({ undoCommandId, forwardCommandId });
      this._setDraft(result.data.draft, 'Undid last change.');
      this._updateHistoryButtons();
    });
  }

  async redo() {
    if (!this.draft || this._redoStack.length === 0) {
      this._setStatus('Nothing to redo.', 'info');
      return;
    }
    const entry = this._redoStack[this._redoStack.length - 1];
    const redoCommandId = uuidv4();
    await this._runExclusive(async () => {
      const url = this._urlFor('command', this.draft.draft_id);
      const body = {
        command_id: redoCommandId,
        expected_revision: this.draft.revision,
        type: 'redo',
        payload: { target_command_id: entry.undoCommandId },
        issued_at: new Date().toISOString(),
      };
      const result = await this._postJson(url, body);
      this._redoStack.pop();
      this._history.push(entry.forwardCommandId);
      this._setDraft(result.data.draft, 'Redid change.');
      this._updateHistoryButtons();
    });
  }

  _updateHistoryButtons() {
    if (this._dom.undoBtn) {
      this._dom.undoBtn.disabled = this._history.length === 0;
    }
    if (this._dom.redoBtn) {
      this._dom.redoBtn.disabled = this._redoStack.length === 0;
    }
  }

  // ---------------------------------------------------------------------------
  // Draft / room state
  // ---------------------------------------------------------------------------

  _setDraft(draft, statusMessage) {
    const previousDraftId = this.draft?.draft_id || null;
    const previousSelection = this.selection;
    this.draft = draft;
    if (previousDraftId !== (draft?.draft_id || null)) {
      this._history = [];
      this._redoStack = [];
      this.selection = null;
      this._updateHistoryButtons();
    } else if (previousSelection?.type === 'hex') {
      this.selection = this._findRoomHex(previousSelection.q, previousSelection.r)
        ? previousSelection
        : null;
    } else if (previousSelection?.type === 'placement') {
      this.selection = this._findPlacement(previousSelection.instanceId)
        ? previousSelection
        : null;
    } else if (previousSelection?.type === 'port') {
      this.selection = this._findPort(previousSelection.family, previousSelection.portId)
        ? previousSelection
        : null;
    } else {
      this.selection = previousSelection;
    }
    this.hexCanvas?.clearMovementBandOverlay();
    this.bus.emit('room:changed', {
      roomId: draft?.room_id || draft?.room?.room_id || null,
      room: draft?.room || null,
      transition: { id: uuidv4() },
    });
    this.hexCanvas?.resizeToContainer();
    this._fitRoomToView();
    if (this.selection?.type === 'hex') {
      this.hexCanvas?.renderMovementBandOverlay({
        step: [{ q: this.selection.q, r: this.selection.r }],
      });
    }
    this._renderInspector();
    if (statusMessage) {
      this._setStatus(statusMessage, 'success');
    }
  }

  // ---------------------------------------------------------------------------
  // Catalog
  // ---------------------------------------------------------------------------

  async _loadCatalog(reset) {
    if (reset) {
      this.catalog.offset = 0;
      this.catalog.items = [];
    }
    const params = new URLSearchParams();
    if (this.catalog.family) {
      params.set('family', this.catalog.family);
    }
    if (this.catalog.search) {
      params.set('search', this.catalog.search);
    }
    params.set('limit', String(this.catalog.limit));
    params.set('offset', String(this.catalog.offset));
    try {
      const result = await this._getJson(`${this.urls.catalog}?${params.toString()}`);
      const page = result.data;
      this.catalog.items = reset ? page.definitions : this.catalog.items.concat(page.definitions);
      this.catalog.total = page.total;
      this.catalog.offset = this.catalog.items.length;
      this._renderCatalogList();
    } catch (err) {
      this._showError(err, 'Failed to load catalog');
    }
  }

  _renderCatalogList() {
    const list = this._dom.catalogList;
    if (!list) {
      return;
    }
    clearElement(list);
    this.catalog.items.forEach((definition) => {
      const item = document.createElement('li');
      item.className = 'room-editor__catalog-item';
      item.setAttribute('role', 'option');
      item.tabIndex = 0;
      const isSelected = this._selectedCatalogDefinition
        && this._selectedCatalogDefinition.definition_id === definition.definition_id
        && this._selectedCatalogDefinition.family === definition.family;
      item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
      item.classList.toggle('room-editor__catalog-item--active', isSelected);

      const swatch = document.createElement('span');
      swatch.className = 'room-editor__catalog-swatch';
      const swatchColor = /^#[0-9A-Fa-f]{6}$/.test(definition.visual?.color || '')
        ? definition.visual.color
        : `#${(FAMILY_COLORS[definition.family] ?? 0x94a3b8).toString(16).padStart(6, '0')}`;
      swatch.style.backgroundColor = swatchColor;
      item.appendChild(swatch);

      const text = document.createElement('span');
      text.className = 'room-editor__catalog-text';
      const title = document.createElement('span');
      title.className = 'room-editor__catalog-title';
      title.textContent = definition.label;
      const meta = document.createElement('span');
      meta.className = 'room-editor__catalog-meta';
      meta.textContent = `${definition.family} - ${definition.category}`;
      text.appendChild(title);
      text.appendChild(meta);
      item.appendChild(text);

      const select = () => {
        if (this._suppressNextCatalogClick) {
          this._suppressNextCatalogClick = false;
          return;
        }
        this._selectedCatalogDefinition = definition;
        this._setTool('place');
        this._renderCatalogList();
        if (this._dom.catalogSelectedLabel) {
          this._dom.catalogSelectedLabel.textContent = `Selected: ${definition.label}`;
        }
      };
      item.addEventListener('click', select);
      item.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          select();
        }
      });
      this._bindCatalogDragSource(item, definition, swatchColor);

      list.appendChild(item);
    });
    if (this._dom.catalogEmpty) {
      this._dom.catalogEmpty.hidden = this.catalog.items.length !== 0;
    }
    if (this._dom.catalogMoreBtn) {
      this._dom.catalogMoreBtn.hidden = this.catalog.offset >= this.catalog.total;
    }
  }

  // ---------------------------------------------------------------------------
  // Catalog-to-canvas drag-drop
  //
  // A pointerdown on a catalog list item starts a gesture tracked at the
  // document level (the drag needs to travel outside the list, over the canvas).
  // A floating "ghost" swatch follows the cursor the whole way, giving the
  // click-to-hold / drag / release-to-drop visual the user asked for. Short
  // taps (no meaningful pointer movement) fall through to the existing
  // click-to-select behavior instead of being treated as a drag.
  // ---------------------------------------------------------------------------

  _bindCatalogDragSource(item, definition, swatchColor) {
    const DRAG_THRESHOLD_PX = 6;

    item.addEventListener('pointerdown', (event) => {
      if (event.button !== 0 && event.pointerType === 'mouse') {
        return;
      }
      const startX = event.clientX;
      const startY = event.clientY;
      let dragging = false;
      let ghost = null;

      const createGhost = () => {
        const el = document.createElement('div');
        el.className = 'room-editor__drag-ghost';
        el.style.backgroundColor = swatchColor;
        el.textContent = (definition.label || '?').trim().slice(0, 1).toUpperCase() || '?';
        document.body.appendChild(el);
        return el;
      };

      const moveGhost = (clientX, clientY) => {
        if (ghost) {
          ghost.style.transform = `translate(${clientX}px, ${clientY}px)`;
        }
      };

      const updateHoverPreview = (clientX, clientY) => {
        if (!this.hexCanvas) {
          return;
        }
        const axial = this._clientPointToAxial(clientX, clientY);
        if (!axial) {
          this.hexCanvas.clearMovementBandOverlay();
          return;
        }
        const valid = Boolean(this._findRoomHex(axial.q, axial.r))
          && !(this._isSolidFamily(definition.family) && this._findSolidPlacementAt(axial.q, axial.r));
        this.hexCanvas.renderMovementBandOverlay(
          valid ? { step: [{ q: axial.q, r: axial.r }] } : { stride3: [{ q: axial.q, r: axial.r }] },
        );
      };

      const cleanup = () => {
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        window.removeEventListener('blur', onPointerUp);
        if (ghost) {
          ghost.remove();
          ghost = null;
        }
        document.body.classList.remove('room-editor__body--dragging');
        this.hexCanvas?.clearMovementBandOverlay();
        this._catalogDragCleanup = null;
      };

      const onPointerMove = (moveEvent) => {
        const dx = moveEvent.clientX - startX;
        const dy = moveEvent.clientY - startY;
        if (!dragging && Math.hypot(dx, dy) > DRAG_THRESHOLD_PX) {
          dragging = true;
          ghost = createGhost();
          document.body.classList.add('room-editor__body--dragging');
        }
        if (dragging) {
          moveGhost(moveEvent.clientX, moveEvent.clientY);
          updateHoverPreview(moveEvent.clientX, moveEvent.clientY);
        }
      };

      const onPointerUp = (upEvent) => {
        const wasDragging = dragging;
        const dropClientX = upEvent.clientX ?? startX;
        const dropClientY = upEvent.clientY ?? startY;
        cleanup();
        if (wasDragging) {
          this._suppressNextCatalogClick = true;
          const axial = this._clientPointToAxial(dropClientX, dropClientY);
          if (axial) {
            this._selectedCatalogDefinition = definition;
            this._setTool('place');
            this._renderCatalogList();
            if (this._dom.catalogSelectedLabel) {
              this._dom.catalogSelectedLabel.textContent = `Selected: ${definition.label}`;
            }
            this._placeSelectedDefinitionAt(axial.q, axial.r);
          }
        }
      };

      document.addEventListener('pointermove', onPointerMove);
      document.addEventListener('pointerup', onPointerUp);
      window.addEventListener('blur', onPointerUp);
      this._catalogDragCleanup = cleanup;
    });
  }

  /**
   * Converts a DOM client point (e.g. from a catalog drag gesture) into
   * axial hex coordinates, accounting for canvas CSS scaling. Returns null
   * when the point falls outside the canvas element entirely.
   */
  _clientPointToAxial(clientX, clientY) {
    const canvas = this.hexCanvas?.app?.view;
    if (!canvas) {
      return null;
    }
    const rect = canvas.getBoundingClientRect();
    if (clientX < rect.left || clientX > rect.right || clientY < rect.top || clientY > rect.bottom || rect.width === 0 || rect.height === 0) {
      return null;
    }
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const x = (clientX - rect.left) * scaleX;
    const y = (clientY - rect.top) * scaleY;
    return this.hexCanvas.globalToAxial(x, y);
  }

  // ---------------------------------------------------------------------------
  // Inspector
  // ---------------------------------------------------------------------------

  _renderInspector() {
    const body = this._dom.inspectorBody;
    if (!body) {
      return;
    }
    clearElement(body);

    if (!this.draft) {
      const hint = document.createElement('p');
      hint.className = 'room-editor__hint';
      hint.textContent = 'Load or create a room to begin editing.';
      body.appendChild(hint);
      return;
    }

    if (!this.selection || this.selection.type === 'room') {
      body.appendChild(this._buildRoomInspector());
      return;
    }
    if (this.selection.type === 'hex') {
      const hex = this._findRoomHex(this.selection.q, this.selection.r);
      if (!hex) {
        body.appendChild(this._buildRoomInspector());
        return;
      }
      body.appendChild(this._buildHexInspector(hex));
      return;
    }
    if (this.selection.type === 'placement') {
      const placement = this._findPlacement(this.selection.instanceId);
      if (!placement) {
        body.appendChild(this._buildRoomInspector());
        return;
      }
      body.appendChild(this._buildPlacementInspector(placement));
      return;
    }
    if (this.selection.type === 'port') {
      const port = this._findPort(this.selection.family, this.selection.portId);
      if (!port) {
        body.appendChild(this._buildRoomInspector());
        return;
      }
      body.appendChild(this._buildPortInspector(this.selection.family, port));
    }
  }

  _buildRoomInspector() {
    const room = this.draft.room || {};
    const wrapper = document.createElement('div');
    wrapper.className = 'room-editor__inspector-panel';

    const heading = document.createElement('h3');
    heading.textContent = 'Room';
    wrapper.appendChild(heading);

    const status = document.createElement('p');
    status.className = 'room-editor__hint';
    status.textContent = `Draft ${this.draft.draft_id} - revision ${this.draft.revision} - status ${this.draft.status}`;
    wrapper.appendChild(status);

    wrapper.appendChild(this._labeledInput('room-id', 'Room ID', 'text', room.room_id || ''));
    wrapper.appendChild(this._labeledInput('room-name', 'Name', 'text', room.name || ''));
    wrapper.appendChild(this._labeledTextarea('room-description', 'Description', room.description || ''));
    wrapper.appendChild(this._labeledSelect('room-type', 'Room type', ROOM_TYPES, room.room_type || ''));
    wrapper.appendChild(this._labeledSelect('room-size', 'Size category', SIZE_CATEGORIES, room.size_category || ''));

    const applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'room-editor__button';
    applyBtn.textContent = 'Apply room metadata';
    applyBtn.setAttribute('data-room-editor-inspector-action', 'apply-room-metadata');
    wrapper.appendChild(applyBtn);

    wrapper.appendChild(this._buildPortSummary('Entry ports', room.entry_ports || [], 'entry'));
    wrapper.appendChild(this._buildPortSummary('Exit ports', room.exit_ports || [], 'exit'));

    return wrapper;
  }

  _buildPortSummary(titleText, ports, family) {
    const section = document.createElement('section');
    section.className = 'room-editor__port-summary';
    const heading = document.createElement('h4');
    heading.textContent = `${titleText} (${ports.length})`;
    section.appendChild(heading);
    if (ports.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'room-editor__hint';
      empty.textContent = 'None';
      section.appendChild(empty);
    }
    ports.forEach((port) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'room-editor__port-link';
      button.textContent = `${port.label} - (${port.hex?.q}, ${port.hex?.r}), edge ${port.edge}`;
      button.setAttribute('data-room-editor-inspector-action', 'select-port');
      button.dataset.portFamily = family;
      button.dataset.portId = port.port_id;
      section.appendChild(button);
    });
    return section;
  }

  _buildHexInspector(hex) {
    const wrapper = document.createElement('div');
    wrapper.className = 'room-editor__inspector-panel';

    const heading = document.createElement('h3');
    heading.textContent = `Hex (${hex.q}, ${hex.r})`;
    wrapper.appendChild(heading);

    const lighting = document.createElement('p');
    lighting.className = 'room-editor__hint';
    lighting.textContent = `Lighting: ${hex.lighting || 'unknown'} (${LIGHTING_LEVELS.includes(hex.lighting) ? 'canonical' : 'legacy'})`;
    wrapper.appendChild(lighting);

    wrapper.appendChild(this._labeledSelect('hex-terrain', 'Terrain type', TERRAIN_TYPES, hex.terrain_type || ''));
    const terrainBtn = document.createElement('button');
    terrainBtn.type = 'button';
    terrainBtn.className = 'room-editor__button';
    terrainBtn.textContent = 'Apply terrain';
    terrainBtn.setAttribute('data-room-editor-inspector-action', 'apply-hex-terrain');
    wrapper.appendChild(terrainBtn);

    wrapper.appendChild(this._labeledInput('hex-elevation', 'Elevation (ft)', 'number', hex.elevation_ft ?? 0));
    const elevationBtn = document.createElement('button');
    elevationBtn.type = 'button';
    elevationBtn.className = 'room-editor__button';
    elevationBtn.textContent = 'Apply elevation';
    elevationBtn.setAttribute('data-room-editor-inspector-action', 'apply-hex-elevation');
    wrapper.appendChild(elevationBtn);

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'room-editor__button room-editor__button--danger';
    removeBtn.textContent = 'Remove hex';
    removeBtn.setAttribute('data-room-editor-inspector-action', 'remove-hex');
    wrapper.appendChild(removeBtn);

    const addEntryBtn = document.createElement('button');
    addEntryBtn.type = 'button';
    addEntryBtn.className = 'room-editor__button';
    addEntryBtn.textContent = 'Add entry port here';
    addEntryBtn.setAttribute('data-room-editor-inspector-action', 'add-entry-port');
    wrapper.appendChild(addEntryBtn);

    const addExitBtn = document.createElement('button');
    addExitBtn.type = 'button';
    addExitBtn.className = 'room-editor__button';
    addExitBtn.textContent = 'Add exit port here';
    addExitBtn.setAttribute('data-room-editor-inspector-action', 'add-exit-port');
    wrapper.appendChild(addExitBtn);

    return wrapper;
  }

  _buildPlacementInspector(placement) {
    const wrapper = document.createElement('div');
    wrapper.className = 'room-editor__inspector-panel';

    const heading = document.createElement('h3');
    heading.textContent = 'Placement';
    wrapper.appendChild(heading);

    const info = document.createElement('dl');
    info.className = 'room-editor__definition-list';
    const addRow = (term, value) => {
      const dt = document.createElement('dt');
      dt.textContent = term;
      const dd = document.createElement('dd');
      dd.textContent = value;
      info.appendChild(dt);
      info.appendChild(dd);
    };
    addRow('Instance', placement.instance_id);
    addRow('Family', placement.definition_ref?.family || '');
    addRow('Definition', placement.definition_ref?.definition_id || '');
    addRow('Version', placement.definition_ref?.version || '');
    addRow('Anchor', `(${placement.anchor_hex?.q}, ${placement.anchor_hex?.r})`);
    addRow('Facing', String(placement.facing ?? 0));
    addRow('Elevation (ft)', String(placement.elevation_ft ?? 0));
    wrapper.appendChild(info);

    const details = document.createElement('div');
    details.className = 'room-editor__placement-details';
    details.dataset.instanceId = placement.instance_id;
    details.textContent = 'Loading catalog details…';
    wrapper.appendChild(details);
    this._loadPlacementDetails(placement, details);

    wrapper.appendChild(this._labeledInput('placement-q', 'Move to Q', 'number', placement.anchor_hex?.q ?? 0));
    wrapper.appendChild(this._labeledInput('placement-r', 'Move to R', 'number', placement.anchor_hex?.r ?? 0));
    const moveBtn = document.createElement('button');
    moveBtn.type = 'button';
    moveBtn.className = 'room-editor__button';
    moveBtn.textContent = 'Move placement';
    moveBtn.setAttribute('data-room-editor-inspector-action', 'move-placement');
    wrapper.appendChild(moveBtn);

    const rotateBtn = document.createElement('button');
    rotateBtn.type = 'button';
    rotateBtn.className = 'room-editor__button';
    rotateBtn.textContent = 'Rotate 60 deg';
    rotateBtn.setAttribute('data-room-editor-inspector-action', 'rotate-placement');
    wrapper.appendChild(rotateBtn);

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'room-editor__button room-editor__button--danger';
    removeBtn.textContent = 'Remove placement';
    removeBtn.setAttribute('data-room-editor-inspector-action', 'remove-placement');
    wrapper.appendChild(removeBtn);

    return wrapper;
  }

  /**
   * Async-enriches a placement inspector panel with catalog details (name,
   * description, category, tags) and a link to the canonical-library edit
   * page. `detailsEl` is re-checked against the live DOM/selection before
   * mutating it, in case the user re-selected something else while the
   * fetch was in flight.
   */
  async _loadPlacementDetails(placement, detailsEl) {
    const family = placement.definition_ref?.family || '';
    const definitionId = placement.definition_ref?.definition_id || '';
    const entry = await this._fetchCatalogEntry(family, definitionId);
    if (!detailsEl.isConnected || this.selection?.instanceId !== placement.instance_id) {
      return;
    }
    clearElement(detailsEl);

    if (!entry) {
      const missing = document.createElement('p');
      missing.className = 'room-editor__hint';
      missing.textContent = 'Catalog details unavailable for this object.';
      detailsEl.appendChild(missing);
      return;
    }

    const info = document.createElement('dl');
    info.className = 'room-editor__definition-list';
    const addRow = (term, value) => {
      if (!value) {
        return;
      }
      const dt = document.createElement('dt');
      dt.textContent = term;
      const dd = document.createElement('dd');
      dd.textContent = value;
      info.appendChild(dt);
      info.appendChild(dd);
    };
    addRow('Name', entry.label);
    addRow('Category', entry.category);
    addRow('Description', entry.description);
    if (Array.isArray(entry.tags) && entry.tags.length > 0) {
      addRow('Tags', entry.tags.join(', '));
    }
    detailsEl.appendChild(info);

    const editUrl = this._canonicalLibraryEditUrl(family, definitionId);
    if (editUrl) {
      const link = document.createElement('a');
      link.className = 'room-editor__port-link';
      link.href = editUrl;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = 'Edit in canonical library ↗';
      detailsEl.appendChild(link);
    }
  }

  _buildPortInspector(family, port) {
    const wrapper = document.createElement('div');
    wrapper.className = 'room-editor__inspector-panel';
    wrapper.dataset.portFamily = family;
    wrapper.dataset.portId = port.port_id;

    const heading = document.createElement('h3');
    heading.textContent = `${family === 'entry' ? 'Entry' : 'Exit'} port`;
    wrapper.appendChild(heading);
    const id = document.createElement('p');
    id.className = 'room-editor__hint';
    id.textContent = port.port_id;
    wrapper.appendChild(id);
    wrapper.appendChild(this._labeledInput('port-label', 'Label', 'text', port.label || ''));
    wrapper.appendChild(this._labeledInput('port-q', 'Hex Q', 'number', port.hex?.q ?? 0));
    wrapper.appendChild(this._labeledInput('port-r', 'Hex R', 'number', port.hex?.r ?? 0));
    wrapper.appendChild(this._labeledSelect('port-edge', 'Hex edge', ['0', '1', '2', '3', '4', '5'], String(port.edge ?? 0)));

    if (family === 'entry') {
      wrapper.appendChild(this._labeledSelect('port-facing', 'Arrival facing', ['0', '1', '2', '3', '4', '5'], String(port.arrival_facing ?? 0)));
      const defaultField = document.createElement('label');
      defaultField.className = 'room-editor__checkbox';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.id = 'room-editor-port-default';
      checkbox.checked = Boolean(port.is_default);
      defaultField.appendChild(checkbox);
      defaultField.append(' Default entry');
      wrapper.appendChild(defaultField);
    } else {
      wrapper.appendChild(this._labeledSelect('port-kind', 'Kind', ['hallway', 'archway', 'door', 'hatch', 'portcullis', 'secret_door', 'magical_barrier', 'collapsed', 'bridge', 'one_way_drop'], port.kind));
      wrapper.appendChild(this._labeledSelect('port-direction', 'Direction', ['bidirectional', 'one_way'], port.direction));
      wrapper.appendChild(this._labeledSelect('port-state', 'Default state', ['open', 'closed', 'locked', 'barred', 'trapped', 'triggered', 'destroyed'], port.default_state));
      wrapper.appendChild(this._labeledInput('port-destination', 'Destination hint', 'text', port.destination_hint || ''));
    }

    const applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'room-editor__button';
    applyBtn.textContent = 'Apply port changes';
    applyBtn.setAttribute('data-room-editor-inspector-action', 'apply-port');
    wrapper.appendChild(applyBtn);

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'room-editor__button room-editor__button--danger';
    removeBtn.textContent = 'Remove port';
    removeBtn.disabled = family === 'entry' && Boolean(port.is_default);
    removeBtn.title = removeBtn.disabled ? 'Choose another default entry before removing this port.' : '';
    removeBtn.setAttribute('data-room-editor-inspector-action', 'remove-port');
    wrapper.appendChild(removeBtn);
    return wrapper;
  }

  _labeledInput(name, labelText, type, value) {
    const field = document.createElement('div');
    field.className = 'room-editor__field';
    const label = document.createElement('label');
    label.setAttribute('for', `room-editor-${name}`);
    label.textContent = labelText;
    const input = document.createElement('input');
    input.type = type;
    input.id = `room-editor-${name}`;
    input.name = name;
    input.value = value;
    field.appendChild(label);
    field.appendChild(input);
    return field;
  }

  _labeledTextarea(name, labelText, value) {
    const field = document.createElement('div');
    field.className = 'room-editor__field';
    const label = document.createElement('label');
    label.setAttribute('for', `room-editor-${name}`);
    label.textContent = labelText;
    const textarea = document.createElement('textarea');
    textarea.id = `room-editor-${name}`;
    textarea.name = name;
    textarea.value = value;
    textarea.rows = 3;
    field.appendChild(label);
    field.appendChild(textarea);
    return field;
  }

  _labeledSelect(name, labelText, options, selectedValue) {
    const field = document.createElement('div');
    field.className = 'room-editor__field';
    const label = document.createElement('label');
    label.setAttribute('for', `room-editor-${name}`);
    label.textContent = labelText;
    const select = document.createElement('select');
    select.id = `room-editor-${name}`;
    select.name = name;
    options.forEach((value) => {
      select.appendChild(makeOption(value, value.replace(/_/g, ' '), value === selectedValue));
    });
    field.appendChild(label);
    field.appendChild(select);
    return field;
  }

  _handleInspectorClick(event) {
    const button = event.target.closest('[data-room-editor-inspector-action]');
    if (!button) {
      return;
    }
    const action = button.getAttribute('data-room-editor-inspector-action');
    const body = this._dom.inspectorBody;
    const val = (name) => body.querySelector(`#room-editor-${name}`)?.value;

    switch (action) {
      case 'apply-room-metadata':
        this._sendCommand('set_room_metadata', {
          changes: {
            room_id: val('room-id'),
            name: val('room-name'),
            description: val('room-description'),
            room_type: val('room-type'),
            size_category: val('room-size'),
          },
        });
        break;
      case 'apply-hex-terrain':
        if (this.selection?.type === 'hex') {
          this._sendCommand('set_hex_terrain', {
            hex: { q: this.selection.q, r: this.selection.r },
            terrain_type: val('hex-terrain'),
          });
        }
        break;
      case 'apply-hex-elevation':
        if (this.selection?.type === 'hex') {
          this._sendCommand('set_hex_elevation', {
            hex: { q: this.selection.q, r: this.selection.r },
            elevation_ft: Number(val('hex-elevation')),
          });
        }
        break;
      case 'remove-hex':
        if (this.selection?.type === 'hex') {
          this._sendCommand('remove_hex', { hex: { q: this.selection.q, r: this.selection.r } });
        }
        break;
      case 'add-entry-port':
      case 'add-exit-port':
        if (this.selection?.type === 'hex') {
          this._addPortAt(action === 'add-entry-port' ? 'entry' : 'exit', this.selection.q, this.selection.r);
        }
        break;
      case 'select-port':
        this._selectPort(button.dataset.portFamily, button.dataset.portId);
        break;
      case 'apply-port':
        if (this.selection?.type === 'port') {
          const family = this.selection.family;
          const changes = {
            label: val('port-label'),
            hex: { q: Number(val('port-q')), r: Number(val('port-r')) },
            edge: Number(val('port-edge')),
          };
          if (family === 'entry') {
            changes.arrival_facing = Number(val('port-facing'));
            changes.is_default = Boolean(body.querySelector('#room-editor-port-default')?.checked);
          } else {
            changes.kind = val('port-kind');
            changes.direction = val('port-direction');
            changes.default_state = val('port-state');
            changes.destination_hint = val('port-destination') || null;
          }
          this._sendCommand(`update_${family}_port`, {
            port_id: this.selection.portId,
            changes,
          });
        }
        break;
      case 'remove-port':
        if (this.selection?.type === 'port') {
          this._sendCommand(`remove_${this.selection.family}_port`, {
            port_id: this.selection.portId,
          });
        }
        break;
      case 'move-placement':
        if (this.selection?.type === 'placement') {
          const placement = this._findPlacement(this.selection.instanceId);
          const q = Number(val('placement-q'));
          const r = Number(val('placement-r'));
          const blocker = this._isSolidFamily(placement?.definition_ref?.family)
            ? this._findSolidPlacementAt(q, r, this.selection.instanceId)
            : null;
          if (blocker) {
            this._showPlacementCollision(q, r, blocker, 'Cannot move placement');
            break;
          }
          this._sendCommand('move_object', {
            instance_id: this.selection.instanceId,
            anchor_hex: { q, r },
          });
        }
        break;
      case 'rotate-placement': {
        if (this.selection?.type === 'placement') {
          const placement = this._findPlacement(this.selection.instanceId);
          const nextFacing = ((Number(placement?.facing) || 0) + 1) % 6;
          this._sendCommand('rotate_object', { instance_id: this.selection.instanceId, facing: nextFacing });
        }
        break;
      }
      case 'remove-placement':
        if (this.selection?.type === 'placement') {
          this._sendCommand('remove_object', { instance_id: this.selection.instanceId });
        }
        break;
      default:
        break;
    }
  }

  // ---------------------------------------------------------------------------
  // Validation panel
  // ---------------------------------------------------------------------------

  _renderValidation(result) {
    const list = this._dom.validationList;
    if (!list) {
      return;
    }
    clearElement(list);
    const findings = [...(result.errors || []), ...(result.warnings || [])];
    if (findings.length === 0) {
      const item = document.createElement('li');
      item.className = 'room-editor__validation-item room-editor__validation-item--ok';
      item.textContent = 'No issues found.';
      list.appendChild(item);
      return;
    }
    findings.forEach((finding) => {
      const item = document.createElement('li');
      item.className = `room-editor__validation-item room-editor__validation-item--${finding.severity}`;
      item.textContent = `[${finding.severity}] ${finding.message} (${finding.path || finding.code})`;
      list.appendChild(item);
    });
  }

  // ---------------------------------------------------------------------------
  // Keyboard
  // ---------------------------------------------------------------------------

  _handleKeydown(event) {
    const target = event.target;
    const isEditableField = target instanceof HTMLInputElement
      || target instanceof HTMLTextAreaElement
      || target instanceof HTMLSelectElement;
    if (isEditableField) {
      return;
    }
    const isMeta = event.ctrlKey || event.metaKey;
    if (isMeta && !event.shiftKey && event.key.toLowerCase() === 'z') {
      event.preventDefault();
      this.undo();
      return;
    }
    if (isMeta && (event.key.toLowerCase() === 'y' || (event.shiftKey && event.key.toLowerCase() === 'z'))) {
      event.preventDefault();
      this.redo();
      return;
    }
    if (event.key === 'Delete' || event.key === 'Backspace') {
      if (this.selection?.type === 'placement') {
        event.preventDefault();
        this._sendCommand('remove_object', { instance_id: this.selection.instanceId });
      } else if (this.selection?.type === 'hex') {
        event.preventDefault();
        this._sendCommand('remove_hex', { hex: { q: this.selection.q, r: this.selection.r } });
      }
    }
  }

  // ---------------------------------------------------------------------------
  // HTTP + status helpers
  // ---------------------------------------------------------------------------

  _urlFor(key, draftId) {
    const template = this.urls[key] || '';
    return template.replace('{draft_id}', encodeURIComponent(draftId));
  }

  _catalogEntryUrl(family, definitionId) {
    const template = this.urls.catalogEntry || '';
    return template
      .replace('{family}', encodeURIComponent(family))
      .replace('{definition_id}', encodeURIComponent(definitionId));
  }

  _canonicalLibraryEditUrl(family, definitionId) {
    const template = this.urls.canonicalLibraryEdit || '';
    const base = template
      .replace('{family}', encodeURIComponent(family))
      .replace('{definition_id}', encodeURIComponent(definitionId));
    if (!base) {
      return '';
    }
    const returnTo = encodeURIComponent(`${window.location.pathname}${window.location.search}`);
    return `${base}?return_to=${returnTo}`;
  }

  /**
   * Fetches (and caches) the normalized catalog entry for a placement's
   * family/definition_id, used to enrich the inspector with name/description
   * details that placements don't carry inline. Returns null on any failure.
   */
  async _fetchCatalogEntry(family, definitionId) {
    const key = `${family}:${definitionId}`;
    if (this._catalogEntryCache.has(key)) {
      return this._catalogEntryCache.get(key);
    }
    const url = this._catalogEntryUrl(family, definitionId);
    if (!url) {
      return null;
    }
    let entry = null;
    try {
      const result = await this._getJson(url);
      entry = result?.data || null;
    } catch (_err) {
      entry = null;
    }
    this._catalogEntryCache.set(key, entry);
    return entry;
  }

  async _runExclusive(fn) {
    if (this._busy) {
      this._setStatus('Please wait for the current action to finish.', 'info');
      return null;
    }
    this._busy = true;
    this._setBusy(true);
    try {
      return await fn();
    } catch (err) {
      this._showError(err);
      return null;
    } finally {
      this._busy = false;
      this._setBusy(false);
    }
  }

  _setBusy(busy) {
    const controls = [
      this._dom.newRoomBtn, this._dom.loadRoomBtn, this._dom.saveBtn, this._dom.publishBtn,
      this._dom.validateBtn, this._dom.roomSelect,
    ];
    controls.forEach((el) => {
      if (el) {
        el.disabled = busy;
      }
    });
    if (!busy) {
      this._updateHistoryButtons();
    }
  }

  async _getJson(url) {
    const res = await fetch(url, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    return this._parseResponse(res);
  }

  async _postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': this.csrfToken,
      },
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
    });
    return this._parseResponse(res);
  }

  async _parseResponse(res) {
    let json = null;
    try {
      json = await res.json();
    } catch (_err) {
      json = null;
    }
    if (!res.ok) {
      const code = json?.error?.code || `http_${res.status}`;
      const message = json?.error?.message || `Request failed with status ${res.status}.`;
      const err = new Error(message);
      err.code = code;
      err.status = res.status;
      throw err;
    }
    return json || {};
  }

  _setStatus(message, level = 'info') {
    const el = this._dom.status;
    if (!el) {
      return;
    }
    el.textContent = message;
    el.setAttribute('data-status-level', level);
  }

  _showError(err, prefix = 'Request failed') {
    const code = err?.code ? ` (${err.code})` : '';
    this._setStatus(`${prefix}: ${err?.message || 'Unknown error'}${code}`, 'error');
    console.error('[RoomEditorShell]', err);
  }

  _logCommandDiagnostics(phase, command, error = null) {
    console.info('[RoomEditor] command diagnostics', {
      phase,
      command_id: command?.command_id || null,
      expected_revision: command?.expected_revision ?? null,
      type: command?.type || null,
      payload: command?.payload || null,
      draftId: this.draft?.draft_id || null,
      roomId: this.draft?.room?.room_id || null,
      status: error?.status || null,
      code: error?.code || null,
      message: error?.message || null,
    });
  }
}

export default RoomEditorShell;
