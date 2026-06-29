// Jest setup file for additional configuration
import '@testing-library/jest-native/extend-expect';

// Mock React Native modules that don't work in Jest environment
jest.mock('react-native/Libraries/Animated/NativeAnimatedHelper');

// Mock AsyncStorage
jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock')
);

// Mock react-native-maps
jest.mock('react-native-maps', () => {
  const React = require('react');
  const MapView = () => React.createElement('MapView');
  MapView.Marker = () => React.createElement('Marker');
  MapView.PROVIDER_GOOGLE = 'google';
  return MapView;
});

// Silence the warning: Animated: `useNativeDriver` is not supported
jest.mock('react-native/Libraries/Animated/NativeAnimatedHelper');

// Mock console methods to reduce noise during tests
global.console = {
  ...console,
  // Uncomment to suppress console methods during tests
  // log: jest.fn(),
  // debug: jest.fn(),
  // info: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
};
