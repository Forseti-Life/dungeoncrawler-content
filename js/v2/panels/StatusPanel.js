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
    this._backendRequests = new Map();
    this._backendWaitTimer = null;
    this._backendWaitThresholdMs = 7000;
  }

  init() {
    this._ensureInitiativeStatusHost();
    // Elements matching v2 template data-status attributes
    const s = (k) => this._resolveStatusElement(k);
    // Elements matching original hexmap.js IDs (graceful degradation if absent)
    const id = (k) => (typeof document !== 'undefined' ? document.getElementById(k) : null);
    this._el = {
      unavailBanner: s('unavail-banner'),
      backendWait:    s('backend-wait'),
      zoom:          s('zoom'),
      hexInfo:       s('hex-info'),
      hexLegend:     s('hex-legend'),
      fullscreen:    s('fullscreen'),
      zoomLevel:     id('zoom-level') || s('zoom'),
    };
    this._el.unavailBanner = this._el.unavailBanner || this._ensureUnavailableBannerElement();
    this._el.backendWait = this._el.backendWait || this._ensureBackendWaitElement();
    this._dockBackendWaitIntoInitiativeStatus();
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[StatusPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._bindDom();
    this._subscribe();
    if (this._el.unavailBanner) this._el.unavailBanner.hidden = true;
    if (this._el.backendWait)    this._el.backendWait.hidden = true;
    if (this._el.hexInfo)       this._el.hexInfo.hidden = true;
    if (this._el.hexLegend)     this._el.hexLegend.hidden = true;
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (this._backendWaitTimer) {
      window.clearTimeout(this._backendWaitTimer);
      this._backendWaitTimer = null;
    }
  }

  // ---------------------------------------------------------------------------
  // DOM
  // ---------------------------------------------------------------------------

  _bindDom() {
    const { fullscreen } = this._el;
    if (fullscreen) {
      const onFullscreen = () => this.bus.emit('user:fullscreen-toggle');
      fullscreen.addEventListener('click', onFullscreen);
      this._unsubs.push(() => fullscreen.removeEventListener('click', onFullscreen));
    }
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:server-unavailable', (d) => this.showServerUnavailable(d?.message)),
      this.bus.on('game:server-available',   () => { if (this._el.unavailBanner) this._el.unavailBanner.hidden = true; }),
      this.bus.on('game:backend-request-start', (d) => this.showBackendWait(d)),
      this.bus.on('game:backend-request-end',   (d) => this.hideBackendWait(d)),
      this.bus.on('hex:hovered',             (d) => this.updateHoveredHex(d?.q ?? null, d?.r ?? null)),
      this.bus.on('hex:out',                 () => this.updateHoveredHex(null, null)),
      this.bus.on('hex:clicked',             (d) => this.updateSelectedHex(d?.q ?? 0, d?.r ?? 0)),
      this.bus.on('canvas:zoom-changed',     (d) => this.updateZoomLevel(d?.scale ?? 1)),
      this.bus.on('hex:details',             (d) => this.updateHexDetails(d)),
      this.bus.on('hex:contents',            (d) => this.updateSelectedHexContents(d?.occupants ?? [], d?.q, d?.r, d?.onChoose ?? (() => {}))),
    );
  }

  _ensureBackendWaitElement() {
    const statusHost = this._ensureInitiativeStatusHost();
    const anchor = this._el.unavailBanner || this.container?.querySelector('[data-status="unavail-banner"]') || null;
    if (!this.container && !anchor?.parentNode) {
      return null;
    }

    const element = document.createElement('div');
    element.dataset.status = 'backend-wait';
    element.className = 'backend-wait-banner';
    element.setAttribute('role', 'status');
    element.setAttribute('aria-live', 'polite');
    element.hidden = true;
    element.innerHTML = '<span class="backend-wait-banner__spinner" aria-hidden="true"></span><span data-backend-wait-label>Waiting for backend response...</span>';
    if (statusHost) {
      statusHost.appendChild(element);
    } else if (anchor?.parentNode) {
      anchor.parentNode.insertBefore(element, anchor.nextSibling);
    } else {
      this.container.prepend(element);
    }
    return element;
  }

  _ensureUnavailableBannerElement() {
    const statusHost = this._ensureInitiativeStatusHost();
    const existing = statusHost?.querySelector?.('[data-status="unavail-banner"]')
      || this.container?.querySelector?.('[data-status="unavail-banner"]')
      || null;
    if (existing) {
      return existing;
    }
    if (!(statusHost instanceof HTMLElement)) {
      return null;
    }

    const element = document.createElement('div');
    element.dataset.status = 'unavail-banner';
    element.className = 'server-unavail-banner';
    element.hidden = true;
    const text = document.createElement('span');
    text.textContent = 'Server unavailable — reconnecting…';
    element.appendChild(text);
    statusHost.prepend(element);
    return element;
  }

  _resolveStatusElement(key = '') {
    const statusKey = String(key || '').trim();
    if (!statusKey) {
      return null;
    }
    const preferred = this.container?.querySelector?.(`.map-initiative-status [data-status="${statusKey}"]`) || null;
    if (preferred) {
      return preferred;
    }
    return this.container?.querySelector?.(`[data-status="${statusKey}"]`) || null;
  }

  _dockBackendWaitIntoInitiativeStatus() {
    const element = this._el.backendWait;
    const statusHost = this._ensureInitiativeStatusHost();
    if (!(element instanceof HTMLElement) || !(statusHost instanceof HTMLElement)) {
      return;
    }
    if (statusHost.contains(element)) {
      return;
    }
    statusHost.appendChild(element);
  }

  _resolveMapInitiativeTracker() {
    return this.container?.querySelector?.('#map-initiative-tracker')
      || document.getElementById('map-initiative-tracker')
      || this.container?.querySelector?.('#initiative-tracker')
      || document.getElementById('initiative-tracker')
      || this.container?.querySelector?.('.initiative-tracker')
      || document.querySelector('.initiative-tracker')
      || null;
  }

  _ensureInitiativeStatusHost() {
    const existing = this.container?.querySelector?.('.map-initiative-status')
      || document.querySelector('.map-initiative-status')
      || null;
    if (existing) {
      return existing;
    }

    const tracker = this._resolveMapInitiativeTracker();
    if (!(tracker instanceof HTMLElement)) {
      return null;
    }

    const host = document.createElement('div');
    host.className = 'map-initiative-status';
    const list = tracker.querySelector('.initiative-list');
    if (list?.parentNode === tracker) {
      tracker.insertBefore(host, list);
    } else {
      tracker.appendChild(host);
    }
    return host;
  }

  showBackendWait(data = {}) {
    const requestId = String(data?.requestId || '').trim();
    if (!requestId) {
      return;
    }

    this._backendRequests.set(requestId, {
      label: String(data?.label || 'Waiting for backend response...').trim() || 'Waiting for backend response...',
      startedAt: Date.now(),
    });
    this._renderBackendWait();
  }

  hideBackendWait(data = {}) {
    const requestId = String(data?.requestId || '').trim();
    if (!requestId) {
      return;
    }

    this._backendRequests.delete(requestId);
    this._renderBackendWait();
  }

  _renderBackendWait() {
    const element = this._el.backendWait;
    if (!element) {
      return;
    }
    this._dockBackendWaitIntoInitiativeStatus();
    element.style.position = 'static';
    element.style.top = 'auto';
    element.style.left = 'auto';
    element.style.transform = 'none';
    element.style.maxWidth = '100%';
    element.style.width = '100%';
    element.style.zIndex = 'auto';

    if (this._backendWaitTimer) {
      window.clearTimeout(this._backendWaitTimer);
      this._backendWaitTimer = null;
    }

    const active = Array.from(this._backendRequests.values());
    if (!active.length) {
      element.hidden = true;
      element.classList.remove('backend-wait-banner--slow');
      return;
    }

    const oldest = active.reduce((carry, item) => (
      !carry || item.startedAt < carry.startedAt ? item : carry
    ), null);
    const elapsed = Date.now() - oldest.startedAt;
    const isSlow = elapsed >= this._backendWaitThresholdMs;
    const label = element.querySelector('[data-backend-wait-label]') || element;
    label.textContent = isSlow
      ? `${oldest.label} Still waiting; the backend may be busy.`
      : oldest.label;
    element.hidden = false;
    element.classList.toggle('backend-wait-banner--slow', isSlow);

    if (!isSlow) {
      this._backendWaitTimer = window.setTimeout(() => {
        this._backendWaitTimer = null;
        this._renderBackendWait();
      }, Math.max(0, this._backendWaitThresholdMs - elapsed));
    }
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
    this._renderHexInfoDetails(details);
  }

  updateHoveredHex(q, r) {
    if (this._el.hoveredHex) {
      this._el.hoveredHex.textContent = q !== null ? `(${q}, ${r})` : 'None';
    }
    if (q === null || r === null) {
      this._syncHexInfoElement(null);
    }
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
  _syncHexInfoElement(content = null) {
    const { hexInfo } = this._el;
    if (!hexInfo) return;
    if (!content) {
      hexInfo.hidden = true;
      hexInfo.textContent = '';
    } else {
      hexInfo.hidden = false;
      hexInfo.textContent = content;
    }
  }

  _renderHexInfoDetails(details) {
    if (!details || typeof details !== 'object') {
      this._syncHexInfoElement(null);
      return;
    }

    const coords = Number.isFinite(Number(details.q)) && Number.isFinite(Number(details.r))
      ? `Hex (${details.q}, ${details.r})`
      : 'Hex';
    const roomLabel = details.roomName || details.roomId || 'Unknown room';
    const roomId = details.roomId || 'unknown_room';
    const entryLabel = details.isEntry ? 'Entry' : 'Not entry';
    const visibleLabel = details.isVisible ? 'Visible' : 'Not visible';
    const discoveredLabel = details.isDiscovered ? 'Discovered' : 'Undiscovered';
    const objectCount = Number.isFinite(Number(details.objectCount)) ? Number(details.objectCount) : 0;
    const elevationLabel = details.elevationFt === null || details.elevationFt === undefined ? 'NA' : `${details.elevationFt} ft`;
    const objectsLabel = Array.isArray(details.objects)
      ? (details.objects.length ? details.objects.join(', ') : 'None')
      : (details.objects || 'None');
    const entitiesLabel = Array.isArray(details.entities)
      ? (details.entities.length ? details.entities.join(', ') : 'None')
      : (details.entities || 'None');
    const content = [
      `${coords} • ${entryLabel} • ${visibleLabel} • ${discoveredLabel}`,
      `Room: ${roomLabel}`,
      `Room ID: ${roomId}`,
      `Terrain: ${details.terrain || 'unknown'} • Lighting: ${details.lighting || 'unknown'}`,
      `Elevation: ${elevationLabel} • Passability: ${details.passability || 'unknown'}`,
      `Objects (${objectCount}): ${objectsLabel}`,
      `Entities: ${entitiesLabel}`,
      `Connection: ${details.connection || 'none'}`,
    ].join('\n');

    this._syncHexInfoElement(content);
  }
}
