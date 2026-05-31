/**
 * @file systems/NavigationSystem.js
 *
 * Room transition and dungeon context switching.
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class NavigationSystem {
  constructor(shell, bus) {
    this.shell = shell;
    this.bus = bus;
    this.stateManager = null;
    this.dungeonData = null;
    this._unsubs = [];
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('user:navigate', (d) => this.executeDirectNavigate(d?.button)),
      this.bus.on('user:navigate-dungeon', (d) => this.navigateToDungeonContext(d?.dungeonSwitch)),
    );
  }

  ensureNavigateLocationGroups(campaignId) {
    if (!campaignId || (this.navigateLocationsCampaignId === campaignId && Array.isArray(this.navigateLocationGroups) && this.navigateLocationGroups.length)) {
      return;
    }
    if (this.navigateLocationsInflight) {
      return;
    }

    this.navigateLocationsInflight = fetch(`/api/campaign/${campaignId}/visited-locations`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.error || 'Unable to load visited locations.');
        }

        this.navigateLocationsCampaignId = campaignId;
        this.navigateLocationGroups = (Array.isArray(data.dungeons) ? data.dungeons : [])
          .map((group) => ({
            dungeonId: String(group?.dungeon_id || ''),
            dungeonName: String(group?.dungeon_name || group?.dungeon_id || 'Dungeon'),
            mapId: String(group?.map_id || group?.dungeon_id || ''),
            dungeonLevelId: String(group?.dungeon_level_id || ''),
            locations: Array.isArray(group?.locations)
              ? group.locations.map((location) => ({
                roomId: String(location?.room_id || ''),
                roomName: String(location?.room_name || location?.room_id || 'Room'),
                meta: String(location?.description || ''),
                lastVisitedLabel: Number(location?.last_visited || 0) > 0
                  ? `Visited ${new Date(Number(location.last_visited) * 1000).toLocaleString()}`
                  : 'Visited by party',
              })).filter((location) => location.roomId)
              : [],
          }))
          .filter((group) => group.locations.length > 0);
      })
      .catch((error) => {
        console.warn('Failed to load campaign visited locations:', error);
      })
      .finally(() => {
        this.navigateLocationsInflight = null;
        if (this.activeActionRailCategory === 'navigate') {
          this._refreshActionRail();
        }
      });
  }

  async executeDirectNavigate(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const hexmap = context.hexmap;
      const roomId = String(button.dataset.roomId || '').trim();
      const roomName = button.dataset.roomName || roomId || 'that room';
      const rawOriginQ = String(button.dataset.originQ || '').trim();
      const rawOriginR = String(button.dataset.originR || '').trim();
      const originQ = rawOriginQ !== '' ? Number(rawOriginQ) : null;
      const originR = rawOriginR !== '' ? Number(rawOriginR) : null;

      if (!hexmap || !roomId) {
        return;
      }

      let changed = false;
      if (Number.isFinite(originQ) && Number.isFinite(originR)) {
        changed = Boolean(hexmap.tryTransitionAtHex?.(originQ, originR));
      } else if (hexmap?.getVisualRooms?.()?.[roomId]) {
        changed = Boolean(hexmap.navigateToVisitedRoom?.(roomId));
      }
      if (!changed) {
        this._appendChatLine('System', 'That destination is not navigable right now.', 'system');
        return;
      }

      this._appendChatLine('System', `Navigating to ${roomName}.`, 'system');
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  handleNavigationResult(nav) {
    const hexmap = this.stateManager?.hexmap;
    if (!hexmap || !hexmap.dungeonData) {
      console.error('[Navigation] hexmap or dungeonData not available');
      return;
    }

    const targetRoomId = nav.target_room_id;
    const newRoom = nav.room;
    const newEntities = nav.entities || [];
    const newConnections = nav.connections || [];
    const entryHex = nav.entry_hex || { q: 0, r: 0 };

    console.log('[Navigation] Transitioning to:', targetRoomId, nav.destination);

    if (nav.dungeon_switch?.map_id) {
      this._appendChatLine('System', `🗺️ Traveling to ${nav.destination || targetRoomId}...`, 'system');
      this.navigateToDungeonContext(nav.dungeon_switch);
      return;
    }

    // 1. Inject the new room into dungeonData.rooms (keyed by room_id).
    if (newRoom && targetRoomId) {
      hexmap.dungeonData.rooms[targetRoomId] = newRoom;
    }

    // 2. Append new entities to dungeonData.entities.
    if (!Array.isArray(hexmap.dungeonData.entities)) {
      hexmap.dungeonData.entities = [];
    }
    for (const entity of newEntities) {
      // Avoid duplicates by instance_id.
      const existingIdx = hexmap.dungeonData.entities.findIndex(
        (e) => (e.instance_id || e.entity_instance_id) === (entity.instance_id || entity.entity_instance_id)
      );
      if (existingIdx === -1) {
        hexmap.dungeonData.entities.push(entity);
      }
    }

    // 3. Append new connections to dungeonData.connections.
    if (!Array.isArray(hexmap.dungeonData.connections)) {
      hexmap.dungeonData.connections = [];
    }
    for (const conn of newConnections) {
      // Avoid duplicate connections.
      const connId = conn.connection_id || `${conn.from_room}_${conn.to_room}`;
      const exists = hexmap.dungeonData.connections.some(
        (c) => (c.connection_id || `${c.from_room}_${c.to_room}`) === connId
      );
      if (!exists) {
        hexmap.dungeonData.connections.push(conn);
      }
    }

    // 4. Move the selected player entity to the new room entry hex.
    const selectedEntity = hexmap.stateManager?.get('selectedEntity');
    if (selectedEntity && Array.isArray(hexmap.dungeonData.entities)) {
      const entityRef = selectedEntity.dcEntityRef;
      for (const de of hexmap.dungeonData.entities) {
        const deRef = de.instance_id || de.entity_instance_id;
        if (deRef === entityRef || (selectedEntity.dcCharacterId && de?.state?.metadata?.character_id == selectedEntity.dcCharacterId)) {
          de.placement = {
            room_id: targetRoomId,
            hex: { q: Number(entryHex.q), r: Number(entryHex.r) },
          };
          break;
        }
      }

      // Also move ally NPCs to adjacent hexes.
      const allyNpcs = hexmap.dungeonData.entities.filter(
        (e) => e.entity_type === 'npc' && e?.state?.metadata?.team === 'ally'
      );
      const offsets = [{ q: 1, r: 0 }, { q: -1, r: 0 }, { q: 0, r: 1 }, { q: 0, r: -1 }, { q: 1, r: -1 }, { q: -1, r: 1 }];
      allyNpcs.forEach((npc, i) => {
        const offset = offsets[i % offsets.length];
        const npcQ = Number(entryHex.q) + offset.q;
        const npcR = Number(entryHex.r) + offset.r;
        npc.placement = {
          room_id: targetRoomId,
          hex: { q: npcQ, r: npcR },
        };
        hexmap.persistLaunchLocationContext?.(
          targetRoomId,
          npcQ,
          npcR,
          npc.instance_id || npc.entity_instance_id || null
        );
      });

      // Deselect before room switch.
      hexmap.deselectEntity();
    }

    hexmap.persistLaunchLocationContext?.(
      targetRoomId,
      Number(entryHex.q),
      Number(entryHex.r),
      selectedEntity?.dcEntityRef || null
    );

    // 5. Show travel notification in chat.
    this._appendChatLine('System', `🗺️ Traveling to ${nav.destination || newRoom?.name || targetRoomId}...`, 'system');

    // 6. Switch to the new room (triggers full re-render, chat reload, banner).
    hexmap.setActiveRoom(targetRoomId);
    hexmap.updateLaunchLocationContext?.(targetRoomId, Number(entryHex.q), Number(entryHex.r));
    this.activateGameShellTab('view');
    if (targetRoomId && this.loadActiveRoomView) {
      this.loadActiveRoomView(targetRoomId, { force: true, preserveExisting: true });
    }

    // 7. Re-select the player entity in the new room.
    const newPlayerEntity = hexmap.findLaunchPlayerEntity();
    if (newPlayerEntity) {
      hexmap.selectEntity(newPlayerEntity);
      if (hexmap.launchCharacter) {
        hexmap.uiManager?.showLaunchCharacter?.(hexmap.launchCharacter);
      }
    }

    console.log('[Navigation] Room switch complete:', targetRoomId);
  }

  navigateToDungeonContext(dungeonSwitch) {
    if (typeof window === 'undefined' || !window.location) {
      console.error('[Navigation] window.location not available for dungeon switch');
      return;
    }

    const hexmap = this.stateManager?.hexmap;
    const params = new URLSearchParams(window.location.search);
    const campaignId = hexmap?.resolveCampaignId?.() || params.get('campaign_id');
    const characterId = hexmap?.launchContext?.character_id || params.get('character_id');

    if (campaignId) {
      params.set('campaign_id', String(campaignId));
    }
    if (characterId) {
      params.set('character_id', String(characterId));
    }

    params.set('map_id', String(dungeonSwitch.map_id));
    params.set('room_id', String(dungeonSwitch.room_id || dungeonSwitch.target_room_id || ''));
    if (dungeonSwitch.dungeon_level_id) {
      params.set('dungeon_level_id', String(dungeonSwitch.dungeon_level_id));
    }
    if (dungeonSwitch.next_room_id) {
      params.set('next_room_id', String(dungeonSwitch.next_room_id));
    } else {
      params.delete('next_room_id');
    }
    params.set('start_q', '0');
    params.set('start_r', '0');

    window.location.assign(`${window.location.pathname}?${params.toString()}`);
  }

  // --- Proxy helpers (UIManager methods now live on panels/bus) ---

  _beginActionRailRequest(button) {
    return this.shell.panels.actionRail?.beginActionRailRequest(button) ?? false;
  }

  _endActionRailRequest(button) {
    this.shell.panels.actionRail?.endActionRailRequest(button);
  }

  _getActionRailContext() {
    return this.shell.panels.actionRail?.getActionRailContext() ?? {};
  }

  _refreshActionRail() {
    this.shell.panels.actionRail?.refreshActionRail?.();
  }

  _appendChatLine(speaker, message, type = 'system') {
    this.bus.emit('chat:system-message', { text: message, speaker, kind: type });
  }

}
