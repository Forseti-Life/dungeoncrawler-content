/**
 * @file panels/CombatPanel.js
 *
 * Renders initiative tracker, current turn indicator, round counter,
 * and combat state controls (start/end combat).
 *
 * Subscribes to bus events:
 *   combat:turn-changed    — { entity, turnIndex, totalTurns }
 *   combat:round-changed   — { roundNumber }
 *   combat:state-changed   — { state } (active/inactive/ended)
 *
 * Fires bus events:
 *   user:combat-start  — player requests combat start
 *   user:combat-end    — player requests combat end
 *   user:end-turn      — player ends their turn
 *
 * Phase 4 implementation.
 */

export class CombatPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
  }
}
