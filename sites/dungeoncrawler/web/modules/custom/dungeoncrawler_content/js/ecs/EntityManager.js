/**
 * @file
 * EntityManager - manages all entities and provides query functionality.
 * 
 * Database Schema Integration:
 * ============================
 * This EntityManager serializes entities to JSON for storage in the
 * dc_campaign_characters.character_data field (TEXT BIG). The database
 * uses a hybrid storage model:
 * 
 * Hot Columns (indexed for fast queries):
 * - position_q, position_r: Mirrors PositionComponent.q/.r
 * - hp_current, hp_max: Mirrors StatsComponent.currentHp/.maxHp
 * - armor_class: Mirrors StatsComponent.ac
 * - type: Mirrors IdentityComponent.entityType (see EntityType enum)
 * - instance_id: Maps to Entity.id for runtime ECS reference
 * 
 * JSON Columns (full entity state):
 * - character_data: Full Entity.toJSON() output with all components
 * - state_data: Campaign-scoped runtime deltas (merged on load)
 * - default_character_data: Template baseline for resets
 * 
 * Type Mapping:
 * -------------
 * DB 'type' field     | EntityType enum value
 * --------------------|------------------------
 * 'pc'                | 'player_character'
 * 'npc'               | 'npc'
 * 'obstacle'          | 'obstacle'
 * 'trap'              | 'trap'
 * 'hazard'            | 'hazard'
 * 
 * Data Integrity:
 * ---------------
 * When loading/saving entities, ensure hot columns are synchronized with
 * their corresponding component values to maintain query accuracy.
 * 
 * @see /sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.install
 * @see Entity.toJSON() for serialization format
 * @see components/PositionComponent.js, StatsComponent.js, IdentityComponent.js
 */

import { Entity } from './Entity.js';

/**
 * Schema version for EntityManager serialization.
 * Increment when making breaking changes to JSON structure.
 */
export const ENTITY_MANAGER_SCHEMA_VERSION = 1;

export class EntityManager {
  constructor() {
    this.entities = new Map();
    this.nextEntityId = 1;
    this.systems = [];
    
    // Cache for component queries
    this.queryCache = new Map();
    
    // Track active entity count for optimization
    this._activeCount = 0;
  }

  /**
   * Create a new entity.
   * @returns {Entity} New entity
   */
  createEntity() {
    const entity = new Entity(this.nextEntityId++);
    this.entities.set(entity.id, entity);
    this._activeCount++;
    this.invalidateQueryCache();
    
    return entity;
  }

  /**
   * Get entity by ID.
   * @param {number} id - Entity ID
   * @returns {Entity|undefined} Entity or undefined
   */
  getEntity(id) {
    return this.entities.get(id);
  }

  /**
   * Remove entity by ID.
   * @param {number} id - Entity ID
   * @returns {boolean} True if entity was removed
   */
  removeEntity(id) {
    const entity = this.entities.get(id);
    if (entity) {
      if (entity.isActive()) {
        this._activeCount--;
      }
      entity.destroy();
      this.entities.delete(id);
      this.invalidateQueryCache();
      return true;
    }
    return false;
  }

  /**
   * Get all entities.
   * @returns {Entity[]} Array of all entities
   */
  getAllEntities() {
    return Array.from(this.entities.values()).filter(e => e.isActive());
  }

  /**
   * Get entities that have all specified components.
   * Results are cached for performance.
   * @param {...string} componentNames - Component names to query
   * @returns {Entity[]} Array of matching entities
   */
  getEntitiesWith(...componentNames) {
    // Create cache key
    const cacheKey = componentNames.sort().join('|');
    
    // Check cache
    if (this.queryCache.has(cacheKey)) {
      return this.queryCache.get(cacheKey);
    }

    // Query entities
    const result = [];
    for (const entity of this.entities.values()) {
      if (!entity.isActive()) continue;
      
      if (componentNames.every(name => entity.hasComponent(name))) {
        result.push(entity);
      }
    }

    // Cache result
    this.queryCache.set(cacheKey, result);
    return result;
  }

  /**
   * Get entities that have any of the specified components.
   * @param {...string} componentNames - Component names to query
   * @returns {Entity[]} Array of matching entities
   */
  getEntitiesWithAny(...componentNames) {
    const result = [];
    for (const entity of this.entities.values()) {
      if (!entity.isActive()) continue;
      
      if (componentNames.some(name => entity.hasComponent(name))) {
        result.push(entity);
      }
    }
    return result;
  }

  /**
   * Get entity count.
   * @returns {number} Number of active entities
   */
  getEntityCount() {
    return this._activeCount;
  }

  /**
   * Clear all entities.
   */
  clear() {
    this.entities.clear();
    this.nextEntityId = 1;
    this._activeCount = 0;
    this.invalidateQueryCache();
  }

  /**
   * Backwards-compatible alias for clear().
   */
  removeAllEntities() {
    this.clear();
  }

  /**
   * Invalidate query cache (call when entities change).
   * 
   * Cache Invalidation Strategy:
   * - Called automatically on entity creation/removal
   * - Should be called manually after bulk component changes
   * - Does NOT auto-invalidate on component modifications (performance)
   * 
   * Manual invalidation required after:
   * - entity.addComponent() / removeComponent()
   * - Batch entity updates via system processing
   */
  invalidateQueryCache() {
    this.queryCache.clear();
  }

  /**
   * Add a system to the manager.
   * @param {System} system - System instance
   * @throws {TypeError} If system is null or undefined
   */
  addSystem(system) {
    if (!system) {
      throw new TypeError('System cannot be null or undefined');
    }
    
    this.systems.push(system);
    this.systems.sort((a, b) => (a.priority || 0) - (b.priority || 0));
    
    if (typeof system.init === 'function') {
      system.init();
    }
  }

  /**
   * Remove a system from the manager.
   * @param {System} system - System instance
   * @returns {boolean} True if system was removed
   */
  removeSystem(system) {
    const index = this.systems.indexOf(system);
    if (index !== -1) {
      this.systems.splice(index, 1);
      if (typeof system.destroy === 'function') {
        system.destroy();
      }
      return true;
    }
    return false;
  }

  /**
   * Update all systems.
   * @param {number} deltaTime - Time since last update (ms)
   */
  update(deltaTime) {
    for (const system of this.systems) {
      if (system.isEnabled()) {
        system.update(deltaTime);
      }
    }
  }

  /**
   * Serialize all entities to JSON.
   * 
   * Output format:
   * {
   *   version: 1,  // Schema version for migration support
   *   nextEntityId: 1234,
   *   entities: [
   *     {
   *       id: 1,
   *       active: true,
   *       components: {
   *         PositionComponent: { q: 5, r: 3, elevation: 0, facing: 0 },
   *         StatsComponent: { currentHp: 45, maxHp: 50, ac: 15, ... },
   *         IdentityComponent: { name: "Hero", entityType: "player_character", ... }
   *       }
   *     },
   *     ...
   *   ]
   * }
   * 
   * Storage Contract:
   * - This JSON is stored in dc_campaign_characters.character_data
   * - Hot columns (hp_current, position_q/r, armor_class) must be synced separately
   * - Entity.id maps to dc_campaign_characters.instance_id
   * - IdentityComponent.entityType must map to dc_campaign_characters.type
   * 
   * @returns {object} Serialized data with version, nextEntityId, and entities array
   */
  toJSON(format = 'ecs') {
    const entities = [];
    for (const entity of this.entities.values()) {
      if (entity.isActive()) {
        entities.push(entity.toJSON(format));
      }
    }
    
    if (format === 'entity_instance') {
      // Return array of entity_instances for schema conformance
      return entities;
    }

    // ECS format includes manager metadata
    return {
      version: ENTITY_MANAGER_SCHEMA_VERSION,
      nextEntityId: this.nextEntityId,
      entities: entities
    };
  }

  /**
   * Deserialize entities from JSON.
   * 
   * Supports two data sources:
   * 1. character_data: Full entity state from dc_campaign_characters.character_data
   * 2. state_data: Campaign runtime deltas from dc_campaign_characters.state_data
   * 
   * Data Merge Strategy:
   * - If only character_data provided: Load as-is
   * - If state_data provided: Merge state_data over character_data (deep merge)
   * - Hot columns (hp_current, position_q/r) should already reflect state_data
   * 
   * Migration Support:
   * - Checks data.version and applies migrations if needed
   * - Legacy data without version field treated as version 1
   * - Future versions should implement migration logic here
   * 
   * @param {object} data - Serialized data with version, nextEntityId, entities array
   * @param {object} componentClasses - Map of component name to class constructor
   * @throws {TypeError} If data is invalid or missing required fields
   * @example
   * // Load from database
   * const data = JSON.parse(dbRow.character_data);
   * manager.fromJSON(data, {
   *   PositionComponent: PositionComponent,
   *   StatsComponent: StatsComponent,
   *   IdentityComponent: IdentityComponent
   * });
   */
  fromJSON(data, componentClasses) {
    if (!data) {
      throw new TypeError('Data cannot be null or undefined');
    }
    
    // Handle entity_instance array format
    if (Array.isArray(data)) {
      this.clear();
      for (const entityData of data) {
        const entity = Entity.fromJSON(entityData, componentClasses);
        this.entities.set(entity.id, entity);
        if (entity.isActive()) {
          this._activeCount++;
        }
        // Track highest ID for proper nextEntityId
        if (entity.id >= this.nextEntityId) {
          this.nextEntityId = entity.id + 1;
        }
      }
      this.invalidateQueryCache();
      return;
    }
    
    // Handle ECS format
    if (typeof data !== 'object') {
      throw new TypeError('Data must be a valid object or array');
    }
    if (!Array.isArray(data.entities)) {
      throw new TypeError('Data.entities must be an array');
    }
    
    // Check schema version for migrations (future-proofing)
    const version = data.version || 1;
    if (version !== ENTITY_MANAGER_SCHEMA_VERSION) {
      console.warn(`EntityManager: Loading data version ${version}, current version is ${ENTITY_MANAGER_SCHEMA_VERSION}`);
      // Future: Apply migrations here if version < ENTITY_MANAGER_SCHEMA_VERSION
    }
    
    this.clear();
    this.nextEntityId = data.nextEntityId || 1;

    for (const entityData of data.entities) {
      const entity = Entity.fromJSON(entityData, componentClasses);
      this.entities.set(entity.id, entity);
      if (entity.isActive()) {
        this._activeCount++;
      }
    }

    this.invalidateQueryCache();
  }
}
