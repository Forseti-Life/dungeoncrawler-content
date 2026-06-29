/**
 * Community Screen - Web Version
 */

import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import Icon from '../../components/Icon.web';
import { Colors } from '../../utils/colors';

const CommunityScreen: React.FC = () => {
  console.log('👥 CommunityScreen rendering...');

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Icon name="account-group" size={48} color={Colors.primary} />
        <Text style={styles.headerTitle}>Join Our Community</Text>
        <Text style={styles.headerSubtitle}>
          Connect with neighbors and stay informed about safety
        </Text>
      </View>

      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="forum" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Community Forums</Text>
        </View>
        <Text style={styles.cardText}>
          Join discussions with neighbors about safety concerns, local events, and community
          initiatives.
        </Text>
        <TouchableOpacity style={styles.button}>
          <Text style={styles.buttonText}>Visit Forums</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="account-multiple" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Safety Ambassadors</Text>
        </View>
        <Text style={styles.cardText}>
          Connect with trained community safety ambassadors in your neighborhood.
        </Text>
        <TouchableOpacity style={styles.button}>
          <Text style={styles.buttonText}>Find Ambassadors</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="calendar" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Community Events</Text>
        </View>
        <Text style={styles.cardText}>
          Attend local safety workshops, neighborhood watch meetings, and community gatherings.
        </Text>
        <TouchableOpacity style={styles.button}>
          <Text style={styles.buttonText}>View Events</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  button: {
    alignItems: 'center',
    backgroundColor: Colors.primary,
    borderRadius: 8,
    paddingHorizontal: 20,
    paddingVertical: 12,
  },
  buttonText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '600',
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
  cardText: {
    color: Colors.textSecondary,
    fontSize: 14,
    lineHeight: 20,
    marginBottom: 12,
  },
  cardTitle: {
    color: Colors.text,
    fontSize: 18,
    fontWeight: 'bold',
    marginLeft: 8,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  header: {
    alignItems: 'center',
    backgroundColor: Colors.surface,
    padding: 20,
  },
  headerSubtitle: {
    color: Colors.textSecondary,
    fontSize: 14,
    textAlign: 'center',
  },
  headerTitle: {
    color: Colors.text,
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 8,
    marginTop: 12,
  },
});

export default CommunityScreen;
