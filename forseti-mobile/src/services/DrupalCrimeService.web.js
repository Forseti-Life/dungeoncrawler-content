/**
 * Drupal Crime Data Service for Web (Mock)
 *
 * Web-compatible mock returning sample crime data
 */

// Mock crime data for San Francisco
const MOCK_CRIME_DATA = {
  safetyScore: 72,
  crimeCount: 15,
  recentIncidents: [
    {
      id: 1,
      type: 'Theft',
      severity: 'Medium',
      timestamp: Date.now() - 2 * 60 * 60 * 1000,
      distance: '0.3 miles',
    },
    {
      id: 2,
      type: 'Vandalism',
      severity: 'Low',
      timestamp: Date.now() - 5 * 60 * 60 * 1000,
      distance: '0.5 miles',
    },
    {
      id: 3,
      type: 'Assault',
      severity: 'High',
      timestamp: Date.now() - 12 * 60 * 60 * 1000,
      distance: '0.8 miles',
    },
  ],
  neighborhoodStats: {
    totalIncidents: 234,
    trend: 'decreasing',
    safestHours: ['9am-5pm'],
    riskiestHours: ['11pm-3am'],
  },
};

class DrupalCrimeService {
  constructor() {
    this.baseUrl = 'https://forseti.life';
    this.apiEndpoints = {
      riskLevel: '/api/amisafe/risk-level',
      aggregated: '/api/amisafe/aggregated',
      incidents: '/api/amisafe/incidents',
      citywideStats: '/api/amisafe/citywide-stats',
    };
  }

  /**
   * Get safety score for a specific location (mock)
   */
  async getSafetyScore(latitude, longitude, h3Index = null) {
    console.log(
      `[DrupalCrimeService.web] Mock safety data for: [${latitude.toFixed(6)}, ${longitude.toFixed(6)}]`
    );

    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 300));

    return {
      safetyScore: MOCK_CRIME_DATA.safetyScore,
      crimeCount: MOCK_CRIME_DATA.crimeCount,
      location: `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`,
      h3Index: h3Index || '8d283082813ffff',
      timestamp: Date.now(),
      recentIncidents: MOCK_CRIME_DATA.recentIncidents,
      message: 'Mock data for web preview',
    };
  }

  /**
   * Get aggregated crime data for H3 cell (mock)
   */
  async getAggregatedData(h3Index, resolution = 13) {
    console.log(`[DrupalCrimeService.web] Mock aggregated data for H3: ${h3Index}`);

    await new Promise(resolve => setTimeout(resolve, 300));

    return {
      h3Index,
      resolution,
      totalIncidents: MOCK_CRIME_DATA.crimeCount,
      safetyScore: MOCK_CRIME_DATA.safetyScore,
      incidentsByType: {
        theft: 8,
        vandalism: 4,
        assault: 2,
        other: 1,
      },
      timeDistribution: {
        morning: 2,
        afternoon: 5,
        evening: 4,
        night: 4,
      },
      trend: 'stable',
      lastUpdated: Date.now(),
    };
  }

  /**
   * Get recent crime incidents (mock)
   */
  async getRecentIncidents(latitude, longitude, radius = 1000) {
    console.log(`[DrupalCrimeService.web] Mock incidents near: [${latitude}, ${longitude}]`);

    await new Promise(resolve => setTimeout(resolve, 300));

    return {
      incidents: MOCK_CRIME_DATA.recentIncidents,
      totalCount: MOCK_CRIME_DATA.recentIncidents.length,
      radius,
      center: { lat: latitude, lng: longitude },
      message: 'Mock data for web preview',
    };
  }

  /**
   * Get citywide statistics (mock)
   */
  async getCitywideStats() {
    console.log('[DrupalCrimeService.web] Mock citywide stats');

    await new Promise(resolve => setTimeout(resolve, 300));

    return {
      totalIncidents: 15420,
      averageSafetyScore: 68,
      mostCommonCrimes: ['Theft', 'Vandalism', 'Vehicle Break-in'],
      safestNeighborhoods: ['Marina', 'Nob Hill', 'Pacific Heights'],
      highRiskAreas: ['Tenderloin', 'Mission', 'SOMA'],
      trendingUp: false,
      lastUpdated: Date.now(),
      message: 'Mock data for web preview',
    };
  }

  /**
   * Get crime heatmap data (mock)
   */
  async getHeatmapData(bounds, resolution = 9) {
    console.log('[DrupalCrimeService.web] Mock heatmap data');

    await new Promise(resolve => setTimeout(resolve, 400));

    // Return mock hexagonal data points
    return {
      hexagons: [
        { h3Index: '8928308280fffff', intensity: 0.7, count: 12 },
        { h3Index: '8928308281fffff', intensity: 0.4, count: 6 },
        { h3Index: '8928308282fffff', intensity: 0.9, count: 18 },
        { h3Index: '8928308283fffff', intensity: 0.2, count: 3 },
        { h3Index: '8928308284fffff', intensity: 0.5, count: 8 },
      ],
      resolution,
      bounds,
      totalHexagons: 5,
      message: 'Mock data for web preview',
    };
  }

  /**
   * Subscribe to location updates (mock - no-op)
   */
  async subscribeToLocationUpdates(callback) {
    console.log('[DrupalCrimeService.web] Location subscription (mock)');

    // Return mock unsubscribe function
    return () => {
      console.log('[DrupalCrimeService.web] Unsubscribed from location updates');
    };
  }
}

// Create singleton instance
const drupalCrimeService = new DrupalCrimeService();

// Export singleton and class
export { DrupalCrimeService };
export default drupalCrimeService;
