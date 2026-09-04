/**
 * @file
 * Canonical Dungeon Editor browser shell (slices 3-5: placements, links, regions).
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
 *     validation) and the GM assistant panel (dungeon_editor surface).
 *
 * Rules carried over from the Room Editor:
 *   - the shell never keeps state the server did not confirm; every render
 *     starts from the server read model;
 *   - the shell never does placement math outside placementTransform.js;
 *   - HexCanvas knows nothing about dungeons, drafts, or commands.
 *
 * Link legality is decided by the server; the shell only pre-computes the
 * legal targets for highlighting with the same sealed-link geometry
 * (10-placement-transform-spec.md §5: Hb == neighbor(Ha, Ea), Eb == opposite(Ea)).
 * Publication is a later slice.
 */

import { GameEventBus } from '../GameEventBus.js';
import { HexCanvas } from '../canvas/HexCanvas.js';
import './placementTransform.js';

const transform = globalThis.DungeonCrawlerPlacementTransform;
if (!transform) {
  throw new Error('placement_transform_unavailable');
}

const LINK_KINDS = ['hallway', 'archway', 'door', 'hatch', 'portcullis', 'secret_door', 'magical_barrier', 'collapsed', 'bridge', 'one_way_drop'];
const LINK_DIRECTIONS = ['bidirectional', 'one_way'];
const LINK_STATES = ['open', 'closed', 'locked', 'barred', 'trapped', 'triggered', 'destroyed'];

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
    /** @type {string|null} Selected link_id (exclusive with the others). */
    this.selectedLinkId = null;
    /** @type {string|null} Selected region_id (exclusive with the others). */
    this.selectedRegionId = null;
    /**
     * Click-to-link gesture. from = the level-space exit port; legal = keys
     * ("placementId:portId") of entry ports that satisfy the sealed-link
     * rule; to = the chosen legal entry, awaiting kind/direction/state.
     * @type {{from: object, legal: Set<string>, to: object|null}|null}
     */
    this._linking = null;

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
      linkList: q('[data-dungeon-editor-link-list]'),
      linkEmpty: q('[data-dungeon-editor-link-empty]'),
      regionList: q('[data-dungeon-editor-region-list]'),
      regionEmpty: q('[data-dungeon-editor-region-empty]'),
      regionForm: q('[data-dungeon-editor-region-form]'),
      validationList: q('[data-dungeon-editor-validation-list]'),
      gmPanel: q('[data-dungeon-editor-gm-panel]'),
      gmState: q('[data-dungeon-editor-gm-state]'),
      gmContext: q('[data-dungeon-editor-gm-context]'),
      gmContextToggle: q('[data-dungeon-editor-action="gm-toggle-context"]'),
      gmTools: q('[data-dungeon-editor-gm-tools]'),
      gmToolsToggle: q('[data-dungeon-editor-action="gm-toggle-tools"]'),
      gmTranscript: q('[data-dungeon-editor-gm-transcript]'),
      gmPlan: q('[data-dungeon-editor-gm-plan]'),
      gmPlanList: q('[data-dungeon-editor-gm-plan-list]'),
      gmApplyPlanBtn: q('[data-dungeon-editor-action="gm-apply-plan"]'),
      gmPreviewPlanBtn: q('[data-dungeon-editor-action="gm-preview-plan"]'),
      gmDiscardPlanBtn: q('[data-dungeon-editor-action="gm-discard-plan"]'),
      gmForm: q('[data-dungeon-editor-gm-form]'),
      gmInput: q('[data-dungeon-editor-gm-input]'),
      gmDryRun: q('[data-dungeon-editor-gm-dry-run]'),
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
    this._dom.linkList?.addEventListener('click', (event) => {
      const item = event.target.closest('[data-link-id]');
      if (item) {
        this._selectLink(item.getAttribute('data-link-id'));
      }
    });
    this._dom.regionList?.addEventListener('click', (event) => {
      const item = event.target.closest('[data-region-id]');
      if (item) {
        this._selectRegion(item.getAttribute('data-region-id'));
      }
    });
    this._dom.regionForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!this.model) {
        return;
      }
      const form = this._dom.regionForm;
      const regionId = String(form.elements.region_id.value).trim();
      const name = String(form.elements.name.value).trim();
      if (!regionId || !name) {
        this._setStatus('Region id and name are both required.', 'error');
        return;
      }
      this.applyCommand('add_region', { region_id: regionId, name, placement_ids: [] }).then((result) => {
        if (result) {
          form.reset();
          this._selectRegion(regionId);
        }
      });
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
    if (event.key === 'Escape' && this._linking) {
      event.preventDefault();
      this._cancelLinking('Linking cancelled.');
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
    if (action.startsWith('link-')) {
      this._runLinkAction(action);
      return;
    }
    if (action.startsWith('region-')) {
      this._runRegionAction(action);
      return;
    }
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
    if (field.startsWith('link-')) {
      this._runLinkField(field, input);
      return;
    }
    if (field.startsWith('region-')) {
      this._runRegionField(field, input);
      return;
    }
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
    this._gm = { context: null, manifest: null, plan: null };

    this._dom.gmContextToggle?.addEventListener('click', () => {
      this._toggleDisclosure(this._dom.gmContextToggle, this._dom.gmContext);
    });
    this._dom.gmToolsToggle?.addEventListener('click', () => {
      this._toggleDisclosure(this._dom.gmToolsToggle, this._dom.gmTools);
    });
    this._dom.gmForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      this._submitGmMessage();
    });
    this._dom.gmInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        this._submitGmMessage();
      }
    });
    this._dom.gmTools?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-gm-tool]');
      if (!button || !this._dom.gmInput) {
        return;
      }
      const template = button.getAttribute('data-gm-template') || '{}';
      this._dom.gmInput.value = `${button.getAttribute('data-gm-tool')} ${template}`;
      this._dom.gmInput.focus();
    });
    this._dom.gmDiscardPlanBtn?.addEventListener('click', () => this._setGmPlan(null));
    this._dom.gmPreviewPlanBtn?.addEventListener('click', () => this._previewGmPlan());
    this._dom.gmApplyPlanBtn?.addEventListener('click', () => this._applyGmPlan());

    this._setGmState('Load or create a dungeon to ground the assistant.');
  }

  _toggleDisclosure(button, body) {
    const open = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', open ? 'false' : 'true');
    if (body) {
      body.hidden = open;
    }
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
    this.bus.on('canvas:port-clicked', (port) => this._handlePortClick(port));

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
    if (this._linking) {
      this._cancelLinking('Linking cancelled - click a highlighted entry port to link, Esc to cancel.');
      return;
    }
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
          const key = `${placement.placement_id}:${port.port_id}`;
          const entry = {
            placementId: placement.placement_id,
            portId: port.port_id,
            kind: port.kind,
            q: level.q,
            r: level.r,
            edge: level.edge,
            linked: port.kind === 'exit' && linkedExitPorts.has(key),
            highlight: this._portHighlight(key, port.kind),
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
    if (this.selectedLinkId && !(model.port_links || []).some((l) => l.link_id === this.selectedLinkId)) {
      this.selectedLinkId = null;
    }
    if (this.selectedRegionId && !(model.regions || []).some((r) => r.region_id === this.selectedRegionId)) {
      this.selectedRegionId = null;
    }
    if (this._linking) {
      // Geometry may have changed underneath the gesture; recompute or drop.
      const from = this._levelPorts().find((p) => p.key === this._linking.from.key && p.kind === 'exit' && !p.linked);
      this._linking = from ? { from, legal: this._legalEntryTargets(from), to: null } : null;
      this.container.toggleAttribute('data-linking', !!this._linking);
    }
    if (this._dom.revision) {
      this._dom.revision.textContent = `rev ${model.revision} · ${model.placements.length} rooms · ${(model.port_links || []).length} links`;
    }
    this._emitMap();
    this._fitMapToView();
    this._renderMetadataForm();
    this._renderPlacementList();
    this._renderLinkList();
    this._renderRegionList();
    this._renderInspector();
    this._renderValidation(model.validation);
    this._refreshGmContext();
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
    this.selectedLinkId = null;
    this.selectedRegionId = null;
    this._renderSelection();
  }

  _selectLink(linkId) {
    this.selectedLinkId = linkId || null;
    this.selectedPlacementId = null;
    this.selectedRegionId = null;
    this._renderSelection();
  }

  _selectRegion(regionId) {
    this.selectedRegionId = regionId || null;
    this.selectedPlacementId = null;
    this.selectedLinkId = null;
    this._renderSelection();
  }

  _renderSelection() {
    this._renderPlacementList();
    this._renderLinkList();
    this._renderRegionList();
    this._renderInspector();
  }

  _selectedLink() {
    return (this.model?.port_links || []).find((l) => l.link_id === this.selectedLinkId) || null;
  }

  _selectedRegion() {
    return (this.model?.regions || []).find((r) => r.region_id === this.selectedRegionId) || null;
  }

  _placementLabel(placementId) {
    return (this.model?.placements || []).find((p) => p.placement_id === placementId)?.label || placementId;
  }

  // ---------------------------------------------------------------------------
  // Click-to-link gesture (03-interface-design.md "Linking ports")
  // ---------------------------------------------------------------------------

  /**
   * Every resolved port in level space, keyed "placementId:portId".
   */
  _levelPorts() {
    const linked = new Set((this.model?.port_links || []).map((l) => `${l.from.placement_id}:${l.from.port_id}`));
    const ports = [];
    (this.model?.placements || []).forEach((placement) => {
      if (!placement.resolved) {
        return;
      }
      const spec = { origin: placement.origin, rotation_steps: placement.rotation_steps };
      placement.room.ports.forEach((port) => {
        const level = transform.toLevelPort({ q: port.q, r: port.r }, port.edge, spec);
        const key = `${placement.placement_id}:${port.port_id}`;
        ports.push({
          key,
          placementId: placement.placement_id,
          portId: port.port_id,
          label: placement.label,
          kind: port.kind,
          q: level.q,
          r: level.r,
          edge: level.edge,
          linked: port.kind === 'exit' && linked.has(key),
        });
      });
    });
    return ports;
  }

  /**
   * Entry ports across the exit's shared edge: the sealed-link rule.
   */
  _legalEntryTargets(from) {
    const target = transform.neighbor({ q: from.q, r: from.r }, from.edge);
    const facing = transform.opposite(from.edge);
    const legal = new Set();
    this._levelPorts().forEach((port) => {
      if (port.kind === 'entry' && port.placementId !== from.placementId && port.q === target.q && port.r === target.r && port.edge === facing) {
        legal.add(port.key);
      }
    });
    return legal;
  }

  _portHighlight(key, kind) {
    if (!this._linking) {
      return null;
    }
    if (key === this._linking.from.key) {
      return 'source';
    }
    if (kind !== 'entry') {
      return null;
    }
    return this._linking.legal.has(key) ? 'legal' : 'illegal';
  }

  _handlePortClick(port) {
    if (!this.model || this._busy || this._drag || port.button !== 0) {
      return;
    }
    const key = `${port.placementId}:${port.portId}`;
    const level = this._levelPorts().find((p) => p.key === key);
    if (!level) {
      return;
    }
    if (!this._linking) {
      if (level.kind !== 'exit') {
        this._selectPlacement(level.placementId);
        this._setStatus(`"${level.portId}" is an entry port. Start a link from an exit port.`, 'warning');
        return;
      }
      if (level.linked) {
        const link = (this.model.port_links || []).find((l) => l.from.placement_id === level.placementId && l.from.port_id === level.portId);
        this._selectLink(link?.link_id || null);
        return;
      }
      this._linking = { from: level, legal: this._legalEntryTargets(level), to: null };
      this.container.setAttribute('data-linking', 'true');
      this._emitMap();
      const count = this._linking.legal.size;
      this._setStatus(
        count
          ? `Linking from "${level.portId}" on ${level.label}: click one of ${count} highlighted entry port(s). Esc cancels.`
          : `Linking from "${level.portId}" on ${level.label}: no entry port faces it. Move a room so its entry sits at (${transform.neighbor(level, level.edge).q}, ${transform.neighbor(level, level.edge).r}) facing edge ${transform.opposite(level.edge)}. Esc cancels.`,
        count ? 'info' : 'warning',
      );
      return;
    }
    if (key === this._linking.from.key) {
      this._cancelLinking('Linking cancelled.');
      return;
    }
    if (level.kind !== 'entry') {
      this._setStatus(`"${level.portId}" is an exit port; a link ends on an entry port.`, 'error');
      return;
    }
    if (!this._linking.legal.has(key)) {
      const from = this._linking.from;
      const need = transform.neighbor({ q: from.q, r: from.r }, from.edge);
      this._setStatus(
        `"${level.portId}" on ${level.label} is at (${level.q}, ${level.r}) facing edge ${level.edge}; a link from "${from.portId}" must land at (${need.q}, ${need.r}) facing edge ${transform.opposite(from.edge)}.`,
        'error',
      );
      return;
    }
    this._linking.to = level;
    this.selectedPlacementId = null;
    this.selectedLinkId = null;
    this.selectedRegionId = null;
    this._renderSelection();
    this._setStatus('Choose the link kind, direction and default state, then create it.', 'info');
  }

  _cancelLinking(message) {
    if (!this._linking) {
      return;
    }
    this._linking = null;
    this.container.removeAttribute('data-linking');
    this._emitMap();
    this._renderInspector();
    if (message) {
      this._setStatus(message, 'info');
    }
  }

  _runLinkAction(action) {
    if (action === 'link-cancel') {
      this._cancelLinking('Linking cancelled.');
      return;
    }
    if (action === 'link-create') {
      const pending = this._linking;
      if (!pending?.to) {
        return;
      }
      const body = this._dom.inspectorBody;
      const kind = body?.querySelector('[data-link-new="kind"]')?.value || '';
      const direction = body?.querySelector('[data-link-new="direction"]')?.value || '';
      const defaultState = body?.querySelector('[data-link-new="default_state"]')?.value || '';
      if (!kind || !direction || !defaultState) {
        this._setStatus('Kind, direction and default state are all required; nothing is assumed.', 'error');
        return;
      }
      this.applyCommand('link_ports', {
        from: { placement_id: pending.from.placementId, port_id: pending.from.portId },
        to: { placement_id: pending.to.placementId, port_id: pending.to.portId },
        kind,
        direction,
        default_state: defaultState,
      }).then((result) => {
        if (result) {
          this._linking = null;
          this.container.removeAttribute('data-linking');
          const created = (result.model.port_links || []).find((l) => l.from.placement_id === pending.from.placementId && l.from.port_id === pending.from.portId);
          this._selectLink(created?.link_id || null);
          this._emitMap();
        }
      });
      return;
    }
    const link = this._selectedLink();
    if (!link) {
      return;
    }
    if (action === 'link-unlink') {
      this.applyCommand('unlink_ports', { link_id: link.link_id });
    }
  }

  _runLinkField(field, input) {
    const link = this._selectedLink();
    if (!link) {
      return;
    }
    const key = field.slice('link-'.length);
    let value;
    if (key === 'travel_cost') {
      value = Number.parseInt(input.value, 10);
      if (!Number.isInteger(value)) {
        this._setStatus('Travel cost must be a whole number.', 'error');
        input.value = String(link.travel_cost);
        return;
      }
    } else if (key === 'tags') {
      value = Array.from(new Set(String(input.value).split(',').map((t) => t.trim()).filter(Boolean)));
    } else {
      value = String(input.value);
    }
    if (JSON.stringify(value) === JSON.stringify(link[key])) {
      return;
    }
    this.applyCommand('update_port_link', { link_id: link.link_id, changes: { [key]: value } }).then((result) => {
      if (!result) {
        this._renderInspector();
      }
    });
  }

  _runRegionAction(action) {
    const region = this._selectedRegion();
    if (!region) {
      return;
    }
    if (action === 'region-remove') {
      this.applyCommand('remove_region', { region_id: region.region_id });
    }
  }

  _runRegionField(field, input) {
    const region = this._selectedRegion();
    if (!region) {
      return;
    }
    const key = field.slice('region-'.length);
    let value;
    if (key === 'placement_ids') {
      const boxes = this._dom.inspectorBody?.querySelectorAll('[data-inspector-field="region-placement_ids"]') || [];
      value = Array.from(boxes).filter((box) => box.checked).map((box) => box.value);
    } else if (key === 'ambient_hazard_level') {
      value = Number.parseInt(input.value, 10);
      if (!Number.isInteger(value)) {
        this._setStatus('Hazard level must be a whole number.', 'error');
        input.value = String(region.ambient_hazard_level);
        return;
      }
    } else {
      value = String(input.value);
      if (key === 'name' && !value.trim()) {
        this._setStatus('A region needs a name.', 'error');
        input.value = region.name;
        return;
      }
    }
    if (JSON.stringify(value) === JSON.stringify(region[key])) {
      return;
    }
    this.applyCommand('update_region', { region_id: region.region_id, changes: { [key]: value } }).then((result) => {
      if (!result) {
        this._renderInspector();
      }
    });
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

  _renderLinkList() {
    const list = this._dom.linkList;
    if (!list) {
      return;
    }
    clearElement(list);
    const links = this.model?.port_links || [];
    if (this._dom.linkEmpty) {
      this._dom.linkEmpty.hidden = !this.model || links.length > 0;
    }
    links.forEach((link) => {
      const item = makeEl('li', 'dungeon-editor__link-item');
      item.setAttribute('data-link-id', link.link_id);
      item.setAttribute('aria-selected', link.link_id === this.selectedLinkId ? 'true' : 'false');
      const label = makeEl('span', null, `${this._placementLabel(link.from.placement_id)}:${link.from.port_id} → ${this._placementLabel(link.to.placement_id)}:${link.to.port_id}`);
      const meta = makeEl('span', 'dungeon-editor__item-meta', `${link.kind} · ${link.direction} · ${link.default_state}`);
      item.append(label, meta);
      list.appendChild(item);
    });
  }

  _renderRegionList() {
    const list = this._dom.regionList;
    if (!list) {
      return;
    }
    clearElement(list);
    const regions = this.model?.regions || [];
    if (this._dom.regionEmpty) {
      this._dom.regionEmpty.hidden = !this.model || regions.length > 0;
    }
    if (this._dom.regionForm) {
      Array.from(this._dom.regionForm.elements).forEach((el) => { el.disabled = !this.model; });
    }
    regions.forEach((region) => {
      const item = makeEl('li', 'dungeon-editor__region-item');
      item.setAttribute('data-region-id', region.region_id);
      item.setAttribute('aria-selected', region.region_id === this.selectedRegionId ? 'true' : 'false');
      const label = makeEl('span', null, region.name);
      const meta = makeEl('span', 'dungeon-editor__item-meta', `${region.region_id} · ${region.placement_ids.length} room(s)`);
      item.append(label, meta);
      list.appendChild(item);
    });
  }

  _renderInspector() {
    const body = this._dom.inspectorBody;
    if (!body) {
      return;
    }
    clearElement(body);
    if (!this.model) {
      body.appendChild(makeEl('p', 'room-editor__hint', 'Load or create a dungeon to begin.'));
      return;
    }
    if (this._linking?.to) {
      this._renderPendingLinkInspector(body);
      return;
    }
    const link = this._selectedLink();
    if (link) {
      this._renderLinkInspector(body, link);
      return;
    }
    const region = this._selectedRegion();
    if (region) {
      this._renderRegionInspector(body, region);
      return;
    }
    const placement = (this.model?.placements || []).find((p) => p.placement_id === this.selectedPlacementId);
    if (!placement) {
      body.appendChild(makeEl('p', 'room-editor__hint', 'Select a placement, link or region to inspect it.'));
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

  /**
   * @param {string} attribute 'data-inspector-field' (edits fire commands on
   *   change) or 'data-link-new' (collected only when "Create link" is pressed).
   */
  _inspectorSelect(attribute, field, options, selected, placeholder = null) {
    const select = document.createElement('select');
    select.setAttribute(attribute, field);
    if (placeholder) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = placeholder;
      option.selected = true;
      select.appendChild(option);
    }
    options.forEach((value) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value.replace(/_/g, ' ');
      option.selected = value === selected;
      select.appendChild(option);
    });
    return select;
  }

  _inspectorField(labelText, control) {
    const field = makeEl('label', 'room-editor__field', `${labelText} `);
    field.appendChild(control);
    return field;
  }

  _inspectorButton(name, text, extraClass = '') {
    const btn = makeEl('button', `room-editor__button ${extraClass}`.trim(), text);
    btn.type = 'button';
    btn.setAttribute('data-inspector-action', name);
    return btn;
  }

  /**
   * Kind, direction and default state are required by the contract and have
   * no defaults; the form starts empty and the server refuses guesses.
   */
  _renderPendingLinkInspector(body) {
    const { from, to } = this._linking;
    body.appendChild(makeEl('p', 'room-editor__eyebrow', 'New link'));
    const dl = makeEl('dl', 'dungeon-editor__inspector-grid');
    [
      ['From', `${from.label} : ${from.portId} @ (${from.q}, ${from.r}) edge ${from.edge}`],
      ['To', `${to.label} : ${to.portId} @ (${to.q}, ${to.r}) edge ${to.edge}`],
    ].forEach(([term, value]) => {
      dl.appendChild(makeEl('dt', null, term));
      dl.appendChild(makeEl('dd', null, value));
    });
    body.appendChild(dl);
    body.appendChild(this._inspectorField('Kind', this._inspectorSelect('data-link-new', 'kind', LINK_KINDS, null, '- choose -')));
    body.appendChild(this._inspectorField('Direction', this._inspectorSelect('data-link-new', 'direction', LINK_DIRECTIONS, null, '- choose -')));
    body.appendChild(this._inspectorField('Default state', this._inspectorSelect('data-link-new', 'default_state', LINK_STATES, null, '- choose -')));
    const actions = makeEl('div', 'dungeon-editor__inspector-actions');
    actions.append(this._inspectorButton('link-create', 'Create link'), this._inspectorButton('link-cancel', 'Cancel'));
    body.appendChild(actions);
  }

  _renderLinkInspector(body, link) {
    body.appendChild(makeEl('p', 'room-editor__eyebrow', 'Link'));
    const dl = makeEl('dl', 'dungeon-editor__inspector-grid');
    [
      ['From', `${this._placementLabel(link.from.placement_id)} : ${link.from.port_id}`],
      ['To', `${this._placementLabel(link.to.placement_id)} : ${link.to.port_id}`],
      ['Link id', link.link_id],
    ].forEach(([term, value]) => {
      dl.appendChild(makeEl('dt', null, term));
      dl.appendChild(makeEl('dd', null, value));
    });
    body.appendChild(dl);
    body.appendChild(this._inspectorField('Kind', this._inspectorSelect('data-inspector-field', 'link-kind', LINK_KINDS, link.kind)));
    body.appendChild(this._inspectorField('Direction', this._inspectorSelect('data-inspector-field', 'link-direction', LINK_DIRECTIONS, link.direction)));
    body.appendChild(this._inspectorField('Default state', this._inspectorSelect('data-inspector-field', 'link-default_state', LINK_STATES, link.default_state)));
    const cost = document.createElement('input');
    cost.type = 'number';
    cost.min = '0';
    cost.max = '100';
    cost.step = '1';
    cost.value = String(link.travel_cost);
    cost.setAttribute('data-inspector-field', 'link-travel_cost');
    body.appendChild(this._inspectorField('Travel cost', cost));
    const description = document.createElement('textarea');
    description.rows = 2;
    description.maxLength = 2000;
    description.value = link.description;
    description.setAttribute('data-inspector-field', 'link-description');
    body.appendChild(this._inspectorField('Description', description));
    const tags = document.createElement('input');
    tags.type = 'text';
    tags.value = link.tags.join(', ');
    tags.setAttribute('data-inspector-field', 'link-tags');
    body.appendChild(this._inspectorField('Tags (comma separated)', tags));
    const actions = makeEl('div', 'dungeon-editor__inspector-actions');
    actions.appendChild(this._inspectorButton('link-unlink', 'Unlink', 'room-editor__button--danger'));
    body.appendChild(actions);
  }

  _renderRegionInspector(body, region) {
    body.appendChild(makeEl('p', 'room-editor__eyebrow', `Region ${region.region_id}`));
    const name = document.createElement('input');
    name.type = 'text';
    name.maxLength = 200;
    name.value = region.name;
    name.setAttribute('data-inspector-field', 'region-name');
    body.appendChild(this._inspectorField('Name', name));
    const description = document.createElement('textarea');
    description.rows = 2;
    description.maxLength = 4000;
    description.value = region.description;
    description.setAttribute('data-inspector-field', 'region-description');
    body.appendChild(this._inspectorField('Description', description));
    const hazard = document.createElement('input');
    hazard.type = 'number';
    hazard.min = '0';
    hazard.max = '10';
    hazard.step = '1';
    hazard.value = String(region.ambient_hazard_level);
    hazard.setAttribute('data-inspector-field', 'region-ambient_hazard_level');
    body.appendChild(this._inspectorField('Ambient hazard level', hazard));

    body.appendChild(makeEl('p', 'room-editor__hint', 'Rooms in this region'));
    const list = makeEl('ul', 'dungeon-editor__checklist');
    (this.model.placements || []).forEach((placement) => {
      const item = makeEl('li');
      const label = makeEl('label');
      const box = document.createElement('input');
      box.type = 'checkbox';
      box.value = placement.placement_id;
      box.checked = region.placement_ids.includes(placement.placement_id);
      box.setAttribute('data-inspector-field', 'region-placement_ids');
      label.append(box, makeEl('span', null, placement.label));
      item.appendChild(label);
      list.appendChild(item);
    });
    body.appendChild(list);
    const actions = makeEl('div', 'dungeon-editor__inspector-actions');
    actions.appendChild(this._inspectorButton('region-remove', 'Remove region', 'room-editor__button--danger'));
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

  // ---------------------------------------------------------------------------
  // GM assistant (dungeon_editor surface of the editor GM harness)
  // ---------------------------------------------------------------------------

  /**
   * Re-grounds the assistant on the server's view of the draft. The panel never
   * keeps its own copy of draft state: every turn is grounded from the same
   * draft the manual tools mutate, through the same DungeonEditorService.
   */
  async _refreshGmContext() {
    if (!this._gm || !this.draft?.draft_id || !this.urls.gm) {
      this._renderGmContext();
      return;
    }
    const url = `${this._draftUrl('gm')}?profile=${encodeURIComponent(this._gmProfile())}`;
    try {
      const result = await this._getJson(url);
      this._gm.context = result?.data?.context_snapshot || null;
      this._gm.manifest = this._gm.context?.tools || null;
      this._renderGmContext();
      this._renderGmToolset();
      this._setGmState(`Grounded on revision ${this._gm.context?.draft?.revision ?? '?'}.`);
    } catch (err) {
      this._gm.context = null;
      this._gm.manifest = null;
      this._renderGmContext();
      this._renderGmToolset();
      this._setGmState(`Context unavailable: ${err?.message || 'unknown error'}${err?.code ? ` (${err.code})` : ''}`, 'error');
    }
  }

  _gmProfile() {
    return 'editing';
  }

  _renderGmContext() {
    const body = this._dom.gmContext;
    if (!body) {
      return;
    }
    clearElement(body);
    const context = this._gm?.context;
    if (!context) {
      body.appendChild(makeEl('p', 'room-editor__hint', 'No grounded context loaded.'));
      return;
    }
    const list = makeEl('ul', 'room-editor__gm-context-list');
    const rows = [
      ['Dungeon', `${context.dungeon?.name || '(unnamed)'} [${context.draft?.dungeon_id || 'unbound'}]`],
      ['Revision', String(context.draft?.revision ?? '?')],
      ['Status', String(context.draft?.status || '?')],
      ['Placements', String(context.dungeon?.placement_count ?? 0)],
      ['Entrances', String((context.dungeon?.level_entrances || []).length)],
      ['Links', String(context.dungeon?.port_link_count ?? 0)],
      ['Regions', String(context.dungeon?.region_count ?? 0)],
      ['Validation', `${context.validation_summary?.profile}: ${context.validation_summary?.error_count ?? 0} errors, ${context.validation_summary?.warning_count ?? 0} warnings`],
      ['Published', context.publication?.has_published_version ? 'yes' : 'no'],
      ['Authority', context.authority_boundary?.mutation_gateway || '?'],
    ];
    rows.forEach(([label, value]) => {
      list.appendChild(makeEl('li', null, `${label}: ${value}`));
    });
    body.appendChild(list);
  }

  /**
   * Renders the server-declared toolset. The manifest is authoritative: the
   * panel never invents tool names, so assistant parity with the manual editor
   * is always exactly what the surface registry exposes.
   */
  _renderGmToolset() {
    const body = this._dom.gmTools;
    if (!body) {
      return;
    }
    clearElement(body);
    const manifest = this._gm?.manifest;
    if (!manifest) {
      body.appendChild(makeEl('p', 'room-editor__hint', 'Toolset unavailable.'));
      return;
    }
    Object.keys(manifest.families || {}).forEach((family) => {
      body.appendChild(makeEl('p', 'room-editor__gm-tool-family', family));
      const list = makeEl('ul', 'room-editor__gm-tool-list');
      (manifest.families[family] || []).forEach((tool) => {
        const item = makeEl('li');
        const button = makeEl('button', `room-editor__gm-tool${tool.mutating ? ' room-editor__gm-tool--mutating' : ''}`, tool.name);
        button.type = 'button';
        button.title = `${tool.summary}\n${tool.authority}`;
        button.setAttribute('data-gm-tool', tool.name);
        const template = {};
        (tool.arguments || []).filter((arg) => arg.required).forEach((arg) => {
          template[arg.name] = arg.type === 'integer' ? 0 : (arg.type === 'array' ? [] : (arg.type === 'boolean' ? false : ''));
        });
        button.setAttribute('data-gm-template', JSON.stringify(template));
        item.appendChild(button);
        list.appendChild(item);
      });
      body.appendChild(list);
    });
  }

  _setGmState(text, level = 'info') {
    if (this._dom.gmState) {
      this._dom.gmState.textContent = text;
      this._dom.gmState.setAttribute('data-status-level', level);
    }
  }

  _appendGmMessage(kind, text, detail) {
    const list = this._dom.gmTranscript;
    if (!list) {
      return;
    }
    const item = makeEl('li', `room-editor__gm-message room-editor__gm-message--${kind}`, text);
    if (detail !== undefined && detail !== null) {
      item.appendChild(makeEl('pre', null, typeof detail === 'string' ? detail : JSON.stringify(detail, null, 2)));
    }
    list.appendChild(item);
    list.scrollTop = list.scrollHeight;
  }

  /**
   * Classifies one composer submission.
   *
   * A message whose first token is a registered tool name is an explicit tool
   * call (`<tool_name> {json arguments}`). Anything else is a natural-language
   * intent, which the harness may only answer with a read-only tool run or an
   * explicit proposal; it can never mutate directly.
   */
  _parseGmMessage(raw) {
    const text = String(raw || '').trim();
    if (!text) {
      throw new Error('Enter a request, or a tool name to run directly.');
    }
    const match = text.match(/^([a-z][a-z0-9_]*)\s*([\s\S]*)$/);
    if (!match || !this._isKnownGmTool(match[1])) {
      return { kind: 'natural_language', utterance: text };
    }
    const rest = match[2].trim();
    let args = {};
    if (rest) {
      let parsed = null;
      try {
        parsed = JSON.parse(rest);
      } catch (_err) {
        throw new Error('Tool arguments must be a JSON object.');
      }
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new Error('Tool arguments must be a JSON object.');
      }
      args = parsed;
    }
    return { kind: 'tool_call', toolName: match[1], args };
  }

  _isKnownGmTool(name) {
    return !!this._gm?.manifest
      && Object.values(this._gm.manifest.families || {}).some((tools) => tools.some((t) => t.name === name));
  }

  async _submitGmMessage() {
    const raw = this._dom.gmInput?.value || '';
    let parsed = null;
    try {
      parsed = this._parseGmMessage(raw);
    } catch (err) {
      this._appendGmMessage('error', err.message);
      return;
    }
    if (parsed.kind === 'natural_language' && !this._gm.context?.assistant?.natural_language_available) {
      this._appendGmMessage('error', 'Natural-language requests are unavailable. Run a tool directly, e.g. "validate_dungeon".');
      return;
    }
    this._appendGmMessage('user', raw.trim());
    if (this._dom.gmInput) {
      this._dom.gmInput.value = '';
    }
    if (parsed.kind === 'natural_language') {
      await this._sendGmRequest({ type: 'natural_language', utterance: parsed.utterance }, false, 'assistant');
      return;
    }
    await this._invokeGmTool(parsed.toolName, parsed.args, !!this._dom.gmDryRun?.checked);
  }

  async _invokeGmTool(toolName, args, dryRun) {
    return this._sendGmRequest({ type: 'tool_call', tool_name: toolName, arguments: args || {} }, !!dryRun, toolName);
  }

  /**
   * Posts one request envelope and folds the response into shared editor state.
   *
   * A mutating tool goes through the same DungeonEditorService authority as
   * manual editing, so after it reports a new revision the read model is
   * re-fetched exactly as it is after a manual command.
   */
  async _sendGmRequest(intent, dryRun, label) {
    if (!this.draft?.draft_id) {
      this._appendGmMessage('error', 'Load or create a dungeon before using the assistant.');
      return null;
    }
    if (!this.urls.gm) {
      this._appendGmMessage('error', 'Editor GM harness endpoint is not configured.');
      return null;
    }
    if (this._busy) {
      this._appendGmMessage('error', 'Another request is still running.');
      return null;
    }
    const body = {
      schema_version: 'editor-gm-request-v1',
      tool_context: {
        tool_id: 'dungeon_editor',
        draft_id: this.draft.draft_id,
        validation_profile: this._gmProfile(),
      },
      intent,
      options: { dry_run: !!dryRun },
    };

    this._setBusy(true);
    this._setGmState(`Running ${label}...`);
    let envelope = null;
    try {
      const result = await this._postJson(this._draftUrl('gm'), body);
      envelope = result?.data || {};
    } catch (err) {
      this._setBusy(false);
      this._appendGmMessage('error', `${label} failed: ${err?.message || 'unknown error'}${err?.code ? ` (${err.code})` : ''}`, err?.findings || undefined);
      this._setGmState('Last request failed.', 'error');
      if (err?.status === 409) {
        await this.refresh().catch(() => {});
      }
      return null;
    }
    this._setBusy(false);

    this._gm.context = envelope.context_snapshot || this._gm.context;
    this._gm.manifest = this._gm.context?.tools || this._gm.manifest;
    this._renderGmContext();
    this._renderGmToolset();

    if (Array.isArray(envelope.command_plan) && envelope.command_plan.length) {
      this._setGmPlan(envelope.command_plan);
    }
    this._appendGmMessage(dryRun ? 'info' : 'success', `${label} → ${envelope.route_family}`, envelope.tool_result);
    this._renderGmProposal(envelope.tool_result);
    (envelope.messages || []).forEach((message) => {
      this._appendGmMessage(message.level === 'error' ? 'error' : 'info', message.text);
    });

    const applied = envelope.tool_result?.final_revision;
    if (Number.isInteger(applied) && !dryRun && applied !== this.model?.revision) {
      // Assistant-applied commands join the same undo history as manual ones.
      (envelope.tool_result.receipts || []).forEach((receipt) => {
        if (receipt.command_type === 'undo') {
          this._redoStack.push(receipt.command_id);
        } else {
          this._history.push(receipt.command_id);
          if (receipt.command_type !== 'redo') {
            this._redoStack = [];
          }
        }
      });
      this._setStatus(`Assistant applied ${label} (revision ${applied}).`, 'info');
      await this.refresh().catch((err) => this._showError(err, 'Reload after assistant apply failed'));
    } else {
      this._setGmState(`Grounded on revision ${this._gm.context?.draft?.revision ?? '?'}.`);
    }
    return envelope;
  }

  /**
   * Renders an approval affordance for a mutating tool the assistant proposed
   * but is not allowed to run. Approval re-sends the call as an explicit
   * tool_call intent, so the mutation is always author-authorised.
   */
  _renderGmProposal(result) {
    const proposal = result?.proposed_execution;
    if (!proposal || result.intent !== 'proposed_execution') {
      return;
    }
    const list = this._dom.gmTranscript;
    if (!list) {
      return;
    }
    const item = makeEl('li', 'room-editor__gm-message room-editor__gm-message--warning');
    item.appendChild(makeEl('span', null, `Proposed: ${proposal.tool_name} (${proposal.authority})`));
    item.appendChild(makeEl('pre', null, JSON.stringify(proposal.arguments || {}, null, 2)));
    const approve = makeEl('button', 'room-editor__button room-editor__button--primary', 'Approve and run');
    approve.type = 'button';
    approve.setAttribute('data-dungeon-editor-gm-approve', proposal.tool_name);
    approve.addEventListener('click', () => {
      approve.disabled = true;
      this._invokeGmTool(proposal.tool_name, proposal.arguments || {}, false);
    });
    item.appendChild(approve);
    list.appendChild(item);
    list.scrollTop = list.scrollHeight;
  }

  _setGmPlan(plan) {
    this._gm.plan = plan;
    const panel = this._dom.gmPlan;
    const list = this._dom.gmPlanList;
    if (!panel || !list) {
      return;
    }
    clearElement(list);
    if (!plan || !plan.length) {
      panel.hidden = true;
      return;
    }
    plan.forEach((step) => {
      list.appendChild(makeEl('li', null, `${step.command_type}: ${step.rationale || ''}`));
    });
    panel.hidden = false;
  }

  _gmPlanCommands() {
    return (this._gm.plan || []).map((step) => ({
      type: step.command_type,
      payload: step.payload || {},
      rationale: step.rationale || '',
    }));
  }

  /**
   * Projects the proposed plan through the harness without mutating the draft,
   * so the author approves on evidence rather than on the rationale text.
   */
  async _previewGmPlan() {
    if (!this._gm.plan?.length) {
      this._appendGmMessage('error', 'There is no proposed plan to preview.');
      return;
    }
    await this._invokeGmTool('preview_command_plan', {
      commands: this._gmPlanCommands(),
      profile: this._gmProfile(),
    }, false);
  }

  async _applyGmPlan() {
    if (!this._gm.plan?.length) {
      this._appendGmMessage('error', 'There is no proposed plan to apply.');
      return;
    }
    const commands = this._gmPlanCommands().map(({ type, payload }) => ({ type, payload }));
    const result = await this._invokeGmTool('apply_dungeon_commands', {
      expected_revision: this.model?.revision,
      commands,
    }, false);
    if (result) {
      this._setGmPlan(null);
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
