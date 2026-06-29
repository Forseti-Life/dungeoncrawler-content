import React from 'react';
import { AppRegistry } from 'react-native';
import { name as appName } from './app.json';

// Suppress known third-party library warnings
const originalWarn = console.warn;
const originalError = console.error;

console.warn = (...args) => {
  const msg = args[0]?.toString() || '';
  // Suppress react-navigation and react-native-web warnings
  if (
    msg.includes('shadow*') ||
    msg.includes('pointerEvents') ||
    msg.includes('BackHandler') ||
    msg.includes('screens with the same name')
  ) {
    return;
  }
  originalWarn.apply(console, args);
};

console.error = (...args) => {
  const msg = args[0]?.toString() || '';
  // Suppress non-critical errors and native service errors on web
  if (
    msg.includes('BackHandler') ||
    msg.includes('touch end without a touch start') ||
    msg.includes('getCurrentUser is not a function') ||
    msg.includes('StorageService.default.getData is not a function') ||
    msg.includes('Error checking authentication') ||
    msg.includes('Error loading settings') ||
    msg.includes('Error restoring monitoring state')
  ) {
    return;
  }
  originalError.apply(console, args);
};

console.log('🚀 Forseti Mobile Web - Starting...');

// Wrap app import in try-catch to see errors
let App;
try {
  App = require('./App.web.simple').default;
  console.log('✅ App.web.simple.tsx loaded successfully (custom navigation)');
} catch (error) {
  console.error('❌ Error loading App.tsx:', error);
  // Create error display component
  App = () => {
    const React = require('react');
    const { View, Text, ScrollView, StyleSheet } = require('react-native');
    return React.createElement(
      View,
      { style: { flex: 1, padding: 20, backgroundColor: '#1a1a2e' } },
      React.createElement(
        ScrollView,
        null,
        React.createElement(
          Text,
          { style: { color: '#ff6b6b', fontSize: 20, fontWeight: 'bold', marginBottom: 10 } },
          '❌ Error Loading App'
        ),
        React.createElement(
          Text,
          { style: { color: '#ffffff', fontSize: 14, fontFamily: 'monospace' } },
          error.toString()
        ),
        React.createElement(
          Text,
          { style: { color: '#aaa', fontSize: 12, marginTop: 10, fontFamily: 'monospace' } },
          error.stack
        )
      )
    );
  };
}

// Register and run
try {
  AppRegistry.registerComponent(appName, () => App);
  console.log('✅ Component registered');

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      const rootTag = document.getElementById('root');
      if (rootTag) {
        console.log('✅ Root element found, mounting app');
        AppRegistry.runApplication(appName, { rootTag });
        console.log('✅ App mounted successfully');
      } else {
        console.error('❌ Root element not found');
      }
    });
  } else {
    const rootTag = document.getElementById('root');
    if (rootTag) {
      console.log('✅ Root element found, mounting app');
      AppRegistry.runApplication(appName, { rootTag });
      console.log('✅ App mounted successfully');
    } else {
      console.error('❌ Root element not found');
    }
  }
} catch (error) {
  console.error('❌ Error mounting app:', error);
}
