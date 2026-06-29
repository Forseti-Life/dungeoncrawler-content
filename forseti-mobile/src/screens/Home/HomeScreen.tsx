/**
 * Home Screen - Main dashboard for Forseti Mobile Application
 */

import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  RefreshControl,
  Image,
  Linking,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

// Services
import LocationService, { Location } from '../../services/location/LocationService';
import StorageService from '../../services/storage/StorageService';

// Utils
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography, Shadows } = Theme;

interface SafetyScore {
  score: number;
  level: 'MINIMAL' | 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  description: string;
}

interface QuickStats {
  totalIncidents: number;
  recentIncidents: number;
  safetyTrend: 'improving' | 'stable' | 'declining';
}

interface HomeScreenProps {
  navigation?: any;
}

const HomeScreen: React.FC<HomeScreenProps> = ({ navigation }) => {
  const [currentLocation, setCurrentLocation] = useState<Location | null>(null);
  const [safetyScore, setSafetyScore] = useState<SafetyScore | null>(null);
  const [quickStats, setQuickStats] = useState<QuickStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);

  useEffect(() => {
    initializeHomeScreen();
  }, []);

  const initializeHomeScreen = async () => {
    try {
      setIsLoading(true);

      // Get current location
      const location = await LocationService.getCurrentLocation();
      setCurrentLocation(location);

      // Load safety data for current location
      await loadSafetyData(location);
    } catch (error) {
      console.error('Error initializing home screen:', error);
      // Don't show alert - just leave currentLocation as null
      // The UI will show "Unable to get location" message
    } finally {
      setIsLoading(false);
    }
  };

  const loadSafetyData = async (location: Location) => {
    try {
      // Mock safety score calculation - in real app, this would call the API
      const mockSafetyScore: SafetyScore = {
        score: 75,
        level: 'MEDIUM',
        description: 'Moderate safety level in your area',
      };
      setSafetyScore(mockSafetyScore);

      // Mock quick stats - in real app, this would call the API
      const mockStats: QuickStats = {
        totalIncidents: 1250,
        recentIncidents: 15,
        safetyTrend: 'stable',
      };
      setQuickStats(mockStats);
    } catch (error) {
      console.error('Error loading safety data:', error);
    }
  };

  const handleRefresh = async () => {
    setIsRefreshing(true);
    await initializeHomeScreen();
    setIsRefreshing(false);
  };

  const getSafetyColor = (level: SafetyScore['level']): string => {
    switch (level) {
      case 'MINIMAL':
        return Colors.riskMinimal;
      case 'LOW':
        return Colors.riskLow;
      case 'MEDIUM':
        return Colors.riskMedium;
      case 'HIGH':
        return Colors.riskHigh;
      case 'CRITICAL':
        return Colors.riskCritical;
      default:
        return Colors.gray;
    }
  };

  const getTrendIcon = (trend: QuickStats['safetyTrend']): string => {
    switch (trend) {
      case 'improving':
        return 'trending-up';
      case 'declining':
        return 'trending-down';
      case 'stable':
      default:
        return 'trending-neutral';
    }
  };

  const getTrendColor = (trend: QuickStats['safetyTrend']): string => {
    switch (trend) {
      case 'improving':
        return Colors.success;
      case 'declining':
        return Colors.danger;
      case 'stable':
      default:
        return Colors.info;
    }
  };

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <Icon name="loading" size={48} color={Colors.primary} />
        <Text style={styles.loadingText}>Loading safety information...</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} />}
    >
      {/* Header Section */}
      <View style={styles.header}>
        <Text style={styles.welcomeText}>Forseti Mobile</Text>
        <Text style={styles.subtitleText}>AI-Powered Safety Monitoring for Philadelphia</Text>
      </View>

      {/* Quick Actions */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="flash" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Quick Actions</Text>
        </View>
        <View style={styles.cardContent}>
          <View style={styles.actionsGrid}>
            <TouchableOpacity
              style={styles.actionButton}
              onPress={() => navigation?.navigate('Map')}
            >
              <Image
                source={require('../../../assets/images/forseti_connected.png')}
                style={styles.actionIcon}
                resizeMode="contain"
              />
              <Text style={styles.actionText}>View Map</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.actionButton}
              onPress={() => navigation?.navigate('Settings')}
            >
              <Image
                source={require('../../../assets/images/forseti_safe.png')}
                style={styles.actionIcon}
                resizeMode="contain"
              />
              <Text style={styles.actionText}>Settings</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.actionButton}
              onPress={() => Linking.openURL('https://forseti.life')}
            >
              <Image
                source={require('../../../assets/images/forseti_whole.png')}
                style={styles.actionIcon}
                resizeMode="contain"
              />
              <Text style={styles.actionText}>Community</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.actionButton}
              onPress={() => Linking.openURL('https://forseti.life/talk-with-forseti')}
            >
              <Image
                source={require('../../../assets/images/forseti_capable.png')}
                style={styles.actionIcon}
                resizeMode="contain"
              />
              <Text style={styles.actionText}>Report Issue</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>

      {/* About Forseti Card */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="information" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>About Forseti</Text>
        </View>
        <View style={styles.cardContent}>
          <Text style={styles.aboutText}>
            Forseti uses advanced AI and geospatial technology to help keep Philadelphia safe. Get
            real-time safety alerts based on your location and historical crime data.
          </Text>
          <TouchableOpacity
            style={styles.learnMoreButton}
            onPress={() => Linking.openURL('https://forseti.life/about')}
          >
            <Text style={styles.learnMoreText}>Learn More</Text>
            <Icon name="arrow-right" size={16} color={Colors.primary} />
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  aboutText: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    lineHeight: 20,
    marginBottom: Spacing.md,
  },
  accuracyText: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    marginTop: Spacing.xs,
  },
  actionButton: {
    alignItems: 'center',
    backgroundColor: Colors.lightGray,
    borderRadius: Spacing.borderRadius.md,
    marginBottom: Spacing.md,
    padding: Spacing.md,
    width: '48%',
  },
  actionIcon: {
    height: 32,
    width: 32,
  },
  actionText: {
    ...Typography.bodySmall,
    color: Colors.textPrimary,
    marginTop: Spacing.sm,
    textAlign: 'center',
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  card: {
    backgroundColor: Colors.white,
    borderRadius: Spacing.borderRadius.lg,
    margin: Spacing.md,
    padding: Spacing.md,
    ...Shadows.md,
  },
  cardContent: {
    paddingLeft: Spacing.xl,
  },
  cardHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    marginBottom: Spacing.md,
  },
  cardTitle: {
    ...Typography.heading4,
    color: Colors.textPrimary,
    marginLeft: Spacing.sm,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  errorText: {
    ...Typography.body,
    color: Colors.danger,
    fontStyle: 'italic',
  },
  header: {
    alignItems: 'center',
    backgroundColor: Colors.primary,
    padding: Spacing.lg,
  },
  learnMoreButton: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    paddingVertical: Spacing.sm,
  },
  learnMoreText: {
    color: Colors.primary,
    fontSize: 15,
    fontWeight: Typography.fontWeight.semibold,
    marginRight: Spacing.xs,
  },
  loadingContainer: {
    alignItems: 'center',
    backgroundColor: Colors.background,
    flex: 1,
    justifyContent: 'center',
  },
  loadingText: {
    marginTop: Spacing.md,
    ...Typography.body,
    color: Colors.textSecondary,
  },
  locationText: {
    ...Typography.body,
    color: Colors.textPrimary,
    fontFamily: 'monospace',
  },
  safetyDescription: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
  },
  safetyLevel: {
    ...Typography.heading4,
    marginBottom: Spacing.xs,
  },
  safetyScoreContainer: {
    alignItems: 'center',
    flexDirection: 'row',
  },
  safetyScoreDetails: {
    flex: 1,
  },
  safetyScoreNumber: {
    fontSize: 48,
    fontWeight: Typography.fontWeight.bold,
    marginRight: Spacing.md,
  },
  statItem: {
    alignItems: 'center',
  },
  statLabel: {
    ...Typography.caption,
    color: Colors.textSecondary,
    marginTop: Spacing.xs,
    textAlign: 'center',
  },
  statNumber: {
    ...Typography.xl,
    color: Colors.textPrimary,
    fontWeight: Typography.fontWeight.bold,
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
  },
  subtitleText: {
    ...Typography.body,
    color: Colors.white,
    opacity: 0.9,
  },
  welcomeText: {
    ...Typography.heading2,
    color: Colors.white,
    marginBottom: Spacing.xs,
  },
});

export default HomeScreen;
