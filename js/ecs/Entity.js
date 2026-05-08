/**
 * @file
 * Entity class - represents a game object by ID with attached components.
 * 
 * Schema Conformance:
 * This class supports both the ECS component model and database schema alignment:
 * - Component-based state (StatsComponent, PositionComponent, etc.)
 * - Hot columns for database queries (hp, armor_class, position, etc.)
 * - Optional metadata for entity_instance.schema.json compliance
 * 
 * Database Hot Columns (dc_campaign_characters):
 * - hp_current, hp_max, armor_class, experience_points
 * - position_q, position_r, last_room_id
 * 
 * Entity Type Enum (database & API):
 * - 'pc' (player character), 'npc' (non-player character)
 * - 'obstacle', 'trap', 'hazard'
 * 
 * Note: This differs from IdentityComponent.EntityType which uses more granular
 * types (player_character, creature, item, treasure). DatabaseEntityType aligns
 * with dc_campaign_characters.type column constraints.
 */

// Valid entity types per database schema and API documentation
// Aligned with dc_campaign_characters.type column
export const DatabaseEntityType = {
  PLAYER_CHARACTER: 'pc',
  NPC: 'npc',
  OBSTACLE: 'obstacle',
  TRAP: 'trap',
  HAZARD: 'hazard'
};

// Legacy alias for backward compatibility
export const EntityType = DatabaseEntityType;

export class Entity {
  /**
   * Create a new entity.
   * @param {number} id - Unique positive entity ID
   * @param {object} options - Optional configuration
   * @param {string} options.entityType - Entity type (pc|npc|obstacle|trap|hazard)
   * @param {string} options.instanceId - Runtime instance identifier
   * @param {object} options.placement - Placement data (roomId, q, r)
   * @throws {Error} If id is not a positive number or entityType is invalid
   */
  constructor(id, options = {}) {
    if (typeof id !== 'number' || id <= 0 || !Number.isInteger(id)) {
      throw new Error('Entity ID must be a positive integer');
    }
    
    this.id = id;
    this.components = new Map();
    this.active = true;
    
    // Optional metadata for schema conformance
    this.entityType = options.entityType || null;
    this.instanceId = options.instanceId || null;
    this.placement = options.placement || null;
    
    // Validate entityType if provided
    if (this.entityType !== null) {
      const validTypes = Object.values(EntityType);
      if (!validTypes.includes(this.entityType)) {
        throw new Error(`Invalid entity type: ${this.entityType}. Valid types: ${validTypes.join(', ')}`);
      }
    }
  }

  /**
   * Add a component to this entity.
   * @param {string} componentName - Component type name (non-empty string)
   * @param {object} componentData - Component instance (must be an object)
   * @returns {Entity} This entity for chaining
   * @throws {Error} If componentName is empty or componentData is null/undefined
   */
  addComponent(componentName, componentData) {
    if (!componentName || typeof componentName !== 'string') {
      throw new Error('Component name must be a non-empty string');
    }
    if (componentData === null || componentData === undefined) {
      throw new Error('Component data cannot be null or undefined');
    }
    this.components.set(componentName, componentData);
    return this;
  }

  /**
   * Get a component from this entity.
   * @param {string} componentName - Component type name
   * @returns {object|undefined} Component instance or undefined if not found
   */
  getComponent(componentName) {
    return this.components.get(componentName);
  }

  /**
   * Check if entity has a component.
   * @param {string} componentName - Component type name
   * @returns {boolean} True if component exists
   */
  hasComponent(componentName) {
    return this.components.has(componentName);
  }

  /**
   * Remove a component from this entity.
   * @param {string} componentName - Component type name
   * @returns {boolean} True if component was removed
   */
  removeComponent(componentName) {
    return this.components.delete(componentName);
  }

  /**
   * Get all component names.
   * @returns {string[]} Array of component names
   */
  getComponentNames() {
    return Array.from(this.components.keys());
  }

  /**
   * Deactivate this entity (marks for removal).
   */
  destroy() {
    this.active = false;
  }

  /**
   * Check if entity is active.
   * @returns {boolean} True if active
   */
  isActive() {
    return this.active;
  }

  /**
   * Serialize entity to JSON.
   * 
   * Extracts hot columns from components for database optimization:
   * - hp_current, hp_max from StatsComponent
   * - armor_class from StatsComponent
   * - experience_points from StatsComponent or metadata
   * - position_q, position_r from PositionComponent or placement
   * - last_room_id from placement
   * 
   * @returns {object} Serialized entity data with hot columns
   */
  toJSON(format = 'ecs') {
    if (format === 'entity_instance') {
      return this._toEntityInstanceJSON();
    }
    
    // Default ECS format (backward compatible)
    const data = {
      id: this.id,
      active: this.active,
      components: {}
    };

    // Add optional metadata if present
    if (this.entityType !== null) {
      data.entityType = this.entityType;
    }
    if (this.instanceId !== null) {
      data.instanceId = this.instanceId;
    }
    if (this.placement !== null) {
      data.placement = this.placement;
    }

    // Serialize components
    for (const [name, component] of this.components.entries()) {
      if (typeof component.toJSON === 'function') {
        data.components[name] = component.toJSON();
      } else {
        // Deep clone to prevent shared references
        data.components[name] = JSON.parse(JSON.stringify(component));
      }
    }
    
    // Include entity_instance properties if present
    if (this.entity_instance_id) {
      data.entity_instance_id = this.entity_instance_id;
    }
    if (this.entity_type) {
      data.entity_type = this.entity_type;
    }
    if (this.entity_ref) {
      data.entity_ref = this.entity_ref;
    }
    if (this.placement) {
      data.placement = this.placement;
    }

    // Extract hot columns from components for database optimization
    const hotColumns = this._extractHotColumns();
    if (Object.keys(hotColumns).length > 0) {
      data.hotColumns = hotColumns;
    }

    return data;
  }
  
  /**
   * Serialize entity to entity_instance.schema.json format.
   * Converts component-based state to structured state object.
   * 
   * @private
   * @returns {object} Entity instance in schema format
   */
  _toEntityInstanceJSON() {
    // Extract state from components or use defaults
    const state = {
      active: this.active,
      destroyed: false,
      disabled: false,
      hidden: false,
      collected: false,
      hit_points: null,
      inventory: [],
      metadata: {}
    };
    
    // Convert common components to state properties
    const healthComp = this.getComponent('HealthComponent') || this.getComponent('Health');
    if (healthComp) {
      state.hit_points = {
        current: healthComp.currentHp || healthComp.current || 0,
        max: healthComp.maxHp || healthComp.max || 0
      };
    }
    
    const inventoryComp = this.getComponent('InventoryComponent') || this.getComponent('Inventory');
    if (inventoryComp && Array.isArray(inventoryComp.items)) {
      state.inventory = inventoryComp.items;
    }
    
    // Build entity_instance payload
    const data = {
      schema_version: '1.0.0',
      entity_instance_id: this.entity_instance_id || `temp-${this.id}`,
      entity_type: this.entity_type || 'creature',
      entity_ref: this.entity_ref || {
        content_type: 'creature',
        content_id: 'unknown',
        version: null
      },
      placement: this.placement || {
        room_id: 'unknown',
        hex: { q: 0, r: 0 },
        spawn_type: null
      },
      state: state,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    
    return data;
  }

  /**
   * Extract hot column values from components.
   * @private
   * @returns {object} Hot column data
   */
  _extractHotColumns() {
    const hotColumns = {};

    // Extract from StatsComponent if present
    const stats = this.getComponent('StatsComponent');
    if (stats) {
      if (stats.currentHp !== undefined) {
        hotColumns.hp_current = stats.currentHp;
      }
      if (stats.maxHp !== undefined) {
        hotColumns.hp_max = stats.maxHp;
      }
      if (stats.ac !== undefined) {
        hotColumns.armor_class = stats.ac;
      }
      if (stats.experiencePoints !== undefined) {
        hotColumns.experience_points = stats.experiencePoints;
      }
    }

    // Extract from PositionComponent if present
    const position = this.getComponent('PositionComponent');
    if (position) {
      if (position.q !== undefined) {
        hotColumns.position_q = position.q;
      }
      if (position.r !== undefined) {
        hotColumns.position_r = position.r;
      }
    }

    // Extract from placement metadata if present
    if (this.placement) {
      if (this.placement.roomId !== undefined) {
        hotColumns.last_room_id = this.placement.roomId;
      }
      if (this.placement.q !== undefined && hotColumns.position_q === undefined) {
        hotColumns.position_q = this.placement.q;
      }
      if (this.placement.r !== undefined && hotColumns.position_r === undefined) {
        hotColumns.position_r = this.placement.r;
      }
    }

    return hotColumns;
  }

  /**
   * Create entity from JSON data.
   * 
   * Supports both ECS format and entity_instance.schema.json format:
   * - ECS format: {id, active, components, hotColumns}
   * - Metadata: entityType, instanceId, placement
   * - Hot columns are informational only (components are source of truth)
   * 
   * @param {object} data - Serialized entity data with id, active, and components
   * @param {object} componentClasses - Map of component name to class constructor
   * @returns {Entity} Deserialized entity
   * @throws {Error} If data is invalid or missing required fields
   */
  static fromJSON(data, componentClasses) {
    if (!data || typeof data !== 'object') {
      throw new Error('Invalid data: must be an object');
    }
    
    // Detect format: entity_instance has entity_instance_id, ECS has id
    if (data.entity_instance_id && data.entity_type && data.entity_ref) {
      return Entity._fromEntityInstanceJSON(data, componentClasses);
    }
    
    // ECS format (backward compatible)
    if (!data.id) {
      throw new Error('Invalid data: missing required field "id"');
    }
    
    // Extract optional metadata
    const options = {};
    if (data.entityType) {
      options.entityType = data.entityType;
    }
    if (data.instanceId) {
      options.instanceId = data.instanceId;
    }
    if (data.placement) {
      options.placement = data.placement;
    }
    
    const entity = new Entity(data.id, options);
    entity.active = data.active !== undefined ? data.active : true;
    
    // Restore entity_instance properties if present
    if (data.entity_instance_id) {
      entity.entity_instance_id = data.entity_instance_id;
    }
    if (data.entity_type) {
      entity.entity_type = data.entity_type;
    }
    if (data.entity_ref) {
      entity.entity_ref = data.entity_ref;
    }
    if (data.placement) {
      entity.placement = data.placement;
    }

    // Restore components
    if (data.components) {
      for (const [name, componentData] of Object.entries(data.components)) {
        if (componentClasses && componentClasses[name] && componentClasses[name].fromJSON) {
          entity.addComponent(name, componentClasses[name].fromJSON(componentData));
        } else {
          entity.addComponent(name, componentData);
        }
      }
    }

    // Note: hotColumns are not restored to components (they're derived data)
    // Components are the source of truth; hot columns are for database optimization

    return entity;
  }
  
  /**
   * Create entity from entity_instance.schema.json format.
   * Converts structured state to components.
   * 
   * @private
   * @param {object} data - Entity instance in schema format
   * @param {object} componentClasses - Map of component name to class constructor
   * @returns {Entity} Entity with populated components
   * @throws {Error} If data is invalid
   */
  static _fromEntityInstanceJSON(data, componentClasses) {
    // Generate a numeric ID from UUID hash (for ECS compatibility)
    const numericId = Entity._hashUuidToId(data.entity_instance_id);
    
    const entity = new Entity(numericId, {
      entity_instance_id: data.entity_instance_id,
      entity_type: data.entity_type,
      entity_ref: data.entity_ref,
      placement: data.placement
    });
    
    // Set active state from entity_instance state
    if (data.state) {
      entity.active = data.state.active !== undefined ? data.state.active : true;
      
      // Convert state to components
      if (data.state.hit_points) {
        entity.addComponent('HealthComponent', {
          currentHp: data.state.hit_points.current,
          maxHp: data.state.hit_points.max
        });
      }
      
      if (data.state.inventory && Array.isArray(data.state.inventory)) {
        entity.addComponent('InventoryComponent', {
          items: data.state.inventory
        });
      }
      
      // Store additional state flags as MetadataComponent
      if (data.state.destroyed || data.state.disabled || data.state.hidden || data.state.collected) {
        entity.addComponent('StateComponent', {
          destroyed: data.state.destroyed || false,
          disabled: data.state.disabled || false,
          hidden: data.state.hidden || false,
          collected: data.state.collected || false
        });
      }
      
      // Store custom metadata
      if (data.state.metadata && Object.keys(data.state.metadata).length > 0) {
        entity.addComponent('MetadataComponent', data.state.metadata);
      }
    }
    
    // Add placement as PositionComponent if available
    if (data.placement && data.placement.hex) {
      entity.addComponent('PositionComponent', {
        q: data.placement.hex.q,
        r: data.placement.hex.r,
        room_id: data.placement.room_id
      });
    }
    
    return entity;
  }
  
  /**
   * Convert UUID to numeric ID via simple hash.
   * Used for entity_instance_id to ECS id conversion.
   * 
   * @private
   * @param {string} uuid - UUID string
   * @returns {number} Positive integer ID
   */
  static _hashUuidToId(uuid) {
    let hash = 0;
    for (let i = 0; i < uuid.length; i++) {
      const char = uuid.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash) || 1; // Ensure positive
  }
}
