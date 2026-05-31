/**
 * @file systems/PlayerAutomation.js
 *
 * Automated player turn sequencing.
 *
 * Handles the AI-assisted automated turn loop: profiling the player's
 * capabilities, advancing through turn steps, syncing with server state,
 * and handling room transitions triggered by automation.
 *
 * Fires bus events:
 *   automation:step-queued    — automation queued a turn step
 *   automation:step-complete  — step finished, result described
 *   automation:stopped        — automation halted (reason included)
 *
 * Responds to bus events:
 *   user:automation-start  — begin automated turn
 *   user:automation-stop   — halt automation
 *   combat:turn-changed    — check if it's player's automated turn
 *
 * Phase 3 of systems implementation (after EncounterSystem).
 */

export class PlayerAutomation {
  /**
   * @param {import('../GameShell').GameShell} shell
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(shell, bus) {
    this.shell = shell;
    this.bus = bus;
    this._unsubs = [];
    this._timer = null;
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
    if (this._timer) {
      clearTimeout(this._timer);
      this._timer = null;
    }
  }
}
