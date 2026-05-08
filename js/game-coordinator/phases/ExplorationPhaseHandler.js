/**
 * @file
 * ExplorationPhaseHandler — client-side handler for the exploration phase.
 *
 * Routes hex clicks, button presses, and UI actions into the correct
 * exploration intents, then sends them to the server via GameCoordinatorApi.
 *
 * In exploration:
 *  - Click entity → select or interact/talk
 *  - Click empty hex → move selected entity (free movement, no action cost)
 *  - Click room transition hex → room transition
 *  - Search button → search action
 *  - Rest button → rest action
 *  - Talk + NPC → talk action
 */

export class ExplorationPhaseHandler {
  /**
   * @param {object} deps - Injected dependencies from GameCoordinator
   * @param {import('./GameCoordinatorApi').GameCoordinatorApi} deps.api
   * @param {import('./PhaseManager').PhaseManager} deps.phaseManager
   * @param {object} deps.hexmap - Reference to Drupal.behaviors.hexMap
   */
  constructor(deps) {
    this.api = deps.api;
    this.phaseManager = deps.phaseManager;
    this.hexmap = deps.hexmap;
  }

  /**
   * Get the phase name this handler manages.
   * @returns {string}
   */
  getPhaseName() {
    return 'exploration';
  }

  // =========================================================================
  // Hex Click Routing
  // =========================================================================

  /**
   * Handle a hex click during exploration.
   * Returns true if the click was consumed, false to fall through.
   *
   * @param {number} q - Hex Q coordinate
   * @param {number} r - Hex R coordinate
   * @param {object|null} selectedEntity - Currently selected ECS entity
   * @param {string} actionMode - Current action mode (move/attack/interact/talk)
   * @returns {boolean} Whether the click was consumed
   */
  handleHexClick(q, r, selectedEntity, actionMode) {
    // 1. Room transition check (always takes priority).
    if (this.hexmap.tryTransitionAtHex(q, r)) {
      this._notifyServer('room_transition', selectedEntity, { target_hex: { q, r } });
      return true;
    }

    // 2. Entity at hex — interact or talk.
    const entityAtHex = this._findEntityAtHex(q, r);
    if (entityAtHex && selectedEntity && entityAtHex.id !== selectedEntity.id) {
      if (actionMode === 'attack') {
        this._handleAggressiveAction(selectedEntity, entityAtHex);
        return true;
      }
      if (actionMode === 'interact') {
        return this._handleInteract(selectedEntity, entityAtHex, q, r);
      }
      if (actionMode === 'talk') {
        return this._handleTalk(selectedEntity, entityAtHex);
      }
    }

    // 3. Click on entity → select it.
    if (entityAtHex && entityAtHex.hasComponent('MovementComponent')) {
      this.hexmap.selectEntity(entityAtHex);
      return true;
    }

    // 4. Move selected entity (free movement in exploration).
    if (selectedEntity && actionMode === 'move') {
      return this._handleMove(selectedEntity, q, r);
    }

    // Not consumed — let hexmap handle default behavior.
    return false;
  }

  // =========================================================================
  // Action Methods (called from buttons or hexmap)
  // =========================================================================

  /**
   * Perform a search action.
   * @param {object} selectedEntity
   * @returns {Promise<object|null>}
   */
  async performSearch(selectedEntity) {
    if (!selectedEntity) return null;

    const actorId = this._getEntityInstanceId(selectedEntity);
    if (!actorId) return null;

    try {
      const result = await this.api.search(actorId, this.phaseManager.stateVersion);
      if (result?.success) {
        this._applyResult(result);
      }
      return result;
    } catch (err) {
      console.error('[ExplorationPhaseHandler] Search failed:', err);
      return null;
    }
  }

  /**
   * Perform a rest action.
   * @param {object} selectedEntity
   * @param {string} [restType='short']
   * @returns {Promise<object|null>}
   */
  async performRest(selectedEntity, restType = 'short') {
    if (!selectedEntity) return null;

    const actorId = this._getEntityInstanceId(selectedEntity);
    if (!actorId) return null;

    try {
      const result = await this.api.rest(actorId, restType, this.phaseManager.stateVersion);
      if (result?.success) {
        this._applyResult(result);
      }
      return result;
    } catch (err) {
      console.error('[ExplorationPhaseHandler] Rest failed:', err);
      return null;
    }
  }

  // =========================================================================
  // Internal
  // =========================================================================

  /**
   * Handle move in exploration (uses existing client-side A* then notifies server).
   * @private
   */
  _handleMove(selectedEntity, q, r) {
    // Use existing movement system for immediate client feedback.
    const movementRange = this.hexmap.stateManager.get('movementRange');
    const hexKey = `${q}_${r}`;

    // In exploration, allow move even without checking range (free movement).
    const success = this.hexmap.movementSystem.moveEntity(selectedEntity, q, r);
    if (success) {
      this.hexmap.showMovementRange(selectedEntity);
      this.hexmap.refreshFogOfWar();

      // Notify server of move asynchronously (non-blocking).
      this._notifyServer('move', selectedEntity, { target_hex: { q, r } });
      return true;
    }
    return false;
  }

  /**
   * Handle interact action.
   * @private
   */
  _handleInteract(selectedEntity, targetEntity, q, r) {
    // Delegate to hexmap's existing interact logic first for quests/doors/etc.
    if (this.hexmap.performInteractAtHex(selectedEntity, q, r, targetEntity)) {
      // Also notify the game coordinator server.
      const targetId = this._getEntityInstanceId(targetEntity);
      if (targetId) {
        this._notifyServer('interact', selectedEntity, {}, targetId);
      }
      return true;
    }
    return false;
  }

  /**
   * Handle talk action (exploration talk is free — delegates to chat).
   * @private
   */
  _handleTalk(selectedEntity, targetEntity) {
    const targetIdentity = targetEntity.getComponent?.('IdentityComponent');
    const targetType = targetIdentity?.entityType;

    // Only NPCs are talkable.
    if (targetType !== 'npc' && targetType !== 'NPC') {
      return false;
    }

    // Emit talk event for chat UI consumers.
    const identity = selectedEntity.getComponent('IdentityComponent');
    window.dispatchEvent(new CustomEvent('dungeoncrawler:talk', {
      detail: {
        entityId: selectedEntity.id,
        name: identity?.name || `Entity ${selectedEntity.id}`,
        targetId: targetEntity.id,
        targetName: targetIdentity?.name || 'NPC',
        roomId: this.hexmap.activeRoomId || null,
        phase: 'exploration',
      },
    }));
    return true;
  }

  /**
   * Send a non-blocking notification to the game coordinator server.
   * Errors are logged but don't block client interaction.
   * @private
   */
  async _notifyServer(type, entity, params = {}, targetId = null) {
    const actorId = this._getEntityInstanceId(entity);
    if (!actorId) return;

    try {
      const options = { stateVersion: this.phaseManager.stateVersion };
      if (targetId) options.target = targetId;

      const result = await this.api.sendAction(type, actorId, params, options);
      if (result?.success) {
        this._applyResult(result);
      }
    } catch (err) {
      console.warn(`[ExplorationPhaseHandler] Server notification failed for '${type}':`, err.message);
    }
  }

  /**
   * Transition from exploration into encounter when the player commits to an
   * aggressive action.
   * @private
   */
  async _handleAggressiveAction(selectedEntity, targetEntity) {
    const actorId = this._getEntityInstanceId(selectedEntity);
    const targetId = this._getEntityInstanceId(targetEntity);
    if (!actorId || !targetId) {
      return null;
    }

    const actorIdentity = selectedEntity.getComponent?.('IdentityComponent');
    const targetIdentity = targetEntity.getComponent?.('IdentityComponent');
    const targetPosition = targetEntity.getComponent?.('PositionComponent');
    const roomId = targetPosition?.room_id || this.hexmap.activeRoomId || null;
    const reason = `${actorIdentity?.name || 'A combatant'} initiates an aggressive action against ${targetIdentity?.name || 'a target'}.`;

    try {
      const result = await this.api.transitionPhase('encounter', {
        reason,
        trigger_action: 'aggressive_action',
        aggressor: actorId,
        target: targetId,
        encounter_context: {
          room_id: roomId,
          enemies: [{ entity_instance_id: targetId }],
          reason,
        },
      });

      if (result?.success) {
        this.hexmap.stateManager?.set('actionMode', 'attack');
        this._applyResult(result);
      }

      return result;
    } catch (err) {
      console.error('[ExplorationPhaseHandler] Aggressive action transition failed:', err);
      return null;
    }
  }

  /**
   * Apply server result to the phase manager.
   * @private
   */
  _applyResult(result) {
    if (result.game_state) {
      this.phaseManager.applyServerState(result.game_state, result.available_actions);
    }
  }

  /**
   * Find an ECS entity at a hex position.
   * @private
   */
  _findEntityAtHex(q, r) {
    if (!this.hexmap.entityManager) return null;
    const entities = this.hexmap.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
    for (const entity of entities) {
      const pos = entity.getComponent('PositionComponent');
      if (pos.q === q && pos.r === r) {
        return entity;
      }
    }
    return null;
  }

  /**
   * Get the server-side entity_instance_id from an ECS entity.
   * @private
   */
  _getEntityInstanceId(entity) {
    return entity?.dcEntityRef || entity?.dcEntityInstanceId || null;
  }
}

export default ExplorationPhaseHandler;
