/**
 * @file
 * Base Component class - pure data containers with no logic.
 * 
 * This implements the Component pattern from Entity-Component-System (ECS) architecture.
 * Components are pure data containers with no game logic - logic belongs in Systems.
 * 
 * ## Database Schema Integration
 * 
 * Components serialize to JSON for storage in database TEXT fields (character_data, state_data).
 * The database schema uses a hybrid "hot-column" architecture for performance:
 * 
 * **Hot Columns** (dc_campaign_characters table):
 * - `hp_current`, `hp_max` (int) - Direct column storage for fast combat queries
 * - `armor_class` (int) - AC value for combat resolution
 * - `position_q`, `position_r` (int) - Hex grid coordinates for movement
 * - `experience_points` (int) - XP tracking
 * - `last_room_id` (varchar) - Current location
 * 
 * **JSON Payload Columns**:
 * - `character_data` (TEXT) - Full character sheet as JSON
 * - `state_data` (TEXT) - Campaign-scoped runtime state
 * - `default_character_data` (TEXT) - Template baseline
 * 
 * PHP services extract hot-column values from nested state structure:
 * - `state.resources.hitPoints.current` → `hp_current` column
 * - `state.defenses.armorClass.total` → `armor_class` column
 * - `state.position.{q,r}` → `position_q`, `position_r` columns
 * 
 * Component.toJSON() produces the JSON payload structure. Hot-column extraction
 * is handled by PHP services (CharacterStateService) during database writes.
 * 
 * @see dungeoncrawler_content.install - Schema definitions with hot-column fields
 * @see CharacterStateService::updateCharacterState() - Hot-column extraction logic
 * 
 * @example
 * // Extend Component for custom components
 * class HealthComponent extends Component {
 *   constructor(hp = 100) {
 *     super();
 *     this.currentHp = hp;
 *     this.maxHp = hp;
 *   }
 * }
 * 
 * // Use with entities
 * const health = new HealthComponent(50);
 * const cloned = health.clone();
 * const json = health.toJSON();
 * const restored = HealthComponent.fromJSON(json);
 */

// Sentinel value for circular reference detection
const CIRCULAR_REF = '[Circular]';

export class Component {
  /**
   * Create a new component.
   * Components should be extended by specific component types.
   * 
   * Subclasses should call super() and then initialize their data properties.
   * Do not add methods that contain game logic - use Systems for that.
   */
  constructor() {
    this.type = this.constructor.name;
  }

  /**
   * Serialize component to JSON.
   * Override in subclasses if you need custom serialization logic.
   * 
   * Default implementation serializes all own properties except functions.
   * Automatically handles nested objects and arrays. Circular references are detected
   * and excluded from the output with a console warning.
   * 
   * **Schema Conformance Note**: This method produces JSON for database TEXT columns
   * (character_data, state_data). Hot-column extraction (hp_current, position_q, etc.)
   * is handled server-side by PHP services. Components should structure data to match
   * the expected nested format:
   * - Health data: `resources.hitPoints.{current, max}`
   * - Defense data: `defenses.armorClass.{total, value}`
   * - Position data: `position.{q, r}`
   * - Location data: `location.roomId` or `roomId`
   * 
   * @returns {Object} Serialized component data (plain object)
   */
  toJSON() {
    const data = {
      type: this.type  // Always include type field for proper deserialization
    };
    const seen = new WeakSet();
    // Add this component to seen set immediately to detect self-references
    seen.add(this);
    
    const serialize = (obj) => {
      // Handle primitives and null
      if (obj === null || typeof obj !== 'object') {
        return obj;
      }
      
      // Detect circular references - prevents infinite loops during serialization
      // Circular refs are replaced with a sentinel value and logged as warnings
      if (seen.has(obj)) {
        console.warn('Circular reference detected in component serialization');
        return CIRCULAR_REF;
      }
      seen.add(obj);
      
      // Handle arrays
      if (Array.isArray(obj)) {
        return obj.map(item => serialize(item));
      }
      
      // Handle plain objects
      const result = {};
      for (const [key, value] of Object.entries(obj)) {
        if (typeof value !== 'function') {
          const serialized = serialize(value);
          // Only include valid values (skip circular references)
          if (serialized !== CIRCULAR_REF) {
            result[key] = serialized;
          }
        }
      }
      return result;
    };
    
    // Serialize all component properties (including type already added above)
    for (const [key, value] of Object.entries(this)) {
      if (key !== 'type' && typeof value !== 'function') {
        const serialized = serialize(value);
        // Only include valid values (skip circular references)
        if (serialized !== CIRCULAR_REF) {
          data[key] = serialized;
        }
      }
    }
    return data;
  }

  /**
   * Deserialize component from JSON.
   * Override in subclasses if you need custom deserialization logic.
   * 
   * Default implementation creates a new instance and assigns all properties.
   * Note: This preserves the prototype chain of the component class.
   * 
   * **Schema Conformance Note**: When loading from database, this receives data from
   * JSON TEXT columns (character_data, state_data). Hot-column values (hp_current, etc.)
   * are NOT included in this data - they exist as separate database columns and should
   * be synchronized server-side during save operations.
   * 
   * @param {Object} data - Serialized component data
   * @returns {Component} New component instance
   * @throws {Error} If data is invalid or malformed
   */
  static fromJSON(data) {
    if (!data || typeof data !== 'object') {
      throw new Error('fromJSON requires a valid object');
    }
    
    try {
      const component = new this();
      Object.assign(component, data);
      return component;
    } catch (error) {
      throw new Error(`Failed to deserialize component: ${error.message}`);
    }
  }

  /**
   * Clone this component.
   * Creates a deep copy by serializing and deserializing.
   * 
   * Override in subclasses if you need custom cloning logic or have
   * properties that don't serialize well (e.g., functions, circular refs).
   * 
   * @returns {Component} Cloned component instance
   */
  clone() {
    return this.constructor.fromJSON(this.toJSON());
  }

  /**
   * Validate component data.
   * Override in subclasses to implement validation logic.
   * 
   * For components that map to hot columns, validation should check that:
   * - Data structure matches expected nested format (e.g., resources.hitPoints)
   * - Values are within valid ranges (e.g., currentHp >= 0, ac >= 0)
   * - Required fields for hot-column extraction are present
   * 
   * @returns {boolean} True if component data is valid
   * @example
   * class HealthComponent extends Component {
   *   validate() {
   *     return this.currentHp >= 0 && this.currentHp <= this.maxHp;
   *   }
   * }
   */
  validate() {
    return true;
  }
}
