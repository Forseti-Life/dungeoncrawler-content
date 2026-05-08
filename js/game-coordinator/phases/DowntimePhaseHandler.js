/**
 * @file
 * DowntimePhaseHandler — client-side stub for the downtime phase.
 *
 * Downtime is the between-adventure phase. For now, only long rest is
 * supported from the client. Full downtime activities (craft, earn income,
 * retrain) are deferred to a future sprint.
 */

export class DowntimePhaseHandler {
  /**
   * @param {object} deps
   * @param {import('./GameCoordinatorApi').GameCoordinatorApi} deps.api
   * @param {import('./PhaseManager').PhaseManager} deps.phaseManager
   * @param {object} deps.hexmap
   */
  constructor(deps) {
    this.api = deps.api;
    this.phaseManager = deps.phaseManager;
    this.hexmap = deps.hexmap;
  }

  getPhaseName() {
    return 'downtime';
  }

  /**
   * Hex clicks during downtime are not meaningful (no combat, no movement).
   * @returns {boolean}
   */
  handleHexClick() {
    return false;
  }

  /**
   * Perform a long rest.
   * @param {object} selectedEntity
   * @returns {Promise<object|null>}
   */
  async performLongRest(selectedEntity) {
    const actorId = selectedEntity?.dcEntityRef || selectedEntity?.dcEntityInstanceId;
    if (!actorId) return null;

    try {
      const result = await this.api.rest(actorId, 'long', this.phaseManager.stateVersion);
      if (result?.success && result.game_state) {
        this.phaseManager.applyServerState(result.game_state, result.available_actions);
      }
      return result;
    } catch (err) {
      console.error('[DowntimePhaseHandler] Long rest failed:', err);
      return null;
    }
  }
}

export default DowntimePhaseHandler;
