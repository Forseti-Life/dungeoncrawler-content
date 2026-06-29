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
    this.roomViewCache = new Map();
    this.roomViewRetryTimer = null;
    this.roomViewCacheTtlMs = 300000;        // 5 min default TTL
    this.roomViewPendingCacheTtlMs = 30000;  // 30 sec for pending entries
    this.roomViewRetryDelayMs = 5000;        // 5 sec retry delay
    this.lastRoomViewKey = null;
    this.dungeonData = null;
    this.stateManager = null;
  }

  init(dungeonData = {}, stateManager = {}) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => document.getElementById(k);
    const s = (k) => this.container?.querySelector(`[data-room="${k}"]`) || null;
    this._el = {
      roomViewName:            id('room-view-name')            || s('name'),
      roomViewMeta:            id('room-view-meta'),
      roomViewDescription:     id('room-view-description')     || s('description'),
      roomViewStatus:          id('room-view-status'),
      roomViewGallery:         id('room-view-gallery')         || s('gallery'),
      roomViewPlaceholder:     id('room-view-placeholder')     || s('empty'),
      roomViewPlaceholderText: id('room-view-placeholder-text'),
      roomViewCardTemplate:    id('room-view-card-template'),
      roomViewSceneImage:      s('scene-image'),
      roomViewResponders:      s('responders'),
      npcPortraitsName:            id('npc-portraits-name'),
      npcPortraitsMeta:            id('npc-portraits-meta'),
      npcPortraitsStatus:          id('npc-portraits-status'),
      npcPortraitsGrid:            id('npc-portraits-grid'),
      npcPortraitsPlaceholder:     id('npc-portraits-placeholder'),
      npcPortraitsPlaceholderText: id('npc-portraits-placeholder-text'),
      chatShell:               document.getElementById('hexmap-chat'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[RoomViewPanel] init', { container: !!this.container, chatShell: !!this._el.chatShell, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (this.roomViewRetryTimer) clearTimeout(this.roomViewRetryTimer);
  }

  _subscribe() {
    this._unsubs.push(
      // room:changed fires on room transitions — update name/meta with minimal data
      this.bus.on('room:changed', (d) => {
        const room = { name: d?.roomName, id: d?.roomId, ...(d?.room ?? {}) };
        this.updateRoomViewPanel(room, d?.viewState ?? {}, 'room:changed');
      }),
      // room:view-loaded fires after the view-image API completes with full entries
      this.bus.on('room:view-loaded', (d) => this.updateRoomViewPanel(d?.room, d?.viewState, 'room:view-loaded')),
    );
  }

  formatRoomViewMeta(room) {
    if (!room || typeof room !== 'object') {
      return 'Waiting for room context...';
    }

    return [
      room.room_type && String(room.room_type) !== 'unknown' ? String(room.room_type).replace(/_/g, ' ') : '',
      room.size_category ? String(room.size_category).replace(/_/g, ' ') : '',
      this.formatRoomViewField(room.terrain),
      this.formatRoomViewField(room.lighting) ? `lighting: ${this.formatRoomViewField(room.lighting)}` : '',
    ].filter(Boolean).join(' • ') || 'Current room scene';
  }

  formatRoomViewField(value) {
    if (!value) return '';
    if (typeof value === 'object') {
      return String(value.type || value.level || value.name || '').replace(/_/g, ' ');
    }
    return String(value).replace(/_/g, ' ');
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

  buildRoomPortraitEntries(roomId = null) {
    const hexmap = this.stateManager?.hexmap || null;
    const resolvedRoomId = roomId || hexmap?.resolveActiveRoomId?.() || null;
    if (!resolvedRoomId) {
      return [];
    }

    const canonicalOccupants = typeof hexmap?.getVisualOccupants === 'function'
      ? hexmap.getVisualOccupants()
      : [];
    const roomOccupants = canonicalOccupants.filter((occupant) => {
      if (String(occupant?.room_id || '') !== resolvedRoomId) {
        return false;
      }
      const rawType = String(occupant?.occupant_type || '').trim().toLowerCase();
      if (!['npc', 'player_character', 'player'].includes(rawType)) {
        return false;
      }
      return hexmap?.isVisualOccupantVisible?.(occupant) !== false;
    });

    if (roomOccupants.length === 0) {
      return [];
    }

    const entries = [];
    const seen = new Set();
    roomOccupants.forEach((occupant) => {
      const entityId = String(occupant?.occupant_id || '').trim();
      if (!entityId || seen.has(entityId)) {
        return;
      }
      seen.add(entityId);

      const contentId = String(occupant?.content_id || '').trim();
      const objectDefinition = contentId ? hexmap?.getObjectDefinition?.(contentId) : null;
      const portraitSpriteId = contentId ? `portrait_${contentId}` : null;
      const fallbackPortraitSpriteId = typeof objectDefinition?.visual?.sprite_id === 'string'
        && objectDefinition.visual.sprite_id.startsWith('portrait_')
        ? objectDefinition.visual.sprite_id
        : null;
      const portraitUrl = occupant?.presentation?.portrait_url
        || (portraitSpriteId ? hexmap?.spriteService?.getCachedUrl?.(portraitSpriteId) : null)
        || (fallbackPortraitSpriteId ? hexmap?.spriteService?.getCachedUrl?.(fallbackPortraitSpriteId) : null)
        || null;
      const name = String(occupant?.label || objectDefinition?.label || contentId || 'Unknown').trim();
      const rawType = String(occupant?.occupant_type || '').trim().toLowerCase();
      const kind = rawType === 'npc' ? 'NPC' : 'PC';
      const summary = String(occupant?.presentation?.role || objectDefinition?.description || '').trim();
      const characterId = Number(occupant?.character_id || occupant?.state?.character_id || 0) || 0;

      entries.push({
        entityId,
        name,
        kind,
        portraitUrl,
        summary,
        characterId,
      });
    });

    entries.sort((a, b) => {
      if (a.kind !== b.kind) {
        return a.kind === 'PC' ? -1 : 1;
      }
      return a.name.localeCompare(b.name);
    });
    return entries;
  }

  buildRoomPortraitCard(entry = {}) {
    const card = document.createElement('article');
    card.className = 'npc-portrait-card';
    card.tabIndex = 0;
    card.setAttribute('role', 'button');
    card.dataset.entityId = String(entry.entityId || '').trim();
    card.setAttribute('aria-label', `${entry.name || 'Room occupant'}: open character sheet`);
    card.addEventListener('click', () => this.focusRoomPortraitActor(entry));
    card.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      event.preventDefault();
      this.focusRoomPortraitActor(entry);
    });

    const frame = document.createElement('div');
    frame.className = 'npc-portrait-card__frame';
    if (entry.portraitUrl) {
      const image = document.createElement('img');
      image.className = 'npc-portrait-card__image';
      image.src = entry.portraitUrl;
      image.alt = `${entry.name || 'Room occupant'} portrait`;
      frame.appendChild(image);
    } else {
      const placeholder = document.createElement('div');
      placeholder.className = 'npc-portrait-card__placeholder';
      placeholder.textContent = String(entry.name || '?').trim().charAt(0).toUpperCase() || '?';
      frame.appendChild(placeholder);
    }

    const name = document.createElement('h4');
    name.className = 'npc-portrait-card__name';
    name.textContent = entry.name || 'Unknown';

    const meta = document.createElement('p');
    meta.className = 'npc-portrait-card__meta';
    meta.textContent = entry.kind || 'Room occupant';

    const summary = document.createElement('p');
    summary.className = 'npc-portrait-card__summary';
    summary.textContent = entry.summary || 'No additional summary available.';

    card.append(frame, name, meta, summary);
    return card;
  }

  resolvePortraitEntityByRef(actorRef = '') {
    const normalizedRef = String(actorRef || '').trim();
    if (!normalizedRef) {
      return null;
    }

    const hexmap = this.stateManager?.hexmap || null;
    const direct = hexmap?.entityManager?.getEntity?.(normalizedRef) || null;
    if (direct) {
      return direct;
    }

    const entities = hexmap?.entityManager?.getEntitiesWith?.('PositionComponent') || [];
    return entities.find((entity) => String(
      entity?.dcEntityRef
      || entity?.dcEntityInstanceId
      || entity?.instanceId
      || entity?.id
      || ''
    ).trim() === normalizedRef) || null;
  }

  focusRoomPortraitActor(entry = {}) {
    const actorRef = String(entry?.entityId || '').trim();
    if (!actorRef) {
      return;
    }

    const hexmap = this.stateManager?.hexmap || null;
    const entity = this.resolvePortraitEntityByRef(actorRef);
    if (entity && typeof hexmap?.selectEntity === 'function') {
      hexmap.selectEntity(entity);
    }

    const resolvedCharacterId = Number(
      entry?.characterId
      || entity?.dcCharacterId
      || entity?.dcStatePayload?.metadata?.character_id
      || entity?.dcStatePayload?.character_id
      || entity?.dcStatePayload?.state?.character_id
      || 0
    ) || 0;
    if (resolvedCharacterId > 0) {
      this.bus.emit('character:sheet-requested', { characterId: resolvedCharacterId });
    }

    if (typeof hexmap?.activateGameShellTab === 'function') {
      hexmap.activateGameShellTab('party');
      return;
    }

    const shell = typeof document !== 'undefined'
      ? document.querySelector('[data-game-shell]')
      : null;
    if (shell instanceof HTMLElement) {
      shell.dispatchEvent(new CustomEvent('dungeoncrawler:activate-tab', {
        detail: { tabId: 'party' },
      }));
    }
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

  formatPortraitsMeta(room, entryCount = 0) {
    const summary = entryCount > 0
      ? `${entryCount} room portrait${entryCount === 1 ? '' : 's'}`
      : 'Portraits for PCs and NPCs in the active room.';
    if (!room || typeof room !== 'object') {
      return summary;
    }
    return [
      summary,
      room.room_type ? String(room.room_type).replace(/_/g, ' ') : '',
      room.size_category ? String(room.size_category).replace(/_/g, ' ') : '',
    ].filter(Boolean).join(' • ');
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

  loadActiveRoomView(roomId, options = {}) {
    this.bus.emit('room:view-reload-requested', { roomId, ...options });
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

  updateRoomViewPanel(room, state = {}, _source = 'unknown') {
    console.log('[RoomViewPanel] updateRoomViewPanel', { source: _source, roomId: room?.room_id, roomName: room?.name, entries: state?.entries?.length ?? 0, statusLabel: state?.statusLabel ?? 'none' });
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
    if (this._el.roomViewDescription) {
      const description = String(room?.description || '').trim();
      this._el.roomViewDescription.textContent = description;
      this._el.roomViewDescription.hidden = description === '';
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

    const portraitEntries = this.buildRoomPortraitEntries(room?.room_id || room?.id || null);
    if (this._el.npcPortraitsName) {
      this._el.npcPortraitsName.textContent = room?.name || 'Current room';
    }
    if (this._el.npcPortraitsMeta) {
      this._el.npcPortraitsMeta.textContent = this.formatPortraitsMeta(room, portraitEntries.length);
    }
    if (this._el.npcPortraitsStatus) {
      this._el.npcPortraitsStatus.textContent = portraitEntries.length > 0 ? `${portraitEntries.length} Loaded` : 'Unavailable';
    }
    if (this._el.npcPortraitsPlaceholderText) {
      this._el.npcPortraitsPlaceholderText.textContent = portraitEntries.length > 0
        ? ''
        : 'No PC or NPC portraits are available for the active room yet.';
    }
    if (this._el.npcPortraitsGrid) {
      this._el.npcPortraitsGrid.innerHTML = '';
      this._el.npcPortraitsGrid.hidden = portraitEntries.length === 0;
      portraitEntries.forEach((entry) => {
        this._el.npcPortraitsGrid.appendChild(this.buildRoomPortraitCard(entry));
      });
    }
    if (this._el.npcPortraitsPlaceholder) {
      this._el.npcPortraitsPlaceholder.hidden = portraitEntries.length > 0;
    }

    const sceneImageSrc = this.resolveRoomViewImageSrc(entries);
    if (sceneImageSrc || !preserveChatBackground) {
      this.setChatPanelSceneBackground(sceneImageSrc, room);
    }

    // DOM visibility trace — helps identify panel hidden/CSS issues
    const gamePanel = this._el.roomViewGallery?.closest('#game-panel-view');
    console.log('[RoomViewPanel] updateRoomViewPanel:dom', {
      source: _source,
      entryCount: entries.length,
      statusLabel,
      placeholderText,
      galleryHidden: this._el.roomViewGallery?.hidden ?? 'no-el',
      placeholderHidden: this._el.roomViewPlaceholder?.hidden ?? 'no-el',
      roomViewNameText: this._el.roomViewName?.textContent ?? 'no-el',
      roomViewDescriptionLength: this._el.roomViewDescription?.textContent?.trim()?.length ?? 'no-el',
      roomViewStatusText: this._el.roomViewStatus?.textContent ?? 'no-el',
      panelHidden: gamePanel?.hidden ?? 'no-ancestor',
      panelDisplay: gamePanel ? window.getComputedStyle(gamePanel).display : 'no-ancestor',
      panelHasActiveClass: gamePanel?.classList?.contains('game-shell__panel--active') ?? 'no-ancestor',
    });
  }

  setChatPanelSceneBackground(imageSrc = '', room = null) {
    const chatShell = this._el.chatShell;
    if (!chatShell) {
      return;
    }

    const normalizedImageSrc = typeof imageSrc === 'string' ? imageSrc.trim() : '';
    if (!normalizedImageSrc) {
      chatShell.style.removeProperty('--chat-scene-image');
      chatShell.style.removeProperty('background-image');
      chatShell.style.removeProperty('background-position');
      chatShell.style.removeProperty('background-size');
      chatShell.style.removeProperty('background-repeat');
      chatShell.dataset.sceneReady = 'false';
      chatShell.removeAttribute('data-scene-room');
      return;
    }

    chatShell.style.setProperty('--chat-scene-image', `url(${JSON.stringify(normalizedImageSrc)})`);
    chatShell.style.backgroundImage = `linear-gradient(180deg, rgba(6, 10, 18, 0.22) 0%, rgba(6, 10, 18, 0.54) 55%, rgba(6, 10, 18, 0.72) 100%), url(${JSON.stringify(normalizedImageSrc)})`;
    chatShell.style.backgroundPosition = 'center';
    chatShell.style.backgroundSize = 'cover';
    chatShell.style.backgroundRepeat = 'no-repeat';
    chatShell.dataset.sceneReady = 'true';
    if (room?.name) {
      chatShell.dataset.sceneRoom = String(room.name);
    } else {
      chatShell.removeAttribute('data-scene-room');
    }
  }

}
