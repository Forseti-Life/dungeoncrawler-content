// Minimal test to verify React Native Web is working
import React from 'react';
import { AppRegistry, View, Text, StyleSheet } from 'react-native';

const TestApp = () => (
  <View style={styles.container}>
    <Text style={styles.text}>🎉 Forseti Mobile Web Preview</Text>
    <Text style={styles.subtitle}>React Native Web is working!</Text>
  </View>
);

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    backgroundColor: '#1a1a2e',
    flex: 1,
    justifyContent: 'center',
  },
  subtitle: {
    color: '#16c79a',
    fontSize: 16,
  },
  text: {
    color: '#ffffff',
    fontSize: 28,
    fontWeight: 'bold',
    marginBottom: 10,
  },
});

AppRegistry.registerComponent('ForsetiMobileApp', () => TestApp);
AppRegistry.runApplication('ForsetiMobileApp', {
  rootTag: document.getElementById('root'),
});
