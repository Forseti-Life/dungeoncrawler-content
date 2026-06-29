/**
 * Background Monitoring Hook
 *
 * React hook to integrate background location monitoring into the app
 */

import { useEffect, useState } from 'react';
import { Alert, Platform } from 'react-native';
import BackgroundLocationService from '../services/location/BackgroundLocationService';
import StorageService from '../storage/StorageService';
import { request, PERMISSIONS, RESULTS } from 'react-native-permissions';
import { DebugLogger } from '../components/DebugConsole';
import { logError, logInfo, logWarning } from '../utils/ErrorHandler';
import { requestNotificationPermission } from '../utils/permissions';

export const useBackgroundMonitoring = () => {
  const [isMonitoring, setIsMonitoring] = useState(false);
  const [currentH3Index, setCurrentH3Index] = useState<string | null>(null);
  const [permissionsGranted, setPermissionsGranted] = useState(false);

  /**
   * Request location permissions
   */
  const requestLocationPermissions = async (): Promise<boolean> => {
    try {
      // Request foreground location first
      const foregroundPermission = Platform.select({
        ios: PERMISSIONS.IOS.LOCATION_WHEN_IN_USE,
        android: PERMISSIONS.ANDROID.ACCESS_FINE_LOCATION,
      });

      if (!foregroundPermission) {
        return false;
      }

      let result = await request(foregroundPermission);

      if (result !== RESULTS.GRANTED) {
        Alert.alert(
          'Location Permission Required',
          'Forseti needs location access to provide safety alerts.',
          [{ text: 'OK' }]
        );
        return false;
      }

      // Request background location (iOS always, Android 10+)
      if (Platform.OS === 'ios') {
        const backgroundPermission = PERMISSIONS.IOS.LOCATION_ALWAYS;
        result = await request(backgroundPermission);

        if (result !== RESULTS.GRANTED) {
          Alert.alert(
            'Background Location Required',
            'Please enable "Always Allow" location access in Settings for continuous safety monitoring.',
            [{ text: 'OK' }]
          );
          return false;
        }
      } else if (Platform.OS === 'android' && Number(Platform.Version) >= 29) {
        // Android 10+ requires separate background permission
        const backgroundPermission = PERMISSIONS.ANDROID.ACCESS_BACKGROUND_LOCATION;
        result = await request(backgroundPermission);

        if (result !== RESULTS.GRANTED) {
          Alert.alert(
            'Background Location Required',
            'Please enable "Allow all the time" location access for continuous safety monitoring.',
            [{ text: 'OK' }]
          );
          return false;
        }
      }

      setPermissionsGranted(true);
      return true;
    } catch (error) {
      logError('useBackgroundMonitoring:requestPermissions', error);
      return false;
    }
  };

  /**
   * Start background monitoring
   */
  const startMonitoring = async () => {
    try {
      logInfo('useBackgroundMonitoring', 'Starting background monitoring...');

      // Check/request permissions first
      logInfo('useBackgroundMonitoring', 'Requesting permissions...');

      const hasPermissions = await requestLocationPermissions();
      if (!hasPermissions) {
        logWarning('useBackgroundMonitoring', 'Permissions denied by user');
        return;
      }
      logInfo('useBackgroundMonitoring', 'Permissions granted');

      // Request notification permission (Android 13+ / iOS)
      if (Platform.OS === 'android' && Platform.Version >= 33) {
        logInfo('useBackgroundMonitoring', 'Requesting notification permission (Android 13+)...');
        const notifPermission = await requestNotificationPermission();
        if (!notifPermission) {
          logWarning('useBackgroundMonitoring', 'Notification permission denied');
          Alert.alert(
            'Permission Required',
            'Notifications are required for safety alerts and background monitoring on Android 13+.',
            [{ text: 'OK' }]
          );
          return;
        }
        logInfo('useBackgroundMonitoring', 'Notification permission granted');
      }

      // Start the background service
      // Start the background service
      logInfo('useBackgroundMonitoring', 'Starting BackgroundLocationService...');

      try {
        await BackgroundLocationService.startMonitoring();
        logInfo('useBackgroundMonitoring', 'BackgroundLocationService started successfully');
      } catch (serviceError) {
        logError('useBackgroundMonitoring:serviceError', serviceError, {
          errorType: typeof serviceError,
          step: 'BackgroundLocationService.startMonitoring()',
        });
        throw serviceError;
      }

      setIsMonitoring(true);
      logInfo('useBackgroundMonitoring', 'Monitoring state set to true');

      Alert.alert(
        '🛡️ Protection Enabled',
        "Forseti is now monitoring your location. You'll be alerted if you enter high-crime areas.",
        [{ text: 'OK' }]
      );
    } catch (error) {
      logError('useBackgroundMonitoring:startMonitoring', error, {
        step: 'Start monitoring failed',
        isMonitoring: isMonitoring,
      });

      Alert.alert(
        'Error',
        `Failed to start background monitoring.\n\nError: ${error instanceof Error ? error.message : String(error)}\n\nCheck the debug console (bug icon) for full details.`,
        [{ text: 'OK' }]
      );
      setIsMonitoring(false);
      throw error; // Re-throw so toggleMonitoring can catch it
    }
  };

  /**
   * Stop background monitoring
   */
  const stopMonitoring = async () => {
    try {
      console.log('🛑 [useBackgroundMonitoring] Stopping background monitoring...');
      await BackgroundLocationService.stopMonitoring();
      console.log('✅ [useBackgroundMonitoring] Background monitoring stopped');
      setIsMonitoring(false);

      Alert.alert('Protection Disabled', 'Background monitoring has been stopped.', [
        { text: 'OK' },
      ]);
    } catch (error) {
      console.error('❌ [useBackgroundMonitoring] Error stopping monitoring:', error);
      console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');
      setIsMonitoring(false);
    }
  };

  /**
   * Toggle monitoring on/off
   */
  const toggleMonitoring = async () => {
    // Log immediately (synchronously) before any async operations
    console.log(`🔄 [useBackgroundMonitoring] Toggle monitoring (current: ${isMonitoring})`);
    DebugLogger.info(`🔄 Toggle monitoring called (current: ${isMonitoring})`);

    try {
      if (isMonitoring) {
        DebugLogger.info('🛑 Stopping monitoring...');
        await stopMonitoring();
        DebugLogger.info('✅ Monitoring stopped successfully');
      } else {
        DebugLogger.info('🚀 Starting monitoring...');
        await startMonitoring();
        DebugLogger.info('✅ Monitoring started successfully');
      }
    } catch (error) {
      console.error('❌ [useBackgroundMonitoring] Error toggling monitoring:', error);
      console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');

      // Log to DebugConsole
      try {
        DebugLogger.error('❌ Toggle monitoring failed', String(error));
        if (error instanceof Error && error.stack) {
          DebugLogger.error('Stack trace', error.stack);
        }
        DebugLogger.error('Error type', typeof error);
        if (error && typeof error === 'object') {
          DebugLogger.error('Error keys', Object.keys(error).join(', '));
        }
      } catch (debugLogError) {
        console.error('Failed to log to DebugConsole:', debugLogError);
      }

      Alert.alert(
        'Error',
        `Failed to ${isMonitoring ? 'stop' : 'start'} background monitoring.\n\nError: ${error instanceof Error ? error.message : String(error)}\n\nCheck debug console for details.`,
        [{ text: 'OK' }]
      );

      // Restore state if toggle failed
      try {
        const active = BackgroundLocationService.isActive();
        console.log(`📊 [useBackgroundMonitoring] Restoring state to: ${active}`);
        setIsMonitoring(active);
      } catch (restoreError) {
        logError('useBackgroundMonitoring:restoreState', restoreError);
        setIsMonitoring(false);
      }
    }
  };

  /**
   * Restore monitoring state on mount
   */
  useEffect(() => {
    const restoreState = async () => {
      try {
        logInfo('useBackgroundMonitoring', 'Restoring monitoring state...');

        // Restore monitoring if it was enabled before (currently disabled in service)
        await BackgroundLocationService.restoreMonitoringState();
        logInfo('useBackgroundMonitoring', 'Monitoring state check complete');

        // Update UI state
        const active = BackgroundLocationService.isActive();
        logInfo('useBackgroundMonitoring', `Monitoring active: ${active}`);
        setIsMonitoring(active);

        // Update current H3 index periodically
        const interval = setInterval(() => {
          try {
            const h3Index = BackgroundLocationService.getCurrentH3Index();
            setCurrentH3Index(h3Index);
          } catch (error) {
            // Ignore errors in interval
          }
        }, 5000);

        return () => clearInterval(interval);
      } catch (error) {
        logError('useBackgroundMonitoring:restoreMonitoringState', error);
        // Don't crash - just set to inactive state
        setIsMonitoring(false);
        setCurrentH3Index(null);
      }
    };

    // Run restore in a way that won't crash the app
    restoreState().catch(error => {
      logError('useBackgroundMonitoring:fatalRestore', error);
      setIsMonitoring(false);
      setCurrentH3Index(null);
    });
  }, []);

  return {
    isMonitoring,
    currentH3Index,
    permissionsGranted,
    startMonitoring,
    stopMonitoring,
    toggleMonitoring,
    requestLocationPermissions,
  };
};
