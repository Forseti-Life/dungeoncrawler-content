/**
 * @file
 * CharacterState TypeScript interfaces.
 * 
 * Based on the design document:
 * docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md
 * 
 * SCHEMA CONFORMANCE NOTES:
 * These TypeScript interfaces define the **runtime API format** (camelCase) for character
 * state. This is NOT the same as the database storage format (snake_case).
 * 
 * Three-layer architecture:
 * 1. **TypeScript** (this file): Runtime API contract for client-side consumption
 *    - Uses camelCase naming (experiencePoints, hitPoints, spellSlots)
 *    - Nested structures (basicInfo, resources, defenses)
 *    - Source of truth for TypeScript type checking
 * 
 * 2. **JSON Schema** (config/schemas/character.schema.json): Storage validation
 *    - Uses snake_case naming (experience_points, hit_points, spell_slots)
 *    - Flatter structure matching database expectations
 *    - Validates data persisted to character_data column
 * 
 * 3. **Database Hot Columns** (dc_campaign_characters table): Query optimization
 *    - Core identity: name, level, ancestry, class
 *    - Combat/gameplay: hp_current, hp_max, armor_class, experience_points
 *    - Position tracking: position_q, position_r, last_room_id
 *    - Enables fast filtering without JSON path queries
 *    - Synchronized with JSON payload by PHP service
 *    - Note: Position fields managed by ECS PositionComponent, not CharacterState
 * 
 * The PHP CharacterStateService handles translation between these layers:
 * - READ: Database (snake_case + hot columns) → API (camelCase nested)
 * - WRITE: API (camelCase nested) → Database (snake_case + hot columns sync)
 * 
 * TypeScript code should ONLY use these interfaces. Never manually convert between
 * snake_case and camelCase - the PHP service handles all schema translation.
 * 
 * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-object
 * @see js/SCHEMA_ALIGNMENT.md
 */

/**
 * Main character state interface.
 */
export interface CharacterState {
  // Identity
  characterId: string;
  userId: string;
  campaignId?: string;
  
  // Basic Info
  basicInfo: {
    name: string;
    level: number;
    experiencePoints: number;
    ancestry: string;
    heritage: string;
    background: string;
    class: string;
    alignment: string;
    deity?: string;
    age?: number;
    appearance?: string;
    personality?: string;
  };
  
  // Ability Scores
  // NOTE: Uses full names (strength, dexterity) not abbreviations (str, dex)
  // JSON schema uses abbreviated names, PHP service translates between formats
  abilities: {
    strength: number;
    dexterity: number;
    constitution: number;
    intelligence: number;
    wisdom: number;
    charisma: number;
  };
  
  // Hit Points and Resources
  resources: {
    hitPoints: {
      current: number;
      max: number;
      temporary: number;
    };
    stamina?: {
      current: number;
      max: number;
    };
    resolve?: {
      current: number;
      max: number;
    };
    heroPoints: {
      current: number;
      max: number;
    };
    focusPoints?: {
      current: number;
      max: number;
    };
    spellSlots?: Record<number, {
      current: number;
      max: number;
    }>;
  };
  
  // Defenses
  defenses: {
    armorClass: {
      base: number;
      flatFooted: number;
      touch: number;
      modifiers: Modifier[];
    };
    savingThrows: {
      fortitude: {
        base: number;
        modifiers: Modifier[];
      };
      reflex: {
        base: number;
        modifiers: Modifier[];
      };
      will: {
        base: number;
        modifiers: Modifier[];
      };
    };
    perception: {
      base: number;
      modifiers: Modifier[];
    };
  };
  
  // Active Conditions
  conditions: Condition[];
  
  // Actions and Abilities
  actions: {
    threeActionEconomy: {
      actionsRemaining: number;
      reactionAvailable: boolean;
    };
    availableActions: Action[];
  };
  
  // Spells
  spells: {
    spellcastingTradition?: 'arcane' | 'divine' | 'occult' | 'primal';
    spellcastingType?: 'prepared' | 'spontaneous';
    spellAttackBonus: number;
    spellDC: number;
    knownSpells: Spell[];
    preparedSpells?: PreparedSpell[];
    focusSpells: Spell[];
    cantrips: Spell[];
  };
  
  // Skills
  skills: {
    [skillName: string]: {
      proficiencyRank: 'untrained' | 'trained' | 'expert' | 'master' | 'legendary';
      bonus: number;
      modifiers: Modifier[];
    };
  };
  
  // Inventory
  inventory: {
    worn: {
      armor?: Item;
      weapons: Item[];
      accessories: Item[];
    };
    carried: Item[];
    currency: {
      cp: number;
      sp: number;
      gp: number;
      pp: number;
    };
    totalBulk: number;
    encumbrance: 'unencumbered' | 'encumbered' | 'overloaded';
  };
  
  // Features and Feats
  features: {
    ancestryFeatures: Feature[];
    classFeatures: Feature[];
    feats: Feat[];
  };
  
  // Metadata
  metadata: {
    createdAt: string;
    updatedAt: string;
    lastSyncedAt: string;
    version: number;
  };
}

/**
 * Modifier interface.
 */
export interface Modifier {
  id: string;
  name: string;
  value: number;
  type: 'circumstance' | 'status' | 'item' | 'untyped';
  source: string;
  duration?: Duration;
}

/**
 * Condition interface.
 */
export interface Condition {
  id: string;
  name: string;
  description: string;
  severity: 'harmful' | 'beneficial' | 'neutral';
  value?: number;
  duration?: Duration;
  effects: Effect[];
  appliedAt: string;
}

/**
 * Duration interface.
 */
export interface Duration {
  type: 'rounds' | 'minutes' | 'hours' | 'days' | 'permanent' | 'encounter' | 'until_removed';
  value?: number;
  endsAt?: string;
}

/**
 * Effect interface.
 */
export interface Effect {
  type: 'modifier' | 'immunity' | 'resistance' | 'vulnerability' | 'special';
  target: string;
  value: number | string;
}

/**
 * Action interface.
 */
export interface Action {
  id: string;
  name: string;
  actionCost: number;
  isReaction: boolean;
  description: string;
  traits: string[];
  requirements?: string;
  effects: ActionEffect[];
}

/**
 * Base action effect interface.
 */
interface BaseActionEffect {
  type: 'damage' | 'heal' | 'condition' | 'movement' | 'custom';
}

/**
 * Damage action effect.
 */
export interface DamageEffect extends BaseActionEffect {
  type: 'damage';
  details: {
    diceCount: number;
    diceSize: number;
    damageType: string;
    bonus?: number;
  };
}

/**
 * Heal action effect.
 */
export interface HealEffect extends BaseActionEffect {
  type: 'heal';
  details: {
    diceCount: number;
    diceSize: number;
    bonus?: number;
  };
}

/**
 * Condition action effect.
 */
export interface ConditionEffect extends BaseActionEffect {
  type: 'condition';
  details: {
    conditionId: string;
    duration?: Duration;
  };
}

/**
 * Movement action effect.
 */
export interface MovementEffect extends BaseActionEffect {
  type: 'movement';
  details: {
    distance: number;
    unit: 'feet' | 'squares';
  };
}

/**
 * Custom action effect.
 */
export interface CustomEffect extends BaseActionEffect {
  type: 'custom';
  details: {
    description: string;
    [key: string]: unknown;
  };
}

/**
 * Action effect discriminated union.
 */
export type ActionEffect = 
  | DamageEffect 
  | HealEffect 
  | ConditionEffect 
  | MovementEffect 
  | CustomEffect;

/**
 * Spell interface.
 */
export interface Spell {
  id: string;
  name: string;
  level: number;
  tradition: 'arcane' | 'divine' | 'occult' | 'primal';
  school: string;
  traits: string[];
  castingTime: string;
  components: string[];
  range: string;
  area?: string;
  targets?: string;
  duration: string;
  savingThrow?: string;
  description: string;
  heightenedEffects?: { [level: string]: string };
}

/**
 * Prepared spell interface.
 */
export interface PreparedSpell {
  spellId: string;
  level: number;
  expended: boolean;
}

/**
 * Weapon properties interface.
 */
export interface WeaponProperties {
  damage: string;
  damageType: string;
  range?: number;
  traits: string[];
  group: string;
}

/**
 * Armor properties interface.
 */
export interface ArmorProperties {
  armorClass: number;
  dexCap?: number;
  checkPenalty: number;
  speedPenalty: number;
  strength?: number;
  group: string;
  traits: string[];
}

/**
 * Consumable properties interface.
 */
export interface ConsumableProperties {
  level: number;
  uses: number;
  maxUses: number;
  effect: string;
}

/**
 * Base item interface.
 */
interface BaseItem {
  id: string;
  name: string;
  quantity: number;
  bulk: number;
  equipped: boolean;
  description: string;
}

/**
 * Weapon item.
 */
export interface WeaponItem extends BaseItem {
  type: 'weapon';
  properties: WeaponProperties;
}

/**
 * Armor item.
 */
export interface ArmorItem extends BaseItem {
  type: 'armor';
  properties: ArmorProperties;
}

/**
 * Consumable item.
 */
export interface ConsumableItem extends BaseItem {
  type: 'consumable';
  properties: ConsumableProperties;
}

/**
 * Generic item (treasure, tool, other).
 */
export interface GenericItem extends BaseItem {
  type: 'treasure' | 'tool' | 'other';
  properties?: Record<string, unknown>;
}

/**
 * Item discriminated union.
 */
export type Item = 
  | WeaponItem 
  | ArmorItem 
  | ConsumableItem 
  | GenericItem;

/**
 * Remote update interface for WebSocket messages.
 */
export interface RemoteUpdate {
  characterId: string;
  version: number;
  timestamp: number;
  operations: UpdateOperation[];
}

/**
 * Feature interface.
 */
export interface Feature {
  id: string;
  name: string;
  description: string;
  level: number;
  source: string;
}

/**
 * Feat interface.
 */
export interface Feat {
  id: string;
  name: string;
  type: 'ancestry' | 'class' | 'general' | 'skill';
  level: number;
  description: string;
  prerequisites?: string[];
  traits: string[];
}

/**
 * Update operation type.
 */
export type UpdateOperationType = 
  | 'hitPoints'
  | 'condition'
  | 'spell'
  | 'action'
  | 'reaction'
  | 'inventory'
  | 'experience'
  | 'resource';

/**
 * Update operation interface.
 */
export interface UpdateOperation {
  type: UpdateOperationType;
  path: string;
  value: unknown;
  timestamp: number;
  version: number;
}

/**
 * ARCHITECTURE NOTES:
 * 
 * Position Tracking
 * -----------------
 * Character position data (hex coordinates, room location) is managed by the ECS 
 * (Entity Component System) using PositionComponent, not directly in CharacterState.
 * These fields are stored in database hot columns for performance:
 * - position_q, position_r: Hex axial coordinates
 * - last_room_id: Most recent room location
 * 
 * @see js/ecs/components/PositionComponent.js
 * @see js/ecs/index.js for component-to-schema mapping
 * 
 * Hot Columns vs CharacterState
 * -----------------------------
 * The dc_campaign_characters table includes 11 hot columns for query optimization.
 * CharacterState exposes the character sheet fields (name, level, HP, etc.) but not
 * the position/location fields which are ECS-managed runtime gameplay state.
 * 
 * Complete hot column list:
 * - Core identity: name, level, ancestry, class
 * - Combat/gameplay: hp_current, hp_max, armor_class, experience_points
 * - Position tracking: position_q, position_r, last_room_id (ECS-managed)
 * 
 * @see js/SCHEMA_ALIGNMENT.md for detailed field mapping
 */
