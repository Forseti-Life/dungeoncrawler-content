/**
 * @format
 */

import 'react-native-gesture-handler';
import { AppRegistry } from 'react-native';
import App from './App';
import './src/services/location/BackgroundLocationService'; // Register headless task
import ErrorHandler from './src/utils/ErrorHandler';

// Initialize global error handling framework
try {
  ErrorHandler.initialize();
} catch (error) {
  console.error('[index.js] Failed to initialize error handler:', error);
}

AppRegistry.registerComponent('forseti-mobile', () => App);
