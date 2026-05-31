/**
 * @file panels/StatusPanel.js
 *
 * HUD overlays and system status indicators.
 *
 * PURE UI — no game logic. All state is server or canvas pushed.
 *
 * Renders:
 *   - Server unavailability banner
 *   - Zoom level indicator
 *   - Hovered hex info (coordinates + terrain type)
 *   - Fullscreen toggle button
 *
 * DOM bindings (via [data-status="key"]):
 *   unavail-banner  — server unavailability notice (hidden by default)
 *   zoom            — current zoom/scale display
 *   hex-info        — hovered hex coordinate + terrain display
 *   fullscreen      — fullscreen toggle button
 *
 * Subscribes to bus events:
 *   game:server-unavailable  — show unavailability banner
 *   game:server-available    — hide unavailability banner
 *   hex:hovered              — { q, r, terrain? }  update hex info display
 *   hex:out                  — clear hex info display
 *   canvas:zoom-changed      — { scale }  update zoom indicator
 *
 * Fires bus events:
 *   user:fullscreen-toggle  — user clicked fullscreen button
 */

export class StatusPanel {
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
    const s = (key) => this.container.querySelector(`[data-status="${key}"]`);
    this._el = {
      unavailBanner: s('unavail-banner'),
      zoom:          s('zoom'),
      hexInfo:       s('hex-info'),
      fullscreen:    s('fullscreen'),
    };
    this._bindEvents();
    this._subscribe();
    // Initialise banner hidden
    if (this._el.unavailBanner) this._el.unavailBanner.hidden = true;
    if (this._el.hexInfo)       this._el.hexInfo.hidden = true;
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // DOM events
  // ---------------------------------------------------------------------------

  _bindEvents() {
    const { fullscreen } = this._el;
    if (fullscreen) {
      fullscreen.addEventListener('click', () => {
        this.bus.emit('user:fullscreen-toggle');
      });
    }
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:server-unavailable', () => this._onServerUnavailable()),
      this.bus.on('game:server-available',   () => this._onServerAvailable()),
      this.bus.on('hex:hovered',             (d) => this._onHexHovered(d)),
      this.bus.on('hex:out',                 () => this._onHexOut()),
      this.bus.on('canvas:zoom-changed',     (d) => this._onZoomChanged(d)),
    );
  }

  _onServerUnavailable() {
    const { unavailBanner } = this._el;
    if (unavailBanner) unavailBanner.hidden = false;
  }

  _onServerAvailable() {
    const { unavailBanner } = this._el;
    if (unavailBanner) unavailBanner.hidden = true;
  }

  _onHexHovered({ q, r, terrain } = {}) {
    const { hexInfo } = this._el;
    if (!hexInfo) return;
    hexInfo.hidden  = false;
    hexInfo.textContent = terrain
      ? `(${q}, ${r}) — ${terrain}`
      : `(${q}, ${r})`;
  }

  _onHexOut() {
    const { hexInfo } = this._el;
    if (!hexInfo) return;
    hexInfo.hidden = true;
    hexInfo.textContent = '';
  }

  _onZoomChanged({ scale } = {}) {
    const { zoom } = this._el;
    if (!zoom) return;
    zoom.textContent = `${Math.round((scale ?? 1) * 100)}%`;
  }
}

// ---------------------------------------------------------------------------
// (No innerHTML usage in this panel — textContent only, no _esc needed)
// ---------------------------------------------------------------------------
