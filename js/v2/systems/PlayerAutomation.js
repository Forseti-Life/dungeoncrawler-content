/**
 * @file systems/PlayerAutomation.js
 *
 * Automated player turn sequencing.
 *
 * THIN ADAPTER: observes bus events for "is it the player's turn?" and, when
 * automation is active, calls the server API to execute the turn. No game
 * decision logic runs client-side — the server picks the action.
 *
 * Fires bus events:
 *   automation:started        — automation loop began
 *   automation:step-queued    — automation queued a turn step
 *   automation:step-complete  — step finished, result described
 *   automation:stopped        — automation halted (reason included)
 *
 * Responds to bus events:
 *   user:automation-start  — begin automated turn loop
 *   user:automation-stop   — halt automation
 *   combat:turn-changed    — { entity } check if it's the player's turn to automate
 */

/** Delay between automation steps (ms) — prevents API flooding. */
const AUTOMATION_STEP_DELAY_MS = 800;

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
    this._active = false;
  }

  init() {
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this.stop('destroyed');
  }

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  start() {
    if (this._active) return;
    this._active = true;
    this.bus.emit('automation:started');
  }

  stop(reason = 'stopped') {
    this._active = false;
    if (this._timer) { clearTimeout(this._timer); this._timer = null; }
    this.bus.emit('automation:stopped', { reason });
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('user:automation-start', () => this.start()),
      this.bus.on('user:automation-stop',  () => this.stop('user-request')),
      this.bus.on('combat:turn-changed',   (data) => this._onTurnChanged(data)),
    );
  }

  _onTurnChanged({ entity } = {}) {
    if (!this._active || !entity) return;
    // Only automate the player-team entity
    const isPlayer = entity.getComponent?.('CombatComponent')?.isPlayerTeam?.() ?? false;
    if (!isPlayer) return;

    const entityId = entity.getComponent?.('IdentityComponent')?.entityId ?? entity.id;
    this.bus.emit('automation:step-queued', { entityId });
    this._timer = setTimeout(() => this._executeStep(entity), AUTOMATION_STEP_DELAY_MS);
  }

  async _executeStep(entity) {
    this._timer = null;
    if (!this._active) return;

    try {
      const ctx = this.shell.launchContext ?? {};
      const encounterId = ctx.encounter_id;
      const actorId     = entity.getComponent?.('IdentityComponent')?.entityId ?? entity.id;

      if (typeof fetch !== 'undefined' && encounterId && actorId) {
        const mod = await import('../../hexmap-api.js').catch(() => null);
        if (mod?.default?.action) {
          await mod.default.action({
            encounterId,
            actorId,
            actionType: 'automate',
          });
        }
      }
      this.bus.emit('automation:step-complete', { entityId: entity?.id, result: 'ok' });
    } catch (err) {
      this.stop('error');
      this.bus.emit('game:error', { source: 'PlayerAutomation', error: err.message });
    }
  }
}

