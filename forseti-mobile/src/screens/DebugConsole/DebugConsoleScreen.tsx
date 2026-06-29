/**
 * Debug Console Screen
 *
 * Full-screen view of all debug logs with scrolling
 */

import React, { useState, useEffect } from 'react';
import { View, Text, ScrollView, StyleSheet, TouchableOpacity, SafeAreaView } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { DebugLogger } from '../../components/DebugConsole';
import { useConsoleLogs } from '../../hooks/useConsoleLogs';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography } = Theme;

interface LogEntry {
  id: number;
  timestamp: string;
  level: 'info' | 'warn' | 'error';
  message: string;
}

const DebugConsoleScreen = ({ navigation }: any) => {
  const [entries, setEntries] = useState<LogEntry[]>([]);
  const [autoScroll, setAutoScroll] = useState(true);
  const scrollViewRef = React.useRef<ScrollView>(null);

  // Console log upload functionality
  const {
    logCount,
    isUploading,
    uploadLogs,
    clearLogs: clearConsoleLogs,
    uploadError,
  } = useConsoleLogs();

  useEffect(() => {
    const unsubscribe = DebugLogger.subscribe(setEntries);
    return unsubscribe;
  }, []);

  useEffect(() => {
    if (autoScroll && scrollViewRef.current) {
      scrollViewRef.current.scrollToEnd({ animated: true });
    }
  }, [entries, autoScroll]);

  const getColor = (level: string) => {
    switch (level) {
      case 'info':
        return '#00D4FF'; // Bright cyan for info (high contrast)
      case 'warn':
        return '#FFB800'; // Bright orange for warnings (high contrast)
      case 'error':
        return '#FF4444'; // Bright red for errors (high contrast)
      default:
        return '#FFFFFF'; // White default
    }
  };

  const getIcon = (level: string) => {
    switch (level) {
      case 'info':
        return 'information';
      case 'warn':
        return 'alert';
      case 'error':
        return 'alert-circle';
      default:
        return 'message';
    }
  };

  const clearLogs = () => {
    DebugLogger.clear();
  };

  const handleUploadLogs = async () => {
    const success = await uploadLogs();
    if (success) {
      DebugLogger.info('Debug logs uploaded successfully to server');
    } else {
      DebugLogger.error(`Failed to upload debug logs: ${uploadError || 'Unknown error'}`);
    }
  };

  const handleClearConsoleLogs = async () => {
    await clearConsoleLogs();
    DebugLogger.info('Debug log storage cleared');
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Icon name="arrow-left" size={24} color={Colors.primary} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Debug Console</Text>
        <View style={styles.headerActions}>
          <TouchableOpacity
            onPress={handleUploadLogs}
            style={[styles.headerButton, isUploading && styles.disabledButton]}
            disabled={isUploading}
          >
            <Icon
              name={isUploading ? 'loading' : 'cloud-upload'}
              size={20}
              color={isUploading ? Colors.textSecondary : Colors.primary}
            />
          </TouchableOpacity>
          <TouchableOpacity onPress={handleClearConsoleLogs} style={styles.headerButton}>
            <Icon name="broom" size={20} color={Colors.warning} />
          </TouchableOpacity>
          <TouchableOpacity onPress={() => setAutoScroll(!autoScroll)} style={styles.headerButton}>
            <Icon
              name={autoScroll ? 'pause' : 'play'}
              size={20}
              color={autoScroll ? Colors.success : Colors.textSecondary}
            />
          </TouchableOpacity>
          <TouchableOpacity onPress={clearLogs} style={styles.headerButton}>
            <Icon name="delete" size={20} color={Colors.danger} />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.statsBar}>
        <Text style={styles.statsText}>
          Debug: {entries.length} (Info: {entries.filter(e => e.level === 'info').length}, Warn:{' '}
          {entries.filter(e => e.level === 'warn').length}, Error:{' '}
          {entries.filter(e => e.level === 'error').length}) | Storage: {logCount} entries
        </Text>
        <Text style={styles.statsHint}>
          📤 Upload debug logs to server | 🧹 Clear debug storage | 📜 Auto-scroll:{' '}
          {autoScroll ? 'ON' : 'OFF'}
        </Text>
      </View>

      {entries.length === 0 ? (
        <View style={styles.emptyState}>
          <Icon name="bug-outline" size={64} color={Colors.lightGray} />
          <Text style={styles.emptyText}>No logs yet</Text>
          <Text style={styles.emptySubtext}>Debug logs will appear here</Text>
        </View>
      ) : (
        <ScrollView
          ref={scrollViewRef}
          style={styles.logContainer}
          contentContainerStyle={styles.logContent}
        >
          {entries.map((entry, index) => (
            <View
              key={entry.id}
              style={[styles.logEntry, { borderLeftColor: getColor(entry.level) }]}
            >
              <View style={styles.logHeader}>
                <Text style={styles.logNumber}>#{index + 1}</Text>
                <Icon name={getIcon(entry.level)} size={16} color={getColor(entry.level)} />
                <Text style={[styles.timestamp, { color: getColor(entry.level) }]}>
                  {new Date(entry.timestamp).toLocaleTimeString()}
                </Text>
                <Text style={[styles.level, { color: getColor(entry.level) }]}>
                  [{entry.level.toUpperCase()}]
                </Text>
              </View>
              <Text style={styles.message}>{entry.message}</Text>
            </View>
          ))}
        </ScrollView>
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000000', // Pure black background
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: Spacing.md,
    backgroundColor: '#1a1a1a', // Dark gray header
    borderBottomWidth: 1,
    borderBottomColor: '#333333',
  },
  backButton: {
    padding: Spacing.xs,
  },
  headerTitle: {
    ...Typography.heading2,
    flex: 1,
    marginLeft: Spacing.md,
    color: '#FFFFFF', // White header text
  },
  headerActions: {
    flexDirection: 'row',
    gap: Spacing.md,
  },
  headerButton: {
    padding: Spacing.xs,
  },
  disabledButton: {
    opacity: 0.5,
  },
  statsBar: {
    backgroundColor: '#1a1a1a', // Dark stats bar
    padding: Spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: '#333333',
  },
  statsText: {
    ...Typography.caption,
    color: '#CCCCCC', // Light gray stats text
    textAlign: 'center',
    marginBottom: 4,
  },
  statsHint: {
    ...Typography.caption,
    fontSize: 11,
    color: '#888888', // Dimmer gray for hint
    textAlign: 'center',
    fontStyle: 'italic',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: Spacing.xl,
  },
  emptyText: {
    ...Typography.heading3,
    color: '#FFFFFF', // White empty text
    marginTop: Spacing.md,
  },
  emptySubtext: {
    ...Typography.body,
    color: '#CCCCCC', // Light gray subtext
    marginTop: Spacing.xs,
  },
  logContainer: {
    flex: 1,
    backgroundColor: '#000000', // Black log container
  },
  logContent: {
    padding: Spacing.sm,
  },
  logEntry: {
    backgroundColor: '#1a1a1a', // Dark gray log entries
    borderRadius: Spacing.borderRadius.md,
    borderLeftWidth: 4,
    padding: Spacing.md,
    marginBottom: Spacing.sm,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.3,
    shadowRadius: 2,
    logNumber: {
      ...Typography.caption,
      fontSize: 10,
      color: '#666666',
      fontWeight: Typography.fontWeight.bold,
      minWidth: 30,
    },
    elevation: 2,
  },
  logHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: Spacing.xs,
    gap: Spacing.xs,
  },
  timestamp: {
    ...Typography.caption,
    fontWeight: Typography.fontWeight.semibold,
    color: '#FFFFFF', // White timestamp (will be overridden by level color)
  },
  level: {
    ...Typography.caption,
    fontWeight: Typography.fontWeight.bold,
    color: '#FFFFFF', // White level (will be overridden by level color)
  },
  message: {
    ...Typography.body,
    color: '#FFFFFF', // Pure white message text for maximum contrast
    lineHeight: 20,
    fontFamily: 'monospace',
    fontSize: 13,
  },
});

export default DebugConsoleScreen;
