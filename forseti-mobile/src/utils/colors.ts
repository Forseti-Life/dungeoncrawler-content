/**
 * Color palette for Forseti Mobile Application
 * Consistent with Forseti branding and web application
 *
 * Brand colors sourced from: sites/forseti/web/themes/custom/forseti/images/logos/README.md
 */

export const Colors = {
  // Primary colors - Forseti brand cyan
  primary: '#00d4ff',
  primaryDark: '#00a0cc',
  primaryLight: '#33dcff',

  // Secondary colors - Forseti brand dark blue
  secondary: '#16213e',
  secondaryDark: '#0f1829',
  secondaryLight: '#1a1a2e',

  // Status colors
  success: '#28a745',
  warning: '#fdd835',
  danger: '#f44336',
  info: '#00d4ff',

  // Safety risk colors (matching Forseti palette)
  riskCritical: '#f44336',
  riskHigh: '#ff9800',
  riskMedium: '#fdd835',
  riskLow: '#28a745',
  riskMinimal: '#6c757d',

  // Neutral colors
  white: '#ffffff',
  black: '#000000',
  gray: '#6c757d',
  lightGray: '#e9ecef',
  darkGray: '#1a1a2e',

  // Background colors - Forseti dark theme
  background: '#1a1a2e',
  backgroundDark: '#16213e',
  lighter: '#ffffff',
  darker: '#000000',

  // Text colors
  text: '#ffffff',
  textPrimary: '#ffffff',
  textSecondary: '#adb5bd',
  textMuted: '#adb5bd',
  textLight: '#ffffff',

  // UI element colors
  card: '#16213e',
  surface: '#16213e',
  border: '#2a3f5f',
  cyan: '#00d4ff',

  // Map colors - Forseti branded
  mapBackground: '#1a1a2e',
  hexagonFill: 'rgba(0, 212, 255, 0.3)',
  hexagonStroke: '#00d4ff',

  // Chart colors - Forseti palette
  chartPrimary: '#00d4ff',
  chartSecondary: '#16213e',
  chartAccent: '#33dcff',

  // Shadow colors
  shadowLight: 'rgba(0, 0, 0, 0.1)',
  shadowMedium: 'rgba(0, 0, 0, 0.15)',
  shadowDark: 'rgba(0, 0, 0, 0.3)',

  // Overlay colors
  overlayLight: 'rgba(255, 255, 255, 0.9)',
  overlayDark: 'rgba(26, 26, 46, 0.9)',

  // Crime type colors (consistent with Forseti palette)
  crimeTypeColors: {
    burglary: '#f44336',
    theft: '#ff9800',
    robbery: '#e83e8c',
    violence: '#f44336',
    drugs: '#6f42c1',
    vandalism: '#ff9800',
    assault: '#f44336',
    weapons: '#1a1a2e',
    fraud: '#20c997',
    other: '#6c757d',
  },
};

export default Colors;
