/**
 * Permission utilities for Forseti Mobile Application
 * Handles location, notification, and storage permissions
 */

import { Platform, PermissionsAndroid, Alert } from 'react-native';
import { check, request, PERMISSIONS, RESULTS, Permission } from 'react-native-permissions';

export interface PermissionResult {
  granted: boolean;
  message?: string;
}

/**
 * Request location permission for both iOS and Android
 */
export const requestLocationPermission = async (): Promise<boolean> => {
  try {
    if (Platform.OS === 'android') {
      // Android location permissions
      const fineLocationGranted = await PermissionsAndroid.check(
        PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION
      );

      const coarseLocationGranted = await PermissionsAndroid.check(
        PermissionsAndroid.PERMISSIONS.ACCESS_COARSE_LOCATION
      );

      if (fineLocationGranted && coarseLocationGranted) {
        return true;
      }

      const granted = await PermissionsAndroid.requestMultiple([
        PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
        PermissionsAndroid.PERMISSIONS.ACCESS_COARSE_LOCATION,
      ]);

      return (
        granted[PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION] === 'granted' &&
        granted[PermissionsAndroid.PERMISSIONS.ACCESS_COARSE_LOCATION] === 'granted'
      );
    } else {
      // iOS location permissions
      const permission = PERMISSIONS.IOS.LOCATION_WHEN_IN_USE;
      const result = await check(permission);

      if (result === RESULTS.GRANTED) {
        return true;
      }

      if (result === RESULTS.DENIED) {
        const requestResult = await request(permission);
        return requestResult === RESULTS.GRANTED;
      }

      return false;
    }
  } catch (error) {
    console.error('Error requesting location permission:', error);
    return false;
  }
};

/**
 * Request notification permissions
 */
export const requestNotificationPermission = async (): Promise<boolean> => {
  try {
    if (Platform.OS === 'android') {
      // Android notification permissions (API 33+)
      if (Platform.Version >= 33) {
        const granted = await PermissionsAndroid.request(
          PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS
        );
        return granted === 'granted';
      }
      return true; // Notifications are granted by default on older Android versions
    } else {
      // iOS notification permissions
      const permission = PERMISSIONS.IOS.NOTIFICATIONS;
      const result = await check(permission);

      if (result === RESULTS.GRANTED) {
        return true;
      }

      if (result === RESULTS.DENIED) {
        const requestResult = await request(permission);
        return requestResult === RESULTS.GRANTED;
      }

      return false;
    }
  } catch (error) {
    console.error('Error requesting notification permission:', error);
    return false;
  }
};

/**
 * Request storage permissions (primarily for Android)
 */
export const requestStoragePermission = async (): Promise<boolean> => {
  try {
    if (Platform.OS === 'android') {
      const granted = await PermissionsAndroid.request(
        PermissionsAndroid.PERMISSIONS.WRITE_EXTERNAL_STORAGE
      );
      return granted === 'granted';
    }
    return true; // iOS doesn't require explicit storage permissions for app data
  } catch (error) {
    console.error('Error requesting storage permission:', error);
    return false;
  }
};

/**
 * Check if location services are enabled
 */
export const checkLocationServicesEnabled = async (): Promise<boolean> => {
  try {
    if (Platform.OS === 'android') {
      const enabled = await PermissionsAndroid.check(
        PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION
      );
      return enabled;
    } else {
      const permission = PERMISSIONS.IOS.LOCATION_WHEN_IN_USE;
      const result = await check(permission);
      return result === RESULTS.GRANTED;
    }
  } catch (error) {
    console.error('Error checking location services:', error);
    return false;
  }
};

/**
 * Request all necessary permissions at app startup
 */
export const requestAllPermissions = async (): Promise<{
  location: boolean;
  notifications: boolean;
  storage: boolean;
}> => {
  const results = {
    location: false,
    notifications: false,
    storage: false,
  };

  try {
    // Request location permission
    results.location = await requestLocationPermission();

    // Request notification permission
    results.notifications = await requestNotificationPermission();

    // Request storage permission
    results.storage = await requestStoragePermission();

    return results;
  } catch (error) {
    console.error('Error requesting permissions:', error);
    return results;
  }
};

/**
 * Show permission explanation dialog
 */
export const showPermissionDialog = (
  title: string,
  message: string,
  onAccept: () => void,
  onDecline?: () => void
): void => {
  Alert.alert(
    title,
    message,
    [
      {
        text: 'Not Now',
        onPress: onDecline,
        style: 'cancel',
      },
      {
        text: 'Allow',
        onPress: onAccept,
      },
    ],
    { cancelable: false }
  );
};

/**
 * Permission status checker
 */
export const getPermissionStatus = async (permission: Permission): Promise<string> => {
  try {
    const result = await check(permission);
    return result;
  } catch (error) {
    console.error('Error checking permission status:', error);
    return RESULTS.UNAVAILABLE;
  }
};

export default {
  requestLocationPermission,
  requestNotificationPermission,
  requestStoragePermission,
  checkLocationServicesEnabled,
  requestAllPermissions,
  showPermissionDialog,
  getPermissionStatus,
};
