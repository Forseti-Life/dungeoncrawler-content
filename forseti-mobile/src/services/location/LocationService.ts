/**
 * Location Service for Forseti Mobile Application
 * Handles GPS tracking, geolocation, and location-based features
 */

import Geolocation from 'react-native-geolocation-service';
import { Platform, PermissionsAndroid } from 'react-native';

export interface Location {
  latitude: number;
  longitude: number;
  accuracy?: number;
  timestamp?: number;
}

export interface LocationError {
  code: number;
  message: string;
}

export interface LocationConfig {
  enableHighAccuracy: boolean;
  timeout: number;
  maximumAge: number;
  distanceFilter: number;
  interval: number;
}

class LocationService {
  private static instance: LocationService;
  private watchId: number | null = null;
  private currentLocation: Location | null = null;
  private locationUpdateCallbacks: ((location: Location) => void)[] = [];
  private errorCallbacks: ((error: LocationError) => void)[] = [];

  private defaultConfig: LocationConfig = {
    enableHighAccuracy: true,
    timeout: 15000,
    maximumAge: 10000,
    distanceFilter: 10, // meters
    interval: 30000, // 30 seconds
  };

  private constructor() {}

  public static getInstance(): LocationService {
    if (!LocationService.instance) {
      LocationService.instance = new LocationService();
    }
    return LocationService.instance;
  }

  /**
   * Initialize the location service
   */
  public async initialize(): Promise<void> {
    try {
      const hasPermission = await this.checkLocationPermission();
      if (!hasPermission) {
        throw new Error('Location permission not granted');
      }

      // Get initial location
      await this.getCurrentLocation();
      console.log('✅ LocationService initialized successfully');
    } catch (error) {
      console.error('❌ LocationService initialization failed:', error);
      throw error;
    }
  }

  /**
   * Check if location permission is granted
   */
  private async checkLocationPermission(): Promise<boolean> {
    if (Platform.OS === 'android') {
      const granted = await PermissionsAndroid.check(
        PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION
      );
      return granted;
    }
    return true; // iOS permissions handled in App.tsx
  }

  /**
   * Get current location (one-time)
   */
  public async getCurrentLocation(): Promise<Location> {
    return new Promise((resolve, reject) => {
      Geolocation.getCurrentPosition(
        position => {
          const location: Location = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            timestamp: position.timestamp,
          };
          this.currentLocation = location;
          resolve(location);
        },
        error => {
          const locationError: LocationError = {
            code: error.code,
            message: error.message,
          };
          reject(locationError);
        },
        {
          enableHighAccuracy: this.defaultConfig.enableHighAccuracy,
          timeout: this.defaultConfig.timeout,
          maximumAge: this.defaultConfig.maximumAge,
        }
      );
    });
  }

  /**
   * Start watching location changes
   */
  public startLocationUpdates(config?: Partial<LocationConfig>): void {
    if (this.watchId !== null) {
      this.stopLocationUpdates();
    }

    const finalConfig = { ...this.defaultConfig, ...config };

    this.watchId = Geolocation.watchPosition(
      position => {
        const location: Location = {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          timestamp: position.timestamp,
        };

        this.currentLocation = location;
        this.notifyLocationCallbacks(location);
      },
      error => {
        const locationError: LocationError = {
          code: error.code,
          message: error.message,
        };
        this.notifyErrorCallbacks(locationError);
      },
      {
        enableHighAccuracy: finalConfig.enableHighAccuracy,
        timeout: finalConfig.timeout,
        maximumAge: finalConfig.maximumAge,
        distanceFilter: finalConfig.distanceFilter,
        interval: finalConfig.interval,
      }
    );

    console.log('📍 Location updates started');
  }

  /**
   * Stop watching location changes
   */
  public stopLocationUpdates(): void {
    if (this.watchId !== null) {
      Geolocation.clearWatch(this.watchId);
      this.watchId = null;
      console.log('📍 Location updates stopped');
    }
  }

  /**
   * Subscribe to location updates
   */
  public onLocationUpdate(callback: (location: Location) => void): () => void {
    this.locationUpdateCallbacks.push(callback);

    // Return unsubscribe function
    return () => {
      const index = this.locationUpdateCallbacks.indexOf(callback);
      if (index > -1) {
        this.locationUpdateCallbacks.splice(index, 1);
      }
    };
  }

  /**
   * Subscribe to location errors
   */
  public onLocationError(callback: (error: LocationError) => void): () => void {
    this.errorCallbacks.push(callback);

    // Return unsubscribe function
    return () => {
      const index = this.errorCallbacks.indexOf(callback);
      if (index > -1) {
        this.errorCallbacks.splice(index, 1);
      }
    };
  }

  /**
   * Get the last known location
   */
  public getLastKnownLocation(): Location | null {
    return this.currentLocation;
  }

  /**
   * Calculate distance between two points (Haversine formula)
   */
  public calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const R = 6371; // Earth's radius in kilometers
    const dLat = this.toRadians(lat2 - lat1);
    const dLon = this.toRadians(lon2 - lon1);

    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(this.toRadians(lat1)) *
        Math.cos(this.toRadians(lat2)) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c; // Distance in kilometers
  }

  /**
   * Check if location is within a specific radius
   */
  public isWithinRadius(
    centerLat: number,
    centerLon: number,
    targetLat: number,
    targetLon: number,
    radiusKm: number
  ): boolean {
    const distance = this.calculateDistance(centerLat, centerLon, targetLat, targetLon);
    return distance <= radiusKm;
  }

  /**
   * Get address from coordinates (reverse geocoding)
   */
  public async reverseGeocode(latitude: number, longitude: number): Promise<string> {
    try {
      // This would typically use a geocoding service like Google Maps
      // For now, return formatted coordinates
      return `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
    } catch (error) {
      console.error('Reverse geocoding failed:', error);
      return 'Unknown location';
    }
  }

  /**
   * Convert degrees to radians
   */
  private toRadians(degrees: number): number {
    return degrees * (Math.PI / 180);
  }

  /**
   * Notify all location update callbacks
   */
  private notifyLocationCallbacks(location: Location): void {
    this.locationUpdateCallbacks.forEach(callback => {
      try {
        callback(location);
      } catch (error) {
        console.error('Error in location callback:', error);
      }
    });
  }

  /**
   * Notify all error callbacks
   */
  private notifyErrorCallbacks(error: LocationError): void {
    this.errorCallbacks.forEach(callback => {
      try {
        callback(error);
      } catch (error) {
        console.error('Error in error callback:', error);
      }
    });
  }

  /**
   * Cleanup - stop all location services
   */
  public cleanup(): void {
    this.stopLocationUpdates();
    this.locationUpdateCallbacks = [];
    this.errorCallbacks = [];
    this.currentLocation = null;
    console.log('🧹 LocationService cleaned up');
  }
}

// Export singleton instance
export default LocationService.getInstance();
