/**
 * @file StatsComponent.js
 * Component for entity statistics (Pathfinder 2e compliant).
 */

import { Component } from '../Component.js';

/**
 * Default values for StatsComponent.
 */
const DEFAULTS = {
  ABILITY_SCORE: 10,
  MAX_HP: 10,
  ARMOR_CLASS: 10,
  SAVE_BONUS: 0,
  WALK_SPEED: 30,
  OTHER_SPEED: 0,
  LEVEL: 1,
  PROFICIENCY_BONUS: 2,
  PERCEPTION: 0,
  TEMP_HP: 0,
  EXPERIENCE_POINTS: 0
};

/**
 * Valid ability score names (full format).
 */
const ABILITY_NAMES = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];

/**
 * Valid abbreviated ability score names (character schema format).
 */
const ABILITY_ABBREVIATIONS = ['str', 'dex', 'con', 'int', 'wis', 'cha'];

/**
 * Map abbreviated ability names to full names.
 */
const ABILITY_MAP = {
  str: 'strength',
  dex: 'dexterity',
  con: 'constitution',
  int: 'intelligence',
  wis: 'wisdom',
  cha: 'charisma'
};

/**
 * Map full ability names to abbreviated names.
 */
const ABILITY_REVERSE_MAP = {
  strength: 'str',
  dexterity: 'dex',
  constitution: 'con',
  intelligence: 'int',
  wisdom: 'wis',
  charisma: 'cha'
};

/**
 * StatsComponent
 * 
 * Stores core statistics for entities including ability scores,
 * hit points, armor class, saves, and speeds.
 * 
 * This is a minimal implementation focusing on movement-critical stats.
 * Will be expanded to full PF2e stats in future iterations.
 * 
 * Schema Compatibility Notes:
 * - Internally uses full ability names: strength, dexterity, constitution, intelligence, wisdom, charisma
 * - Matches creature.schema.json format (pf2e_stats.ability_scores)
 * - Constructor accepts BOTH full names AND abbreviated names (str, dex, con, int, wis, cha)
 * - This supports character.schema.json format (abilities with abbreviated names)
 * - toJSON() exports full names (creature schema format)
 * - toAbbreviatedJSON() exports abbreviated names (character schema format)
 * - fromJSON() accepts both formats automatically
 * - getAbilityModifier() accepts both formats
 * 
 * Database Table References:
 * - dc_characters table has hot columns: name, level, ancestry, class
 * - character_data JSON column uses character.schema.json (abbreviated ability names)
 * - When loading from dc_characters, pass character_data.abilities directly - constructor handles conversion
 */
export class StatsComponent extends Component {
  /**
   * @param {Object} config - Configuration object
   * @param {number} [config.strength] - Strength ability score
   * @param {number} [config.str] - Strength ability score (abbreviated)
   * @param {number} [config.dexterity] - Dexterity ability score
   * @param {number} [config.dex] - Dexterity ability score (abbreviated)
   * @param {number} [config.constitution] - Constitution ability score
   * @param {number} [config.con] - Constitution ability score (abbreviated)
   * @param {number} [config.intelligence] - Intelligence ability score
   * @param {number} [config.int] - Intelligence ability score (abbreviated)
   * @param {number} [config.wisdom] - Wisdom ability score
   * @param {number} [config.wis] - Wisdom ability score (abbreviated)
   * @param {number} [config.charisma] - Charisma ability score
   * @param {number} [config.cha] - Charisma ability score (abbreviated)
   * @param {number} [config.maxHp] - Maximum hit points
   * @param {number} [config.currentHp] - Current hit points
   * @param {number} [config.ac] - Armor class
   * @param {number} [config.fortitude] - Fortitude save bonus
   * @param {number} [config.reflex] - Reflex save bonus
   * @param {number} [config.will] - Will save bonus
   * @param {number} [config.speed] - Walking speed in feet
   * @param {number} [config.flySpeed] - Flying speed in feet
   * @param {number} [config.swimSpeed] - Swimming speed in feet
   * @param {number} [config.burrowSpeed] - Burrowing speed in feet
   * @param {number} [config.climbSpeed] - Climbing speed in feet
   * @param {number} [config.level] - Character level
   * @param {number} [config.proficiencyBonus] - Proficiency bonus
   * @param {number} [config.perception] - Perception bonus
   * @param {number} [config.experiencePoints] - Experience points (hot column for database queries)
   */
  constructor(config = {}) {
    super();
    
    // Ability Scores (10 is average, modifiers = (score - 10) / 2)
    // Support both full names and abbreviations for schema compatibility
    this.abilities = {
      strength: config.strength ?? config.str ?? DEFAULTS.ABILITY_SCORE,
      dexterity: config.dexterity ?? config.dex ?? DEFAULTS.ABILITY_SCORE,
      constitution: config.constitution ?? config.con ?? DEFAULTS.ABILITY_SCORE,
      intelligence: config.intelligence ?? config.int ?? DEFAULTS.ABILITY_SCORE,
      wisdom: config.wisdom ?? config.wis ?? DEFAULTS.ABILITY_SCORE,
      charisma: config.charisma ?? config.cha ?? DEFAULTS.ABILITY_SCORE
    };
    
    // Hit Points
    this.maxHp = config.maxHp ?? DEFAULTS.MAX_HP;
    this.currentHp = config.currentHp ?? this.maxHp;
    this.tempHp = DEFAULTS.TEMP_HP;
    
    // Armor Class
    this.ac = config.ac ?? DEFAULTS.ARMOR_CLASS;
    
    // Saving Throws (total bonus)
    this.saves = {
      fortitude: config.fortitude ?? DEFAULTS.SAVE_BONUS,
      reflex: config.reflex ?? DEFAULTS.SAVE_BONUS,
      will: config.will ?? DEFAULTS.SAVE_BONUS
    };
    
    // Movement Speeds (feet per round)
    this.speeds = {
      walk: config.speed ?? DEFAULTS.WALK_SPEED,
      fly: config.flySpeed ?? DEFAULTS.OTHER_SPEED,
      swim: config.swimSpeed ?? DEFAULTS.OTHER_SPEED,
      burrow: config.burrowSpeed ?? DEFAULTS.OTHER_SPEED,
      climb: config.climbSpeed ?? DEFAULTS.OTHER_SPEED
    };
    
    // Level and proficiency
    this.level = config.level ?? DEFAULTS.LEVEL;
    this.proficiencyBonus = config.proficiencyBonus ?? DEFAULTS.PROFICIENCY_BONUS;
    
    // Perception
    this.perception = config.perception ?? DEFAULTS.PERCEPTION;
    
    // Experience points (hot column for database queries)
    this.experiencePoints = config.experiencePoints ?? DEFAULTS.EXPERIENCE_POINTS;
  }
  
  /**
   * Normalize ability name from abbreviated to full format.
   * @param {string} ability - Ability name (accepts both full and abbreviated)
   * @returns {string} - Full ability name
   * @throws {Error} - If ability name is invalid
   * @private
   */
  _normalizeAbilityName(ability) {
    // If already full name, return it
    if (ABILITY_NAMES.includes(ability)) {
      return ability;
    }
    // If abbreviated, convert to full
    if (ABILITY_ABBREVIATIONS.includes(ability)) {
      return ABILITY_MAP[ability];
    }
    throw new Error(`Invalid ability name: ${ability}. Valid abilities: ${ABILITY_NAMES.join(', ')} or ${ABILITY_ABBREVIATIONS.join(', ')}`);
  }
  
  /**
   * Get ability modifier.
   * @param {string} ability - Ability name (accepts both full and abbreviated formats)
   * @returns {number} - Ability modifier
   * @throws {Error} - If ability name is invalid
   */
  getAbilityModifier(ability) {
    const normalizedAbility = this._normalizeAbilityName(ability);
    const score = this.abilities[normalizedAbility] ?? DEFAULTS.ABILITY_SCORE;
    return Math.floor((score - 10) / 2);
  }
  
  /**
   * Apply damage to HP.
   * @param {number} damage - Damage amount (must be non-negative)
   * @returns {number} - Actual damage dealt (after temp HP)
   * @throws {Error} - If damage is negative
   */
  takeDamage(damage) {
    if (damage < 0) {
      throw new Error('Damage must be non-negative');
    }
    
    let remaining = damage;
    
    // Temp HP absorbs damage first
    if (this.tempHp > 0) {
      const absorbed = Math.min(this.tempHp, damage);
      this.tempHp -= absorbed;
      remaining -= absorbed;
    }
    
    // Apply remaining damage to current HP
    if (remaining > 0) {
      this.currentHp = Math.max(0, this.currentHp - remaining);
    }
    
    return damage;
  }
  
  /**
   * Heal HP.
   * @param {number} amount - Healing amount (must be non-negative)
   * @returns {number} - Actual HP healed
   * @throws {Error} - If amount is negative
   */
  heal(amount) {
    if (amount < 0) {
      throw new Error('Healing amount must be non-negative');
    }
    
    const oldHp = this.currentHp;
    this.currentHp = Math.min(this.maxHp, this.currentHp + amount);
    return this.currentHp - oldHp;
  }
  
  /**
   * Check if entity is alive.
   * @returns {boolean}
   */
  isAlive() {
    return this.currentHp > 0;
  }
  
  /**
   * Check if entity is at full HP.
   * @returns {boolean}
   */
  isFullHealth() {
    return this.currentHp >= this.maxHp;
  }
  
  /**
   * Get HP percentage.
   * @returns {number} - Value between 0.0 and 1.0
   */
  getHealthPercentage() {
    if (this.maxHp <= 0) {
      return 0;
    }
    return this.currentHp / this.maxHp;
  }
  
  /**
   * Serialize component to JSON (full ability names format - creature schema).
   * @returns {Object}
   */
  toJSON() {
    return {
      type: this.constructor.name,
      abilities: {...this.abilities},
      maxHp: this.maxHp,
      currentHp: this.currentHp,
      tempHp: this.tempHp,
      ac: this.ac,
      saves: {...this.saves},
      speeds: {...this.speeds},
      level: this.level,
      proficiencyBonus: this.proficiencyBonus,
      perception: this.perception,
      experiencePoints: this.experiencePoints
    };
  }
  
  /**
   * Serialize component to JSON using abbreviated ability names (character schema format).
   * Use this for compatibility with dc_characters table character_data JSON.
   * @returns {Object}
   */
  toAbbreviatedJSON() {
    return {
      type: this.constructor.name,
      abilities: {
        str: this.abilities.strength,
        dex: this.abilities.dexterity,
        con: this.abilities.constitution,
        int: this.abilities.intelligence,
        wis: this.abilities.wisdom,
        cha: this.abilities.charisma
      },
      maxHp: this.maxHp,
      currentHp: this.currentHp,
      tempHp: this.tempHp,
      ac: this.ac,
      saves: {...this.saves},
      speeds: {...this.speeds},
      level: this.level,
      proficiencyBonus: this.proficiencyBonus,
      perception: this.perception
    };
  }
  
  /**
   * Deserialize component from JSON.
   * Supports both full ability names (creature schema) and abbreviated names (character schema).
   * @param {Object} data - Serialized data
   * @returns {StatsComponent}
   */
  static fromJSON(data) {
    // Handle both full and abbreviated ability names
    const abilities = data.abilities || {};
    const config = {
      // Try full names first, then abbreviations
      strength: abilities.strength ?? abilities.str,
      dexterity: abilities.dexterity ?? abilities.dex,
      constitution: abilities.constitution ?? abilities.con,
      intelligence: abilities.intelligence ?? abilities.int,
      wisdom: abilities.wisdom ?? abilities.wis,
      charisma: abilities.charisma ?? abilities.cha,
      maxHp: data.maxHp,
      currentHp: data.currentHp,
      ac: data.ac,
      fortitude: data.saves?.fortitude,
      reflex: data.saves?.reflex,
      will: data.saves?.will,
      speed: data.speeds?.walk,
      flySpeed: data.speeds?.fly,
      swimSpeed: data.speeds?.swim,
      burrowSpeed: data.speeds?.burrow,
      climbSpeed: data.speeds?.climb,
      level: data.level,
      proficiencyBonus: data.proficiencyBonus,
      perception: data.perception,
      experiencePoints: data.experiencePoints
    };
    
    const component = new StatsComponent(config);
    component.tempHp = data.tempHp;
    return component;
  }
}
