/**
 * Web-compatible Icon component
 * Replaces react-native-vector-icons with emoji-based icons for web preview
 */

import React from 'react';
import { Text, TextStyle } from 'react-native';

interface IconProps {
  name: string;
  size?: number;
  color?: string;
  style?: TextStyle;
}

// Map Material Community Icons to emojis for web
const iconMap: { [key: string]: string } = {
  // Navigation & UI (outlined and filled versions)
  home: '🏠',
  'home-outline': '🏠',
  menu: '☰',
  'chevron-right': '›',
  'chevron-left': '‹',
  'chevron-down': '⌄',
  'chevron-up': '⌃',
  'arrow-right': '→',
  'arrow-left': '←',
  close: '✕',
  check: '✓',
  plus: '+',
  minus: '−',

  // Map & Location (outlined and filled versions)
  map: '🗺️',
  'map-outline': '🗺️',
  'map-marker': '📍',
  'map-marker-outline': '📌',
  navigation: '🧭',
  'crosshairs-gps': '🎯',
  target: '🎯',

  // Communication (outlined and filled versions)
  phone: '📞',
  email: '✉️',
  chat: '💬',
  forum: '💭',
  comment: '💬',
  message: '💬',
  robot: '🤖',
  'robot-outline': '🤖',

  // Social (outlined and filled versions)
  account: '👤',
  'account-outline': '👤',
  'account-group': '👥',
  'account-group-outline': '👥',
  'account-multiple': '👥',
  'account-multiple-outline': '👥',
  'shield-account': '🛡️',

  // Safety & Security (outlined and filled versions)
  shield: '🛡️',
  'shield-outline': '🛡️',
  'shield-check': '✅',
  'shield-check-outline': '✅',
  'shield-alert': '⚠️',
  'shield-alert-outline': '⚠️',
  alert: '⚠️',
  'alert-outline': '⚠️',
  'alert-circle': '⚠️',
  'alert-circle-outline': '⚠️',
  information: 'ℹ️',
  'information-outline': 'ℹ️',
  'help-circle': '❓',
  'help-circle-outline': '❓',

  // Status
  loading: '⏳',
  refresh: '🔄',
  sync: '🔄',
  'check-circle': '✅',
  'check-circle-outline': '✅',
  'close-circle': '❌',
  'close-circle-outline': '❌',

  // Actions (outlined and filled versions)
  cog: '⚙️',
  'cog-outline': '⚙️',
  settings: '⚙️',
  tune: '🎛️',
  filter: '🔍',
  'filter-outline': '🔍',
  magnify: '🔍',
  search: '🔍',

  // Safety Features
  lightbulb: '💡',
  'lightbulb-outline': '💡',
  'lightbulb-on': '💡',
  'lightbulb-on-outline': '💡',
  flash: '⚡',
  bell: '🔔',
  'bell-outline': '🔔',
  'bell-alert': '🔔',
  'bell-alert-outline': '🔔',

  // Charts & Data
  'chart-line': '📈',
  'chart-bar': '📊',
  'chart-pie': '📊',
  'trending-up': '📈',
  'trending-down': '📉',

  // Content
  'file-document': '📄',
  'file-document-outline': '📄',
  'book-open': '📖',
  'book-open-outline': '📖',
  newspaper: '📰',
  note: '📝',
  'note-outline': '📝',

  // Media
  camera: '📷',
  'camera-outline': '📷',
  image: '🖼️',
  'image-outline': '🖼️',
  video: '🎥',
  'video-outline': '🎥',

  // Weather
  'weather-sunny': '☀️',
  'weather-cloudy': '☁️',
  'weather-rainy': '🌧️',
  'weather-night': '🌙',

  // Default fallback
  circle: '●',
  'circle-outline': '○',
  square: '■',
  'square-outline': '□',
  triangle: '▲',
  'triangle-outline': '△',
};

const Icon: React.FC<IconProps> = ({ name, size = 24, color = '#000000', style }) => {
  const emoji = iconMap[name] || iconMap[name.replace('mdi-', '')] || '•';

  return (
    <Text
      style={[
        {
          fontSize: size,
          color: color,
          lineHeight: size * 1.2,
          textAlign: 'center',
        },
        style,
      ]}
    >
      {emoji}
    </Text>
  );
};

export default Icon;
