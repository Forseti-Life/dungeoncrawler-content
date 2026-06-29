/**
 * Console Log Upload Service for Forseti Mobile Application
 * Uses existing DebugLogger system to upload logs to Drupal backend
 */

import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import APP_VERSION from '../../config/AppVersion';

// Import device info with fallback if package not available
let DeviceInfo: any;
try {
  DeviceInfo = require('react-native-device-info');
} catch (error) {
  console.log('react-native-device-info not available, using fallback');
  DeviceInfo = null;
}

interface UploadLogRequest {
  user_id: string;
  log_content: string;
  device_info?: string;
  app_version?: string;
}

interface UploadLogResponse {
  success: boolean;
  message?: string;
  error?: string;
  timestamp?: number;
}

class ConsoleLogService {
  private static instance: ConsoleLogService;
  private readonly API_BASE_URL = 'https://forseti.life';

  private constructor() {}

  public static getInstance(): ConsoleLogService {
    if (!ConsoleLogService.instance) {
      ConsoleLogService.instance = new ConsoleLogService();
    }
    return ConsoleLogService.instance;
  }

  /**
   * Initialize the service (no-op since we use existing DebugLogger)
   */
  public async initialize(): Promise<void> {
    // No initialization needed - using existing DebugLogger system
    console.log('ConsoleLogService: Using existing DebugLogger system');
  }

  /**
   * Get logs from existing DebugLogger system
   */
  private async getLogsFromStorage(): Promise<string> {
    try {
      const stored = await AsyncStorage.getItem('debug_logs');
      if (!stored) {
        return '';
      }

      const logs = JSON.parse(stored);
      if (!Array.isArray(logs)) {
        return '';
      }

      // Format logs similar to how DebugLogger displays them
      return logs
        .map((log: any) => {
          const timestamp = new Date(log.timestamp).toISOString();
          return `[${log.level.toUpperCase()}] ${timestamp} - ${log.message}`;
        })
        .join('\n');
    } catch (error) {
      console.error('Failed to load debug logs:', error);
      return '';
    }
  }

  /**
   * Get device information
   */
  private async getDeviceInfo(): Promise<string> {
    try {
      if (!DeviceInfo) {
        // Fallback device info without react-native-device-info
        return JSON.stringify({
          platform: Platform.OS,
          platformVersion: Platform.Version,
          timestamp: new Date().toISOString(),
          note: 'Limited device info - react-native-device-info not available',
        });
      }

      const deviceInfo = {
        model: await DeviceInfo.getModel(),
        brand: await DeviceInfo.getBrand(),
        systemName: await DeviceInfo.getSystemName(),
        systemVersion: await DeviceInfo.getSystemVersion(),
        platform: Platform.OS,
        platformVersion: Platform.Version,
        appVersion: await DeviceInfo.getVersion(),
        buildNumber: await DeviceInfo.getBuildNumber(),
        bundleId: await DeviceInfo.getBundleId(),
        deviceId: await DeviceInfo.getDeviceId(),
        isEmulator: await DeviceInfo.isEmulator(),
        totalMemory: await DeviceInfo.getTotalMemory(),
        usedMemory: await DeviceInfo.getUsedMemory(),
      };

      return JSON.stringify(deviceInfo);
    } catch (error) {
      return JSON.stringify({
        error: 'Failed to get device info',
        platform: Platform.OS,
        platformVersion: Platform.Version,
        timestamp: new Date().toISOString(),
      });
    }
  }

  /**
   * Get user identifier
   */
  private async getUserId(): Promise<string> {
    try {
      // Debug: Log what auth data is available
      console.log('🔍 Checking user authentication data...');
      
      // Try to get user from Drupal auth system first
      const forsetiUser = await AsyncStorage.getItem('forseti_user');
      if (forsetiUser) {
        const user = JSON.parse(forsetiUser);
        console.log('✅ Found forseti_user:', { mail: user.mail, name: user.name, uid: user.uid });
        if (user.mail) {
          return user.mail; // Use email as user ID
        }
        if (user.name) {
          return user.name; // Use username as fallback
        }
        if (user.uid) {
          return `user_${user.uid}`; // Use UID as fallback
        }
      }

      // Try to get from general login system
      const username = await AsyncStorage.getItem('username');
      if (username) {
        console.log('✅ Found username:', username);
        return username;
      }

      const userId = await AsyncStorage.getItem('userId');
      if (userId) {
        console.log('✅ Found userId:', userId);
        return `user_${userId}`;
      }

      console.log('⚠️ No user authentication data found');

      // Try to get device ID if DeviceInfo is available
      if (DeviceInfo) {
        const deviceId = await DeviceInfo.getDeviceId();
        console.log('📱 Using device ID:', deviceId);
        return `device_${deviceId}`;
      }

      // Fallback to timestamp-based ID
      const fallbackId = `anonymous_${Date.now()}`;
      console.log('🔄 Using fallback ID:', fallbackId);
      return fallbackId;
    } catch (error) {
      console.error('❌ Error getting user ID:', error);
      // Ultimate fallback to timestamp-based ID
      return `fallback_${Date.now()}`;
    }
  }

  /**
   * Upload logs to server
   */
  public async uploadLogs(): Promise<boolean> {
    try {
      const logContent = await this.getLogsFromStorage();
      if (!logContent.trim()) {
        console.log('No debug logs to upload');
        return false;
      }

      const userId = await this.getUserId();
      const deviceInfo = await this.getDeviceInfo();
      const appVersion = DeviceInfo ? await DeviceInfo.getVersion() : APP_VERSION.SHORT_VERSION;
      
      console.log('📤 Uploading console logs:', {
        version: appVersion,
        buildDate: APP_VERSION.BUILD_DATE,
        userId: userId,
        logLength: logContent.length
      });

      const requestData: UploadLogRequest = {
        user_id: userId,
        log_content: logContent,
        device_info: deviceInfo,
        app_version: appVersion,
      };

      const response = await fetch(`${this.API_BASE_URL}/api/amisafe/log/upload`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData),
      });

      const result: UploadLogResponse = await response.json();

      if (result.success) {
        console.log('Debug logs uploaded successfully');
        return true;
      } else {
        console.error('Failed to upload debug logs:', result.error);
        return false;
      }
    } catch (error) {
      console.error('Error uploading debug logs:', error);
      return false;
    }
  }

  /**
   * Get current log count from DebugLogger storage
   */
  public async getLogCount(): Promise<number> {
    try {
      const stored = await AsyncStorage.getItem('debug_logs');
      if (!stored) {
        return 0;
      }

      const logs = JSON.parse(stored);
      return Array.isArray(logs) ? logs.length : 0;
    } catch (error) {
      return 0;
    }
  }

  /**
   * Get logs as formatted string for display
   */
  public async getLogsAsString(): Promise<string> {
    return await this.getLogsFromStorage();
  }

  /**
   * Clear logs (delegates to DebugLogger system)
   */
  public async clearLogs(): Promise<void> {
    try {
      await AsyncStorage.removeItem('debug_logs');
      console.log('Debug logs cleared');
    } catch (error) {
      console.error('Failed to clear debug logs:', error);
    }
  }
}

export default ConsoleLogService;
