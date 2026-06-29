/**
 * H3 Geospatial Service for Forseti Mobile Application
 *
 * Implements ultra-precise location tracking using H3 hexagonal indexing
 * at Level 13 resolution (44m²) as specified in the technical documentation.
 */

import * as h3 from 'h3-js';

// H3 Resolution configuration based on TECHNICAL_SPECIFICATION.md
export const H3_RESOLUTIONS = {
  CITY_WIDE: 5, // 251.1 km² - City-wide statistics
  NEIGHBORHOOD: 8, // 0.7 km² - Neighborhood context
  BLOCK: 10, // 15,047 m² - Block awareness
  USER_TRACKING: 13, // 44 m² - User tracking (primary resolution)
};

// Update frequencies for different resolutions
export const UPDATE_FREQUENCIES = {
  [H3_RESOLUTIONS.CITY_WIDE]: 24 * 60 * 60 * 1000, // Daily
  [H3_RESOLUTIONS.NEIGHBORHOOD]: 60 * 60 * 1000, // Hourly
  [H3_RESOLUTIONS.BLOCK]: 15 * 60 * 1000, // Every 15 minutes
  [H3_RESOLUTIONS.USER_TRACKING]: 0, // Real-time
};

/**
 * Core H3 Location Processing Service
 */
export class H3LocationService {
  constructor() {
    this.currentH3Index = null;
    this.previousH3Index = null;
    this.locationHistory = [];
  }

  /**
   * Convert GPS coordinates to H3 index
   * @param {number} lat Latitude coordinate
   * @param {number} lng Longitude coordinate
   * @param {number} resolution H3 resolution level (default: 13 for user tracking)
   * @returns {string} H3 index string
   */
  convertToH3(lat, lng, resolution = H3_RESOLUTIONS.USER_TRACKING) {
    try {
      if (!this.isValidCoordinate(lat, lng)) {
        throw new Error(`Invalid coordinates: lat=${lat}, lng=${lng}`);
      }

      const h3Index = h3.latLngToCell(lat, lng, resolution);

      // Log the conversion for debugging
      console.log(
        `🗺️ H3 Conversion: [${lat.toFixed(6)}, ${lng.toFixed(6)}] → ${h3Index} (res: ${resolution})`
      );

      return h3Index;
    } catch (error) {
      console.error('H3 Conversion Error:', error);
      throw error;
    }
  }

  /**
   * Convert H3 index back to GPS coordinates
   * @param {string} h3Index H3 hexagon index
   * @returns {object} {lat, lng} coordinates
   */
  convertFromH3(h3Index) {
    try {
      const [lat, lng] = h3.cellToLatLng(h3Index);
      return { lat, lng };
    } catch (error) {
      console.error('H3 to GPS Conversion Error:', error);
      throw error;
    }
  }

  /**
   * Get surrounding hexagons for context awareness
   * @param {string} h3Index Center hexagon
   * @param {number} ringSize Number of rings around center (default: 1)
   * @returns {Array} Array of neighboring H3 indexes
   */
  getNeighbors(h3Index, ringSize = 1) {
    try {
      let neighbors = [];

      for (let ring = 1; ring <= ringSize; ring++) {
        const ringNeighbors = h3.gridRingUnsafe(h3Index, ring);
        neighbors = neighbors.concat(ringNeighbors);
      }

      // Include the center hexagon
      neighbors.push(h3Index);

      console.log(`🔍 Found ${neighbors.length} neighbors for ${h3Index} (${ringSize} rings)`);
      return neighbors;
    } catch (error) {
      console.error('H3 Neighbors Error:', error);
      return [h3Index]; // Return at least the center hexagon
    }
  }

  /**
   * Calculate distance between two H3 hexagons
   * @param {string} h3Index1 First hexagon
   * @param {string} h3Index2 Second hexagon
   * @returns {number} Distance in number of hexagons
   */
  h3Distance(h3Index1, h3Index2) {
    try {
      return h3.gridDistance(h3Index1, h3Index2);
    } catch (error) {
      console.error('H3 Distance Calculation Error:', error);
      return 0;
    }
  }

  /**
   * Check if user has moved to a new hexagon (core movement detection)
   * @param {number} lat Current latitude
   * @param {number} lng Current longitude
   * @returns {object} Movement detection result
   */
  checkLocationChange(lat, lng) {
    const newH3Index = this.convertToH3(lat, lng);
    const hasChanged = newH3Index !== this.currentH3Index;
    const distance = this.currentH3Index ? this.h3Distance(this.currentH3Index, newH3Index) : 0;

    // Update tracking
    this.previousH3Index = this.currentH3Index;
    this.currentH3Index = newH3Index;

    // Add to history
    this.locationHistory.push({
      h3Index: newH3Index,
      timestamp: Date.now(),
    });

    // Keep only last 100 location points
    if (this.locationHistory.length > 100) {
      this.locationHistory = this.locationHistory.slice(-100);
    }

    const result = {
      hasChanged,
      currentH3: newH3Index,
      previousH3: this.previousH3Index,
      distance,
      shouldTriggerUpdate: hasChanged, // Trigger API call when hexagon changes
    };

    if (hasChanged) {
      console.log(
        `📍 Location Change Detected: ${this.previousH3Index} → ${newH3Index} (distance: ${distance})`
      );
    }

    return result;
  }

  /**
   * Get hexagon center coordinates for visualization
   * @param {string} h3Index H3 hexagon index
   * @returns {object} Center coordinates
   */
  getHexagonCenter(h3Index) {
    return this.convertFromH3(h3Index);
  }

  /**
   * Get hexagon boundary points for map visualization
   * @param {string} h3Index H3 hexagon index
   * @returns {Array} Array of boundary coordinates
   */
  getHexagonBoundary(h3Index) {
    try {
      const boundary = h3.cellToBoundary(h3Index);
      return boundary.map(([lat, lng]) => ({ lat, lng }));
    } catch (error) {
      console.error('H3 Boundary Error:', error);
      return [];
    }
  }

  /**
   * Get current user's H3 index
   */
  getCurrentH3Index() {
    return this.currentH3Index;
  }

  /**
   * Get location history for analysis
   */
  getLocationHistory() {
    return this.locationHistory;
  }

  /**
   * Calculate hexagon area in square meters
   * @param {number} resolution H3 resolution level
   * @returns {number} Area in square meters
   */
  getHexagonArea(resolution) {
    // Create a sample H3 index at this resolution to get area
    const sampleH3 = h3.latLngToCell(0, 0, resolution);
    return h3.cellArea(sampleH3, h3.UNITS.m2);
  }

  /**
   * Validate GPS coordinates
   * @param {number} lat Latitude
   * @param {number} lng Longitude
   * @returns {boolean} True if coordinates are valid
   */
  isValidCoordinate(lat, lng) {
    return (
      typeof lat === 'number' &&
      typeof lng === 'number' &&
      lat >= -90 &&
      lat <= 90 &&
      lng >= -180 &&
      lng <= 180 &&
      !isNaN(lat) &&
      !isNaN(lng)
    );
  }

  /**
   * Get resolution info for debugging
   * @param {number} resolution H3 resolution level
   * @returns {object} Resolution information
   */
  getResolutionInfo(resolution) {
    const descriptions = {
      5: 'City-wide statistics',
      8: 'Neighborhood context',
      10: 'Block awareness',
      13: 'User tracking (primary)',
    };

    const sampleH3 = h3.latLngToCell(0, 0, resolution);

    return {
      area: h3.cellArea(sampleH3, h3.UNITS.m2),
      avgEdgeLength: h3.edgeLength(resolution, h3.UNITS.m),
      description: descriptions[resolution] || `Resolution ${resolution}`,
    };
  }

  /**
   * Reset service state (useful for testing)
   */
  reset() {
    this.currentH3Index = null;
    this.previousH3Index = null;
    this.locationHistory = [];
    console.log('🔄 H3LocationService reset');
  }
}

// Singleton instance for global use
export const h3LocationService = new H3LocationService();

// Export utility functions for direct use
export const H3Utils = {
  convertToH3: (lat, lng, resolution = H3_RESOLUTIONS.USER_TRACKING) =>
    h3LocationService.convertToH3(lat, lng, resolution),

  convertFromH3: h3Index => h3LocationService.convertFromH3(h3Index),

  getNeighbors: (h3Index, rings = 1) => h3LocationService.getNeighbors(h3Index, rings),

  distance: (h3Index1, h3Index2) => h3LocationService.h3Distance(h3Index1, h3Index2),

  boundary: h3Index => h3LocationService.getHexagonBoundary(h3Index),

  center: h3Index => h3LocationService.getHexagonCenter(h3Index),

  area: resolution => h3LocationService.getHexagonArea(resolution),
};

export default h3LocationService;
