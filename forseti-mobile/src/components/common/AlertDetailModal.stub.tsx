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
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

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

/**
 * AlertDetailModal Component
 * 
 * PSEUDOCODE:
 * 1. Render modal overlay (semi-transparent background)
 * 2. Show modal content card:
 *    - Header with close button (X)
 *    - Alert severity icon and color
 *    - Risk level (Z-score)
 *    - Location address
 *    - Timestamp
 *    - Crime count in area
 * 3. Action buttons:
 *    - [View on Map] - navigates to map centered on alert
 *    - [Dismiss] - closes modal
 * 4. Dismiss options:
 *    - Tap X button
 *    - Tap outside modal (background)
 *    - Swipe down (mobile gesture)
 *    - Press Escape key (web)
 * 5. Accessibility:
 *    - Focus trapped in modal
 *    - Screen reader announces modal role
 *    - Clear labels for all actions
 * 
 * STYLING:
 * - Modal slides up from bottom (mobile)
 * - Modal fades in center (web/tablet)
 * - Responsive sizing based on device
 * - See docs/product/design/04-mobile-first-approach.md (Lines 545-590)
 */
export const AlertDetailModal: React.FC<AlertDetailModalProps> = ({
  visible,
  alert,
  onClose,
  onViewOnMap,
}) => {
  // TODO: Implement alert detail modal
  // Reference: docs/product/design/03-user-flows.md (Lines 411-503)
  
  if (!alert) return null;
  
  return null; // STUB
  
  /* IMPLEMENTATION PLAN:
  
  const getSeverityConfig = (severity: string) => {
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
          <View style={styles.header}>
            <Text style={styles.headerTitle}>Alert Details</Text>
            <TouchableOpacity
              onPress={onClose}
              accessibilityLabel="Close alert details"
              accessibilityRole="button"
            >
              <Icon name="close" size={24} color="#ffffff" />
            </TouchableOpacity>
          </View>
          
          <View style={styles.content}>
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
            
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Risk Score:</Text>
              <Text style={styles.detailValue}>Z: {alert.zScore.toFixed(1)}</Text>
            </View>
            
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Location:</Text>
              <Text style={styles.detailValue}>
                {alert.location.address || 'Current Location'}
              </Text>
            </View>
            
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Time:</Text>
              <Text style={styles.detailValue}>
                {alert.timestamp.toLocaleString()}
              </Text>
            </View>
            
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Nearby Crimes:</Text>
              <Text style={styles.detailValue}>{alert.crimeCount}</Text>
            </View>
          </View>
          
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
                <Icon name="map" size={20} color="#ffffff" />
                <Text style={styles.buttonText}>View on Map</Text>
              </TouchableOpacity>
            )}
            
            <TouchableOpacity
              style={[styles.button, styles.buttonSecondary]}
              onPress={onClose}
              accessibilityLabel="Dismiss alert"
              accessibilityRole="button"
            >
              <Text style={styles.buttonText}>Dismiss</Text>
            </TouchableOpacity>
          </View>
        </View>
      </TouchableOpacity>
    </Modal>
  );
  
  */
};

export default AlertDetailModal;
