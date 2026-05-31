/**
 * @file panels/PortraitPanel.js
 *
 * Renders room occupant portraits (PCs and NPCs).
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class PortraitPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    // State carried over from UIManager
    this.dungeonData = null;
    this.stateManager = null;
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => document.getElementById(k);
    this._el = {
      npcPortraitsPanel:           id('npc-portraits-panel'),
      npcPortraitsName:            id('npc-portraits-name'),
      npcPortraitsMeta:            id('npc-portraits-meta'),
      npcPortraitsStatus:          id('npc-portraits-status'),
      npcPortraitsGrid:            id('npc-portraits-grid'),
      npcPortraitsPlaceholder:     id('npc-portraits-placeholder'),
      npcPortraitsPlaceholderText: id('npc-portraits-placeholder-text'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[PortraitPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('room:changed', (d) => this.loadRoomPortraitsPanel(d?.roomId)),
      this.bus.on('room:occupants-changed', (d) => this.loadRoomPortraitsPanel(d?.roomId)),
    );
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

      entries.push({
        entityId,
        name,
        kind,
        portraitUrl,
        summary,
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

  loadRoomPortraitsPanel(roomId = null) {
    if (!this._el.npcPortraitsPanel) {
      return;
    }

    const hexmap = this.stateManager?.hexmap || null;
    const resolvedRoomId = roomId || hexmap?.resolveActiveRoomId?.() || null;
    const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
    const room = resolvedRoomId
      ? visualRooms[resolvedRoomId] || null
      : hexmap?.getActiveRoomData?.() || null;
    const entries = this.buildRoomPortraitEntries(resolvedRoomId);

    if (this._el.npcPortraitsName) {
      this._el.npcPortraitsName.textContent = room?.name || 'Current room';
    }
    if (this._el.npcPortraitsMeta) {
      this._el.npcPortraitsMeta.textContent = this.formatPortraitsMeta(room, entries.length);
    }
    if (this._el.npcPortraitsStatus) {
      this._el.npcPortraitsStatus.textContent = entries.length > 0 ? `${entries.length} Loaded` : 'Unavailable';
    }
    if (this._el.npcPortraitsPlaceholderText) {
      this._el.npcPortraitsPlaceholderText.textContent = entries.length > 0
        ? ''
        : 'No PC or NPC portraits are available for the active room yet.';
    }
    if (this._el.npcPortraitsGrid) {
      this._el.npcPortraitsGrid.innerHTML = '';
      this._el.npcPortraitsGrid.hidden = entries.length === 0;
      entries.forEach((entry) => {
        this._el.npcPortraitsGrid.appendChild(this.buildRoomPortraitCard(entry));
      });
    }
    if (this._el.npcPortraitsPlaceholder) {
      this._el.npcPortraitsPlaceholder.hidden = entries.length > 0;
    }
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

}
