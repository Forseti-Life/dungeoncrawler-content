/**
 * Alert Detail Modal Component
 * 
 * DESIGN REFERENCE:
 * - docs/product/design/02-wireframes.md (Lines 181-235)
 * - docs/product/design/03-user-flows.md (Lines 411-503)
 * 
 * IMPLEMENTATION DETAILS:
 * - Modal overlay showing alert details
 * - Risk level with color coding
 * - Location information
 * - Timestamp of alert
 * - Action buttons (View on Map, Dismiss)
 * - Swipe down to dismiss (mobile)
 * 
 * ACCESSIBILITY:
 * - Focus trap within modal
 * - Escape key closes modal
 * - Screen reader announces modal content
 * - See docs/product/design/05-accessibility-checklist.md (Lines 380-410)
 */

import React from 'react';
import {
  Modal,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography, Shadows } = Theme;

interface Alert {
  id: string;
  severity: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  location: {
    lat: number;
    lng: number;
    address?: string;
  };
  zScore: number;
  timestamp: Date;
  crimeCount: number;
}

interface AlertDetailModalProps {
  visible: boolean;
  alert: Alert | null;
  onClose: () => void;
  onViewOnMap?: (alert: Alert) => void;
}

interface SeverityConfig {
  color: string;
  icon: string;
  label: string;
}

/**
 * Get severity configuration based on alert level
 */
const getSeverityConfig = (severity: string): SeverityConfig => {
  switch (severity) {
    case 'CRITICAL':
      return { color: '#ef4444', icon: 'alert-circle', label: 'Critical' };
    case 'HIGH':
      return { color: '#f97316', icon: 'alert', label: 'High Risk' };
    case 'MEDIUM':
      return { color: '#eab308', icon: 'alert-outline', label: 'Medium' };
    default:
      return { color: '#22c55e', icon: 'information', label: 'Low' };
  }
};

/**
 * AlertDetailModal Component
 * 
 * Modal overlay displaying detailed information about a safety alert
 * with action buttons to view on map or dismiss.
 */
export const AlertDetailModal: React.FC<AlertDetailModalProps> = ({
  visible,
  alert,
  onClose,
  onViewOnMap,
}) => {
  if (!alert) return null;

  const severityConfig = getSeverityConfig(alert.severity);

  return (
    <Modal
      visible={visible}
      transparent={true}
      animationType="slide"
      onRequestClose={onClose}
      accessibilityViewIsModal={true}
    >
      <TouchableOpacity
        style={styles.overlay}
        activeOpacity={1}
        onPress={onClose}
      >
        <View style={styles.modalContent} onStartShouldSetResponder={() => true}>
          {/* Header */}
          <View style={styles.header}>
            <Text style={styles.headerTitle}>Alert Details</Text>
            <TouchableOpacity
              onPress={onClose}
              accessibilityLabel="Close alert details"
              accessibilityRole="button"
              hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            >
              <Icon name="close" size={24} color={Colors.text || '#ffffff'} />
            </TouchableOpacity>
          </View>

          {/* Content */}
          <ScrollView style={styles.content}>
            {/* Severity Indicator */}
            <View style={styles.severityContainer}>
              <Icon
                name={severityConfig.icon}
                size={48}
                color={severityConfig.color}
              />
              <Text style={[styles.severityLabel, { color: severityConfig.color }]}>
                {severityConfig.label}
              </Text>
            </View>

            {/* Alert Details */}
            <View style={styles.detailRow}>
              <Icon name="chart-line" size={20} color={Colors.textSecondary || '#94a3b8'} />
              <Text style={styles.detailLabel}>Risk Score:</Text>
              <Text style={styles.detailValue}>Z: {alert.zScore.toFixed(1)}</Text>
            </View>

            <View style={styles.detailRow}>
              <Icon name="map-marker" size={20} color={Colors.textSecondary || '#94a3b8'} />
              <Text style={styles.detailLabel}>Location:</Text>
              <Text style={styles.detailValue}>
                {alert.location.address || `${alert.location.lat.toFixed(4)}, ${alert.location.lng.toFixed(4)}`}
              </Text>
            </View>

            <View style={styles.detailRow}>
              <Icon name="clock-outline" size={20} color={Colors.textSecondary || '#94a3b8'} />
              <Text style={styles.detailLabel}>Time:</Text>
              <Text style={styles.detailValue}>
                {alert.timestamp.toLocaleString()}
              </Text>
            </View>

            <View style={styles.detailRow}>
              <Icon name="alert-circle-outline" size={20} color={Colors.textSecondary || '#94a3b8'} />
              <Text style={styles.detailLabel}>Nearby Crimes:</Text>
              <Text style={styles.detailValue}>{alert.crimeCount}</Text>
            </View>
          </ScrollView>

          {/* Action Buttons */}
          <View style={styles.actions}>
            {onViewOnMap && (
              <TouchableOpacity
                style={[styles.button, styles.buttonPrimary]}
                onPress={() => {
                  onViewOnMap(alert);
                  onClose();
                }}
                accessibilityLabel="View alert on map"
                accessibilityRole="button"
              >
                <Icon name="map" size={20} color={Colors.background || '#1a1a2e'} />
                <Text style={styles.buttonPrimaryText}>View on Map</Text>
              </TouchableOpacity>
            )}

            <TouchableOpacity
              style={[styles.button, styles.buttonSecondary]}
              onPress={onClose}
              accessibilityLabel="Dismiss alert"
              accessibilityRole="button"
            >
              <Text style={styles.buttonSecondaryText}>Dismiss</Text>
            </TouchableOpacity>
          </View>
        </View>
      </TouchableOpacity>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: Colors.card || '#16213e',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '80%',
    ...Shadows.lg,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: Spacing.md || 16,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border || '#2a3f5f',
  },
  headerTitle: {
    fontSize: Typography.size.lg || 18,
    fontWeight: Typography.weight.bold || '700',
    color: Colors.text || '#ffffff',
    fontFamily: Typography.family.bold,
  },
  content: {
    padding: Spacing.md || 16,
  },
  severityContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: Spacing.lg || 24,
    paddingVertical: Spacing.md || 16,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border || '#2a3f5f',
  },
  severityLabel: {
    fontSize: Typography.size.xl || 20,
    fontWeight: Typography.weight.bold || '700',
    marginLeft: Spacing.md || 16,
    fontFamily: Typography.family.bold,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: Spacing.md || 16,
  },
  detailLabel: {
    color: Colors.textSecondary || '#94a3b8',
    fontSize: Typography.size.sm || 14,
    marginLeft: Spacing.sm || 8,
    marginRight: Spacing.xs || 4,
    fontFamily: Typography.family.regular,
  },
  detailValue: {
    color: Colors.text || '#ffffff',
    fontSize: Typography.size.sm || 14,
    flex: 1,
    fontFamily: Typography.family.medium,
  },
  actions: {
    padding: Spacing.md || 16,
    borderTopWidth: 1,
    borderTopColor: Colors.border || '#2a3f5f',
  },
  button: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: Spacing.md || 16,
    borderRadius: 8,
    marginBottom: Spacing.sm || 8,
  },
  buttonPrimary: {
    backgroundColor: Colors.primary || '#00d4ff',
  },
  buttonPrimaryText: {
    color: Colors.background || '#1a1a2e',
    fontSize: Typography.size.base || 16,
    fontWeight: Typography.weight.semibold || '600',
    marginLeft: Spacing.sm || 8,
    fontFamily: Typography.family.semibold,
  },
  buttonSecondary: {
    backgroundColor: 'transparent',
    borderWidth: 1,
    borderColor: Colors.border || '#2a3f5f',
  },
  buttonSecondaryText: {
    color: Colors.text || '#ffffff',
    fontSize: Typography.size.base || 16,
    fontWeight: Typography.weight.semibold || '600',
    fontFamily: Typography.family.semibold,
  },
});

export default AlertDetailModal;
