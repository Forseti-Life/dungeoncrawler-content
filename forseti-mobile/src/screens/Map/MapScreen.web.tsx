/**
 * Map Screen - Web Version
 * Embeds the Forseti Safety Map in an iframe for web preview
 */

import React from 'react';
import { View, StyleSheet } from 'react-native';

const MapScreen: React.FC = () => {
  return (
    <View style={styles.container}>
      <iframe
        src="https://forseti.life/safety-map"
        style={styles.iframe}
        title="Forseti Safety Map"
        allow="geolocation"
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    height: '100%',
    width: '100%',
    overflow: 'hidden',
  },
  iframe: {
    border: 'none',
    height: '100%',
    width: '100%',
  },
});

export default MapScreen;
