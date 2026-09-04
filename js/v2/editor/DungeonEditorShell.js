/**
 * @file
 * Canonical Dungeon Editor browser shell (slice 3: read-only).
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   02-target-architecture.md, 03-interface-design.md
 *
 * Responsibilities:
 *   - create or load a dungeon draft through the JSON API;
 *   - resolve every placement's room-local geometry into level space with the
 *     shared placementTransform (the same function the server uses);
 *   - hand already-transformed geometry to HexCanvas via `map:changed`;
 *   - render the author drawer (room library, placements, inspector,
 *     validation) and the GM panel's grounded context.
 *
 * Rules carried over from the Room Editor:
 *   - the shell never keeps state the server did not confirm; every render
 *     starts from the server read model;
 *   - the shell never does placement math outside placementTransform.js;
 *   - HexCanvas knows nothing about dungeons, drafts, or commands.
 *
 * Slice 3 issues no commands. There is no mutation path in this file.
 */

import { GameEventBus } from '../GameEventBus.js';
import { HexCanvas } from '../canvas/HexCanvas.js';
import './placementTransform.js';

const transform = globalThis.DungeonCrawlerPlacementTransform;
if (!transform) {
  throw new Error('placement_transform_unavailable');
}

const PLACEMENT_TINTS = [
  0x60a5fa, 0x34d399, 0xfbbf24, 0xf472b6, 0xa78bfa, 0xfb923c, 0x2dd4bf, 0xe879f9,
];

function clearElement(el) {
  while (el && el.firstChild) {
    el.removeChild(el.firstChild);
  }
}

function makeEl(tag, className, text) {
  const el = document.createElement(tag);
  if (className) {
    el.className = className;
  }
  if (text !== undefined && text !== null) {
    el.textContent = text;
  }
  return el;
}

function hexColor(int) {
  return `#${int.toString(16).padStart(6, '0')}`;
}

export class DungeonEditorShell {
  /**
   * @param {HTMLElement} container Root element carrying [data-dungeon-editor].
   * @param {object} settings drupalSettings.dungeoncrawlerContent.dungeonEditor
   */
  constructor(container, settings = {}) {
    this.container = container;
    this.settings = settings && typeof settings === 'object' ? settings : {};
    this.urls = this.settings.urls || {};
    this.csrfToken = String(this.settings.csrfToken || '');

    this.bus = new GameEventBus();
    this.hexCanvas = null;

    /** @type {object|null} dungeon-editor-draft-v1 */
    this.draft = null;
    /** @type {object|null} dungeon-editor-read-model-v1 */
    this.model = null;
    /** @type {Array} Published room library entries. */
    this.roomLibrary = [];
    /** @type {string|null} Selected placement_id. */
    this.selectedPlacementId = null;

    this._dom = {};
    this._resizeObserver = null;
    this._busy = false;
  }

  // ---------------------------------------------------------------------------
  // Lifecycle
  // ---------------------------------------------------------------------------

  init() {
    this._bindDom();
    this._bindHeaderEvents();
    this._bindAuthorDrawerEvents();
    this._bindGmEvents();
    this._initCanvas();
    this._loadRoomLibrary();

    const initialDungeonId = this.settings.selectedDungeonId || null;
    if (initialDungeonId) {
      this.loadDungeon(initialDungeonId);
    } else {
      this._setStatus('Select a dungeon to view, or start a new one.', 'info');
    }
  }

  destroy() {
    if (this._resizeObserver) {
      this._resizeObserver.disconnect();
      this._resizeObserver = null;
    }
    this.hexCanvas?.destroy();
    this.hexCanvas = null;
    this.bus.destroy();
  }

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  async loadDungeon(dungeonId) {
    return this._createOrLoadDraft(dungeonId ? String(dungeonId) : null);
  }

  async newDungeon() {
    return this._createOrLoadDraft(null);
  }

  /**
   * Re-reads the draft's read model from the server and re-renders.
   */
  async refresh() {
    if (!this.draft?.draft_id) {
      return;
    }
    const url = String(this.urls.describe || '').replace('{draft_id}', encodeURIComponent(this.draft.draft_id));
    const response = await this._getJson(url);
    this._setModel(response.data);
  }

  // ---------------------------------------------------------------------------
  // DOM binding
  // ---------------------------------------------------------------------------

  _bindDom() {
    const root = this.container;
    const q = (selector) => root.querySelector(selector);
    this._dom = {
      status: q('[data-dungeon-editor-status]'),
      revision: q('[data-dungeon-editor-revision]'),
      dungeonSelect: q('[data-dungeon-editor-dungeon-select]'),
      loadBtn: q('[data-dungeon-editor-action="load-dungeon"]'),
      newBtn: q('[data-dungeon-editor-action="new-dungeon"]'),
      workspace: q('[data-dungeon-editor-workspace]'),
      authorDrawer: q('[data-dungeon-editor-author-drawer]'),
      authorDrawerToggle: q('[data-dungeon-editor-action="toggle-author-drawer"]'),
      canvasContainer: q('[data-dungeon-editor-canvas]'),
      roomList: q('[data-dungeon-editor-room-list]'),
      roomListEmpty: q('[data-dungeon-editor-room-list-empty]'),
      placementList: q('[data-dungeon-editor-placement-list]'),
      placementEmpty: q('[data-dungeon-editor-placement-empty]'),
      inspectorBody: q('[data-dungeon-editor-inspector-body]'),
      validationList: q('[data-dungeon-editor-validation-list]'),
      gmState: q('[data-dungeon-editor-gm-state]'),
      gmContext: q('[data-dungeon-editor-gm-context]'),
      gmContextToggle: q('[data-dungeon-editor-action="gm-toggle-context"]'),
    };
  }

  _bindHeaderEvents() {
    this._dom.loadBtn?.addEventListener('click', () => {
      const id = this._dom.dungeonSelect?.value || '';
      if (!id) {
        this._setStatus('Choose a dungeon first.', 'warning');
        return;
      }
      this.loadDungeon(id);
    });
    this._dom.newBtn?.addEventListener('click', () => this.newDungeon());
  }

  _bindAuthorDrawerEvents() {
    this._setAuthorDrawerOpen(false);
    this._dom.authorDrawerToggle?.addEventListener('click', () => {
      const open = this._dom.authorDrawerToggle.getAttribute('aria-expanded') === 'true';
      this._setAuthorDrawerOpen(!open);
    });
    this._dom.placementList?.addEventListener('click', (event) => {
      const item = event.target.closest('[data-placement-id]');
      if (item) {
        this._selectPlacement(item.getAttribute('data-placement-id'));
      }
    });
  }

  _setAuthorDrawerOpen(open) {
    if (this._dom.authorDrawer) {
      this._dom.authorDrawer.hidden = !open;
    }
    this._dom.authorDrawerToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    this._dom.workspace?.setAttribute('data-author-drawer-open', open ? 'true' : 'false');
    this.hexCanvas?.resizeToContainer();
  }

  _bindGmEvents() {
    this._dom.gmContextToggle?.addEventListener('click', () => {
      const expanded = this._dom.gmContextToggle.getAttribute('aria-expanded') === 'true';
      this._dom.gmContextToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      if (this._dom.gmContext) {
        this._dom.gmContext.hidden = expanded;
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Canvas
  // ---------------------------------------------------------------------------

  _initCanvas() {
    if (!this._dom.canvasContainer) {
      return;
    }
    this.hexCanvas = new HexCanvas(this._dom.canvasContainer, this.bus, {
      hexSize: 24,
      showGrid: true,
      showHexIndicators: false,
    });
    this.hexCanvas.init();
    this.bus.on('canvas:hex-clicked', ({ q, r }) => this._handleHexClick(q, r));

    if (typeof ResizeObserver !== 'undefined') {
      this._resizeObserver = new ResizeObserver(() => {
        window.requestAnimationFrame(() => {
          this.hexCanvas?.resizeToContainer();
          this._fitMapToView();
        });
      });
      this._resizeObserver.observe(this._dom.canvasContainer);
    }
  }

  _handleHexClick(q, r) {
    const key = transform.hexKey({ q: Number(q), r: Number(r) });
    const claimants = this.model?.occupancy?.[key];
    if (Array.isArray(claimants) && claimants.length) {
      this._selectPlacement(claimants[0]);
    }
  }

  /**
   * Projects the read model into the `map:changed` contract.
   *
   * Every hex and port is transformed here with the shared module. The
   * server has already computed occupancy with its own transformer; both are
   * pinned to the same fixture vectors, so they cannot disagree without a
   * test failing first.
   */
  _emitMap() {
    if (!this.model) {
      this.bus.emit('map:changed', { mapId: null, placements: null });
      return;
    }
    const placements = [];
    const ports = [];
    const portIndex = new Map();
    const linkedExitPorts = new Set();
    (this.model.port_links || []).forEach((link) => {
      linkedExitPorts.add(`${link.from.placement_id}:${link.from.port_id}`);
    });

    this.model.placements.forEach((placement, index) => {
      const tint = PLACEMENT_TINTS[index % PLACEMENT_TINTS.length];
      const spec = { origin: placement.origin, rotation_steps: placement.rotation_steps };
      const hexes = placement.resolved
        ? placement.room.hexes.map((hex) => ({
          ...hex,
          ...transform.toLevel({ q: hex.q, r: hex.r }, spec),
        }))
        : [];
      placements.push({
        placementId: placement.placement_id,
        label: placement.label,
        tint,
        hexes,
      });
      if (placement.resolved) {
        placement.room.ports.forEach((port) => {
          const level = transform.toLevelPort({ q: port.q, r: port.r }, port.edge, spec);
          const entry = {
            placementId: placement.placement_id,
            portId: port.port_id,
            kind: port.kind,
            q: level.q,
            r: level.r,
            edge: level.edge,
            linked: port.kind === 'exit' && linkedExitPorts.has(`${placement.placement_id}:${port.port_id}`),
          };
          ports.push(entry);
          portIndex.set(`${placement.placement_id}:${port.port_id}`, entry);
        });
      }
    });

    const links = (this.model.port_links || []).map((link) => {
      const from = portIndex.get(`${link.from.placement_id}:${link.from.port_id}`);
      const to = portIndex.get(`${link.to.placement_id}:${link.to.port_id}`);
      if (!from || !to) {
        return null;
      }
      return { linkId: link.link_id, kind: link.kind, direction: link.direction, from, to };
    }).filter(Boolean);

    this.bus.emit('map:changed', {
      mapId: this.model.draft_id,
      placements,
      ports,
      links,
      occupancy: this.model.occupancy || {},
    });
  }

  _fitMapToView() {
    if (!this.hexCanvas?.app || !this.model) {
      return;
    }
    const hexSize = Number(this.hexCanvas.config.hexSize || 24);
    const keys = Object.keys(this.model.occupancy || {});
    if (keys.length === 0) {
      this.hexCanvas.setWorldScale(1);
      const w = Number(this.hexCanvas.app.screen?.width || 800);
      const h = Number(this.hexCanvas.app.screen?.height || 600);
      this.hexCanvas.setWorldPosition(w / 2, h / 2);
      return;
    }
    const bounds = keys.reduce((acc, key) => {
      const [q, r] = key.split(':').map(Number);
      const p = this.hexCanvas.axialToPixel(q, r, hexSize);
      return {
        minX: Math.min(acc.minX, p.x - hexSize),
        maxX: Math.max(acc.maxX, p.x + hexSize),
        minY: Math.min(acc.minY, p.y - hexSize),
        maxY: Math.max(acc.maxY, p.y + hexSize),
      };
    }, { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity });
    const screenWidth = Number(this.hexCanvas.app.screen?.width || 800);
    const screenHeight = Number(this.hexCanvas.app.screen?.height || 600);
    const padding = 80;
    const fit = Math.min(
      (screenWidth - padding) / Math.max(1, bounds.maxX - bounds.minX),
      (screenHeight - padding) / Math.max(1, bounds.maxY - bounds.minY),
    );
    const scale = Math.max(this.hexCanvas.config.minZoom, Math.min(this.hexCanvas.config.maxZoom, Number.isFinite(fit) ? fit : 1));
    const centerX = (bounds.minX + bounds.maxX) / 2;
    const centerY = (bounds.minY + bounds.maxY) / 2;
    this.hexCanvas.setWorldScale(scale);
    this.hexCanvas.setWorldPosition((screenWidth / 2) - (centerX * scale), (screenHeight / 2) - (centerY * scale));
  }

  // ---------------------------------------------------------------------------
  // Draft lifecycle
  // ---------------------------------------------------------------------------

  async _createOrLoadDraft(dungeonId) {
    if (this._busy) {
      return;
    }
    this._setBusy(true);
    this._setStatus(dungeonId ? `Loading ${dungeonId}...` : 'Creating a new dungeon draft...', 'info');
    try {
      const response = await this._postJson(this.urls.create, dungeonId ? { dungeon_id: dungeonId } : {});
      this.draft = response.data;
      await this.refresh();
      this._setStatus(
        `${this.model.name} - revision ${this.model.revision}, ${this.model.placements.length} placement(s). Read-only.`,
        'info',
      );
    } catch (err) {
      this._showError(err, dungeonId ? `Could not load ${dungeonId}` : 'Could not create draft');
    } finally {
      this._setBusy(false);
    }
  }

  _setModel(model) {
    this.model = model;
    if (this.selectedPlacementId && !model.placements.some((p) => p.placement_id === this.selectedPlacementId)) {
      this.selectedPlacementId = null;
    }
    if (this._dom.revision) {
      this._dom.revision.textContent = `rev ${model.revision} · ${model.placements.length} rooms · ${(model.port_links || []).length} links`;
    }
    this._emitMap();
    this._fitMapToView();
    this._renderPlacementList();
    this._renderInspector();
    this._renderValidation(model.validation);
    this._renderGmContext();
  }

  _selectPlacement(placementId) {
    this.selectedPlacementId = placementId || null;
    this._renderPlacementList();
    this._renderInspector();
  }

  // ---------------------------------------------------------------------------
  // Drawer rendering
  // ---------------------------------------------------------------------------

  async _loadRoomLibrary() {
    try {
      const response = await this._getJson(this.urls.rooms);
      this.roomLibrary = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
      this.roomLibrary = [];
      this._showError(err, 'Could not load the room library');
    }
    this._renderRoomLibrary();
  }

  _renderRoomLibrary() {
    const list = this._dom.roomList;
    if (!list) {
      return;
    }
    clearElement(list);
    if (this._dom.roomListEmpty) {
      this._dom.roomListEmpty.hidden = this.roomLibrary.length > 0;
    }
    this.roomLibrary.forEach((room) => {
      const item = makeEl('li', 'dungeon-editor__room-item');
      item.setAttribute('data-room-id', room.room_id);
      item.setAttribute('data-version-id', room.version_id);
      const swatch = makeEl('span', 'dungeon-editor__swatch');
      swatch.style.background = '#475569';
      const label = makeEl('span');
      const link = makeEl('a', 'dungeon-editor__item-link', room.name);
      link.href = String(this.urls.roomEditor || '#').replace('{room_id}', encodeURIComponent(room.room_id));
      link.target = '_blank';
      link.rel = 'noopener';
      label.appendChild(link);
      const meta = makeEl('span', 'dungeon-editor__item-meta', `v${room.version} · ${room.hex_count} hex · ${room.exit_port_count} exit`);
      item.append(swatch, label, meta);
      list.appendChild(item);
    });
  }

  _renderPlacementList() {
    const list = this._dom.placementList;
    if (!list) {
      return;
    }
    clearElement(list);
    const placements = this.model?.placements || [];
    if (this._dom.placementEmpty) {
      this._dom.placementEmpty.hidden = placements.length > 0;
    }
    placements.forEach((placement, index) => {
      const item = makeEl('li', 'dungeon-editor__placement-item');
      item.setAttribute('data-placement-id', placement.placement_id);
      item.setAttribute('aria-selected', placement.placement_id === this.selectedPlacementId ? 'true' : 'false');
      const swatch = makeEl('span', 'dungeon-editor__swatch');
      swatch.style.background = hexColor(PLACEMENT_TINTS[index % PLACEMENT_TINTS.length]);
      const label = makeEl('span', null, `${placement.label}${placement.is_level_entrance ? ' (entrance)' : ''}`);
      const meta = makeEl(
        'span',
        'dungeon-editor__item-meta',
        placement.resolved
          ? `(${placement.origin.q}, ${placement.origin.r}) r${placement.rotation_steps}`
          : 'unresolved version',
      );
      item.append(swatch, label, meta);
      list.appendChild(item);
    });
  }

  _renderInspector() {
    const body = this._dom.inspectorBody;
    if (!body) {
      return;
    }
    clearElement(body);
    const placement = (this.model?.placements || []).find((p) => p.placement_id === this.selectedPlacementId);
    if (!placement) {
      body.appendChild(makeEl('p', 'room-editor__hint', this.model ? 'Select a placement to inspect it.' : 'Load or create a dungeon to begin.'));
      return;
    }
    const dl = makeEl('dl', 'dungeon-editor__inspector-grid');
    const rows = [
      ['Label', placement.label],
      ['Room', placement.resolved ? placement.room.name : placement.room_id],
      ['Room id', placement.room_id],
      ['Version', placement.version_id],
      ['Origin', `q=${placement.origin.q} r=${placement.origin.r}`],
      ['Rotation', `${placement.rotation_steps} step(s) = ${placement.rotation_steps * 60}°`],
      ['Entrance', placement.is_level_entrance ? 'yes' : 'no'],
      ['Footprint', placement.resolved ? `${placement.room.hex_count} hexes` : 'unresolved'],
      ['Ports', placement.resolved ? `${placement.room.ports.length}` : 'unresolved'],
      ['Tags', placement.tags.length ? placement.tags.join(', ') : '-'],
    ];
    rows.forEach(([term, value]) => {
      dl.appendChild(makeEl('dt', null, term));
      dl.appendChild(makeEl('dd', null, String(value)));
    });
    body.appendChild(dl);
  }

  _renderValidation(result) {
    const list = this._dom.validationList;
    if (!list) {
      return;
    }
    clearElement(list);
    const findings = result?.findings || [];
    if (findings.length === 0) {
      list.appendChild(makeEl('li', 'room-editor__validation-item room-editor__validation-item--ok', 'No issues found.'));
      return;
    }
    findings.forEach((finding) => {
      const where = finding.hex ? ` @ (${finding.hex.q}, ${finding.hex.r})` : '';
      list.appendChild(makeEl(
        'li',
        `room-editor__validation-item room-editor__validation-item--${finding.severity}`,
        `[${finding.severity}] ${finding.message}${where} (${finding.code})`,
      ));
    });
  }

  /**
   * The grounded context the slice 7 assistant will receive, shown now so
   * the panel is honest about what the assistant would see.
   */
  _renderGmContext() {
    const el = this._dom.gmContext;
    if (!el) {
      return;
    }
    clearElement(el);
    if (!this.model) {
      el.appendChild(makeEl('p', 'room-editor__hint', 'No draft loaded.'));
      return;
    }
    const summary = {
      draft_id: this.model.draft_id,
      revision: this.model.revision,
      name: this.model.name,
      placements: this.model.placements.length,
      port_links: (this.model.port_links || []).length,
      regions: (this.model.regions || []).length,
      validation: {
        is_valid: this.model.validation?.is_valid,
        counts: this.model.validation?.counts,
      },
    };
    const pre = makeEl('pre', null, JSON.stringify(summary, null, 2));
    el.appendChild(pre);
    if (this._dom.gmState) {
      this._dom.gmState.textContent = 'Not connected (slice 7)';
    }
  }

  // ---------------------------------------------------------------------------
  // Status / transport
  // ---------------------------------------------------------------------------

  _setBusy(busy) {
    this._busy = busy;
    [this._dom.loadBtn, this._dom.newBtn, this._dom.dungeonSelect].forEach((el) => {
      if (el) {
        el.disabled = busy;
      }
    });
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
    console.error('[DungeonEditorShell]', err);
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
      const err = new Error(json?.error?.message || `Request failed with status ${res.status}.`);
      err.code = json?.error?.code || `http_${res.status}`;
      err.status = res.status;
      err.findings = json?.error?.findings || null;
      throw err;
    }
    return json || {};
  }
}
