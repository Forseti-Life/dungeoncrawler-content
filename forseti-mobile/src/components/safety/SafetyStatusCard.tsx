/**
 * Safety Status Card Component
 * 
 * DESIGN REFERENCE:
 * - docs/product/design/02-wireframes.md (Lines 15-62, Lines 280-350)
 * - docs/product/design/01-sitemap-navigation.md (Lines 43-50)
 * 
 * IMPLEMENTATION DETAILS:
 * - Displays current location safety status
 * - Color-coded risk levels (Green, Yellow, Orange, Red)
 * - Z-score with explanation
 * - Last updated timestamp
 * - Tap to view details
 * 
 * RISK LEVELS:
 * - Safe (Green): Z-score < 1.0
 * - Medium (Yellow): Z-score 1.0-2.0
 * - Elevated (Orange): Z-score 2.0-3.0
 * - High Risk (Red): Z-score > 3.0
 * 
 * ACCESSIBILITY:
 * - Screen reader announces risk level
 * - Color + icon + text (not color alone)
 * - Minimum contrast ratio 4.5:1
 * - See docs/product/design/05-accessibility-checklist.md (Lines 93-132)
 */

import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography, Shadows } = Theme;

interface Location {
  lat: number;
  lng: number;
  address?: string;
}

interface SafetyStatusCardProps {
  location: Location;
  zScore: number;
  lastUpdated: Date;
  onPress?: () => void;
}

interface RiskLevel {
  level: string;
  color: string;
  icon: string;
}

/**
 * Calculate risk level from Z-score based on design specifications
 */
const getRiskLevel = (score: number): RiskLevel => {
  if (score < 1.0) {
    return { level: 'Safe', color: '#22c55e', icon: 'shield-check' };
  }
  if (score < 2.0) {
    return { level: 'Medium', color: '#eab308', icon: 'shield-alert' };
  }
  if (score < 3.0) {
    return { level: 'Elevated', color: '#f97316', icon: 'shield-alert' };
  }
  return { level: 'High Risk', color: '#ef4444', icon: 'shield-alert' };
};

/**
 * Format time ago string for last updated timestamp
 */
const formatTimeAgo = (date: Date): string => {
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
  
  if (seconds < 60) return 'Just now';
  if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
  return `${Math.floor(seconds / 86400)} days ago`;
};

/**
 * SafetyStatusCard Component
 * 
 * Displays current location safety status with color-coded risk level,
 * Z-score, and last updated timestamp.
 */
export const SafetyStatusCard: React.FC<SafetyStatusCardProps> = ({
  location,
  zScore,
  lastUpdated,
  onPress,
}) => {
  const risk = getRiskLevel(zScore);
  const timeAgo = formatTimeAgo(lastUpdated);

  return (
    <TouchableOpacity
      style={[styles.card, { borderLeftColor: risk.color }, Shadows.md]}
      onPress={onPress}
      accessible={true}
      accessibilityRole="button"
      accessibilityLabel={`Safety status: ${risk.level} risk, Z-score ${zScore.toFixed(1)}`}
      accessibilityHint="Double tap to view details"
      activeOpacity={0.7}
    >
      <View style={styles.header}>
        <Icon name="map-marker" size={16} color={Colors.textSecondary || '#94a3b8'} />
        <Text style={styles.locationText} numberOfLines={1} ellipsizeMode="tail">
          {location.address || `${location.lat.toFixed(4)}, ${location.lng.toFixed(4)}`}
        </Text>
      </View>
      
      <View style={styles.statusContainer}>
        <Icon name={risk.icon} size={48} color={risk.color} />
        <View style={styles.statusInfo}>
          <Text style={[styles.statusLevel, { color: risk.color }]}>
            {risk.level}
          </Text>
          <Text style={styles.zScore}>
            Z-Score: {zScore.toFixed(1)}
          </Text>
        </View>
      </View>
      
      <View style={styles.footer}>
        <Text style={styles.lastUpdated}>
          Last Updated: {timeAgo}
        </Text>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: Colors.card || '#16213e',
    borderRadius: 12,
    borderLeftWidth: 4,
    padding: Spacing.md || 16,
    marginBottom: Spacing.md || 16,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: Spacing.sm || 8,
  },
  locationText: {
    flex: 1,
    marginLeft: Spacing.xs || 4,
    color: Colors.textSecondary || '#94a3b8',
    fontSize: Typography.size.sm || 14,
    fontFamily: Typography.family.regular,
  },
  statusContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: Spacing.md || 16,
  },
  statusInfo: {
    marginLeft: Spacing.md || 16,
    flex: 1,
  },
  statusLevel: {
    fontSize: Typography.size.xl || 20,
    fontWeight: Typography.weight.bold || '700',
    fontFamily: Typography.family.bold,
    marginBottom: Spacing.xs || 4,
  },
  zScore: {
    color: Colors.text || '#ffffff',
    fontSize: Typography.size.base || 16,
    fontFamily: Typography.family.regular,
  },
  footer: {
    borderTopWidth: 1,
    borderTopColor: Colors.border || '#2a3f5f',
    paddingTop: Spacing.sm || 8,
  },
  lastUpdated: {
    color: Colors.textSecondary || '#94a3b8',
    fontSize: Typography.size.sm || 14,
    fontFamily: Typography.family.regular,
  },
});

export default SafetyStatusCard;
