/**
 * @file
 * EntityInstanceMapper - utility for converting between Entity (ECS) format
 * and entity_instance.schema.json format.
 * 
 * This mapper bridges the gap between the component-based ECS architecture
 * and the structured entity_instance schema used for database persistence
 * and API communication.
 */

import { Entity } from './Entity.js';

/**
 * Mapper utilities for Entity <-> entity_instance conversions.
 */
export class EntityInstanceMapper {
  /**
   * Convert Entity to entity_instance.schema.json format.
   * 
   * @param {Entity} entity - ECS entity to convert
   * @param {object} overrides - Optional property overrides
   * @returns {object} entity_instance formatted object
   * @throws {Error} If entity is invalid
   * 
   * @example
   * const entity = new Entity(1, {
   *   entity_instance_id: 'uuid-123',
   *   entity_type: 'creature',
   *   entity_ref: { content_type: 'creature', content_id: 'goblin_001' },
   *   placement: { room_id: 'room-123', hex: { q: 2, r: 3 } }
   * });
   * entity.addComponent('HealthComponent', { currentHp: 10, maxHp: 15 });
   * const instanceData = EntityInstanceMapper.toEntityInstance(entity);
   */
  static toEntityInstance(entity, overrides = {}) {
    if (!entity || !(entity instanceof Entity)) {
      throw new Error('Invalid entity: must be an Entity instance');
    }
    
    const data = entity.toJSON('entity_instance');
    
    // Apply overrides
    return {
      ...data,
      ...overrides
    };
  }
  
  /**
   * Convert entity_instance.schema.json format to Entity.
   * 
   * @param {object} instanceData - entity_instance formatted object
   * @param {object} componentClasses - Optional component class mappings
   * @returns {Entity} ECS entity
   * @throws {Error} If instanceData is invalid
   * 
   * @example
   * const instanceData = {
   *   entity_instance_id: 'uuid-123',
   *   entity_type: 'creature',
   *   entity_ref: { content_type: 'creature', content_id: 'goblin_001' },
   *   placement: { room_id: 'room-123', hex: { q: 2, r: 3 } },
   *   state: {
   *     active: true,
   *     hit_points: { current: 10, max: 15 },
   *     inventory: []
   *   }
   * };
   * const entity = EntityInstanceMapper.fromEntityInstance(instanceData);
   */
  static fromEntityInstance(instanceData, componentClasses = null) {
    if (!instanceData || typeof instanceData !== 'object') {
      throw new Error('Invalid instanceData: must be an object');
    }
    
    return Entity.fromJSON(instanceData, componentClasses);
  }
  
  /**
   * Convert array of Entities to entity_instance format.
   * Useful for batch operations and API responses.
   * 
   * @param {Entity[]} entities - Array of entities
   * @returns {object[]} Array of entity_instance objects
   * 
   * @example
   * const entities = [entity1, entity2, entity3];
   * const instances = EntityInstanceMapper.toEntityInstanceArray(entities);
   */
  static toEntityInstanceArray(entities) {
    if (!Array.isArray(entities)) {
      throw new Error('Invalid entities: must be an array');
    }
    
    return entities.map(entity => EntityInstanceMapper.toEntityInstance(entity));
  }
  
  /**
   * Convert array of entity_instance objects to Entities.
   * Useful for hydrating ECS from database or API.
   * 
   * @param {object[]} instanceArray - Array of entity_instance objects
   * @param {object} componentClasses - Optional component class mappings
   * @returns {Entity[]} Array of entities
   * 
   * @example
   * const instances = [instanceData1, instanceData2, instanceData3];
   * const entities = EntityInstanceMapper.fromEntityInstanceArray(instances);
   */
  static fromEntityInstanceArray(instanceArray, componentClasses = null) {
    if (!Array.isArray(instanceArray)) {
      throw new Error('Invalid instanceArray: must be an array');
    }
    
    return instanceArray.map(instance => 
      EntityInstanceMapper.fromEntityInstance(instance, componentClasses)
    );
  }
  
  /**
   * Validate entity_instance data against schema requirements.
   * Checks for required fields and basic structure conformance.
   * 
   * @param {object} instanceData - entity_instance object to validate
   * @returns {object} Validation result { valid: boolean, errors: string[] }
   * 
   * @example
   * const result = EntityInstanceMapper.validate(instanceData);
   * if (!result.valid) {
   *   console.error('Validation errors:', result.errors);
   * }
   */
  static validate(instanceData) {
    const errors = [];
    
    if (!instanceData || typeof instanceData !== 'object') {
      return { valid: false, errors: ['Data must be an object'] };
    }
    
    // Check required fields per entity_instance.schema.json
    const requiredFields = [
      'entity_instance_id',
      'entity_type',
      'entity_ref',
      'placement',
      'state',
      'schema_version'
    ];
    
    for (const field of requiredFields) {
      if (!(field in instanceData)) {
        errors.push(`Missing required field: ${field}`);
      }
    }
    
    // Validate entity_type enum
    if (instanceData.entity_type && 
        !['creature', 'item', 'obstacle'].includes(instanceData.entity_type)) {
      errors.push(`Invalid entity_type: must be creature, item, or obstacle`);
    }
    
    // Validate entity_ref structure
    if (instanceData.entity_ref) {
      if (!instanceData.entity_ref.content_type) {
        errors.push('entity_ref missing required field: content_type');
      }
      if (!instanceData.entity_ref.content_id) {
        errors.push('entity_ref missing required field: content_id');
      }
    }
    
    // Validate placement structure
    if (instanceData.placement) {
      if (!instanceData.placement.room_id) {
        errors.push('placement missing required field: room_id');
      }
      if (!instanceData.placement.hex) {
        errors.push('placement missing required field: hex');
      } else {
        if (typeof instanceData.placement.hex.q !== 'number') {
          errors.push('placement.hex missing required field: q (integer)');
        }
        if (typeof instanceData.placement.hex.r !== 'number') {
          errors.push('placement.hex missing required field: r (integer)');
        }
      }
    }
    
    // Validate state structure
    if (instanceData.state) {
      if (typeof instanceData.state.active !== 'boolean') {
        errors.push('state.active must be a boolean');
      }
    }
    
    // Validate schema_version format
    if (instanceData.schema_version && 
        !/^\d+\.\d+\.\d+$/.test(instanceData.schema_version)) {
      errors.push('schema_version must follow semantic versioning (e.g., "1.0.0")');
    }
    
    return {
      valid: errors.length === 0,
      errors: errors
    };
  }
  
  /**
   * Create a minimal valid entity_instance object for testing.
   * 
   * @param {object} overrides - Properties to override defaults
   * @returns {object} Valid entity_instance object
   * 
   * @example
   * const testInstance = EntityInstanceMapper.createTestInstance({
   *   entity_type: 'item',
   *   entity_ref: { content_type: 'item', content_id: 'healing_potion' }
   * });
   */
  static createTestInstance(overrides = {}) {
    const defaults = {
      schema_version: '1.0.0',
      entity_instance_id: `test-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      entity_type: 'creature',
      entity_ref: {
        content_type: 'creature',
        content_id: 'test_creature',
        version: null
      },
      placement: {
        room_id: 'test-room',
        hex: { q: 0, r: 0 },
        spawn_type: null
      },
      state: {
        active: true,
        destroyed: false,
        disabled: false,
        hidden: false,
        collected: false,
        hit_points: null,
        inventory: [],
        metadata: {}
      },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    
    return {
      ...defaults,
      ...overrides,
      // Deep merge entity_ref if provided
      entity_ref: overrides.entity_ref ? {
        ...defaults.entity_ref,
        ...overrides.entity_ref
      } : defaults.entity_ref,
      // Deep merge placement if provided
      placement: overrides.placement ? {
        ...defaults.placement,
        ...overrides.placement,
        hex: overrides.placement.hex ? {
          ...defaults.placement.hex,
          ...overrides.placement.hex
        } : defaults.placement.hex
      } : defaults.placement,
      // Deep merge state if provided
      state: overrides.state ? {
        ...defaults.state,
        ...overrides.state
      } : defaults.state
    };
  }
}
