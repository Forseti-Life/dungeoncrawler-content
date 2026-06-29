# Game Architecture: Entity-Component-System Design

**Document**: ECS Architecture for PF2e Dungeon Crawler  
**Created**: 2026-02-12  
**Purpose**: Define data structures and system architecture for turn-based tactical gameplay

## Verification Notes (2026-02-18)

- This is a design + implementation roadmap document; not every section is fully implemented.
- Implemented ECS foundation exists under `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/ecs/` (entities, components, systems, mapper).
- Current combat runtime exposed to the hexmap is the lightweight REST encounter flow in `CombatEncounterApiController` + `CombatEncounterStore`.
- Full server-authoritative combat/action services described in some sections remain planned and should be treated as future-state until corresponding controllers/services are completed.

---

## Table of Contents

1. [ECS Overview](#ecs-overview)
2. [Component Definitions](#component-definitions)
3. [System Definitions](#system-definitions)
4. [Entity Templates](#entity-templates)
5. [Game State Structure](#game-state-structure)
6. [Database Schema](#database-schema)
7. [Integration with PixiJS](#integration-with-pixijs)
8. [Implementation Roadmap](#implementation-roadmap)

---

## ECS Overview

### What is ECS?

**Entity-Component-System** is a design pattern that separates:
- **Entities**: Unique IDs representing game objects
- **Components**: Pure data containers (no logic)
- **Systems**: Logic that operates on entities with specific components

### Why ECS for Turn-Based Tactical RPG?

✅ **Advantages:**
- Clean separation of data and logic
- Easy to serialize game state for save/load
- Flexible composition (add/remove components dynamically)
- Efficient queries (e.g., "all entities with HP and Position")
- Natural fit for turn-based mechanics
- Scales well with complex interactions

### Architecture Flow

```
┌─────────────────────────────────────────────────────┐
│                   Game Loop (Turn)                  │
│                                                     │
│  1. Input System (player selects action)           │
│  2. Action Validation System                        │
│  3. Movement System (process movement)              │
│  4. Combat System (process attacks)                 │
│  5. Status Effect System (process conditions)       │
│  6. Render System (update PixiJS sprites)           │
│  7. Turn Management System (next turn)              │
└─────────────────────────────────────────────────────┘
```

---

## Component Definitions

Components are **pure data** with no methods (except setters/getters for validation).

### Core Components

#### 1. PositionComponent
```javascript
class PositionComponent {
  q: number;              // Axial coordinate Q
  r: number;              // Axial coordinate R
  elevation: number;      // Z-height (0 = ground level)
  facing: number;         // Direction facing (0-5 for hex)
}
```

**Database Schema:**
```sql
CREATE TABLE component_position (
  entity_id INT PRIMARY KEY,
  q INT NOT NULL,
  r INT NOT NULL,
  elevation INT DEFAULT 0,
  facing INT DEFAULT 0,
  INDEX idx_hex (q, r)
);
```

---

#### 2. StatsComponent (PF2e Core Stats)
```javascript
class StatsComponent {
  // Basic Stats
  level: number;
  
  // Ability Scores
  strength: number;
  dexterity: number;
  constitution: number;
  intelligence: number;
  wisdom: number;
  charisma: number;
  
  // Derived Stats
  maxHP: number;
  currentHP: number;
  tempHP: number;
  armorClass: number;
  
  // Speeds (in feet, 1 hex = 5 feet)
  baseSpeed: number;        // Usually 25 or 30
  currentSpeed: number;     // Modified by conditions
  
  // Saves
  fortitudeSave: number;
  reflexSave: number;
  willSave: number;
  
  // Perception
  perception: number;
}
```

**Database Schema:**
```sql
CREATE TABLE component_stats (
  entity_id INT PRIMARY KEY,
  level INT NOT NULL,
  strength INT NOT NULL,
  dexterity INT NOT NULL,
  constitution INT NOT NULL,
  intelligence INT NOT NULL,
  wisdom INT NOT NULL,
  charisma INT NOT NULL,
  max_hp INT NOT NULL,
  current_hp INT NOT NULL,
  temp_hp INT DEFAULT 0,
  armor_class INT NOT NULL,
  base_speed INT DEFAULT 30,
  current_speed INT DEFAULT 30,
  fortitude_save INT NOT NULL,
  reflex_save INT NOT NULL,
  will_save INT NOT NULL,
  perception INT NOT NULL
);
```

---

#### 3. ActionsComponent (PF2e 3-Action Economy)
```javascript
class ActionsComponent {
  maxActions: number;              // Usually 3
  actionsRemaining: number;        // Actions left this turn
  hasReaction: boolean;            // Reaction available
  attacksMadeThisTurn: number;     // Number of attacks made this turn
  mapPenalty: number;              // Current Multiple Attack Penalty (-5, -10, etc.)
  mapPenaltyPerAttack: number;     // MAP penalty per attack (-5 standard, -4 agile)
  actionHistory: Action[];         // History of actions taken this turn
  canAct: boolean;                 // Whether entity can take actions
  actionBonus: number;             // Bonus/penalty to action count (e.g., Haste +1)
}

interface Action {
  name: string;                    // 'Stride', 'Strike', 'Cast Spell', etc.
  cost: number;                    // Action cost (0=free, 1-3=actions, -1=reaction)
  type: string;                    // 'action', 'activity', 'reaction', 'free_action'
  timestamp: number;               // When action was taken
}
```

**Storage:**
Components are stored as JSON in the unified `state_data` column of the `dc_campaign_characters` table. This approach uses hot columns (hp_current, hp_max, armor_class, position_q, position_r) for frequently accessed data, while component state is serialized into the flexible JSON `state_data` field.

**JSON Structure Example:**
```json
{
  "components": {
    "ActionsComponent": {
      "type": "ActionsComponent",
      "maxActions": 3,
      "actionsRemaining": 2,
      "hasReaction": true,
      "attacksMadeThisTurn": 1,
      "mapPenalty": -5,
      "mapPenaltyPerAttack": -5,
      "actionHistory": [
        {
          "name": "Strike",
          "cost": 1,
          "type": "action",
          "timestamp": 1642534800000
        }
      ],
      "canAct": true,
      "actionBonus": 0
    }
  }
}
```

---

#### 4. StatusEffectsComponent
```javascript
class StatusEffectsComponent {
  conditions: Condition[];
  buffs: Effect[];
  debuffs: Effect[];
}

interface Condition {
  name: string;               // 'prone', 'stunned', 'frightened', etc.
  value?: number;             // Numbered conditions (frightened 2)
  duration: Duration;
  source: number;             // Entity ID that applied condition
}

interface Effect {
  id: string;
  name: string;
  type: 'buff' | 'debuff';
  modifiers: Modifier[];
  duration: Duration;
  source: number;             // Entity ID or spell ID
}

interface Modifier {
  stat: string;               // 'ac', 'attack', 'speed', etc.
  value: number;              // +2, -1, etc.
  type: string;               // 'circumstance', 'status', 'item'
}

interface Duration {
  type: 'rounds' | 'minutes' | 'unlimited' | 'until_end_of_turn';
  value?: number;
  expiresOnRound?: number;
}
```

**Database Schema:**
```sql
CREATE TABLE component_status_effects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_id INT NOT NULL,
  effect_type ENUM('condition', 'buff', 'debuff') NOT NULL,
  name VARCHAR(100) NOT NULL,
  value INT NULL,
  duration_type ENUM('rounds', 'minutes', 'unlimited', 'until_end_of_turn') NOT NULL,
  duration_value INT NULL,
  expires_on_round INT NULL,
  source_entity_id INT NULL,
  modifiers JSON,
  INDEX idx_entity (entity_id)
);
```

---

#### 5. MovementComponent
```javascript
class MovementComponent {
  canMove: boolean;
  movementRemaining: number;      // Feet remaining this turn
  movementSpeed: number;          // Base speed from StatsComponent
  movementMode: MovementMode;
  hasMovedThisTurn: boolean;
  path: HexCoordinate[];          // Currently planned path
}

enum MovementMode {
  WALK = 'walk',
  CLIMB = 'climb',
  SWIM = 'swim',
  FLY = 'fly',
  BURROW = 'burrow'
}

interface HexCoordinate {
  q: number;
  r: number;
}
```

**Database Schema:**
```sql
CREATE TABLE component_movement (
  entity_id INT PRIMARY KEY,
  can_move BOOLEAN DEFAULT TRUE,
  movement_remaining INT DEFAULT 0,
  movement_speed INT DEFAULT 30,
  movement_mode ENUM('walk', 'climb', 'swim', 'fly', 'burrow') DEFAULT 'walk',
  has_moved_this_turn BOOLEAN DEFAULT FALSE
);
```

---

#### 6. CombatComponent
```javascript
class CombatComponent {
  initiative: number;
  initiativeModifier: number;
  isInCombat: boolean;
  team: string;                   // 'player', 'enemy', 'neutral'
  
  // Attack bonuses
  meleeAttackBonus: number;
  rangedAttackBonus: number;
  spellAttackBonus: number;
  
  // Weapon info
  equippedWeapon?: Weapon;
  weaponTraits: string[];         // 'agile', 'finesse', 'reach', etc.
}

interface Weapon {
  name: string;
  damage: string;                 // '1d8', '2d6', etc.
  damageType: string;             // 'slashing', 'piercing', 'bludgeoning'
  traits: string[];
  range?: number;                 // For ranged weapons (in feet)
  reach: number;                  // Usually 5 or 10 feet
}
```

**Database Schema:**
```sql
CREATE TABLE component_combat (
  entity_id INT PRIMARY KEY,
  initiative INT DEFAULT 0,
  initiative_modifier INT DEFAULT 0,
  is_in_combat BOOLEAN DEFAULT FALSE,
  team ENUM('player', 'enemy', 'neutral') DEFAULT 'neutral',
  melee_attack_bonus INT DEFAULT 0,
  ranged_attack_bonus INT DEFAULT 0,
  spell_attack_bonus INT DEFAULT 0,
  equipped_weapon_id INT NULL,
  weapon_traits JSON
);
```

---

#### 7. RenderComponent (PixiJS Integration)
```javascript
class RenderComponent {
  spriteKey: string;              // Asset path or texture key
  sprite: PIXI.Sprite | null;     // Reference to PixiJS sprite
  container: PIXI.Container | null;
  scale: number;
  rotation: number;
  tint: number;                   // Hex color for tinting
  visible: boolean;
  zIndex: number;                 // Rendering order
  
  // UI Elements
  healthBar?: PIXI.Graphics;
  nameplate?: PIXI.Text;
  statusIcons?: PIXI.Container;
}
```

**Note:** Sprite references not stored in DB (recreated on load)

---

#### 8. IdentityComponent
```javascript
class IdentityComponent {
  name: string;
  description: string;
  entityType: EntityType;
  tags: string[];
}

enum EntityType {
  PLAYER_CHARACTER = 'player_character',
  NPC = 'npc',
  CREATURE = 'creature',
  ITEM = 'item',
  OBSTACLE = 'obstacle',
  TRAP = 'trap',
  TREASURE = 'treasure',
  HAZARD = 'hazard'
}
```

**Database Schema:**
```sql
CREATE TABLE component_identity (
  entity_id INT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  entity_type ENUM('player_character', 'npc', 'creature', 'item', 'obstacle', 'trap', 'treasure', 'hazard') NOT NULL,
  tags JSON
);
```

---

#### 9. AIComponent (For NPCs/Creatures)
```javascript
class AIComponent {
  behaviorType: BehaviorType;
  aggressionLevel: number;        // 0-10 scale
  preferredRange: number;         // Feet (melee: 5, ranged: 30+)
  targetPriority: string[];       // ['lowest_hp', 'nearest', 'spellcaster']
  currentTarget: number | null;   // Entity ID
  lastAction: string;
  decisionTree?: AIDecisionTree;
}

enum BehaviorType {
  AGGRESSIVE = 'aggressive',
  DEFENSIVE = 'defensive',
  SUPPORT = 'support',
  COWARD = 'coward',
  RANDOM = 'random',
  SCRIPTED = 'scripted'
}
```

**Database Schema:**
```sql
CREATE TABLE component_ai (
  entity_id INT PRIMARY KEY,
  behavior_type ENUM('aggressive', 'defensive', 'support', 'coward', 'random', 'scripted') NOT NULL,
  aggression_level INT DEFAULT 5,
  preferred_range INT DEFAULT 5,
  target_priority JSON,
  current_target_id INT NULL
);
```

---

#### 10. AbilitiesComponent (Spells, Feats, Special Abilities)
```javascript
class AbilitiesComponent {
  abilities: Ability[];
  spells: Spell[];
  feats: Feat[];
}

interface Ability {
  id: string;
  name: string;
  actionCost: number;             // 1, 2, 3, or 'reaction'
  range: number;                  // Feet
  cooldown?: number;              // Rounds
  usesRemaining?: number;
  maxUses?: number;
  description: string;
  effects: AbilityEffect[];
}

interface AbilityEffect {
  type: 'damage' | 'heal' | 'condition' | 'buff' | 'debuff' | 'movement';
  value: string | number;
  target: 'self' | 'enemy' | 'ally' | 'area';
  duration?: Duration;
}
```

**Database Schema:**
```sql
CREATE TABLE component_abilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_id INT NOT NULL,
  ability_id VARCHAR(100) NOT NULL,
  ability_name VARCHAR(255) NOT NULL,
  action_cost INT NOT NULL,
  range_feet INT DEFAULT 0,
  cooldown_rounds INT NULL,
  uses_remaining INT NULL,
  max_uses INT NULL,
  effects JSON,
  INDEX idx_entity (entity_id)
);
```

---

## System Definitions

Systems contain **logic** and operate on entities with specific component combinations.

### 1. MovementSystem

**Purpose:** Handle movement calculations, pathfinding, and movement actions

**Operates On:** Entities with `PositionComponent`, `MovementComponent`, `ActionsComponent`

**Key Methods:**
```javascript
class MovementSystem {
  // Calculate which hexes are reachable
  calculateMovementRange(entity, remainingMovement) {
    // Returns array of {q, r, cost} hexes
  }
  
  // Find optimal path between two hexes
  findPath(startQ, startR, endQ, endR, movementBudget) {
    // A* pathfinding considering terrain costs
    // Returns array of hex coordinates
  }
  
  // Execute movement along path
  moveEntity(entity, path, actionCost) {
    // Deduct actions, update position, trigger movement events
  }
  
  // Check if movement is valid
  canMoveTo(entity, q, r) {
    // Check obstacles, terrain, other entities
  }
  
  // Calculate movement cost for terrain
  getTerrainCost(q, r, movementMode) {
    // Normal: 5 feet, difficult: 10 feet, impassable: Infinity
  }
}
```

**Events Triggered:**
- `onMovementStart`
- `onMovementEnd`
- `onEnterHex`
- `onLeaveHex`

---

### 2. CombatSystem

**Purpose:** Handle attacks, damage, saving throws

**Operates On:** Entities with `CombatComponent`, `StatsComponent`, `PositionComponent`

**Key Methods:**
```javascript
class CombatSystem {
  // Roll initiative for encounter
  rollInitiative(entities) {
    // 1d20 + initiativeModifier for each entity
  }
  
  // Process attack action
  makeAttack(attacker, defender, weapon) {
    // 1. Roll attack (1d20 + attack bonus vs AC)
    // 2. Check degree of success
    // 3. Roll damage if hit
    // 4. Apply damage to defender
    // 5. Apply MAP to attacker
  }
  
  // Apply damage
  applyDamage(entity, amount, damageType) {
    // Handle temp HP, resistances, weaknesses
  }
  
  // Check if in melee/ranged range
  isInRange(attacker, defender, weapon) {
    // Calculate hex distance, check weapon range/reach
  }
  
  // Calculate attack bonus with modifiers
  getAttackBonus(entity, attackType) {
    // Base + conditions + buffs + MAP
  }
}
```

**Events Triggered:**
- `onAttackRoll`
- `onHit`
- `onCriticalHit`
- `onMiss`
- `onDamage`
- `onDeath`

---

### 3. TurnManagementSystem

**Purpose:** Control turn order, action economy, round progression

**Operates On:** All combat entities

**Key Methods:**
```javascript
class TurnManagementSystem {
  // Start encounter
  startEncounter(entities) {
    // Roll initiative, sort by result
  }
  
  // Start new turn
  startTurn(entity) {
    // Reset actions to 3
    // Reset reaction
    // Reset MAP to 0
    // Process start-of-turn effects
  }
  
  // End current turn
  endTurn(entity) {
    // Process end-of-turn effects
    // Advance to next entity in initiative
  }
  
  // Start new round
  startRound(roundNumber) {
    // Process round-based durations
    // Update status effects
  }
  
  // Check if encounter is over
  checkVictoryCondition() {
    // All enemies defeated OR all players defeated
  }
}
```

**Events Triggered:**
- `onEncounterStart`
- `onTurnStart`
- `onTurnEnd`
- `onRoundStart`
- `onRoundEnd`
- `onEncounterEnd`

---

### 4. StatusEffectSystem

**Purpose:** Manage conditions, buffs, debuffs, duration tracking

**Operates On:** Entities with `StatusEffectsComponent`

**Key Methods:**
```javascript
class StatusEffectSystem {
  // Apply status effect
  applyEffect(entity, effect) {
    // Add effect to StatusEffectsComponent
    // Apply modifiers to other components
  }
  
  // Remove status effect
  removeEffect(entity, effectId) {
    // Remove effect and its modifiers
  }
  
  // Update all effects (called each round)
  updateEffects(currentRound) {
    // Decrement durations, remove expired effects
  }
  
  // Calculate total modifier for a stat
  getTotalModifier(entity, statName) {
    // Sum all modifiers of highest type (PF2e bonus rules)
  }
  
  // Check if entity has condition
  hasCondition(entity, conditionName) {
    // Returns boolean or condition value
  }
}
```

**Events Triggered:**
- `onEffectApplied`
- `onEffectRemoved`
- `onEffectExpired`

---

### 5. RenderSystem

**Purpose:** Sync game state with PixiJS rendering

**Operates On:** Entities with `RenderComponent`, `PositionComponent`

**Key Methods:**
```javascript
class RenderSystem {
  // Create sprite for entity
  createSprite(entity) {
    // Load texture, create PIXI.Sprite, attach to container
  }
  
  // Update sprite position based on PositionComponent
  updatePosition(entity) {
    // Convert hex coords to pixel coords
  }
  
  // Animate movement
  animateMovement(entity, path, duration) {
    // Use GSAP or PixiJS AnimatedSprite
  }
  
  // Update health bar
  updateHealthBar(entity) {
    // Redraw health bar based on current HP
  }
  
  // Show/hide status icons
  updateStatusIcons(entity) {
    // Display icons for active conditions
  }
  
  // Highlight entity (selection, hovering)
  highlight(entity, highlightType) {
    // Add glow effect or outline
  }
}
```

---

### 6. InputSystem

**Purpose:** Handle player input and convert to game actions

**Operates On:** Current active entity (player's turn)

**Key Methods:**
```javascript
class InputSystem {
  // Handle hex click
  onHexClick(q, r) {
    // Determine action based on game state:
    // - No selection: Select entity at hex
    // - Entity selected + move mode: Move to hex
    // - Entity selected + attack mode: Attack entity at hex
  }
  
  // Handle action button click
  onActionButtonClick(actionType) {
    // Enter action mode (move, attack, cast spell, etc.)
  }
  
  // Handle ability use
  useAbility(abilityId, targetHex) {
    // Validate, execute, deduct actions
  }
  
  // Handle end turn
  endTurn() {
    // Confirm and pass turn to next entity
  }
}
```

---

### 7. AISystem

**Purpose:** Control non-player entities

**Operates On:** Entities with `AIComponent`

**Key Methods:**
```javascript
class AISystem {
  // Execute AI turn
  executeTurn(entity) {
    // 1. Select target
    // 2. Decide action (move, attack, ability)
    // 3. Execute action
    // 4. Repeat until actions exhausted
    // 5. End turn
  }
  
  // Select best target
  selectTarget(entity) {
    // Based on target priority, range, threat level
  }
  
  // Decide best action
  decideAction(entity, target) {
    // Move closer, attack, use ability, defend, flee
  }
  
  // Evaluate tactical position
  evaluatePosition(entity) {
    // Consider flanking, cover, allies nearby
  }
}
```

---

### 8. LineOfSightSystem

**Purpose:** Calculate visibility and fog of war

**Operates On:** Entities with `PositionComponent`

**Key Methods:**
```javascript
class LineOfSightSystem {
  // Check if entity can see target
  hasLineOfSight(entity, target) {
    // Bresenham's algorithm for hex grid
  }
  
  // Calculate visible hexes from position
  getVisibleHexes(q, r, visionRange) {
    // Returns array of visible hex coords
  }
  
  // Update fog of war
  updateFogOfWar(playerEntities) {
    // Combines vision from all player entities
  }
  
  // Check if position provides cover
  hasCover(entityPos, targetPos) {
    // Standard cover (+2 AC), Greater cover (+4 AC)
  }
}
```

---

## Entity Templates

Templates for creating common entity types with predefined components.

### Player Character Template
```javascript
const PlayerCharacterTemplate = {
  components: [
    IdentityComponent,
    PositionComponent,
    StatsComponent,
    ActionsComponent,
    StatusEffectsComponent,
    MovementComponent,
    CombatComponent,
    RenderComponent,
    AbilitiesComponent
  ],
  defaults: {
    team: 'player',
    maxActions: 3,
    canMove: true,
    isInCombat: false
  }
};
```

### Creature Template
```javascript
const CreatureTemplate = {
  components: [
    IdentityComponent,
    PositionComponent,
    StatsComponent,
    ActionsComponent,
    StatusEffectsComponent,
    MovementComponent,
    CombatComponent,
    RenderComponent,
    AbilitiesComponent,
    AIComponent
  ],
  defaults: {
    team: 'enemy',
    maxActions: 3,
    canMove: true,
    isInCombat: false,
    behaviorType: 'aggressive'
  }
};
```

### Item Template
```javascript
const ItemTemplate = {
  components: [
    IdentityComponent,
    PositionComponent,
    RenderComponent
  ],
  defaults: {
    entityType: 'item',
    visible: true
  }
};
```

### Obstacle Template
```javascript
const ObstacleTemplate = {
  components: [
    IdentityComponent,
    PositionComponent,
    RenderComponent
  ],
  defaults: {
    entityType: 'obstacle',
    blocksMovement: true,
    blocksLineOfSight: true
  }
};
```

---

## Game State Structure

### EncounterState
```javascript
class EncounterState {
  encounterId: number;
  entities: Map<number, Entity>;      // entityId -> Entity
  initiativeOrder: number[];          // Sorted entity IDs
  currentTurnIndex: number;
  currentRound: number;
  encounterStatus: EncounterStatus;
  mapData: MapData;
  fogOfWar: Set<string>;              // Set of "q_r" strings for hidden hexes
  actionQueue: QueuedAction[];
  history: EncounterHistory;
}

enum EncounterStatus {
  NOT_STARTED = 'not_started',
  IN_PROGRESS = 'in_progress',
  PLAYER_VICTORY = 'player_victory',
  PLAYER_DEFEAT = 'player_defeat',
  FLED = 'fled'
}

interface Entity {
  id: number;
  components: Map<string, Component>;
}

interface MapData {
  width: number;
  height: number;
  terrain: Map<string, TerrainType>;  // "q_r" -> terrain type
  elevation: Map<string, number>;     // "q_r" -> elevation
}
```

### QueuedAction
```javascript
interface QueuedAction {
  entityId: number;
  actionType: string;
  target?: ActionTarget;
  priority: number;
  timestamp: number;
}

interface ActionTarget {
  type: 'entity' | 'hex' | 'area';
  entityId?: number;
  hex?: { q: number, r: number };
  area?: { center: { q: number, r: number }, radius: number };
}
```

### EncounterHistory
```javascript
interface EncounterHistory {
  rounds: RoundHistory[];
}

interface RoundHistory {
  roundNumber: number;
  turns: TurnHistory[];
}

interface TurnHistory {
  entityId: number;
  actions: ActionResult[];
}

interface ActionResult {
  action: string;
  success: boolean;
  rolls?: { type: string, result: number }[];
  damage?: number;
  effects?: string[];
}
```

---

## Database Schema

### Core Tables

```sql
-- Entities table
CREATE TABLE entities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  encounter_id INT NOT NULL,
  entity_type ENUM('player_character', 'npc', 'creature', 'item', 'obstacle', 'trap', 'treasure', 'hazard') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_encounter (encounter_id)
);

-- Encounters table
CREATE TABLE encounters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dungeon_level_id INT NOT NULL,
  status ENUM('not_started', 'in_progress', 'player_victory', 'player_defeat', 'fled') DEFAULT 'not_started',
  current_round INT DEFAULT 1,
  current_turn_entity_id INT NULL,
  initiative_order JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_dungeon_level (dungeon_level_id)
);

-- Map data table
CREATE TABLE map_hexes (
  encounter_id INT NOT NULL,
  q INT NOT NULL,
  r INT NOT NULL,
  terrain_type ENUM('floor', 'wall', 'difficult', 'water', 'pit') DEFAULT 'floor',
  elevation INT DEFAULT 0,
  is_visible BOOLEAN DEFAULT FALSE,
  is_explored BOOLEAN DEFAULT FALSE,
  PRIMARY KEY (encounter_id, q, r),
  INDEX idx_encounter (encounter_id)
);

-- Component tables (see component definitions above for schemas)
```

---

## Integration with PixiJS

### Architecture

```
┌─────────────────────────────────────────────────┐
│              ECS Game State                     │
│                                                 │
│  Entity 1: [Position, Stats, Render, Combat]   │
│  Entity 2: [Position, Render, AI]              │
│  Entity 3: [Position, Item]                    │
└───────────────────┬─────────────────────────────┘
                    │
                    ↓
┌─────────────────────────────────────────────────┐
│           RenderSystem (Bridge)                 │
│                                                 │
│  - Reads PositionComponent, RenderComponent    │
│  - Updates PIXI.Sprite positions               │
│  - Handles animations                           │
│  - Manages sprite lifecycle                     │
└───────────────────┬─────────────────────────────┘
                    │
                    ↓
┌─────────────────────────────────────────────────┐
│          PixiJS Rendering Layer                 │
│                                                 │
│  - hexContainer (terrain)                       │
│  - objectContainer (entities)                   │
│  - uiContainer (health bars, effects)          │
│  - fogContainer (fog of war)                    │
└─────────────────────────────────────────────────┘
```

### RenderComponent ↔ PIXI.Sprite Mapping

```javascript
class RenderSystem {
  syncEntityToSprite(entity) {
    const render = entity.getComponent('RenderComponent');
    const position = entity.getComponent('PositionComponent');
    
    // Create sprite if doesn't exist
    if (!render.sprite) {
      render.sprite = this.createSprite(render.spriteKey);
      this.objectContainer.addChild(render.sprite);
    }
    
    // Update position
    const pixelPos = this.hexToPixel(position.q, position.r);
    render.sprite.x = pixelPos.x;
    render.sprite.y = pixelPos.y;
    
    // Update appearance
    render.sprite.scale.set(render.scale);
    render.sprite.rotation = render.rotation;
    render.sprite.tint = render.tint;
    render.sprite.visible = render.visible;
    render.sprite.zIndex = render.zIndex;
    
    // Update health bar
    this.updateHealthBar(entity);
  }
  
  updateHealthBar(entity) {
    const stats = entity.getComponent('StatsComponent');
    const render = entity.getComponent('RenderComponent');
    
    if (!render.healthBar) {
      render.healthBar = this.createHealthBar();
      render.sprite.addChild(render.healthBar);
    }
    
    const healthPercent = stats.currentHP / stats.maxHP;
    this.redrawHealthBar(render.healthBar, healthPercent);
  }
}
```

### Game Loop Integration

```javascript
// Main game loop
class GameEngine {
  update(deltaTime) {
    // 1. Process queued actions
    this.actionQueue.process();
    
    // 2. Update systems
    this.statusEffectSystem.update(this.currentRound);
    this.movementSystem.update();
    this.combatSystem.update();
    
    // 3. Check turn/round progression
    this.turnSystem.update();
    
    // 4. AI turns (if current entity has AIComponent)
    const currentEntity = this.getCurrentEntity();
    if (currentEntity.hasComponent('AIComponent')) {
      this.aiSystem.executeTurn(currentEntity);
    }
    
    // 5. Sync visual state
    this.renderSystem.update();
    
    // 6. Update PixiJS
    this.pixiApp.render();
  }
}
```

---

## Implementation Roadmap

### Phase 1: Core ECS Foundation (Week 1-2)
- [ ] Create base Entity, Component, System classes
- [ ] Implement EntityManager (CRUD operations)
- [ ] Create core components (Position, Stats, Identity, Render)
- [ ] Implement RenderSystem integration with existing PixiJS
- [ ] Database schema creation

### Phase 2: Movement System (Week 3)
- [ ] Implement MovementComponent
- [ ] Create MovementSystem with pathfinding (A* algorithm)
- [ ] Add movement range calculation
- [ ] Implement movement animation
- [ ] UI: Movement range overlay visualization

### Phase 3: Turn Management (Week 4)
- [ ] Implement ActionsComponent
- [ ] Create TurnManagementSystem
- [ ] Add initiative rolling
- [ ] Implement action economy (3 actions per turn)
- [ ] UI: Turn order display, action counter

### Phase 4: Combat System (Week 5-6)
- [ ] Implement CombatComponent
- [ ] Create CombatSystem (attacks, damage)
- [ ] Add attack range visualization
- [ ] Implement Multiple Attack Penalty
- [ ] Add damage types and resistances
- [ ] UI: Attack targeting, damage numbers

### Phase 5: Status Effects (Week 7)
- [ ] Implement StatusEffectsComponent
- [ ] Create StatusEffectSystem
- [ ] Add PF2e conditions (prone, stunned, etc.)
- [ ] Implement buff/debuff system
- [ ] Duration tracking
- [ ] UI: Status icons above entities

### Phase 6: AI System (Week 8)
- [ ] Implement AIComponent
- [ ] Create AISystem with basic behaviors
- [ ] Add target selection logic
- [ ] Implement decision tree
- [ ] Test with various creature types

### Phase 7: Abilities & Spells (Week 9-10)
- [ ] Implement AbilitiesComponent
- [ ] Add ability activation system
- [ ] Create spell targeting system
- [ ] Implement area effects
- [ ] UI: Ability buttons, targeting reticle

### Phase 8: Advanced Features (Week 11-12)
- [ ] LineOfSightSystem and fog of war
- [ ] Cover and flanking mechanics
- [ ] Terrain effects (difficult terrain, hazards)
- [ ] Save/load encounter state
- [ ] Encounter history and replay

### Phase 9: Polish & Testing (Week 13-14)
- [ ] Performance optimization
- [ ] Animation polish
- [ ] Sound effects integration
- [ ] Comprehensive testing
- [ ] Balance adjustments

---

## JavaScript/TypeScript Implementation Example

### Entity Class
```javascript
class Entity {
  constructor(id) {
    this.id = id;
    this.components = new Map();
  }
  
  addComponent(componentName, componentData) {
    this.components.set(componentName, componentData);
  }
  
  getComponent(componentName) {
    return this.components.get(componentName);
  }
  
  hasComponent(componentName) {
    return this.components.has(componentName);
  }
  
  removeComponent(componentName) {
    this.components.delete(componentName);
  }
}
```

### EntityManager Class
```javascript
class EntityManager {
  constructor() {
    this.entities = new Map();
    this.nextEntityId = 1;
  }
  
  createEntity() {
    const entity = new Entity(this.nextEntityId++);
    this.entities.set(entity.id, entity);
    return entity;
  }
  
  getEntity(id) {
    return this.entities.get(id);
  }
  
  removeEntity(id) {
    this.entities.delete(id);
  }
  
  // Query entities by component
  getEntitiesWith(...componentNames) {
    const result = [];
    for (const entity of this.entities.values()) {
      if (componentNames.every(name => entity.hasComponent(name))) {
        result.push(entity);
      }
    }
    return result;
  }
}
```

### System Base Class
```javascript
class System {
  constructor(entityManager) {
    this.entityManager = entityManager;
  }
  
  update(deltaTime) {
    // Override in subclasses
  }
  
  getEntities() {
    // Override to specify required components
    return [];
  }
}
```

---

## Best Practices

### 1. Component Design
- ✅ **DO:** Keep components as pure data (no logic)
- ✅ **DO:** Use small, focused components
- ❌ **DON'T:** Create "god components" with too much data
- ❌ **DON'T:** Add methods to components (use systems)

### 2. System Design
- ✅ **DO:** Keep systems focused on one responsibility
- ✅ **DO:** Make systems stateless (all state in entities)
- ✅ **DO:** Define clear system execution order
- ❌ **DON'T:** Have systems modify each other
- ❌ **DON'T:** Store entity references in systems

### 3. Performance
- ✅ **DO:** Use component queries efficiently
- ✅ **DO:** Cache frequently accessed data
- ✅ **DO:** Only update what changed
- ❌ **DON'T:** Query all entities every frame
- ❌ **DON'T:** Create/destroy entities during update loops

### 4. Serialization
- ✅ **DO:** Design components to be JSON-serializable
- ✅ **DO:** Separate transient data (sprites) from persistent data
- ✅ **DO:** Version your save format
- ❌ **DON'T:** Store PIXI objects in database

---

## Next Steps

1. **Create ECS Foundation**: Implement Entity, Component, System base classes
2. **Migrate Existing Code**: Refactor current hexmap.js to use ECS pattern
3. **Build Core Systems**: Start with RenderSystem and MovementSystem
4. **Test Incrementally**: Keep existing functionality working while refactoring
5. **Add New Features**: Build on solid ECS foundation

---

## References

- [Entity-Component-System FAQ](https://github.com/SanderMertens/ecs-faq)
- [bitECS Documentation](https://github.com/NateTheGreatt/bitECS)
- [Pathfinder 2E Rules](https://2e.aonprd.com)
- [A* Pathfinding for Hex Grids](https://www.redblobgames.com/grids/hexagons/)

---

**Document Status**: Draft v1.0  
**Last Updated**: 2026-02-12  
**Next Review**: After Phase 1 implementation
