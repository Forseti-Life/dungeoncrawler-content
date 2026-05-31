/**
 * @file panels/PartyRailPanel.js
 *
 * Renders the party member quick-select rail.
 *
 * Shows portrait thumbnails for all PC occupants in the current room.
 * Clicking a member tile fires entity:selected on the bus.
 * The currently-selected entity's tile is highlighted.
 *
 * PURE UI — no game logic.
 *
 * DOM bindings (via [data-party="key"]):
 *   rail   — tile container
 *   empty  — shown when no party members present
 *
 * Occupant shape: { occupant_id, label, occupant_type, presentation: { portrait_url? } }
 *
 * Subscribes to bus events:
 *   room:occupants-changed  — rebuild party rail (PCs only)
 *   entity:selected         — highlight selected member tile
 *
 * Fires bus events:
 *   entity:selected  — { entityId } when a party tile is clicked
 */

export class PartyRailPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._members = [];
    this._selectedId = null;
    this._el = {};
  }

  init() {
    const s = (key) => this.container.querySelector(`[data-party="${key}"]`);
    this._el = { rail: s('rail'), empty: s('empty') };
    this._bindEvents();
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this._members = [];
  }

  // ---------------------------------------------------------------------------
  // DOM events
  // ---------------------------------------------------------------------------

  _bindEvents() {
    const { rail } = this._el;
    if (!rail) return;
    rail.addEventListener('click', (e) => {
      const tile = e.target?.closest?.('[data-entity-id]');
      if (!tile) return;
      const entityId = tile.dataset.entityId;
      this._selectedId = entityId;
      this._updateSelection();
      this.bus.emit('entity:selected', { entityId });
    });
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('room:occupants-changed', (data) => this._onOccupantsChanged(data)),
      this.bus.on('entity:selected',        (data) => this._onEntitySelected(data)),
    );
  }

  _onOccupantsChanged({ occupants = [] } = {}) {
    this._members = occupants.filter(
      (o) => o.is_party === true || o.occupant_type === 'player_character' || o.occupant_type === 'pc',
    );
    this._renderRail();
  }

  _onEntitySelected({ entityId } = {}) {
    this._selectedId = entityId ?? null;
    this._updateSelection();
  }

  // ---------------------------------------------------------------------------
  // Rendering
  // ---------------------------------------------------------------------------

  _renderRail() {
    const { rail, empty } = this._el;
    if (empty) empty.hidden = this._members.length > 0;
    if (!rail) return;
    rail.innerHTML = this._members.map((m) => this._tileHtml(m)).join('');
  }

  _tileHtml(member) {
    const id      = _esc(member.occupant_id);
    const name    = _esc(member.label ?? '');
    const initial = _esc((member.label ?? '?')[0].toUpperCase());
    const sel     = member.occupant_id === this._selectedId ? ' party-tile--selected' : '';
    const portraitUrl = member.presentation?.portrait_url;
    const img = portraitUrl
      ? `<img class="party-tile__portrait" src="${_esc(portraitUrl)}" alt="${name}">`
      : `<span class="party-tile__initial">${initial}</span>`;
    return `<div class="party-tile${sel}" data-entity-id="${id}" title="${name}">${img}</div>`;
  }

  _updateSelection() {
    const { rail } = this._el;
    if (!rail) return;
    rail.querySelectorAll('[data-entity-id]').forEach((tile) => {
      tile.classList.toggle('party-tile--selected', tile.dataset.entityId === this._selectedId);
    });
  }
}

// ---------------------------------------------------------------------------
// Utility
// ---------------------------------------------------------------------------

function _esc(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
