/**
 * @file systems/EncounterSystem.js
 *
 * Bridges the ECS TurnManagementSystem + CombatSystem to the GameEventBus.
 *
 * Responsibilities:
 *   - Wire ECS turn/round/state callbacks → bus events
 *   - Expose startCombat / endCombat / endTurn for bus consumers
 *   - Forward attack and damage results onto the bus
 *
 * Fires bus events:
 *   combat:started           — encounter began
 *   combat:turn-changed      — { entity, turnIndex, totalTurns, initiativeOrder }
 *   combat:round-changed     — { roundNumber }
 *   combat:state-changed     — { state }  ('active'|'inactive'|'ended')
 *   combat:attack-performed  — { attacker, target, result }
 *   combat:damage-dealt      — { target, amount, remaining }
 *
 * Responds to bus events:
 *   user:combat-start  — begin encounter (startCombat)
 *   user:combat-end    — end encounter (endCombat)
 *   user:end-turn      — advance turn (endTurn)
 *   user:attack        — { attacker, target } perform attack
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

  init() {
    const { turnManagementSystem, combatSystem } = this.shell;

    // --- Wire ECS turn management callbacks → bus ---

    if (turnManagementSystem) {
      turnManagementSystem.onTurnChange((entity, turnIndex, totalTurns) => {
        const initiativeOrder = turnManagementSystem.getInitiativeOrder?.() ?? [];
        this.bus.emit('combat:turn-changed', { entity, turnIndex, totalTurns, initiativeOrder });
      });

      turnManagementSystem.onRoundChange((roundNumber) => {
        this.bus.emit('combat:round-changed', { roundNumber });
      });

      turnManagementSystem.onCombatStateChange((combatState) => {
        const state = this._normalizeState(combatState);
        this.bus.emit('combat:state-changed', { state });
        if (state === 'active') {
          this.bus.emit('combat:started');
        }
      });
    }

    // --- Wire ECS combat system callbacks → bus ---

    if (combatSystem) {
      combatSystem.onAttack?.((data) => {
        this.bus.emit('combat:attack-performed', {
          attacker: data.attacker,
          target:   data.target,
          result:   data.result,
        });
      });

      combatSystem.onDamage?.((data) => {
        const stats = data.target?.getComponent?.('StatsComponent');
        this.bus.emit('combat:damage-dealt', {
          target:    data.target,
          amount:    data.damage ?? data.amount ?? 0,
          remaining: stats?.currentHp ?? 0,
        });
      });
    }

    // --- Respond to player bus events ---

    this._unsubs.push(
      this.bus.on('user:combat-start', () => this.startCombat()),
      this.bus.on('user:combat-end',   () => this.endCombat()),
      this.bus.on('user:end-turn',     () => this.endTurn()),
      this.bus.on('user:attack',       ({ attacker, target } = {}) => {
        this._performAttack(attacker, target);
      }),
    );
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Public API (called via bus or GameShell)
  // ---------------------------------------------------------------------------

  /** Begin combat with entities currently in the room. */
  startCombat() {
    const { turnManagementSystem, entityManager } = this.shell;
    if (!turnManagementSystem) return;

    // Collect all combatants present in the room
    const combatants = entityManager
      ? entityManager.getAllEntities().filter((e) => e.getComponent?.('CombatComponent'))
      : [];

    turnManagementSystem.startCombat(combatants);
  }

  /** End combat and reset turn state. */
  endCombat() {
    this.shell.turnManagementSystem?.endCombat?.();
  }

  /** Advance to the next turn. */
  endTurn() {
    this.shell.turnManagementSystem?.endTurn?.();
  }

  // ---------------------------------------------------------------------------
  // Private
  // ---------------------------------------------------------------------------

  /**
   * Resolve and execute an attack through the ECS CombatSystem.
   * @param {object} attacker - ECS Entity
   * @param {object} target   - ECS Entity
   * @private
   */
  _performAttack(attacker, target) {
    const { combatSystem } = this.shell;
    if (!combatSystem || !attacker || !target) return;
    const check = combatSystem.canAttack?.(attacker, target);
    if (check && !check.canAttack) {
      this.bus.emit('combat:action-denied', { reason: check.reason });
      return;
    }
    combatSystem.makeAttack?.(attacker, target);
  }

  /**
   * Normalize ECS CombatState strings to the canonical bus values.
   * @param {string} ecsState
   * @returns {'active'|'inactive'|'ended'}
   * @private
   */
  _normalizeState(ecsState) {
    const s = String(ecsState ?? '').toLowerCase();
    if (s === 'inactive')   return 'inactive';
    if (s === 'ended')      return 'ended';
    // IN_PROGRESS / ROLLING_INITIATIVE → 'active'
    return 'active';
  }
}
