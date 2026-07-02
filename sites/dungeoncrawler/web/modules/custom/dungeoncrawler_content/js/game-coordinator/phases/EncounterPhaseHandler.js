/**
 * @file
 * EncounterPhaseHandler — client-side handler for the encounter (combat) phase.
 *
 * Wraps the existing CombatSystem, TurnManagementSystem, and combat API
 * calls, routing them through the unified GameCoordinatorApi. In encounter
 * mode:
 *  - Click hostile entity → strike (1 action)
 *  - Click empty hex → stride (1 action, movement cost)
 *  - End turn button → end_turn action (advances initiative)
 *  - Interact + target → interact (1 action)
 *  - Talk → talk (0 actions, free action)
 *
 * The server enforces turn order, action economy (3 actions/turn), and MAP.
 * The client provides immediate visual feedback then reconciles with the
 * server response.
 */

export class EncounterPhaseHandler {
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
    return 'encounter';
  }

  // =========================================================================
  // Hex Click Routing
  // =========================================================================

  /**
   * Handle a hex click during encounter.
   * Returns true if the click was consumed, false to fall through.
   *
   * @param {number} q
   * @param {number} r
   * @param {object|null} selectedEntity
   * @param {string} actionMode
   * @returns {boolean}
   */
  handleHexClick(q, r, selectedEntity, actionMode) {
    if (!selectedEntity) return false;

    // Validate it's the player's turn.
    const actorInstanceId = this._getEntityInstanceId(selectedEntity);
    if (!this._isPlayerTurn(selectedEntity)) {
      console.info('[EncounterPhaseHandler] Not your turn.');
      return false;
    }

    // Check entity at hex.
    const entityAtHex = this._findEntityAtHex(q, r);

    // 1. Attack mode + hostile target → Strike.
    if (entityAtHex && actionMode === 'attack' && entityAtHex.id !== selectedEntity.id) {
      if (this._isHostile(selectedEntity, entityAtHex)) {
        this._handleStrike(selectedEntity, entityAtHex);
        return true;
      }
    }

    // 2. Interact mode + target entity → Interact.
    if (entityAtHex && actionMode === 'interact' && entityAtHex.id !== selectedEntity.id) {
      this._handleInteract(selectedEntity, entityAtHex, q, r);
      return true;
    }

    // 3. Talk mode + NPC → Talk (free action).
    if (entityAtHex && actionMode === 'talk' && entityAtHex.id !== selectedEntity.id) {
      const identity = entityAtHex.getComponent?.('IdentityComponent');
      if (identity?.entityType === 'npc' || identity?.entityType === 'NPC') {
        this._handleTalk(selectedEntity, entityAtHex);
        return true;
      }
    }

    // 4. Click entity → select it (if it's a player entity).
    if (entityAtHex && entityAtHex.hasComponent('MovementComponent')) {
      this.hexmap.selectEntity(entityAtHex);
      return true;
    }

    // 5. Move mode + empty hex → Stride (1 action).
    if (actionMode === 'move') {
      return this._handleStride(selectedEntity, q, r);
    }

    return false;
  }

  // =========================================================================
  // Action Methods
  // =========================================================================

  /**
   * End the current turn.
   * @param {object} selectedEntity
   * @returns {Promise<object|null>}
   */
  async performEndTurn(selectedEntity) {
    const actorId = this._getEntityInstanceId(selectedEntity);
    if (!actorId) return null;

    try {
      const result = await this.api.endTurn(actorId, this.phaseManager.stateVersion);
      if (result?.success) {
        this._applyResult(result);
        this._syncTurnManagement(result);
      }
      return result;
    } catch (err) {
      console.error('[EncounterPhaseHandler] End turn failed:', err);
      return null;
    }
  }

  /**
   * Cast a spell.
   * @param {object} selectedEntity
   * @param {string} [targetEntityId]
   * @param {object} [spellParams={}]
   * @returns {Promise<object|null>}
   */
  async performCastSpell(selectedEntity, targetEntityId, spellParams = {}) {
    const actorId = this._getEntityInstanceId(selectedEntity);
    if (!actorId) return null;

    try {
      const result = await this.api.castSpell(actorId, targetEntityId, spellParams, this.phaseManager.stateVersion);
      if (result?.success) {
        this._applyResult(result);
        this._syncTurnManagement(result);
      }
      return result;
    } catch (err) {
      console.error('[EncounterPhaseHandler] Cast spell failed:', err);
      return null;
    }
  }

  // =========================================================================
  // Internal Handlers
  // =========================================================================

  /**
   * @private
   */
  async _handleStrike(attacker, target) {
    const attackerId = this._getEntityInstanceId(attacker);
    const targetId = this._getEntityInstanceId(target);
    if (!attackerId || !targetId) return;

    // Use existing combat system for immediate visual feedback.
    const canAttack = this.hexmap.combatSystem?.canAttack(attacker, target);
    if (canAttack && !canAttack.canAttack) {
      console.warn('[EncounterPhaseHandler] Cannot attack:', canAttack.reason);
      return;
    }

    try {
      const result = await this.api.strike(attackerId, targetId, {}, this.phaseManager.stateVersion);
      if (result?.success) {
        this._applyResult(result);
        this._syncTurnManagement(result);

        // Apply combat result visuals.
        const actionResult = result.action_result || {};
        this._applyCombatVisuals(attacker, target, actionResult);
      } else {
        console.warn('[EncounterPhaseHandler] Strike failed:', result?.error);
      }
    } catch (err) {
      console.error('[EncounterPhaseHandler] Strike API error:', err);
      this.hexmap.notifyServerUnavailable?.();
    }
  }

  /**
   * @private
   */
  async _handleStride(selectedEntity, q, r) {
    const actorId = this._getEntityInstanceId(selectedEntity);
    if (!actorId) return false;

    // Check movement range.
    const movementRange = this.hexmap.stateManager.get('movementRange');
    const hexKey = `${q}_${r}`;
    if (movementRange && !movementRange.has(hexKey)) {
      return false;
    }

    // Move locally for immediate feedback.
    const success = this.hexmap.movementSystem?.moveEntity(selectedEntity, q, r);
    if (!success) return false;

    this.hexmap.showMovementRange(selectedEntity);
    this.hexmap.refreshFogOfWar?.();

    try {
      const result = await this.api.stride(actorId, q, r, this.phaseManager.stateVersion);
      if (result?.success) {
        this._applyResult(result);
        this._syncTurnManagement(result);
      }
    } catch (err) {
      console.error('[EncounterPhaseHandler] Stride API error:', err);
    }

    return true;
  }

  /**
   * @private
   */
  async _handleInteract(selectedEntity, targetEntity, q, r) {
    // Delegate to hexmap's existing interact logic for quests/doors.
    this.hexmap.performInteractAtHex(selectedEntity, q, r, targetEntity);

    const actorId = this._getEntityInstanceId(selectedEntity);
    const targetId = this._getEntityInstanceId(targetEntity);
    if (actorId && targetId) {
      try {
        const result = await this.api.interact(actorId, targetId, 'generic', this.phaseManager.stateVersion);
        if (result?.success) {
          this._applyResult(result);
          this._syncTurnManagement(result);
        }
      } catch (err) {
        console.warn('[EncounterPhaseHandler] Interact API error:', err.message);
      }
    }
  }

  /**
   * @private
   */
  _handleTalk(selectedEntity, targetEntity) {
    const identity = selectedEntity.getComponent('IdentityComponent');
    const targetIdentity = targetEntity.getComponent?.('IdentityComponent');

    window.dispatchEvent(new CustomEvent('dungeoncrawler:talk', {
      detail: {
        entityId: selectedEntity.id,
        name: identity?.name || `Entity ${selectedEntity.id}`,
        targetId: targetEntity.id,
        targetName: targetIdentity?.name || 'NPC',
        roomId: this.hexmap.activeRoomId || null,
        phase: 'encounter',
      },
    }));

    // Talk is a free action in encounter.
    const actorId = this._getEntityInstanceId(selectedEntity);
    if (actorId) {
      this.api.talk(actorId, this._getEntityInstanceId(targetEntity), '', this.phaseManager.stateVersion)
        .catch(err => console.warn('[EncounterPhaseHandler] Talk notify failed:', err.message));
    }
  }

  // =========================================================================
  // Combat Visuals Bridge
  // =========================================================================

  /**
   * Apply visual effects from a combat action result.
   * @private
   */
  _applyCombatVisuals(attacker, target, actionResult) {
    if (!actionResult) return;

    const roll = actionResult.roll;
    const degree = actionResult.degree;
    const damage = actionResult.damage;

    // Update target HP via existing combat system.
    if (damage && damage.total > 0) {
      const stats = target.getComponent?.('StatsComponent');
      if (stats) {
        stats.currentHp = Math.max(0, (stats.currentHp || stats.hp) - damage.total);
      }
    }

    // Update action economy display.
    this._updateActionDisplay(attacker);

    // Trigger visual feedback (damage numbers, hit flash, etc.).
    if (this.hexmap.renderSystem?.showDamageNumber) {
      const pos = target.getComponent('PositionComponent');
      if (pos) {
        this.hexmap.renderSystem.showDamageNumber(pos.q, pos.r, damage?.total || 0, degree);
      }
    }
  }

  /**
   * @private
   */
  _updateActionDisplay(entity) {
    const actions = entity.getComponent?.('ActionsComponent');
    const movement = entity.getComponent?.('MovementComponent');
    const combat = entity.getComponent?.('CombatComponent');
    const identity = entity.getComponent?.('IdentityComponent');
    const name = identity ? identity.name : `Entity ${entity.id}`;
    const isPlayersTurn = combat?.isPlayerTeam?.() || combat?.team === 'player';

    if (actions && movement && this.hexmap.uiManager) {
      this.hexmap.uiManager.updateCurrentTurn(name, actions, movement, actions.hasReactionAvailable?.(), combat?.team, isPlayersTurn);
    }
  }

  // =========================================================================
  // State Sync
  // =========================================================================

  /**
   * Sync the existing TurnManagementSystem with server state.
   * @private
   */
  _syncTurnManagement(result) {
    const tms = this.hexmap.turnManagementSystem;
    if (!tms || typeof tms.hydrateFromServer !== 'function') return;

    // Build a server state shape that TurnManagementSystem expects.
    const gameState = result.game_state || {};
    const serverPayload = {
      encounter_id: gameState.encounter_id,
      initiative_order: gameState.initiative_order || [],
      current_round: gameState.round,
      turn_index: gameState.turn?.index,
    };

    this.hexmap.stateManager.set('serverCombatMode', true);
    tms.hydrateFromServer(serverPayload);
    this.hexmap.syncSelectedToCurrentTurn?.();
  }

  /**
   * Apply server result to the phase manager and check for phase transitions.
   * @private
   */
  _applyResult(result) {
    if (result.game_state) {
      this.phaseManager.applyServerState(result.game_state, result.available_actions);
    }

    // Check if encounter ended (phase transitioned back to exploration).
    if (result.phase_transition) {
      console.info('[EncounterPhaseHandler] Phase transition detected:', result.phase_transition);
    }
  }

  // =========================================================================
  // Helpers
  // =========================================================================

  /**
   * @private
   */
  _isPlayerTurn(entity) {
    const combat = entity.getComponent?.('CombatComponent');
    const isPlayer = combat?.isPlayerTeam?.() || combat?.team === 'player';
    if (!isPlayer) return false;

    // Check against phase manager's turn state.
    const entityInstanceId = this._getEntityInstanceId(entity);
    if (entityInstanceId && this.phaseManager.turn) {
      return this.phaseManager.isEntityTurn(entityInstanceId);
    }

    // Fallback to existing TurnManagementSystem.
    return this.hexmap.turnManagementSystem?.isEntityTurn(entity) ?? true;
  }

  /**
   * @private
   */
  _isHostile(entity, target) {
    const aCombat = entity.getComponent?.('CombatComponent');
    const bCombat = target.getComponent?.('CombatComponent');
    if (aCombat && bCombat && typeof aCombat.isHostileTo === 'function') {
      return aCombat.isHostileTo(bCombat);
    }
    return false;
  }

  /**
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
   * @private
   */
  _getEntityInstanceId(entity) {
    return entity?.dcEntityRef || entity?.dcEntityInstanceId || null;
  }
}

export default EncounterPhaseHandler;
