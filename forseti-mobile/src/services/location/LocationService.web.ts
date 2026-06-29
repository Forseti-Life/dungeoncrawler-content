/**
 * Web Mock for LocationService
 * Provides mock location data for web preview
 */

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

class LocationService {
  private static instance: LocationService;
  private currentLocation: Location = {
    latitude: 37.7749,
    longitude: -122.4194,
    accuracy: 10,
    timestamp: Date.now(),
  };

  static getInstance(): LocationService {
    if (!LocationService.instance) {
      LocationService.instance = new LocationService();
    }
    return LocationService.instance;
  }

  async getCurrentLocation(): Promise<Location> {
    console.log('[Web Mock] LocationService.getCurrentLocation()');
    return this.currentLocation;
  }

  async requestPermissions(): Promise<boolean> {
    console.log('[Web Mock] LocationService.requestPermissions()');
    return true;
  }

  startWatching(callback: (location: Location) => void): void {
    console.log('[Web Mock] LocationService.startWatching()');
    callback(this.currentLocation);
  }

  stopWatching(): void {
    console.log('[Web Mock] LocationService.stopWatching()');
  }

  getLastKnownLocation(): Location | null {
    return this.currentLocation;
  }
}

export default LocationService.getInstance();
