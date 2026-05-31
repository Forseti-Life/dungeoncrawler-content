/**
 * @file systems/NavigationSystem.js
 *
 * Room navigation and dungeon context transitions.
 *
 * THIN ADAPTER: translates user intent events into API calls, then emits
 * bus events based on server responses. No game-state logic client-side.
 *
 * Flow:
 *   user:navigate-to-room → API /api/combat/navigate → room:changed
 *   hex:clicked → check dungeon connections → user:navigate-to-room (if passable)
 *
 * Fires bus events:
 *   room:changing   — { fromRoomId, toRoomId } — transition about to occur
 *   room:changed    — { roomId, roomName, ... } — transition complete (server data)
 *
 * Responds to bus events:
 *   user:navigate-to-room    — { roomId, connectionId? }
 *   hex:clicked              — { q, r } — check for navigation at this hex
 */

export class NavigationSystem {
  /**
   * @param {import('../GameShell').GameShell} shell
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(shell, bus) {
    this.shell = shell;
    this.bus = bus;
    this._unsubs = [];
    this._navigating = false;
  }

  init() {
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('user:navigate-to-room', (data) => this._onNavigateToRoom(data)),
      this.bus.on('hex:clicked',           (data) => this._onHexClicked(data)),
    );
  }

  async _onNavigateToRoom({ roomId, connectionId } = {}) {
    if (!roomId || this._navigating) return;
    this._navigating = true;

    const fromRoomId = this.shell.activeRoomId;
    this.bus.emit('room:changing', { fromRoomId, toRoomId: roomId });

    try {
      const ctx = this.shell.launchContext ?? {};
      const payload = {
        campaignId:    ctx.campaign_id,
        characterId:   ctx.character_id,
        mapId:         ctx.map_id,
        currentRoomId: fromRoomId,
        connectionId:  connectionId ?? null,
        targetRoomId:  roomId,
      };

      let roomData = {};
      if (typeof fetch !== 'undefined') {
        const mod = await import('../../hexmap-api.js').catch(() => null);
        if (mod?.default?.navigate) {
          const result = await mod.default.navigate(payload);
          roomData = result?.room ?? {};
        }
      }

      this.shell.activeRoomId = roomId;
      this.bus.emit('room:changed', {
        roomId,
        roomName:      roomData.name ?? roomData.room_name ?? '',
        sceneImageUrl: roomData.scene_image_url ?? null,
        responders:    roomData.responders ?? [],
        occupants:     roomData.occupants ?? [],
        ...roomData,
      });
    } catch (err) {
      // Re-surface as a game:error — server handles recovery
      this.bus.emit('game:error', { source: 'NavigationSystem', error: err.message });
    } finally {
      this._navigating = false;
    }
  }

  _onHexClicked({ q, r } = {}) {
    // Check dungeon graph for a connection at this hex
    const dungeon = this.shell.dungeonData ?? {};
    const connections = dungeon.connections ?? dungeon.room_connections ?? [];
    const match = connections.find((c) => c.hex_q === q && c.hex_r === r);
    if (match?.target_room_id) {
      this.bus.emit('user:navigate-to-room', {
        roomId:       match.target_room_id,
        connectionId: match.connection_id ?? null,
      });
    }
  }
}

