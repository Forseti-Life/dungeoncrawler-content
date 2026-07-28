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
    this._actionRailPendingRequests = new Map();
    this._navigationTransitionPending = false;
    this._navigationTransitionRoomId = '';
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this._actionRailPendingRequests.clear();
    this._navigationTransitionPending = false;
    this._navigationTransitionRoomId = '';
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

    if (this._navigationTransitionPending) {
      console.warn('[Navigation] executeDirectNavigate: ignored because a prior transition is still synchronizing', {
        pendingRoomId: this._navigationTransitionRoomId || null,
        requestedRoomId: String(button?.dataset?.roomId || '').trim() || null,
      });
      this._appendChatLine('System', 'Navigation is still synchronizing the previous room transition. Please wait a moment.', 'system');
      return;
    }

    if (!this._beginActionRailRequest(button)) {
      console.error('[Navigation] executeDirectNavigate: _beginActionRailRequest returned false — aborting');
      return;
    }

    this._navigationTransitionPending = true;
    this._navigationTransitionRoomId = String(button?.dataset?.roomId || '').trim();
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
      this._appendChatLine('System', `Navigating to ${roomName}.`, 'system');

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

      const hasCanonicalTransition = Boolean(connectionId) || Boolean(matchedCapability);
      if ((!roomExistsInCurrentDungeon && !hasCanonicalTransition) || isQuestSyntheticDestination) {
        console.warn('[Navigation] executeDirectNavigate: routing through in-session destination request', {
          roomId,
          roomName,
          roomExistsInCurrentDungeon,
          hasCanonicalTransition,
          isQuestSyntheticDestination,
          connectionId,
          matchedCapability,
        });
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
      const fallbackActorId = String(
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
        actorId: fallbackActorId,
        hasCoordinator: !!coordinator,
        hasCoordinatorApi: !!coordinator?.api?.sendAction,
        contextActorRef: context?.actorRef,
        launchPlayerRef: launchPlayer?.dcEntityRef,
      });

      if (!coordinator?.api?.sendAction) {
        console.error('[Navigation] executeDirectNavigate: no coordinator available — aborting', { hasCoordinator: !!coordinator });
        this._appendChatLine('System', 'No active player actor is available for navigation right now.', 'system');
        return;
      }

      const authoritativeState = await this._getAuthoritativeCoordinatorState(coordinator, hexmap);
      const currentRoomId = String(
        authoritativeState?.activeRoomId
        || coordinator?.phaseManager?.activeRoomId
        || hexmap.resolveActiveRoomId?.()
        || ''
      ).trim();
      if (currentRoomId && currentRoomId === roomId) {
        this._appendChatLine('System', `You are already in ${roomName}.`, 'system');
        return;
      }

      const localStateVersion = Number(
        authoritativeState?.stateVersion
        ?? coordinator?.phaseManager?.stateVersion
        ?? 0
      ) || 0;
      const actorId = String(
        authoritativeState?.turnEntity
        || coordinator?.phaseManager?.turn?.entity
        || fallbackActorId
        || ''
      ).trim();
      if (!actorId) {
        console.error('[Navigation] executeDirectNavigate: missing local actor for transition');
        this._appendChatLine('System', 'No active player actor is available for navigation right now.', 'system');
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
      let optimisticApplied = false;
      // Avoid optimistic room projection here: it can trigger duplicate room-load
      // fan-out before authoritative navigation capabilities arrive.
      let result = null;
      try {
        result = await coordinator.api.sendAction('transition', actorId, params, {
          stateVersion: localStateVersion,
        });
      } catch (error) {
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
        const isAlreadyThereFailure = Number(error?.status || 0) === 422
          && /not reachable from the active room/i.test(serverError);

        if (isAlreadyThereFailure) {
          const refreshed = await this._getAuthoritativeCoordinatorState(coordinator, hexmap);
          const refreshedRoomId = String(refreshed?.activeRoomId || '').trim();
          if (refreshedRoomId && refreshedRoomId === roomId) {
            this._appendChatLine('System', `You are already in ${roomName}.`, 'system');
            return;
          }
        }

        if (isReachabilityFailure) {
          if (optimisticApplied && currentRoomId && typeof hexmap.setActiveRoom === 'function') {
            hexmap.setActiveRoom(currentRoomId, {
              source: 'navigation-system',
              phase: 'navigation-rollback',
            });
          }
          console.warn('[Navigation] transition reachability failed', {
            roomId,
            connectionId,
            status: error?.status,
            error: serverError,
            payload: error?.payload || null,
          });
          this._appendChatLine('System', serverError, 'error');
          return;
        }
        if (/state version mismatch/i.test(serverError)) {
          console.warn('[Navigation] transition state version mismatch, retrying with authoritative state', {
            roomId,
            connectionId,
            status: error?.status,
            error: serverError,
          });
          const refreshedState = await this._getAuthoritativeCoordinatorState(coordinator, hexmap);
          const retryActorId = String(refreshedState?.turnEntity || fallbackActorId || '').trim();
          if (!retryActorId) {
            if (optimisticApplied && currentRoomId && typeof hexmap.setActiveRoom === 'function') {
              hexmap.setActiveRoom(currentRoomId, {
                source: 'navigation-system',
                phase: 'navigation-rollback',
              });
            }
            this._appendChatLine('System', serverError, 'error');
            return;
          }
          try {
            result = await coordinator.api.sendAction('transition', retryActorId, params, {
              stateVersion: refreshedState?.stateVersion ?? coordinator.phaseManager?.stateVersion,
            });
          } catch (retryError) {
            if (optimisticApplied && currentRoomId && typeof hexmap.setActiveRoom === 'function') {
              hexmap.setActiveRoom(currentRoomId, {
                source: 'navigation-system',
                phase: 'navigation-rollback',
              });
            }
            const retryServerError = String(
              retryError?.payload?.error
              || retryError?.message
              || serverError
            ).trim();
            this._appendChatLine('System', retryServerError, 'error');
            return;
          }
        } else {
          if (optimisticApplied && currentRoomId && typeof hexmap.setActiveRoom === 'function') {
            hexmap.setActiveRoom(currentRoomId, {
              source: 'navigation-system',
              phase: 'navigation-rollback',
            });
          }
          this._appendChatLine('System', serverError, 'error');
          return;
        }
      }
      console.log('[Navigation] executeDirectNavigate: transition result', {
        success: result?.success,
        error: result?.error,
        activeRoomId: result?.game_state?.active_room_id || result?.active_room_id,
      });
      if (!result?.success) {
        if (optimisticApplied && currentRoomId && typeof hexmap.setActiveRoom === 'function') {
          hexmap.setActiveRoom(currentRoomId, {
            source: 'navigation-system',
            phase: 'navigation-rollback',
          });
        }
        this._appendChatLine('System', result?.error || 'That destination is not navigable right now.', 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      const navigationResult = result?.navigation && typeof result.navigation === 'object'
        ? result.navigation
        : null;
      const hasAuthoritativeNavigationReceipt = Boolean(
        navigationResult
        && Array.isArray(navigationResult.navigation_capabilities)
        && String(navigationResult.target_room_id || '').trim() !== ''
      );
      const fallbackNavigationCapabilities = Array.isArray(result?.navigation_capabilities)
        ? result.navigation_capabilities
        : (Array.isArray(result?.game_state?.navigation_capabilities) ? result.game_state.navigation_capabilities : null);
      const nextRoomId = String(
        result?.game_state?.active_room_id
        || navigationResult?.target_room_id
        || result?.active_room_id
        || roomId
        || ''
      ).trim();

      if (hasAuthoritativeNavigationReceipt) {
        this.handleNavigationResult(navigationResult);
        this._refreshActionRail();
      } else {
        if (Array.isArray(fallbackNavigationCapabilities)) {
          hexmap.dungeonData.navigation_capabilities = fallbackNavigationCapabilities;
          this.bus.emit('navigation:capabilities-updated', {
            roomId: nextRoomId || null,
            capabilityCount: fallbackNavigationCapabilities.length,
            source: 'transition-result-fallback',
          });
        }

        if (nextRoomId && typeof hexmap.setActiveRoom === 'function') {
          console.info('[Navigation] Syncing active room after successful navigate', {
            requestedRoomId: roomId,
            activeRoomId: nextRoomId,
          });
          hexmap.setActiveRoom(nextRoomId, {
            source: 'navigation-system',
            phase: 'navigation-authoritative',
          });
          const entryHex = result?.entry_hex || navigationResult?.entry_hex || null;
          if (entryHex && Number.isFinite(Number(entryHex.q)) && Number.isFinite(Number(entryHex.r))) {
            this._persistPartyLocationAfterTransition(hexmap, nextRoomId, entryHex);
            hexmap.updateLaunchLocationContext?.(nextRoomId, Number(entryHex.q), Number(entryHex.r));
          }
        }
        this._refreshActionRail();
      }

      const postTransitionTasks = [];
      if (nextRoomId && this.shell?.loadRuntimeStateBundle && !hasAuthoritativeNavigationReceipt) {
        const authoritativeMapId = String(
          result?.dungeon_id
          || result?.map_id
          || result?.game_state?.dungeon_id
          || navigationResult?.dungeon_id
          || navigationResult?.map_id
          || ''
        ).trim();
        const authoritativeDungeonLevelId = String(
          result?.dungeon_level_id
          || result?.game_state?.dungeon_level_id
          || navigationResult?.dungeon_level_id
          || ''
        ).trim();
        const currentMapId = String(
          hexmap?.dungeonData?.map_id
          || hexmap?.launchContext?.map_id
          || this.shell?.launchContext?.map_id
          || ''
        ).trim();
        const currentDungeonLevelId = String(
          hexmap?.dungeonData?.level_id
          || hexmap?.launchContext?.dungeon_level_id
          || this.shell?.launchContext?.dungeon_level_id
          || ''
        ).trim();
        const roomScopedCapabilities = typeof hexmap?.resolveNavigationCapabilities === 'function'
          ? hexmap.resolveNavigationCapabilities(nextRoomId)
          : [];
        const requiresRuntimeBundleHydration = (
          !Array.isArray(fallbackNavigationCapabilities)
          || fallbackNavigationCapabilities.length === 0
          || !Array.isArray(hexmap?.dungeonData?.navigation_capabilities)
          || hexmap.dungeonData.navigation_capabilities.length === 0
          || String(hexmap?.resolveActiveRoomId?.() || '').trim() !== nextRoomId
          || !Array.isArray(roomScopedCapabilities)
          || roomScopedCapabilities.length === 0
          || (authoritativeMapId === '' && authoritativeDungeonLevelId === '' && navigationResult === null)
          || (authoritativeMapId !== '' && authoritativeMapId !== currentMapId)
          || (authoritativeDungeonLevelId !== '' && authoritativeDungeonLevelId !== currentDungeonLevelId)
        );
        if (requiresRuntimeBundleHydration) {
          const runtimeBundleQuery = this.shell.buildRuntimeBundleQueryForRoom(nextRoomId, {
            mapId: authoritativeMapId || undefined,
            dungeonLevelId: authoritativeDungeonLevelId || undefined,
            startQ: Number.isFinite(Number(result?.entry_hex?.q ?? navigationResult?.entry_hex?.q))
              ? Number(result?.entry_hex?.q ?? navigationResult?.entry_hex?.q)
              : 0,
            startR: Number.isFinite(Number(result?.entry_hex?.r ?? navigationResult?.entry_hex?.r))
              ? Number(result?.entry_hex?.r ?? navigationResult?.entry_hex?.r)
              : 0,
          });
          postTransitionTasks.push(
            this.shell.loadRuntimeStateBundle(runtimeBundleQuery)
              .then(() => {
                if (typeof this.shell?.syncCoordinatorStateFromServer === 'function') {
                  return this.shell.syncCoordinatorStateFromServer(nextRoomId);
                }
                return true;
              })
              .then(() => {
                this._refreshActionRail();
              })
              .catch((error) => {
                this._handleRuntimeBundleLoadFailure(error, roomName, nextRoomId);
              })
          );
        }
      }
      if (nextRoomId) {
        postTransitionTasks.push(this._reconcileAuthoritativeStateAfterTransition(coordinator, hexmap, nextRoomId));
      }
      if (postTransitionTasks.length > 0) {
        await Promise.allSettled(postTransitionTasks);
      }
      this._refreshActionRail();
    } finally {
      this._navigationTransitionPending = false;
      this._navigationTransitionRoomId = '';
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
    // Presentation-only contract: the client must render the server-authored
    // navigation payload as-is and must never synthesize or clear exits locally.
    // Missing navigation_capabilities is a server contract failure, not a client
    // reconciliation case.
    if (!Array.isArray(nav.navigation_capabilities)) {
      throw new Error('Navigation receipt contract violation: navigation_capabilities is required for client rendering.');
    }
    const navigationCapabilities = nav.navigation_capabilities;
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

    // Keep active-room navigation strictly sourced from server navigation service.
    hexmap.dungeonData.navigation_capabilities = navigationCapabilities;
    this.bus.emit('navigation:capabilities-updated', {
      roomId: String(targetRoomId || '').trim(),
      capabilityCount: Array.isArray(navigationCapabilities) ? navigationCapabilities.length : 0,
      source: 'navigation-receipt',
    });

    // 4. Move the full party formation to the destination entry hex.
    const selectedEntity = hexmap.stateManager?.get('selectedEntity');
    const partyFormationOffsets = [
      { q: 0, r: 0 },
      { q: 1, r: 0 }, { q: -1, r: 0 }, { q: 0, r: 1 }, { q: 0, r: -1 }, { q: 1, r: -1 }, { q: -1, r: 1 },
      { q: 2, r: 0 }, { q: -2, r: 0 }, { q: 0, r: 2 }, { q: 0, r: -2 }, { q: 2, r: -1 }, { q: -2, r: 1 },
    ];
    const resolvePartyOffset = (index) => partyFormationOffsets[index] || partyFormationOffsets[index % partyFormationOffsets.length];
    const destinationQ = Number(entryHex.q);
    const destinationR = Number(entryHex.r);
    const persistedPartyMoves = [];

    let anchorEntityRef = String(selectedEntity?.dcEntityRef || '').trim();
    if (Array.isArray(hexmap.dungeonData.entities)) {
      const selectedEntityRef = String(selectedEntity?.dcEntityRef || '').trim();
      const selectedCharacterId = Number(selectedEntity?.dcCharacterId || 0) || null;
      const launchCharacterId = Number(
        hexmap?.launchContext?.character_id
        || hexmap?.launchCharacter?.character_id
        || hexmap?.launchCharacter?.id
        || 0
      ) || null;
      const isPartyEntity = (entity) => {
        const rawType = String(entity?.entity_type || '').trim().toLowerCase();
        const metadata = entity?.state?.metadata || {};
        const entityRef = String(entity?.instance_id || entity?.entity_instance_id || entity?.id || '').trim();
        const entityCharacterId = Number(metadata?.character_id || entity?.character_id || 0) || null;
        if (selectedEntityRef !== '' && entityRef === selectedEntityRef) {
          return true;
        }
        if (selectedCharacterId && entityCharacterId === selectedCharacterId) {
          return true;
        }
        if (launchCharacterId && entityCharacterId === launchCharacterId) {
          return true;
        }
        if (rawType === 'player_character' || rawType === 'player') {
          return true;
        }
        const team = String(metadata?.team || '').trim().toLowerCase();
        return team === 'ally' || team === 'player';
      };
      const partyEntities = hexmap.dungeonData.entities.filter(isPartyEntity);
      partyEntities.sort((left, right) => {
        const leftRef = String(left?.instance_id || left?.entity_instance_id || left?.id || '').trim();
        const rightRef = String(right?.instance_id || right?.entity_instance_id || right?.id || '').trim();
        const leftMeta = left?.state?.metadata || {};
        const rightMeta = right?.state?.metadata || {};
        const leftCharacterId = Number(leftMeta?.character_id || left?.character_id || 0) || null;
        const rightCharacterId = Number(rightMeta?.character_id || right?.character_id || 0) || null;
        if (selectedEntityRef && leftRef === selectedEntityRef) {
          return -1;
        }
        if (selectedEntityRef && rightRef === selectedEntityRef) {
          return 1;
        }
        if (selectedCharacterId && leftCharacterId === selectedCharacterId) {
          return -1;
        }
        if (selectedCharacterId && rightCharacterId === selectedCharacterId) {
          return 1;
        }
        const leftType = String(left?.entity_type || '').trim().toLowerCase();
        const rightType = String(right?.entity_type || '').trim().toLowerCase();
        const leftIsPc = leftType === 'player_character' || leftType === 'player';
        const rightIsPc = rightType === 'player_character' || rightType === 'player';
        if (leftIsPc !== rightIsPc) {
          return leftIsPc ? -1 : 1;
        }
        return leftRef.localeCompare(rightRef);
      });

      partyEntities.forEach((entity, index) => {
        const offset = resolvePartyOffset(index);
        const entityRef = String(entity?.instance_id || entity?.entity_instance_id || entity?.id || '').trim();
        entity.placement = {
          ...(entity?.placement || {}),
          room_id: targetRoomId,
          hex: { q: destinationQ + offset.q, r: destinationR + offset.r },
        };
        if (entityRef) {
          persistedPartyMoves.push({
            entityRef,
            q: destinationQ + offset.q,
            r: destinationR + offset.r,
          });
        }
      });

      if (!anchorEntityRef && partyEntities[0]) {
        anchorEntityRef = String(
          partyEntities[0]?.instance_id
          || partyEntities[0]?.entity_instance_id
          || partyEntities[0]?.id
          || ''
        ).trim();
      }
    }
    if (selectedEntity) {
      // Deselect before room switch.
      hexmap.deselectEntity();
    }

    this._synchronizeVisualStateForNavigation(
      hexmap,
      targetRoomId,
      newRoom,
      newConnections,
      entryHex,
      String(nav.destination || '').trim()
    );

    hexmap.persistLaunchLocationContext?.(
      targetRoomId,
      destinationQ,
      destinationR,
      anchorEntityRef || null
    );
    persistedPartyMoves.forEach((move) => {
      hexmap.persistLaunchLocationContext?.(targetRoomId, move.q, move.r, move.entityRef);
    });

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
    // setActiveRoom() already emits canonical room:changed and room:occupants-changed
    // through GameShell; avoid duplicate room lifecycle fan-out here.

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

  _synchronizeVisualStateForNavigation(hexmap, targetRoomId, newRoom, newConnections, entryHex, roomNameHint = '') {
    if (!hexmap || !targetRoomId) {
      return;
    }

    if (!hexmap.mapVisualState || typeof hexmap.mapVisualState !== 'object') {
      hexmap.mapVisualState = {};
    }
    const visualState = hexmap.mapVisualState;
    if (!visualState.map_meta || typeof visualState.map_meta !== 'object') {
      visualState.map_meta = {};
    }
    visualState.map_meta.active_room_id = targetRoomId;

    if (!visualState.topology || typeof visualState.topology !== 'object') {
      visualState.topology = {};
    }
    if (!Array.isArray(visualState.topology.connections)) {
      visualState.topology.connections = [];
    }
    for (const conn of Array.isArray(newConnections) ? newConnections : []) {
      const connId = String(conn?.connection_id || `${conn?.from_room || ''}__${conn?.to_room || ''}`).trim();
      if (!connId) {
        continue;
      }
      const exists = visualState.topology.connections.some((candidate) => {
        const candidateId = String(candidate?.connection_id || `${candidate?.from_room || ''}__${candidate?.to_room || ''}`).trim();
        return candidateId === connId;
      });
      if (!exists) {
        visualState.topology.connections.push(conn);
      }
    }

    if (!visualState.topology.rooms || typeof visualState.topology.rooms !== 'object') {
      visualState.topology.rooms = {};
    }
    const previousRoom = (
      visualState.topology.rooms[targetRoomId]
      && typeof visualState.topology.rooms[targetRoomId] === 'object'
    )
      ? visualState.topology.rooms[targetRoomId]
      : {};
    const incomingRoom = newRoom && typeof newRoom === 'object' ? newRoom : {};
    const mergedRoom = {
      ...previousRoom,
      ...incomingRoom,
      room_id: targetRoomId,
    };
    if (!String(mergedRoom.name || '').trim()) {
      mergedRoom.name = roomNameHint || targetRoomId;
    }
    visualState.topology.rooms[targetRoomId] = mergedRoom;

    if (!visualState.occupants || typeof visualState.occupants !== 'object') {
      visualState.occupants = {};
    }
    if (!Array.isArray(visualState.occupants.party)) {
      visualState.occupants.party = [];
    }
    if (!Array.isArray(visualState.occupants.entities)) {
      visualState.occupants.entities = [];
    }

    const roomOccupants = (Array.isArray(hexmap.dungeonData?.entities) ? hexmap.dungeonData.entities : [])
      .map((entity) => this._buildVisualOccupantFromEntity(entity, targetRoomId, entryHex))
      .filter(Boolean);
    visualState.occupants.entities = [
      ...visualState.occupants.entities.filter((occupant) => String(occupant?.room_id || '') !== targetRoomId),
      ...roomOccupants,
    ];

    if (visualState.occupants.party.length === 0) {
      const synthesizedParty = (Array.isArray(hexmap.dungeonData?.entities) ? hexmap.dungeonData.entities : [])
        .map((entity) => this._buildVisualOccupantFromEntity(entity, targetRoomId, entryHex, { forceParty: true }))
        .filter(Boolean);
      if (synthesizedParty.length > 0) {
        visualState.occupants.party = synthesizedParty;
      }
    }

    const partyOffsets = [
      { q: 0, r: 0 },
      { q: 1, r: 0 }, { q: -1, r: 0 }, { q: 0, r: 1 }, { q: 0, r: -1 }, { q: 1, r: -1 }, { q: -1, r: 1 },
      { q: 2, r: 0 }, { q: -2, r: 0 }, { q: 0, r: 2 }, { q: 0, r: -2 }, { q: 2, r: -1 }, { q: -2, r: 1 },
    ];
    visualState.occupants.party = visualState.occupants.party.map((occupant, index) => {
      const offset = partyOffsets[index] || partyOffsets[index % partyOffsets.length];
      return {
        ...occupant,
        room_id: targetRoomId,
        placement: {
          ...(occupant?.placement || {}),
          q: Number(entryHex?.q || 0) + offset.q,
          r: Number(entryHex?.r || 0) + offset.r,
        },
      };
    });
  }

  _buildAuthoritativeRoomExitsFromTopology(roomId, topologyConnections = []) {
    const normalizedRoomId = String(roomId || '').trim();
    if (!normalizedRoomId || !Array.isArray(topologyConnections)) {
      return [];
    }

    const exits = [];
    const seen = new Set();
    topologyConnections.forEach((connection) => {
      if (!connection || typeof connection !== 'object') {
        return;
      }
      const fromRoomId = String(connection?.from_room || connection?.from_room_id || '').trim();
      const toRoomId = String(connection?.to_room || connection?.to_room_id || '').trim();
      const bidirectional = Object.prototype.hasOwnProperty.call(connection, 'bidirectional')
        ? Boolean(connection.bidirectional)
        : true;

      const forward = fromRoomId === normalizedRoomId;
      const reverse = bidirectional && toRoomId === normalizedRoomId;
      if (!forward && !reverse) {
        return;
      }

      const targetRoomId = forward ? toRoomId : fromRoomId;
      if (!targetRoomId) {
        return;
      }

      const connectionId = String(
        connection?.connection_id
        || `${fromRoomId || normalizedRoomId}__${toRoomId || targetRoomId}`
      ).trim();
      const dedupeKey = `${connectionId}:${targetRoomId}`;
      if (!connectionId || seen.has(dedupeKey)) {
        return;
      }
      seen.add(dedupeKey);

      const isDiscovered = Object.prototype.hasOwnProperty.call(connection, 'is_discovered')
        ? Boolean(connection.is_discovered)
        : true;
      const isPassable = Object.prototype.hasOwnProperty.call(connection, 'is_passable')
        ? Boolean(connection.is_passable)
        : true;
      const blockedReason = String(connection?.blocked_reason || '').trim();
      const available = typeof connection?.available === 'boolean'
        ? connection.available
        : (blockedReason === '' && isDiscovered && isPassable);
      const fromHex = connection?.from_hex || connection?.from || null;
      const toHex = connection?.to_hex || connection?.to || null;

      exits.push({
        connection_id: connectionId,
        origin_room_id: normalizedRoomId,
        target_room_id: targetRoomId,
        target_room_name: String(
          forward
            ? (connection?.to_room_name || '')
            : (connection?.from_room_name || '')
        ).trim(),
        destination_type: String(connection?.destination_type || 'room').trim().toLowerCase() || 'room',
        destination_id: String(connection?.destination_id || targetRoomId).trim() || targetRoomId,
        type: String(connection?.type || 'passage').trim() || 'passage',
        available,
        blocked_reason: blockedReason || (available ? null : 'blocked'),
        is_discovered: isDiscovered,
        is_passable: isPassable,
        bidirectional,
        requires_interaction: Object.prototype.hasOwnProperty.call(connection, 'requires_interaction')
          ? Boolean(connection.requires_interaction)
          : !isPassable,
        origin_hex: forward ? fromHex : toHex,
        target_hex: forward ? toHex : fromHex,
      });
    });

    return exits;
  }

  _buildVisualOccupantFromEntity(entity, targetRoomId, entryHex, options = {}) {
    if (!entity || typeof entity !== 'object') {
      return null;
    }
    const placement = entity?.placement || {};
    const hex = placement?.hex || {};
    const roomId = String(placement?.room_id || '').trim();
    if (roomId !== String(targetRoomId || '').trim()) {
      return null;
    }

    const metadata = entity?.state?.metadata || {};
    const rawType = String(entity?.entity_type || '').trim().toLowerCase();
    const occupantId = String(entity?.instance_id || entity?.entity_instance_id || entity?.id || '').trim();
    if (!occupantId) {
      return null;
    }

    const forceParty = options?.forceParty === true;
    const normalizedType = rawType === 'player' ? 'player_character' : (rawType || 'npc');
    if (!forceParty && !['npc', 'player_character', 'player'].includes(rawType)) {
      return null;
    }
    if (forceParty && !this._isPartyRuntimeEntity(entity)) {
      return null;
    }

    const occupant = {
      occupant_id: occupantId,
      occupant_type: normalizedType,
      room_id: String(targetRoomId || '').trim(),
      content_id: String(entity?.entity_ref?.content_id || '').trim(),
      label: String(
        metadata?.display_name
        || metadata?.name
        || entity?.display_name
        || entity?.name
        || occupantId
      ).trim(),
      character_id: Number(metadata?.character_id || entity?.character_id || 0) || null,
      placement: {
        q: Number(hex?.q || entryHex?.q || 0),
        r: Number(hex?.r || entryHex?.r || 0),
        orientation: String(placement?.orientation || metadata?.orientation || 'n').trim().toLowerCase() || 'n',
      },
      visible: entity?.state?.hidden !== true,
      is_party: forceParty ? true : undefined,
      state: entity?.state || {},
      presentation: {
        portrait_url: metadata?.portrait_url || metadata?.portrait || null,
        role: String(metadata?.role || '').trim(),
        badge: String(metadata?.team || '').trim(),
        sprite_id: String(metadata?.sprite_id || '').trim() || null,
      },
    };
    if (forceParty) {
      occupant.is_party = true;
    }
    return occupant;
  }

  _isPartyRuntimeEntity(entity) {
    const metadata = entity?.state?.metadata || {};
    const rawType = String(entity?.entity_type || '').trim().toLowerCase();
    const team = String(metadata?.team || '').trim().toLowerCase();
    const followerKind = String(metadata?.follower_kind || '').trim().toLowerCase();
    const role = String(metadata?.role || '').trim().toLowerCase();
    return rawType === 'player_character'
      || rawType === 'player'
      || team === 'player'
      || team === 'ally'
      || followerKind === 'familiar'
      || role === 'familiar';
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
      const bundle = await this.shell.loadRuntimeStateBundle(
        this.shell.buildRuntimeBundleQueryForRoom(roomId, {
          mapId: mapId || undefined,
          dungeonLevelId: dungeonLevelId || undefined,
          nextRoomId: nextRoomId || undefined,
          startQ: 0,
          startR: 0,
        }),
      );
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
      const response = await fetch(`/api/campaign/${campaignId}/navigation/locations/request`, {
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
      if (!response.ok || !payload?.success) {
        console.error('[Navigation] requestInSessionDestination failed', {
          campaignId,
          destination,
          originRoomId,
          status: response.status,
          payload,
          fallbackRoomId,
          fallbackMapId,
          fallbackDungeonLevelId,
        });
      }
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

  async _reconcileAuthoritativeStateAfterTransition(coordinator, hexmap, optimisticRoomId = '') {
    const expectedRoomId = String(optimisticRoomId || '').trim();
    if (typeof this.shell?.syncCoordinatorStateFromServer === 'function') {
      return this.shell.syncCoordinatorStateFromServer(expectedRoomId);
    }
    if (!coordinator?.api?.getState) {
      return false;
    }
    try {
      const state = await coordinator.api.getState();
      const serverRoomId = String(
        state?.active_room_id
        ?? state?.game_state?.active_room_id
        ?? coordinator?.phaseManager?.activeRoomId
        ?? ''
      ).trim();
      if (state?.success && (!serverRoomId || !expectedRoomId || serverRoomId === expectedRoomId)) {
        coordinator.applyAuthoritativeUpdate?.(state);
        return true;
      }
      if (!serverRoomId || !expectedRoomId || serverRoomId === expectedRoomId) {
        return false;
      }
      // Do not force a room rollback from reconcile polling; transition receipts
      // are authoritative for room projection and this poll can lag briefly.
      console.warn('[Navigation] reconcile mismatch ignored to avoid stale room rollback', {
        expectedRoomId,
        serverRoomId,
      });
      return false;
    } catch (error) {
      console.warn('[Navigation] reconcile authoritative state after transition failed', error);
      return false;
    }
  }

  async _getAuthoritativeCoordinatorState(coordinator, hexmap = null) {
    if (!coordinator?.api?.getState) {
      return {
        stateVersion: coordinator?.phaseManager?.stateVersion ?? null,
        activeRoomId: coordinator?.phaseManager?.activeRoomId ?? hexmap?.resolveActiveRoomId?.() ?? null,
        turnEntity: coordinator?.phaseManager?.turn?.entity ?? null,
      };
    }
    try {
      const state = await coordinator.api.getState();
      if (state?.success) {
        coordinator.applyAuthoritativeUpdate?.(state);
      }
      return {
        stateVersion: Number(state?.state_version ?? coordinator?.phaseManager?.stateVersion ?? 0) || 0,
        activeRoomId: String(
          state?.active_room_id
          ?? state?.game_state?.active_room_id
          ?? coordinator?.phaseManager?.activeRoomId
          ?? hexmap?.resolveActiveRoomId?.()
          ?? ''
        ).trim() || null,
        turnEntity: String(
          state?.turn?.entity
          ?? state?.game_state?.turn?.entity
          ?? coordinator?.phaseManager?.turn?.entity
          ?? ''
        ).trim() || null,
      };
    } catch (error) {
      console.warn('[Navigation] failed to fetch authoritative state before transition', error);
      return {
        stateVersion: coordinator?.phaseManager?.stateVersion ?? null,
        activeRoomId: coordinator?.phaseManager?.activeRoomId ?? hexmap?.resolveActiveRoomId?.() ?? null,
        turnEntity: coordinator?.phaseManager?.turn?.entity ?? null,
      };
    }
  }

  _beginActionRailRequest(button) {
    const hasActionRail = !!this.shell?.panels?.actionRail;
    console.log('[Navigation] _beginActionRailRequest', { hasActionRail, isHTMLButton: button instanceof HTMLButtonElement });
    const result = this.shell.panels.actionRail?.beginActionRailRequest(button) ?? false;
    if (!result) {
      console.warn('[Navigation] _beginActionRailRequest: returned false', { hasActionRail, isHTMLButton: button instanceof HTMLButtonElement, pending: button?.dataset?.actionRailPending });
      return false;
    }
    this._beginActionRailPendingChatRequest(button);
    return result;
  }

  _endActionRailRequest(button) {
    this._settleActionRailPendingChatRequest(button);
    this.shell.panels.actionRail?.endActionRailRequest(button);
  }

  _getActionRailContext() {
    return this.shell.panels.actionRail?.getActionRailContext() ?? {};
  }

  _refreshActionRail() {
    const actionRail = this.shell?.panels?.actionRail || null;
    if (typeof actionRail?.invalidateActionRail === 'function') {
      actionRail.invalidateActionRail(['navigation', 'room', 'header']);
      return;
    }
    if (typeof actionRail?.queueActionRailRefresh === 'function') {
      actionRail.queueActionRailRefresh();
      return;
    }
    actionRail?.refreshActionRail?.();
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

  _handleRuntimeBundleLoadFailure(error, roomName = '', roomId = '') {
    const status = Number(error?.status || 0);
    const code = String(error?.code || '').trim().toLowerCase();
    const retryAfter = Number(error?.retryAfter || 0) || 0;
    const destination = String(roomName || roomId || 'destination').trim();

    let message = String(error?.message || 'Unable to load runtime state for this destination.').trim();
    if (status === 503 && code === 'launch_slice_not_ready') {
      message = retryAfter > 0
        ? `Destination ${destination} is still provisioning (${retryAfter}s). Please retry in a moment.`
        : `Destination ${destination} is still provisioning. Please retry in a moment.`;
    }

    this.bus.emit('game:server-unavailable', { message });
    this._appendChatLine('System', message, 'error');
  }

  _beginActionRailPendingChatRequest(button) {
    const requestId = String(button?.dataset?.backendRequestId || '').trim();
    if (!requestId || this._actionRailPendingRequests.has(requestId)) {
      return;
    }

    const chatPanel = this.shell?.panels?.chat || null;
    if (!chatPanel || typeof chatPanel.buildPendingChatRequest !== 'function') {
      return;
    }

    const context = this._getActionRailContext();
    const runtimeContext = context?.runtimeContext || {};
    const roomId = String(runtimeContext.roomId || context?.hexmap?.resolveActiveRoomId?.() || '').trim();
    if (!roomId) {
      return;
    }
    const campaignId = Number(runtimeContext.campaignId || context?.hexmap?.resolveCampaignId?.() || 0) || null;
    const characterId = Number(context?.characterId || runtimeContext.characterId || 0) || null;
    const target = chatPanel.buildChatRenderTarget?.({
      view: 'room',
      channelKey: 'room',
      context: {
        campaignId,
        roomId,
        characterId,
      },
    }) || {
      view: 'room',
      channelKey: 'room',
      context: { campaignId, roomId, characterId },
    };

    const pending = chatPanel.buildPendingChatRequest(requestId, 'System', '', roomId, {
      includePlayer: false,
      includePlaceholder: true,
      placeholderSpeaker: 'System',
      placeholderType: 'system',
      placeholderText: '⏳ Server response pending...',
      target,
    });
    if (pending) {
      this._actionRailPendingRequests.set(requestId, pending);
    }
  }

  _settleActionRailPendingChatRequest(button) {
    const requestId = String(button?.dataset?.backendRequestId || '').trim();
    if (!requestId) {
      return;
    }

    const chatPanel = this.shell?.panels?.chat || null;
    const pending = this._actionRailPendingRequests.get(requestId)
      || chatPanel?.pendingChatRequests?.get?.(requestId)
      || null;

    if (!pending) {
      this._actionRailPendingRequests.delete(requestId);
      return;
    }

    if (pending.gmProgressLineId) {
      if (chatPanel?.isChatTargetVisible?.(pending.target)) {
        chatPanel?.removeChatLineById?.(pending.gmProgressLineId);
      } else {
        chatPanel?.removeRememberedChatLineById?.(pending.target, pending.gmProgressLineId);
      }
      pending.gmProgressLineId = '';
      pending.progressLineIds = [];
    }

    chatPanel?.settlePendingChatRequest?.(pending, {
      removePlayer: false,
    });
    this._actionRailPendingRequests.delete(requestId);
  }

  _persistPartyLocationAfterTransition(hexmap, roomId, entryHex) {
    if (!hexmap || !roomId || !entryHex) {
      return;
    }
    const baseQ = Number(entryHex?.q);
    const baseR = Number(entryHex?.r);
    if (!Number.isFinite(baseQ) || !Number.isFinite(baseR)) {
      return;
    }
    const offsets = [
      { q: 0, r: 0 },
      { q: 1, r: 0 }, { q: -1, r: 0 }, { q: 0, r: 1 }, { q: 0, r: -1 }, { q: 1, r: -1 }, { q: -1, r: 1 },
    ];
    const entities = Array.isArray(hexmap?.dungeonData?.entities) ? hexmap.dungeonData.entities : [];
    const partyEntities = entities.filter((entity) => {
      const rawType = String(entity?.entity_type || '').trim().toLowerCase();
      const metadata = entity?.state?.metadata || {};
      const team = String(metadata?.team || '').trim().toLowerCase();
      return rawType === 'player_character'
        || rawType === 'player'
        || team === 'player'
        || team === 'ally';
    });
    partyEntities.forEach((entity, index) => {
      const offset = offsets[index] || offsets[index % offsets.length];
      const q = baseQ + offset.q;
      const r = baseR + offset.r;
      const entityRef = String(entity?.instance_id || entity?.entity_instance_id || entity?.id || '').trim();
      if (!entityRef) {
        return;
      }
      entity.placement = {
        ...(entity?.placement || {}),
        room_id: roomId,
        hex: { q, r },
      };
      hexmap.persistLaunchLocationContext?.(roomId, q, r, entityRef);
    });
  }

}
