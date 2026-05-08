/**
 * @file MovementComponent.js
 * Component for entity movement capabilities and state.
 * 
 * ## Database Schema Mapping
 * 
 * This component maps to the entity_instance.state.metadata fields when persisted
 * to the database. Movement data is stored as part of the extensible metadata object
 * in the state_data JSON column for runtime state tracking.
 * 
 * ### ECS to Database Mapping:
 * 
 * **MovementComponent fields → state_data.metadata:**
 * - `movementRemaining` → state_data.metadata.movementRemaining (feet remaining this turn)
 * - `movementMode` → state_data.metadata.movementMode ('walk', 'fly', 'swim', 'burrow', 'climb')
 * - `movementSpeed` → state_data.metadata.movementSpeed (base speed in feet)
 * - `canMove` → state_data.metadata.canMove (boolean, can move this turn)
 * - `path` → state_data.metadata.path (array of hex coordinates)
 * - `hexMovementCost` → state_data.metadata.hexMovementCost (cost per hex in feet)
 * - `movementModes` → state_data.metadata.movementModes (available movement types/speeds)
 * 
 * ### Hot Columns:
 * No hot columns for MovementComponent. Position tracking uses PositionComponent's
 * position_q and position_r hot columns in dc_campaign_characters table.
 * 
 * ### Schema References:
 * - entity_instance.schema.json: state.metadata (extensible object)
 * - dc_campaign_characters table: state_data (JSON column)
 * 
 * ### Non-Persisted Fields:
 * - `onMovementDepleted` - Runtime callback, not serialized to database
 * 
 * @see /config/schemas/entity_instance.schema.json
 * @see Component.toJSON() for serialization pattern
 */

import { Component } from '../Component.js';

/**
 * Movement modes supported by the system.
 */
export const MovementMode = {
  WALK: 'walk',
  FLY: 'fly',
  SWIM: 'swim',
  BURROW: 'burrow',
  CLIMB: 'climb'
};

/**
 * Default movement speed in feet per round.
 * @const {number}
 */
export const DEFAULT_MOVEMENT_SPEED = 30;

/**
 * Standard hex movement cost in feet.
 * @const {number}
 */
export const DEFAULT_HEX_MOVEMENT_COST = 5;

/**
 * MovementComponent
 * 
 * Stores movement-related data for entities that can move.
 * Tracks movement budget, speed, mode, and planned paths.
 */
export class MovementComponent extends Component {
  /**
   * @param {number} movementSpeed - Base movement speed in feet (typically 25 or 30)
   * @throws {Error} If movementSpeed is invalid (negative or NaN)
   */
  constructor(movementSpeed = DEFAULT_MOVEMENT_SPEED) {
    super();
    
    // Validate input
    if (typeof movementSpeed !== 'number' || isNaN(movementSpeed)) {
      throw new Error('movementSpeed must be a valid number');
    }
    if (movementSpeed < 0) {
      throw new Error('movementSpeed cannot be negative');
    }
    
    // Can this entity move this turn?
    this.canMove = true;
    
    // Movement remaining this turn (feet)
    this.movementRemaining = movementSpeed;
    
    // Base movement speed (feet per round)
    this.movementSpeed = movementSpeed;
    
    // Current movement mode
    this.movementMode = MovementMode.WALK;
    
    // Planned path as array of {q, r} coordinates
    this.path = [];
    
    // Movement costs per hex (modified by terrain)
    this.hexMovementCost = DEFAULT_HEX_MOVEMENT_COST;
    
    // Available movement modes with speeds
    this.movementModes = {
      [MovementMode.WALK]: movementSpeed,
      [MovementMode.FLY]: 0,
      [MovementMode.SWIM]: 0,
      [MovementMode.BURROW]: 0,
      [MovementMode.CLIMB]: 0
    };

    // Optional hook when movement hits zero
    this.onMovementDepleted = null;
  }
  
  /**
   * Set movement mode and update current budget.
   * @param {string} mode - Movement mode from MovementMode enum
   * @returns {boolean} True if mode was set, false if unavailable
   * @throws {Error} If mode is not a valid MovementMode value
   */
  setMovementMode(mode) {
    // Validate mode is a valid MovementMode value
    const validModes = Object.values(MovementMode);
    if (!validModes.includes(mode)) {
      throw new Error(`Invalid movement mode: ${mode}. Must be one of: ${validModes.join(', ')}`);
    }
    
    if (this.movementModes[mode] > 0) {
      this.movementMode = mode;
      this.movementRemaining = this.movementModes[mode];
      return true;
    }
    return false;
  }
  
  /**
   * Check if entity has a specific movement mode.
   * @param {string} mode - Movement mode to check
   * @returns {boolean}
   */
  hasMovementMode(mode) {
    return this.movementModes[mode] > 0;
  }
  
  /**
   * Add or update a movement mode.
   * @param {string} mode - Movement mode
   * @param {number} speed - Speed in feet
   * @throws {Error} If mode or speed are invalid
   */
  addMovementMode(mode, speed) {
    // Validate mode
    const validModes = Object.values(MovementMode);
    if (!validModes.includes(mode)) {
      throw new Error(`Invalid movement mode: ${mode}. Must be one of: ${validModes.join(', ')}`);
    }
    
    // Validate speed
    if (typeof speed !== 'number' || isNaN(speed)) {
      throw new Error('speed must be a valid number');
    }
    if (speed < 0) {
      throw new Error('speed cannot be negative');
    }
    
    this.movementModes[mode] = speed;
  }
  
  /**
   * Consume movement budget.
   * @param {number} cost - Movement cost in feet
   * @returns {boolean} - True if movement was consumed, false if insufficient
   * @throws {Error} If cost is invalid
   */
  consumeMovement(cost) {
    // Validate cost
    if (typeof cost !== 'number' || isNaN(cost)) {
      throw new Error('cost must be a valid number');
    }
    if (cost < 0) {
      throw new Error('cost cannot be negative');
    }
    
    if (this.movementRemaining >= cost) {
      const hadMovement = this.movementRemaining > 0;
      this.movementRemaining -= cost;

      // Only trigger callback once when movement hits zero
      if (hadMovement && this.movementRemaining <= 0 && typeof this.onMovementDepleted === 'function') {
        this.onMovementDepleted();
      }

      return true;
    }
    return false;
  }
  
  /**
   * Restore movement budget (e.g., at start of turn).
   */
  restoreMovement() {
    this.movementRemaining = this.movementModes[this.movementMode];
    this.canMove = true;
    this.path = [];
  }

  /**
   * Register a callback invoked when movement depletes.
   * @param {Function|null} callback
   */
  setOnMovementDepleted(callback) {
    this.onMovementDepleted = callback;
  }
  
  /**
   * Get number of hexes this entity can move.
   * @returns {number}
   */
  getMaxHexes() {
    return Math.floor(this.movementRemaining / this.hexMovementCost);
  }
  
  /**
   * Validate component data for schema conformance.
   * 
   * Ensures all movement data conforms to expected types and value ranges before
   * persistence to database. This validation is called by fromJSON() during
   * deserialization to guarantee data integrity.
   * 
   * @returns {boolean} True if component data is valid and schema-compliant
   * 
   * @example
   * const movement = new MovementComponent(30);
   * movement.movementSpeed = -5; // Invalid
   * movement.validate(); // Returns false
   */
  validate() {
    // Check numeric values are valid
    if (typeof this.movementSpeed !== 'number' || isNaN(this.movementSpeed) || this.movementSpeed < 0) {
      return false;
    }
    if (typeof this.movementRemaining !== 'number' || isNaN(this.movementRemaining) || this.movementRemaining < 0) {
      return false;
    }
    if (typeof this.hexMovementCost !== 'number' || isNaN(this.hexMovementCost) || this.hexMovementCost <= 0) {
      return false;
    }
    
    // Check movement mode is valid
    const validModes = Object.values(MovementMode);
    if (!validModes.includes(this.movementMode)) {
      return false;
    }
    
    // Check canMove is boolean
    if (typeof this.canMove !== 'boolean') {
      return false;
    }
    
    // Check path is an array
    if (!Array.isArray(this.path)) {
      return false;
    }
    
    // Check movementModes object has valid structure
    if (typeof this.movementModes !== 'object' || this.movementModes === null) {
      return false;
    }
    
    // Validate each movement mode speed
    for (const [mode, speed] of Object.entries(this.movementModes)) {
      if (!validModes.includes(mode)) {
        return false;
      }
      if (typeof speed !== 'number' || isNaN(speed) || speed < 0) {
        return false;
      }
    }
    
    return true;
  }
  
  /**
   * Serialize component to JSON for database persistence.
   * 
   * Serializes all movement-related state for storage in entity_instance.state.metadata.
   * The `type` field enables component identification during deserialization.
   * The `movementModes` object is shallow-copied to prevent reference issues.
   * 
   * This method explicitly lists all persisted fields to ensure callbacks like
   * `onMovementDepleted` are excluded from serialization.
   * 
   * @returns {Object} Serialized component data conforming to state_data.metadata schema
   * 
   * @example
   * const movement = new MovementComponent(30);
   * const json = movement.toJSON();
   * // json = {
   * //   type: 'MovementComponent',
   * //   canMove: true,
   * //   movementRemaining: 30,
   * //   movementSpeed: 30,
   * //   movementMode: 'walk',
   * //   path: [],
   * //   hexMovementCost: 5,
   * //   movementModes: { walk: 30, fly: 0, swim: 0, burrow: 0, climb: 0 }
   * // }
   */
  toJSON() {
    return {
      type: this.constructor.name,
      canMove: this.canMove,
      movementRemaining: this.movementRemaining,
      movementSpeed: this.movementSpeed,
      movementMode: this.movementMode,
      path: this.path,
      hexMovementCost: this.hexMovementCost,
      movementModes: {...this.movementModes}
    };
  }
  
  /**
   * Deserialize component from JSON loaded from database.
   * 
   * Reconstructs a MovementComponent from state_data.metadata. Validates all required
   * fields and performs type checking to ensure data integrity. The resulting component
   * is validated before being returned.
   * 
   * @param {Object} data - Serialized data from state_data.metadata
   * @returns {MovementComponent} Reconstructed component instance
   * @throws {Error} If data is invalid or missing required fields
   * 
   * @example
   * const data = {
   *   movementSpeed: 30,
   *   canMove: true,
   *   movementRemaining: 15,
   *   movementMode: 'walk',
   *   hexMovementCost: 5,
   *   movementModes: { walk: 30, fly: 0 },
   *   path: [{q: 0, r: 1}, {q: 1, r: 1}]
   * };
   * const movement = MovementComponent.fromJSON(data);
   */
  static fromJSON(data) {
    // Validate input
    if (!data || typeof data !== 'object') {
      throw new Error('fromJSON requires a valid object');
    }
    
    // Validate required fields
    if (typeof data.movementSpeed !== 'number') {
      throw new Error('Missing or invalid movementSpeed in data');
    }
    if (typeof data.canMove !== 'boolean') {
      throw new Error('Missing or invalid canMove in data');
    }
    if (typeof data.movementRemaining !== 'number') {
      throw new Error('Missing or invalid movementRemaining in data');
    }
    if (typeof data.movementMode !== 'string') {
      throw new Error('Missing or invalid movementMode in data');
    }
    if (typeof data.hexMovementCost !== 'number') {
      throw new Error('Missing or invalid hexMovementCost in data');
    }
    if (!data.movementModes || typeof data.movementModes !== 'object') {
      throw new Error('Missing or invalid movementModes in data');
    }
    
    const component = new MovementComponent(data.movementSpeed);
    component.canMove = data.canMove;
    component.movementRemaining = data.movementRemaining;
    component.movementMode = data.movementMode;
    component.path = Array.isArray(data.path) ? data.path : [];
    component.hexMovementCost = data.hexMovementCost;
    component.movementModes = {...data.movementModes};
    
    // Validate the resulting component
    if (!component.validate()) {
      throw new Error('Deserialized component failed validation');
    }
    
    return component;
  }
}
