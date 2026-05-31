/**
 * @file panels/PortraitPanel.js
 *
 * Renders room occupant portraits (PCs and NPCs).
 *
 * Data source: canonical occupant API (MapVisualStateProjector output).
 * Occupant shape expected in room:occupants-changed payload:
 *   { occupant_id, content_id, label, occupant_type, presentation: { portrait_url, role } }
 *
 * PC entries sort before NPCs; each group sorted alphabetically.
 *
 * Subscribes to bus events:
 *   room:occupants-changed  — { occupants: Array } re-render portrait cards
 *
 * Fires no bus events (display only).
 *
 * DOM selectors (all optional, graceful degradation):
 *   [data-portrait="room-name"]   Room name label
 *   [data-portrait="count"]       "N occupants" chip
 *   [data-portrait="grid"]        Portrait card container
 *   [data-portrait="placeholder"] Shown when grid is empty
 *
 * @see MerchantPanel — sibling panel, same data source
 */

export class PortraitPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
  }

  init() {
    this._bindElements();
    this._unsubs.push(
      this.bus.on('room:occupants-changed', ({ occupants, roomName } = {}) => {
        this._render(Array.isArray(occupants) ? occupants : [], roomName ?? '');
      }),
    );
    this._render([], '');
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Private
  // ---------------------------------------------------------------------------

  _bindElements() {
    const s = (attr) => this.container?.querySelector(`[data-portrait="${attr}"]`) ?? null;
    this._el = {
      roomName:    s('room-name'),
      count:       s('count'),
      grid:        s('grid'),
      placeholder: s('placeholder'),
    };
  }

  /**
   * Re-render portrait grid for the given occupant list.
   * @param {Array} occupants
   * @param {string} roomName
   * @private
   */
  _render(occupants, roomName) {
    const entries = this._buildEntries(occupants);

    if (this._el.roomName) this._el.roomName.textContent = roomName || 'Current room';
    if (this._el.count)    this._el.count.textContent    = `${entries.length} occupant${entries.length !== 1 ? 's' : ''}`;

    if (this._el.grid) {
      this._el.grid.innerHTML = entries.map((e) => this._cardHtml(e)).join('');
      this._el.grid.hidden = entries.length === 0;
    }
    if (this._el.placeholder) {
      this._el.placeholder.hidden = entries.length > 0;
    }
  }

  /**
   * Map raw occupants to normalised entry objects; sort PCs before NPCs, then alpha.
   * @param {Array} occupants
   * @returns {Array<{entityId, name, kind, portraitUrl, summary}>}
   * @private
   */
  _buildEntries(occupants) {
    const seen = new Set();
    const entries = [];

    occupants.forEach((occ) => {
      const entityId = String(occ?.occupant_id ?? '').trim();
      if (!entityId || seen.has(entityId)) return;
      seen.add(entityId);

      const rawType = String(occ?.occupant_type ?? '').toLowerCase();
      entries.push({
        entityId,
        name:       String(occ?.label ?? occ?.content_id ?? 'Unknown').trim(),
        kind:       rawType === 'npc' ? 'NPC' : 'PC',
        portraitUrl: occ?.presentation?.portrait_url ?? null,
        summary:    String(occ?.presentation?.role ?? '').trim(),
      });
    });

    return entries.sort((a, b) => {
      if (a.kind !== b.kind) return a.kind === 'PC' ? -1 : 1;
      return a.name.localeCompare(b.name);
    });
  }

  /**
   * @param {{entityId, name, kind, portraitUrl, summary}} entry
   * @returns {string} HTML
   * @private
   */
  _cardHtml({ name, kind, portraitUrl, summary }) {
    const initial = (name.trim()[0] ?? '?').toUpperCase();
    const imageHtml = portraitUrl
      ? `<img class="npc-portrait-card__image" src="${this._esc(portraitUrl)}" alt="${this._esc(name)} portrait" loading="lazy">`
      : `<div class="npc-portrait-card__placeholder" aria-hidden="true">${this._esc(initial)}</div>`;

    return `<article class="npc-portrait-card">
      <div class="npc-portrait-card__frame">${imageHtml}</div>
      <h4 class="npc-portrait-card__name">${this._esc(name)}</h4>
      <p class="npc-portrait-card__meta">${this._esc(kind)}</p>
      ${summary ? `<p class="npc-portrait-card__summary">${this._esc(summary)}</p>` : ''}
    </article>`;
  }

  /** @private */
  _esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
}
