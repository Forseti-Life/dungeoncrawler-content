/**
 * @file MovementSystem.js
 * System for pathfinding and entity movement.
 * 
 * Coordinate System:
 * This system uses axial hex coordinates (q, r) following the Red Blob Games
 * standard for hexagonal grids (https://www.redblobgames.com/grids/hexagons/).
 * 
 * Schema Conformance:
 * - PositionComponent: Uses q, r coordinates
 * - entity_instance.schema.json: placement.hex uses q, r
 * - Database tables:
 *   - dc_campaign_characters: position_q, position_r (hot columns)
 *   - combat_participants: position_q, position_r (as of update 10009)
 * 
 * All movement calculations assume flat-top hex orientation with 6 directions.
 */

import { System } from '../System.js';

/**
 * Priority queue implementation for A* pathfinding.
 */
class PriorityQueue {
  constructor() {
    this.items = [];
  }
  
  enqueue(item, priority) {
    this.items.push({ item, priority });
    this.items.sort((a, b) => a.priority - b.priority);
  }
  
  dequeue() {
    return this.items.shift()?.item;
  }
  
  isEmpty() {
    return this.items.length === 0;
  }
}

/**
 * MovementSystem
 * 
 * Handles all movement-related logic including:
 * - Pathfinding using A* algorithm
 * - Movement range calculation
 * - Movement execution
 * - Terrain cost evaluation
 */
export class MovementSystem extends System {
  constructor(entityManager) {
    super(entityManager);
    this.priority = 50; // Run before render system (100)
    
    // Hex neighbors (flat-top orientation)
    this.hexDirections = [
      { q: 1, r: 0 },   // East
      { q: 1, r: -1 },  // Northeast
      { q: 0, r: -1 },  // Northwest
      { q: -1, r: 0 },  // West
      { q: -1, r: 1 },  // Southwest
      { q: 0, r: 1 }    // Southeast
    ];
    
    // Terrain cost multipliers (can be expanded)
    this.terrainCosts = {
      'normal': 1,
      'difficult': 2,
      'greater_difficult': 4,
      'hazardous': 2,
      'impassable': Infinity
    };
    
    // Movement range cache
    this.movementRangeCache = new Map();
  }
  
  /**
   * Initialize system.
   */
  init() {
    console.log('MovementSystem initialized');
  }
  
  /**
   * Update system (called each frame).
   * @param {number} deltaTime - Time elapsed since last update (ms)
   */
  update(deltaTime) {
    // MovementSystem is primarily event-driven
    // Could add animation updates here if needed
  }
  
  /**
   * Get hex neighbors.
   * @param {number} q - Q coordinate
   * @param {number} r - R coordinate
   * @returns {Array} - Array of {q, r} neighbors
   */
  getNeighbors(q, r) {
    return this.hexDirections.map(dir => ({
      q: q + dir.q,
      r: r + dir.r
    }));
  }
  
  /**
   * Calculate hex distance (Manhattan distance in axial coordinates).
   * @param {number} q1 - Start Q
   * @param {number} r1 - Start R
   * @param {number} q2 - End Q
   * @param {number} r2 - End R
   * @returns {number}
   */
  hexDistance(q1, r1, q2, r2) {
    const s1 = -q1 - r1;
    const s2 = -q2 - r2;
    return (Math.abs(q1 - q2) + Math.abs(r1 - r2) + Math.abs(s1 - s2)) / 2;
  }
  
  /**
   * Get terrain cost at a hex position.
   * @param {number} q - Q coordinate
   * @param {number} r - R coordinate
   * @returns {number} - Cost multiplier
   */
  getTerrainCost(q, r) {
    // Check for entities that block movement
    const entitiesAtPos = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
    for (const entity of entitiesAtPos) {
      const pos = entity.getComponent('PositionComponent');
      const identity = entity.getComponent('IdentityComponent');
      
      if (pos.q === q && pos.r === r && identity.blocksMovement()) {
        return Infinity; // Impassable
      }
    }
    
    // Default terrain cost (can be expanded with terrain components)
    return 1;
  }
  
  /**
   * Calculate movement range using breadth-first search.
   * @param {Entity} entity - Entity to calculate range for
   * @param {number} maxDistance - Maximum movement distance in hexes
   * @returns {Set} - Set of hex keys "q_r" that are reachable
   */
  calculateMovementRange(entity, maxDistance = null) {
    const pos = entity.getComponent('PositionComponent');
    const movement = entity.getComponent('MovementComponent');
    
    if (!pos || !movement) {
      return new Set();
    }
    
    // Use provided max distance or calculate from movement budget
    const maxHexes = maxDistance !== null ? maxDistance : movement.getMaxHexes();
    
    // Check cache
    const cacheKey = `${entity.id}_${pos.q}_${pos.r}_${maxHexes}`;
    if (this.movementRangeCache.has(cacheKey)) {
      return this.movementRangeCache.get(cacheKey);
    }
    
    const reachable = new Set();
    const visited = new Map(); // hex key -> cost to reach
    const queue = [];
    
    const startKey = `${pos.q}_${pos.r}`;
    queue.push({ q: pos.q, r: pos.r, cost: 0 });
    visited.set(startKey, 0);
    reachable.add(startKey);
    
    while (queue.length > 0) {
      const current = queue.shift();
      const currentCost = visited.get(`${current.q}_${current.r}`);
      
      // Get neighbors
      const neighbors = this.getNeighbors(current.q, current.r);
      
      for (const neighbor of neighbors) {
        const neighborKey = `${neighbor.q}_${neighbor.r}`;
        const terrainCost = this.getTerrainCost(neighbor.q, neighbor.r);
        
        // Skip impassable terrain
        if (terrainCost === Infinity) {
          continue;
        }
        
        const newCost = currentCost + terrainCost;
        
        // Skip if we've exceeded movement budget
        if (newCost > maxHexes) {
          continue;
        }
        
        // Skip if we've already found a better path to this hex
        if (visited.has(neighborKey) && visited.get(neighborKey) <= newCost) {
          continue;
        }
        
        visited.set(neighborKey, newCost);
        reachable.add(neighborKey);
        queue.push({ q: neighbor.q, r: neighbor.r, cost: newCost });
      }
    }
    
    // Cache result
    this.movementRangeCache.set(cacheKey, reachable);
    
    return reachable;
  }
  
  /**
   * Find path using A* algorithm.
   * @param {number} startQ - Start Q coordinate
   * @param {number} startR - Start R coordinate
   * @param {number} endQ - End Q coordinate
   * @param {number} endR - End R coordinate
   * @param {number} maxCost - Maximum movement cost (in hexes)
   * @returns {Array|null} - Array of {q, r} coordinates or null if no path
   */
  findPath(startQ, startR, endQ, endR, maxCost = Infinity) {
    const openSet = new PriorityQueue();
    const cameFrom = new Map();
    const gScore = new Map(); // Cost from start
    const fScore = new Map(); // Estimated total cost
    
    const startKey = `${startQ}_${startR}`;
    const endKey = `${endQ}_${endR}`;
    
    gScore.set(startKey, 0);
    fScore.set(startKey, this.hexDistance(startQ, startR, endQ, endR));
    openSet.enqueue(startKey, fScore.get(startKey));
    
    while (!openSet.isEmpty()) {
      const currentKey = openSet.dequeue();
      
      // Reached destination
      if (currentKey === endKey) {
        return this.reconstructPath(cameFrom, currentKey);
      }
      
      const [currentQ, currentR] = currentKey.split('_').map(Number);
      const neighbors = this.getNeighbors(currentQ, currentR);
      
      for (const neighbor of neighbors) {
        const neighborKey = `${neighbor.q}_${neighbor.r}`;
        const terrainCost = this.getTerrainCost(neighbor.q, neighbor.r);
        
        // Skip impassable terrain
        if (terrainCost === Infinity) {
          continue;
        }
        
        const tentativeGScore = gScore.get(currentKey) + terrainCost;
        
        // Skip if exceeds movement budget
        if (tentativeGScore > maxCost) {
          continue;
        }
        
        // Skip if not better than existing path
        if (gScore.has(neighborKey) && tentativeGScore >= gScore.get(neighborKey)) {
          continue;
        }
        
        // This path is the best so far
        cameFrom.set(neighborKey, currentKey);
        gScore.set(neighborKey, tentativeGScore);
        
        const h = this.hexDistance(neighbor.q, neighbor.r, endQ, endR);
        fScore.set(neighborKey, tentativeGScore + h);
        openSet.enqueue(neighborKey, fScore.get(neighborKey));
      }
    }
    
    // No path found
    return null;
  }
  
  /**
   * Reconstruct path from A* search.
   * @param {Map} cameFrom - Map of hex keys showing path
   * @param {string} currentKey - End position key
   * @returns {Array} - Array of {q, r} coordinates
   */
  reconstructPath(cameFrom, currentKey) {
    const path = [];
    let current = currentKey;
    
    while (current) {
      const [q, r] = current.split('_').map(Number);
      path.unshift({ q, r });
      current = cameFrom.get(current);
    }
    
    return path;
  }
  
  /**
   * Move entity to target position.
   * @param {Entity} entity - Entity to move
   * @param {number} targetQ - Target Q coordinate
   * @param {number} targetR - Target R coordinate
   * @returns {boolean} - True if movement succeeded
   */
  moveEntity(entity, targetQ, targetR) {
    const pos = entity.getComponent('PositionComponent');
    const movement = entity.getComponent('MovementComponent');
    
    if (!pos || !movement) {
      console.warn('Entity missing PositionComponent or MovementComponent');
      return false;
    }
    
    if (!movement.canMove) {
      console.warn('Entity cannot move this turn');
      return false;
    }
    
    // Find path
    const maxHexes = movement.getMaxHexes();
    const path = this.findPath(pos.q, pos.r, targetQ, targetR, maxHexes);
    
    if (!path || path.length === 0) {
      console.warn('No valid path to destination');
      return false;
    }
    
    // Calculate movement cost (subtract 1 because first hex is current position)
    const movementCost = (path.length - 1) * movement.hexMovementCost;
    
    if (!movement.consumeMovement(movementCost)) {
      console.warn('Insufficient movement to reach destination');
      return false;
    }
    
    // Update position
    const destination = path[path.length - 1];
    pos.setHex(destination.q, destination.r);
    
    // Store path for potential animation
    movement.path = path;
    
    // Invalidate movement range cache
    this.invalidateCache(entity);
    
    console.log(`Entity ${entity.id} moved to (${targetQ}, ${targetR})`);
    return true;
  }
  
  /**
   * Invalidate movement range cache for entity.
   * @param {Entity} entity - Entity to invalidate cache for
   */
  invalidateCache(entity) {
    const keysToDelete = [];
    for (const key of this.movementRangeCache.keys()) {
      if (key.startsWith(`${entity.id}_`)) {
        keysToDelete.push(key);
      }
    }
    keysToDelete.forEach(key => this.movementRangeCache.delete(key));
  }
  
  /**
   * Clear all movement range caches.
   */
  clearCache() {
    this.movementRangeCache.clear();
  }
  
  /**
   * Cleanup system.
   */
  destroy() {
    this.clearCache();
    console.log('MovementSystem destroyed');
  }
}
