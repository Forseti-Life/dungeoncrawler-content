/**
 * @file panels/StatusPanel.js
 *
 * HUD overlays and system status indicators.
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class StatusPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this._lastServerMsgAt = 0;
    this._serverMsgCooldown = 3000;
  }

  init() {
    // Elements matching v2 template data-status attributes
    const s = (k) => this.container?.querySelector(`[data-status="${k}"]`) || null;
    // Elements matching original hexmap.js IDs (graceful degradation if absent)
    const id = (k) => document.getElementById(k);
    this._el = {
      unavailBanner: s('unavail-banner'),
      zoom:          s('zoom'),
      hexInfo:       s('hex-info'),
      fullscreen:    s('fullscreen'),
      zoomLevel:     id('zoom-level') || s('zoom'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[StatusPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._bindDom();
    this._subscribe();
    if (this._el.unavailBanner) this._el.unavailBanner.hidden = true;
    if (this._el.hexInfo)       this._el.hexInfo.hidden = true;
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // DOM
  // ---------------------------------------------------------------------------

  _bindDom() {
    const { fullscreen } = this._el;
    if (fullscreen) {
      fullscreen.addEventListener('click', () => this.bus.emit('user:fullscreen-toggle'));
    }
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:server-unavailable', (d) => this.showServerUnavailable(d?.message)),
      this.bus.on('game:server-available',   () => { if (this._el.unavailBanner) this._el.unavailBanner.hidden = true; }),
      this.bus.on('hex:hovered',             (d) => this.updateHoveredHex(d?.q ?? null, d?.r ?? null)),
      this.bus.on('hex:out',                 () => this.updateHoveredHex(null, null)),
      this.bus.on('hex:clicked',             (d) => this.updateSelectedHex(d?.q ?? 0, d?.r ?? 0)),
      this.bus.on('canvas:zoom-changed',     (d) => this.updateZoomLevel(d?.scale ?? 1)),
      this.bus.on('hex:details',             (d) => this.updateHexDetails(d)),
      this.bus.on('hex:contents',            (d) => this.updateSelectedHexContents(d?.occupants ?? [], d?.q, d?.r, d?.onChoose ?? (() => {}))),
    );
  }

  showServerUnavailable(message = 'Unable to connect to server. Please try again.') {
    const now = Date.now();
    if ((now - this._lastServerMsgAt) < this._serverMsgCooldown) {
      return;
    }

    this._lastServerMsgAt = now;

    if (this._el.unavailBanner) {
      this._el.unavailBanner.hidden = false;
      const span = this._el.unavailBanner.querySelector('span') || this._el.unavailBanner;
      span.textContent = message;
    }

    this.bus.emit('chat:system-message', { text: message, kind: 'system' });
  }

  updateHexDetails(details) {
    const fallback = {
      room: 'None',
      terrain: 'Unknown',
      elevation: '-',
      lighting: 'Unknown',
      passability: 'Unknown',
      objects: 'None',
      entities: 'None',
      connection: 'None'
    };

    const payload = details ? {
      room: details.roomName || fallback.room,
      terrain: details.terrain || fallback.terrain,
      elevation: Number.isFinite(details.elevationFt) ? `${details.elevationFt} ft` : fallback.elevation,
      lighting: details.lighting || fallback.lighting,
      passability: details.passability || fallback.passability,
      objects: Array.isArray(details.objects) && details.objects.length ? details.objects.join(', ') : fallback.objects,
      entities: Array.isArray(details.entities) && details.entities.length ? details.entities.join(', ') : fallback.entities,
      connection: details.connection || fallback.connection
    } : fallback;

    const map = {
      hexDetailRoom: payload.room,
      hexDetailTerrain: payload.terrain,
      hexDetailElevation: payload.elevation,
      hexDetailLighting: payload.lighting,
      hexDetailPassability: payload.passability,
      hexDetailObjects: payload.objects,
      hexDetailEntities: payload.entities,
      hexDetailConnection: payload.connection
    };

    Object.entries(map).forEach(([key, value]) => {
      if (this._el[key]) {
        this._el[key].textContent = value;
      }
    });
  }

  updateHoveredHex(q, r) {
    if (this._el.hoveredHex) {
      this._el.hoveredHex.textContent = q !== null ? `(${q}, ${r})` : 'None';
    }
    this._syncHexInfoElement(q, r);
  }

  updateHoveredObject(label) {
    if (this._el.hoveredObject) {
      this._el.hoveredObject.textContent = label || 'None';
    }
  }

  updateSelectedHex(q, r) {
    if (this._el.selectedHex) {
      this._el.selectedHex.textContent = `(${q}, ${r})`;
    }
  }

  updateSelectedHexContents(occupants, q, r, onChoose) {
    const summary = this._el.selectedHexContentsSummary;
    const empty = this._el.selectedHexContentsEmpty;
    const list = this._el.selectedHexContentsList;
    if (!summary || !empty || !list) {
      return;
    }

    const hasCoords = Number.isFinite(q) && Number.isFinite(r);
    summary.textContent = hasCoords
      ? `Hex (${q}, ${r}) contains ${occupants.length} entr${occupants.length === 1 ? 'y' : 'ies'}.`
      : 'Click a hex to inspect everything on it.';

    list.innerHTML = '';

    if (!occupants.length) {
      empty.style.display = '';
      return;
    }

    empty.style.display = 'none';

    occupants.forEach((occupant) => {
      const row = document.createElement('div');
      row.className = 'hex-contents-item';
      if (occupant.isSelected) {
        row.classList.add('is-selected');
      }

      const meta = document.createElement('div');
      meta.className = 'hex-contents-item__meta';

      const name = document.createElement('div');
      name.className = 'hex-contents-item__name';
      name.textContent = occupant.name;

      const detail = document.createElement('div');
      detail.className = 'hex-contents-item__detail';
      detail.textContent = `${occupant.typeLabel}${occupant.teamLabel ? ` • ${occupant.teamLabel}` : ''}`;

      meta.appendChild(name);
      meta.appendChild(detail);

      const actions = document.createElement('div');
      actions.className = 'hex-contents-item__actions';

      const inspectBtn = document.createElement('button');
      inspectBtn.type = 'button';
      inspectBtn.className = 'hex-contents-item__button hex-contents-item__button--secondary';
      inspectBtn.textContent = 'Inspect';
      inspectBtn.addEventListener('click', () => onChoose(occupant.entityId, 'inspect'));
      actions.appendChild(inspectBtn);

      if (occupant.canSelect) {
        const selectBtn = document.createElement('button');
        selectBtn.type = 'button';
        selectBtn.className = 'hex-contents-item__button';
        selectBtn.textContent = occupant.isSelected ? 'Selected' : 'Select';
        selectBtn.addEventListener('click', () => onChoose(occupant.entityId, 'select'));
        actions.appendChild(selectBtn);
      }

      row.appendChild(meta);
      row.appendChild(actions);
      list.appendChild(row);
    });
  }

  updateZoomLevel(scale) {
    if (this._el.zoomLevel) {
      const zoomPercent = Math.round(scale * 100);
      this._el.zoomLevel.textContent = `${zoomPercent}%`;
    }
  }

  // Emit hex-info in simple mode when hex-info element exists but not hoveredHex
  _syncHexInfoElement(q, r) {
    const { hexInfo } = this._el;
    if (!hexInfo) return;
    if (q === null) {
      hexInfo.hidden = true;
      hexInfo.textContent = '';
    } else {
      hexInfo.hidden = false;
      hexInfo.textContent = `(${q}, ${r})`;
    }
  }
}
