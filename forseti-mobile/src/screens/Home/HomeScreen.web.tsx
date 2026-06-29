/**
 * Home Screen - Web Version
 * Simplified dashboard optimized for web preview
 */

import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Image } from 'react-native';
import Icon from '../../components/Icon.web';
import { Colors } from '../../utils/colors';

interface HomeScreenProps {
  navigation?: {
    navigate: (tab: string) => void;
  };
}

const HomeScreen: React.FC<HomeScreenProps> = ({ navigation }) => {
  console.log('🏠 HomeScreen.web.tsx rendering...');

  // TODO: Replace with actual location-based safety data from API
  const [safetyScore] = useState<number | null>(null); // null = no data available
  const location = null; // Will be populated when location services are integrated

  console.log('HomeScreen state:', { safetyScore, location });

  const getSafetyColor = () => {
    if (safetyScore >= 80) return '#4CAF50';
    if (safetyScore >= 60) return '#FF9800';
    return '#F44336';
  };

  const quickActions = [
    { icon: 'map', label: 'View Map', color: Colors.primary, type: 'icon', navigateTo: 'map' },
    {
      icon: require('../../../assets/images/forseti_chat.png'),
      label: 'AI Chat',
      color: '#9C27B0',
      type: 'image',
      navigateTo: 'chat',
    },
    { icon: 'alert', label: 'Report Incident', color: '#F44336', type: 'icon', navigateTo: 'chat' },
    {
      icon: require('../../../assets/images/forseti_safe.png'),
      label: 'Safety Tips',
      color: '#4CAF50',
      type: 'image',
      navigateTo: 'safety',
    },
  ];

  console.log('HomeScreen about to render, Colors:', Colors);
  console.log('quickActions:', quickActions);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ paddingBottom: 100, minHeight: '100%' }}
    >
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.welcomeText}>Welcome to Forseti</Text>
        <Text style={styles.subtitleText}>AI-Powered Safety Monitoring</Text>
      </View>

      {/* Safety Score */}
      <View style={[styles.card, styles.safetyCard]}>
        <Text style={styles.cardTitle}>Safety Score</Text>
        <View style={styles.scoreContainer}>
          <View style={styles.scoreDetails}>
            <Text style={styles.scoreLevel}>Not within service area</Text>
            <Text style={styles.scoreDescription}>
              Safety data is currently available for select metropolitan areas.
              {'\n'}Check back soon as we expand coverage.
            </Text>
          </View>
        </View>
      </View>

      {/* Quick Actions */}
      <View style={styles.card}>
        <View style={styles.actionsGrid}>
          {quickActions.map((action, index) => (
            <TouchableOpacity
              key={index}
              style={styles.actionButton}
              onPress={() => {
                console.log(`${action.label} pressed`);
                if (navigation && action.navigateTo) {
                  navigation.navigate(action.navigateTo);
                }
              }}
            >
              <View style={[styles.actionIcon, { backgroundColor: action.color + '20' }]}>
                {action.type === 'image' ? (
                  <Image source={action.icon} style={styles.actionIconImage} resizeMode="contain" />
                ) : (
                  <Icon name={action.icon} size={24} color={action.color} />
                )}
              </View>
              <Text style={styles.actionLabel}>{action.label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      <View style={styles.footer}>
        <Text style={styles.footerText}>
          Powered by H3 Geospatial Intelligence • Updated 5 min ago
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  actionButton: {
    alignItems: 'center',
    marginBottom: 16,
    width: '48%',
  },
  actionIcon: {
    alignItems: 'center',
    borderRadius: 28,
    height: 56,
    justifyContent: 'center',
    marginBottom: 8,
    width: 56,
  },
  actionIconImage: {
    width: 32,
    height: 32,
  },
  actionLabel: {
    color: Colors.text,
    fontSize: 12,
    textAlign: 'center',
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    marginTop: 12,
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 12,
    margin: 12,
    padding: 16,
  },
  cardHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    marginBottom: 12,
  },
  cardTitle: {
    color: Colors.text,
    fontSize: 16,
    fontWeight: 'bold',
    marginLeft: 8,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
    height: '100%',
    width: '100%',
  },
  footer: {
    alignItems: 'center',
    padding: 20,
  },
  footerText: {
    color: Colors.textSecondary,
    fontSize: 11,
    textAlign: 'center',
  },
  header: {
    backgroundColor: Colors.primary,
    minHeight: 80,
    padding: 20,
    paddingTop: 16,
  },
  incidentDetails: {
    color: Colors.textSecondary,
    fontSize: 12,
  },
  incidentInfo: {
    flex: 1,
  },
  incidentRow: {
    alignItems: 'center',
    borderBottomColor: Colors.border,
    borderBottomWidth: 1,
    flexDirection: 'row',
    paddingVertical: 12,
  },
  incidentType: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 2,
  },
  locationSubtext: {
    color: Colors.textSecondary,
    fontSize: 12,
  },
  locationText: {
    color: Colors.text,
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 4,
  },
  safetyCard: {
    alignItems: 'center',
  },
  scoreCircle: {
    alignItems: 'center',
    borderRadius: 40,
    borderWidth: 4,
    height: 80,
    justifyContent: 'center',
    marginRight: 16,
    width: 80,
  },
  scoreContainer: {
    alignItems: 'center',
    flexDirection: 'row',
    marginTop: 12,
  },
  scoreDescription: {
    color: Colors.textSecondary,
    fontSize: 14,
  },
  scoreDetails: {
    flex: 1,
  },
  scoreLevel: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  scoreNumber: {
    fontSize: 32,
    fontWeight: 'bold',
  },
  severityDot: {
    borderRadius: 5,
    height: 10,
    marginRight: 12,
    width: 10,
  },
  statBox: {
    alignItems: 'center',
    backgroundColor: Colors.surface,
    borderRadius: 12,
    flex: 1,
    marginHorizontal: 4,
    padding: 16,
  },
  statLabel: {
    color: Colors.textSecondary,
    fontSize: 12,
    textAlign: 'center',
  },
  statNumber: {
    color: Colors.primary,
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  statsContainer: {
    flexDirection: 'row',
    marginBottom: 12,
    marginHorizontal: 12,
  },
  subtitleText: {
    color: '#FFFFFF',
    fontSize: 14,
    opacity: 0.9,
  },
  tipText: {
    color: Colors.text,
    fontSize: 14,
    lineHeight: 20,
    marginTop: 8,
  },
  viewAllButton: {
    alignItems: 'center',
    marginTop: 12,
    paddingVertical: 8,
  },
  viewAllText: {
    color: Colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  welcomeText: {
    color: '#FFFFFF',
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 4,
  },
});

export default HomeScreen;
