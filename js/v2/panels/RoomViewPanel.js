/**
 * @file panels/RoomViewPanel.js
 *
 * Room view gallery, image cache, and responder context.
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class RoomViewPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this._roomViewCache = new Map();
    this._roomViewRetryTimer = null;
  }

  init() {
    const id = (k) => document.getElementById(k);
    const s = (k) => this.container?.querySelector(`[data-room="${k}"]`) || null;
    this._el = {
      roomViewName:            id('room-view-name')            || s('name'),
      roomViewMeta:            id('room-view-meta'),
      roomViewStatus:          id('room-view-status'),
      roomViewGallery:         id('room-view-gallery')         || s('gallery'),
      roomViewPlaceholder:     id('room-view-placeholder')     || s('empty'),
      roomViewPlaceholderText: id('room-view-placeholder-text'),
      roomViewCardTemplate:    id('room-view-card-template'),
      roomViewSceneImage:      s('scene-image'),
      roomViewResponders:      s('responders'),
    };
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (this._roomViewRetryTimer) clearTimeout(this._roomViewRetryTimer);
  }

  _subscribe() {
    this._unsubs.push(
      // room:changed fires on room transitions — update name/meta with minimal data
      this.bus.on('room:changed', (d) => {
        const room = { name: d?.roomName, ...(d?.room ?? {}) };
        this.updateRoomViewPanel(room, d?.viewState ?? {});
      }),
      // room:view-loaded fires after the view-image API completes with full entries
      this.bus.on('room:view-loaded', (d) => this.updateRoomViewPanel(d?.room, d?.viewState)),
    );
  }

  formatRoomViewMeta(room) {
    if (!room || typeof room !== 'object') {
      return 'Waiting for room context...';
    }

    return [
      room.room_type ? String(room.room_type).replace(/_/g, ' ') : '',
      room.size_category ? String(room.size_category).replace(/_/g, ' ') : '',
      room.terrain ? String(room.terrain).replace(/_/g, ' ') : '',
      room.lighting ? `lighting: ${String(room.lighting).replace(/_/g, ' ')}` : '',
    ].filter(Boolean).join(' • ') || 'Current room scene';
  }

  buildRoomViewCard(entry, room) {
    const template = this._el.roomViewCardTemplate;
    const imageSrc = entry?.image?.url || entry?.image?.data_uri || '';
    if (!template || !imageSrc) {
      return null;
    }

    const fragment = template.content?.cloneNode(true);
    if (!fragment) {
      return null;
    }

    const article = fragment.querySelector('.room-view-card');
    const eyebrow = fragment.querySelector('.room-view-card__eyebrow');
    const title = fragment.querySelector('.room-view-card__title');
    const status = fragment.querySelector('.room-view-card__status');
    const image = fragment.querySelector('.room-view-card__image');

    if (eyebrow) {
      eyebrow.textContent = entry?.message_window?.label || 'Scene snapshot';
    }
    if (title) {
      title.textContent = entry?.title || room?.name || 'Generated Scene';
    }
    if (status) {
      status.textContent = entry?.mode === 'cache' ? 'Cached' : 'Generated';
    }
    if (image) {
      image.src = imageSrc;
      image.alt = entry?.title
        ? `${entry.title} for ${room?.name || 'current room'}`
        : 'Generated room scene';
    }

    return article;
  }

  buildRoomViewCacheKey(campaignId, roomId) {
    if (!campaignId || !roomId) {
      return '';
    }
    return ['room-view', campaignId, roomId].join(':');
  }

  clearRoomViewRetry() {
    if (this.roomViewRetryTimer) {
      window.clearTimeout(this.roomViewRetryTimer);
      this.roomViewRetryTimer = null;
    }
  }

  getCachedRoomViewPayload(cacheKey) {
    if (!cacheKey) {
      return null;
    }
    const entry = this.roomViewCache.get(cacheKey);
    if (!entry) {
      return null;
    }
    const ttlMs = Number.isFinite(entry.ttlMs) ? entry.ttlMs : this.roomViewCacheTtlMs;
    if ((Date.now() - entry.storedAt) >= ttlMs) {
      this.roomViewCache.delete(cacheKey);
      return null;
    }
    return entry.payload || null;
  }

  resolveRoomViewCacheTtlMs(payload) {
    const status = String(payload?.status || '').toLowerCase();
    return status === 'pending'
      ? this.roomViewPendingCacheTtlMs
      : this.roomViewCacheTtlMs;
  }

  resolveRoomViewImageSrc(entries = []) {
    if (!Array.isArray(entries)) {
      return '';
    }
    const firstImageEntry = entries.find((entry) => entry?.entry_type === 'establishing' && Boolean(entry?.image?.url || entry?.image?.data_uri))
      || entries.find((entry) => Boolean(entry?.image?.url || entry?.image?.data_uri));
    return firstImageEntry?.image?.url || firstImageEntry?.image?.data_uri || '';
  }

  scheduleRoomViewRetry(roomId, viewKey) {
    this.clearRoomViewRetry();
    this.roomViewRetryTimer = window.setTimeout(() => {
      this.roomViewRetryTimer = null;
      if (this.lastRoomViewKey !== viewKey) {
        return;
      }
      this.loadActiveRoomView(roomId, {
        force: true,
        preserveExisting: true,
      });
    }, this.roomViewRetryDelayMs);
  }

  setCachedRoomViewPayload(cacheKey, payload) {
    if (!cacheKey) {
      return payload;
    }
    const ttlMs = this.resolveRoomViewCacheTtlMs(payload);
    this.roomViewCache.set(cacheKey, {
      storedAt: Date.now(),
      ttlMs,
      payload,
    });
    return payload;
  }

  updateRoomViewPanel(room, state = {}) {
    const {
      statusLabel = 'Idle',
      placeholderText = 'Room transition imagery will appear here.',
      entries = [],
      preserveChatBackground = false,
    } = state;

    if (this._el.roomViewName) {
      this._el.roomViewName.textContent = room?.name || 'Current room';
    }
    if (this._el.roomViewMeta) {
      this._el.roomViewMeta.textContent = this.formatRoomViewMeta(room);
    }
    if (this._el.roomViewStatus) {
      this._el.roomViewStatus.textContent = statusLabel;
    }
    if (this._el.roomViewPlaceholderText) {
      this._el.roomViewPlaceholderText.textContent = placeholderText;
    }

    if (this._el.roomViewGallery) {
      this._el.roomViewGallery.innerHTML = '';
      this._el.roomViewGallery.hidden = entries.length === 0;
      entries.forEach((entry) => {
        const card = this.buildRoomViewCard(entry, room);
        if (card) {
          this._el.roomViewGallery.appendChild(card);
        }
      });
    }
    if (this._el.roomViewPlaceholder) {
      this._el.roomViewPlaceholder.hidden = entries.length > 0;
    }

    const sceneImageSrc = this.resolveRoomViewImageSrc(entries);
    if (sceneImageSrc || !preserveChatBackground) {
      this.setChatPanelSceneBackground(sceneImageSrc, room);
    }
  }

}
