/**
 * @file ActionsComponent.js
 * Component for Pathfinder 2e 3-action economy.
 * 
 * Storage: This component is serialized to JSON and stored in the `state_data` 
 * column of the `dc_campaign_characters` table. The unified JSON storage approach
 * allows flexible component composition while maintaining hot columns for frequently
 * accessed gameplay data (HP, AC, position).
 */

import { Component } from '../Component.js';

/**
 * Action types in PF2e.
 */
export const ActionType = {
  ACTION: 'action',           // Standard action (1 action)
  REACTION: 'reaction',       // Reaction (triggered)
  FREE_ACTION: 'free_action', // Free action
  ACTIVITY: 'activity'        // Multi-action activity (2-3 actions)
};

/**
 * Action cost in number of actions.
 */
export const ActionCost = {
  FREE: 0,
  ONE: 1,
  TWO: 2,
  THREE: 3,
  REACTION: -1 // Special: uses reaction
};

/**
 * Multiple Attack Penalty (MAP) constants.
 */
export const MAPConstants = {
  STANDARD_PENALTY: -5,  // Standard weapon MAP per attack
  AGILE_PENALTY: -4,     // Agile weapon MAP per attack
  MIN_BONUS: -3,         // Minimum action bonus allowed
  MAX_BONUS: 3           // Maximum action bonus allowed
};

/**
 * ActionsComponent
 * 
 * Manages the 3-action economy for Pathfinder 2e.
 * Tracks available actions, reactions, and Multiple Attack Penalty (MAP).
 * 
 * @class
 * @extends Component
 * 
 * @property {number} maxActions - Maximum actions per turn (usually 3)
 * @property {number} actionsRemaining - Actions left this turn
 * @property {boolean} hasReaction - Whether reaction is available
 * @property {number} attacksMadeThisTurn - Number of attacks made this turn for MAP calculation
 * @property {number} mapPenalty - Current Multiple Attack Penalty (e.g., -5, -10)
 * @property {number} mapPenaltyPerAttack - MAP increment per attack (-5 standard, -4 agile)
 * @property {Array<Object>} actionHistory - Array of actions taken this turn
 * @property {boolean} canAct - Whether entity can take actions (false when stunned, etc.)
 * @property {number} actionBonus - Bonus/penalty to action count (e.g., Haste spell gives +1)
 * @property {Function|null} onActionsDepleted - Optional callback when actions reach zero
 * 
 * **Database Storage:**
 * This component is stored as JSON in the `state_data` field of the `dc_campaign_characters` table.
 * Serialization is handled by `toJSON()` and deserialization by `fromJSON()`.
 * 
 * **Example JSON:**
 * ```json
 * {
 *   "type": "ActionsComponent",
 *   "maxActions": 3,
 *   "actionsRemaining": 2,
 *   "hasReaction": true,
 *   "attacksMadeThisTurn": 1,
 *   "mapPenalty": -5,
 *   "mapPenaltyPerAttack": -5,
 *   "actionHistory": [{"name": "Strike", "cost": 1, "type": "action", "timestamp": 1642534800000}],
 *   "canAct": true,
 *   "actionBonus": 0
 * }
 * ```
 */
export class ActionsComponent extends Component {
  /**
   * @param {number} maxActions - Maximum actions per turn (default 3)
   * @param {number} mapPenaltyPerAttack - MAP penalty per attack (default -5 for standard weapons)
   */
  constructor(maxActions = 3, mapPenaltyPerAttack = MAPConstants.STANDARD_PENALTY) {
    super();
    
    // Validate maxActions
    if (!Number.isInteger(maxActions) || maxActions < 0) {
      throw new Error(`maxActions must be a non-negative integer, got: ${maxActions}`);
    }
    
    // Action economy
    this.maxActions = maxActions;
    this.actionsRemaining = maxActions;
    this.hasReaction = true;
    
    // Multiple Attack Penalty (MAP)
    this.attacksMadeThisTurn = 0;
    this.mapPenalty = 0; // Current MAP (-5, -10, etc.)
    this.mapPenaltyPerAttack = mapPenaltyPerAttack; // Configurable: -5 standard, -4 agile
    
    // Action history this turn
    this.actionHistory = [];
    
    // Can take actions?
    this.canAct = true;
    
    // Bonus/penalty to action count
    this.actionBonus = 0; // e.g., Haste gives +1

    // Optional hook when actions hit zero
    this.onActionsDepleted = null;
  }
  
  /**
   * Check if entity can afford an action.
   * @param {number} cost - Action cost (from ActionCost enum)
   * @returns {boolean} True if the action can be afforded
   * 
   * @example
   * if (actions.canAfford(ActionCost.ONE)) {
   *   // Can perform a single action
   * }
   */
  canAfford(cost) {
    // Validate cost input
    if (typeof cost !== 'number') {
      return false;
    }
    
    if (!this.canAct) {
      return false;
    }
    
    if (cost === ActionCost.FREE) {
      return true;
    }
    
    if (cost === ActionCost.REACTION) {
      return this.hasReaction;
    }
    
    return this.actionsRemaining >= cost;
  }
  
  /**
   * Consume actions.
   * @param {number} cost - Action cost
   * @param {string} actionName - Name of action for history
   * @returns {boolean} True if actions were successfully consumed
   * 
   * @example
   * if (actions.spendActions(ActionCost.TWO, 'Cast Spell')) {
   *   // Successfully spent 2 actions
   * }
   */
  spendActions(cost, actionName = 'Unknown') {
    if (!this.canAfford(cost)) {
      return false;
    }
    
    if (cost === ActionCost.REACTION) {
      this.hasReaction = false;
      this.actionHistory.push({
        name: actionName,
        cost: cost,
        type: ActionType.REACTION,
        timestamp: Date.now()
      });
      return true;
    }
    
    if (cost === ActionCost.FREE) {
      this.actionHistory.push({
        name: actionName,
        cost: cost,
        type: ActionType.FREE_ACTION,
        timestamp: Date.now()
      });
      return true;
    }
    
    // Spend standard actions
    this.actionsRemaining -= cost;
    this.actionHistory.push({
      name: actionName,
      cost: cost,
      type: cost >= 2 ? ActionType.ACTIVITY : ActionType.ACTION,
      timestamp: Date.now()
    });

    // Trigger depletion hook once we hit zero or below
    if (this.actionsRemaining <= 0 && typeof this.onActionsDepleted === 'function') {
      this.onActionsDepleted();
    }
    
    return true;
  }
  
  /**
   * Record an attack for MAP calculation.
   * @returns {number|null} MAP penalty to apply to this attack, or null if attack cannot be made
   * 
   * @example
   * const penalty = actions.makeAttack();
   * if (penalty !== null) {
   *   const attackRoll = d20() + attackBonus + penalty;
   * }
   */
  makeAttack() {
    // Attacks cost one action; abort if unavailable
    if (!this.spendActions(ActionCost.ONE, 'Attack')) {
      return null;
    }

    const currentMAP = this.mapPenalty;
    this.attacksMadeThisTurn++;
    
    // Update MAP for next attack using configurable penalty
    this.mapPenalty = this.attacksMadeThisTurn * this.mapPenaltyPerAttack;
    
    return currentMAP;
  }
  
  /**
   * Get current MAP penalty.
   * @returns {number} Current Multiple Attack Penalty
   */
  getCurrentMAP() {
    return this.mapPenalty;
  }
  
  /**
   * Restore actions at start of turn.
   */
  startTurn() {
    this.actionsRemaining = this.maxActions + this.actionBonus;
    this.hasReaction = true;
    this.attacksMadeThisTurn = 0;
    this.mapPenalty = 0;
    this.actionHistory = [];
    this.canAct = true;
  }
  
  /**
   * End turn cleanup.
   */
  endTurn() {
    this.actionsRemaining = 0;
    // Reaction persists until start of next turn
    // MAP resets at start of turn, not end
  }
  
  /**
   * Check if has actions remaining.
   * @returns {boolean} True if entity has actions remaining
   */
  hasActionsRemaining() {
    return this.actionsRemaining > 0;
  }
  
  /**
   * Check if has reaction available.
   * @returns {boolean} True if reaction is available
   */
  hasReactionAvailable() {
    return this.hasReaction;
  }
  
  /**
   * Get action count with visual representation.
   * @returns {string} Visual representation (e.g., "◆◆◇" for 2 of 3 actions)
   * 
   * @example
   * console.log(actions.getActionDisplay()); // "◆◆◆" (3 actions available)
   */
  getActionDisplay() {
    const filled = '◆'; // Filled diamond (action available)
    const empty = '◇';  // Empty diamond (action used)
    const total = this.maxActions + this.actionBonus;
    
    let display = '';
    for (let i = 0; i < total; i++) {
      display += i < this.actionsRemaining ? filled : empty;
    }
    return display;
  }

  /**
   * Register a callback invoked when actions deplete.
   * @param {Function|null} callback - Callback function, or null to clear
   */
  setOnActionsDepleted(callback) {
    if (callback !== null && typeof callback !== 'function') {
      throw new Error('Callback must be a function or null');
    }
    this.onActionsDepleted = callback;
  }
  
  /**
   * Apply status effect that modifies actions.
   * @param {string} effect - Effect name (reserved for future tracking/logging functionality)
   * @param {number} modifier - Action modifier to add
   * 
   * Note: The effect parameter is currently unused but retained in the signature
   * for API stability and future enhancements (e.g., effect tracking, stacking rules).
   * 
   * @example
   * actions.applyActionModifier('Haste', 1); // Add 1 action
   * actions.applyActionModifier('Slow', -1); // Remove 1 action
   */
  applyActionModifier(effect, modifier) {
    if (typeof modifier !== 'number' || !Number.isFinite(modifier)) {
      throw new Error(`Action modifier must be a finite number, got: ${modifier}`);
    }
    
    this.actionBonus += modifier;
    // Cap between MIN_BONUS and MAX_BONUS
    this.actionBonus = Math.max(MAPConstants.MIN_BONUS, Math.min(MAPConstants.MAX_BONUS, this.actionBonus));
  }
  
  /**
   * Prevent entity from taking actions (e.g., stunned, paralyzed).
   * @param {boolean} canAct - Whether entity can act
   */
  setCanAct(canAct) {
    if (typeof canAct !== 'boolean') {
      throw new Error(`canAct must be a boolean, got: ${typeof canAct}`);
    }
    
    this.canAct = canAct;
    if (!canAct) {
      this.actionsRemaining = 0;
    }
  }
  
  /**
   * Serialize component to JSON.
   * @returns {Object} Serialized component data
   */
  toJSON() {
    return {
      type: this.constructor.name,
      maxActions: this.maxActions,
      actionsRemaining: this.actionsRemaining,
      hasReaction: this.hasReaction,
      attacksMadeThisTurn: this.attacksMadeThisTurn,
      mapPenalty: this.mapPenalty,
      mapPenaltyPerAttack: this.mapPenaltyPerAttack,
      actionHistory: [...this.actionHistory],
      canAct: this.canAct,
      actionBonus: this.actionBonus
    };
  }
  
  /**
   * Deserialize component from JSON.
   * @param {Object} data - Serialized data
   * @returns {ActionsComponent} Deserialized component instance
   */
  static fromJSON(data) {
    const mapPenaltyPerAttack = data.mapPenaltyPerAttack ?? MAPConstants.STANDARD_PENALTY;
    const component = new ActionsComponent(data.maxActions, mapPenaltyPerAttack);
    component.actionsRemaining = data.actionsRemaining;
    component.hasReaction = data.hasReaction;
    component.attacksMadeThisTurn = data.attacksMadeThisTurn;
    component.mapPenalty = data.mapPenalty;
    component.actionHistory = data.actionHistory ? [...data.actionHistory] : [];
    component.canAct = data.canAct;
    component.actionBonus = data.actionBonus || 0;
    return component;
  }
}
