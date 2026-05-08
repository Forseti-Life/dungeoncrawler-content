/**
 * @file
 * CharacterStateService - Client-side character state management.
 * 
 * Based on the design document:
 * docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md
 * 
 * SCHEMA CONFORMANCE:
 * This service operates on the **runtime API format** (camelCase) provided by the PHP
 * CharacterStateService. It does NOT need to handle database schema translation or 
 * hot-column references - that layer is handled by the PHP service.
 * 
 * Three-layer schema architecture:
 * 1. TypeScript (this file): camelCase runtime format for client consumption
 * 2. JSON Schema: snake_case storage format in character_data column
 * 3. Database: Hot columns (name, level, ancestry, class) for query performance
 * 
 * The PHP service translates between these layers. This TypeScript service only needs
 * to conform to the CharacterState interface defined in character-state.types.ts.
 * 
 * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-service-pseudocode
 * @see js/SCHEMA_ALIGNMENT.md
 */

import { CharacterState, UpdateOperation, Condition, Item, RemoteUpdate } from './types/character-state.types';

/**
 * Event callback type for state change listeners.
 */
export type EventCallback<T = unknown> = (data: T) => void;

/**
 * State change event data.
 */
export interface StateChangeEvent {
  characterId: string;
  version: number;
  timestamp: number;
}

/**
 * Sync error event data.
 */
export interface SyncErrorEvent {
  error: Error;
  operations: UpdateOperation[];
  retryable: boolean;
}

/**
 * Level up available event data.
 */
export interface LevelUpEvent {
  characterId: string;
  currentLevel: number;
  currentXp: number;
  xpRequired: number;
}

/**
 * CharacterStateService
 * 
 * Manages character state, handles updates, and synchronizes with backend.
 * Implements optimistic updates with rollback on failure.
 */
export class CharacterStateService {
  private characterState: CharacterState | null = null;
  private websocket: WebSocket | null = null;
  private readonly updateQueue: UpdateOperation[] = [];
  private readonly listeners: Map<string, EventCallback[]> = new Map();
  private updateQueueInterval: number | null = null;

  /**
   * Initialize the service: load character and establish WebSocket.
   * 
   * Fetches the character state from the PHP API endpoint which returns data in the
   * camelCase format matching CharacterState interface. The PHP service handles:
   * - Reading from dc_campaign_characters table (hot columns + character_data JSON)
   * - Translating snake_case storage format to camelCase runtime format
   * - Merging library defaults with campaign-specific state overrides
   * 
   * @param characterId - The ID of the character to load
   * @throws {Error} If characterId is invalid or initialization fails
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#initialize-the-service-load-character-and-establish-websocket
   * @see js/SCHEMA_ALIGNMENT.md#php-service-layer-characterstatephp
   */
  async initialize(characterId: string): Promise<void> {
    if (!characterId || typeof characterId !== 'string') {
      throw new Error('Invalid character ID');
    }
    
    // TODO: Implement
    // - Load initial state from API endpoint (e.g., /api/character/load/{id})
    // - API returns CharacterState in camelCase format (already translated by PHP)
    // - Establish WebSocket connection
    // - Start update queue processor
    console.log('TODO: Initialize CharacterStateService for character', characterId);
  }

  /**
   * Establish WebSocket connection for real-time updates.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#establish-websocket-connection-for-real-time-updates
   */
  private connectWebSocket(characterId: string): void {
    // TODO: Implement
    // - Create WebSocket connection
    // - Handle onopen, onmessage, onerror, onclose
    // - Implement reconnection logic
    console.log('TODO: Connect WebSocket for character', characterId);
  }

  /**
   * Get current character state (immutable copy).
   * 
   * Returns a deep copy of the current character state to prevent external mutations.
   * The state structure follows the CharacterState interface with camelCase naming,
   * matching the format provided by the PHP API.
   * 
   * Hot-column fields (name, level, ancestry, class) are nested within basicInfo
   * in the runtime format, even though they are denormalized columns in the database.
   * The PHP service handles this structural translation.
   * 
   * @returns Immutable copy of character state, or null if not initialized
   * @throws {Error} If state cannot be serialized (e.g., circular references)
   * @see js/SCHEMA_ALIGNMENT.md#hot-column-references
   */
  getState(): Readonly<CharacterState> | null {
    if (!this.characterState) return null;
    
    try {
      // Use structuredClone if available (modern browsers), fallback to JSON method
      if (typeof structuredClone !== 'undefined') {
        return structuredClone(this.characterState);
      }
      return JSON.parse(JSON.stringify(this.characterState));
    } catch (error) {
      throw new Error(`Failed to clone character state: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
  }

  /**
   * Update hit points.
   * 
   * Updates HP with optimistic UI update and queues the change for server sync.
   * The PHP service will persist changes to both:
   * - Hot columns: hp_current, hp_max (for fast queries)
   * - JSON payload: character_data.hit_points (for complete state)
   * 
   * @param delta - Amount to change HP by (positive for heal, negative for damage)
   * @param temporary - Whether to modify temporary HP instead of current HP
   * @throws {Error} If character state is not initialized
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#update-hit-points
   * @see js/SCHEMA_ALIGNMENT.md#unified-vs-hot-column-structures
   */
  async updateHitPoints(delta: number, temporary: boolean = false): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    if (!Number.isFinite(delta)) {
      throw new Error(`Invalid delta value: ${delta}`);
    }
    
    // TODO: Implement
    // - Optimistic update to characterState.resources.hitPoints
    // - Bound checking (0 <= current <= max)
    // - Queue for server sync (PHP will update hot columns)
    // - Emit state-changed event
    console.log('TODO: Update HP', { delta, temporary });
  }

  /**
   * Add condition to character.
   * 
   * @param condition - The condition to add
   * @throws {Error} If character state is not initialized or condition is invalid
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#add-condition-to-character
   */
  async addCondition(condition: Condition): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    if (!condition.id || !condition.name) {
      throw new Error('Invalid condition: id and name are required');
    }
    
    // TODO: Implement
    // - Add condition to characterState.conditions
    // - Apply condition effects
    // - Queue for server sync
    // - Emit state-changed event
    console.log('TODO: Add condition', condition);
  }

  /**
   * Remove condition from character.
   * 
   * @param conditionId - The ID of the condition to remove
   * @throws {Error} If character state is not initialized or condition ID is invalid
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#remove-condition-from-character
   */
  async removeCondition(conditionId: string): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    if (!conditionId || typeof conditionId !== 'string') {
      throw new Error('Invalid condition ID');
    }
    
    // TODO: Implement
    // - Remove condition from characterState.conditions
    // - Remove condition effects
    // - Queue for server sync
    // - Emit state-changed event
    console.log('TODO: Remove condition', conditionId);
  }

  /**
   * Cast a spell (consume slot or focus point).
   * 
   * @param spellId - The ID of the spell to cast
   * @param level - The spell level (1-10 for standard spells, 0 for cantrips)
   * @param isFocusSpell - Whether this is a focus spell
   * @throws {Error} If character state is not initialized, spell ID is invalid, or no resources available
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#cast-a-spell-consume-slot-or-focus-point
   */
  async castSpell(spellId: string, level: number, isFocusSpell: boolean = false): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    if (!spellId || typeof spellId !== 'string') {
      throw new Error('Invalid spell ID');
    }
    
    if (!Number.isInteger(level) || level < 0 || level > 10) {
      throw new Error('Spell level must be an integer between 0 and 10');
    }
    
    // TODO: Implement
    // - Check available slots/focus points
    // - Decrement appropriate resource
    // - Queue for server sync
    // - Emit state-changed event
    // - Throw error if no resources available
    console.log('TODO: Cast spell', { spellId, level, isFocusSpell });
  }

  /**
   * Use an action (track three-action economy).
   * 
   * @param actionCost - Number of actions to consume (1-3)
   * @throws {Error} If character state is not initialized, actionCost is invalid, or insufficient actions remaining
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#use-an-action-track-three-action-economy
   */
  async useAction(actionCost: number = 1): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    if (!Number.isInteger(actionCost) || actionCost < 1 || actionCost > 3) {
      throw new Error('Action cost must be an integer between 1 and 3');
    }
    
    // TODO: Implement
    // - Check actionsRemaining >= actionCost
    // - Decrement actionsRemaining
    // - Queue for server sync
    // - Emit state-changed event
    console.log('TODO: Use action', { actionCost });
  }

  /**
   * Use reaction.
   * 
   * @throws {Error} If character state is not initialized or no reaction available
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#use-reaction
   */
  async useReaction(): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    // TODO: Implement
    // - Check reactionAvailable is true
    // - Set reactionAvailable to false
    // - Queue for server sync
    // - Emit state-changed event
    console.log('TODO: Use reaction');
  }

  /**
   * Start new turn (reset actions and reaction).
   * 
   * @throws {Error} If character state is not initialized
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#start-new-turn-reset-actions-and-reaction
   */
  async startNewTurn(): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    // TODO: Implement
    // - Reset actionsRemaining to 3
    // - Reset reactionAvailable to true
    // - Update condition durations
    // - Queue for server sync
    // - Emit state-changed event
    console.log('TODO: Start new turn');
  }

  /**
   * Update inventory (add, remove, equip items).
   * 
   * @param action - The inventory action to perform
   * @param item - The item to operate on
   * @throws {Error} If character state is not initialized, action is invalid, or item is invalid
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#update-inventory-add-remove-equip-items
   */
  async updateInventory(action: 'add' | 'remove' | 'equip' | 'unequip', item: Item): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    if (!['add', 'remove', 'equip', 'unequip'].includes(action)) {
      throw new Error(`Invalid inventory action: ${action}`);
    }
    
    if (!item || !item.id) {
      throw new Error('Invalid item: id is required');
    }
    
    // TODO: Implement
    // - Handle add/remove/equip/unequip
    // - Recalculate bulk
    // - Update encumbrance
    // - Queue for server sync
    // - Emit state-changed event
    console.log('TODO: Update inventory', { action, item });
  }

  /**
   * Gain experience points.
   * 
   * @param xp - Amount of experience points to gain (must be positive)
   * @throws {Error} If character state is not initialized or XP value is invalid
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#gain-experience-points
   */
  async gainExperience(xp: number): Promise<void> {
    if (!this.characterState) {
      throw new Error('Character state not initialized');
    }
    
    if (!Number.isFinite(xp) || xp < 0) {
      throw new Error('Experience points must be a non-negative number');
    }
    
    // TODO: Implement
    // - Add XP to character
    // - Check if level up available
    // - Emit level-up-available event if applicable
    // - Queue for server sync
    // - Emit state-changed event
    console.log('TODO: Gain XP', { xp });
  }

  /**
   * Queue an update for server synchronization.
   */
  private queueUpdate(operation: UpdateOperation): void {
    // TODO: Implement
    // - Add operation to updateQueue
    console.log('TODO: Queue update', operation);
  }

  /**
   * Process queued updates (batch send to server).
   * 
   * Sends batched update operations to the PHP API endpoint which will:
   * 1. Apply operations to character state
   * 2. Update hot columns (name, level, hp_current, hp_max, etc.)
   * 3. Persist full JSON payload to character_data column
   * 4. Return updated state in camelCase format
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#process-queued-updates-batch-send-to-server
   * @see js/SCHEMA_ALIGNMENT.md#synchronization
   */
  private async processUpdateQueue(): Promise<void> {
    // TODO: Implement
    // - Send batched operations to /api/character/{id}/update
    // - PHP service updates both hot columns and JSON
    // - Update version on success
    // - Re-queue on failure
    // - Emit sync-error on failure
    console.log('TODO: Process update queue');
  }

  /**
   * Handle updates received from WebSocket.
   * 
   * @param update - Remote update from the server
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#handle-updates-received-from-websocket
   */
  private handleRemoteUpdate(update: RemoteUpdate): void {
    if (!update || typeof update !== 'object') {
      console.error('Invalid remote update received:', update);
      return;
    }
    
    // TODO: Implement
    // - Check version (ignore if <= current)
    // - Apply update to characterState
    // - Update version
    // - Emit state-changed event
    // - Emit remote-update event
    console.log('TODO: Handle remote update', update);
  }

  /**
   * Apply condition effects to character state.
   */
  private applyConditionEffects(condition: Condition): void {
    // TODO: Implement
    // - Add modifiers from condition.effects
    console.log('TODO: Apply condition effects', condition);
  }

  /**
   * Remove condition effects from character state.
   */
  private removeConditionEffects(condition: Condition): void {
    // TODO: Implement
    // - Remove modifiers from condition.effects
    console.log('TODO: Remove condition effects', condition);
  }

  /**
   * Update condition durations (called at start of turn).
   */
  private updateConditionDurations(): void {
    // TODO: Implement
    // - Decrement round-based durations
    // - Remove expired conditions
    console.log('TODO: Update condition durations');
  }

  /**
   * Recalculate total bulk and encumbrance.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#recalculate-total-bulk-and-encumbrance
   */
  private recalculateBulk(): void {
    // TODO: Implement
    // - Sum worn item bulk
    // - Sum carried item bulk
    // - Calculate encumbrance based on STR
    console.log('TODO: Recalculate bulk');
  }

  /**
   * Check if character has enough XP to level up.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#check-if-character-has-enough-xp-to-level-up
   */
  private isLevelUpAvailable(): boolean {
    // TODO: Implement
    // - Calculate XP needed for next level
    // - Compare with current XP
    return false;
  }

  /**
   * Event emitter: Register listener for specific event.
   * 
   * @param event - Event name to listen to
   * @param callback - Callback function to invoke when event is emitted
   * @returns Unsubscribe function to remove the listener
   * 
   * Note: If the same callback is registered multiple times, each registration
   * will receive a separate unsubscribe function. Each call to unsubscribe
   * will remove only one instance of the callback (the first occurrence).
   */
  on<T = unknown>(event: string, callback: EventCallback<T>): () => void {
    if (!event || typeof event !== 'string') {
      throw new Error('Event name must be a non-empty string');
    }
    
    if (typeof callback !== 'function') {
      throw new Error('Callback must be a function');
    }
    
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event)!.push(callback);
    
    // Return unsubscribe function that removes only the first occurrence of this callback
    return () => {
      const callbacks = this.listeners.get(event);
      if (callbacks) {
        const index = callbacks.indexOf(callback);
        if (index !== -1) {
          callbacks.splice(index, 1);
        }
      }
    };
  }

  /**
   * Event emitter: Emit event to all registered listeners.
   * 
   * @param event - Event name to emit
   * @param data - Data to pass to event listeners
   */
  private emit<T = unknown>(event: string, data: T): void {
    const callbacks = this.listeners.get(event) || [];
    callbacks.forEach(callback => {
      try {
        callback(data);
      } catch (error) {
        console.error(`Error in event listener for "${event}":`, error);
      }
    });
  }

  /**
   * Cleanup: close WebSocket and save state.
   * Safe to call multiple times.
   * 
   * @throws {Error} If cleanup fails
   */
  async destroy(): Promise<void> {
    try {
      // TODO: Implement
      // - Close WebSocket
      // - Flush remaining updates
      // - Clear interval
      // - Clear all listeners
      console.log('TODO: Destroy CharacterStateService');
      
      // Clear listeners to prevent memory leaks
      this.listeners.clear();
    } catch (error) {
      throw new Error(`Failed to destroy service: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
  }
}
