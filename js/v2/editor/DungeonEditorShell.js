/**
 * @file
 * Canonical Dungeon Editor browser shell (slice 4: placement authoring).
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   02-target-architecture.md, 03-interface-design.md
 *
 * Responsibilities:
 *   - create or load a dungeon draft through the JSON API;
 *   - resolve every placement's room-local geometry into level space with the
 *     shared placementTransform (the same function the server uses);
 *   - drive every change through POST .../commands and re-render only
 *     from the server's response ("server-confirmed state only");
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
 * Links, regions and publication are later slices; this file issues only the
 * slice 4 command types.
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

    /**
     * Active drag, or null. kind is 'place' (from the library) or 'move'
     * (an existing placement). Only the server-confirmed model is ever
     * rendered as truth; the drag only drives the ghost.
     */
    this._drag = null;
    /** @type {string[]} Forward command ids in apply order (undo targets). */
    this._history = [];
    /** @type {string[]} Undo command ids in apply order (redo targets). */
    this._redoStack = [];
    this._windowMove = (event) => this._onDragMove(event);
    this._windowUp = (event) => this._onDragEnd(event);
    this._keydown = (event) => this._onKeydown(event);
  }

  // ---------------------------------------------------------------------------
  // Lifecycle
  // ---------------------------------------------------------------------------

  init() {
    this._bindDom();
    this._bindHeaderEvents();
    this._bindAuthorDrawerEvents();
    this._bindGmEvents();
    this._bindAuthoringEvents();
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
    window.removeEventListener('pointermove', this._windowMove);
    window.removeEventListener('pointerup', this._windowUp);
    window.removeEventListener('pointercancel', this._windowUp);
    document.removeEventListener('keydown', this._keydown);
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
    const url = this._draftUrl('describe');
    const response = await this._getJson(url);
    this._setModel(response.data);
  }

  /**
   * Issues one command and replaces the whole model from the response.
   *
   * On any failure the last server-confirmed model is re-rendered, so a
   * rejected move visibly snaps back. A revision conflict reloads the draft.
   *
   * @returns {Promise<object|null>} The command result, or null if rejected.
   */
  async applyCommand(type, payload) {
    if (!this.model || this._busy) {
      return null;
    }
    const command = {
      command_id: crypto.randomUUID(),
      expected_revision: this.model.revision,
      type,
      payload,
      issued_at: new Date().toISOString(),
    };
    this._setBusy(true);
    try {
      const response = await this._postJson(this._draftUrl('command'), command);
      const result = response.data;
      if (type === 'undo') {
        this._redoStack.push(command.command_id);
      } else if (type === 'redo') {
        this._history.push(command.command_id);
      } else {
        this._history.push(command.command_id);
        this._redoStack = [];
      }
      this.draft = result.draft;
      this._setModel(result.model);
      this._setStatus(`${type.replace(/_/g, ' ')} applied - revision ${result.result_revision}.`, 'info');
      return result;
    } catch (err) {
      this._showError(err, `${type.replace(/_/g, ' ')} rejected`);
      if (err?.status === 409) {
        await this.refresh().catch(() => {});
      } else {
        this._emitMap();
      }
      return null;
    } finally {
      this._setBusy(false);
      this._updateHistoryButtons();
    }
  }

  async undo() {
    const target = this._history[this._history.length - 1];
    if (!target) {
      return null;
    }
    const result = await this.applyCommand('undo', { target_command_id: target });
    if (result) {
      this._history.pop();
    }
    return result;
  }

  async redo() {
    const target = this._redoStack[this._redoStack.length - 1];
    if (!target) {
      return null;
    }
    const result = await this.applyCommand('redo', { target_command_id: target });
    if (result) {
      this._redoStack.pop();
    }
    return result;
  }

  _draftUrl(key) {
    return String(this.urls[key] || '').replace('{draft_id}', encodeURIComponent(this.draft.draft_id));
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
      undoBtn: q('[data-dungeon-editor-action="undo"]'),
      redoBtn: q('[data-dungeon-editor-action="redo"]'),
      metadataForm: q('[data-dungeon-editor-metadata-form]'),
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

  _bindAuthoringEvents() {
    this._dom.undoBtn?.addEventListener('click', () => this.undo());
    this._dom.redoBtn?.addEventListener('click', () => this.redo());

    // Library drag starts on the row, not on the link into the Room Editor.
    this._dom.roomList?.addEventListener('pointerdown', (event) => {
      if (event.button !== 0 || event.target.closest('a')) {
        return;
      }
      const item = event.target.closest('[data-room-id]');
      if (!item || !this.model) {
        return;
      }
      const room = this.roomLibrary.find((r) => r.version_id === item.getAttribute('data-version-id'));
      if (!room || !Array.isArray(room.footprint) || !room.footprint.length) {
        return;
      }
      event.preventDefault();
      item.setAttribute('data-dragging', 'true');
      this._beginDrag({
        kind: 'place',
        roomId: room.room_id,
        versionId: room.version_id,
        footprint: room.footprint.map((h) => ({ q: Number(h.q), r: Number(h.r) })),
        anchor: { q: Number(room.footprint[0].q), r: Number(room.footprint[0].r) },
        rotation: 0,
        item,
        candidate: null,
      });
    });

    this._dom.metadataForm?.addEventListener('change', (event) => {
      const field = event.target.closest('[data-dungeon-editor-meta]');
      if (!field || !this.model) {
        return;
      }
      const key = field.getAttribute('data-dungeon-editor-meta');
      const value = key === 'depth' ? Number.parseInt(field.value, 10) : String(field.value);
      if (key === 'depth' && !Number.isInteger(value)) {
        this._setStatus('Depth must be a whole number.', 'error');
        this._renderMetadataForm();
        return;
      }
      if (String(this.model[key]) === String(value)) {
        return;
      }
      this.applyCommand('set_dungeon_metadata', { changes: { [key]: value } }).then((result) => {
        if (!result) {
          this._renderMetadataForm();
        }
      });
    });

    this._dom.inspectorBody?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-inspector-action]');
      if (!button) {
        return;
      }
      this._runInspectorAction(button.getAttribute('data-inspector-action'));
    });
    this._dom.inspectorBody?.addEventListener('change', (event) => {
      const field = event.target.closest('[data-inspector-field]');
      if (!field) {
        return;
      }
      this._runInspectorField(field.getAttribute('data-inspector-field'), field);
    });

    document.addEventListener('keydown', this._keydown);
  }

  _onKeydown(event) {
    if (!this.model || this._busy) {
      return;
    }
    const target = event.target;
    if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) {
      return;
    }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
      event.preventDefault();
      if (event.shiftKey) {
        this.redo();
      } else {
        this.undo();
      }
      return;
    }
    const placement = this._selectedPlacement();
    if (!placement) {
      return;
    }
    if (event.key === '[' || event.key === ']') {
      event.preventDefault();
      const delta = event.key === ']' ? 1 : 5;
      this.applyCommand('rotate_room_placement', {
        placement_id: placement.placement_id,
        rotation_steps: (placement.rotation_steps + delta) % 6,
      });
    } else if (event.key === 'Delete' || event.key === 'Backspace') {
      event.preventDefault();
      this.applyCommand('remove_room_placement', { placement_id: placement.placement_id });
    }
  }

  _selectedPlacement() {
    return (this.model?.placements || []).find((p) => p.placement_id === this.selectedPlacementId) || null;
  }

  _runInspectorAction(action) {
    const placement = this._selectedPlacement();
    if (!placement) {
      return;
    }
    switch (action) {
      case 'rotate-ccw':
        this.applyCommand('rotate_room_placement', { placement_id: placement.placement_id, rotation_steps: (placement.rotation_steps + 5) % 6 });
        break;
      case 'rotate-cw':
        this.applyCommand('rotate_room_placement', { placement_id: placement.placement_id, rotation_steps: (placement.rotation_steps + 1) % 6 });
        break;
      case 'toggle-entrance':
        this.applyCommand('set_placement_metadata', { placement_id: placement.placement_id, changes: { is_level_entrance: !placement.is_level_entrance } });
        break;
      case 'remove':
        this.applyCommand('remove_room_placement', { placement_id: placement.placement_id });
        break;
      case 'retarget': {
        const select = this._dom.inspectorBody?.querySelector('[data-inspector-field="version"]');
        const versionId = select?.value;
        if (versionId && versionId !== placement.version_id) {
          this.applyCommand('retarget_room_placement', { placement_id: placement.placement_id, version_id: versionId });
        }
        break;
      }
      default:
        break;
    }
  }

  _runInspectorField(field, input) {
    const placement = this._selectedPlacement();
    if (!placement) {
      return;
    }
    if (field === 'label') {
      const label = String(input.value).trim();
      if (label && label !== placement.label) {
        this.applyCommand('set_placement_metadata', { placement_id: placement.placement_id, changes: { label } });
      } else {
        input.value = placement.label;
      }
    } else if (field === 'tags') {
      const tags = Array.from(new Set(String(input.value).split(',').map((t) => t.trim()).filter(Boolean)));
      if (JSON.stringify(tags) !== JSON.stringify(placement.tags)) {
        this.applyCommand('set_placement_metadata', { placement_id: placement.placement_id, changes: { tags } });
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Drag and drop (03-interface-design.md "Drag and drop")
  // ---------------------------------------------------------------------------

  _beginDrag(drag) {
    this._drag = drag;
    this.container.setAttribute('data-dragging', 'true');
    this.hexCanvas?.setPanEnabled(false);
    window.addEventListener('pointermove', this._windowMove);
    window.addEventListener('pointerup', this._windowUp);
    window.addEventListener('pointercancel', this._windowUp);
  }

  /**
   * Axial hex under a client-space pointer, or null when off-canvas.
   */
  _pointerToAxial(clientX, clientY) {
    const canvas = this._dom.canvasContainer?.querySelector('canvas');
    if (!canvas || !this.hexCanvas) {
      return null;
    }
    const rect = canvas.getBoundingClientRect();
    if (clientX < rect.left || clientX > rect.right || clientY < rect.top || clientY > rect.bottom) {
      return null;
    }
    const axial = this.hexCanvas.globalToAxial(clientX - rect.left, clientY - rect.top);
    if (!axial || !Number.isFinite(axial.q) || !Number.isFinite(axial.r)) {
      return null;
    }
    return { q: Math.round(axial.q), r: Math.round(axial.r) };
  }

  /**
   * Candidate origin for a drag: the hex under the pointer minus the anchor's
   * rotated room-local offset, so the grabbed hex stays under the cursor.
   */
  _candidateOrigin(drag, axial) {
    const rotated = transform.rotate(drag.anchor.q, drag.anchor.r, drag.rotation);
    return { q: axial.q - rotated.q, r: axial.r - rotated.r };
  }

  _ghostFor(drag, origin) {
    const spec = { origin, rotation_steps: drag.rotation };
    const hexes = drag.footprint.map((hex) => transform.toLevel(hex, spec));
    const occupancy = this.model?.occupancy || {};
    const own = drag.kind === 'move' ? drag.placementId : null;
    const valid = hexes.every((hex) => {
      const claimants = occupancy[transform.hexKey(hex)];
      return !Array.isArray(claimants) || claimants.every((id) => id === own);
    });
    return { hexes, valid };
  }

  _onDragMove(event) {
    const drag = this._drag;
    if (!drag) {
      return;
    }
    const axial = this._pointerToAxial(event.clientX, event.clientY);
    if (!axial) {
      drag.candidate = null;
      this.bus.emit('map:ghost', null);
      return;
    }
    const origin = this._candidateOrigin(drag, axial);
    if (drag.candidate && drag.candidate.q === origin.q && drag.candidate.r === origin.r) {
      return;
    }
    drag.candidate = origin;
    this.bus.emit('map:ghost', this._ghostFor(drag, origin));
  }

  _onDragEnd(event) {
    const drag = this._drag;
    if (!drag) {
      return;
    }
    this._drag = null;
    this.container.removeAttribute('data-dragging');
    drag.item?.removeAttribute('data-dragging');
    this.hexCanvas?.setPanEnabled(true);
    window.removeEventListener('pointermove', this._windowMove);
    window.removeEventListener('pointerup', this._windowUp);
    window.removeEventListener('pointercancel', this._windowUp);
    this.bus.emit('map:ghost', null);

    if (event.type === 'pointercancel') {
      return;
    }
    const axial = this._pointerToAxial(event.clientX, event.clientY);
    const origin = axial ? this._candidateOrigin(drag, axial) : drag.candidate;
    if (!origin) {
      return;
    }
    if (drag.kind === 'place') {
      this.applyCommand('place_room', {
        room_id: drag.roomId,
        version_id: drag.versionId,
        origin,
        rotation_steps: 0,
      }).then((result) => {
        if (result?.placement_id) {
          this._selectPlacement(result.placement_id);
        }
      });
      return;
    }
    if (origin.q === drag.origin.q && origin.r === drag.origin.r) {
      this._selectPlacement(drag.placementId);
      return;
    }
    this.applyCommand('move_room_placement', { placement_id: drag.placementId, origin });
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
    this.bus.on('canvas:hex-clicked', ({ q, r, button, clientX, clientY }) => this._handleHexClick(q, r, button, clientX, clientY));

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

  _handleHexClick(q, r, button = 0, clientX = null, clientY = null) {
    const axial = { q: Number(q), r: Number(r) };
    const claimants = this.model?.occupancy?.[transform.hexKey(axial)];
    if (!Array.isArray(claimants) || !claimants.length) {
      return;
    }
    const placementId = claimants.includes(this.selectedPlacementId) ? this.selectedPlacementId : claimants[0];
    this._selectPlacement(placementId);
    const placement = this._selectedPlacement();
    if (button !== 0 || this._busy || !placement?.resolved || this._drag) {
      return;
    }
    // Anchor = the grabbed hex in room-local space, so it tracks the cursor.
    const spec = { origin: placement.origin, rotation_steps: placement.rotation_steps };
    const anchor = transform.toRoomLocal(axial, spec);
    this._beginDrag({
      kind: 'move',
      placementId: placement.placement_id,
      origin: { ...placement.origin },
      rotation: placement.rotation_steps,
      footprint: placement.room.hexes.map((h) => ({ q: h.q, r: h.r })),
      anchor,
      item: null,
      candidate: null,
      startClient: { x: clientX, y: clientY },
    });
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
      this._history = [];
      this._redoStack = [];
      await this.refresh();
      this._setStatus(
        `${this.model.name} - revision ${this.model.revision}, ${this.model.placements.length} placement(s).`,
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
    this._renderMetadataForm();
    this._renderPlacementList();
    this._renderInspector();
    this._renderValidation(model.validation);
    this._renderGmContext();
    this._updateHistoryButtons();
  }

  _renderMetadataForm() {
    const form = this._dom.metadataForm;
    if (!form) {
      return;
    }
    form.querySelectorAll('[data-dungeon-editor-meta]').forEach((field) => {
      const key = field.getAttribute('data-dungeon-editor-meta');
      field.value = this.model ? String(this.model[key] ?? '') : '';
      field.disabled = !this.model;
    });
  }

  _updateHistoryButtons() {
    if (this._dom.undoBtn) {
      this._dom.undoBtn.disabled = this._busy || !this.model || this._history.length === 0;
    }
    if (this._dom.redoBtn) {
      this._dom.redoBtn.disabled = this._busy || !this.model || this._redoStack.length === 0;
    }
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

    const labelField = makeEl('label', 'room-editor__field', 'Label ');
    const labelInput = document.createElement('input');
    labelInput.type = 'text';
    labelInput.maxLength = 200;
    labelInput.value = placement.label;
    labelInput.setAttribute('data-inspector-field', 'label');
    labelField.appendChild(labelInput);
    body.appendChild(labelField);

    const tagsField = makeEl('label', 'room-editor__field', 'Tags (comma separated) ');
    const tagsInput = document.createElement('input');
    tagsInput.type = 'text';
    tagsInput.value = placement.tags.join(', ');
    tagsInput.setAttribute('data-inspector-field', 'tags');
    tagsField.appendChild(tagsInput);
    body.appendChild(tagsField);

    const versions = this.roomLibrary.filter((room) => room.room_id === placement.room_id);
    if (versions.length) {
      const versionField = makeEl('label', 'room-editor__field', 'Published version ');
      const select = document.createElement('select');
      select.setAttribute('data-inspector-field', 'version');
      versions.forEach((room) => {
        const option = document.createElement('option');
        option.value = room.version_id;
        option.textContent = `v${room.version} (${room.hex_count} hex)`;
        option.selected = room.version_id === placement.version_id;
        select.appendChild(option);
      });
      if (!versions.some((room) => room.version_id === placement.version_id)) {
        const option = document.createElement('option');
        option.value = placement.version_id;
        option.textContent = `${placement.version_id} (pinned, not in library)`;
        option.selected = true;
        select.appendChild(option);
      }
      versionField.appendChild(select);
      body.appendChild(versionField);
    }

    const actions = makeEl('div', 'dungeon-editor__inspector-actions');
    const action = (name, text, extraClass = '') => {
      const btn = makeEl('button', `room-editor__button ${extraClass}`.trim(), text);
      btn.type = 'button';
      btn.setAttribute('data-inspector-action', name);
      return btn;
    };
    actions.append(
      action('rotate-ccw', '⟲ 60°'),
      action('rotate-cw', '⟳ 60°'),
      action('toggle-entrance', placement.is_level_entrance ? 'Unset entrance' : 'Set as entrance'),
    );
    if (versions.length) {
      actions.appendChild(action('retarget', 'Retarget to selected version'));
    }
    actions.appendChild(action('remove', 'Remove', 'room-editor__button--danger'));
    body.appendChild(actions);
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
    this._updateHistoryButtons();
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
    if (Array.isArray(err?.findings) && err.findings.length && this._dom.validationList) {
      // Rejection findings are shown above the draft's own (still valid) state.
      err.findings.slice().reverse().forEach((finding) => {
        const where = finding.hex ? ` @ (${finding.hex.q}, ${finding.hex.r})` : (finding.pointer ? ` at ${finding.pointer}` : '');
        const item = makeEl(
          'li',
          `room-editor__validation-item room-editor__validation-item--error room-editor__validation-item--rejected`,
          `[rejected] ${finding.message}${where} (${finding.code})`,
        );
        this._dom.validationList.prepend(item);
      });
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
      const err = new Error(json?.error?.message || `Request failed with status ${res.status}.`);
      err.code = json?.error?.code || `http_${res.status}`;
      err.status = res.status;
      err.findings = json?.error?.findings || null;
      throw err;
    }
    return json || {};
  }
}
