/**
 * Real Crime Data Service for Forseti Mobile
 *
 * Connects to Drupal amisafe module APIs for crime data, safety scores, and H3 aggregated data
 */

import axios from 'axios';
import drupalAuthService from './DrupalAuthService';

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
   * Get safety score for a specific location
   */
  async getSafetyScore(latitude, longitude, h3Index = null) {
    try {
      console.log(`🛡️ Loading safety data for: [${latitude.toFixed(6)}, ${longitude.toFixed(6)}]`);

      const params = new URLSearchParams({
        lat: latitude.toString(),
        lng: longitude.toString(),
        format: 'json',
      });

      if (h3Index) {
        params.append('h3_index', h3Index);
      }

      const response = await drupalAuthService.authenticatedRequest({
        method: 'GET',
        url: `${this.baseUrl}${this.apiEndpoints.riskLevel}?${params.toString()}`,
        timeout: 10000,
      });

      if (response.data) {
        const data = response.data;

        return {
          safetyScore: data.safety_score || data.risk_score || 75,
          crimeCount: data.incident_count || data.crime_count || 0,
          location: `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`,
          lastUpdated: data.last_updated || new Date().toISOString(),
          h3Index: data.h3_index || h3Index,
          riskLevel: data.risk_level || this.calculateRiskLevel(data.incident_count || 0),
          crimes: data.recent_incidents || [],
          coverage: {
            radius: data.coverage_radius || 500, // meters
            area: data.coverage_area || 0.785, // km²
            h3Resolution: data.h3_resolution || 13,
          },
        };
      }

      // Fallback if no data
      return this.createFallbackSafetyData(latitude, longitude);
    } catch (error) {
      console.error('Error loading safety score:', error);

      // Return fallback data on error
      return this.createFallbackSafetyData(latitude, longitude);
    }
  }

  /**
   * Get H3 aggregated crime data for map visualization
   * Matches web implementation's buildApiUrl - only sends date_range filter
   */
  async getAggregatedData(resolution, bounds, filters = {}) {
    try {
      console.log(`\n📊 [DRUPAL API] Requesting H3 Resolution ${resolution} aggregated data...`);

      // Set limit based on resolution - higher resolution needs more hexagons (matching web)
      let limit = 1000;
      if (resolution >= 11) {
        limit = 20000; // Ultra-precision needs many hexagons
      } else if (resolution >= 8) {
        limit = 5000; // Medium precision
      }

      const params = new URLSearchParams({
        resolution: resolution.toString(),
        bounds: bounds,
        limit: limit.toString(),
        format: 'json',
      });

      // Add date range filter ONLY (matching web implementation)
      // Web does NOT send crime_types, districts, or time_periods
      if (filters.date_range) {
        params.append('date_range', filters.date_range);
        console.log(`  📅 Date filter: ${filters.date_range}`);
      }

      const url = `${this.baseUrl}${this.apiEndpoints.aggregated}?${params.toString()}`;
      console.log('🔗 [DRUPAL API] URL:', url);

      const response = await drupalAuthService.authenticatedRequest({
        method: 'GET',
        url: url,
        timeout: 15000,
      });

      console.log('\n📊 [DRUPAL API] Response received:');
      console.log('  Status:', response.status);
      console.log('  Has data:', !!response.data);
      console.log('  Hexagons:', response.data?.hexagons?.length || 0);

      if (response.data) {
        return {
          hexagons: response.data.hexagons || [],
          meta: {
            resolution: resolution,
            bounds: bounds,
            total: response.data.total || 0,
            filters: filters,
            timestamp: new Date().toISOString(),
          },
        };
      }

      return { hexagons: [], meta: { resolution, bounds, total: 0 } };
    } catch (error) {
      console.error('❌ [DRUPAL API] Error loading aggregated data:', error);
      if (error.response) {
        console.error('  Response status:', error.response.status);
        console.error('  Response data:', error.response.data);
      }
      return { hexagons: [], meta: { resolution, bounds, total: 0, error: error.message } };
    }
  }

  /**
   * Get individual crime incidents for high-resolution views
   * Simplified to match web implementation - only date_range filter
   */
  async getIncidents(bounds, filters = {}) {
    try {
      console.log('📍 Loading individual crime incidents...');

      const params = new URLSearchParams({
        bounds: bounds,
        limit: '500',
        format: 'json',
      });

      // Add date range filter only (matching web implementation)
      if (filters.date_range) {
        params.append('date_range', filters.date_range);
      }

      const response = await drupalAuthService.authenticatedRequest({
        method: 'GET',
        url: `${this.baseUrl}${this.apiEndpoints.incidents}?${params.toString()}`,
        timeout: 10000,
      });

      if (response.data) {
        return {
          incidents: response.data.incidents || [],
          total: response.data.total || 0,
          bounds: bounds,
        };
      }

      return { incidents: [], total: 0, bounds };
    } catch (error) {
      console.error('Error loading incidents:', error);
      return { incidents: [], total: 0, bounds, error: error.message };
    }
  }

  /**
   * Get citywide crime statistics
   */
  async getCitywideStats() {
    try {
      console.log('📈 Loading citywide statistics...');

      const response = await drupalAuthService.authenticatedRequest({
        method: 'GET',
        url: `${this.baseUrl}${this.apiEndpoints.citywideStats}?format=json`,
        timeout: 5000,
      });

      if (response.data) {
        return {
          totalIncidents: response.data.total_incidents || 0,
          last24Hours: response.data.last_24_hours || 0,
          last7Days: response.data.last_7_days || 0,
          last30Days: response.data.last_30_days || 0,
          crimeTypes: response.data.crime_types || {},
          districts: response.data.districts || {},
          trends: response.data.trends || {},
          lastUpdated: response.data.last_updated || new Date().toISOString(),
        };
      }

      return null;
    } catch (error) {
      console.error('Error loading citywide stats:', error);
      return null;
    }
  }

  /**
   * Create fallback safety data when API is unavailable
   */
  createFallbackSafetyData(latitude, longitude) {
    // Generate mock data based on location (for offline/fallback)
    const score = Math.floor(Math.random() * 40) + 60; // 60-100 range
    const crimeCount = Math.floor(Math.random() * 15);

    return {
      safetyScore: score,
      crimeCount: crimeCount,
      location: `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`,
      lastUpdated: new Date().toISOString(),
      riskLevel: this.calculateRiskLevel(crimeCount),
      crimes: this.generateFallbackCrimes(crimeCount),
      coverage: {
        radius: 500,
        area: 0.785,
        h3Resolution: 13,
      },
      fallback: true,
    };
  }

  /**
   * Generate fallback crime data
   */
  generateFallbackCrimes(count) {
    const crimeTypes = ['Theft', 'Assault', 'Vandalism', 'Burglary', 'Vehicle Crime'];
    return Array.from({ length: count }, (_, i) => ({
      id: i + 1,
      type: crimeTypes[Math.floor(Math.random() * crimeTypes.length)],
      distance: `${(Math.random() * 0.5).toFixed(2)} mi`,
      time: `${Math.floor(Math.random() * 24)} hours ago`,
      severity: Math.floor(Math.random() * 5) + 1,
    }));
  }

  /**
   * Calculate risk level based on incident count
   */
  calculateRiskLevel(incidentCount) {
    if (incidentCount === 0) return 'SAFE';
    else if (incidentCount <= 5) return 'LOW';
    else if (incidentCount <= 15) return 'MODERATE';
    else if (incidentCount <= 30) return 'HIGH';
    else return 'CRITICAL';
  }

  /**
   * Test connection to Forseti APIs
   */
  async testConnection() {
    try {
      console.log('🧪 Testing Forseti API connection...');

      // Test basic endpoint
      const response = await axios.get(`${this.baseUrl}/api/amisafe/citywide-stats?format=json`, {
        timeout: 5000,
      });

      console.log('✅ Forseti API connection successful');
      return {
        success: true,
        status: response.status,
        data: response.data,
      };
    } catch (error) {
      console.error('❌ Forseti API connection failed:', error);
      return {
        success: false,
        error: error.message,
        status: error.response?.status,
      };
    }
  }
}

// Create singleton instance
const drupalCrimeService = new DrupalCrimeService();

export default drupalCrimeService;
