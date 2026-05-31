/**
 * @file systems/EncounterSystem.js
 *
 * Combat encounter state management.
 *
 * Responsibilities:
 *   - Encounter participant resolution (who is in this combat?)
 *   - Hostile detection and team assignment
 *   - Combat start controls and evaluation
 *   - Attack/damage event handling
 *
 * Fires bus events:
 *   combat:started           — encounter began
 *   combat:turn-changed      — { entity, turnIndex, totalTurns }
 *   combat:round-changed     — { roundNumber }
 *   combat:state-changed     — { state }  (active/inactive/ended)
 *   combat:attack-performed  — { attacker, target, result }
 *   combat:damage-dealt      — { target, amount, remaining }
 *
 * Responds to bus events:
 *   user:combat-start  — begin encounter
 *   user:attack        — perform attack action
 *
 * Phase 4 implementation.
 */

export class EncounterSystem {
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
