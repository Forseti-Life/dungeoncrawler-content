/**
 * H3 Geospatial Service for Web (Mock)
 *
 * Web-compatible mock using h3-js library
 */

// Note: h3-js is a pure JavaScript implementation that works on web
// We can use it directly without special mocking

// H3 Resolution configuration
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
 * Core H3 Location Processing Service (Web Mock)
 */
export class H3LocationService {
  constructor() {
    this.currentH3Index = null;
    this.previousH3Index = null;
    this.locationHistory = [];
  }

  /**
   * Convert GPS coordinates to H3 index (mock - returns static index)
   */
  convertToH3(lat, lng, resolution = H3_RESOLUTIONS.USER_TRACKING) {
    try {
      if (!this.isValidCoordinate(lat, lng)) {
        throw new Error(`Invalid coordinates: lat=${lat}, lng=${lng}`);
      }

      // Return mock H3 index for San Francisco area
      const mockIndexes = {
        5: '852830827ffffff', // City wide
        8: '8828308281fffff', // Neighborhood
        10: '8a28308280c7fff', // Block
        13: '8d283082813ffff', // User tracking
      };

      const h3Index = mockIndexes[resolution] || mockIndexes[13];

      console.log(
        `[H3LocationService.web] Mock H3 index for [${lat}, ${lng}] at res ${resolution}: ${h3Index}`
      );

      return h3Index;
    } catch (error) {
      console.error('[H3LocationService.web] Error converting to H3:', error);
      throw error;
    }
  }

  /**
   * Validate coordinates
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
   * Update current H3 index
   */
  updateCurrentIndex(lat, lng, resolution = H3_RESOLUTIONS.USER_TRACKING) {
    this.previousH3Index = this.currentH3Index;
    this.currentH3Index = this.convertToH3(lat, lng, resolution);

    // Add to history
    this.locationHistory.push({
      h3Index: this.currentH3Index,
      timestamp: Date.now(),
      lat,
      lng,
      resolution,
    });

    // Limit history to 100 entries
    if (this.locationHistory.length > 100) {
      this.locationHistory.shift();
    }

    return this.currentH3Index;
  }

  /**
   * Get current H3 index
   */
  getCurrentH3Index() {
    return this.currentH3Index;
  }

  /**
   * Check if H3 cell changed
   */
  hasH3CellChanged() {
    return this.currentH3Index !== this.previousH3Index && this.previousH3Index !== null;
  }

  /**
   * Get neighboring H3 cells (mock)
   */
  getNeighbors(h3Index = null, resolution = H3_RESOLUTIONS.USER_TRACKING) {
    const index = h3Index || this.currentH3Index;
    if (!index) {
      return [];
    }

    // Return mock neighbors
    return [
      '8d283082813ffff',
      '8d283082810ffff',
      '8d283082811ffff',
      '8d283082814ffff',
      '8d283082815ffff',
      '8d283082816ffff',
    ];
  }

  /**
   * Get H3 cell center point (mock)
   */
  getCellCenter(h3Index) {
    // Return San Francisco coordinates
    return {
      lat: 37.7749,
      lng: -122.4194,
    };
  }

  /**
   * Clear location history
   */
  clearHistory() {
    this.locationHistory = [];
    console.log('[H3LocationService.web] History cleared');
  }

  /**
   * Get location history
   */
  getHistory() {
    return [...this.locationHistory];
  }
}

// Create singleton instance
const h3LocationService = new H3LocationService();

// Export singleton and class
export { h3LocationService };
export default h3LocationService;
