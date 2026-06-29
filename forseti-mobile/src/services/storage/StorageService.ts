/**
 * Storage Service for Forseti Mobile Application
 * Handles local data persistence using AsyncStorage
 */

import AsyncStorage from '@react-native-async-storage/async-storage';

export interface StorageItem {
  key: string;
  value: any;
  timestamp?: number;
  expiry?: number;
}

class StorageService {
  private static instance: StorageService;
  private initialized = false;

  private constructor() {}

  public static getInstance(): StorageService {
    if (!StorageService.instance) {
      StorageService.instance = new StorageService();
    }
    return StorageService.instance;
  }

  /**
   * Initialize the storage service
   */
  public async initialize(): Promise<void> {
    try {
      // Test AsyncStorage availability
      await AsyncStorage.getItem('test');
      this.initialized = true;
      console.log('✅ StorageService initialized successfully');
    } catch (error) {
      console.error('❌ StorageService initialization failed:', error);
      throw new Error('Storage service initialization failed');
    }
  }

  /**
   * Store a value with optional expiry
   */
  public async setItem(key: string, value: any, expiryHours?: number): Promise<void> {
    try {
      const item: StorageItem = {
        key,
        value,
        timestamp: Date.now(),
        expiry: expiryHours ? Date.now() + expiryHours * 60 * 60 * 1000 : undefined,
      };

      await AsyncStorage.setItem(key, JSON.stringify(item));
    } catch (error) {
      console.error(`Error storing item ${key}:`, error);
      throw error;
    }
  }

  /**
   * Retrieve a value, checking expiry if set
   */
  public async getItem<T = any>(key: string): Promise<T | null> {
    try {
      const jsonValue = await AsyncStorage.getItem(key);
      if (jsonValue === null) {
        return null;
      }

      const item: StorageItem = JSON.parse(jsonValue);

      // Check if item has expired
      if (item.expiry && Date.now() > item.expiry) {
        await this.removeItem(key);
        return null;
      }

      return item.value as T;
    } catch (error) {
      console.error(`Error retrieving item ${key}:`, error);
      return null;
    }
  }

  /**
   * Remove a specific item
   */
  public async removeItem(key: string): Promise<void> {
    try {
      await AsyncStorage.removeItem(key);
    } catch (error) {
      console.error(`Error removing item ${key}:`, error);
      throw error;
    }
  }

  /**
   * Clear all stored data
   */
  public async clearAll(): Promise<void> {
    try {
      await AsyncStorage.clear();
    } catch (error) {
      console.error('Error clearing all storage:', error);
      throw error;
    }
  }

  /**
   * Get all keys
   */
  public async getAllKeys(): Promise<string[]> {
    try {
      return await AsyncStorage.getAllKeys();
    } catch (error) {
      console.error('Error getting all keys:', error);
      return [];
    }
  }

  /**
   * Store user preferences
   */
  public async setUserPreferences(preferences: any): Promise<void> {
    await this.setItem('userPreferences', preferences);
  }

  /**
   * Get user preferences
   */
  public async getUserPreferences(): Promise<any> {
    return await this.getItem('userPreferences');
  }

  /**
   * Store cached crime data
   */
  public async setCrimeData(resolution: number, data: any, expiryHours = 24): Promise<void> {
    const key = `crimeData_${resolution}`;
    await this.setItem(key, data, expiryHours);
  }

  /**
   * Get cached crime data
   */
  public async getCrimeData(resolution: number): Promise<any> {
    const key = `crimeData_${resolution}`;
    return await this.getItem(key);
  }

  /**
   * Store user's favorite locations
   */
  public async setFavoriteLocations(locations: any[]): Promise<void> {
    await this.setItem('favoriteLocations', locations);
  }

  /**
   * Get user's favorite locations
   */
  public async getFavoriteLocations(): Promise<any[]> {
    return (await this.getItem('favoriteLocations')) || [];
  }

  /**
   * Store safety alerts history
   */
  public async setSafetyAlerts(alerts: any[]): Promise<void> {
    await this.setItem('safetyAlerts', alerts);
  }

  /**
   * Get safety alerts history
   */
  public async getSafetyAlerts(): Promise<any[]> {
    return (await this.getItem('safetyAlerts')) || [];
  }

  /**
   * Store offline map data
   */
  public async setOfflineMapData(bounds: any, data: any, expiryHours = 168): Promise<void> {
    const key = `offlineMap_${bounds.north}_${bounds.south}_${bounds.east}_${bounds.west}`;
    await this.setItem(key, data, expiryHours);
  }

  /**
   * Get offline map data
   */
  public async getOfflineMapData(bounds: any): Promise<any> {
    const key = `offlineMap_${bounds.north}_${bounds.south}_${bounds.east}_${bounds.west}`;
    return await this.getItem(key);
  }

  /**
   * Clean expired items
   */
  public async cleanExpiredItems(): Promise<void> {
    try {
      const keys = await this.getAllKeys();
      const now = Date.now();

      for (const key of keys) {
        try {
          const jsonValue = await AsyncStorage.getItem(key);
          if (jsonValue) {
            const item: StorageItem = JSON.parse(jsonValue);
            if (item.expiry && now > item.expiry) {
              await this.removeItem(key);
              console.log(`🧹 Cleaned expired item: ${key}`);
            }
          }
        } catch (error) {
          // Skip malformed items
          console.warn(`⚠️ Skipping malformed storage item: ${key}`);
        }
      }
    } catch (error) {
      console.error('Error cleaning expired items:', error);
    }
  }

  /**
   * Get storage usage statistics
   */
  public async getStorageStats(): Promise<{
    totalItems: number;
    totalSize: number;
    oldestItem: string | null;
    newestItem: string | null;
  }> {
    try {
      const keys = await this.getAllKeys();
      let totalSize = 0;
      let oldestTimestamp = Infinity;
      let newestTimestamp = 0;
      let oldestKey = null;
      let newestKey = null;

      for (const key of keys) {
        try {
          const jsonValue = await AsyncStorage.getItem(key);
          if (jsonValue) {
            totalSize += jsonValue.length;
            const item: StorageItem = JSON.parse(jsonValue);

            if (item.timestamp) {
              if (item.timestamp < oldestTimestamp) {
                oldestTimestamp = item.timestamp;
                oldestKey = key;
              }
              if (item.timestamp > newestTimestamp) {
                newestTimestamp = item.timestamp;
                newestKey = key;
              }
            }
          }
        } catch (error) {
          // Skip malformed items
        }
      }

      return {
        totalItems: keys.length,
        totalSize,
        oldestItem: oldestKey,
        newestItem: newestKey,
      };
    } catch (error) {
      console.error('Error getting storage stats:', error);
      return {
        totalItems: 0,
        totalSize: 0,
        oldestItem: null,
        newestItem: null,
      };
    }
  }
}

// Export singleton instance
const storageService = StorageService.getInstance();
export default storageService;
export { StorageService, storageService };
