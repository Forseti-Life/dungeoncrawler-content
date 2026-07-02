/**
 * @file CombatSystem.js
 * System for handling combat actions (attacks, damage, etc.).
 * 
 * SCHEMA CONFORMANCE NOTES (DCC-0249):
 * =====================================
 * This is a **client-side ECS system** that operates on runtime component data.
 * It does NOT directly persist to the database. Combat state changes (HP, position)
 * must be synchronized to the backend via API calls to persist to hot columns.
 * 
 * Database Hot Columns (dc_campaign_characters table):
 * - hp_current, hp_max: Synced from resources.hitPoints in state_data JSON
 * - armor_class: Synced from defenses.armorClass.total in state_data JSON
 * - position_q, position_r: Synced from position.{q,r} in state_data JSON (hex axial coordinates)
 * - experience_points: Synced from basicInfo.experiencePoints in state_data JSON
 * - last_room_id: Synced from location.roomId in state_data JSON (most recent room)
 * 
 * Hot columns are maintained by CharacterStateService::saveState() which extracts
 * these values from the JSON payload and updates both the state_data column and
 * the individual hot columns atomically. This provides:
 * 1. Fast queries for combat-critical data without JSON parsing
 * 2. Single source of truth (JSON payload is authoritative)
 * 3. Consistency guarantee via database transaction
 * 
 * PERSISTENCE FLOW:
 * =================
 * 1. CombatSystem modifies StatsComponent (e.g., HP via takeDamage())
 * 2. Frontend calls backend API endpoint (e.g., /api/character/{id}/update)
 * 3. Backend calls CharacterStateService::updateHitPoints() or saveState()
 * 4. CharacterStateService extracts hot columns from JSON and updates both atomically
 * 
 * IMPORTANT: Changes made to ECS components are transient until persisted via API.
 * 
 * @see CharacterStateService::saveState() for hot column synchronization
 * @see CharacterStateService::updateHitPoints() for HP-specific updates
 * @see dungeoncrawler_content.install for dc_campaign_characters schema
 */

import { System } from '../System.js';
import { ActionCost } from '../components/ActionsComponent.js';

/**
 * Attack result.
 */
export const AttackResult = {
  CRITICAL_HIT: 'critical_hit',
  HIT: 'hit',
  MISS: 'miss',
  CRITICAL_MISS: 'critical_miss'
};

/**
 * CombatSystem
 * 
 * Handles combat mechanics including:
 * - Attack legality checks (range, hostility, actions)
 * - Server-result attack projection for ECS/UI callbacks
 * - Damage application from server-authoritative outcomes
 * - Multiple Attack Penalty (MAP)
 * - Damage application (transient - requires backend sync for persistence)
 * 
 * NOTE: This system operates on ECS components (StatsComponent, CombatComponent).
 * All state changes are runtime-only until synchronized to the database via
 * CharacterStateService API calls. See file header for schema conformance details.
 */
export class CombatSystem extends System {
  constructor(entityManager) {
    super(entityManager);
    this.priority = 30; // Run after turn management (10), before movement (50)
    this.requireServerResultPayload = true;
    
    // Callbacks for UI/animation
    this.onAttackCallback = null;
    this.onDamageCallback = null;
  }

  /**
   * Enable or disable strict server-result enforcement.
   *
   * @param {boolean} required
   */
  setServerResultRequirement(required) {
    this.requireServerResultPayload = Boolean(required);
  }
  
  /**
   * Initialize system.
   */
  init() {
    console.log('CombatSystem initialized');
  }
  
  /**
   * Update system.
   * @param {number} deltaTime - Time elapsed since last update (ms)
   */
  update(deltaTime) {
    // Combat system is event-driven
  }
  
  /**
   * Get attack bonus for an entity.
   * 
   * Reads from ECS components populated from database schema:
   * - CombatComponent.attackBonus: derived from character_data JSON
   * - CombatComponent.weaponProficiency: derived from character_data JSON
   * - StatsComponent.abilities.strength: derived from character_data JSON
   * 
   * These values should match the JSON payload structure used by CharacterStateService.
   * 
   * @param {Entity} attacker - Attacking entity
   * @returns {number} - Attack bonus
   */
  getAttackBonus(attacker) {
    const combat = attacker.getComponent('CombatComponent');
    const stats = attacker.getComponent('StatsComponent');
    
    if (!combat || !stats) {
      return 0;
    }
    
    // Base attack bonus + ability modifier (using STR for now)
    const strMod = stats.getAbilityModifier('strength');
    return combat.attackBonus + combat.weaponProficiency + strMod;
  }
  
  /**
   * Project a server-authoritative attack outcome into ECS/UI state.
   * 
   * SCHEMA CONFORMANCE:
   * - Reads AC from StatsComponent.ac (maps to hot column armor_class)
   * - Uses backend-provided attack/damage outcomes from /api/combat/attack
   * - Does not perform any random roll generation client-side
   * 
   * @param {Entity} attacker - Attacking entity
   * @param {Entity} target - Target entity
   * @param {Object} [serverResult=null] - Server-authoritative attack result payload
   * @returns {Object|null} - Normalized attack data, or null when no server result is provided
   */
  makeAttack(attacker, target, serverResult = null) {
    const attackerCombat = attacker.getComponent('CombatComponent');
    const attackerActions = attacker.getComponent('ActionsComponent');
    const targetStats = target.getComponent('StatsComponent');
    const targetIdentity = target.getComponent('IdentityComponent');
    
    if (!attackerCombat || !targetStats) {
      console.warn('Missing components for attack');
      return null;
    }

    if (!serverResult || typeof serverResult !== 'object') {
      const message = 'CombatSystem.makeAttack() requires server-authoritative result payload.';
      if (this.requireServerResultPayload) {
        throw new Error(message);
      }
      console.warn(message);
      return null;
    }
    
    // Get Multiple Attack Penalty if ActionsComponent exists
    let mapPenalty = 0;
    if (attackerActions) {
      const mapResult = attackerActions.makeAttack();
      if (mapResult === null) {
        console.warn('No actions remaining to attack');
        return null;
      }
      mapPenalty = mapResult;
    }
    
    const attackRoll = Number(
      serverResult.attackRoll
      ?? serverResult.attack_roll
      ?? serverResult.roll
      ?? serverResult.natural
      ?? 0
    );

    const attackBonus = this.getAttackBonus(attacker);

    const attackTotal = Number(
      serverResult.attackTotal
      ?? serverResult.attack_total
      ?? serverResult.total
      ?? (attackRoll + attackBonus + mapPenalty)
    );
    
    // Get target AC (from hot column armor_class via StatsComponent)
    const targetAC = targetStats.ac;
    
    const result = String(
      serverResult.result
      ?? serverResult.degree
      ?? serverResult.outcome
      ?? AttackResult.MISS
    ).toLowerCase();
    
    // Apply server damage only when explicitly requested by caller.
    const damage = Number(serverResult.damage ?? serverResult.total_damage ?? 0);
    const shouldApplyDamage = Boolean(serverResult.applyDamage === true);
    if (shouldApplyDamage && Number.isFinite(damage) && damage > 0) {
      this.applyDamage(target, damage);
    }
    
    const attackData = {
      attacker: attacker,
      target: target,
      result: result,
      attackRoll: attackRoll,
      attackTotal: attackTotal,
      mapPenalty: mapPenalty,
      targetAC: targetAC,
      damage: damage
    };
    
    // Log attack
    const attackerName = attacker.getComponent('IdentityComponent')?.name || `Entity ${attacker.id}`;
    const targetName = targetIdentity?.name || `Entity ${target.id}`;
    
    console.log(`${attackerName} attacks ${targetName}: ${attackRoll} + ${attackBonus} + ${mapPenalty} = ${attackTotal} vs AC ${targetAC}`);
    console.log(`Result: ${result}${damage > 0 ? `, Damage: ${damage}` : ''}`);
    
    // Trigger callback for UI/animation
    if (this.onAttackCallback) {
      this.onAttackCallback(attackData);
    }
    
    return attackData;
  }
  
  /**
   * Apply damage to an entity.
   * 
   * SCHEMA CONFORMANCE: This method modifies StatsComponent.currentHp which maps to:
   * - Hot Column: dc_campaign_characters.hp_current
   * - JSON Path: state_data -> resources.hitPoints.current
   * 
   * Changes are TRANSIENT until persisted via backend API call.
   * Frontend should call CharacterStateService::updateHitPoints() or saveState()
   * to synchronize hp_current hot column with the JSON payload.
   * 
   * @param {Entity} target - Target entity
   * @param {number} damage - Damage amount
   * @returns {boolean} - True if target is still alive
   */
  applyDamage(target, damage) {
    const stats = target.getComponent('StatsComponent');
    const combat = target.getComponent('CombatComponent');
    
    if (!stats) {
      return true;
    }
    
    // Apply damage (transient change - requires API sync for persistence)
    const actualDamage = stats.takeDamage(damage);
    
    // Check if defeated
    if (!stats.isAlive() && combat) {
      combat.defeat();
      console.log(`${target.getComponent('IdentityComponent')?.name || target.id} has been defeated!`);
    }
    
    // Trigger callback for UI/animation
    if (this.onDamageCallback) {
      this.onDamageCallback({
        target: target,
        damage: actualDamage,
        remainingHp: stats.currentHp,
        maxHp: stats.maxHp,
        defeated: !stats.isAlive()
      });
    }
    
    return stats.isAlive();
  }
  
  /**
   * Heal an entity.
   * 
   * SCHEMA CONFORMANCE: This method modifies StatsComponent.currentHp which maps to:
   * - Hot Column: dc_campaign_characters.hp_current
   * - JSON Path: state_data -> resources.hitPoints.current
   * 
   * Changes are TRANSIENT until persisted via backend API call.
   * 
   * @param {Entity} target - Target entity
   * @param {number} amount - Healing amount
   * @returns {number} - Actual HP healed
   */
  heal(target, amount) {
    const stats = target.getComponent('StatsComponent');
    
    if (!stats) {
      return 0;
    }
    
    // Apply healing (transient change - requires API sync for persistence)
    const healed = stats.heal(amount);
    
    console.log(`${target.getComponent('IdentityComponent')?.name || target.id} healed for ${healed} HP`);
    
    return healed;
  }
  
  /**
   * Check if entity can attack target.
   * 
   * SCHEMA CONFORMANCE:
   * - Position checks use PositionComponent (q, r hex axial coordinates)
   * - Position maps to hot columns: position_q, position_r
   * - Position is extracted from state_data -> position.{q,r}
   * - Room tracking maps to hot column: last_room_id from state_data -> location.roomId
   * 
   * @param {Entity} attacker - Attacking entity
   * @param {Entity} target - Target entity
   * @returns {Object} - {canAttack, reason}
   */
  canAttack(attacker, target) {
    // Check if entities exist
    if (!attacker || !target) {
      return { canAttack: false, reason: 'Invalid entities' };
    }
    
    // Check if attacker has actions
    const actions = attacker.getComponent('ActionsComponent');
    if (actions && !actions.canAfford(ActionCost.ONE)) {
      return { canAttack: false, reason: 'No actions remaining' };
    }
    
    // Check if target is alive
    const targetStats = target.getComponent('StatsComponent');
    if (targetStats && !targetStats.isAlive()) {
      return { canAttack: false, reason: 'Target is defeated' };
    }
    
    // Check if entities are hostile
    const attackerCombat = attacker.getComponent('CombatComponent');
    const targetCombat = target.getComponent('CombatComponent');
    if (attackerCombat && targetCombat && !attackerCombat.isHostileTo(targetCombat)) {
      return { canAttack: false, reason: 'Target is not hostile' };
    }
    
    // Check range (for now, assume melee - must be adjacent)
    // Position coordinates (q, r) map to hot columns for fast spatial queries
    const attackerPos = attacker.getComponent('PositionComponent');
    const targetPos = target.getComponent('PositionComponent');
    if (attackerPos && targetPos) {
      const distance = attackerPos.distanceTo(targetPos);
      if (distance > 1) {
        return { canAttack: false, reason: 'Target out of range' };
      }
    }
    
    return { canAttack: true, reason: null };
  }
  
  /**
   * Execute attack action (checks actions, performs attack).
   * @param {Entity} attacker - Attacking entity
   * @param {Entity} target - Target entity
   * @param {Object} [serverResult=null] - Server-authoritative attack result payload
   * @returns {Object|null} - Attack data or null if failed
   */
  attack(attacker, target, serverResult = null) {
    if ((!serverResult || typeof serverResult !== 'object') && this.requireServerResultPayload) {
      throw new Error('CombatSystem.attack() blocked: missing server-authoritative result payload.');
    }

    // Check if attack is valid
    const check = this.canAttack(attacker, target);
    if (!check.canAttack) {
      console.warn(`Cannot attack: ${check.reason}`);
      return null;
    }

    // Perform attack using server-authoritative result payload only.
    return this.makeAttack(attacker, target, serverResult);
  }
  
  /**
   * Register callback for attack events.
   * @param {Function} callback - Callback function(attackData)
   */
  onAttack(callback) {
    this.onAttackCallback = callback;
  }
  
  /**
   * Register callback for damage events.
   * @param {Function} callback - Callback function(damageData)
   */
  onDamage(callback) {
    this.onDamageCallback = callback;
  }
  
  /**
   * Cleanup system.
   */
  destroy() {
    console.log('CombatSystem destroyed');
  }
}
