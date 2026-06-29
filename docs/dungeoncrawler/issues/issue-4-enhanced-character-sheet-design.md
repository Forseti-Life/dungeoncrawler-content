# Issue #4: Enhanced Character Sheet - Design Document

## Overview
Design a comprehensive character view and management system for real-time Pathfinder 2E gameplay. This system will enable players to view and update character stats, track conditions, manage inventory, cast spells, and track resources during active game sessions.

**Note:** This is a **DESIGN-ONLY** document. Implementation will be handled in separate PRs.

## Design Goals

1. **Real-Time Updates**: Character state synchronizes across all clients viewing the character
2. **Mobile-First**: Responsive design that works on phones, tablets, and desktop
3. **Performance**: Handle rapid updates during combat without lag
4. **User-Friendly**: Intuitive interface for both new and experienced players
5. **Accurate**: Correctly implement PF2e rules and mechanics
6. **Extensible**: Easy to add new features and game content

---

## UI/UX Wireframes

### Desktop Layout (≥1024px)

```
┌──────────────────────────────────────────────────────────────────────┐
│  Character Sheet: Grok the Mighty (Level 5 Fighter)           [Menu] │
├─────────────────┬────────────────────────────────────────────────────┤
│                 │ ┌──────────────────────────────────────────────┐   │
│  ┌───────────┐  │ │ Resources & Vitals                           │   │
│  │   Avatar  │  │ │  HP: ████████░░ 42/50    Temp HP: +5         │   │
│  │   Image   │  │ │  Stamina: ███████░ 14/16                     │   │
│  │           │  │ │  Hero Points: ⬤⬤○                            │   │
│  └───────────┘  │ └──────────────────────────────────────────────┘   │
│                 │ ┌──────────────────────────────────────────────┐   │
│  Level 5        │ │ Defenses                                      │   │
│  XP: 2100/3000  │ │  AC: 22 (19 flat, 18 touch)                  │   │
│                 │ │  Fort: +11  Ref: +8  Will: +6                │   │
│  [Level Up]     │ │  Perception: +8                               │   │
│                 │ └──────────────────────────────────────────────┘   │
├─────────────────┤ ┌──────────────────────────────────────────────┐   │
│ Attributes      │ │ Conditions & Effects                          │   │
│  STR +4  DEX +2 │ │  [+] Add Condition                            │   │
│  CON +3  INT +0 │ │  ⚠ Wounded 1 (-1 to recovery checks)         │   │
│  WIS +1  CHA -1 │ │  🛡 Shield Raised (+2 AC until next turn)     │   │
│                 │ └──────────────────────────────────────────────┘   │
│ Skills          │                                                    │
│  Acrobatics +9  │ [Actions] [Spells] [Abilities] [Inventory]       │
│  Athletics +11  │ ┌────────────────────────────────────────────────┤
│  Crafting +7    │ │ Current Tab Content (Actions, Spells, etc)    │
│  ... (expand)   │ │                                                │
└─────────────────┴─┴────────────────────────────────────────────────┘
```

### Mobile Layout (≤768px)

```
┌──────────────────────────────┐
│ ☰  Grok the Mighty   [Menu] │
├──────────────────────────────┤
│ ┌──────────────────────────┐ │
│ │ HP: ████░ 42/50 (+5)     │ │
│ │ Stamina: ███ 14/16       │ │
│ │ Hero Points: ⬤⬤○         │ │
│ └──────────────────────────┘ │
│ ┌──────────────────────────┐ │
│ │ AC: 22  Fort: +11        │ │
│ │ Ref: +8  Will: +6        │ │
│ └──────────────────────────┘ │
│ ┌──────────────────────────┐ │
│ │ Conditions: 2 active     │ │
│ │ • Wounded 1  • Shield ↑  │ │
│ └──────────────────────────┘ │
│                              │
│ Quick Actions:               │
│ [Strike] [Move] [Cast]       │
│                              │
│ Tabs:                        │
│ [Stats][Skills][Spells][Inv] │
│                              │
│ (Tab content scrolls)        │
└──────────────────────────────┘
```

### Component Breakdown

#### 1. Header Bar
- Character name and level
- Campaign indicator (if in active campaign)
- Menu (settings, export, delete character)
- Real-time sync indicator (green dot when connected)

#### 2. Resource Panel (Always Visible)
- **Hit Points**: Visual bar + numeric (current/max)
- **Temporary HP**: Displayed as bonus overlay
- **Stamina/Resolve**: Class-specific resources
- **Hero Points**: Visual dots (max 3)
- **Focus Points**: For spellcasters
- **Spell Slots**: By level (1st: ●●○, 2nd: ●○○)
- Quick adjustment buttons (+/-) on hover/tap

#### 3. Defenses Panel
- Armor Class (AC) with flat-footed and touch variants
- Saving throws (Fortitude, Reflex, Will)
- Perception
- Conditional modifiers (e.g., "vs. magic +2")

#### 4. Conditions Panel
- Active conditions with icons and tooltips
- Duration tracking (rounds remaining)
- Quick remove button (×)
- Add condition button with searchable dropdown
- Color-coded severity (red = harmful, blue = beneficial, yellow = neutral)

#### 5. Actions Tab
- Three-action economy visualizer: ●●● (available) ○○○ (used)
- Reaction status: ⚡ (available) ⚪ (used)
- Common actions with quick-use buttons:
  - Strike (with weapon selector)
  - Move/Stride
  - Interact
  - Cast a Spell
  - Raise Shield
  - Aid
- Custom actions/macros

#### 6. Spells Tab
- Organized by spell level
- Prepared vs. Spontaneous display
- Cast button with confirmation
- Heightening options
- Spell slot consumption tracking
- Focus spell separate section
- Cantrips always available

#### 7. Abilities Tab
- Class features
- Ancestry features
- Feats
- Special abilities
- Searchable and filterable

#### 8. Inventory Tab
- Worn armor and weapons (quick access)
- Carried items with bulk tracking
- Consumables (potions, scrolls, etc.)
- Treasure and currency
- Quick equip/stow actions
- Bulk calculator (light/medium/heavy encumbrance)

#### 9. Skills Panel (Sidebar/Expandable)
- All skills with proficiency level
- Modifiers calculated
- Roll button for each skill
- Lore skills separate section

#### 10. Character Progression
- Current XP and XP to next level
- Progress bar
- Level up button (when eligible)
- Milestone tracking (optional)

---

## Data Structure for Character State

### CharacterState Object

```typescript
interface CharacterState {
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
      max: number; // typically 3
    };
    focusPoints?: {
      current: number;
      max: number;
    };
    spellSlots?: {
      [level: string]: { // "1", "2", "3", etc.
        current: number;
        max: number;
      };
    };
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
      actionsRemaining: number; // 0-3
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
    createdAt: string; // ISO 8601
    updatedAt: string; // ISO 8601
    lastSyncedAt: string; // ISO 8601
    version: number; // for optimistic locking
  };
}

// Supporting Types

interface Modifier {
  id: string;
  name: string;
  value: number;
  type: 'circumstance' | 'status' | 'item' | 'untyped';
  source: string; // e.g., "Inspire Courage", "Magic Weapon"
  duration?: Duration;
}

interface Condition {
  id: string;
  name: string;
  description: string;
  severity: 'harmful' | 'beneficial' | 'neutral';
  value?: number; // for conditions with values (e.g., Wounded 2)
  duration?: Duration;
  effects: Effect[];
  appliedAt: string; // ISO 8601
}

interface Duration {
  type: 'rounds' | 'minutes' | 'hours' | 'days' | 'permanent' | 'encounter' | 'until_removed';
  value?: number;
  endsAt?: string; // ISO 8601 for time-based durations
}

interface Effect {
  type: 'modifier' | 'immunity' | 'resistance' | 'vulnerability' | 'special';
  target: string; // what it affects (e.g., "AC", "speed", "attack_rolls")
  value: number | string;
}

interface Action {
  id: string;
  name: string;
  actionCost: number; // 1, 2, 3, or 0 for free actions
  isReaction: boolean;
  description: string;
  traits: string[];
  requirements?: string;
  effects: ActionEffect[];
}

interface ActionEffect {
  type: 'damage' | 'heal' | 'condition' | 'movement' | 'custom';
  details: any; // type-specific details
}

interface Spell {
  id: string;
  name: string;
  level: number; // 0 for cantrips
  tradition: 'arcane' | 'divine' | 'occult' | 'primal';
  school: string;
  traits: string[];
  castingTime: string; // "2 actions", "1 reaction", etc.
  components: string[]; // ["somatic", "verbal"]
  range: string;
  area?: string;
  targets?: string;
  duration: string;
  savingThrow?: string;
  description: string;
  heightenedEffects?: { [level: string]: string };
}

interface PreparedSpell {
  spellId: string;
  level: number;
  expended: boolean;
}

interface Item {
  id: string;
  name: string;
  type: 'weapon' | 'armor' | 'consumable' | 'treasure' | 'tool' | 'other';
  quantity: number;
  bulk: number;
  equipped: boolean;
  description: string;
  properties?: any; // type-specific properties (weapon damage, armor AC bonus, etc.)
}

interface Feature {
  id: string;
  name: string;
  description: string;
  level: number;
  source: string;
}

interface Feat {
  id: string;
  name: string;
  type: 'ancestry' | 'class' | 'general' | 'skill';
  level: number;
  description: string;
  prerequisites?: string[];
  traits: string[];
}
```

---

## CharacterState Service Pseudocode

### Service Architecture

```typescript
/**
 * CharacterStateService
 * 
 * Manages character state, handles updates, and synchronizes with backend.
 * Implements optimistic updates with rollback on failure.
 */
class CharacterStateService {
  private characterState: CharacterState;
  private websocket: WebSocket;
  private updateQueue: UpdateOperation[];
  private listeners: Map<string, Function[]>;
  
  constructor(characterId: string) {
    this.characterState = null;
    this.updateQueue = [];
    this.listeners = new Map();
  }
  
  /**
   * Initialize the service: load character and establish WebSocket
   */
  async initialize(characterId: string): Promise<void> {
    // Load initial state from API
    const response = await fetch(`/api/character/${characterId}/state`);
    this.characterState = await response.json();
    
    // Establish WebSocket connection
    this.connectWebSocket(characterId);
    
    // Start update queue processor
    this.processUpdateQueue();
  }
  
  /**
   * Establish WebSocket connection for real-time updates
   */
  private connectWebSocket(characterId: string): void {
    this.websocket = new WebSocket(`wss://domain/ws/character/${characterId}`);
    
    this.websocket.onopen = () => {
      console.log('WebSocket connected');
      this.emit('connection', { status: 'connected' });
    };
    
    this.websocket.onmessage = (event) => {
      const update = JSON.parse(event.data);
      this.handleRemoteUpdate(update);
    };
    
    this.websocket.onerror = (error) => {
      console.error('WebSocket error:', error);
      this.emit('connection', { status: 'error', error });
    };
    
    this.websocket.onclose = () => {
      console.log('WebSocket closed, attempting reconnect...');
      this.emit('connection', { status: 'disconnected' });
      setTimeout(() => this.connectWebSocket(characterId), 3000);
    };
  }
  
  /**
   * Get current character state (immutable copy)
   */
  getState(): CharacterState {
    return JSON.parse(JSON.stringify(this.characterState));
  }
  
  /**
   * Update hit points
   */
  async updateHitPoints(delta: number, temporary: boolean = false): Promise<void> {
    const operation: UpdateOperation = {
      type: 'UPDATE_HP',
      path: temporary ? 'resources.hitPoints.temporary' : 'resources.hitPoints.current',
      value: delta,
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    // Optimistic update
    if (temporary) {
      this.characterState.resources.hitPoints.temporary += delta;
    } else {
      this.characterState.resources.hitPoints.current = Math.max(
        0,
        Math.min(
          this.characterState.resources.hitPoints.max,
          this.characterState.resources.hitPoints.current + delta
        )
      );
    }
    
    this.emit('state-changed', this.characterState);
    
    // Queue for server sync
    this.queueUpdate(operation);
  }
  
  /**
   * Add condition to character
   */
  async addCondition(condition: Condition): Promise<void> {
    const operation: UpdateOperation = {
      type: 'ADD_CONDITION',
      path: 'conditions',
      value: condition,
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    // Optimistic update
    this.characterState.conditions.push(condition);
    this.applyConditionEffects(condition);
    this.emit('state-changed', this.characterState);
    
    // Queue for server sync
    this.queueUpdate(operation);
  }
  
  /**
   * Remove condition from character
   */
  async removeCondition(conditionId: string): Promise<void> {
    const condition = this.characterState.conditions.find(c => c.id === conditionId);
    if (!condition) return;
    
    const operation: UpdateOperation = {
      type: 'REMOVE_CONDITION',
      path: 'conditions',
      value: conditionId,
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    // Optimistic update
    this.characterState.conditions = this.characterState.conditions.filter(
      c => c.id !== conditionId
    );
    this.removeConditionEffects(condition);
    this.emit('state-changed', this.characterState);
    
    // Queue for server sync
    this.queueUpdate(operation);
  }
  
  /**
   * Cast a spell (consume slot or focus point)
   */
  async castSpell(spellId: string, level: number, isFocusSpell: boolean = false): Promise<void> {
    if (isFocusSpell) {
      if (this.characterState.resources.focusPoints.current <= 0) {
        throw new Error('No focus points remaining');
      }
      
      this.characterState.resources.focusPoints.current -= 1;
    } else {
      const slotKey = level.toString();
      if (!this.characterState.resources.spellSlots[slotKey] || 
          this.characterState.resources.spellSlots[slotKey].current <= 0) {
        throw new Error(`No level ${level} spell slots remaining`);
      }
      
      this.characterState.resources.spellSlots[slotKey].current -= 1;
    }
    
    const operation: UpdateOperation = {
      type: 'CAST_SPELL',
      path: isFocusSpell ? 'resources.focusPoints' : `resources.spellSlots.${level}`,
      value: { spellId, level, isFocusSpell },
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    this.emit('state-changed', this.characterState);
    this.queueUpdate(operation);
  }
  
  /**
   * Use an action (track three-action economy)
   */
  async useAction(actionCost: number = 1): Promise<void> {
    if (this.characterState.actions.threeActionEconomy.actionsRemaining < actionCost) {
      throw new Error('Not enough actions remaining');
    }
    
    this.characterState.actions.threeActionEconomy.actionsRemaining -= actionCost;
    
    const operation: UpdateOperation = {
      type: 'USE_ACTION',
      path: 'actions.threeActionEconomy.actionsRemaining',
      value: actionCost,
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    this.emit('state-changed', this.characterState);
    this.queueUpdate(operation);
  }
  
  /**
   * Use reaction
   */
  async useReaction(): Promise<void> {
    if (!this.characterState.actions.threeActionEconomy.reactionAvailable) {
      throw new Error('Reaction already used');
    }
    
    this.characterState.actions.threeActionEconomy.reactionAvailable = false;
    
    const operation: UpdateOperation = {
      type: 'USE_REACTION',
      path: 'actions.threeActionEconomy.reactionAvailable',
      value: false,
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    this.emit('state-changed', this.characterState);
    this.queueUpdate(operation);
  }
  
  /**
   * Start new turn (reset actions and reaction)
   */
  async startNewTurn(): Promise<void> {
    this.characterState.actions.threeActionEconomy.actionsRemaining = 3;
    this.characterState.actions.threeActionEconomy.reactionAvailable = true;
    
    // Decrement condition durations
    this.updateConditionDurations();
    
    const operation: UpdateOperation = {
      type: 'START_TURN',
      path: 'actions.threeActionEconomy',
      value: { actionsRemaining: 3, reactionAvailable: true },
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    this.emit('state-changed', this.characterState);
    this.queueUpdate(operation);
  }
  
  /**
   * Update inventory (add, remove, equip items)
   */
  async updateInventory(action: 'add' | 'remove' | 'equip' | 'unequip', item: Item): Promise<void> {
    const operation: UpdateOperation = {
      type: 'UPDATE_INVENTORY',
      path: 'inventory',
      value: { action, item },
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    switch (action) {
      case 'add':
        this.characterState.inventory.carried.push(item);
        break;
      case 'remove':
        this.characterState.inventory.carried = this.characterState.inventory.carried.filter(
          i => i.id !== item.id
        );
        break;
      case 'equip':
        // Move from carried to worn
        this.characterState.inventory.carried = this.characterState.inventory.carried.filter(
          i => i.id !== item.id
        );
        if (item.type === 'weapon') {
          this.characterState.inventory.worn.weapons.push(item);
        } else if (item.type === 'armor') {
          this.characterState.inventory.worn.armor = item;
        } else {
          this.characterState.inventory.worn.accessories.push(item);
        }
        break;
      case 'unequip':
        // Move from worn to carried
        // (implementation details omitted for brevity)
        break;
    }
    
    this.recalculateBulk();
    this.emit('state-changed', this.characterState);
    this.queueUpdate(operation);
  }
  
  /**
   * Gain experience points
   */
  async gainExperience(xp: number): Promise<void> {
    this.characterState.basicInfo.experiencePoints += xp;
    
    const operation: UpdateOperation = {
      type: 'GAIN_XP',
      path: 'basicInfo.experiencePoints',
      value: xp,
      timestamp: Date.now(),
      version: this.characterState.metadata.version,
    };
    
    this.emit('state-changed', this.characterState);
    this.queueUpdate(operation);
    
    // Check if level up is available
    if (this.isLevelUpAvailable()) {
      this.emit('level-up-available', { level: this.characterState.basicInfo.level + 1 });
    }
  }
  
  /**
   * Queue an update for server synchronization
   */
  private queueUpdate(operation: UpdateOperation): void {
    this.updateQueue.push(operation);
  }
  
  /**
   * Process queued updates (batch send to server)
   */
  private async processUpdateQueue(): Promise<void> {
    setInterval(async () => {
      if (this.updateQueue.length === 0) return;
      
      const operations = [...this.updateQueue];
      this.updateQueue = [];
      
      try {
        const response = await fetch(`/api/character/${this.characterState.characterId}/update`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ operations }),
        });
        
        if (!response.ok) {
          throw new Error('Update failed');
        }
        
        const result = await response.json();
        
        // Update version for optimistic locking
        this.characterState.metadata.version = result.version;
        this.characterState.metadata.lastSyncedAt = new Date().toISOString();
        
      } catch (error) {
        console.error('Failed to sync updates:', error);
        // Re-queue failed operations
        this.updateQueue.unshift(...operations);
        this.emit('sync-error', { error, operations });
      }
    }, 1000); // Batch updates every 1 second
  }
  
  /**
   * Handle updates received from WebSocket
   */
  private handleRemoteUpdate(update: any): void {
    // Only apply if version is newer
    if (update.version <= this.characterState.metadata.version) {
      return;
    }
    
    // Apply the update
    switch (update.type) {
      case 'UPDATE_HP':
        // Apply HP change from another client
        this.characterState.resources.hitPoints.current = update.value;
        break;
      case 'ADD_CONDITION':
        this.characterState.conditions.push(update.value);
        this.applyConditionEffects(update.value);
        break;
      case 'REMOVE_CONDITION':
        this.characterState.conditions = this.characterState.conditions.filter(
          c => c.id !== update.value
        );
        break;
      // ... other update types
    }
    
    this.characterState.metadata.version = update.version;
    this.emit('state-changed', this.characterState);
    this.emit('remote-update', update);
  }
  
  /**
   * Apply condition effects to character state
   */
  private applyConditionEffects(condition: Condition): void {
    condition.effects.forEach(effect => {
      if (effect.type === 'modifier') {
        // Add modifier to appropriate location
        // (implementation details omitted for brevity)
      }
    });
  }
  
  /**
   * Remove condition effects from character state
   */
  private removeConditionEffects(condition: Condition): void {
    // Reverse of applyConditionEffects
    // (implementation details omitted for brevity)
  }
  
  /**
   * Update condition durations (called at start of turn)
   */
  private updateConditionDurations(): void {
    this.characterState.conditions = this.characterState.conditions.filter(condition => {
      if (!condition.duration) return true;
      
      if (condition.duration.type === 'rounds') {
        condition.duration.value -= 1;
        if (condition.duration.value <= 0) {
          this.removeConditionEffects(condition);
          return false; // Remove condition
        }
      }
      
      return true;
    });
  }
  
  /**
   * Recalculate total bulk and encumbrance
   */
  private recalculateBulk(): void {
    let totalBulk = 0;
    
    // Worn items
    if (this.characterState.inventory.worn.armor) {
      totalBulk += this.characterState.inventory.worn.armor.bulk;
    }
    this.characterState.inventory.worn.weapons.forEach(w => totalBulk += w.bulk);
    this.characterState.inventory.worn.accessories.forEach(a => totalBulk += a.bulk);
    
    // Carried items
    this.characterState.inventory.carried.forEach(item => totalBulk += item.bulk * item.quantity);
    
    this.characterState.inventory.totalBulk = totalBulk;
    
    // Determine encumbrance
    const strScore = this.characterState.abilities.strength;
    const encumberedAt = 5 + strScore;
    const overloadedAt = 10 + strScore;
    
    if (totalBulk >= overloadedAt) {
      this.characterState.inventory.encumbrance = 'overloaded';
    } else if (totalBulk >= encumberedAt) {
      this.characterState.inventory.encumbrance = 'encumbered';
    } else {
      this.characterState.inventory.encumbrance = 'unencumbered';
    }
  }
  
  /**
   * Check if character has enough XP to level up
   */
  private isLevelUpAvailable(): boolean {
    const currentLevel = this.characterState.basicInfo.level;
    const currentXP = this.characterState.basicInfo.experiencePoints;
    const xpForNextLevel = 1000 * currentLevel; // Simplified XP table
    
    return currentXP >= xpForNextLevel;
  }
  
  /**
   * Event emitter pattern for state changes
   */
  on(event: string, callback: Function): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event).push(callback);
  }
  
  private emit(event: string, data: any): void {
    const callbacks = this.listeners.get(event) || [];
    callbacks.forEach(callback => callback(data));
  }
  
  /**
   * Cleanup: close WebSocket and save state
   */
  async destroy(): Promise<void> {
    if (this.websocket) {
      this.websocket.close();
    }
    
    // Flush remaining updates
    if (this.updateQueue.length > 0) {
      await this.processUpdateQueue();
    }
  }
}

interface UpdateOperation {
  type: string;
  path: string;
  value: any;
  timestamp: number;
  version: number;
}
```

---

## API Endpoints Design

### RESTful API Endpoints

#### 1. Get Character State
```
GET /api/character/{characterId}/state

Response 200:
{
  "success": true,
  "data": CharacterState,
  "version": 42
}
```

#### 2. Update Character State (Batch)
```
POST /api/character/{characterId}/update

Request Body:
{
  "operations": [
    {
      "type": "UPDATE_HP",
      "path": "resources.hitPoints.current",
      "value": -10,
      "timestamp": 1676384567890,
      "version": 42
    },
    {
      "type": "ADD_CONDITION",
      "path": "conditions",
      "value": { ... },
      "timestamp": 1676384567891,
      "version": 42
    }
  ]
}

Response 200:
{
  "success": true,
  "version": 43,
  "appliedOperations": 2,
  "conflicts": []
}

Response 409 (Conflict):
{
  "success": false,
  "error": "Version conflict",
  "currentVersion": 43,
  "clientVersion": 42,
  "latestState": CharacterState
}
```

#### 3. Get Character Summary (Lightweight)
```
GET /api/character/{characterId}/summary

Response 200:
{
  "success": true,
  "data": {
    "characterId": "123",
    "name": "Grok",
    "level": 5,
    "class": "Fighter",
    "hp": { "current": 42, "max": 50 },
    "conditions": ["Wounded 1"],
    "lastUpdated": "2026-02-12T22:00:00Z"
  }
}
```

#### 4. Cast Spell
```
POST /api/character/{characterId}/cast-spell

Request Body:
{
  "spellId": "fireball",
  "level": 3,
  "isFocusSpell": false,
  "targets": ["enemy1", "enemy2"],
  "heightened": false
}

Response 200:
{
  "success": true,
  "spellSlotConsumed": { "level": 3, "remaining": 1 },
  "effects": [ ... ]
}

Response 400:
{
  "success": false,
  "error": "No spell slots remaining at level 3"
}
```

#### 5. Update Hit Points
```
POST /api/character/{characterId}/hp

Request Body:
{
  "delta": -10,
  "temporary": false,
  "source": "Goblin attack"
}

Response 200:
{
  "success": true,
  "hitPoints": {
    "current": 32,
    "max": 50,
    "temporary": 0
  }
}
```

#### 6. Manage Conditions
```
POST /api/character/{characterId}/conditions

Request Body:
{
  "action": "add",
  "condition": {
    "name": "Frightened",
    "value": 2,
    "duration": { "type": "rounds", "value": 3 }
  }
}

Response 200:
{
  "success": true,
  "conditions": [ ... all active conditions ... ]
}

DELETE /api/character/{characterId}/conditions/{conditionId}

Response 200:
{
  "success": true,
  "message": "Condition removed"
}
```

#### 7. Manage Inventory
```
POST /api/character/{characterId}/inventory

Request Body:
{
  "action": "add",
  "item": {
    "name": "Healing Potion",
    "type": "consumable",
    "quantity": 1,
    "bulk": 0.1
  }
}

Response 200:
{
  "success": true,
  "inventory": { ... },
  "totalBulk": 5.3,
  "encumbrance": "unencumbered"
}
```

#### 8. Gain Experience
```
POST /api/character/{characterId}/experience

Request Body:
{
  "xp": 120,
  "source": "Defeated goblins"
}

Response 200:
{
  "success": true,
  "experiencePoints": 2220,
  "level": 5,
  "levelUpAvailable": false,
  "xpToNextLevel": 780
}
```

#### 9. Level Up Character
```
POST /api/character/{characterId}/level-up

Request Body:
{
  "choices": {
    "abilityBoosts": ["str", "con", "dex", "wis"],
    "classFeat": "Power Attack",
    "skillIncreases": ["Athletics", "Intimidation"]
  }
}

Response 200:
{
  "success": true,
  "newLevel": 6,
  "updatedState": CharacterState
}
```

#### 10. Start Turn (Combat Management)
```
POST /api/character/{characterId}/start-turn

Response 200:
{
  "success": true,
  "actionsRemaining": 3,
  "reactionAvailable": true,
  "conditionsUpdated": ["Frightened reduced to 1", "Shield Raised expired"]
}
```

### API Error Responses

All endpoints follow consistent error format:

```json
{
  "success": false,
  "error": "Human-readable error message",
  "code": "ERROR_CODE",
  "details": { ... additional context ... }
}
```

Common HTTP Status Codes:
- `200` - Success
- `400` - Bad Request (invalid input)
- `401` - Unauthorized (not logged in)
- `403` - Forbidden (not your character)
- `404` - Not Found (character doesn't exist)
- `409` - Conflict (version mismatch)
- `500` - Internal Server Error

---

## Real-Time Sync Design (WebSockets)

### Architecture Overview

```
┌─────────────┐         WebSocket          ┌─────────────┐
│   Client A  │◄──────────────────────────►│   Server    │
│   (Player)  │        (wss://...)          │  (Node.js)  │
└─────────────┘                             └──────┬──────┘
                                                   │
┌─────────────┐         WebSocket                 │
│   Client B  │◄───────────────────────────────────┤
│     (GM)    │                                    │
└─────────────┘                                    │
                                                   │
┌─────────────┐         WebSocket                 │
│   Client C  │◄───────────────────────────────────┘
│  (Observer) │
└─────────────┘
```

### WebSocket Protocol

#### Connection Establishment

```javascript
// Client connects
const ws = new WebSocket('wss://domain/ws/character/123?token=AUTH_TOKEN');

ws.onopen = () => {
  // Subscribe to character updates
  ws.send(JSON.stringify({
    type: 'SUBSCRIBE',
    characterId: '123'
  }));
};
```

#### Message Types

**1. Subscribe to Character**
```json
{
  "type": "SUBSCRIBE",
  "characterId": "123"
}
```

**2. Unsubscribe from Character**
```json
{
  "type": "UNSUBSCRIBE",
  "characterId": "123"
}
```

**3. State Update (Client → Server)**
```json
{
  "type": "UPDATE",
  "characterId": "123",
  "operation": {
    "type": "UPDATE_HP",
    "path": "resources.hitPoints.current",
    "value": -10,
    "timestamp": 1676384567890,
    "version": 42
  }
}
```

**4. State Update Broadcast (Server → Clients)**
```json
{
  "type": "STATE_UPDATE",
  "characterId": "123",
  "operation": {
    "type": "UPDATE_HP",
    "path": "resources.hitPoints.current",
    "value": -10,
    "timestamp": 1676384567890,
    "version": 43
  },
  "userId": "user456",
  "userName": "Alice"
}
```

**5. Full State Sync**
```json
{
  "type": "FULL_SYNC",
  "characterId": "123",
  "state": CharacterState,
  "version": 43
}
```

**6. Heartbeat (Keep-Alive)**
```json
{
  "type": "PING"
}

// Response
{
  "type": "PONG",
  "timestamp": 1676384567890
}
```

**7. Error Message**
```json
{
  "type": "ERROR",
  "error": "Version conflict",
  "code": "VERSION_MISMATCH",
  "details": { ... }
}
```

### Server-Side WebSocket Handler (Pseudocode)

```javascript
class CharacterWebSocketHandler {
  constructor() {
    this.subscriptions = new Map(); // characterId -> Set<WebSocket>
    this.characterVersions = new Map(); // characterId -> version number
  }
  
  async handleConnection(ws, request) {
    // Authenticate user
    const user = await this.authenticateToken(request.token);
    if (!user) {
      ws.close(4001, 'Unauthorized');
      return;
    }
    
    ws.userId = user.id;
    ws.userName = user.name;
    
    // Handle messages
    ws.on('message', async (data) => {
      const message = JSON.parse(data);
      await this.handleMessage(ws, message);
    });
    
    // Cleanup on disconnect
    ws.on('close', () => {
      this.handleDisconnect(ws);
    });
    
    // Send initial connection success
    ws.send(JSON.stringify({ type: 'CONNECTED', userId: user.id }));
  }
  
  async handleMessage(ws, message) {
    switch (message.type) {
      case 'SUBSCRIBE':
        await this.handleSubscribe(ws, message.characterId);
        break;
      
      case 'UNSUBSCRIBE':
        this.handleUnsubscribe(ws, message.characterId);
        break;
      
      case 'UPDATE':
        await this.handleUpdate(ws, message);
        break;
      
      case 'PING':
        ws.send(JSON.stringify({ type: 'PONG', timestamp: Date.now() }));
        break;
      
      default:
        ws.send(JSON.stringify({
          type: 'ERROR',
          error: 'Unknown message type',
          code: 'INVALID_MESSAGE'
        }));
    }
  }
  
  async handleSubscribe(ws, characterId) {
    // Verify user has access to this character
    const hasAccess = await this.verifyCharacterAccess(ws.userId, characterId);
    if (!hasAccess) {
      ws.send(JSON.stringify({
        type: 'ERROR',
        error: 'Access denied',
        code: 'FORBIDDEN'
      }));
      return;
    }
    
    // Add to subscription set
    if (!this.subscriptions.has(characterId)) {
      this.subscriptions.set(characterId, new Set());
    }
    this.subscriptions.get(characterId).add(ws);
    
    // Send current state
    const state = await this.loadCharacterState(characterId);
    ws.send(JSON.stringify({
      type: 'FULL_SYNC',
      characterId,
      state,
      version: this.characterVersions.get(characterId) || 0
    }));
    
    console.log(`User ${ws.userName} subscribed to character ${characterId}`);
  }
  
  handleUnsubscribe(ws, characterId) {
    const subscribers = this.subscriptions.get(characterId);
    if (subscribers) {
      subscribers.delete(ws);
      if (subscribers.size === 0) {
        this.subscriptions.delete(characterId);
      }
    }
  }
  
  async handleUpdate(ws, message) {
    const { characterId, operation } = message;
    
    // Verify ownership (only character owner or GM can update)
    const canUpdate = await this.verifyUpdatePermission(ws.userId, characterId);
    if (!canUpdate) {
      ws.send(JSON.stringify({
        type: 'ERROR',
        error: 'Update permission denied',
        code: 'FORBIDDEN'
      }));
      return;
    }
    
    // Check version for optimistic locking
    const currentVersion = this.characterVersions.get(characterId) || 0;
    if (operation.version !== currentVersion) {
      // Version conflict - send full state to client
      const state = await this.loadCharacterState(characterId);
      ws.send(JSON.stringify({
        type: 'ERROR',
        error: 'Version conflict',
        code: 'VERSION_MISMATCH',
        currentVersion,
        state
      }));
      return;
    }
    
    // Apply update to database
    try {
      await this.applyUpdate(characterId, operation);
      
      // Increment version
      const newVersion = currentVersion + 1;
      this.characterVersions.set(characterId, newVersion);
      
      // Broadcast to all subscribers
      this.broadcast(characterId, {
        type: 'STATE_UPDATE',
        characterId,
        operation: { ...operation, version: newVersion },
        userId: ws.userId,
        userName: ws.userName
      }, ws); // Exclude sender
      
      // Confirm to sender
      ws.send(JSON.stringify({
        type: 'UPDATE_CONFIRMED',
        characterId,
        version: newVersion
      }));
      
    } catch (error) {
      ws.send(JSON.stringify({
        type: 'ERROR',
        error: 'Update failed',
        code: 'UPDATE_FAILED',
        details: error.message
      }));
    }
  }
  
  broadcast(characterId, message, excludeWs = null) {
    const subscribers = this.subscriptions.get(characterId);
    if (!subscribers) return;
    
    const messageStr = JSON.stringify(message);
    subscribers.forEach(ws => {
      if (ws !== excludeWs && ws.readyState === WebSocket.OPEN) {
        ws.send(messageStr);
      }
    });
  }
  
  handleDisconnect(ws) {
    // Remove from all subscriptions
    this.subscriptions.forEach((subscribers, characterId) => {
      subscribers.delete(ws);
      if (subscribers.size === 0) {
        this.subscriptions.delete(characterId);
      }
    });
    
    console.log(`User ${ws.userName} disconnected`);
  }
  
  async applyUpdate(characterId, operation) {
    // Apply operation to database
    // Implementation depends on database structure
    // Could use JSON path updates or specific handlers per operation type
  }
  
  async loadCharacterState(characterId) {
    // Load from database
    // Return CharacterState object
  }
  
  async verifyCharacterAccess(userId, characterId) {
    // Check if user owns character or is in same campaign
    // Return boolean
  }
  
  async verifyUpdatePermission(userId, characterId) {
    // Check if user owns character or is GM of campaign
    // Return boolean
  }
  
  async authenticateToken(token) {
    // Verify JWT or session token
    // Return user object or null
  }
}
```

### Fallback Strategy (No WebSocket Support)

For environments where WebSockets are not available:

1. **Polling**: Client polls `/api/character/{id}/state` every 3-5 seconds
2. **Version Checking**: Include `If-None-Match: {version}` header to minimize bandwidth
3. **Server Returns**: `304 Not Modified` if no changes, or `200` with new state
4. **Graceful Degradation**: Show "Limited sync mode" indicator in UI

---

## Mobile-Responsive Layout Design

### Breakpoints

- **Mobile**: 320px - 767px (portrait and landscape phones)
- **Tablet**: 768px - 1023px (portrait and landscape tablets)
- **Desktop**: 1024px+ (laptops and desktops)

### Layout Strategies

#### Mobile (≤767px)
- **Single Column**: Stacked vertical layout
- **Collapsible Sections**: Expandable panels to conserve space
- **Bottom Navigation**: Fixed bottom nav bar for quick tab switching
- **Floating Action Buttons**: Quick actions (e.g., +/- HP)
- **Full-Screen Modals**: For complex interactions (spell selection, inventory management)
- **Touch-Optimized**: 44x44px minimum touch targets
- **Swipe Gestures**: Swipe to switch tabs or dismiss panels

#### Tablet (768px-1023px)
- **Two Column**: Sidebar + main content area
- **Persistent Sidebar**: Character stats always visible
- **Tabbed Content**: Main area switches between tabs
- **Bottom Sheet Modals**: Instead of full-screen modals
- **Grid Layouts**: 2-column grids for inventory, spells

#### Desktop (≥1024px)
- **Three Column**: Sidebar + main + detail panel
- **No Tabs**: All sections visible simultaneously
- **Hover Interactions**: Tooltips, quick actions on hover
- **Drag & Drop**: Rearrange items, drag spells to cast
- **Keyboard Shortcuts**: Power user features

### Progressive Enhancement

```css
/* Mobile-first approach */
.character-sheet {
  display: flex;
  flex-direction: column;
}

.stats-panel,
.resources-panel,
.actions-panel {
  width: 100%;
  padding: 1rem;
}

/* Tablet */
@media (min-width: 768px) {
  .character-sheet {
    flex-direction: row;
  }
  
  .sidebar {
    width: 250px;
    flex-shrink: 0;
  }
  
  .main-content {
    flex: 1;
  }
}

/* Desktop */
@media (min-width: 1024px) {
  .character-sheet {
    display: grid;
    grid-template-columns: 250px 1fr 300px;
    grid-template-rows: auto 1fr;
    gap: 1rem;
  }
  
  .sidebar {
    grid-row: 1 / 3;
  }
  
  .header {
    grid-column: 2 / 4;
  }
  
  .main-content {
    grid-column: 2;
  }
  
  .detail-panel {
    grid-column: 3;
  }
}
```

### Touch Interactions

- **Tap**: Select, activate
- **Long Press**: Context menu, detailed info
- **Swipe Right**: Previous tab
- **Swipe Left**: Next tab
- **Pinch**: Zoom (for hex map, character portrait)
- **Pull to Refresh**: Reload character state

### Offline Support

- **Service Worker**: Cache character data
- **Local Storage**: Store recent state
- **Optimistic Updates**: Apply immediately, sync when online
- **Sync Indicator**: Show connection status
- **Conflict Resolution**: Prompt user when conflicts occur

---

## Performance Considerations

### Optimization Strategies

1. **Lazy Loading**: Load tabs/sections on demand
2. **Virtual Scrolling**: For long lists (spells, inventory)
3. **Debouncing**: Batch rapid updates (e.g., HP adjustments)
4. **Memoization**: Cache calculated values (modifiers, totals)
5. **Web Workers**: Offload calculations to background thread
6. **Asset Optimization**: Compress images, use SVG icons, lazy load images
7. **Code Splitting**: Separate bundles per route/feature

### Target Performance Metrics

- **First Contentful Paint**: < 1.5s
- **Time to Interactive**: < 3.5s
- **WebSocket Latency**: < 100ms for local updates
- **Update Propagation**: < 200ms to all clients
- **Bundle Size**: < 200KB (gzipped) for main app

---

## Technology Stack Recommendations

### Frontend
- **Framework**: React 18+ with TypeScript
- **State Management**: Zustand or Redux Toolkit (for complex state)
- **UI Components**: Radix UI or Headless UI (accessible, unstyled)
- **Styling**: Tailwind CSS or styled-components
- **WebSocket**: Socket.io-client or native WebSocket API
- **Forms**: React Hook Form
- **Data Fetching**: TanStack Query (React Query)

### Backend (if building new API layer)
- **Framework**: Drupal 11 (existing) with custom modules
- **WebSocket Server**: Node.js with Socket.io or ws library
  - OR Drupal WebSocket module (if available)
  - OR Mercure (server-sent events alternative)
- **Database**: MySQL (existing)
- **Caching**: Redis (for real-time state)
- **API**: RESTful + WebSocket hybrid

### Infrastructure
- **Hosting**: AWS, GCP, or DigitalOcean
- **CDN**: CloudFlare for assets
- **WebSocket**: Dedicated WebSocket server or serverless (AWS API Gateway)
- **Monitoring**: Sentry (error tracking), DataDog (performance)

---

## Security Considerations

### Authentication & Authorization

1. **JWT Tokens**: Secure API and WebSocket authentication
2. **Role-Based Access**:
   - **Owner**: Full read/write access to character
   - **GM**: Read/write access to all characters in campaign
   - **Player**: Read access to characters in same party
   - **Observer**: Read-only access (if shared publicly)
3. **Session Management**: Expire inactive sessions, refresh tokens

### Data Validation

1. **Input Validation**: Validate all user input server-side
2. **Type Checking**: Use TypeScript for compile-time safety
3. **Sanitization**: Escape user-generated content (character names, descriptions)
4. **Rate Limiting**: Prevent API abuse (100 requests/minute per user)

### WebSocket Security

1. **Authentication**: Require token on connection
2. **Authorization**: Verify permissions for each message
3. **Message Validation**: Validate message structure and content
4. **Disconnect**: Timeout inactive connections after 5 minutes
5. **DOS Protection**: Limit message rate per connection

---

## Testing Strategy

### Unit Tests
- Character state calculations (HP, AC, modifiers)
- Condition effect application
- Inventory bulk calculation
- Experience and level-up logic

### Integration Tests
- API endpoints (all CRUD operations)
- WebSocket message handling
- State synchronization between clients
- Optimistic locking and conflict resolution

### E2E Tests
- Complete character creation workflow
- Combat scenario (HP changes, condition tracking, action economy)
- Spell casting and resource consumption
- Inventory management
- Level up process

### Performance Tests
- Concurrent user connections (100+ simultaneous)
- Message throughput (1000 updates/second)
- State synchronization latency
- Database query performance

---

## Implementation Phases (Future)

**Note:** These are suggested phases for implementation, not part of this design document.

### Phase 1: Core Character View (MVP)
- Display character stats (read-only)
- Show HP, AC, saves
- Display skills and abilities
- Mobile-responsive layout

### Phase 2: Resource Management
- Update HP (with +/- buttons)
- Track hero points
- Spell slot consumption
- Action economy tracker

### Phase 3: Conditions & Effects
- Add/remove conditions
- Apply condition effects
- Duration tracking
- Visual indicators

### Phase 4: Real-Time Sync
- WebSocket integration
- Multi-client synchronization
- Optimistic updates with conflict resolution
- Connection status indicator

### Phase 5: Inventory & Spells
- Full inventory management
- Spell casting interface
- Bulk calculation
- Equipment management

### Phase 6: Character Progression
- Experience tracking
- Level up interface
- Ability score increases
- Feat selection

---

## Appendix: Example Screens

### Example 1: Combat View (Mobile)

```
┌──────────────────────────┐
│ Grok - Turn 1     [✓]    │
├──────────────────────────┤
│ HP: ██████░░ 42/50       │
│ Actions: ●●● Reaction: ⚡ │
├──────────────────────────┤
│ Quick Actions:           │
│ [⚔ Strike] [🏃 Move]     │
│ [🛡 Raise Shield]         │
├──────────────────────────┤
│ Conditions:              │
│ • Shield Raised (+2 AC)  │
└──────────────────────────┘
```

### Example 2: Spell Casting (Tablet)

```
┌────────────────────────────────────────┐
│ Cast Spell                       [✕]   │
├────────────────────────────────────────┤
│ Spell Slots: 1st ●●○ 2nd ●○○ 3rd ●●○  │
├────────────────────────────────────────┤
│ Selected: Fireball (3rd-level)         │
│                                        │
│ Actions: ●● (2 actions)                │
│ Range: 500 feet                        │
│ Area: 20-foot burst                    │
│ Saving Throw: Basic Reflex             │
│                                        │
│ Heighten to level 4? [Yes] [No]       │
│                                        │
│ [Cancel]              [Cast Spell ⚡]  │
└────────────────────────────────────────┘
```

### Example 3: Level Up (Desktop)

```
┌──────────────────────────────────────────────────┐
│ Level Up: Grok reaches Level 6!           [✕]   │
├──────────────────────────────────────────────────┤
│ ┌──────────────────┬─────────────────────────┐  │
│ │ Ability Boosts   │ Class Features           │  │
│ │ Choose 4:        │ ✓ Fighter Feat           │  │
│ │ [✓] Strength     │ ✓ Skill Increase         │  │
│ │ [✓] Dexterity    │                          │  │
│ │ [✓] Constitution │ New HP: 50 → 60          │  │
│ │ [ ] Intelligence │                          │  │
│ │ [✓] Wisdom       │                          │  │
│ │ [ ] Charisma     │                          │  │
│ └──────────────────┴─────────────────────────┘  │
│ ┌─────────────────────────────────────────────┐ │
│ │ Select Fighter Feat (Level 6):              │ │
│ │ ( ) Furious Focus                           │ │
│ │ ( ) Knockdown                               │ │
│ │ (●) Powerful Shove                          │ │
│ └─────────────────────────────────────────────┘ │
│                                                  │
│ [Cancel]                   [Complete Level Up]  │
└──────────────────────────────────────────────────┘
```

---

## Conclusion

This design document provides a comprehensive blueprint for the Enhanced Character Sheet system. It covers:

✅ **UI/UX Wireframes** - Desktop, mobile, and tablet layouts  
✅ **Data Structures** - Complete TypeScript interfaces for character state  
✅ **Service Pseudocode** - CharacterStateService with all major operations  
✅ **API Endpoints** - RESTful API design with request/response examples  
✅ **Real-Time Sync** - WebSocket architecture and protocol  
✅ **Mobile-Responsive** - Adaptive layouts with progressive enhancement  
✅ **Security** - Authentication, authorization, and validation strategies  
✅ **Performance** - Optimization techniques and target metrics  
✅ **Technology Stack** - Recommended frameworks and tools  

**Next Steps** (for implementation PRs):
1. Review and approve this design
2. Create component library and UI mockups
3. Implement data layer and API endpoints
4. Build frontend components
5. Integrate WebSocket real-time sync
6. Test and iterate

---

**Document Metadata:**
- **Created**: 2026-02-12
- **Version**: 1.0
- **Status**: Design Complete - Awaiting Review
- **Related Issues**: Character Creation (#1), Combat Encounters (#2)
