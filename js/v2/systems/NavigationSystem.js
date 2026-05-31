/**
 * @file systems/NavigationSystem.js
 *
 * Room navigation and dungeon context transitions.
 *
 * Responsibilities:
 *   - Room transition execution (resolve entry hex, move entities, update state)
 *   - Navigation capability resolution per hex
 *   - Dungeon context switching (travel to different dungeon)
 *   - Visited room entry hex tracking
 *
 * Fires bus events:
 *   room:changing   — { fromRoomId, toRoomId } — transition about to occur
 *   room:changed    — { roomId, room }          — transition complete
 *
 * Responds to bus events:
 *   user:navigate-to-room    — { roomId }
 *   user:navigate-to-dungeon — { dungeonSwitch }
 *   hex:clicked              — check for navigation connection at hex
 *
 * Phase 1 of systems (initialized early — room transitions needed before combat).
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
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
  }
}
