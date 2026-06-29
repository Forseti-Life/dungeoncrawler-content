/**
 * Background Location Monitoring Service
 *
 * Monitors user location in the background, tracks H3 hexagon changes,
 * and sends notifications when entering high-crime areas (z-score >= 2)
 */

import Geolocation from 'react-native-geolocation-service';
import { Platform, AppState, AppStateStatus, NativeModules } from 'react-native';
import * as h3 from 'h3-js';
import NotificationService from '../notifications/NotificationService'; // Re-enabled for testing
import StorageService from '../storage/StorageService';
import axios from 'axios';
import { DebugLogger } from '../../components/DebugConsole';
import { logError, logInfo, logWarning } from '../../utils/ErrorHandler';
import APP_VERSION from '../../config/AppVersion';

const { LocationServiceModule } = NativeModules;

interface LocationCoords {
  latitude: number;
  longitude: number;
  accuracy: number;
  timestamp: number;
}

interface H3HexagonData {
  h3_index: string;
  incident_count: number;
  incident_z_score: number;
  risk_level: string;
  resolution: number;
}

class BackgroundLocationService {
  private static instance: BackgroundLocationService;
  private watchId: number | null = null;
  private isMonitoring: boolean = false;
  private currentH3Index: string | null = null;
  private lastNotificationTime: number = 0;
  private notificationCooldown: number = 300000; // 5 minutes in milliseconds (default) - general cooldown
  private zScoreThreshold: number = 2.0; // Default threshold
  private h3Resolution: number = 11; // Default resolution - configurable
  private hexagonNotifications: Map<string, number> = new Map(); // Track per-hexagon notification times
  private readonly PER_HEXAGON_COOLDOWN = 3600000; // 1 hour in milliseconds for same hex

  // Configuration
  private readonly API_BASE_URL = 'https://forseti.life';
  private readonly UPDATE_INTERVAL = 60000; // Check location every 60 seconds
  private readonly DISTANCE_FILTER = 0; // meters - set to 0 for time-based updates without movement requirement

  private constructor() {
    this.setupAppStateListener();
  }

  public static getInstance(): BackgroundLocationService {
    if (!BackgroundLocationService.instance) {
      BackgroundLocationService.instance = new BackgroundLocationService();
    }
    return BackgroundLocationService.instance;
  }

  /**
   * Setup app state listener to maintain monitoring when app is backgrounded
   */
  private setupAppStateListener(): void {
    AppState.addEventListener('change', (nextAppState: AppStateStatus) => {
      if (nextAppState === 'background' && this.isMonitoring) {
        console.log('📱 App backgrounded - maintaining location monitoring');
      } else if (nextAppState === 'active' && this.isMonitoring) {
        console.log('📱 App foregrounded - location monitoring active');
      }
    });
  }

  /**
   * Start background location monitoring
   */
  public async startMonitoring(): Promise<void> {
    console.log('🚀 [BackgroundLocationService] startMonitoring called');
    DebugLogger.info('🚀 [Service] startMonitoring called');

    if (this.isMonitoring) {
      console.log('⚠️ [BackgroundLocationService] Background monitoring already active');
      DebugLogger.warn('⚠️ [Service] Already monitoring, skipping');
      return;
    }

    try {
      // Load user settings from storage
      console.log('⚙️ [BackgroundLocationService] Loading user settings...');
      DebugLogger.info('⚙️ [Service] Loading user settings...');

      try {
        await this.loadUserSettings();
        console.log('✅ [BackgroundLocationService] User settings loaded');
        DebugLogger.info('✅ [Service] User settings loaded');
      } catch (settingsError) {
        DebugLogger.error('❌ [Service] Failed to load settings:', settingsError);
        DebugLogger.error(
          'Settings error:',
          settingsError instanceof Error ? settingsError.message : String(settingsError)
        );
        throw settingsError;
      }

      // Start Android foreground service if available
      if (Platform.OS === 'android') {
        logInfo('BackgroundLocationService', 'Android detected, starting foreground service', {
          platformVersion: Platform.Version,
        });

        logInfo('BackgroundLocationService', 'Checking LocationServiceModule', {
          type: typeof LocationServiceModule,
          isNull: LocationServiceModule === null,
          isUndefined: LocationServiceModule === undefined,
        });

        if (!LocationServiceModule) {
          const availableModules = Object.keys(NativeModules).join(', ');
          logError('BackgroundLocationService', new Error('LocationServiceModule not registered'), {
            availableModules,
            solution: 'Check MainApplication.java for LocationServicePackage registration',
          });
          throw new Error('LocationServiceModule is null/undefined! Native module not registered.');
        }

        logInfo('BackgroundLocationService', 'LocationServiceModule verified', {
          methods: Object.keys(LocationServiceModule).join(', '),
        });

        try {
          logInfo('BackgroundLocationService', 'Calling startLocationService()...');
          const result = await LocationServiceModule.startLocationService();
          logInfo('BackgroundLocationService', 'Android foreground service started', { result });
        } catch (error) {
          logError('BackgroundLocationService:startForegroundService', error, {
            platform: 'android',
            platformVersion: Platform.Version,
          });
          throw new Error(
            `Failed to start location service: ${error instanceof Error ? error.message : String(error)}`
          );
        }
      } else {
        logInfo('BackgroundLocationService', 'iOS detected, skipping foreground service');
      }

      // Initialize notification service
      // await NotificationService.initialize(); // Temporarily disabled

      // Save monitoring state
      console.log('💾 [BackgroundLocationService] Saving monitoring state...');
      await StorageService.setItem('background_monitoring_enabled', true);
      console.log('✅ [BackgroundLocationService] Monitoring state saved');

      this.isMonitoring = true;

      // Start watching location
      console.log('📍 [BackgroundLocationService] Starting location watch...');
      this.watchId = Geolocation.watchPosition(
        position => this.handleLocationUpdate(position.coords),
        error => this.handleLocationError(error),
        {
          enableHighAccuracy: true,
          distanceFilter: this.DISTANCE_FILTER,
          interval: this.UPDATE_INTERVAL,
          fastestInterval: this.UPDATE_INTERVAL / 2,
          showLocationDialog: false, // Don't block service with location dialog
          forceRequestLocation: true, // Force location updates
          forceLocationManager: true, // Use LocationManager for consistent updates
          showsBackgroundLocationIndicator: true, // iOS
          pausesLocationUpdatesAutomatically: false, // iOS
        }
      );

      console.log(
        '✅ [BackgroundLocationService] Background location monitoring started successfully'
      );

      console.log(
        `📍 Monitoring H3 Resolution ${this.h3Resolution} with z-score threshold >= ${this.zScoreThreshold}`
      );
    } catch (error) {
      console.error('❌ Failed to start background monitoring:', error);
      this.isMonitoring = false;
      throw error;
    }
  }

  /**
   * Clean up old hexagon notification entries (cleanup entries older than 2 hours)
   */
  private cleanupOldHexagonNotifications(): void {
    const now = Date.now();
    const cleanupThreshold = this.PER_HEXAGON_COOLDOWN * 2; // 2 hours
    let removedCount = 0;
    
    for (const [hexId, timestamp] of this.hexagonNotifications.entries()) {
      if (now - timestamp > cleanupThreshold) {
        this.hexagonNotifications.delete(hexId);
        removedCount++;
      }
    }
    
    if (removedCount > 0) {
      DebugLogger.info(`🧹 [CLEANUP] Removed ${removedCount} old hexagon notification entries`);
    }
  }
  
  /**
   * Load user settings from storage
   */
  private async loadUserSettings(): Promise<void> {
    try {
      const threshold = await StorageService.getItem('z_score_threshold');
      const cooldown = await StorageService.getItem('notification_cooldown');
      const resolution = await StorageService.getItem('h3_resolution');

      if (threshold !== null) {
        this.zScoreThreshold = threshold;
      }

      if (cooldown !== null) {
        this.notificationCooldown = cooldown * 60000; // Convert minutes to milliseconds
      }
      
      if (resolution !== null) {
        this.h3Resolution = resolution;
      }

      console.log(
        `⚙️ Settings loaded - Z-Score: ${this.zScoreThreshold}, Cooldown: ${cooldown || 5}min, Resolution: ${this.h3Resolution}`
      );
      DebugLogger.info(`⚙️ [SETTINGS] Loaded - Threshold: ${this.zScoreThreshold}, Cooldown: ${cooldown || 5}min, Resolution: ${this.h3Resolution}`);
    } catch (error) {
      console.warn('Could not load user settings, using defaults:', error);
      DebugLogger.error('❌ [SETTINGS] Error loading user settings:', error);
    }
  }
  
  /**
   * Reload settings from storage (public method for settings changes)
   */
  public async reloadSettings(): Promise<void> {
    DebugLogger.info('🔄 [SETTINGS] Reloading settings from storage...');
    await this.loadUserSettings();
    DebugLogger.info('✅ [SETTINGS] Settings reloaded successfully');
  }

  /**
   * Stop background location monitoring
   */
  public async stopMonitoring(): Promise<void> {
    if (!this.isMonitoring) {
      return;
    }

    if (this.watchId !== null) {
      Geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }

    // Stop Android foreground service if available
    if (Platform.OS === 'android' && LocationServiceModule) {
      try {
        await LocationServiceModule.stopLocationService();
        console.log('✅ Android foreground service stopped');
      } catch (error) {
        console.error('Failed to stop Android foreground service:', error);
      }
    }

    this.isMonitoring = false;
    this.currentH3Index = null;

    // Save monitoring state
    await StorageService.setItem('background_monitoring_enabled', false);

    console.log('🛑 Background location monitoring stopped');
  }

  /**
   * Handle location updates (made public for testing)
   */
  public async handleLocationUpdate(coords: any): Promise<void> {
    try {
      const location: LocationCoords = {
        latitude: coords.latitude,
        longitude: coords.longitude,
        accuracy: coords.accuracy,
        timestamp: Date.now(),
      };

      // Check for test location override
      const testH3Location = await StorageService.getItem('test_h3_location');
      let h3Index: string;

      if (testH3Location && typeof testH3Location === 'string' && testH3Location.length >= 10) {
        h3Index = testH3Location;
        DebugLogger.info(`🧪 [TEST MODE] Using test H3 location: ${h3Index}`);
        DebugLogger.info(`🧪 [TEST MODE] Real GPS would be: ${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)}`);
        console.log(`🧪 TEST MODE: Using override H3 index: ${h3Index}`);
        console.log(`🧪 TEST MODE: Real GPS coordinates: ${coords.latitude}, ${coords.longitude}`);
      } else {
        // Convert real GPS to H3 index
        h3Index = h3.latLngToCell(location.latitude, location.longitude, this.h3Resolution);
      }

      // Check if we've moved to a new hexagon
      if (h3Index !== this.currentH3Index) {
        console.log(`📍 Moved to new H3:${this.h3Resolution} hexagon: ${h3Index}`);

        const previousH3 = this.currentH3Index;
        this.currentH3Index = h3Index;

        try {
          DebugLogger.info('🔍 [LOCATION UPDATE] About to call checkHexagonSafety');
          // Fetch crime data for new hexagon
          await this.checkHexagonSafety(h3Index, location, previousH3);
          DebugLogger.info('🔍 [LOCATION UPDATE] checkHexagonSafety completed successfully');
        } catch (safetyError) {
          DebugLogger.error('❌ [LOCATION UPDATE] Error in checkHexagonSafety:', safetyError);
          console.error('Error in hexagon safety check:', safetyError);
          // Don't re-throw - continue location monitoring even if safety check fails
        }
      }
    } catch (error) {
      console.error('Error handling location update:', error);
      DebugLogger.error('❌ [LOCATION UPDATE] General location update error:', error);
    }
  }

  /**
   * Check safety of current hexagon and send notification if dangerous
   */
  private async checkHexagonSafety(
    h3Index: string,
    location: LocationCoords,
    previousH3: string | null
  ): Promise<void> {
    try {
      DebugLogger.info('🔍 [SAFETY CHECK] Starting hexagon safety check');
      
      // Check per-hexagon notification cooldown (1 hour for same hex)
      const now = Date.now();
      const lastNotificationForThisHex = this.hexagonNotifications.get(h3Index) || 0;
      const timeSinceLastNotificationForThisHex = now - lastNotificationForThisHex;
      const hexCooldownRemaining = this.PER_HEXAGON_COOLDOWN - timeSinceLastNotificationForThisHex;
      
      DebugLogger.info(`⏰ [HEX COOLDOWN] Last notification for ${h3Index}: ${Math.round(timeSinceLastNotificationForThisHex/1000)}s ago`);
      DebugLogger.info(`⏰ [HEX COOLDOWN] Required: ${Math.round(this.PER_HEXAGON_COOLDOWN/1000)}s (1 hour)`);
      
      if (hexCooldownRemaining > 0) {
        const remainingMinutes = Math.ceil(hexCooldownRemaining / 60000);
        DebugLogger.info(`⏰ [HEX COOLDOWN] Already notified about this hex - ${remainingMinutes}min remaining`);
        console.log(`🔔 Hex cooldown: Already notified about ${h3Index} - ${remainingMinutes} minutes remaining`);
        
        // Still save location to history even if we don't notify
        const hexagonData = await this.fetchHexagonData(h3Index);
        if (hexagonData) {
          const zScore = hexagonData.incident_z_score || 0;
          await this.saveLocationHistory(h3Index, location, zScore);
        }
        return;
      }
      
      DebugLogger.info('✅ [HEX COOLDOWN] No recent notification for this hex - proceeding');

      DebugLogger.info('🔍 [SAFETY CHECK] About to fetch hexagon data');
      // Fetch hexagon data from API
      const hexagonData = await this.fetchHexagonData(h3Index);
      DebugLogger.info(`🔍 [SAFETY CHECK] Fetch completed, result: ${hexagonData ? 'DATA' : 'NULL'}`);

      if (!hexagonData) {
        console.log('ℹ️ No crime data available for this hexagon');
        DebugLogger.info('🔍 [SAFETY CHECK] Exiting due to no data - SHOULD BE SAFE EXIT');
        return;
      }

      DebugLogger.info('🔍 [SAFETY CHECK] Processing valid hexagon data');

      // Check if z-score meets threshold for notification
      const rawZScore = hexagonData.incident_z_score;
      const zScore = rawZScore || 0;
      
      DebugLogger.info(`🔢 [Z-SCORE] Raw from API: ${rawZScore} (${typeof rawZScore}), Processed: ${zScore}`);
      DebugLogger.info(`🎯 [THRESHOLD] Current threshold: ${this.zScoreThreshold}`);
      DebugLogger.info(`🎯 [COMPARISON] ${zScore} >= ${this.zScoreThreshold}? ${zScore >= this.zScoreThreshold ? 'YES - WILL ALERT' : 'NO - safe'}`);

      // Ensure zScore is a valid number before using toFixed
      if (typeof zScore !== 'number' || !Number.isFinite(zScore)) {
        DebugLogger.warning(`⚠️ [INVALID Z-SCORE] Expected number, got: ${typeof zScore}, value: ${zScore}`);
        return;
      }

      DebugLogger.info(`✅ [Z-SCORE] Valid z-score: ${zScore.toFixed(2)}`);

      if (zScore >= this.zScoreThreshold) {
        DebugLogger.info('� [HIGH RISK] Dangerous area detected - sending notification');
        console.log(`🚨 HIGH RISK: Z-Score ${zScore.toFixed(2)} >= threshold ${this.zScoreThreshold}`);
        await this.sendDangerNotification(hexagonData, location);
        
        // Track notification for this specific hexagon
        this.hexagonNotifications.set(h3Index, now);
        this.lastNotificationTime = now; // Keep for general tracking
        
        DebugLogger.info(`✅ [NOTIFICATION] Danger notification sent for ${h3Index} - 1 hour cooldown started for this hex`);
        DebugLogger.info(`📝 [TRACKING] Now tracking ${this.hexagonNotifications.size} hexagon(s) with recent notifications`);
      } else {
        DebugLogger.info(`✅ [SAFE] Z-Score ${zScore.toFixed(2)} below threshold ${this.zScoreThreshold.toFixed(2)}`);
        console.log(
          `✅ Safe area - z-score: ${zScore.toFixed(2)} (threshold: ${this.zScoreThreshold})`
        );
        DebugLogger.info('🔍 [SAFETY CHECK] Safe area, no notification needed');
      }

      DebugLogger.info('🔍 [SAFETY CHECK] About to save location history');
      // Save location history with validated zScore
      await this.saveLocationHistory(h3Index, location, zScore);
      DebugLogger.info('🔍 [SAFETY CHECK] Location history saved successfully');
      
      // Periodically clean up old hexagon notification entries (every 10th location update)
      if (Math.random() < 0.1) { // 10% chance to run cleanup
        this.cleanupOldHexagonNotifications();
      }
    } catch (error) {
      console.error('Error checking hexagon safety:', error);
      DebugLogger.error('❌ [HEXAGON SAFETY ERROR] Detailed error info:', error);
      DebugLogger.error('❌ [HEXAGON SAFETY ERROR] Error occurred during safety check process');
      if (error instanceof Error) {
        DebugLogger.error('❌ [ERROR STACK]', error.stack);
      }
    }
  }

  /**
   * Fetch hexagon crime data from API
   */
  private async fetchHexagonData(h3Index: string): Promise<H3HexagonData | null> {
    const apiUrl = `${this.API_BASE_URL}/api/amisafe/aggregated`;
    const params = {
      resolution: this.h3Resolution,
      h3_index: h3Index,
      format: 'json',
    };

    try {
      // Log API call
      DebugLogger.info(`🌐 [API CALL] ${apiUrl}`);
      DebugLogger.info(
        `📋 [API PARAMS] resolution: ${params.resolution}, h3_index: ${params.h3_index}`
      );
      console.log(
        `🌐 API Call: ${apiUrl}?resolution=${params.resolution}&h3_index=${params.h3_index}`
      );

      // Make the API request
      const response = await axios.get(apiUrl, {
        params,
        timeout: 10000,
      });

      // Basic response validation
      if (!response?.data) {
        DebugLogger.info('⚠️ [API RESPONSE] No response data received');
        return null;
      }

      // Log response structure safely
      const responseType = typeof response.data;
      const responseKeys = (response.data && typeof response.data === 'object') 
        ? Object.keys(response.data).join(', ') 
        : 'none';
      
      DebugLogger.info(`📦 [RAW RESPONSE] Type: ${responseType}, Keys: ${responseKeys}`);

      // Check hexagons array with step-by-step logging
      DebugLogger.info('🔍 [STEP 1] About to check hexagons array');
      const hexagons = response.data.hexagons;
      
      DebugLogger.info('🔍 [STEP 2] Hexagons extracted from response');
      const isArray = Array.isArray(hexagons);
      
      DebugLogger.info(`🔍 [STEP 3] Is array check complete: ${isArray}`);
      if (!isArray || hexagons.length === 0) {
        DebugLogger.info('🔍 [STEP 4] Inside null return condition');
        
        const hexagonsLength = isArray ? hexagons.length : 'not-array';
        DebugLogger.info(`🔍 [STEP 5] Length calculated: ${hexagonsLength}`);
        
        DebugLogger.info(`📦 [HEXAGONS] Count: ${hexagonsLength}, First hexagon type: none`);
        DebugLogger.info('🔍 [STEP 6] Basic logging complete');
        
        DebugLogger.info('🔍 [PROCESSING] Taking null return path - no valid hexagon data');
        DebugLogger.info('🔍 [STEP 7] Processing message logged');
        
        DebugLogger.info('⚠️ [API RESPONSE] No hexagon data returned');
        DebugLogger.info('🔍 [STEP 8] Warning logged');
        
        console.log('⚠️ API Response: No hexagon data');
        DebugLogger.info('🔍 [STEP 9] Console log complete');
        
        DebugLogger.info('🔍 [STEP 10] About to return null');
        return null;
      }

      // Process first hexagon
      const hexagon = hexagons[0];
      DebugLogger.info(`📦 [HEXAGONS] Count: ${hexagons.length}, First hexagon type: ${typeof hexagon}`);

      if (!hexagon || typeof hexagon !== 'object') {
        DebugLogger.info('⚠️ [API RESPONSE] Invalid hexagon data structure');
        return null;
      }

      // Extract and validate data safely
      const result = {
        h3_index: String(hexagon.h3_index || ''),
        incident_count: Number(hexagon.incident_count) || 0,
        incident_z_score: Number(hexagon.analytics?.z_scores?.incident) || 0,
        risk_level: String(hexagon.analytics?.risk_level || 'LOW'),
        resolution: this.h3Resolution,
      };

      // Log successful response
      const zScoreValue = Number.isFinite(result.incident_z_score)
        ? result.incident_z_score.toFixed(2)
        : '0.00';
      
      DebugLogger.info(
        `✅ [API RESPONSE] Z-Score: ${zScoreValue}, Incidents: ${result.incident_count}, Risk: ${result.risk_level}`
      );
      console.log(
        `✅ API Response: H3=${result.h3_index}, Z-Score=${zScoreValue}, Count=${result.incident_count}, Risk=${result.risk_level}`
      );

      return result;

    } catch (error) {
      DebugLogger.error('❌ [API ERROR] Failed to fetch hexagon data:', error);
      DebugLogger.error('📱 [VERSION INFO] App:', APP_VERSION.DISPLAY_VERSION);
      console.error('Error fetching hexagon data:', error);
      
      // Safely log error details
      if (error && typeof error === 'object' && 'stack' in error) {
        console.error('Error stack:', error.stack);
      }
      console.error('📱 Version:', APP_VERSION.DISPLAY_VERSION);
      
      return null;
    }
  }

  /**
   * Send danger notification to user
   */
  private async sendDangerNotification(
    hexagonData: H3HexagonData,
    location: LocationCoords
  ): Promise<void> {
    // Ensure we have valid numeric values before using toFixed
    const zScoreValue = Number.isFinite(hexagonData.incident_z_score) 
      ? hexagonData.incident_z_score 
      : 0;
    const zScore = zScoreValue.toFixed(1);
    const incidentCount = hexagonData.incident_count || 0;
    const riskLevel = hexagonData.risk_level || 'LOW';

    // Check if we're in test mode for enhanced logging
    const testH3Location = await StorageService.getItem('test_h3_location');
    const isTestMode = testH3Location && typeof testH3Location === 'string' && testH3Location.length >= 10;
    
    if (isTestMode) {
      DebugLogger.info(`🧪 [TEST NOTIFICATION] Sending notification for test H3: ${hexagonData.h3_index}`);
      DebugLogger.info(`🧪 [TEST NOTIFICATION] Z-Score: ${zScore}, Incidents: ${incidentCount}, Risk: ${riskLevel}`);
    }
    
    // Send the actual notification (re-enabled for testing)
    try {
      await NotificationService.sendSafetyAlert({
        id: `danger-alert-${Date.now()}`,
        title: isTestMode ? '🧪 Test Mode Alert' : '⚠️ High Crime Area Alert',
        message: isTestMode 
          ? `TEST NOTIFICATION\n\nH3: ${hexagonData.h3_index}\nZ-Score: ${zScore}\nIncidents: ${incidentCount}\nRisk Level: ${riskLevel}\n\nThis alert was triggered by your test location.`
          : `You are entering a potentially dangerous area. ${incidentCount} incidents reported here (Risk: ${riskLevel}, Z-Score: ${zScore})`,
        type: 'high_crime_area',
        priority: 'high',
        timestamp: Date.now(),
        location: location,
      });
      
      DebugLogger.info(`✅ [NOTIFICATION SENT] Alert delivered for ${isTestMode ? 'TEST' : 'REAL'} location`);
      console.log(`✅ Notification sent for H3: ${hexagonData.h3_index} (${isTestMode ? 'TEST MODE' : 'LIVE MODE'})`);
    } catch (error) {
      DebugLogger.error('❌ [NOTIFICATION ERROR] Failed to send safety alert:', error);
      console.error('Failed to send notification:', error);
    }
    
    // Legacy logging for debugging
    console.log('⚠️ Danger notification details:', {
      id: `danger-alert-${Date.now()}`,
      title: isTestMode ? '🧪 Test Mode Alert' : '⚠️ High Crime Area Alert',
      message: `${incidentCount} incidents, Z-Score: ${zScore}, Risk: ${riskLevel}`,
      url: `https://forseti.life/safety-map?lat=${location.latitude}&lng=${location.longitude}`,
      testMode: isTestMode,
      data: {
        type: 'danger_alert',
        h3_index: hexagonData.h3_index,
        z_score: hexagonData.incident_z_score || 0,
        latitude: location.latitude,
        longitude: location.longitude,
        url: `https://forseti.life/safety-map?lat=${location.latitude}&lng=${location.longitude}`,
      },
      priority: 'high',
      sound: true,
      vibrate: true,
    }); // Closing console.log

    console.log(
      `🚨 DANGER NOTIFICATION (DISABLED) - Z-Score: ${zScore}, Incidents: ${incidentCount}`
    );
  }

  /**
   * Handle location errors
   */
  private handleLocationError(error: any): void {
    console.error('Background location error:', error);
  }

  /**
   * Save location history for analytics
   */
  private async saveLocationHistory(
    h3Index: string,
    location: LocationCoords,
    zScore: number
  ): Promise<void> {
    try {
      // Ensure we get a proper array, handling corrupted data
      const storedHistory = await StorageService.getItem('location_history');
      const history = Array.isArray(storedHistory) ? storedHistory : [];

      history.push({
        h3_index: h3Index,
        latitude: location.latitude,
        longitude: location.longitude,
        z_score: zScore,
        timestamp: location.timestamp,
        resolution: this.h3Resolution,
      });

      // Keep only last 100 locations
      const trimmedHistory = history.slice(-100);
      await StorageService.setItem('location_history', trimmedHistory);
    } catch (error) {
      console.error('Error saving location history:', error);
    }
  }

  /**
   * Check if monitoring is currently active
   */
  public isActive(): boolean {
    return this.isMonitoring;
  }

  /**
   * Get current H3 index
   */
  public getCurrentH3Index(): string | null {
    return this.currentH3Index;
  }

  /**
   * Get hexagon notification status for debugging
   */
  public getHexagonNotificationStatus(): string {
    if (this.hexagonNotifications.size === 0) {
      return 'No recent hex notifications';
    }
    
    const now = Date.now();
    const statusLines: string[] = [];
    
    for (const [hexId, timestamp] of this.hexagonNotifications.entries()) {
      const minutesAgo = Math.round((now - timestamp) / 60000);
      const remainingMinutes = Math.max(0, 60 - minutesAgo); // 60 minutes = 1 hour
      statusLines.push(`• ${hexId}: ${remainingMinutes}min cooldown left`);
    }
    
    return statusLines.join('\n');
  }

  /**
   * Restore monitoring state on app restart
   */
  public async restoreMonitoringState(): Promise<void> {
    try {
      console.log('🔄 [BackgroundLocationService] Checking monitoring state...');
      const wasEnabled = await StorageService.getItem('background_monitoring_enabled');
      console.log(`📊 [BackgroundLocationService] Previous state: ${wasEnabled}`);

      // Temporarily disable auto-restore to prevent crashes
      // User must manually enable monitoring in Settings
      if (wasEnabled === true) {
        console.log(
          'ℹ️ [BackgroundLocationService] Monitoring was enabled but auto-restore is disabled'
        );
        console.log('ℹ️ [BackgroundLocationService] User must manually enable in Settings');
        // Don't auto-start to prevent crashes
        // await this.startMonitoring();
      }
    } catch (error) {
      console.error('❌ [BackgroundLocationService] Error in restoreMonitoringState:', error);
      // Don't throw - just log and continue
    }
  }
}

export default BackgroundLocationService.getInstance();
