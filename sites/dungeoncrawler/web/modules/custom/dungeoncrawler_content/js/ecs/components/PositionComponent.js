/**
 * @file
 * PositionComponent - hex grid position data.
 * 
 * Uses axial coordinate system for hexagonal grids.
 * Reference: https://www.redblobgames.com/grids/hexagons/
 */

import { Component } from '../Component.js';

/**
 * Hexagonal direction constants (0-5 for pointy-top hexes).
 */
export const HexDirection = {
  EAST: 0,
  NORTHEAST: 1,
  NORTHWEST: 2,
  WEST: 3,
  SOUTHWEST: 4,
  SOUTHEAST: 5
};

export class PositionComponent extends Component {
  /**
   * Create a position component.
   * @param {number} q - Axial Q coordinate
   * @param {number} r - Axial R coordinate
   * @param {number} elevation - Z-height (default 0)
   * @param {number} facing - Direction facing 0-5 (default 0)
   * @throws {TypeError} If q or r are not finite numbers
   * @throws {RangeError} If facing is not between 0 and 5
   */
  constructor(q = 0, r = 0, elevation = 0, facing = 0) {
    super();
    
    // Validate coordinates
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      throw new TypeError('Coordinates q and r must be finite numbers');
    }
    if (!Number.isFinite(elevation)) {
      throw new TypeError('Elevation must be a finite number');
    }
    if (!Number.isInteger(facing) || facing < 0 || facing > 5) {
      throw new RangeError('Facing must be an integer between 0 and 5');
    }
    
    this.q = q;
    this.r = r;
    this.elevation = elevation;
    this.facing = facing;
  }

  /**
   * Get hex coordinates as object.
   * @returns {{q: number, r: number}} Hex coordinates
   */
  getHex() {
    return { q: this.q, r: this.r };
  }

  /**
   * Set hex coordinates.
   * @param {number} q - Axial Q coordinate
   * @param {number} r - Axial R coordinate
   * @throws {TypeError} If q or r are not finite numbers
   */
  setHex(q, r) {
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      throw new TypeError('Coordinates q and r must be finite numbers');
    }
    this.q = q;
    this.r = r;
  }

  /**
   * Get cube coordinates (for distance calculations).
   * @returns {{q: number, r: number, s: number}} Cube coordinates
   */
  getCube() {
    return {
      q: this.q,
      r: this.r,
      s: -this.q - this.r
    };
  }

  /**
   * Calculate distance to another position in hexes.
   * Uses cube coordinate distance formula.
   * @param {PositionComponent} other - Other position
   * @returns {number} Distance in hexes
   * @throws {TypeError} If other is not a PositionComponent
   */
  distanceTo(other) {
    if (!other || typeof other.q !== 'number' || typeof other.r !== 'number') {
      throw new TypeError('other must be a valid PositionComponent with q and r coordinates');
    }
    return (
      Math.abs(this.q - other.q) +
      Math.abs(this.q + this.r - other.q - other.r) +
      Math.abs(this.r - other.r)
    ) / 2;
  }

  /**
   * Get hex key for map lookups.
   * @returns {string} Key in format "q_r"
   */
  getKey() {
    return `${this.q}_${this.r}`;
  }

  /**
   * Check if position equals another.
   * Note: This comparison excludes facing direction - two positions
   * with different facing are considered equal if they occupy the same hex.
   * @param {PositionComponent} other - Other position
   * @returns {boolean} True if same hex position and elevation
   */
  equals(other) {
    if (!other || typeof other.q !== 'number' || typeof other.r !== 'number') {
      return false;
    }
    return this.q === other.q && this.r === other.r && this.elevation === other.elevation;
  }

  /**
   * Serialize component to JSON.
   * @returns {Object} Serialized component data
   */
  toJSON() {
    return {
      type: this.constructor.name,
      q: this.q,
      r: this.r,
      elevation: this.elevation,
      facing: this.facing
    };
  }

  /**
   * Deserialize component from JSON.
   * @param {Object} data - Serialized component data
   * @returns {PositionComponent} New component instance
   */
  static fromJSON(data) {
    return new PositionComponent(
      data.q,
      data.r,
      data.elevation,
      data.facing
    );
  }
}
