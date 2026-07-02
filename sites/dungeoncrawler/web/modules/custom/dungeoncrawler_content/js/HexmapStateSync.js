/**
 * @file HexmapStateSync.js
 *
 * State adapter: polls the server for authoritative game state and reconciles
 * the client presentation cache. Contains NO UI code.
 *
 * Thin-client seam overview:
 *   Client intent → hexmap.js (presentation)
 *   Backend authority → HexmapStateSync → combatApi → Backend
 *   Backend result → HexmapStateSync.apply() → ECS (TurnManagementSystem) + UIManager
 *
 * Responsibilities:
 *   - Periodic server-state polling (start/stop/forceSync)
 *   - Applying authoritative state: initiative order, world delta, character state
 *
 * NOT responsible for:
 *   - Rendering or DOM manipulation (UIManager owns that)
 *   - Gameplay rules or client-originated mutations (GameCoordinator owns that)
 *   - Performing combat actions (hexmap-api.js and GameCoordinatorApi.js own that)
 */

import combatApi from './hexmap-api.js';

export class HexmapStateSync {
  /**
   * @param {object} hexmap - Reference to Drupal.behaviors.hexMap
   */
  constructor(hexmap) {
    this._hexmap = hexmap;
    this._timer = null;
    this._inFlight = false;
    this._failures = 0;
  }

  /**
   * Start periodic state polling. Runs an immediate sync then sets the interval.
   */
  start() {
    this.stop();
    const hm = this._hexmap;
    const intervalMs = Number(hm.config?.serverStateSyncIntervalMs || 3000);
    this._timer = setInterval(() => this.sync(), intervalMs);
    this.sync({ force: true, silent: true });
  }

  /**
   * Stop periodic state polling and reset in-flight state.
   */
  stop() {
    if (this._timer) {
      clearInterval(this._timer);
      this._timer = null;
    }
    this._inFlight = false;
    this._failures = 0;
  }

  /**
   * @returns {boolean} True if polling is active.
   */
  isActive() {
    return this._timer !== null;
  }

  /**
   * @returns {object} Payload for the getCurrentState API call.
   * @private
   */
  _buildPayload() {
    const hm = this._hexmap;
    return {
      campaignId: hm.resolveCampaignId?.() ?? null,
      roomId: hm.resolveActiveRoomId?.() ?? null,
      encounterId: hm.stateManager?.get('encounterId') || null,
      mapId: hm.stateManager?.get('mapId') || null,
    };
  }

  /**
   * Perform one server-state poll and apply the result.
   *
   * @param {boolean} [force=false] - Skip the in-flight guard.
   * @param {boolean} [silent=false] - Suppress the first-failure UI notification.
   * @returns {Promise<object|null>} Applied server state, or null on skip/error.
   */
  async sync({ force = false, silent = false } = {}) {
    const hm = this._hexmap;
    if (!hm.canUseServerCombatApi?.()) return null;
    if (this._inFlight && !force) return null;

    const payload = this._buildPayload();
    if (!payload.campaignId && !payload.encounterId) return null;

    this._inFlight = true;
    try {
      const serverState = await combatApi.getCurrentState(payload);
      this._failures = 0;

      if (!serverState || typeof serverState !== 'object') return null;

      if (serverState.success && serverState.data) {
        this.apply(serverState.data);
        return serverState.data;
      }

      this.apply(serverState);
      return serverState;
    } catch (err) {
      this._failures += 1;
      console.error('Current state sync failed:', err);
      if (!silent && this._failures === 1) {
        hm.notifyServerUnavailable?.();
      }
      return null;
    } finally {
      this._inFlight = false;
    }
  }

  /**
   * Reconcile authoritative server state onto the client presentation cache.
   *
   * This method contains ONLY state reconciliation — no UI rendering.
   * UI updates are triggered through the stateManager subscription model or
   * through UIManager methods that operate on already-updated ECS state.
   *
   * @param {object} serverState
   */
  apply(serverState = {}) {
    if (!serverState || typeof serverState !== 'object') return;
    const hm = this._hexmap;

    if (serverState.encounter_id) {
      hm.stateManager?.set('encounterId', serverState.encounter_id);
    }

    if (serverState.map_id) {
      hm.stateManager?.set('mapId', serverState.map_id);
    }

    // Hydrate turn/combat order (server is authoritative for initiative).
    if (typeof hm.turnManagementSystem?.hydrateFromServer === 'function'
        && Array.isArray(serverState.initiative_order)) {
      hm.stateManager?.set('serverCombatMode', true);
      if (hm.combatSystem && typeof hm.combatSystem.setServerResultRequirement === 'function') {
        hm.combatSystem.setServerResultRequirement(true);
      }
      hm.turnManagementSystem.hydrateFromServer(serverState);
      hm.syncSelectedToCurrentTurn?.();
    }

    // Apply authoritative world-state delta (open_passage, open_door, move_object, etc.).
    if (serverState.world_delta) {
      hm.applyWorldDelta?.(serverState.world_delta);
    }

    // Reconcile player character state from server (server is canonical for HP, XP, etc.).
    const characterState = serverState.character_state
      || serverState.player_character
      || serverState.current_character
      || null;
    if (characterState && hm.uiManager
        && typeof hm.uiManager.showLaunchCharacter === 'function') {
      hm.uiManager.showLaunchCharacter(characterState);
    }
  }
}

export default HexmapStateSync;
