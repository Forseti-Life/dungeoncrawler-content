/**
 * Map Screen - Interactive Safety Map with H3 Hexagons
 * Displays real-time crime data on a map with H3 hexagonal grid
 */

import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ActivityIndicator, Alert } from 'react-native';
import MapView, { Polygon, PROVIDER_GOOGLE } from 'react-native-maps';
import { cellToBoundary } from 'h3-js';
import axios from 'axios';
import LocationService from '../../services/location/LocationService';
import { Colors } from '../../utils/colors';
import DebugConsole, { DebugLogger } from '../../components/DebugConsole';

interface HexagonData {
  h3_index: string;
  incident_count: number;
  center?: {
    lat: number;
    lng: number;
  };
}

const MapScreen: React.FC = () => {
  const [hexagons, setHexagons] = useState<HexagonData[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusText, setStatusText] = useState('Initializing...');
  const [region, setRegion] = useState({
    latitude: 39.9526,
    longitude: -75.1652,
    latitudeDelta: 0.1,
    longitudeDelta: 0.1,
  });

  useEffect(() => {
    initializeMap();
  }, []);

  const initializeMap = async () => {
    try {
      DebugLogger.info('🗺️ Initializing map...');
      // Try to get current location
      const location = await LocationService.getCurrentLocation();
      if (location) {
        DebugLogger.info(`📍 Got location: ${location.latitude}, ${location.longitude}`);
        setRegion({
          latitude: location.latitude,
          longitude: location.longitude,
          latitudeDelta: 0.05,
          longitudeDelta: 0.05,
        });
      }
    } catch (error) {
      DebugLogger.warn('⚠️ Using default Philadelphia location');
      console.log('Using default Philadelphia location');
    }

    await loadHexagonData();
  };

  const loadHexagonData = async () => {
    try {
      setLoading(true);
      setStatusText('Fetching hexagon data...');
      DebugLogger.info('📡 Fetching hexagon data from API...');

      const response = await axios.get('https://forseti.life/api/amisafe/aggregated', {
        params: {
          resolution: 9,
          limit: 500,
        },
        timeout: 15000,
      });

      setStatusText('Processing API response...');
      DebugLogger.info(`✅ API Response received`);

      if (response.data && response.data.hexagons) {
        const hexData = response.data.hexagons;
        setStatusText(`Loaded ${hexData.length} hexagons`);
        DebugLogger.info(`✅ Loaded ${hexData.length} hexagons`);
        DebugLogger.info(
          `📊 Sample hex: ${hexData[0]?.h3_index}, count: ${hexData[0]?.incident_count}`
        );
        setHexagons(hexData);
      } else {
        setStatusText('ERROR: No hexagons in response');
        DebugLogger.error('❌ No hexagons in API response');
        DebugLogger.error(
          `Response structure: ${JSON.stringify(Object.keys(response.data || {}))}`
        );
      }
    } catch (error: any) {
      setStatusText(`ERROR: ${error.message}`);
      DebugLogger.error(`❌ API Error: ${error.message}`);
      if (error.response) {
        DebugLogger.error(`Response status: ${error.response.status}`);
      }
      console.error('Error loading hexagon data:', error);
      Alert.alert('Error', 'Failed to load map data. Please try again later.');
    } finally {
      setLoading(false);
      setStatusText(prev => (prev.includes('ERROR') ? prev : `Ready: ${hexagons.length} hexagons`));
      DebugLogger.info('✅ Map loading complete');
    }
  };

  const getHexagonColor = (count: number): string => {
    if (count === 0) return 'rgba(0, 212, 255, 0.1)'; // Very light cyan
    if (count < 5) return 'rgba(255, 235, 59, 0.4)'; // Yellow
    if (count < 15) return 'rgba(255, 152, 0, 0.5)'; // Orange
    if (count < 30) return 'rgba(255, 87, 34, 0.6)'; // Deep orange
    return 'rgba(244, 67, 54, 0.7)'; // Red
  };

  const renderHexagons = () => {
    DebugLogger.info(`🎨 Rendering ${hexagons.length} hexagons`);
    let successCount = 0;
    let errorCount = 0;

    const polygons = hexagons.map((hex, index) => {
      try {
        // Convert H3 index to boundary coordinates
        const boundary = cellToBoundary(hex.h3_index, true); // true = GeoJSON format [lat, lng]

        if (!boundary || boundary.length === 0) {
          DebugLogger.error(`❌ Empty boundary for hex: ${hex.h3_index}`);
          errorCount++;
          return null;
        }

        const coordinates = boundary.map(([lat, lng]) => ({
          latitude: lat,
          longitude: lng,
        }));

        const color = getHexagonColor(hex.incident_count);
        successCount++;

        if (index === 0) {
          DebugLogger.info(
            `✅ First hex rendered: ${hex.h3_index}, coords: ${coordinates.length}, color: ${color}`
          );
        }

        return (
          <Polygon
            key={hex.h3_index}
            coordinates={coordinates}
            fillColor={color}
            strokeColor="rgba(0, 212, 255, 0.3)"
            strokeWidth={1}
          />
        );
      } catch (error: any) {
        errorCount++;
        if (errorCount <= 3) {
          DebugLogger.error(`❌ Error rendering hex ${hex.h3_index}: ${error.message}`);
        }
        console.error('Error rendering hexagon:', hex.h3_index, error);
        return null;
      }
    });

    DebugLogger.info(`✅ Rendered ${successCount} hexagons, ${errorCount} errors`);
    return polygons;
  };

  return (
    <View style={styles.container}>
      <MapView
        style={styles.map}
        provider={PROVIDER_GOOGLE}
        initialRegion={region}
        region={region}
        onRegionChangeComplete={setRegion}
        showsUserLocation
        showsMyLocationButton
      >
        {!loading && renderHexagons()}
      </MapView>

      {/* Status Text Overlay */}
      <View style={styles.statusOverlay}>
        <Text style={styles.statusText}>{statusText}</Text>
        <Text style={styles.statusSubtext}>Hexagons: {hexagons.length}</Text>
      </View>

      {loading && (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.loadingText}>Loading crime data...</Text>
        </View>
      )}

      <View style={styles.legend}>
        <Text style={styles.legendTitle}>Crime Incidents</Text>
        <View style={styles.legendItem}>
          <View style={[styles.legendColor, { backgroundColor: 'rgba(255, 235, 59, 0.6)' }]} />
          <Text style={styles.legendText}>Low (1-4)</Text>
        </View>
        <View style={styles.legendItem}>
          <View style={[styles.legendColor, { backgroundColor: 'rgba(255, 152, 0, 0.6)' }]} />
          <Text style={styles.legendText}>Medium (5-14)</Text>
        </View>
        <View style={styles.legendItem}>
          <View style={[styles.legendColor, { backgroundColor: 'rgba(255, 87, 34, 0.6)' }]} />
          <Text style={styles.legendText}>High (15-29)</Text>
        </View>
        <View style={styles.legendItem}>
          <View style={[styles.legendColor, { backgroundColor: 'rgba(244, 67, 54, 0.7)' }]} />
          <Text style={styles.legendText}>Very High (30+)</Text>
        </View>
      </View>

      <DebugConsole />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  legend: {
    backgroundColor: 'rgba(26, 26, 46, 0.9)',
    borderRadius: 8,
    bottom: 20,
    left: 20,
    padding: 12,
    position: 'absolute',
  },
  legendColor: {
    borderRadius: 3,
    height: 20,
    marginRight: 8,
    width: 20,
  },
  legendItem: {
    alignItems: 'center',
    flexDirection: 'row',
    marginVertical: 4,
  },
  legendText: {
    color: Colors.text,
    fontSize: 12,
  },
  legendTitle: {
    color: Colors.primary,
    fontSize: 14,
    fontWeight: 'bold',
    marginBottom: 8,
  },
  loadingOverlay: {
    alignItems: 'center',
    backgroundColor: 'rgba(26, 26, 46, 0.8)',
    bottom: 0,
    justifyContent: 'center',
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
  },
  loadingText: {
    color: Colors.text,
    fontSize: 16,
    marginTop: 16,
  },
  map: {
    flex: 1,
  },
  statusOverlay: {
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    borderRadius: 8,
    padding: 12,
    position: 'absolute',
    right: 20,
    top: 20,
    zIndex: 1000,
  },
  statusSubtext: {
    color: Colors.textSecondary,
    fontSize: 12,
    marginTop: 4,
  },
  statusText: {
    color: Colors.primary,
    fontSize: 14,
    fontWeight: 'bold',
  },
});

export default MapScreen;
