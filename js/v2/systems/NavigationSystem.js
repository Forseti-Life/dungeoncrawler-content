/**
 * @file systems/NavigationSystem.js
 *
 * Room transition and in-session dungeon context switching.
 *
 * Authority boundary:
 * - This system dispatches intents and applies server-authoritative results.
 * - It is not a navigation-rule engine and must not invent local legality.
 * - Any client-side checks here are UX guards only; server remains decisive.
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
      this.bus.on('user:navigate-dungeon', (d) => this.executeInSessionDungeonSwitch(d?.dungeonSwitch)),
      this.bus.on('navigation:apply-result', (d) => this.handleNavigationResult(d?.navigation)),
    );
  }

  async executeDirectNavigate(button) {
    console.log('[Navigation] executeDirectNavigate: entry', {
      hasButton: !!button,
      isHTMLButton: button instanceof HTMLButtonElement,
      roomId: button?.dataset?.roomId,
      roomName: button?.dataset?.roomName,
      mapId: button?.dataset?.mapId,
      dungeonLevelId: button?.dataset?.dungeonLevelId,
    });

    if (!this._beginActionRailRequest(button)) {
      console.error('[Navigation] executeDirectNavigate: _beginActionRailRequest returned false — aborting');
      return;
    }

    try {
      const context = this._getActionRailContext();
      const hexmap = context.hexmap;
      const roomId = String(button.dataset.roomId || '').trim();
      const roomName = button.dataset.roomName || roomId || 'that room';
      const connectionId = String(button.dataset.connectionId || '').trim();
      const rawOriginQ = String(button.dataset.originQ || '').trim();
      const rawOriginR = String(button.dataset.originR || '').trim();
      const originQ = rawOriginQ !== '' ? Number(rawOriginQ) : null;
      const originR = rawOriginR !== '' ? Number(rawOriginR) : null;
      const mapId = String(button.dataset.mapId || '').trim();
      const dungeonLevelId = String(button.dataset.dungeonLevelId || '').trim();

      console.log('[Navigation] executeDirectNavigate: context resolved', {
        hasHexmap: !!hexmap,
        roomId,
        roomName,
        connectionId,
        mapId,
        dungeonLevelId,
      });

      if (!hexmap || !roomId) {
        console.error('[Navigation] executeDirectNavigate: missing hexmap or roomId — aborting', { hasHexmap: !!hexmap, roomId });
        this._appendChatLine('System', 'Navigation could not resolve the destination room.', 'system');
        return;
      }

      // If the target room does not exist in the current dungeon's visual rooms,
      // resolve it through in-session generation/runtime-state loading.
      const visualRooms = typeof hexmap.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
      const roomExistsInCurrentDungeon = Boolean(visualRooms[roomId]);
      const activeRoomId = String(hexmap.resolveActiveRoomId?.() || '').trim();
      const capabilities = typeof hexmap.resolveNavigationCapabilities === 'function'
        ? hexmap.resolveNavigationCapabilities(activeRoomId)
        : [];
      const matchedCapability = Array.isArray(capabilities)
        ? capabilities.find((capability) => {
          if (connectionId) {
            return String(capability?.connection_id || '').trim() === connectionId;
          }
          return String(capability?.target_room_id || '').trim() === roomId;
        }) || null
        : null;
      const isQuestSyntheticDestination = connectionId.startsWith('quest-synthetic-')
        || (
          matchedCapability?.quest_reference === true
          && String(matchedCapability?.type || '').trim().toLowerCase() === 'synthetic'
        );
      console.log('[Navigation] executeDirectNavigate: room resolution', {
        roomId,
        roomExistsInCurrentDungeon,
        isQuestSyntheticDestination,
        activeRoomId,
        visualRoomCount: Object.keys(visualRooms).length,
        visualRoomKeys: Object.keys(visualRooms).slice(0, 5),
      });

      if (!roomExistsInCurrentDungeon || isQuestSyntheticDestination) {
        // Keep a single authoritative path for off-topology or quest-synthetic
        // destinations:
        // ask the server to resolve/generate the destination and any required
        // in-session dungeon switch metadata, then apply that response.
        await this.requestInSessionDestination(roomId || roomName, {
          fallbackRoomId: roomId,
          mapId,
          dungeonLevelId,
        });
        return;
      }

      const selectedEntity = hexmap.stateManager?.get('selectedEntity');
      const launchPlayer = hexmap.findLaunchPlayerEntity?.() || null;
      const actorId = String(
        context?.actorRef
        || selectedEntity?.dcEntityRef
        || selectedEntity?.dcEntityInstanceId
        || selectedEntity?.id
        || launchPlayer?.dcEntityRef
        || launchPlayer?.dcEntityInstanceId
        || launchPlayer?.instanceId
        || launchPlayer?.id
        || '',
      ).trim() || null;
      const coordinator = hexmap.gameCoordinator || null;

      console.log('[Navigation] executeDirectNavigate: actor/coordinator resolution', {
        actorId,
        hasCoordinator: !!coordinator,
        hasCoordinatorApi: !!coordinator?.api?.sendAction,
        contextActorRef: context?.actorRef,
        launchPlayerRef: launchPlayer?.dcEntityRef,
      });

      if (!coordinator?.api?.sendAction || !actorId) {
        console.warn('[Navigation] executeDirectNavigate: missing coordinator/actor, rerouting through in-session destination resolver', {
          hasCoordinator: !!coordinator,
          actorId,
          roomId,
          roomName,
          mapId,
          dungeonLevelId,
        });
        await this.requestInSessionDestination(roomId || roomName, {
          fallbackRoomId: roomId,
          mapId,
          dungeonLevelId,
        });
        return;
      }

      const params = {
        target_room_id: roomId,
      };
      if (connectionId) {
        params.connection_id = connectionId;
      }
      if (Number.isFinite(originQ) && Number.isFinite(originR)) {
        params.target_hex = { q: originQ, r: originR };
      }

      console.log('[Navigation] executeDirectNavigate: sending transition action', { actorId, params });
      // Authoritative transition validation and state mutation happen on server.
      let result = null;
      try {
        result = await coordinator.api.sendAction('transition', actorId, params, {
          stateVersion: coordinator.phaseManager?.stateVersion,
        });
      } catch (error) {
        const statusCode = Number(error?.status || 0);
        const serverError = String(
          error?.payload?.error
          || error?.message
          || 'That destination is not navigable right now.'
        ).trim();
        const isReachabilityFailure = Number(error?.status || 0) === 422
          && (
            /not reachable from the active room/i.test(serverError)
            || /not available for transition/i.test(serverError)
          );
        const isTransitionServiceFailure = statusCode >= 500
          || /service unavailable/i.test(serverError)
          || /internal server error/i.test(serverError);

        if (isReachabilityFailure || isTransitionServiceFailure) {
          console.warn('[Navigation] transition failed, rerouting through in-session destination resolver', {
            roomId,
            connectionId,
            status: statusCode,
            error: serverError,
            reason: isReachabilityFailure ? 'reachability' : 'service',
          });
          await this.requestInSessionDestination(roomId || roomName, {
            fallbackRoomId: roomId,
            mapId,
            dungeonLevelId,
          });
          return;
        }

        this._appendChatLine('System', serverError, 'error');
        return;
      }
      console.log('[Navigation] executeDirectNavigate: transition result', {
        success: result?.success,
        error: result?.error,
        activeRoomId: result?.game_state?.active_room_id || result?.active_room_id,
      });
      if (!result?.success) {
        this._appendChatLine('System', result?.error || 'That destination is not navigable right now.', 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      const nextRoomId = String(
        result?.game_state?.active_room_id
        || result?.active_room_id
        || roomId
        || ''
      ).trim();
      if (nextRoomId && typeof hexmap.setActiveRoom === 'function') {
        console.info('[Navigation] Syncing active room after successful navigate', {
          requestedRoomId: roomId,
          activeRoomId: nextRoomId,
        });
        hexmap.setActiveRoom(nextRoomId);
        const entryHex = result?.entry_hex || result?.navigation?.entry_hex || null;
        if (entryHex && Number.isFinite(Number(entryHex.q)) && Number.isFinite(Number(entryHex.r))) {
          hexmap.updateLaunchLocationContext?.(nextRoomId, Number(entryHex.q), Number(entryHex.r));
        }
      }
      this._appendChatLine('System', `Navigating to ${roomName}.`, 'system');
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  handleNavigationResult(nav) {
    // Apply navigation receipt produced by server systems. This method projects
    // server-authored deltas into local runtime structures for rendering.
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
      void this.executeInSessionDungeonSwitch({
        ...nav.dungeon_switch,
        room_id: nav.dungeon_switch.room_id || targetRoomId,
        target_room_id: targetRoomId || nav.dungeon_switch.target_room_id,
      });
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
    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
      window.dispatchEvent(new CustomEvent('dungeoncrawler:game-shell-tab-changed', {
        detail: { tabId: 'view' },
      }));
    }
    this.bus.emit('room:changed', {
      roomId: targetRoomId,
      roomName: nav.destination || newRoom?.name || targetRoomId,
      room: newRoom || null,
    });
    if (targetRoomId) {
      this.bus.emit('room:view-reload-requested', { roomId: targetRoomId, force: true, preserveExisting: true });
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

  async executeInSessionDungeonSwitch(dungeonSwitch) {
    // Cross-dungeon travel is now in-session: fetch authoritative runtime state
    // and hydrate; do not redirect URL or derive local dungeon snapshots.
    const hexmap = this.stateManager?.hexmap;
    if (!hexmap || !this.shell?.loadRuntimeStateBundle) {
      this._appendChatLine('System', 'Unable to switch destination in-session right now.', 'error');
      return;
    }

    const roomId = String(dungeonSwitch?.room_id || dungeonSwitch?.target_room_id || '').trim();
    const mapId = String(dungeonSwitch?.map_id || '').trim();
    const dungeonLevelId = String(dungeonSwitch?.dungeon_level_id || '').trim();
    const nextRoomId = String(dungeonSwitch?.next_room_id || '').trim();
    const campaignId = Number(hexmap.resolveCampaignId?.() || this.shell.resolveCampaignId?.() || 0);
    const characterId = Number(hexmap.launchContext?.character_id || this.shell.launchContext?.character_id || 0);

    if (!campaignId || !roomId) {
      this._appendChatLine('System', 'Navigation could not resolve campaign or destination room.', 'error');
      return;
    }

    try {
      const bundle = await this.shell.loadRuntimeStateBundle({
        campaign_id: campaignId,
        character_id: characterId || undefined,
        room_id: roomId,
        map_id: mapId || undefined,
        dungeon_level_id: dungeonLevelId || undefined,
        next_room_id: nextRoomId || undefined,
        start_q: 0,
        start_r: 0,
      });
      const resolvedRoomName = this.resolveRoomNameFromBundle(bundle, roomId);
      this._appendChatLine('System', `🗺️ Traveling to ${resolvedRoomName}...`, 'system');
      this._refreshActionRail();
      return bundle;
    } catch (error) {
      this._appendChatLine('System', error?.message || 'Unable to load destination runtime state.', 'error');
      return null;
    }
  }

  async requestInSessionDestination(destinationLabel, options = {}) {
    // Destination expansion/generation remains server-owned. Client requests and
    // then applies returned navigation receipts.
    if (typeof fetch !== 'function') {
      this._appendChatLine('System', 'Navigation API is unavailable in this environment.', 'error');
      return;
    }

    const hexmap = this.stateManager?.hexmap;
    const campaignId = Number(hexmap?.resolveCampaignId?.() || this.shell?.resolveCampaignId?.() || 0);
    const originRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
    const fallbackRoomId = typeof options === 'string'
      ? String(options).trim()
      : String(options?.fallbackRoomId || '').trim();
    const mapId = typeof options === 'object' ? String(options?.mapId || '').trim() : '';
    const dungeonLevelId = typeof options === 'object' ? String(options?.dungeonLevelId || '').trim() : '';
    const destination = String(destinationLabel || fallbackRoomId || '').trim();
    const characterId = Number(hexmap?.launchContext?.character_id || this.shell?.launchContext?.character_id || 0);
    const fallbackMapId = mapId
      || String(hexmap?.launchContext?.map_id || this.shell?.launchContext?.map_id || '').trim();
    const fallbackDungeonLevelId = dungeonLevelId
      || String(
        hexmap?.launchContext?.dungeon_level_id
        || this.shell?.launchContext?.dungeon_level_id
        || '',
      ).trim();

    if (!campaignId || !destination) {
      this._appendChatLine('System', 'Navigation could not resolve destination metadata.', 'error');
      return;
    }

    try {
      const response = await fetch(`/api/campaign/${campaignId}/gm/locations/request`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({
          destination,
          origin_room_id: originRoomId || undefined,
          character_id: characterId || undefined,
          map_id: fallbackMapId || undefined,
          dungeon_level_id: fallbackDungeonLevelId || undefined,
        }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload?.success || !payload?.data?.navigation) {
        throw new Error(payload?.error || 'Unable to resolve destination in-session.');
      }

      const navigation = payload.data.navigation;
      const resolvedRoomId = String(
        navigation?.dungeon_switch?.room_id
        || navigation?.target_room_id
        || fallbackRoomId
        || '',
      ).trim();

      const shouldForceBundleReload = !navigation?.dungeon_switch?.map_id
        && fallbackMapId !== ''
        && resolvedRoomId !== '';

      if (navigation?.dungeon_switch?.map_id || shouldForceBundleReload) {
        const switchPayload = navigation?.dungeon_switch?.map_id
          ? {
            ...navigation.dungeon_switch,
            room_id: resolvedRoomId,
            target_room_id: navigation.target_room_id || navigation.dungeon_switch.target_room_id || resolvedRoomId,
          }
          : {
            map_id: fallbackMapId,
            dungeon_level_id: fallbackDungeonLevelId || undefined,
            room_id: resolvedRoomId,
            target_room_id: resolvedRoomId,
            next_room_id: '',
          };
        await this.executeInSessionDungeonSwitch(switchPayload);
        return;
      }
      this.handleNavigationResult(navigation);
    } catch (error) {
      this._appendChatLine('System', error?.message || 'Unable to generate destination.', 'error');
    }
  }

  resolveRoomNameFromBundle(bundle, fallbackRoomId = '') {
    const roomId = String(bundle?.launch_context?.room_id || fallbackRoomId || '').trim();
    const rooms = bundle?.map_visual_state?.topology?.rooms;
    if (roomId && rooms && typeof rooms === 'object') {
      const room = rooms[roomId];
      const roomName = String(room?.name || room?.title || '').trim();
      if (roomName) {
        return roomName;
      }
    }
    return roomId || 'destination';
  }

  // --- Proxy helpers (UIManager methods now live on panels/bus) ---

  _beginActionRailRequest(button) {
    const hasActionRail = !!this.shell?.panels?.actionRail;
    console.log('[Navigation] _beginActionRailRequest', { hasActionRail, isHTMLButton: button instanceof HTMLButtonElement });
    const result = this.shell.panels.actionRail?.beginActionRailRequest(button) ?? false;
    if (!result) {
      console.warn('[Navigation] _beginActionRailRequest: returned false', { hasActionRail, isHTMLButton: button instanceof HTMLButtonElement, pending: button?.dataset?.actionRailPending });
    }
    return result;
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
    this.bus.emit('chat:system-message', {
      text: message,
      speaker,
      kind: type,
      view: 'room',
      channel: 'room',
      source: 'navigation-system',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
    });
  }

}
