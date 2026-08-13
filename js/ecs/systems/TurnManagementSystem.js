/**
 * @file TurnManagementSystem.js
 * System for managing turn order, initiative, and round progression.
 * 
 * SCHEMA CONFORMANCE NOTES (DCC-0252):
 * =====================================
 * This is a **client-side ECS system** that manages turn-based combat state.
 * It operates on runtime component data and can be hydrated from server-authoritative
 * state. Combat encounter state changes must be synchronized to the backend via API
 * calls to persist to database tables.
 * 
 * Database Tables (see dungeoncrawler_content.install):
 * ------------------------------------------------------
 * 
 * ### combat_encounters Table (Lines 179-238)
 * Tracks active and historical combat encounters with:
 * - id: Primary key (serial)
 * - campaign_id: Owning campaign (optional)
 * - room_id: Room identifier where combat occurs
 * - status: Encounter status (setup|active|paused|ended) - maps to TurnManagementSystem.combatState
 * - current_round: Current combat round - synced from TurnManagementSystem.currentRound
 * - turn_index: Current turn index in initiative order - synced from TurnManagementSystem.currentTurnIndex
 * - created/updated: Timestamps
 * 
 * ### combat_participants Table (Lines 240-357)
 * Participants within a combat encounter with:
 * - id: Primary key (serial)
 * - encounter_id: Linked encounter
 * - entity_id: External entity identifier from the map/ECS
 * - entity_ref: External reference (character/content id)
 * - name: Display name
 * - team: Team designation (player/enemy/ally/neutral) - synced from CombatComponent.team
 * - initiative: Final initiative value - synced from CombatComponent.initiativeResult
 * - initiative_roll: Raw d20 roll if rolled server-side
 * - ac: Armor Class - synced from StatsComponent.ac
 * - hp: Current hit points - synced from StatsComponent.currentHp
 * - max_hp: Maximum hit points - synced from StatsComponent.maxHp
 * - actions_remaining: Actions left this turn - synced from ActionsComponent.actionsRemaining
 * - attacks_this_turn: Attacks made this turn (for MAP) - synced from CombatComponent.attacksMade
 * - position_x/position_y: Grid/hex position
 * - is_defeated: Boolean defeated flag - synced from CombatComponent.isDefeated
 * - created/updated: Timestamps
 * 
 * JSON Column Mapping (state_data.metadata):
 * ------------------------------------------
 * The following metadata fields are stored in the state_data JSON column of dc_campaign_characters:
 * 
 * | ECS Component Property | state_data.metadata Field | Description |
 * |------------------------|---------------------------|-------------|
 * | CombatComponent.initiativeResult | metadata.initiative | Final initiative value |
 * | CombatComponent.initiativeRoll | metadata.initiativeRoll | Raw d20 roll |
 * | CombatComponent.team | metadata.team | Team designation |
 * | CombatComponent.inCombat | metadata.inCombat | Combat participation flag |
 * | CombatComponent.turnOrder | metadata.turnOrder | Position in initiative order |
 * | ActionsComponent.actionsRemaining | metadata.actionsRemaining | Actions left this turn |
 * | CombatComponent.attacksMade | metadata.attacksMade | Attacks made this turn (MAP tracking) |
 * 
 * PERSISTENCE FLOW:
 * =================
 * 1. TurnManagementSystem manages local turn state using server-hydrated values
 * 2. For server-authoritative mode, use hydrateFromServer() to load encounter state
 * 3. Frontend calls backend API endpoint (e.g., /api/encounter/{id}/advance-turn)
 * 4. Backend updates combat_encounters table (current_round, turn_index, status)
 * 5. Backend updates combat_participants table (initiative, team, actions_remaining, etc.)
 * 6. Backend syncs to dc_campaign_characters hot columns and state_data JSON
 * 7. Frontend rehydrates from updated server state
 * 
 * IMPORTANT: Changes made to ECS components are transient until persisted via API.
 * 
 * Server Hydration:
 * ------------------
 * The hydrateFromServer() method expects a payload matching the structure from
 * CombatEncounterApiController::buildEncounterResponse() with:
 * - initiative_order: Array of {entity_id, initiative, name, team, ...}
 * - turn_index: Current turn index (0-based)
 * - current_round: Current round number (1-based)
 * - status: Encounter status (setup|active|paused|ended)
 * 
 * @see dungeoncrawler_content.install Lines 179-238 (combat_encounters table)
 * @see dungeoncrawler_content.install Lines 240-357 (combat_participants table)
 * @see ../index.js Lines 50-54 (Component-to-Schema Mapping table)
 * @see ../System.js Lines 20-80 (Database Schema Integration documentation)
 * @see CombatEncounterApiController::buildEncounterResponse() (server payload structure)
 */

import { System } from '../System.js';

/**
 * Combat state.
 * 
 * Note: This enum represents client-side combat state transitions.
 * When syncing to the database combat_encounters.status field, map as follows:
 * - INACTIVE → null/no encounter record (pre-combat)
 * - ROLLING_INITIATIVE → 'setup' (initializing encounter)
 * - IN_PROGRESS → 'active' (combat in progress)
 * - ENDED → 'ended' (combat completed)
 * 
 * The database also supports 'paused' status for temporarily stopped encounters,
 * which is not currently used in this client-side state machine.
 */
export const CombatState = {
  INACTIVE: 'inactive',       // No combat
  ROLLING_INITIATIVE: 'rolling_initiative', // Rolling initiative
  IN_PROGRESS: 'in_progress', // Combat active
  ENDED: 'ended'              // Combat ended
};

function normalizeEncounterStatus(status) {
  const normalized = String(status ?? '').trim().toLowerCase();
  return normalized || 'idle';
}

function resolveBooleanFlag(value) {
  if (typeof value === 'boolean') {
    return value;
  }
  if (typeof value === 'number') {
    return value > 0;
  }
  const normalized = String(value ?? '').trim().toLowerCase();
  if (!normalized) {
    return false;
  }
  if (['1', 'true', 'yes', 'y'].includes(normalized)) {
    return true;
  }
  if (['0', 'false', 'no', 'n', 'null', 'undefined'].includes(normalized)) {
    return false;
  }
  return Boolean(value);
}

function mapEncounterStatusToCombatState(status) {
  switch (normalizeEncounterStatus(status)) {
    case 'active':
      return CombatState.IN_PROGRESS;

    case 'setup':
    case 'rolling_initiative':
      return CombatState.ROLLING_INITIATIVE;

    case 'ended':
      return CombatState.ENDED;

    case 'paused':
      return CombatState.IN_PROGRESS;

    case 'idle':
    default:
      return CombatState.INACTIVE;
  }
}

/**
 * TurnManagementSystem
 * 
 * Manages turn-based combat including:
 * - Initiative ordering from server-authoritative values
 * - Turn progression
 * - Round tracking
 * - Action economy integration
 */
export class TurnManagementSystem extends System {
  constructor(entityManager) {
    super(entityManager);
    this.priority = 10; // Run early (before movement, combat)
    
    // Combat state
    this.combatState = CombatState.INACTIVE;
    this.initiativeOrder = []; // Sorted array of entity IDs
    this.currentTurnIndex = -1;
    this.currentRound = 0;
    this.encounterStatus = 'idle';
    
    // Callbacks for UI updates
    this.onTurnChangeCallback = null;
    this.onRoundChangeCallback = null;
    this.onCombatStateChangeCallback = null;
    this.onOrderChangeCallback = null;
    this.serverHydrated = false; // true when using server-authoritative turn order
    this.hydratedInitiativeCards = [];
  }
  
  /**
   * Initialize system.
   */
  init() {
    console.log('TurnManagementSystem initialized');
  }
  
  /**
   * Update system.
   * @param {number} deltaTime - Time elapsed since last update (ms)
   */
  update(deltaTime) {
    // Turn management is primarily event-driven
    // Could add time-based effects here (e.g., counters, durations)
  }
  
  /**
   * Start combat encounter.
   */
  startCombat(options = {}) {
    const { force = false, allowLocalStart = false } = options;

    if (!allowLocalStart && !this.serverHydrated) {
      console.warn('TurnManagementSystem.startCombat() blocked: hydrateFromServer() is required for server-authoritative combat.');
      return;
    }

    if (this.combatState !== CombatState.INACTIVE) {
      if (!force) {
        console.warn('Combat already in progress');
        return;
      }
      // Reset current combat before forcing a new one
      this.endCombat();
    }
    
    console.log('Starting combat...');
    this.combatState = CombatState.ROLLING_INITIATIVE;
    this.encounterStatus = 'setup';
    this.currentRound = 0;
    this.initiativeOrder = [];
    this.currentTurnIndex = -1;
    
    // Mark all combatants as in combat
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      combat.enterCombat();
    }
    
    // Build initiative order from hydrated/server-provided initiative values
    this.rollInitiative();

    if (this.initiativeOrder.length === 0) {
      console.warn('No combatants available; combat will not start');
      this.endCombat();
      return;
    }
    
    // Start first round
    this.startRound();
    
    if (this.onCombatStateChangeCallback) {
      this.onCombatStateChangeCallback(this.combatState);
    }
  }
  
  /**
   * Build initiative order from existing initiative values.
   *
   * No random rolls are performed client-side. Initiative values must be
   * provided by server payloads via hydrateFromServer().
   */
  rollInitiative() {
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');

    if (!this.serverHydrated) {
      console.warn('TurnManagementSystem.rollInitiative() called before server hydration; initiative values may be stale.');
    }
    
    const initiatives = [];
    
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      const result = Number(combat.getInitiative());
      const initiative = Number.isFinite(result) ? result : 0;
      const roll = Number(combat.initiativeRoll);
      
      initiatives.push({
        entityId: entity.id,
        initiative,
        roll: Number.isFinite(roll) ? roll : 0
      });

      console.log(`Entity ${entity.id} initiative (server): ${initiative} (roll=${initiatives[initiatives.length - 1].roll})`);
    }
    
    // Sort by initiative (highest first), with roll as tiebreaker, then entityId.
    initiatives.sort((a, b) => {
      if (b.initiative !== a.initiative) {
        return b.initiative - a.initiative;
      }
      if (b.roll !== a.roll) {
        return b.roll - a.roll;
      }
      return String(a.entityId).localeCompare(String(b.entityId));
    });
    
    // Set turn order
    this.initiativeOrder = initiatives.map((init, index) => {
      const entity = this.entityManager.getEntity(init.entityId);
      const combat = entity.getComponent('CombatComponent');
      combat.turnOrder = index;
      return init.entityId;
    });
    
    console.log('Initiative order (server values):', this.initiativeOrder);
  }

  /**
   * Resolve a server participant reference to a local ECS entity.
   *
   * @param {string|number} serverEntityId
   * @returns {Entity|null}
   */
  resolveEntityFromServerId(serverEntityId) {
    const direct = this.entityManager.getEntity(serverEntityId);
    if (direct) {
      return direct;
    }

    const numericId = Number(serverEntityId);
    if (Number.isFinite(numericId)) {
      const numeric = this.entityManager.getEntity(numericId);
      if (numeric) {
        return numeric;
      }
    }

    const serverIdString = String(serverEntityId);
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');
    for (const entity of combatants) {
      if (String(entity.id) === serverIdString) {
        return entity;
      }
      if (entity.dcEntityRef && String(entity.dcEntityRef) === serverIdString) {
        return entity;
      }
      if (entity.dcCharacterId && String(entity.dcCharacterId) === serverIdString) {
        return entity;
      }
    }

    return null;
  }
  
  /**
   * Start a new round.
   */
  startRound() {
    const activeOrder = this.initiativeOrder.filter((entityId) => {
      const entity = this.entityManager.getEntity(entityId);
      if (!entity) {
        return false;
      }

      const combat = entity.getComponent('CombatComponent');
      const stats = entity.getComponent('StatsComponent');
      if (!combat) {
        return false;
      }

      const defeated = combat.isDefeated || (stats && !stats.isAlive());
      return !defeated;
    });

    if (activeOrder.length === 0) {
      console.warn('No active combatants remain; ending combat');
      this.endCombat();
      return;
    }

    if (activeOrder.length !== this.initiativeOrder.length) {
      this.initiativeOrder = activeOrder;
      this.currentTurnIndex = -1;
    }

    this.currentRound++;
    this.currentTurnIndex = -1;
    
    console.log(`=== Round ${this.currentRound} ===`);
    
    // Reset turn tracking for all combatants
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      combat.resetTurnTracking();
    }
    
    // Start first turn
    this.nextTurn();
    
    if (this.onRoundChangeCallback) {
      this.onRoundChangeCallback(this.currentRound);
    }
  }
  
  /**
   * Advance to next turn.
   */
  nextTurn() {
    // End current turn if there is one
    if (this.currentTurnIndex >= 0) {
      this.endCurrentTurn();
    }
    
    // Find next entity that can take a turn
    let nextIndex = this.currentTurnIndex + 1;
    let foundNextTurn = false;
    
    while (nextIndex < this.initiativeOrder.length && !foundNextTurn) {
      const entityId = this.initiativeOrder[nextIndex];
      const entity = this.entityManager.getEntity(entityId);
      
      if (!entity) {
        // Entity was removed, skip
        nextIndex++;
        continue;
      }
      
      const combat = entity.getComponent('CombatComponent');
      const stats = entity.getComponent('StatsComponent');
      
      // Skip defeated entities
      if (combat.isDefeated || (stats && !stats.isAlive())) {
        nextIndex++;
        continue;
      }
      
      // Found valid entity
      this.currentTurnIndex = nextIndex;
      foundNextTurn = true;
    }
    
    if (!foundNextTurn) {
      // No more turns this round, start next round
      this.startRound();
      return;
    }
    
    // Start turn for current entity
    this.startCurrentTurn();
  }
  
  /**
   * Start turn for current entity.
   */
  startCurrentTurn() {
    const entityId = this.initiativeOrder[this.currentTurnIndex];
    const entity = this.entityManager.getEntity(entityId);
    
    if (!entity) {
      this.nextTurn();
      return;
    }
    
    const combat = entity.getComponent('CombatComponent');
    const actions = entity.getComponent('ActionsComponent');
    const movement = entity.getComponent('MovementComponent');
    const identity = entity.getComponent('IdentityComponent');
    
    // Mark turn start
    combat.startTurn();
    
    const maybeAutoEndTurn = () => {
      if (this.shouldAutoEndTurn(entity)) {
        this.endTurn();
      }
    };

    // Restore actions
    if (actions) {
      actions.startTurn();
      // Auto-end turn only when both actions and movement are spent
      actions.setOnActionsDepleted(maybeAutoEndTurn);
    }

    // Restore movement
    if (movement) {
      movement.restoreMovement();
      movement.setOnMovementDepleted(maybeAutoEndTurn);
    }
    
    const name = identity ? identity.name : `Entity ${entity.id}`;
    console.log(`>>> ${name}'s turn (${this.currentTurnIndex + 1}/${this.initiativeOrder.length})`);
    
    this.combatState = CombatState.IN_PROGRESS;
    this.encounterStatus = 'active';
    
    if (this.onTurnChangeCallback) {
      this.onTurnChangeCallback(entity, this.currentTurnIndex, this.initiativeOrder.length);
    }
  }
  
  /**
   * End current entity's turn.
   */
  endCurrentTurn() {
    if (this.currentTurnIndex < 0) {
      return;
    }
    
    const entityId = this.initiativeOrder[this.currentTurnIndex];
    const entity = this.entityManager.getEntity(entityId);
    
    if (!entity) {
      return;
    }
    
    const combat = entity.getComponent('CombatComponent');
    const actions = entity.getComponent('ActionsComponent');
    const movement = entity.getComponent('MovementComponent');
    
    // Mark turn end
    combat.endTurn();
    
    if (actions) {
      // Clear depletion hook to avoid cross-turn firing
      actions.setOnActionsDepleted(null);
      actions.endTurn();
    }

    if (movement) {
      movement.setOnMovementDepleted(null);
    }
    
    const identity = entity.getComponent('IdentityComponent');
    const name = identity ? identity.name : `Entity ${entity.id}`;
    console.log(`<<< ${name}'s turn ended`);
  }
  
  /**
   * End current turn and advance (player/UI triggered).
   */
  endTurn() {
    if (this.combatState !== CombatState.IN_PROGRESS) {
      console.warn('No active turn to end');
      return;
    }
    
    this.nextTurn();
  }
  
  /**
   * End combat.
   */
  endCombat() {
    console.log('Combat ended');
    
    this.combatState = CombatState.ENDED;
    this.encounterStatus = 'ended';
    
    // Exit combat for all combatants
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      combat.exitCombat();
    }
    
    // Reset state
    this.initiativeOrder = [];
    this.currentTurnIndex = -1;
    this.currentRound = 0;
    this.combatState = CombatState.INACTIVE;
    this.encounterStatus = 'idle';
    this.serverHydrated = false;
    this.hydratedInitiativeCards = [];
    
    if (this.onCombatStateChangeCallback) {
      this.onCombatStateChangeCallback(this.combatState);
    }
  }
  
  /**
   * Get current turn entity.
   * @returns {Entity|null}
   */
  getCurrentTurnEntity() {
    if (this.currentTurnIndex < 0 || this.currentTurnIndex >= this.initiativeOrder.length) {
      return null;
    }
    
    const entityId = this.initiativeOrder[this.currentTurnIndex];
    return this.entityManager.getEntity(entityId);
  }
  
  /**
   * Check if it's a specific entity's turn.
   * @param {Entity} entity - Entity to check
   * @returns {boolean}
   */
  isEntityTurn(entity) {
    const currentEntity = this.getCurrentTurnEntity();
    return currentEntity && currentEntity.id === entity.id;
  }
  
  /**
   * Get initiative order with entity data.
   * @returns {Array} - Array of {entity, initiative, isCurrent}
   */
  getInitiativeOrder() {
    if (Array.isArray(this.hydratedInitiativeCards) && this.hydratedInitiativeCards.length > 0) {
      return this.hydratedInitiativeCards.map((entry, index) => {
        const entityId = entry?.entityId ?? entry?.entity_id ?? '';
        const entity = this.resolveEntityFromServerId(entityId) || this.entityManager.getEntity(entityId) || null;
        const resolvedIndex = Number(entry?.order);
        const orderIndex = Number.isFinite(resolvedIndex) ? resolvedIndex : index;
        const isCurrent = Number.isFinite(this.currentTurnIndex)
          ? orderIndex === this.currentTurnIndex
          : resolveBooleanFlag(entry?.is_current ?? entry?.isCurrent);

        return {
          entity,
          entityId: entity ? entity.id : entityId,
          name: String(entry?.name || entity?.getComponent?.('IdentityComponent')?.name || `Entity ${entityId}`).trim(),
          initiative: Number.isFinite(Number(entry?.initiative)) ? Number(entry.initiative) : 0,
          isCurrent,
          isDefeated: resolveBooleanFlag(entry?.is_defeated ?? entry?.isDefeated),
          team: String(entry?.team || '').trim().toLowerCase() || null,
          hp: entry?.hp && typeof entry.hp === 'object' ? entry.hp : null,
          actionsRemaining: Number.isFinite(Number(entry?.actions_remaining))
            ? Number(entry.actions_remaining)
            : null,
          reactionAvailable: typeof entry?.reaction_available === 'boolean'
            ? entry.reaction_available
            : null,
        };
      });
    }

    return this.initiativeOrder.map((entityId, index) => {
      const entity = this.entityManager.getEntity(entityId);
      const combat = entity ? entity.getComponent('CombatComponent') : null;
      const identity = entity ? entity.getComponent('IdentityComponent') : null;
      
      return {
        entity: entity,
        entityId: entityId,
        name: identity ? identity.name : `Entity ${entityId}`,
        initiative: combat ? combat.getInitiative() : 0,
        isCurrent: index === this.currentTurnIndex,
        isDefeated: combat ? combat.isDefeated : false
      };
    });
  }
  
  /**
   * Check if combat is active.
   * @returns {boolean}
   */
  isCombatActive() {
    return this.combatState === CombatState.IN_PROGRESS;
  }

  /**
   * Hydrate turn state from a server payload (server-authoritative mode).
   * Expects shape similar to CombatEncounterApiController::buildEncounterResponse().
   * @param {Object} serverState
   */
  hydrateFromServer(serverState = {}) {
    const previousOrder = Array.isArray(this.initiativeOrder) ? [...this.initiativeOrder] : [];
    const previousTurnIndex = this.currentTurnIndex;
    const previousRound = this.currentRound;
    const previousCombatState = this.combatState;
    const wasServerHydrated = this.serverHydrated;
    const previousCurrentEntity = this.getCurrentTurnEntity();
    const previousCurrentActions = previousCurrentEntity?.getComponent('ActionsComponent') || null;
    const previousActionsRemaining = previousCurrentActions ? Number(previousCurrentActions.actionsRemaining) : null;
    const previousAttacksMade = previousCurrentActions ? Number(previousCurrentActions.attacksMadeThisTurn) : null;

    const presentation = serverState?.encounter_presentation && typeof serverState.encounter_presentation === 'object'
      ? serverState.encounter_presentation
      : null;
    const previousHydratedCardsSignature = JSON.stringify(
      Array.isArray(this.hydratedInitiativeCards) ? this.hydratedInitiativeCards : []
    );
    const initiativeEntries = Array.isArray(presentation?.initiative_order)
      ? presentation.initiative_order
      : (Array.isArray(serverState.initiative_order) ? serverState.initiative_order : []);
    const encounterStatus = normalizeEncounterStatus(
      presentation?.status
      ?? serverState?.status
      ?? ''
    );

    const order = [];
    const initiativeByEntityId = new Map();
    this.hydratedInitiativeCards = [];
    initiativeEntries.forEach((entry, index) => {
      const entryEntityId = entry?.entity_id ?? entry?.entityId ?? '';
      const entity = this.resolveEntityFromServerId(entryEntityId);
      if (!entity) {
        if (entryEntityId) {
          this.hydratedInitiativeCards.push({
            ...entry,
            entity_id: entryEntityId,
            order: index,
          });
        }
        return;
      }

      order.push(entity.id);
      initiativeByEntityId.set(entity.id, {
        initiative: Number(entry?.initiative),
        order: index,
      });
      this.hydratedInitiativeCards.push({
        ...entry,
        entity_id: entryEntityId,
        entityId: entity.id,
        order: index,
      });
    });

    const participants = Array.isArray(serverState.participants)
      ? serverState.participants
      : initiativeEntries.map((entry) => ({
          entity_id: entry?.entity_id ?? entry?.entityId ?? null,
          team: entry?.team ?? null,
          initiative: entry?.initiative ?? null,
          hp: entry?.hp?.current ?? null,
          max_hp: entry?.hp?.max ?? null,
          actions_remaining: entry?.actions_remaining ?? null,
          reaction_available: entry?.reaction_available ?? null,
          is_defeated: entry?.is_defeated ?? entry?.isDefeated ?? false,
        }));
    participants.forEach((participant) => {
      const participantEntityId = participant?.entity_id ?? participant?.entity_ref ?? participant?.id ?? null;
      const entity = this.resolveEntityFromServerId(participantEntityId);
      if (!entity) {
        return;
      }

      const combat = entity.getComponent('CombatComponent');
      if (combat) {
        const participantInitiative = Number(participant?.initiative);
        const orderInitiative = initiativeByEntityId.get(entity.id)?.initiative;
        const initiativeResult = Number.isFinite(participantInitiative)
          ? participantInitiative
          : (Number.isFinite(orderInitiative) ? orderInitiative : null);

        const initiativeRoll = Number(participant?.initiative_roll);
        if (Number.isFinite(initiativeResult)) {
          combat.setInitiativeFromServer(
            initiativeResult,
            Number.isFinite(initiativeRoll) ? initiativeRoll : null
          );
        }

        const resolvedOrder = initiativeByEntityId.get(entity.id)?.order;
        if (Number.isInteger(resolvedOrder)) {
          combat.turnOrder = resolvedOrder;
        }

        if (participant?.team) {
          combat.team = participant.team;
        }
        combat.isDefeated = resolveBooleanFlag(participant?.is_defeated);
        combat.inCombat = encounterStatus === 'active';
      }

      const actions = entity.getComponent('ActionsComponent');
      if (actions) {
        const remaining = Number(participant?.actions_remaining);
        if (Number.isFinite(remaining)) {
          actions.actionsRemaining = Math.max(0, remaining);
          actions.canAct = actions.actionsRemaining > 0;
        }

        const attacksMade = Number(participant?.attacks_this_turn);
        if (Number.isFinite(attacksMade)) {
          actions.attacksMadeThisTurn = Math.max(0, attacksMade);
          actions.mapPenalty = actions.attacksMadeThisTurn * actions.mapPenaltyPerAttack;
        }
      }
    });

    const currentTurnIndex = Number(
      presentation?.turn_index
      ?? serverState.turn_index
    );
    const currentRound = Number(
      presentation?.current_round
      ?? serverState.current_round
    );
    const currentEntityId = String(
      presentation?.current_entity_id
      ?? ''
    ).trim();
    const resolvedTurnIndex = Number.isFinite(currentTurnIndex)
      ? currentTurnIndex
      : (currentEntityId !== ''
        ? initiativeEntries.findIndex((entry) => String(entry?.entity_id ?? entry?.entityId ?? '').trim() === currentEntityId)
        : 0);
    this.initiativeOrder = order;
    this.currentTurnIndex = this.initiativeOrder.length > 0
      ? (resolvedTurnIndex >= 0 ? resolvedTurnIndex : 0)
      : -1;
    this.currentRound = Number.isFinite(currentRound) && currentRound > 0
      ? currentRound
      : (encounterStatus === 'idle' || encounterStatus === 'ended' ? 0 : 1);
    this.encounterStatus = encounterStatus;
    this.combatState = mapEncounterStatusToCombatState(encounterStatus);
    this.serverHydrated = true;

    const orderChanged = previousOrder.length !== this.initiativeOrder.length
      || previousOrder.some((entityId, index) => entityId !== this.initiativeOrder[index]);
    const hydratedCardsChanged = previousHydratedCardsSignature !== JSON.stringify(
      Array.isArray(this.hydratedInitiativeCards) ? this.hydratedInitiativeCards : []
    );
    const currentEntity = this.getCurrentTurnEntity();
    const currentActions = currentEntity?.getComponent('ActionsComponent') || null;
    const currentActionsRemaining = currentActions ? Number(currentActions.actionsRemaining) : null;
    const currentAttacksMade = currentActions ? Number(currentActions.attacksMadeThisTurn) : null;
    const currentEntityUnchanged = Boolean(
      previousCurrentEntity
      && currentEntity
      && previousCurrentEntity.id === currentEntity.id
    );
    const currentTurnResourcesChanged = Boolean(
      currentEntityUnchanged
      && currentActions
      && (
        previousActionsRemaining !== currentActionsRemaining
        || previousAttacksMade !== currentAttacksMade
      )
    );
    const turnChanged = !wasServerHydrated
      || orderChanged
      || previousTurnIndex !== this.currentTurnIndex
      || currentTurnResourcesChanged;
    const roundChanged = !wasServerHydrated
      || previousRound !== this.currentRound;
    const combatStateChanged = !wasServerHydrated
      || previousCombatState !== this.combatState;

    if (currentEntity && this.onTurnChangeCallback && turnChanged) {
      this.onTurnChangeCallback(currentEntity, this.currentTurnIndex, this.initiativeOrder.length);
    }

    if (this.onRoundChangeCallback && roundChanged) {
      this.onRoundChangeCallback(this.currentRound);
    }

    if (this.onCombatStateChangeCallback && combatStateChanged) {
      this.onCombatStateChangeCallback(this.combatState);
    }

    if (this.onOrderChangeCallback && (!wasServerHydrated || orderChanged || turnChanged || hydratedCardsChanged)) {
      this.onOrderChangeCallback(this.getInitiativeOrder());
    }
  }

  /**
   * Determine whether the active entity has exhausted actions and movement.
   * @param {Entity} entity
   * @returns {boolean}
   */
  shouldAutoEndTurn(entity) {
    const actions = entity.getComponent('ActionsComponent');
    const movement = entity.getComponent('MovementComponent');

    const actionsSpent = !actions || actions.actionsRemaining <= 0;
    const movementSpent = !movement || movement.movementRemaining <= 0;

    return actionsSpent && movementSpent;
  }
  
  /**
   * Get current round number.
   * @returns {number}
   */
  getCurrentRound() {
    return this.currentRound;
  }

  /**
   * Get current normalized server encounter status.
   * @returns {string}
   */
  getEncounterStatus() {
    return this.encounterStatus;
  }
  
  /**
   * Register callback for turn changes.
   * @param {Function} callback - Callback function(entity, turnIndex, totalTurns)
   */
  onTurnChange(callback) {
    this.onTurnChangeCallback = callback;
  }
  
  /**
   * Register callback for round changes.
   * @param {Function} callback - Callback function(roundNumber)
   */
  onRoundChange(callback) {
    this.onRoundChangeCallback = callback;
  }
  
  /**
   * Register callback for combat state changes.
   * @param {Function} callback - Callback function(combatState)
   */
  onCombatStateChange(callback) {
    this.onCombatStateChangeCallback = callback;
  }

  /**
   * Register callback for initiative order changes.
   * @param {Function} callback - Callback function(order)
   */
  onOrderChange(callback) {
    this.onOrderChangeCallback = callback;
  }
  
  /**
   * Cleanup system.
   */
  destroy() {
    this.endCombat();
    console.log('TurnManagementSystem destroyed');
  }
}
