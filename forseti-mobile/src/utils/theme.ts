/**
 * Forseti Mobile Application Theme
 * Central export for all design tokens: colors, spacing, typography, shadows
 *
 * Usage:
 * import { Theme } from '../utils/theme';
 *
 * style={{
 *   color: Theme.colors.primary,
 *   padding: Theme.spacing.md,
 *   fontSize: Theme.typography.fontSize.lg,
 *   ...Theme.shadows.md
 * }}
 */

import { Colors } from './colors';
import { Spacing } from './spacing';
import { Typography } from './typography';
import { Shadows } from './shadows';

export const Theme = {
  Colors: Colors,
  Spacing: Spacing,
  Typography: Typography,
  Shadows: Shadows,
};

// Also export with lowercase for flexibility
export const theme = {
  colors: Colors,
  spacing: Spacing,
  typography: Typography,
  shadows: Shadows,
};

// Export individual modules for backward compatibility
export { Colors, Spacing, Typography, Shadows };
