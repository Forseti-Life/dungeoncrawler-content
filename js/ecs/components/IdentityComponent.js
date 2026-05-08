/**
 * @file
 * IdentityComponent - entity name, type, and description.
 * 
 * Schema Conformance Notes:
 * ------------------------
 * This component uses a more granular type system than entity_instance.schema.json
 * for gameplay logic purposes. The schema uses entity_type with 3 values:
 * - "creature" (includes player_character, npc, creature)
 * - "item" (includes item, treasure)  
 * - "obstacle" (includes obstacle, trap, hazard)
 * 
 * When serializing to entity_instance format for database storage, use the
 * getSchemaEntityType() method to convert from ECS entityType to schema entity_type.
 * 
 * The entity_ref.content_type in the schema provides additional detail:
 * ["creature", "item", "obstacle", "trap", "hazard"]
 */

import { Component } from '../Component.js';

export const EntityType = {
  PLAYER_CHARACTER: 'player_character',
  NPC: 'npc',
  CREATURE: 'creature',
  ITEM: 'item',
  OBSTACLE: 'obstacle',
  TRAP: 'trap',
  TREASURE: 'treasure',
  HAZARD: 'hazard'
};

/**
 * IdentityComponent
 * 
 * Stores identity information for entities including name, type, description, and tags.
 * 
 * @property {string} name - Entity name
 * @property {string} entityType - Entity type from EntityType enum (granular ECS types)
 * @property {string} description - Entity description
 * @property {string[]} tags - Array of string tags for flexible categorization
 * 
 * Type System:
 * -----------
 * This component uses granular types for gameplay logic:
 * - PLAYER_CHARACTER, NPC, CREATURE - Different creature subtypes
 * - ITEM, TREASURE - Different item subtypes
 * - OBSTACLE, TRAP, HAZARD - Different obstacle subtypes
 * 
 * For database/schema serialization, these map to 3 entity_type values:
 * - "creature" (player_character, npc, creature)
 * - "item" (item, treasure)
 * - "obstacle" (obstacle, trap, hazard)
 */
export class IdentityComponent extends Component {
  /**
   * Create an identity component.
   * @param {string} name - Entity name
   * @param {string} entityType - Entity type from EntityType enum
   * @param {string} description - Entity description
   */
  constructor(name = 'Unnamed', entityType = EntityType.CREATURE, description = '') {
    super();
    this.name = name;
    this.entityType = entityType;
    this.description = description;
    this.tags = [];
  }

  /**
   * Add a tag.
   * @param {string} tag - Tag to add
   */
  addTag(tag) {
    if (!this.tags.includes(tag)) {
      this.tags.push(tag);
    }
  }

  /**
   * Remove a tag.
   * @param {string} tag - Tag to remove
   */
  removeTag(tag) {
    const index = this.tags.indexOf(tag);
    if (index !== -1) {
      this.tags.splice(index, 1);
    }
  }

  /**
   * Check if has tag.
   * @param {string} tag - Tag to check
   * @returns {boolean} True if has tag
   */
  hasTag(tag) {
    return this.tags.includes(tag);
  }

  /**
   * Check if entity is a player character.
   * @returns {boolean} True if player character
   */
  isPlayerCharacter() {
    return this.entityType === EntityType.PLAYER_CHARACTER;
  }

  /**
   * Check if entity is an NPC.
   * @returns {boolean} True if NPC
   */
  isNPC() {
    return this.entityType === EntityType.NPC;
  }

  /**
   * Check if entity is a creature (including NPCs and player characters).
   * @returns {boolean} True if creature, NPC, or player character
   */
  isCreature() {
    return this.entityType === EntityType.CREATURE || 
           this.entityType === EntityType.NPC ||
           this.entityType === EntityType.PLAYER_CHARACTER;
  }

  /**
   * Check if entity is an item.
   * @returns {boolean} True if item
   */
  isItem() {
    return this.entityType === EntityType.ITEM || this.entityType === EntityType.TREASURE;
  }

  /**
   * Check if entity blocks movement.
   * Entities that block movement make their hex impassable to other entities.
   * @returns {boolean} True if blocks movement
   */
  blocksMovement() {
    return this.entityType === EntityType.OBSTACLE || 
           this.entityType === EntityType.CREATURE ||
           this.entityType === EntityType.NPC ||
           this.entityType === EntityType.PLAYER_CHARACTER;
  }

  /**
   * Get entity_type for entity_instance.schema.json format.
   * Converts granular ECS entityType to the 3-value schema entity_type.
   * 
   * Schema entity_type values:
   * - "creature": player_character, npc, creature
   * - "item": item, treasure
   * - "obstacle": obstacle, trap, hazard
   * 
   * @returns {string} Schema-compatible entity_type value
   */
  getSchemaEntityType() {
    if (this.entityType === EntityType.PLAYER_CHARACTER || 
        this.entityType === EntityType.NPC || 
        this.entityType === EntityType.CREATURE) {
      return 'creature';
    }
    if (this.entityType === EntityType.ITEM || 
        this.entityType === EntityType.TREASURE) {
      return 'item';
    }
    // obstacle, trap, hazard all map to 'obstacle'
    return 'obstacle';
  }

  /**
   * Get content_type for entity_ref in entity_instance.schema.json format.
   * Provides more detail than entity_type for schema's entity_ref.content_type.
   * 
   * Schema content_type values: ["creature", "item", "obstacle", "trap", "hazard"]
   * 
   * @returns {string} Schema-compatible content_type value
   */
  getSchemaContentType() {
    // Map ECS types to schema content_type
    if (this.entityType === EntityType.PLAYER_CHARACTER || 
        this.entityType === EntityType.NPC || 
        this.entityType === EntityType.CREATURE) {
      return 'creature';
    }
    if (this.entityType === EntityType.ITEM || 
        this.entityType === EntityType.TREASURE) {
      return 'item';
    }
    if (this.entityType === EntityType.TRAP) {
      return 'trap';
    }
    if (this.entityType === EntityType.HAZARD) {
      return 'hazard';
    }
    return 'obstacle';
  }

  /**
   * Serialize component to JSON.
   * @returns {object} Serialized component data
   */
  toJSON() {
    return {
      type: this.constructor.name,
      name: this.name,
      entityType: this.entityType,
      description: this.description,
      tags: [...this.tags]
    };
  }

  /**
   * Deserialize component from JSON.
   * @param {object} data - Serialized component data
   * @returns {IdentityComponent} New component instance
   */
  static fromJSON(data) {
    const component = new IdentityComponent(
      data.name,
      data.entityType,
      data.description
    );
    if (data.tags) {
      component.tags = [...data.tags];
    }
    return component;
  }

  /**
   * Clone this component.
   * @returns {IdentityComponent} Cloned component
   */
  clone() {
    return IdentityComponent.fromJSON(this.toJSON());
  }
}
