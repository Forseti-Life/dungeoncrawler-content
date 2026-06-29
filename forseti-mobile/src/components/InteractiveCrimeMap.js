/**
 * Interactive Safety Map Component for Forseti Mobile
 *
 * React Native implementation based on the web crime-map.js functionality
 * Features: H3 hexagon visualization, zoom-based resolution switching, crime incidents
 */

import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Dimensions,
  Alert,
  TouchableOpacity,
  Modal,
  ScrollView,
  ActivityIndicator,
} from 'react-native';
import MapView, { PROVIDER_GOOGLE, Polygon, Circle } from 'react-native-maps';
import Svg, { Polygon as SvgPolygon } from 'react-native-svg';
import * as h3 from 'h3-js';
import FilterPanel from './FilterPanel';
import { DebugLogger } from './DebugConsole';

const { width, height } = Dimensions.get('window');

// Philadelphia center coordinates (matching web implementation)
const PHILADELPHIA_CENTER = {
  latitude: 39.9526,
  longitude: -75.1652,
  latitudeDelta: 0.0922,
  longitudeDelta: 0.0421,
};

const InteractiveCrimeMap = ({
  initialLocation = PHILADELPHIA_CENTER,
  onLocationChange,
  filters = {},
  drupalCrimeService = null,
}) => {
  // Core state
  const [mapRegion, setMapRegion] = useState(initialLocation);
  const [hexagons, setHexagons] = useState([]);
  const [incidents, setIncidents] = useState([]);
  const [currentFilters, setCurrentFilters] = useState(filters);
  const [isLoading, setIsLoading] = useState(false);
  const [selectedHexagon, setSelectedHexagon] = useState(null);
  const [showHexagonDetails, setShowHexagonDetails] = useState(false);
  const [citywideStats, setCitywideStats] = useState(null);

  // Map control state
  const [currentZoom, setCurrentZoom] = useState(12);
  const [viewMode, setViewMode] = useState('hexagon'); // hexagon, heatmap, points
  const [minimalMode] = useState(true); // Clean data visualization

  // Filter state (simplified to match web version - only datePreset)
  const [showFilterPanel, setShowFilterPanel] = useState(false);
  const [activeFilters, setActiveFilters] = useState({
    datePreset: '12months', // Default to 12 months (matching web)
  });

  // Performance optimization
  const mapRef = useRef(null);
  const dataCache = useRef(new Map());
  const currentRequest = useRef(null);

  /**
   * Get optimal H3 resolution based on zoom level (matching web implementation)
   */
  const getOptimalResolution = zoomLevel => {
    if (zoomLevel <= 9)
      return 4; // ~1,770 km² - Complete metro area coverage
    else if (zoomLevel <= 10)
      return 5; // ~251 km² - Metro districts
    else if (zoomLevel <= 11)
      return 6; // ~36 km² - City areas
    else if (zoomLevel <= 12)
      return 7; // ~5.2 km² - Neighborhoods
    else if (zoomLevel <= 13)
      return 8; // ~0.7 km² - Block groups
    else if (zoomLevel <= 14)
      return 9; // ~0.1 km² - Street blocks
    else if (zoomLevel <= 15)
      return 10; // ~15,047 m² - Building groups
    else if (zoomLevel <= 16)
      return 11; // ~2,150 m² - Individual buildings
    else if (zoomLevel <= 17)
      return 12; // ~307 m² - Room-level precision
    else return 13; // ~44 m² - Ultra-precision
  };

  /**
   * Get human-readable description of H3 resolution
   */
  const getResolutionDescription = resolution => {
    const descriptions = {
      4: '~1,770km² metro-wide',
      5: '~251km² districts',
      6: '~36km² city areas',
      7: '~5.2km² neighborhoods',
      8: '~0.7km² block groups',
      9: '~0.1km² street blocks',
      10: '~15,047m² building groups',
      11: '~2,150m² buildings',
      12: '~307m² rooms',
      13: '~44m² ultra-precision',
    };
    return descriptions[resolution] || 'unknown';
  };

  /**
   * Calculate risk level based on incident count
   */
  const calculateRiskLevel = incidentCount => {
    if (incidentCount === 0) return 'SAFE';
    else if (incidentCount <= 5) return 'LOW';
    else if (incidentCount <= 15) return 'MODERATE';
    else if (incidentCount <= 30) return 'HIGH';
    else return 'CRITICAL';
  };

  /**
   * Calculate hexagon styling based on incident count
   */
  /**
   * Calculate hexagon styling based on z-score (matching web implementation)
   * Uses incident_z_score for normalized heat map coloring across resolutions
   */
  const calculateHexagonStyle = hexagonData => {
    // Extract z-score from analytics if available, otherwise use incident count
    let zScore = 0;
    let incidentCount = 0;

    if (typeof hexagonData === 'object') {
      // Get z-score from analytics (prioritize this for accurate coloring)
      if (hexagonData.analytics && hexagonData.analytics.z_scores) {
        zScore = hexagonData.analytics.z_scores.incident || 0;
      }
      incidentCount = hexagonData.incident_count || hexagonData.incidentCount || 0;
    } else {
      // Legacy: hexagonData is just an incident count number
      incidentCount = hexagonData;
      // Fallback: estimate z-score from count
      zScore = Math.log10(Math.max(1, incidentCount));
    }

    // Color gradient based on z-score from -1 (green) to 11+ (red)
    let fillColor, strokeColor;
    let fillOpacity = 0.6;

    if (zScore >= 11.0) {
      fillColor = 'rgba(139, 0, 0, 0.95)'; // Dark red
      strokeColor = '#FF0000';
    } else if (zScore >= 10.0) {
      fillColor = 'rgba(165, 0, 0, 0.92)'; // Very dark red
      strokeColor = '#FF0000';
    } else if (zScore >= 9.0) {
      fillColor = 'rgba(220, 20, 60, 0.88)'; // Crimson
      strokeColor = '#FF1493';
    } else if (zScore >= 8.0) {
      fillColor = 'rgba(232, 37, 60, 0.85)'; // Bright crimson
      strokeColor = '#FF4444';
    } else if (zScore >= 7.0) {
      fillColor = 'rgba(255, 0, 0, 0.82)'; // Red
      strokeColor = '#FF4500';
    } else if (zScore >= 6.0) {
      fillColor = 'rgba(255, 36, 0, 0.78)'; // Scarlet
      strokeColor = '#FF5500';
    } else if (zScore >= 5.0) {
      fillColor = 'rgba(255, 69, 0, 0.75)'; // Orange-red
      strokeColor = '#FF6347';
    } else if (zScore >= 4.0) {
      fillColor = 'rgba(255, 102, 0, 0.72)'; // Red-orange
      strokeColor = '#FF7700';
    } else if (zScore >= 3.0) {
      fillColor = 'rgba(255, 140, 0, 0.68)'; // Dark orange
      strokeColor = '#FFA500';
    } else if (zScore >= 2.0) {
      fillColor = 'rgba(255, 165, 0, 0.65)'; // Orange
      strokeColor = '#FFB833';
    } else if (zScore >= 1.0) {
      fillColor = 'rgba(255, 176, 0, 0.62)'; // Yellow-orange
      strokeColor = '#FFC800';
    } else if (zScore >= 0.5) {
      fillColor = 'rgba(255, 200, 0, 0.58)'; // Gold
      strokeColor = '#FFD700';
    } else if (zScore >= 0) {
      fillColor = 'rgba(255, 215, 0, 0.55)'; // Yellow
      strokeColor = '#FFEC8B';
    } else if (zScore >= -0.5) {
      fillColor = 'rgba(173, 255, 47, 0.52)'; // Yellow-green
      strokeColor = '#90EE90';
    } else if (zScore >= -1.0) {
      fillColor = 'rgba(144, 238, 144, 0.48)'; // Light green
      strokeColor = '#7FFF7F';
    } else {
      fillColor = 'rgba(50, 205, 50, 0.45)'; // Green (very safe)
      strokeColor = '#3CB371';
    }

    return {
      fillColor,
      strokeColor,
      strokeWidth: 1,
    };
  };

  /**
   * Get crime type name from code (matching web implementation)
   */
  const getCrimeTypeName = code => {
    const crimeTypes = {
      1: 'Part I Crimes Against Person',
      2: 'Part I Crimes Against Property',
      3: 'Part II Crimes Against Person',
      4: 'Part II Crimes Against Property',
      5: 'Part II Crimes Against Society',
      6: 'Crimes Against Children',
      7: 'Other Offenses',
      8: 'Traffic',
      9: 'Unknown/Other',
    };

    if (!code) return 'Unknown';

    const codeStr = code.toString();
    for (const [key, value] of Object.entries(crimeTypes)) {
      if (codeStr.startsWith(key)) {
        return value;
      }
    }

    return 'Other';
  };

  /**
   * Convert H3 hexagon to React Native MapView polygon coordinates
   */
  const h3ToPolygonCoords = h3Index => {
    try {
      if (!h3Index) {
        DebugLogger.error('❌ No h3Index provided');
        return null;
      }

      if (!h3 || typeof h3.cellToBoundary !== 'function') {
        DebugLogger.error('❌ H3 library not available');
        return null;
      }

      // Get H3 boundary coordinates
      // When geoJson=true, h3-js returns [longitude, latitude] format
      const boundary = h3.cellToBoundary(h3Index, true);
      DebugLogger.info(
        `✅ H3 ${h3Index.substring(0, 10)}... boundary: ${boundary ? boundary.length + ' points' : 'null'}`
      );

      if (!boundary || !Array.isArray(boundary)) {
        return null;
      }

      // GeoJSON format is [lng, lat], MapView needs {latitude, longitude}
      const coords = boundary.map(coord => ({
        latitude: coord[1],
        longitude: coord[0],
      }));
      DebugLogger.info(
        `✅ Converted to ${coords.length} coordinates, first: lat=${coords[0].latitude.toFixed(4)}, lng=${coords[0].longitude.toFixed(4)}`
      );
      return coords;
    } catch (error) {
      DebugLogger.error(`❌ Failed to convert H3 to polygon: ${error.message}`);
      return null;
    }
  };

  /**
   * Convert internal filter state to API format
   * Simplified to match web implementation - only date_range parameter
   */
  const convertFiltersForAPI = filters => {
    const apiFilters = {};

    // Date preset - only filter sent to API (matching web implementation)
    if (filters.datePreset) {
      apiFilters.date_range = filters.datePreset;
    }

    return apiFilters;
  };

  /**
   * Load hexagon crime data
   */
  const loadHexagonData = async () => {
    setIsLoading(true);

    try {
      if (!drupalCrimeService) {
        console.error('❌ No Drupal crime service provided');
        Alert.alert('Error', 'Crime data service not available');
        return;
      }

      const resolution = getOptimalResolution(currentZoom);
      console.log(`\n📊 [MOBILE] Loading H3 Resolution ${resolution} data...`);
      console.log(`  Zoom: ${currentZoom}`);
      console.log(`  Map Region:`, {
        center: [mapRegion.latitude.toFixed(4), mapRegion.longitude.toFixed(4)],
        delta: [mapRegion.latitudeDelta.toFixed(4), mapRegion.longitudeDelta.toFixed(4)],
      });

      // Build bounds string for API (matching web format)
      const north = mapRegion.latitude + mapRegion.latitudeDelta / 2;
      const south = mapRegion.latitude - mapRegion.latitudeDelta / 2;
      const east = mapRegion.longitude + mapRegion.longitudeDelta / 2;
      const west = mapRegion.longitude - mapRegion.longitudeDelta / 2;
      const bounds = `${north},${east},${south},${west}`;

      console.log(
        `  Bounds: N=${north.toFixed(4)} S=${south.toFixed(4)} E=${east.toFixed(4)} W=${west.toFixed(4)}`
      );
      console.log(`  Active Filters:`, activeFilters);

      // Convert activeFilters to API format
      const apiFilters = convertFiltersForAPI(activeFilters);

      // Use Drupal crime service
      const data = await drupalCrimeService.getAggregatedData(resolution, bounds, apiFilters);

      console.log('\n📊 [MOBILE] Received data from Drupal API:');
      console.log(`  Hexagons: ${data.hexagons ? data.hexagons.length : 0}`);
      console.log(`  Resolution: ${data.meta ? data.meta.resolution : 'unknown'}`);
      if (data.hexagons && data.hexagons.length > 0) {
        console.log(`  Sample hexagon:`, data.hexagons[0]);
      }

      if (data.hexagons && data.hexagons.length > 0) {
        console.log(`✅ [MOBILE] Setting ${data.hexagons.length} hexagons for rendering`);
        setHexagons(data.hexagons);

        // Update visible incidents count
        const incidentCount = updateVisibleIncidentsCount(data.hexagons);
        console.log(`📊 Total visible incidents: ${incidentCount}`);

        // Load individual incidents for high-resolution views
        if (resolution >= 10) {
          loadIncidentPoints();
        }
      } else {
        console.log('⚠️ [MOBILE] No hexagons received - clearing display');
        setHexagons([]);
      }
    } catch (error) {
      console.error('Error loading hexagon data:', error);
      Alert.alert('Error', 'Failed to load crime data. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * Load individual incident points for high-resolution views
   */
  const loadIncidentPoints = async () => {
    try {
      if (!drupalCrimeService) {
        console.warn('⚠️ No Drupal crime service for incident points');
        return;
      }

      const north = mapRegion.latitude + mapRegion.latitudeDelta / 2;
      const south = mapRegion.latitude - mapRegion.latitudeDelta / 2;
      const east = mapRegion.longitude + mapRegion.longitudeDelta / 2;
      const west = mapRegion.longitude - mapRegion.longitudeDelta / 2;
      const bounds = `${north},${east},${south},${west}`;

      // Convert activeFilters to API format (only date_range)
      const apiFilters = convertFiltersForAPI(activeFilters);

      // Use Drupal crime service
      const data = await drupalCrimeService.getIncidents(bounds, apiFilters);

      if (data.incidents && data.incidents.length > 0) {
        setIncidents(data.incidents);
        console.log(`📍 Loaded ${data.incidents.length} individual incidents via Drupal`);
      } else {
        setIncidents([]);
      }
    } catch (error) {
      console.error('Error loading incident points:', error);
    }
  };

  /**
   * Load citywide statistics
   */
  const loadCitywideStats = async () => {
    try {
      if (!drupalCrimeService) {
        console.warn('⚠️ No Drupal crime service for citywide stats');
        return;
      }

      const data = await drupalCrimeService.getCitywideStats();
      if (data) {
        setCitywideStats(data);
        console.log('📈 Loaded citywide statistics via Drupal');
      }
    } catch (error) {
      console.error('Error loading citywide stats:', error);
    }
  };

  /**
   * Update visible incidents count based on rendered hexagons
   */
  const updateVisibleIncidentsCount = hexagonData => {
    if (!hexagonData || hexagonData.length === 0) {
      return 0;
    }

    const totalIncidents = hexagonData.reduce((sum, hex) => {
      return sum + (hex.incident_count || 0);
    }, 0);

    console.log(`📊 Visible incidents: ${totalIncidents} across ${hexagonData.length} hexagons`);
    return totalIncidents;
  };

  /**
   * Get current incident count from loaded hexagons
   */
  const getCurrentIncidentCount = () => {
    return hexagons.reduce((sum, hex) => sum + (hex.incident_count || 0), 0);
  };

  /**
   * Get active sector count (hexagons with incidents)
   */
  const getActiveSectorCount = () => {
    return hexagons.filter(hex => (hex.incident_count || 0) > 0).length;
  };

  /**
   * Reset view to initial state
   */
  const resetView = () => {
    console.log('🔄 Resetting map view...');

    // Reset map to initial location
    if (mapRef.current) {
      mapRef.current.animateToRegion(initialLocation, 1000);
    }

    // Clear filters
    setCurrentFilters({});

    // Reload data
    loadHexagonData();
  };

  /**
   * Fit map to show all hexagons
   */
  const fitMapToHexagons = () => {
    if (hexagons.length === 0 || !mapRef.current) {
      return;
    }

    // Calculate bounds from hexagons
    let minLat = Infinity,
      maxLat = -Infinity;
    let minLng = Infinity,
      maxLng = -Infinity;

    hexagons.forEach(hex => {
      if (hex.center) {
        const lat = hex.center.lat;
        const lng = hex.center.lng;
        minLat = Math.min(minLat, lat);
        maxLat = Math.max(maxLat, lat);
        minLng = Math.min(minLng, lng);
        maxLng = Math.max(maxLng, lng);
      }
    });

    if (minLat !== Infinity && maxLat !== -Infinity) {
      const centerLat = (minLat + maxLat) / 2;
      const centerLng = (minLng + maxLng) / 2;
      const latDelta = (maxLat - minLat) * 1.2; // Add 20% padding
      const lngDelta = (maxLng - minLng) * 1.2;

      mapRef.current.animateToRegion(
        {
          latitude: centerLat,
          longitude: centerLng,
          latitudeDelta: Math.max(latDelta, 0.01),
          longitudeDelta: Math.max(lngDelta, 0.01),
        },
        1000
      );

      console.log(`📍 Fitted map to ${hexagons.length} hexagons`);
    }
  };

  /**
   * Apply filters to crime data
   * Matches web implementation's applyFilters function
   */
  const applyFilters = filters => {
    console.log('🔍 ApplyFilters: New filters applied:', filters);
    setActiveFilters(filters);

    // Reload data with new filters
    loadHexagonData();
  };

  /**
   * Clear all filters and reload data
   * Matches web implementation - reset to 12 months default
   */
  const clearAllFilters = () => {
    console.log('🔍 ClearAllFilters: Resetting to 12 months default');
    const defaultFilters = {
      datePreset: '12months',
    };
    setActiveFilters(defaultFilters);
    loadHexagonData();
  };

  /**
   * Count active filters for badge display
   * Only counts if datePreset is not the default (12months)
   */
  const getActiveFilterCount = () => {
    // Show badge if date filter is not default (12 months)
    return activeFilters.datePreset !== '12months' ? 1 : 0;
  };

  /**
   * Switch visualization mode
   * Matches web implementation's switchViewMode function
   */
  const switchViewMode = mode => {
    console.log(`🔄 SwitchViewMode: Changing from ${viewMode} to ${mode}`);
    setViewMode(mode);

    // Load appropriate data for the view mode
    if (mode === 'hexagon') {
      loadHexagonData();
    } else if (mode === 'points') {
      loadIncidentPoints();
    }
    // heatmap uses hexagon data with different rendering
  };

  /**
   * Handle map region change
   */
  const onRegionChangeComplete = region => {
    try {
      setMapRegion(region);

      // Estimate zoom level from latitudeDelta
      const zoom = Math.round(Math.log(360 / region.latitudeDelta) / Math.LN2);
      setCurrentZoom(zoom);

      // Reload data for new region (matching web implementation handleMapMove)
      setTimeout(() => {
        loadHexagonData().catch(err => {
          console.error('Error loading hexagons on zoom:', err);
        });
      }, 500);
    } catch (error) {
      console.error('Error in onRegionChangeComplete:', error);
    }
  };

  /**
   * Handle hexagon press
   */
  const onHexagonPress = hexagon => {
    setSelectedHexagon(hexagon);
    setShowHexagonDetails(true);
  };

  /**
   * Create hexagon detail modal content
   */
  const renderHexagonDetails = () => {
    if (!selectedHexagon) return null;

    const incidentCount = selectedHexagon.incident_count || selectedHexagon.incidentCount || 0;
    const h3Index = selectedHexagon.h3_index || selectedHexagon.h3Index || 'Unknown';
    const h3Resolution = selectedHexagon.resolution || selectedHexagon.h3_resolution || 'Unknown';
    const riskLevel = calculateRiskLevel(incidentCount);
    const uniqueTypes = selectedHexagon.unique_incident_types || selectedHexagon.unique_types || 0;

    return (
      <Modal visible={showHexagonDetails} animationType="slide" presentationStyle="pageSheet">
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>H3:{h3Resolution} Sector Analysis</Text>
            <TouchableOpacity
              style={styles.closeButton}
              onPress={() => setShowHexagonDetails(false)}
            >
              <Text style={styles.closeButtonText}>✕</Text>
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>📊 Crime Statistics</Text>
              <View style={styles.statGrid}>
                <View style={styles.statItem}>
                  <Text style={styles.statLabel}>Total Incidents:</Text>
                  <Text style={[styles.statValue, styles[`risk${riskLevel}`]]}>
                    {incidentCount.toLocaleString()}
                  </Text>
                </View>
                <View style={styles.statItem}>
                  <Text style={styles.statLabel}>Crime Types:</Text>
                  <Text style={styles.statValue}>{uniqueTypes}</Text>
                </View>
                <View style={styles.statItem}>
                  <Text style={styles.statLabel}>Risk Level:</Text>
                  <Text style={[styles.statValue, styles[`risk${riskLevel}`]]}>{riskLevel}</Text>
                </View>
              </View>
            </View>

            <View style={styles.section}>
              <Text style={styles.sectionTitle}>🌍 Geographic Details</Text>
              <Text style={styles.infoText}>H3 Index: {h3Index}</Text>
              <Text style={styles.infoText}>
                Precision: {getResolutionDescription(h3Resolution)}
              </Text>
            </View>
          </ScrollView>
        </View>
      </Modal>
    );
  };

  // Load initial data
  useEffect(() => {
    loadHexagonData();
    loadCitywideStats();
  }, []);

  // Reload data when filters change
  useEffect(() => {
    if (currentFilters !== filters) {
      setCurrentFilters(filters);
      loadHexagonData();
    }
  }, [filters]);

  return (
    <View style={styles.container}>
      <MapView
        ref={mapRef}
        provider={PROVIDER_GOOGLE}
        style={styles.map}
        initialRegion={initialLocation}
        onRegionChangeComplete={onRegionChangeComplete}
        mapType="standard"
        showsUserLocation={true}
        showsMyLocationButton={true}
        showsCompass={true}
        showsScale={true}
      >
        {/* Render H3 Hexagons */}
        {hexagons.length > 0 &&
          DebugLogger.info(`🎨 Rendering ${hexagons.length} hexagons on map...`)}
        {hexagons.map((hexagon, index) => {
          const coords = h3ToPolygonCoords(hexagon.h3_index);
          if (!coords) {
            if (index === 0)
              DebugLogger.error(`❌ Failed to get coords for first hexagon: ${hexagon.h3_index}`);
            return null;
          }

          // Pass full hexagon object for z-score styling
          const style = calculateHexagonStyle(hexagon);

          if (index === 0) {
            DebugLogger.info(
              `🎨 First polygon: ${coords.length} coords, fill: ${style.fillColor}, stroke: ${style.strokeColor}`
            );
          }

          return (
            <Polygon
              key={`hexagon-${index}`}
              coordinates={coords}
              fillColor={style.fillColor}
              strokeColor={style.strokeColor}
              strokeWidth={style.strokeWidth}
              onPress={() => onHexagonPress(hexagon)}
            />
          );
        })}
      </MapView>

      {/* Loading Indicator */}
      {isLoading && (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color="#00ff41" />
          <Text style={styles.loadingText}>Loading Crime Data...</Text>
        </View>
      )}

      {/* Map Controls */}
      <View style={styles.mapControls}>
        <Text style={styles.zoomIndicator}>
          Zoom: {currentZoom} | H3: {getOptimalResolution(currentZoom)}
        </Text>
        <Text style={styles.hexagonCount}>
          {hexagons.length} hexagons | {getActiveSectorCount()} active
        </Text>
        <Text style={styles.incidentCount}>
          {getCurrentIncidentCount().toLocaleString()} incidents
        </Text>

        {/* Action Buttons - Moved to Bottom */}
        <View style={styles.actionButtons}>
          <TouchableOpacity style={styles.actionButton} onPress={resetView}>
            <Text style={styles.actionButtonText}>Reset</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.actionButton} onPress={fitMapToHexagons}>
            <Text style={styles.actionButtonText}>Fit View</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.actionButton, getActiveFilterCount() > 0 && styles.actionButtonActive]}
            onPress={() => setShowFilterPanel(true)}
          >
            <Text style={styles.actionButtonText}>
              Filters {getActiveFilterCount() > 0 && `(${getActiveFilterCount()})`}
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Hexagon Details Modal */}
      {renderHexagonDetails()}

      {/* Filter Panel */}
      <FilterPanel
        visible={showFilterPanel}
        onClose={() => setShowFilterPanel(false)}
        onApplyFilters={applyFilters}
        currentFilters={activeFilters}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  actionButton: {
    backgroundColor: 'rgba(0, 255, 65, 0.9)',
    borderRadius: 6,
    elevation: 3,
    flex: 1,
    paddingHorizontal: 10,
    paddingVertical: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.2,
    shadowRadius: 2,
  },
  actionButtonActive: {
    backgroundColor: 'rgba(255, 165, 0, 0.9)', // Orange for active filters
  },
  actionButtonText: {
    color: '#000000',
    fontSize: 14,
    fontWeight: 'bold',
    textAlign: 'center',
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'space-between',
    marginTop: 10,
  },
  closeButton: {
    alignItems: 'center',
    backgroundColor: '#333333',
    borderRadius: 15,
    height: 30,
    justifyContent: 'center',
    width: 30,
  },
  closeButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  container: {
    flex: 1,
  },
  hexagonCount: {
    color: '#cccccc',
    fontSize: 11,
    marginTop: 2,
    textAlign: 'center',
  },
  incidentCount: {
    color: '#cccccc',
    fontSize: 11,
    marginTop: 2,
    textAlign: 'center',
  },
  infoText: {
    color: '#ffffff',
    fontSize: 14,
    marginBottom: 5,
  },
  loadingOverlay: {
    alignItems: 'center',
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    bottom: 0,
    justifyContent: 'center',
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
  },
  loadingText: {
    color: '#00ff41',
    fontSize: 16,
    fontWeight: 'bold',
    marginTop: 10,
  },
  map: {
    flex: 1,
  },
  mapControls: {
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    borderRadius: 8,
    bottom: 20,
    left: 20,
    padding: 10,
    position: 'absolute',
    right: 20,
  },
  modalContainer: {
    backgroundColor: '#1a1a1a',
    flex: 1,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  modalHeader: {
    alignItems: 'center',
    borderBottomColor: '#333333',
    borderBottomWidth: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    padding: 20,
  },
  modalTitle: {
    color: '#00ff41',
    fontSize: 18,
    fontWeight: 'bold',
  },
  resolutionDescription: {
    color: '#ffffff',
    fontSize: 12,
    marginTop: 2,
    textAlign: 'center',
  },
  riskCRITICAL: { color: '#f44336' },
  riskHIGH: { color: '#ff9800' },
  riskLOW: { color: '#28a745' },
  riskMODERATE: { color: '#fdd835' },
  riskSAFE: { color: '#28a745' },
  section: {
    marginBottom: 20,
  },
  sectionTitle: {
    color: '#00ff41',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  statGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statItem: {
    marginBottom: 10,
    width: '48%',
  },
  statLabel: {
    color: '#cccccc',
    fontSize: 12,
  },
  statValue: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  zoomIndicator: {
    color: '#00ff41',
    fontSize: 14,
    fontWeight: 'bold',
    textAlign: 'center',
  },
});

export default InteractiveCrimeMap;
