/**
 * @file panels/RoomViewPanel.js
 *
 * Renders the room scene image, room name, and NPC responder thumbnails.
 *
 * PURE UI — no game logic, no server fetching.
 * All room display data is pushed via bus events from the server.
 *
 * DOM bindings (via [data-room="key"]):
 *   scene-image  — <img> for the room scene/background art
 *   name         — room name heading
 *   responders   — container for NPC responder portrait chips
 *   empty        — shown when no room is loaded
 *
 * room:changed event shape (server-pushed):
 *   { roomId, roomName, sceneImageUrl?, responders?: [{ npc_id, name, portrait_url? }] }
 *
 * Subscribes to bus events:
 *   room:changed  — render view for the new room
 *
 * Fires no bus events (display only).
 */

export class RoomViewPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._currentRoomId = null;
    this._el = {};
  }

  init() {
    const s = (key) => this.container.querySelector(`[data-room="${key}"]`);
    this._el = {
      sceneImage: s('scene-image'),
      name:       s('name'),
      responders: s('responders'),
      empty:      s('empty'),
    };
    this._showEmpty();
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this._currentRoomId = null;
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('room:changed', (data) => this._onRoomChanged(data)),
    );
  }

  _onRoomChanged({ roomId, roomName, sceneImageUrl, responders = [] } = {}) {
    this._currentRoomId = roomId ?? null;
    const { sceneImage, name, responders: respEl, empty } = this._el;

    if (empty) empty.hidden = true;

    if (name) name.textContent = String(roomName ?? '');

    if (sceneImage) {
      if (sceneImageUrl) {
        sceneImage.src     = sceneImageUrl;
        sceneImage.hidden  = false;
      } else {
        sceneImage.src     = '';
        sceneImage.hidden  = true;
      }
    }

    if (respEl) {
      respEl.innerHTML = responders.map((r) => this._responderChipHtml(r)).join('');
    }
  }

  _responderChipHtml(r) {
    const name = _esc(r.name ?? 'NPC');
    const id   = _esc(r.npc_id ?? '');
    const img  = r.portrait_url
      ? `<img class="room-responder__portrait" src="${_esc(r.portrait_url)}" alt="${name}">`
      : `<span class="room-responder__initial">${_esc((r.name ?? '?')[0].toUpperCase())}</span>`;
    return `<div class="room-responder" data-npc-id="${id}">${img}<span class="room-responder__name">${name}</span></div>`;
  }

  _showEmpty() {
    const { empty } = this._el;
    if (empty) empty.hidden = false;
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
