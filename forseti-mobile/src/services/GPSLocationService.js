/**
 * GPS Location Service for Forseti Mobile Application
 *
 * Provides continuous GPS tracking, location permissions management,
 * and integration with H3 geospatial indexing for ultra-precise location monitoring.
 */

import Geolocation from 'react-native-geolocation-service';
import { PermissionsAndroid, Platform, AppState } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { h3LocationService, H3_RESOLUTIONS } from './H3LocationService';

// Location tracking configuration
const LOCATION_CONFIG = {
  enableHighAccuracy: true,
  timeout: 15000,
  maximumAge: 10000,
  distanceFilter: 10, // Minimum distance in meters before update
  interval: 10000, // Update interval in milliseconds (10 seconds)
  fastestInterval: 5000, // Fastest update interval (5 seconds)
  showLocationDialog: true,
  forceRequestLocation: true,
};

// Storage keys for caching
const STORAGE_KEYS = {
  LAST_LOCATION: 'forseti_last_location',
  LOCATION_HISTORY: 'forseti_location_history',
  PERMISSION_STATUS: 'forseti_permission_status',
};

/**
 * GPS Location Service Class
 */
export class GPSLocationService {
  constructor() {
    this.watchId = null;
    this.currentLocation = null;
    this.isTracking = false;
    this.locationCallbacks = [];
    this.errorCallbacks = [];
    this.h3ChangeCallbacks = [];
    this.permissionStatus = 'unknown';

    // Bind methods to maintain context
    this.onLocationUpdate = this.onLocationUpdate.bind(this);
    this.onLocationError = this.onLocationError.bind(this);
    this.handleAppStateChange = this.handleAppStateChange.bind(this);

    // Listen to app state changes for background/foreground handling
    AppState.addEventListener('change', this.handleAppStateChange);
  }

  /**
   * Request location permissions from user
   * @returns {Promise<boolean>} True if permission granted
   */
  async requestLocationPermission() {
    try {
      if (Platform.OS === 'android') {
        const granted = await PermissionsAndroid.request(
          PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
          {
            title: 'Forseti Location Permission',
            message: 'Forseti needs access to your location to provide real-time safety alerts.',
            buttonNeutral: 'Ask Me Later',
            buttonNegative: 'Cancel',
            buttonPositive: 'OK',
          }
        );

        const hasPermission = granted === PermissionsAndroid.RESULTS.GRANTED;
        this.permissionStatus = hasPermission ? 'granted' : 'denied';

        // Cache permission status
        await AsyncStorage.setItem(STORAGE_KEYS.PERMISSION_STATUS, this.permissionStatus);

        console.log(`📍 Location Permission: ${this.permissionStatus}`);
        return hasPermission;
      } else {
        // iOS permissions are handled automatically by react-native-geolocation-service
        this.permissionStatus = 'granted';
        return true;
      }
    } catch (error) {
      console.error('Location Permission Error:', error);
      this.permissionStatus = 'denied';
      return false;
    }
  }

  /**
   * Check current permission status
   * @returns {Promise<string>} Permission status: 'granted', 'denied', 'unknown'
   */
  async checkPermissionStatus() {
    try {
      const cachedStatus = await AsyncStorage.getItem(STORAGE_KEYS.PERMISSION_STATUS);
      if (cachedStatus) {
        this.permissionStatus = cachedStatus;
        return cachedStatus;
      }

      if (Platform.OS === 'android') {
        const hasPermission = await PermissionsAndroid.check(
          PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION
        );
        this.permissionStatus = hasPermission ? 'granted' : 'denied';
      } else {
        this.permissionStatus = 'granted'; // Assume granted on iOS
      }

      return this.permissionStatus;
    } catch (error) {
      console.error('Permission Check Error:', error);
      return 'unknown';
    }
  }

  /**
   * Get current location once
   * @returns {Promise<object>} Location object with lat, lng, accuracy, timestamp
   */
  async getCurrentLocation() {
    return new Promise((resolve, reject) => {
      if (this.permissionStatus !== 'granted') {
        reject(new Error('Location permission not granted'));
        return;
      }

      Geolocation.getCurrentPosition(
        position => {
          const location = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: position.coords.accuracy,
            timestamp: position.timestamp,
            altitude: position.coords.altitude,
            heading: position.coords.heading,
            speed: position.coords.speed,
          };

          console.log(
            `📍 Current Location: [${location.lat.toFixed(6)}, ${location.lng.toFixed(6)}] ±${location.accuracy}m`
          );
          resolve(location);
        },
        error => {
          console.error('Get Current Location Error:', error);
          reject(error);
        },
        LOCATION_CONFIG
      );
    });
  }

  /**
   * Start continuous location tracking
   * @returns {Promise<boolean>} True if tracking started successfully
   */
  async startTracking() {
    try {
      // Check permissions first
      const hasPermission = await this.checkPermissionStatus();
      if (hasPermission !== 'granted') {
        const granted = await this.requestLocationPermission();
        if (!granted) {
          throw new Error('Location permission denied');
        }
      }

      if (this.isTracking) {
        console.log('📍 Location tracking already active');
        return true;
      }

      console.log('🚀 Starting GPS location tracking...');

      this.watchId = Geolocation.watchPosition(
        this.onLocationUpdate,
        this.onLocationError,
        LOCATION_CONFIG
      );

      this.isTracking = true;
      console.log('✅ GPS tracking started successfully');
      return true;
    } catch (error) {
      console.error('Start Tracking Error:', error);
      this.isTracking = false;
      return false;
    }
  }

  /**
   * Stop location tracking
   */
  stopTracking() {
    if (this.watchId !== null) {
      Geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }
    this.isTracking = false;
    console.log('🛑 GPS tracking stopped');
  }

  /**
   * Handle location updates from GPS
   * @param {object} position GPS position object
   */
  async onLocationUpdate(position) {
    const location = {
      lat: position.coords.latitude,
      lng: position.coords.longitude,
      accuracy: position.coords.accuracy,
      timestamp: position.timestamp,
      altitude: position.coords.altitude,
      heading: position.coords.heading,
      speed: position.coords.speed,
    };

    // Update current location
    this.currentLocation = location;

    // Process with H3 service for hexagon tracking
    const h3Result = h3LocationService.checkLocationChange(location.lat, location.lng);

    console.log(
      `📍 Location Update: [${location.lat.toFixed(6)}, ${location.lng.toFixed(6)}] → ${h3Result.currentH3}`
    );

    // Cache location
    await this.cacheLocation(location);

    // Notify location callbacks
    this.locationCallbacks.forEach(callback => {
      try {
        callback(location, h3Result);
      } catch (error) {
        console.error('Location Callback Error:', error);
      }
    });

    // Notify H3 change callbacks if hexagon changed
    if (h3Result.hasChanged && h3Result.shouldTriggerUpdate) {
      console.log(`🔄 H3 Hexagon Changed: ${h3Result.previousH3} → ${h3Result.currentH3}`);

      this.h3ChangeCallbacks.forEach(callback => {
        try {
          callback(h3Result, location);
        } catch (error) {
          console.error('H3 Change Callback Error:', error);
        }
      });
    }
  }

  /**
   * Handle location errors
   * @param {object} error GPS error object
   */
  onLocationError(error) {
    console.error('GPS Location Error:', error);

    this.errorCallbacks.forEach(callback => {
      try {
        callback(error);
      } catch (callbackError) {
        console.error('Error Callback Error:', callbackError);
      }
    });
  }

  /**
   * Handle app state changes (background/foreground)
   * @param {string} nextAppState App state: 'active', 'background', 'inactive'
   */
  handleAppStateChange(nextAppState) {
    console.log(`📱 App State Changed: ${nextAppState}`);

    if (nextAppState === 'background' && this.isTracking) {
      // Continue tracking in background for safety monitoring
      console.log('📍 Continuing location tracking in background');
    } else if (nextAppState === 'active' && !this.isTracking) {
      // Restart tracking when app comes to foreground
      console.log('📍 Restarting location tracking in foreground');
      this.startTracking();
    }
  }

  /**
   * Cache location data for offline access
   * @param {object} location Location object
   */
  async cacheLocation(location) {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.LAST_LOCATION, JSON.stringify(location));

      // Maintain location history (last 50 locations)
      const historyJson = await AsyncStorage.getItem(STORAGE_KEYS.LOCATION_HISTORY);
      let history = historyJson ? JSON.parse(historyJson) : [];

      history.push({
        ...location,
        h3Index: h3LocationService.getCurrentH3Index(),
      });

      // Keep only last 50 locations
      if (history.length > 50) {
        history = history.slice(-50);
      }

      await AsyncStorage.setItem(STORAGE_KEYS.LOCATION_HISTORY, JSON.stringify(history));
    } catch (error) {
      console.error('Cache Location Error:', error);
    }
  }

  /**
   * Get cached location (useful for offline mode)
   * @returns {Promise<object|null>} Cached location or null
   */
  async getCachedLocation() {
    try {
      const locationJson = await AsyncStorage.getItem(STORAGE_KEYS.LAST_LOCATION);
      return locationJson ? JSON.parse(locationJson) : null;
    } catch (error) {
      console.error('Get Cached Location Error:', error);
      return null;
    }
  }

  /**
   * Get location history
   * @returns {Promise<Array>} Array of historical locations
   */
  async getLocationHistory() {
    try {
      const historyJson = await AsyncStorage.getItem(STORAGE_KEYS.LOCATION_HISTORY);
      return historyJson ? JSON.parse(historyJson) : [];
    } catch (error) {
      console.error('Get Location History Error:', error);
      return [];
    }
  }

  /**
   * Register callback for location updates
   * @param {function} callback Function to call on location update
   */
  onLocationUpdate(callback) {
    this.locationCallbacks.push(callback);
  }

  /**
   * Register callback for location errors
   * @param {function} callback Function to call on location error
   */
  onLocationError(callback) {
    this.errorCallbacks.push(callback);
  }

  /**
   * Register callback for H3 hexagon changes
   * @param {function} callback Function to call when user moves to new hexagon
   */
  onH3Change(callback) {
    this.h3ChangeCallbacks.push(callback);
  }

  /**
   * Remove callback
   * @param {function} callback Callback to remove
   */
  removeCallback(callback) {
    this.locationCallbacks = this.locationCallbacks.filter(cb => cb !== callback);
    this.errorCallbacks = this.errorCallbacks.filter(cb => cb !== callback);
    this.h3ChangeCallbacks = this.h3ChangeCallbacks.filter(cb => cb !== callback);
  }

  /**
   * Get current tracking status
   * @returns {object} Status object
   */
  getStatus() {
    return {
      isTracking: this.isTracking,
      hasLocation: this.currentLocation !== null,
      permissionStatus: this.permissionStatus,
      currentLocation: this.currentLocation,
      currentH3Index: h3LocationService.getCurrentH3Index(),
      watchId: this.watchId,
    };
  }

  /**
   * Clean up service (call on app termination)
   */
  cleanup() {
    this.stopTracking();
    AppState.removeEventListener('change', this.handleAppStateChange);
    this.locationCallbacks = [];
    this.errorCallbacks = [];
    this.h3ChangeCallbacks = [];
    console.log('🧹 GPS Location Service cleaned up');
  }
}

// Singleton instance for global use
export const gpsLocationService = new GPSLocationService();

export default gpsLocationService;
