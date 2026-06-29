/**
 * Loading State Components
 * 
 * DESIGN REFERENCE:
 * - docs/product/design/02-wireframes.md (Lines 693-730)
 * - docs/product/design/01-sitemap-navigation.md (Loading states section)
 * 
 * IMPLEMENTATION DETAILS:
 * - Skeleton screens for loading states
 * - Spinner for inline loading
 * - Progress indicators
 * - Shimmer effect for better UX
 * 
 * ACCESSIBILITY:
 * - Screen reader announces loading state
 * - accessibilityRole="progressbar"
 * - See docs/product/design/05-accessibility-checklist.md (Lines 490-510)
 */

import React from 'react';
import {
  View,
  Text,
  ActivityIndicator,
  StyleSheet,
  TouchableOpacity,
} from 'react-native';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography } = Theme;

/**
 * Skeleton Screen Component
 * 
 * Renders placeholder blocks matching content layout with shimmer effect
 * for better perceived performance during loading states.
 */
export const SkeletonScreen: React.FC = () => {
  return (
    <View
      style={styles.container}
      accessible={true}
      accessibilityRole="progressbar"
      accessibilityLabel="Loading content"
    >
      <View style={[styles.block, styles.blockHeader]} />
      <View style={[styles.block, styles.blockContent]} />
      <View style={[styles.block, styles.blockFooter]} />
    </View>
  );
};

/**
 * Loading Spinner Component
 * 
 * Shows activity indicator with brand color and optional message
 */
interface LoadingSpinnerProps {
  message?: string;
  size?: 'small' | 'large';
}

export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  message = 'Loading...',
  size = 'large',
}) => {
  return (
    <View style={styles.spinnerContainer}>
      <ActivityIndicator
        size={size}
        color={Colors.primary || '#00d4ff'}
        accessibilityLabel={message}
      />
      {message && (
        <Text style={styles.spinnerMessage}>{message}</Text>
      )}
    </View>
  );
};

/**
 * Empty State Component
 * 
 * Displays icon, message, and optional action button for empty states
 * 
 * DESIGN REFERENCE:
 * - docs/product/design/02-wireframes.md (Lines 731-766)
 */
interface EmptyStateProps {
  icon: string;
  message: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  icon,
  message,
  description,
  actionLabel,
  onAction,
}) => {
  return (
    <View style={styles.emptyContainer}>
      <Text style={styles.emptyIcon}>{icon}</Text>
      <Text style={styles.emptyMessage}>{message}</Text>
      {description && (
        <Text style={styles.emptyDescription}>{description}</Text>
      )}
      {actionLabel && onAction && (
        <TouchableOpacity
          style={styles.emptyAction}
          onPress={onAction}
          accessibilityRole="button"
          accessibilityLabel={actionLabel}
        >
          <Text style={styles.emptyActionText}>{actionLabel}</Text>
        </TouchableOpacity>
      )}
    </View>
  );
};

/**
 * Error State Component
 * 
 * Displays error icon, message, and retry action
 * 
 * DESIGN REFERENCE:
 * - docs/product/design/02-wireframes.md (Lines 767-802)
 * - docs/product/design/05-accessibility-checklist.md (Lines 468-489)
 */
interface ErrorStateProps {
  message: string;
  onRetry?: () => void;
}

export const ErrorState: React.FC<ErrorStateProps> = ({
  message,
  onRetry,
}) => {
  return (
    <View style={styles.errorContainer}>
      <Text style={styles.errorIcon}>⚠️</Text>
      <Text style={styles.errorMessage}>{message}</Text>
      {onRetry && (
        <TouchableOpacity
          style={styles.errorRetry}
          onPress={onRetry}
          accessibilityRole="button"
          accessibilityLabel="Retry loading"
        >
          <Text style={styles.errorRetryText}>Try Again</Text>
        </TouchableOpacity>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  // Skeleton Screen Styles
  container: {
    padding: Spacing.md || 16,
    backgroundColor: Colors.background || '#1a1a2e',
  },
  block: {
    backgroundColor: Colors.card || '#16213e',
    borderRadius: 8,
    marginBottom: Spacing.md || 16,
  },
  blockHeader: {
    height: 60,
  },
  blockContent: {
    height: 200,
  },
  blockFooter: {
    height: 40,
  },

  // Loading Spinner Styles
  spinnerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: Spacing.lg || 24,
  },
  spinnerMessage: {
    marginTop: Spacing.md || 16,
    color: Colors.text || '#ffffff',
    fontSize: Typography.size.base || 16,
    fontFamily: Typography.family.regular,
  },

  // Empty State Styles
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: Spacing.xl || 32,
  },
  emptyIcon: {
    fontSize: 64,
    marginBottom: Spacing.md || 16,
  },
  emptyMessage: {
    color: Colors.text || '#ffffff',
    fontSize: Typography.size.lg || 18,
    fontWeight: Typography.weight.semibold || '600',
    textAlign: 'center',
    marginBottom: Spacing.sm || 8,
  },
  emptyDescription: {
    color: Colors.textSecondary || '#94a3b8',
    fontSize: Typography.size.base || 16,
    textAlign: 'center',
    marginBottom: Spacing.lg || 24,
    lineHeight: 24,
  },
  emptyAction: {
    backgroundColor: Colors.primary || '#00d4ff',
    paddingHorizontal: Spacing.lg || 24,
    paddingVertical: Spacing.md || 16,
    borderRadius: 8,
    marginTop: Spacing.md || 16,
  },
  emptyActionText: {
    color: Colors.background || '#1a1a2e',
    fontSize: Typography.size.base || 16,
    fontWeight: Typography.weight.semibold || '600',
  },

  // Error State Styles
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: Spacing.xl || 32,
  },
  errorIcon: {
    fontSize: 64,
    marginBottom: Spacing.md || 16,
  },
  errorMessage: {
    color: Colors.danger || '#ef4444',
    fontSize: Typography.size.base || 16,
    textAlign: 'center',
    marginBottom: Spacing.lg || 24,
    lineHeight: 24,
  },
  errorRetry: {
    backgroundColor: Colors.primary || '#00d4ff',
    paddingHorizontal: Spacing.lg || 24,
    paddingVertical: Spacing.md || 16,
    borderRadius: 8,
  },
  errorRetryText: {
    color: Colors.background || '#1a1a2e',
    fontSize: Typography.size.base || 16,
    fontWeight: Typography.weight.semibold || '600',
  },
});

export default {
  SkeletonScreen,
  LoadingSpinner,
  EmptyState,
  ErrorState,
};
