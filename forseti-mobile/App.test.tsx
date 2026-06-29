/**
 * Minimal test version of App to verify basic functionality
 */

import React from 'react';
import { SafeAreaView, StatusBar, Text, View, StyleSheet } from 'react-native';

function App() {
  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="light-content" />
      <View style={styles.content}>
        <Text style={styles.title}>AmISafe</Text>
        <Text style={styles.subtitle}>Mobile App Test</Text>
        <Text style={styles.message}>✅ App launched successfully!</Text>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#1a1a2e',
    flex: 1,
  },
  content: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    padding: 20,
  },
  message: {
    color: '#4ade80',
    fontSize: 16,
  },
  subtitle: {
    color: '#ffffff',
    fontSize: 18,
    marginBottom: 30,
  },
  title: {
    color: '#667eea',
    fontSize: 32,
    fontWeight: 'bold',
    marginBottom: 10,
  },
});

export default App;
