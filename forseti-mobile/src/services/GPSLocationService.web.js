/**
 * GPS Location Service for Web (Mock)
 *
 * Web-compatible mock version using browser Geolocation API
 */

// Default San Francisco location for demo
const DEFAULT_LOCATION = {
  latitude: 37.7749,
  longitude: -122.4194,
  accuracy: 10,
  altitude: 0,
  heading: 0,
  speed: 0,
};

/**
 * GPS Location Service Class (Web Mock)
 */
export class GPSLocationService {
  constructor() {
    this.watchId = null;
    this.currentLocation = DEFAULT_LOCATION;
    this.isTracking = false;
    this.locationCallbacks = [];
    this.errorCallbacks = [];
    this.h3ChangeCallbacks = [];
    this.permissionStatus = 'granted';
  }

  /**
   * Request location permissions (auto-granted on web for demo)
   */
  async requestLocationPermission() {
    console.log('[GPSLocationService.web] Permission auto-granted for web preview');
    this.permissionStatus = 'granted';
    return true;
  }

  /**
   * Check current permission status
   */
  async checkPermissionStatus() {
    return this.permissionStatus;
  }

  /**
   * Get current location
   */
  async getCurrentLocation() {
    console.log('[GPSLocationService.web] Returning mock location:', this.currentLocation);
    return {
      coords: this.currentLocation,
      timestamp: Date.now(),
    };
  }

  /**
   * Start tracking location
   */
  async startTracking() {
    if (this.isTracking) {
      console.log('[GPSLocationService.web] Already tracking');
      return true;
    }

    console.log('[GPSLocationService.web] Starting location tracking (mock)');
    this.isTracking = true;

    // Try to use browser geolocation if available
    if (navigator.geolocation) {
      this.watchId = navigator.geolocation.watchPosition(
        position => {
          this.currentLocation = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            altitude: position.coords.altitude || 0,
            heading: position.coords.heading || 0,
            speed: position.coords.speed || 0,
          };
          this.notifyLocationCallbacks(this.currentLocation);
        },
        error => {
          console.warn('[GPSLocationService.web] Geolocation error:', error.message);
          this.notifyErrorCallbacks(error);
        },
        {
          enableHighAccuracy: true,
          timeout: 15000,
          maximumAge: 10000,
        }
      );
    } else {
      // Fallback to mock updates
      console.log('[GPSLocationService.web] Browser geolocation not available, using mock data');
      this.watchId = setInterval(() => {
        this.notifyLocationCallbacks(this.currentLocation);
      }, 10000);
    }

    return true;
  }

  /**
   * Stop tracking location
   */
  async stopTracking() {
    if (!this.isTracking) {
      return;
    }

    console.log('[GPSLocationService.web] Stopping location tracking');
    this.isTracking = false;

    if (navigator.geolocation && typeof this.watchId === 'number') {
      navigator.geolocation.clearWatch(this.watchId);
    } else if (this.watchId) {
      clearInterval(this.watchId);
    }

    this.watchId = null;
  }

  /**
   * Register callback for location updates
   */
  onLocationUpdate(callback) {
    this.locationCallbacks.push(callback);
    return () => {
      this.locationCallbacks = this.locationCallbacks.filter(cb => cb !== callback);
    };
  }

  /**
   * Register callback for location errors
   */
  onLocationError(callback) {
    this.errorCallbacks.push(callback);
    return () => {
      this.errorCallbacks = this.errorCallbacks.filter(cb => cb !== callback);
    };
  }

  /**
   * Register callback for H3 cell changes
   */
  onH3CellChange(callback) {
    this.h3ChangeCallbacks.push(callback);
    return () => {
      this.h3ChangeCallbacks = this.h3ChangeCallbacks.filter(cb => cb !== callback);
    };
  }

  /**
   * Notify all location callbacks
   */
  notifyLocationCallbacks(location) {
    this.locationCallbacks.forEach(callback => {
      try {
        callback(location);
      } catch (error) {
        console.error('[GPSLocationService.web] Error in location callback:', error);
      }
    });
  }

  /**
   * Notify all error callbacks
   */
  notifyErrorCallbacks(error) {
    this.errorCallbacks.forEach(callback => {
      try {
        callback(error);
      } catch (err) {
        console.error('[GPSLocationService.web] Error in error callback:', err);
      }
    });
  }

  /**
   * Get last known location (from memory)
   */
  async getLastKnownLocation() {
    return {
      coords: this.currentLocation,
      timestamp: Date.now(),
    };
  }

  /**
   * Clear location history (no-op on web)
   */
  async clearLocationHistory() {
    console.log('[GPSLocationService.web] Clear location history (no-op)');
    return true;
  }

  /**
   * Get current H3 index (mock)
   */
  getCurrentH3Index(resolution = 9) {
    // Return a mock H3 index for San Francisco
    return '8928308280fffff';
  }

  /**
   * Get location accuracy status
   */
  getAccuracyStatus() {
    return {
      isHighAccuracy: true,
      accuracy: this.currentLocation.accuracy || 10,
      message: 'Mock location data for web preview',
    };
  }
}

// Create singleton instance
const gpsLocationService = new GPSLocationService();

// Export singleton and class
export { gpsLocationService };
export default gpsLocationService;
